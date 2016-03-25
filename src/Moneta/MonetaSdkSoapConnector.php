<?php
/**
 * Класс для доступа к методам вебсервиса платежной системы www.moneta.ru
 * через SOAP
 *
 * PHP version 5
 *
 */

namespace Moneta;

use Moneta\MonetaWebServiceConnector;

class MonetaSdkSoapConnector extends MonetaWebServiceConnector
{

	/**
	 * Версия API Moneta.ru
	 *
	 * @var string
	 */
	public $version = "VERSION_2";

	private $isDebug;


	function __construct($wsdl, $username, $password, $cert = null, $options = null, $isDebug = false)
	{
		$this->isDebug = $isDebug;

		if ($isDebug) {
            ini_set("soap.wsdl_cache_enabled", "0");
        }
        else {
            ini_set("soap.wsdl_cache_ttl", "86400");
        }

		if ($options === null) {
            $options = array();
        }

		if ($cert && file_exists($cert)) {
			$options['local_cert'] 		= $cert;
		}

		parent::__construct($wsdl, $options);

		if (!$cert || ($cert && !file_exists($cert))) {
			$this->inputHeaders[] = $this->createSecurityHeader($username, $password);
		}

	}


	private function createSecurityHeader($userName, $password)
	{
		$sns = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";

		// формируем параметр username
		$username = new \SoapVar($userName, XSD_STRING, NULL, $sns, NULL, $sns);

		// формируем параметр password
		$password = new \SoapVar($password, XSD_STRING, NULL, $sns, NULL, $sns);

		// Для того чтобы выразить тег <UserNameToken> с вложенными внутри него тегами <Username> и <Password>,
		// мы должны определить промежуточный класс
		$tmp = new \stdClass();
		$tmp->Username = $username;
		$tmp->Password = $password;

		// содержимое сложного XML-тега <UsernameToken> в виде SoapVar,
		// тип которого не XSD_STRING, а SOAP_ENC_OBJECT
		$authData = new \SoapVar($tmp, SOAP_ENC_OBJECT, null, $sns, 'wsse:UsernameToken', $sns);

		// формируем содержимое тега Security , т.е. сам UsernameToken
		$tmp = new \stdClass();
		$tmp->UsernameToken = $authData;

		$usernameToken = new \SoapVar($tmp, SOAP_ENC_OBJECT, null, $sns, 'wsse:UsernameToken', $sns);

		$secHeaderValue = new \SoapVar($usernameToken, SOAP_ENC_OBJECT, NULL, $sns, 'wsse:Security', $sns);

		return new \SoapHeader($sns, 'Security', $secHeaderValue, true);
	}


	protected function call($method, $data, $options = null)
	{
		$soapClient = $this->client;

		if (is_object($data[0]))
			$data[0]->version = $this->version;

		if ($this->isDebug) {
			MonetaSdkUtils::addToLog("soapCall method: {$method}\n");
			MonetaSdkUtils::addToLog("soapCall data:\n".print_r($data, true));
		}

		$result = $soapClient->__soapCall($method, $data, $options, $this->inputHeaders, $this->outputHeaders);

		return $result;
	}


}