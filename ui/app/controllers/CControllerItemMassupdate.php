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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerItemMassupdate extends CController {

	protected function checkInput() {
		$fields = [
			'context' => 'required|string|in host,template',
			'ids' => 'required|array_id',
			'prototype' => 'required|in 0,1',
			'update' => 'in 1',
			'visible' => 'array',
			'parent_discoveryid' => 'id',
			'history_mode' => 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'trends_mode' => 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'mass_update_tags' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'delay_flex' => 'array',

			// The fields used for all item types.
			'type' => 'int32',
			'value_type' => 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY]),
			'units' => 'string',
			'history' => 'string',
			'trends' => 'string',
			'valuemapid' => 'id',
			'logtimefmt' => 'string',
			'description' => 'string',
			'status' => 'in '.implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'discover' => 'in '.ZBX_PROTOTYPE_DISCOVER.','.ZBX_PROTOTYPE_NO_DISCOVER,
			'tags' => 'array',
			'preprocessing' => 'array',
			'preprocessing_action' => 'in '.implode(',', [ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE_ALL]),

			// The fields used for multiple item types.
			'interfaceid' => 'id',
			'authtype' => 'string',
			'username' => 'string',
			'password' => 'string',
			'timeout' => 'string|not_empty',
			'delay' => 'string',
			'trapper_hosts' => 'string',

			// Dependent item type specific fields.
			'master_itemid' => 'id',

			// HTTP Agent item type specific fields.
			'url' => 'string',
			'post_type' => 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts' => 'string',
			'headers' => 'array',
			'allow_traps' => 'in '.implode(',', [HTTPCHECK_ALLOW_TRAPS_ON, HTTPCHECK_ALLOW_TRAPS_OFF]),

			// JMX item type specific fields.
			'jmx_endpoint' => 'string',

			// SSH item type specific fields.
			'publickey' => 'string',
			'privatekey' => 'string'
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
		if ($this->getInput('prototype') == 1) {
			$count = API::ItemPrototype()->get([
				'countOutput' => true,
				'itemids' => $this->getInput('ids'),
				'editable' => true
			]);
		}
		else {
			$count = API::Item()->get([
				'countOutput' => true,
				'itemids' => $this->getInput('ids'),
				'editable' => true
			]);
		}

		return $count != 0;
	}

	protected function doAction() {
		$this->setResponse($this->hasInput('update') ? $this->update() : $this->form());
	}

	/**
	 * Handle item mass update action.
	 *
	 * @return CControllerResponse
	 */
	protected function update(): CControllerResponse {
		$items_count = count($this->getInput('ids'));
		$item_prototypes = (bool) $this->getInput('prototype', false);

		try {
			$input = [
				'type' => DB::getDefault('items', 'type'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'units' => DB::getDefault('items', 'units'),
				'history' => ITEM_NO_STORAGE_VALUE,
				'trends' => ITEM_NO_STORAGE_VALUE,
				'valuemapid' => 0,
				'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
				'description' => DB::getDefault('items', 'description'),
				'status' => DB::getDefault('items', 'status'),
				'discover' => DB::getDefault('items', 'discover'),
				'tags' => [],
				'preprocessing' => [],

				// The fields used for multiple item types.
				'interfaceid' => 0,
				'authtype' => DB::getDefault('items', 'authtype'),
				'username' => DB::getDefault('items', 'username'),
				'password' => DB::getDefault('items', 'password'),
				'timeout' => DB::getDefault('items', 'timeout'),
				'delay' => DB::getDefault('items', 'delay'),
				'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),

				// Dependent item type specific fields.
				'master_itemid' => 0,

				// HTTP Agent item type specific fields.
				'url' => DB::getDefault('items', 'url'),
				'post_type' => DB::getDefault('items', 'post_type'),
				'posts' => DB::getDefault('items', 'posts'),
				'headers' => [],
				'allow_traps' => DB::getDefault('items', 'allow_traps'),

				// JMX item type specific fields.
				'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),

				// SSH item type specific fields.
				'publickey' => DB::getDefault('items', 'publickey'),
				'privatekey' => DB::getDefault('items', 'privatekey')
			];

			$input = array_intersect_key($input, $this->getInput('visible', []));
			$this->getInputs($input, array_keys($input));

			$options = [];

			if (array_key_exists('tags', $input)) {
				$input['tags'] = prepareItemTags($input['tags']);

				$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
					'tag' =>	['type' => API_STRING_UTF8],
					'value' =>	['type' => API_STRING_UTF8]
				]];

				if (!CApiInputValidator::validateUniqueness($api_input_rules, $input['tags'], '/tags', $error)) {
					error($error);
					throw new Exception();
				}

				$tag_values = [];

				foreach ($input['tags'] as $tag) {
					$tag_values[$tag['tag']][] = $tag['value'];
				}

				$options['selectTags'] = ['tag', 'value'];
			}

			if (array_key_exists('preprocessing', $input)) {
				$input['preprocessing'] =
					$this->getInput('preprocessing_action', ZBX_ACTION_REPLACE) == ZBX_ACTION_REMOVE_ALL
						? []
						: normalizeItemPreprocessingSteps($input['preprocessing']);
			}

			if (array_key_exists('delay', $input)) {
				$delay_flex = $this->getInput('delay_flex', []);

				if (!isValidCustomIntervals($delay_flex)) {
					throw new Exception();
				}

				$input['delay'] = getDelayWithCustomIntervals($input['delay'], $delay_flex);
			}

			if (array_key_exists('headers', $input)) {
				$input['headers'] = prepareItemHeaders($input['headers']);
			}

			$itemids = $this->getInput('ids');

			if ($item_prototypes) {
				$db_items = API::ItemPrototype()->get([
					'output' => ['type', 'key_', 'value_type', 'templateid', 'authtype', 'allow_traps', 'snmp_oid'],
					'selectHosts' => ['status'],
					'itemids' => $itemids,
					'preservekeys' => true
				] + $options);
			}
			else {
				$db_items = API::Item()->get([
					'output' => ['type', 'key_', 'value_type', 'templateid', 'flags', 'authtype', 'allow_traps',
						'snmp_oid'
					],
					'selectHosts' => ['status'],
					'itemids' => $itemids,
					'preservekeys' => true
				] + $options);
			}

			$items = [];

			foreach ($itemids as $itemid) {
				$db_item = $db_items[$itemid];

				if ($item_prototypes) {
					$db_item['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
				}

				$item = array_intersect_key($input, getSanitizedItemFields($input + $db_item));

				if (array_key_exists('tags', $input)) {
					$item['tags'] = $this->getTagsToUpdate($db_item, $tag_values);
				}

				if ($item) {
					$items[] = ['itemid' => $itemid] + $item;
				}
			}

			$result = true;

			if ($items) {
				if ($item_prototypes) {
					$response = API::ItemPrototype()->update($items);
				}
				else {
					$response = API::Item()->update($items);
				}

				if ($response === false) {
					throw new Exception();
				}
			}
		}
		catch (Exception $e) {
			$result = false;
			CMessageHelper::setErrorTitle(
				$item_prototypes
					? _n('Cannot update item prototype', 'Cannot update item prototypes', $items_count)
					: _n('Cannot update item', 'Cannot update items', $items_count)
			);
		}

		if ($result) {
			$messages = CMessageHelper::getMessages();
			$output = ['title' => $item_prototypes
				? _n('Item prototype updated', 'Item prototypes updated', $items_count)
				: _n('Item updated', 'Items updated', $items_count)
			];

			if (count($messages)) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => CMessageHelper::getTitle(),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		return (new CControllerResponseData(['main_block' => json_encode($output)]))->disableView();
	}

	/**
	 * Get item tags to update or null if no tags to update found.
	 *
	 * @param array $db_item
	 * @param array $tag_values
	 *
	 * @return array
	 */
	private function getTagsToUpdate(array $db_item, array $tag_values): ?array {
		$tags = [];

		switch ($this->getInput('mass_update_tags', ZBX_ACTION_ADD)) {
			case ZBX_ACTION_ADD:
				foreach ($db_item['tags'] as $db_tag) {
					if (array_key_exists($db_tag['tag'], $tag_values)
							&& in_array($db_tag['value'], $tag_values[$db_tag['tag']])) {
						unset($tag_values[$db_tag['tag']][$db_tag['value']]);
					}
				}

				foreach ($tag_values as $tag => $values) {
					foreach ($values as $value) {
						$tags[] = ['tag' => (string) $tag, 'value' => $value];
					}
				}

				$tags = array_merge($db_item['tags'], $tags);
				break;

			case ZBX_ACTION_REPLACE:
				foreach ($tag_values as $tag => $values) {
					foreach ($values as $value) {
						$tags[] = ['tag' => (string) $tag, 'value' => $value];
					}
				}

				CArrayHelper::sort($tags, ['tag', 'value']);
				CArrayHelper::sort($db_item['tags'], ['tag', 'value']);
				break;

			case ZBX_ACTION_REMOVE:
				foreach ($db_item['tags'] as $db_tag) {
					if (!array_key_exists($db_tag['tag'], $tag_values)
							|| !in_array($db_tag['value'], $tag_values[$db_tag['tag']])) {
						$tags[] = ['tag' => $db_tag['tag'], 'value' => $db_tag['value']];
					}
				}
				break;
		}

		return $tags;
	}

	/**
	 * Handle item mass update form initialization.
	 *
	 * @return CControllerResponse
	 */
	protected function form(): CControllerResponse {
		$data = [
			'action' => $this->getAction(),
			'context' => $this->getInput('context'),
			'delay_flex' => [['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE]],
			'ids' => $this->getInput('ids'),
			'initial_item_type' => null,
			'interfaceids' => [],
			'interfaces' => [],
			'multiple_interface_types' => false,
			'prototype' => $this->getInput('prototype'),
			'user' => ['debug_mode' => $this->getDebugMode()],
			'title' => _('Mass update')
		];

		if ($data['prototype']) {
			$data['parent_discoveryid'] = $this->getInput('parent_discoveryid', 0);
			$data += [
				'location_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'item.prototype.list')
					->setArgument('context', $this->getInput('context'))
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->getUrl(),
				'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM_PROTOTYPE,
				'preprocessing_types' => CItemPrototype::SUPPORTED_PREPROCESSING_TYPES
			];
		}
		else {
			$data += [
				'location_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'item.list')
					->setArgument('context', $this->getInput('context'))
					->getUrl(),
				'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM,
				'preprocessing_types' => CItem::SUPPORTED_PREPROCESSING_TYPES
			];
		}

		if ($data['context'] === 'host') {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'flags'],
				'itemids' => $data['ids'],
				'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
				'limit' => 2
			]);
			$host = reset($hosts);
			$data['discovered_host'] = ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED);
			$data['hostid'] = $host['hostid'];
			$data['interfaces'] = $host['interfaces'];
			CArrayHelper::sort($data['interfaces'], [['field' => 'main', 'order' => ZBX_SORT_DOWN]]);

			// Interfaceids for js.
			foreach ($host['interfaces'] as $interface) {
				$data['interfaceids'][$interface['type']][] = $interface['interfaceid'];
			}
		}
		else {
			$hosts = API::Template()->get([
				'output' => ['templateid'],
				'itemids' => $data['ids'],
				'limit' => 2
			]);
			$host = reset($hosts);
			$data['hostid'] = $host['templateid'];
		}

		$data['single_host_selected'] = (count($hosts) == 1);

		if ($data['context'] === 'host' && $data['single_host_selected']) {
			$entity = $data['prototype'] ? API::ItemPrototype() : API::Item();
			$items = $entity->get([
				'output' => ['itemid', 'type'],
				'itemids' => $data['ids']
			]);

			$item_types = array_column($items, 'type', 'type');
			$item_interface_types = array_intersect_key(
				itemTypeInterface() + array_fill_keys($item_types, false),
				$item_types
			);
			$initial_type = count($item_interface_types) ? min(array_keys($item_interface_types)) : 0;
			$data['initial_item_type'] = $initial_type;
			$data['multiple_interface_types'] = (count(array_unique($item_interface_types)) > 1);
			$data['type'] = $initial_type;
		}

		$data['item_types'] = item_type2str();
		unset($data['item_types'][ITEM_TYPE_HTTPTEST], $data['item_types'][ITEM_TYPE_SCRIPT],
			$data['item_types'][ITEM_TYPE_BROWSER]
		);

		return new CControllerResponseData($data);
	}
}
