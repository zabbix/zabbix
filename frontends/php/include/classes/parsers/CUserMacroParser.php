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


/**
 * A parser for user macros.
 */
class CUserMacroParser {

	// possible parsing states
	const STATE_UNQUOTED_OPEN = 0;
	const STATE_UNQUOTED_PROGRESS = 1;
	const STATE_QUOTED_OPEN = 2;
	const STATE_QUOTED_CLOSED = 3;
	const STATE_MACRO_CLOSED = 4;

	/**
	 * Set to true if the macro is valid.
	 *
	 * @var bool
	 */
	private $is_valid;

	/**
	 * Postion indicator, where macro name ends. Either before } or :
	 *
	 * @var int
	 */
	private $macro_name_end;

	/**
	 * Sarting postion indicator, where macro context starts. If context is quoted, starts after first ".
	 *
	 * @var int
	 */
	private $context_start;

	/**
	 * Ending position indicator, where the macro context ends. If context is quoted, ends after second ".
	 *
	 * @var int
	 */
	private $context_end;

	/**
	 * Source string.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Error message if the macro is invalid.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * Current position on a parsed element.
	 *
	 * @var integer
	 */
	private $pos;

	/**
	 * Parsed result string
	 *
	 * @var CParserResult
	 */
	private $parse_result;

