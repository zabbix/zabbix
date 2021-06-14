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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Helper class containing methods for operations with user macros.
 */
class CApiUserMacroHelper {

	/**
	 * Returns macro without spaces and curly braces.
	 *
	 * @param string $macro
	 *
	 * @return string
	 */
	public static function trimMacro(string $macro): string {
		$user_macro_parser = new CUserMacroParser();

		$user_macro_parser->parse($macro);

		$macro = $user_macro_parser->getMacro();
		$context = $user_macro_parser->getContext();
		$regex = $user_macro_parser->getRegex();

		if ($context !== null) {
			$macro .= ':context:'.$context;
		}
		elseif ($regex !== null) {
			$macro .= ':regex:'.$regex;
		}

		return $macro;
	}

	/**
	 * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
	 * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
	 * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
	 *
	 * @static
	 *
	 * @param array  $hostmacros
	 * @param int    $hostmacros[]['hostmacroid']
	 * @param int    $hostmacros[]['hostid']
	 * @param string $hostmacros[]['macro']
	 *
	 * @throws APIException if any of the given macros already exist.
	 */
	public static function checkDuplicates(array $hostmacros) {
		$macro_names = [];
		$existing_macros = [];
		$user_macro_parser = new CUserMacroParser();

		// Parse each macro, get unique names and, if context exists, narrow down the search.
		foreach ($hostmacros as $hostmacro) {
			if (!array_key_exists('macro', $hostmacro)) {
				continue;
			}

			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			$macro_names[] = ($context === null && $regex === null) ? '{$'.$macro_name : '{$'.$macro_name.':';
			$existing_macros[$hostmacro['hostid']] = [];
		}

		if (!$existing_macros) {
			return;
		}

		$options = [
			'output' => ['hostmacroid', 'hostid', 'macro'],
			'filter' => ['hostid' => array_keys($existing_macros)],
			'search' => ['macro' => $macro_names],
			'searchByAny' => true,
			'startSearch' => true
		];

		$db_hostmacros = DBselect(DB::makeSql('hostmacro', $options));

		// Collect existing unique macro names and their contexts for each host.
		while ($db_hostmacro = DBfetch($db_hostmacros)) {
			$user_macro_parser->parse($db_hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			$existing_macros[$db_hostmacro['hostid']][$macro_name][$db_hostmacro['hostmacroid']] =
				['context' => $context, 'regex' => $regex];
		}

		// Compare each macro name and context to existing one.
		foreach ($hostmacros as $hostmacro) {
			if (!array_key_exists('macro', $hostmacro)) {
				continue;
			}

			$hostid = $hostmacro['hostid'];

			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			if (array_key_exists($macro_name, $existing_macros[$hostid])) {
				$has_context = ($context !== null && in_array($context,
					array_column($existing_macros[$hostid][$macro_name], 'context'), true
				));
				$has_regex = ($regex !== null && in_array($regex,
					array_column($existing_macros[$hostid][$macro_name], 'regex'), true
				));
				$is_macro_without_context = ($context === null && $regex === null);

				if ($is_macro_without_context || $has_context || $has_regex) {
					foreach ($existing_macros[$hostid][$macro_name] as $hostmacroid => $macro_details) {
						if ((!array_key_exists('hostmacroid', $hostmacro)
									|| bccomp($hostmacro['hostmacroid'], $hostmacroid) != 0)
								&& $context === $macro_details['context'] && $regex === $macro_details['regex']) {
							$hosts = DB::select('hosts', [
								'output' => ['name'],
								'hostids' => $hostid
							]);

							throw new APIException(ZBX_API_ERROR_PARAMETERS,
								_s('Macro "%1$s" already exists on "%2$s".', $hostmacro['macro'], $hosts[0]['name'])
							);
						}
					}
				}
			}
		}
	}
}
