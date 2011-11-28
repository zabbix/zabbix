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
 * @package API
 */
class CAction extends CZBXAPI {
	/**
	 * Get Actions data
	 *
	 * @param _array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['actionids']
	 * @param array $options['applicationids']
	 * @param array $options['status']
	 * @param array $options['editable']
	 * @param array $options['extendoutput']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 * @return array|int item data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('actionid', 'name', 'status');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sql_parts = array(
			'select'	=> array('actions' => 'a.actionid'),
			'from'		=> array('actions' => 'actions a'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'actionids'					=> null,
			'triggerids'				=> null,
			'mediatypeids'				=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'scriptids'					=> null,
			'nopermissions'				=> null,
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
			'selectConditions'			=> null,
			'selectOperations'			=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sql_parts['select']['actions']);

			$dbTable = DB::getSchema('actions');
			$sql_parts['select']['actionid'] = 'a.actionid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 'a.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $user_type || !is_null($options['nopermissions'])) {
		}
		else {
			// conditions are checked here by sql, operations after, by api queries
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			// condition hostgroup
			$sql_parts['where'][] =
				' NOT EXISTS ('.
					' SELECT cc.conditionid'.
					' FROM conditions cc'.
					' WHERE cc.conditiontype='.CONDITION_TYPE_HOST_GROUP.
						' AND cc.actionid=a.actionid'.
						' AND ('.
							' NOT EXISTS('.
								' SELECT rr.id'.
								' FROM rights rr,users_groups ug'.
								' WHERE rr.id='.zbx_dbcast_2bigint('cc.value').
									' AND rr.groupid=ug.usrgrpid'.
									' AND ug.userid='.$userid.
									' AND rr.permission>='.$permission.
							' )'.
							' OR EXISTS('.
								' SELECT rr.id'.
								' FROM rights rr,users_groups ugg'.
								' WHERE rr.id='.zbx_dbcast_2bigint('cc.value').
									' AND rr.groupid=ugg.usrgrpid'.
									' AND ugg.userid='.$userid.
									' AND rr.permission<'.$permission.
							')'.
						')'.
				')';

			// condition host or template
			$sql_parts['where'][] =
				' NOT EXISTS ('.
					' SELECT cc.conditionid'.
					' FROM conditions cc'.
					' WHERE (cc.conditiontype='.CONDITION_TYPE_HOST.' OR cc.conditiontype='.CONDITION_TYPE_HOST_TEMPLATE.')'.
						' AND cc.actionid=a.actionid'.
						' AND ('.
							' NOT EXISTS ('.
								' SELECT hgg.hostid'.
								' FROM hosts_groups hgg,rights r,users_groups ug'.
								' WHERE hgg.hostid='.zbx_dbcast_2bigint('cc.value').
									' AND r.id=hgg.groupid'.
									' AND ug.userid='.$userid.
									' AND r.permission>='.$permission.
									' AND r.groupid=ug.usrgrpid)'.
							' OR EXISTS ('.
								' SELECT hgg.hostid'.
									' FROM hosts_groups hgg,rights rr,users_groups gg'.
									' WHERE hgg.hostid='.zbx_dbcast_2bigint('cc.value').
										' AND rr.id=hgg.groupid'.
										' AND rr.groupid=gg.usrgrpid'.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$permission.')'.
							')'.
				')';

			// condition trigger
			$sql_parts['where'][] =
				' NOT EXISTS ('.
					' SELECT cc.conditionid'.
					' FROM conditions cc'.
					' WHERE cc.conditiontype='.CONDITION_TYPE_TRIGGER.
						' AND cc.actionid=a.actionid'.
						' AND ('.
							' NOT EXISTS ('.
								' SELECT f.triggerid'.
								' FROM functions f,items i,hosts_groups hg,rights r,users_groups ug'.
								' WHERE ug.userid='.$userid.
									' AND r.groupid=ug.usrgrpid'.
									' AND r.permission>='.$permission.
									' AND hg.groupid=r.id'.
									' AND i.hostid=hg.hostid'.
									' AND f.itemid=i.itemid'.
									' AND f.triggerid='.zbx_dbcast_2bigint('cc.value').')'.
							' OR EXISTS ('.
								' SELECT ff.functionid'.
								' FROM functions ff,items ii'.
								' WHERE ff.triggerid='.zbx_dbcast_2bigint('cc.value').
									' AND ii.itemid=ff.itemid'.
									' AND EXISTS ('.
										' SELECT hgg.groupid'.
										' FROM hosts_groups hgg,rights rr,users_groups ugg'.
										' WHERE hgg.hostid=ii.hostid'.
											' AND rr.id=hgg.groupid'.
											' AND rr.groupid=ugg.usrgrpid'.
											' AND ugg.userid='.$userid.
											' AND rr.permission<'.$permission.'))'.
					')'.
				')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// actionids
		if (!is_null($options['actionids'])) {
			zbx_value2array($options['actionids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['actionid'] = 'a.actionid';
			}
			$sql_parts['where'][] = DBcondition('a.actionid', $options['actionids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['groupids'] = 'c.value';
			}
			$sql_parts['from']['conditions'] = 'conditions c';
			$sql_parts['where'][] = DBcondition('c.value', $options['groupids']);
			$sql_parts['where']['c'] = 'c.conditiontype='.CONDITION_TYPE_HOST_GROUP;
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['hostids'] = 'c.value';
			}
			$sql_parts['from']['conditions'] = 'conditions c';
			$sql_parts['where'][] = DBcondition('c.value', $options['hostids']);
			$sql_parts['where']['c'] = 'c.conditiontype='.CONDITION_TYPE_HOST;
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['triggerids'] = 'c.value';
			}
			$sql_parts['from']['conditions'] = 'conditions c';
			$sql_parts['where'][] = DBcondition('c.value', $options['triggerids']);
			$sql_parts['where']['c'] = 'c.conditiontype='.CONDITION_TYPE_TRIGGER;
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['mediatypeid'] = 'om.mediatypeid';
			}
			$sql_parts['from']['opmessage'] = 'opmessage om';
			$sql_parts['from']['operations'] = 'operations o';
			$sql_parts['where'][] = DBcondition('om.mediatypeid', $options['mediatypeids']);
			$sql_parts['where']['ao'] = 'a.actionid=o.actionid';
			$sql_parts['where']['oom'] = 'o.operationid=om.operationid';
		}

		// operation messages
		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['usrgrpid'] = 'omg.usrgrpid';
			}
			$sql_parts['from']['opmessage_grp'] = 'opmessage_grp omg';
			$sql_parts['from']['operations'] = 'operations o';
			$sql_parts['where'][] = DBcondition('omg.usrgrpid', $options['usrgrpids']);
			$sql_parts['where']['ao'] = 'a.actionid=o.actionid';
			$sql_parts['where']['oomg'] = 'o.operationid=omg.operationid';
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['userid'] = 'omu.userid';
			}
			$sql_parts['from']['opmessage_usr'] = 'opmessage_usr omu';
			$sql_parts['from']['operations'] = 'operations o';
			$sql_parts['where'][] = DBcondition('omu.userid', $options['userids']);
			$sql_parts['where']['ao'] = 'a.actionid=o.actionid';
			$sql_parts['where']['oomu'] = 'o.operationid=omu.operationid';
		}

		// operation commands
		// scriptids
		if (!is_null($options['scriptids'])) {
			zbx_value2array($options['scriptids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['scriptid'] = 'oc.scriptid';
			}
			$sql_parts['from']['opmessage_usr'] = 'opcommand oc';
			$sql_parts['from']['operations'] = 'operations o';
			$sql_parts['where'][] = '('.DBcondition('oc.scriptid', $options['scriptids']).' AND oc.type='.ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT.')' ;
			$sql_parts['where']['ao'] = 'a.actionid=o.actionid';
			$sql_parts['where']['ooc'] = 'o.operationid=oc.operationid';
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('actions a', $options, $sql_parts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('actions a', $options, $sql_parts);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sql_parts['select']['actions'] = 'a.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sql_parts['select'] = array('COUNT(DISTINCT a.actionid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'a');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$actionids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if (!empty($sql_parts['select'])) {
			$sql_select .= implode(',', $sql_parts['select']);
		}
		if (!empty($sql_parts['from'])) {
			$sql_from .= implode(',', $sql_parts['from']);
		}
		if (!empty($sql_parts['where'])) {
			$sql_where .= ' AND '.implode(' AND ', $sql_parts['where']);
		}
		if (!empty($sql_parts['order'])) {
			$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
		}
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('a.actionid', $nodeids).
					$sql_where.
					$sql_order;
		$db_res = DBselect($sql, $sql_limit);
		while ($action = DBfetch($db_res)) {
			if ($options['countOutput']) {
				$result = $action['rowscount'];
			}
			else {
				$actionids[$action['actionid']] = $action['actionid'];
				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$action['actionid']] = array('actionid' => $action['actionid']);
				}
				else {
					if (!isset($result[$action['actionid']])) {
						$result[$action['actionid']] = array();
					}
					if (!is_null($options['selectConditions']) && !isset($result[$action['actionid']]['conditions'])) {
						$result[$action['actionid']]['conditions'] = array();
					}
					if (!is_null($options['selectOperations']) && !isset($result[$action['actionid']]['operations'])) {
						$result[$action['actionid']]['operations'] = array();
					}

					$result[$action['actionid']] += $action;

					// return mediatype as array
					if (!empty($action['mediatypeid'])) {
						$result[$action['actionid']]['mediatypeids'][] = $action['mediatypeid'];
					}
					unset($result[$action['actionid']]['mediatypeid']);
				}
			}
		}

		if (USER_TYPE_SUPER_ADMIN == $user_type || !is_null($options['nopermissions'])) {
		}
		else {
			// check hosts, templates
			$hosts = $hostids = array();
			$sql = 'SELECT o.actionid,och.hostid'.
					' FROM operations o,opcommand_hst och'.
					' WHERE o.operationid=och.operationid'.
						' AND och.hostid<>0'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_hosts = DBselect($sql);
			while ($host = DBfetch($db_hosts)) {
				if (!isset($hosts[$host['hostid']])) {
					$hosts[$host['hostid']] = array();
				}
				$hosts[$host['hostid']][$host['actionid']] = $host['actionid'];
				$hostids[$host['hostid']] = $host['hostid'];
			}

			$db_templates = DBselect(
				'SELECT o.actionid,ot.templateid'.
				' FROM operations o,optemplate ot'.
				' WHERE o.operationid=ot.operationid'.
					' AND '.DBcondition('o.actionid', $actionids)
			);
			while ($template = DBfetch($db_templates)) {
				if (!isset($hosts[$template['templateid']])) {
					$hosts[$template['templateid']] = array();
				}
				$hosts[$template['templateid']][$template['actionid']] = $template['actionid'];
				$hostids[$template['templateid']] = $template['templateid'];
			}

			$allowedHosts = API::Host()->get(array(
				'hostids' => $hostids,
				'output' => API_OUTPUT_SHORTEN,
				'editable' => $options['editable'],
				'templated_hosts' => true,
				'preservekeys' => true
			));
			foreach ($hostids as $hostid) {
				if (isset($allowedHosts[$hostid])) {
					continue;
				}
				foreach ($hosts[$hostid] as $actionid) {
					unset($result[$actionid], $actionids[$actionid]);
				}
			}
			unset($allowedHosts);

			// check hostgroups
			$groups = $groupids = array();
			$db_groups = DBselect(
				'SELECT o.actionid,ocg.groupid'.
				' FROM operations o,opcommand_grp ocg'.
				' WHERE o.operationid=ocg.operationid'.
					' AND '.DBcondition('o.actionid', $actionids)
			);
			while ($group = DBfetch($db_groups)) {
				if (!isset($groups[$group['groupid']])) {
					$groups[$group['groupid']] = array();
				}
				$groups[$group['groupid']][$group['actionid']] = $group['actionid'];
				$groupids[$group['groupid']] = $group['groupid'];
			}

			$db_groups = DBselect(
				'SELECT o.actionid,og.groupid'.
				' FROM operations o,opgroup og'.
				' WHERE o.operationid=og.operationid'.
					' AND '.DBcondition('o.actionid', $actionids)
			);
			while ($group = DBfetch($db_groups)) {
				if (!isset($groups[$group['groupid']])) {
					$groups[$group['groupid']] = array();
				}
				$groups[$group['groupid']][$group['actionid']] = $group['actionid'];
				$groupids[$group['groupid']] = $group['groupid'];
			}

			$allowedGroups = API::HostGroup()->get(array(
				'groupids' => $groupids,
				'output' => API_OUTPUT_SHORTEN,
				'editable' => $options['editable'],
				'preservekeys' => true
			));
			foreach ($groupids as $groupid) {
				if (isset($allowedGroups[$groupid])) {
					continue;
				}
				foreach ($groups[$groupid] as $actionid) {
					unset($result[$actionid], $actionids[$actionid]);
				}
			}
			unset($allowedGroups);

			// check scripts
			$scripts = $scriptids = array();
			$db_scripts = DBselect(
				'SELECT o.actionid,oc.scriptid'.
				' FROM operations o,opcommand oc'.
				' WHERE o.operationid=oc.operationid'.
					' AND '.DBcondition('o.actionid', $actionids).
					' AND oc.type='.ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
			);
			while ($script = DBfetch($db_scripts)) {
				if (!isset($scripts[$script['scriptid']])) {
					$scripts[$script['scriptid']] = array();
				}
				$scripts[$script['scriptid']][$script['actionid']] = $script['actionid'];
				$scriptids[$script['scriptid']] = $script['scriptid'];
			}

			$allowedScripts = API::Script()->get(array(
				'scriptids' => $scriptids,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			foreach ($scriptids as $scriptid) {
				if (isset($allowedScripts[$scriptid])) {
					continue;
				}
				foreach ($scripts[$scriptid] as $actionid) {
					unset($result[$actionid], $actionids[$actionid]);
				}
			}
			unset($allowedScripts);

			// check users
			$users = $userids = array();
			$db_users = DBselect(
				'SELECT o.actionid,omu.userid'.
				' FROM operations o,opmessage_usr omu'.
				' WHERE o.operationid=omu.operationid'.
					' AND '.DBcondition('o.actionid', $actionids)
			);
			while ($user = DBfetch($db_users)) {
				if (!isset($users[$user['userid']])) {
					$users[$user['userid']] = array();
				}
				$users[$user['userid']][$user['actionid']] = $user['actionid'];
				$userids[$user['userid']] = $user['userid'];
			}

			$allowed_users = API::User()->get(array(
				'userids' => $userids,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			foreach ($userids as $userid) {
				if (isset($allowed_users[$userid])) {
					continue;
				}
				foreach ($users[$userid] as $actionid) {
					unset($result[$actionid], $actionids[$actionid]);
				}
			}

			// check usergroups
			$usrgrps = $usrgrpids = array();
			$db_usergroups = DBselect(
				'SELECT o.actionid,omg.usrgrpid'.
				' FROM operations o,opmessage_grp omg'.
				' WHERE o.operationid=omg.operationid'.
					' AND '.DBcondition('o.actionid', $actionids)
			);
			while ($usrgrp = DBfetch($db_usergroups)) {
				if (!isset($usrgrps[$usrgrp['usrgrpid']])) {
					$usrgrps[$usrgrp['usrgrpid']] = array();
				}
				$usrgrps[$usrgrp['usrgrpid']][$usrgrp['actionid']] = $usrgrp['actionid'];
				$usrgrpids[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];
			}

			$allowed_usrgrps = API::UserGroup()->get(array(
				'usrgrpids' => $usrgrpids,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));

			foreach ($usrgrpids as $usrgrpid) {
				if (isset($allowed_usrgrps[$usrgrpid])) {
					continue;
				}
				foreach ($usrgrps[$usrgrpid] as $actionid) {
					unset($result[$actionid], $actionids[$actionid]);
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// Adding Conditions
		if (!is_null($options['selectConditions']) && str_in_array($options['selectConditions'], $subselects_allowed_outputs)) {
			$res = DBselect('SELECT c.* FROM conditions c WHERE '.DBcondition('c.actionid', $actionids));
			while ($condition = DBfetch($res)) {
				$result[$condition['actionid']]['conditions'][$condition['conditionid']] = $condition;
			}
		}

		// adding operations
		if (!is_null($options['selectOperations']) && str_in_array($options['selectOperations'], $subselects_allowed_outputs)) {
			$operations = array();
			$operationids = array();
			$res = DBselect(
				'SELECT o.*'.
				' FROM operations o'.
				' WHERE '.DBcondition('o.actionid', $actionids)
			);
			while ($operation = DBfetch($res)) {
				$operation['opconditions'] = array();
				$operations[$operation['operationid']] = $operation;
				$operationids[$operation['operationid']] = $operation['operationid'];
			}

			$res = DBselect('SELECT op.* FROM opconditions op WHERE '.DBcondition('op.operationid', $operationids));
			while ($opcondition = DBfetch($res)) {
				if (!isset($operations[$opcondition['operationid']]['opconditions'])) {
					$operations[$opcondition['operationid']]['opconditions'] = array();
				}
				$operations[$opcondition['operationid']]['opconditions'][] = $opcondition;
			}

			$opmessage = $opcommand = $opgroup = $optemplate = array();
			foreach ($operations as $operationid => $operation) {
				switch ($operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$opmessage[] = $operationid;
						break;
					case OPERATION_TYPE_COMMAND:
						$opcommand[] = $operationid;
						break;
					case OPERATION_TYPE_GROUP_ADD:
					case OPERATION_TYPE_GROUP_REMOVE:
						$opgroup[] = $operationid;
						break;
					case OPERATION_TYPE_TEMPLATE_ADD:
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						$optemplate[] = $operationid;
						break;
					case OPERATION_TYPE_HOST_ADD:
					case OPERATION_TYPE_HOST_REMOVE:
					case OPERATION_TYPE_HOST_ENABLE:
					case OPERATION_TYPE_HOST_DISABLE:
				}
			}

			// get OPERATION_TYPE_MESSAGE data
			if (!empty($opmessage)) {
				$db_opmessages = DBselect(
					'SELECT o.operationid,o.default_msg,o.subject,o.message,o.mediatypeid'.
					' FROM opmessage o'.
					' WHERE '.DBcondition('operationid', $opmessage)
				);
				while ($db_opmessage = DBfetch($db_opmessages)) {
					$operations[$db_opmessage['operationid']]['opmessage_grp'] = array();
					$operations[$db_opmessage['operationid']]['opmessage_usr'] = array();
					$operations[$db_opmessage['operationid']]['opmessage'] = $db_opmessage;
				}

				$db_opmessage_grp = DBselect(
					'SELECT og.operationid,og.usrgrpid'.
					' FROM opmessage_grp og'.
					' WHERE '.DBcondition('operationid', $opmessage)
				);
				while ($opmessage_grp = DBfetch($db_opmessage_grp)) {
					$operations[$opmessage_grp['operationid']]['opmessage_grp'][] = $opmessage_grp;
				}

				$db_opmessage_usr = DBselect(
					'SELECT ou.operationid,ou.userid'.
					' FROM opmessage_usr ou'.
					' WHERE '.DBcondition('operationid', $opmessage)
				);
				while ($opmessage_usr = DBfetch($db_opmessage_usr)) {
					$operations[$opmessage_usr['operationid']]['opmessage_usr'][] = $opmessage_usr;
				}
			}

			// get OPERATION_TYPE_COMMAND data
			if (!empty($opcommand)) {
				$db_opcommands = DBselect(
					'SELECT o.*'.
					' FROM opcommand o'.
					' WHERE '.DBcondition('operationid', $opcommand)
				);
				while ($db_opcommand = DBfetch($db_opcommands)) {
					$operations[$db_opcommand['operationid']]['opcommand_grp'] = array();
					$operations[$db_opcommand['operationid']]['opcommand_hst'] = array();
					$operations[$db_opcommand['operationid']]['opcommand'] = $db_opcommand;
				}

				$db_opcommand_hst = DBselect(
					'SELECT oh.opcommand_hstid,oh.operationid,oh.hostid'.
					' FROM opcommand_hst oh'.
					' WHERE '.DBcondition('operationid', $opcommand)
				);
				while ($opcommand_hst = DBfetch($db_opcommand_hst)) {
					$operations[$opcommand_hst['operationid']]['opcommand_hst'][] = $opcommand_hst;
				}

				$db_opcommand_grp = DBselect(
					'SELECT og.opcommand_grpid,og.operationid,og.groupid'.
					' FROM opcommand_grp og'.
					' WHERE '.DBcondition('operationid', $opcommand)
				);
				while ($opcommand_grp = DBfetch($db_opcommand_grp)) {
					$operations[$opcommand_grp['operationid']]['opcommand_grp'][] = $opcommand_grp;
				}
			}

			// get OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE data
			if (!empty($opgroup)) {
				$db_opgroup = DBselect(
					'SELECT o.operationid,o.groupid'.
					' FROM opgroup o'.
					' WHERE '.DBcondition('operationid', $opgroup)
				);
				while ($opgroup = DBfetch($db_opgroup)) {
					if (!isset($operations[$opgroup['operationid']]['opgroup'])) {
						$operations[$opgroup['operationid']]['opgroup'] = array();
					}
					$operations[$opgroup['operationid']]['opgroup'][] = $opgroup;
				}
			}

			// get OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE data
			if (!empty($optemplate)) {
				$db_optemplate = DBselect(
					'SELECT o.operationid,o.templateid'.
					' FROM optemplate o'.
					' WHERE '.DBcondition('operationid', $optemplate)
				);
				while ($optemplate = DBfetch($db_optemplate)) {
					if (!isset($operations[$optemplate['operationid']]['optemplate'])) {
						$operations[$optemplate['operationid']]['optemplate'] = array();
					}
					$operations[$optemplate['operationid']]['optemplate'][] = $optemplate;
				}
			}
			foreach ($operations as $operation) {
				$result[$operation['actionid']]['operations'][$operation['operationid']] = $operation;
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public function exists($object) {
		$keyFields = array(array('actionid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => true,
			'limit' => 1
		);

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}

		$objs = $this->get($options);
		return !empty($objs);
	}

	/**
	 * Add actions
	 *
	 * @param _array $actions multidimensional array with actions data
	 * @param array $actions[0,...]['expression']
	 * @param array $actions[0,...]['description']
	 * @param array $actions[0,...]['type'] OPTIONAL
	 * @param array $actions[0,...]['priority'] OPTIONAL
	 * @param array $actions[0,...]['status'] OPTIONAL
	 * @param array $actions[0,...]['comments'] OPTIONAL
	 * @param array $actions[0,...]['url'] OPTIONAL
	 * @return boolean
	 */
	public function create($actions) {
		$actions = zbx_toArray($actions);

		// check fields
		$action_db_fields = array(
			'name' => null,
			'eventsource' => null,
			'evaltype' => null
		);
		$duplicates = array();
		foreach ($actions as $action) {
			if (!check_db_fields($action_db_fields, $action)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameter for action "%s".', $action['name']));
			}
			if (isset($action['esc_period']) && $action['esc_period'] < SEC_PER_MIN && $action['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" has incorrect value for "esc_period" (minimum %2%s seconds).', $action['name'], SEC_PER_MIN));
			}
			if (isset($duplicates[$action['name']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $action['name']));
			}
			else {
				$duplicates[$action['name']] = $action['name'];
			}
		}

