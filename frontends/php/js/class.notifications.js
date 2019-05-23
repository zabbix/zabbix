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
 * Default value in seconds, for poller interval.
 */
ZBX_Notifications.POLL_INTERVAL = 30;

/**
 * @param {ZBX_LocalStorage} store
 * @param {ZBX_BrowserTab} tab
 */
function ZBX_Notifications(store, tab) {
	if (!(store instanceof ZBX_LocalStorage) || !(tab instanceof ZBX_BrowserTab)) {
		throw 'Unmatched signature!';
	}

	this.disabled = false;
	this.player = new ZBX_NotificationsAudio();

	this.store = store;

	this.tab = tab;
	this.tab.onFocus(this.onTabFocus.bind(this));
	this.tab.onUnload(this.onTabUnload.bind(this));

	this.dom = new ZBX_NotificationCollection();
	this.dom.onTimeout = this.onNotifTimeout.bind(this);

	this.do_poll_server = false;

	this.dom.btn_close.onclick = this.btnCloseClicked.bind(this);
	this.dom.btn_snooze.onclick = this.btnSnoozeClicked.bind(this);
	this.dom.btn_mute.onclick = this.btnMuteClicked.bind(this);

	this.store.onUpdate(this.onStoreUpdate.bind(this));

	this.onSnoozeChange(this.store.readKey('notifications.alarm.snoozed'));
	this.onMuteChange(this.store.readKey('notifications.alarm.muted'));
	this.onNotificationsList(this.store.readKey('notifications.list'));
	this.onTabFocusChanged(this.store.readKey('tabs.lastfocused'));

	this.player.seek(this.store.readKey('notifications.alarm.seek'));
	this.player.file(this.store.readKey('notifications.alarm.wave'));

	this.player.onTimeout = this.onPlayerTimeout.bind(this);

	this.poll_interval = ZBX_Notifications.POLL_INTERVAL;
	this.restartMainLoop();

	/*
	 * Upon object creation, invoke tab.onFocus hook if tab was not yet opened in background. Re-stack exists
	 * because of IE11.
	 */
	setTimeout(function() {
		document.hasFocus() && this.onTabFocus(this.tab);
		this.mainLoop();
	}.bind(this), 0);
}

/**
 * Sets interval for main loop.
 */
ZBX_Notifications.prototype.restartMainLoop = function() {
	if (this.main_loop_id) {
		clearInterval(this.main_loop_id);
	}

	this.main_loop_id = setInterval(this.mainLoop.bind(this), this.poll_interval * 1000);
};

/**
 * Proxies and stores update event to various handlers.
 *
 * @param {string} key   The local storage key.
 * @param {mixed} value  That the key holds.
 */
ZBX_Notifications.prototype.onStoreUpdate = function(key, value) {
	switch (key) {
		case 'notifications.alarm.end':
		case 'notifications.alarm.start':
			this.renderPlayer();
			break;
		case 'notifications.alarm.wave':
			this.player.file(value);
			break;
		case 'notifications.alarm.seek':
			this.player.seek(value);
			break;
		case 'notifications.alarm.timeout':
			this.do_poll_server && this.player.timeout(value);
			break;
		case 'notifications.alarm.muted':
			this.onMuteChange(value);
			break;
		case 'notifications.alarm.snoozed':
			this.onSnoozeChange(value);
			break;
		case 'notifications.localtimeouts':
			this.onNotificationsList(this.store.readKey('notifications.list'), value);
			break;
		case 'notifications.list':
			this.onNotificationsList(value);
			break;
		case 'tabs.lastfocused':
			this.onTabFocusChanged(value);
			break;
		case 'notifications.disabled':
			this.disabled = value;
			value && this.onNotificationsList({});
			value && this.player.stop();
			break;
		case 'notifications.poll_interval':
			this.poll_interval = value;
			this.restartMainLoop();
			break;
	}
};

/**
 * Handles server response. Local timedouts object is maintained. If notificaion is not in server response,
 * it might be recovered if it should still be visible.
 *
 * @param {object} resp  Server response object.
 */
