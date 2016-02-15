/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

var CSwitcher = Class.create({
	switcherName : '',
	switchers : {},

	initialize : function(name) {
		this.switcherName = name;

		var element = $(this.switcherName),
			toggles = $$('div[data-switcherid]'),
			switcherids = cookie.readArray(this.switcherName),
			state_all = 0;

		if (element == null) {
			return;
		}

		addListener(element, 'click', this.showHide.bindAsEventListener(this));

		for (var i = 0; i < toggles.length; i++) {
			addListener(toggles[i], 'click', this.showHide.bindAsEventListener(this));

			var switcherid = toggles[i].getAttribute('data-switcherid');

			this.switchers[switcherid] = {};
			this.switchers[switcherid]['state'] = (switcherids.indexOf(switcherid) != -1 ? 1 : 0);

			if (this.switchers[switcherid]['state'] == 1) {
				toggles[i].firstChild.className = 'arrow-down';

				var elements = $$('tr[data-parentid=' + switcherid + ']');

				for (var j = 0; j < elements.length; j++) {
					elements[j].style.display = '';
				}

				state_all = 1;
			}
		}

		if (state_all == 1) {
			element.firstChild.className = 'arrow-down';
		}

		this.storeCookie();
	},

	showHide : function(e) {
		PageRefresh.restart();

		var obj = Event.element(e);

		if (obj.className != 'treeview') {
			obj = obj.parentElement;
		}

		var switcherid = obj.getAttribute('data-switcherid'),
			state = (obj.firstChild.className == 'arrow-right' ? 1 : 0),
			state_all = (state == 1 ? 1 : 0);

		obj.firstChild.className = (state == 1 ? 'arrow-down' : 'arrow-right');

		var toggles = $$(switcherid == null ? 'div[data-switcherid]' : 'div[data-switcherid=' + switcherid + ']');

		for (var i = 0; i < toggles.length; i++) {
			var toggle_switcherid = toggles[i].getAttribute('data-switcherid');

			if (this.switchers[toggle_switcherid]['state'] != state) {
				this.switchers[toggle_switcherid]['state'] = state;
				toggles[i].firstChild.className = (state == 1 ? 'arrow-down' : 'arrow-right');

				var elements = $$('tr[data-parentid=' + toggle_switcherid + ']');

				for (var j = 0; j < elements.length; j++) {
					elements[j].style.display = (state == 1 ? '' : 'none');
				}
			}
		}

		if (state_all != 1) {
			for (var i in this.switchers) {
				if (this.switchers[i]['state'] == 1) {
					state_all = 1;
				}
			}
		}

		$(this.switcherName).firstChild.className = (state_all == 1 ? 'arrow-down' : 'arrow-right');

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
