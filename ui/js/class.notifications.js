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
 * Default value in seconds, for poller interval.
 */
ZBX_Notifications.POLL_INTERVAL = 30;

ZBX_Notifications.ALARM_SEVERITY_RESOLVED = -1;
ZBX_Notifications.ALARM_INFINITE_SERVER = -1;
ZBX_Notifications.ALARM_ONCE_PLAYER = -1;
ZBX_Notifications.ALARM_ONCE_SERVER = 1;

/**
 * Fetches and renders notifications. Server always returns full list of actual notifications that this class will
 * render into DOM. Last focused ZBX_BrowserTab instance is the active one. Active ZBX_BrowserTab instance is the only
 * one that polls server, meanwhile other instances are inactive. This is achieved by synchronizing state of active tab
 * via ZBX_LocalStorage and responding to it's change event.
 *
 * Only methods prefixed with <push> are the "action dispatchers", methods prefixed with <handlePushed> responds to
 * these "actions" by passing the new received state value through a method prefixed with <consume> that will adjust
 * instance's internal state, that in turn can be dispatched as "action". Other methods prefixed with <handle> responds
 * to other events than localStorage change event - (poll, focus, timeout..) and still they would reuse <consume>
 * domain methods and issue an action via <push> if needed and call to render method explicitly. The <handlePushed> is
 * not reused on the instance that produces the action. This is so to reduce complexity and increase maintainability,
 * because when an action produces an action, logic diverges deep, instead <consume> various domain within logic
 * and call `render` once, then <push> into localStorage once.
 *
 * Methods prefixed with <render> uses only consumed internal state and should not <push> any changes.
 *
 * @param {ZBX_LocalStorage} store
 * @param {ZBX_BrowserTab} tab
 */
function ZBX_Notifications(store, tab) {
	if (!(store instanceof ZBX_LocalStorage) || !(tab instanceof ZBX_BrowserTab)) {
		throw 'Unmatched signature!';
	}

	this.active = false;

	this.poll_interval = ZBX_Notifications.POLL_INTERVAL;

	this.store = store;
	this.tab = tab;

	this.collection = new ZBX_NotificationCollection();
	this.alarm = new ZBX_NotificationsAlarm(new ZBX_NotificationsAudio());

	this.fetchUpdates();

	this.consumeList(this._cached_list);
	this.consumeUserSettings(this._cached_user_settings);
	this.consumeAlarmState(this._cached_alarm_state);

	// Latest data page is being reloaded in background.
	var all_tabids = this.tab.getAllTabIds(),
		any_active_tab = (all_tabids.indexOf(this._cached_active_tabid) !== -1);

	// If pages are opened in background, and has never yet received focusIn event.
	if (!any_active_tab || document.hasFocus()) {
		this.becomeActive();
	}
	else {
		this.becomeInactive();
	}

	/*
	 * Fetched store is immediately rendered if this is not the only session, data as can be trusted then.
	 * Then if this is active instance it will always poll server once at construction, and then rerender if needed.
	 */
	if (all_tabids.length > 1) {
		this.render();
	}

	if (this.active) {
		this.pushUpdates();
	}
	this.restartMainLoop();

	this.bindEventHandlers();
}

/**
 * Binds to click events, LS update events and tab events.
 */
ZBX_Notifications.prototype.bindEventHandlers = function() {
	this.tab.onBeforeUnload(this.handleTabBeforeUnload.bind(this));
	this.tab.onFocus(this.handleTabFocusIn.bind(this));
	this.tab.onCrashed(this.handleTabFocusIn.bind(this));

	this.collection.btn_snooze.onclick = this.handleSnoozeClicked.bind(this);
	this.collection.btn_close.onclick = this.handleCloseClicked.bind(this);
	this.collection.btn_mute.onclick = this.handleMuteClicked.bind(this);

	this.store.onKeySync('notifications.active_tabid', this.handlePushedActiveTabid.bind(this));
	this.store.onKeySync('notifications.list', this.handlePushedList.bind(this));
	this.store.onKeySync('notifications.user_settings', this.handlePushedUserSettings.bind(this));
	this.store.onKeySync('notifications.alarm_state', this.handlePushedAlarmState.bind(this));

	this.alarm.onChange(this.handleAlarmStateChanged.bind(this));
};

