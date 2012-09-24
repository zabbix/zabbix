/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


// graphs timeline controls (gtlc)
var timeControl = {

	objectList: {}, // objects needs to be controlled
	refreshPage: true,
	refreshInterval: 0,
	timeTimeout: null,

	addObject: function(domid, time, objData) {
		this.objectList[domid] = {
			refresh: false,
			processed: 0,
			id: domid,
			containerid: null,
			domid: domid,
			time: {},
			objDims: {},
			src: location.href,
			dynamic: 1,
			periodFixed: 1,
			loadSBox: 0,
			loadImage: 0,
			loadScroll: 1,
			scrollWidthByImage: 0,
			mainObject: 0, // object on changing will reflect on all others
			sliderMaximumTimePeriod: null // max period in seconds
		};

		for (var key in this.objectList[domid]) {
			if (isset(key, objData)) {
				this.objectList[domid][key] = objData[key];
			}
		}

		var nowDate = new CDate();
		var now = parseInt(nowDate.getTime() / 1000);

		if (!isset('period', time)) {
			time.period = 3600;
		}

		if (!isset('endtime', time)) {
			time.endtime = now;
		}

		time.starttime = (!isset('starttime', time) || is_null(time['starttime']))
			? time.endtime - 3 * ((time.period < 86400) ? 86400 : time.period)
			: nowDate.setZBXDate(time.starttime) / 1000;

		time.usertime = (!isset('usertime', time))
			? time.endtime
			: nowDate.setZBXDate(time.usertime) / 1000;

		this.objectList[domid].timeline = create_timeline(
			this.objectList[domid].domid,
			parseInt(time.period),
			parseInt(time.starttime),
			parseInt(time.usertime),
			parseInt(time.endtime),
			this.objectList[domid].sliderMaximumTimePeriod,
			time.isNow
		);
	},

	processObjects: function() {
		for (var objid in this.objectList) {
			if (empty(this.objectList[objid])) {
				continue;
			}

			if (this.objectList[objid].processed == 1) {
				continue;
			}
			else {
				this.objectList[objid].processed = 1;
			}

			var obj = this.objectList[objid];

			// width
			if ((!isset('width', obj.objDims) || obj.objDims.width < 0) && isset('shiftXleft', obj.objDims) && isset('shiftXright', obj.objDims)) {
				var width = get_bodywidth();
				if (!is_number(width)) {
					width = 1000;
				}

				if (!isset('width', obj.objDims)) {
					obj.objDims.width = 0;
				}
				obj.objDims.width += width - (parseInt(obj.objDims.shiftXleft) + parseInt(obj.objDims.shiftXright) + 27);
			}

			// url
			if (isset('graphtype', obj.objDims) && obj.objDims.graphtype < 2) {
				var graphUrl = new Curl(obj.src);
				graphUrl.setArgument('width', obj.objDims.width);

				obj.src = graphUrl.getUrl();
			}

			// image
			if (obj.loadImage) {
				if (!obj.refresh) {
					this.addImage(obj.domid, false);
				}
			}
			else if (obj.loadScroll) {
				this.addScroll(null, obj.domid);
			}

			// refresh
			if (obj.refresh) {
				this.refreshImage(obj.domid);
			}
		}
	},

	addImage: function(objid, rebuildListeners) {
		var obj = this.objectList[objid];

		var img = $(obj.domid);
		if (empty(img)) {
			img = document.createElement('img');
			img.setAttribute('id', obj.domid);
			img.setAttribute('src', obj.src);
			$(obj.containerid).appendChild(img);
		}

		if (obj.loadScroll && empty(obj.scroll_listener)) {
			obj.scroll_listener = this.addScroll.bindAsEventListener(this, obj.domid);
			addListener(img, 'load', obj.scroll_listener);
		}

		if (obj.loadSBox && empty(obj.sbox_listener)) {
			obj.sbox_listener = this.addSBox.bindAsEventListener(this, obj.domid);
			addListener(img, 'load', obj.sbox_listener);
			addListener(img, 'load', moveSBoxes);
		}
	},

	refreshImage: function(objid) {
		var obj = this.objectList[objid];
		var period = this.getPeriod(objid);
		var stime = this.getSTime(objid);

		// image
		var imgUrl = new Curl(obj.src);
		imgUrl.setArgument('period', period);
		imgUrl.setArgument('stime', stime);
		imgUrl = this.getFormattedUrl(objid, imgUrl);

		jQuery('<img />', {id: obj.domid + '_tmp', src: imgUrl.getUrl()}).load(function() {
			var id = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));
			jQuery('#' + id).replaceWith(jQuery(this));
			jQuery(this).attr('id', id);
		});

		// link
		var graphUrl = new Curl(jQuery('#' + obj.containerid).attr('href'));
		graphUrl.setArgument('width', obj.objDims.width);
		graphUrl.setArgument('period', period);
		graphUrl.setArgument('stime', stime);
		graphUrl = this.getFormattedUrl(objid, graphUrl);

		jQuery('#' + obj.containerid).attr('href', graphUrl.getUrl());
	},

	refreshObject: function(objid) {
		this.objectList[objid].processed = 0;
		this.objectList[objid].refresh = true;
		this.processObjects();
	},

	refreshTime: function(refreshInterval) {
		if (!empty(refreshInterval)) {
			this.refreshInterval = refreshInterval * 1000;
		}

		if (this.refreshInterval > 0) {
			for (var sbid in ZBX_SCROLLBARS) {
				if (!empty(ZBX_SCROLLBARS[sbid]) && !empty(ZBX_SCROLLBARS[sbid].timeline)) {
					var timelineid = ZBX_SCROLLBARS[sbid].timeline.timelineid;

					// timeline
					if (ZBX_TIMELINES[timelineid].isNow()) {
						ZBX_TIMELINES[timelineid].setNow();
					}
					else {
						ZBX_TIMELINES[timelineid].endtime(ZBX_TIMELINES[timelineid].timeNow());
					}

					// scrollbar
					ZBX_SCROLLBARS[sbid].timeline = ZBX_TIMELINES[timelineid];
					ZBX_SCROLLBARS[sbid].setBarPosition();
					ZBX_SCROLLBARS[sbid].setGhostByBar();
					ZBX_SCROLLBARS[sbid].setTabInfo();
					ZBX_SCROLLBARS[sbid].updateGlobalTimeline();
				}
			}

			this.timeTimeout = window.setTimeout(function() { timeControl.refreshTime(); }, this.refreshInterval);
		}
	},

	isNow: function() {
		for (var sbid in ZBX_SCROLLBARS) {
			if (!empty(ZBX_SCROLLBARS[sbid]) && !empty(ZBX_SCROLLBARS[sbid].timeline)) {
				return ZBX_TIMELINES[ZBX_SCROLLBARS[sbid].timeline.timelineid].isNow();
			}
		}

		return null;
	},

	objectUpdate: function(objid, timelineid) {
		if (!isset(objid, this.objectList)) {
			throw('timeControl: Object is not declared "' + objid + '".');
		}

		var usertime = ZBX_TIMELINES[timelineid].usertime(),
			period = ZBX_TIMELINES[timelineid].period();
		if (isNaN(usertime) || isNaN(period)) {
			// clean sbox'es
			for (var objectId in this.objectList) {
				if (!empty(this.objectList[objectId]) && isset(objectId, ZBX_SBOX)) {
					ZBX_SBOX[objectId].sbox.clear_params();
				}
			}

			return;
		}

		if (ZBX_TIMELINES[timelineid].now() || ZBX_TIMELINES[timelineid].isNow()) {
			usertime += 31536000; // 31536000 = 86400 * 365 = 1 year
		}

		var date = new CDate((usertime - period) * 1000);

		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('period', period);
			url.setArgument('stime', date.getZBXDate());
			url.unsetArgument('output');
			url = this.getFormattedUrl(objid, url);

			location.href = url.getUrl();
		}
		else {
			flickerfreeScreen.refreshAll(period, date.getZBXDate(), ZBX_TIMELINES[timelineid].isNow());

			// update related objects
			for (var objectId in this.objectList) {
				if (!empty(this.objectList[objectId]) && isset(objectId, ZBX_SBOX)) {
					ZBX_SBOX[objectId].sbox.timeline = ZBX_TIMELINES[timelineid];
				}
			}
		}
	},

	objectReset: function() {
		var usertime = 1600000000;
		var period = 3600;
		var stime = 201911051255;

		// update time control
		for (var objid in this.objectList) {
			if (!empty(this.objectList[objid])) {
				this.objectList[objid].timeline.period(period);
				this.objectList[objid].timeline.usertime(usertime);

				if (isset(objid, ZBX_SCROLLBARS)) {
					ZBX_SCROLLBARS[objid].setBarPosition();
					ZBX_SCROLLBARS[objid].setGhostByBar();
					ZBX_SCROLLBARS[objid].setTabInfo();
				}

				if (isset(objid, ZBX_SBOX)) {
					ZBX_SBOX[objid].sbox.timeline = this.objectList[objid].timeline;
				}
			}
		}

		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('period', period);
			url.setArgument('stime', stime);
			url.unsetArgument('output');
			url = this.getFormattedUrl(objid, url);

			location.href = url.getUrl();
		}
		else {
			flickerfreeScreen.refreshAll(period, stime, true);
		}
	},

	addSBox: function(e, objid) {
		var obj = this.objectList[objid];
		var img = $(obj.domid);

		if (!is_null(img)) {
			removeListener(img, 'load', obj.sbox_listener);
		}

		ZBX_SBOX[obj.domid] = new Object;
		ZBX_SBOX[obj.domid].shiftT = parseInt(obj.objDims.shiftYtop);
		ZBX_SBOX[obj.domid].shiftL = parseInt(obj.objDims.shiftXleft);
		ZBX_SBOX[obj.domid].shiftR = parseInt(obj.objDims.shiftXright);
		ZBX_SBOX[obj.domid].height = parseInt(obj.objDims.graphHeight);
		ZBX_SBOX[obj.domid].width = parseInt(obj.objDims.width);
		ZBX_SBOX[obj.domid].additionShiftL = 0;

		var sbox = sbox_init(obj.domid, obj.timeline.timelineid, obj.domid);
		sbox.onchange = this.objectUpdate.bind(this);
	},

	addScroll: function(e, objid) {
		var obj = this.objectList[objid];
		var img = $(obj.domid);

		if (!is_null(img)) {
			removeListener(img, 'load', obj.scroll_listener);
		}

		var width = null;
		if (obj.scrollWidthByImage == 0) {
			width = get_bodywidth() - 30;
			if (!is_number(width)) {
				width = 900;
			}
		}

		var scrl = scrollCreate(
			obj.domid,
			width,
			obj.timeline.timelineid,
			this.objectList[objid].periodFixed,
			this.objectList[objid].sliderMaximumTimePeriod
		);
		scrl.onchange = this.objectUpdate.bind(this);

		if (obj.dynamic && !is_null($(img))) {
			addListener(obj.domid, 'load', function() { ZBX_SCROLLBARS[scrl.scrollbarid].disabled = 0; });
		}
	},

	getPeriod: function(objid) {
		return this.objectList[objid].timeline.period();
	},

	getSTime: function(objid) {
		var obj = this.objectList[objid];
		var date = new CDate((obj.timeline.usertime() - obj.timeline.period()) * 1000);

		return date.getZBXDate();
	},

	getObject: function(objid) {
		return this.objectList[objid];
	},

	getFormattedUrl: function(objid, url) {
		// ignore time in edit mode
		if (typeof(flickerfreeScreen) != 'undefined'
				&& !empty(flickerfreeScreen.screens[objid])
				&& flickerfreeScreen.screens[objid].mode == 1 // SCREEN_MODE_EDIT
				&& flickerfreeScreen.screens[objid].resourcetype == 0) { // SCREEN_RESOURCE_GRAPH
			url.unsetArgument('period');
			url.unsetArgument('stime');
		}

		return url;
	}
};

