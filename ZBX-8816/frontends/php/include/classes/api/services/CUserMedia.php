<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class containing methods for operations with users media.
 *
 * @package API
 */
class CUserMedia extends CApiService {

	protected $tableName = 'media';
	protected $tableAlias = 'm';
	protected $sortColumns = array('mediaid', 'userid', 'mediatypeid');

	/**
	 * Get users data.
	 *
	 * @param array  $options
	 * @param array  $options['usrgrpids']	filter by UserGroup IDs
	 * @param array  $options['userids']	filter by User IDs
	 * @param bool   $options['type']		filter by User type [USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3]
	 * @param bool   $options['getAccess']	extend with access data for each User
	 * @param bool   $options['count']		output only count of objects in result. (result returned in property 'rowscount')
	 * @param string $options['pattern']	filter by Host name containing only give pattern
	 * @param int    $options['limit']		output will be limited to given number
	 * @param string $options['sortfield']	output will be sorted by given property ['userid', 'alias']
	 * @param string $options['sortorder']	output will be sorted in given order ['ASC', 'DESC']
	 *
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();

		$sqlParts = array(
			'select'	=> array('media' => 'm.mediaid'),
			'from'		=> array('media' => 'media m'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
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
			'output'					=> API_OUTPUT_EXTEND,
			'editable'					=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable'] && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
				$sqlParts['from']['users_groups'] = 'users_groups ug';
				$sqlParts['where']['mug'] = 'm.userid=ug.userid';
				$sqlParts['where'][] = 'ug.usrgrpid IN ('.
					' SELECT uug.usrgrpid'.
					' FROM users_groups uug'.
					' WHERE uug.userid='.self::$userData['userid'].
				')';
			}
			else {
				$sqlParts['from']['users'] = 'users u';
				$sqlParts['where']['mu'] = 'm.userid=u.userid';
				$sqlParts['where'][] = 'u.userid='.self::$userData['userid'];
			}
		}

		// mediaids
		if ($options['mediaids'] !== null) {
			zbx_value2array($options['mediaids']);
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
		}

		// userids
		if ($options['userids'] !== null) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['users'] = 'users u';
			$sqlParts['where'][] = dbConditionInt('u.userid', $options['userids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['userid'] = 'm.userid';
			}
		}

		// usrgrpids
		if ($options['usrgrpids'] !== null) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['mug'] = 'm.userid=ug.userid';

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['usrgrpid'] = 'ug.usrgrpid';
			}
		}

		// mediatypeids
		if ($options['mediatypeids'] !== null) {
			zbx_value2array($options['mediatypeids']);

			$sqlParts['where'][] = dbConditionInt('m.mediatypeid', $options['mediatypeids']);

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['mediatypeid'] = 'm.mediatypeid';
			}
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('media m', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('media m', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		while ($media = DBfetch($res)) {
			if ($options['countOutput'] !== null) {
				if ($options['groupCount'] !== null) {
					$result[] = $media;
				}
				else {
					$result = $media['rowscount'];
				}
			}
			else {
				$result[$media['mediaid']] = $media;
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		// removing keys
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}
}
