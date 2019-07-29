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


class CTriggersSchemaCreater implements CSchemaCreater {

	public function create() {
		return (new CIndexedArrayXmlTag('triggers'))
			->setSchema(
				(new CArrayXmlTag('trigger'))
					->setSchema(
						(new CStringXmlTag('expression'))->setRequired(),
						(new CStringXmlTag('name'))
							->setRequired()
							->setKey('description'),
						(new CStringXmlTag('correlation_mode'))
							->setDefaultValue(CXmlConstantValue::TRIGGER_DISABLED)
							->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_DISABLED)
							->addConstant(CXmlConstantName::TAG_VALUE, CXmlConstantValue::TRIGGER_TAG_VALUE),
						new CStringXmlTag('correlation_tag'),
						(new CIndexedArrayXmlTag('dependencies'))
							->setSchema(
								(new CArrayXmlTag('dependency'))
									->setSchema(
										(new CStringXmlTag('expression'))->setRequired(),
										(new CStringXmlTag('name'))
											->setRequired()
											->setKey('description'),
										new CStringXmlTag('recovery_expression')
									)
							),
						(new CStringXmlTag('description'))->setKey('comments'),
						(new CStringXmlTag('manual_close'))
							->setDefaultValue(CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
							->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
						(new CStringXmlTag('priority'))
							->setDefaultValue(CXmlConstantValue::NOT_CLASSIFIED)
							->addConstant(CXmlConstantName::NOT_CLASSIFIED, CXmlConstantValue::NOT_CLASSIFIED)
							->addConstant(CXmlConstantName::INFO, CXmlConstantValue::INFO)
							->addConstant(CXmlConstantName::WARNING, CXmlConstantValue::WARNING)
							->addConstant(CXmlConstantName::AVERAGE, CXmlConstantValue::AVERAGE)
							->addConstant(CXmlConstantName::HIGH, CXmlConstantValue::HIGH)
							->addConstant(CXmlConstantName::DISASTER, CXmlConstantValue::DISASTER),
						new CStringXmlTag('recovery_expression'),
						(new CStringXmlTag('recovery_mode'))
							->setDefaultValue(CXmlConstantValue::TRIGGER_EXPRESSION)
							->addConstant(CXmlConstantName::EXPRESSION, CXmlConstantValue::TRIGGER_EXPRESSION)
							->addConstant(CXmlConstantName::RECOVERY_EXPRESSION, CXmlConstantValue::TRIGGER_RECOVERY_EXPRESSION)
							->addConstant(CXmlConstantName::NONE, CXmlConstantValue::TRIGGER_NONE),
						(new CStringXmlTag('status'))
							->setDefaultValue(CXmlConstantValue::ENABLED)
							->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
							->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
						(new CIndexedArrayXmlTag('tags'))
							->setSchema(
								(new CArrayXmlTag('tag'))
									->setSchema(
										(new CStringXmlTag('tag'))->setRequired(),
										new CStringXmlTag('value')
									)
							),
						(new CStringXmlTag('type'))
							->setDefaultValue(CXmlConstantValue::SINGLE)
							->addConstant(CXmlConstantName::SINGLE, CXmlConstantValue::SINGLE)
							->addConstant(CXmlConstantName::MULTIPLE, CXmlConstantValue::MULTIPLE),
						new CStringXmlTag('url')
					)
			);
	}
}
