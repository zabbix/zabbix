// JavaScript Document
var ZBX_TIMELINES = {};

function create_timeline(tlid, period, starttime, usertime, endtime){
	if(is_null(tlid)){
		var tlid = ZBX_TIMELINES.length;
	}
	
	var now = new Date();
	now = parseInt(now.getTime() / 1000);

	if('undefined' == typeof(usertime)) usertime = now;
	if('undefined' == typeof(endtime)) endtime = now;
	
	ZBX_TIMELINES[tlid] = new CTimeLine(tlid, period, starttime, usertime, endtime);
	
return ZBX_TIMELINES[tlid];
}

var CTimeLine = Class.create();

CTimeLine.prototype = {
timelineid: null,			// own id in array

_starttime: null,				// timeline start time (left, past)
_endtime: null,					// timeline end time (right, now)

_usertime: null,				// selected end time (bar, user selection)
_period: null,					// selected period

minperiod: 3600,				// minimal allowed period

// DEBUG
debug_status: 	1,			// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 	'',			// debug string
debug_prev:		'',			// don't log repeated fnc

initialize: function(id, period, starttime, usertime, endtime){
	this.debug('initialize', id);
	
	this.timelineid = id;

	if((endtime - starttime) < (3*this.minperiod)) starttime = endtime - (3*this.minperiod);
	
	this.starttime(starttime);
	this.endtime(endtime);

	this.usertime(usertime);
	this.period(period);
},

now: function(){
	var tmp_date = new Date();
return parseInt(tmp_date.getTime()/1000);
},

setNow: function(){
	var end = this.now();
	
	this._endtime = end;
	this._usertime = end;
},


period: function(period){
	this.debug('period');

	if('undefined'==typeof(period)) return this._period;

	if((this._usertime - period) < this._starttime)  period = this._usertime - this._starttime;
	
//	if((this._usertime - this.period() + period) > this._endtime) period = this._period + this._endtime - this._usertime;
	if(period < this.minperiod) period = this.minperiod;
	
	this._period = period;

return this._period;
},

usertime: function(usertime){
	this.debug('usertime');
	
	if('undefined'==typeof(usertime)) return this._usertime;

	if((usertime + this.minperiod) < this._starttime) usertime = this._starttime + this.minperiod;
	if(usertime > this._endtime) usertime = this._endtime;

	this._usertime = usertime;
	
return this._usertime;
},

starttime: function(starttime){
	this.debug('starttime');

	if('undefined'==typeof(starttime)) return this._starttime;
	
	this._starttime = starttime;

return this._starttime;
},

endtime: function(endtime){
	this.debug('endtime');
	
	if('undefined'==typeof(endtime)) return this._endtime;
	
	if(endtime < (this._starttime+this._period*3)) endtime = this._starttime+this._period*3;

	this._endtime = endtime;

return this._endtime;
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'CTimeLine['+this.timelineid+'].'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

		if(this.debug_prev == str) return true;

		this.debug_info += str + '\n';
		if(this.debug_status == 2){
			SDI(str);
		}
		
		this.debug_prev = str;
	}
}
}