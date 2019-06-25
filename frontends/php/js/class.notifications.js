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

ZBX_Notifications.ALARM_SEVERITY_RESOLVED = -1;
ZBX_Notifications.ALARM_INFINITE_SERVER = -1;
ZBX_Notifications.ALARM_ONCE_PLAYER = 1;
ZBX_Notifications.ALARM_ONCE_SERVER = -1;

ZBX_Notifications.DEBUG_DEBOUNCE = 0;
ZBX_Notifications.DEBUG_GRPS = [];
ZBX_Notifications.DEBUG_GRP = function(log) {
	clearTimeout(ZBX_Notifications.DEBUG_DEBOUNCE);
	ZBX_Notifications.DEBUG_GRPS.push(log);
	ZBX_Notifications.DEBUG_DEBOUNCE = setTimeout(function() {
		var d = new Date();
		var time = ("00" + d.getHours()).slice(-2) + ":" +
		("00" + d.getMinutes()).slice(-2) + ":" +
		("00" + d.getSeconds()).slice(-2);

		console.groupCollapsed("%cNOTIF: " + time + ' [' + ZBX_Notifications.DEBUG_GRPS.length + ']', 'color:cadetblue');
		ZBX_Notifications.DEBUG_GRPS.forEach(function(log) {
			console.groupCollapsed.apply(console, log.title);
			log.args.forEach(function(arg) {
				console.dir(arg)
			});

			console.groupEnd();
		});

		ZBX_Notifications.DEBUG_GRPS = [];

		console.groupEnd();
	}, 100);
};

ZBX_Notifications.DEBUG = function() {
	// return
	if (IE) return;
	if (ZBX_Notifications.DEBUG.halt) {
		!ZBX_Notifications.DEBUG.halted && console.warn("debug halt")
		ZBX_Notifications.DEBUG.halted = 1
		return;
	}
	var stack = new Error().stack;
	trace = stack.split('\n');
	var pos = trace[2].match('at (.*) .*')[1];

	var style = 'color:red;';

	if (pos.match('\\.handlePushed')) {
		// console.info('%c<< ' + pos, 'color:red');
	}

	if (pos.match('\\.consume')) {
		// console.info('%c[!]' + pos, 'color:gold');
	}

	if (pos.match('\\.push')) {
		// console.info('%c>> ' + pos, 'color:green');
	}

	if (pos.match('Collection\\.') || pos.match('Collection$')) {
		style = 'color:darkgoldenrod;';
	}

	if (pos.match('Notification\\.') || pos.match('Notification$')) {
		style = 'color:darkkhaki;';
	}

	if (pos.match('Notifications\\.') || pos.match('Notifications$')) {
		style = 'color:crimson;';
	}

	if (pos.match('Audio\\.') || pos.match('Audio$')) {
		style = 'color:antiquewhite;';
	}

	if (pos.match('^new ')) {
		style += 'background:black;font-size:14px';
	}
	// if (trace.length > 6) return;

	var log = {
		title: ['-'.repeat(trace.length) + '%c' + pos + ' [' + (arguments.length) + ']', style],
		args: [],
	}

	var len = arguments.length;
	for (var i = 0; i < len; i ++) {
		var a = arguments[i];

		if (typeof a === 'string' && a[0] === ':') {
			log.title[0] += '%c' + a;
			log.title.push('color: white;');
			continue;
		}


		if (
			(a instanceof ZBX_Notifications) ||
			(a instanceof ZBX_Notification) ||
			(a instanceof ZBX_BrowserTab) ||
			(a instanceof ZBX_NotificationsAudio) ||
			(a instanceof ZBX_NotificationCollection) ||
			(a instanceof ZBX_LocalStorage)
		) {
			log.args.push(a);
		}
		else if (typeof a === 'object' && a !== null) {
			try {
				log.args.push(JSON.parse(Object.toJSON(a)));
			} catch (e) {
				log.args.push("FAIL");
				console.warn("FAIL", log, a, e);
			}
		}
		else {
			log.args.push(a);
		}
	}

	ZBX_Notifications.DEBUG_GRP(log);
};

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
 * domain methods and issue an anction via <push> if needed and call to render method explicitly. The <handlePushed> is
 * not reused on the instance that produces the action. This is so to reduce complexity and increase maintainebility,
 * because when an action produces an action, logic diverges deep, instead <consume> various domain within logic
 * and call `render` once, then <push> into localStorage once.
 *
 * Methods prefixed with <render> uses only consumed internal state and should not <push> any changes.
 *
 * @param {ZBX_LocalStorage} store
 * @param {ZBX_BrowserTab} tab
 */
