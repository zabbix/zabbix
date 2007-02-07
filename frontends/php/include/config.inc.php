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

function SDI($msg="SDI") { echo "DEBUG INFO: "; var_export($msg); echo BR; } // DEBUG INFO!!!

?>
<?php
	include_once("include/defines.inc.php");

	include_once("include/copt.lib.php");

// GLOBALS
	global $USER_DETAILS, $USER_RIGHTS, $ERROR_MSG, $INFO_MSG;

	$USER_DETAILS	= array();
	$USER_RIGHTS	= array();
	$ERROR_MSG	= array();
	$INFO_MSG	= array();
// END OF GLOBALS

	include_once 	"include/db.inc.php";
	include_once 	"include/html.inc.php";
	include_once 	"include/locales.inc.php";
	include_once 	"include/perm.inc.php";

	include_once 	"include/audit.inc.php";
	include_once 	"include/acknow.inc.php";
	include_once 	"include/autoregistration.inc.php";
	include_once 	"include/escalations.inc.php";
	include_once 	"include/hosts.inc.php";
	include_once 	"include/users.inc.php";
	include_once 	"include/graphs.inc.php";
	include_once 	"include/items.inc.php";
	include_once 	"include/screens.inc.php";
	include_once 	"include/triggers.inc.php";
	include_once 	"include/actions.inc.php";
        include_once    "include/events.inc.php";
	include_once 	"include/profiles.inc.php";
	include_once 	"include/services.inc.php";
	include_once 	"include/maps.inc.php";
	include_once 	"include/media.inc.php";

// Include Validation

	include_once 	"include/validate.inc.php";

// Include Classes
	include_once("include/classes/ctag.inc.php");
	include_once("include/classes/cvar.inc.php");
	include_once("include/classes/cspan.inc.php");
	include_once("include/classes/cimg.inc.php");
	include_once("include/classes/clink.inc.php");
	include_once("include/classes/chelp.inc.php");
	include_once("include/classes/cbutton.inc.php");
	include_once("include/classes/ccombobox.inc.php");
	include_once("include/classes/ctable.inc.php");
	include_once("include/classes/ctableinfo.inc.php");
	include_once("include/classes/ctextarea.inc.php");
	include_once("include/classes/ctextbox.inc.php");
	include_once("include/classes/cpassbox.inc.php");
	include_once("include/classes/cform.inc.php");
	include_once("include/classes/cfile.inc.php");
	include_once("include/classes/ccheckbox.inc.php");
	include_once("include/classes/clistbox.inc.php");
	include_once("include/classes/cform.inc.php");
	include_once("include/classes/cformtable.inc.php");
	include_once("include/classes/cmap.inc.php");
	include_once("include/classes/cflash.inc.php");
	include_once("include/classes/ciframe.inc.php");

// Include Tactical Overview modules
	include_once("include/classes/chostsinfo.mod.php");
	include_once("include/classes/ctriggerinfo.mod.php");
	include_once("include/classes/cserverinfo.mod.php");
	include_once("include/classes/cflashclock.mod.php");


	function zbx_stripslashes($value){
		if(is_array($value)){
			$value = array_map('zbx_stripslashes',$value);
		} elseif (is_string($value)){
			$value = stripslashes($value);
		}
		return $value;
	}

	function get_request($name, $def){
		global  $_REQUEST;
		if(isset($_REQUEST[$name]))
			return $_REQUEST[$name];
		else
			return $def;
	}

	function info($msg)
	{
		global $INFO_MSG;

		if(is_array($INFO_MSG))
		{
			array_push($INFO_MSG,$msg);
		}
		else
		{
			$INFO_MSG=array($msg);
		}
	}

	function error($msg)
	{
		global $ERROR_MSG;
		if(is_array($ERROR_MSG))
		{
			array_push($ERROR_MSG,$msg);
		}
		else
		{
			$ERROR_MSG=array($msg);
		}
	}

	function getmicrotime()
	{
		list($usec, $sec) = explode(" ",microtime()); 
		return ((float)$usec + (float)$sec); 
	} 

	function	iif($bool,$a,$b)
	{
		if($bool)
		{
			return $a;
		}
		else
		{
			return $b;
		}
	}

	function	iif_echo($bool,$a,$b)
	{
		echo iif($bool,$a,$b);
	}

	function	convert_units($value,$units)
	{
// Special processing for unix timestamps
		if($units=="unixtime")
		{
			$ret=date("Y.m.d H:i:s",$value);
			return $ret;
		}
// Special processing of uptime
		if($units=="uptime")
		{
			$ret="";
			$days=floor($value/(24*3600));
			if($days>0)
			{
				$value=$value-$days*(24*3600);
			}
			$hours=floor($value/(3600));
			if($hours>0)
			{
				$value=$value-$hours*3600;
			}
			$min=floor($value/(60));
			if($min>0)
			{
				$value=$value-$min*(60);
			}
			if($days==0)
			{
				$ret = sprintf("%02d:%02d:%02d", $hours, $min, $value);	
			}
			else
			{
				$ret = sprintf("%d days, %02d:%02d:%02d", $days, $hours, $min, $value);	
			}
			return $ret;
		}
// Special processing for seconds
		if($units=="s")
		{
			$ret="";

			$t=floor($value/(365*24*3600));
			if($t>0)
			{
				$ret=$t."y";
				$value=$value-$t*(365*24*3600);
			}
			$t=floor($value/(30*24*3600));
			if($t>0)
			{
				$ret=$ret.$t."m";
				$value=$value-$t*(30*24*3600);
			}
			$t=floor($value/(24*3600));
			if($t>0)
			{
				$ret=$ret.$t."d";
				$value=$value-$t*(24*3600);
			}
			$t=floor($value/(3600));
			if($t>0)
			{
				$ret=$ret.$t."h";
				$value=$value-$t*(3600);
			}
			$t=floor($value/(60));
			if($t>0)
			{
				$ret=$ret.$t."m";
				$value=$value-$t*(60);
			}
			$ret=$ret.round($value)."s";
		
			return $ret;	
		}

		$u="";

// Special processing for bits (kilo=1000, not 1024 for bits)
		if( ($units=="b") || ($units=="bps"))
		{
			$abs=abs($value);

			if($abs<1000)
			{
				$u="";
			}
			else if($abs<1000*1000)
			{
				$u="K";
				$value=$value/1000;
			}
			else if($abs<1000*1000*1000)
			{
				$u="M";
				$value=$value/(1000*1000);
			}
			else
			{
				$u="G";
				$value=$value/(1000*1000*1000);
			}
	
			if(round($value) == round($value,2))
			{
				$s=sprintf("%.0f",$value);
			}
			else
			{
				$s=sprintf("%.2f",$value);
			}

			return "$s $u$units";
		}


		if($units=="")
		{
			if(round($value) == round($value,2))
			{
				return sprintf("%.0f",$value);
			}
			else
			{
				return sprintf("%.2f",$value);
			}
		}

		$abs=abs($value);

		if($abs<1024)
		{
			$u="";
		}
		else if($abs<1024*1024)
		{
			$u="K";
			$value=$value/1024;
		}
		else if($abs<1024*1024*1024)
		{
			$u="M";
			$value=$value/(1024*1024);
		}
		else if($abs<1024*1024*1024*1024)
		{
			$u="G";
			$value=$value/(1024*1024*1024);
		}
		else
		{
			$u="T";
			$value=$value/(1024*1024*1024*1024);
		}

		if(round($value) == round($value,2))
		{
			$s=sprintf("%.0f",$value);
		}
		else
		{
			$s=sprintf("%.2f",$value);
		}

		return "$s $u$units";
	}

	function	get_template_permission_str($num)
	{
		$str=SPACE;
		if(($num&1)==1)	$str=$str.S_ADD.SPACE;
		if(($num&2)==2)	$str=$str.S_UPDATE.SPACE;
		if(($num&4)==4)	$str=$str.S_DELETE.SPACE;
		return $str;
	}
	
	function	get_media_count_by_userid($userid)
	{
		$sql="select count(mediaid) as cnt from media where userid=$userid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		return $row["cnt"]; 
	}
	
	function	get_action_count_by_triggerid($triggerid)
	{
		$cnt=0;

		$sql="select count(actionid) as cnt from actions where triggerid=$triggerid and scope=0";
		$result=DBselect($sql);
		$row=DBfetch($result);

		$cnt=$cnt+$row["cnt"];

		$sql="select count(actionid) as cnt from actions where scope=2";
		$result=DBselect($sql);
		$row=DBfetch($result);

		$cnt=$cnt+$row["cnt"];

		$sql="select distinct h.hostid from hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=$triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="select count(*) as cnt from actions a,hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and a.triggerid=".$row["hostid"]." and a.scope=1";
			$result2=DBselect($sql);
			$row2=DBfetch($result2);
			$cnt=$cnt+$row2["cnt"];
		}

		return $cnt; 
	}

