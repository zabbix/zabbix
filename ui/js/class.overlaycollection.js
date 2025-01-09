/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * A stack implementation for identified overlay objects.
 *
 * @prop {int} length   Overlay collection size.
 * @prop {array} stack  Array of overlay identifiers.
 * @prop {object} map   Overlay objects keyed by their identifiers.
 */
function OverlayCollection() {
	this.stack = [];
	this.map = {};
}

Object.defineProperty(OverlayCollection.prototype, 'length', {
	get: function () {
		return this.stack.length;
	},
	writeable: false
});

/**
 * Fetches overlay object positioned at top of stack.
 *
 * @return {object|undefined}  Overlay object currently in top of stack.
 */
OverlayCollection.prototype.end = function() {
	return this.getById(this.stack[this.length - 1]);
};

/**
 * Retrieve overlay by it's ID.
 *
 * @param {string} id  Overlay identifier.
 *
 * @return {Overlay|undefined}  Overlay object.
 */
OverlayCollection.prototype.getById = function(id) {
	return this.map[id];
};

/**
 * Adds new overlay to the stack or, if it already exists, moves it to the top of the stack.
 *
 * @param {object} Overlay object.
 */
OverlayCollection.prototype.pushUnique = function(overlay) {
	if (this.map[overlay.dialogueid]) {
		this._restackEnd(overlay.dialogueid);
	}
	else {
		this._write(overlay, this.length);
	}
};

/**
 * Removes an overlay object, returns a reference.
 *
 * @param {string} id  An overlay identifier.
 *
 * @return {object|undefined}  Overlay object that is no longer in this stack.
 */
OverlayCollection.prototype.removeById = function(id) {
	var overlay = this.getById(id);

	if (overlay) {
		delete this.map[id];
		this.stack.splice(this._fetchIndex(id), 1);
	}

	return overlay;
};

/**
 * Get unused overlay id.
 *
 * @return {string}
 */
OverlayCollection.prototype.getNextId = function() {
	var overlayid = Math.random().toString(36).substring(7);

	while (this.stack.indexOf(overlayid) !== -1) {
		overlayid = Math.random().toString(36).substring(7);
	}

	return overlayid;
};

/**
 * Retrieves overlay Z-index by ID.
 *
 * @param {string} id  Overlay object identifier.
 *
 * @throws Error
 *
 * @return {int}  Position in stack.
 */
OverlayCollection.prototype._fetchIndex = function(id) {
	for (var i = this.length - 1; i >= 0; i--) {
		if (this.stack[i] == id) {
			return i;
		}
	}

	throw new Error('Fetching nonexistent overlay: ' + id);
};

/**
 * Moves an overlay to the top of stack.
 *
 * @param {string} id  An overlay identifier.
 */
OverlayCollection.prototype._restackEnd = function(id) {
	this.stack.splice(this._fetchIndex(id), 1);
	this.stack.push(id);
};

/**
 * @param {object} overlay  Overlay object.
 * @param {int} position  Z-index for overlay object.
 */
OverlayCollection.prototype._write = function(overlay, position) {
	this.stack[position] = overlay.dialogueid;
	this.map[overlay.dialogueid] = overlay;
};
