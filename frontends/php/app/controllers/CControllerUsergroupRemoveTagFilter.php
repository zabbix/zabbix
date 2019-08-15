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


class CControllerUsergroupRemoveTagFilter extends CController {

	protected function checkInput() {
		$fields = [
			'usrgrpid'        => 'db usrgrp.usrgrpid',
			'name'            => 'db usrgrp.name',
			'userids'         => 'array_db users.userid',
			'gui_access'      => 'db usrgrp.gui_access',
			'users_status'    => 'db usrgrp.users_status',
			'debug_mode'      => 'db usrgrp.debug_mode',

			'permissions'     => 'array',
			'tag_filters'     => 'array',

			'new_tag_filter'  => 'array',
			'new_group_right' => 'array',

			'index'           => 'int32',
			'form_refresh'    => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$old_input = $this->getInputAll();

		unset($old_input['tag_filters'][$this->getInput('index')]);

		$url = (new CUrl('zabbix.php'))
			->setArgument('usrgrpid', $this->getInput('usrgrpid', 0))
			->setArgument('action', 'usergroup.edit');

		$response = new CControllerResponseRedirect($url->getUrl());
		$response->setFormData($old_input);
		$this->setResponse($response);
	}
}
