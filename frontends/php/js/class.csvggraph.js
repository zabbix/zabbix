/*
 ** Zabbix
 ** Copyright (C) 2001-2018 Zabbix SIA
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
 * JQuery class that initializes interactivity for SVG graph. Currently following features are supported:
 *  - SBox - time range selector;
 *  - problems - show problems in hintbox when mouse is moved over the problem zone;
 *  - values - show mouse overed value in hintbox or all values at particular moment (X axis) if no specific value is
 *    toucked.
 */
jQuery(function ($) {
	"use strict"

	var min_period = 60; // Minimal period in seconds to zoom in.

	function SBoxKeyboardInteraction(e) {
		if (e.keyCode == 27) {
			destroySBox(e);
		}
	}

	function destroySBox(e, graph) {
		var graph = graph || e.data.graph;
		$('.svg-graph-selection', graph).attr({'width': 0, 'height': 0});
		$('.svg-graph-selection-text', graph).text('');
		graph.data('graph').boxing = false;
	}

	function destroyHintbox(graph) {
		var hbox = graph.data('hintbox');
		graph.removeData('hintbox');
		$(hbox).remove();
		hideHelper(graph)
	}

	function startSBoxDrag(e) {
		var graph = $(this),
			data = graph.data('graph'),
			sbox = $('.svg-graph-selection', graph),
			hbox = graph.data('hintbox');

		if (data.dimX <= e.offsetX && e.offsetX <= data.dimX + data.dimW && data.dimY <= e.offsetY
				&& e.offsetY <= data.dimY + data.dimH) {
			$(document).on('keydown', {graph: graph}, SBoxKeyboardInteraction);
			$(document).on('mouseup', {graph: graph}, destroySBox);

			e.stopPropagation();
			data.start = e.offsetX - data.dimX;
			data.boxing = true;

			if (typeof hbox !== 'undefined') {
				graph.removeData('hintbox');
				hbox.remove();
			}

			sbox.attr({
				'x': (data.start + data.dimX) + 'px',
				'y': data.dimY + 'px',
				'height': data.dimH
			});
		}
	}

	function moveSBoxMouse(e) {
		var data = $(this).data('graph'),
			sbox = $('.svg-graph-selection', $(this)),
			stxt = $('.svg-graph-selection-text', $(this));

		if (data.boxing && (e.offsetX - data.dimX) > 0 && (data.dimW + data.dimX) >= e.offsetX) {
			e.stopPropagation();

			data.end = e.offsetX - data.dimX;
			sbox.attr({
				'x': (Math.min(data.start, data.end) + data.dimX) + 'px',
				'width': Math.abs(data.end - data.start) + 'px'
			});

			var seconds = Math.round(Math.abs(data.end - data.start) * data.spp),
				label = formatTimestamp(seconds, false, true)
					+ (seconds < min_period ? ' [min 1' + locale['S_MINUTE_SHORT'] + ']'  : '');

			stxt.text(label)
				.attr({
					'x': (Math.min(data.start, data.end) + data.dimX + 5) + 'px',
					'y': (data.dimY + 15) + 'px'
				});
		}
	}

	function endSBoxDrag(e) {
		var data = $(this).data('graph');
		e.stopPropagation();

		if (data.boxing) {
			data.end = e.offsetX - data.dimX;

			$(document).off('keydown', SBoxKeyboardInteraction);
			$(document).off('mouseup',destroySBox);
			destroySBox(null, $(this))

			var seconds = Math.round(Math.abs(data.end - data.start) * data.spp),
				from_offset = Math.floor(Math.min(data.start, data.end)) * data.spp,
				to_offset = Math.floor(data.dimW - Math.max(data.start, data.end)) * data.spp;

			if (seconds > min_period && (from_offset > 0 || to_offset > 0)) {
				$.publish('timeselector.rangeoffset', {
					from_offset: Math.ceil(from_offset),
					to_offset: Math.ceil(to_offset)
				});
			}
		}
	}

	function findValues(graph, x, y) {
		/**
		 * Find mouse-overed data points.
		 *
		 * Writen in native javascript to make it faster. Each data set can have only one value at specific time. That's
		 * why we need only maximum 1 value from each [data-set] group.
		 *
		 * Use point size and line width as a tolerance to make matching area wider.
		 */
		var matching_x = [],
			matching_xy = [],
			xy_only = false,
			values = [],
			tolerance,
			x_min,
			x_max,
			y_min,
			y_max;

		graph[0].querySelectorAll('[data-set]').forEach(function(ds) {
			tolerance = +ds.getAttribute('data-tolerance');

			if (ds.getAttribute('data-set') === 'line') {
				x_min = x - tolerance / 2;
				x_max = x + tolerance / 2;
				y_min = y - tolerance / 2;
				y_max = y + tolerance / 2;

				[...ds.querySelectorAll('path')].forEach(function(c) {
					c.getAttribute('d').match(/\d+,\d+/g).forEach(function(p) {
						p = p.split(',');

						if (+p[0] > x_min && +p[0] < x_max) {
							if (+p[1] > y_min && +p[1] < y_max) {
								matching_xy.push({
									val: +[1],
									metric: ds.getAttribute('data-metric'),
									color: ds.getAttribute('data-color')
								});

								xy_only = true; // Stop collect X points, because we already know that XY point is found.
								return;
							}

							if (!xy_only) {
								matching_x.push({
									val: +p[1],
									metric: ds.getAttribute('data-metric'),
									color: ds.getAttribute('data-color')
								});
							}
						}
					});
				});
			}
			else if (ds.getAttribute('data-set') === 'staircase') {
				x_min = x - tolerance / 2;
				x_max = x + tolerance / 2;
				y_min = y - tolerance / 2;
				y_max = y + tolerance / 2;

				[...ds.querySelectorAll('path')].forEach(function(c) {
					var value_catched = false;
					c.getAttribute('d').match(/\d+,\d+/g).forEach(function(p) {
						// This is because in staircase graph each value is represented by 2 points.
						if (value_catched) {
							value_catched = false;
							return;
						}
						p = p.split(',');

						if (+p[0] > x_min && +p[0] < x_max) {
							value_catched = true;
							if (+p[1] > y_min && +p[1] < y_max) {
								matching_xy.push({
									val: +[1],
									metric: ds.getAttribute('data-metric'),
									color: ds.getAttribute('data-color')
								});

								xy_only = true; // Stop collect X points, because we already know that XY point is found.
								return;
							}

							if (!xy_only) {
								matching_x.push({
									val: +p[1],
									metric: ds.getAttribute('data-metric'),
									color: ds.getAttribute('data-color')
								});
							}
						}
					});
				});
			}
			else if (ds.getAttribute('data-set') === 'points') {
				x_min = x - tolerance;
				x_max = x + tolerance;
				y_min = y - tolerance;
				y_max = y + tolerance;

				[...ds.querySelectorAll('circle')].forEach(function(c) {
					var cx = +c.getAttribute('cx'),
						cy = +c.getAttribute('cy');

					if (cx > x_min && cx < x_max) {
						if (cy > y_min && cy < y_max) {
							matching_xy.push({
								val: c.getAttribute('label'),
								metric: ds.getAttribute('data-metric'),
								color: ds.getAttribute('data-color')
							});

							xy_only = true; // Stop collect X points, because we already know that XY point is found.
							return;
						}

						if (!xy_only) {
							matching_x.push({
								val: cy,
								metric: ds.getAttribute('data-metric'),
								color: ds.getAttribute('data-color')
							});
						}
					}
				});
			}
		});

		return matching_xy.length ? matching_xy : matching_x;
	}

	function findProblems(graph, x) {
		var problems = [],
			problem_start,
			problem_width;

		graph[0].querySelectorAll('[data-info]').forEach(function(problem) {
			problem_start = +problem.getAttribute('x');
			problem_width = +problem.getAttribute('width');

			if (x > problem_start && problem_start + problem_width > x) {
				problems.push(JSON.parse(problem.getAttribute('data-info')));
			}
		});

		return problems;
	}

	function hideHelper(graph) {
		graph.find('.svg-value-box').attr('x', -10);
	}

	function showHintbox(e) {
		var graph = $(this),
			data = graph.data('graph'),
			hbox = graph.data('hintbox'),
			line = graph.find('.svg-value-box'),
			html = null,
			inx = false;

		if (typeof data.boxing === 'undefined' || data.boxing === false) {
			e.stopPropagation();
			inx = (data.dimX <= e.offsetX && e.offsetX <= data.dimX + data.dimW);

			// Show problems when mouse is in the 15px high zone under the graph canvas.
			if (inx && data.dimY + data.dimH <= e.offsetY && e.offsetY <= data.dimY + data.dimH + 15) {
				hideHelper(graph);

				var values = findProblems(graph, e.offsetX);
				if (values.length) {
					var tbody = $('<tbody>');

					values.forEach(function(val) {
						tbody.append(
							$('<tr>')
								.append($('<td>').text(val.clock))
								.append($('<td>').text(val.r_clock || ''))
								.append($('<td>', {'class': val.status_color}).text(val.status))
								.append($('<td>', {'class': val.severity}).text(val.name))
						);
					});

					// TODO miks: replace with translation strings.
					html = $('<table></table>')
						.addClass('list-table')
						.append(tbody)
						.append(
							$('<thead>').append(
								$('<tr>')
									.append($('<th>').text('Time').addClass('right'))
									.append($('<th>').text('Recovery time'))
									.append($('<th>').text('Status'))
									.append($('<th>').text('Problem'))
							)
						);
				}
			}
			// Show graph values if mouse is over the graph canvas.
			else if (inx && data.dimY <= e.offsetY && e.offsetY <= data.dimY + data.dimH) {
				// Helper line should follow the mouse.
				line.attr({x: e.clientX - 14});

				// Find values and draw HTML for value hintbox.
				var values = findValues(graph, e.offsetX, e.offsetY);
				if (values.length) {
					html = $('<ul></ul>');
					values.forEach(function(val) {
						$('<li></li>')
							.append(
								$('<span></span>').css({
									'background-color': val.color,
									'margin': '3px 10px 3px 3px',
									'width': '10px',
									'height': '10px',
									'float': 'left',
									'display': 'block'
								})
							)
							.append(val.metric + ': ' + val.val)
							.appendTo(html);
					});
				}
			}
			else {
				hideHelper(graph);
			}

			if (html !== null) {
				if (typeof hbox === 'undefined') {
					hbox = hintBox.createBox(e, graph, null, '', false, false, graph.parent());
					graph.data('hintbox', hbox);
				}

				hbox.html(html).css({'left': e.offsetX + 20, 'top': e.offsetY + 20});
			}
		}
		else {
			hideHelper(graph);
		}

		if (html === null) {
			graph.removeData('hintbox');
			$(hbox).remove();
		}
	}

	var methods = {
		init: function(options) {
			options = $.extend({}, {
				sbox: false,
				problems: true,
				values: false
			}, options);

			// TODO miks: remove this before going into production.
			// ---<--- testing data:
			options.values = false;
			options.problems = false;
			// --->--- testing data.

			this.each(function() {
				var graph = $(this),
					graph_data = {
						dimX: options.dims.x,
						dimY: options.dims.y,
						dimW: options.dims.w,
						dimH: options.dims.h
					};

				graph
					.attr('unselectable', 'on')
					.css('user-select', 'none')
					.on('selectstart', false)

				if (options.values || options.problems) {
					graph.on('mousemove', showHintbox);
				}

				if (options.sbox) {
					graph_data = $.extend({}, {
						start: 0,
						boxing: false,
						spp: options.spp
					}, graph_data);

					graph
						.on('mouseup', endSBoxDrag)
						.on('mousedown', startSBoxDrag)
						.on('mousemove', moveSBoxMouse);
				}

				if (options.values || options.problems || options.sbox) {
					graph
						.data('graph', graph_data)
						.on('mouseleave', function() {
							if (options.values || options.problems) {
								destroyHintbox($(this));
							}
						});
				}
			});
		}
	};

	$.fn.svggraph = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else {
			return methods.init.apply(this, arguments);
		}
	};
});
