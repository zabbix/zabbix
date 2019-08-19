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


class CControllerUsergroupAddTagFilter extends CController {

	/**
	 * @var array  Form object for adding new tag filter.
	 */
	protected $new_tag_filter = [
		'groupids' => [],
		'tag' => '',
		'value' => '',
		'include_subgroups' => false,
	];

	protected function checkInput() {
		$fields = [
			'usrgrpid'        => 'db usrgrp.usrgrpid',
			'name'            => 'db usrgrp.name',
			'userids'         => 'array_db users.userid',
			'gui_access'      => 'db usrgrp.gui_access',
			'users_status'    => 'db usrgrp.users_status',
			'debug_mode'      => 'db usrgrp.debug_mode',

			'group_rights'    => 'array',
			'tag_filters'     => 'array',

			'new_tag_filter'  => 'array',
			'new_group_right' => 'array',

			'form_refresh'    => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}
		else {
			$this->new_tag_filter = $this->getInput('new_tag_filter') + $this->new_tag_filter;
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$form_data = $this->getInputAll();

		$url = (new CUrl('zabbix.php'))
			->setArgument('usrgrpid', $this->getInput('usrgrpid', 0))
			->setArgument('action', 'usergroup.edit');

		$response = new CControllerResponseRedirect($url->getUrl());

		if ($this->validateNewTagFilter($error)) {
			unset($form_data['new_tag_filter']);
			$form_data = $this->updateFormData($form_data);
		}
		else {
			$form_data['new_tag_filter'] = $this->new_tag_filter;
			$response->setMessageError($error);
		}

		$response->setFormData($form_data);
		$this->setResponse($response);
	}

	/**
	 * @param string $error
	 *
	 * @return bool
	 */
	protected function validateNewTagFilter(&$error) {
		if (!$this->new_tag_filter['groupids']) {
			$error = _s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty'));

			return false;
		}
		elseif ($this->new_tag_filter['tag'] === '' && $this->new_tag_filter['value'] !== '') {
			$error = _s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty'));

			return false;
		}

		return true;
	}

	/**
	 * @param array $form_data
	 *
	 * @return array
	 */
	protected function updateFormData(array $form_data) {
		$groupids = $this->new_tag_filter['include_subgroups']
			? getSubGroups($this->new_tag_filter['groupids'])
			: $this->new_tag_filter['groupids'];

		foreach ($groupids as $groupid) {
			$form_data['tag_filters'][] = [
				'groupid' => $groupid,
				'tag' => $this->new_tag_filter['tag'],
				'value' => $this->new_tag_filter['value'],
			];
		}

		return $form_data;
	}
}
