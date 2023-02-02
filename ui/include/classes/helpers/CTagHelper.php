<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


final class CTagHelper {

	/**
	 * Add the inherited tags to the given tags.
	 *
	 * @param array      $tags
	 * @param string     $context
	 * @param array      $hostids
	 * @param array      $parent_object
	 */
	public static function addInheritedTags(array &$tags, string $context, array $hostids, array $parent_object): void {
		$inherited_tags = [];

		if (array_key_exists('template_names', $parent_object)
				&& !array_key_exists(0, $parent_object['template_names'])) {
			$db_templates = API::Template()->get([
				'output' => [],
				'selectTags' => ['tag', 'value'],
				'templateids' => array_keys($parent_object['template_names']),
				'preservekeys' => true
			]);

			foreach ($db_templates as $templateid => $template) {
				foreach ($template['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $inherited_tags)
							&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
						$inherited_tags[$tag['tag']][$tag['value']]['templateids'][] = $templateid;
					}
					else {
						$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
							'templateids' => [$templateid],
							'parent_object' => $parent_object,
							'type' => ZBX_PROPERTY_INHERITED
						];
					}
				}
			}
		}
		elseif (array_key_exists('templateid', $parent_object)) {
			$db_templates = API::Template()->get([
				'output' => ['name'],
				'selectTags' => ['tag', 'value'],
				'templateids' => $parent_object['templateid']
			]);

			$parent_object['template_names'] = [$parent_object['templateid'] => $parent_object['template_name']];
			unset($parent_object['templateid'], $parent_object['template_name']);

			foreach ($db_templates[0]['tags'] as $tag) {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
					'parent_object' => $parent_object,
					'type' => ZBX_PROPERTY_INHERITED
				];
			}
		}

		if ($context === 'template') {
			$db_hosts = API::Template()->get([
				'output' => [],
				'selectTags' => ['tag', 'value'],
				'templateids' => $hostids
			]);
		}
		else {
			$db_hosts = API::Host()->get([
				'output' => [],
				'selectTags' => ['tag', 'value'],
				'hostids' => $hostids
			]);
		}

		foreach ($db_hosts as $db_host) {
			foreach ($db_host['tags'] as $tag) {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_INHERITED];
			}
		}

		foreach ($tags as &$tag) {
			if (array_key_exists($tag['tag'], $inherited_tags)
					&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
				$tag = $inherited_tags[$tag['tag']][$tag['value']];
				$tag['type'] = ZBX_PROPERTY_BOTH;
				unset($inherited_tags[$tag['tag']][$tag['value']]);
			}
			else {
				$tag['type'] = ZBX_PROPERTY_OWN;
			}
		}
		unset($tag);

		foreach ($inherited_tags as $value_tag) {
			foreach ($value_tag as $tag) {
				$tags[] = $tag;
			}
		}
	}
}
