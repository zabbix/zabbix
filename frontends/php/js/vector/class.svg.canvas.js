/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * SVGCanvas class.
 *
 * Implements basic functionality needed to render SVG from JS.
 *
 * @param {object}	options				Canvas options.
 * @param {number}	options.width		Canvas width (width attribute of a SVG image).
 * @param {number}	options.height		Canvas height (height attribute of a SVG image).
 * @param {boolean}	options.mask		Masking option for textarea elements (@see SVGCanvas.prototype.createTextarea)
 * @param {boolean}	shadowBuffer		Shadow buffer (double buffering) support. If set to true, additional hidden
 *										group element is created within SVG.
 */
function SVGCanvas(options, shadowBuffer) {
	this.options = options;
	this.id = 0;
	this.elements = [];
	this.textPadding = 5;
	this.maskColor = '#3d3d3d';
	this.mask = false;

	if (typeof options.mask !== 'undefined') {
		this.mask = (options.mask === true);
	}
	if (typeof options.useViewBox !== 'boolean') {
		options.useViewBox = false;
	}

	this.buffer = null;

	var svg_options = options.useViewBox
		? {
			'viewBox': '0 0 ' + options.width + ' ' + options.height,
			'width': '100%',
			'height': '100%',
			'style': 'max-width: ' + options.width + 'px; max-height: ' + options.height + 'px;'
		}
		: {
			'width': options.width,
			'height': options.height
		};

	this.root = this.createElement('svg', svg_options, null);
	if (shadowBuffer === true) {
		this.buffer = this.root.add('g', {
			class: 'shadow-buffer',
			style: 'visibility: hidden;'
		});
	}
}

// Predefined namespaces for SVG as key => value
SVGCanvas.NAMESPACES = {
	xlink: 'http://www.w3.org/1999/xlink'
};

/**
 * Generate unique id.
 * Id is unique within page context.
 *
 * @return {number} Unique id.
 */
SVGCanvas.getUniqueId = function () {
	if (typeof SVGCanvas.uniqueId === 'undefined') {
		SVGCanvas.uniqueId = 0;
	}

	return SVGCanvas.uniqueId++;
};

/**
 * Create new SVG element.
 * Additional workaround is added to implement textarea element as a text element with a set of tspan subelements.
 *
 * @param {string}     type             Element type (SVG tag).
 * @param {object}     attributes       Element attributes (SVG tag attributes) as key => value pairs.
 * @param {SVGElement} parent           Parent element if any (or null if none).
 * @param {mixed}      content          Element textContent of a set of subelements.
 *
 * @return {SVGElement} Created element.
 */
SVGCanvas.prototype.createElement = function (type, attributes, parent, content) {
	var element;

	if(type.toLowerCase() === 'textarea') {
		var textarea = new SVGTextArea(this);
		element = textarea.create(attributes, parent, content);
	}
	else {
		element = new SVGElement(this, type, attributes, parent, content);
		this.elements.push(element);
	}

	return element;
};

/**
 * Get elements by specified attributes.
 *
 * SVG elements with specified attributes are returned as array of SVGElement (if any).
 *
 * @return {array} Elements that match specified attributes.
 */
SVGCanvas.prototype.getElementsByAttributes = function (attributes) {
	var names = Object.keys(attributes),
		elements = this.elements.filter(function (item) {
			for (var i = 0; i < names.length; i++) {
				if (item.attributes[names[i]] !== attributes[names[i]]) {
					return false;
				}
			}

			return true;
		});

	return elements;
};

/**
 * Add element to the SVG root element (svg tag).
 *
 * @return {SVGElement} Created element.
 */
SVGCanvas.prototype.add = function (type, attributes, content) {
	return this.root.add(type, attributes, content);
};

/**
 * Attach SVG element to the specified container in DOM.
 *
 * @param {object}     container       DOM node.
 */
SVGCanvas.prototype.render = function (container) {
	if (this.root.element.parentNode) {
		this.root.element.parentNode.removeChild(this.root.element);
	}

	container.appendChild(this.root.element);
};

/**
 * Resize canvas.
 *
 * @param {number}     width       New width.
 * @param {number}     height      New height.
 *
 * @return {boolean} true if size is changed and false if size is the same as previous.
 */
