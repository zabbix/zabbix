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


class CControllerTemplateUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'templateid' =>			'required|db hosts.hostid',
			'template_name' =>		'required|db hosts.host|not_empty',
			'visiblename' =>		'db hosts.name',
			'groups' =>				'required|array',
			'description' =>		'db hosts.description',
			'tags' =>				'array',
			'macros' =>				'array',
			'valuemaps' =>			'array',
			'templates' =>			'array_db hosts.hostid',
			'add_templates' =>		'array_db hosts.hostid',
			'clear_templates' =>	'array_db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add template'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$templateid = $this->getInput('templateid');
		$tags = $this->getInput('tags', []);

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

		// todo - fix exceptions:
		if ($upd_valuemaps && !API::ValueMap()->update($upd_valuemaps)) {
			throw new Exception();
		}

		if ($ins_valuemaps && !API::ValueMap()->create($ins_valuemaps)) {
			throw new Exception();
		}

		if ($del_valuemapids && !API::ValueMap()->delete(array_keys($del_valuemapids))) {
			throw new Exception();
		}

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

			// todo - fix exceptions:
			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		// Linked templates.
		$templates = [];

		foreach (array_merge($this->getInput('templates', []), $this->getInput('add_templates', [])) as $linked_id) {
			$templates[] = ['templateid' => $linked_id];
		}

		$template_name = $this->getInput('template_name', '');

		$save_macros = $macros;

		foreach ($save_macros as &$macro) {
			unset($macro['allow_revert']);
		}
		unset($macro);

		$template = [
			'host' => $template_name,
			'name' => ($this->getInput('visiblename', '') === '') ? $template_name : $this->getInput('visiblename'),
			'description' => $this->getInput('description', ''),
			'groups' => zbx_toObject($groups, 'groupid'),
			'templates' => $templates,
			'tags' => $tags,
			'macros' => $save_macros
		];

		$templates_clear = array_diff(
			$this->getInput('clear_templates', []),
			$this->getInput('add_templates', [])
		);

		$template['templateid'] = $templateid;
		$template['templates_clear'] = zbx_toObject($templates_clear, 'templateid');

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
