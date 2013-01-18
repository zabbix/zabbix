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

	// data
	objectList: {},
	timeline: null,
	scrollbar: null,

	// options
	refreshPage: true,
	timeRefreshInterval: 0,
	timeRefreshTimeoutHandler: null,

	addObject: function(id, time, objData) {
		this.objectList[id] = {
			id: id,
			containerid: null,
			refresh: false,
			processed: 0,
			time: {},
			objDims: {},
			src: location.href,
			dynamic: 1,
			periodFixed: 1,
			loadSBox: 0,
			loadImage: 0,
			loadScroll: 0,
			mainObject: 0, // object on changing will reflect on all others
			sliderMaximumTimePeriod: null // max period in seconds
		};

		for (var key in this.objectList[id]) {
			if (isset(key, objData)) {
				this.objectList[id][key] = objData[key];
			}
		}

		this.objectList[id].time = time;
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

				obj.time.usertime = (!isset('usertime', obj.time))
					? obj.time.endtime
					: nowDate.setZBXDate(obj.time.usertime) / 1000;

				this.timeline = new CTimeLine(
					parseInt(obj.time.period),
					parseInt(obj.time.starttime),
					parseInt(obj.time.usertime),
					parseInt(obj.time.endtime),
					obj.sliderMaximumTimePeriod,
					obj.time.isNow
				);

				// scrollbar
				var width = get_bodywidth() - 30;
				if (!is_number(width)) {
					width = 900;
				}
				else if (width < 600) {
					width = 600;
				}

				this.scrollbar = new CScrollBar(width, obj.periodFixed, obj.sliderMaximumTimePeriod);
				this.scrollbar.onchange = this.objectUpdate.bind(this);
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
			img.setAttribute('src', obj.src);
			$(obj.containerid).appendChild(img);
		}

		if (obj.loadSBox && empty(obj.sbox_listener)) {
			obj.sbox_listener = this.addSBox.bindAsEventListener(this, id);
			addListener(img, 'load', obj.sbox_listener);
			addListener(img, 'load', sboxGlobalMove);
		}
	},

	refreshImage: function(id) {
		var obj = this.objectList[id],
			period = this.timeline.period(),
			stime = new CDate((this.timeline.usertime() - this.timeline.period()) * 1000).getZBXDate();

		// image
		var imgUrl = new Curl(obj.src);
		imgUrl.setArgument('period', period);
		imgUrl.setArgument('stime', stime);

		jQuery('<img />', {id: id + '_tmp', src: imgUrl.getUrl()}).load(function() {
			var imgId = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));

			jQuery('#' + imgId).replaceWith(jQuery(this));
			jQuery(this).attr('id', imgId);
		});

		// link
		var graphUrl = new Curl(jQuery('#' + obj.containerid).attr('href'));
		graphUrl.setArgument('width', obj.objDims.width);
		graphUrl.setArgument('period', period);
		graphUrl.setArgument('stime', stime);

		jQuery('#' + obj.containerid).attr('href', graphUrl.getUrl());
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

			// scrollbar
			this.scrollbar.setBarPosition();
			this.scrollbar.setGhostByBar();
			this.scrollbar.setTabInfo();
			this.scrollbar.resetIsNow();

			// plan next time update
			this.timeRefreshTimeoutHandler = window.setTimeout(function() { timeControl.refreshTime(); }, this.timeRefreshInterval);
		}
	},

	objectUpdate: function() {
		var usertime = this.timeline.usertime(),
			period = this.timeline.period();

		// secure browser from fast user operations
		if (isNaN(usertime) || isNaN(period)) {
			for (var id in this.objectList) {
				if (isset(id, ZBX_SBOX)) {
					ZBX_SBOX[id].clearParams();
				}
			}

			return;
		}

		if (this.timeline.now() || this.timeline.isNow()) {
			usertime += 31536000; // 31536000 = 86400 * 365 = 1 year
		}

		var date = new CDate((usertime - period) * 1000);

		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('period', period);
			url.setArgument('stime', date.getZBXDate());
			url.unsetArgument('output');

			location.href = url.getUrl();
		}
		else {
			flickerfreeScreen.refreshAll(period, date.getZBXDate(), this.timeline.isNow());
		}
	},

	objectReset: function() {
		var usertime = 1600000000,
			period = 3600,
			stime = 201911051255;

		this.timeline.period(period);
		this.timeline.usertime(usertime);
		this.scrollbar.setBarPosition();
		this.scrollbar.setGhostByBar();
		this.scrollbar.setTabInfo();

		if (this.refreshPage) {
			var url = new Curl(location.href);
			url.setArgument('period', period);
			url.setArgument('stime', stime);
			url.unsetArgument('output');

			location.href = url.getUrl();
		}
		else {
			flickerfreeScreen.refreshAll(period, stime, true);
		}
	},

	addSBox: function(e, id) {
		var sbox = sbox_init(id);
		sbox.onchange = this.objectUpdate.bind(this);
	}
};

