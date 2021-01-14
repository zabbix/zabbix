/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


var PMasters = {};

function initPMaster(pmid, args) {
	if (typeof PMasters[pmid] === 'undefined') {
		PMasters[pmid] = new CPMaster(pmid, args);
	}
}

var CPMaster = function(pmid, obj4upd) {
	this.pmasterid = pmid;

	var doll = [];
	for (var id in obj4upd) {
		if (typeof(obj4upd[id]) != 'undefined' && !is_null(obj4upd[id])) {
			doll = obj4upd[id];

			if (typeof(doll['frequency']) == 'undefined') {
				doll['frequency'] = 60;
			}
			if (typeof(doll['url']) == 'undefined') {
				doll['url'] = location.href;
			}
			if (typeof(doll['counter']) == 'undefined') {
				doll['counter'] = 0;
			}
			if (typeof(doll['darken']) == 'undefined') {
				doll['darken'] = 0;
			}
			if (typeof(doll['params']) == 'undefined') {
				doll['params'] = [];
			}

			this.addStartDoll(id, doll.frequency, doll.url, doll.counter, doll.darken, doll.params);
		}
	}
};

CPMaster.prototype = {
	pmasterid:	0,	// pmasters reference id
	dolls:		[],	// list of updated objects

	addStartDoll: function(domid, frequency, url, counter, darken, params) {
		this.addDoll(domid, frequency, url, counter, darken, params);
		this.dolls[domid].startDoll();

		return this.dolls[domid];
	},

	addDoll: function(domid, frequency, url, counter, darken, params) {
		var obj = document.getElementById(domid);
		if (typeof(obj) == 'undefined') {
			return false;
		}

		if (typeof(this.dolls[domid]) != 'undefined') {
			return this.dolls[domid];
		}

		var obj4update = {
			'domid':		domid,
			'url':			url,
			'params':		params,
			'frequency':	frequency,
			'darken':		darken,
			'lastupdate':	0,
			'counter':		0,
			'ready':		true
		};

		this.dolls[domid] = new CDoll(obj4update);
		this.dolls[domid]._pmasterid = this.pmasterid;

		return this.dolls[domid];
	},

	rmvDoll: function(domid) {
		if (typeof(this.dolls[domid]) != 'undefined' && !is_null(this.dolls[domid])) {
			this.dolls[domid].pexec.stop();
			this.dolls[domid].pexec = null;
			delete this.dolls[domid];
		}
	},

	startAllDolls: function() {
		for (var domid in this.dolls) {
			if (typeof(this.dolls[domid]) != 'undefined' && !is_null(this.dolls[domid])) {
				this.dolls[domid].startDoll();
			}
		}
	},

	stopAllDolls: function() {
		for (var domid in this.dolls) {
			if (typeof(this.dolls[domid]) != 'undefined' && !is_null(this.dolls[domid])) {
				this.dolls[domid].stopDoll();
			}
		}
	},

	clear: function() {
		for (var domid in this.dolls) {
			this.rmvDoll(domid);
		}
		this.dolls = [];
	}
};

var CDoll = function(obj4update) {
		this._domid = obj4update.domid;
		this._domobj = jQuery('#'+this._domid);
		this._domobj_header = jQuery('#'+this._domid+'_header');
		this._domobj_footer = jQuery('#'+this._domid+'_footer');
		this.url(obj4update.url);
		this.frequency(obj4update.frequency);
		this.lastupdate(obj4update.lastupdate);
		this.darken(obj4update.darken);
		this.counter(obj4update.counter);
		this.params(obj4update.params);
		this.ready(obj4update.ready);
	};

