/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Represents DOM node for notification list. Stores the collection of ZBX_Notification objects.
 */
function ZBX_NotificationCollection() {
	this._dangling_nodes = [];

	this._list_sequence = [];
	this._list_obj = {};

	this.makeNodes();

	this.node.style.right = '10px';
	this.node.style.top = '0px';
}

/**
 * @return {array}
 */
ZBX_NotificationCollection.prototype.getIds = function() {
	return this._list_sequence;
};

/**
 * @param {callable} callback
 */
ZBX_NotificationCollection.prototype.map = function(callback) {
	var len = this._list_sequence.length;

	while (--len > -1) {
		var ret = callback(this.getById(this._list_sequence[len]), len);
	}
};

/**
 * @param {callable} callback
 *
 * @return {array}
 */
ZBX_NotificationCollection.prototype.filterList = function(callback) {
	var list = [],
		len = this._list_sequence.length;

	while (--len > -1) {
		var ret = callback(this.getById(this._list_sequence[len]));
		(ret !== false) && list.push(ret);
	}

	return list;
};

/**
 * @return {array} List of raw notification objects.
 */
ZBX_NotificationCollection.prototype.getRawList = function() {
	return this.filterList(function(notif) {
		return notif.getRaw();
	});
};

/**
 * Merges current with new list. Updates changes for ZBX_Notification if possible, or creates new ZBX_Notification.
 * Recoverable notification is just partial of raw with one mandatory field - `eventid`. New iterator sequence reflects
 * the order of list given to this method. During merge, nodes that are not present in list will be removed.
 *
 * @param {array} list List of raw/recoverable notification objects.
 */
ZBX_NotificationCollection.prototype.consumeList = function(list) {
	var new_list_sequence = [];

	while (raw = list.pop()) {
		/*
		 * Case if server returns with "recovered" notification type, that cannot be recovered by client.
		 * This should never happen.
		 */
		if (!raw.body && !this._list_obj[raw.eventid]) {
			continue;
		}

		if (this._list_obj[raw.eventid]) {
			this._list_obj[raw.eventid].updateRaw(raw);
		}
		else {
			this._list_obj[raw.eventid] = new ZBX_Notification(raw);
		}

		new_list_sequence.push(raw.eventid);
	}

	for (var id in this._list_obj) {
		if (new_list_sequence.indexOf(id) == -1) {
			this._dangling_nodes.push(this._list_obj[id].node);
			delete this._list_obj[id];
		}
	}

	this._list_sequence = new_list_sequence;
};

/**
 * Creates detached DOM nodes.
 */
ZBX_NotificationCollection.prototype.makeNodes = function() {
	var header = document.createElement('div'),
		controls = document.createElement('ul');

	this.node = document.createElement('div');
	this.node.style.display = 'none';
	this.node.hidden = true;
	this.node.className = 'overlay-dialogue notif';

	this.btn_close = document.createElement('button');
	this.btn_close.setAttribute('title', locale['S_CLOSE']);
	this.btn_close.setAttribute('type', 'button');
	this.btn_close.className = 'overlay-close-btn';

	this.node.appendChild(this.btn_close);

	header.className = 'dashboard-widget-head cursor-move';
	this.node.appendChild(header);

	header.appendChild(controls);

	this.btn_mute = this.makeToggleBtn(
		{class: 'btn-sound-on', title: locale['S_MUTE']},
		{class: 'btn-sound-off', title: locale['S_UNMUTE']}
	);

	this.btn_snooze = this.makeToggleBtn({class: 'btn-alarm-on'}, {class: 'btn-alarm-off'});
	this.btn_snooze.setAttribute('title', locale['S_SNOOZE']);

	controls.appendChild(document.createElement('li').appendChild(this.btn_snooze));
	controls.appendChild(document.createElement('li').appendChild(this.btn_mute));

	this.list_node = document.createElement('ul');
	this.list_node.className = 'notif-body';

	this.node.appendChild(this.list_node);
};

/**
 * Creates a button node with a method `renderState(bool)`.
 *
 * @param {object} attrs_inactive  Attribute key-value object to be mapped on renderState(true).
 * @param {object} attrs_active    Attribute key-value object to be mapped on renderState(false).
 *
 * @return {HTMLElement} DOM button element.
 */
