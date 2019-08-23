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


class CGraphsSchemaCreator implements CSchemaCreator {

	public function create() {
		return (new CIndexedArrayXmlTag('graphs'))
			->setSchema(
				(new CArrayXmlTag('graph'))
					->setSchema(
						(new CStringXmlTag('name'))->setRequired(),
						(new CIndexedArrayXmlTag('graph_items'))
							->setRequired()
							->setSchema(
								(new CArrayXmlTag('graph_item'))
									->setRequired()
									->setSchema(
										(new CArrayXmlTag('item'))
											->setRequired()
											->setSchema(
												(new CStringXmlTag('host'))->setRequired(),
												(new CStringXmlTag('key'))->setRequired()
											),
										(new CStringXmlTag('calc_fnc'))
											->setDefaultValue(DB::getDefault('graphs_items', 'calc_fnc'))
											->addConstant(CXmlConstantName::MIN, CXmlConstantValue::MIN)
											->addConstant(CXmlConstantName::AVG, CXmlConstantValue::AVG)
											->addConstant(CXmlConstantName::MAX, CXmlConstantValue::MAX)
											->addConstant(CXmlConstantName::ALL, CXmlConstantValue::ALL)
											->addConstant(CXmlConstantName::LAST, CXmlConstantValue::LAST),
										new CStringXmlTag('color'),
										(new CStringXmlTag('drawtype'))
											->setDefaultValue(DB::getDefault('graphs_items', 'drawtype'))
											->addConstant(CXmlConstantName::SINGLE_LINE, CXmlConstantValue::SINGLE_LINE)
											->addConstant(CXmlConstantName::FILLED_REGION, CXmlConstantValue::FILLED_REGION)
											->addConstant(CXmlConstantName::BOLD_LINE, CXmlConstantValue::BOLD_LINE)
											->addConstant(CXmlConstantName::DOTTED_LINE, CXmlConstantValue::DOTTED_LINE)
											->addConstant(CXmlConstantName::DASHED_LINE, CXmlConstantValue::DASHED_LINE)
											->addConstant(CXmlConstantName::GRADIENT_LINE, CXmlConstantValue::GRADIENT_LINE),
										(new CStringXmlTag('sortorder'))
											->setDefaultValue(DB::getDefault('graphs_items', 'sortorder')),
										(new CStringXmlTag('type'))
											->setDefaultValue(DB::getDefault('graphs_items', 'type'))
											->addConstant(CXmlConstantName::SIMPLE, CXmlConstantValue::SIMPLE)
											->addConstant(CXmlConstantName::GRAPH_SUM, CXmlConstantValue::GRAPH_SUM),
										(new CStringXmlTag('yaxisside'))
											->setDefaultValue(DB::getDefault('graphs_items', 'yaxisside'))
											->addConstant(CXmlConstantName::LEFT, CXmlConstantValue::LEFT)
											->addConstant(CXmlConstantName::RIGHT, CXmlConstantValue::RIGHT)
									)
							),
						(new CStringXmlTag('height'))->setDefaultValue(DB::getDefault('graphs', 'height')),
						(new CStringXmlTag('percent_left'))->setDefaultValue(DB::getDefault('graphs', 'percent_left')),
						(new CStringXmlTag('percent_right'))->setDefaultValue(DB::getDefault('graphs', 'percent_right')),
						(new CStringXmlTag('show_3d'))
							->setDefaultValue(DB::getDefault('graphs', 'show_3d'))
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('show_legend'))
							->setDefaultValue(DB::getDefault('graphs', 'show_legend'))
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('show_triggers'))
							->setDefaultValue(DB::getDefault('graphs', 'show_triggers'))
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('show_work_period'))
							->setDefaultValue(DB::getDefault('graphs', 'show_work_period'))
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('type'))
							->setDefaultValue(DB::getDefault('graphs', 'graphtype'))
							->addConstant(CXmlConstantName::NORMAL, CXmlConstantValue::NORMAL)
							->addConstant(CXmlConstantName::STACKED, CXmlConstantValue::STACKED)
							->addConstant(CXmlConstantName::PIE, CXmlConstantValue::PIE)
							->addConstant(CXmlConstantName::EXPLODED, CXmlConstantValue::EXPLODED),
						(new CStringXmlTag('width'))->setDefaultValue(DB::getDefault('graphs', 'width')),
						(new CStringXmlTag('yaxismax'))->setDefaultValue(DB::getDefault('graphs', 'yaxismax')),
						(new CStringXmlTag('yaxismin'))->setDefaultValue(DB::getDefault('graphs', 'yaxismin')),
						(new CStringXmlTag('ymax_item_1'))
							->setDefaultValue('0')
							->setExportHandler(function(array $data, CXmlTagInterface $class) {
								if ($data['ymax_type_1'] == CXmlConstantValue::ITEM
										&& array_key_exists('ymax_item_1', $data)
										&& (!array_key_exists('host', $data['ymax_item_1'])
											|| !array_key_exists('key', $data['ymax_item_1']))) {
									throw new Exception(_s('Invalid tag "%1$s": %2$s.',
										'/zabbix_export/graphs/graph/ymax_item_1', _('an array is expected')
									));
								}

								return $data['ymax_item_1'];
							}),
						(new CStringXmlTag('ymax_type_1'))
							->setDefaultValue(DB::getDefault('graphs', 'ymax_type'))
							->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::CALCULATED)
							->addConstant(CXmlConstantName::FIXED, CXmlConstantValue::FIXED)
							->addConstant(CXmlConstantName::ITEM, CXmlConstantValue::ITEM),
						(new CStringXmlTag('ymin_item_1'))
							->setDefaultValue('0')
							->setExportHandler(function(array $data, CXmlTagInterface $class) {
								if ($data['ymin_type_1'] == CXmlConstantValue::ITEM
										&& array_key_exists('ymin_item_1', $data)
										&& (!array_key_exists('host', $data['ymin_item_1'])
											|| !array_key_exists('key', $data['ymin_item_1']))) {
									throw new Exception(_s('Invalid tag "%1$s": %2$s.',
										'/zabbix_export/graphs/graph/ymin_item_1', _('an array is expected')
									));
								}

								return $data['ymax_item_1'];
							}),
						(new CStringXmlTag('ymin_type_1'))
							->setDefaultValue(DB::getDefault('graphs', 'ymin_type'))
							->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::CALCULATED)
							->addConstant(CXmlConstantName::FIXED, CXmlConstantValue::FIXED)
							->addConstant(CXmlConstantName::ITEM, CXmlConstantValue::ITEM)
					)
			);
	}
}
