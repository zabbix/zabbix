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


require 'include/forms.inc.php';

class CControllerHostTagsList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setPostContentType(static::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'source' =>					'string|in '.implode(',', ['template', 'host', 'host_prototype']),
			'hostid' =>					'db hosts.hostid',
			'parent_discoveryid' =>		'db items.itemid',
			'templateids' =>			'array',
			'show_inherited_tags' =>	'in 0,1',
			'tags' =>					'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret && !array_intersect_key($this->getInputAll(), array_flip(['source', 'hostid', 'parent_discoveryid']))) {
			error(_('Incorrect input parameters.'));

			$ret = false;
		}

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
		if ($this->hasInput('parent_discoveryid')) {
			$db_lld_rules = API::DiscoveryRule()->get([
				'output' => [],
				'selectHosts' => ['status'],
				'itemids' => $this->getInput('parent_discoveryid'),
				'editable' => true
			]);

			return $db_lld_rules
				&& (($db_lld_rules[0]['hosts'][0]['status'] == HOST_STATUS_TEMPLATE
						&& $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES))
					|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS));
		}

		if ($this->hasInput('hostid')) {
			$hostid = $this->getInput('hostid');

			$db_templates = API::Template()->get([
				'output' => [],
				'templateids' => [$hostid]
			]);

			if ($db_templates) {
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
			}

			$db_hosts = API::Host()->get([
				'output' => [],
				'hostids' => [$hostid]
			]);

			if ($db_hosts) {
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
			}

			$db_host_prototypes = API::HostPrototype()->get([
				'output' => [],
				'selectParentHost' => ['status'],
				'hostids' => [$hostid]
			]);

			return $db_host_prototypes
				&& (($db_host_prototypes[0]['parentHost']['status'] == HOST_STATUS_TEMPLATE
						&& $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES))
					|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS));
		}

		return match ($this->getInput('source')) {
			'template' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'host' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
			'host_prototype' => false
		};
	}

	protected function doAction(): void {
		$data = [
			'hostid' => 0,
			'show_inherited_tags' => 0,
			'tags' => [],
			'has_inline_validation' => true
		];
		$this->getInputs($data, ['source', 'hostid', 'show_inherited_tags', 'tags']);

		$data['tags'] = array_filter($data['tags'], static fn (array $tag) =>
			$tag['tag'] !== '' || $tag['value'] !== ''
		);

		if ($data['show_inherited_tags']) {
			$tag_values = $data['tags'] ? self::getTagValues($data['tags']) : [];

			$templateids = $this->getInput('templateids', []);
			$inherited_tag_templates = $templateids ? $this->getInheritedTagTemplates($templateids) : [];

			if ($tag_values || $inherited_tag_templates) {
				$data['tags'] = self::getMergedOwnAndInheritedTags($tag_values, $inherited_tag_templates);

				CArrayHelper::sort($data['tags'], ['tag', 'value']);
			}
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}

	private static function getTagValues(array $tags): array {
		$tag_values = [];

		foreach ($tags as $tag) {
			$tag_values[$tag['tag']][$tag['value']] = true;
		}

		return $tag_values;
	}

	private function getInheritedTagTemplates(array $templateids): array {
		$inherited_tag_templates = [];

		self::addTemplateTags($inherited_tag_templates, $templateids);
		self::addTemplateData($inherited_tag_templates);

		return $inherited_tag_templates;
	}

	private static function addTemplateTags(array &$inherited_tag_templates, array $templateids): void {
		$templates = API::Template()->get([
			'output' => [],
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value', 'objectid'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		foreach ($templates as $templateid => $template) {
			foreach ($template['tags'] as $tag) {
				$inherited_tag_templates[$tag['tag']][$tag['value']][$templateid] = [];
			}

			foreach ($template['inheritedTags'] as $tag) {
				$inherited_tag_templates[$tag['tag']][$tag['value']][$tag['objectid']] = [];
			}
		}
	}

	private static function addTemplateData(array &$inherited_tag_templates): void {
		$tag_templates_by_templateid = [];

		foreach ($inherited_tag_templates as &$value_templates) {
			foreach ($value_templates as &$_templates) {
				foreach ($_templates as $templateid => &$template) {
					$tag_templates_by_templateid[$templateid][] = &$template;
				}
				unset($template);
			}
			unset($_templates);
		}
		unset($value_templates);

		$accessible_templates = API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($tag_templates_by_templateid),
			'preservekeys' => true
		]);

		$editable_templates = $accessible_templates
			? API::Template()->get([
				'output' => [],
				'templateids' => array_keys($accessible_templates),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		foreach ($tag_templates_by_templateid as $templateid => &$tag_templates) {
			$template_data = array_key_exists($templateid, $accessible_templates)
				? [
					'name' => $accessible_templates[$templateid]['name'],
					'permission' => array_key_exists($templateid, $editable_templates) ? PERM_READ_WRITE : PERM_READ
				]
				: [
					'name' => _('Inaccessible template'),
					'permission' => PERM_DENY
				];

			foreach ($tag_templates as &$template) {
				$template = $template_data;
			}
			unset($template);
		}
		unset($tag_templates);
	}

	private static function getMergedOwnAndInheritedTags(array $tag_values, array $inherited_tag_templates): array {
		$tags = [];

		foreach ($tag_values as $tag => $values) {
			foreach ($values as $value => $true) {
				$inherited_tags_exists = array_key_exists($tag, $inherited_tag_templates)
					&& array_key_exists($value, $inherited_tag_templates[$tag]);

				$tags[] = [
					'tag' => $tag,
					'value' => $value,
					'type' => $inherited_tags_exists ? ZBX_PROPERTY_BOTH : ZBX_PROPERTY_OWN
				] + ($inherited_tags_exists ? ['parent_templates' => $inherited_tag_templates[$tag][$value]] : []);

				unset($inherited_tag_templates[$tag][$value]);
			}
		}

		foreach ($inherited_tag_templates as $tag => $value_templates) {
			foreach ($value_templates as $value => $templates) {
				$tags[] = [
					'tag' => $tag,
					'value' => $value,
					'type' => ZBX_PROPERTY_INHERITED,
					'parent_templates' => $templates
				];
			}
		}

		return $tags;
	}
}
