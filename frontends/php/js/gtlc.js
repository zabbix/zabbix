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
		function (e, data) {
			endpoint.setArgument('method', [e.type, e.namespace].join('.'));

			if (xhr && xhr.abort) {
				xhr.abort();
			}

			xhr = $.post(endpoint.getUrl(), (e.namespace === 'rangechange') ? {from: data.from, to: data.to} : timerange,
				'json'
			)
				.success(function (json) {
					timerange = json.result;

					updateTimeselectorUI($.extend(timerange, data, {event: e.namespace});
					$.publish('timeselector.rangeupdate', timerange);
				})
				.always(function () {
					xhr = null;
				});
		}
	);

	// DOM element event triggerers initialization.
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
			.click(function() {
				var offset = button.offset();

				button.data('clndr').clndr.clndrshow(parseInt(offset.top + button.outerHeight(), 10),
					parseInt(offset.left, 10), input);
			});
	});

	// Update UI.
	function updateTimeselectorUI(data) {
		// Update 'from' and 'to' input elements value.
		element.from.val(data[(data.event === 'rangechange') ? 'from' : 'from_date']);
		element.to.val(data[(data.event === 'rangechange') ? 'to' : 'to_date']);
		// Update selected time range label.
		element.label.text(data.label);
		element.decrement.attr('disabled', !data.can_decrement);
		element.zoomout.attr('disabled', !data.can_zoomout);
		element.increment.attr('disabled', !data.can_increment);
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

	/**
	 * Changes height of sbox for given image
	 *
	 * @param {string} id       image HTML element id attribute
	 * @param {int}    height   new height for sbox
	 */
	changeSBoxHeight: function(id, height) {
		var obj = this.objectList[id],
			img = $(id);

		obj['objDims']['graphHeight'] = height;

		if (!empty(ZBX_SBOX[id])) {
			ZBX_SBOX[id].updateHeightBoxContainer(height);
		}

		if (obj.loadSBox && empty(obj.sbox_listener)) {
			obj.sbox_listener = this.addSBox.bindAsEventListener(this, id);
			addListener(img, 'load', obj.sbox_listener);
			addListener(img, 'load', sboxGlobalMove);
		}
	},

	processObjects: function() {
		// create timeline and scrollbar
		// for (var id in this.objectList) {
		// 	if (!empty(this.objectList[id]) && !this.objectList[id].processed && this.objectList[id].loadScroll) {
		// 		var obj = this.objectList[id];

		// 		obj.processed = 1;

		// 		// timeline
		// 		var nowDate = new CDate(),
		// 			now = parseInt(nowDate.getTime() / 1000);

		// 		if (!isset('period', obj.time)) {
		// 			obj.time.period = 3600;
		// 		}
		// 		if (!isset('endtime', obj.time)) {
		// 			obj.time.endtime = now;
		// 		}
		// 		if (!isset('isNow', obj.time)) {
		// 			obj.time.isNow = false;
		// 		}

		// 		obj.time.starttime = (!isset('starttime', obj.time) || is_null(obj.time['starttime']))
		// 			? obj.time.endtime - 3 * ((obj.time.period < 86400) ? 86400 : obj.time.period)
		// 			: nowDate.setZBXDate(obj.time.starttime) / 1000;

		// 		obj.time.usertime = (!isset('usertime', obj.time) || obj.time.isNow)
		// 			? obj.time.endtime
		// 			: nowDate.setZBXDate(obj.time.usertime) / 1000;

		// 		this.timeline = new CTimeLine(
		// 			parseInt(obj.time.period),
		// 			parseInt(obj.time.starttime),
		// 			parseInt(obj.time.usertime),
		// 			parseInt(obj.time.endtime),
		// 			obj.sliderMaximumTimePeriod,
		// 			obj.time.isNow
		// 		);

				// scrollbar
				// var width = get_bodywidth() - 100;

				// if (!is_number(width)) {
				// 	width = 900;
				// }
				// else if (width < 600) {
				// 	width = 600;
				// }

				// this.scrollbar = new CScrollBar(width, obj.periodFixed, obj.sliderMaximumTimePeriod, obj.profile);
				// this.scrollbar.onchange = this.objectUpdate.bind(this);
		// 	}
		// }


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
			img = $(id);

		if (empty(img)) {
			img = document.createElement('img');
			img.setAttribute('id', id);
			$(obj.containerid).appendChild(img);

			var xhr = flickerfreeScreen.getImageSboxHeight(new Curl(obj.src), function (height) {
				timeControl.changeSBoxHeight(id, height);
				img.setAttribute('src', obj.src);
			});

			if (xhr === null) {
				img.setAttribute('src', obj.src);
			}
		}

		// Apply sbox events to image.
		if (obj.loadSBox && empty(obj.sbox_listener) && img.hasAttribute('src')) {
			obj.sbox_listener = this.addSBox.bindAsEventListener(this, id);
			addListener(img, 'load', obj.sbox_listener);
			addListener(img, 'load', sboxGlobalMove);
		}
	},

	refreshImage: function(id) {
		// image
		var url = new Curl(obj.src);
		url.setArgument('from', this.timeline.from);
		url.setArgument('to', this.timeline.to);

		var img = jQuery('<img />', {id: id + '_tmp'})
			.on('load', function() {
				var imgId = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));

				jQuery(this).unbind('load');
				if (!empty(jQuery(this).data('height'))) {
					timeControl.changeSBoxHeight(id, jQuery(this).data('height'));
				}
				jQuery('#' + imgId).replaceWith(jQuery(this));
				jQuery(this).attr('id', imgId);

				// Update dashboard widget footer.
				if (obj.onDashboard) {
					timeControl.updateDashboardFooter(id);
				}
			}),
			xhr = flickerfreeScreen.getImageSboxHeight(url, function (height) {
				img.data('height', height)
					.attr('src', url.getUrl());
			});

		if (xhr === null) {
			img.attr('src', url.getUrl());
		}

		// link
		var graphUrl = new Curl(jQuery('#' + obj.containerid).attr('href'));
		graphUrl.setArgument('width', obj.objDims.width);
		graphUrl.setArgument('from', this.timeline.from);
		graphUrl.setArgument('to', this.timeline.to);

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
		// TODO: Should be saved as some kind of 'default' setting.
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
	},

	addSBox: function(e, id) {
		var sbox = sbox_init(id),
			self = this;
		sbox.onchange = this.objectUpdate.bind(this);
	},

	// Remove SBox from all objects in objectList.
	removeAllSBox: function() {
		for (var id in this.objectList) {
			if (!empty(this.objectList[id]) && this.objectList[id]['loadSBox'] == 1) {
				var obj = this.objectList[id],
					img = $(id);

				obj['loadSBox'] = 0;
				removeListener(img, 'load', obj.sbox_listener);
				removeListener(img, 'load', sboxGlobalMove);
				delete obj.sbox_listener;
				jQuery(".box_on").remove();
			}
		}
	}
};

