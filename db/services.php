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
 * External services definition for Invoice PDF plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_invoicepdf_get_invoice_data' => [
        'classname'   => 'local_invoicepdf\external\get_invoice_data',
        'methodname'  => 'execute',
        'description' => 'Get invoice data for chart visualization',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> ['local/invoicepdf:viewallinvoices']
    ]
];

$services = [
    'Invoice PDF Services' => [
        'functions' => ['local_invoicepdf_get_invoice_data'],
        'restrictedusers' => 0,
        'enabled' => 1
    ]
];