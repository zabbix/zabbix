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


class CRegistryFactoryTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CRegistryFactory
	 */
	protected $factory;

	public function setUp() {
		$this->factory = new CRegistryFactory([
			'string' => 'DateTime',
			'closure' => function() {
				return new DateTime();
			}
		]);
	}

	/**
	 * Test that the factory creates the right objects.
	 */
	public function testObjectCreate() {
		$this->assertEquals(get_class($this->factory->getObject('string')), 'DateTime');
		$this->assertEquals(get_class($this->factory->getObject('closure')), 'DateTime');
	}

	/**
	 * Test that the factory creates the object only once and returns the same object after that.
	 */
	public function testObjectSame() {
		$this->assertTrue($this->factory->getObject('string') === $this->factory->getObject('string'));
	}
}
