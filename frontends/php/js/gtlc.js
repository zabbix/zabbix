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
 * jQuery based publish/subscribe handler.
 *
 * - $.subscribe(event_name, callback)
 * - $.unsubscribe(event_name, callback)
 * - $.publish(event_name, data_object)
 *
 */
(function($) {
	var pubsub = $({});

	$.subscribe = function() {
		pubsub.on.apply(pubsub, arguments);
	};

	$.unsubscribe = function() {
		pubsub.off.apply(pubsub, arguments);
	};

	$.publish = function() {
		pubsub.trigger.apply(pubsub, arguments);
	};
}(jQuery));

// Time range selector.
jQuery(function ($){
	var container = $('.filter-space').first(),
		xhr = null,
		endpoint = new Curl('zabbix.php'),
		element = {
			from: container.find('[name=from]'),
			to: container.find('[name=to]'),
			from_clndr: container.find('[name=from_calendar]'),
			to_clndr: container.find('[name=to_calendar]'),
			apply: container.find('[name=apply]'),
			increment: container.find('.btn-time-right'),
			decrement: container.find('.btn-time-left'),
			zoomout: container.find('.btn-time-out'),
			quickranges: container.find('.time-quick a'),
			label: container.find('.btn-time')
		},
		request_data = {
			idx: container.length ? container.data()['profileIdx'] : '',
			idx2: container.length ? container.data()['profileIdx2'] : 0,
			from: element.from.val(),
			to: element.to.val()
		},
		ui_disabled = false;

	endpoint.setArgument('action', 'timeselector.update');
	endpoint.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON

	$.subscribe('timeselector.rangechange timeselector.decrement timeselector.increment timeselector.zoomout'+
		' timeselector.rangeoffset',
		timeSelectorEventHandler
	);

	element.from.keydown(submitChangeHandler);
	element.to.keydown(submitChangeHandler);

	// Time selector DOM elements event triggerers initialization.
	container.on('click', function (e) {
		var event = '',
			data = {},
			target = $(e.target);

		if (ui_disabled) {
			return cancelEvent(e);
		}
		else if (target.is(element.increment)) {
			event = 'timeselector.increment';
		}
		else if (target.is(element.decrement)) {
			event = 'timeselector.decrement';
		}
		else if (target.is(element.zoomout)) {
			event = 'timeselector.zoomout';
		}
		else if (target.is(element.apply)) {
			event = 'timeselector.rangechange';
			data = {
				from: element.from.val(),
				to: element.to.val()
			}
		}
		else if (element.quickranges.index(target) != -1) {
			event = 'timeselector.rangechange';
			data = target.data();
			element.quickranges.removeClass('selected');
			target.addClass('selected');
		}

		if (event !== '') {
			$.publish(event, data);
		}
	});

	// Calendar toggle visibility handlers initialization.
	if (element.from_clndr.length && element.to_clndr.length) {
		$([element.from_clndr, element.to_clndr]).each(function () {
			var button = $(this),
				input = element[button.is(element.from_clndr) ? 'from' : 'to'].get(0);

			button.data('clndr', create_calendar(null, input, null, button.attr('id'), ''))
				.data('input', input)
				.click(toggleCalendarPickerHandler);
		});
	}

	/**
	 * Trigger timeselector.rangechange event on 'enter' key press in 'from' or 'to' input field.
	 *
	 * @param {object} e jQuery event object.
	 */
	function submitChangeHandler(e) {
		if (e.which == 13) {
			$.publish('timeselector.rangechange', {
				from: element.from.val(),
				to: element.to.val()
			});
			return cancelEvent(e);
		}
	}

	/**
	 * Time selector UI update.
	 *
	 * @param {object} data Server response on 'timeselector.rangechange' request.
	 */
	function updateTimeSelectorUI(data) {
		if ('error' in data === false) {
			element.from.val(data.from);
			element.to.val(data.to);
			element.label.text(data.label);
		}

		$([element.from[0], element.to[0], element.apply[0]]).attr('disabled', false);

		$.each({
			decrement: data.can_decrement,
			increment: data.can_increment,
			zoomout: data.can_zoomout
		}, function (elm, state) {
			if (state) {
				element[elm].removeAttr('disabled');
			}
			else {
				element[elm].attr('disabled', true);
			}

			element[elm].removeClass('disabled');
		});

		element.from_clndr.data('clndr').clndr.clndrhide();
		element.to_clndr.data('clndr').clndr.clndrhide();

		element.quickranges.removeClass('selected');
		element.quickranges.filter('[data-label="'+data.label+'"]').addClass('selected');

		element.apply.closest('.ui-tabs-panel').removeClass('in-progress');
		ui_disabled = false;
	}

	/**
	 * Disable time selector UI.
	 */
	function disableTimeSelectorUI() {
		element.apply.closest('.ui-tabs-panel').addClass('in-progress');
		$([element.from[0], element.to[0], element.apply[0]]).attr('disabled', true);
		$([element.decrement[0], element.zoomout[0], element.increment[0]]).addClass('disabled');
		element.from_clndr.data('clndr').clndr.clndrhide();
		element.to_clndr.data('clndr').clndr.clndrhide();
		ui_disabled = true;
	}

	/**
	 * Show or hide associated to button calendar picker.
	 *
	 * @param {object} e    jQuery event object.
	 */
	function toggleCalendarPickerHandler(e) {
		var button = $(this),
			offset = button.offset();

		if (!ui_disabled) {
			button.data('clndr').clndr.clndrshow(parseInt(offset.top + button.outerHeight(), 10),
				parseInt(offset.left, 10), button.data('input')
			);
		}

		return cancelEvent(e);
	}

	/**
	 * Time selector events handler. Any of current time selector interval changes will publish event
	 * 'timeselector.rangeupdate'.
	 *
	 * Handled events:
	 *   timeselector.rangechange    Event to apply new time selector from and to values.
	 *   timeselector.decrement      Event to decrement current time selector interval.
	 *   timeselector.increment      Event to increment current time selector interval.
	 *   timeselector.zoomout        Event to zoomout current time selector interval.
	 *   timeselector.rangeoffset    Event to apply offset to from and to values.
	 *
	 * @param {object} e        jQuery event object.
	 * @param {object} data     Object with published data for event.
	 */
	function timeSelectorEventHandler(e, data) {
		var args = {
			'idx': request_data.idx,
			'idx2': request_data.idx2,
			'from': (e.namespace === 'rangechange') ? data.from : request_data.from,
			'to': (e.namespace === 'rangechange') ? data.to : request_data.to
		};

		if (e.namespace === 'rangeoffset') {
			args.from_offset = data.from_offset;
			args.to_offset = data.to_offset;
		}

		endpoint.setArgument('method', e.namespace);

		if (xhr && xhr.abort) {
			return;
		}

		disableTimeSelectorUI();

		xhr = $.ajax({
			url: endpoint.getUrl(),
			type: 'post',
			cache: false,
			data: args,
			success: function (json) {
				request_data = $.extend(data, request_data, json, {event: e.namespace});
				updateTimeSelectorUI(request_data);

				if (json.error) {
					container.find('.time-input-error').each(function (i, elm) {
						var node = $(elm),
							field = node.attr('data-error-for');

						if (json.error[field]) {
							node.show()
								.find('.red').text(json.error[field]);
						}
						else {
							node.hide();
						}
					});
					delete request_data.error;
				}
				else {
					updateUrlFromToArguments(json.from, json.to);
					container.find('.time-input-error').hide();
					$.publish('timeselector.rangeupdate', request_data);
				}

				xhr = null;
			},
			error: function () {
				var request = this,
					retry = function() {
						$.ajax(request);
					};

				// Retry with 2s interval.
				setTimeout(retry, 2000);
			}
		});
	}

	/**
	 * Replaces 'from' and/or 'to' URL arguments with new values in browser history.
	 *
	 * @param {string} from    Value for 'from' argument.
	 * @param {string} to      Value for 'to' argument.
	 */
	function updateUrlFromToArguments(from, to) {
		var url = new Curl(),
			args = url.getArguments();

		if ('from' in args) {
			url.setArgument('from', from);
		}

		if ('to' in args) {
			url.setArgument('to', to);
		}

		if (('from' in args) || ('to' in args)) {
			url.unsetArgument('sid');
			history.replaceState(history.state, '', url.getUrl());
		}
	}

	// Time selection box for graphs.
	var selection = null,
		anchor = null,
		noclick_area = null;

	$(document).on('mousedown', 'img', selectionHandlerDragStart);

	/**
	 * Handle selection box drag start event.
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandlerDragStart(e) {
		if (e.which !== 1) {
			return;
		}

		var target = $(e.target),
			data = target.data();

		if (typeof data.zbx_sbox === 'undefined') {
			return;
		}

		/**
		 * @prop {object}  data
		 * @prop {integer} data.height            Height of selection box.
		 * @prop {integer} data.left              Left margin of selection box.
		 * @prop {integer} data.right             Right margin of selection box.
		 * @prop {integer} data.top               Top margin of selection box.
		 * @prop {integer} data.from_ts           Timestamp for start time of selection box.
		 * @prop {integer} data.to_ts             Timestamp for end time of selection box.
		 * @prop {integer} data.prevent_refresh   Mark image as non updateable during selection.
		 */
		data = data.zbx_sbox;
		data.prevent_refresh = true;
		target.data('zbx_sbox', data);

		var offset = target.offset(),
			left = data.left,
			right = target.outerWidth() - data.right,
			xpos = Math.min(Math.max(left, e.pageX - offset.left), right);

		offset.top += data.top;
		if ((e.pageY < offset.top) || e.pageY > offset.top + data.height) {
			return;
		}

		noclick_area = $('<div/>').css({
			position: 'absolute',
			top: 0,
			left: 0,
			height: target.outerHeight() + 'px',
			width: target.outerWidth() + 'px',
			overflow: 'hidden'
		}).insertAfter(target);

		selection = {
			dom: $('<div class="graph-selection"/>').css({
				position: 'absolute',
				top: data.top,
				left: xpos,
				height: data.height + 'px',
				width: '1px'
			}).appendTo(noclick_area),
			offset: offset,
			min: left,
			max: right,
			base_x: xpos,
			seconds_per_px: (data.to_ts - data.from_ts)/(right - left)
		}

		$(document)
			.on('mouseup', selectionHandlerDragEnd)
			.on('mousemove', selectionHandlerDrag)
			.on('mouseup', function () {
				data.prevent_refresh = false;
				target.data('zbx_sbox', data.zbx_sbox);
			});

		return false;
	}

	/**
	 * Handle selection box drag end event.
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandlerDragEnd(e) {
		var from_offset = (selection.dom.position().left - selection.min) * selection.seconds_per_px,
			to_offset = (selection.max - selection.dom.width() - selection.dom.position().left) * selection.seconds_per_px;

		selection.dom.remove();
		selection = null;
		$(document)
			.off('mouseup', selectionHandlerDragEnd)
			.off('mousemove', selectionHandlerDrag);

		noclick_area.remove();
		noclick_area = null;

		if (from_offset > 0 || to_offset > 0) {
			$.publish('timeselector.rangeoffset', {
				from_offset: Math.ceil(from_offset),
				to_offset: Math.ceil(to_offset)
			});
		}
	}

	/**
	 * Handle selection box drag event
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandlerDrag(e) {
		var x = Math.min(Math.max(selection.min, e.pageX - selection.offset.left), selection.max),
			width = Math.abs(x - selection.base_x),
			seconds = Math.round(width * selection.seconds_per_px),
			label = formatTimestamp(seconds, false, true)
				+ (seconds < 60 ? ' [min 1' + locale['S_MINUTE_SHORT'] + ']'  : '');

		selection.dom.css({
			left: Math.min(selection.base_x, x),
			width: width + 'px'
		}).text(label);
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
	timeRefreshInterval: 0,
	timeRefreshTimeoutHandler: null,

	addObject: function(id, time, objData) {
		if (typeof this.objectList[id] === 'undefined'
				|| (typeof(objData['reloadOnAdd']) !== 'undefined' && objData['reloadOnAdd'] === 1)) {
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
				loadImage: 0,
				mainObject: 0, // object on changing will reflect on all others
				onDashboard: 0 // object is on dashboard
			}, objData);

			var objectUpdate = this.objectUpdate.bind(this.objectList[id]);
			jQuery.subscribe('timeselector.rangeupdate', function(e, data) {
				objectUpdate(data);
			});
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
					var width = get_bodywidth();

					if (!is_number(width)) {
						width = 1000;
					}
					if (!isset('width', obj.objDims)) {
						obj.objDims.width = 0;
					}

					obj.objDims.width += width - (parseInt(obj.objDims.shiftXleft) + parseInt(obj.objDims.shiftXright) + 23);
				}

				// url
				if (isset('graphtype', obj.objDims) && obj.objDims.graphtype < 2) {
					var graphUrl = new Curl(obj.src);
					graphUrl.unsetArgument('sid');
					graphUrl.setArgument('width', obj.objDims.width - 1);

					obj.src = graphUrl.getUrl();
				}

				// image
				if (obj.loadImage) {
					if (!obj.refresh) {
						this.addImage(id);
					}
					else if (this.isRefreshable(obj.timeline)) {
						this.refreshImage(id);
					}
				}

			}
		}
	},

	/**
	 * Returns is the supplied 'timeline' interval refreshable.
	 *
	 * @param {object} timeline
	 * @param {int}    timeline.from_ts    Interval 'from' value as timestamp.
	 * @param {int}    timeline.to_ts      Interval 'to' value as timestamp.
	 */
	isRefreshable: function(timeline) {
		var timestamp = Math.floor((new Date()).getTime()/1000),
			buffer = 300;

		return (timeline.from_ts - buffer <= timestamp && timestamp <= timeline.to_ts + buffer)
			|| (timeline.to.indexOf('/') == -1 && timeline.to.indexOf('now') != -1);
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
			img = jQuery('<img/>', {id: id}).appendTo(('#'+obj.containerid));

			var xhr = (obj.loadSBox == 0)
				? null
				: flickerfreeScreen.getImageSboxHeight(url, function (height) {
					zbx_sbox.height = parseInt(height, 10);
					img.data('zbx_sbox', zbx_sbox).attr('src', obj.src);
				});

			if (xhr === null) {
				img.attr('src', url.getUrl());
			}

			img.data('zbx_sbox', zbx_sbox);
		}
	},

	refreshImage: function(id) {
		var obj = this.objectList[id],
			url = new Curl(obj.src, false),
			img = jQuery('#' + id).last(),
			zbx_sbox = img.data('zbx_sbox');

		if (!this.isRefreshable(obj.timeline) || (zbx_sbox && zbx_sbox.prevent_refresh)) {
			return;
		}

		url.setArgument('_', (new Date()).getTime().toString(34));
		url.setArgument('from', obj.timeline.from);
		url.setArgument('to', obj.timeline.to);

		var clone = jQuery('<img/>', {
				id: img.attr('id'),
				'class': img.attr('class'),
				css: {
					position: 'absolute',
					top: 0,
					left: 0
				}
			})
			.on('load', function() {
				jQuery('#' + id)
					.first().css({position: 'relative'})
					.siblings('img').remove();

				// Update dashboard widget footer.
				if (obj.onDashboard) {
					timeControl.updateDashboardFooter(id);
				}
			});

		clone.data('zbx_sbox', jQuery.extend(zbx_sbox, {
			left: obj.objDims.shiftXleft,
			right: obj.objDims.shiftXright,
			top: obj.objDims.shiftYtop,
			from: obj.timeline.from,
			from_ts: obj.timeline.from_ts,
			to: obj.timeline.to,
			to_ts: obj.timeline.to_ts
		}));

		var async = (obj.loadSBox == 0)
			? null
			: flickerfreeScreen.getImageSboxHeight(url, function (height) {
				zbx_sbox.height = parseInt(height, 10);
				clone.data('zbx_sbox', zbx_sbox)
					.attr('src', url.getUrl())
					.insertBefore(img);
			});

		if (async === null) {
			clone.attr('src', url.getUrl())
				.insertBefore(img);
		}

		// link
		var graphUrl = new Curl(jQuery('#' + obj.containerid).attr('href'), false);
		graphUrl.setArgument('width', obj.objDims.width);
		graphUrl.setArgument('from', obj.timeline.from);
		graphUrl.setArgument('to', obj.timeline.to);

		jQuery('#' + obj.containerid).attr('href', graphUrl.getUrl());
	},

	/**
	 * Updates dashboard widget footer for specified graph
	 *
	 * @param {string} id  Id of img tag with graph.
	 */
	updateDashboardFooter: function (id) {
		var widgets = jQuery(".dashbrd-grid-widget-container")
				.dashboardGrid("getWidgetsBy", "uniqueid", id.replace('graph_', ''));

		if (widgets.length !== 1) {
			return;
		}

		var widget = widgets[0],
			obj = this.objectList[id],
			url = new Curl('zabbix.php'),
			post_args = {
				uniqueid: widget['uniqueid'],
				only_footer: 1
			};

		if (widget.type === 'graph') {
			post_args.from = obj.timeline.from;
			post_args.to = obj.timeline.to;
		}

		url.setArgument('action', 'widget.graph.view');
		jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: post_args,
			dataType: 'json',
			success: function(resp) {
				widget['content_footer'].html(resp.footer);
			}
		});
	},

	refreshObject: function(id) {
		this.objectList[id].processed = 0;
		this.objectList[id].refresh = true;
		this.processObjects();

		if (this.timeRefreshInterval > 0) {
			this.refreshTime();
		}
	},

	useTimeRefresh: function(timeRefreshInterval) {
		if (!empty(timeRefreshInterval) && timeRefreshInterval > 0) {
			this.timeRefreshInterval = timeRefreshInterval * 1000;
		}
	},

	refreshTime: function() {
		if (this.timeRefreshInterval > 0) {
			// plan next time update
			this.timeRefreshTimeoutHandler = window.setTimeout(function() { timeControl.refreshTime(); }, this.timeRefreshInterval);
		}
	},

	removeAllSBox: function() {
		jQuery.each(this.objectList, function(i, obj) {
			if (obj.loadSBox == 1) {
				obj.loadSBox = 0;
				jQuery('#'+obj.id).data('zbx_sbox', null);
			}
		});
	},

	/**
	 * Update object timeline data. Will reload page when timeConrol.refreshPage is set to true.
	 *
	 * @param {object} data     Object passed by 'timeselector.rangeupdate'.
	 */
	objectUpdate: function(data) {
		if (timeControl.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('from', data.from);
			url.setArgument('to', data.to);
			url.unsetArgument('output');

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
