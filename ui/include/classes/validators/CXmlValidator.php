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


class CXmlValidator extends CValidator {

	public function validate($value) {
		libxml_use_internal_errors(true);

		if (simplexml_load_string($value, null, LIBXML_IMPORT_FLAGS) === false) {
			$errors = libxml_get_errors();
			libxml_clear_errors();

			if ($errors) {
				$error = reset($errors);
				$this->setError(_s('%1$s [Line: %2$s | Column: %3$s]', '('.$error->code.') '.trim($error->message),
					$error->line, $error->column
				));

				return false;
			}

			$this->setError(_('XML is expected'));

			return false;
		}

		return true;
	}
}
