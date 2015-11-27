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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Trigger');
$page['file'] = 'tr_logform.php';
$page['scripts'] = ['tr_logform.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'description' =>	[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,			'isset({add}) || isset({update})',
		_('Name')
	],
	'itemid' =>			[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,				'isset({add}) || isset({update})'],
	'sform' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'sitems' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'triggerid' =>		[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,				null],
	'type' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'priority' =>		[T_ZBX_INT, O_OPT, null,		IN('0,1,2,3,4,5'),	'isset({add}) || isset({update})'],
	'expressions' =>	[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,			'isset({add}) || isset({update})'],
	'expr_type' =>		[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'comments' =>		[T_ZBX_STR, O_OPT, null,		null,				null],
	'url' =>			[T_ZBX_STR, O_OPT, null,		null,				null],
	'status' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,		null,				null],
	// actions
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,				null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,				null],
	'keys' => 			[T_ZBX_STR, O_OPT, null,		null,				null],
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('itemid') && !API::Item()->isWritable([$_REQUEST['itemid']])
		|| getRequest('triggerid') && !API::Trigger()->isWritable([$_REQUEST['triggerid']])) {
	access_deny();
}

$itemid = getRequest('itemid', 0);

$constructor = new CTextTriggerConstructor(new CTriggerExpression());

/**
 * Save a trigger
 */
if (hasRequest('add') || hasRequest('update')) {
	$item = API::Item()->get([
		'output' => ['key_'],
		'selectHosts' => ['host'],
		'itemids' => $itemid,
		'limit' => 1
	]);
	$item = reset($item);
	$host = reset($item['hosts']);

	show_messages();

	$exprs = getRequest('expressions', false);
	if ($exprs && ($expression = $constructor->getExpressionFromParts($host['host'], $item['key_'], $exprs))) {
		if (!check_right_on_trigger_by_expression(PERM_READ_WRITE, $expression)) {
			access_deny();
		}

		$now = time();
		$status = hasRequest('status') ? TRIGGER_STATUS_DISABLED : TRIGGER_STATUS_ENABLED;
		$type = TRIGGER_MULT_EVENT_ENABLED;

		if (hasRequest('triggerid')) {
			$triggerId = getRequest('triggerid');
			$description = getRequest('description', '');

			$db_triggers = API::Trigger()->get([
				'output' => ['description', 'expression', 'templateid'],
				'triggerids' => [$triggerId]
			]);

			if ($db_triggers[0]['templateid'] != 0) {
				$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers);

				$description = $db_triggers[0]['description'];
				$expression = $db_triggers[0]['expression'];
			}

			$trigger = [];
			$trigger['triggerid'] = $triggerId;
			$trigger['expression'] = $expression;
			$trigger['description'] = $description;
			$trigger['type'] = $type;
			$trigger['priority'] = getRequest('priority', 0);
			$trigger['status'] = $status;
			$trigger['comments'] = getRequest('comments', '');
			$trigger['url'] = getRequest('url', '');

			$result = (bool) API::Trigger()->update($trigger);

			$auditAction = AUDIT_ACTION_UPDATE;

			show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
		}
		else {
			$trigger = [];
			$trigger['expression'] = $expression;
			$trigger['description'] = getRequest('description');
			$trigger['type'] = $type;
			$trigger['priority'] = getRequest('priority', 0);
			$trigger['status'] = $status;
			$trigger['comments'] = getRequest('comments', '');
			$trigger['url'] = getRequest('url', '');

			$result = (bool) API::Trigger()->create($trigger);
			if ($result) {
				$dbTriggers = API::Trigger()->get([
					'triggerids' => $result['triggerids'],
					'output' => ['triggerid']
				]);

				$dbTrigger = reset($dbTriggers);
				$triggerId = $dbTrigger['triggerid'];
			}

			$auditAction = AUDIT_ACTION_ADD;

			show_messages($result, _('Trigger added'), _('Cannot add trigger'));
		}

		if ($result) {
			DBstart();

			add_audit($auditAction, AUDIT_RESOURCE_TRIGGER,
				_('Trigger').' ['.$triggerId.'] ['.$trigger['description'].']'
			);

			DBend(true);

			unset($_REQUEST['sform']);

			zbx_add_post_js('closeForm("items.php");');
			require_once dirname(__FILE__).'/include/page_footer.php';
		}
	}
}

//------------------------ <FORM> ---------------------------

