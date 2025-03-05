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
 * SVGMap class.
 *
 * Implements vector map rendering functionality.
 */
function SVGMap(options) {
	this.layers = {};
	this.options = options;
	this.elements = {};
	this.shapes = {};
	this.links = {};
	this.background = null;
	this.container = null;
	this.imageUrl = 'imgstore.php?iconid=';
	this.imageCache = new ImageCache();
	this.canvas = new SVGCanvas(options.canvas, true);
	if (this.options.show_timestamp === undefined) {
		this.options.show_timestamp = true;
	}
	this.rendered_promise = Promise.resolve();
	this.can_select_element = this.options.can_select_element || false;
	this.selected_element_id = options.selected_element_id || '';

	this.EVENT_ELEMENT_SELECT = 'element.select';

	// Extra group for font styles.
	const container = this.canvas.add('g', {
		class: 'map-container',
		'font-family': SVGMap.FONTS[9],
		'font-size': '10px'
	});

	const layers_to_add = [
		//  Background.
		{
			type: 'g',
			attributes: {
				class: 'map-background',
				fill: `#${options.theme.backgroundcolor}`
			}
		},
		// Grid.
		{
			type: 'g',
			attributes: {
				class: 'map-grid',
				stroke: `#${options.theme.gridcolor}`,
				fill: `#${options.theme.gridcolor}`,
				'stroke-width': '1',
				'stroke-dasharray': '4,4',
				'shape-rendering': 'crispEdges'
			}
		},
		// Custom shapes.
		{
			type: 'g',
			attributes: {
				class: 'map-shapes'
			}
		},
		// Elements (images, image labels, links, link labels, highlights, selections and markers).
		{
			type: 'g',
			attributes: {
				class: 'map-elements'
			}
		}
	];

	// Marks (timestamp and homepage).
	if (options.show_timestamp) {
		layers_to_add.push({
			type: 'g',
			attributes: {
				class: 'map-marks',
				fill: 'rgba(150, 150, 150, 0.75)',
				'font-size': '8px',
				'shape-rendering': 'crispEdges'
			},
			content: [
				{
					type: 'text',
					attributes: {
						class: 'map-timestamp',
						'text-anchor': 'end',
						x: options.canvas.width - 6,
						y: options.canvas.height - 6
					}
				}
			]
		});
	}

	const layers = container.add(layers_to_add);

	['background', 'grid', 'shapes', 'elements', 'marks']
		.forEach((attribute, index) => this.layers[attribute] = layers[index]);

	this.layers.background.add('rect', {
		x: 0,
		y: 0,
		width: this.options.canvas.width,
		height: this.options.canvas.height
	});

	// Render goes first as it is needed for getBBox to work.
	if (this.options.container) {
		this.render(this.options.container);
	}

	if (options.show_timestamp) {
		const elements = this.canvas.getElementsByAttributes({class: 'map-timestamp'});

		if (elements.length == 0) {
			throw 'timestamp element is missing';
		}
		else {
			this.timestamp = elements[0];
		}
	}

	this.update(this.options);
}

/**
 * Get rendered promise.
 *
 * @return {Promise<void>}
 */
SVGMap.prototype.promiseRendered = function () {
	return this.rendered_promise;
};

// Predefined list of fonts for maps.
SVGMap.FONTS = [
	'Georgia, serif',
	'"Palatino Linotype", "Book Antiqua", Palatino, serif',
	'"Times New Roman", Times, serif',
	'Arial, Helvetica, sans-serif',
	'"Arial Black", Gadget, sans-serif',
	'"Comic Sans MS", cursive, sans-serif',
	'Impact, Charcoal, sans-serif',
	'"Lucida Sans Unicode", "Lucida Grande", sans-serif',
	'Tahoma, Geneva, sans-serif',
	'"Trebuchet MS", Helvetica, sans-serif',
	'Verdana, Geneva, sans-serif',
	'"Courier New", Courier, monospace',
	'"Lucida Console", Monaco, monospace'
];

SVGMap.LABEL_TYPE_LABEL = MAP_LABEL_TYPE_LABEL;
SVGMap.LABEL_TYPE_IP = MAP_LABEL_TYPE_IP;
SVGMap.LABEL_TYPE_NAME = MAP_LABEL_TYPE_NAME;
SVGMap.LABEL_TYPE_STATUS = MAP_LABEL_TYPE_STATUS;
SVGMap.LABEL_TYPE_NOTHING = MAP_LABEL_TYPE_NOTHING;
SVGMap.LABEL_TYPE_CUSTOM = MAP_LABEL_TYPE_CUSTOM;

