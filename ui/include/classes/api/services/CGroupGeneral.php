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
 * Common class for host group API and template group API.
 */
abstract class CGroupGeneral extends CApiService {
	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massadd' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massupdate' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massremove' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'propagate' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'hstgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = ['groupid', 'name'];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	abstract public function get(array $options);

	/**
	 * @param array  $groups
	 *
	 * @return array
	 */
	public function update(array $groups): array {
		$this->validateUpdate($groups, $db_groups);

		$upd_groups = [];

		foreach ($groups as $group) {
			$upd_group = DB::getUpdatedValues('hstgrp', $group, $db_groups[$group['groupid']]);

			if ($upd_group) {
				$upd_groups[] = [
					'values' => $upd_group,
					'where' => ['groupid' => $group['groupid']]
				];
			}
		}

		if ($upd_groups) {
			DB::update('hstgrp', $upd_groups);
		}

		$resource = $this instanceof CHostGroup ? CAudit::RESOURCE_HOST_GROUP : CAudit::RESOURCE_TEMPLATE_GROUP;
		self::addAuditLog(CAudit::ACTION_UPDATE, $resource, $groups, $db_groups);

		return ['groupids' => array_column($groups, 'groupid')];
	}

	/**
	 * @param array $groupids
	 *
	 * @return array
	 */
	public function delete(array $groupids): array {
		$this->validateDelete($groupids, $db_groups);

		$this instanceof CHostGroup ? CHostGroup::deleteForce($db_groups) : CTemplateGroup::deleteForce($db_groups);

		return ['groupids' => $groupids];
	}

	/**
	 * @param array $groups
	 * @param array $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	abstract protected function validateUpdate(array &$groups, array &$db_groups = null): void;

	/**
	 * Validates if groups can be deleted.
	 *
	 * @param array      $groupids
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	abstract protected function validateDelete(array $groupids, array &$db_groups = null): void;

	/**
	 * Check for unique host group or template group names.
	 *
	 * @static
	 *
	 * @param array      $groups
	 * @param array|null $db_groups
	 *
	 * @throws APIException if host group or template group names are not unique.
	 */
	protected function checkDuplicates(array $groups, array $db_groups = null): void {
		$names = [];

		foreach ($groups as $group) {
			if (!array_key_exists('name', $group)) {
				continue;
			}

			if ($db_groups === null || $group['name'] !== $db_groups[$group['groupid']]['name']) {
				$names[] = $group['name'];
			}
		}

		if (!$names) {
			return;
		}

		$group_type = $this instanceof CHostGroup ? HOST_GROUP_TYPE_HOST_GROUP : HOST_GROUP_TYPE_TEMPLATE_GROUP;

		$duplicates = DB::select('hstgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names, 'type' => $group_type],
			'limit' => 1
		]);

