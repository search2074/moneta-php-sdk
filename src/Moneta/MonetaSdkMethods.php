<?php

namespace Moneta;

use Moneta;

class MonetaSdkMethods
{
	const EXCEPTION_NO_CONNECTION_TYPE      = 'no connection type is defined: ';

    const EXCEPTION_NO_MOTHOD               = 'method is not exists: ';

    const EXCEPTION_MONETA                  = 'merchantAPI error: ';

    public $settings;

    private $monetaConnectionType;

    public $monetaService;

    public $data;

    public $render;

    public $error;

    public $errorCode;

    public $errorMessage;

    public $errorMessageHumanConverted;

    public $events;

    public $calledMethods;


    /**
     * Execute SDK method
     *
     * @param $function
     * @param $args
     * @throws MonetaSdkException
     */
    public function executeSdkRequest($function, $args)
    {
        if (!method_exists($this->monetaService, $function)) {
            $this->error = true;
            throw new MonetaSdkException(self::EXCEPTION_NO_MOTHOD . $function);
        }

        try {
            $result = call_user_func_array(array($this->monetaService, $function), $args);
            $this->data = json_decode(json_encode($result), true);
            $this->render = MonetaSdkUtils::requireView($function, $this->data, $this->getSettingValue('monetasdk_view_files_path'));
        }
        catch (\Exception $e) {
            $this->parseException($e);
        }
    }

    /**
     * Create connection with Moneta if necessary
     *
     * @throws MonetaSdkException
     */
	public function checkMonetaServiceConnection()
	{
		if (!$this->monetaService) {
			$this->monetaConnectionType = $this->getSettingValue('monetasdk_connection_type');
			if ($this->monetaConnectionType == 'soap') {
				$wsdl 		= $this->getSettingValue('monetasdk_demo_mode') ? $this->getSettingValue('monetasdk_demo_url') : $this->getSettingValue('monetasdk_production_url');
				if ($this->getSettingValue('monetasdk_use_x509')) {
					$wsdl  .= $this->getSettingValue('monetasdk_x509_port') ? ":".$this->getSettingValue('monetasdk_x509_port') : "";
				}
				$wsdl      .= $this->getSettingValue('monetasdk_use_x509') ? $this->getSettingValue('monetasdk_x509_soap_link') : $this->getSettingValue('monetasdk_soap_link');
				$username	= $this->getSettingValue('monetasdk_account_username');
				$password	= $this->getSettingValue('monetasdk_account_password');
				$options 	= null;
				$isDebug	= $this->getSettingValue('monetasdk_debug_mode');
				// connect to moneta wsdl
				$this->monetaService = new MonetaSdkSoapConnector($wsdl, $username, $password, $options, $isDebug);
			}
			else if ($this->monetaConnectionType == 'json') {
				// json connection not require initializition
			}
			else {
                $this->error = true;
				throw new MonetaSdkException(self::EXCEPTION_NO_CONNECTION_TYPE . $this->monetaConnectionType);
			}
		}
	}

    /**
     * Clean SDK method
     */
    public function cleanResultData()
    {
        $this->data                         = null;
        $this->render                       = null;
        $this->error                        = false;
        $this->errorCode                    = null;
        $this->errorMessage                 = null;
        $this->errorMessageHumanConverted   = null;
    }

    /**
     * @param $value
     * @return mixed
     * @throws MonetaSdkException
     */
    public function getSettingValue($value)
    {
        return MonetaSdkUtils::getValueFromArray($value, $this->settings);
    }

    /**
     * @param $name
     * @param $value
     */
    public function setSettingValue($name, $value)
    {
        $this->settings[$name] = $value;
    }

