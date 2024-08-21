<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class api {
    /**
     * Generate an invoice
     *
     * @param int $userid User ID
     * @param float $amount Amount to be invoiced
     * @param string $currency Currency code (e.g., 'USD', 'EUR')
     * @param string $description Description of the invoice item
     * @param array $additional_data Additional data for the invoice (optional)
     * @return int|bool The ID of the generated invoice, or false if generation failed
     */
    public static function generate_invoice($userid, $amount, $currency, $description, $additional_data = array()) {
        global $DB;

        // Validate input
        $user = \core_user::get_user($userid);
        if (!$user) {
            throw new \moodle_exception('invaliduser');
        }

        if (!is_numeric($amount) || $amount <= 0) {
            throw new \moodle_exception('invalidamount', 'local_invoicepdf');
        }

        // Generate invoice number
        $invoice_number = invoice_number_manager::get_next_invoice_number();

        // Create transaction object (simulating payment gateway transaction)
        $transaction = new \stdClass();
        $transaction->userid = $userid;
        $transaction->amount = $amount;
        $transaction->currency = $currency;

        // Generate PDF
        $invoice_generator = new invoice_generator($transaction, $user);
        $pdf_content = $invoice_generator->generate_pdf($invoice_number, $description, $additional_data);

        // Store invoice
        $invoice_id = invoice_manager::store_invoice(
            $userid,
            $invoice_number,
            $amount,
            $currency,
            $pdf_content
        );

        if ($invoice_id) {
            // Send email with attached PDF
            $invoice_generator->send_invoice_email($pdf_content, $invoice_number);
            return $invoice_id;
        }

        return false;
    }

    /**
     * Get invoice details
     *
     * @param int $invoice_id Invoice ID
     * @return object|bool Invoice object or false if not found
     */
    public static function get_invoice($invoice_id) {
        return invoice_manager::get_invoice($invoice_id);
    }

    /**
     * Get user's invoices
     *
     * @param int $userid User ID
     * @return array Array of invoice objects
     */
    public static function get_user_invoices($userid) {
        return invoice_manager::get_user_invoices($userid);
    }

    /**
     * Resend an invoice
     *
     * @param int $invoice_id Invoice ID
     * @return bool True if the invoice was resent successfully, false otherwise
     */
    public static function resend_invoice($invoice_id) {
        return invoice_manager::resend_invoice($invoice_id);
    }
}