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

class CSVGPie {

	static ZBX_STYLE_CLASS =				'svg-pie-chart';
	static ZBX_STYLE_ARCS =					'svg-pie-chart-arcs';
	static ZBX_STYLE_ARC_NO_DATA =			'svg-pie-chart-arc-no-data';
	static ZBX_STYLE_TOTAL_VALUE =			'svg-pie-chart-total-value';
	static ZBX_STYLE_TOTAL_VALUE_NO_DATA =	'svg-pie-chart-total-value-no-data';

	static LINE_HEIGHT = 1.14;

	static ANIMATE_DURATION_WHOLE = 1000;
	static ANIMATE_DURATION_SECTORS = 300;

	static DRAW_TYPE_PIE = 0;
	static DRAW_TYPE_DOUGHNUT = 1;

	/**
	 * Widget configuration.
	 *
	 * @type {Object}
	 */
	#config;

	/**
	 * Inner padding of the root SVG element.
	 *
	 * @type {Object}
	 */
	#padding;

	/**
	 * Root SVG element.
	 *
	 * @type {SVGSVGElement}
	 */
	#svg;

	/**
	 * SVG group element implementing padding inside the root SVG element.
	 *
	 * @type {SVGGElement}
	 */
	#g;

	/**
	 * SVG group element implementing scaling and fitting of its contents inside the root SVG element.
	 *
	 * @type {SVGGElement}
	 */
	#g_scalable;

	/**
	 * SVG group element that contains all the sectors and empty circles.
	 *
	 * @type {SVGGElement}
	 */
	#arcs_container;

	/**
	 * SVG text element that contains total value and units.
	 *
	 * @type {SVGTextElement}
	 */
	#total_value_container;

	/**
	 * Usable width of widget without padding.
	 *
	 * @type {number}
	 */
	#width;

	/**
	 * Usable height of widget without padding.
	 *
	 * @type {number}
	 */
	#height;

	/**
	 * Old sectors (for animation).
	 *
	 * @type {array}
	 */
	#sectors_old = [];

	/**
	 * New sectors (for animation).
	 *
	 * @type {array}
	 */
	#sectors_new = [];

	/**
	 * Outer radius of pie chart.
	 *
	 * @type {number}
	 */
	#radius_outer = 1;

	/**
	 * Inner radius of pie chart.
	 *
	 * @type {number}
	 */
	#radius_inner;

