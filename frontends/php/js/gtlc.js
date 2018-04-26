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
	var container = $('#filter-space'),
		xhr = null,
		endpoint = new Curl('jsrpc.php'),
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
		ui_disabled = false,
		timerange = {from: element.from.val(), to: element.to.val()};

	endpoint.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON

	$.subscribe('timeselector.rangechange timeselector.decrement timeselector.increment timeselector.zoomout',
		timeselectorEventHandler
	);

	// Time selectorm DOM elements event triggerers initialization.
	container.on('click', function (e) {
		var event = '',
			data = {},
			target = $(e.target);

		if (ui_disabled) {
			e.preventDefault();
			return false;
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
				to: element.to.val(),
				collapse: true
			}
		}
		else if (element.quickranges.index(target) != -1) {
			event = 'timeselector.rangechange';
			data = target.data();
			data.collapse = true;
		}

		if (event !== '') {
			$.publish(event, data);
		}
	});

	// Calendar toggle visibility handlers initialization.
	$([element.from_clndr, element.to_clndr]).each(function () {
		var button = $(this),
			input = element[button.is(element.from_clndr) ? 'from' : 'to'].get(0);

		button.data('clndr', create_calendar(null, input, null, '', ''))
			.data('input', input)
			.click(toggleCalendarPickerHandler);
	});

	/**
	 * Time selector UI update.
	 *
	 * @param {object} data Server response on 'timeselector.rangechange' request.
	 */
	function updateTimeselectorUI(data) {
		var is_timestamp = /^\d+$/;

		element.from.val(is_timestamp.test(data['from']) ? data['from_date'] : data['from']);
		element.to.val(is_timestamp.test(data['to']) ? data['to_date'] : data['to']);
		element.label.text(data.label);

		$([element.from[0], element.to[0], element.apply[0], element.decrement[0], element.zoomout[0],
			element.increment[0]
		]).attr('disabled', false);

		element.from_clndr.data('clndr').clndr.clndrhide();
		element.to_clndr.data('clndr').clndr.clndrhide();

		if (data.collapse) {
			element.label.closest('.ui-tabs-collapsible').tabs('option', 'active', false);
		}

		element.apply.closest('.ui-tabs-panel').removeClass('in-progress');
		ui_disabled = false;
	}

	/**
	 * Disable time selector UI.
	 */
	function disableTimeselectorUI() {
		element.apply.closest('.ui-tabs-panel').addClass('in-progress');
		$([element.from[0], element.to[0], element.apply[0], element.decrement[0], element.zoomout[0],
			element.increment[0]
		]).attr('disabled', true);
		element.from_clndr.data('clndr').clndr.clndrhide();
		element.to_clndr.data('clndr').clndr.clndrhide();
		ui_disabled = true;
	}

	/**
	 * Show or hide associated to button calendar picker.
	 */
	function toggleCalendarPickerHandler(e) {
		var button = $(this,)
			offset = button.offset();

		if (ui_disabled) {
			e.preventDefault();
			return false;
		}

		button.data('clndr').clndr.clndrshow(parseInt(offset.top + button.outerHeight(), 10), parseInt(offset.left, 10),
			button.data('input')
		);
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
	 *
	 * @param {object} e        jQuery event object.
	 * @param {object} data     Object with published data for event.
	 */
	function timeselectorEventHandler(e, data) {
		endpoint.setArgument('method', [e.type, e.namespace].join('.'));

		if (xhr && xhr.abort) {
			return;
		}

		disableTimeselectorUI();

		xhr = $.post(endpoint.getUrl(), (e.namespace === 'rangechange') ? {from: data.from, to: data.to} : timerange,
			'json'
		)
			.success(function (json) {
				timerange = json.result;

				updateTimeselectorUI($.extend(timerange, data, {event: e.namespace}));
				$.publish('timeselector.rangeupdate', timerange);
			})
			.always(function () {
				xhr = null;
			});
	}

	// Time selection box for graphs.
	var selection = null,
		anchor = null,
		noclick_area = null;

	$(document).on('mousedown', 'img', selectionHandleDragStart);

	/**
	 * Handle selection box drag start event.
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandleDragStart(e) {
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
		 * @prop {integer} data.height      Height of selection box.
		 * @prop {integer} data.left        Left margin of selection box.
		 * @prop {integer} data.right       Right margin of selection box.
		 * @prop {integer} data.top         Top margin of selection box.
		 * @prop {integer} data.period      Period length in seconds of selection box.
		 * @prop {integer} data.timestamp   Timestamp for start time of selection box.
		 */
		data = data.zbx_sbox;

		var offset = target.offset(),
			left = offset.left - 10 + data.left,
			right = offset.left - 10 + target.width() - data.right,
			xpos = Math.min(Math.max(left, e.pageX), right);

		offset.top += data.top;
		if ((e.pageY < offset.top) || e.pageY > offset.top + data.height) {
			return;
		}

		noclick_area = $('<div/>').css({
			position: 'absolute',
			top: 0,
			left: 0,
			height: target.outerHeight() + 'px',
			width: target.outerWidth() + 'px'
		}).insertAfter(target);

		selection = {
			dom: $('<div class="graph-selection"/>').css({
				position: 'absolute',
				top: data.top,
				left: xpos,
				height: data.height + 'px',
				width: '1px'
			}).insertAfter(noclick_area),
			min: left,
			max: right,
			base_x: xpos,
			seconds_per_px: parseInt(data.period/(right - left)),
			from_ts: data.timestamp
		}

		$(document)
			.on('mouseup', selectionHandlerDragEnd)
			.on('mousemove', selectionHandlerDrag);

		return false;
	}

	/**
	 * Handle selection box drag end event.
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandlerDragEnd(e) {
		var from = selection.from_ts + (selection.dom.offset().left - selection.min) * selection.seconds_per_px,
			to = from + selection.dom.width() * selection.seconds_per_px;

		selection.dom.remove();
		selection = null;
		$(document)
			.off('mouseup', selectionHandlerDragEnd)
			.off('mousemove', selectionHandlerDrag);

		noclick_area.remove();
		noclick_area = null;

		$.publish('timeselector.rangechange', {
			from: Math.round(from),
			to: Math.round(to)
		});
	}

	/**
	 * Handle selection box drag event
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandlerDrag(e) {
		var x = Math.min(Math.max(selection.min, e.pageX), selection.max),
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
 * flickerfreeScreen refresh on timeselector change.
 */
