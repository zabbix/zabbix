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


ZBX_LocalStorage.DEBUG_DEBOUNCE = 0;
ZBX_LocalStorage.DEBUG_GRPS = [];
ZBX_LocalStorage.DEBUG_GRP = function(log) {
	clearTimeout(ZBX_LocalStorage.DEBUG_DEBOUNCE);
	ZBX_LocalStorage.DEBUG_GRPS.push(log);
	ZBX_LocalStorage.DEBUG_DEBOUNCE = setTimeout(function() {
		var d = new Date();
		var time = ("00" + d.getHours()).slice(-2) + ":" +
		("00" + d.getMinutes()).slice(-2) + ":" +
		("00" + d.getSeconds()).slice(-2);

		console.groupCollapsed("%cLS/BT: " + time + ' [' + ZBX_LocalStorage.DEBUG_GRPS.length + ']', 'color:grey');
		ZBX_LocalStorage.DEBUG_GRPS.forEach(function(log) {
			console.groupCollapsed.apply(console, log.title);
			log.args.forEach(function(arg) {
				console.dir(arg)
			});

			console.groupEnd();
		});

		ZBX_LocalStorage.DEBUG_GRPS = [];

		console.groupEnd();
	}, 100);
};

ZBX_LocalStorage.DEBUG = function() {
	return;
	if (IE) return;
	// return
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
			(a instanceof ZBX_LocalStorage) ||
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

	ZBX_LocalStorage.DEBUG_GRP(log);
};

ZBX_LocalStorage.defines = {
	PREFIX_SEPARATOR: ':',
	KEEP_ALIVE_INTERVAL: 30,
	KEY_SESSIONS: 'sessions',
	EVT_CHANGE: 1,
	EVT_MAP: 2
};

/**
 * Local storage wrapper. Implements singleton.
 *
 * @param {string} version  Mandatory parameter.
 * @param {string} prefix   Used to distinct keys between sessions within same domain.
 */
function ZBX_LocalStorage(version, prefix) {
	ZBX_LocalStorage.DEBUG(version, prefix);

	if (!version || !prefix) {
		throw 'Local storage instantiation must be versioned, and prefixed.';
	}

	if (ZBX_LocalStorage.instance) {
		return ZBX_LocalStorage.instance;
	}

	ZBX_LocalStorage.master = localStorage;
	ZBX_LocalStorage.slave = sessionStorage;

	ZBX_LocalStorage.sessionid = prefix;
	ZBX_LocalStorage.prefix = prefix + ZBX_LocalStorage.defines.PREFIX_SEPARATOR;
	ZBX_LocalStorage.instance = this;
	ZBX_LocalStorage.signature = (Math.random() % 9e6).toString(36).substr(2);

	this.rel_keys = {};
	this.abs_keys = {};

	this.addKey('version');
	this.addKey('tabs.lastseen');
	this.addKey('notifications.list');
	this.addKey('notifications.active_tabid');
	this.addKey('notifications.user_settings');
	this.addKey('notifications.alarm_state');

	if (this.readKey('version') != version) {
		this.truncate();
		this.writeKey('version', version);
	}

	this.keepAlive();
	setInterval(this.keepAlive, ZBX_LocalStorage.defines.KEEP_ALIVE_INTERVAL * 1000);

	this.register();
}

/**
 * @return {string}
 */
ZBX_LocalStorage.prototype.onKeyUpdate = function(key, callback) {
	this.rel_keys[key].subscribe(callback);
};


/**
 * Transform key into absolute key.
 *
 * @param {string} key
 *
 * @return {string}
 */
ZBX_LocalStorage.prototype.toAbsKey = function(key) {
	return ZBX_LocalStorage.prefix + key;
};

/**
 * @param {string} relative_key
 */
ZBX_LocalStorage.prototype.addKey = function(relative_key) {
	var absolute_key = this.toAbsKey(relative_key);

	this.rel_keys[relative_key] = new ZBX_LocalStorageKey(relative_key, absolute_key);
	this.abs_keys[absolute_key] = this.rel_keys[relative_key];

	// DEBUG
	var properties = {};
	var that = this;
	var kk = '_' + relative_key.replace('.', '_').replace('.', '_')
	properties[kk] = properties[relative_key] = {
		set: function(k) {
			return function(value) {
				return that.writeKey(k, value);
			}
		}(relative_key),

		get: function(k) {
			return function() {
				return that.readKey(k);
			}
		}(relative_key)
	}
	Object.defineProperties(this, properties);
};

/**
 * TODO test after this refactoring.
 *
 * Keeps alive local storage sessions. Removes inactive session.
 */
