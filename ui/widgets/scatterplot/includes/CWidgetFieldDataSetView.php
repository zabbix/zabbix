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


namespace Widgets\ScatterPlot\Includes;

use CButton,
	CButtonIcon,
	CButtonLink,
	CCol,
	CColorPicker,
	CDiv,
	CFormField,
	CFormGrid,
	CHorList,
	CIcon,
	CItemHelper,
	CLabel,
	CLink,
	CList,
	CListItem,
	CMultiSelect,
	CRadioButtonList,
	CRow,
	CScriptTag,
	CSelect,
	CSimpleButton,
	CSpan,
	CTable,
	CTableColumn,
	CTag,
	CTemplateTag,
	CTextBox,
	CVar;

use CWidgetFieldMultiSelectOverrideHostView,
	CWidgetFieldView,
	CWidgetFieldMultiSelectGroupView,
	CWidgetFieldPatternSelectHostView,
	CWidgetFieldPatternSelectItemView,
	CWidgetFieldTagsView;

use Zabbix\Widgets\CWidgetField;

use Zabbix\Widgets\Fields\{
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldPatternSelectHost,
	CWidgetFieldPatternSelectItem,
	CWidgetFieldTags
};

class CWidgetFieldDataSetView extends CWidgetFieldView {

	public function __construct(CWidgetFieldDataSet $field) {
		$this->field = $field;
	}

	public function getView(): CList {
		$view = (new CList())->setId('data_sets');

		$values = $this->field->getValue();

		if (!$values) {
			$values[] = CWidgetFieldDataSet::getDefaults();
		}

		// Get item names for single item datasets.
		$itemids = [];

		foreach (['x_axis_itemids', 'y_axis_itemids'] as $key) {
			foreach (array_column($values, $key) as $items_spec) {
				foreach ($items_spec as $item_spec) {
					if (!is_array($item_spec)) {
						$itemids[$item_spec] = true;
					}
				}
			}
		}

		$item_names = $itemids
			? CWidgetFieldDataSet::getItemNames(array_keys($itemids), !$this->field->isTemplateDashboard())
			: [];

		foreach ($values as $i => $value) {
			if ($value['dataset_type'] == CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM) {
				$value['item_names'] = $item_names;
			}

			$view->addItem(
				$this->getGraphDataSetLayout($value, $value['dataset_type'], $i == 0, $i)
			);
		}

		return $view;
	}

	public function getFooterView(): CList {
		return (new CList())
			->addClass(ZBX_STYLE_BTN_SPLIT)
			->addItem(
				(new CSimpleButton(_('Add new data set')))
					->setId('dataset-add')
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass(ZBX_ICON_PLUS_SMALL)
			)
			->addItem(
				(new CSimpleButton())
					->setId('dataset-menu')
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass(ZBX_ICON_CHEVRON_DOWN_SMALL)
			);
	}

	public function getTemplates(): array {
		$value = ['color' => '#{color}'] + CWidgetFieldDataSet::getDefaults();

		return [
			new CTemplateTag('dataset-pattern-item-tmpl',
				$this->getGraphDataSetLayout($value, CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM, true)
			),
			new CTemplateTag('dataset-single-item-tmpl',
				$this->getGraphDataSetLayout($value, CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM, true)
			),
			new CTemplateTag('dataset-item-row-tmpl', $this->getItemRowTemplate())
		];
	}

	private function getGraphDataSetLayout(array $value, int $dataset_type, bool $is_opened,
			$row_num = '#{rowNum}'): CListItem {
		$field_name = $this->field->getName();

		$dataset_head = [
			new CDiv(
				(new CButtonIcon(ZBX_ICON_CHEVRON_UP))->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE)
			),
			new CVar($field_name.'['.$row_num.'][dataset_type]', $dataset_type, '')
		];

		$tags_html = [];

