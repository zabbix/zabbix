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
		$host = [];

		if ($this->hasInput('hostid')) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name', 'active_available'],
				'selectHostGroups' => ['groupid'],
				'selectInterfaces' => ['type', 'ip', 'dns', 'port', 'useip'],
				'selectMacros' => ['macro', 'value', 'description', 'type'],
				'hostids' => $this->getInput('hostid')
			]);
			$host = $hosts[0];

			// Potentially changed field for multiselect ease-of-use.
			$host['groups'] = $host['hostgroups'];
			unset($host['hostgroups']);

			// Take only first interface from each type if possible.
			$interfaces_by_type = [];

			foreach ($host['interfaces'] as $interface) {
				if (!array_key_exists($interface['type'], $interfaces_by_type)) {
					// Add "address" depending on IP/DNS usage. Original fields left as indication for CAddressParser.
					$interface['address'] = $interface['useip'] == INTERFACE_USE_IP
						? $interface['ip']
						: $interface['dns'];

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

		$agent_interface_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_SSH,
			ITEM_TYPE_TELNET
		];
		$snmp_interface_types = [ITEM_TYPE_SNMP, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_SSH, ITEM_TYPE_TELNET
		];
		$ipmi_interface_types = [ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET];
		$jmx_interface_types = [ITEM_TYPE_JMX];

		// Get template items, LLD rules and item prototypes that require interfaces.
		$items = API::Item()->get([
			'output' => ['type'],
			'templateids' => $template['templateid'],
			'filter' => [
				'type' => array_keys(array_flip($agent_interface_types) + array_flip($snmp_interface_types) +
					array_flip($ipmi_interface_types) + array_flip($jmx_interface_types)),
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE]
			]
		]);

		$agent_interface_required = false;
		$snmp_interface_required = false;
		$ipmi_interface_required = false;
		$jmx_interface_required = false;

		foreach ($items as $item) {
			if (in_array($item['type'], $agent_interface_types)) {
				$agent_interface_required = true;
			}
			if (in_array($item['type'], $snmp_interface_types)) {
				$snmp_interface_required = true;
			}
			if (in_array($item['type'], $ipmi_interface_types)) {
				$ipmi_interface_required = true;
			}
			if (in_array($item['type'], $jmx_interface_types)) {
				$jmx_interface_required = true;
			}
		}

		/*
		 * If host exists and macro name matches template name, value is taken from host instead of template. Skips
		 * macros that have no config and converts JSON string for list and checkbox config types to array.
		 */
		if ($host && $host['macros']) {
			foreach ($template['macros'] as $m => &$tmpl_macro) {
				// Skip macros that do no have config set up.
				if ($tmpl_macro['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
					unset($template['macros'][$m]);
					continue;
				}

				// Converts list and checkbox config type options to array.
				if ($tmpl_macro['config']['type'] == ZBX_WIZARD_FIELD_LIST
						|| $tmpl_macro['config']['type'] == ZBX_WIZARD_FIELD_CHECKBOX) {
					$tmpl_macro['config']['options'] = json_decode($tmpl_macro['config']['options'], true);
				}

				// Use host macro value if macro name matches.
				foreach ($host['macros'] as $host_macro) {
					if ($tmpl_macro['macro'] === $host_macro['macro']) {
						$tmpl_macro['value'] = $host_macro['value'];
					}
				}
			}
			unset($tmpl_macro);
		}

		$data = [
			'arg_server_host' => self::getArgServerHost(),
			/*
			 * All host data (or empty of no host). Interface IP and DNS merged into one field "address". Only one
			 * interface remains (could be non-main interface) so it will pre-fill the fields if needed.
			 */
			'host' => $host,
			// Host availability. Used for PSK button display.
			'active_available' => !$host || $host['active_available'] != INTERFACE_AVAILABLE_TRUE,
			/*
			 * All template data. Only config-required macros are available here. Includes unparsed "readme". If host
			 * exists and macro names match, macro value is pre-filled from host instead of template.
			 */
			'template' => $template,
			// Interface requirements according to template item, LLD rule and item prototype types.
			'agent_interface_required' => $agent_interface_required,
			'snmp_interface_required' => $snmp_interface_required,
			'ipmi_interface_required' => $ipmi_interface_required,
			'jmx_interface_required' => $jmx_interface_required
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	protected static function getArgServerHost(): string {
		$result = [];

		/** @var CConfigFile $config */
		$config = ZBase::getInstance()->Component()->get('config')->config;
		if ($config['ZBX_SERVER'] && $config['ZBX_SERVER_PORT']) {
			$result[] = $config['ZBX_SERVER'].':'.$config['ZBX_SERVER_PORT'];
		}
		else {
			$hanodes = API::HaNode()->get([
				'output' => ['address', 'port'],
				'filter' => ['status' =>ZBX_NODE_STATUS_ACTIVE]
			]);


			foreach ($hanodes as $hanode) {
				$result[] = $hanode['address'].':'.$hanode['port'];
			}
		}

		return implode(',', $result);
	}
}