/**
 * Get image URL.
 *
 * @param {number|string} id  Image ID.
 *
 * @return {string}           Image URL.
 */
SVGMap.prototype.getImageUrl = function (id) {
	return this.imageUrl + id;
};

/**
 * Get image from image cache.
 *
 * @param {number|string} id  Image ID.
 *
 * @return {object|null}      Image object or null if image object is not present in cache.
 */
SVGMap.prototype.getImage = function (id) {
	if (id !== undefined && this.imageCache.images[id] !== undefined) {
		return this.imageCache.images[id];
	}

	return null;
};

/**
 * Update background image.
 *
 * @param {string} background  Background image ID.
 */
SVGMap.prototype.updateBackground = function (background) {
	let element = null;

	if (background && background != 0) {
		if (this.background !== null && background === this.options.background) {
			// Background was not changed.
			return;
		}

		const image = this.getImage(background);

		element = this.layers.background.add('image', {
			x: 0,
			y: 0,
			width: image.naturalWidth,
			height: image.naturalHeight,
			'xlink:href': this.getImageUrl(background)
		});
	}

	if (this.background !== null) {
		this.background.remove();
	}

	this.background = element;
};

/**
 * Set grid size.
 *
 * @param {number} size  Grid size. Setting grid size to 0 turns of the grid.
 */
SVGMap.prototype.setGrid = function (size) {
	this.layers.grid.clear();

	if (size == 0) {
		return;
	}

	for (let x = size; x < this.options.canvas.width; x += size) {
		this.layers.grid.add('line', {
			x1: x,
			y1: 0,
			x2: x,
			y2: this.options.canvas.height
		});

		this.layers.grid.add('text', {
			x: x + 3,
			y: 9 + 3,
			'stroke-width': 0
		}, x);
	}

	for (let y = size; y < this.options.canvas.height; y += size) {
		this.layers.grid.add('line', {
			x1: 0,
			y1: y,
			x2: this.options.canvas.width,
			y2: y
		});

		this.layers.grid.add('text', {
			x: 3,
			y: y + 12,
			'stroke-width': 0
		}, y);
	}

	this.layers.grid.add('text', {
		x: 2,
		y: 12,
		'stroke-width': 0
	}, 'Y X:');
};

/**
 * Compare map object attributes to determine if attributes were changed.
 *
 * @param {object} source  Object to be compared.
 * @param {object} target  Object to be compared with.
 *
 * @return {boolean}       True if objects attributes are different, false if object attributes are the same.
 */
SVGMap.isChanged = function (source, target) {
	if (typeof source !== 'object' || source === null) {
		return true;
	}

	const keys = Object.keys(target);

	for (let i = 0; i < keys.length; i++) {
		if (typeof target[keys[i]] === 'object') {
			if (SVGMap.isChanged(source[keys[i]], target[keys[i]])) {
				return true;
			}
		}
		else {
			if (target[keys[i]] !== source[keys[i]]) {
				return true;
			}
		}
	}

	return false;
};

/**
 * Update map elements and links. First iterate through objects of specified type and check if they exist. If not,
 * remove them completely. Then if objects still exist iterate through objects of specified type again create new class
 * instance. After that iterate through elements and links adding the links to elements and calculate the coordinates
 * for elements. Then process the elements and links - first add links and then elements.
 *
 * @param {object}  data         Object with elements, links, corresponding ID fields and class names.
 * @param {boolean} incremental  Update method. If set to true, items are added to the existing set of map objects.
 */
SVGMap.prototype.updateElements = function (data, incremental) {
	const items = {},
		class_names = {},
		types = {},
		id_fields = {};

	Object.keys(data).forEach((key) => {
		items[key] = data[key].items;
		class_names[key] = data[key].class_name;
		types[key] = key;
		id_fields[key] = data[key].id_field;
	});

	if (incremental !== true) {
		for (const type in types) {
			Object.keys(this[type]).forEach((key) => {
				if (items[type].filter((item) => item[id_fields[type]] === key).length == 0) {
					this[type][key].remove();
					delete this[type][key];
				}
			});
		}
	}

	// Initialize classes.
	for (const type in types) {
		items[type].forEach((item) => {
			if (typeof this[type][item[id_fields[type]]] !== 'object') {
				this[type][item[id_fields[type]]] = new window[class_names[type]](this, {});
			}
		});
	}

	const processed_links = [],
		processed_elements = [];

	// Calculate x, y and other necessary options in order to draw links. And add only matching links to elements.
	items.elements.forEach((elements_item) => {
		this.elements[elements_item.selementid].updateOptions(elements_item);

		elements_item.links = [];

		items.links.forEach((links_item) => {
			if ((elements_item.selementid_orig !== undefined
					&& (elements_item.selementid_orig === links_item.selementid1
							|| elements_item.selementid_orig === links_item.selementid2
							|| elements_item.selementid === links_item.selementid1
							|| elements_item.selementid === links_item.selementid2))
					|| (elements_item.selementid === links_item.selementid1
							|| elements_item.selementid === links_item.selementid2)) {
				elements_item.links.push(links_item);
			}
		});
	});

	// If elements have links, first draw links and then the elements. Otherwise just draw the element.
	items.elements.forEach((elements_item) => {
		if (elements_item.links.length > 0) {
			elements_item.links.forEach((links_item) => {
				const linkid = links_item.linkid;

				if (!processed_links.includes(linkid)) {
					this.links[linkid].update(links_item);
					processed_links.push(linkid);
				}
			});
		}

		const selementid = elements_item.selementid;

		if (!processed_elements.includes(selementid)) {
			this.elements[selementid].update(elements_item);
			processed_elements.push(selementid);
		}
	});
};