ZBX_LocalStorage.prototype.keepAlive = function() {
	ZBX_LocalStorage.DEBUG();
	var timestamp = Math.floor(+new Date / 1000),
		sessions = JSON.parse(localStorage.getItem(ZBX_LocalStorage.defines.KEY_SESSIONS) || '{}'),
		alive_ids = [],
		expired_timestamp = timestamp - 2 * ZBX_LocalStorage.defines.KEEP_ALIVE_INTERVAL,
		id,
		i;

	for (id in sessions) {
		if (sessions[id] < expired_timestamp) {
			delete sessions[id];
		}
		else {
			alive_ids.push(id);
		}
	}

	for (i = 0; i < localStorage.length; i++) {
		var pts = localStorage.key(i).split(ZBX_LocalStorage.defines.PREFIX_SEPARATOR);
		if (pts.length < 2) {
			continue;
		}
		if (alive_ids.indexOf(pts[0]) == -1) {
			localStorage.removeItem(localStorage.key(i));
		}
	}

	sessions[ZBX_LocalStorage.sessionid] = timestamp;
	localStorage.setItem(ZBX_LocalStorage.defines.KEY_SESSIONS, ZBX_LocalStorage.stringify(sessions));
};

/**
 * Callback gets passed a reference of object under this key. The reference then is written back into local storage.
 *
 * @param {string} key
 * @param {callable} callback
 */
ZBX_LocalStorage.prototype.mutateObject = function(key, callback) {
	ZBX_LocalStorage.DEBUG(key, callback);
	var obj = this.readKey(key);

	callback(obj);
	this.writeKey(key, obj);
};

/**
 * @param {RegEx|string} regex
 * @param {callback} callback
 */
ZBX_LocalStorage.prototype.eachKeyRegex = function(regex, callback) {
	this.eachKey(function(key) {
		key.relative_key.match(regex) && callback(key);
	});
};

/**
 * @param {callback}
 */
ZBX_LocalStorage.prototype.eachKey = function(callback) {
	for (var i in this.rel_keys) {
		callback(this.rel_keys[i]);
	}
};

/**
 * Validates if key is used by this version of localStorage.
 *
 * @param {string} key  Key to test.
 *
 * @return {bool}
 */
ZBX_LocalStorage.prototype.hasKey = function(key) {
	return typeof this.rel_keys[key] !== 'undefined';
};

/**
 * Alias to throw error on invalid key access.
 *
 * @param {string} key  Key to test.
 */
ZBX_LocalStorage.prototype.ensureKey = function(key) {
	// ZBX_LocalStorage.DEBUG(key);

	if (typeof key !== 'string') {
		throw 'Key must be a string, ' + (typeof key) + ' given instead.';
	}

	if (!this.hasKey(key)) {
		throw 'Unknown localStorage key access at "' + key + '"';
	}
};

/**
 * We have our own `stringify` because Prototype.js defines Array.prototype.toJSON method. Which then is invoked
 * by native `JSON.stringify` method, producing unexpected results when serializing an array object. Since Prototype.js
 * itself depends on it's implementation, it is decided to not delete `Array.prototype.toJSON` field as it would not be
 * safe. Prototype.js provides `Object.prototype.toJSON` method we could proxy through.
 *
 * @param {mixed} value
 *
 * @return {string} Valid JSON string.
 */
ZBX_LocalStorage.stringify = function(value) {
	return window.Prototype ? Object.toJSON(value) : JSON.stringify(value);
}

/**
 * This whole design of signed payloads exists only because of IE11.
 *
 * @param {mixed} value
 *
 * @return {string}
 */
ZBX_LocalStorage.prototype.wrap = function(value) {
	return ZBX_LocalStorage.stringify({
		payload: value,
		signature: ZBX_LocalStorage.signature
	});
};

/**
 * Not only IE dispatches 'storage' event 'onwrite' instead of 'onchange', but this event is also dispatched onto
 * window that is the modifier. So we need to sign all payloads.
 *
 * @param {string} value
 *
 * @return {mixed}
 */
ZBX_LocalStorage.prototype.unwrap = function(value) {
	if (value === null) {
		throw "Expected JSON string";
	}

	return JSON.parse(value);
};

/**
 * After this method call local storage will not perform any further writes.
 */
ZBX_LocalStorage.prototype.destruct = function() {
	this.writeKey = function() {};
	this.truncate();
	this.truncateBackup();
};

/**
 * Backup keys are removed.
 */
ZBX_LocalStorage.prototype.truncateBackup = function() {
	this.eachKey(function(key) {
		key.truncateBackup();
	});
};

