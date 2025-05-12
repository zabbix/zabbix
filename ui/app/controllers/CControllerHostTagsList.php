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

	private ?array $host;

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
		if ($this->getInput('hostid', 0) == 0) {
			return true;
		}

		switch ($this->getInput('source')) {
			case 'host':
				$api = API::Host();
				$options = [
					'output' => ['hostid'],
					'selectParentTemplates' => ['templateid'],
					'hostids' => [$this->getInput('hostid')]
				];
				break;

			case 'host_prototype':
				$api = API::HostPrototype();
				$options = [
					'output' => ['hostid'],
					'selectTemplates' => ['templateid'],
					'hostids' => [$this->getInput('hostid')]
				];
				break;

			case 'template':
				$api = API::Template();
				$options = [
					'output' => ['templateid'],
					'selectParentTemplates' => ['templateid'],
					'templateids' => [$this->getInput('hostid')]
				];
				break;
		}

		if ($this->getInput('show_inherited_tags', 0)) {
			$options['selectInheritedTags'] = ['tag', 'value', 'objectid'];
		}

		$db_hosts = $api->get($options);

		if (!$db_hosts) {
			return false;
		}

		$this->host = $db_hosts[0];

		return true;
	}

	protected function doAction(): void {
		$data = [
			'hostid' => 0,
			'show_inherited_tags' => 0,
			'tags' => []
		];
		$this->getInputs($data, ['source', 'hostid', 'show_inherited_tags', 'tags']);

		$data['tags'] = array_filter($data['tags'], static fn (array $tag) =>
			$tag['tag'] !== '' || $tag['value'] !== ''
		);

		if ($data['show_inherited_tags']) {
			$templateids = $this->getInput('templateids', []);

			if ($data['hostid'] != 0) {
				$linked_templateids = $data['source'] === 'host_prototype'
					? array_column($this->host['templates'], 'templateid')
					: array_column($this->host['parentTemplates'], 'templateid');

				$link_templateids = array_diff($templateids, $linked_templateids);
				$unlink_templateids = array_diff($linked_templateids, $templateids);
				$unlink_templateids = array_flip(
					array_column(self::getAllTemplateTags($unlink_templateids), 'objectid')
				);

				$inherited_tags = [];

				foreach ($this->host['inheritedTags'] as $tag) {
					if (!array_key_exists($tag['objectid'], $unlink_templateids)) {
						$inherited_tags[] = $tag;
					}
				}

				$inherited_tags = array_merge($inherited_tags, self::getAllTemplateTags($link_templateids));
			}
			else {
				$inherited_tags = self::getAllTemplateTags($templateids);
			}

			$data['tags'] = self::mergeHostAndInheritedTags($data['tags'], $inherited_tags);
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}

	private static function getAllTemplateTags(array $templateids): array {
		if (!$templateids) {
			return [];
		}

		$db_templates = API::Template()->get([
			'output' => [],
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value', 'objectid'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		$tags = [];

		foreach ($db_templates as $templateid => $template) {
			foreach ($template['tags'] as $tag) {
				$tag['objectid'] = $templateid;

				$tags[] = $tag;
			}

			foreach ($template['inheritedTags'] as $tag) {
				$tags[] = $tag;
			}
		}

		return $tags;
	}

	private static function mergeHostAndInheritedTags(array $host_tags, array $inherited_tags): array {
		$unique_tags = [];
		$parent_templates = [];

		foreach ($inherited_tags as $tag) {
			if (array_key_exists($tag['tag'], $unique_tags)
					&& array_key_exists($tag['value'], $unique_tags[$tag['tag']])) {
				$unique_tags[$tag['tag']][$tag['value']]['parent_templates'][$tag['objectid']] = [];
			}
			else {
				$unique_tags[$tag['tag']][$tag['value']] = $tag + [
					'type' => ZBX_PROPERTY_INHERITED,
					'parent_templates' => [
						$tag['objectid'] => []
					]
				];
			}

			$parent_templates[$tag['objectid']] = [];
		}

		$db_templates = $parent_templates
			? API::Template()->get([
				'output' => ['name'],
				'templateids' => array_keys($parent_templates),
				'preservekeys' => true
			])
			: [];

		$rw_templates = $db_templates
			? API::Template()->get([
				'output' => [],
				'templateids' => array_keys($db_templates),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		foreach ($parent_templates as $templateid => &$template) {
			$template = array_key_exists($templateid, $db_templates)
				? [
					'hostid' => $templateid,
					'name' => $db_templates[$templateid]['name'],
					'permission' => array_key_exists($templateid, $rw_templates) ? PERM_READ_WRITE : PERM_READ
				]
				: [
					'hostid' => $templateid,
					'name' => _('Inaccessible template'),
					'permission' => PERM_DENY
				];
		}
		unset($template);

		foreach ($host_tags as $tag) {
			if (array_key_exists($tag['tag'], $unique_tags)
					&& array_key_exists($tag['value'], $unique_tags[$tag['tag']])) {
				$unique_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
			}
			else {
				$unique_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
			}
		}

		$tags = [];

		foreach ($unique_tags as $tags_by_value) {
			foreach ($tags_by_value as $tag) {
				if (array_key_exists('parent_templates', $tag)) {
					foreach ($tag['parent_templates'] as $templateid => &$template) {
						$template = $parent_templates[$templateid];
					}
					unset($template, $tag['objectid']);
				}

				$tags[] = $tag;
			}
		}

		if ($tags) {
			CArrayHelper::sort($tags, ['tag', 'value']);
		}

		return $tags;
	}
}
