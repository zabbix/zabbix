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
 * Class containing methods for operations media types.
 *
 * @package API
 */
class CMediatype extends CApiService {

	protected $tableName = 'media_type';
	protected $tableAlias = 'mt';
	protected $sortColumns = array('mediatypeid');

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
	public function get($options = array()) {
		$result = array();

		$sqlParts = array(
			'select'	=> array('media_type' => 'mt.mediatypeid'),
			'from'		=> array('media_type' => 'media_type mt'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'mediatypeids'				=> null,
			'mediaids'					=> null,
			'userids'					=> null,
			'editable'					=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectUsers'				=> null,
			'selectMedia'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
		}
		elseif ($options['editable'] === null && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif ($options['editable'] !== null && self::$userData['type'] != USER_TYPE_ZABBIX_ADMIN) {
			return array();
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
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
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
	 * Add Media types.
	 *
	 * @param array		$mediaTypes
	 * @param string	$mediaTypes['type']				E-mail, Script, SMS, Jabber, Ez Texting and Remedy Service
	 * @param string	$mediaTypes['description']		Name of the media type
	 * @param string	$mediaTypes['smtp_server']		Used for e-mail and Remedy Service URL
	 * @param string	$mediaTypes['smtp_helo']		Used for e-mail and Remedy Service Proxy
	 * @param string	$mediaTypes['smtp_email']		Used for e-mail only
	 * @param string	$mediaTypes['exec_path']		Used for scripts, Ez Texting, Remedy Service company name
	 * @param string	$mediaTypes['gsm_modem']		Used for SMS only
	 * @param string	$mediaTypes['username']			Used for Jabber, Ez Texting and Remedy Service
	 * @param string	$mediaTypes['passwd']			Used for Jabber, Ez Texting and Remedy Service
	 * @param int		$mediaTypes['status']			Enabled or disabled
	 *
	 * @return array
	 */
	public function create($mediaTypes) {
		$mediaTypes = zbx_toArray($mediaTypes);

		$this->validateCreate($mediaTypes);

		$mediaTypeIds = DB::insert('media_type', $mediaTypes);

		return array('mediatypeids' => $mediaTypeIds);
	}

	/**
	 * Update Media types.
	 *
	 * @param array		$mediaTypes
	 * @param string	$mediaTypes['type']				E-mail, Script, SMS, Jabber, Ez Texting and Remedy Service
	 * @param string	$mediaTypes['description']		Name of the media type
	 * @param string	$mediaTypes['smtp_server']		Used for e-mail and Remedy Service URL
	 * @param string	$mediaTypes['smtp_helo']		Used for e-mail and Remedy Service Proxy
	 * @param string	$mediaTypes['smtp_email']		Used for e-mail only
	 * @param string	$mediaTypes['exec_path']		Used for scripts, Ez Texting, Remedy Service company name
	 * @param string	$mediaTypes['gsm_modem']		Used for SMS only
	 * @param string	$mediaTypes['username']			Used for Jabber, Ez Texting and Remedy Service
	 * @param string	$mediaTypes['passwd']			Used for Jabber, Ez Texting and Remedy Service
	 * @param int		$mediaTypes['status']			Enabled or disabled
	 *
	 * @return array
	 */
	public function update($mediaTypes) {
		$mediaTypes = zbx_toArray($mediaTypes);

		$this->validateUpdate($mediaTypes);

		$update = array();
		foreach ($mediaTypes as $mediaType) {
			$update[] = array(
				'values' => $mediaType,
				'where' => array('mediatypeid' => $mediaType['mediatypeid'])
			);
		}

		DB::update('media_type', $update);

		$mediaTypeIds = zbx_objectValues($mediaTypes, 'mediatypeid');

		return array('mediatypeids' => $mediaTypeIds);
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

		$actions = API::Action()->get(array(
			'mediatypeids' => $mediatypeids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		if (!empty($actions)) {
			$action = reset($actions);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media types used by action "%s".', $action['name']));
		}

		DB::delete('media_type', array('mediatypeid' => $mediatypeids));

		return array('mediatypeids' => $mediatypeids);
	}

	/**
	 * Validate media type data on Create method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $mediaTypes
	 */
	protected function validateCreate(array $mediaTypes) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create media types.'));
		}

		if (!$mediaTypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$typeValidator = new CLimitedSetValidator(array(
			'values' => array_keys(media_type2str())
		));
		$statusValidator = new CLimitedSetValidator(array(
			'values' => array(MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED)
		));

		$requiredFields = array('type', 'description');

		foreach ($mediaTypes as $mediaType) {
			// check if required keys are set and they are not empty
			$missingKeys = checkRequiredKeys($mediaType, $requiredFields);
			if ($missingKeys) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type missing parameters: %1$s',
					implode(', ', $missingKeys)
				));
			}
			else {
				foreach ($requiredFields as $field) {
					if (zbx_empty($mediaType[$field])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Field "%1$s" is missing a value for media type "%2$s".',
							$field,
							$mediaType['description']
						));
					}
				}
			}

			// check if media type already exists
			$dbMediaTypeExists = API::getApiService()->select($this->tableName(), array(
				'output' => array('description'),
				'filter' => array('description' => $mediaType['description'])
			));

			if ($dbMediaTypeExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type "%1$s" already exists.',
					$dbMediaTypeExists[0]['description']
				));
			}

