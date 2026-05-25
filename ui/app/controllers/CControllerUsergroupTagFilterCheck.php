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


class CControllerUsergroupTagFilterCheck extends CController {

	/**
	 * @var array  Host group data from database.
	 */
	private $host_groups = [];
	private $host_group_ids = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'groupid' => ['db tag_filter.groupid'],
			'new_tag_groups' => ['array', 'required', 'not_empty', 'field' => ['db hstgrp.groupid']],
			'new_tag_filters' => ['objects', 'required',
				'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db tag_filter.value'],
					'tag' => [
						['db tag_filter.tag', 'required'],
						['db tag_filter.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
					]
				]
			],
			'tag_filters' => ['objects',
				'fields' => [
					'groupid' => ['db tag_filter.groupid'],
					'name' => ['string'],
					'tags' => ['objects',
						'uniq' => ['tag', 'value'],
						'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
						'fields' => [
							'value' => ['db tag_filter.value'],
							'tag' => [
								['db tag_filter.tag', 'required'],
								['db tag_filter.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
							]
						]
					]
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->getValidationRules()) && $this->validateHostGroups();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	/**
	 * Validates provided host groups
	 *
	 * @return bool
	 */
	protected function validateHostGroups(): bool {
		$host_groups = $this->getInput('new_tag_groups', []);
		$db_hostgroups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);

		$this->host_groups = $db_hostgroups ?: $this->host_groups;
		$this->host_group_ids = array_column($this->host_groups, 'groupid');

		foreach ($host_groups as $groupid) {
			if (!in_array($groupid, $this->host_group_ids)) {
				error(_('No permissions to referred object or it does not exist!'));

				return false;
			}
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction(): void {
		$data['tag_filters'] = $this->getInput('tag_filters', []);

		$opened_groupid = $this->getInput('groupid');
		$groupids = $this->getInput('new_tag_groups', []);
		$new_tag_filters = $this->getInput('new_tag_filters', []);

		if (array_key_exists($opened_groupid, $data['tag_filters'])) {
			unset($data['tag_filters'][$opened_groupid]);
		}

		foreach ($new_tag_filters as $key => $new_tag_filter) {
			if ($new_tag_filter['tag'] === '' && $new_tag_filter['value'] === '') {
				unset($new_tag_filters[$key]);
			}
		}

		foreach ($groupids as $groupid) {
			// Check if this groupid exists in the tag_filters, check for duplicates, delete removed tags, add new tags.
			if (array_key_exists($groupid, $data['tag_filters'])) {
				$existing_tag_filters = &$data['tag_filters'][$groupid]['tags'];

				foreach ($existing_tag_filters as $key => $existing_tag_filter) {
					if ($existing_tag_filter['tag'] === '') {
						unset($existing_tag_filters[$key]);
						break;
					}
					else {
						$is_still_present = false;

						foreach ($new_tag_filters as $new_tag_filter) {
							if ($existing_tag_filter['tag'] === $new_tag_filter['tag']
									&& $existing_tag_filter['value'] === $new_tag_filter['value']) {
								$is_still_present = true;
								break;
							}
						}

						// If the existing tag is not found in the opened for edit group's new tags list, remove it.
						if (!$is_still_present && bccomp($groupid, $opened_groupid) == 0) {
							unset($existing_tag_filters[$key]);
						}
					}
				}

				foreach ($new_tag_filters as $new_tag_filter) {
					$is_duplicate = false;

					foreach ($existing_tag_filters as $existing_tag_filter) {
						// Skip duplicate tags.
						if ($new_tag_filter['tag'] === $existing_tag_filter['tag']
								&& $new_tag_filter['value'] === $existing_tag_filter['value']) {
							$is_duplicate = true;
							break;
						}
					}

					// Add unique new tags to the host group's existing tags.
					if (!$is_duplicate) {
						$existing_tag_filters[] = $new_tag_filter;
					}
				}

				if (!$existing_tag_filters) {
					$existing_tag_filters = [['tag' => '', 'value' => '']];
				}
			}
			else {
				$key = array_search($groupid, $this->host_group_ids);
				$name = $key !== false ? $this->host_groups[$key]['name'] : '';

				$data['tag_filters'][$groupid] = [
					'groupid' => $groupid,
					'name' => $name,
					'tags' => !$new_tag_filters ? [['tag' => '', 'value' => '']] : $new_tag_filters
				];
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
