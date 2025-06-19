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


class CTriggerExpressionParser extends CExpressionParser {

	public function parse($source, $pos = 0) {
		if (parent::parse($source, $pos) == self::PARSE_SUCCESS) {
			$expression_validator = new CExpressionValidator([
				'usermacros' => $this->options['usermacros'],
				'lldmacros' => $this->options['lldmacros']
			]);
			$expression_validator->validate($this->getResult()->getTokens());

			$this->error = $expression_validator->getError();

			return $this->error
				? self::PARSE_FAIL
				: self::PARSE_SUCCESS;
		}

		return self::PARSE_FAIL;
	}
}