		if ($dataset_type == CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM) {
			$dataset_head[] = (new CColorPicker($field_name.'['.$row_num.'][color]',
				$field_name.'['.$row_num.'][color_palette]')
			)
				->setPalette($value['color_palette'] ?? null)
				->setColor($value['color'] ?? null);

			if ($this->field->isTemplateDashboard()) {
				foreach (['x_axis_items', 'y_axis_items'] as $key) {
					$item_pattern = (new CWidgetFieldPatternSelectItem($field_name.'['.$row_num.']['.$key.']',
						$key === 'x_axis_items' ? _('X-Axis') : _('Y-Axis'),
					))
						->setTemplateId($this->field->getTemplateId())
						->setValue($value[$key]);

					$item_pattern_view = (new CWidgetFieldPatternSelectItemView($item_pattern))
						->setPopupParameter('numeric', true)
						->setPlaceholder(_('item patterns'))
						->setFormName($this->form_name);

					$item_pattern_html = [];

					foreach ($item_pattern_view->getViewCollection() as
							['label' => $label, 'view' => $view, 'class' => $class]) {
						$item_pattern_html[] = $label;
						$item_pattern_html[] = (new CFormField(
							$view->addClass('js-'.($key === 'x_axis_items' ? 'x-items' : 'y-items').'-multiselect')
						))
							->addClass($class)
							->addClass('select-field-view');
					}

					$item_pattern_html[] = implode('', $item_pattern_view->getTemplates());
					$item_pattern_html[] = new CScriptTag($item_pattern_view->getJavaScript());

					$dataset_head[] = (new CDiv())
						->addClass('head-row')
						->addItem($item_pattern_html);
				}
			}
			else {
				// Host group
				$captions = [];

				if (array_key_exists('hostgroupids', $value)) {
					$captions = CWidgetFieldDataSet::getHostGroupCaptions($value['hostgroupids']);
				}

				$host_group_multiselect = (new CWidgetFieldMultiSelectGroup(
					$field_name.'['.$row_num.'][hostgroupids]', _('Host groups')
				))
					->acceptWidget()
					->setMultiple()
					->setValue($value['hostgroupids'])
					->setValuesCaptions($captions);

				$host_group_multiselect_view = (new CWidgetFieldMultiSelectGroupView($host_group_multiselect))
					->setFormName($this->form_name)
					->setWidth(null);

				$host_group_html = [];

				foreach ($host_group_multiselect_view->getViewCollection() as
						['label' => $label, 'view' => $view, 'class' => $class]) {
					$host_group_html[] = $label;
					$host_group_html[] = (new CFormField(
						$view->addClass('js-hostgroups-multiselect')
					))
						->addClass($class)
						->addClass('select-field-view');
				}

				$host_group_html[] = implode('', $host_group_multiselect_view->getTemplates());
				$host_group_html[] = new CScriptTag($host_group_multiselect_view->getJavaScript());

				// Host pattern
				$host_pattern_field = (new CWidgetFieldPatternSelectHost(
					$field_name.'['.$row_num.'][hosts]', _('Host patterns')
				))->setValue($value['hosts']);

				$host_pattern_view = (new CWidgetFieldPatternSelectHostView($host_pattern_field))
					->setFilterPreselect([
						'id' => $host_group_multiselect_view->getId(),
						'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
						'submit_as' => 'groupid'
					])
					->setPlaceholder(_('host patterns'))
					->setFormName($this->form_name);

				$host_pattern_html = [];

				foreach ($host_pattern_view->getViewCollection() as
						['label' => $label, 'view' => $view, 'class' => $class]) {
					$host_pattern_html[] = $label;
					$host_pattern_html[] = (new CFormField(
						$view->addClass('js-hosts-multiselect')
					))
						->addClass($class)
						->addClass('select-field-view');
				}

				$host_pattern_html[] = implode('', $host_pattern_view->getTemplates());
				$host_pattern_html[] = new CScriptTag($host_pattern_view->getJavaScript());

				// Item pattern
				foreach (['x_axis_items', 'y_axis_items'] as $key) {
					$item_pattern = (new CWidgetFieldPatternSelectItem($field_name.'['.$row_num.']['.$key.']',
						$key === 'x_axis_items' ? _('X-Axis') : _('Y-Axis'),
					))->setValue($value[$key]);

					$item_pattern_view = (new CWidgetFieldPatternSelectItemView($item_pattern))
						->setFilterPreselect([
							'id' => $host_pattern_view->getId(),
							'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
							'submit_as' => 'host_pattern'
						])
						->setPopupParameter('numeric', true)
						->setPlaceholder(_('item patterns'))
						->setFormName($this->form_name);

					$item_pattern_html = [];

					foreach ($item_pattern_view->getViewCollection() as
							['label' => $label, 'view' => $view, 'class' => $class]) {
						$item_pattern_html[] = $label;
						$item_pattern_html[] = (new CFormField(
							$view->addClass('js-'.($key === 'x_axis_items' ? 'x-items' : 'y-items').'-multiselect')
						))
							->addClass($class)
							->addClass('select-field-view');
					}

					$item_pattern_html[] = implode('', $item_pattern_view->getTemplates());
					$item_pattern_html[] = new CScriptTag($item_pattern_view->getJavaScript());

					$item_pattern_fields[$key] = $item_pattern_html;
				}

				$dataset_head[] = (new CDiv())
					->addClass('head-row')
					->addItem($host_pattern_html)
					->addItem($host_group_html);

				$dataset_head[] = (new CDiv())
					->addClass('head-row')
					->addItem($item_pattern_fields);

				// Host tags
				$tags_field = (new CWidgetFieldTags($field_name.'['.$row_num.'][host_tags]', _('Host tags')))
					->setValue($value['host_tags']);

				$tags_field_view = (new CWidgetFieldTagsView($tags_field))->setFormName($this->form_name);

				foreach ($tags_field_view->getViewCollection() as
						['label' => $label, 'view' => $view, 'class' => $class]) {
					$tags_html[] = $label;
					$tags_html[] = (new CFormField(
						(new CRadioButtonList($field_name.'['.$row_num.'][host_tags_evaltype]',
							(int) $value['host_tags_evaltype']
						))
							->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
							->addValue(_('Or'), TAG_EVAL_TYPE_OR)
							->setModern()
					));
					$tags_html[] = '';
					$tags_html[] = (new CFormField($view))
						->addClass($class)
						->addItem($tags_field_view->getTemplates())
						->addClass('host-tags');
				}

				$tags_html[] = new CScriptTag($tags_field_view->getJavaScript());
			}
		}
		else {
			$dataset_head[] = (new CColorPicker($field_name.'['.$row_num.'][color]'))
				->setColor($value['color'] ?? null);

			foreach (['x_axis_itemids' => 'x_axis_references', 'y_axis_itemids' => 'y_axis_references']
					as $key => $reference) {
				$item_rows = [];

				foreach ($value[$key] as $i => $item_spec) {
					if (is_array($item_spec)) {
						$itemid = '0';
						$item_reference = $item_spec[CWidgetField::FOREIGN_REFERENCE_KEY];
						$item_name = '';
					}
					else {
						$itemid = $item_spec;
						$item_reference = '';
						$item_name = array_key_exists($itemid, $value['item_names'])
							? $value['item_names'][$itemid] :
							'';
					}

					$item_rows[] = $this->getItemRowTemplate($row_num, ($i + 1), $itemid, $item_reference, $item_name,
						$key, $reference
					);
				}

				$empty_msg_block = (new CDiv(_('No item selected.')))->addClass('no-items-message');

				$items_list = (new CTable())
					->addClass('single-item-table')
					->setAttribute('data-key', $key)
					->setAttribute('data-set', $row_num)
					->setColumns([
						(new CTableColumn($key === 'x_axis_itemids' ? _('X-Axis') : _('Y-Axis')))
							->addClass('table-col-name'),
						new CTableColumn()
					])
					->addItem($item_rows)
					->addItem(
						(new CTag('tfoot', true,
							(new CCol(
								new CHorList([
									(new CButtonLink(_('Add item')))->addClass('js-add-item'),
									(new CButtonLink(_('Add widget')))->addClass('js-add-widget')
								])
							))
						))
					);

				$dataset_head[] = (new CDiv([$empty_msg_block, $items_list]))
					->addClass('items-list table-forms-separator');
			}
		}

