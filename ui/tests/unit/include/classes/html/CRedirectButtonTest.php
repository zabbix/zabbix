<?php declare(strict_types = 0);
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


class CRedirectButtonTest extends CTagTest {

	public function constructProvider() {
		return [
			[
				['caption'],
				'<button type="button">caption</button>'
			],
			[
				['caption', 'http://google.com'],
				'<button type="button" data-url="http://google.com">caption</button>'
			],
			[
				['caption', 'http://google.com', 'Are you sure?'],
				'<button type="button" data-url="http://google.com" data-confirmation="Are you sure?">caption</button>'
			],
			// CSRF token argument exists
			[
				['caption', (new CUrl('http://localhost'))->setArgument(CSRF_TOKEN_NAME, 'value')],
				'<button type="button" data-post="1" data-url="http://localhost?_csrf_token=value">caption</button>'
			],
			// caption encoding
			[
				['</button>'],
				'<button type="button">&lt;/button&gt;</button>'
			],
			// parameter encoding
			[
				['caption', 'url"&"'],
				'<button type="button" data-url="url&quot;&amp;&quot;">caption</button>'
			]
		];
	}

	/**
	 * @param $caption
	 * @param $url
	 * @param $confirmation
	 * @param $class
	 *
	 * @return CRedirectButton
	 */
	protected function createTag($caption = null, $url = null, $confirmation = null) {
		return new CRedirectButton($caption, $url, $confirmation);
	}
}