/**
 * Reads all from store.
 */
ZBX_Notifications.prototype.fetchUpdates = function() {
	this._cached_list = this.store.readKey('notifications.list', []);
	this._cached_user_settings = this.store.readKey('notifications.user_settings', {
		msg_timeout: ZBX_Notifications.POLL_INTERVAL * 2
	});
	this._cached_active_tabid = this.store.readKey('notifications.active_tabid', '');
	this._cached_alarm_state = this.store.readKey('notifications.alarm_state', this.alarm.produce());
};

/**
 * @param {string} id
 */
ZBX_Notifications.prototype.removeById = function(id) {
	if (id.constructor != String) {
		id += '';
	}

	this.collection.removeById(id);
};

/**
 * @param {string} id
 *
 * @return {ZBX_Notification}
 */
ZBX_Notifications.prototype.getById = function(id) {
	return this.collection.getById(id);
};

/**
 * @param {object} alarm_state
 */
ZBX_Notifications.prototype.consumeAlarmState = function(alarm_state) {
	this.alarm.consume(alarm_state, this.getById(alarm_state.start));
};

/**
 * Used to speed up poll interval, in case if user has set message timeout to be short enough it is possible
 * to miss a recovered event for notification that is long gone, because of how Problems API is implemented.
 *
 * @param {objects} user_settings
 *
 * @return {integer}
 */
ZBX_Notifications.prototype.calcPollInterval = function(user_settings) {
	var min_timeout = Math.floor(user_settings.msg_timeout / 2);

	if (min_timeout < 1) {
		min_timeout = 1;
	}
	else if (min_timeout > ZBX_Notifications.POLL_INTERVAL) {
		min_timeout = ZBX_Notifications.POLL_INTERVAL;
	}

	return min_timeout;
};

/**
 * @param {objects} user_settings
 */
ZBX_Notifications.prototype.consumeUserSettings = function(user_settings) {
	var poll_interval = this.calcPollInterval(user_settings);
	if (this.poll_interval != poll_interval) {
		this.poll_interval = poll_interval;
		this._main_loop_id && this.restartMainLoop();
	}

	this._cached_user_settings = user_settings;

	if (user_settings.muted) {
		this.alarm.mute();
	}
	else {
		this.alarm.unmute();
	}

	if (this._cached_user_settings.disabled) {
		this.alarm.stop();
		this.pushAlarmState(this.alarm.produce());
		this.dropStore();
	}
};

/**
 * Consumes list into virtual DOM (collection). Computes and resets display timeouts for notification objects.
 * After display timeout collection is mutated and rendered. This loop is reused (acceptNotification) to choose
 * a notification to be played - most recent, most severe. Then it is written into alarm_state that once consumed,
 * will know if this notification has been played or not.
 *
 * @param {array} list  Ordered list of raw notification objects.
 */
ZBX_Notifications.prototype.consumeList = function(list) {
	this.collection.consumeList(list);
	this._cached_list = this.collection.getRawList();

	this.alarm.reset();
	this.collection.map(function(notif) {
		this.alarm.acceptNotification(notif);

		notif.display_timeoutid && clearTimeout(notif.display_timeoutid);
		notif.display_timeoutid = setTimeout(function() {
			this.removeById(notif.getId());
			this.debounceRender();
			this.pushUpdates();
		}.bind(this), notif.calcDisplayTimeout(this._cached_user_settings));
	}.bind(this));
};

/**
 * Stops ticking.
 */
ZBX_Notifications.prototype.stopMainLoop = function() {
	if (this._main_loop_id) {
		clearInterval(this._main_loop_id);
	}
};

/**
 * Sets interval for main loop. Tick is immediately executed.
 */
ZBX_Notifications.prototype.restartMainLoop = function() {
	this.stopMainLoop();
	this._main_loop_id = setInterval(this.mainLoop.bind(this), this.poll_interval * 1000);
	this.mainLoop();
};

