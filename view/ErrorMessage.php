<?php
if (isset($data['errorMessageHumanConverted'])) {
?>
    <?= $data['errorMessageHumanConverted']; ?><br/>
<?php
}
else if (is_object($data) && isset($data->render)) {
?>
    <?= $data->render; ?>
<?php
}
?>
