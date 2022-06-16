<button type="button" class="button capture-openpay">Capture</button>
<div class="wc-order-data-row wc-order-data-row-toggle wc-openpay-partial-capture" style="display:none" data-ol-has-click-handler>
    <table class="wc-order-totals">

        <tr>
            <td class="label">Total autorizado:</td>
            <td class="total" id="openpay-auth-total"><?php echo wc_price( $auth_total, array( 'currency' => $order->get_currency() ) ); ?></td>
        </tr>
        <tr>
            <td class="label">Monto capturado:</td>
            <td class="total" id="openpay-already-captured"><?php echo wc_price( $already_captured, array( 'currency' => $order->get_currency() ) ); ?></td>
        </tr>

        <?php if ( $already_captured != 0 ) : ?>
            <tr>
                <td class="label">Monto autorizado remanente:</td>
                <td class="total" id="openpay-auth-remaining"><?php echo wc_price( $auth_remaining, array( 'currency' => $order->get_currency() ) ); ?></td>
            </tr>
        <?php endif; ?>

        <tr>
            <td class="label"><label for="openpay-capture-amount">Total a capturar:</label></td>
            <td class="total">
                <input type="text" class="text" id="openpay-capture-amount" name="openpay-capture-amount"  />
                <div class="clear"></div>
            </td>
        </tr>
    </table>
    <div class="clear"></div>
    <div class="capture-actions">
        <?php $amount = '<span class="capture-amount">' . wc_price( 0, array( 'currency' => $order->get_currency() ) ) . '</span>'; ?>
        <button type="button" class="button button-primary capture-action" disabled="disabled" ><?php printf( 'Captura %s via Openpay', $amount ); ?></button>
        <div class="clear"></div>
    </div>
</div>
