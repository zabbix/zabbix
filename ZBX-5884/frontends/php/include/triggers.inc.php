<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; ifnot, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

/**
 * Returns an array of trigger IDs that are available to the current user.
 *
 * @param int $perm         either PERM_READ_WRITE for writing, or PERM_READ_ONLY for reading
 * @param array $hostids
 * @param int $cache
 *
 * @return array|int
 */
function get_accessible_triggers($perm, $hostids = array(), $cache = 1) {
	static $available_triggers;

	$userid = CWebUser::$data['userid'];
	$nodeid = get_current_nodeid();
	$nodeid_str = is_array($nodeid) ? implode('', $nodeid) : strval($nodeid);
	$hostid_str = implode('', $hostids);
	$cache_hash = md5($userid.$perm.$nodeid_str.$hostid_str);

	if ($cache && isset($available_triggers[$cache_hash])) {
		return $available_triggers[$cache_hash];
	}

	$options = array(
		'output' => API_OUTPUT_SHORTEN,
		'nodeids' => $nodeid
	);
	if (!empty($hostids)) {
		$options['hostids'] = $hostids;
	}
	if ($perm == PERM_READ_WRITE) {
		$options['editable'] = true;
	}

	$result = API::Trigger()->get($options);
	$result = zbx_objectValues($result, 'triggerid');
	$result = zbx_toHash($result);

	$available_triggers[$cache_hash] = $result;

	return $result;
}

function getSeverityStyle($severity, $type = true) {
	$styles = array(
		TRIGGER_SEVERITY_DISASTER => 'disaster',
		TRIGGER_SEVERITY_HIGH => 'high',
		TRIGGER_SEVERITY_AVERAGE => 'average',
		TRIGGER_SEVERITY_WARNING => 'warning',
		TRIGGER_SEVERITY_INFORMATION => 'information',
		TRIGGER_SEVERITY_NOT_CLASSIFIED => 'not_classified'
	);

	if (!$type) {
		return 'normal';
	}
	elseif (isset($styles[$severity])) {
		return $styles[$severity];
	}
	else {
		return '';
	}
}

function getSeverityCaption($severity = null) {
	$config = select_config();

	$severities = array(
		TRIGGER_SEVERITY_NOT_CLASSIFIED => _($config['severity_name_0']),
		TRIGGER_SEVERITY_INFORMATION => _($config['severity_name_1']),
		TRIGGER_SEVERITY_WARNING => _($config['severity_name_2']),
		TRIGGER_SEVERITY_AVERAGE => _($config['severity_name_3']),
		TRIGGER_SEVERITY_HIGH => _($config['severity_name_4']),
		TRIGGER_SEVERITY_DISASTER => _($config['severity_name_5'])
	);

	if (is_null($severity)) {
		return $severities;
	}
	elseif (isset($severities[$severity])) {
		return $severities[$severity];
	}
	else {
		return _('Unknown');
	}
}

function getSeverityColor($severity, $value = TRIGGER_VALUE_TRUE) {
	if ($value == TRIGGER_VALUE_FALSE) {
		return 'AAFFAA';
	}
	$config = select_config();

	switch ($severity) {
		case TRIGGER_SEVERITY_DISASTER:
			$color = $config['severity_color_5'];
			break;
		case TRIGGER_SEVERITY_HIGH:
			$color = $config['severity_color_4'];
			break;
		case TRIGGER_SEVERITY_AVERAGE:
			$color = $config['severity_color_3'];
			break;
		case TRIGGER_SEVERITY_WARNING:
			$color = $config['severity_color_2'];
			break;
		case TRIGGER_SEVERITY_INFORMATION:
			$color = $config['severity_color_1'];
			break;
		case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			$color = $config['severity_color_0'];
			break;
		default:
			$color = $config['severity_color_0'];
	}

	return $color;
}

function getSeverityCell($severity, $text = null, $force_normal = false) {
	if ($text === null) {
		$text = getSeverityCaption($severity);
	}

	return new CCol($text, getSeverityStyle($severity, !$force_normal));
}

// retrieve trigger's priority for services
function get_service_status_of_trigger($triggerid) {
	$sql = 'SELECT t.triggerid,t.priority'.
			' FROM triggers t'.
			' WHERE t.triggerid='.$triggerid.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				' AND t.value='.TRIGGER_VALUE_TRUE;
	$rows = DBfetch(DBselect($sql, 1));

	return !empty($rows['priority']) ? $rows['priority'] : 0;
}

/**
 * Add color style and blinking to an object like CSpan or CDiv depending on trigger status
 * Settings and colors are kept in 'config' database table
 *
 * @param mixed $object object like CSpan, CDiv, etc.
 * @param int $triggerValue TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE or TRIGGER_VALUE_UNKNOWN
 * @param int $triggerLastChange
 * @param bool $isAcknowledged
 * @return void
 */
function addTriggerValueStyle($object, $triggerValue, $triggerLastChange, $isAcknowledged) {
	$config = select_config();

	// color of text and blinking depends on trigger value and whether event is acknowledged
	if ($triggerValue == TRIGGER_VALUE_TRUE && !$isAcknowledged) {
		$color = $config['problem_unack_color'];
		$blinks = $config['problem_unack_style'];
	}
	elseif ($triggerValue == TRIGGER_VALUE_TRUE && $isAcknowledged) {
		$color = $config['problem_ack_color'];
		$blinks = $config['problem_ack_style'];
	}
	elseif ($triggerValue == TRIGGER_VALUE_FALSE && !$isAcknowledged) {
		$color = $config['ok_unack_color'];
		$blinks = $config['ok_unack_style'];
	}
	elseif ($triggerValue == TRIGGER_VALUE_FALSE && $isAcknowledged) {
		$color = $config['ok_ack_color'];
		$blinks = $config['ok_ack_style'];
	}
	if (isset($color) && isset($blinks)) {
		// color
		$object->addStyle('color: #'.$color);

		// blinking
		$timeSinceLastChange = time() - $triggerLastChange;
		if ($blinks && $timeSinceLastChange < $config['blink_period']) {
			$object->addClass('blink'); // elements with this class will blink
			$object->setAttribute('data-time-to-blink', $config['blink_period'] - $timeSinceLastChange);
		}
	}
	else {
		$object->addClass('unknown');
	}
}

function trigger_value2str($value) {
	$str_val[TRIGGER_VALUE_FALSE] = _('OK');
	$str_val[TRIGGER_VALUE_TRUE] = _('PROBLEM');
	$str_val[TRIGGER_VALUE_UNKNOWN] = _('UNKNOWN');

	if (isset($str_val[$value])) {
		return $str_val[$value];
	}

	return _('Unknown');
}

function discovery_value($val = null) {
	$array = array(
		DOBJECT_STATUS_UP => _('UP'),
		DOBJECT_STATUS_DOWN => _('DOWN'),
		DOBJECT_STATUS_DISCOVER => _('DISCOVERED'),
		DOBJECT_STATUS_LOST => _('LOST')
	);

	if (is_null($val)) {
		return $array;
	}
	elseif (isset($array[$val])) {
		return $array[$val];
	}
	else {
		return _('Unknown');
	}
}

function discovery_value_style($val) {
	switch ($val) {
		case DOBJECT_STATUS_UP:
			$style = 'off';
			break;
		case DOBJECT_STATUS_DOWN:
			$style = 'on';
			break;
		case DOBJECT_STATUS_DISCOVER:
			$style = 'off';
			break;
		case DOBJECT_STATUS_LOST:
			$style = 'unknown';
			break;
		default:
			$style = '';
	}

	return $style;
}

