<?php declare(strict_types = 0);
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
 * Converter for converting import data from 4.4 to 5.0.
 */
class C44ImportConverter extends CConverter {


	/**
	 * Update types.
	 */
	const TYPE_ITEM = 1;
	const TYPE_DISCOVERY_RULE = 2;
	const TYPE_ITEM_PROTOTYPE = 3;

	/**
	 * Convert import data from 4.4 to 5.0 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert($data): array {
		$data['zabbix_export']['version'] = '5.0';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertSnmpFieldsToInterfaces($data['zabbix_export']['hosts']);
		}

		$data['zabbix_export'] = $this->sanitizeSnmpFields($data['zabbix_export']);

		return $data;
	}

	/**
	 * Set SNMP_AGENT type and unset all SNMP fields from items, discovery rules and item prototypes
	 * in hosts and templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function sanitizeSnmpFields(array $data): array {
		$fields = ['snmp_community', 'snmpv3_contextname', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authprotocol', 'snmpv3_authpassphrase', 'snmpv3_privprotocol', 'snmpv3_privpassphrase', 'port'
		];
		$types = [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3];

		foreach (['hosts', 'templates'] as $tag) {
			if (array_key_exists($tag, $data)) {
				foreach ($data[$tag] as &$value) {
					if (array_key_exists('items', $value)) {
						foreach ($value['items'] as &$item) {
							foreach ($fields as $field) {
								unset($item[$field]);
							}

							if (array_key_exists('type', $item) && in_array($item['type'], $types)) {
								$item['type'] = CXmlConstantName::SNMP_AGENT;
							}
						}
						unset($item);
					}

					if (array_key_exists('discovery_rules', $value)) {
						foreach ($value['discovery_rules'] as &$drule) {
							foreach ($fields as $field) {
								unset($drule[$field]);
							}

							if (array_key_exists('type', $drule) && in_array($drule['type'], $types)) {
								$drule['type'] = CXmlConstantName::SNMP_AGENT;
							}

							if (array_key_exists('item_prototypes', $drule)) {
								foreach ($drule['item_prototypes'] as &$prototype) {
									foreach ($fields as $field) {
										unset($prototype[$field]);
									}

									if (array_key_exists('type', $prototype) && in_array($prototype['type'], $types)) {
										$prototype['type'] = CXmlConstantName::SNMP_AGENT;
									}
								}
								unset($prototype);
							}
						}
						unset($drule);
					}
				}
				unset($value);
			}
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
	protected function getDefaultInterfaceArray(array $interface): array {
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
	protected function createHelperArray(array $data, int $type): array {
		return [
			'from' => $type,
			'id' => $data['key'],
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
				? (array_key_exists('snmpv3_securitylevel', $data)
					? $data['snmpv3_securitylevel']
					: CXmlConstantName::NOAUTHNOPRIV)
				: '',
			'authprotocol' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_authprotocol', $data)
					? $data['snmpv3_authprotocol']
					: CXmlConstantName::MD5)
				: '',
			'authpassphrase' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_authpassphrase', $data) ? $data['snmpv3_authpassphrase'] : '')
				: '',
			'privprotocol' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_privprotocol', $data)
					? $data['snmpv3_privprotocol']
					: CXmlConstantName::DES)
				: '',
			'privpassphrase' => ($data['type'] === CXmlConstantName::SNMPV3)
				? (array_key_exists('snmpv3_privpassphrase', $data) ? $data['snmpv3_privpassphrase'] : '')
				: ''
		];
	}

	/**
	 * Extract SNMP fields from items, discovery rules and item prototypes.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function extractSnmpFields(array $host): array {
		// SNMP types.
		$types = [CXmlConstantName::SNMPV1, CXmlConstantName::SNMPV2, CXmlConstantName::SNMPV3,
			CXmlConstantName::SNMP_TRAP
		];
		$interfaces = [];

		if (array_key_exists('items', $host)) {
			// Getting all SNMP items and their interfaces.
			foreach ($host['items'] as $item) {
				if (array_key_exists('type', $item) && in_array($item['type'], $types)) {
					$interfaceid = str_replace('if', '', $item['interface_ref']);

					$interfaces[$interfaceid][] = $this->createHelperArray($item, self::TYPE_ITEM);
				}
			}
		}

		if (array_key_exists('discovery_rules', $host)) {
			// Getting all SNMP  discovery rules and their interfaces.
			foreach ($host['discovery_rules'] as $drule) {
				if (array_key_exists('type', $drule) && in_array($drule['type'], $types)) {
					$interfaceid = str_replace('if', '', $drule['interface_ref']);

					$interfaces[$interfaceid][] = $this->createHelperArray($drule, self::TYPE_DISCOVERY_RULE);
				}

				if (array_key_exists('item_prototypes', $drule)) {
					// Getting all SNMP item prototypes and their interfaces.
					foreach ($drule['item_prototypes'] as $prototype) {
						if (array_key_exists('type', $prototype) && in_array($prototype['type'], $types)) {
							$interfaceid = str_replace('if', '', $prototype['interface_ref']);

							$interfaces[$interfaceid][] = $this->createHelperArray($prototype,
								self::TYPE_ITEM_PROTOTYPE
							);
						}
					}
				}
			}
		}

		return $interfaces;
	}

	/**
	 * Get max interfaceid from host interfaces.
	 *
	 * @param array $interfaces
	 *
	 * @return int
	 */
	protected function getHostMaxInterfaceId(array $interfaces): int {
		$max_id = 1;

		foreach ($interfaces as $interface) {
			$interfaceid = (int) str_replace('if', '', $interface['interface_ref']);

			if ($interfaceid > $max_id) {
				$max_id = $interfaceid;
			}
		}

		return $max_id;
	}

