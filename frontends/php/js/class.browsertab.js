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


ZBX_BrowserTab.DEBUG_DEBOUNCE = 0;
ZBX_BrowserTab.DEBUG_GRPS = [];
ZBX_BrowserTab.DEBUG_GRP = function(log) {
	clearTimeout(ZBX_BrowserTab.DEBUG_DEBOUNCE);
	ZBX_BrowserTab.DEBUG_GRPS.push(log);
	ZBX_BrowserTab.DEBUG_DEBOUNCE = setTimeout(function() {
		var d = new Date();
		var time = ("00" + d.getHours()).slice(-2) + ":" +
		("00" + d.getMinutes()).slice(-2) + ":" +
		("00" + d.getSeconds()).slice(-2);

		console.groupCollapsed("%cBT: " + time + ' [' + ZBX_BrowserTab.DEBUG_GRPS.length + ']', 'color:gold');
		ZBX_BrowserTab.DEBUG_GRPS.forEach(function(log) {
			console.groupCollapsed.apply(console, log.title);
			log.args.forEach(function(arg) {
				console.dir(arg)
			});

			console.groupEnd();
		});

		ZBX_BrowserTab.DEBUG_GRPS = [];

		console.groupEnd();
	}, 100);
};

ZBX_BrowserTab.DEBUG = function() {
	return;
	if (IE) return;
	var stack = new Error().stack;
	trace = stack.split('\n');
	var pos = trace[2].match('at (.*) .*')[1];

	var style = 'color:red;';

	if (pos.match('BrowserTab\\.') || pos.match('BrowserTab')) {
		style = 'color:darkgoldenrod;';
	}

	if (pos.match('LocakStorage\\.') || pos.match('LocakStorage$')) {
		style = 'color:darkkhaki;';
	}

	// if (pos.match('^new ')) {
	// 	style += 'background:black;font-size:14px';
	// }
	// // if (trace.length > 6) return;

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
			(a instanceof ZBX_BrowserTab) ||
			(a instanceof ZBX_Notification) ||
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
		// log.args.push(stack);
	}

	ZBX_BrowserTab.DEBUG_GRP(log);
};


/**
 * Amount of seconds for keep-alive interval.
 */
ZBX_BrowserTab.keep_alive_interval = 30;

/**
 * This object is representing a browser tab. Implements singleton pattern. It ensures there are only non-crashed tabs
 * in store.
 *
 * @param {ZBX_LocalStorage} store  A localStorage wrapper.
 */
function ZBX_BrowserTab(store) {
	if (ZBX_BrowserTab.instance) {
		return ZBX_BrowserTab.instance;
	}
	ZBX_BrowserTab.DEBUG();

	if (!(store instanceof ZBX_LocalStorage)) {
		throw 'Unmatched signature!';
	}

	ZBX_BrowserTab.instance = this;

	this.uid = (Math.random() % 9e6).toString(36).substr(2);
	this.store = store;

	var ctx_lastseen = this.store.readKey('tabs.lastseen', {});
	ctx_lastseen[this.uid] = Math.floor(+new Date / 1000);

	this.lastseen = ctx_lastseen;

	this.on_focus_cbs = [];
	this.on_blur_cbs = [];
	this.on_before_unload_cbs = [];
	this.on_crashed_cbs = [];

	this.bindEventHandlers();
	this.pushLastseen();
}

/**
 * @param {object} lastseen
 */
ZBX_BrowserTab.prototype.handlePushedLastseen = function(lastseen) {
	ZBX_BrowserTab.DEBUG(lastseen);

	this.lastseen = lastseen;
};

/**
 * Checks and updates current lastseen object.
 */
ZBX_BrowserTab.prototype.handleKeepAliveTick = function() {
	this.lastseen[this.uid] = Math.floor(+new Date / 1000);

	this.findCrashedTabs().forEach(function(tabid) {
		delete this.lastseen[tabid];
		this.handleCrashed(tabid);
	}.bind(this));

	this.pushLastseen();
};

/**
 * Writes own ID in `store.tabs` object. Registers unload event to remove own ID from `store.tabs.lastseen`.
 * Registers focus event. Begins a loop to see if any tab of tabs has crashed.
 */
ZBX_BrowserTab.prototype.bindEventHandlers = function() {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());

	setInterval(this.handleKeepAliveTick.bind(this), ZBX_BrowserTab.keep_alive_interval * 1000);

	// If beforeunload event is used, it is dispatched twice if navigating across domain in chrome, because unload event.
	window.addEventListener('unload', this.handleUnload.bind(this));
	window.addEventListener('focus', this.handleFocus.bind(this));
	this.store.onKeyUpdate('tabs.lastseen', this.handlePushedLastseen.bind(this));
};

/**
 * @return {array} List of IDs of crashed tabs.
 */
ZBX_BrowserTab.prototype.findCrashedTabs = function() {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());

	var since = this.lastseen[this.uid] - (ZBX_BrowserTab.keep_alive_interval - 1) * 2,
		crashed_tabids = [];

	for (var tabid in this.lastseen) {
		if (this.lastseen[tabid] < since) {
			crashed_tabids.push(tabid);
		}
	}

	return crashed_tabids;
};

/**
 * Gives all tab ids.
 *
 * @return {array}
 */
ZBX_BrowserTab.prototype.getAllTabIds = function() {
	return Object.keys(this.lastseen);
};

/**
 * @param {string} tabid  The crashed tab ID.
 */
ZBX_BrowserTab.prototype.handleCrashed = function(tabid) {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());
	this.on_crashed_cbs.forEach(function(c) {c(this);}.bind(this));
};

/**
 * @param {callable} callback
 */
ZBX_BrowserTab.prototype.onFocus = function(callback) {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());
	this.on_focus_cbs.push(callback);
};

/**
 * @param {callable} callback
 */
ZBX_BrowserTab.prototype.onCrashed = function(callback) {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());
	this.on_crashed_cbs.push(callback);
};

/**
 * @param {callable} callback
 */
ZBX_BrowserTab.prototype.onUnload = function(callback) {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());
	this.on_before_unload_cbs.push(callback);
};

/**
 * @param {FocusEvent} e
 */
ZBX_BrowserTab.prototype.handleFocus = function(e) {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());
	this.on_focus_cbs.forEach(function(c) {c(this);}.bind(this));
};

/**
 * @param {UnloadEvent} e
 */
ZBX_BrowserTab.prototype.handleUnload = function(e) {
	ZBX_BrowserTab.DEBUG(this.getAllTabIds());

	delete this.lastseen[this.uid];

	this.on_before_unload_cbs.forEach(function(c) {c(this, this.getAllTabIds());}.bind(this));
	this.pushLastseen();

	window.removeEventListener('unload', this.handleUnload.bind(this));
	window.removeEventListener('focus', this.handleFocus.bind(this));
};

/**
 * Writes to store.
 */
ZBX_BrowserTab.prototype.pushLastseen = function() {
	this.store.writeKey('tabs.lastseen', this.lastseen);
};

ZABBIX.namespace('instances.browserTab', new ZBX_BrowserTab(
	ZABBIX.namespace('instances.localStorage')
));
