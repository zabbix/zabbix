/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


// Time range selector.
jQuery(function($) {
	var $container = $('.filter-space').first(),
		xhr = null,
		endpoint = new Curl('zabbix.php', false),
		element = {
			from: $container.find('[name=from]'),
			to: $container.find('[name=to]'),
			from_clndr: $container.find('[name=from_calendar]'),
			to_clndr: $container.find('[name=to_calendar]'),
			apply: $container.find('[name=apply]'),
			increment: $container.find('.btn-time-right'),
			decrement: $container.find('.btn-time-left'),
			zoomout: $container.find('.btn-time-out'),
			quickranges: $container.find('.time-quick a'),
			label: $container.find('.btn-time')
		},
		request_data = {
			idx: $container.data('profileIdx'),
			idx2: $container.data('profileIdx2'),
			from: element.from.val(),
			to: element.to.val()
		},
		ui_accessible = ($container.data('accessible') == 1),
		ui_disabled = false;

	endpoint.setArgument('action', 'timeselector.update');
	endpoint.addSID();
	endpoint.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON

	$.subscribe('timeselector.rangechange timeselector.decrement timeselector.increment timeselector.zoomout' +
		' timeselector.rangeoffset',
		timeSelectorEventHandler
	);

	element.from.keydown(submitChangeHandler);
	element.to.keydown(submitChangeHandler);

	// Time selector DOM elements event triggerers initialization.
	$container.on('click', function(event) {
		var action = '',
			data = {},
			$target = $(event.target);

		if (ui_disabled) {
			return cancelEvent(event);
		}
		else if ($target.is(element.increment)) {
			action = 'timeselector.increment';
		}
		else if ($target.is(element.decrement)) {
			action = 'timeselector.decrement';
		}
		else if ($target.is(element.zoomout)) {
			action = 'timeselector.zoomout';
		}
		else if ($target.is(element.apply)) {
			action = 'timeselector.rangechange';
			data = {
				from: element.from.val(),
				to: element.to.val()
			}
		}
		else if (element.quickranges.index($target) != -1) {
			action = 'timeselector.rangechange';
			data = $target.data();
			element.quickranges.removeClass('selected');
			$target.addClass('selected');
		}

		if (action !== '') {
			$.publish(action, data);
		}
	});

	/**
	 * Trigger timeselector.rangechange event on 'enter' key press in 'from' or 'to' input field.
	 *
	 * @param {object} event  jQuery event object.
	 */
	function submitChangeHandler(event) {
		if (event.which == 13) { // Enter
			$.publish('timeselector.rangechange', {
				from: element.from.val(),
				to: element.to.val()
			});
			return cancelEvent(event);
		}
	}

	/**
	 * Time selector UI update.
	 *
	 * @param {object} data  Server response on 'timeselector.rangechange' request.
	 */
	function updateTimeSelectorUI(data) {
		if (!ui_accessible) {
			return;
		}

		if ('error' in data === false) {
			element.from.val(data.from);
			element.to.val(data.to);
			element.label.text(data.label);
		}

		$([element.from[0], element.to[0], element.apply[0]]).prop('disabled', false);

		$.each({
			decrement: data.can_decrement,
			increment: data.can_increment,
			zoomout: data.can_zoomout
		}, function(elm, state) {
			if (typeof state !== 'undefined') {
				element[elm].prop('disabled', !state);
			}

			element[elm].removeClass('disabled');
		});

		element.quickranges.removeClass('selected');
		element.quickranges
			.filter('[data-label="' + data.label + '"]')
			.addClass('selected');

		element.apply
			.closest('.ui-tabs-panel')
			.removeClass('is-loading is-loading-fadein');

		ui_disabled = false;
	}

	/**
	 * Disable time selector UI.
	 */
	function disableTimeSelectorUI() {
		if (!ui_accessible) {
			return;
		}

		element.apply
			.closest('.ui-tabs-panel')
			.addClass('is-loading is-loading-fadein');

		$([element.from[0], element.to[0], element.apply[0]]).prop('disabled', true);
		$([element.decrement[0], element.zoomout[0], element.increment[0]]).addClass('disabled');

		ui_disabled = true;
	}

	/**
	 * Time selector events handler. Any of current time selector interval changes will publish event
	 * 'timeselector.rangeupdate'.
	 *
	 * Handled events:
	 *   timeselector.rangechange  Event to apply new time selector from and to values.
	 *   timeselector.decrement    Event to decrement current time selector interval.
	 *   timeselector.increment    Event to increment current time selector interval.
	 *   timeselector.zoomout      Event to zoomout current time selector interval.
	 *   timeselector.rangeoffset  Event to apply offset to from and to values.
	 *
	 * @param {object} event  jQuery event object.
	 * @param {object} data   Object with published data for event.
	 */
	function timeSelectorEventHandler(event, data) {
		var args = {
			'idx': request_data.idx,
			'idx2': request_data.idx2,
			'from': (event.namespace === 'rangechange') ? data.from : request_data.from,
			'to': (event.namespace === 'rangechange') ? data.to : request_data.to
		};

		switch (event.namespace) {
			case 'rangeoffset':
				args.from_offset = data.from_offset;
				args.to_offset = data.to_offset;
				break;

			case 'zoomout':
				if (request_data.can_zoomout === false) {
					return;
				}
				break;
		}

		endpoint.setArgument('method', event.namespace);

		if (xhr && xhr.abort) {
			return;
		}

		disableTimeSelectorUI();

		xhr = $.ajax({
			url: endpoint.getUrl(),
			type: 'post',
			cache: false,
			data: args,
			success: function(json) {
				request_data = $.extend(data, request_data, json, {event: event.namespace});
				updateTimeSelectorUI(request_data);

				if (json.error) {
					if (typeof json.error === 'string') {
						// Error message originates from CControllerTimeSelectorUpdate::checkInput().
						alert(json.error);
					}

					$container.find('.time-input-error').each(function(i, elm) {
						var $node = $(elm),
							field = $node.attr('data-error-for');

						if (json.error[field]) {
							$node
								.show()
								.find('.red')
								.text(json.error[field]);
						}
						else {
							$node.hide();
						}
					});

					delete request_data.error;
				}
				else {
					updateUrlArguments(json.from, json.to);
					$container
						.find('.time-input-error')
						.hide();
					$.publish('timeselector.rangeupdate', request_data);
				}

				xhr = null;
			},
			error: function(request, status, error) {
				/*
				 * In case there is something very wrong with the code like "echo '<br>'" in the middle where there is
				 * supposed to be JSON, show error. Otherwise it could've been just a temporary connection issue
				 * like 404, for example, so just retry.
				 */
				if (request.status != 200) {
					var request = this,
						retry = function() {
							$.ajax(request);
						};

					// Retry with 2s interval.
					setTimeout(retry, 2000);
				}
				else {
					alert(error);
				}
			}
		});
	}

	/**
	 * Update from/to URL arguments and remove page URL argument from browser history.
	 *
	 * @param {string} from  Value for 'from' argument.
	 * @param {string} to    Value for 'to' argument.
	 */
	function updateUrlArguments(from, to) {
		var url = new Curl(),
			args = url.getArguments();

		if (('from' in args) || ('to' in args) || ('page' in args)) {
			if ('from' in args) {
				url.setArgument('from', from);
			}

			if ('to' in args) {
				url.setArgument('to', to);
			}

			if ('page' in args) {
				url.unsetArgument('page');
			}

			url.unsetArgument('sid');

			history.replaceState(history.state, '', url.getUrl());
		}
	}

	// Time selection box for graphs.
	var selection = null,
		noclick_area = null,
		was_dragged = false,
		prevent_click = false;

	$(document)
		.on('mousedown', 'img', selectionHandlerDragStart)
		.on('dblclick', 'img', function(event) {
			if (typeof $(event.target).data('zbx_sbox') !== 'undefined') {
				$.publish('timeselector.zoomout', {
					from: element.from.val(),
					to: element.to.val()
				});

				return cancelEvent(event);
			}
		})
		.on('click', 'a', function(event) {
			// Prevent click on graph image parent <a/> element when clicked inside graph selectable area.
			if ($(event.target).is('img') && typeof $(event.target).data('zbx_sbox') !== 'undefined' && prevent_click
					&& $(this).hasClass('dashboard-widget-graph-link')) {
				return cancelEvent(event);
			}
		});

	/**
	 * Handle selection box drag start event.
	 *
	 * @param {object} event  jQuery event object.
	 */
	function selectionHandlerDragStart(event) {
		if (event.which !== 1) {
			return;
		}

		var target = $(event.target),
			data = target.data();

		if (typeof data.zbx_sbox === 'undefined') {
			return;
		}

		was_dragged = false;

		/**
		 * @prop {object}  data
		 * @prop {integer} data.height           Height of selection box.
		 * @prop {integer} data.left             Left margin of selection box.
		 * @prop {integer} data.right            Right margin of selection box.
		 * @prop {integer} data.top              Top margin of selection box.
		 * @prop {integer} data.from_ts          Timestamp for start time of selection box.
		 * @prop {integer} data.to_ts            Timestamp for end time of selection box.
		 * @prop {integer} data.prevent_refresh  Mark image as non updateable during selection.
		 */
		data = data.zbx_sbox;
		data.prevent_refresh = true;
		target.data('zbx_sbox', data);

		var offset = target.offset(),
			left = data.left,
			right = target.outerWidth() - data.right,
			xpos = Math.min(Math.max(left, event.pageX - offset.left), right),
			parent = target.parent();

		offset.top += data.top;
		if ((event.pageY < offset.top) || event.pageY > offset.top + data.height) {
			prevent_click = false;
			return;
		}

		prevent_click = true;
		noclick_area = $('<div>')
			.css({
				position: 'absolute',
				top: 0,
				left: (parent.is('.center') ? target : parent).position().left,
				height: target.height() + 'px',
				width: target.width() + 'px',
				overflow: 'hidden',
				display: 'none'
			})
			.insertAfter(parent);

		selection = {
			dom: $('<div>', {class: 'graph-selection'})
				.css({
					position: 'absolute',
					top: data.top,
					left: xpos,
					height: data.height + 'px',
					width: '1px'
				})
				.appendTo(noclick_area),
			offset: offset,
			min: left,
			max: right,
			base_x: xpos,
			seconds_per_px: (data.to_ts - data.from_ts) / (right - left)
		}

		$(document)
			.on('mouseup', {zbx_sbox: data, target: target}, selectionHandlerDragEnd)
			.on('mousemove', selectionHandlerDrag);

		return cancelEvent(event);
	}

	/**
	 * Handle selection box drag end event.
	 *
	 * @param {object} event  jQuery event object.
	 */
	function selectionHandlerDragEnd(event) {
		var left = Math.floor(Math.max(selection.dom.position().left, selection.min)),
			from_offset = (left - selection.min) * selection.seconds_per_px,
			to_offset = (selection.max - Math.floor(selection.dom.width()) - left) * selection.seconds_per_px,
			zbx_sbox = event.data.zbx_sbox;

		zbx_sbox.prevent_refresh = false;
		event.data.target.data('zbx_sbox', zbx_sbox);

		selection.dom.remove();
		selection = null;
		$(document)
			.off('mouseup', selectionHandlerDragEnd)
			.off('mousemove', selectionHandlerDrag);

		noclick_area.remove();
		noclick_area = null;

		if (was_dragged && (from_offset > 0 || to_offset > 0)) {
			$.publish('timeselector.rangeoffset', {
				from_offset: Math.ceil(from_offset),
				to_offset: Math.ceil(to_offset)
			});
		}

		return cancelEvent(event);
	}

	/**
	 * Handle selection box drag event
	 *
	 * @param {object} event  jQuery event object.
	 */
	function selectionHandlerDrag(event) {
		var x = Math.min(Math.max(selection.min, event.pageX - selection.offset.left), selection.max),
			width = Math.abs(x - selection.base_x),
			seconds = Math.round(width * selection.seconds_per_px),
			label = formatTimestamp(seconds, false, true) + (seconds < 60 ? ' [min 1' + t('S_MINUTE_SHORT') + ']' : '');

		if (!was_dragged) {
			was_dragged = true;
			noclick_area.show();
		}

		selection.dom
			.css({
				left: Math.min(selection.base_x, x),
				width: width + 'px'
			})
			.text(label);
	}

	function checkDisableTimeSelectorUI() {
		if (!element.zoomout.length) {
			return false;
		}

		$.ajax({
			url: endpoint.getUrl(),
			type: 'post',
			cache: false,
			data: {
				method: 'rangechange',
				idx: request_data.idx,
				idx2: request_data.idx2,
				from: request_data.from,
				to: request_data.to
			},
			success: function(json) {
				updateTimeSelectorUI(json);
			}
		});
	}

	if (!$container.data('disable-initial-check')) {
		checkDisableTimeSelectorUI();
	}
});