	/**
	 * @param {HTMLElement} container           HTML container to append the root SVG element to.
	 *
	 * @param {Object}      padding             Inner padding of the root SVG element.
	 *        {number}      padding.horizontal
	 *        {number}      padding.vertical
	 *
	 * @param {Object}      config              Widget configuration.
	 */
	constructor(container, padding, config) {
		this.#config = config;
		this.#padding = padding;

		this.#svg = d3.create('svg:svg')
			.attr('class', CSVGPie.ZBX_STYLE_CLASS);

		container.prepend(this.#svg.node());

		this.#g = d3.create('svg:g')
			.attr('transform',
				`translate(${this.#padding.horizontal} ${this.#padding.vertical})`);

		this.#svg.node().append(this.#g.node());

		this.#g_scalable = d3.create('svg:g');

		this.#g.node().append(this.#g_scalable.node());

		this.#radius_inner = this.#config.draw_type === CSVGPie.DRAW_TYPE_PIE
			? 0
			: this.#radius_outer - this.#config.width / 100;

		this.#createContainers();
	}

	/**
	 * Set size of the root SVG element and re-position the elements.
	 *
	 * @param {number} width
	 * @param {number} height
	 */
	setSize({width, height}) {
		this.#width = width - (this.#padding.horizontal) * 2;
		this.#height = height - (this.#padding.vertical) * 2;

		this.#svg
			.attr('width', width)
			.attr('height', height);

		const box_size = this.#radius_outer * 2;

		const scale = Math.min(this.#width / box_size, this.#height / box_size);
		const offset = scale / 10;

		const x = this.#width / 2;
		const y = (this.#height - box_size * scale) / 2 + this.#radius_outer * scale;

		this.#g_scalable.attr('transform', `translate(${x} ${y}) scale(${scale - offset})`);
	}

	/**
	 * Set value of the pie chart.
	 *
	 * @param {array}	sectors		Array of sectors to show in pie chart.
	 * @param {array}	items		Array of all possible items that can show up in pie chart.
	 * @param {Object}	total_value	Object of total value and units.
	 */
	setValue({sectors, items, total_value}) {
		sectors = this.#sortByRef(sectors, items);

		if (sectors.length > 0) {
			this.#arcs_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_NO_DATA}`)
				.style('display', 'none');
		}

		this.#sectors_old = this.#sectors_new;
		this.#sectors_new = sectors;

		const pie = d3.pie().sort(null).value(d => d.percent_of_total);
		const arc = d3.arc().innerRadius(this.#radius_inner).outerRadius(this.#radius_outer);

		const was = this.#prepareTransitionArray(this.#sectors_new, this.#sectors_old, items);
		const is = this.#prepareTransitionArray(this.#sectors_old, this.#sectors_new, items);

		const key = d => d.data.name;

		this.#arcs_container
			.selectAll('path')
			.data(pie(was), key)
			.enter()
			.insert('svg:path')
			.attr('data-hintbox', 1)
			.attr('data-hintbox-static', 1)
			.attr('data-hintbox-track-mouse', 1)
			.attr('data-hintbox-delay', 0)
			.style('fill', d => d.data.color)
			.style('stroke-width', this.#config.space)
			.each((d, index, nodes) => nodes[index]._current = d);

		this.#arcs_container
			.selectAll('path')
			.data(pie(is), key);

		this.#arcs_container
			.selectAll('path')
			.transition()
			.duration(CSVGPie.ANIMATE_DURATION_WHOLE)
			.attrTween('d', (d, index, nodes) => {
				const _this = nodes[index];

				const interpolate = d3.interpolate(_this._current, d);

				return t => {
					_this._current = interpolate(t);

					return arc(_this._current);
				};
			})
			.on('start', (d, index, nodes) => {
				const _this = nodes[index];

				const _this_d3 = d3.select(_this);

				_this_d3.on('mouseenter mouseleave', null);

				_this_d3.attr('transform', 'translate(0, 0)');

				_this_d3.attr('data-hintbox-contents', this.#setHint(d));
			})
			.end()
			.then(() => {
				const sectors = this.#arcs_container.selectAll('path').nodes();

				if (sectors.length > 1) {
					for (let i = 0; i < sectors.length; i++) {
						const _this = d3.select(sectors[i]);

						_this
							.on('mouseenter', () => {
								const datum = _this.datum();

								const x = arc.centroid(datum)[0] / 10;
								const y = arc.centroid(datum)[1] / 10;

								_this.transition().duration(CSVGPie.ANIMATE_DURATION_SECTORS).attr('transform', `translate(${x}, ${y})`);
							})
							.on('mouseleave', () => {
								_this.transition().duration(CSVGPie.ANIMATE_DURATION_SECTORS).attr('transform', 'translate(0, 0)');
							});
					}
				}
			})
			.catch(() => {});

		this.#arcs_container
			.selectAll('path')
			.data(pie(this.#sectors_new), key)
			.exit()
			.transition()
			.delay(CSVGPie.ANIMATE_DURATION_WHOLE)
			.duration(0)
			.remove()
			.end()
			.then(() => {
				const sectors = this.#arcs_container.selectAll('path').nodes();

				if (sectors.length === 0) {
					this.#arcs_container
						.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_NO_DATA}`)
						.style('display', '');
				}
			});

		if (this.#config.total_value?.show) {
			this.#total_value_container
				.text(() => {
					let text = '';

					if (total_value.value === null) {
						text = total_value.value_text;
					}
					else {
						text = total_value.value;

						if (this.#config.total_value.units_show && total_value.units !== '') {
							text += ` ${total_value.units}`;
						}
					}

					return text;
				});

			this.#total_value_container
				.classed(CSVGPie.ZBX_STYLE_TOTAL_VALUE_NO_DATA, total_value.value === null);
		}
	}

	/**
	 * Get the root SVG element.
	 *
	 * @returns {SVGSVGElement}
	 */
	getSVGElement() {
		return this.#svg.node();
	}

	/**
	 * Remove created SVG element from the container.
	 */
	destroy() {
		this.#svg.node().remove();
	}

	/**
	 * Create containers for elements (arcs, total value).
	 */
	#createContainers() {
		this.#arcs_container = this.#g_scalable
			.append('svg:g')
			.attr('class', CSVGPie.ZBX_STYLE_ARCS);

		this.#arcs_container
			.append('svg:circle')
			.attr('class', CSVGPie.ZBX_STYLE_ARC_NO_DATA)
			.attr('r', this.#radius_outer);

		if (this.#config.draw_type === CSVGPie.DRAW_TYPE_DOUGHNUT) {
			this.#arcs_container
				.append('svg:circle')
				.attr('class', CSVGPie.ZBX_STYLE_ARC_NO_DATA)
				.attr('r', this.#radius_inner);

			if (this.#config.total_value?.show) {
				this.#total_value_container = this.#g_scalable
					.append('svg:text')
					.attr('class', CSVGPie.ZBX_STYLE_TOTAL_VALUE)
					.style('font-size', this.#config.total_value.size / 100)
					.style('font-weight', this.#config.total_value.is_bold ? 'bold' : '')
					.style('fill', this.#config.total_value.color !== '' ? this.#config.total_value.color : '')
					.attr('y', this.#config.total_value.size / 100 / 2 / CSVGPie.LINE_HEIGHT);
			}
		}
	}

	/**
	 * Set hint for sector.
	 *
	 * @param {Object}	sector	All necessary information about sector.
	 *
	 * @returns {string}
	 */
	#setHint(sector) {
		const hint = d3.create('div')
			.attr('class', 'svg-pie-chart-hintbox');

		hint.append('span')
			.attr('class', 'svg-pie-chart-hintbox-color')
			.style('background-color', sector.data.color);

		hint.append('span')
			.attr('class', 'svg-pie-chart-hintbox-name')
			.text(`${sector.data.name}: `);

		hint.append('span')
			.attr('class', 'svg-pie-chart-hintbox-value')
			.text(() => {
				let text = sector.data.formatted_value.value;

				if (sector.data.formatted_value.units !== '') {
					text += ` ${sector.data.formatted_value.units}`;
				}

				return text;
			});

		return hint.node().outerHTML;
	}

	/**
	 * Combine two arrays to use result for animation.
	 *
	 * @param {array}	arr_old
	 * @param {array}	arr_new
	 * @param {array}	items	Array to sort result by.
	 *
	 * @returns {array}
	 */
	#prepareTransitionArray(arr_old, arr_new, items) {
		const arr_new_set = new Set();

		arr_new.forEach(element => arr_new_set.add(element.name));

		const arr_old_only = arr_old
			.filter(element => !arr_new_set.has(element.name))
			.map(element => ({...element, percent_of_total: 0}));

		const merged = d3.merge([arr_new, arr_old_only]);

		return this.#sortByRef(merged, items);
	}

	/**
	 * Sort array of objects by another (reference) array of objects.
	 *
	 * @param {array}	arr	Array to sort.
	 * @param {array}	ref	Reference array.
	 */
	#sortByRef(arr, ref) {
		return arr.sort((a, b) => {
			const aIndex = ref.findIndex(i => i.name === a.name);
			const bIndex = ref.findIndex(i => i.name === b.name);

			return aIndex - bIndex;
		});
	}
}
