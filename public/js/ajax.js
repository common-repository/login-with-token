jQuery(document).ready(function($) {
    
    $('body').on('submit', 'form#tsa_form_login_id', function (e) {
        
        //Disable button to avoid double-clicks
        $('#ajax_submit_button').attr('disabled', 'disabled');

        e.preventDefault();

        var tsa_phone_number = $('#tsa_phone_number').val();
        var tsa_country_code = $('#tsa_country_code').val();
        tsa_phone_number = tsa_country_code + '' + tsa_phone_number.replace(/^0+/, '');
        
        var data = {
            'action': 'send_token_ajax_php',
            'tsa_phone_number': tsa_phone_number
        };

        jQuery.post(tsa_token.ajaxurl, data, function(response) {
            console.log('Got this from the server: ' + response);
            $('#tsa_form_div_response_text').text(response);
            $( ".tsa_form_div" ).fadeOut( "slow");
            $( ".tsa_form_div_response" ).fadeIn( "slow");
        });
    });
});