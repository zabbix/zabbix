<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Class containing operations with user edit form.
 */
class CControllerUserEdit extends CControllerUserEditGeneral {

	protected function checkInput(): bool {
		$locales = CControllerUserUpdateGeneral::getAllowedLocales();
		$themes = CControllerUserUpdateGeneral::getAllowedThemes();

		$fields = [
			'userid' =>				'db users.userid',
			'username' =>			'db users.username',
			'name' =>				'db users.name',
			'surname' =>			'db users.surname',
			'user_groups' =>		'array_id',
			'change_password' =>	'in 0,1',
			'current_password' =>	'string',
			'password1' =>			'string',
			'password2' =>			'string',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'timezone' =>			'db users.timezone|in '.implode(',', array_keys($this->timezones)),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'refresh' =>			'db users.refresh',
			'rows_per_page' =>		'db users.rows_per_page',
			'url' =>				'db users.url',
			'medias' =>				'array',
			'roleid' =>				'id',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)) {
			return false;
		}

		if ($this->getInput('userid', 0) != 0) {
			$users = API::User()->get([
				'output' => ['username', 'name', 'surname', 'lang', 'theme', 'autologin', 'autologout', 'refresh',
					'rows_per_page', 'url', 'roleid', 'timezone', 'provisioned'
				],
				'selectMedias' => ['mediaid', 'mediatypeid', 'period', 'sendto', 'severity', 'active',
					'provisioned'
				],
				'selectRole' => ['roleid'],
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

	protected function doAction(): void {
		$db_defaults = DB::getDefaults('users');

		$data = [
			'userid' => null,
			'username' => '',
			'name' => '',
			'surname' => '',
			'current_password' => '',
			'password1' => '',
			'password2' => '',
			'lang' => $db_defaults['lang'],
			'timezone' => $db_defaults['timezone'],
			'timezones' => $this->timezones,
			'theme' => $db_defaults['theme'],
			'autologin' => $db_defaults['autologin'],
			'autologout' => '0',
			'refresh' => $db_defaults['refresh'],
			'rows_per_page' => $db_defaults['rows_per_page'],
			'url' => '',
			'medias' => [],
			'roleid' => '',
			'role' => [],
			'modules_rules' => [],
			'proxies' => $this->getEmptyAccessResult(),
			'proxy_groups' => $this->getEmptyAccessResult(),
			'user_type' => '',
			'form_refresh' => 0,
			'action' => $this->getAction(),
			'db_user' => ['username' => ''],
			'readonly' => false
		];
		$user_groups = [];

		if ($this->getInput('userid', 0) != 0) {
			$data['userid'] = $this->getInput('userid');
			$data['username'] = $this->user['username'];
			$data['name'] = $this->user['name'];
			$data['surname'] = $this->user['surname'];
			$user_groups = array_column($this->user['usrgrps'], 'usrgrpid');
			$data['change_password'] = $this->hasInput('change_password') || $this->hasInput('password1');
			$data['current_password'] = '';
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
			$data['db_user']['username'] = $this->user['username'];
			$data['roleid_required'] = (bool) $this->user['role'];

			if (!$this->getInput('form_refresh', 0)) {
				$data['roleid'] = $this->user['roleid'];
			}

			if ($this->user['provisioned'] == CUser::PROVISION_STATUS_YES) {
				$data['readonly'] = true;
			}
		}
		else {
			$data['change_password'] = true;
			$data['roleid_required'] = true;
			$data['roleid'] = $this->getInput('roleid', '');
		}

		// Overwrite with input variables.
		$this->getInputs($data, ['username', 'name', 'surname', 'change_password', 'password1', 'password2', 'lang',
			'timezone', 'theme', 'autologin', 'autologout', 'refresh', 'rows_per_page', 'url', 'form_refresh', 'roleid'
		]);
		if ($data['form_refresh'] != 0) {
			$user_groups = $this->getInput('user_groups', []);
			$data['medias'] = $this->getInput('medias', []);
		}

		$data['password_requirements'] = $this->getPasswordRequirements();
		$data = $this->setUserMedias($data);

		$data['groups'] = $user_groups
			? API::UserGroup()->get([
				'output' => ['usrgrpid', 'name', 'userdirectoryid', 'proxy_mode', 'proxy_group_mode'],
				'usrgrpids' => $user_groups,
				'selectProxies' => ['proxyid', 'name'],
				'selectProxyGroups' => ['proxy_groupid', 'name']
			])
			: [];

		if (!empty($data['groups'])) {
			$limit = CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE);
			$db_proxies_count = API::Proxy()->get([
				'countOutput' => true,
				'proxy_groupids' => [0]
			]);

			$db_proxy_group_count = API::ProxyGroup()->get([
				'countOutput' => true
			]);

			$db_proxies = API::Proxy()->get([
				'output' => ['proxyid', 'name'],
				'proxy_groupids' => [0],
				'limit' => $limit
			]);

			$db_proxy_groups = API::ProxyGroup()->get([
				'output' => ['proxy_groupid', 'name'],
				'limit' => $limit
			]);

			$data['proxies'] = $this->resolveProxyAccess($db_proxies, $db_proxies_count, $data['groups']);
			$data['proxy_groups'] = $this->resolveProxyGroupAccess(
				$db_proxy_groups, $db_proxy_group_count, $data['groups']
			);
		}

		CArrayHelper::sort($data['groups'], ['name']);
		$data['groups'] = CArrayHelper::renameObjectsKeys($data['groups'], ['usrgrpid' => 'id']);

		$data['internal_auth'] = true;

		foreach ($data['groups'] as $group) {
			if ($group['userdirectoryid'] != 0) {
				$data['internal_auth'] = false;
				break;
			}
		}

		if ($data['roleid']) {
			$roles = API::Role()->get([
				'output' => ['name', 'type'],
				'selectRules' => ['services.read.mode', 'services.read.list', 'services.read.tag',
					'services.write.mode', 'services.write.list', 'services.write.tag', 'modules'
				],
				'roleids' => $data['roleid']
			]);

			if ($roles) {
				$role = $roles[0];

				$data['role'] = [['id' => $data['roleid'], 'name' => $role['name']]];
				$data['user_type'] = $role['type'];

				if ($role['rules']['services.read.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL) {
					$data['service_read_access'] = CRoleHelper::SERVICES_ACCESS_ALL;
				}
				elseif ($role['rules']['services.read.list'] || $role['rules']['services.read.tag']['tag'] !== '') {
					$data['service_read_access'] = CRoleHelper::SERVICES_ACCESS_LIST;
				}
				else {
					$data['service_read_access'] = CRoleHelper::SERVICES_ACCESS_NONE;
				}

				$data['service_read_list'] = API::Service()->get([
					'output' => ['serviceid', 'name'],
					'serviceids' => array_column($role['rules']['services.read.list'], 'serviceid')
				]);
				$data['service_read_tag'] = $role['rules']['services.read.tag'];

				if ($role['rules']['services.write.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL) {
					$data['service_write_access'] = CRoleHelper::SERVICES_ACCESS_ALL;
				}
				elseif ($role['rules']['services.write.list'] || $role['rules']['services.write.tag']['tag'] !== '') {
					$data['service_write_access'] = CRoleHelper::SERVICES_ACCESS_LIST;
				}
				else {
					$data['service_write_access'] = CRoleHelper::SERVICES_ACCESS_NONE;
				}

				$data['service_write_list'] = API::Service()->get([
					'output' => ['serviceid', 'name'],
					'serviceids' => array_column($role['rules']['services.write.list'], 'serviceid')
				]);
				$data['service_write_tag'] = $role['rules']['services.write.tag'];

				foreach ($role['rules']['modules'] as $rule) {
					$data['modules_rules'][$rule['moduleid']] = $rule['status'];
				}
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
			$data['templategroups_rights'] = [
				'0' => [
					'permission' => PERM_READ_WRITE,
					'name' => '',
					'grouped' => '1'
				]
			];
		}
		else {
			$data['groups_rights'] = collapseGroupRights(getHostGroupsRights($user_groups));
			$data['templategroups_rights'] = collapseGroupRights(getTemplateGroupsRights($user_groups));
		}

		$data['modules'] = [];

		$db_modules = API::Module()->get([
			'output' => ['moduleid', 'relative_path', 'status']
		]);

		if ($db_modules) {
			$module_manager = new CModuleManager(APP::getRootDir());

			foreach ($db_modules as $db_module) {
				$manifest = $module_manager->addModule($db_module['relative_path']);

				if ($manifest !== null) {
					$data['modules'][$db_module['moduleid']] = $manifest['name'];
				}
			}
		}

		natcasesort($data['modules']);

		$disabled_modules = array_filter($db_modules,
			static function(array $db_module): bool {
				return $db_module['status'] == MODULE_STATUS_DISABLED;
			}
		);

		$data['disabled_moduleids'] = array_column($disabled_modules, 'moduleid', 'moduleid');
		$data['js_validation_rules'] = $data['userid'] === null
			? (new CFormValidator(CControllerUserCreate::getValidationRules()))->getRules()
			: (new CFormValidator(CControllerUserUpdate::getValidationRules()))->getRules();

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of users'));
		$this->setResponse($response);
	}

	protected function resolveProxyAccess(array $all_proxies, int $total_count, array $user_groups): array {
		return $this->resolveAccess(
			$all_proxies,
			$total_count,
			$user_groups,
			'proxy_mode',
			'proxies',
			'proxyid'
		);
	}

	protected function resolveProxyGroupAccess(array $all_proxy_groups, int $total_count, array $user_groups): array {
		return $this->resolveAccess(
			$all_proxy_groups,
			$total_count,
			$user_groups,
			'proxy_group_mode',
			'proxy_groups',
			'proxy_groupid'
		);
	}

	private function resolveAccess(array $all_objects, int $total_count, array $user_groups, string $mode_key,
			string $object_key, string $id_key): array {
		$all_by_id = [];

		foreach ($all_objects as $object) {
			$all_by_id[$object[$id_key]] = $object['name'];
		}

		$allowed_ids = [];
		$denied_ids = [];

		$has_allow_rules = false;

		foreach ($user_groups as $user_group) {
			$object_ids = array_column($user_group[$object_key], $id_key);
			$is_allow_mode = (int) $user_group[$mode_key] === PROXY_MODE_ALLOW;

			if ($is_allow_mode && empty($object_ids)) {
				return [
					'list' => [],
					'mode' => PROXY_MODE_DENY,
					'more' => 0
				];
			}

			if (!$is_allow_mode && empty($object_ids)) {
				$allowed_ids = array_keys($all_by_id);
				continue;
			}

			if ($is_allow_mode) {
				$has_allow_rules = true;
				$allowed_ids = array_merge($allowed_ids, $object_ids);
			}
			else {
				$denied_ids = array_merge($denied_ids, $object_ids);
			}
		}

		$allowed_ids = array_unique($allowed_ids);
		$denied_ids = array_unique($denied_ids);

		if (!$has_allow_rules && empty($allowed_ids)) {
			$allowed_ids = array_keys($all_by_id);
		}

		$final_allowed_ids = array_diff($allowed_ids, $denied_ids);
		$allowed_lookup = array_flip($final_allowed_ids);

		$list = [];

		foreach ($all_by_id as $object_id => $object_name) {
			$list[] = [
				'name' => $object_name,
				'mode' => array_key_exists($object_id, $allowed_lookup) ? PROXY_MODE_ALLOW : PROXY_MODE_DENY
			];
		}

		return [
			'list' => count($final_allowed_ids) === $total_count ? [] : $list,
			'mode' => count($final_allowed_ids) === $total_count ? PROXY_MODE_ALLOW : PROXY_MODE_DENY,
			'more' => max(0, $total_count - count($all_objects))
		];
	}

	protected function getEmptyAccessResult(): array {
		return [
			'list' => [],
			'mode' => PROXY_MODE_DENY,
			'more' => 0
		];
	}
}
