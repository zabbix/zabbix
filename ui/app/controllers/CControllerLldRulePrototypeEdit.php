<?php declare(strict_types=0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
			'name'					=> 'db items.name',
			'type'					=> 'in '.implode(',', CControllerLldRuleUpdateGeneral::getItemTypes()),
			'key'					=> 'db items.key_',
			'url'					=> 'db items.url',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'script'				=> 'db items.params',
			'browser_script'		=> 'db items.params',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'db items.posts',
			'headers'				=> 'array',
			'status_codes'			=> 'db items.status_codes',
			'follow_redirects'		=> 'in '.implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'http_proxy'			=> 'db items.http_proxy',
			'http_authtype'			=> 'in '.implode(',', array_keys(httptest_authentications())),
			'http_username'			=> 'db items.username',
			'http_password'			=> 'db items.password',
			'verify_peer'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]),
			'verify_host'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
			'ssl_cert_file'			=> 'db items.ssl_cert_file',
			'ssl_key_file'			=> 'db items.ssl_key_file',
			'ssl_key_password'		=> 'db items.ssl_key_password',
			'master_itemid'			=> 'id',
			'interfaceid'			=> 'id',
			'snmp_oid'				=> 'db items.snmp_oid',
			'ipmi_sensor'			=> 'db items.ipmi_sensor',
			'authtype'				=> 'in '.implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'jmx_endpoint'			=> 'db items.jmx_endpoint',
			'username'				=> 'db items.username',
			'publickey'				=> 'db items.publickey',
			'privatekey'			=> 'db items.privatekey',
			'password'				=> 'db items.password',
			'params_es'				=> 'db items.params',
			'params_ap'				=> 'db items.params',
			'delay'					=> 'db items.delay',
			'delay_flex'			=> 'array',
			'custom_timeout'		=> 'in '.implode(',', [ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED, ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]),
			'timeout'				=> 'db items.timeout',
			'lifetime_type'			=> 'in '.implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY]),
			'lifetime'				=> 'db items.lifetime',
			'enabled_lifetime_type'	=> 'in '.implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY]),
			'enabled_lifetime'		=> 'db items.lifetime',
			'allow_traps'			=> 'in '.implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]),
			'trapper_hosts'			=> 'db items.trapper_hosts',
			'inventory_link'		=> 'db items.inventory_link',
			'description'			=> 'db items.description',
			'status'				=> 'in '.implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'lld_macro_paths'		=> 'array',
			'evaltype'				=> 'in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]),
			'formula'				=> 'db items.formula',
			'conditions'			=> 'array',
			'overrides'				=> 'array',
			'preprocessing'			=> 'array',
			'context'				=> 'required|in host,template',
			'discover'				=> 'in '.implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER]),
			'hostid'				=> 'id',
			'itemid'				=> 'id',
			'templateid'			=> 'id',
			'parent_discoveryid'	=> 'id',
			'clone'					=> 'in 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->hasInput('clone') && !$this->hasInput('itemid')) {
				$ret = false;
				error(_s('Incorrect value for "%1$s" field.', 'itemid'));
			}
			elseif (!$this->hasInput('parent_discoveryid')) {
				$ret = false;
				error(_s('Incorrect value for "%1$s" field.', 'parent_discoveryid'));
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
				'itemids' => [$this->getInput('itemid', $this->getInput('parent_discoveryid'))]
			]);

			if (!$host) {
				return false;
			}

			$this->host = reset($host);
		}
		else {
			$template = API::Template()->get([
				'output' => ['templateid', 'name', 'flags', 'proxyid'],
				'itemids' => [$this->getInput('itemid', $this->getInput('parent_discoveryid'))]
			]);

			if (!$template) {
				return false;
			}

			$this->template = reset($template);
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
		$host = $this->getInput('context') === 'host' ? $this->getHost() : $this->getTemplate();

		[$lldrule, $inherited_timeouts] = $this->getLldRuleData($host);

		$data = [
			'itemid' => $lldrule['itemid'],
			'host' => $host,
			'context' => $this->getInput('context'),
			'parent_discovery' => $this->parent_discovery,
			'executable_item_types' => checkNowAllowedTypes(),
			'preprocessing_types' => CDiscoveryRulePrototype::SUPPORTED_PREPROCESSING_TYPES,
			'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_LLD_PROTOTYPE,
			'lldrule' => $lldrule,
			'inherited_timeouts' => $inherited_timeouts,
			'readonly' => $lldrule['templated'] || $lldrule['discovered_lld'],
			'can_edit_source_timeouts' => $host['proxyid']
				? CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				: CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL),
			'types' => array_intersect_key(item_type2str(), array_flip(CControllerLldRuleUpdateGeneral::getItemTypes())),
			'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($host['hostid']),
			'js_test_validation_rules' => (new CFormValidator(
				CControllerPopupItemTestEdit::getValidationRules(true)
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

	/**
	 * Get host data.
	 *
	 * @return array
	 */
	protected function getHost(): array {
		$host = $this->host;

		if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
			$host['proxyid'] = $host['assigned_proxyid'];
		}
		unset($host['monitored_by'], $host['assigned_proxyid']);

		$host['interfaces'] = array_column($host['interfaces'], null, 'interfaceid');
		// Sort interfaces to be listed starting with one selected as 'main'.
		CArrayHelper::sort($host['interfaces'], [
			['field' => 'main', 'order' => ZBX_SORT_DOWN],
			['field' => 'interfaceid','order' => ZBX_SORT_UP]
		]);

		return $host;
	}

	/**
	 * Get template data.
	 *
	 * @return array
	 */
	protected function getTemplate(): array {
		$template = $this->template;
		$template += [
			'hostid' => $this->template['templateid'],
			'proxyid' => 0,
			'status' => HOST_STATUS_TEMPLATE,
			'interfaces' => []
		];

		return $template;
	}

	private function getLldRuleData(array $host): array {
		if ($this->lldrule) {
			$item = CLldRulePrototypeHelper::convertApiInputForForm($this->lldrule);

			if ($this->getInput('clone', 0)) {
				$item = CLldRulePrototypeHelper::normalizeFormData($this->getInputAll() + $item);
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

		if ($item['lifetime_type'] != ZBX_LLD_DELETE_AFTER) {
			$item['lifetime'] = DB::getDefault('items', 'lifetime');
		}

		if ($item['enabled_lifetime_type'] != ZBX_LLD_DISABLE_AFTER) {
			$item['enabled_lifetime'] = ZBX_LLD_RULE_ENABLED_LIFETIME;
		}

		if ($item['itemid']) {
			$item['templates'] = makeItemTemplatesHtml($item['itemid'], getItemParentTemplates([$item], $item['flags']),
				$item['flags'], CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			);
		}

		CArrayHelper::sort($item['overrides'], ['step']);

		return [$item, $inherited_timeouts];
	}
}
