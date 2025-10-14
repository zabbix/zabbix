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


abstract class CDashboardImporterGeneral extends CImporter {

	/**
	 * Prepare dashboard data for import.
	 * Each widget field "value" has reference to resource it represents, reference structure may differ depending on
	 * widget field "type".
	 *
	 * @param array $widgets
	 * @param string $dashboard_name  for error purpose
	 * @param bool $allow_missing to skip missing
	 *
	 * @return array
	 *
	 * @throws Exception if referenced object is not found in database and allow_missing = false
	 */
	final protected function resolveDashboardWidgetReferences(array $widgets, string $dashboard_name,
			bool $allow_missing = false): array {

		foreach ($widgets as &$widget) {
			foreach ($widget['fields'] as $key => &$field) {
				switch ($field['type']) {
					case ZBX_WIDGET_FIELD_TYPE_GROUP:
						$group_name = $field['value']['name'];
						$field['value'] = $this->referencer->findHostGroupidByName($group_name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('hostgroups', ['name' => $group_name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find host group "%1$s" used in dashboard "%2$s".',
									$group_name, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_HOST:
						$host_name = $field['value']['host'];

						$field['value'] = $this->referencer->findHostidByHost($host_name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('hosts', ['host' => $host_name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
									$host_name, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_ITEM:
					case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
						$host_name = $field['value']['host'];
						$item_key = $field['value']['key'];

						$hostid = $this->referencer->findTemplateidOrHostidByHost($host_name);

						if ($hostid === null) {
							if ($allow_missing) {
								$this->appendMissingObject('hosts', ['host' => $host_name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
									$host_name, $dashboard_name
								));
							}
						}
						else {
							$field['value'] = $this->referencer->findItemidByKey($hostid, $item_key, true);

							if ($field['value'] === null) {
								if ($allow_missing) {
									$this->appendMissingObject('items', ['key' => $item_key, 'host' => $host_name]);
									unset($widget['fields'][$key]);
								}
								else {
									throw new Exception(_s('Cannot find item "%1$s" used in dashboard "%2$s".',
										$host_name . ':' . $item_key, $dashboard_name
									));
								}
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_GRAPH:
					case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
						$host_name = $field['value']['host'];
						$graph_name = $field['value']['name'];

						$hostid = $this->referencer->findTemplateidOrHostidByHost($host_name);

						if ($hostid === null) {
							if ($allow_missing) {
								$this->appendMissingObject('hosts', ['host' => $host_name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find host "%1$s" used in dashboard "%2$s".',
									$host_name, $dashboard_name
								));
							}
						}
						else {
							$field['value'] = $this->referencer->findGraphidByName($hostid, $graph_name, true);

							if ($field['value'] === null) {
								if ($allow_missing) {
									$this->appendMissingObject('graphs', ['name' => $graph_name, 'host' => $host_name]);
									unset($widget['fields'][$key]);
								}
								else {
									throw new Exception(_s('Cannot find graph "%1$s" used in dashboard "%2$s".',
										$graph_name, $dashboard_name
									));
								}
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_MAP:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findMapidByName($name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('sysmaps', ['name' => $name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find map "%1$s" used in dashboard "%2$s".',
									$name, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_SERVICE:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findServiceidByName($name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('services', ['name' => $name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find service "%1$s" used in dashboard "%2$s".',
									$name, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_SLA:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findSlaidByName($name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('sla', ['name' => $name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find SLA "%1$s" used in dashboard "%2$s".',
									$name, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_USER:
						$username = $field['value']['username'];

						$field['value'] = $this->referencer->findUseridByUsername($username);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('users', ['name' => $username]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find user "%1$s" used in dashboard "%2$s".',
									$username, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_ACTION:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findActionidByName($name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('actions', ['name' => $name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find action "%1$s" used in dashboard "%2$s".',
									$name, $dashboard_name
								));
							}
						}

						break;

					case ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE:
						$name = $field['value']['name'];

						$field['value'] = $this->referencer->findMediaTypeidByName($name);

						if ($field['value'] === null) {
							if ($allow_missing) {
								$this->appendMissingObject('mediatypes', ['name' => $name]);
								unset($widget['fields'][$key]);
							}
							else {
								throw new Exception(_s('Cannot find media type "%1$s" used in dashboard "%2$s".',
									$name, $dashboard_name
								));
							}
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
