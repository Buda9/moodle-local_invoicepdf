<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

use core\message\message;
use core_moodle\exception as moodle_exception;

/**
 * Invoice number manager class.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invoice_number_manager {
    /**
     * Get the next invoice number with proper error handling and validation.
     *
     * @return string The next invoice number with prefix
     * @throws moodle_exception If required configuration is missing or invalid
     */
    public static function get_next_invoice_number(): string {
        global $DB;

        // Get plugin configuration
        $config = get_config('local_invoicepdf');

        // Validate required settings
        if (empty($config->invoice_prefix)) {
            throw new moodle_exception('error:missing_prefix', 'local_invoicepdf');
        }

        if (!isset($config->next_invoice_number) || !is_numeric($config->next_invoice_number)) {
            // Initialize next_invoice_number if not set or invalid
            set_config('next_invoice_number', '1', 'local_invoicepdf');
            $config->next_invoice_number = 1;
        }

        // Start transaction to ensure thread safety
        $transaction = $DB->start_delegated_transaction();

        try {
            $next_number = (int)$config->next_invoice_number;

            // Validate number is positive
            if ($next_number <= 0) {
                $next_number = 1;
            }

            // Update the next invoice number
            set_config('next_invoice_number', $next_number + 1, 'local_invoicepdf');

            // Commit transaction
            $transaction->allow_commit();

            // Format number with leading zeros (6 digits)
            $formatted_number = sprintf('%06d', $next_number);

            // Return the full invoice number (prefix + number)
            return $config->invoice_prefix . $formatted_number;

        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw new moodle_exception('error:invoice_number_generation', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Reset the invoice number sequence to a specific number.
     * Useful for administrative purposes or error recovery.
     *
     * @param int $number The number to reset to
     * @return bool True if reset was successful
     * @throws moodle_exception If the number is invalid
     */
    public static function reset_invoice_number(int $number): bool {
        if ($number <= 0) {
            throw new moodle_exception('error:invalid_number', 'local_invoicepdf');
        }

        try {
            set_config('next_invoice_number', $number, 'local_invoicepdf');
            return true;
        } catch (\Exception $e) {
            throw new moodle_exception('error:reset_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Get the current next invoice number without incrementing it.
     *
     * @return string The current next invoice number with prefix
     * @throws moodle_exception If required configuration is missing
     */
    public static function get_current_number(): string {
        $config = get_config('local_invoicepdf');

        if (empty($config->invoice_prefix)) {
            throw new moodle_exception('error:missing_prefix', 'local_invoicepdf');
        }

        if (!isset($config->next_invoice_number) || !is_numeric($config->next_invoice_number)) {
            return $config->invoice_prefix . '000001';
        }

        return $config->invoice_prefix . sprintf('%06d', (int)$config->next_invoice_number);
    }
}