<?php
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


require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../include/CTest.php';

class dbConditionStringTest extends CTest {

	private const FILTER_CHUNK_SIZE = 950;

	public static function provider(): Generator {
		yield 'Filter is empty.' => [
			['field', []],
			'1=0'
		];

		yield 'Filter contain a single value.' => [
			['field', ['a']],
			'field=\'a\''
		];


		yield 'Filter contain 2 values.' => [
			['field', ['a', 'b']],
			'field IN (\'a\',\'b\')'
		];

		$value_count = self::FILTER_CHUNK_SIZE - 1;

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter contain '.$value_count.' values.' => [
			['field', $values],
			'field IN ('.implode(',', $filter_chunks[0]).')'
		];


		[$values, $filter_chunks] = self::getValuesAndFilterChunks(self::FILTER_CHUNK_SIZE);

		yield 'Filter contain '.self::FILTER_CHUNK_SIZE.' values.' => [
			['field', $values],
			'field IN ('.implode(',', $filter_chunks[0]).')'
		];

		$value_count = self::FILTER_CHUNK_SIZE + 1;

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter contain '.$value_count.' values.' => [
			['field', $values],
			'('.
				'field IN ('.implode(',', $filter_chunks[0]).')'.
				' OR field='.reset($filter_chunks[1]).
			')'
		];

		$value_count = (self::FILTER_CHUNK_SIZE * 2) - floor(self::FILTER_CHUNK_SIZE / 2);

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter contain '.$value_count.' values.' => [
			['field', $values],
			'('.
				'field IN ('.implode(',', $filter_chunks[0]).')'.
				' OR field IN ('.implode(',', $filter_chunks[1]).')'.
			')'
		];

		$value_count = (self::FILTER_CHUNK_SIZE * 2) + floor(self::FILTER_CHUNK_SIZE / 2);

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter contain '.$value_count.' values.' => [
			['field', $values],
			'('.
				'field IN ('.implode(',', $filter_chunks[0]).')'.
				' OR field IN ('.implode(',', $filter_chunks[1]).')'.
				' OR field IN ('.implode(',', $filter_chunks[2]).')'.
			')'
		];

		yield 'Filter with negation contain a single value.' => [
			['field', ['a'], true],
			'field!=\'a\''
		];

		yield 'Filter with negation contain 2 values.' => [
			['field', ['a', 'b'], true],
			'field NOT IN (\'a\',\'b\')'
		];

		$value_count = self::FILTER_CHUNK_SIZE - 1;

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter with negation contain '.$value_count.' values.' => [
			['field', $values, true],
			'field NOT IN ('.implode(',', $filter_chunks[0]).')'
		];


		[$values, $filter_chunks] = self::getValuesAndFilterChunks(self::FILTER_CHUNK_SIZE);

		yield 'Filter with negation contain '.self::FILTER_CHUNK_SIZE.' values.' => [
			['field', $values, true],
			'field NOT IN ('.implode(',', $filter_chunks[0]).')'
		];

		$value_count = self::FILTER_CHUNK_SIZE + 1;

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter with negation contain '.$value_count.' values.' => [
			['field', $values, true],
			'('.
				'field NOT IN ('.implode(',', $filter_chunks[0]).')'.
				' AND field!='.reset($filter_chunks[1]).
			')'
		];

		$value_count = (self::FILTER_CHUNK_SIZE * 2) - floor(self::FILTER_CHUNK_SIZE / 2);

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter with negation contain '.$value_count.' values.' => [
			['field', $values, true],
			'('.
				'field NOT IN ('.implode(',', $filter_chunks[0]).')'.
				' AND field NOT IN ('.implode(',', $filter_chunks[1]).')'.
			')'
		];

		$value_count = (self::FILTER_CHUNK_SIZE * 2) + floor(self::FILTER_CHUNK_SIZE / 2);

		[$values, $filter_chunks] = self::getValuesAndFilterChunks($value_count);

		yield 'Filter with negation contain '.$value_count.' values.' => [
			['field', $values, true],
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
	public function test($params, $expectedResult) {
		$result = call_user_func_array('dbConditionString', $params);

		$this->assertSame($expectedResult, $result);
	}
}
