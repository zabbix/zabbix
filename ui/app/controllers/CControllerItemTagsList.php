<?php
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

class CControllerItemTagsList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setPostContentType(static::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'tags'					=> 'array',
			'show_inherited_tags'	=> 'in 0,1',
			'hostid'				=> 'id',
			'itemid'				=> 'id'
		];

		$ret = $this->validateInput($fields);

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

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$item = [
			'itemid' => 0,
			'templateid' => 0,
			'hostid' => $this->getInput('hostid', 0),
			'flag' => ZBX_FLAG_DISCOVERY_NORMAL
		];
		$data = [
			'tags' => [],
			'show_inherited_tags' => 0,
			'source' => 'item'
		];
		$this->getInputs($data, array_keys($data));

		$data['tags'] = array_filter($data['tags'], function ($tag) {
			return $tag['tag'] !== '' || $tag['value'] !== '';
		});

		if ($this->hasInput('itemid')) {
			$items = API::Item()->get([
				'output' => ['itemid', 'templateid', 'hostid', 'flags'],
				'selectDiscoveryRule' => ['name', 'templateid'],
				'itemids' => [$this->getInput('itemid')]
			]);

			if (!$items) {
				$items = API::ItemPrototype()->get([
					'output' => ['itemid', 'templateid', 'hostid'],
					'selectDiscoveryRule' => ['name', 'templateid'],
					'itemids' => [$this->getInput('itemid')]
				]);
			}
			else if ($items[0]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				unset($items[0]['discoveryRule']);
			}
			else if ($items[0]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$data['readonly'] = 1;
			}

			if ($items) {
				$item = reset($items);
			}
		}

		if ($data['show_inherited_tags']) {
			$data['tags'] = CItemHelper::addInheritedTags($item, $data['tags']);
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];
		$this->setResponse(new CControllerResponseData($data));
	}
}
