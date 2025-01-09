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


class CSVGPie {

	static ZBX_STYLE_CLASS =				'svg-pie-chart';
	static ZBX_STYLE_ARCS =					'svg-pie-chart-arcs';
	static ZBX_STYLE_ARC_CONTAINER =		'svg-pie-chart-arc-container';
	static ZBX_STYLE_ARC_PLACEHOLDER =		'svg-pie-chart-arc-placeholder';
	static ZBX_STYLE_ARC_STROKE =			'svg-pie-chart-arc-stroke';
	static ZBX_STYLE_ARC =					'svg-pie-chart-arc';
	static ZBX_STYLE_SPACE_CONTAINER =		'svg-pie-chart-space-container';
	static ZBX_STYLE_SPACE =				'svg-pie-chart-space';
	static ZBX_STYLE_ARC_NO_DATA_OUTER =	'svg-pie-chart-arc-no-data-outer';
	static ZBX_STYLE_ARC_NO_DATA_INNER =	'svg-pie-chart-arc-no-data-inner';
	static ZBX_STYLE_TOTAL_VALUE =			'svg-pie-chart-total-value';
	static ZBX_STYLE_TOTAL_VALUE_NO_DATA =	'svg-pie-chart-total-value-no-data';

	static TEXT_BASELINE = 0.8;

	static ANIMATE_DURATION_WHOLE = 1000;
	static ANIMATE_DURATION_POP_OUT = 300;
	static ANIMATE_DURATION_POP_IN = 100;

	static DRAW_TYPE_PIE = 0;
	static DRAW_TYPE_DOUGHNUT = 1;

	static TOTAL_VALUE_SIZE_DEFAULT = 20;
	static TOTAL_VALUE_HEIGHT_MIN = 12;
	static TOTAL_VALUE_PADDING = 10;

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
	#container;

	/**
	 * SVG group element that contains all the sectors.
	 *
	 * @type {SVGGElement}
	 * @member {Selection}
	 */
	#arcs_container;

	/**
	 * SVG group element that contains all the space elements between sectors.
	 *
	 * @type {SVGGElement}
	 * @member {Selection}
	 */
	#space_container;

	/**
	 * SVG foreignObject element that contains total value and units.
	 *
	 * @type {SVGForeignObjectElement}
	 * @member {Selection}
	 */
	#total_value_container;

	/**
	 * SVG text element that contains no data text.
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
	 * SVG G element that represents a sector that is popped out.
	 *
	 * @type {SVGGElement}
	 */
	#popped_out_sector = null;

	/**
	 * Distance how much sector is popped out.
	 *
	 * @type {number}
	 */
	#pop_out_distance = 0.06;

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
	 * Scale of pie chart.
	 *
	 * @type {number}
	 */
	#scale = 1;

	/**
	 * Total value text of pie chart combined from value and units.
	 *
	 * @type {string}
	 */
	#total_value_text = '';

	/**
	 * Font size of total value.
	 *
	 * @type {number}
	 */
	#total_value_font_size = 0;

	/**
	 * Canvas context for text measuring.
	 *
	 * @type {object}
	 */
	#canvas_context = null;

	/**
	 * Arc generator function.
	 *
	 * @type {function}
	 */
	#arcGenerator;

