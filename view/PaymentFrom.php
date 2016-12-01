<?php
    $hideAdditionalField = '';
    $autoSubmit = false;
    if (isset($data['autoSubmit']) && $data['autoSubmit']) {
        $autoSubmit = true;
        $hideAdditionalField = 'type="hidden"';
    }
?>

<form name="<?= $data['formName']; ?>" id="<?= $data['formId']; ?>" action="<?= $data['action']; ?>" method="<?= $data['method']; ?>">

    <input type="hidden" name="MNT_ID" value="<?= $data['accountId']; ?>">
    <input type="hidden" name="MNT_TRANSACTION_ID" value="<?= $data['orderId']; ?>">
    <input type="hidden" name="MNT_CURRENCY_CODE" value="<?= $data['currency']; ?>">
    <input type="hidden" name="MNT_AMOUNT" value="<?= $data['amount']; ?>">
    <input type="hidden" name="MNT_DESCRIPTION" value="<?= $data['description']; ?>">
    <input type="hidden" name="MNT_TEST_MODE" value="<?= $data['testMode']; ?>">
    <input type="hidden" name="MNT_SIGNATURE" value="<?= $data['signature']; ?>">
    <input type="hidden" name="MNT_SUCCESS_URL" value="<?= $data['successUrl']; ?>">
    <input type="hidden" name="MNT_FAIL_URL" value="<?= $data['failUrl']; ?>">

    <input type="hidden" name="followup" value="true">

    <?php
        if ($data['paymentSystemParams']['accountId']) {
    ?>
            <input type="hidden" name="paymentSystem.accountId" value="<?= $data['paymentSystemParams']['accountId']; ?>">
    <?php
        }
    ?>
    
    <?php
        if ($data['paymentSystemParams']['unitId']) {
    ?>
            <input type="hidden" name="paymentSystem.unitId" value="<?= $data['paymentSystemParams']['unitId']; ?>">
            <input type="hidden" name="paymentSystem.limitIds" value="<?= $data['paymentSystemParams']['unitId']; ?>">
    <?php
        }
    ?>

    <input type="hidden" name="MNT_PAY_SYSTEM" value="<?= $data['paySystem']; ?>">
    <input type="hidden" name="MNT_IS_REGULAR" value="<?= $data['isRegular']; ?>">
    <input type="hidden" name="MNT_IS_IFRAME" value="<?= $data['isIframe']; ?>">
    <input type="hidden" name="MNT_FORM_METHOD" value="<?= $data['method']; ?>">

    <?php
        if (count($data['postData']) && is_array($data['postData'])) {
            foreach ($data['postData'] AS $varData) {
                if ($varData['value']) {
                    $hideAdditionalField = 'type="hidden"';
                }
    ?>
        <span style="<?= ($hideAdditionalField) ? 'display: none;' : ''; ?>"><?= $autoSubmit ? '' : $varData['name']; ?> </span><input <?= ($hideAdditionalField) ? $hideAdditionalField : 'type="text"'; ?> name="<?= $varData['var']; ?>" value="<?= $varData['value']; ?>"><?= ($hideAdditionalField) ? '' : '<br/>'; ?>
    <?php
            }
        }
    ?>

    <?php
    if (count($data['forwardFields']) && is_array($data['forwardFields'])) {
        foreach ($data['forwardFields'] AS $key => $val) {
            ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $val; ?>">
            <?php
        }
    }
    if (count($data['additionalData']) && is_array($data['additionalData'])) {
        foreach ($data['additionalData'] AS $key => $value) {
            ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
            <?php
        }
    }
    ?>

    <?php
        if (!$autoSubmit) {
    ?>
            <input type="submit" name="submit" value="Оплатить">
    <?php
        }
        else {
    ?>
            <script type="text/javascript">document.getElementById('<?= $data['formId']; ?>').submit();</script>
    <?php
        }
    ?>

</form>