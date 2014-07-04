<?php

class ZBX8301DruleCreateTest extends ZbxApiTestBase
{
	/**
	 * @group regression
	 * @fixtures base_users
	 */
	public function testCreateValidateUnique()
	{
		$this->expectApiException(
			function () {
				return $this->processJsonFixtures('regression/fixtures/api/drule/ZBX8301DruleCreate.in.json');
			},
			'Invalid params.',
			'Only Zabbix agent, SNMPv1, SNMPv2 and SNMPv3 checks can be made unique.'
		);
	}
}
