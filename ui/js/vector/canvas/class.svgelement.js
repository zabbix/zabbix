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
 * SVGElement class.
 *
 * Implements basic functionality needed to create SVG elements.
 *
 * @see SVGCanvas.createElement
 *
 * @param {SVGCanvas}   renderer    SVGCanvas used to render elements.
 * @param {string}      type        Type of SVG element.
 * @param {object}      attributes  Element attributes (SVG tag attributes) as key => value pairs.
 * @param {SVGElement}  parent      Parent element if any (or null if none).
 * @param {mixed}       content     Element textContent of a set of subelements.
 * @param {object}      target      Target element before which current element should be added.
 */
class SVGElement {
	constructor(renderer, type, attributes, parent, content, target) {
		this.id = renderer.id++;
		this.type = type;
		this.attributes = attributes;
		this.target = target;
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
	 * Merge source and target attributes.  If both source and attributes contain the same set of keys, values from
	 * attributes are used.
	 *
	 * @param {object} source      Source object attributes.
	 * @param {object} attributes  New object attributes.
	 *
	 * @return {object}            Merged set of attributes.
	 */
	static mergeAttributes(source, attributes) {
		const merged = {};

		if (typeof source === 'object') {
			Object.keys(source).forEach((key) => merged[key] = source[key]);
		}
		if (typeof attributes === 'object') {
			Object.keys(attributes).forEach((key) => merged[key] = attributes[key]);
		}

		return merged;
	}

	/**
	 * Add child SVG element.
	 *
	 * @see SVGCanvas.prototype.createElement
	 *
	 * @param {mixed}  type        Type of SVG element or array of objects containing type, attribute, content fields.
	 * @param {object} attributes  Element attributes (SVG tag attributes) as key => value pairs.
	 * @param {mixed}  content     Element textContent of a set of subelements.
	 * @param {object} target      Target element before which current element should be added.
	 *
	 * @return {mixed}             SVGElement created or array of SVGElement is type was Array.
	 */
	add(type, attributes, content, target) {
		// Multiple items to add.
		if (Array.isArray(type)) {
			const items = [];

			type.forEach((element) => {
				if (typeof element !== 'object' || typeof element.type !== 'string') {
					throw 'Invalid element configuration!';
				}

				items.push(this.add(element.type, element.attributes, element.content));
			});

			return items;
		}

		if (attributes === undefined || attributes === null) {
			attributes = {};
		}

		const element = this.canvas.createElement(type, attributes, this, content, target);

		if (type.toLowerCase() !== 'textarea') {
			this.items.push(element);
		}

		return element;
	}

	/**
	 * Remove all children elements.
	 *
	 * @return {SVGElement}
	 */
	clear() {
		const items = this.items;

		items.forEach((item) => item.remove());
		this.items = [];

		return this;
	}

	/**
	 * Update attributes of SVG element.
	 *
	 * @param {object} attributes  New element attributes (SVG tag attributes) as key => value pairs.
	 *
	 * @return {SVGElement}
	 */
	update(attributes) {
		Object.keys(attributes).forEach((name) => {
			const attribute = name.split(':');

			if (attribute.length == 1) {
				this.element.setAttributeNS(null, name, attributes[name]);
			}
			else if (attribute.length == 2 && SVGCanvas.NAMESPACES[attribute[0]] !== undefined) {
				this.element.setAttributeNS(SVGCanvas.NAMESPACES[attribute[0]], name, attributes[name]);
			}
		});

		// Update actual object attributes after this.element node map attributes have been updated already.
		this.attributes = attributes;

		return this;
	}

	/**
	 * Mark element as invalid (flag used to force redraw of element).
	 *
	 * @return {SVGElement}
	 */
	invalidate() {
		this.invalid = true;

		return this;
	}

	/**
	 * Remove element from parent and from DOM.
	 *
	 * @return {SVGElement}
	 */
	remove() {
		this.clear();

		if (this.element !== null) {
			this.element.remove();
			this.element = null;
		}

		if (this.parent !== null && this.parent.items !== undefined) {
			this.parent.items = this.parent.items.filter((item) => item.id !== this.id);
		}

		return this;
	}

	/**
	 * Replace existing DOM element with a new one.
	 *
	 * @param {object} target  New DOM element.
	 *
	 * @return {SVGElement}
	 */
	replace(target) {
		if (this.element !== null && this.invalid === false) {
			this.element.parentNode.insertBefore(target.element, this.element);
		}

		this.remove();

		Object.keys(target).forEach((key) => this[key] = target[key]);

		return this;
	}

	/**
	 * Create SVG DOM element.
	 *
	 * @return {object} DOM element.
	 */
	create() {
		const element = this.type !== ''
				? document.createElementNS('http://www.w3.org/2000/svg', this.type)
				: document.createTextNode(this.content);

		this.remove();
		this.element = element;

		if (this.type !== '') {
			this.update(this.attributes);

			if (Array.isArray(this.content)) {
				this.content.forEach((element) => {
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
				});

				this.content = null;
			}
			else if ((/string|number|boolean/).test(typeof this.content)) {
				element.textContent = this.content;
			}
		}

		if (this.target !== undefined) {
			this.target.element.parentNode.insertBefore(element, this.target.element);
		}
		else if (this.parent !== null && this.parent.element !== null) {
			this.parent.element.appendChild(element);
		}

		return element;
	}
}