		$exception_group = $this instanceof CHostGroup ? 'Host group' : 'Template group';

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('%1$s "%2$s" already exists.',$exception_group, $duplicates[0]['name'])
			);
		}
	}

	/**
	 * Check that new UUIDs are not already used and generate UUIDs where missing.
	 *
	 * @static
	 *
	 * @param array $groups_to_create
	 *
	 * @throws APIException
	 */
	protected static function checkAndAddUuid(array &$groups_to_create): void {
		foreach ($groups_to_create as &$group) {
			if (!array_key_exists('uuid', $group)) {
				$group['uuid'] = generateUuidV4();
			}
		}
		unset($group);

		$db_uuid = DB::select('hstgrp', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($groups_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * Get host groups or template groups input array based on requested data and database data.
	 *
	 * @static
	 *
	 * @param array $data
	 * @param array $db_groups
	 *
	 * @return array
	 */
	protected function getGroupsByData(array $data, array $db_groups): array {
		$objects = $this instanceof CHostGroup ? 'hosts' : 'templates';
		$objectid = $this instanceof CHostGroup ? 'hostid' : 'templateid';

		$groups = [];

		foreach ($db_groups as $db_group) {
			$group = ['groupid' => $db_group['groupid']];

			$group[$objects] = [];
			$db_hosts = array_column($db_group[$objects], null, $objectid);

			if (array_key_exists($objects, $data)) {
				foreach ($data[$objects] as $host) {
					if (array_key_exists($host[$objectid], $db_hosts)) {
						$group[$objects][] = $db_hosts[$host[$objectid]];
					}
					else {
						$group[$objects][] = [$objectid => $host[$objectid]];
					}
				}
			}

			$groups[] = $group;
		}

		return $groups;
	}

	/**
	 * Get rows to insert hosts or templates on the given host groups or template groups.
	 *
	 * @static
	 *
	 * @param array      $groups
	 * @param string     $method
	 * @param array|null $db_hostgroupids
	 *
	 * @return array
	 */
	private function getInsHostsGroups(array $groups, string $method, array &$db_hostgroupids = null): array {
		$ins_hosts_groups = [];
		$objects = $this instanceof CHostGroup ? 'hosts' : 'templates';
		$objectid = $this instanceof CHostGroup ? 'hostid' : 'templateid';

		if ($method === 'massUpdate') {
			$db_hostgroupids = [];
		}

		foreach ($groups as $group) {
			foreach ($group[$objects] as $host) {
				if (!array_key_exists('hostgroupid', $host)) {
					$ins_hosts_groups[] = [
						'hostid' => $host[$objectid],
						'groupid' => $group['groupid']
					];
				}
				elseif ($method === 'massUpdate') {
					$db_hostgroupids[$host['hostgroupid']] = true;
				}
			}
		}

		return $ins_hosts_groups;
	}

	/**
	 * Add IDs of inserted hosts or templates on the given host groups or template groups.
	 *
	 * @param array $groups
	 * @param array $hostgroupids
	 */
	private function addHostgroupids(array &$groups, array $hostgroupids): void {
		$objects = $this instanceof CHostGroup ? 'hosts' : 'templates';

		foreach ($groups as &$group) {
			foreach ($group[$objects] as &$host) {
				if (!array_key_exists('hostgroupid', $host)) {
					$host['hostgroupid'] = array_shift($hostgroupids);
				}
			}
			unset($host);
		}
		unset($group);
	}

	/**
	 * Get IDs to delete hosts or templates from the given host groups or template groups.
	 *
	 * @static
	 *
	 * @param array $db_groups
	 * @param array $db_hostgroupids
	 *
	 * @return array
	 */
	protected function getDelHostgroupids(array $db_groups, array $db_hostgroupids = []): array {
		$objects = $this instanceof CHostGroup ? 'hosts' : 'templates';
		$del_hostgroupids = [];

		foreach ($db_groups as $db_group) {
			$del_hostgroupids += array_diff_key($db_group[$objects], $db_hostgroupids);
		}

		$del_hostgroupids = array_keys($del_hostgroupids);

		return $del_hostgroupids;
	}

	/**
	 * Inherit user groups data of parent host groups or template groups.
	 *
	 * @param array $groups
	 */
	protected function inheritUserGroupsData(array $groups): void {
		$group_links = $this->getGroupLinks($groups);

		if ($group_links) {
			$usrgrps = [];
			$db_usrgrps = [];

			$this->prepareInheritedRights($group_links, $usrgrps, $db_usrgrps);

			if ($this instanceof CHostGroup) {
				$this->prepareInheritedTagFilters($group_links, $usrgrps, $db_usrgrps);
			}

			if ($usrgrps) {
				CUserGroup::updateForce(array_values($usrgrps), $db_usrgrps);
			}
		}
	}

	/**
	 * Get links of parent groups to given groups.
	 *
	 * @param array $groups
	 *
	 * @return array Array where keys are parent group IDs and values are the array of child group IDs.
	 */
	private function getGroupLinks(array $groups): array {
		$parent_names = [];

		foreach ($groups as $group) {
			$name = $group['name'];

			while (($pos = strrpos($name, '/')) !== false) {
				$name = substr($name, 0, $pos);
				$parent_names[$name] = true;
			}
		}

		if (!$parent_names) {
			return [];
		}

		$group_type = $this instanceof CHostGroup ? HOST_GROUP_TYPE_HOST_GROUP : HOST_GROUP_TYPE_TEMPLATE_GROUP;

		$options = [
			'output' => ['groupid', 'name'],
			'filter' => ['name' => array_keys($parent_names), 'type' => $group_type]
		];
		$result = DBselect(DB::makeSql('hstgrp', $options));

		$parents_groupids = [];

		while ($row = DBfetch($result)) {
			$parents_groupids[$row['name']] = $row['groupid'];
		}

		if (!$parents_groupids) {
			return [];
		}

		$group_links = [];

		foreach ($groups as $group) {
			$name = $group['name'];

			while (($pos = strrpos($name, '/')) !== false) {
				$name = substr($name, 0, $pos);

				if (array_key_exists($name, $parents_groupids)) {
					$group_links[$parents_groupids[$name]][] = $group['groupid'];
					break;
				}
			}
		}

		return $group_links;
	}

	/**
	 * Prepare rights to inherit from parent host groups or template groups.
	 *
	 * @static
	 *
	 * @param array  $group_links
	 * @param array  $usrgrps
	 * @param array  $db_usrgrps
	 */
	private function prepareInheritedRights(array $group_links, array &$usrgrps, array &$db_usrgrps): void {
		$db_rights = DBselect(
			'SELECT r.groupid,r.permission,r.id,g.name'.
			' FROM rights r,usrgrp g'.
			' WHERE r.groupid=g.usrgrpid'.
			' AND '.dbConditionInt('r.id', array_keys($group_links))
		);

		$object_rights = $this instanceof CHostGroup ? 'hostgroup_rights' : 'templategroup_rights';
		while ($db_right = DBfetch($db_rights)) {
			if (!array_key_exists($db_right['groupid'], $usrgrps)) {
				$usrgrps[$db_right['groupid']] = ['usrgrpid' => $db_right['groupid']];
				$db_usrgrps[$db_right['groupid']] = [
					'usrgrpid' => $db_right['groupid'],
					'name' => $db_right['name']
				];
			}

			if (!array_key_exists($object_rights, $db_usrgrps[$db_right['groupid']])) {
				$db_usrgrps[$db_right['groupid']][$object_rights] = [];
			}

			foreach ($group_links[$db_right['id']] as $hstgrpid) {
				$usrgrps[$db_right['groupid']][$object_rights][] = [
					'permission' => $db_right['permission'],
					'id' => $hstgrpid
				];
			}
		}
	}

	/**
	 * Prepare tag filters to inherit from parent host groups.
	 *
	 * @static
	 *
	 * @param array  $group_links
	 * @param array  $usrgrps
	 * @param array  $db_usrgrps
	 */
	private static function prepareInheritedTagFilters(array $group_links, array &$usrgrps,
			array &$db_usrgrps): void {
		$db_tag_filters = DBselect(
			'SELECT t.usrgrpid,t.groupid,t.tag,t.value,g.name'.
			' FROM tag_filter t,usrgrp g'.
			' WHERE t.usrgrpid=g.usrgrpid'.
			' AND '.dbConditionInt('t.groupid', array_keys($group_links))
		);

		while ($db_tag_filter = DBfetch($db_tag_filters)) {
			if (!array_key_exists($db_tag_filter['usrgrpid'], $usrgrps)) {
				$usrgrps[$db_tag_filter['usrgrpid']] = ['usrgrpid' => $db_tag_filter['usrgrpid']];
				$db_usrgrps[$db_tag_filter['usrgrpid']] = [
					'usrgrpid' => $db_tag_filter['usrgrpid'],
					'name' => $db_tag_filter['name']
				];
			}

			if (!array_key_exists('tag_filters', $db_usrgrps[$db_tag_filter['usrgrpid']])) {
				$db_usrgrps[$db_tag_filter['usrgrpid']]['tag_filters'] = [];
			}

			foreach ($group_links[$db_tag_filter['groupid']] as $hstgrpid) {
				$usrgrps[$db_tag_filter['usrgrpid']]['tag_filters'][] = [
					'groupid' => $hstgrpid,
					'tag' => $db_tag_filter['tag'],
					'value' => $db_tag_filter['value']
				];
			}
		}
	}

	/**
	 * Add given hosts or templates to given host groups or template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data): array {
		$this->validateMassAdd($data, $db_groups);

		$groups = $this->getGroupsByData($data, $db_groups);
		$ins_hosts_groups = $this->getInsHostsGroups($groups, __FUNCTION__);

		if ($ins_hosts_groups) {
			$hostgroupids = DB::insertBatch('hosts_groups', $ins_hosts_groups);
			$this->addHostgroupids($groups, $hostgroupids);
		}

		$resource = $this instanceof CHostGroup ? CAudit::RESOURCE_HOST_GROUP : CAudit::RESOURCE_TEMPLATE_GROUP;
		self::addAuditLog(CAudit::ACTION_UPDATE, $resource, $groups, $db_groups);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Replace hosts or templates on the given host groups or template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		$this->validateMassUpdate($data, $db_groups);

		$groups = $this->getGroupsByData($data, $db_groups);
		$ins_hosts_groups = $this->getInsHostsGroups($groups, __FUNCTION__, $db_hostgroupids);
		$del_hostgroupids = $this->getDelHostgroupids($db_groups, $db_hostgroupids);

		if ($ins_hosts_groups) {
			$hostgroupids = DB::insertBatch('hosts_groups', $ins_hosts_groups);
			$this->addHostgroupids($groups, $hostgroupids);
		}

		if ($del_hostgroupids) {
			DB::delete('hosts_groups', ['hostgroupid' => $del_hostgroupids]);
		}

		$resource = $this instanceof CHostGroup ? CAudit::RESOURCE_HOST_GROUP : CAudit::RESOURCE_TEMPLATE_GROUP;
		self::addAuditLog(CAudit::ACTION_UPDATE, $resource, $groups, $db_groups);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Remove given hosts or templates from given host groups or template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data): array {
		$this->validateMassRemove($data, $db_groups);

		$groups = $this->getGroupsByData([], $db_groups);
		$del_hostgroupids = $this->getDelHostgroupids($db_groups);

		if ($del_hostgroupids) {
			DB::delete('hosts_groups', ['hostgroupid' => $del_hostgroupids]);
		}

		$resource = $this instanceof CHostGroup ? CAudit::RESOURCE_HOST_GROUP : CAudit::RESOURCE_TEMPLATE_GROUP;
		self::addAuditLog(CAudit::ACTION_UPDATE, $resource, $groups, $db_groups);

		return ['groupids' => $data['groupids']];
	}

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	abstract protected function validateMassAdd(array &$data, ?array &$db_groups): void;

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	abstract protected function validateMassUpdate(array &$data, ?array &$db_groups): void;

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	abstract protected function validateMassRemove(array &$data, ?array &$db_groups): void;

	/**
	 * Add the existing hosts or templates whether these are affected by the mass methods.
	 * If template IDs passed as empty array, all host or template links of given groups will be collected from database
	 * and all existing host or template IDs will be collected in $db_hostids.
	 *
	 * @static
	 *
	 * @param array      $hostids
	 * @param array      $db_groups
	 * @param array|null $db_hostids
	 */
	protected function addAffectedObjects(array $hostids, array &$db_groups, array &$db_hostids = null): void {
		if (!$hostids) {
			$db_hostids = [];
		}

		$objects = $this instanceof CHostGroup ? 'hosts' : 'templates';
		$objectid = $this instanceof CHostGroup ? 'hostid' : 'templateid';

		foreach ($db_groups as &$db_group) {
			$db_group[$objects] = [];
		}
		unset($db_group);

		if ($hostids) {
			$options = [
				'output' => ['hostgroupid', 'hostid', 'groupid'],
				'filter' => [
					'hostid' => $hostids,
					'groupid' => array_keys($db_groups)
				]
			];
			$db_hosts_groups = DBselect(DB::makeSql('hosts_groups', $options));
		}
		else {
			$db_hosts_groups = DBselect(
				'SELECT hg.hostgroupid,hg.hostid,hg.groupid'.
				' FROM hosts_groups hg,hosts h'.
				' WHERE hg.hostid=h.hostid'.
				' AND '.dbConditionInt('hg.groupid', array_keys($db_groups)).
				' AND h.flags='.ZBX_FLAG_DISCOVERY_NORMAL
			);
		}

		while ($link = DBfetch($db_hosts_groups)) {
			$db_groups[$link['groupid']][$objects][$link['hostgroupid']] = [
				'hostgroupid' => $link['hostgroupid'],
				$objectid => $link['hostid']
			];

			if (!$hostids) {
				$db_hostids[$link['hostid']] = true;
			}
		}

		if (!$hostids) {
			$db_hostids = array_keys($db_hostids);
		}
	}

	/**
	 * Check to delete given hosts from the given host groups and templates from given template groups.
	 *
	 * @static
	 *
	 * @param array  $del_hostids
	 * @param array  $groupids
	 *
	 * @throws APIException
	 */
	protected function checkDeletedObjects(array $del_hostids, array $groupids): void {
		$entity = $this instanceof CHostGroup ? API::Host() : API::Template();
		$objectids = $this instanceof CHostGroup ? 'hostids' : 'templateids';
		$db_hosts = $entity->get([
			'output' => ['host'],
			$objectids => $del_hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_hosts) != count($del_hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this instanceof CHostGroup ? CHostGroup::checkHostsWithoutGroups($db_hosts, $groupids)
			: CTemplateGroup::checkTemplatesWithoutGroups($db_hosts, $groupids);
	}

	/**
	 *  Apply permissions to all host group's or template group's subgroups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function propagate(array $data): array {
		$this->validatePropagate($data, $db_groups);

		foreach ($db_groups as $db_group) {
			if ($data['permissions']) {
				$this->inheritPermissions($db_group['groupid'], $db_group['name']);
			}
			if ($this instanceof CHostGroup && $data['tag_filters']) {
				$this->inheritTagFilters($db_group['groupid'], $db_group['name']);
			}
		}

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * @param array $data
	 * @param array $db_groups
	 *
	 * @throws APIException if the input is invalid
	 */
	abstract protected function validatePropagate(array &$data, array &$db_groups = null): void;

	/**
	 * Apply host group rights to all subgroups or template group rights to all subgroups.
	 *
	 * @param string $groupid  Host group or template group ID.
	 * @param string $name     Host group or template group name.
	 */
	protected function inheritPermissions(string $groupid, string $name): void {
		$child_groupids = $this->getChildGroupIds($name);

		$object_rights = $this instanceof CHostGroup ? 'hostgroup_rights' : 'templategroup_rights';
		$select_object_rights = $this instanceof CHostGroup ? 'selectHostGroupRights' : 'selectTemplateGroupRights';

		if (!$child_groupids) {
			return;
		}

		$usrgrps = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			$select_object_rights => ['id', 'permission']
		]);

		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$rights = array_column($usrgrp[$object_rights], null, 'id');

			if (array_key_exists($groupid, $rights)) {
				foreach ($child_groupids as $child_groupid) {
					$rights[$child_groupid] = [
						'id' => $child_groupid,
						'permission' => $rights[$groupid]['permission']
					];
				}
			}
			else {
				foreach ($child_groupids as $child_groupid) {
					unset($rights[$child_groupid]);
				}
			}

			$rights = array_values($rights);

			if ($usrgrp[$object_rights] !== $rights) {
				$upd_usrgrps[] = [
					'usrgrpid' => $usrgrp['usrgrpid'],
					$object_rights => $rights
				];
			}
		}

		if ($upd_usrgrps) {
			API::UserGroup()->update($upd_usrgrps);
		}
	}

	/**
	 * Returns list of child groups for host group or template group with given name.
	 *
	 * @param string $name     host group or template group name.
	 */
	protected function getChildGroupIds(string $name): array {
		$parent = $name.'/';
		$len = strlen($parent);

		$groups = $this->get([
			'output' => ['groupid', 'name'],
			'search' => ['name' => $parent],
			'startSearch' => true
		]);

		$child_groupids = [];
		foreach ($groups as $group) {
			if (substr($group['name'], 0, $len) === $parent) {
				$child_groupids[] = $group['groupid'];
			}
		}

		return $child_groupids;
	}
}
