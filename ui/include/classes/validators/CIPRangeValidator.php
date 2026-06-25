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


class CIPRangeValidator extends CValidator {

	/**
	 * Options passed to CIPRangeParser.
	 *
	 * @var array
	 */
	public array $parser_options = [];

	/**
	 * Maximum allowed IP count inside the range.
	 *
	 * Null means no maximum check.
	 *
	 * @var int|null
	 */
	public $max = null;

	/**
	 * Parser for a range of IP addresses separated by a comma.
	 *
	 * @var CIPRangeParser
	 */
	private CIPRangeParser $ip_range_parser;

	public function __construct(array $options = []) {
		if (array_key_exists('max', $options)) {
			$this->max = $options['max'];
		}
		if (array_key_exists('parser_options', $options) && is_array($options['parser_options'])) {
			$this->parser_options = $options['parser_options'];
		}
		else {
			$this->parser_options = $options;
		}

		$this->ip_range_parser = new CIPRangeParser($this->parser_options);
	}

	public function validate($value): bool {
		if ($this->ip_range_parser->parse($value) != CParser::PARSE_SUCCESS) {
			$this->setError($this->ip_range_parser->getError());

			return false;
		}

		$max_ip_count = $this->ip_range_parser->getMaxIPCount();

		if ($this->max !== null && bccomp($max_ip_count, (string)$this->max) > 0) {
			$this->setError(_s('IP range "%1$s" exceeds "%2$s" address limit',
				$this->ip_range_parser->getMaxIPRange(),
				$this->max
			));

			return false;
		}

		return true;
	}
}
