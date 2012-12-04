<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * File containing CMediatype class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Media types
 */
class CMediatype extends CZBXAPI {

	protected $tableName = 'media_type';
	protected $tableAlias = 'mt';

	/**
	 * Get Media types data
	 *
	 * @param array $options
	 * @param array $options['nodeids'] filter by Node IDs
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
		$nodeCheck = false;
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('mediatypeid');

		$sqlParts = array(
			'select'	=> array('media_type' => 'mt.mediatypeid'),
			'from'		=> array('media_type' => 'media_type mt'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
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
			'output'					=> API_OUTPUT_REFER,
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

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			$sqlParts['where'][] = DBcondition('mt.mediatypeid', $options['mediatypeids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('mt.mediatypeid', $nodeids);
			}
		}

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);
			$sqlParts['select']['mediaid'] = 'm.mediaid';
			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = DBcondition('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('m.mediaid', $nodeids);
			}
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);
			$sqlParts['select']['userid'] = 'm.userid';
			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = DBcondition('m.userid', $options['userids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('m.userid', $nodeids);
			}
		}

		// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('mt.mediatypeid', $nodeids);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('media_type mt', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('media_type mt', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'mt');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$mediatypeids = array();
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
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
				$mediatypeids[$mediatype['mediatypeid']] = $mediatype['mediatypeid'];

				if (!isset($result[$mediatype['mediatypeid']])) {
					$result[$mediatype['mediatypeid']] = array();
				}

				// userids
				if (isset($mediatype['userid']) && is_null($options['selectUsers'])) {
					if (!isset($result[$mediatype['mediatypeid']]['users'])) {
						$result[$mediatype['mediatypeid']]['users'] = array();
					}
					$result[$mediatype['mediatypeid']]['users'][] = array('userid' => $mediatype['userid']);
					unset($mediatype['userid']);
				}
				$result[$mediatype['mediatypeid']] += $mediatype;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
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

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Add Media types
	 *
	 * @param array $mediatypes
	 * @param string $mediatypes['type']
	 * @param string $mediatypes['description']
	 * @param string $mediatypes['smtp_server']
	 * @param string $mediatypes['smtp_helo']
	 * @param string $mediatypes['smtp_email']
	 * @param string $mediatypes['exec_path']
	 * @param string $mediatypes['gsm_modem']
	 * @param string $mediatypes['username']
	 * @param string $mediatypes['passwd']
	 * @param integer $mediatypes['status']
	 * @return array|boolean
	 */
	public function create($mediatypes) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create media types.'));
		}

		$mediatypes = zbx_toArray($mediatypes);

		foreach ($mediatypes as $mediatype) {
			$mediatypeDbFields = array(
				'type' => null,
				'description' => null
			);
			if (!check_db_fields($mediatypeDbFields, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for media type.'));
			}

			if (in_array($mediatype['type'], array(MEDIA_TYPE_JABBER, MEDIA_TYPE_EZ_TEXTING))
					&& (!isset($mediatype['passwd']) || empty($mediatype['passwd']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Password required for media type.'));
			}

			$mediatypeExist = $this->get(array(
				'filter' => array('description' => $mediatype['description']),
				'output' => API_OUTPUT_EXTEND
			));
			if (!empty($mediatypeExist)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%s" already exists.', $mediatypeExist[0]['description']));
			}
		}

		$mediatypeids = DB::insert('media_type', $mediatypes);

		return array('mediatypeids' => $mediatypeids);
	}

	/**
	 * Update Media types
	 *
	 * @param array $mediatypes
	 * @param string $mediatypes['type']
	 * @param string $mediatypes['description']
	 * @param string $mediatypes['smtp_server']
	 * @param string $mediatypes['smtp_helo']
	 * @param string $mediatypes['smtp_email']
	 * @param string $mediatypes['exec_path']
	 * @param string $mediatypes['gsm_modem']
	 * @param string $mediatypes['username']
	 * @param string $mediatypes['passwd']
	 * @param integer $mediatypes['status']
	 * @return array
	 */
	public function update($mediatypes) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can edit media types.'));
		}

		$mediatypes = zbx_toArray($mediatypes);

		$update = array();
		foreach ($mediatypes as $mediatype) {
			$mediatypeDbFields = array(
				'mediatypeid' => null
			);
			if (!check_db_fields($mediatypeDbFields, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for media type.'));
			}

			if (isset($mediatype['description'])) {
				$options = array(
					'filter' => array('description' => $mediatype['description']),
					'preservekeys' => true,
					'output' => array('mediatypeid')
				);
				$existMediatypes = $this->get($options);
				$existMediatype = reset($existMediatypes);

				if ($existMediatype && bccomp($existMediatype['mediatypeid'], $mediatype['mediatypeid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%s" already exists.', $mediatype['description']));
				}
			}

			if (array_key_exists('passwd', $mediatype) && empty($mediatype['passwd'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Password required for media type.'));
			}

			if (array_key_exists('type', $mediatype) && !in_array($mediatype['type'], array(MEDIA_TYPE_JABBER, MEDIA_TYPE_EZ_TEXTING))) {
				$mediatype['passwd'] = '';
			}

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
	 * @return boolean
	 */
	public function delete($mediatypeids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete media types.'));
		}

		$mediatypeids = zbx_toArray($mediatypeids);

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
}
