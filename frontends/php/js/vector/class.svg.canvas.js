/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

function SVGCanvas(options, shadowBuffer) {
	this.id = 0;

	this.options = options;
	this.elements = [];

	this.textPadding = 5;
	this.maskColor = '#3d3d3d';
	this.mask = false;
	if (options.mask !== undefined) {
		this.mask = (options.mask === true);
	}

	this.buffer = null;

	this.root = this.createElement('svg', {
		'width': options.width,
		'height': options.height
	}, null);

	if (shadowBuffer === true) {
		this.buffer = this.root.add('g', {
			id: 'shadow-buffer',
			style: 'visibility: hidden;'
		});
	}
}

SVGCanvas.NAMESPACES = {
	xlink: 'http://www.w3.org/1999/xlink'
};

SVGCanvas.getUniqueId = function () {
	if (SVGCanvas.uniqueId === undefined) {
		SVGCanvas.uniqueId = 0;
	}

	return SVGCanvas.uniqueId++;
};

SVGCanvas.prototype.createElement = function (type, attributes, parent, content) {
	var element;

	if(type.toLowerCase() === 'textarea') {
		element = this.createTextarea(attributes, parent, content);
	}
	else {
		element = new SVGElement(this, type, attributes, parent, content);
		this.elements.push(element);
	}

	return element;
};

SVGCanvas.prototype.createTextarea = function (attributes, parent, content) {
	if (typeof content === 'string' && content.trim() === '') {
		return;
	}

	var group = this.createElement('g', null, parent),
		x = attributes.x,
		y = attributes.y,
		anchor = attributes.anchor,
		background = attributes.background,
		clip = attributes.clip,
		lines = [],
		pos = [x, y],
		rect = null,
		offset = 0;
		skip = 0.9;

	['x', 'y', 'anchor', 'background', 'clip'].forEach(function (key) {
		delete attributes[key];
	});

	if (typeof anchor !== 'object') {
		anchor = {};
	}

	if (typeof background === 'object') {
		rect = group.add('rect', background);
		pos[0] -= this.textPadding;
		pos[1] -= this.textPadding;

		offset = this.textPadding;
	}

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
			lines.push( {
				type: 'tspan',
				attributes: SVGElement.mergeAttributes({
					x: offset,
					dy: skip + 'em',
					'text-anchor': 'middle'
				}, line.attributes),
				content: line.content.replace(/[\r\n]/g, '')
			});

			skip = 1.2;
		}
		else {
			skip += 1.2;
		}
	});

	var text = group.add('text', attributes, lines),
		size = text.element.getBBox(),
		width = Math.ceil(size.width),
		height = Math.ceil(size.height);

	if ((IE || ED) && lines.length > 0 &&
		attributes['font-size'] !== undefined && parseInt(attributes['font-size']) > 16) {
		height = Math.ceil(lines.length * parseInt(attributes['font-size']) * 1.2);
	}

	switch (anchor.horizontal) {
		case 'center':
			pos[0] -= Math.floor(width/2);
		break;

		case 'right':
			pos[0] -= width;
		break;
	}

	switch (anchor.vertical) {
		case 'middle':
			pos[1] -= Math.floor(height/2);
		break;

		case 'bottom':
			pos[1] -= height;
		break;
	}

	if (rect !== null) {
		rect.element.setAttribute('width', width + (this.textPadding * 2));
		rect.element.setAttribute('height', height + (this.textPadding * 2));
	}

	if (clip !== undefined)
	{
		if (clip.attributes.x !== undefined && clip.attributes.y !== undefined) {
			clip.attributes.x -= (pos[0] + Math.floor(width/2));
			clip.attributes.y -= pos[1];
		}
		else if (clip.attributes.cx !== undefined && clip.attributes.cy !== undefined) {
			clip.attributes.cx -= (pos[0] + Math.floor(width/2));
			clip.attributes.cy -= pos[1];
		}

		var uniqueId = SVGCanvas.getUniqueId();
		if (this.mask) {
			clip.attributes.fill = '#ffffff';
			group.add('mask', {
				id: 'mask-' + uniqueId
			}, [
				{
					type: 'rect',
					attributes: {
						x: -Math.floor(width / 2),
						y: 0,
						'width': width,
						'height': height,
						fill: this.maskColor
					}
				},
				clip
			]);

			text.element.setAttribute('mask', 'url(#mask-' + uniqueId + ')');
		}
		else {
			group.add('clipPath', {
				id: 'clip-' + uniqueId
			}, [clip]);

			text.element.setAttribute('clip-path', 'url(#clip-' + uniqueId + ')');
		}
	}

	text.element.setAttribute('transform', 'translate(' + Math.floor(width/2) + ' ' + offset + ')');
	group.element.setAttribute('transform', 'translate(' + pos.join(' ') + ')');

	return group;
};

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