SVGCanvas.prototype.resize = function (width, height) {
	if (this.options.width !== width || this.options.height !== height) {
		this.options.width = width;
		this.options.height = height;
		this.root.update({'width': width, 'height': height});

		return true;
	}

	return false;
};

/**
 * SVGTextArea class.
 *
 * Implements textarea (multiline text) for svg.
 *
 * @param {object}     canvas       Instance of SVGCanvas.
 *
 */
function SVGTextArea(canvas) {
	this.canvas = canvas;
	this.element = null;
}

/**
 * Parse text line and extract links as <a> elements.
 *
 * @param {string} text		Text line to be parsed.
 *
 * @return {mixed}			Parsed text as {array} if links are present or as {string} if there are no links in text.
 */
SVGTextArea.parseLinks = function (text) {
	var index,
		offset = 0,
		link,
		parts = [];

	while ((index = text.search(/((ftp|file|https?):\/\/[^\s]+)/i)) !== -1) {
		if (offset !== index) {
			parts.push(text.substring(offset, index));
		}

		text = text.substring(index);
		index = text.search(/\s/);

		if (index === -1) {
			index = text.length;
		}

		link = text.substring(0, index);
		text = text.substring(index);
		offset = 0;
		parts.push({
			type: 'a',
			attributes: {
				href: link,
				onclick: 'window.location = ' + JSON.stringify(link) + '; return false;' // Workaround for Safari.
			},
			content: link
		});
	}

	if (text !== '') {
		if (parts.length !== 0) {
			parts.push(text);
		}
		else {
			parts = text;
		}
	}

	return parts;
};

/**
 * Wrap text line to the specified width.
 *
 * @param {string} line		Text line to be wrapped.
 *
 * @return {array}			Wrapped line as {array} of strings.
 */
SVGTextArea.prototype.wrapLine = function (line) {
	if (this.canvas.buffer === null || typeof this.clip === 'undefined') {
		// No text wrapping without shadow buffer of clipping object.
		return [line];
	}

	var max_width = this.clip.attributes.width,
		current;

	if (typeof max_width === 'undefined' && typeof this.clip.attributes.rx !== 'undefined') {
		max_width = parseInt(this.clip.attributes.rx * 2, 10);
	}

	max_width -= this.canvas.textPadding * 2;

	if (typeof this.canvas.wrapper === 'undefined') {
		this.canvas.wrapper = {
			text: this.canvas.buffer.add('text', this.attributes),
			node: document.createTextNode('')
		};

		this.canvas.wrapper.text.element.appendChild(this.canvas.wrapper.node);
	}
	else {
		this.canvas.wrapper.text.update(this.attributes);
	}

	var text = this.canvas.wrapper.text.element,
		node = this.canvas.wrapper.node,
		size,
		wrapped = [];

	node.textContent = line;
	size = text.getBBox();

	// Check length of the line in pixels.
	if (Math.ceil(size.width) > max_width) {
		var words = line.split(' ');
			current = [];

		while (words.length > 0) {
			current.push(words.shift());
			node.textContent = current.join(' ');
			size = text.getBBox();

			if (Math.ceil(size.width) > max_width) {
				if (current.length > 1) {
					words.unshift(current.pop());
					wrapped.push(current.join(' '));
					current = [];
				}
				else {
					// Word is too long to fit the line.
					wrapped.push(current.pop());
				}
			}
		}

		if (current.length > 0) {
			wrapped.push(current.join(' '));
		}
	}
	else {
		wrapped.push(line);
	}

	return wrapped;
};

/**
 * Get horizontal offset (position in pixels) of text anchor.
 *
 * @return {numeric}		Horizontal offset in pixels.
 */
SVGTextArea.prototype.getHorizontalOffset = function () {
	switch (this.anchor.horizontal) {
		case 'center':
			return Math.floor(this.width/2);

		case 'right':
			return this.width;
	}

	return 0;
};

/**
 * Get text-anchor attribute value from horizontal anchor value.
 *
 * @return {string}		Value of text-anchor attribute.
 */
