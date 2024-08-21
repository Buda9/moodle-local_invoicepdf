<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class invoice_number_manager {
    public static function get_next_invoice_number() {
        global $DB;

        // Start transaction to ensure thread safety
        $transaction = $DB->start_delegated_transaction();

        try {
            $config = get_config('local_invoicepdf');
            $next_number = $config->next_invoice_number;

            // Update the next invoice number
            set_config('next_invoice_number', $next_number + 1, 'local_invoicepdf');

            // Commit transaction
            $transaction->allow_commit();

            // Return the full invoice number (prefix + number)
            return $config->invoice_prefix . sprintf('%06d', $next_number);
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
}