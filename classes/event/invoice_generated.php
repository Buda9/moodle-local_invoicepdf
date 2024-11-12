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
 * Invoice generated event.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf\event;

defined('MOODLE_INTERNAL') || die();

use core\event\base;
use context_system;
use moodle_url;
use coding_exception;

/**
 * Invoice generated event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int paymentid: The ID of the payment.
 *      - string invoice_number: The invoice number.
 * }
 */
class invoice_generated extends base {

    /**
     * @var array Protected custom data for the event.
     */
    protected $data = [];

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = base::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_invoicepdf_invoices';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventinvoicegenerated', 'local_invoicepdf');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->data['userid']}' generated an invoice with number '{$this->other['invoice_number']}' " .
               "for payment id '{$this->other['paymentid']}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('/local/invoicepdf/download.php', [
            'id' => $this->data['objectid']
        ]);
    }

    /**
     * Custom validation.
     *
     * @throws coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['paymentid'])) {
            throw new coding_exception('The \'paymentid\' value must be set in other.');
        }

        if (!isset($this->other['invoice_number'])) {
            throw new coding_exception('The \'invoice_number\' value must be set in other.');
        }

        if (empty($this->data['userid'])) {
            throw new coding_exception('The \'userid\' must be set.');
        }
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_invoicepdf_invoices', 'restore' => 'local_invoicepdf_invoice'];
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the information in 'other' to it's new value in the new course.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'paymentid' => ['db' => 'payments', 'restore' => 'payment']
        ];
    }

    /**
     * Get legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array(
            $this->data['courseid'] ?? SITEID,
            'local_invoicepdf',
            'generate',
            $this->get_url()->out(),
            $this->other['invoice_number']
        );
    }

    /**
     * Create instance of event.
     *
     * @param array $data
     * @return invoice_generated
     */
    public static function create(array $data) {
        $data['context'] = $data['context'] ?? context_system::instance();
        $event = parent::create($data);
        return $event;
    }
}