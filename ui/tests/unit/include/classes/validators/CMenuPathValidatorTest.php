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

	private CMenuPathValidator $validator;

	protected function setUp(): void {
		$this->validator = new CMenuPathValidator();
	}

	public function dataProvider(): array {
		return [
			// Valid paths.
			['/', null],
			['/folder', null],
			['folder/', null],
			['folder1/folder2', null],
			['/folder1/folder2/', null],
			['', null],
			['folder', null],
			['/folder/sub/', null],
			['/a/b/c/d/e/', null],
			['/folder1/folder2', null],
			['folder1/folder2/', null],
			['folder1/folder2\/', null],
			['folder1\/name', null],
			['folder\/name/sub', null],
			[' folder / sub ', null],
			[' ', null],
			['/ folder /', null],
			['folder-name_123', null],
			['фолдер/sub', null],
			['\/', null],

			// Invalid paths.
			['folder1//folder2', _('Incorrect menu path.')],
			['//folder', _('Incorrect menu path.')],
			['/folder//sub', _('Incorrect menu path.')],
			['///folder', _('Incorrect menu path.')],
			['folder1///folder2', _('Incorrect menu path.')],
			['//folder//sub', _('Incorrect menu path.')],
			['folder//sub/', _('Incorrect menu path.')],
			['/folder/sub//name', _('Incorrect menu path.')],
			['//', _('Incorrect menu path.')],
			['///', _('Incorrect menu path.')],
			['folder/ /sub', _('Incorrect menu path.')],
			['/ \ / /', _('Incorrect menu path.')]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testPaths(string $path, ?string $error): void {
		$result = $this->validator->validate($path);

		if ($error === null) {
			$this->assertTrue($result, sprintf('Path "%s" should be valid', $path));
		}
		else {
			$this->assertFalse($result, sprintf('Path "%s" should be invalid', $path));
			$this->assertEquals($this->validator->getError());
		}
	}
}
