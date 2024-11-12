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

use core\event\base;
use core\event\payment_completed;
use core_payment\helper as payment_helper;
use core\message\message;
use context_system;
use moodle_exception;
use stdClass;

/**
 * Class observer
 *
 * @package local_invoicepdf
 */
class observer {
    /**
     * Validate plugin settings.
     *
     * @return bool True if all required settings are present
     * @throws \moodle_exception If required settings are missing
     */
    private static function validate_settings(): bool {
        $config = get_config('local_invoicepdf');
        $required_settings = [
            'company_name' => 'error:missing_company_name',
            'company_address' => 'error:missing_company_address',
            'invoice_template' => 'error:missing_invoice_template',
            'invoice_prefix' => 'error:missing_invoice_prefix',
            'enabled_gateways' => 'error:no_gateways_enabled'
        ];

        foreach ($required_settings as $setting => $error) {
            if (empty($config->$setting)) {
                throw new \moodle_exception($error, 'local_invoicepdf');
            }
        }

        return true;
    }

    /**
     * Validate PDF content.
     *
     * @param string $content The PDF content
     * @return bool True if content is valid PDF
     */
    private static function validate_pdf_content(string $content): bool {
        // Check if content starts with PDF signature
        if (substr($content, 0, 4) !== '%PDF') {
            return false;
        }

        // Check if content has minimum size
        if (strlen($content) < 100) {
            return false;
        }

        return true;
    }

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
        $transaction = null;

        try {
            // Validate plugin settings first
            self::validate_settings();

            // Fetch the payment record using Moodle's payment API
            $payment = payment_helper::get_payment_by_id($paymentid);
            if (!$payment) {
                throw new \moodle_exception('paymentnotfound', 'local_invoicepdf', '', $paymentid);
            }

            // Check if this payment gateway is enabled for invoice generation
            $enabled_gateways = get_config('local_invoicepdf', 'enabled_gateways');
            $enabled_gateways = $enabled_gateways ? explode(',', $enabled_gateways) : [];

            if (!in_array($payment->get_gateway(), $enabled_gateways)) {
                debugging("Invoice PDF: Gateway not enabled for invoice generation: " . $payment->get_gateway(), DEBUG_DEVELOPER);
                return; // Don't generate invoice
            }

            // Start transaction
            $transaction = $DB->start_delegated_transaction();

            // Fetch the user record
            $user = $DB->get_record('user', ['id' => $payment->get_userid()]);
            if (!$user) {
                throw new \moodle_exception('usernotfound', 'local_invoicepdf', '', $payment->get_userid());
            }

            debugging("Invoice PDF: Processing invoice for user: {$user->id} ({$user->username})", DEBUG_DEVELOPER);

            // Generate invoice number first to ensure uniqueness
            $invoice_number = invoice_number_manager::get_next_invoice_number();
            
            // Generate the PDF
            $invoice_generator = new invoice_generator($payment, $user);
            $pdf_content = $invoice_generator->generate_pdf($invoice_number);
            
            // Validate PDF content
            if (!$pdf_content || !self::validate_pdf_content($pdf_content)) {
                throw new \moodle_exception('pdffailed', 'local_invoicepdf', '', $paymentid);
            }

            // Store the invoice
            $invoice_id = invoice_manager::store_invoice(
                $user->id,
                $invoice_number,
                $payment->get_amount(),
                $payment->get_currency(),
                $pdf_content
            );

            if (!$invoice_id) {
                throw new \moodle_exception('invoicestorefailed', 'local_invoicepdf', '', $paymentid);
            }

            // Send the invoice email
            $result = $invoice_generator->send_invoice_email($pdf_content, $invoice_number);
            if (!$result) {
                // Log warning but don't fail the process
                debugging("Warning: Failed to send invoice email for payment ID: $paymentid", DEBUG_DEVELOPER);
            }

            // Commit transaction
            if ($transaction) {
                $transaction->allow_commit();
            }

            debugging("Invoice PDF: Successfully processed invoice for payment ID: $paymentid", DEBUG_DEVELOPER);

            // Trigger invoice generated event
            $params = [
                'context' => context_system::instance(),
                'objectid' => $invoice_id,
                'relateduserid' => $user->id,
                'other' => [
                    'paymentid' => $paymentid,
                    'invoice_number' => $invoice_number
                ]
            ];
            $event = \local_invoicepdf\event\invoice_generated::create($params);
            $event->trigger();

        } catch (\moodle_exception $e) {
            // Rollback transaction if exists
            if ($transaction) {
                $transaction->rollback($e);
            }

            // Log detailed error
            debugging("Invoice PDF Error: " . $e->getMessage(), DEBUG_DEVELOPER);
            error_log('Invoice PDF plugin error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            // Attempt to notify admin
            self::notify_admin_of_failure($paymentid, $e->getMessage());
        }
    }

    /**
     * Notify admin of invoice generation failure.
     *
     * @param int $paymentid The payment ID
     * @param string $error The error message
     * @return void
     */
    private static function notify_admin_of_failure(int $paymentid, string $error): void {
        try {
            $admin = get_admin();
            $subject = get_string('invoice_generation_failed_subject', 'local_invoicepdf');
            $message = get_string('invoice_generation_failed_body', 'local_invoicepdf', [
                'paymentid' => $paymentid,
                'error' => $error
            ]);

            email_to_user($admin, $admin, $subject, $message);
        } catch (\Exception $e) {
            // Just log if notification fails
            error_log('Failed to notify admin of invoice generation failure: ' . $e->getMessage());
        }
    }
}