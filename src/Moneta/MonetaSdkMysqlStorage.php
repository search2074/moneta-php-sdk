<?php

namespace Moneta;

use Moneta;

class MonetaSdkMysqlStorage implements MonetaSdkStorage
{
    const EXCEPTION_NO_MYSQL = 'No connection: ';

    const EXCEPTION_NO_DB = 'No database: ';

    const TABLE_NAME_INVOICE = 'sdk_invoices';

    private $mysqlConnector;


    public function __construct($storageSettings)
    {
        if (!$this->mysqlConnector) {
            $host = $storageSettings['monetasdk_storage_mysql_port'] ? $storageSettings['monetasdk_storage_mysql_host'] . ":" . $storageSettings['monetasdk_storage_mysql_port'] : $storageSettings['monetasdk_storage_mysql_host'];
            $link = mysql_connect($host, $storageSettings['monetasdk_storage_mysql_username'], $storageSettings['monetasdk_storage_mysql_password']);
            if (!$link) {
                throw new MonetaSdkException(self::EXCEPTION_NO_MYSQL . 'MonetaSdkMysqlStorage');
            }
            $database = mysql_select_db($storageSettings['monetasdk_storage_mysql_database'], $link);
            if (!$database) {
                throw new MonetaSdkException(self::EXCEPTION_NO_DB . 'MonetaSdkMysqlStorage');
            }

            $this->mysqlConnector = $link;
            if (!$this->checkInvoiceTableIsExists()) {
                $this->createInvoiceTable();
            }
        }
    }

    public function __destruct()
    {
        if ($this->mysqlConnector) {
            mysql_close($this->mysqlConnector);
        }
    }

    /**
     * @param $saveInvoiceData
     */
    public function createInvoice($saveInvoiceData)
    {
        $arrFields = array();
        $arrValues = array();
        foreach ($saveInvoiceData AS $key => $val) {
            $arrFields[] = $key;
            $arrValues[] = "'" . $this->prepareValue($val) . "'";
        }

        $strFields = implode(',', $arrFields);
        $strValues = implode(',', $arrValues);
        $sql = "INSERT INTO `" . self::TABLE_NAME_INVOICE . "` ({$strFields}) VALUES ({$strValues})";
        mysql_query($sql, $this->mysqlConnector);
    }

    /**
     * @param $updateInvoiceData
     */
    public function updateInvoice($updateInvoiceData)
    {
        $invoiceId = $this->prepareValue($updateInvoiceData['invoiceId']);
        $arrPair = array();
        foreach ($updateInvoiceData AS $key => $val) {
            if ($key != 'invoiceId') {
                $arrPair[] = "{$key} = '" . $this->prepareValue($val) . "'";
            }
        }
        $strPair = implode(',', $arrPair);
        $sql = "UPDATE `" . self::TABLE_NAME_INVOICE . "` SET {$strPair} WHERE invoiceId = '{$invoiceId}'";
        mysql_query($sql, $this->mysqlConnector);
    }

    /**
     * @param $invoiceId
     * @return array|bool
     */
    public function getInvoice($invoiceId)
    {
        $result = false;
        $invoiceId = $this->prepareValue($invoiceId);
        $sql = "SELECT * FROM `" . self::TABLE_NAME_INVOICE . "` WHERE invoiceId = '{$invoiceId}'";
        $retval = mysql_query($sql, $this->mysqlConnector);
        if ($retval) {
            while ($row = mysql_fetch_array($retval, MYSQL_ASSOC)) {
                if ($row) {
                    $result = $row;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param $tokenHash
     * @return array|bool
     */
    public function getInvoiceByHash($tokenHash)
    {
        $result = false;
        $tokenHash = $this->prepareValue($tokenHash);
        $sql = "SELECT * FROM `" . self::TABLE_NAME_INVOICE . "` WHERE tokenHash = '{$tokenHash}'";
        $retval = mysql_query($sql, $this->mysqlConnector);
        if ($retval) {
            while ($row = mysql_fetch_array($retval, MYSQL_ASSOC)) {
                if ($row) {
                    $result = $row;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getInvoicesForNotifications()
    {
        $result = array();
        $sql = "SELECT * FROM `" . self::TABLE_NAME_INVOICE . "` WHERE invoiceStatus = '" . MonetaSdk::STATUS_FINISHED . "' AND dateNotify IS NOT NULL AND dateNotify <= NOW()";
        $retval = mysql_query($sql, $this->mysqlConnector);
        if ($retval) {
            while ($row = mysql_fetch_array($retval, MYSQL_ASSOC)) {
                if ($row) {
                    $result[] = $row;
                }
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getInvoicesForRepay()
    {
        $result = array();
        $sql = "SELECT * FROM `" . self::TABLE_NAME_INVOICE . "` WHERE invoiceStatus = '" . MonetaSdk::STATUS_FINISHED . "' AND dateTarget IS NOT NULL AND dateTarget <= NOW()";
        $retval = mysql_query($sql, $this->mysqlConnector);
        if ($retval) {
            while ($row = mysql_fetch_array($retval, MYSQL_ASSOC)) {
                if ($row) {
                    $result[] = $row;
                }
            }
        }

        return $result;
    }

    public function createOperation()
    {

    }

    public function updateOperation()
    {

    }

    public function getOperation()
    {

    }

    /**
     * @return bool
     */
    private function checkInvoiceTableIsExists()
    {
        if (mysql_num_rows(mysql_query("SHOW TABLES LIKE '" . self::TABLE_NAME_INVOICE . "'", $this->mysqlConnector)) > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * createInvoiceTable
     */
    private function createInvoiceTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME_INVOICE . "` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `invoiceId` int(11) NOT NULL,
          `invoiceStatus` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
          `amount` DECIMAL( 11, 2 ) NOT NULL DEFAULT '0',
          `tokenHash` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
          `paymentToken` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
          `dateNotify` datetime NOT NULL,
          `dateTarget` datetime NOT NULL,
          `payer` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
          `payee` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
          `orderId` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
          `paymentSystem` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
          `notificationEmail` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
          `recursion` int(11) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`),
          KEY `invoiceId` (`invoiceId`),
          KEY `invoiceStatus` (`invoiceStatus`,`tokenHash`,`dateNotify`,`dateTarget`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
        ";

        mysql_query($sql, $this->mysqlConnector);
    }

    /**
     * @param $valluesArray
     * @return array
     */
    private function prepareArray($valluesArray)
    {
        if (!is_array($valluesArray) || !count($valluesArray)) {
            return array();
        }
        $result = array();
        foreach ($valluesArray AS $key => $val) {
            $result[$key] = $this->prepareValue($val);
        }
        return $result;
    }

    /**
     * @param $value
     * @return string
     */
    private function prepareValue($value)
    {
        return mysql_real_escape_string(trim(htmlspecialchars($value)));
    }

}