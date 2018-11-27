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


(function($) {
	"use strict"

	function makeWidgetDiv(data, widget) {
		widget['content_header'] = $('<div>')
			.addClass('dashbrd-grid-widget-head')
			.append($('<h4>').text(
				(widget['header'] !== '') ? widget['header'] : data['widget_defaults'][widget['type']]['header']
			));
		widget['content_body'] = $('<div>').addClass('dashbrd-grid-widget-content');
		widget['content_script'] = $('<div>');
		widget['content_header'].append($('<ul>')
			.append($('<li>')
				.append($('<button>', {
					'type': 'button',
					'class': 'btn-widget-action',
					'title': t('Adjust widget refresh interval'),
					'data-menu-popup': JSON.stringify({
						'type': 'refresh',
						'widgetName': widget['widgetid'],
						'currentRate': widget['rf_rate'],
						'multiplier': false
					}),
					'attr': {
						'aria-haspopup': true
					}
				}))
			)
		);

		return $('<div>', {
			'class': 'dashbrd-grid-widget' + (!widget['widgetid'].length ? ' new-widget' : ''),
			'css': {
				'min-height': '' + data['options']['widget-height'] + 'px',
				'min-width': '' + data['options']['widget-width'] + '%'
			}
		})
			.append($('<div>', {'class': 'dashbrd-grid-widget-mask'}))
			.append(
				$('<div>', {'class': 'dashbrd-grid-widget-padding'})
					.append(widget['content_header'])
					.append(widget['content_body'])
					.append(widget['content_script'])
			);
	}

	function makeWidgetInfoBtns(btns) {
		var info_btns = [];

		if (btns.length) {
			btns.each(function(btn) {
				info_btns.push(
					$('<button>', {
						'type': 'button',
						'data-hintbox': 1,
						'data-hintbox-static': 1
					})
						.addClass(btn.icon)
				);
				info_btns.push(
					$('<div></div>')
						.html(btn.hint)
						.addClass('hint-box')
						.hide()
				);
			});
		}

		return info_btns.length ? info_btns : null;
	}

	function removeWidgetInfoBtns($content_header) {
		if ($content_header.find('[data-hintbox=1]').length) {
			$content_header.find('[data-hintbox=1]').next('.hint-box').remove();
			$content_header.find('[data-hintbox=1]').trigger('remove');
		}
	}

	/**
	 * Set height of dashboard container DOM element.
	 *
	 * @param {object} $obj         Dashboard container jQuery object.
	 * @param {object} data         Dashboard data and options object.
	 * @param {integer} min_rows    Minimal desired rows count.
	 */
	function resizeDashboardGrid($obj, data, min_rows) {
		data['options']['rows'] = 0;

		$.each(data['widgets'], function() {
			if (this['pos']['y'] + this['pos']['height'] > data['options']['rows']) {
				data['options']['rows'] = this['pos']['y'] + this['pos']['height'];
			}
		});

		if (data['options']['rows'] == 0) {
			data.new_widget_placeholder.show();
		}

		if (typeof(min_rows) != 'undefined' && data['options']['rows'] < min_rows) {
			data['options']['rows'] = min_rows;
		}

		$obj.css({
			height: Math.max(data['options']['widget-height'] * data['options']['rows'], data.minimalHeight) + 'px'
		});
	}

	/**
	 * Calculate minimal required height of dashboard container.
	 *
	 * @param {object} $obj    Dashboard container DOM element.
	 */
	function calculateGridMinHeight($obj) {
		return $(window).height() - $obj.offset().top - parseInt($(document.body).css('margin-bottom'), 10);
	}

	function getWidgetByTarget(widgets, $div) {
		return widgets[$div.data('widget-index')];
	}

	function generateRandomString(length) {
		var space = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			ret = '';

		for (var i = 0; length > i; i++) {
			ret += space.charAt(Math.floor(Math.random() * space.length));
		}
		return ret;
	}

	function getDivPosition($obj, data, $div) {
		var pos = $div.position(),
			cell_w = data['cell-width'],
			cell_h = data['options']['widget-height'],
			place_x = Math.round(pos.left / cell_w),
			place_y = Math.round(pos.top / cell_h),
			place_w = Math.round(($div.width() + (pos.left - place_x * cell_w)) / cell_w),
			place_h = Math.round(($div.height() + (pos.top - place_y * cell_h)) / cell_h);

		if (data['pos-action'] === 'resize') {
			place_w = Math.min(place_w, place_w + place_x, data['options']['max-columns'] - place_x);
			place_h = Math.min(place_h, place_h + place_y, data['options']['max-rows'] - place_y);
		}

		place_x = Math.min(place_x, data['options']['max-columns'] - place_w);
		place_y = Math.min(place_y, data['options']['max-rows'] - place_h);

		return {
			x: Math.max(place_x, 0),
			y: Math.max(place_y, 0),
			width: Math.max(place_w, 1),
			height: Math.max(place_h, data['options']['widget-min-rows'])
		}
	}

	function getCurrentCellWidth(data) {
		return $('.dashbrd-grid-widget-container').width() / data['options']['max-columns'];
	}

	function setDivPosition($div, data, pos, is_placeholder) {
		var cell_w = is_placeholder ? data['cell-width'] : data['options']['widget-width'],
			unit = is_placeholder ? 'px' : '%';

		$div.css({
			left: cell_w * pos['x'] + unit,
			top: data['options']['widget-height'] * pos['y'] + 'px',
			width: cell_w * pos['width'] + unit,
			height: data['options']['widget-height'] * pos['height'] + 'px'
		});
	}

	function resetCurrentPositions(widgets) {
		for (var i = 0; i < widgets.length; i++) {
			widgets[i]['current_pos'] = $.extend({}, widgets[i]['pos']);
		}
	}

	function startWidgetPositioning($div, data) {
		data['cell-width'] = getCurrentCellWidth(data);
		data['placeholder'].show();
		$('.dashbrd-grid-widget-mask', $div).show();

		$div.addClass('dashbrd-grid-widget-draggable');

		resetCurrentPositions(data['widgets']);
	}

	function posEquals(pos1, pos2) {
		var ret = true;

		$.each(['x', 'y', 'width', 'height'], function(index, key) {
			if (pos1[key] !== pos2[key]) {
				ret = false;
				return false;
			}
		});

		return ret;
	}

	/**
	 * Check is there collision between two position objects.
	 *
	 * @param {object} pos1   Object with position and dimension.
	 * @param {object} pos2   Object with position and dimension.
	 * @returns {boolean}
	 */
	function rectOverlap(pos1, pos2) {
		return pos1.x < (pos2.x + pos2.width) &&
			(pos1.x + pos1.width) > pos2.x &&
			pos1.y < (pos2.y + pos2.height) &&
			(pos1.y + pos1.height) > pos2.y;
	}

	/**
	 * Rearrange widgets on drag operation.
	 *
	 * @param {array}  data    Array of widgets objects.
	 * @param {object} widget  Moved widget object.
	 */
	function realignWidget(data, widget) {
		var realignDrag = function (widget) {
				var pos = widget.current_pos,
					to_row = pos.y + pos.height;

				$.map(data.widgets, function (box) {
					return (widget.uniqueid != box.uniqueid && rectOverlap(widget.current_pos, box.current_pos))
						? box : null
				})
				.sort(function (box1, box2) {
					return box2.current_pos.y - box1.current_pos.y;
				})
				.each(function (box) {
					if (box.current_pos.y < to_row && pos.y - box.current_pos.height >= 0) {
						var free;
						box.current_pos.y = pos.y - box.current_pos.height;

						$.each(data.widgets, function() {
							free = !(box.uniqueid != this.uniqueid && rectOverlap(box.current_pos, this.current_pos));
							return free;
						});

						if (free) {
							return true;
						}
					}
					box.current_pos.y = to_row;

					realignDrag(box);
				});
			};

		realignDrag(widget);
	}

	/**
	 * Resize widgets.
	 *
	 * @param {object} widgets        Array of widget objects.
	 * @param {object} widget         Resized widget object.
	 * @param {object} axis           Resized axis options.
	 * @param {string} axis.axis_key  Axis key as string: 'x', 'y'.
	 * @param {string} axis.size_key  Size key as string: 'width', 'height'.
	 * @param {number} axis.axis_key  Minimum size allowed for one item.
	 * @param {number} axis.axis_key  Maximum size allowed for one item, also is used as maximum size of dashboard.
	 */
	function fitWigetsIntoBox(widgets, widget, axis) {
		var axis_key = axis.axis_key,
			size_key = axis.size_key,
			size_min = axis.size_min,
			size_max = axis.size_max,
			opposite_axis_key = axis_key == 'x' ? 'y' : 'x',
			opposite_size_key = size_key == 'width' ? 'height' : 'width',
			new_max = 0,
			affected,
			getAffectedInBounds = function(bounds) {

				return $.map(affected, function(box) {

					return rectOverlap(bounds, box.current_pos) ? box : null;
				});
			},
			getAffectedTreeAsArray = function(pos) {
				$.map(widgets, function(box) {

					return !('affected_axis' in box) && rectOverlap(pos, box.pos) ? box : null;
				}).each(function(box) {
					if ('affected_axis' in box) {

						return;
					}

					var boundary = $.extend({}, box.pos);

					if (axis_key in axis) {
						boundary[axis_key] = Math.max(0, boundary[axis_key] - axis[size_key]);
						boundary[size_key] += box.pos[axis_key] - boundary[axis_key];
					}
					else {
						boundary[size_key] += axis[size_key];
					}

					box.affected_axis = axis_key;

					getAffectedTreeAsArray(boundary);
				});

				return $.map(widgets, function(box) {

					return 'affected_axis' in box && box.affected_axis == axis_key && box.uniqueid != widget.uniqueid ? box : null;
				});
			};

		var boundary = $.extend({}, widget.pos);

		boundary[axis_key] += axis_key in axis ? axis[axis_key] : boundary[size_key];
		boundary[size_key] = axis[size_key];
		boundary[opposite_size_key] = widget.current_pos[opposite_size_key];

		// Get array of only affected by resize operation widgets.
		affected = getAffectedTreeAsArray(boundary);

		var margins = {},
			axis_pos = $.extend(widget.current_pos);

		// Resize action for left/up is mirrored right/down action.
		if (axis_key in axis) {
			affected.each(function(box) {
				box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
			});
			axis_pos[axis_key] = size_max - axis_pos[axis_key] - axis_pos[size_key];
		}

		affected = affected.sort(function(box1, box2) {
			return box1.current_pos[axis_key] - box2.current_pos[axis_key];
		});

		// Compact widget and it affected siblings by removing empty columns.
		affected.each(function(box) {
			var newpos = axis_pos[axis_key] + axis_pos[size_key],
				last = box.current_pos[opposite_axis_key] + box.current_pos[opposite_size_key];

			for (var i = box.current_pos[opposite_axis_key]; i < last; i++) {
				if (i in margins) {
					newpos = Math.max(newpos, margins[i]);
				}
			}

			for (var i = box.current_pos[opposite_axis_key]; i < last; i++) {
				margins[i] = newpos + box.current_pos[size_key];
			}

			if (box.current_pos[axis_key] <= newpos) {
				box.current_pos[axis_key] = newpos;
				new_max = Math.max(new_max, newpos + box.current_pos[size_key]);
			}
		});

		// Compact widget by resizing.
		if (new_max > size_max) {
			var scanline = {
					x: 0,
					y: 0,
					width: 12,
					height: 128
				},
				overlap = new_max - size_max,
				slot = axis_pos[axis_key] + axis_pos[size_key],
				next_col,
				col,
				collapsed;
			scanline[size_key] = 1;

			while (slot < new_max && overlap > 0) {
				scanline[axis_key] = slot;
				col = getAffectedInBounds(scanline);
				scanline[axis_key] += scanline[size_key];

				if (scanline[axis_key] == new_max) {
					next_col = [{current_pos: scanline}];
					collapsed = false;
				}
				else {
					next_col = getAffectedInBounds(scanline);
					collapsed = next_col.length > 0;
				}

				$.each(next_col, function(_, box) {
					box.new_pos = $.extend({}, box.current_pos);
					box.new_pos[axis_key] = slot;

					$.each(col, function(_, box1) {
						if (rectOverlap(box1.current_pos, box.new_pos)) {
							if (box1.current_pos[size_key] > size_min) {
								box1.new_pos = $.extend({}, box1.current_pos);
								box1.new_pos[size_key] -= scanline[size_key];
							}
							else {
								collapsed = false;
							}
						}

						return collapsed;
					});

					return collapsed;
				});

				next_col.concat(col).each(function(box) {
					if (collapsed && 'new_pos' in box) {
						box.current_pos = box.new_pos;
						box.current_pos[axis_key] = Math.max(box.pos[axis_key], box.current_pos[axis_key]);
					}

					delete box.new_pos;
				});

				if (collapsed) {
					affected.each(function(box) {
						if (box.current_pos[axis_key] > slot + scanline[size_key]) {
							box.current_pos[axis_key] -= scanline[size_key];
							box.current_pos[axis_key] = Math.max(box.current_pos[axis_key], axis_key in axis
								? size_max - box.pos[axis_key] - box.pos[size_key]
								: box.pos[axis_key]
							);
						}
					});

					overlap -= 1;
				}
				else {
					slot += scanline[size_key];
				}
			}
		}

		/**
		 * When it is impossible to fit affected widgets into required boundary box ensure that wigets at least will
		 * stay in dashboard boundary box.
		 */
		if (overlap > 0) {
			widget.current_pos[size_key] -= overlap;

			if (axis_key in axis) {
				widget.current_pos[axis_key] += overlap;
			}

			affected.each(function(box) {
				box.current_pos[axis_key] -= overlap;
				box.current_pos[axis_key] = Math.max(box.current_pos[axis_key], axis_key in axis
					? size_max - box.pos[axis_key] - box.pos[size_key]
					: box.pos[axis_key]
				);
			});
		}

		// Resize action for left/up is mirrored right/down action, mirror coordinates back.
		if (axis_key in axis) {
			affected.each(function(box) {
				box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
			});
		}
	}

	/**
	 * Rearrange widgets. Modifies widget.current_pos if desired size is greater than allowed by resize.
	 *
	 * @param {array}  data    Array of widgets objects.
	 * @param {object} widget  Moved widget object.
	 */
	function realignResize(data, widget) {
		var changes = {},
			axis;

		// Fill changes object with difference between current_pos and pos. Do not set not changed values.
		$.each(widget.current_pos, function(k, v) {
			if (widget.pos[k] != v) {
				changes[k] = v - widget.pos[k];
			}
		});

		data.widgets.each(function(box) {
			if (box.uniqueid != widget.uniqueid) {
				box.current_pos = $.extend({}, box.pos);
			}

			delete box.affected_axis;
		});

		// If there are no affected widgets exit.
		if ('width' in changes == false && 'height' in changes == false) {
			return;
		}

		// Horizontal resize.
		if ('width' in changes) {
			axis = {
				axis_key: 'x',
				size_key: 'width',
				size_min: 1,
				size_max: data.options['max-columns'],
				width: changes.width
			}

			if ('x' in changes) {
				axis.x = changes.x;
			}

			fitWigetsIntoBox(data.widgets, widget, axis);
		}

		// Vertical resize.
		if ('height' in changes) {
			axis = {
				axis_key: 'y',
				size_key: 'height',
				size_min: data.options['widget-min-rows'],
				size_max: data.options['max-rows'],
				height: changes.height
			}

			if ('y' in changes) {
				axis.y = changes.y;
			}

			fitWigetsIntoBox(data.widgets, widget, axis);
		}

		// Force to repaint non changed widgets too.
		data.widgets.each(function(box) {
			if ('current_pos' in box == false) {
				box.current_pos = $.extend({}, box.pos);
			}
		});

		return;
	}

	function checkWidgetOverlap($obj, data, widget) {
		resetCurrentPositions(data['widgets']);
		realignWidget(data, widget);

		$.each(data['widgets'], function() {
			if (!posEquals(this['pos'], this['current_pos'])) {
				this['pos'] = this['current_pos'];
				setDivPosition(this['div'], data, this['pos'], false);
			}

			delete this['current_pos'];
		});
	}

	/**
	 * User action handler for resize of widget
	 *
	 * @param {object} $obj  Dasboard DOM element.
	 * @param {object} $div  Widget DOM element.
	 * @param {object} data  Dashboard data object.
	 */
	function doWidgetResize($obj, $div, data) {
		var	widget = getWidgetByTarget(data['widgets'], $div),
			pos = getDivPosition($obj, data, $div);

		if (!posEquals(pos, widget['current_pos'])) {
			widget['current_pos'] = pos;
			realignResize(data, widget);

			data.widgets.each(function(box) {
				if (widget.uniqueid != box.uniqueid && 'current_pos' in box) {
					setDivPosition(box['div'], data, box['current_pos'], false);
				}
			});

			setDivPosition(data['placeholder'], data, pos, true);
			resizeDashboardGrid($obj, data, pos.y + pos.height);
		}
		else {
			setDivPosition(data['placeholder'], data, pos, false);
		}
	}

	/**
	 * User action handler for drag of widget
	 *
	 * @param {object} $obj  Dasboard DOM element.
	 * @param {object} $div  Widget DOM element.
	 * @param {object} data  Dashboard data object.
	 */
	function doWidgetPositioning($obj, $div, data) {
		var	widget = getWidgetByTarget(data['widgets'], $div),
			pos = getDivPosition($obj, data, $div);

		setDivPosition(data['placeholder'], data, pos, true);

		if (!posEquals(pos, widget['current_pos'])) {
			resetCurrentPositions(data['widgets']);
			widget['current_pos'] = pos;

			realignWidget(data, widget);

			$.each(data['widgets'], function() {
				if (widget != this) {
					setDivPosition(this['div'], data, this['current_pos'], false);
				}
			});
		}

		resizeDashboardGrid($obj, data, pos.y + pos.height);
	}

	function stopWidgetPositioning($obj, $div, data) {
		var	widget = getWidgetByTarget(data['widgets'], $div);

		data['placeholder'].hide();
		$('.dashbrd-grid-widget-mask', $div).hide();

		$div.removeClass('dashbrd-grid-widget-draggable');

		$.each(data['widgets'], function() {
			// Check if position of widget changed
			var new_pos = this['current_pos'],
				old_pos = this['pos'],
				changed = false;

			$.each(['x', 'y', 'width', 'height'], function(index, value) {
				if (new_pos[value] !== old_pos[value]) {
					changed = true;
				}
			});

			if (changed === true) {
				// mark dashboard as updated
				data['options']['updated'] = true;
				this['pos'] = this['current_pos'];
			}

			// should be present only while dragging
			delete this['current_pos'];
		});
		setDivPosition($div, data, widget['pos'], false);
		resizeDashboardGrid($obj, data);
	}

	function makeDraggable($obj, data, widget) {
		widget['content_header']
			.addClass('cursor-move');

		widget['div'].draggable({
			handle: widget['content_header'],
			scroll: true,
			scrollSensitivity: data.options['widget-height'],
			start: function(event, ui) {
				data['pos-action'] = 'drag';
				data.calculated = {
					'left-max': $obj.width() - ui.helper.width(),
					'top-max': data.options['max-rows'] * data.options['widget-height'] - ui.helper.height(),
				};

				setResizableState('disable', data.widgets, widget.uniqueid);
				startWidgetPositioning(ui.helper, data);
			},
			drag: function(event, ui) {

				// Limit element draggable area for X and Y axis.
				ui.position = {
					left: Math.max(0, Math.min(ui.position.left, data.calculated['left-max'])),
					top: Math.max(0, Math.min(ui.position.top, data.calculated['top-max']))
				};

				doWidgetPositioning($obj, ui.helper, data);
			},
			stop: function(event, ui) {
				data['pos-action'] = '';
				delete data.calculated;

				setResizableState('enable', data.widgets, widget.uniqueid);
				stopWidgetPositioning($obj, ui.helper, data);
			}
		});
	}

	function makeResizable($obj, data, widget) {
		var	handles = {};

		$.each(['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw'], function(index, key) {
			var	$handle = $('<div>').addClass('ui-resizable-handle').addClass('ui-resizable-' + key);

			if ($.inArray(key, ['n', 'e', 's', 'w']) >= 0) {
				$handle
					.append($('<div>', {'class': 'ui-resize-dot'}))
					.append($('<div>', {'class': 'ui-resizable-border-' + key}));
			}

			widget['div'].append($handle);
			handles[key] = $handle;
		});

		widget['div'].resizable({
			handles: handles,
			autoHide: true,
			scroll: false,
			containment: 'parent',
			minWidth: getCurrentCellWidth(data),
			start: function(event, ui) {
				data['pos-action'] = 'resize';
				setResizableState('disable', data.widgets, widget.uniqueid);
				startWidgetPositioning($(event.target), data);
			},
			resize: function(event, ui) {
				// Hack for Safari to manually accept parent container height in pixels on widget resize.
				if (SF) {
					$.each(data['widgets'], function() {
						if (this.type === 'clock' || this.type === 'sysmap') {
							this.content_body.find(':first').height(this.content_body.height());
						}
					});
				}

				doWidgetResize($obj, $(event.target), data);
			},
			stop: function(event, ui) {
				data['pos-action'] = '';
				setResizableState('enable', data.widgets, widget.uniqueid);
				stopWidgetPositioning($obj, $(event.target), data);

				// Hack for Safari to manually accept parent container height in pixels when done widget snapping to grid.
				if (SF) {
					$.each(data['widgets'], function() {
						if (this.type === 'clock' || this.type === 'sysmap') {
							this.content_body.find(':first').height(this.content_body.height());
						}
					});
				}

				doAction('onResizeEnd', $obj, data, widget);
			},
			minHeight: data['options']['widget-min-rows'] * data['options']['widget-height']
		});
	}

	/**
	 * Set resizable state for dashboard widgets.
	 *
	 * @param {string} state     Enable or disable resizable for widgets. Available values: 'enable', 'disable'.
	 * @param {array}  widgets   Array of all widgets.
	 * @param {string} ignoreid  All widget except widget with such id will be affected.
	 */
	function setResizableState(state, widgets, ignoreid) {
		widgets.each(function (widget) {
			if (widget.uniqueid !== ignoreid) {
				widget.div.resizable(state);
			}
		});
	}

	function showPreloader(widget) {
		if (typeof(widget['preloader_div']) == 'undefined') {
			widget['preloader_div'] = $('<div>')
				.addClass('preloader-container')
				.append($('<div>').addClass('preloader'));

			widget['div'].append(widget['preloader_div']);
		}
	}

	function hidePreloader(widget) {
		if (typeof(widget['preloader_div']) != 'undefined') {
			widget['preloader_div'].remove();
			delete widget['preloader_div'];
		}
	}

	function startPreloader(widget) {
		if (typeof(widget['preloader_timeoutid']) != 'undefined' || typeof(widget['preloader_div']) != 'undefined') {
			return;
		}

		widget['preloader_timeoutid'] = setTimeout(function () {
			delete widget['preloader_timeoutid'];

			showPreloader(widget);
			widget['content_body'].fadeTo(widget['preloader_fadespeed'], 0.4);
		}, widget['preloader_timeout']);
	}

	function stopPreloader(widget) {
		if (typeof(widget['preloader_timeoutid']) != 'undefined') {
			clearTimeout(widget['preloader_timeoutid']);
			delete widget['preloader_timeoutid'];
		}

		hidePreloader(widget);
		widget['content_body'].fadeTo(0, 1);
	}

	function startWidgetRefreshTimer($obj, data, widget, rf_rate) {
		if (rf_rate != 0) {
			widget['rf_timeoutid'] = setTimeout(function () {
				if (doAction('timer_refresh', $obj, data, widget) == 0) {
					// widget was not updated, update it's content
					updateWidgetContent($obj, data, widget);
				}
				else {
					// widget was updated, start next timeout.
					startWidgetRefreshTimer($obj, data, widget, widget['rf_rate']);
				}
			}, rf_rate * 1000);
		}
	}

	function stopWidgetRefreshTimer(widget) {
		clearTimeout(widget['rf_timeoutid']);
		delete widget['rf_timeoutid'];
	}

	function startWidgetRefresh($obj, data, widget) {
		if (typeof(widget['rf_timeoutid']) != 'undefined') {
			stopWidgetRefreshTimer(widget);
		}

		startWidgetRefreshTimer($obj, data, widget, widget['rf_rate']);
	}

	function updateWidgetContent($obj, data, widget) {
		if (++widget['update_attempts'] > 1) {
			return;
		}

		var url = new Curl('zabbix.php'),
			ajax_data;

		url.setArgument('action', 'widget.' + widget['type'] + '.view');

		ajax_data = {
			'dashboardid': data['dashboard']['id'],
			'uniqueid': widget['uniqueid'],
			'initial_load': widget['initial_load'] ? 1 : 0,
			'edit_mode': data['options']['edit_mode'] ? 1 : 0,
			'storage': widget['storage'],
			'content_width': widget['content_body'].width(),
			'content_height': widget['content_body'].height() - 4 // -4 is added to avoid scrollbar
		};

		if (widget['widgetid'] !== '') {
			ajax_data['widgetid'] = widget['widgetid'];
		}
		if (widget['header'] !== '') {
			ajax_data['name'] = widget['header'];
		}
		// display widget with yet unsaved changes
		if (typeof widget['fields'] !== 'undefined' && Object.keys(widget['fields']).length != 0) {
			ajax_data['fields'] = JSON.stringify(widget['fields']);
		}
		if (typeof(widget['dynamic']) !== 'undefined') {
			ajax_data['dynamic_hostid'] = widget['dynamic']['hostid'];
			ajax_data['dynamic_groupid'] = widget['dynamic']['groupid'];
		}

		startPreloader(widget);

		jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: ajax_data,
			dataType: 'json',
			success: function(resp) {
				stopPreloader(widget);
				var $content_header = $('h4', widget['content_header']);

				$content_header.text(resp.header);

				if (typeof resp.aria_label !== 'undefined') {
					$content_header.attr('aria-label', (resp.aria_label !== '') ? resp.aria_label : null);
				}

				widget['content_body'].find('[data-hintbox=1]').trigger('remove');
				widget['content_body'].empty();
				if (typeof(resp.messages) !== 'undefined') {
					widget['content_body'].append(resp.messages);
				}
				widget['content_body'].append(resp.body);
				if (typeof(resp.debug) !== 'undefined') {
					widget['content_body'].append(resp.debug);
				}
				removeWidgetInfoBtns(widget['content_header']);

				if (typeof(resp.info) !== 'undefined' && data['options']['edit_mode'] === false) {
					widget['content_header'].find('ul > li').prepend(makeWidgetInfoBtns(resp.info));
				}

				// Creates new script elements and removes previous ones to force their re-execution.
				widget['content_script'].empty();
				if (typeof(resp.script_file) !== 'undefined' && resp.script_file.length) {
					// NOTE: it is done this way to make sure, this script is executed before script_run function below.
					if (typeof(resp.script_file) === 'string') {
						resp.script_file = [resp.script_file];
					}

					for (var i = 0, l = resp.script_file.length; l > i; i++) {
						var new_script = $('<script>')
							.attr('type', 'text/javascript')
							.attr('src', resp.script_file[i]);
						widget['content_script'].append(new_script);
					}
				}
				if (typeof(resp.script_inline) !== 'undefined') {
					// NOTE: to execute script with current widget context, add unique ID for required div, and use it in script.
					var new_script = $('<script>')
						.text(resp.script_inline);
					widget['content_script'].append(new_script);
				}

				if (widget['update_attempts'] == 1) {
					widget['update_attempts'] = 0;
					startWidgetRefreshTimer($obj, data, widget, widget['rf_rate']);
					doAction('onContentUpdated', $obj, data, null);
				}
				else {
					widget['update_attempts'] = 0;
					updateWidgetContent($obj, data, widget);
				}

				var callOnDashboardReadyTrigger = false;
				if (!widget['ready']) {
					widget['ready'] = true; // leave it before registerDataExchangeCommit.
					methods.registerDataExchangeCommit.call($obj);

					// If this is the last trigger loaded, then set callOnDashboardReadyTrigger to be true.
					callOnDashboardReadyTrigger
						= (data['widgets'].filter(function(widget) {return !widget['ready']}).length == 0);
				}
				widget['ready'] = true;

				if (callOnDashboardReadyTrigger) {
					doAction('onDashboardReady', $obj, data, null);
				}
			},
			error: function() {
				// TODO: gentle message about failed update of widget content
				widget['update_attempts'] = 0;
				startWidgetRefreshTimer($obj, data, widget, 3);
			}
		});

		widget['initial_load'] = false;
	}

	function refreshWidget($obj, data, widget) {
		if (typeof(widget['rf_timeoutid']) !== 'undefined') {
			stopWidgetRefreshTimer(widget);
		}

		updateWidgetContent($obj, data, widget);
	}

	function updateWidgetConfig($obj, data, widget) {
		var	url = new Curl('zabbix.php'),
			fields = $('form', data.dialogue['body']).serializeJSON(),
			type = fields['type'],
			name = fields['name'],
			ajax_data = {
				type: type,
				name: name
			},
			pos,
			preloader;

		delete fields['type'];
		delete fields['name'];

		url.setArgument('action', 'dashboard.widget.check');

		if (Object.keys(fields).length != 0) {
			ajax_data['fields'] = JSON.stringify(fields);
		}

		if (widget === null || 'type' in widget == false) {
			if (widget && 'pos' in widget) {
				pos = $.extend({}, data.widget_defaults[type].size, widget.pos);

				$.map(data.widgets, function(box) {
					return rectOverlap(box.pos, pos) ? box : null;
				}).each(function (box) {
					if (!rectOverlap(box.pos, pos)) {
						return;
					}

					if (pos.x + pos.width > box.pos.x && pos.x < box.pos.x) {
						pos.width = box.pos.x - pos.x;
					}
					else if (pos.y + pos.height > box.pos.y && pos.y < box.pos.y) {
						pos.height = box.pos.y - pos.y;
					}
				});

				pos.width = Math.min(data.options['max-columns'] - pos.x, pos.width);
				pos.height = Math.min(data.options['max-rows'] - pos.y, pos.height);
			}
			else {
				pos = findEmptyPosition($obj, data, type);
			}

			preloader = $('<div/>').css({
				position: 'absolute',
				top: pos.y * data.options['widget-height'] + 'px',
				left: pos.x * data.options['widget-width'] + '%',
				height: pos.height * data.options['widget-height'] + 'px',
				width: pos.width * data.options['widget-width'] + '%'
			}).appendTo($obj);
		}

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: ajax_data,
			success: function(resp) {
				if (typeof(resp.errors) !== 'undefined') {
					// Error returned. Remove previous errors.
					$('.msg-bad', data.dialogue['body']).remove();
					data.dialogue['body'].prepend(resp.errors);
				}
				else {
					// No errors, proceed with update.
					overlayDialogueDestroy('widgetConfg');

					if (widget === null || 'type' in widget == false) {
						// In case of ADD widget, create widget with required selected fields and add it to dashboard.
						var scroll_by = (pos['y'] * data['options']['widget-height'])
								- $('.dashbrd-grid-widget-container').scrollTop(),
							widget_data = {
								'type': type,
								'header': name,
								'pos': pos,
								'rf_rate': 0,
								'fields': fields
							},
							add_new_widget = function() {
								methods.addWidget.call($obj, widget_data);
								// New widget is last element in data['widgets'] array.
								widget = data['widgets'].slice(-1)[0];
								updateWidgetContent($obj, data, widget);
								setWidgetModeEdit($obj, data, widget);
							};

						if (scroll_by > 0) {
							var new_height = (pos['y'] + pos['height']) * data['options']['widget-height'];

							if (new_height > $('.dashbrd-grid-widget-container').height()) {
								$('.dashbrd-grid-widget-container').height(new_height);
							}

							$('html, body')
								// Estimated scroll speed: 200ms for each 250px.
								.animate({scrollTop: '+=' + scroll_by + 'px'}, Math.floor(scroll_by / 250) * 200)
								.promise()
								.then(add_new_widget);
						}
						else {
							add_new_widget();
						}
					}
					else {
						// In case of EDIT widget.
						if (widget['type'] !== type) {
							widget['type'] = type;
							widget['initial_load'] = true;
						}

						widget['header'] = name;
						widget['fields'] = fields;
						updateWidgetDynamic($obj, data, widget);
						refreshWidget($obj, data, widget);
					}

					// Mark dashboard as updated.
					data['options']['updated'] = true;
				}
			}
		}).always(function() {
			if (preloader) {
				preloader.remove();
			}
		});
	}

	function findEmptyPosition($obj, data, type) {
		var pos = {
			'x': 0,
			'y': 0,
			'width': data['widget_defaults'][type]['size']['width'],
			'height': data['widget_defaults'][type]['size']['height']
		}

		// go y by row and try to position widget in each space
		var	max_col = data['options']['max-columns'] - pos['width'],
			found = false,
			x, y;

		for (y = 0; !found; y++) {
			for (x = 0; x <= max_col && !found; x++) {
				pos['x'] = x;
				pos['y'] = y;
				found = isPosFree($obj, data, pos);
			}
		}

		return pos;
	}

	function isPosFree($obj, data, pos) {
		var free = true;

		$.each(data['widgets'], function() {
			if (rectOverlap(pos, this['pos'])) {
				free = false;
			}
		});

		return free;
	}

	function openConfigDialogue($obj, data, widget, trigger_elmnt) {
		var edit_mode = (widget !== null);

		data.dialogue = {};
		data.dialogue.widget = widget;

		overlayDialogue({
			'title': (edit_mode ? t('Edit widget') : t('Add widget')),
			'content': '',
			'buttons': [
				{
					'title': (edit_mode ? t('Apply') : t('Add')),
					'class': 'dialogue-widget-save',
					'keepOpen': true,
					'action': function() {
						updateWidgetConfig($obj, data, widget);
					}
				},
				{
					'title': t('Cancel'),
					'class': 'btn-alt',
					'action': function() {}
				}
			],
			'dialogueid': 'widgetConfg'
		}, trigger_elmnt);

		var overlay_dialogue = $('#overlay_dialogue');
		data.dialogue.div = overlay_dialogue;
		data.dialogue.body = $('.overlay-dialogue-body', overlay_dialogue);

		updateWidgetConfigDialogue();
	}

	function setModeEditDashboard($obj, data) {
		$.each(data['widgets'], function(index, widget) {
			widget['rf_rate'] = 0;
			setWidgetModeEdit($obj, data, widget);
		});

		data['pos-action'] = '';
		data['cell-width'] = getCurrentCellWidth(data);
		data['add_widget_dimension'] = {};

		/**
		 * Add new widget user interaction handlers.
		 */
		$(document).on('click mouseup dragend', function() {
			if (data['pos-action'] != 'add') {
				return;
			}

			var dimension = $.extend({}, data.add_widget_dimension);

			if (dimension.width == 1 && dimension.height == 2) {
				delete dimension.width;
				delete dimension.height;
			}

			data['pos-action'] = '';
			data.add_widget_dimension = {};
			setResizableState('enable', data.widgets, '');
			$obj.dashboardGrid('addNewWidget', null, dimension);
		});
		$obj.on('mousedown', function(event) {
			if (event.which != 1 ) {
				return;
			}

			if (data['pos-action'] == '' && ($(event.target).is($obj))
					|| $(event.target).is(data.new_widget_placeholder)
					|| $(event.target).parent().is(data.new_widget_placeholder)) {
				setResizableState('disable', data.widgets, '');
				data['pos-action'] = 'add';
				data.new_widget_placeholder
					.find('.dashbrd-grid-widget-new-box')
						.removeClass('dashbrd-grid-widget-set-position')
						.text(t('Release to create a new widget in the selected area.'))
						.addClass('dashbrd-grid-widget-set-size');
				return cancelEvent(event);
			}
		}).on('mouseleave', function(event) {
			if (data['pos-action'] == 'add') {
				return;
			}

			data.add_widget_dimension = {};

			if (data.widgets.length) {
				data.new_widget_placeholder.hide();
			}
			else {
				data.new_widget_placeholder.removeAttr('style');
			}

			resizeDashboardGrid($obj, data);
			data.new_widget_placeholder
				.find('.dashbrd-grid-widget-new-box')
					.text(t('Add a new widget'))
					.removeClass('dashbrd-grid-widget-set-size dashbrd-grid-widget-set-position');

		}).on('mouseenter mousemove', function(event) {
			var drag = data['pos-action'] == 'add';

			if (data['pos-action'] !== '' && !drag) {
				return;
			}

			if (event.type == 'mouseenter' && data['pos-action'] == '') {
				data.new_widget_placeholder.show()
					.find('.dashbrd-grid-widget-new-box')
						.text(t('Click and drag to mark desired widget size.'))
						.addClass('dashbrd-grid-widget-set-position');
			}

			if (!drag && !$(event.target).is($obj) && !$(event.target).is(data.new_widget_placeholder)
					&& !$(event.target).parent().is(data.new_widget_placeholder)) {
				resizeDashboardGrid($obj, data);
				data.add_widget_dimension = {};
				data.new_widget_placeholder.hide()
					.find('.dashbrd-grid-widget-new-box')
						.removeClass('dashbrd-grid-widget-set-size');
				return;
			}

			var o = data.options,
				offset = $obj.offset(),
				y = Math.min(o['max-rows'] - 1,
					Math.max(0, Math.floor((event.pageY - offset.top) / o['widget-height']))
				),
				x = Math.min(o['max-columns'] - 1,
					Math.max(0, Math.floor((event.pageX - offset.left) / data['cell-width']))
				),
				pos = {
					y: y,
					x: x,
					height: o['widget-min-rows'],
					width: 1
				},
				overlap = false;

			if (drag) {
				if ('top' in data.add_widget_dimension == false) {
					data.add_widget_dimension = $.extend(data.add_widget_dimension, {
						top: y,
						left: x
					});
				}

				pos = {
					y: Math.min(y, data.add_widget_dimension.top),
					x: Math.min(x, data.add_widget_dimension.left),
					width: Math.abs(data.add_widget_dimension.left - x) + 1,
					height: Math.max(2, Math.abs(data.add_widget_dimension.top - y) + 1 +
						(data.add_widget_dimension.top > y)
					)
				}

				$.each(data.widgets, function(_, box) {
					overlap |= rectOverlap(box.pos, pos);

					return !overlap;
				});

				if (overlap) {
					pos = data.add_widget_dimension;
				}
			}
			else {
				// Proceed only when coordinates have been changed.
				if (data.add_widget_dimension.y == y && data.add_widget_dimension.x == x) {
					return;
				}

				$.each(data.widgets, function(_, box) {
					overlap |= rectOverlap(box.pos, pos);

					return !overlap;
				});

				/**
				 * If there is collision make additional check to ensure that mouse is not at the bottom of 1x2 free
				 * slot.
				 */
				if (overlap && pos.y > 0) {
					overlap = false;
					--pos.y;

					$.each(data.widgets, function(_, box) {
						overlap |= rectOverlap(box.pos, pos);

						return !overlap;
					});
				}

				if (overlap) {
					data.add_widget_dimension = {};
					data.new_widget_placeholder.hide()
						.find('.dashbrd-grid-widget-new-box')
							.removeClass('dashbrd-grid-widget-set-size');
					return;
				}
			}

			resizeDashboardGrid($obj, data, pos.y + pos.height);
			data.add_widget_dimension = $.extend(data.add_widget_dimension, pos);

			data.new_widget_placeholder.css({
				position: 'absolute',
				top: data.add_widget_dimension.y * o['widget-height'] + 'px',
				left: data.add_widget_dimension.x * o['widget-width'] + '%',
				height: data.add_widget_dimension.height * o['widget-height'] + 'px',
				width: data.add_widget_dimension.width * o['widget-width'] + '%'
			}).show();
		});

		return;
	}

	function setWidgetModeEdit($obj, data, widget) {
		var	btn_edit = $('<button>')
			.attr('type', 'button')
			.addClass('btn-widget-edit')
			.attr('title', t('Edit'))
			.click(function() {
				doAction('beforeConfigLoad', $obj, data, widget);
				methods.editWidget.call($obj, widget, this);
			});

		var	btn_delete = $('<button>')
			.attr('type', 'button')
			.addClass('btn-widget-delete')
			.attr('title', t('Delete'))
			.click(function(){
				methods.deleteWidget.call($obj, widget);
			});

		$('ul', widget['content_header']).hide();
		widget['content_header'].append($('<ul>')
			.addClass('dashbrd-widg-edit')
			.append($('<li>').append(btn_edit))
			.append($('<li>').append(btn_delete))
		);

		stopWidgetRefreshTimer(widget);
		makeDraggable($obj, data, widget);
		makeResizable($obj, data, widget);
	}

	function deleteWidget($obj, data, widget) {
		var index = widget['div'].data('widget-index');

		// remove div from the grid
		widget['div'].find('[data-hintbox=1]').trigger('remove');
		widget['div'].remove();
		data['widgets'].splice(index, 1);

		// update widget-index for all following widgets
		for (var i = index; i < data['widgets'].length; i++) {
			data['widgets'][i]['div'].data('widget-index', i);
		}

		// mark dashboard as updated
		data['options']['updated'] = true;
		resizeDashboardGrid($obj, data);
	}

	function saveChanges($obj, data) {
		var	url = new Curl('zabbix.php'),
			ajax_widgets = [];

		// Remove previous messages.
		dashboardRemoveMessages();

		url.setArgument('action', 'dashboard.update');

		$.each(data['widgets'], function(index, widget) {
			var	ajax_widget = {};

			if (widget['widgetid'] !== '') {
				ajax_widget['widgetid'] = widget['widgetid'];
			}
			ajax_widget['pos'] = widget['pos'];
			ajax_widget['type'] = widget['type'];
			ajax_widget['name'] = widget['header'];
			if (Object.keys(widget['fields']).length != 0) {
				ajax_widget['fields'] = JSON.stringify(widget['fields']);
			}

			ajax_widgets.push(ajax_widget);
		});

		var ajax_data = {
			dashboardid: data['dashboard']['id'], // can be undefined if dashboard is new
			name: data['dashboard']['name'],
			userid: data['dashboard']['userid'],
			widgets: ajax_widgets
		};

		if (isset('sharing', data['dashboard'])) {
			ajax_data['sharing'] = data['dashboard']['sharing'];
		}

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: ajax_data,
			success: function(resp) {
				// We can have redirect with errors.
				if ('redirect' in resp) {
					// There are no more unsaved changes.
					data['options']['updated'] = false;
					/*
					 * Replace add possibility to remove previous url (as ..&new=1) from the document history.
					 * It allows to use back browser button more user-friendly.
					 */
					window.location.replace(resp.redirect);
				}
				else if ('errors' in resp) {
					// Error returned.
					dashboardAddMessages(resp.errors);
				}
			},
			complete: function() {
				var ul = $('#dashbrd-config').closest('ul');
				$('#dashbrd-save', ul).prop('disabled', false);
			}
		});
	}

	function confirmExit($obj, data) {
		if (data['options']['updated'] === true) {
			return t('You have unsaved changes.') + "\n" + t('Are you sure, you want to leave this page?');
		}
	}

	function updateWidgetDynamic($obj, data, widget) {
		// this function may be called for widget that is not in data['widgets'] array yet.
		if (typeof(widget['fields']['dynamic']) !== 'undefined' && widget['fields']['dynamic'] === '1') {
			if (data['dashboard']['dynamic']['has_dynamic_widgets'] === true) {
				widget['dynamic'] = {
					'hostid': data['dashboard']['dynamic']['hostid'],
					'groupid': data['dashboard']['dynamic']['groupid']
				};
			}
			else {
				delete widget['dynamic'];
			}
		}
		else if (typeof(widget['dynamic']) !== 'undefined') {
			delete widget['dynamic'];
		}
	}

	function generateUniqueId($obj, data) {
		var ref = false;

		while (!ref) {
			ref = generateRandomString(5);

			$.each(data['widgets'], function(index, widget) {
				if (widget['uniqueid'] === ref) {
					ref = false;
					return false; // break
				}
			});
		}

		return ref;
	}

	/**
	 * Performs action added by addAction function.
	 *
	 * @param {string} hook_name  Name of trigger that is currently being called.
	 * @param {object} $obj       Dashboard grid object.
	 * @param {object} data       Data from dashboard grid.
	 * @param {object} widget     Current widget object (can be null for generic actions).
	 *
	 * @return int                Number of triggers, that were called.
	 */
	function doAction(hook_name, $obj, data, widget) {
		if (typeof(data['triggers'][hook_name]) === 'undefined') {
			return 0;
		}
		var triggers = [];

		if (widget === null) {
			triggers = data['triggers'][hook_name];
		}
		else {
			$.each(data['triggers'][hook_name], function(index, trigger) {
				if (widget['uniqueid'] === trigger['uniqueid']) {
					triggers.push(trigger);
				}
			});
		}
		triggers.sort(function(a,b) {
			var priority_a = (typeof(a['options']['priority']) !== 'undefined') ? a['options']['priority'] : 10;
			var priority_b = (typeof(b['options']['priority']) !== 'undefined') ? b['options']['priority'] : 10;

			if (priority_a < priority_b) {
				return -1;
			}
			if (priority_a > priority_b) {
				return 1;
			}
			return 0;
		});

		$.each(triggers, function(index, trigger) {
			if (typeof(window[trigger['function']]) !== typeof(Function)) {
				return true; // continue
			}

			var params = [];
			if (typeof(trigger['options']['parameters']) !== 'undefined') {
				params = trigger['options']['parameters'];
			}
			if (typeof(trigger['options']['grid']) !== 'undefined') {
				var grid = {};
				if (typeof(trigger['options']['grid']['widget']) !== 'undefined'
						&& trigger['options']['grid']['widget']
				) {
					if (widget === null) {
						var widgets = methods.getWidgetsBy.call($obj, 'uniqueid', trigger['uniqueid']);
						// will return only first element
						if (widgets.length > 0) {
							grid['widget'] = widgets[0];
						}
					}
					else {
						grid['widget'] = widget;
					}
				}
				if (typeof(trigger['options']['grid']['data']) !== 'undefined' && trigger['options']['grid']['data']) {
					grid['data'] = data;
				}
				if (typeof(trigger['options']['grid']['obj']) !== 'undefined' && trigger['options']['grid']['obj']) {
					grid['obj'] = $obj;
				}
				params.push(grid);
			}

			try {
				window[trigger['function']].apply(null, params);
			}
			catch(e) {}
		});

		return triggers.length;
	}

	var	methods = {
		init: function(options) {
			var default_options = {
				'widget-height': 70,
				'widget-min-rows': 2,
				'max-rows': 64,
				'max-columns': 12,
				'rows': 0,
				'updated': false,
				'editable': true
			};
			options = $.extend(default_options, options);
			options['widget-width'] = 100 / options['max-columns'];
			options['edit_mode'] = false;

			return this.each(function() {
				var	$this = $(this),
					new_widget_placeholder = $('<div>', {class: 'dashbrd-grid-new-widget-placeholder'}).append(
						$('<div>', {
							class: 'dashbrd-grid-widget-new-box',
							text: t('Add a new widget')
						})
					);

				if (options['editable']) {
					if (options['kioskmode']) {
						new_widget_placeholder = $('<h1>').text(t('Cannot add widgets in kiosk mode'));
					}
					else {
						new_widget_placeholder.on('click', function() {
							// Add new widget handler when not in edit mode.
							if (!methods.isEditMode.call($this)) {
								showEditMode();
								methods.addNewWidget.call($this, this);
							}
						});
					}
				}
				else {
					new_widget_placeholder.addClass('disabled');
				}

				$this.append(new_widget_placeholder);

				$this.data('dashboardGrid', {
					dashboard: {},
					options: options,
					widgets: [],
					widget_defaults: {},
					triggers: {},
					placeholder: $this.find('.dashbrd-grid-widget-placeholder').hide(),
					new_widget_placeholder: new_widget_placeholder,
					widget_relation_submissions: [],
					widget_relations: {
						relations: [],
						tasks: {}
					},
					data_buffer: [],
					minimalHeight: calculateGridMinHeight($this)
				});

				var	data = $this.data('dashboardGrid');

				$(window).on('beforeunload', function() {
					var	res = confirmExit($this, data);

					// Return value only if we need confirmation window, return nothing otherwise.
					if (typeof res !== 'undefined') {
						return res;
					}
				}).on('resize', function () {
					// Recalculate dashboard container minimal required height.
					data.minimalHeight = calculateGridMinHeight($this);
					data['cell-width'] = getCurrentCellWidth(data);
				});
			});
		},

		setDashboardData: function(dashboard) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				if (!$.isEmptyObject(data['dashboard']) && (data['dashboard']['name'] !== dashboard['name']
						|| data['dashboard']['userid'] !== dashboard['userid'])) {
					data['options']['updated'] = true;
				}

				dashboard = $.extend({}, data['dashboard'], dashboard);
				data['dashboard'] = dashboard;
			});
		},

		setWidgetDefaults: function(defaults) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				defaults = $.extend({}, data['widget_defaults'], defaults);
				data['widget_defaults'] = defaults;
			});
		},

		addWidget: function(widget) {
			// If no fields are given, 'fields' will contain empty array instead of simple object.
			if (widget['fields'].length === 0) {
				widget['fields'] = {};
			}
			widget = $.extend({}, {
				'widgetid': '',
				'type': '',
				'header': '',
				'pos': {
					'x': 0,
					'y': 0,
					'width': 1,
					'height': 1
				},
				'rf_rate': 0,
				'preloader_timeout': 10000,	// in milliseconds
				'preloader_fadespeed': 500,
				'update_attempts': 0,
				'initial_load': true,
				'ready': false,
				'fields': {},
				'storage': {}
			}, widget);

			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				widget['uniqueid'] = generateUniqueId($this, data);
				widget['div'] = makeWidgetDiv(data, widget).data('widget-index', data['widgets'].length);
				updateWidgetDynamic($this, data, widget);

				data['widgets'].push(widget);
				$this.append(widget['div']);

				setDivPosition(widget['div'], data, widget['pos'], false);
				checkWidgetOverlap($this, data, widget);

				resizeDashboardGrid($this, data);

				showPreloader(widget);
				data.new_widget_placeholder.hide();
			});
		},

		setWidgetRefreshRate: function(widgetid, rf_rate) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (widget['widgetid'] == widgetid) {
						widget['rf_rate'] = rf_rate;
						startWidgetRefresh($this, data, widget);
					}
				});
			});
		},

		refreshWidget: function(widgetid) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (widget['widgetid'] == widgetid || widget['uniqueid'] === widgetid) {
						refreshWidget($this, data, widget);
					}
				});
			});
		},

		setWidgetStorageValue: function(uniqueid, field, value) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (widget['uniqueid'] === uniqueid) {
						widget['storage'][field] = value;
					}
				});
			});
		},

		addWidgets: function(widgets) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(widgets, function(index, value) {
					methods.addWidget.apply($this, Array.prototype.slice.call(arguments, 1));
				});

				$.each(data['widgets'], function(index, value) {
					updateWidgetContent($this, data, value);
				});
			});
		},

		// Make widgets editable - Header icons, Resizeable, Draggable
		setModeEditDashboard: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				data['options']['edit_mode'] = true;
				doAction('onEditStart', $this, data, null);
				dashboardRemoveMessages();
				setModeEditDashboard($this, data);
			});
		},

		// Save changes and remove editable elements from widget - Header icons, Resizeable, Draggable
		saveDashboardChanges: function() {
			return this.each(function() {
				var	$this = $(this),
					ul = $('#dashbrd-config').closest('ul'),
					data = $this.data('dashboardGrid');

				$('#dashbrd-save', ul).prop('disabled', true);
				doAction('beforeDashboardSave', $this, data, null);
				saveChanges($this, data);
			});
		},

		// Discard changes and remove editable elements from widget - Header icons, Resizeable, Draggable
		cancelEditDashboard: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid'),
					current_url = new Curl(location.href),
					url = new Curl('zabbix.php');

				// Don't show warning about existing updates
				data['options']['updated'] = false;

				url.unsetArgument('sid');
				url.setArgument('action', 'dashboard.view');
				if (current_url.getArgument('dashboardid')) {
					url.setArgument('dashboardid', current_url.getArgument('dashboardid'));
				}

				// Redirect to last active dashboard.
				// (1) In case of New Dashboard from list, it will open list
				// (2) In case of New Dashboard or Clone Dashboard from other dashboard, it will open that dashboard
				// (3) In case of simple editing of current dashboard, it will reload same dashboard
				location.replace(url.getUrl());
			});
		},

		// After pressing "Edit" button on widget
		editWidget: function(widget, trigger_elmnt) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				openConfigDialogue($this, data, widget, trigger_elmnt);
			});
		},

		// After pressing "delete" button on widget
		deleteWidget: function(widget) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				deleteWidget($this, data, widget);
			});
		},

		/*
		 * Add or update form on widget configuration dialogue (when opened, as well as when requested by 'onchange'
		 * attributes in form itself).
		 */
		updateWidgetConfigDialogue: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid'),
					body = data.dialogue['body'],
					footer = $('.overlay-dialogue-footer', data.dialogue['div']),
					header = $('.dashbrd-widget-head', data.dialogue['div']),
					form = $('form', body),
					widget = data.dialogue['widget'], // widget currently being edited
					url = new Curl('zabbix.php'),
					ajax_data = {},
					fields;

				// Disable saving, while form is being updated.
				$('.dialogue-widget-save', footer).prop('disabled', true);

				url.setArgument('action', 'dashboard.widget.edit');

				if (form.length) {
					// Take values from form.
					fields = form.serializeJSON();
					ajax_data['type'] = fields['type'];
					delete fields['type'];

					if (data.dialogue['widget_type'] === ajax_data['type']) {
						ajax_data['name'] = fields['name'];
						delete fields['name'];
					}
					else {
						// Get default config if widget type changed.
						fields = {};
					}
				}
				else if (widget !== null) {
					// Open form with current config.
					ajax_data['type'] = widget['type'];
					ajax_data['name'] = widget['header'];
					fields = widget['fields'];
				}
				else {
					// Get default config for new widget.
					fields = {};
				}

				data.dialogue['widget_type'] = ajax_data['type'];

				if (Object.keys(fields).length != 0) {
					ajax_data['fields'] = JSON.stringify(fields);
				}

				jQuery.ajax({
					url: url.getUrl(),
					method: 'POST',
					data: ajax_data,
					dataType: 'json',
					beforeSend: function() {
						body.empty()
							.append($('<div>')
								// The smallest possible size of configuration dialog.
								.css({
									'width': '544px',
									'height': '68px',
									'max-width': '100%'
								})
								.append($('<div>')
									.addClass('preloader-container')
									.append($('<div>').addClass('preloader'))
								));
					},
					success: function(resp) {
						body.empty();
						body.append(resp.body);
						if (typeof(resp.debug) !== 'undefined') {
							body.append(resp.debug);
						}
						if (typeof(resp.messages) !== 'undefined') {
							body.append(resp.messages);
						}

						body.find('form').attr('aria-labeledby', header.find('h4').attr('id'));

						// Change submit function for returned form.
						$('#widget_dialogue_form', body).on('submit', function(e) {
							e.preventDefault();
							updateWidgetConfig($this, data, widget);
						});

						// Enable save button after successful form update.
						$('.dialogue-widget-save', footer).prop('disabled', false);
					},
					complete: function() {
						if (data.dialogue['widget_type'] === 'svggraph') {
							jQuery('[data-dialogueid="widgetConfg"]').addClass('sticked-to-top');
						}
						else {
							jQuery('[data-dialogueid="widgetConfg"]').removeClass('sticked-to-top');
						}

						overlayDialogueOnLoad(true, jQuery('[data-dialogueid="widgetConfg"]'));
					}
				});
			});
		},

		// Returns list of widgets filterd by key=>value pair
		getWidgetsBy: function(key, value) {
			var widgets_found = [];
			this.each(function() {
				var	$this = $(this),
						data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (typeof widget[key] !== 'undefined' && widget[key] === value) {
						widgets_found.push(widget);
					}
				});
			});

			return widgets_found;
		},

		// Register widget as data receiver shared by other widget
		registerDataExchange: function(obj) {
			return this.each(function() {
				var $this = $(this),
					data = $this.data('dashboardGrid');

				data['widget_relation_submissions'].push(obj);
			});
		},

		registerDataExchangeCommit: function() {
			return this.each(function() {
				var $this = $(this),
					used_indexes = [],
					data = $this.data('dashboardGrid'),
					erase;

				if (data['widget_relation_submissions'].length
						&& !data['widgets'].filter(function(widget) {return !widget['ready']}).length) {
					$.each(data['widget_relation_submissions'], function(rel_index, rel) {
						erase = false;

						// No linked widget reference given. Just register as data receiver.
						if (typeof rel.linkedto === 'undefined') {
							if (typeof data['widget_relations']['tasks'][rel.uniqueid] === 'undefined') {
								data['widget_relations']['tasks'][rel.uniqueid] = [];
							}

							data['widget_relations']['tasks'][rel.uniqueid].push({
								data_name: rel.data_name,
								callback: rel.callback
							});
							erase = true;
						}
						/*
						 * Linked widget reference is given. Register two direction relationship as well as
						 * register data receiver.
						 */
						else {
							$.each(data['widgets'], function(index, widget) {
								if (typeof widget['fields']['reference'] !== 'undefined'
										&& widget['fields']['reference'] === rel.linkedto) {
									if (typeof data['widget_relations']['relations'][widget.uniqueid] === 'undefined') {
										data['widget_relations']['relations'][widget.uniqueid] = [];
									}
									if (typeof data['widget_relations']['relations'][rel.uniqueid] === 'undefined') {
										data['widget_relations']['relations'][rel.uniqueid] = [];
									}
									if (typeof data['widget_relations']['tasks'][rel.uniqueid] === 'undefined') {
										data['widget_relations']['tasks'][rel.uniqueid] = [];
									}

									data['widget_relations']['relations'][widget.uniqueid].push(rel.uniqueid);
									data['widget_relations']['relations'][rel.uniqueid].push(widget.uniqueid);
									data['widget_relations']['tasks'][rel.uniqueid].push({
										data_name: rel.data_name,
										callback: rel.callback
									});
									erase = true;
								}
							});
						}

						if (erase) {
							used_indexes.push(rel_index);
						}
					});

					for (var i = used_indexes.length - 1; i >= 0; i--) {
						data['widget_relation_submissions'].splice(used_indexes[i], 1);
					}

					methods.callWidgetDataShare.call($this);
				}
			});
		},

		/**
		 * Pushes received data in data buffer and calls sharing method.
		 *
		 * @param object widget  data origin widget
		 * @param string data_name  string to identify data shared
		 *
		 * @returns boolean		indicates either there was linked widget that was related to data origin widget
		 */
		widgetDataShare: function(widget, data_name) {
			var args = Array.prototype.slice.call(arguments, 2),
				uniqueid = widget['uniqueid'],
				ret = true;

			if (!args.length) {
				return false;
			}

			this.each(function() {
				var $this = $(this),
					data = $this.data('dashboardGrid'),
					indx = -1;

				if (typeof data['widget_relations']['relations'][widget['uniqueid']] === 'undefined'
						|| data['widget_relations']['relations'][widget['uniqueid']].length == 0) {
					ret = false;
				}

				if (typeof data['data_buffer'][uniqueid] === 'undefined') {
					data['data_buffer'][uniqueid] = [];
				}
				else if (typeof data['data_buffer'][uniqueid] !== 'undefined') {
					$.each(data['data_buffer'][uniqueid], function(i, arr) {
						if (arr['data_name'] === data_name) {
							indx = i;
						}
					});
				}

				if (indx === -1) {
					data['data_buffer'][uniqueid].push({
						data_name: data_name,
						args: args,
						old: []
					});
				}
				else {
					if (data['data_buffer'][uniqueid][indx]['args'] !== args) {
						data['data_buffer'][uniqueid][indx]['args'] = args;
						data['data_buffer'][uniqueid][indx]['old'] = [];
					}
				}

				methods.callWidgetDataShare.call($this);
			});

			return ret;
		},

		callWidgetDataShare: function($obj) {
			return this.each(function() {
				var $this = $(this),
					data = $this.data('dashboardGrid');

				for (var src_uniqueid in data['data_buffer']) {
					if (typeof data['data_buffer'][src_uniqueid] === 'object') {
						$.each(data['data_buffer'][src_uniqueid], function(index, buffer_data) {
							if (typeof data['widget_relations']['relations'][src_uniqueid] !== 'undefined') {
								$.each(data['widget_relations']['relations'][src_uniqueid], function(index,
										dest_uid) {
									if (buffer_data['old'].indexOf(dest_uid) == -1) {
										if (typeof data['widget_relations']['tasks'][dest_uid] !== 'undefined') {
											var widget = methods.getWidgetsBy.call($this, 'uniqueid', dest_uid);
											if (widget.length) {
												$.each(data['widget_relations']['tasks'][dest_uid], function(i, task) {
													if (task['data_name'] === buffer_data['data_name']) {
														task.callback.apply($obj, [widget[0], buffer_data['args']]);
													}
												});

												buffer_data['old'].push(dest_uid);
											}
										}
									}
								});
							}
						});
					}
				}
			});
		},

		makeReference: function() {
			var ref = false;

			this.each(function() {
				var data = $(this).data('dashboardGrid');

				while (!ref) {
					ref = generateRandomString(5);

					for (var i = 0, l = data['widgets'].length; l > i; i++) {
						if (typeof data['widgets'][i]['fields']['reference'] !== 'undefined') {
							if (data['widgets'][i]['fields']['reference'] === ref) {
								ref = false;
								break;
							}
						}
					}
				}
			});

			return ref;
		},

		addNewWidget: function(trigger_elmnt, pos) {
			var widget = (pos && 'x' in pos && 'y' in pos) ? {pos: pos} : null;

			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				openConfigDialogue($this, data, widget, trigger_elmnt);
			});
		},

		isEditMode: function() {
			var response = false;

			this.each(function() {
				response = $(this).data('dashboardGrid')['options']['edit_mode'];
			});

			return response;
		},

		/**
		 * Add action, that will be performed on $hook_name trigger
		 *
		 * @param string hook_name  name of trigger, when $function_to_call should be called
		 * @param string function_to_call  name of function in global scope that will be called
		 * @param string uniqueid  identifier of widget, that added this action
		 * @param array options  any key in options is optional
		 * @param array options['parameters']  array of parameters with which the function will be called
		 * @param array options['grid']  mark, what data from grid should be passed to $function_to_call.
		 *								If is empty, parameter 'grid' will not be added to function_to_call params.
		 * @param string options['grid']['widget']  should contain 1. Will add widget object.
		 * @param string options['grid']['data']  should contain '1'. Will add dashboard grid data object.
		 * @param string options['grid']['obj']  should contain '1'. Will add dashboard grid object ($this).
		 * @param int options['priority']  order, when it should be called, compared to others. Default = 10
		 * @param int options['trigger_name']  unique name. There can be only one trigger with this name for each hook.
		 */
		addAction: function(hook_name, function_to_call, uniqueid, options) {
			this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid'),
					found = false,
					trigger_name = null;

				if (typeof(data['triggers'][hook_name]) === 'undefined') {
					data['triggers'][hook_name] = [];
				}

				// add trigger with each name only once
				if (typeof(options['trigger_name']) !== 'undefined') {
					trigger_name = options['trigger_name'];
					$.each(data['triggers'][hook_name], function(index, trigger) {
						if (typeof(trigger['options']['trigger_name']) !== 'undefined'
							&& trigger['options']['trigger_name'] === trigger_name)
						{
							found = true;
						}
					});
				}

				if (!found) {
					data['triggers'][hook_name].push({
						'function': function_to_call,
						'uniqueid': uniqueid,
						'options': options
					});
				}
			});
		}
	}

	$.fn.dashboardGrid = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else if (typeof method === 'object' || !method) {
			return methods.init.apply(this, arguments);
		}
		else {
			$.error('Invalid method "' +  method + '".');
		}
	}
}(jQuery));
