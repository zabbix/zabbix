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

class CMathFunctionValidatorTest extends TestCase {

	/**
	 * An array of math functions, options and the expected results.
	 */
	public function dataProvider() {
		return [
			['abs()', ['rc' => false, 'error' => 'invalid number of parameters in function "abs"']],
			['abs(1)', ['rc' => true, 'error' => null]],
			['abs(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "abs"']],

			['acos()', ['rc' => false, 'error' => 'invalid number of parameters in function "acos"']],
			['acos(1)', ['rc' => true, 'error' => null]],
			['acos(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "acos"']],

			['ascii()', ['rc' => false, 'error' => 'invalid number of parameters in function "ascii"']],
			['ascii("a")', ['rc' => true, 'error' => null]],
			['ascii("a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "ascii"']],

			['asin()', ['rc' => false, 'error' => 'invalid number of parameters in function "asin"']],
			['asin(1)', ['rc' => true, 'error' => null]],
			['asin(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "asin"']],

			['atan()', ['rc' => false, 'error' => 'invalid number of parameters in function "atan"']],
			['atan(1)', ['rc' => true, 'error' => null]],
			['atan(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "atan"']],

			['atan2()', ['rc' => false, 'error' => 'invalid number of parameters in function "atan2"']],
			['atan2(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "atan2"']],
			['atan2(1, 1)', ['rc' => true, 'error' => null]],
			['atan2(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "atan2"']],

			['avg()', ['rc' => false, 'error' => 'invalid number of parameters in function "avg"']],
			['avg(1)', ['rc' => true, 'error' => null]],
			['avg(1, 1)', ['rc' => true, 'error' => null]],
			['avg(1, 1, 1)', ['rc' => true, 'error' => null]],

			['between()', ['rc' => false, 'error' => 'invalid number of parameters in function "between"']],
			['between(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "between"']],
			['between(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "between"']],
			['between(1, 1, 1)', ['rc' => true, 'error' => null]],
			['between(1, 1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "between"']],

			['bitand()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitand"']],
			['bitand(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitand"']],
			['bitand(1, 1)', ['rc' => true, 'error' => null]],
			['bitand(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitand"']],

			['bitlength()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitlength"']],
			['bitlength(1)', ['rc' => true, 'error' => null]],
			['bitlength(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitlength"']],

			['bitlshift()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitlshift"']],
			['bitlshift(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitlshift"']],
			['bitlshift(1, 1)', ['rc' => true, 'error' => null]],
			['bitlshift(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitlshift"']],

			['bitnot()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitnot"']],
			['bitnot(1)', ['rc' => true, 'error' => null]],
			['bitnot(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitnot"']],

			['bitor()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitor"']],
			['bitor(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitor"']],
			['bitor(1, 1)', ['rc' => true, 'error' => null]],
			['bitor(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitor"']],

			['bitrshift()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitrshift"']],
			['bitrshift(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitrshift"']],
			['bitrshift(1, 1)', ['rc' => true, 'error' => null]],
			['bitrshift(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitrshift"']],

			['bitxor()', ['rc' => false, 'error' => 'invalid number of parameters in function "bitxor"']],
			['bitxor(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitxor"']],
			['bitxor(1, 1)', ['rc' => true, 'error' => null]],
			['bitxor(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bitxor"']],

			['bytelength()', ['rc' => false, 'error' => 'invalid number of parameters in function "bytelength"']],
			['bytelength(1)', ['rc' => true, 'error' => null]],
			['bytelength(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "bytelength"']],

			['cbrt()', ['rc' => false, 'error' => 'invalid number of parameters in function "cbrt"']],
			['cbrt(1)', ['rc' => true, 'error' => null]],
			['cbrt(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "cbrt"']],

			['ceil()', ['rc' => false, 'error' => 'invalid number of parameters in function "ceil"']],
			['ceil(1)', ['rc' => true, 'error' => null]],
			['ceil(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "ceil"']],

			['char()', ['rc' => false, 'error' => 'invalid number of parameters in function "char"']],
			['char(1)', ['rc' => true, 'error' => null]],
			['char(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "char"']],

			['concat()', ['rc' => false, 'error' => 'invalid number of parameters in function "concat"']],
			['concat("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "concat"']],
			['concat("a", "a")', ['rc' => true, 'error' => null]],
			['concat("a", "a", "a")', ['rc' => true, 'error' => null]],
			['concat("a", "a", "a", "a")', ['rc' => true, 'error' => null]],

			['cos()', ['rc' => false, 'error' => 'invalid number of parameters in function "cos"']],
			['cos(1)', ['rc' => true, 'error' => null]],
			['cos(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "cos"']],

			['cosh()', ['rc' => false, 'error' => 'invalid number of parameters in function "cosh"']],
			['cosh(1)', ['rc' => true, 'error' => null]],
			['cosh(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "cosh"']],

			['cot()', ['rc' => false, 'error' => 'invalid number of parameters in function "cot"']],
			['cot(1)', ['rc' => true, 'error' => null]],
			['cot(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "cot"']],

			['count()', ['rc' => false, 'error' => 'invalid number of parameters in function "count"']],
			['count(1)', ['rc' => true, 'error' => null]],
			['count(1, 1)', ['rc' => true, 'error' => null]],
			['count(1, 1, 1)', ['rc' => true, 'error' => null]],
			['count(1, 1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "count"']],

			['date()', ['rc' => true, 'error' => null]],
			['date(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "date"']],

			['dayofmonth()', ['rc' => true, 'error' => null]],
			['dayofmonth(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "dayofmonth"']],

			['dayofweek()', ['rc' => true, 'error' => null]],
			['dayofweek(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "dayofweek"']],

			['degrees()', ['rc' => false, 'error' => 'invalid number of parameters in function "degrees"']],
			['degrees(1)', ['rc' => true, 'error' => null]],
			['degrees(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "degrees"']],

			['e()', ['rc' => true, 'error' => null]],
			['e(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "e"']],

			['exp()', ['rc' => false, 'error' => 'invalid number of parameters in function "exp"']],
			['exp(1)', ['rc' => true, 'error' => null]],
			['exp(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "exp"']],

			['expm1()', ['rc' => false, 'error' => 'invalid number of parameters in function "expm1"']],
			['expm1(1)', ['rc' => true, 'error' => null]],
			['expm1(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "expm1"']],

			['floor()', ['rc' => false, 'error' => 'invalid number of parameters in function "floor"']],
			['floor(1)', ['rc' => true, 'error' => null]],
			['floor(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "floor"']],

			['histogram_quantile()', ['rc' => false, 'error' => 'invalid number of parameters in function "histogram_quantile"']],
			['histogram_quantile(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "histogram_quantile"']],
			['histogram_quantile(1, 1)', ['rc' => true, 'error' => null]],
			['histogram_quantile(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "histogram_quantile"']],
			['histogram_quantile(1, 1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "histogram_quantile"']],
			['histogram_quantile(1, 1, 1, 1, 1)', ['rc' => true, 'error' => null]],
			['histogram_quantile(1, 1, 1, 1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "histogram_quantile"']],
			['histogram_quantile(1, 1, 1, 1, 1, 1, 1)', ['rc' => true, 'error' => null]],

			['in()', ['rc' => false, 'error' => 'invalid number of parameters in function "in"']],
			['in(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "in"']],
			['in(1, 1)', ['rc' => true, 'error' => null]],
			['in(1, 1, 1)', ['rc' => true, 'error' => null]],
			['in(1, 1, 1, 1)', ['rc' => true, 'error' => null]],

			['insert()', ['rc' => false, 'error' => 'invalid number of parameters in function "insert"']],
			['insert("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "insert"']],
			['insert("a", 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "insert"']],
			['insert("a", 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "insert"']],
			['insert("a", 1, 1, "a")', ['rc' => true, 'error' => null]],
			['insert("a", 1, 1, "a", 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "insert"']],

			['jsonpath()', ['rc' => false, 'error' => 'invalid number of parameters in function "jsonpath"']],
			['jsonpath("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "jsonpath"']],
			['jsonpath("a", "a")', ['rc' => true, 'error' => null]],
			['jsonpath("a", "a", "a")', ['rc' => true, 'error' => null]],
			['jsonpath("a", "a", "a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "jsonpath"']],

			['kurtosis()', ['rc' => false, 'error' => 'invalid number of parameters in function "kurtosis"']],
			['kurtosis(1)', ['rc' => true, 'error' => null]],
			['kurtosis(1, 1)', ['rc' => true, 'error' => null]],
			['kurtosis(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "kurtosis"']],

			['left()', ['rc' => false, 'error' => 'invalid number of parameters in function "left"']],
			['left("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "left"']],
			['left("a", 1)', ['rc' => true, 'error' => null]],
			['left("a", 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "left"']],

			['length()', ['rc' => false, 'error' => 'invalid number of parameters in function "length"']],
			['length(1)', ['rc' => true, 'error' => null]],
			['length(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "length"']],

			['log()', ['rc' => false, 'error' => 'invalid number of parameters in function "log"']],
			['log(1)', ['rc' => true, 'error' => null]],
			['log(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "log"']],

			['log10()', ['rc' => false, 'error' => 'invalid number of parameters in function "log10"']],
			['log10(1)', ['rc' => true, 'error' => null]],
			['log10(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "log10"']],

			['ltrim()', ['rc' => false, 'error' => 'invalid number of parameters in function "ltrim"']],
			['ltrim("a")', ['rc' => true, 'error' => null]],
			['ltrim("a", "a")', ['rc' => true, 'error' => null]],
			['ltrim("a", "a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "ltrim"']],

			['mad()', ['rc' => false, 'error' => 'invalid number of parameters in function "mad"']],
			['mad(1)', ['rc' => true, 'error' => null]],
			['mad(1, 1)', ['rc' => true, 'error' => null]],
			['mad(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "mad"']],

			['max()', ['rc' => false, 'error' => 'invalid number of parameters in function "max"']],
			['max(1)', ['rc' => true, 'error' => null]],
			['max(1, 1)', ['rc' => true, 'error' => null]],
			['max(1, 1, 1)', ['rc' => true, 'error' => null]],

			['mid()', ['rc' => false, 'error' => 'invalid number of parameters in function "mid"']],
			['mid("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "mid"']],
			['mid("a", 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "mid"']],
			['mid("a", 1, 1)', ['rc' => true, 'error' => null]],
			['mid("a", 1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "mid"']],

			['min()', ['rc' => false, 'error' => 'invalid number of parameters in function "min"']],
			['min(1)', ['rc' => true, 'error' => null]],
			['min(1, 1)', ['rc' => true, 'error' => null]],
			['min(1, 1, 1)', ['rc' => true, 'error' => null]],

			['mod()', ['rc' => false, 'error' => 'invalid number of parameters in function "mod"']],
			['mod(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "mod"']],
			['mod(1, 1)', ['rc' => true, 'error' => null]],
			['mod(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "mod"']],

			['now()', ['rc' => true, 'error' => null]],
			['now(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "now"']],

			['pi()', ['rc' => true, 'error' => null]],
			['pi(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "pi"']],

			['power()', ['rc' => false, 'error' => 'invalid number of parameters in function "power"']],
			['power(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "power"']],
			['power(1, 1)', ['rc' => true, 'error' => null]],
			['power(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "power"']],

			['radians()', ['rc' => false, 'error' => 'invalid number of parameters in function "radians"']],
			['radians(1)', ['rc' => true, 'error' => null]],
			['radians(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "radians"']],

			['rand()', ['rc' => true, 'error' => null]],
			['rand(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "rand"']],

			['repeat()', ['rc' => false, 'error' => 'invalid number of parameters in function "repeat"']],
			['repeat("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "repeat"']],
			['repeat("a", 1)', ['rc' => true, 'error' => null]],
			['repeat("a", 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "repeat"']],

			['replace()', ['rc' => false, 'error' => 'invalid number of parameters in function "replace"']],
			['replace("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "replace"']],
			['replace("a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "replace"']],
			['replace("a", "a", "a")', ['rc' => true, 'error' => null]],
			['replace("a", "a", "a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "replace"']],

			['right()', ['rc' => false, 'error' => 'invalid number of parameters in function "right"']],
			['right("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "right"']],
			['right("a", 1)', ['rc' => true, 'error' => null]],
			['right("a", 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "right"']],

			['round()', ['rc' => false, 'error' => 'invalid number of parameters in function "round"']],
			['round(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "round"']],
			['round(1, 1)', ['rc' => true, 'error' => null]],
			['round(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "round"']],

			['rtrim()', ['rc' => false, 'error' => 'invalid number of parameters in function "rtrim"']],
			['rtrim("a")', ['rc' => true, 'error' => null]],
			['rtrim("a", "a")', ['rc' => true, 'error' => null]],
			['rtrim("a", "a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "rtrim"']],

			['signum()', ['rc' => false, 'error' => 'invalid number of parameters in function "signum"']],
			['signum(1)', ['rc' => true, 'error' => null]],
			['signum(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "signum"']],

			['sin()', ['rc' => false, 'error' => 'invalid number of parameters in function "sin"']],
			['sin(1)', ['rc' => true, 'error' => null]],
			['sin(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "sin"']],

			['sinh()', ['rc' => false, 'error' => 'invalid number of parameters in function "sinh"']],
			['sinh(1)', ['rc' => true, 'error' => null]],
			['sinh(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "sinh"']],

			['skewness()', ['rc' => false, 'error' => 'invalid number of parameters in function "skewness"']],
			['skewness(1)', ['rc' => true, 'error' => null]],
			['skewness(1, 1)', ['rc' => true, 'error' => null]],
			['skewness(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "skewness"']],

			['sqrt()', ['rc' => false, 'error' => 'invalid number of parameters in function "sqrt"']],
			['sqrt(1)', ['rc' => true, 'error' => null]],
			['sqrt(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "sqrt"']],

			['stddevpop()', ['rc' => false, 'error' => 'invalid number of parameters in function "stddevpop"']],
			['stddevpop(1)', ['rc' => true, 'error' => null]],
			['stddevpop(1, 1)', ['rc' => true, 'error' => null]],
			['stddevpop(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "stddevpop"']],

			['stddevsamp()', ['rc' => false, 'error' => 'invalid number of parameters in function "stddevsamp"']],
			['stddevsamp(1)', ['rc' => true, 'error' => null]],
			['stddevsamp(1, 1)', ['rc' => true, 'error' => null]],
			['stddevsamp(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "stddevsamp"']],

			['sum()', ['rc' => false, 'error' => 'invalid number of parameters in function "sum"']],
			['sum(1)', ['rc' => true, 'error' => null]],
			['sum(1, 1)', ['rc' => true, 'error' => null]],
			['sum(1, 1, 1)', ['rc' => true, 'error' => null]],

			['sumofsquares()', ['rc' => false, 'error' => 'invalid number of parameters in function "sumofsquares"']],
			['sumofsquares(1)', ['rc' => true, 'error' => null]],
			['sumofsquares(1, 1)', ['rc' => true, 'error' => null]],
			['sumofsquares(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "sumofsquares"']],

			['tan()', ['rc' => false, 'error' => 'invalid number of parameters in function "tan"']],
			['tan(1)', ['rc' => true, 'error' => null]],
			['tan(1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "tan"']],

			['time()', ['rc' => true, 'error' => null]],
			['time(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "time"']],

			['trim()', ['rc' => false, 'error' => 'invalid number of parameters in function "trim"']],
			['trim("a")', ['rc' => true, 'error' => null]],
			['trim("a", "a")', ['rc' => true, 'error' => null]],
			['trim("a", "a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "trim"']],

			['truncate()', ['rc' => false, 'error' => 'invalid number of parameters in function "truncate"']],
			['truncate(1)', ['rc' => false, 'error' => 'invalid number of parameters in function "truncate"']],
			['truncate(1, 1)', ['rc' => true, 'error' => null]],
			['truncate(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "truncate"']],

			['varpop()', ['rc' => false, 'error' => 'invalid number of parameters in function "varpop"']],
			['varpop(1)', ['rc' => true, 'error' => null]],
			['varpop(1, 1)', ['rc' => true, 'error' => null]],
			['varpop(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "varpop"']],

			['varsamp()', ['rc' => false, 'error' => 'invalid number of parameters in function "varsamp"']],
			['varsamp(1)', ['rc' => true, 'error' => null]],
			['varsamp(1, 1)', ['rc' => true, 'error' => null]],
			['varsamp(1, 1, 1)', ['rc' => false, 'error' => 'invalid number of parameters in function "varsamp"']],

			['xmlxpath()', ['rc' => false, 'error' => 'invalid number of parameters in function "xmlxpath"']],
			['xmlxpath("a")', ['rc' => false, 'error' => 'invalid number of parameters in function "xmlxpath"']],
			['xmlxpath("a", "a")', ['rc' => true, 'error' => null]],
			['xmlxpath("a", "a", "a")', ['rc' => true, 'error' => null]],
			['xmlxpath("a", "a", "a", "a")', ['rc' => false, 'error' => 'invalid number of parameters in function "xmlxpath"']]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testMathFunctionValidator(string $source, array $expected) {
		$expression_parser = new CExpressionParser();
		$math_function_validator = new CMathFunctionValidator([
			'parameters' => (new CMathFunctionData(['calculated' => true]))->getParameters()
		]);
		$expression_parser->parse($source);
		$tokens = $expression_parser->getResult()->getTokens();

		$this->assertSame(CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION, $tokens[0]['type']);
		$this->assertSame($expected, [
			'rc' => $math_function_validator->validate($tokens[0]),
			'error' => $math_function_validator->getError()
		]);
	}
}
