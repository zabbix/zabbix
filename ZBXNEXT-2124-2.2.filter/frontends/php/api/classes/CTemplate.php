<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class containing methods for operations with template.
 *
 * @package API
 */
class CTemplate extends CHostGeneral {

	protected $sortColumns = array('hostid', 'host', 'name');

	/**
	 * Overrides the parent function so that templateids will be used instead of hostids for the template API.
	 */
	public function pkOption($tableName = null) {
		if ($tableName && $tableName != $this->tableName()) {
			return parent::pkOption($tableName);
		}
		else {
			return 'templateids';
		}
	}

	/**
	 * Get Template data
	 *
	 * @param array $options
	 * @return array|boolean Template data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('templates' => 'h.hostid'),
			'from'		=> array('hosts' => 'hosts h'),
			'where'		=> array('h.status='.HOST_STATUS_TEMPLATE),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'parentTemplateids'			=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'with_items'				=> null,
			'with_triggers'				=> null,
			'with_graphs'				=> null,
			'with_httptests'			=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> '',
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'selectParentTemplates'		=> null,
			'selectItems'				=> null,
			'selectDiscoveries'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectMacros'				=> null,
			'selectScreens'				=> null,
			'selectHttpTests'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE h.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.$permission.
					')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'hg.groupid', $nodeids);
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['where']['templateid'] = dbConditionInt('h.hostid', $options['templateids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'h.hostid', $nodeids);
			}
		}

		// parentTemplateids
		if (!is_null($options['parentTemplateids'])) {
			zbx_value2array($options['parentTemplateids']);

			$sqlParts['select']['parent_templateid'] = 'ht.templateid as parent_templateid';
			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['parentTemplateids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'ht.templateid', $nodeids);
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['select']['linked_hostid'] = 'ht.hostid as linked_hostid';
			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.hostid', $options['hostids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.templateid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['ht'] = 'ht.hostid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'ht.hostid', $nodeids);
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['select']['itemid'] = 'i.itemid';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'i.itemid', $nodeids);
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['select']['triggerid'] = 'f.triggerid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'f.triggerid', $nodeids);
			}
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['select']['graphid'] = 'gi.graphid';
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'gi.graphid', $nodeids);
			}
		}

		// node check !!!!
		// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'h.hostid', $nodeids);
		}

		// with_items
		if (!is_null($options['with_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i'.
				' WHERE h.hostid=i.hostid'.
					' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
				')';
		}

		// with_triggers
		if (!is_null($options['with_triggers'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i,functions f,triggers t'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=f.itemid'.
					' AND f.triggerid=t.triggerid'.
					' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
				')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i,graphs_items gi,graphs g'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=gi.itemid'.
					' AND gi.graphid=g.graphid'.
					' AND g.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
				')';
		}

		// with_httptests
		if (!empty($options['with_httptests'])) {
			$sqlParts['where'][] = 'EXISTS (SELECT ht.httptestid FROM httptest ht WHERE ht.hostid=h.hostid)';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($template = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $template;
				else
					$result = $template['rowscount'];
			}
			else{
				$template['templateid'] = $template['hostid'];
				unset($template['hostid']);

				if (!isset($result[$template['templateid']])) {
					$result[$template['templateid']]= array();
				}

				// groupids
				if (isset($template['groupid']) && is_null($options['selectGroups'])) {
					if (!isset($result[$template['templateid']]['groups'])) {
						$result[$template['templateid']]['groups'] = array();
					}

					$result[$template['templateid']]['groups'][] = array('groupid' => $template['groupid']);
					unset($template['groupid']);
				}

				// hostids
				if (isset($template['linked_hostid']) && is_null($options['selectHosts'])) {
					if (!isset($result[$template['templateid']]['hosts']))
						$result[$template['templateid']]['hosts'] = array();

					$result[$template['templateid']]['hosts'][] = array('hostid' => $template['linked_hostid']);
					unset($template['linked_hostid']);
				}
				// parentTemplateids
				if (isset($template['parent_templateid']) && is_null($options['selectParentTemplates'])) {
					if (!isset($result[$template['templateid']]['parentTemplates']))
						$result[$template['templateid']]['parentTemplates'] = array();

					$result[$template['templateid']]['parentTemplates'][] = array('templateid' => $template['parent_templateid']);
					unset($template['parent_templateid']);
				}

				// itemids
				if (isset($template['itemid']) && is_null($options['selectItems'])) {
					if (!isset($result[$template['templateid']]['items']))
						$result[$template['templateid']]['items'] = array();

					$result[$template['templateid']]['items'][] = array('itemid' => $template['itemid']);
					unset($template['itemid']);
				}

				// triggerids
				if (isset($template['triggerid']) && is_null($options['selectTriggers'])) {
					if (!isset($result[$template['templateid']]['triggers']))
						$result[$template['templateid']]['triggers'] = array();

					$result[$template['templateid']]['triggers'][] = array('triggerid' => $template['triggerid']);
					unset($template['triggerid']);
				}

				// graphids
				if (isset($template['graphid']) && is_null($options['selectGraphs'])) {
					if (!isset($result[$template['templateid']]['graphs'])) $result[$template['templateid']]['graphs'] = array();

					$result[$template['templateid']]['graphs'][] = array('graphid' => $template['graphid']);
					unset($template['graphid']);
				}

				$result[$template['templateid']] += $template;
			}

		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Get Template ID by Template name
	 *
	 * @param array $template_data
	 * @param array $template_data['host']
	 * @param array $template_data['templateid']
	 * @return string templateid
	 */
	public function getObjects($templateData) {
		$options = array(
			'filter' => $templateData,
			'output'=>API_OUTPUT_EXTEND
		);

		if (isset($templateData['node']))
			$options['nodeids'] = getNodeIdByNodeName($templateData['node']);
		elseif (isset($templateData['nodeids']))
			$options['nodeids'] = $templateData['nodeids'];

		$result = $this->get($options);

		return $result;
	}

