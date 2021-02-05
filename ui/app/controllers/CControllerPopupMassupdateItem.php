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
			'ids' => 'required|array_id',
			'hostid' => 'required|id',
			'update' => 'in 1',
			'visible' => 'array',
			'description' => 'string',
			'delay' => 'string',
			'history' => 'string',
			'trapper_hosts' => 'string',
			'units' => 'string',
			'authtype' => 'string',
			'jmx_endpoint' => 'string',
			'username' => 'string',
			'password' => 'string',
			'publickey' => 'string',
			'privatekey' => 'string',
			'trends' => 'string',
			'logtimefmt' => 'string',
			'url' => 'string',
			'post_type' => 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts' => 'string',
			'delay_flex' => 'array',
			'applications' => 'array',
			'preprocessing' => 'array',
			'headers' => 'array',
			'status' => 'in '.implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'type' => 'int32',
			'interfaceid' => 'id',
			'value_type' => 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'valuemapid' => 'id',
			'master_itemid' => 'id',
			'allow_traps' => 'in '.implode(',', [HTTPCHECK_ALLOW_TRAPS_ON, HTTPCHECK_ALLOW_TRAPS_OFF]),
			'massupdate_app_action' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'preprocessing_test_type' => 'int32',
			'trends_mode' => 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'history_mode' => 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'context' => 'required|string|in '.implode(',', ['host', 'template'])
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
		$items = API::Item()->get([
			'output' => [],
			'selectHosts' => ['hostid', 'status'],
			'itemids' => $this->getInput('ids'),
			'editable' => true
		]);

		if (!$items) {
			return false;
		}

		$hosts = API::Host()->get([
			'output' => [],
			'hostids' => [$this->getInput('hostid')],
			'templated_hosts' => true,
			'editable' => true
		]);

		if (!$hosts) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		if ($this->hasInput('update')) {
			$output = [];

			$visible = $this->getInput('visible', []);
			$itemids = $this->getInput('ids');

			$result = true;

			if (isset($visible['delay'])) {
				$delay = $this->getInput('delay', DB::getDefault('items', 'delay'));

				if ($this->hasInput('delay_flex')) {
					$intervals = [];
					$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
					$time_period_parser = new CTimePeriodParser(['usermacros' => true]);
					$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

					foreach ($this->getInput('delay_flex') as $interval) {
						if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
							if ($interval['delay'] === '' && $interval['period'] === '') {
								continue;
							}

							if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
								$result = false;
								info(_s('Invalid interval "%1$s".', $interval['delay']));
								break;
							}
							elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
								$result = false;
								info(_s('Invalid interval "%1$s".', $interval['period']));
								break;
							}

							$intervals[] = $interval['delay'].'/'.$interval['period'];
						}
						else {
							if ($interval['schedule'] === '') {
								continue;
							}

							if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
								$result = false;
								info(_s('Invalid interval "%1$s".', $interval['schedule']));
								break;
							}

							$intervals[] = $interval['schedule'];
						}
					}

					if ($intervals) {
						$delay .= ';'.implode(';', $intervals);
					}
				}
			}
			else {
				$delay = null;
			}

			$applications = $this->getInput('applications', []);
			$applicationids = [];

			if ($result) {
				try {
					DBstart();

					if (array_key_exists('applications', $visible)) {
						$massupdate_app_action = $this->getInput('massupdate_app_action');

						if ($massupdate_app_action == ZBX_ACTION_ADD || $massupdate_app_action == ZBX_ACTION_REPLACE) {
							$new_applications = [];

							foreach ($applications as $application) {
								if (is_array($application) && array_key_exists('new', $application)) {
									$new_applications[] = [
										'name' => $application['new'],
										'hostid' => $this->getInput('hostid')
									];
								}
								else {
									$applicationids[] = $application;
								}
							}

							if ($new_applications) {
								if ($new_application = API::Application()->create($new_applications)) {
									$applicationids = array_merge($applicationids, $new_application['applicationids']);
								}
								else {
									throw new Exception();
								}
							}
						}
						else {
							foreach ($applications as $application) {
								$applicationids[] = $application;
							}
						}
					}

					$items = API::Item()->get([
						'output' => ['itemid', 'flags', 'type'],
						'selectApplications' => ['applicationid'],
						'itemids' => $itemids,
						'preservekeys' => true
					]);

					$items_to_update = [];

					if ($items) {
						$item = [
							'interfaceid' => $this->getInput('interfaceid', 0),
							'description' => $this->getInput('description', ''),
							'delay' => $delay,
							'history' => ($this->getInput('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
								? ITEM_NO_STORAGE_VALUE
								: $this->getInput('history', ''),
							'type' => $this->getInput('type', 0),
							'snmp_oid' => $this->getInput('snmp_oid', 0),
							'value_type' => $this->getInput('value_type', ITEM_VALUE_TYPE_UINT64),
							'trapper_hosts' => $this->getInput('trapper_hosts', ''),
							'units' => $this->getInput('units', ''),
							'trends' => ($this->getInput('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
								? ITEM_NO_STORAGE_VALUE
								: $this->getInput('trends', ''),
							'logtimefmt' => $this->getInput('logtimefmt', ''),
							'valuemapid' => $this->getInput('valuemapid', 0),
							'authtype' => $this->getInput('authtype', ''),
							'jmx_endpoint' => $this->getInput('jmx_endpoint', ''),
							'username' => $this->getInput('username', ''),
							'password' => $this->getInput('password', ''),
							'publickey' => $this->getInput('publickey', ''),
							'privatekey' => $this->getInput('privatekey', ''),
							'applications' => [],
							'status' => $this->getInput('status', ITEM_STATUS_ACTIVE),
							'master_itemid' => $this->getInput('master_itemid', 0),
							'url' =>  $this->getInput('url', ''),
							'post_type' => $this->getInput('post_type', ZBX_POSTTYPE_RAW),
							'posts' => $this->getInput('posts', ''),
							'headers' => $this->getInput('headers', []),
							'allow_traps' => $this->getInput('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
							'preprocessing' => [],
							'timeout' => $this->getInput('timeout', '')
						];

						if ($item['headers']) {
							$headers = [];

							foreach ($item['headers']['name'] as $index => $key) {
								if (array_key_exists($index, $item['headers']['value'])) {
									$headers[$key] = $item['headers']['value'][$index];
								}
							}

							// Ignore single row if it is empty.
							if (count($headers) == 1 && $key === '' && $item['headers']['value'][$index] === '') {
								$headers = [];
							}

							$item['headers'] = $headers;
						}

						if ($this->hasInput('preprocessing')) {
							$preprocessing = $this->getInput('preprocessing');

							foreach ($preprocessing as &$step) {
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

							$item['preprocessing'] = $preprocessing;
						}

						$item = array_intersect_key($item, $visible);

						$discovered_item = [];
						if ($this->hasInput('status')) {
							$discovered_item['status'] = $this->getInput('status');
						}

						foreach ($itemids as $itemid) {
							if (array_key_exists($itemid, $items)) {
								if ($items[$itemid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
									if ($item) {
										if (array_key_exists('applications', $visible)) {
											if ($applicationids) {
												$db_applicationids = array_column($items[$itemid]['applications'],
													'applicationid'
												);

												switch ($massupdate_app_action) {
													case ZBX_ACTION_ADD:
														$upd_applicationids = array_merge($applicationids,
															$db_applicationids
														);
														break;

													case ZBX_ACTION_REPLACE:
														$upd_applicationids = $applicationids;
														break;

													case ZBX_ACTION_REMOVE:
														$upd_applicationids = array_diff($db_applicationids,
															$applicationids
														);
														break;
												}

												$item['applications'] = array_keys(array_flip($upd_applicationids));
											}
											else {
												if ($massupdate_app_action == ZBX_ACTION_ADD
														|| $massupdate_app_action == ZBX_ACTION_REMOVE) {
													unset($item['applications']);
												}
											}
										}

										$items_to_update[] = ['itemid' => $itemid] + $item;
									}
								}
								else {
									if ($discovered_item) {
										$items_to_update[] = ['itemid' => $itemid] + $discovered_item;
									}
								}
							}
						}
					}

					if ($items_to_update) {
						foreach ($items_to_update as &$update_item) {
							$type = array_key_exists('type', $update_item)
								? $update_item['type']
								: $items[$update_item['itemid']]['type'];

							if ($type != ITEM_TYPE_JMX) {
								unset($update_item['jmx_endpoint']);
							}

							if ($type != ITEM_TYPE_HTTPAGENT && $type != ITEM_TYPE_SCRIPT) {
								unset($update_item['timeout']);
							}
						}
						unset($update_item);

						if (!API::Item()->update($items_to_update)) {
							throw new Exception();
						}
					}
				}
				catch (Exception $e) {
					$result = false;
					CMessageHelper::setErrorTitle(_('Cannot update items'));
				}

				$result = DBend($result);
			}

			if ($result) {
				$messages = CMessageHelper::getMessages();
				$output = ['title' => _('Items updated')];
				if (count($messages)) {
					$output['messages'] = array_column($messages, 'message');
				}
			}
			else {
				$output['errors'] = makeMessageBox(false, filter_messages(), CMessageHelper::getTitle())->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$data = [
				'title' => _('Mass update'),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				],
				'prototype' => false,
				'ids' => $this->getInput('ids', []),
				'hostid' => $this->getInput('hostid', 0),
				'context' => $this->getInput('context'),
				'delay_flex' => [['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE]],
				'multiple_interface_types' => false,
				'initial_item_type' => null,
				'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM,
				'preprocessing_types' => CItem::SUPPORTED_PREPROCESSING_TYPES,
				'displayApplications' => true,
				'display_interfaces' => true,
				'displayMasteritems' => true,
				'location_url' => (new CUrl('items.php'))
					->setArgument('context', $this->getInput('context'))
					->getUrl()
			];

			// hosts
			$data['hosts'] = API::Host()->get([
				'output' => ['hostid'],
				'itemids' => $data['ids'],
				'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details']
			]);
			$hostCount = count($data['hosts']);

			if ($hostCount > 1) {
				$data['displayApplications'] = false;
				$data['display_interfaces'] = false;
				$data['displayMasteritems'] = false;
			}
			else {
				// Get template count to display applications multiselect only for single template.
				$templates = API::Template()->get([
					'output' => ['templateid'],
					'itemids' => $data['ids']
				]);
				$templateCount = count($templates);

				if ($templateCount != 0) {
					$data['display_interfaces'] = false;

					if ($templateCount == 1 && $data['hostid'] == 0) {
						// If selected from filter without 'hostid'.
						$templates = reset($templates);
						$data['hostid'] = $templates['templateid'];
					}

					/*
					 * If items belong to single template and some belong to single host, don't display
					 * application multiselect and don't display application multiselect for multiple templates.
					 */
					if ($hostCount == 1 && $templateCount == 1 || $templateCount > 1) {
						$data['displayApplications'] = false;
						$data['displayMasteritems'] = false;
					}
				}

				if ($hostCount == 1 && $data['display_interfaces']) {
					$data['hosts'] = reset($data['hosts']);

					// Sort interfaces to be listed starting with one selected as 'main'.
					CArrayHelper::sort($data['hosts']['interfaces'], [
						['field' => 'main', 'order' => ZBX_SORT_DOWN]
					]);

					// If selected from filter without 'hostid'.
					if ($data['hostid'] == 0) {
						$data['hostid'] = $data['hosts']['hostid'];
					}

					// Set the initial chosen interface to one of the interfaces the items use.
					$items = API::Item()->get([
						'output' => ['itemid', 'type', 'name'],
						'itemids' => $data['ids']
					]);

					$used_interface_types = [];
					foreach ($items as $item) {
						$used_interface_types[$item['type']] = itemTypeInterface($item['type']);
					}

				$initial_type = 0;
				if (count($used_interface_types)) {
					$initial_type = min(array_keys($used_interface_types));
				}

					$data['type'] = $this->hasInput('type') ? $data['type'] : $initial_type;
					$data['initial_item_type'] = $initial_type;
					$data['multiple_interface_types'] = (count(array_unique($used_interface_types)) > 1);
				}
			}

			// Item types.
			$data['itemTypes'] = item_type2str();
			unset($data['itemTypes'][ITEM_TYPE_HTTPTEST], $data['itemTypes'][ITEM_TYPE_SCRIPT]);

			// Valuemap.
			$data['valuemaps'] = API::ValueMap()->get([
				'output' => ['valuemapid', 'name']
			]);
			CArrayHelper::sort($data['valuemaps'], ['name']);

			// Interfaceids for js.
			$data['interfaceids'] = [];
			if ($data['display_interfaces']) {
				foreach ($data['hosts']['interfaces'] as $interface) {
					$data['interfaceids'][$interface['type']][] = $interface['interfaceid'];
				}
			}

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
