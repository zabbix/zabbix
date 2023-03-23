/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

const SVGNS = 'http://www.w3.org/2000/svg';
const XMLNS = 'http://www.w3.org/1999/xhtml';
const FONT_SIZE_RATIO = 0.82;


// Must be synced with PHP.
const DESC_V_POSITION_TOP = 0
const DESC_V_POSITION_BOTTOM = 1;

const UNITS_POSITION_BEFORE = 0;
const UNITS_POSITION_ABOVE = 1;
const UNITS_POSITION_AFTER = 2;
const UNITS_POSITION_BELOW = 3;

// If min/max block widths exceed this (current 1/4) of total SVG width, then limit the width of min/max blocks.
const MAX_WIDTH_MINMAX_RATIO = 0.25;

class CSVGGauge {
	// TO DO: add description?
	constructor(options, data) {
		this.options = options;
		this.container = options.container;
		this.data = data;

		// Width and height of the SVG.
		this.width = this.options.canvas.width;
		this.height = this.options.canvas.height;

		// Contains all the elements - description, value, min/max, thresholds ...
		this.elements = {};

		// Create the main SVG element.
		this.svg = document.createElementNS(SVGNS, 'svg');
		// TO DO: the preserveAspectRatio: 'xMidYMid' doesn't seem to do anything. At least for now. So probably delete this comment later.
		this.#addAttributesNS(this.svg, {width: this.width, height: this.height});
		this.#addAttributes(this.svg, {xmlns: SVGNS});

		// Set starting point.
		this.x = 0;
		this.y = 0;

		// TO DO: Background color set to parent DIV. But let's leave this in for now to test if saving image will work.
		// if (this.options.bg_color !== '') {
		// 	this.#addAttributesNS(this.svg, {style: `background-color: ${this.options.bg_color}`});
		// }

		// TO DO: probably remove these assignments. because these are not really necessary, but I assume at some
		// point I might change or add some properties to these objects.
		this.description = this.data.description;
		this.value = this.data.value;
		this.units = this.data.units;
		this.minmax = this.data.minmax;
		this.thresholds = this.data.thresholds;

		console.log(this.thresholds, 'this.thresholds');

		// Add all objects to DOM.
		this.#draw();
	}

	// TO DO: add description and finish function
	#draw() {
		// Add SVG to DOM.
		this.container.appendChild(this.svg);

		if (this.description.pos == DESC_V_POSITION_TOP) {
			// Set position to center of SVG.
			this.x = this.width / 2;

			this.#addDescription(this.description, this.x, this.y, 'top center');

			// Set the new y position to the height of the description.
			this.y += this.elements.description.height;
		}

		if (this.thresholds) {
			// reserve the space for threshold labels
			// draw threshold arc
		}

		// TO DO: add other objects and fix all the positions.

