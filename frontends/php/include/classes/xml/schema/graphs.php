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


return (new CXmlTagIndexedArray('graphs'))->setSchema(
	(new CXmlTagArray('graph'))->setSchema(
		(new CXmlTagString('name'))->setRequired(),
		(new CXmlTagIndexedArray('graph_items'))->setRequired()->setKey('gitems')->setSchema(
			(new CXmlTagArray('graph_item'))->setSchema(
				(new CXmlTagArray('item'))->setRequired()->setKey('itemid')->setSchema(
					(new CXmlTagString('host'))->setRequired(),
					(new CXmlTagString('key'))->setRequired()
				),
				(new CXmlTagString('calc_fnc'))
					->setDefaultValue(CXmlDefine::AVG)
					->addConstant(CXmlConstant::MIN, CXmlDefine::MIN)
					->addConstant(CXmlConstant::AVG, CXmlDefine::AVG)
					->addConstant(CXmlConstant::MAX, CXmlDefine::MAX)
					->addConstant(CXmlConstant::ALL, CXmlDefine::ALL)
					->addConstant(CXmlConstant::LAST, CXmlDefine::LAST),
				new CXmlTagString('color'),
				(new CXmlTagString('drawtype'))
					->setDefaultValue(CXmlDefine::SINGLE_LINE)
					->addConstant(CXmlConstant::SINGLE_LINE, CXmlDefine::SINGLE_LINE)
					->addConstant(CXmlConstant::FILLED_REGION, CXmlDefine::FILLED_REGION)
					->addConstant(CXmlConstant::BOLD_LINE, CXmlDefine::BOLD_LINE)
					->addConstant(CXmlConstant::DOTTED_LINE, CXmlDefine::DOTTED_LINE)
					->addConstant(CXmlConstant::DASHED_LINE, CXmlDefine::DASHED_LINE)
					->addConstant(CXmlConstant::GRADIENT_LINE, CXmlDefine::GRADIENT_LINE),
				(new CXmlTagString('sortorder'))
					->setDefaultValue('0'),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::SIMPLE)
					->addConstant(CXmlConstant::SIMPLE, CXmlDefine::SIMPLE)
					->addConstant(CXmlConstant::GRAPH_SUM, CXmlDefine::GRAPH_SUM),
				(new CXmlTagString('yaxisside'))
					->setDefaultValue(CXmlDefine::LEFT)
					->addConstant(CXmlConstant::LEFT, CXmlDefine::LEFT)
					->addConstant(CXmlConstant::RIGHT, CXmlDefine::RIGHT)
			)
		),
		(new CXmlTagString('height'))
			->setDefaultValue('200'),
		(new CXmlTagString('percent_left'))
			->setDefaultValue('0'),
		(new CXmlTagString('percent_right'))
			->setDefaultValue('0'),
		(new CXmlTagString('show_3d'))
			->setDefaultValue(CXmlDefine::NO)
			->addConstant(CXmlConstant::NO, CXmlDefine::NO)
			->addConstant(CXmlConstant::YES, CXmlDefine::YES),
		(new CXmlTagString('show_legend'))
			->setDefaultValue(CXmlDefine::YES)
			->addConstant(CXmlConstant::NO, CXmlDefine::NO)
			->addConstant(CXmlConstant::YES, CXmlDefine::YES),
		(new CXmlTagString('show_triggers'))
			->setDefaultValue(CXmlDefine::YES)
			->addConstant(CXmlConstant::NO, CXmlDefine::NO)
			->addConstant(CXmlConstant::YES, CXmlDefine::YES),
		(new CXmlTagString('show_work_period'))
			->setDefaultValue(CXmlDefine::YES)
			->addConstant(CXmlConstant::NO, CXmlDefine::NO)
			->addConstant(CXmlConstant::YES, CXmlDefine::YES),
		(new CXmlTagString('type'))->setKey('graphtype')
			->setDefaultValue(CXmlDefine::NORMAL)
			->addConstant(CXmlConstant::NORMAL, CXmlDefine::NORMAL)
			->addConstant(CXmlConstant::STACKED, CXmlDefine::STACKED)
			->addConstant(CXmlConstant::PIE, CXmlDefine::PIE)
			->addConstant(CXmlConstant::EXPLODED, CXmlDefine::EXPLODED),
		(new CXmlTagString('width'))
			->setDefaultValue('900'),
		(new CXmlTagString('yaxismax'))
			->setDefaultValue('100'),
		(new CXmlTagString('yaxismin'))
			->setDefaultValue('0'),
		(new CXmlTagString('ymax_item_1'))->setKey('ymax_itemid'),
		(new CXmlTagString('ymax_type_1'))->setKey('ymax_type')
			->setDefaultValue(CXmlDefine::CALCULATED)
			->addConstant(CXmlConstant::CALCULATED, CXmlDefine::CALCULATED)
			->addConstant(CXmlConstant::FIXED, CXmlDefine::FIXED)
			->addConstant(CXmlConstant::ITEM, CXmlDefine::ITEM),
		(new CXmlTagString('ymin_item_1'))->setKey('ymin_itemid'),
		(new CXmlTagString('ymin_type_1'))->setKey('ymin_type')
			->setDefaultValue(CXmlDefine::CALCULATED)
			->addConstant(CXmlConstant::CALCULATED, CXmlDefine::CALCULATED)
			->addConstant(CXmlConstant::FIXED, CXmlDefine::FIXED)
			->addConstant(CXmlConstant::ITEM, CXmlDefine::ITEM)
	)
);