ZBX_NotificationCollection.prototype.makeToggleBtn = function(attrs_inactive, attrs_active) {
	var button = document.createElement('button');
	button.renderState = function(is_active) {
		var attrs = is_active ? attrs_active : attrs_inactive,
			attr_name;

		for (attr_name in attrs) {
			this.setAttribute(attr_name, attrs[attr_name]);
		}
	};

	return button;
};

/**
 * Iterator property will be updated and reference to DOM node kept to be gracefully removed at render. No reference
 * to notification object would exist after this call - notification is removed and will not cycle back into LS.
 *
 * @param {string} id
 */
ZBX_NotificationCollection.prototype.removeById = function(id) {
	var index = this._list_sequence.indexOf(id);

	if (index === -1) {
		return;
	}

	this._list_sequence.splice(index, 1);

	if (this._list_obj[id]) {
		this._list_obj[id].display_timeoutid && clearTimeout(this._list_obj[id].display_timeoutid);
		this._dangling_nodes.push(this._list_obj[id].node);
		delete this._list_obj[id];
	}
};

/**
 * @param {string} id
 *
 * @return {ZBX_Notification}
 */
ZBX_NotificationCollection.prototype.getById = function(id) {
	return this._list_obj[id];
};

/**
 * Shows list of notifications.
 *
 * @return {Promise}
 */
ZBX_NotificationCollection.prototype.show = function() {
	return ZBX_Notifications.util.fadeIn(this.node);
};

/**
 * Hides list of notifications.
 *
 * @return {Promise}
 */
ZBX_NotificationCollection.prototype.hide = function() {
	return ZBX_Notifications.util.fadeOut(this.node);
};

/**
 * @return {boolean}
 */
ZBX_NotificationCollection.prototype.isEmpty = function() {
	return !this._list_sequence.length;
};

/**
 * Animates slide-up-remove on dangling nodes one by one.
 */
ZBX_NotificationCollection.prototype.removeDanglingNodes = function() {
	var duration = this._dangling_nodes.length > 4 ? 200 : 500;
	var first = true;

	while (node = this._dangling_nodes.pop()) {
		ZBX_Notifications.util.slideUp(node, first && 500 || duration, this._dangling_nodes.length * duration)
			.then(function(node) {
				node.parentNode && node.remove();
			});
		first = false;
	}
};

/**
 * Notification sequence is maintained in DOM, in server response notifications must be ordered.
 * Shows or hides list node, updates and appends notification nodes, then deligates to remove dangling nodes.
 *
 * @param {object} severity_styles
 * @param {ZBX_NotificationsAlarm} alarm_state
 */
ZBX_NotificationCollection.prototype.render = function(severity_styles, alarm_state) {
	this.btn_snooze.renderState(alarm_state.isSnoozed(this.getRawList()));
	if (alarm_state.supported) {
		this.btn_mute.renderState(alarm_state.muted);
	}
	else {
		this.btn_mute.renderState(true);
		this.btn_mute.disabled = true;
		this.btn_mute.title = locale['S_CANNOT_SUPPORT_NOTIFICATION_AUDIO'];
	}

	var list_node = this.list_node,
		prev_notif_node = null;

	if (this.isEmpty()) {
		return this.hide().then(function() {
			list_node.innerHTML = '';
			this._dangling_nodes = [];
		}.bind(this));
	}

	var slide_down = list_node.children.length != 0;

	this.map(function(notif, index) {
		notif.render(severity_styles);

		if (notif.isNodeConnected()) {
			prev_notif_node = notif.node;
			return;
		}

		if (prev_notif_node) {
			prev_notif_node.insertAdjacentElement('afterend', notif.node);
		}
		else {
			list_node.insertAdjacentElement('afterbegin', notif.node);
		}

		slide_down && ZBX_Notifications.util.slideDown(notif.node, 200);
		prev_notif_node = notif.node;
	});

	this.removeDanglingNodes();

	(this.node.style.display === 'none') && this.show();
};
