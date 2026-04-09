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
 * Template dashboard importer.
 */
class CTemplateDashboardImporter extends CDashboardImporterGeneral {

	/**
	 * Import template dashboard.
	 *
	 * @param array $template_dashboards
	 *
	 * @throws Exception
	 */
	public function import(array $template_dashboards): void {
		$dashboards_to_create = [];
		$dashboards_to_update = [];

		foreach ($template_dashboards as $template_name => $dashboards) {
			$templateid = $this->referencer->findTemplateidByHost($template_name);

			if ($templateid === null || !$this->importedObjectContainer->isTemplateProcessed($templateid)) {
				continue;
			}

			foreach ($dashboards as $name => $dashboard) {
				foreach ($dashboard['pages'] as &$dashboard_page) {
					$dashboard_page['widgets'] = $this->resolveWidgetReferences($dashboard_page['widgets'],
						$name, false
					);
				}
				unset($dashboard_page);

				$dashboardid = $this->referencer->findTemplateDashboardidByUuid($dashboard['uuid']);

				if ($dashboardid === null) {
					$dashboardid = $this->referencer->findTemplateDashboardidByNameAndId($dashboard['name'],
						$templateid
					);
				}

				if ($dashboardid !== null) {
					$dashboard['dashboardid'] = $dashboardid;
					$dashboards_to_update[] = $dashboard;
				}
				else {
					$dashboard['templateid'] = $templateid;
					$dashboards_to_create[] = $dashboard;
				}
			}
		}

		if ($this->options['templateDashboards']['updateExisting'] && $dashboards_to_update) {
			API::TemplateDashboard()->update($dashboards_to_update);
		}

		if ($this->options['templateDashboards']['createMissing'] && $dashboards_to_create) {
			API::TemplateDashboard()->create($dashboards_to_create);
		}
	}

	/**
	 * Deletes missing template dashboards.
	 *
	 * @param array $template_dashboards
	 *
	 * @throws APIException
	 */
	public function delete(array $template_dashboards): void {
		$templateids = $this->importedObjectContainer->getTemplateids();
		if (!$templateids) {
			return;
		}

		$dashboardids = [];

		foreach ($template_dashboards as $template_name => $dashboards) {
			$templateid = $this->referencer->findTemplateidByHost($template_name);

			if ($templateid !== null) {
				foreach ($dashboards as $dashboard) {
					$dashboardid = $this->referencer->findTemplateDashboardidByUuid($dashboard['uuid']);

					if ($dashboardid === null) {
						$dashboardid = $this->referencer->findTemplateDashboardidByNameAndId($dashboard['name'],
							$templateid
						);
					}

					if ($dashboardid !== null) {
						$dashboardids[$dashboardid] = true;
					}
				}
			}
		}

		$db_dashboardids = API::TemplateDashboard()->get([
			'output' => [],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		$dashboardids_to_delete = array_diff_key($db_dashboardids, $dashboardids);

		if ($dashboardids_to_delete) {
			API::TemplateDashboard()->delete(array_keys($dashboardids_to_delete));
		}
	}
}
