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
 * SVGCanvas class.
 *
 * Implements basic functionality needed to render SVG from JS.
 *
 * @param {object}  options         Canvas options.
 * @param {number}  options.width   Canvas width (width attribute of a SVG image).
 * @param {number}  options.height  Canvas height (height attribute of a SVG image).
 * @param {boolean} options.mask    Masking option for textarea elements (@see SVGTextArea.#createClipping).
 * @param {boolean} shadow_buffer   Shadow buffer (double buffering) support. If set to true, additional hidden
 *                                  group element is created within SVG.
 */
class SVGCanvas {
	constructor(options, shadow_buffer) {
		this.options = options;
		this.id = 0;
		this.elements = [];
		this.textPadding = 5;
		this.maskColor = '#3d3d3d';
		this.mask = false;

		if (options.mask !== undefined) {
			this.mask = options.mask === true;
		}
		if (typeof options.useViewBox !== 'boolean') {
			options.useViewBox = false;
		}

		this.buffer = null;

		const svg_options = options.useViewBox
			? {
				'viewBox': `0 0 ${options.width} ${options.height}`,
				'style': `max-width: ${options.width}px; max-height: ${options.height}px;`,
				'preserveAspectRatio': 'xMinYMin meet'
			}
			: {
				'width': options.width,
				'height': options.height
			};

		this.root = this.createElement('svg', svg_options, null);

		if (shadow_buffer === true) {
			this.buffer = this.root.add('g', {
				class: 'shadow-buffer',
				style: 'visibility: hidden;'
			});
		}
	}

	// Predefined namespaces for SVG as key => value
	static NAMESPACES = {xlink: 'http://www.w3.org/1999/xlink'};

	/**
	 * Generate unique ID within page context.
	 *
	 * @return {number} Unique ID.
	 */
	static getUniqueId() {
		if (this.uniqueid === undefined) {
			this.uniqueid = 0;
		}

		return this.uniqueid++;
	}

	/**
	 * Create new SVG element. Additional workaround is added to implement textarea element as a text element with a set
	 * of tspan subelements.
	 *
	 * @param {string}     type        Element type (SVG tag).
	 * @param {object}     attributes  Element attributes (SVG tag attributes) as key => value pairs.
	 * @param {SVGElement} parent      Parent element if any (or null if none).
	 * @param {mixed}      content     Element textContent of a set of subelements.
	 * @param {object}     target      Target element before which current element should be added.
	 *
	 * @return {SVGElement} Created element.
	 */
	createElement(type, attributes, parent, content, target) {
		let element;

		if (type.toLowerCase() === 'textarea') {
			const textarea = new SVGTextArea(this);

			element = textarea.create(attributes, parent, content);
		}
		else {
			element = new SVGElement(this, type, attributes, parent, content, target);
			this.elements.push(element);
		}

		return element;
	}

	/**
	 * Get elements by specified attributes.
	 *
	 * SVG elements with specified attributes are returned as array of SVGElement (if any).
	 *
	 * @return {array}  Elements that match specified attributes.
	 */
	getElementsByAttributes(attributes) {
		const names = Object.keys(attributes);

		return this.elements.filter((item) => names.every((name) => item.attributes[name] === attributes[name]));
	}

	/**
	 * Add element to the SVG root element (SVG tag).
	 *
	 * @return {SVGElement}  Created element.
	 */
	add(type, attributes, content) {
		return this.root.add(type, attributes, content);
	}

	/**
	 * Attach SVG element to the specified container in DOM.
	 *
	 * @param {object} container  DOM node.
	 */
	render(container) {
		if (this.root.element.parentNode) {
			this.root.element.parentNode.removeChild(this.root.element);
		}

		container.appendChild(this.root.element);
	}

	/**
	 * Resize canvas.
	 *
	 * @param {number} width   New width.
	 * @param {number} height  New height.
	 *
	 * @return {boolean}       True if size is changed and false if size is the same as previous.
	 */
	resize(width, height) {
		if (this.options.width != width || this.options.height != height) {
			this.options.width = width;
			this.options.height = height;
			this.root.update({width, height});

			return true;
		}

		return false;
	}
}
