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

	private ?array $src_template = null;

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['template.get', ['host' => '{template_name}']],
			['template.get', ['name' => '{visiblename}']],
			['template.get', ['name' => '{template_name}']],
			['host.get', ['host' => '{template_name}']],
			['host.get', ['name' => '{visiblename}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'template_name' => ['db hosts.host', 'required', 'not_empty', 'regex' => '/^'.ZBX_PREG_HOST_FORMAT.'$/',
				'messages' => ['regex' => _('Incorrect characters used for template name.')]
			],
			'visiblename' => ['db hosts.host'],
			'templates' => ['array', 'field' => ['db hosts.hostid']],
			'template_add_templates' => ['array', 'field' => ['db hosts.hostid']],
			'template_groups_new' => ['array', 'field' => ['db hstgrp.name']],
			'template_groups' => [
				['array', 'field' => ['db hstgrp.groupid']],
				['array', 'required', 'not_empty', 'when' => ['template_groups_new', 'empty']]
			],
			'description' => ['db hosts.description'],
			'tags' => ['objects', 'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db host_tag.value'],
					'tag' => ['db host_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
				]
			],
			'macros' => ['objects', 'uniq' => ['macro'],
				'messages' => ['uniq' => _('Macro name is not unique.')],
				'fields' => [
					'hostmacroid' => ['db hostmacro.hostmacroid'],
					'type' => ['db hostmacro.type', 'required', 'in' => [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET,
						ZBX_MACRO_TYPE_VAULT
					]],
					'value' => [
						['db hostmacro.value'],
						['db hostmacro.value', 'required', 'not_empty',
							'use' => [CVaultSecretParser::class, ['provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)]],
							'when' => ['type', 'in' => [ZBX_MACRO_TYPE_VAULT]]
						]
					],
					'description' => ['db hostmacro.description'],
					'macro' => [
						['db hostmacro.macro', 'use' => [CUserMacroParser::class, []], 'messages' => ['use' => _('Expected user macro format is "{$MACRO}".')]],
						['db hostmacro.macro', 'required', 'not_empty', 'when' => ['value', 'not_empty']],
						['db hostmacro.macro', 'required', 'not_empty', 'when' => ['description', 'not_empty']]
					],
					'automatic' => ['db hostmacro.automatic', 'in' => [ZBX_USERMACRO_MANUAL, ZBX_USERMACRO_AUTOMATIC]],
					'discovery_state' => ['integer'],
					'inherited_type' => ['integer']
				]
			],
			'valuemaps' => ['objects', 'fields' => [
				'valuemapid' => ['db valuemap.valuemapid'],
				'name' => ['db valuemap.name', 'not_empty', 'required'],
				'mappings' => ['objects', 'not_empty', 'uniq' => ['type', 'value'],
					'messages' => ['uniq' => _('Mapping type and value combination is not unique.')],
					'fields' => [
						'type' => ['db valuemap_mapping.type', 'required', 'in' => [VALUEMAP_MAPPING_TYPE_EQUAL,
							VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
							VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP, VALUEMAP_MAPPING_TYPE_DEFAULT
						]],
						'value' => [
							['db valuemap_mapping.value', 'required', 'when' => ['type', 'in' => [
								VALUEMAP_MAPPING_TYPE_EQUAL
							]]],
							['db valuemap_mapping.value', 'required', 'not_empty', 'when' => ['type', 'in' => [
								VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
								VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP
							]]],
							['float', 'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								VALUEMAP_MAPPING_TYPE_LESS_EQUAL
							]]],
							['string',
								'use' => [CRangesParser::class, ['with_minus' => true, 'with_float' => true, 'with_suffix' => true]],
								'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_IN_RANGE]],
								'messages' => ['use' => _('Invalid range.')]
							],
							['string', 'use' => [CRegexValidator::class, []],
								'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_REGEXP]]
							]
						],
						'newvalue' => ['db valuemap_mapping.newvalue', 'required', 'not_empty']
					]
				]
			]],
			'clone' => ['integer', 'in' => [1]],
			'clone_templateid' => ['db hosts.hostid']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add template'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}

		if ($this->hasInput('clone_templateid') && $this->hasInput('clone')) {
			$src_templates = API::Template()->get([
				'output' => ['templateid', 'readme'],
				'selectMacros' => ['macro', 'config'],
				'templateids' => $this->getInput('clone_templateid')
			]);

			if (!$src_templates) {
				return false;
			}

			$this->src_template = $src_templates[0];
		}

		return true;
	}

	protected function doAction(): void {
		try {
			DBstart();
			$template_name = $this->getInput('template_name', '');

			// Linked templates.
			$templates = [];

			foreach (array_merge($this->getInput('templates', []), $this->getInput('template_add_templates', [])) as $templateid) {
				$templates[] = ['templateid' => $templateid];
			}

			// Add new group.
			$groups = $this->getInput('template_groups', []);
			$new_groups = $this->getInput('template_groups_new', []);

			if ($new_groups) {
				$new_groupid = API::TemplateGroup()->create(array_map(
					static fn(string $name) => ['name' => $name],
					$new_groups
				));

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

			$src_macros = $this->src_template !== null
				? array_column($this->src_template['macros'], null, 'macro')
				: [];

			foreach ($macros as &$macro) {
				if (array_key_exists($macro['macro'], $src_macros)) {
					$macro['config'] = $src_macros[$macro['macro']]['config'];
				}

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
				'tags' => $tags,
				'macros' => $macros
			];

			if ($this->src_template !== null) {
				$template['readme'] = $this->src_template['readme'];
			}

			$result = API::Template()->create($template);

			if ($result === false) {
				throw new Exception();
			}

			$template = ['templateid' => $result['templateids'][0]] + $template;

			if (!$this->createValueMaps($template['templateid'], $this->getInput('valuemaps', []))
					|| ($this->hasInput('clone')
						&& !$this->copyFromCloneSourceTemplate($this->src_template['templateid'], $template))) {
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
