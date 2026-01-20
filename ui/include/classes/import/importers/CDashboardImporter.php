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
 * Global dashboard importer.
 */
class CDashboardImporter extends CDashboardImporterGeneral {

	/**
	 * Import global dashboard.
	 *
	 * @param array $dashboards
	 */
	public function import(array $dashboards): void {
		$dashboards_to_create = [];
		$dashboards_to_update = [];

		foreach ($dashboards as $name => $dashboard) {
			foreach ($dashboard['pages'] as &$dashboard_page) {
				$dashboard_page['widgets'] = $this->resolveWidgetReferences($dashboard_page['widgets'], $name, true);
			}
			unset($dashboard_page);

			$dashboardid = $this->referencer->findDashboardidByName($dashboard['name']);

			if ($dashboardid !== null) {
				$dashboard['dashboardid'] = $dashboardid;

				if (array_key_exists('pages', $dashboard)) {
					foreach ($dashboard['pages'] as $index => &$dashboard_page) {
						$dashboard_pageid = $this->referencer->findDashboardPageidByIndex($dashboardid, $index);

						if ($dashboard_pageid !== null) {
							$dashboard_page['dashboard_pageid'] = $dashboard_pageid;

							if (array_key_exists('widgets', $dashboard_page)) {
								foreach ($dashboard_page['widgets'] as &$widget) {
									$widgetid = $this->referencer->findWidgetidByPosition($dashboard_pageid,
										(int) $widget['x'], (int) $widget['y']
									);

									if ($widgetid !== null) {
										$widget['widgetid'] = $widgetid;
									}
								}
								unset($widget);
							}
						}
					}
					unset($dashboard_page);
				}

				$dashboards_to_update[] = $dashboard;
			}
			else {
				$dashboards_to_create[] = $dashboard;
			}
		}

		if ($this->options['dashboards']['updateExisting'] && $dashboards_to_update) {
			API::Dashboard()->update($dashboards_to_update);
		}

		if ($this->options['dashboards']['createMissing'] && $dashboards_to_create) {
			API::Dashboard()->create($dashboards_to_create);
		}
	}

	/**
	 * Collect missing referred objects.
	 *
	 * @param array $dashboards
	 *
	 * @return $this
	 */
	public function collectMissingObjects(array $dashboards): static {
		foreach ($dashboards as $name => $dashboard) {
			foreach ($dashboard['pages'] as $dashboard_page) {
				$this->resolveWidgetReferences($dashboard_page['widgets'], $name, true);
			}
		}

		return $this;
	}
}
