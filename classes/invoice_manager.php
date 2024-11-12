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

global $CFG;
require_once($CFG->libdir . '/messagelib.php');
require_once($CFG->libdir . '/datalib.php');

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
     * @throws \dml_exception If there's a database error.
     */
    public static function get_user_invoices(int $userid, int $page = 0, int $perpage = 10): array {
        global $DB;

        // Validate parameters
        if ($userid <= 0) {
            throw new \coding_exception('Invalid user ID');
        }
        if ($page < 0 || $perpage <= 0) {
            throw new \coding_exception('Invalid pagination parameters');
        }

        return $DB->get_records('local_invoicepdf_invoices',
            ['userid' => $userid],
            'timecreated DESC',
            '*',
            $page * $perpage,
            $perpage
        );
    }

    /**
     * Get all invoices.
     *
     * @param int $page The page number.
     * @param int $perpage The number of records per page.
     * @return array An array of all invoice records.
     * @throws \dml_exception If there's a database error.
     */
    public static function get_all_invoices(int $page = 0, int $perpage = 10): array {
        global $DB;

        // Validate parameters
        if ($page < 0 || $perpage <= 0) {
            throw new \coding_exception('Invalid pagination parameters');
        }

        return $DB->get_records('local_invoicepdf_invoices',
            [],
            'timecreated DESC',
            '*',
            $page * $perpage,
            $perpage
        );
    }

    /**
     * Get the count of user invoices.
     *
     * @param int $userid The user ID.
     * @return int The number of invoices for the user.
     * @throws \dml_exception If there's a database error.
     */
    public static function get_user_invoices_count(int $userid): int {
        global $DB;

        if ($userid <= 0) {
            throw new \coding_exception('Invalid user ID');
        }

        return $DB->count_records('local_invoicepdf_invoices', ['userid' => $userid]);
    }

    /**
     * Get the total count of all invoices.
     *
     * @return int The total number of invoices.
     * @throws \dml_exception If there's a database error.
     */
    public static function get_all_invoices_count(): int {
        global $DB;
        return $DB->count_records('local_invoicepdf_invoices');
    }

    /**
     * Get a specific invoice.
     *
     * @param int $id The invoice ID.
     * @return \stdClass|false The invoice record or false if not found.
     * @throws \dml_exception If there's a database error.
     */
    public static function get_invoice(int $id): \stdClass|false {
        global $DB;

        if ($id <= 0) {
            throw new \coding_exception('Invalid invoice ID');
        }

        return $DB->get_record('local_invoicepdf_invoices', ['id' => $id]);
    }

    /**
     * Get the PDF content of an invoice.
     *
     * @param int $id The invoice ID.
     * @return string|false The PDF content or false if not found.
     * @throws \dml_exception If there's a database error.
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
        try {
            $invoice = self::get_invoice($id);
            if (!$invoice) {
                \debugging('Invoice not found: ' . $id, DEBUG_DEVELOPER);
                return false;
            }

            $user = \core_user::get_user($invoice->userid);
            if (!$user) {
                \debugging('User not found: ' . $invoice->userid, DEBUG_DEVELOPER);
                return false;
            }

            $pdf_content = self::get_invoice_pdf($id);
            if (!$pdf_content) {
                \debugging('PDF content not found for invoice: ' . $id, DEBUG_DEVELOPER);
                return false;
            }

            $subject = get_string('invoice_email_subject', 'local_invoicepdf');
            $messagetext = get_string('invoice_email_body', 'local_invoicepdf');
            $filename = "invoice_{$invoice->invoice_number}.pdf";

            $from = \core_user::get_support_user();
            if (!$from) {
                \debugging('Support user not found', DEBUG_DEVELOPER);
                return false;
            }

            $message = new \core\message\message();
            $message->component = 'local_invoicepdf';
            $message->name = 'invoice';
            $message->userfrom = $from;
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

            return message_send($message);

        } catch (\Exception $e) {
            \debugging('Error resending invoice email: ' . $e->getMessage(), DEBUG_DEVELOPER);
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

        // Validate parameters
        if ($userid <= 0) {
            \debugging('Invalid user ID: ' . $userid, DEBUG_DEVELOPER);
            return false;
        }

        if (empty($invoice_number)) {
            \debugging('Empty invoice number', DEBUG_DEVELOPER);
            return false;
        }

        if ($amount <= 0) {
            \debugging('Invalid amount: ' . $amount, DEBUG_DEVELOPER);
            return false;
        }

        if (empty($currency) || strlen($currency) !== 3) {
            \debugging('Invalid currency code: ' . $currency, DEBUG_DEVELOPER);
            return false;
        }

        if (empty($pdf_content)) {
            \debugging('Empty PDF content', DEBUG_DEVELOPER);
            return false;
        }

        // Start transaction
        $transaction = $DB->start_delegated_transaction();

        try {
            // Check if invoice number already exists
            if ($DB->record_exists('local_invoicepdf_invoices', ['invoice_number' => $invoice_number])) {
                throw new \moodle_exception('error:duplicate_invoice_number', 'local_invoicepdf');
            }

            // Create invoice record
            $invoice = new \stdClass();
            $invoice->userid = $userid;
            $invoice->invoice_number = $invoice_number;
            $invoice->amount = $amount;
            $invoice->currency = $currency;
            $invoice->pdf_content = $pdf_content;
            $invoice->timecreated = time();

            // Insert record
            $id = $DB->insert_record('local_invoicepdf_invoices', $invoice);
            if (!$id) {
                throw new \moodle_exception('error:invoice_store_failed', 'local_invoicepdf');
            }

            // Commit transaction
            $transaction->allow_commit();

            return $id;

        } catch (\Exception $e) {
            $transaction->rollback($e);
            \debugging('Failed to store invoice: ' . $e->getMessage(), DEBUG_DEVELOPER);
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

        // Validate parameters
        if ($startdate <= 0 || $enddate <= 0 || $startdate > $enddate) {
            \debugging('Invalid date range', DEBUG_DEVELOPER);
            return false;
        }

        try {
            $invoices = $DB->get_records_select('local_invoicepdf_invoices',
                'timecreated BETWEEN :startdate AND :enddate',
                ['startdate' => $startdate, 'enddate' => $enddate],
                'timecreated ASC'
            );

            if ($format === 'csv') {
                $csv = "Invoice Number,Date,User,Amount,Currency\n";
                foreach ($invoices as $invoice) {
                    $user = \core_user::get_user($invoice->userid);
                    if (!$user) {
                        \debugging('User not found: ' . $invoice->userid, DEBUG_DEVELOPER);
                        continue;
                    }
                    $csv .= sprintf('%s,%s,%s,%.2f,%s' . "\n",
                        $invoice->invoice_number,
                        userdate($invoice->timecreated, get_string('strftimedate', 'langconfig')),
                        fullname($user),
                        $invoice->amount,
                        $invoice->currency
                    );
                }
                return $csv;
            }

            \debugging('Unsupported export format: ' . $format, DEBUG_DEVELOPER);
            return false;

        } catch (\Exception $e) {
            \debugging('Error exporting invoices: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}