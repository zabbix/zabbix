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

	function resizeDashboardGrid($obj, data, min_rows) {
		data['options']['rows'] = 0;

		$.each(data['widgets'], function() {
			if (this['pos']['y'] + this['pos']['height'] > data['options']['rows']) {
				data['options']['rows'] = this['pos']['y'] + this['pos']['height'];
			}
		});

		if (typeof(min_rows) != 'undefined' && data['options']['rows'] < min_rows) {
			data['options']['rows'] = min_rows;
		}

		$obj.css({'height': '' + (data['options']['widget-height'] * data['options']['rows']) + 'px'});

		if (data['options']['rows'] == 0) {
			data['empty_placeholder'].show();
		}
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
	 * Rearrange widgets.
	 * TODO: fix step 2 collapse part.
	 *
	 * @param {array}  data    Array of widgets objects.
	 * @param {object} widget  Moved widget object.
	 */
	function realignResizeStateless(data, widget) {
		var changes = {};

		// Fill changes object with difference between current_pos and pos. Do not set not changed values.
		$.each(widget.current_pos, function(k, v) {
			if (widget.pos[k] != v) {
				changes[k] = v - widget.pos[k];
			}
		});

		// If there are no affected widgets restore initial dimensions.
		if ('width' in changes == false && 'height' in changes == false) {
			resetCurrentPositions(data['widgets']);
			return;
		}

		/**
		 * Same code will be used for X and Y axes.
		 */
		var axis_key = 'x',
			size_key = 'width',
			size_min = 1,
			size_max = 12;

		// Do horizontal resize.
		if ('width' in changes) {
			var max_width = size_max,
				max_height = 1,
				pos = {
					x: widget.current_pos.x,
					width: widget.current_pos.width,
					y: widget.pos.y,
					height: widget.pos.height
				},
				getAffectedTreeAsArray = function (pos) {
					$.map(data.widgets, function(box) {
						return !('current_pos' in box) && rectOverlap(pos, box.pos) ? box : null;
					}).each(function (box) {
						if ('current_pos' in box) {
							return;
						}

						box.current_pos = $.extend({}, box.pos);

						var boundary = {
							y: box.pos.y,
							height: box.pos.height
						};

						if ('x' in changes) {
							boundary.x = Math.max(0, box.pos.x - changes.width);
							boundary.width = box.pos.x - changes.width < 0
								? box.pos.width
								: box.pos.width + changes.width;
						}
						else {
							boundary.x = box.pos.x;
							boundary.width = box.pos.width + changes.width;
						}

						getAffectedTreeAsArray(boundary);
					});

					return $.map(data.widgets, function (box) {
						return 'current_pos' in box && box.uniqueid != widget.uniqueid ? box : null;
					});
				},
				affected = getAffectedTreeAsArray(pos)
					.sort(function (box1, box2) {
						return 'x' in changes
							? (box2.current_pos.x + box2.current_pos.width) - (box1.current_pos.x + box1.current_pos.width)
							: box1.current_pos.x - box2.current_pos.x;
					})
					.each(function (box) {
						max_height = Math.max(max_height, box.current_pos.y + box.current_pos.height);
						box.div.css('background-color', 'red');
						box.div.css('opacity', '0.5');
					}),
				getAffectedInBounds = function (bounds) {
					return $.map(affected, function (box) {
						return rectOverlap(bounds, box.current_pos) ? box : null;
					});
				}

			// debug code.
			// affected[0] && affected[0].div.css('background-color', 'maroon');

			/**
			 * 1. Move widgets.
			 */
			var margins = {},
				axis_pos = $.extend(widget.current_pos);

			// Is Alice in mirrorland?
			if (axis_key in changes) {
				affected.each(function (box) {
					box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
				});
				axis_pos[axis_key] = size_max - axis_pos[axis_key] - axis_pos[size_key];
			}

			// Move widget and it affected siblings. Everybody knows what TETЯIS is.
			var new_max = 0,
				opposite_axis_key = axis_key == 'x' ? 'y' : 'x',
				opposite_size_key = size_key == 'width' ? 'height' : 'width';

			affected.each(function (box) {
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
					//box.div.css('background-color', 'rgba(0, 0, 255, 0.1)');
					box.div.css('opacity', '1');
				}
			});

			/**
			 * 2. Resize.
			 */
			if (new_max > size_max) {
				var scanline = {
						x: axis_pos[axis_key] + axis_pos[size_key],
						width: 1,
						y: 0,
						height: max_height
					},
					delta = 0,
					slots = [getAffectedInBounds(scanline)],
					i = 1;
				scanline[axis_key] += 1
				while (scanline[axis_key] < new_max) {
					slots.push(getAffectedInBounds(scanline));

					var can_move = true,
						can_collapse = new_max > size_max + delta;

					$.each(slots[i], function (_, box) {
						box.new_pos = $.extend({}, box.current_pos);
						box.new_pos[axis_key] -= delta + (can_collapse ? 1 : 0);
						//box.content_header.html('delta: '+delta);

						if (can_collapse) {
							$.each(slots[i - 1], function (_, box1) {
								if (rectOverlap(box1.current_pos, box.new_pos)) {
									if (box1.current_pos[size_key] > size_min) {
										box1.new_pos = $.extend({}, box1.current_pos);
										box1.new_pos[size_key] -= 1;
										box1.div.css('background-color', 'rgba(255, 0, 255, 0.2)');
									}
									else {
										can_move = false;
									}
								}

								return can_move;
							});
						}
						else {
							if (scanline[axis_key] != box.current_pos[axis_key]
									&& box.current_pos[size_key] > size_min) {
								/*
								 * Slots can contain multiple entries for same widget when widget size is greater
								 * than 1, such widget should be moved only once.
								 */
								box.new_pos = $.extend({}, box.current_pos);
							}
						}

						return can_move;
					});

					if (can_move) {
						// debug code
						// TODO: remove!
						// console['log']('will move, delta='+delta+', collapse='+can_collapse,
						// 	JSON.parse(JSON.stringify(slots[i]))
						// );
						delta += can_collapse ? 1 : 0;

						slots[i].each(function (box) {
							slots[i - 1].push(box);
						});
						slots[i - 1].each(function (box) {
							if ('new_pos' in box) {
								box.current_pos = box.new_pos;
								delete box.new_pos;
								box.div.css('background-color', can_collapse
									? 'rgba(0, 255, 0, 0.5)'
									: 'rgba(0, 255, 0, 0.1)');
								// box.content_header.html(JSON.stringify({delta: delta, new_max:new_max}));
							}
						});
						// slots.pop();
						// i -= 1;
						slots[i] = [];
					}
					else {
						// TODO: remove!
						console['log']('  no move, delta='+delta+', collapse='+can_collapse,
							JSON.parse(JSON.stringify(slots[i]))
						);

						slots[i].each(function (box) {
							'new_pos' in box && box.div.css('background-color', 'rgba(0, 0, 0, 0.2)');
							delete box.new_pos;
						});
						slots[i - 1].each(function (box) {
							'new_pos' in box && box.div.css('background-color', 'rgba(0, 0, 0, 0.2)');
							delete box.new_pos;
						});
						//i += 1;
					}

					i += 1;
					scanline[axis_key] += 1;
				}
			}

			/**
			 * 3. Move widget to best available 'fit' position when it is impossible to fit widgets in to desired
			 *    boundary box.
			 * TODO: remove false when step 2 collapse will work.
			 */
			if (false && new_max > size_max) {
				affected.each(function (box) {
					box.current_pos[axis_key] = Math.max(box.pos[axis_key],
						box.current_pos[axis_key] - new_max - size_max
					);
				});
			}

		}

		// Get Alice back.
		if (axis_key in changes) {
			affected.each(function (box) {
				box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
				// debug code
				// TODO: remove!
				if (box.current_pos[axis_key] < 0) {
					console.error(box.header+' outside bounds for '+axis_key, box.current_pos);
					box.current_pos[axis_key] = 0;
				}
			});
		}

		// Force to repaint non changed widgets too.
		$.each(data.widgets, function (i, box) {
			if ('current_pos' in box == false) {
				box.current_pos = $.extend({}, box.pos);
				box.div.css('opacity', '1');
			}
		});
	}

	/**
	 * TODO: remove when realignResizeStateless step 2 will work.
	 * Rearrange widgets on resize operation.
	 *
	 * @param {array}  data    Array of widgets objects.
	 * @param {object} widget  Moved widget object.
	 */
	function realignResize(data, widget) {
		var changes = {},
			max_width = 11,
			max_height = 12,
			min_width = 1,
			min_height = 2,
			pos_changed = false,
			resized = [],
			sortHandler,
			success;

		$.each(widget.current_pos, function(index, val) {
			val -= widget.previous_pos[index];

			if (val !== 0) {
				changes[index] = val;
				pos_changed = true;
			}
		});

		if (!pos_changed) {
			// TODO: remove!
			console['log']('no position changes? '+widget.header, widget);
			return false;
		}

		$.map(data.widgets, function(box) {
			box.restore_pos = $.extend({}, box.current_pos);
		});

		// TODO: diagonal resize should be separated into two steps.
		var sort = {
			Xdesc: function (box1, box2) {
				return box2.current_pos.x - box1.current_pos.x;
			},
			Xasc: function (box1, box2) {
				return box1.current_pos.x - box2.current_pos.x;
			},
			Ydesc: function (box1, box2) {
				return box2.current_pos.y - box1.current_pos.y;
			},
			Yasc: function (box1, box2) {
				return box1.current_pos.y - box2.current_pos.y;
			}
		};

		if ('width' in changes) {
			sortHandler = 'x' in changes ? sort.Xdesc : sort.Xasc;
		}
		else if ('height' in changes) {
			sortHandler = 'y' in changes ? sort.Ydesc : sort.Yasc;
		}

		var realignMove = function (widget) {
				var dbg = $.map(data.widgets, function (box) {
					return (widget.uniqueid != box.uniqueid && rectOverlap(widget.current_pos, box.current_pos))
						? box : null
				})
				.sort(sortHandler)
				.each(function (box) {
					// Horizontal move.
					if ('x' in changes || 'width' in changes) {
						box.current_pos.x += 'x' in changes ? changes.x : changes.width;

						if (box.current_pos.x + box.current_pos.width > max_width || box.current_pos.x < 0) {
							success = false;
							return false;
						}
					}

					// Vertical move.
					if ('y' in changes || 'height' in changes) {
						box.current_pos.y += 'y' in changes ? changes.y : changes.height;

						if (box.current_pos.y + box.current_pos.height > max_height|| box.current_pos.y < 0) {
							success = false;
							return false;
						}
					}

					// Stop processing if box moving attempt failed.
					return realignMove(box);
				});

				return success;
			},
			realignResize = function (widget, resize) {
				$.map(data.widgets, function (box) {
					return (widget.uniqueid != box.uniqueid && rectOverlap(widget.current_pos, box.current_pos))
						? box : null
				})
				.sort(sortHandler)
				.each(function (box) {
					var x, width,
						y, height;
					// Horizontal move and resize.
					if ('width' in changes) {
						x = box.current_pos.x + ('x' in changes ? changes.x : changes.width);
						width = resize ? box.current_pos.width - changes.width : box.current_pos.width;

						if ((x < 0 || x + width > max_width) && width < min_width) {
							// We can not move nor resize sibling. Stop processing of all other branches.
							success = false;
							return false;
						}

						// box.current_pos.x = x > 0 ? x : 0;
						width = width < min_width ? min_width : width;

						// if (resize && width != box.current_pos.width) {
						resize = true;
						if (width != box.current_pos.width) {
							resized.push({
								uniqueid: box.uniqueid,
								x: x > 0 ? x : 0,
								width: box.current_pos.width
							});

							if (x + width > max_width) {
								box.current_pos.width = width;
								resize = false;
							}
							else {
								// For resize to right side set new x position.
								if ('x' in changes == false) {
									box.current_pos.x = x > 0 ? x : 0;
								}
								// Resize only if there will be overlap.
								$.each(data.widgets, function() {
									if (this.uniqueid != box.uniqueid && rectOverlap(box.current_pos, this.current_pos)) {
										box.current_pos.width = width;
										resize = false;
										return false;
									}
								});
							}

							if (!resize && 'x' in changes) {
								// For successfull resize to left side do not change x position.
								// Ignore overlap check for children of resized sibling.
								return true;
							}
						}

						box.current_pos.x = x > 0 ? x : 0;
					}

					// Vertical move and resize.
					if ('height' in changes) {
						y = box.current_pos.y + ('y' in changes ? changes.y : changes.height);
						height = resize ? box.current_pos.height - changes.height : box.current_pos.height;

						if (y < 0 && height < min_height) {
							// We can not move nor resize sibling. Stop processing of all other branches.
							success = false;
							return false;
						}

						//box.current_pos.y = y > 0 ? y : 0;
						height = height < min_height ? min_height : height;

						resize = true;
						if (resize && height != box.current_pos.height) {
							resized.push({
								uniqueid: box.uniqueid,
								y: y > 0 ? y : 0,
								height: box.current_pos.height
							});

							if (y + height > max_height) {
								box.current_pos.height = height;
								resize = false;
							}
							else {
								// For resize to bottom side set new y position.
								if ('y' in changes == false) {
									box.current_pos.y = y > 0 ? y : 0;
								}
								// Resize only if there will be overlap.
								$.each(data.widgets, function() {
									if (this.uniqueid != box.uniqueid && rectOverlap(box.current_pos, this.current_pos)) {
										box.current_pos.height = height;
										resize = false;
										return false;
									}
								});
							}

							if (!resize && 'y' in changes) {
								// For successfull resize to top side do not change y position.
								// Ignore overlap check for resized sibling.
								return true;
							}
						}

						box.current_pos.y = y > 0 ? y : 0;
					}
					return realignResize(box, resize);
				});
				return success;
			};

		success = true;
		// attempt to move first.
		if (realignMove(widget)) {
			return true;
		}

		$.map(data.widgets, function(box) {
			box.current_pos = $.extend({}, box.restore_pos);
		});

		success = true;
		// attempt resize and move.
		if (realignResize(widget, true)) {
			// When resized widgets count is more than 1, check is the resize operation valid and should not be reverted.
			if (resized.length) {
				$.each(resized, function(_, pos) {
					var widget,
						widget_pos;

					$.each(data.widgets, function(i, box) {
						if (box.uniqueid == pos.uniqueid) {
							widget = box;
							return false;
						}
					});

					widget_pos = $.extend({}, widget.current_pos);
					widget.current_pos = $.extend(widget.current_pos, pos);

					if (widget.current_pos.x + widget.current_pos.width > max_width) {
						// Widget will be outside allowed area.
						widget.current_pos = widget_pos;
					}
					else {
						$.each(data.widgets, function(i, box) {
							if (box.uniqueid != pos.uniqueid && rectOverlap(widget.current_pos, box.current_pos)) {
								widget.current_pos = widget_pos;
								return false;
							}
						});
					}
				});
			}

			return true;
		}

		$.map(data.widgets, function(box) {
			box.current_pos = box.restore_pos;
			delete box.restore_pos;
		});

		return false;
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

		setDivPosition(data['placeholder'], data, pos, true);

		if (!posEquals(pos, widget['current_pos'])) {
			$.each(data.widgets, function(i, box) {
				box.div.css('background-color', '');
				delete box.current_pos;
			});
			widget['current_pos'] = pos;
			realignResizeStateless(data, widget);

			$.each(data['widgets'], function() {
				if (widget.uniqueid != this.uniqueid && 'current_pos' in this) {
					setDivPosition(this['div'], data, this['current_pos'], false);
				}
			});
		}
		return;

		// This part is used by doWidgetResize
		// TODO: remove
		if (!posEquals(pos, widget['current_pos'])) {
			widget['current_pos'] = pos;
			// TODO: envoke realignResize separately for x and y changes.

			if (realignResize(data, widget)) {
				delete widget.freeze_pos;
				widget.previous_pos = $.extend({}, pos);
				setDivPosition(data['placeholder'], data, pos, true);

				$.each(data['widgets'], function() {
					if (widget.uniqueid != this.uniqueid) {
						setDivPosition(this['div'], data, this['current_pos'], false);
					}
				});
			}
			else {
				widget.current_pos = $.extend({}, widget.previous_pos);
				widget.freeze_pos = $.extend({}, widget.previous_pos);
				pos = widget.freeze_pos;
				setDivPosition($div, data, widget.freeze_pos, false);
				setDivPosition(data['placeholder'], data, widget.freeze_pos, true);
			}
		}

		// TODO: envoke only on vertical resize!
		// var min_rows = 0;

		// $.each(data['widgets'], function() {
		// 	var rows = this['current_pos']['y'] + this['current_pos']['height'];

		// 	if (min_rows < rows) {
		// 		min_rows = rows;
		// 	}
		// });

		// if (data['options']['rows'] < min_rows) {
		// 	resizeDashboardGrid($obj, data, min_rows);
		// }
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

		var min_rows = 0;

		$.each(data['widgets'], function() {
			var rows = this['current_pos']['y'] + this['current_pos']['height'];

			if (min_rows < rows) {
				min_rows = rows;
			}
		});

		if (data['options']['rows'] < min_rows) {
			resizeDashboardGrid($obj, data, min_rows);
		}
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
			scroll: false,
			start: function(event, ui) {
				data['pos-action'] = 'drag';
				startWidgetPositioning($(event.target), data);
			},
			drag: function(event, ui) {
				doWidgetPositioning($obj, $(event.target), data);
			},
			stop: function(event, ui) {
				stopWidgetPositioning($obj, $(event.target), data);
			}
		});
	}

	function stopDraggable($obj, data, widget) {
		widget['content_header']
			.removeClass('cursor-move');

		widget['div'].draggable("destroy");
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
			minWidth: getCurrentCellWidth(data),
			start: function(event, ui) {
				var widget = getWidgetByTarget(data['widgets'], $(event.target));
				// Is used only by realignResize method.
				// TODO: remove
				widget.previous_pos = widget.pos;
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
			};

		delete fields['type'];
		delete fields['name'];

		url.setArgument('action', 'dashboard.widget.check');

		if (Object.keys(fields).length != 0) {
			ajax_data['fields'] = JSON.stringify(fields);
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

					if (widget === null) {
						// In case of ADD widget, create widget with required selected fields and add it to dashboard.
						var pos = findEmptyPosition($obj, data, type),
							scroll_by = (pos['y'] * data['options']['widget-height'])
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
	 * Creates div for empty dashboard.
	 *
	 * @param {object} $obj     Dashboard grid object.
	 * @param {object} options  Dashboard options (will be put in data['options'] in dashboard grid).
	 *
	 * @return {object}         jQuery <div> object for placeholder.
	 */
	function emptyPlaceholderDiv($obj, options) {
		var $div = $('<div>', {'class': 'dashbrd-grid-empty-placeholder'}),
			$text = $('<h1>');

		if (options['editable']) {
			if (options['kioskmode']) {
				$text.text(t('Cannot add widgets in kiosk mode'));
			}
			else {
				$text.append(
					$('<a>', {'href':'#'})
						.text(t('Add a new widget'))
						.click(function(e){
							// To prevent going by href link.
							e.preventDefault();

							if (!methods.isEditMode.call($obj)) {
								showEditMode();
							}

							methods.addNewWidget.call($obj, this);
						})
				);
			}
		}
		else {
			$text.addClass('disabled').text(t('Add a new widget'));
		}

		return $div.append($text);
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
					$placeholder = $('<div>', {'class': 'dashbrd-grid-widget-placeholder'}),
					$empty_placeholder = emptyPlaceholderDiv($this, options);

				$this.data('dashboardGrid', {
					dashboard: {},
					options: options,
					widgets: [],
					widget_defaults: {},
					triggers: {},
					placeholder: $placeholder,
					empty_placeholder: $empty_placeholder,
					widget_relation_submissions: [],
					widget_relations: {
						relations: [],
						tasks: {}
					},
					data_buffer: []
				});

				var	data = $this.data('dashboardGrid');

				$this.append($placeholder.hide());
				$this.append($empty_placeholder);

				$(window).bind('beforeunload', function() {
					var	res = confirmExit($this, data);

					// Return value only if we need confirmation window, return nothing otherwise.
					if (typeof res !== 'undefined') {
						return res;
					}
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
				data['empty_placeholder'].hide();

				data['widgets'].push(widget);
				$this.append(widget['div']);

				setDivPosition(widget['div'], data, widget['pos'], false);
				checkWidgetOverlap($this, data, widget);

				resizeDashboardGrid($this, data);

				showPreloader(widget);
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

		addNewWidget: function(trigger_elmnt) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				openConfigDialogue($this, data, null, trigger_elmnt);
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
