/*
 * 2013-05-19
 * 
 * Integrates support/sales chat functionality into any website.
 * Line5 e.K.
 * http://www.line5.eu
 * 
 * source code + license:
 * https://github.com/Line5/messup
 *
 * Chat data is stored in Sessions. No database needed.
 * 
 * Requires JQuery! Tested with versions 1.5-1.9
 * 
 */

;
(function($, window, document, undefined) {
	var myObj = null;

	// CHANGE HERE:
	var chatButtonText = 'Live Support Chat';
	var chatButtonBackgroundColor = '#ccc';

	var scripturl = '/messup/messup.php';
	var lg = 'en';
	// DO NOT CHANGE BELOW!

	// define strings
	var strPleaseEnterMsg = new Array();
	var strSendMsg = new Array();
	
	strPleaseEnterMsg['de'] = 'Geben Sie hier Ihre Nachricht ein - senden mit ENTER:';
	strPleaseEnterMsg['en'] = 'Please enter your message - submit with ENTER:';
	strSendMsg['de'] = 'Nachricht senden';
	strSendMsg['en'] = 'Send message';
	
	// Create the defaults once
	var messupChat = "messupChat", defaults = {};
	var messupChatLatestMessage = 0;
	var messupChatActive = false;
	var periodicalUpdateActive = false;
	var messupTimeout = null;
	var agentIsOnline = null;

	// The actual plugin constructor
	function Plugin(element, options) {
		this.element = element;
		// jQuery has an extend method which merges the contents of two or
		// more objects, storing the result in the first object. The first
		// object
		// is generally empty as we don't want to alter the default options for
		// future instances of the plugin
		this.options = $.extend({}, defaults, options);

		this._defaults = defaults;
		this._name = messupChat;

		this.init();
		this.checkMsg();
	}

	Plugin.prototype = {

		init : function() {
			// Place initialization logic here
			// You already have access to the DOM element and
			// the options via the instance, e.g. this.element
			// and this.options
			// you can add more functions like the one below and
			// call them like so: this.yourOtherFunction(this.element,
			// this.options).
			// this.yourOtherFunction(this.element, this.options);

			myGalleryOnPage = this.options['myGalleryOnPage'];
			this.initMessup();
			this.initialiseEvents();

		},
		initialiseEvents : function() {
			/**
			 * event handling for mouseclicks
			 */
			// switch on / off chat window
			$('#messupChatButton').bind('click', function(event) {
				if (agentIsOnline == true) {
					$('#messupWindow').toggle();
					if ($('#messupWindow').is(':visible')) {
						if ($('#messup-form').is(':visible')) {
							$('#messupChat').css({
								'height' : 'auto'
							});
						} else {
							$('#messupChat').css({
								'height' : '70%'
							});
						}
					} else {
						$('#messupChat').css({
							'height' : 'auto'
						});
					}
				}
			});

			// catch all form submissions. submit via ajax.
			var self = this;
			$('#messupChat form')
					.bind(
							'submit',
							function(event) {
								var url = scripturl + '?type=ajax';
								var data = $(this).serializeArray();
								var goOn = true;
								if (data[0].value == 'endchat') {
									goOn = confirm("Are you sure you want to end the chat conversation?");
								}
								if (goOn == true) {
									data[data.length] = {
										name : 'latestMessage',
										value : messupChatLatestMessage
									};
									$('#messupMsgBox').val('');

									$
											.ajax({
												type : "POST",
												url : url,
												data : data,
												fail : function(jqXHR,
														textStatus, errorThrown) {
													alert("Fehler");
												},
												success : function(data) {
													// alert(data);
													// A) add error messages
													// - to initiate chat form
													// - to message send form
													// - to "end chat" form
													// >> .err
													// B) change content
													// displayed
													// - to chat layout
													// - to chat ended layout
													// >> .ps
													// C) add messages to chat
													// window
													// >> .newmsg
													if (data.err) {
														for (i = 0; i < data.err.length; i++) {
															if ($(
																	'input[name="'
																			+ data.err[i].field
																			+ '"]')
																	.size() > 0) {
																$(
																		'input[name="'
																				+ data.err[i].field
																				+ '"]')
																		.toggleClass(
																				'errorfield',
																				true);
																$(
																		'input[name="'
																				+ data.err[i].field
																				+ '"]')
																		.before(
																				"<div class='errtext' id='err"
																						+ data.err[i].field
																						+ "'>"
																						+ data.err[i].note
																						+ "</div>");
															} else {
																$(self)
																		.find(
																				'h2')
																		.after(
																				"<div class='errtext' id='err"
																						+ data.err[i].field
																						+ "'>"
																						+ data.err[i].note
																						+ "</div>");
															}
														}
													} else {
														if (data == null) {
															if ($('#messupConv')
																	.is(
																			':visible')) {
																$(
																		'#messup-form')
																		.show();
																$('#messupConv')
																		.hide();
																$('#messupChat')
																		.css(
																				{
																					'height' : 'auto'
																				});
															}
														}
														if (data.form) {
															if (data.form == 'newchat') {
																$(
																		'#messup-form')
																		.hide();
																$('#messupConv')
																		.show();
																$('#messupChat')
																		.css(
																				{
																					'height' : '70%'
																				});
																if (periodicalUpdateActive == false) {
																	periodicalUpdateActive = true;
																	messupTimeout = setInterval(
																			function() {
																				self
																						.checkMsg(self);
																			},
																			5000);
																}
															} else if (data.form == 'endchat') {
																$(
																		'#messupConversationHistory')
																		.empty();
																$(
																		'#messupWindow')
																		.hide();
																$('#messupChat')
																		.css(
																				{
																					'height' : 'auto'
																				});
																$(
																		'#messup-form')
																		.show();
																$('#messupConv')
																		.hide();
																messupChatLatestMessage = 0;
																messupChatActive = false;
															}
														}
														if (data.messages) {
															if ($(
																	'#messup-form')
																	.is(
																			':visible')) {
																$(
																		'#messup-form')
																		.hide();
																$('#messupConv')
																		.show();
																$('#messupChat')
																		.css(
																				{
																					'height' : '70%'
																				});
															}
															for (i = 0; i < data.messages.length; i++) {
																var newline = "<strong>"
																		+ data.messages[i].time
																		+ " - "
																		+ data.messages[i].name
																		+ ":</strong> "
																		+ data.messages[i].message
																		+ "<br />";
																$(
																		'#messupConversationHistory')
																		.html(
																				$(
																						'#messupConversationHistory')
																						.html()
																						+ newline);
																messupChatLatestMessage = data.messages[i].id;
															}
															$(
																	"#messupConversationHistory")
																	.animate(
																			{
																				scrollTop : $('#messupConversationHistory')[0].scrollHeight
																			},
																			1000);
														}
													}
												},
												dataType : "json"
											});
								}
								return false;
							});

			/**
			 * catch keyboard inputs.
			 */

			$('#messupMsgBox').keypress(function(e) {
				if (e.which == 27) {
					// Escape empties the textbox.
					$('#messupMsgBox').val('');
				} else if (e.which == 13) {
					// enter sends message
					e.preventDefault();
					$('#messup-chatform').submit();
					return false;
				}
			});

		},
		/**
		 * check for messages
		 */
		checkMsg : function() {
			var url = scripturl + '?type=ajax';
			var data = Array();
			var originalMessupChatLatestMessage = messupChatLatestMessage;
			data[data.length] = {
				name : 'latestMessage',
				value : messupChatLatestMessage
			};
			data[data.length] = {
				name : 'form',
				value : 'checkmsg'
			};
			var self = this;
			$
					.ajax({
						type : "POST",
						url : url,
						data : data,
						fail : function(jqXHR, textStatus, errorThrown) {
							alert("Fehler");
						},
						success : function(data) {
							if (data.err) {
								for (i = 0; i < data.err.length; i++) {
									$('#messupErr').html(data.err[i].note);
									$('#messupErr').css('display', 'block');
								}
							} else {
								$('#messupErr').html("");
								$('#messupErr').css('display', 'none');
							}
							if (data.messages) {
								if ($('#messupWindow').is(':visible') != true) {
									$('#messupWindow').show();
								}
								if ($('#messup-form').is(':visible')) {
									$('#messup-form').hide();
									$('#messupConv').show();
									$('#messupChat').css({
										'height' : '70%'
									});
								}
								for (i = 0; i < data.messages.length; i++) {
									var newline = "<strong>"
											+ data.messages[i].time + " - "
											+ data.messages[i].name
											+ ":</strong> "
											+ data.messages[i].message
											+ "<br />";
									$('#messupConversationHistory').html(
											$('#messupConversationHistory')
													.html()
													+ newline);
									messupChatLatestMessage = data.messages[i].id;
								}
								if (messupChatLatestMessage
										- originalMessupChatLatestMessage > 10) {
									$("#messupConversationHistory")
											.scrollTop(
													$('#messupConversationHistory')[0].scrollHeight);
								} else {
									$("#messupConversationHistory")
											.animate(
													{
														scrollTop : $('#messupConversationHistory')[0].scrollHeight
													}, 1000);
								}
								if (messupChatLatestMessage > 0) {
									messupChatActive = true;
								}
							}
							if (data.online) {
								agentIsOnline = data.online;
								if (agentIsOnline == true) {
									$('#messupOnlineStatus').html("ONLINE");
								}
							}
							if (messupChatActive == true) {
								if (periodicalUpdateActive == false) {
									periodicalUpdateActive = true;
									messupTimeout = setInterval(function() {
										self.checkMsg(self);
									}, 5000);

								}
							}
						},
						dataType : "json"
					});
		},
		/**
		 * initialize sizes
		 */
		initMessup : function() {
			// append support chat window
			var html;
			html = '<div id="messupChat">';
			html += '<a id="messupChatButton"><strong>'
					+ chatButtonText
					+ '</strong></a> <span id="messupOnlineStatus">offline</span>';
			html += '<div id="messupWindow" style="display: none;">';
			html += '<div id="messupErr" style="display: none;"></div>';
			html += '<form id="messup-form" action="" method="POST">';
			html += '<input type="hidden" name="form" value="newchat" />';
			html += '<label for="email">E-Mail</label>';
			html += '<input class="messupInp" type="input" name="email" value="" />';
			html += '<label for="name">Ihr Name</label>';
			html += '<input class="messupInp" type="input" name="name" value="" />';
			html += '<label for="message">Ihr Anliegen</label>';
			html += '<textarea class="messupInp" name="message"></textarea><br />';
			html += '<input type="submit" value="Chat starten..." /></form>';
			html += '<div id="messupConv" style="display: none;">';
			html += '<div id="messupConversationHistory"></div>';
			html += '<form id="messup-chatform" action="" method="POST">';
			html += '<input type="hidden" name="form" value="sendmsg" />';
			html += '<label for="message">'+strPleaseEnterMsg[lg]+'</label>';
			html += '<textarea name="message" id="messupMsgBox"></textarea><br />';
			html += '<input type="submit" class="messupSmall" value="'+strSendMsg[lg]+'" /></form>';
			html += '<form id="messup-chatend" action="" method="POST">';
			html += '<input type="hidden" name="form" value="endchat" />';
			html += '<input type="submit" class="messupSmall" value="X" title="Chat beenden" /></form>';
			html += '</div>';
			html += '</div>';

			html += '</div>';
			jQuery('body').append(html);
			$('#messupChat').css({
				'position' : 'fixed',
				'bottom' : 0,
				'right' : 20,
				'background-color' : chatButtonBackgroundColor,
				'padding' : 7,
				'width' : 310
			});
			$('#messupChat label').css({
				'display' : 'block'
			});
			$('#messupChatButton').css({
				'cursor' : 'pointer'
			});
			$('#messupConversationHistory').css({
				'overflow' : 'scroll',
				'position' : 'absolute',
				'overflow-x' : 'hidden',
				'width' : 300,
				'top' : 50,
				'bottom' : 80,
				'backgroundColor' : '#eee',
				'padding' : 3
			});
			$('.messupSmall').css({
				'font-size' : '9px'
			});
			$('#messupMsgBox').css({
				'width' : 300,
				'height' : 30
			});
			$('#messup-chatend').css({
				'position' : 'absolute',
				'bottom' : 0,
				'right' : 6
			});
			$('#messup-chatform').css({
				'position' : 'absolute',
				'bottom' : 0
			});
			$('#messup-form .messupInp').css({
				'width' : '90%'
			});
			$('#messupErr').css({'position': 'absolute',
				'top': 30, 'right': 5, 'background-color':'#fcc',
				'width': '50%', 'border': 'solid 2px #c00', 'color': '#c00',
				'font-size': '11px', 'padding':3, 'z-index':30})
		}

	};

	$.fn[messupChat] = function(options) {
		myObj = null;
		myObj = new Plugin(this, options);

		return myObj;
	};

})(jQuery, window, document);

jQuery(document).ready(function() {
	jQuery('body').messupChat();
});