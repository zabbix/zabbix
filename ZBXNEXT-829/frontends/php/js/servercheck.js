var checkServerStatus = (function ($) {
	"use strict";

	var checker = {
		timeout: 0,
		warning: false,

		check: function() {
			new RPC.Call({
				'method': 'zabbix.status',
				'params': {},
				'onSuccess': $.proxy(this.onSuccess, this)
			});
		},

		onSuccess: function(result) {
			if (result.result) {
				this.hideWarning()
			}
			else {
				this.showWarning(result.message);
			}
		},

		showWarning: function(message) {
			if (!this.warning) {
				$('#message-global').text(message).addClass('warning-global');
				this.warning = true;
			}
		},

		hideWarning: function() {
			if (this.warning) {
				$('#message-global').text('').removeClass('warning-global');
				this.warning = false;
			}
		}
	};

	function checkStatus() {
		checker.check();

		window.setTimeout(checkStatus, checker.timeout);
	}

	return function(timeout) {
		checker.timeout = timeout * 1000;

		checkStatus();
	}
}(jQuery));

