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


class CMenuPathValidator extends CValidator {

	public function validate($value): bool {
		// If empty is allowed there is only root folder, return early.
		if ($value === '/') {
			return true;
		}

		$folders = splitPath($value);
		$folders = array_map('trim', $folders);
		$count = count($folders);

		// folder1/{empty}/name or folder1/folder2/{empty}
		foreach ($folders as $num => $folder) {
			// Allow the trailing slash.
			if ($folder === '' && $num != ($count - 1) && $num != 0) {
				return false;
			}
		}

		return true;
	}
}
