//Javascript document
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
**/

/************************************************************************************/
/*								PAGE REFRESH										*/
/************************************************************************************/
// Author: Aly
var PageRefresh = {
delay:			null,	// refresh timeout
delayLeft:		null,	// left till refresh
timeout:		null,	// link to timeout

init: function(time){
	this.delay = time;
	this.delayLeft = this.delay;
	this.start();
},

check: function(){
	if(is_null(this.delay)) return false;

	this.delayLeft -= 1000;
	if(this.delayLeft < 0)
		location.reload();
	else
		this.timeout = setTimeout('PageRefresh.check()', 1000);
},

start: function(){
	if(is_null(this.delay)) return false;

	this.timeout = setTimeout('PageRefresh.check()', 1000);
},

restart: function(){
	this.stop();
	this.delayLeft = this.delay;
	this.start();
},

stop: function(){
	clearTimeout(this.timeout);
},

restart: function(){
	this.stop();
	this.delayLeft = this.delay;
	this.start();
}
}

/************************************************************************************/
/*								MAIN MENU stuff										*/
/************************************************************************************/
// Author: Aly

var MMenu = {
menus:			{'empty': 0, 'view': 0, 'cm': 0, 'reports': 0, 'config': 0, 'admin': 0},
def_label:		null,
sub_active: 	false,
timeout_reset:	null,
timeout_change:	null,

mouseOver: function(show_label){
	clearTimeout(this.timeout_reset);
	this.timeout_change = setTimeout('MMenu.showSubMenu("'+show_label+'")', 200);
	PageRefresh.restart();
},

submenu_mouseOver: function(){
	clearTimeout(this.timeout_reset);
	clearTimeout(this.timeout_change);
	PageRefresh.restart();
},

mouseOut: function(){
	clearTimeout(this.timeout_change);
	this.timeout_reset = setTimeout('MMenu.showSubMenu("'+this.def_label+'")', 2500);
},

showSubMenu: function(show_label){
	var menu_div = $('sub_' + show_label);
	if (!is_null(menu_div)) {
		$(show_label).className = 'active';
		menu_div.show();
		for (var key in this.menus) {
			if (key == show_label) {
				continue;
			}
			var menu_cell = $(key);
			if (!is_null(menu_cell)) {
				if (menu_cell.tagName.toLowerCase() != 'select') {
					menu_cell.className = '';
				}
			}
			var sub_menu_cell = $('sub_' + key);
			if (!is_null(sub_menu_cell)) {
				sub_menu_cell.hide();
			}
		}
	}
}
};


/************************************************************************************/
/*						Automatic checkbox range selection 							*/
/************************************************************************************/
// Author: Aly

