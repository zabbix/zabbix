<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CScreenImporter extends CAbstractScreenImporter {

	/**
	 * Import screens.
	 *
	 * @param array $screens
	 */
	public function import(array $screens) {
		$screens_to_create = [];
		$screens_to_update = [];

		foreach ($screens as $screen) {
			$screen = $this->resolveScreenReferences($screen);

			if ($screenid = $this->referencer->resolveScreen($screen['name'])) {
				$screen['screenid'] = $screenid;
				$screens_to_update[] = $screen;
			}
			else {
				$screens_to_create[] = $screen;
			}
		}

		if ($this->options['screens']['createMissing'] && $screens_to_create) {
			API::Screen()->create($screens_to_create);
		}

		if ($this->options['screens']['updateExisting'] && $screens_to_update) {
			API::Screen()->update($screens_to_update);
		}
	}
}
