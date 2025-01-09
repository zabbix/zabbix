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


abstract class CParser {

	const PARSE_FAIL = -1;
	const PARSE_SUCCESS = 0;
	const PARSE_SUCCESS_CONT = 1;

	protected $length = 0;
	protected $match = '';
	protected $error_source = false;
	protected $error_pos = 0;
	protected $error_msgs = [
		'empty' => 'string is empty',
		'unexpected_end' => 'unexpected end of string'
	];

	/**
	 * Try to parse the string starting from the given position.
	 *
	 * @param string	$source		string to parse
	 * @param int 		$pos		position to start from
	 *
	 * @return int
	 */
	abstract public function parse($source, $pos = 0);

	/**
	 * Returns length of the parsed element.
	 *
	 * @return int
	 */
	public function getLength() {
		return $this->length;
	}

	/**
	 * Returns parsed element.
	 *
	 * @return string
	 */
	public function getMatch() {
		return $this->match;
	}

	/**
	 * Returns the error message if string is invalid.
	 *
	 * @return string
	 */
	public function getError(): string {
		if ($this->error_source === false) {
			return '';
		}

		if (!isset($this->error_source[$this->error_pos])) {
			return ($this->error_pos == 0) ? $this->error_msgs['empty'] : $this->error_msgs['unexpected_end'];
		}

		// The error message is prepared here to avoid extra calculations if the error message is not used.
		return $this->errorPosMessage($this->error_source, $this->error_pos);
	}

	/**
	 * Save error source string and position for later use, when error will be retrieved.
	 *
	 * @param string $source
	 * @param int $pos
	 */
	protected function errorPos($source, $pos): void {
		$this->error_source = $source;
		$this->error_pos = $pos;
	}

	/**
	 * Clears error, when parse is used multiple times with same parser.
	 */
	protected function errorClear(): void {
		$this->error_source = false;
		$this->error_pos = 0;
	}

	/**
	 * Prepares error message for incorrect syntax at position.
	 *
	 * @param string $source
	 * @param int $pos
	 *
	 * @return string
	 */
	protected function errorPosMessage($source, $pos): string {
		$maxChunkSize = 50;
		$chunk = substr($source, $pos);

		if (mb_strlen($chunk) > $maxChunkSize) {
			$chunk = mb_substr($chunk, 0, $maxChunkSize) . ' ...';
		}

		return _s('incorrect syntax near "%1$s"', $chunk);
	}
}
