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
 * Converter for converting import data from 1.8 to 2.0.
 */
class C10ImportConverter extends CConverter {

	/**
	 * Converter used for converting simple check item keys.
	 *
	 * @var CConverter
	 */
	protected $itemKeyConverter;

	/**
	 * Converter used for converting trigger expressions.
	 *
	 * @var CConverter
	 */
	protected $triggerConverter;

	public function __construct() {
		$this->itemKeyConverter = new C10ItemKeyConverter();
		$this->triggerConverter = new C10TriggerConverter();
	}

	public function convert($value) {
		$content = $value['zabbix_export'];

		$content['version'] = '2.0';
		$content = $this->convertTime($content);

		$content = $this->convertDependencies($content);
		$content = $this->separateTemplatesFromHosts($content);
		$content = $this->convertHosts($content);
		$content = $this->convertTemplates($content);

		$content = $this->convertSysmaps($content);

		$content = $this->filterDuplicateGroups($content);
		$content = $this->filterDuplicateTriggers($content);
		$content = $this->filterDuplicateGraphs($content);

		$value['zabbix_export'] = $content;

		return $value;
	}

	/**
	 * Convert the date and time elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertTime(array $content) {
		if (array_key_exists('date', $content) && array_key_exists('time', $content)) {
			list($day, $month, $year) = explode('.', $content['date']);
			list($hours, $minutes) = explode('.', $content['time']);
			$content['date'] = date(DATE_TIME_FORMAT_SECONDS_XML, mktime($hours, $minutes, 0, $month, $day, $year));
		}

		unset($content['time']);

		return $content;
	}

	/**
	 * Separate templates and their elements from other hosts into the "templates" array.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function separateTemplatesFromHosts(array $content) {
		if (!isset($content['hosts']) || !$content['hosts']) {
			return $content;
		}

		$templates = [];
		foreach ($content['hosts'] as $i => $host) {
			// skip hosts
			if (isset($host['status']) && $host['status'] != HOST_STATUS_TEMPLATE) {
				continue;
			}

			$template = [];
			foreach (['name', 'groups', 'items', 'templates', 'triggers', 'graphs', 'macros'] as $key) {
				if (isset($host[$key])) {
					$template[$key] = $host[$key];
				}
			}

			$templates[] = $template;

			unset($content['hosts'][$i]);
		}

		if ($templates) {
			$content['templates'] = $templates;

			// reset host keys
			$content['hosts'] = array_values($content['hosts']);
		}

		return $content;
	}

	/**
	 * Convert host elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertHosts(array $content) {
		if (!isset($content['hosts']) || !$content['hosts']) {
			return $content;
		}

		foreach ($content['hosts'] as &$host) {
			$host['host'] = $host['name'];
			$host = $this->convertHostInterfaces($host);
			$host = $this->convertHostProfiles($host);
			$host = $this->convertHostApplications($host);
			$host = $this->convertHostItems($host);
			$host = $this->convertHostTriggers($host, $host['host']);
			$host = $this->convertHostGraphs($host, $host['host']);
			$host = $this->convertHostMacros($host);
			$host = $this->convertProxy($host);

			$host = $this->wrapChildren($host, 'templates', 'name');
			$host = $this->wrapChildren($host, 'groups', 'name');

			unset($host['useipmi']);
			unset($host['useip']);
			unset($host['ip']);
			unset($host['dns']);
			unset($host['port']);
			unset($host['ipmi_ip']);
			unset($host['ipmi_port']);
			unset($host['host_profile']);
			unset($host['host_profiles_ext']);
		}
		unset($host);

		$content = $this->mergeTo($content['hosts'], $content, 'groups');
		$content = $this->mergeTo($content['hosts'], $content, 'triggers');
		$content = $this->mergeTo($content['hosts'], $content, 'graphs');
		foreach ($content['hosts'] as &$host) {
			unset($host['triggers']);
			unset($host['graphs']);
		}
		unset($host);

		return $content;
	}

	/**
	 * Convert template elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertTemplates(array $content) {
		if (!isset($content['templates'])) {
			return $content;
		}

		foreach ($content['templates'] as &$template) {
			$template['template'] = $template['name'];
			$template = $this->convertHostApplications($template);
			$template = $this->convertHostItems($template);
			$template = $this->convertHostTriggers($template, $template['template']);
			$template = $this->convertHostGraphs($template, $template['template']);
			$template = $this->convertHostMacros($template);

			$template = $this->wrapChildren($template, 'templates', 'name');
			$template = $this->wrapChildren($template, 'groups', 'name');
		}
		unset($template);

		$content = $this->mergeTo($content['templates'], $content, 'groups');
		$content = $this->mergeTo($content['templates'], $content, 'triggers');
		$content = $this->mergeTo($content['templates'], $content, 'graphs');
		foreach ($content['templates'] as &$host) {
			unset($host['triggers']);
			unset($host['graphs']);
		}
		unset($host);

		return $content;
	}

	/**
	 * Create host interfaces from the host properties and items and add them to the "host" element.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertHostInterfaces(array $host) {
		$interfaces = [];
		$i = 0;

		// create an agent interface from the host properties
		if (isset($host['useip']) && isset($host['ip']) && isset($host['dns']) && isset($host['port'])) {
			$agentInterface = [
				'type' => (string) INTERFACE_TYPE_AGENT,
				'useip' => $host['useip'],
				'ip' => $host['ip'],
				'dns' => $host['dns'],
				'port' => $host['port'],
				'default' => (string) INTERFACE_PRIMARY,
				'interface_ref' => 'if'.$i
			];
			$interfaces[] = $agentInterface;
			$i++;
		}

		$hasIpmiItem = false;
		$snmpItems = [];

		if (isset($host['items']) && $host['items']) {
			foreach ($host['items'] as $item) {
				if (!isset($item['type'])) {
					continue;
				}

				if ($item['type'] == ITEM_TYPE_IPMI) {
					$hasIpmiItem = true;
				}
				if ($item['type'] == ITEM_TYPE_SNMPV1 || $item['type'] == ITEM_TYPE_SNMPV2C
						|| $item['type'] == ITEM_TYPE_SNMPV3) {
					$snmpItems[] = $item;
				}
			}

			// If at least one IPMI item exists on a host, create an IPMI interface.
			if ($hasIpmiItem) {
				if (array_key_exists('ipmi_ip', $host) && $host['ipmi_ip'] !== '') {
					$ip_parser = new CIPParser(['v6' => ZBX_HAVE_IPV6]);

					if ($ip_parser->parse($host['ipmi_ip']) == CParser::PARSE_SUCCESS) {
						$ip = $host['ipmi_ip'];
						$dns = '';
						$useip = (string) INTERFACE_USE_IP;
					}
					else {
						$ip = '';
						$dns = $host['ipmi_ip'];
						$useip = (string) INTERFACE_USE_DNS;
					}
				}
				else {
					$ip = $host['ip'];
					$dns = '';
					$useip = (string) INTERFACE_USE_IP;
				}

				$ipmiInterface = [
					'type' => (string) INTERFACE_TYPE_IPMI,
					'useip' => $useip,
					'ip' => $ip,
					'dns' => $dns,
					'port' => (array_key_exists('ipmi_port', $host) && $host['ipmi_port'] !== '')
						? $host['ipmi_port'] : '623',
					'default' => (string) INTERFACE_PRIMARY,
					'interface_ref' => 'if'.$i
				];
				$interfaces[] = $ipmiInterface;
				$i++;
			}

			// If SNMP item exist, create an SNMP interface for each SNMP item port.
			if ($snmpItems) {
				$snmpInterfaces = [];

				foreach ($snmpItems as $item) {
					if (!isset($item['snmp_port']) || isset($snmpInterfaces[$item['snmp_port']])) {
						continue;
					}

					$snmpInterface = [
						'type' => (string) INTERFACE_TYPE_SNMP,
						'useip' => $host['useip'],
						'ip' => $host['ip'],
						'dns' => $host['dns'],
						'port' => $item['snmp_port'],
						'default' => (count($snmpInterfaces)) ? (string) INTERFACE_SECONDARY : (string) INTERFACE_PRIMARY,
						'interface_ref' => 'if'.$i
					];
					$snmpInterfaces[$item['snmp_port']] = $snmpInterface;
					$interfaces[] = $snmpInterface;
					$i++;
				}
			}
		}

		if ($interfaces) {
			$host['interfaces'] = $interfaces;
		}

		// map items to new interfaces
		if (isset($host['items']) && $host['items']) {
			foreach ($host['items'] as &$item) {
				if (!array_key_exists('type', $item)) {
					continue;
				}

				// 1.8 till 4.4 uses the old item types.
				if (in_array($item['type'], [ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C, ITEM_TYPE_SNMPV3])) {
					$interface_type = INTERFACE_TYPE_SNMP;
				}
				else {
					$interface_type = itemTypeInterface($item['type']);
				}

				switch ($interface_type) {
					case INTERFACE_TYPE_AGENT:
					case INTERFACE_TYPE_OPT:
						$item['interface_ref'] = $agentInterface['interface_ref'];
						break;

					case INTERFACE_TYPE_IPMI:
						$item['interface_ref'] = $ipmiInterface['interface_ref'];
						break;

					case INTERFACE_TYPE_SNMP:
						if (isset($item['snmp_port'])) {
							$item['interface_ref'] = $snmpInterfaces[$item['snmp_port']]['interface_ref'];
						}
						break;
				}
			}
			unset($item);
		}

		return $host;
	}

	/**
	 * Convert host "host_profile" and "host_profiles_ext" elements and calculate "inventory_mode".
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertHostProfiles(array $host) {
		$host['inventory'] = ['inventory_mode' => (string) HOST_INVENTORY_DISABLED];

		// rename and merge profile fields
		if (array_key_exists('host_profile', $host)) {
			foreach ($host['host_profile'] as $key => $value) {
				$host['inventory'][$this->getNewProfileName($key)] = $value;
			}
			$host['inventory']['inventory_mode'] = (string) HOST_INVENTORY_MANUAL;
		}

		if (array_key_exists('host_profiles_ext', $host)) {
			foreach ($host['host_profiles_ext'] as $key => $value) {
				$key = $this->getNewProfileName($key);

				// if renaming results in a duplicate inventory field, concatenate them
				// this is the case with "notes" and "device_notes"
				if (array_key_exists($key, $host['inventory']) && $host['inventory'][$key] !== '') {
					$host['inventory'][$key] .= "\r\n\r\n".$value;
				}
				else {
					$host['inventory'][$key] = $value;
				}
			}
			$host['inventory']['inventory_mode'] = (string) HOST_INVENTORY_MANUAL;
		}

		return $host;
	}

	/**
	 * Map an old profile key name to the new inventory key name.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	protected function getNewProfileName($key) {
		$map = [
			'devicetype' => 'type',
			'serialno' => 'serialno_a',
			'macaddress' => 'macaddress_a',
			'hardware' => 'hardware_full',
			'software' => 'software_full',
			'device_type' => 'type_full',
			'device_alias' => 'alias',
			'device_os' => 'os_full',
			'device_os_short' => 'os_short',
			'device_serial' => 'serialno_b',
			'device_tag' => 'asset_tag',
			'ip_macaddress' => 'macaddress_b',
			'device_hardware' => 'hardware',
			'device_software' => 'software',
			'device_app_01' => 'software_app_a',
			'device_app_02' => 'software_app_b',
			'device_app_03' => 'software_app_c',
			'device_app_04' => 'software_app_d',
			'device_app_05' => 'software_app_e',
			'device_chassis' => 'chassis',
			'device_model' => 'model',
			'device_hw_arch' => 'hw_arch',
			'device_vendor' => 'vendor',
			'device_contract' => 'contract_number',
			'device_who' => 'installer_name',
			'device_status' => 'deployment_status',
			'device_url_1' => 'url_a',
			'device_url_2' => 'url_b',
			'device_url_3' => 'url_c',
			'device_networks' => 'host_networks',
			'ip_subnet_mask' => 'host_netmask',
			'ip_router' => 'host_router',
			'oob_subnet_mask' => 'oob_netmask',
			'date_hw_buy' => 'date_hw_purchase',
			'site_street_1' => 'site_address_a',
			'site_street_2' => 'site_address_b',
			'site_street_3' => 'site_address_c',
			'poc_1_phone_1' => 'poc_1_phone_a',
			'poc_1_phone_2' => 'poc_1_phone_b',
			'poc_2_phone_1' => 'poc_2_phone_a',
			'poc_2_phone_2' => 'poc_2_phone_b',
			'device_notes' => 'notes'
		];

		return array_key_exists($key, $map) ? $map[$key] : $key;
	}

	/**
	 * Filters duplicate host groups from the content.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function filterDuplicateGroups(array $content) {
		if (!isset($content['groups'])) {
			return $content;
		}

		$groups = [];

		foreach ($content['groups'] as $group) {
			$groups[$group['name']] = $group;
		}

		$content['groups'] = array_values($groups);

		return $content;
	}

	/**
	 * Converts triggers elements.
	 *
	 * @param array 	$host
	 * @param string 	$hostName 	technical name of the host that the triggers were imported under
	 *
	 * @return array
	 */
	protected function convertHostTriggers(array $host, $hostName) {
		if (!isset($host['triggers']) || !$host['triggers']) {
			return $host;
		}

		foreach ($host['triggers'] as &$trigger) {
			$trigger = CArrayHelper::renameKeys($trigger, ['description' => 'name', 'comments' => 'description']);
			$trigger = $this->convertTriggerExpression($trigger, $hostName);
		}
		unset($trigger);

		return $host;
	}

