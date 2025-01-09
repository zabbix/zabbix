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


class CSubmitTest extends CTagTest {

	public function constructProvider() {
		return [
			[
				[],
				'<button type="submit" id="submit" name="submit" value=""></button>'
			],
			[
				['my-button'],
				'<button type="submit" id="my-button" name="my-button" value=""></button>'
			],
			[
				['button[value]'],
				'<button type="submit" id="button_value" name="button[value]" value=""></button>'
			],
			[
				['button', 'caption'],
				'<button type="submit" id="button" name="button" value="caption">caption</button>'
			],
			// value encoding
			[
				['button', '</button>'],
				'<button type="submit" id="button" name="button" value="&lt;/button&gt;">&lt;/button&gt;</button>'
			],
			// parameter encoding
			[
				['button"&"'],
				'<button type="submit" id="button&quot;&amp;&quot;" name="button&quot;&amp;&quot;" value=""></button>'
			]
		];
	}
	/**
	 * @param $name
	 * @param $caption
	 * @return CSubmit
	 */
	protected function createTag($name = 'submit', $caption = '') {
		return new CSubmit($name, $caption);
	}
}