function ZBX_Notifications(store, tab) {
	N = this;
	ZBX_Notifications.DEBUG(store, tab);

	if (!(store instanceof ZBX_LocalStorage) || !(tab instanceof ZBX_BrowserTab)) {
		throw 'Unmatched signature!';
	}

	this.active = false;

	this.poll_interval = ZBX_Notifications.POLL_INTERVAL;

	this.store = store;
	this.tab = tab;

	this.player = new ZBX_NotificationsAudio();
	this.collection = new ZBX_NotificationCollection();

	this.bindEventHandlers();

	this.fetchUpdates();
	this.consumeList(this.list);
	this.consumeUserSettings(this.user_settings);
	this.consumeAlarmState(this.alarm_state);

	// Latest data page is being reloaded in background.
	var all_tabids = this.tab.getAllTabIds(),
		any_active_tab = (all_tabids.indexOf(this.active_tabid) !== -1);

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

	this.pushUpdates();
	this.restartMainLoop();
}

/**
 * Binds to click events, LS update events and tab events.
 */
ZBX_Notifications.prototype.bindEventHandlers = function() {
	ZBX_Notifications.DEBUG();

	this.tab.onUnload(this.handleTabUnload.bind(this));
	this.tab.onFocus(this.handleTabFocusIn.bind(this));
	this.tab.onCrashed(this.handleTabFocusIn.bind(this));

	this.collection.btn_snooze.onclick = this.handleSnoozeClicked.bind(this);
	this.collection.btn_close.onclick = this.handleCloseClicked.bind(this);
	this.collection.btn_mute.onclick = this.handleMuteClicked.bind(this);

	this.store.onKeyUpdate('notifications.active_tabid', this.handlePushedActiveTabid.bind(this));
	this.store.onKeyUpdate('notifications.list', this.handlePushedList.bind(this));
	this.store.onKeyUpdate('notifications.user_settings', this.handlePushedUserSettings.bind(this));
	this.store.onKeyUpdate('notifications.alarm_state', this.handlePushedAlarmState.bind(this));
};

/**
 * Reads all from store.
 */
ZBX_Notifications.prototype.fetchUpdates = function() {
	var default_alarm_state = {
		severity: -2,
		start: '',
		end: '',
		seek: 0,
		timeout: 0,
		muted: true,
		snoozed: false
	};

	this.list = this.store.readKey('notifications.list', []);
	this.user_settings = this.store.readKey('notifications.user_settings', {});
	this.active_tabid = this.store.readKey('notifications.active_tabid', '');
	this.alarm_state = this.store.readKey('notifications.alarm_state', default_alarm_state);
};

/**
 * This does not bother for this.list property as it is not used for render or push.
 *
 * @param {string} id
 */
ZBX_Notifications.prototype.removeById = function(id) {
	ZBX_Notifications.DEBUG(id);

	this.collection.removeById(id);
};

/**
 * @param {string} id
 *
 * @return {ZBX_Notification}
 */
ZBX_Notifications.prototype.getById = function(id) {
	ZBX_Notifications.DEBUG(id);

	return this.collection.getById(id);
};

/**
 * TODO very complex, uncomprehensible method...
 * TODO maybe this primitive object `alarm_state` should be wrapped because it looks like contains behaviour.
 *
 * @param {object} alarm_state
 */