var CGhostBox = Class.create({

	box:		null, // resized dom object
	sideToMove:	-1, // 0 - left side, 1 - right side

	// resize start position
	start: {
		width:		null,
		leftSide:	null,
		rightSide:	null
	},

	// resize in progress position
	current: {
		width:		null,
		leftSide:	null,
		rightSide:	null
	},

	initialize: function(id) {
		this.box = $(id);

		if (is_null(this.box)) {
			throw('Cannot initialize GhostBox with given object id.');
		}
	},

	startResize: function(side) {
		var dimensions = getDimensions(this.box);

		this.sideToMove = side;
		this.start.width = dimensions.width;
		this.start.leftSide = dimensions.left;
		this.start.rightSide = dimensions.right;
		this.box.style.zIndex = 20;
	},

	endResize: function() {
		this.sideToMove = -1;
		this.box.style.zIndex = 0;
	},

	calcResizeByPX: function(px) {
		px = parseInt(px, 10);

		// resize from the left
		if (this.sideToMove == 0) {
			var width = this.start.rightSide - this.start.leftSide - px;

			if (width < 0) {
				this.current.leftSide = this.start.rightSide;
			}
			else {
				this.current.leftSide = this.start.leftSide + px;
			}
			this.current.rightSide = this.start.rightSide;
		}
		// resize from the right
		else if (this.sideToMove == 1) {
			var width = this.start.rightSide - this.start.leftSide + px;

			if (width < 0) {
				this.current.rightSide = this.start.leftSide;
			}
			else {
				this.current.rightSide = this.start.rightSide + px;
			}
			this.current.leftSide = this.start.leftSide;
		}

		this.current.width = this.current.rightSide - this.current.leftSide;
	},

	resizeBox: function(px) {
		if (typeof(px) != 'undefined') {
			this.calcResizeByPX(px);
		}

		this.box.style.left = this.current.leftSide + 'px';
		this.box.style.width = this.current.width + 'px';
	}
});

