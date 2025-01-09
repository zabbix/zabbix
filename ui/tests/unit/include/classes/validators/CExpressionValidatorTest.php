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

class CExpressionValidatorTest extends TestCase {

	/**
	 * An array of expressions, options and the expected results.
	 */
	public function dataProvider() {
		return [
			['avg(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['histogram_quantile(1, bucket_rate_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['avg(count_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(count_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(count_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(count_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(count_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['avg(exists_foreach(/host/key))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(exists_foreach(/host/key))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(exists_foreach(/host/key))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(exists_foreach(/host/key))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(exists_foreach(/host/key))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['avg(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['kurtosis(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['mad(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['skewness(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['stddevpop(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['stddevsamp(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sumofsquares(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['varpop(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['varsamp(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['avg(max_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(max_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(max_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(max_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(max_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['avg(min_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(min_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(min_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(min_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(min_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			['avg(sum_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(sum_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['max(sum_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['min(sum_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['sum(sum_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],

			// User macros.
			['find(/host/key, {$PERIOD}:{$TIMESHIFT})', ['usermacros' => true], ['rc' => true, 'error' => null]],
			['find(/host/key, {$PERIOD}:{$TIMESHIFT})', [], ['rc' => false, 'error' => 'invalid second parameter in function "find"']],
			['find(/host/key, 1d, {$OP})', ['usermacros' => true], ['rc' => true, 'error' => null]],
			['find(/host/key, 1d, {$OP})', [], ['rc' => false, 'error' => 'invalid third parameter in function "find"']],
			['find(/host/key, 1d, "{$OP}")', ['usermacros' => true], ['rc' => true, 'error' => null]],
			['find(/host/key, 1d, "{$OP}")', [], ['rc' => false, 'error' => 'invalid third parameter in function "find"']],

			// LLD macros.
			['find(/host/key, {#PERIOD}:{#TIMESHIFT})', ['lldmacros' => true], ['rc' => true, 'error' => null]],
			['find(/host/key, {#PERIOD}:{#TIMESHIFT})', [], ['rc' => false, 'error' => 'invalid second parameter in function "find"']],
			['find(/host/key, 1d, {#OP})', ['lldmacros' => true], ['rc' => true, 'error' => null]],
			['find(/host/key, 1d, {#OP})', [], ['rc' => false, 'error' => 'invalid third parameter in function "find"']],
			['find(/host/key, 1d, "{#OP}")', ['lldmacros' => true], ['rc' => true, 'error' => null]],
			['find(/host/key, 1d, "{#OP}")', [], ['rc' => false, 'error' => 'invalid third parameter in function "find"']],

			// Unknown function in trigger expression.
			['avg_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "avg_foreach"']],
			['bucket_percentile(/host/key)', [], ['rc' => false, 'error' => 'unknown function "bucket_percentile"']],
			['bucket_rate_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "bucket_rate_foreach"']],
			['count_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "count_foreach"']],
			['exists_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "exists_foreach"']],
			['item_count(/host/key)', [], ['rc' => false, 'error' => 'unknown function "item_count"']],
			['last_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "last_foreach"']],
			['max_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "max_foreach"']],
			['min_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "min_foreach"']],
			['sum_foreach(/host/key)', [], ['rc' => false, 'error' => 'unknown function "sum_foreach"']],

			// Not aggregated.
			['avg_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "avg_foreach"']],
			['bucket_rate_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "bucket_rate_foreach"']],
			['count_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "count_foreach"']],
			['exists_foreach(/host/key)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "exists_foreach"']],
			['last_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "last_foreach"']],
			['max_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "max_foreach"']],
			['min_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "min_foreach"']],
			['sum_foreach(/host/key, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "sum_foreach"']],

			// Wildcards.
			['avg(avg_foreach(/*/key[p1,p2], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(avg_foreach(/host/*, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(avg_foreach(/host/key[*,p2], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(avg_foreach(/host/key[p1,*], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(avg_foreach(/host/key[*,*], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(avg_foreach(/*/key[*,*], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(avg_foreach(/*/*, 1))', ['calculated' => true], ['rc' => false, 'error' => 'invalid first parameter in function "avg_foreach"']],
			['avg(/*/key[p1,p2], 1))', ['calculated' => true], ['rc' => false, 'error' => 'invalid first parameter in function "avg"']],
			['avg(/host/*, 1))', ['calculated' => true], ['rc' => false, 'error' => 'invalid first parameter in function "avg"']],
			['avg(/host/key[*,p2], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(/host/key[p1,*], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['avg(/host/key[*,*], 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['item_count(/host/key[*,*]))', ['calculated' => true], ['rc' => true, 'error' => null]],

			// Non-aggregating math function.
			['length(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "avg_foreach"']],

			// Non-existing math function.
			['foo(avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => false, 'error' => 'unknown function "foo"']],

			// More than one parameter for aggregating math function.
			['avg(count_foreach(/host/key, 20m), "eq", 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "count_foreach"']],
			['avg(count_foreach(/host/key, 20m), 1, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "count_foreach"']],
			['avg(count_foreach(/host/key, 20m, "eq", 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(count_foreach(/host/key, 20m, , ))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(count_foreach(/host/key, 20m), "eq", 2)', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(count_foreach(/host/key, 20m, "eq"))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(count_foreach(/host/key, 20m, "eq", ))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(count_foreach(/host/key, 20m, 1, 1), "eq", 2)', ['calculated' => true], ['rc' => false, 'error' => 'invalid third parameter in function "count_foreach"']],
			['count(count_foreach(/host/key, 20m, "eq", 1), "eq", 2)', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(last_foreach(/host/key, 1))', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(last_foreach(/host/key, 1), 1, 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "count"']],
			['count(last_foreach(/host/key, 1), "eq")', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(last_foreach(/host/key, 1), "eq", 1)', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(last_foreach(/host/key, 1), "eq", "string")', ['calculated' => true], ['rc' => true, 'error' => null]],
			['count(last_foreach(/host/key, 1), "eq", 1, 1)', ['calculated' => true], ['rc' => false, 'error' => 'invalid number of parameters in function "count"']],
			['sum(avg_foreach(/host/key, 1), 1)', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "avg_foreach"']],
			['sum(1, avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "avg_foreach"']],
			['sum(avg_foreach(/host/key, 1), avg_foreach(/host/key, 1))', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "avg_foreach"']],

			// Host/key reference requirement.
			['sum(1, 2, 3)', [], ['rc' => false, 'error' => 'trigger expression must contain at least one /host/key reference']],
			['sum(1, 2, 3)', ['calculated' => true], ['rc' => true, 'error' => null]],

			// Incorrect usage of math/history functions.
			['foo(/host/item)', [], ['rc' => false, 'error' => 'unknown function "foo"']],
			['abs(/host/item)', [], ['rc' => false, 'error' => 'incorrect usage of function "abs"']],
			['foo(1, 2, 3)', [], ['rc' => false, 'error' => 'unknown function "foo"']],
			['change(1, 2, 3)', [], ['rc' => false, 'error' => 'incorrect usage of function "change"']],
			['count(123)', [], ['rc' => false, 'error' => 'incorrect usage of function "count"']],
			['avg(bucket_rate_foreach(/host/*, 1))', ['calculated' => true], ['rc' => false, 'error' => 'incorrect usage of function "bucket_rate_foreach"']],
			['jsonpath(/host/item)', [], ['rc' => false, 'error' => 'incorrect usage of function "jsonpath"']],
			['jsonpath(/host/item, "$.path")', [], ['rc' => false, 'error' => 'incorrect usage of function "jsonpath"']],
			['jsonpath(last(/host/item))', [], ['rc' => false, 'error' => 'invalid number of parameters in function "jsonpath"']],
			['jsonpath(last(/host/item), "$.path")', [], ['rc' => true, 'error' => null]],
			['jsonpath(last(/host/item), "$.path", "fallback")', [], ['rc' => true, 'error' => null]],
			['jsonpath(last(/host/item), "$.path", "fallback", "extra param")', [], ['rc' => false, 'error' => 'invalid number of parameters in function "jsonpath"']],
			['xmlxpath(/host/item)', [], ['rc' => false, 'error' => 'incorrect usage of function "xmlxpath"']],
			['xmlxpath(/host/item, "/path")', [], ['rc' => false, 'error' => 'incorrect usage of function "xmlxpath"']],
			['xmlxpath(last(/host/item))', [], ['rc' => false, 'error' => 'invalid number of parameters in function "xmlxpath"']],
			['xmlxpath(last(/host/item), "/path")', [], ['rc' => true, 'error' => null]],
			['xmlxpath(last(/host/item), "/path", "fallback")', [], ['rc' => true, 'error' => null]],
			['xmlxpath(last(/host/item), "/path", "fallback", "extra param")', [], ['rc' => false, 'error' => 'invalid number of parameters in function "xmlxpath"']]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testExpressionValidator(string $source, array $options, array $expected) {
		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => true
		] + $options);
		$expression_validator = new CExpressionValidator($options);

		$expression_parser->parse($source);
		$tokens = $expression_parser->getResult()->getTokens();

		$this->assertSame($expected, [
			'rc' => $expression_validator->validate($tokens),
			'error' => $expression_validator->getError()
		]);
	}
}
