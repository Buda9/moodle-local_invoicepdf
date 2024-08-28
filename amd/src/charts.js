define(['jquery', 'core/ajax', 'core/templates', 'core/chart_builder'],
    function($, Ajax, Templates, ChartBuilder) {
        return {
            init: function() {
                Ajax.call([{
                    methodname: 'local_invoicepdf_get_invoice_data',
                    args: {},
                    done: function(response) {
                        var ctx = document.getElementById('invoiceChart').getContext('2d');
                        new ChartBuilder(ctx, {
                            type: 'bar',
                            data: response
                        });
                    },
                    fail: function(reason) {
                        console.log('Failed to load invoice data: ' + reason);
                    }
                }]);
            }
        };
    });