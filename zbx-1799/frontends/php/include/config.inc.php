<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
	require_once('include/debug.inc.php');
	
function __autoload($class_name){
	$class_name = zbx_strtolower($class_name);
	$api = array(
		'apiexception' => 1,
		'caction' => 1,
		'calert' => 1,
		'capiinfo' => 1,
		'capplication' => 1,
		'cevent' => 1,
		'cgraph' => 1,
		'cgraphitem' => 1,
		'chost' => 1,
		'chostgroup' => 1,
		'cimage' => 1,
		'citem' => 1,
		'cmaintenance' => 1,
		'cmap' => 1,
		'cproxy' => 1,
		'cscreen' => 1,
		'cscript' => 1,
		'ctemplate' => 1,
		'ctrigger' => 1,
		'cuser' => 1,
		'cusergroup' => 1,
		'cusermacro' => 1,
		'czbxapi' => 1
	);

	$rpc = array(
		'cjsonrpc' =>1,
		'czbxrpc' => 1,
		'cxmlrpc' => null,
		'csoap' => null,
		'csoapjr' => null);

	if(isset($api[$class_name]))
		require_once('api/classes/class.'.$class_name.'.php');
	else if(isset($rpc[$class_name]))
		require_once('api/rpc/class.'.$class_name.'.php');
	else
		require_once('include/classes/class.'.$class_name.'.php');
}
?>
<?php

	require_once('include/defines.inc.php');
	require_once('include/func.inc.php');
	require_once('include/html.inc.php');
	require_once('include/copt.lib.php');
	require_once('include/profiles.inc.php');
	require_once('conf/maintenance.inc.php');

	require_once('include/nodes.inc.php');
	require_once('include/hosts.inc.php');

	require_once('include/users.inc.php');
// GLOBALS
	global $USER_DETAILS, $USER_RIGHTS;

	$USER_DETAILS	= array();
	$USER_RIGHTS	= array();
// END OF GLOBALS

// Include Tactical Overview modules
	require_once('include/locales.inc.php');

	require_once('include/perm.inc.php');
	require_once('include/audit.inc.php');
	require_once('include/js.inc.php');

