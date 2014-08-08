<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


abstract class CTagTest extends PHPUnit_Framework_TestCase {

	abstract public function itemProvider();

	/**
	 * @dataProvider itemProvider
	 *
	 * @param $items
	 * @param $expectedResult
	 */
	public function testContruct($items, $expectedResult) {
		$tag = $this->createTag($items);
		$this->assertEquals($expectedResult, (string) $tag);
	}

	/**
	 * @dataProvider itemProvider
	 *
	 * @param $items
	 * @param $expectedResult
	 */
	public function testAddItems($items, $expectedResult) {
		$tag = $this->createTag();
		$tag->addItem($items);
		$this->assertEquals($expectedResult, (string) $tag);
	}

	abstract public function testClass();
	abstract public function testId();

	/**
	 * @param mixed $items
	 * @param string $class
	 * @param string $id
	 *
	 * @return CTag
	 */
	abstract public function createTag($items = null, $class = null, $id = null);
}
