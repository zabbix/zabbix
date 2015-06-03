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
		$userType = self::$userData['type'];

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
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (is_null($options['editable']) && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif (!is_null($options['editable']) || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
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
	 * Check input.
	 *
	 * @param array  $mediatypes
	 * @param string $method
	 */
	protected function checkInput(array $mediatypes, $method) {
		$create = ($method === 'create');
		$update = ($method === 'update');

		// permissions check
		if ($create) {
			if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create media types.'));
			}

			$mediatype_db_fields = ['type' => null, 'description' => null];
		}
		else {
			if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can edit media types.'));
			}

			$mediatype_db_fields = ['mediatypeid' => null];
		}

		foreach ($mediatypes as $mediatype) {
			if (!check_db_fields($mediatype_db_fields, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for media type.'));
			}

			if ($create) {
				$mediatype_exist = $this->get([
					'filter' => ['description' => $mediatype['description']],
					'output' => ['description']
				]);

				$mediatype_description = $mediatype['description'];
			}
			else {
				if (isset($mediatype['description'])) {
					$existMediatypes = $this->get([
						'filter' => ['description' => $mediatype['description']],
						'preservekeys' => true,
						'output' => ['mediatypeid']
					]);
					$existMediatype = reset($existMediatypes);

					$mediatype_exist =  ($existMediatype
							&& bccomp($existMediatype['mediatypeid'], $mediatype['mediatypeid']) != 0);
					$mediatype_description = $mediatype['description'];
				}
			}

			if ($mediatype_exist) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%s" already exists.',
					$mediatype_description
				));
			}

			if((in_array($mediatype['type'], [MEDIA_TYPE_JABBER, MEDIA_TYPE_EZ_TEXTING])
						|| ($mediatype['type'] == MEDIA_TYPE_EMAIL
						&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL))
					&& (!isset($mediatype['passwd']) || $mediatype['passwd'] === '')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Password required for media type.'));
			}

			if ($mediatype['type'] == MEDIA_TYPE_EMAIL && isset($mediatype['smtp_port'])
					&& !validatePortNumber($mediatype['smtp_port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect media type port "%s" provided.',
					$mediatype['port']
				));
			}
		}
	}

	/**
	 * Add Media types
	 *
	 * @param array $mediatypes
	 * @param int $mediatypes['type']
	 * @param string $mediatypes['description']
	 * @param string $mediatypes['smtp_server']
	 * @param int $mediatypes['smtp_port']
	 * @param string $mediatypes['smtp_helo']
	 * @param string $mediatypes['smtp_email']
	 * @param int $mediatypes['smtp_security']
	 * @param int $mediatypes['smtp_verify_peer']
	 * @param int $mediatypes['smtp_verify_host']
	 * @param int $mediatypes['smtp_authentication']
	 * @param string $mediatypes['exec_path']
	 * @param string $mediatypes['gsm_modem']
	 * @param string $mediatypes['username']
	 * @param string $mediatypes['passwd']
	 * @param int $mediatypes['status']
	 * @return array|boolean
	 */
	public function create($mediatypes) {
		$mediatypes = zbx_toArray($mediatypes);

		$this->checkInput($mediatypes, __FUNCTION__);

		$mediatypeids = DB::insert('media_type', $mediatypes);

		return array('mediatypeids' => $mediatypeids);
	}

	/**
	 * Update Media types
	 *
	 * @param array $mediatypes
	 * @param int $mediatypes['type']
	 * @param string $mediatypes['description']
	 * @param string $mediatypes['smtp_server']
	 * @param int $mediatypes['smtp_port']
	 * @param string $mediatypes['smtp_helo']
	 * @param string $mediatypes['smtp_email']
	 * @param int $mediatypes['smtp_security']
	 * @param int $mediatypes['smtp_verify_peer']
	 * @param int $mediatypes['smtp_verify_host']
	 * @param int $mediatypes['smtp_authentication']
	 * @param string $mediatypes['exec_path']
	 * @param string $mediatypes['gsm_modem']
	 * @param string $mediatypes['username']
	 * @param string $mediatypes['passwd']
	 * @param int $mediatypes['status']
	 * @return array
	 */
	public function update($mediatypes) {
		$mediatypes = zbx_toArray($mediatypes);

		$this->checkInput($mediatypes, __FUNCTION__);

		$update = array();
		foreach ($mediatypes as $mediatype) {
			$mediatypeid = $mediatype['mediatypeid'];
			unset($mediatype['mediatypeid']);

			if (!empty($mediatype)) {
				$update[] = array(
					'values' => $mediatype,
					'where' => array('mediatypeid' => $mediatypeid)
				);
			}
		}

		DB::update('media_type', $update);
		$mediatypeids = zbx_objectValues($mediatypes, 'mediatypeid');

		return array('mediatypeids' => $mediatypeids);
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

		return $result;
	}
}
