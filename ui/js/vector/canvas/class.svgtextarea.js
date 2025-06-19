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
 * SVGTextArea class.
 *
 * Implements textarea (multiline text) for svg.
 *
 * @param {object} canvas  Instance of SVGCanvas.
 *
 */
class SVGTextArea {
	constructor(canvas) {
		this.canvas = canvas;
		this.element = null;
		this.resize_observer = null;
	}

	/**
	* Parse text line and extract links as <a> elements.
	*
	* @param {string} text  Text line to be parsed.
	*
	* @return {mixed}       Parsed text as {array} if links are present or as {string} if there are no links in text.
	*/
	static parseLinks(text) {
		let index,
			offset = 0,
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

			const link = text.substring(0, index);

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
	}

	/**
	 * Wrap text line to the specified width.
	 *
	 * @param {string} line  Text line to be wrapped.
	 *
	 * @return {array}       Wrapped line as {array} of strings.
	 */
	#wrapLine(line) {
		if (this.canvas.buffer === null || typeof this.clip === 'undefined') {
			// No text wrapping without shadow buffer of clipping object.
			return [line];
		}

		let max_width = this.clip.attributes.width;

		if (max_width === undefined && this.clip.attributes.rx !== undefined) {
			max_width = parseInt(this.clip.attributes.rx * 2, 10);
		}

		max_width -= this.canvas.textPadding * 2;

		if (this.canvas.wrapper === undefined) {
			this.canvas.wrapper = {
				text: this.canvas.buffer.add('text', this.attributes),
				node: document.createTextNode('')
			};

			this.canvas.wrapper.text.element.appendChild(this.canvas.wrapper.node);
		}
		else {
			this.canvas.wrapper.text.update(this.attributes);
		}

		const text = this.canvas.wrapper.text.element,
			node = this.canvas.wrapper.node,
			wrapped = [];

		node.textContent = line;

		let size = text.getBBox();

		// Check length of the line in pixels.
		if (Math.ceil(size.width) > max_width) {
			const words = line.split(' ');

			let current = [];

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
	}

	/**
	 * Get horizontal offset (position in pixels) of text anchor.
	 *
	 * @return {numeric} Horizontal offset in pixels.
	 */
	#getHorizontalOffset() {
		switch (this.anchor.horizontal) {
			case 'center':
				return Math.floor(this.width / 2);

			case 'right':
				return this.width;
		}

