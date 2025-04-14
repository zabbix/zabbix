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
 * @class Creates a new Selement.
 *
 * @property {object} sysmap      Reference to Map object.
 * @property {object} data        Selement DB values.
 * @property {bool}   selected    If element is now selected by user.
 * @property {string} id          Element ID.
 * @property {object} domNode     Reference to related DOM element.
 *
 * @param {object} sysmap  Reference to Map object.
 * @param {object} data    Element DB values.
 */
class Selement {
	constructor(sysmap, data) {
		this.sysmap = sysmap;
		this.selected = false;

		if (!data) {
			data = {
				selementid: getUniqueId(),
				elementtype: SVGMapElement.TYPE_IMAGE,
				elements: {},
				iconid_off: this.sysmap.defaultIconId, // first imageid
				label: t('S_NEW_ELEMENT'),
				label_location: -1, // set default map label location
				show_label: SVGMapElement.SHOW_LABEL_DEFAULT,
				x: 0,
				y: 0,
				urls: {},
				elementName: this.sysmap.defaultIconName, // first image name
				use_iconmap: SYSMAP_ELEMENT_USE_ICONMAP_ON,
				evaltype: TAG_EVAL_TYPE_AND_OR,
				tags: [],
				inherited_label: null,
				zindex: Object.values(sysmap.selements).reduce((max, el) => Math.max(max, el.data.zindex), -1) + 1
			};
		}
		else {
			if ($.isArray(data.urls)) {
				data.urls = {};
			}
		}

		this.data = data;
		this.updateLabel();
		this.id = this.data.selementid;
		this.expanded = this.data.expanded;
		delete this.data.expanded;

		// Assign by reference.
		this.sysmap.data.selements[this.id] = this.data;

		// Create dom.
		this.domNode = $('<div>', {style: 'position: absolute; z-index: 100'})
			.appendTo(this.sysmap.container)
			.addClass('cursor-pointer sysmap_element')
			.attr('data-id', this.id)
			.attr('data-type', 'selements');

		this.makeDraggable(true);
		this.makeResizable(this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
				&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS
				&& this.data.areatype == SVGMapElement.AREA_TYPE_CUSTOM
		);

		this.#updateIcon();

		Object.assign(this.domNode[0].style, {
			top: `${this.data.y}px`,
			left: `${this.data.x}px`
		});
	}

	/**
	 * Returns element data.
	 */
	getData(...args) {
		return Shape.prototype.getData.apply(this, args);
	}

	/**
	 * Allows dragging of element.
	 */
	makeDraggable(...args) {
		return Shape.prototype.makeDraggable.apply(this, args);
	}

	/**
	 * Allows resizing of element.
	 */
	makeResizable(...args) {
		return Shape.prototype.makeResizable.apply(this, args);
	}

	/**
	 * Update label data inherited from map configuration.
	 */
	updateLabel() {
		if (this.sysmap.data.label_format != 0) {
			switch (parseInt(this.data.elementtype, 10)) {
				case SVGMapElement.TYPE_HOST_GROUP:
					this.data.label_type = this.sysmap.data.label_type_hostgroup;

					if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
						this.data.inherited_label = this.sysmap.data.label_string_hostgroup;
					}
					break;

				case SVGMapElement.TYPE_HOST:
					this.data.label_type = this.sysmap.data.label_type_host;

					if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
						this.data.inherited_label = this.sysmap.data.label_string_host;
					}
					break;

				case SVGMapElement.TYPE_TRIGGER:
					this.data.label_type = this.sysmap.data.label_type_trigger;

					if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
						this.data.inherited_label = this.sysmap.data.label_string_trigger;
					}
					break;

				case SVGMapElement.TYPE_MAP:
					this.data.label_type = this.sysmap.data.label_type_map;

