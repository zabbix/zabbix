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


class CControllerLldRulePrototypeCreate extends CControllerLldRuleUpdateGeneral {

	/**
	 * @var array
	 */
	private $parent_discovery;

	public static function getValidationRulesApiUniq(): array {
		return [
			['discoveryrule.get', ['key_' => '{key}', 'hostid' => '{hostid}']],
			['discoveryruleprototype.get', ['key_' => '{key}', 'hostid' => '{hostid}']]
		];
	}

	public static function getFieldsValidationRulesAdditional(): array {
		return [
			'parent_discoveryid' => ['db items.itemid', 'required'],
			'discover' => ['db items.discover', 'required', 'in' => [ITEM_DISCOVER, ITEM_NO_DISCOVER]]
		];
	}

	public static function getValidationRulesTypeField(): array {
		return [
			['db items.type', 'required', 'in' => self::getItemTypes()]
		];
	}

	public static function isPrototype(): bool {
		return true;
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add discovery prototype'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);

			return false;
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

		return true;
	}

	public function doAction(): void {
		$output = [];
		$result = API::DiscoveryRulePrototype()->create($this->getInputForApi());
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Discovery prototype created');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add discovery prototype'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function getInputForApi(): array {
		$input = CLldRulePrototypeHelper::normalizeFormData($this->getInputAll() + CLldRulePrototypeHelper::getDefaults());
		$input['flags'] = ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE;
		$input = CLldRulePrototypeHelper::convertFormInputForApi($input);
		$input['templateid'] = 0;
		$input['itemid'] = 0;

		$input['hosts'] = $this->parent_discovery['hosts'];

		return [
			'ruleid' => $this->parent_discovery['itemid'],
			'hostid' => $this->parent_discovery['hosts'][0]['hostid']
		] + getSanitizedItemFields($input);
	}
}
