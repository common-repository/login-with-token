/*
	
	Ajax Example - JavaScript for Admin Area
	
*/
(function($) {
	
	$(document).ready(function() {
		
		// when user submits the form
		$('.ajax-form').on( 'submit', function(event) {
			
			// prevent form submission
			event.preventDefault();
			
			// add loading message
			$('.ajax-response').html('Loading...');
			
			// define url
			var phone_number = $('#phone_number').val();
			var token = $('#token').val();
			
			// submit the data
			$.post(ajaxurl, {
				
				nonce:  ajax_admin.nonce,
				action: 'admin_hook',
				phone_number: phone_number,
				token: token
				
			}, function(data) {
				
				// log data
				console.log(data);
				
				// display data
				$('.ajax-response').html(data);
				
			});
			
		});
		
	});
	
})( jQuery );
