<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


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
	$color_class = null;
	$blinks = null;

	// Color class for text and blinking depends on trigger value and whether event is acknowledged.
	if ($triggerValue == TRIGGER_VALUE_TRUE && !$isAcknowledged) {
		$color_class = ZBX_STYLE_PROBLEM_UNACK_FG;
		$blinks = CSettingsHelper::get(CSettingsHelper::PROBLEM_UNACK_STYLE);
	}
	elseif ($triggerValue == TRIGGER_VALUE_TRUE && $isAcknowledged) {
		$color_class = ZBX_STYLE_PROBLEM_ACK_FG;
		$blinks = CSettingsHelper::get(CSettingsHelper::PROBLEM_ACK_STYLE);
	}
	elseif ($triggerValue == TRIGGER_VALUE_FALSE && !$isAcknowledged) {
		$color_class = ZBX_STYLE_OK_UNACK_FG;
		$blinks = CSettingsHelper::get(CSettingsHelper::OK_UNACK_STYLE);
	}
	elseif ($triggerValue == TRIGGER_VALUE_FALSE && $isAcknowledged) {
		$color_class = ZBX_STYLE_OK_ACK_FG;
		$blinks = CSettingsHelper::get(CSettingsHelper::OK_ACK_STYLE);
	}

	if ($color_class != null && $blinks != null) {
		$object->addClass($color_class);

		// blinking
		$timeSinceLastChange = time() - $triggerLastChange;
		$blink_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::BLINK_PERIOD));

		if ($blinks && $timeSinceLastChange < $blink_period) {
			$object->addClass('js-blink'); // elements with this class will blink
			$object->setAttribute('data-time-to-blink', $blink_period - $timeSinceLastChange);
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

function get_trigger_by_triggerid($triggerid) {
	$db_trigger = DBfetch(DBselect('SELECT t.* FROM triggers t WHERE t.triggerid='.zbx_dbstr($triggerid)));
	if (!empty($db_trigger)) {
		return $db_trigger;
	}
	error(_s('No trigger with trigger ID "%1$s".', $triggerid));

	return false;
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

/**
 * Prepare arrays containing only hosts and triggers that will be shown results table.
 *
 * @param array $db_hosts
 * @param array $db_triggers
 *
 * @return array
 */
function getTriggersOverviewTableData(array $db_hosts, array $db_triggers): array {
	// Prepare triggers to show in results table.
	$triggers_by_name = [];
	foreach ($db_triggers as $trigger) {
		foreach ($trigger['hosts'] as $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				continue;
			}

			$triggers_by_name[$trigger['description']][$host['hostid']] = $trigger['triggerid'];
		}
	}

	$limit = (int) CSettingsHelper::get(CSettingsHelper::MAX_OVERVIEW_TABLE_SIZE);
	$exceeded_trigs = (count($triggers_by_name) > $limit);
	$triggers_by_name = array_slice($triggers_by_name, 0, $limit, true);
	foreach ($triggers_by_name as $name => $triggers) {
		$triggers_by_name[$name] = array_slice($triggers, 0, $limit, true);
	}

	// Prepare hosts to show in results table.
	$exceeded_hosts = false;
	$hosts_by_name = [];
	foreach ($db_hosts as $host) {
		if (count($hosts_by_name) >= $limit) {
			$exceeded_hosts = true;
			break;
		}
		else {
			$hosts_by_name[$host['name']] = $host['hostid'];
		}
	}

	return [$triggers_by_name, $hosts_by_name, ($exceeded_hosts || $exceeded_trigs)];
}

/**
 * @param array   $groupids
 * @param array   $host_options
 * @param array   $trigger_options
 * @param array   $problem_options
 * @param int     $problem_options['min_severity']         (optional) Minimal problem severity.
 * @param int     $problem_options['show_suppressed']      (optional) Whether to show triggers with suppressed problems.
 * @param int     $problem_options['time_from']            (optional) The time starting from which the problems were created.
 * @param array   $problem_options['tags']                 (optional)
 * @param string  $problem_options['tags'][]['tag']        (optional)
 * @param int     $problem_options['tags'][]['operation']  (optional)
 * @param string  $problem_options['tags'][]['value']      (optional)
 * @param int     $problem_options['evaltype']		       (optional)
 *
 * @return array
 */
function getTriggersOverviewData(array $groupids, array $host_options = [], array $trigger_options = [],
		array $problem_options = []): array {

	$host_options = [
		'output' => ['hostid', 'name'],
		'groupids' => $groupids ? $groupids : null,
		'with_monitored_triggers' => true,
		'preservekeys' => true
	] + $host_options;

	$trigger_options = [
		'output' => ['triggerid', 'expression', 'description', 'value', 'priority', 'lastchange', 'flags', 'comments',
			'manual_close'
		],
		'selectHosts' => ['hostid', 'name'],
		'selectDependencies' => ['triggerid'],
		'monitored' => true
	] + $trigger_options;

	$problem_options += [
		'show_suppressed' => ZBX_PROBLEM_SUPPRESSED_FALSE
	];

	$limit = 0;
	do {
		$limit += (int) CSettingsHelper::get(CSettingsHelper::MAX_OVERVIEW_TABLE_SIZE);

		$db_hosts = API::Host()->get(['limit' => $limit + 1] + $host_options);
		$fetch_more = (count($db_hosts) > $limit);

		$db_triggers = getTriggersWithActualSeverity([
			'hostids' => array_keys($db_hosts)
		] + $trigger_options, $problem_options);

		if (!$db_triggers) {
			$db_hosts = [];
		}

		// Unset hosts without having matching triggers.
		$represented_hosts = [];
		foreach ($db_triggers as $trigger) {
			$hostids = array_column($trigger['hosts'], 'hostid');
			$represented_hosts += array_combine($hostids, $hostids);
		}

		$db_hosts = array_intersect_key($db_hosts, $represented_hosts);
	} while ($fetch_more && count($db_hosts) < $limit);

	CArrayHelper::sort($db_hosts, [
		['field' => 'name', 'order' => ZBX_SORT_UP]
	]);

	$db_triggers = CMacrosResolverHelper::resolveTriggerNames($db_triggers, true);
	$dependencies = $db_triggers ? getTriggerDependencies($db_triggers) : [];

	CArrayHelper::sort($db_triggers, [
		['field' => 'description', 'order' => ZBX_SORT_UP]
	]);

	[$triggers_by_name, $hosts_by_name, $exceeded_limit] = getTriggersOverviewTableData($db_hosts, $db_triggers);

	return [$db_hosts, $db_triggers, $dependencies, $triggers_by_name, $hosts_by_name, $exceeded_limit];
}

/**
 * Get triggers data with priority set to highest priority of unresolved problems generated by this trigger.
 *
 * @param array $trigger_options                           API options. Array 'output' should contain 'value', option
 *                                                         'preservekeys' should be set to true.
 * @param array   $problem_options
 * @param int     $problem_options['show_suppressed']      Whether to show triggers with suppressed problems.
 * @param int     $problem_options['min_severity']         (optional) Minimal problem severity.
 * @param int     $problem_options['time_from']            (optional) The time starting from which the problems were
 *                                                         created.
 * @param bool    $problem_options['acknowledged']         (optional) Whether to show triggers with acknowledged
 *                                                         problems.
 * @param array   $problem_options['tags']                 (optional)
 * @param string  $problem_options['tags'][]['tag']        (optional)
 * @param int     $problem_options['tags'][]['operation']  (optional)
 * @param string  $problem_options['tags'][]['value']      (optional)
 * @param int     $problem_options['evaltype']		       (optional)
 *
 * @return array
 */
function getTriggersWithActualSeverity(array $trigger_options, array $problem_options) {
	$problem_options += [
		'min_severity' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
		'show_suppressed' => null,
		'show_recent' => null,
		'time_from' => null,
		'acknowledged' => null
	];

	$triggers = API::Trigger()->get(['preservekeys' => true] + $trigger_options);

	$nondependent_trigger_options = [
		'output' => [],
		'triggerids' => array_keys($triggers),
		'skipDependent' => true,
		'preservekeys' => true
	];

	$nondependent_triggers = API::Trigger()->get($nondependent_trigger_options);

	CArrayHelper::sort($triggers, ['description']);

	if ($triggers) {
		$problem_stats = [];

		foreach ($triggers as $triggerid => &$trigger) {
			$trigger['priority'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
			$trigger['resolved'] = true;

			$problem_stats[$triggerid] = [
				'has_resolved' => false,
				'has_unresolved' => false,
				'has_resolved_unacknowledged' => false,
				'has_unresolved_unacknowledged' => false
			];

			if ($trigger['value'] == TRIGGER_VALUE_TRUE && !array_key_exists($triggerid, $nondependent_triggers)) {
				$trigger['value'] = TRIGGER_VALUE_FALSE;
			}
		}
		unset($trigger);

		$problems = API::Problem()->get([
			'output' => ['eventid', 'acknowledged', 'objectid', 'severity', 'r_eventid'],
			'objectids' => array_keys($triggers),
			'suppressed' => ($problem_options['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_FALSE) ? false : null,
			'symptom' => false,
			'recent' => $problem_options['show_recent'],
			'acknowledged' => $problem_options['acknowledged'],
			'time_from' => $problem_options['time_from'],
			'tags' => array_key_exists('tags', $problem_options) && $problem_options['tags']
				? $problem_options['tags']
				: null,
			'evaltype' => array_key_exists('evaltype', $problem_options)
				? $problem_options['evaltype']
				: TAG_EVAL_TYPE_AND_OR
		]);

		foreach ($problems as $problem) {
			$triggerid = $problem['objectid'];

			if ($problem['r_eventid'] == 0 && array_key_exists($triggerid, $nondependent_triggers)) {
				$triggers[$triggerid]['resolved'] = false;
			}

			$triggers[$triggerid]['problem']['eventid'] = $problem['eventid'];

			if ($triggers[$triggerid]['priority'] < $problem['severity']) {
				$triggers[$triggerid]['priority'] = $problem['severity'];
			}

			if ($problem['r_eventid'] == 0) {
				$problem_stats[$triggerid]['has_unresolved'] = true;
				if ($problem['acknowledged'] == 0 && $problem['severity'] >= $problem_options['min_severity']) {
					$problem_stats[$triggerid]['has_unresolved_unacknowledged'] = true;
				}
			}
			else {
				$problem_stats[$triggerid]['has_resolved'] = true;
				if ($problem['acknowledged'] == 0 && $problem['severity'] >= $problem_options['min_severity']) {
					$problem_stats[$triggerid]['has_resolved_unacknowledged'] = true;
				}
			}
		}

		foreach ($triggers as $triggerid => &$trigger) {
			$stats = $problem_stats[$triggerid];

			$trigger['problem']['acknowledged'] = (
				// Trigger has only resolved problems, all acknowledged.
				($stats['has_resolved'] && !$stats['has_resolved_unacknowledged'] && !$stats['has_unresolved'])
					// Trigger has unresolved problems, all acknowledged.
					|| ($stats['has_unresolved'] && !$stats['has_unresolved_unacknowledged'])
			) ? 1 : 0;

			$trigger['value'] = ($triggers[$triggerid]['resolved'] === true)
				? TRIGGER_VALUE_FALSE
				: TRIGGER_VALUE_TRUE;

			if (($stats['has_resolved'] || $stats['has_unresolved'])
					&& $trigger['priority'] >= $problem_options['min_severity']) {
				continue;
			}

			if (!array_key_exists('only_true', $trigger_options)
					|| ($trigger_options['only_true'] === null && $trigger_options['filter']['value'] === null)) {
				// Overview type = 'Data', Maps, Dashboard or Overview 'show any' mode.
				$trigger['value'] = TRIGGER_VALUE_FALSE;
			}
			else {
				unset($triggers[$triggerid]);
			}
		}
		unset($trigger);
	}

	return $triggers;
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
		}

		$min = $startTime;
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

		$diff = max($clock - $time, 0);
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

	$options = [
		'monitored' => true,
		'countOutput' => true,
		'filter' => [],
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
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

/**
 * Make trigger info block.
 *
 * @param array  $trigger  Trigger described in info block.
 * @param string $eventid  Associated event ID.
 *
 * @return object
 */
function make_trigger_details($trigger, $eventid) {
	$hostNames = [];

	$hostIds = zbx_objectValues($trigger['hosts'], 'hostid');

	$hosts = API::Host()->get([
		'output' => ['name', 'hostid', 'status'],
		'hostids' => $hostIds
	]);

	if (count($hosts) > 1) {
		order_result($hosts, 'name');
	}

	foreach ($hosts as $host) {
		$hostNames[] = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']));
		$hostNames[] = ', ';
	}
	array_pop($hostNames);

	$table = (new CTableInfo())
		->addRow([
			new CCol(_n('Host', 'Hosts', count($hosts))),
			(new CCol($hostNames))->addClass(ZBX_STYLE_WORDBREAK)
		])
		->addRow([
			new CCol(_('Trigger')),
			new CCol((new CLinkAction(CMacrosResolverHelper::resolveTriggerName($trigger)))
				->addClass(ZBX_STYLE_WORDBREAK)
				->setMenuPopup(CMenuPopupHelper::getTrigger([
					'triggerid' => $trigger['triggerid'],
					'backurl' => (new CUrl('tr_events.php'))
						->setArgument('triggerid', $trigger['triggerid'])
						->setArgument('eventid', $eventid)
						->getUrl(),
					'eventid' => $eventid,
					'show_rank_change_cause' => true
				]))
			)
		])
		->addRow([
			_('Severity'),
			CSeverityHelper::makeSeverityCell((int) $trigger['priority'])
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
			new CCol((new CDiv($trigger['expression']))->addClass(ZBX_STYLE_WORDBREAK))
		])
		->addRow([
			new CCol(_('Recovery expression')),
			new CCol((new CDiv($trigger['recovery_expression']))->addClass(ZBX_STYLE_WORDBREAK))
		])
		->addRow([_('Event generation'), _('Normal').((TRIGGER_MULT_EVENT_ENABLED == $trigger['type'])
			? ' + '._('Multiple PROBLEM events')
			: '')
		]);

	$table->addRow([_('Allow manual close'), ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
		? (new CCol(_('Yes')))->addClass(ZBX_STYLE_GREEN)
		: (new CCol(_('No')))->addClass(ZBX_STYLE_RED)
	]);

	$table->addRow([_('Enabled'), ($trigger['status'] == TRIGGER_STATUS_ENABLED)
		? (new CCol(_('Yes')))->addClass(ZBX_STYLE_GREEN)
		: (new CCol(_('No')))->addClass(ZBX_STYLE_RED)
	]);

	return $table;
}

/**
 * Analyze an expression and returns expression html tree.
 *
 * @param string $expression  Trigger expression or recovery expression string.
 * @param int    $type        Type can be either TRIGGER_EXPRESSION or TRIGGER_RECOVERY_EXPRESSION.
 * @param string $error       [OUT] An error message.
 *
 * @return array|bool
 */
function analyzeExpression(string $expression, int $type, ?string &$error = null) {
	if ($expression === '') {
		return ['', null];
	}

	$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

	if ($expression_parser->parse($expression) != CParser::PARSE_SUCCESS) {
		$error = $expression_parser->getError();

		return false;
	}

	$expression_tree[] = getExpressionTree($expression_parser, 0, $expression_parser->getLength() - 1);

	$next = [];
	$letter_num = 0;

	return buildExpressionHtmlTree($expression_tree, $next, $letter_num, 0, null, $type);
}

/**
 * Builds expression HTML tree.
 *
 * @param array  $expressionTree  Output of getExpressionTree() function.
 * @param array  $next            Parameter only for recursive call; should be empty array.
 * @param int    $letterNum       Parameter only for recursive call; should be 0.
 * @param int    $level           Parameter only for recursive call.
 * @param string $operator        Parameter only for recursive call.
 * @param int    $type            Type can be either TRIGGER_EXPRESSION or TRIGGER_RECOVERY_EXPRESSION.
 *
 * @return array  Array containing the trigger expression formula as the first element and an array describing the
 *                expression tree as the second.
 */
function buildExpressionHtmlTree(array $expressionTree, array &$next, &$letterNum, $level, $operator, $type) {
	$treeList = [];
	$outline = '';

	end($expressionTree);
	$lastKey = key($expressionTree);

	foreach ($expressionTree as $key => $element) {
		switch ($element['type']) {
			case 'operator':
				$next[$level] = ($key != $lastKey);
				$expr = expressionLevelDraw($next, $level);
				$expr[] = NBSP();
				$expr[] = ($element['operator'] === 'and') ? _('And') : _('Or');
				$levelDetails = [
					'list' => $expr,
					'id' => $element['id'],
					'expression' => [
						'value' => $element['expression']
					]
				];

				$levelErrors = expressionHighLevelErrors($element['expression']);
				if ($levelErrors) {
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
						->setId($expressionId);
				}

				$expr = expressionLevelDraw($next, $level);
				$expr[] = NBSP();
				$expr[] = bold($letter);
				$expr[] = NBSP();
				$expr[] = $url;

				$levelDetails = [
					'list' => $expr,
					'id' => $element['id'],
					'expression' => [
						'value' => $element['expression']
					]
				];

				$levelErrors = expressionHighLevelErrors($element['expression']);
				if ($levelErrors) {
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
		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
		if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
			$tokens = $expression_parser->getResult()->getTokensOfTypes([
				CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
				CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION
			]);
			foreach ($tokens as $token) {
				$info = get_item_function_info($token['match']);

				if (!is_array($info) && isset($definedErrorPhrases[$info])) {
					if (!isset($errors[$expression][$token['match']])) {
						$errors[$expression][$token['match']] = $definedErrorPhrases[$info];
					}
				}
			}
		}
	}

	$ret = [];
	if (!$errors[$expression]) {
		return $ret;
	}

	$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
	if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
		$tokens = $expression_parser->getResult()->getTokensOfTypes([
			CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
			CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION
		]);
		foreach ($tokens as $token) {
			if (isset($errors[$expression][$token['match']])) {
				$ret[$token['match']] = $errors[$expression][$token['match']];
			}
		}
	}

	return $ret;
}

/**
 * Draw level for trigger expression builder tree.
 *
 * @param array $next
 * @param int   $level
 *
 * @return array
 */
function expressionLevelDraw(array $next, $level) {
	$expr = [];
	for ($i = 1; $i <= $level; $i++) {
		if ($i == $level) {
			$class_name = $next[$i] ? ZBX_ICON_TREE_TOP_RIGHT_BOTTOM_SMALL : ZBX_ICON_TREE_TOP_RIGHT_SMALL;
		}
		else {
			$class_name = $next[$i] ? ZBX_ICON_TREE_TOP_BOTTOM_SMALL : ZBX_STYLE_ICON_EMPTY_SMALL;
		}

		$expr[] = (new CSpan(''))->addClass($class_name);
	}
	return $expr;
}

/**
 * Makes tree of expression elements
 *
 * Expression:
 *   "last(/host1/system.cpu.util[,iowait], 0) > 50 and last(/host2/system.cpu.util[,iowait], 0) > 50"
 * Result:
 *   [
 *     [0] => [
 *       'id' => '0_94',
 *       'type' => 'operator',
 *       'operator' => 'and',
 *       'elements' => [
 *         [0] => [
 *           'id' => '0_44',
 *           'type' => 'expression',
 *           'expression' => 'last(/host1/system.cpu.util[,iowait], 0) > 50'
 *         ],
 *         [1] => [
 *           'id' => '50_94',
 *           'type' => 'expression',
 *           'expression' => 'last(/host2/system.cpu.util[,iowait], 0) > 50'
 *         ]
 *       ]
 *     ]
 *   ]
 *
 * @param CExpressionParser $expression_parser
 * @param int $start
 * @param int $end
 *
 * @return array
 */
function getExpressionTree(CExpressionParser $expression_parser, int $start, int $end) {
	$tokens = array_column($expression_parser->getResult()->getTokens(), null, 'pos');
	$expression = $expression_parser->getMatch();

	$expressionTree = [];
	foreach (['or', 'and'] as $operator) {
		$operatorFound = false;
		$lParentheses = -1;
		$rParentheses = -1;
		$expressions = [];

		$openSymbolNum = $start;
		while (strpos(CExpressionParser::WHITESPACES, $expression[$openSymbolNum]) !== false) {
			$openSymbolNum++;
		}

		for ($i = $openSymbolNum, $level = 0; $i <= $end; $i++) {
			if (array_key_exists($i, $tokens)) {
				switch ($tokens[$i]['type']) {
					case CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE:
						if ($level == 0) {
							$lParentheses = $i;
						}
						$level++;
						break;

					case CExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE:
						$level--;
						if ($level == 0) {
							$rParentheses = $i;
						}
						break;

					case CExpressionParserResult::TOKEN_TYPE_OPERATOR:
						/*
						 * Once reached the end of a complete expression, parse the expression on the left side of the
						 * operator.
						 */
						if ($level == 0 && $tokens[$i]['match'] === $operator) {
							// Find the last symbol of the expression before the operator.
							$closeSymbolNum = $i - 1;

							// Trim blank symbols after the expression.
							while (strpos(CExpressionParser::WHITESPACES, $expression[$closeSymbolNum]) !== false) {
								$closeSymbolNum--;
							}

							$expressions[] = getExpressionTree($expression_parser, $openSymbolNum, $closeSymbolNum);
							$openSymbolNum = $i + $tokens[$i]['length'];
							$operatorFound = true;
						}
						break;
				}
				$i += $tokens[$i]['length'] - 1;
			}
		}

		// Trim blank symbols in the end of the trigger expression.
		$closeSymbolNum = $end;
		while (strpos(CExpressionParser::WHITESPACES, $expression[$closeSymbolNum]) !== false) {
			$closeSymbolNum--;
		}

		/*
		 * Once found a whole expression and parsed the expression on the left side of the operator, parse the
		 * expression on the right.
		 */
		if ($operatorFound) {
			$expressions[] = getExpressionTree($expression_parser, $openSymbolNum, $closeSymbolNum);

			// Trim blank symbols in the beginning of the trigger expression.
			$openSymbolNum = $start;
			while (strpos(CExpressionParser::WHITESPACES, $expression[$openSymbolNum]) !== false) {
				$openSymbolNum++;
			}

			// Trim blank symbols in the end of the trigger expression.
			$closeSymbolNum = $end;
			while (strpos(CExpressionParser::WHITESPACES, $expression[$closeSymbolNum]) !== false) {
				$closeSymbolNum--;
			}

			$expressionTree = [
				'id' => $openSymbolNum.'_'.$closeSymbolNum,
				'expression' => substr($expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
				'type' => 'operator',
				'operator' => $operator,
				'elements' => $expressions
			];
			break;
		}
		// If finding both operators failed, it means there's only one expression return the result.
		elseif ($operator === 'and') {
			// Trim extra parentheses.
			if ($openSymbolNum == $lParentheses && $closeSymbolNum == $rParentheses) {
				$openSymbolNum++;
				$closeSymbolNum--;

				$expressionTree = getExpressionTree($expression_parser, $openSymbolNum, $closeSymbolNum);
			}
			// No extra parentheses remain, return the result.
			else {
				$expressionTree = [
					'id' => $openSymbolNum.'_'.$closeSymbolNum,
					'expression' => substr($expression, $openSymbolNum, $closeSymbolNum - $openSymbolNum + 1),
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
 * - and - add an expression using "and";
 * - or  - add an expression using "or";
 * - r   - replace;
 * - R   - remove.
 *
 * @param string $expression
 * @param string $expression_id   Element identifier like "0_55".
 * @param string $action          Action to perform.
 * @param string $new_expression  Expression for AND, OR or replace actions.
 * @param string $error           [OUT] An error message.
 *
 * @return bool|string  Returns new expression or false if expression is incorrect.
 */
function remakeExpression($expression, $expression_id, $action, $new_expression, ?string &$error = null) {
	if ($expression === '') {
		return false;
	}

	$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
	if ($action !== 'R' && $expression_parser->parse($new_expression) != CParser::PARSE_SUCCESS) {
		$error = $expression_parser->getError();
		return false;
	}

	if ($expression_parser->parse($expression) != CParser::PARSE_SUCCESS) {
		$error = $expression_parser->getError();
		return false;
	}

	$expression_tree[] = getExpressionTree($expression_parser, 0, $expression_parser->getLength() - 1);

	if (rebuildExpressionTree($expression_tree, $expression_id, $action, $new_expression)) {
		$expression = makeExpression($expression_tree);
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

function get_item_function_info(string $expr) {
	$rule_float = ['value_type' => _('Numeric (float)'), 'values' => null];
	$rule_int = ['value_type' => _('Numeric (integer)'), 'values' => null];
	$rule_str = ['value_type' => _('String'), 'values' => null];
	$rule_any = ['value_type' => _('Any'), 'values' => null];
	$rule_0or1 = ['value_type' => _('0 or 1'), 'values' => [0 => 0, 1 => 1]];
	$rules = [
		// Every nested array should have two elements: label, values.
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
		'string' => [
			ITEM_VALUE_TYPE_TEXT => $rule_str,
			ITEM_VALUE_TYPE_STR => $rule_str,
			ITEM_VALUE_TYPE_LOG => $rule_str
		],
		'log_as_uint' => [
			ITEM_VALUE_TYPE_LOG => $rule_int
		],
		'log_as_0or1' => [
			ITEM_VALUE_TYPE_LOG => $rule_0or1
		]
	];

	$hist_functions = [
		'avg' => $rules['numeric_as_float'],
		'baselinedev' => $rules['numeric_as_float'],
		'baselinewma' => $rules['numeric_as_float'],
		'change' => $rules['numeric'] + $rules['string_as_0or1'],
		'count' => $rules['numeric_as_uint'] + $rules['string_as_uint'],
		'changecount' => $rules['numeric_as_uint'] + $rules['string_as_uint'],
		'countunique' => $rules['numeric_as_uint'] + $rules['string_as_uint'],
		'find' => $rules['numeric_as_0or1'] + $rules['string_as_0or1'],
		'first' => $rules['numeric'] + $rules['string'],
		'forecast' => $rules['numeric_as_float'],
		'fuzzytime' => $rules['numeric_as_0or1'],
		'kurtosis' => $rules['numeric_as_float'],
		'last' => $rules['numeric'] + $rules['string'],
		'logeventid' => $rules['log_as_0or1'],
		'logseverity' => $rules['log_as_uint'],
		'logsource' => $rules['log_as_0or1'],
		'mad' => $rules['numeric_as_float'],
		'max' => $rules['numeric'],
		'min' => $rules['numeric'],
		'monodec' => $rules['numeric_as_uint'],
		'monoinc' => $rules['numeric_as_uint'],
		'nodata' => $rules['numeric_as_0or1'] + $rules['string_as_0or1'],
		'percentile' => $rules['numeric'],
		'rate' => $rules['numeric'],
		'skewness' => $rules['numeric_as_float'],
		'stddevpop' => $rules['numeric_as_float'],
		'stddevsamp' => $rules['numeric_as_float'],
		'sum' => $rules['numeric'],
		'sumofsquares' => $rules['numeric_as_float'],
		'timeleft' => $rules['numeric_as_float'],
		'trendavg' => $rules['numeric'],
		'trendcount' => $rules['numeric'],
		'trendmax' => $rules['numeric'],
		'trendmin' => $rules['numeric'],
		'trendstl' => $rules['numeric'],
		'trendsum' => $rules['numeric'],
		'varpop' => $rules['numeric_as_float'],
		'varsamp' => $rules['numeric_as_float']
	];

	$math_functions = [
		'abs' => ['any' => $rule_float],
		'acos' => ['any' => $rule_float],
		'ascii' => ['any' => $rule_int],
		'asin' => ['any' => $rule_float],
		'atan' => ['any' => $rule_float],
		'atan2' => ['any' => $rule_float],
		'avg' => ['any' => $rule_float],
		'between' => ['any' => $rule_0or1],
		'bitand' => ['any' => $rule_int],
		'bitlength' => ['any' => $rule_int],
		'bitlshift' => ['any' => $rule_int],
		'bitnot' => ['any' => $rule_int],
		'bitor' => ['any' => $rule_int],
		'bitrshift' => ['any' => $rule_int],
		'bitxor' => ['any' => $rule_int],
		'bytelength' => ['any' => $rule_int],
		'cbrt' => ['any' => $rule_float],
		'ceil' => ['any' => $rule_int],
		'char' => ['any' => $rule_str],
		'concat' => ['any' => $rule_str],
		'cos' => ['any' => $rule_float],
		'cosh' => ['any' => $rule_float],
		'cot' => ['any' => $rule_float],
		'date' => [
			'any' => ['value_type' => 'YYYYMMDD', 'values' => null]
		],
		'dayofmonth' => [
			'any' => ['value_type' => '1-31', 'values' => null]
		],
		'dayofweek' => [
			'any' => ['value_type' => '1-7', 'values' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7]]
		],
		'degrees' => ['any' => $rule_float],
		'e' => ['any' => $rule_float],
		'exp' => ['any' => $rule_float],
		'expm1' => ['any' => $rule_float],
		'floor' => ['any' => $rule_int],
		'in' => ['any' => $rule_0or1],
		'insert' => ['any' => $rule_str],
		'jsonpath' => ['any' => $rule_str],
		'left' => ['any' => $rule_str],
		'length' => ['any' => $rule_int],
		'log' => ['any' => $rule_float],
		'log10' => ['any' => $rule_float],
		'ltrim' => ['any' => $rule_str],
		'max' => ['any' => $rule_float],
		'mid' => ['any' => $rule_str],
		'min' => ['any' => $rule_float],
		'mod' => ['any' => $rule_float],
		'now' => ['any' => $rule_int],
		'pi' => ['any' => $rule_float],
		'power' => ['any' => $rule_float],
		'radians' => ['any' => $rule_float],
		'rand' => ['any' => $rule_int],
		'repeat' => ['any' => $rule_str],
		'replace' => ['any' => $rule_str],
		'right' => ['any' => $rule_str],
		'round' => ['any' => $rule_float],
		'rtrim' => ['any' => $rule_str],
		'signum' => ['any' => $rule_int],
		'sin' => ['any' => $rule_float],
		'sinh' => ['any' => $rule_float],
		'sqrt' => ['any' => $rule_float],
		'sum' => ['any' => $rule_float],
		'tan' => ['any' => $rule_float],
		'time' => [
			'any' => ['value_type' => 'HHMMSS', 'values' => null]
		],
		'trim' => ['any' => $rule_str],
		'truncate' => ['any' => $rule_float],
		'xmlxpath' => ['any' => $rule_str]
	];

	$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
	$expression_parser->parse($expr);
	$token = $expression_parser->getResult()->getTokens()[0];

	switch ($token['type']) {
		case CExpressionParserResult::TOKEN_TYPE_MACRO:
			$result = $rule_0or1;
			break;

		case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
		case CExpressionParserResult::TOKEN_TYPE_LLD_MACRO:
			$result = $rule_any;
			break;

		case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
			if (!array_key_exists($token['data']['function'], $hist_functions)) {
				$result = EXPRESSION_FUNCTION_UNKNOWN;
				break;
			}

			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'filter' => [
					'host' => $token['data']['parameters'][0]['data']['host']
				],
				'templated_hosts' => true
			]);

			if (!$hosts) {
				$result = EXPRESSION_HOST_UNKNOWN;
				break;
			}

			$items = API::Item()->get([
				'output' => ['value_type'],
				'hostids' => $hosts[0]['hostid'],
				'filter' => [
					'key_' => $token['data']['parameters'][0]['data']['item']
				],
				'webitems' => true
			]);

			if (!$items) {
				$items = API::ItemPrototype()->get([
					'output' => ['value_type'],
					'hostids' => $hosts[0]['hostid'],
					'filter' => [
						'key_' => $token['data']['parameters'][0]['data']['item']
					]
				]);
			}

			if (!$items) {
				$result = EXPRESSION_HOST_ITEM_UNKNOWN;
				break;
			}

			$hist_function = $hist_functions[$token['data']['function']];
			$value_type = $items[0]['value_type'];

			if (array_key_exists('any', $hist_function)) {
				$value_type = 'any';
			}
			elseif (!array_key_exists($value_type, $hist_function)) {
				$result = EXPRESSION_UNSUPPORTED_VALUE_TYPE;
				break;
			}

			$result = $hist_function[$value_type];
			break;

		case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
			if (!array_key_exists($token['data']['function'], $math_functions)) {
				$result = EXPRESSION_FUNCTION_UNKNOWN;
				break;
			}

			$result = $math_functions[$token['data']['function']]['any'];
			break;

		default:
			$result = EXPRESSION_NOT_A_MACRO_ERROR;
	}

	return $result;
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
			'output' => ['hostid', 'name', 'maintenanceid', 'maintenance_status', 'maintenance_type'],
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
 * @param string $triggers_hosts[<triggerid>][]['maintenanceid']
 * @param int    $triggers_hosts[<triggerid>][]['maintenance_status']
 * @param int    $triggers_hosts[<triggerid>][]['maintenance_type']
 *
 * @return array
 */
function makeTriggersHostsList(array $triggers_hosts) {
	$db_maintenances = [];

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
	}

	foreach ($triggers_hosts as &$hosts) {
		$trigger_hosts = [];

		foreach ($hosts as $host) {
			$host_name = (new CLinkAction($host['name']))
				->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']))
				->addClass(ZBX_STYLE_WORDBREAK);

			if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				if (array_key_exists($host['maintenanceid'], $db_maintenances)) {
					$maintenance = $db_maintenances[$host['maintenanceid']];
					$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
						$maintenance['description']
					);
				}
				else {
					$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], _('Inaccessible maintenance'),
						''
					);
				}

				$host_name = (new CSpan([$host_name, $maintenance_icon]))->addClass(ZBX_STYLE_REL_CONTAINER);
			}

			if ($trigger_hosts) {
				$trigger_hosts[] = (new CSpan(','))->addClass('separator');
			}
			$trigger_hosts[] = $host_name;
		}

		$hosts = $trigger_hosts;
	}
	unset($hosts);

	return $triggers_hosts;
}

/**
 * Get parent templates for each given trigger.
 *
 * @param $array $triggers                  An array of triggers.
 * @param string $triggers[]['triggerid']   ID of a trigger.
 * @param string $triggers[]['templateid']  ID of parent template trigger.
 * @param int    $flag                      Origin of the trigger (ZBX_FLAG_DISCOVERY_NORMAL or
 *                                          ZBX_FLAG_DISCOVERY_PROTOTYPE).
 *
 * @return array
 */
function getTriggerParentTemplates(array $triggers, $flag) {
	$parent_triggerids = [];
	$data = [
		'links' => [],
		'templates' => []
	];

	foreach ($triggers as $trigger) {
		if ($trigger['templateid'] != 0) {
			$parent_triggerids[$trigger['templateid']] = true;
			$data['links'][$trigger['triggerid']] = ['triggerid' => $trigger['templateid']];
		}
	}

	if (!$parent_triggerids) {
		return $data;
	}

	$all_parent_triggerids = [];
	$hostids = [];
	if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$lld_ruleids = [];
	}

	do {
		if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$db_triggers = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'templateid'],
				'selectHosts' => ['hostid'],
				'selectDiscoveryRule' => ['itemid'],
				'triggerids' => array_keys($parent_triggerids)
			]);
		}
		// ZBX_FLAG_DISCOVERY_NORMAL
		else {
			$db_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'templateid'],
				'selectHosts' => ['hostid'],
				'triggerids' => array_keys($parent_triggerids)
			]);
		}

		$all_parent_triggerids += $parent_triggerids;
		$parent_triggerids = [];

		foreach ($db_triggers as $db_trigger) {
			foreach ($db_trigger['hosts'] as $host) {
				$data['templates'][$host['hostid']] = [];
				$hostids[$db_trigger['triggerid']][] = $host['hostid'];
			}

			if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$lld_ruleids[$db_trigger['triggerid']] = $db_trigger['discoveryRule']['itemid'];
			}

			if ($db_trigger['templateid'] != 0) {
				if (!array_key_exists($db_trigger['templateid'], $all_parent_triggerids)) {
					$parent_triggerids[$db_trigger['templateid']] = true;
				}

				$data['links'][$db_trigger['triggerid']] = ['triggerid' => $db_trigger['templateid']];
			}
		}
	}
	while ($parent_triggerids);

	foreach ($data['links'] as &$parent_trigger) {
		$parent_trigger['hostids'] = array_key_exists($parent_trigger['triggerid'], $hostids)
			? $hostids[$parent_trigger['triggerid']]
			: [0];

		if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$parent_trigger['lld_ruleid'] = array_key_exists($parent_trigger['triggerid'], $lld_ruleids)
				? $lld_ruleids[$parent_trigger['triggerid']]
				: 0;
		}
	}
	unset($parent_trigger);

	$db_templates = $data['templates']
		? API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($data['templates']),
			'preservekeys' => true
		])
		: [];

	$rw_templates = $db_templates
		? API::Template()->get([
			'output' => [],
			'templateids' => array_keys($db_templates),
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['templates'][0] = [];

	foreach ($data['templates'] as $hostid => &$template) {
		$template = array_key_exists($hostid, $db_templates)
			? [
				'hostid' => $hostid,
				'name' => $db_templates[$hostid]['name'],
				'permission' => array_key_exists($hostid, $rw_templates) ? PERM_READ_WRITE : PERM_READ
			]
			: [
				'hostid' => $hostid,
				'name' => _('Inaccessible template'),
				'permission' => PERM_DENY
			];
	}
	unset($template);

	return $data;
}

/**
 * Returns a template prefix for selected trigger.
 *
 * @param string $triggerid
 * @param array  $parent_templates  The list of the templates, prepared by getTriggerParentTemplates() function.
 * @param int    $flag              Origin of the trigger (ZBX_FLAG_DISCOVERY_NORMAL or ZBX_FLAG_DISCOVERY_PROTOTYPE).
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array|null
 */
function makeTriggerTemplatePrefix($triggerid, array $parent_templates, $flag, bool $provide_links) {
	if (!array_key_exists($triggerid, $parent_templates['links'])) {
		return null;
	}

	while (array_key_exists($parent_templates['links'][$triggerid]['triggerid'], $parent_templates['links'])) {
		$triggerid = $parent_templates['links'][$triggerid]['triggerid'];
	}

	$templates = [];
	foreach ($parent_templates['links'][$triggerid]['hostids'] as $hostid) {
		$templates[] = $parent_templates['templates'][$hostid];
	}

	CArrayHelper::sort($templates, ['name']);

	$list = [];

	foreach ($templates as $template) {
		if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
			if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$url = (new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.prototype.list')
					->setArgument('parent_discoveryid', $parent_templates['links'][$triggerid]['lld_ruleid'])
					->setArgument('context', 'template');
			}
			// ZBX_FLAG_DISCOVERY_NORMAL
			else {
				$url = (new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.list')
					->setArgument('filter_hostids', [$template['hostid']])
					->setArgument('filter_set', 1)
					->setArgument('uncheck', 1)
					->setArgument('context', 'template');
			}

			$name = (new CLink($template['name'], $url))->addClass(ZBX_STYLE_LINK_ALT);
		}
		else {
			$name = new CSpan($template['name']);
		}

		$list[] = $name->addClass(ZBX_STYLE_GREY);
		$list[] = ', ';
	}

	array_pop($list);
	$list[] = NAME_DELIMITER;

	return $list;
}

/**
 * Returns a list of trigger templates.
 *
 * @param string $triggerid
 * @param array  $parent_templates  The list of the templates, prepared by getTriggerParentTemplates() function.
 * @param int    $flag              Origin of the trigger (ZBX_FLAG_DISCOVERY_NORMAL or ZBX_FLAG_DISCOVERY_PROTOTYPE).
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array
 */
function makeTriggerTemplatesHtml($triggerid, array $parent_templates, $flag, bool $provide_links) {
	$list = [];

	while (array_key_exists($triggerid, $parent_templates['links'])) {
		$list_item = [];
		$templates = [];

		foreach ($parent_templates['links'][$triggerid]['hostids'] as $hostid) {
			$templates[] = $parent_templates['templates'][$hostid];
		}

		$show_parentheses = (count($templates) > 1 && $list);

		if ($show_parentheses) {
			CArrayHelper::sort($templates, ['name']);
			$list_item[] = '(';
		}

		foreach ($templates as $template) {
			if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
				if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$attribute_name = 'data-parent_discoveryid';
					$attribute_value = $parent_templates['links'][$triggerid]['lld_ruleid'];
					$prototype = '1';
				}
				// ZBX_FLAG_DISCOVERY_NORMAL
				else {
					$attribute_name = 'data-hostid';
					$attribute_value = $template['hostid'];
					$prototype = '0';
				}

				$trigger_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', $prototype === '1' ? 'trigger.prototype.edit' : 'trigger.edit')
					->setArgument('triggerid', $parent_templates['links'][$triggerid]['triggerid'])
					->setArgument('context', 'template')
					->setArgument($attribute_name === 'data-parent_discoveryid'
						? 'parent_discoveryid'
						: 'data-hostid',
					$attribute_value)
					->getUrl();

				$name = (new CLink($template['name'], $trigger_url))
					->addClass('js-related-trigger-edit')
					->setAttribute('data-action', $prototype === '1' ? 'trigger.prototype.edit' : 'trigger.edit')
					->setAttribute('data-triggerid', $parent_templates['links'][$triggerid]['triggerid'])
					->setAttribute('data-context', 'template')
					->setAttribute($attribute_name, $attribute_value);
			}
			else {
				$name = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
			}

			$list_item[] = $name;
			$list_item[] = ', ';
		}
		array_pop($list_item);

		if ($show_parentheses) {
			$list_item[] = ')';
		}

		array_unshift($list, $list_item, [NBSP(), RARR(), NBSP()]);

		$triggerid = $parent_templates['links'][$triggerid]['triggerid'];
	}

	if ($list) {
		array_pop($list);
	}

	return $list;
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
 * @param array $dependencies
 *        array $dependencies['up']    (optional) The list of "Dependent" triggers.
 *        array $dependencies['down']  (optional) The list of "Depends on" triggers.
 * @param bool  $freeze_on_click
 *
 * @return array
 */
function makeTriggerDependencies(array $dependencies, bool $freeze_on_click = true): array {
	$result = [];

	foreach (['down', 'up'] as $type) {
		if (array_key_exists($type, $dependencies)) {
			$table = (new CTableInfo())
				->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->setHeader([$type === 'down' ? _('Depends on') : _('Dependent')]);

			foreach ($dependencies[$type] as $description) {
				$table->addRow($description);
			}

			$result[] = (new CButtonIcon($type === 'down' ? ZBX_ICON_BULLET_ALT_DOWN : ZBX_ICON_BULLET_ALT_UP))
				->addClass(ZBX_STYLE_COLOR_ICON)
				->setHint($table, '', $freeze_on_click);
		}
	}

	return $result;
}

/**
 * Return list of functions that can be used without /host/key reference.
 *
 * @return array
 */
function getStandaloneFunctions(): array {
	return ['date', 'dayofmonth', 'dayofweek', 'time', 'now'];
}

/**
 * Returns a list of functions that return a constant or random number.
 *
 * @return array
 */
function getFunctionsConstants(): array {
	return ['e', 'pi', 'rand'];
}
