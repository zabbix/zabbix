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

abstract class CImportConverterTest extends TestCase {

	abstract protected function createConverter();
	abstract protected function createSource(array $data = []);
	abstract protected function createExpectedResult(array $data = []);

	public function testConvertGeneral() {
		$this->assertConvert($this->createExpectedResult(), $this->createSource());
	}

	protected function assertConvert(array $expectedResult, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expectedResult, $result);
	}
}
