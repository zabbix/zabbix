<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
 * @package API
 */
abstract class CHostGeneral extends CZBXAPI {

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';

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
			API::HostGroup()->massAdd($options = array(
				'hosts' => $data['hosts'],
				'templates' => $data['templates'],
				'groups' => $data['groups']
			));
		}

		// link templates
		if (!empty($data['templates_link'])) {
			$this->link(zbx_objectValues(zbx_toArray($data['templates_link']), 'templateid'), $allHostIds);
		}

		// create macros
		if (!empty($data['macros'])) {
			$data['macros'] = zbx_toArray($data['macros']);

			$hostMacrosToAdd = array();
			foreach ($data['macros'] as $hostMacro) {
				foreach ($allHostIds as $hostid) {
					$hostMacro['hostid'] = $hostid;
					$hostMacrosToAdd[] = $hostMacro;
				}
			}

			API::UserMacro()->create($hostMacrosToAdd);
		}

		$ids = array('hostids' => $hostIds, 'templateids' => $templateIds);

		return array($this->pkOption() => $ids[$this->pkOption()]);
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

		if (isset($data['groupids'])) {
			API::HostGroup()->massRemove(array(
				'hostids' => $data['hostids'],
				'templateids' => $data['templateids'],
				'groupids' => zbx_toArray($data['groupids'])
			));
		}

		if (!empty($data['templateids_link'])) {
			$this->unlink(zbx_toArray($data['templateids_link']), $allHostIds);
		}

		if (isset($data['templateids_clear'])) {
			$this->unlink(zbx_toArray($data['templateids_clear']), $allHostIds, true);
		}

		if (isset($data['macros'])) {
			$hostMacros = API::UserMacro()->get(array(
				'hostids' => $allHostIds,
				'filter' => array(
					'macro' => $data['macros']
				)
			));
			$hostMacroIds = zbx_objectValues($hostMacros, 'hostmacroid');
			API::UserMacro()->delete($hostMacroIds);
		}

		return array($this->pkOption() => $data[$this->pkOption()]);
	}

	/**
	 * Links the templates to the given hosts.
	 *
	 * @param array $templateids
	 * @param array $targetids    an array of host IDs to link the templates to
	 *
	 * @return bool
	 */
	protected function link(array $templateids, array $targetids) {
		if (empty($templateids)) {
			return;
		}

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateids);
		if (!zbx_empty($templateIdDuplicates)) {
			$duplicatesFound = array();
			foreach ($templateIdDuplicates as $value => $count) {
				$duplicatesFound[] = _s('template ID "%1$s" is passed %2$s times', $value, $count);
			}
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot pass duplicate template IDs for the linkage: %s.', implode(', ', $duplicatesFound))
			);
		}

		// check if any templates linked to targets have more than one unique item key/application
		foreach ($targetids as $targetid) {
			$linkedTpls = $this->get(array(
				'nopermissions' => true,
				'output' => API_OUTPUT_SHORTEN,
				'hostids' => $targetid
			));
			$allids = array_merge($templateids, zbx_objectValues($linkedTpls, 'templateid'));

			$res = DBselect(
				'SELECT key_,COUNT(itemid) AS cnt'.
				' FROM items'.
				' WHERE '.dbConditionInt('hostid', $allids).
				' GROUP BY key_'.
				' HAVING COUNT(itemid)>1'
			);
			if ($dbCnt = DBfetch($res)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template with item key "%1$s" already linked to host.', htmlspecialchars($dbCnt['key_'])));
			}

			$res = DBselect(
				'SELECT name,COUNT(applicationid) AS cnt'.
				' FROM applications'.
				' WHERE '.dbConditionInt('hostid', $allids).
				' GROUP BY name'.
				' HAVING COUNT(applicationid)>1'
			);
			if ($dbCnt = DBfetch($res)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template with application "%1$s" already linked to host.', htmlspecialchars($dbCnt['name'])));
			}
		}

		// get DB templates which exists in all targets
		$res = DBselect('SELECT * FROM hosts_templates WHERE '.dbConditionInt('hostid', $targetids));
		$mas = array();
		while ($row = DBfetch($res)) {
			if (!isset($mas[$row['templateid']])) {
				$mas[$row['templateid']] = array();
			}
			$mas[$row['templateid']][$row['hostid']] = 1;
		}
		$targetIdCount = count($targetids);
		$commonDBTemplateIds = array();
		foreach ($mas as $templateId => $targetList) {
			if (count($targetList) == $targetIdCount) {
				$commonDBTemplateIds[] = $templateId;
			}
		}

		// check if there are any template with triggers which depends on triggers in templates which will be not linked
		$commonTemplateIds = array_unique(array_merge($commonDBTemplateIds, $templateids));
		foreach ($templateids as $templateid) {
			$triggerids = array();
			$dbTriggers = get_triggers_by_hostid($templateid);
			while ($trigger = DBfetch($dbTriggers)) {
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];
			}

			$sql = 'SELECT DISTINCT h.host'.
					' FROM trigger_depends td,functions f,items i,hosts h'.
					' WHERE ('.dbConditionInt('td.triggerid_down', $triggerids).' AND f.triggerid=td.triggerid_up)'.
						' AND i.itemid=f.itemid'.
						' AND h.hostid=i.hostid'.
						' AND '.dbConditionInt('h.hostid', $commonTemplateIds, true).
						' AND h.status='.HOST_STATUS_TEMPLATE;
			if ($dbDepHost = DBfetch(DBselect($sql))) {
				$tmpTpls = API::Template()->get(array(
					'templateids' => $templateid,
					'output'=> API_OUTPUT_EXTEND
				));
				$tmpTpl = reset($tmpTpls);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Trigger in template "%1$s" has dependency with trigger in template "%2$s".', $tmpTpl['host'], $dbDepHost['host']));
			}
		}

		$res = DBselect(
			'SELECT hostid,templateid'.
			' FROM hosts_templates'.
			' WHERE '.dbConditionInt('hostid', $targetids).
				' AND '.dbConditionInt('templateid', $templateids)
		);
		$linked = array();
		while ($row = DBfetch($res)) {
			if (!isset($linked[$row['hostid']])) {
				$linked[$row['hostid']] = array();
			}
			$linked[$row['hostid']][$row['templateid']] = 1;
		}

		// add template linkages, if problems rollback later
		foreach ($targetids as $targetid) {
			foreach ($templateids as $templateid) {
				if (isset($linked[$targetid]) && isset($linked[$targetid][$templateid])) {
					continue;
				}

				$values = array(get_dbid('hosts_templates', 'hosttemplateid'), $targetid, $templateid);
				$sql = 'INSERT INTO hosts_templates VALUES ('.implode(', ', $values).')';
				$result = DBexecute($sql);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBError');
				}
			}
		}

		// check if all trigger templates are linked to host.
		// we try to find template that is not linked to hosts ($targetids)
		// and exists trigger which reference that template and template from ($templateids)
		$sql = 'SELECT DISTINCT h.host'.
				' FROM functions f,items i,triggers t,hosts h'.
				' WHERE f.itemid=i.itemid'.
					' AND f.triggerid=t.triggerid'.
					' AND i.hostid=h.hostid'.
					' AND h.status='.HOST_STATUS_TEMPLATE.
					' AND NOT EXISTS (SELECT 1 FROM hosts_templates ht WHERE ht.templateid=i.hostid AND '.dbConditionInt('ht.hostid', $targetids).')'.
					' AND EXISTS (SELECT 1 FROM functions ff,items ii WHERE ff.itemid=ii.itemid AND ff.triggerid=t.triggerid AND '.dbConditionInt('ii.hostid', $templateids). ')';
		if ($dbNotLinkedTpl = DBfetch(DBSelect($sql, 1))) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Trigger has items from template "%1$s" that is not linked to host.', $dbNotLinkedTpl['host'])
			);
		}

		// check template linkage circularity
		$res = DBselect(
			'SELECT ht.hostid,ht.templateid'.
			' FROM hosts_templates ht,hosts h '.
			' WHERE ht.hostid=h.hostid '.
				' AND h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')'
		);

		// build linkage graph and prepare list for $rootList generation
		$graph = array();
		$hasParentList = array();
		$hasChildList = array();
		$all = array();
		while ($row = DBfetch($res)) {
			if (!isset($graph[$row['hostid']])) {
				$graph[$row['hostid']] = array();
			}
			$graph[$row['hostid']][] = $row['templateid'];
			$hasParentList[$row['templateid']] = $row['templateid'];
			$hasChildList[$row['hostid']] = $row['hostid'];
			$all[$row['templateid']] = $row['templateid'];
			$all[$row['hostid']] = $row['hostid'];
		}

		// get list of templates without parents
		$rootList = array();
		foreach ($hasChildList as $parentId) {
			if (!isset($hasParentList[$parentId])) {
				$rootList[] = $parentId;
			}
		}

		// search cycles and double linkages in rooted parts of graph
		$visited = array();
		foreach ($rootList as $root) {
			$path = array();

			// raise exception on cycle or double linkage
			$this->checkCircularAndDoubleLinkage($graph, $root, $path, $visited);
		}

		// there is still possible cycles without root
		if (count($visited) < count($all)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Circular template linkage is not allowed.'));
		}

		foreach ($targetids as $targetid) {
			foreach ($templateids as $templateid) {
				if (isset($linked[$targetid]) && isset($linked[$targetid][$templateid])) {
					continue;
				}

				API::Application()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));

				API::DiscoveryRule()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));

				API::Itemprototype()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));

				API::Item()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));
			}

			// we do linkage in two separate loops because for triggers you need all items already created on host
			foreach ($templateids as $templateid) {
				if (isset($linked[$targetid]) && isset($linked[$targetid][$templateid])) {
					continue;
				}

				// sync triggers
				API::Trigger()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));

				API::TriggerPrototype()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));

				API::GraphPrototype()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));

				API::Graph()->syncTemplates(array(
					'hostids' => $targetid,
					'templateids' => $templateid
				));
			}
		}

		foreach ($targetids as $targetid) {
			foreach ($templateids as $templateid) {
				if (isset($linked[$targetid]) && isset($linked[$targetid][$templateid])) {
					continue;
				}

				API::Trigger()->syncTemplateDependencies(array(
					'templateids' => $templateid,
					'hostids' => $targetid,
				));
			}
		}
	}

	/**
	 * Unlinks the templates from the given hosts. If $tragetids is set to null, the templates will be unlinked from
	 * all hosts.
	 *
	 * @param $templateids
	 * @param null|array $targetids the IDs of the hosts to unlink the templates from
	 * @param bool $clear           delete all of the inherited objects from the hosts
	 *
	 * @return void
	 */
	protected function unlink($templateids, $targetids = null, $clear = false) {
		$flags = ($clear)
			? array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY)
			: array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY, ZBX_FLAG_DISCOVERY_CHILD);

		/* TRIGGERS {{{ */
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
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot unlink trigger "%s", it has items from template that is left linked to host.', $dbTrigger['description'])
			);
		}


		$sqlFrom = ' triggers t,hosts h';
		$sqlWhere = ' EXISTS ('.
			'SELECT ff.triggerid'.
			' FROM functions ff,items ii'.
			' WHERE ff.triggerid=t.templateid'.
			' AND ii.itemid=ff.itemid'.
			' AND '.dbConditionInt('ii.hostid', $templateids).')'.
			' AND '.dbConditionInt('t.flags', $flags);


		if (!is_null($targetids)) {
			$sqlFrom = ' triggers t,functions f,items i,hosts h';
			$sqlWhere .= ' AND '.dbConditionInt('i.hostid', $targetids).
				' AND f.itemid=i.itemid'.
				' AND t.triggerid=f.triggerid'.
				' AND h.hostid=i.hostid';
		}
		$sql = 'SELECT DISTINCT t.triggerid,t.description,t.flags,t.expression,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbTriggers = DBSelect($sql);
		$triggers = array(
			ZBX_FLAG_DISCOVERY_NORMAL => array(),
			ZBX_FLAG_DISCOVERY_CHILD => array()
		);
		$triggerids = array();
		while ($trigger = DBfetch($dbTriggers)) {
			$triggers[$trigger['flags']][$trigger['triggerid']] = array(
				'description' => $trigger['description'],
				'expression' => explode_exp($trigger['expression']),
				'triggerid' => $trigger['triggerid'],
				'host' => $trigger['host']
			);
			if (!in_array($trigger['triggerid'], $triggerids)) {
				array_push($triggerids, $trigger['triggerid']);
			}
		}

		if (!empty($triggers[ZBX_FLAG_DISCOVERY_NORMAL])) {
			if ($clear) {
				$result = API::Trigger()->delete(array_keys($triggers[ZBX_FLAG_DISCOVERY_NORMAL]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear triggers'));
			}
			else{
				DB::update('triggers', array(
					'values' => array('templateid' => 0),
					'where' => array('triggerid' => array_keys($triggers[ZBX_FLAG_DISCOVERY_NORMAL]))
				));

				foreach ($triggers[ZBX_FLAG_DISCOVERY_NORMAL] as $trigger) {
					info(_s('Unlinked: Trigger "%1$s" on "%2$s".', $trigger['description'], $trigger['host']));
				}
			}
		}

		if (!empty($triggers[ZBX_FLAG_DISCOVERY_CHILD])) {
			if ($clear) {
				$result = API::TriggerPrototype()->delete(array_keys($triggers[ZBX_FLAG_DISCOVERY_CHILD]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear triggers'));
			}
			else{
				DB::update('triggers', array(
					'values' => array('templateid' => 0),
					'where' => array('triggerid' => array_keys($triggers[ZBX_FLAG_DISCOVERY_CHILD]))
				));

				foreach ($triggers[ZBX_FLAG_DISCOVERY_CHILD] as $trigger) {
					info(_s('Unlinked: Trigger prototype "%1$s" on "%2$s".', $trigger['description'], $trigger['host']));
				}
			}
		}
		/* }}} TRIGGERS */


		/* ITEMS, DISCOVERY RULES {{{ */
		$sqlFrom = ' items i1,items i2,hosts h';
		$sqlWhere = ' i2.itemid=i1.templateid'.
			' AND '.dbConditionInt('i2.hostid', $templateids).
			' AND '.dbConditionInt('i1.flags', $flags).
			' AND h.hostid=i1.hostid';

		if (!is_null($targetids)) {
			$sqlWhere .= ' AND '.dbConditionInt('i1.hostid', $targetids);
		}
		$sql = 'SELECT DISTINCT i1.itemid,i1.flags,i1.name,i1.hostid,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbItems = DBSelect($sql);
		$items = array(
			ZBX_FLAG_DISCOVERY_NORMAL => array(),
			ZBX_FLAG_DISCOVERY => array(),
			ZBX_FLAG_DISCOVERY_CHILD => array(),
		);
		while ($item = DBfetch($dbItems)) {
			$items[$item['flags']][$item['itemid']] = array(
				'name' => $item['name'],
				'host' => $item['host']
			);
		}

		if (!empty($items[ZBX_FLAG_DISCOVERY])) {
			if ($clear) {
				$result = API::DiscoveryRule()->delete(array_keys($items[ZBX_FLAG_DISCOVERY]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear discovery rules'));
			}
			else{
				DB::update('items', array(
					'values' => array('templateid' => 0),
					'where' => array('itemid' => array_keys($items[ZBX_FLAG_DISCOVERY]))
				));

				foreach ($items[ZBX_FLAG_DISCOVERY] as $discoveryRule) {
					info(_s('Unlinked: Discovery rule "%1$s" on "%2$s".', $discoveryRule['name'], $discoveryRule['host']));
				}
			}
		}


		if (!empty($items[ZBX_FLAG_DISCOVERY_NORMAL])) {
			if ($clear) {
				$result = API::Item()->delete(array_keys($items[ZBX_FLAG_DISCOVERY_NORMAL]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear items'));
			}
			else{
				DB::update('items', array(
					'values' => array('templateid' => 0),
					'where' => array('itemid' => array_keys($items[ZBX_FLAG_DISCOVERY_NORMAL]))
				));

				foreach ($items[ZBX_FLAG_DISCOVERY_NORMAL] as $item) {
					info(_s('Unlinked: Item "%1$s" on "%2$s".', $item['name'], $item['host']));
				}
			}
		}


		if (!empty($items[ZBX_FLAG_DISCOVERY_CHILD])) {
			if ($clear) {
				$result = API::Itemprototype()->delete(array_keys($items[ZBX_FLAG_DISCOVERY_CHILD]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear item prototypes'));
			}
			else{
				DB::update('items', array(
					'values' => array('templateid' => 0),
					'where' => array('itemid' => array_keys($items[ZBX_FLAG_DISCOVERY_CHILD]))
				));

				foreach ($items[ZBX_FLAG_DISCOVERY_CHILD] as $item) {
					info(_s('Unlinked: Item prototype "%1$s" on "%2$s".', $item['name'], $item['host']));
				}
			}
		}
		/* }}} ITEMS, DISCOVERY RULES */


		/* GRAPHS {{{ */
		$sqlFrom = ' graphs g,hosts h';
		$sqlWhere = ' EXISTS ('.
			'SELECT ggi.graphid'.
			' FROM graphs_items ggi,items ii'.
			' WHERE ggi.graphid=g.templateid'.
			' AND ii.itemid=ggi.itemid'.
			' AND '.dbConditionInt('ii.hostid', $templateids).')'.
			' AND '.dbConditionInt('g.flags', $flags);


		if (!is_null($targetids)) {
			$sqlFrom = ' graphs g,graphs_items gi,items i,hosts h';
			$sqlWhere .= ' AND '.dbConditionInt('i.hostid', $targetids).
				' AND gi.itemid=i.itemid'.
				' AND g.graphid=gi.graphid'.
				' AND h.hostid=i.hostid';
		}
		$sql = 'SELECT DISTINCT g.graphid,g.name,g.flags,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbGraphs = DBSelect($sql);
		$graphs = array(
			ZBX_FLAG_DISCOVERY_NORMAL => array(),
			ZBX_FLAG_DISCOVERY_CHILD => array(),
		);
		while ($graph = DBfetch($dbGraphs)) {
			$graphs[$graph['flags']][$graph['graphid']] = array(
				'name' => $graph['name'],
				'graphid' => $graph['graphid'],
				'host' => $graph['host']
			);
		}

		if (!empty($graphs[ZBX_FLAG_DISCOVERY_CHILD])) {
			if ($clear) {
				$result = API::GraphPrototype()->delete(array_keys($graphs[ZBX_FLAG_DISCOVERY_CHILD]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear graph prototypes'));
			}
			else{
				DB::update('graphs', array(
					'values' => array('templateid' => 0),
					'where' => array('graphid' => array_keys($graphs[ZBX_FLAG_DISCOVERY_CHILD]))
				));

				foreach ($graphs[ZBX_FLAG_DISCOVERY_CHILD] as $graph) {
					info(_s('Unlinked: Graph prototype "%1$s" on "%2$s".', $graph['name'], $graph['host']));
				}
			}
		}


		if (!empty($graphs[ZBX_FLAG_DISCOVERY_NORMAL])) {
			if ($clear) {
				$result = API::Graph()->delete(array_keys($graphs[ZBX_FLAG_DISCOVERY_NORMAL]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear graphs.'));
			}
			else{
				DB::update('graphs', array(
					'values' => array('templateid' => 0),
					'where' => array('graphid' => array_keys($graphs[ZBX_FLAG_DISCOVERY_NORMAL]))
				));

				foreach ($graphs[ZBX_FLAG_DISCOVERY_NORMAL] as $graph) {
					info(_s('Unlinked: Graph "%1$s" on "%2$s".', $graph['name'], $graph['host']));
				}
			}
		}
		/* }}} GRAPHS */


		/* APPLICATIONS {{{ */
		$sqlFrom = ' applications a1,applications a2,hosts h';
		$sqlWhere = ' a2.applicationid=a1.templateid'.
			' AND '.dbConditionInt('a2.hostid', $templateids).
			' AND h.hostid=a1.hostid';
		if (!is_null($targetids)) {
			$sqlWhere .= ' AND '.dbConditionInt('a1.hostid', $targetids);
		}
		$sql = 'SELECT DISTINCT a1.applicationid,a1.name,a1.hostid,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbApplications = DBSelect($sql);
		$applications = array();
		while ($application = DBfetch($dbApplications)) {
			$applications[$application['applicationid']] = array(
				'name' => $application['name'],
				'hostid' => $application['hostid'],
				'host' => $application['host']
			);
		}

		if (!empty($applications)) {
			if ($clear) {
				$result = API::Application()->delete(array_keys($applications), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear applications'));
			}
			else{
				DB::update('applications', array(
					'values' => array('templateid' => 0),
					'where' => array('applicationid' => array_keys($applications))
				));

				foreach ($applications as $application) {
					info(_s('Unlinked: Application "%1$s" on "%2$s".', $application['name'], $application['host']));
				}
			}
		}
		/* }}} APPLICATIONS */


		$cond = array('templateid' => $templateids);
		if (!is_null($targetids)) $cond['hostid'] =  $targetids;
		DB::delete('hosts_templates', $cond);

		if (!is_null($targetids)) {
			$hosts = API::Host()->get(array(
				'hostids' => $targetids,
				'output' => array('hostid', 'host'),
				'nopermissions' => true,
			));
		}
		else{
			$hosts = API::Host()->get(array(
				'templateids' => $templateids,
				'output' => array('hostid', 'host'),
				'nopermissions' => true,
			));
		}

		if (!empty($hosts)) {
			$templates = API::Template()->get(array(
				'templateids' => $templateids,
				'output' => array('hostid', 'host'),
				'nopermissions' => true,
			));

			$hosts = implode(', ', zbx_objectValues($hosts, 'host'));
			$templates = implode(', ', zbx_objectValues($templates, 'host'));

			info(_s('Templates "%1$s" unlinked from hosts "%2$s".', $templates, $hosts));
		}
	}

	/**
	 * Searches for cycles and double linkages in graph.
	 *
	 * @exception rises exception if cycle or double linkage is found
	 *
	 * @param array $graph - array with keys as parent ids and values as arrays with child ids
	 * @param int $current - cursor for recursive DFS traversal, starting point for algorithm
	 * @param array $path - should be passed empty array for DFS
	 * @param array $visited - there will be stored visited graph node ids
	 * @return false
	 */
	protected function checkCircularAndDoubleLinkage($graph, $current, &$path, &$visited) {
		if (isset($path[$current])) {
			if ($path[$current] == 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Circular template linkage is not allowed.'));
			}
			else {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Template cannot be linked to another template more than once even through other templates.'));
			}
		}
		$path[$current] = 1;
		$visited[$current] = 1;

		if (isset($graph[$current])) {
			foreach ($graph[$current] as $next) {
				$this->checkCircularAndDoubleLinkage($graph, $next, $path, $visited);
			}
		}

		$path[$current] = 2;

		return false;
	}
}
