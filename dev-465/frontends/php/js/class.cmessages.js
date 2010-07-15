/*
** Copyright (C) 2010 Artem "Aly" Suharev
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
// JavaScript Document

var ZBX_MESSAGES = new Array();				// obj instances
function initMessages(args){					// use this function to initialize Messaging system
	var messagesListId = ZBX_MESSAGES.length;
	ZBX_MESSAGES[messagesListId] = new CMessageList(messagesListId, args);

return messagesListId;
}

// Puppet master Class
// Author: Aly
var CMessageList = Class.create(CDebug,{
messageListId:			0,				// PMasters reference id

updateFrequency:		60,				// seconds
timeoutFrequency:		10,				// seconds
soundFrequency:			1,				// seconds

ready:					false,
PEupdater:				null,			// PeriodicalExecuter object update
PEtimeout:				null,			// PeriodicalExecuter object update

lastupdate:				0,				// lastupdate timestamp
msgcounter:				0,				// how many messages have been added

pipeLength:				15,				// how many messages to show

messageList:			{},				// list of recieved messages
messagePipe:			new Array(),	// messageid pipe line
messageLast:			{},				// last massages sourceid by caption

effectTimeout:			1000,			// effect time out

sounds:{								// sound playback settings
	'priority': 0,						// max new message priority
	'sound': null,						// sound to play
	'repeat':	1						// loop sound for 1,3,5,10 .. times
},

dom:					{},				// dom object links

initialize: function($super, messagesListId, args){
	this.messageListId = messagesListId;
	$super('CMessageList['+messagesListId+']');
//--
//	return false;
	
	this.dom = {};
	this.messageList = {};
	this.messageLast = {};

	this.updateSettings();
	this.createContainer();
	if(IE) this.fixIE();

	addListener(this.dom.closeAll, 'click', this.closeAllMessages.bindAsEventListener(this));
	addListener(this.dom.snooze, 'click', this.stopSound.bindAsEventListener(this));

	new Draggable(this.dom.container, {
		handle: this.dom.header,
		constraint: 'vertical',
		scroll: window,
		snap: function(x,y){ if(y < 0) return [x,0]; else return [x,y]; }
	});
//	addListener(this.dom.mute, 'click', this.mute.bindAsEventListener(this));

	if(is_null(this.PEupdater)){
		this.ready = true;
		this.lastupdate = 0;

		this.PEupdater = new PeriodicalExecuter(this.getServerMessages.bind(this), this.updateFrequency);
		this.getServerMessages();
	}

	if(is_null(this.PEtimeout)){
		this.PEtimeout = new PeriodicalExecuter(this.timeoutMessages.bind(this), this.timeoutFrequency);
		this.timeoutMessages();
	}
},

addMessage: function(newMessage){
	this.debug('addMessage');
//--
	var newMessage = newMessage || {};

	while(isset(this.msgcounter, this.messageList)){
		this.msgcounter++;
	}

	if(this.messagePipe.length > this.pipeLength){
		var lastMessageId = this.messagePipe.shift();
		this.closeMessage(lastMessageId);
	}
	
	this.messagePipe.push(this.msgcounter);
	newMessage.messageid = this.msgcounter;

	this.messageList[this.msgcounter] = new CMessage(this, newMessage);
	this.messageLast[this.messageList[this.msgcounter].caption] = this.messageList[this.msgcounter].sourceid;

	$(this.dom.container).show();

return this.messageList[this.msgcounter];
},


playSound: function(messages){
	this.debug('playSound');
//--

	this.sounds.priority = 0;
	this.sounds.sound = null;

	for(var i=0; i < messages.length; i++){
		var message = messages[i];
		if(message.type != 1 && message.type != 3) continue;
		
		if(message.priority > this.sounds.priority){
			this.sounds.priority = message.priority;
			this.sounds.sound = message.sound;
		}
	}

	this.ready = true;
	if(!is_null(this.sounds.sound)){
		AudioList.loop(this.sounds.sound, this.sounds.repeat);
	}
},

stopSound: function(e){
	this.debug('stopSound');
//--
	if(!is_null(this.sounds.sound)){
		AudioList.stop(this.sounds.sound);
	}
},

closeMessage: function(messageid, withEffect){
	this.debug('closeMessage', messageid);
//--
	if(!isset(messageid, this.messageList)) return true;


	AudioList.stop(this.messageList[messageid].sound);
	if(withEffect) this.messageList[messageid].remove();
	else this.messageList[messageid].close();
	

	try{
		delete(this.messageList[messageid]);
	}
	catch(e){
		this.messageList[messageid] = null;
	}

	this.messagePipe = new Array();
	for(var messageid in this.messageList) this.messagePipe.push(messageid);

	if(this.messagePipe.length < 1){
		this.messagePipe = new Array();
		this.messageList = {};
		setTimeout(Element.hide.bind(Element, this.dom.container), this.effectTimeout);
	}
},

closeAllMessages: function(e){
	this.debug('closeAllMessages');
//--
	var lastMessageId = this.messagePipe.pop();

	var rpcRequest = {
		'method': 'message.close',
		'params': {
			'caption': this.messageList[lastMessageId].caption,
			'sourceid': this.messageList[lastMessageId].sourceid,
			'priority': this.messageList[lastMessageId].priority,
			'messageid': this.messageList[lastMessageId].messageid
		},
//		'onSuccess': function(resp){ SDI(resp)},
		'onFailure': function(resp){zbx_throw('Messages Widget: message request failed.');}
	}

	new RPC.Call(rpcRequest);

	Effect.SlideUp(this.dom.container, {duration: (this.effectTimeout / 1000)});
	for(var messageid in this.messageList){
		if(empty(this.messageList[messageid])) continue;

		setTimeout(this.closeMessage.bind(this, messageid, false), this.effectTimeout);
	}
},

timeoutMessages: function(){
	this.debug('timeoutMessages');
//--
	var now = parseInt(new Date().getTime()/1000);

	var timeout = 0;
	for(var messageid in this.messageList){
		if(empty(this.messageList[messageid])) continue;

		var msg = this.messageList[messageid];
		if((msg.time + parseInt(msg.timeout, 10)) < now){
			setTimeout(this.closeMessage.bind(this, messageid, true), (500*timeout));
			timeout++;
		}
	}
},

getServerMessages: function(){
	this.debug('getServerMessages');
//--

	var now = parseInt(new Date().getTime()/1000);
	if(!this.ready || ((this.lastupdate + this.updateFrequency) > now)) return true;

	this.ready = false;

	var rpcRequest = {
		'method': 'message.get',
		'params': {
			'messageListId': this.messageListId,
			'messageLast': this.messageLast
		},
		'onSuccess': this.serverRespond.bind(this),
		'onFailure': function(resp){zbx_throw('Messages Widget: message request failed.');}
	}

//SDJ(rpcRequest.params.messageLast);

	new RPC.Call(rpcRequest);

	this.lastupdate = now;
},

serverRespond: function(messages){
	this.debug('serverRespond');
//--

	for(var i=0; i < messages.length; i++){
		var message = this.addMessage(messages[i]);
	}

	this.playSound(messages);
	this.ready = true;
},

updateSettings: function(){
	this.debug('updateSettings');
//--

	var rpcRequest = {
		'method': 'message.settings',
		'params': {},
		'onSuccess': this.setSettings.bind(this),
		'onFailure': function(resp){zbx_throw('Messages Widget: settings request failed.');}
	}

//SDJ(rpcRequest.params.messageLast);

	new RPC.Call(rpcRequest);
},

setSettings: function(settings){
	this.debug('setSettings');
//--

	this.sounds.repeat = settings.sounds.loop;
},
// DOM creation
createContainer: function(){
	this.debug('createContainer');
//--

	this.dom.container = $('zbx_messages');

	if(!empty(this.dom.container)) return false;

	var doc_body = document.getElementsByTagName('body')[0];
	if(empty(doc_body)) return false;

	this.dom.container = document.createElement('div');
	doc_body.appendChild(this.dom.container);
// container
	this.dom.container.setAttribute('id','zbx_messages');
	this.dom.container.className = 'messagecontainer';
	$(this.dom.container).hide();

// Header
	this.dom.header = document.createElement('div');
	this.dom.container.appendChild(this.dom.header);

	this.dom.header.className = 'header';
// close all
	this.dom.closeAll = document.createElement('div');
	this.dom.header.appendChild(this.dom.closeAll);

	this.dom.closeAll.setAttribute('title', 'Close all');
	this.dom.closeAll.className = 'iconclose';
	this.dom.closeAll.style.cssFloat = 'right';
	this.dom.closeAll.style.marginRight = '2px';

// snooze
	this.dom.snooze = document.createElement('div');
	this.dom.header.appendChild(this.dom.snooze);

	this.dom.snooze.setAttribute('title', 'Snooze');
	this.dom.snooze.className = 'iconsnooze';
	this.dom.snooze.style.cssFloat = 'right';
	this.dom.snooze.style.marginRight = '2px';

// mute
	this.dom.mute = document.createElement('div');
	this.dom.header.appendChild(this.dom.mute);

	this.dom.mute.setAttribute('title', 'Mute/Unmute');
	this.dom.mute.className = 'iconsound';
	this.dom.mute.style.cssFloat = 'right';
	this.dom.mute.style.marginRight = '2px';

// Message List
	this.dom.list = document.createElement('ul');
	this.dom.container.appendChild(this.dom.list);
},

fixIE: function(){
	if(IE6) this.dom.header.style.width = '60px';

	this.dom.closeAll.style.position = 'absolute';
	this.dom.closeAll.style.right = '2px';

	this.dom.snooze.style.position = 'absolute';
	this.dom.snooze.style.right = '22px';

	this.dom.mute.style.position = 'absolute';
	this.dom.mute.style.right = '42px';
}
});

// JavaScript Document
// Message Class
// Author: Aly
var CMessage = Class.create(CDebug,{
list:				null,			// link to message list containing this message
messageid:			null,			// msg id
caption:			'unknown',		// msg caption (events, actions, infos..  e.t.c.)
sourceid:			null,			// caption + sourceid = identifier for server
type:				0,				// 1 - sound, 2 - text, 3 - sound & text, 4 - notdefined
priority:			0,				// msg priority ASC
sound:				null,			// msg sound
color:				'ffffff',		// msg color
time:				0,				// msg time arrival
title:				'No title',		// msg header
body:				['No text'],	// msg details
timeout:			60,				// msg timeout

dom:				{},				// msg dom links


initialize: function($super, messageList, message){
	this.messageid = message.messageid;
	$super('CMessage['+this.messageid+']');
//--

	this.dom = {};
	this.list = messageList;

	for(var key in message){
		if(empty(message[key]) || !isset(key, this)) continue;

		if(key == 'time') this[key] = parseInt(message[key]);
		else this[key] = message[key];
	}

	this.createMessage();
	if(IE) this.fixIE();

},

show: function(){
},

close: function(){
	this.debug('close');
//--
	$(this.dom.listItem).remove();

	if(IE) this.fixIE();
	this.dom = {};
},

playSound: function(){
},

stopSound: function(){

},

notify: function(){
},

remove: function(){
	this.stopSound();


	Effect.BlindUp(this.dom.listItem, {duration: (this.list.effectTimeout / 1000)});
	Effect.Fade(this.dom.listItem, {duration: (this.list.effectTimeout / 1000)});
	setTimeout(this.close.bind(this), this.list.effectTimeout);

},

createMessage: function(){
	this.debug('createMessage');
//--

// LI
	this.dom.listItem = document.createElement('li');
	$(this.list.dom.list).insert({'top': this.dom.listItem});
	this.dom.listItem.style.border = '1px solid #FFFFFF';//+this.color;

	this.dom.listItem.className = 'listItem';
// message
	this.dom.message = document.createElement('div');
	this.dom.listItem.appendChild(this.dom.message);

	this.dom.message.className = 'message';
	this.dom.message.style.backgroundColor = '#'+this.color;

// message box
	this.dom.messageBox = document.createElement('div');
	this.dom.message.appendChild(this.dom.messageBox);

	this.dom.messageBox.className = 'messagebox';
// title
	this.dom.title = document.createElement('span');
	this.dom.messageBox.appendChild(this.dom.title);

	this.dom.title.appendChild(document.createTextNode(this.title));
	this.dom.title.className = 'title';

// body
	if(!is_array(this.body)) this.body = new Array(this.body);

//	this.dom.message.style.height = (24+14*this.body.length)+'px';
	for(var i=0; i < this.body.length; i++){
		if(!isset(i, this.body) || empty(this.body[i])) continue;
		this.dom.messageBox.appendChild(document.createElement('br'));

		this.dom.body = document.createElement('span');
		this.dom.messageBox.appendChild(this.dom.body);

		this.dom.body.appendChild(document.createTextNode(this.body[i]));
		this.dom.body.className = 'body';
	}
},

fixIE: function(){
	if(IE6 || IE7){
		var maxWidth = 60;
		for(var tmpmsg in this.list.messageList){
			var msgDims = getDimensions(this.list.messageList[tmpmsg].dom.message);
			msgDims.width += 4;
			if(maxWidth < msgDims.width) maxWidth = msgDims.width;
		}

		this.list.dom.header.style.width = maxWidth+'px';
	}
}
});