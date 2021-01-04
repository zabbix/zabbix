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

require_once dirname(__FILE__).'/../include/CWebTest.php';

class testPageHostInterfaces extends CWebTest {

	public function getCheckInterfacesData() {
		return [
			[
				[
					'host' => 'Not available host',
					'interfaces' => [
						'ZBX' => [
							'color' => 'rgba(214, 78, 78, 1)', //red
							'rows' => [
								[
									'Interface' => 'zabbixzabbixzabbix.com:10050',
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => 'ERROR Agent'
								]
							]
						],
						'SNMP' => [
							'color' => 'rgba(214, 78, 78, 1)', //red
							'rows' => [
								[
									'Interface' => "zabbixzabbixzabbix.com:10050\nSNMPv2, Community: {\$SNMP_COMMUNITY}",
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => 'ERROR SNMP'
								]
							]
						],
						'IPMI' => [
							'color' => 'rgba(214, 78, 78, 1)', //red
							'rows' => [
								[
									'Interface' => 'zabbixzabbixzabbix.com:10050',
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => 'ERROR IPMI'
								]
							]
						],
						'JMX' => [
							'color' => 'rgba(214, 78, 78, 1)', //red
							'rows' => [
								[
									'Interface' => 'zabbixzabbixzabbix.com:10050',
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => 'ERROR JMX'
								]
							]
						]
					]
				]
			],
			[
				[
					'host' => 'Host with Orange interface',
					'interfaces' => [
						'ZBX' => [
							'color' => 'rgba(52, 175, 103, 1)', //green
							'rows' => [
								[
									'Interface' => '1available.zabbix.com:10050',
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								],
								[
									'Interface' => '2available.zabbix.com:10051',
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								]
							]
						],
						'SNMP' => [
							'color' => 'rgba(241, 165, 11, 1)', //orange
							'rows' => [
								[
									'Interface' => "127.0.0.98:163\nSNMPv3, Context name:",
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								],
								[
									'Interface' => "127.0.0.99:162\nSNMPv2, Community: {\$SNMP_COMMUNITY}",
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => ''
								]
							]
						],
						'IPMI' => [
							'color' => 'rgba(214, 78, 78, 1)', //red
							'rows' => [
								[
									'Interface' => '1unavail.IPMI.zabbix.com:623',
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => '1 Error IPMI'
								],
								[
									'Interface' => '2unavail.IPMI.zabbix.com:624',
									'Status' => [
										'text' => 'Not available',
										'color' => 'rgba(214, 78, 78, 1)' //red
									],
									'Error' => '2 Error IPMI'
								]
							]
						]
					]
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'interfaces' => [
						'ZBX' => [
							'color' => 'rgba(235, 235, 235, 1)', //grey
							'rows' => [
								[
									'Interface' => '127.0.0.1:10051',
									'Status' => [
										'text' => 'Unknown',
										'color' => 'rgba(235, 235, 235, 1)' //grey
									],
									'Error' => ''
								],
								[
									'Interface' => '127.0.0.2:10052',
									'Status' => [
										'text' => 'Unknown',
										'color' => 'rgba(235, 235, 235, 1)' //grey
									],
									'Error' => ''
								]
							]
						],
						'SNMP' => [
							'color' => 'rgba(235, 235, 235, 1)', //grey
							'rows' => [
								[
									'Interface' => "127.0.0.3:10053\nSNMPv2, Community: {\$SNMP_COMMUNITY}",
									'Status' => [
										'text' => 'Unknown',
										'color' => 'rgba(235, 235, 235, 1)' //grey
									],
									'Error' => ''
								]
							]
						],
						'IPMI' => [
							'color' => 'rgba(235, 235, 235, 1)', //grey
							'rows' => [
								[
									'Interface' => '127.0.0.4:10054',
									'Status' => [
										'text' => 'Unknown',
										'color' => 'rgba(235, 235, 235, 1)' //grey
									],
									'Error' => ''
								]
							]
						],
						'JMX' => [
							'color' => 'rgba(235, 235, 235, 1)', //grey
							'rows' => [
								[
									'Interface' => '127.0.0.5:10055',
									'Status' => [
										'text' => 'Unknown',
										'color' => 'rgba(235, 235, 235, 1)' //grey
									],
									'Error' => ''
								]
							]
						]
					]
				]
			],
			[
				[
					'host' => 'Available host',
					'interfaces' => [
						'ZBX' => [
							'color' => 'rgba(52, 175, 103, 1)', //green
							'rows' => [
								[
									'Interface' => '127.0.0.1:10050',
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								]
							]
						],
						'SNMP' => [
							'color' => 'rgba(52, 175, 103, 1)', //green
							'rows' => [
								[
									'Interface' => "zabbixzabbixzabbix.com:10050\nSNMPv2, Community: {\$SNMP_COMMUNITY}",
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								]
							]
						],
						'IPMI' => [
							'color' => 'rgba(52, 175, 103, 1)', //green
							'rows' => [
								[
									'Interface' => 'zabbixzabbixzabbix.com:10050',
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								]
							]
						],
						'JMX' => [
							'color' => 'rgba(52, 175, 103, 1)', //green
							'rows' => [
								[
									'Interface' => 'zabbixzabbixzabbix.com:10050',
									'Status' => [
										'text' => 'Available',
										'color' => 'rgba(52, 175, 103, 1)' //green
									],
									'Error' => ''
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Test displaying host interfaces on Configuration->Hosts page.
	 *
	 * @dataProvider getCheckInterfacesData
	 */
	public function testPageHostInterfaces_MonitoringHosts($data) {
		$this->page->login()->open('zabbix.php?action=host.view')->waitUntilReady();
		$this->checkInterfaces($data, 'host_view');
	}

	/**
	 * Test displaying host interfaces on Configuration->Hosts page.
	 *
	 * @dataProvider getCheckInterfacesData
	 */
	public function testPageHostInterfaces_ConfigurationHosts($data) {
		$this->page->login()->open('hosts.php')->waitUntilReady();
		$this->checkInterfaces($data, 'hosts');
	}

	/**
	 * Test displaying host interfaces on Host form page.
	 *
	 * @dataProvider getCheckInterfacesData
	 */
	public function testPageHostInterfaces_HostForm($data) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host ='.zbx_dbstr($data['host']));
		$this->page->login()->open('hosts.php?form=update&hostid='.$id)->waitUntilReady();
		$this->checkInterfaces($data, $selector = null, true);
	}

	/**
	 * Test displaying host interfaces on Host form page.
	 *
	 * @dataProvider getCheckInterfacesData
	 */
	public function testPageHostInterfaces_DiscoveryPage($data) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host ='.zbx_dbstr($data['host']));
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$id.'&context=host')->waitUntilReady();
		$this->checkInterfaces($data, $selector = null, true);
	}

	/**
	 * Function for checking interfaces.
	 *
	 * @param array     $data          data from data provider
	 * @param string    $selector      table selector on page
	 * @param boolean   $navigation    is it configuration form or not
	 */
	private function checkInterfaces($data, $selector = null, $navigation = false) {
		if ($navigation) {
			$availability = $this->query('xpath://div[@class="status-container"]')->waitUntilPresent()->one();
		}
		else {
			$table = $this->query('xpath://form[@name='.zbx_dbstr($selector).']/table[@class="list-table"]')
					->waitUntilReady()->asTable()->one();
			$availability = $table->findRow('Name', $data['host'])->getColumn('Availability');
		}

		$host_interfaces = [];
		foreach ($availability->query('xpath:.//span[@data-hintbox="1"]')->all() as $interface) {
			$interface_name = $interface->getText();
			// Write interfaces names into array.
			$host_interfaces[] = $interface_name;
			// Check interface color in availability column.
			$this->assertEquals($data['interfaces'][$interface_name]['color'], $interface->getCSSValue('background-color'));
			// Open interface popup.
			$interface->click();
			$overlay = $this->query('xpath://div[@class="overlay-dialogue"]')->asOverlayDialog()->waitUntilPresent()->one();
			$interface_table = $overlay->query('xpath:.//table[@class="list-table"]')->asTable()->one();
			// Check table headers in popup.
			$this->assertSame(['Interface', 'Status', 'Error'], $interface_table->getHeadersText());
			// Check every interface row.
			foreach ($interface_table->getRows() as $i => $row) {
				$this->assertEquals($data['interfaces'][$interface_name]['rows'][$i]['Interface'],
						$row->getColumn('Interface')->getText());
				$this->assertEquals($data['interfaces'][$interface_name]['rows'][$i]['Status']['text'],
						$row->getColumn('Status')->getText());
				$this->assertEquals($data['interfaces'][$interface_name]['rows'][$i]['Status']['color'], $row->getColumn('Status')
						->query('xpath:.//span[contains(@class, "status")]')->one()->getCSSValue('background-color'));
				$this->assertEquals($data['interfaces'][$interface_name]['rows'][$i]['Error'],
						$row->getColumn('Error')->getText());
			}

			$overlay->close();
			$overlay->waitUntilNotPresent();
		}
		// Assert interface names in Availability column.
		$this->assertEquals(array_keys($data['interfaces']), $host_interfaces);
	}
}
