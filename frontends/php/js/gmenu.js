// JavaScript Document
/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**
*/
// Title: graph period menu class
// Author: Aly

<!--

var G_MENU;						//gmenu obj reference
var _PE_GM = null;				// Periodical executer obj reference
var GMENU_IMG_PATH='images/general/bar';
//var cal = new calendar();

function gmenuinit(top,left,period,bar_stime){
	
	gmenucreate(top,left);
	
	period = period || 3600;
	bar_stime = bar_stime || 0;
	
	G_MENU = new gmenu(period,bar_stime);
	
	G_MENU.gm_value = $('gmenu_period_value');
	G_MENU.gm_type = $('gmenu_period_type');
	
	if(is_null(G_MENU.gm_value) || is_null(G_MENU.gm_type)){ 
		G_MENU = null;
		return false;
	}
	
	G_MENU.gm_gmenu = $('gmenu');
	
	G_MENU.gm_minute = $('gmenu_minute');
	G_MENU.gm_hour = $('gmenu_hour');
	G_MENU.gm_day = $('gmenu_day');
	G_MENU.gm_month = $('gmenu_month');
	G_MENU.gm_year = $('gmenu_year');
	
	G_MENU.syncBSDateByBSTime();

	G_MENU.calcPeriodAndTypeByUnix(period);
	G_MENU.setBSDate();
	G_MENU.setPeriod();
	G_MENU.setPeriodType();
	
	
	var gm_gm_msover = function(e){ 
		if(!is_null(_PE_SB)){
			_PE_SB.stop();
			_PE_SB = null;
		}

		G_MENU.msover = 1;
		if(IE){
			e.cancelBubble = true;
		}
		else{ 
			e.stopPropagation();
		}
	}

	var gm_gm_msout = function(e){ 
		if(!IE && (e.eventPhase != 2)){ 
			if(IE){
				e.cancelBubble = true;
			}
			else{ 
				e.stopPropagation();
			}
			return;
		}

		G_MENU.msover = 0;
		if(is_null(_PE_GM)){
			_PE_GM = new PeriodicalExecuter(G_MENU.ongmmsout.bind(G_MENU),0.5);
		}
	}
	
	if(IE){
		G_MENU.gm_gmenu.attachEvent('onmouseover',gm_gm_msover);		
		
		G_MENU.gm_gmenu.attachEvent('onmouseout',gm_gm_msout);
		
		$('gmenu_load').attachEvent('onclick',G_MENU.ongmload.bindAsEventListener(G_MENU));
		$('gmenu_hide').attachEvent('onclick',G_MENU.gmenuhide.bindAsEventListener(G_MENU));
	}
	else{
		G_MENU.gm_gmenu.addEventListener('mouseover',gm_gm_msover,true);
		
		G_MENU.gm_gmenu.addEventListener('mouseout',gm_gm_msout,false);
		
		$('gmenu_load').addEventListener('click',G_MENU.ongmload.bindAsEventListener(G_MENU),false);
		$('gmenu_hide').addEventListener('click',G_MENU.gmenuhide.bindAsEventListener(G_MENU),false);
	}


	
	$('gmenu_day_up').onclick = G_MENU.dayup.bind(G_MENU);
	$('gmenu_month_up').onclick = G_MENU.monthup.bind(G_MENU);
	$('gmenu_year_up').onclick = G_MENU.yearup.bind(G_MENU);
	
	$('gmenu_hour_up').onclick = G_MENU.hourup.bind(G_MENU);
	$('gmenu_minute_up').onclick = G_MENU.minuteup.bind(G_MENU);
	
	$('gmenu_period_v_up').onclick = G_MENU.pvalueup.bind(G_MENU);
	$('gmenu_period_t_up').onclick = G_MENU.ptypeup.bind(G_MENU);
	
	$('gmenu_day_down').onclick = G_MENU.daydown.bind(G_MENU);
	$('gmenu_month_down').onclick = G_MENU.monthdown.bind(G_MENU);
	$('gmenu_year_down').onclick = G_MENU.yeardown.bind(G_MENU);
	
	$('gmenu_hour_down').onclick = G_MENU.hourdown.bind(G_MENU);
	$('gmenu_minute_down').onclick = G_MENU.minutedown.bind(G_MENU);
	
	$('gmenu_period_v_down').onclick = G_MENU.pvaluedown.bind(G_MENU);
	$('gmenu_period_t_down').onclick = G_MENU.ptypedown.bind(G_MENU);
	
//	G_MENU.gmenushow();
//	cal.onselect = SCROLL_BAR.movebarbydate.bind(SCROLL_BAR);
}


