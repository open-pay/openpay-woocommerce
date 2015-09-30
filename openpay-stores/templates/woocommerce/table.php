<h2><?php _e( 'Order Details', 'woocommerce' ); ?></h2>
<table class="shop_table order_details">
    <thead>
        <tr>
            <th class="product-name"><?php _e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-total"><?php _e( 'Total', 'woocommerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ( sizeof( $order->get_items() ) > 0 ) {
            foreach( $order->get_items() as $item ) {
                $_product     = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
                $item_meta    = new WC_Order_Item_Meta( $item['item_meta'], $_product );
                ?>
                <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?>">
                    <td class="product-name">
                        <?php
                            if ( $_product && ! $_product->is_visible() )
                                echo apply_filters( 'woocommerce_order_item_name', $item['name'], $item );
                            else
                                echo apply_filters( 'woocommerce_order_item_name', sprintf( '<a href="%s">%s</a>', get_permalink( $item['product_id'] ), $item['name'] ), $item );
                            echo apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times; %s', $item['qty'] ) . '</strong>', $item );
                            $item_meta->display();
                            if ( $_product && $_product->exists() && $_product->is_downloadable() && $order->is_download_permitted() ) {
                                $download_files = $order->get_item_downloads( $item );
                                $i              = 0;
                                $links          = array();
                                foreach ( $download_files as $download_id => $file ) {
                                    $i++;
                                    $links[] = '<small><a href="' . esc_url( $file['download_url'] ) . '">' . sprintf( __( 'Download file%s', 'woocommerce' ), ( count( $download_files ) > 1 ? ' ' . $i . ': ' : ': ' ) ) . esc_html( $file['name'] ) . '</a></small>';
                                }
                                echo '<br/>' . implode( '<br/>', $links );
                            }
                        ?>
                    </td>
                    <td class="product-total">
                        <?php echo $order->get_formatted_line_subtotal( $item ); ?>
                    </td>
                </tr>
                <?php
                if ( $order->has_status( array( 'completed', 'processing' ) ) && ( $purchase_note = get_post_meta( $_product->id, '_purchase_note', true ) ) ) {
                    ?>
                    <tr class="product-purchase-note">
                        <td colspan="3"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); ?></td>
                    </tr>
                    <?php
                }
            }
        }
        do_action( 'woocommerce_order_items_table', $order );
        ?>
    </tbody>
    <tfoot>
    <?php
        if ( $totals = $order->get_order_item_totals() ) foreach ( $totals as $total ) :
            ?>
            <tr>
                <th scope="row"><?php echo $total['label']; ?></th>
                <td><?php echo $total['value']; ?></td>
            </tr>
            <?php
        endforeach;
    ?>
    </tfoot>
</table>

<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>