/**
 * Removes all local storage and creates default objects. Backup keys are not removed.
 *
 * @param {callable} filter_cb  Optional callback which return true if key should be removed.
 */
ZBX_LocalStorage.prototype.truncate = function(filter_cb) {
	this.eachKey(function(key) {
		key.truncate();
	});
};

ZBX_LocalStorage.prototype.register = function() {
	window.addEventListener('storage', function(event) {
		// This key is for internal use only.
		// TODO can be made as one of keys
		if (event.key === ZBX_LocalStorage.defines.KEY_SESSIONS) {
			return;
		}

		this.handleStorageEvent(event);
	}.bind(this));
};

ZBX_LocalStorage.prototype.handleStorageEvent = function(event) {
	if (event.constructor != StorageEvent) {
		throw 'Unmatched method signature!';
	}

	// This means, storage has been truncated.
	if (event.key === null || event.key === '') {
		// return this.mapCallback(callback);
		return;
	}

	if (event.newValue === event.oldValue) {
		// This is expensive, but internet expoler just dispatched storage change event at write.
		return;
	}

	var value, key;

	try {
		value = this.unwrap(event.newValue);
	}
	catch(e) {
		// If value could not be unwraped, it has not originated from this class.
		return;
	}

	if (value.signature === ZBX_LocalStorage.signature) {
		// Internet expoler just dispatched storage event onto current instance.
		return;
	}

	if (!this.abs_keys[event.key]) {
		return;
	}

	this.abs_keys[event.key].publish(value.payload);
};

/**
 * Writes an underlaying value.
 *
 * @param {string} key
 * @param {string} value
 */
ZBX_LocalStorage.prototype.writeKey = function(key, value) {
	if (typeof value === 'undefined') {
		throw 'Value may not be undefined, use null instead: ' + key;
	}

	this.ensureKey(key);
	this.rel_keys[key].write(this.wrap(value));
};

/**
 * Fetches underlaying value. A copy of default value is returned if key has no data.
 *
 * @param {string} key
 *
 * @return {mixed}
 */
ZBX_LocalStorage.prototype.readKey = function(key, fallback) {
	this.ensureKey(key);

	try {
		var item = this.rel_keys[key].fetch();

		if (!item) {
			return fallback;
		}

		return this.unwrap(item).payload;
	}
	catch(e) {
		console.warn('failed to parse storage item "' + key + '"');

		this.truncate();
		this.truncateBackup();
	}

	return null;
};

function ZBX_LocalStorageKey(relative_key, absolute_key) {
	this.backup_store = sessionStorage;
	this.primary_store = localStorage;

	this.relative_key = relative_key;
	this.absolute_key = ZBX_LocalStorage.prefix + this.relative_key;

	this.on_update_cbs = [];
}

ZBX_LocalStorageKey.prototype.writePrimary = function(string) {
	if (string.constructor != String || string[0] != '{') {
		throw 'Corrupted input.';
	}
	this.primary_store.setItem(this.absolute_key, string);
};

ZBX_LocalStorageKey.prototype.writeBackup = function(string) {
	if (string.constructor != String || string[0] != '{') {
		throw 'Corrupted input.';
	}
	this.backup_store.setItem(this.absolute_key, string);
};

ZBX_LocalStorageKey.prototype.write = function(string) {
	this.writeBackup(string);
	this.writePrimary(string);
};

ZBX_LocalStorageKey.prototype.fetchPrimary = function() {
	return this.primary_store.getItem(this.absolute_key);
};

ZBX_LocalStorageKey.prototype.fetchBackup = function() {
	return this.backup_store.getItem(this.absolute_key);
};

ZBX_LocalStorageKey.prototype.fetch = function() {
	return this.fetchPrimary() || this.fetchBackup();
};

ZBX_LocalStorageKey.prototype.truncateBackup = function() {
	this.backup_store.removeItem(this.absolute_key);
};

ZBX_LocalStorageKey.prototype.truncatePrimary = function() {
	this.primary_store.removeItem(this.absolute_key);
};

ZBX_LocalStorageKey.prototype.truncate = function() {
	this.truncateBackup();
	this.truncatePrimary();
};

ZBX_LocalStorageKey.prototype.subscribe = function(callback) {
	this.on_update_cbs.push(callback);
};

ZBX_LocalStorageKey.prototype.publish = function(payload) {
	this.on_update_cbs.forEach(function(callback) {
		callback(payload);
	});
};

ZABBIX.namespace(
	'instances.localStorage',
	new ZBX_LocalStorage('1', cookie.read('localstoragePath'))
);
