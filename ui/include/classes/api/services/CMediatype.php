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

	public const OUTPUT_FIELDS = ['mediatypeid', 'type', 'name', 'smtp_server', 'smtp_helo', 'smtp_email',
		'exec_path', 'gsm_modem', 'username', 'passwd', 'status', 'smtp_port', 'smtp_security', 'smtp_verify_peer',
		'smtp_verify_host', 'smtp_authentication', 'maxsessions', 'maxattempts', 'attempt_interval', 'message_format',
		'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url', 'event_menu_name', 'description',
		'provider', 'parameters'
	];

	public const LIMITED_OUTPUT_FIELDS = ['mediatypeid', 'type', 'name', 'status', 'description', 'maxattempts'];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['media_type' => 'mt.mediatypeid'],
			'from'		=> ['media_type' => 'media_type mt'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'mediatypeids'				=> null,
			'mediaids'					=> null,
			'userids'					=> null,
			'editable'					=> false,
			// filter
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'selectUsers'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && $options['editable']) {
			return [];
		}

		$output_fields = self::$userData['type'] == USER_TYPE_SUPER_ADMIN
			? self::OUTPUT_FIELDS
			: self::LIMITED_OUTPUT_FIELDS;

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			// filter
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => DB::getFilterFields('media_type', $output_fields)],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => DB::getSearchFields('media_type', $output_fields)],

			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'selectMessageTemplates' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => static fn(): bool => self::$userData['type'] == USER_TYPE_SUPER_ADMIN, 'type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['eventsource', 'recovery', 'subject', 'message']), 'default' => null],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'selectActions' =>  		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', CAction::OUTPUT_FIELDS), 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && $options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = self::LIMITED_OUTPUT_FIELDS;
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			$sqlParts['where'][] = dbConditionInt('mt.mediatypeid', $options['mediatypeids']);
		}

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);

			if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
				$_options = [
					'output' => ['mediaid'],
					'filter' => ['userid' => self::$userData['userid']]
				];
				$accessible_mediaids = DBfetchColumn(DBselect(DB::makeSql('media', $_options)), 'mediaid');

				$options['mediaids'] = array_intersect($options['mediaids'], $accessible_mediaids);
			}

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
				$options['userids'] = array_intersect($options['userids'], [self::$userData['userid']]);
			}

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.userid', $options['userids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('media_type mt', $options, $sqlParts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('media_type mt', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		while ($mediatype = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $mediatype;
				}
				else {
					$result = $mediatype['rowscount'];
				}
			}
			else {
				$result[$mediatype['mediatypeid']] = $mediatype;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['mediatypeid', 'type'], $options['output']);
		}

		if (!$options['preservekeys']) {
			$result = array_values($result);
		}
		return $result;
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

		foreach ($mediatypes as $index => &$mediatype) {
			$mediatype['mediatypeid'] = $mediatypeids[$index];
		}
		unset($mediatype);

		self::updateParameters($mediatypes, __FUNCTION__);
		self::updateMessageTemplates($mediatypes, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MEDIA_TYPE, $mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$mediatypes): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK])],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'name')],
			'smtp_server' =>			['type' => API_STRING_UTF8],
			'smtp_helo' =>				['type' => API_STRING_UTF8],
			'smtp_email' =>				['type' => API_STRING_UTF8],
			'exec_path' =>				['type' => API_STRING_UTF8],
			'gsm_modem' =>				['type' => API_STRING_UTF8],
			'username' =>				['type' => API_STRING_UTF8],
			'passwd' =>					['type' => API_STRING_UTF8],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED])],
			'smtp_port' =>				['type' => API_INT32],
			'smtp_security' =>			['type' => API_INT32],
			'smtp_verify_peer' =>		['type' => API_INT32],
			'smtp_verify_host' =>		['type' => API_INT32],
			'smtp_authentication' =>	['type' => API_INT32],
			'provider' =>				['type' => API_INT32, 'in' => implode(',', array_keys(CMediatypeHelper::getEmailProviders()))],
			'maxsessions' =>			['type' => API_INT32],
			'maxattempts' =>			['type' => API_INT32, 'in' => '1:100'],
			'attempt_interval' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'attempt_interval'), 'in' => '0:'.SEC_PER_HOUR],
			'message_format' =>			['type' => API_INT32],
			'script' =>					['type' => API_STRING_UTF8],
			'timeout' =>				['type' => API_TIME_UNIT],
			'process_tags' =>			['type' => API_INT32],
			'show_event_menu' =>		['type' => API_INT32],
			'event_menu_url' =>			['type' => API_STRING_UTF8],
			'event_menu_name' =>		['type' => API_STRING_UTF8],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'description')],
			'parameters' =>				['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
			'message_templates' =>		['type' => API_OBJECTS, 'uniq' => [['eventsource', 'recovery']], 'fields' => [
				'eventsource' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
				'recovery' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE])], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION])],
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION])], 'type' => API_INT32, 'in' => ACTION_OPERATION],
												['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION])]
				]],
				'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
				'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'message')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType($mediatypes);
		self::checkDuplicates($mediatypes);
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

		self::validateUpdate($mediatypes, $db_mediatypes);

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

		$mediatypes = $this->extendObjectsByKey($mediatypes, $db_mediatypes, 'mediatypeid', ['type']);

		self::updateParameters($mediatypes, __FUNCTION__, $db_mediatypes);
		self::updateMessageTemplates($mediatypes, __FUNCTION__, $db_mediatypes);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MEDIA_TYPE, $mediatypes, $db_mediatypes);

		return ['mediatypeids' => array_column($mediatypes, 'mediatypeid')];
	}

	/**
	 * @param array      $mediatypes
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateUpdate(array &$mediatypes, ?array &$db_mediatypes): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'mediatypeid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'type' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK])],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'name')],
			'smtp_server' =>			['type' => API_STRING_UTF8],
			'smtp_helo' =>				['type' => API_STRING_UTF8],
			'smtp_email' =>				['type' => API_STRING_UTF8],
			'exec_path' =>				['type' => API_STRING_UTF8],
			'gsm_modem' =>				['type' => API_STRING_UTF8],
			'username' =>				['type' => API_STRING_UTF8],
			'passwd' =>					['type' => API_STRING_UTF8],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED])],
			'smtp_port' =>				['type' => API_INT32],
			'smtp_security' =>			['type' => API_INT32],
			'smtp_verify_peer' =>		['type' => API_INT32],
			'smtp_verify_host' =>		['type' => API_INT32],
			'smtp_authentication' =>	['type' => API_INT32],
			'provider' =>				['type' => API_INT32, 'in' => implode(',', array_keys(CMediatypeHelper::getEmailProviders()))],
			'maxsessions' =>			['type' => API_INT32],
			'maxattempts' =>			['type' => API_INT32, 'in' => '1:100'],
			'attempt_interval' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'attempt_interval'), 'in' => '0:'.SEC_PER_HOUR],
			'message_format' =>			['type' => API_INT32],
			'script' =>					['type' => API_STRING_UTF8],
			'timeout' =>				['type' => API_TIME_UNIT],
			'process_tags' =>			['type' => API_INT32],
			'show_event_menu' =>		['type' => API_INT32],
			'event_menu_url' =>			['type' => API_STRING_UTF8],
			'event_menu_name' =>		['type' => API_STRING_UTF8],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'description')],
			'parameters' =>				['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
			'message_templates' =>		['type' => API_OBJECTS, 'uniq' => [['eventsource', 'recovery']], 'fields' => [
				'eventsource' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
				'recovery' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE])], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION])],
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION])], 'type' => API_INT32, 'in' => ACTION_OPERATION],
												['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION])]
				]],
				'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
				'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'message')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'type', 'name', 'smtp_server', 'smtp_helo', 'smtp_email', 'exec_path',
				'gsm_modem', 'username', 'passwd', 'status', 'smtp_port', 'smtp_security', 'smtp_verify_peer',
				'smtp_verify_host', 'smtp_authentication', 'maxsessions', 'maxattempts', 'attempt_interval',
				'message_format', 'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url',
				'event_menu_name', 'description', 'provider'
			],
			'mediatypeids' => array_column($mediatypes, 'mediatypeid'),
			'preservekeys' => true
		]);

		if (count($db_mediatypes) != count($mediatypes)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($mediatypes, $db_mediatypes);
		self::validateByType($mediatypes, $db_mediatypes);

		self::addAffectedObjects($mediatypes, $db_mediatypes);
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

	/**
	 * Validate fields by type.
	 *
	 * @param array      $mediatypes
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException
	 */
	private static function validateByType(array &$mediatypes, ?array $db_mediatypes = null): void {
		$method = ($db_mediatypes === null) ? 'create' : 'update';

		$db_defaults = DB::getDefaults('media_type');
		$db_defaults['parameters'] = [];

		if ($method === 'update') {
			$type_fields = [
				MEDIA_TYPE_EMAIL => [
					'smtp_server', 'smtp_port', 'smtp_helo', 'smtp_email', 'smtp_security', 'smtp_verify_peer',
					'smtp_verify_host', 'smtp_authentication', 'username', 'passwd', 'message_format', 'provider'
				],
				MEDIA_TYPE_EXEC => [
					'exec_path', 'parameters'
				],
				MEDIA_TYPE_SMS => [
					'gsm_modem', 'maxsessions'
				],
				MEDIA_TYPE_WEBHOOK => [
					'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url', 'event_menu_name',
					'parameters'
				]
			];
		}

		foreach ($mediatypes as $i => &$mediatype) {
			if ($method === 'create') {
				$db_mediatype = $db_defaults;
				$type = $mediatype['type'];
			}
			else {
				$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];
				$type = array_key_exists('type', $mediatype) ? $mediatype['type'] : $db_mediatype['type'];
			}

			$api_input_rules = self::getValidationRulesByType($mediatype, $method, $db_mediatype);
			$type_data = array_intersect_key($mediatype, $api_input_rules['fields']);

			if (!CApiInputValidator::validate($api_input_rules, $type_data, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if ($method === 'update') {
				switch ($type) {
					case MEDIA_TYPE_EMAIL:
						if (array_key_exists('smtp_authentication', $mediatype)
								&& $mediatype['smtp_security'] == SMTP_SECURITY_NONE) {
							$mediatype += [
								'smtp_verify_peer' => $db_defaults['smtp_verify_peer'],
								'smtp_verify_host' => $db_defaults['smtp_verify_host']
							];
						}

						if (array_key_exists('smtp_authentication', $mediatype)
								&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NONE) {
							$mediatype += [
								'username' => $db_defaults['username'],
								'passwd' => $db_defaults['passwd']
							];
						}
						break;

					case MEDIA_TYPE_WEBHOOK:
						if (array_key_exists('show_event_menu', $mediatype)
								&& $mediatype['show_event_menu'] == ZBX_EVENT_MENU_HIDE) {
							$mediatype += [
								'event_menu_url' => $db_defaults['event_menu_url'],
								'event_menu_name' => $db_defaults['event_menu_name']
							];
						}
						break;
				}

				if ($type != $db_mediatype['type']) {
					$mediatype = array_merge(
						array_intersect_key($db_defaults, array_flip($type_fields[$db_mediatype['type']])),
						$mediatype
					);
				}
			}
		}
		unset($mediatype);
	}

	/**
	 * Get type specific validation rules.
	 *
	 * @param array  $mediatype
	 * @param string $method
	 * @param array  $db_mediatype
	 *
	 * @return array
	 */
	private static function getValidationRulesByType(array $mediatype, string $method, array $db_mediatype): array {
		$type = array_key_exists('type', $mediatype) ? $mediatype['type'] : $db_mediatype['type'];
		$api_input_rules = ['type' => API_OBJECT];

		switch ($type) {
			case MEDIA_TYPE_EMAIL:
				$api_input_rules['fields'] = [
					'smtp_server' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'smtp_server')],
					'smtp_helo' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_helo')],
					'smtp_email' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'smtp_email')],
					'smtp_port' =>				['type' => API_INT32, 'in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER],
					'smtp_security' =>			['type' => API_INT32, 'in' => implode(',', [SMTP_SECURITY_NONE, SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL])],
					'smtp_authentication' =>	['type' => API_INT32, 'in' => implode(',', [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL])],
					'message_format' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MEDIA_MESSAGE_FORMAT_TEXT, ZBX_MEDIA_MESSAGE_FORMAT_HTML])],
					'provider' =>				['type' => API_INT32, 'in' => implode(',', array_keys(CMediatypeHelper::getEmailProviders()))]
				];

				$mediatype += array_intersect_key($db_mediatype, array_flip(['smtp_security', 'smtp_authentication', 'provider']));

				if ($mediatype['smtp_security'] == SMTP_SECURITY_STARTTLS
						|| $mediatype['smtp_security'] == SMTP_SECURITY_SSL) {
					$api_input_rules['fields'] += [
						'smtp_verify_peer' =>	['type' => API_INT32, 'in' => '0,1'],
						'smtp_verify_host' =>	['type' => API_INT32, 'in' => '0,1']
					];
				}

				if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL) {
					if ($mediatype['provider'] != CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
						$api_input_rules['fields'] += [
							'username' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'username')],
							'passwd' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'passwd')]
						];
					}
					else {
						$api_input_rules['fields'] += [
							'username' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'username')],
							'passwd' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'passwd')]
						];
					}
				}

				if ($method === 'create' || $type != $db_mediatype['type']) {
					foreach (['smtp_server', 'smtp_email'] as $field) {
						$api_input_rules['fields'][$field]['flags'] |= API_REQUIRED;
					}
				}
				break;

			case MEDIA_TYPE_EXEC:
				$api_input_rules['fields'] = [
					'exec_path' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'exec_path')],
					'parameters' =>			['type' => API_OBJECTS, 'uniq' => [['sortorder']], 'fields' => [
						'sortorder' =>			['type' => API_INT32, 'flags' => API_REQUIRED],
						'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'value')]
					]]
				];

				if ($method === 'create' || $type != $db_mediatype['type']) {
					$api_input_rules['fields']['exec_path']['flags'] |= API_REQUIRED;
				}
				break;

			case MEDIA_TYPE_SMS:
				$api_input_rules['fields'] = [
					'gsm_modem' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'gsm_modem')],
					'maxsessions' =>	['type' => API_INT32, 'in' => DB::getDefault('media_type', 'maxsessions')]
				];

				if ($method === 'create' || $type != $db_mediatype['type']) {
					$api_input_rules['fields']['gsm_modem']['flags'] |= API_REQUIRED;
				}
				break;

			case MEDIA_TYPE_WEBHOOK:
				$api_input_rules['fields'] = [
					'script' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'script')],
					'timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:'.SEC_PER_MIN, 'length' => DB::getFieldLength('media_type', 'timeout')],
					'process_tags' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_MEDIA_TYPE_TAGS_DISABLED, ZBX_MEDIA_TYPE_TAGS_ENABLED])],
					'show_event_menu' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_EVENT_MENU_HIDE, ZBX_EVENT_MENU_SHOW])],
					'parameters' =>			['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
						'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type_param', 'name')],
						'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'value')]
					]]
				];

				$mediatype += array_intersect_key($db_mediatype, array_flip(['show_event_menu']));

				if ($mediatype['show_event_menu'] == ZBX_EVENT_MENU_SHOW) {
					$api_input_rules['fields'] += [
						'event_menu_url' =>		['type' => API_URL, 'flags' => API_ALLOW_EVENT_TAGS_MACRO | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'event_menu_url')],
						'event_menu_name' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'event_menu_name')]
					];
				}

				if ($method === 'create' || $type != $db_mediatype['type']) {
					$api_input_rules['fields']['script']['flags'] |= API_REQUIRED;

					if ($mediatype['show_event_menu'] == ZBX_EVENT_MENU_SHOW) {
						$api_input_rules['fields']['event_menu_url']['flags'] |= API_REQUIRED;
						$api_input_rules['fields']['event_menu_name']['flags'] |= API_REQUIRED;
					}
				}
				break;
		}

		$api_input_rules['fields'] += [
			'smtp_server' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_server')],
			'smtp_helo' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_helo')],
			'smtp_email' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_email')],
			'exec_path' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'exec_path')],
			'gsm_modem' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'gsm_modem')],
			'username' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'username')],
			'passwd' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'passwd')],
			'smtp_port' =>				['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_port')],
			'smtp_security' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_security')],
			'smtp_verify_peer' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_verify_peer')],
			'smtp_verify_host' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_verify_host')],
			'smtp_authentication' =>	['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_authentication')],
			'maxsessions' =>			['type' => API_INT32, 'in' => '0:100'],
			'message_format' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'message_format')],
			'script' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'script')],
			'timeout' =>				['type' => API_TIME_UNIT, 'in' => timeUnitToSeconds(DB::getDefault('media_type', 'timeout'))],
			'process_tags' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'process_tags')],
			'show_event_menu' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'show_event_menu')],
			'event_menu_url' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_url')],
			'event_menu_name' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_name')],
			'parameters' =>				['type' => API_OBJECT, 'fields' => []]
		];

		return $api_input_rules;
	}

	/**
	 * Update table "media_type_param" and populate mediatype.parameters by "mediatype_paramid" property.
	 *
	 * @param array      $mediatypes
	 * @param string     $method
	 * @param array|null $db_mediatypes
	 */
	private static function updateParameters(array &$mediatypes, string $method, ?array $db_mediatypes = null): void {
		$ins_params = [];
		$upd_params = [];
		$del_paramids = [];

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('parameters', $mediatype)) {
				continue;
			}

			$db_params = [];

			if ($method === 'update') {
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

	/**
	 * Update table "media_type_message" and populate mediatype.message_templates by "mediatype_messageid" property.
	 *
	 * @param array      $mediatypes
	 * @param string     $method
	 * @param array|null $db_mediatypes
	 */
	private static function updateMessageTemplates(array &$mediatypes, string $method,
			?array $db_mediatypes = null): void {
		$ins_messages = [];
		$upd_messages = [];
		$del_messageids = [];

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('message_templates', $mediatype)) {
				continue;
			}

			$db_messages = ($method === 'update') ? $db_mediatypes[$mediatype['mediatypeid']]['message_templates'] : [];

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

		DB::delete('media_type', ['mediatypeid' => $mediatypeids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MEDIA_TYPE, $db_mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts): array {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput'] && $this->outputIsRequested('parameters', $options['output'])) {
			$sqlParts = $this->addQuerySelect($this->fieldId('type'), $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedActions($options, $result);

		// adding message templates
		if (array_key_exists('selectMessageTemplates', $options) && $options['selectMessageTemplates'] !== null) {
			$message_templates = [];

			$relation_map = $this->createRelationMap($result, 'mediatypeid', 'mediatype_messageid',
				'media_type_message'
			);

			$related_ids = $relation_map->getRelatedIds();

			if ($related_ids) {
				$message_templates = API::getApiService()->select('media_type_message', [
					'output' => $options['selectMessageTemplates'],
					'mediatype_messageids' => $related_ids,
					'preservekeys' => true
				]);

				$message_templates = $this->unsetExtraFields($message_templates,
					['mediatype_messageid', 'mediatypeid']
				);
			}

			$result = $relation_map->mapMany($result, $message_templates, 'message_templates');
		}

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$user_condition = self::$userData['type'] != USER_TYPE_SUPER_ADMIN
				? ['userid' => self::$userData['userid']]
				: [];
			$_options = [
				'output' => ['mediatypeid', 'userid'],
				'filter' => ['mediatypeid' => array_keys($result)] + $user_condition
			];
			$medias = DBselect(DB::makeSql('media', $_options));

			$relation_map = new CRelationMap();

			while ($media = DBfetch($medias)) {
				$relation_map->addRelation($media['mediatypeid'], $media['userid']);
			}

			$users = [];
			$related_ids = $relation_map->getRelatedIds();

			if ($related_ids) {
				$users = API::User()->get([
					'output' => $options['selectUsers'],
					'userids' => $related_ids,
					'preservekeys' => true
				]);
			}

			$result = $relation_map->mapMany($result, $users, 'users');
		}

		if ($this->outputIsRequested('parameters', $options['output'])) {
			foreach ($result as $mediatypeid => $mediatype) {
				$result[$mediatypeid]['parameters'] = [];
			}

			$parameters = DB::select('media_type_param', [
				'output' => ['mediatypeid', 'name', 'value', 'sortorder'],
				'filter' => ['mediatypeid' => array_keys($result)]
			]);

			foreach ($parameters as $parameter) {
				if ($result[$parameter['mediatypeid']]['type'] == MEDIA_TYPE_EXEC) {
					$result[$parameter['mediatypeid']]['parameters'][] = [
						'sortorder' => $parameter['sortorder'],
						'value' => $parameter['value']
					];
				}
				else {
					$result[$parameter['mediatypeid']]['parameters'][] = [
						'name' => $parameter['name'],
						'value' => $parameter['value']
					];
				}
			}

			foreach ($result as &$mediatype) {
				if ($mediatype['type'] == MEDIA_TYPE_EXEC) {
					CArrayHelper::sort($mediatype['parameters'], [['field' => 'sortorder', 'order' => ZBX_SORT_UP]]);
					$mediatype['parameters'] = array_values($mediatype['parameters']);
				}
			}
			unset($mediatype);
		}

		return $result;
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
			if (array_key_exists('parameters', $mediatype)) {
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

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedActions(array $options, array &$result): void {
		if ($options['selectActions'] === null) {
			return;
		}

		$db_action_mediatypes = DBselect(
			'SELECT DISTINCT om.mediatypeid,o.actionid'.
			' FROM opmessage om,operations o'.
			' WHERE om.operationid=o.operationid'.
				' AND ('.dbConditionId('om.mediatypeid', array_keys($result)).' OR om.mediatypeid IS NULL)'
		);

		$action_mediatypeids = [];

		while ($row = DBfetch($db_action_mediatypes)) {
			if ($row['mediatypeid'] == 0) {
				foreach ($result as $mediatype) {
					$action_mediatypeids[$row['actionid']][$mediatype['mediatypeid']] = true;
				}
			}
			else {
				$action_mediatypeids[$row['actionid']][$row['mediatypeid']] = true;
			}
		}

		$actions = API::Action()->get([
			'output' => $options['selectActions'],
			'actionids' => array_keys($action_mediatypeids),
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		foreach ($result as $mediatype) {
			$result[$mediatype['mediatypeid']]['actions'] = [];
		}

		foreach ($actions as $actionid => $action) {
			foreach ($action_mediatypeids[$actionid] as $mediatypeid => $foo) {
				$result[$mediatypeid]['actions'][] = $action;
			}
		}
	}
}
