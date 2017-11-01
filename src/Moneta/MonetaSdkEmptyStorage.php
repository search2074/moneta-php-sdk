<?php

namespace Moneta;

use Moneta;

class MonetaSdkEmptyStorage implements MonetaSdkStorage
{
    public function createInvoice($saveInvoiceData) {  }

    public function updateInvoice($updateInvoiceData) {  }

    public function getInvoice($invoiceId) {  }

    public function getInvoiceByHash($tokenHash) {  }

    public function getInvoicesForNotifications() {  }

    public function getInvoicesForRepay() {  }

    public function createOperation() {  }

    public function updateOperation() {  }

    public function getOperation() {  }

    public function getInvoiceByOperationId($operationId) {  }

    public function getOperationIdByOrderId($orderId) {  }

}