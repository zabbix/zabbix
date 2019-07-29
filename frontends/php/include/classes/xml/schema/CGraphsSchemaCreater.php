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


class CGraphsSchemaCreater implements CSchemaCreater {

	public function create() {
		return (new CIndexedArrayXmlTag('graphs'))
			->setSchema(
				(new CArrayXmlTag('graph'))
					->setSchema(
						(new CStringXmlTag('name'))->setRequired(),
						(new CIndexedArrayXmlTag('graph_items'))
							->setRequired()
							->setKey('gitems')
							->setSchema(
								(new CArrayXmlTag('graph_item'))
									->setSchema(
										(new CArrayXmlTag('item'))
											->setRequired()
											->setKey('itemid')
											->setSchema(
												(new CStringXmlTag('host'))->setRequired(),
												(new CStringXmlTag('key'))->setRequired()
											),
										(new CStringXmlTag('calc_fnc'))
											->setDefaultValue(CXmlConstantValue::AVG)
											->addConstant(CXmlConstantName::MIN, CXmlConstantValue::MIN)
											->addConstant(CXmlConstantName::AVG, CXmlConstantValue::AVG)
											->addConstant(CXmlConstantName::MAX, CXmlConstantValue::MAX)
											->addConstant(CXmlConstantName::ALL, CXmlConstantValue::ALL)
											->addConstant(CXmlConstantName::LAST, CXmlConstantValue::LAST),
										new CStringXmlTag('color'),
										(new CStringXmlTag('drawtype'))
											->setDefaultValue(CXmlConstantValue::SINGLE_LINE)
											->addConstant(CXmlConstantName::SINGLE_LINE, CXmlConstantValue::SINGLE_LINE)
											->addConstant(CXmlConstantName::FILLED_REGION, CXmlConstantValue::FILLED_REGION)
											->addConstant(CXmlConstantName::BOLD_LINE, CXmlConstantValue::BOLD_LINE)
											->addConstant(CXmlConstantName::DOTTED_LINE, CXmlConstantValue::DOTTED_LINE)
											->addConstant(CXmlConstantName::DASHED_LINE, CXmlConstantValue::DASHED_LINE)
											->addConstant(CXmlConstantName::GRADIENT_LINE, CXmlConstantValue::GRADIENT_LINE),
										(new CStringXmlTag('sortorder'))
											->setDefaultValue('0'),
										(new CStringXmlTag('type'))
											->setDefaultValue(CXmlConstantValue::SIMPLE)
											->addConstant(CXmlConstantName::SIMPLE, CXmlConstantValue::SIMPLE)
											->addConstant(CXmlConstantName::GRAPH_SUM, CXmlConstantValue::GRAPH_SUM),
										(new CStringXmlTag('yaxisside'))
											->setDefaultValue(CXmlConstantValue::LEFT)
											->addConstant(CXmlConstantName::LEFT, CXmlConstantValue::LEFT)
											->addConstant(CXmlConstantName::RIGHT, CXmlConstantValue::RIGHT)
									)
							),
						(new CStringXmlTag('height'))->setDefaultValue('200'),
						(new CStringXmlTag('percent_left'))->setDefaultValue('0'),
						(new CStringXmlTag('percent_right'))->setDefaultValue('0'),
						(new CStringXmlTag('show_3d'))
							->setDefaultValue(CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('show_legend'))
							->setDefaultValue(CXmlConstantValue::YES)
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('show_triggers'))
							->setDefaultValue(CXmlConstantValue::YES)
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('show_work_period'))
							->setDefaultValue(CXmlConstantValue::YES)
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('type'))
							->setKey('graphtype')
							->setDefaultValue(CXmlConstantValue::NORMAL)
							->addConstant(CXmlConstantName::NORMAL, CXmlConstantValue::NORMAL)
							->addConstant(CXmlConstantName::STACKED, CXmlConstantValue::STACKED)
							->addConstant(CXmlConstantName::PIE, CXmlConstantValue::PIE)
							->addConstant(CXmlConstantName::EXPLODED, CXmlConstantValue::EXPLODED),
						(new CStringXmlTag('width'))->setDefaultValue('900'),
						(new CStringXmlTag('yaxismax'))->setDefaultValue('100'),
						(new CStringXmlTag('yaxismin'))->setDefaultValue('0'),
						(new CStringXmlTag('ymax_item_1'))->setKey('ymax_itemid'),
						(new CStringXmlTag('ymax_type_1'))
							->setKey('ymax_type')
							->setDefaultValue(CXmlConstantValue::CALCULATED)
							->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::CALCULATED)
							->addConstant(CXmlConstantName::FIXED, CXmlConstantValue::FIXED)
							->addConstant(CXmlConstantName::ITEM, CXmlConstantValue::ITEM),
						(new CStringXmlTag('ymin_item_1'))->setKey('ymin_itemid'),
						(new CStringXmlTag('ymin_type_1'))
							->setKey('ymin_type')
							->setDefaultValue(CXmlConstantValue::CALCULATED)
							->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::CALCULATED)
							->addConstant(CXmlConstantName::FIXED, CXmlConstantValue::FIXED)
							->addConstant(CXmlConstantName::ITEM, CXmlConstantValue::ITEM)
					)
			);
	}
}
