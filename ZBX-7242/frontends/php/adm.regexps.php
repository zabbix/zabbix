<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
$page['hist_arg'] = array();
$page['type'] = detect_page_type();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'regexpids' =>				array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null),
	'regexpid' =>				array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form})&&{form}=="update"'),
	'name' =>					array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({save})', _('Name')),
	'test_string' =>			array(T_ZBX_STR, O_OPT, null,		null,	'isset({save})', _('Test string')),
	'expressions' =>			array(T_ZBX_STR, O_OPT, null,		null,	'isset({save})'),
	'save' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>					array(T_ZBX_STR, O_OPT, null,		null,	null),
	'go' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null,		null,	null),
	// ajax
	'output' =>					array(T_ZBX_STR, O_OPT, P_ACT,		null,	null),
	'ajaxaction' =>				array(T_ZBX_STR, O_OPT, P_ACT,		null,	null),
	'ajaxdata' =>				array(T_ZBX_STR, O_OPT, P_ACT,		null,	null)
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['output']) && $_REQUEST['output'] == 'ajax') {
	$ajaxResponse = new AjaxResponse;
	$ajaxData = get_request('ajaxdata', array());

	if (isset($_REQUEST['ajaxaction']) && $_REQUEST['ajaxaction'] == 'test') {
		$result = array(
			'expressions' => array(),
			'final' => true
		);
		$testString = $ajaxData['testString'];

		foreach ($ajaxData['expressions'] as $id => $expression) {
			$match = GlobalRegExp::matchExpression($expression, $testString);

			$result['expressions'][$id] = $match;
			$result['final'] = $result['final'] && $match;
		}

		$ajaxResponse->success($result);
	}

	$ajaxResponse->send();

	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Permissions
 */
if (isset($_REQUEST['regexpid'])) {
	$regExp = DBfetch(DBSelect('SELECT re.regexpid FROM regexps re WHERE re.regexpid='.zbx_dbstr(get_request('regexpid'))));
	if (empty($regExp)) {
		access_deny();
	}
}
if (isset($_REQUEST['go']) && !isset($_REQUEST['regexpid'])) {
	if (!isset($_REQUEST['regexpids']) || !is_array($_REQUEST['regexpids'])) {
		access_deny();
	}
	else {
		$regExpChk = DBfetch(DBSelect(
			'SELECT COUNT(*) AS cnt FROM regexps re WHERE '.dbConditionInt('re.regexpid', $_REQUEST['regexpids'])
		));
		if ($regExpChk['cnt'] != count($_REQUEST['regexpids'])) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['regexpid'])) {
	unset($_REQUEST['regexpid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$regExp = array(
		'name' => $_REQUEST['name'],
		'test_string' => $_REQUEST['test_string']
	);
	$expressions = get_request('expressions', array());

	DBstart();

	if (isset($_REQUEST['regexpid'])) {
		$regExp['regexpid'] = $_REQUEST['regexpid'];
		$result = updateRegexp($regExp, $expressions);

		$msg1 = _('Regular expression updated');
		$msg2 = _('Cannot update regular expression');
	}
	else {
		$result = addRegexp($regExp, $expressions);

		$msg1 = _('Regular expression added');
		$msg2 = _('Cannot add regular expression');
	}

	show_messages($result, $msg1, $msg2);

	if ($result) {
		add_audit(!isset($_REQUEST['regexpid']) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,
			AUDIT_RESOURCE_REGEXP, _('Name').NAME_DELIMITER.$_REQUEST['name']);

		unset($_REQUEST['form']);
	}

	Dbend($result);

	clearCookies($result);
}
elseif (isset($_REQUEST['go'])) {
	if ($_REQUEST['go'] == 'delete') {
		$regExpIds = get_request('regexpid', array());

		if (isset($_REQUEST['regexpids'])) {
			$regExpIds = $_REQUEST['regexpids'];
		}

		zbx_value2array($regExpIds);

		$regExps = array();
		foreach ($regExpIds as $regExpId) {
			$regExps[$regExpId] = getRegexp($regExpId);
		}

		DBstart();

		$result = DBexecute('DELETE FROM regexps WHERE '.dbConditionInt('regexpid', $regExpIds));
		$result = Dbend($result);

		$regExpCount = count($regExpIds);

		show_messages($result,
			_n('Regular expression deleted', 'Regular expressions deleted', $regExpCount),
			_n('Cannot delete regular expression', 'Cannot delete regular expressions', $regExpCount)
		);

		if ($result) {
			foreach ($regExps as $regExpId => $regExp) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_REGEXP, 'Id ['.$regExpId.'] '._('Name').' ['.$regExp['name'].']');
			}

			unset($_REQUEST['form'], $_REQUEST['regexpid']);
			clearCookies($result);
		}
	}
}

