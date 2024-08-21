<?php
defined('MOODLE_INTERNAL') || die();

function local_invoicepdf_extend_navigation(global_navigation $navigation) {
    global $USER, $PAGE;

    if (isloggedin() && !isguestuser()) {
        $node = navigation_node::create(
            get_string('user_invoice_archive', 'local_invoicepdf'),
            new moodle_url('/local/invoicepdf/archive.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'invoicepdfarchive',
            new pix_icon('i/report', '')
        );

        if ($PAGE->context->contextlevel == CONTEXT_USER && $PAGE->context->instanceid == $USER->id) {
            $navigation->add_node($node);
        } else {
            $usernode = $navigation->find('myprofile', navigation_node::TYPE_ROOTNODE);
            $usernode->add_node($node);
        }
    }
}

function local_invoicepdf_extend_settings_navigation(settings_navigation $navigation) {
    global $PAGE;

    if ($PAGE->context->contextlevel == CONTEXT_SYSTEM && has_capability('local/invoicepdf:manage', $PAGE->context)) {
        if ($settingsnode = $navigation->find('root', navigation_node::TYPE_SITE_ADMIN)) {
            $strfoo = get_string('pluginname', 'local_invoicepdf');
            $url = new moodle_url('/local/invoicepdf/admin/archive.php');
            $foonode = navigation_node::create(
                $strfoo,
                $url,
                navigation_node::TYPE_SETTING,
                null,
                null,
                new pix_icon('i/report', '')
            );
            if ($settingsnode) {
                $settingsnode->add_node($foonode);
            }
        }
    }
}

function local_invoicepdf_cron() {
    $config = get_config('local_invoicepdf');
    $archive_months = isset($config->archive_months) ? $config->archive_months : 24;

    $archived_count = \local_invoicepdf\invoice_archiver::archive_old_invoices($archive_months);

    mtrace("Archived $archived_count old invoices.");
}