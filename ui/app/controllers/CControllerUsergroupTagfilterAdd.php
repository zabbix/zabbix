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


class CControllerUsergroupTagfilterAdd extends CController {

	protected function checkInput() {

		$fields = [
			'tag_filters'    => 'array',
			'new_tag_filter' => 'required|array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$new_tag_filter = $this->getInput('new_tag_filter') + [
				'groupids' => [],
				'tag' => '',
				'value' => ''
			];

			if (!$new_tag_filter['groupids']) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty')));

				$ret = false;
			}

			if ($ret && $new_tag_filter['tag'] === '' && $new_tag_filter['value'] !== '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));

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
		$new_tag_filter = $this->getInput('new_tag_filter') + [
			'groupids' => [],
			'tag' => '',
			'value' => '',
			'include_subgroups' => '0'
		];

		$groupids = $new_tag_filter['include_subgroups']
			? getSubGroups($new_tag_filter['groupids'])
			: $new_tag_filter['groupids'];

		$tag_filters = $this->getInput('tag_filters', []);

		foreach ($groupids as $groupid) {
			$tag_filters[] = [
				'groupid' => $groupid,
				'tag' => $new_tag_filter['tag'],
				'value' => $new_tag_filter['value']
			];
		}

		$this->setResponse(new CControllerResponseData([
			'tag_filters' => collapseTagFilters($tag_filters),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
