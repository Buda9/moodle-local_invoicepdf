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
 * Invoices generated indicator.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf\analytics\indicator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/lib/analyticlib.php');

/**
 * Invoices generated indicator class.
 */
class invoices_generated extends \core_analytics\local\indicator\binary {

    /**
     * Returns the name of the indicator.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:invoicesgenerated', 'local_invoicepdf');
    }

    /**
     * Returns the help text for the indicator.
     *
     * @return \lang_string
     */
    public static function get_help(): \lang_string {
        return new \lang_string('indicator:invoicesgenerated_help', 'local_invoicepdf');
    }

    /**
     * Calculate the sample for this indicator.
     *
     * @param \core_analytics\analysable $analysable The analysable object.
     * @param int $starttime The start time of the analysis.
     * @param int $endtime The end time of the analysis.
     * @return float 1 if the user has generated invoices, 0 otherwise.
     */
    protected function calculate_sample(\core_analytics\analysable $analysable, $starttime = false, $endtime = false): float {
        global $DB;

        try {
            // Validate that we're analyzing a user
            if (!$analysable instanceof \core_user\analytics\analysable\user) {
                \debugging('Invalid analysable type for invoices generated indicator', DEBUG_DEVELOPER);
                return 0;
            }

            $params = ['sampleid' => $analysable->get_id()];
            if ($starttime) {
                $params['starttime'] = $starttime;
            }
            if ($endtime) {
                $params['endtime'] = $endtime;
            }

            $sql = "SELECT COUNT(*) FROM {local_invoicepdf_invoices}
                    WHERE userid = :sampleid";
            if ($starttime) {
                $sql .= " AND timecreated >= :starttime";
            }
            if ($endtime) {
                $sql .= " AND timecreated <= :endtime";
            }

            // Include archived invoices in the count
            $sql_archived = "SELECT COUNT(*) FROM {local_invoicepdf_archived_invoices}
                           WHERE userid = :sampleid";
            if ($starttime) {
                $sql_archived .= " AND timecreated >= :starttime";
            }
            if ($endtime) {
                $sql_archived .= " AND timecreated <= :endtime";
            }

            $count = $DB->count_records_sql($sql, $params);
            $count_archived = $DB->count_records_sql($sql_archived, $params);

            return ($count + $count_archived) > 0 ? 1 : 0;

        } catch (\dml_exception $e) {
            \debugging('Error calculating invoices generated indicator: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Returns whether this indicator is enabled or not.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return \get_config('local_invoicepdf', 'enable_analytics') ?? true;
    }

    /**
     * Returns whether this indicator can be used with the provided analysable.
     *
     * @param \core_analytics\analysable $analysable The analysable object.
     * @return bool
     */
    public function is_valid_analysable(\core_analytics\analysable $analysable): bool {
        return $analysable instanceof \core_user\analytics\analysable\user;
    }
}