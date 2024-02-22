/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CSVGHoneycomb {

	static ZBX_STYLE_CLASS =				'svg-honeycomb';
	static ZBX_STYLE_HONEYCOMB_CONTAINER =	'svg-honeycomb-container';
	static ZBX_STYLE_CELL =					'svg-honeycomb-cell';
	static ZBX_STYLE_CELL_NO_DATA =			'svg-honeycomb-cell-no-data';
	static ZBX_STYLE_CELL_SHADOW =			'svg-honeycomb-cell-shadow';
	static ZBX_STYLE_CELL_OTHER =			'svg-honeycomb-cell-other';
	static ZBX_STYLE_CELL_OTHER_ELLIPSIS =	'svg-honeycomb-cell-other-ellipsis';
	static ZBX_STYLE_CONTENT =				'svg-honeycomb-content';
	static ZBX_STYLE_BACKDROP =				'svg-honeycomb-backdrop';
	static ZBX_STYLE_LABEL =				'svg-honeycomb-label';
	static ZBX_STYLE_LABEL_PRIMARY =		'svg-honeycomb-label-primary';
	static ZBX_STYLE_LABEL_SECONDARY =		'svg-honeycomb-label-secondary';

	static ID_COUNTER = 0;

	static CELL_WIDTH_MIN = 50;
	static LABEL_WIDTH_MIN = 56;
	static FONT_SIZE_MIN = 12;
	static LINE_HEIGHT = 1.15;

	static EVENT_CELL_CLICK = 'cell.click';
	static EVENT_CELL_ENTER = 'cell.enter';
	static EVENT_CELL_LEAVE = 'cell.leave';

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
	 * Container calculated parameters based on SVG size and cells data.
	 *
	 * @type {Object}
	 */
	#container_params = {
		x: 0,
		y: 0,
		width: 0,
		height: 0,
		columns: 1,
		rows: 1,
		scale: 1
	}

	/**
	 * Data about cells.
	 *
	 * @type {Array}
	 */
	#cells_data = [];

	/**
	 * Maximum number of cells based on the container size.
	 *
	 * @type {number}
	 */
	#cells_max_count;

	/**
	 * Width of cell (inner radius).
	 * It is large number because SVG works more precise that way (later it will be scaled according to widget size).
	 *
	 * @type {number}
	 */
	#cell_width = 1000;

	/**
	 * Height of cell (outer radius).
	 * @type {number}
	 */
	#cell_height = this.#cell_width / Math.sqrt(3) * 2;

	/**
	 * Gap between cells.
	 *
	 * @type {number}
	 */
	#cells_gap = this.#cell_width / 12;

	/**
	 * d attribute of path element to display hexagonal cell.
	 *
	 * @type {string}
	 */
	#cell_path;

	/**
	 * Unique ID of root SVG element.
	 *
	 * @type {string}
	 */
	#svg_id;

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
	 * Created SVG child elements of honeycomb.
	 *
	 * @type {SVGSVGElement}
	 * @member {Selection}
	 */
	#honeycomb_container;

	/**
	 * Canvas context for text measuring.
	 *
	 * @type {CanvasRenderingContext2D}
	 */
	#canvas_context = null;

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

		this.#svg_id = CSVGHoneycomb.#getUniqueId();

		this.#svg = d3.create('svg')
			.attr('id', this.#svg_id)
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CLASS)
			// Add filter element for shadow of popped cell.
			.call(svg => svg
				.append('defs')
				.append('filter')
				.attr('id', `${CSVGHoneycomb.ZBX_STYLE_CELL_SHADOW}-${this.#svg_id}`)
				.attr('x', '-50%')
				.attr('y', '-50%')
				.attr('width', '200%')
				.attr('height', '200%')
				.append('feDropShadow')
				.attr('dx', 0)
				.attr('dy', 0)
				.attr('flood-color', 'rgba(0, 0, 0, .2)')
			);

		this.#container = this.#svg
			.append('g')
			.attr('transform', `translate(${this.#padding.horizontal} ${this.#padding.vertical})`)
			.append('g');

		this.#honeycomb_container = this.#container
			.append('g')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_HONEYCOMB_CONTAINER)
			.style('--line-height', CSVGHoneycomb.LINE_HEIGHT);

		this.#cell_path = this.#generatePath(this.#cell_height, this.#cells_gap);
		this.#canvas_context = document.createElement('canvas').getContext('2d');
	}

	/**
	 * Set size of the root SVG element and re-position the elements.
	 *
	 * @param {number} width
	 * @param {number} height
	 */
	setSize({width, height}) {
		this.#width = width - this.#padding.horizontal * 2;
		this.#height = height - this.#padding.vertical * 2;

		this.#svg
			.attr('width', width)
			.attr('height', height);

		this.#adjustSize();
		this.#updateCells();
	}

	/**
	 * Set value (cells) of honeycomb.
	 *
	 * @param {Array} cells  Array of cells to show in honeycomb.
	 */
	setValue({cells}) {
		this.#cells_data = cells;

		this.#adjustSize();
		this.#updateCells();
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
	 * Adjust size of honeycomb.
	 */
	#adjustSize() {
		const calculateContainerParams = (rows, max_columns) => {
			const columns = Math.max(1, Math.min(max_columns, Math.floor(this.#cells_max_count / rows)));

			rows = Math.ceil(Math.max(1, this.#cells_max_count) / columns);

			const width = this.#cell_width * columns +
				(rows > 1 && columns * 2 <= this.#cells_max_count ? this.#cell_width / 2 : 0);

			const height = this.#cell_height * .25 * (3 * rows + 1) - this.#cells_gap;
			const scale = Math.min(this.#width / (width - this.#cells_gap * .5), this.#height / height);

			return {
				x: (this.#width - width * scale) / 2,
				y: (this.#height - (height + this.#cells_gap) * scale) / 2,
				width,
				height,
				columns,
				rows,
				scale,
				cell_padding: 4 / scale
			};
		};

		const cell_min_width = CSVGHoneycomb.CELL_WIDTH_MIN;
		const cell_min_height = CSVGHoneycomb.CELL_WIDTH_MIN / Math.sqrt(3) * 2;

		const max_rows = Math.floor((this.#height - cell_min_height) / (cell_min_height * .75)) + 1;
		const max_columns = Math.floor((this.#width - (max_rows > 1 ? cell_min_width / 2 : 0)) / cell_min_width);

		this.#cells_max_count = Math.min(this.#cells_data.length, max_rows * max_columns);

		const rows = Math.max(1, Math.min(max_rows, this.#cells_max_count,
			Math.sqrt(this.#height * this.#cells_max_count / this.#width))
		);

		const params_0 = calculateContainerParams(Math.floor(rows), max_columns);
		const params_1 = calculateContainerParams(Math.ceil(rows), max_columns);

		this.#container_params = (params_0.scale > params_1.scale) ? params_0 : params_1;

		this.#container.attr('transform',
			`translate(${this.#container_params.x} ${this.#container_params.y}) scale(${this.#container_params.scale})`
		);
	}

	#updateCells() {
		let data;

		if (this.#cells_data.length > this.#cells_max_count && this.#cells_max_count > 0) {
			data = [...this.#cells_data.slice(0, this.#cells_max_count - 1), {itemid: 0, has_more: true}];
		}
		else if (this.#cells_data.length > 0) {
			data = this.#cells_data.slice(0, this.#cells_max_count);
		}
		else {
			data = [{itemid: 1, no_data: true}];
		}

		this.#calculateLabelsParams(data.filter(d => d.has_more !== true && d.no_data !== true),
			this.#cell_width - this.#cells_gap, this.#cell_height / 2, false
		);

		this.#honeycomb_container
			.style('--stroke-width', 2 / this.#container_params.scale)
			.selectAll(`g.${CSVGHoneycomb.ZBX_STYLE_CELL}`)
			.each((d, i, cells) => {
				if (d.scale_timeout !== undefined) {

					clearTimeout(d.scale_timeout);
					delete(d.scale_timeout);

					if (d.scaled) {
						d.scaled = true;

						this.#cellLeave(d3.select(cells[i]), d);
					}
				}
			})
			.data(data, (d, i) => {
				const row = Math.floor(i / this.#container_params.columns);
				const column = i % this.#container_params.columns;

				d.position = {
					x: this.#cell_width * (column + row % 2 * .5) + this.#cell_width * .5,
					y: this.#cell_height * row * .75 + this.#cell_height * .5
				};

				d.index = i;

				return d.itemid;
			})
			.join(
				enter => enter
					.append('g')
					.attr('class', CSVGHoneycomb.ZBX_STYLE_CELL)
					.attr('data-index', d => d.index)
					.style('--x', d => `${d.position.x}px`)
					.style('--y', d => `${d.position.y}px`)
					.style('--fill', d => this.#getFillColor(d))
					.style('--stroke', d => this.#getFillColor(d))
					.call(cell => cell
						.append('path')
						.attr('d', this.#cell_path)
					)
					.each((d, i, cells) => {
						const cell = d3.select(cells[i]);

						if (d.no_data === true) {
							this.#drawCellNoData(cell);
						}
						else if (d.has_more === true) {
							this.#drawCellHasMore(cell);
						}
						else {
							this.#drawCell(cell);
						}
					}),
				update => update
					.attr('data-index', d => d.index)
					.style('--x', d => `${d.position.x}px`)
					.style('--y', d => `${d.position.y}px`)
					.style('--fill', d => this.#getFillColor(d))
					.style('--stroke', d => this.#getFillColor(d))
					.each((d, i, cells) => {
						const cell = d3.select(cells[i]);

						if (d.no_data !== true && d.has_more !== true) {
							this.#drawLabel(cell);
						}
					}),
				exit => exit.remove()
			);
	}

	/**
	 * Draw "has more" cell that indicates that all cells do not fit in available space in widget.
	 *
	 * @param {Selection} cell
	 */
	#drawCellHasMore(cell) {
		cell
			.classed(CSVGHoneycomb.ZBX_STYLE_CELL_OTHER, true)
			.append('g')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CELL_OTHER_ELLIPSIS)
			.call(ellipsis => {
				for (let i = -1; i <= 1; i++) {
					ellipsis
						.append('circle')
						.attr('cx', this.#cell_width / 5 * i)
						.attr('r', this.#cell_width / 20);
				}
			});
	}

	/**
	 * @param {Selection} cell
	 */
	#drawCellNoData(cell) {
		cell
			.classed(CSVGHoneycomb.ZBX_STYLE_CELL_NO_DATA, true)
			.append('foreignObject')
			.append('xhtml:div')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CONTENT)
			.append('span')
			.text(t('No data'))
			.style('font-size',
				`${Math.max(CSVGHoneycomb.FONT_SIZE_MIN / this.#container_params.scale, this.#cell_width / 10)}px`
			);

		this.#resizeLabels(cell, this.#cell_width - this.#cells_gap, this.#cell_height / 2);
	};

	#drawCell(cell) {
		cell
			.call(cell => this.#drawLabel(cell))
			.on('click', (e, d) => {
				this.#svg.dispatch(CSVGHoneycomb.EVENT_CELL_CLICK, {
					detail: {
						hostid: d.hostid,
						itemid: d.itemid
					}
				});
			})
			.on('mouseenter', (e, d) => {
				if (d.scale_timeout === undefined) {
					cell.raise();

					d.scale_timeout = setTimeout(() => {
						d.scaled = true;

						this.#cellEnter(cell, d);
					});
				}
			})
			.on('mouseleave', (e, d) => {
				if (d.scale_timeout !== undefined) {

					clearTimeout(d.scale_timeout);
					delete(d.scale_timeout);

					if (d.scaled) {
						d.scaled = true;

						this.#cellLeave(cell, d);
					}
				}
			});
	}

	#cellEnter(cell, d) {
		const margin = {
			horizontal: (this.#padding.horizontal / 2 + this.#container_params.x) / this.#container_params.scale,
			vertical: (this.#padding.vertical / 2 + this.#container_params.y) / this.#container_params.scale
		};

		const scale = Math.min(
			this.#container_params.width / Math.sqrt(3) * 2 + margin.horizontal * 2,
			this.#container_params.height + this.#cells_gap + margin.vertical * 2,
			this.#cell_height * (0.15 / this.#container_params.scale + 0.55)
		);

		const scaled_size = {
			width: scale * Math.sqrt(3) / 2,
			height: scale
		}

		const cell_scale = scale / (this.#cell_height - this.#cells_gap);

		const scaled_position = {
			dx: Math.max(
				scaled_size.width / 2 - margin.horizontal,
				Math.min(
					this.#container_params.width - scaled_size.width / 2 + margin.horizontal,
					d.position.x
				)
			) - d.position.x,
			dy: Math.max(
				scaled_size.height / 2 - margin.vertical,
				Math.min(
					this.#container_params.height + this.#cells_gap - scaled_size.height / 2 + margin.vertical,
					d.position.y
				)
			) - d.position.y
		};

		if (cell.select(`.${CSVGHoneycomb.ZBX_STYLE_BACKDROP}`).empty()) {
			cell
				.append('path')
				.classed(CSVGHoneycomb.ZBX_STYLE_BACKDROP, true)
				.attr('d', this.#generatePath(Math.min(this.#cell_height * 1.75, scaled_size.height * .75), 0));
		}
		else {
			clearTimeout(d.backdrop_timeout);
		}

		d.stored_labels = d.labels;

		this.#calculateLabelsParams([d], scaled_size.width, (scaled_size.height + this.#cells_gap) / 2, true);
		this.#resizeLabels(cell, scaled_size.width, (scaled_size.height + this.#cells_gap) / 2);

		this.#svg
			.select(`#${CSVGHoneycomb.ZBX_STYLE_CELL_SHADOW}-${this.#svg_id} feDropShadow`)
			.attr('stdDeviation', 25 / this.#container_params.scale / cell_scale);

		cell
			.style('--dx', `${scaled_position.dx}px`)
			.style('--dy', `${scaled_position.dy}px`)
			.style('--stroke', d => d3.color(this.#getFillColor(d))?.darker(.3).formatHex())
			.style('--stroke-width', 2 / this.#container_params.scale / cell_scale)
			.style('--scale', cell_scale)
			.select('path')
			.style('filter', `url(#${CSVGHoneycomb.ZBX_STYLE_CELL_SHADOW}-${this.#svg_id})`);

		this.#svg
			.style('--shadow-opacity', 1)
			.dispatch(CSVGHoneycomb.EVENT_CELL_ENTER, {
				detail: {
					hostid: d.hostid,
					itemid: d.itemid
				}
			});
	}

	#cellLeave(cell, d) {
		d.labels = d.stored_labels;

		this.#resizeLabels(cell, this.#cell_width - this.#cells_gap, this.#cell_height  / 2);

		cell
			.style('--dx', null)
			.style('--dy', null)
			.style('--stroke', d => this.#getFillColor(d))
			.style('--scale', null)
			.style('--stroke-width', null)
			.select('path')
			.style('filter', null);

		this.#svg
			.style('--shadow-opacity', null)
			.dispatch(CSVGHoneycomb.EVENT_CELL_LEAVE, {
				detail: {
					hostid: d.hostid,
					itemid: d.itemid
				}
			});

		d.backdrop_timeout = setTimeout(() => {
			cell
				.select(`.${CSVGHoneycomb.ZBX_STYLE_BACKDROP}`)
				.remove();
		}, UI_TRANSITION_DURATION);
	}

	#drawLabel(cell) {
		cell.call(cell => cell.select('foreignObject')?.remove());

		const makeLabel = (label) => {
			return d3.create('span')
				.attr('class', CSVGHoneycomb.ZBX_STYLE_LABEL)
				.call(span => {
					for (const [i, line] of label.lines.entries()) {
						span
							.call(line => {
								if (i > 0) {
									line.append('br');
								}
							})
							.append('span')
							.text(line);
					}
				});
		};

		cell
			.append('foreignObject')
			.append('xhtml:div')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CONTENT)
			.call(container => {
				if (this.#config.primary_label.show) {
					container.append(d => makeLabel(d.labels.primary)
						.classed(CSVGHoneycomb.ZBX_STYLE_LABEL_PRIMARY, true)
						.node()
					);
				}

				if (this.#config.secondary_label.show) {
					container.append(d => makeLabel(d.labels.secondary)
						.classed(CSVGHoneycomb.ZBX_STYLE_LABEL_SECONDARY, true)
						.node()
					);
				}
			});

		this.#resizeLabels(cell, this.#cell_width - this.#cells_gap, this.#cell_height / 2);
	}

	#resizeLabels(cell, width, height) {
		cell
			.call(cell => cell.select('foreignObject')
				.attr('x', d => d.position.x + this.#container_params.cell_padding - width / 2)
				.attr('y', d => d.position.y + -height / 2)
				.attr('width', width - this.#container_params.cell_padding * 2)
				.attr('height', height)
			)
			.call(cell => cell.select(`.${CSVGHoneycomb.ZBX_STYLE_LABEL_PRIMARY}`)
				.style('max-height', d => `${d.labels.primary.lines_count * CSVGHoneycomb.LINE_HEIGHT}em`)
				.style('font-size', d => `${d.labels.primary.font_size}px`)
				.style('font-weight', d => d.labels.primary.font_weight)
				.style('color', d => d.labels.primary.color)

			)
			.call(cell => cell.select(`.${CSVGHoneycomb.ZBX_STYLE_LABEL_SECONDARY}`)
				.style('max-height', d => `${d.labels.secondary.lines_count * CSVGHoneycomb.LINE_HEIGHT}em`)
				.style('font-size', d => `${d.labels.secondary.font_size}px`)
				.style('font-weight', d => d.labels.secondary.font_weight)
				.style('color', d => d.labels.secondary.color)
			);
	}

	#calculateLabelsParams(data, cell_width, container_height, is_scaled) {
		if (!data.length) {
			return;
		}

		for (const d of data) {
			d.labels = {primary: null, secondary: null};
		}

		const calculateLabelParams = (data, container_width, container_height, is_primary) => {
			const c_param = is_primary ? 'primary_label' : 'secondary_label';
			const d_param = is_primary ? 'primary' : 'secondary';

			const is_custom_size = this.#config[c_param].is_custom_size;
			const font_weight = this.#config[c_param].is_bold ? 'bold' : null;

			for (const d of data) {
				const lines = d[c_param].replace('\r', '').split('\n');
				const lines_count = lines.length;

				d.labels[d_param] = {
					lines,
					lines_count,
					line_max_length: Math.ceil(Math.max(...lines.map(line => line.length)) / 8) * 8,
					color: d.is_numeric && this.#config[c_param].color !== '' ? `#${this.#config[c_param].color}` : null,
					font_size: 0,
					font_weight,
					is_custom_size
				};
			}

			if ((container_width * this.#container_params.scale) < CSVGHoneycomb.LABEL_WIDTH_MIN) {
				return;
			}

			for (const d of data) {
				d.labels[d_param] = {...d.labels[d_param],
					font_size: is_custom_size
						? this.#getFontSizeByPercent(this.#config[c_param].size, container_height)
						: this.#getFontSizeByWidth(d.labels[d_param].lines, container_width * .9, font_weight ?? '')
				};
			}

			const thresholds = new Map();

			for (const d of data) {
				const step = d.labels[d_param].line_max_length;

				thresholds.set(step, thresholds.has(step)
					? Math.min(thresholds.get(step), d.labels[d_param].font_size)
					: d.labels[d_param].font_size
				);
			}

			for (const d of data) {
				if (!d.labels[d_param].is_custom_size) {
					d.labels[d_param].font_size = Math.max(
						CSVGHoneycomb.FONT_SIZE_MIN / this.#container_params.scale,
						Math.min(
							thresholds.get(d.labels[d_param].line_max_length),
							Math.floor(container_height / (d.labels[d_param].lines_count * CSVGHoneycomb.LINE_HEIGHT))
						)
					);
				}
			}
		}

		const container_width = cell_width - this.#container_params.cell_padding * 2;

		if (this.#config.primary_label.show) {
			calculateLabelParams(data, container_width, container_height, true)
		}

		if (this.#config.secondary_label.show) {
			calculateLabelParams(data, container_width, container_height, false)
		}

		const font_size_min = CSVGHoneycomb.FONT_SIZE_MIN / this.#container_params.scale;
		const height_limit = container_height / CSVGHoneycomb.LINE_HEIGHT;

		for (const d of data) {
			const { primary, secondary } = d.labels;

			let p_font_size = primary?.font_size || 0;
			const p_lines_count = primary?.lines_count || 0;

			let s_font_size = secondary?.font_size || 0;
			const s_lines_count = secondary?.lines_count || 0;

			if ((p_font_size * p_lines_count + s_font_size * s_lines_count) <= height_limit) {
				continue;
			}

			let font_scale = (p_font_size + s_font_size > height_limit)
				? height_limit / (p_font_size + s_font_size)
				: 1;

			p_font_size *= primary?.is_custom_size ? 1 : font_scale;
			s_font_size *= secondary?.is_custom_size ? 1 : font_scale;

			if (secondary !== null) {
				secondary.lines_count = Math.min(
					secondary.lines_count,
					Math.max(1, Math.floor(height_limit / (p_font_size + s_font_size)))
				);

				if (is_scaled && !secondary?.is_custom_size && secondary.lines_count < s_lines_count) {
					const height = secondary.lines_count * s_font_size;
					s_font_size = Math.min(s_font_size, Math.max(font_size_min, height / s_lines_count));
					secondary.lines_count = Math.floor(height / s_font_size);
				}

				secondary.font_size = s_font_size;
			}

			if (primary !== null) {
				primary.lines_count = Math.min(
					primary.lines_count,
					Math.max(1, Math.floor((height_limit + s_font_size) / p_font_size - s_lines_count))
				);

				if (is_scaled && !primary?.is_custom_size && primary.lines_count < p_lines_count) {
					const height = primary.lines_count * p_font_size;
					p_font_size = Math.min(p_font_size, Math.max(font_size_min, height / p_lines_count));
					primary.lines_count = Math.floor(height / p_font_size);
				}

				primary.font_size = p_font_size;
			}
		}
	}

	#getFillColor(d) {
		if (!d.is_numeric) {
			// Do not apply thresholds to non-numeric items
			return null;
		}

		const bg_color = this.#config.bg_color !== '' ? `#${this.#config.bg_color}` : null;

		if (this.#config.thresholds.length === 0) {
			return bg_color;
		}

		const value = parseFloat(d.value);
		const threshold_type = d.is_binary_units ? 'threshold_binary' : 'threshold';
		const apply_interpolation = this.#config.apply_interpolation && this.#config.thresholds.length > 1;

		let prev = null;
		let curr;

		for (let i = 0; i < this.#config.thresholds.length; i++) {
			curr = this.#config.thresholds[i];

			if (value < curr[threshold_type]) {
				if (prev === null) {
					return apply_interpolation ? `#${curr.color}` : bg_color;
				}

				if (apply_interpolation) {
					// Position [0..1] of cell value between two adjacent thresholds
					const position = (value - prev[threshold_type]) / (curr[threshold_type] - prev[threshold_type]);

					return d3.color(d3.interpolateRgb(`#${prev.color}`, `#${curr.color}`)(position)).formatHex();
				}

				return `#${prev.color}`;
			}

			prev = curr;
		}

		return `#${curr.color}`;
	}

	/**
	 * Generate d attribute of path element to display hexagonal cell.
	 *
	 * @param {number} cell_size  Cell size equals height.
	 * @param {number} cells_gap
	 *
	 * @returns {string}  The d attribute of path element.
	 */
	#generatePath(cell_size, cells_gap) {
		const getPositionOnLine = (start, end, distance) => {
			const x = start[0] + (end[0] - start[0]) * distance;
			const y = start[1] + (end[1] - start[1]) * distance;

			return [x, y];
		};

		const cell_radius = (cell_size - cells_gap) / 2;
		const corner_count = 6;
		const corner_radius = 0.075;
		const handle_distance = corner_radius / 2;
		const offset = Math.PI / 2;

		const corner_position = d3.range(corner_count).map(side => {
			const radian = side * Math.PI * 2 / corner_count;
			const x = Math.cos(radian + offset) * cell_radius;
			const y = Math.sin(radian + offset) * cell_radius;
			return [x, y];
		});

		const corners = corner_position.map((corner, index) => {
			const prev = index === 0 ? corner_position[corner_position.length - 1] : corner_position[index - 1];
			const curr = corner;
			const next = index <= corner_position.length - 2 ? corner_position[index + 1] : corner_position[0];

			return {
				start: getPositionOnLine(prev, curr, 0.5),
				start_curve: getPositionOnLine(prev, curr, 1 - corner_radius),
				handle_1: getPositionOnLine(prev, curr, 1 - handle_distance),
				handle_2: getPositionOnLine(curr, next, handle_distance),
				end_curve: getPositionOnLine(curr, next, corner_radius)
			};
		});

		let path = `M${corners[0].start}`;
		path += corners.map(c => `L${c.start}L${c.start_curve}C${c.handle_1} ${c.handle_2} ${c.end_curve}`);
		path += 'Z';

		return path.replaceAll(',', ' ');
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
		this.#canvas_context.font = `${font_weight} ${font_size}px '${this.#svg.style('font-family')}'`;

		return this.#canvas_context.measureText(text).width;
	}

	#getFontSizeByPercent(font_size, fit_height) {
		return Math.max(
			CSVGHoneycomb.FONT_SIZE_MIN / this.#container_params.scale,
			fit_height * font_size / (100 * CSVGHoneycomb.LINE_HEIGHT)
		);
	}

	#getFontSizeByWidth(lines, fit_width, font_weight = '') {
		return Math.max(CSVGHoneycomb.FONT_SIZE_MIN / this.#container_params.scale,
			Math.min(...lines
				.filter(line => line !== '')
				.map(line => fit_width / this.#getMeasuredTextWidth(line, 10, font_weight) * 9)
			)
		);
	}

	/**
	 * Get unique ID.
	 *
	 * @returns {string}
	 */
	static #getUniqueId() {
		return `CSVGHoneycomb-${this.ID_COUNTER++}`;
	}
}
