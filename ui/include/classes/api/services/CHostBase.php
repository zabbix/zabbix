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
	 * @param array $hosts
	 * @param array $db_hosts
	 *
	 * @throws APIException
	 */
	protected function checkTemplates(array $hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$edit_templates = [];

		foreach ($hosts as $i1 => $host) {
			if (array_key_exists('templates', $host) && array_key_exists('templates_clear', $host)) {
				$path_clear = '/'.($i1 + 1).'/templates_clear';
				$path = '/'.($i1 + 1).'/templates';

				foreach ($host['templates_clear'] as $i2 => $template_clear) {
					foreach ($host['templates'] as $i3 => $template) {
						if (bccomp($template['templateid'], $template_clear['templateid']) == 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path_clear.'/'.($i2 + 1).'/templateid',
								_s('cannot be specified the value of parameter "%1$s"',
									$path.'/'.($i3 + 1).'/templateid'
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
					$db_templates = array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid');

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
			'templateids' => array_keys($edit_templates),
			'editable' => true
		]);

		if ($count != count($edit_templates)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($hosts as $i1 => $host) {
			if (!array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid');
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
	protected function checkTemplatesLinks(array $hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_templates = [];
		$del_templates = [];
		$del_templates_clear = [];
		$check_double_linkage = false;

		foreach ($hosts as $i1 => $host) {
			if (array_key_exists('templates', $host)) {
				$db_templates = ($db_hosts !== null)
					? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
					: [];
				$templates_count = count($host['templates']);
				$templateids = array_column($host['templates'], 'templateid');
				$upd_templates = [];

				foreach ($host['templates'] as $i2 => $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						$upd_templates[$template['templateid']] = $i2;
						unset($db_templates['templateid']);
					}
					else {
						$ins_templates[$template['templateid']][$host[$id_field_name]][$i1][$i2] = $templateids;

						if (($this instanceof CTemplate && $db_hosts !== null) || $templates_count > 1) {
							$check_double_linkage = true;
						}
					}
				}

				foreach ($db_templates as $db_template) {
					$del_templates[$db_template['templateid']][$host[$id_field_name]][$i1] = $upd_templates;
				}

				if (array_key_exists('templates_clear', $host)) {
					foreach ($host['templates_clear'] as $i2 => $template) {
						$del_templates_clear[$template['templateid']][$i1] = $i2;
					}
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				$templateids = array_column($host['templates_clear'], 'templateid');
				$upd_templates = [];

				foreach ($db_hosts[$host[$id_field_name]]['templates'] as $db_template) {
					if (!in_array($db_template['templateid'], $templateids)) {
						$upd_templates[$db_template['templateid']] = '';
					}
				}

				foreach ($host['templates_clear'] as $i2 => $template) {
					$del_templates[$template['templateid']][$host[$id_field_name]][$i1] = $upd_templates;
					$del_templates_clear[$template['templateid']][$i1] = $i2;
				}
			}
		}

		if ($del_templates) {
			if ($this instanceof CHost || $this instanceof CTemplate) {
				$this->checkTriggersOfDelTemplates($del_templates, $del_templates_clear);
			}
		}

		if ($ins_templates) {
			if ($this instanceof CTemplate && $db_hosts !== null) {
				self::checkCircularLinkageNew($ins_templates, $del_templates);
			}

			if ($check_double_linkage) {
				self::checkDoubleLinkageNew($ins_templates, $del_templates);
			}

			$this->checkTriggerDependenciesOfInsTemplates($ins_templates);
			$this->checkTriggerExpressionsOfInsTemplates($ins_templates);
		}
	}

	/**
	 * Check whether triggers of given templates is able to unlink from target hosts or templates.
	 *
	 * @param array  $del_templates
	 * @param string $del_templates[<templateid>][<host index>][<upd_templateid>]  Index of updated template.
	 * @param array  $del_templates_clear[<templateid>][<host index>]              Index of template.
	 */
	private function checkTriggersOfDelTemplates(array $del_templates, array $del_templates_clear): void {
		$all_upd_templates = [];

		foreach ($del_templates as $templateid => $i1_upd_templates) {
			foreach ($i1_upd_templates as $i1 => $upd_templates) {
				if ($upd_templates) {
					$all_upd_templates += $upd_templates;
				}
				else {
					unset($del_templates[$templateid][$i1]);
					unset($del_templates_clear[$templateid][$i1]);
				}
			}
		}

		if (!$del_templates) {
			return;
		}

		$this->checkTriggerDependenciesOfUpdTemplates(array_keys($all_upd_templates), $del_templates,
			$del_templates_clear
		);
		$this->checkTriggerExpressionsOfDelTemplates($del_templates, $del_templates_clear);
	}

	/**
	 * Check whether triggers of existing templates have not dependencies on triggers of unlinked templates on target
	 * hosts or templates.
	 *
	 * @param array  $all_upd_templateids
	 * @param array  $del_templates
	 * @param string $del_templates[<templateid>][<host index>][<upd_templateid]  Index of updated template.
	 * @param array  $del_templates_clear[<templateid>][<host index>]             Index of template.
	 *
	 * @throws APIException
	 */
	private function checkTriggerDependenciesOfUpdTemplates(array $all_upd_templateids, array $del_templates,
			array $del_templates_clear): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS del_templateid,td.triggerid_down,ii.hostid'.
			' FROM items i,functions f,trigger_depends td,functions ff,items ii'.
			' WHERE f.itemid=i.itemid'.
				' AND td.triggerid_up=f.triggerid'.
				' AND ff.triggerid=td.triggerid_down'.
				' AND ii.itemid=df.itemid'.
				' AND '.dbConditionInt('i.hosid', array_keys($del_templates)).
				' AND '.dbConditionInt('ii.hostid', $all_upd_templateids)
		);

		while ($row = DBfetch($result)) {
			foreach ($del_templates[$row['del_templateid']] as $i1 =>$upd_templates) {
				if (array_key_exists($row['hostid'], $upd_templates)) {
					if (array_key_exists($row['del_templateid'], $del_templates_clear)
							&& array_key_exists($i1, $del_templates_clear[$row['del_templateid']])) {
						$i2 = $del_templates_clear[$row['del_templateid']][$i1];
						$path = '/'.$i1.'/templates_clear';

						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/'.($i2 + 1).'/templateid',
							self::getFormattedTriggerError(
								_('cannot be unlinked without template "%1$s" due to dependency of trigger "%2$s"'),
								$row['hostid'], $row['triggerid_down']
							)
						));
					}
					else {
						$i2 = $upd_templates[$row['hostid']];
						$path = '/'.$i1.'/templates';

						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/'.($i2 + 1).'/templateid',
							self::getFormattedTriggerError(
								_('cannot be linked without template "%1$s" due to dependency of trigger "%2$s"'),
								$row['del_templateid'], $row['triggerid_down']
							)
						));
					}
				}
			}
		}
	}

	/**
	 * Check whether all templates of triggers of unlinking templates are unlinked from target hosts or templates.
	 *
	 * @param array  $del_templates
	 * @param string $del_templates[<templateid>][<host index>][<upd_templateid]  Index of updated template.
	 * @param array  $del_templates_clear[<templateid>][<host index>]             Index of template.
	 *
	 * @throws APIException if not linked template is found.
	 */
	private function checkTriggerExpressionsOfDelTemplates(array $del_templates, array $del_templates_clear): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS del_templateid,f.triggerid,ii.hostid'.
			' FROM items i,functions f,functions ff,items ii,hosts h'.
			' WHERE f.itemid=i.itemid'.
				' AND ff.triggerid=f.triggerid'.
				' AND ii.itemid=ff.itemid'.
				' AND h.hostid=ii.hostid'.
				' AND '.dbConditionInt('i.hostid', array_keys($del_templates)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		while ($row = DBfetch($result)) {
			foreach ($del_templates[$row['del_templateid']] as $i1 => $upd_templates) {
				if (array_key_exists($row['hostid'], $upd_templates)) {
					if (array_key_exists($row['del_templateid'], $del_templates_clear)
							&& array_key_exists($i1, $del_templates_clear[$row['del_templateid']])) {
						$i2 = $del_templates_clear[$row['del_templateid']][$i1];
						$path = '/'.($i1 + 1).'/templates_clear';

						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/'.($i2 + 1).'/templateid',
							self::getFormattedTriggerError(
								_('cannot be unlinked without template "%1$s" due to expression of trigger "%2$s"'),
								$row['hostid'], $row['triggerid']
							)
						));
					}
					else {
						$i2 = $upd_templates[$row['hostid']];
						$path = '/'.$i1.'/templates';

						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/'.($i2 + 1).'/templateid',
							self::getFormattedTriggerError(
								_('cannot be linked without template "%1$s" due to expression of trigger "%2$s"'),
								$row['del_templateid'], $row['triggerid']
							)
						));
					}
				}
			}
		}
	}

	/**
	 * Searches for circular linkages.
	 *
	 * @param array $ins_links[<templateid>][<hostid>]
	 * @param array $del_links[<templateid>][<hostid>]
	 */
	private static function checkCircularLinkageNew(array $ins_links, array $del_links): void {
		$templateids = array_keys($ins_links);
		$_templateids = $templateids;

		do {
			$result = DBselect(
				'SELECT ht.templateid,ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('ht.hostid', $_templateids)
			);

			$_templateids = [];

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['templateid'], $del_links)
						&& array_key_exists($row['hostid'], $del_links[$row['templateid']])) {
					continue;
				}

				if (!array_key_exists($row['templateid'], $ins_links)) {
					$_templateids[$row['templateid']] = true;
				}

				$ins_links[$row['templateid']][$row['hostid']] = true;
			}

			$_templateids = array_keys($_templateids);
		}
		while ($_templateids);

		foreach ($templateids as $templateid) {
			self::checkTemplateCircularLinkage($ins_links, $templateid, $ins_links[$templateid]);
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
	private static function checkTemplateCircularLinkage(array $links, $templateid, array $hostids): void {
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
	 * Searches for double linkages.
	 *
	 * @param array $ins_links[<templateid>][<hostid>]
	 * @param array $del_links[<templateid>][<hostid>]
	 */
	private static function checkDoubleLinkageNew(array $ins_links, array $del_links): void {
		$links = [];
		$templateids = [];
		$hostids = [];

		foreach ($ins_links as $templateid => $_hostids) {
			$templateids[$templateid] = true;

			foreach ($_hostids as $hostid => $foo) {
				$links[$hostid][$templateid] = true;
				$hostids[$hostid] = true;
			}
		}

		$_hostids = array_keys($hostids);

		do {
			$result = DBselect(
				'SELECT ht.templateid,ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('ht.templateid', $_hostids)
			);

			$_hostids = [];

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['templateid'], $del_links)
						&& array_key_exists($row['hostid'], $del_links[$row['templateid']])) {
					continue;
				}

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
				if (array_key_exists($row['templateid'], $del_links)
						&& array_key_exists($row['hostid'], $del_links[$row['templateid']])) {
					continue;
				}

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
	private static function checkTemplateDoubleLinkage(array $links, $hostid): array {
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
	 * Check whether all templates of triggers, from which depends the triggers of linking templates, are linked to
	 * target hosts or templates.
	 *
	 * @param array  $ins_templates
	 * @param string $ins_templates[<templateid>][<hostid>][<host index>][<template index>]  Array of host templates
	 *                                                                                       IDs.
	 *
	 * @throws APIException if not linked template is found.
	 */
	private function checkTriggerDependenciesOfInsTemplates(array $ins_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS ins_templateid,td.triggerid_down,ii.hostid'.
			' FROM items i,functions f,trigger_depends td,functions ff,items ii,hosts h'.
			' WHERE f.itemid=i.itemid'.
				' AND td.triggerid_down=f.triggerid'.
				' AND ff.triggerid=td.triggerid_up'.
				' AND ii.itemid=ff.itemid'.
				' AND h.hostid=ii.hostid'.
				' AND '.dbConditionInt('i.hostid', array_keys($ins_templates)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $i1_i2_templateids) {
				$i1 = key($i1_i2_templateids);
				$i2 = key($i1_i2_templateids[$i1]);
				$templateids = $i1_i2_templateids[$i1][$i2];
				$path = '/'.($i1 + 1).'/templates';

				if (bccomp($row['hostid'], $hostid) == 1 && $this instanceof CTemplate) {
					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/templateid',
						_s('cannot be linked due to dependency of trigger "%1$s"',
							$triggers[0]['description']
						)
					));
				}

				if (!in_array($row['hostid'], $templateids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/templateid',
						self::getFormattedTriggerError(
							_('cannot be linked without template "%1$s" due to dependency of trigger "%2$s"'),
							$row['hostid'], $row['triggerid_down']
						)
					));
				}
			}
		}

		if ($this instanceof CTemplate) {
			$hostids = [];

			foreach ($ins_templates as $hostids_i1_i2_templateids) {
				$hostids += array_flip(array_keys($hostids_i1_i2_templateids));
			}

			$result = DBselect(
				'SELECT DISTINCT i.hostid AS ins_templateid,td.triggerid_up,td.triggerid_down,ii.hostid'.
				' FROM items i,functions f,trigger_depends td,functions ff,items ii'.
				' WHERE f.itemid=i.itemid'.
					' AND td.triggerid_up=f.triggerid'.
					' AND ff.triggerid=td.triggerid_down'.
					' AND ii.itemid=ff.itemid'.
					' AND '.dbConditionInt('i.hostid', array_keys($ins_templates)).
					' AND '.dbConditionInt('ii.hostid', array_keys($hostids))
			);

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['hostid'], $ins_templates[$row['ins_templateid']])) {
					$i1 = key($ins_templates[$row['ins_templateid']][$row['hostid']]);
					$i2 = key($ins_templates[$row['ins_templateid']][$row['hostid']][$i1]);
					$path = '/'.($i1 + 1).'/templates';

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => [$row['triggerid_up'], $row['trigger_down']],
						'preservekeys' => true
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/templateid',
						_s('cannot be linked due to template trigger "%1$s" dependency on trigger "%2$s"',
							$triggers[$row['trigger_down']]['description'], $triggers[$row['trigger_up']]['description']
						)
					));
				}
			}
		}
	}

	/**
	 * Check whether all templates of triggers of linking templates are linked to target hosts or templates.
	 *
	 * @param array $ins_templates[<templateid>][<hostid>][<host index>][<template index>]  Array of host templates IDs.
	 *
	 * @throws APIException if not linked template is found.
	 */
	private function checkTriggerExpressionsOfInsTemplates(array $ins_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS ins_templateid,f.triggerid,i.hostid'.
			' FROM items i,functions f,functions ff,items ii,hosts h'.
			' WHERE f.itemid=i.itemid'.
				' AND ff.triggerid=f.triggerid'.
				' AND ii.itemid=ff.itemid'.
				' AND h.hostid=ii.hostid'.
				' AND '.dbConditionInt('i.hostid', array_keys($ins_templates)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $i1_i2_templateids) {
				$i1 = key($i1_i2_templateids);
				$i2 = key($i1_i2_templateids[$i1]);
				$templateids = $i1_i2_templateids[$i1][$i2];
				$path = '/'.($i1 + 1).'/templates';

				if (bccomp($row['hostid'], $hostid) == 1 && $this instanceof CTemplate) {
					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/templateid',
						_s('cannot be linked due to expression of trigger "%1$s"',
							$triggers[0]['description']
						)
					));
				}

				if (!in_array($row['hostid'], $templateids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/'.($i2 + 1).'/templateid',
						self::getFormattedTriggerError(
							_('cannot be linked without template "%1$s" due to expression of trigger "%2$s"'),
							$row['hostid'], $row['triggerid']
						)
					));
				}
			}
		}
	}

	/**
	 * Get formatted trigger error message.
	 *
	 * @param string      $error
	 * @param string      $templateid
	 * @param string      $triggerid
	 * @param string|null $host
	 *
	 * @return string
	 */
	protected static function getFormattedTriggerError(string $error, string $templateid, string $triggerid,
			string $host = null): string {
		$templates = DB::select('hosts', [
			'output' => ['host'],
			'hostids' => $templateid
		]);

		$triggers = DB::select('triggers', [
			'output' => ['description'],
			'triggerids' => $triggerid
		]);

		if ($host === null) {
			return sprintf($error, $templates[0]['host'], $triggers[0]['description']);
		}
		else {
			return sprintf($error, $templates[0]['host'], $host, $triggers[0]['description']);
		}
	}

	/**
	 * Update table "hosts_templates".
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected function updateTemplates(array &$hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hosts_templates = [];
		$del_hosttemplateids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = ($db_hosts !== null)
				? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
				: [];

			if (array_key_exists('templates', $host)) {
				foreach ($host['templates'] as &$template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						$template['hosttemplateid'] = $db_templates[$template['templateid']]['hosttemplateid'];
						unset($db_templates[$template['templateid']]);
					}
					else {
						$ins_hosts_templates[] = [
							'hostid' => $host[$id_field_name],
							'templateid' => $template['templateid']
						];
					}
				}
				unset($template);

				foreach ($db_templates as $del_template) {
					$del_hosttemplateids[] = $del_template['hosttemplateid'];
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as $template) {
					$del_hosttemplateids[] = $db_templates[$template['template']]['hosttemplateid'];
				}
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
			if (count($targetList) == count($targetIds)) {
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

	/**
	 * Updates tags by deleting existing tags if they are not among the input tags, and adding missing ones.
	 *
	 * @param array  $host_tags
	 * @param int    $host_tags[<hostid>]
	 * @param string $host_tags[<hostid>][]['tag']
	 * @param string $host_tags[<hostid>][]['value']
	 */
	protected function updateTags(array $host_tags): void {
		if (!$host_tags) {
			return;
		}

		$insert = [];
		$db_tags = DB::select('host_tag', [
			'output' => ['hosttagid', 'hostid', 'tag', 'value'],
			'filter' => ['hostid' => array_keys($host_tags)],
			'preservekeys' => true
		]);

		$db_host_tags = [];
		foreach ($db_tags as $db_tag) {
			$db_host_tags[$db_tag['hostid']][] = $db_tag;
		}

		foreach ($host_tags as $hostid => $tags) {
			foreach (zbx_toArray($tags) as $tag) {
				if (array_key_exists($hostid, $db_host_tags)) {
					$tag += ['value' => ''];

					foreach ($db_host_tags[$hostid] as $db_tag) {
						if ($tag['tag'] === $db_tag['tag'] && $tag['value'] === $db_tag['value']) {
							unset($db_tags[$db_tag['hosttagid']]);
							$tag = null;
							break;
						}
					}
				}

				if ($tag !== null) {
					$insert[] = ['hostid' => $hostid] + $tag;
				}
			}
		}

		if ($db_tags) {
			DB::delete('host_tag', ['hosttagid' => array_keys($db_tags)]);
		}

		if ($insert) {
			DB::insert('host_tag', $insert);
		}
	}

	/**
	 * Creates user macros for hosts, templates and host prototypes.
	 *
	 * @param array  $hosts
	 * @param array  $hosts[]['templateid|hostid']
	 * @param array  $hosts[]['macros']             (optional)
	 */
	protected function createHostMacros(array $hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hostmacros = [];

		foreach ($hosts as $host) {
			if (array_key_exists('macros', $host)) {
				foreach ($host['macros'] as $macro) {
					$ins_hostmacros[] = ['hostid' => $host[$id_field_name]] + $macro;
				}
			}
		}

		if ($ins_hostmacros) {
			DB::insert('hostmacro', $ins_hostmacros);
		}
	}

	/**
	 * Adding "macros" to the each host object.
	 *
	 * @param array  $db_hosts
	 *
	 * @return array
	 */
	protected function getHostMacros(array $db_hosts): array {
		foreach ($db_hosts as &$db_host) {
			$db_host['macros'] = [];
		}
		unset($db_host);

		$options = [
			'output' => ['hostmacroid', 'hostid', 'macro', 'type', 'value', 'description'],
			'filter' => ['hostid' => array_keys($db_hosts)]
		];
		$db_macros = DBselect(DB::makeSql('hostmacro', $options));

		while ($db_macro = DBfetch($db_macros)) {
			$hostid = $db_macro['hostid'];
			unset($db_macro['hostid']);

			$db_hosts[$hostid]['macros'][$db_macro['hostmacroid']] = $db_macro;
		}

		return $db_hosts;
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

		// Populating new host macro objects for correct inheritance.
		if ($this instanceof CHostPrototype) {
			$hostmacro_defaults['description'] = DB::getDefault('hostmacro', 'description');
		}

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
					$hostmacro += array_intersect_key($db_hostmacro, array_flip(['macro', 'type']));

					if ($hostmacro['type'] != $db_hostmacro['type']) {
						if ($db_hostmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
							$hostmacro += ['value' => ''];
						}

						if ($hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
							$hostmacro += ['value' => $db_hostmacro['value']];
						}
					}

					// Populating new host macro objects for correct inheritance.
					if ($this instanceof CHostPrototype) {
						$hostmacro += array_intersect_key($db_hostmacro, array_flip(['value', 'description']));
					}

					$macros[$hostmacro['hostmacroid']] = $hostmacro['macro'];
				}

				if (array_key_exists('value', $hostmacro) && $hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
					if (!CApiInputValidator::validate(['type' => API_VAULT_SECRET], $hostmacro['value'],
							$path.'/'.($i2 + 1).'/value', $error)) {
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
	 * Updates user macros for hosts, templates and host prototypes.
	 *
	 * @param array  $hosts
	 * @param array  $hosts[]['templateid|hostid']
	 * @param array  $hosts[]['macros']             (optional)
	 * @param array  $db_hosts
	 * @param array  $db_hosts[<hostid>]['macros']  An array of host macros indexed by hostmacroid.
	 */
	protected function updateHostMacros(array $hosts, array $db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hostmacros = [];
		$upd_hostmacros = [];
		$del_hostmacroids = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			$db_host = $db_hosts[$host[$id_field_name]];

			foreach ($host['macros'] as $hostmacro) {
				if (array_key_exists('hostmacroid', $hostmacro)) {
					$db_hostmacro = $db_host['macros'][$hostmacro['hostmacroid']];
					unset($db_host['macros'][$hostmacro['hostmacroid']]);

					$upd_hostmacro = DB::getUpdatedValues('hostmacro', $hostmacro, $db_hostmacro);

					if ($upd_hostmacro) {
						$upd_hostmacros[] = [
							'values' => $upd_hostmacro,
							'where' => ['hostmacroid' => $hostmacro['hostmacroid']]
						];
					}
				}
				else {
					$ins_hostmacros[] = $hostmacro + ['hostid' => $host[$id_field_name]];
				}
			}

			$del_hostmacroids = array_merge($del_hostmacroids, array_keys($db_host['macros']));
		}

		if ($del_hostmacroids) {
			DB::delete('hostmacro', ['hostmacroid' => $del_hostmacroids]);
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		if ($ins_hostmacros) {
			DB::insert('hostmacro', $ins_hostmacros);
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
