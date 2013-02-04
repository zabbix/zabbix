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
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('mediaid', 'userid', 'mediatypeid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('media' => 'm.mediaid'),
			'from'		=> array('media' => 'media m'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
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
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

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
		if (USER_TYPE_SUPER_ADMIN == $userType) {
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
		elseif (!is_null($options['editable']) || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$options['userids'] = self::$userData['userid'];
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('m.mediaid', $nodeids);
			}
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['select']['userid'] = 'u.userid';
			$sqlParts['from']['users'] = 'users u';
			$sqlParts['where'][] = dbConditionInt('u.userid', $options['userids']);
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

			$sqlParts['select']['usrgrpid'] = 'ug.usrgrpid';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.usrgrpid', $options['usrgrpids']);
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

			$sqlParts['select']['mediatypeid'] = 'm.mediatypeid';
			$sqlParts['where'][] = dbConditionInt('m.mediatypeid', $options['mediatypeids']);

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
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to search by user password.'));
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
			$sqlParts['select'] = array('COUNT(DISTINCT m.mediaid) AS rowscount');

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

				if (!isset($result[$media['mediaid']])) {
					$result[$media['mediaid']]= array();
				}
				$result[$media['mediaid']] += $media;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}
}
