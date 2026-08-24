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

require_once __DIR__.'/../../include/CLegacyWebTest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testInheritanceDiscoveryRule extends CLegacyWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template  = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	// Returns list of discovery rules from a template.
	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT itemid'.
			' FROM items'.
			' WHERE hostid=15000'.	//	$this->templateid.
				' AND flags=1'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceDiscoveryRule_SimpleUpdate($data) {
		$sqlDiscovery = 'SELECT * FROM items ORDER BY itemid';
		$oldHashDiscovery = CDBHelper::getHash($sqlDiscovery);
		$this->page->login()->open('zabbix.php?action=popup&popup=lldrule.edit&context=template&itemid='.$data['itemid']);
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->getFooter()->query('button:Update')->waitUntilClickable()->one()->click();
		$dialog->ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Discovery rule updated');

		$this->assertEquals($oldHashDiscovery, CDBHelper::getHash($sqlDiscovery));
	}

	// Returns create data.
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRule6',
					'key' => 'discovery-rule-inheritance6'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceDiscoveryRule5',
					'key' => 'discovery-rule-inheritance5',
					'errors' => [
						'Cannot inherit LLD rule with key "discovery-rule-inheritance5" of template "Inheritance '.
								'test template" to host "Template inheritance test host", because an LLD rule with the '.
								'same key is already inherited from template "Inheritance test template 2".'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRuleWithLLDMacros',
					'key' => 'discovery-rule-inheritance-with-macros',
					'macros' => [
						['macro' => '{#MACRO1}', 'path'=>'$.path.1'],
						['macro' => '{#MACRO2}', 'path'=>'$.path.1']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceDiscoveryRule_SimpleCreate($data) {
		$this->page->login()->open('zabbix.php?action=lldrule.list&filter_set=1&context=template&filter_hostids[0]='.$this->templateid);
		$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->fill(['Name' => $data['name'], 'Key' => $data['key']]);

		if (array_key_exists('macros', $data)) {
			$this->zbxTestTabSwitch('LLD macros');
			$last = count($data['macros']) - 1;

			foreach ($data['macros'] as $i => $lld_macro) {
				$this->query('id:lld_macro_paths_'.$i.'_lld_macro')->one()->fill($lld_macro['macro']);
				$this->query('id:lld_macro_paths_'.$i.'_path')->one()->fill($lld_macro['path']);

				if ($i !== $last) {
					$form->getField('LLD macros')->query('button:Add')->one()->click();
				}
			}
		}

		$form->submit();
		switch ($data['expected']) {
			case TEST_GOOD:
				$dialog->ensureNotPresent();
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestCheckHeader('Discovery rules');

				$itemId = 0;

				// Template DB check.
				$dbResult = DBselect(
					'SELECT itemid,name,templateid'.
					' FROM items'.
					' WHERE hostid='.$this->templateid.
						' AND key_='.zbx_dbstr($data['key']).
						' AND flags=1'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$itemId = $dbRow['itemid'];
					$this->assertEquals($dbRow['name'], $data['name']);
					$this->assertEquals($dbRow['templateid'], 0);
				}

				$this->assertNotEquals($itemId, 0);

				// Host DB check.
				$dbResult = DBselect(
					'SELECT key_,name'.
					' FROM items'.
					' WHERE hostid='.$this->hostid.
						' AND templateid='.$itemId.
						' AND flags=1'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$this->assertEquals($dbRow['key_'], $data['key']);
					$this->assertEquals($dbRow['name'], $data['name']);
				}

				// Host form check.
				$this->zbxTestLogin('zabbix.php?action=lldrule.list&filter_set=1&context=host&filter_hostids[0]='.$this->hostid);
				$this->zbxTestClickLinkText($data['name']);
				$host_dialog = COverlayDialogElement::find()->waitUntilReady()->one();

				$this->zbxTestAssertElementPresentXpath('//z-textarea-flexible[@id="name"][@value="'.$data['name'].'"][@readonly]');
				$this->zbxTestAssertElementPresentXpath('//z-textarea-flexible[@id="key"][@value="'.$data['key'].'"][@readonly]');
				if (array_key_exists('macros', $data)) {
					$this->zbxTestTabSwitch('LLD macros');
					foreach ($data['macros'] as $i => $lld_macro) {
						$this->zbxTestAssertElementPresentXpath('//z-textarea-flexible[@id="lld_macro_paths_'.$i.
								'_lld_macro"][@value="'.$lld_macro['macro'].'"][@readonly]'
						);
						$this->zbxTestAssertElementPresentXpath('//z-textarea-flexible[@id="lld_macro_paths_'.$i.
								'_path"][@value="'.$lld_macro['path'].'"][@readonly]'
						);
					}
				}

				$host_dialog->close();
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestCheckHeader('Discovery rules');
				$this->assertMessage(TEST_BAD, 'Cannot add discovery rule', $data['errors']);

				$dialog->close();
				break;
		}
	}
}
