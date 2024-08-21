<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tcpdf/tcpdf.php');

class invoice_generator {
    private $transaction;
    private $user;
    private $config;
    private $string_manager;

    public function __construct($transaction, $user) {
        global $CFG;
        $this->transaction = $transaction;
        $this->user = $user;
        $this->config = get_config('local_invoicepdf');
        $this->string_manager = get_string_manager();

        // Load all enabled payment gateways
        $this->payment_gateways = \core_component::get_plugin_list('paygw');
    }

    public function generate_and_send_invoice() {
        global $CFG;

        // Set the language to user's preferred language
        $current_lang = $CFG->lang;
        $CFG->lang = $this->user->lang;

        $invoice_number = invoice_number_manager::get_next_invoice_number();
        $pdf_content = $this->generate_pdf($invoice_number);

        // Send email with attached PDF
        $subject = $this->string_manager->get_string('invoice_email_subject', 'local_invoicepdf', null, $this->user->lang);
        $message = $this->string_manager->get_string('invoice_email_body', 'local_invoicepdf', null, $this->user->lang);

        $filename = "invoice_{$invoice_number}.pdf";

        $email_sent = email_to_user(
            $this->user,
            \core_user::get_support_user(),
            $subject,
            $message,
            $message,
            $pdf_content,
            $filename
        );

        // Reset the language
        $CFG->lang = $current_lang;

        return $email_sent;
    }

    private function generate_pdf($invoice_number) {
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($this->config->company_name);
        $pdf->SetTitle($this->string_manager->get_string('invoice', 'local_invoicepdf', null, $this->user->lang));
        $pdf->SetSubject($this->string_manager->get_string('invoice_for_payment', 'local_invoicepdf', null, $this->user->lang));
        $pdf->SetKeywords($this->string_manager->get_string('invoice_keywords', 'local_invoicepdf', null, $this->user->lang));

        $pdf->SetHeaderData($this->get_logo_path(), PDF_HEADER_LOGO_WIDTH, $this->config->company_name, $this->config->company_address);

        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $pdf->AddPage();

        $html = $this->get_invoice_html($invoice_number);

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    private function get_invoice_html($invoice_number) {
        $template = $this->config->invoice_template;

        $placeholders = [
            '{{company_name}}' => $this->config->company_name,
            '{{company_address}}' => $this->config->company_address,
            '{{invoice_number}}' => $invoice_number,
            '{{invoice_date}}' => date($this->config->date_format),
            '{{customer_name}}' => fullname($this->user),
            '{{item_description}}' => $this->string_manager->get_string('course_payment', 'local_invoicepdf', null, $this->user->lang),
            '{{item_amount}}' => $this->transaction->amount . ' ' . $this->transaction->currency,
            '{{total_amount}}' => $this->transaction->amount . ' ' . $this->transaction->currency,
            '{{show_payment_method}}' => $this->config->show_payment_method,
            '{{payment_method}}' => $this->get_payment_method(),
            '{{invoice_footer}}' => $this->string_manager->get_string('invoice_footer', 'local_invoicepdf', null, $this->user->lang),
        ];

        return $this->render_mustache_template($template, $placeholders);
    }

    private function get_payment_method() {
        $component = $this->transaction->payment_area;

        if (strpos($component, 'paygw_') === 0) {
            $gateway = substr($component, 6);  // Remove 'paygw_' prefix
            if (isset($this->payment_gateways[$gateway])) {
                return $this->string_manager->get_string('gatewayname', 'paygw_' . $gateway, null, $this->user->lang);
            }
        }

        // Fallback if the payment method is not recognized
        return $this->string_manager->get_string('unknown_payment_method', 'local_invoicepdf', null, $this->user->lang);
    }

    private function render_mustache_template($template, $context) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/mustache/src/Mustache/Autoloader.php');
        \Mustache_Autoloader::register();

        $mustache = new \Mustache_Engine();
        return $mustache->render($template, $context);
    }

    private function get_logo_path() {
        $fs = get_file_storage();
        $system_context = \context_system::instance();
        $files = $fs->get_area_files($system_context->id, 'local_invoicepdf', 'logo', 0, 'sortorder', false);

        if ($files) {
            $file = reset($files);
            return $file->get_filepath() . $file->get_filename();
        }

        return '';
    }
}