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
 * Global regexes importer.
 */
class CGlobalRegexImporter extends CImporter {

	/**
	 * Import global regexes.
	 *
	 * @param array $global_regexes
	 */
	public function import(array $global_regexes): void {
		$global_regexes_to_create = [];
		$global_regexes_to_update = [];

		foreach ($global_regexes as $global_regex) {
			$regexpid = $this->referencer->findGlobalRegexidByName($global_regex['name']);

			if ($regexpid !== null && $this->options['global_regexes']['updateExisting']) {
				$global_regex['regexpid'] = $regexpid;

				$global_regexes_to_update[] = $global_regex;
			}
			elseif ($regexpid === null && $this->options['global_regexes']['createMissing']) {
				$global_regexes_to_create[] = $global_regex;
			}
		}

		if ($global_regexes_to_update) {
			API::Regexp()->update($global_regexes_to_update);
		}

		if ($global_regexes_to_create) {
			API::Regexp()->create($global_regexes_to_create);
		}
	}
}
