<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * A converter to convert trigger expression syntax from 5.2 to 5.4.
 */
class C52TriggerConverter extends CConverter {

	protected $parser;

	public function __construct() {
		$this->parser = new C10TriggerExpression(['allow_func_only' => true]);
	}

	/**
	 * Converts trigger expression to new syntax.
	 *
	 * @param array  $trigger_data
	 * @param string $trigger_data['expression']
	 * @param string $trigger_data['host']        (optional)
	 * @param string $trigger_data['item']        (optional)
	 *
	 * @return string
	 */
	public function convert($trigger_data) {

		if (($this->parser->parse($trigger_data['expression'])) !== false) {
			$tokens = $this->parser->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO);

			for ($i = count($tokens) - 1; $i >= 0; $i--) {
				$fn = $tokens[$i]['data'];

				if (array_key_exists('host', $trigger_data) && array_key_exists('item', $trigger_data)) {
					$query = sprintf('/%s/%s', $trigger_data['host'], $trigger_data['item']);
				}
				else {
					$query = sprintf('/%s/%s', $fn['host'], $fn['item']);
				}

				switch ($fn['functionName']) {
					case 'abschange':
						$new_expression = sprintf('abs(last(%1$s,1)-last(%1$s,2))', $query);
						break;

					case 'change':
						$new_expression = sprintf('(last(%1$s,1)-last(%1$s,2))', $query);
						break;

					case 'delta':
						$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
						$params = self::paramsToString($params);
						$new_expression = sprintf('(max(%1$s%2$s)-min(%1$s%2$s))', $query, $params);
						break;

					case 'diff':
						$new_expression = sprintf('(last(%1$s,1)<>last(%1$s,2))', $query);
						break;

					case 'prev':
						$new_expression = sprintf('last(%1$s,2)', $query);
						break;

					case 'trenddelta':
						$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
						$params = self::paramsToString($params);
						$new_expression = sprintf('(trendmax(%1$s%2$s)-trendmin(%1$s%2$s))', $query, $params);
						break;

					case 'iregexp':
					case 'regexp':
						$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
						$new_expression = sprintf('find(%1$s,%2$d,"%3$s","%4$s")', $query, $params[1],
							$tokens[$i]['data']['functionName'], $params[0]
						);
						break;

					case 'str':
						$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
						$new_expression = sprintf('find(%1$s,%2$d,"like","%3$s")', $query, $params[1], $params[0]);
						break;

					case 'strlen':
						$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
						$params = self::paramsToString($params);
						$new_expression = sprintf('length(last(%1$s%2$s))', $query, $params);
						break;

					default:
						$new_expression = sprintf('%s(%s%s)', $fn['functionName'], $query,
							self::paramsToString(self::convertParameters($fn['functionParams'], $fn['functionName']))
						);
						break;
				}

				$trigger_data['expression'] = substr_replace($trigger_data['expression'], $new_expression,
					$tokens[$i]['pos'], $tokens[$i]['length']
				);
			}
		}

		return $trigger_data['expression'];
	}

	private static function convertParameters(array $parameters, string $fn_name): array {
		switch ($fn_name) {
			// (sec|#num,<time_shift>)
			case 'delta':
			case 'avg':
			case 'max':
			case 'min':
			case 'sum':
			// (<sec|#num>,<time_shift>)
			case 'last':
			case 'strlen':
			// (sec|#num,<time_shift>,time,<fit>,<mode>)
			case 'forecast':
			// (sec|#num,<time_shift>,percentage)
			case 'percentile':
			// (sec|#num,<time_shift>,threshold,<fit>)
			case 'timeleft':
				$parameters += ['', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[1] = self::convertTimeshift($parameters[1]);
				if ($parameters[1] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1]);
				break;

			// (<sec|#num>,mask,<time_shift>)
			case 'band':
				$parameters += ['', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[2] = self::convertTimeshift($parameters[2]);
				if ($parameters[2] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[2];
				}
				unset($parameters[2]);
				break;

			// (sec|#num,<pattern>,<operator>,<time_shift>)
			case 'count':
				$parameters += ['', '', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[3] = self::convertTimeshift($parameters[3]);
				if ($parameters[3] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[3];
				}
				unset($parameters[3]);
				break;

			// (sec)
			case 'fuzzytime':
			// (sec,<mode>)
			case 'nodata':
				$parameters += [''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				break;

			// (<pattern>,<sec|#num>)
			case 'iregexp':
			case 'regexp':
			case 'str':
				$parameters += ['', ''];
				$parameters[1] = self::convertParamSec($parameters[1]);
				break;

			// (period,period_shift)
			case 'trendavg':
			case 'trendcount':
			case 'trenddelta':
			case 'trendmax':
			case 'trendmin':
			case 'trendsum':
				$parameters += ['', ''];
				$parameters[0] = self::convertParamPeriod($parameters[0]);
				if ($parameters[1] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1]);
				break;
		}

		array_walk($parameters, function (&$param) {
			$param = quoteFunctionParam($param);
		});

		return array_values($parameters);
	}

	private static function convertParamSec(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>['.ZBX_TIME_SUFFIXES.']{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	private static function convertParamPeriod(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>[hdwMy]{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	private static function convertTimeshift(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>['.ZBX_TIME_SUFFIXES.']{0,1})$/', $param, $m) && $m['num'] > 0)
			? 'now-'.$m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	private static function paramsToString(array $parameters): string {
		$parameters = rtrim(implode(',', $parameters), ',');
		return ($parameters === '') ? '' : ','.$parameters;
	}
}
