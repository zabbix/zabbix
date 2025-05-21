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


class CItemKeyValidator extends CValidator {

	protected bool $lldmacros = false;

	public function __construct(array $options = []) {
		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = (bool) $options['lldmacros'];
		}
	}

	public function validate($value) {
		$itemkey_parser = new CItemKey();
		$result = $itemkey_parser->parse($value);

		if ($result != CParser::PARSE_SUCCESS) {
			$this->setError($itemkey_parser->getError());

			return false;
		}

		if ($this->lldmacros) {
			$parameters = CMacrosResolverGeneral::getItemKeyParameters($itemkey_parser->getParamsRaw());
			['lldmacros' => $result] = CMacrosResolverGeneral::extractMacros($parameters, ['lldmacros' => true]);

			if (!$result) {
				$this->setError(_('This field must contain at least one low-level discovery macro.'));

				return false;
			}
		}

		return true;
	}
}