    /**
     * Create new invoice
     *
     * @param null $payer
     * @param $payee
     * @param $amount
     * @param $transactionId
     * @param bool|false $isRegular
     * @param $additionalData
     * @return mixed
     * @throws MonetaSdkException
     */
    public function pvtMonetaCreateInvoice($payer = null, $payee, $amount, $transactionId, $paymentSystem = 'payanyway', $isRegular = false, $additionalData = null)
    {
        try
        {
            $invoiceRequest = new \Moneta\Types\InvoiceRequest();
            if ($payer) {
                $invoiceRequest->payer = $payer;
            }

            $invoiceRequest->payee = $payee;
            $invoiceRequest->amount = $amount;
            $invoiceRequest->clientTransaction = $transactionId;

            $operationInfo = new \Moneta\Types\OperationInfo();

            if ($isRegular) {
                $operationInfo->addAttribute($this->pvtMonetaCreateAttribute('PAYMENTTOKEN',        'request'));
            }

            if ($paymentSystem == 'post' && $additionalData && $this->checkAdditionalData($paymentSystem, $additionalData)) {
                $operationInfo->addAttribute($this->pvtMonetaCreateAttribute('mailofrussiaindex',   $additionalData['additionalParameters_mailofrussiaSenderIndex']));
                $operationInfo->addAttribute($this->pvtMonetaCreateAttribute('mailofrussiaregion',  $additionalData['additionalParameters_mailofrussiaSenderRegion']));
                $operationInfo->addAttribute($this->pvtMonetaCreateAttribute('mailofrussiaaddress', $additionalData['additionalParameters_mailofrussiaSenderAddress']));
                $operationInfo->addAttribute($this->pvtMonetaCreateAttribute('mailofrussianame',    $additionalData['additionalParameters_mailofrussiaSenderName']));
            }
            else if ($paymentSystem == 'euroset' && $additionalData && $this->checkAdditionalData($paymentSystem, $additionalData)) {
                $operationInfo->addAttribute($this->pvtMonetaCreateAttribute('rapidamphone',   $additionalData['additionalParameters_rapidaPhone']));
            }

            $invoiceRequest->operationInfo = $operationInfo;
            return $this->monetaService->Invoice($invoiceRequest);
        }
        catch (Exception $e)
        {
            $this->error = true;
            throw new MonetaSdkException(self::EXCEPTION_MONETA . 'pvtMonetaCreateInvoice: ' . print_r($e, true));
        }
    }

    /**
     * @param string $paymentSystem
     * @return array
     */
    public function getAdditionalFieldsByPaymentSystem($paymentSystem = 'payanyway')
    {
        $result = array();

        switch ($paymentSystem) {
            case 'post':
                $result = array('additionalParameters_mailofrussiaSenderIndex', 'additionalParameters_mailofrussiaSenderRegion', 'additionalParameters_mailofrussiaSenderAddress', 'additionalParameters_mailofrussiaSenderName');
                break;
            case 'euroset':
                $result = array('additionalParameters_rapidaPhone');
                break;
        }

        return $result;
    }

    /**
     * @param $var
     * @param null $source
     * @return null
     */
    public function getRequestedValue($var, $source = null)
    {
        $value = null;
        if ((!$source || strtolower($source) == 'post') && isset($_POST[$var])) {
            $value = $_POST[$var];
        }
        if ((!$source || strtolower($source) == 'get') && isset($_GET[$var])) {
            $value = $_GET[$var];
        }

        return $value;
    }

    /**
     * @param $var
     * @return null|string
     */
    public function getRequestedValueSource($var)
    {
        $source = null;
        if (isset($_POST[$var])) {
            $source = 'post';
        }
        if (isset($_GET[$var])) {
            $source = 'get';
        }

        return $source;
    }

    /**
     * @param $vars
     * @param null $source
     * @return array
     */
    public function addAdditionalData($vars, $source = null)
    {
        $result = array();
        foreach ($vars AS $var) {
            $value = null;
            if ((!$source || strtolower($source) == 'post') && isset($_POST[$var])) {
                $value = $_POST[$var];
            }
            if ((!$source || strtolower($source) == 'get') && isset($_GET[$var])) {
                $value = $_GET[$var];
            }

            $result[$var] = array("var" => $var, "value" => $value, "name" => null);
        }

        return $result;
    }

