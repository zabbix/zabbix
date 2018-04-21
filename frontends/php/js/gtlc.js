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

		if (target.is(element.increment)) {
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
		element.from.val(data[(data.event === 'rangechange') ? 'from' : 'from_date']);
		element.to.val(data[(data.event === 'rangechange') ? 'to' : 'to_date']);
		element.label.text(data.label);
		element.decrement.attr('disabled', !data.can_decrement);
		element.zoomout.attr('disabled', !data.can_zoomout);
		element.increment.attr('disabled', !data.can_increment);
	}

	/**
	 * Show or hide associated to button calendar picker.
	 */
	function toggleCalendarPickerHandler() {
		var button = $(this,)
			offset = button.offset();

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

		// TODO: ignore events and block ui if there is request in progress state. Do not silently abort it.
		if (xhr && xhr.abort) {
			xhr.abort();
		}

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
	var selection = null;
	$(document).on('mousedown', 'img', selectionHandleDragStart);

	/**
	 * Handle selection box drag start event.
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandleDragStart(e) {
		var target = $(e.target),
			data = target.data();

		if (typeof data.sbox_height === 'undefined') {
			return;
		}

		var offset = target.offset(),
			left = offset.left + data.sbox_left,
			right = offset.left + target.width() - data.sbox_right,
			x = Math.min(Math.max(left, e.pageX), right),
			// TODO: should be taken from object data! 12px shift from top objDims.shiftYtop
			margin_top = 12;

		offset.top += 12;
		if ((e.pageY < offset.top) || e.pageY > offset.top + parseInt(data.sbox_height, 10)) {
			return;
		}

		selection = {
			dom: $('<div class="graph-selection"/>').css({
				position: 'absolute',
				top: offset.top,
				left: x,
				height: data.sbox_height + 'px',
				width: '1px'
			}).appendTo(document.body),
			min: left,
			max: right,
			x: x,
			// TODO: above should be initialized when sbox is added to timeline or refreshed.
			seconds_per_px: 28,
			from_ts: 0
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

		$.publish('timeselector.rangechange', {
			from: from,
			to: to
		});

		selection.dom.remove();
		selection = null;
		$(document)
			.off('mouseup', selectionHandlerDragEnd)
			.off('mousemove', selectionHandlerDrag);
	}

	/**
	 * Handle selection box drag event
	 *
	 * @param {object} e    jQuery event object.
	 */
	function selectionHandlerDrag(e) {
		var x = Math.min(Math.max(selection.min, e.pageX), selection.max),
			width = Math.abs(x - selection.x),
			seconds = Math.round(width * selection.seconds_per_px),
			label = formatTimestamp(seconds, false, true)
				+ (seconds < 60 ? ' [min 1' + locale['S_MINUTE_SHORT'] + ']'  : '');

		selection.dom.css({
			left: Math.min(selection.x, x),
			width: width + 'px'
		}).text(label);
	}
});


