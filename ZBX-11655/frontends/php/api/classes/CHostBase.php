<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
abstract class CHostBase extends CZBXAPI {

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';

	/**
	 * Links the templates to the given hosts.
	 *
	 * @param array $templateIds
	 * @param array $targetIds		an array of host IDs to link the templates to
	 *
	 * @return array 	an array of added hosts_templates rows, with 'hostid' and 'templateid' set for each row
	 */
	protected function link(array $templateIds, array $targetIds) {
		if (empty($templateIds)) {
			return;
		}

		// permission check
		if (!API::Template()->isReadable($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateIds);
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
		foreach ($targetIds as $targetid) {
			$linkedTpls = API::Template()->get(array(
				'nopermissions' => true,
				'output' => array('templateid'),
				'hostids' => $targetid
			));

			$templateIdsAll = array_merge($templateIds, zbx_objectValues($linkedTpls, 'templateid'));

			$dbItems = DBselect(
				'SELECT i.key_'.
					' FROM items i'.
					' WHERE '.dbConditionInt('i.hostid', $templateIdsAll).
					' GROUP BY i.key_'.
					' HAVING COUNT(i.itemid)>1'
			);
			if ($dbItem = DBfetch($dbItems)) {
				$dbItemHost = API::Item()->get(array(
					'output' => array('hostid'),
					'filter' => array('key_' => $dbItem['key_']),
					'templateids' => $templateIdsAll
				));
				$dbItemHost = reset($dbItemHost);

				$template = API::Template()->get(array(
					'output' => array('name'),
					'templateids' => $dbItemHost['hostid']
				));

				$template = reset($template);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template "%1$s" with item key "%2$s" already linked to host.',
						$template['name'], $dbItem['key_']));
			}
		}

		// get DB templates which exists in all targets
		$res = DBselect('SELECT * FROM hosts_templates WHERE '.dbConditionInt('hostid', $targetIds));
		$mas = array();
		while ($row = DBfetch($res)) {
			if (!isset($mas[$row['templateid']])) {
				$mas[$row['templateid']] = array();
			}
			$mas[$row['templateid']][$row['hostid']] = 1;
		}
		$targetIdCount = count($targetIds);
		$commonDBTemplateIds = array();
		foreach ($mas as $templateId => $targetList) {
			if (count($targetList) == $targetIdCount) {
				$commonDBTemplateIds[] = $templateId;
			}
		}

		// check if there are any template with triggers which depends on triggers in templates which will be not linked
		$commonTemplateIds = array_unique(array_merge($commonDBTemplateIds, $templateIds));
		foreach ($templateIds as $templateid) {
			$triggerids = array();
			$dbTriggers = get_triggers_by_hostid($templateid);
			while ($trigger = DBfetch($dbTriggers)) {
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];
			}

			$sql = 'SELECT DISTINCT h.host'.
				' FROM trigger_depends td,functions f,items i,hosts h'.
				' WHERE ('.
				dbConditionInt('td.triggerid_down', $triggerids).
				' AND f.triggerid=td.triggerid_up'.
				' )'.
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
			'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionInt('ht.hostid', $targetIds).
				' AND '.dbConditionInt('ht.templateid', $templateIds)
		);
		$linked = array();
		while ($row = DBfetch($res)) {
			if (!isset($linked[$row['hostid']])) {
				$linked[$row['hostid']] = array();
			}
			$linked[$row['hostid']][$row['templateid']] = 1;
		}

		// add template linkages, if problems rollback later
		$hostsLinkageInserts = array();
		foreach ($targetIds as $targetid) {
			foreach ($templateIds as $templateid) {
				if (isset($linked[$targetid]) && isset($linked[$targetid][$templateid])) {
					continue;
				}
				$hostsLinkageInserts[] = array('hostid' => $targetid, 'templateid' => $templateid);
			}
		}
		DB::insert('hosts_templates', $hostsLinkageInserts);

		// check if all trigger templates are linked to host.
		// we try to find template that is not linked to hosts ($targetids)
		// and exists trigger which reference that template and template from ($templateids)
		$sql = 'SELECT DISTINCT h.host'.
			' FROM functions f,items i,triggers t,hosts h'.
			' WHERE f.itemid=i.itemid'.
			' AND f.triggerid=t.triggerid'.
			' AND i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_TEMPLATE.
			' AND NOT EXISTS (SELECT 1 FROM hosts_templates ht WHERE ht.templateid=i.hostid AND '.dbConditionInt('ht.hostid', $targetIds).')'.
			' AND EXISTS (SELECT 1 FROM functions ff,items ii WHERE ff.itemid=ii.itemid AND ff.triggerid=t.triggerid AND '.dbConditionInt('ii.hostid', $templateIds). ')';
		if ($dbNotLinkedTpl = DBfetch(DBSelect($sql, 1))) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Trigger has items from template "%1$s" that is not linked to host.', $dbNotLinkedTpl['host'])
			);
		}

		// check template linkage circularity
		$res = DBselect(
			'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht,hosts h'.
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

		return $hostsLinkageInserts;
	}

	protected function unlink($templateids, $targetids = null) {
		$cond = array('templateid' => $templateids);
		if (!is_null($targetids)) {
			$cond['hostid'] =  $targetids;
		}
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
	 * @throw APIException rises exception if cycle or double linkage is found
	 *
	 * @param array $graph - array with keys as parent ids and values as arrays with child ids
	 * @param int $current - cursor for recursive DFS traversal, starting point for algorithm
	 * @param array $path - should be passed empty array for DFS
	 * @param array $visited - there will be stored visited graph node ids
	 *
	 * @return boolean
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
