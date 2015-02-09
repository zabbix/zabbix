<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

/**
 *	Get trigger severity name by given state and configuration.
 *
 * @param int	 $severity		trigger severity
 * @param array  $config		array containing configuration parameters containing severity names
 *
 * @return string
 */
function getSeverityName($severity, array $config) {
	switch ($severity) {
		case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			return _($config['severity_name_0']);
		case TRIGGER_SEVERITY_INFORMATION:
			return _($config['severity_name_1']);
		case TRIGGER_SEVERITY_WARNING:
			return _($config['severity_name_2']);
		case TRIGGER_SEVERITY_AVERAGE:
			return _($config['severity_name_3']);
		case TRIGGER_SEVERITY_HIGH:
			return _($config['severity_name_4']);
		case TRIGGER_SEVERITY_DISASTER:
			return _($config['severity_name_5']);
		default:
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

/**
 * Returns HTML representation of trigger severity cell containing severity name and color.
 *
 * @param int	 $severity			trigger severity
 * @param array  $config			array of configuration parameters to get trigger severity name
 * @param string $text				trigger severity name
 * @param bool	 $forceNormal		true to return 'normal' class, false to return corresponding severity class
 *
 * @return CCol
 */
function getSeverityCell($severity, $config, $text = null, $forceNormal = false) {
	if ($text === null) {
		$text = CHtml::encode(getSeverityName($severity, $config));
	}

	return new CCol($text, getSeverityStyle($severity, !$forceNormal));
}

/**
 * Add color style and blinking to an object like CSpan or CDiv depending on trigger status
 * Settings and colors are kept in 'config' database table
 *
 * @param mixed $object             object like CSpan, CDiv, etc.
 * @param int $triggerValue         TRIGGER_VALUE_FALSE or TRIGGER_VALUE_TRUE
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

function trigger_value2str($value = null) {
	$triggerValues = array(
		TRIGGER_VALUE_FALSE => _('OK'),
		TRIGGER_VALUE_TRUE => _('PROBLEM')
	);

	if ($value === null) {
		return $triggerValues;
	}
	elseif (isset($triggerValues[$value])) {
		return $triggerValues[$value];
	}
	else {
		return _('Unknown');
	}
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
	$db_trigger = DBfetch(DBselect('SELECT t.* FROM triggers t WHERE t.triggerid='.zbx_dbstr($triggerid)));
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
			' AND '.dbConditionInt('f.triggerid', $triggerids)
	);
}

function get_triggers_by_hostid($hostid) {
	return DBselect(
		'SELECT DISTINCT t.*'.
		' FROM triggers t,functions f,items i'.
		' WHERE i.hostid='.zbx_dbstr($hostid).
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
		'selectDependencies' => array('triggerid')
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
				// get corresponding created trigger id
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

/********************************************************************************
 *                                                                              *
 * Purpose: Translate {10}>10 to something like                                 *
 * localhost:system.cpu.load.last(0)>10                                         *
 *                                                                              *
 * Comments: !!! Don't forget sync code with C !!!                              *
 *                                                                              *
 *******************************************************************************/
function explode_exp($expressionCompressed, $html = false, $resolveMacro = false, $sourceHost = null, $destinationHost = null) {
	$expressionExpanded = $html ? array() : '';
	$trigger = array();

	for ($i = 0, $state = '', $max = strlen($expressionCompressed); $i < $max; $i++) {
		if ($expressionCompressed[$i] == '{') {
			if ($expressionCompressed[$i + 1] == '$') {
				$state = 'USERMACRO';
				$userMacro = '';
			}
			elseif ($expressionCompressed[$i + 1] == '#') {
				$state = 'LLDMACRO';
				$lldMacro = '';
			}
			else {
				$state = 'FUNCTIONID';
				$functionId = '';

				continue;
			}
		}
		elseif ($expressionCompressed[$i] == '}') {
			if ($state == 'USERMACRO') {
				$state = '';
				$userMacro .= '}';

				if ($resolveMacro) {
					$functionData['expression'] = $userMacro;
					$userMacro = CMacrosResolverHelper::resolveTriggerExpressionUserMacro($functionData);
				}

				if ($html) {
					$expressionExpanded[] = $userMacro;
				}
				else {
					$expressionExpanded .= $userMacro;
				}

				continue;
			}
			elseif ($state == 'LLDMACRO') {
				$state = '';
				$lldMacro .= '}';

				if ($html) {
					$expressionExpanded[] = $lldMacro;
				}
				else {
					$expressionExpanded .= $lldMacro;
				}

				continue;
			}
			elseif ($functionId == 'TRIGGER.VALUE') {
				$state = '';

				if ($html) {
					$expressionExpanded[] = '{'.$functionId.'}';
				}
				else {
					$expressionExpanded .= '{'.$functionId.'}';
				}

				continue;
			}

			$state = '';
			$error = true;

			if (is_numeric($functionId)) {
				$functionData = DBfetch(DBselect(
					'SELECT h.host,h.hostid,i.itemid,i.key_,f.function,f.triggerid,f.parameter,i.itemid,i.status,i.type,i.flags'.
					' FROM items i,functions f,hosts h'.
					' WHERE f.functionid='.zbx_dbstr($functionId).
						' AND i.itemid=f.itemid'.
						' AND h.hostid=i.hostid'
				));

				if ($functionData) {
					$error = false;

					if ($resolveMacro) {
						$trigger = $functionData;

						// expand macros in item key
						$items = CMacrosResolverHelper::resolveItemKeys(array($functionData));
						$item = reset($items);

						$functionData['key_'] = $item['key_expanded'];

						// expand macros in function parameter
						$functionParameters = CMacrosResolverHelper::resolveFunctionParameters(array($functionData));
						$functionParameter = reset($functionParameters);
						$functionData['parameter'] = $functionParameter['parameter_expanded'];
					}

					if ($sourceHost !== null && $destinationHost !== null && $sourceHost === $functionData['host']) {
						$functionData['host'] = $destinationHost;
					}

					if ($html) {
						if ($functionData['status'] == ITEM_STATUS_DISABLED) {
							$style = 'disabled';
						}
						elseif ($functionData['status'] == ITEM_STATUS_ACTIVE) {
							$style = 'enabled';
						}
						else {
							$style = 'unknown';
						}

						if ($functionData['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $functionData['type'] == ITEM_TYPE_HTTPTEST) {
							$link = new CSpan($functionData['host'].':'.$functionData['key_'], $style);
						}
						elseif ($functionData['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
							$link = new CLink(
								$functionData['host'].':'.$functionData['key_'],
								'disc_prototypes.php?form=update&itemid='.$functionData['itemid'].'&parent_discoveryid='.
									$trigger['discoveryRuleid'],
								$style
							);
						}
						else {
							$link = new CLink(
								$functionData['host'].':'.$functionData['key_'],
								'items.php?form=update&itemid='.$functionData['itemid'],
								$style
							);
						}

						$expressionExpanded[] = array('{', $link,'.', bold($functionData['function'].'('), $functionData['parameter'], bold(')'), '}');
					}
					else {
						$expressionExpanded .= '{'.$functionData['host'].':'.$functionData['key_'].'.'.$functionData['function'].'('.$functionData['parameter'].')}';
					}
				}
			}

			if ($error) {
				if ($html) {
					$expressionExpanded[] = new CSpan('*ERROR*', 'on');
				}
				else {
					$expressionExpanded .= '*ERROR*';
				}
			}

			continue;
		}

		switch ($state) {
			case 'FUNCTIONID':
				$functionId .= $expressionCompressed[$i];
				break;

			case 'USERMACRO':
				$userMacro .= $expressionCompressed[$i];
				break;

			case 'LLDMACRO':
				$lldMacro .= $expressionCompressed[$i];
				break;

			default:
				if ($html) {
					$expressionExpanded[] = $expressionCompressed[$i];
				}
				else {
					$expressionExpanded .= $expressionCompressed[$i];
				}
		}
	}

	return $expressionExpanded;
}

/**
 * Translate {10}>10 to something like {localhost:system.cpu.load.last(0)}>10.
 *
 * @param array $trigger
 * @param bool  $html
 *
 * @return array|string
 */
function triggerExpression($trigger, $html = false) {
	$expression = $trigger['expression'];
	$exp = $html ? array() : '';

	for ($i = 0, $state = '', $len = strlen($expression); $i < $len; $i++) {
		if ($expression[$i] == '{') {
			if ($expression[$i + 1] == '$') {
				$usermacro = '';
				$state = 'USERMACRO';
			}
			elseif ($expression[$i + 1] == '#') {
				$lldmacro = '';
				$state = 'LLDMACRO';
			}
			else {
				$functionid = '';
				$state = 'FUNCTIONID';
				continue;
			}
		}
		elseif ($expression[$i] == '}') {
			if ($state == 'USERMACRO') {
				$usermacro .= '}';

				if ($html) {
					array_push($exp, $usermacro);
				}
				else {
					$exp .= $usermacro;
				}
			}
			elseif ($state == 'LLDMACRO') {
				$lldmacro .= '}';
				if ($html) {
					array_push($exp, $lldmacro);
				}
				else {
					$exp .= $lldmacro;
				}
			}
			elseif ($functionid == 'TRIGGER.VALUE') {
				if ($html) {
					array_push($exp, '{'.$functionid.'}');
				}
				else {
					$exp .= '{'.$functionid.'}';
				}
			}
			elseif (is_numeric($functionid) && isset($trigger['functions'][$functionid])) {
				$function_data = $trigger['functions'][$functionid];
				$function_data += $trigger['items'][$function_data['itemid']];
				$function_data += $trigger['hosts'][$function_data['hostid']];

				if ($html) {
					$style = ($function_data['status'] == ITEM_STATUS_DISABLED) ? 'disabled' : 'unknown';
					if ($function_data['status'] == ITEM_STATUS_ACTIVE) {
						$style = 'enabled';
					}

					if ($function_data['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $function_data['type'] == ITEM_TYPE_HTTPTEST) {
						$link = new CSpan($function_data['host'].':'.CHtml::encode($function_data['key_']), $style);
					}
					elseif ($function_data['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$link = new CLink($function_data['host'].':'.CHtml::encode($function_data['key_']),
							'disc_prototypes.php?form=update&itemid='.$function_data['itemid'].'&parent_discoveryid='.
							$trigger['discoveryRuleid'], $style);
					}
					else {
						$link = new CLink($function_data['host'].':'.CHtml::encode($function_data['key_']),
							'items.php?form=update&itemid='.$function_data['itemid'], $style);
					}
					array_push(
						$exp,
						array(
							'{',
							$link,
							'.',
							bold($function_data['function'].'('),
							CHtml::encode($function_data['parameter']),
							bold(')'),
							'}'
						)
					);
				}
				else {
					$exp .= '{'.$function_data['host'].':'.$function_data['key_'].'.'.$function_data['function'].'('.$function_data['parameter'].')}';
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

			$state = '';
			continue;
		}

		switch ($state) {
			case 'FUNCTIONID':
				$functionid .= $expression[$i];
				break;
			case 'USERMACRO':
				$usermacro .= $expression[$i];
				break;
			case 'LLDMACRO':
				$lldmacro .= $expression[$i];
				break;
			default:
				if ($html) {
					array_push($exp, $expression[$i]);
				}
				else {
					$exp .= $expression[$i];
				}
		}
	}

	return $exp;
}

/**
 * Implodes expression, replaces names and keys with IDs.
 *
 * For example: localhost:system.cpu.load.last(0)>10 will be translated to {12}>10 and created database representation.
 *
 * @throws Exception if error occurred
 *
 * @param string $expression Full expression with host names and item keys
 * @param numeric $triggerid
 * @param array optional $hostnames Reference to array which will be filled with unique visible host names.
 *
 * @return string Imploded expression (names and keys replaced by IDs)
 */
function implode_exp($expression, $triggerId, &$hostnames = array()) {
	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		throw new Exception($expressionData->error);
	}

	$newFunctions = array();
	$functions = array();
	$items = array();
	$triggerFunctionValidator = new CFunctionValidator();

	foreach ($expressionData->expressions as $exprPart) {
		if (isset($newFunctions[$exprPart['expression']])) {
			continue;
		}

		if (!isset($items[$exprPart['host']][$exprPart['item']])) {
			$result = DBselect(
				'SELECT i.itemid,i.value_type,h.name'.
				' FROM items i,hosts h'.
				' WHERE i.key_='.zbx_dbstr($exprPart['item']).
					' AND '.dbConditionInt('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED, ZBX_FLAG_DISCOVERY_PROTOTYPE)).
					' AND h.host='.zbx_dbstr($exprPart['host']).
					' AND h.hostid=i.hostid'
			);
			if ($row = DBfetch($result)) {
				$hostnames[] = $row['name'];
				$items[$exprPart['host']][$exprPart['item']] = array(
					'itemid' => $row['itemid'],
					'valueType' => $row['value_type']
				);
			}
			else {
				throw new Exception(_s('Incorrect item key "%1$s" provided for trigger expression on "%2$s".',
						$exprPart['item'], $exprPart['host']));
			}
		}

		if (!$triggerFunctionValidator->validate(array(
				'function' => $exprPart['function'],
				'functionName' => $exprPart['functionName'],
				'functionParamList' => $exprPart['functionParamList'],
				'valueType' => $items[$exprPart['host']][$exprPart['item']]['valueType']))) {
			throw new Exception($triggerFunctionValidator->getError());
		}

		$newFunctions[$exprPart['expression']] = 0;

		$functions[] = array(
			'itemid' => $items[$exprPart['host']][$exprPart['item']]['itemid'],
			'triggerid' => $triggerId,
			'function' => $exprPart['functionName'],
			'parameter' => $exprPart['functionParam']
		);
	}

	$functionIds = DB::insert('functions', $functions);

	$num = 0;
	foreach ($newFunctions as &$newFunction) {
		$newFunction = $functionIds[$num++];
	}
	unset($newFunction);

	$exprPart = end($expressionData->expressions);
	do {
		$expression = substr_replace($expression, '{'.$newFunctions[$exprPart['expression']].'}',
				$exprPart['pos'], strlen($exprPart['expression']));
	}
	while ($exprPart = prev($expressionData->expressions));

	$hostnames = array_unique($hostnames);

	return $expression;
}

/**
 * Get items from expression.
 *
 * @param CTriggerExpression $triggerExpression
 *
 * @return array
 */
function getExpressionItems(CTriggerExpression $triggerExpression) {
	$items = array();
	$processedFunctions = array();
	$processedItems = array();

	foreach ($triggerExpression->expressions as $expression) {
		if (isset($processedFunctions[$expression['expression']])) {
			continue;
		}

		if (!isset($processedItems[$expression['host']][$expression['item']])) {
			$dbItems = DBselect(
				'SELECT i.itemid,i.flags'.
				' FROM items i,hosts h'.
				' WHERE i.key_='.zbx_dbstr($expression['item']).
					' AND '.dbConditionInt('i.flags', array(
						ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED, ZBX_FLAG_DISCOVERY_PROTOTYPE
					)).
					' AND h.host='.zbx_dbstr($expression['host']).
					' AND h.hostid=i.hostid'
			);
			if ($dbItem = DBfetch($dbItems)) {
				$items[] = $dbItem;
				$processedItems[$expression['host']][$expression['item']] = true;
			}
		}

		$processedFunctions[$expression['expression']] = true;
	}

	return $items;
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
					' AND t.templateid='.zbx_dbstr($val).
					' AND i.hostid='.zbx_dbstr($hostid);
		if ($db_new_dep = DBfetch(DBselect($sql))) {
			$deps[$id] = $db_new_dep['triggerid'];
		}
	}

	return $deps;
}

/**
 * Creates and returns the trigger overview table for the given hosts.
 *
 * @param array  	$hosts							an array of hosts with host IDs as keys
 * @param string 	$hosts[hostid][name]
 * @param string 	$hosts[hostid][hostid]
 * @param array		$triggers
 * @param string	$triggers[][triggerid]
 * @param string	$triggers[][description]
 * @param string	$triggers[][expression]
 * @param int		$triggers[][value]
 * @param int		$triggers[][lastchange]
 * @param int		$triggers[][flags]
 * @param array		$triggers[][url]
 * @param int		$triggers[][priority]
 * @param array		$triggers[][hosts]
 * @param string	$triggers[][hosts][][hostid]
 * @param string	$triggers[][hosts][][name]
 * @param string 	$pageFile						the page where the element is displayed
 * @param int    	$viewMode						table display style: either hosts on top, or host on the left side
 * @param string 	$screenId						the ID of the screen, that contains the trigger overview table
 *
 * @return CTableInfo
 */
function getTriggersOverview(array $hosts, array $triggers, $pageFile, $viewMode = null, $screenId = null) {
	$data = array();
	$hostNames = array();

	foreach ($triggers as $trigger) {
		$trigger['description'] = CMacrosResolverHelper::resolveTriggerReference($trigger['expression'], $trigger['description']);

		foreach ($trigger['hosts'] as $host) {
			// triggers may belong to hosts that are filtered out and shouldn't be displayed, skip them
			if (!isset($hosts[$host['hostid']])) {
				continue;
			}

			$hostNames[$host['hostid']] = $host['name'];

			// a little tricky check for attempt to overwrite active trigger (value=1) with
			// inactive or active trigger with lower priority.
			if (!isset($data[$trigger['description']][$host['name']])
					|| (($data[$trigger['description']][$host['name']]['value'] == TRIGGER_VALUE_FALSE && $trigger['value'] == TRIGGER_VALUE_TRUE)
						|| (($data[$trigger['description']][$host['name']]['value'] == TRIGGER_VALUE_FALSE || $trigger['value'] == TRIGGER_VALUE_TRUE)
							&& $trigger['priority'] > $data[$trigger['description']][$host['name']]['priority']))) {
				$data[$trigger['description']][$host['name']] = array(
					'hostid' => $host['hostid'],
					'triggerid' => $trigger['triggerid'],
					'value' => $trigger['value'],
					'lastchange' => $trigger['lastchange'],
					'priority' => $trigger['priority'],
					'flags' => $trigger['flags'],
					'url' => $trigger['url'],
					'hosts' => $trigger['hosts'],
					'items' => $trigger['items']
				);
			}
		}
	}

	$triggerTable = new CTableInfo(_('No triggers found.'));

	if (empty($hostNames)) {
		return $triggerTable;
	}

	$triggerTable->makeVerticalRotation();

	order_result($hostNames);

	if ($viewMode == STYLE_TOP) {
		// header
		$header = array(new CCol(_('Triggers'), 'center'));

		foreach ($hostNames as $hostName) {
			$header[] = new CCol($hostName, 'vertical_rotation');
		}

		$triggerTable->setHeader($header, 'vertical_header');

		// data
		foreach ($data as $description => $triggerHosts) {
			$columns = array(nbsp($description));

			foreach ($hostNames as $hostName) {
				$columns[] = getTriggerOverviewCells(
					isset($triggerHosts[$hostName]) ? $triggerHosts[$hostName] : null,
					$pageFile,
					$screenId
				);
			}

			$triggerTable->addRow($columns);
		}
	}
	else {
		// header
		$header = array(new CCol(_('Host'), 'center'));

		foreach ($data as $description => $triggerHosts) {
			$header[] = new CCol($description, 'vertical_rotation');
		}

		$triggerTable->setHeader($header, 'vertical_header');

		// data
		$scripts = API::Script()->getScriptsByHosts(zbx_objectValues($hosts, 'hostid'));

		foreach ($hostNames as $hostId => $hostName) {
			$name = new CSpan($hostName, 'link_menu');
			$name->setMenuPopup(CMenuPopupHelper::getHost($hosts[$hostId], $scripts[$hostId]));

			$columns = array($name);
			foreach ($data as $triggerHosts) {
				$columns[] = getTriggerOverviewCells(
					isset($triggerHosts[$hostName]) ? $triggerHosts[$hostName] : null,
					$pageFile,
					$screenId
				);
			}

			$triggerTable->addRow($columns);
		}
	}

	return $triggerTable;
}

/**
 * Creates and returns a trigger status cell for the trigger overview table.
 *
 * @see getTriggersOverview()
 *
 * @param array  $trigger
 * @param string $pageFile		the page where the element is displayed
 * @param string $screenId
 *
 * @return CCol
 */
function getTriggerOverviewCells($trigger, $pageFile, $screenId = null) {
	$ack = $css = $style = null;
	$desc = array();
	$acknowledge = array();

	// for how long triggers should blink on status change (set by user in administration->general)
	$config = select_config();

	if ($trigger) {
		$style = 'cursor: pointer; ';

		// problem trigger
		if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
			$css = getSeverityStyle($trigger['priority']);
			$ack = null;

			if ($config['event_ack_enable'] == 1) {
				if ($event = get_last_event_by_triggerid($trigger['triggerid'])) {
					if ($screenId) {
						$acknowledge = array(
							'eventid' => $event['eventid'],
							'screenid' => $screenId,
							'backurl' => $pageFile
						);
					}
					else {
						$acknowledge = array(
							'eventid' => $event['eventid'],
							'backurl' => 'overview.php'
						);
					}

					if ($event['acknowledged'] == 1) {
						$ack = new CImg('images/general/tick.png', 'ack');
					}
				}
			}
		}
		// ok trigger
		else {
			$css = 'normal';
		}

		// dependency: triggers on which depends this
		$triggerId = empty($trigger['triggerid']) ? 0 : $trigger['triggerid'];

		// trigger dependency DOWN
		$dependencyTable = new CTableInfo();
		$dependencyTable->setAttribute('style', 'width: 200px;');
		$dependencyTable->addRow(bold(_('Depends on').NAME_DELIMITER));

		$isDependencyFound = false;
		$dbDependencies = DBselect('SELECT td.* FROM trigger_depends td WHERE td.triggerid_down='.zbx_dbstr($triggerId));
		while ($dbDependency = DBfetch($dbDependencies)) {
			$dependencyTable->addRow(SPACE.'-'.SPACE.CMacrosResolverHelper::resolveTriggerNameById($dbDependency['triggerid_up']));
			$isDependencyFound = true;
		}

		if ($isDependencyFound) {
			$icon = new CImg('images/general/arrow_down2.png', 'DEP_DOWN');
			$icon->setAttribute('style', 'vertical-align: middle; border: 0px;');
			$icon->setHint($dependencyTable, '', false);

			$desc[] = $icon;
		}

		// trigger dependency UP
		$dependencyTable = new CTableInfo();
		$dependencyTable->setAttribute('style', 'width: 200px;');
		$dependencyTable->addRow(bold(_('Dependent').NAME_DELIMITER));

		$isDependencyFound = false;
		$dbDependencies = DBselect('SELECT td.* FROM trigger_depends td WHERE td.triggerid_up='.zbx_dbstr($triggerId));
		while ($dbDependency = DBfetch($dbDependencies)) {
			$dependencyTable->addRow(SPACE.'-'.SPACE.CMacrosResolverHelper::resolveTriggerNameById($dbDependency['triggerid_down']));
			$isDependencyFound = true;
		}

		if ($isDependencyFound) {
			$icon = new CImg('images/general/arrow_up2.png', 'DEP_UP');
			$icon->setAttribute('style', 'vertical-align: middle; border: none;');
			$icon->setHint($dependencyTable, '', false);

			$desc[] = $icon;
		}
	}

	$column = ((is_array($desc) && count($desc) > 0) || $ack)
		? new CCol(array($desc, $ack), $css.' hosts')
		: new CCol(SPACE, $css.' hosts');

	$column->setAttribute('style', $style);

	if ($trigger && $config['blink_period'] > 0 && time() - $trigger['lastchange'] < $config['blink_period']) {
		$column->addClass('blink');
		$column->setAttribute('data-toggle-class', $css);
	}

	if ($trigger) {
		$column->setMenuPopup(CMenuPopupHelper::getTrigger($trigger, $acknowledge));
	}

	return $column;
}

/**
 * Calculate trigger availability.
 *
 * @param int $triggerId		trigger id
 * @param int $startTime		begin period
 * @param int $endTime			end period
 *
 * @return array
 */
function calculateAvailability($triggerId, $startTime, $endTime) {
	$startValue = TRIGGER_VALUE_FALSE;

	if ($startTime > 0 && $startTime <= time()) {
		$sql = 'SELECT e.eventid,e.value'.
				' FROM events e'.
				' WHERE e.objectid='.zbx_dbstr($triggerId).
					' AND e.source='.EVENT_SOURCE_TRIGGERS.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.clock<'.zbx_dbstr($startTime).
				' ORDER BY e.eventid DESC';
		if ($row = DBfetch(DBselect($sql, 1))) {
			$startValue = $row['value'];
			$min = $startTime;
		}
	}

	$sql = 'SELECT COUNT(e.eventid) AS cnt,MIN(e.clock) AS min_clock,MAX(e.clock) AS max_clock'.
			' FROM events e'.
			' WHERE e.objectid='.zbx_dbstr($triggerId).
				' AND e.source='.EVENT_SOURCE_TRIGGERS.
				' AND e.object='.EVENT_OBJECT_TRIGGER;
	if ($startTime) {
		$sql .= ' AND e.clock>='.zbx_dbstr($startTime);
	}
	if ($endTime) {
		$sql .= ' AND e.clock<='.zbx_dbstr($endTime);
	}

	$dbEvents = DBfetch(DBselect($sql));
	if ($dbEvents['cnt'] > 0) {
		if (!isset($min)) {
			$min = $dbEvents['min_clock'];
		}
		$max = $dbEvents['max_clock'];
	}
	else {
		if ($startTime == 0 && $endTime == 0) {
			$max = time();
			$min = $max - SEC_PER_DAY;
		}
		else {
			$ret['true_time'] = 0;
			$ret['false_time'] = 0;
			$ret['true'] = (TRIGGER_VALUE_TRUE == $startValue) ? 100 : 0;
			$ret['false'] = (TRIGGER_VALUE_FALSE == $startValue) ? 100 : 0;
			return $ret;
		}
	}

	$state = $startValue;
	$true_time = 0;
	$false_time = 0;
	$time = $min;
	if ($startTime == 0 && $endTime == 0) {
		$max = time();
	}
	if ($endTime == 0) {
		$endTime = $max;
	}

	$rows = 0;
	$dbEvents = DBselect(
		'SELECT e.eventid,e.clock,e.value'.
		' FROM events e'.
		' WHERE e.objectid='.zbx_dbstr($triggerId).
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock BETWEEN '.$min.' AND '.$max.
		' ORDER BY e.eventid'
	);
	while ($row = DBfetch($dbEvents)) {
		$clock = $row['clock'];
		$value = $row['value'];

		$diff = $clock - $time;
		$time = $clock;

		if ($state == 0) {
			$false_time += $diff;
			$state = $value;
		}
		elseif ($state == 1) {
			$true_time += $diff;
			$state = $value;
		}
		$rows++;
	}

	if ($rows == 0) {
		$trigger = get_trigger_by_triggerid($triggerId);
		$state = $trigger['value'];
	}

	if ($state == TRIGGER_VALUE_FALSE) {
		$false_time = $false_time + $endTime - $time;
	}
	elseif ($state == TRIGGER_VALUE_TRUE) {
		$true_time = $true_time + $endTime - $time;
	}
	$total_time = $true_time + $false_time;

	if ($total_time == 0) {
		$ret['true_time'] = 0;
		$ret['false_time'] = 0;
		$ret['true'] = 0;
		$ret['false'] = 0;
	}
	else {
		$ret['true_time'] = $true_time;
		$ret['false_time'] = $false_time;
		$ret['true'] = (100 * $true_time) / $total_time;
		$ret['false'] = (100 * $false_time) / $total_time;
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
	$hostNames = array();

	$config = select_config();

	$hostIds = zbx_objectValues($trigger['hosts'], 'hostid');

	$hosts = API::Host()->get(array(
		'output' => array('name', 'hostid', 'status'),
		'hostids' => $hostIds,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT
	));

	if (count($hosts) > 1) {
		order_result($hosts, 'name', ZBX_SORT_UP);
	}

	$scripts = API::Script()->getScriptsByHosts($hostIds);

	foreach ($hosts as $host) {
		$hostName = new CSpan($host['name'], 'link_menu');
		$hostName->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));
		$hostNames[] = $hostName;
		$hostNames[] = ', ';
	}
	array_pop($hostNames);

	$table = new CTableInfo();
	$table->addRow(array(
		new CCol(_n('Host', 'Hosts', count($hosts))),
		new CCol($hostNames, 'wraptext')
	));
	$table->addRow(array(
		new CCol(_('Trigger')),
		new CCol(CMacrosResolverHelper::resolveTriggerName($trigger), 'wraptext')
	));
	$table->addRow(array(_('Severity'), getSeverityCell($trigger['priority'], $config)));
	$table->addRow(array(
		new CCol(_('Expression')),
		new CCol(explode_exp($trigger['expression'], true, true), 'trigger-expression')
	));
	$table->addRow(array(_('Event generation'), _('Normal').((TRIGGER_MULT_EVENT_ENABLED == $trigger['type'])
		? SPACE.'+'.SPACE._('Multiple PROBLEM events') : '')));
	$table->addRow(array(_('Disabled'), ((TRIGGER_STATUS_ENABLED == $trigger['status'])
		? new CCol(_('No'), 'off') : new CCol(_('Yes'), 'on'))));

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
 * @param array 	$expressionTree 	output of getExpressionTree() function
 * @param array 	$next           	parameter only for recursive call; should be empty array
 * @param int 		$letterNum      	parameter only for recursive call; should be 0
 * @param int 		$level          	parameter only for recursive call
 * @param string 	$operator       	parameter only for recursive call
 *
 * @return array	array containing the trigger expression formula as the first element and an array describing the
 *					expression tree as the second
 */
function buildExpressionHtmlTree(array $expressionTree, array &$next, &$letterNum, $level = 0, $operator = null) {
	$treeList = array();
	$outline = '';

	end($expressionTree);
	$lastKey = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operator':
				$next[$level] = ($key != $lastKey);
				$expr = expressionLevelDraw($next, $level);
				$expr[] = SPACE;
				$expr[] = ($element['operator'] === 'and') ? _('And') : _('Or');
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
						$level + 1, $element['operator']);
				$treeList = array_merge($treeList, $subTreeList);

				$outline .= ($level == 0) ? $subOutline : '('.$subOutline.')';
				if ($operator !== null && $next[$level]) {
					$outline .= ' '.$operator.' ';
				}
				break;
			case 'expression':
				$next[$level] = ($key != $lastKey);

				$letter = num2letter($letterNum++);
				$outline .= $letter;
				if ($operator !== null && $next[$level]) {
					$outline .= ' '.$operator.' ';
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
 * Makes tree of expression elements
 *
 * Expression:
 *   "{host1:system.cpu.util[,iowait].last(0)} > 50 and {host2:system.cpu.util[,iowait].last(0)} > 50"
 * Result:
 *   array(
 *     [0] => array(
 *       'id' => '0_94',
 *       'type' => 'operator',
 *       'operator' => 'and',
 *       'elements' => array(
 *         [0] => array(
 *           'id' => '0_44',
 *           'type' => 'expression',
 *           'expression' => '{host1:system.cpu.util[,iowait].last(0)} > 50'
 *         ),
 *         [1] => array(
 *           'id' => '50_94',
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
	$blankSymbols = array(' ', "\r", "\n", "\t");

	$expressionTree = array();
	foreach (array('or', 'and') as $operator) {
		$operatorFound = false;
		$lParentheses = -1;
		$rParentheses = -1;
		$expressions = array();
		$openSymbolNum = $start;
		$operatorPos = 0;
		$operatorToken = '';

		for ($i = $start, $level = 0; $i <= $end; $i++) {
			switch ($expressionData->expression[$i]) {
				case ' ':
				case "\r":
				case "\n":
				case "\t":
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
				case '{':
					foreach ($expressionData->expressions as $exprPart) {
						if ($exprPart['pos'] == $i) {
							$i += strlen($exprPart['expression']) - 1;
							break;
						}
					}
					break;
				default:
					// try to parse an operator
					if ($operator[$operatorPos] === $expressionData->expression[$i]) {
						$operatorPos++;
						$operatorToken .= $expressionData->expression[$i];

						// operator found
						if ($operatorToken === $operator) {
							// we've reached the end of a complete expression, parse the expression on the left side of
							// the operator
							if ($level == 0) {
								// find the last symbol of the expression before the operator
								$closeSymbolNum = $i - strlen($operator);

								// trim blank symbols after the expression
								while (in_array($expressionData->expression[$closeSymbolNum], $blankSymbols)) {
									$closeSymbolNum--;
								}

								$expressions[] = getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum);
								$openSymbolNum = $i + 1;
								$operatorFound = true;
							}
							$operatorPos = 0;
							$operatorToken = '';
						}
					}
			}
		}

		// trim blank symbols in the end of the trigger expression
		$closeSymbolNum = $end;
		while (in_array($expressionData->expression[$closeSymbolNum], $blankSymbols)) {
			$closeSymbolNum--;
		}

		// we've found a whole expression and parsed the expression on the left side of the operator,
		// parse the expression on the right
		if ($operatorFound) {
			$expressions[] = getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum);

			// trim blank symbols in the beginning of the trigger expression
			$openSymbolNum = $start;
			while (in_array($expressionData->expression[$openSymbolNum], $blankSymbols)) {
				$openSymbolNum++;
			}

			// trim blank symbols in the end of the trigger expression
			$closeSymbolNum = $end;
			while (in_array($expressionData->expression[$closeSymbolNum], $blankSymbols)) {
				$closeSymbolNum--;
			}

			$expressionTree = array(
				'id' => $openSymbolNum.'_'.$closeSymbolNum,
				'expression' => substr($expressionData->expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
				'type' => 'operator',
				'operator' => $operator,
				'elements' => $expressions
			);
			break;
		}
		// if we've tried both operators and didn't find anything, it means there's only one expression
		// return the result
		elseif ($operator === 'and') {
			// trim extra parentheses
			if ($openSymbolNum == $lParentheses && $closeSymbolNum == $rParentheses) {
				$openSymbolNum++;
				$closeSymbolNum--;

				$expressionTree = getExpressionTree($expressionData, $openSymbolNum, $closeSymbolNum);
			}
			// no extra parentheses remain, return the result
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
 * Recreate an expression depending on action.
 *
 * Supported action values:
 * - and	- add an expression using "and";
 * - or		- add an expression using "or";
 * - r 		- replace;
 * - R		- remove.
 *
 * @param string $expression
 * @param string $expressionId  element identifier like "0_55"
 * @param string $action        action to perform
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
 * Rebuild expression depending on action.
 *
 * Supported action values:
 * - and	- add an expression using "and";
 * - or		- add an expression using "or";
 * - r 		- replace;
 * - R		- remove.
 *
 * Example:
 *   $expressionTree = array(
 *     [0] => array(
 *       'id' => '0_94',
 *       'type' => 'operator',
 *       'operator' => 'and',
 *       'elements' => array(
 *         [0] => array(
 *           'id' => '0_44',
 *           'type' => 'expression',
 *           'expression' => '{host1:system.cpu.util[,iowait].last(0)} > 50'
 *         ),
 *         [1] => array(
 *           'id' => '50_94',
 *           'type' => 'expression',
 *           'expression' => '{host2:system.cpu.util[,iowait].last(0)} > 50'
 *         )
 *       )
 *     )
 *   )
 *   $action = 'R'
 *   $expressionId = '50_94'
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
 * @param array 	$expressionTree
 * @param string 	$expressionId  		element identifier like "0_55"
 * @param string 	$action        		action to perform
 * @param string 	$newExpression 		expression for AND, OR or replace actions
 * @param string 	$operator       	parameter only for recursive call
 *
 * @return bool                 returns true if element is found, false - otherwise
 */
function rebuildExpressionTree(array &$expressionTree, $expressionId, $action, $newExpression, $operator = null) {
	foreach ($expressionTree as $key => $expression) {
		if ($expressionId == $expressionTree[$key]['id']) {
			switch ($action) {
				case 'and':
				case 'or':
					switch ($expressionTree[$key]['type']) {
						case 'operator':
							if ($expressionTree[$key]['operator'] == $action) {
								$expressionTree[$key]['elements'][] = array(
									'expression' => $newExpression,
									'type' => 'expression'
								);
							}
							else {
								$element = array(
									'type' => 'operator',
									'operator' => $action,
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
							if (!$operator || $operator != $action) {
								$element = array(
									'type' => 'operator',
									'operator' => $action,
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
					if ($expressionTree[$key]['type'] == 'operator') {
						$expressionTree[$key]['type'] = 'expression';
						unset($expressionTree[$key]['operator'], $expressionTree[$key]['elements']);
					}
					break;
				// remove
				case 'R':
					unset($expressionTree[$key]);
					break;
			}
			return true;
		}

		if ($expressionTree[$key]['type'] == 'operator') {
			if (rebuildExpressionTree($expressionTree[$key]['elements'], $expressionId, $action, $newExpression,
					$expressionTree[$key]['operator'])) {
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
 *       'type' => 'operator',
 *       'operator' => 'and',
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
 *   "{host1:system.cpu.util[,iowait].last(0)} > 50 and {host2:system.cpu.util[,iowait].last(0)} > 50"
 *
 * @param array  $expressionTree
 * @param int    $level				parameter only for recursive call
 * @param string $operator			parameter only for recursive call
 *
 * @return string
 */
function makeExpression(array $expressionTree, $level = 0, $operator = null) {
	$expression = '';

	end($expressionTree);
	$lastKey = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operator':
				$subExpression = makeExpression($element['elements'], $level + 1, $element['operator']);

				$expression .= ($level == 0) ? $subExpression : '('.$subExpression.')';
				break;
			case 'expression':
				$expression .= $element['expression'];
				break;
		}
		if ($operator !== null && $key != $lastKey) {
			$expression .= ' '.$operator.' ';
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
		ITEM_VALUE_TYPE_FLOAT	=> T_ZBX_DBL_BIG,
		ITEM_VALUE_TYPE_STR		=> T_ZBX_STR,
		ITEM_VALUE_TYPE_LOG		=> T_ZBX_STR,
		ITEM_VALUE_TYPE_TEXT	=> T_ZBX_STR
	);

	$function_info = array(
		'band' =>		array('value_type' => _('Numeric (integer 64bit)'),	'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
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
		'percentile' =>	array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'prev' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'regexp' =>		array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'str' =>		array('value_type' => _('0 or 1'),	'type' => T_ZBX_INT,			'validation' => IN('0,1')),
		'strlen' =>		array('value_type' => _('Numeric (integer 64bit)'), 'type' => T_ZBX_INT, 'validation' => NOT_EMPTY),
		'sum' =>		array('value_type' => $value_type,	'type' => $type_of_value_type,	'validation' => NOT_EMPTY),
		'time' =>		array('value_type' => 'HHMMSS',		'type' => T_ZBX_INT,			'validation' => 'strlen({})==6')
	);

	$expressionData = new CTriggerExpression();
	$parseResult = $expressionData->parse($expr);

	if ($parseResult) {
		if ($parseResult->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_MACRO)) {
			$result = array(
				'value_type' => _('0 or 1'),
				'type' => T_ZBX_INT,
				'validation' => IN('0,1')
			);
		}
		elseif ($parseResult->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_USER_MACRO)
				|| $parseResult->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_LLD_MACRO)) {

			$result = array(
				'value_type' => $value_type[ITEM_VALUE_TYPE_FLOAT],
				'type' => T_ZBX_STR,
				'validation' => 'preg_match("/^'.ZBX_PREG_NUMBER.'$/u", {})'
			);
		}
		elseif ($parseResult->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO)) {
			$exprPart = reset($expressionData->expressions);

			if (!isset($function_info[$exprPart['functionName']])) {
				return EXPRESSION_FUNCTION_UNKNOWN;
			}

			$hostFound = API::Host()->get(array(
				'output' => array('hostid'),
				'filter' => array('host' => array($exprPart['host'])),
				'templated_hosts' => true
			));

			if (!$hostFound) {
				return EXPRESSION_HOST_UNKNOWN;
			}

			$itemFound = API::Item()->get(array(
				'output' => array('value_type'),
				'hostids' => zbx_objectValues($hostFound, 'hostid'),
				'filter' => array(
					'key_' => array($exprPart['item'])
				),
				'webitems' => true
			));

			if (!$itemFound) {
				$itemFound = API::ItemPrototype()->get(array(
					'output' => array('value_type'),
					'hostids' => zbx_objectValues($hostFound, 'hostid'),
					'filter' => array(
						'key_' => array($exprPart['item'])
					)
				));

				if (!$itemFound) {
					return EXPRESSION_HOST_ITEM_UNKNOWN;
				}
			}

			$itemFound = reset($itemFound);
			$result = $function_info[$exprPart['functionName']];

			if (is_array($result['value_type'])) {
				$result['value_type'] = $result['value_type'][$itemFound['value_type']];
				$result['type'] = $result['type'][$itemFound['value_type']];

				if ($result['type'] == T_ZBX_INT) {
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

/**
 * Substitute macros in the expression with the given values and evaluate its result.
 *
 * @param string $expression                a trigger expression
 * @param array  $replaceFunctionMacros     an array of macro - value pairs
 *
 * @return bool     the calculated value of the expression
 */
function evalExpressionData($expression, $replaceFunctionMacros) {
	// replace function macros with their values
	$expression = str_replace(array_keys($replaceFunctionMacros), array_values($replaceFunctionMacros), $expression);

	$parser = new CTriggerExpression();
	$parseResult = $parser->parse($expression);

	// The $replaceFunctionMacros array may contain string values which after substitution
	// will result in an invalid expression. In such cases we should just return false.
	if (!$parseResult) {
		return false;
	}

	// turn the expression into valid PHP code
	$evStr = '';
	$replaceOperators = array('not' => '!', '=' => '==');
	foreach ($parseResult->getTokens() as $token) {
		$value = $token['value'];

		switch ($token['type']) {
			case CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR:
				// replace specific operators with their PHP analogues
				if (isset($replaceOperators[$token['value']])) {
					$value = $replaceOperators[$token['value']];
				}

				break;
			case CTriggerExpressionParserResult::TOKEN_TYPE_NUMBER:
				// convert numeric values with suffixes
				if ($token['data']['suffix'] !== null) {
					$value = convert($value);
				}

				$value = '((float) "'.$value.'")';

				break;
		}

		$evStr .= ' '.$value;
	}

	// execute expression
	eval('$result = ('.trim($evStr).');');

	return $result;
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
 * Quoting $param if it contains special characters.
 *
 * @param string $param
 *
 * @return string
 */
function quoteFunctionParam($param) {
	if (!isset($param[0]) || ($param[0] != '"' && false === strpos($param, ',') && false === strpos($param, ')'))) {
		return $param;
	}

	return '"'.str_replace('"', '\\"', $param).'"';
}

/**
 * Returns the text indicating the trigger's status and state. If the $state parameter is not given, only the status of
 * the trigger will be taken into account.
 *
 * @param int $status
 * @param int $state
 *
 * @return string
 */
function triggerIndicator($status, $state = null) {
	if ($status == TRIGGER_STATUS_ENABLED) {
		return ($state == TRIGGER_STATE_UNKNOWN) ? _('Unknown') : _('Enabled');
	}
	elseif ($status == TRIGGER_STATUS_DISABLED) {
		return _('Disabled');
	}

	return _('Unknown');
}

/**
 * Returns the CSS class for the trigger's status and state indicator. If the $state parameter is not given, only the
 * status of the trigger will be taken into account.
 *
 * @param int $status
 * @param int $state
 *
 * @return string
 */
function triggerIndicatorStyle($status, $state = null) {
	if ($status == TRIGGER_STATUS_ENABLED) {
		return ($state == TRIGGER_STATE_UNKNOWN) ? 'unknown' : 'enabled';
	}
	elseif ($status == TRIGGER_STATUS_DISABLED) {
		return 'disabled';
	}

	return 'unknown';
}

/**
 * Orders triggers by both status and state. Triggers are sorted in the following order: enabled, disabled, unknown.
 *
 * Keep in sync with orderItemsByStatus().
 *
 * @param array  $triggers
 * @param string $sortorder
 */
function orderTriggersByStatus(array &$triggers, $sortorder = ZBX_SORT_UP) {
	$sort = array();

	foreach ($triggers as $key => $trigger) {
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED) {
			$statusOrder = ($trigger['state'] == TRIGGER_STATE_UNKNOWN) ? 2 : 0;
		}
		elseif ($trigger['status'] == TRIGGER_STATUS_DISABLED) {
			$statusOrder = 1;
		}

		$sort[$key] = $statusOrder;
	}

	if ($sortorder == ZBX_SORT_UP) {
		asort($sort);
	}
	else {
		arsort($sort);
	}

	$sortedTriggers = array();
	foreach ($sort as $key => $val) {
		$sortedTriggers[$key] = $triggers[$key];
	}
	$triggers = $sortedTriggers;
}