	/**
	 * Allocate the dependencies from the root element to the trigger elements and convert them to a new format.
	 *
	 * Dependencies, that cannot be resolved are skipped.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertDependencies(array $content) {
		// we cannot import dependencies if hosts are missing
		if (!isset($content['dependencies']) || !$content['dependencies'] || !isset($content['hosts'])) {
			unset($content['dependencies']);

			return $content;
		}

		// build a description-expression trigger index with references to the triggers in the content
		$descriptionExpressionIndex = [];
		foreach ($content['hosts'] as $hostKey => $host) {
			if (!isset($host['triggers']) || !$host['triggers']) {
				continue;
			}

			foreach ($host['triggers'] as $triggerKey => $trigger) {
				$descriptionExpressionIndex[$trigger['description']][$trigger['expression']][] =
					&$content['hosts'][$hostKey]['triggers'][$triggerKey];
			}
		}

		$hosts = zbx_toHash($content['hosts'], 'name');

		foreach ($content['dependencies'] as $dependency) {
			list($sourceHost, $sourceDescription) = explode(':', $dependency['description'], 2);
			unset($dependency['description']);

			// if one of the hosts is missing from the data or doesn't have any triggers, skip this dependency
			if (!isset($hosts[$sourceHost]) || !isset($hosts[$sourceHost]['triggers'])) {
				continue;
			}

			foreach ($dependency as $depends) {
				list($targetHost, $targetDescription) = explode(':', $depends, 2);

				// if one of the hosts is missing from the data or doesn't have any triggers, skip this dependency
				if (!isset($hosts[$targetHost]) || !isset($hosts[$targetHost]['triggers'])) {
					continue;
				}

				// find the target trigger
				// use the first trigger with the same description
				$targetTrigger = null;
				foreach ($hosts[$targetHost]['triggers'] as $trigger) {
					if ($trigger['description'] === $targetDescription) {
						$targetTrigger = $trigger;

						break;
					}
				}

				// if the target trigger wasn't found - skip this dependency
				if (!$targetTrigger) {
					continue;
				}

				// find the source trigger and add the dependencies to all of the copies of the trigger
				foreach ($hosts[$targetHost]['triggers'] as $trigger) {
					if ($trigger['description'] === $sourceDescription) {
						// if the source trigger is not present in the data - skip this dependency
						if (!isset($descriptionExpressionIndex[$trigger['description']])
								|| !isset($descriptionExpressionIndex[$trigger['description']][$trigger['expression']])) {

							continue 2;
						}

						// working with references to triggers in the content here
						foreach ($descriptionExpressionIndex[$trigger['description']][$trigger['expression']] as &$trigger) {
							$trigger['dependencies'][] = [
								'name' => $targetTrigger['description'],
								'expression' => $this->triggerConverter->convert($targetTrigger['expression'])
							];
						}
						unset($trigger);
					}
				}
			}
		}

		$content['hosts'] = array_values($hosts);
		unset($content['dependencies']);

		return $content;
	}

	/**
	 * Filters duplicate triggers from the array and returns the content with unique triggers.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function filterDuplicateTriggers(array $content) {
		if (!isset($content['triggers'])) {
			return $content;
		}

		$existingTriggers = [];

		$filteredTriggers = [];
		foreach ($content['triggers'] as $trigger) {
			$name = $trigger['name'];
			$expression = $trigger['expression'];

			if (isset($existingTriggers[$name]) && isset($existingTriggers[$name][$expression])) {
				continue;
			}

			$filteredTriggers[] = $trigger;
			$existingTriggers[$name][$expression] = true;
		}

		$content['triggers'] = $filteredTriggers;

		return $content;
	}

	/**
	 * Convert trigger expression and replace host macros.
	 *
	 * @param array 	$trigger
	 * @param string 	$hostName	technical name of the host that the trigger was imported under
	 *
	 * @return string
	 */
	protected function convertTriggerExpression(array $trigger, $hostName) {
		$trigger['expression'] = $this->triggerConverter->convert($trigger['expression']);

		// replace host macros with the host name
		// not sure why this is required, but this has been preserved from when refactoring CXmlImport18
		$trigger['expression'] = str_replace('{HOSTNAME}', $hostName, $trigger['expression']);
		$trigger['expression'] = str_replace('{HOST.HOST}', $hostName, $trigger['expression']);

		return $trigger;
	}

