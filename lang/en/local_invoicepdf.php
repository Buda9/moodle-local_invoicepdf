<?php
$string['pluginname'] = 'Invoice PDF Generator';
$string['invoicepdf:manage'] = 'Manage Invoice PDF settings';

// Settings strings
$string['setting_company_name'] = 'Company Name';
$string['setting_company_name_desc'] = 'Your company name to be displayed on the invoice';
$string['setting_company_address'] = 'Company Address';
$string['setting_company_address_desc'] = 'Your company address to be displayed on the invoice';
$string['setting_company_logo'] = 'Company Logo';
$string['setting_company_logo_desc'] = 'Upload your company logo to be displayed on the invoice';
$string['setting_invoice_prefix'] = 'Invoice Number Prefix';
$string['setting_invoice_prefix_desc'] = 'Prefix to use for invoice numbers (e.g., INV-)';
$string['setting_next_invoice_number'] = 'Next Invoice Number';
$string['setting_next_invoice_number_desc'] = 'The next invoice number to be used';
$string['setting_date_format'] = 'Date Format';
$string['setting_date_format_desc'] = 'Format to use for dates on the invoice';
$string['setting_show_payment_method'] = 'Show Payment Method';
$string['setting_show_payment_method_desc'] = 'Include the payment method on the invoice';
$string['setting_invoice_footer'] = 'Invoice Footer';
$string['setting_invoice_footer_desc'] = 'Text to appear in the footer of each invoice';
$string['default_invoice_footer'] = 'Copyright by Company';
$string['setting_available_languages'] = 'Available Languages';
$string['setting_available_languages_desc'] = 'Select languages in which invoices can be generated';
$string['setting_invoice_template'] = 'Invoice Template';
$string['setting_invoice_template_desc'] = 'HTML template for the invoice. Use placeholders like {{company_name}}, {{invoice_number}}, etc.';

// Invoice strings
$string['invoice'] = 'Invoice';
$string['invoice_for_payment'] = 'Invoice for payment';
$string['invoice_keywords'] = 'Invoice, Payment, Moodle';
$string['date'] = 'Date';
$string['to'] = 'To';
$string['description'] = 'Description';
$string['amount'] = 'Amount';
$string['total'] = 'Total';
$string['payment_method'] = 'Payment Method';
$string['course_payment'] = 'Course payment';
$string['unknown_payment_method'] = 'Unknown payment method';
$string['invoice_footer'] = 'Thank you for your business!';

// Email strings
$string['invoice_email_subject'] = 'Your invoice for recent payment';
$string['invoice_email_body'] = 'Please find attached your invoice for the recent payment.';

// Default invoice template
$string['default_invoice_template'] = '
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
<footer>{{invoice_footer}}</footer>
';

$string['invoice_email_failed'] = 'Failed to send invoice email. Please contact the system administrator.';

$string['user_invoice_archive'] = 'My Invoices';
$string['admin_invoice_archive'] = 'All Invoices';
$string['invoice_number'] = 'Invoice Number';
$string['actions'] = 'Actions';
$string['download'] = 'Download';
$string['resend'] = 'Resend';
$string['user'] = 'User';
$string['invalidinvoice'] = 'Invalid invoice';
$string['invoice_resent'] = 'Invoice has been resent successfully';
$string['invoice_resend_failed'] = 'Failed to resend invoice';

$string['setting_invoices_per_page'] = 'Invoices per page';
$string['setting_invoices_per_page_desc'] = 'Number of invoices to display per page in the invoice list';
$string['delete_invoice'] = 'Delete invoice';
$string['delete_invoice_confirm'] = 'Are you sure you want to delete this invoice?';
$string['invoice_deleted'] = 'Invoice has been deleted successfully';
$string['invoice_delete_failed'] = 'Failed to delete the invoice';