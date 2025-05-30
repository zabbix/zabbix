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

	private $item = [];

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
		if ($this->hasInput('itemid')) {
			$options = [
				'output' => ['itemid', 'templateid', 'hostid', 'flags'],
				'selectDiscoveryData' => ['parent_itemid'],
				'itemids' => [$this->getInput('itemid')]
			];

			$items = API::Item()->get($options);

			if (!$items) {
				if ($this->getInput('show_inherited_tags', 0) == 1) {
					$options['selectDiscoveryRule'] = ['itemid', 'templateid', 'flags'];
					$options['selectDiscoveryRulePrototype'] = ['itemid', 'templateid', 'flags'];
				}

				$items = API::ItemPrototype()->get($options);
			}

			if (!$items) {
				return false;
			}

			$this->item = reset($items);
		}
		else {
			$this->item =[
				'itemid' => 0,
				'templateid' => 0,
				'hostid' => $this->getInput('hostid', 0),
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL
			];
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'tags' => [],
			'show_inherited_tags' => 0,
			'source' => 'item',
			'has_inline_validation' => true
		];
		$this->getInputs($data, array_keys($data));

		$data['tags'] = array_filter($data['tags'], static fn($tag) => $tag['tag'] !== '' || $tag['value'] !== '');
		$data['readonly'] = $this->item['flags'] & ZBX_FLAG_DISCOVERY_CREATED;

		if ($data['show_inherited_tags'] == 1) {
			if ($this->item['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
				$db_parent = API::ItemPrototype()->get([
					'output' => [],
					'selectDiscoveryRule' => ['itemid', 'templateid', 'flags'],
					'selectDiscoveryRulePrototype' => ['itemid', 'templateid', 'flags'],
					'itemids' => $this->item['discoveryData']['parent_itemid'],
					'nopermissions' => true
				]);
				$db_parent = reset($db_parent);

				$this->item['parent_lld'] = $db_parent['discoveryRule'] ?: $db_parent['discoveryRulePrototype'];
			}
			elseif ($this->item['flags'] & ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$this->item['parent_lld'] = $this->item['discoveryRule'] ?: $this->item['discoveryRulePrototype'];
			}

			$data['tags'] = CItemHelper::addInheritedTags($this->item, $data['tags']);
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];
		$this->setResponse(new CControllerResponseData($data));
	}
}
