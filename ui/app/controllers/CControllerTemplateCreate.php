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


class CControllerTemplateCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'template_name' =>		'required|db hosts.host|not_empty',
			'visiblename' =>		'db hosts.name',
			'groups' =>				'required|array',
			'description' =>		'db hosts.description',
			'tags' =>				'array',
			'macros' =>				'array',
			'valuemaps' =>			'array',
			'templates' =>			'array_db hosts.hostid',
			'add_templates' =>		'array_db hosts.hostid',
			'clear_templates' =>	'array_db hosts.hostid',
			'clone' => 				'in 1'
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

		foreach (array_merge($this->getInput('templates', []), $this->getInput('add_templates', [])) as $templateid) {
			$templates[] = ['templateid' => $templateid];
		}

		$template_name = $this->getInput('template_name', '');

		// Configuration for template cloning.
		$clone = $this->hasInput('clone');
		if ($clone) {
			$warnings = [];

			// todo - move this to edit controller:
			if ($macros && in_array(ZBX_MACRO_TYPE_SECRET, array_column($macros, 'type'))) {
				// Reset macro type and value.
				$macros = array_map(function($value) {
					return ($value['type'] == ZBX_MACRO_TYPE_SECRET)
						? ['value' => '', 'type' => ZBX_MACRO_TYPE_TEXT] + $value
						: $value;
				}, $macros);

				$warnings[] = _('The cloned template contains user defined macros with type "Secret text". The value and type of these macros were reset.');
			}

			$macros = array_map(function($macro) {
				return array_diff_key($macro, array_flip(['hostmacroid']));
			}, $macros);

			$groups = $this->getInput('groups', []);
			$groupids = [];

			// Remove inaccessible groups from request, but leave "new".
			foreach ($groups as $group) {
				if (!is_array($group)) {
					$groupids[] = $group;
				}
			}

			if ($groupids) {
				$groups_allowed = API::TemplateGroup()->get([
					'output' => [],
					'groupids' => $groupids,
					'editable' => true,
					'preservekeys' => true
				]);

				if (count($groupids) != count($groups_allowed)) {
					$warnings[] = _("The template being cloned belongs to a template group you don't have write permissions to. Non-writable group has been removed from the new template.");
				}

				foreach ($groups as $idx => $group) {
					if (!is_array($group) && !array_key_exists($group, $groups_allowed)) {
						unset($groups[$idx]);
					}
				}

				$_REQUEST['groups'] = $groups;
			}

			if ($warnings) {
				if (count($warnings) > 1) {
					CMessageHelper::setWarningTitle(_('Cloned template parameter values have been modified.'));
				}

				array_map('CMessageHelper::addWarning', $warnings);
			}
		}

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

		$result = API::Template()->create($template);

		$input_templateid = $this->getInput('templateid', 0);

		if ($result) {
			$input_templateid = reset($result['templateids']);
		}

		// Value maps.
		$valuemaps = $this->getinput('valuemaps', []);
		$ins_valuemaps = [];

		if ($clone) {
			foreach ($valuemaps as &$valuemap) {
				unset($valuemap['valuemapid']);
			}
			unset($valuemap);
		}

		foreach ($valuemaps as $valuemap) {
			$ins_valuemaps[] = $valuemap + ['hostid' => $input_templateid];
		}

		// todo - check this:
		if ($ins_valuemaps && !API::ValueMap()->create($ins_valuemaps)) {
			$result = false;
		}

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Template added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add template'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
