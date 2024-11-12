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

global $CFG;
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

/**
 * Invoice generator class.
 */
class invoice_generator {
    /** @var \stdClass The transaction object. */
    private \stdClass $transaction;

    /** @var \stdClass The user object. */
    private \stdClass $user;

    /** @var \stdClass The plugin configuration. */
    private \stdClass $config;

    /** @var string Temporary logo file path */
    private string $temp_logo_path = '';

    // TCPDF constants
    private const PAGE_ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAGE_FORMAT = 'A4';
    private const FONT_NAME_MAIN = 'helvetica';
    private const FONT_SIZE_MAIN = 10;
    private const FONT_NAME_DATA = 'helvetica';
    private const FONT_SIZE_DATA = 8;
    private const MARGIN_LEFT = 15;
    private const MARGIN_TOP = 15;
    private const MARGIN_RIGHT = 15;
    private const MARGIN_HEADER = 5;
    private const MARGIN_FOOTER = 10;
    private const MARGIN_BOTTOM = 25;
    private const HEADER_LOGO_WIDTH = 30;
    private const IMAGE_SCALE_RATIO = 1.25;

    /**
     * Constructor.
     *
     * @param \stdClass $transaction The transaction object.
     * @param \stdClass $user The user object.
     * @throws \moodle_exception If required configuration is missing.
     */
    public function __construct(\stdClass $transaction, \stdClass $user) {
        global $CFG;
        
        $this->transaction = $transaction;
        $this->user = $user;
        $this->config = get_config('local_invoicepdf');

        // Validate required settings
        $required_settings = [
            'company_name' => 'error:missing_company_name',
            'company_address' => 'error:missing_company_address',
            'invoice_template' => 'error:missing_invoice_template'
        ];

        foreach ($required_settings as $setting => $error) {
            if (empty($this->config->$setting)) {
                throw new \moodle_exception($error, 'local_invoicepdf');
            }
        }

        // Set defaults if not configured
        if (empty($this->config->font_family)) {
            $this->config->font_family = self::FONT_NAME_MAIN;
        }
        if (empty($this->config->font_size)) {
            $this->config->font_size = (string)self::FONT_SIZE_MAIN;
        }
        if (empty($this->config->header_color)) {
            $this->config->header_color = '#000000';
        }
        if (empty($this->config->date_format)) {
            $this->config->date_format = 'Y-m-d';
        }
    }

