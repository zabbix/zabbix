<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

class CMenuPathValidatorTest extends TestCase {

	private const VALID_NEVER = 0;
	private const VALID_NON_STRICT_ONLY = 1;
	private const VALID_ALWAYS = 2;

	private CMenuPathValidator $validator;
	private CMenuPathValidator $validator_strict;

	protected function setUp(): void {
		$this->validator = new CMenuPathValidator();
		$this->validator_strict = new CMenuPathValidator(['strict' => true]);
	}

	public function dataProvider(): array {
		return [
			['folder1/folder2', null, self::VALID_ALWAYS],
			['', null, self::VALID_ALWAYS],
			['folder', null, self::VALID_ALWAYS],
			['folder1/folder2\/', null, self::VALID_ALWAYS],
			['folder1\/name', null, self::VALID_ALWAYS],
			['folder\/name/sub', null, self::VALID_ALWAYS],
			[' folder / sub ', null, self::VALID_ALWAYS],
			[' ', null, self::VALID_ALWAYS],
			['folder-name_123', null, self::VALID_ALWAYS],
			['фолдер/sub', null, self::VALID_ALWAYS],
			['\/', null, self::VALID_ALWAYS],

			['/', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['/folder', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['folder/', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['/folder1/folder2/', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['/folder/sub/', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['/a/b/c/d/e/', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['/folder1/folder2', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['folder1/folder2/', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],
			['/ folder /', _('directory cannot be empty'), self::VALID_NON_STRICT_ONLY],

			['folder1//folder2', _('directory cannot be empty'), self::VALID_NEVER],
			['//folder', _('directory cannot be empty'), self::VALID_NEVER],
			['/folder//sub', _('directory cannot be empty'), self::VALID_NEVER],
			['///folder', _('directory cannot be empty'), self::VALID_NEVER],
			['folder1///folder2', _('directory cannot be empty'), self::VALID_NEVER],
			['//folder//sub', _('directory cannot be empty'), self::VALID_NEVER],
			['folder//sub/', _('directory cannot be empty'), self::VALID_NEVER],
			['/folder/sub//name', _('directory cannot be empty'), self::VALID_NEVER],
			['//', _('directory cannot be empty'), self::VALID_NEVER],
			['///', _('directory cannot be empty'), self::VALID_NEVER],
			['folder/ /sub', _('directory cannot be empty'), self::VALID_NEVER],
			['/ \ / /', _('directory cannot be empty'), self::VALID_NEVER]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testPaths(string $path, ?string $error, int $mode): void {
		$result = $this->validator->validate($path);
		$result_strict = $this->validator_strict->validate($path);

		switch ($mode) {
			case self::VALID_ALWAYS:
				$this->assertTrue($result, sprintf('Path "%s" should be valid by non-strict validation', $path));
				$this->assertTrue($result_strict, sprintf('Path "%s" should be valid by strict validation', $path));
				break;
			case self::VALID_NON_STRICT_ONLY:
				$this->assertTrue($result, sprintf('Path "%s" should be valid by non-strict validation', $path));
				$this->assertFalse($result_strict, sprintf('Path "%s" should be invalid by strict validation', $path));
				$this->assertEquals($error, $this->validator_strict->getError());
				break;
			case self::VALID_NEVER:
				$this->assertFalse($result, sprintf('Path "%s" should be invalid by non-strict validation', $path));
				$this->assertEquals($error, $this->validator->getError());
				$this->assertFalse($result_strict, sprintf('Path "%s" should be invalid by strict validation', $path));
				$this->assertEquals($error, $this->validator_strict->getError());
				break;
		}
	}
}
