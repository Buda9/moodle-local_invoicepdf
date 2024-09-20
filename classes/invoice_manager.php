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
 * Invoice manager class for the Invoice PDF local plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

use core\message\message;
use core_user;
use moodle_exception;
use stdClass;

/**
 * Invoice manager class.
 */
class invoice_manager {
    /**
     * Get user invoices.
     *
     * @param int $userid The user ID.
     * @param int $page The page number.
     * @param int $perpage The number of records per page.
     * @return array An array of invoice records.
     */
    public static function get_user_invoices(int $userid, int $page = 0, int $perpage = 10): array {
        global $DB;
        return $DB->get_records('local_invoicepdf_invoices',
                                ['userid' => $userid],
                                'timecreated DESC',
                                '*',
                                $page * $perpage,
                                $perpage);
    }

    /**
     * Get all invoices.
     *
     * @param int $page The page number.
     * @param int $perpage The number of records per page.
     * @return array An array of all invoice records.
     */
    public static function get_all_invoices(int $page = 0, int $perpage = 10): array {
        global $DB;
        return $DB->get_records('local_invoicepdf_invoices',
                                null,
                                'timecreated DESC',
                                '*',
                                $page * $perpage,
                                $perpage);
    }

    /**
     * Get the count of user invoices.
     *
     * @param int $userid The user ID.
     * @return int The number of invoices for the user.
     */
    public static function get_user_invoices_count(int $userid): int {
        global $DB;
        return $DB->count_records('local_invoicepdf_invoices', ['userid' => $userid]);
    }

    /**
     * Get the total count of all invoices.
     *
     * @return int The total number of invoices.
     */
    public static function get_all_invoices_count(): int {
        global $DB;
        return $DB->count_records('local_invoicepdf_invoices');
    }

    /**
     * Get a specific invoice.
     *
     * @param int $id The invoice ID.
     * @return stdClass|false The invoice record or false if not found.
     */
    public static function get_invoice(int $id): stdClass|false {
        global $DB;
        return $DB->get_record('local_invoicepdf_invoices', ['id' => $id]);
    }

    /**
     * Get the PDF content of an invoice.
     *
     * @param int $id The invoice ID.
     * @return string|false The PDF content or false if not found.
     */
    public static function get_invoice_pdf(int $id): string|false {
        $invoice = self::get_invoice($id);
        if (!$invoice) {
            return false;
        }
        return $invoice->pdf_content;
    }

    /**
     * Resend an invoice email.
     *
     * @param int $id The invoice ID.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public static function resend_invoice(int $id): bool {
        $invoice = self::get_invoice($id);
        if (!$invoice) {
            return false;
        }
        $user = core_user::get_user($invoice->userid);
        $pdf_content = self::get_invoice_pdf($id);

        $subject = get_string('invoice_email_subject', 'local_invoicepdf');
        $messagetext = get_string('invoice_email_body', 'local_invoicepdf');
        $filename = "invoice_{$invoice->invoice_number}.pdf";

        $message = new message();
        $message->component = 'local_invoicepdf';
        $message->name = 'invoice';
        $message->userfrom = core_user::get_support_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $messagetext;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $subject;
        $message->notification = 0;
        $message->contexturl = null;
        $message->contexturlname = null;
        $message->attachname = $filename;
        $message->attachment = $pdf_content;

        try {
            return message_send($message);
        } catch (\Exception $e) {
            debugging('Error resending invoice email: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Store a new invoice.
     *
     * @param int $userid The user ID.
     * @param string $invoice_number The invoice number.
     * @param float $amount The invoice amount.
     * @param string $currency The currency code.
     * @param string $pdf_content The PDF content.
     * @return int|false The new invoice ID or false if insertion failed.
     */
    public static function store_invoice(int $userid, string $invoice_number, float $amount, string $currency, string $pdf_content): int|false {
        global $DB;

        $invoice = new stdClass();
        $invoice->userid = $userid;
        $invoice->invoice_number = $invoice_number;
        $invoice->amount = $amount;
        $invoice->currency = $currency;
        $invoice->pdf_content = $pdf_content;
        $invoice->timecreated = time();

        try {
            return $DB->insert_record('local_invoicepdf_invoices', $invoice);
        } catch (\dml_exception $e) {
            debugging('Failed to store invoice: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Export invoices for a given date range.
     *
     * @param int $startdate The start date timestamp.
     * @param int $enddate The end date timestamp.
     * @param string $format The export format (currently only 'csv' is supported).
     * @return string|false The exported data as a string, or false if export failed.
     */
    public static function export_invoices(int $startdate, int $enddate, string $format = 'csv'): string|false {
        global $DB;

        $invoices = $DB->get_records_select('local_invoicepdf_invoices',
            'timecreated BETWEEN :startdate AND :enddate',
            ['startdate' => $startdate, 'enddate' => $enddate],
            'timecreated ASC'
        );

        if ($format === 'csv') {
            $csv = "Invoice Number,Date,User,Amount,Currency\n";
            foreach ($invoices as $invoice) {
                $user = core_user::get_user($invoice->userid);
                $csv .= "{$invoice->invoice_number}," . date('Y-m-d', $invoice->timecreated) . "," .
                        fullname($user) . ",{$invoice->amount},{$invoice->currency}\n";
            }
            return $csv;
        }
        return false;
    }
}