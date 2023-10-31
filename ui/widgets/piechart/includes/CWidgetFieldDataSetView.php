<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\PieChart\Includes;

use CButton,
	CButtonIcon,
	CButtonLink,
	CCol,
	CColor,
	CDiv,
	CFormField,
	CFormGrid,
	CLabel,
	CLink,
	CList,
	CListItem,
	CPatternSelect,
	CRow,
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

class CWidgetFieldDataSetView extends CWidgetFieldView {

	public function __construct(CWidgetFieldDataSet $field) {
		$this->field = $field;
	}

	public function getView(): CList {
		$view = (new CList())
			->setId('data_sets')
			->addClass(ZBX_STYLE_SORTABLE_LIST);

		$values = $this->field->getValue();

		if (!$values) {
			$values[] = CWidgetFieldDataSet::getDefaults();
		}

		$itemids = array_merge(...array_column($values, 'itemids'));
		$item_names = [];
		if ($itemids) {
			$item_names = CWidgetFieldDataSet::getItemNames($itemids);
		}

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
					'placeholder' => _('item pattern'),
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
					'placeholder' => _('host pattern'),
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
					'placeholder' => _('item pattern'),
					'wildcard_allowed' => 1,
					'popup' => [
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'name',
							'real_hosts' => 1,
							'numeric' => 1,
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

			$dataset_head = array_merge($dataset_head, [
				(new CColor($field_name.'['.$row_num.'][color]', $value['color']))->appendColorPickerJs(false),
				$host_pattern_field,
				$item_pattern_field
			]);
		}
		else {
			$item_rows = [];
			foreach ($value['itemids'] as $i => $itemid) {
				$item_name = array_key_exists($itemid, $value['item_names'])
					? $value['item_names'][$itemid]
					: '';

				$item_rows[] = $this->getItemRowTemplate($row_num, $i + 1, $itemid, $item_name, $value['color'][$i],
					$value['type'][$i]
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
				->addItem([
					$item_rows,
					(new CTag('tfoot', true))
						->addItem(
							(new CCol(
								(new CList())
									->addClass(ZBX_STYLE_INLINE_FILTER_FOOTER)
									->addItem(
										(new CButtonLink(_('Add')))->addClass('js-add')
									)
							))->setColSpan(5)
						)
				]);

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
				->addClass(ZBX_STYLE_SORTABLE_DRAG_HANDLE)
				->addClass('js-main-drag-icon'),
			(new CLabel(''))
				->addClass(ZBX_STYLE_SORTABLE_DRAG_HANDLE)
				->addClass('js-dataset-label'),
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
										AGGREGATE_LAST => $this->aggr_fnc2str(AGGREGATE_LAST),
										AGGREGATE_MIN => $this->aggr_fnc2str(AGGREGATE_MIN),
										AGGREGATE_MAX => $this->aggr_fnc2str(AGGREGATE_MAX),
										AGGREGATE_AVG => $this->aggr_fnc2str(AGGREGATE_AVG),
										AGGREGATE_COUNT => $this->aggr_fnc2str(AGGREGATE_COUNT),
										AGGREGATE_SUM => $this->aggr_fnc2str(AGGREGATE_SUM),
										AGGREGATE_FIRST => $this->aggr_fnc2str(AGGREGATE_FIRST)
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
										AGGREGATE_NONE => $this->aggr_fnc2str(AGGREGATE_NONE),
										AGGREGATE_MIN => $this->aggr_fnc2str(AGGREGATE_MIN),
										AGGREGATE_MAX => $this->aggr_fnc2str(AGGREGATE_MAX),
										AGGREGATE_AVG => $this->aggr_fnc2str(AGGREGATE_AVG),
										AGGREGATE_COUNT => $this->aggr_fnc2str(AGGREGATE_COUNT),
										AGGREGATE_SUM => $this->aggr_fnc2str(AGGREGATE_SUM)
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
			->addClass(ZBX_STYLE_SORTABLE_ITEM)
			->addClass($is_opened ? ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED : ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED)
			->setAttribute('data-set', $row_num)
			->setAttribute('data-type', $dataset_type);
	}

	private function getItemRowTemplate($ds_num = '#{dsNum}', $row_num = '#{rowNum}', $itemid = '#{itemid}',
			$name = '#{name}', $color = '#{color}', $type = '#{type}'): CRow {
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
			(new CCol(
				(new CLink($name))
					->setId('items_'.$ds_num.'_'.$row_num.'_name')
					->addClass('js-click-expend')
			))->addClass('table-col-name'),
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
					'items_'.$ds_num.'_'.$row_num.'_input'
				)
			]))
				->addClass('table-col-action')
				->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass(ZBX_STYLE_SORTABLE)
			->addClass('single-item-table-row');
	}

	private function aggr_fnc2str($function) {
		switch ($function) {
			case AGGREGATE_NONE:
				return _('none');
			case AGGREGATE_MIN:
				return _('min');
			case AGGREGATE_MAX:
				return _('max');
			case AGGREGATE_AVG:
				return _('avg');
			case AGGREGATE_COUNT:
				return _('count');
			case AGGREGATE_SUM:
				return _('sum');
			case AGGREGATE_FIRST:
				return _('first');
			case AGGREGATE_LAST:
				return _('last');
		}
	}
}
