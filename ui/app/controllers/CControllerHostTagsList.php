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

	private ?array $host = null;
	private ?array $host_prototype = null;

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setPostContentType(static::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'source' =>					'required|string|in '.implode(',', ['template', 'host', 'host_prototype']),
			'hostid' =>					'db hosts.hostid',
			'templateids' =>			'array',
			'show_inherited_tags' =>	'in 0,1',
			'tags' =>					'array'
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
		if ($this->hasInput('hostid')) {
			$hostid = $this->getInput('hostid', 0);

			if ($hostid == 0) {
				return false;
			}

			$db_hosts = API::Host()->get([
				'output' => ['flags'],
				'hostids' => [$hostid]
			]);

			if ($db_hosts) {
				$this->host = $db_hosts[0];

				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
			}

			$db_host_prototypes = API::HostPrototype()->get([
				'output' => ['templateid', 'flags'],
				'hostids' => [$hostid]
			]);

			if (!$db_host_prototypes) {
				return false;
			}

			$this->host_prototype = $db_host_prototypes[0];
		}

		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction(): void {
		$data = [
			'show_inherited_tags' => 0,
			'tags' => [],
			'has_inline_validation' => true,
			'with_automatic' => $this->host !== null && $this->host['flags'] & ZBX_FLAG_DISCOVERY_CREATED,
			'readonly' => $this->host_prototype !== null
				&& ($this->host_prototype['templateid'] != 0
					|| $this->host_prototype['flags'] & ZBX_FLAG_DISCOVERY_CREATED)
		];
		$this->getInputs($data, ['source', 'show_inherited_tags', 'tags']);

		$data['tags'] = array_filter($data['tags'], static fn (array $tag) =>
			$tag['tag'] !== '' || $tag['value'] !== ''
		);

		if ($data['show_inherited_tags']) {
			$tag_details = self::getTagDetails($data['tags']);
			$inherited_tag_details = self::getInheritedTagDetails($this->getInput('templateids', []));

			if ($tag_details['tag_value_pairs'] || $inherited_tag_details['tag_value_pairs']) {
				$data['tags'] = self::getMergedOwnAndInheritedTags($tag_details, $inherited_tag_details);

				CArrayHelper::sort($data['tags'], ['tag', 'value']);
			}
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}

	private static function getTagDetails(array $tags): array {
		$tag_details = [
			'tag_value_pairs' => [],
			'automatics' => []
		];

		foreach ($tags as $tag) {
			$tag += ['automatic' => ZBX_TAG_MANUAL];

			$tag_value_pair = [
				'tag' => $tag['tag'],
				'value' => $tag['value']
			];

			$tag_index = array_search($tag_value_pair, $tag_details['tag_value_pairs'], true);

			if ($tag_index !== false && $tag['automatic'] == ZBX_TAG_MANUAL) {
				continue;
			}

			$tag_details['tag_value_pairs'][] = $tag_value_pair;
			$tag_details['automatics'][] = $tag['automatic'];
		}

		return $tag_details;
	}

	private static function getInheritedTagDetails(array $templateids): array {
		$tag_details = [
			'tag_value_pairs' => [],
			'tag_templates' => []
		];

		if (!$templateids) {
			return $tag_details;
		}

		$templates = API::Template()->get([
			'output' => [],
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value', 'objectid'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		if (!$templates) {
			return $tag_details;
		}

		$templateids = array_intersect_key(array_flip($templateids), $templates);

		$tag_templates_by_templateid = [];

		foreach ($templates as $templateid => $template) {
			foreach ($template['tags'] as $tag_value_pair) {
				$tag_index = array_search($tag_value_pair, $tag_details['tag_value_pairs'], true);

				if ($tag_index !== false) {
					$tag_details['tag_templates'][$tag_index][$templateid] = [];
				}
				else {
					$tag_details['tag_value_pairs'][] = $tag_value_pair;
					$tag_details['tag_templates'][] = [$templateid => []];

					$tag_index = array_key_last($tag_details['tag_templates']);
				}

				$tag_templates_by_templateid[$templateid][] = &$tag_details['tag_templates'][$tag_index][$templateid];
			}

			foreach ($template['inheritedTags'] as $tag) {
				$tag_value_pair = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];

				$tag_index = array_search($tag_value_pair, $tag_details['tag_value_pairs'], true);

				if ($tag_index !== false) {
					$tag_details['tag_templates'][$tag_index][$tag['objectid']] = [];
				}
				else {
					$tag_details['tag_value_pairs'][] = $tag_value_pair;
					$tag_details['tag_templates'][] = [$tag['objectid'] => []];

					$tag_index = array_key_last($tag_details['tag_templates']);
				}

				$tag_templates_by_templateid[$tag['objectid']][] =
					&$tag_details['tag_templates'][$tag_index][$tag['objectid']];

				$templateids[$tag['objectid']] = true;
			}
		}

		$templates = self::getTemplates($templateids);

		foreach ($tag_templates_by_templateid as $templateid => $tag_templates) {
			foreach ($tag_templates as &$tag_template) {
				$tag_template = $templates[$templateid];
			}
			unset($tag_template);
		}

		return $tag_details;
	}

	private static function getTemplates(array $templateids): array {
		$accessible_templates = API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($templateids),
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

		$templates = [];

		foreach ($templateids as $templateid => $true) {
			$templates[$templateid] = array_key_exists($templateid, $accessible_templates)
				? [
					'name' => $accessible_templates[$templateid]['name'],
					'permission' => array_key_exists($templateid, $editable_templates) ? PERM_READ_WRITE : PERM_READ
				]
				: [
					'name' => _('Inaccessible template'),
					'permission' => PERM_DENY
				];
		}

		return $templates;
	}

	private static function getMergedOwnAndInheritedTags(array $tag_details, array $inherited_tag_details): array {
		$tags = [];

		foreach ($tag_details['tag_value_pairs'] as $tag_index => $tag_value_pair) {
			$inherited_tag_index = array_search($tag_value_pair, $inherited_tag_details['tag_value_pairs'], true);

			$tag = [
				'tag' => $tag_value_pair['tag'],
				'value' => $tag_value_pair['value'],
				'automatic' => $tag_details['automatics'][$tag_index],
				'type' => ZBX_PROPERTY_OWN
			];

			if ($inherited_tag_index !== false) {
				$tag['type'] = ZBX_PROPERTY_BOTH;
				$tag['parent_templates'] = $inherited_tag_details['tag_templates'][$inherited_tag_index];

				unset($inherited_tag_details['tag_value_pairs'][$tag_index]);
				unset($inherited_tag_details['tag_templates'][$tag_index]);
			}

			$tags[] = $tag;
		}

		foreach ($inherited_tag_details['tag_value_pairs'] as $tag_index => $tag_value_pair) {
			$tags[] = [
				'tag' => $tag_value_pair['tag'],
				'value' => $tag_value_pair['value'],
				'type' => ZBX_PROPERTY_INHERITED,
				'parent_templates' => $inherited_tag_details['tag_templates'][$tag_index]
			];
		}

		return $tags;
	}
}
