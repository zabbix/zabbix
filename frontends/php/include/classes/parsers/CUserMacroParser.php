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

	public function __construct($source, $validate = true, $pos = 0) {
		$this->validate = $validate;
		$this->pos = $pos;

		$this->parse($source);
	}

	/**
	 * Parse the given source string. There can be none or multiple macros in string, with contexts, quoted contexts and
	 * without. If $this->validate was set to true, string should be validated and it must find one single macro.
	 * No characters are allowed before or after macro, and contexts should be properly quoted. When $this->validate is
	 * set to false, it does not validate the source sting, it just finds all valid macros in string. The source string,
	 * however, may contain quotes before the macro. If a macro has a quoted context, the context is escaped with
	 * backslashes. For example "{$MACRO:\"abc\"} will be considered a valid macro with quoted context. The valid macros
	 * store additional information where they begin, length, name, context etc.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 */

	private function parse($source) {
		$this->source = $source;

		/*
		 * If there are macros, populate the array with data:
		 * $macros[]['match'] - full match with all the escaped quotes
		 * $macros[]['macro'] - matches only macro with context and trims spaces
		 * $macros[]['poitions']['start'] - starting position of the macro
		 * $macros[]['poitions']['length'] - macro length (with context)
		 * $macros[]['macro_name'] - only macro name
		 * $macros[]['context'] - context with no quotes
		 */
		$this->macros = [];

		// True when there is a quote before macro. For example abd"def{MACRO}
		$quoted_string = false;

		// Macro counter.
		$i = 0;

		// Starting state of new string.
		$state = self::STATE_NEW;

		while (isset($this->source[$this->pos])) {
			// Macro was closed, but there is another character.

			if ($state == self::STATE_MACRO_END) {
				// Encountered a character after closed macro

				if ($this->validate) {
					// There must be no characters after a closed macro. End with error and remove the previous result.
					unset($this->macros[$i-1]);

					$this->setError();
					return;
				}
				else {
					// Continue to parse it as normal string and find new macros.
					$state = self::STATE_NEW;
				}
			}

			if (!array_key_exists($i, $this->macros)) {
				$this->macros[$i] = [
					'match' => '',
					'macro' => '',
					'positions' => ['start' => 0, 'length' => 0],
					'macro_name' => '',
					'context' => ''
				];
			}

			switch ($state) {
				case self::STATE_NEW:
					// Encountered some character at the beginning of string or we are just looking for a new macro.
					switch ($this->source[$this->pos]) {
						case '{':
							// Possible that this will be a new macro. Capture starting position and concatenate chars.
							$this->macros[$i]['match'] = $this->source[$this->pos];
							$this->macros[$i]['macro'] = $this->source[$this->pos];
							$this->macros[$i]['positions']['start'] = $this->pos;

							$state = self::STATE_MACRO_NEW;
							break;

						case '"';
							if ($this->validate) {
								// There must be { at this position.
								unset($this->macros[$i]);
								$this->setError();
								return;
							}
							else {
								/*
								 * In a string there is a quote, so it's possible that macro with quoted context will
								 * look something like this: abc"def{$MACRO:\"abc\"}
								 */
								if (isset($this->source[$this->pos-1]) && $this->source[$this->pos-1] !== '\\') {
									if ($quoted_string) {
										/*
										 * There was already a quote, and this is the second one. Close and start over.
										 * possile valid macro can looke like this: abc"def"ghi{$MACRO:"abc"}
										 */
										$quoted_string = false;
									}
									else {
										// No quotes have been encountered before and this is the first.
										$quoted_string = true;
									}
								}
								else {
									// That is the first character in string, so it is not escaped with backslash.
									$quoted_string = true;
								}
							}
							break;

						default:
							if ($this->validate) {
								// There must be { at this position.
								unset($this->macros[$i]);
								$this->setError();
								return;
							}

							// Else it just skips all other chars untill finds where macro begins.
							break;
					}
					break;

				case self::STATE_MACRO_NEW:
					switch ($this->source[$this->pos]) {
						case '$':
							// Even greater potential that following chars will me a real macro.
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];

							$state = self::STATE_MACRO_BEGIN;
							break;

						default:
							// Found something else after {, so this macro is not valid. Remove it from result.
							unset($this->macros[$i]);

							if ($this->validate) {
								// There must be $ after {.
								$this->setError();
								return;
							}

							$state = self::STATE_NEW;
							break;
					}
					break;

				case self::STATE_MACRO_BEGIN:
					// Now after {$ the next char should be macro char. Otherwise it is not a valid macro.
					if ($this->isMacroChar($this->source[$this->pos])) {
						$this->macros[$i]['macro_name'] = $this->source[$this->pos];
						$this->macros[$i]['match'] .= $this->source[$this->pos];
						$this->macros[$i]['macro'] .= $this->source[$this->pos];

						if ($state != self::STATE_MACRO_PROGRESS) {
							$state = self::STATE_MACRO_PROGRESS;
						}
					}
					else {
						unset($this->macros[$i]);

						if ($this->validate) {
							// There must be at least one valid macro char after {$.
							$this->setError();
							return;
						}

						/*
						 * After {$ there was something else. This is not a valid macro. Reset and look for macros
						 * in the rest of the string.
						 */
						$state = self::STATE_NEW;
					}
					break;

				case self::STATE_MACRO_PROGRESS:
					if ($this->isMacroChar($this->source[$this->pos])) {
						// The following chars are macro chars. Everthing is going fine so far.
						$this->macros[$i]['macro_name'] .= $this->source[$this->pos];
						$this->macros[$i]['match'] .= $this->source[$this->pos];
						$this->macros[$i]['macro'] .= $this->source[$this->pos];
					}
					else {
						// First non macro char.
						switch ($this->source[$this->pos]) {
							case ':':
								// {$MACRO:
								$this->macros[$i]['match'] .= $this->source[$this->pos];
								$this->macros[$i]['macro'] .= $this->source[$this->pos];

								$state = self::STATE_CONTEXT_NEW;
								break;

							case '}':
								// {$MACRO} - The macro ended with no context at all.
								$this->macros[$i]['context'] = null;
								$this->macros[$i]['match'] .= $this->source[$this->pos];
								$this->macros[$i]['macro'] .= $this->source[$this->pos];
								$this->macros[$i]['positions']['length'] =
									$this->pos - $this->macros[$i]['positions']['start'] + 1;

								// Start the next macro, because this one is closed and is valid.
								$i++;

								$state = self::STATE_MACRO_END;
								break;

							default:
								// Found other invalid characers in macro name. This is not valid macro.
								unset($this->macros[$i]);

								if ($this->validate) {
									$this->setError();
									return;
								}

								$state = self::STATE_NEW;
								break;
						}
					}
					break;

				case self::STATE_CONTEXT_NEW:
					// The new context is started, this is the first symbol after :
					switch ($this->source[$this->pos]) {
						case '}':
							// The macro ended with empty context. Example: {$MACRO:}
							$this->macros[$i]['context'] = '';
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];
							$this->macros[$i]['positions']['length'] =
								$this->pos - $this->macros[$i]['positions']['start'] + 1;

							// Start the next macro, because this one is closed and is valid.
							$i++;

							$state = self::STATE_MACRO_END;
							break;

						case '"':
							// Examples: abc{$MACRO:" or abc"{$MACRO:"
							if ($quoted_string) {
								// A quote has been encountered before macro. For example: abc"{$MACRO:"
								$quoted_string = false;
								unset($this->macros[$i]);

								// Continue to parse string as normal string, because this was not a valid macro.
								$state = self::STATE_NEW;
							}
							else {
								/*
								 * There were no quotes before macro and this is the first quote in context.
								 * For example: abc{$MACRO:"
								 */
								$this->macros[$i]['context'] = '';
								$this->macros[$i]['match'] .= $this->source[$this->pos];
								$this->macros[$i]['macro'] .= $this->source[$this->pos];

								$state = self::STATE_CONTEXT_QUOTED_PROGRESS;
							}
							break;

						case ' ':
							/*
							 * In an empty context, there is an empty space, and it is trimmed from result.
							 * For example '{$MACRO:    '
							 */
							$this->macros[$i]['match'] .= ' ';
							break;

						case '\\':
							/*
							 * A backslash and following chars, determines the next state now and not after ".
							 * Examples: {$MACRO:\ or "{$MACRO:\ or "{$MACRO:\\ or "{$MACRO:\"
							 */
							if ($quoted_string) {
								// Example: "{$MACRO:\
								if (isset($this->source[$this->pos+1]) && $this->source[$this->pos+1] === '"') {
									// Example: "{$MACRO:\"
									$this->macros[$i]['context'] = '';
									$this->macros[$i]['match'] .= $this->source[$this->pos].$this->source[$this->pos+1];
									$this->macros[$i]['macro'] .= $this->source[$this->pos+1];

									// Skip next char, since it's "
									$this->pos++;

									$state = self::STATE_CONTEXT_QUOTED_PROGRESS;
								}
								else {
									// Example: "{$MACRO:\\
									$this->macros[$i]['context'] = $this->source[$this->pos];
									$this->macros[$i]['match'] .= $this->source[$this->pos];
									$this->macros[$i]['macro'] .= $this->source[$this->pos];

									$state = self::STATE_CONTEXT_UNQUOTED_PROGRESS;
								}
							}
							else {
								// Example: {$MACRO:\
								$this->macros[$i]['context'] = $this->source[$this->pos];
								$this->macros[$i]['match'] .= $this->source[$this->pos];
								$this->macros[$i]['macro'] .= $this->source[$this->pos];

								$state = self::STATE_CONTEXT_UNQUOTED_PROGRESS;
							}
							break;

						default:
							// Example: {$MACRO:a
							$this->macros[$i]['context'] = $this->source[$this->pos];
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];

							$state = self::STATE_CONTEXT_UNQUOTED_PROGRESS;
							break;
					}
					break;

				case self::STATE_CONTEXT_UNQUOTED_PROGRESS:
					// n-th char in unquoted context {$MACRO:aaaa

					switch ($this->source[$this->pos]) {
						case '}':
							// Example: {$MACRO:aaaa}
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];
							$this->macros[$i]['positions']['length'] =
								$this->pos - $this->macros[$i]['positions']['start'] + 1;

							// Start the next macro, because this one is closed and is valid.
							$i++;

							$state = self::STATE_MACRO_END;
							break;

						case '"':
							// Examples: abc{$MACRO:def" or abc"{$MACRO:def"
							if ($quoted_string) {
								// Examples: abc"{$MACRO:def" or abc"{$MACRO:def\"
								if ($this->source[$this->pos-1] === '\\') {
									/*
									 * Quote is escaped but it is still unquoted context, since we encountered other
									 * chars before the (escaped) quote. Example: abc"{$MACRO:def\"
									 */
									$this->macros[$i]['context'] .= $this->source[$this->pos];
									$this->macros[$i]['match'] .= $this->source[$this->pos];
									$this->macros[$i]['macro'] .= $this->source[$this->pos];
								}
								else {
									/*
									 * Quote is not escaped, it closes the string. Renders the macro invalid.
									 * Example: abc"{$MACRO:def"
									 */
									$quoted_string = false;
									unset($this->macros[$i]);

									$state = self::STATE_NEW;
								}
							}
							else {
								// Example: abc{$MACRO:def"
								$this->macros[$i]['context'] .= $this->source[$this->pos];
								$this->macros[$i]['match'] .= $this->source[$this->pos];
								$this->macros[$i]['macro'] .= $this->source[$this->pos];
							}
							break;

						default:
							// Example: {$MACRO:aaaaaaaaabbbbbbb
							$this->macros[$i]['context'] .= $this->source[$this->pos];
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];
							break;
					}
					break;

				case self::STATE_CONTEXT_QUOTED_PROGRESS:
					switch ($this->source[$this->pos]) {
						case '"':
							// That's a second quote in a quoted context, that probably closes the context. Or not.
							if ($this->source[$this->pos-1] === '\\') {
								if ($quoted_string) {
									// Examples: "{$MACRO:\"abc\" or "{$MACRO:\"abc\\"
									if ($this->source[$this->pos-2] === '\\') {
										/*
										 * Context has escaped quote inside it. Example: "{$MACRO:\"abc\\"
										 * if there would be no quote before macro, it would look like {$MACRO:"abc\"
										 */
										$this->macros[$i]['context'] = substr($this->macros[$i]['context'], 0, -2);
										$this->macros[$i]['context'] .= $this->source[$this->pos];
										$this->macros[$i]['match'] .= $this->source[$this->pos];
										$this->macros[$i]['macro'] = substr($this->macros[$i]['macro'], 0, -1);
										$this->macros[$i]['macro'] .= $this->source[$this->pos];
									}
									else {
										// Example: "{$MACRO:\"abc\"
										$this->macros[$i]['match'] .= $this->source[$this->pos];
										$this->macros[$i]['macro'] = substr($this->macros[$i]['macro'], 0, -1);
										$this->macros[$i]['macro'] .= $this->source[$this->pos];
										$this->macros[$i]['context'] = substr($this->macros[$i]['context'], 0, -1);

										$state = self::STATE_CONTEXT_QUOTED_PROGRESS_END;
									}
								}
								else {
									// Example: {$MACRO:"\"
									$this->macros[$i]['context'] .= $this->source[$this->pos];
									$this->macros[$i]['match'] .= $this->source[$this->pos];
									$this->macros[$i]['macro'] .= $this->source[$this->pos];
								}
							}
							else {
								if ($quoted_string) {
									// Example: "{$MACRO:\""
									$quoted_string = false;
									unset($this->macros[$i]);

									$state = self::STATE_NEW;
								}
								else {
									// Example: {$MACRO:"abc"
									$this->macros[$i]['match'] .= $this->source[$this->pos];
									$this->macros[$i]['macro'] .= $this->source[$this->pos];

									$state = self::STATE_CONTEXT_QUOTED_PROGRESS_END;
								}
							}
							break;

						default:
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];
							$this->macros[$i]['context'] .= $this->source[$this->pos];
							break;
					}
					break;

				case self::STATE_CONTEXT_QUOTED_PROGRESS_END:
					switch ($this->source[$this->pos]) {
						case '}':
							// Examples: {$MACRO:"abc"} or "{$MACRO:\"abc\"}
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							$this->macros[$i]['macro'] .= $this->source[$this->pos];
							$this->macros[$i]['positions']['length'] =
								$this->pos - $this->macros[$i]['positions']['start'] + 1;

							$i++;

							$state = self::STATE_MACRO_END;
							break;

						case ' ':
							// Spaces after quoted context are allowed. They are not counted towards context.
							$this->macros[$i]['match'] .= $this->source[$this->pos];
							break;

						case '"':
							// Example: {$MACRO:"abc"" or "{$MACRO:\"abc\""
							if ($quoted_string) {
								// Examples: "{$MACRO:\"abc\"" or "{$MACRO:\"abc\"\"
								if ($this->source[$this->pos-1] !== '\\') {
									$quoted_string = false;
								}
							}
							// Else it is for example: {$MACRO:"abc""

							unset($this->macros[$i]);

							if ($this->validate) {
								$this->setError();
								return;
							}

							$state = self::STATE_NEW;
							break;

						default:
							/*
							 * Encountered other character after quoted context.
							 * Examples: {$MACRO:"abc"a or "{$MACRO:\"abc\"a
							 */
							unset($this->macros[$i]);

							if ($this->validate) {
								$this->setError();
								return;
							}

							$state = self::STATE_NEW;
							break;
					}
					break;
			}

			$this->pos++;
		}

		// Check trailing chars after valid macro.
		if ($state != self::STATE_MACRO_END) {
			unset($this->macros[$i]);

			if ($this->validate) {
				$this->setError();
				return;
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
