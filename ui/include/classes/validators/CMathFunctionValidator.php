<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class to validate mathematical functions used in trigger expressions.
 */
class CMathFunctionValidator extends CValidator {

	/**
	 * The array containing valid functions.
	 *
	 * @var array
	 */
	private $allowed = [
		'abs',
		'avg',
		'bitand',
		'max',
		'min',
		'length',
		'sum',
		'date',
		'dayofmonth',
		'dayofweek',
		'now',
		'time'
	];

	/**
	 * The array containing functions with supported exact number of parameters.
	 *
	 * @var array
	 */
	private $number_of_parameters = [
		'abs' => 1,
		'bitand' => 2,
		'length' => 1,
		'date' => 0,
		'dayofmonth' => 0,
		'dayofweek' => 0,
		'now' => 0,
		'time' => 0
	];

	/**
	 * If set to true, LLD macros can be used inside functions and are properly validated using LLD macro parser.
	 *
	 * @var bool
	 */
	private $lldmacros = false;

	/**
	 * Number parser.
	 *
	 * @var CNumberParser
	 */
	protected $number_parser;

	/**
	 * Parser for user macros.
	 *
	 * @var CUserMacroParser
	 */
	protected $user_macro_parser;

	/**
	 * Position at which error was detected.
	 *
	 * @var int
	 */
	public $error_pos;

	/**
	 * Parser for LLD macros.
	 *
	 * @var CLLDMacroParser
	 */
	protected $lld_macro_parser;

	/**
	 * Validate as part of calculated item formula: allow CQueryParserResult as first argument.
	 *
	 * @var bool
	 */
	protected $calculated = false;

	public function __construct(array $options = []) {
		/*
		 * CValidator is an abstract class, so no specific functionality should be bound to it. Thus putting
		 * an option "lldmacros" (or class variable $lldmacros) in it, is not preferred. Without it, class
		 * initialization would fail due to __set(). So instead we create a local variable in this extended class
		 * and remove the option "lldmacros" before calling the parent constructor.
		 */
		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = $options['lldmacros'];
			unset($options['lldmacros']);
		}
		parent::__construct($options);

		// Init parsers.
		$this->user_macro_parser = new CUserMacroParser();
		$this->lld_macro_parser = $this->lldmacros ? new CLLDMacroParser() : null;
		$this->user_macro_parser = new CUserMacroParser();
		$this->number_parser = new CNumberParser([
			'with_minus' => true,
			'with_suffix' => true
		]);
	}

	/**
	 * Validate trigger math function.
	 *
	 * @param CFunctionParserResult  $fn
	 *
	 * @return bool
	 */
	public function validate($fn) {
		$this->setError('');
		$this->error_pos = 0;

		if (!in_array($fn->function, $this->allowed)) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Unknown function.'));
			return false;
		}

		$last_valid_pos = $fn->pos + $fn->params_raw['pos'] + 1;

		if (count($fn->params_raw['parameters']) == 0
				|| (array_key_exists($fn->function, $this->number_of_parameters)
						&& count($fn->params_raw['parameters']) != $this->number_of_parameters[$fn->function])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid number of parameters.'));
			$this->error_pos = $last_valid_pos;

			return false;
		}

		foreach ($fn->params_raw['parameters'] as $param) {
			if ($param instanceof CQueryParserResult && !$this->calculated) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match));
				$this->error_pos = $last_valid_pos;

				return false;
			}
			elseif ($param instanceof CFunctionParserResult) {
				$last_valid_pos = $param->pos;

				continue;
			}
			elseif (!$this->checkValidConstant($param->getValue(true))) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match));
				$this->error_pos = $last_valid_pos;

				return false;
			}

			$last_valid_pos = $param->pos + $param->length;
		}

		return true;
	}

	/**
	 * Check if parameter is valid constant.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function checkValidConstant(string $param): bool {
		if ($this->user_macro_parser->parse($param) == CParser::PARSE_SUCCESS
				|| $this->number_parser->parse($param) == CParser::PARSE_SUCCESS
				|| ($this->lldmacros && $this->lld_macro_parser->parse($param) != CParser::PARSE_SUCCESS)) {
			return true;
		}

		return false;
	}
}
