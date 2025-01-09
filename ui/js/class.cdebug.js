/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Js debug.
 */
var jsDump;

(function() {
	var reName = /^function (\w+)/;

	function quote(str) {
		return '"' + str.toString().replace(/"/g, '\\"') + '"';
	}

	function literal(o) {
		return o + '';
	}

	function join(pre, arr, post) {
		var s = jsDump.separator(),
			base = jsDump.indent();
			inner = jsDump.indent(1);

		if (arr.join) {
			arr = arr.join(',' + s + inner);
		}
		if (!arr) {
			return pre + post;
		}

		return [pre, inner + arr, base + post].join(s);
	}

	function array(arr) {
		var i = arr.length, ret = Array(i);

		this.up();
		while (i--) {
			ret[i] = this.parse(arr[i]);
		}
		this.down();

		return join('[', ret, ']');
	}

	jsDump = {
		quote: quote,
		literal: literal,
		join: join,
		_depth_: 1,
		HTML: false, // if true, entities are escaped ( <, >, \t, space and \n )
		indentChar: '   ', // indentation unit
		multiline: true, // if true, items in a collection, are separated by a \n, else just a space.

		parse: function(obj, type) { // type is used mostly internally, you can fix a (custom) type in advance
			var parser = this.parsers[type || this.typeOf(obj)];
			type = typeof parser;

			return type == 'function' ? parser.call(this, obj) : type == 'string' ? parser : this.parsers.error;
		},

		typeOf: function(obj) {
			var type = typeof obj,
				f = 'function'; // we'll use it 3 times, save it

			return type != 'object' && type != f ? type :
				!obj ? 'null' :
				obj.exec ? 'regexp' : // some browsers (FF) consider regexps functions
				obj.getHours ? 'date' :
				obj.scrollBy ?  'window' :
				obj.nodeName == '#document' ? 'document' :
				obj.nodeName ? 'node' :
				obj.item ? 'nodelist' : // Safari reports nodelists as functions
				obj.callee ? 'arguments' :
				obj.call || obj.constructor != Array && // an array would also fall on this hack
					(obj + '').indexOf(f) != -1 ? f : // IE reports functions like alert, as objects
				'length' in obj ? 'array' :
				type;
		},

		separator: function() {
			return this.multiline ? this.HTML ? '<br />' : '\n' : this.HTML ? '&nbsp;' : ' ';
		},

		indent: function (extra) { // extra can be a number, shortcut for increasing-calling-decreasing
			if (!this.multiline) {
				return '';
			}

			var chr = this.indentChar;

			if (this.HTML) {
				chr = chr.replace(/\t/g, '   ').replace(/ /g, '&nbsp;');
			}

			return Array(this._depth_ + (extra||0)).join(chr);
		},

		up: function(a) {
			this._depth_ += a || 1;
		},

		down: function(a) {
			this._depth_ -= a || 1;
		},

		setParser: function(name, parser) {
			this.parsers[name] = parser;
		},

		// this is the list of parsers, to modify them, use jsdump.setparser
		parsers: {
			window: '[Window]',
			document: '[Document]',
			error: '[ERROR]', // when no parser is found, shouldn't happen
			unknown: '[Unknown]',
			'null': 'null',
			undefined: 'undefined',
			'function': function(fn) {
				var ret = 'function',
					name = 'name' in fn ? fn.name : (reName.exec(fn) || [])[1]; // functions never have name in IE
				if (name) {
					ret += ' ' + name;
				}
				ret += '(';
				ret = [ret, this.parse(fn, 'functionArgs'), '){'].join('');

				return join(ret, this.parse(fn, 'functionCode'), '}');
			},

			array: array,
			nodelist: array,
			arguments: array,
			key: quote, // object calls it internally, the key part of an item in a map
			functionCode: '[code]', // function calls it internally, it's the content of the function
			attribute: quote, // onode calls it internally, it's an HTML attribute value
			string: quote,
			date: quote,
			regexp: literal, // regex
			number: literal,
			'boolean': literal,

			object: function(map) {
				var ret = [];
				this.up();
				for (var key in map) {
					ret.push(this.parse(key, 'key') + ': ' + this.parse(map[key]));
				}
				this.down();

				return join('{', ret, '}');
			},

			node: function(node) {
				var open = this.HTML ? '&lt;' : '<',
					close = this.HTML ? '&gt;' : '>',
					tag = node.nodeName.toLowerCase(),
					ret = open + tag;

				for (var a in this.DOMAttrs) {
					var val = node[this.DOMAttrs[a]];
					if (val) {
						ret += ' ' + a + '=' + this.parse(val, 'attribute');
					}
				}

				return ret + close + open + '/' + tag + close;
			},

			functionArgs: function(fn) { // function calls it internally, it's the arguments part of the function
				var l = fn.length;

				if (!l) {
					return '';
				}

				var args = Array(l);
				while (l--) {
					args[l] = String.fromCharCode(97 + l); // 97 is 'a'
				}

				return ' ' + args.join(', ') + ' ';
			}
		},

		DOMAttrs: { // attributes to dump from nodes, name => realName
			id: 'id',
			name: 'name',
			'class': 'className'
		}
	};
})();

function cdump($obj) {
	console.log(jsDump.parse($obj));
}

function cdumpts(timestamp) {
	return new CDate(timestamp).getZBXDate();
}
