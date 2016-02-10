/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

var CMessageList = Class.create(CDebug, {
	messageListId:		0,		// reference id
	updateFrequency:	60,		// seconds
	timeoutFrequency:	10,		// seconds
	ready:				false,
	PEupdater:			null,	// PeriodicalExecuter object update
	PEtimeout:			null,	// PeriodicalExecuter object update
	lastupdate:			0,		// lastupdate timestamp
	msgcounter:			0,		// how many messages have been added
	pipeLength:			15,		// how many messages to show
	messageList:		{},		// list of received messages
	messagePipe:		[],		// messageid pipe line
	messageLast:		{},		// last message's sourceid by caption
	messageSettings:	{},		// message settings object. Used to get messageSettings.timeout when adding new message
	effectTimeout:		1000,	// effect time out
	dom:				{},		// dom object links
	sounds: {					// sound playback settings
		'priority':	0,			// max new message priority
		'sound':	null,		// sound to play
		'repeat':	1,			// loop sound for 1,3,5,10 .. times
		'mute':		0,			// mute alarms
		'timeout':	0
	},

	initialize: function($super, messagesListId, args) {
		this.messageListId = messagesListId;
		$super('CMessageList[' + messagesListId + ']');
		this.dom = {};
		this.messageList = {};
		this.messageLast = {};
		this.updateSettings();
		this.createContainer();

		addListener(this.dom.closeAll, 'click', this.closeAllMessages.bindAsEventListener(this));
		addListener(this.dom.snooze, 'click', this.stopSound.bindAsEventListener(this));
		addListener(this.dom.mute, 'click', this.mute.bindAsEventListener(this));

		jQuery(this.dom.container).draggable({
			handle: [this.dom.caption, this.dom.move],
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
		this.debug('setSettings');
		this.messageSettings = settings;
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
		this.debug('updateSettings');
		var rpcRequest = {
			'method': 'message.settings',
			'params': {},
			'onSuccess': this.setSettings.bind(this),
			'onFailure': function() {
				zbx_throw('Messages Widget: settings request failed.');
			}
		};
		new RPC.Call(rpcRequest);
	},

	addMessage: function(newMessage) {
		this.debug('addMessage');
		newMessage = newMessage || {};

		var messages = cookie.read('messages'),
			message_exists = false,
			time_till = null,
			msgcount = 0;

		if (messages != null) {
			/*
			 * For some reason JSON.stringify creates escaped strings like "[{\"sourceid\": \"278513\" ...}]"
			 * so string is parsed twice till it is converted to an object.
			 */
			messages = JSON.parse(messages);
			messages = JSON.parse(messages);

			while (isset(msgcount, messages)) {
				msgcount++;
			}

			for (var i = 0; i < msgcount; i++) {
				if (newMessage.sourceid == messages[i].sourceid) {
					message_exists = true;
					time_till = messages[i].time_till;
					break;
				}
			}
		}

		if (message_exists) {
			newMessage.time_till = time_till;
		}
		else {
			newMessage.time_till = parseInt(new Date().getTime() / 1000) + parseInt(this.messageSettings.timeout);
		}

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

		var messages_to_save = [];

		for (var messageid in this.messageList) {
			messages_to_save[messages_to_save.length] = {
				'sourceid': this.messageList[messageid].sourceid,
				'time': this.messageList[messageid].time,
				'time_till': this.messageList[messageid].time_till
			}
		}

		cookie.create('messages', JSON.stringify(messages_to_save));

		return this.messageList[this.msgcounter];
	},

	mute: function(e) {
		this.debug('mute');
		e = e || window.event;
		var icon = Event.element(e);
		var newClass = switchElementsClass(icon, 'iconmute', 'iconsound');

		if (newClass == 'iconmute') {
			var action = 'message.mute';
			this.sounds.mute = 1;
		}
		else {
			var action = 'message.unmute';
			this.sounds.mute = 0;
		}

		var rpcRequest = {
			'method': action,
			'params': {},
			'onFailure': function() {
				zbx_throw('Messages Widget: mute request failed.');
			}
		};
		new RPC.Call(rpcRequest);
		this.stopSound(e);
	},

	playSound: function(messages) {
		this.debug('playSound');

		if (this.sounds.mute != 0) {
			return true;
		}
		this.stopSound();
		this.sounds.priority = 0;
		this.sounds.sound = null;

		for (var i = 0; i < messages.length; i++) {
			var message = messages[i];
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
		if (!is_null(this.sounds.sound)) {
			if (this.sounds.repeat == 1) {
				AudioList.play(this.sounds.sound);
			}
			else if (this.sounds.repeat > 1) {
				AudioList.loop(this.sounds.sound, {'seconds': this.sounds.repeat});
			}
			else {
				AudioList.loop(this.sounds.sound, {'seconds': this.sounds.timeout});
			}
		}
	},

	stopSound: function() {
		this.debug('stopSound');

		if (!is_null(this.sounds.sound)) {
			AudioList.stop(this.sounds.sound);
		}
	},

	closeMessage: function(messageid, withEffect) {
		this.debug('closeMessage', messageid);

		if (!isset(messageid, this.messageList)) {
			return true;
		}

		AudioList.stop(this.messageList[messageid].sound);
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
		else {
			var messages_to_save = [];

			for (var messageid in this.messageList) {
				messages_to_save[messages_to_save.length] = {
					'sourceid': this.messageList[messageid].sourceid,
					'time': this.messageList[messageid].time,
					'time_till': this.messageList[messageid].time_till
				}
			}

			cookie.create('messages', JSON.stringify(messages_to_save));
		}
	},

	closeAllMessages: function() {
		this.debug('closeAllMessages');
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
				zbx_throw('Messages Widget: message request failed.');
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

		this.stopSound();
	},

	timeoutMessages: function() {
		this.debug('timeoutMessages');
		var now = parseInt(new Date().getTime() / 1000),
			timeout = 0;

		for (var messageid in this.messageList) {
			if (empty(this.messageList[messageid])) {
				continue;
			}
			var msg = this.messageList[messageid];

			if (now >= msg.time_till) {
				setTimeout(this.closeMessage.bind(this, messageid, true), 500 * timeout);
				timeout++;
			}
		}
	},

	getServerMessages: function() {
		this.debug('getServerMessages');

		var now = parseInt(new Date().getTime() / 1000),
			messages = cookie.read('messages'),
			messages_to_save = [],
			msgcount = 0,
			messageids = [],
			last_event_time = 0;

		if (messages != null) {
			/*
			 * For some reason JSON.stringify creates escaped strings like "[{\"sourceid\": \"278513\" ...}]"
			 * so string is parsed twice till it is converted to an object.
			 */
			messages = JSON.parse(messages);
			messages = JSON.parse(messages);

			while (isset(msgcount, messages)) {
				msgcount++;
			}
		}

		if (msgcount > 0) {
			/*
			 * A code "for (var messageid in messages)" is unrealiable, since messages[0] is the object we need,
			 * but it also contains non-empty messages[1] (and more) with function calls and it messeses with iteration.
			 */
			for (var i = 0; i < msgcount; i++) {
				// Find the time for last event.
				if (messages[i].time > last_event_time) {
					last_event_time = messages[i].time;
				}

				/*
				 * Check if the message still has some time to live. If so, re-save those messages cookie
				 * and gather event IDs. Those IDs will be used in RPC call to get older messages.
				 * In the same RPC call, we shall get new messages that haven't been displayed yet.
				 */
				if (now < messages[i].time_till) {
					messageids[messageids.length] = messages[i].sourceid;
					messages_to_save[messages_to_save.length] = messages[i];
				}
			}

			if (messages_to_save.length > 0) {
				cookie.create('messages', JSON.stringify(messages_to_save));
			}
			else {
				cookie.erase('messages');
			}
		}
		else {
			cookie.erase('messages');
		}

		if (!this.ready || ((this.lastupdate + this.updateFrequency) > now)) {
			return true;
		}
		this.ready = false;

		var messageLast = {};

		/*
		 * If page has been fully refreshed, get last event based on cookie if possible. Even after the cookie has beed
		 * deleted (while the page has not been refreshed yet), we still know when was the last time event happened.
		 * If the page was not refreshed, use the original object.
		 */
		if (this.lastupdate == 0 && last_event_time > 0) {
			messageLast = {'events': {'time': last_event_time}};
		}
		else {
			messageLast = this.messageLast;
		}

		var rpcRequest = {
			'method': 'message.get',
			'params': {
				'messageListId': this.messageListId,
				'messageLast': messageLast,
				'lastupdate': this.lastupdate,
				'eventids': messageids.toString()
			},
			'onSuccess': this.serverRespond.bind(this),
			'onFailure': function() {
				zbx_throw('Messages Widget: message request failed.');
			}
		};
		new RPC.Call(rpcRequest);
		this.lastupdate = now;
	},

	serverRespond: function(messages) {
		this.debug('serverRespond');
		for (var i = 0; i < messages.length; i++) {
			this.addMessage(messages[i]);
		}
		this.playSound(messages);
		this.ready = true;
	},

	createContainer: function() {
		this.debug('createContainer');
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

var CMessage = Class.create(CDebug, {
	list:		null,		// link to message list containing this message
	messageid:	null,		// msg id
	caption:	'unknown',	// msg caption (events, actions, infos.. e.t.c.)
	sourceid:	null,		// caption + sourceid = identifier for server
	type:		0,			// 1 - sound, 2 - text, 3 - sound & text, 4 - notdefined
	priority:	0,			// msg priority ASC
	sound:		null,		// msg sound
	color:		'ffffff',	// msg color
	time:		0,			// event time
	time_till:	0,			// how long till the message will become outdated
	title:		'No title',	// msg header
	body:		['No text'],// msg details
	timeout:	60,			// msg timeout
	dom:		{},			// msg dom links

	initialize: function($super, messageList, message) {
		this.messageid = message.messageid;
		$super('CMessage[' + this.messageid + ']');
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
		this.debug('close');
		$(this.dom.listItem).remove();
		this.dom = {};
	},

	remove: function() {
		this.debug('remove');
		jQuery(this.dom.listItem).slideUp(this.list.effectTimeout);
		jQuery(this.dom.listItem).fadeOut(this.list.effectTimeout);
		setTimeout(this.close.bind(this), this.list.effectTimeout);
	},

	createMessage: function() {
		this.debug('createMessage');

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
