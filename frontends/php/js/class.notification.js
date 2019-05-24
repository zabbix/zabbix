/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * In milliseconds, slide animation duration upon notification element remove.
 */
ZBX_Notification.ease = 500;

/**
 * In case if user has set incredibly long notification timeout - he would end up never seeing them, because JS would
 * execute scheduled timeout immediately. So limit the upper bound.
 */
ZBX_Notification.max_timeout = Math.pow(2, 30);

/**
 * Upon instance creation an detached DOM node is created and closing time has been scheduled.
 *
 * @param {object} options
 */
function ZBX_Notification(options) {
	this.uid       = options.uid;
	this.node      = this.makeNode(options);
	this.timeoutid = 0;
	this.onTimeout = function() {};
	this.snoozed   = options.snoozed;
}

/**
 * Removes previous timeout if it is scheduled, then schedules a timeout to close this message.
 *
 * @param {integer} seconds  Timeout in seconds for 'close' to be called.
 *
 * @return ZBX_Notification
 */
ZBX_Notification.prototype.setTimeout = function(seconds) {
	clearTimeout(this.timeoutid);

	if (seconds <= 0) {
		this.onTimeout(this);

		return this;
	}

	var ms = seconds * 1000;

	if (ms > ZBX_Notification.max_timeout) {
		ms = ZBX_Notification.max_timeout;
	}

	this.timeoutid = setTimeout(function() {
		this.onTimeout(this);
	}.bind(this), ms);

	return this;
};

/**
 * Renders message object.
 *
 * @depends {BBCode}
 *
 * @param {object} obj
 *
 * @return {HTMLElement}  Detached DOM node.
 */
ZBX_Notification.prototype.makeNode = function(obj) {
	var node = document.createElement('li'),
		indicator = document.createElement('div'),
		title_node = document.createElement('h4');

	indicator.className = 'notif-indic ' + obj.severity_style;
	node.appendChild(indicator);

	title_node.innerHTML = BBCode.Parse(obj.title);
	node.appendChild(title_node);

	obj.body.forEach(function(line) {
		var p = document.createElement('p');
		p.innerHTML = BBCode.Parse(line);
		node.appendChild(p)
	});

	node.snooze_icon = document.createElement('div');
	node.snooze_icon.className = 'notif-indic-snooze';
	node.snooze_icon.style.opacity = 0;

	node.querySelector('.notif-indic').appendChild(node.snooze_icon)

	return node;
};

/**
 * @param {bool} bool  If true, mark notification as snoozed.
 */
ZBX_Notification.prototype.renderSnoozed = function(bool) {
	this.snoozed = bool;

	if (bool) {
		this.node.snooze_icon.style.opacity = 0.5;
	}
	else {
		this.node.snooze_icon.style.opacity = 0;
	}
};

/**
 * Remove notification from DOM.
 *
 * @param {number} ease  Amount for slide animation or disable.
 * @param {callable} cb  Closer to be called after remove.
 */
ZBX_Notification.prototype.remove = function(ease, cb) {
	var rate = 10;

	ease *= rate;

	if (ease > 0) {
		this.node.style.overflow = 'hidden';

		var t = ease / rate,
			step = this.node.offsetHeight / t,
			id = setInterval(function() {
				if (t < rate) {
					/*
					 * Since there is loaded prototype.js and it extends DOM's native 'remove' method, explicitly check
					 * if node is connected. Also, case of IE11 there is no 'isConnected' method.
					 */
					if (this.node.isConnected || this.node.parentNode) {
						this.node.remove();
						cb && cb();
					}
					clearInterval(id);
				}
				else {
					t -= rate;
					this.node.style.height = (step * t).toFixed() + 'px';
				}
			}.bind(this), rate);
	}
	else {
		this.node.remove();
		cb && cb();
	}
};
