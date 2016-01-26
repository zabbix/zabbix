<?php
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


class CSubmitButtonTest extends CTagTest {

	public function constructProvider() {
		return [
			[
				['caption'],
				'<button type="submit">caption</button>'
			],
			[
				['caption', 'button'],
				'<button type="submit" name="button">caption</button>'
			],
			[
				['caption', 'button[value]'],
				'<button type="submit" name="button[value]">caption</button>'
			],
			[
				['caption', 'button', 'value'],
				'<button type="submit" name="button" value="value">caption</button>'
			],
			[
				['caption', 'button', 'value', 'my-class'],
				'<button class="my-class" type="submit" name="button" value="value">caption</button>'
			],
			// caption encoding
			[
				['</button>'],
				'<button type="submit">&lt;/button&gt;</button>'
			],
			// parameter encoding
			[
				['caption', 'button', 'button"&"'],
				'<button type="submit" name="button" value="button&quot;&amp;&quot;">caption</button>'
			],
		];
	}
	/**
	 * @param $name
	 * @param $value
	 * @param $caption
	 * @param $class
	 *
	 * @return CSubmitButton
	 */
	protected function createTag($name = null, $value = null, $caption = null) {
		return new CSubmitButton($name, $value, $caption);
	}
}
