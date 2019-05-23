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
 * Amount of seconds for keep-alive interval.
 */
ZBX_BrowserTab.keep_alive_interval = 30;

/**
 * This object is representing a browser tab. Implements singleton pattern. It ensures there are only non-crashed tabs
 * in store. It maintains currently focused tab and last focused tab in store.
 *
 * @param {ZBX_LocalStorage} store  A localStorage wrapper.
 */
function ZBX_BrowserTab(store) {
	if (!(store instanceof ZBX_LocalStorage)) {
		throw 'Unmatched signature!';
	}

	if (ZBX_BrowserTab.instance) {
		return ZBX_BrowserTab.instance;
	}

	ZBX_BrowserTab.instance = this;
	this.uid = (Math.random() % 9e6).toString(36).substr(2);
	this.focused = false;
	this.store = store;

	this.on_focus_cbs = [];
	this.on_blur_cbs = [];
	this.on_unload_cbs = [];

	this.register();
}

/**
 * Gives all tab ids.
 *
 * @return {array}
 */
ZBX_BrowserTab.prototype.getAllTabIds = function() {
	return Object.keys(this.store.readKey('tabs.lastseen'));
};

/**
 * Looks for crashed tabs.
 */
ZBX_BrowserTab.prototype.checkAlive = function() {
	var since = Math.floor(+new Date / 1000) - (ZBX_BrowserTab.keep_alive_interval - 1) * 2;
	var tabs = this.store.readKey('tabs.lastseen');

	for (var tabid in tabs) {
		if (tabs[tabid] < since) {
			this.handleCrashed(tabid);
		}
	}
};

/**
 * A focus event on current tab will be spoofed, if it tab just removed a crashed tab reference, that previously was the
 * focused one.
 *
 * @param {string} tabid  The crashed tab ID.
 */
ZBX_BrowserTab.prototype.handleCrashed = function(tabid) {
	this.store.mutateObject('tabs.lastseen', function(tabs) {
		delete tabs[tabid];
	}.bind(this));

	if (this.store.readKey('tabs.lastfocused') == tabid) {
		console.info('Recovered a crashed tab ' + tabid + '. Now tab ' + this.uid + ' is polling for notifications.');
		this.handleFocus();
	}
};

/**
 * @param {callable} callback
 */
ZBX_BrowserTab.prototype.onBlur = function(callback) {
	this.on_blur_cbs.push(callback);
};

/**
 * @param {callable} callback
 */
ZBX_BrowserTab.prototype.onFocus = function(callback) {
	this.on_focus_cbs.push(callback);
};

/**
 * @param {callable} callback
 */
ZBX_BrowserTab.prototype.onUnload = function(callback) {
	this.on_unload_cbs.push(callback);
};

/**
 * Rewrite focused tab ID.
 */
ZBX_BrowserTab.prototype.handleBlur = function() {
	this.on_blur_cbs.forEach(function(c) {c(this);}.bind(this));
	// This object might already be collected at beforeunload handler.
	if (this.store) {
		this.store.writeKey('tabs.lastblured', this.uid);
	}
};

/**
 * Rewrite focused tab ID.
 */
ZBX_BrowserTab.prototype.handleFocus = function() {
	this.on_focus_cbs.forEach(function(c) {c(this);}.bind(this));
	this.store.writeKey('tabs.lastfocused', this.uid);
};

/**
 * Delegates active tab to any other alive tab and cleans up localStorage.
 */
ZBX_BrowserTab.prototype.handleBeforeUnload = function() {
	var uid = this.uid,
		all_tab_ids;

	this.checkAlive();
	this.store.mutateObject('tabs.lastseen', function(tabs) {
		delete tabs[uid];
	});

	all_tab_ids = this.getAllTabIds();
	this.store.writeKey('tabs.lastfocused', all_tab_ids.length ? all_tab_ids[0] : '');

	this.on_unload_cbs.forEach(function(c) {c(this)}.bind(this));

	window.removeEventListener('beforeunload', this.handleBeforeUnload.bind(this));
	window.removeEventListener('focus', this.handleFocus.bind(this));
	window.removeEventListener('blur', this.handleBlur.bind(this));
};

/**
 * Compares instance with active ref from localStorage.
 *
 * @return {bool}
 */
ZBX_BrowserTab.prototype.isFocused = function() {
	return this.store.readKey('tabs.lastfocused') === this.uid;
};

/**
 * @return {bool}
 */
ZBX_BrowserTab.prototype.isSingleSession = function() {
	return this.getAllTabIds().length == 1;
};

/**
 * Updates timestamp for own ID.
 */
ZBX_BrowserTab.prototype.keepAlive = function() {
	var uid = this.uid;
	this.store.mutateObject('tabs.lastseen', function(tabs) {
		tabs[uid] = Math.floor(+new Date / 1000);
	});
};

/**
 * Writes own ID in `store.tabs` object.  Registers beforeunload event to remove own ID from `store.tabs`. Registers
 * focus and blur events to maintain `store.tabs.lastfocused`. Begins a loop to see if any tab of tabs has crashed.
 */
ZBX_BrowserTab.prototype.register = function() {
	this.keepAlive();
	this.checkAlive();

	setInterval(function() {
		this.keepAlive();
		this.checkAlive();
	}.bind(this), ZBX_BrowserTab.keep_alive_interval * 1000);

	window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));
	window.addEventListener('focus', this.handleFocus.bind(this));
	window.addEventListener('blur', this.handleBlur.bind(this));

	if (document.hasFocus()) {
		this.handleFocus();
	}
};

ZABBIX.namespace('instances.browserTab', new ZBX_BrowserTab(
	ZABBIX.namespace('instances.localStorage')
));
