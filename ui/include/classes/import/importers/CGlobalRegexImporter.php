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
	 * @param array $regexps
	 */
	public function import(array $regexps): void {
		$regexps_to_create = [];
		$regexps_to_update = [];

		foreach ($regexps as $regexp) {
			$regexpid = $this->referencer->findGlobalRegexByName($regexp['name']);

			if ($regexpid !== null && $this->options['global_regexes']['updateExisting']) {
				$regexp['regexpid'] = $regexpid;

				$regexps_to_update[] = $regexp;
			}
			elseif ($regexpid === null && $this->options['global_regexes']['createMissing']) {
				$regexps_to_create[] = $regexp;
			}
		}

		if ($regexps_to_update) {
			API::Regexp()->update($regexps_to_update);
		}

		if ($regexps_to_create) {
			API::Regexp()->create($regexps_to_create);
		}
	}
}
