<?php declare(strict_types = 0);
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


/**
 *  A parser for function macros like "{?<trigger expression>}".
 */
class C10ExpressionMacroParser extends CParser {

	/**
	 * @var C10TriggerExpression
	 */
	protected $trigger_expression_parser;

	/**
	 * Set up necessary parsers.
	 */
	public function __construct() {
		$this->trigger_expression_parser = new C10TriggerExpression([
			'host_macro' => ['{HOST.HOST}']
		]);
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!isset($source[$p]) || substr($source, $p, 2) !== '{?') {
			$this->errorPos($source, $p);

			return CParser::PARSE_FAIL;
		}
		$p += 2;

		$this->trigger_expression_parser->parse(substr($source, $p));

		if ($this->trigger_expression_parser->error_type !== C10TriggerExpression::ERROR_UNPARSED_CONTENT) {
			$this->errorPos($source, $p + $this->trigger_expression_parser->error_pos);

			return CParser::PARSE_FAIL;
		}
		$p += $this->trigger_expression_parser->error_pos;

		if (!isset($source[$p]) || $source[$p] !== '}') {
			$this->errorPos($source, $p);

			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS);
	}
}
