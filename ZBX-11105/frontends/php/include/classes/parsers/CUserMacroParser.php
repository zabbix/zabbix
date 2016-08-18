<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CUserMacroParser extends CParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;
	const STATE_END_OF_MACRO = 4;

	private $macro = '';
	private $context = null;
	private $context_quoted = false;
	private $error = '';

	/**
	 * Returns an error message depending on input parameters.
	 *
	 * @param string $source
	 * @param int $pos
	 *
	 * @return string
	 */
	private function errorMessage($source, $pos) {
		if (!isset($source[$pos])) {
			return ($pos == 0) ? _('macro is empty') : _('unexpected end of macro');
		}

		for ($p = $pos, $chunk = '', $maxChunkSize = 50; isset($source[$p]); $p++) {
			if (0x80 != (0xc0 & ord($source[$p])) && $maxChunkSize-- == 0) {
				break;
			}
			$chunk .= $source[$p];
		}

		if (isset($source[$p])) {
			$chunk .= ' ...';
		}

		return _s('incorrect syntax near "%1$s"', $chunk);
	}

	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->macro = '';
		$this->context = null;
		$this->context_quoted = false;
		$this->error = '';

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] != '{') {
			$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}
		$p++;

		if (!isset($source[$p]) || $source[$p] != '$') {
			$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}
		$p++;

		for (; isset($source[$p]) && $this->isMacroChar($source[$p]); $p++)
			;

		if ($p == $pos + 2 || !isset($source[$p])) {
			$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}

		$this->macro = substr($source, $pos + 2, $p - $pos - 2);

		if ($source[$p] == '}') {
			$p++;
			$this->length = $p - $pos;
			$this->match = substr($source, $pos, $this->length);

			if (isset($source[$p])) {
				$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

				return self::PARSE_SUCCESS_CONT;
			}

			return self::PARSE_SUCCESS;
		}

		if ($source[$p] != ':') {
			$this->macro = '';
			$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}
		$p++;

		$this->context = '';
		$this->context_quoted = false;
		$state = self::STATE_NEW;

		for (; isset($source[$p]); $p++) {
			switch ($state) {
				case self::STATE_NEW:
					switch ($source[$p]) {
						case ' ':
							break;

						case '}':
							$state = self::STATE_END_OF_MACRO;
							break;

						case '"':
							$this->context .= $source[$p];
							$this->context_quoted = true;
							$state = self::STATE_QUOTED;
							break;

						default:
							$this->context .= $source[$p];
							$this->context_quoted = false;
							$state = self::STATE_UNQUOTED;
							break;
					}
					break;

				case self::STATE_QUOTED:
					$this->context .= $source[$p];
					if ($source[$p] == '"' && $source[$p - 1] != '\\') {
						$state = self::STATE_END;
					}
					break;

				case self::STATE_UNQUOTED:
					switch ($source[$p]) {
						case '}':
							$state = self::STATE_END_OF_MACRO;
							break;

						default:
							$this->context .= $source[$p];
							break;
					}
					break;

				case self::STATE_END:
					switch ($source[$p]) {
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
			$this->macro = '';
			$this->context = null;
			$this->context_quoted = false;
			$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		if (isset($source[$p])) {
			$this->error = $this->errorMessage(substr($source, $pos), $p - $pos);

			return self::PARSE_SUCCESS_CONT;
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
	 * Returns parsed macro name.
	 *
	 * @return string
	 */
	public function getMacro() {
		return $this->macro;
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
