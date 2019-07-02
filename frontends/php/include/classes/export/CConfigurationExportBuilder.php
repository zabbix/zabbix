<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CConfigurationExportBuilder {

	/**
	 * @var array
	 */
	protected $data = [];

	protected $tag_map = [
		'groups' => 'CXmlTagGroup',
		'triggers' => 'CXmlTagTrigger',
		'templates' => 'CXmlTagTemplate',
		'hosts' => 'CXmlTagHost',
		'graphs' => 'CXmlTagGraph',
		// 'screens' => 'CXmlTagScreen',
		// 'images' => 'CXmlTag',
		// 'maps' => 'CXmlTag',
		'valueMaps' => 'CXmlTagValueMap',
	];

	/**
	 * @param $version  current export version
	 */
	public function __construct() {
		$this->data['version'] = ZABBIX_EXPORT_VERSION;
		$this->data['date'] = date(DATE_TIME_FORMAT_SECONDS_XML, time() - date('Z'));
	}

	/**
	 * Get array with formatted export data.
	 *
	 * @return array
	 */
	public function getExport() {
		return ['zabbix_export' => $this->data];
	}

	public function buildWrapper(array $data)
	{
		// Create triggers.
		$simple_triggers = [];

		if ($data['triggers']) {
			$simple_triggers = $this->createTriggers($data['triggers']);
		}

		foreach (['graphs', 'groups', 'hosts', /* 'images', 'maps', 'screens', */ 'templates', 'triggers', 'valueMaps'] as $tag) {
			if (!$data[$tag]) {
				continue;
			}

			$tag_class = new $this->tag_map[$tag];
			$xml_tag = $tag_class->getTag();
			$data[$tag] = $tag_class->prepareData($data[$tag]);
			$xml_schema = $tag_class->getSchema();
			$this->data[$xml_tag] = $this->build($xml_schema, $data[$tag], $simple_triggers);
		}
	}

	protected function build(array $xml_schema, array $data, $simple_triggers = null, $indexed = false)
	{
		$result = [];

		$n = 0;
		foreach ($data as $row) {
			foreach ($xml_schema as $field_key => $field_val) {

				$is_required = $field_val['type'] & CXmlDefine::REQUIRED;
				$is_array = $field_val['type'] & CXmlDefine::ARRAY;
				$is_indexed_array = $field_val['type'] & CXmlDefine::INDEXED_ARRAY;
				$data_key = array_key_exists('key', $field_val) ? $field_val['key'] : $field_key;
				$has_value = array_key_exists('value', $field_val);
				$has_data = isset($row[$data_key]);

				if (!$is_required && !$has_value && !$has_data) {
					continue;
				}

				if ($data_key == 'screenitems') {
					usleep(1);
				}

				if (!$has_data && $has_value) {
					$value = $field_val['value'];
				} else  {
					$value = $row[$data_key];
				}

				if (!$is_required && !$has_value && !$value) {
					continue;
				}

				if (!$is_required && $has_value && $field_val['value'] == $value) {
					continue;
				}

				if (($is_array || $is_indexed_array) && $has_data) {
					$temp_data = $this->build($field_val['schema'], $is_indexed_array ? [$row[$data_key]] : $row[$data_key], null, $is_indexed_array);
					if ($is_required || count($temp_data) > 0) {
						$result[$n][$field_key] = $temp_data;
					}
					continue;
				}

				if (array_key_exists('range', $field_val)) {
					if (is_callable($field_val['range'])) {
						$field_val['range'] = $field_val['range']($row);
					}

					if (!in_array($value, array_keys($field_val['range']))) {
						// FIXME: throw exception
						continue;
					}

					if ($indexed) {
						$result[$field_key] = $field_val['range'][$value];
					} else {
						$result[$n][$field_key] = $field_val['range'][$value];
					}
				} else {
					if ($indexed) {
						$result[$field_key] = $value;
					} else {
						$result[$n][$field_key] = $value;
					}
				}
			}

			$n++;
		}

		return $result;
	}

	public function createTriggers(array $triggers)
	{
		$simple_triggers = [];

		foreach ($triggers as $triggerid => $trigger) {
			if (count($trigger['items']) == 1 && $trigger['items'][0]['type'] != ITEM_TYPE_HTTPTEST
					&& $trigger['items'][0]['templateid'] == 0) {
				$simple_triggers[] = $trigger;
				unset($triggers[$triggerid]);
			}
		}

		return $simple_triggers;
	}

	/**
	 * Format screens.
	 *
	 * @param array $screens
	 */
	public function buildScreens(array $screens) {
		$this->data['screens'] = $this->formatScreens($screens);
	}

	/**
	 * Format images.
	 *
	 * @param array $images
	 */
	public function buildImages(array $images) {
		$this->data['images'] = [];

		foreach ($images as $image) {
			$this->data['images'][] = [
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['encodedImage']
			];
		}
	}

	/**
	 * Format maps.
	 *
	 * @param array $maps
	 */
	public function buildMaps(array $maps) {
		$this->data['maps'] = [];

		CArrayHelper::sort($maps, ['name']);

		foreach ($maps as $map) {
			$tmpSelements = $this->formatMapElements($map['selements']);
			$this->data['maps'][] = [
				'name' => $map['name'],
				'width' => $map['width'],
				'height' => $map['height'],
				'label_type' => $map['label_type'],
				'label_location' => $map['label_location'],
				'highlight' => $map['highlight'],
				'expandproblem' => $map['expandproblem'],
				'markelements' => $map['markelements'],
				'show_unack' => $map['show_unack'],
				'severity_min' => $map['severity_min'],
				'show_suppressed' => $map['show_suppressed'],
				'grid_size' => $map['grid_size'],
				'grid_show' => $map['grid_show'],
				'grid_align' => $map['grid_align'],
				'label_format' => $map['label_format'],
				'label_type_host' => $map['label_type_host'],
				'label_type_hostgroup' => $map['label_type_hostgroup'],
				'label_type_trigger' => $map['label_type_trigger'],
				'label_type_map' => $map['label_type_map'],
				'label_type_image' => $map['label_type_image'],
				'label_string_host' => $map['label_string_host'],
				'label_string_hostgroup' => $map['label_string_hostgroup'],
				'label_string_trigger' => $map['label_string_trigger'],
				'label_string_map' => $map['label_string_map'],
				'label_string_image' => $map['label_string_image'],
				'expand_macros' => $map['expand_macros'],
				'background' => $map['backgroundid'],
				'iconmap' => $map['iconmap'],
				'urls' => $this->formatMapUrls($map['urls']),
				'selements' => $tmpSelements,
				'shapes' => $map['shapes'],
				'lines' => $map['lines'],
				'links' => $this->formatMapLinks($map['links'], $tmpSelements)
			];
		}
	}

	/**
	 * Format screens.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	protected function formatScreens(array $screens) {
		$result = [];

		CArrayHelper::sort($screens, ['name']);

		foreach ($screens as $screen) {
			$result[] = [
				'name' => $screen['name'],
				'hsize' => $screen['hsize'],
				'vsize' => $screen['vsize'],
				'screen_items' => $this->formatScreenItems($screen['screenitems'])
			];
		}

		return $result;
	}

	/**
	 * Format screen items.
	 *
	 * @param array $screenItems
	 *
	 * @return array
	 */
	protected function formatScreenItems(array $screenItems) {
		$result = [];

		CArrayHelper::sort($screenItems, ['y', 'x']);

		foreach ($screenItems as $screenItem) {
			$result[] = [
				'resourcetype' => $screenItem['resourcetype'],
				'width' => $screenItem['width'],
				'height' => $screenItem['height'],
				'x' => $screenItem['x'],
				'y' => $screenItem['y'],
				'colspan' => $screenItem['colspan'],
				'rowspan' => $screenItem['rowspan'],
				'elements' => $screenItem['elements'],
				'valign' => $screenItem['valign'],
				'halign' => $screenItem['halign'],
				'style' => $screenItem['style'],
				'url' => $screenItem['url'],
				'dynamic' => $screenItem['dynamic'],
				'sort_triggers' => $screenItem['sort_triggers'],
				'resource' => $screenItem['resourceid'],
				'max_columns' => $screenItem['max_columns'],
				'application' => $screenItem['application']
			];
		}

		return $result;
	}

	/**
	 * Format map urls.
	 *
	 * @param array $urls
	 *
	 * @return array
	 */
	protected function formatMapUrls(array $urls) {
		$result = [];

		CArrayHelper::sort($urls, ['name', 'url']);

		foreach ($urls as $url) {
			$result[] = [
				'name' => $url['name'],
				'url' => $url['url'],
				'elementtype' => $url['elementtype']
			];
		}

		return $result;
	}

	/**
	 * Format map element urls.
	 *
	 * @param array $urls
	 *
	 * @return array
	 */
	protected function formatMapElementUrls(array $urls) {
		$result = [];

		CArrayHelper::sort($urls, ['name', 'url']);

		foreach ($urls as $url) {
			$result[] = [
				'name' => $url['name'],
				'url' => $url['url']
			];
		}

		return $result;
	}

	/**
	 * Format map links.
	 *
	 * @param array $links			Map links
	 * @param array $selements		Map elements
	 *
	 * @return array
	 */
	protected function formatMapLinks(array $links, array $selements) {
		$result = [];

		// Get array where key is selementid and value is sort position.
		$flipped_selements = [];
		$selements = array_values($selements);

		foreach ($selements as $key => $item) {
			if (array_key_exists('selementid', $item)) {
				$flipped_selements[$item['selementid']] = $key;
			}
		}

		foreach ($links as &$link) {
			$link['selementpos1'] = $flipped_selements[$link['selementid1']];
			$link['selementpos2'] = $flipped_selements[$link['selementid2']];

			// Sort selements positons asc.
			if ($link['selementpos2'] < $link['selementpos1']) {
				zbx_swap($link['selementpos1'], $link['selementpos2']);
			}
		}
		unset($link);

		CArrayHelper::sort($links, ['selementpos1', 'selementpos2']);

		foreach ($links as $link) {
			$result[] = [
				'drawtype' => $link['drawtype'],
				'color' => $link['color'],
				'label' => $link['label'],
				'selementid1' => $link['selementid1'],
				'selementid2' => $link['selementid2'],
				'linktriggers' => $this->formatMapLinkTriggers($link['linktriggers'])
			];
		}

		return $result;
	}

	/**
	 * Format map link triggers.
	 *
	 * @param array $linktriggers
	 *
	 * @return array
	 */
	protected function formatMapLinkTriggers(array $linktriggers) {
		$result = [];

		foreach ($linktriggers as &$linktrigger) {
			$linktrigger['description'] = $linktrigger['triggerid']['description'];
			$linktrigger['expression'] = $linktrigger['triggerid']['expression'];
			$linktrigger['recovery_expression'] = $linktrigger['triggerid']['recovery_expression'];
		}
		unset($linktrigger);

		CArrayHelper::sort($linktriggers, ['description', 'expression', 'recovery_expression']);

		foreach ($linktriggers as $linktrigger) {
			$result[] = [
				'drawtype' => $linktrigger['drawtype'],
				'color' => $linktrigger['color'],
				'trigger' => $linktrigger['triggerid']
			];
		}

		return $result;
	}

	/**
	 * Format map elements.
	 *
	 * @param array $elements
	 *
	 * @return array
	 */
	protected function formatMapElements(array $elements) {
		$result = [];

		CArrayHelper::sort($elements, ['y', 'x']);

		foreach ($elements as $element) {
			$result[] = [
				'elementtype' => $element['elementtype'],
				'label' => $element['label'],
				'label_location' => $element['label_location'],
				'x' => $element['x'],
				'y' => $element['y'],
				'elementsubtype' => $element['elementsubtype'],
				'areatype' => $element['areatype'],
				'width' => $element['width'],
				'height' => $element['height'],
				'viewtype' => $element['viewtype'],
				'use_iconmap' => $element['use_iconmap'],
				'selementid' => $element['selementid'],
				'elements' => $element['elements'],
				'icon_off' => $element['iconid_off'],
				'icon_on' => $element['iconid_on'],
				'icon_disabled' => $element['iconid_disabled'],
				'icon_maintenance' => $element['iconid_maintenance'],
				'application' => $element['application'],
				'urls' => $this->formatMapElementUrls($element['urls'])
			];
		}

		return $result;
	}
}
