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
 * Invoice generator class for the Invoice PDF local plugin.
 *
 * @package    local_invoicepdf
 * @copyright  2024 Davor Budimir <davor@vokabula.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tcpdf/tcpdf.php');

use core\message\message;
use core_payment\helper;
use core_user;
use moodle_exception;
use stdClass;
use context_system;
use TCPDF;

/**
 * Invoice generator class.
 */
class invoice_generator {
    /** @var stdClass The transaction object. */
    private stdClass $transaction;

    /** @var stdClass The user object. */
    private stdClass $user;

    /** @var stdClass The plugin configuration. */
    private stdClass $config;

    /**
     * Constructor.
     *
     * @param stdClass $transaction The transaction object.
     * @param stdClass $user The user object.
     */
    public function __construct(stdClass $transaction, stdClass $user) {
        $this->transaction = $transaction;
        $this->user = $user;
        $this->config = get_config('local_invoicepdf');
    }

    /**
     * Generate a PDF invoice.
     *
     * @param string $invoice_number The invoice number.
     * @return string The PDF content.
     * @throws moodle_exception If there's an error generating the PDF.
     */
    public function generate_pdf(string $invoice_number): string {
        global $CFG;

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($this->config->company_name);
        $pdf->SetTitle(get_string('invoice', 'local_invoicepdf'));
        $pdf->SetSubject(get_string('invoice_for_payment', 'local_invoicepdf'));
        $pdf->SetKeywords(get_string('invoice_keywords', 'local_invoicepdf'));

        $logo_path = $this->get_logo_path();
        $pdf->SetHeaderData($logo_path, PDF_HEADER_LOGO_WIDTH, $this->config->company_name, $this->config->company_address);

        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $pdf->SetFont('helvetica', '', 10);

        $pdf->AddPage();

        $html = $this->get_invoice_html($invoice_number);

        $pdf->writeHTML($html, true, false, true, false, '');

        try {
            return $pdf->Output('', 'S');
        } catch (\Exception $e) {
            debugging('Error generating PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new moodle_exception('invoicegenerationfailed', 'local_invoicepdf');
        }
    }

    /**
     * Get the HTML content for the invoice.
     *
     * @param string $invoice_number The invoice number.
     * @return string The HTML content.
     */
    private function get_invoice_html(string $invoice_number): string {
        global $PAGE;

        $template = $this->config->invoice_template;

        $context = [
            'company_name' => $this->config->company_name,
            'company_address' => $this->config->company_address,
            'invoice_number' => $invoice_number,
            'invoice_date' => date($this->config->date_format),
            'customer_name' => fullname($this->user),
            'item_description' => get_string('course_payment', 'local_invoicepdf'),
            'item_amount' => $this->transaction->amount . ' ' . $this->transaction->currency,
            'total_amount' => $this->transaction->amount . ' ' . $this->transaction->currency,
            'payment_method' => $this->get_payment_method(),
            'invoice_footer' => get_string('invoice_footer', 'local_invoicepdf'),
        ];

        return $PAGE->get_renderer('core')->render_from_template('local_invoicepdf/invoice', $context);
    }

    /**
     * Get the payment method display name.
     *
     * @return string The payment method display name.
     */
    private function get_payment_method(): string {
        $gateways = helper::get_payment_gateways();
        return $gateways[$this->transaction->gateway]->get_display_name() ?? get_string('unknown_payment_method', 'local_invoicepdf');
    }

    /**
     * Get the logo file path.
     *
     * @return string The logo file path.
     */
    private function get_logo_path(): string {
        $fs = get_file_storage();
        $system_context = context_system::instance();
        $files = $fs->get_area_files($system_context->id, 'local_invoicepdf', 'logo', 0, 'sortorder', false);

        if ($files) {
            $file = reset($files);
            return $file->get_filepath() . $file->get_filename();
        }

        return '';
    }

    /**
     * Send an invoice email.
     *
     * @param string $pdf_content The PDF content.
     * @param string $invoice_number The invoice number.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function send_invoice_email(string $pdf_content, string $invoice_number): bool {
        global $CFG;

        $subject = get_string('invoice_email_subject', 'local_invoicepdf');
        $messagetext = get_string('invoice_email_body', 'local_invoicepdf');
        $filename = "invoice_{$invoice_number}.pdf";

        $from = core_user::get_support_user();

        $message = new message();
        $message->component = 'local_invoicepdf';
        $message->name = 'invoice';
        $message->userfrom = $from;
        $message->userto = $this->user;
        $message->subject = $subject;
        $message->fullmessage = $messagetext;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $subject;
        $message->notification = 0;
        $message->contexturl = null;
        $message->contexturlname = null;
        $message->attachname = $filename;
        $message->attachment = $pdf_content;

        try {
            return message_send($message);
        } catch (\Exception $e) {
            debugging('Error sending invoice email: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}