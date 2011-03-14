<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testFormHost extends CWebTest{

	// Returns all hosts
	public static function allHosts(){
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* @dataProvider allHosts
	*/

	public $host = "Test host";

	public function testFormHost_Create(){
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','Zabbix servers');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host',$this->host);
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host added');
		$this->ok($this->host);
	}

	public function testFormHost_CreateLongHostName(){
		$host="01234567890123456789012345678901234567890123456789012345678901234";
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','Zabbix servers');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host',$host);
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('ERROR');
	}

	public function testFormHost_SimpleUpdate(){
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','Zabbix servers');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host updated');
		$this->ok($this->host);
	}

	public function testFormHost_UpdateHostName(){
		// Update Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link='.$this->host);
		$this->wait();
		$this->input_type('host',$this->host.'2');
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host updated');
	}

	public function testFormHost_CreateExistingHostNoGroups(){
		// Attempt to create a host with a name that already exists and not add it to any groups
		// In future should also check these conditions individually
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host','Zabbix server');
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('No groups for host');
		$this->assertEquals(1,DBcount("select * from hosts where host='Zabbix server'"));
	}

	public function testFormHost_Delete(){
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link='.$this->host.'2');
		$this->wait();
		$this->button_click('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host deleted');
	}

	public function testFormHost_CloneHost(){
		// Clone Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->button_click('clone');
		$this->wait();
		$this->input_type('host',$this->host.'2');
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host added');
	}

	public function testFormHost_DeleteClonedHost(){
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link='.$this->host.'2');
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->assertTitle('Hosts');
		$this->ok('Host deleted');
	}

	public function testFormHost_FullCloneHost(){
		// Full clone Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->button_click('full_clone');
		$this->wait();
		$this->input_type('host',$this->host.'_fullclone');
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host added');
	}

	public function testFormHost_DeleteFullClonedHost(){
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link='.$this->host.'_fullclone');
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->assertTitle('Hosts');
		$this->ok('Host deleted');
	}

//	public function testFormHost_TemplateUnlinkAndClear(){
		// WARNING: not tested yet
		// clicks button named "Unlink and clear" next to template named $template
//		$this->click("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']/../input[@value='Unlink and clear']");
//	}

	public function testFormHost_TemplateUnlink(){
		// Unlink a template from a host from host properties page

		$template = "Template_Linux";

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->tab_switch("Templates");
		$this->ok("$template");
		// clicks button named "Unlink" next to a template by name
		$this->click("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']");

		$this->wait();
		$this->nok("$template");
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host updated');

		// this should be a separate test
		// should check that items, triggers, graphs and applications are not linked to the template anymore
		$this->href_click("items.php?filter_set=1&hostid=10017&sid=");
		$this->wait();
		$this->nok("$template");
		// using "host navigation bar" at the top of entity list
		$this->href_click("triggers.php?hostid=10017&sid=");
		$this->wait();
		$this->nok("$template");
		$this->href_click("graphs.php?hostid=10017&sid=");
		$this->wait();
		$this->nok("$template");
		$this->href_click("applications.php?hostid=10017&sid=");
		$this->wait();
		$this->nok("$template");
	}

	public function testFormHost_TemplateLink(){
		// Link a template to a host from host properties page
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->tab_switch("Templates");
		$this->nok('Template_Linux');

		$template = "Template_Linux";

		// adds template $template to the list of linked template list
		// for now, ignores the fact that template might be already linked
//		$this->button_click('add');
		// the above does not seem to work, thus this ugly method has to be used - at least until buttons get unique names...
		$this->click("//input[@id='add' and @name='add' and @value='Add' and @type='button' and contains(@onclick, 'return PopUp')]");

		// zbx_popup is the default opened window id if none is passed
		$this->waitForPopUp('zbx_popup',6000);
		$this->selectWindow('zbx_popup');
		$this->checkFatalErrors();

		$this->dropdown_select_wait('groupid','Templates');
		$this->check("//input[@value='$template' and @type='checkbox']");
		$this->button_click('select');

		$this->selectWindow();
		$this->wait();

		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Host updated');
		// no entities should be deleted, they all should be updated
		$this->nok('deleted');
		$this->nok('created');
// should check that items, triggers, graphs and applications exist on the host and are linked to the template
// currently doing something very brutal - just looking whether Template_Linux is present on entity pages

		$this->href_click("items.php?filter_set=1&hostid=10017&sid=");
		$this->wait();
		$this->ok("$template:");
		// using "host navigation bar" at the top of entity list
		$this->href_click("triggers.php?hostid=10017&sid=");
		$this->wait();
		$this->ok("$template:");
//		default data.sql has a problem - graphs are not present in the template
//		$this->href_click("graphs.php?hostid=10017&sid=");
//		$this->wait();
//		$this->ok("$template:");
		$this->href_click("applications.php?hostid=10017&sid=");
		$this->wait();
		$this->ok("$template:");

		// tests that items that should have interfaceid don't have it set to NULL
		// checks all items on enabled and disabled hosts (types 0 and 1) except:
		// ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_CALCULATED, ITEM_TYPE_HTTPTEST
		// if any found, something's wrong
		$this->assertEquals(0,DBcount("select itemid from items left join hosts on items.hostid=hosts.hostid where hosts.status in (0,1) and interfaceid is NULL and type not in (2,5,7,8,9,15);"),"Chuck Norris: There are items with interfaceid NULL not of types 2, 5, 7, 8, 9, 15");

	}
}
?>
