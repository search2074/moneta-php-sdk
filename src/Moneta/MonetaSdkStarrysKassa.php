<?php

namespace Moneta;

use Moneta;

class MonetaSdkStarrysKassa implements MonetaSdkKassa
{


    public $kassaStorageSettings;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        // $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_atol_api_url'];


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
        $data = array('timestamp' => $d->format('d.m.Y H:i:s'), 'external_id' => 'atol-' . $document['docNum']);
        $data['receipt']['attributes']['email'] = $document['email'];
        $data['receipt']['attributes']['phone'] = '';

        $items = array();
        $inventPositions = $document['inventPositions'];
        if (is_array($inventPositions) && count($inventPositions)) {
            foreach ($inventPositions AS $position) {
                $tax = MonetaSdkKassa::ATOL_NONE;
                switch ($position['vatTag']) {
                    case MonetaSdkKassa::VAT0:
                        $tax = MonetaSdkKassa::ATOL_VAT0;
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

                $items[] = array(
                    'price' => floatval($position['price']), 'name' => $position['name'], 'quantity' => intval($position['quantity']),
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

        $result = false;
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

        $res = curl_exec($ch);
        if ($res === false) {
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

    private function sendHttpRequest2()
    {
        $url = "https://fce.starrys.ru:4443/fr/api/v2/Complex";

        $cert = "-----BEGIN CERTIFICATE-----
MIIDSTCCAjECCQCqoSlNlQkBKzANBgkqhkiG9w0BAQsFADBQMQswCQYDVQQGEwJS
VTEWMBQGA1UECAwNUm9zdG92LW9uLURvbjEQMA4GA1UECgwHU3RhcnJ5czEXMBUG
A1UEAwwOZmNlLnN0YXJyeXMucnUwHhcNMTcwNzEyMTIwMzIxWhcNMzEwMzIxMTIw
MzIxWjB9MQswCQYDVQQGEwJSVTEWMBQGA1UECAwNUm9zdG92LW9uLURvbjEWMBQG
A1UEBwwNUm9zdG92LW9uLURvbjETMBEGA1UECgwKSFotY29tcGFueTEaMBgGA1UE
CwwRRklzY2FsLURlcGFydG1lbnQxDTALBgNVBAMMBGd1aWQwggEiMA0GCSqGSIb3
DQEBAQUAA4IBDwAwggEKAoIBAQDbYyPI+7GZSwowWGoWU0wN1EYs9QFlmsuAau4/
NUh80Yrn5XFdo6Ai332urQqv81yf6LUuccXvOtFrrRAcx1wPWXRSd2ksSXqPQ7zU
D/rOJ9HqLtPuRboP+ffUnl86tX3CZbaMgyamD1W19HX+b+XkXYnPkblznIIngfza
XqraKO7SWx7yCgHaHCWRdjXsZn2CWQ4pI3L4KObrVdwMTunbrh9O/FENGBElWneM
CDiGZnuBEXWBVdVULWJaR8uo9NOuvbxni75sw+TcRQPFsjVkY8cULuv060fHbhr1
6TZUkhGTKBdQqn+UnQWlYZKJ0lXSCb6eCQo+vHWURRro8tojAgMBAAEwDQYJKoZI
hvcNAQELBQADggEBAFmydp9noX5SqaBNsgZreLigIX+HmxLe6JNf3tAwU2/0uL/P
EECoO1n00aw1B5Z/JZulhXsRFC5c2W/v+K4cfqs7ZX4X4yHsc2t819L6Wjs874Cs
yHkAMr654EeyorQKrhCP6/8GUtlUAm9bPccyOvahLOe+FKurBDzWnuw8IKOQLyRi
tVUAfg5GMf89F59fwYm6EEI0oMGTBYhRw6WaViNrJpXMkAIRbl2XKqVvs3bOdl3g
We8L5BUhvehMTJwpDxTeJVUPDIqEpoRg27w4TtgHOUDgquVvTWjX6klx1bktXtpl
vRhxrqUltMy6lKdiJQDwTL4jzH/HxR4kTkxZGkU=
-----END CERTIFICATE-----
-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDbYyPI+7GZSwow
WGoWU0wN1EYs9QFlmsuAau4/NUh80Yrn5XFdo6Ai332urQqv81yf6LUuccXvOtFr
rRAcx1wPWXRSd2ksSXqPQ7zUD/rOJ9HqLtPuRboP+ffUnl86tX3CZbaMgyamD1W1
9HX+b+XkXYnPkblznIIngfzaXqraKO7SWx7yCgHaHCWRdjXsZn2CWQ4pI3L4KObr
VdwMTunbrh9O/FENGBElWneMCDiGZnuBEXWBVdVULWJaR8uo9NOuvbxni75sw+Tc
RQPFsjVkY8cULuv060fHbhr16TZUkhGTKBdQqn+UnQWlYZKJ0lXSCb6eCQo+vHWU
RRro8tojAgMBAAECggEASi9G/YJmtrBSPLDZBr2Z/R8nr7IVi9cBM2Z1M7h/z31H
3EyQvhpDXyd1p2hqMb84NfaJta/RP6kDAcceqKydK6+TDwPD867RG7rLEmZo5+c9
K1Q0Y+D+HsLnE+WNzBts8BxW00LSAdszn3iPob8S3Nroa+EZ9ccZl+RzrR/P5D7M
tf3eBMDYXKyE5d+oa2z4rKF6W5TxCG0zlLshGVVXPgKdviVhw6UFW6sYI+R2DvlV
kQnVEZNfMcA0xNcbEdTW6J0h5uh15nXsyTs3fVz6StHQL9pxLhNrVHT4ZLAMm9UR
KHAE30WWySaHZz26nSiDKJxn1olFkyC3NA9lfwSRqQKBgQD4lNg7snT+vcgxcqCQ
8BoYspQ+RBwD70QlfYk5dxo23/vDuiBaAMgiDwzLeOj/GUttkpTEZGVceRyUTAnF
tf5FGAC2sOW1alNes4XB5th2xkHgJjK4XUXYFhoOrrthflKIi5noywUVJlytErAm
j4qktCXkd2iFvaJCVIhZ5Ua0DQKBgQDh70CmP2AvL7mOjWEMa2wmCyesOM+bs466
oyA81Do/ugDMKCHL/n+tdvypU1TlCPtB7wCW38dlflKt0F9FOw8hdw0d4SHKx9+y
K3kp/2vbJy6b54U6mSz7ER/HFijWa759bPeB+BWy5fWTgGS4JtV/l7h7PeQhD8OX
ow2QaTlK7wKBgQDrKWyStRWnNITh+o3Z77rQaIiDi01xj3XJfcRGv9zl0ulLVZZr
btfmGJTDHORXCGfqBcSFMnENlWmrBXAtQSmF1do++oSlJiwup+i+8hMP8ii504ki
DuMXNHl8MGMGLUoI8QAuUXnCc2MzPD22jQ7dF6vNQgV4mFibJXtEh/lmNQKBgQDN
Evtdax0E9463w8AZI9BQX8Os4QwgScT9x19Vl1Ufztc2eB7lKKX/b4c6snbWRWa6
nBOu3oQArb6iIga3sjmzqHnxaw3fH7j94dPiuQLPMyttO6KEY9CeOxbbAFQk/Ds1
YZjvEZ2wemaDcgD53dXgMHi09KKDF+nzU37WW4wzZQKBgQCvPGDa2KbcnH+Ei3aA
1u585dUarX3a+B0//1MnApbHY4jyhVOeO8P3q/qzOJuYbaiBqL54/PEaji0KWmo1
c31EfXLr/2iC/LvtP4ElA8ZG1PDPtkbqlZHWGfpd+975W79Vd2jMxZqEwrvW8GWE
0nqbEvrkfVfeQe3DxcKqP8rZlw==
-----END PRIVATE KEY-----";

        $requestData = array();
        $requestJson = json_encode($requestData);

        $requestJson = '{
"Device": "auto",
"ClientId": "214748",
"Password": 1,
"RequestId": "1",
"Lines": [
{
"Qty": 2500,
"Price": 10000,
"PayAttribute": 4,
"TaxId": 1,
"Description": "Булочка с маком"
},
{
"Qty": 500,
"Price": 200000,
"PayAttribute": 4,
"TaxId": 2,
"Description": "Икра чёрная, баклажанная"
}
],
"Cash": 100000,
"NonCash": [ 2000, 3000, 4000 ],
"TaxMode": 1,
"PhoneOrEmail": "user@example.com",
"FullResponse": false
}';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSLCERT, $cert);

        $response = curl_exec($ch);
        curl_close($ch);

    }


}