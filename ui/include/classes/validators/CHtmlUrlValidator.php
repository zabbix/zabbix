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


class CHtmlUrlValidator {

	/**
	 * URL is validated if schema validation is enabled by CSettingsHelper::VALIDATE_URI_SCHEMES parameter.
	 *
	 * Relative URL should start with .php file name.
	 * Absolute URL schema must match the URI schemes comma separated list stored in the DB.
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
	 * @param bool   $options[validate_uri_schemes]    Parameter allows to overwrite global switch
	 *                                                 CSettingsHelper::VALIDATE_URI_SCHEMES for specific uses.
	 *
	 * @return bool
	 */
	public static function validate(string $url, array $options = []): bool {
		$options += [
			'allow_user_macro' => true,
			'allow_event_tags_macro' => false,
			'allow_inventory_macro' => INVENTORY_URL_MACRO_NONE,
			'allow_manualinput_macro' => false,
			'validate_uri_schemes' => (bool) CSettingsHelper::get(CSettingsHelper::VALIDATE_URI_SCHEMES)
		];

		if ($options['validate_uri_schemes'] === false) {
			return true;
		}

		if ($options['allow_inventory_macro'] != INVENTORY_URL_MACRO_NONE) {
			$parser_options = [
				'macros' => ['{INVENTORY.URL.A}', '{INVENTORY.URL.B}', '{INVENTORY.URL.C}'],
				'ref_type' => ($options['allow_inventory_macro'] == INVENTORY_URL_MACRO_TRIGGER)
					? CMacroParser::REFERENCE_NUMERIC
					: CMacroParser::REFERENCE_NONE
			];
			$macro_parsers = [new CMacroParser($parser_options), new CMacroFunctionParser($parser_options)];

			// Macros allowed only at the beginning of $url.
			foreach ($macro_parsers as $macro_parser) {
				if ($macro_parser->parse($url, 0) != CParser::PARSE_FAIL) {
					return true;
				}
			}
		}

		$macro_parsers = [];

		if ($options['allow_event_tags_macro'] === true) {
			$parser_options = [
				'macros' => ['{EVENT.TAGS}'],
				'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC
			];
			array_push($macro_parsers, new CMacroParser($parser_options), new CMacroFunctionParser($parser_options));
		}

		if ($options['allow_user_macro'] === true) {
			array_push($macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}

		if ($options['allow_manualinput_macro'] === true) {
			$macro_parsers[] = new CMacroParser(['macros' => ['{MANUALINPUT}']]);
		}

		if ($macro_parsers) {
			for ($pos = strpos($url, '{'); $pos !== false; $pos = strpos($url, '{', $pos + 1)) {
				foreach ($macro_parsers as $macro_parser) {
					if ($macro_parser->parse($url, $pos) != CParser::PARSE_FAIL) {
						return true;
					}
				}
			}
		}

		$url_parts = parse_url(preg_replace('/[\r\n\t]/', '', trim($url, "\x00..\x1F\x20")));
		if (!$url_parts) {
			return false;
		}

		if (array_key_exists('scheme', $url_parts)) {
			if (!in_array(strtolower($url_parts['scheme']), explode(',', strtolower(CSettingsHelper::get(
					CSettingsHelper::URI_VALID_SCHEMES
			))))) {
				return false;
			}

			if (array_key_exists('host', $url_parts)) {
				return true;
			}

			return array_key_exists('path', $url_parts) && $url_parts['path'] !== '/';
		}

		return array_key_exists('path', $url_parts) && $url_parts['path'] !== '';
	}

	/**
	 * Verifies that URL will not lead to third party pages.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function validateSameSite(string $url): bool {
		$root_path = __DIR__.'/../../../';
		preg_match('/^\/?(?<filename>[a-z0-9_.]+\.php)(\?.*)?$/i', $url, $url_parts);

		return array_key_exists('filename', $url_parts) && file_exists($root_path.$url_parts['filename']);
	}
}