SVGTextArea.prototype.getHorizontalAnchor = function() {
	var mapping = {
		left: 'start',
		center: 'middle',
		right: 'end'
	};

	if (typeof mapping[this.anchor.horizontal] === 'string') {
		return mapping[this.anchor.horizontal];
	}

	return mapping.left;
};

/**
 * Parse content, get the lines, perform line wrapping and link parsing.
 *
 * @param {mixed}	content		Text contents or array of line objects.
 * @param {boolean} parse_links	Set to true if link parsing should be performed.
 *
 * @return {numeric}		Horizontal offset in pixels.
 */
SVGTextArea.prototype.parseContent = function(content, parse_links) {
	var skip = 0.9,
		anchor = this.getHorizontalAnchor();

	this.lines = [];

	if (typeof content === 'string') {
		var items = [];

		content.split("\n").forEach(function (line) {
			items.push({
				content: line,
				attributes: {}
			});
		});

		content = items;
	}

	content.forEach(function (line) {
		if (line.content.trim() !== '') {
			var content = line.content.replace(/[\r\n]/g, '');

			this.wrapLine(content).forEach(function (wrapped) {
				if (parse_links === true) {
					wrapped = SVGTextArea.parseLinks(wrapped);
				}

				this.lines.push({
					type: 'tspan',
					attributes: SVGElement.mergeAttributes({
						x: this.offset,
						dy: skip + 'em',
						'text-anchor': anchor
					}, line.attributes),
					content: wrapped
				});

				skip = 1.2;
			}, this);
		}
		else {
			skip += 1.2;
		}
	}, this);
};

/**
 * Align text position based on horizontal and vertical anchor values.
 */
SVGTextArea.prototype.alignToAnchor = function() {
	if (typeof this.anchor !== 'object') {
		this.anchor = {
			horizontal: 'left'
		};
	}

	this.x -= this.getHorizontalOffset();

	switch (this.anchor.vertical) {
		case 'middle':
			this.y -= Math.floor(this.height/2);
			break;

		case 'bottom':
			this.y -= this.height;
			break;
	}
};

/**
 * Create clipping object to clip (and/or mask) text outside the specified shape.
 */
SVGTextArea.prototype.createClipping = function() {
	if (typeof this.clip !== 'undefined') {
		var offset = this.getHorizontalOffset();
		// Clipping shape should be applied to the text. Clipping mode (clip or mask) depends on mask attribute.

		if (typeof this.clip.attributes.x !== 'undefined' && typeof this.clip.attributes.y !== 'undefined') {
			this.clip.attributes.x -= (this.x + offset);
			this.clip.attributes.y -= this.y;
		}
		else if (typeof this.clip.attributes.cx !== 'undefined' && typeof this.clip.attributes.cy !== 'undefined') {
			this.clip.attributes.cx -= (this.x + offset);
			this.clip.attributes.cy -= this.y;
		}

		var unique_id = SVGCanvas.getUniqueId();

		if (this.canvas.mask) {
			this.clip.attributes.fill = '#ffffff';
			this.element.add('mask', {
				id: 'mask-' + unique_id
			}, [{
					type: 'rect',
					attributes: {
						x: -offset,
						y: 0,
						'width': this.width,
						'height': this.height,
						fill: this.canvas.maskColor
					}
				},
				this.clip
			]);

			this.text.element.setAttribute('mask', 'url(#mask-' + unique_id + ')');
		}
		else {
			this.element.add('clipPath', {
				id: 'clip-' + unique_id
			}, [this.clip]);

			this.text.element.setAttribute('clip-path', 'url(#clip-' + unique_id + ')');
		}
	}
};

