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
        // Update the default settings for new features
        set_config('enabled_gateways', '', 'local_invoicepdf'); // Reset enabled gateways
        set_config('invoices_per_page', '10', 'local_invoicepdf'); // Set default pagination

        // Update the savepoint
        upgrade_plugin_savepoint(true, 2024082104, 'local', 'invoicepdf');
    }

    return true;
}