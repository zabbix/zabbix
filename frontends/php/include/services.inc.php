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
	function	add_service($name,$triggerid,$algorithm,$showsla,$goodsla,$sortorder)
	{
		if(is_null($triggerid)) $triggerid = 'NULL';

		$sql="insert into services (name,status,triggerid,algorithm,showsla,goodsla,sortorder)".
			" values (".zbx_dbstr($name).",0,$triggerid,".zbx_dbstr($algorithm).",$showsla,".zbx_dbstr($goodsla).",$sortorder)";
		$result=DBexecute($sql);
		if(!$result)
		{
			return FALSE;
		}
		return DBinsert_id($result,"services","serviceid");
	}

	function	update_service($serviceid,$name,$triggerid,$algorithm,$showsla,$goodsla,$sortorder)
	{
		if(is_null($triggerid)) $triggerid = 'NULL';

		$sql="update services set name=".zbx_dbstr($name).",triggerid=$triggerid,status=0,algorithm=$algorithm,showsla=$showsla,goodsla=$goodsla,sortorder=$sortorder where serviceid=$serviceid";
		return	DBexecute($sql);
	}

	function	add_host_to_services($hostid,$serviceid)
	{
		$sql="select distinct t.triggerid,t.description from triggers t,hosts h,items i,functions f where h.hostid=$hostid and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$serviceid2=add_service($row["description"],$row["triggerid"],"on",0,"off",99,0);
			add_service_link($serviceid2,$serviceid,0);
		}
		return	1;
	}

	function	is_service_hardlinked($serviceid)
	{
		$sql="select count(*) as cnt from services_links where servicedownid=$serviceid and soft=0";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			return	TRUE;
		}
		return	FALSE;
	}

	function	delete_service_link($linkid)
	{
		$sql="delete from services_links where linkid=$linkid";
		return DBexecute($sql);
	}

	function	delete_service($serviceid)
	{
		$sql="delete from services_links where servicedownid=$serviceid or serviceupid=$serviceid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
	// delete service permisions
		DBexecute('delete from rights where name=\'Service\' and id='.$serviceid);

		$sql="delete from services where serviceid=$serviceid";
		return DBexecute($sql);
	}

	# Return TRUE if triggerid is a reason why the service is not OK
	# Warning: recursive function
	function	does_service_depend_on_the_service($serviceid,$serviceid2)
	{
#		echo "Serviceid:$serviceid Triggerid:$serviceid2<br>";
		$service=get_service_by_serviceid($serviceid);
#		echo "Service status:".$service["status"]."<br>";
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

		$sql="select serviceupid from services_links where servicedownid=$serviceid2 and soft=0";
#		echo $sql."<br>";
		$result=DBselect($sql);
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
		$sql="select count(*) as cnt from services_links where servicedownid=$serviceid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			return	TRUE;
		}
		return	FALSE;
	}

	function	service_has_no_this_parent($parentid,$serviceid)
	{
		$sql="select count(*) as cnt from services_links where serviceupid=$parentid and servicedownid=$serviceid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			return	FALSE;
		}
		return	TRUE;
	}

	function	add_service_link($servicedownid,$serviceupid,$softlink)
	{
		if( ($softlink==0) && (is_service_hardlinked($servicedownid)==true) )
		{
			return	false;
		}

		if($servicedownid==$serviceupid)
		{
			error("cannot link service to itself.");
			return	false;
		}

		$sql="insert into services_links (servicedownid,serviceupid,soft) values ($servicedownid,$serviceupid,$softlink)";
		return	dbexecute($sql);
	}
	function	update_service_link($linkid,$servicedownid,$serviceupid,$softlink)
	{
		if( ($softlink==0) && (is_service_hardlinked($servicedownid)==true) )
		{
			return	false;
		}

		if($servicedownid==$serviceupid)
		{
			error("cannot link service to itself.");
			return	false;
		}

		$sql="update services_links set servicedownid=$servicedownid, serviceupid=$serviceupid, soft=$softlink where linkid=$linkid";
		return	dbexecute($sql);
	}

	function	get_last_service_value($serviceid,$clock)
	{
	       	$sql="select count(*) as cnt,max(clock) as maxx from service_alarms where serviceid=$serviceid and clock<=$clock";
//		echo " $sql<br>";
		
	        $result=DBselect($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
	       		$sql="select value from service_alarms where serviceid=$serviceid and clock=".$row["maxx"];
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

	function	calculate_service_availability($serviceid,$period_start,$period_end)
	{
	       	$sql="select count(*),min(clock),max(clock) from service_alarms where serviceid=$serviceid and clock>=$period_start and clock<=$period_end";
		
		$sql="select clock,value from service_alarms where serviceid=$serviceid and clock>=$period_start and clock<=$period_end";
		$result=DBselect($sql);

// -1,0,1
		$state=get_last_service_value($serviceid,$period_start);
		$problem_time=0;
		$ok_time=0;
		$time=$period_start;
		while($row=DBfetch($result))
		{
			$clock=$row["clock"];
			$value=$row["value"];

			$diff=$clock-$time;

			$time=$clock;
#state=0,1 (OK), >1 PROBLEMS 

			if($state<=1)
			{
				$ok_time+=$diff;
				$state=$value;
			}
			else
			{
				$problem_time+=$diff;
				$state=$value;
			}
		}
//		echo $problem_time,"-",$ok_time,"<br>";

		if(!DBfetch($result))
		{
			if(get_last_service_value($serviceid,$period_start)<=1)
			{
				$ok_time=$period_end-$period_start;
			}
			else
			{
				$problem_time=$period_end-$period_start;
			}
		}
		else
		{
			if($state<=1)
			{
				$ok_time=$ok_time+$period_end-$time;
			}
			else
			{
				$problem_time=$problem_time+$period_end-$time;
			}
		}

//		echo $problem_time,"-",$ok_time,"<br>";

		$total_time=$problem_time+$ok_time;
		if($total_time==0)
		{
			$ret["problem_time"]=0;
			$ret["ok_time"]=0;
			$ret["problem"]=0;
			$ret["ok"]=0;
		}
		else
		{
			$ret["problem_time"]=$problem_time;
			$ret["ok_time"]=$ok_time;
			$ret["problem"]=(100*$problem_time)/$total_time;
			$ret["ok"]=(100*$ok_time)/$total_time;
		}
		return $ret;
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
		$sql="select count(*) as cnt from services_links where serviceupid=$serviceid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		return	$row["cnt"];
	}

	function	get_service_by_serviceid($serviceid)
	{
		$sql="select * from services where serviceid=$serviceid";
		$result=DBselect($sql);
		$res = DBfetch($result);
		if(!$res)
		{
			error("No service with serviceid=[$serviceid]");
			return	FALSE;
		}
		return $res;
	}

	function	get_services_links_by_linkid($linkid)
	{
		$result=DBselect("select * from services_links where linkid=$linkid");
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
?>