/**
 * Create new textarea element.
 *
 * Textarea element has poor support in supported browsers so following workaround is used. Textarea element is a text
 * element with a set of tspan subelements and additional logic for text background and masking / clipping.
 *
 * @param {string}		type							Element type (SVG tag).
 * @param {object}		attributes						Element attributes (SVG tag attributes).
 * @param {number}		attributes.x					Element position on x axis.
 * @param {number}		attributes.y					Element position on y axis.
 * @param {object}		attributes.anchor				Anchor used for text placement.
 * @param {string}		attributes.anchor.horizontal	Horizontal anchor used for text placement.
 * @param {string}		attributes.anchor.vertical		Vertical anchor used for text placement.
 * @param {object}		attributes.background			Attributes of rectangle placed behind text (text background).
 * @param {object}		attributes.clip					SVG element used for clipping or masking (depends on canvas mask option).
 * @param {SVGElement}	parent							Parent element if any (or null if none).
 * @param {mixed}		content							Element textContent of a set of subelements.
 *
 * @return {SVGElement} Created element.
 */
SVGTextArea.prototype.create = function(attributes, parent, content) {
	if (typeof content === 'string' && content.trim() === '') {
		return null;
	}

	if (Array.isArray(content)) {
		var i;

		for (i = 0; i < content.length; i++) {
			if (content[i].content.trim() !== '') {
				break;
			}
		}

		if (i === content.length) {
			return null;
		}
	}

	['x', 'y', 'anchor', 'background', 'clip'].forEach(function (key) {
		this[key] = attributes[key];
	}, this);

	this.offset = 0;
	this.element = this.canvas.createElement('g', null, parent);

	var parse_links = attributes['parse-links'],
		size;

	['x', 'y', 'anchor', 'background', 'clip', 'parse-links'].forEach(function (key) {
		delete attributes[key];
	});

	this.attributes = attributes;

	if (typeof this.background === 'object') {
		this.background = this.element.add('rect', this.background);
		this.x -= this.canvas.textPadding;
		this.y -= this.canvas.textPadding;
		this.offset = this.canvas.textPadding;
	}
	else {
		this.background = null;
	}

	this.parseContent(content, parse_links);
	this.text = this.element.add('text', attributes, this.lines);

	size = this.ZBX_getBBox();
	this.width = Math.ceil(size.width);
	this.height = Math.ceil(size.height + size.y);

	// Workaround for IE/EDGE for proper text height calculation.
	if ((IE || ED) && this.lines.length > 0
			&& typeof attributes['font-size'] !== 'undefined' && parseInt(attributes['font-size']) > 16) {
		this.height = Math.ceil(this.lines.length * parseInt(attributes['font-size']) * 1.2);
	}

	this.alignToAnchor();

	if (this.background !== null) {
		this.background.update({
			width: this.width + (this.canvas.textPadding * 2),
			height: this.height + (this.canvas.textPadding * 2)
		});
	}

	this.createClipping();

	this.text.element.setAttribute('transform', 'translate(' + this.getHorizontalOffset() + ' ' + this.offset + ')');
	this.element.element.setAttribute('transform', 'translate(' + this.x + ' ' + this.y + ')');

	return this.element;
};

/**
 * getBBox workaround for Firefox and probably also old versions of IE.
 *
 * Firefox is not able to get element dimensions using getBBox unless it is appended to the DOM.
 * The workaround creates a SVG element and appends it to the DOM to be able get element dimensions using the getBBox.
 *
 * Read more about this bug here https://bugzilla.mozilla.org/show_bug.cgi?id=612118
 */
SVGTextArea.prototype.ZBX_getBBox = function() {
	try {
		return this.text.element.getBBox();
	}
	catch (err) {
		var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
			ret;

		svg.appendChild(this.text.element);
		document.body.appendChild(svg);
		ret = this.text.element.getBBox();
		svg.parentNode.removeChild(svg);

		return ret;
	}
};

/**
 * ImageCache class.
 *
 * Implements basic functionality needed to preload images, get image attributes and avoid flickering.
 */
function ImageCache() {
	this.lock = 0;
	this.images = {};
	this.context = null;
	this.callback = null;
	this.queue = [];
}

/**
 * Invoke callback (if any), update image preload task queue.
 */
ImageCache.prototype.invokeCallback = function () {
	if (typeof this.callback === 'function') {
		this.callback.call(this.context);
	}

	// Preloads next image list if any.
	var task = this.queue.pop();

	if (typeof task !== 'undefined') {
		this.preload(task.urls, task.callback, task.context);
	}
};

/**
 * Handle image processing event (loaded or error).
 */
