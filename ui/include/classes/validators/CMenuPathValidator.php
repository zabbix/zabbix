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

	protected bool $strict = false;

	public function validate($value): bool {
		$folders = splitPath($value);
		$folders = array_map('trim', $folders);
		$count = count($folders);

		if ($count == 1) {
			return true;
		}

		// folder1/{empty}/name or folder1/folder2/{empty}
		foreach ($folders as $num => $folder) {
			// Allow the trailing slash.
			$is_not_edge = $num != ($count - 1) && $num != 0;

			if ($folder === '' && ($this->strict || $is_not_edge)) {
				$this->setError(_('directory cannot be empty'));
				return false;
			}
		}

		return true;
	}
}
