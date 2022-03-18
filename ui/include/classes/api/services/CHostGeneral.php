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
	 * Check for valid host groups.
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

		$count = API::HostGroup()->get([
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
	 * Check templates links for given data of mass API methods.
	 *
	 * @param string $method
	 * @param array  $templateids
	 * @param array  $db_hosts
	 */
	protected function massCheckTemplatesLinks(string $method, array $templateids, array $db_hosts): void {
		$ins_templates = [];
		$del_links = [];
		$check_double_linkage = false;
		$del_templates = [];

		foreach ($db_hosts as $hostid => $db_host) {
			$db_templateids = array_column($db_host['templates'], 'templateid');

			if ($method === 'massadd') {
				$_templateids = array_diff($templateids, $db_templateids);
			}
			elseif ($method === 'massremove') {
				$_templateids = array_diff($db_templateids, $templateids);
			}
			else {
				$_templateids = $templateids;
			}

			$permitted_templateids = $_templateids;
			$templates_count = count($permitted_templateids);
			$upd_templateids = [];

			if (array_key_exists('nopermissions_templates', $db_host)) {
				foreach ($db_host['nopermissions_templates'] as $db_template) {
					$_templateids[] = $db_template['templateid'];
					$templates_count++;
					$upd_templateids[] = $db_template['templateid'];
				}
			}

			foreach ($permitted_templateids as $templateid) {
				$index = array_search($templateid, $db_templateids);

				if ($index !== false) {
					$upd_templateids[] = $templateid;
					unset($db_templateids[$index]);
				}
				else {
					$ins_templates[$templateid][$hostid] = $_templateids;

					if ($this instanceof CTemplate || $templates_count > 1) {
						$check_double_linkage = true;
					}
				}
			}

			foreach ($db_templateids as $db_templateid) {
				$del_links[$db_templateid][$hostid] = true;

				if ($upd_templateids) {
					$del_templates[$db_templateid][$hostid] = $upd_templateids;
				}
			}
		}

		if ($del_templates) {
			$this->checkTriggerDependenciesOfUpdTemplates($del_templates);
			$this->checkTriggerExpressionsOfDelTemplates($del_templates);
		}

		if ($ins_templates) {
			if ($this instanceof CTemplate) {
				self::checkCircularLinkageNew($ins_templates, $del_links);
			}

			if ($check_double_linkage) {
				$this->checkDoubleLinkageNew($ins_templates, $del_links);
			}

			$this->checkTriggerDependenciesOfInsTemplates($ins_templates);
			$this->checkTriggerExpressionsOfInsTemplates($ins_templates);
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
	 * Update table "hosts_templates" and change objects of linked or unliked templates on target hosts or templates.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected function updateTemplates(array &$hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		parent::updateTemplates($hosts, $db_hosts);

		$ins_links = [];
		$del_links = [];
		$del_links_clear = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = ($db_hosts !== null)
				? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
				: [];

			if (array_key_exists('templates', $host)) {
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
	protected static function unlinkTemplatesObjects(array $templateids, array $hostids = null, bool $clear = false): void {
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

		// items, discovery rules
		$upd_items = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_RULE => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		$sqlFrom = ' items i1,items i2,hosts h';
		$sqlWhere = ' i2.itemid=i1.templateid'.
			' AND '.dbConditionInt('i2.hostid', $templateids).
			' AND '.dbConditionInt('i1.flags', $flags).
			' AND h.hostid=i1.hostid';

		if (!is_null($hostids)) {
			$sqlWhere .= ' AND '.dbConditionInt('i1.hostid', $hostids);
		}
		$sql = 'SELECT DISTINCT i1.itemid,i1.flags,h.status as host_status,i1.type'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;

		$dbItems = DBSelect($sql);

		while ($item = DBfetch($dbItems)) {
			if ($clear) {
				$upd_items[$item['flags']][$item['itemid']] = true;
			}
			else {
				$upd_item = ['templateid' => 0];
				if ($item['host_status'] == HOST_STATUS_TEMPLATE && $item['type'] != ITEM_TYPE_HTTPTEST) {
					$upd_item['uuid'] = generateUuidV4();
				}
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL || $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$upd_item['valuemapid'] = 0;
				}

				$upd_items[$item['flags']][$item['itemid']] = [
					'values' => $upd_item,
					'where' => ['itemid' => $item['itemid']]
				];
			}
		}

		if ($upd_items[ZBX_FLAG_DISCOVERY_RULE]) {
			if ($clear) {
				CDiscoveryRuleManager::delete(array_keys($upd_items[ZBX_FLAG_DISCOVERY_RULE]));
			}
			else {
				DB::update('items', $upd_items[ZBX_FLAG_DISCOVERY_RULE]);
			}
		}

		if ($upd_items[ZBX_FLAG_DISCOVERY_NORMAL]) {
			if ($clear) {
				CItemManager::delete(array_keys($upd_items[ZBX_FLAG_DISCOVERY_NORMAL]));
			}
			else {
				DB::update('items', $upd_items[ZBX_FLAG_DISCOVERY_NORMAL]);
			}
		}

		if ($upd_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			if ($clear) {
				CItemPrototypeManager::delete(array_keys($upd_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
			}
			else {
				DB::update('items', $upd_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			}
		}

		// host prototypes
		if (!$clear && $upd_items[ZBX_FLAG_DISCOVERY_RULE]) {
			$host_prototypes = DBSelect(
				'SELECT DISTINCT h.hostid,h3.status as host_status'.
				' FROM hosts h'.
					' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
					' INNER JOIN hosts h2 ON h.templateid=h2.hostid'.
					' INNER JOIN host_discovery hd2 ON h.hostid=hd.hostid'.
					' INNER JOIN items i ON hd.parent_itemid=i.itemid'.
					' INNER JOIN hosts h3 ON i.hostid=h3.hostid'.
				' WHERE '.dbConditionInt('hd.parent_itemid', array_keys($upd_items[ZBX_FLAG_DISCOVERY_RULE]))
			);

			$upd_host_prototypes = [];

			while ($host_prototype = DBfetch($host_prototypes)) {
				$upd_host_prototype = ['templateid' => 0];
				if ($host_prototype['host_status'] == HOST_STATUS_TEMPLATE) {
					$upd_host_prototype['uuid'] = generateUuidV4();
				}

				$upd_host_prototypes[$host_prototype['hostid']] = [
					'values' => $upd_host_prototype,
					'where' => ['hostid' => $host_prototype['hostid']]
				];
			}

			if ($upd_host_prototypes) {
				DB::update('hosts', $upd_host_prototypes);
				DB::update('group_prototype', [
					'values' => ['templateid' => 0],
					'where' => ['hostid' => array_keys($upd_host_prototypes)]
				]);
			}
		}

		// http tests
		$upd_httptests = [];

		$sqlWhere = '';
		if ($hostids !== null) {
			$sqlWhere = ' AND '.dbConditionInt('ht1.hostid', $hostids);
		}
		$sql = 'SELECT DISTINCT ht1.httptestid,h.status as host_status'.
				' FROM httptest ht1,httptest ht2,hosts h'.
				' WHERE ht1.templateid=ht2.httptestid'.
					' AND ht1.hostid=h.hostid'.
					' AND '.dbConditionInt('ht2.hostid', $templateids).
				$sqlWhere;

		$httptests = DBSelect($sql);

		while ($httptest = DBfetch($httptests)) {
			if ($clear) {
				$upd_httptests[$httptest['httptestid']] = true;
			}
			else {
				$upd_httptest = ['templateid' => 0];
				if ($httptest['host_status'] == HOST_STATUS_TEMPLATE) {
					$upd_httptest['uuid'] = generateUuidV4();
				}

				$upd_httptests[$httptest['httptestid']] = [
					'values' => $upd_httptest,
					'where' => ['httptestid' => $httptest['httptestid']]
				];
			}
		}

		if ($upd_httptests) {
			if ($clear) {
				$result = API::HttpTest()->delete(array_keys($upd_httptests), true);
				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear Web scenarios.'));
				}
			}
			else {
				DB::update('httptest', $upd_httptests);
			}
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

		API::Item()->syncTemplates($link_request);
		$ruleids = API::DiscoveryRule()->syncTemplates($templateids, $hostids);

		if ($ruleids) {
			API::ItemPrototype()->syncTemplates($link_request);
			API::HostPrototype()->syncTemplates($ruleids, $hostids);
		}

		API::Trigger()->syncTemplates($link_request);

		if ($ruleids) {
			API::TriggerPrototype()->syncTemplates($link_request);
			API::GraphPrototype()->syncTemplates($link_request);
		}

		API::Graph()->syncTemplates($link_request);

		API::Trigger()->syncTemplateDependencies($link_request);

		if ($ruleids) {
			API::TriggerPrototype()->syncTemplateDependencies($link_request);
		}
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

			$this->link(zbx_objectValues(zbx_toArray($data['templates_link']), 'templateid'), $allHostIds);
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
			$this->unlink(zbx_toArray($data['templateids_link']), $allHostIds);
		}

		if (isset($data['templateids_clear'])) {
			$this->unlink(zbx_toArray($data['templateids_clear']), $allHostIds, true);
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

	protected function link(array $templateIds, array $targetIds) {
		$hosts_linkage_inserts = parent::link($templateIds, $targetIds);
		$templates_hostids = [];
		$link_requests = [];

		foreach ($hosts_linkage_inserts as $host_tpl_ids) {
			$templates_hostids[$host_tpl_ids['templateid']][] = $host_tpl_ids['hostid'];
		}

		foreach ($templates_hostids as $templateid => $hostids) {
			// Fist link web items, so that later regular items can use web item as their master item.
			Manager::HttpTest()->link($templateid, $hostids);
		}

		while ($templates_hostids) {
			$templateid = key($templates_hostids);
			$link_request = [
				'hostids' => reset($templates_hostids),
				'templateids' => [$templateid]
			];
			unset($templates_hostids[$templateid]);

			foreach ($templates_hostids as $templateid => $hostids) {
				if ($link_request['hostids'] === $hostids) {
					$link_request['templateids'][] = $templateid;
					unset($templates_hostids[$templateid]);
				}
			}

			$link_requests[] = $link_request;
		}

		foreach ($link_requests as $link_request) {
			API::Item()->syncTemplates($link_request);
			$ruleids = API::DiscoveryRule()->syncTemplates($link_request['templateids'], $link_request['hostids']);

			if ($ruleids) {
				API::ItemPrototype()->syncTemplates($link_request);
				API::HostPrototype()->syncTemplates($ruleids, $link_request['hostids']);
			}
		}

		// we do linkage in two separate loops because for triggers you need all items already created on host
		foreach ($link_requests as $link_request){
			API::Trigger()->syncTemplates($link_request);
			API::TriggerPrototype()->syncTemplates($link_request);
			API::GraphPrototype()->syncTemplates($link_request);
			API::Graph()->syncTemplates($link_request);
		}

		foreach ($link_requests as $link_request){
			API::Trigger()->syncTemplateDependencies($link_request);
			API::TriggerPrototype()->syncTemplateDependencies($link_request);
		}

		return $hosts_linkage_inserts;
	}

	/**
	 * Unlinks the templates from the given hosts. If $targetids is set to null, the templates will be unlinked from
	 * all hosts.
	 *
	 * @param array      $templateids
	 * @param null|array $targetids		the IDs of the hosts to unlink the templates from
	 * @param bool       $clear			delete all of the inherited objects from the hosts
	 */
	protected function unlink($templateids, $targetids = null, $clear = false) {
		$flags = ($clear)
			? [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE]
			: [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE];

		// check that all triggers on templates that we unlink, don't have items from another templates
		$sql = 'SELECT DISTINCT t.description'.
			' FROM triggers t,functions f,items i'.
			' WHERE t.triggerid=f.triggerid'.
			' AND f.itemid=i.itemid'.
			' AND '.dbConditionInt('i.hostid', $templateids).
			' AND EXISTS ('.
			'SELECT ff.triggerid'.
			' FROM functions ff,items ii'.
			' WHERE ff.itemid=ii.itemid'.
			' AND ff.triggerid=t.triggerid'.
			' AND '.dbConditionInt('ii.hostid', $templateids, true).
			')'.
			' AND t.flags='.ZBX_FLAG_DISCOVERY_NORMAL;
		if ($dbTrigger = DBfetch(DBSelect($sql, 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot unlink trigger "%1$s", it has items from template that is left linked to host.',
					$dbTrigger['description']
				)
			);
		}

		$templ_triggerids = [];

		$db_triggers = DBselect(
			'SELECT DISTINCT f.triggerid'.
			' FROM functions f,items i'.
			' WHERE f.itemid=i.itemid'.
				' AND '.dbConditionInt('i.hostid', $templateids)
		);

		while ($db_trigger = DBfetch($db_triggers)) {
			$templ_triggerids[] = $db_trigger['triggerid'];
		}

		$upd_triggers = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		if ($templ_triggerids) {
			$sql_distinct = ($targetids !== null) ? ' DISTINCT' : '';
			$sql_from = ($targetids !== null) ? ',functions f,items i' : '';
			$sql_where = ($targetids !== null)
				? ' AND t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND '.dbConditionInt('i.hostid', $targetids)
				: '';

			$db_triggers = DBSelect(
				'SELECT'.$sql_distinct.' t.triggerid,t.flags'.
				' FROM triggers t'.$sql_from.
				' WHERE '.dbConditionInt('t.templateid', $templ_triggerids).
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

		/* GRAPHS {{{ */
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

			$sql = ($targetids !== null)
				? 'SELECT DISTINCT g.graphid,g.flags'.
					' FROM graphs g,graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND '.dbConditionInt('g.templateid', $tpl_graphids).
						' AND '.dbConditionInt('i.hostid', $targetids)
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
		/* }}} GRAPHS */

		/* ITEMS, DISCOVERY RULES {{{ */
		$upd_items = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_RULE => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		$sqlFrom = ' items i1,items i2,hosts h';
		$sqlWhere = ' i2.itemid=i1.templateid'.
			' AND '.dbConditionInt('i2.hostid', $templateids).
			' AND '.dbConditionInt('i1.flags', $flags).
			' AND h.hostid=i1.hostid';

		if (!is_null($targetids)) {
			$sqlWhere .= ' AND '.dbConditionInt('i1.hostid', $targetids);
		}
		$sql = 'SELECT DISTINCT i1.itemid,i1.flags,h.status as host_status,i1.type'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;

		$dbItems = DBSelect($sql);

		while ($item = DBfetch($dbItems)) {
			if ($clear) {
				$upd_items[$item['flags']][$item['itemid']] = true;
			}
			else {
				$upd_item = ['templateid' => 0];
				if ($item['host_status'] == HOST_STATUS_TEMPLATE && $item['type'] != ITEM_TYPE_HTTPTEST) {
					$upd_item['uuid'] = generateUuidV4();
				}
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL || $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$upd_item['valuemapid'] = 0;
				}

				$upd_items[$item['flags']][$item['itemid']] = [
					'values' => $upd_item,
					'where' => ['itemid' => $item['itemid']]
				];
			}
		}

		if ($upd_items[ZBX_FLAG_DISCOVERY_RULE]) {
			if ($clear) {
				CDiscoveryRuleManager::delete(array_keys($upd_items[ZBX_FLAG_DISCOVERY_RULE]));
			}
			else {
				DB::update('items', $upd_items[ZBX_FLAG_DISCOVERY_RULE]);
			}
		}

		if ($upd_items[ZBX_FLAG_DISCOVERY_NORMAL]) {
			if ($clear) {
				CItemManager::delete(array_keys($upd_items[ZBX_FLAG_DISCOVERY_NORMAL]));
			}
			else {
				DB::update('items', $upd_items[ZBX_FLAG_DISCOVERY_NORMAL]);
			}
		}

		if ($upd_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			if ($clear) {
				CItemPrototypeManager::delete(array_keys($upd_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
			}
			else {
				DB::update('items', $upd_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			}
		}
		/* }}} ITEMS, DISCOVERY RULES */

		// host prototypes
		// we need only to unlink host prototypes. in case of unlink and clear they will be deleted together with LLD rules.
		if (!$clear && $upd_items[ZBX_FLAG_DISCOVERY_RULE]) {
			$host_prototypes = DBSelect(
				'SELECT DISTINCT h.hostid,h3.status as host_status'.
				' FROM hosts h'.
					' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
					' INNER JOIN hosts h2 ON h.templateid=h2.hostid'.
					' INNER JOIN host_discovery hd2 ON h.hostid=hd.hostid'.
					' INNER JOIN items i ON hd.parent_itemid=i.itemid'.
					' INNER JOIN hosts h3 ON i.hostid=h3.hostid'.
				' WHERE '.dbConditionInt('hd.parent_itemid', array_keys($upd_items[ZBX_FLAG_DISCOVERY_RULE]))
			);

			$upd_host_prototypes = [];

			while ($host_prototype = DBfetch($host_prototypes)) {
				$upd_host_prototype = ['templateid' => 0];
				if ($host_prototype['host_status'] == HOST_STATUS_TEMPLATE) {
					$upd_host_prototype['uuid'] = generateUuidV4();
				}

				$upd_host_prototypes[$host_prototype['hostid']] = [
					'values' => $upd_host_prototype,
					'where' => ['hostid' => $host_prototype['hostid']]
				];
			}

			if ($upd_host_prototypes) {
				DB::update('hosts', $upd_host_prototypes);
				DB::update('group_prototype', [
					'values' => ['templateid' => 0],
					'where' => ['hostid' => array_keys($upd_host_prototypes)]
				]);
			}
		}

		// http tests
		$upd_httptests = [];

		$sqlWhere = '';
		if (!is_null($targetids)) {
			$sqlWhere = ' AND '.dbConditionInt('ht1.hostid', $targetids);
		}
		$sql = 'SELECT DISTINCT ht1.httptestid,h.status as host_status'.
				' FROM httptest ht1,httptest ht2,hosts h'.
				' WHERE ht1.templateid=ht2.httptestid'.
					' AND ht1.hostid=h.hostid'.
					' AND '.dbConditionInt('ht2.hostid', $templateids).
				$sqlWhere;

		$httptests = DBSelect($sql);

		while ($httptest = DBfetch($httptests)) {
			if ($clear) {
				$upd_httptests[$httptest['httptestid']] = true;
			}
			else {
				$upd_httptest = ['templateid' => 0];
				if ($httptest['host_status'] == HOST_STATUS_TEMPLATE) {
					$upd_httptest['uuid'] = generateUuidV4();
				}

				$upd_httptests[$httptest['httptestid']] = [
					'values' => $upd_httptest,
					'where' => ['httptestid' => $httptest['httptestid']]
				];
			}
		}

		if ($upd_httptests) {
			if ($clear) {
				$result = API::HttpTest()->delete(array_keys($upd_httptests), true);
				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear Web scenarios.'));
				}
			}
			else {
				DB::update('httptest', $upd_httptests);
			}
		}

		parent::unlink($templateids, $targetids);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		// adding groups
		if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
			$groups = API::HostGroup()->get([
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

		// adding templates
		if ($options['selectParentTemplates'] !== null) {
			if ($options['selectParentTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectParentTemplates'],
						'templateids' => $related_ids,
						'nopermissions' => $options['nopermissions'],
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($templates, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $templates, 'parentTemplates', $options['limitSelects']);
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

		// adding items
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

		// adding discoveries
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

		// adding triggers
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

		// adding graphs
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

		// adding http tests
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

		// adding tags
		if ($options['selectTags'] !== null && $options['selectTags'] != API_OUTPUT_COUNT) {
			if ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$options['selectTags'] = ['tag', 'value'];
			}

			$tags_options = [
				'output' => $this->outputExtend($options['selectTags'], ['hostid']),
				'filter' => ['hostid' => $hostids]
			];

			foreach ($result as &$host) {
				$host['tags'] = [];
			}
			unset($host);

			$tags = DBselect(DB::makeSql('host_tag', $tags_options));

			while ($tag = DBfetch($tags)) {
				$hostid = $tag['hostid'];
				unset($tag['hosttagid'], $tag['hostid']);
				$result[$hostid]['tags'][] = $tag;
			}
		}

		// Add value mapping.
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
	 * Validates tags.
	 *
	 * @param array  $host
	 * @param int    $host['evaltype']
	 * @param array  $host['tags']
	 * @param string $host['tags'][]['tag']
	 * @param string $host['tags'][]['value']
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateTags(array $host) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'evaltype'	=> ['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR])],
			'tags'		=> ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value'		=> ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]]
		]];

		// Keep values only for fields with defined validation rules.
		$host = array_intersect_key($host, $api_input_rules['fields']);

		if (!CApiInputValidator::validate($api_input_rules, $host, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Add the existing host groups, templates, tags, macros.
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
	protected function addAffectedGroups(array $hosts, array &$db_hosts): void {
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
			$db_groups = API::HostGroup()->get([
				'output' => [],
				$id_field_name.'s' => $hostids,
				'preservekeys' => true
			]);

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
	 * Add the existing groups, macros or templates whether these are affected by the mass methods.
	 *
	 * @param string     $objects
	 * @param array      $objectids
	 * @param array      $db_hosts
	 */
	protected function massAddAffectedObjects(string $objects, array $objectids, array &$db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

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
				$db_groups = API::HostGroup()->get([
					'output' => [],
					$id_field_name.'s' => array_keys($db_hosts),
					'preservekeys' => true
				]);

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

		if ($objects === 'templates') {
			$permitted_templates = [];

			if (!$objectids && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
				$permitted_templates = API::Template()->get([
					'output' => [],
					'hostids' => array_keys($db_hosts),
					'preservekeys' => true
				]);
			}

			$options = [
				'output' => ['hosttemplateid', 'hostid', 'templateid'],
				'filter' => [
					'hostid' => array_keys($db_hosts)
				]
			];
			$db_hosts_templates = DBselect(DB::makeSql('hosts_templates', $options));

			while ($link = DBfetch($db_hosts_templates)) {
				if ($objectids) {
					if (in_array($link['templateid'], $objectids)) {
						$db_hosts[$link['hostid']]['templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
					else {
						$db_hosts[$link['hostid']]['nopermissions_templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
				}
				else {
					if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN
							|| array_key_exists($link['templateid'], $permitted_templates)) {
						$db_hosts[$link['hostid']]['templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
					else {
						$db_hosts[$link['hostid']]['nopermissions_templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
				}
			}
		}
	}

	/**
	 * Get templates or hosts input array based on requested data and database data.
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

			if (array_key_exists('templates', $db_object)) {
				$templates = $this instanceof CTemplate ? 'templates_link' : 'templates';
				$templateids = $this instanceof CTemplate ? 'templateids_link' : 'templateids';

				if (array_key_exists($templates, $data) || array_key_exists($templateids, $data)) {
					$object['templates'] = [];

					if (array_key_exists($templates, $data)) {
						foreach ($data[$templates] as $template) {
							$object['templates'][] = ['templateid' => $template['templateid']];
						}
					}
				}

				if (array_key_exists('templates_clear', $data) || array_key_exists('templateids_clear', $data)) {
					$object['templates_clear'] = [];
					$db_templateids = array_column($db_object['templates'], 'templateid');

					if (array_key_exists('templates_clear', $data)) {
						foreach ($data['templates_clear'] as $template) {
							if (in_array($template['templateid'], $db_templateids)) {
								$object['templates_clear'][] = ['templateid' => $template['templateid']];
							}
						}
					}
					else {
						foreach ($data['templateids_clear'] as $templateid) {
							if (in_array($templateid, $db_templateids)) {
								$object['templates_clear'][] = ['templateid' => $templateid];
							}
						}
					}
				}
			}

			$objects[] = $object;
		}

		return $objects;
	}
}
