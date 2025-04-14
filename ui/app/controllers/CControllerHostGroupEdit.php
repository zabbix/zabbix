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
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'groupid' =>	'db hstgrp.groupid',
			'name' =>		'string',
			'subgroups' =>	'in 0,1'
		];

		$ret = $this->validateInput($fields);

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
		$data = [
			'groupid' => null,
			'name' => '',
			'subgroups' => 0
		];

		if ($this->getInput('groupid', 0)) {
			$data['groupid'] = $this->getInput('groupid');

			$groups = API::HostGroup()->get([
				'output' => ['name', 'flags'],
				'selectHosts' => ['hostid'],
				'selectDiscoveryRules' => ['itemid', 'name'],
				'selectDiscoveryRulePrototypes' => ['itemid', 'name'],
				'selectHostPrototypes' => ['hostid'],
				'groupids' => $data['groupid']
			]);

			$group = reset($groups);
			$data = array_merge($data, $group);
			unset($data['discoveryRules'], $data['discoveryRulePrototypes']);

			$data['parent_lld'] = $group['discoveryRules'] ?: $group['discoveryRulePrototypes'];

			CArrayHelper::sort($data['parent_lld'], ['name']);

			$data['parent_lld'] = array_values($data['parent_lld']);
			$discovery_ruleids = array_column($data['parent_lld'], 'itemid');

			$host_prototypes = [];

			if ($discovery_ruleids) {
				$editable_discovery_ruleids = API::DiscoveryRule()->get([
					'output' => [],
					'itemids' => $discovery_ruleids,
					'editable' => true,
					'preservekeys' => true
				]);

				foreach ($data['parent_lld'] as &$discovery_rule) {
					$discovery_rule['is_editable'] = array_key_exists($discovery_rule['itemid'],
						$editable_discovery_ruleids
					);
				}
				unset($discovery_rule);

				$host_prototypes = API::HostPrototype()->get([
					'output' => ['hostid'],
					'selectDiscoveryRule' => ['itemid'],
					'selectDiscoveryRulePrototype' => ['itemid'],
					'hostids' => array_column($data['hostPrototypes'], 'hostid'),
					'editable' => true
				]);
			}

			$data['ldd_rule_to_host_prototype'] = [];

			foreach ($host_prototypes as $prototype) {
				$parent_lld = $prototype['discoveryRule'] ?: $prototype['discoveryRulePrototype'];
				$data['ldd_rule_to_host_prototype'][$parent_lld['itemid']][] = $prototype['hostid'];
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
