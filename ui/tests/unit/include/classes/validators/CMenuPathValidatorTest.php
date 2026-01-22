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

	public function dataProviderValidPaths(): array {
		return [
			'root folder' => ['/'],
			'leading slash with folder' => ['/folder'],
			'trailing slash' => ['folder/'],
			'multiple folders' => ['folder1/folder2'],
			'leading and trailing slashes' => ['/folder1/folder2/'],
			'empty string' => [''],
			'single folder' => ['folder'],
			'multiple levels with slashes' => ['/folder/sub/'],
			'deeply nested path' => ['/a/b/c/d/e/'],
			'folder with leading slash only' => ['/folder1/folder2'],
			'folder with trailing slash only' => ['folder1/folder2/'],
			'folder with trailing slash escaped' => ['folder1/folder2\/'],
			'escaped slash in folder name' => ['folder1\/name'],
			'escaped slash with subfolder' => ['folder\/name/sub'],
			'spaces in folder names (trimmed)' => [' folder / sub '],
			'single space folder' => [' '],
			'leading slash with spaces' => ['/ folder /'],
			'special characters in folder' => ['folder-name_123'],
			'unicode folder name' => ['фолдер/sub'],
			'path with only escaped slash' => ['\/']
		];
	}

	public function dataProviderInvalidPaths(): array {
		return [
			'double slash in middle' => ['folder1//folder2'],
			'double leading slash' => ['//folder'],
			'empty middle segment in longer path' => ['/folder//sub'],
			'triple leading slash' => ['///folder'],
			'multiple empty segments' => ['folder1///folder2'],
			'empty segment at start and middle' => ['//folder//sub'],
			'double slash with trailing' => ['folder//sub/'],
			'double slash near end' => ['/folder/sub//name'],
			'only double slash' => ['//'],
			'triple slash' => ['///'],
			'empty middle with spaces' => ['folder/ /sub'],
			'folder with escaped space' => ['/ \ / /']
		];
	}

	/**
	 * @dataProvider dataProviderValidPaths
	 */
	public function testValidPaths(string $path): void {
		$this->assertTrue(
			$this->validator->validate($path),
			sprintf('Path "%s" should be valid', $path)
		);
	}

	/**
	 * @dataProvider dataProviderInvalidPaths
	 */
	public function testInvalidPaths(string $path): void {
		$this->assertFalse(
			$this->validator->validate($path),
			sprintf('Path "%s" should be invalid', $path)
		);
	}
}