/*
 * Display
 */
$generalComboBox = new CComboBox('configDropDown', 'adm.regexps.php', 'redirect(this.options[this.selectedIndex].value);');
$generalComboBox->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeping'),
	'adm.images.php' => _('Images'),
	'adm.iconmapping.php' => _('Icon mapping'),
	'adm.regexps.php' => _('Regular expressions'),
	'adm.macros.php' => _('Macros'),
	'adm.valuemapping.php' => _('Value mapping'),
	'adm.workingtime.php' => _('Working time'),
	'adm.triggerseverities.php' => _('Trigger severities'),
	'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
	'adm.other.php' => _('Other')
));
$regExpForm = new CForm();
$regExpForm->cleanItems();
$regExpForm->addItem($generalComboBox);
if (!isset($_REQUEST['form'])) {
	$regExpForm->addItem(new CSubmit('form', _('New regular expression')));
}

$regExpWidget = new CWidget();
$regExpWidget->addPageHeader(_('CONFIGURATION OF REGULAR EXPRESSIONS'), $regExpForm);

if (isset($_REQUEST['form'])) {
	$data = array(
		'form_refresh' => get_request('form_refresh'),
		'regexpid' => get_request('regexpid')
	);

	if (isset($_REQUEST['regexpid']) && !isset($_REQUEST['form_refresh'])) {
		$regExp = DBfetch(DBSelect(
			'SELECT re.name,re.test_string'.
			' FROM regexps re'.
			' WHERE re.regexpid='.zbx_dbstr($_REQUEST['regexpid']).
				andDbNode('re.regexpid')
		));

		$data['name'] = $regExp['name'];
		$data['test_string'] = $regExp['test_string'];

		$dbExpressions = DBselect(
			'SELECT e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive'.
			' FROM expressions e'.
			' WHERE e.regexpid='.zbx_dbstr($_REQUEST['regexpid']).
				andDbNode('e.expressionid').
			' ORDER BY e.expression_type'
		);
		$data['expressions'] = DBfetchArray($dbExpressions);
	}
	else {
		$data['name'] = get_request('name', '');
		$data['test_string'] = get_request('test_string', '');
		$data['expressions'] = get_request('expressions', array());
	}

	$regExpForm = new CView('administration.general.regularexpressions.edit', $data);
}
else {
	$data = array(
		'displayNodes' => is_array(get_current_nodeid()),
		'cnf_wdgt' => &$regExpWidget,
		'regexps' => array(),
		'regexpids' => array()
	);

	$dbRegExp = DBselect('SELECT re.* FROM regexps re '.whereDbNode('re.regexpid'));
	while ($regExp = DBfetch($dbRegExp)) {
		$regExp['expressions'] = array();
		$regExp['nodename'] = $data['displayNodes'] ? get_node_name_by_elid($regExp['regexpid'], true) : '';

		$data['regexps'][$regExp['regexpid']] = $regExp;
		$data['regexpids'][$regExp['regexpid']] = $regExp['regexpid'];
	}

	order_result($data['regexps'], 'name');

	$data['db_exps'] = DBfetchArray(DBselect(
		'SELECT e.*'.
		' FROM expressions e'.
		' WHERE '.dbConditionInt('e.regexpid', $data['regexpids']).
			andDbNode('e.expressionid').
		' ORDER BY e.expression_type'
	));

	$regExpForm = new CView('administration.general.regularexpressions.list', $data);
}

$regExpWidget->addItem($regExpForm->render());
$regExpWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
