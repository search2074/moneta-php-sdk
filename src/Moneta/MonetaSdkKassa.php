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

    const ORANGEDATA_NONE   = 6;
    const ORANGEDATA_VAT0   = 5;
    const ORANGEDATA_VAT10  = 2;
    const ORANGEDATA_VAT18  = 1;
    const ORANGEDATA_VAT110 = 4;
    const ORANGEDATA_VAT118 = 3;

    const DREAMKASS_NDS_NO_TAX  = "NDS_NO_TAX";
    const DREAMKASS_NDS_0       = "NDS_0";
    const DREAMKASS_NDS_10      = "NDS_10";
    const DREAMKASS_NDS_18      = "NDS_18";
    const DREAMKASS_NDS_110     = "NDS_10_CALCULATED";
    const DREAMKASS_NDS_118     = "NDS_18_CALCULATED";


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

    const ORANGEDATA_DOC_TYPE_SALE          = 1;
    const ORANGEDATA_DOC_TYPE_SALE_RETURN   = 2;

    const DREAMKASS_DOC_TYPE_SALE          = 'SALE';
    const DREAMKASS_DOC_TYPE_SALE_RETURN   = 'RETURN';


    public function authoriseKassa();

    public function checkKassaStatus();

    public function sendDocument($document);

    public function checkDocumentStatus();

}