					if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
						this.data.inherited_label = this.sysmap.data.label_string_map;
					}
					break;

				case SVGMapElement.TYPE_IMAGE:
					this.data.label_type = this.sysmap.data.label_type_image;

					if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
						this.data.inherited_label = this.sysmap.data.label_string_image;
					}
					break;
			}
		}
		else {
			this.data.label_type = this.sysmap.data.label_type;
			this.data.inherited_label = null;
		}

		if (this.data.label_type == SVGMap.LABEL_TYPE_LABEL) {
			this.data.inherited_label = this.data.label;
		}
		else if (this.data.label_type == SVGMap.LABEL_TYPE_NAME) {
			if (this.data.elementtype != SVGMapElement.TYPE_IMAGE) {
				this.data.inherited_label = this.data.elements[0].elementName;
			}
			else {
				this.data.inherited_label = t('S_IMAGE');
			}
		}

		if (this.data.label_type != SVGMap.LABEL_TYPE_CUSTOM && this.data.label_type != SVGMap.LABEL_TYPE_LABEL
				&& this.data.label_type != SVGMap.LABEL_TYPE_IP) {
			this.data.expanded = null;
		}
		else if (this.data.label_type == SVGMap.LABEL_TYPE_IP
				&& this.data.elementtype == SVGMapElement.TYPE_HOST) {
			this.data.inherited_label = '{HOST.IP}';
		}
	}

	/**
	 * Return label based on map constructor configuration.
	 *
	 * @param {boolean}
	 *
	 * @return {string|null}  Label with expanded macros.
	 */
	getLabel(expand) {
		let label = this.data.label;

		if (expand === undefined) {
			expand = true;
		}

		if (this.data.label_type != SVGMap.LABEL_TYPE_NOTHING
				&& this.data.label_type != SVGMap.LABEL_TYPE_STATUS) {
			if (expand && typeof(this.expanded) === 'string'
					&& (this.sysmap.data.expand_macros == SYSMAP_EXPAND_MACROS_ON
						|| (this.data.label_type == SVGMap.LABEL_TYPE_IP
						&& this.data.elementtype == SVGMapElement.TYPE_HOST))) {
				label = this.expanded;
			}
			else if (typeof this.data.inherited_label === 'string') {
				label = this.data.inherited_label;
			}
		}
		else {
			label = '';
		}

		return label;
	}

	/**
	 * Updates element fields.
	 *
	 * @param {object}  data
	 * @param {boolean} unset_undefined  If true, all fields that are not in data parameter will be removed
	 *                                   from element.
	 */
	update(data, unset_undefined = false) {
		const data_fields = ['elementtype', 'elements', 'iconid_off', 'iconid_on', 'iconid_maintenance',
				'iconid_disabled', 'label', 'label_location', 'show_label', 'x', 'y', 'elementsubtype',
				'areatype', 'width', 'height', 'viewtype', 'urls', 'elementName', 'use_iconmap', 'evaltype',
				'tags'
			],
			fields_unsettable = ['iconid_off', 'iconid_on', 'iconid_maintenance', 'iconid_disabled'];

		let invalidate = ((data.label !== undefined && this.data.label !== data.label)
					|| (data.elementtype !== undefined && this.data.elementtype != data.elementtype)
					|| (data.elements !== undefined
					&& Object.keys(this.data.elements).length != Object.keys(data.elements).length));

		if (!invalidate && data.elements) {
			invalidate = Object.keys(this.data.elements).some((id) =>
				Object.keys(this.data.elements[id]).some((key) =>
					this.data.elements[id][key] !== data.elements[id][key]
				)
			);
		}

		data_fields.forEach((field) => {
			if (data[field] !== undefined) {
				this.data[field] = data[field];
			}
			else if (unset_undefined && !fields_unsettable.includes(field)) {
				delete this.data[field];
			}
		});

		if (unset_undefined) {
			// If elementsubtype is not set, it should be 0.
			if (this.data.elementsubtype === undefined) {
				this.data.elementsubtype = SVGMapElement.SUBTYPE_HOST_GROUP;
			}
			if (this.data.use_iconmap === undefined) {
				this.data.use_iconmap = SYSMAP_ELEMENT_USE_ICONMAP_OFF;
			}
		}

		this.makeResizable(
			this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
				&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS
				&& this.data.areatype == SVGMapElement.AREA_TYPE_CUSTOM
		);

		if (this.data.elementtype == SVGMapElement.TYPE_IMAGE) {
			// If element is image, unset advanced icons.
			this.data.iconid_on = '0';
			this.data.iconid_maintenance = '0';
			this.data.iconid_disabled = '0';

			// If image element, set elementName to image name.
			for (const i in this.sysmap.iconList) {
				if (this.sysmap.iconList[i].imageid === this.data.iconid_off) {
					this.data.elementName = this.sysmap.iconList[i].name;
				}
			}
		}
		else {
			this.data.elementName = this.data.elements[0].elementName;
		}

		this.updateLabel();
		this.#updateIcon();
		this.align(false);
		this.sysmap.afterMove(this);

		if (invalidate) {
			this.sysmap.expandMacros(this);
		}
	}

	/**
	 * Updates element position.
	 *
	 * @param {object} coords
	 */
	updatePosition(...args) {
		return Shape.prototype.updatePosition.apply(this, args);
	}

	/**
	 * Remove element.
	 */
	remove() {
		this.domNode.remove();
		delete this.sysmap.data.selements[this.id];
		delete this.sysmap.selements[this.id];

		if (this.sysmap.selection.selements[this.id] !== undefined) {
			this.sysmap.selection.count.selements--;
		}

		delete this.sysmap.selection.selements[this.id];
	}

	/**
	 * Toggle element selection.
	 *
	 * @param {bool} state
	 */
	toggleSelect(...args) {
		return Shape.prototype.toggleSelect.apply(this, args);
	}

	/**
	 * Align element to map or map grid.
	 *
	 * @param {bool} doAutoAlign if we should align element to grid
	 */
	align(...args) {
		return Shape.prototype.align.apply(this, args);
	}

	/**
	 * Get element dimensions.
	 */
	getDimensions(...args) {
		return Shape.prototype.getDimensions.apply(this, args);
	}

	/**
	 * Updates element icon and height/width in case element is area type.
	 */
	#updateIcon() {
		const old_icon_class = this.domNode.get(0).className.match(/sysmap_iconid_\d+/);

		if (old_icon_class !== null) {
			this.domNode[0].classList.remove(old_icon_class[0]);
		}

		if ((this.data.use_iconmap == SYSMAP_ELEMENT_USE_ICONMAP_ON && this.sysmap.data.iconmapid != 0)
				&& (this.data.elementtype == SVGMapElement.TYPE_HOST
					|| (this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
							&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS))) {
			this.domNode[0].classList.add('sysmap_iconid_' + this.sysmap.defaultAutoIconId);
		}
		else {
			this.domNode[0].classList.add('sysmap_iconid_' + this.data.iconid_off);
		}

		if (this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
				&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS) {
			if (this.data.areatype == SVGMapElement.AREA_TYPE_CUSTOM) {
				Object.assign(this.domNode[0].style, {
					width: `${this.data.width}px`,
					height: `${this.data.height}px`
				});
			}
			else {
				Object.assign(this.domNode[0].style, {
					width: `${this.sysmap.data.width}px`,
					height: `${this.sysmap.data.height}px`
				});
			}

			this.domNode[0].classList.add('map-element-area-bg');
		}
		else {
			Object.assign(this.domNode[0].style, {
				width: '',
				height: ''
			});

			this.domNode[0].classList.remove('map-element-area-bg');
		}
	}

	getName() {
		let name;

		if (this.data.elementName === undefined) {
			name = this.data.elements[0].elementName;

			if (Object.keys(this.data.elements).length > 1) {
				name += '...';
			}
		}
		else {
			name = this.data.elementName;
		}

		return name;
	}
}
