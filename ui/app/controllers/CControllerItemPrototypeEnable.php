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


class CControllerItemPrototypeEnable extends CControllerItemPrototype {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'context'	=> 'required|in host,template',
			'field'		=> 'required|in status,discover',
			'itemids'	=> 'required|array_id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	public function doAction() {
		$output = [];
		$items = [];
		$fields = $this->getInput('field') === 'status'
			? ['status' => ITEM_STATUS_ACTIVE]
			: ['discover' => ITEM_DISCOVER];

		foreach ($this->getInput('itemids') as $itemid) {
			$items[] = ['itemid' => $itemid] + $fields;
		}

		$result = API::ItemPrototype()->update($items);
		$messages = array_column(get_and_clear_messages(), 'message');
		$count = count($items);

		if ($result) {
			$output['success']['title'] = _n('Item prototype updated', 'Item prototypes updated', $count);

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot update item prototype', 'Cannot update item prototypes', $count),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
