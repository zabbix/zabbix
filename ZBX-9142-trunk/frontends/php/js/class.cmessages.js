/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


var ZBX_MESSAGES = [];

// use this function to initialize Messaging system
function initMessages(args) {
	var messagesListId = ZBX_MESSAGES.length;
	ZBX_MESSAGES[messagesListId] = new CMessageList(messagesListId, args);
	return messagesListId;
}

var CMessageList = Class.create({
	messageListId:		0,		// reference id
	updateFrequency:	60,		// seconds
	timeoutFrequency:	10,		// seconds
	ready:				false,
	PEupdater:			null,	// PeriodicalExecuter object update
	PEtimeout:			null,	// PeriodicalExecuter object update
	lastupdate:			0,		// lastupdate timestamp
	msgcounter:			0,		// how many messages have been added
	pipeLength:			15,		// how many messages to show
	messages:			{},		// received messages
	messageList:		{},		// list of received messages
	messagePipe:		[],		// messageid pipe line
	messageLast:		{},		// last message's sourceid by caption
	effectTimeout:		1000,	// effect time out
	dom:				{},		// dom object links
	sounds: {					// sound playback settings
		'priority':	0,			// max new message priority
		'sound':	null,		// sound to play
		'repeat':	1,			// loop sound for 1,3,5,10 .. times
		'mute':		0,			// mute alarms
		'timeout':	0
	},

	initialize: function(messagesListId, args) {
		this.messageListId = messagesListId;
		this.dom = {};
		this.messages = {};
		this.messageList = {};
		this.messageLast = {};
		this.updateSettings();
		this.createContainer();

		addListener(this.dom.closeAll, 'click', this.closeAllMessages.bindAsEventListener(this));
		addListener(this.dom.snooze, 'click', this.stopSound.bindAsEventListener(this));
		addListener(this.dom.mute, 'click', this.mute.bindAsEventListener(this));

		jQuery(this.dom.container).draggable({
			handle: this.dom.header,
			axis: 'y',
			containment: [0, 0, 0, 1600]
		});
	},

	start: function() {
		this.stop();

		if (is_null(this.PEupdater)) {
			this.ready = true;
			this.lastupdate = 0;
			this.PEupdater = new PeriodicalExecuter(this.getServerMessages.bind(this), this.updateFrequency);
			this.getServerMessages();
		}

		if (is_null(this.PEtimeout)) {
			this.PEtimeout = new PeriodicalExecuter(this.timeoutMessages.bind(this), this.timeoutFrequency);
			this.timeoutMessages();
		}
	},

	stop: function() {
		if (!is_null(this.PEupdater)) {
			this.PEupdater.stop();
		}
		if (!is_null(this.PEtimeout)) {
			this.PEtimeout.stop();
		}
		this.PEupdater = null;
		this.PEtimeout = null;
	},

	setSettings: function(settings) {
		this.sounds.repeat = settings['sounds.repeat'];
		this.sounds.mute = settings['sounds.mute'];
		if (this.sounds.mute == 1) {
			this.dom.mute.className = 'iconmute menu_icon shadow';
		}

		if (settings.enabled != 1) {
			this.stop();
		}
		else {
			this.start();
		}
	},

	updateSettings: function() {
		var rpcRequest = {
			'method': 'message.settings',
			'params': {},
			'onSuccess': this.setSettings.bind(this),
			'onFailure': function() {
				throw('Messages Widget: settings request failed.');
			}
		};
		new RPC.Call(rpcRequest);
	},

	addMessage: function(newMessage) {
		newMessage = newMessage || {};

		while (isset(this.msgcounter, this.messageList)) {
			this.msgcounter++;
		}

		if (this.messagePipe.length > this.pipeLength) {
			var lastMessageId = this.messagePipe.shift();
			this.closeMessage(lastMessageId);
		}

		this.messagePipe.push(this.msgcounter);
		newMessage.messageid = this.msgcounter;

		this.messageList[this.msgcounter] = new CMessage(this, newMessage);
		this.messageLast[this.messageList[this.msgcounter].caption] = {
			'caption': this.messageList[this.msgcounter].caption,
			'sourceid': this.messageList[this.msgcounter].sourceid,
			'time': this.messageList[this.msgcounter].time,
			'messageid': this.messageList[this.msgcounter].messageid
		};

		jQuery(this.dom.container).fadeTo('fast', 0.9);

		return this.messageList[this.msgcounter];
	},

	mute: function(e) {
		e = e || window.event;
		var icon = Event.element(e);
		var newClass = switchElementClass(icon, 'iconmute', 'iconsound');

		if (newClass == 'iconmute') {
			var action = 'message.mute';
			this.sounds.mute = 1;

			this.stopSound();
		}
		else {
			var action = 'message.unmute';
			this.sounds.mute = 0;

			this.playSound();
		}

		var rpcRequest = {
			'method': action,
			'params': {},
			'onFailure': function() {
				throw('Messages Widget: mute request failed.');
			}
		};
		new RPC.Call(rpcRequest);
	},

	playSound: function() {
		if (this.sounds.mute != 0) {
			return true;
		}

		this.stopSound();
		this.sounds.priority = 0;
		this.sounds.sound = null;

		for (var i = 0; i < this.messages.length; i++) {
			var message = this.messages[i];

			if (message.type != 1 && message.type != 3) {
				continue;
			}

			if (message.priority >= this.sounds.priority) {
				this.sounds.priority = message.priority;
				this.sounds.sound = message.sound;
				this.sounds.timeout = message.timeout;
			}
		}

		this.ready = true;

		if (this.sounds.sound !== null) {
			if (this.sounds.repeat == 1) {
				AudioControl.playOnce(this.sounds.sound);
			}
			else if (this.sounds.repeat > 0) {
				AudioControl.playLoop(this.sounds.sound, this.sounds.repeat);
			}
			else {
				AudioControl.playLoop(this.sounds.sound, this.sounds.timeout);
			}
		}
	},

	stopSound: function() {
		if (!is_null(this.sounds.sound)) {
			AudioControl.stop();
		}
	},

	closeMessage: function(messageid, withEffect) {
		if (!isset(messageid, this.messageList)) {
			return true;
		}

		AudioControl.stop();

		if (withEffect) {
			this.messageList[messageid].remove();
		}
		else {
			this.messageList[messageid].close();
		}

		try {
			delete(this.messageList[messageid]);
		}
		catch(e) {
			this.messageList[messageid] = null;
		}

		this.messagePipe = [];
		for (var messageid in this.messageList) {
			this.messagePipe.push(messageid);
		}

		if (this.messagePipe.length < 1) {
			this.messagePipe = [];
			this.messageList = {};
			setTimeout(Element.hide.bind(Element, this.dom.container), this.effectTimeout);
		}
	},

	closeAllMessages: function() {
		var lastMessageId = this.messagePipe.pop();
		var rpcRequest = {
			'method': 'message.closeAll',
			'params': {
				'caption': this.messageList[lastMessageId].caption,
				'sourceid': this.messageList[lastMessageId].sourceid,
				'time': this.messageList[lastMessageId].time,
				'messageid': this.messageList[lastMessageId].messageid
			},
			'onFailure': function(resp) {
				throw('Messages Widget: message request failed.');
			}
		};

		new RPC.Call(rpcRequest);

		jQuery(this.dom.container).slideUp(this.effectTimeout);

		var count = 0;
		var effect = false;
		for (var messageid in this.messageList) {
			if (empty(this.messageList[messageid])) {
				continue;
			}
			if (!effect) {
				this.closeMessage(this, messageid, effect);
			}
			else {
				setTimeout(this.closeMessage.bind(this, messageid, effect), count * this.effectTimeout * 0.5);
			}
			count++;
		}

		AudioControl.stop();
	},

	timeoutMessages: function() {
		var now = parseInt(new Date().getTime() / 1000);
		var timeout = 0;

		for (var messageid in this.messageList) {
			if (empty(this.messageList[messageid])) {
				continue;
			}
			var msg = this.messageList[messageid];
			if ((msg.time + parseInt(msg.timeout, 10)) < now) {
				setTimeout(this.closeMessage.bind(this, messageid, true), 500 * timeout);
				timeout++;
			}
		}
	},

	getServerMessages: function() {
		var now = parseInt(new Date().getTime() / 1000);
		if (!this.ready || ((this.lastupdate + this.updateFrequency) > now)) {
			return true;
		}
		this.ready = false;
		var rpcRequest = {
			'method': 'message.get',
			'params': {
				'messageListId': this.messageListId,
				'messageLast': this.messageLast
			},
			'onSuccess': this.serverRespond.bind(this),
			'onFailure': function() {
				throw('Messages Widget: message request failed.');
			}
		};
		new RPC.Call(rpcRequest);
		this.lastupdate = now;
	},

	serverRespond: function(messages) {
		for (var i = 0; i < messages.length; i++) {
			this.addMessage(messages[i]);
		}

		this.messages = messages;
		this.playSound();
		this.ready = true;
	},

	createContainer: function() {
		this.dom.container = $('zbx_messages');
		if (!empty(this.dom.container)) {
			return false;
		}

		var doc_body = document.getElementsByTagName('body')[0];
		if (empty(doc_body)) {
			return false;
		}

		this.dom.container = document.createElement('div');
		doc_body.appendChild(this.dom.container);

		// container
		this.dom.container.setAttribute('id', 'zbx_messages');
		this.dom.container.className = 'messagecontainer';
		$(this.dom.container).hide();

		// header
		this.dom.header = document.createElement('div');
		this.dom.container.appendChild(this.dom.header);
		this.dom.header.className = 'header';

		// text
		this.dom.caption = document.createElement('h3');
		this.dom.caption.className = 'headertext move';
		this.dom.caption.appendChild(document.createTextNode(locale['S_MESSAGES']));
		this.dom.header.appendChild(this.dom.caption);

		// controls
		this.dom.controls = document.createElement('div');
		this.dom.header.appendChild(this.dom.controls);
		this.dom.controls.className = 'controls';

		// buttons list
		this.dom.controlList = new CList().node;
		this.dom.controls.appendChild(this.dom.controlList);
		this.dom.controlList.style.cssFloat = 'right';

		// snooze
		this.dom.snooze = document.createElement('div');
		this.dom.snooze.setAttribute('title', locale['S_SNOOZE']);
		this.dom.snooze.className = 'iconsnooze menu_icon shadow';
		this.dom.controlList.addItem(this.dom.snooze, 'linear');

		// mute
		this.dom.mute = document.createElement('div');
		this.dom.mute.setAttribute('title', locale['S_MUTE'] + '/' + locale['S_UNMUTE']);
		this.dom.mute.className = 'iconsound menu_icon shadow';
		this.dom.controlList.addItem(this.dom.mute, 'linear');

		// close all
		this.dom.closeAll = document.createElement('div');
		this.dom.closeAll.setAttribute('title', locale['S_CLEAR']);
		this.dom.closeAll.className = 'iconclose menu_icon shadow';
		this.dom.controlList.addItem(this.dom.closeAll, 'linear');

		// message list
		this.dom.list = new CList().node;
		this.dom.container.appendChild(this.dom.list);
	}
});

