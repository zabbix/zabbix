<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * Base class for Host and Template groups page.
 */
class testPageGroups extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * Objects created in dataSource DiscoveredHosts.
	 */
	const DISCOVERED_GROUP = 'Group created from host prototype 1';
	const DISCOVERED_GROUP2 = 'Group created from host prototype 11';
	const HOST_PROTOTYPE = 'Host created from host prototype {#KEY}';
	const LLD = 'LLD for Discovered host tests';

	/**
	 * Objects created in dataSource HostTemplateGroups.
	 */
	const DELETE_ONE_GROUP = 'One group belongs to one object for Delete test';
	const DELETE_EMPTY_GROUP = 'Group empty for Delete test';
	const DELETE_GROUP2 = 'First group to one object for Delete test';

	/**
	 * The group was created in host/template page test for multiple group deletion.
	 */
	const DELETE_GROUP3 = 'Group 3 for Delete test';

	/**
	 * SQL query to get groups to compare hash values.
	 */
	const GROUPS_SQL = 'SELECT * FROM hstgrp g INNER JOIN hosts_groups hg ON g.groupid=hg.groupid'.
			' ORDER BY g.groupid, hg.hostgroupid';

	/**
	 * SQL query to get hosts to compare hash values.
	 */
	const HOSTS_SQL = 'SELECT * FROM hosts ORDER BY hostid';

	/**
	 * Link to page for opening groups page.
	 */
	protected $link;

	/**
	 * Host or template group.
	 */
	protected $object;

	/**
	 *  Test for checking group page layout.
	 *
	 * @param array $data   data provider
	 * @param array $links  related links of group to be checked
	 */
	public function checkLayout($data, $links) {
		$this->page->login()->open($this->link)->waitUntilReady();
		$this->page->assertHeader(ucfirst($this->object).' groups');
		$this->page->assertTitle('Configuration of '.$this->object.' groups');

		// Check filter.
		$filter = CFilterElement::find()->one();
		$form = $filter->getForm();
		$this->assertEquals(['Name'], $form->getLabels()->asText());
		$this->assertTrue($form->getField('Name')->isAttributePresent(['value' => '', 'maxlength' => '255']));

		// Check displaying and hiding the filter container.
		$this->assertTrue($filter->isExpanded());
		foreach ([false, true] as $state) {
			$filter->expand($state);
			// Leave the page and reopen the previous page to make sure the filter state is still saved..
			$this->page->open('zabbix.php?action=report.status')->waitUntilReady();
			$this->page->open($this->link)->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		// Check buttons.
		$this->assertEquals(3, $this->query('button', ['Create '.$this->object.' group', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);
		$disabled_buttons = ($this->object === 'host') ? ['Enable hosts', 'Disable hosts', 'Delete'] : ['Delete'];
		$this->assertEquals(count($disabled_buttons),
				$this->query('button', $disabled_buttons)->all()->filter(CElementFilter::NOT_CLICKABLE)->count()
		);

		// Check table headers.
		$table = $this->getTable();
		$headers = ($this->object === 'host') ? ['', 'Name', 'Hosts', 'Info'] : ['', 'Name', 'Templates'];
		$this->assertEquals($headers, $table->getHeadersText());
		$this->assertEquals(['Name'], $table->getSortableHeaders()->asText());

		// Check the displayed number of groups in the table.
		$names = $this->getGroupNames();
		$this->assertTableStats(count($names));
		$this->assertSelectedCount(0);
		$this->selectTableRows();
		$this->assertSelectedCount(count($names));
		$this->query('id:all_groups')->asCheckbox()->one()->uncheck();
		$this->assertSelectedCount(0);

		// Check table content.
		$this->assertTableHasData($data);

		// Check hintbox of discovered host group in info column.
		if ($this->object === 'host') {
			$row = $table->findRow('Name', self::LLD.': '.self::DISCOVERED_GROUP);
			$icon = $row->getColumn('Info')->query('tag:button')->one();
			$this->assertTrue($icon->hasClass('zi-i-warning'));
			$icon->click();
			$hintbox = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilVisible();
			$this->assertEquals('The host group is not discovered anymore and will be deleted the next time discovery'.
					' rule is processed.',
					$hintbox->one()->getText()
			);
			$hintbox->query('class:btn-overlay-close')->one()->click()->waitUntilNotPresent();
		}

		// Check related links of group in table row.
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($links['host_template']));
		$row = $table->findRow('Name',
				array_key_exists('lld', $links) ? $links['lld'].': '.$links['name'] : $links['name']
		);

		// Check template link color.
		if ($this->object === 'template') {
			$this->assertTrue($row->getColumn('Templates')->query('link', $links['host_template'])->one()
					->hasClass('grey')
			);
		}

		// Check link to the host or template edit form.
		$row->getColumn(ucfirst($this->object).'s')->query('link', $links['host_template'])->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		$this->assertStringContainsString('zabbix.php?action=popup&popup='.$this->object.'.edit&'.$this->object.
				'id='.$id, $this->page->getCurrentUrl()
		);

		$this->assertEquals(ucfirst($this->object), $dialog->getTitle());
		$dialog->asForm()->checkValue([ucfirst($this->object).' name' => $links['host_template']]);
		$dialog->close();
		$dialog->ensureNotPresent();

		// Check link to hosts or templates page with selected group in filter.
		$group_id = CDBHelper::getValue('SELECT groupid FROM hstgrp WHERE name='.zbx_dbstr($links['name']));
		$row->getColumn('Count')->query('link', $links['count'])->one()->click();
		$this->assertStringContainsString((($this->object === 'host')
			? 'zabbix.php?action=host.list&'
			: 'zabbix.php?action=template.list&').'filter_set=1&filter_groups%5B0%5D='.$group_id, $this->page->getCurrentUrl()
		);
		$this->page->assertHeader(ucfirst($this->object).'s');
		CFilterElement::find()->one()->getForm()->checkValue([ucfirst($this->object).' groups' => $links['name']]);
		$this->assertTableHasData([
			[
				'Name' => array_key_exists('lld', $links)
					? $links['lld'].': '.$links['host_template']
					: $links['host_template']
			]
		]);
		$this->page->open($this->link)->waitUntilReady();

		// Check link to host prototype from host group name.
		if (array_key_exists('lld', $links)) {
			$row->getColumn('Name')->query('link', $links['lld'])->one()->click();
			$this->assertStringContainsString('host_prototypes.php?form=update&parent_discoveryid=',
					$this->page->getCurrentUrl()
			);
			$this->page->assertHeader('Host prototypes');
			$this->query('id:host-prototype-form')->asForm(['normalized' => true])->waitUntilVisible()->one()
					->checkValue(['Host name' => self::HOST_PROTOTYPE]);
			$this->query('button:Cancel')->one()->click();
			$this->assertStringContainsString('host_prototypes.php?cancel=1&parent_discoveryid=',
					$this->page->getCurrentUrl()
			);
			$this->page->open($this->link)->waitUntilReady();
		}
	}

	/**
	 * Get and sort group names.
	 *
	 * @param string $sort  sort content ascending or descending
	 *
	 * @return array
	 */
	public function getGroupNames($sort = 'asc') {
		$names = CDBHelper::getColumn('SELECT name FROM hstgrp WHERE type='.
				constant('HOST_GROUP_TYPE_'.strtoupper($this->object).'_GROUP'), 'name');

		natcasesort($names);
		if ($sort !== 'asc') {
			$names = array_reverse($names);
		}

		if ($this->object === 'host') {
			$discovered_hosts = [
				self::DISCOVERED_GROUP => self::LLD,
				self::DISCOVERED_GROUP2 => self::LLD,
				'Single prototype group KEY' => '17th LLD',
				'グループプロトタイプ番号 1 KEY' => '1st LLD, ..., sixth LLD',
				'Trešais grupu prototips KEY' => 'LLD number 8, ..., sevenths LLD',
				'Two prototype group KEY' => '15th LLD 🙃^天!, 16th LLD',
				'5 prototype group KEY' => '12th LLD, ..., Četrpadsmitais LLD'
			];

			foreach ($discovered_hosts as $group_name => $llds) {
				$names[array_search($group_name, $names)] = $llds.': '.$group_name;
			}
		}

		return $names;
	}

	/**
	 * Check ascending and descending groups sorting by column Name.
	 */
	public function checkColumnSorting() {
		$this->page->login()->open($this->link)->waitUntilReady();
		$table = $this->getTable();

		foreach (['desc', 'asc'] as $sorting) {
			$names = $this->getGroupNames($sorting);
			$table->query('link:Name')->waitUntilClickable()->one()->click();
			$table->waitUntilReloaded();
			$this->assertTableDataColumn($names);
		}
	}

	public static function getFilterData() {
		return [
			// Too many spaces in field.
			[
				[
					'Name' => '  '
				]
			],
			// Special symbols, utf8 and long name.
			[
				[
					'Name' => '&<>//\\[]""#@'
				]
			],
			[
				[
					'Name' => 'æ㓴🙂'
				]
			],
			[
				[
					'Name' => STRING_255
				]
			]
		];
	}

	/**
	 * Check host or template groups filtering by name.
	 */
	public function filter($data) {
		$all = $this->getGroupNames();
		if (array_key_exists('all', $data)) {
			$data['expected'] = $all;
		}

		$this->page->login()->open($this->link)->waitUntilReady();
		$table = $this->getTable();
		$form = CFilterElement::find()->one()->getForm();
		$form->fill(['Name' => $data['Name']]);
		$form->submit();
		$table->waitUntilReloaded();
		$this->assertTableStats(count(CTestArrayHelper::get($data, 'expected', [])));
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

		// Reset filter.
		$form->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();
		$this->assertTableStats(count($all));
	}

	public static function getCancelData() {
		return [
			[
				[
					'action' => 'Delete'
				]
			]
		];
	}

	public function cancel($data) {
		$rows = [
			[
				'index' => 0,
				'count' => 1
			],
			[
				'index' => 1,
				'count' => 2
			],
			[
				'all' => true
			]
		];
		$groups_old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		$hosts_old_hash = CDBHelper::getHash(self::HOSTS_SQL);
		$this->page->login()->open($this->link)->waitUntilReady();
		$table = $this->getTable();

		foreach ($rows as $row) {
			if (array_key_exists('all', $row)) {
				$row['count'] = count($this->getGroupNames());
				$this->selectTableRows();
			}
			else {
				$table->getRow($row['index'])->select();
			}

			// The word "group" in message text depends on the number of selected lines.
			if ($data['action'] === 'Delete') {
				$data['message'] = 'Delete selected '.$this->object.' group'.(($row['count'] === 1) ? '?' : 's?');
			}

			$this->assertSelectedCount($row['count']);
			$this->query('button', $data['action'])->one()->click();
			$this->assertEquals($data['message'], $this->page->getAlertText());
			$this->page->dismissAlert();
			$this->assertSelectedCount($row['count']);
		}

		$this->assertEquals($groups_old_hash, CDBHelper::getHash(self::GROUPS_SQL));
		$this->assertEquals($hosts_old_hash, CDBHelper::getHash(self::HOSTS_SQL));
	}

	public static function getDeleteData() {
		return [
			// Select one.
			[
				[
					'expected' => TEST_GOOD,
					'groups' => self::DELETE_EMPTY_GROUP
				]
			],
			// Select several.
			[
				[
					'expected' => TEST_GOOD,
					'groups' => [self::DELETE_GROUP2, self::DELETE_GROUP3]
				]
			]
		];
	}

	public function delete($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		}

		if (!is_array(CTestArrayHelper::get($data, 'groups', []))) {
			$data['groups'] = [$data['groups']];
		}

		$all = $this->getGroupNames();
		$count = count(CTestArrayHelper::get($data, 'groups', $all));

		$this->page->login()->open($this->link)->waitUntilReady();
		$table = $this->getTable();
		$this->selectTableRows(CTestArrayHelper::get($data, 'groups'));
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$table->waitUntilReloaded();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertSelectedCount(0);
			$this->assertTableStats(count($all) - $count);
			$title = ucfirst($this->object).(($count === 1) ? ' group deleted' : ' groups deleted');
			$this->assertMessage(TEST_GOOD, $title);
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hstgrp WHERE type='.
					constant('HOST_GROUP_TYPE_'.strtoupper($this->object).'_GROUP').
					' AND name IN ('.CDBHelper::escape($data['groups']).')')
			);
		}
		else {
			$this->assertSelectedCount($count);
			$this->assertMessage(TEST_BAD, 'Cannot delete '.$this->object.' group'.(($count > 1) ? 's' : ''), $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));

			// Reset selected groups.
			$this->query('button:Reset')->one()->click();
			$table->waitUntilReloaded();
			$this->assertTableStats(count($all));
		}
	}
}