SVGCanvas.prototype.add = function (type, attributes, content) {
	return this.root.add(type, attributes, content);
};

SVGCanvas.prototype.render = function (container) {
	if (this.root.element.parentNode) {
		this.root.element.parentNode.removeChild(this.root.element);
	}

	container.appendChild(this.root.element);
};

SVGCanvas.prototype.resize = function (width, height) {
	if (this.options.width !== width || this.options.height !== height) {
		this.options.width = width;
		this.options.height = height;
		this.root.update({'width': width, 'height': height});

		return true;
	}

	return false;
};

function ImageCache() {
	this.lock = 0;
	this.images = {};
	this.context = null;
	this.callback = null;

	this.queue = [];
}

ImageCache.prototype.invokeCallback = function () {
	if (typeof this.callback === 'function') {
		this.callback.call(this.context);
	}

	var task = this.queue.pop();
	if (task !== undefined) {
		this.preload(task.urls, task.callback, task.context);
	}
};

ImageCache.prototype.handleCallback = function () {
	this.lock--;

	if (this.lock === 0) {
		this.invokeCallback();
	}
};

ImageCache.prototype.onImageLoaded = function (id, image) {
	this.images[id] = image;
	this.handleCallback();
};

ImageCache.prototype.onImageError = function (id) {
	this.images[id] = null;
	this.handleCallback();
};

ImageCache.prototype.preload = function (urls, callback, context) {
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

		if (object.images[key] !== undefined) {
			return; /* preloaded */
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

SVGElement.prototype.add = function (type, attributes, content) {
	/* multiple items to add */
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

	if (attributes === undefined || attributes === null) {
		attributes = {};
	}

	var element = this.canvas.createElement(type, attributes, this, content);
	if (type.toLowerCase() !== 'textarea') {
		this.items.push(element);
	}

	return element;
};

SVGElement.prototype.clear = function () {
	var items = this.items;

	items.forEach(function (item) {
		item.remove();
	});

	this.items = [];

	return this;
};

SVGElement.prototype.update = function (attributes) {
	Object.keys(attributes).forEach(function (name) {
		var attribute = name.split(':');

		if (attribute.length === 1) {
			this.element.setAttributeNS(null, name, attributes[name]);
		}
		else if (attribute.length === 2 && SVGCanvas.NAMESPACES[attribute[0]] !== undefined) {
			this.element.setAttributeNS(SVGCanvas.NAMESPACES[attribute[0]], name, attributes[name]);
		}
	}, this);

	return this;
};

SVGElement.prototype.moveTo = function (target) {
	this.parent.items = this.parent.items.filter(function (item) {
		return item.id !== this.id;
	}, this);

	this.parent = target;
	this.parent.items.push(this);
	target.element.appendChild(this.element);

	return this;
};

SVGElement.prototype.invalidate = function () {
	this.invalid = true;
	return this;
};

SVGElement.prototype.remove = function () {
	this.clear();

	if (this.element !== null) {
		/* .remove() does not work in IE */
		if (typeof this.element.remove !== 'function') {
			if (this.element.parentNode !== undefined) {
				this.element.parentNode.removeChild(this.element);
			}
		}
		else {
			this.element.remove();
		}
		this.element = null;
	}

	if (this.parent !== null && this.parent.items !== undefined) {
		this.parent.items = this.parent.items.filter(function (item) {
			return item.id !== this.id;
		}, this);
	}

	return this;
};

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

SVGElement.prototype.create = function () {
	var element = document.createElementNS('http://www.w3.org/2000/svg', this.type);

	this.remove();

	this.element = element;
	this.update(this.attributes);

	if (Array.isArray(this.content)) {
		this.content.forEach(function (element) {
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

	if (this.parent !== null && this.parent.element !== null) {
		this.parent.element.appendChild(element);
	}

	return element;
};

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