function getParentHostsByTriggers($triggers) {
	$hosts = array();
	$triggerParent = array();

	while (!empty($triggers)) {
		foreach ($triggers as $tnum => $trigger) {
			if ($trigger['templateid'] == 0) {
				if (isset($triggerParent[$trigger['triggerid']])) {
					foreach ($triggerParent[$trigger['triggerid']] as $triggerid => $state) {
						$hosts[$triggerid] = $trigger['hosts'];
					}
				}
				else {
					$hosts[$trigger['triggerid']] = $trigger['hosts'];
				}
				unset($triggers[$tnum]);
			}
			else {
				if (isset($triggerParent[$trigger['triggerid']])) {
					if (!isset($triggerParent[$trigger['templateid']])) {
						$triggerParent[$trigger['templateid']] = array();
					}
					$triggerParent[$trigger['templateid']][$trigger['triggerid']] = 1;
					$triggerParent[$trigger['templateid']] += $triggerParent[$trigger['triggerid']];
				}
				else {
					if (!isset($triggerParent[$trigger['templateid']])) {
						$triggerParent[$trigger['templateid']] = array();
					}
					$triggerParent[$trigger['templateid']][$trigger['triggerid']] = 1;
				}
			}
		}
		$triggers = API::Trigger()->get(array(
			'triggerids' => zbx_objectValues($triggers, 'templateid'),
			'selectHosts' => array('hostid', 'host', 'name', 'status'),
			'output' => array('triggerid', 'templateid'),
			'filter' => array('flags' => null),
			'nopermissions' => true
		));
	}

	return $hosts;
}

function get_trigger_by_triggerid($triggerid) {
	$db_trigger = DBfetch(DBselect('SELECT t.* FROM triggers t WHERE t.triggerid='.$triggerid));
	if (!empty($db_trigger)) {
		return $db_trigger;
	}
	error(_s('No trigger with triggerid "%1$s".', $triggerid));

	return false;
}

function get_hosts_by_triggerid($triggerids) {
	zbx_value2array($triggerids);

	return DBselect(
		'SELECT DISTINCT h.*'.
		' FROM hosts h,functions f,items i'.
		' WHERE i.itemid=f.itemid'.
			' AND h.hostid=i.hostid'.
			' AND '.DBcondition('f.triggerid', $triggerids)
	);
}

function get_triggers_by_hostid($hostid) {
	return DBselect(
		'SELECT DISTINCT t.*'.
		' FROM triggers t,functions f,items i'.
		' WHERE i.hostid='.$hostid.
			' AND f.itemid=i.itemid'.
			' AND f.triggerid=t.triggerid'
	);
}

function get_trigger_by_description($desc) {
	list($host_name, $trigger_description) = explode(':', $desc, 2);

	$sql = 'SELECT t.*'.
			' FROM triggers t,items i,functions f,hosts h'.
			' WHERE h.host='.zbx_dbstr($host_name).
				' AND i.hostid=h.hostid'.
				' AND f.itemid=i.itemid'.
				' AND t.triggerid=f.triggerid'.
				' AND t.description='.zbx_dbstr($trigger_description).
			' ORDER BY t.triggerid DESC';
	return DBfetch(DBselect($sql, 1));
}

// unescape Raw URL
function utf8RawUrlDecode($source) {
	$decodedStr = '';
	$pos = 0;
	$len = strlen($source);
	while ($pos < $len) {
		$charAt = substr($source, $pos, 1);
		if ($charAt == '%') {
			$pos++;
			$charAt = substr($source, $pos, 1);
			if ($charAt == 'u') {
				// we got a unicode character
				$pos++;
				$unicodeHexVal = substr($source, $pos, 4);
				$unicode = hexdec($unicodeHexVal);
				$entity = "&#".$unicode.';';
				$decodedStr .= html_entity_decode(utf8_encode($entity), ENT_COMPAT, 'UTF-8');
				$pos += 4;
			}
			else {
				$decodedStr .= substr($source, $pos-1, 1);
			}
		}
		else {
			$decodedStr .= $charAt;
			$pos++;
		}
	}

	return $decodedStr;
}

/**
 * Copies the given triggers to the given hosts or templates.
 *
 * Without the $srcHostId parameter it will only be able to copy triggers that belong to only one host. If the
 * $srcHostId parameter is not passed, and a trigger has multiple hosts, it will throw an error. If the
 * $srcHostId parameter is passed, the given host will be replaced with the destination host.
 *
 * This function takes care of copied trigger dependencies.
 * If trigger is copied alongside with trigger on which it depends, then dependencies is replaced directly using new ids,
 * If there is target host within dependency trigger, algorithm will search for potential matching trigger in target host,
 * if matching trigger is found, then id from this trigger is used, if not rise exception,
 * otherwise original dependency will be left.
 *
 *
 * @param int|array $srcTriggerIds triggers which will be copied to $dstHostIds
 * @param int|array $dstHostIds hosts and templates to whom add triggers, ids not present in DB (host table) will be ignored
 * @param int $srcHostId host id in which context trigger with multiple hosts will be treated
 *
 * @return bool
 */
