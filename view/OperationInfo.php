<?php
    if (isset($data['info']) && is_object($data['info']) && isset($data['info']->error) && !$data['info']->error && isset($data['info']->data) && isset($data['info']->data->operation)) {

        $operation = (object) $data['info']->data->operation;
        $attributes = $operation->attribute;

        $operationCurrency      = '';
        $operationAmount        = '';
        $operationDescription   = '';
        $operationStatusId      = '';
        $operationDate          = '';

        foreach ($attributes AS $attributeVal) {
            $attribute = (object) $attributeVal;
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
    ID: <?= $operation->id; ?><br/>
    Date: <?= $operationDate; ?><br/>
    Status: <?= $operationStatusId; ?><br/>
    Amount: <?= $operationAmount; ?><br/>
    Currency: <?= $operationCurrency; ?><br/>
    Description: <?= $operationDescription; ?><br/>
<?php
    }
    else {
?>
    <h4>Информация об операции не получена</h4>
<?php
    }
?>