ZBX_Notifications.prototype.onPollerReceiveUpdates = function(resp) {
	var time_local = (+new Date / 1000),
		stored_list = this.store.readKey('notifications.list'),
		current_timeouts = this.store.readKey('notifications.localtimeouts'),
		recv_ids = [],
		list_obj = {},
		all_snoozed = false,
		notifid;

	resp.notifications.forEach(function(notif) {
		recv_ids.push(notif.uid);
		if (!current_timeouts[notif.uid]) {
			current_timeouts[notif.uid] = {recv_time: time_local, msg_timeout: resp.settings.msg_timeout}
		}
		else {
			current_timeouts[notif.uid].msg_timeout = resp.settings.msg_timeout;
		}
	});

	// Filter timedout.
	for (var id in current_timeouts) {
		if (current_timeouts[id].recv_time + current_timeouts[id].msg_timeout < time_local) {
			delete current_timeouts[id];
		}
	}

	// Unreceived notifs.
	Object.keys(current_timeouts).forEach(function(id) {
		if (recv_ids.indexOf(id) === -1) {
			resp.notifications.push(stored_list[id]);
		}
	});

	// Object from modified response array.
	resp.notifications.forEach(function(raw_notif) {
		var timeout = current_timeouts[id];

		list_obj[raw_notif.uid] = raw_notif;
		list_obj[raw_notif.uid].ttl = time_local - timeout.recv_time + timeout.msg_timeout;
	});

	all_snoozed = this.applySnoozeProp(resp.notifications),
	notifid = ZBX_Notifications.findNotificationToPlay(resp.notifications);

	this.writeAlarm(list_obj[notifid], resp.settings);

	this.store.writeKey('notifications.list', list_obj);
	this.onNotificationsList(list_obj, current_timeouts);

	this.store.writeKey('notifications.localtimeouts', current_timeouts);
	this.store.writeKey('notifications.alarm.snoozed', all_snoozed);

	this.onSnoozeChange(all_snoozed);
}

/**
 * Handles server response.
 *
 * @param {object} resp  Server response object.
 */
ZBX_Notifications.prototype.onPollerReceive = function(resp) {
	if (resp.error) {
		clearInterval(this.main_loop_id);
		return this.store.truncate();
	}

	this.disabled = !resp.settings.enabled;
	this.store.writeKey('notifications.disabled', this.disabled);

	if (this.disabled) {
		this.player.stop();
		this.onNotificationsList({});
		this.store.truncateBackup();
		this.store.truncate(function(key_variants) {
			return !! key_variants.key.match('^notifications\\.');
		});

		return;
	}

	this.writeSettings(resp.settings);
	if (this.store.readKey('notifications.listid') == resp.listid) {
		return;
	}

	this.store.writeKey('notifications.listid', resp.listid);
	this.onPollerReceiveUpdates(resp);
};

/**
 * Callback that is bound into notification and called by notification object when it has timed out. Check if that was
 * the last notification and update store.
 *
 * @param {ZBX_Notification} notif
 */
ZBX_Notifications.prototype.onNotifTimeout = function(notif) {
	notif.remove(ZBX_Notification.ease, function() {
		if (!this.dom.list_node.children.length) {
			this.dom.hide();
		}

		if (this.store.readKey('notifications.alarm.start') == notif.uid) {
			this.store.writeKey('notifications.alarm.end', notif.uid);
			this.player.stop();
		}

		this.store.mutateObject('notifications.localtimeouts', function(list_obj) {
			delete list_obj[notif.uid];
		});

		this.store.mutateObject('notifications.list', function(list_obj) {
			delete list_obj[notif.uid];
		});

	}.bind(this));
};

/**
 * Callback that is bound into player instance. Once player has reached timeout we update store to mark this
 * notification as played. This must only happen for playing/focused tab.
 */
ZBX_Notifications.prototype.onPlayerTimeout = function() {
	if (this.do_poll_server) {
		this.store.writeKey('notifications.alarm.end', this.store.readKey('notifications.alarm.start'));
	}
};

/**
 * Updates DOM on local storage change.
 *
 * @param {object} list_obj  Notification ID keyed hash-map of storable notification objects.
 * @param {object} timeouts_obj  Optional reference, if it is possible to read store less often.
 */
ZBX_Notifications.prototype.onNotificationsList = function(list_obj, timeouts_obj) {
	var timeouts_obj = timeouts_obj || this.store.readKey('notifications.localtimeouts'),
		length = this.dom.renderFromStorable(list_obj, timeouts_obj);

	if (length) {
		this.dom.node.hidden && this.dom.show();
	}
	else {
		!this.dom.node.hidden && this.dom.hide();
	}
};

/**
 * Sets snooze state, either snoozed on not. It is not allowed to de-snooze whole list.
 *
 * @param {bool} bool
 */
ZBX_Notifications.prototype.onSnoozeChange = function(bool) {
	this.dom.btn_snooze.renderState(bool);
	if (!bool) {
		return;
	}

	var list_obj = this.store.readKey('notifications.list'),
		snoozedids = {},
		id;

	for (id  in list_obj) {
		snoozedids[id] = bool;
		list_obj[id].snoozed = bool;
	}

	this.player.stop();
	this.store.writeKey('notifications.snoozedids', snoozedids);
	this.store.writeKey('notifications.list', list_obj);
	this.onNotificationsList(list_obj);
};

