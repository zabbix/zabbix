<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * Get trigger severity full line height css style name.
 *
 * @param  int         $severity Trigger severity.
 *
 * @return string|null
 */
function getSeverityFlhStyle($severity) {
	switch ($severity) {
		case TRIGGER_SEVERITY_DISASTER:
			return ZBX_STYLE_FLH_DISASTER_BG;
		case TRIGGER_SEVERITY_HIGH:
			return ZBX_STYLE_FLH_HIGH_BG;
		case TRIGGER_SEVERITY_AVERAGE:
			return ZBX_STYLE_FLH_AVERAGE_BG;
		case TRIGGER_SEVERITY_WARNING:
			return ZBX_STYLE_FLH_WARNING_BG;
		case TRIGGER_SEVERITY_INFORMATION:
			return ZBX_STYLE_FLH_INFO_BG;
		case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			return ZBX_STYLE_FLH_NA_BG;
		default:
			return null;
	}
}

/**
 * Get trigger severity status css style name.
 *
 * @param  int         $severity Trigger severity.
 *
 * @return string|null
 */
function getSeverityStatusStyle($severity) {
	switch ($severity) {
		case TRIGGER_SEVERITY_DISASTER:
			return ZBX_STYLE_STATUS_DISASTER_BG;
		case TRIGGER_SEVERITY_HIGH:
			return ZBX_STYLE_STATUS_HIGH_BG;
		case TRIGGER_SEVERITY_AVERAGE:
			return ZBX_STYLE_STATUS_AVERAGE_BG;
		case TRIGGER_SEVERITY_WARNING:
			return ZBX_STYLE_STATUS_WARNING_BG;
		case TRIGGER_SEVERITY_INFORMATION:
			return ZBX_STYLE_STATUS_INFO_BG;
		case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			return ZBX_STYLE_STATUS_NA_BG;
		default:
			return null;
	}
}

function getSeverityStyle($severity, $type = true) {
	if (!$type) {
		return ZBX_STYLE_NORMAL_BG;
	}

	switch ($severity) {
		case TRIGGER_SEVERITY_DISASTER:
			return ZBX_STYLE_DISASTER_BG;
		case TRIGGER_SEVERITY_HIGH:
			return ZBX_STYLE_HIGH_BG;
		case TRIGGER_SEVERITY_AVERAGE:
			return ZBX_STYLE_AVERAGE_BG;
		case TRIGGER_SEVERITY_WARNING:
			return ZBX_STYLE_WARNING_BG;
		case TRIGGER_SEVERITY_INFORMATION:
			return ZBX_STYLE_INFO_BG;
		case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			return ZBX_STYLE_NA_BG;
		default:
			return null;
	}
}

/**
 * Get trigger severity name by given state and configuration.
 *
 * @param int   $severity Trigger severity.
 * @param array $config   Array with configuration parameters containing severity names.
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
 * @param int         $severity     Trigger severity.
 * @param array|null  $config       Array of configuration parameters to get trigger severity name; can be omitted
 *                                  if $text is not null.
 * @param string|null $text         Trigger severity name.
 * @param bool        $force_normal True to return 'normal' class, false to return corresponding severity class.
 *
 * @return CCol
 */
function getSeverityCell($severity, array $config = null, $text = null, $force_normal = false) {
	if ($text === null) {
		$text = CHtml::encode(getSeverityName($severity, $config));
	}

	if ($force_normal) {
		return new CCol($text);
	}

	return (new CCol($text))->addClass(getSeverityStyle($severity));
}

/**
 * Add color style and blinking to an object like CSpan or CDiv depending on trigger status.
 * Settings and colors are kept in 'config' database table.
 *
 * @param mixed $object             object like CSpan, CDiv, etc.
 * @param int $triggerValue         TRIGGER_VALUE_FALSE or TRIGGER_VALUE_TRUE
 * @param int $triggerLastChange
 * @param bool $isAcknowledged
 */
function addTriggerValueStyle($object, $triggerValue, $triggerLastChange, $isAcknowledged) {
	$config = select_config();

	$color_class = null;
	$blinks = null;

	// Color class for text and blinking depends on trigger value and whether event is acknowledged.
	if ($triggerValue == TRIGGER_VALUE_TRUE && !$isAcknowledged) {
		$color_class = ZBX_STYLE_PROBLEM_UNACK_FG;
		$blinks = $config['problem_unack_style'];
	}
	elseif ($triggerValue == TRIGGER_VALUE_TRUE && $isAcknowledged) {
		$color_class = ZBX_STYLE_PROBLEM_ACK_FG;
		$blinks = $config['problem_ack_style'];
	}
	elseif ($triggerValue == TRIGGER_VALUE_FALSE && !$isAcknowledged) {
		$color_class = ZBX_STYLE_OK_UNACK_FG;
		$blinks = $config['ok_unack_style'];
	}
	elseif ($triggerValue == TRIGGER_VALUE_FALSE && $isAcknowledged) {
		$color_class = ZBX_STYLE_OK_ACK_FG;
		$blinks = $config['ok_ack_style'];
	}

	if ($color_class != null && $blinks != null) {
		$object->addClass($color_class);

		// blinking
		$timeSinceLastChange = time() - $triggerLastChange;
		$config['blink_period'] = timeUnitToSeconds($config['blink_period']);

		if ($blinks && $timeSinceLastChange < $config['blink_period']) {
			$object->addClass('blink'); // elements with this class will blink
			$object->setAttribute('data-time-to-blink', $config['blink_period'] - $timeSinceLastChange);
		}
	}
	else {
		$object->addClass(ZBX_STYLE_GREY);
	}
}

function trigger_value2str($value = null) {
	$triggerValues = [
		TRIGGER_VALUE_FALSE => _('OK'),
		TRIGGER_VALUE_TRUE => _('PROBLEM')
	];

	if ($value === null) {
		return $triggerValues;
	}
	elseif (isset($triggerValues[$value])) {
		return $triggerValues[$value];
	}

	return _('Unknown');
}

