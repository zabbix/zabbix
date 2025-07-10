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


class CControllerHostGroupEdit extends CController{

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'groupid' => [],
			'name' => [],
			'subgroups' => []
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)) {
			return false;
		}

		if ($this->getInput('groupid', 0)) {
			return (bool) API::HostGroup()->get([
				'output' => [],
				'groupids' => $this->getInput('groupid'),
				'editable' => true
			]);
		}

		return true;
	}

	protected function doAction(): void {
		$js_validation_rules = $this->hasInput('groupid')
			? CControllerHostGroupUpdate::getValidationRules()
			: CControllerHostGroupCreate::getValidationRules();

		$data = [
			'groupid' => null,
			'name' => '',
			'subgroups' => 0,
			'js_validation_rules' => (new CFormValidator($js_validation_rules))->getRules()
		];

		if ($this->getInput('groupid', 0)) {
			$data['groupid'] = $this->getInput('groupid');

			$groups = API::HostGroup()->get([
				'output' => ['name', 'flags'],
				'selectHosts' => ['hostid'],
				'selectDiscoveryRules' => ['itemid', 'name'],
				'selectHostPrototypes' => ['hostid'],
				'groupids' => $data['groupid']
			]);

			$data = array_merge($data, $groups[0]);
			CArrayHelper::sort($data['discoveryRules'], ['name']);

			$data['discoveryRules'] = array_values($data['discoveryRules']);

			$discovery_ruleids = $data['discoveryRules']
				? array_column($data['discoveryRules'], 'itemid')
				: [];

			$host_prototypes = [];

			if ($discovery_ruleids) {
				$editable_discovery_ruleids = API::DiscoveryRule()->get([
					'output' => [],
					'itemids' => $discovery_ruleids,
					'editable' => true,
					'preservekeys' => true
				]);

				foreach ($data['discoveryRules'] as &$discovery_rule) {
					$discovery_rule['is_editable'] = array_key_exists($discovery_rule['itemid'],
						$editable_discovery_ruleids
					);
				}
				unset($discovery_rule);

				$host_prototypes = API::HostPrototype()->get([
					'output' => ['hostid'],
					'selectDiscoveryRule' => ['itemid'],
					'hostids' => array_column($data['hostPrototypes'], 'hostid'),
					'editable' => true
				]);
			}

			$data['ldd_rule_to_host_prototype'] = [];

			foreach ($host_prototypes as $prototype) {
				$data['ldd_rule_to_host_prototype'][$prototype['discoveryRule']['itemid']][] = $prototype['hostid'];
			}

			$data['allowed_ui_conf_hosts'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
		}

		// For clone action.
		if ($this->hasInput('name')) {
			$data['name'] = $this->getInput('name');
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of host group'));
		$this->setResponse($response);
	}
}
