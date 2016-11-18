<?php
    $hideAdditionalField = '';
    $autoSubmit = false;
    if (isset($data['autoSubmit']) && $data['autoSubmit']) {
        $autoSubmit = true;
        $hideAdditionalField = 'type="hidden"';
    }

    $widgetLink  = $data['action'];
    if (strpos($data['action'], '?') === false) {
        $widgetLink .= "?";
    }
    else {
        $widgetLink .= "&";
    }

    $widgetLink .= "MNT_ID=" . $data['accountId'] . "&MNT_TRANSACTION_ID=" . $data['orderId'] . "&MNT_CURRENCY_CODE=" . $data['currency'] .
        "&MNT_AMOUNT=" . $data['amount'] . "&MNT_DESCRIPTION=" . $data['description'] . "&MNT_TEST_MODE=" . $data['testMode'] . "&MNT_SIGNATURE=" . $data['signature'] .
        "&MNT_SUCCESS_URL=" . $data['successUrl'] . "&MNT_FAIL_URL=" . $data['failUrl'] . "&MNT_PAY_SYSTEM=" . $data['paySystem'] . "&MNT_IS_REGULAR=" . $data['isRegular'] .
        "&MNT_FORM_METHOD=" . $data['method'] .
        "&followup=true";

    if ($data['paymentSystemParams']['accountId']) {
        $widgetLink .= "&paymentSystem.accountId=" . $data['paymentSystemParams']['accountId'];
    }

    if ($data['paymentSystemParams']['unitId']) {
        $widgetLink .= "&paymentSystem.unitId=" . $data['paymentSystemParams']['unitId'];
        $widgetLink .= "&paymentSystem.limitIds=" . $data['paymentSystemParams']['unitId'];
    }


    if (count($data['postData']) && is_array($data['postData'])) {
        foreach ($data['postData'] AS $varData) {
            $widgetLink .= "&" . $varData['var'] . "=" . $varData['value'];
        }
    }

?>

<iframe src="<?= $widgetLink; ?>" width="468" height="605" align="left">
    Ваш браузер не поддерживает iframe!
</iframe>