	/**
	 * Convert application from items.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertHostApplications(array $host) {
		if (!isset($host['items']) || !$host['items']) {
			return $host;
		}

		foreach ($host['items'] as $item) {
			if (isset($item['applications']) && $item['applications'] !== '') {
				foreach ($item['applications'] as $application) {
					$host['applications'][] = ['name' => $application];
				}
			}
		}

		return $host;
	}

	/**
	 * Convert item elements.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertHostItems(array $host) {
		if (!isset($host['items']) || !$host['items']) {
			return $host;
		}

		foreach ($host['items'] as &$item) {
			$item = CArrayHelper::renameKeys($item, ['trapper_hosts' => 'allowed_hosts', 'snmp_port' => 'port']);
			$item['name'] = $item['description'];

			// convert simple check keys
			$item['key'] = $this->itemKeyConverter->convert($item['key']);

			$item = $this->wrapChildren($item, 'applications', 'name');

			$item = $this->convertItemValueMap($item);

			// Add tag inventory_link.
			$item['inventory_link'] = '';
			$item['snmpv3_contextname'] = '';
			$item['snmpv3_authprotocol'] = '';
			$item['snmpv3_privprotocol'] = '';

			unset($item['lastlogsize']);
		}
		unset($item);

		return $host;
	}

	/**
	 * Convert graph elements.
	 *
	 * @param array 	$host
	 * @param string 	$hostName	technical name of the host that the graphs were imported under
	 *
	 * @return array
	 */
	protected function convertHostGraphs(array $host, $hostName) {
		if (!isset($host['graphs']) || !$host['graphs']) {
			return $host;
		}

		foreach ($host['graphs'] as &$graph) {
			$graph = CArrayHelper::renameKeys($graph, [
				'graphtype' => 'type',
				'graph_elements' => 'graph_items',
				'ymin_type' => 'ymin_type_1',
				'ymax_type' => 'ymax_type_1',
				'ymin_item_key' => 'ymin_item_1',
				'ymax_item_key' => 'ymax_item_1'
			]);
			$graph = $this->convertGraphItemReference($graph, 'ymin_item_1');
			$graph = $this->convertGraphItemReference($graph, 'ymax_item_1');

			foreach ($graph['graph_items'] as &$graphItem) {
				$graphItem = $this->convertGraphItemReference($graphItem, 'item', $hostName);

				unset($graphItem['periods_cnt']);
			}
			unset($graphItem);

			$graph['graph_items'] = array_values($graph['graph_items']);
		}
		unset($graph);

		return $host;
	}

