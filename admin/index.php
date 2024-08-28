<?php

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_invoicepdf_admin');

$PAGE->set_url(new moodle_url('/local/invoicepdf/admin/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_invoicepdf'));
$PAGE->set_heading(get_string('invoiceadmin', 'local_invoicepdf'));

echo $OUTPUT->header();

// Display summary statistics
$totalinvoices = $DB->count_records('local_invoicepdf_invoices');
$totalamount = $DB->get_field_sql("SELECT SUM(amount) FROM {local_invoicepdf_invoices}");

echo html_writer::tag('h3', get_string('summary', 'local_invoicepdf'));
echo html_writer::tag('p', get_string('totalinvoices', 'local_invoicepdf', $totalinvoices));
echo html_writer::tag('p', get_string('totalamount', 'local_invoicepdf', format_float($totalamount, 2)));

// Add chart for visualizing invoice data
echo html_writer::tag('h3', get_string('invoicechart', 'local_invoicepdf'));
echo html_writer::tag('div', '', array('id' => 'invoiceChart', 'style' => 'width: 100%; height: 400px;'));

// Add export functionality
echo html_writer::tag('h3', get_string('exportinvoices', 'local_invoicepdf'));
$exporturl = new moodle_url('/local/invoicepdf/export.php');
echo html_writer::link($exporturl, get_string('exportcsv', 'local_invoicepdf'), array('class' => 'btn btn-primary'));

// Add table of recent invoices
echo html_writer::tag('h3', get_string('recentinvoices', 'local_invoicepdf'));

$table = new html_table();
$table->head = array(
    get_string('invoicenumber', 'local_invoicepdf'),
    get_string('date', 'local_invoicepdf'),
    get_string('user'),
    get_string('amount', 'local_invoicepdf'),
    get_string('actions')
);

$recentinvoices = $DB->get_records('local_invoicepdf_invoices', null, 'timecreated DESC', '*', 0, 10);

foreach ($recentinvoices as $invoice) {
    $user = $DB->get_record('user', array('id' => $invoice->userid));
    $viewurl = new moodle_url('/local/invoicepdf/view.php', array('id' => $invoice->id));
    $actions = html_writer::link($viewurl, get_string('view'));

    $table->data[] = array(
        $invoice->invoice_number,
        userdate($invoice->timecreated),
        fullname($user),
        format_float($invoice->amount, 2) . ' ' . $invoice->currency,
        $actions
    );
}

echo html_writer::table($table);

// Load JavaScript for chart
$PAGE->requires->js_call_amd('local_invoicepdf/charts', 'init');

echo $OUTPUT->footer();