/**
 * Invokes render once after some timeout if not called again during last timeout.
 *
 * @param {integer} ms  Optional milliseconds for debounce.
 */
ZBX_Notifications.prototype.debounceRender = function(ms) {
	ms = ms || 50;
	if (this._render_timeoutid) {
		clearTimeout(this._render_timeoutid);
	}

	this._render_timeoutid = setTimeout(this.render.bind(this), ms);
};

/**
 * Write ZBX_LocalStorage only values that were updated by <consume> methods. For example, during a new user_settings
 * consumption it came clear that alarm has to be updated, only if that happened, alarm will be pushed.
 */
ZBX_Notifications.prototype.pushUpdates = function() {
	if (this.active) {
		this.pushActiveTabid(this.tab.uid);
	}

	this.pushUserSettings(this._cached_user_settings);
	this.pushList(this.collection.getRawList());
	this.pushAlarmState(this.alarm.produce());
};

/**
 * @param {array} list
 */
ZBX_Notifications.prototype.pushList = function(list) {
	this.store.writeKey('notifications.list', list);
};

/**
 * @param {object} user_settings
 */
ZBX_Notifications.prototype.pushUserSettings = function(user_settings) {
	this.store.writeKey('notifications.user_settings', user_settings);
};

/**
 * @param {object} alarm
 */
ZBX_Notifications.prototype.pushAlarmState = function(alarm_state) {
	this.store.writeKey('notifications.alarm_state', alarm_state);
};

/**
 * @param {string} tabid
 */
ZBX_Notifications.prototype.pushActiveTabid = function(tabid) {
	this.store.writeKey('notifications.active_tabid', tabid);
};

/**
 * This logic is a response - if other instance writes this tabid into LS, when current tab receives focusIn event
 * or at new instance creation depending on context (for example single tab scenario without receiving focusIn event).
 */
ZBX_Notifications.prototype.becomeActive = function() {
	if (this.active) {
		return;
	}

	this._cached_active_tabid = this.tab.uid;
	this.active = true;

	this.pushActiveTabid(this.tab.uid);
	this.fetchUpdates();
	this.consumeAlarmState(this._cached_alarm_state);
	this.renderAudio();
};

/**
 * Notification instance may only ever become inactive when another instance becomes active. At single tab unload case
 * various artifacts like seek position are transferred explicitly.
 */
ZBX_Notifications.prototype.becomeInactive = function() {
	if (this.active) {
		// No need to push everything.
		this.pushAlarmState(this.alarm.produce());
	}

	this._cached_active_tabid = '';
	this.active = false;

	this.renderAudio();
};

/**
 * Backup store still remains, this is used mainly for single instance session case on tab unload event.
 */
ZBX_Notifications.prototype.dropStore = function() {
	this.store.eachKeyRegex('^notifications\\.', function(key) {
		key.truncatePrimary();
	});
};

/**
 * @param {object} user_settings
 */
ZBX_Notifications.prototype.handlePushedUserSettings = function(user_settings) {
	this.consumeUserSettings(user_settings);
	this.consumeList(this.collection.getRawList());
	this.render();
};

/**
 * @param {array} list
 */
ZBX_Notifications.prototype.handlePushedList = function(list) {
	this.consumeList(list);
	this.render();
};

/**
 * @param {object} alarm_state
 */
ZBX_Notifications.prototype.handlePushedAlarmState = function(alarm_state) {
	this.alarm.refresh();
	this.consumeAlarmState(alarm_state);
	this.render();
};

/**
 * @param {string} tabid
 */
ZBX_Notifications.prototype.handlePushedActiveTabid = function(tabid) {
	(tabid === this.tab.uid) ? this.becomeActive() : this.becomeInactive();
};

