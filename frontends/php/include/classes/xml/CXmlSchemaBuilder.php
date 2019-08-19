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
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $class->getTag(), _('empty schema')));
		}

		foreach ($sub_tags as $tag) {
			$result[$tag->getTag()] = $tag;
		}

		return $class instanceof CIndexedArrayXmlTagInterface ? $result : [$class->getTag() => $result];
	}

	public function getFullSchema() {
		return [
			(new CGraphsSchemaCreator)->create(),
			(new CGroupsSchemaCreator)->create(),
			(new CHostsSchemaCreator)->create(),
			(new CValueMapsSchemaCreator)->create(),
			(new CTemplatesSchemaCreator)->create(),
			(new CTriggersSchemaCreator)->create()
		];
	}
}