	/**
	 * Convert item references used in graphs.
	 *
	 * @param array 		$array		source array
	 * @param string		$key		property under which the reference is stored
	 * @param string|null 	$host_name	if set to some host name, host macros will be resolved into this host
	 *
	 * @return array
	 */
	protected function convertGraphItemReference(array $array, $key, $host_name = null) {
		if ($array[$key] === '') {
			$array[$key] = [];
		}
		else {
			list ($host, $item_key) = explode(':', $array[$key], 2);

			// replace host macros with the host name
			// not sure why this is required, but this has been preserved from when refactoring CXmlImport18
			if ($host_name !== null && ($host === '{HOSTNAME}' || $host === '{HOST.HOST}')) {
				$host = $host_name;
			}

			$array[$key] = ['host' => $host, 'key' => $this->itemKeyConverter->convert($item_key)];
		}

		return $array;
	}

	/**
	 * Filters duplicate graphs from the array and returns the content with unique graphs.
	 *
	 * Graphs are assumed identical if their names and items are identical.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function filterDuplicateGraphs(array $content) {
		if (!isset($content['graphs'])) {
			return $content;
		}

		$existingGraphs = [];

		$filteredGraphs = [];
		foreach ($content['graphs'] as $graph) {
			$name = $graph['name'];
			$graphItems = $graph['graph_items'];

			if (isset($existingGraphs[$name])) {
				foreach ($existingGraphs[$name] as $existingGraphItems) {
					if ($graphItems == $existingGraphItems) {
						continue 2;
					}
				}
			}

			$filteredGraphs[] = $graph;

			$existingGraphs[$name][] = $graphItems;
		}

		$content['graphs'] = $filteredGraphs;

		return $content;
	}

	/**
	 * Converts host macro elements.
	 *
	 * @param array 	$host
	 *
	 * @return array
	 */
	protected function convertHostMacros(array $host) {
		if (!isset($host['macros']) || !$host['macros']) {
			return $host;
		}

		foreach ($host['macros'] as &$macro) {
			$macro = CArrayHelper::renameKeys($macro, ['name' => 'macro']);
		}
		unset($macro);

		return $host;
	}

