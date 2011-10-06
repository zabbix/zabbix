<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../include/class.czabbixtest.php');

define('TYPE_UINT', 0);
define('TYPE_FLOAT', 1);
define('TYPE_ANY', 2);

define('ZBX_NOTSUPPORTED', '/ZBX_NOTSUPPORTED/');
define('ZBX_ACTIVE_ONLY', '/Accessible only as active check!/');

class testMetrics extends CZabbixTest
{


	public static function metrics()
	{
		// List of all supported metrics by the agent
		// metric type regexp range_from range_to
		return array(
			array('',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array(' ',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('[agent.ping]',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping hehe',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping hehe',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping,hehe',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping[]]',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('longlonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglong',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('wrong_key',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),


			array('agent.ping',				TYPE_UINT,	'/1/',					1,	1),
			array('agent.ping[]',				TYPE_UINT,	'/1/',					1,	1),
			array('agent.version',			TYPE_ANY,	'/[1-9]\.[0-9]\.[0-9]/',-1,	-1),
			array('web.page.get[localhost,,80]',	TYPE_ANY,	'/200 OK/',		-1,	-1),
			array('web.page.get[localhost,index.html,80]',	TYPE_ANY,	'/200 OK/',		-1,	-1),
			array('web.page.get[localhost,doesnotexist.html,80]',	TYPE_ANY,	'/404 Not Found/',		-1,	-1),
			array('web.page.get[localhost,,1234]',	TYPE_ANY,	'/EOF/',		-1,	-1),
			array('web.page.perf[localhost,,80]',	TYPE_FLOAT,	'',		0,	10),
			array('web.page.perf[localhost,index.html,80]',	TYPE_FLOAT,	'',		0,	10),
			array('web.page.perf[localhost,doesnotexist.html,80]',	TYPE_FLOAT,	'',		0,	10),
			array('web.page.perf[localhost,,1234]',	TYPE_FLOAT,	'',		0,	10),
			array('web.page.regexp[localhost,,80,OK]',	TYPE_ANY,	'/OK/',		-1,	-1),
			array('web.page.regexp[localhost,index.html,80,OK]',	TYPE_ANY,	'/OK/',		-1,	-1),
			array('web.page.regexp[localhost,,1234,OK]',	TYPE_ANY,	'/EOF/',		-1,	-1),
			array('web.page.regexp[localhost,doesnotexist.html,80,OK]',	TYPE_ANY,	'/EOF/',		-1,	-1),
			array('vfs.file.size[/etc/passwd]',	TYPE_UINT,	'',		128,	1000000),
			array('vfs.file.size[/etc/doesnotexist]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.size[/proc/1/root]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.time[/etc/doesnotexist,modify]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.time[/etc/passwd,access]',	TYPE_UINT,	'',		128,	-1),
			array('vfs.file.time[/etc/passwd,change]',	TYPE_UINT,	'',		128,	-1),
			array('vfs.file.time[/etc/passwd,modify]',	TYPE_UINT,	'',		128,	-1),
			array('vfs.file.time[/etc/passwd,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.exists[/etc/doesnotexist]',	TYPE_UINT,	'/^0$/',		0,	0),
			array('vfs.file.exists[/etc/passwd]',	TYPE_UINT,	'/1/',		1,	1),
			array('vfs.file.exists[/etc/passwd,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.exists[/proc/1/root]',	TYPE_UINT,	'/^0$/',		0,	0),
			array('vfs.file.contents[/etc/passwd]',	TYPE_ANY,	'/root/',		-1,	-1),
			array('vfs.file.contents[/etc/doesnotexist]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),

			array('vfs.file.regexp[/etc/doesnotexist,root]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.regexp[/etc/passwd,root]',	TYPE_ANY,	'/root/',		-1,	-1),
			array('vfs.file.regexp[/etc/passwd,notfound]',	TYPE_ANY,	'/EOF/',		-1,	-1),

			array('vfs.file.regmatch[/etc/passwd,root]',	TYPE_UINT,	'/1/',		1,	1),
			array('vfs.file.md5sum[/etc/passwd]',	TYPE_ANY,	'/[a-f0-9]{32}/',		-1,	-1),
			array('vfs.file.cksum[/etc/passwd]',	TYPE_UINT,	'',		1000,	-1),
			array('net.dns[,zabbix.com]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.dns.record[,zabbix.com]',	TYPE_ANY,	'/zabbix\.com/',		-1,	-1),
			array('net.tcp.dns[,zabbix.com]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.tcp.dns.query[,zabbix.com]',	TYPE_ANY,	'/zabbix\.com/',		-1,	-1),
			array('net.tcp.port[,80]',	TYPE_UINT,	'/1/',		1,	1),
// TODO
			array('log[logfile]',	TYPE_ANY,	ZBX_ACTIVE_ONLY,		-1,	-1),
			array('logrt[logfile]',	TYPE_ANY,	ZBX_ACTIVE_ONLY,		-1,	-1),
			array('eventlog[system]',	TYPE_ANY,	ZBX_ACTIVE_ONLY,		-1,	-1),
			array('kernel.maxfiles',	TYPE_UINT,	'',		-1,	-1),
			array('kernel.maxproc',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.size[/,free]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.inode[/,free]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.discovery',	TYPE_ANY,	'/FSNAME/',		-1,	-1),
			array('vfs.dev.read[sda,operations]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,sectors]',	TYPE_UINT,	'',		-1,	-1),
			array('net.tcp.listen[80]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.udp.listen[68]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.if.in[lo,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.out[lo,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.total[lo,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.collisions[lo]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.discovery',	TYPE_ANY,	'/IFNAME/',		-1,	-1),
			array('net.tcp.service[ssh,127.0.0.1,22]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.tcp.service.perf[ssh,127.0.0.1,22]',	TYPE_FLOAT,	'',		0.001,	10),
			array('proc.num[inetd,,,]',	TYPE_ANY,	'/0/',		0,	0),
			array('proc.mem[inetd,,]',	TYPE_ANY,	'/0/',		0,	0),
			array('system.boottime',	TYPE_UINT,	'',		128,	-1),
			array('system.cpu.switches',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.intr',	TYPE_UINT,	'',		-1,	-1),
			array('system.cpu.util[all,user,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,user,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,user,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.load[all,avg1]',	TYPE_FLOAT,	'',		0.001,	10),
			array('system.cpu.num[online]',	TYPE_UINT,	'',		1,	16),
			array('system.hostname[]',	TYPE_ANY,	'',		-1,	-1),
			array('system.hw.chassis[]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.hw.cpu[]',	TYPE_ANY,	'/MHz/',		-1,	-1),
			array('system.hw.macaddr[]',	TYPE_ANY,	'/eth0/',		-1,	-1),
			array('system.localtime[utc]',	TYPE_UINT,	'',						-1,	-1),
			array('system.run[echo test]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.sw.arch',	TYPE_ANY,	'/i686/',		-1,	-1),
			array('system.sw.os[]',	TYPE_ANY,	'/Linux/',		-1,	-1),
			array('system.sw.packages[]',	TYPE_ANY,	'/php/',		-1,	-1),
			array('system.swap.size[all,free]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.in[all]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.out[all]',	TYPE_UINT,	'',		-1,	-1),
			array('system.uname',	TYPE_ANY,	'/Linux/',		-1,	-1),
			array('system.uptime',	TYPE_UINT,	'',		128,	-1),
			array('system.users.num',	TYPE_UINT,	'',		0,	64),
			array('sensor[w83781d-i2c-0-2d,temp1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vm.memory.size[total]',	TYPE_UINT,	'',		-1,	-1),
		);
	}

	/**
	* @dataProvider metrics
	*/
	public function testMetrics_remoteGet($metric, $type, $pattern, $range_from, $range_to)
	{
		$agent_ip = "127.0.0.1";
		$binary = "/home/hudson/public_html/".PHPUNIT_URL."/zabbix_get";

		$cmd = "$binary -s $agent_ip -k '$metric'";

		$result = chop(shell_exec($cmd));

// Validate value type
		switch($type){
		case TYPE_UINT:
			$this->assertTrue(is_numeric($result), "I was expecting unsigned integer but got: \n".print_r($result, true).' for metric '.$metric);
			$this->assertTrue(preg_match('/[0-9]{1,20}/',$result)>0, "I was expecting unsigned integer but got: \n".print_r($result, true).' for metric '.$metric);
		break;
		case TYPE_FLOAT:
			$this->assertTrue(is_float((float)$result), "I was expecting float number but got: \n".print_r($result, true).' for metric '.$metric);
		break;
		}

		if($range_from!=-1){
			$this->assertTrue($result >= $range_from, "I was expecting result to be more or equal to $range_from but got: \n".print_r($result, true).' for metric '.$metric);
		}

		if($range_to!=-1){
			$this->assertTrue($result <= $range_to, "I was expecting result to be less or equal to $range_to but got: \n".print_r($result, true).' for metric '.$metric);
		}


// Validate regexp
		if($pattern != '')
		{
			$this->assertTrue(preg_match($pattern, $result)>0, "I was expecting: \n".print_r($pattern, true)."but got: \n".print_r($result, true).' for metric '.$metric);
		}
	}
}
?>