function copyTriggersToHosts($srcTriggerIds, $dstHostIds, $srcHostId = null) {
	$options = array(
		'triggerids' => $srcTriggerIds,
		'output' => array('triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments', 'type'),
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'selectItems' => API_OUTPUT_EXTEND,
		'selectDependencies' => API_OUTPUT_REFER
	);
	if ($srcHostId) {
		$srcHost = API::Host()->get(array(
			'output' => array('host'),
			'hostids' => $srcHostId,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		));

		// if provided $srcHostId doesn't match any record in DB, return false
		if (!($srcHost = reset($srcHost))) {
			return false;
		}
	}
	// if no $srcHostId provided we will need trigger host 'host'
	else {
		$options['selectHosts'] = array('host');
	}
	$dbSrcTriggers = API::Trigger()->get($options);

	$dbDstHosts = API::Host()->get(array(
		'output' => array('hostid', 'host'),
		'hostids' => $dstHostIds,
		'preservekeys' => true,
		'nopermissions' => true,
		'templated_hosts' => true
	));

	$newTriggers = array();
	// create each trigger for each host
	foreach ($dbDstHosts as $dstHost) {
		foreach ($dbSrcTriggers as $srcTrigger) {
			// if $srcHostId provided, get host 'host' for explode_exp()
			if ($srcHostId != 0) {
				$host = $srcHost['host'];
				$srcTriggerContextHostId = $srcHostId;
			}
			// if $srcHostId not provided, use source trigger first host 'host'
			else {
				// if we have multiple hosts in trigger expression and we haven't pointed ($srcHostId) which host to replace, call error
				if (count($srcTrigger['hosts']) > 1) {
					error(_s('Cannot copy trigger "%1$s:%2$s", because it has multiple hosts in the expression.',
						$srcTrigger['description'], explode_exp($srcTrigger['expression'])));
					return false;
				}
				$host = $srcTrigger['hosts'][0]['host'];
				$srcTriggerContextHostId = $srcTrigger['hosts'][0]['hostid'];
			}
			// get expression for the new trigger to be added
			$srcTrigger['expression'] = explode_exp($srcTrigger['expression'], false, false, $host, $dstHost['host']);

			// the dependencies must be added after all triggers are created
			unset($srcTrigger['dependencies']);

			unset($srcTrigger['templateid']);

			if (!$result = API::Trigger()->create($srcTrigger)) {
				return false;
			}

			$newTriggers[$srcTrigger['triggerid']] = array(
				'newTriggerId' =>reset($result['triggerids']),
				'newTriggerHostId' =>  $dstHost['hostid'],
				'newTriggerHost' =>  $dstHost['host'],
				'srcTriggerContextHostId' => $srcTriggerContextHostId,
				'srcTriggerContextHost' => $host
			);
		}
	}

	$depIds = array();
	foreach ($dbSrcTriggers as $srcTrigger) {
		if ($srcTrigger['dependencies']) {
			foreach ($srcTrigger['dependencies'] as $depTrigger) {
				$depIds[] = $depTrigger['triggerid'];
			}
		}
	}
	$depTriggers = API::Trigger()->get(array(
		'triggerids' => $depIds,
		'output' => API_OUTPUT_EXTEND,
		'nopermissions' => true,
		'selectHosts' => array('hostid'),
		'preservekeys' => true
	));

	// map dependencies to the new trigger IDs and save
	if ($newTriggers) {
		$dependencies = array();
		foreach ($dbSrcTriggers as $srcTrigger) {
			if ($srcTrigger['dependencies']) {
				// get coresponding created trigger id
				$newTrigger = $newTriggers[$srcTrigger['triggerid']];


				foreach ($srcTrigger['dependencies'] as $depTrigger) {
					// we have added $depTrigger trigger, and we know corresponding trigger id for newly created trigger
					if (isset($newTriggers[$depTrigger['triggerid']])) {

						// dependency is within same host
						// according to $srcHostId parameter or dep trigger has single host
						if ($newTrigger['srcTriggerContextHostId'] == $newTriggers[$depTrigger['triggerid']]['srcTriggerContextHostId']) {
							$depTriggerId = $newTriggers[$depTrigger['triggerid']]['newTriggerId'];
						}
						// dependency is to trigger from another host
						else {
							$depTriggerId = $depTrigger['triggerid'];
						}
					}
					// we need to search for $depTrigger trigger if target host is within dependency hosts
					elseif (in_array(array('hostid'=>$newTrigger['srcTriggerContextHostId']), $depTriggers[$depTrigger['triggerid']]['hosts'])) {
						// get all possible $depTrigger matching triggers by description
						$targetHostTriggersByDescription = API::Trigger()->get(array(
							'hostids' => $newTrigger['newTriggerHostId'],
							'output' => array('hosts', 'triggerid', 'expression'),
							'filter' => array('description' => $depTriggers[$depTrigger['triggerid']]['description']),
							'preservekeys' => true
						));

						// compare exploded expressions for exact match
						$expr1 = explode_exp($depTriggers[$depTrigger['triggerid']]['expression']);
						$depTriggerId = null;
						foreach ($targetHostTriggersByDescription as $potentialTargetTrigger) {
							$expr2 = explode_exp($potentialTargetTrigger['expression'], false, false, $newTrigger['newTriggerHost'], $newTrigger['srcTriggerContextHost']);
							if ($expr2 == $expr1) {
								// matching trigger has been found
								$depTriggerId = $potentialTargetTrigger['triggerid'];
								break;
							}
						}
						// if matching trigger wasn't found rise exception
						if (is_null($depTriggerId)) {
							$expr1 = explode_exp($srcTrigger['expression'], false, false, $newTrigger['srcTriggerContextHost'], $newTrigger['newTriggerHost']);
							$expr2 = explode_exp($depTriggers[$depTrigger['triggerid']]['expression'], false, false, $newTrigger['srcTriggerContextHost'], $newTrigger['newTriggerHost']);
							error(_s('Cannot add dependency from trigger "%1$s:%2$s" to non existing trigger "%3$s:%4$s".',
								$srcTrigger['description'], $expr1,
								$depTriggers[$depTrigger['triggerid']]['description'], $expr2));
							return false;
						}
					}
					// leave original dependency
					else {
						$depTriggerId = $depTrigger['triggerid'];
					}


					$dependencies[] = array(
						'triggerid' => $newTrigger['newTriggerId'],
						'dependsOnTriggerid' => $depTriggerId
					);
				}
			}
		}
		if ($dependencies) {
			if (!API::Trigger()->addDependencies($dependencies)) {
				return false;
			}
		}
	}

	return true;
}

function construct_expression($itemid, $expressions) {
	$complite_expr = '';
	$item = get_item_by_itemid($itemid);
	$host = get_host_by_itemid($itemid);
	$prefix = $host['host'].':'.$item['key_'].'.';

	if (empty($expressions)) {
		error(_('Expression cannot be empty'));
		return false;
	}

	$ZBX_PREG_EXPESSION_FUNC_FORMAT = '^(['.ZBX_PREG_PRINT.']*)([&|]{1})(([a-zA-Z_.\$]{6,7})(\\((['.ZBX_PREG_PRINT.']+){0,1}\\)))(['.ZBX_PREG_PRINT.']*)$';
	$functions = array('regexp' => 1, 'iregexp' => 1);
	$expr_array = array();
	$cexpor = 0;
	$startpos = -1;

	foreach ($expressions as $expression) {
		$expression['value'] = preg_replace('/\s+(AND){1,2}\s+/U', '&', $expression['value']);
		$expression['value'] = preg_replace('/\s+(OR){1,2}\s+/U', '|', $expression['value']);

		if ($expression['type'] == REGEXP_INCLUDE) {
			if (!empty($complite_expr)) {
				$complite_expr.=' | ';
			}
			if ($cexpor == 0) {
				$startpos = zbx_strlen($complite_expr);
			}
			$cexpor++;
			$eq_global = '#0';
		}
		else {
			if (($cexpor > 1) & ($startpos >= 0)) {
				$head = substr($complite_expr, 0, $startpos);
				$tail = substr($complite_expr, $startpos);
				$complite_expr = $head.'('.$tail.')';
			}
			$cexpor = 0;
			$eq_global = '=0';
			if (!empty($complite_expr)) {
				$complite_expr.=' & ';
			}
		}

		$expr = '&'.$expression['value'];
		$expr = preg_replace('/\s+(\&|\|){1,2}\s+/U', '$1', $expr);

		$expr_array = array();
		$sub_expr_count=0;
		$sub_expr = '';
		$multi = preg_match('/.+(&|\|).+/', $expr);

		while (preg_match('/'.$ZBX_PREG_EXPESSION_FUNC_FORMAT.'/i', $expr, $arr)) {
			$arr[4] = zbx_strtolower($arr[4]);
			if (!isset($functions[$arr[4]])) {
				error(_('Incorrect function is used').'. ['.$expression['value'].']');
				return false;
			}
			$expr_array[$sub_expr_count]['eq'] = trim($arr[2]);
			$expr_array[$sub_expr_count]['regexp'] = zbx_strtolower($arr[4]).$arr[5];

			$sub_expr_count++;
			$expr = $arr[1];
		}

		if (empty($expr_array)) {
			error(_('Incorrect trigger expression').'. ['.$expression['value'].']');
			return false;
		}

		$expr_array[$sub_expr_count-1]['eq'] = '';

		$sub_eq = '';
		if ($multi > 0) {
			$sub_eq = $eq_global;
		}

		foreach ($expr_array as $id => $expr) {
			if ($multi > 0) {
				$sub_expr = $expr['eq'].'({'.$prefix.$expr['regexp'].'})'.$sub_eq.$sub_expr;
			}
			else {
				$sub_expr = $expr['eq'].'{'.$prefix.$expr['regexp'].'}'.$sub_eq.$sub_expr;
			}
		}

		if ($multi > 0) {
			$complite_expr .= '('.$sub_expr.')';
		}
		else {
			$complite_expr .= '(('.$sub_expr.')'.$eq_global.')';
		}
	}

	if (($cexpor > 1) & ($startpos >= 0)) {
		$head = substr($complite_expr, 0, $startpos);
		$tail = substr($complite_expr, $startpos);
		$complite_expr = $head.'('.$tail.')';
	}

	return $complite_expr;
}

/********************************************************************************
 *																				*
 * Purpose: Translate {10}>10 to something like localhost:procload.last(0)>10	*
 *																				*
 * Comments: !!! Don't forget sync code with C !!!								*
 *																				*
 *******************************************************************************/
