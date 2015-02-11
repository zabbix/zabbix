<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	 * Get template data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
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
			'output'					=> API_OUTPUT_EXTEND,
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
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['where']['templateid'] = dbConditionInt('h.hostid', $options['templateids']);
		}

		// parentTemplateids
		if (!is_null($options['parentTemplateids'])) {
			zbx_value2array($options['parentTemplateids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['parentTemplateids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.hostid', $options['hostids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.templateid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['ht'] = 'ht.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
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

				$result[$template['templateid']] = $template;
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
	 * Get template by template name and ID.
	 *
	 * @deprecated	As of version 2.4, use get method instead.
	 *
	 * @param array  $templateData
	 * @param string $templateData['host']
	 * @param string $templateData['templateid']
	 *
	 * @return array
	 */
	public function getObjects(array $templateData) {
		$this->deprecated('template.getobjects method is deprecated.');

		return $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => $templateData
		));
	}

	/**
	 * Check if template exists.
	 *
	 * @deprecated	As of version 2.4, use get method instead.
	 *
	 * @param array	$object
	 *
	 * @return bool
	 */
	public function exists(array $object) {
		$this->deprecated('template.exists method is deprecated.');

		$template = $this->get(array(
			'output' => array('templateid'),
			'filter' => zbx_array_mintersect(array(array('templateid', 'host', 'name')), $object),
			'limit' => 1
		));

		return (bool) $template;
	}

	/**
	 * Add template.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	public function create(array $templates) {
		$templates = zbx_toArray($templates);

		$this->validateCreate($templates);

		$templateIds = array();

		foreach ($templates as $key => $template) {
			$templates[$key]['groups'] = zbx_toArray($template['groups']);
		}

		foreach ($templates as $template) {
			// if visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}

			$newTemplateIds = DB::insert('hosts', array(array(
				'host' => $template['host'],
				'name' => $template['name'],
				'description' => isset($template['description']) ? $template['description'] : null,
				'status' => HOST_STATUS_TEMPLATE
			)));

			$templateId = reset($newTemplateIds);

			$templateIds[] = $templateId;

			foreach ($template['groups'] as $group) {
				$hostGroupId = get_dbid('hosts_groups', 'hostgroupid');

				$result = DBexecute(
					'INSERT INTO hosts_groups (hostgroupid,hostid,groupid)'.
					' VALUES ('.zbx_dbstr($hostGroupId).','.zbx_dbstr($templateId).','.zbx_dbstr($group['groupid']).')'
				);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot add group.'));
				}
			}

			$template['templateid'] = $templateId;

			$result = $this->massAdd(array(
				'templates' => $template,
				'templates_link' => isset($template['templates']) ? $template['templates'] : null,
				'macros' => isset($template['macros']) ? $template['macros'] : null,
				'hosts' => isset($template['hosts']) ? $template['hosts'] : null
			));

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot create template.'));
			}
		}

		return array('templateids' => $templateIds);
	}

	/**
	 * Validate create template.
	 *
	 * @param array $templates
	 */
	protected function validateCreate(array $templates) {
		$groupIds = array();

		foreach ($templates as $template) {
			// check if hosts have at least 1 group
			if (!isset($template['groups']) || !$template['groups']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No groups for template "%1$s".', $template['host']));
			}

			$template['groups'] = zbx_toArray($template['groups']);

			foreach ($template['groups'] as $group) {
				$groupIds[$group['groupid']] = $group['groupid'];
			}
		}

		$dbHostGroups = API::HostGroup()->get(array(
			'output' => array('groupid'),
			'groupids' => $groupIds,
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($groupIds as $groupId) {
			if (!isset($dbHostGroups[$groupId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		$templateDbFields = array('host' => null);

		foreach ($templates as $template) {
			// if visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}

			if (!check_db_fields($templateDbFields, $template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Field "host" is mandatory.'));
			}

			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $template['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect characters used for template name "%1$s".',
					$template['host']
				));
			}

			if (isset($template['host'])) {
				$templateExists = API::Template()->get(array(
					'output' => array('templateid'),
					'filter' => array('host' => $template['host']),
					'nopermissions' => true,
					'limit' => 1
				));
				if ($templateExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template "%1$s" already exists.', $template['host']));
				}

				$hostExists = API::Host()->get(array(
					'output' => array('hostid'),
					'filter' => array('host' => $template['host']),
					'nopermissions' => true,
					'limit' => 1
				));
				if ($hostExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" already exists.', $template['host']));
				}
			}

			if (isset($template['name'])) {
				$templateExists = API::Template()->get(array(
					'output' => array('templateid'),
					'filter' => array('name' => $template['name']),
					'nopermissions' => true,
					'limit' => 1
				));
				if ($templateExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Template with the same visible name "%1$s" already exists.',
						$template['name']
					));
				}

				$hostExists = API::Host()->get(array(
					'output' => array('hostid'),
					'filter' => array('name' => $template['name']),
					'nopermissions' => true,
					'limit' => 1
				));
				if ($hostExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Host with the same visible name "%1$s" already exists.',
						$template['name']
					));
				}
			}
		}
	}

	/**
	 * Update template.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	public function update(array $templates) {
		$templates = zbx_toArray($templates);

		$this->validateUpdate($templates);

		$macros = array();
		foreach ($templates as &$template) {
			if (isset($template['macros'])) {
				$macros[$template['templateid']] = $template['macros'];

				unset($template['macros']);
			}
		}
		unset($template);

		if ($macros) {
			API::UserMacro()->replaceMacros($macros);
		}

		foreach ($templates as $template) {
			// if visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}

			$templateCopy = $template;

			$template['templates_link'] = isset($template['templates']) ? $template['templates'] : null;
			unset($template['templates'], $template['templateid'], $templateCopy['templates']);
			$template['templates'] = array($templateCopy);

			if (!$this->massUpdate($template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Failed to update template.'));
			}
		}

		return array('templateids' => zbx_objectValues($templates, 'templateid'));
	}

	/**
	 * Validate update template.
	 *
	 * @param array $templates
	 */
	protected function validateUpdate(array $templates) {
		$dbTemplates = $this->get(array(
			'output' => array('templateid'),
			'templateids' => zbx_objectValues($templates, 'templateid'),
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($templates as $template) {
			if (!isset($dbTemplates[$template['templateid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}
	}

	/**
	 * Delete template.
	 *
	 * @param array $templateids
	 * @param array $templateids['templateids']
	 *
	 * @return array
	 */
	public function delete(array $templateids) {
		if (empty($templateids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

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
			'output' => array('itemid'),
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

			if (!API::Host()->isWritable($hostIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

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
	 * Mass update.
	 *
	 * @param string $data['host']
	 * @param string $data['name']
	 * @param string $data['description']
	 * @param array  $data['templates']
	 * @param array  $data['templates_clear']
	 * @param array  $data['templates_link']
	 * @param array  $data['groups']
	 * @param array  $data['hosts']
	 * @param array  $data['macros']
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		$this->validateMassUpdate($data);

		$templates = zbx_toArray($data['templates']);
		$templateIds = zbx_objectValues($templates, 'templateid');

		$fieldsToUpdate = array();

		if (isset($data['host'])) {
			$fieldsToUpdate[] = 'host='.zbx_dbstr($data['host']);
		}

		if (isset($data['name'])) {
			// if visible name is empty replace it with host name
			if (zbx_empty(trim($data['name'])) && isset($data['host'])) {
				$fieldsToUpdate[] = 'name='.zbx_dbstr($data['host']);
			}
			// we cannot have empty visible name
			elseif (zbx_empty(trim($data['name'])) && !isset($data['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot have empty visible template name.'));
			}
			else {
				$fieldsToUpdate[] = 'name='.zbx_dbstr($data['name']);
			}
		}

		if (isset($data['description'])) {
			$fieldsToUpdate[] = 'description='.zbx_dbstr($data['description']);
		}

		if ($fieldsToUpdate) {
			DBexecute('UPDATE hosts SET '.implode(', ', $fieldsToUpdate).' WHERE '.dbConditionInt('hostid', $templateIds));
		}

		$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : array();
		$templateIdsClear = zbx_objectValues($data['templates_clear'], 'templateid');

		if ($data['templates_clear']) {
			$this->massRemove(array(
				'templateids' => $templateIds,
				'templateids_clear' => $templateIdsClear
			));
		}

		// update template linkage
		// firstly need to unlink all things, to correctly check circulars
		if (isset($data['hosts']) && $data['hosts'] !== null) {
			/*
			 * Get all currently linked hosts and templates (skip discovered hosts) to these templates
			 * that user has read permissions.
			 */
			$templateHosts = API::Host()->get(array(
				'output' => array('hostid'),
				'templateids' => $templateIds,
				'templated_hosts' => true,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
			));
			$templateHostIds = zbx_objectValues($templateHosts, 'hostid');
			$newHostIds = zbx_objectValues($data['hosts'], 'hostid');

			$hostsToDelete = array_diff($templateHostIds, $newHostIds);
			$hostIdsToDelete = array_diff($hostsToDelete, $templateIdsClear);
			$hostIdsToAdd = array_diff($newHostIds, $templateHostIds);

			if ($hostIdsToDelete) {
				$result = $this->massRemove(array(
					'hostids' => $hostIdsToDelete,
					'templateids' => $templateIds
				));

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template.'));
				}
			}
		}

		if (isset($data['templates_link']) && $data['templates_link'] !== null) {
			$templateTemplates = API::Template()->get(array(
				'output' => array('templateid'),
				'hostids' => $templateIds
			));
			$templateTemplateIds = zbx_objectValues($templateTemplates, 'templateid');
			$newTemplateIds = zbx_objectValues($data['templates_link'], 'templateid');

			$templatesToDelete = array_diff($templateTemplateIds, $newTemplateIds);
			$templateIdsToDelete = array_diff($templatesToDelete, $templateIdsClear);

			if ($templateIdsToDelete) {
				$result = $this->massRemove(array(
					'templateids' => $templateIds,
					'templateids_link' => $templateIdsToDelete
				));

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template.'));
				}
			}
		}

		if (isset($data['hosts']) && $data['hosts'] !== null && $hostIdsToAdd) {
			$result = $this->massAdd(array(
				'templates' => $templates,
				'hosts' => $hostIdsToAdd
			));

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template.'));
			}
		}

		if (isset($data['templates_link']) && $data['templates_link'] !== null) {
			$templatesToAdd = array_diff($newTemplateIds, $templateTemplateIds);

			if ($templatesToAdd) {
				$result = $this->massAdd(array(
					'templates' => $templates,
					'templates_link' => $templatesToAdd
				));

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template.'));
				}
			}
		}

		// macros
		if (isset($data['macros'])) {
			DB::delete('hostmacro', array('hostid' => $templateIds));

			$this->massAdd(array(
				'hosts' => $templates,
				'macros' => $data['macros']
			));
		}

		/*
		 * Update template and host group linkage. This procedure should be done the last because user can unlink
		 * him self from a group with write permissions leaving only read premissions. Thus other procedures, like
		 * host-template linking, macros update, must be done before this.
		 */
		if (isset($data['groups']) && $data['groups'] !== null && is_array($data['groups'])) {
			$updateGroups = zbx_toArray($data['groups']);

			$templateGroups = API::HostGroup()->get(array(
				'output' => array('groupid'),
				'templateids' => $templateIds
			));
			$templateGroupIds = zbx_objectValues($templateGroups, 'groupid');
			$newGroupIds = zbx_objectValues($updateGroups, 'groupid');

			$groupsToAdd = array_diff($newGroupIds, $templateGroupIds);
			if ($groupsToAdd) {
				$this->massAdd(array(
					'templates' => $templates,
					'groups' => zbx_toObject($groupsToAdd, 'groupid')
				));
			}

			$groupIdsToDelete = array_diff($templateGroupIds, $newGroupIds);
			if ($groupIdsToDelete) {
				$this->massRemove(array(
					'templateids' => $templateIds,
					'groupids' => $groupIdsToDelete
				));
			}
		}

		return array('templateids' => $templateIds);
	}

	/**
	 * Validate mass update.
	 *
	 * @param string $data['host']
	 * @param string $data['name']
	 * @param array  $data['templates']
	 * @param array  $data['groups']
	 * @param array  $data['hosts']
	 *
	 * @return array
	 */
	protected function validateMassUpdate(array $data) {
		$templates = zbx_toArray($data['templates']);

		// check template permissions
		$templateIds = zbx_objectValues($templates, 'templateid');
		if (!$this->isWritable($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if templates have at least 1 group
		if (isset($data['groups']) && !$data['groups']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No groups for template.'));
		}

		// check name
		if (isset($data['name'])) {
			if (count($templates) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update visible template name.'));
			}

			$template = reset($templates);

			$templateExists = $this->get(array(
				'output' => array('templateid'),
				'filter' => array('name' => $data['name']),
				'nopermissions' => true
			));
			$templateExist = reset($templateExists);
			if ($templateExist && bccomp($templateExist['templateid'], $template['templateid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Template with the same visible name "%1$s" already exists.',
					$data['name']
				));
			}

			// can't set the same name as existing host
			$hostExists = API::Host()->get(array(
				'output' => array('hostid'),
				'filter' => array('name' => $data['name']),
				'nopermissions' => true
			));
			if ($hostExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host with the same visible name "%1$s" already exists.',
					$data['name']
				));
			}
		}

		// check host
		if (isset($data['host'])) {
			if (count($templates) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update template name.'));
			}

			$template = reset($templates);

			$templateExists = $this->get(array(
				'output' => array('templateid'),
				'filter' => array('host' => $data['host']),
				'nopermissions' => true
			));
			$templateExist = reset($templateExists);
			if ($templateExist && bccomp($templateExist['templateid'], $template['templateid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Template with the same name "%1$s" already exists.',
					$template['host']
				));
			}

			// can't set the same name as existing host
			$hostExists = API::Host()->get(array(
				'output' => array('hostid'),
				'filter' => array('host' => $template['host']),
				'nopermissions' => true
			));
			if ($hostExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host with the same name "%1$s" already exists.',
					$template['host']
				));
			}
		}

		if (isset($data['host']) && !preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $data['host'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Incorrect characters used for template name "%1$s".',
				$data['host']
			));
		}

		// validate linking object permissions
		if (isset($data['hosts']) && $data['hosts'] !== null) {
			/*
			 * Get all currently linked hosts and templates (skip discovered hosts) to these templates
			 * that user has read permissions.
			 */
			$templateHosts = API::Host()->get(array(
				'output' => array('hostid'),
				'templateids' => $templateIds,
				'templated_hosts' => true,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
			));
			$templateHostIds = zbx_objectValues($templateHosts, 'hostid');
			$newHostIds = zbx_objectValues($data['hosts'], 'hostid');
			$templateIdsClear = isset($data['templates_clear'])
				? zbx_objectValues(zbx_toArray($data['templates_clear']), 'templateid')
				: array();

			$hostsToDelete = array_diff($templateHostIds, $newHostIds);
			$hostIdsToDelete = array_diff($hostsToDelete, $templateIdsClear);
			$hostIdsToAdd = array_diff($newHostIds, $templateHostIds);

			// Gather both host and template IDs and validate write permissions.
			$hostIds = array_merge($hostIdsToAdd, $hostIdsToDelete);

			if ($hostIds) {
				/*
				 * Get all currently linked hosts and templates (skip discovered hosts) to these templates
				 * that user has write permissions.
				 */
				$templatesHostsAllowed = API::Host()->get(array(
					'output' => array('hostid'),
					'templated_hosts' => true,
					'editable' => true,
					'preservekeys' => true,
					'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
				));

				foreach ($hostIds as $hostId) {
					if (!isset($templatesHostsAllowed[$hostId])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}
			}
		}
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
			if (!API::Host()->isWritable($data['hostids'])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

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
					'output' => $this->outputExtend($options['selectScreens'], array('templateid')),
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
