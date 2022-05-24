<?php
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


class CMultiSelect extends CTag {
	/**
	 * Default CSS class name for HTML root element.
	 */
	const ZBX_STYLE_CLASS = 'multiselect-control';

	/**
	 * Search method used for autocomplete requests.
	 */
	const SEARCH_METHOD = 'multiselect.get';

	/**
	 * Supported preselect types.
	 *
	 * @param array
	 */
	protected $preselect_fields = ['hosts', 'hostgroups', 'templategroups'];

	/**
	 * @param array $options['objectOptions']  An array of parameters to be added to the request URL.
	 * @param bool  $options['multiple']       Allows multiple selections.
	 * @param bool  $options['add_post_js']
	 *
	 * @see jQuery.multiSelect()
	 */
	public function __construct(array $options = []) {
		parent::__construct('div', true);

		$options = $this->mapOptions($options);

		$this
			->setId(zbx_formatDomId($options['name']))
			->addClass('multiselect')
			->setAttribute('role', 'application')
			->addItem((new CDiv())
				->setAttribute('aria-live', 'assertive')
				->setAttribute('aria-atomic', 'true')
			)
			->js_event_name = sprintf('multiselect_%s_init', $this->getId());

		if (array_key_exists('disabled', $options) && $options['disabled']) {
			$this->setAttribute('aria-disabled', 'true');
		}

		// Autocomplete url.
		$url = (new CUrl('jsrpc.php'))
			->setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON)
			->setArgument('method', static::SEARCH_METHOD)
			->setArgument('object_name', $options['object_name']);

		if (array_key_exists('objectOptions', $options)) {
			foreach ($options['objectOptions'] as $option_name => $option_value) {
				$url->setArgument($option_name, $option_value);
			}
		}

		$params = [
			'url' => $url->getUrl(),
			'name' => $options['name'],
			'labels' => [
				'No matches found' => _('No matches found'),
				'More matches found...' => _('More matches found...'),
				'type here to search' => _('type here to search'),
				'new' => _('new'),
				'Select' => _('Select')
			]
		];

		if (array_key_exists('data', $options)) {
			$params['data'] = zbx_cleanHashes($options['data']);
		}

		foreach (['defaultValue', 'disabled', 'selectedLimit', 'addNew', 'styles', 'placeholder'] as $option) {
			if (array_key_exists($option, $options)) {
				$params[$option] = $options[$option];
			}
		}

		if (array_key_exists('autosuggest', $options)) {
			if (array_key_exists('filter_preselect_fields', $options['autosuggest'])) {
				$params['autosuggest']['filter_preselect_fields'] = $options['autosuggest']['filter_preselect_fields'];
			}
		}

		if (array_key_exists('custom_select', $options)) {
			$params['custom_select'] = $options['custom_select'];
		}
		elseif (array_key_exists('popup', $options)) {
			if (array_key_exists('filter_preselect_fields', $options['popup'])) {
				$params['popup']['filter_preselect_fields'] = $options['popup']['filter_preselect_fields'];
			}

			if (array_key_exists('parameters', $options['popup'])) {
				$params['popup']['parameters'] = $options['popup']['parameters'];

				$excludeids = array_key_exists('excludeids', $options['popup']['parameters'])
					? $options['popup']['parameters']['excludeids']
					: [];

				$excludeids = array_merge($excludeids, array_key_exists('disableids', $options['popup']['parameters'])
					? $options['popup']['parameters']['disableids']
					: []);

				if ($excludeids) {
					$params['excludeids'] = $excludeids;
				}
			}
		}

		$this->params = $params;

