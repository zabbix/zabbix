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

	const EXPRESSION_TYPE_INCLUDE = 0;
	const EXPRESSION_TYPE_EXCLUDE = 1;

	public function constructFromExpressions($host, $itemKey, array $expressions) {
		$complite_expr = '';
		$prefix = $host.':'.$itemKey.'.';

		if (empty($expressions)) {
			error(_('Expression cannot be empty'));
			return false;
		}

		$ZBX_PREG_EXPESSION_FUNC_FORMAT = '^(['.ZBX_PREG_PRINT.']*)([&|]{1})[(]*(([a-zA-Z_.\$]{6,7})(\\((['.ZBX_PREG_PRINT.']+?){0,1}\\)))(['.ZBX_PREG_PRINT.']*)$';
		$functions = array('regexp' => 1, 'iregexp' => 1);
		$expr_array = array();
		$cexpor = 0;
		$startpos = -1;

		foreach ($expressions as $expression) {
			$expression['value'] = preg_replace('/\s+(AND){1,2}\s+/U', '&', $expression['value']);
			$expression['value'] = preg_replace('/\s+(OR){1,2}\s+/U', '|', $expression['value']);

			if ($expression['type'] == self::EXPRESSION_TYPE_INCLUDE) {
				if (!empty($complite_expr)) {
					$complite_expr.=' | ';
				}
				if ($cexpor == 0) {
					$startpos = zbx_strlen($complite_expr);
				}
				$cexpor++;
				$eq_global = '#0';
			}
			else {
				if (($cexpor > 1) & ($startpos >= 0)) {
					$head = substr($complite_expr, 0, $startpos);
					$tail = substr($complite_expr, $startpos);
					$complite_expr = $head.'('.$tail.')';
				}
				$cexpor = 0;
				$eq_global = '=0';
				if (!empty($complite_expr)) {
					$complite_expr.=' & ';
				}
			}

			$expr = '&'.$expression['value'];
			$expr = preg_replace('/\s+(\&|\|){1,2}\s+/U', '$1', $expr);

			$expr_array = array();
			$sub_expr_count=0;
			$sub_expr = '';
			$multi = preg_match('/.+(&|\|).+/', $expr);

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
				if ($multi > 0) {
					$sub_expr = $expr['eq'].'({'.$prefix.$expr['regexp'].'})'.$sub_eq.$sub_expr;
				}
				else {
					$sub_expr = $expr['eq'].'{'.$prefix.$expr['regexp'].'}'.$sub_eq.$sub_expr;
				}
			}

			if ($multi > 0) {
				$complite_expr .= '('.$sub_expr.')';
			}
			else {
				$complite_expr .= '(('.$sub_expr.')'.$eq_global.')';
			}
		}

		if (($cexpor > 1) & ($startpos >= 0)) {
			$head = substr($complite_expr, 0, $startpos);
			$tail = substr($complite_expr, $startpos);
			$complite_expr = $head.'('.$tail.')';
		}

		return $complite_expr;
	}

}