		// Min/Max max position depends on arc radiuses as well as how wide the min/max blocks are.
		// TODO: if there is no value arc and no threshold arc, the position of min and max is aligned to center of SVG.
		if (this.minmax.show) {
			this.#addMinMax({
				min: [this.minmax.min, 0, this.height / 2, 'middle left'],
				max: [this.minmax.max, this.width, this.height / 2, 'middle right']
			}, this.minmax.font_size);
		}

		this.#addValue(this.value, this.units, this.width / 2, 0, 'top center');

		if (this.description.pos == DESC_V_POSITION_BOTTOM) {
			this.#addDescription(this.description, this.width / 2, this.height, 'bottom center');
		}
	}

	// TO DO: add description
	#addDescription(description, x_start, y_start, anchor) {
		const line_height = this.height * description.font_size / 100;
		const font_size = line_height * FONT_SIZE_RATIO;
		const foreign_object = document.createElementNS(SVGNS, 'foreignObject');
		const div = document.createElement('div');

		foreign_object.appendChild(div);
		this.svg.appendChild(foreign_object);

		this.#addAttributesNS(foreign_object, {x: x_start, y: y_start, width: '100%', height: '100%'});
		this.#addAttributes(div, {xmlns: XMLNS, style: `display: inline-flex; font-size: ${font_size}px;`});

		if (description.is_bold) {
			this.#addAttributes(div, {style: `font-weight: bold;`});
		}

		if (description.color !== '') {
			this.#addAttributes(div, {style: `color: #${description.color};`});
		}

		const lines = description.text.split('\n');

		let line_count;

		for (line_count = 0; line_count < lines.length; line_count++) {
			div.appendChild(document.createTextNode(lines[line_count]));

			if (line_count < lines.length - 1) {
				div.appendChild(document.createElement('br'));
			}
		}

		const block_height = line_height * line_count;
		const height = div.offsetHeight + (block_height - div.offsetHeight);

		let width = div.offsetWidth;

		// In case description is wider, than the actual space of SVG, limit the description and add overflow ellipsis.
		if (width > this.width) {
			this.#addAttributes(div, {style: 'overflow: hidden; text-overflow: ellipsis;'});
			width = this.width;
		}

		const [x, y] = this.#calcXY(x_start, y_start, width, height, anchor);

		this.#addAttributesNS(foreign_object,
			{height: `${height}px`, width: `${width}px`, x: `${x}px`, y: `${y}px`}
		);
		this.#addAttributes(div, {style: 'height: 100%; width: 100%; display: block;'});

		this.elements.description = {
			parent: foreign_object,
			node: div,
			x: x,
			y: y,
			width: width,
			height: height,
			coordinates: this.#calcCoordinates(x, y, width, height)
		};
	}

	// TO DO: add description
	#addValue(value, units, x_start, y_start, anchor) {
		const value_line_height = this.height * value.font_size / 100;
		const value_font_size = value_line_height * FONT_SIZE_RATIO;
		const foreign_object = document.createElementNS(SVGNS, 'foreignObject');
		const div = document.createElement('div');

		foreign_object.appendChild(div);
		this.svg.appendChild(foreign_object);

		this.#addAttributesNS(foreign_object, {x: x_start, y: y_start, width: '100%', height: '100%'});
		this.#addAttributes(div, {xmlns: XMLNS, style: `display: inline-flex;`});

		let block_height;

		// Create two div blocks inside, otherwise add text element to parent.
		if (units.show && units.text !== '') {
			const value_div = document.createElement('div');
			const units_div = document.createElement('div');
			const units_line_height = this.height * units.font_size / 100;
			const units_font_size = units_line_height * FONT_SIZE_RATIO;

			// Append font size to each element.
			this.#addAttributes(value_div, {xmlns: XMLNS, style: `font-size: ${value_font_size}px;`});
			this.#addAttributes(units_div, {xmlns: XMLNS, style: `font-size: ${units_font_size}px;`});

			if (value.is_bold) {
				this.#addAttributes(value_div, {style: `font-weight: bold;`});
			}
			if (value.color !== '') {
				this.#addAttributes(value_div, {style: `color: #${value.color};`});
			}

			if (units.is_bold) {
				this.#addAttributes(units_div, {style: `font-weight: bold;`});
			}
			if (units.color !== '') {
				this.#addAttributes(units_div, {style: `color: #${units.color};`});
			}

			if (units.pos == UNITS_POSITION_BEFORE) {
				// Take largest height from both.
				block_height = (units_line_height > value_line_height) ? units_line_height : value_line_height;

				// If units are larger, add space after units. If value is larger add space before value.
				if (units_line_height > value_line_height) {
					units_div.appendChild(document.createTextNode(units.text + '\xa0'));
					value_div.appendChild(document.createTextNode(value.text));
				}
				else {
					units_div.appendChild(document.createTextNode(units.text));
					value_div.appendChild(document.createTextNode('\xa0' + value.text));
				}

				this.#addAttributes(div, {'style': 'align-items: baseline;'});

				div.appendChild(units_div);
				div.appendChild(value_div);
			}
			else if (units.pos == UNITS_POSITION_ABOVE) {
				// Total height is both units and value combined.
				block_height = units_line_height + value_line_height;

				units_div.appendChild(document.createTextNode(units.text));
				value_div.appendChild(document.createTextNode(value.text));

				this.#addAttributes(div, {'style': 'align-items: center; flex-direction: column;'});

				div.appendChild(units_div);
				div.appendChild(value_div);
			}
			else if (units.pos == UNITS_POSITION_AFTER) {
				// Take largest height from both.
				block_height = (units_line_height > value_line_height) ? units_line_height : value_line_height;

				// If units are larger, add space before units. If value is larger add space after value.
				if (units_line_height > value_line_height) {
					units_div.appendChild(document.createTextNode('\xa0' + units.text));
					value_div.appendChild(document.createTextNode(value.text));
				}
				else {
					units_div.appendChild(document.createTextNode(units.text));
					value_div.appendChild(document.createTextNode(value.text + '\xa0'));
				}

				this.#addAttributes(div, {'style': 'align-items: baseline;'});

				div.appendChild(value_div);
				div.appendChild(units_div);
			}
			else if (units.pos == UNITS_POSITION_BELOW) {
				// Total height is both units and value combined.
				block_height = units_line_height + value_line_height;

				units_div.appendChild(document.createTextNode(units.text));
				value_div.appendChild(document.createTextNode(value.text));

				this.#addAttributes(div, {'style': 'align-items: center; flex-direction: column;'});

				div.appendChild(value_div);
				div.appendChild(units_div);
			}
		}
		else {
			// No units.
			block_height = value_line_height;

			// Append font size to parent.
			this.#addAttributes(div, {style: `font-size: ${value_font_size}px;`});

			if (value.is_bold) {
				this.#addAttributes(div, {style: `font-weight: bold;`});
			}

			if (value.color !== '') {
				this.#addAttributes(div, {style: `color: #${value.color};`});
			}

			div.appendChild(document.createTextNode(value.text));
		}

		const height = div.offsetHeight + (block_height - div.offsetHeight);

		// TODO: check arc radius. Max width is the inner block. Add overflow ellipsis and resize accordingly just like in description.
		const width = div.offsetWidth;
		const [x, y] = this.#calcXY(x_start, y_start, width, height, anchor);

		this.#addAttributesNS(foreign_object,
			{height: `${height}px`, width: `${width}px`, x: `${x}px`, y: `${y}px`}
		);
		this.#addAttributes(div, {style: 'height: 100%; width: 100%;'});

		this.elements.value = {
			parent: foreign_object,
			node: div,
			x: x,
			y: y,
			width: width,
			height: height,
			coordinates: this.#calcCoordinates(x, y, width, height)
		};
	}

	// TO DO: add description
	#addMinMax(minmax, f_size) {
		for (const [key, value] of Object.entries(minmax)) {
			let data, x_start, y_start;

			[data, x_start, y_start] = value;

			const line_height = this.height * f_size / 100;
			const font_size = line_height * FONT_SIZE_RATIO;
			const foreign_object = document.createElementNS(SVGNS, 'foreignObject');
			const div = document.createElement('div');

			foreign_object.appendChild(div);
			this.svg.appendChild(foreign_object);

			this.#addAttributesNS(foreign_object, {x: x_start, y: y_start, width: '100%', height: '100%'});
			this.#addAttributes(div, {xmlns: XMLNS, style: `display: inline-flex; font-size: ${font_size}px;`});

			if (key === 'min') {
				this.#addAttributes(div, {style: 'text-align: right;'});
			}
			else {
				this.#addAttributes(div, {style: 'text-align: left;'});
			}

			// This text already contains units inside, because they are always after the min or max.
			div.appendChild(document.createTextNode(data.text));

			const block_height = line_height;
			const height = div.offsetHeight + (block_height - div.offsetHeight);
			const width = div.offsetWidth;

			this.elements[key] = {
				parent: foreign_object,
				node: div,
				height: height,
				width: width
			};
		}

		// Make both elements same width depending on which one of them is wider than then other.
		if (this.elements.min.width > this.elements.max.width) {
			this.elements.max.width = this.elements.min.width;
		}
		else {
			this.elements.min.width = this.elements.max.width;
		}

		// Both min and max are equal here, so doesn't matter which is compared. Both have to be shurnk down.
		const max_width = this.width * MAX_WIDTH_MINMAX_RATIO;

		if (this.elements.max.width > max_width) {
			this.elements.max.width = max_width;
			this.#addAttributes(this.elements.max.node, {style: 'overflow: hidden; text-overflow: ellipsis;'});

			this.elements.min.width = max_width;
			this.#addAttributes(this.elements.min.node, {style: 'overflow: hidden; text-overflow: ellipsis;'});
		}

		for (const [key, value] of Object.entries(minmax)) {
			let x_start, y_start, anchor;

			[, x_start, y_start, anchor] = value;

			const [x, y] = this.#calcXY(x_start, y_start, this.elements[key].width, this.elements[key].height, anchor);

			this.#addAttributesNS(this.elements[key].parent, {
				height: `${this.elements[key].height}px`,
				width: `${this.elements[key].width}px`,
				x: `${x}px`,
				y: `${y}px`
			});
			this.#addAttributes(this.elements[key].node, {style: 'height: 100%; width: 100%; display: block;'});

			this.elements[key] = {
				x: x,
				y: y,
				coordinates: this.#calcCoordinates(x, y, this.elements[key].width, this.elements[key].height)
			};
		}
	}

	// TO DO: finish functions.

	#addValueArc(value) {
		value.show_arc
		value.arc_size
	}

	#addThresholdArc() {
		// Depends on whether there is a value arc or it is independent.
	}

	#addNeedle() {
		// Depends on whether there is at least one arc. If both arcs exist, needle takes radius of the smallest arc.
	}

	#addThresholdLabels(arc, thresholds) {
		// Depends on whether there is at least one arc. They can be displayed on value arc as well without problems.
		// Though it doesn't make a lot of sense to do so. But there has to be a threshold to show the label.
		// If we don't have value arc or threshold arc we can't show labels, even if thresholds are defined.

		// Threshold font size share same height as min/max since there is no separate setting for it.
		// So we need to reserve space on top of the outter arc. Which ever it is.

		// Each threshold x and y also depends on which ever arc is displayed on the outter rim.
		// Anchor for each threshold depends on the quadrant in which the circle is drawn.

		// First thing to do is probably translate the values to coordinates.

		// If the area on which threshold should be displayed is taken by another threshold or min/max, it cannot be displayed.

		// To ensure most threholds are displayed, I... I don't know what to do.
	}

	// TO DO: create functions to add arcs, add needle etc.

	// TO DO: add description and finish writing the function.
	resize(width, height) {
		// Set main SVG size again.
		this.width = width;
		this.height = height;
		this.#addAttributesNS(this.svg, {width: width, height: height});

		// TO DO: rezise all other elements and calculate coordinates.
	}

	#reposition(element, x, y, anchor) {

	}

	#show(element) {

	}

	// TO DO: add description
	#calcXY(x_start, y_start, width, height, anchor) {
		const achor_parts = anchor.split(' ');

		let x;
		let y;

		if (achor_parts.includes('top')) {
			y = y_start;
		}
		else if (achor_parts.includes('middle')) {
			y = y_start - height / 2;
		}
		else if (achor_parts.includes('bottom')) {
			y = y_start - height;
		}

		if (achor_parts.includes('left')) {
			x = x_start;
		}
		else if (achor_parts.includes('center')) {
			x = x_start - width / 2;
		}
		else if (achor_parts.includes('right')) {
			x = x_start - width;
		}

		return [x, y];
	}

	// TO DO: add description
	#calcCoordinates(x, y, width, height) {
		let coordinates = {};

		coordinates.y1 = y;
		coordinates.x1 = x;

		coordinates.x2 = coordinates.x1 + width;
		coordinates.y2 = coordinates.y1;

		coordinates.x3 = coordinates.x1;
		coordinates.y3 = coordinates.y1 + height;

		coordinates.x4 = coordinates.x3 + width;
		coordinates.y4 = coordinates.y3;

		return coordinates;
	}

	// TO DO: add description
	#addAttributesNS(element, attributes) {
		return this.#addAttributes(element, attributes, true);
	}

	// TO DO: add description
	#addAttributes(element, attributes, use_ns = false) {
		for (const key in attributes) {
			if (key === 'style') {
				const style_attr = use_ns ? element.getAttributeNS(null, 'style') : element.getAttribute('style');
				const new_style = attributes[key];
				const styles = {};

				if (style_attr) {
					const style_list = style_attr.split(';').map(s => s.trim());

					style_list.forEach(style => {
						if (style) {
							const [prop, val] = style.split(':').map(s => s.trim());

							styles[prop] = val;
						}
					});
				}

				new_style.split(';').forEach(style => {
					if (style) {
						const [prop, val] = style.split(':').map(s => s.trim());

						styles[prop] = val;
					}
				});

				let style_string = '';

				for (const prop in styles) {
					style_string += prop + ': ' + styles[prop] + '; ';
				}

				if (use_ns) {
					element.setAttributeNS(null, key, style_string.trim());
				}
				else {
					element.setAttribute(key, style_string.trim());
				}
			}
			else {
				if (use_ns) {
					element.setAttributeNS(null, key, attributes[key]);
				}
				else {
					element.setAttribute(key, attributes[key]);
				}
			}
		}
	}

	/*
	// TO DO: add description or remove this function, since it will most likely not be used. Probably.
	#removeAttributes(element, attributes) {
		for (const key in attributes) {
			if (key === 'style') {
				const style_attr = element.getAttribute('style');
				const styles = {};

				if (style_attr) {
					const style_list = style_attr.split(';').map(s => s.trim());

					style_list.forEach(style => {
						if (style) {
							const [prop, val] = style.split(':').map(s => s.trim());

							styles[prop] = val;
						}
					});
				}

				const style_to_remove = attributes[key];

				delete styles[style_to_remove];

				let style_string = '';

				for (let prop in styles) {
					style_string += `${prop}: ${styles[prop]}; `;
				}

				if (style_string.trim() === '') {
					element.removeAttribute('style');
				}
				else {
					element.setAttribute(key, style_string.trim());
				}
			}
			else {
				element.removeAttribute(key);
			}
		}
	}
	*/
}
