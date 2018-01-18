<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			// caption encoding
			[
				['</button>'],
				'<button type="button">&lt;/button&gt;</button>'
			],
			// parameter encoding
			[
				['caption', 'url"&"'],
				'<button type="button" data-url="url&quot;&&quot;">caption</button>'
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
	protected function createTag($caption = null, $url = null, $confirmation = null) {
		return new CRedirectButton($caption, $url, $confirmation);
	}
}
