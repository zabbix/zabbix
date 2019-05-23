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


ZBX_Promise.PENDING = 0;
ZBX_Promise.RESOLVED = 1;
ZBX_Promise.REJECTED = 2;

/**
 * Promise API polyfill. This polyfill contains only things that we use.
 * Please update this once richer Promises API needs to be used.
 *
 * Warning: method chaining is not implemented.
 *
 * @param {callable} resolver
 */
function ZBX_Promise(resolver) {
	this.state = ZBX_Promise.PENDING;
	this.onResolve = function() {};
	this.onReject = function() {};

	resolver(this.resolve.bind(this), this.reject.bind(this));
}

/**
 * @param {mixed} result
 */
ZBX_Promise.prototype.reject = function(result) {
	this.state = ZBX_Promise.REJECTED;
	this.onReject(result);
};

/**
 * @param {mixed} result
 */
ZBX_Promise.prototype.resolve = function(result) {
	this.state = ZBX_Promise.RESOLVED;
	this.onResolve(result);
};

/**
 * @param {callable} closure
 */
ZBX_Promise.prototype.catch = function(closure) {
	this.onReject = closure;

	return this;
};

/**
 * @param {callable} closure
 */
ZBX_Promise.prototype.then = function(closure) {
	this.onResolve = closure;

	return this;
};

if (!window.Promise) {
	window.Promise = ZBX_Promise;
}
