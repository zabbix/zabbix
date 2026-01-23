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


class CUrlValidator extends CValidator {

	/**
	 * @var bool If set to be true, URLs containing user macros will be considered as valid.
	 */
	protected bool $allow_user_macro = true;

	/**
	 * @var bool If set to be true, URLs containing {EVENT.TAGS.<ref>} macros will be considered as valid.
	 */
	protected bool $allow_event_tags_macro = false;

	/**
	 * @var bool If set to be true, URLs containing {MANUALINPUT} macros will be considered as valid.
	 */
	protected bool $allow_manualinput_macro = false;

	public function validate($value) {
		$options = [
			'allow_user_macro' => $this->allow_user_macro,
			'allow_event_tags_macro' => $this->allow_event_tags_macro,
			'allow_manualinput_macro' => $this->allow_manualinput_macro
		];

		if (!CHtmlUrlValidator::validate((string) $value, $options)) {
			$this->setError(_('unacceptable URL'));

			return false;
		}

		return true;
	}
}
