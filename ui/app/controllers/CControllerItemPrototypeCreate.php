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


class CControllerItemPrototypeCreate extends CControllerItemPrototype {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'parent_discoveryid' => 'required|id'
		] + static::getValidationFields();

		$ret = $this->validateInput($fields) && $this->validateInputEx();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add item prototype'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	public function doAction() {
		$output = [];
		$item = $this->getInputForApi();
		$result = API::ItemPrototype()->create($item);
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Item prototype added');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add item prototype'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function getInputForApi(): array {
		$input = $this->getFormValues();
		$input = CItemPrototypeHelper::convertFormInputForApi($input);
		[$lld_rule] = API::DiscoveryRule()->get([
			'output' => ['itemid', 'hostid'],
			'selectHosts' => ['status'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		]);
		$input['hosts'] = $lld_rule['hosts'];

		return ['hostid' => $lld_rule['hostid'], 'ruleid' => $lld_rule['itemid']] + getSanitizedItemFields($input);
	}
}