	public function exists($object) {
		$keyFields = array(array('templateid', 'host', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('templateid'),
			'nopermissions' => 1,
			'limit' => 1
		);
		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		elseif (isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

		return !empty($objs);
	}

	/**
	 * Add Template
	 *
	 * @param array $templates multidimensional array with templates data
	 * @param string $templates['host']
	 * @return boolean
	 */
	public function create($templates) {
		$templates = zbx_toArray($templates);
		$templateids = array();

		// CHECK IF HOSTS HAVE AT LEAST 1 GROUP {{{
		foreach ($templates as $tnum => $template) {
			if (empty($template['groups'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No groups for template "%1$s".', $template['host']));
			}
			$templates[$tnum]['groups'] = zbx_toArray($templates[$tnum]['groups']);

			foreach ($templates[$tnum]['groups'] as $gnum => $group) {
				$groupids[$group['groupid']] = $group['groupid'];
			}
		}
		// }}} CHECK IF HOSTS HAVE AT LEAST 1 GROUP


		// PERMISSIONS {{{
		$options = array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1
		);
		$updGroups = API::HostGroup()->get($options);
		foreach ($groupids as $gnum => $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}
		// }}} PERMISSIONS

		foreach ($templates as $tnum => $template) {
			// If visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}

			$templateDbFields = array(
				'host' => null
			);

			if (!check_db_fields($templateDbFields, $template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "host" is mandatory'));
			}

			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $template['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect characters used for Template name "%1$s"',
					$template['host']
				));
			}

			if (isset($template['host'])) {
				if ($this->exists(array('host' => $template['host']))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template "%1$s" already exists.', $template['host']));
				}

				if (API::Host()->exists(array('host' => $template['host']))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" already exists.', $template['host']));
				}
			}

			if (isset($template['name'])) {
				if ($this->exists(array('name' => $template['name']))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Template with the same visible name "%1$s" already exists.',
						$template['name']
					));
				}

				if (API::Host()->exists(array('name' => $template['name']))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Host with the same visible name "%1$s" already exists.',
						$template['name']
					));
				}
			}

			$templateid = DB::insert('hosts', array(array('host' => $template['host'],'name' => $template['name'], 'status' => HOST_STATUS_TEMPLATE,)));
			$templateids[] = $templateid = reset($templateid);


			foreach ($template['groups'] as $group) {
				$hostgroupid = get_dbid('hosts_groups', 'hostgroupid');
				$result = DBexecute('INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES ('.zbx_dbstr($hostgroupid).','.zbx_dbstr($templateid).','.zbx_dbstr($group['groupid']).')');
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
			}

