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


/**
 * Class containing methods for operations with hosts.
 */
abstract class CHostGeneral extends CHostBase {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massadd' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massupdate' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massremove' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected function checkGroups(array $hosts, array $db_hosts = null, string $path = null,
			array $group_indexes = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_group_indexes = [];

		foreach ($hosts as $i1 => $host) {
			if (!array_key_exists('groups', $host)) {
				continue;
			}

			$db_groups = $db_hosts !== null
				? array_column($db_hosts[$host[$id_field_name]]['groups'], null, 'groupid')
				: [];

			foreach ($host['groups'] as $i2 => $group) {
				if (array_key_exists($group['groupid'], $db_groups)) {
					unset($db_groups[$group['groupid']]);
				}
				else {
					$ins_group_indexes[$group['groupid']][$i1] = $i2;
				}
			}
		}

		if (!$ins_group_indexes) {
			return;
		}

		$entity = $this instanceof CTemplate ? API::TemplateGroup() : API::HostGroup();
		$db_groups = $entity->get([
			'output' => [],
			'groupids' => array_keys($ins_group_indexes),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($ins_group_indexes as $groupid => $indexes) {
			if (!array_key_exists($groupid, $db_groups)) {
				if ($path === null) {
					$i1 = key($indexes);
					$i2 = $indexes[$i1];

					$path = '/'.($i1 + 1).'/groups/'.($i2 + 1);
				}
				else {
					$i = $group_indexes[$groupid];

					$path .= '/'.($i + 1);
				}

				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.', $path,
					_('object does not exist, or you have no permissions to it')
				));
			}
		}
	}

	public function checkHostsWithoutGroups(array $hosts, array $db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		foreach ($hosts as $host) {
			if (array_key_exists('groups', $host) && !$host['groups']
					&& (!array_key_exists('nopermissions_groups', $db_hosts[$host[$id_field_name]])
						|| !$db_hosts[$host[$id_field_name]]['nopermissions_groups'])) {
				$error = $this instanceof CTemplate
					? _s('Template "%1$s" cannot be without template group.', $db_hosts[$host[$id_field_name]]['host'])
					: _s('Host "%1$s" cannot be without host group.', $db_hosts[$host[$id_field_name]]['host']);

				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
	}

	/**
	 * Check for unique host names.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException if host names are not unique.
	 */
	protected function checkDuplicates(array $hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$h_names = [];
		$v_names = [];

		foreach ($hosts as $host) {
			if (array_key_exists('host', $host)) {
				if ($db_hosts === null || $host['host'] !== $db_hosts[$host[$id_field_name]]['host']) {
					$h_names[] = $host['host'];
				}
			}

			if (array_key_exists('name', $host)) {
				if ($db_hosts === null || $host['name'] !== $db_hosts[$host[$id_field_name]]['name']) {
					$v_names[] = $host['name'];
				}
			}
		}

		if ($h_names) {
			$duplicates = DB::select('hosts', [
				'output' => ['host', 'status'],
				'filter' => [
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED],
					'status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE],
					'host' => $h_names
				],
				'limit' => 1
			]);

			if ($duplicates) {
				$error = ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE)
					? _s('Template with host name "%1$s" already exists.', $duplicates[0]['host'])
					: _s('Host with host name "%1$s" already exists.', $duplicates[0]['host']);

				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}

		if ($v_names) {
			$duplicates = DB::select('hosts', [
				'output' => ['name', 'status'],
				'filter' => [
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED],
					'status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE],
					'name' => $v_names
				],
				'limit' => 1
			]);

			if ($duplicates) {
				$error = ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE)
					? _s('Template with visible name "%1$s" already exists.', $duplicates[0]['name'])
					: _s('Host with visible name "%1$s" already exists.', $duplicates[0]['name']);

				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
	}

	/**
	 * Update table "hosts_groups" and populate hosts.groups by "hostgroupid" property.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected function updateGroups(array &$hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hosts_groups = [];
		$del_hostgroupids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('groups', $host)) {
				continue;
			}

			$db_groups = ($db_hosts !== null)
				? array_column($db_hosts[$host[$id_field_name]]['groups'], null, 'groupid')
				: [];

			foreach ($host['groups'] as &$group) {
				if (array_key_exists($group['groupid'], $db_groups)) {
					$group['hostgroupid'] = $db_groups[$group['groupid']]['hostgroupid'];
					unset($db_groups[$group['groupid']]);
				}
				else {
					$ins_hosts_groups[] = [
						'hostid' => $host[$id_field_name],
						'groupid' => $group['groupid']
					];
				}
			}
			unset($group);

			$db_groups = array_filter($db_groups, static function (array $db_group): bool {
				return $db_group['permission'] == PERM_READ_WRITE;
			});

			$del_hostgroupids = array_merge($del_hostgroupids, array_column($db_groups, 'hostgroupid'));
		}
		unset($host);

		if ($del_hostgroupids) {
			DB::delete('hosts_groups', ['hostgroupid' => $del_hostgroupids]);
		}

		if ($ins_hosts_groups) {
			$hostgroupids = DB::insertBatch('hosts_groups', $ins_hosts_groups);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('groups', $host)) {
				continue;
			}

			foreach ($host['groups'] as &$group) {
				if (!array_key_exists('hostgroupid', $group)) {
					$group['hostgroupid'] = array_shift($hostgroupids);
				}
			}
			unset($group);
		}
		unset($host);
	}

	protected function updateHgSets(array $hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$hgsets = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('groups', $host)) {
				continue;
			}

			if ($db_hosts === null) {
				$groupids = array_column($host['groups'], 'groupid');
			}
			else {
				$groupids = [];
				$_groupids = array_column($host['groups'], 'groupid');
				$_db_groupids = array_column($db_hosts[$host[$id_field_name]]['groups'], 'groupid');

				if (array_diff($_groupids, $_db_groupids) || array_diff($_db_groupids, $_groupids)) {
					$groupids = $_groupids;

					if (array_key_exists('nopermissions_groups', $db_hosts[$host[$id_field_name]])) {
						$groupids = array_merge($groupids,
							array_column($db_hosts[$host[$id_field_name]]['nopermissions_groups'], 'groupid')
						);
					}
				}
			}

			if ($groupids) {
				$hgset_hash = self::getHgSetHash($groupids);

				$hgsets[$hgset_hash]['hash'] = $hgset_hash;
				$hgsets[$hgset_hash]['groupids'] = $groupids;
				$hgsets[$hgset_hash]['hostids'][] = $host[$id_field_name];
			}
		}

		if ($hgsets) {
			if ($db_hosts === null) {
				self::createHostHgSets($hgsets);
			}
			else {
				self::updateHostHgSets($hgsets);
			}
		}
	}

	private static function getHgSetHash(array $groupids): string {
		usort($groupids, 'bccomp');

		return hash('sha256', implode('|', $groupids));
	}

	private static function createHostHgSets(array $hgsets): void {
		$ins_host_hgsets = [];

		$options = [
			'output' => ['hgsetid', 'hash'],
			'filter' => ['hash' => array_keys($hgsets)]
		];
		$result = DBselect(DB::makeSql('hgset', $options));

		while ($row = DBfetch($result)) {
			foreach ($hgsets[$row['hash']]['hostids'] as $hostid) {
				$ins_host_hgsets[] = [
					'hostid' => $hostid,
					'hgsetid' => $row['hgsetid']
				];
			}

			unset($hgsets[$row['hash']]);
		}

		if ($hgsets) {
			self::createHgSets($hgsets);

			foreach ($hgsets as $hgset) {
				foreach ($hgset['hostids'] as $hostid) {
					$ins_host_hgsets[] = [
						'hostid' => $hostid,
						'hgsetid' => $hgset['hgsetid']
					];
				}
			}
		}

		DB::insert('host_hgset', $ins_host_hgsets, false);
	}

	private static function updateHostHgSets(array $hgsets): void {
		$upd_host_hgsets = [];

		$db_hgsetids = array_flip(self::getDbHgSetIds($hgsets));

		$empty_hgset_hash = hash('sha256', '');

		if (array_key_exists($empty_hgset_hash, $hgsets)) {
			DB::delete('host_hgset', ['hostid' => $hgsets[$empty_hgset_hash]['hostids']]);
			unset($hgsets[$empty_hgset_hash]);
		}

		if ($hgsets) {
			$options = [
				'output' => ['hgsetid', 'hash'],
				'filter' => ['hash' => array_keys($hgsets)]
			];
			$result = DBselect(DB::makeSql('hgset', $options));

			while ($row = DBfetch($result)) {
				$upd_host_hgsets[] = [
					'values' => ['hgsetid' => $row['hgsetid']],
					'where' => ['hostid' => $hgsets[$row['hash']]['hostids']]
				];

				if (array_key_exists($row['hgsetid'], $db_hgsetids)) {
					unset($db_hgsetids[$row['hgsetid']]);
				}

				unset($hgsets[$row['hash']]);
			}

			if ($hgsets) {
				self::createHgSets($hgsets);

				foreach ($hgsets as $hgset) {
					$upd_host_hgsets[] = [
						'values' => ['hgsetid' => $hgset['hgsetid']],
						'where' => ['hostid' => $hgset['hostids']]
					];
				}
			}

			DB::update('host_hgset', $upd_host_hgsets);
		}

		self::deleteUnusedHgSets(array_keys($db_hgsetids));
	}

	private static function getDbHgSetIds(array $hgsets): array {
		$hostids = [];

		foreach ($hgsets as $hgset) {
			$hostids = array_merge($hostids, $hgset['hostids']);
		}

		return DBfetchColumn(DBselect(
			'SELECT DISTINCT hh.hgsetid'.
			' FROM host_hgset hh'.
			' WHERE '.dbConditionId('hh.hostid', $hostids)
		), 'hgsetid');
	}

	private static function createHgSets(array &$hgsets): void {
		$hgsetids = DB::insert('hgset', $hgsets);

		foreach ($hgsets as &$hgset) {
			$hgset['hgsetid'] = array_shift($hgsetids);
		}
		unset($hgset);

		self::createHgSetGroups($hgsets);

		self::addHgSetPermissions($hgsets);
		self::createPermissions($hgsets);
	}

	private static function createHgSetGroups(array $hgsets): void {
		$ins_hgset_groups = [];

		foreach ($hgsets as $hgset) {
			foreach ($hgset['groupids'] as $groupid) {
				$ins_hgset_groups[] = ['hgsetid' => $hgset['hgsetid'], 'groupid' => $groupid];
			}
		}

		DB::insert('hgset_group', $ins_hgset_groups, false);
	}

	private static function addHgSetPermissions(array &$hgsets): void {
		$hgset_indexes = [];

		foreach ($hgsets as $i => &$hgset) {
			$hgset['permissions'] = [];

			foreach ($hgset['groupids'] as $groupid) {
				$hgset_indexes[$groupid][] = $i;
			}
		}
		unset($hgset);

		$result = DBselect(
			'SELECT r.id,ugg.ugsetid,'.
				'CASE WHEN MIN(r.permission)='.PERM_DENY.' THEN '.PERM_DENY.' ELSE MAX(r.permission) END AS permission'.
			' FROM rights r,ugset_group ugg'.
			' WHERE r.groupid=ugg.usrgrpid'.
				' AND '.dbConditionId('r.id', array_keys($hgset_indexes)).
			' GROUP BY r.id,ugg.ugsetid'
		);

		while ($row = DBfetch($result)) {
			foreach ($hgset_indexes[$row['id']] as $i) {
				if (!array_key_exists($row['ugsetid'], $hgsets[$i]['permissions'])
						|| ($hgsets[$i]['permissions'][$row['ugsetid']] != PERM_DENY
							&& ($row['permission'] == PERM_DENY
								|| $row['permission'] > $hgsets[$i]['permissions'][$row['ugsetid']]))) {
					$hgsets[$i]['permissions'][$row['ugsetid']] = $row['permission'];
				}
			}
		}
	}

	private static function createPermissions(array $hgsets): void {
		$ins_permissions = [];

		foreach ($hgsets as $hgset) {
			foreach ($hgset['permissions'] as $ugsetid => $permission) {
				if ($permission != PERM_DENY) {
					$ins_permissions[] = [
						'hgsetid' => $hgset['hgsetid'],
						'ugsetid' => $ugsetid,
						'permission' => $permission
					];
				}
			}
		}

		if ($ins_permissions) {
			DB::insert('permission', $ins_permissions, false);
		}
	}

	private static function deleteUnusedHgSets(array $db_hgsetids): void {
		$del_hgsetids = DBfetchColumn(DBselect(
			'SELECT h.hgsetid'.
			' FROM hgset h'.
			' LEFT JOIN host_hgset hh ON h.hgsetid=hh.hgsetid'.
			' WHERE '.dbConditionId('h.hgsetid', $db_hgsetids).
				' AND hh.hostid IS NULL'
		), 'hgsetid');

		if ($del_hgsetids) {
			DB::delete('permission', ['hgsetid' => $del_hgsetids]);
			DB::delete('hgset_group', ['hgsetid' => $del_hgsetids]);
			DB::delete('hgset', ['hgsetid' => $del_hgsetids]);
		}
	}

	/**
	 * Update table "hosts_templates" and change objects of linked or unliked templates on target hosts or templates.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param array|null $upd_hostids
	 */
	protected function updateTemplates(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		parent::updateTemplates($hosts, $db_hosts);

		$ins_links = [];
		$del_links = [];
		$del_links_clear = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
				continue;
			}

			if (array_key_exists('templates', $host)) {
				$db_templates = ($db_hosts !== null)
					? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
					: [];

				foreach ($host['templates'] as $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						unset($db_templates[$template['templateid']]);
					}
					else {
						$ins_links[$template['templateid']][] = $host[$id_field_name];
					}
				}

				$templates_clear = array_key_exists('templates_clear', $host)
					? array_column($host['templates_clear'], null, 'templateid')
					: [];

				foreach ($db_templates as $del_template) {
					if (array_key_exists($del_template['templateid'], $templates_clear)) {
						$del_links_clear[$del_template['templateid']][] = $host[$id_field_name];
					}
					else {
						$del_links[$del_template['templateid']][] = $host[$id_field_name];
					}
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as $template) {
					$del_links_clear[$template['templateid']][] = $host[$id_field_name];
				}
			}
		}

		while ($del_links_clear) {
			$templateid = key($del_links_clear);
			$hostids = reset($del_links_clear);
			$templateids = [$templateid];
			unset($del_links_clear[$templateid]);

			foreach ($del_links_clear as $templateid => $_hostids) {
				if ($_hostids === $hostids) {
					$templateids[] = $templateid;
					unset($del_links_clear[$templateid]);
				}
			}

			self::unlinkTemplatesObjects($templateids, $hostids, true);
		}

		while ($del_links) {
			$templateid = key($del_links);
			$hostids = reset($del_links);
			$templateids = [$templateid];
			unset($del_links[$templateid]);

			foreach ($del_links as $templateid => $_hostids) {
				if ($_hostids === $hostids) {
					$templateids[] = $templateid;
					unset($del_links[$templateid]);
				}
			}

			self::unlinkTemplatesObjects($templateids, $hostids);
		}

		while ($ins_links) {
			$templateid = key($ins_links);
			$hostids = reset($ins_links);
			$templateids = [$templateid];
			unset($ins_links[$templateid]);

			foreach ($ins_links as $templateid => $_hostids) {
				if ($_hostids === $hostids) {
					$templateids[] = $templateid;
					unset($ins_links[$templateid]);
				}
			}

			self::linkTemplatesObjects($templateids, $hostids);
		}
	}

	/**
	 * Unlink or clear objects of given templates from given hosts or templates.
	 *
	 * @param array      $templateids
	 * @param array|null $hostids
	 * @param bool       $clear
	 */
	protected static function unlinkTemplatesObjects(array $templateids, array $hostids = null,
			bool $clear = false): void {
		$flags = ($clear)
			? [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE]
			: [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE];

		// triggers
		$db_triggers = DBselect(
			'SELECT DISTINCT f.triggerid'.
			' FROM functions f,items i'.
			' WHERE f.itemid=i.itemid'.
				' AND '.dbConditionInt('i.hostid', $templateids)
		);

		$tpl_triggerids = DBfetchColumn($db_triggers, 'triggerid');
		$upd_triggers = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		if ($tpl_triggerids) {
			$sql_distinct = ($hostids !== null) ? ' DISTINCT' : '';
			$sql_from = ($hostids !== null) ? ',functions f,items i' : '';
			$sql_where = ($hostids !== null)
				? ' AND t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND '.dbConditionInt('i.hostid', $hostids)
				: '';

			$db_triggers = DBSelect(
				'SELECT'.$sql_distinct.' t.triggerid,t.flags'.
				' FROM triggers t'.$sql_from.
				' WHERE '.dbConditionInt('t.templateid', $tpl_triggerids).
					' AND '.dbConditionInt('t.flags', $flags).
					$sql_where
			);

			while ($db_trigger = DBfetch($db_triggers)) {
				if ($clear) {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']] = true;
				}
				else {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']] = [
						'values' => ['templateid' => 0],
						'where' => ['triggerid' => $db_trigger['triggerid']]
					];
				}
			}

			if (!$clear && ($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL] || $upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE])) {
				$db_triggers = DBselect(
					'SELECT DISTINCT t.triggerid,t.flags'.
					' FROM triggers t,functions f,items i,hosts h'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_TEMPLATE.
						' AND '.dbConditionInt('t.triggerid', array_keys(
							$upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL] + $upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]
						))
				);

				while ($db_trigger = DBfetch($db_triggers)) {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']]['values']['uuid'] = generateUuidV4();
				}
			}
		}

		if ($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]) {
			if ($clear) {
				CTriggerManager::delete(array_keys($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]));
			}
			else {
				DB::update('triggers', $upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]);
			}
		}

		if ($upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			if ($clear) {
				CTriggerPrototypeManager::delete(array_keys($upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
			}
			else {
				DB::update('triggers', $upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			}
		}

		// graphs
		$db_tpl_graphs = DBselect(
			'SELECT DISTINCT g.graphid'.
			' FROM graphs g,graphs_items gi,items i'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=i.itemid'.
				' AND '.dbConditionInt('i.hostid', $templateids).
				' AND '.dbConditionInt('g.flags', $flags)
		);

		$tpl_graphids = [];

		while ($db_tpl_graph = DBfetch($db_tpl_graphs)) {
			$tpl_graphids[] = $db_tpl_graph['graphid'];
		}

		if ($tpl_graphids) {
			$upd_graphs = [
				ZBX_FLAG_DISCOVERY_NORMAL => [],
				ZBX_FLAG_DISCOVERY_PROTOTYPE => []
			];

			$sql = ($hostids !== null)
				? 'SELECT DISTINCT g.graphid,g.flags'.
					' FROM graphs g,graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND '.dbConditionInt('g.templateid', $tpl_graphids).
						' AND '.dbConditionInt('i.hostid', $hostids)
				: 'SELECT g.graphid,g.flags'.
					' FROM graphs g'.
					' WHERE '.dbConditionInt('g.templateid', $tpl_graphids);

			$db_graphs = DBSelect($sql);

			while ($db_graph = DBfetch($db_graphs)) {
				if ($clear) {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']] = true;
				}
				else {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']] = [
						'values' => ['templateid' => 0],
						'where' => ['graphid' => $db_graph['graphid']]
					];
				}
			}

			if (!$clear && ($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL] || $upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE])) {
				$db_graphs = DBselect(
					'SELECT DISTINCT g.graphid,g.flags'.
					' FROM graphs g,graphs_items gi,items i,hosts h'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_TEMPLATE.
						' AND '.dbConditionInt('g.graphid', array_keys(
							$upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL] + $upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]
						))
				);

				while ($db_graph = DBfetch($db_graphs)) {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']]['values']['uuid'] = generateUuidV4();
				}
			}

			if ($upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
				if ($clear) {
					CGraphPrototypeManager::delete(array_keys($upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
				}
				else {
					DB::update('graphs', $upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
				}
			}

			if ($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]) {
				if ($clear) {
					CGraphManager::delete(array_keys($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]));
				}
				else {
					DB::update('graphs', $upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]);
				}
			}
		}

		if ($clear) {
			CDiscoveryRule::clearTemplateObjects($templateids, $hostids);
			CItem::clearTemplateObjects($templateids, $hostids);
			CHttpTest::clearTemplateObjects($templateids, $hostids);
		}
		else {
			CDiscoveryRule::unlinkTemplateObjects($templateids, $hostids);
			CItem::unlinkTemplateObjects($templateids, $hostids);
			CHttpTest::unlinkTemplateObjects($templateids, $hostids);
		}
	}

	/**
	 * Add objects of given templates to given hosts or templates.
	 *
	 * @param array $templateids
	 * @param array $hostids
	 */
	private static function linkTemplatesObjects(array $templateids, array $hostids): void {
		// TODO: Modify parameters of syncTemplates methods when complete audit log will be implementing for hosts.
		$link_request = [
			'templateids' => $templateids,
			'hostids' => $hostids
		];

		foreach ($templateids as $templateid) {
			// Fist link web items, so that later regular items can use web item as their master item.
			Manager::HttpTest()->link($templateid, $hostids);
		}

		CItem::linkTemplateObjects($templateids, $hostids);
		API::Trigger()->syncTemplates($link_request);
		API::Graph()->syncTemplates($link_request);
		CDiscoveryRule::linkTemplateObjects($templateids, $hostids);

		CTriggerGeneral::syncTemplateDependencies($link_request['templateids'], $link_request['hostids']);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		// Add templates.
		if ($options['selectParentTemplates'] !== null) {
			if ($options['selectParentTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];

				// Get template IDs for each host and additional field from relation table if necessary.
				$hosts_templates = DBfetchArray(DBselect(
					'SELECT ht.hostid,ht.templateid'.
						($this->outputIsRequested('link_type', $options['selectParentTemplates'])
							? ',ht.link_type'
							: ''
						).
					' FROM hosts_templates ht'.
					' WHERE '.dbConditionId('ht.hostid', array_keys($result))
				));

				if ($hosts_templates) {
					// Select also template ID if not selected. It can be removed from results if not requested.
					$template_options = $this->outputIsRequested('templateid', $options['selectParentTemplates'])
						? $options['selectParentTemplates']
						: array_merge($options['selectParentTemplates'], ['templateid']);

					/*
					 * Since templates API does not have "link_type" field, remove it from request, so that template.get
					 * validation may pass successfully.
					 */
					if ($this->outputIsRequested('link_type', $template_options) && is_array($template_options)
							&& ($key = array_search('link_type', $template_options)) !== false) {
						unset($template_options[$key]);
					}

					$templates = API::Template()->get([
						'output' => $template_options,
						'templateids' => array_column($hosts_templates, 'templateid'),
						'nopermissions' => $options['nopermissions'],
						'preservekeys' => true
					]);

					if ($options['limitSelects'] !== null) {
						order_result($templates, 'host');
					}
				}

				/*
				 * In order to correctly slice the ordered templates in case of "limitSelects", first they must be
				 * mapped for each host. Otherwise incorrect results may appear. $relation_map key is the host ID, and
				 * values are template ID and, if selected, "link_type".
				 */
				$relation_map = [];
				foreach ($hosts_templates as $host_template) {
					if (!array_key_exists($host_template['hostid'], $relation_map)) {
						$relation_map[$host_template['hostid']] = [];
					}

					$related_fields = ['templateid' => $host_template['templateid']];

					if ($this->outputIsRequested('link_type', $options['selectParentTemplates'])) {
						$related_fields['link_type'] = $host_template['link_type'];
					}

					$relation_map[$host_template['hostid']][] = $related_fields;
				}

				foreach ($result as $hostid => &$host) {
					$host['parentTemplates'] = [];

					if (array_key_exists($hostid, $relation_map)) {
						$templateids = array_column($relation_map[$hostid], 'templateid');
						$templateids = array_combine($templateids, $templateids);

						// Find the matching templates and limit the results if necessary.
						$host['parentTemplates'] = array_values(array_intersect_key($templates, $templateids));

						if ($options['limitSelects'] !== null && $options['limitSelects'] != 0) {
							$host['parentTemplates'] = array_slice($host['parentTemplates'], 0,
								$options['limitSelects']
							);
						}

						// Append the additional field from relation table.
						if ($this->outputIsRequested('link_type', $options['selectParentTemplates'])) {
							foreach ($host['parentTemplates'] as &$template) {
								foreach ($relation_map[$hostid] as $rel_template) {
									if (bccomp($template['templateid'], $rel_template['templateid']) == 0) {
										$template['link_type'] = $rel_template['link_type'];
									}
								}
							}
						}

						// Unset fields if they were not requested.
						$host['parentTemplates'] = $this->unsetExtraFields($host['parentTemplates'], ['templateid'],
							$options['selectParentTemplates']
						);
					}
				}
				unset($host);
			}
			else {
				$templates = API::Template()->get([
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['parentTemplates'] = array_key_exists($hostid, $templates)
						? $templates[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectItems'] !== null) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = API::Item()->get([
					'output' => $this->outputExtend($options['selectItems'], ['hostid', 'itemid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($items, 'name');
				}

				$relationMap = $this->createRelationMap($items, 'hostid', 'itemid');

				$items = $this->unsetExtraFields($items, ['hostid', 'itemid'], $options['selectItems']);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::Item()->get([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$items = zbx_toHash($items, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['items'] = array_key_exists($hostid, $items) ? $items[$hostid]['rowscount'] : '0';
				}
			}
		}

		if ($options['selectDiscoveries'] !== null) {
			if ($options['selectDiscoveries'] != API_OUTPUT_COUNT) {
				$items = API::DiscoveryRule()->get([
					'output' => $this->outputExtend($options['selectDiscoveries'], ['hostid', 'itemid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($items, 'name');
				}

				$relationMap = $this->createRelationMap($items, 'hostid', 'itemid');

				$items = $this->unsetExtraFields($items, ['hostid', 'itemid'], $options['selectDiscoveries']);
				$result = $relationMap->mapMany($result, $items, 'discoveries', $options['limitSelects']);
			}
			else {
				$items = API::DiscoveryRule()->get([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$items = zbx_toHash($items, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['discoveries'] = array_key_exists($hostid, $items)
						? $items[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectTriggers'] !== null) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = [];
				$relationMap = new CRelationMap();
				// discovered items
				$res = DBselect(
					'SELECT i.hostid,f.triggerid'.
						' FROM items i,functions f'.
						' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND i.itemid=f.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostid'], $relation['triggerid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$triggers = API::Trigger()->get([
						'output' => $options['selectTriggers'],
						'triggerids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($triggers, 'description');
					}
				}

				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::Trigger()->get([
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$triggers = zbx_toHash($triggers, 'hostid');

				foreach ($result as $hostid => $host) {
					$result[$hostid]['triggers'] = array_key_exists($hostid, $triggers)
						? $triggers[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectGraphs'] !== null) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$graphs = [];
				$relationMap = new CRelationMap();
				// discovered items
				$res = DBselect(
					'SELECT i.hostid,gi.graphid'.
						' FROM items i,graphs_items gi'.
						' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND i.itemid=gi.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostid'], $relation['graphid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$graphs = API::Graph()->get([
						'output' => $options['selectGraphs'],
						'graphids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($graphs, 'name');
					}
				}

				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::Graph()->get([
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$graphs = zbx_toHash($graphs, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['graphs'] = array_key_exists($hostid, $graphs)
						? $graphs[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectHttpTests'] !== null) {
			if ($options['selectHttpTests'] != API_OUTPUT_COUNT) {
				$httpTests = API::HttpTest()->get([
					'output' => $this->outputExtend($options['selectHttpTests'], ['hostid', 'httptestid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($httpTests, 'name');
				}

				$relationMap = $this->createRelationMap($httpTests, 'hostid', 'httptestid');

				$httpTests = $this->unsetExtraFields($httpTests, ['hostid', 'httptestid'], $options['selectHttpTests']);
				$result = $relationMap->mapMany($result, $httpTests, 'httpTests', $options['limitSelects']);
			}
			else {
				$httpTests = API::HttpTest()->get([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$httpTests = zbx_toHash($httpTests, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['httpTests'] = array_key_exists($hostid, $httpTests)
						? $httpTests[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectValueMaps'] !== null) {
			if ($options['selectValueMaps'] === API_OUTPUT_EXTEND) {
				$options['selectValueMaps'] = ['valuemapid', 'name', 'mappings'];
			}

			foreach ($result as &$host) {
				$host['valuemaps'] = [];
			}
			unset($host);

			$valuemaps = DB::select('valuemap', [
				'output' => array_diff($this->outputExtend($options['selectValueMaps'], ['valuemapid', 'hostid']),
					['mappings']
				),
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			]);

			if ($this->outputIsRequested('mappings', $options['selectValueMaps']) && $valuemaps) {
				$params = [
					'output' => ['valuemapid', 'type', 'value', 'newvalue'],
					'filter' => ['valuemapid' => array_keys($valuemaps)],
					'sortfield' => ['sortorder']
				];
				$query = DBselect(DB::makeSql('valuemap_mapping', $params));

				while ($mapping = DBfetch($query)) {
					$valuemaps[$mapping['valuemapid']]['mappings'][] = [
						'type' => $mapping['type'],
						'value' => $mapping['value'],
						'newvalue' => $mapping['newvalue']
					];
				}
			}

			foreach ($valuemaps as $valuemap) {
				$result[$valuemap['hostid']]['valuemaps'][] = array_intersect_key($valuemap,
					array_flip($options['selectValueMaps'])
				);
			}
		}

		return $result;
	}

	protected static function addGroupsByData(array $data, array &$hosts): void {
		if (!array_key_exists('groups', $data) && (!array_key_exists('groupids', $data) || !$data['groupids'])) {
			return;
		}

		$data['groups'] = array_key_exists('groups', $data) ? $data['groups'] : [];

		foreach ($hosts as &$host) {
			$host['groups'] = $data['groups'];
		}
		unset($host);
	}

	protected static function addMacrosByData(array $data, array &$hosts): void {
		if (!array_key_exists('macros', $data) && (!array_key_exists('macro_names', $data) || !$data['macro_names'])) {
			return;
		}

		$data['macros'] = array_key_exists('macros', $data) ? $data['macros'] : [];

		foreach ($hosts as &$host) {
			$host['macros'] = $data['macros'];
		}
		unset($host);
	}

	protected function addTemplatesByData(array $data, array &$hosts): void {
		$templates_field_name = $this instanceof CTemplate ? 'templates_link' : 'templates';
		$templateids_field_name = $this instanceof CTemplate ? 'templateids_link' : 'templateids';

		if (!array_key_exists($templates_field_name, $data)
				&& (!array_key_exists($templateids_field_name, $data) || !$data[$templateids_field_name])) {
			return;
		}

		$data[$templates_field_name] = array_key_exists($templates_field_name, $data)
			? $data[$templates_field_name]
			: [];

		foreach ($hosts as &$host) {
			$host['templates'] = $data[$templates_field_name];
		}
		unset($host);
	}

	protected static function addTemplatesClearByData(array $data, array &$hosts): void {
		if (!array_key_exists('templates_clear', $data)
				&& (!array_key_exists('templateids_clear', $data) || !$data['templateids_clear'])) {
			return;
		}

		if (array_key_exists('templateids_clear', $data)) {
			foreach ($data['templateids_clear'] as $templateid) {
				$data['templates_clear'][] = ['templateid' => $templateid];
			}
		}

		foreach ($hosts as &$host) {
			$host['templates_clear'] = $data['templates_clear'];
		}
		unset($host);
	}

	/**
	 * Add the existing host or template groups, templates, tags, macros.
	 *
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		$this->addAffectedGroups($hosts, $db_hosts);
		parent::addAffectedObjects($hosts, $db_hosts);
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	public function addAffectedGroups(array $hosts, array &$db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('groups', $host)) {
				$hostids[] = $host[$id_field_name];
				$db_hosts[$host[$id_field_name]]['groups'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$editable_groups = null;

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if ($this instanceof CTemplate) {
				$permitted_groups = API::TemplateGroup()->get([
					'output' => [],
					'templateids' => $hostids,
					'preservekeys' => true
				]);

				$editable_groups = API::TemplateGroup()->get([
					'output' => [],
					'templateids' => $hostids,
					'editable' => true,
					'preservekeys' => true
				]);
			}
			else {
				$permitted_groups = API::HostGroup()->get([
					'output' => [],
					'hostids' => $hostids,
					'preservekeys' => true
				]);

				$editable_groups = API::HostGroup()->get([
					'output' => [],
					'hostids' => $hostids,
					'editable' => true,
					'preservekeys' => true
				]);
			}
		}

		$options = [
			'output' => ['hostgroupid', 'hostid', 'groupid'],
			'filter' => ['hostid' => $hostids]
		];
		$db_groups = DBselect(DB::makeSql('hosts_groups', $options));

		while ($db_group = DBfetch($db_groups)) {
			if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN
					|| array_key_exists($db_group['groupid'], $permitted_groups)) {
				$permission = PERM_READ;

				if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN
						|| array_key_exists($db_group['groupid'], $editable_groups)) {
					$permission = PERM_READ_WRITE;
				}

				$db_hosts[$db_group['hostid']]['groups'][$db_group['hostgroupid']] =
					array_diff_key($db_group, array_flip(['hostid'])) + ['permission' => $permission];
			}
			else {
				$db_hosts[$db_group['hostid']]['nopermissions_groups'][$db_group['hostgroupid']] =
					array_diff_key($db_group, array_flip(['hostid']));
			}
		}
	}

	protected function addHostMacroIds(array &$hosts, array $db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		foreach ($hosts as &$host) {
			$db_hostmacroids = [];

			foreach ($db_hosts[$host[$id_field_name]]['macros'] as $db_macro) {
				$db_hostmacroids[CApiInputValidator::trimMacro($db_macro['macro'])] = $db_macro['hostmacroid'];
			}

			foreach ($host['macros'] as &$macro) {
				$trimmed_macro = CApiInputValidator::trimMacro($macro['macro']);

				if (array_key_exists($trimmed_macro, $db_hostmacroids)) {
					$macro['hostmacroid'] = $db_hostmacroids[$trimmed_macro];
				}
			}
			unset($macro);
		}
		unset($host);
	}

	public function addUnchangedGroups(array &$hosts, array $db_hosts, array $del_objectids = []): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		if (!array_key_exists('groups', reset($hosts))) {
			return;
		}

		foreach ($hosts as &$host) {
			$groupids = array_column($host['groups'], 'groupid');

			foreach ($db_hosts[$host[$id_field_name]]['groups'] as $db_group) {
				if (!in_array($db_group['groupid'], $groupids)
						&& (!array_key_exists('groupids', $del_objectids)
							|| !in_array($db_group['groupid'], $del_objectids['groupids']))) {
					$host['groups'][] = ['groupid' => $db_group['groupid']];
				}
			}
		}
		unset($host);
	}

	protected function addUnchangedMacros(array &$hosts, array $db_hosts, array $del_objectids = []): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		if (!array_key_exists('macros', reset($hosts))) {
			return;
		}

		if (array_key_exists('macro_names', $del_objectids)) {
			$trimmed_del_macros = [];

			foreach ($del_objectids['macro_names'] as $macro) {
				$trimmed_del_macros[] = CApiInputValidator::trimMacro($macro);
			}
		}

		foreach ($hosts as &$host) {
			foreach ($db_hosts[$host[$id_field_name]]['macros'] as $db_macro) {
				if (!array_key_exists('macro_names', $del_objectids)
						|| !in_array(CApiInputValidator::trimMacro($db_macro['macro']), $trimmed_del_macros)) {
					$host['macros'][] = array_intersect_key($db_macro, array_flip(['hostmacroid']));
				}
			}
		}
		unset($host);
	}

	protected function addUnchangedTemplates(array &$hosts, array $db_hosts, array $del_objectids = []): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';
		$templateids_field_name = $this instanceof CTemplate ? 'templateids_link' : 'templateids';

		if (!array_key_exists('templates', reset($hosts))) {
			return;
		}

		foreach ($hosts as &$host) {
			$templateids = array_column($host['templates'], 'templateid');

			foreach ($db_hosts[$host[$id_field_name]]['templates'] as $db_template) {
				if (!in_array($db_template['templateid'], $templateids)
						&& (!array_key_exists($templateids_field_name, $del_objectids)
							|| !in_array($db_template['templateid'], $del_objectids[$templateids_field_name]))
						&& (!array_key_exists('templateids_clear', $del_objectids)
							|| !in_array($db_template['templateid'], $del_objectids['templateids_clear']))) {
					$host['templates'][] = ['templateid' => $db_template['templateid']];
				}
			}
		}
		unset($host);
	}

	protected static function deleteHgSets(array $db_hosts): void {
		$hgsets = [];
		$hgset_hash = self::getHgSetHash([]);

		foreach ($db_hosts as $hostid => $foo) {
			$hgsets[$hgset_hash]['hash'] = $hgset_hash;
			$hgsets[$hgset_hash]['groupids'] = [];
			$hgsets[$hgset_hash]['hostids'][] = $hostid;
		}

		self::updateHostHgSets($hgsets);
	}
}
