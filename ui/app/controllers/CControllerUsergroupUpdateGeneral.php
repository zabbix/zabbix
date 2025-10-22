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

	abstract protected function setErrorResponse(?array $form_errors = null): void;

	protected function loadDbGroups(): void
	{
		$this->db_hostgroups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);
		$this->db_templategroups = API::TemplateGroup()->get([
			'output' => ['groupid', 'name']
		]);
	}

	protected function processTagFilters(array $input_tag_filters): ?array
	{
		$tag_filters = [];
		foreach ($input_tag_filters as $hostgroup) {
			if (in_array($hostgroup['groupid'], array_column($this->db_hostgroups, 'groupid'))) {
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
			else {
				return null;
			}
		}

		return $tag_filters;
	}

	protected function processUserGroupInputData(array &$user_group): bool {
		$user_group['users'] = zbx_toObject($this->getInput('userids', []), 'userid');
		$this->getInputs($user_group, ['users_status', 'gui_access', 'debug_mode', 'userdirectoryid', 'mfaid',
			'name', 'hostgroup_rights', 'templategroup_rights'
		]);

		if (!$this->checkGroupsExist($user_group['hostgroup_rights'],$this->db_hostgroups)
				|| !$this->checkGroupsExist($user_group['templategroup_rights'], $this->db_templategroups)) {
			CMessageHelper::addError(_('No permissions to referred object or it does not exist!'));

			return false;
		}
		$user_group['hostgroup_rights'] = $this->processRights($user_group['hostgroup_rights']);
		$user_group['templategroup_rights'] = $this->processRights($user_group['templategroup_rights']);

		$user_group['tag_filters'] = $this->processTagFilters($this->getInput('tag_filters', []));

		if (array_key_exists('mfaid', $user_group) && $user_group['mfaid'] == -1) {
			$user_group['mfa_status'] = GROUP_MFA_DISABLED;
			$user_group['mfaid'] = 0;
		}
		elseif (array_key_exists('mfaid', $user_group)) {
			$user_group['mfa_status'] = GROUP_MFA_ENABLED;
		}

		return true;
	}

	private function checkGroupsExist(array $groups, $db_groups): bool {
		$groupids = array_merge(...array_values(array_column($groups, 'groupids')));
		$existing_groupids = array_column($db_groups, 'groupid');

		if (array_diff($groupids, $existing_groupids)) {
			return false;
		}

		return true;
	}

	private function processRights(array $rights): array {
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
