<?php

namespace Moneta;

use Moneta;
use orangedata;

class MonetaSdkOrangedataKassa implements MonetaSdkKassa
{
    public $kassaStorageSettings;
    public $kassaApiUrl;
    public $taxMode;
    private $certPath;
    private $certKeyPath;
    private $certPswd;
    private $signKeyPath;
    private $caCertPath;
    private $inn;
    private $paymentType = 4;                                       // полный расчёт
    private $paymentSubjectType = 10;                               // платёж
    private $paymentMethod = 2;                                     // сумма по чеку электронными
    private $byer;                                                  // экземпляр класса библиотеки orangedata
    private $kassaApiUrlTest = 'https://apip.orangedata.ru:2443';   // api url для тестирования

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_orangedata_api_url'];

        $this->kassaApiUrl = $this->kassaApiUrlTest;

        $this->taxMode = (int)$this->kassaStorageSettings['monetasdk_kassa_orangedata_tax_mode'];
        $this->certPswd = $this->kassaStorageSettings['monetasdk_kassa_orangedata_cert_pswd'];
        $this->inn = $this->kassaStorageSettings['monetasdk_kassa_inn'];

        $path = $this->kassaStorageSettings['cert_files_path'] . 'orangedata'
            . DIRECTORY_SEPARATOR . $this->kassaStorageSettings['account_id']
            . DIRECTORY_SEPARATOR;
        $this->certPath = $path . $this->inn . '.crt';
        $this->certKeyPath = $path . $this->inn . '.key';
        $this->signKeyPath = $path . $this->inn . '.pem';
        $this->caCertPath = $path . '..' . DIRECTORY_SEPARATOR . 'cacert.pem';

        require_once $this->kassaStorageSettings['libs_files_path']
            . DIRECTORY_SEPARATOR . 'orangedata'
            . DIRECTORY_SEPARATOR . 'orangedata_client.php';
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

    public function sendDocument($document)
    {
        // данные чека
        $document = @json_decode($document, true);

        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $operationType = MonetaSdkKassa::ORANGEDATA_DOC_TYPE_SALE;
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $operationType = MonetaSdkKassa::ORANGEDATA_DOC_TYPE_SALE_RETURN;
                break;
            default:
                $operationType = MonetaSdkKassa::ORANGEDATA_DOC_TYPE_SALE;
        }

        // телефон или e-mail?
        $clientPhone = (isset($document['phone'])) ? $document['phone'] : null;
        $clientEmail = $document['email'];
        if (!$clientPhone && $clientEmail && strpos($clientEmail, '@') === false) {
            $clientPhone = $clientEmail;
            $clientEmail = null;
        }
        if ($clientPhone) {
            // формат нужен +7-ххх-ххх-хххх
            $pattern = "/[^0-9]/i";
            $clientPhone = preg_replace($pattern, "", $clientPhone);
            // код страны - 7: Россия
            if (preg_match("/^[78]9/i", $clientPhone)) {
                $clientPhone = preg_replace("/^8/i", "7", $clientPhone);  // если первая цифра номера «8», то заменим ее на «7»
            }
            if (preg_match("/^9/i", $clientPhone) && strlen($clientPhone) == 10) {
                $clientPhone = "7" . $clientPhone;
            }
            $clientPhone = '+' . $clientPhone;
        }
        // установим в документ контакт клиента: email в приоритете, если телефон тоже указан.
        if ($clientEmail) {
            $clientContact = $clientEmail;
        } else {
            $clientContact = $clientPhone;
        }


        $items = array();
        if (is_array($document['inventPositions'])) {
            foreach ($document['inventPositions'] AS $position) {
                $tax = MonetaSdkKassa::ORANGEDATA_NONE;
                switch ($position['vatTag']) {
                    case MonetaSdkKassa::VAT0:
                        $tax = MonetaSdkKassa::ORANGEDATA_VAT0;
                        break;
                    case MonetaSdkKassa::VAT10:
                        $tax = MonetaSdkKassa::ORANGEDATA_VAT10;
                        break;
                    case MonetaSdkKassa::VAT18:
                        $tax = MonetaSdkKassa::ORANGEDATA_VAT18;
                        break;
                    case MonetaSdkKassa::VATWR10:
                        $tax = MonetaSdkKassa::ORANGEDATA_VAT110;
                        break;
                    case MonetaSdkKassa::VATWR18:
                        $tax = MonetaSdkKassa::ORANGEDATA_VAT118;
                        break;
                }

                // Description подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = MonetaSdkUtils::convertEscapedUnicode($position['name']);

                //поле Description НЕ должно содержать символов, которые отсутствуют в кодировке CP866
                $position['name'] = preg_replace('/[^0-9a-zA-Zа-яА-ЯёЁ\+\(\) ]/ui', '', $position['name']);

                $items[] = array(
                    "quantity" => floatval($position['quantity']),
                    "price" => floatval($position['price']),
                    "vatTag" => $tax,
                    "name" => $position['name'],
                );
            }
        }

