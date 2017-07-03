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
			.append($('<h4>').text(
				(widget['header'] !== '') ? widget['header'] : data['widget_defaults'][widget['type']]['header']
			));
		widget['content_body'] = $('<div>')
			.addClass('dashbrd-grid-widget-content');
		widget['content_footer'] = $('<div>')
			.addClass('dashbrd-grid-widget-foot');
		widget['content_script'] = $('<div>');

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

		return $('<div>', {
			'class': 'dashbrd-grid-widget',
			'css': {
				'min-height': '' + data['options']['widget-height'] + 'px',
				'min-width': '' + data['options']['widget-width'] + '%'
			}
		})
			.append(
				$('<div>', {'class': 'dashbrd-grid-widget-padding'})
					.append($('<div>', {'class': 'dashbrd-grid-widget-mask'}))
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

	function generateRandomString(length) {
		var space = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			ret = '';

		for (var i = 0; length > i; i++) {
			ret += space.charAt(Math.floor(Math.random() * space.length));
		}
		return ret;
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
		$('.dashbrd-grid-widget-mask', $div).show();

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
		$('.dashbrd-grid-widget-mask', $div).hide();

		$div.removeClass('dashbrd-grid-widget-draggable');

		$.each(data['widgets'], function() {
			// Check if position of widget changed
			var new_pos = this['current_pos'],
				old_pos = this['pos'],
				changed = false;

			$.each(['row', 'col', 'height', 'width'], function(index, value) {
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
				doAction($obj, data, 'onResizeEnd');
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

	function startWidgetRefreshTimer($obj, data, widget, rf_rate) {
		if (rf_rate != 0) {
			widget['rf_timeoutid'] = setTimeout(function () { updateWidgetContent($obj, data, widget); }, rf_rate * 1000);
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
			'fullscreen': data['options']['fullscreen'],
			'uniqueid': widget['uniqueid'],
			'initial_load': widget['initial_load'] ? 1 : 0,
			'edit_mode': data['options']['edit_mode'] ? 1 : 0
		}
		widget['initial_load'] = false;

		if (widget['widgetid'] !== '') {
			ajax_data['widgetid'] = widget['widgetid'];
		}
		if (widget['header'] !== '') {
			ajax_data['name'] = widget['header'];
		}
		// display widget with yet unsaved changes
		if (typeof widget['fields'] !== 'undefined') {
			ajax_data['fields'] = widget['fields'];
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
					// NOTE: to execute scrpt with current widget context, add unique ID for required div, and use it in script
					var new_script = $('<script>')
						.text(resp.script_inline);
					widget['content_script'].append(new_script);
				}

				if (widget['update_attempts'] == 1) {
					widget['update_attempts'] = 0;
					startWidgetRefreshTimer($obj, data, widget, widget['rf_rate']);
				}
				else {
					widget['update_attempts'] = 0;
					updateWidgetContent($obj, data, widget);
				}
			},
			error: function() {
				// TODO: gentle message about failed update of widget content
				widget['update_attempts'] = 0;
				startWidgetRefreshTimer($obj, data, widget, 3);
			}
		});
	}

	function refreshWidget($obj, data, widget) {
		if (typeof(widget['rf_timeoutid']) !== 'undefined') {
			stopWidgetRefreshTimer(widget);
		}

		updateWidgetContent($obj, data, widget);
	}

	function updateWidgetConfig($obj, data, widget) {
		var	url = new Curl('zabbix.php'),
			ajax_widgets = [],
			ajax_widget = {},
			fields = $('form', data.dialogue['body']).serializeJSON(),
			type = fields['type'],
			name = fields['name'];

		delete fields['type'];
		delete fields['name'];

		url.setArgument('action', 'dashbrd.widget.update');

		if (widget !== null) {
			ajax_widget.widgetid = widget['widgetid'];
		}
		ajax_widget.type = type;
		ajax_widget.name = name;
		ajax_widget.fields = fields;
		ajax_widgets.push(ajax_widget);

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: {
				dashboardid: data['dashboard']['id'],
				widgets: ajax_widgets,
				save: 0 // WIDGET_CONFIG_DONT_SAVE - only check
			},
			success: function(resp) {
				if (typeof(resp.errors) !== 'undefined') {
					// Error returned
					// Remove previous errors
					$('.msg-bad', data.dialogue['body']).remove();
					data.dialogue['body'].prepend(resp.errors);
				}
				else {
					// No errors, proceed with update
					overlayDialogueDestroy();

					if (widget === null) {
						// In case of ADD widget
						// create widget with required selected fields and add it to dashboard
						var pos = findEmptyPosition($obj, data, type),
							widget_data = {
							'type': type,
							'header': name,
							'pos': pos,
							'rf_rate': data['widget_defaults'][type]['rf_rate'],
							'fields': fields
						}
						methods.addWidget.call($obj, widget_data);
						// new widget is last element in data['widgets'] array
						widget = data['widgets'].slice(-1)[0];
						setWidgetModeEdit($obj, data, widget);
					}
					else {
						// In case of EDIT widget
						if (widget['type'] !== type) {
							widget['type'] = type;
							widget['initial_load'] = true;
						}
						widget['header'] = name;
						widget['fields'] = fields;
					}

					updateWidgetDynamic($obj, data, widget);
					refreshWidget($obj, data, widget);

					// mark dashboard as updated
					data['options']['updated'] = true;
				}
			},
			error: function() {
				// TODO VM: Add error message box in this case
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
		var	max_col = data['options']['columns'] - pos['width'],
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

	function openConfigDialogue($obj, data, widget) {
		var edit_mode = (widget !== null);

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
		var	btn_edit = $('<button>')
			.attr('type', 'button')
			.addClass('btn-widget-edit')
			.attr('title', t('Edit'))
			.click(function() {
				doAction($obj, data, 'beforeConfigLoad');
				methods.editWidget.call($obj, widget);
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

		makeDraggable($obj, data, widget);
		makeResizable($obj, data, widget);
	}

	function deleteWidget($obj, data, widget) {
		var index = widget['div'].data('widget-index');

		// remove div from the grid
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

		// Remove previous messages
		dashboardRemoveMessages();

		url.setArgument('action', 'dashbrd.widget.update');

		$.each(data['widgets'], function(index, widget) {
			var	ajax_widget = {};

			if (widget['widgetid'] !== '') {
				ajax_widget.widgetid = widget['widgetid'];
			}
			ajax_widget['pos'] = widget['pos'];
			ajax_widget['type'] = widget['type'];
			ajax_widget['name'] = widget['header'];
			ajax_widget['fields'] = widget['fields'];

			ajax_widgets.push(ajax_widget);
		});

		var ajax_data = {
			dashboardid: data['dashboard']['id'], // can be undefined if dashboard is new
			name: data['dashboard']['name'],
			userid: data['dashboard']['userid'],
			widgets: ajax_widgets,
			save: 1 // WIDGET_CONFIG_DO_SAVE - check and save
		};

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: ajax_data,
			success: function(resp) {
				// we can have redirect with errors
				if ('redirect' in resp) {
					// There are no more unsaved changes
					data['options']['updated'] = false;
					// Replace add possibility to remove previous url (as ..&new=1) from the document history
					// it allows to use back browser button more user-friendly
					window.location.replace(resp.redirect);
				}
				else if ('errors' in resp) {
					// Error returned
					dashbaordAddMessages(resp.errors);
				}
			},
			error: function() {
				// TODO VM: add error message box
			}
		});
	}

	function confirmExit($obj, data) {
		if (data['options']['updated'] === true) {
			return t('You have unsaved changes.') + "\n" + t('Are you sure, you want to leave this page?');
		}
	}

	function updateWidgetDynamic($obj, data, widget) {
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

	function doAction($obj, data, hook_name) {
		if (typeof(data['triggers'][hook_name]) === 'undefined') {
			return;
		}
		var triggers = data['triggers'][hook_name];

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
				if (typeof(trigger['options']['grid']['widget']) !== 'undefined') {
					var widgets = methods.getWidgetsBy.call($obj, 'uniqueid', trigger['options']['grid']['widget']);
					// will return only first element
					if (widgets.length > 0) {
						grid['widget'] = widgets[0];
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

			// TODO VM: (?) try-catch may be unnecessary, but it prevents from JS from braking, if this function is not working properly
			try {
				window[trigger['function']].apply(null, params);
			}
			catch(e) {}
		});
	}

	var	methods = {
		init: function(options) {
			var default_options = {
				'fullscreen': 0,
				'widget-height': 70,
				'columns': 12,
				'rows': 0,
				'updated': false
			};
			options = $.extend(default_options, options);
			options['widget-width'] = 100 / options['columns'];
			options['edit_mode'] = false;

			return this.each(function() {
				var	$this = $(this),
					$placeholder = $('<div>', {'class': 'dashbrd-grid-widget-placeholder'});

				$this.data('dashboardGrid', {
					dashboard: {},
					options: options,
					widgets: [],
					widget_defaults: {},
					triggers: {},
					placeholder: $placeholder
				});

				var	data = $this.data('dashboardGrid');

				$this.append($placeholder.hide());

				$(window).bind('beforeunload', function() {
					var	res = confirmExit($this, data);

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
					'row': 0,
					'col': 0,
					'height': 1,
					'width': 1
				},
				'rf_rate': 0,
				'preloader_timeout': 10000,	// in milliseconds
				'preloader_fadespeed': 500,
				'update_attempts': 0,
				'update_attempts': 0,
				'initial_load': true,
				'fields': {}
			}, widget);

			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				widget['uniqueid'] = generateUniqueId($this, data);
				widget['div'] = makeWidgetDiv(data, widget).data('widget-index', data['widgets'].length);
				updateWidgetDynamic($this, data, widget);

				data['widgets'].push(widget);
				$this.append(widget['div']);

				setDivPosition(widget['div'], data, widget['pos']);
				checkWidgetOverlap($this, data, widget);

				resizeDashboardGrid($this, data);

				showPreloader(widget);
				updateWidgetContent($this, data, widget);
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

		setWidgetFieldValue: function(uniqueid, field, value) {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				$.each(data['widgets'], function(index, widget) {
					if (widget['uniqueid'] === uniqueid) {
						widget['fields'][field] = value;
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

				data['options']['edit_mode'] = true;
				doAction($this, data, 'onEditStart');
				dashboardRemoveMessages();
				setModeEditDashboard($this, data);
			});
		},

		// Save changes and remove editable elements from widget - Header icons, Resizeable, Draggable
		saveDashboardChanges: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				doAction($this, data, 'beforeDashboardSave');
				saveChanges($this, data);
				data['options']['edit_mode'] = false;
				doAction($this, data, 'afterDashboardSave');
			});
		},

		// Discard changes and remove editable elements from widget - Header icons, Resizeable, Draggable
		cancelEditDashboard: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				doAction($this, data, 'onEditStop');

				// Don't show warning about existing updates
				data['options']['updated'] = false;

				// Redirect to last active dashboard.
				// (1) In case of New Dashboard from list, it will open list
				// (2) In case of New Dashboard or Clone Dashboard from other dashboard, it will open that dashboard
				// (3) In case of simple editing of current dashboard, it will reload same dashboard
				location.replace('zabbix.php?action=dashboard.view&fullscreen=' + data['options']['fullscreen']);
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

		// Call stopWidgetRefreshTimer on some speciffic widget.
		stopWidgetRefreshTimer: function(widget) {
			stopWidgetRefreshTimer(widget);
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
				var	$this = $(this),
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

				// Don't send on "Add widget" or on "Edit widget", if widget was not saved yet
				if (widget !== null && widget['widgetid'] !== '') {
					ajax_data.widgetid = widget['widgetid'];
				}

				if (form.length) {
					// Take values from form
					ajax_data.fields = form.serializeJSON();
					ajax_data.type = ajax_data['fields']['type'];
					ajax_data.name = ajax_data['fields']['name'];
					delete ajax_data['fields']['type'];
					delete ajax_data['fields']['name'];
				}
				else if (widget !== null) {
					// Open form with current config
					ajax_data.type = widget['type'];
					ajax_data.name = widget['header'];
					ajax_data.fields = widget['fields'];
				}
				else {
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
					complete: function() {
						overlayDialogueOnLoad(true);
					},
					error: function() {
						// TODO VM: add error message box
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
		registerAsSharedDataReceiver: function(obj) {
			return this.each(function() {
				var $this = $(this),
						data = $this.data('dashboardGrid');

				for (var i = 0, l = data['widgets'].length; l > i; i++) {
					if (data['widgets'][i]['uniqueid'] == obj.uniqueid) {
						if (typeof data['widgets'][i]['listen_for'] === 'undefined') {
							data['widgets'][i]['listen_for'] = [];
						}
						data['widgets'][i]['listen_for'].push(obj);
					}
				}
			});
		},

		widgetDataShare: function(widget) {
			var args = Array.prototype.slice.call(arguments, 1),
					reference = '';

			if (!args.length) {
				return false;
			}

			if (typeof widget['fields']['reference'] !== 'undefined') {
				var reference = widget['fields']['reference'];

				return this.each(function() {
					var $this = $(this),
							data = $this.data('dashboardGrid');

					for (var i = 0, l = data['widgets'].length; l > i; i++) {
						if (typeof(data['widgets'][i]['listen_for']) != 'undefined') {
							for (var t = 0, j = data['widgets'][i]['listen_for'].length; j > t; t++) {
								if (data['widgets'][i]['listen_for'][t]['source_widget_reference'] === reference) {
									data['widgets'][i]['listen_for'][t].callback.apply(this, [data['widgets'][i], args]);
								}
							}
						}
					}
				});
			}
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

		addNewWidget: function() {
			return this.each(function() {
				var	$this = $(this),
					data = $this.data('dashboardGrid');

				openConfigDialogue($this, data, null);
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
		 * @param array options  any key in options is optional
		 * @param array options['parameters']  array of parameters with which the function will be called
		 * @param array options['grid']  mark, what data from grid should be passed to $function_to_call.
		 *								If is empty, parameter 'grid' will not be added to function_to_call params.
		 * @param string options['grid']['widget']  should contain uniqueid of widget. Will add widget object.
		 * @param string options['grid']['data']  should contain '1'. Will add dashboard grid data object.
		 * @param string options['grid']['obj']  should contain '1'. Will add dashboard grid object ($this).
		 * @param int options['priority']  order, when it should be called, compared to others. Default = 10
		 * @param int options['trigger_name']  unique name. There can be only one trigger with this name for each hook.
		 */
		addAction: function(hook_name, function_to_call, options) {
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
						if (trigger['trigger_name'] === trigger_name) {
							found = true;
						}
					});
				}

				if (!found) {
					data['triggers'][hook_name].push({
						'trigger_name': trigger_name,
						'function': function_to_call,
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
