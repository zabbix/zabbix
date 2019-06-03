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
 * Handles server response. Local timedouts object is maintained.
 *
 * @param {object} resp  Server response object.
 */
ZBX_Notifications.prototype.onPollerReceiveUpdates = function(resp) {
	var time_local = (+new Date / 1000),
		severity_settings = this.store.readKey('notifications.severity_settings'),
		stored_list = this.store.readKey('notifications.list'),
		stored_timeouts = this.store.readKey('notifications.localtimeouts'),
		snoozedids = this.store.readKey('notifications.snoozedids'),
		new_list = {},
		new_timeouts = {},
		alarm_severity = -2,
		ctn_snoozed = 0,
		alarm_notif = null;

	resp.notifications.forEach(function(notif) {
		if (stored_timeouts[notif.uid]) {
			if (time_local >= (stored_timeouts[notif.uid].recv_time + stored_timeouts[notif.uid].msg_timeout)) {
				return;
			}
			new_timeouts[notif.uid] = stored_timeouts[notif.uid];
		}
		else {
			new_timeouts[notif.uid] = {
				eventid: notif.eventid,
				recv_time: time_local,
				msg_timeout: notif.resolved
					? resp.settings.msg_recovery_timeout
					: resp.settings.msg_timeout
			};
		}

		if (notif.body) {
			new_list[notif.eventid] = notif;
		}
		else if (stored_list[notif.eventid]) {
			new_list[notif.eventid] = stored_list[notif.eventid];
			new_list[notif.eventid].resolved = notif.resolved;
			new_list[notif.eventid].uid = notif.uid;
		}
		else {
			return;
		}

		new_list[notif.eventid].snoozed = !! snoozedids[notif.eventid];

		ctn_snoozed += new_list[notif.eventid].snoozed;

		var notif_alarm_severity = new_list[notif.eventid].resolved ? -1 : new_list[notif.eventid].severity;
		if (!new_list[notif.eventid].snoozed && alarm_severity < notif_alarm_severity) {
			alarm_severity = notif_alarm_severity;
			alarm_notif = new_list[notif.eventid];
		}
	});

	this.writeAlarm(alarm_notif, resp.settings, new_timeouts);

	this.store.writeKey('notifications.list', new_list);
	this.onNotificationsList(new_list, new_timeouts, severity_settings);

	this.store.writeKey('notifications.localtimeouts', new_timeouts);
	this.store.writeKey('notifications.alarm.snoozed', ctn_snoozed == resp.notifications.length);

	this.onSnoozeChange(ctn_snoozed == resp.notifications.length);
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

		if (this.store.readKey('notifications.alarm.start') == notif.eventid) {
			this.store.writeKey('notifications.alarm.end', notif.eventid);
			this.player.stop();
		}

		this.store.mutateObject('notifications.localtimeouts', function(list_obj) {
			delete list_obj[notif.eventid];
		});

		this.store.mutateObject('notifications.list', function(list_obj) {
			delete list_obj[notif.eventid];
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
ZBX_Notifications.prototype.onNotificationsList = function(list_obj, timeouts_obj, severity_settings) {
	var timeouts_obj = timeouts_obj || this.store.readKey('notifications.localtimeouts'),
		severity_settings = severity_settings || this.store.readKey('notifications.severity_settings'),
		length = this.dom.renderFromStorable(list_obj, timeouts_obj, severity_settings);

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
		eventid;

	for (eventid  in list_obj) {
		snoozedids[eventid] = bool;
		list_obj[eventid].snoozed = bool;
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
 * @param {object|null} notif     Notification object in the format it was received from server.
 * @param {object}      opts      Notification settings object.
 * @param {object}      timeouts
 */
ZBX_Notifications.prototype.writeAlarm = function(notif, opts, timeouts_obj) {
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
		var timeout = timeouts_obj[notif.uid],
			time_local = (+new Date / 1000);
		this.store.writeKey('notifications.alarm.timeout', timeout.recv_time - time_local + timeout.msg_timeout);
	}
	// Play once till end of audio file.
	else if (opts.alarm_timeout == 1) {
		this.store.writeKey('notifications.alarm.timeout', -1);
	}
	// Play in loop till end of arbitrary timeout.
	else {
		this.store.writeKey('notifications.alarm.timeout', opts.alarm_timeout);
	}

	this.store.writeKey('notifications.alarm.wave', opts.files[notif.resolved ? -1 : notif.severity]);

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
	this.store.writeKey('notifications.severity_settings', {
		styles: settings.severity_styles,
		files: settings.files,
	});

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
 * On close click, send a request to server that marks current notifications as read.
 */
ZBX_Notifications.prototype.btnCloseClicked = function() {
	this.fetch('notifications.read', {ids: Object.keys(this.store.readKey('notifications.list'))})
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

	this.fetch('notifications.get', {
		validate_eventids: Object.keys(this.store.readKey('notifications.list'))
	})
		.catch(console.error)
		.then(this.onPollerReceive.bind(this));
};

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