    /**
     * @return bool|string
     */
    public function detectEventTypeFromVars()
    {
        $detectedEvent = false;
        if ($this->isFieldsSet(array('MNT_ID', 'MNT_TRANSACTION_ID', 'MNT_AMOUNT'))) {
            $detectedEvent = 'ForwardPaymentForm';
        }

        if ($this->isFieldsSet(array('MNT_ID', 'MNT_TRANSACTION_ID', 'MNT_OPERATION_ID', 'MNT_AMOUNT', 'MNT_CURRENCY_CODE', 'MNT_TEST_MODE', 'MNT_SIGNATURE'))) {
            $detectedEvent = 'MonetaSendCallBack';
        }

        return $detectedEvent;
    }

    /**
     * @return array
     */
    public function getInternalEventNames()
    {
        return array('ForwardPaymentForm', 'MonetaSendCallBack');
    }

    /**
     * @return bool|string
     */
    public function renderError()
    {
        $viewName = 'ErrorMessage';
        $data = array('error' => $this->error, 'errorCode' => $this->errorCode, 'errorMessage' => $this->errorMessage, 'errorMessageHumanConverted' => $this->errorMessageHumanConverted);
        return MonetaSdkUtils::requireView($viewName, $data, $this->getSettingValue('monetasdk_view_files_path'));
    }

    /**
     * @param $vars
     * @return bool
     */
    private function isFieldsSet($vars, $source = null)
    {
        $result = true;
        foreach ($vars AS $var) {
            if (strtolower($source) == 'post' && !isset($_POST[$var])) {
                $result = false;
            }
            if (strtolower($source) == 'get' && !isset($_GET[$var])) {
                $result = false;
            }
            if (!$source && !isset($_POST[$var]) && !isset($_GET[$var])) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param $vars
     * @return bool
     */
    private function isFieldsUnset($vars, $source = null)
    {
        // no any field is in request of defined source type
        $result = true;
        foreach ($vars AS $var) {
            if ((!$source || strtolower($source) == 'post') && isset($_POST[$var])) {
                $result = false;
            }
            if ((!$source || strtolower($source) == 'get') && isset($_GET[$var])) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param $key
     * @param $value
     * @return MonetaKeyValueAttribute
     */
    private function pvtMonetaCreateAttribute($key, $value)
    {
        $monetaAtribute = new \Moneta\Types\KeyValueAttribute();
        $monetaAtribute->key = $key;
        $monetaAtribute->value = $value;

        return $monetaAtribute;
    }

    /**
     * @param string $paymentSystem
     * @param $additionalData
     * @return bool
     */
    private function checkAdditionalData($paymentSystem = 'payanyway', $additionalData)
    {
        $result = true;

        switch ($paymentSystem) {
            case 'post':
                $result = $this->checkParamsInArray($additionalData, $this->getAdditionalFieldsByPaymentSystem($paymentSystem));
                break;
            case 'euroset':
                $result = $this->checkParamsInArray($additionalData, $this->getAdditionalFieldsByPaymentSystem($paymentSystem));
                break;
        }

        return $result;
    }

    /**
     * @param $inputData
     * @param $params
     * @return bool
     * @throws MonetaSdkException
     */
    private function checkParamsInArray($inputData, $params)
    {
        $result = true;
        if (!count($params) || !is_array($params)) {
            $result = false;
        }
        foreach ($params AS $param) {
            if (!MonetaSdkUtils::getValueFromArray($param, $inputData)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param $e
     */
    private function parseException($e)
    {
        $this->error = true;

        if (is_object($e)) {
            $e = (array) $e;
        }

        if ($this->monetaConnectionType == 'soap') {
            if (isset($e['detail']) && is_object($e['detail'])) {
                $this->errorCode = $e['detail']->faultDetail;
            }
            if (isset($e['faultstring'])) {
                $this->errorMessage = $e['faultstring'];
            }
            if ($this->errorCode && isset($this->settings[$this->errorCode])) {
                $this->errorMessageHumanConverted = $this->settings[$this->errorCode];
            }
            else {
                $this->errorMessageHumanConverted = $this->settings['0'];
                $handleServiceUnavailableEvent = MonetaSdkUtils::handleEvent('ServiceUnavailable', array('errorCode' => $this->errorCode, 'errorMessage' => $this->errorMessage, 'errorMessageHumanConverted' => $this->errorMessageHumanConverted), $this->getSettingValue('monetasdk_event_files_path'));
            }
        }

    }

}