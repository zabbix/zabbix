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

class CControllerPopupMassupdateHost extends CControllerPopupMassupdateAbstract {

	protected function checkInput(): bool {
		$fields = [
			'hostids' => 'required|array',
			'update' => 'in 1',
			'visible' => 'array',
			'tags' => 'array',
			'macros' => 'array',
			'groups' => 'array',
			'host_inventory' => 'array',
			'templates' => 'array',
			'inventories' => 'array',
			'description' => 'string',
			'proxy_hostid' => 'string',
			'ipmi_username' => 'string',
			'ipmi_password' => 'string',
			'tls_issuer' => 'string',
			'tls_subject' => 'string',
			'tls_psk_identity' => 'string',
			'tls_psk' => 'string',
			'valuemaps' => 'array',
			'valuemap_remove' => 'array',
			'valuemap_remove_except' => 'in 1',
			'valuemap_remove_all' => 'in 1',
			'valuemap_rename' => 'array',
			'valuemap_update_existing' => 'in 1',
			'valuemap_add_missing' => 'in 1',
			'macros_add' => 'in 0,1',
			'macros_update' => 'in 0,1',
			'macros_remove' => 'in 0,1',
			'macros_remove_all' => 'in 0,1',
			'mass_clear_tpls' => 'in 0,1',
			'mass_action_tpls' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'mass_update_groups' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'mass_update_tags' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'mass_update_macros' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE, ZBX_ACTION_REMOVE_ALL]),
			'valuemap_massupdate' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE, ZBX_ACTION_RENAME, ZBX_ACTION_REMOVE_ALL]),
			'inventory_mode' => 'in '.implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]),
			'status' => 'in '.implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]),
			'tls_connect' => 'in '.implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]),
			'tls_accept' => 'ge 0|le '.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'ipmi_authtype' => 'in '.implode(',', [IPMI_AUTHTYPE_DEFAULT, IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2, IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM, IPMI_AUTHTYPE_RMCP_PLUS]),
			'ipmi_privilege' => 'in '.implode(',', [IPMI_PRIVILEGE_CALLBACK, IPMI_PRIVILEGE_USER, IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM]),
			'backurl' => 'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('tags')) {
			foreach ($this->getInput('tags') as $tag) {
				if (!is_array($tag) || count($tag) != 2
						|| !array_key_exists('tag', $tag) || !is_string($tag['tag'])
						|| !array_key_exists('value', $tag) || !is_string($tag['value'])) {
					error(_s('Incorrect value for "%1$s" field.', 'tags'));
					$ret = false;
					break;
				}
			}
		}

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

	protected function checkPermissions(): bool {
		$hosts = API::Host()->get([
			'output' => [],
			'hostids' => $this->getInput('hostids'),
			'editable' => true
		]);

		return count($hosts) > 0;
	}

	protected function doAction(): void {
		if ($this->hasInput('update')) {
			$hostids = $this->getInput('hostids');
			$visible = $this->getInput('visible', []);

			$macros = array_filter(cleanInheritedMacros($this->getInput('macros', [])),
				static function (array $macro): bool {
					return (bool) array_filter(
						array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
					);
				}
			);

			$tags = array_filter($this->getInput('tags', []),
				static function (array $tag): bool {
					return $tag['tag'] !== '' || $tag['value'] !== '';
				}
			);

			try {
				DBstart();

				// filter only normal and discovery created hosts
				$options = [
					'output' => ['hostid', 'host', 'inventory_mode', 'flags'],
					'hostids' => $hostids,
					'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]]
				];

				if (array_key_exists('groups', $visible)) {
					$options['selectHostGroups'] = ['groupid'];
				}

				if (array_key_exists('templates', $visible)
						&& !($this->getInput('mass_action_tpls') == ZBX_ACTION_REPLACE
							&& !$this->hasInput('mass_clear_tpls'))) {
					$options['selectParentTemplates'] = ['templateid'];
				}

				if (array_key_exists('tags', $visible)) {
					$options['selectTags'] = ['tag', 'value', 'automatic'];
				}

				if (array_key_exists('macros', $visible)) {
					$mass_update_macros = $this->getInput('mass_update_macros', ZBX_ACTION_ADD);

					if ($mass_update_macros == ZBX_ACTION_ADD || $mass_update_macros == ZBX_ACTION_REPLACE
							|| $mass_update_macros == ZBX_ACTION_REMOVE) {
						$options['selectMacros'] = ['hostmacroid', 'macro'];
					}
				}

				$hosts = API::Host()->get($options);

				if (array_key_exists('groups', $visible)) {
					$new_groupids = [];
					$remove_groupids = [];
					$mass_update_groups = $this->getInput('mass_update_groups', ZBX_ACTION_ADD);

					if ($mass_update_groups == ZBX_ACTION_ADD || $mass_update_groups == ZBX_ACTION_REPLACE) {
						if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
							$ins_groups = [];

							foreach ($this->getInput('groups', []) as $new_group) {
								if (is_array($new_group) && array_key_exists('new', $new_group)) {
									$ins_groups[] = ['name' => $new_group['new']];
								}
								else {
									$new_groupids[] = $new_group;
								}
							}

							if ($ins_groups) {
								if (!$result = API::HostGroup()->create($ins_groups)) {
									throw new Exception();
								}

								$new_groupids = array_merge($new_groupids, $result['groupids']);
							}
						}
						else {
							$new_groupids = $this->getInput('groups', []);
						}
					}
					elseif ($mass_update_groups == ZBX_ACTION_REMOVE) {
						$remove_groupids = $this->getInput('groups', []);
					}
				}

				$properties = ['description', 'proxy_hostid', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
					'ipmi_password'
				];

				$new_values = [];
				foreach ($properties as $property) {
					if (array_key_exists($property, $visible)) {
						$new_values[$property] = $this->getInput($property);
					}
				}

				if (array_key_exists('status', $visible)) {
					$new_values['status'] = $this->getInput('status', HOST_STATUS_NOT_MONITORED);
				}

				$host_inventory = array_intersect_key($this->getInput('host_inventory', []), $visible);

				if (array_key_exists('inventory_mode', $visible)) {
					$new_values['inventory_mode'] = $this->getInput('inventory_mode', HOST_INVENTORY_DISABLED);

					if ($new_values['inventory_mode'] == HOST_INVENTORY_DISABLED) {
						$host_inventory = [];
					}
				}

				if (array_key_exists('encryption', $visible)) {
					$new_values['tls_connect'] = $this->getInput('tls_connect', HOST_ENCRYPTION_NONE);
					$new_values['tls_accept'] = $this->getInput('tls_accept', HOST_ENCRYPTION_NONE);

					if ($new_values['tls_connect'] == HOST_ENCRYPTION_PSK
							|| ($new_values['tls_accept'] & HOST_ENCRYPTION_PSK)) {
						$new_values['tls_psk_identity'] = $this->getInput('tls_psk_identity', '');
						$new_values['tls_psk'] = $this->getInput('tls_psk', '');
					}

					if ($new_values['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE
							|| ($new_values['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
						$new_values['tls_issuer'] = $this->getInput('tls_issuer', '');
						$new_values['tls_subject'] = $this->getInput('tls_subject', '');
					}
				}

				foreach ($hosts as &$host) {
					if (array_key_exists('groups', $visible)) {
						if ($new_groupids && $mass_update_groups == ZBX_ACTION_ADD) {
							$current_groupids = array_column($host['hostgroups'], 'groupid');
							$host['groups'] = zbx_toObject(array_unique(array_merge($current_groupids, $new_groupids)),
								'groupid'
							);
						}
						elseif ($new_groupids && $mass_update_groups == ZBX_ACTION_REPLACE) {
							$host['groups'] = zbx_toObject($new_groupids, 'groupid');
						}
						elseif ($remove_groupids) {
							$current_groupids = array_column($host['hostgroups'], 'groupid');
							$host['groups'] = zbx_toObject(array_diff($current_groupids, $remove_groupids), 'groupid');
						}
						unset($host['hostgroups']);
					}

					if (array_key_exists('templates', $visible)) {
						$host_templateids = array_key_exists('parentTemplates', $host)
							? array_column($host['parentTemplates'], 'templateid')
							: [];

						switch ($this->getInput('mass_action_tpls')) {
							case ZBX_ACTION_ADD:
								$host['templates'] = zbx_toObject(
									array_unique(array_merge($host_templateids, $this->getInput('templates', []))),
									'templateid'
								);
								break;

							case ZBX_ACTION_REPLACE:
								$host['templates'] = zbx_toObject($this->getInput('templates', []), 'templateid');

								if ($this->hasInput('mass_clear_tpls')) {
									$host['templates_clear'] = zbx_toObject(
										array_diff($host_templateids, $this->getInput('templates', [])), 'templateid'
									);
								}
								break;

							case ZBX_ACTION_REMOVE:
								$host['templates'] = zbx_toObject(
									array_diff($host_templateids, $this->getInput('templates', [])), 'templateid'
								);

								if ($this->hasInput('mass_clear_tpls')) {
									$host['templates_clear'] =
										zbx_toObject($this->getInput('templates', []), 'templateid');
								}
								break;
						}
					}

					/*
					 * Inventory mode cannot be changed for discovered hosts. If discovered host has disabled inventory
					 * mode, inventory values also cannot be changed.
					 */
					if (array_key_exists('inventory_mode', $new_values)
							&& $host['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
						$host['inventory'] = $host_inventory;
					}
					elseif ($host['inventory_mode'] != HOST_INVENTORY_DISABLED) {
						$host['inventory'] = $host_inventory;
					}
					else {
						$host['inventory'] = [];
					}

					if (array_key_exists('tags', $visible)) {
						switch ($this->getInput('mass_update_tags', ZBX_ACTION_ADD)) {
							case ZBX_ACTION_ADD:
								$tags_map = [];

								foreach ($host['tags'] as $tag) {
									unset($tag['automatic']);
									$tags_map[$tag['tag']][$tag['value']] = $tag;
								}

								foreach ($tags as $tag) {
									$tags_map[$tag['tag']][$tag['value']] = $tag;
								}

								$host['tags'] = [];

								foreach ($tags_map as $tags_map_2) {
									foreach ($tags_map_2 as $tag) {
										$host['tags'][] = $tag;
									}
								}
								break;

							case ZBX_ACTION_REPLACE:
								$tags_map = [];

								foreach ($tags as $tag) {
									$tags_map[$tag['tag']][$tag['value']] = $tag;
								}

								$host['tags'] = [];

								foreach ($tags_map as $tags_map_2) {
									foreach ($tags_map_2 as $tag) {
										$host['tags'][] = $tag;
									}
								}
								break;

							case ZBX_ACTION_REMOVE:
								$tags_map = [];

								foreach ($host['tags'] as $tag) {
									$tags_map[$tag['tag']][$tag['value']] = $tag;
								}

								foreach ($tags as $tag) {
									if (!array_key_exists($tag['tag'], $tags_map)
											|| !array_key_exists($tag['value'], $tags_map[$tag['tag']])) {
										continue;
									}

									if ($tags_map[$tag['tag']][$tag['value']]['automatic'] == ZBX_TAG_AUTOMATIC) {
										error(_s(
											'Cannot remove the tag with name "%1$s" and value "%2$s", defined in a host prototype, from host "%3$s".',
											$tag['tag'], $tag['value'], $host['host']
										));
										throw new Exception();
									}

									unset($tags_map[$tag['tag']][$tag['value']]);
								}

								$host['tags'] = [];

								foreach ($tags_map as $tags_map_2) {
									foreach ($tags_map_2 as $tag) {
										unset($tag['automatic']);
										$host['tags'][] = $tag;
									}
								}
								break;
						}
					}

					if (array_key_exists('macros', $visible)) {
						switch ($mass_update_macros) {
							case ZBX_ACTION_ADD:
								$update_existing = (bool) getRequest('macros_add', 0);
								$host['macros'] = array_column($host['macros'], null, 'hostmacroid');
								$host_macros_by_macro = array_column($host['macros'], null, 'macro');

								foreach ($macros as $macro) {
									if (!array_key_exists($macro['macro'], $host_macros_by_macro)) {
										$host['macros'][] = $macro;
									}
									elseif ($update_existing) {
										$hostmacroid = $host_macros_by_macro[$macro['macro']]['hostmacroid'];
										$host['macros'][$hostmacroid] = [
											'hostmacroid' => $hostmacroid,
											'automatic' => ZBX_USERMACRO_MANUAL
										] + $macro;
									}
								}
								break;

							case ZBX_ACTION_REPLACE:
								$add_missing = (bool) getRequest('macros_update', 0);
								$host['macros'] = array_column($host['macros'], null, 'hostmacroid');
								$host_macros_by_macro = array_column($host['macros'], null, 'macro');

								foreach ($macros as $macro) {
									if (array_key_exists($macro['macro'], $host_macros_by_macro)) {
										$hostmacroid = $host_macros_by_macro[$macro['macro']]['hostmacroid'];
										$host['macros'][$hostmacroid] = [
											'hostmacroid' => $hostmacroid,
											'automatic' => ZBX_USERMACRO_MANUAL
										] + $macro;
									}
									elseif ($add_missing) {
										$host['macros'][] = $macro;
									}
								}
								break;

							case ZBX_ACTION_REMOVE:
								if ($macros) {
									$except_selected = $this->getInput('macros_remove', 0);
									$host_macros_by_macro = array_column($host['macros'], null, 'macro');
									$macros_by_macro = array_column($macros, null, 'macro');

									$host['macros'] = $except_selected
										? array_intersect_key($host_macros_by_macro, $macros_by_macro)
										: array_diff_key($host_macros_by_macro, $macros_by_macro);
								}
								break;

							case ZBX_ACTION_REMOVE_ALL:
								if (!$this->getInput('macros_remove_all', 0)) {
									throw new Exception();
								}

								$host['macros'] = [];
								break;
						}

						$host['macros'] = array_values($host['macros']);
					}

					unset($host['parentTemplates']);

					$host = $new_values + $host;

					/*
					 * API prevents changing host inventory_mode for discovered hosts. However, inventory values can
					 * still be updated if inventory mode allows it.
					 */
					if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
						unset($host['inventory_mode']);
					}
					unset($host['host'], $host['flags']);
				}
				unset($host);

				$result = $hosts ? (bool) API::Host()->update($hosts) : true;

				if ($result === false) {
					throw new Exception();
				}

				// Value mapping.
				if (array_key_exists('valuemaps', $visible)) {
					$this->updateValueMaps($hostids);
				}

				DBend(true);
			}
			catch (Exception $e) {
				DBend(false);

				$result = false;
			}

			if ($this->hasInput('backurl')) {
				$upd_status = ($this->getInput('status', HOST_STATUS_NOT_MONITORED) == HOST_STATUS_MONITORED);
				$backurl = new CUrl($this->getInput('backurl'));
				$cnt = count($hostids);

				if ($result) {
					$backurl->setArgument('uncheck', 1);

					CMessageHelper::setSuccessTitle($upd_status
						? _n('Host enabled', 'Hosts enabled', $cnt)
						: _n('Host disabled', 'Hosts disabled', $cnt)
					);
				}
				else {
					CMessageHelper::setErrorTitle($upd_status
						? _n('Cannot enable host', 'Cannot enable hosts', $cnt)
						: _n('Cannot disable host', 'Cannot disable hosts', $cnt)
					);
				}

				$this->setResponse(new CControllerResponseRedirect($backurl->getUrl()));
			}
			else {
				if ($result) {
					ob_start();
					uncheckTableRows('hosts');

					$output = ['title' => _('Hosts updated'), 'script_inline' => ob_get_clean()];

					if ($messages = CMessageHelper::getMessages()) {
						$output['messages'] = array_column($messages, 'message');
					}
				}
				else {
					$output = [
						'error' => [
							'title' => _('Cannot update hosts'),
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					];
				}

				$this->setResponse(
					(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
				);
			}
		}
		else {
			$data = [
				'title' => _('Mass update'),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				],
				'hostids' => $this->getInput('hostids'),
				'inventories' => zbx_toHash(getHostInventories(), 'db_field'),
				'location_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'host.list')
					->setArgument('page', CPagerHelper::loadPage('host.list'))
					->getUrl()
			];

			$data['proxies'] = API::Proxy()->get([
				'output' => ['hostid', 'host'],
				'filter' => [
					'status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]
				],
				'sortfield' => 'host'
			]);

			$data['discovered_host'] = !(bool) API::Host()->get([
				'output' => [],
				'hostids' => $data['hostids'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
				'limit' => 1
			]);

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
