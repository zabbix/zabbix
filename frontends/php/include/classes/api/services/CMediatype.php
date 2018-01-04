<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Class containing methods for operations media types.
 */
class CMediatype extends CApiService {

	protected $tableName = 'media_type';
	protected $tableAlias = 'mt';
	protected $sortColumns = ['mediatypeid'];

	/**
	 * Get Media types data
	 *
	 * @param array $options
	 * @param array $options['mediatypeids'] filter by Mediatype IDs
	 * @param boolean $options['type'] filter by Mediatype type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
	 * @param boolean $options['output'] output only Mediatype IDs if not set.
	 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
	 * @param string $options['pattern'] filter by Host name containing only give pattern
	 * @param int $options['limit'] output will be limited to given number
	 * @param string $options['sortfield'] output will be sorted by given property [ 'mediatypeid', 'alias' ]
	 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
	 * @return array
	 */
	public function get($options = []) {
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
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
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
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
		}
		elseif (!$options['editable'] && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif ($options['editable'] || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return [];
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			$sqlParts['where'][] = dbConditionInt('mt.mediatypeid', $options['mediatypeids']);
		}

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.userid', $options['userids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('media_type mt', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('media_type mt', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $mediatypes) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create media types.'));
		}

		if (!$mediatypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$required_fields = ['type', 'description'];

		foreach ($mediatypes as $mediatype) {
			if (!is_array($mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Check required parameters.
			$missing_keys = array_diff($required_fields, array_keys($mediatype));

			if ($missing_keys) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Media type is missing parameters: %1$s', implode(', ', $missing_keys))
				);
			}
			else {
				foreach ($required_fields as $field) {
					if ($mediatype[$field] === '' || $mediatype[$field] === null) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Field "%1$s" is missing a value for media type "%2$s".',
							$field,
							$mediatype['description']
						));
					}
				}
			}
		}

		// Check for duplicate names.
		$duplicate_name = CArrayHelper::findDuplicate($mediatypes, 'description');
		if ($duplicate_name) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "description" value "%1$s" for media type.', $duplicate_name['description'])
			);
		}

		$simple_interval_parser = new CSimpleIntervalParser();

		foreach ($mediatypes as $mediatype) {
			// Check if media type already exists.
			$db_mediatype = API::getApiService()->select('media_type', [
				'output' => ['description'],
				'filter' => ['description' => $mediatype['description']],
				'limit' => 1
			]);

			if ($db_mediatype) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Media type "%1$s" already exists.', $mediatype['description'])
				);
			}

			// Check additional fields and values depeding on type.
			$this->checkRequiredFieldsByType($mediatype);

			switch ($mediatype['type']) {
				case MEDIA_TYPE_EZ_TEXTING:
					$message_text_limit_validator = new CLimitedSetValidator([
						'values' => [EZ_TEXTING_LIMIT_USA, EZ_TEXTING_LIMIT_CANADA]
					]);

					if (!$message_text_limit_validator->validate($mediatype['exec_path'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
							$mediatype['exec_path'],
							'exec_path',
							$mediatype['description']
						));
					}
					break;

				case MEDIA_TYPE_EMAIL:
					if (array_key_exists('smtp_authentication', $mediatype)) {
						$smtp_authentication_validator = new CLimitedSetValidator([
							'values' => [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL]
						]);

						if (!$smtp_authentication_validator->validate($mediatype['smtp_authentication'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_authentication'],
								'smtp_authentication',
								$mediatype['description']
							));
						}

						if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL
								&& (!array_key_exists('passwd', $mediatype) || $mediatype['passwd'] === ''
									|| $mediatype['passwd'] === null)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Password required for media type "%1$s".', $mediatype['description'])
							);
						}
					}

					// Validate optional 'smtp_port' field.
					if (array_key_exists('smtp_port', $mediatype) && !validatePortNumber($mediatype['smtp_port'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
							$mediatype['smtp_port'],
							'smtp_port',
							$mediatype['description']
						));
					}

					// Validate optional field 'smtp_security'.
					if (array_key_exists('smtp_security', $mediatype)) {
						$smtp_security_validator = new CLimitedSetValidator([
							'values' => [
								SMTP_CONNECTION_SECURITY_NONE,
								SMTP_CONNECTION_SECURITY_STARTTLS,
								SMTP_CONNECTION_SECURITY_SSL_TLS
							]
						]);

						if (!$smtp_security_validator->validate($mediatype['smtp_security'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_security'],
								'smtp_security',
								$mediatype['description']
							));
						}
					}

					// Validate optional field 'smtp_verify_peer'.
					if (array_key_exists('smtp_verify_peer', $mediatype)) {
						$smtp_verify_peer_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_peer_validator->validate($mediatype['smtp_verify_peer'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_peer'],
								'smtp_verify_peer',
								$mediatype['description']
							));
						}
					}

					// Validate optional field 'smtp_verify_host'.
					if (array_key_exists('smtp_verify_host', $mediatype)) {
						$smtp_verify_host_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_host_validator->validate($mediatype['smtp_verify_host'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_host'],
								'smtp_verify_host',
								$mediatype['description']
							));
						}
					}
					break;

				case MEDIA_TYPE_EXEC:
					if (array_key_exists('exec_params', $mediatype) && $mediatype['exec_params'] !== '') {
						$pos = strrpos($mediatype['exec_params'], "\n");

						if ($pos === false || strlen($mediatype['exec_params']) != $pos + 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Script parameters "%1$s" are missing the last new line feed for media type "%2$s".',
								$mediatype['exec_params'],
								$mediatype['description']
							));
						}
					}
					break;
			}

			// Validate optional 'status' field.
			if (array_key_exists('status', $mediatype)) {
				$status_validator = new CLimitedSetValidator([
					'values' => [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED]
				]);

				if (!$status_validator->validate($mediatype['status'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
						$mediatype['status'],
						'status',
						$mediatype['description']
					));
				}
			}

			// Validate optional 'maxsessions' field.
			if (array_key_exists('maxsessions', $mediatype)) {
				if ($mediatype['maxsessions'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _('cannot be empty')
					));
				}

				$min = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 0;
				$max = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 100;

				if (!ctype_digit((string) $mediatype['maxsessions']) || $mediatype['maxsessions'] > $max
						|| $mediatype['maxsessions'] < $min) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _s('must be between "%1$s" and "%2$s"', $min, $max)
					));
				}
			}

			// Validate optional 'maxattempts' field.
			if (array_key_exists('maxattempts', $mediatype)) {
				if ($mediatype['maxattempts'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _('cannot be empty')
					));
				}

				if (!ctype_digit((string) $mediatype['maxattempts']) || $mediatype['maxattempts'] > 10
						|| $mediatype['maxattempts'] < 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _s('must be between "%1$s" and "%2$s"', 1, 10)
					));
				}
			}

			// Validate optional 'attempt_interval' field.
			if (array_key_exists('attempt_interval', $mediatype)) {
				if ($mediatype['attempt_interval'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _('cannot be empty')
					));
				}

				if ($simple_interval_parser->parse($mediatype['attempt_interval']) == CParser::PARSE_SUCCESS) {
					$attempt_interval = timeUnitToSeconds($mediatype['attempt_interval']);

					if ($attempt_interval < 0 || $attempt_interval > 60) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
						));
					}
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
					));
				}
			}
		}
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $mediatypes) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can edit media types.'));
		}

		if (!$mediatypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// Validate given IDs.
		$this->checkObjectIds($mediatypes, 'mediatypeid',
			_('No "%1$s" given for media type.'),
			_('Empty media type ID.'),
			_('Incorrect media type ID.')
		);

		$mediatypeids = zbx_objectValues($mediatypes, 'mediatypeid');

		// Check value map names.
		$db_mediatypes = API::getApiService()->select('media_type', [
			'output' => ['mediatypeid', 'type', 'description', 'exec_path', 'status', 'smtp_port', 'smtp_verify_peer',
				'smtp_verify_host', 'smtp_authentication', 'maxsessions', 'maxattempts', 'attempt_interval'
			],
			'mediatypeids' => $mediatypeids,
			'preservekeys' => true
		]);

		$check_names = [];
		$simple_interval_parser = new CSimpleIntervalParser();

		foreach ($mediatypes as $mediatype) {
			// Check if this media type exists.
			if (!array_key_exists($mediatype['mediatypeid'], $db_mediatypes)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Validate "description" field.
			if (array_key_exists('description', $mediatype)) {
				if (is_array($mediatype['description'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($mediatype['description'] === '' || $mediatype['description'] === null
						|| $mediatype['description'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'description', _('cannot be empty'))
					);
				}

				$check_names[$mediatype['description']] = true;
			}
		}

		if ($check_names) {
			$db_mediatype_names = API::getApiService()->select('media_type', [
				'output' => ['mediatypeid', 'description'],
				'filter' => ['name' => array_keys($check_names)]
			]);
			$db_mediatype_names = zbx_toHash($db_mediatype_names, 'description');

			foreach ($mediatypes as $mediatype) {
				if (array_key_exists('description', $mediatype)
						&& array_key_exists($mediatype['description'], $db_mediatype_names)
						&& !idcmp($db_mediatype_names[$mediatype['description']]['mediatypeid'],
							$mediatype['mediatypeid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Media type "%1$s" already exists.', $mediatype['description'])
					);
				}
			}
		}

		// Populate "description" field, if not set. Type field should not be populated at this point.
		$mediatypes = $this->extendFromObjects(zbx_toHash($mediatypes, 'mediatypeid'), $db_mediatypes, ['description']);

		$duplicate_name = CArrayHelper::findDuplicate($mediatypes, 'description');
		if ($duplicate_name) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "description" value "%1$s" for media type.', $duplicate_name['description'])
			);
		}

		foreach ($mediatypes as $mediatype) {
			$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];

			// Recheck mandatory fields if type changed.
			if (array_key_exists('type', $mediatype) && $db_mediatype['type'] != $mediatype['type']) {
				$this->checkRequiredFieldsByType($mediatype);
			}
			else {
				$optional_fields_by_type = [
					MEDIA_TYPE_EMAIL => ['smtp_server', 'smtp_helo', 'smtp_email'],
					MEDIA_TYPE_EXEC => ['exec_path'],
					MEDIA_TYPE_SMS => ['gsm_modem'],
					MEDIA_TYPE_JABBER => ['username'],
					MEDIA_TYPE_EZ_TEXTING => ['exec_path', 'username']
				];

				foreach ($optional_fields_by_type[$db_mediatype['type']] as $field) {
					if (array_key_exists($field, $mediatype)
							&& ($mediatype[$field] === '' || $mediatype[$field] === null)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Field "%1$s" is missing a value for media type "%2$s".',
							$field,
							$mediatype['description']
						));
					}
				}

				// Populate "type" field from DB, since it is not set and is required for further validation.
				$mediatype['type'] = $db_mediatype['type'];
			}

			switch ($mediatype['type']) {
				case MEDIA_TYPE_EZ_TEXTING:
					if (array_key_exists('exec_path', $mediatype)) {
						$message_text_limit_validator = new CLimitedSetValidator([
							'values' => [EZ_TEXTING_LIMIT_USA, EZ_TEXTING_LIMIT_CANADA]
						]);

						if ($db_mediatype['exec_path'] !== $mediatype['exec_path']
								&& !$message_text_limit_validator->validate($mediatype['exec_path'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['exec_path'],
								'exec_path',
								$mediatype['description']
							));
						}
					}
					break;

				case MEDIA_TYPE_EMAIL:
					if (array_key_exists('smtp_authentication', $mediatype)) {
						$smtp_authentication_validator = new CLimitedSetValidator([
							'values' => [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL]
						]);

						if (!$smtp_authentication_validator->validate($mediatype['smtp_authentication'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_authentication'],
								'smtp_authentication',
								$mediatype['description']
							));
						}

						if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL) {
							// Check 'passwd' field when auth is set to 'normal' manually.

							if ($db_mediatype['smtp_authentication'] == $mediatype['smtp_authentication']
									&& array_key_exists('passwd', $mediatype)
									&& ($mediatype['passwd'] === '' || $mediatype['passwd'] === null)) {
								/*
								 * When auth is set to 'normal', check if password field is set manually.
								 * Otherwise the password is not changed.
								 */

								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Password required for media type "%1$s".', $mediatype['description'])
								);
							}
							elseif ($db_mediatype['smtp_authentication'] != $mediatype['smtp_authentication']
									&& (!array_key_exists('passwd', $mediatype)
										|| $mediatype['passwd'] === '' || $mediatype['passwd'] === null)) {
								/*
								 * First check if 'passwd' field exists when authentication is changed from
								 * 'none' to 'normal' and then validate it.
								 */

								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Password required for media type "%1$s".', $mediatype['description'])
								);
							}
						}
					}
					elseif ($db_mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL
							&& array_key_exists('passwd', $mediatype)
							&& ($mediatype['passwd'] === '' || $mediatype['passwd'] === null)) {
						// Check 'passwd' field depeding on authentication set from DB and when it is set to 'normal'.

						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Password required for media type "%1$s".', $mediatype['description'])
						);
					}

					// Validate optional 'smtp_port' field.
					if (array_key_exists('smtp_port', $mediatype)
							&& $db_mediatype['smtp_port'] != $mediatype['smtp_port']
							&& !validatePortNumber($mediatype['smtp_port'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
							$mediatype['smtp_port'],
							'smtp_port',
							$mediatype['description']
						));
					}

					// Validate optional field 'smtp_security'.
					if (array_key_exists('smtp_security', $mediatype)) {
						$smtp_security_validator = new CLimitedSetValidator([
							'values' => [
								SMTP_CONNECTION_SECURITY_NONE,
								SMTP_CONNECTION_SECURITY_STARTTLS,
								SMTP_CONNECTION_SECURITY_SSL_TLS
							]
						]);

						if (!$smtp_security_validator->validate($mediatype['smtp_security'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_security'],
								'smtp_security',
								$mediatype['description']
							));
						}
					}

					// Validate optional field 'smtp_verify_peer'.
					if (array_key_exists('smtp_verify_peer', $mediatype)
							&& $db_mediatype['smtp_verify_peer'] != $mediatype['smtp_verify_peer']) {
						$smtp_verify_peer_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_peer_validator->validate($mediatype['smtp_verify_peer'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_peer'],
								'smtp_verify_peer',
								$mediatype['description']
							));
						}
					}

					// Validate optional field 'smtp_verify_host'.
					if (array_key_exists('smtp_verify_host', $mediatype)
							&& $db_mediatype['smtp_verify_host'] != $mediatype['smtp_verify_host']) {
						$smtp_verify_host_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_host_validator->validate($mediatype['smtp_verify_host'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_host'],
								'smtp_verify_host',
								$mediatype['description']
							));
						}
					}
					break;

				case MEDIA_TYPE_EXEC:
					if (array_key_exists('exec_params', $mediatype) && $mediatype['exec_params'] !== '') {
						$pos = strrpos($mediatype['exec_params'], "\n");

						if ($pos === false || strlen($mediatype['exec_params']) != $pos + 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Script parameters "%1$s" are missing the last new line feed for media type "%2$s".',
								$mediatype['exec_params'],
								$mediatype['description']
							));
						}
					}
					break;
			}

			// Validate optional 'status' field and only when status is changed.
			if (array_key_exists('status', $mediatype) && $db_mediatype['status'] != $mediatype['status']) {
				$status_validator = new CLimitedSetValidator([
					'values' => [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED]
				]);

				if (!$status_validator->validate($mediatype['status'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
						$mediatype['status'],
						'status',
						$mediatype['description']
					));
				}
			}

			// Validate optional 'maxsessions' field.
			if (array_key_exists('maxsessions', $mediatype)
					&& $db_mediatype['maxsessions'] != $mediatype['maxsessions']) {
				if ($mediatype['maxsessions'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _('cannot be empty')
					));
				}

				$min = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 0;
				$max = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 100;

				if (!ctype_digit((string) $mediatype['maxsessions']) || $mediatype['maxsessions'] > $max
						|| $mediatype['maxsessions'] < $min) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _s('must be between "%1$s" and "%2$s"', $min, $max)
					));
				}
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_SMS && $mediatype['type'] != $db_mediatype['type']
						&& $db_mediatype['maxsessions'] != 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'maxsessions', _s('must be between "%1$s" and "%2$s"', 1, 1)
				));
			}

			// Validate optional 'maxattempts' field.
			if (array_key_exists('maxattempts', $mediatype)
					&& $db_mediatype['maxattempts'] != $mediatype['maxattempts']) {
				if ($mediatype['maxattempts'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _('cannot be empty')
					));
				}

				if (!ctype_digit((string) $mediatype['maxattempts']) || $mediatype['maxattempts'] > 10
						|| $mediatype['maxattempts'] < 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _s('must be between "%1$s" and "%2$s"', 1, 10)
					));
				}
			}

			// Validate optional 'attempt_interval' field.
			if (array_key_exists('attempt_interval', $mediatype)
					&& $db_mediatype['attempt_interval'] != $mediatype['attempt_interval']) {
				if ($mediatype['attempt_interval'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _('cannot be empty')
					));
				}

				if ($simple_interval_parser->parse($mediatype['attempt_interval']) == CParser::PARSE_SUCCESS) {
					$attempt_interval = timeUnitToSeconds($mediatype['attempt_interval']);

					if ($attempt_interval < 0 || $attempt_interval > 60) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
						));
					}
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
					));
				}
			}
		}
	}

	/**
	 * Add Media types.
	 *
	 * @param array		$mediatypes							multidimensional array with media types data
	 * @param int		$mediatypes['type']					type
	 * @param string	$mediatypes['description']			description
	 * @param string	$mediatypes['smtp_server']			SMTP server
	 * @param int		$mediatypes['smtp_port']			SMTP port
	 * @param string	$mediatypes['smtp_helo']			SMTP hello
	 * @param string	$mediatypes['smtp_email']			SMTP email
	 * @param int		$mediatypes['smtp_security']		SMTP connection security
	 * @param int		$mediatypes['smtp_verify_peer']		SMTP verify peer
	 * @param int		$mediatypes['smtp_verify_host']		SMTP verify host
	 * @param int		$mediatypes['smtp_authentication']	SMTP authentication
	 * @param string	$mediatypes['exec_path']			script name/message text limit
	 * @param string	$mediatypes['exec_params']			script parameters
	 * @param string	$mediatypes['gsm_modem']			GSM modem
	 * @param string	$mediatypes['username']				username
	 * @param string	$mediatypes['passwd']				password
	 * @param int		$mediatypes['status']				media type status
	 * @param int		$mediatypes['maxsessions']			Limit of simultaneously processed alerts.
	 * @param int		$mediatypes['maxattempts']			Maximum attempts to deliver alert successfully.
	 * @param string	$mediatypes['attempt_interval']		Interval between alert delivery attempts.
	 *
	 * @return array
	 */
	public function create($mediatypes) {
		$mediatypes = zbx_toArray($mediatypes);

		$this->validateCreate($mediatypes);

		$mediatypeids = DB::insert('media_type', $mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Update Media types.
	 *
	 * @param array		$mediatypes							multidimensional array with media types data
	 * @param int		$mediatypes['mediatypeid']			id
	 * @param int		$mediatypes['type']					type
	 * @param string	$mediatypes['description']			description
	 * @param string	$mediatypes['smtp_server']			SMTP server
	 * @param int		$mediatypes['smtp_port']			SMTP port
	 * @param string	$mediatypes['smtp_helo']			SMTP hello
	 * @param string	$mediatypes['smtp_email']			SMTP email
	 * @param int		$mediatypes['smtp_security']		SMTP connection security
	 * @param int		$mediatypes['smtp_verify_peer']		SMTP verify peer
	 * @param int		$mediatypes['smtp_verify_host']		SMTP verify host
	 * @param int		$mediatypes['smtp_authentication']	SMTP authentication
	 * @param string	$mediatypes['exec_path']			script name/message text limit
	 * @param string	$mediatypes['exec_params']			script parameters
	 * @param string	$mediatypes['gsm_modem']			GSM modem
	 * @param string	$mediatypes['username']				username
	 * @param string	$mediatypes['passwd']				password
	 * @param int		$mediatypes['status']				media type status
	 * @param int		$mediatypes['maxsessions']			Limit of simultaneously processed alerts.
	 * @param int		$mediatypes['maxattempts']			Maximum attempts to deliver alert successfully.
	 * @param string	$mediatypes['attempt_interval']		Interval between alert delivery attempts.
	 *
	 * @return array
	 */
	public function update($mediatypes) {
		$mediatypes = zbx_toArray($mediatypes);

		$this->validateUpdate($mediatypes);

		$update = [];
		foreach ($mediatypes as $mediatype) {
			$mediatypeid = $mediatype['mediatypeid'];
			unset($mediatype['mediatypeid']);

			if (!empty($mediatype)) {
				$update[] = [
					'values' => $mediatype,
					'where' => ['mediatypeid' => $mediatypeid]
				];
			}
		}

		DB::update('media_type', $update);
		$mediatypeids = zbx_objectValues($mediatypes, 'mediatypeid');

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Delete Media types.
	 *
	 * @param array $mediatypeids
	 *
	 * @return array
	 */
	public function delete(array $mediatypeids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete media types.'));
		}

		$actions = API::Action()->get([
			'mediatypeids' => $mediatypeids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);
		if (!empty($actions)) {
			$action = reset($actions);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media types used by action "%s".', $action['name']));
		}

		DB::delete('media_type', ['mediatypeid' => $mediatypeids]);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Check required fields by type. Values for fields must not be empty.
	 *
	 * @param array		$mediatype							An array of media type data.
	 * @param string	$mediatype['description']			Name of the media type.
	 * @param string	$mediatype['type']					E-mail, Script, SMS, Jabber and Ez Texting.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkRequiredFieldsByType(array $mediatype) {
		$type_validator = new CLimitedSetValidator([
			'values' => array_keys(media_type2str())
		]);

		if (!$type_validator->validate($mediatype['type'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
				$mediatype['type'],
				'type',
				$mediatype['description']
			));
		}

		$required_fields_by_type = [
			MEDIA_TYPE_EMAIL => ['smtp_server', 'smtp_helo', 'smtp_email'],
			MEDIA_TYPE_EXEC => ['exec_path'],
			MEDIA_TYPE_SMS => ['gsm_modem'],
			MEDIA_TYPE_JABBER => ['username'],
			MEDIA_TYPE_EZ_TEXTING => ['exec_path', 'username']
		];

		foreach ($required_fields_by_type[$mediatype['type']] as $field) {
			// Check if fields set on Create method. For update method they are checked when type is changed.
			if (!array_key_exists($field, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Field "%1$s" is required for media type "%2$s".', $field, $mediatype['description'])
				);
			}
			elseif (array_key_exists($field, $mediatype)
					&& ($mediatype[$field] === '' || $mediatype[$field] === null)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Field "%1$s" is missing a value for media type "%2$s".', $field, $mediatype['description'])
				);
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'mediatypeid', 'userid', 'media');
			$users = API::User()->get([
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $users, 'users');
		}

		return $result;
	}
}
