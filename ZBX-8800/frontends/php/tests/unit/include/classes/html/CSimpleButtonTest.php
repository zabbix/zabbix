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


class CSimpleButtonTest extends CTagTest {

	public function constructProvider() {
		return [
			[
				[],
				'<button class="button button-plain shadow ui-corner-all" type="button"></button>'
			],
			[
				['caption'],
				'<button class="button button-plain shadow ui-corner-all" type="button">caption</button>'
			],
			[
				['caption', 'my-class'],
				'<button class="button my-class" type="button">caption</button>'
			],
			// value encoding
			[
				['</button>'],
				'<button class="button button-plain shadow ui-corner-all" type="button">&lt;/button&gt;</button>'
			],
		];
	}

	public function testSetEnabled() {
		$button = $this->createTag();
		$button->setEnabled(false);
		$this->assertEquals(
			'<button class="button button-plain shadow ui-corner-all" type="button" disabled="disabled"></button>',
			(string) $button
		);
	}

	public function testMain() {
		$button = $this->createTag();
		$button->main();
		$this->assertEquals(
			'<button class="button main button-plain shadow ui-corner-all" type="button"></button>',
			(string) $button
		);
	}

	public function testSetButtonStyle() {
		$button = $this->createTag();
		$button->setButtonClass('my-button');
		$this->assertEquals(
			'<button class="button my-button" type="button"></button>',
			(string) $button
		);

		// test class reset
		$button = $this->createTag('', 'my-button');
		$button->setButtonClass(null);
		$this->assertEquals(
			'<button class="button" type="button"></button>',
			(string) $button
		);
	}

	/**
	 * @param $caption
	 * @param $class
	 *
	 * @return CSimpleButton
	 */
	protected function createTag($caption = '', $class = 'button-plain shadow ui-corner-all') {
		return new CSimpleButton($caption, $class);
	}
}
