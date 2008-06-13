// JavaScript Document
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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

// Title: calendar
// Author: Aly

<!--

var CLNDR = new Array();			// calendar obj reference

function create_calendar(time, objects, id){
	id = id || CLNDR.length;
	
	CLNDR[id] = new Object;
	CLNDR[id].clndr = new calendar(time, objects);
}

var calendar = Class.create();

calendar.prototype = {
dt: new Date(),			//Date object on load time
cdt: new Date(),		//Date object of current(viewed) date
sdt: new Date(),		//Date object of a selected date

//day: 1, 				//represents day number
month: 0,				//represents month number
year: 2008,				//represents year
day: 1,					//represents days
hour: 12,				//hours
minute: 00,				//minutes

timestamp: 0,			//selected date in unix timestamp

clndr_calendar: null,		//html obj of calendar

clndr_minute: null,			//html from obj
clndr_hour: null,			//html from obj
clndr_days: null,			//html obj
clndr_month: null,			//html obj
clndr_year: null,			//html obj

clndr_selectedday: null,	//html obj, selected day

clndr_monthup: null,			//html bttn obj
clndr_monthdown: null,			//html bttn obj
clndr_yearup: null,				//html bttn obj
clndr_yeardown: null,			//html bttn obj

objects: new Array(),			// object list where will be saved date
status: false,					// status of objects

visible: 0,				//GMenu style state

monthname: new Array('January','February','March','April','May','June','July','August','September','October','November','December'), // months

initialize: function(stime, objects){
	
	if(!(this.status=this.checkOuterObj(objects))){
		throw 'Calendar: constructor expects second parameter to be list of DOM nodes [d,M,Y,hh,i].';
		return false;
	}
	
	this.calendarcreate();
	
	addListener(this.clndr_monthdown,'click',this.monthdown.bindAsEventListener(this));
	addListener(this.clndr_monthup,'click',this.monthup.bindAsEventListener(this));
	
	addListener(this.clndr_yeardown,'click',this.yeardown.bindAsEventListener(this));
	addListener(this.clndr_yearup,'click',this.yearup.bindAsEventListener(this));	
	
	addListener(this.clndr_hour,'blur',this.sethour.bindAsEventListener(this));	
	addListener(this.clndr_minute,'blur',this.setminute.bindAsEventListener(this));		

	if(('undefined' != typeof(stime)) && !empty(stime)){
		this.sdt.setTime(stime*1000);
	}
	else{
		this.setSDateFromOuterObj();
	}
	
	this.cdt.setTime(this.sdt.getTime());
	this.cdt.setDate(1);
	
	this.syncBSDateBySDT();
	this.setCDate();
},

ondateselected: function(){		// place any function;
	this.setDateToOuterObj();
	this.clndrhide();
},

clndrhide: function(e){
	if((typeof(e) != 'undefined')){
		cancelEvent(e);
	}
	
	this.clndr_calendar.hide();
	this.visible = 0;
},

clndrshow: function(top,left){
	if(this.visible == 1){
		this.clndrhide();
	}
	else{
		if(this.status){
			this.setSDateFromOuterObj();		

			this.cdt.setTime(this.sdt.getTime());
			this.cdt.setDate(1);

			this.syncBSDateBySDT();
			this.setCDate();
		}
		
		if(('undefined' != typeof(top)) && ('undefined' != typeof(left))){
			this.clndr_calendar.style.top = top + 'px';
			this.clndr_calendar.style.left = left + 'px';
		}
		
		this.clndr_calendar.show();
		this.visible = 1;
	}
},

checkOuterObj: function(objects){
	if(('undefined' != typeof(objects)) && !empty(objects)){
		if(is_array(objects)){
			this.objects = objects;			
		}
		else{
			this.objects.push(objects);
		}
	}
	else{
		return false;
	}
	
	for(var i=0; i < this.objects.length; i++){
		if(('undefined' != this.objects[i]) && !empty(this.objects[i])){
			this.objects[i] = $(this.objects[i]);

			if(empty(this.objects[i]))
				return false;
		}
	}
return true;
},

setSDateFromOuterObj: function(){
	switch(this.objects.length){
		case 1:
			var val = null;
			var result = false;
			
			if(this.objects[0].tagName.toLowerCase() == 'input'){
				val = this.objects[0].value;
			}
			else{
				val = (IE)?this.objects[0].innerText:this.objects[0].textContent;
			}

			if(is_string(val)){
				val = val.split('/');
				if(val.length == 3){
					result = this.setSDateDMY(val[0],val[1],val[2]);
				}
			}
			
			if(!result){
				return false;
			}
			
			break;
		case 3:
		case 5:
			var val = new Array();
			var result = true;
			
			for(var i=0; i < this.objects.length; i++){
				if(('undefined' != this.objects[i]) && !empty(this.objects[i])){
					if(this.objects[i].tagName.toLowerCase() == 'input'){
						val[i] = this.objects[i].value;
					}
					else{
						val[i] = (IE)?this.objects[i].innerText:this.objects[i].textContent;
					}
				}
				else{
					result = false;
				}
			}
			
			if(result){
				result = this.setSDateDMY(val[0],val[1],val[2]);

				if(val.length>4){
					val[3] = parseInt(val[3]);
					val[4] = parseInt(val[4]);
				
					if((val[3] > -1) && (val[3] < 24)){
						this.sdt.setHours(val[3]);
						result = true;
					}

					if((val[4] > -1) && (val[4] < 60)){
						this.sdt.setMinutes(val[4]);
						result = true;
					}

				}
			}
			
			if(result){
				return true;
			}
			else{
				return false;
			}
			break;
		default:
			return false;
			break;
	}
},

setSDateDMY: function(d,m,y){
	d = parseInt(d);
	m = parseInt(m);
	y = parseInt(y);

	var result = false;
	if((m > 0) && (m < 13)){
		this.sdt.setMonth(m-1);
		result = true;
	}

	if((y > 71) && (y < 1971)){
		this.sdt.setYear(y);
		result = true;
	}
	
	if((y > 1971) && (y < 10000)){
		this.sdt.setFullYear(y);
		result = true;
	}

	if((d>-1) && (d<29)){
		this.sdt.setDate(d);
		result = true;
	}
	else if((d>28) && result){
		if(d <= this.daysInMonth(this.sdt.getMonth(), this.sdt.getYear())){
			this.sdt.setDate(d);
			result = true;
		}
	}
																							  
//alert(d+'/'+m+'/'+y+'/'+result);
//alert(this.sdt.getDate()+'/'+this.sdt.getMonth()+'/'+this.sdt.getFullYear()+'/'+result);
return result;
},

setDateToOuterObj: function(){
	switch(this.objects.length){
		case 1:
						
			if(this.objects[0].tagName.toLowerCase() == 'input'){
				this.objects[0].value = this.sdt.getDate()+'/'+(this.sdt.getMonth()+1)+'/'+this.sdt.getFullYear();
			}
			else{
				if(IE)
					this.objects[0].innerText =  this.sdt.getDate()+'/'+(this.sdt.getMonth()+1)+'/'+this.sdt.getFullYear();
				else
					this.objects[0].textContent = this.sdt.getDate()+'/'+(this.sdt.getMonth()+1)+'/'+this.sdt.getFullYear();
			}
			break;
		case 3:
		case 5:
// Day		
			if(this.objects[0].tagName.toLowerCase() == 'input'){
				this.objects[0].value = this.sdt.getDate();
			}
			else{
				if(IE)
					this.objects[0].innerText = this.sdt.getDate();
				else
					this.objects[0].textContent = this.sdt.getDate();
			}
// Month	
			if(this.objects[1].tagName.toLowerCase() == 'input'){
				this.objects[1].value = this.sdt.getMonth()+1;
			}
			else{
				if(IE)
					this.objects[1].innerText = this.sdt.getMonth()+1;
				else
					this.objects[1].textContent = this.sdt.getMonth()+1;
			}
// Year
			if(this.objects[2].tagName.toLowerCase() == 'input'){
				this.objects[2].value = this.sdt.getFullYear();
			}
			else{
				if(IE)
					this.objects[2].innerText = this.sdt.getFullYear();
				else
					this.objects[2].textContent = this.sdt.getFullYear();
			}
			
			if(this.objects.length > 4){
// Hour
				if(this.objects[3].tagName.toLowerCase() == 'input'){
					this.objects[3].value = this.sdt.getHours();
				}
				else{
					if(IE)
						this.objects[3].innerText = this.sdt.getHours();
					else
						this.objects[3].textContent = this.sdt.getHours();
				}
// Minute		
				if(this.objects[4].tagName.toLowerCase() == 'input'){
					this.objects[4].value = this.sdt.getMinutes();
				}
				else{
					if(IE)
						this.objects[4].innerText = this.sdt.getMinutes();
					else
						this.objects[4].textContent = this.sdt.getMinutes();
				}
			}				
			break;
	}
},

setminute: function(){
	var minute = parseInt(this.clndr_minute.value);
	if((minute>-1) && (minute < 60)){
		this.minute = minute;
		this.syncSDT();
	}
	else{
		this.clndr_minute.value = this.minute;
	}
},

sethour: function(){
	var hour = parseInt(this.clndr_hour.value);
	if((hour>-1) && (hour < 24)){
		this.hour = hour;
		this.syncSDT();
	}
	else{
		this.clndr_hour.value = this.hour;
	}
},

setday: function(e,day,month,year){
	
	if(!is_null(this.clndr_selectedday)){
		this.clndr_selectedday.removeClassName('selected');
	}
		
	this.setSDT(day,month,year,this.hour,this.minute);
	
	var selectedday = Event.element(e);
	Element.extend(selectedday);
	
	this.clndr_selectedday = selectedday;
	this.clndr_selectedday.addClassName('selected');
	
	this.ondateselected();
},

monthup: function(){
//	var monthlastday = (this.day == this.daysInMonth(this.month,this.year));
	this.month++;
	
	if(this.month > 11){
		this.month = 0;
		this.yearup();		
	}
	else{
		this.syncCDT();
		this.setCDate();
	}
},

monthdown: function(){

	this.month--;
	
	if(this.month < 0){
		this.month = 11;
		this.yeardown();		
	}
	else{
		this.syncCDT();
		this.setCDate();
	}
},

yearup: function(){
	
	this.year++;
	
	this.syncCDT();	
	this.setCDate();	
},

yeardown: function(){
	
	if((this.year-1) < 1971){  // shouldn't be lower
		return ;
	}

	this.year--;
	this.syncCDT();
	this.setCDate();	
},

setSDT: function(d,m,y,h,i){
	this.sdt.setMinutes(i);
	this.sdt.setHours(h);
	this.sdt.setDate(d);
	this.sdt.setMonth(m);
	this.sdt.setFullYear(y);
},

setCDT: function(d,m,y,h,i){
	this.cdt.setMinutes(i);
	this.cdt.setHours(h);
	this.cdt.setDate(d);
	this.cdt.setMonth(m);
	this.cdt.setFullYear(y);
},

syncBSDateBySDT: function(){
	this.minute = this.sdt.getMinutes();
	this.hour = this.sdt.getHours();
	this.day = this.sdt.getDate();
	this.month = this.sdt.getMonth();
	this.year = this.sdt.getFullYear();
},

syncSDT: function(){
	this.setSDT(this.day,this.month,this.year,this.hour,this.minute);
},

syncCDT: function(){
	this.setCDT(1,this.month,this.year,this.hour,this.minute);
},

setCDate: function(){
	
	this.clndr_minute.value = this.minute;
	this.clndr_hour.value = this.hour;

	if(IE){
		this.clndr_month.innerText = this.monthname[this.month];
		this.clndr_year.innerText = this.year;		
	}
	else{
		this.clndr_month.textContent = this.monthname[this.month];
		this.clndr_year.textContent = this.year;
	}
	
	this.createDaysTab();
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
},

// CALENDAR DAYS TAB

createDaysTab: function(){

	this.clndr_days.update('');
	
	var table = document.createElement('table');
	this.clndr_days.appendChild(table);
	
	table.setAttribute('cellpadding','1');
	table.setAttribute('cellspacing','1');
	table.setAttribute('width','100%');
	table.className = 'calendartab';

	var tbody = document.createElement('tbody');
	table.appendChild(tbody);
	
	var cur_month = this.cdt.getMonth();
	
// make 0 - monday, not sunday(as default)
	var prev_days = this.cdt.getDay() - 1;
	if(prev_days < 0) prev_days = 6;
	
	if(prev_days > 0){
		this.cdt.setTime(this.cdt.getTime() - (prev_days*86400000));
	}	

	for(var y=0; y < 6; y++){
		
		var tr = document.createElement('tr');
		tbody.appendChild(tr);
						
		for(var x=0; x < 7; x++){

			var td = document.createElement('td');
			tr.appendChild(td);

			Element.extend(td);
			
			if(x > 4) td.className = 'hollyday';
			if(cur_month != this.cdt.getMonth()){
				td.addClassName('grey');
			}
			
			if( (this.sdt.getFullYear() == this.cdt.getFullYear()) &&
				(this.sdt.getMonth() == this.cdt.getMonth()) &&
				(this.sdt.getDate() == this.cdt.getDate()))
			{
				td.addClassName('selected');
				this.clndr_selectedday = td;
			}
			
			addListener(td,'click',this.setday.bindAsEventListener(this,this.cdt.getDate(),this.cdt.getMonth(),this.cdt.getFullYear()));

			td.appendChild(document.createTextNode(this.cdt.getDate()));
			
			this.cdt.setTime(this.cdt.getTime() + (86400000));	// + 1day
		}
	}
},

/*-------------------------------------------------------------------------------------------------*\
*										CALENDAR CREATION											*
\*-------------------------------------------------------------------------------------------------*/
calendarcreate: function(){
		this.clndr_calendar = document.createElement('div');
		
		Element.extend(this.clndr_calendar);
		this.clndr_calendar.className = 'calendar';
		this.clndr_calendar.hide();
		
	document.body.appendChild(this.clndr_calendar);
		
		addListener(this.clndr_calendar,'mousemove', deselectAll);
	
	//*********** CALENDAR HAT ****************************** 
		var line_div = document.createElement('div');
	this.clndr_calendar.appendChild(line_div);
				
		var table = document.createElement('table');
	line_div.appendChild(table);
	
//		table.setAttribute('border','1');
		table.setAttribute('cellpadding','2');
		table.setAttribute('cellspacing','0');
		table.setAttribute('width','100%');
		table.className = 'calendarhat';
	//  YEAR
	
		var tbody = document.createElement('tbody');
	table.appendChild(tbody);
	
		var tr = document.createElement('tr');
	tbody.appendChild(tr);
		
		var td = document.createElement('td');
	tr.appendChild(td);
	
		this.clndr_yeardown = document.createElement('span');
	td.appendChild(this.clndr_yeardown);
		
		this.clndr_yeardown.className = 'clndr_left_arrow';
		
		this.clndr_yeardown.appendChild(document.createTextNode('«'));
	
		var td = document.createElement('td');
	tr.appendChild(td);
	
		td.className = 'long';
		
		this.clndr_year = document.createElement('span');
	td.appendChild(this.clndr_year);
		
		this.clndr_year.className = 'title';
		this.clndr_year.appendChild(document.createTextNode('2008'));
	
		var td = document.createElement('td');
	tr.appendChild(td);
	
		this.clndr_yearup = document.createElement('span');
	td.appendChild(this.clndr_yearup);
	
		this.clndr_yearup.className = 'clndr_right_arrow';
	
		this.clndr_yearup.appendChild(document.createTextNode('»'));
	
	// MONTH
	
		var tr = document.createElement('tr');
	tbody.appendChild(tr);
		
		var td = document.createElement('td');
	tr.appendChild(td);
	
		this.clndr_monthdown = document.createElement('span');
	td.appendChild(this.clndr_monthdown);
		
		this.clndr_monthdown.className = 'clndr_left_arrow';
		
		this.clndr_monthdown.appendChild(document.createTextNode('«'));
	
		var td = document.createElement('td');
	tr.appendChild(td);
	
		td.className = 'long';
	
		this.clndr_month = document.createElement('span');
	td.appendChild(this.clndr_month);
		
		this.clndr_month.className = 'title';
		this.clndr_month.appendChild(document.createTextNode('March'));
	
		var td = document.createElement('td');
	tr.appendChild(td);
	
		this.clndr_monthup = document.createElement('span');
	td.appendChild(this.clndr_monthup);
		
		this.clndr_monthup.className = 'clndr_right_arrow';
		
		this.clndr_monthup.appendChild(document.createTextNode('»'));
	
	//	DAYS heading
		var table = document.createElement('table');
	line_div.appendChild(table);
	
		table.setAttribute('cellpadding','2');
		table.setAttribute('cellspacing','0');
		table.setAttribute('width','100%');
		table.className = 'calendarhat';
	
		var tbody = document.createElement('tbody');
	table.appendChild(tbody);
	
		var tr = document.createElement('tr');
	tbody.appendChild(tr);
	
		tr.className='header';
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('M'));
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('T'));
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('W'));
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('T'));
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('F'));
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('S'));
		
		var td = document.createElement('td');
	tr.appendChild(td);
		td.appendChild(document.createTextNode('S'));
	//******************************************************
	
	//******** DAYS CALENDAR *************
		this.clndr_days = document.createElement('div');
		Element.extend(this.clndr_days);
		
	this.clndr_calendar.appendChild(this.clndr_days);
		
		this.clndr_days.className = 'calendardays';	

	//************************************************
	
	// TIME INPUT
	
		var line_div = document.createElement('div');
	this.clndr_calendar.appendChild(line_div);

		line_div.className = 'calendartime';
		
		this.clndr_hour = document.createElement('input');	
	line_div.appendChild(this.clndr_hour);
	
		this.clndr_hour.setAttribute('type','text');
		this.clndr_hour.setAttribute('name','hour');
		this.clndr_hour.setAttribute('value','hh');
		this.clndr_hour.setAttribute('maxlength','2');
		this.clndr_hour.className = 'calendar_textbox';
	
	line_div.appendChild(document.createTextNode(' : '));
		
		this.clndr_minute = document.createElement('input');	
	line_div.appendChild(this.clndr_minute);
	
		this.clndr_minute.setAttribute('type','text');
		this.clndr_minute.setAttribute('name','minute');
		this.clndr_minute.setAttribute('value','mm');
		this.clndr_minute.setAttribute('maxlength','2');
		this.clndr_minute.className = 'calendar_textbox';
}
}


