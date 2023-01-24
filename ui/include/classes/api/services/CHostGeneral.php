<?php
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

	/**
	 * Check for valid host groups and template groups.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException if groups are not valid.
	 */
	protected function checkGroups(array $hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$edit_groupids = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('groups', $host)) {
				continue;
			}

			$groupids = array_column($host['groups'], 'groupid');

			if ($db_hosts === null) {
				$edit_groupids += array_flip($groupids);
			}
			else {
				$db_groupids = array_column($db_hosts[$host[$id_field_name]]['groups'], 'groupid');

				$ins_groupids = array_flip(array_diff($groupids, $db_groupids));
				$del_groupids = array_flip(array_diff($db_groupids, $groupids));

				$edit_groupids += $ins_groupids + $del_groupids;
			}
		}

		if (!$edit_groupids) {
			return;
		}

		$entity = $this instanceof CTemplate ? API::TemplateGroup() : API::HostGroup();
		$count = $entity->get([
			'countOutput' => true,
			'groupids' => array_keys($edit_groupids),
			'editable' => true
		]);

		if ($count != count($edit_groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @param array $hostids    an array of host or template IDs
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	protected function checkHostPermissions(array $hostids) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count == count($hostids)) {
				return;
			}

			$count += API::Template()->get([
				'countOutput' => true,
				'templateids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Allows to:
	 * - add hosts to groups;
	 * - link templates to hosts;
	 * - add new macros to hosts.
	 *
	 * Supported $data parameters are:
	 * - hosts          - an array of hosts to be updated
	 * - templates      - an array of templates to be updated
	 * - groups         - an array of host groups to add the host to
	 * - templates_link - an array of templates to link to the hosts
	 * - macros         - an array of macros to create on the host
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$hostIds = zbx_objectValues($data['hosts'], 'hostid');
		$templateIds = zbx_objectValues($data['templates'], 'templateid');

		$allHostIds = array_merge($hostIds, $templateIds);

		// add groups
		if (array_key_exists('groups', $data) && $data['groups']) {
			$options = ['groups' => $data['groups']];

			if ($data['hosts']) {
				$options += [
					'hosts' => array_map(
						static function($host) {
							return array_intersect_key($host, array_flip(['hostid']));
						},
						$data['hosts']
					)
				];
			}

			if ($data['templates']) {
				$options += [
					'templates' => array_map(
						static function($template) {
							return array_intersect_key($template, array_flip(['templateid']));
						},
						$data['templates']
					)
				];
			}

			API::HostGroup()->massAdd($options);
		}

		// link templates
		if (!empty($data['templates_link'])) {
			$this->checkHostPermissions($allHostIds);

			self::link(array_column(zbx_toArray($data['templates_link']), 'templateid'), $allHostIds);
		}

		// create macros
		if (!empty($data['macros'])) {
			$data['macros'] = zbx_toArray($data['macros']);

			$hostMacrosToAdd = [];
			foreach ($data['macros'] as $hostMacro) {
				foreach ($allHostIds as $hostid) {
					$hostMacro['hostid'] = $hostid;
					$hostMacrosToAdd[] = $hostMacro;
				}
			}

			API::UserMacro()->create($hostMacrosToAdd);
		}

		$ids = ['hostids' => $hostIds, 'templateids' => $templateIds];

		return [$this->pkOption() => $ids[$this->pkOption()]];
	}

	/**
	 * Allows to:
	 * - remove hosts from groups;
	 * - unlink and clear templates from hosts;
	 * - remove macros from hosts.
	 *
	 * Supported $data parameters are:
	 * - hostids            - an array of host IDs to be updated
	 * - templateids        - an array of template IDs to be updated
	 * - groupids           - an array of host group IDs the hosts should be removed from
	 * - templateids_link   - an array of template IDs to unlink from the hosts
	 * - templateids_clear  - an array of template IDs to unlink and clear from the hosts
	 * - macros             - an array of macros to delete from the hosts
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$allHostIds = array_merge($data['hostids'], $data['templateids']);

		$this->checkHostPermissions($allHostIds);

		if (!empty($data['templateids_link'])) {
			self::unlink(zbx_toArray($data['templateids_link']), $allHostIds);
		}

		if (isset($data['templateids_clear'])) {
			self::unlink(zbx_toArray($data['templateids_clear']), $allHostIds, true);
		}

		if (array_key_exists('macros', $data)) {
			if (!$data['macros']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
			}

			$hostMacros = API::UserMacro()->get([
				'output' => ['hostmacroid'],
				'hostids' => $allHostIds,
				'filter' => [
					'macro' => $data['macros']
				]
			]);
			$hostMacroIds = zbx_objectValues($hostMacros, 'hostmacroid');
			if ($hostMacroIds) {
				API::UserMacro()->delete($hostMacroIds);
			}
		}

		if (isset($data['groupids'])) {
			$options = ['groupids' => $data['groupids']];

			if ($data['hostids']) {
				$options['hostids'] = $data['hostids'];
			}

			if ($data['templateids']) {
				$options['templateids'] = $data['templateids'];
			}

			API::HostGroup()->massRemove($options);
		}

		return [$this->pkOption() => $data[$this->pkOption()]];
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

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

	/**
	 * Add the existing host or template groups, tags and macros.
	 *
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		parent::addAffectedObjects($hosts, $db_hosts);
		$this->addAffectedGroups($hosts, $db_hosts);
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private function addAffectedGroups(array $hosts, array &$db_hosts): void {
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

		$filter = ['hostid' => $hostids];

		if (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
			if ($this instanceof CTemplate) {
				$db_groups = API::TemplateGroup()->get([
					'output' => [],
					'templateids' => $hostids,
					'preservekeys' => true
				]);
			}
			else {
				$db_groups = API::HostGroup()->get([
					'output' => [],
					'hostids' => $hostids,
					'preservekeys' => true
				]);
			}

			$filter += ['groupid' => array_keys($db_groups)];
		}

		$options = [
			'output' => ['hostgroupid', 'hostid', 'groupid'],
			'filter' => $filter
		];
		$db_groups = DBselect(DB::makeSql('hosts_groups', $options));

		while ($db_group = DBfetch($db_groups)) {
			$db_hosts[$db_group['hostid']]['groups'][$db_group['hostgroupid']] =
				array_diff_key($db_group, array_flip(['hostid']));
		}
	}

	/**
	 * Add the existing groups and macros if affected by the mass methods.
	 *
	 * @param string     $objects
	 * @param array      $objectids
	 * @param array      $db_hosts
	 */
	protected function massAddAffectedObjects(string $objects, array $objectids, array &$db_hosts): void {
		foreach ($db_hosts as &$db_host) {
			$db_host[$objects] = [];
		}
		unset($db_host);

		if ($objects === 'groups') {
			$filter = ['hostid' => array_keys($db_hosts)];

			if ($objectids) {
				$filter += ['groupid' => $objectids];
			}
			elseif (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
				if ($this instanceof CTemplate) {
					$db_groups = API::TemplateGroup()->get([
						'output' => [],
						'templateids' => array_keys($db_hosts),
						'preservekeys' => true
					]);
				}
				else {
					$db_groups = API::HostGroup()->get([
						'output' => [],
						'hostids' => array_keys($db_hosts),
						'preservekeys' => true
					]);
				}

				$filter += ['groupid' => array_keys($db_groups)];
			}

			$options = [
				'output' => ['hostgroupid', 'hostid', 'groupid'],
				'filter' => $filter
			];
			$db_hosts_groups = DBselect(DB::makeSql('hosts_groups', $options));

			while ($link = DBfetch($db_hosts_groups)) {
				$db_hosts[$link['hostid']]['groups'][$link['hostgroupid']] =
					array_diff_key($link, array_flip(['hostid']));
			}
		}

		if ($objects === 'macros') {
			$options = [
				'output' => ['hostmacroid', 'hostid', 'macro', 'value', 'description', 'type'],
				'filter' => ['hostid' => array_keys($db_hosts)]
			];

			if ($objectids) {
				$macro_patterns = [];
				$trimmed_macros = [];

				foreach ($objectids as $macro) {
					$trimmed_macro = CApiInputValidator::trimMacro($macro);
					$context_pos = strpos($trimmed_macro, ':');

					$macro_patterns[] = ($context_pos === false)
						? '{$'.$trimmed_macro
						: '{$'.substr($trimmed_macro, 0, $context_pos);
					$trimmed_macros[] = $trimmed_macro;
				}

				$options += [
					'search' => ['macro' => $macro_patterns],
					'startSearch' => true,
					'searchByAny' => true
				];
			}

			$db_macros = DBselect(DB::makeSql('hostmacro', $options));

			while ($db_macro = DBfetch($db_macros)) {
				if (!$objectids || in_array(CApiInputValidator::trimMacro($db_macro['macro']), $trimmed_macros)) {
					$db_hosts[$db_macro['hostid']]['macros'][$db_macro['hostmacroid']] =
						array_diff_key($db_macro, array_flip(['hostid']));
				}
			}
		}
	}

	/**
	 * Extracts and combines properties from database data for input array of templates or hosts.
	 *
	 * @param array $data
	 * @param array $db_objects
	 *
	 * @return array
	 */
	protected function getObjectsByData(array $data, array $db_objects): array {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$objects = [];

		foreach ($db_objects as $db_object) {
			$object = [$id_field_name => $db_object[$id_field_name]];

			if (array_key_exists('groups', $db_object)) {
				$object['groups'] = [];

				if (array_key_exists('groups', $data)) {
					foreach ($data['groups'] as $group) {
						$object['groups'][] = ['groupid' => $group['groupid']];
					}
				}
			}

			if (array_key_exists('macros', $db_object)) {
				$object['macros'] = [];

				if (array_key_exists('macros', $data) && is_array(reset($data['macros']))) {
					$db_macros = [];

					foreach ($db_object['macros'] as $db_macro) {
						$db_macros[CApiInputValidator::trimMacro($db_macro['macro'])] = $db_macro;
					}

					foreach ($data['macros'] as $macro) {
						$trimmed_macro = CApiInputValidator::trimMacro($macro['macro']);

						if (array_key_exists($trimmed_macro, $db_macros)) {
							$object['macros'][] = ['hostmacroid' => $db_macros[$trimmed_macro]['hostmacroid']] + $macro
								+ ['description' => DB::getDefault('hostmacro', 'description')];
						}
						else {
							$object['macros'][] = $macro;
						}
					}
				}
			}

			$objects[] = $object;
		}

		return $objects;
	}
}