ImageCache.prototype.handleCallback = function () {
	this.lock--;

	// If all images are loaded (error is treated as "loaded"), invoke callback.
	if (this.lock === 0) {
		this.invokeCallback();
	}
};

/**
 * Callback for sucessful image load.
 *
 * @param {string}     id       Image id.
 * @param {object}     image    Loaded image.
 */
ImageCache.prototype.onImageLoaded = function (id, image) {
	this.images[id] = image;
	this.handleCallback();
};

/**
 * Callback for image loading errors.
 *
 * @param {string}     id       Image id.
 */
ImageCache.prototype.onImageError = function (id) {
	this.images[id] = null;
	this.handleCallback();
};

/**
 * Preload images.
 *
 * @param {object}		urls		Urls of images to be preloaded (urls are provided in key=>value format).
 * @param {function}	callback	Callback to be called when loading is finished. Can be null if no callback is needed.
 * @param {object}		context		Context of a callback. (@see first argument of Function.prototype.apply)
 *
 * @return {boolean} true if preloader started loading images and false if preloader is busy.
 */
ImageCache.prototype.preload = function (urls, callback, context) {
	// If preloader is busy, new preloading task is pushed to queue.
	if (this.lock !== 0) {
		this.queue.push({
			'urls':  urls,
			'callback': callback,
			'context': context
		});

		return false;
	}

	this.context = context;
	this.callback = callback;

	var images = 0;
	var object = this;

	Object.keys(urls).forEach(function (key) {
		var url = urls[key];

		if (typeof url !== 'string') {
			object.onImageError.call(object, key);

			return;
		}

		if (typeof object.images[key] !== 'undefined') {
			// Image is pre-loaded already.
			return true;
		}

		var image = new Image();

		image.onload = function () {
			object.onImageLoaded.call(object, key, image);
		};

		image.onerror = function () {
			object.onImageError.call(object, key);
		};

		image.src = url;

		object.lock++;
		images++;
	});

	if (images === 0) {
		this.invokeCallback();
	}

	return true;
};

/**
 * SVGElement class.
 *
 * Implements basic functionality needed to create SVG elements.
 *
 * @see SVGCanvas.prototype.createElement
 *
 * @param {SVGCanvas}  renderer    SVGCanvas used to render elements.
 * @param {string}     type        Type of SVG element.
 * @param {object}     attributes  Element attributes (SVG tag attributes) as key => value pairs.
 * @param {SVGElement} parent      Parent element if any (or null if none).
 * @param {mixed}      content     Element textContent of a set of subelements.
 */
function SVGElement(renderer, type, attributes, parent, content) {
	this.id = renderer.id++;
	this.type = type;
	this.attributes = attributes;
	this.content = content;
	this.canvas = renderer;
	this.parent = parent;
	this.items = [];
	this.element = null;
	this.invalid = false;

	if (type !== null) {
		this.create();
	}
}

/**
 * Add clild SVG element.
 *
 * @see SVGCanvas.prototype.createElement
 *
 * @param {mixed}      type        Type of SVG element or array of objects containing type, attribute and content fields.
 * @param {object}     attributes  Element attributes (SVG tag attributes) as key => value pairs.
 * @param {mixed}      content     Element textContent of a set of subelements.
 *
 * @return {mixed} SVGElement created or array of SVGElement is type was Array.
 */
SVGElement.prototype.add = function (type, attributes, content) {
	// Multiple items to add.
	if (Array.isArray(type)) {
		var items = [];

		type.forEach(function (element) {
			if (typeof element !== 'object' || typeof element.type !== 'string') {
				throw 'Invalid element configuration!';
			}

			items.push(this.add(element.type, element.attributes, element.content));
		}, this);

		return items;
	}

	if (typeof attributes === 'undefined' || attributes === null) {
		attributes = {};
	}

	var element = this.canvas.createElement(type, attributes, this, content);

	if (type.toLowerCase() !== 'textarea') {
		this.items.push(element);
	}

	return element;
};

/**
 * Remove all children elements.
 *
 * @return {SVGElement}
 */
SVGElement.prototype.clear = function () {
	var items = this.items;

	items.forEach(function (item) {
		item.remove();
	});

	this.items = [];

	return this;
};

