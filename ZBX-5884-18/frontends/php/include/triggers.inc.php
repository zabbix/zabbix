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
** along with this program; ifnot, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

/*
 * Function: get_accessible_triggers
 *
 * Description:
 *	 returns string of accessible triggers
 *
 * Author:
 *	 Aly mod by Vedmak
 *
 */
function get_accessible_triggers($perm, $hostids, $cache=1){
	global $USER_DETAILS;
	static $available_triggers;

	$userid = $USER_DETAILS['userid'];

	$nodeid = get_current_nodeid();

	$nodeid_str = (is_array($nodeid)) ? implode('', $nodeid) : strval($nodeid);
	$hostid_str = implode('',$hostids);

	$cache_hash = md5($userid.$perm.$nodeid_str.$hostid_str);
	if($cache && isset($available_triggers[$cache_hash])){
		return $available_triggers[$cache_hash];
	}

	$options = array(
		'output' => API_OUTPUT_SHORTEN,
		'nodeids' => $nodeid,
	);
	if(!empty($hostids)) $options['hostids'] = $hostids;
	if($perm == PERM_READ_WRITE) $options['editable'] = 1;
	$result = CTrigger::get($options);
	$result = zbx_objectValues($result, 'triggerid');
	$result = zbx_toHash($result);

	$available_triggers[$cache_hash] = $result;

return $result;
}

/*
 * Function: getEventColor()
 * Description: convert trigger severity and event value in to the RGB color
 * Author: Aly
 */
function getEventColor($severity, $value=TRIGGER_VALUE_TRUE){
	if($value == TRIGGER_VALUE_FALSE) return 'AADDAA';

	switch($severity){
		case TRIGGER_SEVERITY_DISASTER: $color='FF0000'; break;
		case TRIGGER_SEVERITY_HIGH: $color='FF8888'; break;
		case TRIGGER_SEVERITY_AVERAGE: $color='DDAAAA'; break;
		case TRIGGER_SEVERITY_WARNING: $color='EFEFCC'; break;
		case TRIGGER_SEVERITY_INFORMATION: $color='CCE2CC'; break;
		default: $color='BCBCBC';
	}

return $color;
}

/*
 * Function: get_severity_style()
 * Description: convert severity constant in to the CSS style name
 * Author: Aly
 */
function get_severity_style($severity,$type=true){
	switch($severity){
		case TRIGGER_SEVERITY_DISASTER: $style='disaster'; break;
		case TRIGGER_SEVERITY_HIGH: $style='high'; break;
		case TRIGGER_SEVERITY_AVERAGE: $style='average'; break;
		case TRIGGER_SEVERITY_WARNING: $style='warning'; break;
		case TRIGGER_SEVERITY_INFORMATION:
		default: $style='information';
	}

	if(!$type) $style='normal';//$style.='_empty';
return $style;
}

/*
 * Function: getSeverityCaption()
 * Description: convert severity constant in to the CSS style name
 * Author: Aly
 */
function getSeverityCaption($severity){
	switch($severity){
		case TRIGGER_SEVERITY_DISASTER: $caption=S_DISASTER; break;
		case TRIGGER_SEVERITY_HIGH:		$caption=S_HIGH; break;
		case TRIGGER_SEVERITY_AVERAGE:	$caption=S_AVERAGE; break;
		case TRIGGER_SEVERITY_WARNING:	$caption=S_WARNING; break;
		case TRIGGER_SEVERITY_INFORMATION: $caption=S_INFORMATION; break;
		default: $caption=S_NOT_CLASSIFIED;
	}

return $caption;
}

/*
 * Function: get_service_status_of_trigger
 *
 * Description:
 *	 retrieve trigger's priority for services
 *
 * Author:
 *	 Aly
 *
 * Comments:
 *
 */

	function get_service_status_of_trigger($triggerid){
		$sql = 'SELECT triggerid, priority '.
				' FROM triggers '.
				' WHERE triggerid='.$triggerid.
					' AND status='.TRIGGER_STATUS_ENABLED.
					' AND value='.TRIGGER_VALUE_TRUE;

		$status = ($rows=DBfetch(DBselect($sql,1)))?$rows['priority']:0;

	return $status;
	}

/*
 * Function: get_severity_description
 *
 * Description:
 *	 convert severity constant in to the string representation
 *
 * Author:
 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *
 */
	function get_severity_description($severity=null){
		$severities = array(
			TRIGGER_SEVERITY_NOT_CLASSIFIED => S_NOT_CLASSIFIED,
			TRIGGER_SEVERITY_INFORMATION => S_INFORMATION,
			TRIGGER_SEVERITY_WARNING => S_WARNING,
			TRIGGER_SEVERITY_AVERAGE => S_AVERAGE,
			TRIGGER_SEVERITY_HIGH => S_HIGH,
			TRIGGER_SEVERITY_DISASTER => S_DISASTER,
		);

		if(is_null($severity))
			return $severities;
		else if(isset($severities[$severity]))
			return $severities[$severity];
		else return S_UNKNOWN;
	}

	function get_trigger_value_style($value){
		$str_val[TRIGGER_VALUE_FALSE]	= 'off';
		$str_val[TRIGGER_VALUE_TRUE]	= 'on';
		$str_val[TRIGGER_VALUE_UNKNOWN]	= 'unknown';

		if(isset($str_val[$value]))
			return $str_val[$value];

		return '';
	}

	function trigger_value2str($value){
		$str_val[TRIGGER_VALUE_FALSE]	= S_OK_BIG;
		$str_val[TRIGGER_VALUE_TRUE]	= S_PROBLEM_BIG;
		$str_val[TRIGGER_VALUE_UNKNOWN]	= S_UNKNOWN_BIG;

		if(isset($str_val[$value]))
			return $str_val[$value];

		return S_UNKNOWN;
	}

	function discovery_value($val = null){
		$array = array(
			DOBJECT_STATUS_UP => S_UP_BIG,
			DOBJECT_STATUS_DOWN => S_DOWN_BIG,
			DOBJECT_STATUS_DISCOVER => S_DISCOVERED_BIG,
			DOBJECT_STATUS_LOST => S_LOST_BIG,
		);

		if(is_null($val))
			return $array;
		else if(isset($array[$val]))
			return $array[$val];
		else
			return S_UNKNOWN;
	}

	function discovery_value_style($val){
		switch($val){
			case DOBJECT_STATUS_UP: $style = 'off'; break;
			case DOBJECT_STATUS_DOWN: $style = 'on'; break;
			case DOBJECT_STATUS_DISCOVER: $style = 'off'; break;
			case DOBJECT_STATUS_LOST: $style = 'unknown'; break;
			default: $style = '';
		}

		return $style;
	}

/*
 * Function: get_realhosts_by_triggerid
 *
 * Description:
 *	 retrieve real host for trigger
 *
 * Author:
 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *
 */
	function get_realhosts_by_triggerid($triggerid){
		$trigger = get_trigger_by_triggerid($triggerid);
		if($trigger['templateid'] > 0)
			return get_realhosts_by_triggerid($trigger['templateid']);

		return get_hosts_by_triggerid($triggerid);
	}

/*
 * Function: getParentHostsByTriggers
 *
 * Description:
 *	 retrieve real hostw for triggerw
 *
 * Author:
 *	 Aly (aly@zabbix.com)
 *
 * Comments:
 *
 */
	function getParentHostsByTriggers($triggers){
		$hosts = array();
		$triggerParent = array();

		while(!empty($triggers)){
			foreach($triggers as $tnum => $trigger){

				if($trigger['templateid'] == 0){
					if(isset($triggerParent[$trigger['triggerid']])){
						foreach($triggerParent[$trigger['triggerid']] as $triggerid => $state){
							$hosts[$triggerid] = $trigger['hosts'];
						}
					}
					else{
						$hosts[$trigger['triggerid']] = $trigger['hosts'];
					}
					unset($triggers[$tnum]);
				}
				else{
					if(isset($triggerParent[$trigger['triggerid']])){
						if(!isset($triggerParent[$trigger['templateid']]))
							$triggerParent[$trigger['templateid']] = array();

						$triggerParent[$trigger['templateid']][$trigger['triggerid']] = 1;
						$triggerParent[$trigger['templateid']] += $triggerParent[$trigger['triggerid']];
					}
					else{
						if(!isset($triggerParent[$trigger['templateid']]))
							$triggerParent[$trigger['templateid']] = array();

						$triggerParent[$trigger['templateid']][$trigger['triggerid']] = 1;
					}
				}
			}
//SDII($triggerParent);
			$options = array(
				'triggerids' => zbx_objectValues($triggers, 'templateid'),
				'select_hosts' => array('hostid','host','status'),
				'output' => array('triggerid','templateid'),
				'nopermissions' => true
			);

			$triggers = CTrigger::get($options);
		}

	return $hosts;
	}

	function get_trigger_by_triggerid($triggerid){
		$sql='select * from triggers where triggerid='.$triggerid;
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		error(S_NO_TRIGGER_WITH.' triggerid=['.$triggerid.']');
		return FALSE;
	}

	function get_hosts_by_triggerid($triggerids){
		zbx_value2array($triggerids);

		return DBselect('SELECT DISTINCT h.* '.
						' FROM hosts h, functions f, items i '.
						' WHERE i.itemid=f.itemid '.
							' AND h.hostid=i.hostid '.
							' AND '.DBcondition('f.triggerid',$triggerids));
	}

	function get_functions_by_triggerid($triggerid){
		return DBselect('select * from functions where triggerid='.$triggerid);
	}

/*
 * Function: get_triggers_by_hostid
 *
 * Description:
 *	 retrieve selection of triggers by hostid
 *
 * Author:
 *	Aly
 *
 * Comments:
 *
 */
	function get_triggers_by_hostid($hostid){
		$db_triggers = DBselect('SELECT DISTINCT t.* '.
								' FROM triggers t, functions f, items i '.
								' WHERE i.hostid='.$hostid.
									' AND f.itemid=i.itemid '.
									' AND f.triggerid=t.triggerid');
	return $db_triggers;
	}


/*
 * Function: get_trigger_by_description
 *
 * Description:
 *	 retrieve triggerid by description
 *
 * Author:
 *	Aly
 *
 * Comments:
 *	  description - host-name:trigger-description. Example( "unix server:low free disk space")
 */

	function get_trigger_by_description($desc){
		list($host_name, $trigger_description) = explode(':',$desc,2);

		$sql = 'SELECT t.* '.
				' FROM triggers t, items i, functions f, hosts h '.
				' WHERE h.host='.zbx_dbstr($host_name).
					' AND i.hostid=h.hostid '.
					' AND f.itemid=i.itemid '.
					' AND t.triggerid=f.triggerid '.
					' AND t.description='.zbx_dbstr($trigger_description).
				' ORDER BY t.triggerid DESC';
		$trigger = DBfetch(DBselect($sql,1));
	return $trigger;
	}

	function get_triggers_by_templateid($triggerids) {
		zbx_value2array($triggerids);
		return DBselect('SELECT * FROM triggers WHERE '.DBcondition('templateid', $triggerids));
	}

/*
 * Function: zbx_unquote_param
 *
 * Description:
 *	 unquote string and unescape cahrs
 *
 * Author:
 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *	 Double quotes used only.
 *	 Unquote string only ifvalue directly in quotes.
 *	 Unescape only '\\' and '\"' combination
 *
 */
	function zbx_unquote_param($value){
		$value = trim($value);
		if( !empty($value) && '"' == zbx_substr($value, 0, 1) ){
/* open quotes and unescape chars */
			$value = zbx_substr($value, 1, zbx_strlen($value)-2);

			$new_val = '';
			for ( $i=0, $max=zbx_strlen($value); $i < $max; $i++){

				$current_char = zbx_substr($value, $i, 1);
				$next_char = zbx_substr($value, $i+1, 1);

				if( $i+1 < $max && $current_char == '\\' && ($next_char == '\\' || $next_char == '"') ){
					$new_val .= $next_char;
					$i = $i + 1;
				}
				else
					$new_val .= $current_char;
			}
			$value = $new_val;
		}
	return $value;
	}


/*
 * Function: utf8RawUrlDecode
 *
 * Description:
 *	 unescape Raw URL
 *
 * Author: Vlad
 */
function utf8RawUrlDecode($source){
	$decodedStr = "";
	$pos = 0;
	$len = strlen($source);
	while($pos < $len){
		$charAt = substr($source, $pos, 1);
		if($charAt == '%'){
			$pos++;
			$charAt = substr($source, $pos, 1);
			if($charAt == 'u'){
				// we got a unicode character
				$pos++;
				$unicodeHexVal = substr($source, $pos, 4);
				$unicode = hexdec($unicodeHexVal);
				$entity = "&#" . $unicode . ';';
				$decodedStr .= html_entity_decode(  utf8_encode($entity), ENT_COMPAT, 'UTF-8' );
				$pos += 4;
			}
			else{
				// we have an escaped ascii character
				// $hexVal = substr($source, $pos, 2);
				// $decodedStr .= chr(hexdec($hexVal));
				// $pos += 2;
				$decodedStr .= substr($source, $pos-1, 1);
			}
		}
		else{
			$decodedStr .= $charAt;
			$pos++;
		}
	}
	return $decodedStr;
}

