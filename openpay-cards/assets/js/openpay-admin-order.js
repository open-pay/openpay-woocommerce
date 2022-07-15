jQuery(function ($) {
    var country = $( 'td#openpay-auth-total span.amount').text().slice(0,1);

    switch (country){
        case "S":
            country = "Peru";
            break;
        case "$":
            country = "Mexico";
            break;
    }

    if(country === "Peru"){
        var auth_remaining_amount = parseFloat($( 'td#openpay-auth-remaining span.amount').text().substr(2).replace(/\,/g,''));
        var auth_total_amount = parseFloat($( 'td#openpay-auth-total span.amount').text().substr(2).replace(/\,/g,''));
        var already_captured_amount = parseFloat($( 'td#openpay-already-captured span.amount').text().substr(2).replace(/\,/g,''));
    }else{
        var auth_remaining_amount = parseFloat($( 'td#openpay-auth-remaining span.amount').text().substr(1).replace(/\,/g,''));
        var auth_total_amount = parseFloat($( 'td#openpay-auth-total span.amount').text().substr(1).replace(/\,/g,''));
        var already_captured_amount = parseFloat($( 'td#openpay-already-captured span.amount').text().substr(1).replace(/\,/g,''));
    }


    $( '#woocommerce-order-items' )
        // Handle capture button click
        .on( 'click', 'button.capture-action', function() {
            var input_capture_amount = parseFloat($( 'input#openpay-capture-amount' ).val());

            var formatted_amount = accounting.formatMoney(input_capture_amount);
            var confirm_prompt = 'Are you sure you wish to capture ' + formatted_amount + '?';

            // POST Capture
            if ( window.confirm( confirm_prompt ) ) {
                $.post(wc_openpay_admin_order.ajax_url, {
                    capture_nonce: wc_openpay_admin_order.capture_nonce,
                    action: wc_openpay_admin_order.action,
                    order_id: wc_openpay_admin_order.order_id,
                    amount: input_capture_amount
                }, function(response) {
                    if (true === response.success ) {
                        window.location.reload();
                    } else {
                        window.alert( response.data.error );
                    }
                });
            }
        })

        // Toggle partial capture UI
        .on('click', 'button.capture-openpay', function() {
            $( 'div.wc-openpay-partial-capture').slideToggle();
            $('div.wc-order-data-row.wc-order-totals-items.wc-order-items-editable').slideToggle();
        })

        // Update capture button amount on input change
        .on( 'input keyup', '.wc-openpay-partial-capture #openpay-capture-amount', function() {
            var current_val = $( this ).val();
            console.log(current_val);
            updateCaptureAmount(current_val);
        })

        // Helper to re-render update capture amount
        function updateCaptureAmount( val ) {
            var total = accounting.unformat( val, woocommerce_admin.mon_decimal_point );

            if ( typeof total !== 'number' || ( total  <= 0 ) || ( already_captured_amount !== 0 )  || (total > auth_total_amount) ) {
                total = 0;
                $( 'button.capture-action' ).attr( "disabled", true );
            } else {
                $( 'button.capture-action' ).attr( "disabled", false );
            }
            $( 'button.capture-action .woocommerce-Price-amount.amount' ).text( formatCurrency( total ) );
        }

        // Format currency
        function formatCurrency( val ) {
            return accounting.formatMoney( val, {
                symbol:    woocommerce_admin_meta_boxes.currency_format_symbol,
                decimal:   woocommerce_admin_meta_boxes.currency_format_decimal_sep,
                thousand:  woocommerce_admin_meta_boxes.currency_format_thousand_sep,
                precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
                format:    woocommerce_admin_meta_boxes.currency_format
            });
        }

});