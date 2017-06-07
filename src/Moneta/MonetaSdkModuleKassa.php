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
            'password' => $this->kassaStorageSettings['monetasdk_kassa_module_password']));

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
        $credentials = $this->authoriseKassa();

        $response = static::sendHttpRequest('/v1/status', 'GET', $credentials);

        return $response;


    }

    public function sendDocument($document)
    {
        $credentials = $this->authoriseKassa();

        if (isset($document['id'])) {
            $document['id'] = 'module-' . $document['id'];
        }
        if (isset($document['docNum'])) {
            $document['docNum'] = 'module-' . $document['docNum'];
        }

        $response = static::sendHttpRequest('/v1/doc', 'POST', $credentials, $document);
        if ($response === false) {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest Error:\n" . var_export(error_get_last(), true) . "\n");
            }
        }
        else {
            if ($this->kassaStorageSettings['monetasdk_debug_mode']) {
                MonetaSdkUtils::addToLog("sendHttpRequest Response: \n" . print_r($response, true));
            }
        }

        $result = false;
        if (isset($response['status']) && in_array($response['status'], array('QUEUED', 'PENDING', 'PRINTED', 'COMPLETED'))) {
            $result = true;
        }

        return $result;

    }

    public function checkDocumentStatus()
    {

    }

    private function sendHttpRequest($url, $method, $auth_data, $data = '') {
        $encoded_auth =  base64_encode($auth_data['username'] . ':' . $auth_data['password']);
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

        return json_decode($response, true);
    }


}