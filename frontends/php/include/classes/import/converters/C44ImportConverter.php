<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Converter for converting import data from 4.4 to 5.0.
 */
class C44ImportConverter extends CConverter {

	public function convert($data) {
		$data['zabbix_export']['version'] = '5.0';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export'] = $this->convertSnmpFieldsToInterfaces($data['zabbix_export']);
		}

		$data['zabbix_export'] = $this->deleteSnmpFields($data['zabbix_export']);

		return $data;
	}

	/**
	 * Unset all SNMP fields from items, discovery rules and item prototypes in hosts and templates.
	 *
	 * @todo This function can be rewrited to use array_filter if minimum php >= 5.6.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function deleteSnmpFields(array $data) {
		if (array_key_exists('hosts', $data)) {
			foreach ($data['hosts'] as &$host) {
				if (array_key_exists('items', $host)) {
					foreach ($host['items'] as &$item) {
						unset($item['snmp_community']);
						unset($item['snmpv3_contextname']);
						unset($item['snmpv3_securityname']);
						unset($item['snmpv3_securitylevel']);
						unset($item['snmpv3_authprotocol']);
						unset($item['snmpv3_authpassphrase']);
						unset($item['snmpv3_privprotocol']);
						unset($item['snmpv3_privpassphrase']);
						unset($item['port']);
					}
					unset($item);
				}

				if (array_key_exists('discovery_rules', $host)) {
					foreach ($host['discovery_rules'] as &$drule) {
						unset($drule['snmp_community']);
						unset($drule['snmpv3_contextname']);
						unset($drule['snmpv3_securityname']);
						unset($drule['snmpv3_securitylevel']);
						unset($drule['snmpv3_authprotocol']);
						unset($drule['snmpv3_authpassphrase']);
						unset($drule['snmpv3_privprotocol']);
						unset($drule['snmpv3_privpassphrase']);
						unset($drule['port']);

						if (array_key_exists('item_prototypes', $drule)) {
							foreach ($drule['item_prototypes'] as &$prototype) {
								unset($prototype['snmp_community']);
								unset($prototype['snmpv3_contextname']);
								unset($prototype['snmpv3_securityname']);
								unset($prototype['snmpv3_securitylevel']);
								unset($prototype['snmpv3_authprotocol']);
								unset($prototype['snmpv3_authpassphrase']);
								unset($prototype['snmpv3_privprotocol']);
								unset($prototype['snmpv3_privpassphrase']);
								unset($prototype['port']);
							}
							unset($prototype);
						}
					}
					unset($drule);
				}
			}
			unset($host);
		}

		if (array_key_exists('templates', $data)) {
			foreach ($data['templates'] as &$template) {
				if (array_key_exists('items', $template)) {
					foreach ($template['items'] as &$item) {
						unset($item['snmp_community']);
						unset($item['snmpv3_contextname']);
						unset($item['snmpv3_securityname']);
						unset($item['snmpv3_securitylevel']);
						unset($item['snmpv3_authprotocol']);
						unset($item['snmpv3_authpassphrase']);
						unset($item['snmpv3_privprotocol']);
						unset($item['snmpv3_privpassphrase']);
						unset($item['port']);

						if (array_key_exists('type', $item)
								&& in_array($item['type'], [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3])) {
							$item['type'] = CXmlConstantName::SNMP_AGENT;
						}
					}
					unset($item);
				}

				if (array_key_exists('discovery_rules', $template)) {
					foreach ($template['discovery_rules'] as &$drule) {
						unset($drule['snmp_community']);
						unset($drule['snmpv3_contextname']);
						unset($drule['snmpv3_securityname']);
						unset($drule['snmpv3_securitylevel']);
						unset($drule['snmpv3_authprotocol']);
						unset($drule['snmpv3_authpassphrase']);
						unset($drule['snmpv3_privprotocol']);
						unset($drule['snmpv3_privpassphrase']);
						unset($drule['port']);

						if (array_key_exists('type', $drule)
								&& in_array($drule['type'], [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3])) {
							$drule['type'] = CXmlConstantName::SNMP_AGENT;
						}

						if (array_key_exists('item_prototypes', $drule)) {
							foreach ($drule['item_prototypes'] as &$prototype) {
								unset($prototype['snmp_community']);
								unset($prototype['snmpv3_contextname']);
								unset($prototype['snmpv3_securityname']);
								unset($prototype['snmpv3_securitylevel']);
								unset($prototype['snmpv3_authprotocol']);
								unset($prototype['snmpv3_authpassphrase']);
								unset($prototype['snmpv3_privprotocol']);
								unset($prototype['snmpv3_privpassphrase']);
								unset($prototype['port']);

								if (array_key_exists('type', $prototype)
										&& in_array($prototype['type'], [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3])) {
									$prototype['type'] = CXmlConstantName::SNMP_AGENT;
								}
							}
							unset($prototype);
						}
					}
					unset($drule);
				}
			}
			unset($template);
		}

		return $data;
	}

	/**
	 * Get interface fields with default values.
	 * Because in XML can import interface that contain only interface_ref field.
	 *
	 * @param array $interface
	 *
	 * @return array
	 */
	protected function getDefaultInterfaceArray(array $interface) {
		return $interface + [
			'default' => CXmlConstantName::YES,
			'type' => CXmlConstantName::SNMP,
			'useip' => CXmlConstantName::YES,
			'ip' => '127.0.0.1',
			'dns' => '',
			'port' => '10050'
		];
	}

	/**
	 * Create helper array for interfaces.
	 *
	 * @param array $data
	 * @param int   $type array type; 1 - item, 2 - discovery rule, 3 - item prototype
	 *
	 * @return array
	 */
	protected function createHelperArray(array $data, $type) {
		return [
			'from' => $type,
			'name' => $data['name'],
			'ref' => $data['interface_ref'],
			'port' => array_key_exists('port', $data) ? $data['port'] : '',
			'type' => $data['type'],
			'community' => ($data['type'] === CXmlConstantName::SNMPV1 || $data['type'] === CXmlConstantName::SNMPV2)
				? (array_key_exists('snmp_community', $data) ? $data['snmp_community'] : '')
				: '',
			'contextname' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_contextname', $data) ? $data['snmpv3_contextname'] : '')
				: '',
			'securityname' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_securityname', $data) ? $data['snmpv3_securityname'] : '')
				: '',
			'securitylevel' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_securitylevel', $data) ? $data['snmpv3_securitylevel'] : CXmlConstantName::NOAUTHNOPRIV)
				: '',
			'authprotocol' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_authprotocol', $data) ? $data['snmpv3_authprotocol'] : CXmlConstantName::MD5)
				: '',
			'authpassphrase' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_authpassphrase', $data) ? $data['snmpv3_authpassphrase'] : '')
				: '',
			'privprotocol' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_privprotocol', $data) ? $data['snmpv3_privprotocol'] : CXmlConstantName::DES)
				: '',
			'privpassphrase' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_privpassphrase', $data) ? $data['snmpv3_privpassphrase'] : '')
				: '',
		];
	}

	/**
	 * Get next interface key.
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	protected function getInterfaceKey(array $data) {
		// Check zero element.
		if (!array_key_exists('interface', $data['interfaces'])) {
			return 'interface';
		}

		// Check for next missed element.
		$number = 1;
		while (true) {
			if (!array_key_exists('interface'.$number, $data['interfaces'])) {
				return 'interface'.$number;
			}
			$number++;
		}
	}

	/**
	 * Convert SNMP fields from host items, discovery rules and item prototypes to interfaces.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function convertSnmpFieldsToInterfaces(array $data) {
		// Max interfaceid for new interfaces.
		$max_interfaceid = 1;

		foreach ($data['hosts'] as &$host) {
			// Store item values related to interface.
			$items_interface = [];
			// Store new interfaces.
			$new_interfaces = [];
			// Store itemid that we need update interface_ref.
			$update_items = [];

			if (array_key_exists('items', $host)) {
				// Getting all SNMP items and their interfacces.
				foreach ($host['items'] as &$item) {
					if (array_key_exists('type', $item)
							&& in_array($item['type'], [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3])) {
						$interfaceid = str_replace('if', '', $item['interface_ref']);

						$items_interface[$interfaceid][] = $this->createHelperArray($item, 1);

						// Change item type to SNMP agent.
						$item['type'] = CXmlConstantName::SNMP_AGENT;
					}
				}
				unset($item);
			}

			if (array_key_exists('discovery_rules', $host)) {
				foreach ($host['discovery_rules'] as &$drule) {
					if (array_key_exists('type', $drule)
							&& in_array($drule['type'], [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3])) {
						$interfaceid = str_replace('if', '', $drule['interface_ref']);

						$items_interface[$interfaceid][] = $this->createHelperArray($drule, 2);

						// Change item type to SNMP agent.
						$drule['type'] = CXmlConstantName::SNMP_AGENT;
					}

					if (array_key_exists('item_prototypes', $drule)) {
						/**
						 * @var array $prototype
						 */
						foreach ($drule['item_prototypes'] as &$prototype) {
							if (array_key_exists('type', $prototype)
									&& in_array($prototype['type'], [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3])) {
								$interfaceid = str_replace('if', '', $drule['interface_ref']);

								$items_interface[$interfaceid][] = $this->createHelperArray($prototype, 3);

								// Change item type to SNMP agent.
								$prototype['type'] = CXmlConstantName::SNMP_AGENT;
							}
						}
						unset($prototype);
					}
				}
				unset($drule);
			}

			// Getting all interfaces.
			if (array_key_exists('interfaces', $host)) {
				// Get max interface id.
				foreach ($host['interfaces'] as $key => $interface) {
					$interfaceid = str_replace('if', '', $interface['interface_ref']);

					if ($interfaceid > $max_interfaceid) {
						$max_interfaceid = $interfaceid;
					}
				}

				foreach ($host['interfaces'] as $key => &$interface) {
					$interfaceid = str_replace('if', '', $interface['interface_ref']);

					// Working only with SNMP interfaces.
					if (array_key_exists('type', $interface) && $interface['type'] === CXmlConstantName::SNMP) {
						// Save bulk value.
						$interface['details'] = [
							'bulk' => array_key_exists('bulk', $interface) ? $interface['bulk'] : CXmlConstantName::YES,
						];
						unset($interface['bulk']);

						$standard_interface = $this->getDefaultInterfaceArray($interface);

						// Check if interface used in items.
						if (array_key_exists($interfaceid, $items_interface)) {
							// Copy interface as new and mapping him with parent interface.
							$new_interfaces[$interfaceid][] = ['interface_ref' => 'if'.(++$max_interfaceid), 'new' => true] + $standard_interface;

							// Walk through all items for this interface.
							foreach ($items_interface[$interfaceid] as $item) {
								// Set SNMP version from first item.
								foreach ($new_interfaces[$interfaceid] as &$iface) {
									if (array_key_exists('new', $iface)) {
										$iface['details']['version'] = $item['type'];

										// Item port not set here because we will find it in next steps.

										// And set others snmp fields
										if ($item['type'] === CXmlConstantName::SNMPV1 || $item['type'] === CXmlConstantName::SNMPV2) {
											$iface['details']['community'] = $item['community'];
										}

										if ($item['type'] === CXmlConstantName::SNMPV3) {
											$iface['details']['contextname'] = $item['contextname'];
											$iface['details']['securityname'] = $item['securityname'];
											$iface['details']['securitylevel'] = $item['securitylevel'];
											$iface['details']['authprotocol'] = $item['authprotocol'];
											$iface['details']['authpassphrase'] = $item['authpassphrase'];
											$iface['details']['privprotocol'] = $item['privprotocol'];
											$iface['details']['privpassphrase'] = $item['privpassphrase'];
										}

										unset($iface['new']);
										break;
									}
								}
								unset($iface);

								// Find interfaces with same version.
								$type_interfaces = array_filter($new_interfaces[$interfaceid], function ($iface) use ($item) {
									return $iface['details']['version'] === $item['type'];
								});

								if ($type_interfaces) {
									$same_interfaces = array_filter($type_interfaces, function ($iface) use ($item, $standard_interface) {
										// If item port diff from interface ports it is 100% new interface.
										if ($item['port'] === '') {
											// Item port not set and interface port not equel parent port.
											if ($iface['port'] !== $standard_interface['port']) {
												return false;
											}
										}
										else {
											// If item port not equel interface ports it is 100% new interface.
											if ($iface['port'] !== $item['port']) {
												return false;
											}
										}

										// If community equel between item it is our interface.
										if ($item['type'] === CXmlConstantName::SNMPV1 || $item['type'] === CXmlConstantName::SNMPV2) {
											return $iface['details']['community'] === $item['community'];
										}

										// The same.
										if ($item['type'] === CXmlConstantName::SNMPV3) {
											return $iface['details']['contextname'] === $item['contextname'] &&
												$iface['details']['securityname'] === $item['securityname'] &&
												$iface['details']['securitylevel'] === $item['securitylevel'] &&
												$iface['details']['authprotocol'] === $item['authprotocol'] &&
												$iface['details']['authpassphrase'] === $item['authpassphrase'] &&
												$iface['details']['privprotocol'] === $item['privprotocol'] &&
												$iface['details']['privpassphrase'] === $item['privpassphrase'];
										}
									});

									if ($same_interfaces) {
										$iface = current($same_interfaces);
										$update_items[$item['from']][$item['name']] = $iface['interface_ref'];
									}
									else {
										// Create new interface if not found.
										$new_interface = ['interface_ref' => 'if'.(++$max_interfaceid)] + $standard_interface;

										$new_interface['details']['version'] = $item['type'];

										// Set item port if have.
										if ($item['port'] !== '') {
											$new_interface['port'] = $item['port'];
										}

										if ($item['type'] === CXmlConstantName::SNMPV1 || $item['type'] === CXmlConstantName::SNMPV2) {
											$new_interface['details']['community'] = $item['community'];
										}

										if ($item['type'] === CXmlConstantName::SNMPV3) {
											$new_interface['details']['contextname'] = $item['contextname'];
											$new_interface['details']['securityname'] = $item['securityname'];
											$new_interface['details']['securitylevel'] = $item['securitylevel'];
											$new_interface['details']['authprotocol'] = $item['authprotocol'];
											$new_interface['details']['authpassphrase'] = $item['authpassphrase'];
											$new_interface['details']['privprotocol'] = $item['privprotocol'];
											$new_interface['details']['privpassphrase'] = $item['privpassphrase'];
										}

										$new_interfaces[$interfaceid][] = $new_interface;

										$update_items[$item['from']][$item['name']] = $new_interface['interface_ref'];
									}
								}
								else {
									// Create new interface if not found with same type
									$new_interface = ['interface_ref' => 'if'.(++$max_interfaceid)] + $standard_interface;

									$new_interface['details']['version'] = $item['type'];

									// Set item port if have.
									if ($item['port'] !== '') {
										$new_interface['port'] = $item['port'];
									}

									if ($item['type'] === CXmlConstantName::SNMPV1 || $item['type'] === CXmlConstantName::SNMPV2) {
										$new_interface['details']['community'] = $item['community'];
									}

									if ($item['type'] === CXmlConstantName::SNMPV3) {
										$new_interface['details']['contextname'] = $item['contextname'];
										$new_interface['details']['securityname'] = $item['securityname'];
										$new_interface['details']['securitylevel'] = $item['securitylevel'];
										$new_interface['details']['authprotocol'] = $item['authprotocol'];
										$new_interface['details']['authpassphrase'] = $item['authpassphrase'];
										$new_interface['details']['privprotocol'] = $item['privprotocol'];
										$new_interface['details']['privpassphrase'] = $item['privpassphrase'];
									}

									$new_interfaces[$interfaceid][] = $new_interface;

									$update_items[$item['from']][$item['name']] = $new_interface['interface_ref'];
								}
							}
						}
						else {
							// Interface not used in items. Create with default values.
							$new_interfaces[$interfaceid][] = ['details' => $standard_interface['details'] + ['version' => CXmlConstantName::SNMPV2, 'community' => '{$SNMP_COMMUNITY}']] + $standard_interface;
						}

						// Delete original interface, because we create new.
						unset($host['interfaces'][$key]);
						continue;
					}

					unset($interface['bulk']);
				}
			}

			// Update interface_ref.
			if (array_key_exists('items', $host) && array_key_exists('1', $update_items)) {
				foreach ($host['items'] as &$item) {
					if (array_key_exists($item['name'], $update_items[1])) {
						$item['interface_ref'] = $update_items[1][$item['name']];
					}
				}
				unset($item);
			}
			if (array_key_exists('discovery_rules', $host) && array_key_exists('2', $update_items)) {
				foreach ($host['discovery_rules'] as &$drule) {
					if (array_key_exists($drule['name'], $update_items[2])) {
						$drule['interface_ref'] = $update_items[2][$drule['name']];
					}

					if (array_key_exists('item_prototypes', $drule) && array_key_exists('3', $update_items)) {
						foreach ($drule['item_prototypes'] as &$prototype) {
							if (array_key_exists($prototype['name'], $update_items[3])) {
								$prototype['interface_ref'] = $update_items[3][$prototype['name']];
							}
						}
						unset($prototype);
					}
				}
				unset($drule);
			}

			// Add all new interfaces to host interfaces.
			foreach ($new_interfaces as $interfaces) {
				foreach($interfaces as $interface) {
					$host['interfaces'][$this->getInterfaceKey($host)] = $interface;
				}
			}

			// Set proper default field for interfaces.
			if (array_key_exists('interfaces', $host)) {
				$main = false;
				foreach ($host['interfaces'] as &$interface) {
					if (array_key_exists('type', $interface) && $interface['type'] === CXmlConstantName::SNMP) {
						if ($main) {
							$interface['default'] = CXmlConstantName::NO;
							continue;
						}

						if (!array_key_exists('default', $interface)) {
							$main = true;
						}
						else {
							if ($interface['default'] === CXmlConstantName::YES) {
								$main = true;
							}
						}
					}
				}
				unset($interface);
			}
		}
		unset($host);

		return $data;
	}
}
