/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


jQuery(function($) {
	'use strict';

	/**
	 * Object that sends ajax request for server status and show/hide warning messages.
	 *
	 * @type {Object}
	 */
	var ServerChecker = {
		$elem: null,
		elem_offset_top: 0,
		delay: 10000, // 10 seconds
		warning: false,

		/**
		 * Function to start check server status via RPC call.
		 *
		 * @param {object}  $elem    General element.
		 * @param {integer} timeout  Check rate.
		 */
		start: function($elem, timeout) {
			if (!$elem.length) {
				return false;
			}

			this.prepareNext(timeout);

			this.$elem = $elem;
			this.updateWidth();
			this.$elem.on('mouseenter', this.hideMessage.bind(this));
			$(window).on('resize', this.updateWidth.bind(this));
		},

		prepareNext: function(delay) {
			setTimeout(this.check.bind(this), delay || this.delay);
		},

		/**
		 * Sends ajax request to get Zabbix server availability and message to show if server is not available.
		 */
		check: function() {
			new RPC.Call({
				'method': 'zabbix.status',
				'params': {nocache: true},
				'onSuccess': this.onSuccess.bind(this)
			});
		},

		onSuccess: function(response) {
			if (response.result) {
				this.hideMessage();
			}
			else {
				this.$elem.html($('<span>').text(response.message));
				this.showMessage()
			}

			this.prepareNext();
		},

		showMessage: function(e) {
			if (!this.warning || (e && (e.pageY < this.elem_offset_top || e.type === 'mouseleave'))) {
				$(document).off('mousemove.ServerChecker mouseleave.ServerChecker');

				this.warning = true;
				this.$elem
					.css('display', 'flex')
					.hide()
					.fadeIn(200);
			}
		},

		hideMessage: function(e) {
			if (this.warning) {
				if (e && e.type === 'mouseenter') {
					$(document).on('mousemove.ServerChecker mouseleave.ServerChecker', this.showMessage.bind(this));

					this.elem_offset_top = this.$elem.offset().top;
				}
				else {
					this.warning = false;
				}

				this.$elem.fadeOut(200);
			}
		},

		updateWidth: function() {
			let $wrapper = $('.wrapper');
			this.$elem.css({
				left: $wrapper.offset().left + 10,
				width: $wrapper[0].clientWidth - 20
			});
		}
	};

	ServerChecker.start($('#msg-global-footer'), 5000);
});
