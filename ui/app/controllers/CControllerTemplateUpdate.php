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


class CControllerTemplateUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'templateid' =>			'required|db hosts.hostid',
			'template_name' =>		'required|db hosts.host|not_empty',
			'visiblename' =>		'db hosts.name',
			'templates' =>			'array_db hosts.hostid',
			'add_templates' =>		'array_db hosts.hostid',
			'clear_templates' =>	'array_db hosts.hostid',
			'groups' =>				'required|array',
			'description' =>		'db hosts.description',
			'tags' =>				'array',
			'macros' =>				'array',
			'valuemaps' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update template'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}

		return true;
	}

	protected function doAction(): void {
		$templateid = $this->getInput('templateid');
		$template_name = $this->getInput('template_name', '');
		$tags = $this->getInput('tags', []);

		// Linked templates.
		$templates = [];

		foreach (array_merge($this->getInput('templates', []), $this->getInput('add_templates', [])) as $linked_id) {
			$templates[] = ['templateid' => $linked_id];
		}

		// Clear templates.
		$templates_clear = array_diff(
			$this->getInput('clear_templates', []),
			$this->getInput('add_templates', [])
		);

		// Add new group.
		$groups = $this->getInput('groups', []);
		$new_groups = [];

		foreach ($groups as $idx => $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				$new_groups[] = ['name' => $group['new']];
				unset($groups[$idx]);
			}
		}

		if ($new_groups) {
			$new_groupid = API::TemplateGroup()->create($new_groups);

			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		foreach ($tags as $key => $tag) {
			// Remove empty new tag lines.
			if ($tag['tag'] === '' && $tag['value'] === '') {
				unset($tags[$key]);
				continue;
			}

			// Remove inherited tags.
			if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
				unset($tags[$key]);
			}
			else {
				unset($tags[$key]['type']);
			}
		}

		// Remove inherited macros data.
		$macros = cleanInheritedMacros($this->getInput('macros', []));

		// Remove empty new macro lines.
		$macros = array_filter($macros, function($macro) {
			$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

			return (bool) array_filter(array_intersect_key($macro, $keys));
		});

		foreach ($macros as &$macro) {
			unset($macro['discovery_state']);
			unset($macro['allow_revert']);
		}
		unset($macro);

		// Value maps.
		$valuemaps = $this->getinput('valuemaps', []);
		$ins_valuemaps = [];
		$upd_valuemaps = [];

		$del_valuemapids = API::ValueMap()->get([
			'output' => [],
			'hostids' => $templateid,
			'preservekeys' => true
		]);

		foreach ($valuemaps as $valuemap) {
			if (array_key_exists('valuemapid', $valuemap)) {
				$upd_valuemaps[] = $valuemap;
				unset($del_valuemapids[$valuemap['valuemapid']]);
			}
			else {
				$ins_valuemaps[] = $valuemap + ['hostid' => $templateid];
			}
		}

		if ($upd_valuemaps && !API::ValueMap()->update($upd_valuemaps)) {
			throw new Exception();
		}

		if ($ins_valuemaps && !API::ValueMap()->create($ins_valuemaps)) {
			throw new Exception();
		}

		if ($del_valuemapids && !API::ValueMap()->delete(array_keys($del_valuemapids))) {
			throw new Exception();
		}

		$template = [
			'templateid' => $templateid,
			'host' => $template_name,
			'name' => $this->getInput('visiblename', '') ?: $template_name,
			'templates' => $templates,
			'templates_clear' => zbx_toObject($templates_clear, 'templateid'),
			'groups' => zbx_toObject($groups, 'groupid'),
			'description' => $this->getInput('description', ''),
			'tags' => $tags,
			'macros' => $macros
		];

		$result = API::Template()->update($template);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Template updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update template'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
