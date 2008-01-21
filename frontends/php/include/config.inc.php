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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

function SDI($msg="SDI") { echo "DEBUG INFO: "; var_dump($msg); echo SBR; } // DEBUG INFO!!!
function VDP($var, $msg=null) { echo "DEBUG DUMP: "; if(isset($msg)) echo '"'.$msg.'"'.SPACE; var_dump($var); echo SBR; } // DEBUG INFO!!!
function TODO($msg) { echo "TODO: ".$msg.SBR; }  // DEBUG INFO!!!

?>
<?php
	require_once 	"include/defines.inc.php";
	require_once 	"include/html.inc.php";
	require_once	"include/copt.lib.php";

// GLOBALS
	global $USER_DETAILS, $USER_RIGHTS;

	$USER_DETAILS	= array();
	$USER_RIGHTS	= array();
// END OF GLOBALS

// Include Classes
	require_once("include/classes/ctag.inc.php");
	require_once("include/classes/cvar.inc.php");
	require_once("include/classes/cspan.inc.php");
	require_once("include/classes/cimg.inc.php");
	require_once("include/classes/ccolor.inc.php");
	require_once("include/classes/clink.inc.php");
	require_once("include/classes/chelp.inc.php");
	require_once("include/classes/cbutton.inc.php");
	require_once("include/classes/clist.inc.php");
	require_once("include/classes/ccombobox.inc.php");
	require_once("include/classes/ctable.inc.php");
	require_once("include/classes/ctableinfo.inc.php");
	require_once("include/classes/ctextarea.inc.php");
	require_once("include/classes/ctextbox.inc.php");
	require_once("include/classes/cform.inc.php");
	require_once("include/classes/cfile.inc.php");
	require_once("include/classes/ccheckbox.inc.php");
	require_once("include/classes/cform.inc.php");
	require_once("include/classes/cformtable.inc.php");
	require_once("include/classes/cmap.inc.php");
	require_once("include/classes/cflash.inc.php");
	require_once("include/classes/ciframe.inc.php");
	require_once("include/classes/cpumenu.inc.php");
	require_once("include/classes/graph.inc.php");
	require_once('include/classes/ctree.inc.php');
	require_once('include/classes/cscript.inc.php');

// Include Tactical Overview modules

	require_once 	"include/locales.inc.php";

	include_once("include/classes/chostsinfo.mod.php");
	include_once("include/classes/ctriggerinfo.mod.php");
	include_once("include/classes/cserverinfo.mod.php");
	include_once("include/classes/cflashclock.mod.php");

	require_once 	"include/db.inc.php";
	require_once 	"include/perm.inc.php";
	require_once 	"include/audit.inc.php";
	require_once 	"include/js.inc.php";

