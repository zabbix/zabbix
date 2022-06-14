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


/**
 * SVGMap class.
 *
 * Implements vector map rendering functionality.
 */
function SVGMap(options) {
	var container,
		layers;

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
	if (typeof this.options.show_timestamp === 'undefined') {
		this.options.show_timestamp = true;
	}

	// Extra group for font styles.
	container = this.canvas.add('g', {
		class: 'map-container',
		'font-family': SVGMap.FONTS[9],
		'font-size': '10px'
	});

	var layers_to_add = [
		//  Background.
		{
			type: 'g',
			attributes: {
				class: 'map-background',
				fill: '#' + options.theme.backgroundcolor
			}
		},
		// Grid.
		{
			type: 'g',
			attributes: {
				class: 'map-grid',
				stroke: '#' + options.theme.gridcolor,
				fill: '#' + options.theme.gridcolor,
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
		// Highlights of elements.
		{
			type: 'g',
			attributes: {
				class: 'map-highlights'
			}
		},
		// Links.
		{
			type: 'g',
			attributes: {
				class: 'map-links'
			}
		},
		// Elements.
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

	layers = container.add(layers_to_add);

	['background', 'grid', 'shapes', 'highlights', 'links', 'elements', 'marks'].forEach(function (attribute, index) {
		this.layers[attribute] = layers[index];
	}, this);

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
		var elements = this.canvas.getElementsByAttributes({class: 'map-timestamp'});
		if (elements.length == 0) {
			throw 'timestamp element is missing';
		}
		else {
			this['timestamp'] = elements[0];
		}
	}
	this.update(this.options);
}

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

// Predefined border types (@see dash-array of SVG) for maps.
SVGMap.BORDER_TYPES = {
	'0': '',
	'1': 'none',
	'2': '1,2',
	'3': '4,4'
};

/**
 * Convert array of objects to hashmap (object).
 *
 * @param {array}     array   Array of objects.
 * @param {string}    key     Object field used to identify object.
 *
 * @return {object} Hashmap.
 */
SVGMap.toHashmap = function (array, key) {
	var hashmap = {};

	array.forEach(function (item) {
		if (typeof item !== 'object' || typeof item[key] === 'undefined') {
			// Skip elements that are not objects.
			return;
		}

		hashmap[item[key]] = item;
	});

	return hashmap;
};

/**
 * Get image url.
 *
 * @param {number|string}    id     Image id.
 *
 * @return {string} Image url.
 */
SVGMap.prototype.getImageUrl = function (id) {
	return this.imageUrl + id;
};

/**
 * Get image from image cache.
 *
 * @param {number|string}    id     Image id.
 *
 * @return {object} Image object or null if image object is not present in cache.
 */
SVGMap.prototype.getImage = function (id) {
	if (typeof id !== 'undefined' && typeof this.imageCache.images[id] !== 'undefined') {
		return this.imageCache.images[id];
	}

	return null;
};

/**
 * Update background image.
 *
 * @param {string}    background     Background image id.
 */
SVGMap.prototype.updateBackground = function (background) {
	var element = null;

	if (background && background !== '0') {
		if (this.background !== null && background === this.options.background) {
			// Background was not changed.
			return;
		}

		var image = this.getImage(background);

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
 * @param {number}    size     Grid size. Setting grid size to 0 turns of the grid.
 */
SVGMap.prototype.setGrid = function (size) {
	this.layers.grid.clear();

	if (size === 0) {
		return;
	}

	for (var x = size; x < this.options.canvas.width; x += size) {
		this.layers.grid.add('line', {
			'x1': x,
			'y1': 0,
			'x2': x,
			'y2': this.options.canvas.height
		});

		this.layers.grid.add('text', {
			'x': x + 3,
			'y': 9 + 3,
			'stroke-width': 0
		}, x);
	}

	for (var y = size; y < this.options.canvas.height; y += size) {
		this.layers.grid.add('line', {
			'x1': 0,
			'y1': y,
			'x2': this.options.canvas.width,
			'y2': y
		});

		this.layers.grid.add('text', {
			'x': 3,
			'y': y + 12,
			'stroke-width': 0
		}, y);
	}

	this.layers.grid.add('text', {
		'x': 2,
		'y': 12,
		'stroke-width': 0
	}, 'Y X:');
};

/**
 * Compare objects.  * Used to compare map object attributes to determine if attributes were changed.
 *
 * @param {object} source	Object to be compared.
 * @param {object} target	Object to be compared with.
 *
 * @return {boolean}		True if objects attributes are different, false if object attributes are the same.
 */
SVGMap.isChanged = function (source, target) {
	if (typeof source !== 'object' || source === null) {
		return true;
	}

	var keys = Object.keys(target);

	for (var i = 0; i < keys.length; i++) {
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
 * Update map objects. Iterate through map objects of specified type and update object attributes.
 *
 * @param {string}    type         Object type (name of SVGMap class attribute).
 * @param {string}    className    Class name used to create instance of a new object.
 * @param {object}    items        Hashmap of map objects.
 * @param {boolean}   incremental  Update method. If set to true, items are added to the existing set of map objects.
 */
SVGMap.prototype.updateItems = function (type, className, items, incremental) {
	var keys = Object.keys(items);

	if (incremental !== true) {
		Object.keys(this[type]).forEach(function (key) {
			if (keys.indexOf(key) === -1) {
				this[type][key].remove();
			}
		}, this);
	}

	keys.forEach(function (key) {
		if (typeof this[type][key] !== 'object') {
			this[type][key] = new window[className](this, {});
		}

		this[type][key].update(items[key]);
	}, this);
};

/**
 * Update ordered map objects.
 *
 * @param {string}    type         Object type (name of SVGMap class attribute).
 * @param {string}    idField      Field used to identify objects.
 * @param {string}    className    Class name used to create instance of a new object.
 * @param {object}    items        Array of map objects.
 * @param {boolean}   incremental  Update method. If set to true, items are added to the existing set of map objects.
 */
SVGMap.prototype.updateOrderedItems = function (type, idField, className, items, incremental) {
	if (incremental !== true) {
		Object.keys(this[type]).forEach(function (key) {
			if (items.filter(function (item) {
				return item[idField] == key;
			}).length === 0) {
				this[type][key].remove();
			}
		}, this);
	}

	items.forEach(function (item) {
		if (typeof this[type][item[idField]] !== 'object') {
			this[type][item[idField]] = new window[className](this, {});
		}

		this[type][item[idField]].update(item);
	}, this);
};

/**
 * Update map objects based on specified options.
 *
 * @param {object}    options      Map options.
 * @param {boolean}   incremental  Update method. If set to true, items are added to the existing set of map objects.
 */
SVGMap.prototype.update = function (options, incremental) {
	var images = {},
		rules = [
			{
				name: 'elements',
				field: 'selementid'
			},
			{
				name: 'links',
				field: 'linkid'
			}
		];

	// elements and links are converted into hashmap as order is not important.
	rules.forEach(function (rule) {
		if (typeof options[rule.name] !== 'undefined') {
			options[rule.name] = SVGMap.toHashmap(options[rule.name], rule.field);
		}
		else {
			options[rule.name] = {};
		}
	});

	// Performs ordering of shapes based on zindex value.
	if (typeof options.shapes === 'undefined') {
		options.shapes = [];
	}
	else {
		options.shapes = options.shapes.sort(function (a,b) {
			return a.zindex - b.zindex;
		});
	}

	this.options.label_location = options.label_location;

	// Collect the list of images.
	Object.keys(options.elements).forEach(function (key) {
		var element = options.elements[key];
		if (typeof element.icon !== 'undefined') {
			images[element.icon] = this.getImageUrl(element.icon);
		}
	}, this);

	if (options.background && options.background !== '0') {
		images[options.background] = this.getImageUrl(options.background);
	}

	// Resize the canvas and move marks
	if (typeof options.canvas !== 'undefined' && typeof options.canvas.width !== 'undefined'
			&& typeof options.canvas.height !== 'undefined'
			&& this.canvas.resize(options.canvas.width, options.canvas.height)) {

		this.options.canvas = options.canvas;

		if (this.container !== null) {
			this.container.style.width = options.canvas.width + 'px';
			this.container.style.height = options.canvas.height + 'px';
		}

		if (options.show_timestamp) {
			this.timestamp.update({
				x: options.canvas.width,
				y: options.canvas.height - 6
			});
		}
	}

	// Images are preloaded before update.
	this.imageCache.preload(images, function () {
		// Update is performed after preloading all of the images.
		this.updateItems('elements', 'SVGMapElement', options.elements, incremental);
		this.updateOrderedItems('shapes', 'sysmap_shapeid', 'SVGMapShape', options.shapes, incremental);
		this.updateItems('links', 'SVGMapLink', options.links, incremental);
		this.updateBackground(options.background, incremental);

		this.options = SVGElement.mergeAttributes(this.options, options);
	}, this);

	// Timestamp (date on map) is updated.
	if (options.show_timestamp && typeof options.timestamp !== 'undefined') {
		this.timestamp.element.textContent = options.timestamp;
	}
};

/**
 * Invalidate items based on type.
 *
 * @param {string}    type      Object type (name of SVGMap class attribute).
 */
SVGMap.prototype.invalidate = function (type) {
	Object.keys(this[type]).forEach(function (key) {
		this[type][key].options = {};
		this[type][key].element.invalidate();
	}, this);
};

/**
 * Render map within container.
 *
 * @param {mixed}    container      DOM element or jQuery selector.
 */
SVGMap.prototype.render = function (container) {
	if (typeof container === 'string') {
		container = jQuery(container)[0];
	}
	this.canvas.render(container);
	this.container = container;
};

/*
 * SVGMapElement class. Implements rendering of map elements (selements).
 *
 * @param {object}    map       Parent map.
 * @param {object}    options   Element attributes (match field names in data source).
 */
function SVGMapElement(map, options) {
	this.map = map;
	this.options = options;
	this.highlight = null;
	this.image = null;
	this.label = null;
	this.markers = null;
}

// Predefined label positions.
SVGMapElement.LABEL_POSITION_NONE		= null;
SVGMapElement.LABEL_POSITION_DEFAULT	= -1;
SVGMapElement.LABEL_POSITION_BOTTOM		= 0;
SVGMapElement.LABEL_POSITION_LEFT		= 1;
SVGMapElement.LABEL_POSITION_RIGHT		= 2;
SVGMapElement.LABEL_POSITION_TOP		= 3;

/**
 * Remove part (item) of an element.
 *
 * @param {string}    item      Item to be removed.
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
	['highlight', 'image', 'label', 'markers'].forEach(function (name) {
		this.removeItem(name);
	}, this);

	delete this.map.elements[this.options.selementid];
};

/**
 * Update element highlight (shape and markers placed on the background of element).
 */
SVGMapElement.prototype.updateHighlight = function() {
	var type = null,
		options = null;

	if (this.options.latelyChanged) {
		var radius = Math.floor(this.width / 2) + 12,
			markers = [];

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_BOTTOM) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'rotate(90 ' + (this.center.x+8) + ',' + (this.center.y+radius) + ') translate(' +
						(this.center.x+8) + ',' + (this.center.y+radius) + ')'
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_LEFT) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'rotate(180 ' + (this.center.x-radius) + ',' + (this.center.y+8) + ') translate(' +
						(this.center.x-radius) + ',' + (this.center.y+8) + ')'
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_RIGHT) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'translate(' + (this.center.x+radius) + ',' + (this.center.y-8) + ')'
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_TOP) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'rotate(270 ' + (this.center.x-8) + ',' + (this.center.y-radius) + ') translate(' +
						(this.center.x-8) + ',' + (this.center.y-radius) + ')'
				}
			});
		}

		var element = this.map.layers.highlights.add('g', {
			fill: '#F44336',
			stroke: '#B71C1C'
		}, markers);

		this.removeItem('markers');
		this.markers = element;
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
				fill: '#' + this.options.highlight.st,
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
				fill: '#' + this.options.highlight.hl
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
		if (this.highlight === null || type !== this.highlight.type) {
			var element = this.map.layers.highlights.add(type, options);
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
	var image,
		options =  {
			x: this.x,
			y: this.y,
			width: this.width,
			height: this.height
		};

	if (this.options.actions !== null && this.options.actions !== 'null'
			&& typeof this.options.actions !== 'undefined') {
		var actions = JSON.parse(this.options.actions);

		// 4 - SYSMAP_ELEMENT_TYPE_IMAGE. Don't draw context menu and hand cursor for image elements with no links.
		if (actions.data.elementtype != 4 || actions.data.urls.length != 0) {
			options['data-menu-popup'] = this.options.actions;
			options['style'] = 'cursor: pointer';
		}
	}

	if (typeof this.options.icon !== 'undefined') {
		var href = this.map.getImageUrl(this.options.icon);
		// 2 - PERM_READ
		if (2 > this.options.permission) {
			href += '&unavailable=1';
		}

		if (this.image === null || this.image.attributes['xlink:href'] !== href) {
			options['xlink:href'] = href;

			var image = this.map.layers.elements.add('image', options);
			this.removeItem('image');
			this.image = image;
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
	var x = this.center.x,
		y = this.center.y,
		anchor = {
			horizontal: 'left',
			vertical: 'top'
		};

	switch (this.options.label_location) {
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

	if (this.options.label !== null) {
		var element = this.map.layers.elements.add('textarea', {
			'x': x,
			'y': y,
			fill: '#' + this.map.options.theme.textcolor,
			'anchor': anchor,
			background: {
				fill: '#' + this.map.options.theme.backgroundcolor,
				opacity: 0.7
			}
		}, this.options.label);

		this.removeItem('label');
		this.label = element;
	}
	else {
		this.removeItem('label');
	}
};

/**
 * Update element (highlight, image and label).
 *
 * @param {object}    options      Element attributes.
 */
SVGMapElement.prototype.update = function(options) {
	var image = this.map.getImage(options.icon);

	if (image === null) {
		throw "Invalid element configuration!";
	}

	// Data type normalization.
	['x', 'y', 'width', 'height', 'label_location'].forEach(function(name) {
		if (typeof options[name] !== 'undefined') {
			options[name] = parseInt(options[name]);
		}
	});

	// Inherit label location from map options.
	if (options.label_location === SVGMapElement.LABEL_POSITION_DEFAULT) {
		options.label_location = parseInt(this.map.options.label_location);
	}

	if (typeof options.width !== 'undefined' && typeof options.height !== 'undefined') {
		options.x += Math.floor(options.width / 2) - Math.floor(image.naturalWidth / 2);
		options.y += Math.floor(options.height / 2) - Math.floor(image.naturalHeight / 2);
	}

	options.width = image.naturalWidth;
	options.height = image.naturalHeight;

	if (options.label === null) {
		options.label_location = SVGMapElement.LABEL_POSITION_NONE;
	}

	if (SVGMap.isChanged(this.options, options) === false) {
		// No need to update.
		return;
	}

	this.options = options;

	if (this.x !== options.x || this.y !== options.y || this.width !== options.width
			|| this.height !== options.height) {
		['x', 'y', 'width', 'height'].forEach(function(name) {
			this[name] = options[name];
		}, this);

		this.center = {
			x: this.x + Math.floor(this.width / 2),
			y: this.y + Math.floor(this.height / 2)
		};
	}

	this.updateHighlight();
	this.updateImage();
	this.updateLabel();
};

/**
 * SVGMapLink class. Implements rendering of map links.
 *
 * @param {object}    map       Parent map.
 * @param {object}    options   Link attributes.
 */
function SVGMapLink(map, options) {
	this.map = map;
	this.options = options;
	this.element = null;
}

// Predefined set of line styles
SVGMapLink.LINE_STYLE_DEFAULT	= 0;
SVGMapLink.LINE_STYLE_BOLD		= 2;
SVGMapLink.LINE_STYLE_DOTTED	= 3;
SVGMapLink.LINE_STYLE_DASHED	= 4;

/**
 * Update link.
 *
 * @param {object}    options   Link attributes (match field names in data source).
 */
SVGMapLink.prototype.update = function(options) {
	// Data type normalization.
	options.drawtype = parseInt(options.drawtype);
	options.elements = [this.map.elements[options.selementid1], this.map.elements[options.selementid2]];

	if (typeof options.elements[0] === 'undefined' || typeof options.elements[1] === 'undefined') {
		var remove = true;

		if (options.elements[0] === options.elements[1]) {
			// Check if link is from hostgroup to hostgroup.
			options.elements = [
				this.map.shapes['e-' + options.selementid1],
				this.map.shapes['e-' + options.selementid2]
			];

			remove = (typeof options.elements[0] === 'undefined' || typeof options.elements[1] === 'undefined');
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
		x: options.elements[0].x + Math.floor((options.elements[1].x - options.elements[0].x)/2),
		y: options.elements[0].y + Math.floor((options.elements[1].y - options.elements[0].y)/2)
	};

	if (SVGMap.isChanged(this.options, options) === false) {
		// No need to update.
		return;
	}

	this.options = options;
	this.remove();

	var attributes = {
		stroke: '#' + options.color,
		'stroke-width': 1,
		fill: '#' + this.map.options.theme.backgroundcolor
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

	this.element = this.map.layers.links.add('g', attributes, [
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

	this.element.add('textarea', {
			x: options.center.x,
			y: options.center.y,
			fill: '#' + this.map.options.theme.textcolor,
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
 * @param {object}    map       Parent map.
 * @param {object}    options   Shape attributes.
 */
function SVGMapShape(map, options) {
	this.map = map;
	this.options = options;
	this.element = null;
}

// Predefined set of map shape types.
SVGMapShape.TYPE_RECTANGLE	= 0;
SVGMapShape.TYPE_ELLIPSE	= 1;
SVGMapShape.TYPE_LINE		= 2;

// Predefined label horizontal alignments.
SVGMapShape.LABEL_HALIGN_CENTER	= 0;
SVGMapShape.LABEL_HALIGN_LEFT	= 1;
SVGMapShape.LABEL_HALIGN_RIGHT	= 2;

// Predefined label vertical alignments.
SVGMapShape.LABEL_VALIGN_MIDDLE	= 0;
SVGMapShape.LABEL_VALIGN_TOP	= 1;
SVGMapShape.LABEL_VALIGN_BOTTOM	= 2;

/**
 * Update shape.
 *
 * @param {object}    options        Shape attributes (match field names in data source).
 */
SVGMapShape.prototype.update = function(options) {
	if (SVGMap.isChanged(this.options, options) === false) {
		// No need to update.
		return;
	}

	this.options = options;

	['x', 'y', 'width', 'height'].forEach(function(name) {
		this[name] = parseInt(options[name]);
	}, this);

	this.rx = Math.floor(this.width / 2);
	this.ry = Math.floor(this.height / 2);

	this.center = {
		x: this.x + this.rx,
		y: this.y + this.ry
	};

	var type,
		element,
		clip = {},
		attributes = {},
		mapping = [
			{
				key: 'background_color',
				value: 'fill'
			},
			{
				key: 'border_color',
				value: 'stroke'
			}
		];

	mapping.forEach(function(map) {
		if (typeof options[map.key] !== 'undefined' && /[0-9A-F]{6}/g.test(options[map.key].trim())) {
			attributes[map.value] = '#' + options[map.key];
		}
		else {
			attributes[map.value] = 'none';
		}
	}, this);

	if (typeof options['border_width'] !== 'undefined') {
		attributes['stroke-width'] = parseInt(options['border_width']);
	}

	if (typeof options['border_type'] !== 'undefined') {
		var border_type = SVGMap.BORDER_TYPES[parseInt(options['border_type'])];

		if (border_type !== '' && border_type !== 'none' && attributes['stroke-width'] > 1) {
			var parts = border_type.split(',').map(function (value) {
				return parseInt(value);
			});

			// Make dots round.
			if (parts[0] === 1 && attributes['stroke-width'] > 2) {
				attributes['stroke-linecap'] = 'round';
			}

			border_type = parts.map(function (part) {
				if (part === 1 && attributes['stroke-width'] > 2) {
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
			throw "Invalid shape configuration!";
	}

	if (typeof options.text === 'undefined' || options.text.trim() === '') {
		element = this.map.layers.shapes.add(type, attributes);
	}
	else {
		element = this.map.layers.shapes.add('g', null, [{
			'type': type,
			'attributes': attributes
		}]);

		var x = this.center.x,
			y = this.center.y,
			anchor = {
				horizontal: 'center',
				vertical: 'middle'
			};

		switch (parseInt(options['text_halign'])) {
			case SVGMapShape.LABEL_HALIGN_LEFT:
				x = this.x + this.map.canvas.textPadding;
				anchor.horizontal = 'left';
				break;

			case SVGMapShape.LABEL_HALIGN_RIGHT:
				x = this.x + this.width - this.map.canvas.textPadding;
				anchor.horizontal = 'right';
				break;
		}

		switch (parseInt(options['text_valign'])) {
			case SVGMapShape.LABEL_VALIGN_TOP:
				y = this.y + this.map.canvas.textPadding;
				anchor.vertical = 'top';
				break;

			case SVGMapShape.LABEL_VALIGN_BOTTOM:
				y = this.y + this.height - this.map.canvas.textPadding;
				anchor.vertical = 'bottom';
				break;
		}

		element.add('textarea', {
			'x': x,
			'y': y,
			fill: '#' + (/[0-9A-F]{6}/g.test(options['font_color'].trim()) ? options['font_color'] : '000000'),
			'font-family': SVGMap.FONTS[parseInt(options.font)],
			'font-size': parseInt(options['font_size']) + 'px',
			'anchor': anchor,
			clip: {
				'type': type,
				'attributes': clip
			},
			'parse-links': true
		}, options.text);
	}

	this.replace(element);
};

/**
 * Replace shape.
 *
 * @see SVGElement.prototype.replace
 *
 * @param {object}    element   New shape element.
 */
SVGMapShape.prototype.replace = function (element) {
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