// timeline control core
var ZBX_TIMELINES = {};

function create_timeline(tlid, period, starttime, usertime, endtime, maximumperiod, isNow) {
	if (is_null(tlid)) {
		tlid = ZBX_TIMELINES.length;
	}

	var now = new CDate();
	now = parseInt(now.getTime() / 1000);

	if ('undefined' == typeof(usertime)) {
		usertime = now;
	}
	if ('undefined' == typeof(endtime)) {
		endtime = now;
	}
	if ('undefined' == typeof(isNow)) {
		isNow = false;
	}

	ZBX_TIMELINES[tlid] = new CTimeLine(tlid, period, starttime, usertime, endtime, maximumperiod, isNow);

	return ZBX_TIMELINES[tlid];
}

var CTimeLine = Class.create(CDebug, {

	_starttime:	null,	// timeline start time (left, past)
	_endtime:	null,	// timeline end time (right, now)
	_usertime:	null,	// selected end time (bar, user selection)
	_period:	null,	// selected period
	_now:		false,	// state if time is set to NOW
	_isNow:		false,	// state if time is set to NOW (for outside usage)
	timelineid:	null,	// own id in array
	minperiod:	3600,	// minimal allowed period
	maxperiod:	null,	// max period in seconds

	initialize: function($super, id, period, starttime, usertime, endtime, maximumperiod, isNow) {
		this.timelineid = id;
		$super('CTimeLine[' + id + ']');

		if ((endtime - starttime) < (3 * this.minperiod)) {
			starttime = endtime - (3 * this.minperiod);
		}

		this.starttime(starttime);
		this.endtime(endtime);
		this.usertime(usertime);
		this.period(period);
		this.maxperiod = maximumperiod;
		this.isNow(isNow);
	},

	timeNow: function() {
		return parseInt(new CDate().getTime() / 1000);
	},

	setNow: function() {
		var end = this.timeNow();

		this._endtime = end;
		this._usertime = end;
		this.now();
	},

	now: function() {
		this._now = ((this._usertime + 60) > this._endtime);

		return this._now;
	},


	period: function(period) {
		if ('undefined' == typeof(period)) {
			return this._period;
		}
		if ((this._usertime - period) < this._starttime) {
			period = this._usertime - this._starttime;
		}
		if (period < this.minperiod) {
			period = this.minperiod;
		}
		this._period = period;

		return this._period;
	},

	usertime: function(usertime) {
		if ('undefined' == typeof(usertime)) {
			return this._usertime;
		}
		if ((usertime - this._period) < this._starttime) {
			usertime = this._starttime + this._period;
		}
		if (usertime > this._endtime) {
			usertime = this._endtime;
		}

		this._usertime = usertime;
		this.now();

		return this._usertime;
	},

	starttime: function(starttime) {
		if ('undefined' == typeof(starttime)) {
			return this._starttime;
		}
		this._starttime = starttime;

		return this._starttime;
	},

	endtime: function(endtime) {
		if ('undefined' == typeof(endtime)) {
			return this._endtime;
		}
		if (endtime < (this._starttime + this._period * 3)) {
			endtime = this._starttime + this._period * 3;
		}
		this._endtime = endtime;

		return this._endtime;
	},

	isNow: function(isNow) {
		if ('undefined' == typeof(isNow)) {
			return this._isNow;
		}

		this._isNow = (isNow == 1) ? true : (isNow ? isNow : false);
	}
});

// graph scrolling
var ZBX_SCROLLBARS = {};

function scrollCreate(sbid, w, timelineid, fixedperiod, maximumperiod) {
	if (is_null(sbid)) {
		sbid = ZBX_SCROLLBARS.length;
	}

	if (is_null(timelineid)) {
		throw('Parameters haven\'t been sent properly.');
	}

	if (is_null(w)) {
		var dims = getDimensions(sbid);
		w = dims.width - 2;
	}

	if (w < 600) {
		w = 600;
	}

	ZBX_SCROLLBARS[sbid] = new CScrollBar(sbid, timelineid, w, fixedperiod, maximumperiod);

	return ZBX_SCROLLBARS[sbid];
}

