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

    }

    public function __destruct()
    {

    }

    public function authoriseKassa()
    {
        $data = array("login" => $this->associatedLogin, "pass" => $this->associatedPassword);
        $url = $this->kassaApiUrl . "/" . $this->kassaApiVersion . "/";
        $method = "getToken";
        $result = $this->sendHttpRequest($url, $method, $data);
        $result = @json_decode($result, true);
        return (isset($result['token'])) ? $result['token'] : false;
    }

    public function checkKassaStatus()
    {

    }

    public function sendDocument($document)
    {
        $document = @json_decode($document, true);
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
        $data = array('timestamp' => $d->format('d.m.Y H:i:s'), 'external_id' => 'atol-' . $document['docNum']);
        $data['receipt']['attributes']['email'] = $document['email'];
        $data['receipt']['attributes']['phone'] = '';

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
            $clientPhone = '+' . $clientPhone;
        }
        // установим в документ номер телефона вместо e-mail
        if ($clientPhone) {
            $data['receipt']['attributes']['phone'] = $clientPhone;
            $data['receipt']['attributes']['email'] = '';
        }

        $items = array();
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions) && count($inventPositions)) {
            foreach ($inventPositions AS $position) {
                $tax = MonetaSdkKassa::ATOL_NONE;
                if (isset($position['vatTag'])) {
                    switch ($position['vatTag']) {
                        case MonetaSdkKassa::VAT0:
                            $tax = MonetaSdkKassa::ATOL_VAT0;
                            break;
                        case MonetaSdkKassa::VAT10:
                            $tax = MonetaSdkKassa::ATOL_VAT10;
                            break;
                        case MonetaSdkKassa::VAT18:
                            $tax = MonetaSdkKassa::ATOL_VAT18;
                            break;
                        case MonetaSdkKassa::VATWR10:
                            $tax = MonetaSdkKassa::ATOL_VAT110;
                            break;
                        case MonetaSdkKassa::VATWR18:
                            $tax = MonetaSdkKassa::ATOL_VAT118;
                            break;
                    }
                }

                // productName подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = (isset($position['name']) && is_string($position['name'])) ? MonetaSdkUtils::convertEscapedUnicode($position['name']) : '';

                $items[] = array(
                    'price' => floatval($position['price']), 'name' => (string)$position['name'], 'quantity' => intval($position['quantity']),
                    'sum' => floatval($position['price'] * $position['quantity']), 'tax' => $tax
                );
            }
        }

        $data['receipt']['items'] = $items;

        $totalAmount = 0;
        $payments = array();
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $payments[] = array('type' => 1, 'sum' => floatval($moneyPosition['sum']));
                $totalAmount = $totalAmount + $moneyPosition['sum'];
            }
        }

        $data['receipt']['payments'] = $payments;
        $data['receipt']['total'] = $totalAmount;

        $data['service']['inn'] = $this->kassaInn;

        if (isset($document['responseURL']) && $document['responseURL']) {
            $data['service']['callback_url'] = $document['responseURL'];
        }

        $data['service']['payment_address'] = $this->kassaAddress;

        $respond = $this->sendHttpRequest($url, $method, $data, $tokenid);

        $result = $respond;
        // пример ответа
        // {"uuid":"ea5991ab-05f3-4c10-980a-3b3f3d58ed13","timestamp":"18.05.2017 16:33:23","status":"wait","error":null}
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

    public function checkDocumentStatus()
    {

    }

    private function sendHttpRequest($url, $method, $data, $tokenid = null)
    {
        // запрос надо сделать через curl
        $jsonData = json_encode($data);

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest atolonline Request:\n" . $url . $method . "\n" . "jsonData: " . $jsonData . "\n");
        }

        $operationUrl = $url . $method;
        if ($tokenid) {
            $operationUrl .= "?tokenid=" . $tokenid;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $operationUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData))
        );

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