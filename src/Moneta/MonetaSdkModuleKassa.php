<?php

namespace Moneta;

use Moneta;

class MonetaSdkModuleKassa implements MonetaSdkKassa
{
    public $kassaApiUrl;

    public $associatedLogin;

    public $associatedPassword;

    public $kassaStorageSettings;


    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
        $this->kassaApiUrl = $this->kassaStorageSettings['monetasdk_kassa_module_api_url'];
    }

    public function __destruct()
    {

    }

    public function authoriseKassa()
    {
        $response = static::sendHttpRequest('/v1/associate/' . $this->kassaStorageSettings['monetasdk_kassa_module_uuid'],
            'POST', array('username' => $this->kassaStorageSettings['monetasdk_kassa_module_login'],
            'password' => str_replace('&amp;', '&', $this->kassaStorageSettings['monetasdk_kassa_module_password'])));

        if ($response !== false) {
            $this->associatedLogin = $response['userName'];
            $this->associatedPassword = $response['password'];
            return array(
                'username' => $this->associatedLogin,
                'password' => $this->associatedPassword
            );
        }
        else {
            return false;
        }

    }

    public function checkKassaStatus()
    {

    }

    public function sendDocument($document)
    {
        $document = @json_decode($document, true);

        if (!$this->kassaStorageSettings['monetasdk_kassa_module_encoded_auth']) {
            $credentials = $this->authoriseKassa();
        }
        else {
            $credentials = array('auth' => $this->kassaStorageSettings['monetasdk_kassa_module_encoded_auth']);
        }

        if (isset($document['id'])) {
            $document['id'] = 'module-' . urlencode($document['id']);
        }
        if (isset($document['docNum'])) {
            $document['docNum'] = 'module-' . urlencode($document['docNum']);
        }

        switch ($document['docType']) {
            case MonetaSdkKassa::OPERATION_TYPE_SALE:
                $document['docType'] = MonetaSdkKassa::MODULE_DOC_TYPE_SALE;
                break;
            case MonetaSdkKassa::OPERATION_TYPE_SALE_RETURN:
                $document['docType'] = MonetaSdkKassa::MODULE_DOC_TYPE_SALE_RETURN;
                break;
            default:
        }

        if (isset($document['inventPositions'])) {
            $newInventory = array();
            $inventPositions = $document['inventPositions'];
            if (is_array($inventPositions) && count($inventPositions)) {
                foreach ($inventPositions AS $position) {
                    if (isset($position['name'])) {
                        // productName подвергнуть преобразованию ESCAPED_UNICODE
                        $strName = (string)$position['name'];
                        $strName = preg_replace_callback('/u([0-9a-fA-F]{4})/', function ($match) {
                            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                        }, $strName);
                        $strName = str_replace('\\', '', $strName);
                        $position['name'] = $strName;
                    }

                    $newInventory[] = $position;
                }
            }

            $document['inventPositions'] = $newInventory;
        }

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
            if (strlen($clientPhone) == 11) {
                // да, это номер
                $clientPhone = '+' . substr($clientPhone, 0, 1) . '-' . substr($clientPhone, 1, 3) . '-' . substr($clientPhone, 4, 3) . '-' . substr($clientPhone, 7, 4);
            }
            else {
                $clientPhone = null;
            }
        }
        // установим в документ номер телефона вместо e-mail
        if ($clientPhone) {
            $document['email'] = $clientPhone;
        }

        $document = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $response = static::sendHttpRequest('/v1/doc', 'POST', $credentials, $document);

        $result = $response;
        if (isset($response['status']) && in_array($response['status'], array('QUEUED', 'PENDING', 'PRINTED', 'COMPLETED'))) {
            $result['pawresult'] = true;
        }
        else {
            if (isset($result['pawauth'])) {
                $result['pawauth'] = 'remove!';
            }
        }

        return $result;

    }

    public function checkDocumentStatus()
    {

    }

    private function sendHttpRequest($url, $method, $auth_data, $data = '') {

        $encoded_auth = (isset($auth_data['auth'])) ? $auth_data['auth'] : base64_encode($auth_data['username'] . ':' . $auth_data['password']);

        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest input data:\n" . $url . ', ' . $method . ', ' . $encoded_auth . "\n");
        }
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $encoded_auth
        );
        if ($method == 'POST' && $data != '') {
            $headers['Content-Length'] = mb_strlen($data, '8bit');
        }
        $headers_string = '';
        foreach ($headers as $key => $value) {
            $headers_string .= $key . ': ' . $value."\r\n";
        }
        $options = array(
            'http' => array(
                'header' => $headers_string,
                'method' => $method
            )
        );
        if ($method == 'POST' && $data != '') {
            $options['http']['content'] = $data;
        }
        $context  = stream_context_create($options);
        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest module Request:\n" . $method . ', ' . $this->kassaApiUrl . $url . "\n" . $headers_string . "\n" . $data . "\n");
        }

        $response = false;
        try {
            $response = @file_get_contents($this->kassaApiUrl . $url, false, $context);
        }
        catch (\Exception $e) {
            MonetaSdkUtils::addToLog("sendHttpRequest module file_get_contents error:\n" . $e->getMessage());
        }

        if ($response === false) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest module Error:\n" . var_export(error_get_last(), true) . "\n");
            }
            return false;
        }
        if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
            MonetaSdkUtils::addToLog("sendHttpRequest module Response:\n" . var_export($response, true) . "\n");
        }

        $result = json_decode($response, true);
        $result['pawauth'] = $encoded_auth;

        return $result;
    }


}