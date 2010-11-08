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

	function httptest_status2str($status=null){
		$statuses = array(
			HTTPTEST_STATUS_ACTIVE => S_ACTIVE,
			HTTPTEST_STATUS_DISABLED => S_DISABLED,
		);

		if(is_null($status))
			return $statuses;
		else if(isset($statuses[$status]))
			return $statuses[$status];
		else
			return S_UNKNOWN;
	}

	function httptest_status2style($status){
		$statuses = array(
			HTTPTEST_STATUS_ACTIVE => 'off',
			HTTPTEST_STATUS_DISABLED => 'on',
		);

		if(isset($statuses[$status]))
			return $statuses[$status];
		else
			return 'unknown';
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
