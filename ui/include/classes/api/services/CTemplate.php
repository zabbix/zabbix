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
			'selectTemplateGroups'		=> null,
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
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where'][] = 'h.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}
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

		$upcased_index = array_search($this->tableAlias().'.name_upper', $sqlParts['select']);

		if ($upcased_index !== false) {
			unset($sqlParts['select'][$upcased_index]);
		}

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
			$result = $this->unsetExtraFields($result, ['name_upper']);
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
			'selectTags' =>					['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['tag', 'value'])],
			'selectValueMaps' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['valuemapid', 'name', 'mappings', 'uuid'])],
			'selectParentTemplates' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['templateid', 'host', 'name', 'description', 'uuid'])]
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
		$this->updateHgSets($templates);
		$this->updateTags($templates);
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['host'], ['name']], 'fields' => [
			'uuid' =>			['type' => API_UUID],
			'host' =>			['type' => API_H_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'description')],
			'vendor_name' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'vendor_name')],
			'vendor_version' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'vendor_version')],
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
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
										['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $templates, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkVendorFields($templates);

		self::addUuid($templates);

		self::checkUuidDuplicates($templates);
		$this->checkDuplicates($templates);
		$this->checkGroups($templates);
		$this->checkTemplates($templates);
	}

	/**
	 * Add the UUID to those of the given templates that don't have the 'uuid' parameter set.
	 *
	 * @param array $templates
	 */
	private static function addUuid(array &$templates): void {
		foreach ($templates as &$template) {
			if (!array_key_exists('uuid', $template)) {
				$template['uuid'] = generateUuidV4();
			}
		}
		unset($template);
	}

	/**
	 * Verify template UUIDs are not repeated.
	 *
	 * @param array      $templates
	 * @param array|null $db_templates
	 *
	 * @throws APIException
	 */
	private static function checkUuidDuplicates(array $templates, ?array $db_templates = null): void {
		$template_indexes = [];

		foreach ($templates as $i => $template) {
			if (!array_key_exists('uuid', $template)) {
				continue;
			}

			if ($db_templates === null || $template['uuid'] !== $db_templates[$template['templateid']]['uuid']) {
				$template_indexes[$template['uuid']] = $i;
			}
		}

		if (!$template_indexes) {
			return;
		}

		$duplicates = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => [
				'status' => HOST_STATUS_TEMPLATE,
				'uuid' => array_keys($template_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/'.($template_indexes[$duplicates[0]['uuid']] + 1),
					_('template with the same UUID already exists')
				)
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
		$this->updateForce($templates, $db_templates);

		return ['templateids' => array_column($templates, 'templateid')];
	}

	/**
	 * @param array      $templates
	 * @param array|null $db_templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$templates, ?array &$db_templates = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['templateid'], ['host'], ['name']], 'fields' => [
			'uuid' => 				['type' => API_UUID],
			'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'description')],
			'vendor_name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'vendor_name')],
			'vendor_version' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'vendor_version')],
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
			'output' => ['uuid', 'templateid', 'host', 'name', 'description', 'vendor_name', 'vendor_version'],
			'templateids' => array_column($templates, 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($templates)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->addAffectedObjects($templates, $db_templates);

		self::checkVendorFields($templates, $db_templates);
		self::checkUuidDuplicates($templates, $db_templates);
		$this->checkDuplicates($templates, $db_templates);
		$this->checkGroups($templates, $db_templates);
		$this->checkTemplates($templates, $db_templates);
		$this->checkTemplatesLinks($templates, $db_templates);
		$templates = $this->validateHostMacros($templates, $db_templates);
	}

	/**
	 * Check vendor fields for update or create operation.
	 *
	 * @param array      $templates
	 * @param array|null $db_templates
	 *
	 * @throws Exception
	 */
	private static function checkVendorFields(array $templates, ?array $db_templates = null): void {
		$vendor_fields = array_fill_keys(['vendor_name', 'vendor_version'], '');

		foreach ($templates as $i => $template) {
			if (!array_key_exists('vendor_name', $template) && !array_key_exists('vendor_version', $template)) {
				continue;
			}

			$_template = array_intersect_key($template, $vendor_fields);

			if ($db_templates === null) {
				$_template += $vendor_fields;
			}
			else {
				$_template += array_intersect_key($db_templates[$template['templateid']], $vendor_fields);
			}

			if (($_template['vendor_name'] === '') !== ($_template['vendor_version'] === '')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_('both vendor_name and vendor_version should be either present or empty')
				));
			}
		}
	}

	public function updateForce(array $templates, array $db_templates): void {
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
		$this->updateHgSets($templates, $db_templates);
		$this->updateTags($templates, $db_templates);
		$this->updateMacros($templates, $db_templates);
		$this->updateTemplates($templates, $db_templates);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE, $templates, $db_templates);
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
		$db_lld_rules = DB::select('items', [
			'output' => ['itemid', 'name'],
			'filter' => [
				'hostid' => $templateids,
				'flags' => ZBX_FLAG_DISCOVERY_RULE
			],
			'preservekeys' => true
		]);
		if ($db_lld_rules) {
			CDiscoveryRule::deleteForce($db_lld_rules);
		}

		// delete the items
		$db_items = DB::select('items', [
			'output' => ['itemid', 'name'],
			'filter' => [
				'hostid' => $templateids,
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
				'type' => CItem::SUPPORTED_ITEM_TYPES
			],
			'preservekeys' => true
		]);

		if ($db_items) {
			CItem::deleteForce($db_items);
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
			' WHERE conditiontype='.ZBX_CONDITION_TYPE_TEMPLATE.
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
			'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
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

		// delete web scenarios
		$db_httptests = DB::select('httptest', [
			'output' => ['httptestid', 'name'],
			'filter' => ['hostid' => $templateids],
			'preservekeys' => true
		]);

		if ($db_httptests) {
			CHttpTest::deleteForce($db_httptests);
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

		self::deleteHgSets($db_templates);
		DB::delete('hosts_groups', ['hostid' => $templateids]);

		// Finally delete the template.
		DB::delete('host_tag', ['hostid' => $templateids]);
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
	private function validateDelete(array &$templateids, ?array &$db_templates = null): void {
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
				' AND '.dbConditionId('ht.templateid', $templateids).
				' AND '.dbConditionId('htt.templateid', $templateids, true)
		);

		while ($row = DBfetch($result)) {
			$del_templates[$row['del_templateid']][$row['hostid']][] = $row['templateid'];
		}

		$del_links_clear = [];
		$options = [
			'output' => ['templateid', 'hostid'],
			'filter' => [
				'templateid' => $templateids
			]
		];
		$result = DBselect(DB::makeSql('hosts_templates', $options));

		while ($row = DBfetch($result)) {
			if (!in_array($row['hostid'], $templateids)) {
				$del_links_clear[$row['templateid']][$row['hostid']] = true;
			}
		}

		if ($del_templates) {
			$this->checkTriggerExpressionsOfDelTemplates($del_templates);
		}

		if ($del_links_clear) {
			$this->checkTriggerDependenciesOfHostTriggers($del_links_clear);
		}
	}

	/**
	 * Add given template groups, macros and templates to given templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$this->validateMassAdd($data, $templates, $db_templates);

		$this->updateForce($templates, $db_templates);

		return ['templateids' => array_column($data['templates'], 'templateid')];
	}

	private function validateMassAdd(array &$data, ?array &$templates, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'templates_link' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
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

		foreach ($data['templates'] as $i => $template) {
			if (!array_key_exists($template['templateid'], $db_templates)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/templates/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
		}

		$templates = $data['templates'];

		$this->addObjectsByData($data, $templates);
		$this->addAffectedObjects($templates, $db_templates);
		$this->addUnchangedObjects($templates, $db_templates);

		if (array_key_exists('groups', $data) && $data['groups']) {
			$this->checkGroups($templates, $db_templates, '/groups',
				array_flip(array_column($data['groups'], 'groupid'))
			);
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			$templates = $this->validateHostMacros($templates, $db_templates);
		}

		if (array_key_exists('templates_link', $data) && $data['templates_link']) {
			$this->checkTemplates($templates, $db_templates, '/templates_link',
				array_flip(array_column($data['templates_link'], 'templateid'))
			);
			$this->checkTemplatesLinks($templates, $db_templates);
		}
	}

	/**
	 * Replace template groups, macros and templates on the given templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		$this->validateMassUpdate($data, $templates, $db_templates);

		$this->updateForce($templates, $db_templates);

		return ['templateids' => array_column($data['templates'], 'templateid')];
	}

	private function validateMassUpdate(array &$data, ?array &$templates, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'templates_link' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates_clear' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
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

		foreach ($data['templates'] as $i => $template) {
			if (!array_key_exists($template['templateid'], $db_templates)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/templates/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
		}

		$templates = $data['templates'];

		$this->addObjectsByData($data, $templates);
		$this->addAffectedObjects($templates, $db_templates);

		if (array_key_exists('groups', $data)) {
			$this->checkGroups($templates, $db_templates, '/groups',
				array_flip(array_column($data['groups'], 'groupid'))
			);
			$this->checkHostsWithoutGroups($templates, $db_templates);
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			self::addHostMacroIds($templates, $db_templates);
		}

		if (array_key_exists('templates_link', $data)
				|| (array_key_exists('templates_clear', $data) && $data['templates_clear'])) {
			$path = array_key_exists('templates_link', $data) ? '/templates_link' : null;
			$template_indexes = array_key_exists('templates_link', $data)
				? array_flip(array_column($data['templates_link'], 'templateid'))
				: null;

			$path_clear = array_key_exists('templates_clear', $data) && $data['templates_clear']
				? '/templates_clear'
				: null;
			$template_clear_indexes = array_key_exists('templates_clear', $data) && $data['templates_clear']
				? array_flip(array_column($data['templates_clear'], 'templateid'))
				: null;

			$this->checkTemplates($templates, $db_templates, $path, $template_indexes, $path_clear,
				$template_clear_indexes
			);
			$this->checkTemplatesLinks($templates, $db_templates);
		}
	}

	/**
	 * Remove given template groups, macros and templates from given templates.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$this->validateMassRemove($data, $templates, $db_templates);

		$this->updateForce($templates, $db_templates);

		return ['templateids' => $data['templateids']];
	}

	private function validateMassRemove(array &$data, ?array &$templates, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
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

		foreach ($data['templateids'] as $i => $templateid) {
			if (!array_key_exists($templateid, $db_templates)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/templateids/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
		}

		$templates = [];

		foreach ($data['templateids'] as $templateid) {
			$templates[] = ['templateid' => $templateid];
		}

		$data = CArrayHelper::renameKeys($data, ['macros' => 'macro_names']);

		$this->addObjectsByData($data, $templates);
		$this->addAffectedObjects($templates, $db_templates);
		$this->addUnchangedObjects($templates, $db_templates, $data);

		if (array_key_exists('groupids', $data) && $data['groupids']) {
			$this->checkGroups($templates, $db_templates, '/groupids', array_flip($data['groupids']));
			$this->checkHostsWithoutGroups($templates, $db_templates);
		}

		if ((array_key_exists('templateids_link', $data) && $data['templateids_link'])
				|| (array_key_exists('templateids_clear', $data) && $data['templateids_clear'])) {
			$path_clear = array_key_exists('templateids_clear', $data) && $data['templateids_clear']
				? '/templateids_clear'
				: null;
			$template_clear_indexes = array_key_exists('templateids_clear', $data) && $data['templateids_clear']
				? array_flip($data['templateids_clear'])
				: null;

			$this->checkTemplates($templates, $db_templates, null, null, $path_clear, $template_clear_indexes);
			$this->checkTemplatesLinks($templates, $db_templates);
		}
	}

	private function addObjectsByData(array $data, array &$templates): void {
		self::addGroupsByData($data, $templates);
		self::addMacrosByData($data, $templates);
		$this->addTemplatesByData($data, $templates);
		self::addTemplatesClearByData($data, $templates);
	}

	private function addUnchangedObjects(array &$templates, array $db_templates, array $del_objectids = []): void {
		$this->addUnchangedGroups($templates, $db_templates, $del_objectids);
		$this->addUnchangedMacros($templates, $db_templates, $del_objectids);
		$this->addUnchangedTemplates($templates, $db_templates, $del_objectids);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedTemplateGroups($options, $result);

		$templateids = array_keys($result);

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

		if ($options['selectTags'] !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$output = ['hosttagid', 'hostid', 'tag', 'value'];
			}
			else {
				$output = array_unique(array_merge(['hosttagid', 'hostid'], $options['selectTags']));
			}

			$sql_options = [
				'output' => $output,
				'filter' => ['hostid' => $templateids]
			];
			$db_tags = DBselect(DB::makeSql('host_tag', $sql_options));

			while ($db_tag = DBfetch($db_tags)) {
				$hostid = $db_tag['hostid'];

				unset($db_tag['hosttagid'], $db_tag['hostid']);

				$result[$hostid]['tags'][] = $db_tag;
			}
		}

		return $result;
	}

	private function addRelatedTemplateGroups(array $options, array &$result): void {
		if ($options['selectTemplateGroups'] === null || $options['selectTemplateGroups'] === API_OUTPUT_COUNT) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
		$groups = API::TemplateGroup()->get([
			'output' => $options['selectTemplateGroups'],
			'groupids' => $relation_map->getRelatedIds(),
			'preservekeys' => true
		]);

		$result = $relation_map->mapMany($result, $groups, 'templategroups');
	}
}
