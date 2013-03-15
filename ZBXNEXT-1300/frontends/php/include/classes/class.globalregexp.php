<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class for regular expressions and Zabbix global expressions.
 * Any string that begins with '@' is treated as Zabbix expression.
 * Data from Zabbix expressions is taken from DB, and cached in static variable.
 *
 * @throws Exception
 */
class GlobalRegExp {

	const ERROR_REGEXP_EMPTY = 1;
	const ERROR_REGEXP_NOT_EXISTS = 2;

	/**
	 * Determine if it's Zabbix expression.
	 *
	 * @var bool
	 */
	protected $isZabbixRegexp;

	/**
	 * If we create simple regular expression this contains itself as a string,
	 * if we create Zabbix expression this contains array of expressions taken from DB.
	 *
	 * @var array|string
	 */
	protected $expression;

	/**
	 * Cache for Zabbix expressions.
	 *
	 * @var array
	 */
	private static $_cachedExpressions = array();

	/**
	 * Checks if expression is valid.
	 *
	 * @static
	 *
	 * @param $regExp
	 *
	 * @throws Exception
	 * @return bool
	 */
	public static function isValid($regExp) {
		if (zbx_empty($regExp)) {
			throw new Exception('Empty expression', self::ERROR_REGEXP_EMPTY);
		}

		if ($regExp[0] == '@') {
			$regExp = substr($regExp, 1);

			$sql = 'SELECT r.regexpid'.
					' FROM regexps r'.
					' WHERE r.name='.zbx_dbstr($regExp);
			if (!DBfetch(DBselect($sql))) {
				throw new Exception(_('Global expression does not exist.'), self::ERROR_REGEXP_NOT_EXISTS);
			}
		}

		return true;
	}

	/**
	 * Initialize expression, gets data from db for Zabbix expressions.
	 *
	 * @param string $regExp
	 *
	 * @throws Exception
	 */
	public function __construct($regExp) {
		if ($regExp[0] == '@') {
			$this->isZabbixRegexp = true;
			$regExp = substr($regExp, 1);

			if (!isset(self::$_cachedExpressions[$regExp])) {
				self::$_cachedExpressions[$regExp] = array();

				$dbRegExps = DBselect(
					'SELECT e.regexpid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive'.
					' FROM expressions e,regexps r'.
					' WHERE e.regexpid=r.regexpid'.
						' AND r.name='.zbx_dbstr($regExp)
				);
				while ($expression = DBfetch($dbRegExps)) {
					self::$_cachedExpressions[$regExp][] = $expression;
				}

				if (empty(self::$_cachedExpressions[$regExp])) {
					unset(self::$_cachedExpressions[$regExp]);
					throw new Exception('Does not exist', self::ERROR_REGEXP_NOT_EXISTS);
				}
			}
			$this->expression = self::$_cachedExpressions[$regExp];
		}
		else {
			$this->isZabbixRegexp = false;
			$this->expression = $regExp;
		}
	}

	/**
	 * @param string $string
	 *
	 * @return bool
	 */
	public function match($string) {
		if ($this->isZabbixRegexp) {
			$result = true;

			foreach ($this->expression as $expression) {
				$result = self::matchExpression($expression, $string);

				if (!$result) {
					break;
				}
			}
		}
		else {
			$result = (bool) preg_match('/'.$this->expression.'/', $string);
		}

		return $result;
	}

	public static function matchExpression(array $expression, $string) {
		if ($expression['expression_type'] == EXPRESSION_TYPE_TRUE || $expression['expression_type'] == EXPRESSION_TYPE_FALSE) {
			$result = self::_matchRegular($expression, $string);
		}
		else {
			$result = self::_matchString($expression, $string);
		}

		return $result;
	}

	/**
	 * Matches expression as regular expression.
	 *
	 * @static
	 *
	 * @param array $expression
	 * @param string $string
	 *
	 * @return bool
	 */
	private static function _matchRegular(array $expression, $string) {
		$pattern = '/'.$expression['expression'].'/';
		if ($expression['case_sensitive']) {
			$pattern .= 'i';
		}

		$expectedResult = ($expression['expression_type'] == EXPRESSION_TYPE_TRUE);

		return preg_match($pattern, $string) == $expectedResult;
	}

	/**
	 * Matches expression as string.
	 *
	 * @static
	 *
	 * @param array $expression
	 * @param string $string
	 *
	 * @return bool
	 */
	private static function _matchString(array $expression, $string) {
		$result = true;

		if ($expression['expression_type'] == EXPRESSION_TYPE_ANY_INCLUDED) {
			$paterns = explode($expression['exp_delimiter'], $expression['expression']);
		}
		else {
			$paterns = array($expression['expression']);
		}

		$expectedResult = $expression['expression_type'] != EXPRESSION_TYPE_NOT_INCLUDED;


		if ($expression['case_sensitive']) {
			foreach ($paterns as $patern) {
				$result = $result && ((zbx_strstr($string, $patern) !== false) == $expectedResult);
			}
		}
		else {
			foreach ($paterns as $patern) {
				$result = $result && ((zbx_stristr($string, $patern) !== false) == $expectedResult);
			}
		}

		return $result;
	}
}