	/**
	 * Create new interface array.
	 *
	 * @param array $item
	 * @param int   $maxid
	 * @param array $parent_interface
	 *
	 * @return array
	 */
	protected function createNewInterface(array $item, int $maxid, array $parent_interface): array {
		$interface = ['interface_ref' => 'if'.$maxid] + $parent_interface;

		if ($item['type'] == CXmlConstantName::SNMP_TRAP) {
			$interface['details']['version'] = CXmlConstantName::SNMPV1;
			$interface['details']['community'] = 'public';
		}
		else {
			$interface['details']['version'] = $item['type'];
		}

		// Set item port if have.
		if ($item['port'] !== '') {
			$interface['port'] = $item['port'];
		}

		if ($item['type'] === CXmlConstantName::SNMPV1 || $item['type'] === CXmlConstantName::SNMPV2) {
			$interface['details']['community'] = $item['community'];
		}

		if ($item['type'] === CXmlConstantName::SNMPV3) {
			$interface['details']['contextname'] = $item['contextname'];
			$interface['details']['securityname'] = $item['securityname'];
			$interface['details']['securitylevel'] = $item['securitylevel'];
			$interface['details']['authprotocol'] = $item['authprotocol'];
			$interface['details']['authpassphrase'] = $item['authpassphrase'];
			$interface['details']['privprotocol'] = $item['privprotocol'];
			$interface['details']['privpassphrase'] = $item['privpassphrase'];
		}

		return $interface;
	}

	/**
	 * Update interface_ref in items, discovery rules and item prototypes.
	 *
	 * @param array $host
	 * @param array $updates  itemid => new interface_ref
	 *
	 * @return array
	 */
	protected function updateInterfaceRef(array $host, array $updates): array {
		if (array_key_exists('items', $host) && array_key_exists(self::TYPE_ITEM, $updates)) {
			foreach ($host['items'] as &$item) {
				if (array_key_exists($item['key'], $updates[self::TYPE_ITEM])) {
					$item['interface_ref'] = $updates[self::TYPE_ITEM][$item['key']];
				}
			}
			unset($item);
		}

		if (array_key_exists('discovery_rules', $host)) {
			foreach ($host['discovery_rules'] as &$drule) {
				if (array_key_exists(self::TYPE_DISCOVERY_RULE, $updates)) {
					if (array_key_exists($drule['key'], $updates[self::TYPE_DISCOVERY_RULE])) {
						$drule['interface_ref'] = $updates[self::TYPE_DISCOVERY_RULE][$drule['key']];
					}
				}

				if (array_key_exists('item_prototypes', $drule)
						&& array_key_exists(self::TYPE_ITEM_PROTOTYPE, $updates)) {
					foreach ($drule['item_prototypes'] as &$prototype) {
						if (array_key_exists($prototype['key'], $updates[self::TYPE_ITEM_PROTOTYPE])) {
							$prototype['interface_ref'] = $updates[self::TYPE_ITEM_PROTOTYPE][$prototype['key']];
						}
					}
					unset($prototype);
				}
			}
			unset($drule);
		}

		return $host;
	}

