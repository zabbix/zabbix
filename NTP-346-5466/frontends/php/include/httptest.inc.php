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
	require_once('include/defines.inc.php');
	require_once('include/items.inc.php');
?>
<?php
	function	httptest_status2str($status)
	{
		switch($status)
		{
			case HTTPTEST_STATUS_ACTIVE:	$status = S_ACTIVE;		break;
			case HTTPTEST_STATUS_DISABLED:	$status = S_DISABLED;		break;
			default:
				$status = S_UNKNOWN;		break;
		}
		return $status;
	}
	
	function	httptest_status2style($status)
	{
		switch($status)
		{
			case HTTPTEST_STATUS_ACTIVE:	$status = 'off';	break;
			case HTTPTEST_STATUS_DISABLED:	$status = 'on';		break;
			default:
				$status = 'unknown';	break;
		}
		return $status;
	}

	function	db_save_step($hostid, $applicationid, $httptestid, $testname, $name, $no, $timeout, $url, $posts, $required, $status_codes, $delay, $history, $trends)
	{
		if( $no <= 0 )
		{
			error('Scenario step number can\'t be less then 1');
			return false;
		}

		if (!eregi('^([0-9a-zA-Z\_\.[.-.]\$ ]+)$', $name)) 
		{
			error("Scenario step name should contain '0-9a-zA-Z_ .$'- characters only");
			return false;
		}

		if(!($httpstep_data = DBfetch(DBselect('select httpstepid from httpstep '.
			' where httptestid='.$httptestid.' and name='.zbx_dbstr($name)))))
		{
			$httpstepid = get_dbid("httpstep","httpstepid");
			
			if (!DBexecute('insert into httpstep'.
				' (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) '.
				' values ('.$httpstepid.','.$httptestid.','.zbx_dbstr($name).','.$no.','.
				zbx_dbstr($url).','.$timeout.','.
				zbx_dbstr($posts).','.zbx_dbstr($required).','.zbx_dbstr($status_codes).')'
				)) return false;
		}
		else
		{
			$httpstepid = $httpstep_data['httpstepid'];

			if (!DBexecute('update httpstep set '.
				' name='.zbx_dbstr($name).', no='.$no.', url='.zbx_dbstr($url).', timeout='.$timeout.','.
				' posts='.zbx_dbstr($posts).', required='.zbx_dbstr($required).', status_codes='.zbx_dbstr($status_codes).
				' where httpstepid='.$httpstepid)) return false;
		}

		$monitored_items = array(
			array(
				'description'	=> 'Download speed for step \'$2\' of scenario \'$1\'',
				'key_'		=> 'web.test.in['.$testname.','.$name.',bps]',
				'type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'		=> 'bps',
				'httpstepitemtype'=> HTTPSTEP_ITEM_TYPE_IN),
			array(
				'description'	=> 'Response time for step \'$2\' of scenario \'$1\'',
				'key_'		=> 'web.test.time['.$testname.','.$name.',resp]',
				'type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'		=> 's',
				'httpstepitemtype'=> HTTPSTEP_ITEM_TYPE_TIME),
			array(
				'description'	=> 'Response code for step \'$2\' of scenario \'$1\'',
				'key_'		=> 'web.test.rspcode['.$testname.','.$name.']',
				'type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'		=> '',
				'httpstepitemtype'=> HTTPSTEP_ITEM_TYPE_RSPCODE),
			);
		
		foreach($monitored_items as $item)
		{
			$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.status,i.delta,i.valuemapid '.
				' from items i, httpstepitem hi '.
				' where hi.httpstepid='.$httpstepid.' and hi.itemid=i.itemid '.
				' and hi.type='.$item['httpstepitemtype']));

			if(!$item_data)
			{
				$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.status,i.delta,i.valuemapid '.
					' from items i where i.key_='.zbx_dbstr($item['key_']).' and i.hostid='.$hostid));
			}

			if(!$item_data)
			{
				if (!($itemid = add_item($item['description'], $item['key_'], $hostid, $delay,
					$history, ITEM_STATUS_ACTIVE, ITEM_TYPE_HTTPTEST, '', '', $item['type'], 'localhost',
					161, $item['units'], 0, 0, '', 0, '', '', '0', $trends, '', 0, '', array($applicationid))))
					return false;
			}
			else
			{
				$itemid = $item_data['itemid'];

				if (!(update_item($itemid, $item['description'], $item['key_'], $hostid, $delay, $item_data['history'],
					$item_data['status'], ITEM_TYPE_HTTPTEST, '', '', $item['type'], 'localhost', 161,
					$item['units'], 0, 0, $item_data['delta'], 0, '', '', '0', $item_data['trends'], '',
					$item_data['valuemapid'], '', array($applicationid))))
					return false;
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

	function	db_save_httptest($httptestid, $hostid, $application, $name, $delay, $status, $agent, $macros, $steps)
	{
		$history = 30; // TODO !!! Allow user set this parametr
		$trends = 90; // TODO !!! Allow user set this parametr

 		if (!eregi('^([0-9a-zA-Z\_\.[.-.]\$ ]+)$', $name)) 
		{
			error("Scenario name should contain '0-9a-zA-Z_.$ '- characters only");
			return false;
		}
		
		DBstart();
		
		if($applicationid = DBfetch(DBselect('select applicationid from applications '.
			' where name='.zbx_dbstr($application).
			' and hostid='.$hostid)))
		{
			$applicationid = $applicationid['applicationid'];
		}
		else
		{
			$applicationid = add_application($application, $hostid);
			if(!$applicationid)
			{
				error('Can\'t add new application. ['.$application.']');
				return false;
			}
		}
		
		if(isset($httptestid))
		{
			$result = DBexecute('update httptest set '.
				' applicationid='.$applicationid.', name='.zbx_dbstr($name).', delay='.$delay.','.
				' status='.$status.', agent='.zbx_dbstr($agent).', macros='.zbx_dbstr($macros).','.
				' error='.zbx_dbstr('').', curstate='.HTTPTEST_STATE_UNKNOWN.
				' where httptestid='.$httptestid);
		}
		else
		{
			$httptestid = get_dbid("httptest","httptestid");
			
			if(DBfetch(DBselect('select t.httptestid from httptest t, applications a where t.applicationid=a.applicationid '.
				' and a.hostid='.$hostid.' and t.name='.zbx_dbstr($name))))
			{
				error('Scenario with name ['.$name.'] already exist');
				return false;
			}
			
			$result = DBexecute('insert into httptest'.
				' (httptestid, applicationid, name, delay, status, agent, macros, curstate) '.
				' values ('.$httptestid.','.$applicationid.','.zbx_dbstr($name).','.
				$delay.','.$status.','.zbx_dbstr($agent).','.zbx_dbstr($macros).','.HTTPTEST_STATE_UNKNOWN.')'
				);

			$test_added = true;
		}

		if($result)
		{
			$httpstepids = array();
			foreach($steps as $sid => $s)
			{
				if(!isset($s['name']))		$s['name'] = '';
				if(!isset($s['timeout']))	$s['timeout'] = 15;
				if(!isset($s['url']))       	$s['url'] = '';
				if(!isset($s['posts']))       	$s['posts'] = '';
				if(!isset($s['required']))      $s['required'] = '';
				if(!isset($s['status_codes']))  $s['status_codes'] = '';
			
				$result = db_save_step($hostid, $applicationid, $httptestid,
						$name, $s['name'], $sid+1, $s['timeout'], $s['url'], $s['posts'], $s['required'],$s['status_codes'],
						$delay, $history, $trends);
				
				if(!$result) break;
				
				$httpstepids[$result] = $result;
			}
			if($result)
			{
				/* clean unneeded steps */
				$db_steps = DBselect('select httpstepid from httpstep where httptestid='.$httptestid);
				while($step_data = DBfetch($db_steps))
				{
					if(isset($httpstepids[$step_data['httpstepid']]))	continue;
					delete_httpstep($step_data['httpstepid']);
				}
			}
		}

		if($result)
		{
			$monitored_items = array(
				array(
					'description'	=> 'Download speed for scenario \'$1\'',
					'key_'		=> 'web.test.in['.$name.',,bps]',
					'type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'		=> 'bps',
					'httptestitemtype'=> HTTPSTEP_ITEM_TYPE_IN),
				array(
					'description'	=> 'Failed step of scenario \'$1\'',
					'key_'		=> 'web.test.fail['.$name.']',
					'type'		=> ITEM_VALUE_TYPE_UINT64,
					'units'		=> '',
					'httptestitemtype'=> HTTPSTEP_ITEM_TYPE_LASTSTEP)
				);
			
			foreach($monitored_items as $item)
			{
				$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.status,i.delta,i.valuemapid '.
					' from items i, httptestitem hi '.
					' where hi.httptestid='.$httptestid.' and hi.itemid=i.itemid '.
					' and hi.type='.$item['httptestitemtype']));

				if(!$item_data)
				{
					$item_data = DBfetch(DBselect('select i.itemid,i.history,i.trends,i.status,i.delta,i.valuemapid '.
						' from items i where i.key_='.zbx_dbstr($item['key_']).' and i.hostid='.$hostid));
				}

				if(!$item_data)
				{
					if (!($itemid = add_item($item['description'], $item['key_'], $hostid, $delay,
						$history, ITEM_STATUS_ACTIVE, ITEM_TYPE_HTTPTEST, '', '', $item['type'], 'localhost',
						161, $item['units'], 0, 0, '', 0, '', '', '0', $trends, '', 0, '', array($applicationid))))
					{
						$result = false;
						break;
					}
				}
				else
				{
					$itemid = $item_data['itemid'];

					if (!(update_item($itemid, $item['description'], $item['key_'], $hostid, $delay, $item_data['history'],
						$item_data['status'], ITEM_TYPE_HTTPTEST, '', '', $item['type'], 'localhost', 161,
						$item['units'], 0, 0, $item_data['delta'], 0, '', '', '0', $item_data['trends'], '',
						$item_data['valuemapid'], '', array($applicationid))))
					{
						$result = false;
						break;
					}
				}

				
				$httptestitemid = get_dbid('httptestitem', 'httptestitemid');

				DBexecute('delete from httptestitem where itemid='.$itemid);

				if (!DBexecute('insert into httptestitem'.
					' (httptestitemid, httptestid, itemid, type) '.
					' values ('.$httptestitemid.','.$httptestid.','.$itemid.','.$item['httptestitemtype'].')'
					))
				{
					$result = false;
					break;
				}
			}
		}

		if(!$result && isset($test_added))	delete_httptest($httptestid);
		else	$restult = $httptestid;

		DBend($result);

		return $result;
	}
	
	function	add_httptest($hostid, $application, $name, $delay, $status, $agent, $macros, $steps)
	{
		$result = db_save_httptest(null, $hostid, $application, $name, $delay, $status, $agent, $macros, $steps);
		
		if($result) info("Sceanrio '".$name."' added");

		return $result;
	}
	
	function	update_httptest($httptestid, $hostid, $application, $name, $delay, $status, $agent, $macros, $steps)
	{
		$result = db_save_httptest($httptestid, $hostid, $application, $name, $delay, $status, $agent, $macros, $steps);
		
		if($result)	info("Sceanrio '".$name."' updated");

		return $result;
	}
	
	function	delete_httpstep($httpstepid)
	{
		$db_httpstepitems = DBselect('select distinct * from httpstepitem where httpstepid='.$httpstepid);
		while($httpstepitem_data = DBfetch($db_httpstepitems))
		{
			if(!DBexecute('delete from httpstepitem where httpstepitemid='.$httpstepitem_data['httpstepitemid'])) return false;
			if(!delete_item($httpstepitem_data['itemid'])) return false;
		}
			
		return DBexecute('delete from httpstep where httpstepid='.$httpstepid);
	}
	
	function	delete_httptest($httptestid)
	{
		if (!($httptest = DBfetch(DBselect('select * from httptest where httptestid='.$httptestid)))) return false;
		
		$db_httpstep = DBselect('select distinct s.httpstepid from httpstep s '.
			' where s.httptestid='.$httptestid);
		while($httpstep_data = DBfetch($db_httpstep))
		{
			delete_httpstep($httpstep_data['httpstepid']);
		}
		
		if(!DBexecute('delete from httptest where httptestid='.$httptestid)) return false;
		
		info("Sceanrio '".$httptest["name"]."' deleted");

		return true;
	}
	
	function	activate_httptest($httptestid)
	{
		return DBexecute('update httptest set status='.HTTPTEST_STATUS_ACTIVE.' where httptestid='.$httptestid);
	}

	function	disable_httptest($httptestid)
	{
		return DBexecute('update httptest set status='.HTTPTEST_STATUS_DISABLED.' where httptestid='.$httptestid);
	}

	function	delete_history_by_httptestid($httptestid)
	{
		$db_items = DBselect('select distinct i.itemid from items i, httpstepitem si, httpstep s '.
			' where s.httptestid='.$httptestid.' and si.httpstepid=s.httpstepid and i.itemid=si.itemid');
		while($item_data = DBfetch($db_items))
		{
			if(!delete_history_by_itemid($item_data['itemid'], 0 /* use housekeeper */)) return false;
		}
		return true;
	}

	function	get_httptest_by_httptestid($httptestid)
	{
		return DBfetch(DBselect('select * from httptest where httptestid='.$httptestid));
	}

	function	&get_httpsteps_by_httptestid($httptestid)
	{
		return DBselect('select * from httpstep where httptestid='.$httptestid);
	}

	function	get_httpstep_by_httpstepid($httpstepid)
	{
		return DBfetch(DBselect('select * from httpstep where httpstepid='.$httpstepid));
	}

	function	get_httpstep_by_no($httptestid, $no)
	{
		return DBfetch(DBselect('select * from httpstep where httptestid='.$httptestid.' and no='.$no));
	}
?>
