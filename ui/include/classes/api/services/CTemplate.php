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
 * Class containing methods for operations with template.
 */
class CTemplate extends CHostGeneral {

	protected $sortColumns = ['hostid', 'host', 'name'];

	/**
	 * Get template data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['templates' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['h.status='.HOST_STATUS_TEMPLATE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
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
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'evaltype'					=> TAG_EVAL_TYPE_AND_OR,
			'tags'						=> null,
			'filter'					=> null,
			'search'					=> '',
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
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
			'selectMacros'				=> null,
			'selectDashboards'			=> null,
			'selectHttpTests'			=> null,
			'selectTags'				=> null,
			'selectValueMaps'			=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);
		$this->validateGet($options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

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

			if ($options['groupCount']) {
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

			if ($options['groupCount']) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.hostid', $options['hostids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.templateid';

			if ($options['groupCount']) {
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

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'h',
				'host_tag', 'hostid'
			);
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
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($template = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $template;
				}
				else {
					$result = $template['rowscount'];
				}
			}
			else {
				$template['templateid'] = $template['hostid'];
				// Templates share table with hosts and host prototypes. Therefore remove template unrelated fields.
				unset($template['hostid'], $template['discover']);

				$result[$template['templateid']] = $template;
			}

		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validates the input parameters for the get() method.
	 *
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateGet(array $options) {
		// Validate input parameters.
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'selectValueMaps' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => 'valuemapid,name,mappings,uuid']
		]];
		$options_filter = array_intersect_key($options, $api_input_rules['fields']);
		if (!CApiInputValidator::validate($api_input_rules, $options_filter, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Add template.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	public function create(array $templates) {
		$this->validateCreate($templates);

		$ins_templates = [];

		foreach ($templates as $template) {
			unset($template['groups'], $template['templates'], $template['tags'], $template['macros']);

			$ins_templates[] = $template + ['status' => HOST_STATUS_TEMPLATE];
		}

		$templateids = DB::insert('hosts', $ins_templates);

		foreach ($templates as $index => &$template) {
			$template['templateid'] = $templateids[$index];
		}
		unset($template);

		$this->checkTemplatesLinks($templates);

		$this->updateGroups($templates);
		$this->updateTagsNew($templates);
		$this->updateMacros($templates);
		$this->updateTemplates($templates);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_TEMPLATE, $templates);

		return ['templateids' => $templateids];
	}

	/**
	 * @param array $templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$templates) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['host'], ['name']], 'fields' => [
			'uuid' =>			['type' => API_UUID],
			'host' =>			['type' => API_H_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'description')],
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')],
										['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $templates, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkGroups($templates);
		$this->checkDuplicates($templates);
		self::checkAndAddUuid($templates);

		$this->checkTemplates($templates);
	}

	/**
	 * Check that no duplicate UUID is being added. Add UUID to all templates, if it doesn't exist.
	 *
	 * @param array $templates_to_create
	 *
	 * @throws APIException
	 */
	private static function checkAndAddUuid(array &$templates_to_create): void {
		foreach ($templates_to_create as &$template) {
			if (!array_key_exists('uuid', $template)) {
				$template['uuid'] = generateUuidV4();
			}
		}
		unset($template);

		$db_uuid = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($templates_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * @param array $templates
	 *
	 * @return array
	 */
	public function update(array $templates): array {
		$this->validateUpdate($templates, $db_templates);

		$upd_templates =[];

		foreach ($templates as $template) {
			$upd_template = DB::getUpdatedValues('hosts', $template, $db_templates[$template['templateid']]);

			if ($upd_template) {
				$upd_templates[] = [
					'values' => $upd_template,
					'where' => ['hostid' => $template['templateid']]
				];
			}
		}

		if ($upd_templates) {
			DB::update('hosts', $upd_templates);
		}

		$this->updateGroups($templates, $db_templates);
		$this->updateTagsNew($templates, $db_templates);
		$this->updateMacros($templates, $db_templates);
		$this->updateTemplates($templates, $db_templates);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE, $templates, $db_templates);

		return ['templateids' => array_column($templates, 'templateid')];
	}

	/**
	 * @param array      $templates
	 * @param array|null $db_templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$templates, array &$db_templates = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid'], ['host'], ['name']], 'fields' => [
			'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'description')],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates_clear' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
				'hostmacroid' =>		['type' => API_ID],
				'macro' =>				['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $templates, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_templates = $this->get([
			'output' => ['templateid', 'host', 'name', 'description'],
			'templateids' => array_column($templates, 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($templates)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->addAffectedObjects($templates, $db_templates);

		$this->checkDuplicates($templates, $db_templates);
		$this->checkGroups($templates, $db_templates);
		$this->checkTemplates($templates, $db_templates);
		$this->checkTemplatesLinks($templates, $db_templates);
		$templates = $this->validateHostMacros($templates, $db_templates);
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
		$this->validateDelete($templateids, $db_templates);

		self::unlinkTemplatesObjects($templateids, null, true);

		// delete the discovery rules first
		$del_rules = API::DiscoveryRule()->get([
			'output' => [],
			'hostids' => $templateids,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($del_rules) {
			CDiscoveryRuleManager::delete(array_keys($del_rules));
		}

		// delete the items
		$del_items = API::Item()->get([
			'output' => [],
			'templateids' => $templateids,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($del_items) {
			CItemManager::delete(array_keys($del_items));
		}

		// delete host from maps
		if (!empty($templateids)) {
			DB::delete('sysmaps_elements', ['elementtype' => SYSMAP_ELEMENT_TYPE_HOST, 'elementid' => $templateids]);
		}

		// disable actions
		// actions from conditions
		$actionids = [];
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
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionids]
			]);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_TEMPLATE,
			'value' => $templateids
		]);

		// delete action operation commands
		$operationids = [];
		$sql = 'SELECT DISTINCT ot.operationid'.
			' FROM optemplate ot'.
			' WHERE '.dbConditionInt('ot.templateid', $templateids);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('optemplate', [
			'templateid'=>$templateids
		]);

		// delete empty operations
		$delOperationids = [];
		$sql = 'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
			' AND NOT EXISTS(SELECT NULL FROM optemplate ot WHERE ot.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', [
			'operationid'=>$delOperationids
		]);

		// http tests
		$delHttpTests = API::HttpTest()->get([
			'templateids' => $templateids,
			'output' => ['httptestid'],
			'nopermissions' => 1,
			'preservekeys' => true
		]);
		if (!empty($delHttpTests)) {
			API::HttpTest()->delete(array_keys($delHttpTests), true);
		}

		// Get host prototype operations from LLD overrides where this template is linked.
		$lld_override_operationids = [];

		$db_lld_override_operationids = DBselect(
			'SELECT loo.lld_override_operationid'.
			' FROM lld_override_operation loo'.
			' WHERE EXISTS('.
				'SELECT NULL'.
				' FROM lld_override_optemplate lot'.
				' WHERE lot.lld_override_operationid=loo.lld_override_operationid'.
				' AND '.dbConditionId('lot.templateid', $templateids).
			')'
		);
		while ($db_lld_override_operationid = DBfetch($db_lld_override_operationids)) {
			$lld_override_operationids[] = $db_lld_override_operationid['lld_override_operationid'];
		}

		if ($lld_override_operationids) {
			DB::delete('lld_override_optemplate', ['templateid' => $templateids]);

			// Make sure there no other operations left to safely delete the operation.
			$delete_lld_override_operationids = [];

			$db_delete_lld_override_operationids = DBselect(
				'SELECT loo.lld_override_operationid'.
				' FROM lld_override_operation loo'.
				' WHERE NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_opstatus los'.
						' WHERE los.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_opdiscover lod'.
						' WHERE lod.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_opinventory loi'.
						' WHERE loi.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_optemplate lot'.
						' WHERE lot.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND '.dbConditionId('loo.lld_override_operationid', $lld_override_operationids)
			);

			while ($db_delete_lld_override_operationid = DBfetch($db_delete_lld_override_operationids)) {
				$delete_lld_override_operationids[] = $db_delete_lld_override_operationid['lld_override_operationid'];
			}

			if ($delete_lld_override_operationids) {
				DB::delete('lld_override_operation', ['lld_override_operationid' => $delete_lld_override_operationids]);
			}
		}

		// Finally delete the template.
		DB::delete('hosts', ['hostid' => $templateids]);

		$this->addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_TEMPLATE, $db_templates);

		return ['templateids' => $templateids];
	}

	/**
	 * @param array      $templateids
	 * @param array|null $db_templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$templateids, array &$db_templates = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $templateids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_templates = $this->get([
			'output' => ['templateid', 'host', 'name'],
			'templateids' => $templateids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$del_templates = [];
		$result = DBselect(
			'SELECT ht.hostid,ht.templateid AS del_templateid,htt.templateid'.
			' FROM hosts_templates ht,hosts_templates htt'.
			' WHERE ht.hostid=htt.hostid'.
				' AND ht.templateid!=htt.templateid'.
				' AND '.dbConditionInt('ht.templateid', $templateids).
				' AND '.dbConditionInt('htt.templateid', $templateids, true)
		);

		while ($row = DBfetch($result)) {
			$del_templates[$row['del_templateid']][$row['hostid']][] = $row['templateid'];
		}

		if ($del_templates) {
			$this->checkTriggerDependenciesOfUpdTemplates($del_templates);
			$this->checkTriggerExpressionsOfDelTemplates($del_templates);
		}
	}

	/**
	 * Add given host groups, macros and templates to given templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$this->validateMassAdd($data, $db_templates);

		$templates = $this->getObjectsByData($data, $db_templates);

		$this->updateGroups($templates, $db_templates);
		$this->updateMacros($templates, $db_templates);
		$this->updateTemplates($templates, $db_templates);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE, $templates, $db_templates);

		return ['templateids' => array_column($data['templates'], 'templateid')];
	}

	/**
	 * Replace host groups, macros and templates on the given templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		$this->validateMassUpdate($data, $db_templates);

		$templates = $this->getObjectsByData($data, $db_templates);

		$this->updateGroups($templates, $db_templates);
		$this->updateMacros($templates, $db_templates);
		$this->updateTemplates($templates, $db_templates);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE, $templates, $db_templates);

		return ['templateids' => array_column($data['templates'], 'templateid')];
	}

	/**
	 * Remove given host groups, macros and templates from given templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$this->validateMassRemove($data, $db_templates);

		$templates = $this->getObjectsByData($data, $db_templates);

		$this->updateGroups($templates, $db_templates);
		$this->updateMacros($templates, $db_templates);
		$this->updateTemplates($templates, $db_templates);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE, $templates, $db_templates);

		return ['templateids' => $data['templateids']];
	}

	/**
	 * @param array      $data
	 * @param array|null $db_templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassAdd(array &$data, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'templates' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'macros' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')],
										['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'templates_link' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_templates = $this->get([
			'output' => ['templateid', 'host'],
			'templateids' => array_column($data['templates'], 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($data['templates'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if (array_key_exists('groups', $data) && $data['groups']) {
			$groupids = array_column($data['groups'], 'groupid');

			$count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $groupids,
				'editable' => true
			]);

			if ($count != count($groupids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$this->massAddAffectedObjects('groups', $groupids, $db_templates);
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			$macros = [];

			foreach ($data['macros'] as $macro) {
				$macros[CApiInputValidator::trimMacro($macro['macro'])] = $macro['macro'];
			}

			$options = [
				'output' => ['hostid', 'macro'],
				'filter' => ['hostid' => array_keys($db_templates)]
			];
			$db_macros = DBselect(DB::makeSql('hostmacro', $options));

			while ($db_macro = DBfetch($db_macros)) {
				$trimmed_db_macro = CApiInputValidator::trimMacro($db_macro['macro']);

				if (array_key_exists($trimmed_db_macro, $macros)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Macro "%1$s" already exists on "%2$s".', $macros[$trimmed_db_macro],
							$db_templates[$db_macro['hostid']]['host']
						)
					);
				}
			}

			foreach ($db_templates as &$db_template) {
				$db_template['macros'] = [];
			}
			unset($db_host);
		}

		if (array_key_exists('templates_link', $data) && $data['templates_link']) {
			$templateids = array_column($data['templates_link'], 'templateid');

			$count = API::Template()->get([
				'countOutput' => true,
				'templateids' => $templateids
			]);

			if ($count != count($templateids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$this->massAddAffectedObjects('templates', $templateids, $db_templates);

			$this->massCheckTemplatesLinks('massadd', $templateids, $db_templates);
		}
	}

	/**
	 * @param array      $data
	 * @param array|null $db_templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassUpdate(array &$data, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'templates' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'macros' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')],
										['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'templates_link' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates_clear' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_templates = $this->get([
			'output' => ['templateid', 'host'],
			'templateids' => array_column($data['templates'], 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($data['templates'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if (array_key_exists('groups', $data)) {
			$groupids = array_column($data['groups'], 'groupid');

			$count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $groupids
			]);

			if ($count != count($groupids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$this->massAddAffectedObjects('groups', [], $db_templates);

			$groupids = array_flip($groupids);
			$edit_groupids = [];

			foreach ($db_templates as $db_template) {
				$_groupids = $groupids;

				foreach ($db_template['groups'] as $db_group) {
					if (array_key_exists($db_group['groupid'], $_groupids)) {
						unset($_groupids[$db_group['groupid']]);
					} else {
						$edit_groupids[$db_group['groupid']] = true;
					}
				}

				$edit_groupids += $_groupids;
			}

			if ($edit_groupids) {
				$count = API::HostGroup()->get([
					'countOutput' => true,
					'groupids' => array_keys($edit_groupids),
					'editable' => true
				]);

				if ($count != count($edit_groupids)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}
		}

		if (array_key_exists('macros', $data)) {
			$this->massAddAffectedObjects('macros', [], $db_templates);
		}

		if (array_key_exists('templates_link', $data)
				|| (array_key_exists('templates_clear', $data) && $data['templates_clear'])) {
			if (array_key_exists('templates_link', $data) && array_key_exists('templates_clear', $data)) {
				$path_clear = '/templates_clear';
				$path = '/templates_link';

				foreach ($data['templates_clear'] as $i1_clear => $template_clear) {
					foreach ($data['templates_link'] as $i1 => $template) {
						if (bccomp($template['templateid'], $template_clear['templateid']) == 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path_clear.'/'.($i1_clear + 1).'/templateid',
								_s('cannot be specified the value of parameter "%1$s"',
									$path.'/'.($i1 + 1).'/templateid'
								)
							));
						}
					}
				}
			}

			$this->massAddAffectedObjects('templates', [], $db_templates);

			$templateids_link = array_key_exists('templates_link', $data)
				? array_column($data['templates_link'], 'templateid')
				: [];
			$templateids_clear = array_key_exists('templates_clear', $data)
				? array_column($data['templates_clear'], 'templateid')
				: [];

			$edit_templateids = array_flip($templateids_clear);

			if ($templateids_link) {
				foreach ($db_templates as $db_template) {
					$edit_templateids += array_flip(array_diff(array_column($db_template['templates'], 'templateid'),
						$templateids_link
					));
				}
			}

			if ($edit_templateids) {
				$count = $this->get([
					'countOutput' => true,
					'templateids' => array_keys($edit_templateids)
				]);

				if ($count != count($edit_templateids)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				if (array_key_exists('templates_link', $data)) {
					$this->massCheckTemplatesLinks('massupdate', $templateids_link, $db_templates);
				}
				else {
					$this->massCheckTemplatesLinks('massremove', $templateids_clear, $db_templates);
				}
			}
		}
	}

	/**
	 * @param array      $data
	 * @param array|null $db_templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassRemove(array &$data, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'templateids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'groupids' =>			['type' => API_IDS, 'flags' => API_NORMALIZE, 'uniq' => true],
			'macros' =>				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE, 'uniq' => true, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'templateids_link' =>	['type' => API_IDS, 'flags' => API_NORMALIZE, 'uniq' => true],
			'templateids_clear' =>	['type' => API_IDS, 'flags' => API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_templates = $this->get([
			'output' => ['templateid', 'host'],
			'templateids' => $data['templateids'],
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($data['templateids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if (array_key_exists('groupids', $data) && $data['groupids']) {
			$count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $data['groupids'],
				'editable' => true
			]);

			if ($count != count($data['groupids'])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			CHostGroup::checkObjectsWithoutGroups('templates', $db_templates, $data['groupids']);

			$this->massAddAffectedObjects('groups', $data['groupids'], $db_templates);
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			$this->massAddAffectedObjects('macros', $data['macros'], $db_templates);
		}

		if ((array_key_exists('templateids_link', $data) && $data['templateids_link'])
				|| (array_key_exists('templateids_clear', $data) && $data['templateids_clear'])) {
			if (array_key_exists('templateids_link', $data) && $data['templateids_link']
					&& array_key_exists('templateids_clear', $data) && $data['templateids_clear']) {
				$templateids = array_unique(array_merge($data['templateids_link'], $data['templateids_clear']));
			}
			elseif (array_key_exists('templateids_link', $data) && $data['templateids_link']) {
				$templateids = $data['templateids_link'];
			}
			else {
				$templateids = $data['templateids_clear'];
			}

			$count = $this->get([
				'countOutput' => true,
				'templateids' => $templateids
			]);

			if ($count != count($templateids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$this->massAddAffectedObjects('templates', $templateids, $db_templates);

			$this->massCheckTemplatesLinks('massremove', $templateids, $db_templates);
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$templateids = array_keys($result);

		// Adding Templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectTemplates'],
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($templates, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $templates, 'templates', $options['limitSelects']);
			}
			else {
				$templates = API::Template()->get([
					'parentTemplateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['templates'] = array_key_exists($templateid, $templates)
						? $templates[$templateid]['rowscount']
						: '0';
				}
			}
		}

		// Adding Hosts
		if ($options['selectHosts'] !== null) {
			if ($options['selectHosts'] != API_OUTPUT_COUNT) {
				$hosts = [];
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$hosts = API::Host()->get([
						'output' => $options['selectHosts'],
						'hostids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($hosts, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = API::Host()->get([
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hosts = zbx_toHash($hosts, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['hosts'] = array_key_exists($templateid, $hosts)
						? $hosts[$templateid]['rowscount']
						: '0';
				}
			}
		}

		// Adding dashboards.
		if ($options['selectDashboards'] !== null) {
			if ($options['selectDashboards'] != API_OUTPUT_COUNT) {
				$dashboards = API::TemplateDashboard()->get([
					'output' => $this->outputExtend($options['selectDashboards'], ['templateid']),
					'templateids' => $templateids
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($dashboards, 'name');
				}

				// Build relation map.
				$relationMap = new CRelationMap();
				foreach ($dashboards as $key => $dashboard) {
					$relationMap->addRelation($dashboard['templateid'], $key);
				}

				$dashboards = $this->unsetExtraFields($dashboards, ['templateid'], $options['selectDashboards']);
				$result = $relationMap->mapMany($result, $dashboards, 'dashboards', $options['limitSelects']);
			}
			else {
				$dashboards = API::TemplateDashboard()->get([
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$dashboards = zbx_toHash($dashboards, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['dashboards'] = array_key_exists($templateid, $dashboards)
						? $dashboards[$templateid]['rowscount']
						: '0';
				}
			}
		}

		return $result;
	}
}
