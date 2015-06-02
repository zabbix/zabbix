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
				'<button type="button"></button>'
			],
			[
				['caption'],
				'<button type="button">caption</button>'
			],
			[
				['caption', 'my-class'],
				'<button class="my-class" type="button">caption</button>'
			],
			// value encoding
			[
				['</button>'],
				'<button type="button">&lt;/button&gt;</button>'
			],
		];
	}

	public function testSetEnabled() {
		$button = $this->createTag();
		$button->setEnabled(false);
		$this->assertEquals(
			'<button type="button" disabled="disabled"></button>',
			(string) $button
		);
	}

	public function testMain() {
		$button = $this->createTag();
		$button->main();
		$this->assertEquals(
			'<button class="main" type="button"></button>',
			(string) $button
		);
	}

	/**
	 * @param $caption
	 * @param $class
	 *
	 * @return CSimpleButton
	 */
	protected function createTag($caption = '') {
		return new CSimpleButton($caption);
	}
}
