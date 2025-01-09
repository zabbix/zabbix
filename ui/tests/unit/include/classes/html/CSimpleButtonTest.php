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
			// value encoding
			[
				['</button>'],
				'<button type="button">&lt;/button&gt;</button>'
			]
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
