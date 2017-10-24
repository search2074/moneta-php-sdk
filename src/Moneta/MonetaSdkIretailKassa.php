<?php

namespace Moneta;

use Moneta;

class MonetaSdkIretailKassa implements MonetaSdkKassa
{
    // Основные настройки кассы
    public $kassaStorageSettings;

    // URL API онлайн кассы
    public $kassaApiUrl = '';

    // Метод, вызывемый при обращении к API
    private $kassaApiMethod = 'create';

    // Ключ к API онлайн кассы
    public $kassaApiKey = '';

    // Логин от личного кабинета онлайн кассы
    public $login = '';

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_iretail_api_url'];
        $this->kassaApiKey = $this->kassaStorageSettings['monetasdk_kassa_iretail_api_key'];
        $this->login = $this->kassaStorageSettings['monetasdk_kassa_iretail_login'];
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

        $data = [
            'api_key' => $this->kassaApiKey,
            'login' => $this->login,
            'date_time' => $document['checkoutDateTime'],
            'external_order_id' => $document['docNum'], //номер заказа в магазине
            'mode' => 'email', // отправить электронный чек по email и/или смс
            'type' => 'payment',
        ];

        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $data['type'] = MonetaSdkKassa::IRETAIL_DOC_TYPE_SALE;
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $data['type'] = MonetaSdkKassa::IRETAIL_DOC_TYPE_SALE_RETURN;
                break;
        }

        $data['customer_email'] = $document['email'];

        // телефон или e-mail?
        $clientPhone = (isset($document['phone'])) ? $document['phone'] : null;
        $clientEmail = $document['email'];
        if (!$clientPhone && $clientEmail && strpos($clientEmail, '@') === false) {
            $clientPhone = $clientEmail;
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
        }
        // установим в документ номер телефона вместо e-mail
        if ($clientPhone) {
            $data['customer_phone'] = $clientPhone;
            $data['customer_email'] = '';
        }
        else {
            $data['customer_phone'] = '';
        }

        $totalAmount = 0;
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $totalAmount = $totalAmount + $moneyPosition['sum'];
            }
        }
        $data['card_amount'] = floatval($totalAmount); // сумма, оплаченная клиентом по карте(Внешний неинтегрированный эквайринг)

        $items = [];
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions)) {
            foreach ($inventPositions AS $position) {
                switch ($position['vatTag']) {
                    case MonetaSdkKassa::VAT0:
                        $tax = MonetaSdkKassa::IRETAIL_VAT0;
                        break;
                    case MonetaSdkKassa::VAT10:
                        $tax = MonetaSdkKassa::IRETAIL_VAT10;
                        break;
                    case MonetaSdkKassa::VAT18:
                        $tax = MonetaSdkKassa::IRETAIL_VAT18;
                        break;
                    default:
                        $tax = false;
                }

                // name подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = (MonetaSdkUtils::getValueFromArray('name', $position))
                                    ?
                                    MonetaSdkUtils::convertEscapedUnicode($position['name'])
                                    :
                                    '';
                $items[] = [
                    "name" => $position['name'],
                    "quantity" => floatval($position['quantity']),
                    "price" => floatval($position['price']),
                    /*
                    'unit' => 'kg',
                    'discount' => [
                        'type' => 'amount',
                        'value' => 2
                    ]
                    */
                ];
                if($tax)
                {
                    $items[(count($items) - 1)]['vat'] = $tax;
                }
            }
        }
        $data['purchase']['products'] = $items;

        if(MonetaSdkUtils::getValueFromArray('responseURL', $document))
        {
            $data['callback_url'] = $document['responseURL'];
        }

        $respond = $this->sendHttpRequest($data);
        // пример ответа
        // {"status":true,"result":{"order_id":264604,"external_order_id":"1241","order_number":"E-17-0921-013818-00002-00011","login":"79061388880"}}

        $result = false;
        if ($respond) {
            $respondArray = @json_decode($respond, true);
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendDocument iretail Response parsed:\n" . print_r($respondArray, true) . "\n");
            }
            if (is_array($respondArray)
                && count($respondArray)
                && (true == $respondArray['status'])
                && (isset($respondArray['result']['order_number'])))
            {
                $result = true;
            }
        }
        return $result;
    }

    private function sendHttpRequest($data)
    {
        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest iretail Request:\n" . $this->kassaApiUrl . $this->kassaApiMethod . "\n" . "Data: " . print_r($data, true) . "\n");
        }
        $queryData = http_build_query($data, '', '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->kassaApiUrl . $this->kassaApiMethod);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryData);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest iretail Response error:\n" . var_export(curl_error($ch), true) . "\n");
            }
        } else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest iretail Response origin:\n" . $result . "\n");
            }
        }
        curl_close($ch);
        return $result;
    }
}