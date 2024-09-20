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
 * Library functions for the Invoice PDF local plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the global navigation tree by adding Invoice PDF nodes if necessary.
 *
 * @param global_navigation $navigation An object representing the navigation tree.
 */
function local_invoicepdf_extend_navigation(global_navigation $navigation): void {
    global $USER, $PAGE;

    if (isloggedin() && !isguestuser()) {
        $node = new navigation_node([
            'text' => get_string('user_invoice_archive', 'local_invoicepdf'),
            'action' => new moodle_url('/local/invoicepdf/archive.php'),
            'type' => navigation_node::TYPE_CUSTOM,
            'icon' => new pix_icon('i/report', '')
        ]);

        if ($PAGE->context->contextlevel == CONTEXT_USER && $PAGE->context->instanceid == $USER->id) {
            $navigation->add_node($node);
        } else {
            $usernode = $navigation->find('myprofile', navigation_node::TYPE_ROOTNODE);
            $usernode?->add_node($node);
        }
    }
}

/**
 * Extends the settings navigation with the Invoice PDF settings if necessary.
 *
 * @param settings_navigation $navigation An object representing the navigation tree.
 */
function local_invoicepdf_extend_settings_navigation(settings_navigation $navigation): void {
    global $PAGE;

    if ($PAGE->context->contextlevel == CONTEXT_SYSTEM && has_capability('local/invoicepdf:manage', $PAGE->context)) {
        $settingsnode = $navigation->find('root', navigation_node::TYPE_SITE_ADMIN);
        if ($settingsnode) {
            $strfoo = get_string('pluginname', 'local_invoicepdf');
            $url = new moodle_url('/local/invoicepdf/admin/archive.php');
            $foonode = new navigation_node([
                'text' => $strfoo,
                'action' => $url,
                'type' => navigation_node::TYPE_SETTING,
                'icon' => new pix_icon('i/report', '')
            ]);
            $settingsnode->add_node($foonode);
        }
    }
}

/**
 * Archive old invoices.
 *
 * This function is called from the scheduled task.
 *
 * @return void
 */
function local_invoicepdf_archive_old_invoices(): void {
    $config = get_config('local_invoicepdf');
    $archive_months = $config->archive_months ?? 24;

    $archived_count = \local_invoicepdf\invoice_archiver::archive_old_invoices($archive_months);

    mtrace("Archived $archived_count old invoices.");
}