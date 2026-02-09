<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CImportValidatorFactory extends CRegistryFactory {

	public function __construct(string $format) {
		parent::__construct([
			'1.0' => function() use ($format): CImportValidatorGeneral {
				return new C10ImportValidator($format);
			},
			'2.0' => function() use ($format): CImportValidatorGeneral {
				return new C20ImportValidator($format);
			},
			'3.0' => function() use ($format): CImportValidatorGeneral {
				return new C30ImportValidator($format);
			},
			'3.2' => function() use ($format): CImportValidatorGeneral {
				return new C32ImportValidator($format);
			},
			'3.4' => function() use ($format): CImportValidatorGeneral {
				return new C34ImportValidator($format);
			},
			'4.0' => function() use ($format): CImportValidatorGeneral {
				return new C40ImportValidator($format);
			},
			'4.2' => function() use ($format): CImportValidatorGeneral {
				return new C42ImportValidator($format);
			},
			'4.4' => function() use ($format): CImportValidatorGeneral {
				return new C44ImportValidator($format);
			},
			'5.0' => function() use ($format): CImportValidatorGeneral {
				return new C50ImportValidator($format);
			},
			'5.2' => function() use ($format): CImportValidatorGeneral {
				return new C52ImportValidator($format);
			},
			'5.4' => function() use ($format): CImportValidatorGeneral {
				return new C54ImportValidator($format);
			},
			'6.0' => function() use ($format): CImportValidatorGeneral {
				return new C60ImportValidator($format);
			},
			'6.2' => function() use ($format): CImportValidatorGeneral {
				return new C62ImportValidator($format);
			},
			'6.4' => function() use ($format): CImportValidatorGeneral {
				return new C64ImportValidator($format);
			},
			'7.0' => function() use ($format): CImportValidatorGeneral {
				return new C70ImportValidator($format);
			},
			'7.2' => function() use ($format): CImportValidatorGeneral {
				return new C72ImportValidator($format);
			},
			'7.4' => function() use ($format): CImportValidatorGeneral {
				return new C74ImportValidator($format);
			},
			'8.0' => function() use ($format): CImportValidatorGeneral {
				return new C80ImportValidator($format);
			}
		]);
	}
}
