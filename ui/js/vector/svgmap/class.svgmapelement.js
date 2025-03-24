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
 * SVGMapElement class. Implements rendering of map elements (selements).
 *
 * @param {object} map      Parent map.
 * @param {object} options  Element attributes (match field names in data source).
 */
class SVGMapElement {

	/**
	 * Stores mouse over event handler.
	 *
	 * @type {function}
	 */
	#mouse_over_handler;

	/**
	 * Stores mouse out event handler.
	 *
	 * @type {function}
	 */
	#mouse_out_handler;

	/**
	 * Stores mouse click event handler.
	 *
	 * @type {function}
	 */
	#click_handler;

	constructor (map, options) {
		this.map = map;
		this.options = options;
		this.selection = null;
		this.highlight = null;
		this.image = null;
		this.label = null;
		this.markers = null;
	}

	// Predefined label positions.
	static LABEL_POSITION_NONE = null;
	static LABEL_POSITION_DEFAULT = MAP_LABEL_LOC_DEFAULT;
	static LABEL_POSITION_BOTTOM = MAP_LABEL_LOC_BOTTOM;
	static LABEL_POSITION_LEFT = MAP_LABEL_LOC_LEFT;
	static LABEL_POSITION_RIGHT = MAP_LABEL_LOC_RIGHT;
	static LABEL_POSITION_TOP = MAP_LABEL_LOC_TOP;

	static SHOW_LABEL_AUTO_HIDE = MAP_SHOW_LABEL_AUTO_HIDE;
	static SHOW_LABEL_DEFAULT = MAP_SHOW_LABEL_DEFAULT;

	// Predefined element types and subtypes.
	static TYPE_HOST = SYSMAP_ELEMENT_TYPE_HOST;
	static TYPE_MAP = SYSMAP_ELEMENT_TYPE_MAP;
	static TYPE_TRIGGER = SYSMAP_ELEMENT_TYPE_TRIGGER;
	static TYPE_HOST_GROUP = SYSMAP_ELEMENT_TYPE_HOST_GROUP;
	static TYPE_IMAGE = SYSMAP_ELEMENT_TYPE_IMAGE;

	static SUBTYPE_HOST_GROUP = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
	static SUBTYPE_HOST_GROUP_ELEMENTS = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS;

	static AREA_TYPE_FIT = SYSMAP_ELEMENT_AREA_TYPE_FIT;
	static AREA_TYPE_CUSTOM = SYSMAP_ELEMENT_AREA_TYPE_CUSTOM;

	/**
	 * Remove part (item) of an element.
	 *
	 * @param {string} item  Item to be removed.
	 */
	removeItem(item) {
		if (this[item] !== null) {
			this[item].remove();
			this[item] = null;
		}
	}

	/**
	 * Remove element.
	 */
	remove() {
		['selection', 'highlight', 'image', 'label', 'markers'].forEach((name) => this.removeItem(name));

		delete this.map.elements[this.options.selementid];
	}

	/**
	 * Update element hovered/selected indicators.
	 */
	#updateSelection() {
		if (!this.map.can_select_element || (this.options.elementtype != this.constructor.TYPE_HOST
				&& this.options.elementtype != this.constructor.TYPE_HOST_GROUP)) {
			return;
		}

		const type = 'ellipse',
			options = {
				cx: this.center.x,
				cy: this.center.y,
				rx: Math.floor(this.width / 2) + 20,
				ry: Math.floor(this.width / 2) + 20,
				class: 'selection display-none'
			};

