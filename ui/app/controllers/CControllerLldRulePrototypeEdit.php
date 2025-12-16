<?php declare(strict_types=0);
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


require 'include/forms.inc.php';

class CControllerLldRulePrototypeEdit extends CController
{
	/**
	 * @var array
	 */
	private $host;

	/**
	 * @var array
	 */
	private $template;

	/**
	 * @var array
	 */
	private $lldrule;

	/**
	 * @var array
	 */
	private $parent_discovery;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context'				=> 'required|in host,template',
			'hostid'				=> 'id',
			'itemid'				=> 'id',
			'templateid'			=> 'id',
			'parent_discoveryid'	=> 'id',
			'clone'					=> 'in 1',
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->hasInput('clone') && !$this->hasInput('itemid')) {
				$ret = false;
				error(_s('Incorrect value for "%1$s" field.', 'itemid'));
			}
			elseif (!$this->hasInput('itemid') && !$this->hasInput('hostid')) {
				$ret = false;
				error(_s('Incorrect value for "%1$s" field.', 'hostid'));
			}
		}

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
		$has_access = $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

		if (!$has_access) {
			return false;
		}

		if ($this->getInput('context') === 'host') {
			$host = API::Host()->get([
				'output' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'flags', 'status'],
				'selectInterfaces' => ['interfaceid', 'ip', 'port', 'dns', 'useip', 'details', 'type', 'main'],
				'hostids' => !$this->hasInput('itemid') ? [$this->getInput('hostid')] : null,
				'itemids' => $this->hasInput('itemid') ? [$this->getInput('itemid')] : null
			]);

			if (!$host) {
				return false;
			}

			$this->host = reset($host);
		}
		else {
			$template = API::Template()->get([
				'output' => ['templateid', 'name', 'flags', 'proxyid'],
				'templateids' => !$this->hasInput('itemid') ? [$this->getInput('hostid')] : null,
				'itemids' => $this->hasInput('itemid') ? [$this->getInput('itemid')] : null
			]);

			if (!$template) {
				return false;
			}

			$this->template = reset($template);
			$this->template += [
				'hostid' => $this->template['templateid'],
				'proxyid' => 0,
				'status' => HOST_STATUS_TEMPLATE,
				'interfaces' => []
			];
		}

		$options = [
			'output' => ['itemid', 'name', 'flags'],
			'selectDiscoveryData' => ['parent_itemid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'selectHosts' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'status', 'flags'],
			'editable' => true
		];

		$parent_discovery = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

		if (!$parent_discovery) {
			return false;
		}

		$this->parent_discovery = reset($parent_discovery);

		if ($this->hasInput('itemid')) {
			$lldrules = API::DiscoveryRulePrototype()->get([
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'status', 'flags'],
				'selectFilter' => ['formula', 'evaltype', 'conditions'],
				'selectLLDMacroPaths' => ['lld_macro', 'path'],
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
				'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
				'selectDiscoveryRule' => ['itemid', 'name'],
				'selectDiscoveryRulePrototype' => ['itemid', 'name'],
				'selectDiscoveryData' => ['parent_itemid'],
				'itemids' => $this->getInput('itemid'),
				'discoveryids' => $this->parent_discovery['itemid']
			]);

			if (!$lldrules) {
				return false;
			}

			$this->lldrule = $lldrules[0];
		}

		return true;
	}

	public function doAction(): void {
		$host = $this->getInput('context') === 'host' ? $this->host : $this->template;
		$lldrule = $this->getLldRuleData($host);

		$data = [
			'itemid' => $lldrule['itemid'],
			'host' => $host,
			'context' => $this->getInput('context'),
			'parent_discovery' => $this->parent_discovery,
			'executable_item_types' => checkNowAllowedTypes(),
			'preprocessing_types' => CDiscoveryRulePrototype::SUPPORTED_PREPROCESSING_TYPES,
			'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_LLD_PROTOTYPE,
			'lldrule' => $lldrule,
			'readonly' => $lldrule['templated'] || $lldrule['discovered_lld'],
			'can_edit_source_timeouts' => ($this->host && $this->host['proxyid'])
				? CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				: CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL),
			'types' => array_intersect_key(item_type2str(), array_flip(CControllerLldRuleUpdateGeneral::getItemTypes())),
			'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($host['hostid']),
			'js_test_validation_rules' => (new CFormValidator(
				CControllerPopupItemTestSend::getValidationRules(true)
			))->getRules()
		];

		$data['js_validation_rules'] = $lldrule['itemid']
			? (new CFormValidator(CControllerLldRulePrototypeUpdate::getValidationRules()))->getRules()
			: (new CFormValidator(CControllerLldRulePrototypeCreate::getValidationRules()))->getRules();

		$data['user'] = [
			'debug_mode' => $this->getDebugMode()
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getLldRuleData(array $host): array {
		if ($this->lldrule) {
			$item = CLldRulePrototypeHelper::convertApiInputForForm($this->lldrule);

			if ($this->getInput('clone', 0)) {
				$item['itemid'] = null;
				$item['templateid'] = null;
				$item['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				$item['parent_items'] = [];
				$item['discovered_lld'] = false;
				$item['templated'] = false;
				$item['inventory_link'] = 0;
			}
		}
		else {
			$item = CLldRulePrototypeHelper::getDefaults();
			$item['itemid'] = null;
			$item['hostid'] = $host['hostid'];
		}

		$item['limited'] = $item['templated'];

		if ($item['discovered']) {
			$db_parent = API::DiscoveryRulePrototype()->get([
				'itemids' => $item['discoveryData']['parent_itemid'],
				'selectDiscoveryRule' => ['itemid'],
				'selectDiscoveryRulePrototype' => ['itemid'],
				'nopermissions' => true
			]);
			$db_parent = reset($db_parent);

			$parent_lld = $db_parent['discoveryRule'] ?: $db_parent['discoveryRulePrototype'];
			$item['discoveryData']['lldruleid'] = $parent_lld['itemid'];
		}

		$inherited_timeouts = getInheritedTimeouts($host['proxyid'])['timeouts'];
		$item['inherited_timeout'] = array_key_exists($item['type'], $inherited_timeouts)
			? $inherited_timeouts[$item['type']] : '';

		if ($item['timeout'] === DB::getDefault('items', 'timeout')) {
			$item['timeout'] = $item['inherited_timeout'];
		}

		if ($item['itemid']) {
			$item['templates'] = makeItemTemplatesHtml($item['itemid'], getItemParentTemplates([$item], $item['flags']),
				$item['flags'], CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			);
		}

		CArrayHelper::sort($item['overrides'], ['step']);

		return $item;
	}
}
