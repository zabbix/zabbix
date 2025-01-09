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


class CButtonTest extends CTagTest {

	public function constructProvider() {
		return [
			[
				[],
				'<button type="button" id="button" name="button"></button>'
			],
			[
				['my-button'],
				'<button type="button" id="my-button" name="my-button"></button>'
			],
			[
				['button[value]'],
				'<button type="button" id="button_value" name="button[value]"></button>'
			],
			[
				['button', 'caption'],
				'<button type="button" id="button" name="button">caption</button>'
			],
			// value encoding
			[
				['button', '</button>'],
				'<button type="button" id="button" name="button">&lt;/button&gt;</button>'
			],
			// parameter encoding
			[
				['button"&"'],
				'<button type="button" id="button&quot;&amp;&quot;" name="button&quot;&amp;&quot;"></button>'
			]
		];
	}

	public function testSetEnabled() {
		$button = $this->createTag();
		$button->setEnabled(false);
		$this->assertEquals(
			'<button type="button" id="button" name="button" disabled="disabled"></button>',
			(string) $button
		);
	}

	/**
	 * @param $name
	 * @param $caption
	 * @param $action
	 * @param $class
	 * @return CButton
	 */
	protected function createTag($name = 'button', $caption = '') {
		return new CButton($name, $caption);
	}
}
