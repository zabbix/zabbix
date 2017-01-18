/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	function resizeDashboardGrid($obj, data, min_rows) {
		data['options']['rows'] = 0;

		$.each(data['widgets'], function() {
			if (this['row'] + this['height'] > data['options']['rows']) {
				data['options']['rows'] = this['row'] + this['height'];
			}
		});

		if (min_rows !== undefined && data['options']['rows'] < min_rows) {
			data['options']['rows'] = min_rows;
		}

		$obj.css({'height': '' + (data['options']['widget-height'] * data['options']['rows']) + 'px'});
	}

	function getWidgetByTarget(widgets, $div) {
		return widgets[$div.data('widget-id')];
	}

	function getDivPosition($obj, data, $div) {
		var	target_pos = $div.position(),
			widget_width_px = Math.floor($obj.width() / data['options']['columns']),
			target_top = target_pos.top + 25,
			target_left = target_pos.left + 25,
			target_height = $div.height() + data['options']['widget-height'] - 25,
			target_width = $div.width() + widget_width_px - 25,
			row = (target_top - (target_top % data['options']['widget-height'])) / data['options']['widget-height'],
			col = (target_left - (target_left % widget_width_px)) / widget_width_px,
			height = (target_height - (target_height % data['options']['widget-height'])) / data['options']['widget-height'],
			width = (target_width - (target_width % widget_width_px)) / widget_width_px;

		if (row < 0) {
			row = 0;
		}

		if (col > data['options']['columns'] - width) {
			col = data['options']['columns'] - width;
		}

		if (col < 0) {
			col = 0;
		}

		if (height < 1) {
			height = 1;
		}

		if (width < 1) {
			width = 1;
		}
		else if (width > data['options']['columns']) {
			width = data['options']['columns'];
		}

		return {'row': row, 'col': col, 'height': height, 'width': width};
	}

	function setDivPosition($div, data, pos) {
		$div.css({
			'top': '' + (data['options']['widget-height'] * pos['row']) + 'px',
			'left': '' + (data['options']['widget-width'] * pos['col']) + '%',
			'height': '' + (data['options']['widget-height'] * pos['height']) + 'px',
			'width': '' + (data['options']['widget-width'] * pos['width']) + '%'
		});
	}

	function startWidgetPositioning($div, data) {
		data['placeholder'].show();
		$div.addClass('dashbrd-grid-widget-draggable');
	}

	function doWidgetPositioning($obj, $div, data) {
		var	widget = getWidgetByTarget(data['widgets'], $div),
			pos = getDivPosition($obj, data, $div);

		setDivPosition(data['placeholder'], data, pos);

		if (data['options']['rows'] < pos.row + widget.height) {
			resizeDashboardGrid($obj, data, pos.row + widget.height);
		}
	}

	function stopWidgetPositioning($obj, $div, data) {
		var	widget = getWidgetByTarget(data['widgets'], $div),
			pos = getDivPosition($obj, data, $div),
			url = new Curl('zabbix.php');

		data['placeholder'].hide();
		$div.removeClass('dashbrd-grid-widget-draggable');

		url.setArgument('action', 'dashbrd.widget.update')
		url.setArgument('output', 'ajax');

		jQuery.ajax({
			url: url.getUrl(),
			type: 'post',
			data: {
				widgetid: widget.widgetid,
				row: pos.row,
				col: pos.col,
				height: pos.height,
				width: pos.width
			},
			dataType: 'script',
			success: function(result) {
				$.extend(widget, pos);
				setDivPosition($div, data, widget);
				resizeDashboardGrid($obj, data);
			},
			error: function() {
				// TODO: gentle message about failed saving of widget size and position
				setDivPosition($div, data, widget);
				resizeDashboardGrid($obj, data);
			},
		});
	}

	var	methods = {
		init: function(options) {
			options = $.extend({}, {columns: 12}, options);
			options['widget-height'] = 60;
			options['widget-width'] = 100 / options['columns'];
			options['rows'] = 0;

			return this.each(function() {
				var	$this = $(this),
					$placeholder = $('<div>', {'class': 'dashbrd-grid-widget-placeholder'});

				$this.data('dashboardGrid', {
					options: options,
					widgets: [],
					placeholder: $placeholder
				});

				$this.append($placeholder.hide());
			});
		},

		addWidget: function(params) {
			params = $.extend({}, {'row': 0, 'col': 0, 'height': 1, 'width': 1 }, params);

			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid'),
					$widget = $('<div>', {'class': 'dashbrd-grid-widget'})
						.data('widget-id', data['widgets'].length)
						.append($('<div>', {'class': 'dashbrd-grid-widget-content'}).text(params.widgetid)),	// TODO
					handles = {};

				$.each(['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw'], function(index, value) {
					var	$handle = $('<div>').addClass('ui-resizable-handle').addClass('ui-resizable-' + value);

					if ($.inArray(value, ['n', 'e', 's', 'w']) >= 0) {
						$handle
							.append($('<div>', {'class': 'ui-resize-dot'}))
							.append($('<div>', {'class': 'ui-resizable-border-' + value}));
					}

					$widget.append($handle);
					handles[value] = $handle;
				});

				data['widgets'].push(params);

				setDivPosition($widget, data, params);

				resizeDashboardGrid($this, data);

				$this.append($widget);

				$widget.draggable({
					start: function(event, ui) {
						startWidgetPositioning($(event.target), data);
					},
					drag: function(event, ui) {
						doWidgetPositioning($this, $(event.target), data);
					},
					stop: function(event, ui) {
						stopWidgetPositioning($this, $(event.target), data);
					}
				});

				$widget.resizable({
					handles: handles,
					autoHide: true,
					start: function(event, ui) {
						startWidgetPositioning($(event.target), data);
					},
					resize: function(event, ui) {
						doWidgetPositioning($this, $(event.target), data);
					},
					stop: function(event, ui) {
						stopWidgetPositioning($this, $(event.target), data);
					}
				});
			});
		},

		addWidgets: function(params) {
			return this.each(function() {
				var	$this = $(this);

				$.each(params, function(index, value) {
					methods.addWidget.apply($this, Array.prototype.slice.call(arguments, 1));
				});
			});
		}
	}

	$.fn.dashboardGrid = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || !method) {
			return methods.init.apply(this, arguments);
		} else {
			$.error('Invalid method "' +  method + '".');
		}
	}
}(jQuery));
