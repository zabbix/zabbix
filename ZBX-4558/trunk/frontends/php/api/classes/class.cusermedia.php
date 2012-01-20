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
?>
<?php
/**
 * File containing CUser class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Users
 */
class CUserMedia extends CZBXAPI {

	protected $tableName = 'media';

	protected $tableAlias = 'm';

	/**
	 * Get Users data
	 *
	 * @param array $options
	 * @param array $options['nodeids'] filter by Node IDs
	 * @param array $options['usrgrpids'] filter by UserGroup IDs
	 * @param array $options['userids'] filter by User IDs
	 * @param boolean $options['type'] filter by User type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
	 * @param boolean $options['selectUsrgrps'] extend with UserGroups data for each User
	 * @param boolean $options['getAccess'] extend with access data for each User
	 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
	 * @param string $options['pattern'] filter by Host name containing only give pattern
	 * @param int $options['limit'] output will be limited to given number
	 * @param string $options['sortfield'] output will be sorted by given property [ 'userid', 'alias' ]
	 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$nodeCheck = false;
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('mediaid', 'userid', 'mediatypeid');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('media' => 'm.mediaid'),
			'from'		=> array('media' => 'media m'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'mediaids'					=> null,
			'mediatypeids'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'editable'					=> null,
			'selectUsrgrps'				=> null,
			'selectUsers'				=> null,
			'selectMediatypes'			=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['media']);

			$dbTable = DB::getSchema('media');
			$sqlParts['select']['mediaid'] = 'm.mediaid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'm.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// permission check
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
		}
		elseif (is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)) {
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where']['mug'] = 'm.userid=ug.userid';
			$sqlParts['where'][] = 'ug.usrgrpid IN ('.
										' SELECT uug.usrgrpid'.
											' FROM users_groups uug'.
											' WHERE uug.userid='.self::$userData['userid'].
										' )';
		}
		elseif (!is_null($options['editable']) || (self::$userData['type']!=USER_TYPE_SUPER_ADMIN)) {
			$options['userids'] = self::$userData['userid'];
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);
			$sqlParts['where'][] = DBcondition('m.mediaid', $options['mediaids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('m.mediaid', $nodeids);
			}
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['userid'] = 'u.userid';
			}
			$sqlParts['from']['users'] = 'users u';
			$sqlParts['where'][] = DBcondition('u.userid', $options['userids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['userid'] = 'm.userid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('u.userid', $nodeids);
			}
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['usrgrpid'] = 'ug.usrgrpid';
			}
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = DBcondition('ug.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['mug'] = 'm.userid=ug.userid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['usrgrpid'] = 'ug.usrgrpid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('ug.usrgrpid', $nodeids);
			}
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['mediatypeid'] = 'm.mediatypeid';
			}
			$sqlParts['where'][] = DBcondition('m.mediatypeid', $options['mediatypeids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['mediatypeid'] = 'm.mediatypeid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('m.mediatypeid', $nodeids);
			}
		}

		// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('m.mediaid', $nodeids);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('media m', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			if ($options['search']['passwd']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to search by user password'));
			}
			zbx_db_search('media m', $options, $sqlParts);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['media'] = 'm.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT m.mediaid) as rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'm');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$mediaids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['group'])) {
			$sqlWhere .= ' GROUP BY '.implode(',', $sqlParts['group']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.
					$sqlWhere.
					$sqlGroup.
					$sqlOrder;
		$res = DBselect($sql, $sqlLimit);
		while ($media = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $media;
				}
				else {
					$result = $media['rowscount'];
				}
			}
			else {
				$mediaids[$media['mediaid']] = $media['mediaid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$media['mediaid']] = array('mediaid' => $media['mediaid']);
				}
				else {
					if (!isset($result[$media['mediaid']])) {
						$result[$media['mediaid']]= array();
					}

					// usrgrpids
					if (isset($media['usrgrpid']) && is_null($options['selectUsrgrps'])) {
						if (!isset($result[$media['mediaid']]['usrgrps'])) {
							$result[$media['mediaid']]['usrgrps'] = array();
						}
						$result[$media['mediaid']]['usrgrps'][] = array('usrgrpid' => $media['usrgrpid']);
						unset($media['usrgrpid']);
					}
					$result[$media['mediaid']] += $media;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding usergroups
		if (!is_null($options['selectUsrgrps']) && str_in_array($options['selectUsrgrps'], $subselects_allowed_outputs)) {
			$objParams = array(
				'output' => $options['selectUsrgrps'],
				'userids' => $userids,
				'preservekeys' => true
			);
			$usrgrps = API::UserGroup()->get($objParams);
			foreach ($usrgrps as $usrgrpid => $usrgrp) {
				$uusers = $usrgrp['users'];
				unset($usrgrp['users']);
				foreach ($uusers as $user) {
					$result[$user['userid']]['usrgrps'][] = $usrgrp;
				}
			}
		}

		// TODO:
		// adding users
		if (!is_null($options['selectMedias']) && str_in_array($options['selectMedias'], $subselects_allowed_outputs)) {
		}

		// adding mediatypes
		if (!is_null($options['selectMediatypes']) && str_in_array($options['selectMediatypes'], $subselects_allowed_outputs)) {
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	protected function checkInput(&$medias, $method) {
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

// permissions

		if ($update || $delete) {
			$mediaDBfields = array('mediaid'=> null);
			$dbMedias = $this->get(array(
				'output' => array('mediaid','userid','mediatypeid'),
				'mediaids' => zbx_objectValues($medias, 'mediaid'),
				'editable' => true,
				'preservekeys' => true
			));
		}
		else{
			$mediaDBfields = array('userid'=>null,'mediatypeid'=>null,'sendto'=>null,'period'=>array());
		}

		$alias = array();
		foreach ($medias as $unum => &$media) {
			if (!check_db_fields($mediaDBfields, $media)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for user media "%s".', $media['sendto']));
			}

// PERMISSION CHECK
			if ($create) {
				if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN)
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to create user medias.'));

				$dbMedia = $media;
			}
			elseif ($update) {
				if (!isset($dbMedias[$media['mediaid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to update user media or user media does not exist.'));

				$dbMedia = $dbMedias[$media['mediaid']];

				if (bccomp(self::$userData['userid'], $dbMedia['userid']) != 0) {
					if (USER_TYPE_SUPER_ADMIN != self::$userData['type'])
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to update other users.'));
				}
				else{
					if (USER_TYPE_ZABBIX_ADMIN != self::$userData['type'])
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to update user medias.'));
				}

			}
			else{
				if (!isset($dbMedias[$media['mediaid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions or user media does not exist.'));

				if (bccomp(self::$userData['userid'], $media['userid']) == 0)
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User is not allowed to delete himself.'));

				if ($dbMedias[$media['mediaid']]['alias'] == ZBX_GUEST_USER)
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete %1$s internal user "%2$s", try disabling that user.', S_ZABBIX, ZBX_GUEST_USER));

				continue;
			}


			if (isset($media['period']) && !validate_period($media['period']))
				self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_INCORRECT_TIME_PERIOD);

		}
		unset($media);
	}

/**
 * Add Medias for User
 *
 * @param array $medias
 * @param string $medias['userid']
 * @param string $medias['medias']['mediatypeid']
 * @param string $medias['medias']['address']
 * @param int $medias['medias']['severity']
 * @param int $medias['medias']['active']
 * @param string $medias['medias']['period']
 * @return boolean
 */
	public function create($medias) {
		$medias = zbx_toArray($medias['medias']);
		$mediaids = array();

		$this->checkInput($medias, __FUNCTION__);

		$mediaids = DB::insert('media', $medias);

		return array('mediaids' => $mediaids);
	}

/**
 * Update Medias for User
 *
 * @param array $media_data
 * @param array $media_data['users']
 * @param array $media_data['users']['userid']
 * @param array $media_data['medias']
 * @param string $media_data['medias']['mediatypeid']
 * @param string $media_data['medias']['sendto']
 * @param int $media_data['medias']['severity']
 * @param int $media_data['medias']['active']
 * @param string $media_data['medias']['period']
 * @return boolean
 */
	public function update($medias) {
		$medias = zbx_toArray($medias);

		$this->checkInput($medias, __FUNCTION__);

		$upd_medias = array();
		$del_medias = array();

		$userids = zbx_objectValues($users, 'userid');
		$sql = 'SELECT m.mediaid '.
				' FROM media m '.
				' WHERE '.DBcondition('userid', $userids);
		$result = DBselect($sql);
		while ($media = DBfetch($result)) {
			$del_medias[$media['mediaid']] = $media;
		}

		foreach ($medias as $mnum => $media) {
			if (!isset($media['mediaid'])) continue;

			if (isset($del_medias[$media['mediaid']])) {
				$upd_medias[$media['mediaid']] = $medias[$mnum];
			}

			unset($medias[$mnum]);
			unset($del_medias[$media['mediaid']]);
		}

// DELETE
		if (!empty($del_medias))
			$this->delete($del_medias);

// UPDATE
		$update = array();
		foreach ($upd_medias as $mnum => $media) {
			$update[] = array(
				'values' => $media,
				'where' => array('mediaid' => $media['mediaid'])
			);
		}
		DB::update('media', $update);

// CREATE
		if (!empty($medias))
			$this->create($medias);

		return array('userids'=>$userids);
	}


/**
 * Delete User Medias
 *
 * @param array $mediaids
 * @return boolean
 */
	public function delete($medias) {
		$medias = zbx_toArray($medias);
		$mediaids = zbx_objectValues($medias, 'mediaid');

		$this->checkInput($medias, __FUNCTION__);

		DB::delete('media m', array('mediaid' => $mediaids));

		return array('mediaids'=>$mediaids);
	}


	public function isReadable($ids) {
		if (!is_array($ids)) return false;
		if (empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'userids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable($ids) {
		if (!is_array($ids)) return false;
		if (empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'userids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}
}

?>
