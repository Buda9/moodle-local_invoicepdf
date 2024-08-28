<?php

namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function payment_completed(\core\event\payment_completed $event) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/invoicepdf/lib.php');

        mtrace("Invoice PDF: Payment completed event triggered for payment ID: " . $event->objectid);

        // Fetch the payment record
        $payment = $DB->get_record('payments', ['id' => $event->objectid]);
        if (!$payment) {
            mtrace("Invoice PDF: Payment record not found for ID: " . $event->objectid);
            return;
        }

        // Check if this payment gateway is enabled for invoice generation
        $enabled_gateways = get_config('local_invoicepdf', 'enabled_gateways');
        $enabled_gateways = $enabled_gateways ? explode(',', $enabled_gateways) : [];

        if (empty($enabled_gateways) || !in_array($payment->gateway, $enabled_gateways)) {
            // No gateway has been choosen or current gateway is not selected
            return; // Don't generate invoice
        }

        // Fetch the user record
        $user = $DB->get_record('user', ['id' => $payment->userid]);
        if (!$user) {
            mtrace("Invoice PDF: User not found for ID: " . $payment->userid);
            return;
        }

        // Generate the invoice
        $invoice_generator = new invoice_generator($payment, $user);
        $pdf_content = $invoice_generator->generate_pdf();
        if (!$pdf_content) {
            mtrace("Invoice PDF: Failed to generate PDF for payment ID: " . $event->objectid);
            return;
        }

        // Get the next invoice number
        $invoice_number = invoice_number_manager::get_next_invoice_number();

        // Store the invoice
        $invoice_id = invoice_manager::store_invoice(
            $user->id,
            $invoice_number,
            $payment->amount,
            $payment->currency,
            $pdf_content
        );

        if (!$invoice_id) {
            mtrace("Invoice PDF: Failed to store invoice for payment ID: " . $event->objectid);
            return;
        }

        // Send the invoice email
        $result = $invoice_generator->send_invoice_email($pdf_content, $invoice_number);
        if (!$result) {
            mtrace("Invoice PDF: Failed to send invoice email for payment ID: " . $event->objectid);
        } else {
            mtrace("Invoice PDF: Successfully processed invoice for payment ID: " . $event->objectid);
        }
    }
}