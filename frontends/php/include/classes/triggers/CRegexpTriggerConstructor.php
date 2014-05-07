<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * A class for implementing conversions used by the trigger wizard.
 */
class CRegexpTriggerConstructor {

	const EXPRESSION_TYPE_MATCH = 0;
	const EXPRESSION_TYPE_NO_MATCH = 1;

	/**
	 * Create a trigger expression from the given expression parts.
	 *
	 * @param string    $host                       host name
	 * @param string    $itemKey                    item key
	 * @param array     $expressions                array of expression parts
	 * @param string    $expressions[]['value']     expression string
	 * @param int       $expressions[]['type']      whether the string should match the expression; supported values:
	 *                                              self::EXPRESSION_TYPE_MATCH and self::EXPRESSION_TYPE_NO_MATCH
	 *
	 * @return bool|string
	 */
	public function getExpressionFromParts($host, $itemKey, array $expressions) {
		$result = '';
		$prefix = $host.':'.$itemKey.'.';

		if (empty($expressions)) {
			error(_('Expression cannot be empty'));
			return false;
		}

		// regexp used to split an expressions into tokens
		$ZBX_PREG_EXPESSION_FUNC_FORMAT = '^(['.ZBX_PREG_PRINT.']*) (and|or) [(]*(([a-zA-Z_.\$]{6,7})(\\((['.ZBX_PREG_PRINT.']+?){0,1}\\)))(['.ZBX_PREG_PRINT.']*)$';
		$functions = array('regexp' => 1, 'iregexp' => 1);
		$expr_array = array();
		$cexpor = 0;
		$startpos = -1;

		foreach ($expressions as $expression) {
			if ($expression['type'] == self::EXPRESSION_TYPE_MATCH) {
				if (!empty($result)) {
					$result.=' or ';
				}
				if ($cexpor == 0) {
					$startpos = zbx_strlen($result);
				}
				$cexpor++;
				$eq_global = '<>0';
			}
			else {
				if (($cexpor > 1) & ($startpos >= 0)) {
					$head = substr($result, 0, $startpos);
					$tail = substr($result, $startpos);
					$result = $head.'('.$tail.')';
				}
				$cexpor = 0;
				$eq_global = '=0';
				if (!empty($result)) {
					$result.=' and ';
				}
			}

			$expr = ' and '.$expression['value'];

			// strip extra spaces around "and" and "or" operators
			$expr = preg_replace('/\s+(and|or)\s+/U', ' $1 ', $expr);

			$expr_array = array();
			$sub_expr_count=0;
			$sub_expr = '';
			$multi = preg_match('/.+(and|or).+/', $expr);

			// split an expression into separate tokens
			// start from the first part of the expression, then move to the next one
			while (preg_match('/'.$ZBX_PREG_EXPESSION_FUNC_FORMAT.'/i', $expr, $arr)) {
				$arr[4] = zbx_strtolower($arr[4]);
				if (!isset($functions[$arr[4]])) {
					error(_('Incorrect function is used').'. ['.$expression['value'].']');
					return false;
				}
				$expr_array[$sub_expr_count]['eq'] = trim($arr[2]);
				$expr_array[$sub_expr_count]['regexp'] = zbx_strtolower($arr[4]).$arr[5];

				$sub_expr_count++;
				$expr = $arr[1];
			}

			if (empty($expr_array)) {
				error(_('Incorrect trigger expression').'. ['.$expression['value'].']');
				return false;
			}

			$expr_array[$sub_expr_count-1]['eq'] = '';

			$sub_eq = '';
			if ($multi > 0) {
				$sub_eq = $eq_global;
			}

			foreach ($expr_array as $id => $expr) {
				$eq = ($expr['eq'] === '') ? '' : ' '.$expr['eq'].' ';
				if ($multi > 0) {
					$sub_expr = $eq.'({'.$prefix.$expr['regexp'].'})'.$sub_eq.$sub_expr;
				}
				else {
					$sub_expr = $eq.$expr['eq'].'{'.$prefix.$expr['regexp'].'}'.$sub_eq.$sub_expr;
				}
			}

			if ($multi > 0) {
				$result .= '('.$sub_expr.')';
			}
			else {
				$result .= '(('.$sub_expr.')'.$eq_global.')';
			}
		}

		if (($cexpor > 1) & ($startpos >= 0)) {
			$head = substr($result, 0, $startpos);
			$tail = substr($result, $startpos);
			$result = $head.'('.$tail.')';
		}

		return $result;
	}

}
