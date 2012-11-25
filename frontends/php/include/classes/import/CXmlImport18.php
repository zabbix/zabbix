<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CXmlImport18 {

	private static $xml = null;
	private static $ZBX_EXPORT_MAP = array(
		XML_TAG_HOST => array(
			'attributes' => array(
				'host' => 'name'
			),
			'elements' => array(
				'name' => '',
				'proxy_hostid' => '',
				'useip' => '',
				'dns' => '',
				'ip' => '',
				'port' => '',
				'status' => '',
				'useipmi' => '',
				'ipmi_ip' => '',
				'ipmi_port' => '',
				'ipmi_authtype' => '',
				'ipmi_privilege' => '',
				'ipmi_username' => '',
				'ipmi_password' => '',
			)
		),
		XML_TAG_MACRO => array(
			'attributes' => array(),
			'elements' => array(
				'value' => '',
				'macro' => 'name'
			)
		),
		XML_TAG_HOSTINVENTORY => array(
			'attributes' => array(),
			'elements' => array(
				'devicetype' => '',
				'name' => '',
				'os' => '',
				'serialno' => '',
				'tag' => '',
				'macaddress' => '',
				'hardware' => '',
				'software' => '',
				'contact' => '',
				'location' => '',
				'notes' => '',
				'device_alias' => '',
				'device_type' => '',
				'device_chassis' => '',
				'device_os' => '',
				'device_os_short' => '',
				'device_hw_arch' => '',
				'device_serial' => '',
				'device_model' => '',
				'device_tag' => '',
				'device_vendor' => '',
				'device_contract' => '',
				'device_who' => '',
				'device_status' => '',
				'device_app_01' => '',
				'device_app_02' => '',
				'device_app_03' => '',
				'device_app_04' => '',
				'device_app_05' => '',
				'device_url_1' => '',
				'device_url_2' => '',
				'device_url_3' => '',
				'device_networks' => '',
				'device_notes' => '',
				'device_hardware' => '',
				'device_software' => '',
				'ip_subnet_mask' => '',
				'ip_router' => '',
				'ip_macaddress' => '',
				'oob_ip' => '',
				'oob_subnet_mask' => '',
				'oob_router' => '',
				'date_hw_buy' => '',
				'date_hw_install' => '',
				'date_hw_expiry' => '',
				'date_hw_decomm' => '',
				'site_street_1' => '',
				'site_street_2' => '',
				'site_street_3' => '',
				'site_city' => '',
				'site_state' => '',
				'site_country' => '',
				'site_zip' => '',
				'site_rack' => '',
				'site_notes' => '',
				'poc_1_name' => '',
				'poc_1_email' => '',
				'poc_1_phone_1' => '',
				'poc_1_phone_2' => '',
				'poc_1_cell' => '',
				'poc_1_screen' => '',
				'poc_1_notes' => '',
				'poc_2_name' => '',
				'poc_2_email' => '',
				'poc_2_phone_1' => '',
				'poc_2_phone_2' => '',
				'poc_2_cell' => '',
				'poc_2_screen' => '',
				'poc_2_notes' => ''
			)
		),
		XML_TAG_DEPENDENCY => array(
			'attributes' => array(
				'host_trigger' => 'description'
			),
			'elements' => array(
				'depends' => ''
			)
		),
		XML_TAG_ITEM => array(
			'attributes' => array(
				'type' => '',
				'key_' => 'key',
				'value_type' => ''
			),
			'elements' => array(
				'name' => 'description',
				'ipmi_sensor' => '',
				'delay' => '',
				'history' => '',
				'trends' => '',
				'status' => '',
				'data_type' => '',
				'units' => '',
				'multiplier' => '',
				'delta' => '',
				'formula' => '',
				'lastlogsize' => '',
				'logtimefmt' => '',
				'delay_flex' => '',
				'authtype' => '',
				'username' => '',
				'password' => '',
				'publickey' => '',
				'privatekey' => '',
				'params' => '',
				'trapper_hosts' => '',
				'snmp_community' => '',
				'snmp_oid' => '',
				'port' => '',
				'snmp_port' => '',
				'snmpv3_securityname' => '',
				'snmpv3_securitylevel' => '',
				'snmpv3_authprotocol' => '',
				'snmpv3_authpassphrase' => '',
				'snmpv3_privprotocol' => '',
				'snmpv3_privpassphrase' => ''
			)
		),
		XML_TAG_TRIGGER => array(
			'attributes' => array(),
			'elements' => array(
				'description' => '',
				'type' => '',
				'expression' => '',
				'url' => '',
				'status' => '',
				'priority' => '',
				'comments' => ''
			)
		),
		XML_TAG_GRAPH => array(
			'attributes' => array(
				'name' => '',
				'width' => '',
				'height' => ''
			),
			'elements' => array(
				'ymin_type' => '',
				'ymax_type' => '',
				'ymin_item_key' => '',
				'ymax_item_key' => '',
				'show_work_period' => '',
				'show_triggers' => '',
				'graphtype' => '',
				'yaxismin' => '',
				'yaxismax' => '',
				'show_legend' => '',
				'show_3d' => '',
				'percent_left' => '',
				'percent_right' => ''
			)
		),
		XML_TAG_GRAPH_ELEMENT => array(
			'attributes' => array(
				'host_key_' => 'item'
			),
			'elements' => array(
				'drawtype' => '',
				'sortorder' => '',
				'color' => '',
				'yaxisside' => '',
				'calc_fnc' => '',
				'type' => ''
			)
		)
	);

	private static $oldKeys = array(
		'tcp',
		'ftp',
		'http',
		'imap',
		'ldap',
		'nntp',
		'ntp',
		'pop',
		'smtp',
		'ssh'
	);
	private static $oldKeysPref = array(
		'tcp_perf',
		'ftp_perf',
		'http_perf',
		'imap_perf',
		'ldap_perf',
		'nntp_perf',
		'ntp_perf',
		'pop_perf',
		'smtp_perf',
		'ssh_perf'
	);

	protected static function mapInventoryName($name) {
		$map = array(
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
			'device_notes' => 'notes',
		);

		return isset($map[$name]) ? $map[$name] : $name;
	}

	protected static function createDOMDocument() {
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;

		$root = $doc->appendChild(new DOMElement('zabbix_export'));
		$root->setAttributeNode(new DOMAttr('version', '1.0'));
		$root->setAttributeNode(new DOMAttr('date', zbx_date2str(XML_DATE_DATE_FORMAT)));
		$root->setAttributeNode(new DOMAttr('time', zbx_date2str(XML_TIME_DATE_FORMAT)));

		return $root;
	}

	/**
	 * Converts Simple key from old format to new.
	 *
	 *
	 * @param mixed $oldKey   Simple key in old format
	 *
	 * @return mixed
	 */
	public static function convertOldSimpleKey($oldKey) {
		$newKey = $oldKey;

		$explodedKey = explode(',', $oldKey);

		if (in_array($explodedKey[0], self::$oldKeys)) {
			$newKey = 'net.tcp.service['.$explodedKey[0].',,'.$explodedKey[1].']';
		}
		elseif (in_array($explodedKey[0], self::$oldKeysPref)) {
			$keyWithoutPerf = explode('_', $explodedKey[0]);
			$newKey = 'net.tcp.service.perf['.$keyWithoutPerf[0].',,'.$explodedKey[1].']';
		}

		return $newKey;
	}

	public static function XMLtoArray($parentNode) {
		$array = array();

		foreach ($parentNode->childNodes as $node) {
			if ($node->nodeType == 3) {
				if ($node->nextSibling) {
					continue;
				}
				if (!$node->isWhitespaceInElementContent()) {
					return $node->nodeValue;
				}
			}

			if ($node->hasChildNodes()) {
				$nodeName = $node->nodeName;

				if (isset($array[$nodeName])) {
					$nodeName .= count($array);
				}
				$array[$nodeName] = self::XMLtoArray($node);
			}
		}

		return $array;
	}

	private static function mapXML2arr($xml, $tag) {
		$array = array();

		foreach (self::$ZBX_EXPORT_MAP[$tag]['attributes'] as $attr => $value) {
			if ($value == '') {
				$value = $attr;
			}

			if ($xml->getAttribute($value) != '') {
				$array[$attr] = $xml->getAttribute($value);
			}
		}

		// fill empty values with key if empty
		$map = self::$ZBX_EXPORT_MAP[$tag]['elements'];
		foreach ($map as $db_name => $xml_name) {
			if ($xml_name == '') {
				$map[$db_name] = $db_name;
			}
			else {
				$map[$xml_name] = $db_name;
			}
		}

		foreach ($xml->childNodes as $node) {
			if (isset($map[$node->nodeName])) {
				$array[$map[$node->nodeName]] = $node->nodeValue;
			}
		}

		return $array;
	}

	public static function import($file) {

		libxml_use_internal_errors(true);

		$xml = new DOMDocument();
		if (!$xml->loadXML($file)) {
			$text = '';
			foreach (libxml_get_errors() as $error) {
				switch ($error->level) {
					case LIBXML_ERR_WARNING:
						$text .= _('XML file contains errors').'. Warning '.$error->code.': ';
						break;
					case LIBXML_ERR_ERROR:
						$text .= _('XML file contains errors').'. Error '.$error->code.': ';
						break;
					case LIBXML_ERR_FATAL:
						$text .= _('XML file contains errors').'. Fatal Error '.$error->code.': ';
						break;
				}

				$text .= trim($error->message).' [ Line: '.$error->line.' | Column: '.$error->column.' ]';
				break;
			}
			libxml_clear_errors();

			throw new Exception($text);
		}

		if ($xml->childNodes->item(0)->nodeName != 'zabbix_export') {
			$xml2 = self::createDOMDocument();
			$xml2->appendChild($xml2->ownerDocument->importNode($xml->childNodes->item(0), true));
			self::$xml = $xml2->ownerDocument;
		}
		else {
			self::$xml = $xml;
		}

		return true;
	}

	public static function parseScreen($rules, $xml = null) {
		$xml = is_null($xml) ? self::$xml : $xml;
		$importScreens = self::XMLtoArray($xml);
		if (!isset($importScreens['zabbix_export']['screens'])) {
			return true;
		}
		$importScreens = $importScreens['zabbix_export']['screens'];

		$screens = array();

		foreach ($importScreens as $mnum => &$screen) {
			unset($screen['screenid']);
			$exists = API::Screen()->exists(array('name' => $screen['name']));

			if ($exists && !empty($rules['screens']['updateExisting'])) {
				$db_screens = API::Screen()->get(array('filter' => array('name' => $screen['name'])));
				if (empty($db_screens)) {
					throw new Exception(_s('No permissions for screen "%1$s".', $screen['name']));
				}

				$db_screen = reset($db_screens);

				$screen['screenid'] = $db_screen['screenid'];
			}
			else if ($exists || empty($rules['screens']['createMissing'])) {
				info(_s('Screen "%1$s" skipped - user rule.', $screen['name']));
				unset($importScreens[$mnum]);
				continue; // break if not update exist
			}

			if (!isset($screen['screenitems'])) {
				$screen['screenitems'] = array();
			}

			foreach ($screen['screenitems'] as &$screenitem) {
				$nodeCaption = isset($screenitem['resourceid']['node']) ? $screenitem['resourceid']['node'].':' : '';

				if (!isset($screenitem['resourceid'])) {
					$screenitem['resourceid'] = 0;
				}
				if (is_array($screenitem['resourceid'])) {
					switch ($screenitem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							if (is_array($screenitem['resourceid'])) {
								$db_hostgroups = API::HostGroup()->getObjects($screenitem['resourceid']);
								if (empty($db_hostgroups)) {
									$error = _s('Cannot find group "%1$s" used in screen "%2$s".',
											$nodeCaption.$screenitem['resourceid']['name'], $screen['name']);
									throw new Exception($error);
								}

								$tmp = reset($db_hostgroups);
								$screenitem['resourceid'] = $tmp['groupid'];
							}
							break;
						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$db_hosts = API::Host()->getObjects($screenitem['resourceid']);
							if (empty($db_hosts)) {
								$error = _s('Cannot find host "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resourceid']['host'], $screen['name']);
								throw new Exception($error);
							}

							$tmp = reset($db_hosts);
							$screenitem['resourceid'] = $tmp['hostid'];
							break;
						case SCREEN_RESOURCE_GRAPH:
							$db_graphs = API::Graph()->getObjects($screenitem['resourceid']);
							if (empty($db_graphs)) {
								$error = _s('Cannot find graph "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resourceid']['host'].':'.$screenitem['resourceid']['name'], $screen['name']);
								throw new Exception($error);
							}

							$tmp = reset($db_graphs);
							$screenitem['resourceid'] = $tmp['graphid'];
							break;
						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$db_items = API::Item()->getObjects($screenitem['resourceid']);

							if (empty($db_items)) {
								$error = _s('Cannot find item "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resourceid']['host'].':'.$screenitem['resourceid']['key_'], $screen['name']);
								throw new Exception($error);
							}

							$tmp = reset($db_items);
							$screenitem['resourceid'] = $tmp['itemid'];
							break;
						case SCREEN_RESOURCE_MAP:
							$db_sysmaps = API::Map()->getObjects($screenitem['resourceid']);
							if (empty($db_sysmaps)) {
								$error = _s('Cannot find map "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resourceid']['name'], $screen['name']);
								throw new Exception($error);
							}

							$tmp = reset($db_sysmaps);
							$screenitem['resourceid'] = $tmp['sysmapid'];
							break;
						case SCREEN_RESOURCE_SCREEN:
							$db_screens = API::Screen()->get(array('screenids' => $screenitem['resourceid']));
							if (empty($db_screens)) {
								$error = _s('Cannot find screen "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resourceid']['name'], $screen['name']);
								throw new Exception($error);
							}

							$tmp = reset($db_screens);
							$screenitem['resourceid'] = $tmp['screenid'];
							break;
						default:
							$screenitem['resourceid'] = 0;
							break;
					}
				}
			}
			unset($screenitem);

			$screens[] = $screen;
		}
		unset($screen);

		$importScreens = $screens;

		foreach ($importScreens as $screen) {
			if (isset($screen['screenid'])) {
				API::Screen()->update($screen);
			}
			else {
				API::Screen()->create($screen);
			}

			if (isset($screen['screenid'])) {
				info(_s('Screen "%1$s" updated.', $screen['name']));
			}
			else {
				info(_s('Screen "%1$s" added.', $screen['name']));
			}

		}
	}

	public static function parseMap($rules) {
		$importMaps = self::XMLtoArray(self::$xml);

		if (!isset($importMaps['zabbix_export'])) {
			$importMaps['zabbix_export'] = $importMaps;
		}

		if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN && isset($importMaps['zabbix_export']['images'])) {
			$images = $importMaps['zabbix_export']['images'];
			$images_to_add = array();
			$images_to_update = array();
			foreach ($images as $image) {
				if (API::Image()->exists($image)) {
					if ((($image['imagetype'] == IMAGE_TYPE_ICON) && !empty($rules['images']['updateExisting']))
							|| (($image['imagetype'] == IMAGE_TYPE_BACKGROUND) && (!empty($rules['images']['updateExisting'])))
					) {

						$options = array(
							'filter' => array('name' => $image['name']),
							'output' => array('imageid')
						);
						$imgs = API::Image()->get($options);
						$img = reset($imgs);

						$image['imageid'] = $img['imageid'];

						// image will be decoded in class.image.php
						$image['image'] = $image['encodedImage'];
						unset($image['encodedImage']);

						$images_to_update[] = $image;
					}
				}
				else {
					if ((($image['imagetype'] == IMAGE_TYPE_ICON) && !empty($rules['images']['createMissing']))
							|| (($image['imagetype'] == IMAGE_TYPE_BACKGROUND) && !empty($rules['images']['createMissing']))
					) {

						// No need to decode_base64
						$image['image'] = $image['encodedImage'];

						unset($image['encodedImage']);
						$images_to_add[] = $image;
					}
				}
			}

			if (!empty($images_to_add)) {
				$result = API::Image()->create($images_to_add);
				if (!$result) {
					throw new Exception(_('Cannot add image.'));
				}
			}

			if (!empty($images_to_update)) {
				$result = API::Image()->update($images_to_update);
				if (!$result) {
					throw new Exception(_('Cannot update image.'));
				}
			}
		}


		if (!isset($importMaps['zabbix_export']['sysmaps'])) {
			return true;
		}
		$importMaps = $importMaps['zabbix_export']['sysmaps'];
		foreach ($importMaps as $mnum => &$sysmap) {
			unset($sysmap['sysmapid']);
			$exists = API::Map()->exists(array('name' => $sysmap['name']));

			if (!isset($sysmap['label_format'])) {
				$sysmap['label_format'] = SYSMAP_LABEL_ADVANCED_OFF;
			}

			if ($exists && !empty($rules['maps']['updateExisting'])) {
				$db_maps = API::Map()->getObjects(array('name' => $sysmap['name']));
				if (empty($db_maps)) {
					throw new Exception(_s('No permissions for map "%1$s".', $sysmap['name']));
				}

				$db_map = reset($db_maps);
				$sysmap['sysmapid'] = $db_map['sysmapid'];
			}
			else if ($exists || empty($rules['maps']['createMissing'])) {
				info(_s('Map "%1$s" skipped - user rule.', $sysmap['name']));
				unset($importMaps[$mnum]);
				continue; // break if not update updateExisting
			}

			if (isset($sysmap['backgroundid'])) {
				$image = getImageByIdent($sysmap['backgroundid']);

				if (!$image) {
					error(_s('Cannot find background image "%1$s" used in map "%2$s".',
						$sysmap['backgroundid']['name'], $sysmap['name']));
					$sysmap['backgroundid'] = 0;
				}
				else {
					$sysmap['backgroundid'] = $image['imageid'];
				}
			}
			else {
				$sysmap['backgroundid'] = 0;
			}

			if (!isset($sysmap['selements'])) {
				$sysmap['selements'] = array();
			}
			else {
				$sysmap['selements'] = array_values($sysmap['selements']);
			}

			if (!isset($sysmap['links'])) {
				$sysmap['links'] = array();
			}
			else {
				$sysmap['links'] = array_values($sysmap['links']);
			}

			foreach ($sysmap['selements'] as &$selement) {
				$nodeCaption = isset($selement['elementid']['node']) ? $selement['elementid']['node'].':' : '';

				if (!isset($selement['elementid'])) {
					$selement['elementid'] = 0;
				}
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$db_sysmaps = API::Map()->getObjects($selement['elementid']);
						if (empty($db_sysmaps)) {
							$error = _s('Cannot find map "%1$s" used in exported map "%2$s".',
									$nodeCaption.$selement['elementid']['name'], $sysmap['name']);
							throw new Exception($error);
						}

						$tmp = reset($db_sysmaps);
						$selement['elementid'] = $tmp['sysmapid'];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$db_hostgroups = API::HostGroup()->getObjects($selement['elementid']);
						if (empty($db_hostgroups)) {
							$error = _s('Cannot find group "%1$s" used in map "%2$s".',
									$nodeCaption.$selement['elementid']['name'], $sysmap['name']);
							throw new Exception($error);
						}

						$tmp = reset($db_hostgroups);
						$selement['elementid'] = $tmp['groupid'];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST:
						$db_hosts = API::Host()->getObjects($selement['elementid']);
						if (empty($db_hosts)) {
							$error = _s('Cannot find host "%1$s" used in map "%2$s".',
									$nodeCaption.$selement['elementid']['host'], $sysmap['name']);
							throw new Exception($error);
						}

						$tmp = reset($db_hosts);
						$selement['elementid'] = $tmp['hostid'];
						break;
					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$db_triggers = API::Trigger()->getObjects($selement['elementid']);
						if (empty($db_triggers)) {
							$error = _s('Cannot find trigger "%1$s" used in map "%2$s".',
									$nodeCaption.$selement['elementid']['host'].':'.$selement['elementid']['description'], $sysmap['name']);
							throw new Exception($error);
						}

						$tmp = reset($db_triggers);
						$selement['elementid'] = $tmp['triggerid'];
						break;
					case SYSMAP_ELEMENT_TYPE_IMAGE:
					default:
				}

				$icons = array(
					'iconid_off',
					'iconid_on',
					'iconid_disabled',
					'iconid_maintenance'
				);
				foreach ($icons as $icon) {
					if (isset($selement[$icon])) {
						$image = getImageByIdent($selement[$icon]);
						if (!$image) {
							$error = _s('Cannot find icon "%1$s" used in map "%2$s".', $selement[$icon]['name'], $sysmap['name']);
							throw new Exception($error);
						}
						$selement[$icon] = $image['imageid'];
					}
					else {
						$selement[$icon] = 0;
					}
				}
			}
			unset($selement);

			foreach ($sysmap['links'] as &$link) {
				if (!isset($link['linktriggers'])) {
					continue;
				}

				foreach ($link['linktriggers'] as &$linktrigger) {
					$db_triggers = API::Trigger()->getObjects($linktrigger['triggerid']);
					if (empty($db_triggers)) {
						$nodeCaption = isset($linktrigger['triggerid']['node']) ? $linktrigger['triggerid']['node'].':' : '';
						$error = _s('Cannot find trigger "%1$s" used in map "%2$s".',
								$nodeCaption.$linktrigger['triggerid']['host'].':'.$linktrigger['triggerid']['description'], $sysmap['name']);
						throw new Exception($error);
					}

					$tmp = reset($db_triggers);
					$linktrigger['triggerid'] = $tmp['triggerid'];
				}
				unset($linktrigger);
			}
			unset($link);
		}
		unset($sysmap);


		foreach ($importMaps as $importMap) {
			if (isset($importMap['sysmapid'])) {
				$result = API::Map()->update($importMap);
				if ($result === false) {
					throw new Exception(_s('Cannot update map "%s".', $importMap['name']));
				}
				else {
					info(_s('Map "%s" updated.', $importMap['name']));
				}
			}
			else {
				$result = API::Map()->create($importMap);
				if ($result === false) {
					throw new Exception(_s('Cannot create map "%s".', $importMap['name']));
				}
				else {
					info(_s('Map "%s" created.', $importMap['name']));
				}
			}
		}

		return true;
	}

	public static function parseMain($rules) {
		$triggersForDependencies = array();

		if (!empty($rules['hosts']['updateExisting'])
				|| !empty($rules['hosts']['createMissing'])
				|| !empty($rules['templates']['createMissing'])
				|| !empty($rules['templates']['updateExisting'])
		) {
			$xpath = new DOMXPath(self::$xml);

			$hosts = $xpath->query('hosts/host');

			foreach ($hosts as $host) {
				$host_db = self::mapXML2arr($host, XML_TAG_HOST);

				if (!isset($host_db['status'])) {
					$host_db['status'] = HOST_STATUS_TEMPLATE;
				}
				$current_host = ($host_db['status'] == HOST_STATUS_TEMPLATE)
						? API::Template()->exists($host_db)
						: API::Host()->exists($host_db);


				if (!$current_host
						&& (($host_db['status'] == HOST_STATUS_TEMPLATE && empty($rules['templates']['createMissing']))
								|| ($host_db['status'] != HOST_STATUS_TEMPLATE && empty($rules['hosts']['createMissing'])))
				) {
					continue;
				}

				if ($current_host
						&& (($host_db['status'] == HOST_STATUS_TEMPLATE && empty($rules['templates']['updateExisting']))
								|| ($host_db['status'] != HOST_STATUS_TEMPLATE && empty($rules['hosts']['updateExisting'])))
				) {
					continue;
				}

				// there were no host visible names in 1.8
				if (!isset($host_db['name'])) {
					$host_db['name'] = $host_db['host'];
				}

				// host will have no interfaces - we will be creating them separately
				$host_db['interfaces'] = null;

				// it is possible, that data is imported from 1.8, where there was only one network interface per host
				/**
				 * @todo when new XML format will be introduced, this check should be changed to XML version check
				 */
				$old_version_input = $host_db['status'] != HOST_STATUS_TEMPLATE;
				if ($old_version_input) {
					// rearranging host structure, so it would look more like 2.0 host
					$interfaces = array();

					// the main interface is always "agent" type
					if (!is_null($host_db['ip'])) {
						$interfaces[] = array(
							'main' => INTERFACE_PRIMARY,
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => $host_db['useip'],
							'ip' => $host_db['ip'],
							'dns' => $host_db['dns'],
							'port' => $host_db['port']
						);
					}

					// now we need to check if host had SNMP items. If it had, we need and SNMP interface for every different port.
					$items = $xpath->query('items/item', $host);
					$snmp_interface_ports_created = array();
					foreach ($items as $item) {
						$item_db = self::mapXML2arr($item, XML_TAG_ITEM);
						if (($item_db['type'] == ITEM_TYPE_SNMPV1
								|| $item_db['type'] == ITEM_TYPE_SNMPV2C
								|| $item_db['type'] == ITEM_TYPE_SNMPV3)
								&& !isset($snmp_interface_ports_created[$item_db['snmp_port']])
						) {

							$interfaces[] = array(
								'main' => INTERFACE_PRIMARY,
								'type' => INTERFACE_TYPE_SNMP,
								'useip' => $host_db['useip'],
								'ip' => $host_db['ip'],
								'dns' => $host_db['dns'],
								'port' => $item_db['snmp_port']
							);
							$snmp_interface_ports_created[$item_db['snmp_port']] = 1;
						}
					}
					unset($snmp_interface_ports_created); // it was a temporary variable


					// we ned to add ipmi interface if at least one ipmi item exists
					foreach ($items as $item) {
						$item_db = self::mapXML2arr($item, XML_TAG_ITEM);
						if ($item_db['type'] == ITEM_TYPE_IPMI) {
							// when saving a host in 1.8, it's possible to set useipmi=1 and not to fill an IP address
							// we were not really sure what to do with this host,
							// and decided to take host IP address instead and show info message about this
							if ($host_db['ipmi_ip'] == '') {
								$ipmi_ip = $host_db['ip'];
								info(_s('Host "%s" has "useipmi" parameter checked, but has no "ipmi_ip" parameter! Using host IP address as an address for IPMI interface.', $host_db['host']));
							}
							else {
								$ipmi_ip = $host_db['ipmi_ip'];
							}
							$interfaces[] = array(
								'main' => INTERFACE_PRIMARY,
								'type' => INTERFACE_TYPE_IPMI,
								'useip' => INTERFACE_USE_DNS,
								'ip' => '',
								'dns' => $ipmi_ip,
								'port' => $host_db['ipmi_port']
							);

							// we need only one ipmi interface
							break;
						}
					}
				}

				if ($current_host) {
					$options = array(
						'filter' => array('host' => $host_db['host']),
						'output' => API_OUTPUT_EXTEND,
						'editable' => 1,
						'selectInterfaces' => API_OUTPUT_EXTEND
					);
					if ($host_db['status'] == HOST_STATUS_TEMPLATE) {
						$current_host = API::Template()->get($options);
					}
					else {
						$current_host = API::Host()->get($options);
					}

					if (empty($current_host)) {
						throw new Exception(_s('No permission for host "%1$s".', $host_db['host']));
					}
					else {
						$current_host = reset($current_host);
					}


					// checking if host already exists - then some of the interfaces may not need to be created
					if ($host_db['status'] != HOST_STATUS_TEMPLATE) {
						// for every interface we got based on XML
						foreach ($interfaces as $i => $interface_db) {
							// checking every interface of current host
							foreach ($current_host['interfaces'] as $interface) {
								// if all parameters of interface are identical
								if (
									$interface['type'] == $interface_db['type']
									&& $interface['ip'] == $interface_db['ip']
									&& $interface['dns'] == $interface_db['dns']
									&& $interface['port'] == $interface_db['port']
									&& $interface['useip'] == $interface_db['useip']
								) {
									// this interface is the same as existing one!
									$interfaces[$i]['interfaceid'] = $interface['interfaceid'];
									break;
								}
							}
						}

					}
					$interfaces_created_with_host = false;
				}
				else {
					if ($host_db['status'] != HOST_STATUS_TEMPLATE) {
						$host_db['interfaces'] = $interfaces;
						$interfaces_created_with_host = true;
					}
				}

// HOST GROUPS {{{
				$groups = $xpath->query('groups/group', $host);

				$host_db['groups'] = array();
				$groups_to_parse = array();
				foreach ($groups as $group) {
					$groups_to_parse[] = array('name' => $group->nodeValue);
				}
				if (empty($groups_to_parse)) {
					$groups_to_parse[] = array('name' => ZBX_DEFAULT_IMPORT_HOST_GROUP);
				}

				foreach ($groups_to_parse as $group) {
					$current_group = API::HostGroup()->exists($group);

					if ($current_group) {
						$options = array(
							'filter' => $group,
							'output' => API_OUTPUT_EXTEND,
							'editable' => 1
						);
						$current_group = API::HostGroup()->get($options);
						if (empty($current_group)) {
							throw new Exception(_s('No permissions for group "%1$s".', $group['name']));
						}

						$host_db['groups'][] = reset($current_group);
					}
					else {
						$result = API::HostGroup()->create($group);
						if (!$result) {
							throw new Exception();
						}

						$options = array(
							'groupids' => $result['groupids'],
							'output' => API_OUTPUT_EXTEND
						);
						$new_group = API::HostGroup()->get($options);

						$host_db['groups'][] = reset($new_group);
					}
				}
// }}} HOST GROUPS


// MACROS
				$macros = $xpath->query('macros/macro', $host);
				if ($macros->length > 0) {
					$host_db['macros'] = array();
					foreach ($macros as $macro) {
						$host_db['macros'][] = self::mapXML2arr($macro, XML_TAG_MACRO);
					}
				}
// }}} MACROS

				// host inventory
				if ($old_version_input) {
					$inventoryNode = $xpath->query('host_profile/*', $host);
					if ($inventoryNode->length > 0) {
						if (!isset($host_db['inventory'])) {
							$host_db['inventory'] = array();
						}
						foreach ($inventoryNode as $field) {
							$newInventoryName = self::mapInventoryName($field->nodeName);
							$host_db['inventory'][$newInventoryName] = $field->nodeValue;
						}
					}

					$inventoryNodeExt = $xpath->query('host_profiles_ext/*', $host);
					if ($inventoryNodeExt->length > 0) {
						if (!isset($host_db['inventory'])) {
							$host_db['inventory'] = array();
						}
						foreach ($inventoryNodeExt as $field) {
							$newInventoryName = self::mapInventoryName($field->nodeName);
							if (isset($host_db['inventory'][$newInventoryName]) && $field->nodeValue !== '') {
								$host_db['inventory'][$newInventoryName] .= "\r\n\r\n";
								$host_db['inventory'][$newInventoryName] .= $field->nodeValue;
							}
							else {
								$host_db['inventory'][$newInventoryName] = $field->nodeValue;
							}
						}
					}

					$host_db['inventory_mode'] = isset($host_db['inventory']) ? HOST_INVENTORY_MANUAL : HOST_INVENTORY_DISABLED;
				}

// HOSTS
				if (isset($host_db['proxy_hostid'])) {
					$proxy_exists = API::Proxy()->get(array('proxyids' => $host_db['proxy_hostid']));
					if (empty($proxy_exists)) {
						$host_db['proxy_hostid'] = 0;
					}
				}

				if ($current_host && (!empty($rules['hosts']['updateExisting']) || !empty($rules['templates']['updateExisting']))) {
					if ($host_db['status'] == HOST_STATUS_TEMPLATE) {
						$host_db['templateid'] = $current_host['templateid'];
						$result = API::Template()->update($host_db);
						$current_hostid = $current_host['templateid'];
					}
					else {
						$host_db['hostid'] = $current_host['hostid'];
						$result = API::Host()->update($host_db);
						$current_hostid = $current_host['hostid'];
					}
					if (!$result) {
						throw new Exception();
					}
				}
				if (!$current_host && (!empty($rules['hosts']['createMissing']) || !empty($rules['templates']['createMissing']))) {

					if ($host_db['status'] == HOST_STATUS_TEMPLATE) {
						$result = API::Template()->create($host_db);
						if (!$result) {
							throw new Exception();
						}
						$current_hostid = reset($result['templateids']);
					}
					else {
						$result = API::Host()->create($host_db);
						if (!$result) {
							throw new Exception();
						}
						$current_hostid = reset($result['hostids']);
					}
				}
				$current_hostname = $host_db['host'];

// TEMPLATES {{{
				if (!empty($rules['templateLinkage']['createMissing'])) {
					$templates = $xpath->query('templates/template', $host);

					$templateLinkage = array();
					foreach ($templates as $template) {
						$options = array(
							'filter' => array('host' => $template->nodeValue),
							'output' => array('templateid'),
							'editable' => true
						);
						$current_template = API::Template()->get($options);

						if (empty($current_template)) {
							throw new Exception(_s('No permission for template "%1$s".', $template->nodeValue));
						}
						$current_template = reset($current_template);

						$templateLinkage[] = $current_template;
					}

					$result = API::Template()->massAdd(array(
						'hosts' => array('hostid' => $current_hostid),
						'templates' => $templateLinkage
					));
					if (!$result) {
						throw new Exception();
					}
				}
// }}} TEMPLATES

// ITEMS {{{
				if (!empty($rules['items']['updateExisting']) || !empty($rules['items']['createMissing'])) {
					$items = $xpath->query('items/item', $host);

					// if this is an export from 1.8, we need to make some adjustments to items
					if ($old_version_input) {
						if (!$interfaces_created_with_host) {
							// if host had another interfaces, we are not touching them: they remain as is
							foreach ($interfaces as $i => $interface) {
								// interface was not already created
								if (!isset($interface['interfaceid'])) {
									// creating interface
									$interface['hostid'] = $current_hostid;
									$ids = API::HostInterface()->create($interface);
									if ($ids === false) {
										throw new Exception();
									}
									$interfaces[$i]['interfaceid'] = reset($ids['interfaceids']);
								}
							}
						}
						else {
							$options = array(
								'hostids' => $current_hostid,
								'output' => API_OUTPUT_EXTEND
							);
							$interfaces = API::HostInterface()->get($options);
						}


						// we must know interface ids to assign them to items
						$agent_interface_id = null;
						$ipmi_interface_id = null;
						$snmp_interfaces = array(); // hash 'port' => 'iterfaceid'

						foreach ($interfaces as $interface) {
							switch ($interface['type']) {
								case INTERFACE_TYPE_AGENT:
									$agent_interface_id = $interface['interfaceid'];
									break;
								case INTERFACE_TYPE_IPMI:
									$ipmi_interface_id = $interface['interfaceid'];
									break;
								case INTERFACE_TYPE_SNMP:
									$snmp_interfaces[$interface['port']] = $interface['interfaceid'];
									break;
							}
						}
					}

					foreach ($items as $item) {
						$item_db = self::mapXML2arr($item, XML_TAG_ITEM);
						$item_db['hostid'] = $current_hostid;

						// item needs interfaces
						if ($old_version_input) {
							// 'snmp_port' column was renamed to 'port'
							if ($item_db['snmp_port'] != 0) {
								// zabbix agent items have no ports
								$item_db['port'] = $item_db['snmp_port'];
							}
							unset($item_db['snmp_port']);

							// assigning appropriate interface depending on item type
							switch ($item_db['type']) {
								// zabbix agent interface
								case ITEM_TYPE_ZABBIX:
								case ITEM_TYPE_SIMPLE:
								case ITEM_TYPE_EXTERNAL:
								case ITEM_TYPE_SSH:
								case ITEM_TYPE_TELNET:
									$item_db['interfaceid'] = $agent_interface_id;
									break;
								// snmp interface
								case ITEM_TYPE_SNMPV1:
								case ITEM_TYPE_SNMPV2C:
								case ITEM_TYPE_SNMPV3:
									// for an item with different port - different interface
									$item_db['interfaceid'] = $snmp_interfaces[$item_db['port']];
									break;
								case ITEM_TYPE_IPMI:
									$item_db['interfaceid'] = $ipmi_interface_id;
									break;
								// no interfaces required for these item types
								case ITEM_TYPE_HTTPTEST:
								case ITEM_TYPE_CALCULATED:
								case ITEM_TYPE_AGGREGATE:
								case ITEM_TYPE_INTERNAL:
								case ITEM_TYPE_ZABBIX_ACTIVE:
								case ITEM_TYPE_TRAPPER:
								case ITEM_TYPE_DB_MONITOR:
									$item_db['interfaceid'] = null;
									break;
							}

							$item_db['key_'] = self::convertOldSimpleKey($item_db['key_']);
						}

						$options = array(
							'filter' => array(
								'hostid' => $item_db['hostid'],
								'key_' => $item_db['key_'],
								'flags' => array(
									ZBX_FLAG_DISCOVERY_NORMAL,
									ZBX_FLAG_DISCOVERY_CREATED
								),
							),
							'webitems' => 1,
							'output' => API_OUTPUT_EXTEND,
							'editable' => 1
						);
						$current_item = API::Item()->get($options);
						$current_item = reset($current_item);

						if (!$current_item && empty($rules['items']['createMissing'])) {
							info(_s('Item "%1$s" skipped - user rule.', $item_db['key_']));
							continue; // break if not update updateExisting
						}
						if ($current_item && empty($rules['items']['updateExisting'])) {
							info(_s('Item "%1$s" skipped - user rule.', $item_db['key_']));
							continue; // break if not update updateExisting
						}


// ITEM APPLICATIONS {{{
						$applications = $xpath->query('applications/application', $item);

						$item_applications = array();
						$applications_to_add = array();
						$applicationsIds = array();

						foreach ($applications as $application) {
							$application_db = array(
								'name' => $application->nodeValue,
								'hostid' => $current_hostid
							);

							$current_application = API::Application()->get(array(
								'filter' => $application_db,
								'output' => API_OUTPUT_EXTEND
							));

							$applicationValue = reset($current_application);

							if ($current_application) {
								if (empty($item_applications)) {
									$item_applications = $current_application;
									$applicationsIds[] = $applicationValue['applicationid'];
								}
								else {
									if (!in_array($applicationValue['applicationid'], $applicationsIds)) {
										$item_applications = array_merge($item_applications, $current_application);
										$applicationsIds[] = $applicationValue['applicationid'];
									}
								}
							}
							else {
								$applications_to_add[] = $application_db;
							}
						}

						if (!empty($applications_to_add)) {
							$result = API::Application()->create($applications_to_add);
							if (!$result) {
								throw new Exception();
							}

							$options = array(
								'applicationids' => $result['applicationids'],
								'output' => API_OUTPUT_EXTEND
							);
							$new_applications = API::Application()->get($options);

							$item_applications = array_merge($item_applications, $new_applications);
						}
// }}} ITEM APPLICATIONS

						if ($current_item && !empty($rules['items']['updateExisting'])) {
							$item_db['itemid'] = $current_item['itemid'];
							$result = API::Item()->update($item_db);
							if (!$result) {
								throw new Exception();
							}

							$options = array(
								'itemids' => $result['itemids'],
								'webitems' => 1,
								'output' => API_OUTPUT_EXTEND
							);
							$current_item = API::Item()->get($options);
						}


						if (!$current_item && !empty($rules['items']['createMissing'])) {
							$result = API::Item()->create($item_db);
							if (!$result) {
								throw new Exception();
							}

							$options = array(
								'itemids' => $result['itemids'],
								'webitems' => 1,
								'output' => API_OUTPUT_EXTEND
							);
							$current_item = API::Item()->get($options);
						}

						if (!empty($item_applications)) {
							$r = API::Application()->massAdd(array(
								'applications' => $item_applications,
								'items' => $current_item
							));
							if ($r === false) {
								throw new Exception();
							}
						}
					}
				}
// }}} ITEMS


// TRIGGERS {{{
				if (!empty($rules['triggers']['updateExisting']) || !empty($rules['triggers']['createMissing'])) {
					$triggers = $xpath->query('triggers/trigger', $host);

					$triggers_to_add = array();
					$triggers_to_upd = array();

					foreach ($triggers as $trigger) {
						$trigger_db = self::mapXML2arr($trigger, XML_TAG_TRIGGER);

						if ($old_version_input) {
							$expressionPart = explode(':', $trigger_db['expression']);
							$keyName = explode(',', $expressionPart[1], 2);

							if (count($keyName) == 2) {
								$keyValue = explode('.', $keyName[1], 2);
								$key = $keyName[0].",".$keyValue[0];

								if (in_array($keyName[0], self::$oldKeys) || in_array($keyName[0], self::$oldKeysPref)) {
									$trigger_db['expression'] = str_replace($key, self::convertOldSimpleKey($key), $trigger_db['expression']);
								}
							}
						}

						// {HOSTNAME} is here for backward compatibility
						$trigger_db['expression'] = str_replace('{{HOSTNAME}:', '{'.$host_db['host'].':', $trigger_db['expression']);
						$trigger_db['expression'] = str_replace('{{HOST.HOST}:', '{'.$host_db['host'].':', $trigger_db['expression']);
						$trigger_db['hostid'] = $current_hostid;

						if ($current_trigger = API::Trigger()->exists($trigger_db)) {
							$ctriggers = API::Trigger()->get(array(
								'filter' => array(
									'description' => $trigger_db['description']
								),
								'hostids' => $current_hostid,
								'output' => API_OUTPUT_EXTEND,
								'editable' => 1
							));

							$current_trigger = false;
							foreach ($ctriggers as $ct) {
								$tmp_exp = explode_exp($ct['expression']);
								if (strcmp($trigger_db['expression'], $tmp_exp) == 0) {
									$current_trigger = $ct;
									break;
								}
							}
							if (!$current_trigger) {
								throw new Exception(_s('No permission for trigger "%s".', $trigger_db['description']));
							}
						}
						unset($trigger_db['hostid']);


						if (!$current_trigger && empty($rules['triggers']['createMissing'])) {
							info(_s('Trigger "%1$s" skipped - user rule.', $trigger_db['description']));
							continue; // break if not update updateExisting
						}
						if ($current_trigger && empty($rules['triggers']['updateExisting'])) {
							info(_s('Trigger "%1$s" skipped - user rule.', $trigger_db['description']));
							continue; // break if not update updateExisting
						}

						if ($current_trigger && !empty($rules['triggers']['updateExisting'])) {
							$trigger_db['triggerid'] = $current_trigger['triggerid'];
							$triggers_to_upd[] = $trigger_db;
						}
						if (!$current_trigger && !empty($rules['triggers']['createMissing'])) {
							$triggers_to_add[] = $trigger_db;
						}
					}

					if (!empty($triggers_to_upd)) {
						$result = API::Trigger()->update($triggers_to_upd);
						if (!$result) {
							throw new Exception();
						}

						$options = array(
							'triggerids' => $result['triggerids'],
							'output' => API_OUTPUT_EXTEND
						);
						$r = API::Trigger()->get($options);

						$triggersForDependencies = array_merge($triggersForDependencies, $r);
					}
					if (!empty($triggers_to_add)) {
						$result = API::Trigger()->create($triggers_to_add);
						if (!$result) {
							throw new Exception();
						}

						$options = array(
							'triggerids' => $result['triggerids'],
							'output' => API_OUTPUT_EXTEND
						);
						$r = API::Trigger()->get($options);
						$triggersForDependencies = array_merge($triggersForDependencies, $r);
					}
				}
// }}} TRIGGERS


// GRAPHS {{{
				if (!empty($rules['graphs']['updateExisting']) || !empty($rules['graphs']['createMissing'])) {
					$graphs = $xpath->query('graphs/graph', $host);

					$graphs_to_add = array();
					$graphs_to_upd = array();
					foreach ($graphs as $graph) {
// GRAPH ITEMS {{{
						$gitems = $xpath->query('graph_elements/graph_element', $graph);

						$graph_hostids = array();
						$graph_items = array();
						foreach ($gitems as $gitem) {
							$gitem_db = self::mapXML2arr($gitem, XML_TAG_GRAPH_ELEMENT);

							$data = explode(':', $gitem_db['host_key_']);
							$gitem_host = array_shift($data);
							// {HOSTNAME} is here for backward compatibility
							$gitem_db['host'] = ($gitem_host == '{HOSTNAME}') ? $host_db['host'] : $gitem_host;
							$gitem_db['host'] = ($gitem_host == '{HOST.HOST}') ? $host_db['host'] : $gitem_host;
							if ($old_version_input) {
								$data[0] = self::convertOldSimpleKey($data[0]);
							}
							$gitem_db['key_'] = implode(':', $data);

							if ($current_item = API::Item()->exists($gitem_db)) {
								$current_item = API::Item()->get(array(
									'filter' => array(
										'key_' => $gitem_db['key_'],
										'flags' => array(
											ZBX_FLAG_DISCOVERY_NORMAL,
											ZBX_FLAG_DISCOVERY_CREATED
										),
									),
									'webitems' => 1,
									'host' => $gitem_db['host'],
									'output' => API_OUTPUT_EXTEND,
									'editable' => 1
								));
								if (empty($current_item)) {
									throw new Exception(_s('No permission for item "%1$s".', $gitem_db['key_']));
								}
								$current_item = reset($current_item);

								$graph_hostids[] = $current_item['hostid'];
								$gitem_db['itemid'] = $current_item['itemid'];
								$graph_items[] = $gitem_db;
							}
							else {
								throw new Exception(_s('Item "%1$s" does not exist.', $gitem_db['host_key_']));
							}
						}
// }}} GRAPH ITEMS

						$graph_db = self::mapXML2arr($graph, XML_TAG_GRAPH);
						$graph_db['hostids'] = $graph_hostids;


						// do we need to show the graph legend, after it is imported?
						// in 1.8, this setting was present only for pie and exploded graphs
						// for other graph types we are always showing the legend
						if ($graph_db['graphtype'] != GRAPH_TYPE_PIE && $graph_db['graphtype'] != GRAPH_TYPE_EXPLODED) {
							$graph_db['show_legend'] = 1;
						}

						$current_graph = API::Graph()->exists($graph_db);

						if ($current_graph) {
							$current_graph = API::Graph()->get(array(
								'filter' => array('name' => $graph_db['name']),
								'hostids' => $graph_db['hostids'],
								'output' => API_OUTPUT_EXTEND,
								'editable' => 1
							));

							if (empty($current_graph)) {
								throw new Exception(_s('No permission for graph "%1$s".', $graph_db['name']));
							}
							$current_graph = reset($current_graph);
						}

						if (!$current_graph && empty($rules['graphs']['createMissing'])) {
							info(_s('Graph "%1$s" skipped - user rule.', $graph_db['name']));
							continue; // break if not update updateExisting
						}
						if ($current_graph && empty($rules['graphs']['updateExisting'])) {
							info(_s('Graph "%1$s" skipped - user rule.', $graph_db['name']));
							continue; // break if not update updateExisting
						}

						if (!isset($graph_db['ymin_type'])) {
							throw new Exception(_s('No "ymin_type" field for graph "%s".', $graph_db['name']));
						}

						if (!isset($graph_db['ymax_type'])) {
							throw new Exception(_s('No "ymax_type" field for graph "%s".', $graph_db['name']));
						}

						if ($graph_db['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
							$item_data = explode(':', $graph_db['ymin_item_key'], 2);
							if (count($item_data) < 2) {
								throw new Exception(_s('Incorrect y min item for graph "%1$s".', $graph_db['name']));
							}

							if (!$item = get_item_by_key($item_data[1], $item_data[0])) {
								throw new Exception(_s('Missing item "%1$s" for host "%2$s".', $graph_db['ymin_item_key'], $host_db['host']));
							}

							$graph_db['ymin_itemid'] = $item['itemid'];
						}

						if ($graph_db['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
							$item_data = explode(':', $graph_db['ymax_item_key'], 2);
							if (count($item_data) < 2) {
								throw new Exception(_s('Incorrect y max item for graph "%1$s".', $graph_db['name']));
							}

							if (!$item = get_item_by_key($item_data[1], $item_data[0])) {
								throw new Exception(_s('Missing item "%1$s" for host "%2$s".', $graph_db['ymax_item_key'], $host_db['host']));
							}

							$graph_db['ymax_itemid'] = $item['itemid'];
						}


						$graph_db['gitems'] = $graph_items;
						if ($current_graph) {
							$graph_db['graphid'] = $current_graph['graphid'];
							$graphs_to_upd[] = $graph_db;
						}
						else {
							$graphs_to_add[] = $graph_db;
						}
					}

					if (!empty($graphs_to_add)) {
						$r = API::Graph()->create($graphs_to_add);
						if ($r === false) {
							throw new Exception();
						}
					}
					if (!empty($graphs_to_upd)) {
						$r = API::Graph()->update($graphs_to_upd);
						if ($r === false) {
							throw new Exception();
						}
					}
				}

// SCREENS
				if (!empty($rules['screens']['updateExisting']) || !empty($rules['screens']['createMissing'])) {
					$screens_node = $xpath->query('screens', $host);

					if ($screens_node->length > 0) {
						$importScreens = self::XMLtoArray($screens_node->item(0));

						foreach ($importScreens as $screen) {

							$current_screen = API::TemplateScreen()->get(array(
								'filter' => array('name' => $screen['name']),
								'templateids' => $current_hostid,
								'output' => API_OUTPUT_EXTEND,
								'editable' => 1,
							));
							$current_screen = reset($current_screen);

							if (!$current_screen && empty($rules['screens']['createMissing'])) {
								info(_s('Screen "%1$s" skipped - user rule.', $screen['name']));
								continue;
							}
							if ($current_screen && empty($rules['screens']['updateExisting'])) {
								info(_s('Screen "%1$s" skipped - user rule.', $screen['name']));
								continue;
							}

							if (isset($screen['screenitems'])) {
								foreach ($screen['screenitems'] as &$screenitem) {
									$nodeCaption = isset($screenitem['resourceid']['node'])
											? $screenitem['resourceid']['node'].':' : '';

									if (!isset($screenitem['resourceid'])) {
										$screenitem['resourceid'] = 0;
									}

									if (is_array($screenitem['resourceid'])) {
										switch ($screenitem['resourcetype']) {
											case SCREEN_RESOURCE_GRAPH:
												$db_graphs = API::Graph()->getObjects($screenitem['resourceid']);

												if (empty($db_graphs)) {
													$error = _s('Cannot find graph "%1$s" used in screen "%2$s".',
															$nodeCaption.$screenitem['resourceid']['host'].':'.$screenitem['resourceid']['name'], $screen['name']);
													throw new Exception($error);
												}

												$tmp = reset($db_graphs);
												$screenitem['resourceid'] = $tmp['graphid'];
												break;
											case SCREEN_RESOURCE_SIMPLE_GRAPH:
											case SCREEN_RESOURCE_PLAIN_TEXT:
												$db_items = API::Item()->getObjects($screenitem['resourceid']);

												if (empty($db_items)) {
													$error = _s('Cannot find item "%1$s" used in screen "%2$s".',
															$nodeCaption.$screenitem['resourceid']['host'].':'.$screenitem['resourceid']['key_'], $screen['name']);
													throw new Exception($error);
												}

												$tmp = reset($db_items);
												$screenitem['resourceid'] = $tmp['itemid'];
												break;
											default:
												$screenitem['resourceid'] = 0;
												break;
										}
									}
								}
							}

							$screen['templateid'] = $current_hostid;
							if ($current_screen) {
								$screen['screenid'] = $current_screen['screenid'];

								$result = API::TemplateScreen()->update($screen);
								if (!$result) {
									throw new Exception(_('Cannot update screen.'));
								}

								info('['.$current_hostname.'] '._s('Screen "%1$s" updated.', $screen['name']));
							}
							else {
								$result = API::TemplateScreen()->create($screen);
								if (!$result) {
									throw new Exception(_('Cannot create screen.'));
								}

								info('['.$current_hostname.'] '._s('Screen "%1$s" added.', $screen['name']));
							}
						}
					}
				}

			}

// DEPENDENCIES
			$dependencies = $xpath->query('dependencies/dependency');

			if ($dependencies->length > 0) {
				$triggersForDependencies = zbx_objectValues($triggersForDependencies, 'triggerid');
				$triggersForDependencies = array_flip($triggersForDependencies);
				$newDependencies = array();
				foreach ($dependencies as $dependency) {

					$triggerDescription = $dependency->getAttribute('description');
					$currentTrigger = get_trigger_by_description($triggerDescription);

					if ($currentTrigger && isset($triggersForDependencies[$currentTrigger['triggerid']])) {
						$dependsOnList = $xpath->query('depends', $dependency);

						foreach ($dependsOnList as $dependsOn) {
							$depTrigger = get_trigger_by_description($dependsOn->nodeValue);
							if ($depTrigger['triggerid']) {
								$newDependencies[] = array(
									'triggerid' => $currentTrigger['triggerid'],
									'dependsOnTriggerid' => $depTrigger['triggerid']
								);
							}
						}
					}
				}

				if ($newDependencies) {
					API::Trigger()->addDependencies($newDependencies);
				}
			}
		}
	}
}
