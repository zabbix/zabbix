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

require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerTriggerEdit extends CController {

	/**
	 * @var array
	 */
	private $trigger;

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$allow_any = [];
		foreach (array_keys(CControllerTriggerUpdate::getValidationRules()['fields']) as $name) {
			$allow_any[$name] = [];
		}

		$ret = $this->validateInput(['object', 'fields' => [
			'context' => ['string', 'required', 'in' => ['host', 'template']],
			'show_inherited_tags' => ['integer', 'in' => [0, 1]],
			'form_refresh' => ['integer', 'in' => [0, 1]]
		] + $allow_any]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if ($this->hasInput('hostid')) {
			if ($this->getInput('context') === 'host') {
				$exists = (bool) API::Host()->get([
					'output' => [],
					'hostids' => $this->getInput('hostid')
				]);
			}
			else {
				$exists = (bool) API::Template()->get([
					'output' => [],
					'templateids' => $this->getInput('hostid')
				]);
			}

			if (!$exists) {
				return false;
			}
		}

		if ($this->hasInput('triggerid')) {
			$parameters = [
				'output' => ['triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments',
					'templateid', 'type', 'state', 'flags', 'recovery_mode', 'recovery_expression', 'correlation_mode',
					'correlation_tag', 'manual_close', 'opdata', 'event_name', 'url_name'
				],
				'triggerids' => $this->getInput('triggerid'),
				'selectHosts' => ['hostid'],
				'selectDiscoveryRule' => ['itemid', 'name', 'templateid'],
				'selectDiscoveryData' => ['parent_triggerid', 'disable_source'],
				'selectDependencies' => ['triggerid'],
				'selectTags' => ['tag', 'value'],
				'editable' => true
			];

			if ($this->hasInput('show_inherited_tags') && $this->getInput('show_inherited_tags')) {
				$parameters['selectItems'] = ['itemid', 'templateid', 'flags'];
			}

			$triggers = API::Trigger()->get($parameters);

			if (!$triggers) {
				return false;
			}

			$this->trigger = reset($triggers);
		}
		else {
			$this->trigger = null;
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'hostid' => 0,
			'context' => '',
			'expression' => '',
			'recovery_expression' => '',
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'opdata' => '',
			'priority' => '0',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'type' => '0',
			'event_name' => '',
			'limited' => false,
			'tags' =>[],
			'triggerid' => null,
			'show_inherited_tags' => 0,
			'form_refresh' => 0,
			'status' => $this->hasInput('form_refresh') ? TRIGGER_STATUS_DISABLED : TRIGGER_STATUS_ENABLED,
			'templates' => [],
			'db_dependencies' => [],
			'url' => '',
			'url_name' => ''
		];

		$this->getInputs($data, array_keys($data));

		$data['description'] = $this->getInput('name', '');
		$data['comments'] = $this->getInput('description', '');
		$data['dependencies'] = zbx_toObject($this->getInput('dependencies', []), 'triggerid');

		if ($data['tags']) {
			// Unset empty and inherited tags.
			$tags = [];

			foreach ($data['tags'] as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}

				if (($data['show_inherited_tags'] == 0 || !$this->trigger)
						&& (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN))) {
					continue;
				}

				$tags[] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];
			}

			$data['tags'] = $tags;
		}

		if ($this->trigger) {
			$parent_lld = [];

			if ($this->trigger['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
				$db_parent = API::TriggerPrototype()->get([
					'triggerids' => $this->trigger['discoveryData']['parent_triggerid'],
					'selectDiscoveryRule' => ['itemid', 'templateid', 'flags'],
					'selectDiscoveryRulePrototype' => ['itemid', 'templateid', 'flags'],
					'nopermissions' => true
				]);
				$db_parent = reset($db_parent);

				$parent_lld = $db_parent['discoveryRule'] ?: $db_parent['discoveryRulePrototype'];
				$this->trigger['discoveryData']['lldruleid'] = $parent_lld['itemid'];
			}

			$trigger = $parent_lld
				? CTriggerGeneralHelper::getAdditionalTriggerData($this->trigger + ['parent_lld' => $parent_lld], $data)
				: CTriggerGeneralHelper::getAdditionalTriggerData($this->trigger, $data);

			if ($data['form_refresh']) {
				if ($data['show_inherited_tags']) {
					$data['tags'] = $trigger['tags'];
				}

				$data = array_merge($data, [
					'templateid' => $trigger['templateid'],
					'limited' => $trigger['limited'],
					'flags' => $trigger['flags'],
					'templates' => $trigger['templates'],
					'discoveryRule' => $trigger['discoveryRule'],
					'discoveryData' => $trigger['discoveryData']
				]);
			}
			else {
				$data = $trigger;
			}
		}

		CTriggerGeneralHelper::getDependencies($data);

		if (!$data['tags']) {
			$data['tags'][] = ['tag' => '', 'value' => ''];
		}
		else {
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
			$data['tags'] = array_values($data['tags']);
		}

		$data['expr_temp'] = $data['expression'];
		$data['recovery_expr_temp'] = $data['recovery_expression'];
		$data['user'] = ['debug_mode' => $this->getDebugMode()];
		$data['db_trigger'] = $this->trigger ? CTriggerGeneralHelper::convertApiInputForForm($this->trigger) : [];
		$data['js_validation_rules'] = $this->hasInput('triggerid')
			? (new CFormValidator(CControllerTriggerUpdate::getValidationRules()))->getRules()
			: (new CFormValidator(CControllerTriggerCreate::getValidationRules()))->getRules();

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