ZBX_Notifications.prototype.consumeAlarmState = function(alarm_state) {
	ZBX_Notifications.DEBUG(alarm_state);

	// Nothing to play.
	if (!alarm_state.start) {
		alarm_state.timeout = 0;
	}
	// Playback has happened.
	else if (alarm_state.start + '_' + alarm_state.severity === alarm_state.end) {
		alarm_state.timeout = 0;
	}
	// Regardless if this is new/unitialized playback or not, recalculate this user setting.
	else if (this.user_settings.alarm_timeout == ZBX_Notifications.ALARM_INFINITE_SERVER) {
		var display_timeout = this.getById(alarm_state.start).calcDisplayTimeout(this.user_settings);
		alarm_state.timeout = parseInt(display_timeout / 1000);
	}
	// New playback.
	else if (alarm_state.timeout == 0) {
		// Player instance will know length of timeout, only then it will pass actual time into alarm_state.
		if (this.user_settings.alarm_timeout == ZBX_Notifications.ALARM_ONCE_SERVER) {
			// TODO these values are flipped - server side should have user settings -2 for msg_timeout and -1 for once
			alarm_state.timeout = ZBX_Notifications.ALARM_ONCE_PLAYER;
		}
		// New playback has arbitrary user setting timeout (most likely 10 seconds)
		else {
			alarm_state.timeout = this.user_settings.alarm_timeout;
		}
	}
	else {
		// Timeout cycles through LS across tabs.
	}

	ZBX_Notifications.DEBUG(alarm_state);
	this.alarm_state = alarm_state;

	// DEBUG-T
	if (ZBX_Notifications._playin) { try { ZBX_Notifications._playin.node._playin.remove(); } catch(e){} }
	if (this.alarm_state.start) {
		if (this.getById(this.alarm_state.start)) {
			ZBX_Notifications._playin = this.getById(this.alarm_state.start);
			ZBX_Notifications._playin.node._playin = document.createElement('span');
			ZBX_Notifications._playin.node._playin.style.float = 'right';
			ZBX_Notifications._playin.node._playin.style.marginTop = '-43px';
			ZBX_Notifications._playin.node.appendChild(ZBX_Notifications._playin.node._playin);
			var file = this.user_settings.files[this.alarm_state.severity];
			var pf = '(' + this.user_settings.alarm_timeout + ')🔊';
			ZBX_Notifications._playin.node._playin.innerHTML = file + ' ' + pf;
		}
	}
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
	ZBX_Notifications.DEBUG(user_settings);

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
	ZBX_Notifications.DEBUG(user_settings);

	var poll_interval = this.calcPollInterval(user_settings);
	if (this.poll_interval != poll_interval) {
		this.poll_interval = poll_interval;
		this._main_loop_id && this.restartMainLoop();
	}

	this.user_settings = user_settings;

	if (this.user_settings.disabled) {
		// this.resetAlarmState();
		// this.pushAlarmState();
		// this.dropStore();
	}
};

/**
 * Appends notification to alarm_state context.
 *
 * @param {ZBX_Notification} notif
 */
ZBX_Notifications.prototype.consumeNotifAlarmCtx = function(notif) {
	ZBX_Notifications.DEBUG(notif);

	var raw = notif.getRaw(),
		severity = raw.resolved ? ZBX_Notifications.ALARM_SEVERITY_RESOLVED : raw.severity,
		notifid = notif.getId();

	if (this.alarm_state.snoozed && !raw.snoozed) {
		this.alarm_state.snoozed = false;
	}

	if (this.alarm_state.severity < severity) {
		this.alarm_state.severity = severity;
		this.alarm_state.start = notifid;
	}
};

/**
 * Consumes list into virtual DOM (collection). Computes and resets display timeouts for notification objects.
 * After display timeout collection is mutated and rendered. This loop is reused (consumeNotifAlarmCtx) to choose
 * a notification to be played - most recent, most severe. Then it is written into alarm_state that once consumed,
 * will know if this notification has been played or not.
 *
 * @param {array} list  Ordered list of raw notification objects.
 */
