<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CHtmlUrlValidator {

	/**
	 * URL is validated if schema validation is enabled (see VALIDATE_URI_SCHEMES).
	 *
	 * Relative URL should start with .php file name.
	 * Absolute URL schema must match schemes mentioned in ZBX_URL_VALID_SCHEMES comma separated list.
	 *
	 * @static
	 *
	 * @param string $url                              URL string to validate.
	 * @param array  $options
	 * @param bool   $options[allow_user_macro]        If set to be true, URLs containing user macros will be considered
	 *                                                 as valid.
	 * @param int    $options[allow_inventory_macro]   Enables the usage of trigger/host inventory macros:
	 *                                                  - {INVENTORY.URL.A<1-9>}, {INVENTORY.URL.B<1-9>} and
	 *                                                    {INVENTORY.URL.C<1-9>} if set to INVENTORY_URL_MACRO_TRIGGER;
	 *                                                  - {INVENTORY.URL.A}, {INVENTORY.URL.B} and {INVENTORY.URL.C} if
	 *                                                    set to INVENTORY_URL_MACRO_HOST;
	 * @param bool   $options[allow_event_tags_macro]  If set to be true, URLs containing {EVENT.TAGS.<ref>} macros will
	 *                                                 be considered as valid.
	 * @param bool   $options[validate_uri_schemes]    Parameter allows to overwrite global switch VALIDATE_URI_SCHEMES
	 *                                                 for specific uses.
	 *
	 * @return bool
	 */
	public static function validate($url, array $options = []) {
		$options += [
			'allow_user_macro' => true,
			'allow_event_tags_macro' => false,
			'allow_inventory_macro' => INVENTORY_URL_MACRO_NONE,
			'validate_uri_schemes' => VALIDATE_URI_SCHEMES
		];

		if ($options['validate_uri_schemes'] === false) {
			return true;
		}

		if ($options['allow_inventory_macro'] != INVENTORY_URL_MACRO_NONE) {
			$macro_parser = new CMacroParser(['{INVENTORY.URL.A}', '{INVENTORY.URL.B}', '{INVENTORY.URL.C}'], [
				'ref_type' => ($options['allow_inventory_macro'] == INVENTORY_URL_MACRO_TRIGGER)
					? CMacroParser::REFERENCE_NUMERIC
					: CMacroParser::REFERENCE_NONE
			]);

			// Macros allowed only at the beginning of $url.
			if ($macro_parser->parse($url, 0) != CParser::PARSE_FAIL) {
				return true;
			}
		}

		if ($options['allow_event_tags_macro'] === true) {
			$macro_parser = new CMacroParser(['{EVENT.TAGS}'], ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC]);

			for ($pos = strpos($url, '{'); $pos !== false; $pos = strpos($url, '{', $pos + 1)) {
				if ($macro_parser->parse($url, $pos) != CParser::PARSE_FAIL) {
					return true;
				}
			}
		}

		if ($options['allow_user_macro'] === true) {
			$user_macro_parser = new CUserMacroParser();

			for ($pos = strpos($url, '{'); $pos !== false; $pos = strpos($url, '{', $pos + 1)) {
				if ($user_macro_parser->parse($url, $pos) != CParser::PARSE_FAIL) {
					return true;
				}
			}
		}

		$url = parse_url($url);
		$allowed_schemes = explode(',', strtolower(ZBX_URI_VALID_SCHEMES));

		return ($url && ((array_key_exists('scheme', $url) && in_array(strtolower($url['scheme']), $allowed_schemes))
			|| (array_key_exists('path', $url) && preg_match('/^[a-z_\.]+\.php/i', $url['path']) == 1)
		));
	}
}