	/**
	 * @param string    $source			source string to be parsed
	 * @param int       $start_pos		position in string to start from
	 * @param bool		$part			true if macro is a part of item key or trigger expression
	 */
	public function parse($source, $start_pos, $part) {
		$this->source = $source;
		$this->pos = $start_pos;

		// Check macro opening curly brace.
		if (!isset($this->source[$this->pos]) || $this->source[$this->pos] !== '{') {
			$this->setError();
			return;
		}
		$this->pos++;

		// Check if this is a user macro that starts with $.
		if (!isset($this->source[$this->pos]) || $this->source[$this->pos] !== '$') {
			$this->setError();
			return;
		}
		$this->pos++;

		// Make sure there is at least one valid macro character.
		if (!isset($this->source[$this->pos]) || !$this->isMacroChar($this->source[$this->pos])) {
			$this->setError();
			return;
		}
		$this->pos++;

		// Skip the remaining macro chars.
		while (isset($this->source[$this->pos]) && $this->isMacroChar($this->source[$this->pos])) {
			$this->pos++;
		}

		// Parse macro context if present.
		if (isset($this->source[$this->pos]) && $this->source[$this->pos] === ':') {
			$state = self::STATE_UNQUOTED_OPEN;
			$this->macro_name_end = $this->pos - 1;

			while (isset($this->source[++$this->pos])) {
				switch ($this->source[$this->pos]) {
					case '"';
						// A alosing curly brace was alrady met and no quotes are allowed after it. Must fail with error.
						if ($state == self::STATE_MACRO_CLOSED) {
							$this->setError();
							return;
						}

						// A quoted context string has already met and there can be no more quotes. Must fail with error.
						if ($state == self::STATE_QUOTED_CLOSED) {
							$this->setError();
							return;
						}

						/*
						 * If a quote is not escaped with backslash, it is a closing quote for context string.
						 * Other quotes after this one will fail, becouse we have already closed it.
						 */
						if ($state == self::STATE_QUOTED_OPEN && $this->source[$this->pos - 1] !== '\\') {
							$state = self::STATE_QUOTED_CLOSED;

							$this->context_end = $this->pos;
						}

						// There was an unqoted context, but once we found the first ", it means this will be a quoted context.
						if ($state == self::STATE_UNQUOTED_OPEN) {
							$state = self::STATE_QUOTED_OPEN;

							$this->context_start = $this->pos + 1;
						}
						break;

					case '}':
						// Closing curly brace was alrady met and no other curly braces are allowed. Must fail with error.
						if ($state == self::STATE_MACRO_CLOSED) {
							$this->setError();
							return;
						}

						// A quoted context string was closed. Close the macro with curly brace.
						if ($state == self::STATE_QUOTED_CLOSED) {
							$state = self::STATE_MACRO_CLOSED;
						}

						/*
						 * Context string was still in open stage. It means no symbols have been found yet. Untill now.
						 * Capture only start of a context, but don't change the state yet.
						 */
						if ($state == self::STATE_UNQUOTED_OPEN) {
							$this->context_start = $this->pos;
						}

						/*
						 * There was an unqoted string and macro is now closed. There should be no more characters after
						 * this.
						 */
						if ($state == self::STATE_UNQUOTED_OPEN || $state == self::STATE_UNQUOTED_PROGRESS) {
							$state = self::STATE_MACRO_CLOSED;

							$this->context_end = $this->pos;
						}

						// Otherwise a quoted string just contains a } which is not the end of the macro.
						break;

					case ' ':
						// Closing curly brace was alrady met and no spaces are allowed after it. Must fail with error.
						if ($state == self::STATE_MACRO_CLOSED) {
							$this->setError();
							return;
						}
						break;

					default:
						// Closing curly brace was alrady met and no other characters are allowed. Must fail with error.
						if ($state == self::STATE_MACRO_CLOSED) {
							$this->setError();
							return;
						}

						/*
						 * An unquoted context was open and is now in progress. It has met a character other than space.
						 * And it means that following quotes will not be considered a quoted string anymore, but a part
						 * of unquoted context string.
						 */
						if ($state == self::STATE_UNQUOTED_OPEN) {
							$state = self::STATE_UNQUOTED_PROGRESS;

							$this->context_start = $this->pos;
						}
				}
			}


			if ($state != self::STATE_MACRO_CLOSED) {
				$this->setError();
				return;
			}
		}
		// Otherwise look for closing curly brace. This is a macro with no context.
		elseif (!isset($this->source[$this->pos]) || $this->source[$this->pos] !== '}') {
			$this->setError();
			return;
		}
		/*
		 * If macro comes as a part of item or trigger, it has trailing characters. Otherwise anything follows a closing
		 * curly brace for marco with no context, the macro is invalid.
		 */
		elseif (!$part && isset($this->source[$this->pos + 1])) {
			$this->setError();
			return;
		}
		else {
			$this->macro_name_end = $this->pos;
		}

		$this->is_valid = true;
		$macro_length = $this->pos - $start_pos + 1;

		// prepare result
		$result = new CParserResult();
		$result->source = $this->source;
		$result->pos = $start_pos;
		$result->length = $macro_length;
		$result->match = substr($this->source, $start_pos, $macro_length);

		$this->parse_result = $result;
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise
	 *
	 * @param string    $c
	 *
	 * @return bool
	 */
	private function isMacroChar($c) {
		if (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9')) {
			return true;
		}

		return false;
	}

	/**
	 * Get macro name without curly braces.
	 *
	 * @return string
	 */
	public function getMacroName() {
		if ($this->is_valid) {
			$name = substr($this->source, 2, $this->macro_name_end - 1);
			return str_replace('}', '', $name);
		}
		else {
			return '';
		}
	}

	/**
	 * Get macro context from source. If macro does not contain context, return null. Return string other otherwise.
	 *
	 * @return null|string
	 */
	public function getContext() {
		if ($this->is_valid) {
			if (!$this->context_start) {
				return null;
			}
			elseif ($this->context_start == $this->context_end) {
				return '';
			}
			else {
				return substr($this->source, $this->context_start, $this->context_end - $this->context_start);
			}
		}
		else {
			return '';
		}
	}

	/**
	 * Mark the macro as invalid and set an error message.
	 */
	private function setError() {
		$this->is_valid = false;

		if (!isset($this->source[$this->pos])) {
			$this->error = ($this->pos == 0) ? _('macro is empty') : _('unexpected end of macro');

			return;
		}

		for ($i = $this->pos, $chunk = '', $maxChunkSize = 50; isset($this->source[$i]); $i++) {
			if (0x80 != (0xc0 & ord($this->source[$i])) && $maxChunkSize-- == 0) {
				break;
			}
			$chunk .= $this->source[$i];
		}

		if (isset($this->source[$i])) {
			$chunk .= ' ...';
		}

		$this->error = _s('incorrect syntax near "%1$s"', $chunk);
	}

	/**
	 * Get the error message.
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Check if macro is valid.
	 */
	public function isValid() {
		return $this->is_valid;
	}

	/**
	 * Get the result of parsed result or return false in case of an error.
	 *
	 * @return bool|CParserResult
	 */
	public function getParseResult() {
		if ($this->is_valid) {
			return $this->parse_result;
		}
		else {
			return false;
		}
	}
}
