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


ZBX_LocalStorage.defines = {
	PREFIX_SEPARATOR: ':',
	KEEP_ALIVE_INTERVAL: 30,
	SYNC_INTERVAL_MS: 500,
	KEY_SESSIONS: 'sessions',
	KEY_LAST_WRITE: 'key_last_write'
};

/**
 * Local storage wrapper. Implements singleton.
 *
 * @param {string} version  Mandatory parameter.
 * @param {string} prefix   Used to distinct keys between sessions within same domain.
 */
function ZBX_LocalStorage(version, prefix) {
	if (!version || !prefix) {
		throw 'Local storage instantiation must be versioned, and prefixed.';
	}

	if (ZBX_LocalStorage.instance) {
		return ZBX_LocalStorage.instance;
	}

	ZBX_LocalStorage.sessionid = prefix;
	ZBX_LocalStorage.prefix = prefix + ZBX_LocalStorage.defines.PREFIX_SEPARATOR;
	ZBX_LocalStorage.instance = this;
	ZBX_LocalStorage.signature = (Math.random() % 9e6).toString(36).substr(2);

	this.abs_to_rel_keymap = {};
	this.key_last_write = {};
	this.rel_keys = {};
	this.abs_keys = {};

	this.addKey('version');
	this.addKey('tabs.lastseen');
	this.addKey('notifications.list');
	this.addKey('notifications.active_tabid');
	this.addKey('notifications.user_settings');
	this.addKey('notifications.alarm_state');
	this.addKey('dashboard.copied_widget');
	this.addKey('web.notifications.pos');

	if (this.readKey('version') != version) {
		this.truncate();
		this.writeKey('version', version);
	}

	this.register();
}

/**
 * @param {string} key  Relative key.
 * @param {callable} callback  When out of sync is observed, then new value is read, and passed into callback.
 *
 * @return {string}
 */
ZBX_LocalStorage.prototype.onKeySync = function(key, callback) {
	this.rel_keys[key].subscribeSync(callback);
};

/**
 * @param {string} key  Relative key.
 * @param {callable} callback  Executed on storage event for this key.
 *
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

	this.rel_keys[relative_key] = new ZBX_LocalStorageKey(relative_key);
	this.abs_keys[absolute_key] = this.rel_keys[relative_key];
	this.abs_to_rel_keymap[absolute_key] = relative_key;
};

/**
 * @param {Store} store
 * @param {string} sessionid
 */
ZBX_LocalStorage.prototype.freeSession = function(store, sessionid) {
	var len = store.length,
		matches = [],
		abs_key;

	for (var i = 0; i < len; i++) {
		abs_key = store.key(i);
		if (abs_key.match('^' + sessionid)) {
			matches.push(abs_key);
		}
	}

	matches.forEach(function(abs_key) {
		store.removeItem(abs_key);
	});
};

/**
 * Keeps alive local storage sessions. Removes inactive session.
 */
ZBX_LocalStorage.prototype.keepAlive = function() {
	var store = localStorage,
		lastseen = JSON.parse(store.getItem(ZBX_LocalStorage.defines.KEY_SESSIONS) || '{}'),
		timestamp = (+new Date / 1000) >> 0,
		expired_timestamp = timestamp - 2 * ZBX_LocalStorage.defines.KEEP_ALIVE_INTERVAL;

	lastseen[ZBX_LocalStorage.sessionid] = (+new Date() / 1000) >> 0;

	for (var sessionid in lastseen) {
		if (lastseen[sessionid] < expired_timestamp) {
			this.freeSession(store, sessionid);
			delete lastseen[sessionid];
		}
	}

	store.setItem(ZBX_LocalStorage.defines.KEY_SESSIONS, JSON.stringify(lastseen));
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
	if (typeof key !== 'string') {
		throw 'Key must be a string, ' + (typeof key) + ' given instead.';
	}

	if (!this.hasKey(key)) {
		throw 'Unknown localStorage key access at "' + key + '"';
	}
};

/**
 * This whole design of signed payloads exists only because of IE11.
 *
 * @param {mixed} value
 *
 * @return {string}
 */
