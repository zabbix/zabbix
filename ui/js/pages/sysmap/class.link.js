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
 * Creates a new Link.
 *
 * @class represents connector between two Elements.
 *
 * @property {object} sysmap  Reference to Map object.
 * @property {object} data    Link db values.
 * @property {string} id      Link ID (linkid).
 *
 * @param {object} sysmap  Map object.
 * @param {object} data    Link data from DB.
 */
class Link {
	constructor(sysmap, data) {
		this.sysmap = sysmap;

		if (!data) {
			data = {
				label: '',
				show_label: SVGMapElement.SHOW_LABEL_DEFAULT,
				selementid1: null,
				selementid2: null,
				drawtype: 0,
				color: '00CC00',
				indicator_type: Link.INDICATOR_TYPE_STATIC_LINK,
				linktriggers: {},
				itemid: null,
				item: {},
				thresholds: {},
				highlights: {}
			};

			for (const selementid in this.sysmap.selection.selements) {
				if (data.selementid1 === null) {
					data.selementid1 = selementid;
				}
				else {
					data.selementid2 = selementid;
				}
			}

			// Generate unique linkid.
			data.linkid = getUniqueId();
		}
		else {
			if ($.isArray(data.linktriggers)) {
				data.linktriggers = {};
			}
		}

		this.data = data;
		this.id = this.data.linkid;
		this.expanded = this.data.expanded;
		delete this.data.expanded;

		// Assign by reference.
		this.sysmap.data.links[this.id] = this.data;
	}

	static INDICATOR_TYPE_STATIC_LINK = MAP_INDICATOR_TYPE_STATIC_LINK;
	static INDICATOR_TYPE_TRIGGER = MAP_INDICATOR_TYPE_TRIGGER;
	static INDICATOR_TYPE_ITEM_VALUE = MAP_INDICATOR_TYPE_ITEM_VALUE;

	/**
	 * Return label based on map constructor configuration.
	 *
	 * @param {boolean}
	 *
	 * @return {string} Label with expanded macros.
	 */
	getLabel(expand) {
		let label = this.data.label;

		if (expand === undefined) {
			expand = true;
		}

		if (expand && typeof(this.expanded) === 'string'
				&& this.sysmap.data.expand_macros == SYSMAP_EXPAND_MACROS_ON) {
			label = this.expanded;
		}

		return label;
	}

	/**
	 * Updates values in property data.
	 *
	 * @param {object} data
	 */
	update(data) {
		const invalidate = this.data.label !== data.label;

		Object.assign(this.data, data);

		this.sysmap[invalidate ? 'expandMacros' : 'updateImage'](this);
	}

	/**
	 * Removes Link object and delete all references to it.
	 */
	remove() {
		delete this.sysmap.data.links[this.id];
		delete this.sysmap.links[this.id];

		if (this.sysmap.form.active) {
			this.sysmap.linkForm.updateList(this.sysmap.selection.selements);
		}

		this.sysmap.linkForm.hide();
	}

	/**
	 * Gets Link data.
	 *
	 * @return {object}
	 */
	getData() {
		return this.data;
	}
}
