var checkServerStatus = (function ($) {
	"use strict";

	var checker = {
		timeout: 0,
		warning: false,

		/**
		 * Sends ajax request to get Zabbix server availability and message to show if server is not available.
		 *
		 * @param nocache add 'nocache' parameter to get result not from cache
		 */
		check: function(nocache) {
			var params = nocache ? {nocache: true} : {};

			new RPC.Call({
				'method': 'zabbix.status',
				'params': params,
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

	function checkStatus(nocache) {
		checker.check(nocache);

		window.setTimeout(checkStatus, checker.timeout);
	}

	return function(timeout) {
		checker.timeout = timeout * 1000;

		checkStatus(true);
	}
}(jQuery));