function explode_exp($expression, $html = false, $resolve_macro = false, $src_host = null, $dst_host = null) {
	$functionid = '';
	$macros = '';
	$exp = !$html ? '' : array();
	$trigger = array();
	$state = '';

	for ($i = 0, $max = zbx_strlen($expression); $i < $max; $i++) {
		if ($expression[$i] == '{' && ($expression[$i+1] == '$')) {
			$functionid = '';
			$macros = '';
			$state = 'MACROS';
		}
		elseif ($expression[$i] == '{') {
			$functionid = '';
			$state = 'FUNCTIONID';
			continue;
		}

		if ($expression[$i] == '}') {
			if ($state == 'MACROS') {
				$macros .= '}';
				if ($resolve_macro) {
					$function_data['expression'] = $macros;
					$function_data = API::UserMacro()->resolveTrigger($function_data);
					$macros = $function_data['expression'];
				}

				if ($html) {
					array_push($exp, $macros);
				}
				else {
					$exp .= $macros;
				}
				$macros = '';
				$state = '';
				continue;
			}

			$state = '';
			$sql = 'SELECT h.host,i.itemid,i.key_,f.function,f.triggerid,f.parameter,i.itemid,i.status,i.type,i.flags'.
					' FROM items i,functions f,hosts h'.
					' WHERE f.functionid='.$functionid.
						' AND i.itemid=f.itemid'.
						' AND h.hostid=i.hostid';

			if ($functionid == 'TRIGGER.VALUE') {
				if (!$html) {
					$exp .= '{'.$functionid.'}';
				}
				else {
					array_push($exp, '{'.$functionid.'}');
				}
			}
			elseif (is_numeric($functionid) && $function_data = DBfetch(DBselect($sql))) {
				if ($resolve_macro) {
					$trigger = $function_data;
					$function_data = API::UserMacro()->resolveItem($function_data);
					$function_data['expression'] = $function_data['parameter'];
					$function_data = API::UserMacro()->resolveTrigger($function_data);
					$function_data['parameter'] = $function_data['expression'];
				}

				if (!is_null($src_host) && !is_null($dst_host) && strcmp($src_host, $function_data['host']) == 0) {
					$function_data['host'] = $dst_host;
				}

				if (!$html) {
					$exp .= '{'.$function_data['host'].':'.$function_data['key_'].'.'.$function_data['function'].'('.$function_data['parameter'].')}';
				}
				else {
					$style = $function_data['status'] == ITEM_STATUS_DISABLED ? 'disabled' : 'unknown';
					if ($function_data['status'] == ITEM_STATUS_ACTIVE) {
						$style = 'enabled';
					}

					if ($function_data['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $function_data['type'] == ITEM_TYPE_HTTPTEST) {
						$link = new CSpan($function_data['host'].':'.$function_data['key_'], $style);
					}
					elseif ($function_data['flags'] == ZBX_FLAG_DISCOVERY_CHILD) {
						$link = new CLink($function_data['host'].':'.$function_data['key_'],
							'disc_prototypes.php?form=update&itemid='.$function_data['itemid'].'&parent_discoveryid='.
							$trigger['discoveryRuleid'].'&switch_node='.id2nodeid($function_data['itemid']), $style);
					}
					else {
						$link = new CLink($function_data['host'].':'.$function_data['key_'],
							'items.php?form=update&itemid='.$function_data['itemid'].'&switch_node='.id2nodeid($function_data['itemid']), $style);
					}
					array_push($exp, array('{', $link,'.', bold($function_data['function'].'('), $function_data['parameter'], bold(')'), '}'));
				}
			}
			else {
				if ($html) {
					array_push($exp, new CSpan('*ERROR*', 'on'));
				}
				else {
					$exp .= '*ERROR*';
				}
			}
			continue;
		}

		if ($state == 'FUNCTIONID') {
			$functionid = $functionid.$expression[$i];
			continue;
		}
		elseif ($state == 'MACROS') {
			$macros = $macros.$expression[$i];
			continue;
		}

		if ($html) {
			array_push($exp, $expression[$i]);
		}
		else {
			$exp .= $expression[$i];
		}
	}

	return $exp;
}

/**
 * Translate {10}>10 to something like {localhost:system.cpu.load.last(0)}>10.
 *
 * @param $trigger
 * @param bool $html
 * @return array|string
 */
function triggerExpression($trigger, $html = false) {
	$expression = $trigger['expression'];
	$functionid = '';
	$macros = '';
	$exp = $html ? array() : '';
	$state = '';

	for ($i = 0, $len = strlen($expression); $i < $len; $i++) {
		if ($expression[$i] == '{' && $expression[$i+1] == '$') {
			$functionid = '';
			$macros = '';
			$state = 'MACROS';
		}
		elseif ($expression[$i] == '{') {
			$functionid = '';
			$state = 'FUNCTIONID';
			continue;
		}

		if ($expression[$i] == '}') {
			if ($state == 'MACROS') {
				$macros .= '}';

				if ($html) {
					array_push($exp, $macros);
				}
				else {
					$exp .= $macros;
				}

				$macros = '';
				$state = '';
				continue;
			}

			$state = '';

			if ($functionid == 'TRIGGER.VALUE') {
				if (!$html) {
					$exp .= '{'.$functionid.'}';
				}
				else {
					array_push($exp, '{'.$functionid.'}');
				}
			}
			elseif (is_numeric($functionid) && isset($trigger['functions'][$functionid])) {
				$function_data = $trigger['functions'][$functionid];
				$function_data += $trigger['items'][$function_data['itemid']];
				$function_data += $trigger['hosts'][$function_data['hostid']];

				if (!$html) {
					$exp .= '{'.$function_data['host'].':'.$function_data['key_'].'.'.$function_data['function'].'('.$function_data['parameter'].')}';
				}
				else {
					$style = ($function_data['status'] == ITEM_STATUS_DISABLED) ? 'disabled' : 'unknown';
					if ($function_data['status'] == ITEM_STATUS_ACTIVE) {
						$style = 'enabled';
					}

					if ($function_data['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $function_data['type'] == ITEM_TYPE_HTTPTEST) {
						$link = new CSpan($function_data['host'].':'.$function_data['key_'], $style);
					}
					elseif ($function_data['flags'] == ZBX_FLAG_DISCOVERY_CHILD) {
						$link = new CLink($function_data['host'].':'.$function_data['key_'],
							'disc_prototypes.php?form=update&itemid='.$function_data['itemid'].'&parent_discoveryid='.
							$trigger['discoveryRuleid'], $style);
					}
					else {
						$link = new CLink($function_data['host'].':'.$function_data['key_'],
							'items.php?form=update&itemid='.$function_data['itemid'], $style);
					}
					array_push($exp, array('{', $link, '.', bold($function_data['function'].'('), $function_data['parameter'], bold(')'), '}'));
				}
			}
			else {
				if ($html) {
					array_push($exp, new CSpan('*ERROR*', 'on'));
				}
				else {
					$exp .= '*ERROR*';
				}
			}
			continue;
		}

		if ($state == 'FUNCTIONID') {
			$functionid = $functionid.$expression[$i];
			continue;
		}
		elseif ($state == 'MACROS') {
			$macros = $macros.$expression[$i];
			continue;
		}

		if ($html) {
			array_push($exp, $expression[$i]);
		}
		else {
			$exp .= $expression[$i];
		}
	}

	return $exp;
}

/**
 * Implodes expression, replaces names and keys with IDs
 *
 * Fro example: localhost:procload.last(0)>10 will translated to {12}>10 and created database representation.
 *
 * @throws Exception if error occureed
 *
 * @param string $expression Full expression with host names and item keys
 * @param numeric $triggerid
 * @param array optional $hostnames Reference to array which will be filled with unique visible host names.
 *
 * @return string Imploded expression (names and keys replaced by IDs)
 */
function implode_exp($expression, $triggerid, &$hostnames = array()) {
	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		throw new Exception($expressionData->error);
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
					'SELECT i.itemid,i.value_type,h.name'.
					' FROM items i,hosts h'.
					' WHERE i.key_='.zbx_dbstr($exprPart['item']).
						' AND'.DBcondition('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED, ZBX_FLAG_DISCOVERY_CHILD)).
						' AND h.host='.zbx_dbstr($exprPart['host']).
						' AND h.hostid=i.hostid'.
						' AND '.DBin_node('i.itemid')
			);
			if ($row = DBfetch($result)) {
				$hostnames[] = $row['name'];
				$items[$exprPart['host']][$exprPart['item']] =
						array('itemid' => $row['itemid'], 'valueType' => $row['value_type']);
			}
			else {
				throw new Exception(_s('Incorrect item key "%1$s" provided for trigger expression on "%2$s".',
						$exprPart['item'], $exprPart['host']));
			}
		}

		if (!$triggerFunctionValidator->validate(array('functionName' => $exprPart['functionName'],
				'functionParamList' => $exprPart['functionParamList'],
				'valueType' => $items[$exprPart['host']][$exprPart['item']]['valueType']))) {
			throw new Exception($triggerFunctionValidator->getError());
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

	$offset = 0;
	foreach ($expressionData->expressions as $exprPart) {
		$newExpression = '{'.$newFunctions[$exprPart['expression']].'}';
		$lengthOld = strlen($exprPart['expression']);
		$lengthNew = strlen($newExpression);
		$expression = substr_replace($expression, $newExpression, $exprPart['pos'] + $offset, $lengthOld);
		$offset += $lengthNew - $lengthOld;
	}

	$hostnames = array_unique($hostnames);

	return $expression;
}

function updateTriggerValueToUnknownByHostId($hostids) {
	zbx_value2array($hostids);
	$triggerids = array();

	$result = DBselect(
		'SELECT DISTINCT t.triggerid'.
		' FROM hosts h,items i,functions f,triggers t'.
		' WHERE h.hostid=i.hostid'.
			' AND i.itemid=f.itemid'.
			' AND f.triggerid=t.triggerid'.
			' AND '.DBcondition('h.hostid', $hostids).
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND t.value_flags='.TRIGGER_VALUE_FLAG_NORMAL
	);
	while ($row = DBfetch($result)) {
		$triggerids[] = $row['triggerid'];
	}

	if (!empty($triggerids)) {
		DB::update('triggers', array(
			'values' => array(
				'value_flags' => TRIGGER_VALUE_FLAG_UNKNOWN,
				'error' => _s('Host status became "%s"', _('Not monitored'))
			),
			'where' => array('triggerid' => $triggerids)
		));

		addUnknownEvent($triggerids);
	}

	return true;
}

/**
 * Create unknown type event for given triggers.
 *
 * @param int|array $triggerids triggers to whom add unknown type event
 *
 * @return array returns created event ids
 */
function addUnknownEvent($triggerids) {
	zbx_value2array($triggerids);

	$triggers = API::Trigger()->get(array(
		'triggerids' => $triggerids,
		'output' => array('value'),
		'preservekeys' => true
	));

	$eventids = array();
	foreach ($triggerids as $triggerid) {
		$event = array(
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'objectid' => $triggerid,
			'clock' => time(),
			'value' => TRIGGER_VALUE_UNKNOWN,
			'acknowledged' => 0
		);

		// check if trigger exist in DB
		if (isset($triggers[$event['objectid']])) {
			if ($event['value'] != $triggers[$event['objectid']]['value']) {
				$eventid = get_dbid('events', 'eventid');

				$sql = 'INSERT INTO events (eventid,source,object,objectid,clock,value,acknowledged) '.
						'VALUES ('.$eventid.','.$event['source'].','.$event['object'].','.$event['objectid'].','.
									$event['clock'].','.$event['value'].','.$event['acknowledged'].')';
				if (!DBexecute($sql)) {
					throw new Exception();
				}

				$eventids[$eventid] = $eventid;
			}
		}
	}

	return $eventids;
}

function check_right_on_trigger_by_expression($permission, $expression) {
	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}
	$expressionHosts = $expressionData->getHosts();

	$hosts = API::Host()->get(array(
		'filter' => array('host' => $expressionHosts),
		'editable' => ($permission == PERM_READ_WRITE) ? 1 : null,
		'output' => array('hostid', 'host'),
		'templated_hosts' => true,
		'preservekeys' => true
	));
	$hosts = zbx_toHash($hosts, 'host');

	foreach ($expressionHosts as $host) {
		if (!isset($hosts[$host])) {
			error(_s('Incorrect trigger expression. Host "%1$s" does not exist or you have no access to this host.', $host));
			return false;
		}
	}

	return true;
}

function replace_template_dependencies($deps, $hostid) {
	foreach ($deps as $id => $val) {
		$sql = 'SELECT t.triggerid'.
				' FROM triggers t,functions f,items i'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND t.templateid='.$val.
					' AND i.hostid='.$hostid;
		if ($db_new_dep = DBfetch(DBselect($sql))) {
			$deps[$id] = $db_new_dep['triggerid'];
		}
	}

	return $deps;
}

/**
 * Creates and returns the trigger overview table for the given hosts.
 *
 * Possible $view_style values:
 * - STYLE_TOP
 * - STYLE_LEFT
 *
 * @param string|array $hostids
 * @param int $view_style	    table display style: either hosts on top, or host on the left side
 * @param string $screenId		the ID of the screen, that contains the trigger overview table
 *
 * @return CTableInfo
 */
function get_triggers_overview($hostids, $view_style = null, $screenId = null) {
	if (is_null($view_style)) {
		$view_style = CProfile::get('web.overview.view.style', STYLE_TOP);
	}

	// get triggers
	$dbTriggers = API::Trigger()->get(array(
		'hostids' => $hostids,
		'monitored' => true,
		'skipDependent' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name'),
		'sortfield' => 'description'
	));

	// get hosts
	$hostids = array();
	foreach ($dbTriggers as $trigger) {
		$hostids[] = $trigger['hosts'][0]['hostid'];
	}
	$hosts = API::Host()->get(array(
		'output' => array('name', 'hostid'),
		'hostids' => $hostids,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectInventory' => true,
		'preservekeys' => true
	));
	$hostScripts = API::Script()->getScriptsByHosts(zbx_objectValues($hosts, 'hostid'));
	foreach ($hostScripts as $hostid => $scripts) {
		$hosts[$hostid]['scripts'] = $scripts;
	}

	$triggers = array();
	$hostNames = array();
	foreach ($dbTriggers as $trigger) {
		$trigger['host'] = $trigger['hosts'][0]['name'];
		$trigger['hostid'] = $trigger['hosts'][0]['hostid'];
		$trigger['host'] = get_node_name_by_elid($trigger['hostid'], null, ': ').$trigger['host'];
		$trigger['description'] = CTriggerHelper::expandReferenceMacros($trigger);

		$hostNames[$trigger['hostid']] = $trigger['host'];

		// a little tricky check for attempt to overwrite active trigger (value=1) with
		// inactive or active trigger with lower priority.
		if (!isset($triggers[$trigger['description']][$trigger['host']])
				|| (($triggers[$trigger['description']][$trigger['host']]['value'] == TRIGGER_VALUE_FALSE && $trigger['value'] == TRIGGER_VALUE_TRUE)
					|| (($triggers[$trigger['description']][$trigger['host']]['value'] == TRIGGER_VALUE_FALSE || $trigger['value'] == TRIGGER_VALUE_TRUE)
						&& $trigger['priority'] > $triggers[$trigger['description']][$trigger['host']]['priority']))) {
			$triggers[$trigger['description']][$trigger['host']] = array(
				'hostid'	=> $trigger['hostid'],
				'triggerid'	=> $trigger['triggerid'],
				'value'		=> $trigger['value'],
				'lastchange'=> $trigger['lastchange'],
				'priority'	=> $trigger['priority']
			);
		}
	}

	$triggerTable = new CTableInfo(_('No triggers defined.'));
	if (empty($hostNames)) {
		return $triggerTable;
	}
	$triggerTable->makeVerticalRotation();
	order_result($hostNames);

	if ($view_style == STYLE_TOP) {
		// header
		$header = array(new CCol(_('Triggers'), 'center'));
		foreach ($hostNames as $hostName) {
			$header[] = new CCol($hostName, 'vertical_rotation');
		}
		$triggerTable->setHeader($header, 'vertical_header');

		// data
		foreach ($triggers as $description => $triggerHosts) {
			$tableColumns = array(nbsp($description));
			foreach ($hostNames as $hostid => $hostName) {
				array_push($tableColumns, get_trigger_overview_cells($triggerHosts, $hostName, $screenId));
			}
			$triggerTable->addRow($tableColumns);
		}
	}
	else {
		// header
		$header = array(new CCol(_('Host'), 'center'));
		foreach ($triggers as $description => $triggerHosts) {
			$header[] = new CCol($description, 'vertical_rotation');
		}
		$triggerTable->setHeader($header, 'vertical_header');

		// data
		foreach ($hostNames as $hostid => $hostName) {
			$host = $hosts[$hostid];

			// host js link
			$hostSpan = new CSpan(nbsp($hostName), 'link_menu menu-host');
			$hostSpan->setAttribute('data-menu', hostMenuData($host, ($hostScripts[$host['hostid']]) ? $hostScripts[$host['hostid']] : array()));

			$tableColumns = array($hostSpan);
			foreach ($triggers as $triggerHosts) {
				array_push($tableColumns, get_trigger_overview_cells($triggerHosts, $hostName, $screenId));
			}
			$triggerTable->addRow($tableColumns);
		}
	}

	return $triggerTable;
}

/**
 * Creates and returns a trigger status cell for the trigger overview table.
 *
 * @see get_triggers_overview()
 *
 * @param array $triggerHosts	an array with the data about the trigger for each host
 * @param string $hostName		the name of the cells corresponding host
 * @param string $screenId
 *
 * @return CCol
 */
function get_trigger_overview_cells($triggerHosts, $hostName, $screenId = null) {
	$ack = null;
	$css_class = null;
	$desc = array();
	$config = select_config(); // for how long triggers should blink on status change (set by user in administration->general)

	if (isset($triggerHosts[$hostName])) {
		switch ($triggerHosts[$hostName]['value']) {
			case TRIGGER_VALUE_TRUE:
				$css_class = getSeverityStyle($triggerHosts[$hostName]['priority']);
				$ack = null;

				if ($config['event_ack_enable'] == 1) {
					$event = get_last_event_by_triggerid($triggerHosts[$hostName]['triggerid']);
					if ($event) {
						if ($screenId) {
							global $page;
							$ack_menu = array(_('Acknowledge'), 'acknow.php?eventid='.$event['eventid'].'&screenid='.$screenId.'&backurl='.$page['file']);
						}
						else {
							$ack_menu = array(_('Acknowledge'), 'acknow.php?eventid='.$event['eventid'].'&backurl=overview.php', array('tw' => '_blank'));
						}

						if ($event['acknowledged'] == 1) {
							$ack = new CImg('images/general/tick.png', 'ack');
						}
					}
				}
				break;
			case TRIGGER_VALUE_FALSE:
				$css_class = 'normal';
				break;
			default:
				$css_class = 'trigger_unknown';
		}
		$style = 'cursor: pointer; ';

		// set blinking gif as background if trigger age is less then $config['blink_period']
		if ($config['blink_period'] > 0 && time() - $triggerHosts[$hostName]['lastchange'] < $config['blink_period']) {
			$style .= 'background-image: url(images/gradients/blink.gif); background-position: top left; background-repeat: repeat;';
		}

		unset($item_menu);
		$tr_ov_menu = array(
			// name, url, (target [tw], statusbar [sb]), css, submenu
			array(
				_('Trigger'),
				null,
				null,
				array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader'))),
				array(_('Events'), 'events.php?triggerid='.$triggerHosts[$hostName]['triggerid'], array('tw' => '_blank')
			)
		);

		if (isset($ack_menu)) {
			$tr_ov_menu[] = $ack_menu;
		}

		$dbItems = DBselect(
			'SELECT DISTINCT i.itemid,i.name,i.key_,i.value_type'.
			' FROM items i,functions f'.
			' WHERE f.itemid=i.itemid'.
				' AND f.triggerid='.$triggerHosts[$hostName]['triggerid']
		);
		while ($item = DBfetch($dbItems)) {
			$description = itemName($item);
			switch ($item['value_type']) {
				case ITEM_VALUE_TYPE_UINT64:
				case ITEM_VALUE_TYPE_FLOAT:
					$action = 'showgraph';
					$status_bar = _('Show graph of item').' \''.$description.'\'';
					break;
				case ITEM_VALUE_TYPE_LOG:
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
				default:
					$action = 'showlatest';
					$status_bar = _('Show values of item').' \''.$description.'\'';
					break;
			}

			if (zbx_strlen($description) > 25) {
				$description = zbx_substr($description, 0, 22).'...';
			}

			$item_menu[$action][] = array(
				$description,
				'history.php?action='.$action.'&itemid='.$item['itemid'].'&period=3600',
				array('tw' => '', 'sb' => $status_bar)
			);
		}

		if (isset($item_menu['showgraph'])) {
			$tr_ov_menu[] = array(
				_('Graphs'),
				null,
				null,
				array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader'))
			);
			$tr_ov_menu = array_merge($tr_ov_menu, $item_menu['showgraph']);
		}

		if (isset($item_menu['showlatest'])) {
			$tr_ov_menu[] = array(
				_('Values'),
				null,
				null,
				array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader'))
			);
			$tr_ov_menu = array_merge($tr_ov_menu, $item_menu['showlatest']);
		}
		unset($item_menu);

		// dependency: triggers on which depends this
		$triggerid = !empty($triggerHosts[$hostName]['triggerid']) ? $triggerHosts[$hostName]['triggerid'] : 0;

		$dep_table = new CTableInfo();
		$dep_table->setAttribute('style', 'width: 200px;');
		$dep_table->addRow(bold(_('Depends on').':'));

		$dependency = false;
		$dep_res = DBselect('SELECT td.* FROM trigger_depends td WHERE td.triggerid_down='.$triggerid);
		while ($dep_row = DBfetch($dep_res)) {
			$dep_table->addRow(SPACE.'-'.SPACE.CTriggerHelper::expandDescriptionById($dep_row['triggerid_up']));
			$dependency = true;
		}

		if ($dependency) {
			$img = new Cimg('images/general/arrow_down2.png', 'DEP_DOWN');
			$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
			$img->setHint($dep_table, '', '', false);
			array_push($desc, $img);
		}
		unset($img, $dep_table, $dependency);

		// triggers that depend on this
		$dep_table = new CTableInfo();
		$dep_table->setAttribute('style', 'width: 200px;');
		$dep_table->addRow(bold(_('Dependent').':'));

		$dependency = false;
		$dep_res = DBselect('SELECT td.* FROM trigger_depends td WHERE td.triggerid_up='.$triggerid);
		while ($dep_row = DBfetch($dep_res)) {
			$dep_table->addRow(SPACE.'-'.SPACE.CTriggerHelper::expandDescriptionById($dep_row['triggerid_down']));
			$dependency = true;
		}

		if ($dependency) {
			$img = new Cimg('images/general/arrow_up2.png', 'DEP_UP');
			$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
			$img->setHint($dep_table, '', '', false);
			array_push($desc, $img);
		}
		unset($img, $dep_table, $dependency);
	}

	if ((is_array($desc) && count($desc) > 0) || $ack) {
		$tableColumn = new CCol(array($desc, $ack), $css_class.' hosts');
	}
	else {
		$tableColumn = new CCol(SPACE, $css_class.' hosts');
	}
	if (isset($style)) {
		$tableColumn->setAttribute('style', $style);
	}

	if (isset($tr_ov_menu)) {
		$tr_ov_menu = new CPUMenu($tr_ov_menu, 170);
		$tableColumn->onClick($tr_ov_menu->getOnActionJS());
		$tableColumn->addAction('onmouseover', 'jQuery(this).css({border: "1px dotted #0C0CF0", padding: "0 2px"})');
		$tableColumn->addAction('onmouseout', 'jQuery(this).css({border: "", padding: "1px 3px"})');
	}

	return $tableColumn;
}

function calculate_availability($triggerid, $period_start, $period_end) {
	$start_value = -1;
	if ($period_start > 0 && $period_start <= time()) {
		$sql = 'SELECT e.eventid,e.value'.
				' FROM events e'.
				' WHERE e.objectid='.$triggerid.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.clock<'.$period_start.
				' ORDER BY e.eventid DESC';
		if ($row = DBfetch(DBselect($sql, 1))) {
			$start_value = $row['value'];
			$min = $period_start;
		}
	}

	$sql = 'SELECT COUNT(e.eventid) AS cnt,MIN(e.clock) AS min_clock,MAX(e.clock) AS max_clock'.
			' FROM events e'.
			' WHERE e.objectid='.$triggerid.
				' AND e.object='.EVENT_OBJECT_TRIGGER;
	if ($period_start != 0) {
		$sql .= ' AND clock>='.$period_start;
	}
	if ($period_end != 0) {
		$sql .= ' AND clock<='.$period_end;
	}

	$db_events = DBfetch(DBselect($sql));
	if ($db_events['cnt'] > 0) {
		if (!isset($min)) {
			$min = $db_events['min_clock'];
		}
		$max = $db_events['max_clock'];
	}
	else {
		if ($period_start == 0 && $period_end == 0) {
			$max = time();
			$min = $max - SEC_PER_DAY;
		}
		else {
			$ret['true_time'] = 0;
			$ret['false_time'] = 0;
			$ret['unknown_time'] = 0;
			$ret['true'] = (TRIGGER_VALUE_TRUE == $start_value) ? 100 : 0;
			$ret['false'] = (TRIGGER_VALUE_FALSE == $start_value) ? 100 : 0;
			$ret['unknown'] = (TRIGGER_VALUE_UNKNOWN == $start_value || -1 == $start_value) ? 100 : 0;
			return $ret;
		}
	}

	$state = $start_value;
	$true_time = 0;
	$false_time = 0;
	$unknown_time = 0;
	$time = $min;
	if ($period_start == 0 && $period_end == 0) {
		$max = time();
	}
	if ($period_end == 0) {
		$period_end = $max;
	}

	$rows = 0;
	$db_events = DBselect(
		'SELECT e.eventid,e.clock,e.value'.
		' FROM events e'.
		' WHERE e.objectid='.$triggerid.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock BETWEEN '.$min.' AND '.$max.
		' ORDER BY e.eventid'
	);
	while ($row = DBfetch($db_events)) {
		$clock = $row['clock'];
		$value = $row['value'];

		$diff = $clock - $time;
		$time = $clock;

		if ($state == -1) {
			$state = $value;
			if ($state == 0) {
				$false_time += $diff;
			}
			if ($state == 1) {
				$true_time += $diff;
			}
			if ($state == 2) {
				$unknown_time += $diff;
			}
		}
		elseif ($state == 0) {
			$false_time += $diff;
			$state = $value;
		}
		elseif ($state == 1) {
			$true_time += $diff;
			$state = $value;
		}
		elseif ($state == 2) {
			$unknown_time += $diff;
			$state = $value;
		}
		$rows++;
	}

	if ($rows == 0) {
		$trigger = get_trigger_by_triggerid($triggerid);
		$state = $trigger['value'];
	}

	if ($state == TRIGGER_VALUE_FALSE) {
		$false_time = $false_time + $period_end - $time;
	}
	elseif ($state == TRIGGER_VALUE_TRUE) {
		$true_time = $true_time + $period_end - $time;
	}
	elseif ($state == TRIGGER_VALUE_UNKNOWN) {
		$unknown_time = $unknown_time + $period_end - $time;
	}
	$total_time = $true_time + $false_time + $unknown_time;

	if ($total_time == 0) {
		$ret['true_time'] = 0;
		$ret['false_time'] = 0;
		$ret['unknown_time'] = 0;
		$ret['true'] = 0;
		$ret['false'] = 0;
		$ret['unknown'] = 100;
	}
	else {
		$ret['true_time'] = $true_time;
		$ret['false_time'] = $false_time;
		$ret['unknown_time'] = $unknown_time;
		$ret['true'] = (100 * $true_time) / $total_time;
		$ret['false'] = (100 * $false_time) / $total_time;
		$ret['unknown'] = (100 * $unknown_time) / $total_time;
	}

	return $ret;
}

function get_triggers_unacknowledged($db_element, $count_problems = null, $ack = false) {
	$elements = array(
		'hosts' => array(),
		'hosts_groups' => array(),
		'triggers' => array()
	);

	get_map_elements($db_element, $elements);
	if (empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])) {
		return 0;
	}

	$config = select_config();

	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored' => true,
		'countOutput' => true,
		'filter' => array(),
		'limit' => $config['search_limit'] + 1
	);

	if ($ack) {
		$options['withAcknowledgedEvents'] = 1;
	}
	else {
		$options['withUnacknowledgedEvents'] = 1;
	}

	if ($count_problems) {
		$options['filter']['value'] = TRIGGER_VALUE_TRUE;
	}
	if (!empty($elements['hosts_groups'])) {
		$options['groupids'] = array_unique($elements['hosts_groups']);
	}
	if (!empty($elements['hosts'])) {
		$options['hostids'] = array_unique($elements['hosts']);
	}
	if (!empty($elements['triggers'])) {
		$options['triggerids'] = array_unique($elements['triggers']);
	}

	return API::Trigger()->get($options);
}