/**
 * Sets mute state, either muted on not.
 *
 * @param {bool} bool
 */
ZBX_Notifications.prototype.onMuteChange = function(bool) {
	this.dom.btn_mute.renderState(bool);
	bool && this.player.stop();
};

/**
 * On tab or window close we update local storage.
 *
 * @param {ZBX_BrowserTab} tab.
 */
ZBX_Notifications.prototype.onTabUnload = function(tab) {
	if (this.do_poll_server && !this.disabled) {
		this.store.writeKey('notifications.alarm.seek', this.player.getSeek());
		this.store.writeKey('notifications.alarm.timeout', this.player.getTimeout());
	}

	// If the last tab is unladed.
	if (!tab.getAllTabIds().length) {
		this.store.truncate();
	}
};

/**
 * Determine if this is the instance that will poll server.
 *
 * @param {string} tabid.
 */
ZBX_Notifications.prototype.onTabFocusChanged = function(tabid) {
	if (this.disabled) {
		return;
	}

	var active_blured = (this.do_poll_server && this.tab.uid != tabid);

	if (active_blured) {
		this.store.writeKey('notifications.alarm.seek', this.player.getSeek());
		this.store.writeKey('notifications.alarm.timeout', this.player.getTimeout());
		this.player.stop();
	}

	this.do_poll_server = (this.tab.uid === tabid);
};

/**
 * Bound as callback in ZBX_BrowserTab, and it just passes tab ID into storage key change handler.
 *
 * @param {ZBX_BrowserTab} tab.
 */
ZBX_Notifications.prototype.onTabFocus = function(tab) {
	this.onTabFocusChanged(tab.uid);
};

/**
 * Adjust alarm settings and update local storage to play a notification.
 *
 * @param {object|null} notif  Notification object in the format it was received from server.
 * @param {object}      opts   Notification settings object.
 */
ZBX_Notifications.prototype.writeAlarm = function(notif, opts) {
	if (!notif) {
		this.store.resetKey('notifications.alarm.start');
		this.store.resetKey('notifications.alarm.end');
		this.store.resetKey('notifications.alarm.seek');
		this.store.resetKey('notifications.alarm.wave');
		this.store.resetKey('notifications.alarm.timeout');
		return;
	}

	if (this.store.readKey('notifications.alarm.start') != notif.uid) {
		this.player.seek(0);
		this.store.resetKey('notifications.alarm.seek');
	}

	// Play in loop till end of notification timeout.
	if (opts.alarm_timeout == -1) {
		this.store.writeKey('notifications.alarm.timeout', notif.ttl);
	}
	// Play once till end of audio file.
	else if (opts.alarm_timeout == 1) {
		this.store.writeKey('notifications.alarm.timeout', -1);
	}
	// Play in loop till end of arbitrary timeout.
	else {
		this.store.writeKey('notifications.alarm.timeout', opts.alarm_timeout);
	}

	this.store.writeKey('notifications.alarm.wave', opts.files[notif.file]);

	// This write event is an trigger to play action.
	this.store.writeKey('notifications.alarm.start', notif.uid);

	/*
	 * Re-stack because in chrome the `alarm.start` key sometimes misbehaves, maybe it's because the next call reads
	 * this key too soon after the write call above.
	 */
	setTimeout(this.renderPlayer.bind(this), 0);
};

/**
 * @param {object} settings  Settings object received from server.
 */
ZBX_Notifications.prototype.writeSettings = function(settings) {
	this.store.writeKey('notifications.disabled', !settings.enabled);
	this.store.writeKey('notifications.alarm.muted', settings.muted);

	var min_timeout = Math.floor(settings.msg_timeout / 2);

	if (min_timeout < 1) {
		min_timeout = 1;
	}
	else if (min_timeout > ZBX_Notifications.POLL_INTERVAL) {
		min_timeout = ZBX_Notifications.POLL_INTERVAL;
	}

	if (this.poll_interval != min_timeout) {
		this.poll_interval = min_timeout;
		this.store.writeKey('notifications.poll_interval', this.poll_interval);
		this.restartMainLoop(this.poll_interval);
	}

	this.onMuteChange(settings.muted);
};

/**
 * Mutates list objects by setting an additional 'snoozed' property.
 *
 * @param {array} list  List of notifications received from server.
 *
 * @return {bool}  True in every notification in list matched with snoozed notifications.
 */