		return 0;
	}

	/**
	 * Get text-anchor attribute value from horizontal anchor value.
	 *
	 * @return {string} Value of text-anchor attribute.
	 */
	#getHorizontalAnchor() {
		const mapping = {
			left: 'start',
			center: 'middle',
			right: 'end'
		};

		if (typeof mapping[this.anchor.horizontal] === 'string') {
			return mapping[this.anchor.horizontal];
		}

		return mapping.left;
	}

	/**
	 * Parse content, get the lines, perform line wrapping and link parsing.
	 *
	 * @param {mixed}   content      Text contents or array of line objects.
	 * @param {boolean} parse_links  Set to true if link parsing should be performed.
	 *
	 * @return {numeric}             Horizontal offset in pixels.
	 */
	parseContent(content, parse_links) {
		let skip = 0.9;

		const anchor = this.#getHorizontalAnchor();

		this.lines = [];

		if (typeof content === 'string') {
			const items = [];

			content.split("\n").forEach((line) => {
				items.push({
					content: line,
					attributes: {}
				});
			});

			content = items;
		}

		content.forEach((line) => {
			if (line.content.trim() !== '') {
				const content = line.content.replace(/[\r\n]/g, '');

				this.#wrapLine(content).forEach((wrapped) => {
					if (parse_links === true) {
						wrapped = this.constructor.parseLinks(wrapped);
					}

					this.lines.push({
						type: 'tspan',
						attributes: SVGElement.mergeAttributes({
							x: this.offset,
							dy: `${skip}em`,
							'text-anchor': anchor
						}, line.attributes),
						content: wrapped
					});

					skip = 1.2;
				});
			}
			else {
				skip += 1.2;
			}
		});
	}

	/**
	 * Align text position based on horizontal and vertical anchor values.
	 */
	#alignToAnchor() {
		if (typeof this.anchor !== 'object') {
			this.anchor = {
				horizontal: 'left'
			};
		}

		this.x -= this.#getHorizontalOffset();

		switch (this.anchor.vertical) {
			case 'middle':
				this.y -= Math.floor(this.height/2);
				break;

			case 'bottom':
				this.y -= this.height;
				break;
		}
	}

	/**
	 * Create clipping object to clip (and/or mask) text outside the specified shape.
	 */
	#createClipping() {
		if (this.clip !== undefined) {
			const offset = this.#getHorizontalOffset(),
				unique_id = SVGCanvas.getUniqueId();

			// Clipping shape should be applied to the text. Clipping mode (clip or mask) depends on mask attribute.
			if (this.clip.attributes.x !== undefined && this.clip.attributes.y !== undefined) {
				this.clip.attributes.x -= (this.x + offset);
				this.clip.attributes.y -= this.y;
			}
			else if (this.clip.attributes.cx !== undefined && this.clip.attributes.cy !== undefined) {
				this.clip.attributes.cx -= (this.x + offset);
				this.clip.attributes.cy -= this.y;
			}

			if (this.canvas.mask) {
				this.clip.attributes.fill = '#ffffff';
				this.element.add('mask', {id: `mask-${unique_id}`}, [
					{
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

				this.text.element.setAttribute('mask', `url(#mask-${unique_id})`);
			}
			else {
				this.element.add('clipPath', {id: `clip-${unique_id}`}, [this.clip]);

				this.text.element.setAttribute('clip-path', `url(#clip-${unique_id})`);
			}
		}
	}

	/**
	 * Create new textarea element.
	 *
	 * Textarea element has poor support in supported browsers so following workaround is used. Textarea element is a
	 * text element with a set of tspan subelements and additional logic for text background and masking / clipping.
	 *
	 * @param {string}     type                          Element type (SVG tag).
	 * @param {object}     attributes                    Element attributes (SVG tag attributes).
	 * @param {number}     attributes.x                  Element position on x axis.
	 * @param {number}     attributes.y                  Element position on y axis.
	 * @param {object}     attributes.anchor             Anchor used for text placement.
	 * @param {string}     attributes.anchor.horizontal  Horizontal anchor used for text placement.
	 * @param {string}     attributes.anchor.vertical    Vertical anchor used for text placement.
	 * @param {object}     attributes.background         Attributes of rectangle placed behind text (text background).
	 * @param {object}     attributes.clip               SVG element used for clipping or masking (depends on canvas
	 *                                                   mask option).
	 * @param {SVGElement} parent                        Parent element if any (or null if none).
	 * @param {mixed}      content                       Element textContent of a set of subelements.
	 *
	 * @return {SVGElement}                              Created element.
	 */
	create(attributes, parent, content) {
		if (typeof content === 'string' && content.trim() === '') {
			return null;
		}

		if (this.resize_observer !== null) {
			this.resize_observer.unobserve(this.text.element);
			this.resize_observer = null;
		}

		if (Array.isArray(content)) {
			let i;

			for (i = 0; i < content.length; i++) {
				if (content[i].content.trim() !== '') {
					break;
				}
			}

			if (i === content.length) {
				return null;
			}
		}

		['x', 'y', 'anchor', 'background', 'clip'].forEach((key) => this[key] = attributes[key]);

		this.offset = 0;
		this.element = this.canvas.createElement('g', {}, parent);

		const parse_links = attributes['parse-links'];

		['x', 'y', 'anchor', 'background', 'clip', 'parse-links'].forEach((key) => delete attributes[key]);

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

		this.initial_x = this.x;
		this.initial_y = this.y;

		this.parseContent(content, parse_links);
		this.text = this.element.add('text', attributes, this.lines);

		const onResize = () => {
			if (this.text.element === null) {
				return;
			}

			this.y = this.initial_y;

			const size = this.text.element.getBBox();

			this.width = Math.ceil(size.width);
			this.height = Math.ceil(size.height + size.y);

			this.#alignToAnchor();

			if (this.background !== null) {
				this.background.update({
					width: this.width + (this.canvas.textPadding * 2),
					height: this.height + (this.canvas.textPadding * 2)
				});
			}

			this.#createClipping();

			this.text.element.setAttribute('transform', `translate(${this.#getHorizontalOffset()} ${this.offset})`);
			this.element.element.setAttribute('transform',
				`translate(${(this.initial_x - this.#getHorizontalOffset())} ${this.y})`
			);
		};

		if (this.text !== null && this.text.element !== null && 'data-parent' in this.text.attributes) {
			this.resize_observer = new ResizeObserver(onResize);
			this.resize_observer.observe(this.text.element);
		}

		onResize();

		return this.element;
	}
}