			$template['templateid'] = $templateid;
			$options = array();
			$options['templates'] = $template;
			if (isset($template['templates']) && !is_null($template['templates']))
				$options['templates_link'] = $template['templates'];
			if (isset($template['macros']) && !is_null($template['macros']))
				$options['macros'] = $template['macros'];
			if (isset($template['hosts']) && !is_null($template['hosts']))
				$options['hosts'] = $template['hosts'];

			$result = $this->massAdd($options);
			if (!$result) self::exception(ZBX_API_ERROR_PARAMETERS);
		}

		return array('templateids' => $templateids);
	}

	/**
	 * Update Template
	 *
	 * @param array $templates multidimensional array with templates data
	 * @return boolean
	 */
	public function update($templates) {
		$templates = zbx_toArray($templates);
		$templateids = zbx_objectValues($templates, 'templateid');

		$updTemplates = $this->get(array(
			'templateids' => $templateids,
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		));

		foreach ($templates as $template) {
			if (!isset($updTemplates[$template['templateid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		$macros = array();
		foreach ($templates as $template) {
			// if visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}
			$tplTmp = $template;

			$template['templates_link'] = isset($template['templates']) ? $template['templates'] : null;

			if (isset($template['macros'])) {
				$macros[$template['templateid']] = $template['macros'];
				unset($template['macros']);
			}

			unset($template['templates']);
			unset($template['templateid']);
			unset($tplTmp['templates']);

			$template['templates'] = array($tplTmp);
			$result = $this->massUpdate($template);
			if (!$result) self::exception(ZBX_API_ERROR_PARAMETERS, _('Failed to update template'));
		}

		if ($macros) {
			API::UserMacro()->replaceMacros($macros);
		}

		return array('templateids' => $templateids);
	}

	/**
	 * Delete Template
	 *
	 * @param array $templateids
	 * @param array $templateids['templateids']
	 * @return boolean
	 */
	public function delete($templateids) {
		if (empty($templateids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$templateids = zbx_toArray($templateids);

		$options = array(
			'templateids' => $templateids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);
		$delTemplates = $this->get($options);
		foreach ($templateids as $templateid) {
			if (!isset($delTemplates[$templateid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		API::Template()->unlink($templateids, null, true);

		// delete the discovery rules first
		$delRules = API::DiscoveryRule()->get(array(
			'hostids' => $templateids,
			'nopermissions' => true,
			'preservekeys' => true
		));
		if ($delRules) {
			API::DiscoveryRule()->delete(array_keys($delRules), true);
		}

		// delete the items
		$delItems = API::Item()->get(array(
			'templateids' => $templateids,
			'output' => array('itemid'),
			'nopermissions' => true,
			'preservekeys' => true
		));
		if ($delItems) {
			API::Item()->delete(array_keys($delItems), true);
		}

		// delete screen items
		DBexecute('DELETE FROM screens_items WHERE '.dbConditionInt('resourceid', $templateids).' AND resourcetype='.SCREEN_RESOURCE_HOST_TRIGGERS);

		// delete host from maps
		if (!empty($templateids)) {
			DB::delete('sysmaps_elements', array('elementtype' => SYSMAP_ELEMENT_TYPE_HOST, 'elementid' => $templateids));
		}

		// disable actions
		// actions from conditions
		$actionids = array();
		$sql = 'SELECT DISTINCT actionid'.
			' FROM conditions'.
			' WHERE conditiontype='.CONDITION_TYPE_TEMPLATE.
			' AND '.dbConditionString('value', $templateids);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		// actions from operations
		$sql = 'SELECT DISTINCT o.actionid'.
			' FROM operations o,optemplate ot'.
			' WHERE o.operationid=ot.operationid'.
			' AND '.dbConditionInt('ot.templateid', $templateids);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if (!empty($actionids)) {
			DB::update('actions', array(
				'values' => array('status' => ACTION_STATUS_DISABLED),
				'where' => array('actionid' => $actionids)
			));
		}

		// delete action conditions
		DB::delete('conditions', array(
			'conditiontype' => CONDITION_TYPE_TEMPLATE,
			'value' => $templateids
		));

		// delete action operation commands
		$operationids = array();
		$sql = 'SELECT DISTINCT ot.operationid'.
			' FROM optemplate ot'.
			' WHERE '.dbConditionInt('ot.templateid', $templateids);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('optemplate', array(
			'templateid'=>$templateids,
		));

		// delete empty operations
		$delOperationids = array();
		$sql = 'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
			' AND NOT EXISTS(SELECT NULL FROM optemplate ot WHERE ot.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', array(
			'operationid'=>$delOperationids,
		));

		// http tests
		$delHttpTests = API::HttpTest()->get(array(
			'templateids' => $templateids,
			'output' => array('httptestid'),
			'nopermissions' => 1,
			'preservekeys' => 1
		));
		if (!empty($delHttpTests)) {
			API::HttpTest()->delete(array_keys($delHttpTests), true);
		}

		// Applications
		$delApplications = API::Application()->get(array(
			'templateids' => $templateids,
			'output' => array('applicationid'),
			'nopermissions' => 1,
			'preservekeys' => 1
		));
		if (!empty($delApplications)) {
			API::Application()->delete(array_keys($delApplications), true);
		}

		DB::delete('hosts', array('hostid' => $templateids));

		// TODO: remove info from API
		foreach ($delTemplates as $template) {
			info(_s('Deleted: Template "%1$s".', $template['name']));
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, $template['templateid'], $template['host'], 'hosts', null, null);
		}

		return array('templateids' => $templateids);
	}

	/**
	 * Additionally allows to link templates to hosts and other templates.
	 *
	 * Checks write permissions for templates.
	 *
	 * Additional supported $data parameters are:
	 * - hosts  - an array of hosts or templates to link the given templates to
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : array();
		$templateids = zbx_objectValues($templates, 'templateid');

		// check permissions
		if (!$this->isWritable($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		// link hosts to the given templates
		if (isset($data['hosts']) && !empty($data['hosts'])) {
			$hostIds = zbx_objectValues($data['hosts'], 'hostid');

			// check if any of the hosts are discovered
			$this->checkValidator($hostIds, new CHostNormalValidator(array(
				'message' => _('Cannot update templates on discovered host "%1$s".')
			)));

			$this->link($templateids, $hostIds);
		}

		$data['hosts'] = array();
		return parent::massAdd($data);
	}

	/**
	 * Mass update hosts
	 *
	 * @param _array $hosts multidimensional array with Hosts data
	 * @param array $hosts['hosts'] Array of Host objects to update
	 * @return boolean
	 */
	public function massUpdate($data) {
		$templates = zbx_toArray($data['templates']);
		$templateids = zbx_objectValues($templates, 'templateid');

		$options = array(
			'templateids' => $templateids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
		);
		$updTemplates = $this->get($options);
		foreach ($templates as $tnum => $template) {
			if (!isset($updTemplates[$template['templateid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		// CHECK IF TEMPLATES HAVE AT LEAST 1 GROUP {{{
		if (isset($data['groups']) && empty($data['groups'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No groups for template'));
		}
		// }}} CHECK IF TEMPLATES HAVE AT LEAST 1 GROUP


		// UPDATE TEMPLATES PROPERTIES {{{
		if (isset($data['name'])) {
			if (count($templates) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update visible template name'));
			}

			$curTemplate = reset($templates);

			$options = array(
				'filter' => array(
					'name' => $curTemplate['name']),
				'output' => array('templateid'),
				'editable' => 1,
				'nopermissions' => 1
			);
			$templateExists = $this->get($options);
			$templateExist = reset($templateExists);

			if ($templateExist && (bccomp($templateExist['templateid'], $curTemplate['templateid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Template with the same visible name "%1$s" already exists.',
					$curTemplate['name']
				));
			}

			// can't set the same name as existing host
			if (API::Host()->exists(array('name' => $curTemplate['name']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host with the same visible name "%1$s" already exists.',
					$curTemplate['name']
				));
			}
		}

		if (isset($data['host'])) {
			if (count($templates) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update template name'));
			}

			$curTemplate = reset($templates);

			$options = array(
				'filter' => array(
					'host' => $curTemplate['host']),
				'output' => array('templateid'),
				'editable' => 1,
				'nopermissions' => 1
			);
			$templateExists = $this->get($options);
			$templateExist = reset($templateExists);

			if ($templateExist && (bccomp($templateExist['templateid'], $curTemplate['templateid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Template with the same name "%1$s" already exists.',
					$curTemplate['host']
				));
			}

			// can't set the same name as existing host
			if (API::Host()->exists(array('host' => $curTemplate['host']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host with the same name "%1$s" already exists.',
					$curTemplate['host']
				));
			}
		}

		if (isset($data['host']) && !preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $data['host'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Incorrect characters used for template name "%1$s".',
				$data['host']
			));
		}

		$sqlSet = array();
		if (isset($data['host'])) {
			$sqlSet[] = 'host=' . zbx_dbstr($data['host']);
		}

		if (isset($data['name'])) {
			// if visible name is empty replace it with host name
			if (zbx_empty(trim($data['name'])) && isset($data['host'])) {
				$sqlSet[] = 'name=' . zbx_dbstr($data['host']);
			}
			// we cannot have empty visible name
			elseif (zbx_empty(trim($data['name'])) && !isset($data['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot have empty visible template name'));
			}
			else {
				$sqlSet[] = 'name=' . zbx_dbstr($data['name']);
			}
		}

		if (!empty($sqlSet)) {
			$sql = 'UPDATE hosts SET '.implode(', ', $sqlSet).' WHERE '.dbConditionInt('hostid', $templateids);
			$result = DBexecute($sql);
		}
		// }}} UPDATE TEMPLATES PROPERTIES


		// UPDATE HOSTGROUPS LINKAGE {{{
		if (isset($data['groups']) && !is_null($data['groups'])) {
			$data['groups'] = zbx_toArray($data['groups']);
			$templateGroups = API::HostGroup()->get(array('hostids' => $templateids));
			$templateGroupids = zbx_objectValues($templateGroups, 'groupid');
			$newGroupids = zbx_objectValues($data['groups'], 'groupid');

			$groupsToAdd = array_diff($newGroupids, $templateGroupids);

			if (!empty($groupsToAdd)) {
				$result = $this->massAdd(array(
					'templates' => $templates,
					'groups' => zbx_toObject($groupsToAdd, 'groupid')
				));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _("Can't add group"));
				}
			}

			$groupidsToDel = array_diff($templateGroupids, $newGroupids);
			if (!empty($groupidsToDel)) {
				$result = $this->massRemove(array(
					'templateids' => $templateids,
					'groupids' => $groupidsToDel
				));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _("Can't remove group"));
				}
			}
		}
		// }}} UPDATE HOSTGROUPS LINKAGE

		$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : array();
		$templateidsClear = zbx_objectValues($data['templates_clear'], 'templateid');

		if (!empty($data['templates_clear'])) {
			$result = $this->massRemove(array(
				'templateids' => $templateids,
				'templateids_clear' => $templateidsClear,
			));
		}

		// UPDATE TEMPLATE LINKAGE {{{
		// firstly need to unlink all things, to correctly check circulars

		if (isset($data['hosts']) && !is_null($data['hosts'])) {
			$templateHosts = API::Host()->get(array(
				'templateids' => $templateids,
				'templated_hosts' => 1,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
			));
			$templateHostids = zbx_objectValues($templateHosts, 'hostid');
			$newHostids = zbx_objectValues($data['hosts'], 'hostid');

			$hostsToDel = array_diff($templateHostids, $newHostids);
			$hostidsToDel = array_diff($hostsToDel, $templateidsClear);

			if (!empty($hostidsToDel)) {
				$result = $this->massRemove(array(
					'hostids' => $hostidsToDel,
					'templateids' => $templateids
				));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _("Can't unlink template"));
				}
			}
		}

		if (isset($data['templates_link']) && !is_null($data['templates_link'])) {
			$templateTemplates = API::Template()->get(array('hostids' => $templateids));
			$templateTemplateids = zbx_objectValues($templateTemplates, 'templateid');
			$newTemplateids = zbx_objectValues($data['templates_link'], 'templateid');

			$templatesToDel = array_diff($templateTemplateids, $newTemplateids);
			$templateidsToDel = array_diff($templatesToDel, $templateidsClear);
			if (!empty($templateidsToDel)) {
				$result = $this->massRemove(array(
					'templateids' => $templateids,
					'templateids_link' => $templateidsToDel
				));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _("Can't unlink template"));
				}
			}
		}

		if (isset($data['hosts']) && !is_null($data['hosts'])) {

			$hostsToAdd = array_diff($newHostids, $templateHostids);
			if (!empty($hostsToAdd)) {
				$result = $this->massAdd(array('templates' => $templates, 'hosts' => $hostsToAdd));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _("Can't link template"));
				}
			}
		}

		if (isset($data['templates_link']) && !is_null($data['templates_link'])) {
			$templatesToAdd = array_diff($newTemplateids, $templateTemplateids);
			if (!empty($templatesToAdd)) {
				$result = $this->massAdd(array('templates' => $templates, 'templates_link' => $templatesToAdd));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _("Can't link template"));
				}
			}
		}
		// }}} UPDATE TEMPLATE LINKAGE

		// macros
		if (isset($data['macros'])) {
			DB::delete('hostmacro', array('hostid' => $templateids));

			$this->massAdd(array(
				'hosts' => $templates,
				'macros' => $data['macros']
			));
		}

		return array('templateids' => $templateids);
	}

	/**
	 * Additionally allows to unlink templates from hosts and other templates.
	 *
	 * Checks write permissions for templates.
	 *
	 * Additional supported $data parameters are:
	 * - hostids  - an array of host or template IDs to unlink the given templates from
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$templateids = zbx_toArray($data['templateids']);

		// check permissions
		if (!$this->isWritable($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		if (isset($data['hostids'])) {
			// check if any of the hosts are discovered
			$this->checkValidator($data['hostids'], new CHostNormalValidator(array(
				'message' => _('Cannot update templates on discovered host "%1$s".')
			)));

			API::Template()->unlink($templateids, zbx_toArray($data['hostids']));
		}

		$data['hostids'] = array();
		return parent::massRemove($data);
	}

	/**
	 * Check if user has read permissions for templates.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'templateids' => $ids,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write permissions for templates.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'templateids' => $ids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$templateids = array_keys($result);

		// Adding Templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$templates = API::Template()->get(array(
					'output' => $options['selectTemplates'],
					'nodeids' => $options['nodeids'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($templates, 'host');
				}
				$result = $relationMap->mapMany($result, $templates, 'templates', $options['limitSelects']);
			}
			else {
				$templates = API::Template()->get(array(
					'nodeids' => $options['nodeids'],
					'parentTemplateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				));
				$templates = zbx_toHash($templates, 'templateid');
				foreach ($result as $templateid => $template) {
					if (isset($templates[$templateid]))
						$result[$templateid]['templates'] = $templates[$templateid]['rowscount'];
					else
						$result[$templateid]['templates'] = 0;
				}
			}
		}

		// Adding Hosts
		if ($options['selectHosts'] !== null) {
			if ($options['selectHosts'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$hosts = API::Host()->get(array(
					'output' => $options['selectHosts'],
					'nodeids' => $options['nodeids'],
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'host');
				}
				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = API::Host()->get(array(
					'nodeids' => $options['nodeids'],
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				));
				$hosts = zbx_toHash($hosts, 'templateid');
				foreach ($result as $templateid => $template) {
					if (isset($hosts[$templateid]))
						$result[$templateid]['hosts'] = $hosts[$templateid]['rowscount'];
					else
						$result[$templateid]['hosts'] = 0;
				}
			}
		}

		// Adding screens
		if ($options['selectScreens'] !== null) {
			if ($options['selectScreens'] != API_OUTPUT_COUNT) {
				$screens = API::TemplateScreen()->get(array(
					'output' => $this->outputExtend('screens', array('templateid'), $options['selectScreens']),
					'nodeids' => $options['nodeids'],
					'templateids' => $templateids,
					'nopermissions' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($screens, 'name');
				}

				// preservekeys is not supported by templatescreen.get, so we're building a map using array keys
				$relationMap = new CRelationMap();
				foreach ($screens as $key => $screen) {
					$relationMap->addRelation($screen['templateid'], $key);
				}

				$screens = $this->unsetExtraFields($screens, array('templateid'), $options['selectScreens']);
				$result = $relationMap->mapMany($result, $screens, 'screens', $options['limitSelects']);
			}
			else {
				$screens = API::TemplateScreen()->get(array(
					'nodeids' => $options['nodeids'],
					'templateids' => $templateids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));
				$screens = zbx_toHash($screens, 'templateid');
				foreach ($result as $templateid => $template) {
					if (isset($screens[$templateid]))
						$result[$templateid]['screens'] = $screens[$templateid]['rowscount'];
					else
						$result[$templateid]['screens'] = 0;
				}
			}
		}

		return $result;
	}
}
