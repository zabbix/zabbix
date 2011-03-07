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

	public function testFormHost_TemplateUnlink(){
		// Unlink a template from a host from host properties page
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->click('link=Templates');
		$this->ok('Template_Linux');
//		$this->ok('Unlink');
//		$this->ok('Unlink and clear');
		// probably should either click "button next to the template name" or
		// unlink[$templateid] (retrieved from the db)
		$this->button_click('unlink[10001]');
		$this->wait();
		$this->nok('Template_Linux');
//		$this->button_click('save');
//		$this->wait();
//		$this->assertTitle('Hosts');
//		$this->ok('Host updated');
// should check that items, triggers, graphs and applications are not linked to the template anymore
	}

	public function testFormHost_TemplateLink(){
		// Link a template to a host from host properties page
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->click('link=Zabbix server');
		$this->wait();
		$this->click('link=Templates');
// next one should be uncommented if template is actually unlinked in the previous step
//		$this->nok('Template_Linux');
//		$this->ok('Unlink');
//		$this->ok('Unlink and clear');
		$this->button_click('add');
		$this->wait();
		$this->dropdown_select_wait('groupid','Templates');
		// should use Template_Linux as above (by name or by id from the db)
		$this->checkbox_select("templates[10001]");
		$this->button_click('select');
		$this->wait();
		$this->ok('Template_Linux');

//		$this->button_click('save');
//		$this->wait();
//		$this->assertTitle('Hosts');
//		$this->ok('Host updated');
// should check that items, triggers, graphs and applications exist on the host and are linked to the template
// also that they have been updated, not deleted & recreated
	}
}
?>
