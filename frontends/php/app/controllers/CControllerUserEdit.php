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
		$locales = array_keys(getLocales());
		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'userid' =>				'db users.userid',
			'alias' =>				'db users.alias',
			'name' =>				'db users.name',
			'surname' =>			'db users.surname',
			'user_groups' =>		'array_id|not_empty',
			'change_password' =>	'in 1',
			'password1' =>			'db users.passwd',
			'password2' =>			'db users.passwd',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'refresh' =>			'db users.refresh',
			'rows_per_page' =>		'db users.rows_per_page',
			'url' =>				'db users.url',
			'user_medias' =>		'array',
			'new_media' =>			'array',
			'enable_media' =>		'int32',
			'disable_media' =>		'int32',
			'type' =>				'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		if ($this->getInput('userid', 0) != 0) {
			$users = API::User()->get([
				'output' => ['alias', 'name', 'surname', 'lang', 'theme', 'autologin', 'autologout', 'refresh',
					'rows_per_page', 'url', 'type'
				],
				'selectMedias' => ['mediatypeid', 'period', 'sendto', 'severity', 'active'],
				'selectUsrgrps' => ['usrgrpid'],
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
		$config = select_config();

		$data = [
			'userid' => 0,
			'alias' => '',
			'name' => '',
			'surname' => '',
			'password1' => '',
			'password2' => '',
			'lang' => $db_defaults['lang'],
			'theme' => $db_defaults['theme'],
			'autologin' => $db_defaults['autologin'],
			'autologout' => '0',
			'refresh' => $db_defaults['refresh'],
			'rows_per_page' => $db_defaults['rows_per_page'],
			'url' => '',
			'user_medias' => [],
			'new_media' => [],
			'type' => $db_defaults['type'],
			'config' => [
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
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
			$data['theme'] = $this->user['theme'];
			$data['autologin'] = $this->user['autologin'];
			$data['autologout'] = $this->user['autologout'];
			$data['refresh'] = $this->user['refresh'];
			$data['rows_per_page'] = $this->user['rows_per_page'];
			$data['url'] = $this->user['url'];
			$data['user_medias'] = $this->user['medias'];
			$data['type'] = $this->user['type'];
			$data['db_user']['alias'] = $this->user['alias'];
		}
		else {
			$data['change_password'] = true;
		}

		// Overwrite with input variables.
		$this->getInputs($data, ['alias', 'name', 'surname', 'password1', 'password2', 'lang', 'theme', 'autologin',
			'autologout', 'refresh', 'rows_per_page', 'url', 'form_refresh', 'type'
		]);
		if ($data['form_refresh'] != 0) {
			$user_groups = $this->getInput('user_groups', []);
			$data['user_medias'] = $this->getInput('user_medias', []);
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

		if ($data['type'] == USER_TYPE_SUPER_ADMIN) {
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

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of users'));
		$this->setResponse($response);
	}
}
