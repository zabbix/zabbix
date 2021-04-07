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
		'max',
		'min',
		'length',
		'sum'
	];

	/**
	 * The array containing functions with supported exactly one parameter.
	 *
	 * @var array
	 */
	private $single_parameter_functions = [
		'abs',
		'length'
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
	 * Parser for LLD macros.
	 *
	 * @var CLLDMacroParser
	 */
	protected $lld_macro_parser;

	/**
	 * If set to true, foreach functions will be allowed and validated as first parameter.
	 */
	protected $foreach_function = false;

	/**
	 * Array of function supports foreach function as first parameter.
	 *
	 * @var array
	 */
	protected $parameter_foreach_functions = ['avg', 'max', 'min', 'sum'];

	/**
	 * Array of supported foreach functions.
	 *
	 * @var array
	 */
	protected $foreach_functions = [
		'avg_foreach', 'count_foreach', 'last_foreach', 'max_foreach', 'min_foreach', 'sum_foreach'
	];

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
	 * @param CFunctionParserResult $fn
	 *
	 * @return bool
	 */
	public function validate($fn) {
		$this->setError('');

		if (!in_array($fn->function, $this->allowed)) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Unknown function.'));
			return false;
		}

		if ((in_array($fn->function, $this->single_parameter_functions) && count($fn->params_raw['parameters']) != 1)
				|| count($fn->params_raw['parameters']) == 0) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid number of parameters.'));
			return false;
		}

		$validate_foreach = ($this->foreach_function && in_array($fn->function, $this->parameter_foreach_functions));

		foreach ($fn->params_raw['parameters'] as $param) {
			if ($param instanceof CQueryParserResult) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $param->match));
				return false;
			}
			elseif ($param instanceof CFunctionParserResult) {
				if (in_array($param->function, $this->foreach_functions)) {
					if (!$validate_foreach) {
						$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $param->match));
						return false;
					}

					if (!$this->validateForeachFunction($param)) {
						return false;
					}
				}

				continue;
			}
			elseif (!$this->user_macro_parser->parse($param->match)
					&& $this->number_parser->parse($param->match)
					&& $this->checkString($param->match)
					&& (!$this->lldmacros || $this->lld_macro_parser->parse($param->match))) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $param->match));
				return false;
			}
		}

		return true;
	}

	/**
	 * Will return true for valid foreach functions, false otherwise. Additionally will set error message.
	 * Example of valid function: avg_foreach(/host/key, 5m)
	 *
	 * @param CFunctionParserResult $fn  Validated function.
	 * @return bool
	 */
	protected function validateForeachFunction(CFunctionParserResult $fn): bool {
		$params = $fn->params_raw['parameters'];

		if (!$params || count($params) > 2) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid number of parameters.')
			);
			return false;
		}

		if ((array_shift($params) instanceof CQueryParserResult) === false) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid first parameter.')
			);
			return false;
		}

		if (!$params) {
			return true;
		}

		$parser = new CSimpleIntervalParser();
		$period = array_shift($params);

		if (($period instanceof CFunctionParameterResult) === false
				|| $parser->parse($period->match) === CParser::PARSE_FAIL) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid second parameter.')
			);
			return false;
		}

		return true;
	}

	private function checkString(string $param): bool {
		return preg_match('/^"([^"\\\\]|\\\\["\\\\])*"/', $param);
	}
}
