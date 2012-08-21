/*
 ** Zabbix
 ** Copyright (C) 2000-2012 Zabbix SIA
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


jQuery(function($) {
	'use strict';

	/**
	 * Object that sends ajax request for server status and show/hide warning messages.
	 *
	 * @type {Object}
	 */
	var checker = {
		timeout: 10000, // 10 seconds
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
				this.hideWarning();
			}
			else {
				this.showWarning(result.message);
			}
		},

		showWarning: function(message) {
			if (!this.warning) {
				$('#message-global').text(message);
				$('#message-global-wrap').fadeIn(100);
				this.warning = true;
			}
		},

		hideWarning: function() {
			if (this.warning) {
				$('#message-global-wrap').fadeOut(100);
				this.warning = false;
			}
		}
	};

	// looping function that check for server status every 10 seconds
	function checkStatus(nocache) {
		checker.check(nocache);

		window.setTimeout(checkStatus, checker.timeout);
	}

	// start server status checks with 5 sec dealy after page is loaded
	window.setTimeout(function() {
		checkStatus(true);
	}, 5000);


	// event that hide warning message when mouse hover it
	$('#message-global-wrap').on('mouseenter', function() {
		var obj = $(this),
			offset = obj.offset(),
			x1 = Math.floor(offset.left),
			x2 = x1 + obj.outerWidth(),
			y1 = Math.floor(offset.top),
			y2 = y1 + obj.outerHeight();

		obj.fadeOut(100);

		$(document).on('mousemove.messagehide', function(e) {
			if (e.pageX < x1 || e.pageX > x2 || e.pageY < y1 || e.pageY > y2) {
				obj.fadeIn(100);
				$(document).off('mousemove.messagehide');
				$(document).off('mouseleave.messagehide');
			}
		});
		$(document).on('mouseleave.messagehide', function() {
			obj.fadeIn(100);
			$(document).off('mouseleave.messagehide');
			$(document).off('mousemove.messagehide');
		});
	});
});