		$dataset_head[] = (new CDiv(
			(new CButtonIcon(ZBX_ICON_REMOVE_SMALLER, _('Delete')))->addClass('js-dataset-remove')
		))->addClass('list-item-actions');

		$override_host_html = [];

		if (!$this->field->isTemplateDashboard()) {
			// Host override
			$override_host_field = (new CWidgetFieldMultiSelectOverrideHost(
				$field_name.'['.$row_num.'][override_hostid]'
			))->setValue($value['override_hostid']);

			$override_host_field_view = (new CWidgetFieldMultiSelectOverrideHostView($override_host_field))
				->setFormName($this->form_name)
				->setWidth(null);

			foreach ($override_host_field_view->getViewCollection() as
					['label' => $label, 'view' => $view, 'class' => $class]) {
				$override_host_html[] = $label;
				$override_host_html[] = (new CFormField($view))
					->addClass($class)
					->addClass('select-field-view');
			}

			$override_host_html[] = implode('', $override_host_field_view->getTemplates());
			$override_host_html[] = new CScriptTag($override_host_field_view->getJavaScript());
		}

		$marker_radio = (new CRadioButtonList($field_name.'['.$row_num.'][marker]', (int) $value['marker']))
			->setId($field_name.'_'.$row_num.'_marker')
			->setModern();

