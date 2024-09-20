/**
 * Charts module for the Invoice PDF plugin.
 *
 * @module     local_invoicepdf/charts
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import ChartBuilder from 'core/chart_builder';
import Notification from 'core/notification';

/**
 * Initialize the chart.
 */
export const init = () => {
    const chartContainer = document.getElementById('invoiceChart');
    if (!chartContainer) {
        console.error('Chart container not found');
        return;
    }

    const chartContext = chartContainer.getContext('2d');

    Ajax.call([{
        methodname: 'local_invoicepdf_get_invoice_data',
        args: {},
    }])[0]
        .then((response) => {
            try {
                new ChartBuilder(chartContext, {
                    type: 'bar',
                    data: response,
                });
            } catch (error) {
                Notification.exception(error);
            }
        })
        .catch((error) => {
            Notification.exception(error);
        });
};