ZBX_Notifications.prototype.consumeList = function(list) {
	ZBX_Notifications.DEBUG(list);

	this.collection.consumeList(list);
	this.list = this.collection.getRawList();

	/*
	 * These values are zeroed out for case of empty list. The same notification id is computed again if lesser priority
	 * notification resolves, while consuming this new list.
	 */
	this.alarm_state.start = '';
	this.alarm_state.severity = -2;

	this.collection.map(function(notif) {
		this.consumeNotifAlarmCtx(notif);

		notif.display_timeoutid && clearTimeout(notif.display_timeoutid);
		notif.display_timeoutid = setTimeout(function() {
			this.removeById(notif.getId());
			this.debounceRender();
		}.bind(this), notif.calcDisplayTimeout(this.user_settings));

		// DEBUG-T
		var ttld = (notif.calcDisplayTimeout(this.user_settings) / 1000).toFixed();
		if (notif.__) {
			clearTimeout(notif.__);
		}
		if (!notif.node._id) {
			notif.node._id = document.createElement('span');
			notif.node._id.style.float = 'right';
			notif.node._id.style.marginTop = '-30px';
			notif.node._id.style.fontSize = '9px';
			notif.node.appendChild(notif.node._id);
		}
		notif.node._id.innerHTML = 'ID: ' + notif.getId();

		if (!notif.node._timer) {
			notif.node._timer = document.createElement('span');
			notif.node._timer.style.float = 'right';
			notif.node._timer.style.marginTop = '-57px';
			notif.node.appendChild(notif.node._timer);
		}
		notif.__ = setInterval(function() {
			notif.node._timer.innerHTML = ttld --;
		}.bind(this), 1000);
		// DEBUG-T

	}.bind(this));

	this.consumeAlarmState(this.alarm_state);
};

/**
 * Stops ticking.
 */
ZBX_Notifications.prototype.stopMainLoop = function() {
	ZBX_Notifications.DEBUG();

	if (this._main_loop_id) {
		clearInterval(this._main_loop_id);
	}
};

/**
 * Sets interval for main loop. Tick is immediately executed.
 */
ZBX_Notifications.prototype.restartMainLoop = function() {
	ZBX_Notifications.DEBUG(':this.poll_interval: ' + this.poll_interval);

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

	ZBX_Notifications.DEBUG(ms);
};

/**
 * TODO below is {WIP}
 *
 * Write ZBX_LocalStorage only values that were updated by <consume> methods. For example, during a new user_settings
 * consumption it came clear that alarm_state has to be updated, only if that happened, alarm_state will be pushed.
 */
ZBX_Notifications.prototype.pushUpdates = function() {
	ZBX_Notifications.DEBUG();

	if (this.active) {
		this.pushActiveTabid(this.tab.uid);
	}

	// TODO optimize - not always needed to write them, if they not changed.
	// TODO in consume<domain> methods construct a "change object", based on that push only needded stuff.
	this.pushUserSettings(this.user_settings);
	this.pushList(this.collection.getRawList());
	this.pushAlarmState(this.alarm_state);
};

/**
 * @param {array} list
 */
ZBX_Notifications.prototype.pushList = function(list) {
	ZBX_Notifications.DEBUG(list);

	this.store.writeKey('notifications.list', list);
};

/**
 * @param {object} user_settings
 */
ZBX_Notifications.prototype.pushUserSettings = function(user_settings) {
	ZBX_Notifications.DEBUG(user_settings);

	this.store.writeKey('notifications.user_settings', user_settings);
};

/**
 * @param {object} alarm_state
 */
ZBX_Notifications.prototype.pushAlarmState = function(alarm_state) {
	ZBX_Notifications.DEBUG(alarm_state);

	this.store.writeKey('notifications.alarm_state', alarm_state);
};

/**
 * @param {string} tabid
 */
ZBX_Notifications.prototype.pushActiveTabid = function(tabid) {
	ZBX_Notifications.DEBUG(tabid);

	this.store.writeKey('notifications.active_tabid', tabid);
};

/**
 * This logic is a response - if other instance writes this tabid into LS, when current tab receives focusIn event
 * or at new instance creation depending on context (for example single tab scenario without receiving focusIn event).
 */
ZBX_Notifications.prototype.becomeActive = function() {
	document.title = "Act";
	document.title += (+new Date) - 1560000000000;

	if (this.active) {
		return;
	}

	ZBX_Notifications.DEBUG();

	this.active_tabid = this.tab.uid;
	this.active = true;

	this.pushActiveTabid(this.tab.uid);
	this.renderAudio();
};