			// validate type
			if (!$typeValidator->validate($mediaType['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type "%1$s" has incorrect value for field "%2$s".',
					$mediaType['type'],
					'type'
				));
			}

			// check other fields depending on each type
			$this->checkRequiredFieldsByType($mediaType);

			// validate optional field
			if (isset($mediaType['status']) && !$statusValidator->validate($mediaType['status'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type "%1$s" has incorrect value for field "%2$s".',
					$mediaType['description'],
					'status'
				));
			}
		}
	}

	/**
	 * Validate media type data on Update method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $mediaTypes
	 */
	protected function validateUpdate(array $mediaTypes) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update media types.'));
		}

		if (!$mediaTypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$requiredFields = array('mediatypeid');

		foreach ($mediaTypes as $mediaType) {
			$missingKeys = checkRequiredKeys($mediaType, $requiredFields);
			if ($missingKeys) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type missing parameters: %1$s',
					implode(', ', $missingKeys)
				));
			}
		}

		$mediaTypeIds = zbx_objectValues($mediaTypes, 'mediatypeid');

		$dbMediaTypes = $this->get(array(
			'mediatypeids' => $mediaTypeIds,
			'countOutput' => true
		));

		if ($dbMediaTypes != count($mediaTypes)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _(
				'No permissions to referred object or it does not exist!'
			));
		}

		$typeValidator = new CLimitedSetValidator(array(
			'values' => array_keys(media_type2str())
		));
		$statusValidator = new CLimitedSetValidator(array(
			'values' => array(MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED)
		));

		$dbMediaTypes = API::getApiService()->select($this->tableName(), array(
			'output' => array('mediatypeid', 'type', 'description', 'status'),
			'mediatypeids' => $mediaTypeIds,
			'preservekeys' => true
		));

		foreach ($mediaTypes as $mediaType) {
			// if description changed, check if matches any of existing descriptions
			if (isset($mediaType['description'])
					&& $dbMediaTypes[$mediaType['mediatypeid']]['description'] != $mediaType['description']) {
				foreach ($dbMediaTypes as $dbMediaType) {
					if ($dbMediaType['description'] === $mediaType['description']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Media type "%1$s" already exists.',
							$mediaType['description']
						));
					}
				}
			}
			// use description from DB in other error messages
			else {
				$mediaType['description'] = $dbMediaTypes[$mediaType['mediatypeid']]['description'];
			}

			// when changing type, validate new required fields just like in create method
			if (isset($mediaType['type'])
					&& $dbMediaTypes[$mediaType['mediatypeid']]['type'] != $mediaType['type']) {
				if (!$typeValidator->validate($mediaType['type'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Media type "%1$s" has incorrect value for field "%2$s".',
						$mediaType['description'],
						'type'
					));
				}

				$this->checkRequiredFieldsByType($mediaType);
			}

			// validate input on status change
			if (isset($mediaType['status'])
					&& $dbMediaTypes[$mediaType['mediatypeid']]['status'] != $mediaType['status']
					&& !$statusValidator->validate($mediaType['status'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type "%1$s" has incorrect value for field "%2$s".',
					$mediaType['description'],
					'status'
				));
			}
		}
	}

	/**
	 * Check required fields by type.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array		$mediaType
	 * @param string	$mediaType['type']				E-mail, Script, SMS, Jabber, Ez Texting and Remedy Service
	 * @param string	$mediatype['description']		Name of the media type
	 */
	protected function checkRequiredFieldsByType(array $mediaType) {
		$messageTextLimitValidator = new CLimitedSetValidator(array(
			'values' => array(EZ_TEXTING_LIMIT_USA, EZ_TEXTING_LIMIT_CANADA)
		));

		$requiredFieldsByType = array(
			MEDIA_TYPE_EMAIL => array('smtp_server', 'smtp_helo', 'smtp_email'),
			MEDIA_TYPE_EXEC => array('exec_path'),
			MEDIA_TYPE_SMS => array('gsm_modem'),
			MEDIA_TYPE_JABBER => array('username', 'passwd'),
			MEDIA_TYPE_EZ_TEXTING => array('exec_path', 'username', 'passwd'),
			MEDIA_TYPE_REMEDY => array('smtp_server', 'exec_path', 'username', 'passwd')
		);

		foreach ($requiredFieldsByType[$mediaType['type']] as $field) {
			// check if fields set on Create method
			if (!isset($mediaType[$field])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Field "%1$s" required for media type "%2$s".',
					$field,
					$mediaType['description']
				));
			}
			elseif (isset($mediaType[$field]) && zbx_empty($mediaType[$field])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Field "%1$s" is missing a value for media type "%2$s".',
					$field,
					$mediaType['description']
				));
			}
			elseif (isset($mediaType[$field])
					&& $mediaType['type'] == MEDIA_TYPE_EZ_TEXTING
					&& $field == 'exec_path'
					&&!$messageTextLimitValidator->validate($mediaType[$field])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Media type "%1$s" has incorrect value for field "%2$s".',
					$mediaType['description'],
					$field
				));
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'mediatypeid', 'userid', 'media');
			$users = API::User()->get(array(
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $users, 'users');
		}

		// adding media
		if ($options['selectMedia'] !== null && $options['selectMedia'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'mediatypeid', 'mediaid', 'media');
			$media = API::UserMedia()->get(array(
				'output' => $options['selectMedia'],
				'mediaids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $media, 'media');
		}

		return $result;
	}
}