function getParentHostsByTriggers($triggers) {
	$hosts = [];
	$triggerParent = [];

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
						$triggerParent[$trigger['templateid']] = [];
					}
					$triggerParent[$trigger['templateid']][$trigger['triggerid']] = 1;
					$triggerParent[$trigger['templateid']] += $triggerParent[$trigger['triggerid']];
				}
				else {
					if (!isset($triggerParent[$trigger['templateid']])) {
						$triggerParent[$trigger['templateid']] = [];
					}
					$triggerParent[$trigger['templateid']][$trigger['triggerid']] = 1;
				}
			}
		}
		$triggers = API::Trigger()->get([
			'triggerids' => zbx_objectValues($triggers, 'templateid'),
			'selectHosts' => ['hostid', 'host', 'name', 'status'],
			'output' => ['triggerid', 'templateid'],
			'filter' => ['flags' => null]
		]);
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
 * Without the $src_hostid parameter it will only be able to copy triggers that belong to only one host. If the
 * $src_hostid parameter is not passed, and a trigger has multiple hosts, it will throw an error. If the
 * $src_hostid parameter is passed, the given host will be replaced with the destination host.
 *
 * This function takes care of copied trigger dependencies.
 * If trigger is copied alongside with trigger on which it depends, then dependencies is replaced directly using new ids,
 * If there is target host within dependency trigger, algorithm will search for potential matching trigger in target host,
 * if matching trigger is found, then id from this trigger is used, if not rise exception,
 * otherwise original dependency will be left.
 *
 *
 * @param array $src_triggerids		Triggers which will be copied to $dst_hostids
 * @param array $dst_hostids		Hosts and templates to whom add triggers. IDs not present in DB (host table)
 *									will be ignored.
 * @param int	$src_hostid			Host ID in which context trigger with multiple hosts will be treated.
 *
 * @return bool
 */