		foreach (CScatterPlotMetricPoint::MARKER_ICONS as $marker_type => $icon) {
			$marker_radio->addValue(new CIcon($icon), $marker_type);
		}

		return (new CListItem([
			(new CDiv())
				->addClass(ZBX_STYLE_DRAG_ICON)
				->addClass('js-main-drag-icon'),
			(new CLabel(''))->addClass('js-dataset-label'),
			(new CDiv())
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_HEAD)
				->addClass('dataset-head')
				->addItem($dataset_head),
			(new CDiv())
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_BODY)
				->addClass('dataset-body')
				->addItem([
					(new CFormGrid())
						->addItem($tags_html)
						->addItem($override_host_html),
					(new CFormGrid())
						->addItem([
							new CLabel(_('Marker'), $field_name.'_'.$row_num.'_marker'),
							(new CFormField([
								$marker_radio,
								(new CRadioButtonList($field_name.'['.$row_num.'][marker_size]',
									(int) $value['marker_size'])
								)
									->addValue(_('Small'), CWidgetFieldDataSet::DATASET_MARKER_SIZE_SMALL)
									->addValue(_('Medium'), CWidgetFieldDataSet::DATASET_MARKER_SIZE_MEDIUM)
									->addValue(_('Large'), CWidgetFieldDataSet::DATASET_MARKER_SIZE_LARGE)
									->setModern()
							]))->addClass('marker-config')
						])
						->addItem([
							new CLabel(_('Time shift'), $field_name.'['.$row_num.'][timeshift]'),
							new CFormField(
								(new CTextBox($field_name.'['.$row_num.'][timeshift]', $value['timeshift']))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
									->setAttribute('placeholder', _('none'))
							)
						])
						->addItem([
							new CLabel(_('Aggregation interval'), $field_name.'['.$row_num.'][aggregate_interval]'),
							new CFormField(
								(new CTextBox($field_name.'['.$row_num.'][aggregate_interval]',
									$value['aggregate_interval']
								))
									->setEnabled($value['aggregate_function'] != AGGREGATE_NONE)
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
									->setAttribute('placeholder', GRAPH_AGGREGATE_DEFAULT_INTERVAL)
							)
						])
						->addItem([
							new CLabel(_('Aggregation function'),
								'label-'.$field_name.'_'.$row_num.'_aggregate_function'
							),
							new CFormField(
								(new CSelect($field_name.'['.$row_num.'][aggregate_function]'))
									->setId($field_name.'_'.$row_num.'_aggregate_function')
									->setFocusableElementId('label-'.$field_name.'_'.$row_num.'_aggregate_function')
									->setValue((int) $value['aggregate_function'])
									->addOptions(CSelect::createOptionsFromArray([
										AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
										AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
										AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
										AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
										AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
										AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST),
										AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST)
									]))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
				])
		]))
			->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM)
			->addClass($is_opened ? ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED : ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED)
			->setAttribute('data-set', $row_num)
			->setAttribute('data-type', $dataset_type);
	}

	private function getItemRowTemplate($ds_num = '#{dsNum}', $row_num = '#{rowNum}', $itemid = '#{itemid}',
			$reference = '#{reference}', $name = '#{name}', $key = '#{key}', $ref_key = '#{key_reference}'): CRow {

		return (new CRow([
			(new CCol([
				(new CSpan())
					->addClass('reference-hint')
					->addClass(ZBX_ICON_REFERENCE)
					->addClass(ZBX_STYLE_DISPLAY_NONE)
					->setHint(_('Another widget is used as data source.')),
				(new CLink($name))
					->setId($key.'_'.$ds_num.'_'.$row_num.'_name')
					->addClass('js-click-expand')
			]))->addClass('table-col-name'),
			(new CCol([
				(new CButton('button', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-single-item-row-remove'),
				new CVar($this->field->getName().'['.$ds_num.']['.$key.'][]', $itemid,
					$key.'_'.$ds_num.'_'.$row_num.'_itemid'
				),
				new CVar($this->field->getName().'['.$ds_num.']['.$ref_key.'][]', $reference,
					$key.'_'.$ds_num.'_'.$row_num.'_reference'
				)
			]))
				->addClass('table-col-action')
				->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass('single-item-table-row');
	}
}