function make_trigger_details($trigger) {
	$table = new CTableInfo();

	if (is_show_all_nodes()) {
		$table->addRow(array(_('Node'), get_node_name_by_elid($trigger['triggerid'])));
	}
	$expression = explode_exp($trigger['expression'], true, true);

	$host = API::Host()->get(array(
		'output' => array('name', 'hostid'),
		'hostids' => $trigger['hosts'][0]['hostid'],
		'selectAppllications' => API_OUTPUT_EXTEND,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectInventory' => true,
		'preservekeys' => true
	));
	$host = reset($host);

	$hostScripts = API::Script()->getScriptsByHosts($host['hostid']);

	// host js link
	$hostSpan = new CSpan($host['name'], 'link_menu menu-host');
	$scripts = ($hostScripts[$host['hostid']]) ? $hostScripts[$host['hostid']] : array();
	$hostSpan->attr('data-menu', hostMenuData($host, $scripts));

	// get visible name of the first host
	$table->addRow(array(_('Host'), $hostSpan));
	$table->addRow(array(_('Trigger'), CTriggerHelper::expandDescription($trigger)));
	$table->addRow(array(_('Severity'), getSeverityCell($trigger['priority'])));
	$table->addRow(array(_('Expression'), $expression));
	$table->addRow(array(_('Event generation'), _('Normal').(TRIGGER_MULT_EVENT_ENABLED == $trigger['type'] ? SPACE.'+'.SPACE._('Multiple PROBLEM events') : '')));
	$table->addRow(array(_('Disabled'), (TRIGGER_STATUS_ENABLED == $trigger['status'] ? new CCol(_('No'), 'off') : new CCol(_('Yes'), 'on'))));

	return $table;
}

