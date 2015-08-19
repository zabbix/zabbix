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
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/regexp.inc.php';

$page['title'] = _('Configuration of regular expressions');
$page['file'] = 'adm.regexps.php';
$page['type'] = detect_page_type();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'regexpids' =>		[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'regexpid' =>		[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form}) && {form} == "update"'],
	'name' =>			[T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'test_string' =>	[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	'isset({add}) || isset({update})', _('Test string')],
	'expressions' =>	[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	'isset({add}) || isset({update})'],
	// actions
	'action' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"regexp.massdelete"'),	null],
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,		null,	null],
	// ajax
	'output' =>			[T_ZBX_STR, O_OPT, P_ACT,		null,	null],
	'ajaxaction' =>		[T_ZBX_STR, O_OPT, P_ACT,		null,	null],
	'ajaxdata' =>		[T_ZBX_STR, O_OPT, P_ACT|P_NO_TRIM,		null,	null]
];
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['output']) && $_REQUEST['output'] == 'ajax') {
	$ajaxResponse = new CAjaxResponse;
	$ajaxData = getRequest('ajaxdata', []);

	if (isset($_REQUEST['ajaxaction']) && $_REQUEST['ajaxaction'] == 'test') {
		$result = [
			'expressions' => [],
			'errors' => [],
			'final' => true
		];

		$validator = new CRegexValidator([
			'messageInvalid' => _('Regular expression must be a string'),
			'messageRegex' => _('Incorrect regular expression "%1$s": "%2$s"')
		]);

		foreach ($ajaxData['expressions'] as $id => $expression) {
			if (!in_array($expression['expression_type'], [EXPRESSION_TYPE_FALSE, EXPRESSION_TYPE_TRUE]) ||
				$validator->validate($expression['expression'])
			) {
				$match = CGlobalRegexp::matchExpression($expression, $ajaxData['testString']);

				$result['expressions'][$id] = $match;
			} else {
				$match = false;
				$result['errors'][$id] = $validator->getError();
			}

			$result['final'] = $result['final'] && $match;
		}

		$ajaxResponse->success($result);
	}

	$ajaxResponse->send();

	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Permissions
 */
if (isset($_REQUEST['regexpid'])) {
	$regExp = DBfetch(DBSelect('SELECT re.regexpid FROM regexps re WHERE re.regexpid='.zbx_dbstr(getRequest('regexpid'))));
	if (empty($regExp)) {
		access_deny();
	}
}
if (hasRequest('action') && !hasRequest('regexpid')) {
	if (!hasRequest('regexpids') || !is_array(getRequest('regexpids'))) {
		access_deny();
	}
	else {
		$regExpChk = DBfetch(DBSelect(
			'SELECT COUNT(*) AS cnt FROM regexps re WHERE '.dbConditionInt('re.regexpid', getRequest('regexpids'))
		));
		if ($regExpChk['cnt'] != count(getRequest('regexpids'))) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$regExp = [
		'name' => getRequest('name'),
		'test_string' => getRequest('test_string')
	];
	$expressions = getRequest('expressions', []);

	foreach ($expressions as $i => &$expression) {
		if (!array_key_exists('case_sensitive', $expression)) {
			$expression['case_sensitive'] = 0;
		}
	}
	unset($expression);

	DBstart();

	if (hasRequest('update')) {
		$regExp['regexpid'] = getRequest('regexpid');
		$result = updateRegexp($regExp, $expressions);

		$messageSuccess = _('Regular expression updated');
		$messageFailed = _('Cannot update regular expression');
	}
	else {
		$result = addRegexp($regExp, $expressions);

		$messageSuccess = _('Regular expression added');
		$messageFailed = _('Cannot add regular expression');
	}

	if ($result) {
		add_audit(hasRequest('update') ? AUDIT_ACTION_UPDATE : AUDIT_ACTION_ADD,
			AUDIT_RESOURCE_REGEXP, _('Name').NAME_DELIMITER.getRequest('name'));

		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'regexp.massdelete') {
	$regExpIds = getRequest('regexpids', getRequest('regexpid', []));

	zbx_value2array($regExpIds);

	$regExps = [];
	foreach ($regExpIds as $regExpId) {
		$regExps[$regExpId] = getRegexp($regExpId);
	}

	DBstart();

	$result = DBexecute('DELETE FROM regexps WHERE '.dbConditionInt('regexpid', $regExpIds));

	$regExpCount = count($regExpIds);

	if ($result) {
		foreach ($regExps as $regExpId => $regExp) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_REGEXP,
				'Id ['.$regExpId.'] '._('Name').' ['.$regExp['name'].']'
			);
		}

		unset($_REQUEST['form'], $_REQUEST['regexpid']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result,
		_n('Regular expression deleted', 'Regular expressions deleted', $regExpCount),
		_n('Cannot delete regular expression', 'Cannot delete regular expressions', $regExpCount)
	);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'form_refresh' => getRequest('form_refresh'),
		'regexpid' => getRequest('regexpid')
	];

	if (isset($_REQUEST['regexpid']) && !isset($_REQUEST['form_refresh'])) {
		$regExp = DBfetch(DBSelect(
			'SELECT re.name,re.test_string'.
			' FROM regexps re'.
			' WHERE re.regexpid='.zbx_dbstr($_REQUEST['regexpid'])
		));

		$data['name'] = $regExp['name'];
		$data['test_string'] = $regExp['test_string'];
		$data['expressions'] = DBfetchArray(DBselect(
			'SELECT e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive'.
			' FROM expressions e'.
			' WHERE e.regexpid='.zbx_dbstr($_REQUEST['regexpid']).
			' ORDER BY e.expression_type'
		));
	}
	else {
		$data['name'] = getRequest('name', '');
		$data['test_string'] = getRequest('test_string', '');
		$data['expressions'] = getRequest('expressions', [
			[
				'expression' => '',
				'expression_type' => EXPRESSION_TYPE_INCLUDED,
				'exp_delimiter' => ',',
				'case_sensitive' => 0
			]
		]);
	}

	$view = new CView('administration.general.regularexpressions.edit', $data);
}
else {
	$data = [
		'regexps' => [],
		'regexpids' => []
	];

	$dbRegExp = DBselect('SELECT re.* FROM regexps re');

	while ($regExp = DBfetch($dbRegExp)) {
		$regExp['expressions'] = [];

		$data['regexps'][$regExp['regexpid']] = $regExp;
		$data['regexpids'][$regExp['regexpid']] = $regExp['regexpid'];
	}

	order_result($data['regexps'], 'name');

	$data['db_exps'] = DBfetchArray(DBselect(
		'SELECT e.*'.
		' FROM expressions e'.
		' WHERE '.dbConditionInt('e.regexpid', $data['regexpids']).
		' ORDER BY e.expression_type'
	));

	$view = new CView('administration.general.regularexpressions.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
