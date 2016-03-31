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

            // check tables existance
            if (!$this->checkInvoiceTableIsExists()) {
                // create table invoices
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

    public function createInvoice($saveInvoiceData)
    {

    }

    public function updateInvoice($updateInvoiceData)
    {

    }

    public function getInvoice($invoiceId)
    {

    }

    public function getInvoiceByHash($tokenHash)
    {

    }

    public function getInvoicesForNotifications()
    {

    }

    public function getInvoicesForRepay()
    {

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

    private function checkInvoiceTableIsExists()
    {
        if (mysql_num_rows(mysql_query("SHOW TABLES LIKE '" . self::TABLE_NAME_INVOICE . "'")) > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function createInvoiceTable()
    {
        $sql = "";
        mysql_query($sql);
    }

}