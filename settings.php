<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_invoicepdf', get_string('pluginname', 'local_invoicepdf'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext('local_invoicepdf/company_name',
        get_string('setting_company_name', 'local_invoicepdf'),
        get_string('setting_company_name_desc', 'local_invoicepdf'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('local_invoicepdf/company_address',
        get_string('setting_company_address', 'local_invoicepdf'),
        get_string('setting_company_address_desc', 'local_invoicepdf'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configstoredfile('local_invoicepdf/company_logo',
        get_string('setting_company_logo', 'local_invoicepdf'),
        get_string('setting_company_logo_desc', 'local_invoicepdf'),
        'logo', 0, ['maxfiles' => 1, 'accepted_types' => ['image']]));

    $settings->add(new admin_setting_configtextarea('local_invoicepdf/invoice_template',
        get_string('setting_invoice_template', 'local_invoicepdf'),
        get_string('setting_invoice_template_desc', 'local_invoicepdf'),
        '
        <h1>{{company_name}}</h1>
        <p>{{company_address}}</p>
        <h2>Invoice #{{invoice_number}}</h2>
        <p>Date: {{invoice_date}}</p>
        <p>To: {{customer_name}}</p>
        <table>
            <tr>
                <th>Description</th>
                <th>Amount</th>
            </tr>
            <tr>
                <td>{{item_description}}</td>
                <td>{{item_amount}}</td>
            </tr>
        </table>
        <p>Total: {{total_amount}}</p>
        ', PARAM_RAW));

    $settings->add(new admin_setting_configtext('local_invoicepdf/invoice_prefix',
    get_string('setting_invoice_prefix', 'local_invoicepdf'),
    get_string('setting_invoice_prefix_desc', 'local_invoicepdf'),
    'INV-', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_invoicepdf/next_invoice_number',
    get_string('setting_next_invoice_number', 'local_invoicepdf'),
    get_string('setting_next_invoice_number_desc', 'local_invoicepdf'),
    '1', PARAM_INT));

    $settings->add(new admin_setting_configselect('local_invoicepdf/date_format',
    get_string('setting_date_format', 'local_invoicepdf'),
    get_string('setting_date_format_desc', 'local_invoicepdf'),
    'Y-m-d', [
        'Y-m-d' => 'YYYY-MM-DD',
        'd.m.Y' => 'DD.MM.YYYY',
        'm/d/Y' => 'MM/DD/YYYY'
    ]));

    $settings->add(new admin_setting_configcheckbox('local_invoicepdf/show_payment_method',
    get_string('setting_show_payment_method', 'local_invoicepdf'),
    get_string('setting_show_payment_method_desc', 'local_invoicepdf'),
    1));

    $settings->add(new admin_setting_configtextarea('local_invoicepdf/invoice_footer',
    get_string('setting_invoice_footer', 'local_invoicepdf'),
    get_string('setting_invoice_footer_desc', 'local_invoicepdf'),
    get_string('default_invoice_footer', 'local_invoicepdf'), PARAM_RAW));

    // Multilanguage support
    $languages = get_string_manager()->get_list_of_translations();
    $settings->add(new admin_setting_configmultiselect('local_invoicepdf/available_languages',
    get_string('setting_available_languages', 'local_invoicepdf'),
    get_string('setting_available_languages_desc', 'local_invoicepdf'),
    [current_language()], $languages));

    $settings->add(new admin_setting_configtextarea('local_invoicepdf/invoice_template',
    get_string('setting_invoice_template', 'local_invoicepdf'),
    get_string('setting_invoice_template_desc', 'local_invoicepdf'),
    get_string('default_invoice_template', 'local_invoicepdf'), PARAM_RAW));

    $settings->add(new admin_setting_configtext('local_invoicepdf/invoices_per_page',
    get_string('setting_invoices_per_page', 'local_invoicepdf'),
    get_string('setting_invoices_per_page_desc', 'local_invoicepdf'),
    '10', PARAM_INT));
}