jQuery.subscribe('timeselector.rangeupdate', function(e, data) {
	window.flickerfreeScreen.refreshAll(data.from, data.to);
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
				onDashboard: 0, // object is on dashboard
				profile: { // if values are not null, will save timeline and fixedperiod state here, on change
					idx: null,
					idx2: null
				}
			}, objData);

			if (this.objectList[id].loadSBox) {
				jQuery.subscribe('timeselector.rangeupdate', function(e, data) {
					timeControl.objectList[id].timeline.from = data.from;
					timeControl.objectList[id].timeline.to = data.to;
					timeControl.objectUpdate.bind(this);
				});
			}
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
					graphUrl.setArgument('width', obj.objDims.width);

					obj.src = graphUrl.getUrl();
				}

				// image
				if (obj.loadImage) {
					if (!obj.refresh) {
						this.addImage(id);
					}
				}

				// refresh
				if (obj.timeline.refreshable) {
					this.refreshImage(obj);
				}
			}
		}
	},

	addImage: function(id) {
		var obj = this.objectList[id],
			img = jQuery('#' + id);

		if (img.length == 0) {
			img = jQuery('<img/>').attr('id', id).appendTo(('#'+obj.containerid));

			var xhr = flickerfreeScreen.getImageSboxHeight(new Curl(obj.src), function (height) {
				img.data('zbx_sbox', {
					height: parseInt(height, 10),
					left: obj.objDims.shiftXleft,
					right: obj.objDims.shiftXright,
					top: obj.objDims.shiftYtop,
					period: obj.timeline.period,
					timestamp: obj.timeline.from_ts
				}).attr('src', obj.src);
			});

			if (xhr === null) {
				img.attr('src', obj.src);
			}
		}

		img.data('from', obj.timeline.from);
		img.data('to', obj.timeline.to);
	},

	refreshImage: function(obj) {
		// image
		var id = obj.id,
			url = new Curl(obj.src);
		url.setArgument('from', obj.timeline.from);
		url.setArgument('to', obj.timeline.to);

		var img = jQuery('#' + id).clone()
				.on('load', function() {
					jQuery(this).unbind('load');
					jQuery('#' + id).attr('src', jQuery(this).attr('src'));

					// Update dashboard widget footer.
					if (obj.onDashboard) {
						timeControl.updateDashboardFooter(id);
					}
				}),
			async = flickerfreeScreen.getImageSboxHeight(url, function (height) {
				img.data('zbx_sbox', {
					height: parseInt(height, 10),
					left: obj.objDims.shiftXleft,
					right: obj.objDims.shiftXright,
					top: obj.objDims.shiftYtop,
					period: obj.timeline.period,
					timestamp: obj.timeline.from_ts
				}).attr('src', url.getUrl());
			});

		if (async === null) {
			img.attr('src', url.getUrl());
		}

		// link
		var graphUrl = new Curl(jQuery('#' + obj.containerid).attr('href'));
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

				if ('period_string' in resp) {
					jQuery('h4 span', widget['content_header']).text(resp.period_string);
				}
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

	objectUpdate: function() {
		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('from', this.timeline.from);
			url.setArgument('to', this.timeline.to);
			url.unsetArgument('output');

			location.href = url.getUrl();
		}
		else if (this.profile) {
			var url = new Curl('zabbix.php');
			url.setArgument('action', 'timeline.update');

			sendAjaxData(url.getUrl(), {
				data: {
					idx: this.profile.idx,
					idx2: this.profile.idx2,
					from: this.timeline.from,
					to: this.timeline.to
				}
			});
		}
	},

	objectReset: function() {
		// TODO: Should be saved as some kind of 'default' setting. Is used by webscenario, graph
		// http://z/trunk/frontends/php/httpdetails.php?httptestid=1
		this.timeline.to = 'now';
		this.timeline.from = 'now-1h';

		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('from', this.timeline.from);
			url.setArgument('to', this.timeline.to);
			url.unsetArgument('output');

			location.href = url.getUrl();
		}
		else {
			jQuery.publish('timeselector.rangechange', {from: this.timeline.from, to: this.timeline.to});
		}
	}
};