var chkbxRange = {
startbox:			null,			// start checkbox obj
startbox_name: 		null,			// start checkbox name
chkboxes:			{},				// ckbx list
pageGoName:			null,			// wich checkboxes should be counted by Go button
pageGoCount:		0,				// selected checkboxes
selected_ids:		{},	// ids of selected checkboxes
goButton:			null,
page:				null,			// loaded page name

init: function(){

	this.goButton = $('goButton');
	var path = new Curl();
	this.page = path.getPath();

	this.selected_ids = cookie.readJSON('cb_'+this.page);

	var chk_bx = document.getElementsByTagName('input');

	for(var i=0; i < chk_bx.length; i++){
		if((typeof(chk_bx[i]) != 'undefined') && (chk_bx[i].type.toLowerCase() == 'checkbox')){
			this.implement(chk_bx[i]);
		}
	}

	if(!is_null(this.goButton))
		addListener(this.goButton, 'click', this.submitGo.bindAsEventListener(this), false);

	this.setGo();
},

implement: function(obj){
	var obj_name = obj.name.split('[')[0];

	if(typeof(this.chkboxes[obj_name]) == 'undefined') this.chkboxes[obj_name] = new Array();
	this.chkboxes[obj_name].push(obj);

	addListener(obj, 'click', this.check.bindAsEventListener(this), false);

	if(obj_name == this.pageGoName){
		var obj_id  = obj.name.split('[')[1];
		obj_id = obj_id.substring(0, obj_id.lastIndexOf(']'));

		if(isset(obj_id, this.selected_ids)){
//SDI(obj_id);
			obj.checked = true;
		}
	}
},

check: function(e){
	var e = e || window.event;
	var obj = Event.element(e);

	PageRefresh.restart();

	if((typeof(obj) == 'undefined') || (obj.type.toLowerCase() != 'checkbox')){
		return true;
	}

	this.setGo();

	if(!(e.ctrlKey || e.shiftKey)) return true;
	if(obj.name.indexOf('all_') > -1) return true;
	if(obj.name.indexOf('_single') > -1) return true;


	var obj_name = obj.name.split('[')[0];

	if(!is_null(this.startbox) && (this.startbox_name == obj_name) && (obj.name != this.startbox.name)){
		var chkbx_list = this.chkboxes[obj_name];
		var flag = false;

		for(var i=0; i < chkbx_list.length; i++){
			if(typeof(chkbx_list[i]) !='undefined'){
//alert(obj.name+' == '+chkbx_list[i].name);
				if(flag){
					chkbx_list[i].checked = this.startbox.checked;
				}

				if(obj.name == chkbx_list[i].name) break;
				if(this.startbox.name == chkbx_list[i].name) flag = true;
			}
		}

		if(flag){
			this.startbox = null;
			this.startbox_name = null;

			this.setGo();
			return true;
		}
		else{
			for(var i=chkbx_list.length-1; i >= 0; i--){
				if(typeof(chkbx_list[i]) !='undefined'){
//alert(obj.name+' == '+chkbx_list[i].name);
					if(flag){
						chkbx_list[i].checked = this.startbox.checked;
					}

					if(obj.name == chkbx_list[i].name){
						this.startbox = null;
						this.startbox_name = null;

						this.setGo();
						return true;
					}

					if(this.startbox.name == chkbx_list[i].name) flag = true;
				}
			}
		}

	}
	else{
		if(!is_null(this.startbox)) this.startbox.checked = this.startbox.checked?false:true;

		this.startbox = obj;
		this.startbox_name = obj_name;
	}

	this.setGo();
},

checkAll: function(name, value){
	if(typeof(this.chkboxes[name]) == 'undefined') return false;

	var chk_bx = this.chkboxes[name];
	for(var i=0; i < chk_bx.length; i++){
		if((typeof(chk_bx[i]) !='undefined') && (chk_bx[i].disabled != true)){
			var obj_name = chk_bx[i].name.split('[')[0];

			if(obj_name == name){
				chk_bx[i].checked = value;
			}

		}
	}
},

setGo: function(){
	if(!is_null(this.pageGoName)){

		if(typeof(this.chkboxes[this.pageGoName]) == 'undefined'){
//			alert('CheckBoxes with name '+this.pageGoName+' doesn\'t exist');
			return false;
		}

		var chk_bx = this.chkboxes[this.pageGoName];
		for(var i=0; i < chk_bx.length; i++){
			if(typeof(chk_bx[i]) !='undefined'){
				var box = chk_bx[i];

				var obj_name = box.name.split('[')[0];
				var obj_id  = box.name.split('[')[1];
				obj_id = obj_id.substring(0, obj_id.lastIndexOf(']'));

				var crow = getParent(box,'tr');

				if(box.checked){
					if(!is_null(crow)){
						var origClass = crow.getAttribute('origClass');
						if(is_null(origClass))
							crow.setAttribute('origClass',crow.className);

						crow.className = 'selected';
					}

					if(obj_name == this.pageGoName){
						this.selected_ids[obj_id] = obj_id;
					}
				}
				else{
					if(!is_null(crow)){
						var origClass = crow.getAttribute('origClass');

						if(!is_null(origClass)){
							crow.className = origClass;
							crow.removeAttribute('origClass');
						}
					}

					if(obj_name == this.pageGoName){
						delete(this.selected_ids[obj_id]);
					}
				}

			}
		}

		var countChecked = 0;
		for(var key in this.selected_ids){
			if(!empty(this.selected_ids[key]))
				countChecked++;
		}

		if(!is_null(this.goButton)){
			var tmp_val = this.goButton.value.split(' ');
			this.goButton.value = tmp_val[0]+' ('+countChecked+')';
		}

		cookie.createJSON('cb_'+this.page, this.selected_ids);

		this.pageGoCount = countChecked;
	}
	else{
//		alert('Not isset pageGoName')
	}
},

submitGo: function(e){
	var e = e || window.event;

	if(this.pageGoCount > 0){
		var goSelect = $('go');
		var confirmText = goSelect.options[goSelect.selectedIndex].getAttribute('confirm');

		if(is_null(confirmText) || !confirmText){
//			confirmText = 'Continue with "'+goSelect.options[goSelect.selectedIndex].text+'"?';
		}
		else if(!Confirm(confirmText)){
			Event.stop(e);
			return false;
		}

		var form = getParent(this.goButton, 'form');
		for(var key in this.selected_ids){
			if(!empty(this.selected_ids[key]))
				create_var(form.name, this.pageGoName+'['+key+']', key, false);
		}

		return true;
	}
	else{
		alert(locale['S_NO_ELEMENTS_SELECTED']);
		Event.stop(e);
		return false;
	}
}
};

