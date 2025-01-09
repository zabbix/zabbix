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


abstract class CHostBase extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';

	protected function checkTemplates(array &$hosts, array &$db_hosts = null, string $path = null,
			array $template_indexes = null, string $path_clear = null, array $template_clear_indexes = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_template_indexes = [];
		$clear_template_indexes = [];

		foreach ($hosts as $i1 => &$host) {
			if (array_key_exists('templates', $host) && array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as $i2_clear => $template_clear) {
					foreach ($host['templates'] as $i2 => $template) {
						if (bccomp($template['templateid'], $template_clear['templateid']) != 0) {
							continue;
						}

						if ($path === null) {
							$path_clear = '/'.($i1 + 1).'/templates_clear/'.($i2_clear + 1);
							$path = '/'.($i1 + 1).'/templates/'.($i2 + 1);
						}
						else {
							$path_clear .= '/'.($template_clear_indexes[$template['templateid']] + 1);
							$path .= '/'.($template_indexes[$template['templateid']] + 1);
						}

						$error = _s('cannot be specified the value of parameter "%1$s"', $path.'/templateid');

						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Invalid parameter "%1$s": %2$s.', $path_clear.'/templateid', $error)
						);
					}
				}
			}

			if (array_key_exists('templates', $host)) {
				$db_templates = $db_hosts !== null
					? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
					: [];

				foreach ($host['templates'] as $i2 => $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						if ($db_templates[$template['templateid']]['link_type'] != TEMPLATE_LINK_MANUAL) {
							unset($host['templates'][$i2]);

							$db_hosttemplateid = $db_templates[$template['templateid']]['hosttemplateid'];
							unset($db_hosts[$host[$id_field_name]]['templates'][$db_hosttemplateid]);
						}

						unset($db_templates[$template['templateid']]);
					}
					else {
						$ins_template_indexes[$template['templateid']][$i1] = $i2;
					}
				}

				if ($db_templates) {
					$templateids_clear = array_key_exists('templates_clear', $host)
						? array_column($host['templates_clear'], 'templateid')
						: [];

					foreach ($db_templates as $db_template) {
						if ($db_template['link_type'] != TEMPLATE_LINK_MANUAL
								&& !in_array($db_template['templateid'], $templateids_clear)) {
							unset($db_hosts[$host[$id_field_name]]['templates'][$db_template['hosttemplateid']]);
						}
					}
				}
			}

			if (array_key_exists('templates_clear', $host)) {
				$db_templates = array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid');

				foreach ($host['templates_clear'] as $i2 => $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						if ($db_templates[$template['templateid']]['link_type'] != TEMPLATE_LINK_MANUAL) {
							unset($host['templates_clear'][$i2]);

							$db_hosttemplateid = $db_templates[$template['templateid']]['hosttemplateid'];
							unset($db_hosts[$host[$id_field_name]]['templates'][$db_hosttemplateid]);
						}

						unset($db_templates[$template['templateid']]);
					}
					else {
						$clear_template_indexes[$template['templateid']][$i1] = $i2;
					}
				}
			}
		}
		unset($host);

		if ($ins_template_indexes) {
			$db_templates = API::Template()->get([
				'output' => [],
				'templateids' => array_keys($ins_template_indexes),
				'preservekeys' => true
			]);

			foreach ($ins_template_indexes as $templateid => $indexes) {
				if (!array_key_exists($templateid, $db_templates)) {
					if ($path === null) {
						$i1 = key($indexes);
						$i2 = $indexes[$i1];

						$path = '/'.($i1 + 1).'/templates/'.($i2 + 1);
					}
					else {
						$i = $template_indexes[$templateid];

						$path .= '/'.($i + 1);
					}

					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.', $path,
						_('object does not exist, or you have no permissions to it')
					));
				}
			}
		}

		if ($clear_template_indexes) {
			$db_templates = API::Template()->get([
				'output' => [],
				'templateids' => array_keys($clear_template_indexes),
				'preservekeys' => true
			]);

			foreach ($clear_template_indexes as $templateid => $indexes) {
				if (!array_key_exists($templateid, $db_templates)) {
					if ($path_clear === null) {
						$i1 = key($indexes);
						$i2 = $indexes[$i1];

						$path_clear = '/'.($i1 + 1).'/templates_clear/'.($i2 + 1);
					}
					else {
						$i = $template_clear_indexes[$template['templateid']];

						$path_clear .= '/'.($i + 1);
					}

					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.', $path_clear,
						_('object does not exist, or you have no permissions to it')
					));
				}
				else {
					foreach ($indexes as $i1 => $i2) {
						unset($hosts[$i1]['templates_clear'][$i2]);

						if (!$hosts[$i1]['templates_clear']) {
							unset($hosts[$i1]['templates_clear']);
						}
					}
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
		$is_template_update = $this instanceof CTemplate && $db_hosts !== null;
		$double_linkage_scope = $is_template_update ? null : [];
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

						if (!$is_template_update && $templates_count > 1) {
							$double_linkage_scope[$template['templateid']][$host[$id_field_name]] = true;
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

			if ($is_template_update || $double_linkage_scope) {
				$this->checkDoubleLinkageNew($ins_templates, $del_links, $double_linkage_scope);
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
				' AND '.dbConditionId('i.hostid', array_keys($del_templates))
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
						' AND '.dbConditionId('t.templateid', array_keys($trigger_hosts))
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
	 * Check whether double linkage occurs as a result of the given changes in template links.
	 *
	 * @param array      $ins_templates
	 * @param array      $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
	 * @param array      $del_links[<templateid>][<hostid>]
	 * @param array|null $scope[<templateid>][<hostid>]          The scope of template links to perform the double
	 *                                                           linkage check for. If null, all of $ins_templates
	 *                                                           links will be checked.
	 *
	 * @throws APIException
	 */
	protected static function checkDoubleLinkageNew(array $ins_templates, array $del_links, ?array $scope): void {
		$ins_hosts = self::getInsHosts($ins_templates, $scope);
		$scoped_ins_templates = self::getScopedInsTemplates($ins_templates, $scope, $db_templates);

		$targetids = $scoped_ins_templates + $db_templates;

		if ($scope === null) {
			$children = self::getChildren($ins_hosts + $ins_templates, $del_links, $ins_templates);

			$targetids += $ins_hosts + self::getTemplateOrTargetRelatedIds($children, $ins_hosts);
		}

		$parents = self::getParents($targetids, $del_links, $ins_hosts);

		self::checkParentsOfDbTemplatesLinkedTwice($db_templates, $parents);
		self::checkParentsOfInsTemplatesLinkedTwice($scoped_ins_templates, $parents);

		if ($scope === null) {
			self::addInsHostsParentsAndChildren($ins_hosts, $parents, $children);
			$children_parents = self::getChildrenParents($children, $parents);

			self::checkInsTemplatesLinkedTwiceOnTargetChildren($ins_hosts, $children_parents);
		}
	}

	/**
	 * Get an array indexed by targets of the given $ins_templates and their templates. If the given scope is partial,
	 * returns null.
	 *
	 * @param array      $ins_templates
	 * @param array      $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
	 * @param array|null $scope[<templateid>][<hostid>]          The scope of template links to perform the double
	 *                                                           linkage check for.
	 *
	 * @return array|null
	 */
	private static function getInsHosts(array $ins_templates, ?array $scope): ?array {
		if ($scope !== null) {
			return null;
		}

		$ins_hosts = [];

		foreach ($ins_templates as $templateid => $host_templates) {
			foreach ($host_templates as $hostid => $foo) {
				$ins_hosts[$hostid][$templateid] = [];
			}
		}

		return $ins_hosts;
	}

	/**
	 * Get an array of template links from the given $ins_templates to check for double linkage.
	 * The same target object will be referenced to a common array of template IDs to replace (to be updated later).
	 * Skip template links out of the given scope.
	 *
	 * @param array      $ins_templates
	 * @param array      $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
	 * @param array|null $scope[<templateid>][<hostid>]          The scope of template links to perform the double
	 *                                                           linkage check for. If null, all of $ins_templates
	 *                                                           links will be processed.
	 * @param array      $db_templates
	 * @param array      $db_templates[<templateid>][<hostid>]   Reference to a common array of template IDs to replace.
	 *
	 * @return array|null
	 */
	private static function getScopedInsTemplates(array $ins_templates, ?array $scope,
			array &$db_templates = null): array {
		$scoped_ins_templates = [];
		$db_templates = [];

		foreach ($ins_templates as $templateid => $host_templates) {
			if ($scope !== null && !array_key_exists($templateid, $scope)) {
				continue;
			}

			foreach ($host_templates as $hostid => &$templateids) {
				if (($scope !== null && !array_key_exists($hostid, $scope[$templateid]))
						|| (array_key_exists($templateid, $scoped_ins_templates)
							&& array_key_exists($hostid, $scoped_ins_templates[$templateid]))) {
					continue;
				}

				$scoped_ins_templates[$templateid][$hostid] = &$templateids;

				foreach ($templateids as $_templateid) {
					if (bccomp($_templateid, $templateid) == 0) {
						continue;
					}

					if (array_key_exists($_templateid, $ins_templates)
							&& array_key_exists($hostid, $ins_templates[$_templateid])) {
						$scoped_ins_templates[$_templateid][$hostid] = &$templateids;
					}
					else {
						$db_templates[$_templateid][$hostid] = &$templateids;
					}
				}
			}
			unset($templateids);
		}

		return $scoped_ins_templates;
	}

	/**
	 * Recursively get children of the given template IDs.
	 *
	 * @param array      $templateids[<templateid>]
	 * @param array      $del_links
	 * @param array|null $ins_templates
	 *
	 * @return array
	 */
	private static function getChildren(array $templateids, array $del_links, ?array $ins_templates): array {
		$processed_templateids = $templateids;
		$children = [];

		do {
			$links = DB::select('hosts_templates', [
				'output' => ['templateid', 'hostid'],
				'filter' => ['templateid' => array_keys($templateids)]
			]);

			if ($ins_templates !== null) {
				foreach (array_intersect_key($ins_templates, $templateids) as $templateid => $hostids) {
					foreach ($hostids as $hostid => $foo) {
						$links[] = ['templateid' => $templateid, 'hostid' => $hostid];
					}
				}
			}

			$templateids = [];

			foreach ($links as $link) {
				if ($ins_templates !== null) {
					if (array_key_exists($link['templateid'], $del_links)
							&& array_key_exists($link['hostid'], $del_links[$link['templateid']])) {
						continue;
					}
				}

				if (!array_key_exists($link['hostid'], $processed_templateids)) {
					$templateids[$link['hostid']] = true;
					$processed_templateids[$link['hostid']] = true;
				}

				$children[$link['templateid']][] = $link['hostid'];
			}
		} while ($templateids);

		return $children;
	}

	/**
	 * Recursively get parents of the given target IDs.
	 *
	 * @param array      $targetids[<targetid>]
	 * @param array      $del_links
	 * @param array|null $ins_hosts
	 *
	 * @return array
	 */
	private static function getParents(array $targetids, array $del_links, ?array $ins_hosts): array {
		$processed_targetids = $targetids;
		$parents = [];

		do {
			$links = DB::select('hosts_templates', [
				'output' => ['templateid', 'hostid'],
				'filter' => ['hostid' => array_keys($targetids)]
			]);

			if ($ins_hosts !== null) {
				foreach (array_intersect_key($ins_hosts, $targetids) as $hostid => $templateids) {
					foreach ($templateids as $templateid => $foo) {
						$links[] = ['templateid' => $templateid, 'hostid' => $hostid];
					}
				}
			}

			$targetids = [];

			foreach ($links as $link) {
				if ($ins_hosts !== null) {
					if (array_key_exists($link['templateid'], $del_links)
							&& array_key_exists($link['hostid'], $del_links[$link['templateid']])) {
						continue;
					}
				}

				if (!array_key_exists($link['templateid'], $processed_targetids)) {
					$targetids[$link['templateid']] = true;
					$processed_targetids[$link['templateid']] = true;
				}

				$parents[$link['hostid']][] = $link['templateid'];
			}
		} while ($targetids);

		return $parents;
	}

	/**
	 * Check whether parents of already linked templates would be linked twice to target hosts or templates through new
	 * template linkage.
	 * Populate the referenced arrays of target object template IDs with the parents of the given $db_templates.
	 *
	 * @param array $db_templates
	 * @param array $parents
	 *
	 * @throws APIException
	 */
	private static function checkParentsOfDbTemplatesLinkedTwice(array $db_templates, array $parents): void {
		$_templateids = $db_templates;
		$children = [];

		do {
			$links = array_intersect_key($parents, $_templateids);

			$_templateids = [];

			foreach ($links as $link_templateid => $link_parent_templateids) {
				$db_templateids = self::getRootTemplateIds([$link_templateid => true], $children);

				foreach ($db_templateids as $templateid => $foo) {
					foreach ($db_templates[$templateid] as $hostid => &$templateids) {
						$double_templateids = array_intersect($link_parent_templateids, $templateids);

						if ($double_templateids) {
							$double_templateid = reset($double_templateids);

							$objects = DB::select('hosts', [
								'output' => ['host', 'status', 'flags'],
								'hostids' => [$double_templateid, $hostid, $templateid],
								'preservekeys' => true
							]);

							if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
								$error = _('Cannot link template "%1$s" to template "%2$s", because it would be linked twice through template "%3$s".');
							}
							elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
								$error = _('Cannot link template "%1$s" to host prototype "%2$s", because it would be linked twice through template "%3$s".');
							}
							else {
								$error = _('Cannot link template "%1$s" to host "%2$s", because it would be linked twice through template "%3$s".');
							}

							self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
								$objects[$double_templateid]['host'], $objects[$hostid]['host'],
								$objects[$templateid]['host']
							));
						}

						$templateids = array_merge($templateids, $link_parent_templateids);
					}
					unset($templateids);
				}

				foreach ($link_parent_templateids as $link_parent_templateid) {
					if (!array_key_exists($link_parent_templateid, $children)) {
						$_templateids[$link_parent_templateid] = true;
					}

					$children[$link_parent_templateid][] = $link_templateid;
				}
			}
		} while ($_templateids);
	}

	/**
	 * Check whether parents of templates to link would be linked twice to target hosts or templates.
	 * Populate the referenced arrays of target object template IDs with the parents of the given $ins_templates.
	 *
	 * @param array      $ins_templates
	 * @param array      $ins_templates[<templateid>][<hostid>]  Referenced array of target object template IDs.
	 * @param array      $parents
	 *
	 * @throws APIException
	 */
	private static function checkParentsOfInsTemplatesLinkedTwice(array $ins_templates, array $parents): void {
		$_templateids = $ins_templates;
		$children = [];

		do {
			$links = array_intersect_key($parents, $_templateids);

			$_templateids = [];

			foreach ($links as $link_templateid => $link_parent_templateids) {
				$ins_templateids = self::getRootTemplateIds([$link_templateid => true], $children);

				foreach ($ins_templateids as $ins_templateid => $foo) {
					foreach ($ins_templates[$ins_templateid] as $hostid => &$templateids) {
						$double_templateids = array_intersect($link_parent_templateids, $templateids);

						if ($double_templateids) {
							$double_templateid = reset($double_templateids);
							$objects = DB::select('hosts', [
								'output' => ['host', 'status', 'flags'],
								'hostids' => [$ins_templateid, $hostid, $double_templateid],
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
								$objects[$hostid]['host'], $objects[$double_templateid]['host']
							));
						}
						else {
							$templateids = array_merge($templateids, $link_parent_templateids);
						}
					}
					unset($templateids);
				}

				foreach ($link_parent_templateids as $link_parent_templateid) {
					if (!array_key_exists($link_parent_templateid, $children)) {
						$_templateids[$link_parent_templateid] = true;
					}

					$children[$link_parent_templateid][] = $link_templateid;
				}
			}
		} while ($_templateids);
	}

	/**
	 * Add the parent and children relations of each template to link to the given $ins_hosts.
	 *
	 * @param array $ins_hosts
	 * @param array $parents
	 * @param array $children
	 */
	private static function addInsHostsParentsAndChildren(array &$ins_hosts, array $parents, array $children): void {
		foreach ($ins_hosts as &$template_data) {
			foreach ($template_data as $templateid => &$data) {
				$data['parents'] = self::getTemplateOrTargetRelatedIds($parents, [$templateid => true]);
				$data['children'] = self::getTemplateOrTargetRelatedIds($children, [$templateid => true]);
			}
			unset($data);
		}
		unset($template_data);
	}

	/**
	 * Get the direct parents of each given children template.
	 *
	 * @param array $children
	 * @param array $parents
	 *
	 * @return array
	 */
	private static function getChildrenParents(array $children, array $parents): array {
		$children_parents = [];

		foreach ($children as $templateid => $targetids) {
			foreach ($targetids as $targetid) {
				$children_parents[$templateid][$targetid] =
					self::getTemplateOrTargetRelatedIds($parents, [$targetid => true], $templateid);
			}
		}

		return $children_parents;
	}

	/**
	 * Check whether templates to link, its parents, or children are encountered between parents of target templates'
	 * children.
	 *
	 * @param array $ins_hosts
	 * @param array $children_templates
	 *
	 * @throws APIException
	 */
	private static function checkInsTemplatesLinkedTwiceOnTargetChildren(array $ins_hosts,
			array $children_parents): void {
		$_templateids = $ins_hosts;
		$_parents = [];

		do {
			$links = array_intersect_key($children_parents, $_templateids);

			$_templateids = [];

			foreach ($links as $link_templateid => $host_parent_templates) {
				$ins_hostids = self::getRootTemplateIds([$link_templateid => true], $_parents);

				foreach ($ins_hostids as $ins_hostid => $foo) {
					foreach ($ins_hosts[$ins_hostid] as $templateid => $data) {
						foreach ($host_parent_templates as $hostid => $parent_templateids) {
							if (array_key_exists($templateid, $parent_templateids)
									|| array_intersect_key($data['children'], $parent_templateids)) {
								$objects = DB::select('hosts', [
									'output' => ['host', 'status', 'flags'],
									'hostids' => [$templateid, $ins_hostid, $hostid],
									'preservekeys' => true
								]);

								if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
									$error = _('Cannot link template "%1$s" to template "%2$s", because it would be linked to template "%3$s" twice.');
								}
								elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
									$error = _('Cannot link template "%1$s" to template "%2$s", because it would be linked to host prototype "%3$s" twice.');
								}
								else {
									$error = _('Cannot link template "%1$s" to template "%2$s", because it would be linked to host "%3$s" twice.');
								}

								self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
									$objects[$templateid]['host'], $objects[$ins_hostid]['host'],
									$objects[$hostid]['host']
								));
							}

							$double_templateids = array_intersect_key($data['parents'], $parent_templateids);

							if ($double_templateids) {
								$double_templateid = key($double_templateids);

								$objects = DB::select('hosts', [
									'output' => ['host', 'status', 'flags'],
									'hostids' => [$templateid, $ins_hostid, $double_templateid, $hostid],
									'preservekeys' => true
								]);

								if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
									$error = _('Cannot link template "%1$s" to template "%2$s", because its parent template "%3$s" would be linked to template "%4$s" twice.');
								}
								elseif ($objects[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
									$error = _('Cannot link template "%1$s" to template "%2$s", because its parent template "%3$s" would be linked to host prototype "%4$s" twice.');
								}
								else {
									$error = _('Cannot link template "%1$s" to template "%2$s", because its parent template "%3$s" would be linked to host "%4$s" twice.');
								}

								self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
									$objects[$templateid]['host'], $objects[$ins_hostid]['host'],
									$objects[$double_templateid]['host'], $objects[$hostid]['host']
								));
							}
						}
					}
				}

				foreach ($host_parent_templates as $hostid => $foo) {
					if (!array_key_exists($hostid, $_parents)) {
						$_templateids[$hostid] = true;
						$_parents[$hostid][] = $link_templateid;
					}
				}
			}
		} while ($_templateids);
	}

	/**
	 * Get IDs of targets linked to given templates or IDs of templates linked to given targets.
	 *
	 * @param array       $links
	 * @param array       $sourceids
	 * @param string|null $ignore_relatedid
	 *
	 * @return array
	 */
	private static function getTemplateOrTargetRelatedIds(array $links, array $sourceids,
			string $ignore_relatedid = null): array {
		$processed_sourceids = $sourceids;
		$relatedids = [];

		do {
			$scoped_links = array_intersect_key($links, $sourceids);

			$sourceids = [];

			foreach ($scoped_links as $_relatedids) {
				foreach ($_relatedids as $relatedid) {
					if ($ignore_relatedid !== null && bccomp($relatedid, $ignore_relatedid) == 0) {
						continue;
					}

					if (!array_key_exists($relatedid, $processed_sourceids)) {
						$sourceids[$relatedid] = true;
						$processed_sourceids[$relatedid] = true;
					}

					$relatedids[$relatedid] = true;
				}
			}

			$ignore_relatedid = null;
		} while ($sourceids);

		return $relatedids;
	}

	/**
	 * Recursively collects the roots of the given children or parent templates.
	 *
	 * @param array $templateids
	 * @param array $template_links
	 *
	 * @return array
	 */
	private static function getRootTemplateIds(array $templateids, array $template_links): array {
		$root_templateids = $templateids;

		foreach ($templateids as $templateid => $foo) {
			if (array_key_exists($templateid, $template_links)) {
				unset($root_templateids[$templateid]);

				$root_templateids +=
					self::getRootTemplateIds(array_flip($template_links[$templateid]), $template_links);
			}
		}

		return $root_templateids;
	}

	/**
	 * Check whether all templates of triggers, from which depends the triggers of linking templates, are linked to
	 * target hosts or templates.
	 *
	 * @param array  $ins_templates
	 * @param array  $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
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
				' AND '.dbConditionId('i.hostid', array_keys($ins_templates)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
				if (bccomp($row['hostid'], $hostid) == 0 && $this instanceof CTemplate) {
					$objects = DB::select('hosts', [
						'output' => ['host'],
						'hostids' => [$row['ins_templateid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot link template "%1$s" to template "%2$s" due to dependency of trigger "%3$s".',
							$objects[$row['ins_templateid']]['host'], $objects[$hostid]['host'],
							$triggers[0]['description']
						)
					);
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
					' AND '.dbConditionId('i.hostid', array_keys($ins_templates)).
					' AND '.dbConditionId('ii.hostid', array_keys($hostids))
			);

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['hostid'], $ins_templates[$row['ins_templateid']])) {
					$objects = DB::select('hosts', [
						'output' => ['host'],
						'hostids' => [$row['ins_templateid'], $row['hostid']],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot link template "%1$s" to template "%2$s" due to dependency of trigger "%3$s".',
							$objects[$row['ins_templateid']]['host'], $objects[$row['hostid']]['host'],
							$triggers[0]['description']
						)
					);
				}
			}
		}
	}

	/**
	 * Check whether all templates of triggers of linking templates are linked to target hosts or templates.
	 *
	 * @param array  $ins_templates
	 * @param array  $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
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
				' AND '.dbConditionId('i.hostid', array_keys($ins_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
				if (bccomp($row['hostid'], $hostid) == 0 && $this instanceof CTemplate) {
					$objects = DB::select('hosts', [
						'output' => ['host'],
						'hostids' => [$row['ins_templateid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot link template "%1$s" to template "%2$s" due to expression of trigger "%3$s".',
							$objects[$row['ins_templateid']]['host'], $objects[$hostid]['host'],
							$triggers[0]['description']
						)
					);
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
	 * @param array|null $upd_hostids
	 */
	protected function updateTemplates(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hosts_templates = [];
		$del_hosttemplateids = [];

		foreach ($hosts as $i => &$host) {
			if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = ($db_hosts !== null)
				? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
				: [];
			$changed = false;

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
						$changed = true;
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
					$changed = true;
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

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host[$id_field_name];
				}
				else {
					unset($host['templates'], $db_hosts[$host[$id_field_name]]['templates']);
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
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param array|null $upd_hostids
	 */
	protected function updateTags(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_tags = [];
		$del_hosttagids = [];

		foreach ($hosts as $i => &$host) {
			if (!array_key_exists('tags', $host)) {
				continue;
			}

			$changed = false;
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
					$changed  = true;
				}
			}
			unset($tag);

			$db_tags = array_filter($db_tags, static function (array $db_tag): bool {
				return $db_tag['automatic'] == ZBX_TAG_MANUAL;
			});

			if ($db_tags) {
				$del_hosttagids = array_merge($del_hosttagids, array_keys($db_tags));
				$changed = true;
			}

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host[$id_field_name];
				}
				else {
					unset($host['tags'], $db_hosts[$host[$id_field_name]]['tags']);
				}
			}
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
	 * @param array|null $upd_hostids
	 */
	protected function updateMacros(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hostmacros = [];
		$upd_hostmacros = [];
		$del_hostmacroids = [];

		foreach ($hosts as $i => &$host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			$changed = false;
			$db_macros = ($db_hosts !== null) ? $db_hosts[$host[$id_field_name]]['macros'] : [];

			foreach ($host['macros'] as &$macro) {
				if (array_key_exists('hostmacroid', $macro) && $db_macros) {
					$upd_hostmacro = DB::getUpdatedValues('hostmacro', $macro, $db_macros[$macro['hostmacroid']]);

					if ($upd_hostmacro) {
						$upd_hostmacros[] = [
							'values' => $upd_hostmacro,
							'where' => ['hostmacroid' => $macro['hostmacroid']]
						];
						$changed = true;
					}

					unset($db_macros[$macro['hostmacroid']]);
				}
				else {
					$ins_hostmacros[] = ['hostid' => $host[$id_field_name]] + $macro;
					$changed = true;
				}
			}
			unset($macro);

			if ($db_macros) {
				$del_hostmacroids = array_merge($del_hostmacroids, array_keys($db_macros));
				$changed = true;
			}

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host[$id_field_name];
				}
				else {
					unset($host['macros'], $db_hosts[$host[$id_field_name]]['macros']);
				}
			}
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

					// Check if this is not an attempt to modify automatic host macro.
					if ($this instanceof CHost) {
						$macro_fields = array_flip(['macro', 'value', 'type', 'description']);
						$hostmacro += array_intersect_key($db_hostmacro, array_flip(['automatic']));

						if ($hostmacro['automatic'] == ZBX_USERMACRO_AUTOMATIC
								&& array_diff_assoc(array_intersect_key($hostmacro, $macro_fields), $db_hostmacro)) {
							self::exception(ZBX_API_ERROR_PERMISSIONS,
								_s('Not allowed to modify automatic user macro "%1$s".', $db_hostmacro['macro'])
							);
						}
					}

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
					if (!CApiInputValidator::validate([
								'type' => API_VAULT_SECRET,
								'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)
							], $hostmacro['value'], $path.'/'.($i2 + 1).'/value', $error)) {
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
	protected function addAffectedTemplates(array $hosts, array &$db_hosts): void {
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

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$permitted_templates = API::Template()->get([
				'output' => [],
				'hostids' => $hostids,
				'preservekeys' => true
			]);
		}

		$options = [
			'output' => ['hosttemplateid', 'hostid', 'templateid', 'link_type'],
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
	protected function addAffectedTags(array $hosts, array &$db_hosts): void {
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
			'output' => ['hosttagid', 'hostid', 'tag', 'value', 'automatic'],
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
	protected function addAffectedMacros(array $hosts, array &$db_hosts): void {
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
			'output' => ['hostmacroid', 'hostid', 'macro', 'value', 'description', 'type', 'automatic'],
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
