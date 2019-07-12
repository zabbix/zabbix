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
					->addConstant('MIN', CXmlDefine::MIN)
					->addConstant('AVG', CXmlDefine::AVG)
					->addConstant('MAX', CXmlDefine::MAX)
					->addConstant('ALL', CXmlDefine::ALL)
					->addConstant('LAST', CXmlDefine::LAST),
				(new CXmlTagString('color')),
				(new CXmlTagString('drawtype'))
					->setDefaultValue(CXmlDefine::SINGLE_LINE)
					->addConstant('SINGLE_LINE', CXmlDefine::SINGLE_LINE)
					->addConstant('FILLED_REGION', CXmlDefine::FILLED_REGION)
					->addConstant('BOLD_LINE', CXmlDefine::BOLD_LINE)
					->addConstant('DOTTED_LINE', CXmlDefine::DOTTED_LINE)
					->addConstant('DASHED_LINE', CXmlDefine::DASHED_LINE)
					->addConstant('GRADIENT_LINE', CXmlDefine::GRADIENT_LINE),
				(new CXmlTagString('sortorder'))
					->setDefaultValue('0'),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::SIMPLE)
					->addConstant('SIMPLE', CXmlDefine::SIMPLE)
					->addConstant('GRAPH_SUM', CXmlDefine::GRAPH_SUM),
				(new CXmlTagString('yaxisside'))
					->setDefaultValue(CXmlDefine::LEFT)
					->addConstant('LEFT', CXmlDefine::LEFT)
					->addConstant('RIGHT', CXmlDefine::RIGHT)
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
			->addConstant('NO', CXmlDefine::NO)
			->addConstant('YES', CXmlDefine::YES),
		(new CXmlTagString('show_legend'))
			->setDefaultValue(CXmlDefine::YES)
			->addConstant('NO', CXmlDefine::NO)
			->addConstant('YES', CXmlDefine::YES),
		(new CXmlTagString('show_triggers'))
			->setDefaultValue(CXmlDefine::YES)
			->addConstant('NO', CXmlDefine::NO)
			->addConstant('YES', CXmlDefine::YES),
		(new CXmlTagString('show_work_period'))
			->setDefaultValue(CXmlDefine::YES)
			->addConstant('NO', CXmlDefine::NO)
			->addConstant('YES', CXmlDefine::YES),
		(new CXmlTagString('type'))->setKey('graphtype')
			->setDefaultValue(CXmlDefine::NORMAL)
			->addConstant('NORMAL', CXmlDefine::NORMAL)
			->addConstant('STACKED', CXmlDefine::STACKED)
			->addConstant('PIE', CXmlDefine::PIE)
			->addConstant('EXPLODED', CXmlDefine::EXPLODED),
		(new CXmlTagString('width'))
			->setDefaultValue('900'),
		(new CXmlTagString('yaxismax'))
			->setDefaultValue('100'),
		(new CXmlTagString('yaxismin'))
			->setDefaultValue('0'),
		(new CXmlTagString('ymax_item_1'))->setKey('ymax_itemid'),
		(new CXmlTagString('ymax_type_1'))->setKey('ymax_type')
			->setDefaultValue(CXmlDefine::CALCULATED)
			->addConstant('CALCULATED', CXmlDefine::CALCULATED)
			->addConstant('FIXED', CXmlDefine::FIXED)
			->addConstant('ITEM', CXmlDefine::ITEM),
		(new CXmlTagString('ymin_item_1'))->setKey('ymin_itemid'),
		(new CXmlTagString('ymin_type_1'))->setKey('ymin_type')
			->setDefaultValue(CXmlDefine::CALCULATED)
			->addConstant('CALCULATED', CXmlDefine::CALCULATED)
			->addConstant('FIXED', CXmlDefine::FIXED)
			->addConstant('ITEM', CXmlDefine::ITEM)
	)
);
