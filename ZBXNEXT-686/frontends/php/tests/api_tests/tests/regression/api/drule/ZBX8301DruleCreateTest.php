<?php

class ZBX8301DruleCreateTest extends ZbxApiTestBase
{
	public function testCreateValidateUnique()
	{
		$this->expectApiException(
			'regression/fixtures/api/drule/ZBX8301DruleCreate.in.json',
			'Invalid params.',
			'Only Zabbix agent, SNMPv1, SNMPv2 and SNMPv3 checks can be made unique.'
		);
	}
}
