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
		if (!$this->options['templateScreens']['createMissing']
				&& !$this->options['templateScreens']['updateExisting']) {
			return;
		}

		$screensToCreate = array();
		$screensToUpdate = array();

		foreach ($allScreens as $template => $screens) {
			$templateId = $this->referencer->resolveTemplate($template);

			foreach ($screens as $screenName => $screen) {
				$screen['screenid'] = $this->referencer->resolveTemplateScreen($templateId, $screenName);

				$screen = $this->resolveScreenReferences($screen);
				if ($screen['screenid']) {
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

	/**
	 * Deletes missing template screens.
	 *
	 * @param array $allScreens
	 */
	public function delete(array $allScreens) {
		if (!$this->options['templateScreens']['deleteMissing']) {
			return;
		}

		$templateIdsXML = array();
		$templateScreenIdsXML = array();

		foreach ($allScreens as $template => $screens) {
			$templateId = $this->referencer->resolveTemplate($template);

			// it's possible that we didn't check template update/create,
			// so we have no template ID here and we'll skip deleting templated screens
			if ($templateId) {
				$templateIdsXML[$templateId] = $templateId;

				foreach ($screens as $screenName => $screen) {
					$templateScreenId = $this->referencer->resolveTemplateScreen($templateId, $screenName);

					if ($templateScreenId) {
						$templateScreenIdsXML[$templateScreenId] = $templateScreenId;
					}
				}
			}
		}

		// no templates have been processed
		if (!$templateIdsXML) {
			return;
		}

		$dbTemplateScreenIds = API::TemplateScreen()->get(array(
			'output' => array('screenid'),
			'hostids' => $templateIdsXML,
			'preservekeys' => true,
			'nopermissions' => true,
			'noInheritance' => true
		));

		$templateScreensToDelete = array_diff_key($dbTemplateScreenIds, $templateScreenIdsXML);

		if ($templateScreensToDelete) {
			API::TemplateScreen()->delete(array_keys($templateScreensToDelete));
		}
	}
}
