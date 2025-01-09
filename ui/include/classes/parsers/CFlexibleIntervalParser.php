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


/**
 * A parser for flexible intervals.
 */
class CFlexibleIntervalParser extends CParser {

	private $simple_interval_parser;
	private $time_period_parser;
	private $update_interval;
	private $time_period;

	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	public function __construct($options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		$this->simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
		$this->time_period_parser = new CTimePeriodParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
	}

	/**
	 * Parse the given flexible interval. The source string can contain macros separated by a forward slash.
	 *
	 * (simple_interval|{$M}|{#M})/(time_period|{$M}|{#M})
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->update_interval = '';
		$this->time_period = '';

		$p = $pos;

		if ($this->simple_interval_parser->parse($source, $p) == self::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$update_interval = $this->simple_interval_parser->getMatch();
		$p += $this->simple_interval_parser->getLength();

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->time_period_parser->parse($source, $p) == self::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$this->update_interval = $update_interval;
		$this->time_period = $this->time_period_parser->getMatch();
		$p += $this->time_period_parser->getLength();

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Returns matched update interval. Can contain macro.
	 *
	 * @return string
	 */
	public function getUpdateInterval() {
		return $this->update_interval;
	}

	/**
	 * Returns matched time period. Can contain macro.
	 *
	 * @return string
	 */
	public function getTimePeriod() {
		return $this->time_period;
	}
}
