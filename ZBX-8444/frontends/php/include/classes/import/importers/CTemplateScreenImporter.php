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
	 * @return null
	 */
	public function import(array $allScreens) {
		if ((!$this->options['templateScreens']['createMissing']
				&& !$this->options['templateScreens']['updateExisting']) || !$allScreens) {
			return;
		}

		$screensToCreate = array();
		$screensToUpdate = array();

		foreach ($allScreens as $template => $screens) {
			$templateId = $this->referencer->resolveTemplate($template);

			if (!$this->importedObjectContainer->isTemplateProcessed($templateId)) {
				continue;
			}

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
			$newScreenIds = API::TemplateScreen()->create($screensToCreate);
			foreach ($screensToCreate as $num => $newScreen) {
				$screenId = $newScreenIds['screenids'][$num];
				$this->referencer->addTemplateScreenRef($newScreen['name'], $screenId);
			}
		}

		if ($this->options['templateScreens']['updateExisting'] && $screensToUpdate) {
			API::TemplateScreen()->update($screensToUpdate);
		}
	}

	/**
	 * Deletes missing template screens.
	 *
	 * @param array $allScreens
	 *
	 * @return null
	 */
	public function delete(array $allScreens) {
		if (!$this->options['templateScreens']['deleteMissing']) {
			return;
		}

		$templateIdsXML = $this->importedObjectContainer->getTemplateIds();

		// no templates have been processed
		if (!$templateIdsXML) {
			return;
		}

		$templateScreenIdsXML = array();

		if ($allScreens) {
			foreach ($allScreens as $template => $screens) {
				$templateId = $this->referencer->resolveTemplate($template);

				if ($templateId) {
					foreach ($screens as $screenName => $screen) {
						$templateScreenId = $this->referencer->resolveTemplateScreen($templateId, $screenName);

						if ($templateScreenId) {
							$templateScreenIdsXML[$templateScreenId] = $templateScreenId;
						}
					}
				}
			}
		}

		$dbTemplateScreenIds = API::TemplateScreen()->get(array(
			'output' => array('screenid'),
			'hostids' => $templateIdsXML,
			'nopermissions' => true,
			'preservekeys' => true
		));

		$templateScreensToDelete = array_diff_key($dbTemplateScreenIds, $templateScreenIdsXML);

		if ($templateScreensToDelete) {
			API::TemplateScreen()->delete(array_keys($templateScreensToDelete));
		}
	}
}
