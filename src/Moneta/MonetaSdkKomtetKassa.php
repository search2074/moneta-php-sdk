<?php

namespace Moneta;

use Moneta;

use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\Exception\SdkException;

class MonetaSdkKomtetKassa implements MonetaSdkKassa
{
    // основные настройки кассы
    public $kassaStorageSettings;

    // идентификатор магазина, созданного в личном кабинете
    public $kassaShopId;

    // идентификатор очереди, созданной в личном кабинете.
    public $kassaQueueId;

    // произвольный псевдоним для обращения к очереди
    public $kassaQueueName = '';

    // секретный ключ
    private $secret = '';

    // менеджер работы с очередями и чеками
    private $manager = '';

    // экземпляр класса для формирования чека
    private $check = '';

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaShopId = $this->kassaStorageSettings['monetasdk_kassa_komtet_shop_id'];
        $this->kassaQueueId = $this->kassaStorageSettings['monetasdk_kassa_komtet_queue_id'];
        $this->kassaQueueName = $this->kassaStorageSettings['account_id'];
        $this->secret = $this->kassaStorageSettings['monetasdk_kassa_komtet_secret'];

        require_once $this->kassaStorageSettings['libs_files_path']
            . DIRECTORY_SEPARATOR . 'komtet-kassa-php-sdk'
            . DIRECTORY_SEPARATOR . 'autoload.php';
    }

    public function __destruct()
    {
    }

    public function authoriseKassa()
    {
    }

    public function checkKassaStatus()
    {
    }

    public function checkDocumentStatus()
    {
    }

    public function sendDocument($document)
    {
        // данные чека
        $document = @json_decode($document, true);

        $client = new Client($this->kassaShopId, $this->secret);
        $this->manager = new QueueManager($client);

        $this->kassaQueueName .= $document['docNum'];

        $this->manager->registerQueue($this->kassaQueueName, $this->kassaQueueId);
        $this->manager->setDefaultQueue($this->kassaQueueName);

        $checkID = $document['docNum'];

        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $this->check = Check::createSell($checkID, $document['email'], Check::TS_SIMPLIFIED_IN);
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $this->check = Check::createSellReturn($checkID, $document['email'], Check::TS_SIMPLIFIED_IN);
                break;
            default:
                $this->check = Check::createSell($checkID, $document['email'], Check::TS_SIMPLIFIED_IN);
        }

        // нужно ли распечатать чек
        $this->check->setShouldPrint(false);

        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions)) {
            foreach ($inventPositions AS $key => $position) {
                $tax = Vat::RATE_NO;
                switch ($position['vatTag']) {
                    case MonetaSdkKassa::VAT0:
                        $tax = Vat::RATE_0;
                        break;
                    case MonetaSdkKassa::VAT10:
                        $tax = Vat::RATE_10;
                        break;
                    case MonetaSdkKassa::VAT18:
                        $tax = Vat::RATE_18;
                        break;
                    case MonetaSdkKassa::VATWR10:
                        $tax = Vat::RATE_110;
                        break;
                    case MonetaSdkKassa::VATWR18:
                        $tax = Vat::RATE_118;
                        break;
                }
                $vat = new Vat($tax);

                // name подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = MonetaSdkUtils::convertEscapedUnicode($position['name']);

                $position['price']      = floatval($position['price']);
                $position['quantity']   = floatval($position['quantity']);
                $position_amount        = round($position['price'] * $position['quantity'], 2);

                // Позиция в чеке:
                $this->check->addPosition(
                    new Position(
                        //имя
                        $position['name'],
                        //цена
                        $position['price'],
                        //кол-во
                        $position['quantity'],
                        //общая стоимость
                        $position_amount,
                        //скидка
                        0,
                        //налог
                        $vat
                    )
                );
            }
        }

        $totalAmount = 0;
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $totalAmount = $totalAmount + $moneyPosition['sum'];
            }
        }

        // Итоговая сумма расчёта
        $payment = Payment::createCard($totalAmount); // или createCash при оплате наличными
        $this->check->addPayment($payment);

        $respond = $this->sendHttpRequest();
        /*
        пример ответа
        Array
        (
            [state] => new
            [print_queue_id] => 4354
            [id] => 1812
            [external_id] => 1505827870
        )
        */

        $result = false;
        if ($respond) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendDocument komtet Response parsed:\n" . print_r($respond, true) . "\n");
            }
            if (is_array($respond) && count($respond) && 'new' == $respond['state']) {
                $result = true;
            }
        }
        return $result;
    }

    private function sendHttpRequest()
    {
        $result = false;

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest komtet Request:\n" . "Data: " . print_r($this->check, true) . "\n");
        }
        // Добавляем чек в очередь.
        //получить состояние очереди:
        if ($this->manager->isQueueActive($this->kassaQueueName)) {
            try {
                //$manager->putCheck($check, $queueName); // в случае, если очередь по умолчанию не определена
                $result = $this->manager->putCheck($this->check);
                if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                    MonetaSdkUtils::addToLog("sendHttpRequest komtet Response origin:\n" . print_r($result, true) . "\n");
                }
            } catch (SdkException $e) {
                if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                    MonetaSdkUtils::addToLog("sendHttpRequest komtet Response error:\n" . $e->getMessage() . "\n");
                }
            }
        } else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest komtet Queue NOT active." . "\n");
            }
        }
        return $result;
    }
}