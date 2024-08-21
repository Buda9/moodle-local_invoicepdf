<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function payment_completed(\core\event\base $event) {
        global $DB;

        $eventdata = $event->get_data();
        $paymentid = $eventdata['objectid'];
        $payment = $DB->get_record('payments', ['id' => $paymentid]);

        if (!$payment) {
            \core\notification::error(get_string('payment_not_found', 'local_invoicepdf'));
            return;
        }

        $user = $DB->get_record('user', ['id' => $payment->userid]);

        // Check if this payment gateway is enabled for invoice generation
        $enabled_gateways = get_config('local_invoicepdf', 'enabled_gateways');
        if ($enabled_gateways !== '0' && !in_array($payment->gateway, explode(',', $enabled_gateways))) {
            return; // This gateway is not enabled for invoice generation
        }

        $invoice_generator = new invoice_generator($payment, $user);
        $pdf_content = $invoice_generator->generate_pdf();

        $invoice_number = invoice_number_manager::get_next_invoice_number();

        // Store the invoice
        $invoice_id = invoice_manager::store_invoice(
            $user->id,
            $invoice_number,
            $payment->amount,
            $payment->currency,
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