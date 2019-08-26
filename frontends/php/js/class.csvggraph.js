/*
 ** Zabbix
 ** Copyright (C) 2001-2019 Zabbix SIA
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


/**
 * JQuery class that initializes interactivity for SVG graph.
 *
 * Supported options:
 *  - SBox - time range selector;
 *  - show_problems - show problems in hintbox when mouse is moved over the problem zone;
 *  - min_period - min period in seconds that must be s-boxed to change the data in dashboard timeselector.
 */
jQuery(function ($) {
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
	function destroySBox(e, graph) {
		var graph = graph || e.data.graph,
			data = graph.data('options');

		dropDocumentListeners(e, graph);

		if (data) {
			if (!data.isHintBoxFrozen) {
				$('.dashbrd-grid-container')
					.dashboardGrid('unpauseWidgetRefresh', graph.data('widget')['uniqueid']);
			}

			$('.svg-graph-selection', graph).attr({'width': 0, 'height': 0});
			$('.svg-graph-selection-text', graph).text('');
			graph.data('options').boxing = false;
		}
	}

	/**
	 * Function removes SBox related $(document) event listeners:
	 * - if no other widget have active SBox;
	 * - to avoid another call of destroySBox on 'mouseup' (in case if user has pressed ESC).
	 */
	function dropDocumentListeners(e, graph) {
		var widgets_boxing = 0; // Number of widgets with active SBox.
		$('.dashbrd-grid-container').data('dashboardGrid')['widgets'].forEach(function(w) {
			if (w !== graph.data('widget') && w['type'] === 'svggraph') {
				var svg_graph = $('svg', w['content_body']);
				if (svg_graph.length && svg_graph.data('options')['boxing']) {
					widgets_boxing++;
				}
			}
		});

		if (widgets_boxing == 0 || (e && 'keyCode' in e && e.keyCode == 27)) {
			$(document)
				.off('selectstart', disableSelect)
				.off('keydown', sBoxKeyboardInteraction)
				.off('mousemove', moveSBoxMouse)
				.off('mouseup', destroySBox)
				.off('mouseup', endSBoxDrag);
		}
	}

	// Destroy hintbox, unset its variables and event listeners.
	function destroyHintbox(graph) {
		var data = graph.data('options'),
			hbox = graph.data('hintbox') || null;

		if (hbox !== null && data.isHintBoxFrozen === false) {
			removeFromOverlaysStack(graph.hintboxid);
			graph.off('mouseup', makeHintboxStatic);
			graph.removeData('hintbox');
			hbox.remove();
		}
	}

	// Hide vertical helper line and highlighted data points.
	function hideHelper(graph) {
		graph.find('.svg-helper').attr({'x1': -10, 'x2': -10});
		graph.find('.svg-point-highlight').attr({'cx': -10, 'cy': -10});
	}

	// Create a new hintbox and stick it to certain position where user has clicked.
	function makeHintboxStatic(e, graph) {
		var graph = graph || e.data.graph,
			data = graph.data('options'),
			hbox = graph.data('hintbox'),
			content = hbox ? hbox.find('> div') : null;

		// Destroy old hintbox to make new one with close button.
		destroyHintbox(graph);

		if (content) {
			// Should be put inside hintBoxItem to use functionality of hintBox.
			graph.hintBoxItem = hintBox.createBox(e, graph, content, '', true, false, graph.parent());
			data.isHintBoxFrozen = true;
			$('.dashbrd-grid-container').dashboardGrid('pauseWidgetRefresh', graph.data('widget')['uniqueid']);
			overlayDialogueOnLoad(true, graph.hintBoxItem);

			graph.hintBoxItem.on('onDeleteHint.hintBox', function(e) {
				$('.dashbrd-grid-container')
					.dashboardGrid('unpauseWidgetRefresh', graph.data('widget')['uniqueid']);
				data.isHintBoxFrozen = false; // Unfreeze because only onfrozen hintboxes can be removed.
				graph.off('mouseup', hintboxSilentMode);
				destroyHintbox(graph);
			});

			repositionHintBox(e, graph);
			graph
				.off('mouseup', hintboxSilentMode)
				.on('mouseup', {graph: graph}, hintboxSilentMode);
			graph.data('hintbox', graph.hintBoxItem);
		}
	}

	/**
	 * Silent mode means that hintbox is waiting for click to be repositionated. Once user clicks on graph, existing
	 * hintbox will be repositionated with a new values in the place where user clicked on.
	 */
	function hintboxSilentMode(e) {
		var graph = e.data.graph,
			data = graph.data('options');

		if (data.isHintBoxFrozen) {
			data.isHintBoxFrozen = false;
			showHintbox(e, graph);
			makeHintboxStatic(e, graph);
		}
	}

	// Method to start selection of some horizontal area in graph.
	function startSBoxDrag(e) {
		e.stopPropagation();

		var graph = e.data.graph,
			offsetX = e.clientX - graph.offset().left,
			data = graph.data('options');

		if (data.dimX <= offsetX && offsetX <= data.dimX + data.dimW && data.dimY <= e.offsetY
				&& e.offsetY <= data.dimY + data.dimH) {
			$(document)
				.on('selectstart', disableSelect)
				.on('keydown', {graph: graph}, sBoxKeyboardInteraction)
				.on('mousemove', {graph: graph}, moveSBoxMouse)
				.on('mouseup', {graph: graph}, endSBoxDrag);

			data.start = offsetX - data.dimX;
		}
	}

	// Method to recalculate selected area during mouse move.
	function moveSBoxMouse(e) {
		e.stopPropagation();

		var graph = e.data.graph,
			data = graph.data('options'),
			sbox = $('.svg-graph-selection', graph),
			stxt = $('.svg-graph-selection-text', graph),
			offsetX = e.clientX - graph.offset().left;

		data.end = offsetX - data.dimX;

		// If mouse movement detected (SBox has dragged), destroy opened hintbox and pause widget refresh.
		if (data.start != data.end && !data.boxing) {
			$('.dashbrd-grid-container').dashboardGrid('pauseWidgetRefresh', graph.data('widget')['uniqueid']);
			data.isHintBoxFrozen = false;
			data.boxing = true;
			destroyHintbox(graph);
			hideHelper(graph);
		}

		if (data.boxing) {
			data.end = Math.min(offsetX - data.dimX, data.dimW);
			data.end = (data.end > 0) ? data.end : 0;

			sbox.attr({
				'x': (Math.min(data.start, data.end) + data.dimX) + 'px',
				'y': data.dimY + 'px',
				'width': Math.abs(data.end - data.start) + 'px',
				'height': data.dimH
			});

			var seconds = Math.round(Math.abs(data.end - data.start) * data.spp),
				label = formatTimestamp(seconds, false, true)
					+ (seconds < data.minPeriod ? ' [min 1' + t('S_MINUTE_SHORT') + ']'  : '');

			stxt
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
				$.publish('timeselector.rangeoffset', {
					from_offset: Math.max(0, Math.ceil(from_offset)),
					to_offset: Math.ceil(to_offset)
				});
			}
		}
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
						label = [],
						data_set = nodes[i].getAttribute('data-set'),
						data_nodes = nodes[i].childNodes,
						elmnt_label,
						cx,
						cy;

					for (var index = 0, len = data_nodes.length; index < len; index++) {
						elmnt_label = data_nodes[index].getAttribute('label');
						if (elmnt_label) {
							label.push(elmnt_label);

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

					label = label.join(',').split(',');

					var direction = (IE || ED) // IE11 & Edge transforms 'd' attribute.
							? direction_string.substr(1).replace(/([ML])\s(\d+)\s(\d+)/g, '$1$2\,$3').split(' ')
							: direction_string.substr(1).split(' '),
						index = direction.length,
						point;

					while (index) {
						index--;
						point = direction[index].substr(1).split(',');
						if (x >= parseInt(point[0])) {
							px = point[0];
							py = point[1];
							pv = label[data_set === 'line' ? index : Math.ceil(index / 2)];
							break;
						}
					}
					break;
			}

			data_sets.push({g: nodes[i], x: px, y: py, v: pv, p: pp, s: ps});
		}

		return data_sets;
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
	 * this function. Tolerance is used to find exacly matched point only.
	 */
	function getDataPointTolerance(ds) {
		var data_tag = ds.querySelector(':not(.svg-point-highlight)');

		if (data_tag.tagName.toLowerCase() === 'circle') {
			return +ds.childNodes[1].getAttribute('r');
		}
		else {
			return +window.getComputedStyle(data_tag)['strokeWidth'];
		}
	}

	// Position hintbox near current mouse position.
	function repositionHintBox(e, graph) {
		var hbox = $(graph.hintBoxItem),
			offset = graph.offset(),
			page_bottom = jQuery(window.top).scrollTop() + jQuery(window.top).height(),
			l = (document.body.clientWidth >= e.clientX + hbox.outerWidth() + 20)
				? e.clientX - offset.left + 20
				: e.clientX - hbox.outerWidth() - offset.left - 15,
			t = e.pageY - offset.top - graph.parent().scrollTop(),
			t = page_bottom >= t + offset.top + hbox.outerHeight() + 60 ? t + 60 : t - hbox.height();

		hbox.css({'left': l, 'top': t});
	}

	// Show problem or value hintbox.
	function showHintbox(e, graph) {
		var graph = graph || e.data.graph,
			data = graph.data('options'),
			hbox = graph.data('hintbox') || null,
			offsetX = e.clientX - graph.offset().left,
			html = null,
			in_x = false,
			in_values_area = false,
			in_problem_area = false;

		if (data.boxing === false) {
			// Check if mouse in the horizontal area in which hintbox must be shown.
			in_x = (data.dimX <= offsetX && offsetX <= data.dimX + data.dimW);
			in_problem_area = in_x && (data.dimY + data.dimH <= e.offsetY && e.offsetY <= data.dimY + data.dimH + 15);
			in_values_area = in_x && (data.dimY <= e.offsetY && e.offsetY <= data.dimY + data.dimH);

			// Show problems when mouse is in the 15px high area under the graph canvas.
			if (data.showProblems && data.isHintBoxFrozen === false && in_problem_area) {
				hideHelper(graph);

				var problems = findProblems(graph[0], e.offsetX),
					problems_total = problems.length;
				if (problems_total > 0) {
					var tbody = $('<tbody>');

					problems.slice(0, data.hintMaxRows).forEach(function(val, i) {
						tbody.append(
							$('<tr>')
								.append($('<td>').append($('<a>', {'href': val.url}).text(val.clock)))
								.append($('<td>').append(val.r_eventid
									? $('<a>', {'href': val.url}).text(val.r_clock)
									: val.r_clock)
								)
								.append($('<td>').append($('<span>', {'class': val.status_color}).text(val.status)))
								.append($('<td>', {'class': val.severity}).text(val.name))
						);
					});

					html = $('<div></div>')
							.addClass('svg-graph-hintbox')
							.append(
								$('<table></table>')
									.addClass('list-table compact-view')
									.append(tbody)
							)
							.append(problems_total > data.hintMaxRows
								? makeHintBoxFooter(data.hintMaxRows, problems_total)
								: null
							);
				}
			}
			// Show graph values if mouse is over the graph canvas.
			else if (in_values_area) {
				// Set position of mouse following helper line.
				setHelperPosition(e, graph);

				// Find values.
				var points = findValues(graph[0], offsetX),
					points_total = points.length,
					show_hint = false,
					xy_point = false,
					tolerance;

				/**
				 * Decide if one specific value or list of all matching Xs should be highlighted and either to show or
				 * hide hintbox.
				 */
				if (data.isHintBoxFrozen === false) {
					points.forEach(function(point) {
						if (!show_hint && point.v !== null) {
							show_hint = true;
						}

						tolerance = getDataPointTolerance(point.g);
						if (!xy_point && point.v !== null
								&& (+point.x + tolerance) > e.offsetX && e.offsetX > (+point.x - tolerance)
								&& (+point.y + tolerance) > e.offsetY && e.offsetY > (+point.y - tolerance)) {
							xy_point = point;
							points_total = 1;
							return;
						}
					});
				}

				// Make html for hintbox.
				if (show_hint) {
					html = $('<ul></ul>');
				}
				var rows_added = 0;
				points.forEach(function(point) {
					var point_highlight = point.g.querySelectorAll('.svg-point-highlight')[0];

					if (point.v !== null && (xy_point === false || xy_point === point)) {
						point_highlight.setAttribute('cx', point.x);
						point_highlight.setAttribute('cy', point.y);

						if (point.p > 0) {
							point_highlight.setAttribute('cx', parseInt(point.x) + parseInt(point.p));
						}

						if (show_hint && data.hintMaxRows > rows_added) {
							$('<li></li>')
								.text(point.g.getAttribute('data-metric') + ': ' + point.v)
								.append(
									$('<span></span>')
										.css('background-color', point.g.getAttribute('data-color'))
										.addClass('svg-graph-hintbox-item-color')
								)
								.appendTo(html);
							rows_added++;
						}
					}
					else {
						point_highlight.setAttribute('cx', -10);
						point_highlight.setAttribute('cy', -10);
					}
				});

				if (show_hint) {
					// Calculate time at mouse position.
					var time = parseInt(data.timeFrom + ((offsetX - data.dimX) * data.spp));

					html = $('<div></div>')
							.addClass('svg-graph-hintbox')
							.append(
								$('<div></div>')
									.addClass('header')
									.html(time2str(time))
							)
							.append(html)
							.append(points_total > data.hintMaxRows
								? makeHintBoxFooter(data.hintMaxRows, points_total)
								: null
							);
				}
			}
			else {
				hideHelper(graph);
			}

			if (html !== null) {
				if (hbox === null) {
					hbox = hintBox.createBox(e, graph, html, '', false, false, graph.parent());
					graph
						.off('mouseup', makeHintboxStatic)
						.on('mouseup', {graph: graph}, makeHintboxStatic);
					graph.data('hintbox', hbox);
				}
				else {
					hbox.find('> div').replaceWith(html);
				}

				graph.hintBoxItem = hbox;
				repositionHintBox(e, graph);
			}
		}
		else {
			hideHelper(graph);
		}

		if (html === null && (in_values_area || in_problem_area)) {
			destroyHintbox(graph);
		}
	}

	// Function creates hintbox footer.
	function makeHintBoxFooter(num_displayed, num_total) {
		return $('<div></div>')
			.addClass('table-paging')
			.append(
				$('<div></div>')
					.addClass('paging-btn-container')
					.append(
						$('<div></div>')
							.text(sprintf(t('S_DISPLAYING_FOUND'), num_displayed, num_total))
							.addClass('table-stats')
					)
		);
	}

	var methods = {
		init: function(options, widget) {
			options = $.extend({}, {
				sbox: false,
				show_problems: true,
				hint_max_rows: 20,
				min_period: 60
			}, options);

			this.each(function() {
				var graph = $(this),
					data = {
						dimX: options.dims.x,
						dimY: options.dims.y,
						dimW: options.dims.w,
						dimH: options.dims.h,
						showProblems: options.show_problems,
						hintMaxRows: options.hint_max_rows,
						isHintBoxFrozen: false,
						spp: options.spp || null,
						timeFrom: options.time_from,
						minPeriod: options.min_period,
						boxing: false
					};

				graph
					.data('options', data)
					.data('widget', widget)
					.attr('unselectable', 'on')
					.css('user-select', 'none')
					.on('mousemove', {graph: graph}, showHintbox)
					.on('mouseleave', function(e) {
						var graph = $(this);
						destroyHintbox(graph);
						hideHelper(graph);
					})
					.on('selectstart', false);

				if (options.sbox) {
					dropDocumentListeners(null, graph);

					graph
						.on('dblclick', function() {
							hintBox.hideHint(graph, true);
							$.publish('timeselector.zoomout');
							return false;
						})
						.on('mousedown', {graph: graph}, startSBoxDrag);
				}
			});
		},
		disableSBox: function(e) {
			var graph = $(this);

			destroySBox(e, graph);
			graph.off('mousedown', startSBoxDrag);
		}
	};

	$.fn.svggraph = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
});
