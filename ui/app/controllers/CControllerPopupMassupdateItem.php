<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupMassupdateItem extends CController {

	private $db_items = [];

	protected function checkInput() {
		$fields = [
			'allow_traps' => 'in '.implode(',', [HTTPCHECK_ALLOW_TRAPS_ON, HTTPCHECK_ALLOW_TRAPS_OFF]),
			'authtype' => 'string',
			'context' => 'required|string|in host,template',
			'delay' => 'string',
			'delay_flex' => 'array',
			'description' => 'string',
			'discover' => 'in '.ZBX_PROTOTYPE_DISCOVER.','.ZBX_PROTOTYPE_NO_DISCOVER,
			'headers' => 'array',
			'history' => 'string',
			'history_mode' => 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'ids' => 'required|array_id',
			'interfaceid' => 'id',
			'jmx_endpoint' => 'string',
			'logtimefmt' => 'string',
			'mass_update_tags' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'master_itemid' => 'id',
			'parent_discoveryid' => 'id',
			'password' => 'string',
			'post_type' => 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts' => 'string',
			'preprocessing' => 'array',
			'privatekey' => 'string',
			'prototype' => 'required|in 0,1',
			'publickey' => 'string',
			'status' => 'in '.implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'tags' => 'array',
			'trapper_hosts' => 'string',
			'trends' => 'string',
			'trends_mode' => 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'timeout' => 'string',
			'type' => 'int32',
			'units' => 'string',
			'update' => 'in 1',
			'url' => 'string',
			'username' => 'string',
			'value_type' => 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'valuemapid' => 'id',
			'visible' => 'array'
		];

		if (getRequest('interfaceid') == INTERFACE_TYPE_OPT) {
			unset($fields['interfaceid']);
			unset($_REQUEST['interfaceid']);
		}

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
		$entity = ($this->getInput('prototype') == 1) ? API::ItemPrototype() : API::Item();

		$this->db_items = $entity->get([
			'output' => array_unique(array_merge(CItemBaseHelper::CONDITION_FIELDS, [
				'allow_traps',
				'authtype',
				'delay',
				'description',
				'discover',
				'discover',
				'history_mode',
				'history',
				'interfaceid',
				'jmx_endpoint',
				'logtimefmt',
				'master_itemid',
				'name',
				'parent_discoveryid',
				'password',
				'post_type',
				'posts',
				'privatekey',
				'publickey',
				'ruleid',
				'status',
				'timeout',
				'trapper_hosts',
				'trends_mode',
				'trends',
				'units',
				'url',
				'username',
				'value_type',
				'valuemapid'
			])),
			'selectTags' => ['tag', 'value'],
			'selectHosts' => ['status'],
			'itemids' => $this->getInput('ids'),
			'editable' => true
		]);

		return (bool) $this->db_items;
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
		$result = false;
		$is_prototype = (bool) $this->getInput('prototype');

		$input_submitted = array_intersect_key(
			$this->getInputAll(),
			array_merge($this->getInput('visible', []), ['mass_update_tags' => true])
		);
		$input_submitted = array_fill(0, count($this->db_items), $input_submitted);

		$items = CItemBaseHelper::sanitizeItems($input_submitted, $this->db_items);
		DBstart();

		if ($items) {
			// In case user chose unrelated fields, relay the errors.
			foreach ($items as $i => $item) {
				unset($input_submitted[$i]['mass_update_tags']);
				$items[$i] = $item + $input_submitted[$i];
			}

			$result = (bool) ($is_prototype ? API::ItemPrototype()->update($items) : API::Item()->update($items));
		}

		if (DBend($result)) {
			$output = ['title' => $is_prototype ? _('Item prototypes updated') : _('Items updated')];
			$messages = CMessageHelper::getMessages();

			if (count($messages)) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => $is_prototype ? _('Cannot update item prototypes') : _('Cannot update items'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		return (new CControllerResponseData(['main_block' => json_encode($output)]))->disableView();
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
				'location_url' => (new CUrl('disc_prototypes.php'))
					->setArgument('context', $this->getInput('context'))
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->getUrl(),
				'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM_PROTOTYPE,
				'preprocessing_types' => CItemPrototype::SUPPORTED_PREPROCESSING_TYPES
			];
		}
		else {
			$data += [
				'location_url' => (new CUrl('items.php'))
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
		unset($data['item_types'][ITEM_TYPE_HTTPTEST], $data['item_types'][ITEM_TYPE_SCRIPT]);

		return new CControllerResponseData($data);
	}
}
