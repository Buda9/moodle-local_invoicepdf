<?php
require_once('../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/invoicepdf/archive.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('user_invoice_archive', 'local_invoicepdf'));
$PAGE->set_heading(get_string('user_invoice_archive', 'local_invoicepdf'));

$page = optional_param('page', 0, PARAM_INT);
$perpage = get_config('local_invoicepdf', 'invoices_per_page');

echo $OUTPUT->header();

$invoices = \local_invoicepdf\invoice_manager::get_user_invoices($USER->id, $page, $perpage);
$totalcount = \local_invoicepdf\invoice_manager::get_user_invoices_count($USER->id);

$table = new html_table();
$table->head = array(
    get_string('invoice_number', 'local_invoicepdf'),
    get_string('date', 'local_invoicepdf'),
    get_string('amount', 'local_invoicepdf'),
    get_string('actions', 'local_invoicepdf')
);

foreach ($invoices as $invoice) {
    $actions = html_writer::link(
        new moodle_url('/local/invoicepdf/download.php', array('id' => $invoice->id)),
        get_string('download', 'local_invoicepdf')
    );
    $table->data[] = array(
        $invoice->invoice_number,
        userdate($invoice->timecreated),
        $invoice->amount . ' ' . $invoice->currency,
        $actions
    );
}

echo html_writer::table($table);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);

echo $OUTPUT->footer();