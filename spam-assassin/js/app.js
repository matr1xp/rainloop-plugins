(function ($, window) {
    
	$(function () {
		console.debug('*** Loaded spam-assassin JS plugin(tm) ***');
		if (window.rl && window.rl.settingsGet('Auth'))
		{
			var sSpamConfig = window.rl.pluginSettingsGet('spam-assassin', 'spam_assassin_config');
			if (sSpamConfig) {
				window._spamassassin = window._spamassassin || [];
				//Capture spam button click email senders
				$( document ).on( "click", "a.button-spam", function() { 
				    var sel = $('i.checkboxMessage.icon-checkbox-checked');
				    $.each(sel, function( index, div ) {
				        var email = $(div).parent().parent().find('.senderParent').find('span.sender').attr('title');
				        window._spamassassin.push(email);
				    });
				});
				window.rl.addHook('ajax-default-request', function (sAction, oParameters) {
					if ('MessageMove' === sAction && oParameters)
					{
						oParameters['EmailFrom'] = window._spamassassin.toString();
					}
				});
				
				window.rl.addHook('ajax-default-response', function (sAction, oData, sType) {
					if ('MessageMove' === sAction && sType)
					{
						window._spamassassin = [];
					}
				});
			}
			
		}

	});

}($, window));
