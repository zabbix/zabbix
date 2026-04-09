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


class CSvgGraph {

	static SCATTER_PLOT_MARKER_MIN_SIZE = 6;

	/**
	 * @type {SVGElement}
	 */
	#svg;

	/**
	 * @type {CWidgetSvgGraph|CWidgetScatterPlot}
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
	 * @type {boolean}
	 */
	#show_problems;

	/**
	 * @type {boolean}
	 */
	#show_simple_triggers;

	/**
	 * @type {number|null}
	 */
	#spp;

	/**
	 * @type {Object}
	 */
	#time_period;

	/**
	 * @type {number}
	 */
	#min_period;

	/**
	 * @type {number}
	 */
	#graph_type;

	/**
	 * @type {boolean}
	 */
	#sbox;

	/**
	 * @type {boolean}
	 */
	#is_boxing;

	/**
	 * @type {boolean}
	 */
	#is_static_hintbox_opened;

	/**
	 * @type {number}
	 */
	#start;

	/**
	 * @type {number}
	 */
	#end;

	/**
	 * @type {boolean}
	 */
	#mouse_click_handled = false;

	/**
	 * @type {number|null}
	 */
	#hintbox_animation_frame_id = null;

	constructor(svg, widget, options) {
		this.#svg = svg;

		this.#widget = widget;

		this.#dimX = options.dims.x;
		this.#dimY = options.dims.y;
		this.#dimW = options.dims.w;
		this.#dimH = options.dims.h;
		this.#show_problems = options.show_problems;
		this.#show_simple_triggers = options.show_simple_triggers;
		this.#spp = options.spp || null;
		this.#time_period = options.time_period;
		this.#min_period = options.min_period;
		this.#graph_type = options.graph_type;
		this.#sbox = options.sbox;
		this.#is_boxing = false;
		this.#is_static_hintbox_opened = false;

		this.#svg.setAttribute('unselectable', 'true');
		this.#svg.style.userSelect = 'none';
	}

	activate() {
		this.#svg.addEventListener('click', this.#mouseClickHandler);
		this.#svg.addEventListener('mousemove', this.#mouseMoveHandler);
		this.#svg.addEventListener('mouseleave', this.#mouseLeaveHandler);
		this.#svg.addEventListener('onShowStaticHint', this.#onStaticHintboxOpen);
		this.#svg.addEventListener('onDeleteStaticHint', this.#onStaticHintboxClose);

		if (this.#sbox) {
			this.#svg.addEventListener('dblclick', this.#zoomOutTime);
			this.#svg.addEventListener('mousedown', this.#startSBoxDrag);
		}
	}

	deactivate() {
		this.#svg.removeEventListener('click', this.#mouseClickHandler);
		this.#svg.removeEventListener('mousemove', this.#mouseMoveHandler);
		this.#svg.removeEventListener('mouseleave', this.#mouseLeaveHandler);
		this.#svg.removeEventListener('onShowStaticHint', this.#onStaticHintboxOpen);
		this.#svg.removeEventListener('onDeleteStaticHint', this.#onStaticHintboxClose);

		if (this.#sbox) {
			this.#destroySBox();

			this.#svg.removeEventListener('dblclick', this.#zoomOutTime);
			this.#svg.removeEventListener('mousedown', this.#startSBoxDrag);
		}
	}

	#cancelHintboxAnimationFrame() {
		if (this.#hintbox_animation_frame_id !== null) {
			cancelAnimationFrame(this.#hintbox_animation_frame_id);

			this.#hintbox_animation_frame_id = null;
		}
	}

	#mouseClickHandler = e => {
		if (this.#mouse_click_handled) {
			this.#mouse_click_handled = false;

			return;
		}

