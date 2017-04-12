<?php

namespace Moneta;

use Moneta;

interface MonetaSdkKassa
{
    public function authoriseKassa();

    public function checkKassaStatus();

    public function sendDocument($document);

    public function checkDocumentStatus();

}