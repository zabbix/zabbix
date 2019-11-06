<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerUsergroupEdit extends CController {

	/**
	 * @var array  Form object for adding new tag filter.
	 */
	protected $new_tag_filter = [
		'groupids' => [],
		'tag' => '',
		'value' => '',
		'include_subgroups' => false
	];

	/**
	 * @var array  Form object for adding new group right.
	 */
	protected $new_group_right = [
		'groupids' => [],
		'permission' => PERM_NONE,
		'include_subgroups' => false
	];

	/**
	 * @var array  Default values for usr group form object.
	 */
	protected $user_group = [
		'usrgrpid' => 0,
		'name' => '',
		'gui_access' => GROUP_GUI_ACCESS_SYSTEM,
		'users_status' => GROUP_STATUS_ENABLED,
		'debug_mode' => GROUP_DEBUG_MODE_DISABLED,
		'tag_filters' => []
	];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'usrgrpid'        => 'db usrgrp.usrgrpid',
			'name'            => 'db usrgrp.name',
			'userids'         => 'array_db users.userid',
			'gui_access'      => 'db usrgrp.gui_access|in '.implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED]),
			'users_status'    => 'db usrgrp.users_status|in '.GROUP_STATUS_ENABLED.','.GROUP_STATUS_DISABLED,
			'debug_mode'      => 'db usrgrp.debug_mode|in '.GROUP_DEBUG_MODE_ENABLED.','.GROUP_DEBUG_MODE_DISABLED,

			'group_rights'    => 'array',
			'tag_filters'     => 'array',

			'new_group_right' => 'array',
			'new_tag_filter'  => 'array',

			'form_refresh'    => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}
		else {
			$this->new_tag_filter = $this->getInput('new_tag_filter', []) + $this->new_tag_filter;
			$this->new_group_right = $this->getInput('new_group_right', []) + $this->new_group_right;
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		if ($this->hasInput('usrgrpid')) {
			$user_groups = API::UserGroup()->get([
				'output' => ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode'],
				'selectTagFilters' => ['groupid', 'tag', 'value'],
				'usrgrpids' => $this->getInput('usrgrpid'),
				'editable' => true
			]);

			if (!$user_groups) {
				return false;
			}

			$this->user_group = $user_groups[0];
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'usrgrpid'         => $this->getInput('usrgrpid',     $this->user_group['usrgrpid']),
			'name'             => $this->getInput('name',         $this->user_group['name']),
			'gui_access'       => $this->getInput('gui_access',   $this->user_group['gui_access']),
			'users_status'     => $this->getInput('users_status', $this->user_group['users_status']),
			'debug_mode'       => $this->getInput('debug_mode',   $this->user_group['debug_mode']),

			'group_rights'     => $this->getGroupRights(),
			'new_group_right'  => $this->new_group_right,

			'tag_filters'      => $this->getTagFilters(),
			'new_tag_filter'   => $this->new_tag_filter,

			'host_groups_ms'   => $this->getHostGroupsMs(),
			'users_ms'         => $this->getUsersMs(),

			'form_refresh'     => $this->getInput('form_refresh', 0),
			'can_update_group' => (
				$this->getInput('usrgrpid', 0) == 0) || granted2update_group($this->getInput('usrgrpid', 0)
			)
		];

		$response = new CControllerResponseData($data);

		$response->setTitle(_('Configuration of user groups'));
		$this->setResponse($response);
	}

	/**
	 * @return array
	 */
	protected function getGroupRights() {
		if ($this->hasInput('group_rights')) {
			return $this->getInput('group_rights');
		}

		return collapseHostGroupRights(getHostGroupsRights((array) $this->user_group['usrgrpid']));
	}

	/**
	 * @return array
	 */
	protected function getTagFilters() {
		if ($this->hasInput('tag_filters')) {
			return collapseTagFilters($this->getInput('tag_filters'));
		}

		return collapseTagFilters($this->user_group['tag_filters']);
	}

	/**
	 * Returs all needed host groups formatted for multiselector.
	 *
	 * @return array
	 */
	protected function getHostGroupsMs() {
		$host_groupids = array_merge($this->new_tag_filter['groupids'], $this->new_group_right['groupids']);

		if (!$host_groupids) {
			return [];
		}

		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $host_groupids,
			'preservekeys' => true
		]);
		CArrayHelper::sort($host_groups, ['name']);

		return CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']);
	}

	/**
	 * Returs all needed user formatted for multiselector.
	 *
	 * @return array
	 */
	private function getUsersMs() {
		$options = [
			'output' => ['userid', 'alias', 'name', 'surname']
		];

		if ($this->hasInput('usrgrpid') && !$this->hasInput('form_refresh')) {
			$options['usrgrpids'] = $this->getInput('usrgrpid');
		}
		else {
			$options['userids'] = $this->getInput('userids', []);
		}

		$users = (array_key_exists('usrgrpids', $options) || $options['userids'] !== [])
			? API::User()->get($options)
			: [];

		$users_ms = [];
		foreach ($users as $user) {
			$users_ms[] = ['id' => $user['userid'], 'name' => getUserFullname($user)];
		}

		CArrayHelper::sort($users_ms, ['name']);

		return $users_ms;
	}
}
