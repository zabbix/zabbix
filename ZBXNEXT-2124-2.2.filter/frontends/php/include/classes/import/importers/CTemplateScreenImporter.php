<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CTemplateScreenImporter extends CAbstractScreenImporter {

	/**
	 * Import template screens.
	 *
	 * @param array $allScreens
	 *
	 * @return void
	 */
	public function import(array $allScreens) {
		$screensToCreate = array();
		$screensToUpdate = array();
		foreach ($allScreens as $template => $screens) {
			// TODO: select all at once out of loop
			$dbScreens = DBselect('SELECT s.screenid,s.name FROM screens s WHERE'.
					' s.templateid='.zbx_dbstr($this->referencer->resolveTemplate($template)).
					' AND '.dbConditionString('s.name', array_keys($screens)));
			while ($dbScreen = DBfetch($dbScreens)) {
				$screens[$dbScreen['name']]['screenid'] = $dbScreen['screenid'];
			}

			foreach ($screens as $screen) {
				$screen = $this->resolveScreenReferences($screen);
				if (isset($screen['screenid'])) {
					$screensToUpdate[] = $screen;
				}
				else {
					$screen['templateid'] = $this->referencer->resolveTemplate($template);
					$screensToCreate[] = $screen;
				}
			}
		}

		if ($this->options['templateScreens']['createMissing'] && $screensToCreate) {
			API::TemplateScreen()->create($screensToCreate);
		}
		if ($this->options['templateScreens']['updateExisting'] && $screensToUpdate) {
			API::TemplateScreen()->update($screensToUpdate);
		}
	}
}
