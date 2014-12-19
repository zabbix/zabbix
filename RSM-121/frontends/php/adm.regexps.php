<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'regexpids' =>				array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'regexpid' =>				array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		'isset({form})&&({form}=="update")'),
	'rename' =>					array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({save})', _('Name')),
	'test_string' =>			array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({save})', _('Test string')),
	'delete_regexp' =>			array(T_ZBX_STR, O_OPT, null,		null,		null),
	'g_expressionid' =>			array(T_ZBX_INT, O_OPT, null,		DB_ID,		null),
	'expressions' =>			array(T_ZBX_STR, O_OPT, null,		null,		'isset({save})'),
	'new_expression' =>			array(T_ZBX_STR, O_OPT, null,		null,		null),
	'cancel_new_expression' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'add_expression' =>			array(T_ZBX_STR, O_OPT, null,		null,		null),
	'edit_expressionid' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'delete_expression' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'save' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'delete' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'clone' =>					array(T_ZBX_STR, O_OPT, null,		null,		null),
	'go' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null,		null,		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['regexpid'])) {
	$regExp = DBfetch(DBSelect('SELECT re.regexpid FROM regexps re WHERE re.regexpid='.get_request('regexpid')));
	if (empty($regExp)) {
		access_deny();
	}
}
if (isset($_REQUEST['go']) && !isset($_REQUEST['regexpid'])) {
	if (!isset($_REQUEST['regexpids']) || !is_array($_REQUEST['regexpids'])) {
		access_deny();
	}
	else {
		$regExpChk = DBfetch(DBSelect('SELECT COUNT(*) AS cnt FROM regexps re WHERE '.dbConditionInt('re.regexpid', $_REQUEST['regexpids'])));
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
elseif (isset($_REQUEST['cancel_new_expression'])) {
	unset($_REQUEST['new_expression']);
}
elseif (isset($_REQUEST['save'])) {
	$regExp = array(
		'name' => $_REQUEST['rename'],
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
			AUDIT_RESOURCE_REGEXP, _('Name').': '.$_REQUEST['rename']);

		unset($_REQUEST['form']);
	}
	Dbend($result);
}
elseif (isset($_REQUEST['go'])) {
	if ($_REQUEST['go'] == 'delete') {
		$regExpids = get_request('regexpid', array());
		if (isset($_REQUEST['regexpids'])) {
			$regExpids = $_REQUEST['regexpids'];
		}

		zbx_value2array($regExpids);

		$regExps = array();
		foreach ($regExpids as $regExpid) {
			$regExps[$regExpid] = getRegexp($regExpid);
		}

		DBstart();
		$result = DBexecute('DELETE FROM regexps WHERE '.dbConditionInt('regexpid', $regExpids));
		$result = Dbend($result);

		$regExpCount = count($regExpids);
		show_messages($result,
			_n('Regular expression deleted', 'Regular expressions deleted', $regExpCount),
			_n('Cannot delete regular expression', 'Cannot delete regular expressions', $regExpCount)
		);
		if ($result) {
			foreach ($regExps as $regExpid => $regExp) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_REGEXP, 'Id ['.$regExpid.'] '._('Name').' ['.$regExp['name'].']');
			}

			unset($_REQUEST['form']);
			unset($_REQUEST['regexpid']);

			uncheckTableRows();
		}
	}
}
elseif (isset($_REQUEST['add_expression']) && isset($_REQUEST['new_expression'])) {
	$new_expression = $_REQUEST['new_expression'];

	if (!zbx_empty($new_expression['expression'])) {
		if (!isset($new_expression['case_sensitive'])) {
			$new_expression['case_sensitive'] = 0;
		}

		if (!isset($new_expression['id'])) {
			if (!isset($_REQUEST['expressions'])) {
				$_REQUEST['expressions'] = array();
			}

			if (!str_in_array($new_expression, $_REQUEST['expressions'])) {
				$_REQUEST['expressions'][] = $new_expression;
			}
		}
		else {
			$id = $new_expression['id'];
			unset($new_expression['id']);
			$_REQUEST['expressions'][$id] = $new_expression;
		}

		unset($_REQUEST['new_expression']);
	}
	else {
		error(_('Incorrect expression'));
	}
}
elseif (isset($_REQUEST['delete_expression']) && isset($_REQUEST['g_expressionid'])) {
	$_REQUEST['expressions'] = get_request('expressions', array());
	foreach ($_REQUEST['g_expressionid'] as $val) {
		unset($_REQUEST['expressions'][$val]);
	}
}
elseif (isset($_REQUEST['edit_expressionid'])) {
	$_REQUEST['edit_expressionid'] = array_keys($_REQUEST['edit_expressionid']);
	$edit_expressionid = array_pop($_REQUEST['edit_expressionid']);
	$_REQUEST['expressions'] = get_request('expressions', array());

	if (isset($_REQUEST['expressions'][$edit_expressionid])) {
		$_REQUEST['new_expression'] = $_REQUEST['expressions'][$edit_expressionid];
		$_REQUEST['new_expression']['id'] = $edit_expressionid;
	}
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.regexps.php', 'redirect(this.options[this.selectedIndex].value);');
$cmbConf->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeper'),
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
$form->addItem($cmbConf);
if (!isset($_REQUEST['form'])) {
	$form->addItem(new CSubmit('form', _('New regular expression')));
}

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF REGULAR EXPRESSIONS'), $form);

$data = array();

if (isset($_REQUEST['form'])) {
	$data['form'] = get_request('form', 1);
	$data['form_refresh'] = get_request('form_refresh', 0) + 1;

	$regExpForm = new CView('administration.general.regularexpressions.edit', $data);
}
else {
	$data['cnf_wdgt'] = &$cnf_wdgt;
	$data['regexps'] = array();
	$data['regexpids'] = array();

	$db_regexps = DBselect('SELECT re.* FROM regexps re WHERE '.DBin_node('re.regexpid'));
	while ($regExp = DBfetch($db_regexps)) {
		$regExp['expressions'] = array();
		$data['regexps'][$regExp['regexpid']] = $regExp;
		$data['regexpids'][$regExp['regexpid']] = $regExp['regexpid'];
	}
	order_result($data['regexps'], 'name');

	$data['db_exps'] = DBfetchArray(DBselect('SELECT e.* FROM expressions e WHERE '.
			DBin_node('e.expressionid').
			' AND '.dbConditionInt('e.regexpid', $data['regexpids']).
			' ORDER BY e.expression_type'));

	$regExpForm = new CView('administration.general.regularexpressions.list', $data);
}

$cnf_wdgt->addItem($regExpForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