/*
 * Function: zbx_get_params
 *
 * Description:
 *	 parse list of quoted parameters
 *
 * Author:
 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *	 Double quotes used only.
 *
 */
	function zbx_get_params($string){
		$params = array();
		$quoted = false;
		$prev_char = '';

		$len = zbx_strlen($string);
		for( $param_s = $i = 0; $i < $len; $i++){
			$char = zbx_substr($string, $i, 1);
			switch( $char ){
				case '"':
					if ($prev_char!='\\'){
						$quoted = !$quoted;
					}
					break;
				case ',':
					if( !$quoted ){
						$params[] = zbx_unquote_param(zbx_substr($string, $param_s, $i - $param_s));
						$param_s = $i+1;
					}
					break;
				case '\\':
					$next_symbol = zbx_substr($string, $i+1, 1);

					if( $quoted && $i+1 < $len && ($next_symbol == '\\' || $next_symbol == '"'))
						$i++;
					break;
			}
			$prev_char = $char;
		}

		if( $quoted ){
			error(S_INCORRECT_USAGE_OF_QUOTES.'. ['.$string.']');
			return null;
		}

		if($i > $param_s){
			$params[] = str_replace('\\"', '"', zbx_unquote_param(zbx_substr($string, $param_s, $i - $param_s)));
		}

	return $params;

	}

	function add_trigger($expression, $description, $type, $priority, $status, $comments, $url, $deps=array(), $templateid=0){

		// URL validation against XSS
		if ($url && !validateUrl($url)) {
			error(S_INCORRECT_URL);
			return false;
		}

		$expressionData = new CTriggerExpression();
		if (!$expressionData->parse($expression)) {
			error($expressionData->error);
			return false;
		}

		if (!validate_trigger_dependency($expression, $deps)) {
			error(S_WRONG_DEPENDENCY_ERROR);
			return false;
		}

		if (CTrigger::exists(array('description' => $description, 'expression' => $expression))){
			error('Trigger with name "'.$description.'" and expression "'.$expression.'" already exists.');
			return false;
		}

		if (!validate_trigger_expression($expressionData)) {
			return false;
		}

		$triggerid = get_dbid('triggers','triggerid');

		$result = DBexecute('INSERT INTO triggers '.
			'  (triggerid,description,type,priority,status,comments,url,value,error,templateid) '.
			" values ($triggerid,".zbx_dbstr($description).",$type,$priority,$status,".zbx_dbstr($comments).','.
			zbx_dbstr($url).",2,'Trigger just added. No status update so far.',$templateid)");

		if (!$result) {
			return	$result;
		}

		addEvent($triggerid, TRIGGER_VALUE_UNKNOWN);

		$expression = implode_exp($expression,$triggerid);
		if (is_null($expression)) {
			return false;
		}

		DBexecute('update triggers set expression='.zbx_dbstr($expression).' where triggerid='.$triggerid);

		$trig_hosts = get_hosts_by_triggerid($triggerid);
		$trig_host = DBfetch($trig_hosts);

		$msg = S_ADDED_TRIGGER.SPACE.'"'.$trig_host['host'].':'.$description.'"';
		info($msg);

		if ($trig_host) {
			// create trigger for childs
			$child_hosts = get_hosts_by_templateid($trig_host['hostid']);
			while ($child_host = DBfetch($child_hosts)) {
				if (!$result = copy_trigger_to_host($triggerid, $child_host['hostid'])) {
					return false;
				}
			}
		}

		// add trigger dependencies
		foreach ($deps as $triggerid_up) {
			if (!$result2 = add_trigger_dependency($triggerid, $triggerid_up)) {
				error(S_INCORRECT_DEPENDENCY.' ['.expand_trigger_description($triggerid_up).']');
				return false;
			}
		}

		if (!$result) {
			if ($templateid == 0) {
				// delete main trigger (and recursively childs)
				delete_trigger($triggerid);
			}
			return $result;
		}

		if ($result) {
			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TRIGGER,	$triggerid,	$trig_host['host'].':'.$description, NULL,	NULL, NULL);
		}

		return $triggerid;
	}


	/******************************************************************************
	 *																			*
	 * Comments: !!! Don't forget sync code with C !!!							*
	 *																			*
	 ******************************************************************************/
	function copy_trigger_to_host($triggerid, $hostid, $copy_mode = false){
		$trigger = get_trigger_by_triggerid($triggerid);
		// $deps = replace_template_dependencies(
					// get_trigger_dependencies_by_triggerid($triggerid),
					// $hostid);
		$sql='SELECT t2.triggerid, t2.expression '.
				' FROM triggers t2, functions f1, functions f2, items i1, items i2 '.
				' WHERE f1.triggerid='.$triggerid.
					' AND i1.itemid=f1.itemid '.
					' AND f2.function=f1.function '.
					' AND f2.parameter=f1.parameter '.
					' AND i2.itemid=f2.itemid '.
					' AND i2.key_=i1.key_ '.
					' AND i2.hostid='.$hostid.
					' AND t2.triggerid=f2.triggerid '.
					' AND t2.description='.zbx_dbstr($trigger['description']).
					' AND t2.templateid=0 ';

		$host_triggers = DBSelect($sql);
		while($host_trigger = DBfetch($host_triggers)){
			if(cmp_triggers_exressions($trigger['expression'], $host_trigger['expression'])) continue;
			// link not linked trigger with same expression
			return update_trigger(
				$host_trigger['triggerid'],
				NULL,	// expression
				$trigger['description'],
				$trigger['type'],
				$trigger['priority'],
				NULL,	// status
				$trigger['comments'],
				$trigger['url'],
				array(),
				$copy_mode ? 0 : $triggerid);
		}

		$newtriggerid=get_dbid('triggers','triggerid');

		$result = DBexecute('INSERT INTO triggers '.
					' (triggerid,description,type,priority,status,comments,url,value,expression,templateid)'.
					' VALUES ('.$newtriggerid.','.zbx_dbstr($trigger['description']).','.$trigger['type'].','.$trigger['priority'].','.
					$trigger['status'].','.zbx_dbstr($trigger['comments']).','.
					zbx_dbstr($trigger['url']).",2,'0',".($copy_mode ? 0 : $triggerid).')');

		if(!$result)
			return $result;

		$host = get_host_by_hostid($hostid);
		$newexpression = $trigger['expression'];

		// Loop: functions
		$functions = get_functions_by_triggerid($triggerid);
		while($function = DBfetch($functions)){
			$item = get_item_by_itemid($function['itemid']);

			$host_items = DBselect('SELECT * FROM items WHERE key_='.zbx_dbstr($item['key_']).' AND hostid='.$host['hostid']);
			$host_item = DBfetch($host_items);
			if(!$host_item){
				error(S_MISSING_KEY.SPACE.'"'.$item['key_'].'"'.SPACE.S_FOR_HOST_SMALL.SPACE.'"'.$host['host'].'"');
				return FALSE;
			}

			$newfunctionid=get_dbid('functions','functionid');

			$result = DBexecute('INSERT INTO functions (functionid,itemid,triggerid,function,parameter) '.
				" values ($newfunctionid,".$host_item['itemid'].','.$newtriggerid.','.
				zbx_dbstr($function['function']).','.zbx_dbstr($function['parameter']).')');

			$newexpression = str_replace(
				'{'.$function['functionid'].'}',
				'{'.$newfunctionid.'}',
				$newexpression);
		}

		DBexecute('UPDATE triggers SET expression='.zbx_dbstr($newexpression).' WHERE triggerid='.$newtriggerid);

		info(S_ADDED_TRIGGER.SPACE.'"'.$host['host'].':'.$trigger['description'].'"');
		add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TRIGGER, $newtriggerid, $host['host'].':'.$trigger['description'], NULL, NULL, NULL);
		// Copy triggers to the child hosts
		$child_hosts = get_hosts_by_templateid($hostid);
		while ($child_host = DBfetch($child_hosts)) {
			// recursion
			$result = copy_trigger_to_host($newtriggerid, $child_host['hostid']);
			if (!$result) {
				return result;
			}
		}

		return $newtriggerid;
	}


	function construct_expression($itemid,$expressions){
		$complite_expr='';

		$item = get_item_by_itemid($itemid);
		$host = get_host_by_itemid($itemid);

		$prefix = $host['host'].':'.$item['key_'].'.';

		if(empty($expressions)){
			error(S_EXPRESSION_CANNOT_BE_EMPTY);
			return false;
		}
		$functions = array('regexp'=>1,'iregexp'=>1);

//		$ZBX_EREG_EXPESSION_FUNC_FORMAT = '^([[:print:]]*)([&|]{1})(([a-zA-Z_.$]{6,7})(\\(([[:print:]]+){0,1}\\)))([[:print:]]*)$';
		$ZBX_PREG_EXPESSION_FUNC_FORMAT = '^(['.ZBX_PREG_PRINT.']*)([&|]{1})(([a-zA-Z_.\$]{6,7})(\\((['.ZBX_PREG_PRINT.']+){0,1}\\)))(['.ZBX_PREG_PRINT.']*)$';

		$expr_array = array();

		$cexpor = 0;
		$startpos = -1;

		foreach($expressions as $id => $expression){
			$expression['value'] = preg_replace('/\s+(AND){1,2}\s+/U', '&', $expression['value']);
			$expression['value'] = preg_replace('/\s+(OR){1,2}\s+/U', '|', $expression['value']);
//sdi('<pre>'.print_r($expression['value'],true).'</pre>');
			$pastcexpor = $cexpor;
			if($expression['type'] == REGEXP_INCLUDE){
				if(!empty($complite_expr)) {
					$complite_expr.=' | ';
				}
				if($cexpor == 0){
					 $startpos = zbx_strlen($complite_expr);
				}
				$cexpor++;
				$eq_global = '#0';
			}
			else{
				if(($cexpor > 1) & ($startpos >= 0)){
					$head = substr($complite_expr, 0, $startpos);
					$tail = substr($complite_expr, $startpos);
					$complite_expr = $head.'('.$tail.')';
				}
				$cexpor = 0;
				$eq_global = '=0';
				if(!empty($complite_expr)) {
					$complite_expr.=' & ';
				}
			}

			$expr = '&'.$expression['value'];
			//$expr = '&'.$expression['view'];
			$expr = preg_replace('/\s+(\&|\|){1,2}\s+/U','$1',$expr);

			$expr_array = array();
			$sub_expr_count=0;
			$sub_expr = '';

			$multi = preg_match('/.+(&|\|).+/', $expr);

//			while(mb_eregi($ZBX_EREG_EXPESSION_FUNC_FORMAT, $expr, $arr)){
			while(preg_match('/'.$ZBX_PREG_EXPESSION_FUNC_FORMAT.'/i', $expr, $arr)){
				$arr[4] = zbx_strtolower($arr[4]);

				if(!isset($functions[$arr[4]])){
					error(S_INCORRECT_FUNCTION_IS_USED.'. ['.$expression['value'].']');
					return false;
				}

				$expr_array[$sub_expr_count]['eq'] = trim($arr[2]);
				$expr_array[$sub_expr_count]['regexp'] = zbx_strtolower($arr[4]).$arr[5];

				$sub_expr_count++;
				$expr = $arr[1];
			}

			if(empty($expr_array)){
				error(S_INCORRECT_TRIGGER_EXPRESSION.'. ['.$expression['value'].']');
				return false;
			}

			$expr_array[$sub_expr_count-1]['eq'] = '';

			$sub_eq = '';
			if($multi > 0){
				$sub_eq = $eq_global;
			}

			foreach($expr_array as $id => $expr){
				if($multi > 0){
					$sub_expr = $expr['eq'].'({'.$prefix.$expr['regexp'].'})'.$sub_eq.$sub_expr;
				}
				else{
					$sub_expr = $expr['eq'].'{'.$prefix.$expr['regexp'].'}'.$sub_eq.$sub_expr;
				}
			}

			if($multi > 0){
				$complite_expr.= '('.$sub_expr.')';
			}
			else{
				$complite_expr.= '(('.$sub_expr.')'.$eq_global.')';
			}
		}

		if(($cexpor > 1) & ($startpos >= 0)){
			$head = substr($complite_expr, 0, $startpos);
			$tail = substr($complite_expr, $startpos);
			$complite_expr = $head.'('.$tail.')';
		}

	return $complite_expr;
	}

