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


class CXmlSchemaBuilder {

	public function build(CXmlTagInterface $class) {
		$result = [];
		$sub_tags = $class->getSubTags();

		if (count($sub_tags) === 0) {
			throw new CXmlSchemaBuilderException(_s('Tag "%1$s" schema is empty.', $class->getTag()));
		}

		foreach ($sub_tags as $tag) {
			$result[$tag->getTag()] = $tag;
		}

		return $class instanceof CIndexedArrayXmlTagInterface
			? $result
			: [$class->getTag() => $result];
	}

	public function getFullSchema() {
		return [
			(new CGraphsSchemaCreater)->create(),
			(new CGroupsSchemaCreater)->create(),
			(new CHostsSchemaCreater)->create(),
			(new CValueMapsSchemaCreater)->create(),
			(new CTemplatesSchemaCreater)->create(),
			(new CTriggersSchemaCreater)->create()
		];
	}
}
