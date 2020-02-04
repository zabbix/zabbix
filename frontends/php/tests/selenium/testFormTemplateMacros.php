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

require_once dirname(__FILE__) . '/common/testFormMacros.php';

/**
 * @backup hosts
 */
class testFormTemplateMacros extends testFormMacros {

	use MacrosTrait;

	/**
	 * The name of the template for updating macros, id=40000.
	 *
	 * @var string
	 */
	protected $template_name_update = 'Form test template';

	/**
	 * The name of the template for removing macros, id=99016.
	 *
	 * @var string
	 */
	protected $template_name_remove = 'Template to test graphs';

	/**
	 * @dataProvider getCreateCommonMacrosData
	 */
	public function testFormTemplateMacros_Create($data) {
		$this->checkCreate($data, 'template');
	}

	/**
	 * @dataProvider getUpdateCommonMacrosData
	 */
	public function testFormTemplateMacros_Update($data) {
		$this->checkUpdate($data, $this->template_name_update, 'template');
	}

	public function testFormTemplateMacros_Remove() {
		$this->checkRemove($this->template_name_remove, 'template');
	}

	public function testFormTemplateMacros_ChangeRemoveInheritedMacro() {
		$this->checkChangeRemoveInheritedMacro('template');
	}
}
