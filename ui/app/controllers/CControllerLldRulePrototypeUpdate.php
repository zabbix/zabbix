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


class CControllerLldRulePrototypeUpdate extends CControllerLldRuleUpdateGeneral {

	public static function getValidationRulesApiUniq(): array {
		return [
			['discoveryrule.get', ['key_' => '{key}', 'hostid' => '{hostid}']],
			['discoveryruleprototype.get', ['key_' => '{key}', 'hostid' => '{hostid}'], 'itemid']
		];
	}

	public static function getFieldsValidationRulesAdditional(): array {
		return [
			'itemid' => ['db items.itemid', 'required'],
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
					'title' => _('Cannot update discovery prototype'),
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
		$result = API::DiscoveryRulePrototype()->update($this->getInputForApi());
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Discovery prototype updated');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update discovery prototype'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function getInputForApi(): array {
		$input = CLldRulePrototypeHelper::normalizeFormData(
			$this->getInputAll() + ['overrides' => [], 'delay_flex' => []]
		);
		$input = CLldRulePrototypeHelper::convertFormInputForApi($input);

		[$db_item] = API::DiscoveryRulePrototype()->get([
			'output' => ['templateid', 'flags'],
			'selectHosts' => ['status'],
			'itemids' => [$this->getInput('itemid')]
		]);

		return ['itemid' => $this->getInput('itemid')] + getSanitizedItemFields($db_item + $input);
	}
}
