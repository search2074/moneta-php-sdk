<?php

namespace Moneta;

use Moneta;

class MonetaSdk extends MonetaSdkMethods
{
	function __construct($configPath = null)
	{
        $this->events = array();
        $this->calledMethods = array();
		$this->settings = MonetaSdkUtils::getAllSettings($configPath);
	}

    /**
     * Creates payment system choose form
     *
     * @param array $paySystemTypes
     * @return MonetaSdkResult
     */
    public function showChoosePaymentSystemForm($redirectUrl = null, $paySystemTypes = array())
    {
        $this->calledMethods[] = __FUNCTION__;

        $viewName = 'ChoosePaymentSystemForm';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        MonetaSdkUtils::setSdkCookie('redirect', $redirectUrl);

        $paySystems = array();
        foreach ($this->settings AS $oneSettingParameterKey => $oneSettingParameterVal) {
            if (strpos($oneSettingParameterKey, 'monetasdk_paysys_') !== false && isset($oneSettingParameterVal['group']) && (in_array($oneSettingParameterVal['group'], $paySystemTypes) || !count($paySystemTypes))) {
                $paySystems[$oneSettingParameterKey] = $oneSettingParameterVal;
            }
        }

        $this->data = $paySystems;
        $this->render = MonetaSdkUtils::requireView($viewName, $this->data, $this->getSettingValue('monetasdk_view_files_path'));

        return $this->getCurrentMethodResult();
    }

