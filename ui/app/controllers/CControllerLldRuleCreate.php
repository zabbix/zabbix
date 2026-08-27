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


class CControllerLldRuleCreate extends CControllerLldRuleUpdateGeneral {

	public static function getValidationRulesApiUniq(): array {
		return [
			['discoveryrule.get', ['key_' => '{key}', 'hostid' => '{hostid}']],
			['discoveryruleprototype.get', ['key_' => '{key}', 'hostid' => '{hostid}']]
		];
	}

	public static function getFieldsValidationRulesAdditional(): array {
		return [];
	}

	public static function getValidationRulesTypeField(): array {
		return [
			['db items.type', 'required', 'in' => self::getItemTypes()],
			['db items.type', 'not_in' => [ITEM_TYPE_NESTED],
				'when' => [
					['context', 'in' => ['host']],
					['host_discovered', 'in' => [0]]
				]
			]
		];
	}

	public static function isPrototype(): bool {
		return false;
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add discovery rule'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	public function doAction(): void {
		$output = [];
		$input = $this->getInputForApi();
		$result = API::DiscoveryRule()->create($input);
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Discovery rule created');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add discovery rule'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function getInputForApi(): array {
		$input = CLldRuleHelper::normalizeFormData($this->getInputAll() + CLldRuleHelper::getDefaults());
		$input['flags'] = ZBX_FLAG_DISCOVERY_RULE;
		$input = CLldRuleHelper::convertFormInputForApi($input);
		$input['templateid'] = 0;
		$input['itemid'] = 0;

		$input['hosts'] = API::Host()->get([
			'output' => ['hostid', 'status'],
			'hostids' => [$this->getInput('hostid')],
			'templated_hosts' => true,
			'editable' => true
		]);

		return ['hostid' => $this->getInput('hostid')] + getSanitizedItemFields($input);
	}
}
