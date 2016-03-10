<?php

namespace Moneta;

use Moneta;

class MonetaSdk extends MonetaSdkMethods
{

	function __construct()
	{
		$this->settings = MonetaSdkUtils::getAllSettings();
	}


    // TODO: пусть сохраняет алиас выбранного способа в куку.  Аргумент - типы систем для выбора.
    public function showChoosePaymentSystemForm($paySystemTypes = array())
    {


        $viewName = 'ChoosePaymentSystemForm';
        // return MonetaSdkUtils::requireView($viewName, $data);
    }

    /**
     * Create Assistant payment form
     *
     * @param $paymentSystem
     * @param $orderId
     * @param $amount
     * @param string $currency
     * @param $additionalData
     * @param string $method
     * @return bool
     */
    public function showPaymentFrom($orderId, $amount, $paymentSystem = null, $isRegular = false, $additionalData = null, $method = 'POST', $currency = 'RUB')
    {
        $viewName = 'PaymentFrom';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $amount = number_format($amount, 2, '.', '');

        if (!$paymentSystem && isset($_COOKIE['mnt_data']) && $_COOKIE['mnt_data']) {
            $cookieMntSerializedData = $_COOKIE['mnt_data'];
            $cookieMntData = @unserialize($cookieMntSerializedData);
            if (isset($cookieMntData['paysys']) && $cookieMntData['paysys']) {
                $paymentSystem = $cookieMntData['paysys'];
            }
        }

        if (!$paymentSystem) {
            $paymentSystem = 'payanyway';
        }

        $autoSubmit = false;
        $transactionId = 0;

        // смотрим нужно ли создавать счёт для выбранного платежного метода
        // если $isRegular, тогда тоже надо создавать invoice
        $paymentSystemParams = $this->getSettingValue('monetasdk_paysys_' . $paymentSystem);

        // если нужно, то создадим счёт в монете
        if (($additionalData && $paymentSystemParams['createInvoice']) || ($isRegular && $paymentSystem == 'plastic')) {
            $payer = $paymentSystemParams['accountId'];
            $payee = $this->getSettingValue('monetasdk_account_id');
            $createInvoiceResult = $this->pvtMonetaCreateInvoice($payer, $payee, $amount, $orderId, $paymentSystem, $isRegular, $additionalData);
            if (is_object($createInvoiceResult)) {
                $transactionId = $createInvoiceResult->transaction;
            }
            MonetaSdkUtils::handleEvent('InvoiceCreated', $this->getSettingValue('monetasdk_event_files_path'));
        }

        $action  = $this->getSettingValue('monetasdk_demo_mode') ? $this->getSettingValue('monetasdk_demo_url') : $this->getSettingValue('monetasdk_production_url');
        $action .= $this->getSettingValue('monetasdk_assistant_link');
        if ($transactionId) {
            $autoSubmit = true;
            $action .= '?operationId=' . $transactionId;
        }

        // для форвардинга формы
        if (!$additionalData && $paymentSystemParams['createInvoice'])
        {
            $action = "";
        }

        $signature = null;
        $monetaAccountCode = $this->getSettingValue('monetasdk_account_code');
        if ($monetaAccountCode && $monetaAccountCode != '') {
            $signature = md5( $this->getSettingValue('monetasdk_account_id') . $orderId . $amount . $currency . $this->getSettingValue('monetasdk_test_mode') . $monetaAccountCode );
        }

        $additionalFields = $this->getAdditionalFieldsByPaymentSystem($paymentSystem);
        $varData = $this->addAdditionalData($additionalFields);
        $postData = array();
        foreach ($varData AS $var) {
            if (isset($var['var'])) {
                $var['name'] = $this->getSettingValue('additional_fld_' . $var['var']);
            }

            $postData[] = $var;
        }

        $data = array("paySystem" => $paymentSystem, "orderId" => $orderId, "amount" => $amount, "currency" => $currency,
            "action" => $action, "method" => $method, "formName" => $viewName, "formId" => $viewName, "postData" => $postData,
            "additionalData" => $additionalData, "testMode" => $this->getSettingValue('monetasdk_test_mode'), "signature" => $signature,
            "successUrl" => $this->getSettingValue('monetasdk_success_url'), "failUrl" => $this->getSettingValue('monetasdk_fail_url'),
            "accountId" => $this->getSettingValue('monetasdk_account_id'), "isRegular" => $isRegular ? '1' : null,
            "autoSubmit" => $autoSubmit ? '1' : null, "operationId" => $transactionId, "paymentSystemParams" => $paymentSystemParams);

        $this->rendered = MonetaSdkUtils::requireView($viewName, $data);
        $this->result = $data;

        return $this->getCurrentMethodResult();
    }

