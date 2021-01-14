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
require_once dirname(__FILE__).'/../../include/items.inc.php';

class CControllerPopupMassupdateItemPrototype extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'ids' => 'required|array_id',
			'parent_discoveryid' => 'required|id',
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
			'discover' => 'in '.implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]),
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
		$discoveryRule = API::DiscoveryRule()->get([
			'output' => [],
			'itemids' => [$this->getInput('parent_discoveryid')],
			'editable' => true
		]);

		if (!$discoveryRule) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		if ($this->hasInput('update')) {
			$output = [];
			$item_prototypeids = $this->getInput('ids', []);
			$visible = $this->getInput('visible', []);
			$applications = $this->getInput('applications', []);

			$result = true;

			$applicationids = [];

			$application_prototypes = $this->getInput('application_prototypes', []);
			$application_prototypeids = [];

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

			if ($result) {
				try {
					DBstart();

					// Collect submitted applications and create new applications if necessary.
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

					// Collect submitted application prototypes.
					if (array_key_exists('applicationPrototypes', $visible)) {
						$massupdate_app_prot_action = $this->getInput('massupdate_app_prot_action');

						if ($massupdate_app_prot_action == ZBX_ACTION_ADD
								|| $massupdate_app_prot_action == ZBX_ACTION_REPLACE) {
							$new_application_prototypes = [];

							foreach ($application_prototypes as $application_prototype) {
								if (is_array($application_prototype)
										&& array_key_exists('new', $application_prototype)) {
									$new_application_prototypes[] = [
										'name' => $application_prototype['new'],
									];
								}
								else {
									$application_prototypeids[] = $application_prototype;
								}
							}
						}
						else {
							foreach ($application_prototypes as $application_prototype) {
								$application_prototypeids[] = $application_prototype;
							}
						}
					}

					$item_prototypes = API::ItemPrototype()->get([
						'output' => ['itemid', 'type'],
						'selectApplications' => ['applicationid'],
						'selectApplicationPrototypes' => ['application_prototypeid', 'name'],
						'itemids' => $item_prototypeids,
						'preservekeys' => true
					]);

					$item_prototypes_to_update = [];

					if ($item_prototypes) {
						$item_prototype = [
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
							'applicationPrototypes' => [],
							'status' => $this->getInput('status', ITEM_STATUS_ACTIVE),
							'discover' => $this->getInput('discover', ZBX_PROTOTYPE_DISCOVER),
							'master_itemid' => $this->getInput('master_itemid', 0),
							'url' =>  $this->getInput('url', ''),
							'post_type' => $this->getInput('post_type', ZBX_POSTTYPE_RAW),
							'posts' => $this->getInput('posts', ''),
							'headers' => $this->getInput('headers', []),
							'allow_traps' => $this->getInput('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
							'preprocessing' => [],
							'timeout' => $this->getInput('timeout', '')
						];

						if ($item_prototype['headers']) {
							$headers = [];

							foreach ($item_prototype['headers']['name'] as $index => $key) {
								if (array_key_exists($index, $item_prototype['headers']['value'])) {
									$headers[$key] = $item_prototype['headers']['value'][$index];
								}
							}

							// Ignore single row if it is empty.
							if (count($headers) == 1 && $key === ''
									&& $item_prototype['headers']['value'][$index] === '') {
								$headers = [];
							}

							$item_prototype['headers'] = $headers;
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

							$item_prototype['preprocessing'] = $preprocessing;
						}

						// Check "visible" for differences and update only necessary fields.
						$item_prototype = array_intersect_key($item_prototype, $visible);

						foreach ($item_prototypeids as $item_prototypeid) {
							if (array_key_exists($item_prototypeid, $item_prototypes)) {
								if ($item_prototype) {
									// Process applications.
									if (array_key_exists('applications', $visible)) {
										if ($applicationids) {
											// If there are existing applications submitted.
											$db_applicationids = array_column(
												$item_prototypes[$item_prototypeid]['applications'],
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

											/*
											 * $upd_applicationids now contains new and existing application IDs
											 * depending on operation we want to perform.
											 */
											$item_prototype['applications'] = array_keys(
												array_flip($upd_applicationids)
											);
										}
										else {
											/*
											 * No applications were submitted in form. In case we want to replace
											 *  applications, leave $item['applications'] empty, remove it otherwise.
											 */
											if ($massupdate_app_action == ZBX_ACTION_ADD
													|| $massupdate_app_action == ZBX_ACTION_REMOVE) {
												unset($item_prototype['applications']);
											}
										}
									}

									// Process application prototypes.
									if (array_key_exists('applicationPrototypes', $visible)) {
										$ex_application_prototypes
											= $item_prototypes[$item_prototypeid]['applicationPrototypes'];
										$ex_application_prototypeids = array_column($ex_application_prototypes,
											'application_prototypeid'
										);
										$upd_application_prototypeids = [];
										$application_prototypes = [];

										switch ($massupdate_app_prot_action) {
											case ZBX_ACTION_ADD:
												// Append submitted existing application prototypes.
												if ($application_prototypeids) {
													$upd_application_prototypeids = array_unique(
														array_merge($application_prototypeids,
															$ex_application_prototypeids
														)
													);
												}

												// Append new application prototypes.
												if ($new_application_prototypes) {
													foreach ($new_application_prototypes as $new_application_prototype) {
														if (!in_array($new_application_prototype['name'],
																array_column($application_prototypes, 'name'))) {
															$application_prototypes[] = $new_application_prototype;
														}
													}
												}

												// Append already existing application prototypes so that they are
												// not deleted.
												if (($upd_application_prototypeids || $new_application_prototypes)
														&& $ex_application_prototypes) {
													foreach ($ex_application_prototypes as $db_application_prototype) {
														$application_prototypes[] = $db_application_prototype;
													}
												}
												break;

											case ZBX_ACTION_REPLACE:
												if ($application_prototypeids) {
													$upd_application_prototypeids = $application_prototypeids;
												}

												if ($new_application_prototypes) {
													foreach ($new_application_prototypes as $new_application_prototype) {
														if (!in_array($new_application_prototype['name'],
																array_column($application_prototypes, 'name'))) {
															$application_prototypes[] = $new_application_prototype;
														}
													}
												}
												break;

											case ZBX_ACTION_REMOVE:
												if ($application_prototypeids) {
													$upd_application_prototypeids = array_diff(
														$ex_application_prototypeids, $application_prototypeids
													);
												}
												break;
										}

										/*
										 * There might be added an existing application prototype that belongs
										 * to the discovery rule, not just chosen application prototypes
										 * ($ex_application_prototypes).
										 */
										if ($upd_application_prototypeids) {
											// Collect existing application prototype names. Those are required by API.
											$db_application_prototypes = DBfetchArray(DBselect(
												'SELECT ap.application_prototypeid,ap.name'.
												' FROM application_prototype ap'.
												' WHERE '.dbConditionId('ap.application_prototypeid',
													$upd_application_prototypeids
												)
											));

											// Append those application prototypes to update list.
											foreach ($db_application_prototypes as $db_application_prototype) {
												if (!in_array($db_application_prototype['application_prototypeid'],
														array_column($application_prototypes,
															'application_prototypeid'))) {
													$application_prototypes[] = $db_application_prototype;
												}
											}
										}

										if ($application_prototypes) {
											$item_prototype['applicationPrototypes'] = $application_prototypes;
										}
										else {
											if ($massupdate_app_prot_action == ZBX_ACTION_REPLACE) {
												$item_prototype['applicationPrototypes'] = [];
											}
											else {
												unset($item_prototype['applicationPrototypes']);
											}
										}
									}

									$item_prototypes_to_update[] = ['itemid' => $item_prototypeid] + $item_prototype;
								}
							}
						}
					}

					if ($item_prototypes_to_update) {
						foreach ($item_prototypes_to_update as &$update_item_prototype) {
							$type = array_key_exists('type', $update_item_prototype)
								? $update_item_prototype['type']
								: $item_prototypes[$update_item_prototype['itemid']]['type'];

							if ($type != ITEM_TYPE_JMX) {
								unset($update_item_prototype['jmx_endpoint']);
							}

							if ($type != ITEM_TYPE_HTTPAGENT && $type != ITEM_TYPE_SCRIPT) {
								unset($update_item_prototype['timeout']);
							}
						}
						unset($update_item_prototype);

						if (!API::ItemPrototype()->update($item_prototypes_to_update)) {
							throw new Exception();
						}
					}
				}
				catch (Exception $e) {
					$result = false;
					CMessageHelper::setErrorTitle(_('Cannot update item prototypes'));
				}

				$result = DBend($result);
			}

			if ($result) {
				$messages = CMessageHelper::getMessages();
				$output = ['title' => _('Item prototypes updated')];
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
				'prototype' => true,
				'ids' => $this->getInput('ids', []),
				'parent_discoveryid' => $this->getInput('parent_discoveryid', 0),
				'hostid' => $this->getInput('hostid', 0),
				'context' => $this->getInput('context'),
				'delay_flex' => [['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE]],
				'multiple_interface_types' => false,
				'initial_item_type' => null,
				'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM_PROTOTYPE,
				'preprocessing_types' => CItemPrototype::SUPPORTED_PREPROCESSING_TYPES,
				'displayApplications' => true,
				'display_interfaces' => true,
				'displayMasteritems' => true,
				'location_url' => (new CUrl('disc_prototypes.php'))
					->setArgument('parent_discoveryid', $this->getInput('parent_discoveryid', 0))
					->setArgument('context', $this->getInput('context'))
					->getUrl()
			];

			// hosts
			$data['hosts'] = API::Host()->get([
				'output' => ['hostid'],
				'itemids' => $data['ids'],
				'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details']
			]);

			$templates = API::Template()->get([
				'output' => ['templateid'],
				'itemids' => $data['ids']
			]);

			if ($templates) {
				$data['display_interfaces'] = false;

				if ($data['hostid'] == 0) {
					// If selected from filter without 'hostid'.
					$templates = reset($templates);
					$data['hostid'] = $templates['templateid'];
				}
			}

			if ($data['display_interfaces']) {
				$data['hosts'] = reset($data['hosts']);

				// Sort interfaces to be listed starting with one selected as 'main'.
				if (array_key_exists('interface', $data['hosts']) && is_array($data['hosts']['interface'])) {
					CArrayHelper::sort($data['hosts']['interfaces'], [
						['field' => 'main', 'order' => ZBX_SORT_DOWN]
					]);
				}
				else {
					$data['hosts']['interface'] = [];
				}

				// If selected from filter without 'hostid'.
				if ($data['hostid'] == 0) {
					$data['hostid'] = $data['hosts']['hostid'];
				}

				// Set the initial chosen interface to one of the interfaces the items use.
				$item_prototypes = API::ItemPrototype()->get([
					'output' => ['itemid', 'type', 'name'],
					'itemids' => $data['ids']
				]);

				$used_interface_types = [];
				foreach ($item_prototypes as $item_prototype) {
					$used_interface_types[$item_prototype['type']] = itemTypeInterface($item_prototype['type']);
				}

				$initial_type = 0;
				if (count($used_interface_types)) {
					$initial_type = min(array_keys($used_interface_types));
				}

				$data['type'] = $this->hasInput('type') ? $data['type'] : $initial_type;
				$data['initial_item_type'] = $initial_type;
				$data['multiple_interface_types'] = (count(array_unique($used_interface_types)) > 1);
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
