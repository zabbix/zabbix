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


class CCalcFormulaValidator extends CValidator
{
	protected bool $lldmacros = false;

	public function __construct(array $options = []) {
		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = (bool) $options['lldmacros'];
		}
	}

	public function validate($value) {

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $this->lldmacros,
			'calculated' => true,
			'host_macro' => true,
			'empty_host' => true
		]);

		if ($expression_parser->parse($value) != CParser::PARSE_SUCCESS) {
			$this->setError($expression_parser->getError());

			return false;
		}

		$expression_validator = new CExpressionValidator([
			'usermacros' => true,
			'lldmacros' => $this->lldmacros,
			'calculated' => true
		]);

		if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
			$this->setError($expression_validator->getError());

			return false;
		}

		return true;
	}
}
