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


ZABBIX.namespace('classes.Observer');

ZABBIX.classes.Observer = (function() {
	var Observer = function() {
		this.listeners = {};
	};

	Observer.prototype = {
		constructor: ZABBIX.classes.Observer,

		bind: function(e, callback) {
			if (typeof callback === 'function') {
				e = ('' + e).toLowerCase().split(/\s+/);

				for (let i = 0; i < e.length; i++) {
					if (this.listeners[e[i]] === void(0)) {
						this.listeners[e[i]] = [];
					}

					this.listeners[e[i]].push(callback);
				}
			}

			return this;
		},

		trigger: function(e, target) {
			e = e.toLowerCase();

			const handlers = this.listeners[e] || [];

			if (handlers.length) {
				e = jQuery.Event(e);

				for (let i = 0; i < handlers.length; i++) {
					try {
						if (handlers[i](e, target) === false || e.isDefaultPrevented()) {
							break;
						}
					}
					catch(ex) {
						window.console && window.console.log && window.console.log(ex);
					}
				}
			}

			return this;
		}
	};

	Observer.makeObserver = function(object) {
		for (const key in Observer.prototype) {
			if (Observer.prototype.hasOwnProperty(key) && typeof Observer.prototype[key] === 'function') {
				object[key] = Observer.prototype[key];
			}
		}

		object.listeners = {};
	};

	return Observer;
}());
