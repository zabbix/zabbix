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

var RPC = {
	'_rpcurl': 'jsrpc.php?output=json-rpc', // rpc url
	'_callid': 0, // rpc request id
	'_auth': null, // authentication hash

	auth: function(auth_) {
		if (is_null(this._auth)) {
			this._auth = cookie.read('zbx_sessionid');
		}

		if ('undefined' == typeof(url_)) {
			return this._auth;
		}
		else {
			this._auth = auth_;
		}
	},

	callid: function() {
		this._callid++;
		return this._callid;
	},

	rpcurl: function(rpcurl_) {
		if ('undefined' == typeof(rpcurl_)) {
			return this._rpcurl;
		}
		else {
			this._rpcurl = rpcurl_;
		}
	}
};

RPC.Base = Class.create({
	'userParams':	{},		// user OPtions
	'auth':			null,	// authentication hash
	'callid':		0,		// rpc request id
	'debug_status':	0,		// debug status: 0 - off, 1 - on, 2 - SDI;
	'debug_info':	'',		// debug string
	'debug_prev':	'',		// don't log repeated fnc

	initialize: function(userParams) {
		this.userParams = {
			'method': null,
			'params': {},
			'notification': 0,
			'request': {},
			'onSuccess': function() {},
			'onFailure': function() {}
		};

		Object.extend(this.userParams, userParams || {});

		this.callid = RPC.callid();
		this.auth = RPC.auth();
	},

	debug: function(fnc_name, id) {
		if (this.debug_status) {
			var str = 'RPC.Call[' + RPC.id + '].' + fnc_name;
			if (typeof(id) != 'undefined') {
				str += ' :' + id;
			}

			this.debug_info += str + '\n';

			if (this.debug_status == 2) {
				SDI(str);
			}

			this.debug_prev = str;
		}
	}
});

RPC.Call = Class.create(RPC.Base, {
	initialize: function($super, userParams) {
		$super(userParams);
		this.call();
	},

	call: function() {
		this.debug('call');

		var header = {
			'Content-type': 'application/json-rpc'
		};

		var body = {
			'jsonrpc': '2.0',
			'method': this.userParams.method,
			'params': this.userParams.params,
			'auth': this.auth
		};

		var request = {
			'requestHeaders': header
		};

		if (this.userParams.notification == 0) {
			body.id = this.callid;
			request.onSuccess = this.processRespond.bind(this);
			request.onFailure = this.processError.bind(this);
		}

		Object.extend(request, this.userParams.request);
		request.postBody = Object.toJSON(body),

		new Ajax.Request(RPC.rpcurl(), request);
	},

	processRespond: function(resp){
		this.debug('processRespond');

		var isError = this.processError(resp);
		if (isError) {
			return false;
		}

		if (isset('onSuccess', this.userParams)) {
			this.userParams.onSuccess(resp.responseJSON.result);
		}

		return true;
	},

	processError: function(resp) {
		this.debug('error');

		// json request failed or server responded with incorrect json
		if (is_null(resp) || !isset('responseJSON', resp)) {
			throw('RPC call [' + this.userParams.method + '] request failed');
		}

		// json have wrong header or no respond at all
		if (empty(resp.responseJSON)) {
			throw('RPC: Server call [' + this.userParams.method + '] responded with incorrect JSON.');
		}

		// rpc responded with error || with incorrect json
		if (isset('error', resp.responseJSON) && isset('onFailure', this.userParams)) {
			this.userParams.onFailure(resp.responseJSON.error);
			return true;
		}
		else if (!isset('result', resp.responseJSON)) {
			throw('RPC: Server call [' + this.userParams.method + '] responded with incorrect JSON.');
		}

		return false;
	}
});