/*
<!--
<div class="calendar" onmousemove="javascript: deselectAll();">
<div>
	<table border="0" cellpadding="2" cellspacing="0" class="calendarhat" width="100%">
	<tbody>
		<tr>
			<td><span>&laquo;</span></td>
			<td width="90%"><span class="title">2008</span></td>
			<td><span>&raquo;</span></td>
		</tr>
		<tr>
			<td><span>&laquo;</span></td>
			<td><span class="title">September</span></td>
			<td><span>&raquo;</span></td>
		</tr>
	</tbody>
	</table>
	
	<table border="0" cellpadding="2" cellspacing="0" class="calendarhat" width="100%">
	<tbody>
		<tr class="header">
			<td>M</td>
			<td>T</td>
			<td>W</td>
			<td>T</td>
			<td>F</td>
			<td>S</td>
			<td>S</td>
		</tr>
	</tbody>
	</table>
</div>
<div class="calendardays">
<table border="0" cellpadding="1" cellspacing="1" class="calendartab">
<tbody>
<tr>
	<td class="grey">30</td>
	<td>1</td>
	<td>2</td>
	<td>3</td>
	<td>4</td>
	<td class="hollyday">5</td>
	<td class="hollyday">6</td>
</tr>
</tbody>
</table>
</div>
<div class="calendartime">
	<input type="text" name="hour" value="hh" maxlength="2" class="calendar_textbox" /> : 
	<input type="text" name="minute" value="mm" maxlength="2" class="calendar_textbox" />
</div>
</div>
-->
*/