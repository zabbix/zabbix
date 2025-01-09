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


require_once dirname(__FILE__).'/../include/CAPITest.php';
require_once dirname(__FILE__).'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testTriggerPermissions extends CAPITest {

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'ug1(W)ug2(R)ug3(D)ug4(N)']
			],
			'host_groups' => [
				['name' => 'ug1(W)ug2(R)ug3(D)ug4(N)'],
				['name' => 'hg.other'],
				['name' => 'mht.hg1'],
				['name' => 'mht.hg2'],
				['name' => 'mht.hg3'],
				['name' => 'mht.hg4'],
				['name' => 'mht.hg.other']
			],
			'templates' => [
				[
					'host' => 't1',
					'groups' => [
						'groupid' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)'
					],
					'items' => [
						['key_' => 'i1']
					]
				]
			],
			'hosts' => [
				[
					'host' => 'h1',
					'groups' => [
						'groupid' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)'
					],
					'items' => [
						['key_' => 'i1']
					]
				],
				[
					'host' => 'mht.h1',
					'groups' => [
						'groupid' => ':host_group:mht.hg1'
					],
					'items' => [
						['key_' => 'i1']
					]
				],
				[
					'host' => 'mht.h2',
					'groups' => [
						'groupid' => ':host_group:mht.hg2'
					],
					'items' => [
						['key_' => 'i1']
					]
				],
				[
					'host' => 'mht.h3',
					'groups' => [
						'groupid' => ':host_group:mht.hg3'
					],
					'items' => [
						['key_' => 'i1']
					]
				],
				[
					'host' => 'mht.h4',
					'groups' => [
						'groupid' => ':host_group:mht.hg4'
					],
					'items' => [
						['key_' => 'i1']
					]
				]
			],
			'triggers' => [
				'tg1(t1(i1))' => ['description' => 'tg1(t1(i1))', 'expression' => 'last(/t1/i1)=0'],
				'tg1(h1(i1))' => ['description' => 'tg1(h1(i1))', 'expression' => 'last(/h1/i1)=0'],
				'mht.tg1(h1,h2)' => [
					'description' => 'mht.tg1(h1,h2)',
					'expression' => 'last(/mht.h1/i1)=0 and last(/mht.h2/i1)=0'
				],
				'mht.tg1(h1,h3)' => [
					'description' => 'mht.tg1(h1,h3)',
					'expression' => 'last(/mht.h1/i1)=0 and last(/mht.h3/i1)=0'
				],
				'mht.tg1(h1,h4)' => [
					'description' => 'mht.tg1(h1,h4)',
					'expression' => 'last(/mht.h1/i1)=0 and last(/mht.h4/i1)=0'
				],
				'mht.tg1(h2,h3)' => [
					'description' => 'mht.tg1(h2,h3)',
					'expression' => 'last(/mht.h2/i1)=0 and last(/mht.h3/i1)=0'
				],
				'mht.tg1(h2,h4)' => [
					'description' => 'mht.tg1(h2,h4)',
					'expression' => 'last(/mht.h2/i1)=0 and last(/mht.h4/i1)=0'
				],
				'mht.tg1(h3,h4)' => [
					'description' => 'mht.tg1(h3,h4)',
					'expression' => 'last(/mht.h3/i1)=0 and last(/mht.h4/i1)=0'
				]
			],
			'roles' => [
				['name' => 'r1', 'type' => USER_TYPE_ZABBIX_ADMIN]
			],
			'user_groups' => [
				[
					'name' => 'ug1(W)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ_WRITE]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ_WRITE]
					]
				],
				[
					'name' => 'ug2(R)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'ug3(D)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_DENY]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_DENY]
					]
				],
				[
					'name' => 'ug4(N)',
					'hostgroup_rights' => [
						['id' => ':host_group:hg.other', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'mht.ug1(W)',
					'hostgroup_rights' => [
						['id' => ':host_group:mht.hg1', 'permission' => PERM_READ_WRITE]
					]
				],
				[
					'name' => 'mht.ug2(R)',
					'hostgroup_rights' => [
						['id' => ':host_group:mht.hg2', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'mht.ug3(D)',
					'hostgroup_rights' => [
						['id' => ':host_group:mht.hg3', 'permission' => PERM_DENY]
					]
				],
				[
					'name' => 'mht.ug4(N)',
					'hostgroup_rights' => [
						['id' => ':host_group:mht.hg.other', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'mt.ug1(W)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ_WRITE]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:hg.other', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg1', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg2', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg3', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg4', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg.other', 'permission' => PERM_READ_WRITE]
					]
				],
				[
					'name' => 'mt.ug1(R)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ],
						['id' => ':host_group:hg.other', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg1', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg2', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg3', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg4', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg.other', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'mt.ug1(D)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_DENY]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_DENY],
						['id' => ':host_group:hg.other', 'permission' => PERM_DENY],
						['id' => ':host_group:mht.hg1', 'permission' => PERM_DENY],
						['id' => ':host_group:mht.hg2', 'permission' => PERM_DENY],
						['id' => ':host_group:mht.hg3', 'permission' => PERM_DENY],
						['id' => ':host_group:mht.hg4', 'permission' => PERM_DENY],
						['id' => ':host_group:mht.hg.other', 'permission' => PERM_DENY]
					]
				],
				[
					'name' => 'mt.ug1(N)'
				],
				[
					'name' => 'mt.ug1(mixed)',
					'templategroup_rights' => [
						['id' => ':template_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_DENY]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:ug1(W)ug2(R)ug3(D)ug4(N)', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:hg.other', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg1', 'permission' => PERM_READ_WRITE],
						['id' => ':host_group:mht.hg2', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg3', 'permission' => PERM_READ],
						['id' => ':host_group:mht.hg4', 'permission' => PERM_DENY]
					]
				]
			],
			'users' => [
				[
					'username' => 'u1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug1(W)']
					]
				],
				[
					'username' => 'u2',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug2(R)']
					]
				],
				[
					'username' => 'u3',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug3(D)']
					]
				],
				[
					'username' => 'u4',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug4(N)']
					]
				],
				[
					'username' => 'u1(ug1,ug2)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug1(W)'],
						['usrgrpid' => ':user_group:ug2(R)']
					]
				],
				[
					'username' => 'u1(ug1,ug3)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug1(W)'],
						['usrgrpid' => ':user_group:ug3(D)']
					]
				],
				[
					'username' => 'u1(ug1,ug4)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:ug1(W)'],
						['usrgrpid' => ':user_group:ug4(N)']
					]
				],
				[
					'username' => 'mht.u1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:mht.ug1(W)'],
						['usrgrpid' => ':user_group:mht.ug2(R)'],
						['usrgrpid' => ':user_group:mht.ug3(D)'],
						['usrgrpid' => ':user_group:mht.ug4(N)']
					]
				],
				[
					'username' => 'mt.u1(W)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:mt.ug1(W)']
					]
				],
				[
					'username' => 'mt.u1(R)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:mt.ug1(R)']
					]
				],
				[
					'username' => 'mt.u1(D)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:mt.ug1(D)']
					]
				],
				[
					'username' => 'mt.u1(N)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:mt.ug1(N)']
					]
				],
				[
					'username' => 'mt.u1(mixed)',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:mt.ug1(mixed)']
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getTriggerPermissions() {
		return [
			'User with W permissions on template trigger requests it in editable mode' => [
				'login' => ['username' => 'u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => true,
				'expected_triggerids' => [':trigger:tg1(t1(i1))']
			],
			'User with W permissions on template trigger requests it' => [
				'login' => ['username' => 'u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => false,
				'expected_triggerids' => [':trigger:tg1(t1(i1))']
			],
			'User with W permissions on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with W permissions on host trigger requests it' => [
				'login' => ['username' => 'u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with R permissions on template trigger requests it in editable mode' => [
				'login' => ['username' => 'u2', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with R permissions on template trigger requests it' => [
				'login' => ['username' => 'u2', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => false,
				'expected_triggerids' => [':trigger:tg1(t1(i1))']
			],
			'User with R permissions on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u2', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with R permissions on host trigger requests it' => [
				'login' => ['username' => 'u2', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with D permissions on template trigger requests it in editable mode' => [
				'login' => ['username' => 'u3', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with D permissions on template trigger requests it' => [
				'login' => ['username' => 'u3', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with D permissions on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u3', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with D permissions on host trigger requests it' => [
				'login' => ['username' => 'u3', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with N permissions on template trigger requests it in editable mode' => [
				'login' => ['username' => 'u4', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with N permissions on template trigger requests it' => [
				'login' => ['username' => 'u4', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(t1(i1))'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with N permissions on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u4', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with N permissions on host trigger requests it' => [
				'login' => ['username' => 'u4', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with WR permission on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u1(ug1,ug2)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with WR permission on host trigger requests it' => [
				'login' => ['username' => 'u1(ug1,ug2)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with WD permission on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u1(ug1,ug3)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with WD permission on host trigger requests it' => [
				'login' => ['username' => 'u1(ug1,ug3)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with WN permission on host trigger requests it in editable mode' => [
				'login' => ['username' => 'u1(ug1,ug4)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => true,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with WN permission on host trigger requests it' => [
				'login' => ['username' => 'u1(ug1,ug4)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:tg1(h1(i1))'],
				'editable' => false,
				'expected_triggerids' => [':trigger:tg1(h1(i1))']
			],
			'User with WR permission on trigger with multiple hosts requests it in editable mode' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h1,h2)'],
				'editable' => true,
				'expected_triggerids' => [':trigger:mht.tg1(h1,h2)']
			],
			'User with WR permission on trigger with multiple hosts requests it' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h1,h2)'],
				'editable' => false,
				'expected_triggerids' => [':trigger:mht.tg1(h1,h2)']
			],
			'User with WD permission on trigger with multiple hosts requests it in editable mode' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h1,h3)'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with WD permission on trigger with multiple hosts requests it' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h1,h3)'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with WN permission on trigger with multiple hosts requests it in editable mode' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h1,h4)'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with WN permission on trigger with multiple hosts requests it' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h1,h4)'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with RD permission on trigger with multiple hosts requests it in editable mode' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h2,h3)'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with RD permission on trigger with multiple hosts requests it' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h2,h3)'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with RN permission on trigger with multiple hosts requests it in editable mode' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h2,h4)'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with RN permission on trigger with multiple hosts requests it' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h2,h4)'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with DN permission on trigger with multiple hosts requests it in editable mode' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h3,h4)'],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with DN permission on trigger with multiple hosts requests it' => [
				'login' => ['username' => 'mht.u1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [':trigger:mht.tg1(h3,h4)'],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with W permission on all triggers requests them in editable mode' => [
				'login' => ['username' => 'mt.u1(W)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => true,
				'expected_triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				]
			],
			'User with W permission on all triggers requests them' => [
				'login' => ['username' => 'mt.u1(W)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => false,
				'expected_triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				]
			],
			'User with R permission on all triggers requests them in editable mode' => [
				'login' => ['username' => 'mt.u1(R)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with R permission on all triggers requests them' => [
				'login' => ['username' => 'mt.u1(R)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => false,
				'expected_triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				]
			],
			'User with D permission on all triggers requests them in editable mode' => [
				'login' => ['username' => 'mt.u1(D)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with D permission on all triggers requests them' => [
				'login' => ['username' => 'mt.u1(D)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with N permission on all triggers requests them in editable mode' => [
				'login' => ['username' => 'mt.u1(N)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => true,
				'expected_triggerids' => []
			],
			'User with N permission on all triggers requests them' => [
				'login' => ['username' => 'mt.u1(N)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => false,
				'expected_triggerids' => []
			],
			'User with mixed permission on different triggers requests them with other triggers in editable mode' => [
				'login' => ['username' => 'mt.u1(mixed)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => true,
				'expected_triggerids' => [
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)'
				]
			],
			'User with mixed permission on different triggers requests them with other triggers' => [
				'login' => ['username' => 'mt.u1(mixed)', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'triggerids' => [
					':trigger:tg1(t1(i1))',
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h1,h4)',
					':trigger:mht.tg1(h2,h3)',
					':trigger:mht.tg1(h2,h4)',
					':trigger:mht.tg1(h3,h4)'
				],
				'editable' => false,
				'expected_triggerids' => [
					':trigger:tg1(h1(i1))',
					':trigger:mht.tg1(h1,h2)',
					':trigger:mht.tg1(h1,h3)',
					':trigger:mht.tg1(h2,h3)'
				]
			]
		];
	}

	/**
	 * @dataProvider getTriggerPermissions
	 */
	public function testTriggerPermissions_get(array $login, array $triggerids, bool $editable,
			array $expected_triggerids): void {
		$this->authorize($login['username'], $login['password']);

		CTestDataHelper::convertValueReferences($triggerids);

		$result = $this->call('trigger.get', [
			'output' => [],
			'triggerids' => $triggerids,
			'editable' => $editable,
			'preservekeys' => true
		], null);

		$converted_expected_triggerids = CTestDataHelper::getConvertedValueReferences($expected_triggerids);

		$db_triggerids = array_keys($result['result']);

		foreach ($db_triggerids as &$db_actionid) {
			$i = array_search($db_actionid, $converted_expected_triggerids);

			if ($i !== false) {
				$db_actionid = $expected_triggerids[$i];
			}
		}
		unset($db_actionid);

		sort($expected_triggerids);
		sort($db_triggerids);

		$this->assertSame($expected_triggerids, $db_triggerids);
	}
}
