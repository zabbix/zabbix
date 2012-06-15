<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/defines.inc.php');
require_once('include/items.inc.php');

	function httptest_authentications($type=null){
		$authentication_types = array(
			HTTPTEST_AUTH_NONE => S_NONE,
			HTTPTEST_AUTH_BASIC => S_BASIC_AUTHENTICATION,
			HTTPTEST_AUTH_NTLM => S_NTLM_AUTHENTICATION,
		);

		if(is_null($type))
			return $authentication_types;
		else if(isset($authentication_types[$type]))
			return $authentication_types[$type];
		else
			return S_UNKNOWN;
	}

	function httptest_status2str($status){
		switch($status){
			case HTTPTEST_STATUS_ACTIVE:	$status = S_ACTIVE;		break;
			case HTTPTEST_STATUS_DISABLED:	$status = S_DISABLED;		break;
			default:
				$status = S_UNKNOWN;		break;
		}

	return $status;
	}

	function httptest_status2style($status){
		switch($status){
			case HTTPTEST_STATUS_ACTIVE:	$status = 'off';	break;
			case HTTPTEST_STATUS_DISABLED:	$status = 'on';		break;
			default:
				$status = 'unknown';	break;
		}
		return $status;
	}

	function db_save_step($hostid, $applicationid, $httptestid, $testname, $name, $no, $timeout, $url, $posts, $required, $status_codes, $delay, $history, $trends, $status){
		if($no <= 0){
			error(S_SCENARIO_STEP_NUMBER_CANNOT_BE_LESS_ONE);
			return false;
		}

//		if(!eregi('^([0-9a-zA-Z\_\.[.-.]\$ ]+)$', $name)){
		if(!preg_match('/'.ZBX_PREG_PARAMS.'/i', $name)){
			error(S_SCENARIO_STEP_NAME_SHOULD_CONTAIN.SPACE.S_PRINTABLE_ONLY);
			return false;
		}

		if(!$httpstep_data = DBfetch(DBselect('select httpstepid from httpstep where httptestid='.$httptestid.' and name='.zbx_dbstr($name)))){

			$httpstepid = get_dbid("httpstep","httpstepid");

			if (!DBexecute('insert into httpstep'.
				' (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) '.
				' values ('.$httpstepid.','.$httptestid.','.zbx_dbstr($name).','.$no.','.
				zbx_dbstr($url).','.$timeout.','.
				zbx_dbstr($posts).','.zbx_dbstr($required).','.zbx_dbstr($status_codes).')'
				)) return false;
		}
		else{
			$httpstepid = $httpstep_data['httpstepid'];

			if (!DBexecute('update httpstep set '.
				' name='.zbx_dbstr($name).', no='.$no.', url='.zbx_dbstr($url).', timeout='.$timeout.','.
				' posts='.zbx_dbstr($posts).', required='.zbx_dbstr($required).', status_codes='.zbx_dbstr($status_codes).
				' where httpstepid='.$httpstepid)) return false;
		}

		$monitored_items = array(
			array(
				'description'	=> sprintf(S_DOWNLOAD_SPEED_FOR_STEP, '$2', '$1'),
				'key_'		=> 'web.test.in['.$testname.','.$name.',bps]',
				'type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'		=> 'Bps',
				'httpstepitemtype'=> HTTPSTEP_ITEM_TYPE_IN),
			array(
				'description'	=> sprintf(S_RESPONSE_TIME_FOR_STEP, '$2', '$1'),
				'key_'		=> 'web.test.time['.$testname.','.$name.',resp]',
				'type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'		=> 's',
				'httpstepitemtype'=> HTTPSTEP_ITEM_TYPE_TIME),
			array(
				'description'	=> sprintf(S_RESPONSE_CODE_FOR_STEP, '$2', '$1'),
				'key_'		=> 'web.test.rspcode['.$testname.','.$name.']',
				'type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'		=> '',
				'httpstepitemtype'=> HTTPSTEP_ITEM_TYPE_RSPCODE),
			);

		foreach($monitored_items as $item){
			$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.delta,i.valuemapid '.
				' from items i, httpstepitem hi '.
				' where hi.httpstepid='.$httpstepid.' and hi.itemid=i.itemid '.
				' and hi.type='.$item['httpstepitemtype']));

			if(!$item_data){
				$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.delta,i.valuemapid '.
					' from items i where i.key_='.zbx_dbstr($item['key_']).' and i.hostid='.$hostid));
			}

			$item_args = array(
				'description'			=> $item['description'],
				'key_'					=> $item['key_'],
				'hostid'				=> $hostid,
				'delay'					=> $delay,
				'type'					=> ITEM_TYPE_HTTPTEST,
				'snmp_community'		=>	'',
				'snmp_oid'				=> '',
				'value_type'			=> $item['type'],
				'data_type'				=> ITEM_DATA_TYPE_DECIMAL,
				'trapper_hosts'			=> 'localhost',
				'snmp_port'				=> 161,
				'units'					=> $item['units'],
				'multiplier'			=> 0,
				'snmpv3_securityname'	=> '',
				'snmpv3_securitylevel'	=> 0,
				'snmpv3_authpassphrase'	=> '',
				'snmpv3_privpassphrase'	=> '',
				'formula'				=> 0,
				'logtimefmt'			=> '',
				'delay_flex'			=> '',
				'authtype'				=> 0,
				'username'				=> '',
				'password'				=> '',
				'publickey'				=> '',
				'privatekey'			=> '',
				'params'				=> '',
				'ipmi_sensor'			=> '',
				'applications'			=> array($applicationid),
				'status' 				=> ($status == HTTPTEST_STATUS_ACTIVE) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED); // transform httptest status to item status

			if(!$item_data){
				$item_args['history'] = $history;
				$item_args['delta'] = 0;
				$item_args['trends'] = $trends;
				$item_args['valuemapid'] = 0;

				if(!$itemid = add_item($item_args)){
					return false;
				}
			}
			else{
				$itemid = $item_data['itemid'];

				$item_args['history'] = $item_data['history'];
				$item_args['delta'] = $item_data['delta'];
				$item_args['trends'] = $item_data['trends'];
				$item_args['valuemapid'] = $item_data['valuemapid'];

				if(!update_item($itemid, $item_args)){
					return false;
				}
			}

			$httpstepitemid = get_dbid('httpstepitem', 'httpstepitemid');

			DBexecute('delete from httpstepitem where itemid='.$itemid);

			if (!DBexecute('insert into httpstepitem'.
				' (httpstepitemid, httpstepid, itemid, type) '.
				' values ('.$httpstepitemid.','.$httpstepid.','.$itemid.','.$item['httpstepitemtype'].')'
				)) return false;

		}

		return $httpstepid;
	}

	function db_save_httptest($httptestid, $hostid, $application, $name, $authentication, $http_user, $http_password, $delay, $status, $agent, $macros, $steps){
		$history = 30; // TODO !!! Allow user to set this parameter
		$trends = 90; // TODO !!! Allow user to set this parameter

		if(!preg_match('/^(['.ZBX_PREG_PRINT.'])+$/u', $name)){
			error(S_ONLY_CHARACTERS_ARE_ALLOWED);
			return false;
		}

		DBstart();

		try{

			$sql = 'SELECT t.httptestid'.
					' FROM httptest t, applications a'.
					' WHERE t.applicationid=a.applicationid'.
						' AND a.hostid='.$hostid.
						' AND t.name='.zbx_dbstr($name);
			$t = DBfetch(DBselect($sql));
			if((isset($httptestid) && $t && ($t['httptestid'] != $httptestid)) || ($t && !isset($httptestid))){
				throw new Exception(S_SCENARIO_WITH_NAME.' [ '.$name.' ] '.S_ALREADY_EXISTS_SMALL);
			}


			$sql = 'SELECT applicationid FROM applications WHERE name='.zbx_dbstr($application).' AND hostid='.$hostid;
			if($applicationid = DBfetch(DBselect($sql))){
				$applicationid = $applicationid['applicationid'];
			}
			else{
				$result = CApplication::create(array('name' => $application, 'hostid' => $hostid));
				if(!$result){
					throw new Exception(S_CANNOT_ADD_NEW_APPLICATION.' [ '.$application.' ]');
				}
				else{
					$applicationid = reset($result['applicationids']);
				}
			}

			if(isset($httptestid)){
				$sql = 'UPDATE httptest SET '.
					' applicationid='.$applicationid.', '.
					' name='.zbx_dbstr($name).', '.
					' authentication='.$authentication.', '.
					' http_user='.zbx_dbstr($http_user).', '.
					' http_password='.zbx_dbstr($http_password).', '.
					' delay='.$delay.', '.
					' status='.$status.', '.
					' agent='.zbx_dbstr($agent).', '.
					' macros='.zbx_dbstr($macros).', '.
					' error='.zbx_dbstr('').', '.
					' curstate='.HTTPTEST_STATE_UNKNOWN.
				' WHERE httptestid='.$httptestid;
				if(!DBexecute($sql)){
					throw new Exception('DBerror');
				}
			}
			else{
				$httptestid = get_dbid('httptest', 'httptestid');

				$values = array(
					'httptestid' => $httptestid,
					'applicationid' => $applicationid,
					'name' => zbx_dbstr($name),
					'authentication' => $authentication,
					'http_user' => zbx_dbstr($http_user),
					'http_password' => zbx_dbstr($http_password),
					'delay' => $delay,
					'status' => $status,
					'agent' => zbx_dbstr($agent),
					'macros' => zbx_dbstr($macros),
					'curstate' => HTTPTEST_STATE_UNKNOWN,
				);
				$sql = 'INSERT INTO httptest ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
				if(!DBexecute($sql)){
					throw new Exception('DBerror');
				}
			}

			$httpstepids = array();
			foreach($steps as $sid => $s){
				if(!isset($s['name']))		$s['name'] = '';
				if(!isset($s['timeout']))	$s['timeout'] = 15;
				if(!isset($s['url']))		$s['url'] = '';
				if(!isset($s['posts']))		$s['posts'] = '';
				if(!isset($s['required']))	$s['required'] = '';
				if(!isset($s['status_codes']))	$s['status_codes'] = '';

				$result = db_save_step($hostid, $applicationid, $httptestid, $name, $s['name'], $sid+1, $s['timeout'],
					$s['url'], $s['posts'], $s['required'],$s['status_codes'], $delay, $history, $trends, $status);

				if(!$result){
					throw new Exception('Cannot create web step');
				}

				$httpstepids[$result] = $result;
			}

/* clean unneeded steps */
			$sql = 'SELECT httpstepid FROM httpstep WHERE httptestid='.$httptestid;
			$db_steps = DBselect($sql);
			while($step_data = DBfetch($db_steps)){
				if(!isset($httpstepids[$step_data['httpstepid']])){
					delete_httpstep($step_data['httpstepid']);
				}
			}

			$monitored_items = array(
				array(
					'description'	=> 'Download speed for scenario \'$1\'',
					'key_'		=> 'web.test.in['.$name.',,bps]',
					'type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'		=> 'Bps',
					'httptestitemtype'=> HTTPSTEP_ITEM_TYPE_IN),
				array(
					'description'	=> 'Failed step of scenario \'$1\'',
					'key_'		=> 'web.test.fail['.$name.']',
					'type'		=> ITEM_VALUE_TYPE_UINT64,
					'units'		=> '',
					'httptestitemtype'=> HTTPSTEP_ITEM_TYPE_LASTSTEP)
			);

			foreach($monitored_items as $item){
				$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.delta,i.valuemapid '.
					' from items i, httptestitem hi '.
					' where hi.httptestid='.$httptestid.' and hi.itemid=i.itemid '.
					' and hi.type='.$item['httptestitemtype']));

				if(!$item_data){
					$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.delta,i.valuemapid '.
						' from items i where i.key_='.zbx_dbstr($item['key_']).' and i.hostid='.$hostid));
				}

				$item_args = array(
					'description'			=> $item['description'],
					'key_'					=> $item['key_'],
					'hostid'				=> $hostid,
					'delay'					=> $delay,
					'type'					=> ITEM_TYPE_HTTPTEST,
					'snmp_community'		=> '',
					'snmp_oid'				=> '',
					'value_type'			=> $item['type'],
					'data_type'				=> ITEM_DATA_TYPE_DECIMAL,
					'trapper_hosts'			=> 'localhost',
					'snmp_port'				=> 161,
					'units'					=> $item['units'],
					'multiplier'			=> 0,
					'snmpv3_securityname'	=> '',
					'snmpv3_securitylevel'	=> 0,
					'snmpv3_authpassphrase'	=> '',
					'snmpv3_privpassphrase'	=> '',
					'formula'				=> 0,
					'logtimefmt'			=> '',
					'delay_flex'			=> '',
					'authtype'				=> 0,
					'username'				=> '',
					'password'				=> '',
					'publickey'				=> '',
					'privatekey'			=> '',
					'params'				=> '',
					'ipmi_sensor'			=> '',
					'applications'			=> array($applicationid),
					'status' 				=> ($status == HTTPTEST_STATUS_ACTIVE) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED); // transform httptest status to item status

				if(!$item_data){
					$item_args['history'] = $history;
					$item_args['delta'] = 0;
					$item_args['trends'] = $trends;
					$item_args['valuemapid'] = 0;

					if(!$itemid = add_item($item_args)){
						throw new Exception('Cannot add item');
					}
				}
				else{
					$itemid = $item_data['itemid'];

					$item_args['history'] = $item_data['history'];
					$item_args['delta'] = $item_data['delta'];
					$item_args['trends'] = $item_data['trends'];
					$item_args['valuemapid'] = $item_data['valuemapid'];

					if(!update_item($itemid, $item_args)){
						throw new Exception('Cannot update item');
					}
				}

				$httptestitemid = get_dbid('httptestitem', 'httptestitemid');

				DBexecute('delete from httptestitem where itemid='.$itemid);

				if(!DBexecute('insert into httptestitem (httptestitemid, httptestid, itemid, type) '.
					' values ('.$httptestitemid.','.$httptestid.','.$itemid.','.$item['httptestitemtype'].')')){
					throw new Exception('DBerror');
				}
			}

			return DBend(true);
		}
		catch(Exception $e){
			error($e->getMessage());
			return DBend(false);
		}
	}

	function add_httptest($hostid, $application, $name, $authentication, $http_user, $http_password, $delay, $status, $agent, $macros, $steps){
		$result = db_save_httptest(null, $hostid, $application, $name, $authentication, $http_user, $http_password, $delay, $status, $agent, $macros, $steps);

		if($result) info(S_SCENARIO.SPACE.'"'.$name.'"'.SPACE.S_ADDED_SMALL);

	return $result;
	}

	function update_httptest($httptestid, $hostid, $application, $name, $authentication, $http_user, $http_password, $delay, $status, $agent, $macros, $steps){
		$result = db_save_httptest($httptestid, $hostid, $application, $name, $authentication, $http_user, $http_password, $delay, $status, $agent, $macros, $steps);

		if($result)	info(S_SCENARIO.SPACE.'"'.$name.'"'.SPACE.S_UPDATED_SMALL);

	return $result;
	}

	function delete_httpstep($httpstepids){
		zbx_value2array($httpstepids);

		$db_httpstepitems = DBselect('SELECT DISTINCT * FROM httpstepitem WHERE '.DBcondition('httpstepid',$httpstepids));
		while($httpstepitem_data = DBfetch($db_httpstepitems)){
			if(!DBexecute('DELETE FROM httpstepitem WHERE httpstepitemid='.$httpstepitem_data['httpstepitemid'])) return false;
			if(!delete_item($httpstepitem_data['itemid'])) return false;
		}

	return DBexecute('DELETE FROM httpstep WHERE '.DBcondition('httpstepid',$httpstepids));
	}

	function delete_httptest($httptestids){
		zbx_value2array($httptestids);

		$httptests = array();
		foreach($httptestids as $id => $httptestid){
			$httptests[$httptestid] =  DBfetch(DBselect('SELECT * FROM httptest WHERE httptestid='.$httptestid));
		}

		$db_httpstep = DBselect('SELECT DISTINCT s.httpstepid '.
								' FROM httpstep s '.
								' WHERE '.DBcondition('s.httptestid',$httptestids));
		$del_httpsteps = array();
		while($httpstep_data = DBfetch($db_httpstep)){
			$del_httpsteps[$httpstep_data['httpstepid']] = $httpstep_data['httpstepid'];
		}
		delete_httpstep($del_httpsteps);

		$httptestitemids_del = array();
		$itemids_del = array();
		$sql = 'SELECT httptestitemid, itemid '.
				' FROM httptestitem '.
				' WHERE '.DBcondition('httptestid',$httptestids);

		$db_httptestitems = DBselect($sql);
		while($httptestitem_data = DBfetch($db_httptestitems)){
			$httptestitemids_del[$httptestitem_data['httptestitemid']] = $httptestitem_data['httptestitemid'];
			$itemids_del[$httptestitem_data['itemid']] = $httptestitem_data['itemid'];
		}

		if(!DBexecute('DELETE FROM httptestitem WHERE '.DBcondition('httptestitemid',$httptestitemids_del))) return false;
		if (!delete_item($itemids_del)) return false;

		if(!DBexecute('DELETE FROM httptest WHERE '.DBcondition('httptestid',$httptestids))) return false;

		foreach($httptests as $id => $httptest){
			info(S_SCENARIO.SPACE."'".$httptest["name"]."'".SPACE.S_DELETED_SMALL);
		}

	return true;
	}

	function activate_httptest($httptestid){
		$r = DBexecute('UPDATE httptest SET status='.HTTPTEST_STATUS_ACTIVE.' WHERE httptestid='.$httptestid);

		$itemids = array();
		$sql = 'SELECT hti.itemid FROM httptestitem hti WHERE hti.httptestid='.$httptestid;
		$items_db = DBselect($sql);
		while($itemid = Dbfetch($items_db)){
			$itemids[] = $itemid['itemid'];
		}

		$sql = 'SELECT hsi.itemid FROM httpstep hs, httpstepitem hsi WHERE '.
			' hs.httptestid='.$httptestid.' AND hs.httpstepid=hsi.httpstepid';
		$items_db = DBselect($sql);
		while($itemid = Dbfetch($items_db)){
			$itemids[] = $itemid['itemid'];
		}

		$sql = 'UPDATE items SET status='.ITEM_STATUS_ACTIVE.' WHERE '.DBcondition('itemid', $itemids);
		$r &= DBexecute($sql);
		return $r;
	}

	function disable_httptest($httptestid){
		$r = DBexecute('UPDATE httptest SET status='.HTTPTEST_STATUS_DISABLED.' WHERE httptestid='.$httptestid);

		$itemids = array();
		$sql = 'SELECT hti.itemid FROM httptestitem hti WHERE hti.httptestid='.$httptestid;
		$items_db = DBselect($sql);
		while($itemid = Dbfetch($items_db)){
			$itemids[] = $itemid['itemid'];
		}

		$sql = 'SELECT hsi.itemid FROM httpstep hs, httpstepitem hsi WHERE '.
			' hs.httptestid='.$httptestid.' AND hs.httpstepid=hsi.httpstepid';
		$items_db = DBselect($sql);
		while($itemid = Dbfetch($items_db)){
			$itemids[] = $itemid['itemid'];
		}

		$sql = 'UPDATE items SET status='.ITEM_STATUS_DISABLED.' WHERE '.DBcondition('itemid', $itemids);
		$r &= DBexecute($sql);
		return $r;
	}

	function delete_history_by_httptestid($httptestid){
		$db_items = DBselect('SELECT DISTINCT i.itemid '.
							' FROM items i, httpstepitem si, httpstep s '.
							' WHERE s.httptestid='.$httptestid.
								' AND si.httpstepid=s.httpstepid '.
								' AND i.itemid=si.itemid');
		while($item_data = DBfetch($db_items)){
			if(!delete_history_by_itemid($item_data['itemid'], 0 /* use housekeeper */)) return false;
		}
		return true;
	}

	function get_httptest_by_httptestid($httptestid){
		return DBfetch(DBselect('select * from httptest where httptestid='.$httptestid));
	}

	function get_httpstep_by_no($httptestid, $no){
		return DBfetch(DBselect('select * from httpstep where httptestid='.$httptestid.' and no='.$no));
	}

	function get_httptests_by_hostid($hostids){
		zbx_value2array($hostids);
		$sql = 'SELECT DISTINCT ht.* '.
				' FROM httptest ht, applications ap '.
				' WHERE '.DBcondition('ap.hostid',$hostids).
					' AND ht.applicationid=ap.applicationid';
	return DBselect($sql);
	}
?>