/**
 * Update ordered map objects.
 *
 * @param {string}  type         Object type (name of SVGMap class attribute).
 * @param {string}  id_field     Field used to identify objects.
 * @param {string}  class_name   Class name used to create instance of a new object.
 * @param {object}  items        Array of map objects.
 * @param {boolean} incremental  Update method. If set to true, items are added to the existing set of map objects.
 */
SVGMap.prototype.updateOrderedItems = function (type, id_field, class_name, items, incremental) {
	if (incremental !== true) {
		Object.keys(this[type]).forEach((key) => {
			if (items.filter((item) => item[id_field] === key).length == 0) {
				this[type][key].remove();
				delete this[type][key];
			}
		});
	}

	items.forEach((item) => {
		if (typeof this[type][item[id_field]] !== 'object') {
			this[type][item[id_field]] = new window[class_name](this, {});
		}

		this[type][item[id_field]].update(item);
	});
};

/**
 * Update map objects based on specified options.
 *
 * @param {object}  options      Map options.
 * @param {boolean} incremental  Update method. If set to true, items are added to the existing set of map objects.
 */
SVGMap.prototype.update = function (options, incremental) {
	let invalidate = false;

	// Check for element and link changes only on map or widget refresh. Similar to edit mode when re-ordering.
	if (options.caller !== undefined) {
		/*
		 * Adding new or removing existing elements means that display the order of elements have changed. This includes
		 * highlights, links and selections. Changing an element type to a "host group elements" type can also trigger
		 * this. If element count is the same check only if order (zindex) has changed for element. If so, draw elements
		 * again. Other element properties like coordinates, image URL, width or height are checked later and complete
		 * redrawing is not necessary.
		 */
		invalidate = options.elements.length != this.options.elements.length;

		if (!invalidate) {
			const existing_elements = new Map(this.options.elements.map((element) => [element.selementid, element]));

			for (const element of options.elements) {
				const ex = existing_elements.get(element.selementid);

				if (ex && ex.zindex != element.zindex) {
					invalidate = true;

					break;
				}
			}
		}

		/*
		 * If no changes in element properties, check if links are added or removed. This also should trigger a complete
		 * element redrawing. If link count is the same, check if selement IDs have changed for same link. Meaning that
		 * a different node could be connected. In that case also draw elements again. Other link properties like color,
		 * draw style are checked later and can be applied in real time and redrawing is not necessary.
		 */
		if (!invalidate) {
			invalidate = options.links.length != this.options.links.length;

			if (!invalidate) {
				const existing_links = new Map(this.options.links.map((link) => [link.linkid, link]));

				for (const link of options.links) {
					const ex = existing_links.get(link.linkid);

					if (ex) {
						const [a1, a2] = [link.selementid1, link.selementid2].sort();
						const [b1, b2] = [ex.selementid1, ex.selementid2].sort();

						if (a1 !== b1 || a2 !== b2) {
							invalidate = true;

							break;
						}
					}
				}
			}
		}

		if (invalidate) {
			this.invalidate('selements');
		}
	}

	// Sort map elements and shapes.
	['elements', 'shapes'].forEach((key) => {
		options[key] = (options[key] ?? []).sort((a, b) => a.zindex - b.zindex);
	});

	this.options.label_location = options.label_location;

	// Collect the list of images.
	const images = {};

	Object.keys(options.elements).forEach((key) => {
		const element = options.elements[key];

		if (element.icon !== undefined) {
			images[element.icon] = this.getImageUrl(element.icon);
		}
	});

	if (options.background && options.background != 0) {
		images[options.background] = this.getImageUrl(options.background);
	}

	// Resize the canvas and move marks.
	if (options.canvas !== undefined && options.canvas.width !== undefined && options.canvas.height !== undefined
			&& this.canvas.resize(options.canvas.width, options.canvas.height)) {
		this.options.canvas = options.canvas;

		if (this.container !== null) {
			this.container.style.width = `${options.canvas.width}px`;
			this.container.style.height = `${options.canvas.height}px`;
		}

		if (options.show_timestamp) {
			this.timestamp.update({
				x: options.canvas.width,
				y: options.canvas.height - 6
			});
		}
	}

	this.rendered_promise = new Promise((resolve) => {
		// Images are preloaded before update.
		this.imageCache.preload(images, () => {
			try {
				// Shapes must be drawn fist as host group element links depend on shape around it and its positioning.
				this.updateOrderedItems('shapes', 'sysmap_shapeid', 'SVGMapShape', options.shapes, incremental);
				this.updateElements({
					elements: {
						class_name: 'SVGMapElement',
						id_field: 'selementid',
						items: options.elements
					},
					links: {
						class_name: 'SVGMapLink',
						id_field: 'linkid',
						items: options.links
					}},
					incremental
				);
				this.updateBackground(options.background);
			}
			catch(exception) {
				resolve();

				throw exception;
			}

			this.options = SVGElement.mergeAttributes(this.options, options);

			const readiness = [],
				container = typeof this.options.container !== 'object'
					? document.querySelector(this.options.container)
					: this.options.container;

			container.querySelectorAll('image').forEach((image) => {
				readiness.push(new Promise((resolve) => image.addEventListener('load', resolve)));
			});

			resolve(Promise
				.all(readiness)
				.then(this.select(this.selected_element_id))
			);
		});
	});

	// Timestamp (date on map) is updated.
	if (options.show_timestamp && options.timestamp !== undefined) {
		this.timestamp.element.textContent = options.timestamp;
	}
};

