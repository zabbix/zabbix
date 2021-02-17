<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'allow_traps' => 'in '.implode(',', [HTTPCHECK_ALLOW_TRAPS_ON, HTTPCHECK_ALLOW_TRAPS_OFF]),
			'application_prototypes' => 'array',
			'applications' => 'array',
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
			'massupdate_app_action' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'massupdate_app_prot_action' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
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

		$ret = $this->validateInput($fields);

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
	 * Get array of updated items or item prototypes hosts or templates.
	 *
	 * @return array
	 */
	protected function getHostsOrTemplates(): array {
		$options = ['itemids' => $this->getInput('ids')];

		if ($this->getInput('prototype')) {
			$options['selectApplicationPrototypes'] = ['application_prototypeid', 'name'];
		}

		if ($this->getInput('context') === 'host') {
			$options['output'] = ['hostid'];
			$result = API::Host()->get($options);
		}
		else {
			$options['output'] = ['templateid'];
			$result = CArrayHelper::renameObjectsKeys(API::Template()->get($options), ['templateid' => 'hostid']);
		}

		return $result;
	}

	/**
	 * Get array of updated items or item prototypes.
	 *
	 * @return array
	 */
	protected function getItemsOrPrototypes(): array {
		$options = [
			'output' => ['itemid', 'type'],
			'itemids' => $this->getInput('ids'),
			'selectApplications' => ['applicationid'],
			'preservekeys' => true
		];

		if ($this->getInput('prototype')) {
			$options['selectApplicationPrototypes'] = ['name'];
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
		$applicationids = [];
		$applications_action = $this->getInput('massupdate_app_action', -1);
		$application_prototypes_action = $this->getInput('massupdate_app_prot_action', -1);
		$input = [
			'allow_traps' => HTTPCHECK_ALLOW_TRAPS_OFF,
			'application_prototypes' => [],
			'applications' => [],
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
			'timeout' => '',
			'trapper_hosts' => '',
			'trends' => ITEM_NO_STORAGE_VALUE,
			'type' => 0,
			'units' => '',
			'url' => '',
			'username' => '',
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'valuemapid' => 0,
			'interfaceid' => ''
		];
		$this->getInputs($input, array_keys($input));

		if ($this->getInput('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['trends'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($this->getInput('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['history'] = ITEM_NO_STORAGE_VALUE;
		}

		if (!$prototype) {
			unset($input['application_prototypes']);
		}

		$input = array_intersect_key($input, $this->getInput('visible', []));

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

			$hosts = $this->getHostsOrTemplates();
			$host = array_shift($hosts);

			if ($prototype && $hosts) {
				throw new Exception();
			}

			if (array_key_exists('applications', $input)) {
				if ($applications_action == ZBX_ACTION_ADD || $applications_action == ZBX_ACTION_REPLACE) {
					$new_applications = [];

					foreach ($input['applications'] as $application) {
						if (is_array($application) && array_key_exists('new', $application)) {
							$new_applications[] = [
								'name' => $application['new'],
								'hostid' => $host['hostid']
							];
						}
						else {
							$applicationids[] = $application;
						}
					}

					if ($new_applications) {
						$new_application = API::Application()->create($new_applications);

						if (!$new_application) {
							throw new Exception();
						}

						$applicationids = array_merge($applicationids, $new_application['applicationids']);
					}
				}
				else {
					$applicationids = $input['applications'];
				}
			}

			if (array_key_exists('application_prototypes', $input)) {
				$host_app_prototypes = array_column($host['applicationPrototypes'], 'name', 'application_prototypeid');
				$app_prototypes = [];

				foreach ($input['application_prototypes'] as $app_prototype) {
					if (is_array($app_prototype) && array_key_exists('new', $app_prototype)) {
						$app_prototypes[] = $app_prototype['new'];
					}
					else if (array_key_exists($app_prototype, $host_app_prototypes)) {
						$app_prototypes[] = $host_app_prototypes[$app_prototype]['name'];
					}
				}

				$input['application_prototypes'] = $app_prototypes;
			}

			if (array_key_exists('headers', $input) && $input['headers']) {
				$headers = (count($input['headers']['name']) == count($input['headers']['value']))
					? array_combine($input['headers']['name'], $input['headers']['value'])
					: [];
				$input['headers'] = array_filter($headers, 'strlen');
			}

			if (array_key_exists('preprocessing', $input) && $input['preprocessing']) {
				foreach ($input['preprocessing'] as &$step) {
					switch ($step['type']) {
						case ZBX_PREPROC_MULTIPLIER:
						case ZBX_PREPROC_PROMETHEUS_TO_JSON:
							$step['params'] = trim($step['params'][0]);
							break;

						case ZBX_PREPROC_RTRIM:
						case ZBX_PREPROC_LTRIM:
						case ZBX_PREPROC_TRIM:
						case ZBX_PREPROC_XPATH:
						case ZBX_PREPROC_JSONPATH:
						case ZBX_PREPROC_VALIDATE_REGEX:
						case ZBX_PREPROC_VALIDATE_NOT_REGEX:
						case ZBX_PREPROC_ERROR_FIELD_JSON:
						case ZBX_PREPROC_ERROR_FIELD_XML:
						case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
						case ZBX_PREPROC_SCRIPT:
							$step['params'] = $step['params'][0];
							break;

						case ZBX_PREPROC_VALIDATE_RANGE:
						case ZBX_PREPROC_PROMETHEUS_PATTERN:
							foreach ($step['params'] as &$param) {
								$param = trim($param);
							}
							unset($param);

							$step['params'] = implode("\n", $step['params']);
							break;

						case ZBX_PREPROC_REGSUB:
						case ZBX_PREPROC_ERROR_FIELD_REGEX:
						case ZBX_PREPROC_STR_REPLACE:
							$step['params'] = implode("\n", $step['params']);
							break;

						// ZBX-16642
						case ZBX_PREPROC_CSV_TO_JSON:
							if (!array_key_exists(2, $step['params'])) {
								$step['params'][2] = ZBX_PREPROC_CSV_NO_HEADER;
							}
							$step['params'] = implode("\n", $step['params']);
							break;

						default:
							$step['params'] = '';
					}

					$step += [
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					];
				}
				unset($step);
			}

			$items_to_update = [];
			$items = $this->getItemsOrPrototypes();

			foreach ($ids as $id) {
				if ($prototype || $items[$id]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
					if (array_key_exists('applications', $input) && $applicationids) {
						$db_applicationids = array_column($items[$id]['applications'], 'applicationid');

						if ($applications_action == ZBX_ACTION_ADD) {
							$upd_applicationids = array_merge($applicationids, $db_applicationids);
						}
						else if ($applications_action == ZBX_ACTION_REPLACE) {
							$upd_applicationids = $applicationids;
						}
						else if ($applications_action == ZBX_ACTION_REMOVE) {
							$upd_applicationids = array_diff($db_applicationids, $applicationids);
						}

						$input['applications'] = array_keys(array_flip($upd_applicationids));
					}
					else if ($applications_action == ZBX_ACTION_ADD || $applications_action == ZBX_ACTION_REMOVE) {
						unset($input['applications']);
					}

					$update_item = ['itemid' => $id];

					if (array_key_exists('application_prototypes', $input)) {
						$db_app_prototypes = $items[$id]['applicationPrototypes'];

						if ($application_prototypes_action == ZBX_ACTION_ADD) {
							$app_prototypes = array_merge($input['application_prototypes'], $db_app_prototypes);
						}
						else if ($application_prototypes_action == ZBX_ACTION_REPLACE) {
							$app_prototypes = $input['application_prototypes'];
						}
						else if ($application_prototypes_action == ZBX_ACTION_REMOVE) {
							$app_prototypes = array_diff($db_app_prototypes, $input['application_prototypes']);
						}

						$update_item['applicationPrototypes'] = array_keys(array_flip($app_prototypes));
					}

					$update_item += $input;
					unset($update_item['application_prototypes']);
					$type = array_key_exists('type', $input) ? $input['type'] : $items[$id]['type'];

					if ($type != ITEM_TYPE_JMX) {
						unset($update_item['jmx_endpoint']);
					}

					if ($type != ITEM_TYPE_HTTPAGENT && $type != ITEM_TYPE_SCRIPT) {
						unset($update_item['timeout']);
					}

					$items_to_update[] = $update_item;
				}
				else if (array_key_exists('status', $input)) {
					$items_to_update[] = ['itemid' => $id, 'status' => $input['status']];
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
			$output['errors'] = makeMessageBox(false, filter_messages(), CMessageHelper::getTitle())->toString();
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
