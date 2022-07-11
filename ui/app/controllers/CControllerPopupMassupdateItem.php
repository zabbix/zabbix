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

	private $opt_interfaceid_expected = false;

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

		$this->opt_interfaceid_expected = (getRequest('interfaceid') == INTERFACE_TYPE_OPT);

		if ($this->opt_interfaceid_expected) {
			unset($fields['interfaceid']);
			unset($_REQUEST['interfaceid']);
		}

		$ret = $this->validateInput($fields);

		if ($ret && $this->opt_interfaceid_expected) {
			if ($this->hasInput('type')) {
				$item_types = [$this->getInput('type')];
			}
			else {
				$options = [
					'output' => ['type'],
					'itemids' => $this->getInput('ids')
				];
				$item_types = (bool) $this->getInput('prototype')
					? API::ItemPrototype()->get($options)
					: API::Item()->get($options);

				$item_types = array_column($item_types, 'type', 'type');
			}

			foreach ($item_types as $item_type) {
				if (itemTypeInterface($item_type) != INTERFACE_TYPE_OPT) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Host interface'),
						interfaceType2str(INTERFACE_TYPE_OPT)
					));
					$ret = false;

					break;
				}
			}
		}

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		$entity = ($this->getInput('prototype') == 1) ? API::ItemPrototype() : API::Item();

		return (bool) $entity->get([
			'output' => [],
			'itemids' => $this->getInput('ids'),
			'editable' => true,
			'limit' => 1
		]);
	}

	protected function doAction() {
		$this->setResponse($this->hasInput('update') ? $this->update() : $this->form());
	}

	/**
	 * Get array of updated items or item prototypes.
	 *
	 * @return array
	 */
	protected function getItemsOrPrototypes(): array {
		$options = [
			'output' => ['itemid', 'type'],
			'selectTags' => ['tag', 'value'],
			'itemids' => $this->getInput('ids'),
			'preservekeys' => true
		];

		if ($this->getInput('prototype')) {
			$result = API::ItemPrototype()->get($options);
		}
		else {
			$options['output'][] = 'flags';
			$result = API::Item()->get($options);
		}

		return $result;
	}

	/**
	 * Update item or item prototype, return update action status.
	 *
	 * @param array $data  Array of item or item prototypes data to update.
	 * @return bool
	 */
	protected function updateItemOrPrototype(array $data): bool {
		return (bool) ($this->getInput('prototype') ? API::ItemPrototype()->update($data) : API::Item()->update($data));
	}

	/**
	 * Handle item mass update action.
	 *
	 * @return CControllerResponse
	 */
	protected function update(): CControllerResponse {
		$result = true;
		$ids = $this->getInput('ids');
		$prototype = (bool) $this->getInput('prototype');
		$input = [
			'allow_traps' => HTTPCHECK_ALLOW_TRAPS_OFF,
			'authtype' => '',
			'delay' => DB::getDefault('items', 'delay'),
			'description' => '',
			'discover' => ZBX_PROTOTYPE_DISCOVER,
			'headers' => [],
			'history' => ITEM_NO_STORAGE_VALUE,
			'jmx_endpoint' => '',
			'logtimefmt' => '',
			'master_itemid' => 0,
			'password' => '',
			'post_type' => ZBX_POSTTYPE_RAW,
			'posts' => '',
			'preprocessing' => [],
			'privatekey' => '',
			'publickey' => '',
			'status' => ITEM_STATUS_ACTIVE,
			'tags' => [],
			'timeout' => '',
			'trapper_hosts' => '',
			'trends' => ITEM_NO_STORAGE_VALUE,
			'type' => 0,
			'units' => '',
			'url' => '',
			'username' => '',
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'valuemapid' => 0,
			'interfaceid' => $this->opt_interfaceid_expected ? 0 : ''
		];
		$this->getInputs($input, array_keys($input));

		if ($this->getInput('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['trends'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($this->getInput('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['history'] = ITEM_NO_STORAGE_VALUE;
		}

		$input = array_intersect_key($input, $this->getInput('visible', []));

		if (array_key_exists('tags', $input)) {
			$input['tags'] = array_filter($input['tags'], function ($tag) {
				return ($tag['tag'] !== '' || $tag['value'] !== '');
			});
		}

		try {
			DBstart();
			$delay_flex = $this->getInput('delay_flex', []);

			if (array_key_exists('delay', $input) && $delay_flex) {
				$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
				$time_period_parser = new CTimePeriodParser(['usermacros' => true]);
				$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

				foreach ($delay_flex as $interval) {
					if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
						if ($interval['delay'] === '' && $interval['period'] === '') {
							continue;
						}

						if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
							info(_s('Invalid interval "%1$s".', $interval['delay']));
							throw new Exception();
						}
						elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
							info(_s('Invalid interval "%1$s".', $interval['period']));
							throw new Exception();
						}

						$input['delay'] .= ';'.$interval['delay'].'/'.$interval['period'];
					}
					else {
						if ($interval['schedule'] === '') {
							continue;
						}

						if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
							info(_s('Invalid interval "%1$s".', $interval['schedule']));
							throw new Exception();
						}

						$input['delay'] .= ';'.$interval['schedule'];
					}
				}
			}

			if (array_key_exists('headers', $input) && $input['headers']) {
				$input['headers']['value'] += array_fill_keys(array_keys($input['headers']['name']), '');

				$headers = [];
				foreach ($input['headers']['name'] as $i => $header_name) {
					if ($header_name !== '' || $input['headers']['value'][$i] !== '') {
						$headers[$header_name] = $input['headers']['value'][$i];
					}
				}
				$input['headers'] = $headers;
			}

			if (array_key_exists('preprocessing', $input) && $input['preprocessing']) {
				$input['preprocessing'] = normalizeItemPreprocessingSteps($input['preprocessing']);
			}

			$items_to_update = [];
			$items = $this->getItemsOrPrototypes();

			foreach ($ids as $id) {
				$update_item = [];

				if (array_key_exists('tags', $input)) {
					switch ($this->getInput('mass_update_tags', ZBX_ACTION_ADD)) {
						case ZBX_ACTION_ADD:
							$unique_tags = [];
							foreach (array_merge($items[$id]['tags'], $input['tags']) as $tag) {
								$unique_tags[$tag['tag']][$tag['value']] = $tag;
							}

							foreach ($unique_tags as $tags_by_name) {
								foreach ($tags_by_name as $tag) {
									$update_item['tags'][] = $tag;
								}
							}
							break;

						case ZBX_ACTION_REPLACE:
							$update_item['tags'] = $input['tags'];
							break;

						case ZBX_ACTION_REMOVE:
							$diff_tags = [];
							foreach ($items[$id]['tags'] as $a) {
								foreach ($input['tags'] as $b) {
									if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
										continue 2;
									}
								}

								$diff_tags[] = $a;
							}
							$update_item['tags'] = $diff_tags;
							break;
					}
				}

				if ($prototype || $items[$id]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
					$update_item += $input;

					$type = array_key_exists('type', $input) ? $input['type'] : $items[$id]['type'];

					if ($type != ITEM_TYPE_JMX) {
						unset($update_item['jmx_endpoint']);
					}

					if ($type != ITEM_TYPE_HTTPAGENT && $type != ITEM_TYPE_SCRIPT) {
						unset($update_item['timeout']);
					}
				}
				else if (array_key_exists('status', $input)) {
					$items_to_update[] = ['itemid' => $id, 'status' => $input['status']];
				}

				if ($update_item) {
					$items_to_update[] = ['itemid' => $id] + $update_item;
				}
			}

			if ($items_to_update && !$this->updateItemOrPrototype($items_to_update)) {
				throw new Exception();
			}
		}
		catch (Exception $e) {
			$result = false;
			CMessageHelper::setErrorTitle($prototype ? _('Cannot update item prototypes') : _('Cannot update items'));
		}

		if (DBend($result)) {
			$messages = CMessageHelper::getMessages();
			$output = ['title' => $prototype ? _('Item prototypes updated') : _('Items updated')];

			if (count($messages)) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['errors'] = makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
				->toString();
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