/**
 * Analyze an expression and returns expression html tree
 *
 * @param string $expression
 *
 * @return array
 */
function analyze_expression($expression) {
	if (empty($expression)) {
		return array('', null);
	}

	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}

	$element = array();
	getExpressionTree($expressionData, 0, strlen($expressionData->expression) - 1, $element);
	$expressionTree[] = $element;

	$next = array();
	$letterNum = 0;
	return buildExpressionHtmlTree($expressionTree, $next, $letterNum);
}

function buildExpressionHtmlTree(array $expressionTree, array &$next, &$letterNum, $level = 0, $operand = null) {
	$treeList = array();
	$outline = '';

	end($expressionTree);
	$last_key = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operand':
				$next[$level] = ($key != $last_key);
				$expr = expressionLevelDraw($next, $level);
				$expr[] = SPACE;
				$expr[] = italic($element['operand'] == '&' ? _('AND') : _('OR'));
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
				$next[$level] = ($key != $last_key);

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
			EXPRESSION_VALUE_TYPE_UNKNOWN => _('Unknown variable type, testing not available'),
			EXPRESSION_HOST_UNKNOWN => _('Unknown host, no such host present in system'),
			EXPRESSION_HOST_ITEM_UNKNOWN => _('Unknown host item, no such item in selected host'),
			EXPRESSION_NOT_A_MACRO_ERROR => _('Given expression is not a macro'),
			EXPRESSION_FUNCTION_UNKNOWN => _('Incorrect function is used')
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
 * @param integer $level
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
 * @param integer $start
 * @param integer $end
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
 * @param CTriggerExpression $expressionData
 * @param integer $start
 * @param integer $end
 * @param array &$expressionTree
 */
function getExpressionTree(CTriggerExpression $expressionData, $start, $end, array &$expressionTree) {
	foreach (array('|', '&') as $operand) {
		$operand_found = false;
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

						$element = array();
						getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum, $element);
						$expressions[] = $element;
						$openSymbolNum = $i + 1;
						$operand_found = true;
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

		if ($operand_found) {
			$expressionElementsNum = getExpressionElementsNum($expressionData, $openSymbolNum, $closeSymbolNum);
			if ($expressionElementsNum == 1 && $openSymbolNum == $lParentheses && $closeSymbolNum == $rParentheses) {
				$openSymbolNum++;
				$closeSymbolNum--;
			}

			$element = array();
			getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum, $element);
			$expressions[] = $element;

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

				getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum, $expressionTree);
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
function remake_expression($expression, $expressionId, $action, $newExpression) {
	if (empty($expression)) {
		return false;
	}

	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}

	$element = array();
	getExpressionTree($expressionData, 0, strlen($expressionData->expression) - 1, $element);
	$expressionTree[] = $element;

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
 * @param integer $level        parameter only for recursive call
 * @param string $operand       parameter only for recursive call
 *
 * @return string
 */
function makeExpression(array $expressionTree, $level = 0, $operand = null)
{
	$expression = '';

	end($expressionTree);
	$last_key = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operand':
				$subExpression = makeExpression($element['elements'], $level + 1, $element['operand']);

				$expression .= ($level == 0) ? $subExpression : '('.$subExpression.')';
				if ($operand !== null && $key != $last_key) {
					$expression .= ' '.$operand.' ';
				}
				break;
			case 'expression':
				$expression .= $element['expression'];
				if ($operand !== null && $key != $last_key) {
					$expression .= ' '.$operand.' ';
				}
				break;
		}
	}
	return $expression;
}

