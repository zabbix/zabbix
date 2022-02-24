<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class is used to parse <sec|#num>:<time_shift> trigger parameter.
 */
class CPeriodParser extends CParser {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false   Enable user macros usage in the periods.
	 *   'lldmacros' => false    Enable low-level discovery macros usage in the periods.
	 *
	 * @var array
	 */
	protected $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	/**
	 * @var string
	 */
	private $sec_num = '';

	/**
	 * @var string
	 */
	private $timeshift = '';

	/**
	 * @param array $options
	 * @param bool  $options['lldmacros']
	 */
	public function __construct(array $options) {
		$this->options = $options + $this->options;

		$this->simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros'],
			'with_year' => true
		]);
		$this->relative_time_parser = new CRelativeTimeParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
	}

	/**
	 * Parse period.
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$this->match = '';
		$this->length = 0;
		$this->sec_num = '';
		$this->timeshift = '';

		$p = $pos;
		$sec_num = '';
		$timeshift = '';

		if (preg_match('/^#[0-9]+/', substr($source, $p), $matches)) {
			$sec_num = $matches[0];
			$p += strlen($matches[0]);
		}
		elseif ($this->simple_interval_parser->parse($source, $p) !== self::PARSE_FAIL) {
			$sec_num = $this->simple_interval_parser->match;
			$p += $this->simple_interval_parser->length;
		}
		else {
			return self::PARSE_FAIL;
		}

		if (isset($source[$p]) && $source[$p] === ':') {
			if ($this->relative_time_parser->parse($source, $p + 1) !== self::PARSE_FAIL) {
				$timeshift = $this->relative_time_parser->match;
				$p += $this->relative_time_parser->length + 1;
			}
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->sec_num = $sec_num;
		$this->timeshift = $timeshift;

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Returns the first part of the period.
	 *
	 * @return string
	 */
	public function getSecNum(): string {
		return $this->sec_num;
	}

	/**
	 * Returns the second part of the period.
	 *
	 * @return string
	 */
	public function getTimeshift(): string {
		return $this->timeshift;
	}
}
