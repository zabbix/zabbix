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


/**
 * Class containing operations with user edit form.
 */
class CControllerUserEdit extends CControllerUserEditGeneral {

	protected function checkInput() {
		$this->appendValidationRules([
			'alias' =>			'db users.alias',
			'name' =>			'db users.name',
			'surname' =>		'db users.surname',
			'user_type' =>		'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
			'user_groups' =>	'array_id|not_empty',
			'user_medias' =>	'array',
			'new_media' =>		'array',
			'enable_media' =>	'int32',
			'disable_media' =>	'int32'
		]);

		return parent::checkInput();
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$this->userid = $this->getInput('userid', 0);
		$this->options['output'][] = 'type';
		$this->options['selectMedias'] = ['mediatypeid', 'period', 'sendto', 'severity', 'active'];

		if (parent::checkPermissions()) {
			if ($this->userid != 0) {
				$this->user['user_type'] = $this->user['type'];
				unset($this->user['type']);
			}

			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Get user type, user groups and medias from DB.
	 */
	protected function getDBData() {
		$this->data['user_type'] = $this->user['user_type'];
		$user_groups = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'userids' => [$this->userid]
		]);
		$user_group = zbx_objectValues($user_groups, 'usrgrpid');
		$this->data['user_groups'] = zbx_toHash($user_group);
		$this->data['user_medias'] = $this->user['medias'];
	}

	/**
	 * Set user medias, calculate rights and user groups in data.
	 */
	protected function setFormData() {
		$this->setUserMedias();

		$this->data['groups'] = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $this->data['user_groups']
		]);
		order_result($this->data['groups'], 'name');

		if ($this->data['user_type'] == USER_TYPE_SUPER_ADMIN) {
			$this->data['groups_rights'] = [
				'0' => [
					'permission' => PERM_READ_WRITE,
					'name' => '',
					'grouped' => '1'
				]
			];
		}
		else {
			$this->data['groups_rights'] = collapseHostGroupRights(getHostGroupsRights($this->data['user_groups']));
		}
	}

	protected function doAction() {
		$this->data = $this->getDataDefaults();

		$this->data += [
			'user_medias' => [],
			'new_media' => [],
			'user_groups' => [],
			'user_type' => $this->db_defaults['type']
		];

		$this->fields = ['alias', 'name', 'surname', 'user_type', 'user_groups', 'user_medias', 'new_media'];
		$this->title = _('Configuration of users');

		parent::doAction();
	}
}
