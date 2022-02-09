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


/**
 * Class for importing configuration data.
 */
class CConfigurationImport {

	/**
	 * @var CImportDataAdapter
	 */
	protected $adapter;

	/**
	 * @var CImportReferencer
	 */
	protected $referencer;

	/**
	 * @var CImportedObjectContainer
	 */
	protected $importedObjectContainer;

	/**
	 * @var array
	 */
	protected $options;

	/**
	 * @var array with data read from source string
	 */
	protected $data;

	/**
	 * @var array  cached data from the adapter
	 */
	protected $formattedData = [];

	/**
	 * Constructor.
	 * Source string must be suitable for reader class,
	 * i.e. if string contains json then reader should be able to read json.
	 *
	 * @param array						$options					import options "createMissing", "updateExisting" and "deleteMissing"
	 * @param CImportReferencer			$referencer					class containing all importable objects
	 * @param CImportedObjectContainer	$importedObjectContainer	class containing processed host and template IDs
	 */
	public function __construct(array $options, CImportReferencer $referencer,
			CImportedObjectContainer $importedObjectContainer) {
		$default_options = [
			'groups' => ['updateExisting' => false, 'createMissing' => false],
			'hosts' => ['updateExisting' => false, 'createMissing' => false],
			'templates' => ['updateExisting' => false, 'createMissing' => false],
			'templateDashboards' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'templateLinkage' => ['createMissing' => false, 'deleteMissing' => false],
			'items' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'discoveryRules' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'triggers' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'graphs' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'httptests' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'maps' => ['updateExisting' => false, 'createMissing' => false],
			'images' => ['updateExisting' => false, 'createMissing' => false],
			'mediaTypes' => ['updateExisting' => false, 'createMissing' => false],
			'valueMaps' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false]
		];

		foreach ($default_options as $entity => $rules) {
			$options[$entity] = array_key_exists($entity, $options)
				? array_merge($rules, $options[$entity])
				: $rules;
		}

		$object_options = (
			$options['templateLinkage']['createMissing']
			|| $options['templateLinkage']['deleteMissing']
			|| $options['items']['updateExisting']
			|| $options['items']['createMissing']
			|| $options['items']['deleteMissing']
			|| $options['discoveryRules']['updateExisting']
			|| $options['discoveryRules']['createMissing']
			|| $options['discoveryRules']['deleteMissing']
			|| $options['triggers']['deleteMissing']
			|| $options['graphs']['deleteMissing']
			|| $options['httptests']['updateExisting']
			|| $options['httptests']['createMissing']
			|| $options['httptests']['deleteMissing']
		);

		$options['process_templates'] = (
			!$options['templates']['updateExisting']
			&& ($object_options
				|| $options['templateDashboards']['updateExisting']
				|| $options['templateDashboards']['createMissing']
				|| $options['templateDashboards']['deleteMissing']
			)
		);
		$options['process_hosts'] = (!$options['hosts']['updateExisting'] && $object_options);