CDoll.prototype = {
	_pmasterid:		0,		// PMasters id to which doll belongs
	_domobj:		null,	// DOM obj body for update
	_domobj_header:	null,	// DOM obj header for update
	_domobj_footer:	null,	// DOM obj footer for update
	_domid:			null,	// DOM obj id
	_domdark:		null,	// DOM div for darken updated obj
	_url:			'',
	_frequency:		60,		// min 5 sec
	_darken:		0,		// make updated object darken - 1
	_lastupdate:	0,
	_counter:		0,		// how many times do update, 0 - infinite
	_params:		'',
	_status:		false,
	_ready:			false,
	timer:			null,	// timer object
	updating:		false,
	min_freq:		5,		// seconds

	startDoll: function() {
		if (this.timer === null) {
			this.lastupdate(0);

			var doll = this;
			this.timer = setInterval(function () {
				if (!doll.updating) {
					try {
						doll.updating = true;
						doll.check4Update();
						doll.updating = false;
					} catch(e) {
						doll.updating = false;
						throw e;
					}
				}
			}, this._frequency * 1000);
			this.check4Update();
		}
	},

	restartDoll: function() {
		this.stopDoll();
		this.startDoll();
	},

	stopDoll: function() {
		if (this.timer !== null) {
			clearInterval(this.timer);
			this.timer = null;
		}
	},

	pmasterid: function() {
		return this._pmasterid;
	},

	domid: function() {
		return this._domid;
	},

	domobj: function() {
		return this._domobj;
	},

	url: function(url_) {
		if ('undefined' == typeof(url_)) {
			return this._url;
		}
		else {
			this._url = url_;
		}
	},

	frequency: function(frequency_) {
		if ('undefined' == typeof(frequency_)) {
			return this._frequency;
		}
		else {
			if (frequency_ < this.min_freq) {
				frequency_ = this.min_freq;
			}
			this._frequency=parseInt(frequency_);
		}
	},

	lastupdate: function(lastupdate_) {
		if ('undefined' == typeof(lastupdate_)) {
			return this._lastupdate;
		}
		else {
			this._lastupdate=lastupdate_;
		}
	},

	darken: function(darken_) {
		if ('undefined' == typeof(darken_)) {
			return this._darken;
		}
		else {
			this._darken = darken_;
		}
	},

	counter: function(counter_) {
		if ('undefined' == typeof(counter_)) {
			return Math.abs(this._counter);
		}
		else {
			this._counter = counter_;
		}
	},

	ready: function(ready_) {
		if ('undefined' == typeof(ready_)) {
			return this._ready;
		}
		else {
			this._ready = ready_;
		}
	},

	params: function(params_) {
		if ('undefined' == typeof(params_)) {
			return this._params;
		}
		else {
			this._params = params_;
		}
	},

	check4Update: function() {
		var now = parseInt(new Date().getTime() / 1000);

		if (this._ready && ((this._lastupdate + this._frequency) < (now + this.min_freq))) {
			this.update();
			this._lastupdate = now;
		}
	},

	update: function() {
		this._ready = false;

		if (this._counter == 1) {
			this.stopDoll();
		}
		if (this._darken) {
			this.setDarken();
		}

		var url = new Curl(this._url);
		url.setArgument('upd_counter', this.counter());
		url.setArgument('pmasterid', this.pmasterid());

		new jQuery.ajax(url.getUrl(), {
				'method': 'post',
				'data': this._params,
				'success': this.onSuccess.bind(this),
				'error': this.onFailure.bind(this)
			}
		);

		this._counter--;
	},

	onSuccess: function(_, _, resp) {
		this.rmwDarken();

		var headers = resp.getAllResponseHeaders();

		if (headers.indexOf('Ajax-response: false') > -1) {
			return false;
		}
		else {
			if (is_null(resp.responseJSON)) {
				// If plaintext, slide show data
				this._domobj.html(resp.responseText);
				this._domobj_footer.html('');
			}
			else
			{
				var debug = is_null(resp.responseJSON.debug) ? '' : resp.responseJSON.debug;

				// Dashboard widget data comes in JSON
				this._domobj.html(resp.responseJSON.body + debug);
				this._domobj_header.html(resp.responseJSON.header);
				this._domobj_footer.html(resp.responseJSON.footer);
			}
		}

		this._ready = true;
	},

	onFailure: function(resp) {
		this.rmwDarken();
		this._ready = true;
	},

	setDarken: function() {
		if (is_null(this._domobj)) {
			return false;
		}

		if (is_null(this._domdark)) {
			this._domdark = document.createElement('div');
			document.body.appendChild(this._domdark);
			this._domdark.className = 'onajaxload';
		}

		var obj_params = getPosition(this._domobj);
		obj_params.height = this._domobj.offsetHeight;
		obj_params.width = this._domobj.offsetWidth;

		this._domdark.style.top = obj_params.top + 'px';
		this._domdark.style.left = obj_params.left + 'px';
		this._domdark.style.width = obj_params.width + 'px';
		this._domdark.style.height = obj_params.height + 'px';
	},

	rmwDarken: function() {
		if (!is_null(this._domdark)) {
			this._domdark.style.cursor = 'auto';

			document.body.removeChild(this._domdark);
			this._domdark = null;
		}
	}
};
