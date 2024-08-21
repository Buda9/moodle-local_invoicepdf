<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('invoicepdfarchive');

$PAGE->set_url(new moodle_url('/local/invoicepdf/admin/archive.php'));
$PAGE->set_title(get_string('admin_invoice_archive', 'local_invoicepdf'));
$PAGE->set_heading(get_string('admin_invoice_archive', 'local_invoicepdf'));

$page = optional_param('page', 0, PARAM_INT);
$perpage = get_config('local_invoicepdf', 'invoices_per_page');

// Handle invoice deletion
$delete = optional_param('delete', 0, PARAM_INT);
if ($delete && confirm_sesskey()) {
    if (\local_invoicepdf\invoice_manager::delete_invoice($delete)) {
        \core\notification::success(get_string('invoice_deleted', 'local_invoicepdf'));
    } else {
        \core\notification::error(get_string('invoice_delete_failed', 'local_invoicepdf'));
    }
}

echo $OUTPUT->header();

$invoices = \local_invoicepdf\invoice_manager::get_all_invoices($page, $perpage);
$totalcount = \local_invoicepdf\invoice_manager::get_all_invoices_count();

$table = new html_table();
$table->head = array(
    get_string('invoice_number', 'local_invoicepdf'),
    get_string('date', 'local_invoicepdf'),
    get_string('user', 'local_invoicepdf'),
    get_string('amount', 'local_invoicepdf'),
    get_string('actions', 'local_invoicepdf')
);

foreach ($invoices as $invoice) {
    $user = \core_user::get_user($invoice->userid);
    $actions = html_writer::link(
        new moodle_url('/local/invoicepdf/download.php', array('id' => $invoice->id)),
        get_string('download', 'local_invoicepdf')
    );
    $actions .= ' ' . html_writer::link(
        new moodle_url('/local/invoicepdf/resend.php', array('id' => $invoice->id)),
        get_string('resend', 'local_invoicepdf')
    );
    $actions .= ' ' . html_writer::link(
        new moodle_url($PAGE->url, array('delete' => $invoice->id, 'sesskey' => sesskey())),
        get_string('delete', 'local_invoicepdf'),
        array('onclick' => 'return confirm("'.get_string('delete_invoice_confirm', 'local_invoicepdf').'");')
    );
    $table->data[] = array(
        $invoice->invoice_number,
        userdate($invoice->timecreated),
        fullname($user),
        $invoice->amount . ' ' . $invoice->currency,
        $actions
    );
}

echo html_writer::table($table);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);

echo $OUTPUT->footer();