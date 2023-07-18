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


require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerTemplateEdit extends CController {

	/**
	 * @var mixed
	 */
	private $template = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'templateid' =>				'db hosts.hostid',
			'groupids' =>				'array_db hosts_groups.groupid',
			'template_name' =>			'db hosts.host',
			'visiblename' =>			'db hosts.name',
			'groups' =>					'array',
			'description' =>			'db hosts.description',
			'tags' =>					'array',
			'macros' =>					'array',
			'valuemaps' =>				'array',
			'templates' =>				'array_db hosts.hostid',
			'add_templates' =>			'array_db hosts.hostid',
			'show_inherited_macros' =>	'in 0,1',
			'clone' =>					'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}

		if ($this->hasInput('templateid')) {
			$templates = API::Template()->get([
				'output' => [],
				'templateids' => $this->getInput('templateid'),
				'editable' => true
			]);

			if (!$templates) {
				return false;
			}

			$this->template = $templates[0];
		}

		return true;
	}

	protected function doAction(): void {
		$templateid = $this->hasInput('templateid') ? $this->getInput('templateid') : null;
		$clone = $this->hasInput('clone');

		// Remove inherited macros data.
		$macros = cleanInheritedMacros($this->getInput('macros', []));

		// Remove empty new macro lines.
		$macros = array_filter($macros, function($macro) {
			$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

			return (bool) array_filter(array_intersect_key($macro, $keys));
		});

		$data = [
			'templateid' => $templateid,
			'template_name' =>$this->getInput('template_name', ''),
			'visible_name' => $this->getInput('visiblename', ''),
			'description' => $this->getInput('description', ''),
			'groups_ms' => [],
			'linked_templates' => $this->getInput('linked_templates', []),
			'templates' => $this->getInput('templates', []),
			'add_templates' => [],
			'original_templates' => $this->getInput('original_templates', []),
			'macros' => $macros,
			'tags' => $this->getInput('tags', []),
			'valuemaps' => $this->getInput('valuemaps', []),
			'readonly' => false,
			'show_inherited_macros' => $this->getInput('show_inherited_macros', 0),
			'vendor' => [],
			'clone' => $this->hasInput('clone')
		];

		// Add already linked and new templates when cloning element.
		$templates = [];

		if ($clone) {
			$request_linked_templates = $this->getInput('templates', $data['original_templates']);
			$request_add_templates = $this->getInput('add_templates', []);

			if ($request_linked_templates || $request_add_templates) {
				$templates = API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => array_merge($request_linked_templates, $request_add_templates),
					'preservekeys' => true
				]);

				$data['linked_templates'] = array_intersect_key($templates, array_flip($request_linked_templates));
				CArrayHelper::sort($data['linked_templates'], ['name']);

				$data['add_templates'] = array_intersect_key($templates, array_flip($request_add_templates));

				foreach ($data['add_templates'] as &$template) {
					$template = CArrayHelper::renameKeys($template, ['templateid' => 'id']);
				}
				unset($template);
			}
		}

		if ($this->hasInput('templateid')) {
			$dbTemplates = API::Template()->get([
				'output' => ['host', 'name', 'description', 'vendor_name', 'vendor_version'],
				'selectTemplateGroups' => ['groupid'],
				'selectParentTemplates' => ['templateid'],
				'selectMacros' => ['hostmacroid', 'hostid', 'macro', 'value', 'description', 'type', 'automatic'],
				'selectTags' => ['tag', 'value'],
				'selectValueMaps' => ['valuemapid', 'name', 'mappings'],
				'templateids' => $templateid
			]);

			$data['dbTemplate'] = reset($dbTemplates);

			if (!$clone) {
				$data['vendor'] = array_filter([
					'name' => $data['dbTemplate']['vendor_name'],
					'version' => $data['dbTemplate']['vendor_version']
				], 'strlen');
			}

			foreach ($data['dbTemplate']['parentTemplates'] as $parentTemplate) {
				$data['original_templates'][$parentTemplate['templateid']] = $parentTemplate['templateid'];
			}

			$data['tags'] = $data['dbTemplate']['tags'];
			$data['macros'] = $data['dbTemplate']['macros'];

			CArrayHelper::sort($data['dbTemplate']['valuemaps'], ['name']);

			$data['valuemaps'] = array_values($data['dbTemplate']['valuemaps']);
			$data['template_name'] = $data['dbTemplate']['host'];
			$data['visible_name'] = $data['dbTemplate']['name'];

			// Display empty visible name if equal to host name.
			if ($data['visible_name'] === $data['template_name']) {
				$data['visible_name'] = '';
			}
		}

		// Add description.
		$data['description'] = ($data['templateid'] !== null)
			? $data['dbTemplate']['description']
			: $this->getInput('description', '');

		// Insert empty tag row when no tags are present.
		if (count($data['tags']) == 0) {
			$data['tags'][] = ['tag' => '', 'value' => ''];
		}

		CArrayHelper::sort($data['tags'], ['tag', 'value']);

		// Add already linked templates.
		if (!$clone && array_key_exists('dbTemplate', $data)) {
			$templates = [];
			$linked_templates = $data['dbTemplate']['parentTemplates'];

			foreach ($linked_templates as $template) {
				$linked_template_ids[$template['templateid']] = $template['templateid'];
			}

			if ($linked_templates) {
				$templates = API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => $linked_template_ids,
					'preservekeys' => true
				]);

				$data['linked_templates'] = array_intersect_key($templates, array_flip($linked_template_ids));
				CArrayHelper::sort($data['linked_templates'], ['name']);
			}
		}

		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys($data['linked_templates']),
			'editable' => true,
			'preservekeys' => true
		]);

		// Configuration for template cloning.
		if ($clone) {
			$warnings = [];

			// Reset macro type and value.
			foreach ($macros as &$macro) {
				if (array_key_exists('allow_revert', $macro) && array_key_exists('value', $macro)) {
					$macro['deny_revert'] = true;

					unset($macro['allow_revert']);
				}
			}

			$secret_macro_reset = false;

			foreach ($macros as &$macro) {
				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET && !array_key_exists('value', $macro)) {
					$macro = [
							'type' => ZBX_MACRO_TYPE_TEXT,
							'value' => ''
						] + $macro;

					$secret_macro_reset = true;

					unset($macro['allow_revert']);
				}
			}
			unset($macro);

			if ($secret_macro_reset) {
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
			}

			if ($warnings) {
				if (count($warnings) > 1) {
					CMessageHelper::setWarningTitle(_('Cloned template parameter values have been modified.'));
				}

				array_map('CMessageHelper::addWarning', $warnings);
			}

			$data['macros'] = $macros;
			$data['warnings'] = $warnings;
			$data['clone_templateid'] = $this->getInput('templateid');
			$data['templateid'] = null;
		}

		// The empty inputs will not be shown if there are inherited macros, for example.
		if (!$data['macros']) {
			$macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => ZBX_MACRO_TYPE_TEXT];

			if ($data['show_inherited_macros']) {
				$macro['inherited_type'] = ZBX_PROPERTY_OWN;
			}

			$data['macros'][] = $macro;
		}

		// Add inherited macros to template macros.
		if ($data['show_inherited_macros']) {
			$data['macros'] = mergeInheritedMacros($data['macros'], getInheritedMacros(array_keys($templates)));
		}

		// Sort only after inherited macros are added. Otherwise, the list will look chaotic.
		$data['macros'] = array_values(order_macros($data['macros'], 'macro'));

		foreach ($data['macros'] as &$macro) {
			$macro['discovery_state'] = CControllerHostMacrosList::DISCOVERY_STATE_MANUAL;
		}
		unset($macro);

		if ($templateid !== null) {
			$groups = array_column($data['dbTemplate']['templategroups'], 'groupid');
		}
		elseif ($clone) {
			$groups = $this->getInput('groups', []);
		}
		else {
			$groups = $this->getInput('groupids', []);
		}

		$groupids = [];

		foreach ($groups as $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				continue;
			}

			$groupids[] = $group;
		}

		// Groups with R and RW permissions.
		$groups_all = $groupids
			? API::TemplateGroup()->get([
				'output' => ['name'],
				'groupids' => $groupids,
				'preservekeys' => true
			])
			: [];

		// Groups with RW permissions.
		$groups_rw = $groupids && (CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
			? API::TemplateGroup()->get([
				'output' => [],
				'groupids' => $groupids,
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		// Prepare data for multiselect.
		foreach ($groups as $group) {
			$disabled = (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) && !array_key_exists($group, $groups_rw);

			if (is_array($group) && array_key_exists('new', $group)) {
				$data['groups_ms'][] = [
					'id' => $group['new'],
					'name' => $group['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				];
			}
			elseif (array_key_exists($group, $groups_all)) {
				$data['groups_ms'][] = [
					'id' => $group,
					'name' => $groups_all[$group]['name'],
					'disabled' => $disabled
				];
			}
		}

		CArrayHelper::sort($data['groups_ms'], ['name']);

		foreach ($data['macros'] as &$macro) {
			if ($macro['type'] == ZBX_MACRO_TYPE_SECRET
				&& !array_key_exists('deny_revert', $macro) && !array_key_exists('value', $macro)) {
				$macro['allow_revert'] = true;
			}
		}
		unset($macro);

		// This data is used in template.edit.js.php.
		$data['macros_tab'] = [
			'linked_templates' => array_map('strval', array_keys($data['linked_templates'])),
			'add_templates' => array_map('strval', array_keys($data['add_templates']))
		];

		$data['user'] = ['debug_mode' => $this->getDebugMode()];
		$this->setResponse(new CControllerResponseData($data));
	}
}
