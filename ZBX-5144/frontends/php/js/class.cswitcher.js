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

var CSwitcher = Class.create({
	switcherName : '',
	switchers : {},
	classOpened : 'filteropened',
	classClosed : 'filterclosed',

	initialize : function(name) {
		this.init = true;
		this.switcherName = name;
		var element = $(this.switcherName);

		if (!is_null(element)) {
			addListener(element, 'click', this.showHide.bindAsEventListener(this));

			var state_all = cookie.read(this.switcherName + '_all');
			if (!is_null(state_all)) {
				if (state_all == 1) {
					element.className = this.classOpened;
				}
			}
		}

		var divs = $$('div[data-switcherid]');

		for (var i = 0; i < divs.length; i++) {
			if (!isset(i, divs)) {
				continue;
			}
			addListener(divs[i], 'click', this.showHide.bindAsEventListener(this));

			var switcherid = divs[i].getAttribute('data-switcherid');
			this.switchers[switcherid] = {};
			this.switchers[switcherid]['object'] = divs[i];
		}

		var to_change = cookie.readArray(this.switcherName);
		if (to_change != null) {
			for (var i = 0; i < to_change.length; i++) {
				if (!isset(i, to_change)) {
					continue;
				}
				this.open(to_change[i]);
			}
		}
		this.init = false;
	},

	open : function(switcherid) {
		if (isset(switcherid, this.switchers)) {
			$(this.switchers[switcherid]['object']).className = this.classOpened;
			var elements = $$('tr[data-parentid=' + switcherid + ']');
			for (var i = 0; i < elements.length; i++) {
				if (!isset(i, elements)) {
					continue;
				}
				elements[i].style.display = '';
			}
			this.switchers[switcherid]['state'] = 1;

			if (this.init === false) {
				this.storeCookie();
			}
		}
	},

	showHide : function(e) {
		PageRefresh.restart();

		var obj = Event.element(e);
		var switcherid = obj.getAttribute('data-switcherid');

		if (obj.className == this.classClosed) {
			var state = 1;
			var newClassName = this.classOpened;
			var oldClassName = this.classClosed;
		}
		else {
			var state = 0;
			var newClassName = this.classClosed;
			var oldClassName = this.classOpened;
		}
		obj.className = newClassName;

		if (empty(switcherid)) {
			cookie.create(this.switcherName + '_all', state);

			var divs = $$('div.' + oldClassName);
			for (var i = 0; i < divs.length; i++) {
				if (empty(divs[i])) {
					continue;
				}
				divs[i].className = newClassName;
			}
		}

		var elements = $$('tr[data-parentid]');
		for (var i = 0; i < elements.length; i++) {
			if (empty(elements[i])) {
				continue;
			}
			if (empty(switcherid) || elements[i].getAttribute('data-parentid') == switcherid) {
				if (state) {
					elements[i].style.display = '';
				}
				else {
					elements[i].style.display = 'none';
				}
			}
		}

		if (empty(switcherid)) {
			for (var i in this.switchers) {
				this.switchers[i]['state'] = state;
			}
		}
		else {
			this.switchers[switcherid]['state'] = state;
		}
		this.storeCookie();
	},

	storeCookie : function() {
		var storeArray = [];

		for (var i in this.switchers) {
			if (this.switchers[i]['state'] == 1) {
				storeArray.push(i);
			}
		}
		cookie.createArray(this.switcherName, storeArray);
	}
});
