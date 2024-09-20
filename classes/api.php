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
 * API class for the Invoice PDF local plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

use core_user;
use moodle_exception;
use stdClass;

/**
 * API class for Invoice PDF operations.
 */
class api {
    /**
     * Generate an invoice
     *
     * @param int $userid User ID
     * @param float $amount Amount to be invoiced
     * @param string $currency Currency code (e.g., 'USD', 'EUR')
     * @param string $description Description of the invoice item
     * @param array $additional_data Additional data for the invoice (optional)
     * @return int|false The ID of the generated invoice, or false if generation failed
     * @throws moodle_exception
     */
    public static function generate_invoice(int $userid, float $amount, string $currency, string $description, array $additional_data = []): int|false {
        global $DB;

        // Validate input
        $user = core_user::get_user($userid);
        if (!$user) {
            throw new moodle_exception('invaliduser');
        }

        if ($amount <= 0) {
            throw new moodle_exception('invalidamount', 'local_invoicepdf');
        }

        // Generate invoice number
        $invoice_number = invoice_number_manager::get_next_invoice_number();

        // Create transaction object (simulating payment gateway transaction)
        $transaction = new stdClass();
        $transaction->userid = $userid;
        $transaction->amount = $amount;
        $transaction->currency = $currency;

        // Generate PDF
        $invoice_generator = new invoice_generator($transaction, $user);
        try {
            $pdf_content = $invoice_generator->generate_pdf($invoice_number, $description, $additional_data);
        } catch (moodle_exception $e) {
            debugging('Error generating PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

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
            if (!$invoice_generator->send_invoice_email($pdf_content, $invoice_number)) {
                debugging('Failed to send invoice email for invoice ID: ' . $invoice_id, DEBUG_DEVELOPER);
            }
            return $invoice_id;
        }

        debugging('Failed to store invoice for user ID: ' . $userid, DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Get invoice details
     *
     * @param int $invoice_id Invoice ID
     * @return stdClass|false Invoice object or false if not found
     */
    public static function get_invoice(int $invoice_id): stdClass|false {
        return invoice_manager::get_invoice($invoice_id);
    }

    /**
     * Get user's invoices
     *
     * @param int $userid User ID
     * @param int $page Page number (optional, default 0)
     * @param int $perpage Number of invoices per page (optional, default 10)
     * @return array Array of invoice objects
     */
    public static function get_user_invoices(int $userid, int $page = 0, int $perpage = 10): array {
        return invoice_manager::get_user_invoices($userid, $page, $perpage);
    }

    /**
     * Resend an invoice
     *
     * @param int $invoice_id Invoice ID
     * @return bool True if the invoice was resent successfully, false otherwise
     */
    public static function resend_invoice(int $invoice_id): bool {
        return invoice_manager::resend_invoice($invoice_id);
    }
}