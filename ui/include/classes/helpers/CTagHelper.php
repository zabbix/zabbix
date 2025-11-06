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


class CTagHelper {

	public static function mergeOwnAndInheritedTags(array &$objects, bool $add_tag_type = false): void {
		foreach ($objects as &$object) {
			self::mergeOwnAndInheritedTagsForObject($object, $add_tag_type);
		}
		unset($object);
	}

	public static function mergeOwnAndInheritedTagsForObject(array &$object, bool $add_tag_type = false): void {
		$tag_types = [];

		foreach ($object['inheritedTags'] as $tag) {
			$tag_types[$tag['tag']][$tag['value']] = ZBX_PROPERTY_INHERITED;
		}

		unset($object['inheritedTags']);

		foreach ($object['tags'] as $tag) {
			if (array_key_exists($tag['tag'], $tag_types) && array_key_exists($tag['value'], $tag_types[$tag['tag']])) {
				$tag_types[$tag['tag']][$tag['value']] = ZBX_PROPERTY_BOTH;
			}
			else {
				$tag_types[$tag['tag']][$tag['value']] = ZBX_PROPERTY_OWN;
			}
		}

		$object['tags'] = [];

		foreach ($tag_types as $tag => $values) {
			foreach ($values as $value => $type) {
				$object['tags'][] = [
					'tag' => $tag,
					'value' => $value
				] + ($add_tag_type ? ['type' => $type] : []);
			}
		}
	}

	public static function getTagsHtml(array $objects, int $object_type, array $options = []): array {
		$options += [
			'filter_tags' => [],
			'tag_priority' => '',
			'show_tags_limit' => ZBX_TAG_COUNT_DEFAULT,
			'tag_name_format' => TAG_NAME_FULL,
			'subfilter_tags' => null
		];

		if ($options['show_tags_limit'] == SHOW_TAGS_NONE) {
			return [];
		}

		$id_field_name = self::getIdFieldName($object_type);
		$filter_tags = self::getFilterTagsIndexedByTag($options['filter_tags']);
		$priority_tags = self::getPriorityTags($options['tag_priority']);

		$html_elements = [];

		foreach ($objects as $object) {
			$objectid = $object[$id_field_name];
			$html_elements[$objectid] = [];

			$show_tags_count = 0;

			CArrayHelper::sort($object['tags'], ['tag', 'value']);

			foreach (self::getReorderedTags($object['tags'], $filter_tags, $priority_tags) as $tag) {
				if (self::getTagString($tag, $options['tag_name_format']) === '') {
					continue;
				}

				$html_elements[$objectid][] = self::getTagHtml($tag, $object_type, $options);

				$show_tags_count++;

				if ($show_tags_count == $options['show_tags_limit']) {
					break;
				}
			}

			if (count($object['tags']) <= $show_tags_count) {
				continue;
			}

			$all_tag_html_elements = [];

			foreach ($object['tags'] as $tag) {
				$all_tag_html_elements[] =
					self::getTagHtml($tag, $object_type, ['tag_name_format' => TAG_NAME_FULL] + $options);
			}

			$html_elements[$objectid][] = (new CButtonIcon(ZBX_ICON_MORE))
				->setHint($all_tag_html_elements, ZBX_STYLE_HINTBOX_WRAP.' '.ZBX_STYLE_TAGS_WRAPPER);
		}

		return $html_elements;
	}

	private static function getIdFieldName(int $object_type): string {
		return match($object_type) {
			ZBX_TAG_OBJECT_TEMPLATE => 'templateid',
			ZBX_TAG_OBJECT_HOST, ZBX_TAG_OBJECT_HOST_PROTOTYPE => 'hostid',
			ZBX_TAG_OBJECT_ITEM, ZBX_TAG_OBJECT_ITEM_PROTOTYPE => 'itemid',
			ZBX_TAG_OBJECT_TRIGGER, ZBX_TAG_OBJECT_TRIGGER_PROTOTYPE => 'triggerid',
			ZBX_TAG_OBJECT_HTTPTEST => 'httptestid',
			ZBX_TAG_OBJECT_EVENT, ZBX_TAG_OBJECT_PROBLEM => 'eventid',
			ZBX_TAG_OBJECT_SERVICE  => 'serviceid',
			ZBX_TAG_OBJECT_HOST_GROUP => 'groupid'
		};
	}

	private static function getFilterTagsIndexedByTag(array $filter_tags): array {
		$filter_tags_by_tag = [];

		foreach ($filter_tags as $filter_tag) {
			$filter_tags_by_tag[$filter_tag['tag']][] = $filter_tag;
		}

		return $filter_tags_by_tag;
	}

	private static function getPriorityTags(string $tag_priority): array {
		if ($tag_priority === '') {
			return [];
		}

		return array_map('trim', explode(',', $tag_priority));
	}

	private static function getReorderedTags(array $tags, array $filter_tags, array $priority_tags): array {
		if ($filter_tags) {
			self::orderByFilterTagsFirst($tags, $filter_tags);
		}

		if ($priority_tags) {
			self::orderByPriorityTagsFirst($tags, $priority_tags);
		}

		return $tags;
	}

