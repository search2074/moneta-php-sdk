<?php

namespace Moneta;

use Moneta;

class MonetaSdkAtolonlineKassa implements MonetaSdkKassa
{
    public $kassaApiUrl;

    public $kassaApiVersion;

    public $associatedLogin;

    public $associatedPassword;

    public $groupCode;

    public $kassaInn;

    public $kassaAddress;

    public $kassaStorageSettings;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_atol_api_url'];
        $this->kassaApiVersion = $this->kassaStorageSettings['monetasdk_kassa_atol_api_version'];
        $this->associatedLogin = $this->kassaStorageSettings['monetasdk_kassa_atol_login'];
        $this->associatedPassword = str_replace('&amp;', '&', $this->kassaStorageSettings['monetasdk_kassa_atol_password']);
        $this->groupCode = $this->kassaStorageSettings['monetasdk_kassa_atol_group_code'];
        $this->kassaInn = $this->kassaStorageSettings['monetasdk_kassa_inn'];
        $this->kassaAddress = $this->kassaStorageSettings['monetasdk_kassa_address'];
        $this->snoSystem = $this->kassaStorageSettings['monetasdk_kassa_sno_system'];
        $this->companyEmail = $this->kassaStorageSettings['monetasdk_kassa_company_email'];
        $this->vatType = $this->kassaStorageSettings['monetasdk_kassa_vat_type'];
        $this->paymentMethod = $this->kassaStorageSettings['monetasdk_kassa_payment_method'];
        $this->paymentObject = $this->kassaStorageSettings['monetasdk_kassa_payment_object'];
    }

    public function __destruct(){}

    public function authoriseKassa()
    {
        $data = array("login" => $this->associatedLogin, "pass" => $this->associatedPassword);
        $url = $this->kassaApiUrl . "/" . $this->kassaApiVersion . "/";
        $method = "getToken";
        $result = $this->sendHttpRequest($url, $method, $data);
        $result = @json_decode($result, true);
        return (isset($result['token'])) ? $result['token'] : false;
    }

    public function checkKassaStatus(){}

    public function sendDocument($document)
    {
        $document = @json_decode($document, true);
        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendDocument atolonline: Document aata: " .
                print_r($document, true).PHP_EOL);
        }
        $tokenid = $this->authoriseKassa();
        if (!$tokenid) {
            return false;
        }

        $url = $this->kassaApiUrl . "/" . $this->kassaApiVersion . "/";

        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $method = MonetaSdkKassa::ATOL_METHOD_SALE;
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $method = MonetaSdkKassa::ATOL_METHOD_SALE_RETURN;
                break;
            default:
                $method = false;
        }
        if (!$method)
        {
            MonetaSdkUtils::addToLog("sendDocument atolonline: not defined type operation. Data: ".print_r($document, true));
            return false;
        }

        $method = (!$this->groupCode) ? 'null/' . $method : $this->groupCode . '/' . $method;

        // данные чека
        $d = new \DateTime($document['checkoutDateTime']);

        // пример json запроса:
        /*
        {
            "timestamp":"29.05.2017 09:00:01",
            "external_id":"atol-1122334455",
            "receipt":{
                "client":{
                    "email":"test@mail.ru",
                    "phone":"+71111111111"
                },
                "company":{
                    "inn":"123123123",
                    "payment_address":"http://test.ru",
                    "sno":"osn",
                    "email":"support@test.ru"
                },
                "items":[{
                    "name":"Товар 1",
                    "price":10,
                    "quantity":1,
                    "sum":10,
                    "vat":{
                        "type":"vat20"
                    },
                    "payment_method":"full_payment",
                    "payment_object":"payment"
                }],
                "payments":[{
                    "type":1,
                    "sum":10
                }],
                "total":10
            }
        }
        */

        $data = [
            'timestamp' => $d->format('d.m.Y H:i:s'),
            'external_id' => 'atol-' . $document['docNum']
        ];
        $data['receipt']['client']['email'] = $document['email'];
        if($phone = $this->getClientPhone($document)) {
            $data['receipt']['client']['phone'] = $phone;
        }
        $data['receipt']['company'] = [
            'inn' => $this->kassaInn,
            'payment_address' => $this->kassaAddress,
            'sno' => $this->snoSystem,
            'email' => $this->companyEmail,
        ];

        $items = [];
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions) && !empty($inventPositions)) {
            foreach ($inventPositions AS $position) {
                // productName подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = (isset($position['name']) && is_string($position['name'])) ?
                    MonetaSdkUtils::convertEscapedUnicode($position['name']) : '';

                $items[] = [
                    'name' => (string)$position['name'],
                    'price' => floatval($position['price']),
                    'quantity' => intval($position['quantity']),
                    'sum' => floatval($position['price'] * $position['quantity']),
                    'vat' => [
                        'type' => $this->vatType,
                    ],
                    'payment_method' => $this->paymentMethod,
                    'payment_object' => $this->paymentObject,
                ];
            }
        }

        $data['receipt']['items'] = $items;

        $totalAmount = 0;
        $payments = [];
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $payments[] = [
                    'type' => 1,
                    'sum' => floatval($moneyPosition['sum']),
                ];
                $totalAmount = $totalAmount + $moneyPosition['sum'];
            }
        }

        $data['receipt']['payments'] = $payments;
        $data['receipt']['total'] = $totalAmount;

        //$data['service']['inn'] = $this->kassaInn;