//	The hash has form <md5sum of triggerid>,<sum of priorities>
	function	calc_trigger_hash()
	{
		$priorities=0;
		for($i=0;$i<=5;$i++)
		{
	        	$result=DBselect("select count(*) as cnt from triggers t,hosts h,items i,functions f  where t.value=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status=0 and t.priority=$i");
			$row=DBfetch($result);
			$priorities+=pow(100,$i)*$row["cnt"];
		}
		$triggerids="";
	       	$result=DBselect("select t.triggerid from triggers t,hosts h,items i,functions f  where t.value=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status=0");
		while($row=DBfetch($result))
		{
			$triggerids="$triggerids,".$row["triggerid"];
		}
		$md5sum=md5($triggerids);

		return	"$priorities,$md5sum";
	}

	function	get_function_by_functionid($functionid)
	{
		$sql="select * from functions where functionid=$functionid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No function with functionid=[$functionid]");
		}
		return	$item;
	}

	function	select_config()
	{
		$sql="select * from config";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("Unable to select configuration");
		}
		return	$row;
	}

	function	show_infomsg()
	{
		global	$INFO_MSG;
		global	$ERROR_MSG;
		if(is_array($INFO_MSG) && count($INFO_MSG)>0)
		{
			echo "<p align=center class=\"info\">";
			while($val = array_shift($INFO_MSG))
			{
				echo $val.BR;
			}
			echo "</p>";
		}
	}

	function	show_messages($bool=TRUE,$msg=NULL,$errmsg=NULL)
	{
		global	$ERROR_MSG;

		if(!$bool)
		{
			if(!is_null($errmsg))
				$msg="ERROR:".$errmsg;

			$color="#AA0000";
		}
		else
		{
			$color="#223344";
		}

		if(isset($msg))
		{
			echo "<p align=center>";
			echo "<font color='$color'>";
			echo "<b>[$msg]</b>";
			echo "</font>";
			echo "</p>";
		}

		show_infomsg();

		if(is_array($ERROR_MSG) && count($ERROR_MSG)>0)
		{
			echo "<p align=center class=\"error\">";
			while($val = array_shift($ERROR_MSG))
			{
				echo $val.BR;
			}
			echo "</p>";
		}
	}

	function	show_message($msg)
	{
		show_messages(TRUE,$msg,'');
	}

	function	show_error_message($msg)
	{
		show_messages(FALSE,'',$msg);
	}

	function	parse_period($str)
	{
		$out = NULL;
		$str = trim($str,';');
		$periods = split(';',$str);
		foreach($periods as $preiod)
		{
			if(!ereg('^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$', $preiod, $arr))
				return NULL;
			for($i = $arr[1]; $i <= $arr[2]; $i++)
			{
				if(!isset($out[$i])) $out[$i] = array();
				array_push($out[$i],
					array(
						'start_h'	=> $arr[3],
						'start_m'	=> $arr[4],
						'end_h'		=> $arr[5],
						'end_m'		=> $arr[6]
					));
			}
		}
		return $out;
	}

	function	find_period_start($periods,$time)
	{
		$date = getdate($time);
		$wday = $date['wday'] == 0 ? 7 : $date['wday'];
		$curr = $date['hours']*100+$date['minutes'];

		if(isset($periods[$wday]))
		{
			$next_h = -1;
			$next_m = -1;
			foreach($periods[$wday] as $period)
			{
				$per_start = $period['start_h']*100+$period['start_m'];
				if($per_start > $curr)
				{
					if(($next_h == -1 && $next_m == -1) || ($per_start < ($next_h*100 + $next_m)))
					{
						$next_h = $period['start_h'];
						$next_m = $period['start_m'];
					}
					continue;
				}
				$per_end = $period['end_h']*100+$period['end_m'];
				if($per_end <= $curr) continue;
				return $time;
			}
			if($next_h >= 0 && $next_m >= 0)
			{
				return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);
			}
		}
		for($days=1; $days < 7 ; ++$days)
		{
			$new_wday = (($wday + $days - 1)%7 + 1);
			if(isset($periods[$new_wday ]))
			{
				$next_h = -1;
				$next_m = -1;
				foreach($periods[$new_wday] as $period)
				{
					$per_start = $period['start_h']*100+$period['start_m'];
					if(($next_h == -1 && $next_m == -1) || ($per_start < ($next_h*100 + $next_m)))
					{
						$next_h = $period['start_h'];
						$next_m = $period['start_m'];
					}
				}
				if($next_h >= 0 && $next_m >= 0)
				{
					return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'] + $days, $date['year']);
				}
			}
		}
		return -1;
	}

	function	find_period_end($periods,$time,$max_time)
	{
		$date = getdate($time);
		$wday = $date['wday'] == 0 ? 7 : $date['wday'];
		$curr = $date['hours']*100+$date['minutes'];
//SDI("find_end: ".date('r',$time));
		if(isset($periods[$wday]))
		{
			$next_h = -1;
			$next_m = -1;
			foreach($periods[$wday] as $period)
			{
				$per_start = $period['start_h']*100+$period['start_m'];
				$per_end = $period['end_h']*100+$period['end_m'];
				if($per_start > $curr) continue;
				if($per_end < $curr) continue;

				if(($next_h == -1 && $next_m == -1) || ($per_end > ($next_h*100 + $next_m)))
				{
					$next_h = $period['end_h'];
					$next_m = $period['end_m'];
				}
			}
			if($next_h >= 0 && $next_m >= 0)
			{
				$new_time = mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);

				if($new_time == $time)
					return $time;
				if($new_time > $max_time)
					return $max_time;

				$next_time = find_period_end($periods,$new_time,$max_time);
				if($next_time < 0)
					return $new_time;
				else
					return $next_time;
			}
		}
		return -1;
	}

	function	validate_period(&$str)
	{
/* // simple check
		$per_expr = '[1-7]-[1-7],[0-9]{1,2}:[0-9]{1,2}-[0-9]{1,2}:[0-9]{1,2}';
		$regexp = '^'.$per_expr.'(;'.$per_expr.')*[;]?$';
		if(!ereg($regexp, $str, $arr))
			return -1;

		return 0;
*/
		$str = trim($str,';');
		$out = "";
		$periods = split(';',$str);
		foreach($periods as $preiod)
		{
			// arr[idx]   1       2         3             4            5            6
			if(!ereg('^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$', $preiod, $arr))
				return -1;

			if($arr[1] > $arr[2]) // check week day
				return -1;
			if($arr[3] > 23 || $arr[3] < 0 || $arr[5] > 24 || $arr[5] < 0) // check hour
				return -1;
			if($arr[4] > 59 || $arr[4] < 0 || $arr[6] > 59 || $arr[6] < 0) // check min
				return -1;
			if(($arr[5]*100 + $arr[6]) > 2400) // check max time 24:00
				return -1;
			if(($arr[3] * 100 + $arr[4]) >= ($arr[5] * 100 + $arr[6])) // check time period
				return -1;

			$out .= sprintf("%d-%d,%02d:%02d-%02d:%02d",$arr[1],$arr[2],$arr[3],$arr[4],$arr[5],$arr[6]).';';
		}
		$str = $out;
//parse_period($str);
		return 0;
	}

	function	validate_float($str)
	{
//		echo "Validating float:$str<br>";
		if (eregi('^[ ]*([0-9]+)((\.)?)([0-9]*[KMG]{0,1})[ ]*$', $str, $arr)) 
		{
			return 0;
		}
		else
		{
			return -1;
		}
	}

// Check if str has format #<float> or <float>
	function	validate_ticks($str)
	{
//		echo "Validating float:$str<br>";
		if (eregi('^[ ]*#([0-9]+)((\.)?)([0-9]*)[ ]*$', $str, $arr)) 
		{
			return 0;
		}
		else return validate_float($str);
	}

// Does expression match server:key.function(param) ?
	function	validate_simple_expression($expression)
	{
		/* '-' must be last in the list of character, otherwise it won't be accepted */
		if (eregi('^\{([0-9a-zA-Z\_\.\$-]+)\:([]\[0-9a-zA-Z\_\*\/\.\,\:\(\)\+\$ -]+)\.([a-z]{3,11})\(([#0-9a-zA-Z\_\/\.\,[:space:]]+)\)\}$', $expression, $arr))
		{
			$host=$arr[1];
			$key=$arr[2];
			$function=$arr[3];
			$parameter=$arr[4];

//SDI($host);
//SDI($key);
//SDI($function);
//SDI($parameter);

			$sql="select count(*) as cnt from hosts h,items i where h.host=".zbx_dbstr($host).
				" and i.key_=".zbx_dbstr($key)." and h.hostid=i.hostid";
//SDI($sql);
			$row=DBfetch(DBselect($sql));
			if($row["cnt"]!=1)
			{
				error("No such host ($host) or monitored parameter ($key)");
				return -1;
			}

			if(	($function!="last")&&
				($function!="diff")&&
				($function!="min") &&
				($function!="max") &&
				($function!="avg") &&
				($function!="sum") &&
				($function!="count") &&
				($function!="prev")&&
				($function!="delta")&&
				($function!="change")&&
				($function!="abschange")&&
				($function!="nodata")&&
				($function!="time")&&
				($function!="dayofweek")&&
				($function!="date")&&
				($function!="now")&&
				($function!="str")&&
				($function!="fuzzytime")&&
				($function!="logseverity")&&
				($function!="logsource")&&
				($function!="regexp")
			)
			{
				error("Unknown function [$function]");
				return -1;
			}


			if(in_array($function,array("last","diff","count",
						"prev","change","abschange","nodata","time","dayofweek",
						"date","now","fuzzytime"))
				&& (validate_float($parameter)!=0) )
			{
				error("[$parameter] is not a float");
				return -1;
			}

			if(in_array($function,array("min","max","avg","sum",
						"delta"))
				&& (validate_ticks($parameter)!=0) )
			{
				error("[$parameter] is not a float");
				return -1;
			}
		}
		else
		{
			error("Expression [$expression] does not match to [server:key.func(param)]");
			return -1;
		}
		return 0;
	}
