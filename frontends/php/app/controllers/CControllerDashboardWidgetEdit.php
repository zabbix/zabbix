<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerDashboardWidgetEdit extends CController {

	protected function checkInput() {
		$fields = [
			'type' => 'in '.implode(',', array_keys(CWidgetConfig::getKnownWidgetTypes())),
			'name' => 'string',
			'fields' => 'json'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var string fields[<name>]  (optional)
			 */
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['body' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$known_widget_types = CWidgetConfig::getKnownWidgetTypes();
		natsort($known_widget_types);

		$type = $this->getInput('type', array_keys($known_widget_types)[0]);
		$form = CWidgetConfig::getForm($type, $this->getInput('fields', '{}'));

		$config = select_config();

		$this->setResponse(new CControllerResponseData([
			'config' => [
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'dialogue' => [
				'type' => $type,
				'name' => $this->getInput('name', ''),
				'fields' => $form->getFields(false),
				'tabs' => $form->getTabs()
			],
			'known_widget_types' => $known_widget_types,
			'captions' => $this->getCaptions($form)
		]));
	}

	/**
	 * Prepares mapped list of names for all required resources.
	 *
	 * @param CWidgetForm $form
	 *
	 * @return array
	 */
	private function getCaptions($form) {
		$captions = ['simple' => [], 'ms' => []];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldSelectResource) {
				$resource_type = $field->getResourceType();
				$id = $field->getValue();

				if (!array_key_exists($resource_type, $captions['simple'])) {
					$captions['simple'][$resource_type] = [];
				}

				if ($id != 0) {
					switch ($resource_type) {
						case WIDGET_FIELD_SELECT_RES_SYSMAP:
							$captions['simple'][$resource_type][$id] = _('Inaccessible map');
							break;

						case WIDGET_FIELD_SELECT_RES_GRAPH:
							$captions['simple'][$resource_type][$id] = _('Inaccessible graph');
							break;
					}
				}
			}
		}

		foreach ($captions['simple'] as $resource_type => &$list) {
			if (!$list) {
				continue;
			}

			switch ($resource_type) {
				case WIDGET_FIELD_SELECT_RES_SYSMAP:
					$maps = API::Map()->get([
						'sysmapids' => array_keys($list),
						'output' => ['sysmapid', 'name']
					]);

					if ($maps) {
						foreach ($maps as $key => $map) {
							$list[$map['sysmapid']] = $map['name'];
						}
					}
					break;

				case WIDGET_FIELD_SELECT_RES_GRAPH:
					$graphs = API::Graph()->get([
						'graphids' => array_keys($list),
						'selectHosts' => ['name'],
						'output' => ['graphid', 'name']
					]);

					if ($graphs) {
						foreach ($graphs as $key => $graph) {
							order_result($graph['hosts'], 'name');
							$graph['host'] = reset($graph['hosts']);
							$list[$graph['graphid']] = $graph['host']['name'].NAME_DELIMITER.$graph['name'];
						}
					}
					break;
			}
		}
		unset($list);

		// Prepare data for CMultiSelect controls.
		$groupids = [];
		$hostids = [];
		$itemids = [];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldGroup) {
				$field_name = $field->getName();
				$captions['ms']['groups'][$field_name] = [];

				foreach ($field->getValue() as $groupid) {
					$captions['ms']['groups'][$field_name][$groupid] = ['id' => $groupid];
					$groupids[$groupid][] = $field_name;
				}
			}
			elseif ($field instanceof CWidgetFieldHost) {
				$field_name = $field->getName();
				$captions['ms']['hosts'][$field_name] = [];

				foreach ($field->getValue() as $hostid) {
					$captions['ms']['hosts'][$field_name][$hostid] = ['id' => $hostid];
					$hostids[$hostid][] = $field_name;
				}
			}
			elseif ($field instanceof CWidgetFieldItem) {
				$field_name = $field->getName();
				$captions['ms']['items'][$field_name] = [];

				foreach ($field->getValue() as $itemid) {
					$captions['ms']['items'][$field_name][$itemid] = ['id' => $itemid];
					$itemids[$itemid][] = $field_name;
				}
			}
		}

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			foreach ($groups as $groupid => $group) {
				foreach ($groupids[$groupid] as $field_name) {
					$captions['ms']['groups'][$field_name][$groupid]['name'] = $group['name'];
					unset($captions['ms']['groups'][$field_name][$groupid]['inaccessible']);
				}
			}
		}

		if ($hostids) {
			$hosts = API::Host()->get([
				'output' => ['name'],
				'hostids' => array_keys($hostids),
				'preservekeys' => true
			]);

			foreach ($hosts as $hostid => $host) {
				foreach ($hostids[$hostid] as $field_name) {
					$captions['ms']['hosts'][$field_name][$hostid]['name'] = $host['name'];
				}
			}
		}

		if ($itemids) {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($itemids),
				'preservekeys' => true,
				'webitems' => true
			]);

			$items = CMacrosResolverHelper::resolveItemNames($items);

			foreach ($items as $itemid => $item) {
				foreach ($itemids[$itemid] as $field_name) {
					$captions['ms']['items'][$field_name][$itemid] = [
						'id' => $itemid,
						'name' => $item['name_expanded'],
						'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
					];
				}
			}
		}

		$inaccessible_resources = [
			'groups' => _('Inaccessible group'),
			'hosts' => _('Inaccessible host'),
			'items' => _('Inaccessible item')
		];

		foreach ($captions['ms'] as $resource_type => &$fields_captions) {
			foreach ($fields_captions as &$field_captions) {
				$n = 0;

				foreach ($field_captions as &$caption) {
					if (!array_key_exists('name', $caption)) {
						$postfix = (++$n > 1) ? ' ('.$n.')' : '';
						$caption['name'] = $inaccessible_resources[$resource_type].$postfix;
						$caption['inaccessible'] = true;
					}
				}
				unset($caption);
			}
			unset($field_captions);
		}
		unset($fields_captions);

		return $captions;
	}

	/**
	 * Parses given array of $fields and puts them into $form_list. Adds also field specific attributes to $form,
	 * $js_scripts and $jq_templates.
	 *
	 * @param array     $fields
	 * @param CFormList $form_list
	 * @param CForm     $form
	 * @param array     $js_scripts
	 * @param array     $jq_templates
	 * @param array     $data
	 */
	public static function parseViewField($fields, &$form_list, &$form, &$js_scripts, &$jq_templates, $data) {
		foreach ($fields as $field) {
			$aria_required = ($field->getFlags() & CWidgetField::FLAG_LABEL_ASTERISK);
			$disabled = ($field->getFlags() & CWidgetField::FLAG_DISABLED);

			if ($field instanceof CWidgetFieldComboBox) {
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					(new CComboBox($field->getName(), $field->getValue(), $field->getAction(), $field->getValues()))
						->setAriaRequired($aria_required)
						->setEnabled(!$disabled)
				);
			}
			elseif ($field instanceof CWidgetFieldTextBox || $field instanceof CWidgetFieldUrl) {
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					(new CTextBox($field->getName(), $field->getValue()))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired($aria_required)
						->setEnabled(!$disabled)
				);
			}
			elseif ($field instanceof CWidgetFieldCheckBox) {
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required), [
					new CVar($field->getName(), '0'),
					(new CCheckBox($field->getName()))
						->setChecked((bool) $field->getValue())
						->setEnabled(!$disabled)
						->setLabel($field->getCaption())
						->onChange($field->getAction())
				]);
			}
			elseif ($field instanceof CWidgetFieldGroup) {
				// multiselect.js must be preloaded in parent view.

				$field_name = $field->getName().'[]';

				$field_groupids = (new CMultiSelect([
					'name' => $field_name,
					'object_name' => 'hostGroup',
					'data' => $data['captions']['ms']['groups'][$field->getName()],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => $form->getName(),
							'dstfld1' => zbx_formatDomId($field_name),
						]
					],
					'add_post_js' => false
				]))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired($aria_required);

				$form_list->addRow((new CLabel($field->getLabel(), $field_name.'_ms'))->setAsteriskMark($aria_required),
					$field_groupids
				);

				$js_scripts[] = $field_groupids->getPostJS();
			}
			elseif ($field instanceof CWidgetFieldHost) {
				// multiselect.js must be preloaded in parent view.

				$field_name = $field->getName().'[]';

				$field_hostids = (new CMultiSelect([
					'name' => $field_name,
					'object_name' => 'hosts',
					'data' => $data['captions']['ms']['hosts'][$field->getName()],
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => $form->getName(),
							'dstfld1' => zbx_formatDomId($field_name)
						]
					],
					'add_post_js' => false
				]))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired($aria_required);

				$form_list->addRow((new CLabel($field->getLabel(), $field_name.'_ms'))->setAsteriskMark($aria_required),
					$field_hostids
				);

				$js_scripts[] = $field_hostids->getPostJS();
			}
			elseif ($field instanceof CWidgetFieldItem) {
				// multiselect.js must be preloaded in parent view.

				$field_name = $field->getName().($field->isMultiple() ? '[]' : '');

				$field_itemsids = (new CMultiSelect([
					'name' => $field_name,
					'object_name' => 'items',
					'multiple' => $field->isMultiple(),
					'data' => $data['captions']['ms']['items'][$field->getName()],
					'popup' => [
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'itemid',
							'dstfrm' => $form->getName(),
							'dstfld1' => zbx_formatDomId($field_name)
						] + $field->getFilterParameters()
					],
					'add_post_js' => false
				]))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired($aria_required);

				$form_list->addRow((new CLabel($field->getLabel(), $field_name.'_ms'))->setAsteriskMark($aria_required),
					$field_itemsids
				);

				$js_scripts[] = $field_itemsids->getPostJS();
			}
			elseif ($field instanceof CWidgetFieldReference) {
				$form->addVar($field->getName(), $field->getValue() ? $field->getValue() : '');

				if (!$field->getValue()) {
					$javascript = $field->getJavascript('#'.$form->getAttribute('id'));
					$form->addItem(new CJsScript(get_js($javascript, true)));
				}
			}
			elseif ($field instanceof CWidgetFieldHidden) {
				$form->addVar($field->getName(), $field->getValue());
			}
			elseif ($field instanceof CWidgetFieldSelectResource) {
				$caption = ($field->getValue() != 0)
					? $data['captions']['simple'][$field->getResourceType()][$field->getValue()]
					: '';

				// Needed for popup script.
				$form->addVar($field->getName(), $field->getValue());
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required), [
					(new CTextBox($field->getName().'_caption', $caption, true))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired($aria_required),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('select', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('return PopUp("popup.generic",'.
							CJs::encodeJson($field->getPopupOptions($form->getName())).', null, this);')
				]);
			}
			elseif ($field instanceof CWidgetFieldWidgetListComboBox) {
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					(new CComboBox($field->getName(), [], $field->getAction(), []))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired($aria_required)
				);

				$form->addItem(new CJsScript(get_js($field->getJavascript(), true)));
			}
			elseif ($field instanceof CWidgetFieldNumericBox) {
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					(new CNumericBox($field->getName(), $field->getValue(), $field->getMaxLength()))
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAriaRequired($aria_required)
				);
			}
			elseif ($field instanceof CWidgetFieldRadioButtonList) {
				$radio_button_list = (new CRadioButtonList($field->getName(), $field->getValue()))
					->setModern($field->getModern())
					->setAriaRequired($aria_required);

				foreach ($field->getValues() as $key => $value) {
					$radio_button_list
						->addValue($value, $key, null, $field->getAction())
						->setEnabled(!$disabled);
				}

				$form_list->addRow(
					(new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					$radio_button_list
				);
			}
			elseif ($field instanceof CWidgetFieldSeverities) {
				$severities = (new CList())
					->addClass($field->getStyle());

				for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
					$severities->addItem(
						(new CCheckBox($field->getName().'[]', $severity))
							->setLabel(getSeverityName($severity, $data['config']))
							->setId($field->getName().'_'.$severity)
							->setChecked(in_array($severity, $field->getValue()))
							->setEnabled(!$disabled)
					);
				}

				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					$severities
				);
			}
			elseif ($field instanceof CWidgetFieldTags) {
				$tags = $field->getValue();

				if (!$tags) {
					$tags = [['tag' => '', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']];
				}

				$tags_table = (new CTable())->setId('tags_table');
				$i = 0;

				foreach ($tags as $tag) {
					$tags_table->addRow([
						(new CTextBox($field->getName().'['.$i.'][tag]', $tag['tag']))
							->setAttribute('placeholder', _('tag'))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAriaRequired($aria_required)
							->setEnabled(!$disabled),
						(new CRadioButtonList($field->getName().'['.$i.'][operator]', (int) $tag['operator']))
							->addValue(_('Like'), TAG_OPERATOR_LIKE)
							->addValue(_('Equal'), TAG_OPERATOR_EQUAL)
							->setModern(true)
							->setEnabled(!$disabled),
						(new CTextBox($field->getName().'['.$i.'][value]', $tag['value']))
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAriaRequired($aria_required)
							->setEnabled(!$disabled),
						(new CCol(
							(new CButton($field->getName().'['.$i.'][remove]', _('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-remove')
								->setEnabled(!$disabled)
						))->addClass(ZBX_STYLE_NOWRAP)
					], 'form_row');

					$i++;
				}

				$tags_table->addRow(
					(new CCol(
						(new CButton('tags_add', _('Add')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-add')
							->setEnabled(!$disabled)
					))->setColSpan(3)
				);

				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					$tags_table
				);

				$jq_templates['tag-row'] = (new CRow([
					(new CTextBox($field->getName().'[#{rowNum}][tag]'))
						->setAttribute('placeholder', _('tag'))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAriaRequired($aria_required),
					(new CRadioButtonList($field->getName().'[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
						->addValue(_('Like'), TAG_OPERATOR_LIKE)
						->addValue(_('Equal'), TAG_OPERATOR_EQUAL)
						->setModern(true),
					(new CTextBox($field->getName().'[#{rowNum}][value]'))
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAriaRequired($aria_required),
					(new CCol(
						(new CButton($field->getName().'[#{rowNum}][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
					))->addClass(ZBX_STYLE_NOWRAP)
				]))
					->addClass('form_row')
					->toString();

				// Add dynamic row script and fix the distance between AND/OR buttons and tag inputs below them.
				$js_scripts[] = 'var tags_table = jQuery("#tags_table");'.
					'tags_table.dynamicRows({template: "#tag-row"});'.
					'tags_table.parent().addClass("has-before");';
			}
			elseif ($field instanceof CWidgetFieldDatePicker) {
				$form_list->addRow((new CLabel($field->getLabel(), $field->getName()))->setAsteriskMark($aria_required),
					[
						(new CTextBox($field->getName(), $field->getValue()))
							->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
							->setAriaRequired($aria_required)
							->setEnabled(!$disabled),
						(new CButton($field->getName().'_dp'))
							->addClass(ZBX_STYLE_ICON_CAL)
							->setEnabled(!$disabled)
					]
				);

				$js_scripts[] =
					'var input = jQuery("[name=\"'.$field->getName().'\"]", jQuery("#'.$form->getName().'")).get(0);'.
					'jQuery("#'.$field->getName().'_dp")'.
						'.data("clndr", create_calendar(null, input))'.
						'.data("input", input)'.
						'.click(function() {'.
							'var b = jQuery(this),'.
								'o = b.offset(),'.
								't = parseInt(o.top + b.outerHeight(), 10),'.
								'l = parseInt(o.left, 10);'.
							'b.data("clndr").clndr.clndrshow(t, l, b.data("input"));'.
							'return false;'.
						'})';
			}
			elseif ($field instanceof CWidgetFieldGraphOverride) {
				$overrides = $field->getValue();

				if (!$overrides) {
					$overrides = [
						['hosts' => '', 'items' => '']
					];
				}

				$override_list = (new CList())->addClass(ZBX_STYLE_OVERRIDES_LIST)->setId('overrides');
				$i = 0;

				foreach ($overrides as $override) {
					$options = [
						'row_num' => $i,
						'form_name' => $form->getName()
					];

					$override_list->addItem($field->getFieldLayout($override, $options), ZBX_STYLE_OVERRIDES_LIST_ITEM);
					$i++;
				}

				// Add 'Add' button under the list.
				$override_list->addItem(
					(new CButton('override_add', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add new override')]))
						->addClass(ZBX_STYLE_BTN_ALT)
						->setId('override-add'),
					'overrides-foot'
				);

				// Add accordion.
				$form_list->addRow($override_list);

				// Add dynamicRows template.
				$jq_templates['overrides-row'] = (new CListItem(
					$field->getFieldLayout(
						['hosts' => '', 'items' => ''],
						['row_num' => '#{rowNum}', 'form_name' => $form->getName()]
					)
				))
					->addClass(ZBX_STYLE_OVERRIDES_LIST_ITEM)
					->toString();

				// Define initialization as function to avoid redundancy.
				$js_scripts[] =
					'function initializeOverrides() {'.
						'jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_OPTIONS_LIST.'").overrides({'.
							'add: ".'.ZBX_STYLE_BTN_ALT.'",'.
							'options: "input[type=hidden]",'.
							'captions: '.CJs::encodeJson($field->getOverrideOptionNames()).','.
							'makeName: function(option) {return "'.$field->getName().'["+this.rowId+"]["+option+"]";},'.
							'makeOption: function(name) {'.
								'return name.match(/.*\[('.implode('|', $field->getOverrideOptions()).')\]/)[1];},'.
							'menu: '.CJs::encodeJson($field->getOverrideMenu()).
						'});'.
					'}';

				// Initialize dynamicRows.
				$js_scripts[] = 'jQuery("#overrides")'.
					'.dynamicRows({'.
						'template: "#overrides-row",'.
						'beforeRow: ".overrides-foot",'.
						'remove: ".'.ZBX_STYLE_BTN_TRASH.'",'.
						'add: "#override-add",'.
						'row: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'"'.
					'})'.
					'.bind("tableupdate.dynamicRows", function(event, options) {'.
						'initializeOverrides();'.
					'});';

				// Initialize overrides.
				$js_scripts[] = 'initializeOverrides();';

				// Initialize sortability.
				$js_scripts[] = 'jQuery("#overrides").sortable({'.
					'items: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",'.
					'containment: "parent",'.
					'handle: ".drag-icon",'.
					'tolerance: "pointer",'.
					'cursor: "move",'.
					'opacity: 0.6,'.
					'axis: "y"'.
				'});';
			}
			elseif ($field instanceof CWidgetFieldGraphDataSet) {
				$accordion = (new CList())->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION)->setId('data_sets');

				$data_sets = $field->getValue();
				if (!$data_sets) {
					$data_sets = [$field->getDefault()];
				}

				$i = 0;
				foreach ($data_sets as $data_set) {
					$class = ZBX_STYLE_LIST_ACCORDION_ITEM;
					if ($i == 0) {
						$class .= ' '.ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED;
					}

					$options = [
						'row_num' => $i,
						'letter_id' => num2letter($i),
						'form_name' => $form->getName(),
						'is_opened' => ($i == 0)
					];

					$accordion->addItem($field->getFieldLayout($data_set, $options), $class);
					$i++;
				}

				// Add 'Add' button under accordion.
				$accordion->addItem(
					(new CButton('data_sets_add', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add new data set')]))
						->addClass(ZBX_STYLE_BTN_ALT)
						->setId('dataset-add'),
					ZBX_STYLE_LIST_ACCORDION_FOOT
				);

				// Add accordion.
				$form_list->addRow($accordion);

				// Add dynamicRows template.
				$jq_templates['dataset-row'] = (new CListItem(
					$field->getFieldLayout(
						$field->getDefault(),
						[
							'row_num' => '#{rowNum}',
							'letter_id' => '#{formulaId}',
							'form_name' => $form->getName(),
							'is_opened' => false
						]
					)
				))
					->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM)
					->toString();

				// Initialize dynamicRows.
				$js_scripts[] =
					'jQuery("#data_sets").dynamicRows({'.
						'template: "#dataset-row",'.
						'beforeRow: ".'.ZBX_STYLE_LIST_ACCORDION_FOOT.'",'.
						'remove: ".'.ZBX_STYLE_BTN_TRASH.'",'.
						'add: "#dataset-add",'.
						'row: ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'",'.
						'dataCallback: function(data) {'.
							'data.formulaId = num2letter(data.rowNum);'.
							'return data;'.
						'}'.
					'});';

				// Initialize accordion.
				$js_scripts[] = 'jQuery("#data_sets").zbx_vertical_accordion({handler: ".'.ZBX_STYLE_BTN_GEAR.'"});';

				// Initialize sortability.
				$js_scripts[] =
					'jQuery("#data_sets").sortable({'.
						'items: ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'",'.
						'containment: "parent",'.
						'handle: ".drag-icon",'.
						'tolerance: "pointer",'.
						'cursor: "move",'.
						'opacity: 0.6,'.
						'axis: "y"'.
					'});';
			}
			elseif ($field instanceof CWidgetFieldEmbed) {
				$form_list->addItem($field->getItem());
			}
		}
	}
}
