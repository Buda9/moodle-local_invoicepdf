<?php
namespace local_invoicepdf;

defined('MOODLE_INTERNAL') || die();

class invoice_manager {
    public static function get_user_invoices($userid, $page = 0, $perpage = 10) {
        global $DB;
        return $DB->get_records('local_invoicepdf_invoices',
                                array('userid' => $userid),
                                'timecreated DESC',
                                '*',
                                $page * $perpage,
                                $perpage);
    }

    public static function get_all_invoices($page = 0, $perpage = 10) {
        global $DB;
        return $DB->get_records('local_invoicepdf_invoices',
                                null,
                                'timecreated DESC',
                                '*',
                                $page * $perpage,
                                $perpage);
    }

    public static function get_user_invoices_count($userid) {
        global $DB;
        return $DB->count_records('local_invoicepdf_invoices', array('userid' => $userid));
    }

    public static function get_all_invoices_count() {
        global $DB;
        return $DB->count_records('local_invoicepdf_invoices');
    }

    public static function get_invoice($id) {
        global $DB;
        return $DB->get_record('local_invoicepdf_invoices', array('id' => $id));
    }

    public static function get_invoice_pdf($id) {
        global $DB;
        $invoice = self::get_invoice($id);
        if (!$invoice) {
            return false;
        }
        // We'll assume it's stored in the database
        return $invoice->pdf_content;
    }

    public static function resend_invoice($id) {
        global $DB;
        $invoice = self::get_invoice($id);
        if (!$invoice) {
            return false;
        }
        $user = \core_user::get_user($invoice->userid);
        $pdf_content = self::get_invoice_pdf($id);

        $subject = get_string('invoice_email_subject', 'local_invoicepdf');
        $message = get_string('invoice_email_body', 'local_invoicepdf');
        $filename = "invoice_{$invoice->invoice_number}.pdf";

        return email_to_user(
            $user,
            \core_user::get_support_user(),
            $subject,
            $message,
            $message,
            $pdf_content,
            $filename
        );
    }

    public static function store_invoice($userid, $invoice_number, $amount, $currency, $pdf_content) {
        global $DB;
        $invoice = new \stdClass();
        $invoice->userid = $userid;
        $invoice->invoice_number = $invoice_number;
        $invoice->amount = $amount;
        $invoice->currency = $currency;
        $invoice->pdf_content = $pdf_content;
        $invoice->timecreated = time();

        return $DB->insert_record('local_invoicepdf_invoices', $invoice);
    }
}