		$options = array(
			'filter' => array('name' => $duplicates),
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'nopermissions' => true
		);
		$dbActions = $this->get($options);
		foreach ($dbActions as $dbAction) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $dbAction['name']));
		}

		$actionids = DB::insert('actions', $actions);

		$conditions = $operations = array();
		foreach ($actions as $anum => $action) {
			if (isset($action['conditions']) && !empty($action['conditions'])) {
				foreach ($action['conditions'] as $condition) {
					$condition['actionid'] = $actionids[$anum];
					$conditions[] = $condition;
				}
			}

			if (!isset($action['operations']) || empty($action['operations'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameter for action "%s".', $action['name']));
			}
			else {
				foreach ($action['operations'] as $operation) {
					$operation['actionid'] = $actionids[$anum];
					$operations[] = $operation;
				}
			}
		}

		self::validateConditions($conditions);
		$this->addConditions($conditions);

		$this->validateOperations($operations);
		$this->addOperations($operations);

		return array('actionids' => $actionids);
	}

	/**
	 * Update actions
	 *
	 * @param _array $actions multidimensional array with actions data
	 * @param array $actions[0,...]['actionid']
	 * @param array $actions[0,...]['expression']
	 * @param array $actions[0,...]['description']
	 * @param array $actions[0,...]['type'] OPTIONAL
	 * @param array $actions[0,...]['priority'] OPTIONAL
	 * @param array $actions[0,...]['status'] OPTIONAL
	 * @param array $actions[0,...]['comments'] OPTIONAL
	 * @param array $actions[0,...]['url'] OPTIONAL
	 * @return boolean
	 */
	public function update($actions) {
		$actions = zbx_toArray($actions);
		$actionids = zbx_objectValues($actions, 'actionid');
		$update = array();

		$options = array(
			'actionids' => $actionids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'selectOperations' => API_OUTPUT_EXTEND,
			'selectConditions' => API_OUTPUT_EXTEND,
		);
		$updActions = $this->get($options);
			foreach ($actions as $action) {
			if (isset($action['actionid']) && !isset($updActions[$action['actionid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// check fields
		$duplicates = array();
		foreach ($actions as $action) {
			if (!check_db_fields(array('actionid' => null), $action)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameters for action update method "%s".', $action['name']));
			}

			// check if user changed esc_period or eventsource
			if (isset($action['esc_period']) || isset($action['eventsource'])) {
				$eventsource = isset($action['eventsource']) ? $action['eventsource']: $updActions[$action['actionid']]['eventsource'];
				$esc_period = isset($action['esc_period']) ? $action['esc_period']: $updActions[$action['actionid']]['esc_period'];

				if ($esc_period < SEC_PER_MIN && EVENT_SOURCE_TRIGGERS == $eventsource) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" has incorrect value for "esc_period" (minimum 60 seconds).', $action['name']));
				}
			}

			if (!isset($action['name'])) {
				continue;
			}

			if (isset($duplicates[$action['name']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $action['name']));
			}
			else {
				$duplicates[$action['name']] = $action['name'];
			}
		}

		$operationsCreate = $operationsUpdate = $operationidsDelete = array();
		$conditionsCreate = $conditionsUpdate = $conditionidsDelete = array();
		foreach ($actions as $action) {
			if (isset($action['name'])) {
				$options = array(
					'filter' => array('name' => $action['name']),
					'output' => API_OUTPUT_SHORTEN,
					'editable' => true,
					'nopermissions' => true,
					'preservekeys' => true
				);
				$action_exists = $this->get($options);
				if (($action_exist = reset($action_exists)) && (bccomp($action_exist['actionid'], $action['actionid']) != 0)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $action['name']));
				}
			}

			if (isset($action['conditions'])) {
				$conditionsDb = isset($updActions[$action['actionid']]['conditions']) ? $updActions[$action['actionid']]['conditions'] : array();
				self::validateConditions($action['conditions']);

				foreach ($action['conditions'] as $condition) {
					$condition['actionid'] = $action['actionid'];

					if (!isset($condition['conditionid'])) {
						$conditionsCreate[] = $condition;
					}
					elseif (isset($conditionsDb[$condition['conditionid']])) {
						$conditionsUpdate[] = $condition;
						unset($conditionsDb[$condition['conditionid']]);
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action conditionid'));
					}
				}

				$conditionidsDelete = array_merge($conditionidsDelete, array_keys($conditionsDb));
			}

			if (isset($action['operations']) && empty($action['operations'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" no operations defined.', $action['name']));
			}
			elseif (isset($action['operations'])) {
				$this->validateOperations($action['operations']);

				$operations_db = $updActions[$action['actionid']]['operations'];
				foreach ($action['operations'] as $operation) {
					$operation['actionid'] = $action['actionid'];

					if (!isset($operation['operationid'])) {
						$operationsCreate[] = $operation;
					}
					elseif (isset($operations_db[$operation['operationid']])) {
						$operationsUpdate[] = $operation;
						unset($operations_db[$operation['operationid']]);
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operationid'));
					}
				}
				$operationidsDelete = array_merge($operationidsDelete, array_keys($operations_db));
			}

			$actionid = $action['actionid'];
			unset($action['actionid']);
			if (!empty($action)) {
				$update[] = array(
					'values' => $action,
					'where' => array('actionid' => $actionid)
				);
			}
		}

		DB::update('actions', $update);

		$this->addConditions($conditionsCreate);
		$this->updateConditions($conditionsUpdate);
		if (!empty($conditionidsDelete)) {
			$this->deleteConditions($conditionidsDelete);
		}

		$this->addOperations($operationsCreate);
		$this->updateOperations($operationsUpdate, $updActions);
		if (!empty($operationidsDelete)) {
			$this->deleteOperations($operationidsDelete);
		}

		return array('actionids' => $actionids);
	}

	protected function addConditions($conditions) {
		foreach ($conditions as $condition) {
			$condition_db_fields = array(
				'actionid' => null,
				'conditiontype' => null
			);
			if (!check_db_fields($condition_db_fields, $condition)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for condition.'));
			}
		}
		DB::insert('conditions', $conditions);
	}

	protected function updateConditions($conditions) {
		$update = array();
		foreach ($conditions as $condition) {
			$conditionid = $condition['conditionid'];
			unset($condition['conditionid']);
			$update = array(
				'values' => $condition,
				'where' => array('conditionid' => $conditionid)
			);
		}
		DB::update('conditions', $update);
	}

	protected function deleteConditions($conditionids) {
		DB::delete('conditions', array('conditionid' => $conditionids));
	}

	protected function addOperations($operations) {
		foreach ($operations as $operation) {
			$operationDbFields = array(
				'actionid' => null,
				'operationtype' => null
			);
			if (!check_db_fields($operationDbFields, $operation)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameter for operations.'));
			}
		}

		$operationids = DB::insert('operations', $operations);

		$opmessage = $opcommand = $opmessage_grp = $opmessage_usr = $opcommand_hst = $opcommand_grp = $opgroup = $optemplate = array();
		$opcondition_inserts = array();
		foreach ($operations as $onum => $operation) {
			$operationid = $operationids[$onum];

			switch ($operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					if (isset($operation['opmessage']) && !empty($operation['opmessage'])) {
						$operation['opmessage']['operationid'] = $operationid;
						$opmessage[] = $operation['opmessage'];
					}
					if (isset($operation['opmessage_usr'])) {
						foreach ($operation['opmessage_usr'] as $user) {
							$opmessage_usr[] = array(
								'operationid' => $operationid,
								'userid' => $user['userid']
							);
						}
					}
					if (isset($operation['opmessage_grp'])) {
						foreach ($operation['opmessage_grp'] as $usrgrp) {
							$opmessage_grp[] = array(
								'operationid' => $operationid,
								'usrgrpid' => $usrgrp['usrgrpid']
							);
						}
					}
					break;
				case OPERATION_TYPE_COMMAND:
					if (isset($operation['opcommand']) && !empty($operation['opcommand'])) {
						$operation['opcommand']['operationid'] = $operationid;
						$opcommand[] = $operation['opcommand'];
					}
					if (isset($operation['opcommand_hst'])) {
						foreach ($operation['opcommand_hst'] as $hst) {
							$opcommand_hst[] = array(
								'operationid' => $operationid,
								'hostid' => $hst['hostid']
							);
						}
					}
					if (isset($operation['opcommand_grp'])) {
						foreach ($operation['opcommand_grp'] as $grp) {
							$opcommand_grp[] = array(
								'operationid' => $operationid,
								'groupid' => $grp['groupid']
							);
						}
					}
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					foreach ($operation['opgroup'] as $grp) {
						$opgroup[] = array(
							'operationid' => $operationid,
							'groupid' => $grp['groupid']
						);
					}
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					foreach ($operation['optemplate'] as $tpl) {
						$optemplate[] = array(
							'operationid' => $operationid,
							'templateid' => $tpl['templateid']
						);
					}
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
			}
			if (isset($operation['opconditions'])) {
				foreach ($operation['opconditions'] as $opcondition) {
					$opcondition['operationid'] = $operationid;
					$opcondition_inserts[] = $opcondition;
				}
			}
		}
		DB::insert('opconditions', $opcondition_inserts);
		DB::insert('opmessage', $opmessage, false);
		DB::insert('opcommand', $opcommand, false);
		DB::insert('opmessage_grp', $opmessage_grp);
		DB::insert('opmessage_usr', $opmessage_usr);
		DB::insert('opcommand_hst', $opcommand_hst);
		DB::insert('opcommand_grp', $opcommand_grp);
		DB::insert('opgroup', $opgroup);
		DB::insert('optemplate', $optemplate);

		return true;
	}

	protected function updateOperations($operations, $actionsDb) {
		$operationsUpdate = array();

		// messages
		$opmessageCreate = array();
		$opmessageUpdate = array();
		$opmessageDeleteByOpId = array();

		$opmessage_grpCreate = array();
		$opmessage_usrCreate = array();
		$opmessage_grpDeleteByOpId = array();
		$opmessage_usrDeleteByOpId = array();

		// commands
		$opcommandCreate = array();
		$opcommandUpdate = array();
		$opcommandDeleteByOpId = array();

		$opcommand_grpCreate = array();
		$opcommand_hstCreate = array();

		$opcommand_grpDeleteByOpId = array();
		$opcommand_hstDeleteByOpId = array();

		// groups
		$opgroupCreate = array();
		$opgroupDeleteByOpId = array();

		// templates
		$optemplateCreate = array();
		$optemplateDeleteByOpId = array();

		$opconditionsCreate = array();

		foreach ($operations as $operation) {
			$operationDb = $actionsDb[$operation['actionid']]['operations'][$operation['operationid']];

			$type_changed = false;
			if (isset($operation['operationtype']) && ($operation['operationtype'] != $operationDb['operationtype'])) {
				$type_changed = true;

				switch ($operationDb['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$opmessageDeleteByOpId[] = $operationDb['operationid'];
						$opmessage_grpDeleteByOpId[] = $operationDb['operationid'];
						$opmessage_usrDeleteByOpId[] = $operationDb['operationid'];
						break;
					case OPERATION_TYPE_COMMAND:
						$opcommandDeleteByOpId[] = $operationDb['operationid'];
						$opcommand_hstDeleteByOpId[] = $operationDb['operationid'];
						$opcommand_grpDeleteByOpId[] = $operationDb['operationid'];
						break;
					case OPERATION_TYPE_GROUP_ADD:
						if ($operation['operationtype'] == OPERATION_TYPE_GROUP_REMOVE) {
							break;
						}
					case OPERATION_TYPE_GROUP_REMOVE:
						if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD) {
							break;
						}
						$opgroupDeleteByOpId[] = $operationDb['operationid'];
						break;
					case OPERATION_TYPE_TEMPLATE_ADD:
						if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_REMOVE) {
							break;
						}
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD) {
							break;
						}
						$optemplateDeleteByOpId[] = $operationDb['operationid'];
						break;
				}
			}

			if (!isset($operation['operationtype'])) {
				$operation['operationtype'] = $operationDb['operationtype'];
			}

			switch ($operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					if (!isset($operation['opmessage_grp'])) {
						$operation['opmessage_grp'] = array();
					}
					else {
						zbx_array_push($operation['opmessage_grp'], array('operationid' => $operation['operationid']));
					}

					if (!isset($operation['opmessage_usr'])) {
						$operation['opmessage_usr'] = array();
					}
					else {
						zbx_array_push($operation['opmessage_usr'], array('operationid' => $operation['operationid']));
					}

					if (!isset($operationDb['opmessage_usr'])) {
						$operationDb['opmessage_usr'] = array();
					}
					if (!isset($operationDb['opmessage_grp'])) {
						$operationDb['opmessage_grp'] = array();
					}

					if ($type_changed) {
						$operation['opmessage']['operationid'] = $operation['operationid'];
						$opmessageCreate[] = $operation['opmessage'];

						$opmessage_grpCreate = array_merge($opmessage_grpCreate, $operation['opmessage_grp']);
						$opmessage_usrCreate = array_merge($opmessage_usrCreate, $operation['opmessage_usr']);
					}
					else {
						$opmessageUpdate[] = array(
							'values' => $operation['opmessage'],
							'where' => array('operationid'=>$operation['operationid'])
						);

						$diff = zbx_array_diff($operation['opmessage_grp'], $operationDb['opmessage_grp'], 'usrgrpid');
						$opmessage_grpCreate = array_merge($opmessage_grpCreate, $diff['first']);

						foreach ($diff['second'] as $omgrp) {
							DB::delete('opmessage_grp', array(
								'usrgrpid' => $omgrp['usrgrpid'],
								'operationid' => $operation['operationid']
							));
						}

						$diff = zbx_array_diff($operation['opmessage_usr'], $operationDb['opmessage_usr'], 'userid');
						$opmessage_usrCreate = array_merge($opmessage_usrCreate, $diff['first']);
						foreach ($diff['second'] as $omusr) {
							DB::delete('opmessage_usr', array(
								'userid' => $omusr['userid'],
								'operationid' => $operation['operationid']
							));
						}
					}
					break;
				case OPERATION_TYPE_COMMAND:
					if (!isset($operation['opcommand_grp'])) {
						$operation['opcommand_grp'] = array();
					}
					else {
						zbx_array_push($operation['opcommand_grp'], array('operationid' => $operation['operationid']));
					}

					if (!isset($operation['opcommand_hst'])) {
						$operation['opcommand_hst'] = array();
					}
					else {
						zbx_array_push($operation['opcommand_hst'], array('operationid' => $operation['operationid']));
					}

					if (!isset($operationDb['opcommand_grp'])) {
						$operationDb['opcommand_grp'] = array();
					}
					if (!isset($operationDb['opcommand_hst'])) {
						$operationDb['opcommand_hst'] = array();
					}

					if ($type_changed) {
						$operation['opcommand']['operationid'] = $operation['operationid'];
						$opcommandCreate[] = $operation['opcommand'];

						$opcommand_grpCreate = array_merge($opcommand_grpCreate, $operation['opcommand_grp']);
						$opcommand_hstCreate = array_merge($opcommand_hstCreate, $operation['opcommand_hst']);
					}
					else {
						$opcommandUpdate[] = array(
							'values' => $operation['opcommand'],
							'where' => array('operationid' => $operation['operationid'])
							);

						$diff = zbx_array_diff($operation['opcommand_grp'], $operationDb['opcommand_grp'], 'groupid');
						$opcommand_grpCreate = array_merge($opcommand_grpCreate, $diff['first']);

						foreach ($diff['second'] as $omgrp) {
							DB::delete('opcommand_grp', array(
								'groupid' => $omgrp['groupid'],
								'operationid' => $operation['operationid']
							));
						}

						$diff = zbx_array_diff($operation['opcommand_hst'], $operationDb['opcommand_hst'], 'hostid');
						$opcommand_hstCreate = array_merge($opcommand_hstCreate, $diff['first']);
						foreach ($diff['second'] as $omhst) {
							DB::delete('opcommand_hst', array(
								'hostid' => $omhst['hostid'],
								'operationid' => $operation['operationid']
							));
						}
					}
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					if (!isset($operation['opgroup'])) {
						$operation['opgroup'] = array();
					}
					else {
						zbx_array_push($operation['opgroup'], array('operationid' => $operation['operationid']));
					}

					if (!isset($operationDb['opgroup'])) {
						$operationDb['opgroup'] = array();
					}

					$diff = zbx_array_diff($operation['opgroup'], $operationDb['opgroup'], 'groupid');
					$opgroupCreate = array_merge($opgroupCreate, $diff['first']);
					foreach ($diff['second'] as $ogrp) {
						DB::delete('opgroup', array(
							'groupid' => $ogrp['groupid'],
							'operationid' => $operation['operationid']
						));
					}
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					if (!isset($operation['optemplate'])) {
						$operation['optemplate'] = array();
					}
					else {
						zbx_array_push($operation['optemplate'], array('operationid' => $operation['operationid']));
					}

					if (!isset($operationDb['optemplate'])) {
						$operationDb['optemplate'] = array();
					}

					$diff = zbx_array_diff($operation['optemplate'], $operationDb['optemplate'], 'templateid');
					$optemplateCreate = array_merge($optemplateCreate, $diff['first']);

					foreach ($diff['second'] as $otpl) {
						DB::delete('optemplate', array(
							'templateid' => $otpl['templateid'],
							'operationid' => $operation['operationid']
						));
					}
					break;
			}

			if (!isset($operation['opconditions'])) {
				$operation['opconditions'] = array();
			}
			else {
				zbx_array_push($operation['opconditions'], array('operationid' => $operation['operationid']));
			}

			self::validateOperationConditions($operation['opconditions']);

			$diff = zbx_array_diff($operation['opconditions'], $operationDb['opconditions'], 'opconditionid');
			$opconditionsCreate = array_merge($opconditionsCreate, $diff['first']);

			$opconditionsidDelete = zbx_objectValues($diff['second'], 'opconditionid');
			if (!empty($opconditionsidDelete)) {
				DB::delete('opconditions', array('opconditionid' => $opconditionsidDelete));
			}

			$operationid = $operation['operationid'];
			unset($operation['operationid']);
			if (!empty($operation)) {
				$operationsUpdate[] = array(
					'values' => $operation,
					'where' => array('operationid' => $operationid)
				);
			}
		}

		DB::update('operations', $operationsUpdate);

		if (!empty($opmessageDeleteByOpId)) {
			DB::delete('opmessage', array('operationid' => $opmessageDeleteByOpId));
		}
		if (!empty($opcommandDeleteByOpId)) {
			DB::delete('opcommand', array('operationid' => $opcommandDeleteByOpId));
		}
		if (!empty($opmessage_grpDeleteByOpId)) {
			DB::delete('opmessage_grp', array('operationid' => $opmessage_grpDeleteByOpId));
		}
		if (!empty($opmessage_usrDeleteByOpId)) {
			DB::delete('opmessage_usr', array('operationid' => $opmessage_usrDeleteByOpId));
		}
		if (!empty($opcommand_hstDeleteByOpId)) {
			DB::delete('opcommand_hst', array('operationid' => $opcommand_hstDeleteByOpId));
		}
		if (!empty($opcommand_grpDeleteByOpId)) {
			DB::delete('opcommand_grp', array('operationid' => $opcommand_grpDeleteByOpId));
		}
		if (!empty($opcommand_grpDeleteByOpId)) {
			DB::delete('opcommand_grp', array('opcommand_grpid' => $opcommand_grpDeleteByOpId));
		}
		if (!empty($opcommand_hstDeleteByOpId)) {
			DB::delete('opcommand_hst', array('opcommand_hstid' => $opcommand_hstDeleteByOpId));
		}
		if (!empty($opgroupDeleteByOpId)) {
			DB::delete('opgroup', array('operationid' => $opgroupDeleteByOpId));
		}
		if (!empty($optemplateDeleteByOpId)) {
			DB::delete('optemplate', array('operationid' => $optemplateDeleteByOpId));
		}

		DB::insert('opmessage', $opmessageCreate, false);
		DB::insert('opcommand', $opcommandCreate, false);
		DB::insert('opmessage_grp', $opmessage_grpCreate);
		DB::insert('opmessage_usr', $opmessage_usrCreate);
		DB::insert('opcommand_grp', $opcommand_grpCreate);
		DB::insert('opcommand_hst', $opcommand_hstCreate);
		DB::insert('opgroup', $opgroupCreate);
		DB::insert('optemplate', $optemplateCreate);
		DB::update('opmessage', $opmessageUpdate);
		DB::update('opcommand', $opcommandUpdate);
		DB::insert('opconditions', $opconditionsCreate);
	}

	protected function deleteOperations($operationids) {
		DB::delete('operations', array('operationid' => $operationids));
	}

	public function delete($actionids) {
		$actionids = zbx_toArray($actionids);
		if (empty($actionids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$options = array(
			'actionids' => $actionids,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		);
		$delActions = $this->get($options);
		foreach ($actionids as $actionid) {
			if (isset($delActions[$actionid])) {
				continue;
			}
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('actions', array('actionid' => $actionids));
		DB::delete('alerts', array('actionid' => $actionids));

		return array('actionids' => $actionids);
	}

	public function validateOperations($operations) {
		$operations = zbx_toArray($operations);

		foreach ($operations as $operation) {
			if ((isset($operation['esc_step_from']) || isset($operation['esc_step_to'])) && !isset($operation['esc_step_from'], $operation['esc_step_to'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('esc_step_from and esc_step_to must be set together.'));
			}

			if (isset($operation['esc_step_from'], $operation['esc_step_to'])) {
				if ($operation['esc_step_from'] < 1 || $operation['esc_step_to'] < 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation escalation step values.'));
				}

				if ($operation['esc_step_from'] > $operation['esc_step_to'] && $operation['esc_step_to'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation escalation step values.'));
				}
			}

			if (isset($operation['esc_period'])) {
				if (isset($operation['esc_period']) && $operation['esc_period'] != 0 && $operation['esc_period'] < SEC_PER_MIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation escalation period.'));
				}
			}

			$hostIdsAll = $hostGroupIdsAll = $userIdsAll = $userGroupIdsAll = array();
			switch ($operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					$userids = isset($operation['opmessage_usr']) ? zbx_objectValues($operation['opmessage_usr'], 'userid') : array();
					$usergroupids = isset($operation['opmessage_grp']) ? zbx_objectValues($operation['opmessage_grp'], 'usrgrpid') : array();

					if (empty($userids) && empty($usergroupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No recipients for action operation message.'));
					}

					$userIdsAll = array_merge($userIdsAll, $userids);
					$userGroupIdsAll = array_merge($userGroupIdsAll, $usergroupids);
					break;
				case OPERATION_TYPE_COMMAND:
					if (!isset($operation['opcommand']['type'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No command type specified for action operation.'));
					}

					if ((!isset($operation['opcommand']['command']) || zbx_empty(trim($operation['opcommand']['command'])))
							&& $operation['opcommand']['type'] != ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('No command specified for action operation.'));
					}

					switch ($operation['opcommand']['type']) {
						case ZBX_SCRIPT_TYPE_IPMI:
							break;
						case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
							if (!isset($operation['opcommand']['execute_on'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('No execution target specified for action operation command "%s".', $operation['opcommand']['command']));
							}
							break;
						case ZBX_SCRIPT_TYPE_SSH:
							if (!isset($operation['opcommand']['authtype']) || zbx_empty($operation['opcommand']['authtype'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('No authentication type specified for action operation command "%s".', $operation['opcommand']['command']));
							}

							if (!isset($operation['opcommand']['username']) || zbx_empty($operation['opcommand']['username'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('No authentication user name specified for action operation command "%s".', $operation['opcommand']['command']));
							}

							if ($operation['opcommand']['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
								if (!isset($operation['opcommand']['publickey']) || zbx_empty($operation['opcommand']['publickey'])) {
									self::exception(ZBX_API_ERROR_PARAMETERS, _s('No public key file specified for action operation command "%s".', $operation['opcommand']['command']));
								}
								if (!isset($operation['opcommand']['privatekey']) || zbx_empty($operation['opcommand']['privatekey'])) {
									self::exception(ZBX_API_ERROR_PARAMETERS, _s('No private key file specified for action operation command "%s".', $operation['opcommand']['command']));
								}
							}
							break;
						case ZBX_SCRIPT_TYPE_TELNET:
							if (!isset($operation['opcommand']['username']) || zbx_empty($operation['opcommand']['username'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('No authentication user name specified for action operation command "%s".', $operation['opcommand']['command']));
							}
							break;
						case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
							if (!isset($operation['opcommand']['scriptid']) || zbx_empty($operation['opcommand']['scriptid'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _('No script specified for action operation command.'));
							}
							$scripts = API::Script()->get(array(
								'output' => array('scriptid','name'),
								'scriptids' => $operation['opcommand']['scriptid'],
								'preservekeys' => true
							));
							if (!isset($scripts[$operation['opcommand']['scriptid']])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _('Specified script does not exist or you do not have rights on it for action operation command.'));
							}
							break;
						default:
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation command type.'));
					}

					if (isset($operation['opcommand']['port']) && !zbx_empty($operation['opcommand']['port'])) {
						if (zbx_ctype_digit($operation['opcommand']['port'])) {
							if ($operation['opcommand']['port'] > 65535 || $operation['opcommand']['port'] < 1) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action operation port "%s".', $operation['opcommand']['port']));
							}
						}
						elseif (!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $operation['opcommand']['port'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action operation port "%s".', $operation['opcommand']['port']));
						}
					}

					$groupids = array();
					if (isset($operation['opcommand_grp'])) {
						$groupids = zbx_objectValues($operation['opcommand_grp'], 'groupid');
					}

					$hostids = array();
					$without_current = true;
					if (isset($operation['opcommand_hst'])) {
						foreach ($operation['opcommand_hst'] as $hstCommand) {
							if ($hstCommand['hostid'] == 0) {
								$without_current = false;
							}
							else {
								$hostids[$hstCommand['hostid']] = $hstCommand['hostid'];
							}
						}
					}

					if (empty($groupids) && empty($hostids) && $without_current) {
						if ($operation['opcommand']['type'] == ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('You did not specify targets for action operation global script "%s".', $scripts[$operation['opcommand']['scriptid']]['name']));
						}
						else {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('You did not specify targets for action operation command "%s".',$operation['opcommand']['command']));
						}
					}

					$hostIdsAll = array_merge($hostIdsAll, $hostids);
					$hostGroupIdsAll = array_merge($hostGroupIdsAll, $groupids);
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$groupids = isset($operation['opgroup']) ? zbx_objectValues($operation['opgroup'], 'groupid') : array();
					if (empty($groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no group to operate.'));
					}
					$hostGroupIdsAll = array_merge($hostGroupIdsAll, $groupids);
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$templateids = isset($operation['optemplate']) ? zbx_objectValues($operation['optemplate'], 'templateid') : array();
					if (empty($templateids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no template to operate.'));
					}
					$hostIdsAll = array_merge($hostIdsAll, $templateids);
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
					break;
				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation type.'));
			}
		}

		if (!API::HostGroup()->isWritable($hostGroupIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation host group. Host group does not exist or you have no access to this host group.'));
		}
		if (!API::Host()->isWritable($hostIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation host. Host does not exist or you have no access to this host.'));
		}
		if (!API::User()->isReadable($userIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation user. User does not exist or you have no access to this user.'));
		}
		if (!API::UserGroup()->isReadable($userGroupIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation user group. User group does not exist or you have no access to this user group.'));
		}

		return true;
	}

	/**
	 * Validate conditions.
	 *
	 * @static
	 * @param $conditions
	 * @return bool
	 */
	public static function validateConditions($conditions) {
		$conditions = zbx_toArray($conditions);

		$hostGroupIdsAll = array();
		$templateIdsAll = array();
		$triggerIdsAll = array();
		$hostIdsAll = array();
		$discoveryRuleIdsAll = array();
		$discoveryCheckIdsAll = array();
		$proxyIdsAll = array();

		$discoveryCheckTypes = discovery_check_type2str();
		$discoveryObjectStatuses = discovery_object_status2str();

		foreach ($conditions as $condition) {
			switch ($condition['conditiontype']) {
				case CONDITION_TYPE_HOST_GROUP:
					$hostGroupIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_HOST_TEMPLATE:
					$templateIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_TRIGGER:
					$triggerIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_HOST:
					$hostIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_DRULE:
					$discoveryRuleIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_DCHECK:
					$discoveryCheckIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_PROXY:
					$proxyIdsAll[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_TIME_PERIOD:
					if (!validate_period($condition['value'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action condition period "%s".', $condition['value']));
					}
					break;

				case CONDITION_TYPE_DHOST_IP:
					if (!validate_ip_range($condition['value'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action condition ip "%s".', $condition['value']));
					}
					break;

				case CONDITION_TYPE_DSERVICE_TYPE:
					if (!isset($discoveryCheckTypes[$condition['value']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition discovery check.'));
					}
					break;

				case CONDITION_TYPE_DSERVICE_PORT:
					if (!validate_port_list($condition['value'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action condition port "%s".', $condition['value']));
					}
					break;

				case CONDITION_TYPE_DSTATUS:
					if (!isset($discoveryObjectStatuses[$condition['value']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition discovery status.'));
					}
					break;

				case CONDITION_TYPE_MAINTENANCE:
					// maintenance condition has no value...
					break;
				case CONDITION_TYPE_TRIGGER_NAME:
				case CONDITION_TYPE_TRIGGER_VALUE:
				case CONDITION_TYPE_TRIGGER_SEVERITY:
				case CONDITION_TYPE_NODE:
				case CONDITION_TYPE_DOBJECT:
				case CONDITION_TYPE_DUPTIME:
				case CONDITION_TYPE_DVALUE:
				case CONDITION_TYPE_APPLICATION:
				case CONDITION_TYPE_HOST_NAME:
					if (zbx_empty($condition['value'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty action condition.'));
					}
					break;

				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition type.'));
					break;
			}
		}

		if (!API::HostGroup()->isWritable($hostGroupIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition host group. Host group does not exist or you have no access to it.'));
		}
		if (!API::Host()->isWritable($hostIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition host. Host does not exist or you have no access to it.'));
		}
		if (!API::Template()->isWritable($templateIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition template. Template does not exist or you have no access to it.'));
		}
		if (!API::Trigger()->isWritable($triggerIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition trigger. Trigger does not exist or you have no access to it.'));
		}
		if (!API::DRule()->isWritable($discoveryRuleIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition discovery rule. Discovery rule does not exist or you have no access to it.'));
		}
		if (!API::DCheck()->isWritable($discoveryCheckIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition discovery check. Discovery check does not exist or you have no access to it.'));
		}
		if (!API::Proxy()->isWritable($proxyIdsAll)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition proxy. Proxy does not exist or you have no access to it.'));
		}

		return true;
	}

	/**
	 * Validate operation conditions.
	 *
	 * @static
	 * @param $conditions
	 * @return bool
	 */
	public static function validateOperationConditions($conditions) {
		$conditions = zbx_toArray($conditions);
		$ackStatuses = array(
			EVENT_ACKNOWLEDGED => 1,
			EVENT_NOT_ACKNOWLEDGED => 1
		);

		foreach ($conditions as $condition) {
			switch ($condition['conditiontype']) {
				case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					if (!isset($ackStatuses[$condition['value']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation condition acknowledge type.'));
					}
					break;

				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation condition type.'));
					break;
			}
		}

		return true;
	}
}
?>
