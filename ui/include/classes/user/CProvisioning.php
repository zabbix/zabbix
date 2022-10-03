<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Code responsible for user fields, media and groups provisioning.
 */
class CProvisioning {

	/**
	 * Provision user data to set in API.
	 */
	public const API_USER = [
		'type'		=> USER_TYPE_SUPER_ADMIN,
		'userid'	=> 0,
		'username'	=> 'System'
	];

	public const FALLBACK_GROUP_NAME = '*';

	/**
	 * User directory data array.
	 *
	 * @var string $userdirectory['user_username']
	 * @var string $userdirectory['user_lastname']
	 * @var string $userdirectory['search_attribute']
	 * @var array  $userdirectory['provision_media']
	 * @var string $userdirectory['provision_media'][]['mediatypeid']
	 * @var string $userdirectory['provision_media'][]['attribute']
	 * @var string $userdirectory['provision_groups']
	 *
	 * @var array
	 */
	protected $userdirectory = [];

	public function __construct(array $userdirectory) {
		$userdirectory['provision_groups'] = $this->sortProvisionGroups($userdirectory['provision_groups']);
		$this->userdirectory = $userdirectory;
	}

	/**
	 * Sort provision_groups objects according 'sortorder' column, fallback will be set as last element.
	 * If sortorder property is not set for at least one element all provision_groups elements will be assigned
	 * autoincrementing 'sortorder' field.
	 *
	 * @param array $provision_groups  Array of group mapping definitions.
	 *
	 * @return array
	 */
	public function sortProvisionGroups(array $provision_groups): array {
		if (count(array_column($provision_groups, 'sortorder')) != count($provision_groups)) {
			$i = 0;

			foreach ($provision_groups as &$provision_group) {
				$provision_group['sortorder'] = $i++;
			}
			unset($provision_group);
		}

		foreach ($provision_groups as &$provision_group) {
			if ($provision_group['name'] == CProvisioning::FALLBACK_GROUP_NAME) {
				$provision_group['sortorder'] = max(
					max(array_column($provision_groups, 'sortorder'),
					count($provision_groups))
				);
				break;
			}
		}
		unset($provision_group);

		CArrayHelper::sort($provision_groups, ['sortorder']);

		return $provision_groups;
	}

	/**
	 * Get array of attributes to request from external source when requesting user data.
	 *
	 * @return array Array of attributes names to request from external source.
	 */
	public function getUserIdpAttributes(): array {
		$attributes = [];

		switch ($this->userdirectory['idp_type']) {
			case IDP_TYPE_LDAP:
				$fields = ['user_username', 'user_lastname', 'search_attribute', 'group_membership'];

				break;

			case IDP_TYPE_SAML:
				$fields = ['user_username', 'user_lastname'];

				break;
		}

		$attributes = array_intersect_key($this->userdirectory, array_flip($fields));
		$attributes = array_merge(array_column($this->userdirectory['provision_media'], 'attribute'), $attributes);

		return array_keys(array_flip(array_filter($attributes, 'strlen')));
	}

	/**
	 * Get array of attributes to request from external source when requesting user group data.
	 *
	 * @return array
	 */
	public function getGroupIdpAttributes(): array {
		$attributes = [$this->userdirectory['group_name']];

		return array_values(array_filter($attributes, 'strlen'));
	}

	/**
	 * Return array with user data created from external user data attributes.
	 *
	 * @param array $idp_user       User data from external source.
	 *
	 * @return array
	 *         ['medias']                   Array of user media extracted from external user data.
	 *                                      Empty array when no media found.
	 *         ['medias'][]['mediatypeid']  User media type id.
	 *         ['medias'][]['sendto']       Array with single entry of media notification recipient.
	 *         ['usrgrps']                  Array of user groups extracted from external user data.
	 *                                      Is set when user groups data were found in external data.
	 *         ['usrgrps'][]['usrgrpid']    Matched user group id.
	 */
	public function getUser(array $idp_user): array {
		$user = array_merge(['medias' => []], $this->getUserAttributes($idp_user, $this->userdirectory));

		if ($this->userdirectory['provision_media']) {
			$user['medias'] = $this->getUserMedias($idp_user);
		}

		if ($this->userdirectory['idp_type'] == IDP_TYPE_LDAP && $this->userdirectory['group_membership'] !== ''
				&& array_key_exists($this->userdirectory['group_membership'], $idp_user)) {
			/**
			 * Attribute to search for groups in user data defined, and if there will be no match, 'usrgrps' key
			 * should exist to do not attempt to query LDAP for user groups once again.
			 */
			$user['usrgrps'] = [];
			$user = array_merge($user,
				$this->getUserGroupsAndRole($idp_user[$this->userdirectory['group_membership']])
			);
		}

		return $user;
	}

	/**
	 * Return array with user fields created from matched provision data on external user data attributes.
	 *
	 * @param array $idp_user    User data from external source, LDAP/SAML.
	 *
	 * @return array
	 */
	public function getUserAttributes(array $idp_user): array {
		$user = [];

		if ($this->userdirectory['user_username'] !== ''
				&& array_key_exists($this->userdirectory['user_username'], $idp_user)) {
			$user['name'] = $idp_user[$this->userdirectory['user_username']];
		}

		if ($this->userdirectory['user_lastname'] !== ''
				&& array_key_exists($this->userdirectory['user_lastname'], $idp_user)) {
			$user['surname'] = $idp_user[$this->userdirectory['user_lastname']];
		}

		return $user;
	}

	/**
	 * Return array with user media created from matched provision_media on external user data attributes.
	 *
	 * @param array $idp_user    User data from external source, LDAP/SAML.
	 *
	 * @return array
	 *         []['mediatypeid']
	 *         []['sendto']
	 */
	public function getUserMedias(array $idp_user): array {
		$user_medias = [];
		$attributes = array_column($this->userdirectory['provision_media'], null, 'mediatypeid');

		foreach ($attributes as $mediatypeid => $idp_attributes) {
			$idp_attribute = strtolower($idp_attributes['attribute']);

			if (!array_key_exists($idp_attribute, $idp_user)) {
				continue;
			}

			$user_medias[] = [
				'name' => $idp_attributes['name'],
				'mediatypeid' => $mediatypeid,
				'sendto' =>	[$idp_user[$idp_attribute]],
			];
		}

		return $user_medias;
	}

	/**
	 * Return array with user groups matched to provision groups criteria. Return first matched mapping groups.
	 * Fallback mapping when set should be last in provision_groups list of group mappings.
	 *
	 * @param array $group_names    User group names data from external source, LDAP/SAML.
	 *
	 * @return array
	 *         ['roleid']                 Matched roleid to set for user.
	 *         ['usrgrps']                Matched mapping user groups to set for user.
	 *         ['usrgrps'][]['usrgrpid']  User group
	 */
	public function getUserGroupsAndRole(array $group_names): array {
		$user = [];

		if (!$group_names || !$this->userdirectory['provision_groups']) {
			return $user;
		}

		foreach ($this->userdirectory['provision_groups'] as $provision_group) {
			if (strpos($provision_group['name'], '*') === false) {
				$match = in_array($provision_group['name'], $group_names);
			}
			else {
				$regex = preg_quote($provision_group['name'], '/');
				$regex = '/'.str_replace('\\*', '.*', $regex).'/';
				$match = (bool) array_filter($group_names, function ($group_name) use ($regex) {
					return preg_match($regex, $group_name);
				});
			}

			if ($match) {
				$user['roleid'] = $provision_group['roleid'];
				$user['usrgrps'] = $provision_group['user_groups'];

				break;
			}
		}

		return $user;
	}
}
