<?php

namespace Moneta;

use Moneta;

class MonetaSdkPayanywayKassa implements MonetaSdkKassa
{
    const PAW_KASSA_URL = 'https://kassa.payanyway.ru/api/api.php';

    public $kassaStorageSettings;
    public $kassaAccountId;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaAccountId = $this->kassaStorageSettings['monetasdk_account_id'];

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
        $url = self::PAW_KASSA_URL;
        $method = "sale";
        $data = @json_decode($document, true);

        $respond = $this->sendHttpRequest($url, $method, $data);

        $result = $respond;
        // пример ответа
        // {"data":true,"isSuccess":true,"info":null}

        if ($respond) {
            $respond = @json_decode($respond, true);
            if (isset($respond['isSuccess']) && $respond['isSuccess'] == 'true') {
                $result = true;
            }
        }

        return $result;
    }

    public function checkDocumentStatus()
    {

    }

    private function sendHttpRequest($url, $method, $data)
    {
        // запрос надо сделать через curl
        $jsonData = json_encode($data);

        $operationUrl = $url . "?method=" . $method . "&accountid=" . $this->kassaAccountId;

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest payanyway Request:\n" . $operationUrl . "\n" . "jsonData: " . $jsonData . "\n");
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

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest payanyway Respond:\n" . $result . "\n");
        }

        curl_close($ch);

        return $result;

    }


}