/*
	function	validate_expression($expression)
	{
//		echo "Validating expression: $expression<br>";

		$ok=0;
// Replace all {server:key.function(param)} with 0
		while($ok==0)
		{
//			echo "Expression:$expression<br>";
			$arr="";
			if (eregi('^((.)*)[ ]*(\{((.)*)\})[ ]*((.)*)$', $expression, $arr)) 
			{
//				for($i=0;$i<20;$i++)
//				{
//					if($arr[$i])
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_simple_expression($arr[3])!=0)
				{
					return -1;
				}
				$expression=$arr[1]."0".$arr[6];
	                }
			else
			{
				$ok=1;
			}
		}
//		echo "Result:$expression<br><hr>";

		$ok=0;
		while($ok==0)
		{
// 	Replace all <float> <sign> <float> <K|M|G> with 0
//			echo "Expression:$expression<br>";
			$arr="";
			if (eregi('^((.)*)([0-9\.]+[A-Z]{0,1})[ ]*([\&\|\>\<\=\+\-\*\/\#]{1})[ ]*([0-9\.]+[A-Z]{0,1})((.)*)$', $expression, $arr)) 
			{
//				echo "OK<br>";
//				for($i=0;$i<50;$i++)
//				{
//					if($arr[$i]!="")
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_float($arr[3])!=0)
				{
					error("[".$arr[3]."] is not a float");
					return -1;
				}
				if(validate_float($arr[5])!=0)
				{
					error("[".$arr[5]."] is not a float");
					return -1;
				}
				$expression=$arr[1]."(0)".$arr[6];
	                }
			else
			{
				$ok=1;
			}


// 	Replace all (float) with 0
//			echo "Expression2:[$expression]<br>";
			$arr="";
			if (eregi('^((.)*)(\(([ 0-9\.]+)\))((.)*)$', $expression, $arr)) 
			{
//				echo "OK<br>";
//				for($i=0;$i<30;$i++)
//				{
//					if($arr[$i]!="")
//						echo "  $i: ",$arr[$i],"<br>";
//				}
				if(validate_float($arr[4])!=0)
				{
					error("[".$arr[4]."] is not a float");
					return -1;
				}
				$expression=$arr[1]."0".$arr[5];
				$ok=0;
	                }
			else
			{
				$ok=1;
			}



		}
//		echo "Result:$expression<br><hr>";

		if($expression=="0")
		{
			return 0;
		}

		return 1;
	}
/**/

	function	cr()
	{
		echo "\n";
	}

	# Header for HTML pages

	function	show_header($title,$dorefresh=0,$nomenu=0,$noauth=0)
	{
		global $page;
		global $USER_DETAILS;
COpt::profiling_start("page");

		if($noauth==0)
		{
			global $TRANSLATION;
			if(!isset($TRANSLATION) || !is_array($TRANSLATION))	$TRANSLATION = array();

			check_authorisation();
			include_once "include/locales/".$USER_DETAILS["lang"].".inc.php";
			process_locales();
		}
		include_once "include/locales/en_gb.inc.php";
		process_locales();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo S_HTML_CHARSET; ?>">
<meta name="Author" content="ZABBIX SIA">
<link rel="stylesheet" href="css.css">
<?php
//	if($USER_DETAILS['alias']=='guest')
//	{
//		$refresh=2*$refresh;
//	}
	if(defined($title))	$title=constant($title);
	if($dorefresh && $USER_DETAILS["refresh"])
	{
		echo "	<meta http-equiv=\"refresh\" content=\"".$USER_DETAILS["refresh"]."\">\n";
		echo "	<title>$title [refreshed every ".$USER_DETAILS["refresh"]." sec]</title>\n";
	}
	else
	{
		echo "	<title>$title</title>\n";
	}

?>
</head>
<body>
<?php
	if($nomenu == 0)
	{
	$menu=array(
		"view"=>array(
				"label"=>S_MONITORING,
				"pages"=>array("overview.php","latest.php","tr_status.php","queue.php","events.php","actions.php","maps.php","charts.php","screens.php","srv_status.php","alarms.php","history.php","tr_comments.php","report3.php","profile.php","acknow.php"),
				"level2"=>array(
					array("label"=>S_OVERVIEW,"url"=>"overview.php"),
					array("label"=>S_LATEST_DATA,"url"=>"latest.php"),
					array("label"=>S_TRIGGERS,"url"=>"tr_status.php"),
					array("label"=>S_QUEUE,"url"=>"queue.php"),
					array("label"=>S_EVENTS,"url"=>"events.php"),
					array("label"=>S_ACTIONS,"url"=>"actions.php"),
					array("label"=>S_MAPS,"url"=>"maps.php"),
					array("label"=>S_GRAPHS,"url"=>"charts.php"),
					array("label"=>S_SCREENS,"url"=>"screens.php"),
					array("label"=>S_IT_SERVICES,"url"=>"srv_status.php")
					)
				),
		"cm"=>array(
				"label"=>S_INVENTORY,
				"pages"=>array("hostprofiles.php"),
				"level2"=>array(
					array("label"=>S_HOSTS,"url"=>"hostprofiles.php")
					)
				),
		"reports"=>array(
				"label"=>S_REPORTS,
				"pages"=>array("report1.php","report2.php","report4.php","report5.php"),
				"level2"=>array(
					array("label"=>S_STATUS_OF_ZABBIX,"url"=>"report1.php"),
					array("label"=>S_AVAILABILITY_REPORT,"url"=>"report2.php"),
					array("label"=>S_NOTIFICATIONS,"url"=>"report4.php"),
					array("label"=>S_TRIGGERS_TOP_100,"url"=>"report5.php"),   
					)
				),
		"configuration"=>array(
				"label"=>S_CONFIGURATION,
				"pages"=>array("config.php","users.php","audit.php","hosts.php","items.php","triggers.php","sysmaps.php","graphs.php","screenconf.php","services.php","sysmap.php","media.php","screenedit.php","graph.php","actionconf.php","bulkloader.php"),
				"level2"=>array(
					array("label"=>S_GENERAL,"url"=>"config.php"),
					array("label"=>S_USERS,"url"=>"users.php"),
					array("label"=>S_AUDIT,"url"=>"audit.php"),
					array("label"=>S_HOSTS,"url"=>"hosts.php"),
					array("label"=>S_ITEMS,"url"=>"items.php"),
					array("label"=>S_TRIGGERS,"url"=>"triggers.php"),
					array("label"=>S_ACTIONS,"url"=>"actionconf.php"),
					array("label"=>S_MAPS,"url"=>"sysmaps.php"),
					array("label"=>S_GRAPHS,"url"=>"graphs.php"),
					array("label"=>S_SCREENS,"url"=>"screenconf.php"),
					array("label"=>S_IT_SERVICES,"url"=>"services.php"),
					array("label"=>S_MENU_BULKLOADER,"url"=>"bulkloader.php")
					)
				),
		"login"=>array(
				"label"=>S_LOGIN,
				"pages"=>array("index.php"),
				"level2"=>array(
					array("label"=>S_LOGIN,"url"=>"index.php"),
					)
				),
		);

	$table = new CTable(NULL,"page_header");
	$table->SetCellSpacing(0);
	$table->SetCellPadding(5);

	$help = new CLink(S_HELP, "http://www.zabbix.com/manual/v1.1/index.php", "small_font");
	$help->SetTarget('_blank');
	$col_r = array($help);
	if($USER_DETAILS["alias"]!="guest") {
		array_push($col_r, "|");		
		array_push($col_r, new CLink(S_PROFILE, "profile.php", "small_font"));
	}

	$logo = new CLink(new CImg("images/general/zabbix.png","ZABBIX"),"http://www.zabbix.com");
	$logo->SetTarget('_blank');
	$table->AddRow(array(new CCol($logo, "page_header_l"), new CCol($col_r, "page_header_r")));

	$table->Show();
?>

<table class="menu" cellspacing=0 cellpadding=5>
<tr>
<?php
	$i=-1;
	foreach($menu as $label=>$sub)
	{
		$i++;
// Check permissions
		if($label=="configuration")
		{
			if(	!check_anyright("Configuration of Zabbix","U")
				&&!check_anyright("User","U")
				&&!check_anyright("Host","U")
				&&!check_anyright("Item","U")
				&&!check_anyright("Graph","U")
				&&!check_anyright("Screen","U")
				&&!check_anyright("Network map","U")
				&&!check_anyright("Service","U")
			)
			{
				continue;
			}
			if(	!check_anyright("Default permission","R")
				&&!check_anyright("Host","R")
			)
			{
				continue;
			}

		}
		
		if($label=='cm' && !check_anyright("Host","R"))
		{
			continue;
		}
		
		if(	$label=='reports' 
			&&!check_anyright("Default permission","R")
			&&!check_anyright("Host","R")
			&&!check_anyright("User","R")
			&&!check_anyright("Host","R"))
		{
			continue;
		}

// End of check permissions
		$active=0;
		foreach($sub["pages"] as $label2)
		{
			if($page["file"]==$label2)
			{
				$active=1;
				$active_level1=$label;
			}
		}
		if($i==0)	$url=get_profile("web.menu.view.last",0);
		else if($i==1)	$url=get_profile("web.menu.cm.last",0);
		else if($i==2)	$url=get_profile("web.menu.reports.last",0);
		else if($i==3)	$url=get_profile("web.menu.config.last",0);
		else if($i==4)	$url="0";

		if($url=="0")	$url=$sub["level2"][0]["url"];
		if($active==1) 
		{
			global $page;
			$class = "horizontal_menu";
			if(isset($page["menu.url"]))
				$url = $page["menu.url"];
			else
				$url	= $page["file"];
		}
		else
		{
			$class = "horizontal_menu_n";
		}
		echo "<td class=\"$class\" height=24 colspan=9><b><a href=\"$url\" class=\"highlight\">".$sub["label"]."</a></b></td>\n";
	}
?>
</tr>
</table>

<table class="menu" width="100%" cellspacing=0 cellpadding=5>
<tr><td class="horizontal_menu" height=24 colspan=9><b>
<?php
	if(isset($active_level1))
	foreach($menu[$active_level1]["level2"] as $label=>$sub)
	{
// Check permissions
		if(($sub["url"]=="latest.php")&&!check_anyright("Host","R"))							continue;
		if(($sub["url"]=="overview.php")&&!check_anyright("Host","R"))							continue;
		if(($sub["url"]=="tr_status.php")&&!check_anyright("Host","R"))							continue;
		if(($sub["url"]=="queue.php")&&!check_anyright("Host","R"))							continue;
		if(($sub["url"]=="events.php")&&!check_anyright("Default permission","R"))					continue;
		if(($sub["url"]=="actions.php")&&!check_anyright("Default permission","R"))					continue;
		if(($sub["url"]=="maps.php")&&!check_anyright("Network map","R"))						continue;
		if(($sub["url"]=="charts.php")&&!check_anyright("Graph","R"))							continue;
		if(($sub["url"]=="screens.php")&&!check_anyright("Screen","R"))							continue;
		if(($sub["url"]=="srv_status.php")&&!check_anyright("Service","R"))						continue;
		if(($sub["url"]=="report1.php")&&!check_anyright("Default permission","R"))					continue;
		if(($sub["url"]=="report2.php")&&!check_anyright("Host","R"))							continue;
		if(($sub["url"]=="report4.php")&&!check_anyright("User","R"))							continue;
		if(($sub["url"]=="report5.php")&&!check_anyright("Host","R"))							continue;
		if(($sub["url"]=="config.php")&&!check_anyright("Configuration of Zabbix","U"))					continue;
		if(($sub["url"]=="users.php")&&!check_anyright("User","U"))							continue;
		if(($sub["url"]=="media.php")&&!check_anyright("User","U"))							continue;
		if(($sub["url"]=="audit.php")&&!check_anyright("Audit","U"))							continue;
		if(($sub["url"]=="hosts.php")&&!check_anyright("Host","U"))							continue;
		if(($sub["url"]=="items.php")&&!check_anyright("Item","U"))							continue;
		if(($sub["url"]=="triggers.php")&&!check_anyright("Host","U"))							continue;
		if(($sub["url"]=="sysmaps.php")&&!check_anyright("Network map","U"))						continue;
		if(($sub["url"]=="sysmap.php")&&!check_anyright("Network map","U"))						continue;
		if(($sub["url"]=="graphs.php")&&!check_anyright("Graph","U"))							continue;
		if(($sub["url"]=="graph.php")&&!check_anyright("Graph","U"))							continue;
		if(($sub["url"]=="screenedit.php")&&!check_anyright("Screen","U"))						continue;
		if(($sub["url"]=="screenconf.php")&&!check_anyright("Screen","U"))						continue;
		if(($sub["url"]=="services.php")&&!check_anyright("Service","U"))						continue;
		if(($sub["url"]=="hostprofiles.php")&&!check_anyright("Host","R"))						continue;

		echo "<a href=\"".$sub["url"]."\" class=\"highlight\">".$sub["label"]."</a><span class=\"divider\">".SPACE.SPACE."|".SPACE."</span>\n";
	}
?>
</b></td></tr>
</table>
<br/>
<?php
		}
	}

	# Show screen cell containing plain text values
	function&	get_screen_plaintext($itemid,$elements)
	{
		global $DB_TYPE;

		$item=get_item_by_itemid($itemid);
		switch($item["value_type"])
		{
			case ITEM_VALUE_TYPE_FLOAT:	$history_table = "history";		break;
			case ITEM_VALUE_TYPE_UINT64:	$history_table = "history_uint";	break;
			case ITEM_VALUE_TYPE_TEXT:	$history_table = "history_text";	break;
			default:			$history_table = "history_str";		break;
		}

		$sql="select h.clock,h.value,i.valuemapid from ".$history_table." h, items i where".
			" h.itemid=i.itemid and i.itemid=$itemid order by clock desc";

                $result=DBselect($sql,$elements);

		$table = new CTableInfo();
		$table->SetHeader(array(S_TIMESTAMP,item_description($item["description"],$item["key_"])));
		while($row=DBfetch($result))
		{
			switch($item["value_type"])
			{
				case ITEM_VALUE_TYPE_TEXT:	
					if($DB_TYPE == "ORACLE")
					{
						if(isset($row["value"]))
						{
							$row["value"] = $row["value"]->load();
						}
						else
						{
							$row["value"] = "";
						}
					}
					/* do not use break */
				case ITEM_VALUE_TYPE_STR:	
					$value = nbsp(htmlspecialchars($row["value"]));
					break;
				
				default:
					$value = $row["value"];
					break;
			}

			if($row["valuemapid"] > 0)
				$value = replace_value_by_map($value, $row["valuemapid"]);

			$table->AddRow(array(date(S_DATE_FORMAT_YMDHMS,$row["clock"]),	$value));
		}
		return $table;
	}

	function	get_image_by_name($name,$imagetype=NULL)
	{
		global $DB_TYPE;

		$sql="select image from images where name=".zbx_dbstr($name); 
		if(isset($imagetype))
			$sql .= "and imagetype=".$imagetype;

		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			if($DB_TYPE == "ORACLE")
			{
				if(!isset($row['image']))
					return 0;

				$row['image'] = $row['image']->load();
			}
			else if($DB_TYPE == "POSTGRESQL")
			{
				$row['image'] = pg_unescape_bytea($row['image']);
			}

			return	$row;
		}
		else
		{
			return 0;
		}
	}

	function	get_image_by_imageid($imageid)
	{
		global $DB_TYPE;

		$result=DBselect('select * from images where imageid='.$imageid);
		$row=DBfetch($result);
		if($row)
		{
			if($DB_TYPE == "ORACLE")
			{
				if(!isset($row['image']))
					return 0;

				$row['image'] = $row['image']->load();
			}
			else if($DB_TYPE == "POSTGRESQL")
			{
				$row['image'] = pg_unescape_bytea($row['image']);
//SDI($row['image']);
			}
			return	$row;
		}
		else
		{
			return 0;
		}
	}

	function	add_image($name,$imagetype,$file)
	{
		global $DB_TYPE;
		global $DB;

		if(!is_null($file))
		{
			if($file["error"] != 0 || $file["size"]==0)
			{
				error("Incorrect Image");
				return FALSE;
			}
			if($file["size"]<1024*1024)
			{
				$image=fread(fopen($file["tmp_name"],"r"),filesize($file["tmp_name"]));
				if($DB_TYPE == "ORACLE")
				{
					$lobimage = OCINewDescriptor($DB, OCI_D_LOB);

					$sql = "insert into images (name,imagetype,image)".
						" values (".zbx_dbstr($name).",".$imagetype.",EMPTY_BLOB())".
						" return image into :image";
					$stid = OCIParse($DB, $sql);
					if(!$stid)
					{
						$e = ocierror($stid);
						error("Parse SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						return false;
					}

					OCIBindByName($stid, ':image', $lobimage, -1, OCI_B_BLOB);

					$result = OCIExecute($stid, OCI_DEFAULT);
					if(!$result){
						$e = ocierror($stid);
						error("Execute SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						return false;
					}

					if ($lobimage->save($image)) {
						OCICommit($DB);
					}
					else {
						OCIRollback($DB);
						error("Couldn't save image!\n");
						return false;
					}

					$lobimage->free();
					OCIFreeStatement($stid);

					return $stid;
				}
				else if($DB_TYPE == "POSTGRESQL")
				{
					$image = pg_escape_bytea($image);

					$sql = "insert into images (name,imagetype,image)".
						" values (".zbx_dbstr($name).",".$imagetype.",'".$image."')";
					return	DBexecute($sql);
				}
				$sql = "insert into images (name,imagetype,image)".
					" values (".zbx_dbstr($name).",".$imagetype.",".zbx_dbstr($image).")";
				return	DBexecute($sql);
			}
			else
			{
				error("Image size must be less than 1Mb");
				return false;
			}
		}
		else
		{
			error("Select image to download");
			return false;
		}
	}

	function	update_image($imageid,$name,$imagetype,$file)
	{
		global $DB_TYPE;
		global $DB;

		if(!is_null($file))
		{
			if($file["error"] != 0 || $file["size"]==0)
			{
				error("Incorrect Image");
				return FALSE;
			}
			if($file["size"]<1024*1024)
			{
				$image=fread(fopen($file["tmp_name"],"r"),filesize($file["tmp_name"]));

				if($DB_TYPE == "ORACLE")
				{

					$result = DBexecute("update images set name=".zbx_dbstr($name).
						",imagetype=".zbx_dbstr($imagetype).
						" where imageid=$imageid");

					if(!$result) return $result;

					$stid = OCIParse($DB, "select image from images where imageid=".$imageid." for update");

					$result = OCIExecute($stid, OCI_DEFAULT);
					if(!$result){
						$e = ocierror($stid);
						error("Execute SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
						OCIRollback($DB);
						return false;
					}

					$row = DBfetch($stid);

					$lobimage = $row['image'];

//					if (!($lobimage->erase()))
//					{
//						OCIRollback($DB);
//						error("Failed to truncate LOB\n");
//						return false;
//					}

					if (!$lobimage->save($image)) {
						OCIRollback($DB);
					} else {
						OCICommit($DB);
					}

					$lobimage->free();

					return $stid;
				}
				else if($DB_TYPE == "POSTGRESQL")
				{
					$image = pg_escape_bytea($image);
					$sql="update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype).
						",image='".$image."' where imageid=$imageid";
					return	DBexecute($sql);
				}

				$sql="update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype).
					",image=".zbx_dbstr($image)." where imageid=$imageid";
				return	DBexecute($sql);
			}
			else
			{
				error("Image size must be less than 1Mb");
				return FALSE;
			}
		}
		else
		{
				$sql="update images set name=".zbx_dbstr($name).",imagetype=".zbx_dbstr($imagetype)." where imageid=$imageid";
				return	DBexecute($sql);
		}
	}

	function	delete_image($imageid)
	{
		$sql="delete from images where imageid=$imageid";
		return	DBexecute($sql);
	}

	# Delete Alert by actionid

	function	delete_alert_by_actionid( $actionid )
	{
		$sql="delete from alerts where actionid=$actionid";
		return	DBexecute($sql);
	}

	function	delete_rights_by_userid($userid )
	{
		$sql="delete from rights where userid=$userid";
		return	DBexecute($sql);
	}

	# Delete from History

	function	delete_history_by_itemid($itemid, $use_housekeeper=0)
	{
		$result = delete_trends_by_itemid($itemid,$use_housekeeper);
		if(!$result)	return $result;

		if($use_housekeeper)
		{
			DBexecute("insert into housekeeper (tablename,field,value)".
				" values ('history_log','itemid',$itemid)");
			DBexecute("insert into housekeeper (tablename,field,value)".
				" values ('history_uint','itemid',$itemid)");
			DBexecute("insert into housekeeper (tablename,field,value)".
				" values ('history_str','itemid',$itemid)");
			DBexecute("insert into housekeeper (tablename,field,value)".
				" values ('history','itemid',$itemid)");
			return TRUE;
		}

		DBexecute("delete from history_log where itemid=$itemid");
		DBexecute("delete from history_uint where itemid=$itemid");
		DBexecute("delete from history_str where itemid=$itemid");
		DBexecute("delete from history where itemid=$itemid");
		return TRUE;
	}

	# Delete from Trends

	function	delete_trends_by_itemid($itemid, $use_housekeeper=0)
	{
		if($use_housekeeper)
		{
			DBexecute("insert into housekeeper (tablename,field,value)".
				" values ('trends','itemid',$itemid)");
			return TRUE;
		}
		return	DBexecute("delete from trends where itemid=$itemid");
	}

	# Add alarm

	function	get_alarm_by_alarmid($alarmid)
	{
		$db_alarms = DBselect("select * from alarms where alarmid=$alarmid");
		return DBfetch($db_alarms);
	}

	# Reset nextcheck for related items

	function	reset_items_nextcheck($triggerid)
	{
		$sql="select itemid from functions where triggerid=$triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="update items set nextcheck=0 where itemid=".$row["itemid"];
			DBexecute($sql);
		}
	}

	# Delete Media definition by mediatypeid

	function	delete_media_by_mediatypeid($mediatypeid)
	{
		$sql="delete from media where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Delete alrtes by mediatypeid

	function	delete_alerts_by_mediatypeid($mediatypeid)
	{
		$sql="delete from alerts where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	function	get_mediatype_by_mediatypeid($mediatypeid)
	{
		$sql="select * from media_type where mediatypeid=$mediatypeid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No media type with with mediatypeid=[$mediatypeid]");
		}
		return	$item;
	}

	# Delete media type

	function	delete_mediatype($mediatypeid)
	{

		delete_media_by_mediatypeid($mediatypeid);
		delete_alerts_by_mediatypeid($mediatypeid);
		$sql="delete from media_type where mediatypeid=$mediatypeid";
		return	DBexecute($sql);
	}

	# Update media type

	function	update_mediatype($mediatypeid,$type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path,$gsm_modem)
	{
		$ret = 0;

		$sql="select * from media_type where description=".zbx_dbstr($description)." and mediatypeid!=$mediatypeid";
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("An action type with description '$description' already exists.");
		}
		else
		{
			$sql="update media_type set type=$type,description=".zbx_dbstr($description).",smtp_server=".zbx_dbstr($smtp_server).",smtp_helo=".zbx_dbstr($smtp_helo).",smtp_email=".zbx_dbstr($smtp_email).",exec_path=".zbx_dbstr($exec_path).",gsm_modem=".zbx_dbstr($gsm_modem)." where mediatypeid=$mediatypeid";
			$ret =	DBexecute($sql);
		}
		return $ret;
	}

	# Add Media type

	function	add_mediatype($type,$description,$smtp_server,$smtp_helo,$smtp_email,$exec_path,$gsm_modem)
	{
		$ret = 0;

		if($description==""){
			error(S_INCORRECT_DESCRIPTION);
			return 0;
		}

		$sql="select * from media_type where description=".zbx_dbstr($description);
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("An action type with description '$description' already exists.");
		}
		else
		{
			$sql="insert into media_type (type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem) values ($type,".zbx_dbstr($description).",".zbx_dbstr($smtp_server).",".zbx_dbstr($smtp_helo).",".zbx_dbstr($smtp_email).",".zbx_dbstr($exec_path).",".zbx_dbstr($gsm_modem).")";
			$ret = DBexecute($sql);
		}
		return $ret;
	}

	# Add Media definition

	function	add_media( $userid, $mediatypeid, $sendto, $severity, $active, $period)
	{
		if(validate_period($period) != 0)
		{
			error("Icorrect time period");
			return NULL;
		}

		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++)
		{
			$s=$s|pow(2,(int)$severity[$i]);
		}
		$sql="insert into media (userid,mediatypeid,sendto,active,severity,period) values ($userid,".zbx_dbstr($mediatypeid).",".zbx_dbstr($sendto).",$active,$s,".zbx_dbstr($period).")";
		return	DBexecute($sql);
	}

	# Update Media definition

	function	update_media($mediaid, $userid, $mediatypeid, $sendto, $severity, $active, $period)
	{
		if(validate_period($period) != 0)
		{
			error("Icorrect time period");
			return NULL;
		}

		$c=count($severity);
		$s=0;
		for($i=0;$i<$c;$i++)
		{
			$s=$s|pow(2,(int)$severity[$i]);
		}
		$sql="update media set userid=$userid, mediatypeid=$mediatypeid, sendto=".zbx_dbstr($sendto).", active=$active,severity=$s,period=".zbx_dbstr($period)." where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Delete Media definition

	function	delete_media($mediaid)
	{
		$sql="delete from media where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Delete Media definition by userid

	function	delete_media_by_userid($userid)
	{
		$sql="delete from media where userid=$userid";
		return	DBexecute($sql);
	}

	function	delete_profiles_by_userid($userid)
	{
		$sql="delete from profiles where userid=$userid";
		return	DBexecute($sql);
	}

	# Update configuration

//	function	update_config($smtp_server,$smtp_helo,$smtp_email,$alarm_history,$alert_history)
	function	update_config($alarm_history,$alert_history,$refresh_unsupported,$work_period)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}
		if(validate_period($work_period) != 0)
		{
			error("Icorrect work period");
			return NULL;
		}


//		$sql="update config set smtp_server='$smtp_server',smtp_helo='$smtp_helo',smtp_email='$smtp_email',alarm_history=$alarm_history,alert_history=$alert_history";
		$sql="update config set alarm_history=$alarm_history,alert_history=$alert_history,refresh_unsupported=$refresh_unsupported,".
			"work_period=".zbx_dbstr($work_period);
		return	DBexecute($sql);
	}


	# Activate Media

	function	activate_media($mediaid)
	{
		$sql="update media set active=0 where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Disactivate Media

	function	disactivate_media($mediaid)
	{
		$sql="update media set active=1 where mediaid=$mediaid";
		return	DBexecute($sql);
	}

	# Delete User permission

	function	delete_permission($rightid)
	{
		$sql="delete from rights where rightid=$rightid";
		return DBexecute($sql);
	}

	# Delete User definition

	function	delete_user($userid)
	{
		$sql="select * from users where userid=$userid and alias='guest'";
		$result=DBselect($sql);
		if(DBfetch($result))
		{
			error("Cannot delete user 'guest'");
			return	0;
		}


		delete_media_by_userid($userid);
		delete_actions_by_userid($userid);
		delete_rights_by_userid($userid);
		delete_profiles_by_userid($userid);

	// delete user permisions
		DBexecute('delete from rights where name=\'User\' and id='.$userid);

		$sql="delete from users_groups where userid=$userid";
		DBexecute($sql);
		$sql="delete from users where userid=$userid";
		return DBexecute($sql);
	}

	function	show_header2($col1, $col2=SPACE, $before="", $after="")
	{
		echo $before; 
		show_table_header($col1, $col2);
		echo $after;
	}

	function	show_table_header($col1, $col2=SPACE)
	{
		$table = new CTable(NULL,"header");
		$table->SetCellSpacing(0);
		$table->SetCellPadding(1);
		$table->AddRow(array(new CCol($col1,"header_l"), new CCol($col2,"header_r")));
		$table->Show();
	}

	function	insert_time_navigator($itemid,$period,$from)
	{
		$descr=array("January","February","March","April","May","June",
			"July","August","September","October","November","December");
		$sql="select min(clock) as minn,max(clock) as maxx from history where itemid=$itemid";
		$result=DBselect($sql);
		$row=Dvfetch($result);

		if(!row)
		{
			$min=time(NULL);
			$max=time(NULL);
		}
		else
		{
			$min=$row["minn"];
			$max=$row["maxx"];
		}

		$now=time()-3600*$from-$period;

		$year_min=date("Y",$min);   
		$year_max=date("Y",$max);

		$year_now=date("Y",$now);
		$month_now=date("m",$now);
		$day_now=date("d",$now);
		$hour_now=date("H",$now);

		echo "<form method=\"put\" action=\"history.php\">";
		echo "<input name=\"itemid\" type=\"hidden\" value=$itemid size=8>";
		echo "<input name=\"action\" type=\"hidden\" value=\"showgraph\" size=8>";

		echo "Year";
		echo "<select name=\"year\">";
	        for($i=$year_min;$i<=$year_max;$i++)
	        {
			if($i==$year_now)
			{	
	               		echo "<option value=\"$i\" selected>$i";
			}
			else
			{
	               		echo "<option value=\"$i\">$i";
			}
	        }
		echo "</select>";

		echo "Month";
		echo "<select name=\"month\">";
	        for($i=1;$i<=12;$i++)
	        {
			if($i==$month_now)
			{	
	               		echo "<option value=\"$i\" selected>".$descr[$i-1];
			}
			else
			{
	               		echo "<option value=\"$i\">".$descr[$i-1];
			}
	        }
		echo "</select>";

		echo "Day";
		echo "<select name=\"day\">";
	        for($i=1;$i<=31;$i++)
	        {
			if($i==$day_now)
			{	
	               		echo "<option value=\"$i\" selected>$i";
			}
			else
			{
	               		echo "<option value=\"$i\">$i";
			}
	        }
		echo "</select>";

		echo "Hour";
		echo "<select name=\"hour\">";
	        for($i=0;$i<=23;$i++)
	        {
			if($i==$hour_now)
			{	
	               		echo "<option value=\"$i\" selected>$i";
			}
			else
			{
	               		echo "<option value=\"$i\">$i";
			}
	        }
		echo "</select>";

		echo "Period:";
		echo "<select name=\"period\">";
		if($period==3600)
		{
			echo "<option value=\"3600\" selected>1 hour";
		}
		else
		{
			echo "<option value=\"3600\">1 hour";
		}
		if($period==10800)
		{
			echo "<option value=\"10800\" selected>3 hours";
		}
		else
		{
			echo "<option value=\"10800\">3 hours";
		}
		if($period==21600)
		{
			echo "<option value=\"21600\" selected>6 hours";
		}
		else
		{
			echo "<option value=\"21600\">6 hours";
		}
		if($period==86400)
		{
			echo "<option value=\"86400\" selected>24 hours";
		}
		else
		{
			echo "<option value=\"86400\">24 hours";
		}
		if($period==604800)
		{
			echo "<option value=\"604800\" selected>one week";
		}
		else
		{
			echo "<option value=\"604800\">one week";
		}
		if($period==2419200)
		{
			echo "<option value=\"2419200\" selected>one month";
		}
		else
		{
			echo "<option value=\"2419200\">one month";
		}
		echo "</select>";

		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"showgraph\">";

		echo "</form>";
	}

	# Show History Graph

	function	show_history($itemid,$from,$stime,$period)
	{
		$till=date(S_DATE_FORMAT_YMDHMS,time(NULL)-$from*3600);   
		show_table_header("TILL $till (".($period/3600)." HOURs)");

		echo "<center>";
		echo "<TABLE BORDER=0 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";

		echo "<script language=\"JavaScript\" type=\"text/javascript\">";
		echo "if (navigator.appName == \"Microsoft Internet Explorer\")";
		echo "{";
		echo " document.write(\"<IMG SRC='chart.php?itemid=$itemid&period=$period&from=$from&stime=$stime&width=\"+(document.body.clientWidth-108)+\"'>\")";
		echo "}";
		echo "else if (navigator.appName == \"Netscape\")";
		echo "{";
		echo " document.write(\"<IMG SRC='chart.php?itemid=$itemid&period=$period&from=$from&stime=$stime&width=\"+(document.width-108)+\"'>\")";
		echo "}";
		echo "else";
		echo "{";
		echo " document.write(\"<IMG SRC='chart.php?itemid=$itemid&period=$period&from=$from&stime=$stime'>\")";
		echo "}";
		echo "</script>";

		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
		echo "</center>";
	}

	function	show_page_footer()
	{
		global $USER_DETAILS;

		show_messages();

		echo BR;
		$table = new CTable(NULL,"page_footer");
		$table->SetCellSpacing(0);
		$table->SetCellPadding(1);
		$table->AddRow(array(
			new CCol(new CLink(
				S_ZABBIX_VER.SPACE.S_COPYRIGHT_BY.SPACE.S_SIA_ZABBIX,
				"http://www.zabbix.com", "highlight"),
				"page_footer_l"),
			new CCol(array(
					new CSpan(SPACE.SPACE."|".SPACE.SPACE,"divider"),
					S_CONNECTED_AS.SPACE.$USER_DETAILS["alias"]
				),
				"page_footer_r")
			));
		$table->Show();

COpt::profiling_stop("page");
COpt::profiling_stop("script");

		echo "</body>\n";
		echo "</html>\n";
	}

	function	get_status()
	{
		global $DB_TYPE;
		$status = array();
// server
		if( (exec("ps -ef|grep zabbix_server|grep -v grep|wc -l")>0) || (exec("ps -ax|grep zabbix_server|grep -v grep|wc -l")>0) )
		{
			$status["zabbix_server"] = S_YES;
		}
		else
		{
			$status["zabbix_server"] = S_NO;
		}

// history & trends
/*
		if ($DB_TYPE == "MYSQL")
		{
			$row=DBfetch(DBselect("show table status like 'history'"));
			$status["history_count"]  = $row["Rows"];
			$row=DBfetch(DBselect("show table status like 'history_log'"));
			$status["history_count"] += $row["Rows"];
			$row=DBfetch(DBselect("show table status like 'history_str'"));
			$status["history_count"] += $row["Rows"];
			$row=DBfetch(DBselect("show table status like 'history_uint'"));
			$status["history_count"] += $row["Rows"];

			$row=DBfetch(DBselect("show table status like 'trends'"));
			$status["trends_count"] = $row["Rows"];
		}
		else
		{
			$row=DBfetch(DBselect("select count(itemid) as cnt from history"));
			$status["history_count"]  = $row["cnt"];
			$row=DBfetch(DBselect("select count(itemid) as cnt from history_log"));
			$status["history_count"] += $row["cnt"];
			$row=DBfetch(DBselect("select count(itemid) as cnt from history_str"));
			$status["history_count"] += $row["cnt"];
			$row=DBfetch(DBselect("select count(itemid) as cnt from history_uint"));
			$status["history_count"] += $row["cnt"];

			$result=DBselect("select count(itemid) as cnt from trends");
			$row=DBfetch($result);
			$status["trends_count"]=$row["cnt"];
		}*/
// alarms
//		$row=DBfetch(DBselect("select count(alarmid) as cnt from alarms"));
//		$status["alarms_count"]=$row["cnt"];
// alerts
//		$row=DBfetch(DBselect("select count(alertid) as cnt from alerts"));
//		$status["alerts_count"]=$row["cnt"];
// triggers
		$sql = "select count(t.triggerid) as cnt from triggers t, functions f, items i, hosts h".
			" where t.triggerid=f.triggerid and f.itemid=i.itemid and i.status=0 and i.hostid=h.hostid and h.status=".HOST_STATUS_MONITORED;
		$row=DBfetch(DBselect($sql));
		$status["triggers_count"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and t.status=0"));
		$status["triggers_count_enabled"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and t.status=1"));
		$status["triggers_count_disabled"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and t.status=0 and t.value=0"));
		$status["triggers_count_off"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and t.status=0 and t.value=1"));
		$status["triggers_count_on"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and t.status=0 and t.value=2"));
		$status["triggers_count_unknown"]=$row["cnt"];
// items 
		$sql = "select count(i.itemid) as cnt from items i, hosts h where i.hostid=h.hostid and h.status=".HOST_STATUS_MONITORED;
		$row=DBfetch(DBselect($sql));
		$status["items_count"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and i.status=0"));
		$status["items_count_monitored"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and i.status=1"));
		$status["items_count_disabled"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and i.status=3"));
		$status["items_count_not_supported"]=$row["cnt"];

		$row=DBfetch(DBselect($sql." and i.type=2"));
		$status["items_count_trapper"]=$row["cnt"];
// hosts
		$row=DBfetch(DBselect("select count(hostid) as cnt from hosts"));
		$status["hosts_count"]=$row["cnt"];

		$row=DBfetch(DBselect("select count(hostid) as cnt from hosts where status=".HOST_STATUS_MONITORED));
		$status["hosts_count_monitored"]=$row["cnt"];

		$row=DBfetch(DBselect("select count(hostid) as cnt from hosts where status=".HOST_STATUS_NOT_MONITORED));
		$status["hosts_count_not_monitored"]=$row["cnt"];

		$row=DBfetch(DBselect("select count(hostid) as cnt from hosts where status=".HOST_STATUS_TEMPLATE));
		$status["hosts_count_template"]=$row["cnt"];

		$row=DBfetch(DBselect("select count(hostid) as cnt from hosts where status=".HOST_STATUS_DELETED));
		$status["hosts_count_deleted"]=$row["cnt"];
// users
		$row=DBfetch(DBselect("select count(userid) as cnt from users"));
		$status["users_count"]=$row["cnt"];
		
		$status["users_online"]=0;
		$result=DBselect("select distinct s.userid from sessions s, users u where u.userid=s.userid and (s.lastaccess+u.autologout)>".time());
		while(DBfetch($result))		$status["users_online"]++;

		return $status;
	}

	// If $period_start=$period_end=0, then take maximum period
	function	calculate_availability($triggerid,$period_start,$period_end)
	{
		if(($period_start==0)&&($period_end==0))
		{
	        	$sql="select count(*) as cnt,min(clock) as minn,max(clock) as maxx from alarms where triggerid=$triggerid";
		}
		else
		{
	        	$sql="select count(*) as cnt,min(clock) as minn,max(clock) as maxx from alarms where triggerid=$triggerid and clock>=$period_start and clock<=$period_end";
		}
//		echo $sql,"<br>";

		
	        $result=DBselect($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			$min=$row["minn"];
			$max=$row["maxx"];
		}
		else
		{
			if(($period_start==0)&&($period_end==0))
			{
				$max=time();
				$min=$max-24*3600;
			}
			else
			{
				$ret["true_time"]=0;
				$ret["false_time"]=0;
				$ret["unknown_time"]=0;
				$ret["true"]=0;
				$ret["false"]=0;
				$ret["unknown"]=100;
				return $ret;
			}
		}

		$sql="select clock,value from alarms where triggerid=$triggerid and clock>=$min and clock<=$max";
//		echo " $sql<br>";
		$result=DBselect($sql);

//		echo $sql,"<br>";

// -1,0,1
		$state=-1;
		$true_time=0;
		$false_time=0;
		$unknown_time=0;
		$time=$min;
		if(($period_start==0)&&($period_end==0))
		{
			$max=time();
		}
		$rows=0;
		while($row=DBfetch($result))
		{
			$clock=$row["clock"];
			$value=$row["value"];

			$diff=$clock-$time;

			$time=$clock;

			if($state==-1)
			{
				$state=$value;
				if($state == 0)
				{
					$false_time+=$diff;
				}
				if($state == 1)
				{
					$true_time+=$diff;
				}
				if($state == 2)
				{
					$unknown_time+=$diff;
				}
			}
			else if($state==0)
			{
				$false_time+=$diff;
				$state=$value;
			}
			else if($state==1)
			{
				$true_time+=$diff;
				$state=$value;
			}
			else if($state==2)
			{
				$unknown_time+=$diff;
				$state=$value;
			}
			$rows++;
		}

		if($rows==0)
		{
			$false_time=$max-$min;
		}
		else
		{
			if($state==0)
			{
				$false_time=$false_time+$max-$time;
			}
			elseif($state==1)
			{
				$true_time=$true_time+$max-$time;
			}
			elseif($state==3)
			{
				$unknown_time=$unknown_time+$max-$time;
			}

		}
//		echo "$true_time $false_time $unknown_time";

		$total_time=$true_time+$false_time+$unknown_time;
		if($total_time==0)
		{
			$ret["true_time"]=0;
			$ret["false_time"]=0;
			$ret["unknown_time"]=0;
			$ret["true"]=0;
			$ret["false"]=0;
			$ret["unknown"]=100;
		}
		else
		{
			$ret["true_time"]=$true_time;
			$ret["false_time"]=$false_time;
			$ret["unknown_time"]=$unknown_time;
			$ret["true"]=(100*$true_time)/$total_time;
			$ret["false"]=(100*$false_time)/$total_time;
			$ret["unknown"]=(100*$unknown_time)/$total_time;
		}
		return $ret;
	}

	function	get_resource_name($permission,$id)
	{
		$res="-";
		if($permission=="Graph")
		{
			if(isset($id)&&($id!=0))
			{
				if($graph=get_graph_by_graphid($id))
					$res=$graph["name"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All graphs";
			}
		}
		else if($permission=="Host")
		{
			if(isset($id)&&($id!=0))
			{
				if($host=get_host_by_hostid($id))
					$res=$host["host"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All hosts";
			}
		}
		else if($permission=="Screen")
		{
			if(isset($id)&&($id!=0))
			{
				if($screen=get_screen_by_screenid($id))
					$res=$screen["name"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All screens";
			}
		}
		else if($permission=="Item")
		{
			if(isset($id)&&($id!=0))
			{
				if($item=get_item_by_itemid($id))
					if($host=get_host_by_hostid($item["hostid"]))
						$res=$host["host"].":".$item["description"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All items";
			}
		}
		else if($permission=="User")
		{
			if(isset($id)&&($id!=0))
			{
				if($user=get_user_by_userid($id))
					$res=$user["alias"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All users";
			}
		}
		else if($permission=="Network map")
		{
			if(isset($id)&&($id!=0))
			{
				if($user=get_sysmap_by_sysmapid($id))
					$res=$user["name"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All maps";
			}
		}
		else if($permission=="Application")
		{
			if(isset($id)&&($id > 0))
			{
				if($app = get_application_by_applicationid($id))
					$res = $app["name"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All applications";
			}
		}
		else if($permission=="Service")
		{
			if(isset($id)&&($id > 0))
			{
				if($service = get_service_by_serviceid($id))
					$res = $service["name"];
			}
			elseif(!isset($id) || $id == 0)
			{
				$res="All services";
			}
		}

		if($res == '-' && isset($id) && ($id > 0))
			$res = $id;

		return $res;
	}

	function	not_empty($var)
	{
		return ($var == "" ? 0 : 1);
	}

	function	get_profile($idx,$default_value,$type=PROFILE_TYPE_UNCNOWN)
	{
		global $USER_DETAILS;

		$result = $default_value;
		if($USER_DETAILS["alias"]!="guest")
		{
			$db_profiles = DBselect("select * from profiles where userid=".$USER_DETAILS["userid"]." and idx=".zbx_dbstr($idx));
			$profile=DBfetch($db_profiles);

			if($profile)
			{
				if($type==PROFILE_TYPE_UNCNOWN)
					$type = $profile["valuetype"];

				$result = $profile["value"];
			}
		}
		switch($type)
		{
			case PROFILE_TYPE_ARRAY:	$result = explode(";", $result); break;
			case PROFILE_TYPE_INT:		$result = intval($result); break;
			case PROFILE_TYPE_STR:		$result = strval($result); break;
		}

		if(is_array($result))
		{
			$result = array_filter($result, "not_empty");
		}
// SDI("Get profile:".$idx." = ".$result);
		return $result;
	}

	function	update_profile($idx,$value,$type=PROFILE_TYPE_UNCNOWN)
	{
// SDI("Save profile:".$idx." = ".$value);

		global $USER_DETAILS;

		if($USER_DETAILS["alias"]=="guest")
		{
			return;
		}

		if($type==PROFILE_TYPE_UNCNOWN && is_array($value))	$type = PROFILE_TYPE_ARRAY;
		if($type==PROFILE_TYPE_ARRAY && !is_array($value))	$value = array($value);

		switch($type)
		{
			case PROFILE_TYPE_ARRAY:	$value = implode(";", $value); break;
			default:			$value = strval($value);
		}


		$sql="select value from profiles where userid=".$USER_DETAILS["userid"]." and idx=".zbx_dbstr($idx);
//		echo $sql."<br>";
		$result=DBselect($sql);
		$row=DBfetch($result);

		if(!$row)
		{
			$sql="insert into profiles (userid,idx,value,valuetype)".
				" values (".$USER_DETAILS["userid"].",".zbx_dbstr($idx).",".zbx_dbstr($value).",".$type.")";
			DBexecute($sql);
		}
		else
		{
			$sql="update profiles set value=".zbx_dbstr($value).",valuetype=".$type.
				" where userid=".$USER_DETAILS["userid"]." and idx=".zbx_dbstr($idx);
			DBexecute($sql);
		}
	}

        function get_drawtype_description($drawtype)
        {
		if($drawtype==0)
			return "Line";
		if($drawtype==1)
			return "Filled region";
		if($drawtype==2)
			return "Bold line";
		if($drawtype==3)
			return "Dot";
		if($drawtype==4)
			return "Dashed line";
		return "Unknown";
        }

	function insert_confirm_javascript()
	{
		echo "
<script language=\"JavaScript\" type=\"text/javascript\">
<!--
	function Confirm(msg)
	{
		if(confirm(msg,'title'))
			return true;
		else
			return false;
	}
	function Redirect(url)
	{
		window.location = url;
		return false;
	}	
	function PopUp(url,form_name,param)
	{
		window.open(url,form_name,param);
		return false;
	}

	function CheckAll(form_name, chkMain)
	{
		var frmForm = document.forms[form_name];
		var value = frmForm.elements[chkMain].checked;
		for (var i=0; i < frmForm.length; i++)
		{
			if(frmForm.elements[i].type != 'checkbox') continue;
			if(frmForm.elements[i].name == chkMain) continue;
			if(frmForm.elements[i].disabled == true) continue;
			frmForm.elements[i].checked = value;
		}
	}
//-->
</script>
		";
	}
	function insert_javascript_clock($form, $field)
	{
		echo "
<script language=\"JavaScript\" type=\"text/javascript\">
<!--
	function show_clock()
	{
		var thetime=new Date();

		var nhours=thetime.getHours();
		var nmins=thetime.getMinutes();
		var nsecn=thetime.getSeconds();
		var AorP=\" \";

		var year = thetime.getFullYear();
		var nmonth = thetime.getMonth()+1;
		var ndate = thetime.getDate();
		
		if (nhours>=12)		AorP=\"PM\";
		else			AorP=\"AM\";

		if (nhours>=13)		nhours-=12;
		if (nhours==0)		nhours=12;

		if (nsecn<10)		nsecn=\"0\"+nsecn;
		if (nmins<10)		nmins=\"0\"+nmins;
		if (nmonth<10)		nmonth=\"0\"+nmonth;
		if (ndate<10)		ndate=\"0\"+ndate;

		document.forms['$form'].elements['$field'].value=ndate+\"-\"+nmonth+\"-\"+year+\" \"+nhours+\":\"+nmins+\":\"+nsecn+\" \"+AorP;

		setTimeout('show_clock()',1000);
	} 
//-->
</script>
";
	}

	function	start_javascript_clock()
	{
		echo "
<script language=\"JavaScript\" type=\"text/javascript\">
<!--
	show_clock();
//-->
</script>
";
	}

	function	SetFocus($frm_name, $fld_name)
	{
		echo "
<script language=\"JavaScript\" type=\"text/javascript\">
<!--
	document.forms['$frm_name'].elements['$fld_name'].focus();
//-->
</script>
";
	}

/* Use ImageSetStyle+ImageLIne instead of bugged ImageDashedLine */
	if(function_exists("imagesetstyle"))
	{
		function DashedLine($image,$x1,$y1,$x2,$y2,$color)
		{
// Style for dashed lines
//			$style = array($color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			$style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			ImageSetStyle($image, $style);
			ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
		}

	}
	else
	{
		function DashedLine($image,$x1,$y1,$x2,$y2,$color)
		{
			ImageDashedLine($image,$x1,$y1,$x2,$y2,$color);
		}
	}

	function DashedRectangle($image,$x1,$y1,$x2,$y2,$color)
	{
		DashedLine($image, $x1,$y1,$x1,$y2,$color);
		DashedLine($image, $x1,$y2,$x2,$y2,$color);
		DashedLine($image, $x2,$y2,$x2,$y1,$color);
		DashedLine($image, $x2,$y1,$x1,$y1,$color);
	}


	function time_navigator($resource="graphid",$id)
	{
	echo "<TABLE BORDER=0 align=center COLS=2 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=1>";
	echo "<TR BGCOLOR=#FFFFFF>";
	echo "<TD ALIGN=LEFT>";

	echo "<div align=left>";
	echo "<b>".S_PERIOD.":</b>".SPACE;

	$hour=3600;
		
		$a=array(S_1H=>3600,S_2H=>2*3600,S_4H=>4*3600,S_8H=>8*3600,S_12H=>12*3600,
			S_24H=>24*3600,S_WEEK_SMALL=>7*24*3600,S_MONTH_SMALL=>31*24*3600,S_YEAR_SMALL=>365*24*3600);
		foreach($a as $label=>$sec)
		{
			echo "[";
			if($_REQUEST["period"]>$sec)
			{
				$tmp=$_REQUEST["period"]-$sec;
				echo("<A HREF=\"charts.php?period=$tmp".url_param($resource).url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">-</A>");
			}
			else
			{
				echo "-";
			}

			echo("<A HREF=\"charts.php?period=$sec".url_param($resource).url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">");
			echo($label."</A>");

			$tmp=$_REQUEST["period"]+$sec;
			echo("<A HREF=\"charts.php?period=$tmp".url_param($resource).url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">+</A>");

			echo "]".SPACE;
		}

		echo("</div>");

	echo "</TD>";
	echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
	echo "<b>".nbsp(S_KEEP_PERIOD).":</b>".SPACE;
		if($_REQUEST["keep"] == 1)
		{
			echo("[<A HREF=\"charts.php?keep=0".url_param($resource).url_param("from").url_param("period").url_param("fullscreen")."\">".S_ON_C."</a>]");
		}
		else
		{
			echo("[<A HREF=\"charts.php?keep=1".url_param($resource).url_param("from").url_param("period").url_param("fullscreen")."\">".S_OFF_C."</a>]");
		}
	echo "</TD>";
	echo "</TR>";
	echo "<TR BGCOLOR=#FFFFFF>";
	echo "<TD>";
	if(isset($_REQUEST["stime"]))
	{
		echo "<div align=left>" ;
		echo "<b>".S_MOVE.":</b>".SPACE;

		$day=24;
// $a already defined
//		$a=array("1h"=>1,"2h"=>2,"4h"=>4,"8h"=>8,"12h"=>12,
//			"24h"=>24,"week"=>7*24,"month"=>31*24,"year"=>365*24);
		foreach($a as $label=>$hours)
		{
			echo "[";

			$stime=$_REQUEST["stime"];
			$tmp=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
			$tmp=$tmp-3600*$hours;
			$tmp=date("YmdHi",$tmp);
			echo("<A HREF=\"charts.php?stime=$tmp".url_param($resource).url_param("period").url_param("keep").url_param("fullscreen")."\">-</A>");

			echo($label);

			$stime=$_REQUEST["stime"];
			$tmp=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
			$tmp=$tmp+3600*$hours;
			$tmp=date("YmdHi",$tmp);
			echo("<A HREF=\"charts.php?stime=$tmp".url_param($resource).url_param("period").url_param("keep").url_param("fullscreen")."\">+</A>");

			echo "]".SPACE;
		}
		echo("</div>");
	}
	else
	{
		echo "<div align=left>";
		echo "<b>".S_MOVE.":</b>".SPACE;

		$day=24;
// $a already defined
//		$a=array("1h"=>1,"2h"=>2,"4h"=>4,"8h"=>8,"12h"=>12,
//			"24h"=>24,"week"=>7*24,"month"=>31*24,"year"=>365*24);
		foreach($a as $label=>$hours)
		{
			echo "[";
			$tmp=$_REQUEST["from"]+$hours;
			echo("<A HREF=\"charts.php?from=$tmp".url_param($resource).url_param("period").url_param("keep").url_param("fullscreen")."\">-</A>");

			echo($label);

			if($_REQUEST["from"]>=$hours)
			{
				$tmp=$_REQUEST["from"]-$hours;
				echo("<A HREF=\"charts.php?from=$tmp".url_param($resource).url_param("period").url_param("keep").url_param("fullscreen")."\">+</A>");
			}
			else
			{
				echo "+";
			}

			echo "]".SPACE;
		}
		echo("</div>");
	}
	echo "</TD>";
	echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
//		echo("<div align=left>");
		echo "<form method=\"put\" action=\"charts.php\">";
		echo "<input name=\"graphid\" type=\"hidden\" value=\"".$_REQUEST[$resource]."\" size=12>";
		echo "<input name=\"period\" type=\"hidden\" value=\"".(9*3600)."\" size=12>";
		if(isset($_REQUEST["stime"]))
		{
			echo "<input name=\"stime\" class=\"biginput\" value=\"".$_REQUEST["stime"]."\" size=12>";
		}
		else
		{
			echo "<input name=\"stime\" class=\"biginput\" value=\"yyyymmddhhmm\" size=12>";
		}
		echo SPACE;
		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"go\">";
		echo "</form>";
//		echo("</div>");
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";
	}

	function ImageOut($image)
	{
//		ImageJPEG($image);
		ImagePNG($image);
	}

	function	add_mapping_to_valuemap($valuemapid, $mappings)
	{
		DBexecute("delete from mappings where valuemapid=$valuemapid");

		foreach($mappings as $map)
		{
			$result = DBexecute("insert into mappings (valuemapid, value, newvalue)".
				" values (".$valuemapid.",".zbx_dbstr($map["value"]).",".
				zbx_dbstr($map["newvalue"]).")");

			if(!$result)
				return $result;
		}
		return TRUE;
	}

	function	add_valuemap($name, $mappings)
	{
		if(!is_array($mappings))	return FALSE;
		
		$result = DBexecute("insert into valuemaps (name) values (".zbx_dbstr($name).")");
		if(!$result)
			return $result;

		$valuemapid =  DBinsert_id($result,"valuemaps","valuemapid");
		
		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		return $result;
	}

	function	update_valuemap($valuemapid, $name, $mappings)
	{
		if(!is_array($mappings))	return FALSE;

		$result = DBexecute("update valuemaps set name=".zbx_dbstr($name).
			" where valuemapid=$valuemapid");

		if(!$result)
			return $result;

		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		return $result;
	}

	function	delete_valuemap($valuemapid)
	{
		DBexecute("delete from mappings where valuemapid=$valuemapid");
		DBexecute("delete from valuemaps where valuemapid=$valuemapid");
		return TRUE;
	}

	function	replace_value_by_map($value, $valuemapid)
	{
		if($valuemapid < 1) return $value;

		$result = DBselect("select newvalue from mappings".
			" where valuemapid=".zbx_dbstr($valuemapid)." and value=".zbx_dbstr($value));
		$row = DBfetch($result);
		if($row)
		{
			return $row["newvalue"]." "."($value)";
		}
		return $value;
	}

	function	Alert($msg)
	{
		echo "
<script language=\"JavaScript\" type=\"text/javascript\">
<!--
	alert('$msg');
//-->
</script>
";
	}

	function natksort(&$array) {
		$keys = array_keys($array);
		natcasesort($keys);

		$new_array = array();

		foreach ($keys as $k) {
			$new_array[$k] = $array[$k];
		}

		$array = $new_array;
		return true;
	}

	function	set_image_header()
	{
		//Header( "Content-type:  text/html"); 

		if(MAP_OUTPUT_FORMAT == "JPG")	Header( "Content-type:  image/jpeg"); 
		else				Header( "Content-type:  image/png"); 
		Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 
	}
?>
