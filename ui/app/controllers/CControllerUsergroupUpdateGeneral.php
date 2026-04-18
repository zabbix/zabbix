<?php declare(strict_types = 0);
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
 * Class containing operations for updating/creating a usergroup.
 */
abstract class CControllerUsergroupUpdateGeneral extends CController {
	protected function getUserGroupInputData(): array {
		$user_group = $this->getInputAll();
		$user_group['users'] = zbx_toObject($user_group['userids'], 'userid');
		unset($user_group['userids']);

		return $user_group;
	}

	protected static function processUserGroupInputData(array $user_group): array {
		$user_group['hostgroup_rights'] = self::processRights($user_group['hostgroup_rights']);
		$user_group['templategroup_rights'] = self::processRights($user_group['templategroup_rights']);
		$user_group['tag_filters'] = self::processTagFilters($user_group['tag_filters']);

		return $user_group;
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
}
