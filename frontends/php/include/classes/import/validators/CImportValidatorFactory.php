<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CImportValidatorFactory extends CRegistryFactory {

	public function __construct($format) {
		parent::__construct([
			'1.0' => function () use ($format) {
				return new C10XmlValidator($format);
			},
			'2.0' => function () use ($format) {
				return new C20XmlValidator($format);
			},
			'3.0' => function () use ($format) {
				return new C30XmlValidator($format);
			},
			'3.2' => function () use ($format) {
				return new C32XmlValidator($format);
			},
			'3.4' => function () use ($format) {
				return new C34XmlValidator($format);
			},
			'4.0' => function () use ($format) {
				return new C40XmlValidator($format);
			},
			'4.2' => function () use ($format) {
				return new C42XmlValidator($format);
			},
			'4.4' => function () use ($format) {
				return new C44XmlValidator($format);
			}
		]);
	}
}