		if (this.selection === null || this.selection.invalid) {
			const element = this.map.layers.elements.add(type, options, undefined, this.image);

			this.removeItem('selection');
			this.selection = element;
		}
		else {
			this.selection.update(options);
		}
	}

	/**
	 * Update element highlight (shape and markers placed on the background of element).
	 */
	#updateHighlight() {
		let type = null,
			options = null;

		if (this.options.latelyChanged) {
			const radius = Math.floor(this.width / 2) + 12,
				markers = [];

			if (this.options.label_location != this.constructor.LABEL_POSITION_BOTTOM) {
				markers.push({
					type: 'path',
					attributes: {
						d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
						transform: `rotate(90 ${this.center.x + 8},${this.center.y + radius})` +
							` translate(${this.center.x + 8},${this.center.y + radius})`
					}
				});
			}

			if (this.options.label_location != this.constructor.LABEL_POSITION_LEFT) {
				markers.push({
					type: 'path',
					attributes: {
						d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
						transform: `rotate(180 ${this.center.x - radius},${this.center.y + 8})` +
							` translate(${this.center.x - radius},${this.center.y + 8})`
					}
				});
			}

			if (this.options.label_location != this.constructor.LABEL_POSITION_RIGHT) {
				markers.push({
					type: 'path',
					attributes: {
						d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
						transform: `translate(${this.center.x + radius},${this.center.y - 8})`
					}
				});
			}

			if (this.options.label_location != this.constructor.LABEL_POSITION_TOP) {
				markers.push({
					type: 'path',
					attributes: {
						d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
						transform: `rotate(270 ${this.center.x - 8},${this.center.y - radius})` +
							` translate(${this.center.x - 8},${this.center.y - radius})`
					}
				});
			}

			const element = this.map.layers.elements.add('g', {fill: '#F44336', stroke: '#B71C1C'}, markers,
					this.image
			);

			if (this.markers !== null) {
				this.markers.replace(element);
			}
			else {
				this.markers = element;
			}
		}
		else {
			this.removeItem('markers');
		}

		if (typeof this.options.highlight === 'object' && this.options.highlight !== null) {
			if (this.options.highlight.st !== null) {
				type = 'rect';
				options = {
					x: this.x - 2,
					y: this.y - 2,
					width: this.width + 4,
					height: this.height + 4,
					fill: `#${this.options.highlight.st}`,
					'fill-opacity': 0.5
				};
			}

			if (this.options.highlight.hl !== null) {
				type = 'ellipse';
				options = {
					cx: this.center.x,
					cy: this.center.y,
					rx: Math.floor(this.width / 2) + 10,
					ry: Math.floor(this.width / 2) + 10,
					fill: `#${this.options.highlight.hl}`
				};

				if (this.options.highlight.ack === true) {
					options.stroke = '#329632';
					options['stroke-width'] = '4px';
				}
				else {
					options['stroke-width'] = '0';
				}
			}
		}

		if (type !== null) {
			if (this.highlight === null || type !== this.highlight.type || this.highlight.invalid) {
				const element = this.map.layers.elements.add(type, options, undefined, this.image);

				this.removeItem('highlight');
				this.highlight = element;
			}
			else {
				this.highlight.update(options);
			}
		}
		else {
			this.removeItem('highlight');
		}
	}

	/**
	 * Update element image. Image should be pre-loaded and placed in cache before calling this method.
	 */
	updateImage() {
		const options = {
			x: this.x,
			y: this.y,
			width: this.width,
			height: this.height
		};

		if (this.options.actions !== null && this.options.actions !== 'null' && this.options.actions !== undefined) {
			const actions = JSON.parse(this.options.actions);

			// Don't draw context menu and hand cursor for image elements with no links.
			if (actions.data.elementtype != this.constructor.TYPE_IMAGE || actions.data.urls.length != 0) {
				options['data-menu-popup'] = this.options.actions;
				options.style = 'cursor: pointer';
			}
		}

		if (this.options.icon !== undefined) {
			let href = this.map.getImageUrl(this.options.icon);

			if (this.options.permission < PERM_READ) {
				href += '&unavailable=1';
			}

			options['xlink:href'] = href;

			const is_selectable = this.map.can_select_element
					&& (this.options.elementtype == this.constructor.TYPE_HOST
						|| this.options.elementtype == this.constructor.TYPE_HOST_GROUP),
				label_auto_hide = this.options.show_label == this.constructor.SHOW_LABEL_AUTO_HIDE;

			if (this.image === null || this.image.invalid) {
				const image = this.map.layers.elements.add('image', options);

				this.removeItem('image');
				this.image = image;
			}
			else {
				this.image.update(options);
			}

			this.#addEventListeners(is_selectable, label_auto_hide);
			this.#removeEventListeners(is_selectable, label_auto_hide);
		}
		else {
			this.removeItem('image');
		}
	}

	/**
	 * Update element label.
	 */
	updateLabel() {
		let x = this.center.x,
			y = this.center.y;

		const anchor = {
			horizontal: 'left',
			vertical: 'top'
		};

		switch (this.options.label_location) {
			case this.constructor.LABEL_POSITION_BOTTOM:
				y = this.y + this.height + this.map.canvas.textPadding;
				anchor.horizontal = 'center';
				break;

			case this.constructor.LABEL_POSITION_LEFT:
				x = this.x - this.map.canvas.textPadding;
				anchor.horizontal = 'right';
				anchor.vertical = 'middle';
				break;

			case this.constructor.LABEL_POSITION_RIGHT:
				x = this.x + this.width + this.map.canvas.textPadding;
				anchor.vertical = 'middle';
				break;

			case this.constructor.LABEL_POSITION_TOP:
				y = this.y - this.map.canvas.textPadding;
				anchor.horizontal = 'center';
				anchor.vertical = 'bottom';
				break;
		}

		if (this.options.label !== '' && +this.options.label_type != SVGMap.LABEL_TYPE_NOTHING) {
			const element = this.map.layers.elements.add('textarea', {
				x,
				y,
				fill: `#${this.map.options.theme.textcolor}`,
				anchor,
				background: {
					fill: `#${this.map.options.theme.backgroundcolor}`,
					opacity: 0.7
				},
				'data-parent': `selement_${this.options.selementid}`
			}, this.options.label);

			if (this.label !== null) {
				this.label.replace(element);
			}
			else {
				this.label = element;
			}

			if (this.options.show_label == this.constructor.SHOW_LABEL_AUTO_HIDE) {
				this.#toggleLabel(false);
			}
		}
		else {
			this.removeItem('label');
		}
	}

	/**
	 * Update element options like coordinates of x, y, width, height etc.
	 *
	 * @param {object} options  Element attributes.
	 */
	updateOptions(options) {
		const image = this.map.getImage(options.icon);

		if (image === null) {
			throw 'Invalid element configuration!';
		}

		// Data type normalization.
		['x', 'y', 'width', 'height', 'label_location', 'show_label'].forEach((name) => {
			if (options[name] !== undefined) {
				options[name] = parseInt(options[name]);
			}
		});

		// Inherit label location from map options.
		if (options.label_location == this.constructor.LABEL_POSITION_DEFAULT) {
			options.label_location = parseInt(this.map.options.label_location);
		}

		if (options.show_label == this.constructor.SHOW_LABEL_DEFAULT) {
			options.show_label = parseInt(this.map.options.show_element_label);
		}

		if (options.width !== undefined && options.height !== undefined) {
			options.x += Math.floor(options.width / 2) - Math.floor(image.naturalWidth / 2);
			options.y += Math.floor(options.height / 2) - Math.floor(image.naturalHeight / 2);
		}

		options.width = image.naturalWidth;
		options.height = image.naturalHeight;

		if (options.label === '') {
			options.label_location = this.constructor.LABEL_POSITION_NONE;
		}

		if (this.map.isChanged(this.options, options) === false) {
			// No need to update.
			return;
		}

		this.options = options;

		if (this.x != options.x || this.y != options.y || this.width != options.width || this.height != options.height) {
			['x', 'y', 'width', 'height'].forEach((name) => this[name] = options[name]);

			this.center = {
				x: this.x + Math.floor(this.width / 2),
				y: this.y + Math.floor(this.height / 2)
			};
		}
	}

	/**
	 * Create event handlers and add event listeners to created image object. On map refresh event handlers will already
	 * exist. In case some element properties have changed, no new event handlers are created.
	 *
	 * @param {boolean} is_selectable    True if element is selectable.
	 * @param {boolean} label_auto_hide  True if element has auto-hide label.
	 */
	#addEventListeners(is_selectable, label_auto_hide) {
		if (is_selectable || label_auto_hide) {
			if (this.#mouse_over_handler === undefined) {
				this.#mouse_over_handler = (e) => this.#onMouseOver(e, is_selectable, label_auto_hide);
			}

			if (this.#mouse_out_handler === undefined) {
				this.#mouse_out_handler = (e) => this.#onMouseOut(e, is_selectable, label_auto_hide);
			}

			this.image.element.addEventListener('mouseover', this.#mouse_over_handler);
			this.image.element.addEventListener('mouseout', this.#mouse_out_handler);
		}

		if (is_selectable) {
			if (this.#click_handler === undefined) {
				this.#click_handler = () => this.#onClick();
			}

			this.image.element.addEventListener('click', this.#click_handler);
		}
	}

	/**
	 * Remove event listeners on created image object based on previously set event handlers.
	 *
	 * @param {boolean} is_selectable    True if element is selectable.
	 * @param {boolean} label_auto_hide  True if element has auto-hide label.
	 */
	#removeEventListeners(is_selectable, label_auto_hide) {
		if (!is_selectable && !label_auto_hide) {
			if (this.#mouse_over_handler !== undefined) {
				this.image.element.removeEventListener('mouseover', this.#mouse_over_handler);
			}

			if (this.#mouse_out_handler !== undefined) {
				this.image.element.removeEventListener('mouseout', this.#mouse_out_handler);
			}
		}

		if (!is_selectable && this.#click_handler !== undefined) {
			this.image.element.removeEventListener('click', this.#click_handler);
		}
	}

	/**
	 * Update element selection, highlight, image and label.
	 */
	update() {
		this.updateImage();
		this.#updateSelection();
		this.#updateHighlight();
		this.updateLabel();
	}

	/**
	 * Element mouse over event.
	 *
	 * @param {event}   e                Mouse over event.
	 * @param {boolean} is_selectable    True if element is selectable.
	 * @param {boolean} label_auto_hide  True if label should be toggled.
	 */
	#onMouseOver(e, is_selectable, label_auto_hide) {
		if (is_selectable && !e.target.classList.contains('selected')) {
			this.selection.element.classList.remove('display-none');
		}

		if (label_auto_hide) {
			this.#toggleLabel(true);
		}
	}

	/**
	 * Element mouse out event.
	 *
	 * @param {event}   e                Mouse out event.
	 * @param {boolean} is_selectable    True if element is selectable.
	 * @param {boolean} label_auto_hide  True if label should be toggled.
	 */
	#onMouseOut(e, is_selectable, label_auto_hide) {
		if (is_selectable && !e.target.classList.contains('selected')) {
			this.selection.element.classList.add('display-none');
		}

		if (label_auto_hide) {
			this.#toggleLabel(false);
		}
	}

	/**
	 * Element click event.
	 */
	#onClick() {
		this.map.container.dispatchEvent(new CustomEvent(SVGMap.EVENT_ELEMENT_SELECT, {
			detail: {
				selected_element_id: this.options.selementid,
				hostid: this.options.elementtype == this.constructor.TYPE_HOST
					? this.options.elements[0].hostid
					: null,
				hostgroupid: this.options.elementtype == this.constructor.TYPE_HOST_GROUP
					? this.options.elements[0].groupid
					: null
			}
		}));
	}

	/**
	 * Show or hide element label.
	 *
	 * @param {boolean} show
	 */
	#toggleLabel(show) {
		if (this.map.container === null) {
			return;
		}

		const label = this.map.container.querySelector(`text[data-parent=selement_${this.options.selementid}]`);

		if (label === null) {
			return;
		}

		const trigger_label = label.querySelector('tspan[data-type=trigger]');

		if (trigger_label !== null) {
			const label_parts = label.querySelectorAll('tspan[data-type=label]');

			if (label_parts.length > 0) {
				label_parts.forEach((label_part, index) => {
					label_part.style.display = show ? '' : 'none';
					label_part.setAttribute('dy', show ? (index == 0 ? '0.9em' : '1.2em') : '0');
				});

				trigger_label.setAttribute('dy', show ? '1.2em' : '0.9em');
			}
		}
		else {
			label.parentElement.style.display = show ? '' : 'none';
		}
	}

	/**
	 * Select element.
	 *
	 * @param {boolean} is_selected
	 */
	toggleSelection(is_selected) {
		if (this.selection === null) {
			return;
		}

		this.selection.element.classList.toggle('display-none', !is_selected);
		this.selection.element.classList.toggle('selected', is_selected);
		this.image.element.classList.toggle('selected', is_selected);
	}
}
