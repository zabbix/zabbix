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
 * Class containing methods for operations media types.
 */
class CMediatype extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'media_type';
	protected $tableAlias = 'mt';
	protected $sortColumns = ['mediatypeid'];

	public const OAUTH_OUTPUT_FIELDS = [
		'redirection_url', 'client_id', 'client_secret', 'authorization_url', 'token_url', 'tokens_status',
		'access_token', 'access_token_updated', 'access_expires_in', 'refresh_token'
	];

	public const OUTPUT_FIELDS = ['mediatypeid', 'type', 'name', 'smtp_server', 'smtp_helo', 'smtp_email',
		'exec_path', 'gsm_modem', 'username', 'passwd', 'status', 'smtp_port', 'smtp_security', 'smtp_verify_peer',
		'smtp_verify_host', 'smtp_authentication', 'maxsessions', 'maxattempts', 'attempt_interval', 'message_format',
		'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url', 'event_menu_name', 'description',
		'provider', 'parameters',

		// OAuth output fields.
		'redirection_url', 'client_id', 'client_secret', 'authorization_url', 'token_url', 'tokens_status',
		'access_token', 'access_token_updated', 'access_expires_in', 'refresh_token'
	];

	public const LIMITED_OUTPUT_FIELDS = ['mediatypeid', 'type', 'name', 'status', 'description', 'maxattempts'];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		self::validateGet($options);

		// PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && $options['editable']) {
			return $options['countOutput'] ? '0' : [];
		}

		$resource = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		$db_media_types = [];

		// Return count or grouped counts via direct SQL count.
		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$db_media_types[] = $row;
				}
				else {
					$db_media_types = $row['rowscount'];
				}
			}
			else {
				$db_media_types[$row['mediatypeid']] = $row;
			}
		}

		// Return count for post-SQL filtered result sets.
		if ($options['countOutput']) {
			return $db_media_types;
		}

		if ($db_media_types) {
			$db_media_types = $this->addRelatedObjects($options, $db_media_types);
			$db_media_types = $this->unsetExtraFields($db_media_types, ['mediatypeid', 'type'], $options['output']);

			if (!$options['preservekeys']) {
				$db_media_types = array_values($db_media_types);
			}
		}

		return $db_media_types;
	}

	/**
	 * @throws APIException
	 */
	private static function validateGet(array &$options): void {
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
			$output_fields = self::OUTPUT_FIELDS;
			$user_output_fields = CUser::OUTPUT_FIELDS;
		}
		else {
			$output_fields = self::LIMITED_OUTPUT_FIELDS;
			$user_output_fields = CUser::OWN_LIMITED_OUTPUT_FIELDS;
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filters.
			'mediatypeids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'mediaids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_merge(DB::getFilterFields('media_type', $output_fields), DB::getFilterFields('media_type_oauth', $output_fields))],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_merge(DB::getSearchFields('media_type', $output_fields), DB::getSearchFields('media_type_oauth', $output_fields))],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// Output.
			'output' =>					['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			'groupCount' =>				['type' => API_BOOLEAN, 'default' => false],
			'selectMessageTemplates' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => static fn(): bool => self::$userData['type'] == USER_TYPE_SUPER_ADMIN, 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', ['eventsource', 'recovery', 'subject', 'message']), 'default' => null],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'selectActions' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', CAction::OUTPUT_FIELDS), 'default' => null],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', $user_output_fields), 'default' => null],
			// Sort and limit.
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['mediatypeid']), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// Flags.
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		if ($options['mediaids'] !== null) {
			if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
				$sql_options = [
					'output' => ['mediaid'],
					'filter' => ['userid' => self::$userData['userid']]
				];
				$accessible_mediaids = DBfetchColumn(DBselect(DB::makeSql('media', $sql_options)), 'mediaid');

				$options['mediaids'] = array_intersect($options['mediaids'], $accessible_mediaids);
			}

			$sql_parts['from']['media'] = 'media m';
			$sql_parts['where'][] = dbConditionId('m.mediaid', $options['mediaids']);
			$sql_parts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		if ($options['userids'] !== null) {
			if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
				$options['userids'] = array_intersect($options['userids'], [self::$userData['userid']]);
			}

			$sql_parts['from']['media'] = 'media m';
			$sql_parts['where'][] = dbConditionId('m.userid', $options['userids']);
			$sql_parts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		if ($options['filter'] !== null) {
			$oauth_filter = array_intersect_key($options['filter'], array_flip(self::OAUTH_OUTPUT_FIELDS));

			if ($oauth_filter) {
				$sql_parts['left_join']['media_type_oauth'] =
					['alias' => 'mto', 'table' => 'media_type_oauth', 'using' => 'mediatypeid'];
				$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];

				$this->dbFilter('media_type_oauth mto', ['filter' => $oauth_filter] + $options, $sql_parts);
			}
		}

		if ($options['search'] !== null) {
			$oauth_search = array_intersect_key($options['search'], array_flip(self::OAUTH_OUTPUT_FIELDS));

			if ($oauth_search) {
				$sql_parts['left_join']['media_type_oauth'] =
					['alias' => 'mto', 'table' => 'media_type_oauth', 'using' => 'mediatypeid'];
				$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];

				zbx_db_search('media_type_oauth mto', ['search' => $oauth_search] + $options, $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if ($options['countOutput']) {
			return $sql_parts;
		}

		$oauth_output = array_intersect($options['output'], self::OAUTH_OUTPUT_FIELDS);

		if ($oauth_output) {
			$sql_parts['left_join']['media_type_oauth'] =
				['alias' => 'mto', 'table' => 'media_type_oauth', 'using' => 'mediatypeid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];

			foreach ($oauth_output as $oauth_field) {
				$sql_parts['select'][] = dbConditionCoalesce('mto.'.$oauth_field,
					DB::getDefault('media_type_oauth', $oauth_field), $oauth_field
				);
			}
		}

		if (in_array('parameters', $options['output'])) {
			$sql_parts = $this->addQuerySelect($this->fieldId('type'), $sql_parts);
		}

		return $sql_parts;
	}

	protected function addRelatedObjects(array $options, array $mediatypes): array {
		self::addRelatedParameters($options, $mediatypes);
		self::addRelatedMessageTemplates($options, $mediatypes);
		self::addRelatedActions($options, $mediatypes);
		self::addRelatedUsers($options, $mediatypes);

		return $mediatypes;
	}

	private static function addRelatedParameters(array $options, array &$mediatypes): void {
		if (!in_array('parameters', $options['output'])) {
			return;
		}

		foreach ($mediatypes as &$mediatype) {
			$mediatype['parameters'] = [];
		}
		unset($mediatype);

		$db_parameters = DB::select('media_type_param', [
			'output' => ['mediatypeid', 'name', 'value', 'sortorder'],
			'filter' => ['mediatypeid' => array_keys($mediatypes)]
		]);

		if (!$db_parameters) {
			return;
		}

		foreach ($db_parameters as $parameter) {
			if ($mediatypes[$parameter['mediatypeid']]['type'] == MEDIA_TYPE_EXEC) {
				$mediatypes[$parameter['mediatypeid']]['parameters'][] = [
					'sortorder' => $parameter['sortorder'],
					'value' => $parameter['value']
				];
			}
			else {
				$mediatypes[$parameter['mediatypeid']]['parameters'][] = [
					'name' => $parameter['name'],
					'value' => $parameter['value']
				];
			}
		}

		foreach ($mediatypes as &$mediatype) {
			if ($mediatype['type'] == MEDIA_TYPE_EXEC) {
				CArrayHelper::sort($mediatype['parameters'], [['field' => 'sortorder', 'order' => ZBX_SORT_UP]]);
				$mediatype['parameters'] = array_values($mediatype['parameters']);
			}
		}
		unset($mediatype);
	}

	private static function addRelatedMessageTemplates(array $options, array &$mediatypes): void {
		if (!array_key_exists('selectMessageTemplates', $options) || $options['selectMessageTemplates'] === null) {
			return;
		}

		foreach ($mediatypes as &$mediatype) {
			$mediatype['message_templates'] = [];
		}
		unset($mediatype);

		$sql_options = [
			'output' => array_merge(['mediatype_messageid', 'mediatypeid'], $options['selectMessageTemplates']),
			'filter' => ['mediatypeid' => array_keys($mediatypes)]
		];
		$resource = DBselect(DB::makeSql('media_type_message', $sql_options));

		while ($row = DBfetch($resource)) {
			$mediatypes[$row['mediatypeid']]['message_templates'][] =
				array_diff_key($row, array_flip(['mediatype_messageid', 'mediatypeid']));
		}
	}

	private static function addRelatedActions(array $options, array &$mediatypes): void {
		if ($options['selectActions'] === null) {
			return;
		}

		foreach ($mediatypes as &$mediatype) {
			$mediatype['actions'] = [];
		}
		unset($mediatype);

		$resource = DBselect(
			'SELECT DISTINCT om.mediatypeid,o.actionid'.
			' FROM opmessage om'.
			' JOIN operations o ON om.operationid=o.operationid'.
			' WHERE '.dbConditionId('om.mediatypeid', array_keys($mediatypes)).
				' OR om.mediatypeid IS NULL'
		);

		$action_mediatypeids = [];

		while ($row = DBfetch($resource)) {
			if ($row['mediatypeid'] == 0) {
				foreach ($mediatypes as $mediatype) {
					$action_mediatypeids[$row['actionid']][$mediatype['mediatypeid']] = true;
				}
			}
			else {
				$action_mediatypeids[$row['actionid']][$row['mediatypeid']] = true;
			}
		}

		if (!$action_mediatypeids) {
			return;
		}

		$db_actions = API::Action()->get([
			'output' => $options['selectActions'],
			'actionids' => array_keys($action_mediatypeids),
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		foreach ($db_actions as $actionid => $action) {
			foreach ($action_mediatypeids[$actionid] as $mediatypeid => $foo) {
				$mediatypes[$mediatypeid]['actions'][] = $action;
			}
		}
	}

	private static function addRelatedUsers(array $options, array &$mediatypes): void {
		if ($options['selectUsers'] === null) {
			return;
		}

		foreach ($mediatypes as &$mediatype) {
			$mediatype['users'] = [];
		}
		unset($mediatype);

		$user_condition = self::$userData['type'] != USER_TYPE_SUPER_ADMIN
			? ['userid' => self::$userData['userid']]
			: [];
		$sql_options = [
			'output' => ['mediatypeid', 'userid'],
			'filter' => ['mediatypeid' => array_keys($mediatypes)] + $user_condition
		];
		$resource = DBselect(DB::makeSql('media', $sql_options));

		$userids = [];
		$mediatypeids_userids = [];

		while ($row = DBfetch($resource)) {
			$userids[$row['userid']] = true;
			$mediatypeids_userids[$row['mediatypeid']][] = $row['userid'];
		}

		if (!$userids) {
			return;
		}

		$db_users = API::User()->get([
			'output' => $options['selectUsers'],
			'userids' => array_keys($userids),
			'preservekeys' => true
		]);

		foreach ($db_users as $userid => $user) {
			foreach ($mediatypeids_userids as $mediatypeid => $userids) {
				if (in_array($userid, $userids)) {
					$mediatypes[$mediatypeid]['users'][] = $user;
				}
			}
		}
	}

	/**
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function create(array $mediatypes): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'mediatype', __FUNCTION__)
			);
		}

		self::validateCreate($mediatypes);

		$mediatypeids = DB::insert('media_type', $mediatypes);

		foreach ($mediatypes as $i => &$mediatype) {
			$mediatype['mediatypeid'] = $mediatypeids[$i];
		}
		unset($mediatype);

		self::updateOauth($mediatypes);
		self::updateParameters($mediatypes);
		self::updateMessageTemplates($mediatypes);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MEDIA_TYPE, $mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$mediatypes): void {
		$api_input_rules = self::getValidationRules();

		if (!CApiInputValidator::validate($api_input_rules, $mediatypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($mediatypes);
		self::validateByType(array_keys($api_input_rules['fields']), $mediatypes);
	}

	/**
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function update(array $mediatypes): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'mediatype', __FUNCTION__)
			);
		}

		$this->validateUpdate($mediatypes, $db_mediatypes);

		self::addFieldDefaultsByType($mediatypes, $db_mediatypes);

		$upd_mediatypes = [];

		foreach ($mediatypes as $mediatype) {
			$upd_mediatype = DB::getUpdatedValues('media_type', $mediatype, $db_mediatypes[$mediatype['mediatypeid']]);

			if ($upd_mediatype) {
				$upd_mediatypes[] = [
					'values' => $upd_mediatype,
					'where' => ['mediatypeid' => $mediatype['mediatypeid']]
				];
			}
		}

		if ($upd_mediatypes) {
			DB::update('media_type', $upd_mediatypes);
		}

		self::updateOauth($mediatypes, $db_mediatypes);
		self::updateParameters($mediatypes, $db_mediatypes);
		self::updateMessageTemplates($mediatypes, $db_mediatypes);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MEDIA_TYPE, $mediatypes, $db_mediatypes);

		return ['mediatypeids' => array_column($mediatypes, 'mediatypeid')];
	}

	/**
	 * @param array      $mediatypes
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$mediatypes, ?array &$db_mediatypes): void {
		$api_input_rules = self::getValidationRules(true);

		if (!CApiInputValidator::validate($api_input_rules, $mediatypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mediatypes = $this->get([
			'output' => array_diff(self::OUTPUT_FIELDS, ['parameters']),
			'mediatypeids' => array_column($mediatypes, 'mediatypeid'),
			'preservekeys' => true
		]);

		if (count($db_mediatypes) != count($mediatypes)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($mediatypes, $db_mediatypes);

		$mediatypes = $this->extendObjectsByKey($mediatypes, $db_mediatypes, 'mediatypeid', ['type']);

		self::validateByType(array_keys($api_input_rules['fields']), $mediatypes, $db_mediatypes);

		self::addAffectedObjects($mediatypes, $db_mediatypes);
	}

	private static function getValidationRules(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$specific_fields = $is_update
			? [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
			]
			: [];

		return ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['name']], 'fields' => $specific_fields + [
			'type' =>					['type' => API_INT32, 'flags' => $api_required, 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK])],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'name')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED])],
			'maxattempts' =>			['type' => API_INT32, 'in' => '1:100'],
			'attempt_interval' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0:'.SEC_PER_HOUR, 'length' => DB::getFieldLength('media_type', 'attempt_interval')],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'description')],
			'message_templates' =>		['type' => API_OBJECTS, 'uniq' => [['eventsource', 'recovery']], 'fields' => [
				'eventsource' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
				'recovery' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE])], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION])],
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION])], 'type' => API_INT32, 'in' => ACTION_OPERATION],
												['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION])]
				]],
				'subject' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
				'message' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'message')]
			]]
		]];
	}

	/**
	 * Check for unique media type names.
	 *
	 * @param array      $mediatypes
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException if a media type name is not unique.
	 */
	private static function checkDuplicates(array $mediatypes, ?array $db_mediatypes = null): void {
		$names = [];

		foreach ($mediatypes as $mediatype) {
			if (!array_key_exists('name', $mediatype)) {
				continue;
			}

			if ($db_mediatypes === null || $mediatype['name'] !== $db_mediatypes[$mediatype['mediatypeid']]['name']) {
				$names[] = $mediatype['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('media_type', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	private static function validateByType(array $field_names, array &$mediatypes, ?array $db_mediatypes = null): void {
		$checked_fields = array_fill_keys($field_names, ['type' => API_ANY]);

		foreach ($mediatypes as $i => &$mediatype) {
			$api_input_rules = ['type' => API_OBJECT, 'fields' => $checked_fields];
			$is_update = $db_mediatypes !== null;
			$db_mediatype = $is_update ? $db_mediatypes[$mediatype['mediatypeid']] : null;

			if ($is_update) {
				self::addRequiredFieldsByType($mediatype, $db_mediatype);
			}

			$api_input_rules['fields'] += self::getCommonTypeValidationFields($is_update);

			switch ($mediatype['type']) {
				case MEDIA_TYPE_EMAIL:
					if ($is_update) {
						$mediatype += array_intersect_key($db_mediatype,
							array_flip(['provider', 'smtp_security', 'smtp_authentication'])
						);
					}

					$api_input_rules['fields'] += self::getEmailTypeValidationFields($is_update);
					break;

				case MEDIA_TYPE_SMS:
					$api_input_rules['fields'] += self::getSmsTypeValidationFields($is_update);
					break;

				case MEDIA_TYPE_EXEC:
					$api_input_rules['fields'] += self::getScriptTypeValidationFields($is_update);
					break;

				case MEDIA_TYPE_WEBHOOK:
					if ($is_update) {
						$mediatype += array_intersect_key($db_mediatype, array_flip(['show_event_menu']));
					}

					$api_input_rules['fields'] += self::getWebhookTypeValidationFields($is_update);
					break;
			}

			$api_input_rules['fields'] += self::getDefaultTypeValidationRules();

			if (!CApiInputValidator::validate($api_input_rules, $mediatype, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if ($mediatype['type'] == MEDIA_TYPE_EMAIL) {
				if ($is_update) {
					self::addRequiredFieldsByProvider($mediatype, $db_mediatype);
					self::addRequiredFieldsBySmtpAuthentication($mediatype, $db_mediatype);
				}

				if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
					self::validateSmtpAuthenticationPasswordFields($mediatype, '/'.($i + 1));
				}
				elseif ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
					self::validateSmtpAuthenticationOauthFields($mediatype, $db_mediatype, '/'.($i + 1));
				}
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_WEBHOOK) {
				if ($is_update) {
					self::addRequiredFieldsByShowEventMenu($mediatype, $db_mediatype);
				}

				if ($mediatype['show_event_menu'] == ZBX_EVENT_MENU_SHOW) {
					self::validateShowEventMenuFields($mediatype, $is_update, '/'.($i + 1));
				}
			}
		}
		unset($mediatype);
	}

	private static function addRequiredFieldsByType(array &$mediatype, array $db_mediatype): void {
		if ($mediatype['type'] != $db_mediatype) {
			if ($mediatype['type'] == MEDIA_TYPE_EMAIL) {
				$mediatype += array_intersect_key($db_mediatype, array_flip(['smtp_server', 'smtp_email']));
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_SMS) {
				$mediatype += array_intersect_key($db_mediatype, array_flip(['gsm_modem']));
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_EXEC) {
				$mediatype += array_intersect_key($db_mediatype, array_flip(['exec_path']));
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_SMS) {
				$mediatype += array_intersect_key($db_mediatype, array_flip(['script']));
			}
		}
	}

	private static function getCommonTypeValidationFields(): array {
		return [
			'maxsessions' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_WEBHOOK])], 'type' => API_INT32, 'in' => '0:100'],
									['else' => true] + self::getDefaultTypeValidationRules('maxsessions')
			]],
			'parameters' =>		['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'type', 'in' => implode(',', [MEDIA_TYPE_EXEC])], 'type' => API_OBJECTS, 'uniq' => [['sortorder']], 'fields' => [
					'sortorder' =>	['type' => API_INT32, 'flags' => API_REQUIRED],
					'value' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'value')]
				]],
				['if' => ['field' => 'type', 'in' => implode(',', [MEDIA_TYPE_WEBHOOK])], 'type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
					'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_param', 'name')],
					'value' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'value')]
				]],
				['else' => true] + self::getDefaultTypeValidationRules('parameters')
			]]
		];
	}

	private static function getEmailTypeValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		return [
			'maxsessions' =>			['type' => API_ANY],
			'provider' =>				['type' => API_INT32, 'in' => implode(',', array_keys(CMediatypeHelper::getEmailProviders()))] + ($is_update ? [] : ['default' => DB::getDefault('media_type', 'provider')]),
			'smtp_server' =>			['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'smtp_server')],
			'smtp_port' =>				['type' => API_INT32, 'in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER],
			'smtp_email' =>				['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'smtp_email')],
			'smtp_helo' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_helo')],
			'smtp_security' =>			['type' => API_INT32, 'in' => implode(',', [SMTP_SECURITY_NONE, SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL])] + ($is_update ? [] : ['default' => DB::getDefault('media_type', 'smtp_security')]),
			'smtp_verify_peer' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_security', 'in' => implode(',', [SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL])], 'type' => API_INT32, 'in' => '0,1'],
											['else' => true] + self::getDefaultTypeValidationRules('smtp_verify_peer')
			]],
			'smtp_verify_host' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_security', 'in' => implode(',', [SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL])], 'type' => API_INT32, 'in' => '0,1'],
											['else' => true] + self::getDefaultTypeValidationRules('smtp_verify_host')
			]],
			'smtp_authentication' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'provider', 'in' => implode(',', [CMediatypeHelper::EMAIL_PROVIDER_SMTP, CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY])], 'type' => API_INT32, 'in' => implode(',', [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_PASSWORD, SMTP_AUTHENTICATION_OAUTH])],
											['if' => ['field' => 'provider', 'in' => implode(',', [CMediatypeHelper::EMAIL_PROVIDER_GMAIL, CMediatypeHelper::EMAIL_PROVIDER_OFFICE365])], 'type' => API_INT32, 'in' => implode(',', [SMTP_AUTHENTICATION_PASSWORD, SMTP_AUTHENTICATION_OAUTH])],
											['if' => ['field' => 'provider', 'in' => implode(',', [CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY])], 'type' => API_INT32, 'in' => implode(',', [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_PASSWORD])]
			]] + ($is_update ? [] : ['default' => DB::getDefault('media_type', 'smtp_authentication')]),
			'username' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_PASSWORD])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('username')
			]],
			'passwd' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_PASSWORD])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('passwd')
			]],
			'redirection_url' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('redirection_url')
			]],
			'client_id' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('client_id')
			]],
			'client_secret' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('client_secret')
			]],
			'authorization_url' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('authorization_url')
			]],
			'token_url' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('token_url')
			]],
			'tokens_status' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('tokens_status')
			]],
			'access_token' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('access_token')
			]],
			'access_token_updated' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('access_token_updated')
			]],
			'access_expires_in' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('access_expires_in')
			]],
			'refresh_token' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'smtp_authentication', 'in' => implode(',', [SMTP_AUTHENTICATION_OAUTH])], 'type' => API_ANY],
											['else' => true] + self::getDefaultTypeValidationRules('refresh_token')
			]],
			'message_format' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MEDIA_MESSAGE_FORMAT_TEXT, ZBX_MEDIA_MESSAGE_FORMAT_HTML])]
		];
	}

	private static function addRequiredFieldsByProvider(array &$mediatype, array $db_mediatype): void {
		if ($mediatype['provider'] != $db_mediatype['provider']
				&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
			if ($db_mediatype['provider'] == CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
				$mediatype += array_intersect_key($db_mediatype, array_flip(['username', 'passwd']));
			}
		}
	}

	private static function addRequiredFieldsBySmtpAuthentication(array &$mediatype, array $db_mediatype): void {
		if ($mediatype['smtp_authentication'] != $db_mediatype['smtp_authentication']) {
			if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
				if ($db_mediatype['provider'] == CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
					$mediatype += array_intersect_key($db_mediatype, array_flip(['username', 'passwd']));
				}
			}
			elseif ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
				$mediatype += array_intersect_key($db_mediatype,
					array_flip(['redirection_url', 'client_id', 'client_secret', 'authorization_url', 'token_url'])
				);
			}
		}
	}

	private static function validateSmtpAuthenticationPasswordFields(array &$mediatype, string $path): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'username' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'provider', 'in' => implode(',', [CMediatypeHelper::EMAIL_PROVIDER_SMTP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'username')],
								['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'username')]
			]],
			'passwd' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'provider', 'in' => implode(',', [CMediatypeHelper::EMAIL_PROVIDER_SMTP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'passwd')],
								['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'passwd')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatype, $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function validateSmtpAuthenticationOauthFields(array &$mediatype, ?array $db_mediatype,
			string $path): void {
		$is_update = $db_mediatype !== null;
		$api_required = $is_update ? 0 : API_REQUIRED;

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'redirection_url' =>		['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'redirection_url')],
			'client_id' =>				['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'client_id')],
			'client_secret' =>			['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'client_secret')],
			'authorization_url' =>		['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'authorization_url')],
			'token_url' =>				['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'token_url')],
			'tokens_status' =>			['type' => API_INT32, 'in' => implode(':', [0, OAUTH_ACCESS_TOKEN_VALID | OAUTH_REFRESH_TOKEN_VALID])],
			'access_token' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'access_token')],
			'access_token_updated' =>	['type' => API_TIMESTAMP],
			'access_expires_in' =>		['type' => API_INT32],
			'refresh_token' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_oauth', 'refresh_token')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatype, $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (array_key_exists('tokens_status', $mediatype)) {
			if ($mediatype['tokens_status'] & OAUTH_ACCESS_TOKEN_VALID
					&& (!$is_update || !($db_mediatype['tokens_status'] & OAUTH_ACCESS_TOKEN_VALID))
					&& (!array_key_exists('access_token', $mediatype)
						|| !array_key_exists('access_expires_in', $mediatype))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
					_('both "access_token" and "access_expires_in" must be specified when marking access token valid')
				));
			}

			if ($mediatype['tokens_status'] & OAUTH_REFRESH_TOKEN_VALID
					&& (!$is_update || !($db_mediatype['tokens_status'] & OAUTH_REFRESH_TOKEN_VALID))
					&& !array_key_exists('refresh_token', $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
					_('the "refresh_token" parameter must be specified when marking refresh token valid')
				));
			}
		}

		if (array_key_exists('access_token', $mediatype) !== array_key_exists('access_expires_in', $mediatype)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
				_('both "access_token" and "access_expires_in" should be either present or absent')
			));
		}
	}

	private static function getSmsTypeValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		return [
			'maxsessions' =>	['type' => API_ANY],
			'gsm_modem' =>		['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'gsm_modem')]
		];
	}

	private static function getScriptTypeValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		return [
			'maxsessions' =>	['type' => API_ANY],
			'parameters' =>		['type' => API_ANY],
			'exec_path' =>		['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'exec_path')]
		];
	}

	private static function getWebhookTypeValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		return [
			'maxsessions' =>		['type' => API_ANY],
			'parameters' =>			['type' => API_ANY],
			'script' =>				['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'script')],
			'timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('media_type', 'timeout')],
			'process_tags' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_MEDIA_TYPE_TAGS_DISABLED, ZBX_MEDIA_TYPE_TAGS_ENABLED])],
			'show_event_menu' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_EVENT_MENU_HIDE, ZBX_EVENT_MENU_SHOW])] + ($is_update ? [] : ['default' => DB::getDefault('media_type', 'show_event_menu')]),
			'event_menu_url' =>		['type' => API_ANY],
			'event_menu_name' =>	['type' => API_ANY]
		];
	}

	private static function addRequiredFieldsByShowEventMenu(array &$mediatype, array $db_mediatype): void {
		if ($mediatype['show_event_menu'] != $db_mediatype['show_event_menu']) {
			if ($mediatype['show_event_menu'] == ZBX_EVENT_MENU_SHOW) {
				$mediatype += array_intersect_key($db_mediatype, array_flip(['event_menu_url', 'event_menu_name']));
			}
		}
	}

	private static function validateShowEventMenuFields(array &$mediatype, bool $is_update, string $path): void {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'event_menu_url' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'show_event_menu', 'in' => implode(',', [ZBX_EVENT_MENU_SHOW])], 'type' => API_URL, 'flags' => $api_required | API_NOT_EMPTY | API_ALLOW_EVENT_TAGS_MACRO, 'length' => DB::getFieldLength('media_type', 'event_menu_url')],
										['else' => true] + self::getDefaultTypeValidationRules('event_menu_url')
			]],
			'event_menu_name' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'show_event_menu', 'in' => implode(',', [ZBX_EVENT_MENU_SHOW])], 'type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'event_menu_name')],
										['else' => true] + self::getDefaultTypeValidationRules('event_menu_name')
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatype, $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function getDefaultTypeValidationRules(?string $field_name = null): array {
		$api_input_rules = [
			// The fields used for multiple types of media types.
			'maxsessions' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'maxsessions')],
			'parameters' =>				['type' => API_OBJECTS, 'length' => 0],

			// Email type specific fields.
			'provider' =>				['type' => API_INT32, 'in' => DB::getDefault('media_type', 'provider')],
			'smtp_server' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_server')],
			'smtp_port' =>				['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_port')],
			'smtp_email' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_email')],
			'smtp_helo' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_helo')],
			'smtp_security' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_security')],
			'smtp_verify_peer' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_verify_peer')],
			'smtp_verify_host' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_verify_host')],
			'smtp_authentication' =>	['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_authentication')],
			'username' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'username')],
			'passwd' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'passwd')],
			'redirection_url' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'redirection_url')],
			'client_id' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'client_id')],
			'client_secret' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'client_secret')],
			'authorization_url' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'authorization_url')],
			'token_url' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'token_url')],
			'tokens_status' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type_oauth', 'tokens_status')],
			'access_token' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'access_token')],
			'access_token_updated' =>	['type' => API_TIMESTAMP, 'in' => DB::getDefault('media_type_oauth', 'access_token_updated')],
			'access_expires_in' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type_oauth', 'access_expires_in')],
			'refresh_token' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type_oauth', 'refresh_token')],
			'message_format' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'message_format')],

			// SMS type specific fields.
			'gsm_modem' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'gsm_modem')],

			// Script type specific fields.
			'exec_path' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'exec_path')],

			// Webhook type specific fields.
			'script' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'script')],
			'timeout' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'timeout')],
			'process_tags' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'process_tags')],
			'show_event_menu' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'show_event_menu')],
			'event_menu_url' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_url')],
			'event_menu_name' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_name')]
		];

		if ($field_name !== null && array_key_exists($field_name, $api_input_rules)) {
			return $api_input_rules[$field_name];
		}

		return $api_input_rules;
	}

	private static function addFieldDefaultsByType(array &$mediatypes, array $db_mediatypes): void {
		$type_field_defaults = array_intersect_key(
			DB::getDefaults('media_type') + DB::getDefaults('media_type_oauth') + ['parameters' => []],
			self::getDefaultTypeValidationRules()
		);

		foreach ($mediatypes as &$mediatype) {
			$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];

			if ($mediatype['type'] != $db_mediatype['type']) {
				$type_field_names = self::getFieldNamesByType((int) $mediatype['type']);
				$db_type_field_names = self::getFieldNamesByType((int) $db_mediatype['type']);

				$field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

				$mediatype += array_intersect_key($type_field_defaults, $field_names);
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_EMAIL) {
				self::addFieldDefaultsBySmtpSecurity($mediatype, $db_mediatype, $type_field_defaults);
				self::addFieldDefaultsBySmtpAuthentication($mediatype, $db_mediatype, $type_field_defaults);
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_SMS) {
				$mediatype += ['maxsessions' => DB::getDefault('media_type', 'maxsessions')];
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_EXEC) {
				if ($db_mediatype['type'] == MEDIA_TYPE_WEBHOOK) {
					$mediatype += ['parameters' => []];
				}
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_WEBHOOK) {
				if ($db_mediatype['type'] == MEDIA_TYPE_EXEC) {
					$mediatype += ['parameters' => []];
				}

				self::addFieldDefaultsByShowEventMenu($mediatype, $db_mediatype, $type_field_defaults);
			}
		}
		unset($mediatype);
	}

	private static function getFieldNamesByType(int $type): array {
		return match ($type) {
			MEDIA_TYPE_EMAIL => array_keys(self::getEmailTypeValidationFields()),
			MEDIA_TYPE_SMS => array_keys(self::getSmsTypeValidationFields()),
			MEDIA_TYPE_EXEC => array_keys(self::getScriptTypeValidationFields()),
			MEDIA_TYPE_WEBHOOK => array_keys(self::getWebhookTypeValidationFields())
		};
	}

	private static function addFieldDefaultsBySmtpSecurity(array &$mediatype, array $db_mediatype,
			array $type_field_defaults): void {
		if ($mediatype['smtp_security'] != $db_mediatype['smtp_security']) {
			if ($mediatype['smtp_security'] == SMTP_SECURITY_NONE) {
				$mediatype +=
					array_intersect_key($type_field_defaults, array_flip(['smtp_verify_peer', 'smtp_verify_host']));
			}
		}
	}

	private static function addFieldDefaultsBySmtpAuthentication(array &$mediatype, array $db_mediatype,
			array $type_field_defaults): void {
		if ($mediatype['smtp_authentication'] != $db_mediatype['smtp_authentication']) {
			if ($db_mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
				$mediatype += array_intersect_key($type_field_defaults, array_flip(['username', 'passwd']));
			}
			elseif ($db_mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
				$mediatype += array_intersect_key($type_field_defaults, array_flip(['redirection_url', 'client_id',
					'client_secret', 'authorization_url', 'token_url', 'tokens_status', 'access_token',
					'access_token_updated', 'access_expires_in', 'refresh_token', 'token_url'
				]));
			}
		}
	}

	private static function addFieldDefaultsByShowEventMenu(array &$mediatype, array $db_mediatype,
			array $type_field_defaults): void {
		if ($mediatype['show_event_menu'] != $db_mediatype['show_event_menu']) {
			if ($db_mediatype['show_event_menu'] == ZBX_EVENT_MENU_SHOW) {
				$mediatype +=
					array_intersect_key($type_field_defaults, array_flip(['event_menu_url', 'event_menu_name']));
			}
		}
	}

	private static function updateOauth(array &$mediatypes, ?array $db_mediatypes = null): void {
		$del_mediatypeids = [];
		$upd_media_type_oauth = [];
		$ins_media_type_oauth = [];

		foreach ($mediatypes as &$mediatype) {
			$db_mediatype = $db_mediatypes !== null ? $db_mediatypes[$mediatype['mediatypeid']] : null;

			if ($mediatype['type'] == MEDIA_TYPE_EMAIL
					&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
				if (array_key_exists('access_token', $mediatype)) {
					$mediatype += ['access_token_updated' => time()];
				}

				if ($db_mediatype !== null && $db_mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
					$_upd_media_type_oauth = DB::getUpdatedValues('media_type_oauth', $mediatype, $db_mediatype);

					if ($_upd_media_type_oauth) {
						$upd_media_type_oauth[] = [
							'values' => $_upd_media_type_oauth,
							'where' => ['mediatypeid' => $mediatype['mediatypeid']]
						];
					}
				}
				else {
					$ins_media_type_oauth[] = $mediatype;
				}
			}
			elseif ($db_mediatype !== null && $db_mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
				$del_mediatypeids[] = $mediatype['mediatypeid'];
			}
		}
		unset($mediatype);

		if ($del_mediatypeids) {
			DB::delete('media_type_oauth', ['mediatypeid' => $del_mediatypeids]);
		}

		if ($upd_media_type_oauth) {
			DB::update('media_type_oauth', $upd_media_type_oauth);
		}

		if ($ins_media_type_oauth) {
			DB::insert('media_type_oauth', $ins_media_type_oauth, false);
		}
	}

	private static function updateParameters(array &$mediatypes, ?array $db_mediatypes = null): void {
		$ins_params = [];
		$upd_params = [];
		$del_paramids = [];

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('parameters', $mediatype)) {
				continue;
			}

			$db_params = [];

			if ($db_mediatypes !== null) {
				$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];
				$db_uniq_field = $db_mediatype['type'] == MEDIA_TYPE_EXEC ? 'sortorder' : 'name';
				$db_params = array_column($db_mediatype['parameters'], null, $db_uniq_field);
			}

			$uniq_field = $mediatype['type'] == MEDIA_TYPE_EXEC ? 'sortorder' : 'name';

			foreach ($mediatype['parameters'] as &$param) {
				if (array_key_exists($param[$uniq_field], $db_params)) {
					$db_param = $db_params[$param[$uniq_field]];
					$param['mediatype_paramid'] = $db_param['mediatype_paramid'];
					unset($db_params[$db_param[$uniq_field]]);

					$upd_param = DB::getUpdatedValues('media_type_param', $param, $db_param);

					if ($upd_param) {
						$upd_params[] = [
							'values' => $upd_param,
							'where' => ['mediatype_paramid' => $param['mediatype_paramid']]
						];
					}
				}
				else {
					$ins_params[] = ['mediatypeid' => $mediatype['mediatypeid']] + $param;
				}
			}
			unset($param);

			$del_paramids = array_merge($del_paramids, array_column($db_params, 'mediatype_paramid'));
		}
		unset($mediatype);

		if ($del_paramids) {
			DB::delete('media_type_param', ['mediatype_paramid' => $del_paramids]);
		}

		if ($upd_params) {
			DB::update('media_type_param', $upd_params);
		}

		if ($ins_params) {
			$paramids = DB::insert('media_type_param', $ins_params);
		}

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('parameters', $mediatype)) {
				continue;
			}

			foreach ($mediatype['parameters'] as &$param) {
				if (!array_key_exists('mediatype_paramid', $param)) {
					$param['mediatype_paramid'] = array_shift($paramids);
				}
			}
			unset($param);
		}
		unset($mediatype);
	}

	private static function updateMessageTemplates(array &$mediatypes, ?array $db_mediatypes = null): void {
		$ins_messages = [];
		$upd_messages = [];
		$del_messageids = [];

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('message_templates', $mediatype)) {
				continue;
			}

			$db_messages = $db_mediatypes !== null
				? $db_mediatypes[$mediatype['mediatypeid']]['message_templates']
				: [];

			foreach ($mediatype['message_templates'] as &$message) {
				$db_message = current(
					array_filter($db_messages, static function(array $db_message) use ($message): bool {
						return $message['eventsource'] == $db_message['eventsource']
							&& $message['recovery'] == $db_message['recovery'];
					})
				);

				if ($db_message) {
					$message['mediatype_messageid'] = $db_message['mediatype_messageid'];
					unset($db_messages[$db_message['mediatype_messageid']]);

					$upd_message = DB::getUpdatedValues('media_type_message', $message, $db_message);

					if ($upd_message) {
						$upd_messages[] = [
							'values' => $upd_message,
							'where' => ['mediatype_messageid' => $db_message['mediatype_messageid']]
						];
					}
				}
				else {
					$ins_messages[] = ['mediatypeid' => $mediatype['mediatypeid']] + $message;
				}
			}
			unset($message);

			$del_messageids = array_merge($del_messageids, array_keys($db_messages));
		}
		unset($mediatype);

		if ($del_messageids) {
			DB::delete('media_type_message', ['mediatype_messageid' => $del_messageids]);
		}

		if ($upd_messages) {
			DB::update('media_type_message', $upd_messages);
		}

		if ($ins_messages) {
			$messageids = DB::insert('media_type_message', $ins_messages);
		}

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('message_templates', $mediatype)) {
				continue;
			}

			foreach ($mediatype['message_templates'] as &$message) {
				if (!array_key_exists('mediatype_messageid', $message)) {
					$message['mediatype_messageid'] = array_shift($messageids);
				}
			}
			unset($message);
		}
		unset($mediatype);
	}

	/**
	 * @param array $mediatypeids
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function delete(array $mediatypeids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'mediatype', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $mediatypeids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'name'],
			'mediatypeids' => $mediatypeids,
			'preservekeys' => true
		]);

		if (count($db_mediatypes) != count($mediatypeids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_actions = API::Action()->get([
			'output' => ['actionid', 'name'],
			'mediatypeids' => $mediatypeids,
			'limit' => 1
		]);

		if ($db_actions) {
			$db_action_operations = API::Action()->get([
				'output' => [],
				'selectOperations' => ['opmessage'],
				'selectRecoveryOperations' => ['opmessage'],
				'selectUpdateOperations' => ['opmessage'],
				'actionids' => $db_actions[0]['actionid']
			]);

			foreach (['operations', 'recovery_operations', 'update_operations'] as $operations) {
				foreach ($db_action_operations[0][$operations] as $operation) {
					if (array_key_exists('opmessage', $operation)
							&& array_key_exists($operation['opmessage']['mediatypeid'], $db_mediatypes)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" is used by action "%2$s".',
							$db_mediatypes[$operation['opmessage']['mediatypeid']]['name'],
							$db_actions[0]['name']
						));
					}
				}
			}
		}

		DB::delete('media_type_oauth', ['mediatypeid' => $mediatypeids]);
		DB::delete('media_type', ['mediatypeid' => $mediatypeids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MEDIA_TYPE, $db_mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Add existing parameters and message templates to $db_mediatypes, regardless of whether they will be
	 * affected by the update.
	 *
	 * @param array $mediatypes
	 * @param array $db_mediatypes
	 */
	private static function addAffectedObjects(array $mediatypes, array &$db_mediatypes): void {
		$mediatypeids = ['parameters' => [], 'message_templates' => []];

		foreach ($mediatypes as $mediatype) {
			$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];

			if (array_key_exists('parameters', $mediatype)
					|| ($mediatype['type'] != $db_mediatype['type']
						&& in_array($db_mediatype['type'], [MEDIA_TYPE_EXEC, MEDIA_TYPE_WEBHOOK]))) {
				$mediatypeids['parameters'][] = $mediatype['mediatypeid'];
				$db_mediatypes[$mediatype['mediatypeid']]['parameters'] = [];
			}

			if (array_key_exists('message_templates', $mediatype)) {
				$mediatypeids['message_templates'][] = $mediatype['mediatypeid'];
				$db_mediatypes[$mediatype['mediatypeid']]['message_templates'] = [];
			}
		}

		if ($mediatypeids['parameters']) {
			$options = [
				'output' => ['mediatype_paramid', 'mediatypeid', 'name', 'value', 'sortorder'],
				'filter' => ['mediatypeid' => $mediatypeids['parameters']]
			];
			$db_params = DBselect(DB::makeSql('media_type_param', $options));

			while ($db_param = DBfetch($db_params)) {
				$db_mediatypes[$db_param['mediatypeid']]['parameters'][$db_param['mediatype_paramid']] =
					array_diff_key($db_param, array_flip(['mediatypeid']));
			}
		}

		if ($mediatypeids['message_templates']) {
			$options = [
				'output' => ['mediatype_messageid', 'mediatypeid', 'eventsource', 'recovery', 'subject', 'message'],
				'filter' => ['mediatypeid' => $mediatypeids['message_templates']]
			];
			$db_messages = DBselect(DB::makeSql('media_type_message', $options));

			while ($db_message = DBfetch($db_messages)) {
				$db_mediatypes[$db_message['mediatypeid']]['message_templates'][$db_message['mediatype_messageid']] =
					array_diff_key($db_message, array_flip(['mediatypeid']));
			}
		}
	}
}
