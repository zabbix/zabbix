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
 * SVGMapShape class. Implements rendering of map shapes.
 *
 * @param {object} map      Parent map.
 * @param {object} options  Shape attributes.
 */
class SVGMapShape {
	constructor(map, options) {
		this.map = map;
		this.options = options;
		this.element = null;
	}

	// Set of map shape types.
	static TYPE_RECTANGLE = SYSMAP_SHAPE_TYPE_RECTANGLE;
	static TYPE_ELLIPSE = SYSMAP_SHAPE_TYPE_ELLIPSE;
	static TYPE_LINE = SYSMAP_SHAPE_TYPE_LINE;

	// Label horizontal alignments.
	static LABEL_HALIGN_LEFT = SYSMAP_SHAPE_LABEL_HALIGN_LEFT;
	static LABEL_HALIGN_RIGHT = SYSMAP_SHAPE_LABEL_HALIGN_RIGHT;

	// Label vertical alignments.
	static LABEL_VALIGN_TOP = SYSMAP_SHAPE_LABEL_VALIGN_TOP;
	static LABEL_VALIGN_BOTTOM = SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM;

	// Border types (@see dash-array of SVG) for maps.
	static BORDER_TYPE_NONE = SYSMAP_SHAPE_BORDER_TYPE_NONE;
	static BORDER_TYPE_SOLID = SYSMAP_SHAPE_BORDER_TYPE_SOLID;
	static BORDER_TYPE_DOTTED = SYSMAP_SHAPE_BORDER_TYPE_DOTTED;
	static BORDER_TYPE_DASHED = SYSMAP_SHAPE_BORDER_TYPE_DASHED;

	static BORDER_TYPES = {
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
	update(options) {
		if (this.map.isChanged(this.options, options) === false) {
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

		mapping.forEach(({ key, value }) => {
			const raw_color = options[key]?.trim();
			const color = raw_color ? `#${raw_color}` : null;

			attributes[value] = isColorHex(color) ? color : 'none';
		});

		if (options.border_width !== undefined) {
			attributes['stroke-width'] = parseInt(options.border_width);
		}

		if (options.border_type !== undefined) {
			let border_type = this.constructor.BORDER_TYPES[parseInt(options.border_type)];

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
			case this.constructor.TYPE_RECTANGLE:
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

			case this.constructor.TYPE_ELLIPSE:
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

			case this.constructor.TYPE_LINE:
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
				case this.constructor.LABEL_HALIGN_LEFT:
					x = this.x + this.map.canvas.textPadding;
					anchor.horizontal = 'left';
					break;

				case this.constructor.LABEL_HALIGN_RIGHT:
					x = this.x + this.width - this.map.canvas.textPadding;
					anchor.horizontal = 'right';
					break;
			}

			switch (parseInt(options.text_valign)) {
				case this.constructor.LABEL_VALIGN_TOP:
					y = this.y + this.map.canvas.textPadding;
					anchor.vertical = 'top';
					break;

				case this.constructor.LABEL_VALIGN_BOTTOM:
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
	}

	/**
	 * Remove shape.
	 */
	remove() {
		if (this.element !== null) {
			delete this.map.shapes[this.options.sysmap_shapeid];

			this.element.remove();
			this.element = null;
		}
	}
}
