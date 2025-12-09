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
 * JQuery class that initializes interactivity for SVG graph.
 *
 * Supported options:
 *  - SBox - time range selector;
 *  - show_problems - show problems in hintbox when mouse is moved over the problem zone;
 *  - min_period - min period in seconds that must be s-boxed to change the data in dashboard timeselector.
 */
(function ($) {
	"use strict";

	// Makes SBox selection cancelable pressing Esc.
	function sBoxKeyboardInteraction(e) {
		if (e.keyCode == 27) {
			destroySBox(e, e.data.graph);
		}
	}

	// Disable text selection in document when move mouse pressed cursor.
	function disableSelect(e) {
		e.preventDefault();
	}

	// Cancel SBox and unset its variables.
	function destroySBox(e, graph = e.data.graph) {
		const data = graph.data('options');
		const is_static_hintbox_opened = graph.data('is_static_hintbox_opened');

		if (data) {
			if (!is_static_hintbox_opened) {
				graph.data('widget')._resumeUpdating();
			}

			jQuery('.svg-graph-selection', graph).attr({'width': 0, 'height': 0});
			jQuery('.svg-graph-selection-text', graph).text('');
			graph.data('options').boxing = false;
		}

		dropDocumentListeners(e, graph);
	}

	/**
	 * Function removes SBox related $(document) event listeners:
	 * - if no other widget have active SBox;
	 * - to avoid another call of destroySBox on 'mouseup' (in case if user has pressed ESC).
	 */
	function dropDocumentListeners(e, graph) {
		let widgets_boxing = 0; // Number of widgets with active SBox.

		for (const dashboard_page of ZABBIX.Dashboard.getDashboardPages()) {
			dashboard_page.getWidgets().forEach((widget) => {
				if (widget.getType() === 'svggraph' && widget._svg !== null) {
					const options = jQuery(widget._svg).data('options');
					if (options !== undefined && options.boxing) {
						widgets_boxing++;
					}
				}
			});
		}

		if (widgets_boxing == 0 || (e && 'keyCode' in e && e.keyCode == 27)) {
			jQuery(document)
				.off('selectstart', disableSelect)
				.off('keydown', sBoxKeyboardInteraction)
				.off('mouseup', destroySBox)
				.off('mouseup', endSBoxDrag);

			graph.off('mousemove', moveSBoxMouse);
		}
	}

	// Destroy hintbox, unset its variables and event listeners.
	function destroyHintbox(graph) {
		delete graph[0].dataset.hintboxContents;
	}

	// Hide vertical helper line and highlighted data points.
	function hideHelper(graph) {
		for (const helper of graph[0].querySelectorAll('.svg-helper')) {
			helper.setAttribute('x1', -10);
			helper.setAttribute('x2', -10);
			helper.setAttribute('y1', -10);
			helper.setAttribute('y2', -10);
		}

		if (graph.data('options').graph_type === GRAPH_TYPE_SCATTER_PLOT) {
			const highlighter_points = graph[0].querySelectorAll('g.js-svg-highlight-group');

			for (const highlighter_point of highlighter_points) {
				highlighter_point.setAttribute('transform', 'translate(-10, -10)');
			}
		}
		else {
			for (const point of graph[0].querySelectorAll('.svg-point-highlight')) {
				point.setAttribute('cx', -10);
				point.setAttribute('cy', -10);
			}
		}
	}

	// Method to start selection of some horizontal area in graph.
	function startSBoxDrag(e) {
		e.stopPropagation();

		const graph = e.data.graph;
		const offsetX = e.clientX - graph.offset().left;
		const data = graph.data('options');

		if (data.dimX <= offsetX && offsetX <= data.dimX + data.dimW && data.dimY <= e.offsetY
				&& e.offsetY <= data.dimY + data.dimH) {
			jQuery(document)
				.on('selectstart', disableSelect)
				.on('keydown', {graph: graph}, sBoxKeyboardInteraction)
				.on('mouseup', {graph: graph}, endSBoxDrag);

			graph.on('mousemove', {graph: graph}, moveSBoxMouse);

			data.start = offsetX - data.dimX;
		}
	}

	// Method to recalculate selected area during mouse move.
	function moveSBoxMouse(e) {
		e.stopPropagation();

		const graph = e.data.graph;
		const data = graph.data('options');
		const sbox = graph[0].querySelector('.svg-graph-selection');
		const selection_text = graph[0].querySelector('.svg-graph-selection-text');
		const offsetX = e.clientX - graph.offset().left;

		data.end = offsetX - data.dimX;

		// If mouse movement detected (SBox has dragged), destroy opened hintbox and pause widget refresh.
		if (data.start != data.end && !data.boxing) {
			graph.data('widget')._pauseUpdating();
			data.boxing = true;
			destroyHintbox(graph);
			hintBox.hideHint(graph[0], true);
			hideHelper(graph);
		}

		if (data.boxing) {
			data.end = Math.min(offsetX - data.dimX, data.dimW);
			data.end = data.end > 0 ? data.end : 0;

			sbox.setAttribute('x', `${Math.min(data.start, data.end) + data.dimX}px`);
			sbox.setAttribute('y', `${data.dimY}px`);
			sbox.setAttribute('width', `${Math.abs(data.end - data.start)}px`);
			sbox.setAttribute('height', `${data.dimH}px`);

			const seconds = Math.round(Math.abs(data.end - data.start) * data.spp);
			const label_end = seconds < data.minPeriod ? ' [min 1' + t('S_MINUTE_SHORT') + ']'  : '';

			selection_text.innerHTML = `${formatTimestamp(seconds, false, true)}${label_end}`;
			selection_text.setAttribute('x', `${Math.min(data.start, data.end) + data.dimX + 5}px`);
			selection_text.setAttribute('y', `${data.dimY + 15}px`);
		}
	}

	// Method to end selection of horizontal area in graph.
	function endSBoxDrag(e) {
		e.stopPropagation();

		const graph = e.data.graph;
		const data = graph.data('options');
		const offsetX = e.clientX - graph.offset().left
		const set_date = data && data.boxing;

		destroySBox(e, graph);

		if (set_date) {
			data.end = Math.min(offsetX - data.dimX, data.dimW);

			const seconds = Math.round(Math.abs(data.end - data.start) * data.spp);
			const from_offset = Math.floor(Math.min(data.start, data.end)) * data.spp;
			const to_offset = Math.floor(data.dimW - Math.max(data.start, data.end)) * data.spp;

			if (seconds > data.minPeriod && (from_offset > 0 || to_offset > 0)) {
				const widget = graph.data('widget');

				updateTimeSelector(widget, {
					method: 'rangeoffset',
					from: data.timePeriod.from,
					to: data.timePeriod.to,
					from_offset: Math.max(0, Math.ceil(from_offset)),
					to_offset: Math.ceil(to_offset)
				})
					.then((time_period) => {
						if (time_period === null) {
							return;
						}

						widget._startUpdating();
						widget.feedback({time_period});
						widget.broadcast({
							[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
						});
					});
			}
		}
	}

	function updateTimeSelector(widget, data) {
		widget._schedulePreloader();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'timeselector.calc');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((time_period) => {
				if ('error' in time_period) {
					throw {error: time_period.error};
				}

				if ('has_fields_errors' in time_period) {
					throw new Error();
				}

				return time_period;
			})
			.catch((exception) => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Unexpected server error.');
				}

				widget._updateMessages(messages, title);

				return null;
			})
			.finally(() => {
				widget._hidePreloader();
			});
	}

	// Read SVG nodes and find closest past value to the given x in each data set.
	function findValues(graph, x) {
		const data_sets = [];
		const nodes = graph.querySelectorAll('[data-set]');

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

						if (polygons_nodes[c].getAttribute('data-px') == coord[0][0]) {
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

	// Find metric points that touches the given x and y.
	function findScatterPlotPoints(graph, x, y) {
		const nodes = graph.querySelectorAll('[data-set]');
		const points = [];

		for (let i = 0; i < nodes.length; i++) {
			const point = nodes[i].querySelectorAll('.metric-point');

			for (let c = 0; c < point.length; c++) {
				const ctm = point[c].getCTM();
				const cx = ctm.e;
				const cy = ctm.f;

				if (Math.abs(cx - x) <= 6 && Math.abs(cy - y) <= 6) {
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

	// Find what problems matches in time to the given x.
	function findProblems(graph, x) {
		const problems = [];
		const nodes = graph.querySelectorAll('[data-info]');

		for (let i = 0, l = nodes.length; l > i; i++) {
			const problem_start = +nodes[i].getAttribute('x');
			const problem_width = +nodes[i].getAttribute('width');

			if (x > problem_start && problem_start + problem_width > x) {
				problems.push(JSON.parse(nodes[i].getAttribute('data-info')));
			}
		}

		return problems;
	}

	// Set position of vertical helper line.
	function setHelperPosition(e, graph) {
		const data = graph.data('options');

		if (data.graph_type === GRAPH_TYPE_SVG_GRAPH) {
			const helper = graph[0].querySelector('.svg-helper');

			helper.setAttribute('x1', e.clientX - graph.offset().left);
			helper.setAttribute('y1', data.dimY);
			helper.setAttribute('x2', e.clientX - graph.offset().left);
			helper.setAttribute('y2', data.dimY + data.dimH);
		}
		else {
			const vertical_helper = graph[0].querySelector('.scatter-plot-vertical-helper');

			vertical_helper.setAttribute('x1', e.clientX - graph.offset().left);
			vertical_helper.setAttribute('y1', data.dimY);
			vertical_helper.setAttribute('x2', e.clientX - graph.offset().left);
			vertical_helper.setAttribute('y2', data.dimY + data.dimH);

			const horizontal_helper = graph[0].querySelector('.scatter-plot-horizontal-helper');

			horizontal_helper.setAttribute('x1', data.dimX);
			horizontal_helper.setAttribute('y1', e.clientY - graph.offset().top);
			horizontal_helper.setAttribute('x2', data.dimX + data.dimW);
			horizontal_helper.setAttribute('y2', e.clientY - graph.offset().top);
		}
	}

	/**
	 * Get tolerance for given data set. Tolerance is used to find which elements are highlighted by mouse. Script takes
	 * actual data point and adds N pixels to all sides. Then looks if mouse is in calculated area. N is calculated by
	 * this function. Tolerance is used to find exactly matched point only.
	 */
	function getDataPointTolerance(ds) {
		const data_tag = ds.querySelector(':not(.svg-point-highlight)');

		if (data_tag.tagName.toLowerCase() === 'circle') {
			return +ds.childNodes[1].getAttribute('r');
		}
		else {
			return +window.getComputedStyle(data_tag)['strokeWidth'];
		}
	}

	function getProblemHintboxHtml(problems) {
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

		const hintbox_body = document.createElement('div')
		hintbox_body.classList.add('svg-graph-hintbox');
		hintbox_body.append(table);

		return hintbox_body;
	}

	function getSimpleTriggerHintboxHtml(triggers_areas) {
		const ul = document.createElement('ul');

		for (const trigger_area of triggers_areas) {
			const li = document.createElement('li');
			li.innerText = `${trigger_area.trigger} [${trigger_area.constant}]`;

			const span = document.createElement('span');
			span.style.backgroundColor = trigger_area.color;
			span.classList.add('svg-graph-hintbox-trigger-color');

			li.append(span);

			ul.append(li);
		}

		const hintbox_body = document.createElement('div');
		hintbox_body.classList.add('svg-graph-hintbox');
		hintbox_body.append(ul);

		return hintbox_body;
	}

	function getValuesHintboxHtml(included_points, offsetX, data) {
		let rows_added = 0;

		const hintbox_container = document.createElement('div');
		hintbox_container.classList.add('svg-graph-hintbox');

		const html = document.createElement('ul');

		if (data.graph_type === GRAPH_TYPE_SCATTER_PLOT) {
			for (const point of included_points) {
				const time_from = new CDate(point.time_from * 1000);
				const time_to = new CDate(point.time_to * 1000);

				const aggregation_name = point.g.dataset.aggregationName;
				const ds = point.g.dataset.ds;

				for (const key of ['xItems', 'yItems']) {
					const items_data = Object.entries(JSON.parse(point.g.dataset[key]));

					const li = document.createElement('li');
					li.style.marginTop = key === 'xItems' && rows_added > 0 ? '10px' : null;
					li.append(`${aggregation_name}(`);

					let count = 0;
					for (const [itemid, name] of items_data) {
						count++;

						const item_span = document.createElement('span');
						item_span.classList.add('has-broadcast-data');
						item_span.dataset.itemid = itemid;
						item_span.dataset.ds = ds;
						item_span.innerText = name.toString();

						li.append(item_span);

						if (count !== items_data.length && count > 0) {
							li.append(', ');
						}
					}

					const color_span = document.createElement('span');
					color_span.style.color = point.color;
					color_span.classList.add('svg-graph-hintbox-icon-color', point.marker_class);

					li.append(`): ${key === 'xItems' ? point.vx : point.vy}`, color_span);

					html.append(li);

					rows_added++;
				}

				const row = document.createElement('div');
				row.append(`${time_from.format(PHP_ZBX_FULL_DATE_TIME)} - ${time_to.format(PHP_ZBX_FULL_DATE_TIME)}`);

				html.append(row);
			}
		}
		else {
			for (const point of included_points) {
				const li = document.createElement('li');
				li.classList.add('has-broadcast-data');
				li.dataset.ds = point.g.dataset.ds;
				li.dataset.itemid = point.g.dataset.itemid;

				const color_span = document.createElement('span');
				color_span.style.backgroundColor = point.g.dataset.color;
				color_span.classList.add('svg-graph-hintbox-item-color');

				li.append(`${point.g.dataset.metric}: ${point.v}`, color_span);

				html.append(li);
			}

			// Calculate time at mouse position.
			const time = new CDate((data.timePeriod.from_ts + (offsetX - data.dimX) * data.spp) * 1000);

			const header = document.createElement('div');
			header.classList.add('header');
			header.append(time.format(PHP_ZBX_FULL_DATE_TIME));

			hintbox_container.append(header);
		}

		hintbox_container.append(html);

		return hintbox_container;
	}

	// Show problem or value hintbox.
	function showHintbox(e, graph = e.data.graph) {
		const data = graph.data('options');
		const offsetX = e.clientX - graph.offset().left;
		let html = null;
		let in_x = false;
		let in_values_area = false;
		let in_problem_area = false;

		if (data.boxing === true) {
			hideHelper(graph);
			return;
		}

		// Check if mouse in the horizontal area in which hintbox must be shown.
		in_x = data.dimX <= offsetX && offsetX <= data.dimX + data.dimW;
		in_problem_area = in_x && data.dimY + data.dimH <= e.offsetY && e.offsetY <= data.dimY + data.dimH + 15;
		in_values_area = in_x && data.dimY <= e.offsetY && e.offsetY <= data.dimY + data.dimH;

		// Show problems when mouse is in the 15px high area under the graph canvas.
		if (data.showProblems && in_problem_area) {
			hideHelper(graph);

			const problems = findProblems(graph[0], e.offsetX);

			if (problems.length > 0) {
				html = getProblemHintboxHtml(problems);
			}
		}
		// Show graph values or simple triggers if mouse is over the graph canvas.
		else if (in_values_area) {
			const triggers = data.showSimpleTriggers ? findTriggers(graph[0], e.offsetY) : [];

			if (triggers.length > 0) {
				hideHelper(graph);

				const trigger_areas = triggers.filter(
					t => !(t.begin_position > e.offsetX || e.offsetX > t.end_position)
				);

				if (trigger_areas.length > 0) {
					html = getSimpleTriggerHintboxHtml(triggers);
				}
			}
			else {
				setHelperPosition(e, graph);

				let included_points = [];
				let show_hint = false;

				if (data.graph_type === GRAPH_TYPE_SCATTER_PLOT) {
					const offsetY = e.clientY - graph.offset().top;

					included_points = findScatterPlotPoints(graph[0], offsetX, offsetY);

					if (included_points.length > 0) {
						show_hint = true;
					}

					for (const highlighter_point of graph[0].querySelectorAll('g.js-svg-highlight-group')) {
						highlighter_point.setAttribute('transform', 'translate(-10, -10)');
					}

					included_points.forEach(point => {
						const point_highlight = point.g.querySelector('g.js-svg-highlight-group');

						point_highlight.setAttribute('transform', point.transform);
					});
				}
				else {
					const points = findValues(graph[0], offsetX);
					let xy_point = false;
					let points_total = points.length;

					/**
					 * Decide if one specific value or list of all matching Xs should be highlighted and either to
					 * show or hide hintbox.
					 */
					points.forEach(point => {
						if (!show_hint && point.v !== null) {
							show_hint = true;
						}

						const tolerance = getDataPointTolerance(point.g);

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
				}

				if (show_hint) {
					included_points.sort((p1, p2) => {
						if (data.graph_type === GRAPH_TYPE_SVG_GRAPH) {
							return p1.y - p2.y;
						}
						else {
							if (p1.x !== p2.x) {
								return p2.x - p1.x;
							}

							return p1.y - p2.y;
						}
					});

					html = getValuesHintboxHtml(included_points, offsetX, data);
				}
			}
		}
		else {
			hideHelper(graph);
		}

		if (html !== null) {
			graph[0].dataset.hintboxContents = html.outerHTML;
		}
		else if (in_values_area || in_problem_area) {
			destroyHintbox(graph);
		}
	}

	function findTriggers(graph, y) {
		const triggers = [];

		for (const node of graph.querySelectorAll('.svg-graph-simple-trigger line')) {
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

	const methods = {
		init: function(widget) {
			this.each(function() {
				jQuery(widget._svg)
					.data('options', {
						dimX: widget._svg_options.dims.x,
						dimY: widget._svg_options.dims.y,
						dimW: widget._svg_options.dims.w,
						dimH: widget._svg_options.dims.h,
						showProblems: widget._svg_options.show_problems,
						showSimpleTriggers: widget._svg_options.show_simple_triggers,
						spp: widget._svg_options.spp || null,
						timePeriod: widget._svg_options.time_period,
						minPeriod: widget._svg_options.min_period,
						boxing: false,
						graph_type: widget._svg_options.graph_type
					})
					.data('widget', widget)
					.data('is_static_hintbox_opened', false)
					.attr('unselectable', 'on')
					.css('user-select', 'none');

				if (widget._svg_options.sbox) {
					dropDocumentListeners(null, jQuery(widget._svg));
				}
			});
		},
		activate: function () {
			const widget = jQuery(this).data('widget');
			const graph = jQuery(widget._svg);
			const data = graph.data('options');

			graph[0].dataset.hintbox = '1';
			graph[0].dataset.hintboxStatic = '1';
			graph[0].dataset.hintboxDelay = '0';
			graph[0].dataset.hintboxStaticReopenOnClick = '1';

			graph
				.on('mousemove', (e) => {
					showHintbox(e, graph);
				})
				.on('mouseleave', function() {
					destroyHintbox(graph);
					hideHelper(graph);
				})
				.on('selectstart', false)
				.on('onShowStaticHint', e => onStaticHintboxOpen(e, graph))
				.on('onDeleteStaticHint', e => onStaticHintboxClose(e, graph));

			if (widget._svg_options.sbox) {
				graph
					.on('dblclick', function() {
						hintBox.hideHint(graph[0], true);

						const widget = graph.data('widget');

						updateTimeSelector(widget, {
							method: 'zoomout',
							from: data.timePeriod.from,
							to: data.timePeriod.to,
						})
							.then((time_period) => {
								if (time_period === null) {
									return;
								}

								widget._startUpdating();
								widget.feedback({time_period});
								widget.broadcast({
									[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
								});
							});

						return false;
					})
					.on('mousedown', {graph}, startSBoxDrag);
			}
		},
		deactivate: function (e) {
			const widget = jQuery(this).data('widget');
			const graph = jQuery(widget._svg);

			delete graph[0].dataset.hintbox;
			delete graph[0].dataset.hintboxStatic;
			delete graph[0].dataset.hintboxDelay;
			delete graph[0].dataset.hintboxStaticReopenOnClick;

			destroySBox(e, graph);
			graph.off('mousemove mouseleave dblclick mousedown selectstart onShowStaticHint onDeleteStaticHint');
		},
	};

	function onStaticHintboxOpen(e, graph) {
		graph.data('is_static_hintbox_opened', true);

		const hintbox = graph[0].hintBoxItem[0];
		const widget = graph.data('widget');
		const data = graph.data('options');
		const hintbox_items = hintbox.querySelectorAll('.has-broadcast-data');

		for (const item of hintbox_items) {
			const {itemid, ds} = item.dataset;

			item.addEventListener('click', () => {
				widget.updateItemBroadcast(itemid, ds);
				markSelectedHintboxItems(hintbox, widget);
			});

			if (data.graph_type === GRAPH_TYPE_SVG_GRAPH) {
				item.addEventListener('mouseenter', () => {
					setHighlighting(itemid, ds, graph);
				});

				item.addEventListener('mouseleave', () => {
					resetHighlighting(hintbox, graph);
				});
			}
		}

		markSelectedHintboxItems(hintbox, widget);

		if (data.graph_type === GRAPH_TYPE_SVG_GRAPH) {
			resetHighlighting(hintbox, graph);
		}
	}

	function onStaticHintboxClose(e, graph) {
		graph.data('is_static_hintbox_opened', false);

		removeHighlighting(graph);
	}

	function markSelectedHintboxItems(hintbox, widget) {
		const {itemid, ds} = widget.getItemBroadcasting();

		for (const item of hintbox.querySelectorAll('.has-broadcast-data')) {
			item.classList.toggle('selected', item.dataset.itemid == itemid && item.dataset.ds == ds);
		}
	}

	function setHighlighting(itemid, ds, graph) {
		removeHighlighting(graph);

		const graph_element = graph[0].querySelector(`[data-itemid="${itemid}"][data-ds="${ds}"]`);

		if (graph_element) {
			graph_element.classList.add('highlighted');
			graph[0].classList.add('highlighted');
		}
	}

	function resetHighlighting(hintbox, graph) {
		const selected_item = hintbox.querySelector('.has-broadcast-data.selected');

		if (selected_item) {
			const {itemid, ds} = selected_item.dataset;

			setHighlighting(itemid, ds, graph);
		}
		else {
			removeHighlighting(graph);
		}
	}

	function removeHighlighting(graph) {
		for (const graph_element of graph[0].querySelectorAll('.highlighted')) {
			graph_element.classList.remove('highlighted');
		}

		graph[0].classList.remove('highlighted');
	}

	jQuery.fn.svggraph = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
})(jQuery);
