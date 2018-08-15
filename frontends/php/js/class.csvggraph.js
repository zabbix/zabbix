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
 *  - show_problems - show problems in hintbox when mouse is moved over the problem zone.
 */
jQuery(function ($) {
	"use strict"

	var min_period = 60,  // Minimal period in seconds to zoom in.
		graph = null,     // Main graph object.
		hbox = null,      // Hintbox object.
		data = {},        // Graph options.
		sbox = null,      // SBox SVG object.
		stxt = null,      // SBox text SVG object.
		line = null;      // Graph helper.

	/**
	 * Makes SBox selection cancelable pressing Esc.
	 */
	function sBoxKeyboardInteraction(e) {
		if (e.keyCode == 27) {
			destroySBox();
		}
	}

	/**
	 * Cancel SBox and unset its variables.
	 */
	function destroySBox() {
		$('.svg-graph-selection', graph).attr({'width': 0, 'height': 0});
		$('.svg-graph-selection-text', graph).text('');

		$(document).off('keydown', sBoxKeyboardInteraction);

		graph
			.off('mousemove', moveSBoxMouse)
			.off('mouseup', destroySBox);

		data.boxing = false;
	}

	/**
	 * Destroy hintbox, unset its variables and event listeners.
	 */
	function destroyHintbox() {
		if (hbox !== null && data.isHintBoxFrozen === false) {
			graph.off('mouseup', makeHintboxStatic);
			hbox.remove();
			hbox = null;
		}
	}

	/**
	 * Hide vertical helper line and highlighted data points.
	 */
	function hideHelper() {
		line.attr({'x1': -10, 'x2': -10});
		graph.find('.svg-point-highlight').attr({'cx': -10, 'cy': -10});
	}

	/**
	 * Create a new hintbox and stick it to certain position where user has clicked.
	 */
	function makeHintboxStatic(e) {
		var content = hbox.find('> div');

		// Destroy old hintbox to make new one with close button.
		destroyHintbox();

		// Should be put inside hintBoxItem to use functionality of hintBox.
		graph.hintBoxItem = hintBox.createBox(e, graph, content, '', true, false, graph.parent());
		data.isHintBoxFrozen = true;
		hbox = graph.hintBoxItem;

		graph.hintBoxItem.on('onDeleteHint.hintBox', function(e) {
			data.isHintBoxFrozen = false; // Unfreeze because only onfrozen hintboxes can be removed.
			graph.off('mouseup', hintboxSilentMode);
			destroyHintbox();
		});

		repositionHintBox(e);
		graph.on('mouseup', hintboxSilentMode);
	}

	/**
	 * Silent mode means that hintbox is waiting for click to be repositionated. Once user clicks on graph, existing
	 * hintbox will be repositionated with a new values in the place where user clicked on.
	 */
	function hintboxSilentMode(e) {
		data.isHintBoxFrozen = false;
		showHintbox(e);
		makeHintboxStatic(e);
	}

	/**
	 * Method to start selection of some horizontal area in graph.
	 */
	function startSBoxDrag(e) {
		e.stopPropagation();

		if (data.dimX <= e.offsetX && e.offsetX <= data.dimX + data.dimW && data.dimY <= e.offsetY
				&& e.offsetY <= data.dimY + data.dimH) {
			$(document).on('keydown', sBoxKeyboardInteraction);

			graph
				.on('mousemove', moveSBoxMouse)
				.on('mouseup', destroySBox);

			data.start = e.offsetX - data.dimX;
		}
	}

	/**
	 * Method to recalculate selected area during mouse move.
	 */
	function moveSBoxMouse(e) {
		e.stopPropagation();

		if ((e.offsetX - data.dimX) > 0 && (data.dimW + data.dimX) >= e.offsetX) {
			data.end = e.offsetX - data.dimX;
			if (data.start != data.end) {
				data.isHintBoxFrozen = false;
				data.boxing = true;
				destroyHintbox();
				hideHelper();
			}
			else {
				destroySBox();
				return false;
			}

			data.end = Math.min(e.offsetX - data.dimX, data.dimW);

			sbox.attr({
				'x': (Math.min(data.start, data.end) + data.dimX) + 'px',
				'y': data.dimY + 'px',
				'width': Math.abs(data.end - data.start) + 'px',
				'height': data.dimH
			});

			var seconds = Math.round(Math.abs(data.end - data.start) * data.spp),
				label = formatTimestamp(seconds, false, true)
					+ (seconds < min_period ? ' [min 1' + locale['S_MINUTE_SHORT'] + ']'  : '');

			stxt
				.text(label)
				.attr({
					'x': (Math.min(data.start, data.end) + data.dimX + 5) + 'px',
					'y': (data.dimY + 15) + 'px'
				});
		}
	}

	/**
	 * Method to end selection of horizontal area in graph.
	 */
	function endSBoxDrag(e) {
		e.stopPropagation();

		if (data.boxing) {
			data.end = Math.min(e.offsetX - data.dimX, data.dimW);

			destroySBox();

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

	/**
	 * Read SVG nodes and find closest past value to the given x in each data set.
	 */
	function findValues(x) {
		var data_sets = [];

		[...graph[0].querySelectorAll('[data-set]')].forEach(function(ds) {
			var px = -10,
				py = -10,
				pv = null;

			// Find matching X points.
			switch (ds.getAttribute('data-set')) {
				case 'points':
					var test_x = Math.min(x, +ds.lastChild.getAttribute('cx')),
						points = [...ds.querySelectorAll('circle')].filter(function(c) {
							return (test_x >= parseInt(c.getAttribute('cx')));
						}),
						point = points.slice(-1)[0];

					if (typeof point !== 'undefined') {
						px = point.getAttribute('cx');
						py = point.getAttribute('cy');
						pv = point.getAttribute('label');
					}
					break;

				case 'staircase':
				case 'line':
					var direction = ds.querySelectorAll('.svg-graph-line')[0].getAttribute('d').split(' '),
						label = ds.querySelectorAll('.svg-graph-line')[0].getAttribute('data-label').split(','),
						index = direction.length,
						point;

					while (index) {
						index--;
						point = direction[index].substr(1).split(',');
						if (x > parseInt(point[0])) {
							px = point[0];
							py = point[1];
							pv = label[ds.getAttribute('data-set') === 'line' ? index : index / 2];
							break;
						}
					}
					break;
			}

			data_sets.push({g: ds, x: px, y: py, v: pv});
		});

		return data_sets;
	}

	/**
	 * Find what problems matches in time to the given x.
	 */
	function findProblems(x) {
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

	/**
	 * Set position of vertical helper line.
	 */
	function setHelperPosition(e) {
		line.attr({
			'x1': e.offsetX,
			'y1': data.dimY,
			'x2': e.offsetX,
			'y2': data.dimY + data.dimH
		});
	}

	/**
	 * Get tolerance for given data set. Tolerance is used to find which elements are hovered by mouse. Script takes
	 * actual data point and adds N pixels to all sides. Then looks if mouse is in calculated area. N is calculated by
	 * this function. Tolerance is used to find exacly macthed point only.
	 */
	function getDataPointTolerance(ds) {
		if (ds.getAttribute('data-set') === 'points') {
			// Take radius of first real data set point (the 0th is .svg-point-highlight).
			return +ds.childNodes[1].getAttribute('r');
		}
		else {
			return +window.getComputedStyle(ds.querySelectorAll('path')[0])['strokeWidth'];
		}
	}

	/**
	 * Position hintbox near current mouse position.
	 */
	function repositionHintBox(e) {
		var l = (document.body.clientWidth >= e.screenX + hbox.width()) ? e.offsetX : e.offsetX - hbox.width(),
			t = (window.screen.height >= e.screenY + hbox.height() + 60) ? e.offsetY + 60 : e.offsetY - hbox.height();

		hbox.css({'left': l, 'top': t});
	}

	/**
	 * Show problem or value hintboxe.
	 */
	function showHintbox(e) {
		e.stopPropagation();

		var html = null,
			inx = false;

		if (data.boxing === false) {
			// Check if mouse in the horizontal area in which hintbox must be shown.
			inx = (data.dimX <= e.offsetX && e.offsetX <= data.dimX + data.dimW);

			// Show problems when mouse is in the 15px high area under the graph canvas.
			if (data.showProblems && data.isHintBoxFrozen === false && inx && data.dimY + data.dimH <= e.offsetY
					&& e.offsetY <= data.dimY + data.dimH + 15) {
				hideHelper();

				var values = findProblems(e.offsetX);
				if (values.length) {
					var tbody = $('<tbody>');

					values.forEach(function(val) {
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

					html = $('<div></div>').append(
							$('<table></table>')
								.addClass('list-table compact-view')
								.append(tbody)
						);
				}
			}
			// Show graph values if mouse is over the graph canvas.
			else if (inx && data.dimY <= e.offsetY && e.offsetY <= data.dimY + data.dimH) {
				// Set position of mouse following helper line.
				setHelperPosition(e);

				// Find values.
				var points = findValues(e.offsetX),
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
							return;
						}
					});
				}

				// Make html for hintbox.
				if (show_hint) {
					html = $('<ul></ul>');
				}
				points.forEach(function(point) {
					var point_highlight = point.g.querySelectorAll('.svg-point-highlight')[0];
					if (xy_point === false || xy_point === point) {
						point_highlight.setAttribute('cx', point.x);
						point_highlight.setAttribute('cy', point.y);

						if (show_hint) {
							$('<li></li>')
								.append(
									$('<span></span>')
										.css('background-color', point.g.getAttribute('data-color'))
										.addClass('svg-graph-hintbox-item-color')
								)
								.append(point.g.getAttribute('data-metric') + ': ' + point.v)
								.appendTo(html);
						}
					}
					else {
						point_highlight.setAttribute('cx', -10);
						point_highlight.setAttribute('cy', -10);
					}

				});

				if (show_hint) {
					html = $('<div></div>').append(html);
				}
			}
			else {
				hideHelper();
			}

			if (html !== null) {
				if (hbox === null) {
					hbox = hintBox.createBox(e, graph, html, '', false, false, graph.parent());
					graph.on('mouseup', makeHintboxStatic);
				}
				else {
					hbox.find('> div').replaceWith(html);
				}

				repositionHintBox(e);
			}
		}
		else {
			hideHelper();
		}

		if (html === null) {
			destroyHintbox();
		}
	}

	var methods = {
		init: function(options) {
			options = $.extend({}, {
				sbox: false,
				showProblems: true
			}, options);

			this.each(function() {
				data = {
					dimX: options.dims.x,
					dimY: options.dims.y,
					dimW: options.dims.w,
					dimH: options.dims.h,
					showProblems: options.show_problems,
					isHintBoxFrozen: false,
					boxing: false
				};

				graph = $(this);
				line = graph.find('.svg-helper');
				sbox = options.sbox ? $('.svg-graph-selection', graph) : null;
				stxt = options.sbox ? $('.svg-graph-selection-text', graph) : null;
				hbox = null;

				graph
					.on('mousemove', showHintbox)
					.attr('unselectable', 'on')
					.css('user-select', 'none')
					.on('selectstart', false);

				if (options.sbox) {
					data = $.extend({}, {
						spp: options.spp
					}, data);

					graph
						.on('mousedown', startSBoxDrag)
						.on('mouseup', endSBoxDrag);
				}
			});
		},
		disableSBox: function() {
			destroySBox();
			graph
				.off('mousedown', startSBoxDrag)
				.off('mouseup', endSBoxDrag);

			delete data.spp;
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