// graphs timeline controls (gtlc)
var timeControl = {

	// data
	objectList: {},
	timeline: null,

	// options
	refreshPage: true,
	timeRefreshInterval: 0,
	timeRefreshTimeoutHandler: null,

	addObject: function(id, time, objData) {
		if (typeof this.objectList[id] === 'undefined'
				|| (typeof(objData['reloadOnAdd']) !== 'undefined' && objData['reloadOnAdd'] === 1)) {
			this.objectList[id] = {
				id: id,
				containerid: null,
				refresh: false,
				processed: 0,
				time: {},
				objDims: {},
				src: location.href,
				dynamic: 1,
				// TODO: remove
				loadSBox: 0,
				loadImage: 0,
				loadScroll: 0,
				mainObject: 0, // object on changing will reflect on all others
				onDashboard: 0, // object is on dashboard
				profile: { // if values are not null, will save timeline and fixedperiod state here, on change
					idx: null,
					idx2: null
				}
			};

			for (var key in this.objectList[id]) {
				if (isset(key, objData)) {
					this.objectList[id][key] = objData[key];
				}
			}

			this.objectList[id].time = time;
		}
	},

	processObjects: function() {
		// create timeline and scrollbar
		for (var id in this.objectList) {
			if (!empty(this.objectList[id]) && !this.objectList[id].processed && this.objectList[id].loadScroll) {
				var obj = this.objectList[id];

				obj.processed = 1;

				// timeline
				var nowDate = new CDate(),
					now = parseInt(nowDate.getTime() / 1000);

				if (!isset('period', obj.time)) {
					obj.time.period = 3600;
				}
				if (!isset('endtime', obj.time)) {
					obj.time.endtime = now;
				}
				if (!isset('isNow', obj.time)) {
					obj.time.isNow = false;
				}

				obj.time.starttime = (!isset('starttime', obj.time) || is_null(obj.time['starttime']))
					? obj.time.endtime - 3 * ((obj.time.period < 86400) ? 86400 : obj.time.period)
					: nowDate.setZBXDate(obj.time.starttime) / 1000;

				obj.time.usertime = (!isset('usertime', obj.time) || obj.time.isNow)
					? obj.time.endtime
					: nowDate.setZBXDate(obj.time.usertime) / 1000;

				// this.timeline = new CTimeLine(
				// 	parseInt(obj.time.period),
				// 	parseInt(obj.time.starttime),
				// 	parseInt(obj.time.usertime),
				// 	parseInt(obj.time.endtime),
				// 	obj.sliderMaximumTimePeriod,
				// 	obj.time.isNow
				// );

				// scrollbar
				var width = get_bodywidth() - 100;

				if (!is_number(width)) {
					width = 900;
				}
				else if (width < 600) {
					width = 600;
				}

				// this.scrollbar = new CScrollBar(width, obj.periodFixed, obj.sliderMaximumTimePeriod, obj.profile);
				// this.scrollbar.onchange = this.objectUpdate.bind(this);
			}
		}


		// load objects
		for (var id in this.objectList) {
			if (!empty(this.objectList[id]) && !this.objectList[id].processed && !this.objectList[id].loadScroll) {
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
						this.addImage(id, false);
					}
				}

				// refresh
				if (obj.refresh) {
					this.refreshImage(id);
				}
			}
		}
	},

	addImage: function(id, rebuildListeners) {
		var obj = this.objectList[id],
			img = jQuery('#' + id);

		if (img.length == 0) {
			img = jQuery('<img/>').attr('id', id).appendTo(('#'+obj.containerid));

			img.data('sbox_left', obj.objDims.shiftXleft);
			img.data('sbox_right', obj.objDims.shiftXright);

			var xhr = flickerfreeScreen.getImageSboxHeight(new Curl(obj.src), function (height) {
				img.attr('src', obj.src);
				img.data('sbox_height', height);
			});

			if (xhr === null) {
				img.attr('src', obj.src);
			}
		}

		img.data('from', obj.time.from);
		img.data('to', obj.time.to);
	},

	refreshImage: function(id) {
		// image
		var url = new Curl(obj.src);
		url.setArgument('from', obj.time.from);
		url.setArgument('to', obj.time.to);

		var img = jQuery('<img />', {id: id + '_tmp'})
			.on('load', function() {
				var imgId = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));

				jQuery(this).unbind('load');
				jQuery('#' + imgId).replaceWith(jQuery(this));
				jQuery(this).attr('id', imgId);

				// Update dashboard widget footer.
				if (obj.onDashboard) {
					timeControl.updateDashboardFooter(id);
				}
			}),
			xhr = flickerfreeScreen.getImageSboxHeight(url, function (height) {
				img.data('sbox_height', height)
					.attr('src', url.getUrl());
			});

		if (xhr === null) {
			img.attr('src', url.getUrl());
		}

		// link
		var graphUrl = new Curl(jQuery('#' + obj.containerid).attr('href'));
		graphUrl.setArgument('width', obj.objDims.width);
		graphUrl.setArgument('from', obj.time.from);
		graphUrl.setArgument('to', obj.time.to);

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
			url = new Curl('zabbix.php'),
			post_args = {
				uniqueid: widget['uniqueid'],
				only_footer: 1
			};

		if (widget.type === 'graph') {
			post_args.period = this.timeline.period();
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
			// timeline
			if (this.timeline.isNow()) {
				this.timeline.setNow();
			}
			else {
				this.timeline.refreshEndtime();
			}

			// plan next time update
			this.timeRefreshTimeoutHandler = window.setTimeout(function() { timeControl.refreshTime(); }, this.timeRefreshInterval);
		}
	},

	objectUpdate: function() {
		// var usertime = this.timeline.usertime(),
		// 	period = this.timeline.period(),
		// 	isNow = (this.timeline.now() || this.timeline.isNow());

		// secure browser from fast user operations
		// if (isNaN(usertime) || isNaN(period)) {
		// 	for (var id in this.objectList) {
		// 		if (isset(id, ZBX_SBOX)) {
		// 			ZBX_SBOX[id].clearParams();
		// 		}
		// 	}

		// 	return;
		// }

		var date = new CDate((usertime - period) * 1000),
			stime = date.getZBXDate();

		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('from', this.timeline.from);
			url.setArgument('to', this.timeline.to);
			url.unsetArgument('output');

			location.href = url.getUrl();
		}
		else {
			// TODO: This should be done as event!!!
			var url = new Curl('zabbix.php');
			url.setArgument('action', 'timeline.update');

			sendAjaxData(url.getUrl(), {
				data: {
					idx: '', // this.scrollbar.profile.idx,
					idx2: '', // this.scrollbar.profile.idx2,
					from: this.timeline.from,
					to: this.timeline.to
				}
			});

			jQuery.publish('timeselector.rangechange', {from: this.timeline.from, to: this.timeline.to});

			flickerfreeScreen.refreshAll(this.timeline.from, this.timeline.to);
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
			flickerfreeScreen.refreshAll(this.timeline.from, this.timeline.to);
		}
	}
};
