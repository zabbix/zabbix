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
 * In case if user has set incredibly long notification timeout - he would end up never seeing them, because JS would
 * execute scheduled timeout immediately. So limit the upper bound.
 */
ZBX_Notification.max_timeout = Math.pow(2, 30);

/**
 * Represents notification object.
 *
 * @param {object} raw  A server or LS format of this notification.
 */
function ZBX_Notification(raw) {
	/*
	 * These are pseudo that properties will cycle back into store, to be reused when rendering next time.
	 * If `received_at` property has not been set it is first render. This property might be updated to a new client
	 * time when it's raw state changes from unresolved into resolved.
	 */
	this._raw = {
		snoozed: false,
		received_at: raw.received_at || (+new Date / 1000)
	};

	this.updateRaw(raw);

	this.node = this.makeNode();
}

/**
 * @return {string}
 */
ZBX_Notification.prototype.getId = function() {
	return this._raw.eventid;
};

/**
 * @return {object}
 */
ZBX_Notification.prototype.getRaw = function() {
	return this._raw;
};

/**
 * Merge another raw object into current. If resolution state changes into resolved during this merge, then
 * raw object is assumed to be just received.
 *
 * @param {object} raw  An object in format that is used in store. All keys are optional.
 *
 * @return {ZBX_Notification}
 */
ZBX_Notification.prototype.updateRaw = function(raw) {
	if (!this._raw.resolved && raw.resolved) {
		this._raw.received_at = (+new Date / 1000);
	}

	for (var field_name in raw) {
		this._raw[field_name] = raw[field_name];
	}

	return this;
};

/**
 * Notification body is not updated if server changed it's name, this is to be less confusing.
 *
 * @param {object} severity_styles  Object of class names keyed by severity id.
 */
ZBX_Notification.prototype.render = function(severity_styles) {
	var title_prefix = this._raw.resolved ? locale.S_RESOLVED : locale.S_PROBLEM_ON;

	this.node.title_node.innerHTML = title_prefix + ' ' + BBCode.Parse(this._raw.title);
	this.node.indicator.className = 'notif-indic ' + severity_styles[this._raw.resolved ? -1 : this._raw.severity];
	this.node.snooze_icon.style.opacity = this._raw.snoozed ? 1 : 0;
};

/**
 * @return {float}  Zero or more milliseconds.
 */
ZBX_Notification.prototype.calcDisplayTimeout = function(user_settings) {
	var time_local = (+new Date / 1000),
		timeout = this._raw.resolved ? user_settings.msg_recovery_timeout : user_settings.msg_timeout,
		ttl = (this._raw.received_at - time_local) + timeout;

	return ttl < 0 ? 0 : ttl * 1000;
};

/**
 * @depends {BBCode}
 *
 * @return {HTMLElement}  Detached DOM node.
 */
ZBX_Notification.prototype.makeNode = function() {
	var node = document.createElement('li'),
		indicator = document.createElement('div'),
		title_node = document.createElement('h4');

	node.appendChild(indicator);
	node.appendChild(title_node);

	this._raw.body.forEach(function(line) {
		var p = document.createElement('p');
		p.innerHTML = BBCode.Parse(line);
		node.appendChild(p);
	});

	node.indicator = indicator;
	node.title_node = title_node;
	node.snooze_icon = document.createElement('div');
	node.snooze_icon.className = 'notif-indic-snooze';
	node.snooze_icon.style.opacity = 0;

	node.indicator.appendChild(node.snooze_icon);

	return node;
};

/**
 * Explicitly check if node is connected. Also, in case of IE11 there is no 'isConnected' getter.
 *
 * @return {bool}
 */
ZBX_Notification.prototype.isNodeConnected = function() {
	return !!(this.node.isConnected || this.node.parentNode);
};
