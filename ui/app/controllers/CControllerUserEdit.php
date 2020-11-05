<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
		$locales = array_keys(getLocales());
		$locales[] = LANG_DEFAULT;
		$timezones = DateTimeZone::listIdentifiers();
		$timezones[] = TIMEZONE_DEFAULT;
		$themes = array_keys(APP::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'userid' =>				'db users.userid',
			'alias' =>				'db users.alias',
			'name' =>				'db users.name',
			'surname' =>			'db users.surname',
			'user_groups' =>		'array_id|not_empty',
			'change_password' =>	'in 1',
			'password1' =>			'string',
			'password2' =>			'string',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'timezone' =>			'db users.timezone|in '.implode(',', $timezones),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'refresh' =>			'db users.refresh',
			'rows_per_page' =>		'db users.rows_per_page',
			'url' =>				'db users.url',
			'medias' =>				'array',
			'new_media' =>			'array',
			'enable_media' =>		'int32',
			'disable_media' =>		'int32',
			'roleid' =>				'db users.roleid',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)) {
			return false;
		}

		if ($this->getInput('userid', 0) != 0) {
			$users = API::User()->get([
				'output' => ['alias', 'name', 'surname', 'lang', 'theme', 'autologin', 'autologout', 'refresh',
					'rows_per_page', 'url', 'roleid', 'timezone'
				],
				'selectMedias' => ['mediatypeid', 'period', 'sendto', 'severity', 'active'],
				'selectUsrgrps' => ['usrgrpid'],
				'selectRole' => ['name', 'type'],
				'userids' => $this->getInput('userid'),
				'editable' => true
			]);

			if (!$users) {
				return false;
			}

			$this->user = $users[0];
		}

		return true;
	}

	protected function doAction() {
		$db_defaults = DB::getDefaults('users');

		$data = [
			'userid' => 0,
			'alias' => '',
			'name' => '',
			'surname' => '',
			'password1' => '',
			'password2' => '',
			'lang' => $db_defaults['lang'],
			'timezone' => $db_defaults['timezone'],
			'theme' => $db_defaults['theme'],
			'autologin' => $db_defaults['autologin'],
			'autologout' => '0',
			'refresh' => $db_defaults['refresh'],
			'rows_per_page' => $db_defaults['rows_per_page'],
			'url' => '',
			'medias' => [],
			'new_media' => [],
			'roleid' => '',
			'role' => [],
			'user_type' => '',
			'sid' => $this->getUserSID(),
			'form_refresh' => 0,
			'action' => $this->getAction(),
			'db_user' => ['alias' => '']
		];
		$user_groups = [];

		if ($this->getInput('userid', 0) != 0) {
			$data['userid'] = $this->getInput('userid');
			$data['alias'] = $this->user['alias'];
			$data['name'] = $this->user['name'];
			$data['surname'] = $this->user['surname'];
			$user_groups = zbx_objectValues($this->user['usrgrps'], 'usrgrpid');
			$data['change_password'] = $this->hasInput('change_password') || $this->hasInput('password1');
			$data['password1'] = '';
			$data['password2'] = '';
			$data['lang'] = $this->user['lang'];
			$data['timezone'] = $this->user['timezone'];
			$data['theme'] = $this->user['theme'];
			$data['autologin'] = $this->user['autologin'];
			$data['autologout'] = $this->user['autologout'];
			$data['refresh'] = $this->user['refresh'];
			$data['rows_per_page'] = $this->user['rows_per_page'];
			$data['url'] = $this->user['url'];
			$data['medias'] = $this->user['medias'];
			$data['db_user']['alias'] = $this->user['alias'];

			if (!$this->getInput('form_refresh', 0)) {
				$data['roleid'] = $this->user['roleid'];
				$data['user_type'] = $this->user['role']['type'];
				$data['role'] = [['id' => $data['roleid'], 'name' => $this->user['role']['name']]];
			}
		}
		else {
			$data['change_password'] = true;
			$data['roleid'] = $this->getInput('roleid', '');
		}

		// Overwrite with input variables.
		$this->getInputs($data, ['alias', 'name', 'surname', 'password1', 'password2', 'lang', 'timezone', 'theme',
			'autologin', 'autologout', 'refresh', 'rows_per_page', 'url', 'form_refresh', 'roleid'
		]);
		if ($data['form_refresh'] != 0) {
			$user_groups = $this->getInput('user_groups', []);
			$data['medias'] = $this->getInput('medias', []);
		}

		$data = $this->setUserMedias($data);

		$data['groups'] = $user_groups
			? API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => $user_groups
			])
			: [];
		CArrayHelper::sort($data['groups'], ['name']);
		$data['groups'] = CArrayHelper::renameObjectsKeys($data['groups'], ['usrgrpid' => 'id']);

		if ($data['form_refresh'] && $this->hasInput('roleid')) {
			$roles = API::Role()->get([
				'output' => ['name', 'type'],
				'roleids' => $data['roleid']
			]);

			if ($roles) {
				$data['role'] = [['id' => $data['roleid'], 'name' => $roles[0]['name']]];
				$data['user_type'] = $roles[0]['type'];
			}
		}

		if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
			$data['groups_rights'] = [
				'0' => [
					'permission' => PERM_READ_WRITE,
					'name' => '',
					'grouped' => '1'
				]
			];
		}
		else {
			$data['groups_rights'] = collapseHostGroupRights(getHostGroupsRights($user_groups));
		}

		$data['modules'] = API::Module()->get([
			'output' => ['id'],
			'filter' => ['status' => MODULE_STATUS_ENABLED],
			'preservekeys' => true
		]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of users'));
		$this->setResponse($response);
	}
}
