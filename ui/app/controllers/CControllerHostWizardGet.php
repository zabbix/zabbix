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


class CControllerHostWizardGet extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid'		=> 'db hosts.hostid',
			'templateid'	=> 'required|db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('hostid')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('hostid'),
				'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
				'editable' => true,
				'limit' => 1
			]);

			if (!$hosts) {
				return false;
			}
		}

		// Make sure the template exists and is wizard-ready.
		$templates = API::Template()->get([
			'output' => [],
			'templateids' => $this->getInput('templateid'),
			'filter' => ['wizard_ready' => ZBX_WIZARD_READY],
			'limit' => 1
		]);

		if (!$templates) {
			return false;
		}

		return true;
	}

	protected function doAction(): void {
		$host = null;
		$interfaces_by_type = [];

		if ($this->hasInput('hostid')) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'host', 'name', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
					'ipmi_password', 'tls_connect', 'tls_accept'
				],
				'selectHostGroups' => ['groupid'],
				'selectInterfaces' => ['type', 'ip', 'dns', 'port', 'useip', 'details'],
				'selectMacros' => ['macro', 'value', 'description', 'type'],
				'hostids' => $this->getInput('hostid')
			]);
			$host = $hosts[0];

			// Potentially changed field for multiselect ease-of-use.
			$host['groups'] = $host['hostgroups'];
			unset($host['hostgroups']);

			$host['tls_in_none'] = (bool)($host['tls_accept'] & HOST_ENCRYPTION_NONE);
			$host['tls_in_psk'] = (bool)($host['tls_accept'] & HOST_ENCRYPTION_PSK);
			$host['tls_in_cert'] = (bool)($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE);
			unset($host['tls_accept']);

			// Take only first interface from each type if possible.
			foreach ($host['interfaces'] as $interface) {
				if (!array_key_exists($interface['type'], $interfaces_by_type)) {
					// Add "address" depending on IP/DNS usage. Original fields left as indication for CAddressParser.
					$interface['address'] = $interface['useip'] == INTERFACE_USE_IP
						? $interface['ip']
						: $interface['dns'];

					unset($interface['ip'], $interface['dns'], $interface['useip']);
					$interfaces_by_type[$interface['type']] = $interface;
				}
			}

			// Replace original interfaces with only one left of each type.
			$host['interfaces'] = array_values($interfaces_by_type);
		}

		$templates = API::Template()->get([
			'output' => ['templateid', 'name', 'wizard_ready', 'readme'],
			'selectMacros' => ['macro', 'value', 'description', 'type', 'config'],
			'templateids' => $this->getInput('templateid')
		]);
		$template = $templates[0];

		$parsedown = (new Parsedown())
			->setSafeMode(true);

		if ($template['readme'] !== '') {
			$template['readme'] = $parsedown->text($template['readme']);
		}

		$template['macros'] = $this->prepareTemplateMacros($template['macros'], $parsedown);

		// Get template items, LLD rules and item prototypes that require interfaces.
		$items = API::Item()->get([
			'output' => ['type'],
			'templateids' => $template['templateid'],
			'filter' => [
				'type' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SNMP, ITEM_TYPE_SNMPTRAP,
					ITEM_TYPE_IPMI, ITEM_TYPE_JMX
				],
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE]
			]
		]);

		$install_agent_required = false;
		$agent_interface_required = false;
		$snmp_interface_required = false;
		$ipmi_interface_required = false;
		$jmx_interface_required = false;

		foreach ($items as $item) {
			if (in_array($item['type'], [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE])) {
				$install_agent_required = true;
			}
			if ($item['type'] == ITEM_TYPE_ZABBIX) {
				$agent_interface_required = true;
			}
			if (in_array($item['type'], [ITEM_TYPE_SNMP, ITEM_TYPE_SNMPTRAP])) {
				$snmp_interface_required = true;
			}
			if ($item['type'] == ITEM_TYPE_IPMI) {
				$ipmi_interface_required = true;
			}
			if ($item['type'] == ITEM_TYPE_JMX) {
				$jmx_interface_required = true;
			}
		}

		$install_agent_required = $install_agent_required
			&& !array_key_exists(INTERFACE_TYPE_AGENT, $interfaces_by_type);

		$data = [
			/*
			 * All host data (or empty of no host). Interface IP and DNS merged into one field "address". Only one
			 * interface remains (could be non-main interface) so it will pre-fill the fields if needed.
			 */
			'host' => $host,
			/*
			 * All template data. Only config-required macros are available here. Includes unparsed "readme". If host
			 * exists and macro names match, macro value is pre-filled from host instead of template.
			 */
			'template' => $template,
			'install_agent_required' => $install_agent_required,
			// Interface requirements according to template item, LLD rule and item prototype types.
			'agent_interface_required' => $agent_interface_required && !array_key_exists(INTERFACE_TYPE_AGENT, $interfaces_by_type),
			'snmp_interface_required' => $snmp_interface_required && !array_key_exists(INTERFACE_TYPE_SNMP, $interfaces_by_type),
			'ipmi_interface_required' => $ipmi_interface_required && !array_key_exists(INTERFACE_TYPE_IPMI, $interfaces_by_type),
			'jmx_interface_required' => $jmx_interface_required && !array_key_exists(INTERFACE_TYPE_JMX, $interfaces_by_type)
		];

		$response = new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]);
		$this->setResponse($response);
	}

	private function prepareTemplateMacros(array $macros, $parsedown): array {
		foreach ($macros as $m => &$macro) {
			// Skip macros that do no have config set up.
			if ($macro['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
				unset($macros[$m]);
			}

			if ($macro['config']['description'] !== '') {
				$macro['config']['description'] = $parsedown->text($macro['config']['description']);
			}
		}
		unset($macro);

		usort($macros, static function (array $macro_a, array $macro_b): int {
			$priority_a = (int) $macro_a['config']['priority'];
			$priority_b = (int) $macro_b['config']['priority'];

			if ($priority_a == 0 && $priority_b != 0) {
				return 1;
			}
			if ($priority_a != 0 && $priority_b == 0) {
				return -1;
			}

			if ($priority_a != $priority_b) {
				return $priority_a - $priority_b;
			}

			return 0;
		});

		return array_values($macros);
	}
}
