<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CGroupPrototypeCollectionValidator extends CCollectionValidator {

	/**
	 * Error message if the collection has no group prototypes based on host groups.
	 *
	 * @var string
	 */
	public $messageHostGroups;

	/**
	 * Error message if two group prototypes have duplicate names.
	 *
	 * @var string
	 */
	public $messageDuplicateName;

	/**
	 * Error message if two group prototypes have duplicate group IDs.
	 *
	 * @var string
	 */
	public $messageDuplicateGroupId;

	/**
	 * Checks that a host prototype has at least one group prototype based on a host group.
	 */
	public function validate($value)
	{
		if (parent::validate($value)) {
			// check that at least one group prototype with a host group exists
			$hasHostGroup = false;
			foreach ($value as $groupPrototype) {
				if (isset($groupPrototype['groupid'])) {
					$hasHostGroup = true;
				}
			}
			if (!$hasHostGroup) {
				$this->error($this->messageHostGroups);
				return false;
			}

			// check for name duplicates
			// keep in mind that some group prototypes may not have a name set
			$names = array();
			foreach ($value as $groupPrototype) {
				if (isset($groupPrototype['name'])) {
					if (isset($names[$groupPrototype['name']])) {
						$this->error($this->messageDuplicateName, $groupPrototype['name']);

						return false;
					}

					$names[$groupPrototype['name']] = true;
				}
			}

			// check for group ID duplicates
			// keep in mind that some group prototypes may not have a group ID set
			$groupIds = array();
			foreach ($value as $groupPrototype) {
				if (isset($groupPrototype['groupid'])) {
					if (isset($groupIds[$groupPrototype['groupid']])) {
						$this->error($this->messageDuplicateGroupId, $groupPrototype['groupid']);

						return false;
					}

					$groupIds[$groupPrototype['groupid']] = true;
				}
			}

			return true;
		}
		else {
			return false;
		}
	}

}