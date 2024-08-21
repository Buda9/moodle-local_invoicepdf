<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_invoicepdf_install() {
    global $CFG;

    // Ensure the invoice template uses language strings
    $default_template = get_string('default_invoice_template', 'local_invoicepdf');
    set_config('invoice_template', $default_template, 'local_invoicepdf');
}