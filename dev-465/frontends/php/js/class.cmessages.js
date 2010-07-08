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
messageList:			{},				// list of recieved messages
pexec:					null,			// PeriodicalExecuter object

pipeLength:				15,				// how many messages to show
messagePipe:			new Array(),	// messageid pipe line
url:					'',

updateFrequency:		100,			// seconds
soundFrequency:			1,				// seconds

lastupdate:				0,				// lastupdate timestamp
msgcounter:				0,				// how many messages have been added
ready:					false,

dom:					{},				// dom object links

initialize: function($super, messagesListId, args){
	this.messageListId = messagesListId;
	$super('CMessageList['+messagesListId+']');
//--

	this.dom = {};
	this.messageList = {};
	if(typeof(args) != 'undefined'){
		if(isset('pipeLength', args))		this.pipeLength = args.pipeLength;
		if(isset('updateFrequency', args))	this.updateFrequency = args.updateFrequency;
	}

	this.createContainer();

	if(is_null(this.pexec)){
		this.ready = true;
		this.lastupdate = 0;

		this.pexec = new PeriodicalExecuter(this.getServerMessages.bind(this), this.updateFrequency);
		this.getServerMessages();
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
newMessage.title += this.msgcounter;
	this.messageList[this.msgcounter] = new CMessage(this, newMessage);


return this.messageList[this.msgcounter];
},

closeMessage: function(messageid){
	this.debug('closeMessage', messageid);
//--
	if(!isset(messageid, this.messageList)) return true;

	//this.messageList[messageid].close();
	this.messageList[messageid].remove();

	try{
		delete(this.messageList[messageid]);
	}
	catch(e){
		this.messageList[messageid] = null;
	}
},

closeAllMessages: function(){
	this.debug('closeAllMessages');
//--

	for(var messageid in this.messageList){
		if(empty(this.messageList[messageid])) continue;

		this.closeMessage(messageid);
	}

	this.messageList = {};
},

sortMessages: function(){
    var ids = array();

    for(var messageid in this.messageList){
		if(empty(this.messageList[messageid])) continue;

		ids.push(messageid);
	}

	for(var i=(ids.length - 1); i >= 0; i--){
		for(var j=0; j <= i; j++) {
			if(ids[j+1] >= ids[j]) continue;

			var tempValue = ids[j];
			ids[j] = ids[j+1];
			ids[j+1] = tempValue;
		}
	}

	var sortedMessages = new Array();
	for(var i=0; i < ids.length; i++){
		if(!isset(ids[i], this.messageList)) continue;

		sortedMessages.push(this.messageList[ids[i]]);
    }
	
return sortedMessages;
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
			'lastmsgid': this.lastmsgid
		},
		'onSuccess': this.serverRespond.bind(this),
		'onFailure': function(resp){alert('Messages Widget: message request failed.'); throw('Messages Widget: message request failed.'); }
	}

	new RPC.Call(rpcRequest);

	this.lastupdate = now;
},

serverRespond: function(messages){
	this.debug('serverRespond');
//--

	for(var i=0; i < messages.length; i++){
		this.addMessage(messages[i]);
	}

	this.ready = true;
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

	this.dom.container.setAttribute('id','zbx_messages');
	this.dom.container.className = 'messagecontainer';

	this.dom.list = document.createElement('ul');
	this.dom.container.appendChild(this.dom.list);
}
});

