<?php
if (is_array($data) && count($data)) {
    ?>
    <form name="ChoosePaymentSystemForm" method="get">
        <?php
        foreach ($data AS $paySysKey => $paySysVal) {
            ?>
            <input name="choosePaySysByType" type="radio" value="<?= $paySysKey; ?>" /><?= $paySysVal['title']; ?><br/>
            <?php
        }
        ?>
        <br/>
        <input type="submit" value="Choose">
    </form>
    <?php
}
?>