	/**
	 * Convert SNMP fields from host items, discovery rules and item prototypes to interfaces.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertSnmpFieldsToInterfaces(array $hosts): array {
		foreach ($hosts as &$host) {
			// Store new interfaces.
			$new_interfaces = [];

			// Store id where we need update interface_ref.
			$updates = [];

			// Store values related to interface.
			$interfaces = $this->extractSnmpFields($host);

			// Getting all interfaces.
			if (array_key_exists('interfaces', $host)) {
				$max_interfaceid = $this->getHostMaxInterfaceId($host['interfaces']);

				foreach ($host['interfaces'] as $key => &$interface) {
					$interfaceid = str_replace('if', '', $interface['interface_ref']);

					// Working only with SNMP interfaces.
					if (array_key_exists('type', $interface) && $interface['type'] === CXmlConstantName::SNMP) {
						// Save bulk value.
						$interface['details'] = [
							'bulk' => array_key_exists('bulk', $interface) ? $interface['bulk'] : CXmlConstantName::YES
						];

						unset($interface['bulk']);

						$parent_interface = $this->getDefaultInterfaceArray($interface);

						// Check if interface used in items.
						if (array_key_exists($interfaceid, $interfaces)) {
							// Clone interface and map it with parent interface.
							$new_interfaces[$interfaceid][] = [
								'interface_ref' => 'if'.(++$max_interfaceid),
								'new' => true
							] + $parent_interface;

							// Walk through all items for this interface.
							foreach ($interfaces[$interfaceid] as $item) {
								// Set SNMP version from first item.
								foreach ($new_interfaces[$interfaceid] as &$iface) {
									if (array_key_exists('new', $iface)) {
										if ($item['type'] === CXmlConstantName::SNMP_TRAP) {
											// Use default SNMP V1 interface for SNMP traps.
											$iface['details']['version'] = CXmlConstantName::SNMPV1;
											$iface['details']['community'] = 'public';
										}
										elseif ($item['type'] === CXmlConstantName::SNMPV1
												|| $item['type'] === CXmlConstantName::SNMPV2) {
											$iface['details']['version'] = $item['type'];
											$iface['details']['community'] = $item['community'];
										}
										elseif ($item['type'] === CXmlConstantName::SNMPV3) {
											$iface['details']['version'] = CXmlConstantName::SNMPV3;
											$iface['details']['contextname'] = $item['contextname'];
											$iface['details']['securityname'] = $item['securityname'];
											$iface['details']['securitylevel'] = $item['securitylevel'];
											$iface['details']['authprotocol'] = $item['authprotocol'];
											$iface['details']['authpassphrase'] = $item['authpassphrase'];
											$iface['details']['privprotocol'] = $item['privprotocol'];
											$iface['details']['privpassphrase'] = $item['privpassphrase'];
										}

										// Item port not set here because we will find it in next steps.

										unset($iface['new']);
										break;
									}
								}
								unset($iface);

								// Find interfaces having same SNMP version.
								$same_ver_interfaces = array_filter($new_interfaces[$interfaceid],
									function (array $iface) use (&$item): bool {
										// Use default SNMP V1 interface for SNMP traps.
										if ($item['type'] === CXmlConstantName::SNMP_TRAP) {
											$item['type'] = CXmlConstantName::SNMPV1;
											$item['community'] = 'public';
										}

										return ($iface['details']['version'] === $item['type']);
									}
								);

								if ($same_ver_interfaces) {
									$same_interfaces = array_filter($same_ver_interfaces,
										function (array $iface) use ($item, $parent_interface): bool {
											// If item port differs from interface ports it is 100% new interface.
											if ($item['port'] === '') {
												// Item port not set and interface port not equal parent port.
												if ($iface['port'] !== $parent_interface['port']) {
													return false;
												}
											}
											else {
												// If item port not equal interface ports it is 100% new interface.
												if ($iface['port'] !== $item['port']) {
													return false;
												}
											}

											// If interface community string is equal with item it is our interface.
											if ($item['type'] === CXmlConstantName::SNMPV1
													|| $item['type'] === CXmlConstantName::SNMPV2) {
												return ($iface['details']['community'] === $item['community']);
											}

											// Compare all item specific SNMPV3 fields with interface properties.
											if ($item['type'] === CXmlConstantName::SNMPV3) {
												return ($iface['details']['contextname'] === $item['contextname']
													&& $iface['details']['securityname'] === $item['securityname']
													&& $iface['details']['securitylevel'] === $item['securitylevel']
													&& $iface['details']['authprotocol'] === $item['authprotocol']
													&& $iface['details']['authpassphrase'] === $item['authpassphrase']
													&& $iface['details']['privprotocol'] === $item['privprotocol']
													&& $iface['details']['privpassphrase'] === $item['privpassphrase']);
											}
										}
									);

									if ($same_interfaces) {
										$iface = current($same_interfaces);
										$updates[$item['from']][$item['id']] = $iface['interface_ref'];
									}
									else {
										// Create new interface if not found.
										$iface = $this->createNewInterface($item, ++$max_interfaceid,
											$parent_interface
										);

										$new_interfaces[$interfaceid][] = $iface;

										$updates[$item['from']][$item['id']] = $iface['interface_ref'];
									}
								}
								else {
									// Create new interface if not found with same version.
									$iface = $this->createNewInterface($item, ++$max_interfaceid, $parent_interface);

									$new_interfaces[$interfaceid][] = $iface;

									$updates[$item['from']][$item['id']] = $iface['interface_ref'];
								}
							}
						}
						else {
							// Interface not used in items. Create with default values.
							$new_interfaces[$interfaceid][] = ['details' => $parent_interface['details']
									+ ['version' => CXmlConstantName::SNMPV2, 'community' => '{$SNMP_COMMUNITY}']
								] + $parent_interface;
						}

						// Delete original interface because we created new.
						unset($host['interfaces'][$key]);
						continue;
					}

					// Unset bulk field from interfaces.
					unset($interface['bulk']);
				}
				unset($interface);
			}

			$host = $this->updateInterfaceRef($host, $updates);

			// Add all new interfaces to host interfaces.
			foreach ($new_interfaces as $values) {
				foreach ($values as $value) {
					$host['interfaces'][] = $value;
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

		return $hosts;
	}
}
