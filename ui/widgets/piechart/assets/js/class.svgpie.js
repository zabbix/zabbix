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
	static ZBX_STYLE_ARC =					'svg-pie-chart-arc';
	static ZBX_STYLE_ARC_PLACEHOLDER =		'svg-pie-chart-arc-placeholder';
	static ZBX_STYLE_ARC_NO_DATA_OUTER =	'svg-pie-chart-arc-no-data-outer';
	static ZBX_STYLE_ARC_NO_DATA_INNER =	'svg-pie-chart-arc-no-data-inner';
	static ZBX_STYLE_TOTAL_VALUE =			'svg-pie-chart-total-value';
	static ZBX_STYLE_TOTAL_VALUE_NO_DATA =	'svg-pie-chart-total-value-no-data';

	static LINE_HEIGHT = 1.14;

	static ANIMATE_DURATION_WHOLE = 1000;
	static ANIMATE_DURATION_POP_OUT = 300;
	static ANIMATE_DURATION_POP_IN = 100;

	static DRAW_TYPE_PIE = 0;
	static DRAW_TYPE_DOUGHNUT = 1;

	static TOTAL_VALUE_SIZE_DEFAULT = 10;
	static TOTAL_VALUE_PADDING = 4;

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
	 * @member {Selection}
	 */
	#svg;

	/**
	 * SVG group element implementing scaling and fitting of its contents inside the root SVG element.
	 *
	 * @type {SVGGElement}
	 * @member {Selection}
	 */
	#g_scalable;

	/**
	 * SVG group element that contains all the sectors and empty circles.
	 *
	 * @type {SVGGElement}
	 * @member {Selection}
	 */
	#arcs_container;

	/**
	 * SVG text element that contains total value and units.
	 *
	 * @type {SVGTextElement}
	 * @member {Selection}
	 */
	#total_value_container;

	/**
	 * SVG text element that contains "No data" text.
	 *
	 * @type {SVGTextElement}
	 * @member {Selection}
	 */
	#no_data_container;

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
	 * @type {Array}
	 */
	#sectors_old = [];

	/**
	 * New sectors (for animation).
	 *
	 * @type {Array}
	 */
	#sectors_new = [];

	/**
	 * Old ids of sectors (for animation).
	 *
	 * @type {Array}
	 */
	#all_sectorids_old = [];

	/**
	 * New ids of sectors (for animation).
	 *
	 * @type {Array}
	 */
	#all_sectorids_new = [];

	/**
	 * SVG path element that represents a sector that is popped out.
	 *
	 * @type {SVGPathElement}
	 * @member {Selection}
	 */
	#popped_out_sector = null;

	/**
	 * SVG path element that represents a placeholder under a sector that is popped out.
	 *
	 * @type {SVGPathElement}
	 * @member {Selection}
	 */
	#popped_out_placeholder = null;

	/**
	 * Outer radius of pie chart.
	 * It is large number because SVG works more precise that way (later it will be scaled according to widget size).
	 *
	 * @type {number}
	 */
	#radius_outer = 1000;

	/**
	 * Inner radius of pie chart.
	 *
	 * @type {number}
	 */
	#radius_inner;

	/**
	 * Arc generator function.
	 *
	 * @type {function}
	 */
	#arcGenerator;

	/**
	 * Pie generator function.
	 *
	 * @type {function}
	 */
	#pieGenerator;

	/**
	 * Observer that checks attributes of sectors.
	 *
	 * @type {MutationObserver}
	 */
	#sector_observer;

	/**
	 * @param {Object} padding             Inner padding of the root SVG element.
	 *        {number} padding.horizontal
	 *        {number} padding.vertical
	 *
	 * @param {Object} config              Widget configuration.
	 */
	constructor(padding, config) {
		this.#config = config;
		this.#padding = padding;

		this.#radius_inner = this.#config.draw_type === CSVGPie.DRAW_TYPE_PIE
			? 0
			: this.#radius_outer - this.#config.width * 10;

		this.#pieGenerator = d3.pie().sort(null).value(d => d.percent_of_total);
		this.#arcGenerator = d3.arc().innerRadius(this.#radius_inner).outerRadius(this.#radius_outer);

		this.#svg = d3.create('svg:svg').attr('class', CSVGPie.ZBX_STYLE_CLASS);

		this.#createContainers();

		this.#sector_observer = new MutationObserver((mutation_list) => {
			for (const mutation of mutation_list) {
				if (mutation.type === 'attributes' && mutation.oldValue === 'true'
						&& !mutation.target.matches(':hover')) {
					this.#popIn(mutation.target);
				}
			}
		});
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
	 * @param {Array}  sectors        Array of sectors to show in pie chart.
	 * @param {Array}  all_sectorids  Array of all possible ids of sectors that can show up in pie chart.
	 * @param {Object} total_value    Object of total value and units.
	 */
	setValue({sectors, all_sectorids, total_value}) {
		this.#sector_observer.disconnect();

		this.#svg.on('mousemove', null);

		this.#popped_out_sector = null;
		this.#popped_out_placeholder = null;

		if (sectors.length > 0) {
			all_sectorids = this.#prepareAllSectorids(all_sectorids);
			sectors = this.#sortByReference(sectors, all_sectorids);

			this.#arcs_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_NO_DATA_OUTER}, .${CSVGPie.ZBX_STYLE_ARC_NO_DATA_INNER}`)
				.style('display', 'none');

			this.#no_data_container.style('display', 'none');

			if (this.#config.total_value?.show) {
				this.#total_value_container
					.text(() => {
						let text = total_value.value;

						if (this.#config.total_value.units_show && total_value.units !== '') {
							text += ` ${total_value.units}`;
						}

						return text;
					});

				if (this.#config.total_value.is_custom_size) {
					this.#total_value_container
						.attr('y', this.#config.total_value.size * 10 / 2 / CSVGPie.LINE_HEIGHT - this.#config.total_value.size)
						.style('font-size', `${this.#config.total_value.size * 10}px`);
				}
				else {
					const text_width = this.#getMeasuredTextWidth(
						this.#total_value_container.text(),
						CSVGPie.TOTAL_VALUE_SIZE_DEFAULT,
						this.#svg.style('font-family')) + CSVGPie.TOTAL_VALUE_PADDING;

					const scale = this.#radius_inner * 2 / text_width;

					this.#total_value_container.attr('transform', `scale(${scale})`);
				}

				this.#total_value_container.style('display', '');
			}
		}
		else {
			if (this.#config.total_value?.show) {
				this.#total_value_container
					.text('')
					.style('display', 'none');

				if (this.#config.total_value.is_custom_size) {
					this.#no_data_container
						.attr('y', this.#config.total_value.size * 10 / 2 / CSVGPie.LINE_HEIGHT - this.#config.total_value.size)
						.style('font-size', `${this.#config.total_value.size * 10}px`);
				}
				else {
					const text_width = this.#getMeasuredTextWidth(
						this.#no_data_container.text(),
						CSVGPie.TOTAL_VALUE_SIZE_DEFAULT,
						this.#svg.style('font-family')) + CSVGPie.TOTAL_VALUE_PADDING;

					const scale = this.#radius_inner * 2 / text_width;

					this.#no_data_container.attr('transform', `scale(${scale})`);
				}

				this.#no_data_container.style('display', '');
			}
			else {
				this.#no_data_container
					.attr('y', 2 * CSVGPie.TOTAL_VALUE_SIZE_DEFAULT * 10 / 2 / CSVGPie.LINE_HEIGHT - 2 * CSVGPie.TOTAL_VALUE_SIZE_DEFAULT)
					.style('font-size', `${2 * CSVGPie.TOTAL_VALUE_SIZE_DEFAULT * 10}px`);
			}
		}

		this.#sectors_old = this.#sectors_new;
		this.#sectors_new = sectors;

		const was = this.#prepareTransitionArray(this.#sectors_new, this.#sectors_old, all_sectorids);
		const is = this.#prepareTransitionArray(this.#sectors_old, this.#sectors_new, all_sectorids);

		const key = d => d.data.id;

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}`)
			.data(this.#pieGenerator(this.#sectors_new), key)
			.join('svg:path')
			.attr('class', CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER)
			.attr('d', (d) => this.#arcGenerator(d));

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
			.data(this.#pieGenerator(was), key)
			.enter()
			.insert('svg:path')
			.attr('class', CSVGPie.ZBX_STYLE_ARC)
			.attr('data-hintbox', 1)
			.attr('data-hintbox-static', 1)
			.attr('data-hintbox-track-mouse', 1)
			.attr('data-hintbox-delay', 0)
			.style('stroke-width', this.#config.space)
			.each((d, index, nodes) => nodes[index]._current = d);

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
			.data(this.#pieGenerator(is), key);

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
			.transition()
			.duration(CSVGPie.ANIMATE_DURATION_WHOLE)
			.attrTween('d', (d, index, nodes) => {
				const _this = nodes[index];

				const interpolate = d3.interpolate(_this._current, d);

				return t => {
					_this._current = interpolate(t);

					return this.#arcGenerator(_this._current);
				};
			})
			.on('start', (d, index, nodes) => {
				d3.select(nodes[index])
					.attr('transform', 'translate(0, 0)')
					.attr('data-hintbox-contents', this.#setHint(d))
					.style('fill', d => d.data.color)
					.on('mouseleave', null);
			})
			.end()
			.then(() => {
				const sectors = this.#arcs_container.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`).nodes();

				if (sectors.length > 1) {
					for (let i = 0; i < sectors.length; i++) {
						this.#sector_observer.observe(sectors[i], {
							attributeFilter: ['data-expanded'],
							attributeOldValue: true
						});

						// If mouse was on any sector before/during animation, then pop that sector out again.
						if (sectors[i].matches(':hover')) {
							this.#popOut(sectors[i]);
							this.#popped_out_sector = sectors[i];
						}

						d3.select(sectors[i]).on('mouseleave', (e) => this.#onMouseLeave(e));
					}

					this.#svg.on('mousemove', (e) => this.#onMouseMove(e));
				}
			})
			.catch(() => {});

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
			.data(this.#pieGenerator(this.#sectors_new), key)
			.exit()
			.transition()
			.delay(CSVGPie.ANIMATE_DURATION_WHOLE)
			.duration(0)
			.remove()
			.end()
			.then(() => {
				const sectors = this.#arcs_container.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`).nodes();

				if (sectors.length === 0) {
					this.#arcs_container
						.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_NO_DATA_OUTER}, .${CSVGPie.ZBX_STYLE_ARC_NO_DATA_INNER}`)
						.style('display', '');

					this.#no_data_container.style('display', '');
				}
			})
			.catch(() => {});
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
	 * Create containers for elements (arcs, total value, no data text).
	 */
	#createContainers() {
		const y_offset = 3.5;

		// SVG group element implementing padding inside the root SVG element.
		const main = this.#svg
			.append('svg:g')
			.attr('transform', `translate(${this.#padding.horizontal} ${this.#padding.vertical})`);

		this.#g_scalable = main.append('svg:g');

		this.#arcs_container = this.#g_scalable
			.append('svg:g')
			.attr('class', CSVGPie.ZBX_STYLE_ARCS);

		this.#arcs_container
			.append('svg:circle')
			.attr('class', CSVGPie.ZBX_STYLE_ARC_NO_DATA_OUTER)
			.attr('r', this.#radius_outer);

		if (this.#config.draw_type === CSVGPie.DRAW_TYPE_DOUGHNUT) {
			this.#arcs_container
				.append('svg:circle')
				.attr('class', CSVGPie.ZBX_STYLE_ARC_NO_DATA_INNER)
				.attr('r', this.#radius_inner);

			if (this.#config.total_value?.show) {
				this.#total_value_container = this.#g_scalable
					.append('svg:text')
					.attr('class', CSVGPie.ZBX_STYLE_TOTAL_VALUE)
					.attr('y', y_offset)
					.style('font-size', `${CSVGPie.TOTAL_VALUE_SIZE_DEFAULT}px`)
					.style('font-weight', this.#config.total_value.is_bold ? 'bold' : '')
					.style('fill', this.#config.total_value.color !== '' ? this.#config.total_value.color : '')
					.style('display', 'none');
			}
		}

		this.#no_data_container = this.#g_scalable
			.append('svg:text')
			.attr('class', CSVGPie.ZBX_STYLE_TOTAL_VALUE_NO_DATA)
			.attr('y', y_offset)
			.style('font-size', `${CSVGPie.TOTAL_VALUE_SIZE_DEFAULT}px`)
			.style('font-weight', this.#config.total_value?.is_bold ? 'bold' : '')
			.text(t('No data'));
	}

	/**
	 * Set hint for sector.
	 *
	 * @param {Object} sector  All necessary information about sector.
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
	 * Combine old and new ids of sectors to use result for animation.
	 *
	 * @param {Array} all_sectorids  Array of ids of sectors to sort result by.
	 *
	 * @returns {Array}
	 */
	#prepareAllSectorids(all_sectorids) {
		this.#all_sectorids_old = this.#all_sectorids_new;
		this.#all_sectorids_new = all_sectorids;

		const all_sectorids_new_set = new Set(this.#all_sectorids_new);
		const missing_ids = this.#all_sectorids_old.filter(id => !all_sectorids_new_set.has(id));

		for (const missing_id of missing_ids) {
			const index = this.#all_sectorids_old.indexOf(missing_id);
			const after = this.#all_sectorids_old[index + 1] || null;

			let insert_at_index = this.#all_sectorids_new.indexOf(after);

			if (insert_at_index === -1) {
				insert_at_index = this.#all_sectorids_new.length;
			}

			all_sectorids.splice(insert_at_index, 0, missing_id);
		}

		return all_sectorids;
	}

	/**
	 * Combine two arrays of elements to use result for animation.
	 *
	 * @param {Array} old_sectors
	 * @param {Array} new_sectors
	 * @param {Array} all_sectorids  Array of ids of sectors to sort result by.
	 *
	 * @returns {Array}
	 */
	#prepareTransitionArray(old_sectors, new_sectors, all_sectorids) {
		const sectors_ids = new Set();

		new_sectors.forEach(sector => sectors_ids.add(sector.id));

		old_sectors = old_sectors
			.filter(sector => !sectors_ids.has(sector.id))
			.map(sector => ({...sector, percent_of_total: 0}));

		const sectors = d3.merge([new_sectors, old_sectors]);

		return this.#sortByReference(sectors, all_sectorids);
	}

	/**
	 * Sort array of elements by another (reference) array of ids.
	 *
	 * @param {Array} sectors        Array to sort.
	 * @param {Array} all_sectorids  Reference array.
	 */
	#sortByReference(sectors, all_sectorids) {
		return sectors.sort((a, b) => {
			return all_sectorids.indexOf(a.id) - all_sectorids.indexOf(b.id);
		});
	}

	/**
	 * Get text width using canvas measuring.
	 *
	 * @param {string} text
	 * @param {number} size
	 * @param {string} font_family
	 *
	 * @returns {number}
	 */
	#getMeasuredTextWidth(text, size, font_family) {
		const canvas = document.createElement('canvas');
		const context = canvas.getContext('2d');

		context.font = `${size}px ${font_family}`;

		return context.measureText(text).width;
	}

	/**
	 * Determine when sectors can be popped out or popped in.
	 *
	 * @param {Event} e  Mouse move event.
	 */
	#onMouseMove(e) {
		if (e.target.classList.contains(CSVGPie.ZBX_STYLE_ARC) && e.target.dataset.expanded) {
			return;
		}

		const placeholder = document.elementsFromPoint(e.clientX, e.clientY)
			.find((element) => element.classList.contains(CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER));

		if (e.target.classList.contains(CSVGPie.ZBX_STYLE_ARC)) {
			if (this.#popped_out_sector === null) {
				this.#popped_out_sector = e.target;
				this.#popped_out_placeholder = placeholder;
				this.#popOut(this.#popped_out_sector);
			}
			else if (e.target !== this.#popped_out_sector) {
				this.#popIn(this.#popped_out_sector);
				this.#popped_out_sector = e.target;
				this.#popped_out_placeholder = placeholder;
				this.#popOut(this.#popped_out_sector);
			}
		}
		else if (this.#popped_out_sector !== null && e.target !== this.#popped_out_placeholder) {
			this.#popIn(this.#popped_out_sector);
		}
	}

	/**
	 * Pop in sector if it or its placeholder lost mouse capture.
	 *
	 * @param {Event} e  Mouse leave event.
	 */
	#onMouseLeave(e) {
		if (e.toElement === null || !e.toElement.classList.contains(CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER)) {
			this.#popIn(this.#popped_out_sector);
		}
	}

	/**
	 * Pop out a sector.
	 *
	 * @param {SVGPathElement} sector
	 */
	#popOut(sector) {
		const sector_d3 = d3.select(sector);

		const centroid = this.#arcGenerator.centroid(sector_d3.datum());

		const x = centroid[0];
		const y = centroid[1];

		const hypotenuse = Math.sqrt(x * x + y * y);

		const offset = 60;

		const x_position = x / hypotenuse * offset;
		const y_position = y / hypotenuse * offset;

		sector_d3
			.transition()
			.duration(CSVGPie.ANIMATE_DURATION_POP_OUT)
			.attr('transform', `translate(${x_position}, ${y_position})`);
	}

	/**
	 * Pop in a sector.
	 *
	 * @param {SVGPathElement} sector
	 */
	#popIn(sector) {
		const sector_d3 = d3.select(sector);

		if (!sector?.dataset.expanded) {
			sector_d3
				.transition()
				.duration(CSVGPie.ANIMATE_DURATION_POP_IN)
				.attr('transform', 'translate(0, 0)');
		}

		this.#popped_out_sector = null;
		this.#popped_out_placeholder = null;
	}
}
