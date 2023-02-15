<?php
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


abstract class CHostBase extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';

	/**
	 * Check for valid templates.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException
	 */
	protected static function checkTemplates(array $hosts, array $db_hosts = null): void {
		$edit_templates = [];

		foreach ($hosts as $i1 => $host) {
			if (array_key_exists('templates', $host) && array_key_exists('templates_clear', $host)) {
				$path_clear = '/'.($i1 + 1).'/templates_clear';
				$path = '/'.($i1 + 1).'/templates';

				foreach ($host['templates_clear'] as $i2_clear => $template_clear) {
					foreach ($host['templates'] as $i2 => $template) {
						if (bccomp($template['templateid'], $template_clear['templateid']) == 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path_clear.'/'.($i2_clear + 1).'/templateid',
								_s('cannot be specified the value of parameter "%1$s"',
									$path.'/'.($i2 + 1).'/templateid'
								)
							));
						}
					}
				}
			}

			if (array_key_exists('templates', $host)) {
				$templates = array_column($host['templates'], null, 'templateid');

				if ($db_hosts === null) {
					$edit_templates += $templates;
				}
				else {
					$db_templates = array_column($db_hosts[$host['hostid']]['templates'], null, 'templateid');

					$ins_templates = array_diff_key($templates, $db_templates);
					$del_templates = array_diff_key($db_templates, $templates);

					$edit_templates += $ins_templates + $del_templates;
				}
			}

			if (array_key_exists('templates_clear', $host)) {
				$edit_templates += array_column($host['templates_clear'], null, 'templateid');
			}
		}

		if (!$edit_templates) {
			return;
		}

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => array_keys($edit_templates)
		]);

		if ($count != count($edit_templates)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($hosts as $i1 => $host) {
			if (!array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = array_column($db_hosts[$host['hostid']]['templates'], null, 'templateid');
			$path = '/'.($i1 + 1).'/templates_clear';

			foreach ($host['templates_clear'] as $i2 => $template) {
				if (!array_key_exists($template['templateid'], $db_templates)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/templateid', _('cannot be unlinked')
					));
				}
			}
		}
	}

	/**
	 * Check templates links.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected static function checkTemplatesLinks(array $hosts, array $db_hosts = null): void {
		$ins_templates = [];

		foreach ($hosts as $host) {
			if (array_key_exists('templates', $host)) {
				$db_templates = $db_hosts !== null
					? array_column($db_hosts[$host['hostid']]['templates'], null, 'templateid')
					: [];
				$templateids = array_column($host['templates'], 'templateid');

				if ($db_hosts !== null
						&& array_key_exists('nopermissions_templates', $db_hosts[$host['hostid']])) {
					foreach ($db_hosts[$host['hostid']]['nopermissions_templates'] as $db_template) {
						$templateids[] = $db_template['templateid'];
					}
				}

				foreach ($host['templates'] as $template) {
					if (!array_key_exists($template['templateid'], $db_templates)) {
						$ins_templates[$template['templateid']][$host['hostid']] = $templateids;
					}
				}
			}
		}

		if ($ins_templates) {
			self::checkTriggerDependenciesOfInsTemplates($ins_templates);
			self::checkTriggerExpressionsOfInsTemplates($ins_templates);
		}
	}

	/**
	 * Check whether all templates of triggers, on which the linked template triggers depend, are linked to target
	 * hosts.
	 *
	 * @param array  $ins_templates
	 * @param string $ins_templates[<templateid>][<hostid>]  Array of IDs of templates to replace on target object.
	 *
	 * @throws APIException
	 */
	protected static function checkTriggerDependenciesOfInsTemplates(array $ins_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS ins_templateid,td.triggerid_down,ii.hostid'.
			' FROM items i,functions f,trigger_depends td,functions ff,items ii,hosts h'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=td.triggerid_down'.
				' AND td.triggerid_up=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND ii.hostid=h.hostid'.
				' AND '.dbConditionId('i.hostid', array_keys($ins_templates)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
				if (!in_array($row['hostid'], $templateids)) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'flags'],
						'hostids' => [$row['ins_templateid'], $row['hostid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					$error = $objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
						? _('Cannot link template "%1$s" without template "%2$s" to host prototype "%3$s" due to dependency of trigger "%4$s".')
						: _('Cannot link template "%1$s" without template "%2$s" to host "%3$s" due to dependency of trigger "%4$s".');

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['ins_templateid']]['host'],
						$objects[$row['hostid']]['host'], $objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}
	}

	/**
	 * Check whether all templates of triggers in templates being linked, are linked to all target hosts.
	 *
	 * @param array  $ins_templates
	 * @param string $ins_templates[<templateid>][<hostid>]  Array of IDs of templates to replace on target object.
	 *
	 * @throws APIException
	 */
	protected static function checkTriggerExpressionsOfInsTemplates(array $ins_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS ins_templateid,f.triggerid,ii.hostid'.
			' FROM items i,functions f,functions ff,items ii'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND '.dbConditionId('i.hostid', array_keys($ins_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
				if (!in_array($row['hostid'], $templateids)) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'flags'],
						'hostids' => [$row['ins_templateid'], $row['hostid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					$error = $objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
						? _('Cannot link template "%1$s" without template "%2$s" to host prototype "%3$s" due to expression of trigger "%4$s".')
						: _('Cannot link template "%1$s" without template "%2$s" to host "%3$s" due to expression of trigger "%4$s".');

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['ins_templateid']]['host'],
						$objects[$row['hostid']]['host'], $objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}
	}

	/**
	 * Update table "hosts_templates".
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected static function updateTemplates(array &$hosts, array $db_hosts = null): void {
		$ins_hosts_templates = [];
		$del_hosttemplateids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = ($db_hosts !== null)
				? array_column($db_hosts[$host['hostid']]['templates'], null, 'templateid')
				: [];

			if (array_key_exists('templates', $host)) {
				foreach ($host['templates'] as &$template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						$template['hosttemplateid'] = $db_templates[$template['templateid']]['hosttemplateid'];
						unset($db_templates[$template['templateid']]);
					}
					else {
						$ins_hosts_templates[] = [
							'hostid' => $host['hostid'],
							'templateid' => $template['templateid']
						];
					}
				}
				unset($template);

				$templates_clear_indexes = [];

				if (array_key_exists('templates_clear', $host)) {
					foreach ($host['templates_clear'] as $index => $template) {
						$templates_clear_indexes[$template['templateid']] = $index;
					}
				}

				foreach ($db_templates as $del_template) {
					$del_hosttemplateids[] = $del_template['hosttemplateid'];

					if (array_key_exists($del_template['templateid'], $templates_clear_indexes)) {
						$index = $templates_clear_indexes[$del_template['templateid']];
						$host['templates_clear'][$index]['hosttemplateid'] = $del_template['hosttemplateid'];
					}
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as &$template) {
					$template['hosttemplateid'] = $db_templates[$template['templateid']]['hosttemplateid'];
					$del_hosttemplateids[] = $db_templates[$template['templateid']]['hosttemplateid'];
				}
				unset($template);
			}
		}
		unset($host);

		if ($del_hosttemplateids) {
			DB::delete('hosts_templates', ['hosttemplateid' => $del_hosttemplateids]);
		}

		if ($ins_hosts_templates) {
			$hosttemplateids = DB::insertBatch('hosts_templates', $ins_hosts_templates);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('templates', $host)) {
				continue;
			}

			foreach ($host['templates'] as &$template) {
				if (!array_key_exists('hosttemplateid', $template)) {
					$template['hosttemplateid'] = array_shift($hosttemplateids);
				}
			}
			unset($template);
		}
		unset($host);
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected function updateTags(array &$hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_tags = [];
		$del_hosttagids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('tags', $host)) {
				continue;
			}

			$db_tags = ($db_hosts !== null) ? $db_hosts[$host[$id_field_name]]['tags'] : [];

			$hosttagid_by_tag_value = [];
			foreach ($db_tags as $db_tag) {
				$hosttagid_by_tag_value[$db_tag['tag']][$db_tag['value']] = $db_tag['hosttagid'];
			}

			foreach ($host['tags'] as &$tag) {
				if (array_key_exists($tag['tag'], $hosttagid_by_tag_value)
						&& array_key_exists($tag['value'], $hosttagid_by_tag_value[$tag['tag']])) {
					$tag['hosttagid'] = $hosttagid_by_tag_value[$tag['tag']][$tag['value']];
					unset($db_tags[$tag['hosttagid']]);
				}
				else {
					$ins_tags[] = ['hostid' => $host[$id_field_name]] + $tag;
				}
			}
			unset($tag);

			$del_hosttagids = array_merge($del_hosttagids, array_keys(array_filter($db_tags,
				static function (array $db_tag): bool {
					return $db_tag['automatic'] == ZBX_TAG_MANUAL;
				}
			)));
		}
		unset($host);

		if ($del_hosttagids) {
			DB::delete('host_tag', ['hosttagid' => $del_hosttagids]);
		}

		if ($ins_tags) {
			$hosttagids = DB::insert('host_tag', $ins_tags);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('tags', $host)) {
				continue;
			}

			foreach ($host['tags'] as &$tag) {
				if (!array_key_exists('hosttagid', $tag)) {
					$tag['hosttagid'] = array_shift($hosttagids);
				}
			}
			unset($tag);
		}
		unset($host);
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected function updateMacros(array &$hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hostmacros = [];
		$upd_hostmacros = [];
		$del_hostmacroids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			$db_macros = ($db_hosts !== null) ? $db_hosts[$host[$id_field_name]]['macros'] : [];

			foreach ($host['macros'] as &$macro) {
				if (array_key_exists('hostmacroid', $macro)) {
					$upd_hostmacro = DB::getUpdatedValues('hostmacro', $macro, $db_macros[$macro['hostmacroid']]);

					if ($upd_hostmacro) {
						$upd_hostmacros[] = [
							'values' => $upd_hostmacro,
							'where' => ['hostmacroid' => $macro['hostmacroid']]
						];
					}

					unset($db_macros[$macro['hostmacroid']]);
				}
				else {
					$ins_hostmacros[] = ['hostid' => $host[$id_field_name]] + $macro;
				}
			}
			unset($macro);

			$del_hostmacroids = array_merge($del_hostmacroids, array_keys($db_macros));
		}
		unset($host);

		if ($del_hostmacroids) {
			DB::delete('hostmacro', ['hostmacroid' => $del_hostmacroids]);
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		if ($ins_hostmacros) {
			$hostmacroids = DB::insert('hostmacro', $ins_hostmacros);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			foreach ($host['macros'] as &$macro) {
				if (!array_key_exists('hostmacroid', $macro)) {
					$macro['hostmacroid'] = array_shift($hostmacroids);
				}
			}
			unset($macro);
		}
		unset($host);
	}

	/**
	 * Checks user macros for host.update, template.update and hostprototype.update methods.
	 *
	 * @param array  $hosts
	 * @param array  $hosts[]['templateid|hostid']
	 * @param array  $hosts[]['macros']             (optional)
	 * @param array  $db_hosts
	 * @param array  $db_hosts[<hostid>]['macros']
	 *
	 * @return array Array of passed hosts/templates with padded macros data, when it's necessary.
	 *
	 * @throws APIException if input of host macros data is invalid.
	 */
	protected function validateHostMacros(array $hosts, array $db_hosts): array {
		$hostmacro_defaults = [
			'type' => DB::getDefault('hostmacro', 'type')
		];

		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		foreach ($hosts as $i1 => &$host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			$db_host = $db_hosts[$host[$id_field_name]];
			$path = '/'.($i1 + 1).'/macros';

			$db_macros = array_column($db_host['macros'], 'hostmacroid', 'macro');
			$macros = [];

			foreach ($host['macros'] as $i2 => &$hostmacro) {
				if (!array_key_exists('hostmacroid', $hostmacro)) {
					foreach (['macro', 'value'] as $field_name) {
						if (!array_key_exists($field_name, $hostmacro)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path.'/'.($i2 + 1), _s('the parameter "%1$s" is missing', $field_name)
							));
						}
					}

					$hostmacro += $hostmacro_defaults;
				}
				else {
					if (!array_key_exists($hostmacro['hostmacroid'], $db_host['macros'])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}

					$db_hostmacro = $db_host['macros'][$hostmacro['hostmacroid']];

					// Check if this is not an attempt to modify automatic host macro.
					if ($this instanceof CHost) {
						$macro_fields = array_flip(['macro', 'value', 'type', 'description']);
						$hostmacro += array_intersect_key($db_hostmacro, array_flip(['automatic']));

						if ($hostmacro['automatic'] == ZBX_USERMACRO_AUTOMATIC
								&& array_diff_assoc(array_intersect_key($hostmacro, $macro_fields), $db_hostmacro)) {
							self::exception(ZBX_API_ERROR_PERMISSIONS,
								_s('Not allowed to modify automatic user macro "%1$s".', $db_hostmacro['macro'])
							);
						}
					}

					$hostmacro += array_intersect_key($db_hostmacro, array_flip(['macro', 'type']));

					if ($hostmacro['type'] != $db_hostmacro['type']) {
						if ($db_hostmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
							$hostmacro += ['value' => ''];
						}

						if ($hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
							$hostmacro += ['value' => $db_hostmacro['value']];
						}
					}

					$macros[$hostmacro['hostmacroid']] = $hostmacro['macro'];
				}

				if (array_key_exists('value', $hostmacro) && $hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
					if (!CApiInputValidator::validate([
								'type' => API_VAULT_SECRET,
								'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)
							], $hostmacro['value'], $path.'/'.($i2 + 1).'/value', $error)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, $error);
					}
				}
			}
			unset($hostmacro);

			// Checking for cross renaming of existing macros.
			foreach ($macros as $hostmacroid => $macro) {
				if (array_key_exists($macro, $db_macros) && bccomp($hostmacroid, $db_macros[$macro]) != 0
						&& array_key_exists($db_macros[$macro], $macros)) {
					$hosts = DB::select('hosts', [
						'output' => ['name'],
						'hostids' => $host[$id_field_name]
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Macro "%1$s" already exists on "%2$s".', $macro, $hosts[0]['name'])
					);
				}
			}

			$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['macro']], 'fields' => [
				'macro' =>	['type' => API_USER_MACRO]
			]];

			if (!CApiInputValidator::validateUniqueness($api_input_rules, $host['macros'], $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Add affected tags and macros.
	 *
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		$this->addAffectedTags($hosts, $db_hosts);
		$this->addAffectedMacros($hosts, $db_hosts);
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected static function addAffectedTemplates(array $hosts, array &$db_hosts): void {
		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('templates', $host) || array_key_exists('templates_clear', $host)) {
				$hostids[] = $host['hostid'];
				$db_hosts[$host['hostid']]['templates'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$permitted_templates = [];

		if (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
			$permitted_templates = API::Template()->get([
				'output' => [],
				'hostids' => $hostids,
				'preservekeys' => true
			]);
		}

		$options = [
			'output' => ['hosttemplateid', 'hostid', 'templateid'],
			'filter' => ['hostid' => $hostids]
		];
		$db_templates = DBselect(DB::makeSql('hosts_templates', $options));

		while ($db_template = DBfetch($db_templates)) {
			$index = (self::$userData['type'] == USER_TYPE_SUPER_ADMIN
					|| array_key_exists($db_template['templateid'], $permitted_templates))
				? 'templates'
				: 'nopermissions_templates';

			$db_hosts[$db_template['hostid']][$index][$db_template['hosttemplateid']] =
				array_diff_key($db_template, array_flip(['hostid']));
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedTags(array $hosts, array &$db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('tags', $host)) {
				$hostids[] = $host[$id_field_name];
				$db_hosts[$host[$id_field_name]]['tags'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$options = [
			'output' => ['hosttagid', 'hostid', 'tag', 'value', 'automatic'],
			'filter' => ['hostid' => $hostids]
		];
		$db_tags = DBselect(DB::makeSql('host_tag', $options));

		while ($db_tag = DBfetch($db_tags)) {
			$db_hosts[$db_tag['hostid']]['tags'][$db_tag['hosttagid']] =
				array_diff_key($db_tag, array_flip(['hostid']));
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private function addAffectedMacros(array $hosts, array &$db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('macros', $host)) {
				$hostids[] = $host[$id_field_name];
				$db_hosts[$host[$id_field_name]]['macros'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$options = [
			'output' => ['hostmacroid', 'hostid', 'macro', 'value', 'description', 'type'],
			'filter' => ['hostid' => $hostids]
		];
		$db_macros = DBselect(DB::makeSql('hostmacro', $options));

		while ($db_macro = DBfetch($db_macros)) {
			$db_hosts[$db_macro['hostid']]['macros'][$db_macro['hostmacroid']] =
				array_diff_key($db_macro, array_flip(['hostid']));
		}
	}

	/**
	 * Retrieves and adds additional requested data to the result set.
	 *
	 * @param array  $options
	 * @param array  $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		// adding macros
		if ($options['selectMacros'] !== null && $options['selectMacros'] !== API_OUTPUT_COUNT) {
			$macros = API::UserMacro()->get([
				'output' => $this->outputExtend($options['selectMacros'], ['hostid', 'hostmacroid']),
				'hostids' => $hostids,
				'preservekeys' => true,
				'nopermissions' => true
			]);

			$relationMap = $this->createRelationMap($macros, 'hostid', 'hostmacroid');
			$macros = $this->unsetExtraFields($macros, ['hostid', 'hostmacroid'], $options['selectMacros']);
			$result = $relationMap->mapMany($result, $macros, 'macros',
				array_key_exists('limitSelects', $options) ? $options['limitSelects'] : null
			);
		}

		return $result;
	}
}