/**
 * Invalidate items based on type.
 *
 * @param {string} type  Object type (name of SVGMap class attribute).
 */
SVGMap.prototype.invalidate = function (type) {
	switch (type) {
		case 'shapes':
			Object.keys(this[type]).forEach((key) => {
				this[type][key].options = {};
				this[type][key].element.invalidate();
			});
			break;

		case 'selements':
			Object.values(this.elements).forEach((element) => {
				element.image.invalidate();
				['label', 'markers'].forEach((key) => element.removeItem(key));

				['highlight', 'selection'].forEach((key) => {
					if (element[key] !== null) {
						element[key].invalidate();
					}
				});
			});

			Object.values(this.links).forEach((element) => {
				element.remove();
				element.options = {};
			});
			break;
	}
};

/**
 * Render map within container.
 *
 * @param {mixed} container  DOM element or jQuery selector.
 */
SVGMap.prototype.render = function (container) {
	if (typeof container === 'string') {
		container = jQuery(container)[0];
	}
	this.canvas.render(container);
	this.container = container;
};

/**
 * Map element selection by element ID.
 *
 * @param selected_element_id
 */
SVGMap.prototype.select = function(selected_element_id) {
	for (const element_id in this.elements) {
		this.elements[element_id].toggleSelection(element_id == selected_element_id);
	}
}

/**
 * SVGMapElement class. Implements rendering of map elements (selements).
 *
 * @param {object} map      Parent map.
 * @param {object} options  Element attributes (match field names in data source).
 */
function SVGMapElement(map, options) {
	this.map = map;
	this.options = options;
	this.selection = null;
	this.highlight = null;
	this.image = null;
	this.label = null;
	this.markers = null;
}

// Predefined label positions.
SVGMapElement.LABEL_POSITION_NONE = null;
SVGMapElement.LABEL_POSITION_DEFAULT = MAP_LABEL_LOC_DEFAULT;
SVGMapElement.LABEL_POSITION_BOTTOM = MAP_LABEL_LOC_BOTTOM;
SVGMapElement.LABEL_POSITION_LEFT = MAP_LABEL_LOC_LEFT;
SVGMapElement.LABEL_POSITION_RIGHT = MAP_LABEL_LOC_RIGHT;
SVGMapElement.LABEL_POSITION_TOP = MAP_LABEL_LOC_TOP;

// Predefined element types and subtypes.
SVGMapElement.TYPE_HOST = SYSMAP_ELEMENT_TYPE_HOST;
SVGMapElement.TYPE_MAP = SYSMAP_ELEMENT_TYPE_MAP;
SVGMapElement.TYPE_TRIGGER = SYSMAP_ELEMENT_TYPE_TRIGGER;
SVGMapElement.TYPE_HOST_GROUP = SYSMAP_ELEMENT_TYPE_HOST_GROUP;
SVGMapElement.TYPE_IMAGE = SYSMAP_ELEMENT_TYPE_IMAGE;