		$this->options = $options;
		$this->referencer = $referencer;
		$this->importedObjectContainer = $importedObjectContainer;
	}

	/**
	 * Import configuration data.
	 *
	 * @param CImportDataAdapter $adapter an object to provide access to the imported data
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function import(CImportDataAdapter $adapter): bool {
		$this->adapter = $adapter;

		// Parse all import for references to resolve them all together with less sql count.
		$this->gatherReferences();

		$this->processGroups();
		$this->processTemplates();
		$this->processHosts();

		// Delete missing objects from processed hosts and templates.
		$this->deleteMissingHttpTests();
		$this->deleteMissingTemplateDashboards();
		$this->deleteMissingDiscoveryRules();
		$this->deleteMissingTriggers();
		$this->deleteMissingGraphs();
		$this->deleteMissingItems();

		// Import objects.
		$this->processHttpTests();
		$this->processItems();
		$this->processTriggers();
		$this->processDiscoveryRules();
		$this->processGraphs();
		$this->processImages();
		$this->processMaps();
		$this->processTemplateDashboards();
		$this->processMediaTypes();

		return true;
	}

	/**
	 * Parse all import data and collect references to objects.
	 * For host objects it collects host names, for items - host name and item key, etc.
	 * Collected references are added and resolved via the $this->referencer object.
	 *
	 * @see CImportReferencer
	 */
	protected function gatherReferences(): void {
		$groups_refs = [];
		$templates_refs = [];
		$hosts_refs = [];
		$items_refs = [];
		$valuemaps_refs = [];
		$triggers_refs = [];
		$graphs_refs = [];
		$iconmaps_refs = [];
		$images_refs = [];
		$maps_refs = [];
		$template_dashboards_refs = [];
		$template_macros_refs = [];
		$host_macros_refs = [];
		$host_prototype_macros_refs = [];
		$proxy_refs = [];
		$host_prototypes_refs = [];
		$httptests_refs = [];
		$httpsteps_refs = [];

		foreach ($this->getFormattedGroups() as $group) {
			$groups_refs[$group['name']] = ['uuid' => $group['uuid']];
		}

		foreach ($this->getFormattedTemplates() as $template) {
			$templates_refs[$template['host']] = ['uuid' => $template['uuid']];

			foreach ($template['groups'] as $group) {
				$groups_refs += [$group['name'] => []];
			}

			if (array_key_exists('macros', $template)) {
				foreach ($template['macros'] as $macro) {
					$template_macros_refs[$template['uuid']][] = $macro['macro'];
				}
			}

			if ($template['templates']) {
				foreach ($template['templates'] as $linked_template) {
					$templates_refs += [$linked_template['name'] => []];
				}
			}
		}

		foreach ($this->getFormattedHosts() as $host) {
			$hosts_refs[$host['host']] = [];

			foreach ($host['groups'] as $group) {
				$groups_refs += [$group['name'] => []];
			}

			if (array_key_exists('macros', $host)) {
				foreach ($host['macros'] as $macro) {
					$host_macros_refs[$host['host']][] = $macro['macro'];
				}
			}

			if ($host['templates']) {
				foreach ($host['templates'] as $linked_template) {
					$templates_refs += [$linked_template['name'] => []];
				}
			}

			if ($host['proxy']) {
				$proxy_refs[$host['proxy']['name']] = [];
			}
		}

		foreach ($this->getFormattedItems() as $host => $items) {
			foreach ($items as $item) {
				$items_refs[$host][$item['key_']] = array_key_exists('uuid', $item)
					? ['uuid' => $item['uuid']]
					: [];

				if ($item['valuemap']) {
					$valuemaps_refs[$host][$item['valuemap']['name']] = [];
				}
			}
		}

		foreach ($this->getFormattedDiscoveryRules() as $host => $discovery_rules) {
			foreach ($discovery_rules as $discovery_rule) {
				$items_refs[$host][$discovery_rule['key_']] = array_key_exists('uuid', $discovery_rule)
					? ['uuid' => $discovery_rule['uuid']]
					: [];

				foreach ($discovery_rule['item_prototypes'] as $item_prototype) {
					$items_refs[$host][$item_prototype['key_']] = array_key_exists('uuid', $item_prototype)
						? ['uuid' => $item_prototype['uuid']]
						: [];

					if (!empty($item_prototype['valuemap'])) {
						$valuemaps_refs[$host][$item_prototype['valuemap']['name']] = [];
					}
				}

				foreach ($discovery_rule['trigger_prototypes'] as $trigger) {
					$description = $trigger['description'];
					$expression = $trigger['expression'];
					$recovery_expression = $trigger['recovery_expression'];

					$triggers_refs[$description][$expression][$recovery_expression] = array_key_exists('uuid', $trigger)
						? ['uuid' => $trigger['uuid']]
						: [];

					if (array_key_exists('dependencies', $trigger)) {
						foreach ($trigger['dependencies'] as $dependency) {
							$name = $dependency['name'];
							$expression = $dependency['expression'];
							$recovery_expression = $dependency['recovery_expression'];

							if (!array_key_exists($name, $triggers_refs)
									|| !array_key_exists($expression, $triggers_refs[$name])
									|| !array_key_exists($recovery_expression, $triggers_refs[$name][$expression])) {
								$triggers_refs[$name][$expression] = [];
							}
						}
					}
				}

				foreach ($discovery_rule['graph_prototypes'] as $graph) {
					if ($graph['ymin_item_1']) {
						$item_host = $graph['ymin_item_1']['host'];
						$item_key = $graph['ymin_item_1']['key'];

						if (!array_key_exists($item_host, $items_refs)
								|| !array_key_exists($item_key, $items_refs[$item_host])) {
							$items_refs[$item_host][$item_key] = [];
						}
					}

					if ($graph['ymax_item_1']) {
						$item_host = $graph['ymax_item_1']['host'];
						$item_key = $graph['ymax_item_1']['key'];

						if (!array_key_exists($item_host, $items_refs)
								|| !array_key_exists($item_key, $items_refs[$item_host])) {
							$items_refs[$item_host][$item_key] = [];
						}
					}

					foreach ($graph['gitems'] as $gitem) {
						$item_host = $gitem['item']['host'];
						$item_key = $gitem['item']['key'];

						if (!array_key_exists($item_host, $templates_refs)) {
							$hosts_refs[$item_host] = [];
						}

						if (!array_key_exists($item_host, $items_refs)
								|| !array_key_exists($item_key, $items_refs[$item_host])) {
							$items_refs[$item_host][$item_key] = [];
						}

						$graphs_refs[$item_host][$graph['name']] = array_key_exists('uuid', $graph)
							? ['uuid' => $graph['uuid']]
							: [];
					}
				}

				foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
					if (array_key_exists('uuid', $host_prototype)) {
						$host_prototypes_refs['uuid'][$host][$discovery_rule['uuid']][] = $host_prototype['uuid'];
					}
					else {
						$host_prototypes_refs['host'][$host][$discovery_rule['key_']][] = $host_prototype['host'];
					}

					foreach ($host_prototype['group_prototypes'] as $group_prototype) {
						if (isset($group_prototype['group'])) {
							$groups_refs += [$group_prototype['group']['name'] => []];
						}
					}

					if (array_key_exists('macros', $host_prototype)) {
						foreach ($host_prototype['macros'] as $macro) {
							if (array_key_exists('uuid', $host_prototype)) {
								$host_prototype_macros_refs['uuid'][$host][$discovery_rule['key_']]
									[$host_prototype['uuid']][] = $macro['macro'];
							}
							else {
								$host_prototype_macros_refs['host'][$host][$discovery_rule['key_']]
									[$host_prototype['host']][] = $macro['macro'];
							}
						}
					}

					foreach ($host_prototype['templates'] as $template) {
						$templates_refs += [$template['name'] => []];
					}
				}

				if ($discovery_rule['overrides']) {
					foreach ($discovery_rule['overrides'] as $override) {
						foreach ($override['operations'] as $operation) {
							if ($operation['operationobject'] == OPERATION_OBJECT_HOST_PROTOTYPE
									&& array_key_exists('optemplate', $operation)) {
								foreach ($operation['optemplate'] as $template) {
									$templates_refs += [$template['name'] => []];
								}
							}
						}
					}
				}
			}
		}

		foreach ($this->getFormattedGraphs() as $graph) {
			if ($graph['ymin_item_1']) {
				$item_host = $graph['ymin_item_1']['host'];
				$item_key = $graph['ymin_item_1']['key'];

				if (!array_key_exists($item_host, $templates_refs)) {
					$hosts_refs[$item_host] = [];
				}

				if (!array_key_exists($item_host, $items_refs)
						|| !array_key_exists($item_key, $items_refs[$item_host])) {
					$items_refs[$item_host][$item_key] = [];
				}
			}

			if ($graph['ymax_item_1']) {
				$item_host = $graph['ymax_item_1']['host'];
				$item_key = $graph['ymax_item_1']['key'];

				if (!array_key_exists($item_host, $templates_refs)) {
					$hosts_refs[$item_host] = [];
				}

				if (!array_key_exists($item_host, $items_refs)
						|| !array_key_exists($item_key, $items_refs[$item_host])) {
					$items_refs[$item_host][$item_key] = [];
				}
			}

			if (array_key_exists('gitems', $graph) && $graph['gitems']) {
				foreach ($graph['gitems'] as $gitem) {
					$item_host = $gitem['item']['host'];
					$item_key = $gitem['item']['key'];

					if (!array_key_exists($item_host, $templates_refs)) {
						$hosts_refs[$item_host] = [];
					}

					if (!array_key_exists($item_host, $items_refs)
							|| !array_key_exists($item_key, $items_refs[$item_host])) {
						$items_refs[$item_host][$item_key] = [];
					}

					$graphs_refs[$gitem['item']['host']][$graph['name']] = array_key_exists('uuid', $graph)
						? ['uuid' => $graph['uuid']]
						: [];
				}
			}
		}

		foreach ($this->getFormattedTriggers() as $trigger) {
			$triggers_refs[$trigger['description']][$trigger['expression']][$trigger['recovery_expression']] =
				array_key_exists('uuid', $trigger)
					? ['uuid' => $trigger['uuid']]
					: [];

			if (array_key_exists('dependencies', $trigger)) {
				foreach ($trigger['dependencies'] as $dependency) {
					$name = $dependency['name'];
					$expression = $dependency['expression'];
					$recovery_expression = $dependency['recovery_expression'];

					if (!array_key_exists($name, $triggers_refs)
							|| !array_key_exists($expression, $triggers_refs[$name])
							|| !array_key_exists($recovery_expression, $triggers_refs[$name][$expression])) {
						$triggers_refs[$name][$expression][$recovery_expression] = [];
					}
				}
			}
		}

		foreach ($this->getFormattedMaps() as $map) {
			$maps_refs[$map['name']] = [];

			if ($map['iconmap'] && array_key_exists('name', $map['iconmap']) && $map['iconmap']['name'] !== '') {
				$iconmaps_refs[$map['iconmap']['name']] = [];
			}

			if ($map['background'] && array_key_exists('name', $map['background'])
					&& $map['background']['name'] !== '') {
				$images_refs[$map['background']['name']] = [];
			}

			if (array_key_exists('selements', $map)) {
				foreach ($map['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_MAP:
							$maps_refs[$selement['elements'][0]['name']] = [];
							break;

						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$groups_refs += [$selement['elements'][0]['name'] => []];
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							$hosts_refs += [$selement['elements'][0]['host'] => []];
							break;

						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							foreach ($selement['elements'] as $element) {
								$description = $element['description'];
								$expression = $element['expression'];
								$recovery_expression = $element['recovery_expression'];

								if (!array_key_exists($description, $triggers_refs)
										|| !array_key_exists($expression, $triggers_refs[$description])
										|| !array_key_exists($recovery_expression,
											$triggers_refs[$description][$expression])) {
									$triggers_refs[$description][$expression][$recovery_expression] = [];
								}
							}
							break;
					}
				}
			}

			if (array_key_exists('links', $map)) {
				foreach ($map['links'] as $link) {
					if (array_key_exists('linktriggers', $link)) {
						foreach ($link['linktriggers'] as $link_trigger) {
							$description = $link_trigger['trigger']['description'];
							$expression = $link_trigger['trigger']['expression'];
							$recovery_expression = $link_trigger['trigger']['recovery_expression'];

							if (!array_key_exists($description, $triggers_refs)
									|| !array_key_exists($expression, $triggers_refs[$description])
									|| !array_key_exists($recovery_expression,
										$triggers_refs[$description][$expression])) {
								$triggers_refs[$description][$expression][$recovery_expression] = [];
							}

						}
					}
				}
			}
		}

		foreach ($this->getFormattedTemplateDashboards() as $host => $dashboards) {
			foreach ($dashboards as $dashboard) {
				$template_dashboards_refs[$dashboard['uuid']] = [];

				if (!$dashboard['pages']) {
					continue;
				}

				foreach ($dashboard['pages'] as $dashboard_page) {
					if (!$dashboard_page['widgets']) {
						continue;
					}

					foreach ($dashboard_page['widgets'] as $widget) {
						foreach ($widget['fields'] as $field) {
							$value = $field['value'];

							switch ($field['type']) {
								case ZBX_WIDGET_FIELD_TYPE_ITEM:
								case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
									$templates_refs += [$value['host'] => []];

									if (!array_key_exists($value['host'], $items_refs)
											|| !array_key_exists($value['key'], $items_refs[$value['host']])) {
										$items_refs[$value['host']][$value['key']] = [];
									}
									break;

								case ZBX_WIDGET_FIELD_TYPE_GRAPH:
								case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
									$templates_refs += [$value['host'] => []];

									if (!array_key_exists($value['host'], $graphs_refs)
											|| !array_key_exists($value['name'], $graphs_refs[$value['host']])) {
										$graphs_refs[$value['host']][$value['name']] = [];
									}
									break;
							}
						}
					}
				}
			}
		}

		foreach ($this->getFormattedHttpTests() as $host => $httptests) {
			foreach ($httptests as $httptest) {
				$httptests_refs[$host][$httptest['name']] = array_key_exists('uuid', $httptest)
					? ['uuid' => $httptest['uuid']]
					: [];
			}
		}

		foreach ($this->getFormattedHttpSteps() as $host => $httptests) {
			foreach ($httptests as $httptest_name => $httpsteps) {
				foreach ($httpsteps as $httpstep) {
					$httpsteps_refs[$host][$httptest_name][$httpstep['name']] = [];
				}
			}
		}

		foreach ($this->getFormattedImages() as $image) {
			$images_refs[$image['name']] = [];
		}

		$this->referencer->addGroups($groups_refs);
		$this->referencer->addTemplates($templates_refs);
		$this->referencer->addHosts($hosts_refs);
		$this->referencer->addItems($items_refs);
		$this->referencer->addValuemaps($valuemaps_refs);
		$this->referencer->addTriggers($triggers_refs);
		$this->referencer->addGraphs($graphs_refs);
		$this->referencer->addIconmaps($iconmaps_refs);
		$this->referencer->addImages($images_refs);
		$this->referencer->addMaps($maps_refs);
		$this->referencer->addTemplateDashboards($template_dashboards_refs);
		$this->referencer->addTemplateMacros($template_macros_refs);
		$this->referencer->addHostMacros($host_macros_refs);
		$this->referencer->addHostPrototypeMacros($host_prototype_macros_refs);
		$this->referencer->addProxies($proxy_refs);
		$this->referencer->addHostPrototypes($host_prototypes_refs);
		$this->referencer->addHttpTests($httptests_refs);
		$this->referencer->addHttpSteps($httpsteps_refs);
	}

	/**
	 * Import groups.
	 */
	protected function processGroups(): void {
		if (!$this->options['groups']['createMissing'] && !$this->options['groups']['updateExisting']) {
			return;
		}

		$groups_to_create = [];
		$groups_to_update = [];

		foreach ($this->getFormattedGroups() as $group) {
			$groupid = $this->referencer->findGroupidByUuid($group['uuid']);

			if ($groupid) {
				$groups_to_update[] = $group + ['groupid' => $groupid];
			}
			else {
				$groups_to_create[] = $group;
			}
		}

		if ($this->options['groups']['updateExisting'] && $groups_to_update) {
			API::HostGroup()->update(array_map(function($group) {
				unset($group['uuid']);
				return $group;
			}, $groups_to_update));

			foreach ($groups_to_update as $group) {
				$this->referencer->setDbGroup($group['groupid'], $group);
			}
		}

		if ($this->options['groups']['createMissing'] && $groups_to_create) {
			$created_groups = API::HostGroup()->create($groups_to_create);

			foreach ($created_groups['groupids'] as $index => $groupid) {
				$this->referencer->setDbGroup($groupid, $groups_to_create[$index]);
			}
		}
	}

	/**
	 * Import templates.
	 *
	 * @throws Exception
	 */
	protected function processTemplates(): void {
		if ($this->options['templates']['updateExisting'] || $this->options['templates']['createMissing']
				|| $this->options['process_templates']) {
			$templates = $this->getFormattedTemplates();

			if ($templates) {
				$template_importer = new CTemplateImporter($this->options, $this->referencer,
					$this->importedObjectContainer
				);
				$template_importer->import($templates);

				// Get list of imported template IDs and add them processed template ID list.
				$templateids = $template_importer->getProcessedTemplateids();
				$this->importedObjectContainer->addTemplateIds($templateids);
			}
		}
	}

	/**
	 * Import hosts.
	 *
	 * @throws Exception
	 */
	protected function processHosts(): void {
		if ($this->options['hosts']['updateExisting'] || $this->options['hosts']['createMissing']
				|| $this->options['process_hosts']) {
			$hosts = $this->getFormattedHosts();

			if ($hosts) {
				$host_importer = new CHostImporter($this->options, $this->referencer, $this->importedObjectContainer);
				$host_importer->import($hosts);

				// Get list of imported host IDs and add them processed host ID list.
				$hostids = $host_importer->getProcessedHostIds();
				$this->importedObjectContainer->addHostIds($hostids);
			}
		}
	}

	/**
	 * Import items.
	 *
	 * @throws Exception
	 */
	protected function processItems(): void {
		if (!$this->options['items']['createMissing'] && !$this->options['items']['updateExisting']) {
			return;
		}

		$master_item_key = 'master_item';
		$order_tree = $this->getItemsOrder($master_item_key);

		$items_to_create = [];
		$items_to_update = [];
		$levels = [];

		foreach ($this->getFormattedItems() as $host => $items) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			if ($hostid === null
					|| (!$this->importedObjectContainer->isHostProcessed($hostid)
						&& !$this->importedObjectContainer->isTemplateProcessed($hostid))) {
				continue;
			}

			foreach ($order_tree[$host] as $item_key => $level) {
				$item = $items[$item_key];
				$item['hostid'] = $hostid;
				$levels[$level] = true;

				if (array_key_exists('interface_ref', $item) && $item['interface_ref']) {
					$interfaceid = $this->referencer->findInterfaceidByRef($hostid, $item['interface_ref']);

					if ($interfaceid === null) {
						throw new Exception(_s('Cannot find interface "%1$s" used for item "%2$s" on "%3$s".',
							$item['interface_ref'], $item['name'], $host
						));
					}

					$item['interfaceid'] = $interfaceid;
				}

				if (array_key_exists('valuemap', $item) && $item['valuemap']) {
					$valuemapid = $this->referencer->findValuemapidByName($hostid, $item['valuemap']['name']);

					if ($valuemapid === null) {
						throw new Exception(_s(
							'Cannot find value map "%1$s" used for item "%2$s" on "%3$s".',
							$item['valuemap']['name'],
							$item['name'],
							$host
						));
					}

					$item['valuemapid'] = $valuemapid;
					unset($item['valuemap']);
				}

				if ($item['type'] == ITEM_TYPE_DEPENDENT) {
					if (!array_key_exists('key', $item[$master_item_key])) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_('cannot be empty')
						));
					}

					$master_itemid = $this->referencer->findItemidByKey($hostid, $item[$master_item_key]['key']);

					if ($master_itemid !== null) {
						$item['master_itemid'] = $master_itemid;
						unset($item[$master_item_key]);
					}
				}
				else {
					unset($item[$master_item_key]);
				}

				if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
					$headers = [];

					foreach ($item['headers'] as $header) {
						$headers[$header['name']] = $header['value'];
					}

					$item['headers'] = $headers;

					$query_fields = [];

					foreach ($item['query_fields'] as $query_field) {
						$query_fields[] = [$query_field['name'] => $query_field['value']];
					}

					$item['query_fields'] = $query_fields;
				}

				foreach ($item['preprocessing'] as &$preprocessing_step) {
					$preprocessing_step['params'] = implode("\n", $preprocessing_step['parameters']);
					unset($preprocessing_step['parameters']);
				}
				unset($preprocessing_step);

				$itemid = array_key_exists('uuid', $item)
					? $this->referencer->findItemidByUuid($item['uuid'])
					: $this->referencer->findItemidByKey($hostid, $item['key_']);

				if ($itemid !== null) {
					$item['itemid'] = $itemid;

					if (!array_key_exists($level, $items_to_update)) {
						$items_to_update[$level] = [];
					}

					$items_to_update[$level][] = $item;
				}
				else {
					if (!array_key_exists($level, $items_to_create)) {
						$items_to_create[$level] = [];
					}

					$items_to_create[$level][] = $item;
				}
			}
		}

		ksort($levels);
		foreach (array_keys($levels) as $level) {
			if ($this->options['items']['updateExisting'] && array_key_exists($level, $items_to_update)) {
				$this->updateItemsWithDependency([$items_to_update[$level]], $master_item_key, API::Item());
			}
			if ($this->options['items']['createMissing'] && array_key_exists($level, $items_to_create)) {
				$this->createItemsWithDependency([$items_to_create[$level]], $master_item_key, API::Item());
			}
		}

		// Refresh items because templated ones can be inherited to host and used in triggers, graphs, etc.
		$this->referencer->refreshItems();
	}

	/**
	 * Create CItem or CItemPrototype with dependency.
	 *
	 * @param array $items_by_level              Associative array of entities where key is entity dependency
	 *                                             level and value is array of entities for this level.
	 * @param string $master_item_key            Master entity array key in xml parsed data.
	 * @param CItem|CItemPrototype $api_service  Entity service which is capable to proceed with entity create.
	 *
	 * @throws Exception if entity master entity can not be resolved.
	 */
	protected function createItemsWithDependency(array $items_by_level, string $master_item_key,
			CItemGeneral $api_service): void {
		foreach ($items_by_level as $items_to_create) {
			foreach ($items_to_create as &$item) {
				if (array_key_exists($master_item_key, $item)) {
					$item['master_itemid'] = $this->referencer->findItemidByKey($item['hostid'],
						$item[$master_item_key]['key']
					);

					if ($item['master_itemid'] === null) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _s('value "%1$s" not found', $item[$master_item_key]['key'])
						));
					}
					unset($item[$master_item_key]);
				}
			}
			unset($item);

			$created_items = $api_service->create($items_to_create);

			foreach ($items_to_create as $index => $item) {
				$this->referencer->setDbItem($created_items['itemids'][$index], $item);
			}
		}
	}

	/**
	 * Update CItem or CItemPrototype with dependency.
	 *
	 * @param array $items_by_level              Associative array of entities where key is entity dependency
	 *                                             level and value is array of entities for this level.
	 * @param string $master_item_key            Master entity array key in xml parsed data.
	 * @param CItem|CItemPrototype $api_service  Entity service which is capable to proceed with entity update.
	 *
	 * @throws Exception if entity master entity can not be resolved.
	 */
	protected function updateItemsWithDependency(array $items_by_level, string $master_item_key,
			CItemGeneral $api_service): void {
		foreach ($items_by_level as $items_to_update) {
			foreach ($items_to_update as &$item) {
				if (array_key_exists($master_item_key, $item)) {
					$item['master_itemid'] = $this->referencer->findItemidByKey($item['hostid'],
						$item[$master_item_key]['key']
					);

					if ($item['master_itemid'] === null) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _s('value "%1$s" not found', $item[$master_item_key]['key'])
						));
					}
					unset($item[$master_item_key]);
				}
			}
			unset($item);

			$updated_items = $api_service->update(array_map(function($item) {
				unset($item['uuid']);
				return $item;
			}, $items_to_update));

			foreach ($items_to_update as $index => $item) {
				$this->referencer->setDbItem($updated_items['itemids'][$index], $item);
			}
		}
	}

	/**
	 * Import discovery rules.
	 *
	 * @throws Exception
	 */
	protected function processDiscoveryRules(): void {
		if (!$this->options['discoveryRules']['createMissing'] && !$this->options['discoveryRules']['updateExisting']) {
			return;
		}

		$master_item_key = 'master_item';
		$discovery_rules_by_hosts = $this->getFormattedDiscoveryRules();

		if (!$discovery_rules_by_hosts) {
			return;
		}

		// Unset rules that are related to hosts we did not process.
		foreach ($discovery_rules_by_hosts as $host => $discovery_rules) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			if ($hostid === null
					|| (!$this->importedObjectContainer->isHostProcessed($hostid)
						&& !$this->importedObjectContainer->isTemplateProcessed($hostid))) {
				unset($discovery_rules_by_hosts[$host]);
			}
		}

		if ($this->options['discoveryRules']['updateExisting']) {
			$this->deleteMissingPrototypes($discovery_rules_by_hosts);
		}

		$discovery_rules_to_create = [];
		$discovery_rules_to_update = [];

		foreach ($discovery_rules_by_hosts as $host => $discovery_rules) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			foreach ($discovery_rules as $discovery_rule) {
				$discovery_rule['hostid'] = $hostid;

				$itemid = array_key_exists('uuid', $discovery_rule)
					? $this->referencer->findItemidByUuid($discovery_rule['uuid'])
					: $this->referencer->findItemidByKey($hostid, $discovery_rule['key_']);

				unset($discovery_rule['item_prototypes'], $discovery_rule['trigger_prototypes'],
					$discovery_rule['graph_prototypes'], $discovery_rule['host_prototypes']
				);

				if (array_key_exists('interface_ref', $discovery_rule) && $discovery_rule['interface_ref']) {
					$interfaceid = $this->referencer->findInterfaceidByRef($hostid, $discovery_rule['interface_ref']);

					if ($interfaceid === null) {
						throw new Exception(_s('Cannot find interface "%1$s" used for discovery rule "%2$s" on "%3$s".',
							$discovery_rule['interface_ref'], $discovery_rule['name'], $host
						));
					}

					$discovery_rule['interfaceid'] = $interfaceid;
				}

				if ($discovery_rule['type'] == ITEM_TYPE_HTTPAGENT) {
					$headers = [];

					foreach ($discovery_rule['headers'] as $header) {
						$headers[$header['name']] = $header['value'];
					}

					$discovery_rule['headers'] = $headers;

					$query_fields = [];

					foreach ($discovery_rule['query_fields'] as $query_field) {
						$query_fields[] = [$query_field['name'] => $query_field['value']];
					}

					$discovery_rule['query_fields'] = $query_fields;
				}

				if ($discovery_rule['type'] == ITEM_TYPE_DEPENDENT) {
					if (!array_key_exists('key', $discovery_rule[$master_item_key])) {
						throw new Exception( _s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_('cannot be empty')
						));
					}

					$discovery_rule['master_itemid'] = $this->referencer->findItemidByKey($hostid,
						$discovery_rule[$master_item_key]['key']
					);
				}

				unset($discovery_rule[$master_item_key]);

				if ($discovery_rule['overrides']) {
					foreach ($discovery_rule['overrides'] as &$override) {
						foreach ($override['operations'] as &$operation) {
							if ($operation['operationobject'] == OPERATION_OBJECT_HOST_PROTOTYPE
									&& array_key_exists('optemplate', $operation)) {
								foreach ($operation['optemplate'] as &$template) {
									$templateid = $this->referencer->findTemplateidByHost($template['name']);

									if ($templateid === null) {
										throw new Exception(_s(
											'Cannot find template "%1$s" for override "%2$s" of discovery rule "%3$s" on "%4$s".',
											$template['name'],
											$override['name'],
											$discovery_rule['name'],
											$host
										));
									}

									$template['templateid'] = $templateid;
									unset($template['name']);
								}
								unset($template);
							}
						}
						unset($operation);
					}
					unset($override);
				}

				foreach ($discovery_rule['preprocessing'] as &$preprocessing_step) {
					$preprocessing_step['params'] = implode("\n", $preprocessing_step['parameters']);
					unset($preprocessing_step['parameters']);
				}
				unset($preprocessing_step);

				if ($itemid !== null) {
					$discovery_rule['itemid'] = $itemid;
					unset($discovery_rule['uuid']);
					$discovery_rules_to_update[] = $discovery_rule;
				}
				else {
					/*
					 * The array key "lld_macro_paths" must exist at this point. It is processed by chain conversion.
					 * Unlike discoveryrule.update method, discoveryrule.create does not allow "lld_macro_paths"
					 * to be empty.
					 */
					if (!$discovery_rule['lld_macro_paths']) {
						unset($discovery_rule['lld_macro_paths']);
					}
					$discovery_rules_to_create[] = $discovery_rule;
				}
			}
		}

		$processed_discovery_rules = [];

		if ($this->options['discoveryRules']['createMissing'] && $discovery_rules_to_create) {
			API::DiscoveryRule()->create($discovery_rules_to_create);

			foreach ($discovery_rules_to_create as $discovery_rule) {
				$processed_discovery_rules[$discovery_rule['hostid']][$discovery_rule['key_']] = 1;
			}
		}

		if ($this->options['discoveryRules']['updateExisting'] && $discovery_rules_to_update) {
			API::DiscoveryRule()->update($discovery_rules_to_update);

			foreach ($discovery_rules_to_update as $discovery_rule) {
				$processed_discovery_rules[$discovery_rule['hostid']][$discovery_rule['key_']] = 1;
			}
		}

		// Refresh discovery rules because templated ones can be inherited to host and used for prototypes.
		$this->referencer->refreshItems();

		$order_tree = $this->getDiscoveryRulesItemsOrder($master_item_key);

		// process prototypes
		$item_prototypes_to_update = [];
		$item_prototypes_to_create = [];
		$host_prototypes_to_update = [];
		$host_prototypes_to_create = [];
		$levels = [];

		foreach ($discovery_rules_by_hosts as $host => $discovery_rules) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			foreach ($discovery_rules as $discovery_rule) {
				// if rule was not processed we should not create/update any of its prototypes
				if (!array_key_exists($discovery_rule['key_'], $processed_discovery_rules[$hostid])) {
					continue;
				}

				$itemid = $this->referencer->findItemidByKey($hostid, $discovery_rule['key_']);

				// prototypes
				$item_prototypes = $discovery_rule['item_prototypes'] ? $order_tree[$host][$discovery_rule['key_']] : [];

				foreach ($item_prototypes as $index => $level) {
					$item_prototype = $discovery_rule['item_prototypes'][$index];
					$item_prototype['hostid'] = $hostid;
					$levels[$level] = true;

					if (array_key_exists('interface_ref', $item_prototype) && $item_prototype['interface_ref']) {
						$interfaceid = $this->referencer->findInterfaceidByRef($hostid,
							$item_prototype['interface_ref']
						);

						if ($interfaceid !== null) {
							$item_prototype['interfaceid'] = $interfaceid;
						}
						else {
							throw new Exception(_s(
								'Cannot find interface "%1$s" used for item prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$item_prototype['interface_ref'],
								$item_prototype['name'],
								$discovery_rule['name'],
								$host
							));
						}
					}

					if ($item_prototype['valuemap']) {
						$valuemapid = $this->referencer->findValuemapidByName($hostid,
							$item_prototype['valuemap']['name']
						);

						if ($valuemapid === null) {
							throw new Exception(_s(
								'Cannot find value map "%1$s" used for item prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$item_prototype['valuemap']['name'],
								$item_prototype['name'],
								$discovery_rule['name'],
								$host
							));
						}

						$item_prototype['valuemapid'] = $valuemapid;
						unset($item_prototype['valuemap']);
					}

					if ($item_prototype['type'] == ITEM_TYPE_DEPENDENT) {
						if (!array_key_exists('key', $item_prototype[$master_item_key])) {
							throw new Exception( _s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
								_('cannot be empty')
							));
						}

						$master_item_prototypeid = $this->referencer->findItemidByKey($hostid,
							$item_prototype[$master_item_key]['key']
						);

						if ($master_item_prototypeid !== null) {
							$item_prototype['master_itemid'] = $master_item_prototypeid;
							unset($item_prototype[$master_item_key]);
						}
					}
					else {
						unset($item_prototype[$master_item_key]);
					}

					if ($item_prototype['type'] == ITEM_TYPE_HTTPAGENT) {
						$headers = [];

						foreach ($item_prototype['headers'] as $header) {
							$headers[$header['name']] = $header['value'];
						}

						$item_prototype['headers'] = $headers;

						$query_fields = [];

						foreach ($item_prototype['query_fields'] as $query_field) {
							$query_fields[] = [$query_field['name'] => $query_field['value']];
						}

						$item_prototype['query_fields'] = $query_fields;
					}

					$item_prototypeid = array_key_exists('uuid', $item_prototype)
						? $this->referencer->findItemidByUuid($item_prototype['uuid'])
						: $this->referencer->findItemidByKey($hostid, $item_prototype['key_']);

					$item_prototype['rule'] = [
						'hostid' => $hostid,
						'key' => $discovery_rule['key_']
					];
					$item_prototype['ruleid'] = $itemid;

					foreach ($item_prototype['preprocessing'] as &$preprocessing_step) {
						$preprocessing_step['params'] = implode("\n", $preprocessing_step['parameters']);
						unset($preprocessing_step['parameters']);
					}
					unset($preprocessing_step);

					if ($item_prototypeid !== null) {
						if (!array_key_exists($level, $item_prototypes_to_update)) {
							$item_prototypes_to_update[$level] = [];
						}
						$item_prototype['itemid'] = $item_prototypeid;
						$item_prototypes_to_update[$level][] = $item_prototype;
					}
					else {
						if (!array_key_exists($level, $item_prototypes_to_create)) {
							$item_prototypes_to_create[$level] = [];
						}
						$item_prototypes_to_create[$level][] = $item_prototype;
					}
				}

				foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
					// Resolve group prototypes.
					$group_links = [];

					foreach ($host_prototype['group_links'] as $group_link) {
						$groupid = $this->referencer->findGroupidByName($group_link['group']['name']);

						if ($groupid === null) {
							throw new Exception(_s(
								'Cannot find host group "%1$s" for host prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$group_link['group']['name'],
								$host_prototype['name'],
								$discovery_rule['name'],
								$host
							));
						}

						$group_links[] = ['groupid' => $groupid];
					}

					$host_prototype['groupLinks'] = $group_links;
					$host_prototype['groupPrototypes'] = $host_prototype['group_prototypes'];
					unset($host_prototype['group_links'], $host_prototype['group_prototypes']);

					// Resolve templates.
					$templates = [];

					foreach ($host_prototype['templates'] as $template) {
						$templateid = $this->referencer->findTemplateidByHost($template['name']);

						if ($templateid === null) {
							throw new Exception(_s(
								'Cannot find template "%1$s" for host prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$template['name'],
								$host_prototype['name'],
								$discovery_rule['name'],
								$host
							));
						}

						$templates[] = ['templateid' => $templateid];
					}

					$host_prototype['templates'] = $templates;

					$host_prototypeid = array_key_exists('uuid', $host_prototype)
						? $this->referencer->findHostPrototypeidByUuid($host_prototype['uuid'])
						: $this->referencer->findHostPrototypeidByHost($hostid, $itemid,
							$host_prototype['host']
						);

					if ($host_prototypeid !== null) {
						if (array_key_exists('macros', $host_prototype)) {
							foreach ($host_prototype['macros'] as &$macro) {
								$hostmacroid = $this->referencer->findHostPrototypeMacroid($host_prototypeid,
									$macro['macro']
								);

								if ($hostmacroid !== null) {
									$macro['hostmacroid'] = $hostmacroid;
								}
							}
							unset($macro);
						}

						$host_prototype['hostid'] = $host_prototypeid;
						unset($host_prototype['uuid']);
						$host_prototypes_to_update[] = $host_prototype;
					}
					else {
						$host_prototype['ruleid'] = $itemid;
						$host_prototypes_to_create[] = $host_prototype;
					}
				}
			}
		}

		ksort($levels);
		foreach (array_keys($levels) as $level) {
			if (array_key_exists($level, $item_prototypes_to_update) && $item_prototypes_to_update[$level]) {
				$this->updateItemsWithDependency([$item_prototypes_to_update[$level]], $master_item_key,
					API::ItemPrototype()
				);
			}
			if (array_key_exists($level, $item_prototypes_to_create) && $item_prototypes_to_create[$level]) {
				$this->createItemsWithDependency([$item_prototypes_to_create[$level]], $master_item_key,
					API::ItemPrototype()
				);
			}
		}

		if ($host_prototypes_to_update) {
			API::HostPrototype()->update($host_prototypes_to_update);
		}

		if ($host_prototypes_to_create) {
			API::HostPrototype()->create($host_prototypes_to_create);
		}

		// Refresh prototypes because templated ones can be inherited to host and used in triggers prototypes or graph
		//   prototypes.
		$this->referencer->refreshItems();

		// First we need to create item prototypes and only then trigger and graph prototypes.
		$triggers_to_create = [];
		$triggers_to_update = [];
		$graphs_to_create = [];
		$graphs_to_update = [];

		// The list of triggers to process dependencies.
		$triggers = [];

		foreach ($discovery_rules_by_hosts as $host => $discovery_rules) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			foreach ($discovery_rules as $discovery_rule) {
				// If rule was not processed we should not create/update any of its prototypes.
				if (!array_key_exists($discovery_rule['key_'], $processed_discovery_rules[$hostid])) {
					continue;
				}

				foreach ($discovery_rule['trigger_prototypes'] as $trigger) {
					$triggerid = array_key_exists('uuid', $trigger)
						? $this->referencer->findTriggeridByUuid($trigger['uuid'])
						: $this->referencer->findTriggeridByName($trigger['description'], $trigger['expression'],
							$trigger['recovery_expression']
						);

					$triggers[] = $trigger;
					unset($trigger['dependencies']);

					if ($triggerid !== null) {
						$trigger['triggerid'] = $triggerid;
						$triggers_to_update[] = $trigger;
					}
					else {
						$triggers_to_create[] = $trigger;
					}
				}

				foreach ($discovery_rule['graph_prototypes'] as $graph) {
					if ($graph['ymin_item_1']) {
						$hostid = $this->referencer->findTemplateidOrHostidByHost($graph['ymin_item_1']['host']);

						$itemid = ($hostid !== null)
							? $this->referencer->findItemidByKey($hostid, $graph['ymin_item_1']['key'])
							: null;

						if ($itemid === null) {
							throw new Exception(_s(
								'Cannot find item "%1$s" on "%2$s" used as the Y axis MIN value for graph prototype "%3$s" of discovery rule "%4$s" on "%5$s".',
								$graph['ymin_item_1']['key'],
								$graph['ymin_item_1']['host'],
								$graph['name'],
								$discovery_rule['name'],
								$host
							));
						}

						$graph['ymin_itemid'] = $itemid;
					}

					if ($graph['ymax_item_1']) {
						$hostid = $this->referencer->findTemplateidOrHostidByHost($graph['ymax_item_1']['host']);

						$itemid = ($hostid !== null)
							? $this->referencer->findItemidByKey($hostid, $graph['ymax_item_1']['key'])
							: null;

						if ($itemid === null) {
							throw new Exception(_s(
								'Cannot find item "%1$s" on "%2$s" used as the Y axis MAX value for graph prototype "%3$s" of discovery rule "%4$s" on "%5$s".',
								$graph['ymax_item_1']['key'],
								$graph['ymax_item_1']['host'],
								$graph['name'],
								$discovery_rule['name'],
								$host
							));
						}

						$graph['ymax_itemid'] = $itemid;
					}

					foreach ($graph['gitems'] as &$item) {
						$hostid = $this->referencer->findTemplateidOrHostidByHost($item['item']['host']);

						$item['itemid'] = ($hostid !== null)
							? $this->referencer->findItemidByKey($hostid, $item['item']['key'])
							: null;

						if ($item['itemid'] === null) {
							throw new Exception(_s(
								'Cannot find item "%1$s" on "%2$s" used in graph prototype "%3$s" of discovery rule "%4$s" on "%5$s".',
								$item['item']['key'],
								$item['item']['host'],
								$graph['name'],
								$discovery_rule['name'],
								$host
							));
						}
					}
					unset($item);

					$graphid = array_key_exists('uuid', $graph)
						? $this->referencer->findGraphidByUuid($graph['uuid'])
						: $this->referencer->findGraphidByName($hostid, $graph['name']);

					if ($graphid !== null) {
						$graph['graphid'] = $graphid;
						unset($graph['uuid']);
						$graphs_to_update[] = $graph;
					}
					else {
						$graphs_to_create[] = $graph;
					}
				}
			}
		}

		if ($triggers_to_update) {
			$updated_triggers = API::TriggerPrototype()->update(array_map(function($trigger) {
				unset($trigger['uuid']);
				return $trigger;
			}, $triggers_to_update));

			foreach ($updated_triggers['triggerids'] as $index => $triggerid) {
				$trigger = $triggers_to_update[$index];
				$this->referencer->setDbTrigger($triggerid, $trigger);
			}
		}

		if ($triggers_to_create) {
			$created_triggers = API::TriggerPrototype()->create($triggers_to_create);

			foreach ($created_triggers['triggerids'] as $index => $triggerid) {
				$trigger = $triggers_to_create[$index];
				$this->referencer->setDbTrigger($triggerid, $trigger);
			}
		}

		if ($graphs_to_update) {
			API::GraphPrototype()->update($graphs_to_update);
			$this->referencer->refreshGraphs();
		}

		if ($graphs_to_create) {
			API::GraphPrototype()->create($graphs_to_create);
			$this->referencer->refreshGraphs();
		}

		$this->processTriggerPrototypeDependencies($triggers);
	}

	/**
	 * Update trigger prototype dependencies.
	 *
	 * @param array $triggers
	 *
	 * @throws Exception
	 */
	protected function processTriggerPrototypeDependencies(array $triggers): void {
		$trigger_dependencies = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$dependencies = [];
			$triggerid = $this->referencer->findTriggeridByName($trigger['description'], $trigger['expression'],
				$trigger['recovery_expression']
			);

			foreach ($trigger['dependencies'] as $dependency) {
				$dependent_triggerid = $this->referencer->findTriggeridByName($dependency['name'],
					$dependency['expression'], $dependency['recovery_expression']
				);

				if ($dependent_triggerid === null) {
					throw new Exception(_s('Trigger prototype "%1$s" depends on trigger "%2$s", which does not exist.',
						$trigger['description'],
						$dependency['name']
					));
				}

				$dependencies[] = ['triggerid' => $dependent_triggerid];
			}

			$trigger_dependencies[] = [
				'triggerid' => $triggerid,
				'dependencies' => $dependencies
			];
		}

		if ($trigger_dependencies) {
			API::TriggerPrototype()->update($trigger_dependencies);
		}
	}

	/**
	 * Import web scenarios.
	 *
	 * @throws APIException
	 */
	protected function processHttpTests(): void {
		if (!$this->options['httptests']['createMissing'] && !$this->options['httptests']['updateExisting']) {
			return;
		}

		$httptests_to_create = [];
		$httptests_to_update = [];

		foreach ($this->getFormattedHttpTests() as $host => $httptests) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			if ($hostid === null
					|| (!$this->importedObjectContainer->isHostProcessed($hostid)
						&& !$this->importedObjectContainer->isTemplateProcessed($hostid))) {
				continue;
			}

			foreach ($httptests as $httptest) {
				$httptestid = array_key_exists('uuid', $httptest)
					? $this->referencer->findHttpTestidByUuid($httptest['uuid'])
					: $this->referencer->findHttpTestidByName($hostid, $httptest['name']);

				if ($httptestid !== null) {
					foreach ($httptest['steps'] as &$httpstep) {
						$httpstepid = $this->referencer->findHttpStepidByName($hostid, $httptestid, $httpstep['name']);

						if ($httpstepid !== null) {
							$httpstep['httpstepid'] = $httpstepid;
						}
					}
					unset($httpstep);

					$httptest['httptestid'] = $httptestid;
					unset($httptest['uuid']);

					$httptests_to_update[] = $httptest;
				}
				else {
					$httptest['hostid'] = $hostid;
					$httptests_to_create[] = $httptest;
				}
			}
		}

		if ($this->options['httptests']['updateExisting'] && $httptests_to_update) {
			API::HttpTest()->update($httptests_to_update);
		}

		if ($this->options['httptests']['createMissing'] && $httptests_to_create) {
			API::HttpTest()->create($httptests_to_create);
		}

		$this->referencer->refreshHttpTests();
	}

	/**
	 * Import graphs.
	 *
	 * @throws Exception
	 */
	protected function processGraphs(): void {
		if (!$this->options['graphs']['createMissing'] && !$this->options['graphs']['updateExisting']) {
			return;
		}

		$graphs_to_create = [];
		$graphs_to_update = [];

		foreach ($this->getFormattedGraphs() as $graph) {
			if ($graph['ymin_item_1']) {
				$hostid = $this->referencer->findTemplateidOrHostidByHost($graph['ymin_item_1']['host']);
				$itemid = ($hostid !== null)
					? $this->referencer->findItemidByKey($hostid, $graph['ymin_item_1']['key'])
					: null;

				if ($itemid === null) {
					throw new Exception(_s(
						'Cannot find item "%1$s" on "%2$s" used as the Y axis MIN value for graph "%3$s".',
						$graph['ymin_item_1']['key'],
						$graph['ymin_item_1']['host'],
						$graph['name']
					));
				}

				$graph['ymin_itemid'] = $itemid;
			}

			if ($graph['ymax_item_1']) {
				$hostid = $this->referencer->findTemplateidOrHostidByHost($graph['ymax_item_1']['host']);
				$itemid = ($hostid !== null)
					? $this->referencer->findItemidByKey($hostid, $graph['ymax_item_1']['key'])
					: null;

				if ($itemid === null) {
					throw new Exception(_s(
						'Cannot find item "%1$s" on "%2$s" used as the Y axis MAX value for graph "%3$s".',
						$graph['ymax_item_1']['key'],
						$graph['ymax_item_1']['host'],
						$graph['name']
					));
				}

				$graph['ymax_itemid'] = $itemid;
			}

			$hostid = null;

			foreach ($graph['gitems'] as &$item) {
				$hostid = $this->referencer->findTemplateidOrHostidByHost($item['item']['host']);
				$item['itemid'] = ($hostid !== null)
					? $this->referencer->findItemidByKey($hostid, $item['item']['key'])
					: null;

				if ($item['itemid'] === null) {
					throw new Exception(_s(
						'Cannot find item "%1$s" on "%2$s" used in graph "%3$s".',
						$item['item']['key'],
						$item['item']['host'],
						$graph['name']
					));
				}
			}
			unset($item);

			if ($this->isTemplateGraph($graph)) {
				$graphid = $this->referencer->findGraphidByUuid($graph['uuid']);
			}
			else {
				unset($graph['uuid']);
				$graphid = $this->referencer->findGraphidByName($hostid, $graph['name']);
			}

			if ($graphid !== null) {
				$graph['graphid'] = $graphid;
				unset($graph['uuid']);
				$graphs_to_update[] = $graph;
			}
			else {
				$graphs_to_create[] = $graph;
			}
		}

		if ($this->options['graphs']['updateExisting'] && $graphs_to_update) {
			API::Graph()->update($graphs_to_update);
		}

		if ($this->options['graphs']['createMissing'] && $graphs_to_create) {
			API::Graph()->create($graphs_to_create);
		}

		$this->referencer->refreshGraphs();
	}

	private function isTemplateGraph(array $graph): bool {
		if ($graph['ymin_item_1'] && $this->referencer->findTemplateidByHost($graph['ymin_item_1']['host'])) {
			return true;
		}

		if ($graph['ymax_item_1'] && $this->referencer->findTemplateidByHost($graph['ymax_item_1']['host'])) {
			return true;
		}

		if (array_key_exists('gitems', $graph) && $graph['gitems']) {
			foreach ($graph['gitems'] as $gitem) {
				if ($this->referencer->findTemplateidByHost($gitem['item']['host'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Import triggers.
	 *
	 * @throws Exception
	 */
	protected function processTriggers(): void {
		if (!$this->options['triggers']['createMissing'] && !$this->options['triggers']['updateExisting']) {
			return;
		}

		$triggers_to_create = [];
		$triggers_to_update = [];

		$triggers_to_process_dependencies = [];

		foreach ($this->getFormattedTriggers() as $trigger) {
			$triggerid = null;

			$is_template_trigger = $this->isTemplateTrigger($trigger);

			if ($is_template_trigger && array_key_exists('uuid', $trigger)) {
				$triggerid = $this->referencer->findTriggeridByUuid($trigger['uuid']);
			}
			elseif (!$is_template_trigger) {
				unset($trigger['uuid']);
				$triggerid = $this->referencer->findTriggeridByName($trigger['description'], $trigger['expression'],
					$trigger['recovery_expression']
				);
			}

			if ($triggerid !== null) {
				if ($this->options['triggers']['updateExisting']) {
					$triggers_to_process_dependencies[] = $trigger;

					$trigger['triggerid'] = $triggerid;
					unset($trigger['dependencies'], $trigger['uuid']);
					$triggers_to_update[] = $trigger;
				}
			}
			else {
				if ($this->options['triggers']['createMissing']) {
					$triggers_to_process_dependencies[] = $trigger;

					unset($trigger['dependencies']);
					$triggers_to_create[] = $trigger;
				}
			}
		}

		if ($triggers_to_update) {
			API::Trigger()->update($triggers_to_update);
		}

		if ($triggers_to_create) {
			API::Trigger()->create($triggers_to_create);
		}

		// Refresh triggers because template triggers can be inherited to host and used in maps.
		$this->referencer->refreshTriggers();

		$this->processTriggerDependencies($triggers_to_process_dependencies);
	}

	private function isTemplateTrigger(array $trigger): bool {
		$expression_parser = new CExpressionParser(['usermacros' => true]);

		if ($expression_parser->parse($trigger['expression']) != CParser::PARSE_SUCCESS) {
			return false;
		}

		foreach ($expression_parser->getResult()->getHosts() as $host) {
			$host = $this->referencer->findTemplateidByHost($host);

			if ($host !== null) {
				return true;
			}
		}

		if ($trigger['recovery_expression'] === ''
				|| $expression_parser->parse($trigger['recovery_expression']) != CParser::PARSE_SUCCESS) {
			return false;
		}

		foreach ($expression_parser->getResult()->getHosts() as $host) {
			$host = $this->referencer->findTemplateidByHost($host);

			if ($host !== null) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update trigger dependencies
	 *
	 * @param array $triggers
	 *
	 * @throws Exception
	 */
	protected function processTriggerDependencies(array $triggers): void {
		$trigger_dependencies = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$triggerid = $this->referencer->findTriggeridByName($trigger['description'], $trigger['expression'],
				$trigger['recovery_expression']
			);

			$dependencies = [];

			foreach ($trigger['dependencies'] as $dependency) {
				$dependent_triggerid = $this->referencer->findTriggeridByName($dependency['name'],
					$dependency['expression'], $dependency['recovery_expression']
				);

				if ($dependent_triggerid === null) {
					throw new Exception(_s('Trigger "%1$s" depends on trigger "%2$s", which does not exist.',
						$trigger['description'],
						$dependency['name']
					));
				}

				$dependencies[] = ['triggerid' => $dependent_triggerid];
			}

			$trigger_dependencies[] = [
				'triggerid' => $triggerid,
				'dependencies' => $dependencies
			];
		}

		if ($trigger_dependencies) {
			API::Trigger()->update($trigger_dependencies);
		}
	}

	/**
	 * Import images.
	 *
	 * @throws Exception
	 */
	protected function processImages(): void {
		if (!$this->options['images']['updateExisting'] && !$this->options['images']['createMissing']) {
			return;
		}

		$images_to_import = $this->getFormattedImages();

		if (!$images_to_import) {
			return;
		}

		$images_to_update = [];
		$images_to_create = [];

		foreach ($images_to_import as $image) {
			$imageid = $this->referencer->findImageidByName($image['name']);

			if ($imageid !== null) {
				$image['imageid'] = $imageid;
				unset($image['imagetype']);
				$images_to_update[] = $image;
			}
			else {
				$images_to_create[] = $image;
			}
		}

		if ($this->options['images']['updateExisting'] && $images_to_update) {
			API::Image()->update($images_to_update);
		}

		if ($this->options['images']['createMissing'] && $images_to_create) {
			$created_images = API::Image()->create($images_to_create);

			foreach ($images_to_create as $index => $image) {
				$this->referencer->setDbImage($created_images['imageids'][$index], $image);
			}
		}
	}

	/**
	 * Import maps.
	 *
	 * @throws Exception
	 */
	protected function processMaps(): void {
		if ($this->options['maps']['updateExisting'] || $this->options['maps']['createMissing']) {
			$maps = $this->getFormattedMaps();

			if ($maps) {
				$map_importer = new CMapImporter($this->options, $this->referencer, $this->importedObjectContainer);
				$map_importer->import($maps);
			}
		}
	}

	/**
	 * Import template dashboards.
	 */
	protected function processTemplateDashboards(): void {
		if ($this->options['templateDashboards']['updateExisting']
				|| $this->options['templateDashboards']['createMissing']) {
			$dashboards = $this->getFormattedTemplateDashboards();

			if ($dashboards) {
				$dashboard_importer = new CTemplateDashboardImporter($this->options, $this->referencer,
					$this->importedObjectContainer
				);

				$dashboard_importer->import($dashboards);
			}
		}
	}

	/**
	 * Import media types.
	 */
	protected function processMediaTypes(): void {
		if (!$this->options['mediaTypes']['updateExisting'] && !$this->options['mediaTypes']['createMissing']) {
			return;
		}

		$media_types_to_import = $this->getFormattedMediaTypes();

		if (!$media_types_to_import) {
			return;
		}

		$media_types_to_import = zbx_toHash($media_types_to_import, 'name');

		$db_media_types = API::MediaType()->get([
			'output' => ['mediatypeid', 'name'],
			'filter' => ['name' => array_keys($media_types_to_import)]
		]);
		$db_media_types = zbx_toHash($db_media_types, 'name');

		$media_types_to_update = [];
		$media_types_to_create = [];

		foreach ($media_types_to_import as $name => $media_type) {
			if (array_key_exists($name, $db_media_types)) {
				$media_type['mediatypeid'] = $db_media_types[$name]['mediatypeid'];
				$media_types_to_update[] = $media_type;
			}
			else {
				$media_types_to_create[] = $media_type;
			}
		}

		if ($this->options['mediaTypes']['updateExisting'] && $media_types_to_update) {
			API::MediaType()->update($media_types_to_update);
		}

		if ($this->options['mediaTypes']['createMissing'] && $media_types_to_create) {
			API::MediaType()->create($media_types_to_create);
		}
	}

	/**
	 * Deletes items from DB that are missing in import file.
	 */
	protected function deleteMissingItems(): void {
		if (!$this->options['items']['deleteMissing']) {
			return;
		}

		$processed_hostids = array_merge(
			$this->importedObjectContainer->getHostids(),
			$this->importedObjectContainer->getTemplateids()
		);

		if (!$processed_hostids) {
			return;
		}

		$itemids = [];

		foreach ($this->getFormattedItems() as $host => $items) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			if ($hostid === null) {
				continue;
			}

			foreach ($items as $item) {
				$itemid = array_key_exists('uuid', $item)
					? $this->referencer->findItemidByUuid($item['uuid'])
					: $this->referencer->findItemidByKey($hostid, $item['key_']);

				if ($itemid) {
					$itemids[$itemid] = [];
				}
			}
		}

		$db_itemids = API::Item()->get([
			'output' => ['itemid'],
			'hostids' => $processed_hostids,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'inherited' => false,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$items_to_delete = array_diff_key($db_itemids, $itemids);

		if ($items_to_delete) {
			API::Item()->delete(array_keys($items_to_delete));
		}

		$this->referencer->refreshItems();
	}

	/**
	 * Deletes triggers from DB that are missing in import file.
	 */
	protected function deleteMissingTriggers(): void {
		if (!$this->options['triggers']['deleteMissing']) {
			return;
		}

		$processed_hostids = array_merge(
			$this->importedObjectContainer->getHostids(),
			$this->importedObjectContainer->getTemplateids()
		);

		if (!$processed_hostids) {
			return;
		}

		$triggerids = [];

		foreach ($this->getFormattedTriggers() as $trigger) {
			$triggerid = null;

			if (array_key_exists('uuid', $trigger)) {
				$triggerid = $this->referencer->findTriggeridByUuid($trigger['uuid']);
			}

			// In import file host trigger can have UUID assigned after conversion, such should be searched by name.
			if ($triggerid === null) {
				$triggerid = $this->referencer->findTriggeridByName($trigger['description'], $trigger['expression'],
					$trigger['recovery_expression']
				);

				// Template triggers should only be searched by UUID.
				if ($triggerid !== null && array_key_exists('uuid', $trigger)) {
					$db_trigger = $this->referencer->findTriggerById($triggerid);

					if ($db_trigger['uuid'] !== '' && $db_trigger['uuid'] !== $trigger['uuid']) {
						$triggerid = null;
					}
				}
			}

			if ($triggerid !== null) {
				$triggerids[$triggerid] = [];
			}
		}

		$db_triggerids = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'hostids' => $processed_hostids,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'inherited' => false,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		// Check that potentially deletable trigger belongs to same hosts that are in the import file.
		// If some triggers belong to more hosts than import file contains, don't delete them.
		$triggerids_to_delete = [];
		$processed_hostids = array_flip($processed_hostids);

		foreach (array_diff_key($db_triggerids, $triggerids) as $triggerid => $trigger) {
			$trigger_hostids = array_flip(array_column($trigger['hosts'], 'hostid'));

			if (!array_diff_key($trigger_hostids, $processed_hostids)) {
				$triggerids_to_delete[] = $triggerid;
			}
		}

		if ($triggerids_to_delete) {
			API::Trigger()->delete($triggerids_to_delete);
		}

		// refresh triggers because template triggers can be inherited to host and used in maps
		$this->referencer->refreshTriggers();
	}

	/**
	 * Deletes graphs from DB that are missing in import file.
	 */
	protected function deleteMissingGraphs(): void {
		if (!$this->options['graphs']['deleteMissing']) {
			return;
		}

		$processed_hostids = array_merge(
			$this->importedObjectContainer->getHostids(),
			$this->importedObjectContainer->getTemplateids()
		);

		if (!$processed_hostids) {
			return;
		}

		$graphids = [];

		foreach ($this->getFormattedGraphs() as $graph) {
			$graphid = null;

			if (array_key_exists('uuid', $graph)) {
				$graphid = $this->referencer->findGraphidByUuid($graph['uuid']);
			}

			if ($graphid !== null) {
				$graphids[$graphid] = [];
			}
			elseif (array_key_exists('gitems', $graph)) {
				// In import file host graph can have UUID assigned after conversion, such should be searched by name.
				foreach ($graph['gitems'] as $gitem) {
					$gitem_hostid = $this->referencer->findTemplateidOrHostidByHost($gitem['item']['host']);
					$graphid = $this->referencer->findGraphidByName($gitem_hostid, $graph['name']);

					if ($graphid !== null) {
						$graphids[$graphid] = [];
					}
				}
			}
		}

		$db_graphids = API::Graph()->get([
			'output' => ['graphid'],
			'hostids' => $processed_hostids,
			'selectHosts' => ['hostid'],
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		// check that potentially deletable graph belongs to same hosts that are in XML
		// if some graphs belong to more hosts than current XML contains, don't delete them
		$graphids_to_delete = [];
		$processed_hostids = array_flip($processed_hostids);

		foreach (array_diff_key($db_graphids, $graphids) as $graphid => $graph) {
			$graph_hostids = array_flip(array_column($graph['hosts'], 'hostid'));

			if (!array_diff_key($graph_hostids, $processed_hostids)) {
				$graphids_to_delete[] = $graphid;
			}
		}

		if ($graphids_to_delete) {
			API::Graph()->delete($graphids_to_delete);
		}

		$this->referencer->refreshGraphs();
	}

	/**
	 * Deletes prototypes from DB that are missing in import file.
	 *
	 * @param array $discovery_rules_by_hosts
	 *
	 * @throws APIException
	 */
	protected function deleteMissingPrototypes(array $discovery_rules_by_hosts): void {
		$discovery_ruleids = [];
		$host_prototypeids = [];
		$trigger_prototypeids = [];
		$graph_prototypeids = [];
		$item_prototypeids = [];

		foreach ($discovery_rules_by_hosts as $host => $discovery_rules) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			foreach ($discovery_rules as $discovery_rule) {
				$discoveryid = array_key_exists('uuid', $discovery_rule)
					? $this->referencer->findItemidByUuid($discovery_rule['uuid'])
					: $this->referencer->findItemidByKey($hostid, $discovery_rule['key_']);

				if ($discoveryid === null) {
					continue;
				}

				$discovery_ruleids[$discoveryid] = [];

				foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
					$host_prototypeid = array_key_exists('uuid', $host_prototype)
						? $this->referencer->findHostPrototypeidByUuid($host_prototype['uuid'])
						: $this->referencer->findHostPrototypeidByHost($hostid, $discoveryid, $host_prototype['host']);

					if ($host_prototypeid !== null) {
						$host_prototypeids[$host_prototypeid] = [];
					}
				}

				foreach ($discovery_rule['trigger_prototypes'] as $trigger_prototype) {
					$trigger_prototypeid = array_key_exists('uuid', $trigger_prototype)
						? $this->referencer->findTriggeridByUuid($trigger_prototype['uuid'])
						: $this->referencer->findTriggeridByName($trigger_prototype['description'],
							$trigger_prototype['expression'], $trigger_prototype['recovery_expression']
						);

					if ($trigger_prototypeid !== null) {
						$trigger_prototypeids[$trigger_prototypeid] = [];
					}
				}

				foreach ($discovery_rule['graph_prototypes'] as $graph_prototype) {
					$graph_prototypeid = array_key_exists('uuid', $graph_prototype)
						? $this->referencer->findGraphidByUuid($graph_prototype['uuid'])
						: $this->referencer->findGraphidByName($hostid, $graph_prototype['name']);

					if ($graph_prototypeid !== null) {
						$graph_prototypeids[$graph_prototypeid] = [];
					}
				}

				foreach ($discovery_rule['item_prototypes'] as $item_prototype) {
					$item_prototypeid = array_key_exists('uuid', $item_prototype)
						? $this->referencer->findItemidByUuid($item_prototype['uuid'])
						: $this->referencer->findItemidByKey($hostid, $item_prototype['key_']);

					if ($item_prototypeid !== null) {
						$item_prototypeids[$item_prototypeid] = [];
					}
				}
			}
		}

		$db_host_prototypes = API::HostPrototype()->get([
			'output' => [],
			'discoveryids' => array_keys($discovery_ruleids),
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$host_prototypes_to_delete = array_diff_key($db_host_prototypes, $host_prototypeids);

		if ($host_prototypes_to_delete) {
			API::HostPrototype()->delete(array_keys($host_prototypes_to_delete));
		}

		$db_trigger_prototypes = API::TriggerPrototype()->get([
			'output' => [],
			'discoveryids' => array_keys($discovery_ruleids),
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$trigger_prototypes_to_delete = array_diff_key($db_trigger_prototypes, $trigger_prototypeids);

		// Unlike triggers that belong to multiple hosts, trigger prototypes do not, so we just delete them.
		if ($trigger_prototypes_to_delete) {
			API::TriggerPrototype()->delete(array_keys($trigger_prototypes_to_delete));

			$this->referencer->refreshTriggers();
		}

		$db_graph_prototypes = API::GraphPrototype()->get([
			'output' => [],
			'discoveryids' => array_keys($discovery_ruleids),
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$graph_prototypes_to_delete = array_diff_key($db_graph_prototypes, $graph_prototypeids);

		// Unlike graphs that belong to multiple hosts, graph prototypes do not, so we just delete them.
		if ($graph_prototypes_to_delete) {
			API::GraphPrototype()->delete(array_keys($graph_prototypes_to_delete));

			$this->referencer->refreshGraphs();
		}

		$db_item_prototypes = API::ItemPrototype()->get([
			'output' => [],
			'discoveryids' => array_keys($discovery_ruleids),
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$item_prototypes_to_delete = array_diff_key($db_item_prototypes, $item_prototypeids);

		if ($item_prototypes_to_delete) {
			API::ItemPrototype()->delete(array_keys($item_prototypes_to_delete));

			$this->referencer->refreshItems();
		}
	}

	/**
	 * Deletes web scenarios from DB that are missing in import file.
	 */
	protected function deleteMissingHttpTests(): void {
		if (!$this->options['httptests']['deleteMissing']) {
			return;
		}

		$processed_hostids = array_merge(
			$this->importedObjectContainer->getHostids(),
			$this->importedObjectContainer->getTemplateids()
		);

		if (!$processed_hostids) {
			return;
		}

		$httptestids = [];

		foreach ($this->getFormattedHttpTests() as $host => $httptests) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			if ($hostid === null) {
				continue;
			}

			foreach ($httptests as $httptest) {
				$httptestid = array_key_exists('uuid', $httptest)
					? $this->referencer->findHttpTestidByUuid($httptest['uuid'])
					: $this->referencer->findHttpTestidByName($hostid, $httptest['name']);

				if ($httptestid !== null) {
					$httptestids[$httptestid] = [];
				}
			}
		}

		$db_httptestids = API::HttpTest()->get([
			'output' => [],
			'hostids' => $processed_hostids,
			'inherited' => false,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$httptestids_to_delete = array_diff_key($db_httptestids, $httptestids);

		if ($httptestids_to_delete) {
			API::HttpTest()->delete(array_keys($httptestids_to_delete));
		}

		$this->referencer->refreshHttpTests();
	}

	/**
	 * Deletes template dashboards from DB that are missing in import file.
	 *
	 * @throws APIException
	 */
	protected function deleteMissingTemplateDashboards(): void {
		if (!$this->options['templateDashboards']['deleteMissing']) {
			return;
		}

		$dashboards = $this->getFormattedTemplateDashboards();

		if ($dashboards) {
			$dashboard_importer = new CTemplateDashboardImporter($this->options, $this->referencer,
				$this->importedObjectContainer
			);

			$dashboard_importer->delete($dashboards);
		}
	}

	/**
	 * Deletes discovery rules from DB that are missing in import file.
	 */
	protected function deleteMissingDiscoveryRules(): void {
		if (!$this->options['discoveryRules']['deleteMissing']) {
			return;
		}

		$processed_hostids = array_merge(
			$this->importedObjectContainer->getHostids(),
			$this->importedObjectContainer->getTemplateids()
		);

		if (!$processed_hostids) {
			return;
		}

		$discovery_ruleids = [];

		foreach ($this->getFormattedDiscoveryRules() as $host => $discovery_rules) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host);

			if ($hostid === null) {
				continue;
			}

			foreach ($discovery_rules as $discovery_rule) {
				$discovery_ruleid = array_key_exists('uuid', $discovery_rule)
					? $this->referencer->findItemidByUuid($discovery_rule['uuid'])
					: $this->referencer->findItemidByKey($hostid, $discovery_rule['key_']);

				if ($discovery_ruleid !== null) {
					$discovery_ruleids[$discovery_ruleid] = [];
				}
			}
		}

		$db_discovery_ruleids = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $processed_hostids,
			'inherited' => false,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$discovery_ruleids_to_delete = array_diff_key($db_discovery_ruleids, $discovery_ruleids);

		if ($discovery_ruleids_to_delete) {
			API::DiscoveryRule()->delete(array_keys($discovery_ruleids_to_delete));
		}

		$this->referencer->refreshItems();
	}

	/**
	 * Get formatted groups.
	 *
	 * @return array
	 */
	protected function getFormattedGroups(): array {
		if (!array_key_exists('groups', $this->formattedData)) {
			$this->formattedData['groups'] = $this->adapter->getGroups();
		}

		return $this->formattedData['groups'];
	}

	/**
	 * Get formatted templates.
	 *
	 * @return array
	 */
	public function getFormattedTemplates(): array {
		if (!array_key_exists('templates', $this->formattedData)) {
			$this->formattedData['templates'] = $this->adapter->getTemplates();
		}

		return $this->formattedData['templates'];
	}

	/**
	 * Get formatted hosts.
	 *
	 * @return array
	 */
	public function getFormattedHosts(): array {
		if (!array_key_exists('hosts', $this->formattedData)) {
			$this->formattedData['hosts'] = $this->adapter->getHosts();
		}

		return $this->formattedData['hosts'];
	}

	/**
	 * Get formatted items.
	 *
	 * @return array
	 */
	protected function getFormattedItems(): array {
		if (!array_key_exists('items', $this->formattedData)) {
			$this->formattedData['items'] = $this->adapter->getItems();
		}

		return $this->formattedData['items'];
	}

	/**
	 * Get formatted discovery rules.
	 *
	 * @return array
	 */
	protected function getFormattedDiscoveryRules(): array {
		if (!array_key_exists('discoveryRules', $this->formattedData)) {
			$this->formattedData['discoveryRules'] = $this->adapter->getDiscoveryRules();
		}

		return $this->formattedData['discoveryRules'];
	}

	/**
	 * Get formatted web scenarios.
	 *
	 * @return array
	 */
	protected function getFormattedHttpTests(): array {
		if (!array_key_exists('httptests', $this->formattedData)) {
			$this->formattedData['httptests'] = $this->adapter->getHttpTests();
		}

		return $this->formattedData['httptests'];
	}

	/**
	 * Get formatted web scenario steps.
	 *
	 * @return array
	 */
	protected function getFormattedHttpSteps(): array {
		if (!array_key_exists('httpsteps', $this->formattedData)) {
			$this->formattedData['httpsteps'] = $this->adapter->getHttpSteps();
		}

		return $this->formattedData['httpsteps'];
	}

	/**
	 * Get formatted triggers.
	 *
	 * @return array
	 */
	protected function getFormattedTriggers(): array {
		if (!array_key_exists('triggers', $this->formattedData)) {
			$this->formattedData['triggers'] = $this->adapter->getTriggers();
		}

		return $this->formattedData['triggers'];
	}

	/**
	 * Get formatted graphs.
	 *
	 * @return array
	 */
	protected function getFormattedGraphs(): array {
		if (!array_key_exists('graphs', $this->formattedData)) {
			$this->formattedData['graphs'] = $this->adapter->getGraphs();
		}

		return $this->formattedData['graphs'];
	}

	/**
	 * Get formatted images.
	 *
	 * @return array
	 */
	protected function getFormattedImages(): array {
		if (!array_key_exists('images', $this->formattedData)) {
			$this->formattedData['images'] = $this->adapter->getImages();
		}

		return $this->formattedData['images'];
	}

	/**
	 * Get formatted maps.
	 *
	 * @return array
	 */
	protected function getFormattedMaps(): array {
		if (!array_key_exists('maps', $this->formattedData)) {
			$this->formattedData['maps'] = $this->adapter->getMaps();
		}

		return $this->formattedData['maps'];
	}

	/**
	 * Get formatted template dashboards.
	 *
	 * @return array
	 */
	protected function getFormattedTemplateDashboards(): array {
		if (!array_key_exists('templateDashboards', $this->formattedData)) {
				$this->formattedData['templateDashboards'] = $this->adapter->getTemplateDashboards();
		}

		return $this->formattedData['templateDashboards'];
	}

	/**
	 * Get formatted media types.
	 *
	 * @return array
	 */
	protected function getFormattedMediaTypes(): array {
		if (!array_key_exists('mediaTypes', $this->formattedData)) {
			$this->formattedData['mediaTypes'] = $this->adapter->getMediaTypes();
		}

		return $this->formattedData['mediaTypes'];
	}

	/**
	 * Get items keys order tree, to ensure that master item will be inserted or updated before any of it dependent
	 * item. Returns associative array where key is item index and value is item dependency level.
	 *
	 * @param string $master_item_key  String containing master key name used to identify item master.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function getItemsOrder(string $master_item_key): array {
		$entities = $this->getFormattedItems();

		return $this->getEntitiesOrder($entities, $master_item_key);
	}

	/**
	 * Get discovery rules items prototypes keys order tree, to ensure that master item will be inserted or updated
	 * before any of it dependent item. Returns associative array where key is item prototype index and value is item
	 * prototype dependency level.
	 *
	 * @param string $master_item_key  String containing master key name used to identify item master.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function getDiscoveryRulesItemsOrder(string $master_item_key): array {
		$discovery_rules_by_hosts = $this->getFormattedDiscoveryRules();
		$entities_order = [];

		foreach ($discovery_rules_by_hosts as $host => $discovery_rules) {
			foreach ($discovery_rules as $discovery_rule) {
				if ($discovery_rule['item_prototypes']) {
					$item_prototypes = [$host => $discovery_rule['item_prototypes']];
					$item_prototypes = $this->getEntitiesOrder($item_prototypes, $master_item_key, true);
					$entities_order[$host][$discovery_rule['key_']] = $item_prototypes[$host];
				}
			}
		}

		return $entities_order;
	}

	/**
	 * Generic method to get entities order tree, to ensure that master entity will be inserted or updated before any
	 * of it dependent entities.
	 * Returns associative array where key is entity index in source array grouped by host key and value is entity
	 * dependency level.
	 *
	 * @param array  $items_by_hosts   Associative array of host key and host items.
	 * @param string $master_item_key  String containing master key name to identify item master.
	 * @param bool   $get_prototypes   Option to get also master item prototypes not found in supplied input.
	 *
	 * @return array
	 *
	 * @throws Exception if data is invalid.
	 */
	protected function getEntitiesOrder(array $items_by_hosts, string $master_item_key,
			bool $get_prototypes = false): array {
		$parent_item_hostids = [];
		$parent_item_keys = [];
		$resolved_masters_cache = [];

		$host_name_to_hostid = array_fill_keys(array_keys($items_by_hosts), null);

		foreach ($host_name_to_hostid as $host_name => &$hostid) {
			$hostid = $this->referencer->findTemplateidOrHostidByHost($host_name);
		}
		unset($hostid);

		foreach ($items_by_hosts as $host_name => $items) {
			if (!array_key_exists($host_name, $host_name_to_hostid)) {
				throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'host',
					_s('value "%1$s" not found', $host_name)
				));
			}

			if (!array_key_exists($host_name, $resolved_masters_cache)) {
				$resolved_masters_cache[$host_name] = [];
			}

			// Cache input array entities.
			foreach ($items as $item) {
				$resolved_masters_cache[$host_name][$item['key_']] = [
					'type' => $item['type'],
					$master_item_key => $item[$master_item_key]
				];

				if ($item['type'] == ITEM_TYPE_DEPENDENT && array_key_exists('key', $item[$master_item_key])) {
					$parent_item_hostids[$host_name_to_hostid[$host_name]] = true;
					$parent_item_keys[$item[$master_item_key]['key']] = true;
				}
			}
		}

		// There are entities to resolve from database, resolve and cache them recursively.
		if ($parent_item_keys) {
			/*
			 * For existing items, 'referencer' should be initialized before 'setDbItem' method will be used.
			 * Registering reference when property 'db_items' is empty, will not allow first call of
			 * 'findValueMapidByName' method update references to existing items.
			 */
			$this->referencer->initItemsReferences();

			$options = [
				'output' => ['itemid', 'uuid', 'key_', 'type', 'hostid', 'master_itemid'],
				'hostids' => array_keys($parent_item_hostids),
				'filter' => ['key_' => array_keys($parent_item_keys)],
				'preservekeys' => true
			];

			$db_items = API::Item()->get($options + ['webitems' => true]);

			if ($get_prototypes) {
				$db_items += API::ItemPrototype()->get($options);
			}

			$resolve_entity_keys = [];
			$itemid_to_item_key_by_hosts = [];

			for ($level = 0; $level < ZBX_DEPENDENT_ITEM_MAX_LEVELS; $level++) {
				$missing_master_itemids = [];

				foreach ($db_items as $itemid => $item) {
					$host_name = array_search($item['hostid'], $host_name_to_hostid);

					$this->referencer->setDbItem($itemid, $item);

					$item['key'] = $item['key_'];
					unset($item['key_']);

					$itemid_to_item_key_by_hosts[$host_name][$itemid] = $item['key'];

					$cache_entity = [
						'type' => $item['type']
					];

					if ($item['type'] == ITEM_TYPE_DEPENDENT) {
						$master_itemid = $item['master_itemid'];

						if (array_key_exists($master_itemid, $itemid_to_item_key_by_hosts[$host_name])) {
							$cache_entity[$master_item_key] = [
								'key' => $itemid_to_item_key_by_hosts[$host_name][$master_itemid]
							];
						}
						else {
							$missing_master_itemids[] = $item['master_itemid'];
							$resolve_entity_keys[] = [
								'host' => $host_name,
								'key' => $item['key'],
								'master_itemid' => $item['master_itemid']
							];
						}
					}

					$resolved_masters_cache[$host_name][$item['key']] = $cache_entity;
				}

				if ($missing_master_itemids) {
					$options = [
						'output' => ['uuid', 'key_', 'type', 'hostid', 'master_itemid'],
						'itemids' => $missing_master_itemids,
						'preservekeys' => true
					];
					$db_items = API::Item()->get($options + ['webitems' => true]);

					if ($get_prototypes) {
						$db_items += API::ItemPrototype()->get($options);
					}
				}
				else {
					break;
				}
			}

			if ($missing_master_itemids) {
				throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
					_('maximum number of dependency levels reached')
				));
			}

			foreach ($resolve_entity_keys as $item) {
				$master_itemid = $item['master_itemid'];

				if (!array_key_exists($item['host'], $itemid_to_item_key_by_hosts) ||
						!array_key_exists($master_itemid, $itemid_to_item_key_by_hosts[$item['host']])) {
					throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
						_s('value "%1$s" not found', $master_itemid)
					));
				}

				$master_key = $itemid_to_item_key_by_hosts[$item['host']][$master_itemid];
				$resolved_masters_cache[$item['host']][$item['key']] += [
					$master_item_key => ['key' => $master_key]
				];
			}

			unset($resolve_entity_keys, $itemid_to_item_key_by_hosts);
		}

		// Resolve every entity dependency level.
		$tree = [];

		foreach ($items_by_hosts as $host_name => $items) {
			$hostid = $host_name_to_hostid[$host_name];
			$host_items_tree = [];

			foreach ($items as $index => $item) {
				$level = 0;
				$traversal_path = [$item['key_']];

				while ($item && $item['type'] == ITEM_TYPE_DEPENDENT) {
					if (!array_key_exists('key', $item[$master_item_key])) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_('cannot be empty')
						));
					}

					$master_key = $item[$master_item_key]['key'];

					if (array_key_exists($host_name, $resolved_masters_cache)
							&& array_key_exists($master_key, $resolved_masters_cache[$host_name])) {
						$item = $resolved_masters_cache[$host_name][$master_key];

						if (($item['type'] == ITEM_TYPE_DEPENDENT
									&& $item[$master_item_key]
									&& $master_key === $item[$master_item_key]['key'])
								|| in_array($master_key, $traversal_path)) {
							throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
								_('circular item dependency is not allowed')
							));
						}

						$traversal_path[] = $master_key;
						$level++;
					}
					else {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_s('value "%1$s" not found', $master_key)
						));
					}

					if ($level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_('maximum number of dependency levels reached')
						));
					}
				}

				$host_items_tree[$index] = $level;
			}

			$tree[$host_name] = $host_items_tree;
		}

		// Order item indexes in descending order by nesting level.
		foreach ($tree as &$item_indexes) {
			asort($item_indexes);
		}
		unset($item_indexes);

		return $tree;
	}
}
