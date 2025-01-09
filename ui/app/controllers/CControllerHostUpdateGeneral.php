<?php declare(strict_types = 0);
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
 * Class containing operations for updating/creating a host.
 */
abstract class CControllerHostUpdateGeneral extends CController {

	/**
	 * Common host field validation rules.
	 *
	 * @return array
	 */
	protected static function getValidationFields(): array {
		return [
			'host'				=> 'required|db hosts.host|not_empty',
			'visiblename'		=> 'db hosts.name',
			'description'		=> 'db hosts.description',
			'status'			=> 'required|db hosts.status|in '.implode(',', [HOST_STATUS_MONITORED,
										HOST_STATUS_NOT_MONITORED
									]),
			'monitored_by'		=> 'db hosts.monitored_by|in '.implode(',', [ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP]),
			'proxyid'		    => 'db hosts.proxyid',
			'proxy_groupid'		=> 'db hosts.proxy_groupid',
			'interfaces'		=> 'array',
			'mainInterfaces'	=> 'array',
			'groups'			=> 'required|array',
			'tags'				=> 'array',
			'templates'			=> 'array_db hosts.hostid',
			'add_templates'		=> 'array_db hosts.hostid',
			'clear_templates'	=> 'array_db hosts.hostid',
			'ipmi_authtype'		=> 'in '.implode(',', [IPMI_AUTHTYPE_DEFAULT, IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2,
										IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM,
										IPMI_AUTHTYPE_RMCP_PLUS
									]),
			'ipmi_privilege'	=> 'in '.implode(',', [IPMI_PRIVILEGE_CALLBACK, IPMI_PRIVILEGE_USER,
										IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM
									]),
			'ipmi_username'		=> 'db hosts.ipmi_username',
			'ipmi_password'		=> 'db hosts.ipmi_password',
			'tls_connect'		=> 'db hosts.tls_connect|in '.implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK,
										HOST_ENCRYPTION_CERTIFICATE
									]),
			'tls_accept'		=> 'db hosts.tls_accept|ge 0|le '.
										(0 | HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'tls_subject'		=> 'db hosts.tls_subject',
			'tls_issuer'		=> 'db hosts.tls_issuer',
			'tls_psk_identity'	=> 'db hosts.tls_psk_identity',
			'tls_psk'			=> 'db hosts.tls_psk',
			'inventory_mode'	=> 'db host_inventory.inventory_mode|in '.implode(',', [HOST_INVENTORY_DISABLED,
										HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC
									]),
			'host_inventory'	=> 'array',
			'macros'			=> 'array',
			'valuemaps'			=> 'array',
			'clone'				=> 'in 1',
			'clone_hostid'		=> 'db hosts.hostid'
		];
	}

	/**
	 * Prepare host interfaces.
	 *
	 * @param array $interfaces Submitted interfaces.
	 *
	 * @return array Interfaces for assigning to host.
	 */
	protected function processHostInterfaces(array $interfaces): array {
		foreach ($interfaces as $key => $interface) {
			if ($interface['type'] == INTERFACE_TYPE_SNMP) {
				if (!array_key_exists('details', $interface)) {
					$interface['details'] = [];
				}

				$interfaces[$key]['details']['bulk'] = array_key_exists('bulk', $interface['details'])
					? SNMP_BULK_ENABLED
					: SNMP_BULK_DISABLED;
			}

			if ($interface['isNew']) {
				unset($interfaces[$key]['interfaceid']);
			}

			unset($interfaces[$key]['isNew']);
			$interfaces[$key]['main'] = INTERFACE_SECONDARY;
		}

		$main_interfaces = $this->getInput('mainInterfaces', []);

		foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $type) {
			if (array_key_exists($type, $main_interfaces) && array_key_exists($main_interfaces[$type], $interfaces)) {
				$interfaces[$main_interfaces[$type]]['main'] = INTERFACE_PRIMARY;
			}
		}

		return $interfaces;
	}

	/**
	 * Prepare host level user macros.
	 *
	 * @param array $macros Submitted macros.
	 *
	 * @return array Macros for assigning to host.
	 */
	protected function processUserMacros(array $macros, array $db_macros = []): array {
		$db_macros = array_column($db_macros, null, 'hostmacroid');
		$macro_fields = array_flip(['macro', 'value', 'type', 'description']);
		$macros = cleanInheritedMacros($macros);

		foreach ($macros as &$macro) {
			if (array_key_exists('hostmacroid', $macro) && array_key_exists($macro['hostmacroid'], $db_macros)) {
				$db_macro = $db_macros[$macro['hostmacroid']];
				$macro_diff = array_diff_assoc(array_intersect_key($macro, $macro_fields), $db_macro);
				$mandatory_fields = ['hostmacroid' => $macro['hostmacroid']];

				if (array_key_exists('discovery_state', $macro)
						&& $macro['discovery_state'] == CControllerHostMacrosList::DISCOVERY_STATE_CONVERTING) {
					$macro_diff['automatic'] = ZBX_USERMACRO_MANUAL;
				}

				if ($macro['type'] == ZBX_MACRO_TYPE_VAULT
						&& (!array_key_exists('discovery_state', $macro)
							|| $macro['discovery_state'] != CControllerHostMacrosList::DISCOVERY_STATE_AUTOMATIC)) {
					/**
					 * Macro value must be passed to be sure its syntax is still valid.
					 * Syntax may be changed, e.g., if the Vault provider has been changed.
					 */
					$mandatory_fields['value'] = $macro['value'];
				}

				$macro = $mandatory_fields + $macro_diff;
			}
			else {
				unset($macro['discovery_state'], $macro['original_value'], $macro['original_description'],
					$macro['original_macro_type'], $macro['allow_revert']
				);
			}
		}
		unset($macro);

		return array_filter($macros,
			function (array $macro): bool {
				return (bool) array_filter(
					array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
				);
			}
		);
	}

	/**
	 * Prepare host tags.
	 *
	 * @param array $tags Submitted tags.
	 *
	 * @return array
	 */
	protected function processTags(array $tags): array {
		return array_filter($tags, function (array $tag): bool {
			return ($tag['tag'] !== '' || $tag['value'] !== '');
		});
	}

	/**
	 * Prepare host groups.
	 *
	 * @param array $groups Submitted groups.
	 *
	 * @throws Exception
	 *
	 * @return array Groups for assigning to host.
	 */
	protected function processHostGroups(array $groups): array {
		$new_groups = [];

		foreach ($groups as $idx => $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				$new_groups[] = ['name' => $group['new']];
				unset($groups[$idx]);
			}
		}

		if ($new_groups) {
			$new_groupid = API::HostGroup()->create($new_groups);
			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		return zbx_toObject($groups, 'groupid');
	}

	/**
	 * Merge and prepare templates.
	 *
	 * @param array $templates Array of one or more submitted template sets as arrays (added, existing) to be combined.
	 *
	 * @return array Templates for assigning to host.
	 */
	protected function processTemplates(array $templates): array {
		$all_templates = [];

		foreach ($templates as $template_set) {
			$all_templates = array_merge($all_templates, $template_set);
		}

		return zbx_toObject($all_templates, 'templateid');
	}
}
