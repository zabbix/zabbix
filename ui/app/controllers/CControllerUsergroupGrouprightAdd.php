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


class CControllerUsergroupGrouprightAdd extends CController {

	protected function checkInput() {
		$fields = [
			'group_rights'            => 'required|array',
			'templategroup_rights'    => 'required|array',
			'new_group_right'         => 'required|array',
			'new_templategroup_right' => 'required|array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$new_group_right = $this->getInput('new_group_right') + ['groupids' => []];
			$new_templategroup_right = $this->getInput('new_templategroup_right') + ['groupids' => []];

			if (!$new_group_right['groupids'] && !$new_templategroup_right['groupids']) {
				error(_('At least one host group or template group should be selected.'));

				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse((new CControllerResponseData([
				'main_block' => json_encode(['messages' => getMessages()->toString()])
			]))->disableView());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction() {
		$new_group_right = $this->getInput('new_group_right') + [
			'groupids' => [],
			'permission' => PERM_NONE,
			'include_subgroups' => '0'
		];

		list($groupids, $subgroupids) = $new_group_right['include_subgroups']
			? [[], $new_group_right['groupids']]
			: [$new_group_right['groupids'], []];

		$new_templategroup_right = $this->getInput('new_templategroup_right') + [
				'groupids' => [],
				'permission' => PERM_NONE,
				'include_subgroups' => '0'
		];

		list($templategroup_groupids, $templategroup_subgroupids) = $new_templategroup_right['include_subgroups']
			? [[], $new_templategroup_right['groupids']]
			: [$new_templategroup_right['groupids'], []];

		$this->setResponse(new CControllerResponseData([
			'group_rights' => collapseHostGroupRights(applyHostGroupRights(
				$this->getInput('group_rights'), $groupids, $subgroupids, $new_group_right['permission']
			)),
			'templategroup_rights' => collapseHostGroupRights(applyTemplateGroupRights(
				$this->getInput('templategroup_rights'), $templategroup_groupids, $templategroup_subgroupids, $new_templategroup_right['permission']
			)),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
