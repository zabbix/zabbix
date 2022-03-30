<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->setName('hostgroupForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('groupid', $data['groupid']);

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField((new CTextBox('name', $data['name']))
			->setAttribute('autofocus', 'autofocus')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		)
	]);

if ($data['groupid'] != 0 && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$form_grid->addItem([
		new CFormField((new CCheckBox('subgroups'))
			->setLabel(_('Apply permissions and tag filters to all subgroups'))
			->setChecked($data['subgroups']))
	]);
}

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			hostgroup_edit_popup.init('.json_encode([
				'groupid' => $data['groupid'],
				'subgroups' => $data['subgroups'],
				'create_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'hostgroup.create')
					->getUrl(),
				'update_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'hostgroup.update')
					->getUrl(),
				'delete_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'hostgroup.delete')
					->getUrl()
			]).');
		'))->setOnDocumentReady()
	);

if ($data['groupid'] !== null) {
	$title = _('Host group');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'hostgroup_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'hostgroup_edit_popup.clone('.json_encode([
					'title' => _('New host group'),
					'buttons' => [
						[
							'title' => _('Add'),
							'class' => 'js-add',
							'keepOpen' => true,
							'isSubmit' => true,
							'action' => 'hostgroup_edit_popup.submit();'
						],
						[
							'title' => _('Cancel'),
							'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
							'action' => 'hostgroup_edit_popup.cancel();'
						]
					]
				]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected host group?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'hostgroup_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New host group');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'hostgroup_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::CONFIGURATION_HOSTGROUPS_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.hostgroup.edit.js.php')
];

echo json_encode($output);

