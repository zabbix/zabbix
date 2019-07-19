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


return (new CXmlTagIndexedArray('triggers'))->setSchema(
	(new CXmlTagArray('trigger'))->setSchema(
		(new CXmlTagString('expression'))->setRequired(),
		(new CXmlTagString('name'))->setRequired()->setKey('description'),
		(new CXmlTagString('correlation_mode'))
			->setDefaultValue(CXmlDefine::TRIGGER_DISABLED)
			->addConstant(CXmlConstant::DISABLED, CXmlDefine::TRIGGER_DISABLED)
			->addConstant(CXmlConstant::TAG_VALUE, CXmlDefine::TRIGGER_TAG_VALUE),
		new CXmlTagString('correlation_tag'),
		(new CXmlTagIndexedArray('dependencies'))->setSchema(
			(new CXmlTagArray('dependency'))->setSchema(
				(new CXmlTagString('expression'))->setRequired(),
				(new CXmlTagString('name'))->setRequired()->setKey('description'),
				new CXmlTagString('recovery_expression')
			)
		),
		(new CXmlTagString('description'))->setKey('comments'),
		(new CXmlTagString('manual_close'))
			->setDefaultValue(CXmlDefine::NO)
			->addConstant(CXmlConstant::NO, CXmlDefine::NO)
			->addConstant(CXmlConstant::YES, CXmlDefine::YES),
		(new CXmlTagString('priority'))
			->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
			->addConstant(CXmlConstant::NOT_CLASSIFIED, CXmlDefine::NOT_CLASSIFIED)
			->addConstant(CXmlConstant::INFO, CXmlDefine::INFO)
			->addConstant(CXmlConstant::WARNING, CXmlDefine::WARNING)
			->addConstant(CXmlConstant::AVERAGE, CXmlDefine::AVERAGE)
			->addConstant(CXmlConstant::HIGH, CXmlDefine::HIGH)
			->addConstant(CXmlConstant::DISASTER, CXmlDefine::DISASTER),
		new CXmlTagString('recovery_expression'),
		(new CXmlTagString('recovery_mode'))
			->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
			->addConstant(CXmlConstant::EXPRESSION, CXmlDefine::TRIGGER_EXPRESSION)
			->addConstant(CXmlConstant::RECOVERY_EXPRESSION, CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
			->addConstant(CXmlConstant::NONE, CXmlDefine::TRIGGER_NONE),
		(new CXmlTagString('status'))
			->setDefaultValue(CXmlDefine::ENABLED)
			->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
			->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
		(new CXmlTagIndexedArray('tags'))->setSchema(
			(new CXmlTagArray('tag'))->setSchema(
				(new CXmlTagString('tag'))->setRequired(),
				new CXmlTagString('value')
			)
		),
		(new CXmlTagString('type'))
			->setDefaultValue(CXmlDefine::SINGLE)
			->addConstant(CXmlConstant::SINGLE, CXmlDefine::SINGLE)
			->addConstant(CXmlConstant::MULTIPLE, CXmlDefine::MULTIPLE),
		new CXmlTagString('url')
	)
);