/************************************************************************************/
/*								Audio Control System 								*/
/************************************************************************************/
var AudioList = {
list:		{},		// audio files options
dom:		{},		// dom objects links
standart:	{
	'embed':{
		'enablejavascript':	'true',
		'autostart':		'false',
		'loop':				0
	},
	'audio':{
		'autobuffer':	'autobuffer',
		'autoplay':		null,
		'controls':		null
	}
},

play: function(audiofile){
	if(!this.create(audiofile)) return false;

	if(IE){
		try{
			this.dom[audiofile].Play();
		}
		catch(e){
			setTimeout(this.play.bind(this, audiofile), 500);
		}
	}
	else this.dom[audiofile].play();
},

pause: function(audiofile){
	if(!this.create(audiofile)) return false;

	if(IE){
		try{
			this.dom[audiofile].Stop();
		}
		catch(e){
			setTimeout(this.pause.bind(this, audiofile), 1000);
		}
	}
	else this.dom[audiofile].pause();
},

stop: function(audiofile){
	if(!this.create(audiofile)) return false;

	if(IE) this.dom[audiofile].setAttribute('loop', '0');
	else this.dom[audiofile].removeAttribute('loop');

	if(!IE){
		try{
			if(!this.dom[audiofile].paused){
				this.dom[audiofile].currentTime = 0;
			}
			else if(this.dom[audiofile].currentTime > 0){
				this.dom[audiofile].play();
				this.dom[audiofile].currentTime = 0;
				this.dom[audiofile].pause();
			}
		}
		catch(e){
//			this.remove(audiofile);
		}
	}

	if(!is_null(this.list[audiofile].timeout)){
		clearTimeout(this.list[audiofile].timeout);
		this.list[audiofile].timeout = null;
	}

	this.pause(audiofile);
	this.endLoop(audiofile);
},

stopAll: function(e){

	for(var name in this.list){
		if(empty(this.dom[name])) continue;

		this.stop(name);
	}
},


volume: function(audiofile, vol){
	if(!this.create(audiofile)) return false;
},

loop: function(audiofile, params){
	if(!this.create(audiofile)) return false;

	if(isset('repeat', params)){
		if(IE) this.play(audiofile);
		else{
			if(this.list[audiofile].loop == 0){
				if(params.repeat != 0) this.startLoop(audiofile, params.repeat);
				else this.endLoop(audiofile);
			}

			if(this.list[audiofile].loop != 0){
				this.list[audiofile].loop--;
				this.play(audiofile);
			}
		}
	}
	else if(isset('seconds', params)){
		if(IE){
			this.dom[audiofile].setAttribute('loop', '1');
		}
		else{
			this.startLoop(audiofile, 9999999);
			this.list[audiofile].loop--;
		}

		this.play(audiofile);
		this.list[audiofile].timeout = setTimeout(AudioList.stop.bind(AudioList,audiofile), 1000 * parseInt(params.seconds, 10));
	}
},

startLoop: function(audiofile, loop){
	if(!isset(audiofile, this.list)) return false;

	if(isset('onEnded', this.list[audiofile])) this.endLoop(audiofile);

	this.list[audiofile].loop = parseInt(loop, 10);
	this.list[audiofile].onEnded = this.loop.bind(this, audiofile, {'repeat': 0});
	addListener(this.dom[audiofile], 'ended', this.list[audiofile].onEnded);
},

endLoop: function(audiofile){
	if(!isset(audiofile, this.list)) return true;

	this.list[audiofile].loop = 0;

	if(isset('onEnded', this.list[audiofile])){
		removeListener(this.dom[audiofile], 'ended', this.list[audiofile].onEnded);
		this.list[audiofile].onEnded = null;
		delete(this.list[audiofile].onEnded);
	}
},

create: function(audiofile, params){
	if(typeof(audiofile) == 'undefined') return false;
	if(isset(audiofile, this.list)) return true;

	if(typeof(params) == 'undefined') params = {};

	if(!isset('audioList', this.dom)){
		this.dom.audioList = document.createElement('div');
		document.getElementsByTagName('body')[0].appendChild(this.dom.audioList);

		this.dom.audioList.setAttribute('id','audiolist');
	}

	if(IE){
		this.dom[audiofile] = document.createElement('embed');
		this.dom.audioList.appendChild(this.dom[audiofile]);

		this.dom[audiofile].setAttribute('name', audiofile);
		this.dom[audiofile].setAttribute('src', 'audio/'+audiofile);
		this.dom[audiofile].style.display = 'none';

		for(var key in this.standart.embed){
			if(isset(key, params))
				this.dom[audiofile].setAttribute(key, params[key]);
			else if(!is_null(this.standart.embed[key]))
				this.dom[audiofile].setAttribute(key, this.standart.embed[key]);
		}
	}
	else{
		this.dom[audiofile] = document.createElement('audio');
		this.dom.audioList.appendChild(this.dom[audiofile]);

		this.dom[audiofile].setAttribute('id', audiofile);
		this.dom[audiofile].setAttribute('src', 'audio/'+audiofile);

		for(var key in this.standart.audio){
			if(isset(key, params))
				this.dom[audiofile].setAttribute(key, params[key]);
			else if(!is_null(this.standart.audio[key]))
				this.dom[audiofile].setAttribute(key, this.standart.audio[key]);
		}

		this.dom[audiofile].load();
	}

	this.list[audiofile] = params;
	this.list[audiofile].loop = 0;
	this.list[audiofile].timeout = null;

return true;
},

remove: function(audiofile){
	if(!isset(audiofile, this.dom)) return true;

	$(this.dom[audiofile]).remove();

	delete(this.dom[audiofile]);
	delete(this.list[audiofile]);
}
}