/**
 * flickerfreeScreen refresh on time selector change.
 */
jQuery.subscribe('timeselector.rangeupdate', function(e, data) {
	if (window.flickerfreeScreen) {
		window.flickerfreeScreen.refreshAll(data);
	}
});

// graphs timeline controls (gtlc)
var timeControl = {

	// data
	objectList: {},

	// options
	refreshPage: true,

	addObject: function(id, time, objData) {
		if (typeof this.objectList[id] !== 'undefined' && objData['reloadOnAdd'] !== 1) {
			// Do not reload object twice if not asked to.
			return;
		}

		this.removeObject(id);

		this.objectList[id] = jQuery.extend({
			id: id,
			containerid: null,
			refresh: false,
			processed: 0,
			timeline: time,
			objDims: {},
			src: location.href,
			dynamic: 1,
			loadSBox: 0,
			loadImage: 0
		}, objData);

		var _this = this;
		this.objectList[id].objectUpdate = function(e, data) {
			_this.objectUpdate.call(_this.objectList[id], data);
		};
		jQuery.subscribe('timeselector.rangeupdate', this.objectList[id].objectUpdate);
	},

	removeObject: function(id) {
		if (typeof this.objectList[id] !== 'undefined') {
			jQuery.unsubscribe('timeselector.rangeupdate', this.objectList[id].objectUpdate);

			delete this.objectList[id];
		}
	},

	processObjects: function() {
		// load objects
		for (var id in this.objectList) {
			if (!empty(this.objectList[id]) && !this.objectList[id].processed) {
				var obj = this.objectList[id];

				obj.processed = 1;

				// width
				if ((!isset('width', obj.objDims) || obj.objDims.width < 0) && isset('shiftXleft', obj.objDims) && isset('shiftXright', obj.objDims)) {
					var width = $('.wrapper')[0].scrollWidth - 20;

					if (!is_number(width)) {
						width = 1000;
					}
					if (!isset('width', obj.objDims)) {
						obj.objDims.width = 0;
					}

					obj.objDims.width += width - (parseInt(obj.objDims.shiftXleft) + parseInt(obj.objDims.shiftXright)) - 3;
				}

				// url
				if (isset('graphtype', obj.objDims)) {
					// graph size might have changed regardless of graph's type

					var graphUrl = new Curl(obj.src, false);
					graphUrl.setArgument('width', Math.floor(obj.objDims.width));
					graphUrl.setArgument('height', Math.floor(obj.objDims.graphHeight));

					obj.src = graphUrl.getUrl();
				}

				// image
				if (obj.loadImage) {
					if (!obj.refresh) {
						this.addImage(id);
					}
					else {
						this.refreshImage(id);
					}
				}
			}
		}
	},

	addImage: function(id) {
		var obj = this.objectList[id],
			img = jQuery('#' + id),
			zbx_sbox = {
				left: obj.objDims.shiftXleft,
				right: obj.objDims.shiftXright,
				top: obj.objDims.shiftYtop,
				height: obj.objDims.graphHeight,
				from: obj.timeline.from,
				to: obj.timeline.to,
				from_ts: obj.timeline.from_ts,
				to_ts: obj.timeline.to_ts
			},
			url = new Curl(obj.src, false);

		url.setArgument('_', (new Date()).getTime().toString(34));

		if (img.length == 0) {
			window.flickerfreeScreen.setElementProgressState(obj.id, true);
			img = jQuery('<img>', {id: id}).appendTo(('#'+obj.containerid)).on('load', function() {
				window.flickerfreeScreen.setElementProgressState(obj.id, false);
				img.closest('.dashboard-grid-widget').trigger('load.image', {imageid: id});
			});

			var xhr = (obj.loadSBox == 0)
				? null
				: flickerfreeScreen.getImageSboxHeight(url, function (height) {
					zbx_sbox.height = parseInt(height, 10);
					img.data('zbx_sbox', zbx_sbox).attr('src', obj.src);
				});

			if (xhr === null) {
				img.attr('src', url.getUrl());
			}

			if (obj.loadSBox == 1) {
				img.data('zbx_sbox', zbx_sbox);
			}
		}
	},

	refreshImage: function(id) {
		var obj = this.objectList[id],
			url = new Curl(obj.src, false),
			img = jQuery('#' + id),
			zbx_sbox = img.data('zbx_sbox');

		if (zbx_sbox && zbx_sbox.prevent_refresh) {
			return;
		}

		window.flickerfreeScreen.setElementProgressState(obj.id, true);
		url.setArgument('_', (new Date()).getTime().toString(34));
		url.setArgument('from', obj.timeline.from);
		url.setArgument('to', obj.timeline.to);

		var container = jQuery('#' + obj.containerid),
			clone = jQuery('<img>', {
				id: img.attr('id'),
				class: img.attr('class')
			})
			.one('load', function() {
				img.closest('.dashboard-grid-widget').trigger('load.image', {imageid: img.attr('id')});
				img.replaceWith(clone);
				window.flickerfreeScreen.setElementProgressState(obj.id, false);
			});

		var async = (obj.loadSBox == 0)
			? null
			: flickerfreeScreen.getImageSboxHeight(url, function (height) {
				zbx_sbox.height = parseInt(height, 10);
				clone.data('zbx_sbox', zbx_sbox)
					.attr('src', url.getUrl());
			});

		if (async === null) {
			clone.attr('src', url.getUrl());
		}
		else {
			clone.data('zbx_sbox', jQuery.extend(zbx_sbox, {
				left: obj.objDims.shiftXleft,
				right: obj.objDims.shiftXright,
				top: obj.objDims.shiftYtop,
				from: obj.timeline.from,
				from_ts: obj.timeline.from_ts,
				to: obj.timeline.to,
				to_ts: obj.timeline.to_ts
			}));
		}

		// link
		var graphUrl = new Curl(container.attr('href'), false);
		graphUrl.setArgument('width', obj.objDims.width);
		graphUrl.setArgument('from', obj.timeline.from);
		graphUrl.setArgument('to', obj.timeline.to);

		container.attr('href', graphUrl.getUrl());
	},

	refreshObject: function(id) {
		this.objectList[id].processed = 0;
		this.objectList[id].refresh = true;
		this.processObjects();
	},

	disableAllSBox: function() {
		jQuery.each(this.objectList, function(i, obj) {
			if (obj.loadSBox == 1) {
				jQuery('#'+obj.containerid).removeClass('dashboard-widget-graph-link');
			}
		});
		jQuery(document).off('dblclick mousedown', 'img');
	},

	/**
	 * Update object timeline data. Will reload page when timeConrol.refreshPage is set to true.
	 *
	 * @param {object} data     Object passed by 'timeselector.rangeupdate'.
	 */
	objectUpdate: function(data) {
		if (timeControl.refreshPage) {
			var url = new Curl(location.href, false);
			url.unsetArgument('output');

			// Always reset "page" when reloading with updated time range.
			url.unsetArgument('page');

			location.href = url.getUrl();
		}
		else {
			this.timeline = jQuery.extend(this.timeline, {
				from: data.from,
				from_ts: data.from_ts,
				to: data.to,
				to_ts: data.to_ts
			});
		}
	}
};
