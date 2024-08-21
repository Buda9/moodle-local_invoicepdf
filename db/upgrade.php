<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_invoicepdf_upgrade($oldversion) {
    global $CFG;

    $result = true;

    if ($oldversion < 2024082001) {
        // Update existing invoice template to use language strings
        $default_template = get_string('default_invoice_template', 'local_invoicepdf');
        set_config('invoice_template', $default_template, 'local_invoicepdf');

        upgrade_plugin_savepoint(true, 2024082001, 'local', 'invoicepdf');
    }

    return $result;
}