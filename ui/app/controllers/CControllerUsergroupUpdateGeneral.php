<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * Class containing operations for updating/creating a usergroup.
 */
abstract class CControllerUsergroupUpdateGeneral extends CController {

	protected array $db_hostgroups;
	protected array $db_templategroups;

	final protected function loadDbGroups(): void {
		$this->db_hostgroups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);
		$this->db_templategroups = API::TemplateGroup()->get([
			'output' => ['groupid', 'name']
		]);
	}

	private static function processTagFilters(array $input_tag_filters): array {
		$tag_filters = [];

		foreach ($input_tag_filters as $hostgroup) {
			foreach ($hostgroup['tags'] as $tag_filter) {
				if ($hostgroup['groupid'] != 0) {
					$tag_filters[] = [
						'groupid' => $hostgroup['groupid'],
						'tag' => $tag_filter['tag'],
						'value' => $tag_filter['value']
					];
				}
			}
		}

		return $tag_filters;
	}

	protected function getUserGroupInputData(): array {
		$user_group = [];
		$user_group['users'] = array_map(static fn($userid) => ['userid' => $userid], $this->getInput('userids', []));
		$this->getInputs($user_group, ['users_status', 'gui_access', 'debug_mode', 'userdirectoryid', 'mfaid',
			'name', 'hostgroup_rights', 'templategroup_rights', 'tag_filters'
		]);

		return $user_group;
	}

	final protected function processUserGroupInputData(array &$user_group): bool {
		if (!self::checkGroupsExist($user_group['hostgroup_rights'], $this->db_hostgroups, true)
				|| !self::checkGroupsExist($user_group['templategroup_rights'], $this->db_templategroups, true)
				|| !self::checkGroupsExist($user_group['tag_filters'], $this->db_hostgroups, false)) {
			CMessageHelper::addError(_('No permissions to referred object or it does not exist!'));

			return false;
		}

		$user_group['hostgroup_rights'] = self::processRights($user_group['hostgroup_rights']);
		$user_group['templategroup_rights'] = self::processRights($user_group['templategroup_rights']);
		$user_group['tag_filters'] = self::processTagFilters($user_group['tag_filters']);

		if (array_key_exists('mfaid', $user_group) && $user_group['mfaid'] == -1) {
			$user_group['mfa_status'] = GROUP_MFA_DISABLED;
			unset($user_group['mfaid']);
		}
		elseif (array_key_exists('mfaid', $user_group)) {
			$user_group['mfa_status'] = GROUP_MFA_ENABLED;
		}

		return true;
	}

	private static function checkGroupsExist(array $groups, array $db_groups, bool $multi): bool {
		if ($multi) {
			$groupids = array_merge(...array_values(array_column($groups, 'groupids')));
		}
		else {
			$groupids = array_values(array_column($groups, 'groupid'));
		}

		$existing_groupids = array_column($db_groups, 'groupid');

		if (array_diff($groupids, $existing_groupids)) {
			return false;
		}

		return true;
	}

	private static function processRights(array $rights): array {
		$processed_rights = [];

		foreach ($rights as $right) {
			foreach ($right['groupids'] as $groupid) {
				if (array_key_exists($groupid, $processed_rights)) {
					$processed_rights[$groupid]['permission'] = min($processed_rights[$groupid]['permission'],
						$right['permission']
					);
				}
				else {
					$processed_rights[$groupid] = [
						'id' => $groupid,
						'permission' => $right['permission']
					];
				}
			}
		}

		return array_values($processed_rights);
	}
}
