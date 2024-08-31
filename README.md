# Moodle Invoice PDF Generator Plugin (WiP)

Notice: Still Work in Progress. Invoices are not being generated.

## Overview
The Moodle Invoice PDF Generator is a comprehensive plugin designed to automate the process of generating, sending, and managing invoices within the Moodle learning management system. This plugin seamlessly integrates with Moodle's payment system to create professional, customizable invoices for all financial transactions.

## Key Features
- Automatic PDF invoice generation upon successful payment
- Customizable invoice templates with support for multiple languages
- Secure storage and management of all generated invoices
- User-friendly interface for students to access their invoice history
- Administrative dashboard for managing all invoices across the platform
- Capability to resend invoices and manually generate invoices for specific transactions
- Integration with Moodle's core payment gateways

## Installation
1. Download the plugin and extract it to the `local` directory in your Moodle installation.
2. Rename the extracted folder to `invoicepdf`.
3. Log in as an administrator and visit the notifications page to complete the installation.

## Configuration
After installation, you can configure the plugin by navigating to Site Administration > Plugins > Local plugins > Invoice PDF Generator. Here you can set up:
- Company details (name, address, logo)
- Invoice numbering system
- Email templates
- Invoice design and content

## Usage
- For students: Access your invoice history through your user profile or the custom navigation item.
- For administrators: Manage all invoices, resend invoices, and generate reports through the admin interface.

## Requirements
- Moodle 3.9 or higher
- PHP 7.2 or higher

## API

In order to use plugin API, use something like this

`$invoice_id = \local_invoicepdf\api::generate_invoice($user->id, 100.00, 'USD', 'Course enrollment');`

## Contributing
We welcome contributions to the Moodle Invoice PDF Generator plugin. Please feel free to submit pull requests or create issues for bugs and feature requests.

## License
This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

## Support
For support, please create an issue in the GitHub repository or contact the plugin maintainer.

Thank you for using the Moodle Invoice PDF Generator plugin!
