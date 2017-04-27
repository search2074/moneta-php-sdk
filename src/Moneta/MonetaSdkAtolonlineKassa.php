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

    public $kassaStorageSettings;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_atol_api_url'];
        $this->kassaApiVersion = $this->kassaStorageSettings['monetasdk_kassa_atol_api_version'];
        $this->associatedLogin = $this->kassaStorageSettings['monetasdk_kassa_atol_login'];
        $this->associatedPassword = $this->kassaStorageSettings['monetasdk_kassa_atol_password'];
        $this->groupCode = $this->kassaStorageSettings['monetasdk_kassa_atol_group_code'];
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
        $tokenid = $this->authoriseKassa();
        if (!$tokenid) {
            return false;
        }

        $url = $this->kassaApiUrl . "/" . $this->kassaApiVersion . "/";
        $method = $this->groupCode . "/sell";

        // данные чека
        $document = @json_decode($document, true);

        $d = new \DateTime($document['checkoutDateTime']);
        $data = array('timestamp' => $d->format('d.m.Y H:i:s a'), 'external_id' => $document['docNum']);
        $data['attributes']['email'] = $document['email'];

        $items = array();
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions) && count($inventPositions)) {
            foreach ($inventPositions AS $position) {
                $items[] = array('price' => $position['price'], 'name' => $position['name'], 'quantity' => $position['quantity'], 'sum' => $position['price'] * $position['quantity']);
            }
        }

        $data['items'] = $items;

        $totalAmount = 0;
        $payments = array();
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $payments[] = array('type' => 0, 'sum' => $moneyPosition['sum']);
                $totalAmount = $totalAmount + $moneyPosition['sum'];
            }
        }

        $data['payments'] = $payments;
        $data['total'] = $totalAmount;

        $result = $this->sendHttpRequest($url, $method, $data, $tokenid);
        return json_decode($result, true);
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

        $res = curl_exec($ch);
        if ($res === false) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest atolonline Response:\n" . var_export(curl_error($ch), true) . "\n");
            }
        }

        curl_close($ch);
        return $result;

    }


}