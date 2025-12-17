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


class CControllerHostWizardEdit extends CController {

	private $host = null;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid' => 'db hosts.hostid'
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
			$host = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $this->getInput('hostid'),
				'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
				'editable' => true,
				'limit' => 1
			]);

			if (!$host) {
				return false;
			}

			$this->host = $host[0];
		}

		return true;
	}

	protected function doAction(): void {
		$vendor_template_count = API::Template()->get([
			'output' => [],
			'search' => ['vendor_version' => '-'],
			'countOutput' => true
		]);

		$wizard_ready_templates = API::Template()->get([
			'output' => ['templateid', 'name', 'description', 'vendor_version'],
			'selectTemplateGroups' => ['name'],
			'selectTags' => ['tag', 'value'],
			'selectItems' => ['type'],
			'selectDiscoveryRules' => ['type'],
			'filter' => ['wizard_ready' => ZBX_WIZARD_READY],
			'sortfield' => 'name'
		]);

		$item_prototypes = API::ItemPrototype()->get([
			'output' => ['type'],
			'selectHosts' => ['hostid'],
			'hostids' => array_column($wizard_ready_templates, 'templateid')
		]);

		$item_prototypes_by_templateid = [];

		foreach ($item_prototypes as $item_prototype) {
			$templateid = $item_prototype['hosts'][0]['hostid'];
			$item_prototypes_by_templateid[$templateid][] = $item_prototype;
		}

		$wizard_vendor_template_count = 0;

		foreach ($wizard_ready_templates as &$template) {
			if ($template['vendor_version'] !== '') {
				$wizard_vendor_template_count ++;
			}

			$items = array_merge(
				$template['items'],
				$template['discoveryRules'],
				$item_prototypes_by_templateid[$template['templateid']] ?? []
			);

			$unique_types = array_keys(array_column($items, null, 'type'));

			// Remove unnecessary template data.
			unset($template['items'], $template['discoveryRules'], $template['vendor_version']);

			$template['data_collection'] = null;
			$template['agent_mode'] = [];

			foreach ($unique_types as $type) {
				if (in_array($type, [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE])) {
					$agent_mode = $type == ITEM_TYPE_ZABBIX
						? ZBX_TEMPLATE_AGENT_MODE_PASSIVE
						: ZBX_TEMPLATE_AGENT_MODE_ACTIVE;

					$template['data_collection'] = ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED;
					$template['agent_mode'][$agent_mode] = true;
				}
				elseif ($template['data_collection'] === null) {
					$template['data_collection'] = ZBX_TEMPLATE_DATA_COLLECTION_AGENTLESS;
				}
			}

			$template['agent_mode'] = array_keys($template['agent_mode']);
		}
		unset($template);

		$linked_templates = $this->host !== null
			? API::Template()->get([
				'output' => ['templateid'],
				'hostids' => $this->host['hostid'],
				'preservekeys' => true
			])
			: [];

		$data = [
			'form_action' => $this->host !== null ? 'host.wizard.update' : 'host.wizard.create',
			'host' => $this->host,
			'templates' => $wizard_ready_templates,
			'linked_templates' => array_keys($linked_templates),
			'old_template_count' => $vendor_template_count - $wizard_vendor_template_count,
			'wizard_show_welcome' => CProfile::get('web.host.wizard.show.welcome', 1),
			'agent_script_data' => [
				'version' => preg_match('/^\d+\.\d+/', ZABBIX_VERSION, $version) ? $version[0] : '',
				'server_host' => static::getServerHost()
			],
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	protected static function getServerHost(): string {
		$result = [];
		$unsupported_addresses = ['localhost', '127.0.0.1'];

		/** @var CConfigFile $config */
		$config = ZBase::getInstance()->Component()->get('config')->config;
		if ($config['ZBX_SERVER'] && $config['ZBX_SERVER_PORT']
				&& !in_array($config['ZBX_SERVER'], $unsupported_addresses, true)) {
			$result[] = $config['ZBX_SERVER'] . ':' . $config['ZBX_SERVER_PORT'];
		}
		else {
			$hanodes = API::HaNode()->get([
				'output' => ['address', 'port']
			]);

			if ($hanodes !== false) {
				foreach ($hanodes as $hanode) {
					if (!in_array($hanode['address'], $unsupported_addresses, true)) {
						$result[] = $hanode['address'] . ':' . $hanode['port'];
					}
				}
			}
		}

		return implode(',', $result);
	}
}
