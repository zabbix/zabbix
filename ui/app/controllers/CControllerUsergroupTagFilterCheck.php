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


class CControllerUsergroupTagFilterCheck extends CController {

	/**
	 * @var array  Host group data from database.
	 */
	private $host_groups = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'groupid' =>			'db hosts_groups.groupid',
			'filter_type' =>		'in 1,0',
			'tag_filters' =>		'array',
			'ms_new_tag_filter' =>	'array',
			'new_tag_filter' =>		'array'
		];

		$ret = $this->validateInput($fields) && $this->validateTagFilters();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	/**
	 * Validates the tag filters provided in the input and checks if the host groups and tag values are provided.
	 *
	 * @return bool
	 */
	protected function validateTagFilters(): bool {
		$new_host_groups = $this->getInput('ms_new_tag_filter', []);
		$db_hostgroups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);

		$this->host_groups = $db_hostgroups ?: $this->host_groups;

		if (array_key_exists('groupids', $new_host_groups)) {
			foreach($new_host_groups['groupids'] as $groupid) {
				if (!in_array($groupid, array_column($this->host_groups, 'groupid'))) {
					error(_('No permissions to referred object or it does not exist!'));

					return false;
				}
			}
		}
		else {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty')));

			return false;
		}

		if ($this->hasInput('new_tag_filter')) {
			$empty_tags = 0;
			$unique_tag_filters = [];

			foreach ($this->getInput('new_tag_filter') as $tag_filter) {
				$tag = $tag_filter['tag'];
				$value = $tag_filter['value'];

				if ($tag === '' && $value !== '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));

					return false;
				}

				if (strlen($tag) > DB::getFieldLength('tag_filter', 'tag')) {
					error(_s('Invalid parameter "%1$s": %2$s.', $tag, _('value is too long')));

					return false;
				}

				if (strlen($value) > DB::getFieldLength('tag_filter', 'value')) {
					error(_s('Invalid parameter "%1$s": %2$s.', $value, _('value is too long')));

					return false;
				}

				if ($tag === '' && $value === '') {
					$empty_tags++;
					continue;
				}

				if (array_key_exists($tag, $unique_tag_filters) && $unique_tag_filters[$tag] === $value) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Tags'),
						_s('value "%1$s" already exists', '(tag, value)=('.$tag.', '.$value.')'))
					);

					return false;
				}
				else {
					$unique_tag_filters[$tag] = $value;
				}
			}

			if (count($this->getInput('new_tag_filter')) == $empty_tags) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Tags'), _('cannot be empty')));

				return false;
			}
		}
		elseif ($this->getInput('filter_type') == TAG_FILTER_LIST) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Tags'), _('cannot be empty')));

			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction(): void {
		$data['tag_filters'] = $this->getInput('tag_filters', []);
		$filter_type = $this->getInput('filter_type', TAG_FILTER_ALL);
		$opened_groupid = $this->getInput('groupid');
		$ms_groups = $this->getInput('ms_new_tag_filter', []);
		$groupids = $ms_groups['groupids'];
		$new_tag_filters = $this->getInput('new_tag_filter', []);

		if (!in_array($opened_groupid, $groupids)) {
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

				if ($filter_type == TAG_FILTER_ALL) {
					$existing_tag_filters = [['tag' => '', 'value' => '']];
				}

				if ($filter_type == TAG_FILTER_LIST) {
					foreach ($existing_tag_filters as $key => $existing_tag_filter) {
						if ($existing_tag_filter['tag'] === '') {
							unset($existing_tag_filters[$key]);
							break;
						}
						else {
							$is_still_present = false;

							foreach ($new_tag_filters as $new_tag_filter) {
								if ($existing_tag_filter['tag'] == $new_tag_filter['tag']
										&& $existing_tag_filter['value'] == $new_tag_filter['value']) {
									$is_still_present = true;
									break;
								}
							}

							// If the existing tag is not found in the opened for edit group's new tags list, remove it.
							if (!$is_still_present && $groupid == $opened_groupid) {
								unset($existing_tag_filters[$key]);
							}
						}
					}
				}

				foreach ($new_tag_filters as $new_tag_filter) {
					$is_duplicate = false;

					foreach ($existing_tag_filters as $existing_tag_filter) {
						// Skip duplicate tags.
						if ($new_tag_filter['tag'] == $existing_tag_filter['tag'] &&
								$new_tag_filter['value'] == $existing_tag_filter['value']) {
							$is_duplicate = true;
							break;
						}
					}

					// Add unique new tags to the host group's existing tags.
					if (!$is_duplicate) {
						$existing_tag_filters[] = $new_tag_filter;
					}
				}
			}
			else {
				$key = array_search($groupid, array_column($this->host_groups, 'groupid'));
				$name = $key !== false ? $this->host_groups[$key]['name'] : '';

				$data['tag_filters'][$groupid] = [
					'groupid' => $groupid,
					'name' => $name,
					'tags' => $filter_type == TAG_FILTER_ALL ? [['tag' => '', 'value' => '']] : $new_tag_filters
				];
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