function get_item_function_info($expr) {
	$value_type = array(
		ITEM_VALUE_TYPE_UINT64	=> _('Numeric (integer 64bit)'),
		ITEM_VALUE_TYPE_FLOAT	=> _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR		=> _('Character'),
		ITEM_VALUE_TYPE_LOG		=> _('Log'),
		ITEM_VALUE_TYPE_TEXT	=> _('Text')
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
		'count' =>		array('value_type' => _('Numeric (integer 64bit)'), 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'date' =>		array('value_type' => 'YYYYMMDD',	'type' => T_ZBX_INT,			'validation' => '{}>=19700101&&{}<=99991231'),
		'dayofmonth' =>	array('value_type' => '1-31',		'type' => T_ZBX_INT,			'validation' => '{}>=1&&{}<=31'),
		'dayofweek' =>	array('value_type' => '1-7',		'type' => T_ZBX_INT,			'validation' => IN('1,2,3,4,5,6,7')),
		'delta' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'diff' =>		array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'fuzzytime' =>	array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'iregexp' =>	array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'last' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'logeventid' =>	array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'logseverity' =>array('value_type' => _('Numeric (integer 64bit)'), 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'logsource' =>	array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'max' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'min' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'nodata' =>		array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'now' =>		array('value_type' => _('Numeric (integer 64bit)'), 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'prev' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'regexp' =>		array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'str' =>		array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'strlen' =>		array('value_type' => _('Numeric (integer 64bit)'), 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'sum' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'time' =>		array('value_type' => 'HHMMSS',		'type' => T_ZBX_INT,			'validation' => 'zbx_strlen({})==6')
	);

	$expressionData = new CTriggerExpression();

	if ($expressionData->parse($expr)) {
		if (isset($expressionData->macros[0])) {
			$result = array(
				'value_type' => _('0 or 1'),
				'type' => T_ZBX_INT,
				'validation' => IN('0,1')
			);
		}
		elseif (isset($expressionData->usermacros[0])) {
			$result = array(
				'value_type' => _('0 or 1'),
				'type' => T_ZBX_INT,
				'validation' => NOT_EMPTY
			);
		}
		elseif (isset($expressionData->expressions[0])) {
			$exprPart = reset($expressionData->expressions);

			if (!isset($function_info[$exprPart['functionName']])) {
				return EXPRESSION_FUNCTION_UNKNOWN;
			}

			$hostFound = API::Host()->get(array(
				'filter' => array('host' => array($exprPart['host'])),
				'templated_hosts' => true
			));

			if (empty($hostFound)) {
				return EXPRESSION_HOST_UNKNOWN;
			}

			$itemFound = API::Item()->get(array(
				'hostids' => zbx_objectValues($hostFound, 'hostid'),
				'filter' => array(
					'key_' => array($exprPart['item']),
					'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED, ZBX_FLAG_DISCOVERY_CHILD)
				),
				'webitems' => true
			));
			if (empty($itemFound)) {
				return EXPRESSION_HOST_ITEM_UNKNOWN;
			}

			$result = $function_info[$exprPart['functionName']];

			if (is_array($result['value_type'])) {
				$value_type = null;
				$item_data = API::Item()->get(array(
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

function evalExpressionData($expression, $rplcts, $oct = false) {
	$result = false;

	$evStr = str_replace(array_keys($rplcts), array_values($rplcts), $expression);
	preg_match_all('/[0-9\.]+['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?/', $evStr, $arr, PREG_OFFSET_CAPTURE);
	for ($i = count($arr[0]) - 1; $i >= 0; $i--) {
		$evStr = substr_replace($evStr, convert($arr[0][$i][0]), $arr[0][$i][1], strlen($arr[0][$i][0]));
	}

	if (!preg_match("/^[0-9.\s=#()><+*\/&E|\-]+$/is", $evStr)) {
		return 'FALSE';
	}

	if ($oct) {
		$evStr = preg_replace('/([0-9]+)(\=|\#|\!=|\<|\>)([0-9]+)/', '((float)ltrim("$1","0") $2 (float)ltrim("$3","0"))', $evStr);
	}

	$switch = array('=' => '==', '#' => '!=', '&' => '&&', '|' => '||');
	$evStr = str_replace(array_keys($switch), array_values($switch), $evStr);

	eval('$result = ('.trim($evStr).');');

	return ($result === true || $result && $result != '-') ? 'TRUE' : 'FALSE';
}

/**
 * Resolve {TRIGGER.ID} macro in trigger url.
 * @param array $trigger trigger data with url and triggerid
 * @return string
 */
function resolveTriggerUrl($trigger) {
	return str_replace('{TRIGGER.ID}', $trigger['triggerid'], $trigger['url']);
}

function convert($value) {
	$value = trim($value);
	if (!preg_match('/(?P<value>[\-+]?[0-9]+[.]?[0-9]*)(?P<mult>['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)/', $value, $arr)) {
		return $value;
	}

	$value = $arr['value'];
	switch ($arr['mult']) {
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