function copyTriggersToHosts($src_triggerids, $dst_hostids, $src_hostid = null) {
	$options = [
		'output' => ['triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments', 'type',
			'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close'
		],
		'selectDependencies' => ['triggerid'],
		'selectTags' => ['tag', 'value'],
		'triggerids' => $src_triggerids
	];

	if ($src_hostid) {
		$srcHost = API::Host()->get([
			'output' => ['host'],
			'hostids' => $src_hostid,
			'preservekeys' => true,
			'templated_hosts' => true
		]);

		if (!$srcHost = reset($srcHost)) {
			return false;
		}
	}
	else {
		// Select source trigger first host 'host'.
		$options['selectHosts'] = ['host'];
	}

	$dbSrcTriggers = API::Trigger()->get($options);

	$dbSrcTriggers = CMacrosResolverHelper::resolveTriggerExpressions($dbSrcTriggers,
		['sources' => ['expression', 'recovery_expression']]
	);

	$dbDstHosts = API::Host()->get([
		'output' => ['hostid', 'host'],
		'hostids' => $dst_hostids,
		'preservekeys' => true,
		'templated_hosts' => true
	]);

	$newTriggers = [];

	foreach ($dbDstHosts as $dstHost) {
		// Create each trigger for each host.

		foreach ($dbSrcTriggers as $srcTrigger) {
			if ($src_hostid) {
				// Get host 'host' for triggerExpressionReplaceHost().

				$host = $srcHost['host'];
				$srcTriggerContextHostId = $src_hostid;
			}
			else {
				if (count($srcTrigger['hosts']) > 1) {
					error(_s('Cannot copy trigger "%1$s:%2$s", because it has multiple hosts in the expression.',
						$srcTrigger['description'], $srcTrigger['expression']
					));

					return false;
				}

				// Use source trigger first host 'host'.
				$host = $srcTrigger['hosts'][0]['host'];
				$srcTriggerContextHostId = $srcTrigger['hosts'][0]['hostid'];
			}

			$srcTrigger['expression'] = triggerExpressionReplaceHost($srcTrigger['expression'], $host,
				$dstHost['host']
			);

			if ($srcTrigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$srcTrigger['recovery_expression'] = triggerExpressionReplaceHost($srcTrigger['recovery_expression'],
					$host, $dstHost['host']
				);
			}

			// The dependencies must be added after all triggers are created.
			$result = API::Trigger()->create([[
				'description' => $srcTrigger['description'],
				'expression' => $srcTrigger['expression'],
				'url' => $srcTrigger['url'],
				'status' => $srcTrigger['status'],
				'priority' => $srcTrigger['priority'],
				'comments' => $srcTrigger['comments'],
				'type' => $srcTrigger['type'],
				'recovery_mode' => $srcTrigger['recovery_mode'],
				'recovery_expression' => $srcTrigger['recovery_expression'],
				'correlation_mode' => $srcTrigger['correlation_mode'],
				'correlation_tag' => $srcTrigger['correlation_tag'],
				'tags' => $srcTrigger['tags'],
				'manual_close' => $srcTrigger['manual_close']
			]]);

			if (!$result) {
				return false;
			}

			$newTriggers[$srcTrigger['triggerid']][] = [
				'newTriggerId' => reset($result['triggerids']),
				'newTriggerExpression' => $srcTrigger['expression'],
				'newTriggerHostId' => $dstHost['hostid'],
				'newTriggerHost' => $dstHost['host'],
				'srcTriggerContextHostId' => $srcTriggerContextHostId,
				'srcTriggerContextHost' => $host
			];
		}
	}

	$depids = [];
	foreach ($dbSrcTriggers as $srcTrigger) {
		foreach ($srcTrigger['dependencies'] as $depTrigger) {
			$depids[] = $depTrigger['triggerid'];
		}
	}
	$depTriggers = API::Trigger()->get([
		'triggerids' => $depids,
		'output' => ['description', 'expression', 'recovery_mode', 'recovery_expression'],
		'selectHosts' => ['hostid'],
		'preservekeys' => true
	]);

	$depTriggers = CMacrosResolverHelper::resolveTriggerExpressions($depTriggers,
		['sources' => ['expression', 'recovery_expression']]
	);

	if ($newTriggers) {
		// Map dependencies to the new trigger IDs and save.

		$dependencies = [];

		foreach ($dbSrcTriggers as $srcTrigger) {
			if ($srcTrigger['dependencies']) {
				// Get corresponding created triggers.
				$dst_triggers = $newTriggers[$srcTrigger['triggerid']];

				foreach ($dst_triggers as $dst_trigger) {
					foreach ($srcTrigger['dependencies'] as $depTrigger) {
						/*
						 * We have added $depTrigger trigger and we know corresponding trigger ID for newly
						 * created trigger.
						 */
						if (array_key_exists($depTrigger['triggerid'], $newTriggers)) {
							$dst_dep_triggers = $newTriggers[$depTrigger['triggerid']];

							foreach ($dst_dep_triggers as $dst_dep_trigger) {
								/*
								 * Dependency is within same host according to $src_hostid parameter or dep trigger has
								 * single host.
								 */
								if ($dst_trigger['srcTriggerContextHostId'] == $dst_dep_trigger['srcTriggerContextHostId']
										&& $dst_dep_trigger['newTriggerHostId'] == $dst_trigger['newTriggerHostId']) {
									$depTriggerId = $dst_dep_trigger['newTriggerId'];
									break;
								}
								// Dependency is to trigger from another host.
								else {
									$depTriggerId = $depTrigger['triggerid'];
								}
							}
						}
						// We need to search for $depTrigger trigger if target host is within dependency hosts.
						elseif (in_array(['hostid' => $dst_trigger['srcTriggerContextHostId']],
								$depTriggers[$depTrigger['triggerid']]['hosts'])) {
							// Get all possible $depTrigger matching triggers by description.
							$targetHostTriggersByDescription = API::Trigger()->get([
								'hostids' => $dst_trigger['newTriggerHostId'],
								'output' => ['hosts', 'triggerid', 'expression'],
								'filter' => ['description' => $depTriggers[$depTrigger['triggerid']]['description']],
								'preservekeys' => true
							]);

							$targetHostTriggersByDescription =
								CMacrosResolverHelper::resolveTriggerExpressions($targetHostTriggersByDescription);

							// Compare exploded expressions for exact match.
							$expr1 = $depTriggers[$depTrigger['triggerid']]['expression'];
							$depTriggerId = null;

							foreach ($targetHostTriggersByDescription as $potentialTargetTrigger) {
								$expr2 = triggerExpressionReplaceHost($potentialTargetTrigger['expression'],
									$dst_trigger['newTriggerHost'], $dst_trigger['srcTriggerContextHost']
								);

								if ($expr2 == $expr1) {
									// Matching trigger has been found.
									$depTriggerId = $potentialTargetTrigger['triggerid'];
									break;
								}
							}

							// If matching trigger wasn't found raise exception.
							if ($depTriggerId === null) {
								$expr2 = triggerExpressionReplaceHost($expr1, $dst_trigger['srcTriggerContextHost'],
									$dst_trigger['newTriggerHost']
								);

								error(_s(
									'Cannot add dependency from trigger "%1$s:%2$s" to non existing trigger "%3$s:%4$s".',
									$srcTrigger['description'], $dst_trigger['newTriggerExpression'],
									$depTriggers[$depTrigger['triggerid']]['description'], $expr2
								));

								return false;
							}
						}
						else {
							// Leave original dependency.

							$depTriggerId = $depTrigger['triggerid'];
						}

						$dependencies[] = [
							'triggerid' => $dst_trigger['newTriggerId'],
							'dependsOnTriggerid' => $depTriggerId
						];
					}
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

/**
 * Purpose: Replaces host in trigger expression.
 * {localhost:agent.ping.nodata(5m)}  =>  {localhost6:agent.ping.nodata(5m)}
 *
 * @param string $expression	full expression with host names and item keys
 * @param string $src_host
 * @param string $dst_host
 *
 * @return string
 */
function triggerExpressionReplaceHost($expression, $src_host, $dst_host) {
	$new_expression = '';

	$function_macro_parser = new CFunctionMacroParser();
	$user_macro_parser = new CUserMacroParser();
	$macro_parser = new CMacroParser(['{TRIGGER.VALUE}']);
	$lld_macro_parser = new CLLDMacroParser();

	for ($pos = 0, $pos_left = 0; isset($expression[$pos]); $pos++) {
		if ($function_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
			$host = $function_macro_parser->getHost();
			$item = $function_macro_parser->getItem();
			$function = $function_macro_parser->getFunction();

			if ($host === $src_host) {
				$host = $dst_host;
			}

			$new_expression .= substr($expression, $pos_left, $pos - $pos_left);
			$new_expression .= '{'.$host.':'.$item.'.'.$function.'}';
			$pos_left = $pos + $function_macro_parser->getLength();

			$pos += $function_macro_parser->getLength() - 1;
		}
		elseif ($user_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
			$pos += $user_macro_parser->getLength() - 1;
		}
		elseif ($macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
			$pos += $macro_parser->getLength() - 1;
		}
		elseif ($lld_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
			$pos += $lld_macro_parser->getLength() - 1;
		}
	}

	$new_expression .= substr($expression, $pos_left, $pos - $pos_left);

	return $new_expression;
}

function check_right_on_trigger_by_expression($permission, $expression) {
	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}
	$expressionHosts = $expressionData->getHosts();

	$hosts = API::Host()->get([
		'filter' => ['host' => $expressionHosts],
		'editable' => ($permission == PERM_READ_WRITE),
		'output' => ['hostid', 'host'],
		'templated_hosts' => true,
		'preservekeys' => true
	]);
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

function getTriggersOverviewData(array $groupids, $application, $style, array $host_options = [],
		array $trigger_options = []) {
	// fetch hosts
	$hosts = API::Host()->get([
		'output' => ['hostid', 'status'],
		'selectGraphs' => ($style == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
		'selectScreens' => ($style == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
		'groupids' => $groupids ? $groupids : null,
		'preservekeys' => true
	] + $host_options);

	$hostids = array_keys($hosts);

	$options = [
		'output' => ['triggerid', 'expression', 'description', 'url', 'value', 'priority', 'lastchange', 'flags',
			'comments'],
		'selectHosts' => ['hostid', 'name', 'status'],
		'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
		'hostids' => $hostids,
		'monitored' => true,
		'skipDependent' => true,
		'sortfield' => 'description',
		'selectDependencies' => ['triggerid'],
		'preservekeys' => true
	] + $trigger_options;

	// application filter
	if ($application !== '') {
		$applications = API::Application()->get([
			'output' => [],
			'hostids' => $hostids,
			'search' => ['name' => $application],
			'preservekeys' => true
		]);
		$options['applicationids'] = array_keys($applications);
	}

	$triggers = API::Trigger()->get($options);

	$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);

	$rw_triggers = API::Trigger()->get([
		'output' => [],
		'triggerids' => array_keys($triggers),
		'editable' => true,
		'preservekeys' => true
	]);

	foreach ($triggers as $triggerid => &$trigger) {
		$trigger['editable'] = array_key_exists($triggerid, $rw_triggers);
	}
	unset($trigger);

	return [$hosts, $triggers];
}

/**
 * Creates and returns the trigger overview table for the given hosts.
 *
 * @param array  $hosts                        An array of hosts with host IDs as keys.
 * @param string $hosts[hostid][name]
 * @param string $hosts[hostid][hostid]
 * @param array  $triggers
 * @param string $triggers[<triggerid>][triggerid]
 * @param string $triggers[<triggerid>][description]
 * @param string $triggers[<triggerid>][expression]
 * @param int    $triggers[<triggerid>][value]
 * @param int    $triggers[<triggerid>][lastchange]
 * @param int    $triggers[<triggerid>][flags]
 * @param array  $triggers[<triggerid>][url]
 * @param int    $triggers[<triggerid>][priority]
 * @param array  $triggers[<triggerid>][hosts]
 * @param string $triggers[<triggerid>][hosts][][hostid]
 * @param string $triggers[<triggerid>][hosts][][name]
 * @param array  $triggers[<triggerid>]['dependencies']
 * @param string $triggers[<triggerid>]['dependencies'][]['triggerid']
 * @param string $pageFile                     The page where the element is displayed.
 * @param int    $viewMode                     Table display style: either hosts on top, or host on the left side.
 * @param string $screenId                     The ID of the screen, that contains the trigger overview table.
 * @param bool   $fullscreen                   Display mode.
 *
 * @return CTableInfo
 */
function getTriggersOverview(array $hosts, array $triggers, $pageFile, $viewMode = null, $screenId = null,
		$fullscreen = false) {
	$data = [];
	$host_names = [];
	$trcounter = [];

	$triggers = CMacrosResolverHelper::resolveTriggerNames($triggers, true);

	// Make trigger dependencies.
	if ($triggers) {
		$dependencies = getTriggerDependencies($triggers);
	}

	foreach ($triggers as $trigger) {
		$trigger_name = $trigger['description'];

		foreach ($trigger['hosts'] as $host) {
			// triggers may belong to hosts that are filtered out and shouldn't be displayed, skip them
			if (!isset($hosts[$host['hostid']])) {
				continue;
			}

			$host_names[$host['hostid']] = $host['name'];

			if (!array_key_exists($host['name'], $trcounter)) {
				$trcounter[$host['name']] = [];
			}

			if (!array_key_exists($trigger_name, $trcounter[$host['name']])) {
				$trcounter[$host['name']][$trigger_name] = 0;
			}

			$data[$trigger_name][$trcounter[$host['name']][$trigger_name]][$host['name']] = [
				'triggerid' => $trigger['triggerid'],
				'value' => $trigger['value'],
				'lastchange' => $trigger['lastchange'],
				'priority' => $trigger['priority'],
				'flags' => $trigger['flags'],
				'url' => $trigger['url'],
				'hosts' => $trigger['hosts'],
				'items' => $trigger['items'],
				'description_enabled' => ($trigger['comments'] !== ''
					|| ($trigger['editable'] && $trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL))
			];
			$trcounter[$host['name']][$trigger_name]++;
		}
	}

	$triggerTable = new CTableInfo();

	if (!$host_names) {
		return $triggerTable;
	}

	$triggerTable->makeVerticalRotation();

	order_result($host_names);

	if ($viewMode == STYLE_TOP) {
		// header
		$header = [_('Triggers')];

		foreach ($host_names as $host_name) {
			$header[] = (new CColHeader($host_name))
				->addClass('vertical_rotation')
				->setTitle($host_name);
		}
		$triggerTable->setHeader($header);

		// data
		foreach ($data as $trigger_name => $trigger_data) {
			foreach ($trigger_data as $trigger_hosts) {
				$columns = [nbsp($trigger_name)];

				foreach ($host_names as $host_name) {
					$columns[] = getTriggerOverviewCells(
						array_key_exists($host_name, $trigger_hosts) ? $trigger_hosts[$host_name] : null,
						$dependencies, $pageFile, $screenId, $fullscreen
					);
				}
				$triggerTable->addRow($columns);
			}
		}
	}
	else {
		// header
		$header = [_('Host')];

		foreach ($data as $trigger_name => $trigger_data) {
			foreach ($trigger_data as $trigger_hosts) {
				$header[] = (new CColHeader($trigger_name))
					->addClass('vertical_rotation')
					->setTitle($trigger_name);
			}
		}

		$triggerTable->setHeader($header);

		// data
		$scripts = API::Script()->getScriptsByHosts(zbx_objectValues($hosts, 'hostid'));

		foreach ($host_names as $hostId => $host_name) {
			$name = (new CLinkAction($host_name))
				->setMenuPopup(CMenuPopupHelper::getHost($hosts[$hostId], $scripts[$hostId], true, $fullscreen));

			$columns = [(new CCol($name))->addClass(ZBX_STYLE_NOWRAP)];
			foreach ($data as $trigger_data) {
				foreach ($trigger_data as $trigger_hosts) {
					$columns[] = getTriggerOverviewCells(
						array_key_exists($host_name, $trigger_hosts) ? $trigger_hosts[$host_name] : null,
						$dependencies, $pageFile, $screenId, $fullscreen
					);
				}
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
 * @param array  $dependencies  The list of trigger dependencies, prepared by getTriggerDependencies() function.
 * @param string $pageFile      The page where the element is displayed.
 * @param string $screenid
 * @param bool   $fullscreen    Display mode.
 *
 * @return CCol
 */
function getTriggerOverviewCells($trigger, $dependencies, $pageFile, $screenid = null, $fullscreen = false) {
	$ack = null;
	$css = null;
	$desc = null;
	$acknowledge = [];

	// for how long triggers should blink on status change (set by user in administration->general)
	$config = select_config();

	if ($trigger) {
		$css = getSeverityStyle($trigger['priority'], $trigger['value'] == TRIGGER_VALUE_TRUE);

		// problem trigger
		if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
			$ack = null;

			if ($config['event_ack_enable']) {
				$event = getTriggerLastProblems([$trigger['triggerid']], ['eventid', 'acknowledged']);

				if ($event) {
					$event = reset($event);

					if ($screenid !== null) {
						$acknowledge = [
							'eventid' => $event['eventid'],
							'backurl' => $pageFile.'?screenid='.$screenid
						];
					}
					else {
						$acknowledge = [
							'eventid' => $event['eventid'],
							'backurl' => $pageFile
						];
					}

					if ($event['acknowledged'] == 1) {
						$ack = (new CSpan())->addClass(ZBX_STYLE_ICON_ACKN);
					}
				}
			}
		}

		$desc = array_key_exists($trigger['triggerid'], $dependencies)
			? makeTriggerDependencies($dependencies[$trigger['triggerid']], false)
			: [];
	}

	$column = new CCol([$desc, $ack]);

	if ($css !== null) {
		$column
			->addClass($css)
			->addClass(ZBX_STYLE_CURSOR_POINTER);
	}

	if ($trigger) {
		// blinking
		$config['blink_period'] = timeUnitToSeconds($config['blink_period']);
		$duration = time() - $trigger['lastchange'];

		if ($config['blink_period'] > 0 && $duration < $config['blink_period']) {
			$column->addClass('blink');
			$column->setAttribute('data-time-to-blink', $config['blink_period'] - $duration);
			$column->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN);
		}

		$options = ['description_enabled' => $trigger['description_enabled'], 'fullscreen' => $fullscreen];
		$column->setMenuPopup(CMenuPopupHelper::getTrigger($trigger, $acknowledge, $options));
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
	$elements = [
		'hosts' => [],
		'hosts_groups' => [],
		'triggers' => []
	];

	get_map_elements($db_element, $elements);
	if (empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])) {
		return 0;
	}

	$config = select_config();

	$options = [
		'monitored' => true,
		'countOutput' => true,
		'filter' => [],
		'limit' => $config['search_limit'] + 1
	];

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
	$hostNames = [];

	$config = select_config();

	$hostIds = zbx_objectValues($trigger['hosts'], 'hostid');

	$hosts = API::Host()->get([
		'output' => ['name', 'hostid', 'status'],
		'hostids' => $hostIds,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT
	]);

	if (count($hosts) > 1) {
		order_result($hosts, 'name', ZBX_SORT_UP);
	}

	$scripts = API::Script()->getScriptsByHosts($hostIds);

	foreach ($hosts as $host) {
		$hostNames[] = (new CLinkAction($host['name']))
			->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));
		$hostNames[] = ', ';
	}
	array_pop($hostNames);

	$table = (new CTableInfo())
		->addRow([
			new CCol(_n('Host', 'Hosts', count($hosts))),
			new CCol($hostNames)
		])
		->addRow([
			new CCol(_('Trigger')),
			new CCol(CMacrosResolverHelper::resolveTriggerName($trigger))
		])
		->addRow([
			_('Severity'),
			getSeverityCell($trigger['priority'], $config)
		]);

	$trigger = CMacrosResolverHelper::resolveTriggerExpressions(zbx_toHash($trigger, 'triggerid'), [
		'html' => true,
		'resolve_usermacros' => true,
		'resolve_macros' => true,
		'sources' => ['expression', 'recovery_expression']
	]);

	$trigger = reset($trigger);

	$table
		->addRow([
			new CCol(_('Problem expression')),
			new CCol($trigger['expression'])
		])
		->addRow([
			new CCol(_('Recovery expression')),
			new CCol($trigger['recovery_expression'])
		])
		->addRow([_('Event generation'), _('Normal').((TRIGGER_MULT_EVENT_ENABLED == $trigger['type'])
			? SPACE.'+'.SPACE._('Multiple PROBLEM events')
			: '')
		]);

	if ($config['event_ack_enable']) {
		$table->addRow([_('Allow manual close'), ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
			? (new CCol(_('Yes')))->addClass(ZBX_STYLE_GREEN)
			: (new CCol(_('No')))->addClass(ZBX_STYLE_RED)
		]);
	}

	$table->addRow([_('Enabled'), ($trigger['status'] == TRIGGER_STATUS_ENABLED)
		? (new CCol(_('Yes')))->addClass(ZBX_STYLE_GREEN)
		: (new CCol(_('No')))->addClass(ZBX_STYLE_RED)
	]);

	return $table;
}

/**
 * Analyze an expression and returns expression html tree.
 *
 * @param string $expression		Trigger expression or recovery expression string.
 * @param int $type					Type can be either TRIGGER_EXPRESSION or TRIGGER_RECOVERY_EXPRESSION.
 *
 * @return array
 */
function analyzeExpression($expression, $type) {
	if (empty($expression)) {
		return ['', null];
	}

	$expressionData = new CTriggerExpression();
	if (!$expressionData->parse($expression)) {
		error($expressionData->error);
		return false;
	}

	$expressionTree[] = getExpressionTree($expressionData, 0, strlen($expressionData->expression) - 1);

	$next = [];
	$letterNum = 0;
	return buildExpressionHtmlTree($expressionTree, $next, $letterNum, 0, null, $type);
}

/**
 * Builds expression HTML tree.
 *
 * @param array 	$expressionTree 	Output of getExpressionTree() function.
 * @param array 	$next           	Parameter only for recursive call; should be empty array.
 * @param int 		$letterNum      	Parameter only for recursive call; should be 0.
 * @param int 		$level          	Parameter only for recursive call.
 * @param string 	$operator       	Parameter only for recursive call.
 * @param int		$type				Type can be either TRIGGER_EXPRESSION or TRIGGER_RECOVERY_EXPRESSION.
 *
 * @return array	Array containing the trigger expression formula as the first element and an array describing the
 *					expression tree as the second.
 */
function buildExpressionHtmlTree(array $expressionTree, array &$next, &$letterNum, $level = 0, $operator = null,
		$type) {
	$treeList = [];
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
				$levelDetails = [
					'list' => $expr,
					'id' => $element['id'],
					'expression' => [
						'value' => $element['expression']
					]
				];

				$levelErrors = expressionHighLevelErrors($element['expression']);
				if (count($levelErrors) > 0) {
					$levelDetails['expression']['levelErrors'] = $levelErrors;
				}
				$treeList[] = $levelDetails;

				list($subOutline, $subTreeList) = buildExpressionHtmlTree($element['elements'], $next, $letterNum,
					$level + 1, $element['operator'], $type
				);
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
					$url = $element['expression'];
				}
				else {
					if ($type == TRIGGER_EXPRESSION) {
						$expressionId = 'expr_'.$element['id'];
					}
					else {
						$expressionId = 'recovery_expr_'.$element['id'];
					}

					$url = (new CLinkAction($element['expression']))
						->setId($expressionId)
						->onClick('javascript: copy_expression("'.$expressionId.'", '.$type.');');
				}
				$expr = expressionLevelDraw($next, $level);
				$expr[] = SPACE;
				$expr[] = bold($letter);
				$expr[] = SPACE;
				$expr[] = $url;

				$levelDetails = [
					'list' => $expr,
					'id' => $element['id'],
					'expression' => [
						'value' => $element['expression']
					]
				];

				$levelErrors = expressionHighLevelErrors($element['expression']);
				if (count($levelErrors) > 0) {
					$levelDetails['expression']['levelErrors'] = $levelErrors;
				}
				$treeList[] = $levelDetails;
				break;
		}
	}

	return [$outline, $treeList];
}

function expressionHighLevelErrors($expression) {
	static $errors, $definedErrorPhrases;

	if (!isset($errors)) {
		$definedErrorPhrases = [
			EXPRESSION_HOST_UNKNOWN => _('Unknown host, no such host present in system'),
			EXPRESSION_HOST_ITEM_UNKNOWN => _('Unknown host item, no such item in selected host'),
			EXPRESSION_NOT_A_MACRO_ERROR => _('Given expression is not a macro'),
			EXPRESSION_FUNCTION_UNKNOWN => _('Incorrect function is used'),
			EXPRESSION_UNSUPPORTED_VALUE_TYPE => _('Incorrect item value type')
		];
		$errors = [];
	}

	if (!isset($errors[$expression])) {
		$errors[$expression] = [];
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

	$ret = [];
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
	$expr = [];
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
	$blankSymbols = [' ', "\r", "\n", "\t"];

	$expressionTree = [];
	foreach (['or', 'and'] as $operator) {
		$operatorFound = false;
		$lParentheses = -1;
		$rParentheses = -1;
		$expressions = [];
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

			$expressionTree = [
				'id' => $openSymbolNum.'_'.$closeSymbolNum,
				'expression' => substr($expressionData->expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
				'type' => 'operator',
				'operator' => $operator,
				'elements' => $expressions
			];
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
				$expressionTree = [
					'id' => $openSymbolNum.'_'.$closeSymbolNum,
					'expression' => substr($expressionData->expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
					'type' => 'expression'
				];
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
								$expressionTree[$key]['elements'][] = [
									'expression' => $newExpression,
									'type' => 'expression'
								];
							}
							else {
								$element = [
									'type' => 'operator',
									'operator' => $action,
									'elements' => [
										$expressionTree[$key],
										[
											'expression' => $newExpression,
											'type' => 'expression'
										]
									]
								];
								$expressionTree[$key] = $element;
							}
							break;
						case 'expression':
							if (!$operator || $operator != $action) {
								$element = [
									'type' => 'operator',
									'operator' => $action,
									'elements' => [
										$expressionTree[$key],
										[
											'expression' => $newExpression,
											'type' => 'expression'
										]
									]
								];
								$expressionTree[$key] = $element;
							}
							else {
								$expressionTree[] = [
									'expression' => $newExpression,
									'type' => 'expression'
								];
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
	$rule_float = [_('Numeric (float)'), 'preg_match("/^'.ZBX_PREG_NUMBER.'$/", {})'];
	$rule_int = [_('Numeric (integer)'), 'preg_match("/^'.ZBX_PREG_INT.'$/", {})'];
	$rule_0or1 = [_('0 or 1'), IN('0,1')];
	$rules = [
		// Every nested array should have two elements: label, validation.
		'integer' => [
			ITEM_VALUE_TYPE_UINT64 => $rule_int
		],
		'numeric' => [
			ITEM_VALUE_TYPE_UINT64 => $rule_int,
			ITEM_VALUE_TYPE_FLOAT => $rule_float
		],
		'numeric_as_float' => [
			ITEM_VALUE_TYPE_UINT64 => $rule_float,
			ITEM_VALUE_TYPE_FLOAT => $rule_float
		],
		'numeric_as_uint' => [
			ITEM_VALUE_TYPE_UINT64 => $rule_int,
			ITEM_VALUE_TYPE_FLOAT => $rule_int
		],
		'numeric_as_0or1' => [
			ITEM_VALUE_TYPE_UINT64 => $rule_0or1,
			ITEM_VALUE_TYPE_FLOAT => $rule_0or1
		],
		'string_as_0or1' => [
			ITEM_VALUE_TYPE_TEXT => $rule_0or1,
			ITEM_VALUE_TYPE_STR => $rule_0or1,
			ITEM_VALUE_TYPE_LOG => $rule_0or1
		],
		'string_as_uint' => [
			ITEM_VALUE_TYPE_TEXT => $rule_int,
			ITEM_VALUE_TYPE_STR => $rule_int,
			ITEM_VALUE_TYPE_LOG => $rule_int
		],
		'string_as_float' => [
			ITEM_VALUE_TYPE_TEXT => $rule_float,
			ITEM_VALUE_TYPE_STR => $rule_float,
			ITEM_VALUE_TYPE_LOG => $rule_float
		],
		'log_as_uint' => [
			ITEM_VALUE_TYPE_LOG => $rule_int
		],
		'log_as_0or1' => [
			ITEM_VALUE_TYPE_LOG => $rule_0or1
		],
		'date' => [
			'any' => ['YYYYMMDD', '{}>=19700101&&{}<=99991231']
		],
		'time' => [
			'any' => ['HHMMSS', 'preg_match("/^([01]?\d|2[0-3])([0-5]?\d)([0-5]?\d)$/", {})']
		],
		'day_of_month' => [
			'any' => ['1-31', '{}>=1&&{}<=31']
		],
		'day_of_week' => [
			'any' => ['1-7', IN('1,2,3,4,5,6,7')]
		]
	];

	$functions = [
		'abschange' => $rules['numeric'] + $rules['string_as_0or1'],
		'avg' => $rules['numeric_as_float'],
		'band' => $rules['integer'],
		'change' => $rules['numeric'] + $rules['string_as_0or1'],
		'count' => $rules['numeric_as_uint'] + $rules['string_as_uint'],
		'date' => $rules['date'],
		'dayofmonth' => $rules['day_of_month'],
		'dayofweek' => $rules['day_of_week'],
		'delta' => $rules['numeric'],
		'diff' => $rules['numeric_as_0or1'] + $rules['string_as_0or1'],
		'forecast' => $rules['numeric_as_float'],
		'fuzzytime' => $rules['numeric_as_0or1'],
		'iregexp' => $rules['string_as_0or1'],
		'last' => $rules['numeric'] + $rules['string_as_float'],
		'logeventid' => $rules['log_as_0or1'],
		'logseverity' => $rules['log_as_uint'],
		'logsource' => $rules['log_as_0or1'],
		'max' => $rules['numeric'],
		'min' => $rules['numeric'],
		'nodata' => $rules['numeric_as_0or1'] + $rules['string_as_0or1'],
		'now' => $rules['numeric_as_uint'] + $rules['string_as_uint'],
		'percentile' => $rules['numeric'],
		'prev' => $rules['numeric'] + $rules['string_as_float'],
		'regexp' => $rules['string_as_0or1'],
		'str' => $rules['string_as_0or1'],
		'strlen' => $rules['string_as_uint'],
		'sum' => $rules['numeric'],
		'time' => $rules['time'],
		'timeleft' => $rules['numeric_as_float']
	];

	$expr_data = new CTriggerExpression();
	$expression = $expr_data->parse($expr);

	if (!$expression) {
		return EXPRESSION_NOT_A_MACRO_ERROR;
	}

	switch (true) {
		case ($expression->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_MACRO)):
			$result = [
				'type' => T_ZBX_STR,
				'value_type' => $rule_0or1[0],
				'validation' => $rule_0or1[1]
			];
			break;

		case ($expression->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_USER_MACRO)):
		case ($expression->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_LLD_MACRO)):
			$result = [
				'type' => T_ZBX_STR,
				'value_type' => $rule_float[0],
				'validation' => $rule_float[1]
			];
			break;

		case ($expression->hasTokenOfType(CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO)):
			$expr_part = reset($expr_data->expressions);

			if (!array_key_exists($expr_part['functionName'], $functions)) {
				$result = EXPRESSION_FUNCTION_UNKNOWN;
				break;
			}

			$host = API::Host()->get([
				'output' => ['hostid'],
				'filter' => ['host' => [$expr_part['host']]],
				'templated_hosts' => true
			]);

			if (!$host) {
				$result = EXPRESSION_HOST_UNKNOWN;
				break;
			}

			$item = API::Item()->get([
				'output' => ['value_type'],
				'hostids' => $host[0]['hostid'],
				'filter' => [
					'key_' => [$expr_part['item']]
				],
				'webitems' => true
			]);

			if (!$item) {
				$item = API::ItemPrototype()->get([
					'output' => ['value_type'],
					'hostids' => $host[0]['hostid'],
					'filter' => [
						'key_' => [$expr_part['item']]
					]
				]);
			}

			if (!$item) {
				$result = EXPRESSION_HOST_ITEM_UNKNOWN;
				break;
			}

			$function = $functions[$expr_part['functionName']];
			$value_type = $item[0]['value_type'];

			if (array_key_exists('any', $function)) {
				$value_type = 'any';
			}
			elseif (!array_key_exists($value_type, $function)) {
				$result = EXPRESSION_UNSUPPORTED_VALUE_TYPE;
				break;
			}

			$result = [
				'type' => T_ZBX_STR,
				'value_type' => $function[$value_type][0],
				'validation' => $function[$value_type][1]
			];
			break;

		default:
			$result = EXPRESSION_NOT_A_MACRO_ERROR;
			break;
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
	// Sort by longest array key which in this case contains macros.
	uksort($replaceFunctionMacros, function ($key1, $key2) {
		$s1 = strlen($key1);
		$s2 = strlen($key2);

		if ($s1 == $s2) {
			return 0;
		}

		return ($s1 > $s2) ? -1 : 1;
	});

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
	$replaceOperators = ['not' => '!', '=' => '=='];
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

	if (!preg_match('/(?P<value>[\-+]?([.][0-9]+|[0-9]+[.]?[0-9]*))(?P<mult>['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)/',
			$value, $arr)) {
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
 * @param bool   $forced
 *
 * @return string
 */
function quoteFunctionParam($param, $forced = false) {
	if (!$forced) {
		if (!isset($param[0]) || ($param[0] != '"' && false === strpbrk($param, ',)'))) {
			return $param;
		}
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

	return _('Disabled');
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
		return ($state == TRIGGER_STATE_UNKNOWN) ?
			ZBX_STYLE_GREY :
			ZBX_STYLE_GREEN;
	}

	return ZBX_STYLE_RED;
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
	$sort = [];

	foreach ($triggers as $key => $trigger) {
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED) {
			$sort[$key] = ($trigger['state'] == TRIGGER_STATE_UNKNOWN) ? 2 : 0;
		}
		else {
			$sort[$key] = 1;
		}
	}

	if ($sortorder == ZBX_SORT_UP) {
		asort($sort);
	}
	else {
		arsort($sort);
	}

	$sortedTriggers = [];
	foreach ($sort as $key => $val) {
		$sortedTriggers[$key] = $triggers[$key];
	}
	$triggers = $sortedTriggers;
}

/**
 * Create the list of hosts for each trigger.
 *
 * @param array  $triggers
 * @param string $triggers[]['triggerid']
 * @param array  $triggers[]['hosts']
 * @param string $triggers[]['hosts'][]['hostid']
 *
 * @return array
 */
function getTriggersHostsList(array $triggers) {
	$hostids = [];

	foreach ($triggers as $trigger) {
		foreach ($trigger['hosts'] as $host) {
			$hostids[$host['hostid']] = true;
		}
	}

	$db_hosts = $hostids
		? API::Host()->get([
			'output' => ['hostid', 'name', 'status', 'maintenanceid', 'maintenance_status', 'maintenance_type'],
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'hostids' => array_keys($hostids),
			'preservekeys' => true
		])
		: [];

	$triggers_hosts = [];
	foreach ($triggers as $trigger) {
		$triggers_hosts[$trigger['triggerid']] = [];

		foreach ($trigger['hosts'] as $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				continue;
			}

			$triggers_hosts[$trigger['triggerid']][] = $db_hosts[$host['hostid']];
		}
		order_result($triggers_hosts[$trigger['triggerid']], 'name');
	}

	return $triggers_hosts;
}

/**
 * Make the list of hosts for each trigger.
 *
 * @param array  $triggers_hosts
 * @param string $triggers_hosts[<triggerid>][]['hostid']
 * @param string $triggers_hosts[<triggerid>][]['name']
 * @param int    $triggers_hosts[<triggerid>][]['status']
 * @param string $triggers_hosts[<triggerid>][]['maintenanceid']
 * @param int    $triggers_hosts[<triggerid>][]['maintenance_status']
 * @param int    $triggers_hosts[<triggerid>][]['maintenance_type']
 * @param int    $triggers_hosts[<triggerid>][]['graphs']              The number of graphs.
 * @param int    $triggers_hosts[<triggerid>][]['screens']             The number of screens.
 * @param bool   $fullscreen				                           Fullscreen mode.
 *
 * @return array
 */
function makeTriggersHostsList(array $triggers_hosts, $fullscreen = false) {
	$db_maintenances = [];
	$scripts_by_hosts = [];

	$hostids = [];
	$maintenanceids = [];

	foreach ($triggers_hosts as $hosts) {
		foreach ($hosts as $host) {
			$hostids[$host['hostid']] = true;
			if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}
	}

	if ($hostids) {
		if ($maintenanceids) {
			$db_maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		$scripts_by_hosts = API::Script()->getScriptsByHosts(array_keys($hostids));
	}

	foreach ($triggers_hosts as &$hosts) {
		$trigger_hosts = [];

		foreach ($hosts as $host) {
			$scripts_by_host = array_key_exists($host['hostid'], $scripts_by_hosts)
				? $scripts_by_hosts[$host['hostid']]
				: [];
			$host_name = (new CLinkAction($host['name']))
				->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts_by_host, true, $fullscreen));

			// add maintenance icon with hint if host is in maintenance
			if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenance_icon = (new CSpan())
					->addClass(ZBX_STYLE_ICON_MAINT)
					->addClass(ZBX_STYLE_CURSOR_POINTER);

				if (array_key_exists($host['maintenanceid'], $db_maintenances)) {
					$db_maintenance = $db_maintenances[$host['maintenanceid']];

					$hint = $db_maintenance['name'].' ['.($host['maintenance_type']
						? _('Maintenance without data collection')
						: _('Maintenance with data collection')).']';

					if ($db_maintenance['description'] !== '') {
						$hint .= "\n".$db_maintenance['description'];
					}

					$maintenance_icon->setHint($hint);
				}

				$host_name = (new CSpan([$host_name, $maintenance_icon]))->addClass(ZBX_STYLE_REL_CONTAINER);
			}

			if ($trigger_hosts) {
				$trigger_hosts[] = ', ';
			}
			$trigger_hosts[] = $host_name;
		}

		$hosts = $trigger_hosts;
	}
	unset($hosts);

	return $triggers_hosts;
}

/**
 * Check if user has read permissions for triggers.
 *
 * @param $triggerids
 *
 * @return bool
 */
function isReadableTriggers(array $triggerids) {
	return count($triggerids) == API::Trigger()->get([
		'triggerids' => $triggerids,
		'countOutput' => true
	]);
}

/**
 * Get last problems by given trigger IDs.
 *
 * @param array $triggerids
 * @param array $output         List of output fields.
 *
 * @return array
 */
function getTriggerLastProblems(array $triggerids, array $output) {
	$problems = DBfetchArray(DBselect(
		'SELECT '.implode(',e.', $output).
		' FROM events e'.
		' JOIN ('.
			'SELECT e2.source,e2.object,e2.objectid,MAX(clock) AS clock'.
			' FROM events e2'.
			' WHERE e2.source='.EVENT_SOURCE_TRIGGERS.
				' AND e2.object='.EVENT_OBJECT_TRIGGER.
				' AND e2.value='.TRIGGER_VALUE_TRUE.
				' AND '.dbConditionInt('e2.objectid', $triggerids).
			' GROUP BY e2.source,e2.object,e2.objectid'.
		') e3 ON e3.source=e.source'.
			' AND e3.object=e.object'.
			' AND e3.objectid=e.objectid'.
			' AND e3.clock=e.clock'
	));

	return $problems;
}

/**
 * Returns a list of the trigger dependencies.
 *
 * @param array  $triggers
 * @param array  $triggers[<triggerid>]['dependencies']
 * @param string $triggers[<triggerid>]['dependencies'][]['triggerid']
 *
 * @return array
 */
function getTriggerDependencies(array $triggers) {
	$triggerids = [];
	$triggerids_up = [];
	$triggerids_down = [];

	// "Depends on" triggers.
	foreach ($triggers as $triggerid => $trigger) {
		foreach ($trigger['dependencies'] as $dependency) {
			$triggerids[$dependency['triggerid']] = true;
			$triggerids_up[$triggerid][] = $dependency['triggerid'];
		}
	}

	// "Dependent" triggers.
	$db_trigger_depends = DBselect(
		'SELECT triggerid_down,triggerid_up'.
		' FROM trigger_depends'.
		' WHERE '.dbConditionInt('triggerid_up', array_keys($triggers))
	);

	while ($row = DBfetch($db_trigger_depends)) {
		$triggerids[$row['triggerid_down']] = true;
		$triggerids_down[$row['triggerid_up']][] = $row['triggerid_down'];
	}

	$dependencies = [];

	if (!$triggerids) {
		return $dependencies;
	}

	$db_triggers = API::Trigger()->get([
		'output' => ['expression', 'description'],
		'triggerids' => array_keys($triggerids),
		'preservekeys' => true
	]);
	$db_triggers = CMacrosResolverHelper::resolveTriggerNames($db_triggers);

	foreach ($triggerids_up as $triggerid_up => $triggerids) {
		foreach ($triggerids as $triggerid) {
			$dependencies[$triggerid_up]['down'][] = array_key_exists($triggerid, $db_triggers)
				? $db_triggers[$triggerid]['description']
				: _('Inaccessible trigger');
		}
	}

	foreach ($triggerids_down as $triggerid_down => $triggerids) {
		foreach ($triggerids as $triggerid) {
			$dependencies[$triggerid_down]['up'][] = array_key_exists($triggerid, $db_triggers)
				? $db_triggers[$triggerid]['description']
				: _('Inaccessible trigger');
		}
	}

	return $dependencies;
}

/**
 * Returns icons with tooltips for triggers with dependencies.
 *
 * @param array  $dependencies
 * @param array  $dependencies['up']    (optional) The list of "Dependent" triggers.
 * @param array  $dependencies['down']  (optional) The list of "Depeneds on" triggers.
 * @param bool   $freeze_on_click
 *
 * @return array
 */
function makeTriggerDependencies(array $dependencies, $freeze_on_click = true) {
	$result = [];

	foreach (['down', 'up'] as $type) {
		if (array_key_exists($type, $dependencies)) {
			$header = ($type === 'down') ? _('Depends on') : _('Dependent');
			$class = ($type === 'down') ? ZBX_STYLE_ICON_DEPEND_DOWN : ZBX_STYLE_ICON_DEPEND_UP;

			$table = (new CTableInfo())
				->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->setHeader([$header]);

			foreach ($dependencies[$type] as $description) {
				$table->addRow($description);
			}

			$result[] = (new CSpan())
				->addClass($class)
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->setHint($table, '', $freeze_on_click);
		}
	}

	return $result;
}
