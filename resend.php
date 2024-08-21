<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('invoicepdfarchive');

$id = required_param('id', PARAM_INT);

$invoice = \local_invoicepdf\invoice_manager::get_invoice($id);

if (!$invoice) {
    print_error('invalidinvoice', 'local_invoicepdf');
}

if (\local_invoicepdf\invoice_manager::resend_invoice($id)) {
    redirect(new moodle_url('/local/invoicepdf/admin/archive.php'), get_string('invoice_resent', 'local_invoicepdf'));
} else {
    print_error('invoice_resend_failed', 'local_invoicepdf');
}