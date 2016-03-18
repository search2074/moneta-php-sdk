<?php
/**
 * Класс для доступа к методам вебсервиса платежной системы www.moneta.ru
 * через JSON
 *
 * PHP version 5
 *
 */

namespace Moneta;

use Moneta\MonetaWebServiceConnector;

class MonetaSdkJsonConnector extends MonetaWebServiceConnector
{
	private $jsonConnectionUrl;

	private $username;

	private $password;

	private $isDebug;


	/**
	 * Версия API Moneta.ru
	 *
	 * @var string
	 */
	public $version = "VERSION_2";


	function __construct($jsonConnectionUrl, $username, $password, $isDebug)
	{
		$this->jsonConnectionUrl	= $jsonConnectionUrl;
		$this->username				= $username;
		$this->password				= $password;
		$this->isDebug				= $isDebug;
	}


	protected function call($method, $data, $options = null)
	{
		// этот костыль для установки версии API (нужен рефакторинг метода call)
		if (is_object($data[0]))
			$data[0]->version = $this->version;

		return $this->jsonCall($method, $data, $options);
	}


	private function jsonCall($method, $data, $options)
	{
        $data = json_decode(json_encode($data), true);
		if (!is_array($data) || !count($data)) {
			$data = array();
		}

		$inputData = array();
		foreach ($data AS $itemKey => $itemVal) {
			if ($itemKey == "0") {
				$itemKey = "value";
			}
            if (!is_array($itemVal) || !count($itemVal)) {
                if (!empty($itemVal)) {
                    $inputData[$itemKey] = $itemVal;
                }
            }
            else {
                foreach ($itemVal AS $requestBodyKey => $requestBodyVal) {
                    if (!empty($requestBodyVal)) {
                        $inputData[$requestBodyKey] = $requestBodyVal;
                    }
                }
            }
		}

		$bodyData = array("{$method}Request" => array_merge(array("version" => $this->version), $inputData));
        $requestData = array("Envelope" => array("Header" => array("Security" => array("UsernameToken" => array("Username" => $this->username, "Password" => $this->password))), "Body" => $bodyData));
        if ($this->isDebug) {
            MonetaSdkUtils::addToLog("jsonCall request:\n".print_r($requestData, true));
        }
        $requestData = json_encode($requestData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->jsonConnectionUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
        curl_close($ch);

        if ($this->isDebug) {
            MonetaSdkUtils::addToLog("jsonCall curl response:\n{$response}");
        }
        $response = @json_decode($response, true);
		if (isset($response['Envelope']['Body'][$method.'Response'])) {
			$result = $response['Envelope']['Body'][$method.'Response'];
		}
		else {
			$result = $response;
		}

		return $result;
	}

}