		this.#cancelHintboxAnimationFrame();
		this.#showHintboxAndHighlightPoints(e, true);
	}

	#mouseMoveHandler = e => {
		if (this.#hintbox_animation_frame_id === null) {
			this.#hintbox_animation_frame_id = requestAnimationFrame(() => {
				this.#showHintboxAndHighlightPoints(e, false);

				this.#hintbox_animation_frame_id = null;
			});
		}
	}

	#mouseLeaveHandler = () => {
		this.#cancelHintboxAnimationFrame();
		this.#removeHintbox();
		this.#hideHelper();
	}

	#onStaticHintboxOpen = () => {
		this.#is_static_hintbox_opened = true;

		const hintbox = this.#svg.hintBoxItem[0];
		const hintbox_items = hintbox.querySelectorAll('.has-broadcast-data');

		for (const item of hintbox_items) {
			const {itemid, ds} = item.dataset;
			const itemids = this.#graph_type === GRAPH_TYPE_SCATTER_PLOT ? [itemid] : JSON.parse(item.dataset.itemids);

			item.addEventListener('click', () => {
				this.#widget.updateItemBroadcast(itemids, ds);
				this.#markSelectedHintboxItems(hintbox);
			});

			if (this.#graph_type === GRAPH_TYPE_SVG_GRAPH) {
				item.addEventListener('mouseenter', () => {
					this.#setHighlighting(itemid, ds);
				});

				item.addEventListener('mouseleave', () => {
					this.#resetHighlighting(hintbox);
				});
			}
		}

		this.#markSelectedHintboxItems(hintbox);

		if (this.#graph_type === GRAPH_TYPE_SVG_GRAPH) {
			this.#resetHighlighting(hintbox);
		}
	}

	#onStaticHintboxClose = () => {
		this.#is_static_hintbox_opened = false;

		this.#removeHighlighting();
	}

	#markSelectedHintboxItems(hintbox) {
		const {itemid, ds} = this.#widget.getItemBroadcast();

		for (const item of hintbox.querySelectorAll('.has-broadcast-data')) {
			item.classList.toggle('selected', item.dataset.itemid == itemid && item.dataset.ds == ds);
		}
	}

	#setHighlighting(itemid, ds) {
		this.#removeHighlighting();

		const graph_element = this.#svg.querySelector(`[data-itemid="${itemid}"][data-ds="${ds}"]`);

		if (graph_element) {
			graph_element.classList.add('highlighted');
			this.#svg.classList.add('highlighted');
		}
	}

	#resetHighlighting(hintbox) {
		const selected_item = hintbox.querySelector('.has-broadcast-data.selected');

		if (selected_item) {
			const {itemid, ds} = selected_item.dataset;

			this.#setHighlighting(itemid, ds);
		}
		else {
			this.#removeHighlighting();
		}
	}

	#removeHighlighting() {
		for (const graph_element of this.#svg.querySelectorAll('.highlighted')) {
			graph_element.classList.remove('highlighted');
		}

		this.#svg.classList.remove('highlighted');
	}

	#registerSBoxEvents() {
		document.addEventListener('selectstart', this.#selectStart);
		document.addEventListener('keydown', this.#sBoxKeyDown);
		document.addEventListener('mouseup', this.#endSBoxDrag);

		this.#svg.addEventListener('mousemove', this.#moveSBoxMouse);
	}

	#unregisterSBoxEvents() {
		document.removeEventListener('selectstart', this.#selectStart);
		document.removeEventListener('keydown', this.#sBoxKeyDown);
		document.removeEventListener('mouseup', this.#endSBoxDrag);

		this.#svg.removeEventListener('mousemove', this.#moveSBoxMouse);
	}

	#selectStart = e => {
		e.preventDefault();
	}

	#sBoxKeyDown = e => {
		if (e.key === 'Escape') {
			this.#destroySBox();
		}
	}

	// Method to start selection of some horizontal area in graph.
	#startSBoxDrag = e => {
		e.stopPropagation();

		if (this.#dimX <= e.offsetX && e.offsetX <= this.#dimX + this.#dimW && this.#dimY <= e.offsetY
				&& e.offsetY <= this.#dimY + this.#dimH) {
			this.#registerSBoxEvents();

			this.#start = e.offsetX - this.#dimX;
		}
	}

	// Method to recalculate selected area during mouse move.
	#moveSBoxMouse = e => {
		e.stopPropagation();

		const sbox = this.#svg.querySelector('.svg-graph-selection');
		const selection_text = this.#svg.querySelector('.svg-graph-selection-text');
		const offsetX = e.clientX - this.#svg.getBoundingClientRect().left;

		this.#end = offsetX - this.#dimX;

		// If mouse movement detected (SBox has dragged), destroy opened hintbox and pause widget refresh.
		if (this.#start !== this.#end && !this.#is_boxing) {
			this.#widget._pauseUpdating();

			this.#is_boxing = true;

			this.#cancelHintboxAnimationFrame();
			this.#removeHintbox(true);
			this.#hideHelper();
		}

		if (this.#is_boxing) {
			this.#end = Math.min(offsetX - this.#dimX, this.#dimW);
			this.#end = this.#end > 0 ? this.#end : 0;

			sbox.setAttribute('x', `${Math.min(this.#start, this.#end) + this.#dimX}px`);
			sbox.setAttribute('y', `${this.#dimY}px`);
			sbox.setAttribute('width', `${Math.abs(this.#end - this.#start)}px`);
			sbox.setAttribute('height', `${this.#dimH}px`);

			const seconds = Math.round(Math.abs(this.#end - this.#start) * this.#spp);
			const label_end = seconds < this.#min_period ? ' [min 1' + t('S_MINUTE_SHORT') + ']'  : '';

			selection_text.innerHTML = `${formatTimestamp(seconds, false, true)}${label_end}`;
			selection_text.setAttribute('x', `${Math.min(this.#start, this.#end) + this.#dimX + 5}px`);
			selection_text.setAttribute('y', `${this.#dimY + 15}px`);

			this.#mouse_click_handled = true;
		}
	}

	// Cancel SBox and unset its variables.
	#destroySBox() {
		if (!this.#is_static_hintbox_opened) {
			this.#widget._resumeUpdating();
		}

		const sbox = this.#svg.querySelector('.svg-graph-selection');
		sbox.setAttribute('width', 0);
		sbox.setAttribute('height', 0);

		this.#svg.querySelector('.svg-graph-selection-text').innerHTML = '';

		this.#is_boxing = false;

		this.#unregisterSBoxEvents();
	}

	// Method to end selection of horizontal area in graph.
	#endSBoxDrag = e => {
		e.stopPropagation();

		const set_date = this.#is_boxing;

		this.#destroySBox();

		if (set_date) {
			const offsetX = e.clientX - this.#svg.getBoundingClientRect().left;

			this.#end = Math.min(offsetX - this.#dimX, this.#dimW);

			const seconds = Math.round(Math.abs(this.#end - this.#start) * this.#spp);
			const from_offset = Math.floor(Math.min(this.#start, this.#end)) * this.#spp;
			const to_offset = Math.floor(this.#dimW - Math.max(this.#start, this.#end)) * this.#spp;

			if (seconds > this.#min_period && (from_offset > 0 || to_offset > 0)) {
				this.#widget.updateTimeSelector({
					method: 'rangeoffset',
					from: this.#time_period.from,
					to: this.#time_period.to,
					from_offset: Math.max(0, Math.ceil(from_offset)),
					to_offset: Math.ceil(to_offset)
				});
			}
		}
	}

	#zoomOutTime = () => {
		this.#cancelHintboxAnimationFrame();
		this.#removeHintbox(true);

		this.#widget.updateTimeSelector({
			method: 'zoomout',
			from: this.#time_period.from,
			to: this.#time_period.to,
		});

		return false;
	}

	#setHelperPosition(e) {
		const svg_rect = this.#svg.getBoundingClientRect();

		if (this.#graph_type === GRAPH_TYPE_SVG_GRAPH) {
			const helper = this.#svg.querySelector('.svg-helper');

			helper.setAttribute('x1', e.clientX - svg_rect.left);
			helper.setAttribute('y1', this.#dimY);
			helper.setAttribute('x2', e.clientX - svg_rect.left);
			helper.setAttribute('y2', this.#dimY + this.#dimH);
		}
		else {
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
	}

	#hideHelper() {
		for (const helper of this.#svg.querySelectorAll('.svg-helper')) {
			helper.setAttribute('x1', -10);
			helper.setAttribute('x2', -10);
			helper.setAttribute('y1', -10);
			helper.setAttribute('y2', -10);
		}

		if (this.#graph_type === GRAPH_TYPE_SCATTER_PLOT) {
			const highlighter_points = this.#svg.querySelectorAll('g.js-svg-highlight-group');

			for (const highlighter_point of highlighter_points) {
				highlighter_point.setAttribute('transform', 'translate(-10, -10)');
			}
		}
		else {
			for (const point of this.#svg.querySelectorAll('.svg-point-highlight')) {
				point.setAttribute('cx', -10);
				point.setAttribute('cy', -10);
			}
		}
	}

	#showHintboxAndHighlightPoints(e, is_static) {
		let html = null;

		this.#removeHintbox(is_static);

		if (this.#is_boxing) {
			this.#hideHelper();

			return;
		}

		// Check if mouse in the horizontal area in which hintbox must be shown.
		const in_x = this.#dimX <= e.offsetX && e.offsetX <= this.#dimX + this.#dimW;
		const in_problem_area = in_x && this.#dimY + this.#dimH <= e.offsetY
			&& e.offsetY <= this.#dimY + this.#dimH + 15;
		const in_values_area = in_x && this.#dimY <= e.offsetY && e.offsetY <= this.#dimY + this.#dimH;

		if (this.#graph_type === GRAPH_TYPE_SCATTER_PLOT) {
			if (in_values_area) {
				this.#setHelperPosition(e);

				const included_points = this.#findScatterPlotPoints(e.offsetX, e.offsetY);

				for (const highlighter_point of this.#svg.querySelectorAll('g.js-svg-highlight-group')) {
					highlighter_point.setAttribute('transform', 'translate(-10, -10)');
				}

				if (included_points.length > 0) {
					included_points.forEach(point => {
						const point_highlight = point.g.querySelector('g.js-svg-highlight-group');

						point_highlight.setAttribute('transform', point.transform);
					});

					included_points.sort((p1, p2) => {
						if (p1.x !== p2.x) {
							return p2.x - p1.x;
						}

						return p1.y - p2.y;
					});

					html = this.#getScatterPlotValuesHintboxHtml(included_points);
				}
			}
			else {
				this.#hideHelper();
			}
		}
		// Show problems when mouse is in the 15px high area under the graph canvas.
		else if (this.#show_problems && in_problem_area) {
			this.#hideHelper();

			const problems = this.#findProblems(e.offsetX);

			if (problems.length > 0) {
				html = this.#getProblemHintboxHtml(problems);
			}
		}
		// Show graph values or simple triggers if mouse is over the graph canvas.
		else if (in_values_area) {
			const triggers = this.#show_simple_triggers ? this.#findTriggers(e.offsetY) : [];

			if (triggers.length > 0) {
				this.#hideHelper();

				const trigger_areas = triggers.filter(
					t => !(t.begin_position > e.offsetX || e.offsetX > t.end_position)
				);

				if (trigger_areas.length > 0) {
					html = this.#getSimpleTriggerHintboxHtml(triggers);
				}
			}
			else {
				this.#setHelperPosition(e);

				let included_points = [];
				const points = this.#findValues(e.offsetX);

				let xy_point = false;
				let points_total = points.length;
				let show_hint = false;

				/**
				 * Decide if one specific value or list of all matching Xs should be highlighted and either to
				 * show or hide hintbox.
				 */
				points.forEach(point => {
					if (!show_hint && point.v !== null) {
						show_hint = true;
					}

					const tolerance = this.#getDataPointTolerance(point.g);

					if (!xy_point && point.v !== null
							&& (+point.x + tolerance) > e.offsetX && e.offsetX > (+point.x - tolerance)
							&& (+point.y + tolerance) > e.offsetY && e.offsetY > (+point.y - tolerance)) {
						xy_point = point;
						points_total = 1;
					}
				});

				points.forEach(point => {
					const point_highlight = point.g.querySelector('.svg-point-highlight');
					const include_point = point.v !== null && (xy_point === false || xy_point === point);

					if (include_point) {
						point_highlight.setAttribute('cx', point.x);
						point_highlight.setAttribute('cy', point.y);

						if (point.p > 0) {
							point_highlight.setAttribute('cx', parseInt(point.x) + parseInt(point.p));
						}

						included_points.push(point);
					}
					else {
						point_highlight.setAttribute('cx', -10);
						point_highlight.setAttribute('cy', -10);
					}
				});

				if (show_hint) {
					included_points.sort((p1, p2) => p1.y - p2.y);

					html = this.#getSvgGraphValuesHintboxHtml(included_points, e.offsetX);
				}
			}
		}
		else {
			this.#hideHelper();
		}

		if (html !== null) {
			if (is_static) {
				hintBox.showStaticHint(e, this.#svg, null, false, null, html.outerHTML);
			}
			else {
				hintBox.showHint(e, this.#svg, html.outerHTML);
			}
		}
	}

	#removeHintbox(is_static = false) {
		hintBox.hideHint(this.#svg, is_static);
	}

	#findProblems(x) {
		const problems = [];
		const nodes = this.#svg.querySelectorAll('[data-info]');

		for (let i = 0, l = nodes.length; l > i; i++) {
			const problem_start = +nodes[i].getAttribute('x');
			const problem_width = +nodes[i].getAttribute('width');

			if (x > problem_start && problem_start + problem_width > x) {
				problems.push(JSON.parse(nodes[i].getAttribute('data-info')));
			}
		}

		return problems;
	}

	// Read SVG nodes and find closest past value to the given x in each data set.
	#findValues(x) {
		const data_sets = [];
		const nodes = this.#svg.querySelectorAll('[data-set]');

		for (let i = 0, l = nodes.length; l > i; i++) {
			let px = -10;
			let py = -10;
			let pv = null;
			let pp = 0;
			let ps = 0;

			let points = [];
			let point;

			// Find matching X points.
			switch (nodes[i].getAttribute('data-set')) {
				case 'points':
					const test_x = Math.min(x, +nodes[i].lastChild.getAttribute('cx'));
					const circle_nodes = nodes[i].querySelectorAll('circle');

					for (let c = 0, cl = circle_nodes.length; cl > c; c++) {
						if (test_x >= parseInt(circle_nodes[c].getAttribute('cx'))) {
							points.push(circle_nodes[c]);
						}
					}

					point = points.slice(-1)[0];

					if (typeof point !== 'undefined') {
						px = point.getAttribute('cx');
						py = point.getAttribute('cy');
						pv = point.getAttribute('label');
					}
					break;

				case 'bar':
					const polygons_nodes = nodes[i].querySelectorAll('polygon');

					for (let c = 0, cl = polygons_nodes.length; cl > c; c++) {
						const coord = polygons_nodes[c].getAttribute('points').split(' ').map(function (val) {
							return val.split(',');
						});

						if (polygons_nodes[c].getAttribute('data-px') === coord[0][0]) {
							if (x >= parseInt(coord[0][0])) {
								points.push(polygons_nodes[c]);
							}
						}
						else {
							if (x >= parseInt(polygons_nodes[c].getAttribute('data-px'))) {
								points.push(polygons_nodes[c]);
							}
						}
					}

					px = 0;
					py = 0;

					point = points.slice(-1)[0];

					if (typeof point !== 'undefined') {
						const coord = point.getAttribute('points').split(' ').map(function (val) {
							return val.split(',');
						});

						px = coord[0][0];
						py = coord[1][1];
						pv = point.getAttribute('label');
						pp = (coord[2][0] - coord[0][0]) / 2;
						ps = point.getAttribute('data-px');
					}
					break;

				case 'staircase':
				case 'line':
					const data_set = nodes[i].getAttribute('data-set');
					const data_nodes = nodes[i].childNodes;

					let direction_string = '';
					let labels = [];
					let element_label;
					let cx;
					let cy;

					for (let index = 0, len = data_nodes.length; index < len; index++) {
						element_label = data_nodes[index].getAttribute('label');

						if (element_label) {
							labels.push(element_label);

							if (data_nodes[index].tagName.toLowerCase() === 'circle') {
								cx = data_nodes[index].getAttribute('cx');
								cy = data_nodes[index].getAttribute('cy');
								direction_string += ' _' + cx + ',' + cy;
							}
							else {
								direction_string += ' ' + data_nodes[index].getAttribute('d');
							}
						}
					}

					labels = labels.join(',').split(',');

					const direction = ED // Edge transforms 'd' attribute.
						? direction_string.substr(1).replace(/([ML])\s(\d+)\s(\d+)/g, '$1$2\,$3').split(' ')
						: direction_string.substr(1).split(' ');

					let index = direction.length;
					let point_label;

					while (index) {
						index--;
						point = direction[index].substr(1).split(',');
						point_label = labels[data_set === 'line' ? index : Math.ceil(index / 2)];

						if (x >= parseInt(point[0]) && point_label !== '') {
							px = point[0];
							py = point[1];
							pv = point_label;
							break;
						}
					}
					break;
			}

			data_sets.push({g: nodes[i], x: px, y: py, v: pv, p: pp, s: ps});
		}

		return data_sets;
	}

	// Find scatter plot metric points that touches the given x and y.
	#findScatterPlotPoints(x, y) {
		const nodes = this.#svg.querySelectorAll('[data-set]');
		const points = [];

		for (let i = 0; i < nodes.length; i++) {
			const point = nodes[i].querySelectorAll('.metric-point');

			for (let c = 0; c < point.length; c++) {
				const ctm = point[c].getCTM();
				const cx = ctm.e;
				const cy = ctm.f;

				if (Math.abs(cx - x) <= CSvgGraph.SCATTER_PLOT_MARKER_MIN_SIZE
						&& Math.abs(cy - y) <= CSvgGraph.SCATTER_PLOT_MARKER_MIN_SIZE) {
					if (point[c].getAttribute('value_x') !== null || point[c].getAttribute('value_y') !== null) {
						points.push({
							g: nodes[i],
							x: cx,
							y: cy,
							transform: point[c].getAttribute('transform'),
							vx: point[c].getAttribute('value_x'),
							vy: point[c].getAttribute('value_y'),
							color: point[c].getAttribute('color'),
							time_from: point[c].getAttribute('time_from'),
							time_to: point[c].getAttribute('time_to'),
							marker_class: point[c].getAttribute('marker_class'),
							p: 0,
							s: 0
						});
					}
				}
			}
		}

		return points;
	}

	#findTriggers(y) {
		const triggers = [];

		for (const node of this.#svg.querySelectorAll('.svg-graph-simple-trigger line')) {
			const trigger_y = parseInt(node.getAttribute('y1'));

			if (y < trigger_y + 10 && y > trigger_y - 10) {
				triggers.push({
					begin_position: parseInt(node.getAttribute('x1')),
					end_position: parseInt(node.getAttribute('x2')),
					color: node.parentElement.getAttribute('severity-color'),
					constant: node.parentElement.getAttribute('constant'),
					trigger: node.parentElement.getAttribute('description'),
					elem: node.parentElement
				});
			}
		}

		return triggers;
	}

	/**
	 * Get tolerance for given data set. Tolerance is used to find which elements are highlighted by mouse. Script takes
	 * actual data point and adds N pixels to all sides. Then looks if mouse is in calculated area. N is calculated by
	 * this function. Tolerance is used to find exactly matched point only.
	 */
	#getDataPointTolerance(ds) {
		const data_tag = ds.querySelector(':not(.svg-point-highlight)');

		if (data_tag.tagName.toLowerCase() === 'circle') {
			return +ds.childNodes[1].getAttribute('r');
		}
		else {
			return +window.getComputedStyle(data_tag)['strokeWidth'];
		}
	}

	#getProblemHintboxHtml(problems) {
		const tbody = document.createElement('tbody');

		for (const problem of problems) {
			const tr = document.createElement('tr');

			const clock_link = document.createElement('a');
			clock_link.setAttribute('href', problem.url);
			clock_link.innerText = problem.clock;

			const clock_td = document.createElement('td');
			clock_td.append(clock_link);

			const recovery_clock_td = document.createElement('td');

			if (problem.r_eventid) {
				const recover_clock_link = document.createElement('a');
				recover_clock_link.setAttribute('href', problem.url);
				recover_clock_link.innerText = problem.r_clock;
				recovery_clock_td.append(recover_clock_link);
			}
			else {
				recovery_clock_td.innerText = problem.r_clock;
			}

			const color_span = document.createElement('span');
			color_span.classList.add(problem.status_color);
			color_span.innerText = problem.status;

			const color_td = document.createElement('td');
			color_td.append(color_span);

			const severity_td = document.createElement('td');
			severity_td.classList.add(problem.severity);
			severity_td.innerText = problem.name;

			tr.append(clock_td, recovery_clock_td, color_td, severity_td);

			tbody.append(tr);
		}

		const table = document.createElement('table');
		table.classList.add('list-table', 'compact-view');
		table.append(tbody);

		const hintbox_body = document.createElement('div');
		hintbox_body.classList.add('svg-graph-hintbox');
		hintbox_body.append(table);

		return hintbox_body;
	}

	#getSimpleTriggerHintboxHtml(triggers_areas) {
		const ul = document.createElement('ul');

		for (const trigger_area of triggers_areas) {
			const li = document.createElement('li');

			const color_span = document.createElement('span');
			color_span.style.backgroundColor = trigger_area.color;
			color_span.classList.add('svg-graph-hintbox-trigger-color');

			li.append(color_span, `${trigger_area.trigger} [${trigger_area.constant}]`);

			ul.append(li);
		}

		const hintbox_body = document.createElement('div');
		hintbox_body.classList.add('svg-graph-hintbox');
		hintbox_body.append(ul);

		return hintbox_body;
	}

	#getScatterPlotValuesHintboxHtml(included_points) {
		const hintbox_container = document.createElement('div');
		hintbox_container.classList.add('svg-graph-hintbox');

		for (const point of included_points) {
			const time_from = new CDate(point.time_from * 1000);
			const time_to = new CDate(point.time_to * 1000);

			const aggregation_name = point.g.dataset.aggregationName;
			const ds = point.g.dataset.ds;

			const row = document.createElement('div');
			row.classList.add('scatter-plot-hintbox-row');

			for (const key of ['xItems', 'yItems']) {
				const items_data = Object.entries(JSON.parse(point.g.dataset[key]));

				const axis = document.createElement('div');
				axis.classList.add('scatter-plot-hintbox-row-axis');

				const color_span = document.createElement('span');
				color_span.style.color = point.color;
				color_span.classList.add('scatter-plot-hintbox-icon-color', point.marker_class);

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
					item_span.dataset.ds = ds;
					item_span.innerText = name;

					axis.append(item_span);
				}

				if (aggregation_name || count > 1) {
					axis.append(')');
				}

				axis.append(`: ${key === 'xItems' ? point.vx : point.vy}`);

				row.append(axis);
			}

			row.append(`${time_from.format(PHP_ZBX_FULL_DATE_TIME)} - ${time_to.format(PHP_ZBX_FULL_DATE_TIME)}`);

			hintbox_container.append(row);
		}

		return hintbox_container;
	}

	#getSvgGraphValuesHintboxHtml(included_points, offsetX) {
		const hintbox_container = document.createElement('div');
		hintbox_container.classList.add('svg-graph-hintbox');

		const ul = document.createElement('ul');

		// Calculate time at mouse position.
		const time = new CDate(
			(this.#time_period.from_ts + (offsetX - this.#dimX) * this.#spp) * 1000
		);

		const header = document.createElement('div');
		header.classList.add('header');
		header.append(time.format(PHP_ZBX_FULL_DATE_TIME));

		for (const point of included_points) {
			const li = document.createElement('li');
			li.classList.add('has-broadcast-data');
			li.dataset.ds = point.g.dataset.ds;
			li.dataset.itemid = point.g.dataset.itemid;
			li.dataset.itemids = point.g.dataset.itemids;

			const color_span = document.createElement('span');
			color_span.style.backgroundColor = point.g.dataset.color;
			color_span.classList.add('svg-graph-hintbox-item-color');

			li.append(color_span, `${point.g.dataset.metric}: ${point.v}`);

			ul.append(li);
		}

		hintbox_container.append(header, ul);

		return hintbox_container;
	}
}