ZBX_Notifications.prototype.applySnoozeProp = function(list) {
	if (!(list instanceof Array)) {
		throw 'Expected array';
	}

	var snoozes = this.store.readKey('notifications.snoozedids'),
		is_all_snoozed = true;

	list.forEach(function(raw_notif) {
		if (snoozes[raw_notif.uid]) {
			raw_notif.snoozed = true;
		}
		else {
			raw_notif.snoozed = false;
			is_all_snoozed = false;
		}
	});

	return is_all_snoozed;
};

/**
 * On close click, send a request to server that marks current notifications as read.
 */
ZBX_Notifications.prototype.btnCloseClicked = function() {
	var params = {ids: []},
		list = this.store.readKey('notifications.list'),
		uid;

	for (uid in list) {
		params.ids.push(list[uid].id);
	}

	this.fetch('notifications.read', params)
		.catch(console.error)
		.then(function(resp) {
			this.store.resetKey('notifications.list');
			this.store.resetKey('notifications.localtimeouts');
			this.onNotificationsList({});

			this.store.resetKey('notifications.alarm.start');
			this.renderPlayer();
		}.bind(this));
};

/**
 * Marks whole list as snoozed. Updates local storage.
 */
ZBX_Notifications.prototype.btnSnoozeClicked = function() {
	if (this.store.readKey('notifications.alarm.snoozed')) {
		return;
	}

	this.store.writeKey('notifications.alarm.snoozed', true);
	this.onSnoozeChange(true);
};

/**
 * Toggles muted state on server. If successful, sets new muted value for all tabs.
 */
ZBX_Notifications.prototype.btnMuteClicked = function() {
	var new_value = this.store.readKey('notifications.alarm.muted') ? 0 : 1;

	this.fetch('notifications.mute', {mute: new_value})
		.catch(console.error)
		.then(function() {
			this.store.writeKey('notifications.alarm.muted', new_value);
			this.onMuteChange(new_value);
		}.bind(this));
};

/**
 * Updates player instance to match with local storage.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_Notifications.prototype.renderPlayer = function() {
	if (!this.do_poll_server) {
		return this.player.stop();
	}

	var start = this.store.readKey('notifications.alarm.start'),
		end = this.store.readKey('notifications.alarm.end');

	if (!start) {
		return this.player.stop();
	}
	else if (start == end) {
		return this.player.stop();
	}

	if (this.store.readKey('notifications.alarm.muted')) {
		return this.player.stop();
	}

	if (this.store.readKey('notifications.alarm.snoozed')) {
		return this.player.stop();
	}

	var wave = this.store.readKey('notifications.alarm.wave');

	if (wave) {
		this.player.file(wave);
	}

	this.player.timeout(this.store.readKey('notifications.alarm.timeout'));

	return this.player;
};

/**
 * @param {string} resource  A value for 'action' parameter.
 * @param {object} params    Form data to be send.
 *
 * @return {Promise}  For IE11 ZBX_Promise poly-fill is returned.
 */
ZBX_Notifications.prototype.fetch = function(resource, params) {
	return new Promise(function(resolve, reject) {
		sendAjaxData('zabbix.php?action=' + resource, {
			data: params || {},
			success: resolve,
			error: reject
		});
	});
};

/**
 * Main loop periodically executes at some interval. Only if this instance is 'active' notifications are fetched
 * and rendered.
 */
ZBX_Notifications.prototype.mainLoop = function() {
	if (!this.do_poll_server) {
		return;
	}

	this.fetch('notifications.get')
		.catch(console.error)
		.then(this.onPollerReceive.bind(this));
};

/**
 * Finds most severe, most recent, not-snoozed notification.
 *
 * List received from server reflects current notifications within timeout. To find a notification to play, filter
 * out any snoozed notifications. First sort by severity, then by timeout.
 *
 * @param list array  Notification objects in server provided format.
 *
 * @return string|null  Notification ID if it is found.
 */
ZBX_Notifications.findNotificationToPlay = function(list) {
	if (!list.length) {
		return null;
	}

	return list.reduce(function(acc, cur) {
		if (cur.snoozed) {
			return acc;
		}
		if (cur.priority > acc.priority) {
			return cur;
		}
		if (cur.priority == acc.priority && cur.ttl > acc.ttl) {
			return cur;
		}
		return acc;
	}, {
		uid: '',
		snoozed: true,
		priority: -1,
		ttl: -1
	}).uid;
}

/**
 * Registering instance.
 */
ZABBIX.namespace('instances.notifications', new ZBX_Notifications(
	ZABBIX.namespace('instances.localStorage'),
	ZABBIX.namespace('instances.browserTab')
));

jQuery(function() {
	var notifications_node = ZABBIX.namespace('instances.notifications.dom.node');

	document.body.appendChild(notifications_node);
	jQuery(notifications_node).draggable();
});
