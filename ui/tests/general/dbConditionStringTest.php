<?php
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


require_once __DIR__.'/../include/CTest.php';

class dbConditionStringTest extends CTest {

	private const FILTER_CHUNK_SIZE = 950;

	public static function provider(): Generator {
		yield 'Equality filter with no values.' => [
			['field', []],
			'1=0',
			'1=0'
		];

		yield 'Inequality filter with no values.' => [
			['field', [], true],
			'1=1',
			'1=1'
		];

		yield 'Equality filter with single value.' => [
			['field', ['a']],
			"field='a'",
			"field='a'"
		];

		yield 'Inequality filter with single value.' => [
			['field', ['a'], true],
			"field!='a'",
			"field!='a'"
		];

		yield 'Inclusion filter with two values.' => [
			['field', ['a', 'b']],
			"(field IN ('a','b'))",
			"(field IN ('a','b'))"
		];

		yield 'Exclusion filter with two values.' => [
			['field', ['a', 'b'], true],
			"(field NOT IN ('a','b'))",
			"(field NOT IN ('a','b'))"
		];

		$value_count = self::FILTER_CHUNK_SIZE - 1;

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Inclusion filter with '.$value_count.' values.' => [
			['field', $values],
			'(field IN ('.implode(',', $filter_chunks[0]).'))',
			'(field IN ('.implode(',', $filter_chunks[0]).'))'
		];

		yield 'Exclusion filter with '.$value_count.' values.' => [
			['field', $values, true],
			'(field NOT IN ('.implode(',', $filter_chunks[0]).'))',
			'(field NOT IN ('.implode(',', $filter_chunks[0]).'))'
		];

		$value_count = self::FILTER_CHUNK_SIZE + 1;

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);
		$filter = array_merge(...$filter_chunks);

		yield 'Inclusion filter with '.$value_count.' values.' => [
			['field', $values],
			'(field IN ('.implode(',', $filter).'))',
			'('.
				'field IN ('.implode(',', $filter_chunks[0]).')'.
				' OR field IN ('.implode(',', $filter_chunks[1]).')'.
			')'
		];

		yield 'Exclusion filter with '.$value_count.' values.' => [
			['field', $values, true],
			'(field NOT IN ('.implode(',', $filter).'))',
			'('.
				'field NOT IN ('.implode(',', $filter_chunks[0]).')'.
				' AND field NOT IN ('.implode(',', $filter_chunks[1]).')'.
			')'
		];

		$value_count = (self::FILTER_CHUNK_SIZE * 2) + floor(self::FILTER_CHUNK_SIZE / 2);

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);
		$filter = array_merge(...$filter_chunks);

		yield 'Inclusion filter with '.$value_count.' values.' => [
			['field', $values],
			'(field IN ('.implode(',', $filter).'))',
			'('.
				'field IN ('.implode(',', $filter_chunks[0]).')'.
				' OR field IN ('.implode(',', $filter_chunks[1]).')'.
				' OR field IN ('.implode(',', $filter_chunks[2]).')'.
			')'
		];

		yield 'Exclusion filter with '.$value_count.' values.' => [
			['field', $values, true],
			'(field NOT IN ('.implode(',', $filter).'))',
			'('.
				'field NOT IN ('.implode(',', $filter_chunks[0]).')'.
				' AND field NOT IN ('.implode(',', $filter_chunks[1]).')'.
				' AND field NOT IN ('.implode(',', $filter_chunks[2]).')'.
			')'
		];
	}

	private static function getValuesAndFilterChunks(int $value_count): array {
		$values = [];
		$filter_chunks = [];
		$filter_chunk = [];

		for ($i = 1; $i <= $value_count; $i++) {
			$values[] = (string) $i;
			$filter_chunk[] = '\''.$i.'\'';

			if ($i % self::FILTER_CHUNK_SIZE == 0 || $i == $value_count) {
				$filter_chunks[] = $filter_chunk;
				$filter_chunk = [];
			}
		}

		return [$values, $filter_chunks];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, string $expected_non_oracle, string $expected_oracle): void {
		global $DB;

		$result = dbConditionString(...$params);

		$this->assertSame($DB['TYPE'] === ZBX_DB_ORACLE ? $expected_oracle : $expected_non_oracle, $result);
	}

	/**
	 * @dataProvider provider
	 */
	public function testOracle(array $params, string $expected_non_oracle, string $expected_oracle): void {
		global $DB;

		if ($DB['TYPE'] === ZBX_DB_ORACLE) {
			$this->markTestSkipped();
		}

		$_DB = $DB;
		$DB['TYPE'] = ZBX_DB_ORACLE;

		$result = dbConditionString(...$params);

		$this->assertSame($expected_oracle, $result);

		$DB = $_DB;
	}
}
