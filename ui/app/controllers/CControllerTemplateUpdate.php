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
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['template.get', ['host' => '{template_name}'], 'templateid'],
			['template.get', ['name' => '{visiblename}'], 'templateid'],
			['template.get', ['name' => '{template_name}'], 'templateid'],
			['host.get', ['host' => '{template_name}']],
			['host.get', ['name' => '{visiblename}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'templateid' => ['db hosts.hostid', 'required'],
			'template_name' => ['db hosts.host', 'required', 'not_empty', 'regex' => '/^'.ZBX_PREG_HOST_FORMAT.'$/',
				'messages' => ['regex' => _('Incorrect characters used for template name.')]
			],
			'visiblename' => ['db hosts.host'],
			'templates' => ['array', 'field' => ['db hosts.hostid']],
			'template_add_templates' => ['array', 'field' => ['db hosts.hostid']],
			'clear_templates' => ['array', 'field' => ['db hosts.hostid']],
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
			]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update template'),
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

		return true;
	}

	protected function doAction(): void {
		$templateid = $this->getInput('templateid');
		$template_name = $this->getInput('template_name', '');
		$tags = $this->getInput('tags', []);

		// Linked templates.
		$templates = [];

		foreach (array_merge($this->getInput('templates', []), $this->getInput('template_add_templates', [])) as $linked_id) {
			$templates[] = ['templateid' => $linked_id];
		}

		// Clear templates.
		$templates_clear = array_diff(
			$this->getInput('clear_templates', []),
			$this->getInput('template_add_templates', [])
		);

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
