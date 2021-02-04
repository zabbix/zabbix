<?php
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
 * Class is used to validate and parse a trigger function like func(/host/key, period, params).
 */
class CTriggerFunctionParser extends CFunctionParser {

	/**
	 * Parser for item keys.
	 *
	 * @var CItemKey
	 */
	private $item_key_parser;

	/**
	 * Parser for host names.
	 *
	 * @var CHostNameParser
	 */
	private $host_name_parser;

	private $host = '';
	private $item = '';

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
		$this->item_key_parser = new CItemKey();
		$this->host_name_parser = new CHostNameParser();
	}

	/**
	 * Parse a trigger function and parameters and put them into $this->params_raw array.
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$this->length = 0;
		$this->match = '';
		$this->function = '';
		$this->parameters = '';
		$this->params_raw = [];

		for ($p = $pos; isset($source[$p]) && $this->isFunctionChar($source[$p]); $p++) {
		}

		if ($p == $pos) {
			return self::PARSE_FAIL;
		}

		$p2 = $p;

		$params_raw = [
			'type' => self::PARAM_ARRAY,
			'raw' => '',
			'pos' => $p - $pos,
			'parameters' => []
		];
		if (!$this->parseFunctionParameters($source, $p, $params_raw['parameters'])) {
			return self::PARSE_FAIL;
		}

		$params_raw['raw'] = substr($source, $p2, $p - $p2);

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->function = substr($source, $pos, $p2 - $pos);
		$this->parameters = substr($source, $p2 + 1, $p - $p2 - 2);
		$this->params_raw = $params_raw;

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 * @param array  $parameters
	 *
	 * @return bool
	 */
	protected function parseFunctionParameters(string $source, int &$pos, array &$parameters): bool {
		if (!isset($source[$pos]) || $source[$pos] !== '(') {
			return false;
		}

		$_parameters = [];
		$state = self::STATE_NEW;
		$num = 0;

		for ($p = $pos + 1; isset($source[$p]); $p++) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($source[$p]) {
						case ' ':
							break;

						case ',':
							if ($num == 0) {
								break 2;
							}

							$_parameters[$num++] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => $p - $pos
							];
							break;

						case ')':
							if ($num == 0) {
								break 2;
							}

							$_parameters[$num] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => $p - $pos
							];
							$state = self::STATE_END_OF_PARAMS;
							break;

						case '"':
							if ($num == 0) {
								break 2;
							}

							$_parameters[$num] = [
								'type' => self::PARAM_QUOTED,
								'raw' => $source[$p],
								'pos' => $p - $pos
							];
							$state = self::STATE_QUOTED;
							break;

						default:
							if ($num == 0) {
								if ($this->parseItem($source, $p)) {
									$_parameters[$num] = [
										'type' => self::PARAM_UNQUOTED,
										'raw' => substr($source, $pos + 1, $p - $pos),
										'pos' => 1
									];
									$state = self::STATE_UNQUOTED;
									break;
								}

								break 2;
							}

							$_parameters[$num] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => $source[$p],
								'pos' => $p - $pos
							];
							$state = self::STATE_UNQUOTED;
					}
					break;

				// end of parameter
				case self::STATE_END:
					switch ($source[$p]) {
						case ' ':
							break;

						case ',':
							$state = self::STATE_NEW;
							$num++;
							break;

						case ')':
							$state = self::STATE_END_OF_PARAMS;
							break;

						default:
							break 3;
					}
					break;

				// an unquoted parameter
				case self::STATE_UNQUOTED:
					switch ($source[$p]) {
						case ')':
							$state = self::STATE_END_OF_PARAMS;
							break;

						case ',':
							$state = self::STATE_NEW;
							$num++;
							break;

						default:
							$_parameters[$num]['raw'] .= $source[$p];
					}
					break;

				// a quoted parameter
				case self::STATE_QUOTED:
					$_parameters[$num]['raw'] .= $source[$p];

					if ($source[$p] === '"' && $source[$p - 1] !== '\\') {
						$state = self::STATE_END;
					}
					break;

				// end of parameters
				case self::STATE_END_OF_PARAMS:
					break 2;
			}
		}

		if ($state == self::STATE_END_OF_PARAMS) {
			$parameters = $_parameters;
			$pos = $p;

			return true;
		}

		return false;
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return bool
	 */
	private function parseItem(string $source, int &$pos): bool {
		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return false;
		}
		$p++;

		if ($this->host_name_parser->parse($source, $p) == self::PARSE_FAIL) {
			return false;
		}
		$p += $this->host_name_parser->getLength();

		$p2 = $p;

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return false;
		}
		$p++;

		if ($this->item_key_parser->parse($source, $p) == self::PARSE_FAIL) {
			return false;
		}
		$p += $this->item_key_parser->getLength() - 1;

		$this->host = substr($source, $pos + 1, $p2 - $pos - 1);
		$this->item = substr($source, $p2 + 1, $p - $p2);
		$pos = $p;

		return true;
	}

	/**
	 * Returns parsed host.
	 *
	 * @return string
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * Returns parsed item.
	 *
	 * @return string
	 */
	public function getItem(): string {
		return $this->item;
	}
}