/******************************************************************************
 *																			*
 * Purpose: Translate {10}>10 to something like localhost:procload.last(0)>10 *
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function explode_exp($expression, $html = false, $resolve_macro = false, $src_host = null, $dst_host = null){
//		echo "EXPRESSION:",$expression,"<Br>";
		$functionid='';
		$macros = '';
		if(!$html){
			$exp='';
		}
		else{
			$exp=array();
		}

		$trigger=array();
		$state='';

		for($i=0,$max=zbx_strlen($expression); $i<$max; $i++){
			if(($expression[$i] == '{') && ($expression[$i+1] == '$')){
				$functionid='';
				$macros='';
				$state='MACROS';
			}
			else if($expression[$i] == '{'){
				$functionid='';
				$state='FUNCTIONID';
				continue;
			}

			if($expression[$i] == '}'){
				if($state == 'MACROS'){
					$macros.='}';

					if($resolve_macro){
						$function_data['expression'] = $macros;
						CUserMacro::resolveTrigger($function_data);
						$macros = $function_data['expression'];
					}

					if($html) array_push($exp,$macros);
					else $exp.=$macros;

					$macros = '';
					$state = '';
					continue;
				}

				$state='';
				$sql = 'SELECT h.host,i.itemid,i.key_,f.function,f.triggerid,f.parameter,i.itemid,i.status, i.type'.
						' FROM items i,functions f,hosts h'.
						' WHERE f.functionid='.$functionid.
							' AND i.itemid=f.itemid '.
							' AND h.hostid=i.hostid';

				if($functionid=='TRIGGER.VALUE'){
					if(!$html) $exp.='{'.$functionid.'}';
					else array_push($exp,'{'.$functionid.'}');
				}
				else if(is_numeric($functionid) && $function_data = DBfetch(DBselect($sql))){
					if($resolve_macro){
						$trigger = $function_data;
						CUserMacro::resolveItem($function_data);

						$function_data['expression'] = $function_data['parameter'];
						CUserMacro::resolveTrigger($function_data);
						$function_data['parameter'] = $function_data['expression'];
					}

					if (!is_null($src_host) && !is_null($dst_host) && strcmp($src_host, $function_data['host']) == 0)
						$function_data['host'] = $dst_host;

//SDII($function_data);
					if(!$html){
						$exp.='{'.$function_data['host'].':'.$function_data['key_'].'.'.$function_data['function'].'('.$function_data['parameter'].')}';
					}
					else{
						$style = ($function_data['status']==ITEM_STATUS_DISABLED)? 'disabled':'unknown';
						if($function_data['status']==ITEM_STATUS_ACTIVE){
							$style = 'enabled';
						}


						$link = new CLink(
									$function_data['host'].':'.$function_data['key_'],
									'items.php?form=update&itemid='.$function_data['itemid'].'&switch_node='.id2nodeid($function_data['itemid']),
									$style
								);

						if($function_data['type'] == ITEM_TYPE_HTTPTEST){
							$link = new CSpan($function_data['host'].':'.$function_data['key_'], $style);
						}

						array_push($exp,array('{',$link,'.',bold($function_data['function'].'('),$function_data['parameter'],bold(')'),'}'));
					}
				}
				else{
					if($html){
						array_push($exp, new CSpan('*ERROR*', 'on'));
					}
					else{
						$exp.= '*ERROR*';
					}
				}
				continue;
			}

			if($state == 'FUNCTIONID'){
				$functionid=$functionid.$expression[$i];
				continue;
			}
			else if($state == 'MACROS'){
				$macros=$macros.$expression[$i];
				continue;
			}

			if($html) array_push($exp,$expression[$i]);
			else $exp.=$expression[$i];
		}
//SDII($exp);
	return $exp;
	}

/******************************************************************************
 *																			*
 * Purpose: Translate {10}>10 to something like localhost:procload.last(0)>10 *
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function triggerExpression($trigger, $html, $template=false, $resolve_macro=false){
		$expression = $trigger['expression'];

//		echo "EXPRESSION:",$expression,"<Br>";
		$functionid='';
		$macros = '';
		if(0 == $html) $exp='';
		else $exp=array();

		$state='';

		for($i=0,$max=zbx_strlen($expression); $i<$max; $i++){
			if(($expression[$i] == '{') && ($expression[$i+1] == '$')){
				$functionid='';
				$macros='';
				$state='MACROS';
			}
			else if($expression[$i] == '{'){
				$functionid='';
				$state='FUNCTIONID';
				continue;
			}

			if($expression[$i] == '}'){
				if($state == 'MACROS'){
					$macros.='}';

					if($resolve_macro){
						$function_data['expression'] = $macros;
						CUserMacro::resolveTrigger($function_data);
						$macros = $function_data['expression'];
					}

					if(1 == $html) array_push($exp,$macros);
					else $exp.=$macros;

					$macros = '';
					$state = '';
					continue;
				}

				$state='';

				if($functionid=='TRIGGER.VALUE'){
					if(0 == $html) $exp.='{'.$functionid.'}';
					else array_push($exp,'{'.$functionid.'}');
				}
				else if(is_numeric($functionid) && isset($trigger['functions'][$functionid])){
					$function_data = $trigger['functions'][$functionid];
					$function_data+= $trigger['items'][$function_data['itemid']];
					$function_data+= $trigger['hosts'][$function_data['hostid']];

					if($template) $function_data['host'] = '{HOSTNAME}';

					if($resolve_macro){
						CUserMacro::resolveItem($function_data);

						$function_data['expression'] = $function_data['parameter'];
						CUserMacro::resolveTrigger($function_data);
						$function_data['parameter'] = $function_data['expression'];
					}

//SDII($function_data);
					if($html == 0){
						$exp.='{'.$function_data['host'].':'.$function_data['key_'].'.'.$function_data['function'].'('.$function_data['parameter'].')}';
					}
					else{
						$style = ($function_data['status']==ITEM_STATUS_DISABLED)? 'disabled':'unknown';
						if($function_data['status']==ITEM_STATUS_ACTIVE){
							$style = 'enabled';
						}


						$link = new CLink(
									$function_data['host'].':'.$function_data['key_'],
									'items.php?form=update&itemid='.$function_data['itemid'],
									$style
								);

						if($function_data['type'] == ITEM_TYPE_HTTPTEST){
							$link = new CSpan($function_data['host'].':'.$function_data['key_'], $style);
						}

						array_push($exp,array('{',$link,'.',bold($function_data['function'].'('),$function_data['parameter'],bold(')'),'}'));
					}
				}
				else{
					if(1 == $html){
						array_push($exp, new CSpan('*ERROR*', 'on'));
					}
					else{
						$exp.= '*ERROR*';
					}
				}
				continue;
			}

			if($state == 'FUNCTIONID'){
				$functionid=$functionid.$expression[$i];
				continue;
			}
			else if($state == 'MACROS'){
				$macros=$macros.$expression[$i];
				continue;
			}

			if(1 == $html) array_push($exp,$expression[$i]);
			else $exp.=$expression[$i];
		}
//SDII($exp);
	return $exp;
	}

/**
 * Implodes expression, replaces names and keys with IDs
 *
 * Fro example: localhost:procload.last(0)>10 will translated to {12}>10 and created database representation.
 *
 * @param string $expression Full expression with host names and item keys
 * @param numeric $triggerid
 *
 * @return string Imploded expression (names and keys replaced by IDs), or false if error occured
 */
