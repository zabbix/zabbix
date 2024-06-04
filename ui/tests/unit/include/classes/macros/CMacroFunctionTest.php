<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

require_once __DIR__.'/../../../../../include/translateDefines.inc.php';

class CMacroFunctionTest extends TestCase {

	protected $default_timezone;

	protected function setUp(): void {
		$this->default_timezone = date_default_timezone_get();
		date_default_timezone_set('Europe/Riga');
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->default_timezone);
	}

	public function dataProviderRegsub(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionRegsub.json'), true);
	}

	public function dataProviderFmtnum(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionFmtnum.json'), true);
	}

	public function dataProviderFmttime(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionFmttime.json'), true);
	}

	/**
	 * @dataProvider dataProviderRegsub
	 * @dataProvider dataProviderFmtnum
	 * @dataProvider dataProviderFmttime
	 */
	public function testResolveItemDescriptions(string $value, array $macrofunc, string $expected): void {
		$this->assertSame($expected, CMacroFunction::calcMacrofunc($value, $macrofunc));
	}
}