var gmenu = Class.create();

gmenu.prototype = {


dt: new Date(),			//Date object on load time
cdt: new Date(),		//Date object of bstime

day: 1, 				//represents day number
month: 0,				//represents month number
year: 2007,				//represents year

hour: 12,				//hours
minute: 00,				//minutes

timestamp: 0,			//selected date in unix timestamp

period_min: 3600,		// Minimal period value (seconds)

period: 0,				//period in seconds
bstime: 0,				//graph starttime in seconds

gmenumsover: 0,			// if mouse is over gmenu(how to change it and use it is on your own)

gm_gmenu: null,			// represents HTML obj of gmenu

gm_value: null,			//html obj 
gm_type: null,			//html obj

gm_minute: null,		//html obj
gm_hour: null,			//html obj
gm_day: null,			//html obj
gm_month: null,			//html obj
gm_year: null,			//html obj

visible: 0,				//GMenu style state

monthname: new Array('January','February','March','April','May','June','July','August','September','October','November','December'), // months

period_value: 1,		// period value
period_type: 0,			// period value type
							
period_typename: new Array('Hours','Days','Weeks','Months','Years'),	//period value type 

initialize: function(period, bstime){
	this.bstime = parseInt(bstime);						// setting graph starttime
	
	if(this.bstime < 1000000000){
		this.bstime = parseInt(this.dt.getTime()/1000) - this.period;
	}

	this.cdt.setTime(this.bstime*1000);
},

ongmmsout: function(){
	if(this.msover == 0){
		this.gmenumouseout();
	}
},

gmenumouseout: function(){		// you may attach any functionto this
},

ongmload: function(e){
	if((typeof(e) != 'undefined')){
		if(IE){
			e.cancelBubble = true;
			e.returnValue = false;
		}
		else{
			e.stopPropagation();
			e.preventDefault();
		}
	}
	this.gmenuload();
},

gmenuload: function(){			// bind any func to this
},

gmenuhide: function(e){
	if((typeof(e) != 'undefined')){
		if(IE){
			e.cancelBubble = true;
			e.returnValue = false;
		}
		else{
			e.stopPropagation();
			e.preventDefault();
		}
	}
	
	if(!is_null(_PE_GM)){
		_PE_GM.stop();
		_PE_GM = null;
	}
	this.gm_gmenu.hide();
	this.visible = 0;
},

gmenushow: function(period, bstime){
	if(this.visible == 1){
		this.gmenuhide();
	}
	else{
		if((typeof(period) != 'undefined') && (typeof(bstime) != 'undefined')){
			this.initialize(period, bstime);
			
			this.syncBSDateByBSTime();
			this.calcPeriodAndTypeByUnix(period);
			this.setBSDate();
			this.setPeriod();
			this.setPeriodType();
		}
		
		this.gm_gmenu.show();
		this.visible = 1;
	}
},

minuteup: function(){
	var minuteinsec = 60;
	if((this.bstime+minuteinsec+this.period)>=parseInt(this.dt.getTime()/1000)){  // max date is date when script has been loaded
		if((this.period - 3600) < this.period_min) return;
		this.period-=3600;
		this.calcPeriodAndTypeByUnix(this.period)
//		return;
	}

	this.minute++;
	
	if(this.minute > 59){
		this.minute = 00;
		this.syncBSTime();
		
		this.hourup();
		return;
	}
	
	this.syncBSTime();
	this.setBSDate();
	
	this.setPeriod();
	this.setPeriodType();
},

hourup: function(){
	var hourinsec = 3600;

	if((this.bstime+hourinsec+1+this.period)>=parseInt(this.dt.getTime()/1000)){  // max date is date when script has been loaded
		if((this.period - hourinsec) < this.period_min){ 
			if(((this.dt.getTime()/1000) - (this.bstime+hourinsec)) < this.period_min) return;
			this.period = parseInt((this.dt.getTime()/1000) - (this.bstime+hourinsec));
		}
		else{
			this.period-=hourinsec;
		}
		this.calcPeriodAndTypeByUnix(this.period);
//		return;
	}

	this.hour++;
	
	if(this.hour > 23){
		this.hour = 00;
		this.syncBSTime();
		
		this.dayup();
		return;
	}
	
	this.syncBSTime();
	this.setBSDate();

	this.setPeriod();
	this.setPeriodType();
},

dayup: function(){
	var dayinsec = 86400;
	if((this.bstime+dayinsec+this.period)>=parseInt(this.dt.getTime()/1000)){  // max date is date when script has been loaded

		if((this.period - dayinsec) < this.period_min){ 
			if(((this.dt.getTime()/1000) - (this.bstime+dayinsec)) < this.period_min) return;
			this.period = parseInt((this.dt.getTime()/1000) - (this.bstime+dayinsec));
		}
		else{
			this.period-=dayinsec;
		}
		this.calcPeriodAndTypeByUnix(this.period);
//		return;
	}

	this.day++;
	
	if(this.day > this.daysInMonth(this.month,this.year)){
		this.day = 1;
		this.syncBSTime();
		
		this.monthup();
		return;
	}
	
	this.syncBSTime();
	this.setBSDate();
	
	this.setPeriod();
	this.setPeriodType();
},

monthup: function(){
	var monthinsec = (86400*this.daysInMonth(this.month,this.year));
	if((this.bstime+monthinsec+this.period)>=parseInt(this.dt.getTime()/1000)){  // max date is date when script has been loaded

		if((this.period - monthinsec) < this.period_min){ 
			if(((this.dt.getTime()/1000) - (this.bstime+monthinsec)) < this.period_min) return;
			this.period = parseInt((this.dt.getTime()/1000) - (this.bstime+monthinsec));
		}
		else{
			this.period-=monthinsec;
		}
		this.calcPeriodAndTypeByUnix(this.period);
//		return;
	}

	var monthlastday = (this.day == this.daysInMonth(this.month,this.year));
	this.month++;
	
	if(this.month > 11){
		this.month = 0;
		this.syncBSTime();

		this.yearup();
		
		if(monthlastday) this.day = this.daysInMonth(this.month,this.year);
		this.syncBSTime();
		
		return;
	}

	if(monthlastday) this.day = this.daysInMonth(this.month,this.year);
		
	this.syncBSTime();
	this.period = this.calcPeriod();
	this.setBSDate();
	

	this.setPeriod();
	this.setPeriodType();
},

yearup: function(){
	
	var yearinsec = this.calcPeriodIncByYear(1);

	if((this.bstime+yearinsec+this.period) >= parseInt(this.dt.getTime()/1000)){  // max date is date when script has been loaded

		if((this.period - yearinsec) < this.period_min) return;
		this.period-=yearinsec;
		this.calcPeriodAndTypeByUnix(this.period)
//		return;
	}

	this.year++;
	this.syncBSTime();
	this.period = this.calcPeriod();
	this.setBSDate();
	
	this.setPeriod();
	this.setPeriodType();
},

minutedown: function(){
	this.minute--;
	
	if(this.minute < 0){
		this.minute = 59;
		this.syncBSTime();
		
		this.hourdown();
		return;
	}
	
	this.syncBSTime();
	this.setBSDate();
},

hourdown: function(){
	this.hour--;
	
	if(this.hour < 0){
		this.hour = 23;
		this.syncBSTime();
		
		this.daydown();
		return;
	}
	
	this.syncBSTime();
	this.setBSDate();
},

daydown: function(){
	this.day--;
	
	if(this.day < 1){
		this.monthdown();
		
		this.day = this.daysInMonth(this.month,this.year)
		this.syncBSTime();
	}
	
	this.syncBSTime();
	this.setBSDate();
},

monthdown: function(){

	var monthlastday = (this.day == this.daysInMonth(this.month,this.year));
	this.month--;
	
	if(this.month < 0){
		this.month = 11;
		this.syncBSTime();
		
		this.yeardown();
		
		if(monthlastday) this.day = this.daysInMonth(this.month,this.year);
		this.syncBSTime();
		
		return;
	}
	
	if(monthlastday) this.day = this.daysInMonth(this.month,this.year);

	this.syncBSTime();
	this.period = this.calcPeriod();
	this.setBSDate();
},

yeardown: function(){
	
	if((this.year-1) < 1971){  // shouldn't be lower
		return ;
	}

	this.year--;
	this.syncBSTime();
	this.period = this.calcPeriod();
	this.setBSDate();	
},

pvalueup: function(){
	this.period_value++;
	var period = this.calcPeriod();
	if(period){
		this.period = period;
		this.setPeriod();
		
		this.syncBSDateByBSTime();
		this.setBSDate();
		return;
	}
	this.period_value--;
},

ptypeup: function(){
	if(typeof(this.period_typename[this.period_type+1]) == 'undefined'){
		return;
	}
	var temp_period_value = this.period_value;

	this.period_type++;
	this.period_value = 1;
	
	var period = this.calcPeriod();
	
	if(period){
		this.period = period;
		
		this.setPeriod();
		this.setPeriodType();
		
		this.syncBSDateByBSTime();
		this.setBSDate();

		return;
	}

	this.period_type--;
	this.period_value = temp_period_value;
},

pvaluedown: function(){
	if(this.period_value < 2){
		this.ptypedown();
		return;
	}
	this.period_value--;
	var period = this.calcPeriod();
	if(period){
		this.period = period;
		this.setPeriod();
		return;
	}
	this.period_value++;
},

ptypedown: function(){
	if(this.period_type < 1){
		return;
	}

	var temp_period_value = this.period_value;

	this.period_type--;
	this.period_value = 1;
	
	var period = this.calcPeriod();
	
	if(period){
		this.period = period;
		
		this.setPeriod();
		this.setPeriodType();
		return;
	}

	this.period_type++;
	this.period_value = temp_period_value;
},

calcPeriod: function(){
	var inc = 0;

	switch(this.period_type){
		case 1:
			inc = this.period_value * 86400;	//day
			break;
		case 2:
			inc = this.period_value * 604800;	//week
			break;
		case 3:
			inc = this.calcPeriodIncByMonth(this.period_value); //month
			break;
		case 4:
			inc = this.calcPeriodIncByYear(this.period_value); //years
			break;
		default:
			inc = this.period_value * 3600;			
	}

	if((this.bstime + inc) > parseInt(this.dt.getTime()/1000)){ 
		this.bstime = parseInt(this.dt.getTime()/1000) - inc;
		this.cdt.setTime(this.bstime*1000);
//		return false;
	}
	
return inc;
},

calcPeriodIncByMonth: function(pvalue){
	var inc = 0;
	var days = 0;
	var daysnext = 0;
	var month = this.month;
	var nextmonth = 0;
	var year = this.year;
	var nextyear = 0;
	var type_eval = 0;
	var monthlastday = null;

	for(var i=0; i < pvalue; i++){
		nextyear = year;
		nextmonth = month + 1;
		
		if(nextmonth > 11){ 
			nextyear=this.year+1;
			nextmonth = 0;
		}
		
		days = this.daysInMonth(month,year);
		monthlastday = (this.day == this.daysInMonth(month,year));
		
		if(monthlastday){
			days = this.daysInMonth(nextmonth,nextyear);;
		} 

		inc += 86400 * days;		//each month
		month = nextmonth;
		year = nextyear;
	}
return inc;
},

calcPeriodIncByYear: function(years){
	return this.calcPeriodIncByMonth(years*12);
},

setCDT: function(d,m,y,h,i){
	this.cdt.setMinutes(i);
	this.cdt.setHours(h);
	this.cdt.setDate(d);
	this.cdt.setMonth(m);
	this.cdt.setFullYear(y);
},

syncBSDateByBSTime: function(){
	this.minute = this.cdt.getMinutes();
	this.hour = this.cdt.getHours();
	this.day = this.cdt.getDate();
	this.month = this.cdt.getMonth();
	this.year = this.cdt.getFullYear();
},

syncBSTime: function(){
	this.setCDT(this.day,this.month,this.year,this.hour,this.minute);
	this.bstime = parseInt(this.cdt.getTime()/1000);
},

calcPeriodAndTypeByUnix: function(time){
	if((this.bstime+time) > parseInt(this.dt.getTime()/1000)){
		time = parseInt(this.dt.getTime()/1000) - this.bstime;
	}

	var years = parseInt(time / 22204800);
	var months = parseInt(time / (28*86400));
	var weeks = parseInt(time / 604800);
	var days = parseInt(time / 86400);
	var hours = parseInt(time / 3600);

	this.period_type = 0;

	if((time % 3600) == 0){	
		if(years>0){
			if(time == this.calcPeriodIncByYear(years)){
				this.period = time;
				this.period_value = years;
				this.period_type = 4;
				return;
			}
		}
	
		if(months>0){
			if(time == this.calcPeriodIncByMonth(months)){
				this.period = time;
				this.period_value = months;
				this.period_type = 3;
				return;
			}
		}
		
		if((weeks>0) && (time == (weeks*604800))){
			this.period = time;
			this.period_value = weeks;
			this.period_type = 2;
		}
		else if((days>0) && (time == (days*86400))){
			this.period = time;
			this.period_value = days;
			this.period_type = 1;
		}
		else if(hours>0){
			this.period = time;
			this.period_value = hours;
			this.period_type = 0;
		}
		else{
			this.period = this.period_min;
		}		
	return;
	}

	if(years>0){
		years++;
		this.period = this.calcPeriodIncByYear(years);
		this.period_value = years;
		this.period_type = 4;
	}
	else if(months>0){
		months++;
		this.period = this.calcPeriodIncByMonth(months);
		this.period_value = months;
		this.period_type = 3;
	}
	else if(weeks>0){
		weeks++;
		this.period = weeks * 604800;
		this.period_value = weeks;
		this.period_type = 2;
	}
	else if(days>0){
		days++;
		this.period = days * 86400;
		this.period_value = days;
		this.period_type = 1;
	}
	else if(hours>0){
		hours++;
		this.period_value = hours;
		this.period = hours * 3600;	
	}
	else{
		this.period = this.period_min;
	}
},

setPeriodType: function(){
	if(IE){
		this.gm_type.innerText = this.period_typename[this.period_type];
	}
	else{
		this.gm_type.textContent = this.period_typename[this.period_type];
	}
},

setPeriod: function(){
	if(IE){
		this.gm_value.innerText = this.period_value;
	}
	else{
		this.gm_value.textContent = this.period_value;
	}
},

setBSDate: function(){
	if(IE){
		this.gm_minute.innerText = this.minute;
		this.gm_hour.innerText = this.hour;
		this.gm_day.innerText = this.day;
		this.gm_month.innerText = this.monthname[this.month];
		this.gm_year.innerText = this.year;		
	}
	else{
		this.gm_minute.textContent = this.minute;
		this.gm_hour.textContent = this.hour;
		this.gm_day.textContent = this.day;
		this.gm_month.textContent = this.monthname[this.month];
		this.gm_year.textContent = this.year;
	}
},

daysInFeb: function(year){
	// February has 29 days in any year evenly divisible by four,
    // EXCEPT for centurial years which are not also divisible by 400.
    return (((year % 4 == 0) && ( (!(year % 100 == 0)) || (year % 400 == 0))) ? 29 : 28 );
},

daysInMonth: function(m,y){
	m++;
	var days = 31;
	if (m==4 || m==6 || m==9 || m==11){
		days = 30;
	}
	else if(m==2){
		days = this.daysInFeb(y);
	}
	
return days;
}
}


