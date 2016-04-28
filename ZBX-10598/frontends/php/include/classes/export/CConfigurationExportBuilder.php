<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CConfigurationExportBuilder {

	/**
	 * @var array
	 */
	protected $data = [];

	/**
	 * @param $version  current export version
	 */
	public function __construct() {
		$this->data['version'] = ZABBIX_EXPORT_VERSION;
		$this->data['date'] = date(DATE_TIME_FORMAT_SECONDS_XML, time() - date('Z'));
	}

	/**
	 * Get array with formatted export data.
	 *
	 * @return array
	 */
	public function getExport() {
		return ['zabbix_export' => $this->data];
	}

	/**
	 * Format groups.
	 *
	 * @param array $groups
	 */
	public function buildGroups(array $groups) {
		$this->data['groups'] = $this->formatGroups($groups);
	}

	/**
	 * Format templates.
	 *
	 * @param array $templates
	 */
	public function buildTemplates(array $templates) {
		$this->data['templates'] = [];

		foreach ($templates as $template) {
			$this->data['templates'][] = [
				'template' => $template['host'],
				'name' => $template['name'],
				'description' => $template['description'],
				'groups' => $this->formatGroups($template['groups']),
				'applications' => $this->formatApplications($template['applications']),
				'items' => $this->formatItems($template['items']),
				'discovery_rules' => $this->formatDiscoveryRules($template['discoveryRules']),
				'macros' => $this->formatMacros($template['macros']),
				'templates' => $this->formatTemplateLinkage($template['parentTemplates']),
				'screens' => $this->formatScreens($template['screens'])
			];
		}

		order_result($this->data['templates'], 'template');
	}

	/**
	 * Format hosts.
	 *
	 * @param $hosts
	 */
	public function buildHosts(array $hosts) {
		$this->data['hosts'] = [];

		foreach ($hosts as $host) {
			$host = $this->createInterfaceReferences($host);

			$this->data['hosts'][] = [
				'host' => $host['host'],
				'name' => $host['name'],
				'description' => $host['description'],
				'proxy' => $host['proxy'],
				'status' => $host['status'],
				'ipmi_authtype' => $host['ipmi_authtype'],
				'ipmi_privilege' => $host['ipmi_privilege'],
				'ipmi_username' => $host['ipmi_username'],
				'ipmi_password' => $host['ipmi_password'],
				'tls_connect' => $host['tls_connect'],
				'tls_accept' => $host['tls_accept'],
				'tls_issuer' => $host['tls_issuer'],
				'tls_subject' => $host['tls_subject'],
				'tls_psk_identity' => $host['tls_psk_identity'],
				'tls_psk' => $host['tls_psk'],
				'templates' => $this->formatTemplateLinkage($host['parentTemplates']),
				'groups' => $this->formatGroups($host['groups']),
				'interfaces' => $this->formatHostInterfaces($host['interfaces']),
				'applications' => $this->formatApplications($host['applications']),
				'items' => $this->formatItems($host['items']),
				'discovery_rules' => $this->formatDiscoveryRules($host['discoveryRules']),
				'macros' => $this->formatMacros($host['macros']),
				'inventory' => $this->formatHostInventory($host['inventory'])
			];
		}

		order_result($this->data['hosts'], 'host');
	}

	/**
	 * Format graphs.
	 *
	 * @param array $graphs
	 */
	public function buildGraphs(array $graphs) {
		$this->data['graphs'] = $this->formatGraphs($graphs);
	}

	/**
	 * Format triggers.
	 *
	 * @param array $triggers
	 */
	public function buildTriggers(array $triggers) {
		$this->data['triggers'] = $this->formatTriggers($triggers);
	}

	/**
	 * Format screens.
	 *
	 * @param array $screens
	 */
	public function buildScreens(array $screens) {
		$this->data['screens'] = $this->formatScreens($screens);
	}

	/**
	 * Format images.
	 *
	 * @param array $images
	 */
	public function buildImages(array $images) {
		$this->data['images'] = [];

		foreach ($images as $image) {
			$this->data['images'][] = [
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['encodedImage']
			];
		}
	}

	/**
	 * Format maps.
	 *
	 * @param array $maps
	 */
	public function buildMaps(array $maps) {
		$this->data['maps'] = [];

		foreach ($maps as $map) {
			$tmpSelements = $this->formatMapElements($map['selements']);
			$this->data['maps'][] = [
				'name' => $map['name'],
				'width' => $map['width'],
				'height' => $map['height'],
				'label_type' => $map['label_type'],
				'label_location' => $map['label_location'],
				'highlight' => $map['highlight'],
				'expandproblem' => $map['expandproblem'],
				'markelements' => $map['markelements'],
				'show_unack' => $map['show_unack'],
				'severity_min' => $map['severity_min'],
				'grid_size' => $map['grid_size'],
				'grid_show' => $map['grid_show'],
				'grid_align' => $map['grid_align'],
				'label_format' => $map['label_format'],
				'label_type_host' => $map['label_type_host'],
				'label_type_hostgroup' => $map['label_type_hostgroup'],
				'label_type_trigger' => $map['label_type_trigger'],
				'label_type_map' => $map['label_type_map'],
				'label_type_image' => $map['label_type_image'],
				'label_string_host' => $map['label_string_host'],
				'label_string_hostgroup' => $map['label_string_hostgroup'],
				'label_string_trigger' => $map['label_string_trigger'],
				'label_string_map' => $map['label_string_map'],
				'label_string_image' => $map['label_string_image'],
				'expand_macros' => $map['expand_macros'],
				'background' => $map['backgroundid'],
				'iconmap' => $map['iconmap'],
				'urls' => $this->formatMapUrls($map['urls']),
				'selements' => $tmpSelements,
				'links' => $this->formatMapLinks($map['links'], $tmpSelements)
			];
		}

		order_result($this->data['maps'], 'name');
	}

	/**
	 * Format mappings.
	 *
	 * @param array $mappings
	 *
	 * @return array
	 */
	protected function formatMappings(array $mappings) {
		$result = [];

		foreach ($mappings as $mapping) {
			$result[] = [
				'value' => $mapping['value'],
				'newvalue' => $mapping['newvalue']
			];
		}

		CArrayHelper::sort($result, ['value']);

		return $result;
	}

	/**
	 * Format value maps.
	 *
	 * @param array $valuemaps
	 */
	public function buildValueMaps(array $valuemaps) {
		$this->data['value_maps'] = [];

		foreach ($valuemaps as $valuemap) {
			$this->data['value_maps'][] = [
				'name' => $valuemap['name'],
				'mappings' => $this->formatMappings($valuemap['mappings'])
			];
		}

		CArrayHelper::sort($this->data['value_maps'], ['name']);
	}

	/**
	 * For each host interface an unique reference must be created and then added for all items, discovery rules
	 * and item prototypes that use the interface.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function createInterfaceReferences(array $host) {
		$references = [
			'num' => 1,
			'refs' => []
		];

		// create interface references
		foreach ($host['interfaces'] as &$interface) {
			$refNum = $references['num']++;
			$referenceKey = 'if'.$refNum;
			$interface['interface_ref'] = $referenceKey;
			$references['refs'][$interface['interfaceid']] = $referenceKey;
		}
		unset($interface);

		foreach ($host['items'] as &$item) {
			if ($item['interfaceid']) {
				$item['interface_ref'] = $references['refs'][$item['interfaceid']];
			}
		}
		unset($item);

		foreach ($host['discoveryRules'] as &$discoveryRule) {
			if ($discoveryRule['interfaceid']) {
				$discoveryRule['interface_ref'] = $references['refs'][$discoveryRule['interfaceid']];
			}

			foreach ($discoveryRule['itemPrototypes'] as &$prototype) {
				if ($prototype['interfaceid']) {
					$prototype['interface_ref'] = $references['refs'][$prototype['interfaceid']];
				}
			}
			unset($prototype);
		}
		unset($discoveryRule);

		return $host;
	}

	/**
	 * Format discovery rules.
	 *
	 * @param array $discoveryRules
	 *
	 * @return array
	 */
	protected function formatDiscoveryRules(array $discoveryRules) {
		$result = [];

		foreach ($discoveryRules as $discoveryRule) {
			$data = [
				'name' => $discoveryRule['name'],
				'type' => $discoveryRule['type'],
				'snmp_community' => $discoveryRule['snmp_community'],
				'snmp_oid' => $discoveryRule['snmp_oid'],
				'key' => $discoveryRule['key_'],
				'delay' => $discoveryRule['delay'],
				'status' => $discoveryRule['status'],
				'allowed_hosts' => $discoveryRule['trapper_hosts'],
				'snmpv3_contextname' => $discoveryRule['snmpv3_contextname'],
				'snmpv3_securityname' => $discoveryRule['snmpv3_securityname'],
				'snmpv3_securitylevel' => $discoveryRule['snmpv3_securitylevel'],
				'snmpv3_authprotocol' => $discoveryRule['snmpv3_authprotocol'],
				'snmpv3_authpassphrase' => $discoveryRule['snmpv3_authpassphrase'],
				'snmpv3_privprotocol' => $discoveryRule['snmpv3_privprotocol'],
				'snmpv3_privpassphrase' => $discoveryRule['snmpv3_privpassphrase'],
				'delay_flex' => $discoveryRule['delay_flex'],
				'params' => $discoveryRule['params'],
				'ipmi_sensor' => $discoveryRule['ipmi_sensor'],
				'authtype' => $discoveryRule['authtype'],
				'username' => $discoveryRule['username'],
				'password' => $discoveryRule['password'],
				'publickey' => $discoveryRule['publickey'],
				'privatekey' => $discoveryRule['privatekey'],
				'port' => $discoveryRule['port'],
				'filter' => $discoveryRule['filter'],
				'lifetime' => $discoveryRule['lifetime'],
				'description' => $discoveryRule['description'],
				'item_prototypes' => $this->formatItems($discoveryRule['itemPrototypes']),
				'trigger_prototypes' => $this->formatTriggers($discoveryRule['triggerPrototypes']),
				'graph_prototypes' => $this->formatGraphs($discoveryRule['graphPrototypes']),
				'host_prototypes' => $this->formatHostPrototypes($discoveryRule['hostPrototypes'])
			];

			if (isset($discoveryRule['interface_ref'])) {
				$data['interface_ref'] = $discoveryRule['interface_ref'];
			}

			$result[] = $data;
		}

		order_result($result, 'key');

		return $result;
	}

	/**
	 * Format host inventory.
	 *
	 * @param array $inventory
	 *
	 * @return array
	 */
	protected function formatHostInventory(array $inventory) {
		unset($inventory['hostid']);

		return $inventory;
	}

	/**
	 * Format graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function formatGraphs(array $graphs) {
		$result = [];

		foreach ($graphs as $graph) {
			$result[] = [
				'name' => $graph['name'],
				'width' => $graph['width'],
				'height' => $graph['height'],
				'yaxismin' => $graph['yaxismin'],
				'yaxismax' => $graph['yaxismax'],
				'show_work_period' => $graph['show_work_period'],
				'show_triggers' => $graph['show_triggers'],
				'type' => $graph['graphtype'],
				'show_legend' => $graph['show_legend'],
				'show_3d' => $graph['show_3d'],
				'percent_left' => $graph['percent_left'],
				'percent_right' => $graph['percent_right'],
				'ymin_type_1' => $graph['ymin_type'],
				'ymax_type_1' => $graph['ymax_type'],
				'ymin_item_1' => $graph['ymin_itemid'],
				'ymax_item_1' => $graph['ymax_itemid'],
				'graph_items' => $this->formatGraphItems($graph['gitems'])
			];
		}

		order_result($result, 'name');

		return $result;
	}

	/**
	 * Format host prototypes.
	 *
	 * @param array $hostPrototypes
	 *
	 * @return array
	 */
	protected function formatHostPrototypes(array $hostPrototypes) {
		$result = [];

		foreach ($hostPrototypes as $hostPrototype) {
			$result[] = [
				'host' => $hostPrototype['host'],
				'name' => $hostPrototype['name'],
				'status' => $hostPrototype['status'],
				'group_links' => $this->formatGroupLinks($hostPrototype['groupLinks']),
				'group_prototypes' => $this->formatGroupPrototypes($hostPrototype['groupPrototypes']),
				'templates' => $this->formatTemplateLinkage($hostPrototype['templates'])
			];
		}

		order_result($result, 'host');

		return $result;
	}

	/**
	 * Format group links.
	 *
	 * @param array $groupLinks
	 *
	 * @return array
	 */
	protected function formatGroupLinks(array $groupLinks) {
		$result = [];

		order_result($groupLinks, 'name');

		foreach ($groupLinks as $groupLink) {
			$result[] = [
				'group' => $groupLink['groupid'],
			];
		}

		return $result;
	}

	/**
	 * Format group prototypes.
	 *
	 * @param array $groupPrototypes
	 *
	 * @return array
	 */
	protected function formatGroupPrototypes(array $groupPrototypes) {
		$result = [];

		foreach ($groupPrototypes as $groupPrototype) {
			$result[] = [
				'name' => $groupPrototype['name']
			];
		}

		order_result($result, 'name');

		return $result;
	}

	/**
	 * Format template linkage.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function formatTemplateLinkage(array $templates) {
		$result = [];

		foreach ($templates as $template) {
			$result[] = [
				'name' => $template['host']
			];
		}

		order_result($result, 'name');

		return $result;
	}

	/**
	 * Format triggers.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	protected function formatTriggers(array $triggers) {
		$result = [];

		foreach ($triggers as $trigger) {
			$result[] = [
				'expression' => $trigger['expression'],
				'name' => $trigger['description'],
				'url' => $trigger['url'],
				'status' => $trigger['status'],
				'priority' => $trigger['priority'],
				'description' => $trigger['comments'],
				'type' => $trigger['type'],
				'dependencies' => $this->formatDependencies($trigger['dependencies'])
			];
		}

		CArrayHelper::sort($result, ['name', 'expression']);

		return $result;
	}

	/**
	 * Format host interfaces.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function formatHostInterfaces(array $interfaces) {
		$result = [];

		foreach ($interfaces as $interface) {
			$result[] = [
				'default' => $interface['main'],
				'type' => $interface['type'],
				'useip' => $interface['useip'],
				'ip' => $interface['ip'],
				'dns' => $interface['dns'],
				'port' => $interface['port'],
				'bulk' => $interface['bulk'],
				'interface_ref' => $interface['interface_ref']
			];
		}

		CArrayHelper::sort($result, ['type', 'ip', 'dns', 'port']);

		return $result;
	}

	/**
	 * Format groups.
	 *
	 * @param array $groups
	 *
	 * @return array
	 */
	protected function formatGroups(array $groups) {
		$result = [];

		foreach ($groups as $group) {
			$result[] = [
				'name' => $group['name']
			];
		}

		order_result($result, 'name');

		return $result;
	}

	/**
	 * Format items.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function formatItems(array $items) {
		$result = [];

		foreach ($items as $item) {
			$data = [
				'name' => $item['name'],
				'type' => $item['type'],
				'snmp_community' => $item['snmp_community'],
				'multiplier' => $item['multiplier'],
				'snmp_oid' => $item['snmp_oid'],
				'key' => $item['key_'],
				'delay' => $item['delay'],
				'history' => $item['history'],
				'trends' => $item['trends'],
				'status' => $item['status'],
				'value_type' => $item['value_type'],
				'allowed_hosts' => $item['trapper_hosts'],
				'units' => $item['units'],
				'delta' => $item['delta'],
				'snmpv3_contextname' => $item['snmpv3_contextname'],
				'snmpv3_securityname' => $item['snmpv3_securityname'],
				'snmpv3_securitylevel' => $item['snmpv3_securitylevel'],
				'snmpv3_authprotocol' => $item['snmpv3_authprotocol'],
				'snmpv3_authpassphrase' => $item['snmpv3_authpassphrase'],
				'snmpv3_privprotocol' => $item['snmpv3_privprotocol'],
				'snmpv3_privpassphrase' => $item['snmpv3_privpassphrase'],
				'formula' => $item['formula'],
				'delay_flex' => $item['delay_flex'],
				'params' => $item['params'],
				'ipmi_sensor' => $item['ipmi_sensor'],
				'data_type' => $item['data_type'],
				'authtype' => $item['authtype'],
				'username' => $item['username'],
				'password' => $item['password'],
				'publickey' => $item['publickey'],
				'privatekey' => $item['privatekey'],
				'port' => $item['port'],
				'description' => $item['description'],
				'inventory_link' => $item['inventory_link'],
				'applications' => $this->formatApplications($item['applications']),
				'valuemap' => $item['valuemap'],
				'logtimefmt' => $item['logtimefmt']
			];

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$data['application_prototypes'] = $this->formatApplications($item['applicationPrototypes']);
			}

			if (isset($item['interface_ref'])) {
				$data['interface_ref'] = $item['interface_ref'];
			}

			$result[] = $data;
		}

		order_result($result, 'key');

		return $result;
	}

	/**
	 * Format applications.
	 *
	 * @param array $applications
	 *
	 * @return array
	 */
	protected function formatApplications(array $applications) {
		$result = [];

		foreach ($applications as $application) {
			$result[] = [
				'name' => $application['name']
			];
		}

		order_result($result, 'name');

		return $result;
	}

	/**
	 * Format macros.
	 *
	 * @param array $macros
	 *
	 * @return array
	 */
	protected function formatMacros(array $macros) {
		$result = [];

		foreach ($macros as $macro) {
			$result[] = [
				'macro' => $macro['macro'],
				'value' => $macro['value']
			];
		}

		$macros = order_macros($result, 'macro');

		return $result;
	}

	/**
	 * Format screens.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	protected function formatScreens(array $screens) {
		$result = [];

		foreach ($screens as $screen) {
			$result[] = [
				'name' => $screen['name'],
				'hsize' => $screen['hsize'],
				'vsize' => $screen['vsize'],
				'screen_items' => $this->formatScreenItems($screen['screenitems'])
			];
		}

		order_result($result, 'name');

		return $result;
	}

	/**
	 * Format trigger dependencies.
	 *
	 * @param array $dependencies
	 *
	 * @return array
	 */
	protected function formatDependencies(array $dependencies) {
		$result = [];

		foreach ($dependencies as $dependency) {
			$result[] = [
				'name' => $dependency['description'],
				'expression' => $dependency['expression']
			];
		}

		CArrayHelper::sort($result, ['name', 'expression']);

		return $result;
	}

	/**
	 * Format screen items.
	 *
	 * @param array $screenItems
	 *
	 * @return array
	 */
	protected function formatScreenItems(array $screenItems) {
		$result = [];

		foreach ($screenItems as $screenItem) {
			$result[] = [
				'resourcetype' => $screenItem['resourcetype'],
				'width' => $screenItem['width'],
				'height' => $screenItem['height'],
				'x' => $screenItem['x'],
				'y' => $screenItem['y'],
				'colspan' => $screenItem['colspan'],
				'rowspan' => $screenItem['rowspan'],
				'elements' => $screenItem['elements'],
				'valign' => $screenItem['valign'],
				'halign' => $screenItem['halign'],
				'style' => $screenItem['style'],
				'url' => $screenItem['url'],
				'dynamic' => $screenItem['dynamic'],
				'sort_triggers' => $screenItem['sort_triggers'],
				'resource' => $screenItem['resourceid'],
				'max_columns' => $screenItem['max_columns'],
				'application' => $screenItem['application']
			];
		}

		CArrayHelper::sort($result, ['y', 'x']);

		return $result;
	}

	/**
	 * Format graph items.
	 *
	 * @param array $graphItems
	 *
	 * @return array
	 */
	protected function formatGraphItems(array $graphItems) {
		$result = [];

		foreach ($graphItems as $graphItem) {
			$result[] = [
				'sortorder'=> $graphItem['sortorder'],
				'drawtype'=> $graphItem['drawtype'],
				'color'=> $graphItem['color'],
				'yaxisside'=> $graphItem['yaxisside'],
				'calc_fnc'=> $graphItem['calc_fnc'],
				'type'=> $graphItem['type'],
				'item'=> $graphItem['itemid']
			];
		}

		CArrayHelper::sort($result, ['sortorder']);

		return $result;
	}

	/**
	 * Format map urls.
	 *
	 * @param array $urls
	 *
	 * @return array
	 */
	protected function formatMapUrls(array $urls) {
		$result = [];

		foreach ($urls as $url) {
			$result[] = [
				'name' => $url['name'],
				'url' => $url['url'],
				'elementtype' => $url['elementtype']
			];
		}

		CArrayHelper::sort($result, ['name', 'url']);

		return $result;
	}

	/**
	 * Format map element urls.
	 *
	 * @param array $urls
	 *
	 * @return array
	 */
	protected function formatMapElementUrls(array $urls) {
		$result = [];

		foreach ($urls as $url) {
			$result[] = [
				'name' => $url['name'],
				'url' => $url['url']
			];
		}

		CArrayHelper::sort($result, ['name', 'url']);

		return $result;
	}

	/**
	 * Format map links.
	 *
	 * @param array $links			Map links
	 * @param array $selements		Map elements
	 *
	 * @return array
	 */
	protected function formatMapLinks(array $links, array $selements) {
		$result = [];

		// Get array where key is selementid and value is sort position.
		$flipped_selements = [];
		$selements = array_values($selements);

		foreach ($selements as $key => $item) {
			if (array_key_exists('selementid', $item)) {
				$flipped_selements[$item['selementid']] = $key;
			}
		}

		foreach ($links as &$link) {
			$link['selementpos1'] = $flipped_selements[$link['selementid1']];
			$link['selementpos2'] = $flipped_selements[$link['selementid2']];

			// Sort selements positons asc.
			if ($link['selementpos2'] < $link['selementpos1']) {
				zbx_swap($link['selementpos1'], $link['selementpos2']);
			}
		}
		unset($link);

		CArrayHelper::sort($links, ['selementpos1', 'selementpos2']);

		foreach ($links as $link) {
			$result[] = [
				'drawtype' => $link['drawtype'],
				'color' => $link['color'],
				'label' => $link['label'],
				'selementid1' => $link['selementid1'],
				'selementid2' => $link['selementid2'],
				'linktriggers' => $this->formatMapLinkTriggers($link['linktriggers'])
			];
		}

		return $result;
	}

	/**
	 * Format map link triggers.
	 *
	 * @param array $linktriggers
	 *
	 * @return array
	 */
	protected function formatMapLinkTriggers(array $linktriggers) {
		$result = [];

		foreach ($linktriggers as &$linktrigger) {
			$linktrigger['description'] = $linktrigger['triggerid']['description'];
			$linktrigger['expression'] = $linktrigger['triggerid']['expression'];
		}
		unset($linktrigger);

		CArrayHelper::sort($linktriggers, ['description', 'expression']);

		foreach ($linktriggers as $linktrigger) {
			$result[] = [
				'drawtype' => $linktrigger['drawtype'],
				'color' => $linktrigger['color'],
				'trigger' => $linktrigger['triggerid']
			];
		}

		return $result;
	}

	/**
	 * Format map elements.
	 *
	 * @param array $elements
	 *
	 * @return array
	 */
	protected function formatMapElements(array $elements) {
		$result = [];

		foreach ($elements as $element) {
			$result[] = [
				'elementtype' => $element['elementtype'],
				'label' => $element['label'],
				'label_location' => $element['label_location'],
				'x' => $element['x'],
				'y' => $element['y'],
				'elementsubtype' => $element['elementsubtype'],
				'areatype' => $element['areatype'],
				'width' => $element['width'],
				'height' => $element['height'],
				'viewtype' => $element['viewtype'],
				'use_iconmap' => $element['use_iconmap'],
				'selementid' => $element['selementid'],
				'element' => $element['elementid'],
				'icon_off' => $element['iconid_off'],
				'icon_on' => $element['iconid_on'],
				'icon_disabled' => $element['iconid_disabled'],
				'icon_maintenance' => $element['iconid_maintenance'],
				'application' => $element['application'],
				'urls' => $this->formatMapElementUrls($element['urls'])
			];
		}

		CArrayHelper::sort($result, ['y', 'x']);

		return $result;
	}
}
