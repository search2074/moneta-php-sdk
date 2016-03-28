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
    /**
     * @var
     */
	private $jsonConnectionUrl;

    /**
     * @var
     */
	private $username;

    /**
     * @var
     */
	private $password;

    /**
     * @var
     */
    private $cert;

    /**
     * @var
     */
	private $isDebug;


	/**
	 * Версия API Moneta.ru
	 *
	 * @var string
	 */
	public $version = "VERSION_2";

    /**
     * @param $jsonConnectionUrl
     * @param null $username
     * @param $password
     * @param $isDebug
     */
	function __construct($jsonConnectionUrl, $username, $password, $cert = null, $isDebug = false)
	{
		$this->jsonConnectionUrl	= $jsonConnectionUrl;
		$this->username				= $username;
		$this->password				= $password;
		$this->isDebug				= $isDebug;
        $this->cert				    = $cert;
	}

    /**
     * @param string $method
     * @param mixed $data
     * @param null $options
     * @return array|mixed|null|object
     */
	protected function call($method, $data, $options = null)
	{
        // no need to send version via json connector
		return $this->jsonCall($method, $data, $options);
	}

    /**
     * @param $method
     * @param $data
     * @param $options
     * @return array|mixed|null|object
     */
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

        // no need to send version via json connector
		// $bodyData = array("{$method}Request" => array_merge(array("version" => $this->version), $inputData));

        // some methods needs special argument values defined
        if ($method == 'GetOperationDetailsById') {
            $inputData = $inputData['value'];
        }

        $bodyData = array("{$method}Request" => $inputData);

        // authorization data
        if (!$this->cert) {
            $requestData = array("Envelope" => array("Header" => array("Security" => array("UsernameToken" => array("Username" => $this->username, "Password" => $this->password))), "Body" => $bodyData));
        }
        else {
            $requestData = array("Envelope" => array("Body" => $bodyData));
        }

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
        if ($this->cert) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->cert);
        }

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