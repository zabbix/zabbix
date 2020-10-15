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
	 * @param array $template_dashboards
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
				$dashboard['widgets'] = $this->resolveDashboardWidgetReferences($dashboard['widgets'], $name);

				$dashboardid = $this->referencer->resolveTemplateDashboards($templateid, $name);
				if ($dashboardid) {
					$dashboard['dashboardid'] = $dashboardid;
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
	 * Deletes missing template dashboards.
	 *
	 * @param array $template_dashboards
	 */
	public function delete(array $template_dashboards): void {
		if (!$this->options['templateDashboards']['deleteMissing']) {
			return;
		}

		$templateids = $this->importedObjectContainer->getTemplateIds();
		if (!$templateids) {
			return;
		}

		$dashboardids = [];

		if ($template_dashboards) {
			foreach ($template_dashboards as $template_name => $dashboards) {
				$templateid = $this->referencer->resolveTemplate($template_name);

				if ($templateid) {
					foreach ($dashboards as $name => $dashboard) {
						$dashboardid = $this->referencer->resolveTemplateDashboards($templateid, $name);

						if ($dashboardid) {
							$dashboardids[$dashboardid] = true;
						}
					}
				}
			}
		}

		$db_dashboardids = API::TemplateDashboard()->get([
			'output' => ['dashboardid'],
			'filter' => [
				'templateid' => $templateids
			],
			'preservekeys' => true
		]);

		$dashboards_delete = array_diff_key($db_dashboardids, $dashboardids);

		if ($dashboards_delete) {
			API::TemplateDashboard()->delete(array_keys($dashboards_delete));
		}
	}

	/**
	 * Prepare dashboard data for import.
	 * Each widget field "value" has reference to resource it represents, reference structure may differ depending on
	 * widget field "type".
	 *
	 * @throws Exception if referenced object is not found in database
	 *
	 * @param array $widgets
	 * @param string $dashboard_name  for error purpose
	 *
	 * @return array
	 */
	protected function resolveDashboardWidgetReferences(array $widgets, string $dashboard_name): array {
		if ($widgets) {
			foreach ($widgets as &$widget) {
				foreach ($widget['fields'] as &$field) {
					switch ($field['type']) {
						case ZBX_WIDGET_FIELD_TYPE_HOST:
							$host_name = $field['value']['host'];

							$field['value'] = $this->referencer->resolveHost($host_name);

							if (!$field['value']) {
								throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
									$host_name, $dashboard_name
								));
							}
							break;

						case ZBX_WIDGET_FIELD_TYPE_ITEM:
						case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
							$host_name = $field['value']['host'];
							$item_key = $field['value']['key'];

							$hostid = $this->referencer->resolveHostOrTemplate($host_name);
							$field['value'] = $this->referencer->resolveItem($hostid, $item_key);

							if (!$field['value']) {
								throw new Exception(_s('Cannot find item "%1$s" used in dashboard "%2$s".',
									$host_name.':'.$item_key, $dashboard_name
								));
							}
							break;

						case ZBX_WIDGET_FIELD_TYPE_GRAPH:
						case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
							$host_name = $field['value']['host'];
							$graph_name = $field['value']['name'];

							$hostid = $this->referencer->resolveHostOrTemplate($host_name);
							$field['value'] = $this->referencer->resolveGraph($hostid, $graph_name);

							if (!$field['value']) {
								throw new Exception(_s('Cannot find graph "%1$s" used in dashboard "%2$s".',
									$graph_name, $dashboard_name
								));
							}
							break;
					}
				}
				unset($field);
			}
			unset($widget);
		}

		return $widgets;
	}
}
