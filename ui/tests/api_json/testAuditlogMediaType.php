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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup media_type
 */
class testAuditlogMediaType extends testAuditlogCommon {

	/**
	 * Existing Media type ID.
	 */
	private const MEDIATYPEID = 1;

	public function testAuditlogMediaType_Create() {
		$create = $this->call('mediatype.create', [
			[
				'type' => 0,
				'name' => 'email_media',
				'smtp_server' => 'test.test.com',
				'smtp_helo' => 'test.com',
				'smtp_email' => 'test@test.com',
				'smtp_port' => 587,
				'content_type' => 1,
				'message_templates' => [
					[
						'eventsource' => 0,
						'recovery' => 0,
						'subject' => 'Subject message',
						'message' => 'Main message'
					]
				],
				'maxsessions' => 50,
				'maxattempts' => 5,
				'attempt_interval' => '50s'
			]
		]);

		$resourceid = $create['result']['mediatypeids'][0];
		$message = CDBHelper::getRow('SELECT mediatype_messageid FROM media_type_message WHERE mediatypeid='.
				zbx_dbstr($resourceid)
		);

		$created = json_encode([
			'mediatype.name' => ['add', 'email_media'],
			'mediatype.smtp_server' => ['add', 'test.test.com'],
			'mediatype.smtp_helo' => ['add', 'test.com'],
			'mediatype.smtp_email' => ['add', 'test@test.com'],
			'mediatype.smtp_port' => ['add', '587'],
			'mediatype.message_templates['.$message['mediatype_messageid'].']' => ['add'],
			'mediatype.message_templates['.$message['mediatype_messageid'].'].eventsource' => ['add', '0'],
			'mediatype.message_templates['.$message['mediatype_messageid'].'].recovery' => ['add', '0'],
			'mediatype.message_templates['.$message['mediatype_messageid'].'].subject' => ['add', 'Subject message'],
			'mediatype.message_templates['.$message['mediatype_messageid'].'].message' => ['add', 'Main message'],
			'mediatype.message_templates['.$message['mediatype_messageid'].'].mediatype_messageid'
				=> ['add', $message['mediatype_messageid']],
			'mediatype.maxsessions' => ['add', '50'],
			'mediatype.maxattempts' => ['add', '5'],
			'mediatype.attempt_interval' => ['add', '50s'],
			'mediatype.mediatypeid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, $resourceid);
	}

	public function testAuditlogMediaType_Update() {
		$this->call('mediatype.update', [
			[
				'mediatypeid' => self::MEDIATYPEID,
				'status' => 1,
				'name' => 'updated_email_media',
				'smtp_server' => 'updated_test.test.com',
				'smtp_helo' => 'updated_test.com',
				'smtp_email' => 'updated_test@test.com',
				'smtp_port' => 589,
				'content_type' => 0,
				'message_templates' => [
					[
						'eventsource' => 1,
						'recovery' => 0,
						'subject' => 'Updated subject message',
						'message' => 'Updated main message'
					]
				],
				'maxsessions' => 40,
				'maxattempts' => 10,
				'attempt_interval' => '30s'
			]
		]);

		$updated = json_encode([
			'mediatype.message_templates[1]' => ['delete'],
			'mediatype.message_templates[2]' => ['delete'],
			'mediatype.message_templates[3]' => ['delete'],
			'mediatype.message_templates[5]' => ['delete'],
			'mediatype.status' => ['update', '1', '0'],
			'mediatype.name' => ['update', 'updated_email_media', 'Email'],
			'mediatype.smtp_server' => ['update', 'updated_test.test.com', 'mail.example.com'],
			'mediatype.smtp_helo' => ['update', 'updated_test.com', 'example.com'],
			'mediatype.smtp_email' => ['update', 'updated_test@test.com', 'zabbix@example.com'],
			'mediatype.smtp_port' => ['update', '589', '25'],
			'mediatype.message_templates[4]' => ['update'],
			'mediatype.message_templates[4].subject' => ['update', 'Updated subject message', 'Discovery: '.
				'{DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}'],
			'mediatype.message_templates[4].message' => ['update', 'Updated main message', "Discovery rule: ".
				"{DISCOVERY.RULE.NAME}\r\n\r\nDevice IP: ".
				"{DISCOVERY.DEVICE.IPADDRESS}\r\nDevice DNS: {DISCOVERY.DEVICE.DNS}\r\nDevice status: ".
				"{DISCOVERY.DEVICE.STATUS}\r\nDevice uptime: {DISCOVERY.DEVICE.UPTIME}\r\n\r\nDevice service name: ".
				"{DISCOVERY.SERVICE.NAME}\r\nDevice service port: {DISCOVERY.SERVICE.PORT}\r\nDevice service status: ".
				"{DISCOVERY.SERVICE.STATUS}\r\nDevice service uptime: {DISCOVERY.SERVICE.UPTIME}"],
			'mediatype.maxsessions' => ['update', '40', '1'],
			'mediatype.maxattempts' => ['update', '10', '3'],
			'mediatype.attempt_interval' => ['update', '30s', '10s']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::MEDIATYPEID);
	}

	public function testAuditlogMediaType_Delete() {
		$this->call('mediatype.delete', [self::MEDIATYPEID]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'updated_email_media', self::MEDIATYPEID);
	}
}
