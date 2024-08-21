<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class invoice_archiver {
    /**
     * Archive old invoices
     *
     * @param int $months Number of months after which invoices should be archived
     * @return int Number of archived invoices
     */
    public static function archive_old_invoices($months = 24) {
        global $DB;

        $archive_before = time() - ($months * 30 * 24 * 60 * 60); // Convert months to seconds

        // Select invoices older than the specified time
        $old_invoices = $DB->get_records_select('local_invoicepdf_invoices', 'timecreated < :archivebefore', ['archivebefore' => $archive_before]);

        $archived_count = 0;

        foreach ($old_invoices as $invoice) {
            // Move the invoice to the archive table
            $DB->insert_record('local_invoicepdf_archived_invoices', $invoice);

            // Delete the invoice from the main table
            $DB->delete_records('local_invoicepdf_invoices', ['id' => $invoice->id]);

            $archived_count++;
        }

        return $archived_count;
    }

    /**
     * Retrieve an archived invoice
     *
     * @param int $invoice_id Invoice ID
     * @return object|bool Invoice object or false if not found
     */
    public static function get_archived_invoice($invoice_id) {
        global $DB;
        return $DB->get_record('local_invoicepdf_archived_invoices', ['id' => $invoice_id]);
    }
}