/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

/**
 * Template class implements token replacement logic for strings containing HTML and tokens.
 *
 * The following format is used for tokens: #{token_name}.
 * When found in template String, token will be replaced with value of the key "token_name" of object passed to
 * evaluate function. All occurrences of tokens are replaced by values from object with HTML entities escaped.
 * Token name should be prefixed with character '*' to avoid escaping of HTML entities (for example, #{*test}).
 * Backslash character (\\) can be used to escape token (such tokens will not be affected).
 *
 * Nested object properties could be used in templated by using square bracket token syntax (for example, #{a[b][c]}).
 * Previous example will look for nested value a->b->c ({'a': {'b': {'c': 'value'}}}).
 *
 * @param {string} template    template string
 *
 * @return {Template}
 */
var Template = function(template) {
	this.template = template;
};

Template.prototype = {
	/**
	 * Helper function called when match is found in template.
	 *
	 * @param {array}  match     result of regex matching
	 * @param {object} object    object containing data
	 *
	 * @return {string}
	 */
	onMatch: function (match, object) {
		if (object == null) {
			return match[1] + '';
		}

		var before = match[1] || '';
		if (before == '\\') {
			return match[2];
		}

		var ctx = object,
			expr = match[3],
			escape = (expr.substring(0, 1) !== '*');

		if(!escape) {
			expr = expr.substring(1);
		}

		var pattern = /^([^.[]+|\[((?:.*?[^\\])?)\])(\.|\[|$)/;
		match = pattern.exec(expr);
		if (match == null) {
			return before;
		}

		while (match != null) {
			var comp = match[1].substring(0, 1) === '[' ? match[2].replace(/\\\\]/g, ']') : match[1];

			ctx = ctx[comp];
			if (null == ctx || '' == match[3]) {
				break;
			}

			expr = expr.substring('[' == match[3] ? match[1].length : match[0].length);
			match = pattern.exec(expr);
		}

		ctx = '' + (((typeof ctx === 'undefined') || (ctx === null)) ? '' : ctx);
		return before + (escape ? ctx.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
				.replace(/\"/g,'&quot;').replace(/\'/g,'&apos;') : ctx);
	},

	/**
	 * Fill template with data defined in object.
	 *
	 * @param {object} object    object containing data
	 *
	 * @return {string}
	 */
	evaluate: function(object) {
		var result = '',
			source = this.template;

		while (source.length > 0) {
			var match = source.match(/(^|.|\r|\n)(#\{(.*?)\})/);
			if (match) {
				result += source.substring(0, match.index);
				result += this.onMatch(match, object);
				source = source.substring(match.index + match[0].length);
			}
			else {
				result += source;
				break;
			}
		}

		return result;
	}
};
