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


abstract class CXmlTagAbstract implements CXmlTag
{
	protected $tag;

	protected $schema = [];

	protected $data_sort;

	public function getSchema()
	{
		return $this->schema;
	}

	public function getTag()
	{
		return $this->tag;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		if ($this->data_sort) {
			CArrayHelper::sort($data, $this->data_sort);
		}

		return $data;
	}
}