SVGMapElement.SUBTYPE_HOST_GROUP = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS;

SVGMapElement.AREA_TYPE_FIT = SYSMAP_ELEMENT_AREA_TYPE_FIT;
SVGMapElement.AREA_TYPE_CUSTOM = SYSMAP_ELEMENT_AREA_TYPE_CUSTOM;

/**
 * Remove part (item) of an element.
 *
 * @param {string} item  Item to be removed.
 */
SVGMapElement.prototype.removeItem = function (item) {
	if (this[item] !== null) {
		this[item].remove();
		this[item] = null;
	}
};

/**
 * Remove element.
 */
SVGMapElement.prototype.remove = function () {
	['selection', 'highlight', 'image', 'label', 'markers'].forEach((name) => this.removeItem(name));

	delete this.map.elements[this.options.selementid];
};

/**
 * Update element hovered/selected indicators.
 */
SVGMapElement.prototype.updateSelection = function () {
	if (!this.map.can_select_element || (this.options.elementtype != SVGMapElement.TYPE_HOST
			&& this.options.elementtype != SVGMapElement.TYPE_HOST_GROUP)) {
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
};

/**
 * Update element highlight (shape and markers placed on the background of element).
 */
SVGMapElement.prototype.updateHighlight = function() {
	let type = null,
		options = null;

	if (this.options.latelyChanged) {
		const radius = Math.floor(this.width / 2) + 12,
			markers = [];

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_BOTTOM) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: `rotate(90 ${this.center.x + 8},${this.center.y + radius})` +
						` translate(${this.center.x + 8},${this.center.y + radius})`
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_LEFT) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: `rotate(180 ${this.center.x - radius},${this.center.y + 8})` +
						` translate(${this.center.x - radius},${this.center.y + 8})`
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_RIGHT) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: `translate(${this.center.x + radius},${this.center.y - 8})`
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_TOP) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: `rotate(270 ${this.center.x - 8},${this.center.y - radius})` +
						` translate(${this.center.x - 8},${this.center.y - radius})`
				}
			});
		}

		const element = this.map.layers.elements.add('g', {fill: '#F44336', stroke: '#B71C1C'}, markers, this.image);

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
};

/**
 * Update element image. Image should be pre-loaded and placed in cache before calling this method.
 */