// Include Validation

	require_once 	"include/validate.inc.php";

	function zbx_err_handler($errno, $errstr, $errfile, $errline)
	{
		error($errstr.'['.$errfile.':'.$errline.']');
	}
	
	/********** START INITIALIZATION *********/

	set_error_handler('zbx_err_handler');

	global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID, $ZBX_CONFIGURATION_FILE, $DB_TYPE, $DB_SERVER, $DB_DATABASE, $DB_USER, $DB_PASSWORD;
	global $ZBX_SERVER, $ZBX_SERVER_PORT;
	global $ZBX_LOCALES;

	$ZBX_LOCALNODEID = 0;
	$ZBX_LOCMASTERID = 0;

	$ZBX_CONFIGURATION_FILE = './conf/zabbix.conf.php';

	$ZBX_CONFIGURATION_FILE = realpath(dirname($ZBX_CONFIGURATION_FILE)).'/'.basename($ZBX_CONFIGURATION_FILE);

	unset($show_setup);

	if(file_exists($ZBX_CONFIGURATION_FILE) && !isset($_COOKIE['ZBX_CONFIG'])){
		include $ZBX_CONFIGURATION_FILE;

		$error = '';
		if(!DBconnect($error)){
			$_REQUEST['message'] = $error;

			define('ZBX_DISTRIBUTED', false);
			define('ZBX_PAGE_NO_AUTHERIZATION', true);
			
			$show_warning = true;
		}
		else{
			global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID;

			/* Init LOCAL NODE ID */
			if($local_node_data = DBfetch(DBselect('select * from nodes where nodetype=1 order by nodeid'))){
				$ZBX_LOCALNODEID = $local_node_data['nodeid'];
				$ZBX_LOCMASTERID = $local_node_data['masterid'];

				define('ZBX_DISTRIBUTED', true);
			}
			else{
				define('ZBX_DISTRIBUTED', false);
			}
			unset($local_node_data);
		}
		unset($error);
	}
	else{
		if(file_exists($ZBX_CONFIGURATION_FILE))
			include $ZBX_CONFIGURATION_FILE;

		define('ZBX_PAGE_NO_AUTHERIZATION', true);
		define('ZBX_DISTRIBUTED', false);
		$show_setup = true;
	}

	if(!defined('ZBX_PAGE_NO_AUTHERIZATION')){
		check_authorisation();

		include_once "include/locales/".$USER_DETAILS["lang"].".inc.php";
		process_locales();
	}
	else{
		$USER_DETAILS = array(
			"alias" =>ZBX_GUEST_USER,
			"userid"=>0,
			"lang"  =>"en_gb",
			"type"  =>"0",
			"node"  =>array(
				"name"  =>'- unknown -',
				"nodeid"=>0));
	}

	if(isset($show_setup)){
		unset($show_setup);
		include_once "setup.php";
	}
	else if(isset($show_warning)){
		unset($show_warning);
		include_once "warning.php";
	}

	/********** END INITIALIZATION ************/

	function	init_nodes(){
		/* Init CURRENT NODE ID */
		global	$USER_DETAILS,
			$ZBX_LOCALNODEID, $ZBX_LOCMASTERID,
			$ZBX_CURRENT_NODEID, $ZBX_CURRENT_SUBNODES, $ZBX_CURMASTERID,
			$ZBX_NODES,
			$ZBX_WITH_SUBNODES;

		$ZBX_CURRENT_SUBNODES = array();
		$ZBX_NODES = array();

		if(!defined('ZBX_PAGE_NO_AUTHERIZATION') && ZBX_DISTRIBUTED)
		{
			$ZBX_CURRENT_NODEID = get_cookie('zbx_current_nodeid', $ZBX_LOCALNODEID); // Selected node
			$ZBX_WITH_SUBNODES = get_cookie('zbx_with_subnodes', false); // Show elements from subnodes

			if(isset($_REQUEST['switch_node']))
			{
				if($node_data = DBfetch(DBselect("select * from nodes where nodeid=".$_REQUEST['switch_node'])))
				{
					$ZBX_CURRENT_NODEID = $_REQUEST['switch_node'];
				}
				unset($node_data);
			}

			if(isset($_REQUEST['show_subnodes']))
			{
				$ZBX_WITH_SUBNODES = !empty($_REQUEST['show_subnodes']);
			}

			if($node_data = DBfetch(DBselect("select * from nodes where nodeid=".$ZBX_CURRENT_NODEID)))
			{
				$ZBX_CURMASTERID = $node_data['masterid'];
			}
			
			$ZBX_NODES = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_LIST, null, PERM_RES_DATA_ARRAY);

			if ( !isset($ZBX_NODES[$ZBX_CURRENT_NODEID]) )
			{
				$denyed_page_requested = true;
				$ZBX_CURRENT_NODEID = $ZBX_LOCALNODEID;
				$ZBX_CURMASTERID = $ZBX_LOCMASTERID;
			}

			foreach ( $ZBX_NODES as $nodeid => $node_data )
			{
				for ( 	$curr_node = &$node_data;
					$curr_node['masterid'] != 0 &&
					(bccomp($curr_node['masterid'] , $ZBX_CURRENT_NODEID) != 0);
					$curr_node = &$ZBX_NODES[$curr_node['masterid']]
				);

				if (bccomp($curr_node['masterid'],$ZBX_CURRENT_NODEID) == 0 )
				{
					$ZBX_CURRENT_SUBNODES[$nodeid] = $nodeid;
				}
			}
			
			zbx_set_post_cookie('zbx_current_nodeid',$ZBX_CURRENT_NODEID);
			zbx_set_post_cookie('zbx_with_subnodes',$ZBX_WITH_SUBNODES);
		}
		else
		{
			$ZBX_CURRENT_NODEID = $ZBX_LOCALNODEID;
			$ZBX_CURMASTERID = $ZBX_LOCMASTERID;
			$ZBX_WITH_SUBNODES = false;
		}

		$ZBX_CURRENT_SUBNODES[$ZBX_CURRENT_NODEID] = $ZBX_CURRENT_NODEID;

		if ( count($ZBX_CURRENT_SUBNODES) < 2 && !defined('ZBX_DISABLE_SUBNODES') )
			define('ZBX_DISABLE_SUBNODES', 1);
	}

	function	get_current_nodeid( $forse_with_subnodes = null, $perm = null ){
		global	$ZBX_CURRENT_NODEID, $ZBX_CURRENT_SUBNODES, $ZBX_WITH_SUBNODES;

		if(!isset($ZBX_CURRENT_NODEID))
			init_nodes();

		$result = ( is_show_subnodes($forse_with_subnodes) ? $ZBX_CURRENT_SUBNODES : $ZBX_CURRENT_NODEID );
		
		if(!is_null($perm)){
			global $USER_DETAILS;
			$result = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_ONLY, null, null, $result);
		}

	return $result;
	}

	function	get_node_name_by_elid($id_val, $forse_with_subnodes = null)
	{
		global $ZBX_NODES;

		if ( ! is_show_subnodes($forse_with_subnodes) )
			return null;

		$nodeid = id2nodeid($id_val);

		if ( !isset($ZBX_NODES[$nodeid]) )
			return null;

		return '['.$ZBX_NODES[$nodeid]['name'].'] ';
	}

	function	is_show_subnodes($forse_with_subnodes = null)
	{
		global	$ZBX_WITH_SUBNODES;

		if ( is_null($forse_with_subnodes) )
		{
			if ( defined('ZBX_DISABLE_SUBNODES') )
				$forse_with_subnodes = false;
			else
				$forse_with_subnodes = $ZBX_WITH_SUBNODES;
		}
		return $forse_with_subnodes;
	}

	function	access_deny()
	{
		include_once "include/page_header.php";

		show_error_message(S_NO_PERMISSIONS);

		include_once "include/page_footer.php";
	}
	
	function uint_in_array($needle,$haystack){
		foreach($haystack as $id => $value)
			if(bccomp($needle,$value) == 0) return true;
	return false;
	}

	function str_in_array($needle,$haystack,$strict=false){
		if(is_array($needle)){
			return in_array($needle,$haystack,$strict);
		}
		else if($strict){
			foreach($haystack as $id => $value) 
				if($needle === $value) return true;
		}
		else{
			foreach($haystack as $id => $value)
				if(strcmp($needle,$value) == 0) return true;
		}
	return false;
	}

	function zbx_stripslashes($value){
		if(is_array($value)){
			foreach($value as $id => $data)
				$value[$id] = zbx_stripslashes($data); 
				// $value = array_map('zbx_stripslashes',$value); /* don't use 'array_map' it buggy with indexes */
		} elseif (is_string($value)){
			$value = stripslashes($value);
		}
		return $value;
	}

	function get_request($name, $def=NULL){
		if(isset($_REQUEST[$name]))
			return $_REQUEST[$name];
		else
			return $def;
	}

	function info($msg){
		global $ZBX_MESSAGES;

		if(is_null($ZBX_MESSAGES))
			$ZBX_MESSAGES = array();

		array_push($ZBX_MESSAGES, array('type' => 'info', 'message' => $msg));
	}

	function error($msg){
		global $ZBX_MESSAGES;

		if(is_null($ZBX_MESSAGES))
			$ZBX_MESSAGES = array();

		array_push($ZBX_MESSAGES, array('type' => 'error', 'message' => $msg));
	}

	function clear_messages(){
		global $ZBX_MESSAGES;

		$ZBX_MESSAGES = null;
	}

	function asort_by_key(&$array, $key){
		if(!is_array($array)) {
			error('Incorrect type of asort_by_key');
			return array();
		}
		
		$key = htmlspecialchars($key);
		uasort($array, create_function('$a,$b', 'return $a[\''.$key.'\'] - $b[\''.$key.'\'];'));
	return $array;
	}

	function fatal_error($msg){
		include_once "include/page_header.php";
		show_error_message($msg);
		include_once "include/page_footer.php";
	}
	
	function str2mem($val){
		$val = trim($val);
		$last = strtolower($val{strlen($val)-1});
		switch($last){
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return $val;
	}
	
	function mem2str($size)
	{
		$prefix = 'B';
		if($size > 1048576) {	$size = $size/1048576;	$prefix = 'M'; }
		elseif($size > 1024) {	$size = $size/1024;	$prefix = 'K'; }
		return round($size, 6).$prefix;
	}

	function getmicrotime()
	{
		list($usec, $sec) = explode(" ",microtime()); 
		return ((float)$usec + (float)$sec); 
	}

	/* Do not forget to sync it with add_value_suffix in evalfunc.c! */ 
	function	convert_units($value,$units)
	{
// Special processing for unix timestamps
		if($units=="unixtime")
		{
			$ret=date("Y.m.d H:i:s",$value);
			return $ret;
		}
//Special processing of uptime
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
			$ret=$ret.round($value, 2)."s";
		
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


//	The hash has form <md5sum of triggerid>,<sum of priorities>
	function	calc_trigger_hash()
	{

		$priority = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0, 5=>0);
		$triggerids="";

	       	$result=DBselect('select t.triggerid,t.priority from triggers t,hosts h,items i,functions f'.
			'  where t.value=1 and f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and i.status=0');

		while($row=DBfetch($result))
		{
			$ack = get_last_event_by_triggerid($row["triggerid"]);
			if($ack["acknowledged"] == 1) continue;

			$triggerids="$triggerids,".$row["triggerid"];
			$priority[$row["priority"]]++;
		}

		$md5sum=md5($triggerids);

		$priorities=0;
		for($i=0;$i<=5;$i++)	$priorities += pow(100,$i)*$priority[$i];

		return	"$priorities,$md5sum";
	}

	function	select_config()
	{
		$row=DBfetch(DBselect("select * from config where ".DBin_node("configid", get_current_nodeid(false))));
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

	function	show_messages($bool=TRUE,$okmsg=NULL,$errmsg=NULL)
	{
		global	$page, $ZBX_MESSAGES;

		if (! defined('PAGE_HEADER_LOADED')) return;

		if (!isset($page["type"])) $page["type"] = PAGE_TYPE_HTML;

		$message = array();
		$width = 0;
		$height= 0;

		if(!$bool && !is_null($errmsg))		$msg="ERROR: ".$errmsg;
		else if($bool && !is_null($okmsg))	$msg=$okmsg;

		if(isset($msg))
		{
			switch($page["type"])
			{
				case PAGE_TYPE_IMAGE:
					array_push($message, array(
						'text'	=> $msg,
						'color'	=> (!$bool) ? array('R'=>255,'G'=>0,'B'=>0) : array('R'=>34,'G'=>51,'B'=>68),
						'font'	=> 2));
					$width = max($width, ImageFontWidth(2) * strlen($msg) + 1);
					$height += imagefontheight(2) + 1;
					break;			
				case PAGE_TYPE_XML:
					echo htmlspecialchars($msg)."\n";
					break;			
				case PAGE_TYPE_HTML:
				default:
					$p = new CTag('p','yes');
					$p->AddOption('align','center');
					$p->AddOption('style','color: '.((!$bool)?'#AA0000;':'#223344;'));
					$p->AddItem(bold('['.$msg.']'));
					$p->Show();
					break;
			}
		}


		if(isset($ZBX_MESSAGES))
		{
			if($page["type"] == PAGE_TYPE_IMAGE)
			{
				$msg_font = 2;
				foreach($ZBX_MESSAGES as $msg)
				{
					if($msg['type'] == 'error')
					{
						array_push($message, array(
							'text'	=> $msg['message'],
							'color'	=> array('R'=>255,'G'=>55,'B'=>55),
							'font'	=> $msg_font));
					}
					else
					{
						array_push($message, array(
							'text'	=> $msg['message'],
							'color'	=> array('R'=>155,'G'=>155,'B'=>55),
							'font'	=> $msg_font));
					}
					$width = max($width, ImageFontWidth($msg_font) * strlen($msg['message']) + 1);
					$height += imagefontheight($msg_font) + 1;
				}
			}
			elseif($page["type"] == PAGE_TYPE_XML)
			{
				foreach($ZBX_MESSAGES as $msg)
				{
					echo '['.$msg['type'].'] '.$msg['message']."\n";
				}
			}
			else
			{
				$lst_error = new CList(null,'messages');
				foreach($ZBX_MESSAGES as $msg)
					$lst_error->AddItem($msg['message'], $msg['type']);
//message scroll if needed
				$msg_show = 6;
				$msg_font_size = 8;
				$msg_count = count($ZBX_MESSAGES);
				
				if($msg_count > $msg_show) $msg_count = $msg_show;
					
				$msg_count = ($msg_count * $msg_font_size *4) + 2;
				$lst_error->AddOption('style','font-size: '.$msg_font_size.'pt; height: '.$msg_count.'px;');
//---
				$lst_error->Show();
				
				unset($lst_error);
			}
			$ZBX_MESSAGES = null;
		}

		if($page["type"] == PAGE_TYPE_IMAGE && count($message) > 0)
		{
			$width += 2;
			$height += 2;
			$canvas = imagecreate($width, $height);
			ImageFilledRectangle($canvas,0,0,$width,$height, ImageColorAllocate($canvas, 255, 255, 255));

			foreach($message as $id => $msg)
			{
				$message[$id]['y'] = 1 + (isset($previd) ? $message[$previd]['y'] + $message[$previd]['h'] : 0 );
				$message[$id]['h'] = imagefontheight($msg['font']);
				
				ImageString(
					$canvas,
					$msg['font'],
					1,
					$message[$id]['y'],
					$msg['text'],
					ImageColorAllocate($canvas, $msg['color']['R'], $msg['color']['G'], $msg['color']['B'])
					);
				
				$previd = $id;
			}
			ImageOut($canvas);
			ImageDestroy($canvas);
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
		$str = trim($str,';');
		$out = "";
		$periods = split(';',$str);
		foreach($periods as $preiod)
		{
			// arr[idx]   1       2         3             4            5            6
			if(!ereg('^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$', $preiod, $arr))
				return false;

			if($arr[1] > $arr[2]) // check week day
				return false;
			if($arr[3] > 23 || $arr[3] < 0 || $arr[5] > 24 || $arr[5] < 0) // check hour
				return false;
			if($arr[4] > 59 || $arr[4] < 0 || $arr[6] > 59 || $arr[6] < 0) // check min
				return false;
			if(($arr[5]*100 + $arr[6]) > 2400) // check max time 24:00
				return false;
			if(($arr[3] * 100 + $arr[4]) >= ($arr[5] * 100 + $arr[6])) // check time period
				return false;

			$out .= sprintf("%d-%d,%02d:%02d-%02d:%02d",$arr[1],$arr[2],$arr[3],$arr[4],$arr[5],$arr[6]).';';
		}
		$str = $out;
//parse_period($str);
		return true;
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

	# Add event

	function	get_event_by_eventid($eventid)
	{
		$db_events = DBselect("select * from events where eventid=$eventid");
		return DBfetch($db_events);
	}


	/******************************************************************************
	 *                                                                            *
	 * Purpose: Reset nextcheck for related items                                 *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
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

	# Update configuration

	function	update_config($event_history,$alert_history,$refresh_unsupported,$work_period,$alert_usrgrpid,$event_ack_enable,$event_expire,$event_show_max)
	{
		$update = array();

		if(!is_null($event_history))
		{
			$update[] = 'event_history='.$event_history;
		}
		if(!is_null($alert_history))
		{
			$update[] = 'alert_history='.$alert_history;
		}
		if(!is_null($refresh_unsupported))
		{
			$update[] = 'refresh_unsupported='.$refresh_unsupported;
		}
		if(!is_null($work_period))
		{
			if( !validate_period($work_period) )
			{
				error(S_ICORRECT_WORK_PERIOD);
				return NULL;
			}
			$update[] = 'work_period='.zbx_dbstr($work_period);
		}
		if(!is_null($alert_usrgrpid))
		{
			if($alert_usrgrpid != 0 && !DBfetch(DBselect('select usrgrpid from usrgrp where usrgrpid='.$alert_usrgrpid)))
			{
				error(S_INCORRECT_GROUP);;
				return NULL;
			}
			$update[] = 'alert_usrgrpid='.$alert_usrgrpid;
		}
		if(!is_null($event_ack_enable))
		{
			$update[] = 'event_ack_enable='.$event_ack_enable;
		}
		if(!is_null($event_expire))
		{
			$update[] = 'event_expire='.$event_expire;
		}
		if(!is_null($event_show_max))
		{
			$update[] = 'event_show_max='.$event_show_max;
		}
		if(count($update) == 0)
		{
			error(S_NOTHING_TO_DO);
			return NULL;
		}

		return	DBexecute('update config set '.implode(',',$update).
			' where '.DBin_node('configid', get_current_nodeid(false)));
	}
	
/* Function: 
 *	hide_form_items()
 *
 * Desc:
 *	Searches items/objects for Form tags like "<input"/Form classes like CForm, and makes it empty
 * 
 * Author: 
 *	Aly
 */
	function hide_form_items(&$obj){
		if(is_array($obj)){
			foreach($obj as $id => $item){
				hide_form_items($obj[$id]);			// Attention recursion;
			}
		}
		else if(is_object($obj)){
			if(str_in_array(strtolower(get_class($obj)),array('cform','ccheckbox','cselect','cbutton','cbuttonqmessage','cbuttondelete','cbuttoncancel'))){
				$obj=SPACE;
			}
			if(isset($obj->items) && !empty($obj->items)){
				foreach($obj->items as $id => $item){
					hide_form_items($obj->items[$id]); 		// Recursion
				}
			}
		}
		else{
			foreach(array('<form','<input','<select') as $item){
				if(strpos($obj,$item) !== FALSE) $obj = SPACE;
			}
		}
	}

	function get_table_header($col1, $col2=SPACE){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
		//if empty header than do not show it
			if(($col1 == SPACE) && ($col2 == SPACE)) return new CScript('');
		}
		
		$table = new CTable(NULL,"header");
		$table->SetCellSpacing(0);
		$table->SetCellPadding(1);
		$table->AddRow(array(new CCol($col1,"header_l"), new CCol($col2,"header_r")));
	return $table;
	}

	function	show_table_header($col1, $col2=SPACE)
	{
		$table = get_table_header($col1, $col2);
		$table->Show();
	}

	# Show History Graph

	function	show_history($itemid,$from,$stime,$period){
		$till=date(S_DATE_FORMAT_YMDHMS,time(NULL)-$from*3600);   
		show_table_header(S_TILL.SPACE.$till.' ('.($period/3600).' HOURs)');

		$td = new CCol(get_js_sizeable_graph('graph','chart.php?itemid='.$itemid.
				url_param($from,false,'from').
				url_param($stime,false,'stime').
				url_param($period,false,'period')));
		$td->AddOption('align','center');
		
		$tr = new CRow($td);
		$tr->AddOption('bgcolor','#dddddd');
		
		$table = new CTable();
		$table->AddOption('width','100%');
		$table->AddOption('bgcolor','#cccccc');
		$table->AddOption('cellspacing','1');
		$table->AddOption('cellpadding','3');
		
		$table->AddRow($tr);
		
		$table->Show();
	}


	function	get_status(){
		global $DB_TYPE;
		$status = array();
// server
		if( (exec('ps -ef|grep zabbix_server|grep -v grep|wc -l')>0) || (exec('ps -ax|grep zabbix_server|grep -v grep|wc -l')>0) ){
			$status["zabbix_server"] = S_YES;
		}
		else{
			$status["zabbix_server"] = S_NO;
		}

// history & trends
/*		if ($DB_TYPE == "MYSQL")
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
// events
		$row=DBfetch(DBselect("select count(eventid) as cnt from events"));
		$status["events_count"]=$row["cnt"];
// alerts
		$row=DBfetch(DBselect("select count(alertid) as cnt from alerts"));
		$status["alerts_count"]=$row["cnt"];
// triggers
//		$sql = 'SELECT COUNT(DISTINCT t.triggerid) as cnt '.
		$sql = 'SELECT COUNT(t.triggerid) as cnt '.
				' FROM triggers t, functions f, items i, hosts h'.
				' WHERE t.triggerid=f.triggerid '.
					' AND f.itemid=i.itemid '.
					' AND i.status=0 '.
					' AND i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED;
					
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
		$sql = 'SELECT COUNT(i.itemid) as cnt '.
				' FROM items i, hosts h '.
				' WHERE i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED;
					
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
		$result=DBselect("select distinct s.userid from sessions s, users u where u.userid=s.userid and u.autologout>0 and (s.lastaccess+u.autologout)>".time());
		while(DBfetch($result))		$status["users_online"]++;
		$result=DBselect("select distinct s.userid from sessions s, users u where u.userid=s.userid and u.autologout=0");
		while(DBfetch($result))		$status["users_online"]++;

		return $status;
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

	function	empty2null($var)
	{
		return ($var == "") ? null : $var;
	}


/********** USER PROFILE ***********/

//---------- GET USER VALUE -------------
	function	get_profile($idx,$default_value=null,$type=PROFILE_TYPE_UNKNOWN){
		global $USER_DETAILS;

		$result = $default_value;
		if($USER_DETAILS["alias"]!=ZBX_GUEST_USER)
		{
			$db_profiles = DBselect("select * from profiles where userid=".$USER_DETAILS["userid"]." and idx=".zbx_dbstr($idx));
			$profile=DBfetch($db_profiles);

			if($profile)
			{
				if($type==PROFILE_TYPE_UNKNOWN)
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
		return $result;
	}

//----------- ADD/EDIT USERPROFILE -------------
	function	update_profile($idx,$value,$type=PROFILE_TYPE_UNKNOWN)
	{

		global $USER_DETAILS;

		if($USER_DETAILS["alias"]==ZBX_GUEST_USER)
		{
			return;
		}

		if($type==PROFILE_TYPE_UNKNOWN && is_array($value))	$type = PROFILE_TYPE_ARRAY;
		if($type==PROFILE_TYPE_ARRAY && !is_array($value))	$value = array($value);

		switch($type)
		{
			case PROFILE_TYPE_ARRAY:	$value = implode(";", $value); break;
			default:			$value = strval($value);
		}

		$row = DBfetch(DBselect("select value from profiles where userid=".$USER_DETAILS["userid"]." and idx=".zbx_dbstr($idx)));

		if(!$row)
		{
			$profileid = get_dbid('profiles', 'profileid');
			$sql="insert into profiles (profileid,userid,idx,value,valuetype)".
				" values (".$profileid.",".$USER_DETAILS["userid"].",".zbx_dbstr($idx).",".zbx_dbstr($value).",".$type.")";
			DBexecute($sql);
		}
		else
		{
			$sql="update profiles set value=".zbx_dbstr($value).",valuetype=".$type.
				" where userid=".$USER_DETAILS["userid"]." and idx=".zbx_dbstr($idx);
			DBexecute($sql);
		}
	}

/***********************************/

/************ HISTORY **************/
	function get_user_history(){
		$history=array();
		$delimiter = new CSpan('&raquo;','delimiter');
		for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
			if($rows = get_profile('web.history.'.$i,false)){
				if($i>0){
					array_push($history,$delimiter);
				}
				$url = new CLink($rows[0],$rows[1],'history');
				array_push($history,array(SPACE,$url,SPACE));
			}
		}
	return $history;
	}

	function add_user_history($page){
	
		$title = explode('[',$page['title']);
		$title = $title[0];

		if(!(isset($page['hist_arg']) && is_array($page['hist_arg']))){
			return FALSE;
		}
		
		$url = '';
		foreach($page['hist_arg'] as $arg){
			if(isset($_REQUEST[$arg]) && !empty($_REQUEST[$arg])){
				$url.=((empty($url))?('?'):('&')).$arg.'='.$_REQUEST[$arg];
			}
		}
		$url = $page['file'].$url;

		$curr = 0;
		$profile = array();
		for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
			if($history = get_profile('web.history.'.$i,false)){
				if($history[0] != $title){
					$profile[$curr] = $history;
					$curr++;
				}
			}
		}
				
		$history = array($title,$url);
		
		if($curr < ZBX_HISTORY_COUNT){
			for($i = 0; $i < $curr; $i++){
				update_profile('web.history.'.$i,$profile[$i],PROFILE_TYPE_ARRAY);
			}
			$result = update_profile('web.history.'.$curr,$history,PROFILE_TYPE_ARRAY);
		} else {
			for($i = 1; $i < ZBX_HISTORY_COUNT; $i++){
				update_profile('web.history.'.($i-1),$profile[$i],PROFILE_TYPE_ARRAY);
			}
			$result = update_profile('web.history.'.(ZBX_HISTORY_COUNT-1),$history,PROFILE_TYPE_ARRAY);
		}

	return $result;
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


	function	add_mapping_to_valuemap($valuemapid, $mappings)
	{
		DBexecute("delete from mappings where valuemapid=$valuemapid");

		foreach($mappings as $map)
		{
			$mappingid = get_dbid("mappings","mappingid");

			$result = DBexecute("insert into mappings (mappingid,valuemapid, value, newvalue)".
				" values (".$mappingid.",".$valuemapid.",".zbx_dbstr($map["value"]).",".
				zbx_dbstr($map["newvalue"]).")");

			if(!$result)
				return $result;
		}
		return TRUE;
	}

	function	add_valuemap($name, $mappings)
	{
		if(!is_array($mappings))	return FALSE;

		$valuemapid = get_dbid("valuemaps","valuemapid");
		
		$result = DBexecute("insert into valuemaps (valuemapid,name) values ($valuemapid,".zbx_dbstr($name).")");
		if(!$result)
			return $result;

		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		else
		{
			$result = $valuemapid;
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

	function	set_image_header($format=null)
	{
		global $IMAGE_FORMAT_DEFAULT;

		if(is_null($format)) $format = $IMAGE_FORMAT_DEFAULT;
		
		if(IMAGE_FORMAT_JPEG == $format)	Header( "Content-type:  image/jpeg"); 
		if(IMAGE_FORMAT_TEXT == $format)	Header( "Content-type:  text/html"); 
		else					Header( "Content-type:  image/png"); 
		Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 
	}
	
	function ImageOut($image,$format=NULL)
	{
		global $IMAGE_FORMAT_DEFAULT;

		if(is_null($format)) $format = $IMAGE_FORMAT_DEFAULT;
		
		if(IMAGE_FORMAT_JPEG == $format)
			ImageJPEG($image);
		else
			ImagePNG($image);

		imagedestroy($image);
	}

	/* function:
	 *      get_cookie
	 *
	 * description:
	 *      return cookie value by name,
	 *      if cookie is not present return $default_value.
	 *
	 * author: Eugene Grigorjev
	 */
	function	get_cookie($name, $default_value=null){
		if(isset($_COOKIE[$name]))	return $_COOKIE[$name];
		// else
		return $default_value;
	}

	/* function:
	 *      zbx_setcookie
	 *
	 * description:
	 *      set cookies.
	 *
	 * author: Eugene Grigorjev
	 */
	function	zbx_setcookie($name, $value, $time=null){
		setcookie($name, $value, isset($time) ? $time : (0));
		$_COOKIE[$name] = $value;
	}
	
	/* function:
	 *      zbx_unsetcookie
	 *
	 * description:
	 *      unset and clear cookies.
	 *
	 * author: Eugene Grigorjev
	 */
	function	zbx_unsetcookie($name)
	{
		zbx_setcookie($name, null, -99999);
	}
	
	/* function:
	 *     zbx_flush_post_cookies
	 *
	 * description:
	 *     set posted cookies.
	 *
	 * author: Eugene Grigorjev
	 */
	function	zbx_flush_post_cookies($unset=false){
		global $ZBX_PAGE_COOKIES;

		if(isset($ZBX_PAGE_COOKIES)){
			foreach($ZBX_PAGE_COOKIES as $cookie){
				if($unset)
					zbx_unsetcookie($cookie[0]);
				else
					zbx_setcookie($cookie[0], $cookie[1], $cookie[2]);
			}
			unset($ZBX_PAGE_COOKIES);
		}
	}

	/* function:
	 *      zbx_set_post_cookie
	 *
	 * description:
	 *      set cookies after authorisation.
	 *      require calling 'zbx_flush_post_cookies' function
	 *	Called from:
	 *         a) in 'include/page_header.php'
	 *         b) from 'Redirect()'
	 *
	 * author: Eugene Grigorjev
	 */
	function	zbx_set_post_cookie($name, $value, $time=null)
	{
		global $ZBX_PAGE_COOKIES;

		$ZBX_PAGE_COOKIES[] = array($name, $value, isset($time) ? $time : (0));
	}

	function	inarr_isset($keys, $array=null)
	{
		global $_REQUEST;

		if(is_null($array)) $array =& $_REQUEST;

		if(is_array($keys))
		{
			foreach($keys as $id => $key)
			{
				if( !isset($array[$key]) )
					return false;
			}
			return true;
		}

		return isset($array[$keys]);
	}

	/* function:
	 *      zbx_rksort
	 *
	 * description:
	 *      Recursively sort an array by key
	 *
	 * author: Eugene Grigorjev
	 */
	function	zbx_rksort(&$array, $flags=NULL)
	{
		if(is_array($array))
		{
			foreach($array as $id => $data)
				zbx_rksort($array[$id]);

			ksort($array,$flags);
		}
		return $array;
	}

	/* function:
	 *      zbx_date2str
	 *
	 * description:
	 *      Convert timestamp to string representation. Retun 'Never' if 0.
	 *
	 * author: Alexei Vladishev
	 */
	function	zbx_date2str($format, $timestamp)
	{
		return ($timestamp==0)?S_NEVER:date($format,$timestamp);
	}

	function	encode_log($data)
	{
		if(defined('ZBX_LOG_ENCODING_DEFAULT') && function_exists('mb_convert_encoding'))
		{
			$new=mb_convert_encoding($data, S_HTML_CHARSET, ZBX_LOG_ENCODING_DEFAULT);
		}
		else
		{
			$new = $data;
		}
		return $new;
	}

	
	/* function:
	 *      validate_sort_and_sortorder
	 *
	 * description:
	 *      Checking,setting and saving sort params
	 *
	 * author: Aly
	 */
	function validate_sort_and_sortorder(){
		global $page;
		
		$_REQUEST['sort'] = get_request('sort',get_profile('web.'.$page["file"].'.sort',NULL));
		$_REQUEST['sortorder'] = get_request('sortorder',get_profile('web.'.$page["file"].'.sortorder',ZBX_SORT_UP));
		
		if(!is_null($_REQUEST['sort'])){
			$_REQUEST['sort'] = eregi_replace('[^a-z\.\_]','',$_REQUEST['sort']);
			update_profile('web.'.$page["file"].'.sort',		$_REQUEST['sort']);
		}

		if(!str_in_array($_REQUEST['sortorder'],array(ZBX_SORT_DOWN,ZBX_SORT_UP)))
			$_REQUEST['sortorder'] = ZBX_SORT_UP;

		update_profile('web.'.$page["file"].'.sortorder',	$_REQUEST['sortorder']);
	}

	/* function:
	 *      make_sorting_link
	 *
	 * description:
	 *      Creates links for sorting in table header
	 *
	 * author: Aly
	 */
	function make_sorting_link($obj,$tabfield,$url=''){
		global $page;
		
		$sortorder = ($_REQUEST['sortorder'] == ZBX_SORT_UP)?ZBX_SORT_DOWN:ZBX_SORT_UP;
		
		if(empty($url)){
			$url='?';
			$url_params = explode('&',$_SERVER['QUERY_STRING']);
			foreach($url_params as $id => $param){
				if(empty($param)) continue;
				
				list($name,$value) = explode('=',$param);
				if(empty($name) || ($name == 'sort') || (($name == 'sortorder'))) continue;
				$url.=$param.'&';
			}
		}
		else{
			$url.='&';
		}
		
		$link = new CLink($obj,$url.'sort='.$tabfield.'&sortorder='.$sortorder);
		
		if($tabfield == $_REQUEST['sort']){
			if($sortorder == ZBX_SORT_UP){
				$img = new CImg('images/general/sort_downw.gif','down',10,10);
			}
			else{
				$img = new CImg('images/general/sort_upw.gif','up',10,10);
			}
			
			$img->AddOption('style','line-height: 18px; vertical-align: middle;');
			$link = array($link,SPACE,$img);
		}		
		
	return $link;
	}
	
	function order_by($def,$allways=''){
		global $page;
	
		if(!empty($allways)) $allways = ','.$allways;
		$sortable = explode(',',$def);
		
		$tabfield = get_request('sort',get_profile('web.'.$page["file"].'.sort',null));
		
		if(is_null($tabfield)) return ' ORDER BY '.$def.$allways;
		if(!str_in_array($tabfield,$sortable)) return ' ORDER BY '.$def.$allways;

		$sortorder = get_request('sortorder',get_profile('web.'.$page["file"].'.sortorder',ZBX_SORT_UP));

	return ' ORDER BY '.$tabfield.' '.$sortorder.$allways;
	}
?>
