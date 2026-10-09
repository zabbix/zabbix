/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CScatterPlot {

	static SCATTER_PLOT_MARKER_MIN_SIZE = 6;

	/**
	 * @type {SVGElement}
	 */
	#svg;

	/**
	 * @type {CWidgetScatterPlot}
	 */
	#widget;

	/**
	 * @type {number}
	 */
	#dimX;

	/**
	 * @type {number}
	 */
	#dimY;

	/**
	 * @type {number}
	 */
	#dimW;

	/**
	 * @type {number}
	 */
	#dimH;

	/**
	 * @type {Object}
	 */
	#datasets;

	/**
	 * @type {Object}
	 */
	#metrics;

	/**
	 * @type {Object}
	 */
	#paths;

	/**
	 * @type {boolean}
	 */
	#is_static_hintbox_opened = false;

	/**
	 * @type {number|undefined}
	 */
	#hintbox_timeout;

	constructor(svg, widget, options) {
		this.#svg = svg;

		this.#widget = widget;

		this.#dimX = options.dims.x;
		this.#dimY = options.dims.y;
		this.#dimW = options.dims.w;
		this.#dimH = options.dims.h;
		this.#datasets = options.hintbox_data.datasets;
		this.#metrics = options.hintbox_data.metrics;
		this.#paths = options.hintbox_data.paths;

		this.#svg.setAttribute('unselectable', 'true');
		this.#svg.style.userSelect = 'none';
	}

	activate() {
		this.#svg.addEventListener('click', this.#mouseClickHandler);
		this.#svg.addEventListener('mousemove', this.#mouseMoveHandler);
		this.#svg.addEventListener('mouseleave', this.#mouseLeaveHandler);
		this.#svg.addEventListener('onShowStaticHint', this.#onStaticHintboxOpen);
		this.#svg.addEventListener('onDeleteStaticHint', this.#onStaticHintboxClose);
	}

	deactivate() {
		this.#svg.removeEventListener('click', this.#mouseClickHandler);
		this.#svg.removeEventListener('mousemove', this.#mouseMoveHandler);
		this.#svg.removeEventListener('mouseleave', this.#mouseLeaveHandler);
		this.#svg.removeEventListener('onShowStaticHint', this.#onStaticHintboxOpen);
		this.#svg.removeEventListener('onDeleteStaticHint', this.#onStaticHintboxClose);
	}

	#isInValuesArea = e => {
		const in_x = this.#dimX <= e.offsetX && e.offsetX <= this.#dimX + this.#dimW;
		return in_x && this.#dimY <= e.offsetY && e.offsetY <= this.#dimY + this.#dimH;
	}

	#mouseClickHandler = e => {
		clearTimeout(this.#hintbox_timeout);
		hintBox.hideHint(this.#svg, true);

		this.#removePointHighlight();

		if (this.#isInValuesArea(e)) {
			this.#showHintAndHighlightPoints(e, true);
		}
	}

	#mouseMoveHandler = e => {
		clearTimeout(this.#hintbox_timeout);
		hintBox.hideHint(this.#svg, false);

		if (!this.#is_static_hintbox_opened) {
			this.#removePointHighlight();
		}

		if (this.#isInValuesArea(e)) {
			this.#setHelperPosition(e);

			if (this.#is_static_hintbox_opened) {
				return;
			}

			this.#hintbox_timeout = setTimeout(() => {
				this.#showHintAndHighlightPoints(e);
			}, 200);
		}
		else {
			this.#hideHelper();
		}
	}

	#showHintAndHighlightPoints(e, is_static = false) {
		const included_paths = this.#findPoints(e.offsetX, e.offsetY);

		if (included_paths.length === 0) {
			return;
		}

		this.#highlightPoints(included_paths);

		included_paths.sort((p1, p2) => p1.x !== p2.x ? p2.x - p1.x : p1.y - p2.y);

		if (is_static) {
			hintBox.showStaticHint(e, this.#svg, null, false, null, this.#getHintboxHtml(included_paths));
		}
		else {
			hintBox.showHint(e, this.#svg, this.#getHintboxHtml(included_paths));
		}
	}

	#mouseLeaveHandler = () => {
		clearTimeout(this.#hintbox_timeout);

		this.#hideHelper();

		if (!this.#is_static_hintbox_opened) {
			this.#removePointHighlight();
		}

		hintBox.hideHint(this.#svg, false);
	}

	#onStaticHintboxOpen = () => {
		this.#is_static_hintbox_opened = true;

		const hintbox = this.#svg.hintBoxItem[0];
		const hintbox_items = hintbox.querySelectorAll('.has-broadcast-data');

		for (const item of hintbox_items) {
			const {itemid, ds} = item.dataset;
			const itemids = [itemid];

			item.addEventListener('click', () => {
				this.#widget.updateItemBroadcast(itemids, ds);
				this.#markSelectedHintboxItems(hintbox);
			});
		}

		this.#markSelectedHintboxItems(hintbox);
	}

	#onStaticHintboxClose = () => {
		this.#is_static_hintbox_opened = false;

		this.#removePointHighlight();
	}

	#markSelectedHintboxItems(hintbox) {
		const {itemid, ds} = this.#widget.getItemBroadcast();

		for (const item of hintbox.querySelectorAll('.has-broadcast-data')) {
			item.classList.toggle('selected', item.dataset.itemid == itemid && item.dataset.ds == ds);
		}
	}

	#setHelperPosition(e) {
		const svg_rect = this.#svg.getBoundingClientRect();

		const vertical_helper = this.#svg.querySelector('.scatter-plot-vertical-helper');

		vertical_helper.setAttribute('x1', e.clientX - svg_rect.left);
		vertical_helper.setAttribute('y1', this.#dimY);
		vertical_helper.setAttribute('x2', e.clientX - svg_rect.left);
		vertical_helper.setAttribute('y2', this.#dimY + this.#dimH);

		const horizontal_helper = this.#svg.querySelector('.scatter-plot-horizontal-helper');

		horizontal_helper.setAttribute('x1', this.#dimX);
		horizontal_helper.setAttribute('y1', e.clientY - svg_rect.top);
		horizontal_helper.setAttribute('x2', this.#dimX + this.#dimW);
		horizontal_helper.setAttribute('y2', e.clientY - svg_rect.top);
	}

	#hideHelper() {
		for (const helper of this.#svg.querySelectorAll('.svg-helper')) {
			helper.setAttribute('x1', -10);
			helper.setAttribute('x2', -10);
			helper.setAttribute('y1', -10);
			helper.setAttribute('y2', -10);
		}
	}

	// Find scatter plot metric paths that touches the given x and y.
	#findPoints(offset_x, offset_y) {
		const paths = [];

		const x_rounded = Math.round(offset_x);
		const y_rounded = Math.round(offset_y);

		const min_x = x_rounded - CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;
		const max_x = x_rounded + CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;

		const min_y = y_rounded - CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;
		const max_y = y_rounded + CScatterPlot.SCATTER_PLOT_MARKER_MIN_SIZE;

		for (let x = min_x; x < max_x; x++) {
			for (let y = min_y; y < max_y; y++) {
				const key = `${x}_${y}`;

				if (this.#paths[key]) {
					paths.push({
						x,
						y,
						points: this.#paths[key]
					});
				}
			}
		}

		return paths;
	}

	#highlightPoints(included_paths) {
		included_paths.forEach(path => {
			const x = path.x;
			const y = path.y;

			for (const point_to_highlight of this.#svg.querySelectorAll(`.point-${x}-${y}`)) {
				const href = point_to_highlight.dataset.id;

				point_to_highlight.setAttribute('href', `#highlight_point_${href}`);
				point_to_highlight.classList.add('highlighted');
			}
		});
	}

	#removePointHighlight() {
		for (const highlighted_point of this.#svg.querySelectorAll(`.highlighted`)) {
			const href = highlighted_point.dataset.id;

			highlighted_point.setAttribute('href', `#point_${href}`);
			highlighted_point.classList.remove('highlighted');
		}
	}

	#getHintboxHtml(included_paths) {
		const hintbox_container = document.createElement('div');
		hintbox_container.classList.add('svg-graph-hintbox');

		for (const paths of included_paths) {
			for (const point of paths.points) {
				const metric = this.#metrics[point.metric];
				const ds_id = metric.data_set;
				const dataset = this.#datasets[ds_id];
				const aggregation_name = dataset.aggregation_name;

				for (const tick of point.time_intervals) {
					const time_from = new CDate(tick * 1000);
					const time_to = new CDate((tick + dataset.aggregate_interval) * 1000);

					const row = document.createElement('div');
					row.classList.add('scatter-plot-hintbox-row');

					for (const key of ['x_items', 'y_items']) {
						const items_data = Object.entries(metric[key]);

						const axis = document.createElement('div');
						axis.classList.add('scatter-plot-hintbox-row-axis');

						const color_span = document.createElement('span');
						color_span.style.color = point.color;
						color_span.classList.add('scatter-plot-hintbox-icon-color', dataset.marker_class);

						axis.append(color_span);

						if (aggregation_name) {
							axis.append(`${aggregation_name}(`);
						}
						else if (items_data.length > 1) {
							axis.append('(');
						}

						let count = 0;
						for (const [itemid, name] of items_data) {
							count++;

							if (count > 1) {
								axis.append(', ');
							}

							const item_span = document.createElement('span');
							item_span.classList.add('has-broadcast-data');
							item_span.dataset.itemid = itemid;
							item_span.dataset.ds = ds_id;
							item_span.innerText = `${metric.hostname}${name}`;

							axis.append(item_span);
						}

						if (aggregation_name || count > 1) {
							axis.append(')');
						}

						axis.append(`: ${key === 'x_items' ? point.vx : point.vy}`);

						row.append(axis);
					}

					row.append(
						`${time_from.format(PHP_ZBX_FULL_DATE_TIME)} - ${time_to.format(PHP_ZBX_FULL_DATE_TIME)}`
					);

					hintbox_container.append(row);
				}
			}
		}

		return hintbox_container;
	}
}
