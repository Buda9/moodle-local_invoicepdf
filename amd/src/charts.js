define(['jquery', 'core/ajax', 'core/chart_builder', 'core/notification'],
    function($, Ajax, ChartBuilder, Notification) {

    /**
     * Initialize the chart.
     */
    function init() {
        var chartContainer = document.getElementById('invoiceChart');
        if (!chartContainer) {
            console.error('Chart container not found');
            return;
        }

        var chartContext = chartContainer.getContext('2d');

        Ajax.call([{
            methodname: 'local_invoicepdf_get_invoice_data',
            args: {}
        }])[0].then(function(response) {
            try {
                new ChartBuilder(chartContext, {
                    type: 'bar',
                    data: response
                });
            } catch (error) {
                Notification.exception(error);
            }
        }).catch(function(error) {
            Notification.exception(error);
        });
    }

    return {
        init: init
    };
});