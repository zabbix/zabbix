<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS]
	];

	protected const AUDIT_RESOURCE = AUDIT_RESOURCE_AUTH_TOKEN;

	protected $tableName = 'token';
	protected $tableAlias = 't';
	protected $sortColumns = ['tokenid', 'name', 'description', 'userid', 'lastaccess', 'status', 'expires_at',
		'created_at', 'creator_userid'
	];

	/**
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function create(array $tokens): array {
		$this->validateCreate($tokens);

		array_walk($tokens, function (&$token) {
			$token['created_at'] = time();
			$token['creator_userid'] = static::$userData['userid'];
		});

		$tokenids = DB::insert('token', $tokens);

		array_walk($tokens, function (&$token, $index) use ($tokenids) {
			$token['tokenid'] = $tokenids[$index];
		});

		$this->addAuditBulk(AUDIT_ACTION_ADD, static::AUDIT_RESOURCE, $tokens);

		return ['tokenids' => $tokenids];
	}

	/**
	 * @param array $tokens
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateCreate(array &$tokens): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('token', 'name')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('token', 'description')],
			'userid' =>			['type' => API_ID, 'default' => self::$userData['userid']],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_AUTH_TOKEN_ENABLED, ZBX_AUTH_TOKEN_DISABLED])],
			'expires_at' =>		['type' => API_INT32]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tokens, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates($tokens);
		$this->checkUsers($tokens);
	}

	/**
	 * Check for valid users.
	 *
	 * @param array $tokens
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
	 * Check for duplicated token.
	 *
	 * @param array $tokens
	 *
	 * @throws APIException  if token already exists.
	 */
	protected function checkDuplicates(array $tokens): void {
		$user_tokens = [];

		foreach ($tokens as $token) {
			if (array_key_exists($token['userid'], $user_tokens)
					&& array_key_exists($token['name'], $user_tokens[$token['userid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('API token "%1$s" already exists for user "%2$s".',
						$token['name'], $token['userid']
				));
			}

			$user_tokens[$token['userid']][$token['name']] = true;
		}

		foreach ($user_tokens as $userid => $token_names) {
			$db_tokens = DBfetchArray(DBselect(
				'SELECT t.userid,t.name'.
				' FROM token t'.
				' WHERE '.dbConditionId('t.userid', (array) $userid).
					' AND '.dbConditionString('t.name', array_keys($token_names))
			));

			if ($db_tokens) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('API token "%1$s" already exists for user "%2$s".',
						$db_tokens[0]['name'], $db_tokens[0]['userid']
				));
			}
		}
	}

	/**
	 * @param array $tokenids
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @return array
	 */
	public function delete(array $tokenids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $tokenids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		['type' => $type, 'userid' => $userid] = self::$userData;
		$db_tokens = DB::select('token', [
			'output' => ['tokenid', 'userid', 'name'],
			'tokenids' => $tokenids,
			'filter' => ['userid' => $type == USER_TYPE_SUPER_ADMIN ? null : [$userid]]
		]);

		if (count($db_tokens) != count($tokenids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('token', ['tokenid' => $tokenids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, static::AUDIT_RESOURCE, $db_tokens);

		return ['tokenids' => $tokenids];
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'tokenids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'token' =>					['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'length' => 64, 'default' => null],
			'valid_at' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'expired_at' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'tokenid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
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
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $this->sortColumns), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
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
}