var CMessage = Class.create({
	list:		null,		// link to message list containing this message
	messageid:	null,		// msg id
	caption:	'unknown',	// msg caption (events, actions, infos.. e.t.c.)
	sourceid:	null,		// caption + sourceid = identifier for server
	type:		0,			// 1 - sound, 2 - text, 3 - sound & text, 4 - notdefined
	priority:	0,			// msg priority ASC
	sound:		null,		// msg sound
	color:		'ffffff',	// msg color
	time:		0,			// msg time arrival
	title:		'No title',	// msg header
	body:		['No text'],// msg details
	timeout:	60,			// msg timeout
	dom:		{},			// msg dom links

	initialize: function(messageList, message) {
		this.messageid = message.messageid;
		this.dom = {};
		this.list = messageList;

		for (var key in message) {
			if (empty(message[key]) || !isset(key, this)) {
				continue;
			}
			if (key == 'time') {
				this[key] = parseInt(message[key]);
			}
			else {
				this[key] = message[key];
			}
		}
		this.createMessage();
	},

	close: function() {
		$(this.dom.listItem).remove();
		this.dom = {};
	},

	remove: function() {
		jQuery(this.dom.listItem).slideUp(this.list.effectTimeout);
		jQuery(this.dom.listItem).fadeOut(this.list.effectTimeout);
		setTimeout(this.close.bind(this), this.list.effectTimeout);
	},

	createMessage: function() {
		// message
		this.dom.message = document.createElement('div');
		this.dom.message.className = 'message';
		this.dom.message.style.backgroundColor = '#' + this.color;

		// li
		this.dom.listItem = new CListItem(this.dom.message, 'listItem').node;
		$(this.list.dom.list).insert({'top': this.dom.listItem});

		// message box
		this.dom.messageBox = document.createElement('div');
		this.dom.message.appendChild(this.dom.messageBox);
		this.dom.messageBox.className = 'messagebox';

		// title
		this.dom.title = document.createElement('span');
		this.dom.messageBox.appendChild(this.dom.title);
		$(this.dom.title).update(BBCode.Parse(this.title));
		this.dom.title.className = 'title';

		// body
		if (!is_array(this.body)) {
			this.body = [this.body];
		}
		for (var i = 0; i < this.body.length; i++) {
			if (!isset(i, this.body) || empty(this.body[i])) {
				continue;
			}
			this.dom.messageBox.appendChild(document.createElement('br'));
			this.dom.body = document.createElement('span');
			this.dom.messageBox.appendChild(this.dom.body);
			$(this.dom.body).update(BBCode.Parse(this.body[i]));
			this.dom.body.className = 'body';
		}
	},

	show: function() {},
	notify: function() {}
});

var CNode = Class.create({
	node: null, // main node (ul)

	initialize: function(nodeName) {
		this.node = document.createElement(nodeName);
		return this.node;
	},

	addItem: function(item) {
		if (is_object(item)) {
			this.node.appendChild(item);
		}
		else if (is_string(item)) {
			this.node.appendChild(documect.createTextNode(item));
		}
		else {
			return true;
		}
	},

	setClass: function(className) {
		className = className || '';
		this.node.className = className;
	}
});

var CList = Class.create(CNode, {
	items: [],

	initialize: function($super, className) {
		className = className || '';
		$super('ul');
		this.setClass(this.classNames);
		Object.extend(this.node, this);
	},

	addItem: function($super, item, className) {
		className = className || '';
		if (!is_object(item, CListItem)) {
			item = new CListItem(item, className).node;
		}
		$super(item);
		this.items.push(item);
	}
});

var CListItem = Class.create(CNode, {
	items: [],

	initialize: function($super, item, className) {
		className = className || '';
		item = item || null;
		$super('li');
		this.setClass(className);
		this.addItem(item);
	},

	addItem: function($super, item) {
		$super(item);
		this.items.push(item);
	}
});
