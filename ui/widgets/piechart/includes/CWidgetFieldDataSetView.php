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


namespace Widgets\PieChart\Includes;

use CButton,
	CButtonIcon,
	CButtonLink,
	CCol,
	CColor,
	CDiv,
	CFormField,
	CFormGrid,
	CHorList,
	CItemHelper,
	CLabel,
	CLink,
	CList,
	CListItem,
	CPatternSelect,
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
	CVar,
	CWidgetFieldView;

use Zabbix\Widgets\CWidgetField;

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

		foreach (array_column($values, 'itemids') as $items_spec) {
			foreach ($items_spec as $item_spec) {
				if (!is_array($item_spec)) {
					$itemids[$item_spec] = true;
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
				$this->getDataSetLayout($value, $value['dataset_type'], $i == 0, $i)
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
				$this->getDataSetLayout($value, CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM, true)
			),
			new CTemplateTag('dataset-single-item-tmpl',
				$this->getDataSetLayout($value, CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM, true)
			),
			new CTemplateTag('dataset-item-row-tmpl', $this->getItemRowTemplate())
		];
	}

	private function getDataSetLayout(array $value, int $dataset_type, bool $is_opened,
			$row_num = '#{rowNum}'): CListItem {
		$field_name = $this->field->getName();

		$dataset_head = [
			new CDiv(
				(new CButtonIcon(ZBX_ICON_CHEVRON_UP))->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE)
			),
			new CVar($field_name.'['.$row_num.'][dataset_type]', $dataset_type, '')
		];

		if ($dataset_type == CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM) {
			if ($this->field->isTemplateDashboard()) {
				$host_pattern_field = null;

				$item_pattern_field = (new CPatternSelect([
					'name' => $field_name.'['.$row_num.'][items][]',
					'object_name' => 'items',
					'data' => $value['items'],
					'placeholder' => _('item patterns'),
					'wildcard_allowed' => 1,
					'popup' => [
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'name',
							'hostid' => $this->field->getTemplateId(),
							'hide_host_filter' => true,
							'numeric' => 1,
							'dstfrm' => $this->form_name,
							'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][items][]')
						]
					],
					'add_post_js' => false
				]))->addClass('js-items-multiselect');
			}
			else {
				$host_pattern_field = (new CPatternSelect([
					'name' => $field_name.'['.$row_num.'][hosts][]',
					'object_name' => 'hosts',
					'data' => $value['hosts'],
					'placeholder' => _('host patterns'),
					'wildcard_allowed' => 1,
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'host',
							'dstfrm' => $this->form_name,
							'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][hosts][]')
						]
					],
					'add_post_js' => false
				]))->addClass('js-hosts-multiselect');

				$item_pattern_field = (new CPatternSelect([
					'name' => $field_name.'['.$row_num.'][items][]',
					'object_name' => 'items',
					'data' => $value['items'],
					'placeholder' => _('item patterns'),
					'wildcard_allowed' => 1,
					'popup' => [
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'name',
							'real_hosts' => 1,
							'numeric' => 1,
							'resolve_macros' => 1,
							'dstfrm' => $this->form_name,
							'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][items][]')
						],
						'filter_preselect' => [
							'id' => $host_pattern_field->getId(),
							'submit_as' => 'host_pattern',
							'submit_parameters' => [
								'host_pattern_wildcard_allowed' => 1,
								'host_pattern_multiple' => 1
							],
							'multiple' => true
						]
					],
					'autosuggest' => [
						'filter_preselect' => [
							'id' => $host_pattern_field->getId(),
							'submit_as' => 'host_pattern',
							'submit_parameters' => [
								'host_pattern_wildcard_allowed' => 1,
								'host_pattern_multiple' => 1
							],
							'multiple' => true
						]
					],
					'add_post_js' => false
				]))->addClass('js-items-multiselect');
			}

			$dataset_head[] = (new CColor($field_name.'['.$row_num.'][color]', $value['color']))
				->appendColorPickerJs(false);

			if ($host_pattern_field !== null) {
				$dataset_head[] = $host_pattern_field;
				$dataset_head[] = new CScriptTag($host_pattern_field->getPostJS());
			}

			$dataset_head[] = $item_pattern_field;
			$dataset_head[] = new CScriptTag($item_pattern_field->getPostJS());
		}
		else {
			$item_rows = [];

			foreach ($value['itemids'] as $i => $item_spec) {
				if (is_array($item_spec)) {
					$itemid = '0';
					$item_reference = $item_spec[CWidgetField::FOREIGN_REFERENCE_KEY];
					$item_name = '';
				}
				else {
					$itemid = $item_spec;
					$item_reference = '';
					$item_name = array_key_exists($itemid, $value['item_names']) ? $value['item_names'][$itemid] : '';
				}

				$item_rows[] = $this->getItemRowTemplate($row_num, ($i + 1), $itemid, $item_reference, $item_name,
					$value['color'][$i], $value['type'][$i]
				);
			}

			$empty_msg_block = (new CDiv(_('No item selected.')))->addClass('no-items-message');

			$items_list = (new CTable())
				->addClass('single-item-table')
				->setAttribute('data-set', $row_num)
				->setColumns([
					(new CTableColumn())->addClass('table-col-handle'),
					(new CTableColumn())->addClass('table-col-color'),
					(new CTableColumn())->addClass('table-col-no'),
					(new CTableColumn(_('Name')))->addClass('table-col-name'),
					(new CTableColumn(_('Type')))->addClass('table-col-type'),
					(new CTableColumn(_('Action')))->addClass('table-col-action')
				])
				->addItem($item_rows)
				->addItem(
					(new CTag('tfoot', true,
						(new CCol(
							new CHorList([
								(new CButtonLink(_('Add item')))->addClass('js-add-item'),
								(new CButtonLink(_('Add widget')))->addClass('js-add-widget')
							])
						))->setColSpan(5)
					))
				);

			$dataset_head = array_merge($dataset_head, [
				(new CDiv([$empty_msg_block, $items_list]))->addClass('items-list table-forms-separator')
			]);
		}

		$dataset_head[] = (new CDiv(
			(new CButtonIcon(ZBX_ICON_REMOVE_SMALLER, _('Delete')))->addClass('js-remove')
		))->addClass('list-item-actions');

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
						->addItem([
							new CLabel([
								_('Aggregation function'),
								makeHelpIcon(_('Aggregates each item in the data set.'))
							], 'label-'.$field_name.'_'.$row_num.'_aggregate_function'),
							new CFormField(
								(new CSelect($field_name.'['.$row_num.'][aggregate_function]'))
									->setId($field_name.'_'.$row_num.'_aggregate_function')
									->setFocusableElementId('label-'.$field_name.'_'.$row_num.'_aggregate_function')
									->setValue((int) $value['aggregate_function'])
									->addOptions(CSelect::createOptionsFromArray([
										AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST),
										AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
										AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
										AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
										AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
										AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
										AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST)
									]))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
						->addItem([
							new CLabel([
								_('Data set aggregation'),
								makeHelpIcon(_('Aggregates the whole data set.'))
							], 'label-'.$field_name.'_'.$row_num.'_dataset_aggregation'),
							new CFormField(
								(new CSelect($field_name.'['.$row_num.'][dataset_aggregation]'))
									->setId($field_name.'_'.$row_num.'_dataset_aggregation')
									->setFocusableElementId('label-'.$field_name.'_'.$row_num.'_dataset_aggregation')
									->setValue((int) $value['dataset_aggregation'])
									->addOptions(CSelect::createOptionsFromArray([
										AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
										AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
										AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
										AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
										AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
										AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM)
									]))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
						->addItem([
							new CLabel([
								_('Data set label'),
								makeHelpIcon(_('Also used as legend label for aggregated data sets.'))
							], $field_name.'_'.$row_num.'_data_set_label'),
							new CFormField(
								(new CTextBox($field_name.'['.$row_num.'][data_set_label]', $value['data_set_label']))
									->setId($field_name.'_'.$row_num.'_data_set_label')
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
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
			$reference = '#{reference}', $name = '#{name}', $color = '#{color}', $type = '#{type}'): CRow {
		return (new CRow([
			(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))
				->addClass('table-col-handle')
				->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CCol(
				(new CColor($this->field->getName().'['.$ds_num.'][color][]', $color,
					'items_'.$ds_num.'_'.$row_num.'_color'
				))->appendColorPickerJs(false)
			))->addClass('table-col-color'),
			(new CCol(new CSpan($row_num.':')))->addClass('table-col-no'),
			(new CCol([
				(new CSpan())
					->addClass('reference-hint')
					->addClass(ZBX_ICON_REFERENCE)
					->addClass(ZBX_STYLE_DISPLAY_NONE)
					->setHint(_('Another widget is used as data source.')),
				(new CLink($name))
					->setId('items_'.$ds_num.'_'.$row_num.'_name')
					->addClass('js-click-expand')
			]))->addClass('table-col-name'),
			(new CCol([
				(new CSelect($this->field->getName().'['.$ds_num.'][type][]'))
					->setId('items_'.$ds_num.'_'.$row_num.'_type')
					->setValue($type)
					->addOptions(CSelect::createOptionsFromArray([
						CWidgetFieldDataSet::ITEM_TYPE_NORMAL => _('Normal'),
						CWidgetFieldDataSet::ITEM_TYPE_TOTAL => _('Total')
					]))
			]))->addClass('table-col-type'),
			(new CCol([
				(new CButton('button', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove'),
				new CVar($this->field->getName().'['.$ds_num.'][itemids][]', $itemid,
					'items_'.$ds_num.'_'.$row_num.'_itemid'
				),
				new CVar($this->field->getName().'['.$ds_num.'][references][]', $reference,
					'items_'.$ds_num.'_'.$row_num.'_reference'
				)
			]))
				->addClass('table-col-action')
				->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass('single-item-table-row');
	}
}
