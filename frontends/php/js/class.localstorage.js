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

	this.keys = {
		// Store versioning.
		'version': version,
		// An object where every tab updates timestamp at key of it's ID, in order to assume if there are crashed tabs.
		'tabs.lastseen': {},
		// Browser tab ID that was the last one focused.
		'tabs.lastfocused': '',
		// Browser tab ID that was the last one left focus.
		'tabs.lastblured': '',
		// Stores manifest for notifications that currently are in DOM. Keyed by ID.
		'notifications.list': {},
		// Each notification property of snooze is help separately - keyed by notification ID.
		'notifications.snoozedids': {},
		// Holds a list checksum. This way we know if list we received has updates.
		'notifications.listid': '',
		// Represents state of snooze icon.
		'notifications.alarm.snoozed': '',
		// No audio for notifications will be played.
		'notifications.alarm.muted': false,
		// Currently played notifications audio file.
		'notifications.alarm.wave': '',
		// Seek position will always be read on focus, if playing.
		'notifications.alarm.seek': 0,
		// Duration a player will play audio for.
		'notifications.alarm.timeout': 0,
		// Notification start ID is written when we receive a notification that should be played.
		'notifications.alarm.start': '',
		// Notification end ID is written when notification has completed it's audio playback.
		'notifications.alarm.end': '',
		// Poll interval will be reduced, if there is possibility for user to miss new notifications.
		'notifications.poll_interval': 0,
		// Disabled setting tells notifier objects to stop audio, hide notifications and to stop following active tab.
		'notifications.disabled': false,
		// An object of timeout setting and client time at first render, keyed by notification id.
		'notifications.localtimeouts': {}
	};

	/*
	 * This subset of keys will be mirrored in session storage to be read if session storage has no value under a key.
	 * This way we survive data across page reloads in case of single tab.
	 */
	this.keys_to_backup = {
		'notifications.alarm.end': true,
		'notifications.alarm.snoozed': true,
		'notifications.snoozedids': true,
		'notifications.list': true,
		'notifications.disabled': true,
		'notifications.localtimeouts': true
	};

	if (this.readKey('version') != this.keys.version) {
		this.truncate();
	}

	this.keepAlive();
	setInterval(this.keepAlive, ZBX_LocalStorage.defines.KEEP_ALIVE_INTERVAL * 1000);
}

/**
 * Keeps alive local storage sessions. Removes inactive session.
 */
ZBX_LocalStorage.prototype.keepAlive = function() {
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
	var obj = this.readKey(key);

	callback(obj);
	this.writeKey(key, obj);
};

/**
 * Validates if key is used by this version of localStorage.
 *
 * @param {string} key  Key to test.
 *
 * @return {bool}
 */
