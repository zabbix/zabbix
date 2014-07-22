/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

var LCL_SUGGESTS = [];

function createSuggest(oid){
	var sid = LCL_SUGGESTS.length;
	LCL_SUGGESTS[sid] = new CSuggest(sid, oid);

return sid;
}

var CSuggest = Class.create({
// PUBLIC
'useLocal':			true,	// use cache to find suggests
'useServer':		true,	// use server to find suggests

'saveToCache':		true,	// save results to cache

'cacheTimeOut':		60,		// cache timeout (seconds)
'suggestLimit':		15,		// suggestion show limit

'searchDelay':		200,	// milliseconds


// PRIVATE
'id':				null,	// sugg obj identity
'rpcid':			0,		// rpc request id

'needles':			{},		// searched strings
'userNeedle':		'',		// userNeedle
'timeoutNeedle':	null,	// Timeout reference

'cache':{
	'time':			0,		// cache creation time
	'list':			{},		// cache by word
	'needle':		{}		// cache by needle
},

'dom':{
	'input':		null,	// DOM node input
	'suggest':		null,	// DOM node suggest div
	'sugtab':		null	// DOM node suggests table
},

'hlIndex':			0,		// indicates what row should be highlighted
'suggestCount':		0,		// suggests shown

'mouseOverSuggest':	false,	// indicates if mouse is over suggests

initialize: function(id, objid){
	this.id = id;
//--

	this.cleanCache();

	this.dom.input = $(objid);

	addListener(this.dom.input, 'keyup', this.keyPressed.bindAsEventListener(this));
	addListener(this.dom.input, 'blur', this.suggestBlur.bindAsEventListener(this));
	addListener(window, 'resize', this.positionSuggests.bindAsEventListener(this));
//	addListener(window, 'keypress', this.searchFocus.bindAsEventListener(this));

	this.timeoutNeedle = null;
	this.userNeedle = this.dom.input.value;
},

needleChange: function(e){
	this.hlIndex = 0;
	this.suggestCount = 0;

	clearTimeout(this.timeoutNeedle);

	var target = Event.element(e);
	var needle = target.value.toLowerCase();

	if(empty(needle)){
		this.hideSuggests();
		return true;
	}

	this.userNeedle = needle;
	this.needles[needle] = {'needle': needle, 'list': {}};

	var found = false;

	if(this.useLocal) found = this.searchClient(needle);

	if(!found && this.useServer){
		this.timeoutNeedle = setTimeout(this.searchServer.bind(this, needle), this.searchDelay);
	}
},

// SEARCH
searchServer: function(needle){
	if(needle != this.userNeedle) return true;

	var rpcRequest = {
		'method': 'host.get',
		'params': {
			'startSearch': 1,
			'search': {'name': needle},
			'output': ['hostid', 'name', 'host'],
			'sortfield': 'name',
			'limit': this.suggestLimit
		},
		'onSuccess': this.serverRespond.bind(this, needle),
		'onFailure': function() {
			throw('Suggest Widget: search request failed.');
		}
	};

	new RPC.Call(rpcRequest);

return true;
},

serverRespond: function(needle, respond){
	var params = {
		'list': {},
		'needle': needle
	};

	for(var i=0; i < respond.length; i++){
		if(!isset(i, respond) || empty(respond[i])) continue;
		params.list[i] = respond[i].name;
	}
	this.needles[params.needle].list = params.list;

	if(needle == this.userNeedle){
		this.showSuggests();
		this.newSugTab(params.needle);
	}

	if(this.saveToCache) this.saveCache(params.needle, params.list);
},

searchClient: function(needle){
	var found = false;
	if(this.inCache(needle)){
		this.needles[needle].list = this.cache.needle[needle];
		found = true;
	}
	else if(!this.useServer){
		found = this.searchCache(needle);
	}

	if(found){
		this.showSuggests();
		this.newSugTab(needle);
	}

return found;
},
//-----

// -----------------------------------------------------------------------
// CACHE
// -----------------------------------------------------------------------
searchCache: function(needle){
	var fkey = needle[0];
	if(!isset(fkey, this.cache.list)) return false;

	var found = false;
	var list = {};
	for(var key in this.cache.list[fkey]){
		var value = this.cache.list[fkey][key];
		if(empty(value)) continue;

		if(key.indexOf(needle) === 0){
			list[value] = value;
			found = true;
		}
	}

	this.needles[needle].list = list;
	if(this.saveToCache) this.saveCache(needle, list);

return found;
},

inCache: function(needle){
	if(this.useServer){
		var dd = new Date();
		if((this.cache.time + (this.cacheTimeOut*1000)) < dd.getTime()) this.cleanCache();
	}

return isset(needle, this.cache.needle);
},

saveCache: function(needle, list){
	if(this.useServer){
		var dd = new Date();
		if((this.cache.time + (this.cacheTimeOut*1000)) < dd.getTime()) this.cleanCache();
	}

// Needles
	if(!is_null(needle)) this.cache.needle[needle] = list;

// List
	for(var key in list){
		if(empty(list[key])) continue;

		var word = list[key];
		var lWord = word.toLowerCase();

		var fkey = lWord[0];

// indexing by first letter
		if(!isset(fkey, this.cache.list)) this.cache.list[fkey] = {};
		this.cache.list[fkey][lWord] = word;
	}
},

cleanCache: function(){
	var time = new Date();
	this.cache = {
		'time':		time.getTime(),
		'list':		{},
		'needle':	{}
	}
},

// -----------------------------------------------------------------------
// Events
// -----------------------------------------------------------------------

onSelect: function(selection){
	return true;
},

// -----------------------------------------------------------------------
// Keyboard
// -----------------------------------------------------------------------
searchFocus: function(e){
	if(!e) e = window.event;

	var elem = e.element();
	if(elem.match('input[type=text]') || elem.match('textarea') || elem.match('select')) return true;

	var key = e.keyCode;
	if(key == 47){
		e.stop();
		$(this.dom.input).focus();
		return void(0);
	}
},

keyPressed: function(e){
	if(!e) e = window.event;
	var key = e.keyCode;

	switch(true){
		case(key == 27):
			this.hlIndex = 0;
			this.suggestCount = 0;
			this.removeHighLight(e);
			this.setNeedleByHighLight(e);
			this.hideSuggests(e);
			break;
		case(key==13):
			Event.stop(e);
			this.selectSuggest(e);
			break;
		case(key == 37 || key == 39 || key == 9): // left, right, tab
			break;
		case(key==38): // up
			this.keyUp(e);
			break;
		case(key==40): // down
			this.keyDown(e);
			break;
		default:
			this.needleChange(e);
	}

	Event.stop(e);
},

keyUp: function(e){
	if(this.hlIndex == 0) this.hlIndex = this.suggestCount;
	else this.hlIndex--;

	this.removeHighLight(e);
	this.highLightSuggest(e);
	this.setNeedleByHighLight(e);
},

keyDown: function(e){
	if(is_null(this.dom.suggest) || (this.dom.suggest.style.display == 'none')){
		this.needleChange(e);
		return true;
	}

	if(this.hlIndex == this.suggestCount) this.hlIndex = 0;
	else this.hlIndex++;

	this.removeHighLight(e);
	this.highLightSuggest(e);
	this.setNeedleByHighLight(e);
},

mouseOver: function(e){
	this.mouseOverSuggest = true;

	var row = Event.element(e).parentNode;
	if(is_null(row) || (row.tagName.toLowerCase() != 'tr') || !isset('id',row)) return true;

	var tmp = row.id.split('_');
	if(tmp.length != 2) return true;

	this.hlIndex = parseInt(tmp[1], 10);

	this.removeHighLight(e);
	this.highLightSuggest(e);
},

mouseOut: function(e){
	this.mouseOverSuggest = false;
},

suggestBlur: function(e){
	if(this.mouseOverSuggest) Event.stop(e);
	else this.hideSuggests(e);
},

// -----------------------------------------------------------------------
// HighLight
// -----------------------------------------------------------------------

removeHighLight: function(){
	$$('tr.highlight').each( function(hlRow){hlRow.className = '';});
},


highLightSuggest: function(){
	var row = $('line_'+this.hlIndex);
	if(!is_null(row)) row.className = 'highlight';
},

setNeedleByHighLight: function(){
	if(this.hlIndex == 0)
		this.dom.input.value = this.userNeedle;
	else
		this.dom.input.value = $('line_'+this.hlIndex).readAttribute('needle');
},

selectSuggest: function(e){
	this.setNeedleByHighLight(e);
	this.hideSuggests();

	if(this.onSelect(this.dom.input.value) && !GK) this.dom.input.form.submit();
},


// -----------------------------------------------------------------------
// DOM creation
// -----------------------------------------------------------------------

showSuggests: function(){
	if(is_null(this.dom.suggest)){
		this.dom.suggest = document.createElement('div');
		this.dom.suggest = $(this.dom.suggest);

		var doc_body = document.getElementsByTagName('body')[0];
		if(empty(doc_body)) return false;

		doc_body.appendChild(this.dom.suggest);
		this.dom.suggest.className = 'suggest';

		this.positionSuggests();
	}

	this.dom.suggest.style.display = 'block';
},

hideSuggests: function(){
	if(!is_null(this.dom.suggest)){
		this.dom.suggest.style.display = 'none';
	}
},

positionSuggests: function(){
	if(is_null(this.dom.suggest)) return true;

	var pos = jQuery(this.dom.input).offset();
	var dims = getDimensions(this.dom.input);

	this.dom.suggest.style.top = (pos.top+dims.height)+'px';
	this.dom.suggest.style.left = pos.left+'px';
},

newSugTab: function(needle){
	var list = this.needles[needle].list;

	var sugTab = document.createElement('table');
	sugTab.className = 'suggest';

	var sugBody = document.createElement('tbody');
	sugTab.appendChild(sugBody);

	var count = 0;
	for(var key in list){
		if(empty(list[key])) continue;
		count++;

		var tr = document.createElement('tr');
		sugBody.appendChild(tr);

		tr.setAttribute('id', 'line_'+count);
		tr.setAttribute('needle', list[key]);

		var td = document.createElement('td');
		tr.appendChild(td);

		var bold = document.createElement('b');
		bold.appendChild(document.createTextNode(list[key].substr(0, needle.length)));
		td.appendChild(bold);
		td.appendChild(document.createTextNode(list[key].substr(needle.length)));

		addListener(td, 'mouseover', this.mouseOver.bindAsEventListener(this), true);
		addListener(td, 'mouseup', this.selectSuggest.bindAsEventListener(this), true);
		addListener(td, 'mouseout', this.mouseOut.bindAsEventListener(this), true);

		if(count >= this.suggestLimit) break;
	}

	if(!is_null(this.dom.sugtab)) this.dom.sugtab.remove();

	this.dom.sugtab = $(sugTab);
	this.dom.suggest.appendChild(this.dom.sugtab);

	if(count == 0) this.hideSuggests();

	this.suggestCount = count;
}
});