		if (!array_key_exists('add_post_js', $options) || $options['add_post_js']) {
			zbx_add_post_js($this->getPostJS());
		}
	}

	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');
		return $this;
	}

	public function getPostJS() {
		return 'jQuery("#'.$this->getAttribute('id').'").multiSelect('.json_encode($this->params).');';
	}

	/**
	 * Multiselect options mapper for backward compatibility.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	protected function mapOptions(array $options) {
		$valid_fields = ['name', 'object_name', 'multiple', 'disabled', 'default_value', 'data', 'add_new',
			'add_post_js', 'styles', 'popup', 'custom_select', 'placeholder', 'autosuggest'
		];

		foreach ($options as $field => $value) {
			if (!in_array($field, $valid_fields)) {
				error('unsupported multiselect option: $options[\''.$field.'\']');
			}
		}

		$mapped_options = [];
		$mappings = [
			'name' => 'name',
			'object_name' => 'object_name',
			'disabled' => 'disabled',
			'default_value' => 'defaultValue',
			'data' => 'data',
			'add_new' => 'addNew',
			'add_post_js' => 'add_post_js',
			'styles' => 'styles',
			'placeholder' => 'placeholder'
		];

		foreach ($mappings as $new_field => $old_field) {
			if (array_key_exists($new_field, $options)) {
				$mapped_options[$old_field] = $options[$new_field];
			}
		}

		$multiple = array_key_exists('multiple', $options) ? $options['multiple'] : true;
		if (!$multiple) {
			$mapped_options['selectedLimit'] = '1';
		}

		if (array_key_exists('autosuggest', $options)) {
			$valid_fields = ['filter_preselect_fields'];

			foreach (array_keys($options['autosuggest']) as $field) {
				if (!in_array($field, $valid_fields)) {
					error('unsupported option: $options[\'autosuggest\'][\''.$field.'\']');
				}
			}

			if (array_key_exists('filter_preselect_fields', $options['autosuggest'])) {
				if (is_array($options['autosuggest']['filter_preselect_fields'])) {
					foreach ($options['autosuggest']['filter_preselect_fields'] as $field => $value) {
						if (in_array($field, $this->preselect_fields) && is_string($value) && $value !== '') {
							$mapped_options['autosuggest']['filter_preselect_fields'][$field] = $value;
						}
						else {
							error('invalid property: $options[\'autosuggest\'][\'filter_preselect_fields\'][\''.$field.'\']');
						}
					}
				}
				else {
					error('invalid property: $options[\'autosuggest\'][\'filter_preselect_fields\']');
				}
			}
		}

		$autocomplete_parameters = [];

		if (array_key_exists('custom_select', $options)) {
			$mapped_options['custom_select'] = true;
		}
		elseif (array_key_exists('popup', $options)) {
			$popup_parameters = [];

			$valid_fields = ['parameters', 'filter_preselect_fields'];

			foreach ($options['popup'] as $field => $value) {
				if (!in_array($field, $valid_fields)) {
					error('unsupported option: $options[\'popup\'][\''.$field.'\']');
				}
			}

			if (array_key_exists('filter_preselect_fields', $options['popup'])) {
				if (is_array($options['popup']['filter_preselect_fields'])) {
					foreach ($options['popup']['filter_preselect_fields'] as $field => $value) {
						if (in_array($field, $this->preselect_fields) && is_string($value) && $value !== '') {
							$mapped_options['popup']['filter_preselect_fields'][$field] = $value;
						}
						else {
							error('invalid property: $options[\'popup\'][\'filter_preselect_fields\'][\''.$field.'\']');
						}
					}
				}
				else {
					error('invalid property: $options[\'popup\'][\'filter_preselect_fields\']');
				}
			}

			if (array_key_exists('parameters', $options['popup'])) {
				$parameters = $options['popup']['parameters'];

				$valid_fields = ['srctbl', 'srcfld1', 'srcfld2', 'dstfrm', 'dstfld1', 'real_hosts', 'with_hosts',
					'monitored_hosts', 'with_monitored_triggers', 'editable', 'templated_hosts', 'with_templates',
					'hostid', 'parent_discoveryid', 'normal_only', 'numeric', 'with_graphs', 'with_graph_prototypes',
					'with_items', 'with_simple_graph_items', 'with_simple_graph_item_prototypes', 'with_triggers',
					'value_types', 'excludeids', 'disableids', 'enrich_parent_groups', 'with_monitored_items',
					'with_httptests', 'user_type', 'disable_selected', 'hostids', 'with_inherited', 'context',
					'enabled_only'
				];

				foreach ($parameters as $field => $value) {
					if (!in_array($field, $valid_fields)) {
						error('unsupported option: $options[\'popup\'][\'parameters\'][\''.$field.'\']');
					}
				}

				$mappings = [
					'srctbl' => 'srctbl',
					'srcfld1' => 'srcfld1',
					'srcfld2' => 'srcfld2',
					'dstfrm' => 'dstfrm',
					'dstfld1' => 'dstfld1'
				];

				foreach ($mappings as $new_field => $old_field) {
					if (array_key_exists($new_field, $parameters)) {
						$popup_parameters[$old_field] = $parameters[$new_field];
					}
				}

				if ($multiple) {
					$popup_parameters['multiselect'] = '1';
				}

				if (array_key_exists('hostid', $parameters) && $parameters['hostid'] > 0) {
					$popup_parameters['only_hostid'] = (string) $parameters['hostid'];
					$autocomplete_parameters['hostid'] = (string) $parameters['hostid'];
				}

				if (array_key_exists('groupid', $parameters) && $parameters['groupid'] > 0) {
					$popup_parameters['groupid'] = (string) $parameters['groupid'];
				}

				if (array_key_exists('parent_discoveryid', $parameters) && $parameters['parent_discoveryid'] > 0) {
					$popup_parameters['parent_discoveryid'] = $parameters['parent_discoveryid'];
					$autocomplete_parameters['parent_discoveryid'] = $parameters['parent_discoveryid'];
				}

				if (array_key_exists('numeric', $parameters) && $parameters['numeric']) {
					$popup_parameters['numeric'] = '1';
					$autocomplete_parameters['filter']['value_type'] = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
				}

				if (array_key_exists('normal_only', $parameters) && $parameters['normal_only']) {
					$popup_parameters['normal_only'] = '1';
					$autocomplete_parameters['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				}

				if (array_key_exists('value_types', $parameters)) {
					$popup_parameters['value_types'] = $parameters['value_types'];
					$autocomplete_parameters['filter']['value_type'] = $parameters['value_types'];
				}

				if (array_key_exists('real_hosts', $parameters) && $parameters['real_hosts']) {
					$popup_parameters['real_hosts'] = '1';
					$autocomplete_parameters['real_hosts'] = true;
				}

				if (array_key_exists('with_hosts', $parameters) && $parameters['with_hosts']) {
					$popup_parameters['real_hosts'] = '1';
					$autocomplete_parameters['with_hosts'] = true;
				}

				if (array_key_exists('templated_hosts', $parameters) && $parameters['templated_hosts']) {
					$popup_parameters['templated_hosts'] = '1';
					$autocomplete_parameters['templated_hosts'] = true;
				}

				if ($popup_parameters['srctbl'] == 'template_triggers') {
					$autocomplete_parameters['templated'] = true;
				}

				if (array_key_exists('with_templates', $parameters) && $parameters['with_templates']) {
					$popup_parameters['templated_hosts'] = '1';
					$autocomplete_parameters['with_templates'] = true;
				}

				foreach (['with_graphs', 'with_graph_prototypes', 'with_simple_graph_items',
						'with_simple_graph_item_prototypes', 'with_triggers', 'with_inherited'] as $name) {
					if (array_key_exists($name, $parameters) && $parameters[$name]) {
						$popup_parameters[$name] = '1';
						$autocomplete_parameters[$name] = true;
					}
				}

				if (array_key_exists('editable', $parameters) && $parameters['editable']) {
					$popup_parameters['writeonly'] = '1';
					$autocomplete_parameters['editable'] = true;
				}

				if (array_key_exists('monitored_hosts', $parameters) && $parameters['monitored_hosts']) {
					$popup_parameters['monitored_hosts'] = '1';
					$autocomplete_parameters['monitored'] = true;
				}

				if (array_key_exists('with_monitored_triggers', $parameters) && $parameters['with_monitored_triggers']) {
					$popup_parameters['with_monitored_triggers'] = '1';

					if ($popup_parameters['srctbl'] === 'triggers') {
						$autocomplete_parameters['monitored'] = true;
					}
					else {
						$autocomplete_parameters['with_monitored_triggers'] = true;
					}
				}

				if (array_key_exists('with_monitored_items', $parameters) && $parameters['with_monitored_items']) {
					$popup_parameters['with_monitored_items'] = '1';
					$autocomplete_parameters['with_monitored_items'] = true;
				}

				if (array_key_exists('with_httptests', $parameters) && $parameters['with_httptests']) {
					$popup_parameters['with_httptests'] = '1';
					$autocomplete_parameters['with_httptests'] = true;
				}

				if (array_key_exists('with_items', $parameters) && $parameters['with_items']) {
					$popup_parameters['with_items'] = '1';
					$autocomplete_parameters['with_items'] = true;
				}

				if (array_key_exists('excludeids', $parameters) && $parameters['excludeids']) {
					$popup_parameters['excludeids'] = $parameters['excludeids'];
				}

				if (array_key_exists('disableids', $parameters) && $parameters['disableids']) {
					$popup_parameters['disableids'] = $parameters['disableids'];
				}

				if (array_key_exists('enrich_parent_groups', $parameters) && $parameters['enrich_parent_groups']) {
					$popup_parameters['enrich_parent_groups'] = '1';
					$autocomplete_parameters['enrich_parent_groups'] = true;
				}

				if (array_key_exists('user_type', $parameters) && $parameters['user_type']) {
					$popup_parameters['user_type'] = (int) $parameters['user_type'];
					$autocomplete_parameters['user_type'] = (int) $parameters['user_type'];
				}

				if (array_key_exists('disable_selected', $parameters) && $parameters['disable_selected']) {
					$popup_parameters['disable_selected'] = '1';
				}

				if (array_key_exists('hostids', $parameters) && $parameters['hostids']) {
					$popup_parameters['hostids'] = $parameters['hostids'];
					$autocomplete_parameters['hostids'] = $parameters['hostids'];
				}

				if (array_key_exists('context', $parameters)) {
					$popup_parameters['context'] = $parameters['context'];
					$autocomplete_parameters['context'] = $parameters['context'];
				}

				if (array_key_exists('enabled_only', $parameters) && $parameters['enabled_only']) {
					$popup_parameters['enabled_only'] = '1';
					$autocomplete_parameters['enabled_only'] = true;
				}
			}

			$mapped_options['popup']['parameters'] = $popup_parameters;
		}

		$mapped_options['objectOptions'] = $autocomplete_parameters;

		return $mapped_options;
	}
}
