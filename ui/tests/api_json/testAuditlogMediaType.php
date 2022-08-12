<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup media_type, media_type_message, media_type_param, ids
 */
class testAuditlogMediaType extends CAPITest {

	protected static $resourceid;

	public function testAuditlogMediaType_Create() {
		$created = "{\"mediatype.name\":[\"add\",\"email_media\"],\"mediatype.smtp_server\":[\"add\",\"test.test.com".
				"\"],\"mediatype.smtp_helo\":[\"add\",\"test.com\"],\"mediatype.smtp_email\":[\"add\",\"test@test.com".
				"\"],\"mediatype.smtp_port\":[\"add\",\"587\"],\"mediatype.message_templates[160]\":[\"add\"],".
				"\"mediatype.message_templates[160].eventsource\":[\"add\",\"0\"],\"mediatype.message_templates".
				"[160].recovery\":[\"add\",\"0\"],\"mediatype.message_templates[160].subject\":[\"add\",".
				"\"Subject message\"],\"mediatype.message_templates[160].message\":[\"add\",\"Main message\"],".
				"\"mediatype.message_templates[160].mediatype_messageid\":[\"add\",\"160\"],\"mediatype.maxsessions".
				"\":[\"add\",\"50\"],\"mediatype.maxattempts\":[\"add\",\"5\"],\"mediatype.attempt_interval\":[\"add".
				"\",\"50s\"],\"mediatype.mediatypeid\":[\"add\",\"34\"]}";

		$create = $this->call('mediatype.create', [
			[
				'type' => '0',
				'name' => 'email_media',
				'smtp_server' => 'test.test.com',
				'smtp_helo' => 'test.com',
				'smtp_email' => 'test@test.com',
				'smtp_port' => '587',
				'content_type' => '1',
				'message_templates' => [
					[
						'eventsource' => '0',
						'recovery' => '0',
						'subject' => 'Subject message',
						'message' => 'Main message'
					]
				],
				'maxsessions' => 50,
				'maxattempts' => 5,
				'attempt_interval' => '50s'
			]
		]);

		self::$resourceid = $create['result']['mediatypeids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogMediaType_Update() {
		$updated = "{\"mediatype.message_templates[160]\":[\"delete\"],\"mediatype.message_templates[161]\":[\"add\"],".
				"\"mediatype.status\":[\"update\",\"1\",\"0\"],\"mediatype.name\":[\"update\",\"updated_email_media\",".
				"\"email_media\"],\"mediatype.smtp_server\":[\"update\",\"updated_test.test.com\",\"test.test.com\"],".
				"\"mediatype.smtp_helo\":[\"update\",\"updated_test.com\",\"test.com\"],\"mediatype.smtp_email\":[".
				"\"update\",\"updated_test@test.com\",\"test@test.com\"],\"mediatype.smtp_port\":[\"update\",\"589\",".
				"\"587\"],\"mediatype.content_type\":[\"update\",\"0\",\"1\"],\"mediatype.message_templates".
				"[161].eventsource\":[\"add\",\"1\"],\"mediatype.message_templates[161].recovery\":[\"add\",\"0\"],".
				"\"mediatype.message_templates[161].subject\":[\"add\",\"Updated subject message\"],".
				"\"mediatype.message_templates[161].message\":[\"add\",\"Updated main message\"],".
				"\"mediatype.message_templates[161].mediatype_messageid\":[\"add\",\"161\"],\"mediatype.maxsessions".
				"\":[\"update\",\"40\",\"50\"],\"mediatype.maxattempts\":[\"update\",\"10\",\"5\"],".
				"\"mediatype.attempt_interval\":[\"update\",\"30s\",\"50s\"]}";

		$this->call('mediatype.update', [
			[
				'mediatypeid' => self::$resourceid,
				'status' => 1,
				'name' => 'updated_email_media',
				'smtp_server' => 'updated_test.test.com',
				'smtp_helo' => 'updated_test.com',
				'smtp_email' => 'updated_test@test.com',
				'smtp_port' => '589',
				'content_type' => '0',
				'message_templates' => [
					[
						'eventsource' => '1',
						'recovery' => '0',
						'subject' => 'Updated subject message',
						'message' => 'Updated main message'
					]
				],
				'maxsessions' => 40,
				'maxattempts' => 10,
				'attempt_interval' => '30s'
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogMediaType_Delete() {
		$this->call('mediatype.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'updated_email_media');
	}

	private function sendGetRequest($output, $action, $result) {
		$get = $this->call('auditlog.get', [
			'output' => [$output],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => self::$resourceid,
				'action' => $action
			]
		]);

		$this->assertEquals($result, $get['result'][0][$output]);
	}
}