/**
 * Notification instance may only ever become inactive when another instance becomes active. At single tab unload case
 * various artifacts like seek position are transfered explicitly.
 *
 * (TODO is it possible to just call this method at unload).
 */
ZBX_Notifications.prototype.becomeInactive = function() {
	document.title = "Ina";
	document.title += (+new Date) - 1560000000000;

	ZBX_Notifications.DEBUG();

	if (this.active) {
		// No need to push everything.
		this.alarm_state = this.getAlarmState();
		this.consumeAlarmState(this.alarm_state);
		this.pushAlarmState(this.alarm_state);
	}

	this.active_tabid = '';
	this.active = false;

	this.renderAudio();
};

/**
 * Collects how alarm_state looks at this time (during playback).
 *
 * @return {object}
 */
ZBX_Notifications.prototype.getAlarmState = function() {
	ZBX_Notifications.DEBUG();

	return {
		start: this.alarm_state.start,
		end: this.alarm_state.end,
		muted: this.alarm_state.muted,
		snoozed: this.alarm_state.snoozed,
		severity: this.alarm_state.severity,
		seek: this.player.getSeek(),
		timeout: this.player.getTimeout()
	};
};

/**
 * Backup store still remains, this is used mainly for single instance session case on tab unload event.
 */
ZBX_Notifications.prototype.dropStore = function() {
	ZBX_Notifications.DEBUG();

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
	ZBX_Notifications.DEBUG(alarm_state);

	this.consumeAlarmState(alarm_state);
	this.render();
};

/**
 * @param {string} tabid
 */
ZBX_Notifications.prototype.handlePushedActiveTabid = function(tabid) {
	tabid === this.tab.uid ? this.becomeActive() : this.becomeInactive();
};

/**
 * When active tab is unloaded, any sibling tab is set to become active. If single session, then we drop LS (privacy).
 * We cannot know if this unload will happen because of navigation, scripted reload or a tab was just closed.
 * Latter is always assumed, so when navigating active tab, focus is deligated onto to any tab if possible,
 * then this tab might reclaim focus again at construction if during during that time document has focus.
 * At slow connection during page navigation there will be another active tab polling for notifications (if multitab).
 * Here `tab` is refered as ZBX_Notifications instance and `focus` - wheather instance is `active` (not focused).
 *
 * @param {ZBX_BrowseTab} removed_tab  Current tab instance.
 * @param {array} other_tabids  List of alive tab ids (wuthout current tabid).
 */
ZBX_Notifications.prototype.handleTabUnload = function(removed_tab, other_tabids) {
	ZBX_Notifications.DEBUG(removed_tab, other_tabids);

	if (this.active && other_tabids.length) {
		this.becomeInactive();
		this.pushActiveTabid(other_tabids[0]);
	}
	else if (this.active) {
		this.dropStore();
	}
};

/**
 * Responds when this instance tab receives focus event.
 */
ZBX_Notifications.prototype.handleTabFocusIn = function() {
	ZBX_Notifications.DEBUG();

	this.becomeActive();
};

/**
 * @param {MouseEvent} e
 */
ZBX_Notifications.prototype.handleCloseClicked = function(e) {
	ZBX_Notifications.DEBUG();

	this.fetch('notifications.read', {ids: this.getEventIds()})
		.catch(console.error)
		.then(function(resp) {

			resp.ids.forEach(function(id) {
				this.removeById(id);
				this.debounceRender();
			}.bind(this));

			this.alarm_state.start = '';
			this.pushUpdates();
		}.bind(this));
};

/**
 * @param {MouseEvent} e
 */
ZBX_Notifications.prototype.handleSnoozeClicked = function(e) {
	ZBX_Notifications.DEBUG();

	if (this.alarm_state.snoozed) {
		return;
	}

	this.alarm_state.snoozed = true;
	this.consumeAlarmState(this.alarm_state);

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
	ZBX_Notifications.DEBUG();

	this.fetch('notifications.mute', {mute: this.alarm_state.muted ? 0 : 1})
		.catch(console.error)
		.then(function(resp) {

			this.alarm_state.muted = resp.mute;
			this.consumeAlarmState(this.alarm_state);

			this.pushAlarmState(this.alarm_state);
			this.render();

		}.bind(this));
};

