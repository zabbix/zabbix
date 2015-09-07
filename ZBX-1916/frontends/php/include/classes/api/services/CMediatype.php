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
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
		}
		elseif (is_null($options['editable']) && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif (!is_null($options['editable']) || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
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

		$mediatype_db_fields = ['type' => null, 'description' => null];

		foreach ($mediatypes as $mediatype) {
			if (!check_db_fields($mediatype_db_fields, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for media type.'));
			}

			$mediatype_exists = API::getApiService()->select('media_type', [
				'output' => ['description'],
				'filter' => ['description' => $mediatype['description']]
			]);

			if ($mediatype_exists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" already exists.',
					$mediatype['description']
				));
			}

			if (($mediatype['type'] == MEDIA_TYPE_JABBER || $mediatype['type'] == MEDIA_TYPE_EZ_TEXTING
						|| ($mediatype['type'] == MEDIA_TYPE_EMAIL
						&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL))
					&& (!array_key_exists('passwd', $mediatype) || $mediatype['passwd'] === '')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Password required for media type.'));
			}

			if ($mediatype['type'] == MEDIA_TYPE_EMAIL && array_key_exists('smtp_port', $mediatype)
					&& !validatePortNumber($mediatype['smtp_port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect media type port "%1$s" provided.',
					$mediatype['smtp_port']
				));
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

		$mediatype_db_fields = ['mediatypeid' => null];

		foreach ($mediatypes as $mediatype) {
			if (!check_db_fields($mediatype_db_fields, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for media type.'));
			}

			if (array_key_exists('description', $mediatype)) {
				$existMediatypes = API::getApiService()->select('media_type', [
					'output' => ['mediatypeid'],
					'filter' => ['description' => $mediatype['description']],
					'preservekeys' => true
				]);

				$existMediatype = reset($existMediatypes);
			}

			if ($existMediatype && bccomp($existMediatype['mediatypeid'], $mediatype['mediatypeid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" already exists.',
					$mediatype['description']
				));
			}

			if (($mediatype['type'] == MEDIA_TYPE_JABBER || $mediatype['type'] == MEDIA_TYPE_EZ_TEXTING
						|| ($mediatype['type'] == MEDIA_TYPE_EMAIL
						&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL))
					&& (!array_key_exists('passwd', $mediatype) || $mediatype['passwd'] === '')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Password required for media type.'));
			}

			if ($mediatype['type'] == MEDIA_TYPE_EMAIL && array_key_exists('smtp_port', $mediatype)
					&& !validatePortNumber($mediatype['smtp_port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect media type port "%1$s" provided.',
					$mediatype['smtp_port']
				));
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
	 * @param string	$mediatypes['gsm_modem']			GSM modem
	 * @param string	$mediatypes['username']				username
	 * @param string	$mediatypes['passwd']				password
	 * @param int		$mediatypes['status']				media type status
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
	 * @param string	$mediatypes['gsm_modem']			GSM modem
	 * @param string	$mediatypes['username']				username
	 * @param string	$mediatypes['passwd']				password
	 * @param int		$mediatypes['status']				media type status
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
