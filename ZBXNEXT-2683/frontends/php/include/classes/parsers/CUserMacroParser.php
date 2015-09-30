<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CUserMacroParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;
	const STATE_END_OF_MACRO = 4;

	const PARSE_FAIL = -1;
	const PARSE_SUCCESS = 0;
	const PARSE_SUCCESS_PART = 1;

	private $macro = '';
	private $macro_name = '';
	private $context = null;
	private $context_quoted = false;
	private $error = '';

	/**
	 * Returns an error message depending on input parameters.
	 *
	 * @param string $data
	 * @param int $pos
	 *
	 * @return string
	 */
	private function errorMessage($data, $pos) {
		if (!isset($data[$pos])) {
			return ($pos == 0) ? _('macro is empty') : _('unexpected end of macro');
		}

		for ($p = $pos, $chunk = '', $maxChunkSize = 50; isset($data[$p]); $p++) {
			if (0x80 != (0xc0 & ord($data[$p])) && $maxChunkSize-- == 0) {
				break;
			}
			$chunk .= $data[$p];
		}

		if (isset($data[$p])) {
			$chunk .= ' ...';
		}

		return _s('incorrect syntax near "%1$s"', $chunk);
	}

	public function parse($data, $offset = 0) {
		$this->macro = '';
		$this->macro_name = '';
		$this->context = null;
		$this->context_quoted = false;
		$this->error = '';

		$p = $offset;

		if (!isset($data[$p]) || $data[$p] != '{') {
			$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
			return self::PARSE_FAIL;
		}
		$p++;

		if (!isset($data[$p]) || $data[$p] != '$') {
			$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
			return self::PARSE_FAIL;
		}
		$p++;

		for (; isset($data[$p]) && $this->isMacroChar($data[$p]); $p++)
			;

		if ($p == $offset + 2 || !isset($data[$p])) {
			$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
			return self::PARSE_FAIL;
		}

		$this->macro_name = substr($data, $offset + 2, $p - $offset - 2);

		if ($data[$p] == '}') {
			$p++;
			$this->macro = substr($data, $offset, $p - $offset);

			if (isset($data[$p])) {
				$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
				return self::PARSE_SUCCESS_PART;
			}

			return self::PARSE_SUCCESS;
		}

		if ($data[$p] != ':') {
			$this->macro_name = '';
			$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
			return self::PARSE_FAIL;
		}
		$p++;

		$this->context = '';
		$this->context_quoted = false;
		$state = self::STATE_NEW;

		for (; isset($data[$p]); $p++) {
			switch ($state) {
				case self::STATE_NEW:
					switch ($data[$p]) {
						case ' ':
							break;

						case '}':
							$state = self::STATE_END_OF_MACRO;
							break;

						case '"':
							$this->context .= $data[$p];
							$this->context_quoted = true;
							$state = self::STATE_QUOTED;
							break;

						default:
							$this->context .= $data[$p];
							$this->context_quoted = false;
							$state = self::STATE_UNQUOTED;
							break;
					}
					break;

				case self::STATE_QUOTED:
					$this->context .= $data[$p];
					if ($data[$p] == '"' && $data[$p - 1] != '\\') {
						$state = self::STATE_END;
					}
					break;

				case self::STATE_UNQUOTED:
					switch ($data[$p]) {
						case '}':
							$state = self::STATE_END_OF_MACRO;
							break;

						default:
							$this->context .= $data[$p];
							break;
					}
					break;

				case self::STATE_END:
					switch ($data[$p]) {
						case ' ':
							break;

						case '}':
							$state = self::STATE_END_OF_MACRO;
							break;

						default:
							break 3;
					}
					break;

				case self::STATE_END_OF_MACRO:
					break 2;
			}
		}

		if ($state != self::STATE_END_OF_MACRO) {
			$this->macro_name = '';
			$this->context = null;
			$this->context_quoted = false;
			$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
			return self::PARSE_FAIL;
		}

		$this->macro = substr($data, $offset, $p - $offset);

		if (isset($data[$p])) {
			$this->error = $this->errorMessage(substr($data, $offset), $p - $offset);
			return self::PARSE_SUCCESS_PART;
		}

		return self::PARSE_SUCCESS;
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	private function isMacroChar($c) {
		return (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9'));
	}

	/*
	 * Unquotes special symbols in context
	 *
	 * @param string $context
	 *
	 * @return string
	 */
	private function unquoteContext($context) {
		$unquoted = '';

		for ($p = 1; isset($context[$p]); $p++) {
			if ('\\' == $context[$p] && '"' == $context[$p + 1]) {
				continue;
			}

			$unquoted .= $context[$p];
		}

		return substr($unquoted, 0, -1);
	}

	/**
	 * Returns parsed macro.
	 *
	 * @return string
	 */
	public function getMacro() {
		return $this->macro;
	}

	/**
	 * Returns parsed macro name.
	 *
	 * @return string
	 */
	public function getMacroName() {
		return $this->macro_name;
	}

	/**
	 * Returns parsed macro context.
	 *
	 * @return string
	 */
	public function getContext() {
		return $this->context_quoted ? $this->unquoteContext($this->context) : $this->context;
	}

	/**
	 * Returns the error message if macro is invalid.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}
}