/**
 * When active tab is unloaded, any sibling tab is set to become active. If single session, then we drop LS (privacy).
 * We cannot know if this unload will happen because of navigation, scripted reload or a tab was just closed.
 * Latter is always assumed, so when navigating active tab, focus is deligated onto to any tab if possible,
 * then this tab might reclaim focus again at construction if during during that time document has focus.
 * At slow connection during page navigation there will be another active tab polling for notifications (if multitab).
 * Here `tab` is referred as ZBX_Notifications instance and `focus` - whether instance is `active` (not focused).
 *
 * @param {ZBX_BrowseTab} removed_tab  Current tab instance.
 * @param {array} other_tabids  List of alive tab ids (wuthout current tabid).
 */
ZBX_Notifications.prototype.handleTabBeforeUnload = function(removed_tab, other_tabids) {
	if (this.active && other_tabids.length) {
		this.pushActiveTabid(other_tabids[0]);
		this.becomeInactive();

		/*
		 * Solves problem happening in case when navigating to another top level domain. Chrome dispatches 'focusin'
		 * event right after beforeunload event. It is crucial to not to respond to that, otherwise nonexisting tab
		 * becomes active.
		 */
		this.becomeActive = function() {};
	}
	else if (this.active) {
		this.pushAlarmState(this.alarm.produce());
		this.dropStore();
	}
};

/**
 * Responds when this instance tab receives focus event.
 */
ZBX_Notifications.prototype.handleTabFocusIn = function() {
	this.becomeActive();
};

/**
 * @param {MouseEvent} e
 */
ZBX_Notifications.prototype.handleCloseClicked = function(e) {
	this.fetch('notifications.read', {ids: this.getEventIds()})
		.catch(console.error)
		.then(function(resp) {
			resp.ids.forEach(function(id) {
				this.removeById(id);
				this.debounceRender();
			}.bind(this));

			this.alarm.reset();
			this.pushUpdates();
		}.bind(this));
};

/**
 * @param {MouseEvent} e
 */
ZBX_Notifications.prototype.handleSnoozeClicked = function(e) {
	if (this.alarm.isSnoozed(this._cached_list)) {
		return;
	}

	this.collection.map(function(notif) {
		notif.updateRaw({snoozed: true});
	});

	this.consumeList(this.collection.getRawList());

	this.pushUpdates();
	this.render();
};

/**
 * @param {MouseEvent} e
 */
ZBX_Notifications.prototype.handleMuteClicked = function(e) {
	this.fetch('notifications.mute', {muted: this.alarm.muted ? 0 : 1})
		.catch(console.error)
		.then(function(resp) {
			this._cached_user_settings.muted = (resp.muted == 1);
			this.alarm.consume({muted: this._cached_user_settings.muted});
			this.pushUpdates();
			this.render();
		}.bind(this));
};

/**
 * Handles server response.
 *
 * @param {object} resp  Server response object. Contains settings and list of notifications.
 */
ZBX_Notifications.prototype.handleMainLoopResp = function(resp) {
	if (resp.error) {
		this.stopMainLoop();
		this.store.truncateBackup();
		this.dropStore();

		return;
	}

	this.consumeUserSettings(resp.settings);
	this.consumeList(resp.notifications);
	this.render();

	this.pushUpdates();
};

/**
 * @param {ZBX_NotificationsAlarm} alarm_state
 */
ZBX_Notifications.prototype.handleAlarmStateChanged = function(alarm_state) {
	this.pushAlarmState(alarm_state.produce());
};


/**
 * Collection renders whole list of notifications and snooze, and mute buttons, not all state is passed down here, just
 * user configuration, the list state to be rendered, has been consumed by collection before.
 */
ZBX_Notifications.prototype.renderCollection = function() {
	this.collection.render(this._cached_user_settings.severity_styles, this.alarm);
};

/**
 * Render everything. Any painting optimization may be considered levels deeper.
 */
ZBX_Notifications.prototype.render = function() {
	this.renderCollection();
	this.renderAudio();
};

/**
 * Alarm is stopped for inactive instance.
 */
ZBX_Notifications.prototype.renderAudio = function() {
	if (this.active) {
		this.alarm.render(this._cached_user_settings, this._cached_list);
	}
	else {
		this.alarm.stop();
	}
};