    /**
     * @return bool
     */
    public function processCleanChoosenPaymentSystem()
    {
        $this->data = MonetaSdkUtils::setSdkCookie('paysys', null);
        return $this->getEmptyResult();
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
    public function showPaymentFrom($orderId, $amount, $currency = 'RUB', $description = null, $paymentSystem = null, $isRegular = false, $additionalData = null, $method = 'POST')
    {
        $this->calledMethods[] = __FUNCTION__;

        // pre Execute
        if (!in_array('processInputData', $this->calledMethods)) {
            $this->processInputData('ForwardPaymentForm');
            if (isset($this->data['event']) && $this->data['event'] == 'ForwardPaymentForm') {
                return $this->getCurrentMethodResult();
            }
        }

        // Execute
        $viewName = 'PaymentFrom';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $amount = number_format($amount, 2, '.', '');
        $paymentSystem = $this->selectPaymentSystem($paymentSystem);

        $autoSubmit = false;
        $transactionId = 0;

        $paymentSystemParams = $this->getSettingValue('monetasdk_paysys_' . $paymentSystem);
        if (($additionalData && $paymentSystemParams['createInvoice']) || ($isRegular && $paymentSystem == 'plastic')) {
            $payer = $paymentSystemParams['accountId'];
            $payee = $this->getSettingValue('monetasdk_account_id');
            $transactionId = $this->sdkMonetaCreateInvoice($payer, $payee, $amount, $orderId, $paymentSystem, $isRegular, $additionalData);
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
        $postData = $this->createPostDataFromArray($this->addAdditionalData($additionalFields));

        $this->data = array('paySystem' => $paymentSystem, 'orderId' => $orderId, 'amount' => $amount, 'description' => $description,
            'currency' => $currency, 'action' => $action, 'method' => $method, 'formName' => $viewName, 'formId' => $viewName,
            'postData' => $postData, 'additionalData' => $additionalData, 'testMode' => $this->getSettingValue('monetasdk_test_mode'),
            'signature' => $signature, 'successUrl' => $this->getSettingValue('monetasdk_success_url'),
            'failUrl' => $this->getSettingValue('monetasdk_fail_url'), 'accountId' => $this->getSettingValue('monetasdk_account_id'),
            'isRegular' => $isRegular ? '1' : null, 'autoSubmit' => $autoSubmit ? '1' : null, 'operationId' => $transactionId,
            'paymentSystemParams' => $paymentSystemParams);

        $this->render = MonetaSdkUtils::requireView($viewName, $this->data, $this->getSettingValue('monetasdk_view_files_path'));
        return $this->getCurrentMethodResult();
    }

    /**
     * @param $accountId
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function showAccountBalance($accountId)
    {
        $this->calledMethods[] = __FUNCTION__;

        $viewName = 'AccountBalance';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $balance = $this->sdkMonetaGetAccountBalance($accountId);
        $this->data = array("account" => $accountId, "balance" => $balance);

        $this->render = MonetaSdkUtils::requireView($viewName, $this->data, $this->getSettingValue('monetasdk_view_files_path'));
        return $this->getCurrentMethodResult();
    }

    /**
     * @param $operationId
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function showOperationInfo($operationId)
    {
        $this->calledMethods[] = __FUNCTION__;

        $viewName = 'OperationInfo';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $operationInfo = $this->sdkMonetaGetOperationDetailsById($operationId);
        $this->data = array("operation" => $operationId, "info" => $operationInfo);

        $this->render = MonetaSdkUtils::requireView($viewName, $this->data, $this->getSettingValue('monetasdk_view_files_path'));
        return $this->getCurrentMethodResult();
    }

    /**
     * @param null $redirectUrl
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function showCreateUserForm($redirectUrl = null)
    {
        $this->calledMethods[] = __FUNCTION__;

        // pre Execute
        if (!in_array('processInputData', $this->calledMethods)) {
            $this->processInputData('ForwardCreateUserForm');
            if (isset($this->data['event']) && $this->data['event'] == 'ForwardCreateUserForm') {
                return $this->getCurrentMethodResult();
            }
        }

        MonetaSdkUtils::setSdkCookie('redirect', $redirectUrl);

        // Execute
        $viewName = 'CreateUserForm';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $this->render = MonetaSdkUtils::requireView($viewName, $this->data, $this->getSettingValue('monetasdk_view_files_path'));
        return $this->getCurrentMethodResult();
    }

    /**
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function showAccountHistoryForm($accountId)
    {
        $this->calledMethods[] = __FUNCTION__;

        // pre Execute
        if (!in_array('processInputData', $this->calledMethods)) {
            $this->processInputData('ForwardAccountHistoryForm');
            if (isset($this->data['event']) && $this->data['event'] == 'ForwardAccountHistoryForm') {
                return $this->getCurrentMethodResult();
            }
        }

        // Execute
        $viewName = 'AccountHistoryForm';
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $this->data = array("account" => $accountId);

        $this->render = MonetaSdkUtils::requireView($viewName, $this->data, $this->getSettingValue('monetasdk_view_files_path'));
        return $this->getCurrentMethodResult();

    }

    /**
     * @param $secret
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function processPutSecretToAccountProfile($secret)
    {
        $this->calledMethods[] = __FUNCTION__;

        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $this->data = array('result' => $this->sdkPutSecretToAccountProfile($secret));

        return $this->getEmptyResult();
    }

    /**
     * @param $payPassword
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function processEncryptPayPassword($payPassword)
    {
        $this->calledMethods[] = __FUNCTION__;

        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $secret = $this->sdkGetSecretFromAccountProfile();
        $this->data = array('result' => MonetaSdkUtils::encrypt($payPassword, $secret));

        return $this->getEmptyResult();
    }

    /**
     * @param $payPassword
     * @return MonetaSdkResult
     * @throws MonetaSdkException
     */
    public function processDecryptPayPassword($payPassword)
    {
        $this->calledMethods[] = __FUNCTION__;

        $this->cleanResultData();
        $this->checkMonetaServiceConnection();

        $secret = $this->sdkGetSecretFromAccountProfile();
        $this->data = array('result' => MonetaSdkUtils::decrypt($payPassword, $secret));

        return $this->getEmptyResult();
    }

    /**
     * Switch and execute an action
     */
    public function processInputData($definedEventType = null)
    {
        $this->calledMethods[] = __FUNCTION__;
        $this->cleanResultData();
        $this->checkMonetaServiceConnection();
        $eventType = $this->detectEventTypeFromVars();
        $processResultData = array();
        if ($eventType && (!$definedEventType || $definedEventType == $eventType)) {
            // handle event
            $isEventHandled = MonetaSdkUtils::handleEvent($eventType, array('postVars' => $_POST, 'getVars' => $_GET, 'cookieVars' => $_COOKIE), $this->getSettingValue('monetasdk_event_files_path'));
            // handle internal event
            if (in_array($eventType, $this->getInternalEventNames())) {
                switch ($eventType) {
                    case 'ForwardPaymentForm':
                        $this->processForwardPaymentForm();
                        break;
                    case 'MonetaSendCallBack':
                        $this->processMonetaSendCallBack();
                        break;
                    case 'ForwardChoosePaymentSystemForm':
                        $this->processForwardChoosePaymentSystemForm();
                        $this->processCookieRedirect();
                        break;
                    case 'ForwardCreateUserForm':
                        $processResultData = $this->processForwardCreateUserForm($processResultData);
                        $this->processCookieRedirect();
                        break;
                    case 'ForwardAccountHistoryForm':
                        $processResultData = $this->processForwardAccountHistoryForm();
                        break;
                }
                $this->events[] = $eventType;
            }
            $this->data = array("event" => $eventType, "processResultData" => $processResultData);
        }

        return $this->getCurrentMethodResult();
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
        $this->calledMethods[] = __FUNCTION__;

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
     * @return MonetaSdkResult
     */
	private function getCurrentMethodResult()
	{
        $sdkResult = new MonetaSdkResult();
        $sdkResult->error = $this->error;
        if ($this->error) {
            $sdkResult->data = new MonetaSdkError();
            $sdkResult->data->code = $this->errorCode;
            $sdkResult->data->message = $this->errorMessage;
            $sdkResult->render = $this->renderError();
        }
        else {
            $sdkResult->data = $this->data;
            $sdkResult->render = $this->render;
        }

		return $sdkResult;
	}

    /**
     * @param null $data
     * @return MonetaSdkResult
     */
    private function getEmptyResult($data = null)
    {
        $sdkResult = new MonetaSdkResult();
        $sdkResult->error = false;
        if ($data) {
            $sdkResult->data = $data;
        }

        return $sdkResult;
    }

    /**
     * @param $paymentSystem
     * @return mixed|null|string
     */
    private function selectPaymentSystem($paymentSystem)
    {
        if (!$paymentSystem) {
            $paymentSystem = MonetaSdkUtils::getSdkCookie('paysys');
        }

        if (!$paymentSystem) {
            $paymentSystem = 'payanyway';
        }
        else {
            $paymentSystem = str_replace('monetasdk_paysys_', '', $paymentSystem);
        }

        return $paymentSystem;
    }

    /**
     * @param $varData
     * @return array
     */
    private function createPostDataFromArray($varData)
    {
        $postData = array();
        foreach ($varData AS $var) {
            if (isset($var['var'])) {
                $var['name'] = $this->getSettingValue($var['var']);
                $var['var'] = str_replace('_', '.', $var['var']);
            }

            $postData[] = $var;
        }

        return $postData;
    }

    /**
     * processForwardPaymentForm
     */
    private function processForwardPaymentForm()
    {
        $formMethod     = $this->getRequestedValueSource('MNT_FORM_METHOD');
        $orderId        = $this->getRequestedValue('MNT_TRANSACTION_ID', $formMethod);
        $amount         = $this->getRequestedValue('MNT_AMOUNT', $formMethod);
        $paymentSystem  = $this->getRequestedValue('MNT_PAY_SYSTEM', $formMethod);
        $isRegular      = $this->getRequestedValue('MNT_IS_REGULAR', $formMethod);
        $method         = $this->getRequestedValue('MNT_FORM_METHOD', $formMethod);
        $currency       = $this->getRequestedValue('MNT_CURRENCY_CODE', $formMethod);
        $description    = $this->getRequestedValue('MNT_DESCRIPTION', $formMethod);
        $additionalData = array();
        $additionalFields = $this->getAdditionalFieldsByPaymentSystem($paymentSystem);
        foreach ($additionalFields AS $field) {
            $additionalData[$field] = $this->getRequestedValue($field, $formMethod);
        }
        $this->showPaymentFrom($orderId, $amount, $currency, $description, $paymentSystem, $isRegular, $additionalData, $method);
    }

    /**
     * processMonetaSendCallBack
     */
    private function processMonetaSendCallBack()
    {
        $signature = null;
        $monetaAccountCode = $this->getSettingValue('monetasdk_account_code');
        if ($monetaAccountCode && $monetaAccountCode != '') {
            $signature = md5( $this->getRequestedValue('MNT_ID') . $this->getRequestedValue('MNT_TRANSACTION_ID') . $this->getRequestedValue('MNT_OPERATION_ID') . $this->getRequestedValue('MNT_AMOUNT') . $this->getRequestedValue('MNT_CURRENCY_CODE') . $this->getRequestedValue('MNT_TEST_MODE') . $monetaAccountCode );
        }
        if (!$signature || $signature == $this->getRequestedValue('MNT_SIGNATURE')) {
            $processResultData['orderId'] = $this->getRequestedValue('MNT_TRANSACTION_ID');
            $processResultData['amount'] = $this->getRequestedValue('MNT_AMOUNT');
            $processResultData['answer'] = 'SUCCESS';
            $this->render = 'SUCCESS';
            $handlePaySuccess = MonetaSdkUtils::handleEvent('MonetaPaySuccess', array('orderId' => $processResultData['orderId'], 'amount' => $processResultData['amount']), $this->getSettingValue('monetasdk_event_files_path'));
        }
        else {
            $processResultData['answer'] = 'FAIL';
            $this->render = 'FAIL';
        }
    }

    /**
     * processForwardChoosePaymentSystemForm
     */
    private function processForwardChoosePaymentSystemForm()
    {
        $getChoosenPaymentSystem = str_replace('monetasdk_paysys_', '' , $this->getRequestedValue('choosePaySysByType'));
        MonetaSdkUtils::setSdkCookie('paysys', $getChoosenPaymentSystem);
    }

    /**
     * processForwardCreateUserForm
     *
     * @throws MonetaSdkException
     */
    private function processForwardCreateUserForm($processResultData)
    {
        $firstName  = $this->getRequestedValue('moneta_sdk_first_name');
        $lastName   = $this->getRequestedValue('moneta_sdk_last_name');
        $email      = $this->getRequestedValue('moneta_sdk_email');
        $gender     = $this->getRequestedValue('moneta_sdk_gender');
        $unitId     = $this->sdkMonetaCreateUser($firstName, $lastName, $email, $gender);
        $unitData = array('unitId' => $unitId, 'firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'gender' => $gender);
        $handleCreateUser = MonetaSdkUtils::handleEvent('CreateUserResult', $unitData);
        // добавить пользователю новый счёт
        if ($unitId) {
            $processResultData = array_merge($processResultData, $unitData);
            $accountPaymentPassword = rand(10000, 99999);
            $secret = $this->sdkGetSecretFromAccountProfile();
            $accountEncryptedPaymentPassword = encrypt($accountPaymentPassword, $secret);
            $accountId = $this->sdkMonetaCreateAccount($unitId, $accountPaymentPassword, $email);
            if ($accountId) {
                $accountData = array('unitId' => $unitId, 'accountId' => $accountId, 'accountPaymentPassword' => $accountPaymentPassword,
                    'accountEncryptedPaymentPassword' => $accountEncryptedPaymentPassword, 'accountNotificationEmail' => $email);

                $processResultData = array_merge($processResultData, $accountData);
            }
        }

        return $processResultData;
    }

    /**
     * processForwardAccountHistoryForm
     *
     * @return array
     * @throws MonetaSdkException
     */
    private function processForwardAccountHistoryForm()
    {
        $accountId  = $this->getRequestedValue('moneta_sdk_account');
        $dateFrom   = date("c", strtotime($this->getRequestedValue('moneta_sdk_date_from') . ' 00:00:00'));
        $dateTo     = date("c", strtotime($this->getRequestedValue('moneta_sdk_date_to') . ' 23:59:59'));
        $pageNumber = $this->getRequestedValue('moneta_sdk_page_number');
        if (!$pageNumber) {
            $pageNumber = 1;
        }

        $historyResult = $this->sdkMonetaHistory($accountId, $dateFrom, $dateTo, intval($this->getSettingValue('monetasdk_history_items_per_page')), $pageNumber);
        $processResultData = array( 'history' => $historyResult, 'account' => $accountId, 'moneta_sdk_date_from' => date('d.m.Y', strtotime($dateFrom)),
            'moneta_sdk_date_to' => date('d.m.Y', strtotime($dateTo)), 'moneta_sdk_page_number' => $pageNumber);

        $processResultData = array_merge($processResultData, $processResultData);
        $this->render = MonetaSdkUtils::requireView('AccountHistoryForm', $processResultData, $this->getSettingValue('monetasdk_view_files_path'));

        return $processResultData;
    }

    /**
     * processCookieRedirect
     */
    private function processCookieRedirect()
    {
        $redirectUrl = MonetaSdkUtils::getSdkCookie('redirect');
        if (!$redirectUrl) {
            $redirectUrl = "/";
        }
        $this->pvtRedirectAfterForm($redirectUrl);
    }

    /**
     * @param $url
     */
    private function pvtRedirectAfterForm($url)
    {
        if (!headers_sent()) {
            header("Location: {$url}");
        }
        else {
            $this->render = '<script type="text/javascript">window.location.replace("'.$url.'");</script>';
        }
    }

}