// Include Validation

	require_once('include/validate.inc.php');

	function zbx_err_handler($errno, $errstr, $errfile, $errline){
		error($errstr.'['.$errfile.':'.$errline.']');
//		show_messages();
//		die();
	}

	/********** START INITIALIZATION *********/

	set_error_handler('zbx_err_handler');

	global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID, $ZBX_CONFIGURATION_FILE, $DB;
	global $ZBX_SERVER, $ZBX_SERVER_PORT;
	global $ZBX_LOCALES;

	$ZBX_LOCALNODEID = 0;
	$ZBX_LOCMASTERID = 0;

	$ZBX_CONFIGURATION_FILE = './conf/zabbix.conf.php';
	$ZBX_CONFIGURATION_FILE = realpath(dirname($ZBX_CONFIGURATION_FILE)).'/'.basename($ZBX_CONFIGURATION_FILE);

	unset($show_setup);


	if(defined('ZBX_DENY_GUI_ACCESS')){
		if(isset($ZBX_GUI_ACCESS_IP_RANGE) && is_array($ZBX_GUI_ACCESS_IP_RANGE)){
			$user_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))?($_SERVER['HTTP_X_FORWARDED_FOR']):($_SERVER['REMOTE_ADDR']);
			if(!str_in_array($user_ip,$ZBX_GUI_ACCESS_IP_RANGE)) $DENY_GUI = TRUE;
		}
		else{
			$DENY_GUI = TRUE;
		}
	}

	if(file_exists($ZBX_CONFIGURATION_FILE) && !isset($_COOKIE['ZBX_CONFIG']) && !isset($DENY_GUI)){
		ob_start();
		include $ZBX_CONFIGURATION_FILE;
		ob_end_clean ();
		require_once('include/db.inc.php');

		$error = '';
		if(!DBconnect($error)){
			$_REQUEST['message'] = $error;

			define('ZBX_DISTRIBUTED', false);
			define('ZBX_PAGE_NO_AUTHORIZATION', true);

			$show_warning = true;
		}
		else{
			global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID;

// Init LOCAL NODE ID
			if($local_node_data = DBfetch(DBselect('SELECT * FROM nodes WHERE nodetype=1 ORDER BY nodeid'))){
				$ZBX_LOCALNODEID = $local_node_data['nodeid'];
				$ZBX_LOCMASTERID = $local_node_data['masterid'];

				$ZBX_NODES[$local_node_data['nodeid']] = $local_node_data;

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
		if(file_exists($ZBX_CONFIGURATION_FILE)){
			ob_start();
			include $ZBX_CONFIGURATION_FILE;
			ob_end_clean ();
		}

		require_once('include/db.inc.php');

		define('ZBX_PAGE_NO_AUTHORIZATION', true);
		define('ZBX_DISTRIBUTED', false);
		$show_setup = true;
	}

	if(!defined('ZBX_PAGE_NO_AUTHORIZATION')){
		check_authorisation();

		if(file_exists('include/locales/'.$USER_DETAILS['lang'].'.inc.php')){
			include_once('include/locales/'.$USER_DETAILS['lang'].'.inc.php');
			process_locales();
		}		
	}
	else{
		$USER_DETAILS = array(
			'alias' =>ZBX_GUEST_USER,
			'userid'=>0,
			'lang'  =>'en_gb',
			'type'  =>'0',
			'node'  =>array(
				'name'  =>'- unknown -',
				'nodeid'=>0)
			);
	}

	include_once('include/locales/en_gb.inc.php');
	process_locales();
	set_zbx_locales();
	
// INIT MB Strings if it's available
	init_mbstrings();
/*
//Require MB strings, otherwise show warning page.
	if(!isset($show_setup) && !isset($show_warning) && !init_mbstrings()){
		$_REQUEST['warning_msg'] = S_ZABBIX.SPACE.ZABBIX_VERSION.SPACE.S_REQUIRE_MB_STRING_MODULE;
		$show_warning = true;
	}
//*/

// Ajax - do not need warnings or Errors
	if((isset($DENY_GUI) || isset($show_setup) || isset($show_warning)) && (PAGE_TYPE_HTML <> detect_page_type())){
		header('Ajax-response: false');
		exit();
	}
//---

	if(isset($DENY_GUI)){
		unset($show_warning);
		include_once('warning.php');
	}

	if(isset($show_setup)){
		unset($show_setup);
		include_once('setup.php');
	}
	else if(isset($show_warning)){
		unset($show_warning);
		include_once('warning.php');
	}

	/********** END INITIALIZATION ************/

	function access_deny(){
		global $USER_DETAILS;
		include_once('include/page_header.php');

		if($USER_DETAILS['alias'] != ZBX_GUEST_USER){
			show_error_message(S_NO_PERMISSIONS);
		}
		else{
			$req = new Curl($_SERVER['REQUEST_URI']);
			$req->setArgument('sid', null);

			$warning_msg = array('You cannot view this URL as a ',bold(ZBX_GUEST_USER),'. ',
								'You must login to view this page.', BR(),
								'If you think this message is wrong, ',
								' please consult your administrators about getting the necessary permissions.');

			$table = new CTable(null, 'warning');
			$table->setAlign('center');
			$table->setHeader(new CCol('You are not logged in', 'left'),'header');

			$table->addRow(new CCol($warning_msg));

			$url = urlencode($req->toString());
			$footer = new CCol(
							array(
								new CButton('login',S_LOGIN,"javascript: document.location = 'index.php?request=$url';"),
								new CButton('back',S_CANCEL,'javascript: window.history.back();')
							),
							'left');
			$table->setFooter($footer,'footer');
			$table->show();
		}

		include_once('include/page_footer.php');
	}

	function detect_page_type($default=PAGE_TYPE_HTML){
		if(isset($_REQUEST['output'])){
			switch($_REQUEST['output']){
				case 'ajax':
					return PAGE_TYPE_JS;
					break;
				case 'json':
					return PAGE_TYPE_JS;
					break;
				case 'html':
					return PAGE_TYPE_HTML_BLOCK;
					break;
				case 'img':
					return PAGE_TYPE_IMAGE;
					break;
				case 'css':
					return PAGE_TYPE_CSS;
					break;
			}
		}
	return $default;
	}

	function show_messages($bool=TRUE,$okmsg=NULL,$errmsg=NULL){
		global	$page, $ZBX_MESSAGES;

		if(!defined('PAGE_HEADER_LOADED')) return;
		if(defined('ZBX_API_REQUEST')) return;

		if(!isset($page['type'])) $page['type'] = PAGE_TYPE_HTML;

		$message = array();
		$width = 0;
		$height= 0;
		$img_space = null;

		if(!$bool && !is_null($errmsg))		$msg='ERROR: '.$errmsg;
		else if($bool && !is_null($okmsg))	$msg=$okmsg;

		$api_errors = CZBXAPI::resetErrors();
		if(!empty($api_errors)) error($api_errors);

		if(isset($msg)){
			switch($page['type']){
				case PAGE_TYPE_IMAGE:
					array_push($message, array(
						'text'	=> $msg,
						'color'	=> (!$bool) ? array('R'=>255,'G'=>0,'B'=>0) : array('R'=>34,'G'=>51,'B'=>68),
						'font'	=> 2));
					$width = max($width, ImageFontWidth(2) * zbx_strlen($msg) + 1);
					$height += imagefontheight(2) + 1;
					break;
				case PAGE_TYPE_XML:
					echo htmlspecialchars($msg)."\n";
					break;
//				case PAGE_TYPE_JS: break;
				case PAGE_TYPE_HTML:
				default:
					$msg_tab = new CTable($msg,($bool?'msgok':'msgerr'));
					$msg_tab->setCellPadding(0);
					$msg_tab->setCellSpacing(0);

					$row = array();

					$msg_col = new CCol(bold($msg),'msg_main msg');
					$msg_col->setAttribute('id','page_msg');
					$row[] = $msg_col;

					if(isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)){
						$msg_details = new CDiv(S_DETAILS,'blacklink');
						$msg_details->setAttribute('onclick',new CJSscript("javascript: ShowHide('msg_messages', IE?'block':'table');"));
						$msg_details->setAttribute('title',S_MAXIMIZE.'/'.S_MINIMIZE);
						array_unshift($row, new CCol($msg_details,'clr'));
					}

					$msg_tab->addRow($row);
					$msg_tab->Show();

					$img_space = new CImg('images/general/tree/zero.gif','space','100','2');
					break;
			}
		}

		if(isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)){
			if($page['type'] == PAGE_TYPE_IMAGE){
				$msg_font = 2;
				foreach($ZBX_MESSAGES as $msg){
					if($msg['type'] == 'error'){
						array_push($message, array(
							'text'	=> $msg['message'],
							'color'	=> array('R'=>255,'G'=>55,'B'=>55),
							'font'	=> $msg_font));
					}
					else{
						array_push($message, array(
							'text'	=> $msg['message'],
							'color'	=> array('R'=>155,'G'=>155,'B'=>55),
							'font'	=> $msg_font));
					}
					$width = max($width, imagefontwidth($msg_font) * zbx_strlen($msg['message']) + 1);
					$height += imagefontheight($msg_font) + 1;
				}
			}
			else if($page['type'] == PAGE_TYPE_XML){
				foreach($ZBX_MESSAGES as $msg){
					echo '['.$msg['type'].'] '.$msg['message']."\n";
				}
			}
			else{
				$lst_error = new CList(null,'messages');

				foreach($ZBX_MESSAGES as $msg){
					$lst_error->addItem($msg['message'], $msg['type']);
					$bool = ($bool && ('error' != zbx_strtolower($msg['type'])));
				}

//message scroll if needed
				$msg_show = 6;
				$msg_count = count($ZBX_MESSAGES);

				if($msg_count > $msg_show){
					$msg_count = $msg_show;

					$msg_count = ($msg_count * 16);
					$lst_error->setAttribute('style','height: '.$msg_count.'px;');
				}


				$tab = new CTable(null,($bool?'msgok':'msgerr'));

				$tab->setCellPadding(0);
				$tab->setCellSpacing(0);

				$tab->setAttribute('id','msg_messages');
				$tab->setAttribute('style','width: 100%;');

				if(isset($msg_tab) && $bool){
					$tab->setAttribute('style','display: none;');
				}

				$tab->addRow(new CCol($lst_error,'msg'));
				$tab->Show();
//---
			}
			$ZBX_MESSAGES = null;
		}

		if(!is_null($img_space)) print(unpack_object($img_space));

		if($page['type'] == PAGE_TYPE_IMAGE && count($message) > 0){
			$width += 2;
			$height += 2;
			$canvas = imagecreate($width, $height);
			imagefilledrectangle($canvas,0,0,$width,$height, imagecolorallocate($canvas, 255, 255, 255));

			foreach($message as $id => $msg){

				$message[$id]['y'] = 1 + (isset($previd) ? $message[$previd]['y'] + $message[$previd]['h'] : 0 );
				$message[$id]['h'] = imagefontheight($msg['font']);

				imagestring(
					$canvas,
					$msg['font'],
					1,
					$message[$id]['y'],
					$msg['text'],
					imagecolorallocate($canvas, $msg['color']['R'], $msg['color']['G'], $msg['color']['B'])
					);

				$previd = $id;
			}
			imageOut($canvas);
			imagedestroy($canvas);
		}
	}

	function show_message($msg){
		show_messages(TRUE,$msg,'');
	}

	function show_error_message($msg){
		show_messages(FALSE,'',$msg);
	}

	function info($msgs){
		global $ZBX_MESSAGES;
		zbx_value2array($msgs);

		if(is_null($ZBX_MESSAGES))
			$ZBX_MESSAGES = array();

		foreach($msgs as $msg){
			array_push($ZBX_MESSAGES, array('type' => 'info', 'message' => $msg));
		}
	}

	function error($msgs){
		global $ZBX_MESSAGES, $USER_DETAILS;
		$msgs = zbx_toArray($msgs);

		if(is_null($ZBX_MESSAGES))
			$ZBX_MESSAGES = array();

		foreach($msgs as $msg){
			if(isset($USER_DETAILS['debug_mode']) && !is_object($msg) && !$USER_DETAILS['debug_mode']){
				$msg = preg_replace('/^\[.+?::.+?\]/', '', $msg);
			}
			array_push($ZBX_MESSAGES, array('type' => 'error', 'message' => $msg));
		}
	}

	function clear_messages(){
		global $ZBX_MESSAGES;

		$ZBX_MESSAGES = null;
	}

	function fatal_error($msg){
		include_once 'include/page_header.php';
		show_error_message($msg);
		include_once 'include/page_footer.php';
	}

	function get_tree_by_parentid($parentid,&$tree,$parent_field, $level=0){
		if(empty($tree)) return $tree;

		$level++;
		if($level > 32) return array();

		$result = array();
		if(isset($tree[$parentid])){
			$result[$parentid] = $tree[$parentid];
		}

		$tree_ids = array_keys($tree);

		foreach($tree_ids as $key => $id){
			$child = $tree[$id];
			if(bccomp($child[$parent_field],$parentid) == 0){
				$result[$id] = $child;
				$childs = get_tree_by_parentid($id,$tree,$parent_field, $level); // RECURSION !!!
				$result += $childs;
			}
		}

	return $result;
	}