function implode_exp($expression, $triggerid) {
	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return null;
	}

	$newFunctions = array();
	$functions = array();
	$items = array();
	$triggerFunctionValidator = new CTriggerFunctionValidator();
	foreach ($expressionData->expressions as $exprPart) {
		if (isset($newFunctions[$exprPart['expression']]))
			continue;

		if (!isset($items[$exprPart['host']][$exprPart['item']])) {
			$result = DBselect(
					'SELECT i.itemid,i.value_type'.
					' FROM items i,hosts h'.
					' WHERE i.key_='.zbx_dbstr($exprPart['item']).
						' AND h.host='.zbx_dbstr($exprPart['host']).
						' AND h.hostid=i.hostid'.
						' AND '.DBin_node('i.itemid')
			);
			if ($row = DBfetch($result)) {
				$items[$exprPart['host']][$exprPart['item']] =
						array('itemid' => $row['itemid'], 'valueType' => $row['value_type']);
			}
			else {
				error('Incorrect item key "'.$exprPart['item'].'" provided for trigger expression on "'.$exprPart['host'].'".');
				return null;
			}
		}

		if (!$triggerFunctionValidator->validate(array('functionName' => $exprPart['functionName'],
				'functionParamList' => $exprPart['functionParamList'],
				'valueType' => $items[$exprPart['host']][$exprPart['item']]['valueType']))) {
			error($triggerFunctionValidator->getError());
			return null;
		}

		$newFunctions[$exprPart['expression']] = 0;

		$functions[] = array(
			'itemid' => $items[$exprPart['host']][$exprPart['item']]['itemid'],
			'triggerid' => $triggerid,
			'function' => $exprPart['functionName'],
			'parameter' => $exprPart['functionParam']
		);
	}

	$functionids = DB::insert('functions', $functions);

	$num = 0;
	foreach ($newFunctions as &$newFunction) {
		$newFunction = $functionids[$num++];
	}
	unset($newFunction);

	$exprPart = end($expressionData->expressions);
	do {
		$expression = substr_replace($expression, '{'.$newFunctions[$exprPart['expression']].'}',
				$exprPart['pos'], strlen($exprPart['expression']));
	}
	while ($exprPart = prev($expressionData->expressions));

	return $expression;
}

	function update_trigger_comments($triggerids,$comments){
		zbx_value2array($triggerids);

		$triggers = CTrigger::get(array(
			'editable' => 1,
			'trigegrids' => $triggerids,
			'output' => API_OUTPUT_SHORTEN,
		));
		$triggers = zbx_toHash($triggers, 'triggerid');
		foreach($triggerids as $triggerid){
			if(!isset($triggers[$triggerid])){
				return false;
			}
		}


		return	DBexecute('UPDATE triggers '.
						' SET comments='.zbx_dbstr($comments).
						' WHERE '.DBcondition('triggerid',$triggerids));
	}

	/*
	 * Function: extract_numbers
	 *
	 * Description:
	 *	 Extract from string numbers with prefixes (A-Z)
	 *
	 * Author:
	 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function extract_numbers($str){
		$numbers = array();
//		while( ereg(ZBX_EREG_NUMBER.'([[:print:]]*)', $str, $arr) ) {
		while(preg_match('/'.ZBX_PREG_NUMBER.'(['.ZBX_PREG_PRINT.']*)/', $str, $arr)){
			$numbers[] = $arr[1];
			$str = $arr[2];
		}

	return $numbers;
	}

	/*
	 * Function: expand_trigger_description_constants
	 *
	 * Description:
	 *	 substitute simple macros in data string with real values
	 *
	 * Author:
	 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *		   replcae $1-9 macros
	 *
	 */
	function expand_trigger_description_constants($description, $row){
		if($row && isset($row['expression'])){
//			$numbers = extract_numbers(ereg_replace('(\{[0-9]+\})', 'function', $row['expression']));
			$numbers = extract_numbers(preg_replace('/(\{[0-9]+\})/', 'function', $row['expression']));

			$description = $row['description'];

			for ( $i = 0; $i < 9; $i++ ){
				$description = str_replace(
									'$'.($i+1),
									isset($numbers[$i])?$numbers[$i]:'',
									$description
								);
			}
		}

		return $description;
	}

	/*
	 * Function: expand_trigger_description_by_data
	 *
	 * Description:
	 *	 substitute simple macros in data string with real values
	 *
	 * Author:
	 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
function expand_trigger_description_by_data($row, $flag = ZBX_FLAG_TRIGGER) {
	if ($row) {
		$description = expand_trigger_description_constants($row['description'], $row);

		for ($i = 0; $i < 10; $i++) {
			$macro = '{HOSTNAME'.($i ? $i : '').'}';
			if (zbx_strstr($description, $macro)) {
				$functionid = trigger_get_N_functionid($row['expression'], $i ? $i : 1);

				if (isset($functionid)) {
					$sql = 'SELECT DISTINCT h.host'.
							' FROM functions f,items i,hosts h'.
							' WHERE f.itemid=i.itemid'.
								' AND i.hostid=h.hostid'.
								' AND f.functionid='.$functionid;
					$host = DBfetch(DBselect($sql));
					if (is_null($host['host'])) {
						$host['host'] = $macro;
					}
					$description = str_replace($macro, $host['host'], $description);
				}
			}

			$itemData = null;
			$macro = '{ITEM.LASTVALUE'.($i ? $i : '').'}';
			if (zbx_strstr($description, $macro)) {
				$functionid = trigger_get_N_functionid($row['expression'], $i ? $i : 1);

				if (isset($functionid)) {
					$sql = 'SELECT i.lastvalue,i.value_type,i.itemid,i.valuemapid,i.units'.
							' FROM items i,functions f'.
							' WHERE i.itemid=f.itemid'.
							' AND f.functionid='.$functionid;
					$itemData = DBfetch(DBselect($sql));
					$description = str_replace($macro, format_lastvalue($itemData), $description);
				}
			}

			$macro = '{ITEM.VALUE'.($i ? $i : '').'}';
			if (zbx_strstr($description, $macro)) {
				$functionid = trigger_get_N_functionid($row['expression'], $i ? $i : 1);
				if (isset($functionid)) {
					// if $itemData is set by resolving {ITEM.LASTVALUE} macro, no need to select data again
					if (!$itemData) {
						$sql = 'SELECT i.value_type,i.itemid,i.valuemapid,i.units'.
								' FROM items i,functions f'.
								' WHERE i.itemid=f.itemid'.
								' AND f.functionid='.$functionid;
						$itemData = DBfetch(DBselect($sql));
					}

					$itemData['lastvalue'] = ($flag == ZBX_FLAG_TRIGGER)
							? trigger_get_func_value($row['expression'], ZBX_FLAG_TRIGGER, $i ? $i : 1, 1)
							: trigger_get_func_value($row['expression'], ZBX_FLAG_EVENT, $i ? $i : 1, $row['clock']);
					$description = str_replace($macro, format_lastvalue($itemData), $description);
				}
			}
		}

		if ($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $description, $arr)) {
			$macros = CUserMacro::getMacros($arr[1], array('triggerid' => $row['triggerid']));

			$search = array_keys($macros);
			$values = array_values($macros);

			$description = str_replace($search, $values, $description);
		}
	}
	else {
		$description = '*ERROR*';
	}
	return $description;
}

	function expand_trigger_description_simple($triggerid){
		$sql = 'SELECT DISTINCT h.host,t.description,t.expression,t.triggerid '.
				' FROM triggers t, functions f, items i, hosts h '.
				' WHERE f.triggerid=t.triggerid '.
					' AND i.itemid=f.itemid '.
					' AND h.hostid=i.hostid '.
					' AND t.triggerid='.$triggerid;
		$trigger = DBfetch(DBselect($sql));

	return expand_trigger_description_by_data($trigger);
	}

	function expand_trigger_description($triggerid){
		$description = expand_trigger_description_simple($triggerid);
		$description = htmlspecialchars($description);
	return $description;
	}

	function update_trigger_value_to_unknown_by_hostid($hostids){
		zbx_value2array($hostids);

		$triggers = array();
		$result = DBselect('SELECT DISTINCT t.triggerid '.
			' FROM items i,triggers t,functions f '.
			' WHERE f.triggerid=t.triggerid '.
				' AND f.itemid=i.itemid '.
				' AND '.DBcondition('i.hostid',$hostids));

		$now = time();
		while($row=DBfetch($result)){
			$triggers[$row['triggerid']] = $row['triggerid'];
		}
		if(!empty($triggers)){
// returns updated triggers
			$triggers = addEvent($triggers,TRIGGER_VALUE_UNKNOWN,$now);
		}

		if(!empty($triggers)){
			DBexecute('UPDATE triggers SET value='.TRIGGER_VALUE_UNKNOWN.', lastchange='.$now.' WHERE '.DBcondition('triggerid',$triggers));
		}
	return true;
	}

	function addEvent($triggerids, $value){
		zbx_value2array($triggerids);

		$events = array();
		foreach($triggerids as $tnum => $triggerid){
			$events[] = array(
				'source'		=> EVENT_SOURCE_TRIGGERS,
				'object'		=> EVENT_OBJECT_TRIGGER,
				'objectid'		=> $triggerid,
				'clock'			=> time(),
				'value'			=> $value,
				'acknowledged'	=> 0
			);
		}
		$eventids = CEvent::create($events);

	return $eventids;
	}

/******************************************************************************
 *																			*
 * Purpose: Delete Trigger definition										 *
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function delete_trigger($triggerids){
		zbx_value2array($triggerids);

// first delete child triggers
		$del_chd_triggers = array();
		$db_triggers= get_triggers_by_templateid($triggerids);
		while($db_trigger = DBfetch($db_triggers)){// recursion
			$del_chd_triggers[$db_trigger['triggerid']] = $db_trigger['triggerid'];
		}

		if(!empty($del_chd_triggers)){
			$result = delete_trigger($del_chd_triggers);
			if(!$result) return  $result;
		}

// get hosts before functions deletion !!!
		$trig_hosts = array();
		foreach($triggerids as $id => $triggerid){
			$trig_hosts[$triggerid] = get_hosts_by_triggerid($triggerid);
		}

		$result = delete_dependencies_by_triggerid($triggerids);
		if(!$result)	return	$result;

		DBexecute('DELETE FROM trigger_depends WHERE '.DBcondition('triggerid_up',$triggerids));

		$result = delete_function_by_triggerid($triggerids);
		if(!$result)	return	$result;

		$result = delete_events_by_triggerid($triggerids);
		if(!$result)	return	$result;

		$result = delete_services_by_triggerid($triggerids);
		if(!$result)	return	$result;

		$result = delete_sysmaps_elements_with_triggerid($triggerids);
		if(!$result)	return	$result;

		DBexecute('DELETE FROM sysmaps_link_triggers WHERE '.DBcondition('triggerid',$triggerids));

// disable actions
		$actionids = array();
		$sql = 'SELECT DISTINCT actionid '.
				' FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_TRIGGER.
					' AND '.DBcondition('value',$triggerids,false,true);   // FIXED[POSIBLE value type violation]!!!
		$db_actions = DBselect($sql);
		while ($db_action = DBfetch($db_actions)) {
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid',$actionids));

// delete action conditions
		DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_TRIGGER.
						' AND '.DBcondition('value',$triggerids,false,true)); // FIXED[POSIBLE value type violation]!!!

// Get triggers INFO before delete them!
		$triggers = array();
		$trig_res = DBselect('SELECT triggerid, description FROM triggers WHERE '.DBcondition('triggerid',$triggerids));
		while ($trig_rows = DBfetch($trig_res)) {
			$triggers[$trig_rows['triggerid']] = $trig_rows;
		}
// --

		$result = DBexecute('DELETE FROM triggers WHERE '.DBcondition('triggerid',$triggerids));
		if ($result) {
			foreach ($triggers as $triggerid => $trigger) {
				$trig_host = DBfetch($trig_hosts[$triggerid]);
				$msg = S_TRIGGER.SPACE.'"'.$trig_host['host'].':'.$trigger['description'].'"'.SPACE.S_DELETED_SMALL;
				info($msg);
			}
		}
		return $result;
	}

// Update Trigger definition

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function update_trigger($triggerid,$expression=NULL,$description=NULL,$type=NULL,$priority=NULL,$status=NULL,$comments=NULL,$url=NULL,$deps=array(),$templateid=0){
		$trigger	= get_trigger_by_triggerid($triggerid);
		$trig_hosts	= get_hosts_by_triggerid($triggerid);
		$trig_host	= DBfetch($trig_hosts);

		$event_to_unknown = false;

		// URL validation against XSS
		if ($url && !validateUrl($url)) {
			error(S_INCORRECT_URL);
			return false;
		}

		// restore expression
		$expressionData = new CTriggerExpression();
		if (is_null($expression)) {
			$expression = explode_exp($trigger['expression']);
			if (!$expressionData->parse($expression)) {
				error($expressionData->error);
				return false;
			}
		}
		else {
			if (!$expressionData->parse($expression)) {
				error($expressionData->error);
				return false;
			}
			$event_to_unknown = ($expression != explode_exp($trigger['expression']));
		}

		if(!is_null($deps) && !validate_trigger_dependency($expression, $deps)) {
			error(S_WRONG_DEPENDENCY_ERROR);
			return false;
		}

		if (!validate_trigger_expression($expressionData)) {
			return false;
		}

		if(is_null($description)){
			$description = $trigger['description'];
		}

		$expressionHosts = $expressionData->getHosts();

		if(CTrigger::exists(array('description' => $description, 'expression' => $expression))){

			$host = reset($expressionHosts);
			$options = array(
				'filter' => array('description' => $description, 'host' => $host),
				'output' => API_OUTPUT_EXTEND,
				'editable' => 1,
			);
			$triggers_exist = CTrigger::get($options);

			$trigger_exist = false;
			foreach($triggers_exist as $tnum => $tr){
				$tmp_exp = explode_exp($tr['expression']);
				if(strcmp($tmp_exp, $expression) == 0){
					$trigger_exist = $tr;
					break;
				}
			}
			if($trigger_exist && ($trigger_exist['triggerid'] != $trigger['triggerid'])){
				error('Trigger with name "'.$trigger['description'].'" and expression "'.$expression.'" already exists.');
				return false;
			}
			else if(!$trigger_exist){
				error('No Permissions');
				return false;
			}
		}

		if(!empty($expressionHosts)){
			$chd_hosts	= get_hosts_by_templateid($trig_host['hostid']);

			if(DBfetch($chd_hosts)){
				$expHostName = reset($expressionHosts);

				$db_chd_triggers = get_triggers_by_templateid($triggerid);
				while($db_chd_trigger = DBfetch($db_chd_triggers)){
					$chd_trig_hosts = get_hosts_by_triggerid($db_chd_trigger['triggerid']);
					$chd_trig_host = DBfetch($chd_trig_hosts);

					$newexpression = str_replace(
						'{'.$expHostName.':',
						'{'.$chd_trig_host['host'].':',
						$expression);

// recursion
					update_trigger(
						$db_chd_trigger['triggerid'],
						$newexpression,
						$description,
						$type,
						$priority,
						$status,
						$comments,
						$url,
						(is_null($deps) ? null : replace_template_dependencies($deps, $chd_trig_host['hostid'])),
						$triggerid
					);
				}
			}
		}

		$result = delete_function_by_triggerid($triggerid);

		if(!$result){
			return	$result;
		}

		$expression = implode_exp($expression,$triggerid);
		if(is_null($expression)){
			return false;
		}

		$update_values = array();
		if(!is_null($expression)) $update_values['expression'] = $expression;
		if(!is_null($description)) $update_values['description'] = $description;
		if(!is_null($type)) $update_values['type'] = $type;
		if(!is_null($priority)) $update_values['priority'] = $priority;
		if(!is_null($status)) $update_values['status'] = $status;
		if(!is_null($comments)) $update_values['comments'] = $comments;
		if(!is_null($url)) $update_values['url'] = $url;
		if(!is_null($templateid)) $update_values['templateid'] = $templateid;

		if($event_to_unknown || (!is_null($status) && ($status != TRIGGER_STATUS_ENABLED))){
			if($trigger['value'] != TRIGGER_VALUE_UNKNOWN){
				addEvent($triggerid, TRIGGER_VALUE_UNKNOWN);

				$update_values['value'] = TRIGGER_VALUE_UNKNOWN;
				$update_values['lastchange'] = time();
			}
		}

		DB::update('triggers', array('values' => $update_values, 'where' => array('triggerid='.$triggerid)));

		if (!is_null($deps)) {
			delete_dependencies_by_triggerid($triggerid);

			foreach ($deps as $id => $triggerid_up) {
				if (!$result2 = add_trigger_dependency($triggerid, $triggerid_up)) {
					error(S_INCORRECT_DEPENDENCY.' ['.expand_trigger_description($triggerid_up).']');
				}
				$result &= $result2;
			}
		}

		if ($result) {
			$trig_hosts	= get_hosts_by_triggerid($triggerid);
			$trig_host = DBfetch($trig_hosts);
			$msg = S_TRIGGER.SPACE.'"'.$trig_host['host'].':'.$trigger['description'].'"'.SPACE.S_UPDATED_SMALL;
			info($msg);
		}

		if ($result) {
			$trigger_new = get_trigger_by_triggerid($triggerid);
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER,	$triggerid,	$trig_host['host'].':'.$trigger['description'], 'triggers', $trigger, $trigger_new);
		}

		$result = $result?$triggerid:$result;

		return $result;
	}

function check_right_on_trigger_by_expression($permission,$expression){
	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}
	$expressionHosts = $expressionData->getHosts();

	$hosts = CHost::get(array(
		'filter' => array('host' => $expressionHosts),
		'editable' => (($permission == PERM_READ_WRITE) ? 1 : null),
		'output' => array('hostid', 'host'),
		'templated_hosts' => 1,
		'preservekeys' => 1
	));

	$hosts = zbx_toHash($hosts, 'host');

	foreach ($expressionHosts as $host) {
		if (!isset($hosts[$host])) {
			error('Incorrect trigger expression. Host "'.$host.'" does not exist or you have no access to this host.');
			return false;
		}
	}

	return true;
}

// ----------- DEPENDENCIES --------------

/******************************************************************************
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function get_trigger_dependencies_by_triggerid($triggerid){
		$result = array();

		$db_deps = DBselect('SELECT * FROM trigger_depends WHERE triggerid_down='.$triggerid);
		while($db_dep = DBfetch($db_deps))
			$result[] = $db_dep['triggerid_up'];

	return $result;
	}

	/**
	 * Adds a dependency from $triggerId to $depTriggerId and inherit it to all child triggers.
	 *
	 * @param $triggerId
	 * @param $depTriggerId
	 *
	 * @return bool
	 */
	function add_trigger_dependency($triggerId, $depTriggerId) {
		if (check_dependency_by_triggerid($triggerId, $depTriggerId)) {
			// save the dependency for the current trigger
			$result = insert_dependency($triggerId, $depTriggerId);
			if (!$result) {
				return false;
			}

			// fetch all child triggers
			$childTriggers = array();
			$childTriggerQuery = get_triggers_by_templateid($triggerId);
			while ($childTrigger = DBfetch($childTriggerQuery)) {
				$childTriggers[$childTrigger['triggerid']] = $childTrigger;
			}

			// propagate the dependency to the child triggers
			if ($childTriggers) {
				$childHosts = CHost::get(array(
					'output' => array('hostid', 'status'),
					'triggerids' => array_keys($childTriggers),
					'templated_hosts' => true,
					'nopermissions' => true
				));
				foreach ($childHosts as $childHost) {
					$childTrigger = reset($childHost['triggers']);

					$childDep = array($childTrigger['triggerid'] => $depTriggerId);
					$childDep = replace_template_dependencies($childDep, $childHost['hostid']);

					// if the child host is a template, propagate the dependency to the children
					if($childHost['status'] == HOST_STATUS_TEMPLATE) {
						$result = add_trigger_dependency($childTrigger['triggerid'], $childDep[$childTrigger['triggerid']]);
					}
					// if the child host is not a template, just save the dependency
					else {
						$result = insert_dependency($childTrigger['triggerid'], $childDep[$childTrigger['triggerid']]);
					}
					if (!$result) {
						return false;
					}
				}
			}
		}

		return true;
	}

/******************************************************************************
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/

	/**
	 * Adds the dependency from $triggerid_down to $triggerid_up, does not propagate the dependency to the
	 * child triggers.
	 *
	 * @see add_trigger_dependency() for a way to a add a dependency with inheritance support
	 *
	 * @param $triggerid_down
	 * @param $triggerid_up
	 *
	 * @return bool
	 */
	function insert_dependency($triggerid_down, $triggerid_up) {
		$triggerdepid = get_dbid('trigger_depends', 'triggerdepid');
		return DBexecute('INSERT INTO trigger_depends (triggerdepid,triggerid_down,triggerid_up)'.
				' VALUES ('.$triggerdepid.','.$triggerid_down.','.$triggerid_up.')');
	}

	function replace_triggers_depenedencies($new_triggerids){
		$old_triggerids = array_keys($new_triggerids);

		$deps = array();
		$res = DBselect('SELECT * FROM trigger_depends WHERE '.DBcondition('triggerid_up',$old_triggerids));
		while($db_dep = DBfetch($res)){
			$deps[$db_dep['triggerid_up']] = $db_dep['triggerid_down'];
		}

		delete_dependencies_by_triggerid($deps);

		foreach($new_triggerids as $old_triggerid => $newtriggerid){
			if(isset($deps[$old_triggerid]))
				insert_dependency($deps[$old_triggerid], $newtriggerid);
		}
	}