SVGMapElement.prototype.updateImage = function() {
	const options = {
		x: this.x,
		y: this.y,
		width: this.width,
		height: this.height
	};

	if (this.options.actions !== null && this.options.actions !== 'null' && this.options.actions !== undefined) {
		const actions = JSON.parse(this.options.actions);

		// Don't draw context menu and hand cursor for image elements with no links.
		if (actions.data.elementtype != SVGMapElement.TYPE_IMAGE || actions.data.urls.length != 0) {
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

		if (this.image === null || this.image.invalid) {
			const image = this.map.layers.elements.add('image', options);

			this.removeItem('image');
			this.image = image;

			if (this.map.can_select_element && (this.options.elementtype == SVGMapElement.TYPE_HOST
					|| this.options.elementtype == SVGMapElement.TYPE_HOST_GROUP)) {
				this.image.element.addEventListener('mouseover', (e) => this.onMouseOver(e));
				this.image.element.addEventListener('mouseout', (e) => this.onMouseOut(e));
				this.image.element.addEventListener('click', () => this.onClick());
			}
		}
		else {
			this.image.update(options);
		}
	}
	else {
		this.removeItem('image');
	}
};

/**
 * Update element label.
 */
SVGMapElement.prototype.updateLabel = function() {
	let x = this.center.x,
		y = this.center.y;

	const anchor = {
		horizontal: 'left',
		vertical: 'top'
	};

	switch (+this.options.label_location) {
		case SVGMapElement.LABEL_POSITION_BOTTOM:
			y = this.y + this.height + this.map.canvas.textPadding;
			anchor.horizontal = 'center';
			break;

		case SVGMapElement.LABEL_POSITION_LEFT:
			x = this.x - this.map.canvas.textPadding;
			anchor.horizontal = 'right';
			anchor.vertical = 'middle';
			break;

		case SVGMapElement.LABEL_POSITION_RIGHT:
			x = this.x + this.width + this.map.canvas.textPadding;
			anchor.vertical = 'middle';
			break;

		case SVGMapElement.LABEL_POSITION_TOP:
			y = this.y - this.map.canvas.textPadding;
			anchor.horizontal = 'center';
			anchor.vertical = 'bottom';
			break;
	}

	if (this.options.label !== '') {
		const element = this.map.layers.elements.add('textarea', {
			x,
			y,
			fill: `#${this.map.options.theme.textcolor}`,
			anchor,
			background: {
				fill: `#${this.map.options.theme.backgroundcolor}`,
				opacity: 0.7
			}
		}, this.options.label);

		if (this.label !== null) {
			this.label.replace(element);
		}
		else {
			this.label = element;
		}
	}
	else {
		this.removeItem('label');
	}
};

/**
 * Update element options like coordinates of x, y, width, height etc.
 *
 * @param {object} options  Element attributes.
 */
SVGMapElement.prototype.updateOptions = function(options) {
	const image = this.map.getImage(options.icon);

	if (image === null) {
		throw 'Invalid element configuration!';
	}

	// Data type normalization.
	['x', 'y', 'width', 'height', 'label_location'].forEach((name) => {
		if (options[name] !== undefined) {
			options[name] = parseInt(options[name]);
		}
	});

	// Inherit label location from map options.
	if (options.label_location == SVGMapElement.LABEL_POSITION_DEFAULT) {
		options.label_location = parseInt(this.map.options.label_location);
	}

	if (options.width !== undefined && options.height !== undefined) {
		options.x += Math.floor(options.width / 2) - Math.floor(image.naturalWidth / 2);
		options.y += Math.floor(options.height / 2) - Math.floor(image.naturalHeight / 2);
	}

	options.width = image.naturalWidth;
	options.height = image.naturalHeight;

	if (options.label === '') {
		options.label_location = SVGMapElement.LABEL_POSITION_NONE;
	}

	if (SVGMap.isChanged(this.options, options) === false) {
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
 * Update element selection, highlight, image and label.
 */
SVGMapElement.prototype.update = function() {
	this.updateImage();
	this.updateSelection();
	this.updateHighlight();
	this.updateLabel();
};

/**
 * Element mouse over event.
 */
SVGMapElement.prototype.onMouseOver = function(e) {
	if (e.target.classList.contains('selected')) {
		return;
	}

	this.selection.element.classList.remove('display-none');
};

/**
 * Element mouse out event.
 */
SVGMapElement.prototype.onMouseOut = function(e) {
	if (e.target.classList.contains('selected')) {
		return;
	}

	this.selection.element.classList.add('display-none');
};

/**
 * Element click event.
 */
SVGMapElement.prototype.onClick = function() {
	this.map.container.dispatchEvent(new CustomEvent(this.map.EVENT_ELEMENT_SELECT, {
		detail: {
			selected_element_id: this.options.selementid,
			hostid: this.options.elementtype == SVGMapElement.TYPE_HOST
				? this.options.elements[0].hostid
				: null,
			hostgroupid: this.options.elementtype == SVGMapElement.TYPE_HOST_GROUP
				? this.options.elements[0].groupid
				: null
		}
	}));
};

/**
 * Select element.
 */
SVGMapElement.prototype.toggleSelection = function(is_selected) {
	if (this.selection === null) {
		return;
	}

	this.selection.element.classList.toggle('display-none', !is_selected);
	this.selection.element.classList.toggle('selected', is_selected);
	this.image.element.classList.toggle('selected', is_selected);
};

/**
 * SVGMapLink class. Implements rendering of map links.
 *
 * @param {object} map      Parent map.
 * @param {object} options  Link attributes.
 */
function SVGMapLink(map, options) {
	this.map = map;
	this.options = options;
	this.element = null;
}

// Predefined set of line styles
SVGMapLink.LINE_STYLE_DEFAULT = MAP_LINK_DRAWTYPE_LINE;
SVGMapLink.LINE_STYLE_BOLD = MAP_LINK_DRAWTYPE_BOLD_LINE;
SVGMapLink.LINE_STYLE_DOTTED = MAP_LINK_DRAWTYPE_DOT;
SVGMapLink.LINE_STYLE_DASHED = MAP_LINK_DRAWTYPE_DASHED_LINE;

/**
 * Update link.
 *
 * @param {object} options  Link attributes (match field names in data source).
 */
SVGMapLink.prototype.update = function(options) {
	// Data type normalization.
	options.drawtype = parseInt(options.drawtype);
	options.elements = [this.map.elements[options.selementid1], this.map.elements[options.selementid2]];

	if (options.elements[0] === undefined || options.elements[1] === undefined) {
		let remove = true;

		if (options.elements[0] === options.elements[1]) {
			// Check if link is from hostgroup to hostgroup.
			options.elements = [
				this.map.shapes[`e-${options.selementid1}`],
				this.map.shapes[`e-${options.selementid2}`]
			];

			remove = options.elements[0] === undefined || options.elements[1] === undefined;
		}

		if (remove) {
			// Invalid link configuration.
			this.remove();

			return;
		}
	}

	options.elements[0] = options.elements[0].center;
	options.elements[1] = options.elements[1].center;
	options.center = {
		x: options.elements[0].x + Math.floor((options.elements[1].x - options.elements[0].x) / 2),
		y: options.elements[0].y + Math.floor((options.elements[1].y - options.elements[0].y) / 2)
	};

	if (SVGMap.isChanged(this.options, options) === false) {
		// No need to update.
		return;
	}

	this.options = options;

	const attributes = {
		stroke: `#${options.color}`,
		'stroke-width': 1,
		fill: `#${this.map.options.theme.backgroundcolor}`
	};

	switch (options.drawtype) {
		case SVGMapLink.LINE_STYLE_BOLD:
			attributes['stroke-width'] = 2;
			break;

		case SVGMapLink.LINE_STYLE_DOTTED:
			attributes['stroke-dasharray'] = '1,2';
			break;

		case SVGMapLink.LINE_STYLE_DASHED:
			attributes['stroke-dasharray'] = '4,4';
			break;
	}

	const element = this.map.layers.elements.add('g', attributes, [
		{
			type: 'line',
			attributes: {
				x1: options.elements[0].x,
				y1: options.elements[0].y,
				x2: options.elements[1].x,
				y2: options.elements[1].y
			}
		}
	]);

	element.add('textarea', {
			x: options.center.x,
			y: options.center.y,
			fill: `#${this.map.options.theme.textcolor}`,
			'font-size': '10px',
			'stroke-width': 0,
			anchor: {
				horizontal: 'center',
				vertical: 'middle'
			},
			background: {
			}
		}, options.label
	);

	if (this.element !== null) {
		this.element.replace(element);
	}
	else {
		this.element = element;
	}
};

/**
 * Remove link.
 */
SVGMapLink.prototype.remove = function () {
	if (this.element !== null) {
		this.element.remove();
		this.element = null;
	}
};

/**
 * SVGMapShape class. Implements rendering of map shapes.
 *
 * @param {object} map      Parent map.
 * @param {object} options  Shape attributes.
 */
function SVGMapShape(map, options) {
	this.map = map;
	this.options = options;
	this.element = null;
}

// Set of map shape types.
SVGMapShape.TYPE_RECTANGLE = SYSMAP_SHAPE_TYPE_RECTANGLE;
SVGMapShape.TYPE_ELLIPSE = SYSMAP_SHAPE_TYPE_ELLIPSE;
SVGMapShape.TYPE_LINE = SYSMAP_SHAPE_TYPE_LINE;

// Label horizontal alignments.
SVGMapShape.LABEL_HALIGN_LEFT = SYSMAP_SHAPE_LABEL_HALIGN_LEFT;
SVGMapShape.LABEL_HALIGN_RIGHT = SYSMAP_SHAPE_LABEL_HALIGN_RIGHT;

// Label vertical alignments.
SVGMapShape.LABEL_VALIGN_TOP = SYSMAP_SHAPE_LABEL_VALIGN_TOP;
SVGMapShape.LABEL_VALIGN_BOTTOM = SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM;

// Border types (@see dash-array of SVG) for maps.
SVGMapShape.BORDER_TYPE_NONE = SYSMAP_SHAPE_BORDER_TYPE_NONE;
SVGMapShape.BORDER_TYPE_SOLID = SYSMAP_SHAPE_BORDER_TYPE_SOLID;
SVGMapShape.BORDER_TYPE_DOTTED = SYSMAP_SHAPE_BORDER_TYPE_DOTTED;
SVGMapShape.BORDER_TYPE_DASHED = SYSMAP_SHAPE_BORDER_TYPE_DASHED;
SVGMapShape.BORDER_TYPES = {
	[SVGMapShape.BORDER_TYPE_NONE]: '',
	[SVGMapShape.BORDER_TYPE_SOLID]: 'none',
	[SVGMapShape.BORDER_TYPE_DOTTED]: '1,2',
	[SVGMapShape.BORDER_TYPE_DASHED]: '4,4'
};

/**
 * Update shape.
 *
 * @param {object} options  Shape attributes (match field names in data source).
 */
SVGMapShape.prototype.update = function(options) {
	if (SVGMap.isChanged(this.options, options) === false) {
		// No need to update.
		return;
	}

	this.options = options;

	['x', 'y', 'width', 'height'].forEach((name) => this[name] = parseInt(options[name]));

	this.rx = Math.floor(this.width / 2);
	this.ry = Math.floor(this.height / 2);

	this.center = {
		x: this.x + this.rx,
		y: this.y + this.ry
	};

	let type,
		element,
		clip = {},
		attributes = {};

	const mapping = [
		{
			key: 'background_color',
			value: 'fill'
		},
		{
			key: 'border_color',
			value: 'stroke'
		}
	];

	mapping.forEach((map) => {
		const color = `#${options[map.key].toString().trim()}`;

		attributes[map.value] = isColorHex(color) ? color : 'none';
	});

	if (options.border_width !== undefined) {
		attributes['stroke-width'] = parseInt(options.border_width);
	}

	if (options.border_type !== undefined) {
		let border_type = SVGMapShape.BORDER_TYPES[parseInt(options.border_type)];

		if (border_type !== '' && border_type !== 'none' && attributes['stroke-width'] > 1) {
			const parts = border_type.split(',').map((value) => parseInt(value));

			// Make dots round.
			if (parts[0] == 1 && attributes['stroke-width'] > 2) {
				attributes['stroke-linecap'] = 'round';
			}

			border_type = parts.map((part) => {
				if (part == 1 && attributes['stroke-width'] > 2) {
					return 1;
				}

				return part * attributes['stroke-width'];
			}).join(',');
		}

		if (border_type !== '') {
			attributes['stroke-dasharray'] = border_type;
		}
		else {
			attributes['stroke-width'] = 0;
		}
	}

	switch (parseInt(options.type)) {
		case SVGMapShape.TYPE_RECTANGLE:
			type = 'rect';
			attributes = SVGElement.mergeAttributes(attributes, {
				x: this.x,
				y: this.y,
				width: this.width,
				height: this.height
			});

			clip = {
				x: this.x,
				y: this.y,
				width: this.width,
				height: this.height
			};
			break;

		case SVGMapShape.TYPE_ELLIPSE:
			type = 'ellipse';
			attributes = SVGElement.mergeAttributes(attributes, {
				cx: this.center.x,
				cy: this.center.y,
				rx: this.rx,
				ry: this.ry
			});

			clip = {
				cx: this.center.x,
				cy: this.center.y,
				rx: this.rx,
				ry: this.ry
			};
			break;

		case SVGMapShape.TYPE_LINE:
			type = 'line';

			delete attributes['fill'];
			delete options['text'];

			attributes = SVGElement.mergeAttributes(attributes, {
				x1: this.x,
				y1: this.y,
				x2: this.width,
				y2: this.height
			});
		break;

		default:
			throw 'Invalid shape configuration!';
	}

	if (options.text === undefined || options.text.trim() === '') {
		element = this.map.layers.shapes.add(type, attributes);
	}
	else {
		element = this.map.layers.shapes.add('g', null, [{type, attributes}]);

		let x = this.center.x,
			y = this.center.y;

		const anchor = {
			horizontal: 'center',
			vertical: 'middle'
		};

		switch (parseInt(options.text_halign)) {
			case SVGMapShape.LABEL_HALIGN_LEFT:
				x = this.x + this.map.canvas.textPadding;
				anchor.horizontal = 'left';
				break;

			case SVGMapShape.LABEL_HALIGN_RIGHT:
				x = this.x + this.width - this.map.canvas.textPadding;
				anchor.horizontal = 'right';
				break;
		}

		switch (parseInt(options.text_valign)) {
			case SVGMapShape.LABEL_VALIGN_TOP:
				y = this.y + this.map.canvas.textPadding;
				anchor.vertical = 'top';
				break;

			case SVGMapShape.LABEL_VALIGN_BOTTOM:
				y = this.y + this.height - this.map.canvas.textPadding;
				anchor.vertical = 'bottom';
				break;
		}

		const font_color = `#${options.font_color.toString().trim()}`;

		element.add('textarea', {
			x,
			y,
			fill: isColorHex(font_color) ? font_color : '#000000',
			'font-family': SVGMap.FONTS[parseInt(options.font)],
			'font-size': `${parseInt(options.font_size)}px`,
			anchor,
			clip: {
				type,
				attributes: clip
			},
			'parse-links': true
		}, options.text);
	}

	if (this.element !== null) {
		this.element.replace(element);
	}
	else {
		this.element = element;
	}
};

/**
 * Remove shape.
 */
SVGMapShape.prototype.remove = function () {
	if (this.element !== null) {
		delete this.map.shapes[this.options.sysmap_shapeid];

		this.element.remove();
		this.element = null;
	}
};
