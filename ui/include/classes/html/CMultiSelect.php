<?php
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


class CMultiSelect extends CTag {
	/**
	 * Default CSS class name for HTML root element.
	 */
	const ZBX_STYLE_CLASS = 'multiselect-control';

	/**
	 * Search method used for autocomplete requests.
	 */
	const SEARCH_METHOD = 'multiselect.get';

	const FILTER_PRESELECT_ACCEPT_ID = 'id';

	/**
	 * @var array
	 */
	protected $params = [];

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
			);

		if (array_key_exists('disabled', $options) && $options['disabled']) {
			$this->setAttribute('aria-disabled', 'true');
		}

		if (array_key_exists('readonly', $options) && $options['readonly']) {
			$this->setAttribute('aria-readonly', 'true');
		}

		$this->params = [
			'name' => $options['name'],
			'labels' => [
				'No matches found' => _('No matches found'),
				'More matches found...' => _('More matches found...'),
				'type here to search' => _('type here to search'),
				'new' => _('new'),
				'Select' => _('Select')
			]
		];

		if (array_key_exists('object_name', $options)) {
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

			$this->params['url'] = $url->getUrl();
		}

		if (array_key_exists('multiselect_id', $options)) {
			$this->params['multiselect_id'] = $options['multiselect_id'];
		}

		if (array_key_exists('data', $options)) {
			$this->params['data'] = array_values($options['data']);
		}

		foreach (['defaultValue', 'disabled', 'selectedLimit', 'addNew', 'styles', 'placeholder', 'hidden', 'readonly']
				as $option) {
			if (array_key_exists($option, $options)) {
				$this->params[$option] = $options[$option];
			}
		}

		if (array_key_exists('autosuggest', $options)
				&& array_key_exists('filter_preselect', $options['autosuggest'])) {
			$this->params['autosuggest']['filter_preselect'] = $options['autosuggest']['filter_preselect'];
		}

		if (array_key_exists('custom_select', $options)) {
			$this->params['custom_select'] = $options['custom_select'];
		}
		elseif (array_key_exists('popup', $options)) {
			if (array_key_exists('filter_preselect', $options['popup'])) {
				$this->params['popup']['filter_preselect'] = $options['popup']['filter_preselect'];
			}

			if (array_key_exists('parameters', $options['popup'])) {
				$this->params['popup']['parameters'] = $options['popup']['parameters'];
			}
		}

		$this->setAttribute('data-params', $this->params);

		if (!array_key_exists('add_post_js', $options) || $options['add_post_js']) {
			zbx_add_post_js($this->getPostJS());
		}
	}

	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');
		return $this;
	}

	public function getParams(): array {
		return $this->params;
	}

	public function getPostJS() {
		return 'jQuery("#'.$this->getAttribute('id').'").multiSelect();';
	}

	/**
	 * Multiselect options mapper for backward compatibility.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	protected function mapOptions(array $options) {
		$valid_fields = ['name', 'object_name', 'multiselect_id', 'multiple', 'disabled', 'default_value', 'data',
			'add_new', 'add_post_js', 'styles', 'popup', 'custom_select', 'placeholder', 'autosuggest', 'hidden',
			'readonly'
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
			'multiselect_id' => 'multiselect_id',
			'disabled' => 'disabled',
			'hidden' => 'hidden',
			'default_value' => 'defaultValue',
			'data' => 'data',
			'add_new' => 'addNew',
			'add_post_js' => 'add_post_js',
			'styles' => 'styles',
			'placeholder' => 'placeholder',
			'readonly' => 'readonly'
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
			$valid_fields = ['filter_preselect'];

			foreach (array_keys($options['autosuggest']) as $field) {
				if (!in_array($field, $valid_fields)) {
					error('unsupported option: $options[\'autosuggest\'][\''.$field.'\']');
				}
			}

			if (array_key_exists('filter_preselect', $options['autosuggest'])) {
				if (is_array($options['autosuggest']['filter_preselect'])) {
					if (self::validateFilterPreselect($options['autosuggest']['filter_preselect'],
							'$options[\'autosuggest\'][\'filter_preselect\']')) {
						$mapped_options['autosuggest']['filter_preselect']
							= $options['autosuggest']['filter_preselect'];
					}
				}
				else {
					error('invalid property: $options[\'autosuggest\'][\'filter_preselect\']');
				}
			}
		}

		$autocomplete_parameters = [];

		if (array_key_exists('custom_select', $options)) {
			$mapped_options['custom_select'] = true;
		}
		elseif (array_key_exists('popup', $options)) {
			$valid_fields = ['parameters', 'filter_preselect'];

			foreach (array_keys($options['popup']) as $field) {
				if (!in_array($field, $valid_fields)) {
					error('unsupported option: $options[\'popup\'][\''.$field.'\']');
				}
			}

			if (array_key_exists('filter_preselect', $options['popup'])) {
				if (is_array($options['popup']['filter_preselect'])) {
					if (self::validateFilterPreselect($options['popup']['filter_preselect'],
							'$options[\'popup\'][\'filter_preselect\']')) {
						$mapped_options['popup']['filter_preselect'] = $options['popup']['filter_preselect'];
					}
				}
				else {
					error('invalid property: $options[\'popup\'][\'filter_preselect\']');
				}
			}

			$popup_parameters = [];

			if (array_key_exists('parameters', $options['popup'])) {
				$parameters = $options['popup']['parameters'];

				$valid_fields = ['srctbl', 'srcfld1', 'srcfld2', 'dstfrm', 'dstfld1', 'real_hosts', 'with_hosts',
					'monitored_hosts', 'with_monitored_triggers', 'editable', 'templated_hosts', 'with_templates',
					'hostid', 'parent_discoveryid', 'normal_only', 'numeric', 'with_graphs', 'with_graph_prototypes',
					'with_items', 'with_simple_graph_items', 'with_simple_graph_item_prototypes', 'with_triggers',
					'value_types', 'excludeids', 'disableids', 'enrich_parent_groups', 'with_monitored_items',
					'with_httptests', 'user_type', 'disable_selected', 'hostids', 'with_inherited', 'context',
					'enabled_only', 'group_status', 'hide_host_filter', 'resolve_macros', 'exclude_provisioned'
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

				if (array_key_exists('hide_host_filter', $parameters)) {
					$popup_parameters['hide_host_filter'] = '1';
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

				if (array_key_exists('group_status', $parameters)) {
					$popup_parameters['group_status'] = $parameters['group_status'];
					$autocomplete_parameters['group_status'] = $parameters['group_status'];
				}

				if (array_key_exists('resolve_macros', $parameters) && $parameters['resolve_macros']) {
					$popup_parameters['resolve_macros'] = '1';
					$autocomplete_parameters['resolve_macros'] = true;
				}

				if (array_key_exists('exclude_provisioned', $parameters) && $parameters['exclude_provisioned']) {
					$popup_parameters['exclude_provisioned'] = 1;
					$autocomplete_parameters['exclude_provisioned'] = 1;
				}
			}

			$mapped_options['popup']['parameters'] = $popup_parameters;
		}

		$mapped_options['objectOptions'] = $autocomplete_parameters;

		return $mapped_options;
	}

	protected static function validateFilterPreselect(array $field, string $path): bool {
		$is_valid = true;

		foreach (array_keys($field) as $option) {
			if (!in_array($option, ['id', 'accept', 'submit_as', 'submit_parameters', 'multiple'])) {
				error('unsupported option: '.$path.'[\''.$option.'\']');
				$is_valid = false;
			}
		}

		if (!array_key_exists('id', $field) || !is_string($field['id']) || $field['id'] === '') {
			error('invalid property: '.$path.'[\'id\']');
			$is_valid = false;
		}

		if (array_key_exists('accept', $field) && $field['accept'] !== self::FILTER_PRESELECT_ACCEPT_ID) {
			error('invalid property: '.$path.'[\'accept\']');
			$is_valid = false;
		}

		if (!array_key_exists('submit_as', $field) || !is_string($field['submit_as']) || $field['submit_as'] === '') {
			error('invalid property: '.$path.'[\'submit_as\']');
			$is_valid = false;
		}

		if (array_key_exists('submit_parameters', $field) && !is_array($field['submit_parameters'])) {
			error('invalid property: '.$path.'[\'submit_parameters\']');
			$is_valid = false;
		}

		if (array_key_exists('multiple', $field) && !is_bool($field['multiple'])) {
			error('invalid property: '.$path.'[\'multiple\']');
			$is_valid = false;
		}

		return $is_valid;
	}
}
