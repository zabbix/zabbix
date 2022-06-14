<?php declare(strict_types = 0);
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
 * Class containing methods for operations with authorization tokens.
 */
class CToken extends CApiService {

	public const ACCESS_RULES = [
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS],
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS],
		'generate' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS]
	];

	protected $tableName = 'token';
	protected $tableAlias = 't';
	protected $sortColumns = ['tokenid', 'name', 'lastaccess', 'status', 'expires_at', 'created_at'];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	public function get(array $options = []) {
		$token_fields = ['tokenid', 'name', 'description', 'userid', 'lastaccess', 'status', 'expires_at',
			'created_at', 'creator_userid'
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'tokenids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'token' =>					['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'length' => 64, 'default' => null],
			'valid_at' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'expired_at' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'tokenid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'userid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'lastaccess' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_AUTH_TOKEN_ENABLED, ZBX_AUTH_TOKEN_DISABLED])],
				'expires_at' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'created_at' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'creator_userid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'description' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $token_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select' => [],
			'from'   => [$this->tableName() => $this->tableName().' '.$this->tableAlias()],
			'where'  => [],
			'order'  => [],
			'group'  => []
		];

		// Fix incorrect postgres query when sort is used together with count.
		if ($options['countOutput'] && $options['sortfield']) {
			$options['sortfield'] = [];
		}

		// Hides token field value from being shown.
		if (!$options['countOutput'] && $options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->getTableSchema()['fields'];
			unset($options['output']['token']);
			$options['output'] = array_keys($options['output']);
		}

		// permissions
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sql_parts['where'][] = dbConditionInt($this->tableAlias().'.userid', (array) self::$userData['userid']);
		}

		// tokenids
		if ($options['tokenids'] !== null) {
			$sql_parts['where'][] = dbConditionInt($this->tableAlias().'.tokenid', $options['tokenids']);
		}

		// userids
		if ($options['userids'] !== null) {
			$sql_parts['where'][] = dbConditionInt($this->tableAlias().'.userid', $options['userids']);
		}

		// token
		if ($options['token'] !== null) {
			$token = hash('sha512', $options['token']);
			$sql_parts['where'][] = dbConditionString($this->tableAlias().'.token', (array) $token);
		}

		// valid_at
		if ($options['valid_at'] !== null) {
			$sql_parts['where'][] = '('.$this->tableAlias().'.expires_at=0 OR '.
				$this->tableAlias().'.expires_at>'.$options['valid_at'].')';
		}

		// expired_at
		if ($options['expired_at'] !== null) {
			$sql_parts['where'][] = '('.$this->tableAlias().'.expires_at!=0 AND '.
				$this->tableAlias().'.expires_at<='.$options['expired_at'].')';
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter($this->tableName().' '.$this->tableAlias(), $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search($this->tableName().' '.$this->tableAlias(), $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_tokens = [];
		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_tokens[$row['tokenid']] = $row;
		}

		if (!$db_tokens) {
			return [];
		}

		if (!$options['preservekeys']) {
			$db_tokens = array_values($db_tokens);
		}

		return $this->unsetExtraFields($db_tokens, ['tokenid'], $options['output']);
	}

	/**
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function create(array $tokens): array {
		if ($this::$userData['username'] === ZBX_GUEST_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->validateCreate($tokens);

		array_walk($tokens, function (&$token) {
			$token['created_at'] = time();
			$token['creator_userid'] = static::$userData['userid'];
		});

		$tokenids = DB::insert('token', $tokens);

		array_walk($tokens, function (&$token, $index) use ($tokenids) {
			$token['tokenid'] = $tokenids[$index];
		});

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_AUTH_TOKEN, $tokens);

		return ['tokenids' => $tokenids];
	}

	/**
	 * @param array $tokens
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateCreate(array &$tokens): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['userid', 'name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('token', 'name')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('token', 'description')],
			'userid' =>			['type' => API_ID, 'default' => self::$userData['userid']],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_AUTH_TOKEN_ENABLED, ZBX_AUTH_TOKEN_DISABLED])],
			'expires_at' =>		['type' => API_INT32]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tokens, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkUsers($tokens);
		$this->checkDuplicateNames($tokens);
	}

	/**
	 * Check if token objects contain correct userids.
	 *
	 * @param array  $tokens
	 * @param string $tokens['userid']
	 *
	 * @throws APIException  if user is not valid.
	 */
	protected function checkUsers(array $tokens): void {
		$userids = array_column($tokens, 'userid', 'userid');

		if (array_key_exists(self::$userData['userid'], $userids)) {
			unset($userids[self::$userData['userid']]);
		}

		if ($userids) {
			if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with ID "%1$s" is not available.', key($userids)));
			}

			$db_users = API::User()->get([
				'output' => [],
				'userids' => $userids,
				'preservekeys' => true
			]);

			foreach ($userids as $userid) {
				if (!array_key_exists($userid, $db_users)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with ID "%1$s" is not available.', $userid));
				}
			}
		}
	}

	/**
	 * Check uniqueness of token name per user.
	 *
	 * @param array      $tokens
	 * @param string     $tokens[]['tokenid']           (optional) Required when $db_tokens is not null.
	 * @param string     $tokens[]['userid']
	 * @param string     $tokens[]['name']              (optional)
	 * @param array|null $db_tokens
	 * @param string     $db_tokens[<tokenid>]['name']
	 *
	 *
	 * @throws APIException  if token already exists.
	 */
	protected function checkDuplicateNames(array $tokens, array $db_tokens = null): void {
		$names_by_userid = [];

		foreach ($tokens as $token) {
			if (!array_key_exists('name', $token)) {
				continue;
			}

			if ($db_tokens === null || $token['name'] !== $db_tokens[$token['tokenid']]['name']) {
				$names_by_userid[$token['userid']][] = $token['name'];
			}
		}

		foreach ($names_by_userid as $userid => $names) {
			$duplicate = DBfetch(DBselect(
				'SELECT t.userid,t.name'.
				' FROM token t'.
				' WHERE '.dbConditionId('t.userid', [$userid]).
					' AND '.dbConditionString('t.name', $names),
				1
			));

			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('API token "%1$s" already exists for userid "%2$s".',
					$duplicate['name'], $userid
				));
			}
		}
	}

	/**
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function update(array $tokens): array {
		if ($this::$userData['username'] === ZBX_GUEST_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->validateUpdate($tokens, $db_tokens);

		$upd_tokens = [];
		foreach ($tokens as $token) {
			$db_token = $db_tokens[$token['tokenid']];

			$upd_token = DB::getUpdatedValues('token', $token, $db_token);

			if ($upd_token) {
				$upd_tokens[] = [
					'values' => $upd_token,
					'where' => ['tokenid' => $token['tokenid']]
				];
			}
		}

		if ($upd_tokens) {
			DB::update('token', $upd_tokens);
			self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_AUTH_TOKEN, $tokens, $db_tokens);
		}

		return ['tokenids' => array_column($tokens, 'tokenid')];
	}

	/**
	 * @param array $tokens
	 * @param array $db_tokens
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateUpdate(array &$tokens, array &$db_tokens = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['tokenid']], 'fields' => [
			'tokenid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('token', 'name')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('token', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_AUTH_TOKEN_ENABLED, ZBX_AUTH_TOKEN_DISABLED])],
			'expires_at' =>		['type' => API_INT32]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tokens, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_tokens = $this->get([
			'output' => ['tokenid', 'userid', 'name', 'description', 'status', 'expires_at'],
			'tokenids' => array_column($tokens, 'tokenid', 'tokenid'),
			'preservekeys' => true
		]);

		if (count($db_tokens) != count($tokens)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$tokens = $this->extendObjectsByKey($tokens, $db_tokens, 'tokenid', ['userid']);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['userid', 'name']], 'fields' => [
			'userid' =>	['type' => API_ID],
			'name' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $tokens, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicateNames($tokens, $db_tokens);
	}

	/**
	 * @param array $tokenids
	 *
	 * @static
	 *
	 * @param array $tokenids
	 */
	public static function deleteForce(array $tokenids): void {
		if (!$tokenids) {
			return;
		}

		$db_tokens = DB::select('token', [
			'output' => ['tokenid', 'userid', 'name'],
			'tokenids' => $tokenids
		]);

		DB::delete('token', ['tokenid' => $tokenids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_AUTH_TOKEN, $db_tokens);
	}

	/**
	 * @param array $tokenids
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @return array
	 */
	public function delete(array $tokenids): array {
		if ($this::$userData['username'] === ZBX_GUEST_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $tokenids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_count = $this->get([
			'countOutput' => true,
			'tokenids' => $tokenids
		]);

		if ($db_count != count($tokenids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::deleteForce($tokenids);

		return ['tokenids' => $tokenids];
	}

	/**
	 * Generates authentication token string for given API tokens.
	 *
	 * @param array $tokenids
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @return array
	 */
	public function generate(array $tokenids): array {
		if ($this::$userData['username'] === ZBX_GUEST_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $tokenids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_count = $this->get([
			'countOutput' => true,
			'tokenids' => $tokenids
		]);

		if ($db_count != count($tokenids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_tokens = DB::select('token', [
			'output' => ['tokenid', 'name', 'token', 'creator_userid'],
			'tokenids' => $tokenids,
			'preservekeys' => true
		]);

		$tokens = [];
		$response = [];
		$upd_tokens = [];
		foreach ($tokenids as $tokenid) {
			$new_token = bin2hex(random_bytes(32));

			$token = [
				'tokenid' => $tokenid,
				'token' => hash('sha512', $new_token),
				'creator_userid' => self::$userData['userid']
			];
			$tokens[] = $token;

			$response[] = [
				'tokenid' => $tokenid,
				'token' => $new_token
			];

			$upd_tokens[] = [
				'values' => DB::getUpdatedValues('token', $token, $db_tokens[$tokenid]),
				'where' => ['tokenid' => $tokenid]
			];
		}

		DB::update('token', $upd_tokens);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_AUTH_TOKEN, $tokens, $db_tokens);

		return $response;
	}
}