/************************************************************************************/
/*						Replace Standart Blink functionality						*/
/************************************************************************************/
// Author: Aly
var blink = {
	blinkobjs: new Array(),

	init: function(){

		if(IE)
			this.blinkobjs = $$('*[name=blink]');
		else
			this.blinkobjs = document.getElementsByName("blink");

		if(this.blinkobjs.length > 0) this.view();
	},
	hide: function(){
		for(var id=0; id<this.blinkobjs.length; id++){
			this.blinkobjs[id].style.visibility = 'hidden';
		}
		setTimeout('blink.view()',500);
	},
	view: function(){
		for(var id=0; id<this.blinkobjs.length; id++){
			this.blinkobjs[id].style.visibility = 'visible'
		}
		setTimeout('blink.hide()',1000);
	}
}


/************************************************************************************/
/*								ZABBIX HintBoxes 									*/
/************************************************************************************/
var hintBox = {
boxes:				{},				// array of dom Hint Boxes
boxesCount: 		0,				// unique box id


debug_status: 		0,				// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 		'',				// debug string
debug_prev:			'',				// don't log repeated fnc

createBox: function(obj, hint_text, width, className, byClick){
	this.debug('createBox');

	var boxid = 'hintbox_'+this.boxesCount;

	var box = document.createElement('div');

	var obj_tag = obj.nodeName.toLowerCase();

	if((obj_tag == 'td') || (obj_tag == 'div') || (obj_tag == 'body')) obj.appendChild(box);
	else obj.parentNode.appendChild(box);

	box.setAttribute('id', boxid);
	box.style.display = 'none';
	box.className = 'hintbox';

	if(!empty(className)){
		hint_text = "<span class=" + className + ">" + hint_text + "</"+"span>";
	}

	if(!empty(width)){
		box.style.width = width+'px';
	}

	var close_link = '';
	if(byClick){
		close_link = '<div class="link" '+
						'style="text-align: right; border-bottom: 1px #333 solid;" '+
						'onclick="javascript: hintBox.hide(event, \''+boxid+'\');">'+locale['S_CLOSE']+'</div>';
	}

	box.innerHTML = close_link + hint_text;

/*
	var box_close = document.createElement('div');
	box.appendChild(box_close);
	box_close.appendChild(document.createTextNode('X'));
	box_close.className = 'link';
	box_close.setAttribute('style','text-align: right; background-color: #AAA;');
	box_close.onclick = eval("function(){ hintBox.hide('"+boxid+"'); }");
*/
	this.boxes[boxid] = box;
	this.boxesCount++;

return box;
},

showOver: function(e, obj, hint_text, width, className){
	this.debug('showOver');

	if (!e) var e = window.event;

	var hintid = obj.getAttribute('hintid');
	var hintbox = $(hintid);

	if(!empty(hintbox))
		var byClick = hintbox.getAttribute('byclick');
	else
		var byClick = null;

	if(!empty(byClick)) return;

	var hintbox = this.createBox(obj,hint_text, width, className, false);

	obj.setAttribute('hintid', hintbox.id);
	this.show(e, obj, hintbox);
},

hideOut: function(e, obj){
	this.debug('hideOut');

	if (!e) var e = window.event;

	var hintid = obj.getAttribute('hintid');
	var hintbox = $(hintid);

	if(!empty(hintbox))
		var byClick = hintbox.getAttribute('byclick');
	else
		var byClick = null;

	if(!empty(byClick)) return;

	if(!empty(hintid)){
		obj.removeAttribute('hintid');
		obj.removeAttribute('byclick');

		this.hide(e, hintid);
	}
},

onClick: function(e, obj, hint_text, width, className){
	this.debug('onClick');

	if (!e) var e = window.event;
	cancelEvent(e);

	var hintid = obj.getAttribute('hintid');
	var hintbox = $(hintid);

	if(!empty(hintbox))
		var byClick = hintbox.getAttribute('byclick');
	else
		var byClick = null;

	if(!empty(hintid) && empty(byClick)){
		obj.removeAttribute('hintid');
		this.hide(e, hintid);

		var hintbox = this.createBox(obj, hint_text, width, className, true);

		hintbox.setAttribute('byclick', 'true');
		obj.setAttribute('hintid', hintbox.id);

		this.show(e, obj, hintbox);
	}
	else if(!empty(hintid)){
		obj.removeAttribute('hintid');
		hintbox.removeAttribute('byclick');

		this.hide(e, hintid);
	}
	else{
		var hintbox = this.createBox(obj,hint_text, width, className, true);

		hintbox.setAttribute('byclick', 'true');
		obj.setAttribute('hintid', hintbox.id);

		this.show(e, obj, hintbox);
	}
},

show: function(e, obj, hintbox){
	this.debug('show');

	var hintid = hintbox.id;
	// var body_width = get_bodywidth();
	var body_width = document.viewport.getDimensions().width;

//	pos = getPosition(obj);
// this.debug('body width: ' + body_width);
// this.debug('position.top: ' + pos.top);

// by Object
/*
	if(parseInt(pos.left+obj.offsetWidth+4+hintbox.offsetWidth) > body_width){
		pos.left-=parseInt(hintbox.offsetWidth);
		pos.left-=4;
		pos.left=(pos.left < 0)?0:pos.left;
	}
	else{
		pos.left+= obj.offsetWidth+4;
	}
	hintbox.x	= pos.left;
//*/

	hintbox.style.visibility = 'hidden';
	hintbox.style.display = 'block';

	posit = $(obj).positionedOffset();
	cumoff = $(obj).cumulativeOffset();
	if(parseInt(cumoff.left+10+hintbox.offsetWidth) > body_width){
		posit.left = posit.left - parseInt((cumoff.left+10+hintbox.offsetWidth) - body_width) + document.viewport.getScrollOffsets().left;
		// posit.left-=parseInt(hintbox.offsetWidth);
		posit.left-=10;
		posit.left = (posit.left < 0) ? 0 : posit.left;
	}
	else{
		posit.left+=10;
	}
	hintbox.x	= posit.left;
	hintbox.y	= posit.top;
	hintbox.style.left = hintbox.x + 'px';
	hintbox.style.top	= hintbox.y + 10 + parseInt(obj.offsetHeight/2) + 'px';
	hintbox.style.visibility = 'visible';
	hintbox.style.zIndex = '999';

// IE6 z-index bug
	//if(IE6) showPopupDiv(hintid, 'frame_'+hintid);

},

hide: function(e, boxid){
	this.debug('hide');

	if (!e) var e = window.event;
	cancelEvent(e);

	var hint = $(boxid);
	if(!is_null(hint)){
		delete(this.boxes[boxid]);

		//hidePopupDiv('frame_'+hint.id);
// Opera refresh bug!
		hint.style.display = 'none';
		//hintbox.setAttribute('byclick', 'true');
		if(OP) setTimeout(function(){hint.remove();}, 200);
		else hint.remove();

	}
},

hideAll: function(){
	this.debug('hideAll');

	for(var id in this.boxes){
		if((typeof(this.boxes[id]) != 'undefined') && !empty(this.boxes[id])){
			this.hide(id);
		}
	}
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'PMaster.'+fnc_name;
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

/************************************************************************************/
/*								COLOR PICKER FUNCTIONS 								*/
/************************************************************************************/
function hide_color_picker(){
	if(!color_picker) return;

	color_picker.style.zIndex = 1000;
	color_picker.style.visibility="hidden"
	color_picker.style.left	= "-" + ((color_picker.style.width) ? color_picker.style.width : 100) + "px";

	curr_lbl = null;
	curr_txt = null;
}

function show_color_picker(name){
	if(!color_picker) return;

	curr_lbl = document.getElementById("lbl_" + name);
	curr_txt = document.getElementById(name);

	var pos = getPosition(curr_lbl);

	color_picker.x	= pos.left;
	color_picker.y	= pos.top;

	color_picker.style.left	= color_picker.x + "px";
	color_picker.style.top	= color_picker.y + "px";

	color_picker.style.visibility = "visible";
}

function create_color_picker(){
	if(color_picker) return;

	color_picker = document.createElement("div");
	color_picker.setAttribute("id", "color_picker");
	color_picker.innerHTML = color_table;
	document.body.appendChild(color_picker);

	hide_color_picker();
}

function set_color(color){
	if(curr_lbl){
		curr_lbl.style.background = curr_lbl.style.color = "#" + color;
		curr_lbl.title = "#" + color;
	}
	if(curr_txt)	curr_txt.value = color;

	hide_color_picker();
}

function set_color_by_name(name, color){
	curr_lbl = document.getElementById("lbl_" + name);
	curr_txt = document.getElementById(name);

	set_color(color);
}

/************************************************************************************/
/*								ZABBIX AJAX REQUESTS 								*/
/************************************************************************************/

function add2favorites(favobj,favid){
	if('undefined' == typeof(Ajax)){
		throw("Prototype.js lib is required!");
		return false;
	}

	if((typeof(favid) == 'undefined') || empty(favid)) return;

	var params = {
		'favobj': 	favobj,
		'favid': 	favid,
		'action':	'add'
	}

	send_params(params);
//	json.onetime('dashboard.php?output=json&'+Object.toQueryString(params));
}


function rm4favorites(favobj,favid,menu_rowid){
//	alert(favobj+','+favid+','+menu_rowid);
	if('undefined' == typeof(Ajax)){
		throw("Prototype.js lib is required!");
		return false;
	}

	if((typeof(favobj) == 'undefined') || (typeof(favid) == 'undefined'))
		throw "No agruments sent to function [rm4favorites()].";

	var params = {
		'favobj': 	favobj,
		'favid': 	favid,
		'favcnt':	menu_rowid,
		'action':	'remove'
	}

	send_params(params);
//	json.onetime('dashboard.php?output=json&'+Object.toQueryString(params));
}

function change_flicker_state(divid){
	deselectAll();
	var eff_time = 500;

	var switchArrows = function(){
		switchElementsClass($("flicker_icon_l"),"dbl_arrow_up","dbl_arrow_down");
		switchElementsClass($("flicker_icon_r"),"dbl_arrow_up","dbl_arrow_down");
	}

	var filter_state = ShowHide(divid);
	switchArrows();
//	var filter_state = showHideEffect(divid,'slide', eff_time, switchArrows);

	if(false === filter_state) return false;

	var params = {
		'action':	'flop',
		'favobj': 	'filter',
		'favref': 	divid,
		'state':	filter_state
	}

	send_params(params);

// selection box position
	if(typeof(moveSBoxes) != 'undefined') moveSBoxes();
}

function change_hat_state(icon, divid){
	deselectAll();

	var eff_time = 500;

	var switchIcon = function(){
		switchElementsClass(icon,"arrowup","arrowdown");
	}

	var hat_state = ShowHide(divid);
	switchIcon();
//	var hat_state = showHideEffect(divid, 'slide', eff_time, switchIcon);

	if(false === hat_state) return false;

	var params = {
		'action':	'flop',
		'favobj': 	'hat',
		'favref': 	divid,
		'state':	hat_state
	}

	send_params(params);
}

function send_params(params){
	if(typeof(params) == 'undefined') var params = new Array();

	var url = new Curl(location.href);
	url.setQuery('?output=ajax');

	new Ajax.Request(url.getUrl(),
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onFailure': function(){document.location = url.getPath()+'?'+Object.toQueryString(params);}
					}
	);
}


function setRefreshRate(pmasterid,dollid,interval,params){
	if(typeof(Ajax) == 'undefined'){
		throw("Prototype.js lib is required!");
		return false;
	}

	if((typeof(params) == 'undefined') || is_null(params))  var params = new Array();
	params['pmasterid'] = 	pmasterid;
	params['favobj'] = 		'set_rf_rate';
	params['favref'] = 		dollid;
	params['favcnt'] = 		interval;
//SDJ($params);
	send_params(params);
}

function switch_mute(icon){
	deselectAll();
	var sound_state = switchElementsClass(icon,"iconmute","iconsound");

	if(false === sound_state) return false;
	sound_state = (sound_state == "iconmute")?1:0;

	var params = {
		'favobj': 	'sound',
		'favref':	'sound',
		'state':	sound_state
	}

	send_params(params);
}