//	The hash has form <md5sum of triggerid>,<sum of priorities>
	function calc_trigger_hash(){

		$priority = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0, 5=>0);
		$triggerids='';

		$sql = 'SELECT t.triggerid, t.priority '.
				' FROM triggers t '.
				' WHERE t.value='.TRIGGER_VALUE_TRUE.
					' AND '.DBin_node('t.triggerid').
					' AND exists('.
						'SELECT e.eventid '.
						' FROM events e '.
						' WHERE e.object='.EVENT_OBJECT_TRIGGER.
							' AND e.objectid=t.triggerid '.
							' AND e.acknowledged=0'.
						')';
       	$result=DBselect($sql);
		while($row=DBfetch($result)){
			$triggerids.= ','.$row['triggerid'];
			$priority[$row['priority']]++;
		}

		$md5sum = md5($triggerids);

		$priorities = 0;
		for($i=0; $i<=5; $i++)
			$priorities += pow(100,$i)*$priority[$i];

	return	$priorities.','.$md5sum;
	}

	function parse_period($str){
		$out = NULL;
		$str = trim($str,';');
		$periods = explode(';',$str);
		foreach($periods as $preiod){
//			if(!ereg('^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$', $preiod, $arr)) return NULL;
			if(!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $preiod, $arr)) return NULL;

			for($i = $arr[1]; $i <= $arr[2]; $i++){
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

	function find_period_start($periods,$time){
		$date = getdate($time);
		$wday = $date['wday'] == 0 ? 7 : $date['wday'];
		$curr = $date['hours']*100+$date['minutes'];

		if(isset($periods[$wday])){
			$next_h = -1;
			$next_m = -1;
			foreach($periods[$wday] as $period){
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
			if($next_h >= 0 && $next_m >= 0){
				return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);
			}
		}
		for($days=1; $days < 7 ; ++$days){
			$new_wday = (($wday + $days - 1)%7 + 1);
			if(isset($periods[$new_wday ])){
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

	function find_period_end($periods,$time,$max_time){
		$date = getdate($time);
		$wday = $date['wday'] == 0 ? 7 : $date['wday'];
		$curr = $date['hours']*100+$date['minutes'];

		if(isset($periods[$wday])){
			$next_h = -1;
			$next_m = -1;
			foreach($periods[$wday] as $period){
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
			if($next_h >= 0 && $next_m >= 0){
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

	function validate_period(&$str){
		$str = trim($str,';');
		$out = "";
		$periods = explode(';',$str);
		foreach($periods as $preiod){
			// arr[idx]   1       2         3             4            5            6
//			if(!ereg('^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$', $preiod, $arr)) return false;
			if(!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $preiod, $arr)) return false;

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

	function validate_float($str){
//		echo "Validating float:$str<br>";
//		if (eregi('^[ ]*([0-9]+)((\.)?)([0-9]*[KMG]{0,1})[ ]*$', $str, $arr)) {
		if(preg_match('/^[ ]*([0-9]+)((\.)?)([0-9]*[KMGTsmhdw]{0,1})[ ]*$/i', $str, $arr)) {
			return 0;
		}
		else{
			return -1;
		}
	}

// Check if str has format #<float> or <float>
	function validate_ticks($str){
//		echo "Validating float:$str<br>";
//		if (eregi('^[ ]*#([0-9]+)((\.)?)([0-9]*)[ ]*$', $str, $arr)) {
		if (preg_match('/^[ ]*#([0-9]+)((\.)?)([0-9]*)[ ]*$/i', $str, $arr)) {
			return 0;
		}
		else return validate_float($str);
	}

	function get_status(){
		global $ZBX_SERVER, $ZBX_SERVER_PORT;
		$status = array();
// server
		$checkport = fsockopen($ZBX_SERVER, $ZBX_SERVER_PORT, $errnum, $errstr, 2);
		if(!$checkport) {
			clear_messages();
			$status['zabbix_server'] = S_NO;
		}
		else {
			$status['zabbix_server'] = S_YES;
		}
// triggers
		$sql = 'SELECT COUNT(DISTINCT t.triggerid) as cnt '.
				' FROM triggers t, functions f, items i, hosts h'.
				' WHERE t.triggerid=f.triggerid '.
					' AND f.itemid=i.itemid '.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED;

		$row=DBfetch(DBselect($sql));
		$status['triggers_count']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND t.status=0'));
		$status['triggers_count_enabled']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND t.status=1'));
		$status['triggers_count_disabled']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND t.status=0 AND t.value=0'));
		$status['triggers_count_off']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND t.status=0 AND t.value=1'));
		$status['triggers_count_on']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND t.status=0 AND t.value=2'));
		$status['triggers_count_unknown']=$row['cnt'];

// items
		$sql = 'SELECT COUNT(DISTINCT i.itemid) as cnt '.
				' FROM items i, hosts h '.
				' WHERE i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED;

		$row=DBfetch(DBselect($sql));
		$status['items_count']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND i.status=0'));
		$status['items_count_monitored']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND i.status=1'));
		$status['items_count_disabled']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND i.status=3'));
		$status['items_count_not_supported']=$row['cnt'];

		$row=DBfetch(DBselect($sql.' AND i.type=2'));
		$status['items_count_trapper']=$row['cnt'];

// hosts
		$sql = 'SELECT COUNT(hostid) as cnt '.
				' FROM hosts '.
				' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.','.HOST_STATUS_DELETED.' )';
		$row=DBfetch(DBselect($sql));
		$status['hosts_count']=$row['cnt'];

		$row=DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_MONITORED));
		$status['hosts_count_monitored']=$row['cnt'];

		$row=DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_NOT_MONITORED));
		$status['hosts_count_not_monitored']=$row['cnt'];

		$row=DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_TEMPLATE));
		$status['hosts_count_template']=$row['cnt'];

		$row=DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_DELETED));
		$status['hosts_count_deleted']=$row['cnt'];

// users
		$row=DBfetch(DBselect('SELECT COUNT(userid) as cnt FROM users'));
		$status['users_count']=$row['cnt'];


		$status['users_online'] = 0;
		$sql = 'SELECT DISTINCT s.userid, MAX(s.lastaccess) as lastaccess, MAX(u.autologout) as autologout, s.status '.
				' FROM sessions s, users u '.
				' WHERE '.DBin_node('s.userid').
					' AND u.userid=s.userid '.
					' AND s.status='.ZBX_SESSION_ACTIVE.
				' GROUP BY s.userid,s.status';
		$db_users = DBselect($sql);
		while($user=DBfetch($db_users)){
			$online_time = (($user['autologout'] == 0) || (ZBX_USER_ONLINE_TIME<$user['autologout'])) ? ZBX_USER_ONLINE_TIME : $user['autologout'];
			if(!is_null($user['lastaccess']) && (($user['lastaccess']+$online_time)>=time()) && (ZBX_SESSION_ACTIVE == $user['status'])) $status['users_online']++;
		}


		/* Comments: !!! Don't forget sync code with C !!! */
		$result=DBselect('SELECT i.type, i.delay, count(*)/i.delay as qps '.
							' FROM items i,hosts h '.
							' WHERE i.status='.ITEM_STATUS_ACTIVE.
								' AND i.hostid=h.hostid '.
								' AND h.status='.HOST_STATUS_MONITORED.
							' GROUP BY i.type,i.delay ');

		$status['qps_total']=0;
		while($row=DBfetch($result)){
			$status['qps_total']+=$row['qps'];
		}

	return $status;
	}

	function get_resource_name($permission,$id){
		$res='-';
		if($permission=='Graph'){
			if(isset($id)&&($id!=0)){
				if($graph=get_graph_by_graphid($id))
					$res=$graph['name'];
			}
			else if(!isset($id) || $id == 0){
				$res='All graphs';
			}
		}
		else if($permission=='Host'){
			if(isset($id)&&($id!=0)){
				if($host=get_host_by_hostid($id))
					$res=$host['host'];
			}
			else if(!isset($id) || $id == 0){
				$res='All hosts';
			}
		}
		else if($permission=='Screen'){
			if(isset($id)&&($id!=0)){
				if($screen=get_screen_by_screenid($id))
					$res=$screen['name'];
			}
			else if(!isset($id) || $id == 0){
				$res='All screens';
			}
		}
		else if($permission=='Item'){
			if(isset($id)&&($id!=0)){
				if($item=get_item_by_itemid($id))
					if($host=get_host_by_hostid($item['hostid']))
						$res=$host['host'].':'.$item['description'];
			}
			else if(!isset($id) || $id == 0){
				$res='All items';
			}
		}
		else if($permission=='User'){
			if(isset($id)&&($id!=0)){
				$users = CUser::get(array('userids' => $id,  'extendoutput' => 1));
				if($user = reset($users)) $res = $user['alias'];
			}
			else if(!isset($id) || $id == 0){
				$res='All users';
			}
		}
		else if($permission=='Network map'){
			if(isset($id)&&($id!=0)){
				if($user=get_sysmap_by_sysmapid($id))
					$res=$user['name'];
			}
			else if(!isset($id) || $id == 0){
				$res='All maps';
			}
		}
		else if($permission=='Application'){
			if(isset($id)&&($id > 0)){
				if($app = get_application_by_applicationid($id))
					$res = $app['name'];
			}
			else if(!isset($id) || $id == 0){
				$res='All applications';
			}
		}
		else if($permission=='Service'){
			if(isset($id)&&($id > 0)){
				if($service = get_service_by_serviceid($id))
					$res = $service['name'];
			}
			else if(!isset($id) || $id == 0){
				$res='All services';
			}
		}

		if($res == '-' && isset($id) && ($id > 0))
			$res = $id;

		return $res;
	}

/* Use ImageSetStyle+ImageLIne instead of bugged ImageDashedLine */
	if(function_exists('imagesetstyle')){
		function DashedLine($image,$x1,$y1,$x2,$y2,$color){
// Style for dashed lines
//			$style = array($color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			$style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			ImageSetStyle($image, $style);
			ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
		}

	}
	else{
		function DashedLine($image,$x1,$y1,$x2,$y2,$color){
			ImageDashedLine($image,$x1,$y1,$x2,$y2,$color);
		}
	}

	function DashedRectangle($image,$x1,$y1,$x2,$y2,$color){
		DashedLine($image, $x1,$y1,$x1,$y2,$color);
		DashedLine($image, $x1,$y2,$x2,$y2,$color);
		DashedLine($image, $x2,$y2,$x2,$y1,$color);
		DashedLine($image, $x2,$y1,$x1,$y1,$color);
	}


	function set_image_header($format=null){
		global $IMAGE_FORMAT_DEFAULT;

		if(is_null($format)) $format = $IMAGE_FORMAT_DEFAULT;

		if(IMAGE_FORMAT_JPEG == $format)	Header( "Content-type:  image/jpeg");
		if(IMAGE_FORMAT_TEXT == $format)	Header( "Content-type:  text/html");
		else								Header( "Content-type:  image/png");

		Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT");
	}

	function ImageOut(&$image,$format=NULL){
		global $page;
		global $IMAGE_FORMAT_DEFAULT;

		if($page['type'] != PAGE_TYPE_IMAGE){
			ob_start();
			imagepng($image);
			$image_txt = ob_get_contents();
			ob_end_clean();
//SDI($image_txt);
			session_start();
			$id = md5($image_txt);
			$_SESSION['image_id'] = array();
			$_SESSION['image_id'][$id] = $image_txt;
			session_write_close();
			print($id);

//			print(base64_encode($image_txt));
		}
		else{
			if(is_null($format)) $format = $IMAGE_FORMAT_DEFAULT;

			if(IMAGE_FORMAT_JPEG == $format)
				imagejpeg($image);
			else
				imagepng($image);
		}

//		imagedestroy($image);
	}

	function encode_log($data){
		if(defined('ZBX_LOG_ENCODING_DEFAULT') && function_exists('mb_convert_encoding')){
			$new=mb_convert_encoding($data, S_HTML_CHARSET, ZBX_LOG_ENCODING_DEFAULT);
		}
		else{
			$new = $data;
		}
	return $new;
	}


	function zbx_stripslashes($value){
		if(is_array($value)){
			foreach($value as $id => $data)
				$value[$id] = zbx_stripslashes($data);
				// $value = array_map('zbx_stripslashes',$value); /* don't use 'array_map' it buggy with indexes */
		}
		else if(is_string($value)){
			$value = stripslashes($value);
		}
	return $value;
	}

	function get_str_month($num){
		$month = '[Wrong value for month: '.$num.']';
		switch($num){
			case 1: $month = S_JANUARY; break;
			case 2: $month = S_FEBRUARY; break;
			case 3: $month = S_MARCH; break;
			case 4: $month = S_APRIL; break;
			case 5: $month = S_MAY; break;
			case 6: $month = S_JUNE; break;
			case 7: $month = S_JULY; break;
			case 8: $month = S_AUGUST; break;
			case 9: $month = S_SEPTEMBER; break;
			case 10: $month = S_OCTOBER; break;
			case 11: $month = S_NOVEMBER; break;
			case 12: $month = S_DECEMBER; break;
		}

	return $month;
	}

	function get_str_dayofweek($num){
		$day = '[Wrong value for day of week: '.$num.']';
		switch($num){
			case 1: $day = S_MONDAY; break;
			case 2: $day = S_TUESDAY; break;
			case 3: $day = S_WEDNESDAY; break;
			case 4: $day = S_THURSDAY; break;
			case 5: $day = S_FRIDAY; break;
			case 6: $day = S_SATURDAY; break;
			case 7: $day = S_SUNDAY; break;
		}

	return $day;
	}
/*************** VALUE MAPPING ******************/
	function add_mapping_to_valuemap($valuemapid, $mappings){
		DBexecute("delete FROM mappings WHERE valuemapid=$valuemapid");

		foreach($mappings as $map){
			$mappingid = get_dbid("mappings","mappingid");

			$result = DBexecute("insert into mappings (mappingid,valuemapid, value, newvalue)".
				" values (".$mappingid.",".$valuemapid.",".zbx_dbstr($map["value"]).",".
				zbx_dbstr($map["newvalue"]).")");

			if(!$result)
				return $result;
		}
		return TRUE;
	}

	function add_valuemap($name, $mappings){
		if(!is_array($mappings))	return FALSE;

		$valuemapid = get_dbid("valuemaps","valuemapid");

		$result = DBexecute("insert into valuemaps (valuemapid,name) values ($valuemapid,".zbx_dbstr($name).")");
		if(!$result)
			return $result;

		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		else{
			$result = $valuemapid;
		}
		return $result;
	}

	function update_valuemap($valuemapid, $name, $mappings){
		if(!is_array($mappings))	return FALSE;

		$result = DBexecute('UPDATE valuemaps SET name='.zbx_dbstr($name).
			' WHERE valuemapid='.$valuemapid);

		if(!$result)
			return $result;

		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		return $result;
	}

	function delete_valuemap($valuemapid){
		DBexecute('DELETE FROM mappings WHERE valuemapid='.$valuemapid);
		DBexecute('DELETE FROM valuemaps WHERE valuemapid='.$valuemapid);
	return TRUE;
	}

	function replace_value_by_map($value, $valuemapid){ 
		if($valuemapid < 1) return $value; 
		
		static $valuemaps = array(); 
		if(isset($valuemaps[$valuemapid][$value])) return $valuemaps[$valuemapid][$value]; 
		
		$sql = 'SELECT newvalue '. 
				' FROM mappings '. 
				' WHERE valuemapid='.$valuemapid. 
					' AND value='.zbx_dbstr($value); 
		$result = DBselect($sql); 
		if($row = DBfetch($result)){ 
			$valuemaps[$valuemapid][$value] = $row['newvalue'].' '.'('.$value.')'; 
			return $valuemaps[$valuemapid][$value]; 
		} 
	
	return $value; 
	} 
/*************** END VALUE MAPPING ******************/


/*************** PAGE SORTING ******************/
	/* function:
	 *      validate_sort_and_sortorder
	 *
	 * description:
	 *      Checking,setting AND saving sort params
	 *
	 * author: Aly
	 */
	function validate_sort_and_sortorder($sort=NULL,$sortorder=ZBX_SORT_UP){
		global $page;

		$_REQUEST['sort'] = get_request('sort',CProfile::get('web.'.$page['file'].'.sort',$sort));
		$_REQUEST['sortorder'] = get_request('sortorder',CProfile::get('web.'.$page['file'].'.sortorder',$sortorder));

		if(!is_null($_REQUEST['sort'])){
//			$_REQUEST['sort'] = eregi_replace('[^a-z\.\_]','',$_REQUEST['sort']);
			$_REQUEST['sort'] = preg_replace('/[^a-z\.\_]/i','',$_REQUEST['sort']);
			CProfile::update('web.'.$page['file'].'.sort', $_REQUEST['sort'], PROFILE_TYPE_STR);
		}

		if(!str_in_array($_REQUEST['sortorder'],array(ZBX_SORT_DOWN,ZBX_SORT_UP)))
			$_REQUEST['sortorder'] = ZBX_SORT_UP;

		CProfile::update('web.'.$page['file'].'.sortorder', $_REQUEST['sortorder'], PROFILE_TYPE_STR);
	}

/* function:
 *      make_sorting_header
 *
 * description:
 *      Creates header col for sorting in table header
 *
 * author: Aly
 */
	function make_sorting_header($obj,$tabfield,$url=''){
		global $page;

		$sortorder = (($_REQUEST['sort'] == $tabfield) && ($_REQUEST['sortorder'] == ZBX_SORT_UP))?ZBX_SORT_DOWN:ZBX_SORT_UP;

		$link = new Curl($url);
		if(empty($url)) $link->formatGetArguments();
		$link->setArgument('sort', $tabfield);
		$link->setArgument('sortorder', $sortorder);

		$url = $link->getUrl();

		if(($page['type'] != PAGE_TYPE_HTML) && defined('ZBX_PAGE_MAIN_HAT')){
			$script = new CJSscript("javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
		}
		else{
			$script = new CJSscript("javascript: redirect('".$url."');");
		}

		$col = array(new CSpan($obj,'underline'));
		if(isset($_REQUEST['sort']) && ($tabfield == $_REQUEST['sort'])){
			if($sortorder == ZBX_SORT_UP){
				$img = new CImg('images/general/sort_down.png','down',10,10);
			}
			else{
				$img = new CImg('images/general/sort_up.png','up',10,10);
			}

			$img->setAttribute('style','line-height: 18px; vertical-align: middle;');
			$col[] = SPACE;
			$col[] = $img;
		}

		$col = new CCol($col, 'hover_grey');
		$col->setAttribute('onclick', $script);

	return $col;
	}

//TODO: should be replaced by "make_sorting_header" for every page.
	function make_sorting_link($obj,$tabfield,$url=''){
		global $page;

		$sortorder = (isset($_REQUEST['sortorder']) && ($_REQUEST['sortorder'] == ZBX_SORT_UP))?ZBX_SORT_DOWN:ZBX_SORT_UP;

		if(empty($url)){
			$url='?';
			$url_params = explode('&',$_SERVER['QUERY_STRING']);
			foreach($url_params as $id => $param){
				if(zbx_empty($param)) continue;

				list($name,$value) = explode('=',$param);
				if(zbx_empty($name) || ($name == 'sort') || (($name == 'sortorder'))) continue;
				$url.=$param.'&';
			}
		}
		else{
			$url.='&';
		}

		$url.='sort='.$tabfield.'&sortorder='.$sortorder;

		if(($page['type'] != PAGE_TYPE_HTML) && defined('ZBX_PAGE_MAIN_HAT')){
			$link = new CLink($obj,$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
		}
		else{
			$link = new CLink($obj,$url);
		}

		if(isset($_REQUEST['sort']) && ($tabfield == $_REQUEST['sort'])){
			if($sortorder == ZBX_SORT_UP){
				$img = new CImg('images/general/sort_down.png','down',10,10);
			}
			else{
				$img = new CImg('images/general/sort_up.png','up',10,10);
			}

			$img->setAttribute('style','line-height: 18px; vertical-align: middle;');
			$link = array($link,SPACE,$img);
		}

	return $link;
	}

	function getPageSortField($def){
		global $page;
		$tabfield = get_request('sort',CProfile::get('web.'.$page['file'].'.sort',$def));

	return $tabfield;
	}

	function getPageSortOrder($def=ZBX_SORT_UP){
		global $page;
		$sortorder = get_request('sortorder',CProfile::get('web.'.$page['file'].'.sortorder',$def));

	return $sortorder;
	}
/*************** END PAGE SORTING ******************/

/*************** RESULT SORTING ******************/

	function order_result(&$data, $sortfield, $sortorder=ZBX_SORT_UP){
		if(empty($data)) return false;

		$sort = array();
		foreach($data as $key => $arr){
			if(!isset($arr[$sortfield])) return false;
			$sort[$key] = $arr[$sortfield];
		}
		natcasesort($sort);

		if($sortorder != ZBX_SORT_UP)
			$sort = array_reverse($sort, true);

		$tmp = $data;
		$data = array();
		foreach($sort as $key => $val){
			$data[$key] = $tmp[$key];
		}

	return true;
	}

	function order_page_result(&$data, $def_field, $def_order=ZBX_SORT_UP){
		global $page;

		if(empty($data)) return false;

		$sortfield = get_request('sort',CProfile::get('web.'.$page['file'].'.sort',$def_field));
		$sortorder = get_request('sortorder',CProfile::get('web.'.$page['file'].'.sortorder',$def_order));

	return order_result($data, $sortfield, $sortorder, true);
	}

	function order_by($def,$allways=''){
		global $page;

		if(!empty($allways)) $allways = ','.$allways;
		$sortable = explode(',',$def);

		$tabfield = get_request('sort',CProfile::get('web.'.$page["file"].'.sort',null));

		if(is_null($tabfield)) return ' ORDER BY '.$def.$allways;
		if(!str_in_array($tabfield,$sortable)) return ' ORDER BY '.$def.$allways;

		$sortorder = get_request('sortorder',CProfile::get('web.'.$page["file"].'.sortorder',ZBX_SORT_UP));

	return ' ORDER BY '.$tabfield.' '.$sortorder.$allways;
	}
/*************** END RESULT SORTING ******************/

// Selection
	function selectByPattern(&$table, $column, $pattern, $limit){
		$chunk_size = $limit;

		$rsTable = array();
		foreach($table as $num => $row){
			if(zbx_strtoupper($row[$column]) == zbx_strtoupper($pattern))
				$rsTable = array($num=>$row) + $rsTable;
			else if($limit > 0)
				$rsTable[$num] = $row;
			else
				continue;

			$limit--;
		}

		if(!empty($rsTable)){
			$rsTable = array_chunk($rsTable, $chunk_size, true);
			$rsTable = $rsTable[0];
		}

	return $rsTable;
	}
?>
