fetchStatus();

function fetchStatus(){
    jQuery.ajax({
        url : params.site_url + '/wp-admin/admin-ajax.php?action=fetch_order_status&order_id=' + params.order_number,
        type : 'post',
        error : function(response){
            console.log(response);
        },
        success : function( response ){
            console.log(response);
            if(response !== "on-hold"){
                if(response === 'processing'){
                    alert("Pago aceptado");
                }
                clearInterval(intervalOfRequest);
            }
        }
    });
}

let intervalOfRequest = setInterval(function() {
    fetchStatus()
}, 10000);