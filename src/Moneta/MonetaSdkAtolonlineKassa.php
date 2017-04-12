<?php

namespace Moneta;

use Moneta;

class MonetaSdkAtolonlineKassa implements MonetaSdkKassa
{
    public $kassaStorageSettings;

    public function __construct($storageSettings)
    {
        $this->kassaStorageSettings = $storageSettings;
    }

    public function __destruct()
    {

    }

    public function authoriseKassa()
    {

    }

    public function checkKassaStatus()
    {

    }

    public function sendDocument($document)
    {

    }

    public function checkDocumentStatus()
    {

    }

}