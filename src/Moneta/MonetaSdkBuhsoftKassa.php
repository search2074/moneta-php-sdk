<?php

namespace Moneta;

use Moneta;

class MonetaSdkBuhsoftKassa implements MonetaSdkKassa
{
    public $kassaStorageSettings;
    public $kassaApiUrl;
    private $token = '';

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_buhsoft_api_url'];
        $this->token = $this->kassaStorageSettings['monetasdk_kassa_buhsoft_token'];
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
        $url = $this->kassaApiUrl;
        $method = "docs/add/";

        // данные чека
        $data = [
            'phone' => $document['phone'],
            'email' => $document['email'],
            'print' => 0,                   //0 - не печатать; 1 - печать чека
            'typeOperation' => 0,           //0 - продажа, 1 - возврат
            'price[nal]' => floatval($document['moneyPositions']['nal']),
            'price[bnal]' => floatval($document['moneyPositions']['bnal']),
            'name_cashier' => null,         // если хотите пропустить
            'token' => $this->token
        ];

        $items = [];
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions)) {
            foreach ($inventPositions AS $position) {
                $tax = MonetaSdkKassa::BUHSOFT_NONE;
                switch ($position['vatTag']) {
                    case MonetaSdkKassa::VAT0:
                        $tax = MonetaSdkKassa::BUHSOFT_VAT0;
                        break;
                    case MonetaSdkKassa::VAT10:
                        $tax = MonetaSdkKassa::BUHSOFT_VAT10;
                        break;
                    case MonetaSdkKassa::VAT18:
                        $tax = MonetaSdkKassa::BUHSOFT_VAT18;
                        break;
                }

                // name подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = MonetaSdkUtils::convertEscapedUnicode($position['name']);

                $items[] = [
                    "name" => $position['name'],
                    "count" => floatval($position['quantity']),
                    "price" => floatval($position['price']),
                    "type_nds" => $tax,
                ];
            }
        }
        $data['data'] = $items;

        $respond = $this->sendHttpRequest($url, $method, $data);
        // пример ответа
        // {"ok":"1","guid":"7c5bb631-bd9e-d937-c632-af88266a4b47","time":"\u0421\u0435\u0440\u0432\u0435\u0440 \u0432\u044b\u043a\u043b\u044e\u0447\u0435\u043d"}

        $result = false;
        if ($respond) {
            $respondArray = @json_decode($respond, true);
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendDocument buhsoft Response parsed:\n" . print_r($respondArray, true) . "\n");
            }
            if (is_array($respondArray) && count($respondArray) && (1 == $respondArray['ok'])) {
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
        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest buhsoft Request:\n" . $url . $method . "\n" . "Data: " . print_r($data, true) . "\n");
        }

        $operationUrl = $url . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $operationUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest buhsoft Response error:\n" . var_export(curl_error($ch), true) . "\n");
            }
        } else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest buhsoft Response origin:\n" . $result . "\n");
            }
        }
        curl_close($ch);
        return $result;
    }
}