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
		'sum'
	];

	/**
	 * The array containing functions with supported exact number of parameters.
	 *
	 * @var array
	 */
	private $number_of_parameters = [
		'abs' => 1,
		'bitand' => 2,
		'length' => 1
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
	 * @param array                  $value
	 * @param CFunctionParserResult  $value['fn']
	 *
	 * @return bool
	 */
	public function validate($value) {
		$this->setError('');

		$fn = $value['fn'];

		if (!in_array($fn->function, $this->allowed)) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Unknown function.'));
			return false;
		}

		if (count($fn->params_raw['parameters']) == 0
				|| (in_array($fn->function, $this->number_of_parameters)
						&& count($fn->params_raw['parameters']) != $this->number_of_parameters[$fn->function])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid number of parameters.'));
			return false;
		}

		foreach ($fn->params_raw['parameters'] as $param) {
			if ($param instanceof CQueryParserResult) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $param->match));
				return false;
			}
			elseif ($param instanceof CFunctionParserResult) {
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

	private function checkString(string $param): bool {
		return preg_match('/^"([^"\\\\]|\\\\["\\\\])*"/', $param);
	}
}
