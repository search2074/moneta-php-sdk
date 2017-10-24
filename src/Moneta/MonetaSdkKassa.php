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

    const STARRYS_NONE   = 4;
    const STARRYS_VAT0   = 3;
    const STARRYS_VAT10  = 2;
    const STARRYS_VAT18  = 1;
    const STARRYS_VAT110 = 6;
    const STARRYS_VAT118 = 5;

    const BUHSOFT_NONE   = 4;
    const BUHSOFT_VAT0   = 3;
    const BUHSOFT_VAT10  = 2;
    const BUHSOFT_VAT18  = 1;

    const IRETAIL_VAT0   = 0;
    const IRETAIL_VAT10  = 0.1;
    const IRETAIL_VAT18  = 0.18;


    const OPERATION_TYPE_SALE           = 'SALE';
    const OPERATION_TYPE_SALE_RETURN    = 'SALE_RETURN';

    const ATOL_METHOD_SALE          = 'sell';
    const ATOL_METHOD_SALE_RETURN   = 'sell_refund';

    const MODULE_DOC_TYPE_SALE          = 'SALE';
    const MODULE_DOC_TYPE_SALE_RETURN   = 'RETURN';

    const STARRYS_DOC_TYPE_SALE          = 0;
    const STARRYS_DOC_TYPE_SALE_RETURN   = 2;

    const BUHSOFT_DOC_TYPE_SALE          = 0;
    const BUHSOFT_DOC_TYPE_SALE_RETURN   = 1;

    const IRETAIL_DOC_TYPE_SALE          = 'payment';
    const IRETAIL_DOC_TYPE_SALE_RETURN   = 'refund';

    public function authoriseKassa();

    public function checkKassaStatus();

    public function sendDocument($document);

    public function checkDocumentStatus();

}