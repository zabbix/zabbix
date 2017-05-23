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
				// save original values (only on first widget update before save)
				if (typeof this['pos_orig'] === 'undefined') {
					this['pos_orig'] = this['pos'];
				}
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
			ajax_data = [],
			fields = $('form', data.dialogue['body']).serializeJSON();

		url.setArgument('action', 'dashbrd.widget.update');

		ajax_data.push({
			'widgetid': widget['widgetid'],
			'fields': fields
		});

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: {
				dashboardid: data['options']['dashboardid'], // TODO VM: (?) will not work without dashboard id
				widgets: ajax_data,
				save: 0 // WIDGET_CONFIG_DONT_SAVE - only check
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

					// save original values (only on first widget update before save)
					if (typeof widget['fields_orig'] === 'undefined') {
						widget['fields_orig'] = widget['fields'];
					}
					widget['fields'] = fields;
					widget['type'] = widget['fields']['type'];
					updateWidgetDynamic($obj, data, widget);
					refreshWidget(widget);
				}
			},
			error: function() {
				// TODO VM: (?) Do we need to display some kind of error message here?
			}
		});
	}

	function openConfigDialogue($obj, data, widget) {
		var edit_mode = (widget['widgetid'] === '') ? false : true;
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
				.attr('title', t('Delete'));

			$('ul',widget['content_header']).hide();
			widget['content_header'].append($('<ul>')
				.addClass('dashbrd-widg-edit')
				.append($('<li>').append(btn_edit))
				.append($('<li>').append(btn_delete))
			);

			makeDraggable($obj, data, widget);
			makeResizable($obj, data, widget);
		});
	}

	function setModeViewDashboard($obj, data) {
		$.each(data['widgets'], function(index, widget) {
			// revert all unsaved changes that were done in this edit
			if (typeof widget['pos_orig'] !== 'undefined') {
				widget['pos'] = widget['pos_orig'];
				delete widget['pos_orig'];
				setDivPosition(widget['div'], data, widget['pos']);
				resizeDashboardGrid($obj, data);
			}
			if (typeof widget['fields_orig'] !== 'undefined') {
				widget['fields'] = widget['fields_orig'];
				widget['type'] = widget['fields']['type'];
				delete widget['fields_orig'];
				refreshWidget(widget);
			}

			$('.dashbrd-widg-edit',widget['content_header']).remove();
			$('ul',widget['content_header']).show();

			stopDraggable($obj, data, widget);
			stopResizable($obj, data, widget);
		});
		// update control buttons, controlling dashboard
		dashboardButtonsSetView();
	}

	function saveChanges($obj, data) {
		var	url = new Curl('zabbix.php'),
			ajax_data = [];

		// Remove previous messages
		dashboardRemoveMessages();

		url.setArgument('action', 'dashbrd.widget.update');

		$.each(data['widgets'], function(index, widget) {
			ajax_data.push({
				'widgetid': widget['widgetid'],
				'pos': widget['pos'],
				'fields': widget['fields']
			});
		});

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: {
				dashboardid: data['options']['dashboardid'], // TODO VM: (?) will not work without dashboard id
				widgets: ajax_data,
				save: 1 // WIDGET_CONFIG_DO_SAVE - check and save
			},
			success: function(resp) {
				if (typeof(resp.errors) !== 'undefined') {
					// Error returned
					dashbaordAddMessages(resp.errors);
				} else {
					if (typeof(resp.messages) !== 'undefined') {
						// Success returned
						dashbaordAddMessages(resp.messages);
					}
					$.each(data['widgets'], function(index, data_widget) {
						// remove original values (new ones were just saved)
						delete data_widget['fields_orig'];
						delete data_widget['pos_orig'];
					});

					setModeViewDashboard($obj, data);
				}
			},
			error: function() {
				// TODO VM: (?) Do we need to display some kind of error message here?
			}
		});
	}

	function confirmExit($obj, data) {
		var has_changes = false;
		$.each(data['widgets'], function(index, widget) {
			if (typeof widget['pos_orig'] !== 'undefined') {
				has_changes = true;
			}
			if (typeof widget['fields_orig'] !== 'undefined') {
				has_changes = true;
			}
		});

		if (has_changes === true) {
			return t('You have unsaved changes.')+"\n"+t('Are you sure, you want to leave this page?');
		}
	}

	function updateWidgetDynamic($obj, data, widget) {
		if (typeof(widget['fields']['dynamic']) !== 'undefined' && widget['fields']['dynamic'] === '1') {
			if (data['options']['dynamic']['has_dynamic_widgets'] === true) {
				widget['dynamic'] = {
					'hostid': data['options']['dynamic']['hostid'],
					'groupid': data['options']['dynamic']['groupid']
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

	var	methods = {
		init: function(options) {
			options = $.extend({}, {columns: 12}, options);
			options['widget-height'] = 70;
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
				updateWidgetDynamic($this, data, widget);

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

				dashboardRemoveMessages();
				setModeViewDashboard($this, data);
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

				if (widget['widgetid'] !== '') {
					ajax_data.widgetid = widget['widgetid'];
				}

				if (form.length) {
					// Take values from form
					ajax_data.fields = form.serializeJSON();
				} else {
					// Open form with current config
					ajax_data.fields = widget['fields'];
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