	private static function orderByFilterTagsFirst(array &$tags, array $filter_tags): void {
		$first_tags = [];

		foreach ($tags as $tag_i => $tag) {
			if (!array_key_exists($tag['tag'], $filter_tags)) {
				continue;
			}

			foreach ($filter_tags[$tag['tag']] as $filter_tag) {
				if (($filter_tag['operator'] == TAG_OPERATOR_LIKE
						&& ($filter_tag['value'] === '' || stripos($tag['value'], $filter_tag['value']) !== false))
						|| ($filter_tag['operator'] == TAG_OPERATOR_EQUAL && $tag['value'] === $filter_tag['value'])) {
					$first_tags[] = $tag;
					unset($tags[$tag_i]);
					break;
				}
			}
		}

		$tags = array_merge($first_tags, $tags);
	}

	private static function orderByPriorityTagsFirst(array &$tags, array $priority_tags): void {
		$first_tags = [];

		foreach ($tags as $i => $tag) {
			if (in_array($tag['tag'], $priority_tags)) {
				$first_tags[] = $tag;
				unset($tags[$i]);
			}
		}

		$tags = array_merge($first_tags, $tags);
	}

	private static function getTagHtml(array $tag, int $object_type, array $options): CTag {
		$tag_string = self::getTagString($tag, $options['tag_name_format']);

		if ($options['subfilter_tags'] !== null
				&& (!array_key_exists($tag['tag'], $options['subfilter_tags'])
					|| !array_key_exists($tag['value'], $options['subfilter_tags'][$tag['tag']]))) {
			$html_element = (new CSimpleButton($tag_string))
				->setAttribute('data-key', $tag['tag'])
				->setAttribute('data-value', $tag['value'])
				->onClick(
					'view.setSubfilter([`subfilter_tags[${encodeURIComponent(this.dataset.key)}][]`,'.
						'this.dataset.value'.
					']);'
				)
				->addClass(ZBX_STYLE_BTN_TAG)
				->addClass(ZBX_STYLE_TAG);

			$freeze_on_click = false;
		}
		else {
			$html_element = (new CSpan($tag_string))->addClass(ZBX_STYLE_TAG);

			$freeze_on_click = true;
		}

		if (array_key_exists('type', $tag) && $tag['type'] == ZBX_PROPERTY_INHERITED) {
			return $html_element
				->addClass(ZBX_STYLE_TAG_INHERITED)
				->setHint(new CDiv([
					(new CDiv(_('Inherited tag')))->addClass(ZBX_STYLE_TAG_INHERITED_TITLE),
					self::getTagString($tag)
				]), '', $freeze_on_click);
		}

		if (array_key_exists('type', $tag) && $tag['type'] == ZBX_PROPERTY_BOTH) {
			$hint_title = match ($object_type) {
				ZBX_TAG_OBJECT_TEMPLATE => _('Inherited and template tag'),
				ZBX_TAG_OBJECT_HOST, ZBX_TAG_OBJECT_HOST_PROTOTYPE => _('Inherited and host tag'),
				ZBX_TAG_OBJECT_ITEM, ZBX_TAG_OBJECT_ITEM_PROTOTYPE => _('Inherited and item tag'),
				ZBX_TAG_OBJECT_TRIGGER, ZBX_TAG_OBJECT_TRIGGER_PROTOTYPE => _('Inherited and trigger tag'),
				ZBX_TAG_OBJECT_HTTPTEST => _('Inherited and web scenario tag')
			};

			return $html_element
				->addClass(ZBX_STYLE_TAG_INHERITED_DUPLICATE)
				->setHint(new CDiv([
					(new CDiv(_($hint_title)))->addClass(ZBX_STYLE_TAG_INHERITED_TITLE),
					self::getTagString($tag)
				]));
		}

		return $html_element->setHint(self::getTagString($tag), '', $freeze_on_click);
	}

	/**
	 * Returns tag name in selected format.
	 *
	 * @param array  $tag
	 * @param string $tag['tag']
	 * @param string $tag['value']
	 * @param int    $tag_name_format  TAG_NAME_*
	 *
	 * @return string
	 */
	public static function getTagString(array $tag, int $tag_name_format = TAG_NAME_FULL) {
		switch ($tag_name_format) {
			case TAG_NAME_NONE:
				return $tag['value'];

			case TAG_NAME_SHORTENED:
				return mb_substr($tag['tag'], 0, 3).(($tag['value'] === '') ? '' : ': '.$tag['value']);

			default:
				return $tag['tag'].(($tag['value'] === '') ? '' : ': '.$tag['value']);
		}
	}

	public static function getTagsRaw(array $objects): array {
		$tags = [];

		foreach ($objects as $objectid => $object) {
			$tags[$objectid] = [];

			if (!$object['tags']) {
				continue;
			}

			foreach ($object['tags'] as $tag) {
				$tags[$objectid][] = self::getTagString($tag);
			}
		}

		return $tags;
	}
}