if (hasRequest('sform')) {
	$widget = (new CWidget())->setTitle(_('Trigger'));

	$form = (new CForm())
		->setName('sform')
		->addVar('sform', '1')
		->addVar('itemid', $itemid);

	if (hasRequest('triggerid')) {
		$form->addVar('triggerid', getRequest('triggerid'));
	}

	$form_list = new CFormList();

	if (hasRequest('triggerid') && !hasRequest('form_refresh')) {
		$result = DBselect(
			'SELECT t.expression,t.description,t.priority,t.comments,t.url,t.status,t.type'.
			' FROM triggers t'.
			' WHERE t.triggerid='.zbx_dbstr(getRequest('triggerid')).
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM functions f,items i'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid '.
						' AND i.value_type IN ('.
							ITEM_VALUE_TYPE_LOG.','.ITEM_VALUE_TYPE_TEXT.','.ITEM_VALUE_TYPE_STR.
						')'.
				')'
		);

		if ($row = DBfetch($result)) {
			$description = $row['description'];
			$expression = CMacrosResolverHelper::resolveTriggerExpression($row['expression']);
			$type = $row['type'];
			$priority = $row['priority'];
			$comments = $row['comments'];
			$url = $row['url'];
			$status = $row['status'];
		}

		// break expression into parts
		$expressions = $constructor->getPartsFromExpression($expression);
	}
	else {
		$description = getRequest('description', '');
		$expressions = getRequest('expressions', []);
		$type = getRequest('type', 0);
		$priority = getRequest('priority', 0);
		$comments = getRequest('comments', '');
		$url = getRequest('url', '');
		$status = getRequest('status', 0);
	}

	$keys = getRequest('keys', []);

	$items = API::Item()->get([
		'output' => ['itemid', 'hostid', 'key_', 'name'],
		'selectHosts' => ['name'],
		'itemids' => [$itemid]
	]);

	if ($items) {
		$items = CMacrosResolverHelper::resolveItemNames($items);
		$item_name = $items[0]['hosts'][0]['name'].NAME_DELIMITER.$items[0]['name_expanded'];
	}
	else {
		$item_name = '';
	}

	$form_list->addRow(_('Name'), (new CTextBox('description', $description))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
	$form_list->addRow(_('Item'), [
		(new CTextBox('item', $item_name))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('item')
			->setAttribute('disabled', 'disabled'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton(null, _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick("javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=itemid&dstfld2=item".
				"&srctbl=items&srcfld1=itemid&srcfld2=name');"
			)
	]);

	$form_list->addRow(_('Expression'),
		(new CTextBox('expression'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('logexpr')
	);

	$form_list->addRow(null, [
		new CLabel([new CCheckBox('iregexp'), 'iregexp'], 'iregexp'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('add_key_and', _('AND')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('javascript: add_keyword_and();'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('add_key_or', _('OR')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('javascript: add_keyword_or();'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CComboBox('expr_type', null, null, [
			CTextTriggerConstructor::EXPRESSION_TYPE_MATCH => _('Include'),
			CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH => _('Exclude')
		]))->setId('expr_type'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('add_exp', _('Add')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('javascript: add_logexpr();')
	]);

	$keyTable = (new CTable())
		->setId('key_list')
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Keyword'), _('Type'), _('Action')]);

	$table = (new CTable())
		->setId('exp_list')
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Expression'), _('Type'), _('Position'), _('Action')]);

	$maxId = 0;
	foreach ($expressions as $id => $expr) {
		$imgup = (new CImg('images/general/arrow_up.png', 'up', 12, 14))
			->onClick('javascript: element_up("logtr'.$id.'");')
			->onMouseover('javascript: this.style.cursor = "pointer";')
			->addClass('updown');

		$imgdn = (new CImg('images/general/arrow_down.png', 'down', 12, 14))
			->onClick('javascript: element_down("logtr'.$id.'");')
			->onMouseover('javascript: this.style.cursor = "pointer";')
			->addClass('updown');

		$row = new CRow([
			htmlspecialchars($expr['value']),
			($expr['type'] == CTextTriggerConstructor::EXPRESSION_TYPE_MATCH) ? _('Include') : _('Exclude'),
			[$imgup, ' ', $imgdn],
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('javascript: remove_expression("logtr'.$id.'");')
			))->addClass(ZBX_STYLE_NOWRAP)
		]);
		$row->setId('logtr'.$id);
		$table->addRow($row);

		$form->addVar('expressions['.$id.'][value]', $expr['value']);
		$form->addVar('expressions['.$id.'][type]', $expr['type']);

		$maxId = max($maxId, $id);
	}

	zbx_add_post_js('logexpr_count='.($maxId + 1).';');
	zbx_add_post_js('processExpressionList();');

	$maxId = 0;
	foreach ($keys as $id => $val) {
		$row = new CRow([
			htmlspecialchars($val['value']),
			$val['type'],
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('javascript: remove_keyword("keytr'.$id.'");')
			))->addClass(ZBX_STYLE_NOWRAP)
		]);
		$row->setId('keytr'.$id);
		$keyTable->addRow($row);

		$form->addVar('keys['.$id.'][value]', $val['value']);
		$form->addVar('keys['.$id.'][type]', $val['type']);

		$maxId = max($maxId, $id);
	}

	zbx_add_post_js('key_count='.($maxId + 1).';');

	$form_list->addRow(null,
		(new CDiv($keyTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
	$form_list->addRow(null,
		(new CDiv($table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

	$sev_select = new CComboBox('priority', $priority);

	$config = select_config();

	$severityNames = [];
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$severityNames[] = getSeverityName($severity, $config);
	}
	$sev_select->addItems($severityNames);

	$form_list->addRow(_('Severity'), $sev_select);
	$form_list->addRow(_('Comments'), (new CTextArea('comments', $comments))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
	$form_list->addRow(_('URL'), (new CTextBox('url', $url))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
	$form_list->addRow(_('Disabled'),
		(new CCheckBox('status'))->setChecked($status == TRIGGER_STATUS_DISABLED)
	);

	$tab = (new CTabView())->addTab('trigger_tab', null, $form_list);

	if (hasRequest('triggerid')) {
		$tab->setFooter(makeFormFooter(
			new CSubmit('update', _('Update')),
			[(new CButton('cancel', _('Cancel')))->onClick('javascript: self.close();')]
		));
	}
	else {
		$tab->setFooter(makeFormFooter(
			new CSubmit('add', _('Add')),
			[(new CButton('cancel', _('Cancel')))->onClick('javascript: self.close();')]
		));
	}

	$form->addItem($tab);

	$widget
		->addItem($form)
		->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