	/**
	 * Convert map elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertSysmaps(array $content) {
		if (!isset($content['sysmaps']) || !$content['sysmaps']) {
			return $content;
		}

		$content = CArrayHelper::renameKeys($content, ['sysmaps' => 'maps']);
		foreach ($content['maps'] as &$map) {
			if (isset($map['selements']) && $map['selements']) {
				foreach ($map['selements'] as &$selement) {
					$selement = CArrayHelper::renameKeys($selement, [
						'elementid' => 'element',
						'iconid_off' => 'icon_off',
						'iconid_on' => 'icon_on',
						'iconid_disabled' => 'icon_disabled',
						'iconid_maintenance' => 'icon_maintenance'
					]);

					if (isset($selement['elementtype']) && $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
						$selement['element']['expression'] = $this->triggerConverter->convert(
							$selement['element']['expression']
						);

						unset($selement['element']['host']);
					}

					unset($selement['iconid_unknown']);
				}
				unset($selement);
			}

			if (isset($map['links']) && $map['links']) {
				foreach ($map['links'] as &$link) {
					if (isset($link['linktriggers']) && $link['linktriggers']) {
						foreach ($link['linktriggers'] as &$linkTrigger) {
							$linkTrigger = CArrayHelper::renameKeys($linkTrigger, ['triggerid' => 'trigger']);
							$linkTrigger['trigger']['expression'] = $this->triggerConverter->convert(
								$linkTrigger['trigger']['expression']
							);

							unset($linkTrigger['trigger']['host']);
						}
						unset($linkTrigger);
					}
				}
				unset($link);
			}

			if (!array_key_exists('backgroundid', $map)) {
				$map['backgroundid'] = '';
			}

			$map = CArrayHelper::renameKeys($map, ['backgroundid' => 'background']);
		}
		unset($map);

		return $content;
	}

	/**
	 * Merges all of the values from each element of $source stored in the $key property to the $key property of $target.
	 *
	 * @param array $source
	 * @param array $target
	 * @param string $key
	 *
	 * @return array    $target array with the new values
	 */
	protected function mergeTo(array $source, array $target, $key) {
		$values = (isset($target[$key])) ? $target[$key] : [];

		foreach ($source as $sourceItem) {
			if (!isset($sourceItem[$key]) || !$sourceItem[$key]) {
				continue;
			}

			foreach ($sourceItem[$key] as $value) {
				$values[] = $value;
			}

		}

		if ($values) {
			$target[$key] = $values;
		}

		return $target;
	}

