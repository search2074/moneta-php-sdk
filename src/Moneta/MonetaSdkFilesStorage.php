<?php

namespace Moneta;

use Moneta;

class MonetaSdkFilesStorage implements MonetaSdkStorage
{
    const EXCEPTION_NO_FILE = 'Data file is not available: ';

    const EXCEPTION_NO_PERMISSIONS = 'Set 0777 permissions to the "../integrationmonitoring/moneta-php-sdk/data" folder';

    const DATA_PATH = '/../../data/';

    const DATA_NAME = 'filedb.dat';

    private $fileName;

    private $fileHandler;


    /**
     * @param $storageSettings
     * @throws MonetaSdkException
     */
    public function __construct($storageSettings)
    {
        if (!$this->fileHandler) {
            $this->fileName     = ($storageSettings['monetasdk_storage_files_path']) ? $storageSettings['monetasdk_storage_files_path'] . self::DATA_NAME : __DIR__ . self::DATA_PATH;
            if (!chmod($this->fileName, 0777)) {
                throw new MonetaSdkException(self::EXCEPTION_NO_PERMISSIONS);
            }
            $this->fileName    .= self::DATA_NAME;
            $this->fileHandler  = fopen($this->fileName, "a");
            if (!$this->fileHandler || !is_resource($this->fileHandler)) {
                throw new MonetaSdkException(self::EXCEPTION_NO_FILE . 'MonetaSdkFilesStorage');
            }
        }
    }

    /**
     * Unlock and fclose data file
     */
    public function __destruct()
    {
        if ($this->fileHandler && is_resource($this->fileHandler)) {
            fflush($this->fileHandler);
            flock($this->fileHandler, LOCK_UN);
            fclose($this->fileHandler);
        }
    }

    /**
     * @param $invoiceData
     */
    public function createInvoice($invoiceData)
    {
        $sourceArray = $this->getSourceArray();
        $destinationArray = array_merge($sourceArray, array('invoice_' . time() => $invoiceData));
        file_put_contents($this->fileName, serialize($destinationArray));
    }

    /**
     * @param $updateInvoiceData
     */
    public function updateInvoice($updateInvoiceData)
    {
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            $destinationArray = array();
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                $destinationItem = $sourceItem;
                if (isset($sourceItem['invoiceId']) && $updateInvoiceData['invoiceId'] == $sourceItem['invoiceId']) {
                    $destinationItem['invoiceStatus']   = $updateInvoiceData['invoiceStatus'];
                    $destinationItem['tokenHash']       = $updateInvoiceData['tokenHash'];
                    $destinationItem['paymentToken']    = $updateInvoiceData['paymentToken'];
                    $destinationItem['dateNotify']      = $updateInvoiceData['dateNotify'];
                    $destinationItem['dateTarget']      = $updateInvoiceData['dateTarget'];
                }
                $destinationArray[$sourceItemKey] = $destinationItem;
            }
            file_put_contents($this->fileName, serialize($destinationArray));
        }
    }

    /**
     * @param $invoiceId
     * @return bool
     */
    public function getInvoice($invoiceId)
    {
        $result = false;
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                if (isset($sourceItem['invoiceId']) && $invoiceId == $sourceItem['invoiceId']) {
                    $result = $sourceItem;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param $tokenHash
     * @return bool
     */
    public function getInvoiceByHash($tokenHash)
    {
        $result = false;
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                if (isset($sourceItem['tokenHash']) && $tokenHash == $sourceItem['tokenHash']) {
                    $result = $sourceItem;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param $operationId
     * @return bool|mixed
     */
    public function getInvoiceByOperationId($operationId)
    {
        $result = false;
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                if (isset($sourceItem['invoiceId']) && $operationId == $sourceItem['invoiceId']) {
                    $result = $sourceItem;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param $orderId
     * @return
     */
    public function getOperationIdByOrderId($orderId)
    {
        $result = null;
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                if (isset($sourceItem['orderId']) && $orderId == $sourceItem['orderId']) {
                    $result = $sourceItem['invoiceId'];
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
        $currentTime = time();
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            $destinationArray = array();
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                if (isset($sourceItem['invoiceStatus']) && isset($sourceItem['dateNotify'])
                    && $sourceItem['invoiceStatus'] == MonetaSdk::STATUS_FINISHED && strtotime($sourceItem['dateNotify']) <= $currentTime) {
                    $destinationArray[$sourceItemKey] = $sourceItem;
                }
            }
        }

        return $destinationArray;
    }

    /**
     * @return array
     */
    public function getInvoicesForRepay()
    {
        $currentTime = time();
        $sourceArray = $this->getSourceArray();
        if (is_array($sourceArray) && count($sourceArray)) {
            $destinationArray = array();
            foreach ($sourceArray AS $sourceItemKey => $sourceItem) {
                if (isset($sourceItem['invoiceStatus']) && isset($sourceItem['dateTarget'])
                    && $sourceItem['invoiceStatus'] == MonetaSdk::STATUS_FINISHED && strtotime($sourceItem['dateTarget']) <= $currentTime) {
                    $destinationArray[$sourceItemKey] = $sourceItem;
                }
            }
        }

        return $destinationArray;
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
     * @return array|mixed|null
     */
    private function getSourceArray()
    {
        $fileContent = file_get_contents($this->fileName);
        $sourceArray = null;
        if ($fileContent) {
            $sourceArray = @unserialize($fileContent);
        }
        if (!$sourceArray) {
            $sourceArray = array();
        }
        return $sourceArray;
    }

}