var CScrollBar = Class.create(CDebug, {

	scrollbarid:	null, // scroll id in array
	timelineid:		null, // timeline id to which it is connected
	timeline:		null, // time line object
	ghostBox:		null, // ghost box object
	clndrLeft:		null, // calendar object left
	clndrRight:		null, // calendar object right
	px2sec:			null, // seconds in pixel

	dom: {
		scrollbar:		null,
		info:			null,
		gmenu:			null,
		zoom:			null,
		text:			null,
		links:			null,
		linklist:		[],
		timeline:		null,
		info_left:		null,
		info_right:		null,
		sublevel:		null,
		left:			null,
		right:			null,
		bg:				null,
		overlevel:		null,
		bar:			null,
		icon:			null,
		center:			null,
		ghost:			null,
		left_arr:		null,
		right_arr:		null,
		subline:		null,
		nav_links:		null,
		nav_linklist:	[],
		period_state:	null,
		info_period:	null
	},

	size: {
		scrollline:		null,	// scroll line width
		barminwidth:	21		// bar minimal width
	},

	position: {
		bar:		null,	// bar dimensions
		ghost:		null,	// ghost dimensions
		leftArr:	null,	// left arrow dimensions
		rightArr:	null	// right arrow dimensions
	},

	// status
	scrollmsover:	0,		// if mouse over scrollbar then = 1, out = 0
	barmsdown:		0,		// if mousedown on bar = 1, else = 0
	arrowmsdown:	0,		// if mousedown on arrow = 1, else = 0
	arrow:			'',		// pressed arrow (l/r)
	changed:		0,		// switches to 1, when scrollbar been moved or period changed
	fixedperiod:	1,		// fixes period on bar changes
	disabled:		1,		// activates/disables scrollbars
	maxperiod:		null,	// max period in seconds

	initialize: function($super, sbid, timelineid, width, fixedperiod, maximalperiod) {
		this.scrollbarid = sbid;
		$super('CScrollBar[' + sbid + ']');
		this.maxperiod = maximalperiod;

		try {
			this.fixedperiod = (fixedperiod == 1) ? 1 : 0;

			// checks
			if (!isset(timelineid, ZBX_TIMELINES)) {
				throw('Failed to initialize ScrollBar with given TimeLine.');
			}
			if (empty(this.dom.scrollbar)) {
				this.scrollcreate(width);
			}

			// variable initialization
			this.timeline = ZBX_TIMELINES[timelineid];
			this.ghostBox = new CGhostBox(this.dom.ghost);
			this.size.scrollline = width - 36; // border (17 * 2) + 2 = 36
			this.px2sec = (this.timeline.endtime() - this.timeline.starttime()) / this.size.scrollline;

			// additional dom objects
			this.appendZoomLinks();
			this.appendNavLinks();
			this.appendCalendars();

			// after px2sec is set. important!
			this.position.bar = getDimensions(this.bar);
			this.setBarPosition();
			this.setGhostByBar();
			this.setTabInfo();

			// animate things
			this.makeBarDragable(this.dom.bar);
			this.make_left_arr_dragable(this.dom.left_arr);
			this.make_right_arr_dragable(this.dom.right_arr);
			this.disabled = 0;

		}
		catch(e) {
			throw('ERROR: ScrollBar initialization failed!');
		}
	},

	onBarChange: function() {
		this.updateGlobalTimeline();

		this.changed = 1;
		this.onchange(this.scrollbarid, this.timeline.timelineid);
	},

	updateGlobalTimeline: function() {
		ZBX_TIMELINES[this.timeline.timelineid] = this.timeline;
		ZBX_TIMELINES[this.timeline.timelineid].isNow(ZBX_TIMELINES[this.timeline.timelineid].now());
	},

	//------- MOVE -------
	setFullPeriod: function() {
		if (this.disabled) {
			return false;
		}

		this.timeline.setNow();
		this.timeline.period(this.maxperiod);
		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	setZoom: function(e, zoom) {
		if (this.disabled) {
			return false;
		}

		this.timeline.period(zoom);
		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	navigateLeft: function(e, left) {
		if (this.disabled) {
			return false;
		}

		deselectAll();

		var period = false;
		if (typeof(left) == 'undefined') {
			period = this.timeline.period();
		}

		// fixed
		if (this.fixedperiod == 1) {
			var usertime = this.timeline.usertime();
			var new_usertime = (period)
				? usertime - period // by clicking this.dom.left we move bar by period
				: usertime - left;

			// if we slide to another timezone
			new_usertime -= this.getTZdiff(usertime, new_usertime);

			this.timeline.usertime(new_usertime);
		}
		// dynamic
		else {
			var new_period = (period)
				? this.timeline.period() + 86400 // by clicking this.dom.left we expand period by 1day
				: this.timeline.period() + left;

			this.timeline.period(new_period);
		}

		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	navigateRight: function(e, right) {
		if (this.disabled) {
			return false;
		}

		deselectAll();

		var period = false;
		if (typeof(right) == 'undefined') {
			period = this.timeline.period();
		}

		var usertime = this.timeline.usertime();

		// fixed
		if (this.fixedperiod == 1) {
			var new_usertime = (period)
				? new_usertime = usertime + period // by clicking this.dom.left we move bar by period
				: usertime + right;
		}
		// dynamic
		else {
			if (period) {
				var new_period = this.timeline.period() + 86400; // by clicking this.dom.left we expand period by 1day
				var new_usertime = usertime + 86400; // by clicking this.dom.left we move bar by period
			}
			else {
				var new_period = this.timeline.period() + right;
				var new_usertime = usertime + right;
			}

			this.timeline.period(new_period);
		}

		// if we slide to another timezone
		new_usertime -= this.getTZdiff(usertime, new_usertime);

		this.timeline.usertime(new_usertime);
		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	setBarPosition: function(rightSide, periodWidth, setTimeLine) {
		if (empty(periodWidth)) {
			var periodWidth =  null;
		}
		if (empty(rightSide)) {
			var rightSide = null;
		}
		if (empty(setTimeLine)) {
			var setTimeLine = false;
		}

		var width = 0;
		if (is_null(periodWidth)) {
			width = Math.round(this.timeline.period() / this.px2sec);
			periodWidth = width;
		}
		else {
			width = periodWidth;
		}

		if (is_null(rightSide)) {
			var userTime = this.timeline.usertime();
			var startTime = this.timeline.starttime();

			rightSide = Math.round((userTime - startTime) / this.px2sec);
		}

		var right = rightSide;

		// period
		if (width < this.size.barminwidth) {
			width = this.size.barminwidth;
		}

		// left min
		if ((right - width) < 0) {
			if (width < this.size.barminwidth) {
				width = this.size.barminwidth;
			}
			right = width;

			// actual bar dimensions shouldn't be over side limits
			rightSide = right;
		}

		// right max
		if (right > this.size.scrollline) {
			if (width < this.size.barminwidth) {
				width = this.size.barminwidth;
			}
			right = this.size.scrollline;

			// actual bar dimensions shouldn't be over side limits
			rightSide = right;
		}

		// validate
		if (!is_number(width) || !is_number(right) || !is_number(rightSide) || !is_number(periodWidth)) {
			return;
		}

		// set actual bar position
		this.dom.bar.style.width = width + 'px';
		this.dom.bar.style.left = (right - width) + 'px';

		// set timeline to given dimensions
		this.position.bar.left = rightSide - periodWidth;
		this.position.bar.right = rightSide;
		this.position.bar.width = periodWidth;

		if (setTimeLine) {
			this.updateTimeLine(this.position.bar);
		}

		this.position.bar.left = right - width;
		this.position.bar.width = width;
		this.position.bar.right = right;
	},

	setGhostByBar: function(ui) {
		var dims = (arguments.length > 0)
			? {left: ui.position.left, width: jQuery(ui.helper.context).width()}
			: getDimensions(this.dom.bar);

		// ghost
		this.dom.ghost.style.left = dims.left + 'px';
		this.dom.ghost.style.width = dims.width + 'px';

		// arrows
		this.dom.left_arr.style.left = (dims.left - 4) + 'px';
		this.dom.right_arr.style.left = (dims.left + dims.width - 3) + 'px';

		this.position.ghost = getDimensions(this.dom.ghost);
	},

	setBarByGhost: function() {
		var dimensions = getDimensions(this.dom.ghost);

		this.setBarPosition(dimensions.right, dimensions.width, false);
		this.onBarChange();
	},

	//------- CALENDAR -------
	calendarShowLeft: function() {
		if (this.disabled) {
			return false;
		}

		var pos = getPosition(this.dom.info_left);
		pos.top += 34;
		pos.left -= 145;

		if (CR) {
			pos.top -= 20;
		}

		this.clndrLeft.clndr.clndrshow(pos.top, pos.left);
	},

	calendarShowRight: function() {
		if (this.disabled) {
			return false;
		}

		var pos = getPosition(this.dom.info_right);

		pos.top += 34;
		pos.left -= 77;

		if (CR) {
			pos.top -= 20;
		}

		this.clndrRight.clndr.clndrshow(pos.top, pos.left);
	},

	setCalendarLeft: function(time) {
		if (this.disabled) {
			return false;
		}

		time = parseInt(time / 1000);

		// fixed
		if (this.fixedperiod == 1) {
			this.timeline.usertime(time + this.timeline.period());
		}
		// dynamic
		else {
			this.timeline.period(Math.abs(this.timeline.usertime() - time));
		}

		// bar
		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	setCalendarRight: function(time) {
		if (this.disabled) {
			return false;
		}

		time = parseInt(time / 1000);

		// fixed
		if (this.fixedperiod == 1) {
			this.timeline.usertime(time);
		}
		// dynamic
		else {
			var startusertime = this.timeline.usertime() - this.timeline.period();
			this.timeline.usertime(time);
			this.timeline.period(this.timeline.usertime() - startusertime);
		}

		// bar
		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	//------- DRAG & DROP -------
	barDragStart: function(e, ui) {
		if (this.disabled) {
			return false;
		}
	},

	barDragChange: function(e, ui) {
		if (this.disabled) {
			ui.helper[0].stop(e);
			return false;
		}

		this.position.bar = getDimensions(ui.helper.context);
		this.setGhostByBar(ui);
		this.updateTimeLine(this.position.bar);
		this.setTabInfo();
	},

	barDragEnd: function(e, ui) {
		if (this.disabled) {
			return false;
		}

		this.position.bar = getDimensions(ui.helper.context);
		this.ghostBox.endResize();
		this.setBarByGhost();
		this.setGhostByBar();
	},

	makeBarDragable: function(element) {
		jQuery(element).draggable({
			containment: 'parent',
			axis: 'x',
			start: this.barDragStart.bind(this),
			drag: this.barDragChange.bind(this),
			stop: this.barDragEnd.bind(this)
		});
	},

	// <left arr>
	make_left_arr_dragable: function(element) {
		var pD = {
			left: jQuery(this.dom.overlevel).offset().left,
			width: jQuery(this.dom.overlevel).width()
		};

		jQuery(element).draggable({
			containment: [pD.left - 4, 0, pD.width + pD.left - 4, 0],
			axis: 'x',
			start: this.leftArrowDragStart.bind(this),
			drag: this.leftArrowDragChange.bind(this),
			stop: this.leftArrowDragEnd.bind(this)
		});
	},

	leftArrowDragStart: function(e, ui) {
		if (this.disabled) {
			return false;
		}

		this.position.leftArr = getDimensions(ui.helper.context);
		this.ghostBox.userstartime = this.timeline.usertime();
		this.ghostBox.usertime = this.timeline.usertime();
		this.ghostBox.startResize(0);
	},

	leftArrowDragChange: function(e, ui) {
		if (this.disabled) {
			ui.helper.context.stop(e);
			return false;
		}

		this.ghostBox.resizeBox(ui.position.left - ui.originalPosition.left);
		this.position.ghost = getDimensions(this.dom.ghost);
		this.updateTimeLine(this.position.ghost);
		this.setTabInfo();
	},

	leftArrowDragEnd: function(e, ui) {
		if (this.disabled) {
			return false;
		}

		this.position.leftArr = getDimensions(ui.helper.context);
		this.ghostBox.endResize();
		this.setBarByGhost();
		this.setGhostByBar();
	},

	// <right arr>
	make_right_arr_dragable: function(element) {
		var pD = {
			left: jQuery(this.dom.overlevel).offset().left,
			width: jQuery(this.dom.overlevel).width()
		};

		jQuery(element).draggable({
			containment: [pD.left - 4, 0, pD.width + pD.left - 5, 0],
			axis: 'x',
			start: this.rightArrowDragStart.bind(this),
			drag: this.rightArrowDragChange.bind(this),
			stop: this.rightArrowDragEnd.bind(this)
		});
	},

	rightArrowDragStart: function(e, ui) {
		if (this.disabled) {
			return false;
		}

		this.position.rightArr = getDimensions(ui.helper.context);
		this.ghostBox.userstartime = this.timeline.usertime() - this.timeline.period();
		this.ghostBox.startResize(1);
	},

	rightArrowDragChange: function(e, ui) {
		if (this.disabled) {
			ui.helper.context.stop(e);
			return false;
		}

		this.ghostBox.resizeBox(ui.position.left - ui.originalPosition.left);
		this.position.ghost = getDimensions(this.dom.ghost);
		this.updateTimeLine(this.position.ghost);
		this.setTabInfo();
	},

	rightArrowDragEnd: function(e, ui) {
		if (this.disabled) {
			return false;
		}

		this.position.rightArr = getDimensions(ui.helper.context);
		this.ghostBox.endResize();
		this.setBarByGhost();
		this.setGhostByBar();
	},

	/*---------------------------------------------------------------------
	------------------------------ FUNC USES ------------------------------
	---------------------------------------------------------------------*/
	switchPeriodState: function() {
		if (this.disabled) {
			return false;
		}

		this.fixedperiod = (this.fixedperiod == 1) ? 0 : 1;

		// sending fixed/dynamic setting to server to save in a profile
		var params = {
			favobj: 'timelinefixedperiod',
			favid: this.fixedperiod
		};
		send_params(params);

		this.dom.period_state.innerHTML = (this.fixedperiod)
			? locale['S_FIXED_SMALL']
			: locale['S_DYNAMIC_SMALL'];
	},

	getTZOffset: function(time) {
		return new CDate(time * 1000).getTimezoneOffset() * 60;
	},

	getTZdiff: function(time1, time2) {
		var date = new CDate(time1 * 1000);
		var TimezoneOffset = date.getTimezoneOffset();

		date.setTime(time2 * 1000);

		return (TimezoneOffset - date.getTimezoneOffset()) * 60;
	},

	roundTime: function(usertime) {
		var time = parseInt(usertime);

		if (time > 86400) {
			var dd = new CDate();
			dd.setTime(time * 1000);
			dd.setHours(0);
			dd.setMinutes(0);
			dd.setSeconds(0);
			dd.setMilliseconds(0);
			time = parseInt(dd.getTime() / 1000);
		}

		return time;
	},

	updateTimeLine: function(dim) {
		// timeline update
		var period = this.timeline.period();
		var new_usertime = parseInt(dim.right * this.px2sec, 10) + this.timeline.starttime();
		var new_period = parseInt(dim.width * this.px2sec, 10);

		if (new_period > 86400) {
			new_period = this.roundTime(new_period) - this.getTZOffset(new_period);
		}

		var right = (this.ghostBox.sideToMove == 1 && this.ghostBox.flip >= 0) || (this.ghostBox.sideToMove == 0 && this.ghostBox.flip < 0);
		var left = (this.ghostBox.sideToMove == 0 && this.ghostBox.flip >= 0) || (this.ghostBox.sideToMove == 1 && this.ghostBox.flip < 0);

		// hack for bars most right position
		if (dim.right == this.size.scrollline) {
			if (dim.width != this.position.bar.width) {
				this.position.bar.width = dim.width;
				this.timeline.period(new_period);
			}
			this.timeline.setNow();
		}
		else {
			if (right) {
				new_usertime = this.ghostBox.userstartime + new_period;
			}
			else if (left) {
				new_usertime = this.ghostBox.userstartime;
			}

			// to properly count timezone diffs
			if (period >= 86400) {
				new_usertime = this.roundTime(new_usertime);
			}

			if (dim.width != this.position.bar.width) {
				this.position.bar.width = dim.width;
				this.timeline.period(new_period);
			}

			this.timeline.usertime(new_usertime);
		}

		this.updateGlobalTimeline();
	},

	setTabInfo: function() {
		var period = this.timeline.period(),
			usertime = this.timeline.usertime();
		if (isNaN(period) || isNaN(usertime)) {
			return;
		}

		var userstarttime = usertime - period;

		this.dom.info_period.innerHTML = formatTimestamp(period, false, true);

		// info left
		var date = this.dateToArray(userstarttime);
		this.dom.info_left.innerHTML = date[0] + '.' + date[1] + '.' + date[2] + ' ' + date[3] + ':' + date[4];

		// info right
		var date = this.dateToArray(usertime);
		var right_info = date[0] + '.' + date[1] + '.' + date[2] + ' ' + date[3] + ':' + date[4];

		if (this.timeline.now()) {
			right_info += ' (' + locale['S_NOW_SMALL'] + '!) ';
		}
		this.dom.info_right.innerHTML = right_info;

		// seting zoom link styles
		this.setZoomLinksStyle();

		ZBX_TIMELINES[this.timeline.timelineid] = this.timeline;
	},

	dateToArray: function(unixtime) {
		var date = new CDate(unixtime * 1000),
			dateArray = [date.getDate(), date.getMonth() + 1, date.getFullYear(), date.getHours(), date.getMinutes(), date.getSeconds()];

		for (var i = 0; i < dateArray.length; i++) {
			if ((dateArray[i] + '').length < 2) {
				dateArray[i] = '0' + dateArray[i];
			}
		}

		return dateArray;
	},

	//----------------------------------------------------------------
	//-------- MISC --------------------------------------------------
	//----------------------------------------------------------------
	getmousexy: function(e) {
		if (e.pageX || e.pageY) {
			return {x: e.pageX, y: e.pageY};
		}

		return {
			x: e.clientX + document.body.scrollLeft - document.body.clientLeft,
			y: e.clientY + document.body.scrollTop - document.body.clientTop
		};
	},

	deselectall: function() {
		if (IE) {
			document.selection.empty();
		}
		else {
			var sel = window.getSelection();
			sel.removeAllRanges();
		}
	},

	//----------------------------------------------------------------
	//-------- SCROLL CREATION ---------------------------------------
	//----------------------------------------------------------------
	appendCalendars: function() {
		this.clndrLeft = create_calendar(this.timeline.usertime() - this.timeline.period(), this.dom.info_left, null, null, 'scrollbar_cntr');
		this.clndrRight = create_calendar(this.timeline.usertime(), this.dom.info_right, null, null, 'scrollbar_cntr');
		this.clndrLeft.clndr.onselect = this.setCalendarLeft.bind(this);
		addListener(this.dom.info_left, 'click', this.calendarShowLeft.bindAsEventListener(this));

		this.clndrRight.clndr.onselect = this.setCalendarRight.bind(this);
		addListener(this.dom.info_right, 'click', this.calendarShowRight.bindAsEventListener(this));
	},

	/**
	 * Optimization:
	 * 7200 = 2 * 3600
	 * 10800 = 3 * 3600
	 * 21600 = 6 * 3600
	 * 43200 = 12 * 3600
	 * 604800 = 7 * 86400
	 * 1209600 = 14 * 86400
	 * 2592000 = 30 * 86400
	 * 7776000 = 90 * 86400
	 * 15552000 = 180 * 86400
	 * 31536000 = 365 * 86400
	 */
	appendZoomLinks: function() {
		var timeline = this.timeline.endtime() - this.timeline.starttime();
		var caption = '';
		var zooms = [3600, 7200, 10800, 21600, 43200, 86400, 604800, 1209600, 2592000, 7776000, 15552000, 31536000];
		var links = 0;

		for (var key in zooms) {
			if (empty(zooms[key]) || !is_number(zooms[key])) {
				continue;
			}
			if ((timeline / zooms[key]) < 1) {
				break;
			}

			caption = formatTimestamp(zooms[key], false, true);
			caption = caption.split(' ', 2)[0];

			this.dom.linklist[links] = document.createElement('span');
			this.dom.linklist[links].className = 'link';
			this.dom.linklist[links].setAttribute('zoom', zooms[key]);
			this.dom.linklist[links].appendChild(document.createTextNode(caption));
			this.dom.links.appendChild(this.dom.linklist[links]);
			addListener(this.dom.linklist[links], 'click', this.setZoom.bindAsEventListener(this, zooms[key]), true);

			links++;
		}

		this.dom.linklist[links] = document.createElement('span');
		this.dom.linklist[links].className = 'link';
		this.dom.linklist[links].setAttribute('zoom', this.maxperiod);
		this.dom.linklist[links].appendChild(document.createTextNode(locale['S_ALL_S']));
		this.dom.links.appendChild(this.dom.linklist[links]);
		addListener(this.dom.linklist[links], 'click', this.setFullPeriod.bindAsEventListener(this), true);
	},

	/**
	 * Optimization:
	 * 43200 = 12 * 3600
	 * 604800 = 7 * 86400
	 * 2592000 = 30 * 86400
	 * 15552000 = 180 * 86400
	 * 31536000 = 365 * 86400
	 */
	appendNavLinks: function() {
		var timeline = this.timeline.endtime() - this.timeline.starttime();
		var caption = '';
		var moves = [3600, 43200, 86400, 604800, 2592000, 15552000, 31536000];
		var links = 0;

		var tmp_laquo = document.createElement('span');
		tmp_laquo.className = 'text';
		tmp_laquo.innerHTML = ' &laquo;&laquo; ';
		this.dom.nav_links.appendChild(tmp_laquo);

		for (var i = moves.length; i >= 0; i--) {
			if (!isset(i, moves) || !is_number(moves[i])) {
				continue;
			}
			if ((timeline / moves[i]) < 1) {
				continue;
			}

			caption = formatTimestamp(moves[i], false, true);
			caption = caption.split(' ', 2)[0];

			this.dom.nav_linklist[links] = document.createElement('span');
			this.dom.nav_linklist[links].className = 'link';
			this.dom.nav_linklist[links].setAttribute('nav', moves[i]);
			this.dom.nav_linklist[links].appendChild(document.createTextNode(caption));
			this.dom.nav_links.appendChild(this.dom.nav_linklist[links]);
			addListener(this.dom.nav_linklist[links], 'click', this.navigateLeft.bindAsEventListener(this, moves[i]));

			links++;
		}

		var tmp_laquo = document.createElement('span');
		tmp_laquo.className = 'text';
		tmp_laquo.innerHTML = ' | ';
		this.dom.nav_links.appendChild(tmp_laquo);

		for (var i = 0; i < moves.length; i++) {
			if (!isset(i, moves) || !is_number(moves[i]) || (timeline / moves[i]) < 1) {
				continue;
			}

			caption = formatTimestamp(moves[i], false, true);
			caption = caption.split(' ', 2)[0];

			this.dom.nav_linklist[links] = document.createElement('span');
			this.dom.nav_linklist[links].className = 'link';
			this.dom.nav_linklist[links].setAttribute('nav', moves[i]);
			this.dom.nav_linklist[links].appendChild(document.createTextNode(caption));
			this.dom.nav_links.appendChild(this.dom.nav_linklist[links]);
			addListener(this.dom.nav_linklist[links], 'click', this.navigateRight.bindAsEventListener(this, moves[i]));

			links++;
		}

		var tmp_raquo = document.createElement('span');
		tmp_raquo.className = 'text';
		tmp_raquo.innerHTML = ' &raquo;&raquo; ';
		this.dom.nav_links.appendChild(tmp_raquo);
	},

	setZoomLinksStyle: function() {
		var period = this.timeline.period();

		for (var i = 0; i < this.dom.linklist.length; i++) {
			if (isset(i, this.dom.linklist) && !empty(this.dom.linklist[i])) {
				var linkzoom = this.dom.linklist[i].getAttribute('zoom');

				if (linkzoom == period) {
					this.dom.linklist[i].style.textDecoration = 'none';
					this.dom.linklist[i].style.fontWeight = 'bold';
					this.dom.linklist[i].style.fontSize = '11px';
				}
				else {
					this.dom.linklist[i].style.textDecoration = 'underline';
					this.dom.linklist[i].style.fontWeight = 'normal';
					this.dom.linklist[i].style.fontSize = '10px';
				}
			}
		}

		i = this.dom.linklist.length - 1;
		if (period == (this.timeline.endtime() - this.timeline.starttime())) {
			this.dom.linklist[i].style.textDecoration = 'none';
			this.dom.linklist[i].style.fontWeight = 'bold';
			this.dom.linklist[i].style.fontSize = '11px';
		}
	},

	scrollcreate: function(w) {
		var scr_cntr = $('scrollbar_cntr');
		if (is_null(scr_cntr)) {
			throw('ERROR: SCROLL [scrollcreate]: scroll container node is not found!');
		}

		scr_cntr.style.paddingRight = '2px';
		scr_cntr.style.paddingLeft = '2px';
		scr_cntr.style.margin = '5px 0 0 0';

		this.dom.scrollbar = document.createElement('div');
		this.dom.scrollbar.className = 'scrollbar';
		scr_cntr.appendChild(this.dom.scrollbar);

		Element.extend(this.dom.scrollbar);
		this.dom.scrollbar.setStyle({width: w + 'px'});

		// <info>
		this.dom.info = document.createElement('div');
		this.dom.scrollbar.appendChild(this.dom.info);
		this.dom.info.className = 'info';
		$(this.dom.info).setStyle({width: w + 'px'});

		this.dom.zoom = document.createElement('div');
		this.dom.info.appendChild(this.dom.zoom);
		this.dom.zoom.className = 'zoom';

		this.dom.text = document.createElement('span');
		this.dom.zoom.appendChild(this.dom.text);
		this.dom.text.className = 'text';

		this.dom.text.appendChild(document.createTextNode(locale['S_ZOOM'] + ':'));

		this.dom.links = document.createElement('span');
		this.dom.zoom.appendChild(this.dom.links);
		this.dom.links.className = 'links';

		this.dom.timeline = document.createElement('div');
		this.dom.info.appendChild(this.dom.timeline);
		this.dom.timeline.className = 'timeline';

		// left
		this.dom.info_left = document.createElement('span');
		this.dom.timeline.appendChild(this.dom.info_left);
		this.dom.info_left.className = 'info_left link';
		this.dom.info_left.appendChild(document.createTextNode('02.07.2009 12:15:12'));

		var sep = document.createElement('span');
		sep.className = 'info_sep1';
		sep.appendChild(document.createTextNode(' - '));
		this.dom.timeline.appendChild(sep);

		// right
		this.dom.info_right = document.createElement('span');
		this.dom.timeline.appendChild(this.dom.info_right);
		this.dom.info_right.className = 'info_right link';
		this.dom.info_right.appendChild(document.createTextNode('02.07.2009 12:15:12'));

		// <sublevel>
		this.dom.sublevel = document.createElement('div');
		this.dom.scrollbar.appendChild(this.dom.sublevel);
		this.dom.sublevel.className = 'sublevel';
		$(this.dom.sublevel).setStyle({width: w + 'px'});

		this.dom.left = document.createElement('div');
		this.dom.sublevel.appendChild(this.dom.left);
		this.dom.left.className = 'left';
		addListener(this.dom.left, 'click', this.navigateLeft.bindAsEventListener(this), true);

		this.dom.right = document.createElement('div');
		this.dom.sublevel.appendChild(this.dom.right);
		this.dom.right.className = 'right';
		addListener(this.dom.right, 'click', this.navigateRight.bindAsEventListener(this), true);

		this.dom.bg = document.createElement('div');
		this.dom.sublevel.appendChild(this.dom.bg);
		this.dom.bg.className = 'bg';

		// <overlevel>
		this.dom.overlevel = document.createElement('div');
		this.dom.scrollbar.appendChild(this.dom.overlevel);
		this.dom.overlevel.className = 'overlevel';
		$(this.dom.overlevel).setStyle({width: (w - 34) + 'px'});

		this.dom.bar = document.createElement('div');
		this.dom.overlevel.appendChild(this.dom.bar);
		this.dom.bar.className = 'bar';

		this.dom.icon = document.createElement('div');
		this.dom.bar.appendChild(this.dom.icon);
		this.dom.icon.className = 'icon';

		this.dom.center = document.createElement('div');
		this.dom.icon.appendChild(this.dom.center);
		this.dom.center.className = 'center';

		this.dom.ghost = document.createElement('div');
		this.dom.overlevel.appendChild(this.dom.ghost);
		this.dom.ghost.className = 'ghost';

		this.dom.left_arr = document.createElement('div');
		this.dom.overlevel.appendChild(this.dom.left_arr);
		this.dom.left_arr.className = 'left_arr';

		this.dom.right_arr = document.createElement('div');
		this.dom.overlevel.appendChild(this.dom.right_arr);
		this.dom.right_arr.className = 'right_arr';

		// <subline>
		this.dom.subline = document.createElement('div');
		this.dom.scrollbar.appendChild(this.dom.subline);
		this.dom.subline.className = 'subline';
		$(this.dom.subline).setStyle({width: w + 'px'});

		// additional positioning links
		this.dom.nav_links = document.createElement('div');
		this.dom.subline.appendChild(this.dom.nav_links);
		this.dom.nav_links.className = 'nav_links';

		// period state
		this.dom.period = document.createElement('div');
		this.dom.subline.appendChild(this.dom.period);
		this.dom.period.className = 'period';

		// state
		var tmp  = document.createElement('span');
		tmp.className = 'period_state_begin';
		tmp.appendChild(document.createTextNode('('));
		this.dom.period.appendChild(tmp);

		this.dom.period_state = document.createElement('span');
		this.dom.period.appendChild(this.dom.period_state);
		this.dom.period_state.className = 'period_state link';
		this.dom.period_state.appendChild(document.createTextNode(this.fixedperiod == 1 ? locale['S_FIXED_SMALL'] : locale['S_DYNAMIC_SMALL']));
		addListener(this.dom.period_state, 'click', this.switchPeriodState.bindAsEventListener(this));

		var tmp  = document.createElement('span');
		tmp.className = 'period_state_end';
		tmp.appendChild(document.createTextNode(')'));
		this.dom.period.appendChild(tmp);

		// period info
		this.dom.info_period = document.createElement('div');
		this.dom.subline.appendChild(this.dom.info_period);
		this.dom.info_period.className = 'info_period';
		this.dom.info_period.appendChild(document.createTextNode('0h 0m'));

		/*
		<div class="scrollbar">
			<div class="info">
				<div class="zoom">
					<span class="text">Zoom:</span>
					<span class="links">
						<span class="link">1h</span>
						<span class="link">2h</span>
						<span class="link">3h</span>
						<span class="link">6h</span>
						<span class="link">12h</span>
						<span class="link">1d</span>
						<span class="link">5d</span>
						<span class="link">1w</span>
						<span class="link">1m</span>
						<span class="link">3m</span>
						<span class="link">6m</span>
						<span class="link">YTD</span>
						<span class="link">1y</span>
					</span>
				</div>
				<div class="gmenu"></div>
				<div class="timeline">
					<span class="info_right">30.06.2009 16:35:08</span>
					<span class="info_sep1"> - </span>
					<span class="info_left">30.06.2009 16:35:00</span>
				</div>
			</div>
			<div class="sublevel">
				<div class="left"></div>
				<div class="right"></div>
				<div class="bg">
				</div>
			</div>
			<div class="overlevel">
				<div class="bar">
					<div class="icon">
						<div class="center"></div>
					</div>
				</div>
				<div class="ghost">
					<div class="left_arr"></div>
					<div class="right_arr"></div>
				</div>
			</div>
			<div class="subline">
				<div class="nav_links"></div>
				<div class="info_period">0h 0m</div>
				<div class="period">
					(
					<span class="period_state">fixed</span>
					)
				</div>
			</div>
		</div>
		*/
	}
});

var CGhostBox = Class.create(CDebug, {

	box:		null, // resized dom object
	sideToMove:	null, // 0 - left side, 1 - right side
	flip:		null, // if flip < 0, ghost is fliped

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

	initialize: function($super, id) {
		$super('CGhostBox[' + id + ']');

		this.box = $(id);
		if (is_null(this.box)) {
			throw('Cannot initialize GhostBox with given object id.');
		}
	},

	startResize: function(side) {
		var dimensions = getDimensions(this.box);

		this.sideToMove = side;
		this.flip = 0;
		this.start.width = dimensions.width;
		this.start.leftSide = dimensions.left;
		this.start.rightSide = dimensions.right;
		this.box.style.zIndex = 20;
	},

	endResize: function() {
		this.sideToMove = -1;
		this.flip = 0;
		this.box.style.zIndex = 0;
	},

	calcResizeByPX: function(px) {
		px = parseInt(px, 10);
		this.flip = 0;

		// resize from the left
		if (this.sideToMove == 0) {
			this.flip =  this.start.rightSide - (this.start.leftSide + px);
			if (this.flip < 0) {
				this.current.leftSide = this.start.rightSide;
				this.current.rightSide = this.start.rightSide + Math.abs(this.flip);
			}
			else {
				this.current.leftSide = this.start.leftSide + px;
				this.current.rightSide = this.start.rightSide;
			}
		}
		// resize from the right
		else if (this.sideToMove == 1) {
			this.flip = (this.start.rightSide + px) - this.start.leftSide;
			if (this.flip < 0) {
				this.current.leftSide = this.start.leftSide - Math.abs(this.flip);
				this.current.rightSide = this.start.leftSide;
			}
			else {
				this.current.leftSide = this.start.leftSide;
				this.current.rightSide = this.start.rightSide + px;
			}
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

function sbox_init(sbid, timeline, domobjectid) {
	if (!isset(domobjectid, ZBX_SBOX)) {
		throw('TimeControl: SBOX is not defined for object "' + domobjectid + '".');
	}
	if (is_null(timeline)) {
		throw('Parametrs haven\'t been sent properly.');
	}

	sbid = !is_null(sbid) ? sbid : ZBX_SBOX.length;

	var dims = getDimensions(domobjectid);
	var width = (dims.width - (ZBX_SBOX[domobjectid].shiftL + ZBX_SBOX[domobjectid].shiftR)) - 2;

	ZBX_SBOX[sbid].sbox = new sbox(sbid, timeline, domobjectid, width, ZBX_SBOX[sbid].height);

	// listeners
	addListener(window, 'resize', moveSBoxes);
	addListener(document, 'mouseup', mouseupSBoxes);

	ZBX_SBOX[sbid].sbox.addListeners();

	return ZBX_SBOX[sbid].sbox;
}

var sbox = Class.create(CDebug, {

	sbox_id:			'',		// id to create references in array to self
	timeline:			{},		// timelines object
	mouse_event:		{},		// json object wheres defined needed event params
	start_event:		{},		// copy of mouse_event when box created
	stime:				0,		// new start time
	period:				0,		// new period
	cobj:				{},		// objects params
	dom_obj:			null,	// selection div html obj
	box:				{},		// object params
	dom_box:			null,	// selection box html obj
	dom_period_span:	null,	// period container html obj
	shifts:				{},		// shifts regarding to main objet
	px2time:			null,	// seconds in 1px
	dynamic:			'',		// how page updates, all page/graph only update
	is_active:			false,

	initialize: function($super, sbid, timelineid, domobjectid, width, height) {
		this.sbox_id = sbid;
		$super('CBOX[' + sbid + ']');

		if (!isset(timelineid, ZBX_TIMELINES)) {
			throw('Failed to initialize Selection Box with given TimeLine.');
		}

		// variable initialization
		this.timeline = ZBX_TIMELINES[timelineid];
		this.cobj.width = width;
		this.cobj.height = height;
		this.box.width = 0;
	},

	addListeners: function() {
		var sbox = ZBX_SBOX[this.sbox_id].sbox;
		sbox.clear_params();

		if (sbox.is_active) {
			return;
		}

		var obj = $(this.sbox_id);

		if (is_null(obj)) {
			throw('Failed to initialize Selection Box with given Object.');
		}

		sbox.grphobj = obj;
		sbox.dom_obj = this.create_box_container(obj, this.cobj.height, this.sbox_id);
		sbox.moveSBoxByObj();

		jQuery(sbox.grphobj).off();
		jQuery(sbox.dom_obj).off();

		if (IE) {
			jQuery(sbox.grphobj).mousedown(jQuery.proxy(sbox.mousedown, this));
			sbox.grphobj.onmousemove = this.mousemove.bindAsEventListener(this);
			jQuery('#flickerfreescreen_' + this.sbox_id).find('a').attr('onclick', 'javascript: return ZBX_SBOX["' + this.sbox_id + '"].sbox.ieMouseClick();');
		}
		else {
			jQuery(sbox.dom_obj).mousedown(jQuery.proxy(sbox.mousedown, this));
			jQuery(sbox.dom_obj).mousemove(jQuery.proxy(sbox.mousemove, this));
			jQuery(sbox.dom_obj).click(function(event) { cancelEvent(event); });
			jQuery(sbox.dom_obj).mouseup(jQuery.proxy(sbox.mouseup, this));
		}
	},

	mousedown: function(e) {
		e = e || window.event;

		if (e.which && e.which != 1) {
			return false;
		}
		else if (e.button && e.button != 1) {
			return false;
		}

		this.optimizeEvent(e);
		deselectAll();

		var posxy = getPosition(this.dom_obj);
		if (this.mouse_event.top < posxy.top || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) {
			return true;
		}

		cancelEvent(e);

		if (!ZBX_SBOX[this.sbox_id].sbox.is_active) {
			this.optimizeEvent(e);
			deselectAll();
			this.create_box();

			ZBX_SBOX[this.sbox_id].sbox.is_active = true;
		}
	},

	mousemove: function(e) {
		e = e || window.event;

		if (IE) {
			cancelEvent(e);
		}

		if (ZBX_SBOX[this.sbox_id].sbox.is_active) {
			this.optimizeEvent(e);

			// resize
			if (this.mouse_event.left > (this.cobj.width + ZBX_SBOX[this.sbox_id].additionShiftL)) {
				this.moveright(this.cobj.width - this.start_event.left - ZBX_SBOX[this.sbox_id].additionShiftL);
			}
			else if (this.mouse_event.left < ZBX_SBOX[this.sbox_id].additionShiftL) {
				this.moveleft(ZBX_SBOX[this.sbox_id].additionShiftL, this.start_event.left - ZBX_SBOX[this.sbox_id].additionShiftL);
			}
			else {
				var width = this.validateW(this.mouse_event.left - this.start_event.left);
				var left = this.mouse_event.left - this.shifts.left;

				if (width > 0) {
					this.moveright(width);
				}
				else {
					this.moveleft(left, width);
				}
			}

			this.period = this.calcperiod();

			if (!is_null(this.dom_box)) {
				this.dom_period_span.innerHTML = formatTimestamp(this.period, false, true) + (this.period < 3600 ? ' [min 1h]' : '');
			}
		}
	},

	mouseup: function(e) {
		if (ZBX_SBOX[this.sbox_id].sbox.is_active) {
			cancelEvent(e);

			this.onselect();
			this.clear_params();
		}
	},

	ieMouseClick: function(e) {
		if (!e) {
			e = window.event;
		}

		if (ZBX_SBOX[this.sbox_id].sbox.is_active) {
			this.optimizeEvent(e);
			deselectAll();
			this.mouseup(e);

			return cancelEvent(e);
		}

		if (e.which && e.which != 1) {
			return true;
		}
		else if (e.button && e.button != 1) {
			return true;
		}

		this.optimizeEvent(e);
		deselectAll();

		var posxy = getPosition(this.dom_obj);
		if (this.mouse_event.top < posxy.top || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) {
			return true;
		}

		this.mouseup(e);

		return cancelEvent(e);
	},

	onselect: function() {
		this.px2time = this.timeline.period() / this.cobj.width;
		var userstarttime = this.timeline.usertime() - this.timeline.period();
		userstarttime += Math.round((this.box.left - (ZBX_SBOX[this.sbox_id].additionShiftL - this.shifts.left)) * this.px2time);
		var new_period = this.calcperiod();

		if (this.start_event.left < this.mouse_event.left) {
			userstarttime += new_period;
		}

		this.timeline.period(new_period);
		this.timeline.usertime(userstarttime);

		// synchronize sbox with timeline and scrollbar
		ZBX_TIMELINES[this.timeline.timelineid] = this.timeline;

		for (var sbid in ZBX_SCROLLBARS) {
			if (!empty(ZBX_SCROLLBARS[sbid]) && !empty(ZBX_SCROLLBARS[sbid].timeline)) {
				ZBX_SCROLLBARS[sbid].timeline = this.timeline;
				ZBX_SCROLLBARS[sbid].setBarPosition();
				ZBX_SCROLLBARS[sbid].setGhostByBar();
				ZBX_SCROLLBARS[sbid].setTabInfo();
				ZBX_SCROLLBARS[sbid].updateGlobalTimeline();
			}
		}

		this.onchange(this.sbox_id, this.timeline.timelineid);
	},

	create_box_container: function(obj, height) {
		var id = jQuery(obj).attr('id') + '_box_on';

		if (jQuery('#' + id).length) {
			jQuery('#' + id).remove();
		}

		var div = document.createElement('div');
		div.id = id;
		div.className = 'box_on';
		div.style.height = (height + 2) + 'px';

		jQuery(obj).parent().append(div);

		return div;
	},

	create_box: function() {
		if (!jQuery('#selection_box').length) {
			this.dom_box = document.createElement('div');
			this.dom_obj.appendChild(this.dom_box);
			this.dom_period_span = document.createElement('span');
			this.dom_box.appendChild(this.dom_period_span);
			this.dom_period_span.setAttribute('id', 'period_span');
			this.dom_period_span.innerHTML = this.period;

			var dims = getDimensions(this.dom_obj);

			this.shifts.left = dims.left;
			this.shifts.top = dims.top;

			this.box.top = 0; // we use only x axis
			this.box.left = this.mouse_event.left - dims.left;
			this.box.height = this.cobj.height;

			this.dom_box.setAttribute('id', 'selection_box');
			this.dom_box.style.top = this.box.top + 'px';
			this.dom_box.style.left = this.box.left + 'px';
			this.dom_box.style.height = this.cobj.height + 'px';
			this.dom_box.style.width = '1px';
			this.dom_box.style.zIndex = 98;

			this.start_event.top = this.mouse_event.top;
			this.start_event.left = this.mouse_event.left;

			if (IE) {
				this.dom_box.onmousemove = this.mousemove.bindAsEventListener(this);
			}
		}
	},

	moveleft: function(left, width) {
		if (!is_null(this.dom_box)) {
			this.dom_box.style.left = left + 'px';
		}

		this.box.width = Math.abs(width);

		if (!is_null(this.dom_box)) {
			this.dom_box.style.width = this.box.width + 'px';
		}
	},

	moveright: function(width) {
		if (!is_null(this.dom_box)) {
			this.dom_box.style.left = this.box.left + 'px';
		}
		if (!is_null(this.dom_box)) {
			this.dom_box.style.width = width + 'px';
		}

		this.box.width = width;
	},

	calcperiod: function() {
		var new_period;

		if (this.box.width + 1 >= this.cobj.width) {
			new_period = this.timeline.period();
		}
		else {
			this.px2time = this.timeline.period() / this.cobj.width;
			new_period = Math.round(this.box.width * this.px2time);
		}

		return new_period;
	},

	validateW: function(w) {
		if ((this.start_event.left - ZBX_SBOX[this.sbox_id].additionShiftL + w) > this.cobj.width) {
			w = 0;
		}
		if (this.mouse_event.left < ZBX_SBOX[this.sbox_id].additionShiftL) {
			w = 0;
		}

		return w;
	},

	validateH: function(h) {
		if (h <= 0) {
			h = 1;
		}
		if ((this.start_event.top - this.cobj.top + h) > this.cobj.height) {
			h = this.cobj.height - this.start_event.top;
		}

		return h;
	},

	moveSBoxByObj: function() {
		var posxy = jQuery('#' + jQuery(this.grphobj).attr('id')).position();
		var dims = getDimensions(this.grphobj);

		this.dom_obj.style.top = (posxy.top + ZBX_SBOX[this.sbox_id].shiftT) + 'px';
		this.dom_obj.style.left = posxy.left + 'px';
		if (dims.width > 0) {
			this.dom_obj.style.width = dims.width + 'px';
		}

		this.cobj.top = posxy.top + ZBX_SBOX[this.sbox_id].shiftT;
		ZBX_SBOX[this.sbox_id].additionShiftL = posxy.left + ZBX_SBOX[this.sbox_id].shiftL;
	},

	optimizeEvent: function(e) {
		if (!empty(e.pageX) && !empty(e.pageY)) {
			this.mouse_event.left = e.pageX - jQuery('#flickerfreescreen_' + jQuery(this.grphobj).attr('id')).position().left;
			this.mouse_event.top = e.pageY;
		}
		else if (!empty(e.clientX) && !empty(e.clientY)) {
			this.mouse_event.left = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft - jQuery('#flickerfreescreen_' + jQuery(this.grphobj).attr('id')).position().left;
			this.mouse_event.top = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
		}
		else {
			this.mouse_event.left = parseInt(this.mouse_event.left);
			this.mouse_event.top = parseInt(this.mouse_event.top);
		}

		if (this.mouse_event.left < ZBX_SBOX[this.sbox_id].additionShiftL) {
			this.mouse_event.left = ZBX_SBOX[this.sbox_id].additionShiftL;
		}
		else if (this.mouse_event.left > (this.cobj.width + ZBX_SBOX[this.sbox_id].additionShiftL)) {
			this.mouse_event.left = this.cobj.width + ZBX_SBOX[this.sbox_id].additionShiftL;
		}
	},

	clear_params: function() {
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

function moveSBoxes() {
	for (var sbid in ZBX_SBOX) {
		if (!empty(ZBX_SBOX[sbid]) && isset('sbox', ZBX_SBOX[sbid])) {
			ZBX_SBOX[sbid].sbox.moveSBoxByObj();
		}
	}
}

function mouseupSBoxes(e) {
	for (var sbid in ZBX_SBOX) {
		if (!empty(ZBX_SBOX[sbid]) && isset('sbox', ZBX_SBOX[sbid])) {
			ZBX_SBOX[sbid].sbox.mouseup(e);
		}
	}
}

/**
 * Optimization:
 *
 * 86400 = 24 * 60 * 60
 * 31536000 = 365 * 86400
 * 2592000 = 30 * 86400
 * 604800 = 7 * 86400
 */
function formatTimestamp(timestamp, isTsDouble, isExtend) {
	timestamp = timestamp || 0;
	var years = 0;
	var months = 0;
	var weeks = 0;

	if (isExtend) {
		years = parseInt(timestamp / 31536000);
		months = parseInt((timestamp - years * 31536000) / 2592000);
		//weeks = parseInt((timestamp - years * 31536000 - months * 2592000) / 604800);
	}

	var days = parseInt((timestamp - years * 31536000 - months * 2592000 - weeks * 604800) / 86400);
	var hours = parseInt((timestamp - years * 31536000 - months * 2592000 - weeks * 604800 - days * 86400) / 3600);
	var minutes = parseInt((timestamp - years * 31536000 - months * 2592000 - weeks * 604800 - days * 86400 - hours * 3600) / 60);

	if (isTsDouble) {
		if (months.toString().length == 1) {
			months = '0' + months;
		}
		if (weeks.toString().length == 1) {
			weeks = '0' + weeks;
		}
		if (days.toString().length == 1) {
			days = '0' + days;
		}
		if (hours.toString().length == 1) {
			hours = '0' + hours;
		}
		if (minutes.toString().length == 1) {
			minutes = '0' + minutes;
		}
	}

	var str = (years == 0) ? '' : years + locale['S_YEAR_SHORT'] + ' ';
	str += (months == 0) ? '' : months + locale['S_MONTH_SHORT'] + ' ';
	str += (weeks == 0) ? '' : weeks + locale['S_WEEK_SHORT'] + ' ';
	str += (isExtend && isTsDouble)
		? days + locale['S_DAY_SHORT'] + ' '
		: (days == 0)
			? ''
			: days + locale['S_DAY_SHORT'] + ' ';
	str += (hours == 0) ? '' : hours + locale['S_HOUR_SHORT'] + ' ';
	str += (minutes == 0) ? '' : minutes + locale['S_MINUTE_SHORT'] + ' ';

	return str;
}
