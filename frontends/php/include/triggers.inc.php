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
?>
<?php
	require_once "maps.inc.php";
	require_once "acknow.inc.php";
	require_once "services.inc.php";

	/*
	 * Function: INIT_TRIGGER_EXPRESSION_STRUCTURES
	 *
	 * Description: 
	 *     initialize structures for trigger expression
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function INIT_TRIGGER_EXPRESSION_STRUCTURES()
	{
		if ( defined('TRIGGER_EXPRESSION_STRUCTURES_OK') ) return;
		define('TRIGGER_EXPRESSION_STRUCTURES_OK', 1);

		global $ZBX_TR_EXPR_ALLOWED_MACROS, $ZBX_TR_EXPR_REPLACE_TO, $ZBX_TR_EXPR_ALLOWED_FUNCTIONS;

		$ZBX_TR_EXPR_ALLOWED_MACROS['{TRIGGER.VALUE}'] = '{TRIGGER.VALUE}';

		$ZBX_TR_EXPR_REPLACE_TO = 'zbx_expr_ok';

		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['abschange']	= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_TEXT
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['avg']	= array('args' => array(	0 => array('type' => 'sec_num','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
				),
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['delta']	= array('args' => array( 0 => array('type' => 'sec_num','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
				),
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['change']	= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_TEXT
				),
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['count']	= array('args' => array( 0 => array('type' => 'sec','mandat' => true), 1 => array('type' => 'str') ),
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_TEXT
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['date']	= array('args' => null, 'item_types' => null );

		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['dayofweek']= array('args' => null,	'item_types' => null );

		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['diff']	= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_TEXT
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['fuzzytime']	= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['iregexp']= array('args' => array( 0 => array('type' => 'str','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['last']	= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_TEXT
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['max']	= array('args' => array( 0 => array('type' => 'sec_num','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['min']	= array('args' => array( 0 => array('type' => 'sec_num','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['nodata']= array('args' => array( 0 => array('type' => 'sec','mandat' => true) ), 'item_types' => null );
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['now']	= array('args' => null, 'item_types' => null );
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['prev']	= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_TEXT
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['str']	= array('args' => array( 0 => array('type' => 'str','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG
				)
			);


		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['sum']	= array('args' => array( 0 => array('type' => 'sec_num','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['logseverity']= array('args' => null,
			'item_types' => array(
				ITEM_VALUE_TYPE_LOG
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['logsource']= array('args' => array( 0=> array('type' => 'str','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_LOG
				)
			);

		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['regexp']= array('args' => array( 0 => array('type' => 'str','mandat' => true) ),
			'item_types' => array(
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG
				)
			);
		$ZBX_TR_EXPR_ALLOWED_FUNCTIONS['time']	= array('args' => null, 'item_types' => null );
	}

	INIT_TRIGGER_EXPRESSION_STRUCTURES();


	/*
	 * Function: get_severity_style 
	 *
	 * Description: 
	 *     convert severity constant in to the CSS style name
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	get_severity_style($severity)
	{
		if($severity == TRIGGER_SEVERITY_INFORMATION)	return 'information';
		elseif($severity == TRIGGER_SEVERITY_WARNING)	return 'warning';
		elseif($severity == TRIGGER_SEVERITY_AVERAGE)	return 'average';
		elseif($severity == TRIGGER_SEVERITY_HIGH)	return 'high';
		elseif($severity == TRIGGER_SEVERITY_DISASTER)	return 'disaster';

		return '';
	}

	/*
	 * Function: get_severity_description 
	 *
	 * Description: 
	 *     convert severity constant in to the string representation
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	get_severity_description($severity)
	{
		if($severity == TRIGGER_SEVERITY_NOT_CLASSIFIED)	return S_NOT_CLASSIFIED;
		else if($severity == TRIGGER_SEVERITY_INFORMATION)	return S_INFORMATION;
		else if($severity == TRIGGER_SEVERITY_WARNING)		return S_WARNING;
		else if($severity == TRIGGER_SEVERITY_AVERAGE)		return S_AVERAGE;
		else if($severity == TRIGGER_SEVERITY_HIGH)		return S_HIGH;
		else if($severity == TRIGGER_SEVERITY_DISASTER)		return S_DISASTER;

		return S_UNKNOWN;
	}

	/*
	 * Function: get_trigger_value_style 
	 *
	 * Description: 
	 *     convert trigger value in to the CSS style name
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	get_trigger_value_style($value)
	{
		$str_val[TRIGGER_VALUE_FALSE]	= 'off';
		$str_val[TRIGGER_VALUE_TRUE]	= 'on';
		$str_val[TRIGGER_VALUE_UNKNOWN]	= 'unknown';

		if(isset($str_val[$value]))
			return $str_val[$value];

		return '';
	}

	/*
	 * Function: trigger_value2str 
	 *
	 * Description: 
	 *     convert trigger value in to the string representation
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	trigger_value2str($value)
	{
		$str_val[TRIGGER_VALUE_FALSE]	= S_FALSE_BIG;
		$str_val[TRIGGER_VALUE_TRUE]	= S_TRUE_BIG;
		$str_val[TRIGGER_VALUE_UNKNOWN]	= S_UNKNOWN_BIG;

		if(isset($str_val[$value]))
			return $str_val[$value];

		return S_UNKNOWN;
	}

	/*
	 * Function: get_trigger_priority
	 *
	 * Description: 
	 *     retrive trigger's priority
	 *     
	 * Author: 
	 *     Artem Suharev
	 *
	 * Comments:
	 *
	 */
	
	function get_trigger_priority($triggerid){
		$sql = 'SELECT count(*) as count, priority '.
				' FROM triggers '.
				' WHERE triggerid='.$triggerid.
					' AND status=0 '.
					' AND value='.TRIGGER_VALUE_TRUE.
				' GROUP BY priority';
		
		$rows = DBfetch(DBselect($sql));

		if($rows && !is_null($rows['count']) && !is_null($rows['priority']) && ($rows['count'] > 0)){
			$status = $rows['priority'];
		}
		else{
			$status = 0;
		}
	return $status;
	}

	/*
	 * Function: get_realhosts_by_triggerid 
	 *
	 * Description: 
	 *     retrive real host for trigger
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function        get_realhosts_by_triggerid($triggerid)
	{
		$trigger = get_trigger_by_triggerid($triggerid);
		if($trigger['templateid'] > 0)
			return get_realhosts_by_triggerid($trigger['templateid']);

		return get_hosts_by_triggerid($triggerid);
	}

	function	get_trigger_by_triggerid($triggerid)
	{
		$sql="select * from triggers where triggerid=$triggerid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		error("No trigger with triggerid=[$triggerid]");
		return FALSE;
	}

	function	&get_hosts_by_triggerid($triggerid)
	{
		return DBselect('select distinct h.* from hosts h, functions f, items i'.
			' where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid='.$triggerid);
	}

	function	&get_functions_by_triggerid($triggerid)
	{
		return DBselect('select * from functions where triggerid='.$triggerid);
	}

	/*
	 * Function: get_triggers_by_hostid
	 *
	 * Description: 
	 *     retrive selection of triggers by hostid
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	&get_triggers_by_hostid($hostid, $show_mixed = "yes")
	{
		$db_triggers = DBselect("select distinct t.* from triggers t, functions f, items i".
			" where i.hostid=$hostid and f.itemid=i.itemid and f.triggerid=t.triggerid");

		if($show_mixed == "yes")
			return $db_triggers;

		$triggers = array();
		while($db_trigger = DBfetch($db_triggers))
		{
			$db_hosts = get_hosts_by_triggerid($db_trigger["triggerid"]);
			if(DBfetch($db_hosts))
			{
				array_push($triggers,$db_trigger["triggerid"]);
			}
		}
		$sql = "select distinct * from triggers where triggerid=0";
		foreach($triggers as $triggerid)
		{
			$sql .= " or triggerid=$triggerid";
		}
		return DBselect($sql);
	}

	function	&get_triggers_by_templateid($triggerid)
	{
		return DBselect('select * from triggers where templateid='.$triggerid);
	}

	/*
	 * Function: get_hosts_by_expression
	 *
	 * Description: 
	 *     retrive selection of hosts by trigger expression
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	&get_hosts_by_expression($expression)
	{
		global $ZBX_TR_EXPR_ALLOWED_MACROS, $ZBX_TR_EXPR_REPLACE_TO;

		$expr = $expression;

		$hosts = array();

		/* Replace all {server:key.function(param)} and {MACRO} with '$ZBX_TR_EXPR_REPLACE_TO' */
		while(ereg(ZBX_EREG_EXPRESSION_TOKEN_FORMAT, $expr, $arr))
		{
			if ( $arr[ZBX_EXPRESSION_MACRO_ID] && !isset($ZBX_TR_EXPR_ALLOWED_MACROS[$arr[ZBX_EXPRESSION_MACRO_ID]]) )
			{
				$hosts = array('0');
				break;
			}
			else if( !$arr[ZBX_EXPRESSION_MACRO_ID] ) 
			{
				$hosts[] = zbx_dbstr($arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_HOST_ID]);
			}
			$expr = $arr[ZBX_EXPRESSION_LEFT_ID].$ZBX_TR_EXPR_REPLACE_TO.$arr[ZBX_EXPRESSION_RIGHT_ID];
		}

		if(count($hosts) == 0) $hosts = array('0');

		return DBselect('select distinct * from hosts where '.DBin_node('hostid', get_current_nodeid(false)).
			' and host in ('.implode(',',$hosts).')');
	}

	/*
	 * Function: zbx_unquote_param
	 *
	 * Description: 
	 *     unquote string and unescape cahrs
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *     Double quotes used only.
	 *     Unquote string only if value directly in quotes.
	 *     Unescape only '\\' and '\"' combination
	 *
	 */
	function zbx_unquote_param($value)
	{
		$value = trim($value);
		if ( !empty($value) && '"' == $value[0] )
		{ /* open quotes and unescape chars */
			$value = substr($value, 1, strlen($value)-2);

			$new_val = '';
			for ( $i=0, $max=strlen($value); $i < $max; $i++)
			{
				if ( $i+1 < $max && $value[$i] == '\\' && ($value[$i+1] == '\\' || $value[$i+1] == '"') )
					$new_val .= $value[++$i];
				else
					$new_val .= $value[$i];
			}
			$value = $new_val;
		}
		return $value;
	}

	/*
	 * Function: zbx_get_params 
	 *
	 * Description: 
	 *     parse list of quoted parameters
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *     Double quotes used only.
	 *
	 */
	function zbx_get_params($string)
	{
		$params = array();
		$quoted = false;

		for( $param_s = $i = 0, $len = strlen($string); $i < $len; $i++)
		{
			switch ( $string[$i] )
			{
				case '"':
					$quoted = !$quoted;
					break;
				case ',':
					if ( !$quoted )
					{
						$params[] = zbx_unquote_param(substr($string, $param_s, $i - $param_s));
						$param_s = $i+1;
					}
					break;
				case '\\':
					if ( $quoted && $i+1 < $len && ($string[$i+1] == '\\' || $string[$i+1] == '"'))
						$i++;
					break;
			}
		}

		if( $quoted )
		{
			error('Incorrect usage of quotes. ['.$string.']');
			return null;
		}

		if( $i > $param_s )
		{
			$params[] = zbx_unquote_param(substr($string, $param_s, $i - $param_s));
		}

		return $params;
	}

	/*
	 * Function: validate_expression 
	 *
	 * Description: 
	 *     check trigger expression syntax and validate values
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	validate_expression($expression)
	{
		global $ZBX_TR_EXPR_ALLOWED_MACROS, $ZBX_TR_EXPR_REPLACE_TO, $ZBX_TR_EXPR_ALLOWED_FUNCTIONS;

		if( empty($expression) )
		{
			error('Expression can\'t be empty');
		}
		
		$expr = $expression;
		$h_status = array();

		/* Replace all {server:key.function(param)} and {MACRO} with '$ZBX_TR_EXPR_REPLACE_TO' */
		while(ereg(ZBX_EREG_EXPRESSION_TOKEN_FORMAT, $expr, $arr))
		{
			if ( $arr[ZBX_EXPRESSION_MACRO_ID] && !isset($ZBX_TR_EXPR_ALLOWED_MACROS[$arr[ZBX_EXPRESSION_MACRO_ID]]) )
			{
				error('Unknown macro ['.$arr[ZBX_EXPRESSION_MACRO_ID].']');
				return false;
			}
			else if( !$arr[ZBX_EXPRESSION_MACRO_ID] ) 
			{
				$host		= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_HOST_ID];
				$key		= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_KEY_ID];
				$function	= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_FUNCTION_NAME_ID];
				$parameter	= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_FUNCTION_PARAM_ID];
				
				/* Check host */
				$row=DBfetch(DBselect('select count(*) as cnt,min(status) as status,min(hostid) as hostid from hosts h where h.host='.zbx_dbstr($host).
						' and '.DBin_node('h.hostid', get_current_nodeid(false))
					));
				if($row['cnt']==0)
				{
					error('No such host ('.$host.')');
					return false;
				}
				elseif($row['cnt']!=1)
				{
					error('Too many hosts ('.$host.')');
					return false;
				}

				$h_status[$row['status']][$row['hostid']] = $row['cnt'];

				/* Check key */
				if ( !($item = DBfetch(DBselect('select i.itemid,i.value_type from hosts h,items i where h.host='.zbx_dbstr($host).
						' and i.key_='.zbx_dbstr($key).' and h.hostid=i.hostid '.
						' and '.DBin_node('h.hostid', get_current_nodeid(false))
					))) )
				{
					error('No such monitored parameter ('.$key.') for host ('.$host.')');
					return false;
				}

				/* Check function */
				if( !isset($ZBX_TR_EXPR_ALLOWED_FUNCTIONS[$function]) )
				{
					error('Unknown function ['.$function.']');
					return false;
				}

				$fnc_valid = &$ZBX_TR_EXPR_ALLOWED_FUNCTIONS[$function];

				if ( is_array($fnc_valid['item_types']) &&
					!in_array($item['value_type'], $fnc_valid['item_types']))
				{
					$allowed_types = array();
					foreach($fnc_valid['item_types'] as $type)
						$allowed_types[] = item_value_type2str($type);
					info('Function ('.$function.') available only for items with value types ['.implode(',',$allowed_types).']');
					error('Incorrect value type ['.item_value_type2str($item['value_type']).'] for function ('.$function.') of key ('.$host.':'.$key.')');
					return false;
				}

				if( !is_null($fnc_valid['args']) )
				{
					$parameter = zbx_get_params($parameter);

					if( !is_array($fnc_valid['args']) )
						$fnc_valid['args'] = array($fnc_valid['args']);

					foreach($fnc_valid['args'] as $pid => $params)
					{
						if(!isset($parameter[$pid]))
						{
							if( !isset($params['mandat']) ) 
							{
								continue;
							}
						 	else 
							{
								error('Missed mandatory parameter for function ('.$function.')');
								return false;
							}
						}

						if( 'sec' == $params['type'] 
							&& (validate_float($parameter[$pid])!=0) )
						{
							error('['.$parameter[$pid].'] is not a float for function ('.$function.')');
							return false;
						}

						if( 'sec_num' == $params['type'] 
							&& (validate_ticks($parameter[$pid])!=0) )
						{
							error('['.$parameter[$pid].'] is not a float or counter for function ('.$function.')');
							return false;
						}
					}
				}
			}
			$expr = $arr[ZBX_EXPRESSION_LEFT_ID].$ZBX_TR_EXPR_REPLACE_TO.$arr[ZBX_EXPRESSION_RIGHT_ID];
		}

		if ( isset($h_status[HOST_STATUS_TEMPLATE]) && ( count($h_status) > 1 || count($h_status[HOST_STATUS_TEMPLATE]) > 1 ))
		{
			error("Incorrect trigger expression. You can't use template hosts".
				" in mixed expressions.");
			return false;
		}

		/* Replace all calculations and numbers with '$ZBX_TR_EXPR_REPLACE_TO' */
		$expt_number = '('.$ZBX_TR_EXPR_REPLACE_TO.'|'.ZBX_EREG_NUMBER.')';
		$expt_term = '((\('.$expt_number.'\))|('.$expt_number.'))';
		$expr_format = '(('.$expt_term.ZBX_EREG_SPACES.ZBX_EREG_SIGN.ZBX_EREG_SPACES.$expt_term.')|(\('.$expt_term.'\)))';
		$expr_full_format = '((\('.$expr_format.'\))|('.$expr_format.'))';

		while($res = ereg($expr_full_format.'([[:print:]]*)$', $expr, $arr))
		{
			$expr = substr($expr, 0, strpos($expr, $arr[1])).$ZBX_TR_EXPR_REPLACE_TO.$arr[58];
		}

		if ( $ZBX_TR_EXPR_REPLACE_TO != $expr )
		{
			error('Incorrect trigger expression. ['.str_replace($ZBX_TR_EXPR_REPLACE_TO, ' ... ', $expr).']');
			return false;
		}

		return true;
	}


	function	add_trigger(
		$expression, $description, $priority, $status,
		$comments, $url, $deps=array(), $templateid=0)
	{
		if( !validate_expression($expression) )
			return false;

		$triggerid=get_dbid("triggers","triggerid");

		$result=DBexecute("insert into triggers".
			"  (triggerid,description,priority,status,comments,url,value,error,templateid)".
			" values ($triggerid,".zbx_dbstr($description).",$priority,$status,".zbx_dbstr($comments).",".
			"".zbx_dbstr($url).",2,'Trigger just added. No status update so far.',$templateid)");
		if(!$result)
		{
			return	$result;
		}
 
		add_event($triggerid,TRIGGER_VALUE_UNKNOWN);
 
		if( null == ($expression = implode_exp($expression,$triggerid)) )
		{
			$result = false;
		}

		if($result)
		{
			DBexecute("update triggers set expression=".zbx_dbstr($expression)." where triggerid=$triggerid");

			reset_items_nextcheck($triggerid);

			foreach($deps as $val)
			{
				$result = add_trigger_dependency($triggerid, $val);
			}
		}

		$trig_hosts = get_hosts_by_triggerid($triggerid);
		$trig_host = DBfetch($trig_hosts);
		if($result)
		{
			$msg = "Added trigger '".$description."'";
			if($trig_host)
			{
				$msg .= " to host '".$trig_host["host"]."'";
			}
			info($msg);
		}

		if($trig_host)
		{// create trigger for childs
			$child_hosts = get_hosts_by_templateid($trig_host["hostid"]);
			while($child_host = DBfetch($child_hosts))
			{
				if( !($result = copy_trigger_to_host($triggerid, $child_host["hostid"])))
					break;
			}
		}

		if(!$result){
			if($templateid == 0)
			{ // delete main trigger (and recursively childs)
				delete_trigger($triggerid);
			}
			return $result;
		}

		return $triggerid;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	get_trigger_dependences_by_triggerid($triggerid)
	{
		$result = array();

		$db_deps = DBselect("select * from trigger_depends where triggerid_down=".$triggerid);
		while($db_dep = DBfetch($db_deps))
				$result[] = $db_dep['triggerid_up'];
			
		return $result;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	replace_template_dependences($deps, $hostid)
	{
		foreach($deps as $id => $val)
		{
			if($db_new_dep = DBfetch(DBselect('select t.triggerid from triggers t,functions f,items i '.
				' where t.templateid='.$val.' and f.triggerid=t.triggerid '.
				' and f.itemid=i.itemid and i.hostid='.$hostid)))
					$deps[$id] = $db_new_dep['triggerid'];
		}
		return $deps;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	copy_trigger_to_host($triggerid, $hostid, $copy_mode = false)
	{
		$trigger = get_trigger_by_triggerid($triggerid);

		$deps = replace_template_dependences(
				get_trigger_dependences_by_triggerid($triggerid),
				$hostid);

		$host_triggers = get_triggers_by_hostid($hostid, "no");
		while($host_trigger = DBfetch($host_triggers))
		{
			if($host_trigger["templateid"] != 0)				continue;
			if(cmp_triggers($triggerid, $host_trigger["triggerid"]))	continue;

			// link not linked trigger with same expression
			return update_trigger(
				$host_trigger["triggerid"],
				NULL,	// expression
				$trigger["description"],
				$trigger["priority"],
				NULL,	// status
				$trigger["comments"],
				$trigger["url"],
				$deps,
				$copy_mode ? 0 : $triggerid);
		}

		$newtriggerid=get_dbid("triggers","triggerid");

		$result = DBexecute("insert into triggers".
			" (triggerid,description,priority,status,comments,url,value,expression,templateid)".
			" values ($newtriggerid,".zbx_dbstr($trigger["description"]).",".$trigger["priority"].",".
			$trigger["status"].",".zbx_dbstr($trigger["comments"]).",".
			zbx_dbstr($trigger["url"]).",2,'{???:???}',".
			($copy_mode ? 0 : $triggerid).")");

		if(!$result)
			return $result;

		$host = get_host_by_hostid($hostid);
		$newexpression = $trigger["expression"];

		// Loop: functions
		$functions = get_functions_by_triggerid($triggerid);
		while($function = DBfetch($functions))
		{
			$item = get_item_by_itemid($function["itemid"]);

			$host_items = DBselect("select * from items".
				" where key_=".zbx_dbstr($item["key_"]).
				" and hostid=".$host["hostid"]);
			$host_item = DBfetch($host_items);
			if(!$host_item)
			{
				error("Missing key '".$item["key_"]."' for host '".$host["host"]."'");
				return FALSE;
			}

			$newfunctionid=get_dbid("functions","functionid");

			$result = DBexecute("insert into functions (functionid,itemid,triggerid,function,parameter)".
				" values ($newfunctionid,".$host_item["itemid"].",$newtriggerid,".
				zbx_dbstr($function["function"]).",".zbx_dbstr($function["parameter"]).")");

			$newexpression = str_replace(
				"{".$function["functionid"]."}",
				"{".$newfunctionid."}",
				$newexpression);
		}

		DBexecute("update triggers set expression=".zbx_dbstr($newexpression).
			" where triggerid=$newtriggerid");
// copy dependences
		delete_dependencies_by_triggerid($newtriggerid);
		foreach($deps as $dep_id)
		{
			add_trigger_dependency($newtriggerid, $dep_id);
		}

		info("Added trigger '".$trigger["description"]."' to host '".$host["host"]."'");

// Copy triggers to the child hosts
		$child_hosts = get_hosts_by_templateid($hostid);
		while($child_host = DBfetch($child_hosts))
		{// recursion
			$result = copy_trigger_to_host($newtriggerid, $child_host["hostid"]);
			if(!$result){
				return result;
			}
		}

		return $newtriggerid;
	}
	

	/******************************************************************************
	 *                                                                            *
	 * Purpose: Translate {10}>10 to something like localhost:procload.last(0)>10 *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	explode_exp ($expression, $html,$template=false)
	{
#		echo "EXPRESSION:",$expression,"<Br>";

		$functionid='';
		$exp='';
		$state='';
		for($i=0,$max=strlen($expression); $i<$max; $i++)
		{
			if($expression[$i] == '{')
			{
				$functionid='';
				$state='FUNCTIONID';
				continue;
			}
			if($expression[$i] == '}')
			{
				$state='';
				if($functionid=="TRIGGER.VALUE")
				{
					$exp .= "{".$functionid."}";
				}
				else if(is_numeric($functionid) && $function_data = DBfetch(DBselect('select h.host,i.key_,f.function,f.parameter,i.itemid,i.value_type'.
					' from items i,functions f,hosts h'.
					' where f.functionid='.$functionid.' and i.itemid=f.itemid and h.hostid=i.hostid')))
				{
					if($template) $function_data["host"] = '{HOSTNAME}';
						
					if($html == 0)
					{
						$exp .= "{".$function_data["host"].":".$function_data["key_"].".".
							$function_data["function"]."(".$function_data["parameter"].")}";
					}
					else
					{
						$link = new CLink($function_data["host"].":".$function_data["key_"],
							'history.php?action='.( $function_data["value_type"] ==0 ? 'showvalues' : 'showgraph').
							'&itemid='.$function_data['itemid']);
					
						$exp .= '{'.$link->ToString().'.'.bold($function_data["function"].'(').$function_data["parameter"].bold(')').'}';
					}
				}
				else
				{
					if($html == 1)	$exp .= "<FONT COLOR=\"#AA0000\">";
					$exp .= "*ERROR*";
					if($html == 1)	$exp .= "</FONT>";
				}
				continue;
			}
			if($state == "FUNCTIONID")
			{
				$functionid=$functionid.$expression[$i];
				continue;
			}
			$exp=$exp.$expression[$i];
		}
#		echo "EXP:",$exp,"<Br>";
		return $exp;
	}

	/*
	 * Function: implode_exp
	 *
	 * Description: 
	 *     Translate localhost:procload.last(0)>10 to {12}>10
	 *     And create database representation.
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	implode_exp ($expression, $triggerid)
	{
		global $ZBX_TR_EXPR_ALLOWED_MACROS, $ZBX_TR_EXPR_REPLACE_TO;
		$expr = $expression;
		$short_exp = $expression;

		/* Replace all {server:key.function(param)} and {MACRO} with '$ZBX_TR_EXPR_REPLACE_TO' */
		/* build short expression {12}>10 */
		while(ereg(ZBX_EREG_EXPRESSION_TOKEN_FORMAT, $expr, $arr))
		{
			if ( $arr[ZBX_EXPRESSION_MACRO_ID] && !isset($ZBX_TR_EXPR_ALLOWED_MACROS[$arr[ZBX_EXPRESSION_MACRO_ID]]) )
			{
				error('[ie] Unknown macro ['.$arr[ZBX_EXPRESSION_MACRO_ID].']');
				return false;
			}
			else if( !$arr[ZBX_EXPRESSION_MACRO_ID] ) 
			{
				$s_expr		= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID];
				$host		= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_HOST_ID];
				$key		= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_KEY_ID];
				$function	= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_FUNCTION_NAME_ID];
				$parameter	= &$arr[ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID + ZBX_SIMPLE_EXPRESSION_FUNCTION_PARAM_ID];

				$item = DBfetch(DBselect('select i.itemid from items i,hosts h'.
					' where i.key_='.zbx_dbstr($key).
					' and h.host='.zbx_dbstr($host).
					' and h.hostid=i.hostid'));

				$item = $item["itemid"];

				$functionid = get_dbid("functions","functionid");

				if ( !DBexecute('insert into functions (functionid,itemid,triggerid,function,parameter)'.
					' values ('.$functionid.','.$item.','.$triggerid.','.zbx_dbstr($function).','.
					zbx_dbstr($parameter).')'))
				{
					return	null;
				}
				$short_exp = str_replace($s_expr,'{'.$functionid.'}',$short_exp);
				$expr = str_replace($s_expr,$ZBX_TR_EXPR_REPLACE_TO,$expr);
				continue;
			}
			$expr = $arr[ZBX_EXPRESSION_LEFT_ID].$ZBX_TR_EXPR_REPLACE_TO.$arr[ZBX_EXPRESSION_RIGHT_ID];
		}

		return $short_exp;
	}

	function	update_trigger_comments($triggerid,$comments)
	{
		return	DBexecute("update triggers set comments=".zbx_dbstr($comments).
			" where triggerid=$triggerid");
	}

	# Update Trigger status

	function	update_trigger_status($triggerid,$status)
	{
		// first update status for child triggers
		$db_chd_triggers = get_triggers_by_templateid($triggerid);
		while($db_chd_trigger = DBfetch($db_chd_triggers))
		{
			update_trigger_status($db_chd_trigger["triggerid"],$status);
		}

		add_event($triggerid,TRIGGER_VALUE_UNKNOWN);
		return	DBexecute("update triggers set status=$status where triggerid=$triggerid");
	}

	/*
	 * Function: extract_numbers
	 *
	 * Description: 
	 *     Extract from string numbers with prefixes (A-Z)
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	extract_numbers($str)
	{
		$numbers = array();
		while ( ereg(ZBX_EREG_NUMBER.'([[:print:]]*)', $str, $arr) ) {
			$numbers[] = $arr[1];
			$str = $arr[2];
		}
		return $numbers;
	}

	/*
	 * Function: expand_trigger_description_constants
	 *
	 * Description: 
	 *     substitute simple macros in data string with real values
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *           replcae $1-9 macros
	 *
	 */
	function	expand_trigger_description_constants($description, $row)
	{
		if($row && isset($row['expression']))
		{
			$numbers = extract_numbers(ereg_replace('(\{[0-9]+\})', 'function', $row['expression']));
			$description = $row["description"];

			for ( $i = 0; $i < 9; $i++ )
			{
				$description = 
					str_replace(
						'$'.($i+1),
						isset($numbers[$i]) ? 
							$numbers[$i] : 
							'', 
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
	 *     substitute simple macros in data string with real values
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	expand_trigger_description_by_data($row)
	{
		if($row)
		{
			$description = expand_trigger_description_constants($row['description'], $row);

			if(is_null($row["host"])) $row["host"] = "{HOSTNAME}";
			$description = str_replace("{HOSTNAME}", $row["host"],$description);

			if(strstr($description,"{ITEM.LASTVALUE}"))
			{
				$row2=DBfetch(DBselect('select i.lastvalue from items i, triggers t, functions f '.
					' where i.itemid=f.itemid and f.triggerid=t.triggerid and '.
					' t.triggerid='.$row["triggerid"]));

				if(is_null($row2["lastvalue"])) $row["lastvalue"] = "{ITEM.LASTVALUE}";
				$description = str_replace("{ITEM.LASTVALUE}", $row2["lastvalue"],$description);
			}
		}
		else
		{
			$description = "*ERROR*";
		}
		return $description;
	}
	
	function	expand_trigger_description_simple($triggerid)
	{
		return expand_trigger_description_by_data(
			DBfetch(
				DBselect("select distinct t.description,h.host,t.expression,t.triggerid ".
					" from triggers t left join functions f on t.triggerid=f.triggerid ".
					" left join items i on f.itemid=i.itemid ".
					" left join hosts h on i.hostid=h.hostid ".
					" where t.triggerid=$triggerid")
				)
			);
	}

	function	expand_trigger_description($triggerid)
	{
		$description=expand_trigger_description_simple($triggerid);
		$description=stripslashes(htmlspecialchars($description));

		return $description;
	}

	function	update_trigger_value_to_unknown_by_hostid($hostid)
	{
		$result = DBselect("select distinct t.triggerid".
			" from hosts h,items i,triggers t,functions f".
			" where f.triggerid=t.triggerid and f.itemid=i.itemid".
			" and h.hostid=i.hostid and h.hostid=$hostid");
		$now = time();
		while($row=DBfetch($result))
		{
			if(!add_event($row["triggerid"],TRIGGER_VALUE_UNKNOWN,$now)) continue;

			DBexecute('update triggers set value='.TRIGGER_VALUE_UNKNOWN.' where triggerid='.$row["triggerid"]);
		}
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function add_event($triggerid, $value, $time=NULL)
	{
		if(is_null($time)) $time = time();

		$result = DBselect('select value,clock from events where objectid='.$triggerid.' and object='.EVENT_OBJECT_TRIGGER.
			' order by clock desc',1);
		$last_value = DBfetch($result);
		if($last_value)
		{
			if($value == $last_value['value'])
				return false;
		}
		$eventid = get_dbid("events","eventid");
		$result = DBexecute('insert into events(eventid,source,object,objectid,clock,value) '.
				' values('.$eventid.','.EVENT_SOURCE_TRIGGERS.','.EVENT_OBJECT_TRIGGER.','.$triggerid.','.$time.','.$value.')');
		if($value == TRIGGER_VALUE_FALSE || $value == TRIGGER_VALUE_TRUE)
		{
			DBexesute('update alerts set retries=3,error=\'Trigger changed its status. WIll not send repeats.\''.
				' where triggerid='.$triggerid.' and repeats>0 and status='.ALERT_STATUS_NOT_SENT);
		}
		return true;
	}

	function	add_trigger_dependency($triggerid,$depid)
	{
		$result=insert_dependency($triggerid,$depid);;
		if(!$result)
		{
			return $result;
		}
		//add_additional_dependencies($triggerid,$depid);
		return $result;
	}

	/******************************************************************************
	 *                                                                            *
	 * Purpose: Delete Trigger definition                                         *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_trigger($triggerid)
	{
		// first delete child triggers
		$db_triggers= get_triggers_by_templateid($triggerid);
		while($db_trigger = DBfetch($db_triggers))
		{// recursion
			$result = delete_trigger($db_trigger["triggerid"]);
			if(!$result)    return  $result;
		}

		// get hosts before functions deletion !!!
		$trig_hosts = get_hosts_by_triggerid($triggerid);

		$result = delete_dependencies_by_triggerid($triggerid);
		if(!$result)	return	$result;

		DBexecute("delete from trigger_depends where triggerid_up=$triggerid");

		$result=delete_function_by_triggerid($triggerid);
		if(!$result)	return	$result;

		$result=delete_events_by_triggerid($triggerid);
		if(!$result)	return	$result;

		$result=delete_services_by_triggerid($triggerid);
		if(!$result)	return	$result;

		$result=delete_sysmaps_elements_with_triggerid($triggerid);
		if(!$result)	return	$result;

		DBexecute("delete from alerts where triggerid=$triggerid");

		DBexecute("update sysmaps_links set triggerid=NULL where triggerid=$triggerid");
		
	// disable actions
		$db_actions = DBselect("select distinct actionid from conditions ".
			" where conditiontype=".CONDITION_TYPE_TRIGGER." and value=".$triggerid);
		while($db_action = DBfetch($db_actions))
		{
			DBexecute("update actions set status=".ACTION_STATUS_DISABLED.
				" where actionid=".$db_action["actionid"]);
		}
	// delete action conditions
		DBexecute('delete from conditions where conditiontype='.CONDITION_TYPE_TRIGGER.' and value='.$triggerid);

		$trigger = get_trigger_by_triggerid($triggerid);

		$result = DBexecute("delete from triggers where triggerid=$triggerid");

		if($result)
		{
			$msg = "Trigger '".$trigger["description"]."' deleted";
			$trig_host = DBfetch($trig_hosts);
			if($trig_host)
			{
				$msg .= " from host '".$trig_host["host"]."'";
			}
			info($msg);
		}
		return $result;
	}

	# Update Trigger definition

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	update_trigger($triggerid,$expression=NULL,$description=NULL,$priority=NULL,$status=NULL,
		$comments=NULL,$url=NULL,$deps=array(),$templateid=0)
	{
		$trigger	= get_trigger_by_triggerid($triggerid);
		$trig_hosts	= get_hosts_by_triggerid($triggerid);
		$trig_host	= DBfetch($trig_hosts);

		if(is_null($expression))
		{
			/* Restore expression */
			$expression = explode_exp($trigger["expression"],0);
		}

		if ( !validate_expression($expression) )
			return false;

		$exp_hosts 	= get_hosts_by_expression($expression);
		if( $exp_hosts )
		{
			$chd_hosts	= get_hosts_by_templateid($trig_host["hostid"]);

			if(DBfetch($chd_hosts))
			{
				$exp_host = DBfetch($exp_hosts);
				$db_chd_triggers = get_triggers_by_templateid($triggerid);
				while($db_chd_trigger = DBfetch($db_chd_triggers))
				{
					$chd_trig_hosts = get_hosts_by_triggerid($db_chd_trigger["triggerid"]);
					$chd_trig_host = DBfetch($chd_trig_hosts);

					$newexpression = str_replace(
						"{".$exp_host["host"].":",
						"{".$chd_trig_host["host"].":",
						$expression);
				// recursion
					update_trigger(
						$db_chd_trigger["triggerid"],
						$newexpression,
						$description,
						$priority,
						NULL,		// status
						$comments,
						$url,
						replace_template_dependences($deps, $chd_trig_host['hostid']),
						$triggerid);
				}
			}
		}

		$result=delete_function_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}

		$expression = implode_exp($expression,$triggerid); /* errors can be ignored cose function must return NULL */

		add_event($triggerid,TRIGGER_VALUE_UNKNOWN);
		reset_items_nextcheck($triggerid);

		$sql="update triggers set";
		if(!is_null($expression))	$sql .= " expression=".zbx_dbstr($expression).",";
		if(!is_null($description))	$sql .= " description=".zbx_dbstr($description).",";
		if(!is_null($priority))		$sql .= " priority=$priority,";
		if(!is_null($status))		$sql .= " status=$status,";
		if(!is_null($comments))		$sql .= " comments=".zbx_dbstr($comments).",";
		if(!is_null($url))		$sql .= " url=".zbx_dbstr($url).",";
		if(!is_null($templateid))	$sql .= " templateid=$templateid,";
		$sql .= " value=2 where triggerid=$triggerid";

		$result = DBexecute($sql);

		delete_dependencies_by_triggerid($triggerid);
		foreach($deps as $val)
		{
			$result=add_trigger_dependency($triggerid, $val);
		}

		if($result)
		{
			$trig_hosts	= get_hosts_by_triggerid($triggerid);
			$msg = "Trigger '".$trigger["description"]."' updated";
			$trig_host = DBfetch($trig_hosts);
			if($trig_host)
			{
				$msg .= " for host '".$trig_host["host"]."'";
			}
			info($msg);
		}
		return $result;
	}

	function	check_right_on_trigger_by_triggerid($permission,$triggerid,$accessible_hosts=null)
	{
		$trigger_data = DBfetch(DBselect('select expression from triggers where triggerid='.$triggerid));

		if(!$trigger_data) return false;

		return check_right_on_trigger_by_expression($permission, explode_exp($trigger_data['expression'], 0), $accessible_hosts);
	}

	function	check_right_on_trigger_by_expression($permission,$expression,$accessible_hosts=null)
	{
		if(is_null($accessible_hosts))
		{
			global $USER_DETAILS;
			$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS, $permission, null, PERM_RES_IDS_ARRAY);
		}
		if(!is_array($accessible_hosts)) $accessible_hosts = explode(',', $accessible_hosts);

                $db_hosts = get_hosts_by_expression($expression);
		while($host_data = DBfetch($db_hosts))
		{
			if(!in_array($host_data['hostid'], $accessible_hosts)) return false;
		}

		return true;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_dependencies_by_triggerid($triggerid)
	{
		$db_deps = DBselect('select triggerid_up, triggerid_down from trigger_depends'.
			' where triggerid_down='.$triggerid);
		while($db_dep = DBfetch($db_deps))
		{
			DBexecute('update triggers set dep_level=dep_level-1 where triggerid='.$db_dep['triggerid_up']);
			DBexecute('delete from trigger_depends'.
				' where triggerid_up='.$db_dep['triggerid_up'].
				' and triggerid_down='.$db_dep['triggerid_down']);
		}
		return true;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	insert_dependency($triggerid_down,$triggerid_up)
	{
		$triggerdepid = get_dbid("trigger_depends","triggerdepid");
		$result=DBexecute("insert into trigger_depends (triggerdepid,triggerid_down,triggerid_up)".
			" values ($triggerdepid,$triggerid_down,$triggerid_up)");
		if(!$result)
		{
			return	$result;
		}
		return DBexecute("update triggers set dep_level=dep_level+1 where triggerid=$triggerid_up");
	}

	/* INCORRECT LOGIC: If 1 depends on 2, and 2 depends on 3, then add dependency 1->3
	
	function	add_additional_dependencies($triggerid_down,$triggerid_up)
	{
		$result=DBselect("select triggerid_down from trigger_depends".
			" where triggerid_up=$triggerid_down");
		while($row=DBfetch($result))
		{
			insert_dependency($row["triggerid_down"],$triggerid_up);
			add_additional_dependencies($row["triggerid_down"],$triggerid_up);
		}
		$result=DBselect("select triggerid_up from trigger_depends where triggerid_down=$triggerid_up");
		while($row=DBfetch($result))
		{
			insert_dependency($triggerid_down,$row["triggerid_up"]);
			add_additional_dependencies($triggerid_down,$row["triggerid_up"]);
		}
	}
	*/

	function	delete_function_by_triggerid($triggerid)
	{
		return	DBexecute("delete from functions where triggerid=$triggerid");
	}

	function	delete_events_by_triggerid($triggerid)
	{
		return	DBexecute('delete from events where objectid='.$triggerid.' and object='.EVENT_OBJECT_TRIGGER);
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_triggers_by_itemid($itemid)
	{
		$result=DBselect("select triggerid from functions where itemid=$itemid");
		while($row=DBfetch($result))
		{
			if(!delete_trigger($row["triggerid"]))
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	/******************************************************************************
	 *                                                                            *
	 * Purpose: Delete Service definitions by triggerid                           *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_services_by_triggerid($triggerid)
	{
		$result = DBselect("select serviceid from services where triggerid=$triggerid");
		while($row = DBfetch($result))
		{
			delete_service($row["serviceid"]);
		}
		return	TRUE;
	}

	/*
	 * Function: cmp_triggers
	 *
	 * Description: 
	 *     compate triggers by expression
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	cmp_triggers($triggerid1, $triggerid2)	// compare EXPRESSION !!!
	{
		$trig1 = get_trigger_by_triggerid($triggerid1);
		$trig2 = get_trigger_by_triggerid($triggerid2);

		$trig_fnc1 = get_functions_by_triggerid($triggerid1);
		
		$expr1 = $trig1["expression"];
		while($fnc1 = DBfetch($trig_fnc1))
		{
			$trig_fnc2 = get_functions_by_triggerid($triggerid2);
			while($fnc2 = DBfetch($trig_fnc2)){
				if(strcmp($fnc1["function"],$fnc2["function"]))	continue;
				if($fnc1["parameter"] != $fnc2["parameter"])	continue;

				$item1 = get_item_by_itemid($fnc1["itemid"]);
				$item2 = get_item_by_itemid($fnc2["itemid"]);

				if(strcmp($item1["key_"],$item2["key_"]))	continue;

				$expr1 = str_replace(
					"{".$fnc1["functionid"]."}",
					"{".$fnc2["functionid"]."}",
					$expr1);
				break;
			}
		}
		return strcmp($expr1,$trig2["expression"]);
	}

	/*
	 * Function: delete_template_triggers
	 *
	 * Description: 
	 *     Delete template triggers
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	delete_template_triggers($hostid, $templateid = null, $unlink_mode = false)
	{
		$triggers = get_triggers_by_hostid($hostid);
		while($trigger = DBfetch($triggers))
		{
			if($trigger["templateid"]==0)	continue;

			if($templateid != null)
                        {
				if( !is_array($templateid))
					$templateid = array($templateid);

                                $db_tmp_hosts = get_hosts_by_triggerid($trigger["templateid"]);
				$tmp_host = DBfetch($db_tmp_hosts);

				if( !in_array($tmp_host["hostid"], $templateid) )
					continue;
                        }

                        if($unlink_mode)
                        {
                                if(DBexecute("update triggers set templateid=0 where triggerid=".$trigger["triggerid"]))
                                {
                                        info("Trigger '".$trigger["description"]."' unlinked");
                                }
                        }
                        else
                        {
				delete_trigger($trigger["triggerid"]);
			}
		}

		return TRUE;
	}
	
	/*
	 * Function: copy_template_triggers
	 *
	 * Description: 
	 *     Copy triggers from template
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	copy_template_triggers($hostid, $templateid = null, $copy_mode = false)
	{
		if(null == $templateid)
		{
			$templateid = array_keys(get_templates_by_hostid($hostid));
		}

		if(is_array($templateid))
		{
			foreach($templateid as $id)
				copy_template_triggers($hostid, $id, $copy_mode); // attention recursion
			return;
		}

		$triggers = get_triggers_by_hostid($templateid);
		while($trigger = DBfetch($triggers))
		{
			copy_trigger_to_host($trigger["triggerid"], $hostid, $copy_mode);
		}

		update_template_dependences_for_host($hostid);
	}

	/*
	 * Function: update_template_dependences_for_host
	 *
	 * Description: 
	 *     Update template triggers
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	update_template_dependences_for_host($hostid)
	{
		$db_triggers = get_triggers_by_hostid($hostid);
		while($trigger_data = DBfetch($db_triggers))
		{
			$db_chd_triggers = get_triggers_by_templateid($trigger_data['triggerid']);
			while($chd_trigger_data = DBfetch($db_chd_triggers))
				update_trigger($chd_trigger_data['triggerid'],
					/*$expression*/		NULL,
					/*$description*/	NULL,
					/*$priority*/		NULL,
					/*$status*/		NULL,
					/*$comments*/		NULL,
					/*$url*/		NULL,
					replace_template_dependences(
						get_trigger_dependences_by_triggerid($trigger_data['triggerid']),
						$hostid),
					$trigger_data['triggerid']);

		}
	}

	/*
	 * Function: get_triggers_overview
	 *
	 * Description: 
	 *     Retrive table with overview of triggers
	 *     
	 * Author: 
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function	get_triggers_overview($groupid)
	{
		global $USER_DETAILS;

		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);
		if($groupid > 0)
		{
			$group_where = ',hosts_groups hg where hg.groupid='.$groupid.' and hg.hostid=h.hostid and';
		} else {
			$group_where = ' where';
		}

		$result=DBselect('select distinct t.triggerid,t.description,t.expression,t.value,t.priority,t.lastchange,h.hostid,h.host'.
			' from hosts h,items i,triggers t, functions f '.$group_where.
			' h.status='.HOST_STATUS_MONITORED.' and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid'.
			' and h.hostid in ('.get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, null, null, get_current_nodeid()).') '.
			' and t.status='.TRIGGER_STATUS_ENABLED.' and i.status='.ITEM_STATUS_ACTIVE.
			' order by t.description');
		unset($triggers);
		unset($hosts);
		while($row = DBfetch($result))
		{
			$row['host'] = get_node_name_by_elid($row['hostid']).$row['host'];
			$row['description'] = expand_trigger_description_constants($row['description'], $row);

			$hosts[$row['host']] = $row['host'];
			$triggers[$row['description']][$row['host']] = array(
				'hostid'	=> $row['hostid'], 
				'triggerid'	=> $row['triggerid'], 
				'value'		=> $row['value'], 
				'lastchange'	=> $row['lastchange'],
				'priority'	=> $row['priority']);
		}
		if(!isset($hosts))
		{
			return $table;
		}
		sort($hosts);

		$header=array(new CCol(S_TRIGGERS,'center'));
		foreach($hosts as $hostname)
		{
			$header=array_merge($header,array(new CImg('vtext.php?text='.$hostname)));
		}
		$table->SetHeader($header,'vertical_header');

		foreach($triggers as $descr => $trhosts)
		{
			$table_row = array(nbsp($descr));
			foreach($hosts as $hostname)
			{
				$css_class = NULL;

				unset($tr_ov_menu);
				$ack = null;
				if(isset($trhosts[$hostname]))
				{
					unset($ack_menu);
					switch($trhosts[$hostname]['value'])
					{
						case TRIGGER_VALUE_TRUE:
							$css_class = get_severity_style($trhosts[$hostname]['priority']);
							if( ($ack = get_last_event_by_triggerid($trhosts[$hostname]['triggerid'])) )
								$ack_menu = array(S_ACKNOWLEDGE, 'acknow.php?eventid='.$ack['eventid'], array('tw'=>'_blank'));

							if ( 1 == $ack['acknowledged'] )
								$ack = new CImg('images/general/tick.png','ack');
							else
								$ack = null;

							break;
						case TRIGGER_VALUE_FALSE:
							$css_class = 'normal';
							break;
						default:
							$css_class = 'unknown_trigger';
					}

					$style = 'cursor: pointer; ';

					if((time(NULL)-$trhosts[$hostname]['lastchange'])<300)
						$style .= 'background-image: url(images/gradients/blink1.gif); '.
							'background-position: top left; '.
							'background-repeat: repeate;';
					elseif((time(NULL)-$trhosts[$hostname]['lastchange'])<900)
						$style .= 'background-image: url(images/gradients/blink2.gif); '.
							'background-position: top left; '.
							'background-repeat: repeate;';

					unset($item_menu);
					$tr_ov_menu = array(
						/* name, url, (target [tw], statusbar [sb]), css, submenu */
						array(S_TRIGGER, null,  null, 
							array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
							),
						array(S_EVENTS, 'tr_events.php?triggerid='.$trhosts[$hostname]['triggerid'], array('tw'=>'_blank'))
						);

					if(isset($ack_menu)) $tr_ov_menu[] = $ack_menu;

					$db_items = DBselect('select distinct i.itemid, i.description, i.key_, i.value_type '.
						' from items i, functions f '.
						' where f.itemid=i.itemid and f.triggerid='.$trhosts[$hostname]['triggerid']);

					while($item_data = DBfetch($db_items))
					{
						$description = item_description($item_data['description'], $item_data['key_']);
						switch($item_data['value_type'])
						{
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
						
						if(strlen($description) > 25) $description = substr($description,0,22).'...';

						$item_menu[$action][] = array(
							$description,
							'history.php?action='.$action.'&itemid='.$item_data['itemid'].'&period=3600',
							 array('tw'=>'_blank', 'sb'=>$status_bar));
					}
					if(isset($item_menu['showgraph']))
					{
						$tr_ov_menu[] = array(S_GRAPHS,	null, null,
							array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
							);
						$tr_ov_menu = array_merge($tr_ov_menu, $item_menu['showgraph']);
					}
					if(isset($item_menu['showlatest']))
					{
						$tr_ov_menu[] = array(S_VALUES,	null, null, 
							array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
							);
						$tr_ov_menu = array_merge($tr_ov_menu, $item_menu['showlatest']);
					}

					unset($item_menu);
				}

				$status_col = new CCol(array(SPACE, $ack),$css_class);
				if(isset($style))
				{
					$status_col->AddOption('style', $style);
				}

				if(isset($tr_ov_menu))
				{
					$tr_ov_menu  = new CPUMenu($tr_ov_menu,170);
					$status_col->OnClick($tr_ov_menu->GetOnActionJS());
					$status_col->AddAction('onmouseover',
						'this.old_border=this.style.border; this.style.border=\'1px dotted #0C0CF0\'');
					$status_col->AddAction('onmouseout', 'this.style.border=this.old_border;');
				}
				array_push($table_row,$status_col);
			}
			$table->AddRow($table_row);
		}
		return $table;
	}

	function	get_function_by_functionid($functionid)
	{
		$result=DBselect("select * from functions where functionid=$functionid");
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

	function	calculate_availability($triggerid,$period_start,$period_end)
	{
		$sql='select count(*) as cnt,min(clock) as minn,max(clock) as maxx from events '.
			' where objectid='.$triggerid.' and object='.EVENT_OBJECT_TRIGGER;

		if($period_start!=0)	$sql .= ' and clock>='.$period_start;
		if($period_end!=0)	$sql .= ' and clock<='.$period_end;

		$row=DBfetch(DBselect($sql));
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
				$ret["true_time"]	= 0;
				$ret["false_time"]	= 0;
				$ret["unknown_time"]	= 0;
				$ret["true"]		= 0;
				$ret["false"]		= 0;
				$ret["unknown"]		= 100;
				return $ret;
			}
		}

		$result=DBselect('select clock,value from events where objectid='.$triggerid.' and object='.EVENT_OBJECT_TRIGGER
			.' and clock>='.$min.' and clock<='.$max);

		$state		= -1;
		$true_time	= 0;
		$false_time	= 0;
		$unknown_time	= 0;
		$time		= $min;

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
			$trigger = get_trigger_by_triggerid($triggerid);
			$state = $trigger['value'];
		}
		
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

		$total_time=$true_time+$false_time+$unknown_time;

		if($total_time==0)
		{
			$ret["true_time"]	= 0;
			$ret["false_time"]	= 0;
			$ret["unknown_time"]	= 0;
			$ret["true"]		= 0;
			$ret["false"]		= 0;
			$ret["unknown"]		= 100;
		}
		else
		{
			$ret["true_time"]	= $true_time;
			$ret["false_time"]	= $false_time;
			$ret["unknown_time"]	= $unknown_time;
			$ret["true"]		= (100*$true_time)/$total_time;
			$ret["false"]		= (100*$false_time)/$total_time;
			$ret["unknown"]		= (100*$unknown_time)/$total_time;
		}
		return $ret;
	}

	function construct_expression($itemid,$expressions){
		$expression='';

		$item = get_item_by_itemid($itemid);
		$host = get_host_by_itemid($itemid);
		
		$prefix = $host['host'].':'.$item['key_'].'.';

		foreach($expressions as $id => $expr){
			$eq = (($expr['type'] == REGEXP_INCLUDE)?'#':'=').'0';
			
			if(!preg_match("/^iregexp|regexp\(.*\)/iUm",$expr['value'])){
				error('Incorrect trigger expression. ['.$expr['value'].']');
				return false;
			}
			$expr['value'] = preg_replace('/\s+(\&|\|){1,2}\s+/U','$1',$expr['value']);
			
			$expr['value'] = eregi_replace('(regexp|iregexp)(\(.*\))(&|\|){1,2}','\\1\\2\\3',$expr['value']);

			$expr['value'] = preg_replace('/(regexp|iregexp)(\(.*\))/iUu','{$1$2}'.$eq,$expr['value']);
			
			$patern = array('/iregexp\((.*)\)/iUu','/regexp\((.*)\)/iUu','/regiexp\((.*)\)/iUu');
			$replacement = array('regiexp(\\1)',($prefix."regexp(\\1)"),($prefix."iregexp(\\1)"));
			
			$expr['value'] = preg_replace($patern,$replacement,$expr['value']);
			$expressions[$id] = $expr;
		}
		
		foreach($expressions as $id => $expr){
			$expression .= (!empty($expression))?' & ':'';
			$expression .= '('.$expr['value'].')';
		}
	return $expression;
	}
	
	function get_notacknowledged($triggerid){
		$cond=(TRIGGER_SHOW_UNDEFINED_ACK)?' OR (e.value=2 AND (('.time().'-e.clock)<'.TRIGGER_FALSE_TIME_ACK.'))':'';
		
		$sql = 'SELECT DISTINCT e.eventid, e.value, e.clock '.
				' FROM events e '.
				' WHERE e.object=0 AND e.objectid='.$triggerid.
					'  AND (e.acknowledged=0 AND (e.value=1 OR (e.value=0 AND (('.time().'-e.clock)<'.TRIGGER_FALSE_TIME_ACK.'))'.$cond.'))'.
				'ORDER BY e.eventid DESC';
	return DBselect($sql);
	}
?>
