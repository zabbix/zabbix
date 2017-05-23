/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
			.append($('<h4>').text(widget['header']));
		widget['content_body'] = $('<div>')
			.addClass('dashbrd-grid-widget-content');
		widget['content_footer'] = $('<div>')
			.addClass('dashbrd-grid-widget-foot');
		widget['content_script'] = $('<div>');

		if (widget['rf_rate'] != 0) {
			widget['content_header'].append($('<ul>')
				.append($('<li>')
					.append($('<button>', {
						'type': 'button',
						'class': 'btn-widget-action',
						'data-menu-popup': JSON.stringify({
							'type': 'refresh',
							'widgetName': widget['widgetid'],
							'currentRate': widget['rf_rate'],
							'multiplier': false
						})
					}))
				)
			);
		}

		return $('<div>', {
			'class': 'dashbrd-grid-widget',
			'css': {
				'min-height': '' + data['options']['widget-height'] + 'px',
				'min-width': '' + data['options']['widget-width'] + '%'
			}
		})
			.append(
				$('<div>', {'class': 'dashbrd-grid-widget-padding'})
					.append(widget['content_header'])
					.append(widget['content_body'])
					.append(widget['content_footer'])
					.append(widget['content_script'])
			);
	}

	function resizeDashboardGrid($obj, data, min_rows) {
		data['options']['rows'] = 0;

		$.each(data['widgets'], function() {
			if (this['pos']['row'] + this['pos']['height'] > data['options']['rows']) {
				data['options']['rows'] = this['pos']['row'] + this['pos']['height'];
			}
		});

		if (typeof(min_rows) != 'undefined' && data['options']['rows'] < min_rows) {
			data['options']['rows'] = min_rows;
		}

		$obj.css({'height': '' + (data['options']['widget-height'] * data['options']['rows']) + 'px'});
	}

	function getWidgetByTarget(widgets, $div) {
		return widgets[$div.data('widget-index')];
	}

	function getDivPosition($obj, data, $div) {
		var	target_pos = $div.position(),
			widget_width_px = Math.floor($obj.width() / data['options']['columns']),
			target_top = target_pos.top + 25,
			target_left = target_pos.left + 25,
			target_height = $div.height() + 25,
			target_width = $div.width() + 25,
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

	function resetCurrentPositions(widgets) {
		for (var i = 0; i < widgets.length; i++) {
			widgets[i]['current_pos'] = $.extend({}, widgets[i]['pos']);
		}
	}

	function startWidgetPositioning($div, data) {
		data['placeholder'].show();

		$div.addClass('dashbrd-grid-widget-draggable');

		resetCurrentPositions(data['widgets']);
	}

	function posEquals(pos1, pos2) {
		var ret = true;

		$.each(['row', 'col', 'height', 'width'], function(index, key) {
			if (pos1[key] !== pos2[key]) {
				ret = false;
				return false;
			}
		});

		return ret;
	}

	function rectOverlap(pos1, pos2) {
		return ((pos1['row'] >= pos2['row'] && pos2['row'] + pos2['height'] - 1 >= pos1['row']) ||
			(pos2['row'] >= pos1['row'] && pos1['row'] + pos1['height'] - 1 >= pos2['row'])) &&
			((pos1['col'] >= pos2['col'] && pos2['col'] + pos2['width'] - 1 >= pos1['col']) ||
			(pos2['col'] >= pos1['col'] && pos1['col'] + pos1['width'] - 1 >= pos2['col']));
	}

	function realignWidget($obj, data, widget) {
		var to_row = widget['current_pos']['row'] + widget['current_pos']['height'],
			overlapped_widgets = [];

		$.each(data['widgets'], function() {
			if (widget != this && rectOverlap(widget['current_pos'], this['current_pos'])) {
				overlapped_widgets.push(this);
			}
		});

		overlapped_widgets.sort(function (widget1, widget2) {
			return widget2['current_pos']['row'] - widget1['current_pos']['row'];
		});

		for (var i = 0; i < overlapped_widgets.length; i++) {
			overlapped_widgets[i]['current_pos']['row'] = to_row;

			realignWidget($obj, data, overlapped_widgets[i]);
		}
	}

	function checkWidgetOverlap($obj, data, widget) {
		resetCurrentPositions(data['widgets']);
		realignWidget($obj, data, widget);

		$.each(data['widgets'], function() {
			if (!posEquals(this['pos'], this['current_pos'])) {
				this['pos'] = this['current_pos'];
				setDivPosition(this['div'], data, this['pos']);
			}

			delete this['current_pos'];
		});
	}

	function doWidgetPositioning($obj, $div, data) {
		var	widget = getWidgetByTarget(data['widgets'], $div),
			pos = getDivPosition($obj, data, $div);

		setDivPosition(data['placeholder'], data, pos);

		if (!posEquals(pos, widget['current_pos'])) {
			resetCurrentPositions(data['widgets']);
			widget['current_pos'] = pos;

			realignWidget($obj, data, widget);

			$.each(data['widgets'], function() {
				if (widget != this) {
					setDivPosition(this['div'], data, this['current_pos']);
				}
			});
		}

		var min_rows = 0;

		$.each(data['widgets'], function() {
			var rows = this['current_pos']['row'] + this['current_pos']['height'];

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

		$div.removeClass('dashbrd-grid-widget-draggable');

		$.each(data['widgets'], function() {
			// Check if position of widget changed
			var new_pos = this['current_pos'],
				old_pos = this['pos'],
				changed = false;

			$.each(['row','col','height','width'], function(index, value) {
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
		setDivPosition($div, data, widget['pos']);
		resizeDashboardGrid($obj, data);
	}

	function makeDraggable($obj, data, widget) {
		widget['content_header']
			.addClass('cursor-move');

		widget['div'].draggable({
			handle: widget['content_header'],
			start: function(event, ui) {
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
			start: function(event, ui) {
				startWidgetPositioning($(event.target), data);
			},
			resize: function(event, ui) {
				doWidgetPositioning($obj, $(event.target), data);
			},
			stop: function(event, ui) {
				stopWidgetPositioning($obj, $(event.target), data);
			}
		});
	}

	function stopResizable($obj, data, widget) {
		widget['div'].resizable("destroy");
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
			widget['content_footer'].fadeTo(widget['preloader_fadespeed'], 0.4);
		}, widget['preloader_timeout']);
	}

	function stopPreloader(widget) {
		if (typeof(widget['preloader_timeoutid']) != 'undefined') {
			clearTimeout(widget['preloader_timeoutid']);
			delete widget['preloader_timeoutid'];
		}

		hidePreloader(widget);
		widget['content_body'].fadeTo(0, 1);
		widget['content_footer'].fadeTo(0, 1);
	}

	function startWidgetRefreshTimer(widget, rf_rate) {
		if (rf_rate != 0) {
			widget['rf_timeoutid'] = setTimeout(function () { updateWidgetContent(widget); }, rf_rate * 1000);
		}
	}

	function startWidgetRefresh(widget) {
		if (typeof(widget['rf_timeoutid']) != 'undefined') {
			clearTimeout(widget['rf_timeoutid']);
			delete widget['rf_timeoutid'];
		}

		startWidgetRefreshTimer(widget, widget['rf_rate']);
	}

	function updateWidgetContent(widget) {
		if (++widget['update_attempts'] > 1) {
			return;
		}

		var url = new Curl('zabbix.php'),
			ajax_data = {};

		url.setArgument('action', 'widget.' + widget['type'] + '.view');

		ajax_data['widgetid'] = widget['widgetid'];
		// display widget with yet unsaved changes
		if (typeof widget['fields'] !== 'undefined') {
			ajax_data['fields'] = widget['fields'];
		}

		startPreloader(widget);

		jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: ajax_data,
			dataType: 'json',
			success: function(resp) {
				stopPreloader(widget);

				$('h4', widget['content_header']).text(resp.header);

				widget['content_body'].empty();
				if (typeof(resp.messages) !== 'undefined') {
					widget['content_body'].append(resp.messages);
				}
				widget['content_body'].append(resp.body);
				if (typeof(resp.debug) !== 'undefined') {
					widget['content_body'].append(resp.debug);
				}

				widget['content_footer'].html(resp.footer);

				// Creates new script elements and removes previous ones to force their reexecution
				widget['content_script'].empty();
				if (typeof(resp.script_file) !== 'undefined') {
					// NOTE: it is done this way to make sure, this script is executed before script_run function below.
					var new_script = $('<script>')
						.attr('type', 'text/javascript')
						.attr('src',resp.script_file);
					widget['content_script'].append(new_script);
				}
				if (typeof(resp.script_inline) !== 'undefined') {
					// NOTE: to execute scrpt with current widget context, add unique ID for required div, and use it in script
					var new_script = $('<script>')
						.text(resp.script_inline);
					widget['content_script'].append(new_script);
				}

				if (widget['update_attempts'] == 1) {
					widget['update_attempts'] = 0;
					startWidgetRefreshTimer(widget, widget['rf_rate']);
				}
				else {
					widget['update_attempts'] = 0;
					updateWidgetContent(widget);
				}
			},
			error: function() {
				// TODO: gentle message about failed update of widget content
				widget['update_attempts'] = 0;
				startWidgetRefreshTimer(widget, 3);
			}
		});
	}

	function refreshWidget(widget) {
		if (typeof(widget['rf_timeoutid']) !== 'undefined') {
			clearTimeout(widget['rf_timeoutid']);
			delete widget['rf_timeoutid'];
		}

		updateWidgetContent(widget);
	}

	function updateWidgetConfig($obj, data, widget) {
		var	url = new Curl('zabbix.php'),
			ajax_widgets = [],
			ajax_widget = {},
			fields = $('form', data.dialogue['body']).serializeJSON();

		url.setArgument('action', 'dashbrd.widget.update');

		if (widget !== null) {
			ajax_widget.widgetid = widget['widgetid'];
		}
		ajax_widget.fields = fields;
		ajax_widgets.push(ajax_widget);

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: {
				dashboard_id: data['dashboard']['id'], // TODO VM: (?) will not work without dashboard id
				widgets: ajax_widgets,
				save: 0 // 0 - only check; 1 - check and save
			},
			success: function(resp) {
				if (typeof(resp.errors) !== 'undefined') {
					// Error returned
					// Remove previous errors
					$('.msg-bad', data.dialogue['body']).remove();
					data.dialogue['body'].prepend(resp.errors);
				} else {
					// No errors, proceed with update
					overlayDialogueDestroy();

					if (widget === null) {
						// In case of ADD widget
						// create widget with required selected fields and add it to dashboard
						var pos = findEmptyPosition($obj, data, fields['type']);
						var widget_data = {
							'type': fields['type'],
							'header': data['widget_defaults'][fields['type']]['header'],
							'pos': pos,
							'rf_rate': data['widget_defaults'][fields['type']]['rf_rate'],
							'fields': fields
						}
						methods.addWidget.call($obj, widget_data);
						// new widget is last element in data['widgets'] array
						widget = data['widgets'].slice(-1)[0];
						setWidgetModeEdit($obj, data, widget);
					} else {
						// In case of EDIT widget
					}

					widget['fields'] = fields;
					widget['type'] = widget['fields']['type'];
					refreshWidget(widget);

					// mark dashboard as updated
					data['options']['updated'] = true;
				}
			},
			error: function() {
				// TODO VM: (?) Do we need to display some kind of error message here?
			}
		});
	}

	function findEmptyPosition($obj, data, type) {
		var pos = {
			'row': 0,
			'col': 0,
			'height': data['widget_defaults'][type]['size']['height'],
			'width': data['widget_defaults'][type]['size']['width']
		}

		// go row by row and try to position widget in each space
		// TODO VM: (?) probably not the most efficient algorithm,
		//			but simple one, and for our dashboard size should work fast enough
		var max_col = data['options']['columns'] - pos['width'],
			found = false,
			col, row;
		for (row = 0; !found; row++) {
			for (col = 0; col <= max_col && !found; col++) {
				pos['row'] = row;
				pos['col'] = col;
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

	function openConfigDialogue($obj, data, widget = null) {
		var edit_mode = (widget !== null) ? true : false;
		data.dialogue = {};
		data.dialogue.widget = widget;

		overlayDialogue({
			'title': (edit_mode ? t('Edit widget') : t('Add widget')),
			'content': '',
			'buttons': [
				{
					'title': (edit_mode ? t('Update') : t('Add')),
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
			]
		});

		var overlay_dialogue = $('#overlay_dialogue');
		data.dialogue.div = overlay_dialogue;
		data.dialogue.body = $('.overlay-dialogue-body', overlay_dialogue);

		updateWidgetConfigDialogue();
	}

	function setModeEditDashboard($obj, data) {
		$.each(data['widgets'], function(index, widget) {
			setWidgetModeEdit($obj, data, widget);
		});
	}

	function setWidgetModeEdit($obj, data, widget) {
		var btn_edit = $('<button>')
			.attr('type', 'button')
			.addClass('btn-widget-edit')
			.attr('title', t('Edit'))
			.click(function(){
				methods.editWidget.call($obj, widget);
			});

		var btn_delete = $('<button>')
			.attr('type', 'button')
			.addClass('btn-widget-delete')
			.attr('title', t('Delete'))
			.click(function(){
				methods.deleteWidget.call($obj, widget);
			});

		$('ul',widget['content_header']).hide();
		widget['content_header'].append($('<ul>')
			.addClass('dashbrd-widg-edit')
			.append($('<li>').append(btn_edit))
			.append($('<li>').append(btn_delete))
		);

		makeDraggable($obj, data, widget);
		makeResizable($obj, data, widget);
	}

	function deleteWidget($obj, data, widget) {
		var index = widget['div'].data('widget-index');

		// remove div from the grid
		widget['div'].remove();
		data['widgets'].splice(index,1);

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

		// Remove previous messages
		dashboardRemoveMessages();

		url.setArgument('action', 'dashbrd.widget.update');

		$.each(data['widgets'], function(index, widget) {
			var ajax_widget = {};
			if (widget['widgetid'] !== '') {
				ajax_widget.widgetid = widget['widgetid'];
			}
			ajax_widget['pos'] = widget['pos'];
			ajax_widget['fields'] = widget['fields'];

			ajax_widgets.push(ajax_widget);
		});

		var ajax_data = {
			dashboard_id: data['dashboard']['id'], // can be undefined if dashboard is new
			widgets: ajax_widgets,
			save: 1 // 0 - only check; 1 - check and save
		};

		if (typeof data['dashboard']['name'] !== 'undefined') {
			ajax_data['name'] = data['dashboard']['name'];
		}

		if (typeof data['dashboard']['userid'] !== 'undefined') {
			ajax_data['userid'] = data['dashboard']['userid'];
		}

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: ajax_data,
			success: function(resp) {
				if (typeof(resp.errors) !== 'undefined') {
					// Error returned
					dashbaordAddMessages(resp.errors);
				} else {
					if (typeof(resp.messages) !== 'undefined') {
						// Success returned
//						dashbaordAddMessages(resp.messages); // TODO VM: looks bad
					}
					// There are no more unsaved changes
					data['options']['updated'] = false;
					// Reload page to get latest wiget data from server.
					window.location.replace(resp.redirect);
				}
			},
			error: function() {
				// TODO VM: (?) Do we need to display some kind of error message here?
			}
		});
	}

	function confirmExit($obj, data) {
		if (data['options']['updated'] === true) {
			return t('You have unsaved changes.')+"\n"+t('Are you sure, you want to leave this page?');
		}
	}

	var	methods = {
		init: function(options) {
			var default_options = {
				'widget-height': 70,
				'columns': 12,
				'widget-width': 100 / 12,
				'rows': 0,
				'updated': false
			};
			options = $.extend(default_options, options);
			options['widget-width'] = 100 / options['columns'];

			return this.each(function() {
				var	$this = $(this),
					$placeholder = $('<div>', {'class': 'dashbrd-grid-widget-placeholder'});

				$this.data('dashboardGrid', {
					dashboard: {},
					options: options,
					widgets: [],
					widget_defaults: {},
					placeholder: $placeholder
				});
				var data = $this.data('dashboardGrid');

				$this.append($placeholder.hide());

				// TODO VM: (?) it is good to have warning, but it looks kinda bad and we have no controll over it.
				$(window).bind('beforeunload', function() {
					var res = confirmExit($this, data);
					// return value only if we need confirmation window, return nothing othervise
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

				dashboard = $.extend({}, data['dashboard'], dashboard);
				data['dashboard'] = dashboard;
			});
		},

		getDashboardData: function() {
			return $(this).data('dashboardGrid');
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
			widget = $.extend({}, {
				'widgetid': '',
				'type': '',
				'header': '',
				'pos': {
					'row': 0,
					'col': 0,
					'height': 1,
					'width': 1
				},
				'rf_rate': 0,
				'preloader_timeout': 10000,	// in milliseconds
				'preloader_fadespeed': 500,
				'update_attempts': 0,
				'fields': {
					'type': '',
				}
			}, widget);

			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				widget['div'] = makeWidgetDiv(data, widget).data('widget-index', data['widgets'].length);

				data['widgets'].push(widget);
				$this.append(widget['div']);

				setDivPosition(widget['div'], data, widget['pos']);
				checkWidgetOverlap($this, data, widget);

				resizeDashboardGrid($this, data);

				showPreloader(widget);
				updateWidgetContent(widget);
			});
		},

		setWidgetRefreshRate: function(widgetid, rf_rate) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (widget['widgetid'] == widgetid) {
						widget['rf_rate'] = rf_rate;
						startWidgetRefresh(widget);
					}
				});
			});
		},

		refreshWidget: function(widgetid) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (widget['widgetid'] == widgetid) {
						refreshWidget(widget);
					}
				});
			});
		},

		addWidgets: function(widgets) {
			return this.each(function() {
				var	$this = $(this);

				$.each(widgets, function(index, value) {
					methods.addWidget.apply($this, Array.prototype.slice.call(arguments, 1));
				});
			});
		},

		// Make widgets editable - Header icons, Resizeable, Draggable
		setModeEditDashboard: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				dashboardRemoveMessages();
				setModeEditDashboard($this, data);
			});
		},

		// Save changes and remove editable elements from widget - Header icons, Resizeable, Draggable
		saveDashboardChanges: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				saveChanges($this, data);
			});
		},

		// Discard changes and remove editable elements from widget - Header icons, Resizeable, Draggable
		cancelEditDashboard: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				// Don't show warning about existing updates
				data['options']['updated'] = false;

				// Redirect to last active dashboard.
				// (1) In case of New Dashboard from list, it will open list
				// (2) In case of New Dashboard or Clone Dashboard from other dashboard, it will open that dashboard
				// (3) In case of simple editing of current dashboard, it will reload same dashboard
				location.replace('zabbix.php?action=dashboard.view'); // TODO VM: (?) by such I am limiting usage of dashboard grid to this page.
			});
		},

		// After pressing "Edit" button on widget
		editWidget: function(widget) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				openConfigDialogue($this, data, widget);
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

		// Add or update form on widget configuration dialogue
		// (when opened, as well as when requested by 'onchange' attributes in form itself)
		updateWidgetConfigDialogue: function() {
			return this.each(function() {
				var $this = $(this),
					data = $this.data('dashboardGrid'),
					body = data.dialogue['body'],
					footer = $('.overlay-dialogue-footer', data.dialogue['div']),
					form = $('form', body),
					widget = data.dialogue['widget'], // widget currently beeing edited
					url = new Curl('zabbix.php'),
					ajax_data = {};

				// disable saving, while form is beeing updated
				$('.dialogue-widget-save', footer).prop('disabled', true);

				url.setArgument('action', 'dashbrd.widget.config');

				if (form.length) {
					// Take values from form
					ajax_data.fields = form.serializeJSON();
				} else if (widget !== null) {
					// Open form with current config
					ajax_data.fields = widget['fields'];
				} else {
					// Get default config for new widget
					ajax_data.fields = [];
				}

				jQuery.ajax({
					url: url.getUrl(),
					method: 'POST',
					data: ajax_data,
					dataType: 'json',
					success: function(resp) {
						body.empty();
						body.append(resp.body);
						if (typeof(resp.debug) !== 'undefined') {
							body.append(resp.debug);
						}
						if (typeof(resp.messages) !== 'undefined') {
							body.append(resp.messages);
						}

						// Change submit function for returned form
						$('#widget_dialogue_form', body).on('submit', function(e) {
							e.preventDefault();
							updateWidgetConfig($this, data, widget);
						});

						// position dialogue in middle of screen
						data.dialogue['div'].css({
							'margin-top': '-' + (data.dialogue['div'].outerHeight() / 2) + 'px',
							'margin-left': '-' + (data.dialogue['div'].outerWidth() / 2) + 'px'
						});

						// Enable save button after sucessfull form update
						$('.dialogue-widget-save', footer).prop('disabled', false);
					},
					error: function() {
						// TODO VM: (?) do we need to have error message on failed dialogue form update?
					}
				});
			});
		},

		addNewWidget: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				openConfigDialogue($this, data);
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