/**
 * @param {string} resource  A value for 'action' parameter.
 * @param {object} params    Form data to be sent.
 *
 * @return {Promise}
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
 * @return {array}
 */
ZBX_Notifications.prototype.getEventIds = function() {
	return this.collection.getIds();
};

/**
 * Main loop periodically executes at some interval. Only if this instance is 'active' notifications are fetched
 * and rendered.
 */
ZBX_Notifications.prototype.mainLoop = function() {
	if (!this.active) {
		return;
	}

	this.fetch('notifications.get', {known_eventids: this.getEventIds()})
		.catch(console.error)
		.then(this.handleMainLoopResp.bind(this));
};

/**
 * Utilities.
 */
ZBX_Notifications.util = {};

/**
 * @param {Node} node  Display none node.
 *
 * @return {Promise}
 */
ZBX_Notifications.util.getNodeHeight = function(node) {
	node.style.display = 'block';
	node.style.position = 'absolute';
	node.style.visibility = 'hidden';
	node.style.overflow = 'hidden';

	return new Promise(function(resolve, failed) {
		function readHeight() {
			if (!node.offsetHeight) {
				requestAnimationFrame(readHeight);
			}
			else {
				node.removeAttribute('style');
				resolve(node.offsetHeight);
			}
		}

		readHeight();
	});
};

/**
 * Fully IE11 compatible slideUp animation using CSS.
 *
 * @param {Node} node
 * @param {integer} duration  Animation duration in milliseconds.
 * @param {integer} delay  Milliseconds to wait before animating.
 *
 * @return {Promise}  Resolved once animation should have finished.
 */
ZBX_Notifications.util.slideDown = function(node, duration, delay) {
	delay = delay || 0;
	duration = duration || 200;

	return new Promise(function(resolved, failed) {
		ZBX_Notifications.util.getNodeHeight(node).then(function(height) {
			var padding = window.getComputedStyle(node).padding;

			node.style.height = '0px';
			node.style.padding = '0px';
			node.style.overflow = 'hidden';
			node.style.boxSizing = 'border-box';
			node.style.transitionDuration = duration + 'ms';
			node.style.transitionProperty = 'opacity, height, margin, padding';

			setTimeout(function() {
				node.style.height = height + 'px';
				node.style.padding = padding;
				setTimeout(function() {
					node.removeAttribute('style');
				}, duration);
				resolved(node);
			}, delay);
		});
	});
};

/**
 * @param {Node} node
 */
ZBX_Notifications.util.fadeIn = function(node) {
	node.style.opacity = 0;
	node.style.display = 'inherit';

	var op = 0;
	var id = setInterval(function() {
		op += 0.1;
		if (op > 1) {
			return clearInterval(id);
		}
		node.style.opacity = op;
	}, 50);
};

/**
 * @param {Node} node
 *
 * @return {Promise}  Resolved once animation should have finished.
 */
ZBX_Notifications.util.fadeOut = function(node) {
	var opacity = 1,
		intervalid;

	return new Promise(function(resolved, failed) {
		if (node.style.display === 'none') {
			return resolved(node);
		}

		node.style.opacity = opacity;
		intervalid = setInterval(function() {
			opacity -= 0.1;
			if (opacity < 0) {
				node.style.display = 'none';

				resolved(node);
				return clearInterval(intervalid);
			}
			node.style.opacity = opacity;
		}, 50);
	});
};

/**
 * Fully IE11 compatible slideUp animation using CSS.
 *
 * @param {Node} node
 * @param {integer} duration  Animation duration in milliseconds.
 * @param {integer} delay  Milliseconds to wait before animating.
 *
 * @return {Promise}  Resolved once animation should have finished.
 */
ZBX_Notifications.util.slideUp = function(node, duration, delay) {
	delay = delay || 0;

	node.style.overflow = 'hidden';
	node.style.boxSizing = 'border-box';
	node.style.transitionDuration = duration + 'ms';
	node.style.transitionProperty = 'height, margin, padding';
	node.style.height = node.offsetHeight + 'px';

	setTimeout(function() {
		node.style.height = '0px';
		node.style.padding = '0px';
		node.style.margin = '0px';
	}, delay);

	return new Promise(function(resolved, failed) {
		setTimeout(resolved.bind(null, node), delay + duration);
	});
};


