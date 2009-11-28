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

var CSwitcher = Class.create();

CSwitcher.prototype = {

switchers_name : 'switchers',
switchers : {},
imgOpened : 'images/general/opened.gif',  
imgClosed : 'images/general/closed.gif',
initialState : 0,

initialize : function(){

	var divs = $$('div[data-switcherid]');
	for(var i=0; i<divs.length; i++){
		if(!isset(i, divs)) continue;
		addListener(divs[i], 'click', this.showHide.bindAsEventListener(this));
		
		var key = divs[i].readAttribute('data-switcherid');
		this.switchers[key] = 0;
	}
	
	
	if((to_change = cookie.readJSON(this.switchers_name)) != null){
		cookie.erase(this.switchers_name);
SDJ(to_change);		
		for(var i in to_change){
			this.switchers[i] = to_change[i];
			if(this.initialState != to_change[i]){
				this.open(to_change[i]);
			}
		}	
	}
 // SDJ(this.switchers);
},

open : function(switcherid){
	var switcherid = $$('div[data-switcherid='+switcherid+']');


	if(typeof(switcherid) != 'undefined'){
	
		var obj = switcherid[0];

		$(obj).firstDescendant().writeAttribute('src', this.imgOpened);
	
		var elements = $$('tr[data-parentid='+switcherid+']');
		for(var i=0; i<elements.length; i++){
			if(!isset(elements[i])) continue;
			elements[i].style.display = '';
		}
	}
},

showHide : function(e){
//	var e = event || e;
	var obj = e.currentTarget;

	cookie.erase(this.switchers_name);
	
	var switcherid = $(obj).readAttribute('data-switcherid');
	var img = $(obj).firstDescendant();
	
	if(img.readAttribute('src') == this.imgClosed){
		var state = 1;
		var newImgPath = this.imgOpened;
		var oldImgPath = this.imgClosed;
	}
	else{
		var state = 0;
		var newImgPath = this.imgClosed;
		var oldImgPath = this.imgOpened;
	}
	img.writeAttribute('src', newImgPath);
	
	
	if(typeof(switcherid) == 'undefined'){
		var imgs = $$('img[src='+oldImgPath+']');
		for(var i=0; i < imgs.length; i++){
			if(empty(imgs[i])) continue;
			imgs[i].src = newImgPath;
		}
	}
	
	var elements = $$('tr[data-parentid]');

	for(var i=0; i<elements.length; i++){
		if(empty(elements[i])) continue;
		
		if((typeof(switcherid) == 'undefined') || elements[i].getAttribute('data-parentid') == switcherid){
			if(state){
				elements[i].style.display = '';
			}
			else{
				elements[i].style.display = 'none';
			}
		}
	}
	
	if(typeof(switcherid) == 'undefined'){
		for(var i in this.switchers){
			if(!isset(i, this.switchers)) continue;
			this.switchers[i] = state;
		}
	}
	else{
		this.switchers[switcherid] = state;
	}

	cookie.createJSON(this.switchers_name, this.switchers);
}

}



