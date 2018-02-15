<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * A parser for flexible intervals.
 */
class CFlexibleIntervalParser extends CParser {

	private $simple_interval_parser;
	private $time_period_parser;

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

		$p = $pos;

		if ($this->simple_interval_parser->parse($source, $p) == self::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->simple_interval_parser->getLength();

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->time_period_parser->parse($source, $p) == self::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->time_period_parser->getLength();

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