/**
 * @param {ZBX_NotificationsAudio} player
 */
function ZBX_NotificationsAlarm(player) {
	this.player = player;

	this.severity = -2;
	this.start = '';
	this.end = '';
	this.timeout = 0;
	this.muted = true;
	this.notif = null;
	this.on_changed_cbs = [];

	this.old_id = this.getId();
}

/**
 * An alarm is identified by notification and it's current severity.
 *
 * @return {string}
 */
ZBX_NotificationsAlarm.prototype.getId = function() {
	if (this.notif) {
		return this.notif.getId() + '_' + this.severity;
	}

	return '';
};

/**
 * Invokes callbacks.
 */
ZBX_NotificationsAlarm.prototype.dispatchChanged = function() {
	this.on_changed_cbs.forEach(function(callback) {
		callback(this);
	}.bind(this));
};

/**
 * This mechanism exists to prevent or explicitly allow seek position to be applied at render.
 */
ZBX_NotificationsAlarm.prototype.refresh = function() {
	this.old_id = '';
};

/**
 * Subscribes a callback.
 */
ZBX_NotificationsAlarm.prototype.onChange = function(callback) {
	this.on_changed_cbs.push(callback);
};

/**
 * Calculated property.
 */
ZBX_NotificationsAlarm.prototype.markAsPlayed = function() {
	this.end = this.getId();
};

/**
 * @return {bool}
 */
ZBX_NotificationsAlarm.prototype.isPlayed = function() {
	return (this.getId() === this.end);
};

/**
 * @param {array} list  List of raw notifications.
 *
 * @return {bool}
 */
ZBX_NotificationsAlarm.prototype.isSnoozed = function(list) {
	for (var i = 0; i < list.length; i++) {
		if (!list[i].snoozed) {
			return false;
		}
	}

	return (list.length == 0) ? false : true;
};

/**
 * @return {bool}
 */
ZBX_NotificationsAlarm.prototype.isStopped = function() {
	return !this.getId();
};

/**
 * @param {object} alarm_state
 * @param {ZBX_Notification} notif
 */
ZBX_NotificationsAlarm.prototype.consume = function(alarm_state, notif) {
	if (notif) {
		this.notif = notif;
	}

	for (var field in alarm_state) {
		this[field] = alarm_state[field];
	}
};

/**
 * Does not update state, just renders player stopped.
 */
ZBX_NotificationsAlarm.prototype.stop = function() {
	this.notif = null;
	this.player.stop();
};

ZBX_NotificationsAlarm.prototype.mute = function() {
	this.muted = true;
	this.player.mute();
};

ZBX_NotificationsAlarm.prototype.unmute = function() {
	this.muted = false;
	this.player.unmute();
};

/**
 * @param {object} user_settings
 * @param {array} list  List of raw notification objects.
 */
ZBX_NotificationsAlarm.prototype.render = function(user_settings, list) {
	user_settings.muted ? this.mute() : this.unmute();

	if (this.isStopped() || this.isPlayed() || this.isSnoozed(list)) {
		return this.player.stop();
	}

	this.player.file(user_settings.files[this.severity]);

	if (this.old_id !== this.getId()) {
		this.player.seek(0);
		this.player.stop();
	}

	this.player.tune({
		playOnce: (this.calcTimeout(user_settings) == ZBX_Notifications.ALARM_ONCE_PLAYER),
		messageTimeout: (this.notif.calcDisplayTimeout(user_settings) / 1000) >> 0,
		callback: function() {
			this.markAsPlayed();
			this.dispatchChanged();
		}.bind(this)
	});

	this.player.timeout(this.calcTimeout(user_settings));
	this.old_id = this.getId();
};

/**
 * @param {object} user_settings
 *
 * @return {integer}
 */
