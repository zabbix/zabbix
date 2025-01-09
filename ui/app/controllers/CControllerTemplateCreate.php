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


class CControllerTemplateCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'template_name' =>		'required|db hosts.host|not_empty',
			'visiblename' =>		'db hosts.name',
			'templates' =>			'array_db hosts.hostid',
			'add_templates' =>		'array_db hosts.hostid',
			'groups' =>				'required|array',
			'description' =>		'db hosts.description',
			'tags' =>				'array',
			'macros' =>				'array',
			'valuemaps' =>			'array',
			'clone' =>				'in 1',
			'clone_templateid' =>	'db hosts.hostid'
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
		try {
			DBstart();
			$template_name = $this->getInput('template_name', '');

			// Linked templates.
			$templates = [];

			foreach (array_merge($this->getInput('templates', []), $this->getInput('add_templates', [])) as $templateid) {
				$templates[] = ['templateid' => $templateid];
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

				if (!$new_groupid) {
					throw new Exception();
				}

				$groups = array_merge($groups, $new_groupid['groupids']);
			}

			// Add tags.
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
				unset($macro['allow_revert']);
			}
			unset($macro);

			$template = [
				'host' => $template_name,
				'name' => $this->getInput('visiblename', '') ?: $template_name,
				'templates' => $templates,
				'groups' => zbx_toObject($groups, 'groupid'),
				'description' => $this->getInput('description', ''),
				'tags' =>$tags,
				'macros' => $macros
			];

			$result = API::Template()->create($template);

			if ($result === false) {
				throw new Exception();
			}

			$template = ['templateid' => $result['templateids'][0]] + $template;
			$src_templateid = $this->getInput('clone_templateid', 0);

			if (!$this->createValueMaps($template['templateid'], $this->getInput('valuemaps', []))
					|| ($this->hasInput('clone') && !$this->copyFromCloneSourceTemplate($src_templateid, $template))) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
			$result = false;
			DBend(false);
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

	/**
	 * Create valuemaps.
	 *
	 * @param string $tempplateid  Target template ID.
	 * @param array  $valuemaps    Array with valuemaps data.
	 *
	 * @return bool
	 */
	private function createValueMaps(string $templateid, array $valuemaps): bool {
		foreach ($valuemaps as $key => $valuemap) {
			unset($valuemap['valuemapid']);
			$valuemaps[$key] = $valuemap + ['hostid' => $templateid];
		}

		return !($valuemaps && !API::ValueMap()->create($valuemaps));
	}

	/**
	 * Copy http tests, items, triggers, graphs, discovery rules and template dashboards from source template to target
	 * template.
	 *
	 * @param string $src_templateid
	 * @param array  $dst_template
	 *
	 * @return bool
	 */
	private function copyFromCloneSourceTemplate(string $src_templateid, array $dst_template): bool {
		// First copy web scenarios with web items, so that later regular items can use web item as their master item.
		if (!copyHttpTests($src_templateid, $dst_template['templateid'])
			|| !CItemHelper::cloneTemplateItems($src_templateid, $dst_template)
			|| !CTriggerHelper::cloneTemplateTriggers($src_templateid, $dst_template['templateid'])
			|| !CGraphHelper::cloneTemplateGraphs($src_templateid, $dst_template['templateid'])
			|| !CLldRuleHelper::cloneTemplateItems($src_templateid, $dst_template)) {
			return false;
		}

		// Copy template dashboards.
		$db_template_dashboards = API::TemplateDashboard()->get([
			'output' => API_OUTPUT_EXTEND,
			'templateids' => $src_templateid,
			'selectPages' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		if ($db_template_dashboards) {
			$db_template_dashboards = CDashboardHelper::prepareForClone($db_template_dashboards,
				$dst_template['templateid']
			);

			if (!API::TemplateDashboard()->create($db_template_dashboards)) {
				return false;
			}
		}

		return true;
	}
}
