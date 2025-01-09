<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
class CTemplateDashboardImporter extends CImporter {

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
					$dashboard_page['widgets'] = $this->resolveDashboardWidgetReferences($dashboard_page['widgets'],
						$name
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

	/**
	 * Prepare dashboard data for import.
	 * Each widget field "value" has reference to resource it represents, reference structure may differ depending on
	 * widget field "type".
	 *
	 * @param array $widgets
	 * @param string $dashboard_name  for error purpose
	 *
	 * @return array
	 *
	 * @throws Exception if referenced object is not found in database
	 */
	protected function resolveDashboardWidgetReferences(array $widgets, string $dashboard_name): array {
		foreach ($widgets as &$widget) {
			foreach ($widget['fields'] as &$field) {
				switch ($field['type']) {
					case ZBX_WIDGET_FIELD_TYPE_HOST:
						$host_name = $field['value']['host'];

						$field['value'] = $this->referencer->findHostidByHost($host_name);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
								$host_name, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_ITEM:
					case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
						$host_name = $field['value']['host'];
						$item_key = $field['value']['key'];

						$hostid = $this->referencer->findTemplateidOrHostidByHost($host_name);

						if ($hostid === null) {
							throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
								$host_name, $dashboard_name
							));
						}

						$field['value'] = $this->referencer->findItemidByKey($hostid, $item_key, true);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find item "%1$s" used in dashboard "%2$s".',
								$host_name.':'.$item_key, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_GRAPH:
					case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
						$host_name = $field['value']['host'];
						$graph_name = $field['value']['name'];

						$hostid = $this->referencer->findTemplateidOrHostidByHost($host_name);

						if ($hostid === null) {
							throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
								$host_name, $dashboard_name
							));
						}

						$field['value'] = $this->referencer->findGraphidByName($hostid, $graph_name, true);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find graph "%1$s" used in dashboard "%2$s".',
								$graph_name, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_MAP:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findMapidByName($name);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find map "%1$s" used in dashboard "%2$s".',
								$name, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_SERVICE:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findServiceidByName($name);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find service "%1$s" used in dashboard "%2$s".',
								$name, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_SLA:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findSlaidByName($name);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find SLA "%1$s" used in dashboard "%2$s".',
								$name, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_USER:
						$username = $field['value']['username'];

						$field['value'] = $this->referencer->findUseridByUsername($username);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find user "%1$s" used in dashboard "%2$s".',
								$username, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_ACTION:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findActionidByName($name);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find action "%1$s" used in dashboard "%2$s".',
								$name, $dashboard_name
							));
						}
						break;

					case ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findMediaTypeidByName($name);

						if ($field['value'] === null) {
							throw new Exception(_s('Cannot find media type "%1$s" used in dashboard "%2$s".',
								$name, $dashboard_name
							));
						}
						break;
				}
			}
			unset($field);
		}
		unset($widget);

		return $widgets;
	}
}