/******************************************************************************
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function replace_template_dependencies($deps, $hostid){
		foreach($deps as $id => $val){
			$sql = 'SELECT t.triggerid '.
				' FROM triggers t,functions f,items i '.
				' WHERE t.templateid='.$val.
					' AND f.triggerid=t.triggerid '.
					' AND f.itemid=i.itemid '.
					' AND i.hostid='.$hostid;
			if($db_new_dep = DBfetch(DBselect($sql))){
				$deps[$id] = $db_new_dep['triggerid'];
			}
		}

	return $deps;
	}

/******************************************************************************
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function delete_dependencies_by_triggerid($triggerids){
		zbx_value2array($triggerids);

		$db_deps = DBselect('SELECT triggerid_up, triggerid_down '.
						' FROM trigger_depends '.
						' WHERE '.DBcondition('triggerid_down',$triggerids));

		while($db_dep = DBfetch($db_deps)){
			DBexecute('DELETE FROM trigger_depends'.
				' WHERE triggerid_up='.$db_dep['triggerid_up'].
					' AND triggerid_down='.$db_dep['triggerid_down']);
		}
	return true;
	}

	function check_dependency_by_triggerid($triggerid,$triggerid_up,$level=0){
		if(bccomp($triggerid,$triggerid_up) == 0) return false;
		if($level > 16) return true;

		$level++;
		$result = true;

		$sql = 'SELECT triggerid_up FROM trigger_depends WHERE triggerid_down='.$triggerid_up;
		$res = DBselect($sql);
		while(($trig = DBfetch($res)) && $result){
			$result &= check_dependency_by_triggerid($triggerid,$trig['triggerid_up'],$level);		// RECURSION!!!
		}

	return $result;
	}

// Deny adding dependency between templates ifthey are not high level templates
	function validate_trigger_dependency($expression, $deps) {
		$result = true;

		//if we have atleast one dependency
		if(!empty($deps)){
			$templates = array();
			$templateids = array();
			$templated_trigger = false;

			$expressionData = new CTriggerExpression();
			if (!$expressionData->parse($expression)) {
				error($expressionData->error);
				return false;
			}
			$hosts = CHost::get(array(
				'templated_hosts' => true,
				'filter' => array('host' => $expressionData->getHosts()),
				'output' => array('host', 'status')
			));
			foreach($hosts as $hnum => $triggerhost){
				if($triggerhost['status'] == HOST_STATUS_TEMPLATE){
					$templates[$triggerhost['hostid']] = $triggerhost;
					$templateids[$triggerhost['hostid']] = $triggerhost['hostid'];
					$templated_trigger = true;
				}
			}

			$dep_templateids = array();
			$db_dephosts = get_hosts_by_triggerid($deps);
			while($dephost = DBfetch($db_dephosts)) {
				if($templated_dep = ($dephost['status'] == HOST_STATUS_TEMPLATE)){
					$templates[$dephost['hostid']] = $dephost;
					$dep_templateids[$dephost['hostid']] = $dephost['hostid'];
				}

				//we have a host trigger added to template trigger or otherwise
				if($templated_trigger != $templated_dep){
					return false;
				}
			}


			$tdiff = array_diff($dep_templateids, $templateids);
			if(!empty($templateids) && !empty($dep_templateids) && !empty($tdiff)){
				$tpls = zbx_array_merge($templateids, $dep_templateids);
				$sql = 'SELECT DISTINCT ht.templateid, ht.hostid, h.host'.
						' FROM hosts_templates ht, hosts h'.
						' WHERE h.hostid=ht.hostid'.
							' AND '.DBcondition('ht.templateid', $tpls);

				$db_lowlvltpl = DBselect($sql);
				$map = array();
				while($lovlvltpl = DBfetch($db_lowlvltpl)){
					if(!isset($map[$lovlvltpl['hostid']])) $map[$lovlvltpl['hostid']] = array();
					$map[$lovlvltpl['hostid']][$lovlvltpl['templateid']] = $lovlvltpl['host'];
				}

				foreach($map as $hostid => $templates){
					$set_with_dep = false;

					foreach($templateids as $tplid){
						if(isset($templates[$tplid])){
							$set_with_dep = true;
							break;
						}
					}
					foreach($dep_templateids as $dep_tplid){
						if(!isset($templates[$dep_tplid]) && $set_with_dep){
							error('Not all Templates are linked to host [ '.reset($templates).' ]');
							$result = false;
							break 2;
						}
					}
				}

			}
		}

	return $result;
	}

	function delete_function_by_triggerid($triggerids){
		zbx_value2array($triggerids);
	return	DBexecute('DELETE FROM functions WHERE '.DBcondition('triggerid',$triggerids));
	}

	function delete_events_by_triggerid($triggerids){
		zbx_value2array($triggerids);
	return	DBexecute('DELETE FROM events WHERE '.DBcondition('objectid',$triggerids).' AND object='.EVENT_OBJECT_TRIGGER);
	}

/******************************************************************************
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function delete_triggers_by_itemid($itemids){
		zbx_value2array($itemids);

		$del_triggers = array();
		$result=DBselect('SELECT triggerid FROM functions WHERE '.DBcondition('itemid',$itemids));
		while($row=DBfetch($result)){
			$del_triggers[$row['triggerid']] = $row['triggerid'];
		}
		if(!empty($del_triggers)){
			if(!delete_trigger($del_triggers)) return FALSE;
		}

	return TRUE;
	}

/******************************************************************************
 *																			*
 * Purpose: Delete Service definitions by triggerid						   *
 *																			*
 * Comments: !!! Don't forget sync code with C !!!							*
 *																			*
 ******************************************************************************/
	function delete_services_by_triggerid($triggerids){
		zbx_value2array($triggerids);

		$result = DBselect('SELECT serviceid FROM services WHERE '.DBcondition('triggerid',$triggerids));
		while($row = DBfetch($result)){
			delete_service($row['serviceid']);
		}
	return	TRUE;
	}

/*
 * Function: cmp_triggers_exressions
 *
 * Description:
 * 		Warning: function compares ONLY expressions, there is no check on functions and items
 *
 * Author:
 *	 Aly
 *
 * Comments:
 *
 */
	function cmp_triggers_exressions($expr1, $expr2){
		$expr1 = preg_replace('/{[0-9]+}/', 'func', $expr1);
		$expr2 = preg_replace('/{[0-9]+}/', 'func', $expr2);
		return strcmp($expr1, $expr2);
	}

	/*
	 * Function: cmp_triggers
	 *
	 * Description:
	 *	 compare triggers by expression
	 *
	 * Author:
	 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function cmp_triggers($triggerid1, $triggerid2){
// compare EXPRESSION !!!
		$trig1 = get_trigger_by_triggerid($triggerid1);
		$trig2 = get_trigger_by_triggerid($triggerid2);

		$trig_fnc1 = get_functions_by_triggerid($triggerid1);

		$expr1 = $trig1['expression'];
		while($fnc1 = DBfetch($trig_fnc1)){
			$trig_fnc2 = get_functions_by_triggerid($triggerid2);
			while($fnc2 = DBfetch($trig_fnc2)){
				if(strcmp($fnc1['function'],$fnc2['function']))	continue;
				if($fnc1['parameter'] != $fnc2['parameter'])	continue;

				$item1 = get_item_by_itemid($fnc1['itemid']);
				$item2 = get_item_by_itemid($fnc2['itemid']);

				if(strcmp($item1['key_'],$item2['key_']))	continue;

				$expr1 = str_replace(
					'{'.$fnc1['functionid'].'}',
					'{'.$fnc2['functionid'].'}',
					$expr1);
				break;
			}
		}
		return strcmp($expr1,$trig2['expression']);
	}

	/*
	 * Function: delete_template_triggers
	 *
	 * Description:
	 *	 Delete template triggers
	 *
	 * Author:
	 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function delete_template_triggers($hostid, $templateids = null, $unlink_mode = false){
		zbx_value2array($templateids);

		$triggers = get_triggers_by_hostid($hostid);

		$host = get_host_by_hostid($hostid);

		while ($trigger = DBfetch($triggers)) {
			if ($trigger['templateid']==0) {
				continue;
			}

			if ($templateids != null) {
				$db_tmp_hosts = get_hosts_by_triggerid($trigger['templateid']);
				$tmp_host = DBfetch($db_tmp_hosts);

				if (!uint_in_array($tmp_host['hostid'], $templateids)) {
					continue;
				}
			}

			if ($unlink_mode) {
				if (DBexecute('UPDATE triggers SET templateid=0 WHERE triggerid='.$trigger['triggerid'])) {
					info(sprintf(S_TRIGGER_UNLINKED, $host['host'].':'.$trigger['description']));
				}
			}
			else {
				delete_trigger($trigger['triggerid']);
			}
		}
		return TRUE;
	}

/**
 * Copy triggers from template.
 *
 * @param $hostid
 * @param $templateid
 * @param bool $copy_mode
 *
 * @return array            An map of new trigger IDs in the form of array('oldTriggerId' => 'newTriggerId')
 */
function copy_template_triggers($hostid, $templateid, $copy_mode = false) {
	$triggers = get_triggers_by_hostid($templateid);
	$triggerArray = array();
	while ($trigger = DBfetch($triggers)) {
		$triggerArray[] = $trigger;
	}
	$newId = array();
	foreach ($triggerArray as $triggerData) {
		$newId[$triggerData['triggerid']] = copy_trigger_to_host($triggerData['triggerid'], $hostid, $copy_mode);
	}

	return $newId;
}