// timeline control
var CTimeLine = Class.create(CDebug, {

	_starttime:	null,	// timeline start time (left, past)
	_endtime:	null,	// timeline end time (right, now)
	_usertime:	null,	// selected end time (bar, user selection)
	_period:	null,	// selected period
	_now:		false,	// state if time is set to NOW
	_isNow:		false,	// state if time is set to NOW (for outside usage)
	minperiod:	3600,	// minimal allowed period
	maxperiod:	null,	// max period in seconds

	initialize: function($super, period, starttime, usertime, endtime, maximumPeriod, isNow) {
		if ((endtime - starttime) < (3 * this.minperiod)) {
			starttime = endtime - (3 * this.minperiod);
		}

		this._starttime = starttime;
		this._endtime = endtime;
		this._usertime = usertime;
		this._period = period;
		this.maxperiod = maximumPeriod;

		// re-validate
		this.period(period);
		this.isNow(isNow);
	},

	now: function() {
		this._now = ((this._usertime + 60) > this._endtime);

		return this._now;
	},

	isNow: function(isNow) {
		if (typeof(isNow) == 'undefined') {
			return this._isNow;
		}

		this._isNow = (isNow == 1) ? true : (isNow ? isNow : false);
	},

	setNow: function() {
		var end = parseInt(new CDate().getTime() / 1000);

		this._endtime = end;
		this._usertime = end;
		this._now = true;
	},

	refreshEndtime: function() {
		this._endtime = parseInt(new CDate().getTime() / 1000);
	},

	period: function(period) {
		if (empty(period)) {
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
		if (empty(usertime)) {
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
		if (empty(starttime)) {
			return this._starttime;
		}

		this._starttime = starttime;

		return this._starttime;
	},

	endtime: function(endtime) {
		if (empty(endtime)) {
			return this._endtime;
		}

		this._endtime = endtime;

		return this._endtime;
	}
});

// graph scrolling
var CScrollBar = Class.create(CDebug, {

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

	initialize: function($super, width, fixedperiod, maximalPeriod) {
		try {
			this.fixedperiod = (fixedperiod == 1) ? 1 : 0;
			this.maxperiod = maximalPeriod;

			// create scrollbar
			this.scrollCreate(width);

			// variable initialization
			this.ghostBox = new CGhostBox(this.dom.ghost);
			this.size.scrollline = width - 36; // border (17 * 2) + 2 = 36
			this.px2sec = (timeControl.timeline.endtime() - timeControl.timeline.starttime()) / this.size.scrollline;

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
		catch (e) {
			throw('ERROR: ScrollBar initialization failed!');
		}
	},

	onBarChange: function() {
		this.resetIsNow();
		this.changed = 1;
		this.onchange();
	},

	resetIsNow: function() {
		timeControl.timeline.isNow(timeControl.timeline.now());
	},

	//------- MOVE -------
	setFullPeriod: function() {
		if (this.disabled) {
			return false;
		}

		timeControl.timeline.setNow();
		timeControl.timeline.period(this.maxperiod);

		this.setBarPosition();
		this.setGhostByBar();
		this.setTabInfo();
		this.onBarChange();
	},

	setZoom: function(e, zoom) {
		if (this.disabled) {
			return false;
		}

		timeControl.timeline.period(zoom);

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
		if (empty(left)) {
			period = timeControl.timeline.period();
		}

		// fixed
		if (this.fixedperiod == 1) {
			var usertime = timeControl.timeline.usertime(),
				new_usertime = (period)
					? usertime - period // by clicking this.dom.left we move bar by period
					: usertime - left;

			// if we slide to another timezone
			new_usertime -= this.getTZdiff(usertime, new_usertime);

			timeControl.timeline.usertime(new_usertime);
		}
		// dynamic
		else {
			var new_period = (period)
				? timeControl.timeline.period() + 86400 // by clicking this.dom.left we expand period by 1day
				: timeControl.timeline.period() + left;

			timeControl.timeline.period(new_period);
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
			period = timeControl.timeline.period();
		}

		var usertime = timeControl.timeline.usertime();

		// fixed
		if (this.fixedperiod == 1) {
			var new_usertime = (period)
				? new_usertime = usertime + period // by clicking this.dom.left we move bar by period
				: usertime + right;
		}
		// dynamic
		else {
			if (period) {
				var new_period = timeControl.timeline.period() + 86400; // by clicking this.dom.left we expand period by 1day
				var new_usertime = usertime + 86400; // by clicking this.dom.left we move bar by period
			}
			else {
				var new_period = timeControl.timeline.period() + right;
				var new_usertime = usertime + right;
			}

			timeControl.timeline.period(new_period);
		}

		// if we slide to another timezone
		new_usertime -= this.getTZdiff(usertime, new_usertime);

		timeControl.timeline.usertime(new_usertime);

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
			width = Math.round(timeControl.timeline.period() / this.px2sec);
			periodWidth = width;
		}
		else {
			width = periodWidth;
		}

		if (is_null(rightSide)) {
			var userTime = timeControl.timeline.usertime(),
				startTime = timeControl.timeline.starttime();

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
			timeControl.timeline.usertime(time + timeControl.timeline.period());
		}
		// dynamic
		else {
			timeControl.timeline.period(Math.abs(timeControl.timeline.usertime() - time));
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
			timeControl.timeline.usertime(time);
		}
		// dynamic
		else {
			var startusertime = timeControl.timeline.usertime() - timeControl.timeline.period();
			timeControl.timeline.usertime(time);
			timeControl.timeline.period(timeControl.timeline.usertime() - startusertime);
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
		this.ghostBox.userstartime = timeControl.timeline.usertime();
		this.ghostBox.usertime = timeControl.timeline.usertime();
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
		this.ghostBox.userstartime = timeControl.timeline.usertime() - timeControl.timeline.period();
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
		var date = new CDate(time1 * 1000),
			timezoneOffset = date.getTimezoneOffset();

		date.setTime(time2 * 1000);

		return (timezoneOffset - date.getTimezoneOffset()) * 60;
	},

	roundTime: function(usertime) {
		var time = parseInt(usertime);

		if (time > 86400) {
			var date = new CDate();
			date.setTime(time * 1000);
			date.setHours(0);
			date.setMinutes(0);
			date.setSeconds(0);
			date.setMilliseconds(0);
			time = parseInt(date.getTime() / 1000);
		}

		return time;
	},

	updateTimeLine: function(dim) {
		// timeline update
		var period = timeControl.timeline.period();
		var new_usertime = parseInt(dim.right * this.px2sec, 10) + timeControl.timeline.starttime();
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
				timeControl.timeline.period(new_period);
			}

			timeControl.timeline.setNow();
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
				timeControl.timeline.period(new_period);
			}

			timeControl.timeline.usertime(new_usertime);
		}

		this.resetIsNow();
	},

	setTabInfo: function() {
		var period = timeControl.timeline.period(),
			usertime = timeControl.timeline.usertime();

		// secure browser from incorrect user actions
		if (isNaN(period) || isNaN(usertime)) {
			return;
		}

		this.dom.info_period.innerHTML = formatTimestamp(period, false, true);

		// info left
		this.dom.info_left.innerHTML = new Date((usertime - period) * 1000).format(locale['S_DATE_FORMAT']);

		// info right
		var right_info = new Date(usertime * 1000).format(locale['S_DATE_FORMAT']);

		if (timeControl.timeline.now()) {
			right_info += ' (' + locale['S_NOW_SMALL'] + '!) ';
		}
		this.dom.info_right.innerHTML = right_info;

		// seting zoom link styles
		this.setZoomLinksStyle();
	},

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
		this.clndrLeft = create_calendar(timeControl.timeline.usertime() - timeControl.timeline.period(), this.dom.info_left, null, null, 'scrollbar_cntr');
		this.clndrRight = create_calendar(timeControl.timeline.usertime(), this.dom.info_right, null, null, 'scrollbar_cntr');
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
		var timeline = timeControl.timeline.endtime() - timeControl.timeline.starttime();
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
		var timeline = timeControl.timeline.endtime() - timeControl.timeline.starttime();
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
		var period = timeControl.timeline.period();

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
		if (period == (timeControl.timeline.endtime() - timeControl.timeline.starttime())) {
			this.dom.linklist[i].style.textDecoration = 'none';
			this.dom.linklist[i].style.fontWeight = 'bold';
			this.dom.linklist[i].style.fontSize = '11px';
		}
	},

	scrollCreate: function(width) {
		var scr_cntr = $('scrollbar_cntr');
		if (is_null(scr_cntr)) {
			throw('ERROR: SCROLL [scrollcreate]: scroll container node is not found!');
		}

		// remove existed scrollbars
		jQuery('.scrollbar').remove();

		scr_cntr.style.paddingRight = '2px';
		scr_cntr.style.paddingLeft = '2px';
		scr_cntr.style.margin = '5px 0 0 0';

		this.dom.scrollbar = document.createElement('div');
		this.dom.scrollbar.className = 'scrollbar';
		scr_cntr.appendChild(this.dom.scrollbar);

		Element.extend(this.dom.scrollbar);
		this.dom.scrollbar.setStyle({width: width + 'px'});

		// <info>
		this.dom.info = document.createElement('div');
		this.dom.scrollbar.appendChild(this.dom.info);
		this.dom.info.className = 'info';
		$(this.dom.info).setStyle({width: width + 'px'});

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
		$(this.dom.sublevel).setStyle({width: width + 'px'});

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
		$(this.dom.overlevel).setStyle({width: (width - 34) + 'px'});

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
		$(this.dom.subline).setStyle({width: width + 'px'});

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

function sbox_init(id) {
	ZBX_SBOX[id] = new sbox(id);

	// global listeners
	addListener(window, 'resize', sboxGlobalMove);
	addListener(document, 'mouseup', sboxGlobalMouseup);
	addListener(document, 'mousemove', sboxGlobalMousemove);
	ZBX_SBOX[id].addListeners();

	return ZBX_SBOX[id];
}

var sbox = Class.create(CDebug, {

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

	initialize: function($super, id) {
		var tc = timeControl.objectList[id],
			shiftL = parseInt(tc.objDims.shiftXleft),
			shiftR = parseInt(tc.objDims.shiftXright),
			width = getDimensions(id).width - (shiftL + shiftR) - 2;

		this.sbox_id = id;
		this.containerId = '#flickerfreescreen_' + id;
		this.shiftT = parseInt(tc.objDims.shiftYtop);
		this.shiftL = shiftL;
		this.shiftR = shiftR;
		this.additionShiftL = 0;
		this.areaWidth = width;
		this.areaHeight = parseInt(tc.objDims.graphHeight);
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

		if (IE) {
			jQuery(this.grphobj).mousedown(jQuery.proxy(this.mouseDown, this));
			this.grphobj.onmousemove = this.mouseMove.bindAsEventListener(this);
			jQuery(this.containerId).find('a').attr('onclick', 'javascript: return ZBX_SBOX["' + this.sbox_id + '"].ieMouseClick();');
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
		deselectAll();

		var posxy = getPosition(this.dom_obj);
		if (this.mouse_event.top < posxy.top || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) {
			return true;
		}

		cancelEvent(e);

		if (!this.is_active) {
			this.optimizeEvent(e);
			deselectAll();
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
				this.dom_period_span.innerHTML = formatTimestamp(this.period, false, true) + (this.period < 3600 ? ' [min 1h]' : '');
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
			deselectAll();
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
		deselectAll();

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

		timeControl.timeline.period(new_period);
		timeControl.timeline.usertime(userstarttime);
		timeControl.scrollbar.setBarPosition();
		timeControl.scrollbar.setGhostByBar();
		timeControl.scrollbar.setTabInfo();
		timeControl.scrollbar.resetIsNow();

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
		this.dom_obj.style.height = (this.areaHeight + 2) + 'px';

		jQuery(this.grphobj).parent().append(this.dom_obj);
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

			this.shifts.left = dims.left;
			this.shifts.top = dims.top;

			this.box.top = 0; // we use only x axis
			this.box.left = this.mouse_event.left - dims.left;
			this.box.height = this.areaHeight;

			this.dom_box.setAttribute('id', 'selection_box');
			this.dom_box.style.top = this.box.top + 'px';
			this.dom_box.style.left = this.box.left + 'px';
			this.dom_box.style.height = this.areaHeight + 'px';
			this.dom_box.style.width = '1px';
			this.dom_box.style.zIndex = 98;

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
		var posxy = jQuery('#' + jQuery(this.grphobj).attr('id')).position();
		var dims = getDimensions(this.grphobj);

		this.dom_obj.style.top = (posxy.top + this.shiftT) + 'px';
		this.dom_obj.style.left = posxy.left + 'px';
		if (dims.width > 0) {
			this.dom_obj.style.width = dims.width + 'px';
		}

		this.additionShiftL = posxy.left + this.shiftL;
	},

	optimizeEvent: function(e) {
		if (!empty(e.pageX) && !empty(e.pageY)) {
			this.mouse_event.left = e.pageX - jQuery(this.containerId).position().left;
			this.mouse_event.top = e.pageY;
		}
		else if (!empty(e.clientX) && !empty(e.clientY)) {
			this.mouse_event.left = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft - jQuery(this.containerId).position().left;
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
