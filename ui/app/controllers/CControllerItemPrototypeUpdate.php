<?php declare(strict_types=0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerItemPrototypeUpdate extends CControllerItemPrototype {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'itemid'	=> 'required|id'
		] + static::getValidationFields();

		$ret = $this->validateInput($fields) && $this->validateInputEx();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update item prototype'),
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
		$result = API::ItemPrototype()->update($item);
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Item prototype updated');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update item prototype'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function getInputForApi(): array {
		$input = $this->getFormValues();
		$input = CItemPrototypeHelper::convertFormInputForApi($input);
		[$db_item] = API::ItemPrototype()->get([
			'output' => ['templateid', 'flags'],
			'selectHosts' => ['status'],
			'itemids' => $this->getInput('itemid')
		]);

		return ['itemid' => $this->getInput('itemid')] + getSanitizedItemFields($db_item + $input);
	}
}
