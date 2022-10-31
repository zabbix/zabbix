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
 * @var CPartial $this
 * @var array $data
 */

$form = (new CForm())
	->setId('hostgroupForm')
	->setName('hostgroupForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('groupid', $data['groupid'])
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$form_grid = new CFormGrid();

if ($data['groupid'] !== null && $data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	if ($data['discoveryRule']) {
		if ($data['allowed_ui_conf_hosts'] && $data['is_discovery_rule_editable']) {
			$discovery_rule = (new CLink($data['discoveryRule']['name'],
				(new CUrl('host_prototypes.php'))
					->setArgument('form', 'update')
					->setArgument('parent_discoveryid', $data['discoveryRule']['itemid'])
					->setArgument('hostid', $data['hostPrototype']['hostid'])
					->setArgument('context', 'host')
			));
		}
		else {
			$discovery_rule = new CSpan($data['discoveryRule']['name']);
		}
	}
	else {
		$discovery_rule = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_GREY);
	}

	$form_grid->addItem([[new CLabel(_('Discovered by')), new CFormField($discovery_rule)]]);
}

$form_grid->addItem([
	(new CLabel(_('Group name'), 'name'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('name', $data['name'], $data['groupid'] != 0 && $data['flags'] == ZBX_FLAG_DISCOVERY_CREATED))
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

$tabs = (new CTabView(['id' => 'hostgroup-tabs']))->addTab('hostgroup-tab', _('Host group'), $form_grid);

if (array_key_exists('buttons', $data)) {
	$primary_btn = array_shift($data['buttons']);
	$tabs->setFooter(makeFormFooter($primary_btn, $data['buttons']));
}

$form
	->addItem($tabs)
	->show();
