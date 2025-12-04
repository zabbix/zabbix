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
		graph.find('.svg-helper').attr({'x1': -10, 'x2': -10});

		if (graph.data('options').hintbox_type === GRAPH_HINTBOX_TYPE_SCATTER_PLOT) {
			const highlighter_points = graph[0].querySelectorAll('g.js-svg-highlight-group');

			for (const highlighter_point of highlighter_points) {
				highlighter_point.setAttribute('transform', 'translate(-10, -10)');
			}
		}
		else {
			graph.find('.svg-point-highlight').attr({'cx': -10, 'cy': -10});
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

		var graph = e.data.graph,
			data = graph.data('options'),
			$sbox = jQuery('.svg-graph-selection', graph),
			$stxt = jQuery('.svg-graph-selection-text', graph),
			offsetX = e.clientX - graph.offset().left;

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
			data.end = (data.end > 0) ? data.end : 0;

			$sbox.attr({
				'x': (Math.min(data.start, data.end) + data.dimX) + 'px',
				'y': data.dimY + 'px',
				'width': Math.abs(data.end - data.start) + 'px',
				'height': data.dimH
			});

			var seconds = Math.round(Math.abs(data.end - data.start) * data.spp),
				label = formatTimestamp(seconds, false, true)
					+ (seconds < data.minPeriod ? ' [min 1' + t('S_MINUTE_SHORT') + ']'  : '');

			$stxt
				.text(label)
				.attr({
					'x': (Math.min(data.start, data.end) + data.dimX + 5) + 'px',
					'y': (data.dimY + 15) + 'px'
				});
		}
	}

	// Method to end selection of horizontal area in graph.
	function endSBoxDrag(e) {
		e.stopPropagation();

		var graph = e.data.graph,
			data = graph.data('options'),
			offsetX = e.clientX - graph.offset().left,
			set_date = data && data.boxing;

		destroySBox(e, graph);

		if (set_date) {
			data.end = Math.min(offsetX - data.dimX, data.dimW);

			var seconds = Math.round(Math.abs(data.end - data.start) * data.spp),
				from_offset = Math.floor(Math.min(data.start, data.end)) * data.spp,
				to_offset = Math.floor(data.dimW - Math.max(data.start, data.end)) * data.spp;

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
		var data_sets = [],
			nodes = graph.querySelectorAll('[data-set]');

		for (var i = 0, l = nodes.length; l > i; i++) {
			var px = -10,
				py = -10,
				pv = null,
				pp = 0,
				ps = 0;

			// Find matching X points.
			switch (nodes[i].getAttribute('data-set')) {
				case 'points':
					var test_x = Math.min(x, +nodes[i].lastChild.getAttribute('cx')),
						circle_nodes = nodes[i].querySelectorAll('circle'),
						points = [];

					for (var c = 0, cl = circle_nodes.length; cl > c; c++) {
						if (test_x >= parseInt(circle_nodes[c].getAttribute('cx'))) {
							points.push(circle_nodes[c]);
						}
					}

					var point = points.slice(-1)[0];
					if (typeof point !== 'undefined') {
						px = point.getAttribute('cx');
						py = point.getAttribute('cy');
						pv = point.getAttribute('label');
					}
					break;

				case 'bar':
					var polygons_nodes = nodes[i].querySelectorAll('polygon');
					var points = [];
					var pp = 0;

					for (var c = 0, cl = polygons_nodes.length; cl > c; c++) {
						var coord = polygons_nodes[c].getAttribute('points').split(' ').map(function (val) {
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

					var point = points.slice(-1)[0];
					if (typeof point !== 'undefined') {
						var coord = point.getAttribute('points').split(' ').map(function (val) {
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
					var direction_string = '',
						labels = [],
						data_set = nodes[i].getAttribute('data-set'),
						data_nodes = nodes[i].childNodes,
						elmnt_label,
						cx,
						cy;

					for (var index = 0, len = data_nodes.length; index < len; index++) {
						elmnt_label = data_nodes[index].getAttribute('label');

						if (elmnt_label) {
							labels.push(elmnt_label);

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

					var direction = ED // Edge transforms 'd' attribute.
							? direction_string.substr(1).replace(/([ML])\s(\d+)\s(\d+)/g, '$1$2\,$3').split(' ')
							: direction_string.substr(1).split(' '),
						index = direction.length,
						point,
						point_label;

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
		var problems = [],
			problem_start,
			problem_width,
			nodes = graph.querySelectorAll('[data-info]');

		for (var i = 0, l = nodes.length; l > i; i++) {
			problem_start = +nodes[i].getAttribute('x');
			problem_width = +nodes[i].getAttribute('width');

			if (x > problem_start && problem_start + problem_width > x) {
				problems.push(JSON.parse(nodes[i].getAttribute('data-info')));
			}
		}

		return problems;
	}

	// Set position of vertical helper line.
	function setHelperPosition(e, graph) {
		var data = graph.data('options');
		graph.find('.svg-helper').attr({
			'x1': e.clientX - graph.offset().left,
			'y1': data.dimY,
			'x2': e.clientX - graph.offset().left,
			'y2': data.dimY + data.dimH
		});
	}

	/**
	 * Get tolerance for given data set. Tolerance is used to find which elements are hovered by mouse. Script takes
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

	function getProblemHintboxHtml(e, graph) {
		const problems = findProblems(graph[0], e.offsetX);
		let problems_total = problems.length;

		if (problems_total === 0) {
			return null;
		}

		const tbody = jQuery('<tbody>');

		problems.forEach(function(val, i) {
			tbody.append(
				jQuery('<tr>')
					.append(jQuery('<td>').append(jQuery('<a>', {'href': val.url}).text(val.clock)))
					.append(jQuery('<td>').append(val.r_eventid
						? jQuery('<a>', {'href': val.url}).text(val.r_clock)
						: val.r_clock)
					)
					.append(jQuery('<td>').append(
						jQuery('<span>', { 'class': val.status_color }).text(val.status))
					)
					.append(jQuery('<td>', {'class': val.severity}).text(val.name))
			);
		});

		return jQuery('<div>')
			.addClass('svg-graph-hintbox')
			.append(
				jQuery('<table>')
					.addClass('list-table compact-view')
					.append(tbody)
			);
	}

	function getSimpleTriggerHintboxHtml(e, triggers) {
		if (triggers.length > 0) {
			const hint_body = jQuery('<ul></ul>');

			const trigger_areas = triggers.filter(t => !(t.begin_position > e.offsetX || e.offsetX > t.end_position));

			if (!trigger_areas.length) {
				return null;
			}

			for (const trigger of trigger_areas) {
				hint_body.append(
					jQuery('<li>')
						.text(trigger.trigger + ' [' + trigger.constant + ']')
						.append(
							jQuery('<span>')
								.css('background-color', trigger.color)
								.addClass('svg-graph-hintbox-trigger-color')
						)
				)
			}

			return jQuery('<div>')
				.addClass('svg-graph-hintbox')
				.append(hint_body);
		}
	}

	function getValuesHintboxHtml(included_points, offsetX, data) {
		let html = jQuery('<ul>');
		let rows_added = 0;

		for (const point of included_points) {
			if (data.hintbox_type === GRAPH_HINTBOX_TYPE_SCATTER_PLOT) {
				const time_from = new CDate(point.time_from * 1000);
				const time_to = new CDate(point.time_to * 1000);

				jQuery('<li>')
					.css('margin-top', rows_added > 0 ? '10px' : null)
					.text(point.g.getAttribute('data-metric-x') + ': ' + point.vx)
					.append(
						jQuery('<span>')
							.css('color', point.color)
							.addClass('svg-graph-hintbox-icon-color')
							.addClass(point.marker_class)
					)
					.appendTo(html);

				jQuery('<li>')
					.text(point.g.getAttribute('data-metric-y') + ': ' + point.vy)
					.append(
						jQuery('<span>')
							.css('color', point.color)
							.addClass('svg-graph-hintbox-icon-color')
							.addClass(point.marker_class)
					)
					.appendTo(html);

				jQuery('<div>')
					.text(time_from.format(PHP_ZBX_FULL_DATE_TIME) + ' - ' + time_to.format(PHP_ZBX_FULL_DATE_TIME))
					.appendTo(html);
			}
			else {
				jQuery('<li>')
					.text(point.g.getAttribute('data-metric') + ': ' + point.v)
					.append(
						jQuery('<span>')
							.css('background-color', point.g.getAttribute('data-color'))
							.addClass('svg-graph-hintbox-item-color')
					)
					.appendTo(html);
			}
		}

		const hintbox_container = jQuery('<div>').addClass('svg-graph-hintbox');

		if (data.hintbox_type === GRAPH_HINTBOX_TYPE_SVG_GRAPH) {
			// Calculate time at mouse position.
			const time = new CDate((data.timePeriod.from_ts + (offsetX - data.dimX) * data.spp) * 1000);

			hintbox_container.append(
				jQuery('<div>')
					.addClass('header')
					.html(time.format(PHP_ZBX_FULL_DATE_TIME))
			);
		}

		hintbox_container.append(html);
		html = hintbox_container;

		return html;
	}

	// Show problem or value hintbox.
	function showHintbox(e, graph = e.data.graph) {
		const data = graph.data('options');
		const offsetX = e.clientX - graph.offset().left;
		let html = null;
		let in_x = false;
		let in_values_area = false;
		let in_problem_area = false;

		const hintbox_type = data.hintbox_type;

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

			html = getProblemHintboxHtml(e, graph);
		}
		// Show graph values or simple triggers if mouse is over the graph canvas.
		else if (in_values_area) {
			const triggers = data.showSimpleTriggers ? findTriggers(graph[0], e.offsetY) : [];

			if (triggers.length > 0) {
				hideHelper(graph);

				html = getSimpleTriggerHintboxHtml(e, triggers);
			}
			else {
				setHelperPosition(e, graph);

				let included_points = [];
				let show_hint = false;

				if (hintbox_type === GRAPH_HINTBOX_TYPE_SCATTER_PLOT) {
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
						if (hintbox_type === GRAPH_HINTBOX_TYPE_SVG_GRAPH) {
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
			graph[0].dataset.hintboxContents = html[0].outerHTML;
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
						hintMaxRows: widget._svg_options.hint_max_rows,
						spp: widget._svg_options.spp || null,
						timePeriod: widget._svg_options.time_period,
						minPeriod: widget._svg_options.min_period,
						boxing: false,
						hintbox_type: widget._svg_options.hintbox_type
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
				.on('onShowStaticHint', (e) => onStaticHintboxOpen(e, graph))
				.on('onDeleteStaticHint', (e) => onStaticHintboxClose(e, graph));

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
	}

	function onStaticHintboxClose(e, graph) {
		graph.data('is_static_hintbox_opened', false);
	}

	jQuery.fn.svggraph = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
})(jQuery);
