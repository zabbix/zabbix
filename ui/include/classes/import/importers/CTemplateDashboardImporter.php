<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * Template dashboard importer.
 */
class CTemplateDashboardImporter extends CImporter {

	/**
	 * Import template dashboard.
	 *
	 * @param array $dashboards
	 */
	public function import(array $template_dashboards): void {
		if ((!$this->options['templateDashboards']['createMissing']
				&& !$this->options['templateDashboards']['updateExisting']) || !$template_dashboards) {
			return;
		}

		$dashboards_create = [];
		$dashboards_update = [];

		foreach ($template_dashboards as $template_name => $dashboards) {
			$templateid = $this->referencer->resolveTemplate($template_name);

			if (!$this->importedObjectContainer->isTemplateProcessed($templateid)) {
				continue;
			}

			foreach ($dashboards as $name => $dashboard) {

				// $screen = $this->resolveScreenReferences($screen);
				if ($this->referencer->resolveTemplateDashboards($templateid, $name)) {
					$dashboards_update[] = $dashboard;
				}
				else {
					$dashboard['templateid'] = $templateid;
					$dashboards_create[] = $dashboard;
				}
			}
		}

		if ($this->options['templateDashboards']['createMissing'] && $dashboards_create) {
			$created_ids = API::TemplateDashboard()->create($dashboards_create);
			foreach ($dashboards_create as $key => $dashboard) {
				$dashboardid = $created_ids['dashboardids'][$key];
				$this->referencer->addTemplateDashboardsRef($dashboard['name'], $dashboardid);
			}
		}

		if ($this->options['templateDashboards']['updateExisting'] && $dashboards_update) {
			API::TemplateDashboard()->update($dashboards_update);
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

		$templateScreenIdsXML = [];

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

		$dbTemplateScreenIds = API::TemplateScreen()->get([
			'output' => ['screenid'],
			'filter' => [
				'templateid' => $templateIdsXML
			],
			'nopermissions' => true,
			'preservekeys' => true
		]);

		$templateScreensToDelete = array_diff_key($dbTemplateScreenIds, $templateScreenIdsXML);

		if ($templateScreensToDelete) {
			API::TemplateScreen()->delete(array_keys($templateScreensToDelete));
		}
	}
}
