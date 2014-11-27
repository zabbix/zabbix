<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CImportConverterFactory extends CRegistryFactory {

	public function __construct(array $objects = array()) {
		parent::__construct(array_merge(array(
			'1.0' => function() {
				$itemKeyConverter = new C10ItemKeyConverter();
				return new C10ImportConverter($itemKeyConverter, new C10TriggerConverter($itemKeyConverter));
			},
			'2.0' => function() {
				return new C20ImportConverter(
					new C20TriggerConverter(new CFunctionMacroParser(), new CMacroParser('#'))
				);
			}
		), $objects));
	}
}
