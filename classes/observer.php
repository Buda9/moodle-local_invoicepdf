<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Observer for payment events.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

use core\event\payment_completed;
use core_payment\helper;
use moodle_exception;

/**
 * Class observer
 *
 * @package local_invoicepdf
 */
class observer {

    /**
     * Handle the payment completed event.
     *
     * @param payment_completed $event The payment completed event.
     * @return void
     */
    public static function payment_completed(payment_completed $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/invoicepdf/lib.php');

        $paymentid = $event->objectid;
        debugging("Invoice PDF: Payment completed event triggered for payment ID: $paymentid", DEBUG_DEVELOPER);

        try {
            // Fetch the payment record using Moodle's payment API
            $payment = helper::get_payment_by_id($paymentid);
            if (!$payment) {
                throw new moodle_exception('paymentnotfound', 'local_invoicepdf', '', $paymentid);
            }

            debugging("Invoice PDF: Payment details - Gateway: {$payment->get_gateway()}, Amount: {$payment->get_amount()}, Currency: {$payment->get_currency()}", DEBUG_DEVELOPER);

            // Check if this payment gateway is enabled for invoice generation
            $enabled_gateways = get_config('local_invoicepdf', 'enabled_gateways');
            debugging("Invoice PDF: Enabled gateways configuration: $enabled_gateways", DEBUG_DEVELOPER);

            $enabled_gateways = $enabled_gateways ? explode(',', $enabled_gateways) : [];

            if (empty($enabled_gateways)) {
                debugging("Invoice PDF: No gateways are enabled for invoice generation", DEBUG_DEVELOPER);
                return; // Don't generate invoice
            }

            if (!in_array($payment->get_gateway(), $enabled_gateways)) {
                debugging("Invoice PDF: Gateway not enabled for invoice generation: " . $payment->get_gateway(), DEBUG_DEVELOPER);
                return; // Don't generate invoice
            }

            // Fetch the user record
            $user = $DB->get_record('user', ['id' => $payment->get_userid()]);
            if (!$user) {
                throw new moodle_exception('usernotfound', 'local_invoicepdf', '', $payment->get_userid());
            }

            debugging("Invoice PDF: Processing invoice for user: {$user->id} ({$user->username})", DEBUG_DEVELOPER);

            // Generate the invoice
            $invoice_generator = new invoice_generator($payment, $user);
            $invoice_number = invoice_number_manager::get_next_invoice_number();
            debugging("Invoice PDF: Generating PDF with invoice number: $invoice_number", DEBUG_DEVELOPER);
            
            $pdf_content = $invoice_generator->generate_pdf($invoice_number);
            if (!$pdf_content) {
                throw new moodle_exception('pdffailed', 'local_invoicepdf', '', $paymentid);
            }

            // Store the invoice
            debugging("Invoice PDF: Attempting to store invoice in database", DEBUG_DEVELOPER);
            $invoice_id = invoice_manager::store_invoice(
                $user->id,
                $invoice_number,
                $payment->get_amount(),
                $payment->get_currency(),
                $pdf_content
            );

            if (!$invoice_id) {
                throw new moodle_exception('invoicestorefailed', 'local_invoicepdf', '', $paymentid);
            }

            debugging("Invoice PDF: Successfully stored invoice with ID: $invoice_id", DEBUG_DEVELOPER);

            // Send the invoice email
            debugging("Invoice PDF: Attempting to send invoice email", DEBUG_DEVELOPER);
            $result = $invoice_generator->send_invoice_email($pdf_content, $invoice_number);
            if (!$result) {
                throw new moodle_exception('emailsendfailed', 'local_invoicepdf', '', $paymentid);
            }

            debugging("Invoice PDF: Successfully sent invoice email for payment ID: $paymentid", DEBUG_DEVELOPER);
            debugging("Invoice PDF: Successfully processed invoice for payment ID: $paymentid", DEBUG_DEVELOPER);

        } catch (moodle_exception $e) {
            debugging("Invoice PDF Error: " . $e->getMessage(), DEBUG_DEVELOPER);
            // Log the error to Moodle's error log
            error_log('Invoice PDF plugin error: ' . $e->getMessage());
        }
    }
}