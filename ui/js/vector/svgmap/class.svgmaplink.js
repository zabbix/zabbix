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
 * SVGMapLink class. Implements rendering of map links.
 *
 * @param {object} map      Parent map.
 * @param {object} options  Link attributes.
 */
class SVGMapLink {
	constructor (map, options) {
		this.map = map;
		this.options = options;
		this.element = null;
	}

	// Predefined set of line styles
	static LINE_STYLE_DEFAULT = DRAWTYPE_LINE;
	static LINE_STYLE_BOLD = DRAWTYPE_BOLD_LINE;
	static LINE_STYLE_DOTTED = DRAWTYPE_DOT;
	static LINE_STYLE_DASHED = DRAWTYPE_DASHED_LINE;

	/**
	 * Update link.
	 *
	 * @param {object} options  Link attributes (match field names in data source).
	 */
	update(options) {
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

		if (this.map.isChanged(this.options, options) === false) {
			// No need to update.
			return;
		}

		this.options = options;

		const attributes = {};

		if (options.hover_link) {
			attributes['stroke'] = 'transparent';
			attributes['stroke-width'] = 10;
			attributes['opacity'] = 0;
		}
		else {
			attributes['stroke'] = `#${options.color}`;
			attributes['stroke-width'] = 1;
			attributes['fill'] = `#${this.map.options.theme.backgroundcolor}`;

			switch (options.drawtype) {
				case this.constructor.LINE_STYLE_BOLD:
					attributes['stroke-width'] = 2;
					break;

				case this.constructor.LINE_STYLE_DOTTED:
					attributes['stroke-dasharray'] = '1,2';
					break;

				case this.constructor.LINE_STYLE_DASHED:
					attributes['stroke-dasharray'] = '4,4';
					break;
			}
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

		if (options.hover_link) {
			let rows = '';

			for (const link of options.links) {
				const link_row = document.createElement('div');

				link_row.classList.add('map-link-hintbox-row');

				const color_box = document.createElement('div');

				color_box.classList.add('map-link-hintbox-row-color-box');
				color_box.style.backgroundColor = `#${link.color}`;
				link_row.append(color_box);

				const label_box = document.createElement('div');

				label_box.innerText = link.label;
				link_row.append(label_box);
				rows += link_row.outerHTML;
			}

			element.element.dataset.hintbox = '1';
			element.element.dataset.hintboxStatic = '1';
			element.element.dataset.hintboxContents = rows;
			element.element.style.cursor = 'pointer';
		}
		else {
			element.add('textarea', {
					x: options.center.x,
					y: options.center.y,
					fill: `#${this.map.options.theme.textcolor}`,
					'font-size': '10px',
					'stroke-width': 0,
					anchor: {horizontal: 'center', vertical: 'middle'},
					background: {}
				}, options.label
			);
		}

		if (this.element !== null) {
			this.element.replace(element);
		}
		else {
			this.element = element;
		}
	}

	/**
	 * Remove link.
	 */
	remove() {
		if (this.element !== null) {
			this.element.remove();
			this.element = null;
		}
	}
}
