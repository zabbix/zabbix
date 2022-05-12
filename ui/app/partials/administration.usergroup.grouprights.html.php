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
 */

$group_rights_table = (new CTable())
	->setId('group-right-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host group'), _('Permissions')]);

foreach ($data['group_rights'] as $groupid => $group_right) {
	$form_vars = [];
	$form_data_json = [];

	$form_vars[] = (new CVar('group_rights['.$groupid.'][name]', $group_right['name']))->removeId();
	$form_data_json[$groupid] = ['name' => $group_right['name']];

	if ($groupid == 0) {
		$form_vars[] = (new CVar('group_rights['.$groupid.'][grouped]', $group_right['grouped']))->removeId();
		$form_vars[] = (new CVar('group_rights['.$groupid.'][permission]', $group_right['permission']))->removeId();

		$form_data_json[$groupid]['grouped'] = $group_right['grouped'];
		$form_data_json[$groupid]['permission'] = $group_right['permission'];

		$row = [
			italic(_('All groups')),
			[
				permissionText($group_right['permission']),
				$form_vars,
				(new CVar('group_right', json_encode($form_data_json, JSON_FORCE_OBJECT)))
					->removeId()
					->setEnabled(false)
			]
		];
	}
	else {
		if (array_key_exists('grouped', $group_right) && $group_right['grouped']) {
			$form_vars[] = (new CVar('group_rights['.$groupid.'][grouped]', $group_right['grouped']))->removeId();
			$form_data_json[$groupid]['grouped'] = $group_right['grouped'];

			$group_name = [$group_right['name'], SPACE, italic('('._('including subgroups').')')];
		}
		else {
			$group_name = $group_right['name'];
		}

		$permissions = [
			(new CRadioButtonList('group_rights['.$groupid.'][permission]', (int) $group_right['permission']))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->addValue(_('None'), PERM_NONE)
				->setModern(true),
			$form_vars,
			(new CVar('group_right', json_encode($form_data_json, JSON_FORCE_OBJECT)))
				->removeId()
				->setEnabled(false)
		];
		$row = [$group_name, $permissions];
	}

	$group_rights_table->addRow($row);
}

$group_rights_table->show();
