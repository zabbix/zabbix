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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup media_type, media_type_message, media_type_param, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditMediaType extends testPageReportsAuditValues {

	/**
	 * Id of media type.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "mediatype.attempt_interval: 50s".
			"\nmediatype.maxattempts: 5".
			"\nmediatype.maxsessions: 50".
			"\nmediatype.mediatypeid: 34".
			"\nmediatype.message_templates[160]: Added".
			"\nmediatype.message_templates[160].eventsource: 0".
			"\nmediatype.message_templates[160].mediatype_messageid: 160".
			"\nmediatype.message_templates[160].message: Main message".
			"\nmediatype.message_templates[160].recovery: 0".
			"\nmediatype.message_templates[160].subject: Subject message".
			"\nmediatype.name: email_media".
			"\nmediatype.smtp_email: test@test.com".
			"\nmediatype.smtp_helo: test.com".
			"\nmediatype.smtp_port: 587".
			"\nmediatype.smtp_server: test.test.com";

	public $updated = "mediatype.attempt_interval: 50s => 30s".
			"\nmediatype.content_type: 1 => 0".
			"\nmediatype.maxattempts: 5 => 10".
			"\nmediatype.maxsessions: 50 => 40".
			"\nmediatype.message_templates[160]: Deleted".
			"\nmediatype.message_templates[161]: Added".
			"\nmediatype.message_templates[161].eventsource: 1".
			"\nmediatype.message_templates[161].mediatype_messageid: 161".
			"\nmediatype.message_templates[161].message: Updated main message".
			"\nmediatype.message_templates[161].recovery: 0".
			"\nmediatype.message_templates[161].subject: Updated subject message".
			"\nmediatype.name: email_media => updated_email_media".
			"\nmediatype.smtp_email: test@test.com => updated_test@test.com".
			"\nmediatype.smtp_helo: test.com => updated_test.com".
			"\nmediatype.smtp_port: 587 => 589".
			"\nmediatype.smtp_server: test.test.com => updated_test.test.com".
			"\nmediatype.status: 0 => 1";

	public $deleted = 'Description: updated_email_media';

	public $resource_name = 'Media type';

	public function prepareCreateData() {
		$ids = CDataHelper::call('mediatype.create', [
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
				'attempt_interval' => '50s',

			]
		]);
		$this->assertArrayHasKey('mediatypeids', $ids);
		self::$ids = $ids['mediatypeids'][0];
	}

	/**
	 * Check audit of created media type.
	 */
	public function testAuditMediaType_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of updated media type.
	 */
	public function testAuditMediaType_Update() {
		CDataHelper::call('mediatype.update', [
			[
				'mediatypeid' => self::$ids,
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

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted media type.
	 */
	public function testAuditMediaType_Delete() {
		CDataHelper::call('mediatype.delete', [self::$ids]);
		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