ZBX_NotificationsAlarm.prototype.calcTimeout = function(user_settings) {
	if (user_settings.alarm_timeout == ZBX_Notifications.ALARM_INFINITE_SERVER) {
		return (this.notif.calcDisplayTimeout(user_settings) / 1000) >> 0;
	}

	if (user_settings.alarm_timeout == ZBX_Notifications.ALARM_ONCE_SERVER) {
		return ZBX_Notifications.ALARM_ONCE_PLAYER;
	}

	if (this.timeout == 0) {
		return user_settings.alarm_timeout;
	}

	return this.timeout;
};

/**
 * @return {object}
 */
ZBX_NotificationsAlarm.prototype.produce = function() {
	return {
		start: this.start,
		end: this.end,
		muted: this.muted,
		severity: this.severity,
		seek: this.player.getSeek(),
		timeout: this.player.getTimeout(),
		supported: !! this.player.audio
	};
};

/*
 * Resets crucial fields to accept notifications.
 */
ZBX_NotificationsAlarm.prototype.reset = function() {
	this.old_id = this.getId();
	this.start = '';
	this.severity = -2;
	this.notif = null;
};

/**
 * Appends notification to state in context.
 *
 * @param {ZBX_Notification} notif
 */
ZBX_NotificationsAlarm.prototype.acceptNotification = function(notif) {
	var raw = notif.getRaw(),
		severity = raw.resolved ? ZBX_Notifications.ALARM_SEVERITY_RESOLVED : raw.severity;

	if (raw.snoozed) {
		return;
	}

	if (this.severity < severity) {
		this.severity = severity;
		this.notif = notif;
		this.start = notif.getId();
	}
};

/**
 * Registering instance.
 */
ZABBIX.namespace('instances.notifications', new ZBX_Notifications(
	ZABBIX.namespace('instances.localStorage'),
	ZABBIX.namespace('instances.browserTab')
));

/**
 * Appends list node to DOM when document is ready, then make it draggable.
 */
$(function() {
	let wrapper = document.querySelector(".wrapper"),
		main = document.querySelector("main"),
		ntf_node = ZABBIX.namespace('instances.notifications.collection.node'),
		store = ZABBIX.namespace('instances.localStorage'),
		ntf_pos = store.readKey('web.notifications.pos', null),
		pos_top = 10,
		pos_side = 10,
		side = 'right';

	main.appendChild(ntf_node);

	if (ntf_pos !== null && 'top' in ntf_pos) {
		side = ('right' in ntf_pos ? 'right' : ('left' in ntf_pos ? 'left' : null));
		if (side !== null) {
			pos_top = Math.max(-main.offsetTop, Math.min(ntf_pos.top, wrapper.scrollHeight - ntf_node.offsetHeight));
			pos_side = Math.max(0, Math.min(ntf_pos[side], Math.floor(wrapper.scrollWidth - ntf_node.offsetWidth) / 2));
		}
	}

	ntf_node.style.top = pos_top + 'px';
	ntf_node.style[side] = pos_side + 'px';

	$(ntf_node).draggable({handle: '>.dashboard-widget-head',
		start: function(event, ui) {
			ui.helper.data('containment', {
				min_top: -main.offsetTop,
				max_top: wrapper.scrollHeight - this.offsetHeight - main.offsetTop,
				min_left: 0,
				max_left: wrapper.scrollWidth - this.offsetWidth
			});
		},
		drag: function(event, ui) {
			let containment = ui.helper.data('containment');

			ui.position.top = Math.max(Math.min(ui.position.top, containment.max_top), containment.min_top);
			ui.position.left = Math.max(Math.min(ui.position.left, containment.max_left), containment.min_left);
		},
		stop: function(event, ui) {
			ntf_pos = {top: ui.position.top};

			if (ui.position.left < (wrapper.scrollWidth - this.offsetWidth) / 2) {
				ntf_pos.left = ui.position.left;
				this.style.right = null;
			}
			else {
				ntf_pos.right = wrapper.scrollWidth - this.offsetWidth - ui.position.left;
				this.style.left = null;
				this.style.right = ntf_pos.right + 'px';
			}

			store.writeKey('web.notifications.pos', ntf_pos);
		}
	});
});
