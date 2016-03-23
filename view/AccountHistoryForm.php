<form name="AccountHistoryForm" method="get">
    <input type="hidden" name="moneta_sdk_account" value="<?= $data['account']; ?>" />

    <h3>Получить историю операций</h3>
    С <input type="text" class="input" name="moneta_sdk_date_from" value="<?php if (isset($data['moneta_sdk_date_from'])) { echo $data['moneta_sdk_date_from']; } ?>" /> по <input type="text" class="input" name="moneta_sdk_date_to" value="<?php if (isset($data['moneta_sdk_date_to'])) { echo $data['moneta_sdk_date_to']; } ?>" /><br/><br/>

<?php
    if (isset($data['history']) && is_object($data['history']) && isset($data['history']->operation) && isset($data['history']->totalSize) && $data['history']->totalSize > 0) {
        $operations = $data['history']->operation;
        if (is_object($operations)) {
            $operationsNew = array();
            $operationsNew[] = $operations;
            $operations = $operationsNew;
        }

        if (is_array($operations) && count($operations)) {
?>
        <table border="1">
            <tr>
                <td>ID</td>
                <td>Date</td>
                <td>Status</td>
                <td>Amount</td>
                <td>Currency</td>
                <td>Description</td>
            </tr>
        <?php
            foreach ($operations AS $operation) {
                $attributes = $operation->attribute;
        ?>
            <tr>
                <td><?= $operation->id; ?></td>
                <?php
                    $operationCurrency      = '';
                    $operationAmount        = '';
                    $operationDescription   = '';
                    $operationStatusId      = '';
                    $operationDate          = '';

                    foreach ($attributes AS $attribute) {
                        switch ($attribute->key) {
                            case 'created':
                                $operationDate = date('d.m.Y, H:i', strtotime($attribute->value));
                                break;
                            case 'sourceamounttotal':
                                $operationAmount = $attribute->value;
                                break;
                            case 'statusid':
                                $operationStatusId = $attribute->value;
                                break;
                            case 'targetcurrencycode':
                                $operationCurrency = $attribute->value;
                                break;
                            case 'description':
                                $operationDescription = nl2br($attribute->value);
                                break;
                        }
                    }
                ?>
                <td><?= $operationDate; ?></td>
                <td><?= $operationStatusId; ?></td>
                <td><?= $operationAmount; ?></td>
                <td><?= $operationCurrency; ?></td>
                <td><?= $operationDescription; ?></td>
            </tr>
        <?php
            }
        ?>
        </table><br/>

        <?php
            if ($data['history']->pagesCount > 1) {
        ?>
            <select name="moneta_sdk_page_number">
            <?php
                for ($i = 1; $i <= $data['history']->pagesCount; $i++) {
                    $activePageClass = '';
                    if ($data['history']->pageNumber == $i) {
                        $activePageClass = 'selected="selected" ';
                    }
            ?>
                <option value="<?= $i; ?>"<?= $activePageClass; ?>><?= $i; ?></option>
            <?php
                }
            ?>
            </select>
        <?php
            }
        ?>

<?php
        }
    }
    else if (isset($data['history']) && is_object($data['history'])) {
?>
    <h4>No operation found</h4>
<?php
    }
?>

    <input type="submit" value="Choose">

</form>
