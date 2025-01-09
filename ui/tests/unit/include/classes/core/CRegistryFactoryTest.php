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

class CRegistryFactoryTest extends TestCase {

	/**
	 * @var CRegistryFactory
	 */
	protected $factory;

	protected function setUp(): void {
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
}
