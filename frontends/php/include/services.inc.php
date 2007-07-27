<?php
/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	function	add_service($name,$triggerid,$status,$algorithm,$showsla,$goodsla,$sortorder,$service_times=array(),$parentid,$childs){
	
		foreach($childs as $id => $child){		//add childs
			if($parentid == $child['serviceid']){
				error('Service can\'t be parent and child in onetime.');
				return FALSE;
			}
		}
		
		if(is_null($triggerid) || $triggerid==0) $triggerid = 'NULL';

		$serviceid=get_dbid("services","serviceid");
		
		remove_service_links($serviceid); //removes all links with current serviceid

		$result =($parentid != 0)?(add_service_link($serviceid,$parentid,0)):(true); //add parent
		
		foreach($childs as $id => $child){		//add childs
			if(!isset($child['soft']) || empty($child['soft'])) $child['soft'] = 0;
			$result = add_service_link($child['serviceid'],$serviceid,$child['soft']); 
		}
		
		if(!$result){
			return FALSE;
		}

		$result=DBexecute('INSERT INTO services (serviceid,name,status,triggerid,algorithm,showsla,goodsla,sortorder)'.
							' VALUES ('.$serviceid.','.zbx_dbstr($name).','.$status.' ,'.$triggerid.' ,'.zbx_dbstr($algorithm).
								' ,'.$showsla.','.zbx_dbstr($goodsla).','.$sortorder.')');
		if(!$result){
			return FALSE;
		}
		
		$status = get_service_status($serviceid,$algorithm,$triggerid);
		update_services($triggerid, $status); // updating status to all services by the dependency
		
		DBExecute('DELETE FROM services_times WHERE serviceid='.$serviceid);

		foreach($service_times as $val){
			$timeid = get_dbid('services_times','timeid');
			$result = DBexecute('INSERT INTO services_times (timeid, serviceid, type, ts_from, ts_to, note)'.
				' values ('.$timeid.','.$serviceid.','.$val['type'].','.$val['from'].','.$val['to'].','.zbx_dbstr($val['note']).')');

			if(!$result)
			{
				delete_service($serviceid);
				return FALSE;
			}
		}
		return $serviceid;
	}

	function	update_service($serviceid,$name,$triggerid,$status,$algorithm,$showsla,$goodsla,$sortorder,$service_times=array(),$parentid,$childs){
		foreach($childs as $id => $child){		//add childs
			if($parentid == $child['serviceid']){
				error('Service can\'t be parent and child in onetime.');
				return FALSE;
			}
		}
		remove_service_links($serviceid); //removes all links with current serviceid

		$result =($parentid != 0)?(add_service_link($serviceid,$parentid,0)):(true); //add parent
		
		foreach($childs as $id => $child){		//add childs
			if(empty($child['soft']) || !isset($child['soft'])) $child['soft'] = 0;
			$result = add_service_link($child['serviceid'],$serviceid,$child['soft']); 
		}
		
		if(!$result){
			return FALSE;
		}
		
		if(is_null($triggerid) || $triggerid==0) $triggerid = 'NULL';

		$result = DBexecute('UPDATE services '.
							' SET name='.zbx_dbstr($name).
								',triggerid='.$triggerid.', status='.$status.', algorithm='.$algorithm.', '.
								' showsla='.$showsla.', goodsla='.$goodsla.', sortorder='.$sortorder.
							' WHERE serviceid='.$serviceid);

		$status = get_service_status($serviceid,$algorithm,$triggerid);
		update_services($triggerid, $status); // updating status to all services by the dependency

		DBexecute('DELETE FROM services_times WHERE serviceid='.$serviceid);
		foreach($service_times as $val){
			$timeid = get_dbid('services_times','timeid');
			DBexecute('INSERT INTO services_times (timeid,serviceid, type, ts_from, ts_to, note)'.
				' VALUES('.$timeid.','.$serviceid.','.$val['type'].','.$val['from'].','.$val['to'].','.zbx_dbstr($val['note']).')');
		}

		return $result;
	}
	
	function	add_host_to_services($hostid, $serviceid){
		global $ZBX_CURNODEID;

		$result = DBselect('SELECT distinct h.host,t.triggerid,t.description '.
							' FROM triggers t,hosts h,items i,functions f '.
							' WHERE h.hostid='.$hostid.
								' AND h.hostid=i.hostid '.
								' AND i.itemid=f.itemid '.
								' AND f.triggerid=t.triggerid '.
								' AND '.DBid2nodeid('t.triggerid').'='.$ZBX_CURNODEID
							);
							
		while($row=DBfetch($result)){
			$serviceid2 = add_service(expand_trigger_description_by_data($row),$row["triggerid"],"on",0,"off",99);
			add_service_link($serviceid2,$serviceid,0);
		}
		return	1;
	}

	function	is_service_hardlinked($serviceid){
		$row = DBfetch(DBselect("SELECT count(*) as cnt FROM services_links WHERE servicedownid=".$serviceid." and soft=0"));
		if($row["cnt"]>0)
		{
			return	TRUE;
		}
		return	FALSE;
	}
	
	/*
	 * Function: get_service_status
	 *
	 * Description: 
	 *     retrive true status
	 *     
	 * Author: 
	 *     Artem Suahrev
	 *
	 * Comments:
	 *
	 */
	
	function get_service_status($serviceid,$algorithm,$triggerid=null,$status=0){
		
		if(!$status && is_numeric($triggerid)){
			$status = get_trigger_priority($triggerid);/* Do nothing */
		}
		
		if((SERVICE_ALGORITHM_MAX == $algorithm) || (SERVICE_ALGORITHM_MIN == $algorithm)){
			if(SERVICE_ALGORITHM_MAX == $algorithm){
			
				$result = DBselect('SELECT count(*) as count,max(status) as status'.
									' FROM services s,services_links l '.
									' WHERE l.serviceupid='.$serviceid.
										' AND s.serviceid=l.servicedownid'
									);
			}
			/* MIN otherwise */
			else{
				$result = DBselect('SELECT count(*) as count,min(status) as status'.
									' FROM services s,services_links l '.
									' WHERE l.serviceupid='.$serviceid.
										' AND s.serviceid=l.servicedownid'
									);
			}
			
			$rows = DBfetch($result);
	
			if($rows && !is_null($rows['count']) && !is_null($rows['status'])){
				if($rows['count'] > 0){
					$status=$rows['status'];
				}
			}
		}
		
	return $status;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_service_link($linkid)
	{
		$sql="DELETE FROM services_links WHERE linkid=$linkid";
		return DBexecute($sql);
	}

	function	delete_service($serviceid)
	{
		$sql="DELETE FROM services_links WHERE servicedownid=$serviceid OR serviceupid=$serviceid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}

		$sql="DELETE FROM services WHERE serviceid=$serviceid";
		return DBexecute($sql);
	}

	# Return TRUE if triggerid is a reason why the service is not OK
	# Warning: recursive function
	function	does_service_depend_on_the_service($serviceid,$serviceid2)
	{
		$service=get_service_by_serviceid($serviceid);
		if($service["status"]==0)
		{
			return	FALSE;
		}
		if($serviceid==$serviceid2)
		{
			if($service["status"]>0)
			{
				return TRUE;
			}
			
		}

		$result=DBselect("SELECT serviceupid FROM services_links WHERE servicedownid=$serviceid2 and soft=0");
		while($row=DBfetch($result))
		{
			if(does_service_depend_on_the_service($serviceid,$row["serviceupid"]) == TRUE)
			{
				return	TRUE;
			}
		}
		return	FALSE;
	}

	function	service_has_parent($serviceid)
	{
		$row = DBfetch(DBselect("SELECT count(*) as cnt FROM services_links WHERE servicedownid=$serviceid"));
		if($row["cnt"]>0)
		{
			return	TRUE;
		}
		return	FALSE;
	}

	function	service_has_no_this_parent($parentid,$serviceid)
	{
		$row = DBfetch(DBselect("SELECT count(*) as cnt FROM services_links WHERE serviceupid=$parentid and servicedownid=$serviceid"));
		if($row["cnt"]>0)
		{
			return	FALSE;
		}
		return	TRUE;
	}

	function	add_service_link($servicedownid,$serviceupid,$softlink){
		if( ($softlink==0) && (is_service_hardlinked($servicedownid)==true) ){
			error("cannot link hardlinked service.");
			return	false;
		}

		if($servicedownid==$serviceupid){
			error("cannot link service to itself.");
			return	false;
		}

		$linkid=get_dbid("services_links","linkid");

		$sql="INSERT INTO services_links (linkid,servicedownid,serviceupid,soft) values ($linkid,$servicedownid,$serviceupid,$softlink)";
		$result=DBexecute($sql);

		if(!$result)
			return $result;

		return $linkid;
	}
	
	function	update_service_link($linkid,$servicedownid,$serviceupid,$softlink){
		if( ($softlink==0) && (is_service_hardlinked($servicedownid)==true) ){
			return	false;
		}

		if($servicedownid==$serviceupid){
			error("cannot link service to itself.");
			return	false;
		}

		$sql="UPDATE services_links SET servicedownid=$servicedownid, serviceupid=$serviceupid, soft=$softlink WHERE linkid=$linkid";
		return	dbexecute($sql);
	}
	
	function remove_service_links($serviceid){
		$query='DELETE 
				FROM services_links 
				WHERE serviceupid='.$serviceid.' 
					OR  (servicedownid='.$serviceid.' 
					AND soft<>1)';
		DBExecute($query);
	}

	function	get_last_service_value($serviceid,$clock){
	       	$sql="SELECT count(*) as cnt,max(clock) as maxx FROM service_alarms WHERE serviceid=$serviceid and clock<=$clock";
//		echo " $sql<br>";
		
	        $result=DBselect($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
	       		$sql="SELECT value FROM service_alarms WHERE serviceid=$serviceid and clock=".$row["maxx"];
		        $result2=DBselect($sql);
// Assuring that we get very latest service value. There could be several with the same timestamp
//			$value=DBget_field($result2,0,0);
			while($row2=DBfetch($result2))
			{
				$value=$row2["value"];
			}
		}
		else
		{
			$value=0;
		}
		return $value;
	}

	function	expand_periodical_service_times(&$data, 
		$period_start, $period_end, 
		$ts_from, $ts_to, 
		$type='ut' /* 'ut' OR 'dt' */
		)
	{
			/* calculate period FROM '-1 week' to know period name for  $period_start */
			for($curr = ($period_start - (7*24*36000)); $curr<=$period_end; $curr += 6*3600)
			{
				$curr_date = getdate($curr);
				$from_date = getdate($ts_from);
				if($curr_date['wday'] == $from_date['wday'])
				{
					$curr_from = mktime(
						$from_date['hours'],$from_date['minutes'],$from_date['seconds'],
						$curr_date['mon'],$curr_date['mday'],$curr_date['year']
						);
					$curr_to = $curr_from + ($ts_to - $ts_from);

					$curr_from	= max($curr_from, $period_start);
					$curr_from	= min($curr_from, $period_end);
					$curr_to	= max($curr_to, $period_start);
					$curr_to	= min($curr_to, $period_end);

					$curr = $curr_to;

					if(isset($data[$curr_from][$type.'_s']))
						$data[$curr_from][$type.'_s'] ++;
					else
						$data[$curr_from][$type.'_s'] = 1;

					if(isset($data[$curr_to][$type.'_e']))
						$data[$curr_to][$type.'_e'] ++;
					else
						$data[$curr_to][$type.'_e'] = 1;


				}
			}
	}

	function	calculate_service_availability($serviceid,$period_start,$period_end)
	{


//	       	$sql="SELECT count(*),min(clock),max(clock) FROM service_alarms WHERE serviceid=$serviceid and clock>=$period_start and clock<=$period_end";

		/* FILL data */

		/* structure of "$data"
		 *	key	- time stamp
		 *	alarm	- on/off status (0,1 - off; >1 - on)
		 *	dt_s	- count of downtime starts
		 *	dt_e	- count of downtime ends
		 *	ut_s	- count of uptime starts
		 *	ut_e	- count of uptime ends
		 */

		$data[$period_start]['alarm'] = get_last_service_value($serviceid,$period_start);

		$service_alarms = DBselect("SELECT clock,value FROM service_alarms".
			" WHERE serviceid=".$serviceid." and clock>=".$period_start." and clock<=".$period_end." order by clock");

		/* add alarms */
		while($db_alarm_row = DBfetch($service_alarms))
		{
			$data[$db_alarm_row['clock']]['alarm'] = $db_alarm_row['value'];
		}

		/* add periodical downtimes */
		$service_times = DBselect('SELECT ts_from,ts_to FROM services_times WHERE type='.SERVICE_TIME_TYPE_UPTIME.
				' and serviceid='.$serviceid);
		if($db_time_row = DBfetch($service_times))
		{
			/* if exist any uptime - unmarked time is downtime */
			$unmarked_period_type = 'dt';
			do{
				expand_periodical_service_times($data,
					$period_start, $period_end,
					$db_time_row['ts_from'], $db_time_row['ts_to'],
					'ut');

			}while($db_time_row = DBfetch($service_times));
		}
		else
		{
			/* if missed any uptime - unmarked time is uptime */
			$unmarked_period_type = 'ut';
		}

		/* add periodical downtimes */
		$service_times = DBselect('SELECT ts_from,ts_to FROM services_times WHERE type='.SERVICE_TIME_TYPE_DOWNTIME.
				' and serviceid='.$serviceid);
		while($db_time_row = DBfetch($service_times))
		{
			expand_periodical_service_times($data,
				$period_start, $period_end,
				$db_time_row['ts_from'], $db_time_row['ts_to'],
				'dt');
		}

		/* add one-time downtimes */
		$service_times = DBselect('SELECT ts_from,ts_to FROM services_times WHERE type='.SERVICE_TIME_TYPE_ONETIME_DOWNTIME.' and serviceid='.$serviceid);
		while($db_time_row = DBfetch($service_times)){
		
			if( ($db_time_row['ts_to'] < $period_start) || ($db_time_row['ts_from'] > $period_end)) continue;

			if($db_time_row['ts_from'] < $period_start)	$db_time_row['ts_from'] = $period_start;
			if($db_time_row['ts_to'] > $period_end)		$db_time_row['ts_to'] = $period_end;

			if(isset($data[$db_time_row['ts_from']]['dt_s']))
				$data[$db_time_row['ts_from']]['dt_s'] ++;
			else
				$data[$db_time_row['ts_from']]['dt_s'] = 1;

			if(isset($data[$db_time_row['ts_to']]['dt_e']))
				$data[$db_time_row['ts_to']]['dt_e'] ++;
			else
				$data[$db_time_row['ts_to']]['dt_e'] = 1;
		}
		if(!isset($data[$period_end])) $data[$period_end] = array();

/*
		print('From: '.date('l d M Y H:i',$period_start).' To: '.date('l d M Y H:i',$period_end).BR);
$ut = 0;
$dt = 0;
		foreach($data as $ts => $val)
		{
			print($ts);
			print(" - [".date('l d M Y H:i',$ts)."]");
			if(isset($val['ut_s'])) {print(' ut_s-'.$val['ut_s']); $ut+=$val['ut_s'];}
			if(isset($val['ut_e'])) {print(' ut_e-'.$val['ut_e']); $ut-=$val['ut_e'];}
			if(isset($val['dt_s'])) {print(' dt_s-'.$val['dt_s']); $dt+=$val['dt_s'];}
			if(isset($val['dt_e'])) {print(' dt_e-'.$val['dt_e']); $dt-=$val['dt_e'];}
			if(isset($val['alarm'])) {print(' alarm is '.$val['alarm']); }
			print('       ut = '.$ut.'      dt = '.$dt);
			print(BR);
		}
*/
		/* calculate times */

		ksort($data); /* sort by time stamp */

		$dt_cnt = 0;
		$ut_cnt = 0;
		$sla_time = array(
			'dt' => array('problem_time' => 0, 'ok_time' => 0),
			'ut' => array('problem_time' => 0, 'ok_time' => 0)
			);
		$prev_alarm = $data[$period_start]['alarm'];
		$prev_time  = $period_start;

//print_r($data[$period_start]); print(BR);

		if(isset($data[$period_start]['ut_s'])) $ut_cnt += $data[$period_start]['ut_s'];
		if(isset($data[$period_start]['ut_e'])) $ut_cnt -= $data[$period_start]['ut_e'];
		if(isset($data[$period_start]['dt_s'])) $dt_cnt += $data[$period_start]['dt_s'];
		if(isset($data[$period_start]['dt_e'])) $dt_cnt -= $data[$period_start]['dt_e'];
		foreach($data as $ts => $val)
		{
			if($ts == $period_start) continue; /* skip first data [already readed] */

			if($dt_cnt > 0)
			{
				$period_type = 'dt';
			}
			else if($ut_cnt > 0)
			{
				$period_type = 'ut';
			}
			else /* dt_cnt=0 && ut_cnt=0 */
			{
				$period_type = $unmarked_period_type;
			}

			/* state=0,1 [OK] (1 - information severity of trigger), >1 [PROBLEMS] (trigger severity) */
			if($prev_alarm > 1)
			{
				$sla_time[$period_type]['problem_time']	+= $ts - $prev_time;
			}
			else
			{
				$sla_time[$period_type]['ok_time'] 	+= $ts - $prev_time;
			}
//print_r($val); print(BR);
			if(isset($val['ut_s'])) $ut_cnt += $val['ut_s'];
			if(isset($val['ut_e'])) $ut_cnt -= $val['ut_e'];
			if(isset($val['dt_s'])) $dt_cnt += $val['dt_s'];
			if(isset($val['dt_e'])) $dt_cnt -= $val['dt_e'];

			if(isset($val['alarm'])) $prev_alarm = $val['alarm'];

			$prev_time = $ts;
		}

		$sla_time['problem_time']	= &$sla_time['ut']['problem_time'];
		$sla_time['ok_time']		= &$sla_time['ut']['ok_time'];
		$sla_time['downtime_time']	= $sla_time['dt']['ok_time'] + $sla_time['dt']['problem_time'];

		$full_time = $sla_time['problem_time'] + $sla_time['ok_time'];
		if($full_time > 0)
		{
			$sla_time['problem'] 	= 100 * $sla_time['problem_time'] / $full_time;
			$sla_time['ok']		= 100 * $sla_time['ok_time'] / $full_time;
		}
		else
		{
			$sla_time['problem'] 	= 100;
			$sla_time['ok']		= 100;
		}

		return $sla_time;
	}

	function	get_service_status_description($status)
	{
		$desc="<font color=\"#00AA00\">OK</a>";
		if($status==5)
		{
			$desc="<font color=\"#FF0000\">Disaster</a>";
		}
		elseif($status==4)
		{
			$desc="<font color=\"#FF8888\">Serious".SPACE."problem</a>";
		}
		elseif($status==3)
		{
			$desc="<font color=\"#AA0000\">Average".SPACE."problem</a>";
		}
		elseif($status==2)
		{
			$desc="<font color=\"#AA5555\">Minor".SPACE."problem</a>";
		}
		elseif($status==1)
		{
			$desc="<font color=\"#00AA00\">OK</a>";
		}
		return $desc;
	}

	function	get_num_of_service_childs($serviceid)
	{
		$row = DBfetch(DBselect("SELECT count(distinct servicedownid) as cnt FROM services_links ".
					" WHERE serviceupid=".$serviceid));
		return	$row["cnt"];
	}

	function	get_service_by_serviceid($serviceid)
	{
		$res = DBfetch(DBselect("SELECT * FROM services WHERE serviceid=".$serviceid));
		if(!$res)
		{
			error("No service with serviceid=[".$serviceid."]");
			return	FALSE;
		}
		return $res;
	}

	function	get_services_links_by_linkid($linkid)
	{
		$result=DBselect("SELECT * FROM services_links WHERE linkid=$linkid");
		$res = DBfetch($result);
		if(!$res)
		{
			error("No service linkage with linkid=[$linkid]");
			return	FALSE;
		}
		return $res;
	}

	function algorithm2str($algorithm)
	{
		if($algorithm == SERVICE_ALGORITHM_NONE)
		{
			return S_NONE;
		}
		elseif($algorithm == SERVICE_ALGORITHM_MAX)
		{
			return S_MAX_OF_CHILDS;
		}
		elseif($algorithm == SERVICE_ALGORITHM_MIN)
		{
			return S_MIN_OF_CHILDS;
		}
		return S_UNKNOWN;
	}
	
	function get_service_childs($serviceid,$soft=0){
		$childs = array();

		$query = 'SELECT sl.servicedownid '.
			' FROM services_links sl '.
			' WHERE sl.serviceupid = '.$serviceid.(($soft == 1)?(''):(' AND sl.soft <> 1'));
		
		$res =  DBSelect($query);
		while($row = DBFetch($res)){
			$childs[] = $row['servicedownid'];
			$childs = array_merge($childs, get_service_childs($row['servicedownid']));
		}
		return $childs;
	}
	
	function createServiceTree(&$services,&$temp,$id=0,$serviceupid=0,$parentid=0, $soft=0, $linkid=''){

		$rows = $services[$id];
		if(($rows['serviceid'] > 0) && ($rows['caption'] != 'root')){
			$rows['algorithm'] = algorithm2str($rows['algorithm']);
		}
	
	//---------------------------- if not leaf -----------------------------
		$rows['parentid'] = $parentid;
		if($soft == 0){
			$rows['caption'] = new CLink($rows['caption'],'#',null,'javascript: call_menu(event, '.zbx_jsvalue($rows['serviceid']).','.zbx_jsvalue($rows['caption']).'); return false;');
				
			$temp[$rows['serviceid']]=$rows;
		
			if(isset($rows['childs'])){
				foreach($rows['childs'] as $cid => $nodeid){
					if(!isset($services[$nodeid['id']])){
						continue;
					}
					if(isset($services[$nodeid['id']]['serviceupid'])){
						createServiceTree($services,$temp,$nodeid['id'],$services[$nodeid['id']]['serviceupid'],$rows['serviceid'],$nodeid['soft'], $nodeid['linkid']);
					}
				}			
			}
		} else {
			$rows['caption'] = '<font style="color: #888888;">'.$rows['caption'].'</font>';
			$temp[$rows['serviceid'].','.$linkid]=$rows;
		}
	return ;
	}
	
	function createShowServiceTree(&$services,&$temp,$id=0,$serviceupid=0,$parentid=0, $soft=0, $linkid=''){

		$rows = $services[$id];
		
	
	//---------------------------- if not leaf -----------------------------
		$rows['parentid'] = $parentid;
		if(($rows['serviceid']  > 0) && ($rows['caption'] != 'root')){
			$rows['status'] = get_service_status_description($rows["status"]);
		}
		
		if($soft == 0){

			$temp[$rows['serviceid']]=$rows;
		
			if(isset($rows['childs'])){
				foreach($rows['childs'] as $cid => $nodeid){
					if(!isset($services[$nodeid['id']])){
						continue;
					}
					if(isset($services[$nodeid['id']]['serviceupid'])){
						createShowServiceTree($services,$temp,$nodeid['id'],$services[$nodeid['id']]['serviceupid'],$rows['serviceid'],$nodeid['soft'], $nodeid['linkid']);	}
				}			
			}
		} else {
			$rows['caption'] = new CSpan($rows['caption']);
			$rows['caption']->AddOption('style','color: #888888;');
			$temp[$rows['serviceid'].','.$linkid]=$rows;
		}
	return ;
	}
	
	function closeform(){
		zbx_add_post_js('closeform();');
	}
	
	function del_empty_nodes($services){
		do{
			unset($retry);
			foreach($services as $id => $data){
				if(isset($data['serviceupid']) && !isset($services[$data['serviceupid']])){
					unset($services[$id]);
					$retry = true;
					//break;
				}
			}
		} while(isset($retry));
	return $services;
	}
	
/******************************************************************************
 *                                                                            *
 * Function: update_services_rec                                              *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev   (PHP ver. by Artem Suharev)                     *
 *                                                                            *
 * Comments: recursive function   !!! Don't forget sync code with C !!!       *
 *                                                                            *
 ******************************************************************************/
function update_services_rec($serviceid){

	$result = DBselect('SELECT l.serviceupid,s.algorithm '.
						' FROM services_links l,services s '.
						' WHERE s.serviceid=l.serviceupid '.
							' AND l.servicedownid='.$serviceid
						);
	$status=0;

	while($rows=DBfetch($result)){
		$serviceupid = $rows['serviceupid'];
		$algorithm = $rows['algorithm'];
		
		if(SERVICE_ALGORITHM_NONE == $algorithm){
			/* Do nothing */
		}
		else if((SERVICE_ALGORITHM_MAX == $algorithm) || (SERVICE_ALGORITHM_MIN == $algorithm)){

			$status = get_service_status($serviceupid,$algorithm);

			$now=time();
			
			add_service_alarm($serviceupid,$status,$now);
			DBexecute('UPDATE services SET status='.$status.' WHERE serviceid='.$serviceupid);
		}
		else{
			error('Unknown calculation algorithm of service status ['.$algorithm.']');
			return false;
		}
	}

	$result = DBselect('SELECT serviceupid FROM services_links WHERE servicedownid='.$serviceid);

	while($rows=DBfetch($result)){
		$serviceupid =  $rows['serviceupid'];
		update_services_rec($serviceupid);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: update_services                                                  *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *             status - new status of the service                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev   (PHP ver. by Artem Suharev)                     *
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function update_services($triggerid, $status){
	DBexecute('UPDATE services SET status='.$status.' WHERE triggerid='.$triggerid);

	$result = DBselect('SELECT serviceid FROM services WHERE triggerid='.$triggerid);

	while(($rows=DBfetch($result))){
		update_services_rec($rows['serviceid']);
	}
}

/******************************************************************************
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function latest_service_alarm($serviceid, $status){
	$ret = false;

	$result = DBselect('SELECT max(clock) as clock FROM service_alarms WHERE serviceid='.$serviceid);
	$rows = DBfetch($result);

	if(!$rows || is_null($rows['clock'])){
		$ret = false;
	}
	else{
		$clock=$rows['clock'];

		$result = DBselect('SELECT value FROM service_alarms WHERE serviceid='.$serviceid.' AND clock='.$clock);
		$rows = DBfetch($result);
		if($rows && !is_null($rows['value'])){
			if($rows['value'] == $status){
				$ret = true;
			}
		}
	}

	return $ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function add_service_alarm($serviceid,$status,$clock){

	if(latest_service_alarm($serviceid,$status) == true){
		return true;
	}

	DBexecute('INSERT INTO service_alarms (servicealarmid,serviceid,clock,value) VALUES ('.get_dbid('service_alarms','servicealarmid').','.$serviceid.','.$clock.','.$status.')');
	
	return true;
}
?>
