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


class CUserMacroParser extends CParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;
	const STATE_END_OF_MACRO = 4;
	public const REGEX_PREFIX = 'regex:';

	private $macro = '';
	private $context = null;
	private $context_quoted = false;
	private $regex = null;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'allow_regex' => false  Enable "regex:" context prefix. This prefix should be accessible in the user macro
	 *                           configuration places (global-, template- and host-level macros) only.
	 *
	 * @var array
	 */
	private $options = [
		'allow_regex' => false
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->error_msgs['empty'] = _('macro is empty');
		$this->error_msgs['unexpected_end'] = _('unexpected end of macro');
	}

	/**
	 * @inheritDoc
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->macro = '';
		$this->context = null;
		$this->context_quoted = false;
		$this->errorClear();
		$this->regex = null;
		$has_regex = false;

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] != '{') {
			$this->errorPos(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}
		$p++;

		if (!isset($source[$p]) || $source[$p] != '$') {
			$this->errorPos(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}
		$p++;

		for (; isset($source[$p]) && $this->isMacroChar($source[$p]); $p++)
			;

		if ($p == $pos + 2 || !isset($source[$p])) {
			$this->errorPos(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}

		$this->macro = substr($source, $pos + 2, $p - $pos - 2);

		if ($source[$p] == '}') {
			$p++;
			$this->length = $p - $pos;
			$this->match = substr($source, $pos, $this->length);

			if (isset($source[$p])) {
				$this->errorPos(substr($source, $pos), $p - $pos);

				return self::PARSE_SUCCESS_CONT;
			}

			return self::PARSE_SUCCESS;
		}

		if ($source[$p] != ':') {
			$this->macro = '';
			$this->errorPos(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->options['allow_regex'] && preg_match("/^\s*".self::REGEX_PREFIX."/", substr($source, $p)) === 1) {
			$has_regex = true;
			$p += strpos(substr($source, $p), self::REGEX_PREFIX) + strlen(self::REGEX_PREFIX);
		}

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
			$this->errorPos(substr($source, $pos), $p - $pos);

			return self::PARSE_FAIL;
		}

		if ($has_regex) {
			$this->regex = $this->context;
			$this->context = null;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		if (isset($source[$p])) {
			$this->errorPos(substr($source, $pos), $p - $pos);

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
	private function isMacroChar(string $c): bool {
		return (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9'));
	}

	/*
	 * Unquotes special symbols in context
	 *
	 * @param string $context
	 *
	 * @return string
	 */
	private function unquoteContext(string $context): string {
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
	public function getMacro(): string {
		return $this->macro;
	}

	/**
	 * Returns parsed macro context.
	 *
	 * @return string|null
	 */
	public function getContext(): ?string {
		return ($this->context !== null && $this->context_quoted)
			? $this->unquoteContext($this->context)
			: $this->context;
	}

	/**
	 * Returns parsed regex string.
	 *
	 * @return string|null
	 */
	public function getRegex(): ?string {
		return ($this->regex !== null && $this->context_quoted) ? $this->unquoteContext($this->regex) : $this->regex;
	}

	/**
	 * Quotes special symbols in context.
	 *
	 * @param string $context
	 * @param bool   $force_quote  true - enclose context in " even if it does not contain any special characters.
	 *                             false - do nothing if the context does not contain any special characters.
	 *
	 * @return string
	 */
	private static function quoteContext(string $context, bool $force_quote = false): string {
		$force_quote = $force_quote
			|| (isset($context[0]) && (strpos(' "', $context[0]) !== false || strpos($context, '}') !== false));

		return $force_quote ? '"'.strtr($context, '"', '\"').'"': $context;
	}

	/**
	 * Returns the full macro without insignificant spaces around the context/regular expression.
	 * The context/regular expression will be quoted only if it contains special characters.
	 *
	 * NOTE: To retrieve the original macro, use the getMatch() method.
	 *
	 * @return string
	 */
	public function getMinifiedMacro(): string {
		if ($this->match === '') {
			return '';
		}

		$macro = '{$'.$this->macro;

		if ($this->context !== null) {
			$macro .= ':'.self::quoteContext($this->getContext());
		}

		if ($this->regex !== null) {
			$macro .= ':regex:'.self::quoteContext($this->getRegex());
		}

		return $macro.'}';
	}
}
