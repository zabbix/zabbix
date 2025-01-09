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

	public function dataProviderRegrepl(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionRegrepl.json'), true);
	}

	public function dataProviderTr(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionTr.json'), true);
	}

	public function dataProviderBtoa(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionBtoa.json'), true);
	}

	public function dataProviderUrlencode(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionUrlencode.json'), true);
	}

	public function dataProviderHtmlencode(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionHtmlencode.json'), true);
	}

	public function dataProviderUrldecode(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionUrldecode.json'), true);
	}

	public function dataProviderHtmldecode(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionHtmldecode.json'), true);
	}

	public function dataProviderLowercase(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionLowercase.json'), true);
	}

	public function dataProviderUppercase(): array {
		return json_decode(file_get_contents(__DIR__.'/CMacroFunctionUppercase.json'), true);
	}

	/**
	 * @dataProvider dataProviderRegsub
	 * @dataProvider dataProviderFmtnum
	 * @dataProvider dataProviderFmttime
	 * @dataProvider dataProviderRegrepl
	 * @dataProvider dataProviderTr
	 * @dataProvider dataProviderBtoa
	 * @dataProvider dataProviderUrlencode
	 * @dataProvider dataProviderHtmlencode
	 * @dataProvider dataProviderUrldecode
	 * @dataProvider dataProviderHtmldecode
	 * @dataProvider dataProviderLowercase
	 * @dataProvider dataProviderUppercase
	 */
	public function testResolveItemDescriptions(string $value, array $macrofunc, string $expected): void {
		$this->assertSame($expected, CMacroFunction::calcMacrofunc($value, $macrofunc));
	}
}
