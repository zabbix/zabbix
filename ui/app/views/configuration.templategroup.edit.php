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
 */

$this->includeJsFile('configuration.templategroup.edit.js.php');

$widget = (new CWidget())->setTitle(_('Template groups'));

$form = (new CForm())
	->setName('templategroupForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
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

$tab = (new CTabView())->addTab('templategroupTab', _('Template group'), $form_grid);

$cancelButton = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'templategroup.list')
	->setArgument('page', CPagerHelper::loadPage('templategroup.list', null))
))->setId('cancel');

if ($data['groupid'] == 0) {
	$tab->setFooter(makeFormFooter(
		new CSubmitButton( _('Add'),'action', 'templategroup.create'),
		[$cancelButton]
	));
}
else {
	$tab->setFooter(makeFormFooter(
		new CSubmitButton(_('Update'),'action', 'templategroup.update'), [
			(new CSimpleButton(_('Clone')))
				->setId('clone')
				->setEnabled(CWebUser::getType() == USER_TYPE_SUPER_ADMIN)
				->addClass('js-clone-templategroup'),
			new CRedirectButton(_('Delete'),
				'zabbix.php?action=templategroup.delete&sid='.$data['sid'].'&groupids[]='.$data['groupid'],
				_('Delete template group?')
			),
			$cancelButton
		]
	));
}

$form->addItem($tab);
$widget->addItem($form);
$widget->show();

(new CScriptTag('
	view.init('.'"'.$data['name'].'"'.');
'))
	->setOnDocumentReady()
	->show();