/*-------------------------------------------------------------------------------------------------*\
*										GMENU CREATION												*
\*-------------------------------------------------------------------------------------------------*/
function gmenucreate(top,left){
	
	var div_gmenu = document.createElement('div');
	document.getElementsByTagName('body')[0].appendChild(div_gmenu);
	
	Element.extend(div_gmenu);
	div_gmenu.setAttribute('id','gmenu');
	div_gmenu.setStyle({top: top+'px', left: left+'px',display: 'none'});
	
////////////////////////////////////////////// BSDATE

	var div_bsdate = document.createElement('div');
	div_gmenu.appendChild(div_bsdate);
	
	div_bsdate.setAttribute('id','gmenu_bsdate');
	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_day_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_month_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_year_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
	
	var span = document.createElement('span');
	div_bsdate.appendChild(span);
	
	span.setAttribute('id','gmenu_day')
	span.appendChild(document.createTextNode('1'));
	
	var span = document.createElement('span');
	div_bsdate.appendChild(span);
	
	span.setAttribute('id','gmenu_month')
	span.appendChild(document.createTextNode('January'));
	
	var span = document.createElement('span');
	div_bsdate.appendChild(span);
	
	span.setAttribute('id','gmenu_year')
	span.appendChild(document.createTextNode('2007'));
	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_day_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');

	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_month_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');

	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_year_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');

/////////////////////////////////////// HOUR:MINUTE

	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_hour_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
	
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_minute_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
		
	var span = document.createElement('span');
	div_bsdate.appendChild(span);
	
	span.setAttribute('id','gmenu_hour')
	span.appendChild(document.createTextNode('12'));
	
	var span = document.createElement('span');
	div_bsdate.appendChild(span);
	
	span.setAttribute('id','gmenu_minute')
	span.appendChild(document.createTextNode('00'));
		
	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_hour_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');

	var img = document.createElement('img');
	div_bsdate.appendChild(img);
	
	img.setAttribute('id','gmenu_minute_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');

//////////////////////////////////////////////////////// PERIOD 

	var div_period = document.createElement('div');
	div_gmenu.appendChild(div_period);
	
	div_period.setAttribute('id','gmenu_period');
	
	var img = document.createElement('img');
	div_period.appendChild(img);
	
	img.setAttribute('id','gmenu_period_v_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
	
	var img = document.createElement('img');
	div_period.appendChild(img);
	
	img.setAttribute('id','gmenu_period_t_up');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_up.gif');
	img.setAttribute('alt','^');
		
	var span = document.createElement('span');
	div_period.appendChild(span);
	
	span.setAttribute('id','gmenu_period_desc')
	span.appendChild(document.createTextNode('Period'));
	
	var span = document.createElement('span');
	div_period.appendChild(span);
	
	span.setAttribute('id','gmenu_period_value')
	span.appendChild(document.createTextNode('1'));
	
	var span = document.createElement('span');
	div_period.appendChild(span);
	
	span.setAttribute('id','gmenu_period_type')
	span.appendChild(document.createTextNode('Hours'));
		
	var img = document.createElement('img');
	div_period.appendChild(img);
	
	img.setAttribute('id','gmenu_period_v_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');

	var img = document.createElement('img');
	div_period.appendChild(img);
	
	img.setAttribute('id','gmenu_period_t_down');
	img.setAttribute('src',GMENU_IMG_PATH+'/arrow_down.gif');
	img.setAttribute('alt','v');
	
///////////////////////////////////////////////////// CONTROL BUTTONS

	var link_load = document.createElement('a');
	div_gmenu.appendChild(link_load);
	
	link_load.setAttribute('id','gmenu_load');
	link_load.setAttribute('href',location.href);
	link_load.appendChild(document.createTextNode('OK'));
	
	var link_hide = document.createElement('a');
	div_gmenu.appendChild(link_hide);
	
	link_hide.setAttribute('id','gmenu_hide');
	link_hide.setAttribute('href',location.href);
	
	link_hide.appendChild(document.createTextNode('Back'));

/*
<div id="gmenu" style="top: 220px; left:10px;">
    <div id="gmenu_bsdate">
        <img id="gmenu_day_up" src="images/general/bar/arrow_up.gif" alt="^" />
        <img id="gmenu_month_up" src="images/general/bar/arrow_up.gif" alt="^" />
        <img id="gmenu_year_up" src="images/general/bar/arrow_up.gif" alt="^" />
    
        <span id="gmenu_day">20</span><span id="gmenu_month">January</span><span id="gmenu_year">2007</span>
    
        <img id="gmenu_day_down" src="images/general/bar/arrow_down.gif" alt="v" />
        <img id="gmenu_month_down" src="images/general/bar/arrow_down.gif" alt="v" />
        <img id="gmenu_year_down" src="images/general/bar/arrow_down.gif" alt="v" />

        <img id="gmenu_hour_up" src="images/general/bar/arrow_up.gif" alt="^" />
        <img id="gmenu_minute_up" src="images/general/bar/arrow_up.gif" alt="^" />
    
        <span id="gmenu_hour">12</span><span id="gmenu_minute">00</span>
    
        <img id="gmenu_hour_down" src="images/general/bar/arrow_down.gif" alt="v" />
        <img id="gmenu_minute_down" src="images/general/bar/arrow_down.gif" alt="v" />
    </div>
	<div id="gmenu_period">
        <img id="gmenu_period_v_up" src="images/general/bar/arrow_up.gif" alt="^" />
        <img id="gmenu_period_t_up" src="images/general/bar/arrow_up.gif" alt="^" />
    
        <span id="gmenu_period_desc">Period</span><span id="gmenu_period_value">10</span><span id="gmenu_period_type">Months</span>
    
        <img id="gmenu_period_v_down" src="images/general/bar/arrow_down.gif" alt="v" />
        <img id="gmenu_period_t_down" src="images/general/bar/arrow_down.gif" alt="v" />
    </div>
    <a href="javascript: return false;" id="gmenu_load">OK</a>
    <a href="#1" id="gmenu_hide">Back</a>
</div>
//*/
}