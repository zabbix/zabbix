var checkServerStatus = (function ($) {
	"use strict";

	var checker = {
		timeout: 0,
		warning: false,

		check: function() {
			var that = this;

			new RPC.Call({
				'method': 'zabbix.status',
				'params': {},
				'onSuccess': function(result) {
					if (result) {
						that.hideWarning()
					}
					else {
						that.showWarning();
					}
				},
				'onFailure': function() {}
			});
		},

		showWarning: function() {
			if (!this.warning) {
				$('#message-global').show();
				this.warning = true;
			}
		},

		hideWarning: function() {
			if (this.warning) {
				$('#message-global').hide();
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