ZBX_LocalStorage.prototype.wrap = function(value) {
	return JSON.stringify({
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
 */
ZBX_LocalStorage.prototype.truncate = function() {
	this.eachKey(function(key) {
		key.truncate();
	});
};

/**
 * Synchronization tick. It checks if any of keys have been written outside the window object where this instance runs.
 * It compares last writes this instance knows about with last writes in storage. @see this.flushKeyWrite,
 * When it is detected that some key is out of sync, then synthetic event is dispatched providing subscribers with
 * new value, keep in mind that a key may hold native event subscription via `onKeyUpdate` and synthetic event is
 * provided via `onKeySync` method.
 */
ZBX_LocalStorage.prototype.syncTick = function() {
	var key_last_write = this.fetchKeyWrites();

	for (var abs_key in this.key_last_write) {
		if (this.key_last_write[abs_key] != key_last_write[abs_key]) {
			this.key_last_write[abs_key] = key_last_write[abs_key];
			this.abs_keys[abs_key].publishSync(this.readKey(this.abs_to_rel_keymap[abs_key]));
		}
	}
};

/**
 * Adds event handlers.
 */
ZBX_LocalStorage.prototype.register = function() {
	window.addEventListener('storage', this.handleStorageEvent.bind(this));
	this.keepAlive();
	setInterval(this.keepAlive.bind(this), ZBX_LocalStorage.defines.KEEP_ALIVE_INTERVAL * 1000);

	this.syncTick();
	setInterval(this.syncTick.bind(this), ZBX_LocalStorage.defines.SYNC_INTERVAL_MS);
};

/**
 * @param {StorageEvent} event
 */
ZBX_LocalStorage.prototype.handleStorageEvent = function(event) {
	if (event.constructor != StorageEvent) {
		throw 'Unmatched method signature!';
	}

	// Internal usage key.
	if (event.key === ZBX_LocalStorage.defines.KEY_LAST_WRITE) {
		return;
	}

	// Internal usage key.
	if (event.key === ZBX_LocalStorage.defines.KEY_SESSIONS) {
		return;
	}

	// This means, storage has been truncated.
	if (event.key === null || event.key === '') {
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
		// If value could not be unwrapped, it has not originated from this class.
		return;
	}

	if (value.signature === ZBX_LocalStorage.signature) {
		// Internet explorer just dispatched storage event onto current instance.
		return;
	}

	if (!this.abs_keys[event.key]) {
		return;
	}

	this.abs_keys[event.key].publish(value.payload);
};

/**
 * Writes an underlying value.
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
	this.flushKeyWrite(this.toAbsKey(key));
};

/**
 * @return {object}
 */
ZBX_LocalStorage.prototype.fetchKeyWrites = function() {
	var key_writes = JSON.parse(localStorage.getItem(ZBX_LocalStorage.defines.KEY_LAST_WRITE) || '{}'),
		session_regex = '^' + ZBX_LocalStorage.prefix,
		session_key_writes = {};

	for (var abs_key in key_writes) {
		if (abs_key.match(session_regex)) {
			session_key_writes[abs_key] = key_writes[abs_key];
		}
	}

	return session_key_writes;
};

/**
 * Merges local sync object updates and writes them into store.
 *
 * @param {string} abs_key  Absolute key.
 */
ZBX_LocalStorage.prototype.flushKeyWrite = function(abs_key) {
	var key_last_write = this.fetchKeyWrites();

	key_last_write[abs_key] = +new Date;

	localStorage.setItem(ZBX_LocalStorage.defines.KEY_LAST_WRITE, JSON.stringify(key_last_write));

	this.key_last_write = key_last_write;
};

/**
 * Fetches underlying value. A copy of default value is returned if key has no data.
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

/**
 * @param {string} relative_key
 */
function ZBX_LocalStorageKey(relative_key) {
	this.backup_store = sessionStorage;
	this.primary_store = localStorage;

	this.relative_key = relative_key;
	this.absolute_key = ZBX_LocalStorage.prefix + this.relative_key;

	this.on_update_cbs = [];
	this.on_sync_cbs = [];
}

/**
 * @throw
 *
 * @param {string} string
 */
ZBX_LocalStorageKey.prototype.writePrimary = function(string) {
	if (string.constructor != String || string[0] != '{') {
		throw 'Corrupted input.';
	}
	this.primary_store.setItem(this.absolute_key, string);
};

/**
 * @throw
 *
 * @param {string} string
 */
ZBX_LocalStorageKey.prototype.writeBackup = function(string) {
	if (string.constructor != String || string[0] != '{') {
		throw 'Corrupted input.';
	}
	this.backup_store.setItem(this.absolute_key, string);
};

/**
 * @param {string} string
 */
ZBX_LocalStorageKey.prototype.write = function(string) {
	this.writeBackup(string);
	this.writePrimary(string);
};

/**
 * Fetch key value.
 *
 * @return {string|null}  Null is returned if key is deleted.
 */
ZBX_LocalStorageKey.prototype.fetchPrimary = function() {
	return this.primary_store.getItem(this.absolute_key);
};

/**
 * Fetch key value.
 *
 * @return {string|null}  Null is returned if key is deleted.
 */
ZBX_LocalStorageKey.prototype.fetchBackup = function() {
	return this.backup_store.getItem(this.absolute_key);
};

/**
 * Fetch key value.
 *
 * @return {string|null}  Null is returned if key is deleted.
 */
ZBX_LocalStorageKey.prototype.fetch = function() {
	return this.fetchPrimary() || this.fetchBackup();
};

/**
 * Removes current key data from backup store.
 */
ZBX_LocalStorageKey.prototype.truncateBackup = function() {
	this.backup_store.removeItem(this.absolute_key);
};

/**
 * Removes current key data from primary store.
 */
ZBX_LocalStorageKey.prototype.truncatePrimary = function() {
	this.primary_store.removeItem(this.absolute_key);
};

/**
 * Removes current key data from stores.
 */
ZBX_LocalStorageKey.prototype.truncate = function() {
	this.truncateBackup();
	this.truncatePrimary();
};

/**
 * Sunscribe a callback to key sync request.
 *
 * @param {callable} callback
 */
ZBX_LocalStorageKey.prototype.subscribeSync = function(callback) {
	this.on_sync_cbs.push(callback);
};

/**
 * @param {object} payload
 */
ZBX_LocalStorageKey.prototype.publishSync = function(payload) {
	this.on_sync_cbs.forEach(function(callback) {
		callback(payload);
	});
};

/**
 * @param {callable} callback
 */
ZBX_LocalStorageKey.prototype.subscribe = function(callback) {
	this.on_update_cbs.push(callback);
};

/**
 * @param {object} payload
 */
ZBX_LocalStorageKey.prototype.publish = function(payload) {
	this.on_update_cbs.forEach(function(callback) {
		callback(payload);
	});
};

ZABBIX.namespace(
	'instances.localStorage',
	new ZBX_LocalStorage('1', window.ZBX_SESSION_NAME)
);