	/**
	 * Arc generator function with padding from all sides.
	 *
	 * @type {function}
	 */
	#arcGeneratorWithPadding;

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
	 * Rendered promise.
	 *
	 * @type {Promise<void>}
	 */
	#rendered_promise = Promise.resolve();

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
		this.#arcGeneratorWithPadding = d3.arc()
			.innerRadius(this.#radius_inner + this.#config.stroke * 10 / 2)
			.outerRadius(this.#radius_outer - this.#config.stroke * 10 / 2)
			.padAngle(this.#config.stroke * 0.01);

		this.#svg = d3.create('svg:svg').attr('class', CSVGPie.ZBX_STYLE_CLASS);

		this.#canvas_context = document.createElement('canvas').getContext('2d');

		this.#createContainers();

		this.#sector_observer = new MutationObserver((mutation_list) => {
			for (const mutation of mutation_list) {
				if (mutation.type === 'attributes' && !mutation.target.matches(':hover')) {
					// Hintbox close button was clicked.
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
		this.#width = Math.max(0, width - this.#padding.horizontal * 2);
		this.#height = Math.max(0, height - this.#padding.vertical * 2);

		this.#svg
			.attr('width', width)
			.attr('height', height);

		const box_size = this.#radius_outer * 2;

		this.#scale = Math.min(this.#width / box_size, this.#height / box_size);
		this.#scale -= this.#scale / 10;

		const x = this.#width / 2;
		const y = (this.#height - box_size * this.#scale) / 2 + this.#radius_outer * this.#scale;

		this.#container.attr('transform', `translate(${x} ${y}) scale(${this.#scale})`);

		this.#positionValue();
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

		if (this.#popped_out_sector !== null) {
			this.#popIn(this.#popped_out_sector);
		}

		if (this.#config.total_value && total_value.value !== null) {
			this.#total_value_text = total_value.formatted_value.value;

			if (this.#config.total_value.units_show && total_value.formatted_value.units !== '') {
				this.#total_value_text += ` ${total_value.formatted_value.units}`;
			}
		}

		if (this.#config.space > 0 && sectors.length > 1) {
			this.#space_container.style('display', '');

			this.#arcGeneratorWithPadding.padAngle(this.#config.stroke * 0.01 + this.#config.space * 0.01 / 3);
		}

		if (sectors.length > 0) {
			all_sectorids = this.#prepareAllSectorids(all_sectorids);
			sectors = this.#sortByReference(sectors, all_sectorids);
		}

		if (total_value.value > 0) {
			this.#container
				.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_NO_DATA_OUTER}, .${CSVGPie.ZBX_STYLE_ARC_NO_DATA_INNER}`)
				.style('display', 'none');
		}

		this.#sectors_old = this.#sectors_new;
		this.#sectors_new = sectors;

		const was = this.#prepareTransitionArray(this.#sectors_new, this.#sectors_old, all_sectorids);
		const is = this.#prepareTransitionArray(this.#sectors_old, this.#sectors_new, all_sectorids);

		const key = d => d.data.id;

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_CONTAINER}`)
			.data(this.#pieGenerator(was), key)
			.join('svg:g')
			.attr('class', CSVGPie.ZBX_STYLE_ARC_CONTAINER)
			.attr('data-hintbox', 1)
			.attr('data-hintbox-static', 1)
			.attr('data-hintbox-track-mouse', 1)
			.attr('data-hintbox-delay', 0)
			.attr('data-hintbox-ignore-position-change', 1)
			.each((d, index, nodes) => {
				const sector = nodes[index];

				d3.select(sector)
					.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}`)
					.data((d) => [d])
					.join('svg:path')
					.attr('class', CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER)
					.attr('d', (d) => this.#arcGenerator(d))
					.each((d, index, nodes) => nodes[index]._current = d);

				if (this.#config.stroke > 0) {
					d3.select(sector)
						.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_STROKE}`)
						.data((d) => [d])
						.join('svg:path')
						.attr('class', CSVGPie.ZBX_STYLE_ARC_STROKE)
						.style('transition', this.#sectors_old.length > 0
							? `fill ${CSVGPie.ANIMATE_DURATION_WHOLE}ms linear`
							: ''
						)
						.each((d, index, nodes) => nodes[index]._current = d);
				}

				d3.select(sector)
					.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
					.data((d) => [d])
					.join('svg:path')
					.attr('class', CSVGPie.ZBX_STYLE_ARC)
					.style('transition', this.#sectors_old.length > 0
						? `fill ${CSVGPie.ANIMATE_DURATION_WHOLE}ms linear`
						: ''
					)
					.each((d, index, nodes) => nodes[index]._current = d);
			})
			.on('mouseleave', null);

		if (this.#config.space > 0) {
			this.#space_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_SPACE}`)
				.data(this.#pieGenerator(was), key)
				.join('svg:line')
				.attr('class', CSVGPie.ZBX_STYLE_SPACE)
				.attr('x1', 0)
				.attr('x2', 0)
				.attr('y1', `${-this.#radius_inner}`)
				.attr('y2', `${-(this.#radius_outer + this.#radius_outer * this.#pop_out_distance)}`)
				.style('stroke-width', this.#config.space * 10 / 2)
				.each((d, index, nodes) => nodes[index]._current = d);
		}

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_CONTAINER}`)
			.data(this.#pieGenerator(is), key)
			.attr('data-hintbox-contents', d => this.#getHint(d.data))
			.each((d, index, nodes) => {
				const sector = nodes[index];

				if (sector.matches(':hover')) {
					// Simulate mouse leave event to show hint properly.
					// Must be jQuery event because hints listen to jQuery events.
					jQuery(sector).trigger(jQuery.Event('mouseleave'));
				}
			});

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}`)
			.data(this.#pieGenerator(is), key);

		if (this.#config.stroke > 0) {
			this.#arcs_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_STROKE}`)
				.data(this.#pieGenerator(is), key);
		}

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
			.data(this.#pieGenerator(is), key);

		if (this.#config.space > 0) {
			this.#space_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_SPACE}`)
				.data(this.#pieGenerator(is), key);
		}

		this.#rendered_promise = this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}, .${CSVGPie.ZBX_STYLE_ARC_STROKE}, .${CSVGPie.ZBX_STYLE_ARC}`)
			.transition()
			.duration(CSVGPie.ANIMATE_DURATION_WHOLE)
			.attrTween('d', (d, index, nodes) => {
				const _this = nodes[index];

				const interpolate = d3.interpolate(_this._current, d);

				return t => {
					_this._current = interpolate(t);

					if (this.#config.stroke > 0 && _this.classList.contains(CSVGPie.ZBX_STYLE_ARC)) {
						return this.#arcGeneratorWithPadding(_this._current);
					}
					else {
						return this.#arcGenerator(_this._current);
					}
				};
			})
			.on('start', () => {
				if (this.#config.stroke > 0) {
					this.#arcs_container
						.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_STROKE}`)
						.style('fill', (d) => {
							const multiplier = 0.7;
							const intensity = 20;

							const color_darker = d3.color(d.data.color).darker(multiplier);

							const {l, c, h} = d3.lch(color_darker);

							return d3.lch(l, c + intensity * multiplier, h);
						});
				}

				this.#arcs_container
					.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
					.style('fill', (d) => d.data.color);
			})
			.end()
			.then(() => {
				const sectors = this.#arcs_container.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_CONTAINER}`).nodes();

				for (const sector of sectors) {
					this.#sector_observer.observe(sector, {
						attributeFilter: ['data-expanded'],
						attributeOldValue: true
					});

					setTimeout(() => {
						// If mouse was on any sector before/during animation, then pop that sector out again.
						if (sector.matches(':hover')) {
							this.#popOut(sector);

							// Simulate mouse move event to show hint properly.
							// Must be jQuery event because hints listen to jQuery events.
							const mousemove = jQuery.Event('mousemove');

							mousemove.clientX = this.#svg.node().dataset.mouseClientX;
							mousemove.clientY = this.#svg.node().dataset.mouseClientY;

							jQuery(sector).trigger(mousemove);
						}
					});

					d3.select(sector).on('mouseleave', () => this.#onMouseLeave(sector));
				}

				this.#svg.on('mousemove', (e) => this.#onMouseMove(e));
			})
			.catch(() => {});

		if (this.#config.space > 0) {
			this.#space_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_SPACE}`)
				.transition()
				.duration(CSVGPie.ANIMATE_DURATION_WHOLE)
				.styleTween('transform', (d, index, nodes) => {
					const _this = nodes[index];

					const interpolate = d3.interpolate(_this._current, d);

					return t => {
						_this._current = interpolate(t);

						return `rotate(${_this._current.startAngle}rad)`;
					};
				});
		}

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}`)
			.data(this.#pieGenerator(this.#sectors_new), key);

		if (this.#config.stroke > 0) {
			this.#arcs_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_STROKE}`)
				.data(this.#pieGenerator(this.#sectors_new), key);
		}

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC}`)
			.data(this.#pieGenerator(this.#sectors_new), key);

		this.#arcs_container
			.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_CONTAINER}`)
			.data(this.#pieGenerator(this.#sectors_new), key)
			.exit()
			.transition()
			.delay(CSVGPie.ANIMATE_DURATION_WHOLE)
			.duration(0)
			.remove()
			.end()
			.then(() => {
				const sectors = this.#arcs_container.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_CONTAINER}`).nodes();

				if (sectors.length === 0) {
					this.#container
						.selectAll(`.${CSVGPie.ZBX_STYLE_ARC_NO_DATA_OUTER}, .${CSVGPie.ZBX_STYLE_ARC_NO_DATA_INNER}`)
						.style('display', '');

					this.#no_data_container.style('display', '');
				}

				if (this.#config.space > 0 && sectors.length < 2) {
					this.#space_container.style('display', 'none');
				}
			})
			.catch(() => {});

		if (this.#config.space > 0) {
			this.#space_container
				.selectAll(`.${CSVGPie.ZBX_STYLE_SPACE}`)
				.data(this.#pieGenerator(this.#sectors_new), key)
				.exit()
				.transition()
				.delay(CSVGPie.ANIMATE_DURATION_WHOLE)
				.duration(0)
				.remove();
		}

		this.#positionValue();
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
	 * Get rendered promise.
	 *
	 * @returns {Promise<void>}
	 */
	promiseRendered() {
		return this.#rendered_promise;
	}

	/**
	 * Create containers for elements (arcs, total value, no data text).
	 */
	#createContainers() {
		// SVG group element implementing padding inside the root SVG element.
		const main = this.#svg
			.append('svg:g')
			.attr('transform', `translate(${this.#padding.horizontal} ${this.#padding.vertical})`);

		this.#container = main.append('svg:g');

		this.#arcs_container = this.#container
			.append('svg:g')
			.attr('class', CSVGPie.ZBX_STYLE_ARCS);

		if (this.#config.space > 0) {
			this.#space_container = this.#container
				.append('svg:g')
				.attr('class', CSVGPie.ZBX_STYLE_SPACE_CONTAINER);
		}

		this.#container
			.append('svg:circle')
			.attr('class', CSVGPie.ZBX_STYLE_ARC_NO_DATA_OUTER)
			.attr('r', this.#radius_outer);

		if (this.#config.draw_type === CSVGPie.DRAW_TYPE_DOUGHNUT) {
			this.#container
				.append('svg:circle')
				.attr('class', CSVGPie.ZBX_STYLE_ARC_NO_DATA_INNER)
				.attr('r', this.#radius_inner);

			let total_value_width = this.#radius_inner * 2;
			total_value_width -= total_value_width / CSVGPie.TOTAL_VALUE_PADDING;

			if (this.#config.total_value?.show) {
				this.#total_value_container = this.#container
					.append('svg:foreignObject')
					.attr('class', CSVGPie.ZBX_STYLE_TOTAL_VALUE)
					.attr('x', -total_value_width / 2)
					.attr('width', `${total_value_width}px`)
					.style('font-weight', this.#config.total_value.is_bold ? 'bold' : '')
					.style('color', this.#config.total_value.color !== '' ? this.#config.total_value.color : '')
					.style('display', 'none');

				this.#total_value_container.append('xhtml:div');
			}
		}

		this.#no_data_container = this.#container
			.append('svg:text')
			.attr('class', CSVGPie.ZBX_STYLE_TOTAL_VALUE_NO_DATA)
			.style('font-weight', this.#config.total_value?.is_bold ? 'bold' : '')
			.text(t('No data'));
	}

	/**
	 * Adjust position of total value and no data text inside pie chart.
	 */
	#positionValue() {
		const getAutoFontSize = (text, default_size, font_weight) => {
			let available_width = this.#radius_inner * 2 * this.#scale;
			available_width -= available_width / CSVGPie.TOTAL_VALUE_PADDING;

			const text_width = this.#getMeasuredTextWidth(text, default_size, font_weight);

			const width_ratio = available_width / text_width;

			const default_height = default_size * width_ratio;
			const max_height = available_width * CSVGPie.TEXT_BASELINE;

			const normal_height = Math.min(default_height, max_height) * .875;
			const min_height = CSVGPie.TOTAL_VALUE_HEIGHT_MIN;

			return Math.max(normal_height, min_height) / this.#scale;
		}

		if (this.#sectors_new.length > 0) {
			this.#no_data_container.style('display', 'none');

			if (this.#config.total_value?.show) {
				this.#total_value_container
					.select('div')
					.text(this.#total_value_text)
					.attr('title', this.#total_value_text);

				if (this.#config.total_value.is_custom_size) {
					this.#total_value_font_size = this.#config.total_value.size * 10;
				}
				else {
					if (this.#scale > 0) {
						const font_weight = this.#config.total_value.is_bold ? 'bold' : '';

						this.#total_value_font_size = getAutoFontSize(
							this.#total_value_text, CSVGPie.TOTAL_VALUE_HEIGHT_MIN, font_weight
						);
					}
					else {
						this.#total_value_font_size = 0;
					}
				}

				this.#total_value_container
					.attr('y', -this.#total_value_font_size / 2 / CSVGPie.TEXT_BASELINE)
					.attr('height', `${this.#total_value_font_size / CSVGPie.TEXT_BASELINE}px`)
					.style('line-height', `${this.#total_value_font_size / CSVGPie.TEXT_BASELINE}px`)
					.style('font-size', `${this.#total_value_font_size}px`)
					.style('display', '');
			}
		}
		else {
			if (this.#config.total_value?.show) {
				this.#total_value_container.style('display', 'none');

				this.#total_value_container
					.select('div')
					.text('');

				if (this.#config.total_value.is_custom_size) {
					this.#no_data_container
						.attr('y', this.#config.total_value.size / 2 * 10 * CSVGPie.TEXT_BASELINE)
						.style('font-size', `${this.#config.total_value.size * 10}px`);
				}
				else {
					const font_weight = this.#config.total_value.is_bold ? 'bold' : '';

					const text_width = this.#getMeasuredTextWidth(
						this.#no_data_container.text(), CSVGPie.TOTAL_VALUE_SIZE_DEFAULT, font_weight
					);

					let text_scale = this.#radius_inner * 2 / text_width;
					text_scale -= text_scale / CSVGPie.TOTAL_VALUE_PADDING;

					this.#no_data_container
						.attr('transform', `scale(${text_scale})`)
						.attr('y', CSVGPie.TOTAL_VALUE_SIZE_DEFAULT / 2 * CSVGPie.TEXT_BASELINE)
						.style('font-size', `${CSVGPie.TOTAL_VALUE_SIZE_DEFAULT}px`);
				}
			}
			else {
				this.#no_data_container
					.attr('y', CSVGPie.TOTAL_VALUE_SIZE_DEFAULT / 2 * 10 * CSVGPie.TEXT_BASELINE)
					.style('font-size', `${CSVGPie.TOTAL_VALUE_SIZE_DEFAULT * 10}px`);
			}
		}
	}

	/**
	 * Set hint for sector.
	 *
	 * @param {Object} sector  All necessary information about sector.
	 *
	 * @returns {string}
	 */
	#getHint(sector) {
		const hint = d3.create('div')
			.attr('class', 'svg-pie-chart-hintbox');

		hint.append('span')
			.attr('class', 'svg-pie-chart-hintbox-color')
			.style('background-color', sector.color);

		hint.append('span')
			.attr('class', 'svg-pie-chart-hintbox-name')
			.text(`${sector.name}: `);

		hint.append('span')
			.attr('class', 'svg-pie-chart-hintbox-value')
			.text(() => {
				let text = sector.formatted_value.value;

				if (sector.formatted_value.units !== '') {
					text += ` ${sector.formatted_value.units}`;
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
	 * @param {number} font_size
	 * @param {number|string} font_weight
	 *
	 * @returns {number}
	 */
	#getMeasuredTextWidth(text, font_size, font_weight = '') {
		this.#canvas_context.font = `${font_weight} ${font_size}px ${this.#svg.style('font-family')}`;

		return this.#canvas_context.measureText(text).width;
	}

	/**
	 * Determine when sectors can be popped out or popped in.
	 *
	 * @param {Event} e  Mouse move event.
	 */
	#onMouseMove(e) {
		const sector = e.target.closest(`.${CSVGPie.ZBX_STYLE_ARC_CONTAINER}`);

		if (sector !== null && !sector.dataset.expanded) {
			if (this.#popped_out_sector === null) {
				this.#popOut(sector);
			}
			else if (sector !== this.#popped_out_sector) {
				this.#popIn(this.#popped_out_sector);
				this.#popOut(sector);
			}
		}

		this.#svg.node().dataset.mouseClientX = e.clientX;
		this.#svg.node().dataset.mouseClientY = e.clientY;
	}

	/**
	 * Pop in sector if it lost mouse capture.
	 *
	 * @param {SVGGElement} sector
	 */
	#onMouseLeave(sector) {
		if (!sector.dataset.expanded) {
			this.#popIn(sector);
		}
	}

	/**
	 * Pop out a sector.
	 *
	 * @param {SVGGElement} sector
	 */
	#popOut(sector) {
		const sector_d3 = d3.select(sector);

		sector_d3
			.transition()
			.duration(CSVGPie.ANIMATE_DURATION_POP_OUT)
			.attr('transform', `scale(${1 + this.#pop_out_distance})`);

		// Translate placeholder back in its original position to simulate not popping it.
		sector_d3
			.select(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}`)
			.transition()
			.duration(CSVGPie.ANIMATE_DURATION_POP_OUT)
			.attr('transform', `scale(${1 - this.#pop_out_distance})`);

		this.#popped_out_sector = sector;
	}

	/**
	 * Pop in a sector.
	 *
	 * @param {SVGGElement} sector
	 */
	#popIn(sector) {
		if (!sector.dataset.expanded) {
			const sector_d3 = d3.select(sector);

			sector_d3
				.transition()
				.duration(CSVGPie.ANIMATE_DURATION_POP_IN)
				.attr('transform', 'scale(1)');

			// Translate placeholder back in its original position to simulate not popping it.
			sector_d3
				.select(`.${CSVGPie.ZBX_STYLE_ARC_PLACEHOLDER}`)
				.transition()
				.duration(CSVGPie.ANIMATE_DURATION_POP_IN)
				.attr('transform', 'scale(1)');
		}

		this.#popped_out_sector = null;
	}
}
