<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function payment_completed(\core\event\base $event) {
        global $DB;

        $transaction = $event->get_record_snapshot('payments', $event->objectid);
        $user = $DB->get_record('user', ['id' => $transaction->userid]);

        $invoice_generator = new invoice_generator($transaction, $user);
        $pdf_content = $invoice_generator->generate_pdf();

        $invoice_number = invoice_number_manager::get_next_invoice_number();

        // Store the invoice
        $invoice_id = invoice_manager::store_invoice(
            $user->id,
            $invoice_number,
            $transaction->amount,
            $transaction->currency,
            $pdf_content
        );

        if ($invoice_id) {
            // Send email with attached PDF
            $result = $invoice_generator->send_invoice_email($pdf_content, $invoice_number);

            if (!$result) {
                \core\notification::error(get_string('invoice_email_failed', 'local_invoicepdf'));
            }
        } else {
            \core\notification::error(get_string('invoice_creation_failed', 'local_invoicepdf'));
        }
    }
}