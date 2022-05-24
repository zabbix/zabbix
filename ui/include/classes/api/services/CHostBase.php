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
	protected function checkTemplates(array $hosts, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

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
			'templateids' => array_keys($edit_templates)
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
		$del_links = [];
		$check_double_linkage = false;
		$del_templates = [];
		$del_links_clear = [];

		foreach ($hosts as $host) {
			if (array_key_exists('templates', $host)) {
				$db_templates = ($db_hosts !== null)
					? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
					: [];
				$templateids = array_column($host['templates'], 'templateid');
				$templates_count = count($host['templates']);
				$upd_templateids = [];

				if ($db_hosts !== null
						&& array_key_exists('nopermissions_templates', $db_hosts[$host[$id_field_name]])) {
					foreach ($db_hosts[$host[$id_field_name]]['nopermissions_templates'] as $db_template) {
						$templateids[] = $db_template['templateid'];
						$templates_count++;
						$upd_templateids[] = $db_template['templateid'];
					}
				}

				foreach ($host['templates'] as $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						$upd_templateids[] = $template['templateid'];
						unset($db_templates[$template['templateid']]);
					}
					else {
						$ins_templates[$template['templateid']][$host[$id_field_name]] = $templateids;

						if (($this instanceof CTemplate && $db_hosts !== null) || $templates_count > 1) {
							$check_double_linkage = true;
						}
					}
				}

				foreach ($db_templates as $db_template) {
					$del_links[$db_template['templateid']][$host[$id_field_name]] = true;

					if (($this instanceof CHost || $this instanceof CTemplate) && $upd_templateids) {
						$del_templates[$db_template['templateid']][$host[$id_field_name]] = $upd_templateids;
					}
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				$templateids = array_column($host['templates_clear'], 'templateid');
				$upd_templateids = [];

				foreach ($db_hosts[$host[$id_field_name]]['templates'] as $db_template) {
					if (!in_array($db_template['templateid'], $templateids)) {
						$upd_templateids[] = $db_template['templateid'];
					}
				}

				foreach ($host['templates_clear'] as $template) {
					$del_links[$template['templateid']][$host[$id_field_name]] = true;

					if (($this instanceof CHost || $this instanceof CTemplate) && $upd_templateids) {
						$del_templates[$template['templateid']][$host[$id_field_name]] = $upd_templateids;
					}
				}
			}

			if (($this instanceof CHost || $this instanceof CTemplate) && array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as $template) {
					$del_links_clear[$template['templateid']][$host[$id_field_name]] = true;
				}
			}
		}

		if ($del_templates) {
			$this->checkTriggerExpressionsOfDelTemplates($del_templates);
		}

		if ($del_links_clear) {
			$this->checkTriggerDependenciesOfHostTriggers($del_links_clear);
		}

		if ($ins_templates) {
			if ($this instanceof CTemplate && $db_hosts !== null) {
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
	 * Check whether all templates of triggers of unlinking templates are unlinked from target hosts or templates.
	 *
	 * @param array $del_templates
	 * @param array $del_templates[<templateid>][<hostid>]  Array of IDs of existing templates.
	 *
	 * @throws APIException if not linked template is found.
	 */
	protected function checkTriggerExpressionsOfDelTemplates(array $del_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS del_templateid,f.triggerid,ii.hostid'.
			' FROM items i,functions f,functions ff,items ii'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND '.dbConditionInt('i.hostid', array_keys($del_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($del_templates[$row['del_templateid']] as $hostid => $upd_templateids) {
				if (in_array($row['hostid'], $upd_templateids)) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status'],
						'hostids' => [$row['del_templateid'], $row['hostid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					$error = ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE)
						? _('Cannot unlink template "%1$s" without template "%2$s" from template "%3$s" due to expression of trigger "%4$s".')
						: _('Cannot unlink template "%1$s" without template "%2$s" from host "%3$s" due to expression of trigger "%4$s".');

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['del_templateid']]['host'],
						$objects[$row['hostid']]['host'], $objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}
	}

	/**
	 * Check whether the triggers of the target hosts or templates don't have a dependencies on the triggers of the
	 * unlinking (with cleaning) templates.
	 *
	 * @param array $del_links_clear[<templateid>][<hostid>]
	 *
	 * @throws APIException
	 */
	protected function checkTriggerDependenciesOfHostTriggers(array $del_links_clear): void {
		$del_host_templates = [];

		foreach ($del_links_clear as $templateid => $hosts) {
			foreach ($hosts as $hostid => $foo) {
				$del_host_templates[$hostid][] = $templateid;
			}
		}

		$result = DBselect(
			'SELECT DISTINCT i.hostid AS templateid,t.triggerid,ii.hostid'.
			' FROM items i,functions f,triggers t,functions ff,items ii'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=t.templateid'.
				' AND t.triggerid=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND '.dbConditionId('i.hostid', array_keys($del_links_clear)).
				' AND '.dbConditionId('ii.hostid', array_keys($del_host_templates))
		);

		$trigger_links = [];

		while ($row = DBfetch($result)) {
			if (in_array($row['templateid'], $del_host_templates[$row['hostid']])) {
				$trigger_links[$row['triggerid']][$row['hostid']] = $row['templateid'];
			}
		}

		if (!$trigger_links) {
			return;
		}

		$result = DBselect(
			'SELECT DISTINCT td.triggerid_up,td.triggerid_down,i.hostid'.
			' FROM trigger_depends td,functions f,items i'.
			' WHERE td.triggerid_down=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND '.dbConditionId('td.triggerid_up', array_keys($trigger_links)).
				' AND '.dbConditionId('td.triggerid_down', array_keys($trigger_links), true).
				' AND '.dbConditionId('i.hostid', array_keys($del_host_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($trigger_links[$row['triggerid_up']] as $hostid => $templateid) {
				if (bccomp($row['hostid'], $hostid) == 0) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status'],
						'hostids' => [$templateid, $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					$error = ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE)
						? _('Cannot unlink template "%1$s" from template "%2$s" due to dependency of trigger "%3$s".')
						: _('Cannot unlink template "%1$s" from host "%2$s" due to dependency of trigger "%3$s".');

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$templateid]['host'],
						$objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}

		if ($this instanceof CTemplate) {
			$trigger_hosts = [];

			foreach ($trigger_links as $triggerid => $hostids) {
				$trigger_hosts[$triggerid] = array_keys($hostids);
			}

			$trigger_map = [];

			while (true) {
				$result = DBselect(
					'SELECT DISTINCT t.templateid,t.triggerid,i.hostid'.
					' FROM triggers t,functions f,items i'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND '.dbConditionInt('t.templateid', array_keys($trigger_hosts))
				);

				$_trigger_hosts = [];
				$hostids = [];

				while ($row = DBfetch($result)) {
					foreach ($trigger_hosts[$row['templateid']] as $hostid) {
						if (array_key_exists($row['hostid'], $del_host_templates)
								&& in_array($hostid, $del_host_templates[$row['hostid']])) {
							continue;
						}

						$trigger_map[$row['triggerid']] = $row['templateid'];
						$_trigger_hosts[$row['triggerid']][] = $row['hostid'];
						$hostids[$row['hostid']] = true;
					}
				}

				if (!$_trigger_hosts) {
					break;
				}

				$trigger_hosts = $_trigger_hosts;

				$result = DBselect(
					'SELECT DISTINCT td.triggerid_up,td.triggerid_down,i.hostid'.
					' FROM trigger_depends td,functions f,items i'.
					' WHERE td.triggerid_down=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND '.dbConditionId('td.triggerid_up', array_keys($trigger_hosts)).
						' AND '.dbConditionId('td.triggerid_down', array_keys($trigger_hosts), true).
						' AND '.dbConditionId('i.hostid', array_keys($hostids))
				);

				while ($row = DBfetch($result)) {
					foreach ($trigger_hosts[$row['triggerid_up']] as $hostid) {
						if (bccomp($row['hostid'], $hostid) == 0) {
							$triggerid = $row['triggerid_up'];

							do {
								$triggerid = $trigger_map[$triggerid];
							} while (array_key_exists($triggerid, $trigger_map));

							$from_hostid = key($trigger_links[$triggerid]);
							$templateid = $trigger_links[$triggerid][$from_hostid];

							$objects = DB::select('hosts', [
								'output' => ['host', 'status'],
								'hostids' => [$templateid, $from_hostid, $hostid],
								'preservekeys' => true
							]);

							$triggers = DB::select('triggers', [
								'output' => ['description'],
								'triggerids' => $row['triggerid_down']
							]);

							$error = ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE)
								? _('Cannot unlink template "%1$s" from template "%2$s" due to dependency of trigger "%3$s" on template "%4$s".')
								: _('Cannot unlink template "%1$s" from template "%2$s" due to dependency of trigger "%3$s" on host "%4$s".');

							self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$templateid]['host'],
								$objects[$from_hostid]['host'], $triggers[0]['description'], $objects[$hostid]['host']
							));
						}
					}
				}
			}
		}
	}

	/**
	 * Check whether circular linkage occurs as a result of the given changes in templates links.
	 *
	 * @param array $ins_links[<templateid>][<hostid>]
	 * @param array $del_links[<templateid>][<hostid>]
	 *
	 * @throws APIException
	 */
	protected static function checkCircularLinkageNew(array $ins_links, array $del_links): void {
		$links = [];
		$_hostids = $ins_links;

		do {
			$result = DBselect(
				'SELECT ht.templateid,ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('ht.hostid', array_keys($_hostids))
			);

			$_hostids = [];

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['templateid'], $del_links)
						&& array_key_exists($row['hostid'], $del_links[$row['templateid']])) {
					continue;
				}

				if (!array_key_exists($row['templateid'], $links)) {
					$_hostids[$row['templateid']] = true;
				}

				$links[$row['templateid']][$row['hostid']] = true;
			}
		}
		while ($_hostids);

		foreach ($ins_links as $templateid => $hostids) {
			if (array_key_exists($templateid, $links)) {
				$links[$templateid] += $hostids;
			}
			else {
				$links[$templateid] = $ins_links[$templateid];
			}
		}

		foreach ($ins_links as $templateid => $hostids) {
			foreach ($hostids as $hostid => $foo) {
				if (array_key_exists($hostid, $links)){
					$links_path = [$hostid => true];

					if (self::circularLinkageExists($links, $templateid, $links[$hostid], $links_path)) {
						$template_name = '';

						$templates = DB::select('hosts', [
							'output' => ['hostid', 'host'],
							'hostids' => array_keys($links_path + [$templateid => true]),
							'preservekeys' => true
						]);

						foreach ($templates as $template) {
							$description = '"'.$template['host'].'"';

							if (bccomp($template['hostid'], $templateid) == 0) {
								$template_name = $description;
							}
							else {
								$links_path[$template['hostid']] = $description;
							}
						}

						$circular_linkage = (bccomp($templateid, $hostid) == 0)
							? $template_name.' -> '.$template_name
							: $template_name.' -> '.implode(' -> ', $links_path).' -> '.$template_name;

						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot link template "%1$s" to template "%2$s", because a circular linkage (%3$s) would occur.',
							$templates[$templateid]['host'], $templates[$hostid]['host'], $circular_linkage
						));
					}
				}
			}
		}
	}

	/**
	 * Recursively check whether given template to link forms a circular linkage.
	 *
	 * @param array  $links[<templateid>][<hostid>]
	 * @param string $templateid
	 * @param array  $hostids[<hostid>]
	 * @param array  $links_path                     Circular linkage path, collected performing the check.
	 *
	 * @return bool
	 */
	private static function circularLinkageExists(array $links, string $templateid, array $hostids,
			array &$links_path): bool {
		if (array_key_exists($templateid, $hostids)) {
			return true;
		}

		$_links_path = $links_path;

		foreach ($hostids as $hostid => $foo) {
			if (array_key_exists($hostid, $links)) {
				$links_path = $_links_path;
				$hostid_links = array_diff_key($links[$hostid], $links_path);

				if ($hostid_links) {
					$links_path[$hostid] = true;

					if (self::circularLinkageExists($links, $templateid, $hostid_links, $links_path)) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check whether double linkage occurs as a result of the given changes in templates links.
	 *
	 * @param array $ins_links[<templateid>][<hostid>]
	 * @param array $del_links[<templateid>][<hostid>]
	 *
	 * @throws APIException
	 */
	protected function checkDoubleLinkageNew(array $ins_links, array $del_links): void {
		$hostids = [];

		foreach ($ins_links as $ins_hostids) {
			$hostids += $ins_hostids;
		}

		if ($this instanceof CTemplate) {
			$_templateids = $hostids;

			do {
				$result = DBselect(
					'SELECT ht.templateid,ht.hostid'.
					' FROM hosts_templates ht'.
					' WHERE '.dbConditionId('ht.templateid', array_keys($_templateids))
				);

				$_templateids = [];

				while ($row = DBfetch($result)) {
					if (array_key_exists($row['templateid'], $del_links)
							&& array_key_exists($row['hostid'], $del_links[$row['templateid']])) {
						continue;
					}

					if (!array_key_exists($row['hostid'], $hostids)) {
						$hostids[$row['hostid']] = true;
						$_templateids[$row['hostid']] = true;
					}
				}
			}
			while ($_templateids);
		}

		$_hostids = $ins_links + $hostids;
		$links = [];
		$templateids = [];

		do {
			$result = DBselect(
				'SELECT ht.templateid,ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionId('hostid', array_keys($_hostids))
			);

			$_hostids = [];

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['templateid'], $del_links)
						&& array_key_exists($row['hostid'], $del_links[$row['templateid']])) {
					continue;
				}

				if (!array_key_exists($row['templateid'], $templateids)) {
					$templateids[$row['templateid']] = true;
					$_hostids[$row['templateid']] = true;
				}

				$links[$row['hostid']][$row['templateid']] = true;
			}
		}
		while ($_hostids);

		$_ins_links = [];

		foreach ($ins_links as $templateid => $ins_hostids) {
			foreach ($ins_hostids as $hostid => $foo) {
				$links[$hostid][$templateid] = true;
				$_ins_links[$hostid][$templateid] = true;
			}
		}

		foreach ($hostids as $hostid => $foo) {
			if (self::doubleLinkageExists($links, $hostid, $parent_templateid)) {
				$hostid = self::getDoubleLinkageHost($links, $_ins_links, $hostid, $parent_templateid, $ins_templateid);

				$objects = DB::select('hosts', [
					'output' => ['host', 'status', 'flags'],
					'hostids' => [$hostid, $ins_templateid, $parent_templateid],
					'preservekeys' => true
				]);

				if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
					$error = _('Cannot link template "%1$s" to template "%2$s", because its parent template "%3$s" would be linked twice.');
				}
				elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$error = _('Cannot link template "%1$s" to host prototype "%2$s", because its parent template "%3$s" would be linked twice.');
				}
				else {
					$error = _('Cannot link template "%1$s" to host "%2$s", because its parent template "%3$s" would be linked twice.');
				}

				self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$ins_templateid]['host'],
					$objects[$hostid]['host'], $objects[$parent_templateid]['host']
				));
			}
		}
	}

	/**
	 * Recursively check whether given host have a double linkage.
	 *
	 * @param array       $links[<hostid>][<templateid>]
	 * @param string      $hostid
	 * @param string|null $parent_templateid              ID of double linked template, retrieved performing the check.
	 * @param array       $templateids
	 *
	 * @return bool
	 */
	private static function doubleLinkageExists(array $links, string $hostid, string &$parent_templateid = null,
			array &$templateids = []): bool {
		$templateids = $links[$hostid];

		foreach ($links[$hostid] as $templateid => $foo) {
			if (array_key_exists($templateid, $links)) {
				$_templateids = [];

				if (self::doubleLinkageExists($links, $templateid, $parent_templateid, $_templateids)) {
					return true;
				}

				$double_linked_templateids = array_intersect_key($templateids, $_templateids);

				if ($double_linked_templateids) {
					$parent_templateid = key($double_linked_templateids);

					return true;
				}

				$templateids += $_templateids;
			}
		}

		return false;
	}

	/**
	 * Get ID of the object to which is requested to link a template, that results a double linkage.
	 *
	 * @param array       $links[<hostid>][<templateid]
	 * @param array       $ins_links[<hostid>][<templateid>]
	 * @param string      $hostid
	 * @param string      $parent_templateid
	 * @param string|null $ins_templateid                     ID of template, requested to be linked to the found host,
	 *                                                        retrieved performing the check.
	 *
	 * @return string
	 */
	private static function getDoubleLinkageHost(array $links, array $ins_links, string $hostid,
			string $parent_templateid, string &$ins_templateid = null): string {
		if (!array_key_exists($hostid, $ins_links)) {
			foreach ($links[$hostid] as $templateid => $foo) {
				if (array_key_exists($templateid, $links)) {
					$_hostid = self::getDoubleLinkageHost($links, $ins_links, $templateid, $parent_templateid,
						$ins_templateid
					);

					if ($ins_templateid !== null) {
						return $_hostid;
					}
				}
			}
		}
		else {
			foreach ($links[$hostid] as $templateid => $foo) {
				if (!array_key_exists($templateid, $ins_links[$hostid])) {
					continue;
				}

				if (bccomp($templateid, $parent_templateid) == 0
						|| (array_key_exists($templateid, $links)
							&& self::isDoubleLinkageTemplate($links, $templateid, $parent_templateid))) {
					$ins_templateid = $templateid;
					break;
				}
			}
		}

		return $hostid;
	}

	/**
	 * Check whether the given template is requested to be linked and results a double linkage.
	 *
	 * @param array  $links
	 * @param string $templateid
	 * @param string $parent_templateid
	 *
	 * @return bool
	 */
	private static function isDoubleLinkageTemplate(array $links, string $templateid, string $parent_templateid): bool {
		if (array_key_exists($parent_templateid, $links[$templateid])) {
			return true;
		}

		foreach ($links[$templateid] as $templateid => $foo) {
			if (array_key_exists($templateid, $links)) {
				return self::isDoubleLinkageTemplate($links, $templateid, $parent_templateid);
			}
		}

		return false;
	}

	/**
	 * Check whether all templates of triggers, from which depends the triggers of linking templates, are linked to
	 * target hosts or templates.
	 *
	 * @param array  $ins_templates
	 * @param string $ins_templates[<templateid>][<hostid>]  Array of IDs of templates to replace on target object.
	 *
	 * @throws APIException if not linked template is found.
	 */
	protected function checkTriggerDependenciesOfInsTemplates(array $ins_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS ins_templateid,td.triggerid_down,ii.hostid'.
			' FROM items i,functions f,trigger_depends td,functions ff,items ii,hosts h'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=td.triggerid_down'.
				' AND td.triggerid_up=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND ii.hostid=h.hostid'.
				' AND '.dbConditionInt('i.hostid', array_keys($ins_templates)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
				if (bccomp($row['hostid'], $hostid) == 0 && $this instanceof CTemplate) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status', 'flags'],
						'hostids' => [$row['ins_templateid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Cannot link template "%1$s" to template "%2$s" due to dependency of trigger "%3$s".');
					}
					elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$error = _('Cannot link template "%1$s" to host prototype "%2$s" due to dependency of trigger "%3$s".');
					}
					else {
						$error = _('Cannot link template "%1$s" to host "%2$s" due to dependency of trigger "%3$s".');
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['ins_templateid']]['host'],
						$objects[$hostid]['host'], $triggers[0]['description']
					));
				}

				if (!in_array($row['hostid'], $templateids)) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status', 'flags'],
						'hostids' => [$row['ins_templateid'], $row['hostid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Cannot link template "%1$s" without template "%2$s" to template "%3$s" due to dependency of trigger "%4$s".');
					}
					elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$error = _('Cannot link template "%1$s" without template "%2$s" to host prototype "%3$s" due to dependency of trigger "%4$s".');
					}
					else {
						$error = _('Cannot link template "%1$s" without template "%2$s" to host "%3$s" due to dependency of trigger "%4$s".');
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['ins_templateid']]['host'],
						$objects[$row['hostid']]['host'], $objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}

		if ($this instanceof CTemplate) {
			$hostids = [];

			foreach ($ins_templates as $hostids_templateids) {
				foreach ($hostids_templateids as $hostid => $templateids) {
					$hostids[$hostid] = true;
				}
			}

			$result = DBselect(
				'SELECT DISTINCT i.hostid AS ins_templateid,td.triggerid_down,ii.hostid'.
				' FROM items i,functions f,trigger_depends td,functions ff,items ii'.
				' WHERE i.itemid=f.itemid'.
					' AND f.triggerid=td.triggerid_up'.
					' AND td.triggerid_down=ff.triggerid'.
					' AND ff.itemid=ii.itemid'.
					' AND '.dbConditionInt('i.hostid', array_keys($ins_templates)).
					' AND '.dbConditionInt('ii.hostid', array_keys($hostids))
			);

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['hostid'], $ins_templates[$row['ins_templateid']])) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status', 'flags'],
						'hostids' => [$row['ins_templateid'], $row['hostid']],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					if ($objects[$row['hostid']]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Cannot link template "%1$s" to template "%2$s" due to dependency of trigger "%3$s".');
					}
					elseif ($objects[$row['hostid']]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$error = _('Cannot link template "%1$s" to host prototype "%2$s" due to dependency of trigger "%3$s".');
					}
					else {
						$error = _('Cannot link template "%1$s" to host "%2$s" due to dependency of trigger "%3$s".');
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['ins_templateid']]['host'],
						$objects[$row['hostid']]['host'], $triggers[0]['description']
					));
				}
			}
		}
	}

	/**
	 * Check whether all templates of triggers of linking templates are linked to target hosts or templates.
	 *
	 * @param array  $ins_templates
	 * @param string $ins_templates[<templateid>][<hostid>]  Array of IDs of templates to replace on target object.
	 *
	 * @throws APIException if not linked template is found.
	 */
	protected function checkTriggerExpressionsOfInsTemplates(array $ins_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS ins_templateid,f.triggerid,ii.hostid'.
			' FROM items i,functions f,functions ff,items ii'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND '.dbConditionInt('i.hostid', array_keys($ins_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
				if (bccomp($row['hostid'], $hostid) == 0 && $this instanceof CTemplate) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status', 'flags'],
						'hostids' => [$row['ins_templateid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Cannot link template "%1$s" to template "%2$s" due to expression of trigger "%3$s".');
					}
					elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$error = _('Cannot link template "%1$s" to host prototype "%2$s" due to expression of trigger "%3$s".');
					}
					else {
						$error = _('Cannot link template "%1$s" to host "%2$s" due to expression of trigger "%3$s".');
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $objects[$row['ins_templateid']]['host'],
						$objects[$hostid]['host'], $triggers[0]['description']
					));
				}

				if (!in_array($row['hostid'], $templateids)) {
					$objects = DB::select('hosts', [
						'output' => ['host', 'status', 'flags'],
						'hostids' => [$row['ins_templateid'], $row['hostid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Cannot link template "%1$s" without template "%2$s" to template "%3$s" due to expression of trigger "%4$s".');
					}
					elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$error = _('Cannot link template "%1$s" without template "%2$s" to host prototype "%3$s" due to expression of trigger "%4$s".');
					}
					else {
						$error = _('Cannot link template "%1$s" without template "%2$s" to host "%3$s" due to expression of trigger "%4$s".');
					}

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
	protected function updateTagsNew(array &$hosts, array $db_hosts = null): void {
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

			$del_hosttagids = array_merge($del_hosttagids, array_keys($db_tags));
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

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateIds);
		if ($templateIdDuplicates) {
			$duplicatesFound = [];
			foreach ($templateIdDuplicates as $value => $count) {
				$duplicatesFound[] = _s('template ID "%1$s" is passed %2$s times', $value, $count);
			}
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot pass duplicate template IDs for the linkage: %1$s.', implode(', ', $duplicatesFound))
			);
		}

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateIds
		]);

		if ($count != count($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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
					'output'=> ['host'],
					'templateids' => $templateid
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Trigger in template "%1$s" has dependency with trigger in template "%2$s".', $tmpTpls[0]['host'], $dbDepHost['host']));
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

			$hosttemplateids = DB::insertBatch('hosts_templates', $hostsLinkageInserts);

			foreach ($hostsLinkageInserts as &$host_linkage) {
				$host_linkage['hosttemplateid'] = array_shift($hosttemplateids);
			}
			unset($host_linkage);
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
			$cond['hostid'] = $targetids;
		}
		DB::delete('hosts_templates', $cond);

		if (!is_null($targetids)) {
			$hosts = API::Host()->get([
				'hostids' => $targetids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);
		}
		else {
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
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		$this->addAffectedTemplates($hosts, $db_hosts);
		$this->addAffectedTags($hosts, $db_hosts);
		$this->addAffectedMacros($hosts, $db_hosts);
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private function addAffectedTemplates(array $hosts, array &$db_hosts): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('templates', $host) || array_key_exists('templates_clear', $host)) {
				$hostids[] = $host[$id_field_name];
				$db_hosts[$host[$id_field_name]]['templates'] = [];
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
			if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN
					|| array_key_exists($db_template['templateid'], $permitted_templates)) {
				$db_hosts[$db_template['hostid']]['templates'][$db_template['hosttemplateid']] =
					array_diff_key($db_template, array_flip(['hostid']));
			}
			else {
				$db_hosts[$db_template['hostid']]['nopermissions_templates'][$db_template['hosttemplateid']] =
					array_diff_key($db_template, array_flip(['hostid']));
			}
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private function addAffectedTags(array $hosts, array &$db_hosts): void {
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
			'output' => ['hosttagid', 'hostid', 'tag', 'value'],
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
