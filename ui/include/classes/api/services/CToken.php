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
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_MANAGE_API_TOKENS]
	];

	protected const AUDIT_RESOURCE = AUDIT_RESOURCE_AUTH_TOKEN;

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
}
