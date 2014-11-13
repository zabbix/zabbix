<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	const EXPORT_VERSION = '2.0';

	/**
	 * @var array
	 */
	protected $data = array();

	public function __construct() {
		$this->data['version'] = self::EXPORT_VERSION;
		$this->data['date'] = date('Y-m-d\TH:i:s\Z', time() - date('Z'));
	}

	/**
	 * Get array with formatted export data.
	 *
	 * @return array
	 */
	public function getExport() {
		return array('zabbix_export' => $this->data);
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
		order_result($templates, 'host');
		$this->data['templates'] = array();

		foreach ($templates as $template) {
			$this->data['templates'][] = array(
				'template' => $template['host'],
				'name' => $template['name'],
				'groups' => $this->formatGroups($template['groups']),
				'applications' => $this->formatApplications($template['applications']),
				'items' => $this->formatItems($template['items']),
				'discovery_rules' => $this->formatDiscoveryRules($template['discoveryRules']),
				'macros' => $this->formatMacros($template['macros']),
				'templates' => $this->formatTemplateLinkage($template['parentTemplates']),
				'screens' => $this->formatScreens($template['screens'])
			);
		}
	}

	/**
	 * Format hosts.
	 *
	 * @param $hosts
	 */
	public function buildHosts($hosts) {
		order_result($hosts, 'host');
		$this->data['hosts'] = array();

		foreach ($hosts as $host) {
			$host = $this->createInterfaceReferences($host);

			$this->data['hosts'][] = array(
				'host' => $host['host'],
				'name' => $host['name'],
				'proxy' => $host['proxy'],
				'status' => $host['status'],
				'ipmi_authtype' => $host['ipmi_authtype'],
				'ipmi_privilege' => $host['ipmi_privilege'],
				'ipmi_username' => $host['ipmi_username'],
				'ipmi_password' => $host['ipmi_password'],
				'templates' => $this->formatTemplateLinkage($host['parentTemplates']),
				'groups' => $this->formatGroups($host['groups']),
				'interfaces' => $this->formatHostInterfaces($host['interfaces']),
				'applications' => $this->formatApplications($host['applications']),
				'items' => $this->formatItems($host['items']),
				'discovery_rules' => $this->formatDiscoveryRules($host['discoveryRules']),
				'macros' => $this->formatMacros($host['macros']),
				'inventory' => $this->formatHostInventory($host['inventory'])
			);
		}
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
		$this->data['images'] = array();

		foreach ($images as $image) {
			$this->data['images'][] = array(
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['encodedImage']
			);
		}
	}

	/**
	 * Format maps.
	 *
	 * @param array $maps
	 */
	public function buildMaps(array $maps) {
		order_result($maps, 'name');
		$this->data['maps'] = array();

		foreach ($maps as $map) {
			$this->data['maps'][] = array(
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
				'selements' => $this->formatMapElements($map['selements']),
				'links' => $this->formatMapLinks($map['links'])
			);
		}
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
		$references = array(
			'num' => 1,
			'refs' => array()
		);

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
		$result = array();
		order_result($discoveryRules, 'name');

		foreach ($discoveryRules as $discoveryRule) {
			$data = array(
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
			);
			if (isset($discoveryRule['interface_ref'])) {
				$data['interface_ref'] = $discoveryRule['interface_ref'];
			}
			$result[] = $data;
		}

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
		$result = array();
		order_result($graphs, 'name');

		foreach ($graphs as $graph) {
			$result[] = array(
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
			);
		}

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
		$result = array();
		order_result($hostPrototypes, 'host');

		foreach ($hostPrototypes as $hostPrototype) {
			$result[] = array(
				'host' => $hostPrototype['host'],
				'name' => $hostPrototype['name'],
				'status' => $hostPrototype['status'],
				'group_links' => $this->formatGroupLinks($hostPrototype['groupLinks']),
				'group_prototypes' => $this->formatGroupPrototypes($hostPrototype['groupPrototypes']),
				'templates' => $this->formatTemplateLinkage($hostPrototype['templates'])
			);
		}

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
		$result = array();

		foreach ($groupLinks as $groupLink) {
			$result[] = array(
				'group' => $groupLink['groupid'],
			);
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
		$result = array();

		foreach ($groupPrototypes as $groupPrototype) {
			$result[] = array(
				'name' => $groupPrototype['name']
			);
		}

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
		$result = array();
		order_result($templates, 'host');

		foreach ($templates as $template) {
			$result[] = array(
				'name' => $template['host']
			);
		}

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
		order_result($triggers, 'description');

		$result = array();
		foreach ($triggers as $trigger) {
			$tr = array(
				'expression' => $trigger['expression'],
				'name' => $trigger['description'],
				'url' => $trigger['url'],
				'status' => $trigger['status'],
				'priority' => $trigger['priority'],
				'description' => $trigger['comments'],
				'type' => $trigger['type']
			);
			if (isset($trigger['dependencies'])) {
				$tr['dependencies'] = $this->formatDependencies($trigger['dependencies']);
			}

			$result[] = $tr;
		}

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
		$result = array();
		order_result($interfaces, 'ip');

		foreach ($interfaces as $interface) {
			$result[] = array(
				'default' => $interface['main'],
				'type' => $interface['type'],
				'useip' => $interface['useip'],
				'ip' => $interface['ip'],
				'dns' => $interface['dns'],
				'port' => $interface['port'],
				'interface_ref' => $interface['interface_ref']
			);
		}

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
		$result = array();
		order_result($groups, 'name');

		foreach ($groups as $group) {
			$result[] = array(
				'name' => $group['name']
			);
		}

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
		$result = array();
		order_result($items, 'name');

		foreach ($items as $item) {
			$data = array(
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
			);
			if (isset($item['interface_ref'])) {
				$data['interface_ref'] = $item['interface_ref'];
			}
			$result[] = $data;
		}

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
		$result = array();
		order_result($applications, 'name');

		foreach ($applications as $application) {
			$result[] = array(
				'name' => $application['name']
			);
		}

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
		$result = array();
		$macros = order_macros($macros, 'macro');

		foreach ($macros as $macro) {
			$result[] = array(
				'macro' => $macro['macro'],
				'value' => $macro['value']
			);
		}

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
		$result = array();
		order_result($screens, 'name');

		foreach ($screens as $screen) {
			$result[] = array(
				'name' => $screen['name'],
				'hsize' => $screen['hsize'],
				'vsize' => $screen['vsize'],
				'screen_items' => $this->formatScreenItems($screen['screenitems'])
			);
		}

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
		$result = array();

		foreach ($dependencies as $dependency) {
			$result[] = array(
				'name' => $dependency['description'],
				'expression' => $dependency['expression']
			);
		}

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
		$result = array();

		foreach ($screenItems as $screenItem) {
			$result[] = array(
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
				'application' => $screenItem['application']
			);
		}

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
		$result = array();

		foreach ($graphItems as $graphItem) {
			$result[] = array(
				'sortorder'=> $graphItem['sortorder'],
				'drawtype'=> $graphItem['drawtype'],
				'color'=> $graphItem['color'],
				'yaxisside'=> $graphItem['yaxisside'],
				'calc_fnc'=> $graphItem['calc_fnc'],
				'type'=> $graphItem['type'],
				'item'=> $graphItem['itemid']
			);
		}

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
		$result = array();
		foreach ($urls as $url) {
			$result[] = array(
				'name' => $url['name'],
				'url' => $url['url'],
				'elementtype' => $url['elementtype']
			);
		}

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
		$result = array();

		foreach ($urls as $url) {
			$result[] = array(
				'name' => $url['name'],
				'url' => $url['url']
			);
		}

		return $result;
	}

	/**
	 * Format map links.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	protected function formatMapLinks(array $links) {
		$result = array();

		foreach ($links as $link) {
			$result[] = array(
				'drawtype' => $link['drawtype'],
				'color' => $link['color'],
				'label' => $link['label'],
				'selementid1' => $link['selementid1'],
				'selementid2' => $link['selementid2'],
				'linktriggers' => $this->formatMapLinkTriggers($link['linktriggers'])
			);
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
		$result = array();

		foreach ($linktriggers as $linktrigger) {
			$result[] = array(
				'drawtype' => $linktrigger['drawtype'],
				'color' => $linktrigger['color'],
				'trigger' => $linktrigger['triggerid']
			);
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
		$result = array();
		foreach ($elements as $element) {
			$result[] = array(
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
				'urls' => $this->formatMapElementUrls($element['urls'])
			);
		}

		return $result;
	}
}