    /**
     * Switch and execute an action
     */
    public function processInputData()
    {
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $actionExecuted = false;
        $eventType = $this->detectEventTypeFromVars();
        if ($eventType) {
            // handle event
            $isEventHandled = MonetaSdkUtils::handleEvent($eventType, $this->getSettingValue('monetasdk_event_files_path'));

            // handle internal event
            if (in_array($eventType, $this->getInternalEventNames())) {

                switch ($eventType) {
                    case 'ForwardPaymentForm':
                        $formMethod     = $this->getRequestedValue('MNT_FORM_METHOD');
                        $orderId        = $this->getRequestedValue('MNT_TRANSACTION_ID', $formMethod);
                        $amount         = $this->getRequestedValue('MNT_AMOUNT', $formMethod);
                        $paymentSystem  = $this->getRequestedValue('MNT_PAY_SYSTEM', $formMethod);
                        $isRegular      = $this->getRequestedValue('MNT_IS_REGULAR', $formMethod);
                        $method         = $this->getRequestedValue('MNT_FORM_METHOD', $formMethod);
                        $currency       = $this->getRequestedValue('MNT_CURRENCY_CODE', $formMethod);

                        $additionalData = array();
                        $additionalFields = $this->getAdditionalFieldsByPaymentSystem($paymentSystem);
                        foreach ($additionalFields AS $field) {
                            $additionalData[$field] = $this->getRequestedValue($field, $formMethod);
                        }

                        $this->showPaymentFrom($orderId, $amount, $paymentSystem, $isRegular, $additionalData, $method, $currency);
                        break;

                    case 'MonetaSendCallBack':
                        $signature = null;
                        $monetaAccountCode = $this->getSettingValue('monetasdk_account_code');
                        if ($monetaAccountCode && $monetaAccountCode != '') {
                            $signature = md5( $this->getRequestedValue('MNT_ID') . $this->getRequestedValue('MNT_TRANSACTION_ID') . $this->getRequestedValue('MNT_OPERATION_ID') . $this->getRequestedValue('MNT_AMOUNT') . $this->getRequestedValue('MNT_CURRENCY_CODE') . $this->getRequestedValue('MNT_TEST_MODE') . $monetaAccountCode );
                        }

                        if (!$signature || $signature == $this->getRequestedValue('MNT_SIGNATURE')) {
                            $orderId = $this->getRequestedValue('MNT_TRANSACTION_ID');
                            $amount = $this->getRequestedValue('MNT_AMOUNT');
                            $handlePaySuccess = MonetaSdkUtils::handleEvent('MonetaPaySuccess', $this->getSettingValue('monetasdk_event_files_path'));
                            die('SUCCESS');
                        }
                        else {
                            die('FAIL');
                        }

                        break;
                }

            }

            $actionExecuted = true;
            if (!$this->result) {
                $this->result = array("event" => $eventType);
            }
        }

        $this->dataProcessingActionExecuted = $actionExecuted;
        return $actionExecuted;
    }

    /**
     * Catch method and execute it
     *
     * @param $function
     * @param $args
     * @return bool
     * @throws MonetaSdkException
     */
	public function __call($function, $args)
	{
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();
        if (strpos($function, 'moneta') === 0) {
            $function = str_replace('moneta', '', $function);
        }
        $this->executeSdkRequest($function, $args);
        return $this->getCurrentMethodResult();
	}

    /**
     * Prepare SDK results
     *
     * @return bool
     */
	private function getCurrentMethodResult()
	{
		if ($this->error) {
			return false;
		}
		return $this->result;
	}

}