/**
 * Update attributes of SVG element.
 *
 * @param {object} attributes		New element attributes (SVG tag attributes) as key => value pairs.
 *
 * @return {SVGElement}
 */
SVGElement.prototype.update = function (attributes) {
	Object.keys(attributes).forEach(function (name) {
		var attribute = name.split(':');

		if (attribute.length === 1) {
			this.element.setAttributeNS(null, name, attributes[name]);
		}
		else if (attribute.length === 2 && typeof SVGCanvas.NAMESPACES[attribute[0]] !== 'undefined') {
			this.element.setAttributeNS(SVGCanvas.NAMESPACES[attribute[0]], name, attributes[name]);
		}
	}, this);

	return this;
};

/**
 * Moves element from one parent to another.
 *
 * @param {object} target		New parent element.
 *
 * @return {SVGElement}
 */
SVGElement.prototype.moveTo = function (target) {
	this.parent.items = this.parent.items.filter(function (item) {
		return item.id !== this.id;
	}, this);

	this.parent = target;
	this.parent.items.push(this);
	target.element.appendChild(this.element);

	return this;
};

/**
 * Mark element as invalid (flag used to force redraw of element).
 *
 * @return {SVGElement}
 */
SVGElement.prototype.invalidate = function () {
	this.invalid = true;

	return this;
};

/**
 * Remove element from parent and from DOM.
 *
 * @return {SVGElement}
 */
SVGElement.prototype.remove = function () {
	this.clear();

	if (this.element !== null) {
		// Workaround for IE as .remove() does not work in IE.
		if (typeof this.element.remove !== 'function') {
			if (typeof this.element.parentNode !== 'undefined') {
				this.element.parentNode.removeChild(this.element);
			}
		}
		else {
			this.element.remove();
		}
		this.element = null;
	}

	if (this.parent !== null && typeof this.parent.items !== 'undefined') {
		this.parent.items = this.parent.items.filter(function (item) {
			return item.id !== this.id;
		}, this);
	}

	return this;
};

/**
 * Replace existing DOM element with a new one.
 *
 * @param {object} target		New DOM element.
 *
 * @return {SVGElement}
 */
SVGElement.prototype.replace = function (target) {
	if (this.element !== null && this.invalid === false) {
		this.element.parentNode.insertBefore(target.element, this.element);
	}

	this.remove();

	Object.keys(target).forEach(function (key) {
		this[key] = target[key];
	}, this);

	return this;
};

/**
 * Create SVG DOM element.
 *
 * @return {object} DOM element.
 */
SVGElement.prototype.create = function () {
	var element = (this.type !== '')
			? document.createElementNS('http://www.w3.org/2000/svg', this.type)
			: document.createTextNode(this.content);

	this.remove();
	this.element = element;

	if (this.type !== '') {
		this.update(this.attributes);

		if (Array.isArray(this.content)) {
			this.content.forEach(function (element) {
				if (typeof element === 'string') {
					// Treat element as a text node.
					element = {
						type: '',
						attributes: null,
						content: element
					};
				}

				if (typeof element !== 'object' || typeof element.type !== 'string') {
					throw 'Invalid element configuration!';
				}

				this.add(element.type, element.attributes, element.content);
			}, this);

			this.content = null;
		}
		else if ((/string|number|boolean/).test(typeof this.content)) {
			element.textContent = this.content;
		}
	}

	if (this.parent !== null && this.parent.element !== null) {
		this.parent.element.appendChild(element);
	}

	return element;
};

/**
 * Merge source and target attributes.  If both source and attributes contain the same set of keys, values from
 * attributes are used.
 *
 * @param {object}	source			Source object attributes.
 * @param {object}	attributes		New object attributes.
 *
 * @return {object}					Merged set of attributes.
 */
SVGElement.mergeAttributes = function (source, attributes) {
	var merged = {};

	if (typeof source === 'object') {
		Object.keys(source).forEach(function (key){
			merged[key] = source[key];
		});
	}

	if (typeof attributes === 'object') {
		Object.keys(attributes).forEach(function (key){
			merged[key] = attributes[key];
		});
	}

	return merged;
};