ZBX_LocalStorage.prototype.hasKey = function(key) {
	return typeof this.keys[key] !== 'undefined';
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
 * Transforms absolute key into relative key.
 *
 * @param {string} abs_key
 *
 * @return {string|null}  Relative key if found.
 */
ZBX_LocalStorage.prototype.fromAbsKey = function(abs_key) {
	var match = abs_key.match('^' + ZBX_LocalStorage.prefix + '(.*)');

	if (match !== null) {
		match = match[1];
	}

	return match;
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
 * Writes an underlaying value.
 *
 * @param {string} key
 * @param {string} value
 */
ZBX_LocalStorage.prototype.writeKey = function(key, value) {
	if (typeof value === 'undefined') {
		throw 'Value may not be undefined, use null instead';
	}

	this.ensureKey(key);

	var abs_key = this.toAbsKey(key);
	value = this.wrap(value);

	if (this.keys_to_backup[key]) {
		sessionStorage.setItem(abs_key, value);
	}

	localStorage.setItem(abs_key, value);
};

/**
 * Writes default value.
 *
 * @param {string} key  Key to reset.
 */
ZBX_LocalStorage.prototype.resetKey = function(key) {
	this.ensureKey(key);
	this.writeKey(key, this.keys[key]);
};

/**
 * Fetches underlaying value. A copy of default value is returned if key has no data.
 *
 * @param {string} key  Key to test.
 *
 * @return {mixed}
 */
ZBX_LocalStorage.prototype.readKey = function(key) {
	this.ensureKey(key);

	try {
		var abs_key = this.toAbsKey(key),
			item = this.primaryFetch(abs_key)
				|| this.keys_to_backup[key] && this.backupFetch(abs_key)
				|| this.wrap(this.keys[key]);

		return this.unwrap(item).payload;
	}
	catch(e) {
		console.warn('failed to parse storage item "' + key + '"');
		this.truncate();
		this.truncateBackup();

		return null;
	}
};

/**
 * @param {string} abs_key
 *
 * @return {mixed|null}  A null is returned when there is no such key.
 */
ZBX_LocalStorage.prototype.backupFetch = function(abs_key) {
	return sessionStorage.getItem(abs_key);
};

/**
 * @param {string} abs_key
 *
 * @return {mixed|null}  A null is returned when there is no such key.
 */
ZBX_LocalStorage.prototype.primaryFetch = function(abs_key) {
	return localStorage.getItem(abs_key);
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
 * @param {string} value
 *
 * @return {mixed}
 */
ZBX_LocalStorage.prototype.unwrap = function(value) {
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
	var abs_key, key, i;

	for (i = 0; i < sessionStorage.length; i++) {
		abs_key = sessionStorage.key(i);
		key = this.fromAbsKey(abs_key);

		if (key && this.keys_to_backup[key]) {
			sessionStorage.removeItem(abs_key);
		}
	}
};

/**
 * Removes all local storage and creates default objects. Backup keys are not removed.
 *
 * @param {callable} filter_cb  Optional callback which return true if key should be removed.
 */
ZBX_LocalStorage.prototype.truncate = function(filter_cb) {
	var iter = this.iterator();

	if (filter_cb && filter_cb.constructor === Function) {
		iter = iter.filter(filter_cb);
	}

	iter.forEach(function(key_variants) {
		localStorage.removeItem(key_variants.abs_key);
	});
};

/**
 * Registers an event handler. A callback will get passed key that were modified and the new value the key now holds.
 * Note: handle is fired only when there was a change, not in the case of every `writeKey` call.
 *
 * @param {callable} callback
 */
ZBX_LocalStorage.prototype.onUpdate = function(callback) {
	window.addEventListener('storage', function(event) {
		// This key is for internal use only.
		if (event.key === ZBX_LocalStorage.defines.KEY_SESSIONS) {
			return;
		}

		// This means, storage has been truncated.
		if (event.key === null || event.key === '') {
			return this.mapCallback(callback);
		}

		try {
			/*
			 * Not only IE dispatches 'storage' event 'onwrite' instead of 'onchange', but event is also dispatched onto
			 * window that is the modifier. So we need to sign all payloads.
			 */
			var value = this.unwrap(event.newValue);

			if (value.signature !== ZBX_LocalStorage.signature) {
				callback(this.fromAbsKey(event.key), value.payload, ZBX_LocalStorage.defines.EVT_CHANGE);
			}
		}
		catch(e) {
			// If value could not be unwraped, it has not originated from this class.
		}
	}.bind(this));
};

/**
 * Apply every callback for each localStorage entry.
 *
 * @param {callable} callback
 */
ZBX_LocalStorage.prototype.mapCallback = function(callback) {
	this.iterator().forEach(function(key_variants) {
		if (this.hasKey(key_variants.key)) {
			callback(key_variants.key, this.readKey(key_variants.key), ZBX_LocalStorage.defines.EVT_MAP);
		}
	}.bind(this));
};

/**
 * Returns list of keys found in localStorage relevant for current session.
 *
 * @return {array}
 */
ZBX_LocalStorage.prototype.iterator = function() {
	var length = localStorage.length,
		list = [],
		abs_key,
		key,
		i;

	for (i = 0; i < length; i++) {
		abs_key = localStorage.key(i);
		key = this.fromAbsKey(abs_key);

		if (!key) {
			continue;
		}

		list.push({
			abs_key: abs_key,
			key: key
		});
	}

	return list;
};

ZABBIX.namespace(
	'instances.localStorage',
	new ZBX_LocalStorage('1', cookie.read('localstoragePath'))
);
