<?php

namespace Moneta;

use Moneta;

class MonetaSdkStarrysKassa implements MonetaSdkKassa
{
    public $kassaStorageSettings;
    public $kassaApiUrl;
    public $kassaApiVersion;
    public $taxMode;
    private $clientId;
    private $certPath;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_starrys_api_url'];
        $this->kassaApiVersion = $this->kassaStorageSettings['monetasdk_kassa_starrys_api_version'];
        $this->taxMode = $this->kassaStorageSettings['monetasdk_kassa_starrys_tax_mode'];
        $this->clientId = $this->kassaStorageSettings['monetasdk_kassa_starrys_client_id'];
        $this->certPath = $this->kassaStorageSettings['cert_files_path'] . DIRECTORY_SEPARATOR . 'starrys'
                . DIRECTORY_SEPARATOR . $this->kassaStorageSettings['account_id']
                . DIRECTORY_SEPARATOR . $this->kassaStorageSettings['account_id'] . '.pem';
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
        $url = $this->kassaApiUrl . "/" . $this->kassaApiVersion . "/";
        $method = "Complex";

        // данные чека
        $document = @json_decode($document, true);

        $data = [
            "Device" => "auto",
            "ClientId" => $this->clientId,
            "RequestId" => (string)$document['docNum'],
            //"Password"  => 1,
        ];

        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $data['DocumentType'] = MonetaSdkKassa::STARRYS_DOC_TYPE_SALE;
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $data['DocumentType'] = MonetaSdkKassa::STARRYS_DOC_TYPE_SALE_RETURN;
                break;
            default:
                $data['DocumentType'] = MonetaSdkKassa::STARRYS_DOC_TYPE_SALE;
        }

        $items = [];
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions)) {
            foreach ($inventPositions AS $position) {
                $tax = MonetaSdkKassa::STARRYS_NONE;
                switch ($position['vatTag']) {
                    case MonetaSdkKassa::VAT0:
                        $tax = MonetaSdkKassa::STARRYS_VAT0;
                        break;
                    case MonetaSdkKassa::VAT10:
                        $tax = MonetaSdkKassa::STARRYS_VAT10;
                        break;
                    case MonetaSdkKassa::VAT18:
                        $tax = MonetaSdkKassa::STARRYS_VAT18;
                        break;
                    case MonetaSdkKassa::VATWR10:
                        $tax = MonetaSdkKassa::STARRYS_VAT110;
                        break;
                    case MonetaSdkKassa::VATWR18:
                        $tax = MonetaSdkKassa::STARRYS_VAT118;
                        break;
                }

                // Description подвергнуть преобразованию ESCAPED_UNICODE
                $position['name'] = MonetaSdkUtils::convertEscapedUnicode($position['name']);

                //поля Description и PhoneOrEmail НЕ должны содержать символов, которые отсутствуют в кодировке CP866
                $position['name'] = preg_replace('/[^0-9a-zA-Zа-яА-ЯёЁ\+\(\) ]/ui', '', $position['name']);

                $items[] = [
                    "Qty" => round(round(floatval($position['quantity']), 4) * 1000),
                    "Price" => round(round(floatval($position['price']), 2) * 100),
                    "PayAttribute" => 4,
                    "TaxId" => $tax,
                    "Description" => $position['name']
                ];
            }
        }

        $totalAmount = 0;
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $totalAmount = $totalAmount + (round($moneyPosition['sum'], 2) * 100);
            }
        }

        $data['Lines'] = $items;
        //$data['Cash'] = $document['moneyPositions']['sum'];
        $data['NonCash'] = [$totalAmount, 0, 0];
        $data['TaxMode'] = intval($this->taxMode);
        $data['PhoneOrEmail'] = $document['email'];
        //$data['Place'] = 'www.example.com';
        $data['FullResponse'] = false;

        $respond = $this->sendHttpRequest($url, $method, $data);
        // пример ответа(короткий вариант)
        // {"ClientId":"52","Date":{"Date":{"Day":7,"Month":9,"Year":17},"Time":{"Hour":14,"Minute":15,"Second":21}},"Device":{"Name":"10000000000000000048","Address":"192.168.142.20:4048"},"DeviceRegistrationNumber":"2505480089058565","DeviceSerialNumber":"10000000000000000048","DocNumber":22,"DocumentType":0,"FNSerialNumber":"9999999999999048","FiscalDocNumber":26,"FiscalSign":1244333967,"GrandTotal":20000,"Path":"/fr/api/v2/Complex","QR":"t=20170907T1415\u0026s=200.00\u0026fn=9999999999999048\u0026i=26\u0026fp=1244333967\u0026n=1","RequestId":"235fwe6f23ET435152","Response":{"Error":0}}

        $result = false;

        // return "url: ". $url . $method. ", respond: {$respond}";

        if ($respond) {
            $respondArray = @json_decode($respond, true);
            if (is_array($respondArray) && count($respondArray)) {
                $errorCode = isset($respondArray['Response']['Error']) ? intval($respondArray['Response']['Error']) : -1;
                if (0 == $errorCode) {
                    $result = true;
                }
            } else {
                if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                    MonetaSdkUtils::addToLog("sendDocument starrys error in response:\n" . $respondArray . "\n");
                }
            }
        }

        if (!$result) {
            $result = $respond;
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

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest starrys Request:\n" . $url . $method . "\n" . "jsonData: " . $jsonData . "\n");
        }

        $operationUrl = $url . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $operationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest starrys Response error:\n" . var_export(curl_error($ch), true) . "\n");
            }
        } else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest starrys Response:\n" . $result . "\n");
            }
        }
        curl_close($ch);
        return $result;
    }
}