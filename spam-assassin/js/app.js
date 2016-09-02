(function ($, window) {
    
	$(function () {

		if (window.rl && window.rl.settingsGet('Auth'))
		{
			//Capture spam button click email senders
			$( document ).on( "click", "a.button-spam", function() { 
			    var sel = $('i.checkboxMessage.icon-checkbox-checked');
			    $.each(sel, function( index, div ) {
			        var email = $(div).parent().parent().find('.senderParent').find('span.sender').attr('title');
			        console.debug(email);
			    });
			});
		}

	});

}($, window));
