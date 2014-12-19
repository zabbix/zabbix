<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormTemplate extends CWebTest {
	public $template = "Test template";
	public $template_tmp = "Test template 2";

	public function testFormTemplate_Create() {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->zbxTestClickWait('form');
		$this->input_type('template_name', $this->template);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template added');
		$this->zbxTestTextPresent($this->template);
	}

	public function testFormTemplate_CreateLongTemplateName() {
// 64 character long template name
		$template="000000000011111111112222222222333333333344444444445555555555666";
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->zbxTestClickWait('form');
		$this->input_type('template_name', $template);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template added');
		$this->zbxTestTextPresent($template);
	}

	public function testFormTemplate_SimpleUpdate() {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->zbxTestClickWait('link=Template OS Linux');
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template updated');
		$this->zbxTestTextPresent($this->template);
	}

	public function testFormTemplate_UpdateTemplateName() {
		// Update template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->template);
		$this->input_type('template_name', $this->template_tmp);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template updated');
	}

	/**
	 * Adds two macros to an existing host.
	 */
	public function testFormTemplate_AddMacros() {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestClick('link='.$this->template_tmp);
		$this->waitForPageToLoad("30000");
		$this->tab_switch('Macros');
		$this->type("name=macros[0][macro]", '{$TEST_MACRO}');
		$this->type("name=macros[0][value]", "1");
		$this->zbxTestClick("//table[@id='tbl_macros']//input[@id='macro_add']");
		$this->verifyElementPresent("name=macros[1][macro]");
		$this->type("name=macros[1][macro]", '{$TEST_MACRO2}');
		$this->type("name=macros[1][value]", "2");
		$this->zbxTestClick('save');
		$this->waitForPageToLoad("30000");
		$this->zbxTestTextPresent("Template updated");
	}

	public function testFormTemplate_CreateExistingTemplateNoGroups() {
		// Attempt to create a template with a name that already exists and not add it to any groups
		// In future should also check these conditions individually
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('form');
		$this->input_type('template_name', 'Template OS Linux');
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('No groups for template');
		$this->assertEquals(1, DBcount("select * from hosts where host='Template OS Linux'"));
	}

	public function testFormTemplate_Delete() {

		// save the ID of the host
		$template = DBfetch(DBSelect("select hostid from hosts where host like '".$this->template_tmp."'"));

		$this->chooseOkOnNextConfirmation();
		// Delete template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->template_tmp);
		$this->zbxTestClick('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template deleted');

		// check if the macros have been deleted
		$macrosCount = DBcount("select * from hostmacro where hostid=".$template['hostid']);
		$this->assertEquals(0, $macrosCount, 'Template macros have not been deleted.');
	}

	public function testFormTemplate_CloneTemplate() {
		// Clone template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link=Template OS Linux');
		$this->zbxTestClickWait('clone');
		$this->input_type('template_name', $this->template_tmp);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template added');
	}

	public function testFormTemplate_DeleteClonedTemplate() {
		$this->chooseOkOnNextConfirmation();

		// Delete template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->template_tmp);
		$this->zbxTestClickWait('delete');
		$this->getConfirmation();
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template deleted');
	}

	public function testFormTemplate_FullCloneTemplate() {
		// Full clone template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link=Template OS Linux');
		$this->zbxTestClickWait('full_clone');
		$this->input_type('template_name', $this->template.'_fullclone');
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template added');
	}

	public function testFormTemplate_DeleteFullClonedTemplate() {
		$this->chooseOkOnNextConfirmation();

		// Delete full cloned template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->template.'_fullclone');
		$this->zbxTestClickWait('delete');
		$this->getConfirmation();
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template deleted');
	}
}