	/**
	 * Adds a $wrapperKey property for each element of $key in $array and moves it's value to the new property.
	 *
	 * @param array $array
	 * @param string $key
	 * @param string $wrapperKey
	 *
	 * @return array
	 */
	protected function wrapChildren(array $array, $key, $wrapperKey) {
		if (array_key_exists($key, $array)) {
			foreach ($array[$key] as &$content) {
				$content = [$wrapperKey => $content];
			}
			unset($content);
		}

		return $array;
	}

	/**
	 * Convert old proxy format to new proxy format.
	 *
	 * @param array $host  Import data.
	 *
	 * @return array
	 */
	protected function convertProxy(array $host) {
		$host['proxy'] = [];

		// If ID is zero, no proxy was assigned to host. Thus it is not name.
		if ($host['proxy_hostid'] != 0) {
			$host['proxy']['name'] = $host['proxy_hostid'];
		}

		unset($host['proxy_hostid']);

		return $host;
	}

	/**
	 * Convert valuemap for items.
	 *
	 * @param array $item  Import data.
	 *
	 * @return array
	 */
	protected function convertItemValueMap(array $item) {
		$item['valuemap'] = [];
		if (array_key_exists('valuemapid', $item)) {
			// If ID is zero, no value maps were assigned to item. Thus it is not name.
			if ($item['valuemapid'] != 0) {
				$item['valuemap']['name'] = $item['valuemapid'];
			}

			unset($item['valuemapid']);
		}

		return $item;
	}
}