    /**
     * Generate a PDF invoice.
     *
     * @param string $invoice_number The invoice number.
     * @return string The PDF content.
     * @throws \moodle_exception If there's an error generating the PDF.
     */
    public function generate_pdf(string $invoice_number): string {
        global $CFG;

        if (empty($invoice_number)) {
            throw new \moodle_exception('error:missing_invoice_number', 'local_invoicepdf');
        }

        try {
            // Create PDF instance
            $pdf = new \TCPDF(
                self::PAGE_ORIENTATION,
                self::UNIT,
                self::PAGE_FORMAT,
                true,
                'UTF-8',
                false
            );

            // Set document information
            $pdf->SetCreator('Moodle Invoice PDF Generator');
            $pdf->SetAuthor($this->config->company_name);
            $pdf->SetTitle(\get_string('invoice', 'local_invoicepdf') . ' #' . $invoice_number);
            $pdf->SetSubject(\get_string('invoice_for_payment', 'local_invoicepdf'));
            $pdf->SetKeywords(\get_string('invoice_keywords', 'local_invoicepdf'));

            // Set header data
            $logo_path = $this->prepare_logo();
            if ($logo_path) {
                $pdf->SetHeaderData($logo_path, self::HEADER_LOGO_WIDTH, $this->config->company_name, $this->config->company_address);
            } else {
                $pdf->SetHeaderData('', 0, $this->config->company_name, $this->config->company_address);
            }

            // Set header and footer fonts
            $pdf->setHeaderFont([self::FONT_NAME_MAIN, '', self::FONT_SIZE_MAIN]);
            $pdf->setFooterFont([self::FONT_NAME_DATA, '', self::FONT_SIZE_DATA]);

            // Set margins
            $pdf->SetMargins(self::MARGIN_LEFT, self::MARGIN_TOP, self::MARGIN_RIGHT);
            $pdf->SetHeaderMargin(self::MARGIN_HEADER);
            $pdf->SetFooterMargin(self::MARGIN_FOOTER);

            // Set auto page breaks
            $pdf->SetAutoPageBreak(true, self::MARGIN_BOTTOM);

            // Set image scale factor
            $pdf->setImageScale(self::IMAGE_SCALE_RATIO);

            // Set font
            $pdf->SetFont($this->config->font_family, '', (int)$this->config->font_size);

            // Add a page
            $pdf->AddPage();

            // Get HTML content
            $html = $this->get_invoice_html($invoice_number);
            if (empty($html)) {
                throw new \moodle_exception('error:template_rendering_failed', 'local_invoicepdf');
            }

            // Write HTML
            $pdf->writeHTML($html, true, false, true, false, '');

            // Get PDF content
            $content = $pdf->Output('', 'S');
            if (empty($content)) {
                throw new \moodle_exception('error:pdf_generation_failed', 'local_invoicepdf');
            }

            return $content;

        } catch (\Exception $e) {
            \debugging('Error generating PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:invoice_generation_failed', 'local_invoicepdf', '', $e->getMessage());
        } finally {
            // Always cleanup temporary files
            $this->cleanup_logo();
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

        try {
            // Verify template exists
            $template_exists = $PAGE->get_renderer('core')->get_template_exists('local_invoicepdf/invoice');
            if (!$template_exists) {
                throw new \moodle_exception('error:template_not_found', 'local_invoicepdf');
            }

            // Prepare template context
            $context = [
                'company_name' => $this->config->company_name,
                'company_address' => $this->config->company_address,
                'invoice_number' => $invoice_number,
                'invoice_date' => \userdate(time(), $this->config->date_format),
                'customer_name' => \fullname($this->user),
                'item_description' => \get_string('course_payment', 'local_invoicepdf'),
                'item_amount' => $this->transaction->amount . ' ' . $this->transaction->currency,
                'total_amount' => $this->transaction->amount . ' ' . $this->transaction->currency,
                'show_payment_method' => !empty($this->config->show_payment_method),
                'payment_method' => $this->get_payment_method(),
                'invoice_footer' => \get_string('invoice_footer', 'local_invoicepdf'),
                // Style variables
                'font_family' => $this->config->font_family,
                'font_size' => $this->config->font_size,
                'header_color' => $this->config->header_color
            ];

            // Render template
            return $PAGE->get_renderer('core')->render_from_template('local_invoicepdf/invoice', $context);
        } catch (\Exception $e) {
            \debugging('Error rendering template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error:template_rendering_failed', 'local_invoicepdf', '', $e->getMessage());
        }
    }

    /**
     * Get the payment method display name.
     *
     * @return string The payment method display name.
     */
    private function get_payment_method(): string {
        try {
            $gateways = \core_payment\helper::get_payment_gateways();
            return $gateways[$this->transaction->gateway]->get_display_name() ?? 
                   \get_string('unknown_payment_method', 'local_invoicepdf');
        } catch (\Exception $e) {
            \debugging('Error getting payment method: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return \get_string('unknown_payment_method', 'local_invoicepdf');
        }
    }

    /**
     * Prepare logo for PDF generation.
     *
     * @return string|null The logo file path or null if no logo.
     */
    private function prepare_logo(): ?string {
        global $CFG;

        try {
            $fs = \get_file_storage();
            $system_context = \context_system::instance();
            $files = $fs->get_area_files($system_context->id, 'local_invoicepdf', 'logo', 0, 'sortorder', false);

            if (!$files) {
                return null;
            }

            $file = reset($files);
            
            // Create temporary file
            $temp_path = $CFG->tempdir . DIRECTORY_SEPARATOR . 'invoicepdf_logo_' . uniqid() . '.' . $file->get_filename();
            if (!$file->copy_content_to($temp_path)) {
                \debugging('Failed to copy logo to temporary file', DEBUG_DEVELOPER);
                return null;
            }
            
            $this->temp_logo_path = $temp_path;
            return $temp_path;
        } catch (\Exception $e) {
            \debugging('Error preparing logo: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Cleanup temporary logo file.
     */
    private function cleanup_logo(): void {
        if (!empty($this->temp_logo_path) && file_exists($this->temp_logo_path)) {
            try {
                unlink($this->temp_logo_path);
            } catch (\Exception $e) {
                \debugging('Error cleaning up logo: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            $this->temp_logo_path = '';
        }
    }

    /**
     * Send an invoice email.
     *
     * @param string $pdf_content The PDF content.
     * @param string $invoice_number The invoice number.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function send_invoice_email(string $pdf_content, string $invoice_number): bool {
        if (empty($pdf_content) || empty($invoice_number)) {
            \debugging('Missing required parameters for sending email', DEBUG_DEVELOPER);
            return false;
        }

        try {
            $subject = \get_string('invoice_email_subject', 'local_invoicepdf');
            $messagetext = \get_string('invoice_email_body', 'local_invoicepdf');
            $filename = "invoice_{$invoice_number}.pdf";

            $from = \core_user::get_support_user();
            if (!$from) {
                throw new \moodle_exception('error:no_support_user', 'local_invoicepdf');
            }

            $message = new \core\message\message();
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

            return \message_send($message);
        } catch (\Exception $e) {
            \debugging('Error sending invoice email: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Destructor to ensure cleanup.
     */
    public function __destruct() {
        $this->cleanup_logo();
    }
}