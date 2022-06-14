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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../traits/PreprocessingTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Common code for cloning hosts and templates with preprocessing steps in items.
 */
class testFormPreprocessingClone extends CWebTest {

	use PreprocessingTrait;

	public $hostid;
	public $itemid;
	public $lldid;
	public $item_prototypeid;

	const COMMON_PREPROCESSING = [
		[
			'type' => '5',
			'params' => "regular expression pattern \ntest output",
			'error_handler' => 2,
			'error_handler_params' => 'value1'
		],
		[
			'type' => '11',
			'params' => '/document/item/value/text()',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '12',
			'params' => '$.document.item.value parameter.',
			'error_handler' => 3,
			'error_handler_params' => 'error1'
		],
		[
			'type' => '15',
			'params' => 'regular expression pattern for not matching',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '16',
			'params' => '/json/path',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '17',
			'params' => '/xml/path',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '21',
			'params' => 'test script',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '24',
			'params' => ".\n/\n1",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '25',
			'params' => "1\n2",
			'error_handler' => 0,
			'error_handler_params' => ''
		]
	];

	/**
	 * Get preprocessing steps for item and item prototype.
	 */
	public function getItemPreprocessing() {
		return array_merge(self::COMMON_PREPROCESSING, [
			[
				'type' => '1',
				'params' => '123',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '2',
				'params' => 'abc',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '3',
				'params' => 'def',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '4',
				'params' => '1a2b3c',
				'error_handler' => 0,
				'error_handler_params' => ''
			],

			[
				'type' => '6',
				'params' => '',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '7',
				'params' => '',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '8',
				'params' => '',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '9',
				'params' => '',
				'error_handler' => 2,
				'error_handler_params' => 'value2'
			],
			[
				'type' => '13',
				'params' => "-5\n3",
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '14',
				'params' => 'regular expression pattern for matching',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '18',
				'params' => "regular expression pattern for error matching \ntest output",
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '19',
				'params' => '',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '22',
				'params' => "cpu_usage_system\nlabel\nlabel_name",
				'error_handler' => 3,
				'error_handler_params' => 'error2'
			]
		]);
	}

	/**
	 * Get preprocessing steps for discovery rule.
	 */
	public function getLLDPreprocessing() {
		return array_merge(self::COMMON_PREPROCESSING, [
			[
				'type' => '20',
				'params' => '7',
				'error_handler' => 0,
				'error_handler_params' => ''
			],
			[
				'type' => '23',
				'params' => 'metric',
				'error_handler' => 2,
				'error_handler_params' => 'error3'
			]
		]);
	}

	/**
	 * Add preprocessing steps to LLD.
	 */
	public function prepareLLDPreprocessing() {
		CDataHelper::call('discoveryrule.update', [
			'itemid' => $this->lldid,
			'preprocessing' => $this->getLLDPreprocessing()
		]);
	}

	/**
	 * Add preprocessing steps to item.
	 */
	public function prepareItemPreprocessing() {
		CDataHelper::call('item.update', [
			'itemid' => $this->itemid,
			'preprocessing' => $this->getItemPreprocessing()
		]);
	}

	/**
	 * Add preprocessing steps to item prototype.
	 */
	public function prepareItemPrototypePreprocessing() {
		CDataHelper::call('itemprototype.update', [
			'itemid' => $this->item_prototypeid,
			'preprocessing' => $this->getItemPreprocessing()
		]);
	}

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Function for cloning host or template and check whether preprocessing is cloned correctly.
	 *
	 * @param boolean $template		true if template, false if host
	 */
	public function executeCloning($template = false) {
		$context = ($template) ? 'template' : 'host';
		$this->page->login();

		// Get item key and preprocessing.
		$item_key = CDBHelper::getValue('SELECT key_ FROM items WHERE itemid ='.$this->itemid);
		$item_original_steps = $this->getSteps('items.php?form=update&hostid='.$this->hostid.
				'&context='.$context.'&itemid='.$this->itemid);

		// Get LLD key and  preprocessing.
		$lld_key = CDBHelper::getValue('SELECT key_ FROM items WHERE itemid ='.$this->lldid);
		$lld_original_steps = $this->getSteps('host_discovery.php?form=update&context='.$context.'&itemid='.$this->lldid);

		// Get item prototype key and preprocessing.
		$item_prototype_key = CDBHelper::getValue('SELECT key_ FROM items WHERE itemid ='.$this->item_prototypeid);
		$item_prototype_original_steps = $this->getSteps('disc_prototypes.php?form=update&parent_discoveryid='.
				$this->lldid.'&context='.$context.'&itemid='.$this->item_prototypeid);

		// Open host or template and make a full clone of it.
		$url = ($template) ? 'templates.php?form=update&templateid=' : 'zabbix.php?action=host.edit&hostid=';
		$this->page->open($url.$this->hostid);
		$this->query('button:Full clone')->waitUntilClickable()->one()->click();

		// Fill cloned host or template form and save it.
		$form = ($template)
			? $this->query('name:templatesForm')->asForm()->waitUntilPresent()->one()
			: $this->query('id:host-form')->asForm()->waitUntilPresent()->one();

		$new_host_name = 'Cloned host name'.time();
		$form->fill([($template) ? 'Template name' : 'Host name' => $new_host_name]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, ($template) ? 'Template added' : 'Host added');

		// Check new host in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM hosts WHERE host ='.zbx_dbstr($new_host_name)));
		$cloned_hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host ='.zbx_dbstr($new_host_name));

		// Get new cloned item id and assert item preprocessing.
		$new_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid ='.$cloned_hostid.' AND key_ ='.
				zbx_dbstr($item_key));
		$item_cloned_steps = $this->getSteps('items.php?form=update&context='.$context.'&hostid='.$cloned_hostid.
				'&itemid='.$new_itemid);
		$this->assertEquals($item_original_steps, $item_cloned_steps);

		// Get new cloned lld rule id and assert lld preprocessing.
		$new_lldid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid ='.$cloned_hostid.
				' AND key_ ='.zbx_dbstr($lld_key));
		$lld_cloned_steps = $this->getSteps('host_discovery.php?form=update&context='.$context.'&itemid='.$new_lldid);
		$this->assertEquals($lld_original_steps, $lld_cloned_steps);

		// Get new cloned item prototype id and assert item prototype preprocessing.
		$new_item_prototypeid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid ='.$cloned_hostid.
				' AND key_ ='.zbx_dbstr($item_prototype_key));
		$item_prototype_cloned_steps = $this->getSteps('disc_prototypes.php?form=update&context='.$context.'&parent_discoveryid='.
				$new_lldid.'&itemid='.$new_item_prototypeid);
		$this->assertEquals($item_prototype_original_steps, $item_prototype_cloned_steps);
	}

	/**
	 * Function for getting preprocessing steps.
	 *
	 * @param string $link	URL of item, prototype or LLD
	 *
	 * @return array
	 */
	private function getSteps($link) {
		$this->page->open($link);
		$this->query('name:itemForm')->asForm()->waitUntilPresent()->one()->selectTab('Preprocessing');

		return $this->listPreprocessingSteps();
	}
}
