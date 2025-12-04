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


class CUrlValidator extends CValidator {

	protected bool $allow_user_macro = true;
	protected bool $allow_event_tags_macro = false;

	public function __construct(array $options = []) {
		if (array_key_exists('allow_user_macro', $options)) {
			$this->allow_user_macro = $options['allow_user_macro'];
		}

		if (array_key_exists('allow_event_tags_macro', $options)) {
			$this->allow_event_tags_macro = $options['allow_event_tags_macro'];
		}
	}

	/**
	 * Checks if the given string is:
	 * - either macro or valid url with CHtmlUrlValidator
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$options = [
			'allow_user_macro' => $this->allow_user_macro,
			'allow_event_tags_macro' => $this->allow_event_tags_macro,
			'allow_inventory_macro' => INVENTORY_URL_MACRO_NONE,
			'allow_manualinput_macro' => false,
			'validate_uri_schemes' => (bool) CSettingsHelper::get(CSettingsHelper::VALIDATE_URI_SCHEMES)
		];

		if (!CHtmlUrlValidator::validate((string) $value, $options)) {
			$this->setError(_('unacceptable URL'));

			return false;
		}

		return true;
	}
}
