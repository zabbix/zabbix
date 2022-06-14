<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CConfigurationExport {

	/**
	 * @var CExportWriter
	 */
	protected $writer;

	/**
	 * @var CConfigurationExportBuilder
	 */
	protected $builder;

	/**
	 * Array with data that must be exported.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Array with data fields that must be exported.
	 *
	 * @var array
	 */
	protected $dataFields;

	protected $options;

	/**
	 * Constructor.
	 *
	 * @param array $options IDs of elements that should be exported.
	 */
	public function __construct(array $options) {
		$this->options = array_merge([
			'hosts' => [],
			'templates' => [],
			'groups' => [],
			'images' => [],
			'maps' => [],
			'mediaTypes' => []
		], $options);

		$this->data = [
			'groups' => [],
			'templates' => [],
			'hosts' => [],
			'triggers' => [],
			'triggerPrototypes' => [],
			'graphs' => [],
			'graphPrototypes' => [],
			'images' => [],
			'maps' => [],
			'mediaTypes' => []
		];

		$this->dataFields = [
			'item' => ['hostid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username',
				'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'inventory_link', 'flags',
				'logtimefmt', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts',
				'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
				'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
				'verify_host', 'allow_traps', 'uuid'
			],
			'drule' => ['itemid', 'hostid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'formula', 'valuemapid', 'params', 'ipmi_sensor', 'authtype',
				'username', 'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'inventory_link',
				'flags', 'filter', 'lifetime', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields',
				'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
				'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
				'verify_host', 'allow_traps', 'parameters', 'uuid'
			],
			'item_prototype' => ['hostid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username',
				'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'inventory_link', 'flags',
				'logtimefmt', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts',
				'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
				'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
				'verify_host', 'allow_traps', 'discover', 'uuid'
			]
		];
	}

	/**
	 * Setter for $writer property.
	 *
	 * @param CExportWriter $writer
	 */
	public function setWriter(CExportWriter $writer) {
		$this->writer = $writer;
	}

	/**
	 * Setter for builder property.
	 *
	 * @param CConfigurationExportBuilder $builder
	 */
	public function setBuilder(CConfigurationExportBuilder $builder) {
		$this->builder = $builder;
	}

	/**
	 * Export elements whose IDs were passed to constructor.
	 * The resulting export format depends on the export writer that was set,
	 * the export structure depends on the builder that was set.
	 *
	 * @return string result or false on insufficient user permissions.
	 */
	public function export() {
		try {
			$this->gatherData();

			// Parameter in CImportValidatorFactory is irrelavant here, since export does not validate data.
			$schema = (new CImportValidatorFactory(CExportWriterFactory::YAML))
				->getObject(ZABBIX_EXPORT_VERSION)
				->getSchema();

			$simple_triggers = [];
			if ($this->data['triggers']) {
				$simple_triggers = $this->builder->extractSimpleTriggers($this->data['triggers']);
			}

			if ($this->data['groups']) {
				$this->builder->buildGroups($schema['rules']['groups'], $this->data['groups']);
			}

			if ($this->data['templates']) {
				$this->builder->buildTemplates($schema['rules']['templates'], $this->data['templates'],
					$simple_triggers
				);
			}

			if ($this->data['hosts']) {
				$this->builder->buildHosts($schema['rules']['hosts'], $this->data['hosts'], $simple_triggers);
			}

			if ($this->data['triggers']) {
				$this->builder->buildTriggers($schema['rules']['triggers'], $this->data['triggers']);
			}

			if ($this->data['graphs']) {
				$this->builder->buildGraphs($schema['rules']['graphs'], $this->data['graphs']);
			}

			if ($this->data['images']) {
				$this->builder->buildImages($this->data['images']);
			}

			if ($this->data['maps']) {
				$this->builder->buildMaps($schema['rules']['maps'], $this->data['maps']);
			}

			if ($this->data['mediaTypes']) {
				$this->builder->buildMediaTypes($schema['rules']['media_types'], $this->data['mediaTypes']);
			}

			return $this->writer->write($this->builder->getExport());
		}
		catch (CConfigurationExportException $e) {
			return false;
		}
	}

	/**
	 * Gathers data required for export from database depends on $options passed to constructor.
	 */
	protected function gatherData() {
		$options = $this->filterOptions($this->options);

		if ($options['groups']) {
			$this->gatherGroups($options['groups']);
		}

		if ($options['templates']) {
			$this->gatherTemplates($options['templates']);
		}

		if ($options['hosts']) {
			$this->gatherHosts($options['hosts']);
		}

		if ($options['templates'] || $options['hosts']) {
			$this->gatherGraphs($options['hosts'], $options['templates']);
			$this->gatherTriggers($options['hosts'], $options['templates']);
		}

		if ($options['maps']) {
			$options['images'] = array_merge($options['images'], $this->gatherMaps($options['maps']));
			$options['images'] = array_keys(array_flip($options['images']));
		}

		if ($options['images']) {
			$this->gatherImages($options['images']);
		}

		if ($options['mediaTypes']) {
			$this->gatherMediaTypes($options['mediaTypes']);
		}
	}

	/**
	 * Gather image data for export.
	 *
	 * @param array $imageids
	 */
	protected function gatherImages(array $imageids): void {
		$images = API::Image()->get([
			'output' => ['imageid', 'name', 'imagetype'],
			'imageids' => $imageids,
			'select_image' => true,
			'preservekeys' => true
		]);

		foreach ($images as &$image) {
			$image = [
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['image']
			];
		}
		unset($image);

		$this->data['images'] = $images;
	}

	/**
	 * Excludes objects that cannot be exported.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	protected function filterOptions(array $options) {
		if ($options['hosts']) {
			// exclude discovered hosts
			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => $options['hosts'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
			]);

			$options['hosts'] = zbx_objectValues($hosts, 'hostid');
		}

		return $options;
	}

	/**
	 * Get groups for export from database.
	 *
	 * @param array $groupIds
	 */
	protected function gatherGroups(array $groupIds) {
		$this->data['groups'] = API::HostGroup()->get([
			'output' => ['name', 'uuid'],
			'groupids' => $groupIds,
			'preservekeys' => true
		]);
	}

	/**
	 * Get templates for export from database.
	 *
	 * @param array $templateids
	 */
	protected function gatherTemplates(array $templateids) {
		$templates = API::Template()->get([
			'output' => ['host', 'name', 'description', 'uuid'],
			'selectGroups' => ['groupid', 'name', 'uuid'],
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectDashboards' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'selectValueMaps' => ['valuemapid', 'name', 'mappings', 'uuid'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		foreach ($templates as &$template) {
			// merge host groups with all groups
			$this->data['groups'] += zbx_toHash($template['groups'], 'groupid');

			$template['dashboards'] = [];
			$template['discoveryRules'] = [];
			$template['items'] = [];
			$template['httptests'] = [];
		}
		unset($template);

		if ($templates) {
			$templates = $this->gatherTemplateDashboards($templates);
			$templates = $this->gatherItems($templates);
			$templates = $this->gatherDiscoveryRules($templates);
			$templates = $this->gatherHttpTests($templates);
		}

		$this->data['templates'] = $templates;
	}

	/**
	 * Get Hosts for export from database.
	 *
	 * @param array $hostIds
	 */
	protected function gatherHosts(array $hostIds) {
		$hosts = API::Host()->get([
			'output' => [
				'proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password',
				'name', 'description', 'inventory_mode'
			],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectInventory' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => ['groupid', 'name', 'uuid'],
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'selectValueMaps' => ['valuemapid', 'name', 'mappings'],
			'hostids' => $hostIds,
			'preservekeys' => true
		]);

		foreach ($hosts as &$host) {
			// merge host groups with all groups
			$this->data['groups'] += zbx_toHash($host['groups'], 'groupid');

			$host['discoveryRules'] = [];
			$host['items'] = [];
			$host['httptests'] = [];
		}
		unset($host);

		if ($hosts) {
			$hosts = $this->gatherProxies($hosts);
			$hosts = $this->gatherItems($hosts);
			$hosts = $this->gatherDiscoveryRules($hosts);
			$hosts = $this->gatherHttpTests($hosts);
		}

		$this->data['hosts'] = $hosts;
	}

	/**
	 * Get template dashboards from database.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function gatherTemplateDashboards(array $templates) {
		$dashboards = API::TemplateDashboard()->get([
			'output' => ['dashboardid', 'name', 'templateid', 'display_period', 'auto_start', 'uuid'],
			'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
			'templateids' => array_keys($templates),
			'preservekeys' => true
		]);

		foreach ($dashboards as $dashboard) {
			$dashboard['pages'] = $this->prepareDashboardPages($dashboard['pages']);

			$templates[$dashboard['templateid']]['dashboards'][] = $dashboard;
		}

		return $templates;
	}

	/**
	 * Get dashboard pages' related objects from database.
	 *
	 * @param array $dashboard_pages
	 *
	 * @return array
	 */
	protected function prepareDashboardPages(array $dashboard_pages): array {
		$hostids = [];
		$itemids = [];
		$graphids = [];

		// Collect IDs.
		foreach ($dashboard_pages as $dashboard_page) {
			foreach ($dashboard_page['widgets'] as $widget) {
				foreach ($widget['fields'] as $field) {
					switch ($field['type']) {
						case ZBX_WIDGET_FIELD_TYPE_HOST:
							$hostids[$field['value']] = true;
							break;

						case ZBX_WIDGET_FIELD_TYPE_ITEM:
						case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
							$itemids[$field['value']] = true;
							break;

						case ZBX_WIDGET_FIELD_TYPE_GRAPH:
						case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
							$graphids[$field['value']] = true;
							break;
					}
				}
			}
		}

		$hosts = $this->getHostsReferences(array_keys($hostids));
		$items = $this->getItemsReferences(array_keys($itemids));
		$graphs = $this->getGraphsReferences(array_keys($graphids));

		// Replace IDs.
		foreach ($dashboard_pages as &$dashboard_page) {
			foreach ($dashboard_page['widgets'] as &$widget) {
				foreach ($widget['fields'] as &$field) {
					switch ($field['type']) {
						case ZBX_WIDGET_FIELD_TYPE_HOST:
							$field['value'] = $hosts[$field['value']];
							break;

						case ZBX_WIDGET_FIELD_TYPE_ITEM:
						case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
							$field['value'] = $items[$field['value']];
							break;

						case ZBX_WIDGET_FIELD_TYPE_GRAPH:
						case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
							$field['value'] = $graphs[$field['value']];
							break;
					}
				}
				unset($field);
			}
			unset($widget);
		}
		unset($dashboard_page);

		return $dashboard_pages;
	}

	/**
	 * Get proxies from database.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function gatherProxies(array $hosts) {
		$proxy_hostids = [];
		$db_proxies = [];

		foreach ($hosts as $host) {
			if ($host['proxy_hostid'] != 0) {
				$proxy_hostids[$host['proxy_hostid']] = true;
			}
		}

		if ($proxy_hostids) {
			$db_proxies = DBfetchArray(DBselect(
				'SELECT h.hostid,h.host'.
				' FROM hosts h'.
				' WHERE '.dbConditionInt('h.hostid', array_keys($proxy_hostids))
			));
			$db_proxies = zbx_toHash($db_proxies, 'hostid');
		}

		foreach ($hosts as &$host) {
			$host['proxy'] = ($host['proxy_hostid'] != 0 && array_key_exists($host['proxy_hostid'], $db_proxies))
				? ['name' => $db_proxies[$host['proxy_hostid']]['host']]
				: [];
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Get hosts items from database.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function gatherItems(array $hosts) {
		$items = API::Item()->get([
			'output' => $this->dataFields['item'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts),
			'inherited' => false,
			'webitems' => true,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'preservekeys' => true
		]);

		foreach ($items as $itemid => &$item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if (array_key_exists($item['master_itemid'], $items)) {
					$item['master_item'] = ['key_' => $items[$item['master_itemid']]['key_']];
				}
				else {
					// Do not export dependent items with master item from template.
					unset($items[$itemid]);
				}
			}
		}
		unset($item);

		foreach ($items as $itemid => $item) {
			if ($item['type'] == ITEM_TYPE_HTTPTEST) {
				unset($items[$itemid]);
			}
		}

		$items = $this->prepareItems($items);

		foreach ($items as $item) {
			$item['host'] = $hosts[$item['hostid']]['host'];
			$hosts[$item['hostid']]['items'][] = $item;
		}

		return $hosts;
	}

	/**
	 * Get items related objects data from database. and set 'valueMaps' data.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function prepareItems(array $items) {
		$valuemapids = array_flip(array_column($items, 'valuemapid'));

		// Value map IDs that are zeros, should be skipped.
		unset($valuemapids[0]);

		if ($valuemapids) {
			$db_valuemaps = API::ValueMap()->get([
				'output' => ['name'],
				'valuemapids' => array_keys($valuemapids),
				'preservekeys' => true
			]);
		}

		foreach ($items as $idx => &$item) {
			$item['valuemap'] = [];

			if ($item['valuemapid'] != 0) {
				$item['valuemap'] = ['name' => $db_valuemaps[$item['valuemapid']]['name']];
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Get hosts discovery rules from database.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function gatherDiscoveryRules(array $hosts) {
		$discovery_rules = API::DiscoveryRule()->get([
			'output' => $this->dataFields['drule'],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'hostids' => array_keys($hosts),
			'inherited' => false,
			'preservekeys' => true
		]);

		$itemids = [];
		foreach ($hosts as $host_data) {
			foreach ($host_data['items'] as $item) {
				$itemids[$item['itemid']] = $item['key_'];
			}
		};

		$discovery_rules = $this->prepareDiscoveryRules($discovery_rules);

		foreach ($discovery_rules as $discovery_rule) {
			if ($discovery_rule['type'] == ITEM_TYPE_DEPENDENT) {
				if (!array_key_exists($discovery_rule['master_itemid'], $itemids)) {
					// Do not export dependent discovery rule with master item from template.
					continue;
				}

				$discovery_rule['master_item'] = ['key_' => $itemids[$discovery_rule['master_itemid']]];
			}

			foreach ($discovery_rule['itemPrototypes'] as $itemid => $item_prototype) {
				$discovery_rule['itemPrototypes'][$itemid]['host'] = $hosts[$discovery_rule['hostid']]['host'];
			}

			$hosts[$discovery_rule['hostid']]['discoveryRules'][] = $discovery_rule;
		}

		return $hosts;
	}

	/**
	 * Get discovery rules related objects from database.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function prepareDiscoveryRules(array $items) {
		$templateids = [];

		foreach ($items as &$item) {
			$item['itemPrototypes'] = [];
			$item['graphPrototypes'] = [];
			$item['triggerPrototypes'] = [];
			$item['hostPrototypes'] = [];

			// Unset unnecessary condition fields.
			foreach ($item['filter']['conditions'] as &$condition) {
				unset($condition['item_conditionid'], $condition['itemid']);
			}
			unset($condition);

			// Unset unnecessary filter field and prepare the operations.
			if ($item['overrides']) {
				foreach ($item['overrides'] as &$override) {
					if (array_key_exists('filter', $override)) {
						if (!$override['filter']['conditions']) {
							unset($override['filter']);
						}
						unset($override['filter']['eval_formula']);
					}

					foreach ($override['operations'] as &$operation) {
						if (array_key_exists('opstatus', $operation)) {
							$operation['status'] = $operation['opstatus']['status'];
							unset($operation['opstatus']);
						}
						if (array_key_exists('opdiscover', $operation)) {
							$operation['discover'] = $operation['opdiscover']['discover'];
							unset($operation['opdiscover']);
						}
						if (array_key_exists('opperiod', $operation)) {
							$operation['delay'] = $operation['opperiod']['delay'];
							unset($operation['opperiod']);
						}
						if (array_key_exists('ophistory', $operation)) {
							$operation['history'] = $operation['ophistory']['history'];
							unset($operation['ophistory']);
						}
						if (array_key_exists('optrends', $operation)) {
							$operation['trends'] = $operation['optrends']['trends'];
							unset($operation['optrends']);
						}
						if (array_key_exists('opseverity', $operation)) {
							$operation['severity'] = $operation['opseverity']['severity'];
							unset($operation['opseverity']);
						}
						if (array_key_exists('optag', $operation)) {
							$operation['tags'] = [];
							foreach ($operation['optag'] as $tag) {
								$operation['tags'][] = $tag;
							}
							unset($operation['optag']);
						}
						if (array_key_exists('optemplate', $operation)) {
							foreach ($operation['optemplate'] as $template) {
								$templateids[$template['templateid']] = true;
							}
						}
						if (array_key_exists('opinventory', $operation)) {
							$operation['inventory_mode'] = $operation['opinventory']['inventory_mode'];
							unset($operation['opinventory']);
						}
					}
					unset($operation);
				}
				unset($override);

			}
		}
		unset($item);

		if ($templateids) {
			$templates = API::Template()->get([
				'output' => ['name'],
				'templateids' => array_keys($templateids),
				'preservekeys' => true
			]);
			foreach ($items as &$item) {
				if ($item['overrides']) {
					foreach ($item['overrides'] as &$override) {
						foreach ($override['operations'] as &$operation) {
							if (array_key_exists('optemplate', $operation)) {
								$operation['templates'] = [];
								foreach ($operation['optemplate'] as $template) {
									$operation['templates'][] = ['name' => $templates[$template['templateid']]['name']];
								}
								unset($operation['optemplate']);
							}
						}
						unset($operation);
					}
					unset($override);
				}
			}
			unset($item);
		}

		// gather item prototypes
		$item_prototypes = API::ItemPrototype()->get([
			'output' => $this->dataFields['item_prototype'],
			'selectDiscoveryRule' => ['itemid'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'inherited' => false,
			'preservekeys' => true
		]);

		$unresolved_master_itemids = [];

		// Gather all master item IDs and check if master item IDs already belong to item prototypes.
		foreach ($item_prototypes as $item_prototype) {
			if ($item_prototype['type'] == ITEM_TYPE_DEPENDENT
					&& !array_key_exists($item_prototype['master_itemid'], $item_prototypes)) {
				$unresolved_master_itemids[$item_prototype['master_itemid']] = true;
			}
		}

		// Some leftover regular, non-lld and web items.
		if ($unresolved_master_itemids) {
			$master_items = API::Item()->get([
				'output' => ['itemid', 'key_'],
				'itemids' => array_keys($unresolved_master_itemids),
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
				'webitems' => true,
				'preservekeys' => true
			]);
		}

		$valuemapids = [];

		foreach ($item_prototypes as &$item_prototype) {
			$valuemapids[$item_prototype['valuemapid']] = true;

			if ($item_prototype['type'] == ITEM_TYPE_DEPENDENT) {
				$master_itemid = $item_prototype['master_itemid'];

				if (array_key_exists($master_itemid, $item_prototypes)) {
					$item_prototype['master_item'] = ['key_' => $item_prototypes[$master_itemid]['key_']];
				}
				else {
					$item_prototype['master_item'] = ['key_' => $master_items[$master_itemid]['key_']];
				}
			}
		}
		unset($item_prototype);

		// Value map IDs that are zeros, should be skipped.
		unset($valuemapids[0]);

		if ($valuemapids) {
			$db_valuemaps = API::ValueMap()->get([
				'output' => ['name'],
				'valuemapids' => array_keys($valuemapids),
				'preservekeys' => true
			]);
		}

		foreach ($item_prototypes as $item_prototype) {
			$item_prototype['valuemap'] = [];

			if ($item_prototype['valuemapid'] != 0) {
				$item_prototype['valuemap']['name'] = $db_valuemaps[$item_prototype['valuemapid']]['name'];
			}

			$items[$item_prototype['discoveryRule']['itemid']]['itemPrototypes'][] = $item_prototype;
		}

		// gather graph prototypes
		$graphs = API::GraphPrototype()->get([
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		]);

		$graphs = $this->prepareGraphs($graphs);

		foreach ($graphs as $graph) {
			$items[$graph['discoveryRule']['itemid']]['graphPrototypes'][] = $graph;
		}

		// gather trigger prototypes
		$triggers = API::TriggerPrototype()->get([
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'type', 'flags',
				'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata',
				'discover', 'event_name', 'uuid'
			],
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectDependencies' => ['expression', 'description', 'recovery_expression'],
			'selectHosts' => ['status'],
			'selectItems' => ['itemid', 'flags', 'type'],
			'selectTags' => ['tag', 'value'],
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'inherited' => false,
			'preservekeys' => true
		]);

		$triggers = $this->prepareTriggers($triggers);

		foreach ($triggers as $trigger) {
			$items[$trigger['discoveryRule']['itemid']]['triggerPrototypes'][] = $trigger;
		}

		// gather host prototypes
		$host_prototypes = API::HostPrototype()->get([
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectTemplates' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'selectInterfaces' => ['main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'inherited' => false,
			'preservekeys' => true
		]);

		// Replace group prototype group IDs with references.
		$groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			$groupids += array_flip(array_column($host_prototype['groupLinks'], 'groupid'));
		}

		$groups = $this->getGroupsReferences(array_keys($groupids));

		// Export the groups used in group prototypes.
		$this->data['groups'] += $groups;

		foreach ($host_prototypes as $host_prototype) {
			foreach ($host_prototype['groupLinks'] as &$group_link) {
				$group_link = $groups[$group_link['groupid']];
			}
			unset($group_link);

			$items[$host_prototype['discoveryRule']['itemid']]['hostPrototypes'][] = $host_prototype;
		}

		return $items;
	}

	/**
	 * Get web scenarios from database.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function gatherHttpTests(array $hosts) {
		$httptests = API::HttpTest()->get([
			'output' => ['name', 'hostid', 'delay', 'retries', 'agent', 'http_proxy', 'variables',
				'headers', 'status', 'authentication', 'http_user', 'http_password', 'verify_peer', 'verify_host',
				'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'uuid'
			],
			'selectSteps' => ['no', 'name', 'url', 'query_fields', 'posts', 'variables', 'headers', 'follow_redirects',
				'retrieve_mode', 'timeout', 'required', 'status_codes'
			],
			'selectTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts),
			'inherited' => false,
			'preservekeys' => true
		]);

		foreach ($httptests as $httptest) {
			$hosts[$httptest['hostid']]['httptests'][] = $httptest;
		}

		return $hosts;
	}

	/**
	 * Get graphs for export from database.
	 *
	 * @param array $hostIds
	 * @param array $templateIds
	 */
	protected function gatherGraphs(array $hostIds, array $templateIds) {
		$hostIds = array_merge($hostIds, $templateIds);

		$graphs = API::Graph()->get([
			'hostids' => $hostIds,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		$this->data['graphs'] = $this->prepareGraphs($graphs);
	}

	/**
	 * Unset graphs that have LLD created items and replace graph itemids with array of host and key.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function prepareGraphs(array $graphs) {
		$graphItemIds = [];

		foreach ($graphs as $graph) {
			foreach ($graph['gitems'] as $gItem) {
				$graphItemIds[$gItem['itemid']] = $gItem['itemid'];
			}

			if ($graph['ymin_itemid']) {
				$graphItemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
			}
			if ($graph['ymax_itemid']) {
				$graphItemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
			}
		}

		$graph_items = API::Item()->get([
			'output' => ['key_'],
			'selectHosts' => ['host', 'status'],
			'itemids' => $graphItemIds,
			'webitems' => true,
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE]
			],
			'preservekeys' => true
		]);

		foreach ($graphs as $gnum => $graph) {
			if ($graph['ymin_itemid']) {
				if (array_key_exists($graph['ymin_itemid'], $graph_items)) {
					$item = $graph_items[$graph['ymin_itemid']];
					$graphs[$gnum]['ymin_itemid'] = [
						'host' => $item['hosts'][0]['host'],
						'key' => $item['key_']
					];
				}
				else {
					unset($graphs[$gnum]);
					continue;
				}
			}

			if ($graph['ymax_itemid']) {
				if (array_key_exists($graph['ymax_itemid'], $graph_items)) {
					$item = $graph_items[$graph['ymax_itemid']];
					$graphs[$gnum]['ymax_itemid'] = [
						'host' => $item['hosts'][0]['host'],
						'key' => $item['key_']
					];
				}
				else {
					unset($graphs[$gnum]);
					continue;
				}
			}

			foreach ($graph['gitems'] as $ginum => $gItem) {
				if (array_key_exists($gItem['itemid'], $graph_items)) {
					$item = $graph_items[$gItem['itemid']];

					if ($item['hosts'][0]['status'] != HOST_STATUS_TEMPLATE) {
						unset($graph['uuid']);
					}
					unset($item['hosts'][0]['status']);

					$graphs[$gnum]['gitems'][$ginum]['itemid'] = [
						'host' => $item['hosts'][0]['host'],
						'key' => $item['key_']
					];
				}
				else {
					unset($graphs[$gnum]);
					continue 2;
				}
			}
		}

		return $graphs;
	}

	/**
	 * Get triggers for export from database.
	 *
	 * @param array $hostIds
	 * @param array $templateIds
	 */
	protected function gatherTriggers(array $hostIds, array $templateIds) {
		$hostIds = array_merge($hostIds, $templateIds);

		$triggers = API::Trigger()->get([
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'type', 'flags',
				'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata',
				'event_name', 'uuid'
			],
			'selectDependencies' => ['expression', 'description', 'recovery_expression'],
			'selectItems' => ['itemid', 'flags', 'type', 'templateid'],
			'selectTags' => ['tag', 'value'],
			'selectHosts' => ['status'],
			'hostids' => $hostIds,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'inherited' => false,
			'preservekeys' => true
		]);

		$this->data['triggers'] = $this->prepareTriggers($triggers);
	}

	/**
	 * Prepare trigger expressions and unset triggers containing discovered items.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	protected function prepareTriggers(array $triggers) {
		// Unset triggers containing discovered items.
		foreach ($triggers as $idx => &$trigger) {
			if ($trigger['hosts'][0]['status'] != HOST_STATUS_TEMPLATE) {
				unset($trigger['uuid']);
			}
			unset($trigger['hosts']);

			foreach ($trigger['items'] as $item) {
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($triggers[$idx]);
					continue 2;
				}
			}

			$trigger['dependencies'] = CMacrosResolverHelper::resolveTriggerExpressions($trigger['dependencies'],
				['sources' => ['expression', 'recovery_expression']]
			);
		}
		unset($trigger);

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		return $triggers;
	}

	/**
	 * Get maps for export from database and collect image IDs used in maps for later usage.
	 *
	 * @param array $mapids
	 *
	 * @return array
	 */
	protected function gatherMaps(array $mapids): array {
		$sysmaps = API::Map()->get([
			'sysmapids' => $mapids,
			'selectShapes' => ['type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size', 'font_color',
				'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color', 'background_color',
				'zindex'
			],
			'selectLines' => ['x1', 'x2', 'y1', 'y2', 'line_type', 'line_width', 'line_color', 'zindex'],
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'selectIconMap' => API_OUTPUT_EXTEND,
			'selectUrls' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		$imageids = $this->prepareMapExport($sysmaps);

		$this->data['maps'] = $sysmaps;

		return $imageids;
	}

	/**
	 * Get media types for export builder from database.
	 *
	 * @param array $mediatypeids
	 *
	 * return array
	 */
	protected function gatherMediaTypes(array $mediatypeids) {
		$this->data['mediaTypes'] = API::MediaType()->get([
			'output' => ['name', 'type', 'smtp_server', 'smtp_port', 'smtp_helo', 'smtp_email', 'smtp_security',
				'smtp_verify_peer', 'smtp_verify_host', 'smtp_authentication', 'username', 'passwd', 'content_type',
				'exec_path', 'exec_params', 'gsm_modem', 'status', 'maxsessions', 'maxattempts', 'attempt_interval',
				'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url', 'event_menu_name',
				'description', 'parameters'
			],
			'selectMessageTemplates' => ['eventsource', 'recovery', 'subject', 'message'],
			'mediatypeids' => $mediatypeids,
			'preservekeys' => true
		]);
	}

	/**
	 * Change map elements real database selement id and icons ids to unique field references. Gather image IDs for
	 * later usage.
	 *
	 * @param array $exportMaps
	 *
	 * @return array
	 */
	protected function prepareMapExport(array &$exportMaps): array {
		$sysmapIds = $groupIds = $hostIds = $triggerIds = $imageIds = [];

		// gather element IDs that must be substituted
		foreach ($exportMaps as $sysmap) {
			foreach ($sysmap['selements'] as $selement) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$sysmapIds[$selement['elements'][0]['sysmapid']] = $selement['elements'][0]['sysmapid'];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$groupIds[$selement['elements'][0]['groupid']] = $selement['elements'][0]['groupid'];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$hostIds[$selement['elements'][0]['hostid']] = $selement['elements'][0]['hostid'];
						break;

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						foreach ($selement['elements'] as $element) {
							$triggerIds[$element['triggerid']] = $element['triggerid'];
						}
						break;
				}

				if ($selement['iconid_off'] > 0) {
					$imageIds[$selement['iconid_off']] = $selement['iconid_off'];
				}
				if ($selement['iconid_on'] > 0) {
					$imageIds[$selement['iconid_on']] = $selement['iconid_on'];
				}
				if ($selement['iconid_disabled'] > 0) {
					$imageIds[$selement['iconid_disabled']] = $selement['iconid_disabled'];
				}
				if ($selement['iconid_maintenance'] > 0) {
					$imageIds[$selement['iconid_maintenance']] = $selement['iconid_maintenance'];
				}
			}

			if ($sysmap['backgroundid'] > 0) {
				$imageIds[$sysmap['backgroundid']] = $sysmap['backgroundid'];
			}

			foreach ($sysmap['links'] as $link) {
				foreach ($link['linktriggers'] as $linktrigger) {
					$triggerIds[$linktrigger['triggerid']] = $linktrigger['triggerid'];
				}
			}
		}

		$sysmaps = $this->getMapsReferences($sysmapIds);
		$groups = $this->getGroupsReferences($groupIds);
		$hosts = $this->getHostsReferences($hostIds);
		$triggers = $this->getTriggersReferences($triggerIds);
		$images = $this->getImagesReferences($imageIds);

		foreach ($exportMaps as &$sysmap) {
			if (!empty($sysmap['iconmap'])) {
				$sysmap['iconmap'] = ['name' => $sysmap['iconmap']['name']];
			}

			foreach ($sysmap['urls'] as $unum => $url) {
				unset($sysmap['urls'][$unum]['sysmapurlid']);
			}

			$sysmap['backgroundid'] = ($sysmap['backgroundid'] > 0) ? $images[$sysmap['backgroundid']] : [];

			foreach ($sysmap['selements'] as &$selement) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$selement['elements'] = [$sysmaps[$selement['elements'][0]['sysmapid']]];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$selement['elements'] = [$groups[$selement['elements'][0]['groupid']]];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$selement['elements'] = [$hosts[$selement['elements'][0]['hostid']]];
						break;

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						foreach ($selement['elements'] as &$element) {
							$element = $triggers[$element['triggerid']];
						}
						unset($element);
						break;
				}

				$selement['iconid_off'] = ($selement['iconid_off'] > 0) ? $images[$selement['iconid_off']] : [];
				$selement['iconid_on'] = ($selement['iconid_on'] > 0) ? $images[$selement['iconid_on']] : [];
				$selement['iconid_disabled'] = ($selement['iconid_disabled'] > 0)
					? $images[$selement['iconid_disabled']]
					: [];
				$selement['iconid_maintenance'] = ($selement['iconid_maintenance'] > 0)
					? $images[$selement['iconid_maintenance']]
					: [];
			}
			unset($selement);

			foreach ($sysmap['links'] as &$link) {
				foreach ($link['linktriggers'] as &$linktrigger) {
					$linktrigger['triggerid'] = $triggers[$linktrigger['triggerid']];
				}
				unset($linktrigger);
			}
			unset($link);
		}
		unset($sysmap);

		return $imageIds;
	}

	/**
	 * Get groups references by group IDs.
	 *
	 * @param array $groupIds
	 *
	 * @return array
	 */
	protected function getGroupsReferences(array $groupIds) {
		$groups = API::HostGroup()->get([
			'groupids' => $groupIds,
			'output' => ['uuid', 'name'],
			'preservekeys' => true
		]);

		// Access denied for some objects?
		if (count($groups) != count($groupIds)) {
			throw new CConfigurationExportException();
		}

		foreach ($groups as &$group) {
			$group = [
				'uuid' => $group['uuid'],
				'name' => $group['name']
			];
		}
		unset($group);

		return $groups;
	}

	/**
	 * Get hosts references by host IDs.
	 *
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getHostsReferences(array $hostIds) {
		$ids = [];

		$hosts = API::Host()->get([
			'hostids' => $hostIds,
			'output' => ['host'],
			'preservekeys' => true
		]);

		// Access denied for some objects?
		if (count($hosts) != count($hostIds)) {
			throw new CConfigurationExportException();
		}

		foreach ($hosts as $id => $host) {
			$ids[$id] = ['host' => $host['host']];
		}

		return $ids;
	}

	/**
	 * Get maps references by map ids.
	 *
	 * @param array $mapIds
	 *
	 * @return array
	 */
	protected function getMapsReferences(array $mapIds) {
		$ids = [];

		$maps = API::Map()->get([
			'sysmapids' => $mapIds,
			'output' => ['name'],
			'preservekeys' => true
		]);

		// Access denied for some objects?
		if (count($maps) != count($mapIds)) {
			throw new CConfigurationExportException();
		}

		foreach ($maps as $id => $map) {
			$ids[$id] = ['name' => $map['name']];
		}

		return $ids;
	}

	/**
	 * Get graphs references by graph IDs.
	 *
	 * @param array $graphIds
	 *
	 * @return array
	 */
	protected function getGraphsReferences(array $graphIds) {
		$ids = [];

		$graphs = API::Graph()->get([
			'graphids' => $graphIds,
			'selectHosts' => ['host'],
			'output' => ['name'],
			'preservekeys' => true,
			'filter' => ['flags' => null]
		]);

		// Access denied for some objects?
		if (count($graphs) != count($graphIds)) {
			throw new CConfigurationExportException();
		}

		foreach ($graphs as $id => $graph) {
			$host = reset($graph['hosts']);

			$ids[$id] = [
				'name' => $graph['name'],
				'host' => $host['host']
			];
		}

		return $ids;
	}

	/**
	 * Get items references by item IDs.
	 *
	 * @param array $itemIds
	 *
	 * @return array
	 */
	protected function getItemsReferences(array $itemIds) {
		$ids = [];

		$items = API::Item()->get([
			'itemids' => $itemIds,
			'output' => ['key_'],
			'selectHosts' => ['host'],
			'webitems' => true,
			'preservekeys' => true,
			'filter' => ['flags' => null]
		]);

		// Access denied for some objects?
		if (count($items) != count($itemIds)) {
			throw new CConfigurationExportException();
		}

		foreach ($items as $id => $item) {
			$host = reset($item['hosts']);

			$ids[$id] = [
				'key' => $item['key_'],
				'host' => $host['host']
			];
		}

		return $ids;
	}

	/**
	 * Get triggers references by trigger IDs.
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	protected function getTriggersReferences(array $triggerIds) {
		$ids = [];

		$triggers = API::Trigger()->get([
			'output' => ['expression', 'description', 'recovery_expression'],
			'triggerids' => $triggerIds,
			'preservekeys' => true
		]);

		// Access denied for some objects?
		if (count($triggers) != count($triggerIds)) {
			throw new CConfigurationExportException();
		}

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		foreach ($triggers as $id => $trigger) {
			$ids[$id] = [
				'description' => $trigger['description'],
				'expression' => $trigger['expression'],
				'recovery_expression' => $trigger['recovery_expression']
			];
		}

		return $ids;
	}

	/**
	 * Get images references by image IDs.
	 *
	 * @param array $imageIds
	 *
	 * @return array
	 */
	protected function getImagesReferences(array $imageIds) {
		$ids = [];

		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'imageids' => $imageIds,
			'preservekeys' => true
		]);

		// Access denied for some objects?
		if (count($images) != count($imageIds)) {
			throw new CConfigurationExportException();
		}

		foreach ($images as $id => $image) {
			$ids[$id] = ['name' => $image['name']];
		}

		return $ids;
	}
}
