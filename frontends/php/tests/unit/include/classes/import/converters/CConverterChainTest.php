<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CConverterChainTest extends PHPUnit_Framework_TestCase {

	public function testConvertFullChain() {
		$chain = new CConverterChain();
		$chain->addConverter('one', $this->createMockConverter(1, 2));
		$chain->addConverter('two', $this->createMockConverter(2, 3));
		$chain->addConverter('three', $this->createMockConverter(3, 4));

		$result = $chain->convert(1, 'one');
		$this->assertEquals(4, $result);
	}

	public function testConvertPartialChain() {
		$chain = new CConverterChain();
		$chain->addConverter('one', $this->createMockConverter(1, 2, false));
		$chain->addConverter('two', $this->createMockConverter(2, 3));
		$chain->addConverter('three', $this->createMockConverter(3, 4));

		$result = $chain->convert(2, 'two');
		$this->assertEquals(4, $result);
	}

	/**
	 * @param $from             value expected as input
	 * @param $to               value to return
	 * @param bool  $invoked    whether this converter will be invoked
	 *
	 * @return CConverter
	 */
	protected function createMockConverter($from, $to, $invoked = true) {
		$mock = $this->getMockBuilder('CConverter')->getMock();
		$mock->method('convert')->willReturn($to);

		if ($invoked) {
			$mock->expects($this->once())->method('convert')->with($this->equalTo($from));
		}

		return $mock;
	}

}
