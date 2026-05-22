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


/**
 * Class for validating URLs.
 */
class CUrlValidator extends CValidator {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'inventory_macro'    Treat the URL as valid, if it starts with a trigger/host inventory macro:
	 *                        - {INVENTORY.URL.A<1-9>}, {INVENTORY.URL.B<1-9>} and {INVENTORY.URL.C<1-9>}
	 *                          - if set to INVENTORY_URL_MACRO_TRIGGER;
	 *                        - {INVENTORY.URL.A}, {INVENTORY.URL.B} and {INVENTORY.URL.C}
	 *                          - if set to INVENTORY_URL_MACRO_HOST.
	 *   'user_macro'         If true, treat the URL as valid whenever it includes a user macro.
	 *   'event_tags_macro'   If true, treat the URL as valid whenever it includes an {EVENT.TAGS.<ref>} macro.
	 *   'manualinput_macro'  If true, treat the URL as valid whenever it includes a {MANUALINPUT} macro.
	 *   'schemes'            If not null, the URL scheme will be validated against the provided list.
	 *                        The list is expected to contain schemes in lower case.
	 *                        Scheme validation won't take place if the URL does not contain a scheme component.
	 *
	 * @var array
	 */
	private array $options = [
		'inventory_macro' => INVENTORY_URL_MACRO_NONE,
		'user_macro' => false,
		'event_tags_macro' => false,
		'manualinput_macro' => false,
		'schemes' => null
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * @param string $value  URL string to validate.
	 *
	 * @return bool
	 */
	public function validate($value): bool {
		if ($this->options['inventory_macro'] != INVENTORY_URL_MACRO_NONE && $this->startsWithInventoryMacro($value)) {
			return true;
		}

		if (($this->options['user_macro'] === true || $this->options['event_tags_macro'] === true
				|| $this->options['manualinput_macro'] === true)
			&& $this->containsMacro($value)) {
			return true;
		}

		return $this->validateUrl($value);
	}

	protected function startsWithInventoryMacro(string $value): bool {
		$parser_options = [
			'macros' => ['{INVENTORY.URL.A}', '{INVENTORY.URL.B}', '{INVENTORY.URL.C}'],
			'ref_type' => $this->options['inventory_macro'] == INVENTORY_URL_MACRO_TRIGGER
				? CMacroParser::REFERENCE_NUMERIC
				: CMacroParser::REFERENCE_NONE
		];

		$macro_parsers = [
			new CMacroParser($parser_options),
			new CMacroFunctionParser($parser_options)
		];

		foreach ($macro_parsers as $macro_parser) {
			if ($macro_parser->parse($value, 0) != CParser::PARSE_FAIL) {
				return true;
			}
		}

		return false;
	}

	protected function containsMacro(string $value): bool {
		$macro_parsers = [];

		if ($this->options['user_macro'] === true) {
			array_push($macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}

		if ($this->options['event_tags_macro'] === true) {
			$parser_options = [
				'macros' => ['{EVENT.TAGS}'],
				'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC
			];
			array_push($macro_parsers, new CMacroParser($parser_options), new CMacroFunctionParser($parser_options));
		}

		if ($this->options['manualinput_macro'] === true) {
			$macro_parsers[] = new CMacroParser(['macros' => ['{MANUALINPUT}']]);
		}

		if ($macro_parsers) {
			for ($pos = strpos($value, '{'); $pos !== false; $pos = strpos($value, '{', $pos + 1)) {
				foreach ($macro_parsers as $macro_parser) {
					if ($macro_parser->parse($value, $pos) != CParser::PARSE_FAIL) {
						return true;
					}
				}
			}
		}

		return false;
	}

	protected function validateUrl(string $value): bool {
		$value = preg_replace('/[\r\n\t]/', '', trim($value, "\x00..\x20"));

		if ($value === '') {
			$this->setError(_('unacceptable URL'));

			return false;
		}

		$url_parts = parse_url($value);

		if (!$url_parts) {
			$this->setError(_('unacceptable URL'));

			return false;
		}

		if (array_key_exists('scheme', $url_parts) && $this->options['schemes'] !== null) {
			if (!in_array(strtolower($url_parts['scheme']), $this->options['schemes'], true)) {
				$this->setError(_('unacceptable URL scheme'));

				return false;
			}
		}

		if (!array_key_exists('host', $url_parts) && !array_key_exists('path', $url_parts)) {
			$this->setError(_('unacceptable URL'));

			return false;
		}

		return true;
	}
}