// selection box uppon graphs
var ZBX_SBOX = {};

function sbox_init(id) {
	ZBX_SBOX[id] = new sbox(id);

	// global listeners
	addListener(window, 'resize', sboxGlobalMove);
	addListener(document, 'mouseup', sboxGlobalMouseup);
	addListener(document, 'mousemove', sboxGlobalMousemove);
	ZBX_SBOX[id].addListeners();
	return ZBX_SBOX[id];
}

var sbox = Class.create({

	sbox_id:			'',		// id to create references in array to self
	mouse_event:		{},		// json object wheres defined needed event params
	start_event:		{},		// copy of mouse_event when box created
	stime:				0,		// new start time
	period:				0,		// new period
	dom_obj:			null,	// selection div html obj
	box:				{},		// object params
	areaWidth:			0,
	areaHeight:			0,
	dom_box:			null,	// selection box html obj
	dom_period_span:	null,	// period container html obj
	shifts:				{},		// shifts regarding to main objet
	px2time:			null,	// seconds in 1px
	dynamic:			'',		// how page updates, all page/graph only update
	is_active:			false,	// flag show is sbox is selected, must be unique among all
	is_activeIE:		false,

	initialize: function(id) {
		var tc = timeControl.objectList[id],
			shiftL = parseInt(tc.objDims.shiftXleft),
			shiftR = parseInt(tc.objDims.shiftXright),
			width = getDimensions(id).width - (shiftL + shiftR) - 2;

		this.sbox_id = id;
		this.containerId = '#flickerfreescreen_' + id;
		this.shiftT = parseInt(tc.objDims.shiftYtop) + 1;
		this.shiftL = shiftL;
		this.shiftR = shiftR;
		this.additionShiftL = 0;
		this.areaWidth = width;
		this.areaHeight = parseInt(tc.objDims.graphHeight) + 1;
		this.box.width = width;
	},

	addListeners: function() {
		var obj = $(this.sbox_id);
		if (is_null(obj)) {
			throw('Failed to initialize Selection Box with given Object!');
		}

		this.clearParams();
		this.grphobj = obj;
		this.createBoxContainer();
		this.moveSBoxByObj();

		jQuery(this.grphobj).off();
		jQuery(this.dom_obj).off();

		if (IE9 || IE10) {
			jQuery(this.grphobj).mousedown(jQuery.proxy(this.mouseDown, this));
			jQuery(this.grphobj).mousemove(jQuery.proxy(this.mouseMove, this));
			jQuery(this.grphobj).click(function() {
				ZBX_SBOX[obj.sbox_id].ieMouseClick();
			});
		}
		else {
			jQuery(this.dom_obj).mousedown(jQuery.proxy(this.mouseDown, this));
			jQuery(this.dom_obj).mousemove(jQuery.proxy(this.mouseMove, this));
			jQuery(this.dom_obj).click(function(e) { cancelEvent(e); });
			jQuery(this.dom_obj).mouseup(jQuery.proxy(this.mouseUp, this));
		}
	},

	mouseDown: function(e) {
		e = e || window.event;

		if (e.which && e.which != 1) {
			return false;
		}
		else if (e.button && e.button != 1) {
			return false;
		}

		this.optimizeEvent(e);

		var posxy = getPosition(this.dom_obj);
		if (this.mouse_event.top < posxy.top || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) {
			return true;
		}

		cancelEvent(e);

		if (!this.is_active) {
			this.optimizeEvent(e);
			this.createBox();

			this.is_active = true;
			this.is_activeIE = true;
		}
	},

	mouseMove: function(e) {
		e = e || window.event;

		if (IE) {
			cancelEvent(e);
		}

		if (this.is_active) {
			this.optimizeEvent(e);

			// resize
			if (this.mouse_event.left > (this.areaWidth + this.additionShiftL)) {
				this.moveRight(this.areaWidth - this.start_event.left - this.additionShiftL);
			}
			else if (this.mouse_event.left < this.additionShiftL) {
				this.moveLeft(this.additionShiftL, this.start_event.left - this.additionShiftL);
			}
			else {
				var width = this.validateW(this.mouse_event.left - this.start_event.left),
					left = this.mouse_event.left - this.shifts.left;

				if (width > 0) {
					this.moveRight(width);
				}
				else {
					this.moveLeft(left, width);
				}
			}

			this.period = this.calcPeriod();

			if (!is_null(this.dom_box)) {
				this.dom_period_span.innerHTML = formatTimestamp(this.period, false, true) + (this.period < 60 ? ' [min 1' + locale['S_MINUTE_SHORT'] + ']'  : '');
			}
		}
	},

	mouseUp: function(e) {
		if (this.is_active) {
			cancelEvent(e);
			this.onSelect();
			this.clearParams();
		}
	},

	ieMouseClick: function(e) {
		if (!e) {
			e = window.event;
		}

		if (this.is_activeIE) {
			this.optimizeEvent(e);
			this.mouseUp(e);
			this.is_activeIE = false;

			return cancelEvent(e);
		}

		if (e.which && e.which != 1) {
			return true;
		}
		else if (e.button && e.button != 1) {
			return true;
		}

		this.optimizeEvent(e);

		var posxy = getPosition(this.dom_obj);
		if (this.mouse_event.top < posxy.top || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) {
			return true;
		}

		this.mouseUp(e);

		return cancelEvent(e);
	},

	onSelect: function() {
		this.px2time = timeControl.timeline.period() / this.areaWidth;
		var userstarttime = timeControl.timeline.usertime() - timeControl.timeline.period();
		userstarttime += Math.round((this.box.left - (this.additionShiftL - this.shifts.left)) * this.px2time);
		var new_period = this.calcPeriod();

		if (this.start_event.left < this.mouse_event.left) {
			userstarttime += new_period;
		}

		// timeControl.timeline.period(new_period);
		// timeControl.timeline.usertime(userstarttime);
		// timeControl.scrollbar.setBarPosition();
		// timeControl.scrollbar.setGhostByBar();
		// timeControl.scrollbar.setTabInfo();
		// timeControl.scrollbar.resetIsNow();
		this.onchange();
	},

	createBoxContainer: function() {
		var id = this.sbox_id + '_box_on';

		if (jQuery('#' + id).length) {
			jQuery('#' + id).remove();
		}

		this.dom_obj = document.createElement('div');
		this.dom_obj.id = id;
		this.dom_obj.className = 'box_on';
		this.dom_obj.style.height = this.areaHeight + 'px';

		jQuery(this.grphobj).parent().append(this.dom_obj);
	},

	updateHeightBoxContainer: function(height) {
		this.areaHeight = height;
		this.dom_obj.style.height = this.areaHeight + 'px';
	},

	createBox: function() {
		if (!jQuery('#selection_box').length) {
			this.dom_box = document.createElement('div');
			this.dom_obj.appendChild(this.dom_box);
			this.dom_period_span = document.createElement('span');
			this.dom_box.appendChild(this.dom_period_span);
			this.dom_period_span.setAttribute('id', 'period_span');
			this.dom_period_span.innerHTML = this.period;

			var dims = getDimensions(this.dom_obj);

			this.shifts.left = dims.offsetleft;
			this.shifts.top = dims.top;

			this.box.top = 0; // we use only x axis
			this.box.left = this.mouse_event.left - dims.offsetleft;
			this.box.height = this.areaHeight;

			this.dom_box.setAttribute('id', 'selection_box');
			this.dom_box.className = 'graph-selection';
			this.dom_box.style.top = this.box.top + 'px';
			this.dom_box.style.left = this.box.left + 'px';
			this.dom_box.style.height = this.areaHeight + 'px';
			this.dom_box.style.width = '1px';

			this.start_event.top = this.mouse_event.top;
			this.start_event.left = this.mouse_event.left;

			if (IE) {
				this.dom_box.onmousemove = this.mouseMove.bindAsEventListener(this);
			}
		}
	},

	moveLeft: function(left, width) {
		if (!is_null(this.dom_box)) {
			this.dom_box.style.left = left + 'px';
		}

		this.box.width = Math.abs(width);

		if (!is_null(this.dom_box)) {
			this.dom_box.style.width = this.box.width + 'px';
		}
	},

	moveRight: function(width) {
		if (!is_null(this.dom_box)) {
			this.dom_box.style.left = this.box.left + 'px';
		}
		if (!is_null(this.dom_box)) {
			this.dom_box.style.width = width + 'px';
		}

		this.box.width = width;
	},

	calcPeriod: function() {
		var new_period;

		if (this.box.width + 1 >= this.areaWidth) {
			new_period = timeControl.timeline.period();
		}
		else {
			this.px2time = timeControl.timeline.period() / this.areaWidth;
			new_period = Math.round(this.box.width * this.px2time);
		}

		return new_period;
	},

	validateW: function(w) {
		if ((this.start_event.left - this.additionShiftL + w) > this.areaWidth) {
			w = 0;
		}
		if (this.mouse_event.left < this.additionShiftL) {
			w = 0;
		}

		return w;
	},

	moveSBoxByObj: function() {
		var posxy = jQuery(this.grphobj).position();
		var dims = getDimensions(this.grphobj);

		this.dom_obj.style.top = this.shiftT + 'px';
		this.dom_obj.style.left = posxy.left + 'px';
		if (dims.width > 0) {
			this.dom_obj.style.width = dims.width + 'px';
		}

		this.additionShiftL = dims.offsetleft + this.shiftL;
	},

	optimizeEvent: function(e) {
		if (!empty(e.pageX) && !empty(e.pageY)) {
			this.mouse_event.left = e.pageX;
			this.mouse_event.top = e.pageY;
		}
		else if (!empty(e.clientX) && !empty(e.clientY)) {
			this.mouse_event.left = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
			this.mouse_event.top = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
		}
		else {
			this.mouse_event.left = parseInt(this.mouse_event.left);
			this.mouse_event.top = parseInt(this.mouse_event.top);
		}

		if (this.mouse_event.left < this.additionShiftL) {
			this.mouse_event.left = this.additionShiftL;
		}
		else if (this.mouse_event.left > (this.areaWidth + this.additionShiftL)) {
			this.mouse_event.left = this.areaWidth + this.additionShiftL;
		}
	},

	clearParams: function() {
		if (!is_null(this.dom_box)) {
			var id = jQuery(this.dom_box).attr('id');

			if (jQuery('#' + id).length) {
				jQuery('#' + id).remove();
			}
		}

		this.mouse_event = {};
		this.start_event = {};
		this.dom_box = null;
		this.shifts = {};
		this.box = {};
		this.box.width = 0;
		this.is_active = false;
	}
});

function sboxGlobalMove() {
	for (var id in ZBX_SBOX) {
		if (!empty(ZBX_SBOX[id])) {
			ZBX_SBOX[id].moveSBoxByObj();
		}
	}
}

function sboxGlobalMouseup(e) {
	for (var id in ZBX_SBOX) {
		if (!empty(ZBX_SBOX[id])) {
			ZBX_SBOX[id].mouseUp(e);
		}
	}
}

function sboxGlobalMousemove(e) {
	for (var id in ZBX_SBOX) {
		if (!empty(ZBX_SBOX[id]) && ZBX_SBOX[id].is_active) {
			ZBX_SBOX[id].mouseMove(e);
		}
	}
}