        $totalAmount = 0;
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $totalAmount = $totalAmount + ($moneyPosition['sum']);
            }
        }


        $this->byer = new orangedata\orangedata_client(
            $this->inn,
            $this->kassaApiUrl,
            $this->signKeyPath,
            $this->certKeyPath,
            $this->certPath,
            $this->caCertPath,
            $this->certPswd
        );
        $this->byer->create_order($document['docNum'], $operationType, $clientContact, $this->taxMode);

        foreach ($items as $key => $item) {
            $this->byer->add_position_to_order($item['quantity'], $item['price'], $item['vatTag'], $item['name'], $this->paymentType, $this->paymentSubjectType);
        }

        $this->byer->add_payment_to_order($this->paymentMethod, $totalAmount);

        $respondRequest = $this->sendHttpRequest();
        //ответ в случае успеха: true
        //ответ в случае ошибки:
        //Ошибка: Conflict. Order with same id is already exists in the system.
        //{"id":"111","deviceSN":"1400000000001226","deviceRN":"0000000400054952","fsNumber":"9999078900001341","ofdName":"ООО \"Ярус\" (\"ОФД-Я\")","ofdWebsite":"www.ofd-ya.ru","ofdinn":"7728699517","fnsWebsite":"www.nalog.ru","companyINN":"1215192632","companyName":"НКО «МОНЕТА.РУ»","documentNumber":2271,"shiftNumber":5434,"documentIndex":2727,"processedAt":"2017-11-13T14:05:00.939996","content":{"type":1,"positions":[{"quantity":1.37,"price":100.0,"tax":1,"text":"Булочка с маком","paymentMethodType":4,"paymentSubjectType":10},{"quantity":1.0,"price":200.32,"tax":2,"text":"Батон","paymentMethodType":4,"paymentSubjectType":10}],"checkClose":{"payments":[{"type":2,"amount":337.32}],"taxationSystem":1},"customerContact":"dmitry.elsukov@moneta.com"},"change":0.0,"fp":"1986462328"}

        $result = false;
        $respondArray = null;
        if (true == $respondRequest) {
            $iteration = 1;
            while ($iteration <= 2) {
                usleep(5000000);
                $respondStatus = $this->checkDocumentStatus($document['docNum']);
                $respondArray = @json_decode($respondStatus, true);
                if (is_array($respondArray) && count($respondArray)) {
                    break;
                }
                $iteration++;
            }
        }

        //Ответ на запрос статуса документа
        // в случае успеха:
        //{"id":"1510570822","deviceSN":"1400000000001226","deviceRN":"0000000400054952","fsNumber":"9999078900001341","ofdName":"ООО \"Ярус\" (\"ОФД-Я\")","ofdWebsite":"www.ofd-ya.ru","ofdinn":"7728699517","fnsWebsite":"www.nalog.ru","companyINN":"1215192632","companyName":"НКО «МОНЕТА.РУ»","documentNumber":4210,"shiftNumber":4773,"documentIndex":1279,"processedAt":"2017-11-13T13:59:24.37603","content":{"type":1,"positions":[{"quantity":1.37,"price":100.0,"tax":1,"text":"Булочка с маком","paymentMethodType":4,"paymentSubjectType":10},{"quantity":1.0,"price":200.32,"tax":2,"text":"Батон","paymentMethodType":4,"paymentSubjectType":10}],"checkClose":{"payments":[{"type":2,"amount":337.32}],"taxationSystem":1},"customerContact":"dmitry.elsukov@moneta.com"},"change":0.0,"fp":"1217030075"}
        //в случае ошибка: текстовая строка

        if (is_array($respondArray) && count($respondArray)) {
            $result = isset($respondArray['id']) ? true : false;
        } else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendDocument orangedata error in response status:\n" . $respondArray . "\n");
            }
        }
        return $result;
    }

    private function sendHttpRequest()
    {
        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest orangedata send document:\n" . var_export($this->byer, true) . "\n");
        }

        try {
            $result = $this->byer->send_order();
        } catch (Exception $ex) {
            $result = PHP_EOL . $ex->getMessage();
        }

        if (true !== $result) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest orangedata send document error:\n" . $result . "\n");
            }
        } else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest orangedata send document success:\n" . $result . "\n");
            }
        }
        return $result;
    }

    public function checkDocumentStatus($documentNum = '')
    {
        try {
            $order_result = $this->byer->get_order_status($documentNum);
        } catch (Exception $ex) {
            $order_result = PHP_EOL . $ex->getMessage();
        }
        return $order_result;
    }
}