<?php
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


class CRedirectButtonTest extends CTagTest {

	public function constructProvider() {
		return [
			[
				['caption'],
				'<button class="button button-plain shadow ui-corner-all" type="button">caption</button>'
			],
			[
				['caption', 'http://google.com'],
				'<button class="button button-plain shadow ui-corner-all" type="button" data-url="http://google.com">caption</button>'
			],
			[
				['caption', 'http://google.com', 'Are you sure?'],
				'<button class="button button-plain shadow ui-corner-all" type="button" data-url="http://google.com" data-confirmation="Are you sure?">caption</button>'
			],
			[
				['caption', 'http://google.com', null, 'my-class'],
				'<button class="button my-class" type="button" data-url="http://google.com">caption</button>'
			],
			// caption encoding
			[
				['</button>'],
				'<button class="button button-plain shadow ui-corner-all" type="button">&lt;/button&gt;</button>'
			],
			// parameter encoding
			[
				['caption', 'url"&"'],
				'<button class="button button-plain shadow ui-corner-all" type="button" data-url="url&quot;&&quot;">caption</button>'
			],
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
	protected function createTag($caption = null, $url = null, $confirmation = null, $class = 'button-plain shadow ui-corner-all') {
		return new CRedirectButton($caption, $url, $confirmation, $class);
	}
}
