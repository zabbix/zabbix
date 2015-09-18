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

	// Possible parsing states.
	const STATE_NEW = 0;
	const STATE_MACRO_NEW = 1;
	const STATE_MACRO_BEGIN = 2;
	const STATE_MACRO_PROGRESS = 3;
	const STATE_MACRO_END = 4;
	const STATE_CONTEXT_NEW = 5;
	const STATE_CONTEXT_UNQUOTED_PROGRESS = 6;
	const STATE_CONTEXT_QUOTED_PROGRESS = 7;
	const STATE_CONTEXT_QUOTED_PROGRESS_END = 8;

	/**
	 * Source string.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Current position on a parsed element.
	 *
	 * @var integer
	 */
	private $pos = 0;

	/**
	 * Set to true if the macro is valid.
	 *
	 * @var bool
	 */
	private $is_valid = false;

	/**
	 * Error message if the macro is invalid.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Stores all macros found in string.
	 *
	 * @var array
	 */
	private $macros = [];

	/**
	 * Stores the count of macros found in string.
	 *
	 * @var int
	 */
	private $count = 0;

	public function __construct($source, $validate = true, $pos = 0) {
		$this->parse($source, $validate, $pos);
	}

	/**
	 * Parse the given source string. There can be none or multiple macros in string, with contexts, quoted contexts and
	 * without. If $this->validate was set to true, string should be validated and it must find one single macro.
	 * No other characters are allowed before or after macro, and contexts should be properly quoted.
	 * When $this->validate is set to false, it does not validate the source sting, it finds all valid macros in string.
	 * If source string contains a quote before the actual macro, the quoted macro context is NOT escaped with
	 * backslashes. This means that quoted item keys like echo["{$MACRO:\"abc\"}"] should process parameters and quotes
	 * before user macro parsing, so that macro parser receives an unquoted key parameter {$MACRO:"abc"}.
	 * The valid macros store additional information where they begin, length, name, context etc.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 */
	private function parse($source, $validate, $pos) {
		$this->source = $source;
		$this->validate = $validate;
		$this->pos = $pos;
		$prev_pos = 0;

		// Starting state of new string.
		$state = self::STATE_NEW;

		while (isset($this->source[$this->pos])) {
			// Macro was closed, but there is another character.

			if ($state == self::STATE_MACRO_END) {
				// Encountered a character after closed macro.

				if ($this->validate) {
					// There must be no characters after a closed macro. End with error and remove the previous result.
					unset($this->macros[$this->count - 1]);

					$this->setError();
					return;
				}
				else {
					// Continue to parse it as normal string and find new macros.
					$state = self::STATE_NEW;
				}
			}

			if (!array_key_exists($this->count, $this->macros)) {
				$this->macros[$this->count] = [
					'match' => '',
					'macro' => '',
					'pos' => 0,
					'macro_name' => '',
					'context' => ''
				];
			}

			switch ($state) {
				case self::STATE_NEW:
					// Encountered some character at the beginning of string or we are just looking for a new macro.
					switch ($this->source[$this->pos]) {
						case '{':
							// Possibly that this will be a new macro.
							$this->macros[$this->count] = [
								'match' => $this->source[$this->pos],
								'macro' => $this->source[$this->pos],
								'pos' => $this->pos
							];

							$state = self::STATE_MACRO_NEW;
							break;

						default:
							if ($this->validate) {
								// There must be { at this position.
								unset($this->macros[$this->count]);

								$this->setError();
								return;
							}

							// Else it just skips all other chars until it finds where macro begins.
					}
					break;

				case self::STATE_MACRO_NEW:
					switch ($this->source[$this->pos]) {
						case '$':
							// Even greater potential that following chars will me a real macro.
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							$state = self::STATE_MACRO_BEGIN;
							break;

						case '{':
							// Example: {{
							unset($this->macros[$this->count]);

							if ($this->validate) {
								$this->setError();
								return;
							}

							// But is possible that the second { could be start of a new macro.
							$this->macros[$this->count] = [
								'match' => $this->source[$this->pos],
								'macro' => $this->source[$this->pos],
								'pos' => $this->pos
							];
							break;

						default:
							// Instead of $ found something else after {.
							unset($this->macros[$this->count]);

							if ($this->validate) {
								$this->setError();
								return;
							}

							$state = self::STATE_NEW;
					}
					break;

				case self::STATE_MACRO_BEGIN:
					// Now after {$ the next char should be macro char. Otherwise it is not a valid macro.
					if ($this->isMacroChar($this->source[$this->pos])) {
						if ($prev_pos == 0) {
							$prev_pos = $this->pos;
						}

						$this->macros[$this->count]['macro_name'] = $this->source[$this->pos];
						$this->macros[$this->count]['match'] .= $this->source[$this->pos];
						$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

						$state = self::STATE_MACRO_PROGRESS;
					}
					else {
						unset($this->macros[$this->count]);

						if ($this->validate) {
							// There must be at least one valid macro char after {$.
							$this->setError();
							return;
						}

						/*
						 * After {$ there was something else. This is not a valid macro. But if there is another {,
						 * for example {${, it could be beginning of a new macro.
						 */
						switch ($this->source[$this->pos]) {
							case '{':
								$this->macros[$this->count] = [
									'match' => $this->source[$this->pos],
									'macro' => $this->source[$this->pos],
									'pos' => $this->pos
								];

								$state = self::STATE_MACRO_NEW;
								break;

							default:
								$state = self::STATE_NEW;
						}
					}
					break;

				case self::STATE_MACRO_PROGRESS:
					if ($this->isMacroChar($this->source[$this->pos])) {
						// The following chars are macro chars. Everything is going fine so far.
						$this->macros[$this->count]['macro_name'] .= $this->source[$this->pos];
						$this->macros[$this->count]['match'] .= $this->source[$this->pos];
						$this->macros[$this->count]['macro'] .= $this->source[$this->pos];
					}
					else {
						// First non macro char.
						switch ($this->source[$this->pos]) {
							case ':':
								// {$MACRO:
								$this->macros[$this->count]['match'] .= $this->source[$this->pos];
								$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

								$state = self::STATE_CONTEXT_NEW;
								break;

							case '}':
								// {$MACRO} - The macro ended with no context at all.
								$this->macros[$this->count]['context'] = null;
								$this->macros[$this->count]['match'] .= $this->source[$this->pos];
								$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

								// Start the next macro, because this one is closed and is valid.
								$this->count++;

								$state = self::STATE_MACRO_END;
								$prev_pos = 0;
								break;

							case '{':
								/*
								 * At this point the current macro would look something like {$ABC{,
								 * where second { can be beginning of a new macro.
								 */
								unset($this->macros[$this->count]);

								if ($this->validate) {
									$this->setError();
									return;
								}

								if ($prev_pos != 0) {
									$this->pos = $prev_pos;
									$prev_pos = 0;
									$state = self::STATE_NEW;
								}
								break;

							default:
								// Found other invalid characers in macro name. This is not valid macro.
								unset($this->macros[$this->count]);

								if ($this->validate) {
									$this->setError();
									return;
								}

								$state = self::STATE_NEW;
						}
					}
					break;

				case self::STATE_CONTEXT_NEW:
					// The new context is started, this is the first symbol after :
					switch ($this->source[$this->pos]) {
						case '}':
							// The macro ended with empty context. Example: {$MACRO:}
							$this->macros[$this->count]['context'] = '';
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							// Start the next macro, because this one is closed and is valid.
							$this->count++;

							$state = self::STATE_MACRO_END;
							$prev_pos = 0;
							break;

						case '{':
							// Example: {$ABC:{$MACRO

							$this->macros[$this->count]['context'] = $this->source[$this->pos];
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							$state = self::STATE_CONTEXT_UNQUOTED_PROGRESS;
							break;

						case '"':
							/*
							 * There were no quotes before macro and this is the first quote in context.
							 * For example: abc{$MACRO:"
							 */
							$this->macros[$this->count]['context'] = '';
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							$state = self::STATE_CONTEXT_QUOTED_PROGRESS;
							break;

						case ' ':
							/*
							 * In an empty context, there is an empty space, and it is trimmed from result.
							 * For example '{$MACRO:    '
							 */
							$this->macros[$this->count]['match'] .= ' ';
							break;

						default:
							// Example: {$MACRO:a
							$this->macros[$this->count]['context'] = $this->source[$this->pos];
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							$state = self::STATE_CONTEXT_UNQUOTED_PROGRESS;
					}
					break;

				case self::STATE_CONTEXT_UNQUOTED_PROGRESS:
					// n-th char in unquoted context {$MACRO:aaaa

					switch ($this->source[$this->pos]) {
						case '}':
							// Example: {$MACRO:aaaa}
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							// Start the next macro, because this one is closed and is valid.
							$this->count++;

							$state = self::STATE_MACRO_END;
							$prev_pos = 0;
							break;

						default:
							// Example: {$MACRO:aaaaaaaaabbbbbbb
							$this->macros[$this->count]['context'] .= $this->source[$this->pos];
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];
					}
					break;

				case self::STATE_CONTEXT_QUOTED_PROGRESS:
					switch ($this->source[$this->pos]) {
						case '{':
							/*
							 * Example: {$ABC:"abc{$MACRO:}
							 * The { and following chars are part of context, but in case we fail to find closing ",
							 * return to this position and parse the remaining chars.
							 */
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];
							$this->macros[$this->count]['context'] .= $this->source[$this->pos];
							break;

						case '"':
							// That's a second quote in a quoted context, that probably closes the context. Or not.
							if ($this->source[$this->pos - 1] === '\\') {
								// Example: {$MACRO:"abc\"
								$this->macros[$this->count]['context'] .= $this->source[$this->pos];
							}
							else {
								// Example: {$MACRO:"abc"
								$state = self::STATE_CONTEXT_QUOTED_PROGRESS_END;
							}

							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];
							break;

						case "\\":
							// Add to context only " not \".
							if ($this->source[$this->pos + 1] !== '"') {
								$this->macros[$this->count]['context'] .= $this->source[$this->pos];
							}

							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];
							break;

						default:
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];
							$this->macros[$this->count]['context'] .= $this->source[$this->pos];
					}
					break;

				case self::STATE_CONTEXT_QUOTED_PROGRESS_END:
					switch ($this->source[$this->pos]) {
						case '}':
							// Example: {$MACRO:"abc"}
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							$this->macros[$this->count]['macro'] .= $this->source[$this->pos];

							$this->count++;
							$prev_pos = 0;

							$state = self::STATE_MACRO_END;
							break;

						case ' ':
							// Spaces after quoted context are allowed. They are not counted towards context.
							$this->macros[$this->count]['match'] .= $this->source[$this->pos];
							break;

						default:
							/*
							 * Encountered other character after quoted context.
							 * Examples: {$MACRO:"abc"a or {$MACRO:"abc""
							 */
							unset($this->macros[$this->count]);

							if ($this->validate) {
								$this->setError();
								return;
							}

							if ($prev_pos != 0) {
								$this->pos = $prev_pos;
								$prev_pos = 0;
								$state = self::STATE_NEW;
							}
					}
					break;
			}

			$this->pos++;
		}

		// Check trailing chars after valid macro.
		if ($state != self::STATE_MACRO_END) {
			unset($this->macros[$this->count]);

			if ($this->validate) {
				$this->setError();
				return;
			}

			/*
			 * In case macro ended, but there was another macro inside string, go back.
			 * Example: {$ABC:"{$MACRO}
			 * The quoted context is not closed, but it should find that there is a valid macro {$MACRO} inside.
			 */
			if ($prev_pos != 0) {
				$this->parse($source, $validate, $prev_pos);
			}
		}

		if ($this->validate) {
			$this->is_valid = true;
		}
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise.
	 *
	 * @param string $c
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
	 * Get an array valid macros.
	 *
	 * @return array
	 */
	public function getMacros() {
		return $this->macros;
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

		for ($this->count = $this->pos, $chunk = '', $max = 50; isset($this->source[$this->count]); $this->count++) {
			if (0x80 != (0xc0 & ord($this->source[$this->count])) && $max-- == 0) {
				break;
			}
			$chunk .= $this->source[$this->count];
		}

		if (isset($this->source[$this->count])) {
			$chunk .= ' ...';
		}

		$this->error = _s('incorrect syntax near "%1$s"', $chunk);
	}

	/**
	 * Get the error message for invalid macro.
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
}