/*
 * Function: get_triggers_overview
 *
 * Description:
 *	 Retrieve table with overview of triggers
 *
 * Author:
 *	 Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function get_triggers_overview($hostids,$view_style=null){
		global $USER_DETAILS;

		if(is_null($view_style)) $view_style = CProfile::get('web.overview.view.style',STYLE_TOP);

		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);

		$options = array(
			'hostids' => $hostids,
			'monitored' => 1,
			'expandData' => 1,
			'skipDependent' => 1,
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => 'description'
		);

		$db_triggers = CTrigger::get($options);

		unset($triggers);
		unset($hosts);

		$triggers = array();

		foreach($db_triggers as $tnum => $row){
			$row['host'] = get_node_name_by_elid($row['hostid'], null, ': ').$row['host'];
			$row['description'] = expand_trigger_description_constants($row['description'], $row);

			$hosts[zbx_strtolower($row['host'])] = $row['host'];

			// A little tricky check for attempt to overwrite active trigger (value=1) with
			// inactive or active trigger with lower priority.
			if(!isset($triggers[$row['description']][$row['host']]) ||
				(
					(($triggers[$row['description']][$row['host']]['value'] == TRIGGER_VALUE_FALSE) && ($row['value'] == TRIGGER_VALUE_TRUE)) ||
					(
						(($triggers[$row['description']][$row['host']]['value'] == TRIGGER_VALUE_FALSE) || ($row['value'] == TRIGGER_VALUE_TRUE)) &&
						($row['priority'] > $triggers[$row['description']][$row['host']]['priority'])
					)
				)
			)
			{
				$triggers[$row['description']][$row['host']] = array(
					'hostid'	=> $row['hostid'],
					'triggerid'	=> $row['triggerid'],
					'value'		=> $row['value'],
					'lastchange'=> $row['lastchange'],
					'priority'	=> $row['priority']);
			}
		}

		if(!isset($hosts)){
			return $table;
		}
		ksort($hosts);


		$css = getUserTheme($USER_DETAILS);
		$vTextColor = ($css == 'css_od.css')?'&color=white':'';

		if($view_style == STYLE_TOP){
			$header = array(new CCol(S_TRIGGERS,'center'));

			foreach($hosts as $hostname){
				$header = array_merge($header,array(new CCol(array(new CImg('vtext.php?text='.urlencode($hostname).$vTextColor)), 'hosts')));
			}
			$table->setHeader($header,'vertical_header');

			foreach($triggers as $descr => $trhosts){
				$table_row = array(nbsp($descr));
				foreach($hosts as $hostname){
					$table_row = get_trigger_overview_cells($table_row,$trhosts,$hostname);
				}
				$table->addRow($table_row);
			}
		}
		else{
			$header=array(new CCol(S_HOSTS,'center'));
			foreach($triggers as $descr => $trhosts){
				$descr = array(new CImg('vtext.php?text='.urlencode($descr).$vTextColor));
				array_push($header,$descr);
			}
			$table->setHeader($header,'vertical_header');

			foreach($hosts as $hostname){
				$table_row = array(nbsp($hostname));
				foreach($triggers as $descr => $trhosts){
					$table_row=get_trigger_overview_cells($table_row,$trhosts,$hostname);
				}
				$table->addRow($table_row);
			}
		}
	return $table;
	}

	function get_trigger_overview_cells(&$table_row,&$trhosts,&$hostname){
		$css_class = NULL;
		$config = select_config();

		unset($tr_ov_menu);
		$ack = null;
		if(isset($trhosts[$hostname])){
			unset($ack_menu);

			switch($trhosts[$hostname]['value']){
				case TRIGGER_VALUE_TRUE:
					$css_class = get_severity_style($trhosts[$hostname]['priority']);
					$ack = null;

					if($config['event_ack_enable'] == 1){
						$event = get_last_event_by_triggerid($trhosts[$hostname]['triggerid']);
						if($event){
							$ack_menu = array(
											S_ACKNOWLEDGE,
											'acknow.php?eventid='.$event['eventid'].'&backurl=overview.php',
											array('tw'=>'_blank')
										);

							if(1 == $event['acknowledged'])
								$ack = new CImg('images/general/tick.png','ack');
						}
					}
					break;
				case TRIGGER_VALUE_FALSE:
					$css_class = 'normal';
					break;
				default:
					$css_class = 'unknown_trigger';
			}

			$style = 'cursor: pointer; ';

			if((time()-$trhosts[$hostname]['lastchange'])<300)
				$style .= 'background-image: url(images/gradients/blink1.gif); '.
					'background-position: top left; '.
					'background-repeat: repeat;';
			else if((time()-$trhosts[$hostname]['lastchange'])<900)
				$style .= 'background-image: url(images/gradients/blink2.gif); '.
					'background-position: top left; '.
					'background-repeat: repeat;';

			unset($item_menu);
			$tr_ov_menu = array(
// name, url, (target [tw], statusbar [sb]), css, submenu
				array(S_TRIGGER, null,  null,
					array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
					),
				array(S_EVENTS, 'events.php?triggerid='.$trhosts[$hostname]['triggerid'], array('tw'=>'_blank'))
				);

			if(isset($ack_menu)) $tr_ov_menu[] = $ack_menu;

			$sql = 'SELECT DISTINCT i.itemid, i.description, i.key_, i.value_type '.
					' FROM items i, functions f '.
					' WHERE f.itemid=i.itemid '.
						' AND f.triggerid='.$trhosts[$hostname]['triggerid'];
			$db_items = DBselect($sql);
			while($item_data = DBfetch($db_items)){
				$description = item_description($item_data);
				switch($item_data['value_type']){
					case ITEM_VALUE_TYPE_UINT64:
					case ITEM_VALUE_TYPE_FLOAT:
						$action = 'showgraph';
						$status_bar = S_SHOW_GRAPH_OF_ITEM.' \''.$description.'\'';
						break;
					case ITEM_VALUE_TYPE_LOG:
					case ITEM_VALUE_TYPE_STR:
					case ITEM_VALUE_TYPE_TEXT:
					default:
						$action = 'showlatest';
						$status_bar = S_SHOW_VALUES_OF_ITEM.' \''.$description.'\'';
						break;
				}

				if(zbx_strlen($description) > 25) $description = zbx_substr($description,0,22).'...';

				$item_menu[$action][] = array(
					$description,
					'history.php?action='.$action.'&itemid='.$item_data['itemid'].'&period=3600',
					 array('tw'=>'', 'sb'=>$status_bar));
			}

			if(isset($item_menu['showgraph'])){
				$tr_ov_menu[] = array(S_GRAPHS,	null, null,
					array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
					);
				$tr_ov_menu = array_merge($tr_ov_menu, $item_menu['showgraph']);
			}

			if(isset($item_menu['showlatest'])){
				$tr_ov_menu[] = array(S_VALUES,	null, null,
					array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
					);
				$tr_ov_menu = array_merge($tr_ov_menu, $item_menu['showlatest']);
			}

			unset($item_menu);
		}
// dependency
// TRIGGERS ON WHICH DEPENDS THIS
		$desc = array();
		if(isset($trhosts[$hostname])){

			$triggerid = $trhosts[$hostname]['triggerid'];

			$dependency = false;
			$dep_table = new CTableInfo();
			$dep_table->setAttribute('style', 'width: 200px;');
			$dep_table->addRow(bold(S_DEPENDS_ON.':'));

			$sql_dep = 'SELECT * FROM trigger_depends WHERE triggerid_down='.$triggerid;
			$dep_res = DBselect($sql_dep);
			while($dep_row = DBfetch($dep_res)){
				$dep_table->addRow(SPACE.'-'.SPACE.expand_trigger_description($dep_row['triggerid_up']));
				$dependency = true;
			}

			if($dependency){
				$img = new Cimg('images/general/down_icon.png','DEP_DOWN');
				$img->setAttribute('style','vertical-align: middle; border: 0px;');
				$img->SetHint($dep_table);

				array_push($desc,$img);
			}
			unset($img, $dep_table, $dependency);

// TRIGGERS THAT DEPEND ON THIS
			$dependency = false;
			$dep_table = new CTableInfo();
			$dep_table->setAttribute('style', 'width: 200px;');
			$dep_table->addRow(bold(S_DEPENDENT.':'));

			$sql_dep = 'SELECT * FROM trigger_depends WHERE triggerid_up='.$triggerid;
			$dep_res = DBselect($sql_dep);
			while($dep_row = DBfetch($dep_res)){
				$dep_table->addRow(SPACE.'-'.SPACE.expand_trigger_description($dep_row['triggerid_down']));
				$dependency = true;
			}

			if($dependency){
				$img = new Cimg('images/general/up_icon.png','DEP_UP');
				$img->setAttribute('style','vertical-align: middle; border: 0px;');
				$img->SetHint($dep_table);

				array_push($desc,$img);
			}
			unset($img, $dep_table, $dependency);
		}
//------------------------
		//SDII($desc);
		//SDII($ack);
		if((is_array($desc) && count($desc) > 0) || $ack) {
			$status_col = new CCol(array($desc, $ack),$css_class.' hosts');
		} else {
			$status_col = new CCol(SPACE,$css_class.' hosts');
		}
		if(isset($style)){
			$status_col->setAttribute('style', $style);
		}

		if(isset($tr_ov_menu)){
			$tr_ov_menu  = new CPUMenu($tr_ov_menu,170);
			$status_col->OnClick($tr_ov_menu->GetOnActionJS());
			$status_col->addAction('onmouseover', 'this.style.border=\'1px dotted #0C0CF0\'');
			$status_col->addAction('onmouseout', 'this.style.border = \'\';');
		}
		array_push($table_row,$status_col);

	return $table_row;
	}

	function calculate_availability($triggerid,$period_start,$period_end){
		$start_value = -1;

		if(($period_start>0) && ($period_start <= time())){
			$sql='SELECT e.eventid, e.value '.
					' FROM events e '.
					' WHERE e.objectid='.$triggerid.
						' AND e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.clock<'.$period_start.
					' ORDER BY e.eventid DESC';
			if($row = DBfetch(DBselect($sql,1))){
				$start_value = $row['value'];
				$min = $period_start;
			}
		}

		$sql='SELECT COUNT(*) as cnt, MIN(clock) as minn, MAX(clock) as maxx '.
				' FROM events '.
				' WHERE objectid='.$triggerid.
					' AND object='.EVENT_OBJECT_TRIGGER;

		if($period_start!=0)	$sql .= ' AND clock>='.$period_start;
		if($period_end!=0)		$sql .= ' AND clock<='.$period_end;
//SDI($sql);

		$row=DBfetch(DBselect($sql));
		if($row['cnt']>0){
			if(!isset($min)) $min=$row['minn'];

			$max=$row['maxx'];
		}
		else{
			if(($period_start==0)&&($period_end==0)){
				$max=time();
				$min=$max-24*3600;
			}
			else{
				$ret['true_time']		= 0;
				$ret['false_time']		= 0;
				$ret['unknown_time']	= 0;
				$ret['true']		= (TRIGGER_VALUE_TRUE == $start_value) ? 100 : 0;
				$ret['false']		= (TRIGGER_VALUE_FALSE == $start_value)? 100 : 0;
				$ret['unknown']		= (TRIGGER_VALUE_UNKNOWN == $start_value || -1 == $start_value) ? 100 : 0;
				return $ret;
			}
		}

		$state		= $start_value;//-1;
		$true_time	= 0;
		$false_time	= 0;
		$unknown_time	= 0;
		$time		= $min;
		if(($period_start==0)&&($period_end==0)){
			$max = time();
		}
		if($period_end == 0){
			$period_end = $max;
		}

		$rows=0;
		$sql = 'SELECT eventid,clock,value '.
				' FROM events '.
				' WHERE objectid='.$triggerid.
					' AND object='.EVENT_OBJECT_TRIGGER.
					' AND clock>='.$min.
					' AND clock<='.$max.
				' ORDER BY clock ASC, eventid ASC';
		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$clock=$row['clock'];
			$value=$row['value'];

			$diff=$clock-$time;
//if($diff < 0) SDI($row);
			$time=$clock;

			if($state==-1){
				$state=$value;
				if($state == 0) $false_time+=$diff;
				if($state == 1)	$true_time+=$diff;
				if($state == 2)	$unknown_time+=$diff;
			}
			else if($state==0){
				$false_time+=$diff;
				$state=$value;
			}
			else if($state==1){
				$true_time+=$diff;
				$state=$value;
			}
			else if($state==2){
				$unknown_time+=$diff;
				$state=$value;
			}
			$rows++;
		}

		if($rows==0){
			$trigger = get_trigger_by_triggerid($triggerid);
			$state = $trigger['value'];
		}

		if($state==TRIGGER_VALUE_FALSE) $false_time=$false_time+$period_end-$time;
		else if($state==TRIGGER_VALUE_TRUE) $true_time=$true_time+$period_end-$time;
		else if($state==TRIGGER_VALUE_UNKNOWN) $unknown_time=$unknown_time+$period_end-$time;
		$total_time=$true_time+$false_time+$unknown_time;

		if($total_time == 0){
			$ret['true_time']	= 0;
			$ret['false_time']	= 0;
			$ret['unknown_time']	= 0;
			$ret['true']		= 0;
			$ret['false']		= 0;
			$ret['unknown']		= 100;
		}
		else{
			$ret['true_time']	= $true_time;
			$ret['false_time']	= $false_time;
			$ret['unknown_time']	= $unknown_time;
			$ret['true']		= (100*$true_time)/$total_time;
			$ret['false']		= (100*$false_time)/$total_time;
			$ret['unknown']		= (100*$unknown_time)/$total_time;
		}

	return $ret;
	}

/*
 * Function: trigger_depenent_rec
 *
 * Description:
 *	 check iftrigger depends on other triggers having status TRUE
 *
 * Author:
 *	 Alexei Vladishev
 *
 * Comments: Recursive function
 *
 */
	function trigger_dependent_rec($triggerid,&$level){
		$ret = FALSE;

		$level++;

		/* Check for recursive loop */
		if($level > 32)	return $ret;

		$sql = 'SELECT t.triggerid, t.value '.
				' FROM trigger_depends d, triggers t '.
				' WHERE d.triggerid_down='.$triggerid.
					' AND d.triggerid_up=t.triggerid';

		$result = DBselect($sql);
		while($row = DBfetch($result)){
			if(TRIGGER_VALUE_TRUE == $row['value'] || trigger_dependent_rec($row['triggerid'], $level)){
				$ret = TRUE;
				break;
			}
		}

	return $ret;
	}

/*
 * Function: trigger_depenent
 *
 * Description:
 *	 check iftrigger depends on other triggers having status TRUE
 *
 * Author:
 *	 Alexei Vladishev
 *
 * Comments:
 *
 */
	function trigger_dependent($triggerid){
		$level = 0;
		return trigger_dependent_rec($triggerid, $level);
	}

/*
 * Function: trigger_get_N_functionid
 *
 * Description:
 *	 get functionid of Nth function of trigger expression
 *
 * Author:
 *	 Alexei Vladishev
 *
 * Comments:
 *
 */
	function trigger_get_N_functionid($expression, $function){
		$result = NULL;

//		$arr=split('[\{\}]',$expression);
		$arr = preg_split('/[\{\}]/', $expression);
		$num = 1;
		foreach($arr as $id){
			if(is_numeric($id)){
				if($num == $function){
					$result = $id;
					break;
				}
				$num++;
			}
		}

	return $result;
	}

	/*
	 * Function: trigger_get_func_value
	 *
	 * Description:
	 *	 get historical value of Nth function of trigger expression
	 *	 flag:  ZBX_FLAG_EVENT - get value by clock, ZBX_FLAG_TRIGGR - get value by index
	 *	 ZBX_FLAG_TRIGGER, param: 0 - last value, 1 - prev, 2 - prev prev, etc
	 *	 ZBX_FLAG_EVENT, param: event timestamp
	 *
	 * Author:
	 *	 Alexei Vladishev
	 *
	 * Comments:
	 *
	 */
	function trigger_get_func_value($expression, $flag, $function, $param){
		$result = NULL;

		$functionid=trigger_get_N_functionid($expression,$function);
		if(isset($functionid)){
			$row=DBfetch(DBselect('select i.* from items i, functions f '.
				' where i.itemid=f.itemid and f.functionid='.$functionid));
			if($row)
			{
				$result=($flag == ZBX_FLAG_TRIGGER)?
					item_get_history($row, $param):
					item_get_history($row, 0, $param);
			}
		}
		return $result;
	}

	function get_triggers_unacknowledged($db_element, $count_problems=null, $ack=false){
		$elements = array('hosts' => array(), 'hosts_groups' => array(), 'triggers' => array());

		get_map_elements($db_element, $elements);
		if(empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])){
			return 0;
		}

		$config = select_config();
		$options = array(
			'nodeids' => get_current_nodeid(),
			'monitored' => 1,
			'countOutput' => 1,
			'filter' => array(),
			'limit' => ($config['search_limit']+1)
		);
		if($ack) $options['withAcknowledgedEvents'] = 1;
		else $options['withUnacknowledgedEvents'] = 1;
		if($count_problems) $options['filter']['value'] = TRIGGER_VALUE_TRUE;
		if(!empty($elements['hosts_groups'])) $options['groupids'] = array_unique($elements['hosts_groups']);
		if(!empty($elements['hosts'])) $options['hostids'] = array_unique($elements['hosts']);
		if(!empty($elements['triggers'])) $options['triggerids'] = array_unique($elements['triggers']);
		$triggers = CTrigger::get($options);


	return $triggers;
	}

// author: Aly
	function make_trigger_details($triggerid,&$trigger_data){
		$table = new CTableInfo();

		if(is_show_all_nodes()){
			$table->addRow(array(S_NODE, get_node_name_by_elid($triggerid)));
		}

		$table->addRow(array(S_HOST, $trigger_data['host']));
		$table->addRow(array(S_TRIGGER, $trigger_data['exp_desc']));
		$table->addRow(array(S_SEVERITY, new CCol(get_severity_description($trigger_data['priority']), get_severity_style($trigger_data['priority']))));
		$table->addRow(array(S_EXPRESSION, $trigger_data['exp_expr']));
		$table->addRow(array(S_EVENT_GENERATION, S_NORMAL.((TRIGGER_MULT_EVENT_ENABLED==$trigger_data['type'])?SPACE.'+'.SPACE.S_MULTIPLE_PROBLEM_EVENTS:'')));
		$table->addRow(array(S_DISABLED, ((TRIGGER_STATUS_ENABLED==$trigger_data['status'])?new CCol(S_NO,'off'):new CCol(S_YES,'on')) ));

	return $table;
	}