//        if (isset($document['responseURL']) && $document['responseURL']) {
//            $data['service']['callback_url'] = $document['responseURL'];
//        }

//        $data['service']['payment_address'] = $this->kassaAddress;

        $respond = $this->sendHttpRequest($url, $method, $data, $tokenid);

        $result = $respond;
        // пример успешного ответа:
        /*
            {
                "uuid":"9afafa6a-0a00-444d-1a45-12345a12f2ff",
                "status":"wait",
                "error":null,
                "timestamp":"29.11.2021 10:00:01"
            }
        */
        // пример ответа с ошибкой
        /*
            {
                "uuid":"9afafa6a-0a00-444d-1a45-12345a12f2ff",
                "status":"wait",
                "error":{
                    "code":33,
                    "error_id":"dba2abc1-01a1-3ab5-ba11-12adc132b15f",
                    "text":"В системе существует чек с external_id : \"atol-1122334455\" и group_code: \"mac-test-ru_1122\"",
                    "type":"system"
                },
                "timestamp":"29.11.2021 10:00:01"
            }
        */
        if ($respond) {
            $respondArray = @json_decode($respond, true);
            if (is_array($respondArray) && count($respondArray)) {
                foreach ($respondArray AS $respondItemKey => $respondItemValue) {
                    if ($respondItemKey == 'error' && (!$respondItemValue || $respondItemValue == 'null')) {
                        $result = true;
                    }
                }
            }
        }

        // return $result . " | " . $url . $method;
        return $result;
    }

    public function checkDocumentStatus(){}

    private function getClientPhone($document)
    {
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
            $clientPhone = '+' . $clientPhone;
            return $clientPhone;
        }
        return null;
    }

    private function sendHttpRequest($url, $method, $data, $tokenid = null)
    {
        $jsonData = json_encode($data);

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest atolonline Request:\n" . $url . $method . "\n" . "jsonData: " . $jsonData . "\n");
        }

        $operationUrl = $url . $method;
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
        ];
        if ($tokenid) {
            $operationUrl .= "?tokenid=" . $tokenid;
            $headers[] = "Token: {$tokenid}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $operationUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if ($result === false) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest atolonline Response error:\n" . var_export(curl_error($ch), true) . "\n");
            }
        }
        else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest atolonline Response:\n" . $result . "\n");
            }
        }

        curl_close($ch);
        return $result;
    }
}