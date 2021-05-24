<?php
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
		if (!empty($data['groups'])) {
			API::HostGroup()->massAdd([
				'hosts' => $data['hosts'],
				'templates' => $data['templates'],
				'groups' => $data['groups']
			]);
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

		if (isset($data['macros'])) {
			$hostMacros = API::UserMacro()->get([
				'output' => ['hostmacroid'],
				'hostids' => $allHostIds,
				'filter' => [
					'macro' => $data['macros']
				]
			]);
			$hostMacroIds = zbx_objectValues($hostMacros, 'hostmacroid');
			API::UserMacro()->delete($hostMacroIds);
		}

		if (isset($data['groupids'])) {
			API::HostGroup()->massRemove($data);
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
			API::DiscoveryRule()->syncTemplates($link_request);
			API::ItemPrototype()->syncTemplates($link_request);
			API::HostPrototype()->syncTemplates($link_request);
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
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$templates = API::Template()->get([
					'output' => $options['selectParentTemplates'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($templates, 'host');
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
				// discovered items
				$res = DBselect(
					'SELECT i.hostid,f.triggerid'.
						' FROM items i,functions f'.
						' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND i.itemid=f.itemid'
				);
				$relationMap = new CRelationMap();
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostid'], $relation['triggerid']);
				}

				$triggers = API::Trigger()->get([
					'output' => $options['selectTriggers'],
					'triggerids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'description');
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
				// discovered items
				$res = DBselect(
					'SELECT i.hostid,gi.graphid'.
						' FROM items i,graphs_items gi'.
						' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND i.itemid=gi.itemid'
				);
				$relationMap = new CRelationMap();
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostid'], $relation['graphid']);
				}

				$graphs = API::Graph()->get([
					'output' => $options['selectGraphs'],
					'graphids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
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

		// adding macros
		if ($options['selectMacros'] !== null && $options['selectMacros'] != API_OUTPUT_COUNT) {
			$macros = API::UserMacro()->get([
				'output' => $this->outputExtend($options['selectMacros'], ['hostid', 'hostmacroid']),
				'hostids' => $hostids,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($macros, 'hostid', 'hostmacroid');

			$macros = $this->unsetExtraFields($macros, ['hostid', 'hostmacroid'], $options['selectMacros']);
			$result = $relationMap->mapMany($result, $macros, 'macros', $options['limitSelects']);
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
			$tags = DBselect(DB::makeSql('host_tag', $tags_options));

			foreach ($result as &$host) {
				$host['tags'] = [];
			}
			unset($host);

			while ($tag = DBfetch($tags)) {
				$result[$tag['hostid']]['tags'][] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];
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
}
