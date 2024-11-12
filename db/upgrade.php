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
 * Invoice PDF plugin upgrade script.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/upgradelib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/invoicepdf/lib.php');

use core\plugin_manager;

/**
 * Upgrade script for the Invoice PDF plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_local_invoicepdf_upgrade(int $oldversion): bool {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024082001) {
        // Update existing invoice template to use language strings
        $default_template = get_string('default_invoice_template', 'local_invoicepdf');
        set_config('invoice_template', $default_template, 'local_invoicepdf');

        // Update the savepoint
        upgrade_plugin_savepoint(true, 2024082001, 'local', 'invoicepdf');
    }

    if ($oldversion < 2024082104) {
        // Get all available payment gateways
        $pluginman = plugin_manager::instance();
        $available_gateways = $pluginman->get_plugins_of_type('paygw');
        $gateway_names = [];
        foreach ($available_gateways as $gateway) {
            $gateway_names[] = $gateway->name;
        }

        // Enable all available payment gateways if none are currently enabled
        $current_gateways = get_config('local_invoicepdf', 'enabled_gateways');
        if (empty($current_gateways) && !empty($gateway_names)) {
            set_config('enabled_gateways', implode(',', $gateway_names), 'local_invoicepdf');
        }

        // Set default pagination if not set
        if (!get_config('local_invoicepdf', 'invoices_per_page')) {
            set_config('invoices_per_page', '10', 'local_invoicepdf');
        }

        // Set other default settings if they don't exist
        $default_settings = [
            'company_name' => get_string('default_company_name', 'local_invoicepdf'),
            'company_address' => get_string('default_company_address', 'local_invoicepdf'),
            'invoice_prefix' => 'INV-',
            'next_invoice_number' => '1',
            'date_format' => 'Y-m-d',
            'show_payment_method' => '1',
            'invoice_footer' => get_string('default_invoice_footer', 'local_invoicepdf'),
            'header_color' => '#000000',
            'font_family' => 'helvetica',
            'font_size' => '12'
        ];

        foreach ($default_settings as $name => $value) {
            if (!get_config('local_invoicepdf', $name)) {
                set_config($name, $value, 'local_invoicepdf');
            }
        }

        // Update the savepoint
        upgrade_plugin_savepoint(true, 2024082104, 'local', 'invoicepdf');
    }

    return true;
}