/**
 * Analyze an expression and returns expression html tree
 *
 * @param string $expression
 *
 * @return array
 */
function analyzeExpression($expression) {
	if (empty($expression)) {
		return array('', null);
	}

	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}

	$expressionTree[] = getExpressionTree($expressionData, 0, strlen($expressionData->expression) - 1);

	$next = array();
	$letterNum = 0;
	return buildExpressionHtmlTree($expressionTree, $next, $letterNum);
}

/**
 * Builds expression html tree
 *
 * @param array $expressionTree output of getExpressionTree() function
 * @param array $next           parameter only for recursive call; should be empty array
 * @param int $letterNum        parameter only for recursive call; should be 0
 * @param int $level            parameter only for recursive call
 * @param string $operand       parameter only for recursive call
 *
 * @return bool                 returns true if element is found, false - otherwise
 */
function buildExpressionHtmlTree(array $expressionTree, array &$next, &$letterNum, $level = 0, $operand = null) {
	$treeList = array();
	$outline = '';

	end($expressionTree);
	$lastKey = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operand':
				$next[$level] = ($key != $lastKey);
				$expr = expressionLevelDraw($next, $level);
				$expr[] = SPACE;
				$expr[] = italic($element['operand'] == '&' ? S_AND_BIG : S_OR_BIG);
				$levelDetails = array(
					'list' => $expr,
					'id' => $element['id'],
					'expression' => array(
						'value' => $element['expression']
					)
				);

				$levelErrors = expressionHighLevelErrors($element['expression']);
				if (count($levelErrors) > 0) {
					$levelDetails['expression']['levelErrors'] = $levelErrors;
				}
				$treeList[] = $levelDetails;

				list($subOutline, $subTreeList) = buildExpressionHtmlTree($element['elements'], $next, $letterNum,
						$level + 1, $element['operand']);
				$treeList = array_merge($treeList, $subTreeList);

				$outline .= ($level == 0) ? $subOutline : '('.$subOutline.')';
				if ($operand !== null && $next[$level]) {
					$outline .= ' '.$operand.' ';
				}
				break;
			case 'expression':
				$next[$level] = ($key != $lastKey);

				$letter = num2letter($letterNum++);
				$outline .= $letter;
				if ($operand !== null && $next[$level]) {
					$outline .= ' '.$operand.' ';
				}

				if (defined('NO_LINK_IN_TESTING')) {
					$url = new CSpan($element['expression']);
				}
				else {
					$expressionId = 'expr_'.$element['id'];

					$url = new CSpan($element['expression'], 'link');
					$url->setAttribute('id', $expressionId);
					$url->setAttribute('onclick', 'javascript: copy_expression("'.$expressionId.'");');
				}
				$expr = expressionLevelDraw($next, $level);
				$expr[] = SPACE;
				$expr[] = bold($letter);
				$expr[] = SPACE;
				$expr[] = $url;

				$levelDetails = array(
					'list' => $expr,
					'id' => $element['id'],
					'expression' => array(
						'value' => $element['expression']
					)
				);

				$levelErrors = expressionHighLevelErrors($element['expression']);
				if (count($levelErrors) > 0) {
					$levelDetails['expression']['levelErrors'] = $levelErrors;
				}
				$treeList[] = $levelDetails;
				break;
		}
	}
	return array($outline, $treeList);
}

function expressionHighLevelErrors($expression) {
	static $errors, $definedErrorPhrases;

	if (!isset($errors)) {
		$definedErrorPhrases = array(
			EXPRESSION_VALUE_TYPE_UNKNOWN => S_EXPRESSION_VALUE_TYPE_UNKNOWN,
			EXPRESSION_HOST_UNKNOWN => S_EXPRESSION_HOST_UNKNOWN,
			EXPRESSION_HOST_ITEM_UNKNOWN => S_EXPRESSION_HOST_ITEM_UNKNOWN,
			EXPRESSION_NOT_A_MACRO_ERROR => S_EXPRESSION_NOT_A_MACRO_ERROR,
			EXPRESSION_FUNCTION_UNKNOWN => S_INCORRECT_FUNCTION_IS_USED
		);
		$errors = array();
	}

	if (!isset($errors[$expression])) {
		$errors[$expression] = array();
		$expressionData = new CTriggerExpression();
		if ($expressionData->parse($expression)) {
			foreach ($expressionData->expressions as $exprPart) {
				$info = get_item_function_info($exprPart['expression']);

				if (!is_array($info) && isset($definedErrorPhrases[$info])) {
					if (!isset($errors[$expression][$exprPart['expression']])) {
						$errors[$expression][$exprPart['expression']] = $definedErrorPhrases[$info];
					}
				}
			}
		}
	}

	$ret = array();
	if (count($errors[$expression]) == 0) {
		return $ret;
	}

	$expressionData = new CTriggerExpression();
	if ($expressionData->parse($expression)) {
		foreach ($expressionData->expressions as $exprPart) {
			if (isset($errors[$expression][$exprPart['expression']])) {
				$ret[$exprPart['expression']] = $errors[$expression][$exprPart['expression']];
			}
		}
	}
	return $ret;
}

/**
 * Draw level for trigger expression builder tree
 *
 * @param array $next
 * @param int $level
 *
 * @return array
 */
function expressionLevelDraw(array $next, $level) {
	$expr = array();
	for ($i = 1; $i <= $level; $i++) {
		if ($i == $level) {
			$image = $next[$i] ? 'top_right_bottom' : 'top_right';
		}
		else {
			$image = $next[$i] ? 'top_bottom' : 'space';
		}
		$expr[] = new CImg('images/general/tr_'.$image.'.gif', 'tr', 12, 12);
	}
	return $expr;
}

/**
 * Returns number of elements in a trigger expression
 * Element is expression between two operands.
 *
 * For example:
 * expression "{host.key.last(0)}=0 & ({host2:key.last(0)}=0 & {host3.key.last(0)}=0)" has two elements:
 * "{host.key.last(0)}=0" and "({host2:key.last(0)}=0 & {host3.key.last(0)}=0)"
 *
 * @param CTriggerExpression $expressionData
 * @param int $start
 * @param int $end
 *
 * @return integer
 */
function getExpressionElementsNum(CTriggerExpression $expressionData, $start, $end)
{
	for ($i = $start, $level = 0, $expressionElementsNum = 1; $i <= $end; $i++) {
		switch ($expressionData->expression[$i]) {
			case '(':
				$level++;
				break;
			case ')':
				$level--;
				break;
			case '|':
			case '&':
				if ($level == 0) {
					$expressionElementsNum++;
				}
				break;
			case '{':
				foreach ($expressionData->expressions as $exprPart) {
					if ($exprPart['pos'] == $i) {
						$i += strlen($exprPart['expression']) - 1;
						break;
					}
				}
				break;
		}
	}
	return $expressionElementsNum;
}

/**
 * Makes tree of expression elements
 *
 * Expression:
 *   "{host1:system.cpu.util[,iowait].last(0)} > 50 & {host2:system.cpu.util[,iowait].last(0)} > 50"
 * Result:
 *   array(
 *     [0] => array(
 *       'id' => '0_92',
 *       'type' => 'operand',
 *       'operand' => '&',
 *       'elements' => array(
 *         [0] => array(
 *           'id' => '0_44',
 *           'type' => 'expression',
 *           'expression' => '{host1:system.cpu.util[,iowait].last(0)} > 50'
 *         ),
 *         [1] => array(
 *           'id' => '48_92',
 *           'type' => 'expression',
 *           'expression' => '{host2:system.cpu.util[,iowait].last(0)} > 50'
 *         )
 *       )
 *     )
 *   )
 *
 * @param CTriggerExpression $expressionData
 * @param int $start
 * @param int $end
 *
 * @return array
 */
function getExpressionTree(CTriggerExpression $expressionData, $start, $end) {
	$expressionTree = array();

	foreach (array('|', '&') as $operand) {
		$operandFound = false;
		$lParentheses = -1;
		$rParentheses = -1;
		$expressions = array();
		$openSymbolNum = $start;

		for ($i = $start, $level = 0; $i <= $end; $i++) {
			switch ($expressionData->expression[$i]) {
				case ' ':
					if ($openSymbolNum == $i) {
						$openSymbolNum++;
					}
					break;
				case '(':
					if ($level == 0) {
						$lParentheses = $i;
					}
					$level++;
					break;
				case ')':
					$level--;
					if ($level == 0) {
						$rParentheses = $i;
					}
					break;
				case $operand:
					if ($level == 0) {
						$closeSymbolNum = $i - 1;
						while ($expressionData->expression[$closeSymbolNum] == ' ') {
							$closeSymbolNum--;
						}

						$expressionElementsNum = getExpressionElementsNum($expressionData, $openSymbolNum, $closeSymbolNum);
						if ($expressionElementsNum == 1 && $openSymbolNum == $lParentheses && $closeSymbolNum == $rParentheses) {
							$openSymbolNum++;
							$closeSymbolNum--;
						}

						$expressions[] = getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum);
						$openSymbolNum = $i + 1;
						$operandFound = true;
					}
					break;
				case '{':
					foreach ($expressionData->expressions as $exprPart) {
						if ($exprPart['pos'] == $i) {
							$i += strlen($exprPart['expression']) - 1;
							break;
						}
					}
					break;
			}
		}

		$closeSymbolNum = $end;
		while ($expressionData->expression[$closeSymbolNum] == ' ') {
			$closeSymbolNum--;
		}

		if ($operandFound) {
			$expressionElementsNum = getExpressionElementsNum($expressionData, $openSymbolNum, $closeSymbolNum);
			if ($expressionElementsNum == 1 && $openSymbolNum == $lParentheses && $closeSymbolNum == $rParentheses) {
				$openSymbolNum++;
				$closeSymbolNum--;
			}

			$expressions[] = getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum);

			$openSymbolNum = $start;
			while ($expressionData->expression[$openSymbolNum] == ' ') {
				$openSymbolNum++;
			}

			$closeSymbolNum = $end;
			while ($expressionData->expression[$closeSymbolNum] == ' ') {
				$closeSymbolNum--;
			}

			$expressionTree = array(
				'id' => $openSymbolNum.'_'.$closeSymbolNum,
				'expression' => substr($expressionData->expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
				'type' => 'operand',
				'operand' => $operand,
				'elements' => $expressions
			);
			break;
		}
		elseif ($operand == '&') {
			if ($openSymbolNum == $lParentheses && $closeSymbolNum == $rParentheses) {
				$openSymbolNum++;
				$closeSymbolNum--;

				$expressionTree = getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum);
			}
			else {
				$expressionTree = array(
					'id' => $openSymbolNum.'_'.$closeSymbolNum,
					'expression' => substr($expressionData->expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
					'type' => 'expression'
				);
			}
		}
	}

	return $expressionTree;
}

/**
 * Recreate an expression depending on action
 *
 * @param string $expression
 * @param string $expressionId  element identifier like "0_55"
 * @param string $action        one of &/|/r/R (AND/OR/replace/Remove)
 * @param string $newExpression expression for AND, OR or replace actions
 *
 * @return bool                 returns new expression or false if expression is incorrect
 */
function remakeExpression($expression, $expressionId, $action, $newExpression) {
	if (empty($expression)) {
		return false;
	}

	$expressionData = new CTriggerExpression();
	if ($action != 'R' && !$expressionData->parse($newExpression)) {
		error($expressionData->error);
		return false;
	}

	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}

	$expressionTree[] = getExpressionTree($expressionData, 0, strlen($expressionData->expression) - 1);

	if (rebuildExpressionTree($expressionTree, $expressionId, $action, $newExpression)) {
		$expression = makeExpression($expressionTree);
	}
	return $expression;
}

/**
 * Rebuild expression depending on action
 *
 * Example:
 *   $expressionTree = array(
 *     [0] => array(
 *       'id' => '0_92',
 *       'type' => 'operand',
 *       'operand' => '&',
 *       'elements' => array(
 *         [0] => array(
 *           'id' => '0_44',
 *           'type' => 'expression',
 *           'expression' => '{host1:system.cpu.util[,iowait].last(0)} > 50'
 *         ),
 *         [1] => array(
 *           'id' => '48_92',
 *           'type' => 'expression',
 *           'expression' => '{host2:system.cpu.util[,iowait].last(0)} > 50'
 *         )
 *       )
 *     )
 *   )
 *   $action = 'R'
 *   $expressionId = '48_92'
 *
 * Result:
 *   $expressionTree = array(
 *     [0] => array(
 *       'id' => '0_44',
 *       'type' => 'expression',
 *       'expression' => '{host1:system.cpu.util[,iowait].last(0)} > 50'
 *     )
 *   )
 *
 * @param array $expressionTree
 * @param string $expressionId  element identifier like "0_55"
 * @param string $action        one of &/|/r/R (AND/OR/replace/Remove)
 * @param string $newExpression expression for AND, OR or replace actions
 * @param string $operand       parameter only for recursive call
 *
 * @return bool                 returns true if element is found, false - otherwise
 */
