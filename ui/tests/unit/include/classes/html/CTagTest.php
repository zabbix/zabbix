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


use PHPUnit\Framework\TestCase;

abstract class CTagTest extends TestCase {

	public abstract function constructProvider();

	/**
	 * @dataProvider constructProvider
	 *
	 * @param array $params
	 * @param string $expectedHtml
	 */
	public function testConstruct(array $params, $expectedHtml) {
		$tag = call_user_func_array([$this, 'createTag'], $params);
		$this->assertEquals($expectedHtml, (string) $tag);
	}

	/**
	 * @return CTag
	 */
	abstract protected function createTag();
}
