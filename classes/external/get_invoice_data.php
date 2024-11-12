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
 * External function to get invoice data for chart visualization.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function to get invoice data.
 */
class get_invoice_data extends \external_api {

    /**
     * Returns description of method parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Returns description of method result value.
     *
     * @return \external_description
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'labels' => new \external_multiple_structure(
                new \external_value(PARAM_TEXT, 'Month label')
            ),
            'datasets' => new \external_multiple_structure(
                new \external_single_structure([
                    'label' => new \external_value(PARAM_TEXT, 'Dataset label'),
                    'data' => new \external_multiple_structure(
                        new \external_value(PARAM_FLOAT, 'Value')
                    ),
                    'backgroundColor' => new \external_value(PARAM_TEXT, 'Background color'),
                    'borderColor' => new \external_value(PARAM_TEXT, 'Border color'),
                    'borderWidth' => new \external_value(PARAM_INT, 'Border width')
                ])
            )
        ]);
    }

    /**
     * Get invoice data for chart visualization.
     *
     * @return array Chart data
     */
    public static function execute() {
        global $DB;

        // Check capability
        $context = \context_system::instance();
        require_capability('local/invoicepdf:viewallinvoices', $context);

        // Get last 12 months of data
        $months = [];
        $amounts = [];
        $counts = [];

        for ($i = 11; $i >= 0; $i--) {
            $start = strtotime("-$i months", strtotime('first day of this month'));
            $end = strtotime("-$i months", strtotime('last day of this month'));

            // Format month label
            $months[] = date('M Y', $start);

            // Get total amount for month
            $sql = "SELECT COALESCE(SUM(amount), 0) as total
                   FROM {local_invoicepdf_invoices}
                   WHERE timecreated BETWEEN :startdate AND :enddate";
            $params = ['startdate' => $start, 'enddate' => $end];
            $amount = $DB->get_field_sql($sql, $params);
            $amounts[] = (float)$amount;

            // Get invoice count for month
            $sql = "SELECT COUNT(*) as count
                   FROM {local_invoicepdf_invoices}
                   WHERE timecreated BETWEEN :startdate AND :enddate";
            $count = $DB->get_field_sql($sql, $params);
            $counts[] = (int)$count;
        }

        // Format data for chart
        $data = [
            'labels' => $months,
            'datasets' => [
                [
                    'label' => get_string('totalamount', 'local_invoicepdf'),
                    'data' => $amounts,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1
                ],
                [
                    'label' => get_string('totalinvoices', 'local_invoicepdf'),
                    'data' => $counts,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];

        return $data;
    }
}