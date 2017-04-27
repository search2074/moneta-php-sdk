<?php

namespace Moneta;

use Moneta;

interface MonetaSdkKassa
{
    const VAT0     = 1104;  // НДС 0%
    const VAT10    = 1103;  // НДС 10%
    const VAT18    = 1102;  // НДС 18%
    const VATNOVAT = 1105;  // НДС не облагается
    const VATWR10  = 1107;  // НДС с рассч. ставкой 10%
    const VATWR18  = 1106;  // НДС с рассч. ставкой 18%

    const ATOL_NONE   = 'none';
    const ATOL_VAT0   = 'vat0';
    const ATOL_VAT10  = 'vat10';
    const ATOL_VAT18  = 'vat18';
    const ATOL_VAT110 = 'vat110';
    const ATOL_VAT118 = 'vat118';

    public function authoriseKassa();

    public function checkKassaStatus();

    public function sendDocument($document);

    public function checkDocumentStatus();

}