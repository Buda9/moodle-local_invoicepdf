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
 * Installation script for the Invoice PDF local plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/moodlelib.php');
require_once($CFG->dirroot.'/lib/setuplib.php');

/**
 * Installation procedure
 *
 * @return bool
 */
function xmldb_local_invoicepdf_install() {
    global $CFG, $DB;

    $gateway_names = [];
    
    // Get payment gateways if core_payment is available
    if (file_exists($CFG->dirroot . '/payment/classes/helper.php')) {
        require_once($CFG->dirroot . '/payment/classes/helper.php');
        try {
            $gateways = core_payment\helper::get_payment_gateways();
            if (!empty($gateways)) {
                $gateway_names = array_keys($gateways);
            }
        } catch (Exception $e) {
            mtrace('Notice: Could not fetch payment gateways: ' . $e->getMessage());
        }
    }

    // Default invoice template that matches the lang string template
    $default_template = '
<h1>{{company_name}}</h1>
<p>{{company_address}}</p>
<h2>{{#str}}invoice, local_invoicepdf{{/str}} #{{invoice_number}}</h2>
<p>{{#str}}date, local_invoicepdf{{/str}}: {{invoice_date}}</p>
<p>{{#str}}to, local_invoicepdf{{/str}}: {{customer_name}}</p>
<table>
    <tr>
        <th>{{#str}}description, local_invoicepdf{{/str}}</th>
        <th>{{#str}}amount, local_invoicepdf{{/str}}</th>
    </tr>
    <tr>
        <td>{{item_description}}</td>
        <td>{{item_amount}}</td>
    </tr>
</table>
<p>{{#str}}total, local_invoicepdf{{/str}}: {{total_amount}}</p>
{{#show_payment_method}}
<p>{{#str}}payment_method, local_invoicepdf{{/str}}: {{payment_method}}</p>
{{/show_payment_method}}
<footer>{{invoice_footer}}</footer>';

    // Initialize all required settings with default values
    $settings = [
        'enabled_gateways' => !empty($gateway_names) ? implode(',', $gateway_names) : '',
        'company_name' => '',
        'company_address' => '',
        'invoice_template' => $default_template,
        'invoice_prefix' => 'INV-',
        'next_invoice_number' => '1',
        'date_format' => 'Y-m-d',
        'show_payment_method' => '1',
        'invoice_footer' => get_string('invoice_footer', 'local_invoicepdf'),
        'header_color' => '#000000',
        'font_family' => 'helvetica',
        'font_size' => '12',
        'invoices_per_page' => '10',
        'custom_css' => ''
    ];

    // Ensure config_plugins table exists
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('config_plugins')) {
        mtrace('Error: config_plugins table does not exist');
        return false;
    }

    // Apply all settings
    foreach ($settings as $name => $value) {
        try {
            $params = ['plugin' => 'local_invoicepdf', 'name' => $name];
            
            // Check if setting already exists
            if ($DB->record_exists('config_plugins', $params)) {
                // Only update if value is different
                $current = $DB->get_field('config_plugins', 'value', $params);
                if ($current !== $value) {
                    $DB->set_field('config_plugins', 'value', $value, $params);
                }
            } else {
                // Insert new setting
                $record = new stdClass();
                $record->plugin = 'local_invoicepdf';
                $record->name = $name;
                $record->value = $value;
                $DB->insert_record('config_plugins', $record);
            }
        } catch (Exception $e) {
            mtrace('Error setting config for ' . $name . ': ' . $e->getMessage());
            // Continue with other settings even if one fails
        }
    }

    return true;
}