// JavaScript Document
// Message Class
// Author: Aly
var CMessage = Class.create(CDebug,{
messageList:		null,			// link to message list containing this message
messageid:			null,			// msg id
caption:			'unknown',		// msg caption (events, actions, infos..  e.t.c.)
sourceid:			null,			// caption + sourceid = identifier for server
type:				0,				// 1 - sound, 2 - text, 3 - sound & text, 4 - notdefined
priority:			0,				// msg priority ASC
color:				'ffffff',		// msg color
time:				0,				// msg time arrival
title:				'No title',		// msg header
body:				'No text',		// msg details

dom:				{},				// msg dom links

initialize: function($super, messageList, message){
	this.messageid = message.messageid;
	$super('CMessage['+this.messageid+']');
//--
	this.dom = {};
	this.messageList = messageList;

	for(var key in message){
		if(empty(message[key]) || !isset(key, this)) continue;

		this[key] = message[key];
	}

	this.createMessage();
},

show: function(){
},

close: function(){
	this.debug('close');
//--

	var rpcRequest = {
		'method': 'message.close',
		'params': {
			'messageListId': this.messageListId,
			'messageid': this.messageid,
			'caption': this.caption,
			'sourceid': this.sourceid,
			'priority': this.priority
		},
//		'onSuccess': this.confirmClose.bind(this),
		'onFailure': function(resp){ zbx_throw('Messages Widget: message request failed.'); }
	}

	new RPC.Call(rpcRequest);

	this.lastupdate = now;
},

confirmClose: function(confirm){
	this.debug('confirmClose');
//--

	alert('Closed: '+confirm.messageid);
},

playSound: function(){
},

stopSound: function(){

},

notify: function(){
},

remove: function(){
	this.stopSound();
	$(this.dom.listItem).remove();
	this.dom = {};
},

createMessage: function(){
	this.debug('createMessage');
//--

// LI
	this.dom.listItem = document.createElement('li');
	//this.messageList.dom.list.appendChild(this.dom.listItem);
	$(this.messageList.dom.list).insert({'top': this.dom.listItem});

	this.dom.listItem.className = 'listItem';
// message
	this.dom.message = document.createElement('div');
	this.dom.listItem.appendChild(this.dom.message);

	this.dom.message.className = 'message';

// color box
	this.dom.colorBox = document.createElement('div');
	this.dom.message.appendChild(this.dom.colorBox);

	this.dom.colorBox.className = 'colorbox';
	this.dom.colorBox.style.backgroundColor = '#'+this.color;
//*/

//* close box
	this.dom.closeBox = document.createElement('div');
	this.dom.message.appendChild(this.dom.closeBox);

	this.dom.closeBox.className = 'closebox';
	this.dom.closeBox.style.backgroundColor = '#FFFFFF';
	//this.dom.colorBox.style.backgroundColor = '#'+this.color;
//*/

// message table
	this.dom.table = document.createElement('table');
	//this.dom.listItem.appendChild(this.dom.table);
	this.dom.message.appendChild(this.dom.table);

	this.dom.table.className = 'messageTable';

	this.dom.tbody = document.createElement('tbody');
	this.dom.table.appendChild(this.dom.tbody);

	this.dom.row = document.createElement('tr');
	this.dom.tbody.appendChild(this.dom.row);

/* color box
	this.dom.colorBox = document.createElement('td');
	this.dom.row.appendChild(this.dom.colorBox);

	this.dom.colorBox.className = 'colorbox';
	this.dom.colorBox.style.backgroundColor = '#'+this.color;
//*/

// message box
	this.dom.messageBox = document.createElement('td');
	this.dom.row.appendChild(this.dom.messageBox);

	this.dom.messageBox.className = 'messagebox';

	this.dom.title = document.createElement('span');
	this.dom.messageBox.appendChild(this.dom.title);

	this.dom.title.appendChild(document.createTextNode(this.title));
	this.dom.title.className = 'title';

	this.dom.messageBox.appendChild(document.createElement('br'));

	this.dom.body = document.createElement('span');
	this.dom.messageBox.appendChild(this.dom.body);

	this.dom.body.appendChild(document.createTextNode(this.body));
	this.dom.body.className = 'body';


/* close box
	this.dom.closeBox = document.createElement('td');
	this.dom.row.appendChild(this.dom.closeBox);

	this.dom.closeBox.className = 'closebox';
	this.dom.closeBox.style.backgroundColor = '#FFFFFF';
	//this.dom.colorBox.style.backgroundColor = '#'+this.color;
//*/
}
});