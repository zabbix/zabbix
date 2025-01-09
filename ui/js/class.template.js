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


/*
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
 */

class Template {

	/**
	 * @type {string}
	 */
	#template;

	/**
	 * @param {string} template
	 */
	constructor(template) {
		this.#template = template;
	}

	/**
	 * Fill template with data defined in object.
	 *
	 * @param {Object} data
	 *
	 * @return {string}
	 */
	evaluate(data) {
		let template = this.#template;
		let result = '';

		while (template.length > 0) {
			const match = template.match(/(^|.|\r|\n)(#\{(.*?)\})/);

			if (match) {
				result += template.substring(0, match.index);
				result += this.#match(match, data);
				template = template.substring(match.index + match[0].length);
			}
			else {
				result += template;
				break;
			}
		}

		return result;
	}

	/**
	 * Fill template with data defined in object and return as HTMLElement.
	 *
	 * @param {Object} data
	 *
	 * @return {HTMLElement}
	 */
	evaluateToElement(data = {}) {
		const template = document.createElement('template');

		template.innerHTML = this.evaluate(data);

		return template.content.firstElementChild;
	}

	/**
	 * Helper function called when match is found in template.
	 *
	 * @param {array}  match  Result of regex matching.
	 * @param {Object} data   Object containing data.
	 *
	 * @return {string}
	 */
	#match(match, data) {
		if (data === undefined || data === null) {
			return `${match[1]}`;
		}

		const before = match[1] || '';

		if (before === '\\') {
			return match[2];
		}

		let expr = match[3];

		const escape = expr.substring(0, 1) !== '*';

		if (!escape) {
			expr = expr.substring(1);
		}

		const pattern = /^([^.[]+|\[((?:.*?[^\\])?)\])(\.|\[|$)/;

		match = pattern.exec(expr);

		if (match === null) {
			return before;
		}

		while (match !== null) {
			const comp = match[1].substring(0, 1) === '[' ? match[2].replace(/\\\\]/g, ']') : match[1];

			data = data[comp];
			if (data === undefined || data === null || match[3] === '') {
				break;
			}

			expr = expr.substring('[' === match[3] ? match[1].length : match[0].length);
			match = pattern.exec(expr);
		}

		data = data === undefined || data === null ? '' : `${data}`;

		return before + (escape ? escapeHtml(data) : data);
	}
}
