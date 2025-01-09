<?php declare(strict_types = 0);
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
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('templategroup')))->removeId())
	->setId('templategroupForm')
	->setName('templategroupForm')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('groupid', $data['groupid']);

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Group name'), 'name'))->setAsteriskMark(),
		new CFormField((new CTextBox('name', $data['name']))
			->setAttribute('autofocus', 'autofocus')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		)
	]);

if ($data['groupid'] != 0 && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$form_grid->addItem([
		new CFormField((new CCheckBox('subgroups'))
			->setLabel(_('Apply permissions to all subgroups'))
			->setChecked($data['subgroups']))
	]);
}

$tabs = (new CTabView(['id' => 'templategroup-tabs']))->addTab('templategroup-tab', _('Template group'), $form_grid);

if (array_key_exists('buttons', $data)) {
	$primary_btn = array_shift($data['buttons']);
	$tabs->setFooter(makeFormFooter($primary_btn, $data['buttons']));
}

$form->addItem($tabs);

if ($data['groupid'] !== null) {
	$title = _('Template group');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'templategroup_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'enabled' => CWebUser::getType() == USER_TYPE_SUPER_ADMIN,
			'isSubmit' => false,
			'action' => 'templategroup_edit_popup.clone('.json_encode([
				'title' => _('New template group'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'templategroup_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
						'cancel' => true,
						'action' => ''
					]
				]
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected template group?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'templategroup_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New template group');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'templategroup_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TEMPLATE_GROUPS_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('templategroup.edit.js.php').
		'templategroup_edit_popup.init('.json_encode([
			'groupid' => $data['groupid'],
			'name' => $data['name']
		]).');',
	'dialogue_class' => 'modal-popup-static'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
