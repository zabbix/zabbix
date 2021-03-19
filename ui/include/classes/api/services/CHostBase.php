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


abstract class CHostBase extends CApiService {

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
		$templateIds = array_unique($templateIds);

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateIds
		]);

		if ($count != count($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateIds);
		if (!zbx_empty($templateIdDuplicates)) {
			$duplicatesFound = [];
			foreach ($templateIdDuplicates as $value => $count) {
				$duplicatesFound[] = _s('template ID "%1$s" is passed %2$s times', $value, $count);
			}
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot pass duplicate template IDs for the linkage: %1$s.', implode(', ', $duplicatesFound))
			);
		}

		// get DB templates which exists in all targets
		$res = DBselect('SELECT * FROM hosts_templates WHERE '.dbConditionInt('hostid', $targetIds));
		$mas = [];
		while ($row = DBfetch($res)) {
			if (!isset($mas[$row['templateid']])) {
				$mas[$row['templateid']] = [];
			}
			$mas[$row['templateid']][$row['hostid']] = 1;
		}
		$targetIdCount = count($targetIds);
		$commonDBTemplateIds = [];
		foreach ($mas as $templateId => $targetList) {
			if (count($targetList) == $targetIdCount) {
				$commonDBTemplateIds[] = $templateId;
			}
		}

		// check if there are any template with triggers which depends on triggers in templates which will be not linked
		$commonTemplateIds = array_unique(array_merge($commonDBTemplateIds, $templateIds));
		foreach ($templateIds as $templateid) {
			$triggerids = [];
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
				$tmpTpls = API::Template()->get([
					'templateids' => $templateid,
					'output'=> API_OUTPUT_EXTEND
				]);
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
		$linked = [];
		while ($row = DBfetch($res)) {
			$linked[$row['templateid']][$row['hostid']] = true;
		}

		// add template linkages, if problems rollback later
		$hostsLinkageInserts = [];

		foreach ($templateIds as $templateid) {
			$linked_targets = array_key_exists($templateid, $linked) ? $linked[$templateid] : [];

			foreach ($targetIds as $targetid) {
				if (array_key_exists($targetid, $linked_targets)) {
					continue;
				}

				$hostsLinkageInserts[] = ['hostid' => $targetid, 'templateid' => $templateid];
			}
		}

		if ($hostsLinkageInserts) {
			self::checkCircularLinkage($hostsLinkageInserts);
			self::checkDoubleLinkage($hostsLinkageInserts);

			DB::insertBatch('hosts_templates', $hostsLinkageInserts);
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
			' AND NOT EXISTS (SELECT 1 FROM hosts_templates ht WHERE ht.templateid=i.hostid AND '.dbConditionInt('ht.hostid', $targetIds).')'.
			' AND EXISTS (SELECT 1 FROM functions ff,items ii WHERE ff.itemid=ii.itemid AND ff.triggerid=t.triggerid AND '.dbConditionInt('ii.hostid', $templateIds). ')';
		if ($dbNotLinkedTpl = DBfetch(DBSelect($sql, 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Trigger has items from template "%1$s" that is not linked to host.', $dbNotLinkedTpl['host'])
			);
		}

		return $hostsLinkageInserts;
	}

	protected function unlink($templateids, $targetids = null) {
		$cond = ['templateid' => $templateids];
		if (!is_null($targetids)) {
			$cond['hostid'] =  $targetids;
		}
		DB::delete('hosts_templates', $cond);

		if (!is_null($targetids)) {
			$hosts = API::Host()->get([
				'hostids' => $targetids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);
		}
		else{
			$hosts = API::Host()->get([
				'templateids' => $templateids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);
		}

		if (!empty($hosts)) {
			$templates = API::Template()->get([
				'templateids' => $templateids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);

			$hosts = implode(', ', zbx_objectValues($hosts, 'host'));
			$templates = implode(', ', zbx_objectValues($templates, 'host'));

			info(_s('Templates "%1$s" unlinked from hosts "%2$s".', $templates, $hosts));
		}
	}

	/**
	 * Searches for circular linkages for specific template.
	 *
	 * @param array  $links[<templateid>][<hostid>]  The list of linkages.
	 * @param string $templateid                     ID of the template to check circular linkages.
	 * @param array  $hostids[<hostid>]
	 *
	 * @throws APIException if circular linkage is found.
	 */
	private static function checkTemplateCircularLinkage(array $links, $templateid, array $hostids) {
		if (array_key_exists($templateid, $hostids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Circular template linkage is not allowed.'));
		}

		foreach ($hostids as $hostid => $foo) {
			if (array_key_exists($hostid, $links)) {
				self::checkTemplateCircularLinkage($links, $templateid, $links[$hostid]);
			}
		}
	}

	/**
	 * Searches for circular linkages.
	 *
	 * @param array  $host_templates
	 * @param string $host_templates[]['templateid']
	 * @param string $host_templates[]['hostid']
	 */
	private static function checkCircularLinkage(array $host_templates) {
		$links = [];

		foreach ($host_templates as $host_template) {
			$links[$host_template['templateid']][$host_template['hostid']] = true;
		}

		$templateids = array_keys($links);
		$_templateids = $templateids;

		do {
			$result = DBselect(
				'SELECT ht.templateid,ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('ht.hostid', $_templateids)
			);

			$_templateids = [];

			while ($row = DBfetch($result)) {
				if (!array_key_exists($row['templateid'], $links)) {
					$_templateids[$row['templateid']] = true;
				}

				$links[$row['templateid']][$row['hostid']] = true;
			}

			$_templateids = array_keys($_templateids);
		}
		while ($_templateids);

		foreach ($templateids as $templateid) {
			self::checkTemplateCircularLinkage($links, $templateid, $links[$templateid]);
		}
	}

	/**
	 * Searches for double linkages.
	 *
	 * @param array  $links[<hostid>][<templateid>]  The list of linked template IDs by host ID.
	 * @param string $hostid
	 *
	 * @throws APIException if double linkage is found.
	 *
	 * @return array  An array of the linked templates for the selected host.
	 */
	private static function checkTemplateDoubleLinkage(array $links, $hostid) {
		$templateids = $links[$hostid];

		foreach ($links[$hostid] as $templateid => $foo) {
			if (array_key_exists($templateid, $links)) {
				$_templateids = self::checkTemplateDoubleLinkage($links, $templateid);

				if (array_intersect_key($templateids, $_templateids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Template cannot be linked to another template more than once even through other templates.')
					);
				}

				$templateids += $_templateids;
			}
		}

		return $templateids;
	}

	/**
	 * Searches for double linkages.
	 *
	 * @param array  $host_templates
	 * @param string $host_templates[]['templateid']
	 * @param string $host_templates[]['hostid']
	 */
	private static function checkDoubleLinkage(array $host_templates) {
		$links = [];
		$templateids = [];
		$hostids = [];

		foreach ($host_templates as $host_template) {
			$links[$host_template['hostid']][$host_template['templateid']] = true;
			$templateids[$host_template['templateid']] = true;
			$hostids[$host_template['hostid']] = true;
		}

		$_hostids = array_keys($hostids);

		do {
			$result = DBselect(
				'SELECT ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('ht.templateid', $_hostids)
			);

			$_hostids = [];

			while ($row = DBfetch($result)) {
				if (!array_key_exists($row['hostid'], $hostids)) {
					$_hostids[$row['hostid']] = true;
				}

				$hostids[$row['hostid']] = true;
			}

			$_hostids = array_keys($_hostids);
		}
		while ($_hostids);

		$_templateids = array_keys($templateids + $hostids);
		$templateids = [];

		do {
			$result = DBselect(
				'SELECT ht.templateid,ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('hostid', $_templateids)
			);

			$_templateids = [];

			while ($row = DBfetch($result)) {
				if (!array_key_exists($row['templateid'], $templateids)) {
					$_templateids[$row['templateid']] = true;
				}

				$templateids[$row['templateid']] = true;
				$links[$row['hostid']][$row['templateid']] = true;
			}

			$_templateids = array_keys($_templateids);
		}
		while ($_templateids);

		foreach ($hostids as $hostid => $foo) {
			self::checkTemplateDoubleLinkage($links, $hostid);
		}
	}
}
