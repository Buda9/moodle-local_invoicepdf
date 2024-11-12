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
 * Invoice archiver class for the Invoice PDF local plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/moodlelib.php');

/**
 * Invoice archiver class.
 */
class invoice_archiver {
    /** @var int Default number of months after which invoices should be archived. */
    private const DEFAULT_ARCHIVE_MONTHS = 24;

    /** @var int Number of records to process in each batch. */
    private const BATCH_SIZE = 1000;

    /**
     * Archive old invoices.
     *
     * @param int $months Number of months after which invoices should be archived
     * @return int Number of archived invoices
     * @throws \moodle_exception If archiving fails
     */
    public static function archive_old_invoices(int $months = self::DEFAULT_ARCHIVE_MONTHS): int {
        global $DB;

        // Validate input
        if ($months <= 0) {
            \debugging('Invalid number of months: ' . $months, DEBUG_DEVELOPER);
            throw new \moodle_exception('error:invalid_archive_months', 'local_invoicepdf');
        }

        $archive_before = time() - ($months * 30 * 24 * 60 * 60); // Convert months to seconds
        $archived_count = 0;
        $batch_count = 0;

        try {
            do {
                // Start transaction for this batch
                $transaction = $DB->start_delegated_transaction();

                try {
                    // Select a batch of old invoices
                    $old_invoices = $DB->get_records_select(
                        'local_invoicepdf_invoices',
                        'timecreated < :archivebefore',
                        ['archivebefore' => $archive_before],
                        'timecreated ASC',
                        '*',
                        0,
                        self::BATCH_SIZE
                    );

                    if (empty($old_invoices)) {
                        $transaction->allow_commit();
                        break;
                    }

                    foreach ($old_invoices as $invoice) {
                        // Move the invoice to the archive table
                        $archived = $DB->insert_record('local_invoicepdf_archived_invoices', $invoice);
                        if (!$archived) {
                            throw new \moodle_exception('error:archive_failed', 'local_invoicepdf');
                        }

                        // Delete the invoice from the main table
                        $deleted = $DB->delete_records('local_invoicepdf_invoices', ['id' => $invoice->id]);
                        if (!$deleted) {
                            throw new \moodle_exception('error:delete_failed', 'local_invoicepdf');
                        }

                        $archived_count++;
                    }

                    // Commit this batch
                    $transaction->allow_commit();
                    $batch_count++;

                    \debugging("Archived batch $batch_count with " . count($old_invoices) . " invoices", DEBUG_DEVELOPER);

                } catch (\Exception $e) {
                    $transaction->rollback($e);
                    throw $e;
                }

                // Continue until no more records are found
            } while (true);

            return $archived_count;

        } catch (\Exception $e) {
            \debugging('Error archiving invoices: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:archiving_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Retrieve an archived invoice.
     *
     * @param int $invoice_id Invoice ID
     * @return \stdClass|false Invoice object or false if not found
     * @throws \moodle_exception If retrieval fails
     */
    public static function get_archived_invoice(int $invoice_id): \stdClass|false {
        global $DB;

        if ($invoice_id <= 0) {
            \debugging('Invalid invoice ID: ' . $invoice_id, DEBUG_DEVELOPER);
            throw new \moodle_exception('error:invalid_invoice_id', 'local_invoicepdf');
        }

        try {
            return $DB->get_record('local_invoicepdf_archived_invoices', ['id' => $invoice_id]);
        } catch (\Exception $e) {
            \debugging('Error retrieving archived invoice: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:archive_retrieval_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Get the count of archived invoices.
     *
     * @return int Number of archived invoices
     * @throws \moodle_exception If count fails
     */
    public static function get_archived_count(): int {
        global $DB;

        try {
            return $DB->count_records('local_invoicepdf_archived_invoices');
        } catch (\Exception $e) {
            \debugging('Error counting archived invoices: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:archive_count_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Get archived invoices with pagination.
     *
     * @param int $page Page number (0-based)
     * @param int $perpage Number of records per page
     * @return array Array of archived invoices
     * @throws \moodle_exception If retrieval fails
     */
    public static function get_archived_invoices(int $page = 0, int $perpage = 50): array {
        global $DB;

        if ($page < 0 || $perpage <= 0) {
            \debugging('Invalid pagination parameters', DEBUG_DEVELOPER);
            throw new \moodle_exception('error:invalid_pagination', 'local_invoicepdf');
        }

        try {
            return $DB->get_records(
                'local_invoicepdf_archived_invoices',
                null,
                'timecreated DESC',
                '*',
                $page * $perpage,
                $perpage
            );
        } catch (\Exception $e) {
            \debugging('Error retrieving archived invoices: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:archive_list_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Delete old archived invoices.
     *
     * @param int $months Number of months after which archived invoices should be deleted
     * @return int Number of deleted invoices
     * @throws \moodle_exception If deletion fails
     */
    public static function cleanup_old_archives(int $months = 84): int {
        global $DB;

        if ($months <= 0) {
            \debugging('Invalid number of months: ' . $months, DEBUG_DEVELOPER);
            throw new \moodle_exception('error:invalid_cleanup_months', 'local_invoicepdf');
        }

        $delete_before = time() - ($months * 30 * 24 * 60 * 60);
        $deleted_count = 0;

        try {
            // Start transaction
            $transaction = $DB->start_delegated_transaction();

            try {
                // Delete old archived invoices
                $deleted_count = $DB->delete_records_select(
                    'local_invoicepdf_archived_invoices',
                    'timecreated < :deletebefore',
                    ['deletebefore' => $delete_before]
                );

                $transaction->allow_commit();
                return $deleted_count;

            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }

        } catch (\Exception $e) {
            \debugging('Error cleaning up archived invoices: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:cleanup_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }
}