<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CConditionFormulaParser extends CParser {

	const STATE_AFTER_OPEN_BRACE = 0;
	const STATE_AFTER_OPERATOR = 1;
	const STATE_AFTER_CLOSE_BRACE = 2;
	const STATE_AFTER_CONSTANT = 3;
	const STATE_AFTER_NOT_OPERATOR = 4;

	private string $error;

	/**
	 * Array of unique constants used in the formula.
	 */
	private array $constants = [];

	/**
	 * Parses the given condition formula.
	 *
	 * Example:
	 *   A and (B and C)
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->error = '';
		$this->match = '';
		$this->length = 0;

		$p = $pos;
		$parsed_pos = 0;

		if (self::parseExpression($source, $p, $this->constants, $parsed_pos)) {
			// Including trailing spaces as part of the expression.
			while (isset($source[$p]) && $source[$p] === ' ') {
				$p++;
			}

			$len = $p - $pos;

			$this->length = $len;
			$this->match = substr($source, $pos, $len);

			if (isset($source[$p])) {
				$this->error = _s('incorrect syntax near "%1$s"', substr($source, $parsed_pos - 1));

				return self::PARSE_SUCCESS_CONT;
			}

			return CParser::PARSE_SUCCESS;
		}

		$this->error = isset($source[0])
			? _s('incorrect syntax near "%1$s"', substr($source, $parsed_pos == 0 ? 0 : $parsed_pos - 1))
			: _('expression is empty');

		return CParser::PARSE_FAIL;
	}

	private static function parseExpression(string $source, int &$pos, array &$constants, int &$parsed_pos): bool {
		$state = self::STATE_AFTER_OPEN_BRACE;
		$after_space = false;
		$level = 0;
		$p = $pos;
		$_constants = [];

		while (isset($source[$p])) {
			if ($source[$p] === ' ') {
				$after_space = true;
				$p++;
				continue;
			}

			switch ($state) {
				case self::STATE_AFTER_OPEN_BRACE:
					switch ($source[$p]) {
						case '(':
							$level++;
							break;

						default:
							if (self::parseConstant($source, $p, $_constants)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$constants = $_constants;
								}
							}
							elseif (self::parseNot($source, $p)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_OPERATOR:
					switch ($source[$p]) {
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parseConstant($source, $p, $_constants)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$constants = $_constants;
								}
							}
							elseif (self::parseNot($source, $p)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_NOT_OPERATOR:
					switch ($source[$p]) {
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parseConstant($source, $p, $_constants)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$constants = $_constants;
								}
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CLOSE_BRACE:
					switch ($source[$p]) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$level--;

							if ($level == 0) {
								$pos = $p + 1;
								$constants = $_constants;
							}
							break;

						default:
							if (self::parseOperator($source, $p)) {
								$state = self::STATE_AFTER_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CONSTANT:
					switch ($source[$p]) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$state = self::STATE_AFTER_CLOSE_BRACE;
							$level--;

							if ($level == 0) {
								$pos = $p + 1;
								$constants = $_constants;
							}
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parseOperator($source, $p)) {
								$state = self::STATE_AFTER_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;
			}

			$after_space = false;
			$p++;
		}

		$parsed_pos = $p;

		return (bool) $constants;
	}

	/**
	 * Parses a constant and advances the position to its last character.
	 */
	private static function parseConstant(string $source, int &$pos, array &$constants): bool {
		if (preg_match('/^([A-Z]+)/', substr($source, $pos), $matches)) {
			$constants[] = [
				'value' => $matches[1],
				'pos' => $pos
			];

			$pos += strlen($matches[1]) - 1;

			return true;
		}

		return false;
	}

	/**
	 * Parses a keyword and advances the position to its last character.
	 */
	private static function parseNot(string $source, int &$pos): bool {
		if (substr($source, $pos, 3) !== 'not') {
			return false;
		}

		$pos += 2;

		return true;
	}

	/**
	 * Parses an operator and advances the position to its last character.
	 */
	private static function parseOperator(string $source, int &$pos): bool {
		if (preg_match('/^(and|or)/', substr($source, $pos), $matches)) {
			$pos += strlen($matches[1]) - 1;

			return true;
		}

		return false;
	}

	public function getConstants(): array {
		return $this->constants;
	}

	/**
	 * Returns a friendly error message or empty string if expression was parsed successfully.
	 */
	public function getError(): string {
		return $this->error;
	}
}
