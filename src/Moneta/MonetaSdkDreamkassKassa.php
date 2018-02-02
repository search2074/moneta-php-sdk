<?php

namespace Moneta;

use Moneta;

class MonetaSdkDreamkassKassa implements MonetaSdkKassa
{

    const DREAMKASS_TIME_OUT      = 180;
    const DREAMKASS_TAX_MODE      = "DEFAULT";
    const DREAMKASS_POSITION_TYPE = "COUNTABLE";
    const DREAMKASS_PAYMENT_TYPE  = "CASHLESS";

    public $kassaStorageSettings;

    public $kassaApiUrl;
    private $drkassLogin;
    private $drkassPassword;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_dreamkass_url'];
        $this->drkassLogin = $this->kassaStorageSettings['monetasdk_kassa_dreamkass_id'];
        $this->drkassPassword = $this->kassaStorageSettings['monetasdk_kassa_dreamkass_token'];

        // захардкодим
        $this->kassaApiUrl = "";
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
        $document = @json_decode($document, true);
        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $method = MonetaSdkKassa::DREAMKASS_DOC_TYPE_SALE;
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $method = MonetaSdkKassa::DREAMKASS_DOC_TYPE_SALE_RETURN;
                break;
            default:
                $method = $document['docType'];
        }

        $dreamDocument = array();

        // общие реквизиты
        $dreamDocument['deviceId'] = 'dr-' . $document['docNum'];
        $dreamDocument['type'] = $method;
        $dreamDocument['timeout'] = self::DREAMKASS_TIME_OUT;
        $dreamDocument['taxMode'] = self::DREAMKASS_TAX_MODE;

        // товары
        $dreamPositions = array();
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions) && count($inventPositions)) {
            foreach ($inventPositions AS $position) {
                $dreamItem = array();
                $dreamItem['name'] = (isset($position['name']) && is_string($position['name'])) ? MonetaSdkUtils::convertEscapedUnicode($position['name']) : '';
                $dreamItem['type'] = self::DREAMKASS_POSITION_TYPE;
                $dreamItem['quantity'] = intval($position['quantity']);
                $dreamItem['price'] = round(floatval($position['price'], 2));
                $dreamItem['priceSum'] = intval($position['quantity']) * round(floatval($position['price'], 2));

                $tax = MonetaSdkKassa::DREAMKASS_NDS_NO_TAX;
                if (isset($position['vatTag'])) {
                    switch ($position['vatTag']) {
                        case MonetaSdkKassa::VAT0:
                            $tax = MonetaSdkKassa::DREAMKASS_NDS_0;
                            break;
                        case MonetaSdkKassa::VAT10:
                            $tax = MonetaSdkKassa::DREAMKASS_NDS_10;
                            break;
                        case MonetaSdkKassa::VAT18:
                            $tax = MonetaSdkKassa::DREAMKASS_NDS_18;
                            break;
                        case MonetaSdkKassa::VATWR10:
                            $tax = MonetaSdkKassa::DREAMKASS_NDS_110;
                            break;
                        case MonetaSdkKassa::VATWR18:
                            $tax = MonetaSdkKassa::DREAMKASS_NDS_118;
                            break;
                    }
                }

                $dreamItem['tax'] = $tax;

                // добавим элемент
                $dreamPositions[] = $dreamItem;

            }
        }

        $dreamDocument['positions'] = $dreamPositions;

        // суммы
        $totalAmount = 0;
        $payments = array();
        if (is_array($document['moneyPositions']) && count($document['moneyPositions'])) {
            foreach ($document['moneyPositions'] AS $moneyPosition) {
                $payments[] = array('sum' => round(floatval($moneyPosition['sum']), 2), 'type' => self::DREAMKASS_PAYMENT_TYPE);
                $totalAmount = $totalAmount + round(floatval($moneyPosition['sum']), 2);
            }
        }

        $dreamDocument['payments'] = $payments;
        $dreamDocument['total'] = array("priceSum" => $totalAmount);

        // данные покупателя
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

        if ($clientEmail) {
            $dreamDocument['attributes']['email'] = $clientEmail;
        }

        if ($clientPhone) {
            $dreamDocument['attributes']['phone'] = $clientPhone;
        }

        $respond = $this->sendHttpRequest($this->kassaApiUrl, "receipts", $dreamDocument);

        return $respond;

    }

    public function checkDocumentStatus()
    {
    }

    private function sendHttpRequest($url, $method, $data)
    {
        // запрос надо сделать через curl
        $jsonData = json_encode($data);

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest dreamkass Request:\n" . $url . $method . "\n" . "jsonData: " . $jsonData . "\n");
        }

        $operationUrl = $url . $method;

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
                MonetaSdkUtils::addToLog("sendHttpRequest dreamkass Response error:\n" . var_export(curl_error($ch), true) . "\n");
            }
        }
        else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest dreamkass Response:\n" . $result . "\n");
            }
        }

        curl_close($ch);
        return $result;

    }


    /*
        {
          "deviceId": 1385,
          "type": "SALE",
          "timeout": 180,
          "taxMode": "DEFAULT",
          "positions": [
            {
              "name": "Шоколад Сникерс",
              "type": "COUNTABLE",
              "quantity": 2,
              "price": 4500,
              "priceSum": 9000,
              "tax": "NDS_18",
              "taxSum": 1620
            }
          ],
          "payments": [
            {
              "sum": 9000,
              "type": "CASHLESS"
            }
          ],
          "attributes": {
            "email": "john.smith@example.com",
            "phone": "+71239994499"
          },
          "total": {
            "priceSum": 9000
          }
        }
     */


}