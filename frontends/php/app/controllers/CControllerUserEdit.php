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


class CControllerUserEdit extends CController {

	private $user = [];
	private $is_profile;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$supported_locales = array_keys(getLocales());

		$this->is_profile = ($this->getAction() === 'profile.edit') ? true : false;

		$fields = [
			'userid' =>				'db users.userid',
			'password1' =>			'db users.passwd',
			'password2' =>			'db users.passwd',
			'change_password' =>	'string',
			'lang' =>				'db users.lang|in '.implode(',', $supported_locales),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'autologout_visible' =>	'in 0,1',
			'url' =>				'string',
			'refresh' =>			'string',
			'rows_per_page' =>		'int32|ge 1|le 999999',
			'form_refresh' =>		'int32'
		];

		if ($this->is_profile) {
			$fields += [
				'messages' =>		'array'
			];
		}
		else {
			$fields += [
				'alias' =>			'db users.alias',
				'name' =>			'db users.name',
				'surname' =>		'db users.surname',
				'user_type' =>		'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
				'user_groups' =>	'array_id|not_empty'
			];
		}

		if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
			$fields += [
				'user_medias' =>	'array',
				'new_media' =>		'array',
				'enable_media' =>	'int32',
				'disable_media' =>	'int32'
			];
		}

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->is_profile && $this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}
		elseif ($this->is_profile && (CWebUser::isGuest() || !CWebUser::isLoggedIn())) {
			return false;
		}

		$userid = $this->is_profile ? CWebUser::$data['userid'] : $this->getInput('userid', 0);

		if ($userid != 0) {
			$output = ['alias', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh', 'theme',
				'rows_per_page'
			];
			if (!$this->is_profile) {
				$output[] = 'type';
			}

			$options = ['output' => $output];

			if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
				$options += ['selectMedias' => ['mediatypeid', 'period', 'sendto', 'severity', 'active']];
			}

			$options += [
				'userids' => [$userid],
				'editable' => true
			];

			$users = API::User()->get($options);

			if (!$users) {
				return false;
			}

			$this->user = $users[0];

			if (!$this->is_profile) {
				$this->user['user_type'] = $this->user['type'];
				unset($this->user['type']);
			}
		}

		return true;
	}

	protected function doAction() {
		$config = select_config();

		// Default values.
		$db_defaults = DB::getDefaults('users');

		$data = [
			'sid' => $this->getUserSID(),
			'userid' => 0,
			'alias' => '',
			'name' => '',
			'surname' => '',
			'change_password' => false,
			'config' => $config,
			'form_refresh' => 0,
			'password1' => '',
			'password2' => '',
			'url' => '',
			'lang' => $db_defaults['lang'],
			'theme' => $db_defaults['theme'],
			'autologin' => $db_defaults['autologin'],
			'autologout' => $db_defaults['autologout'],
			'autologout_visible' => 0,
			'refresh' => $db_defaults['refresh'],
			'rows_per_page' => $db_defaults['rows_per_page'],
		];

		if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
			$data += [
				'user_medias' => [],
				'new_media' => [],
			];
		}

		if ($this->is_profile) {
			$messages = getMessageSettings();
		}
		else {
			$data += [
				'user_groups' => [],
				'user_type' => $db_defaults['type']
			];
		}

		$userid = $this->is_profile ? CWebUser::$data['userid'] : $this->getInput('userid', 0);

		// Get values from the dabatase.
		if ($userid != 0) {
			if (!$this->is_profile) {
				$data['user_type'] = $this->user['user_type'];
				$user_groups = API::UserGroup()->get([
					'output' => ['usrgrpid'],
					'userids' => [$userid]
				]);
				$user_group = zbx_objectValues($user_groups, 'usrgrpid');
				$data['user_groups'] = zbx_toHash($user_group);
			}

			$data['userid'] = $userid;
			$data['alias'] = $this->user['alias'];
			$data['name'] = $this->user['name'];
			$data['surname'] = $this->user['surname'];
			$data['url'] = $this->user['url'];
			$data['password1'] = null;
			$data['password2'] = null;
			$data['autologin'] = $this->user['autologin'];
			$data['autologout'] = $this->user['autologout'];
			$data['autologout_visible']	= (!$this->hasInput('form_refresh')
				&& (bool) timeUnitToSeconds($this->user['autologout'])
			);
			$data['lang'] = $this->user['lang'];
			$data['theme'] = $this->user['theme'];
			$data['refresh'] = $this->user['refresh'];
			$data['rows_per_page'] = $this->user['rows_per_page'];

			if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
				$data['user_medias'] = $this->user['medias'];
			}
		}

		$fields = [
			'url',
			'password1',
			'password2',
			'change_password',
			'autologin',
			'autologout',
			'autologout_visible',
			'lang',
			'theme',
			'refresh',
			'rows_per_page',
			'refresh',
			'form_refresh'
		];

		if ($this->is_profile) {
			$fields = array_merge($fields, ['messages']);
		}
		else {
			$fields = array_merge($fields, ['alias', 'name', 'surname', 'user_type', 'user_groups']);
		}

		if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
			$fields = array_merge($fields, ['user_medias', 'new_media']);
		}

		// Overwrite with input variables.
		$this->getInputs($data, $fields);

		if ($this->hasInput('form_refresh')) {
			if (!$this->hasInput('user_groups')) {
				$data['user_groups'] = [];
			}

			if (!$this->hasInput('change_password') && ($this->hasInput('password1') || $this->hasInput('password2'))) {
				$data['change_password'] = true;
			}
		}

		if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
			if ($data['new_media']) {
				$data['user_medias'][] = $data['new_media'];
			}

			if ($data['user_medias']) {
				if ($this->hasInput('enable_media')) {
					if (array_key_exists($this->getInput('enable_media'), $data['user_medias'])) {
						$data['user_medias'][$this->getInput('enable_media')]['active'] = 0;
					}
				}
				elseif ($this->hasInput('disable_media')) {
					if (array_key_exists($this->getInput('disable_media'), $data['user_medias'])) {
						$data['user_medias'][$this->getInput('disable_media')]['active'] = 1;
					}
				}

				$media_type_descriptions = [];
				$db_media_types = DBselect(
					'SELECT mt.mediatypeid,mt.type,mt.description FROM media_type mt WHERE '.
						dbConditionInt('mt.mediatypeid', zbx_objectValues($data['user_medias'], 'mediatypeid'))
				);
				while ($db_media_type = DBfetch($db_media_types)) {
					$media_type_descriptions[$db_media_type['mediatypeid']]['description'] = $db_media_type['description'];
					$media_type_descriptions[$db_media_type['mediatypeid']]['mediatype'] = $db_media_type['type'];
				}

				foreach ($data['user_medias'] as &$media) {
					$media['description'] = $media_type_descriptions[$media['mediatypeid']]['description'];
					$media['mediatype'] = $media_type_descriptions[$media['mediatypeid']]['mediatype'];
					$media['send_to_sort_field'] = is_array($media['sendto'])
						? implode(', ', $media['sendto'])
						: $media['sendto'];
				}
				unset($media);

				CArrayHelper::sort($data['user_medias'], ['description', 'send_to_sort_field']);

				foreach ($data['user_medias'] as &$media) {
					unset($media['send_to_sort_field']);
				}
				unset($media);
			}
		}

		if ($this->is_profile) {
			// set messages
			$data['messages'] = $this->getInput('messages', []);
			if (!array_key_exists('enabled', $data['messages'])) {
				$data['messages']['enabled'] = 0;
			}
			if (!array_key_exists('sounds.recovery', $data['messages'])) {
				$data['messages']['sounds.recovery'] = 'alarm_ok.wav';
			}
			if (!array_key_exists('triggers.recovery', $data['messages'])) {
				$data['messages']['triggers.recovery'] = 0;
			}
			if (!array_key_exists('triggers.severities', $data['messages'])) {
				$data['messages']['triggers.severities'] = [];
			}

			$data['messages'] = array_merge($messages, $data['messages']);
		}
		else {
			$data['groups'] = API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => $data['user_groups']
			]);
			order_result($data['groups'], 'name');

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
				$data['groups_rights'] = collapseHostGroupRights(getHostGroupsRights($data['user_groups']));
			}
		}

		$data['is_profile'] = $this->is_profile;
		$data['action'] = $this->getAction();

		$response = new CControllerResponseData($data);
		$response->setTitle($this->is_profile ? _('User profile') : _('Configuration of users'));
		$this->setResponse($response);
	}
}
