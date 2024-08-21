<?php
require_once('../../config.php');
require_login();

$id = required_param('id', PARAM_INT);

$invoice = \local_invoicepdf\invoice_manager::get_invoice($id);

if (!$invoice || ($invoice->userid != $USER->id && !is_siteadmin())) {
    print_error('invalidaccess');
}

$pdf_content = \local_invoicepdf\invoice_manager::get_invoice_pdf($id);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice_'.$invoice->invoice_number.'.pdf"');
header('Content-Length: ' . strlen($pdf_content));

echo $pdf_content;
exit();