/**
 * Handles server response.
 *
 * @param {object} resp  Server response object. Contains settings and list of notifications.
 */
ZBX_Notifications.prototype.handleMainLoopResp = function(resp) {
	ZBX_Notifications.DEBUG(resp);

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
 * Collection renders whole list of notifications and snooze, and mute buttons, not all state is passed down here, just
 * user configuration, the list state to be rendered, has been consumed by collection before.
 */
ZBX_Notifications.prototype.renderCollection = function() {
	ZBX_Notifications.DEBUG();

	this.collection.render(this.user_settings.severity_styles, this.alarm_state);
};

/**
 * Render everything. Any painting optimization may be considered levels deeper.
 */
ZBX_Notifications.prototype.render = function() {
	ZBX_Notifications.DEBUG();

	this.renderCollection();
	this.renderAudio();
};

/**
 * Updates player instance to match with this.alarm_state.
 *
 * @return {Promise}  Resolved once playback has reached timeout (only during playback in action).
 */
ZBX_Notifications.prototype.renderAudio = function() {
	ZBX_Notifications.DEBUG();

	var playback_ended = !this.alarm_state.start || (this.alarm_state.start === this.alarm_state.end),
		playback_suppressed = this.alarm_state.muted || this.alarm_state.snoozed;

	if (!this.active || playback_ended || playback_suppressed) {
		// return this.player.fadeStop(100);
		return this.player.stop();
	}

	var file = this.user_settings.files[this.alarm_state.severity];

	this.player.file(file);
	this.player.seek(this.alarm_state.seek);

	if (this.alarm_state.timeout == ZBX_Notifications.ALARM_ONCE_PLAYER) {
		this.alarm_state.end = this.alarm_state.start + '_' + this.alarm_state.severity;
		this.player.once();
	}
	else {
		this.player.timeout(this.alarm_state.timeout).then(function(player) {
			/*
			 * It is not checked again here if this bound instance is active or not, because if it isn't active, player has been
			 * rerendered as stopped and this promise is never resolved.
			 */
			this.alarm_state.end = this.alarm_state.start + '_' + this.alarm_state.severity;
			this.pushAlarmState(this.alarm_state);
		}.bind(this));
	}

};

/**
 * @param {string} resource  A value for 'action' parameter.
 * @param {object} params    Form data to be send.
 *
 * @return {Promise}  For IE11 ZBX_Promise poly-fill is returned.
 */
ZBX_Notifications.prototype.fetch = function(resource, params) {
	ZBX_Notifications.DEBUG(resource, params);

	// TODO the sendAjaxData will call a `success` method for you on network error! Not in the scope of this task.
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
	ZBX_Notifications.DEBUG();

	return this.collection.getIds();
};

/**
 * Main loop periodically executes at some interval. Only if this instance is 'active' notifications are fetched
 * and rendered.
 */
ZBX_Notifications.prototype.mainLoop = function() {
	ZBX_Notifications.DEBUG('this.poll_interval: ' + this.poll_interval, 'active: ' + this.active);

	if (!this.active) {
		return;
	}

	this.fetch('notifications.get', {known_eventids: this.getEventIds()})
		.catch(console.error)
		.then(this.handleMainLoopResp.bind(this));
};

/**
 * Utilities..
 *
 * TODO could these be removed if we use scss and some class toggling?
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
 * Registering instance.
 */
ZABBIX.namespace('instances.notifications', new ZBX_Notifications(
	ZABBIX.namespace('instances.localStorage'),
	ZABBIX.namespace('instances.browserTab')
));

/**
 * Appends list node to DOM when document is ready, then makes it draggable.
 */
jQuery(function() {
	var notifications_node = ZABBIX.namespace('instances.notifications.collection.node');

	document.body.appendChild(notifications_node);
	jQuery(notifications_node).draggable();
});
