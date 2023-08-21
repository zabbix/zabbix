<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

class CControllerUsergroupTagFilterCheck extends CController {

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

	protected function validateTagFilters(): bool {
		if (!array_key_exists('groupids', $this->getInput('ms_new_tag_filter', []))) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty')));

			return false;
		}

		if ($this->hasInput('new_tag_filter')) {
			foreach ($this->getInput('new_tag_filter') as $tag_filter) {
				if (($tag_filter['tag'] === '' && $tag_filter['value'] !== '')
						|| ($this->getInput('filter_type') == TAG_FILTER_LIST && $tag_filter['tag'] === ''
							&& $tag_filter['value'] === '')) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));

					return false;
				}
			}
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction(): void {
		$data['tag_filters'] = $this->getInput('tag_filters', []);
		$filter_type = $this->getInput('filter_type', TAG_FILTER_ALL);
		$ms_groups = $this->getInput('ms_new_tag_filter', []);
		$groupids = $ms_groups['groupids'];
		$new_tag_filters = $this->filterDuplicates($this->getInput('new_tag_filter', []));
		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);

		foreach ($groupids as $groupid) {
			// Check if this groupid exists in the tag_filters, check for duplicates, delete removed tags, add new tags.
			if (isset($data['tag_filters'][$groupid])) {
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
							// If the existing tag is not found in the new tags list, remove it (only from the host group which was open for editing).
							if (!$is_still_present && $groupid == $this->getInput('groupid')) {
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
				$key = array_search($groupid, array_column($host_groups, 'groupid'));
				$name = $key !== false ? $host_groups[$key]['name'] : '';

				$data['tag_filters'][$groupid] = [
					'groupid' => $groupid,
					'name' => $name,
					'tags' => $filter_type == TAG_FILTER_ALL ? [['tag' => '', 'value' => '']] : $new_tag_filters
				];
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}

	private function filterDuplicates($tag_filters): array {
		$unique_tag_filters = [];
		$used = [];

		foreach ($tag_filters as $tag_filter) {
			$unique_pair = $tag_filter['tag'].NAME_DELIMITER.$tag_filter['value'];
			if (!isset($used[$unique_pair])) {
				$used[$unique_pair] = true;
				$unique_tag_filters[] = $tag_filter;
			}
		}

		return $unique_tag_filters;
	}
}