function rebuildExpressionTree(array &$expressionTree, $expressionId, $action, $newExpression, $operand = null)
{
	foreach ($expressionTree as $key => $expression) {
		if ($expressionId == $expressionTree[$key]['id']) {
			switch ($action) {
				// AND and OR
				case '&':
				case '|':
					switch ($expressionTree[$key]['type']) {
						case 'operand':
							if ($expressionTree[$key]['operand'] == $action) {
								$expressionTree[$key]['elements'][] = array(
									'expression' => $newExpression,
									'type' => 'expression'
								);
							}
							else {
								$element = array(
									'type' => 'operand',
									'operand' => $action,
									'elements' => array(
										$expressionTree[$key],
										array(
											'expression' => $newExpression,
											'type' => 'expression'
										)
									)
								);
								$expressionTree[$key] = $element;
							}
							break;
						case 'expression':
							if (!$operand || $operand != $action) {
								$element = array(
									'type' => 'operand',
									'operand' => $action,
									'elements' => array(
										$expressionTree[$key],
										array(
											'expression' => $newExpression,
											'type' => 'expression'
										)
									)
								);
								$expressionTree[$key] = $element;
							}
							else {
								$expressionTree[] = array(
									'expression' => $newExpression,
									'type' => 'expression'
								);
							}
							break;
					}
					break;
				// replace
				case 'r':
					$expressionTree[$key]['expression'] = $newExpression;
					if ($expressionTree[$key]['type'] == 'operand') {
						$expressionTree[$key]['type'] = 'expression';
						unset($expressionTree[$key]['operand'], $expressionTree[$key]['elements']);
					}
					break;
				// remove
				case 'R':
					unset($expressionTree[$key]);
					break;
			}
			return true;
		}

		if ($expressionTree[$key]['type'] == 'operand') {
			if (rebuildExpressionTree($expressionTree[$key]['elements'], $expressionId, $action, $newExpression,
					$expressionTree[$key]['operand'])) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Makes expression by expression tree
 *
 * Example:
 *   $expressionTree = array(
 *     [0] => array(
 *       'type' => 'operand',
 *       'operand' => '&',
 *       'elements' => array(
 *         [0] => array(
 *           'type' => 'expression',
 *           'expression' => '{host1:system.cpu.util[,iowait].last(0)} > 50'
 *         ),
 *         [1] => array(
 *           'type' => 'expression',
 *           'expression' => '{host2:system.cpu.util[,iowait].last(0)} > 50'
 *         )
 *       )
 *     )
 *   )
 *
 * Result:
 *   "{host1:system.cpu.util[,iowait].last(0)} > 50 & {host2:system.cpu.util[,iowait].last(0)} > 50"
 *
 * @param array $expressionTree
 * @param int $level        parameter only for recursive call
 * @param string $operand       parameter only for recursive call
 *
 * @return string
 */
function makeExpression(array $expressionTree, $level = 0, $operand = null)
{
	$expression = '';

	end($expressionTree);
	$lastKey = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operand':
				$subExpression = makeExpression($element['elements'], $level + 1, $element['operand']);

				$expression .= ($level == 0) ? $subExpression : '('.$subExpression.')';
				break;
			case 'expression':
				$expression .= $element['expression'];
				break;
		}
		if ($operand !== null && $key != $lastKey) {
			$expression .= ' '.$operand.' ';
		}
	}
	return $expression;
}

function get_item_function_info($expr) {
	$value_type = array(
		ITEM_VALUE_TYPE_UINT64	=> S_NUMERIC_UINT64,
		ITEM_VALUE_TYPE_FLOAT	=> S_NUMERIC_FLOAT,
		ITEM_VALUE_TYPE_STR		=> S_CHARACTER,
		ITEM_VALUE_TYPE_LOG		=> S_LOG,
		ITEM_VALUE_TYPE_TEXT	=> S_TEXT
	);

	$type_of_value_type = array(
		ITEM_VALUE_TYPE_UINT64	=> T_ZBX_INT,
		ITEM_VALUE_TYPE_FLOAT	=> T_ZBX_DBL,
		ITEM_VALUE_TYPE_STR		=> T_ZBX_STR,
		ITEM_VALUE_TYPE_LOG		=> T_ZBX_STR,
		ITEM_VALUE_TYPE_TEXT	=> T_ZBX_STR
	);

	$function_info = array(
		'abschange' =>	array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'avg' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'change' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'count' =>		array('value_type' => S_NUMERIC_UINT64, 'type' => T_ZBX_INT, 		'validation' => NOT_EMPTY),
		'date' =>		array('value_type' => 'YYYYMMDD',	'type' => T_ZBX_INT,			'validation' => '{}>=19700101&&{}<=99991231'),
		'dayofmonth' =>	array('value_type' => '1-31',		'type' => T_ZBX_INT,			'validation' => '{}>=1&&{}<=31'),
		'dayofweek' =>	array('value_type' => '1-7',		'type' => T_ZBX_INT,			'validation' => IN('1,2,3,4,5,6,7')),
		'delta' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'diff' =>		array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'fuzzytime' =>	array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'iregexp' =>	array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'last' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'logeventid' =>	array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'logseverity' =>array('value_type' => S_NUMERIC_UINT64, 'type' => T_ZBX_INT, 		'validation' => NOT_EMPTY),
		'logsource' =>	array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'max' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'min' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'nodata' =>		array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'now' =>		array('value_type' => S_NUMERIC_UINT64, 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'prev' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'regexp' =>		array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'str' =>		array('value_type' => S_0_OR_1,		'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'strlen' =>		array('value_type' => S_NUMERIC_UINT64, 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'sum' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'time' =>		array('value_type' => 'HHMMSS',		'type' => T_ZBX_INT,			'validation' => 'zbx_strlen({})==6')
	);

	$expressionData = new CTriggerExpression();

	if ($expressionData->parse($expr)) {
		if (isset($expressionData->macros[0])) {
			$result = array(
				'value_type' => S_0_OR_1,
				'type' => T_ZBX_INT,
				'validation' => IN('0,1')
			);
		}
		elseif (isset($expressionData->usermacros[0])) {
			$result = array(
				'value_type' => S_0_OR_1,
				'type' => T_ZBX_INT,
				'validation' => NOT_EMPTY
			);
		}
		elseif (isset($expressionData->expressions[0])) {
			$exprPart = reset($expressionData->expressions);

			if (!isset($function_info[$exprPart['functionName']])) {
				return EXPRESSION_FUNCTION_UNKNOWN;
			}

			$hostFound = CHost::get(array(
				'filter' => array('host' => array($exprPart['host'])),
				'templated_hosts' => true
			));

			if (empty($hostFound)) {
				return EXPRESSION_HOST_UNKNOWN;
			}

			$itemFound = CItem::get(array(
				'hostids' => zbx_objectValues($hostFound, 'hostid'),
				'filter' => array(
					'key_' => array($exprPart['item']),
				),
				'webitems' => true
			));
			if (empty($itemFound)) {
				return EXPRESSION_HOST_ITEM_UNKNOWN;
			}

			$result = $function_info[$exprPart['functionName']];

			if (is_array($result['value_type'])) {
				$value_type = null;
				$item_data = CItem::get(array(
					'itemids' => zbx_objectValues($itemFound, 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'webitems' => true
				));

				if ($item_data = reset($item_data)) {
					$value_type = $item_data['value_type'];
				}

				if ($value_type == null) {
					return EXPRESSION_VALUE_TYPE_UNKNOWN;
				}

				$result['value_type'] = $result['value_type'][$value_type];
				$result['type'] = $result['type'][$value_type];

				if ($result['type'] == T_ZBX_INT || $result['type'] == T_ZBX_DBL) {
					$result['type'] = T_ZBX_STR;
					$result['validation'] = 'preg_match("/^'.ZBX_PREG_NUMBER.'$/u",{})';
				}
			}
		}
		else {
			return EXPRESSION_NOT_A_MACRO_ERROR;
		}
	}

	return $result;
}

	function convert($value){
		$value = trim($value);
		if(!preg_match('/(?P<value>[\-+]?[0-9]+[.]?[0-9]*)(?P<mult>[YZEPTGMKsmhdw]?)/', $value, $arr)) return $value;

		$value = $arr['value'];
		switch($arr['mult']){
			case 'Y':
				$value *= 1024 * 1024 * 1024 * 1024 * 1024 * 1024 * 1024 * 1024;
				break;
			case 'Z':
				$value *= 1024 * 1024 * 1024 * 1024 * 1024 * 1024 * 1024;
				break;
			case 'E':
				$value *= 1024 * 1024 * 1024 * 1024 * 1024 * 1024;
				break;
			case 'P':
				$value *= 1024 * 1024 * 1024 * 1024 * 1024;
				break;
			case 'T':
				$value *= 1024 * 1024 * 1024 * 1024;
				break;
			case 'G':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'M':
				$value *= 1024 * 1024;
				break;
			case 'K':
				$value *= 1024;
				break;
			case 'm':
				$value *= 60;
				break;
			case 'h':
				$value *= 60 * 60;
				break;
			case 'd':
				$value *= 60 * 60 * 24;
				break;
			case 'w':
				$value *= 60 * 60 * 24 * 7;
				break;
		}

		return $value;
	}

	function copy_triggers($srcid, $destid){
		try{
			$options = array(
				'hostids' => $srcid,
				'output' => array('host'),
				'templated_hosts' => 1
			);
			$src = CHost::get($options);
			if(empty($src)) throw new Exception();
			$src = reset($src);

			$options = array(
				'hostids' => $destid,
				'output' => array('host'),
				'templated_hosts' => 1
			);
			$dest = CHost::get($options);
			if(empty($dest)) throw new Exception();
			$dest = reset($dest);

			$options = array(
				'hostids' => $srcid,
				'output' => API_OUTPUT_EXTEND,
				'inherited' => 0,
				'select_items' => API_OUTPUT_EXTEND,
				'select_dependencies' => API_OUTPUT_EXTEND
			);
			$triggers = CTrigger::get($options);

			$hash = array();

			foreach($triggers as $trigger){
				if (httpitemExists($trigger['items']))
					continue;

				$trigger['expression'] = explode_exp($trigger['expression'], false, false, $src['host'], $dest['host']);

				$result = CTrigger::create($trigger);

				if(!$result) throw new Exception();

				$hash[$trigger['triggerid']] = reset($result['triggerids']);
			}

			foreach($triggers as $trigger){
				if (httpitemExists($trigger['items']))
					continue;

				foreach($trigger['dependencies'] as $dep){
					if(isset($hash[$dep['triggerid']])){
						$dep = $hash[$dep['triggerid']];
					}
					else{
						$dep = $dep['triggerid'];
					}

					$res = add_trigger_dependency($hash[$trigger['triggerid']], $dep);
					if(!$res) throw new Exception();
				}
			}

			return true;
		}
		catch(Exception $e){
			return false;
		}
	}

	function evalExpressionData($expression, $rplcts, $oct=false){
		$result = false;

		$evStr = str_replace(array_keys($rplcts), array_values($rplcts), $expression);

		preg_match_all('/[0-9\.]+['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?/', $evStr, $arr, PREG_OFFSET_CAPTURE);
		for ($i = count($arr[0]) - 1; $i >= 0; $i--) {
			$evStr = substr_replace($evStr, convert($arr[0][$i][0]), $arr[0][$i][1], strlen($arr[0][$i][0]));
		}

		if (!preg_match("/^[0-9.\s=#()><+*\/&E|\-]+$/is", $evStr)) return 'FALSE';

		if($oct)
			$evStr = preg_replace('/([0-9]+)(\=|\#|\!=|\<|\>)([0-9]+)/','((float)ltrim("$1","0") $2 (float)ltrim("$3","0"))', $evStr);

		$switch = array('=' => '==','#' => '!=','&' => '&&','|' => '||');
		$evStr = str_replace(array_keys($switch), array_values($switch), $evStr);

		eval('$result = ('.trim($evStr).');');

		$result = (($result === true) || ($result && $result != '-')) ? 'TRUE' : 'FALSE';

	return $result;
	}

/**
 * Resolve {TRIGGER.ID} macro in trigger url.
 * @param array $trigger trigger data with url and triggerid
 * @return string
 */
function resolveTriggerUrl($trigger) {
	return str_replace('{TRIGGER.ID}', $trigger['triggerid'], $trigger['url']);
}

	/**
	 * Checks that the objects in the expression are used correctly. Doesn't perform syntax validation.
	 *
	 * @param CTriggerExpression $expression
	 *
	 * @return bool
	 */
	function validate_trigger_expression(CTriggerExpression $expressionData) {
		if (!isset($expressionData->expressions[0])) {
			error('Trigger expression must contain at least one host:key reference.');
			return false;
		}

		$expressionHosts = $expressionData->getHosts();
		$hosts = CHost::get(array(
			'output' => array('status'),
			'filter' => array('host' => $expressionHosts),
			'nopermissions' => true,
			'preservekeys' => true
		));
		$templates = CTemplate::get(array(
			'output' => array('status'),
			'select_templates' => API_OUTPUT_REFER,
			'select_hosts' => API_OUTPUT_REFER,
			'filter' => array('host' => $expressionHosts),
			'templated_hosts' => true,
			'nopermissions' => true,
			'preservekeys' => true
		));

		// check that the expression doesn't contain items from both hosts and templates
		if ($templates && $hosts) {
			error(S_TRIGGER_EXPRESSION_CANT_MIX_TEMPLATES_HOSTS);
			return false;
		}

		// check that if the trigger uses templates, they all have the same links
		if ($templates) {
			$firstTemplate = reset($templates);
			$firstTemplateLinks = array_merge(
				zbx_objectValues($firstTemplate['hosts'], 'hostid'),
				zbx_objectValues($firstTemplate['templates'], 'templateid')
			);
			foreach ($templates as $template) {
				$templateLinks = array_merge(
					zbx_objectValues($template['hosts'], 'hostid'),
					zbx_objectValues($template['templates'], 'templateid')
				);

				if (array_diff($firstTemplateLinks, $templateLinks) || array_diff($templateLinks, $firstTemplateLinks)) {
					error(S_TRIGGER_BELONGS_TO_TEMPLATES_WITH_DIFFERENT_LINKS);
					return false;
				}
			}
		}

		return true;
	}

/**
 * Quoting $param if it contain special characters
 *
 * @param string $param
 *
 * @return string
 */
function quoteFunctionParam($param)
{
	if (!isset($param[0]) || ($param[0] != '"' && false === strpos($param, ',') && false === strpos($param, ')'))) {
		return $param;
	}

	return '"'.str_replace('"', '\\"', $param).'"';
}

?>
