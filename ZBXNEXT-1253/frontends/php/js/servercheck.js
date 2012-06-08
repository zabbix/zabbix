/*
 ** Zabbix
 ** Copyright (C) 2000-2011 Zabbix SIA
 **
 ** This program is free software; you can redistribute it and/or modify
 ** it under the terms of the GNU General Public License as published by
 ** the Free Software Foundation; either version 2 of the License, or
 ** (at your option) any later version.
 **
 ** This program is distributed in the hope that it will be useful,
 ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 ** GNU General Public License for more details.
 **
 ** You should have received a copy of the GNU General Public License
 ** along with this program; if not, write to the Free Software
 ** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/

var checkServerStatus = (function ($) {
	'use strict';

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

