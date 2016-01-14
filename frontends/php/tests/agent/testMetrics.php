<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

define('TYPE_UINT', 0);
define('TYPE_FLOAT', 1);
define('TYPE_ANY', 2);

define('ZBX_NOTSUPPORTED', '/ZBX_NOTSUPPORTED/');
define('ZBX_ACTIVE_ONLY', '/Accessible only as active check!/');

class testMetrics extends CZabbixTest {

	public static function metrics() {
		// List of all supported metrics by the agent
		// metric type regexp range_from range_to
		return array(
			array('',					TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array(' ',					TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('[agent.ping]',		TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping [zzzz]',	TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping zzzz',	TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping,zzzz',	TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping[]]',		TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.ping[0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9]',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('longlonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglonglongl',				TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('wrong_key',			TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),


			array('agent.ping',			TYPE_UINT,	'/1/',					1,	1),
			array('agent.ping[]',		TYPE_ANY,	ZBX_NOTSUPPORTED,					-1,	-1),
			array('agent.version',		TYPE_ANY,	'/[1-9]\.[0-9]\.[0-9]/',-1,	-1),
			array('kernel.maxfiles',	TYPE_UINT,	'',		-1,	-1),
			array('kernel.maxproc',		TYPE_UINT,	'',		-1,	-1),



			array('vfs.dev.read[]',									TYPE_UINT,	'',					-1,	-1),
			array('vfs.dev.read[sda]',								TYPE_UINT,	'',					-1,	-1),
			array('vfs.dev.read[sda,,avg1]',						TYPE_UINT,	'',					-1,	-1),
			array('vfs.dev.read[sda,bytes]',						TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,bytes,avg1]',					TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,bytes,avg5]',					TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,bytes,avg15]',					TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,bytes,avg15,wrong_param]',		TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,bytes,wrong_param]',			TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,operations]',					TYPE_UINT,	'',					-1,	-1),
			array('vfs.dev.read[sda,operations,avg1]',				TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,operations,avg5]',				TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,operations,avg15]',				TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,operations,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,operations,wrong_param]',		TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,ops]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,ops,avg1]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,ops,avg5]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,ops,avg15]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,ops,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.read[sda,ops,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.read[sda,sectors]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,sectors,avg1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,sectors,avg5]',	TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,sectors,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('vfs.dev.read[sda,sectors,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.read[sda,sectors,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.read[sda,sps]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,sps,avg1]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,sps,avg5]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,sps,avg15]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.read[sda,sps,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.read[sda,sps,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,,avg1]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,bytes]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,bytes,avg1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,bytes,avg5]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,bytes,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,bytes,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,bytes,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,operations]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,operations,avg1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,operations,avg5]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,operations,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,operations,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,operations,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,ops]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,ops,avg1]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,ops,avg5]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,ops,avg15]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,ops,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,ops,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,sectors]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,sectors,avg1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,sectors,avg5]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,sectors,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,sectors,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,sectors,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.dev.write[sda,sps]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,sps,avg1]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,sps,avg5]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,sps,avg15]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.dev.write[sda,sps,avg15,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.cksum[/etc/doesnotexist]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.cksum[/etc/passwd]',	TYPE_UINT,	'',		1000,	-1),
			array('vfs.file.cksum[/etc/passwd,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.exists[/etc/doesnotexist]',	TYPE_UINT,	'/^0$/',		0,	0),
			array('vfs.file.exists[/etc/passwd]',	TYPE_UINT,	'/1/',		1,	1),
			array('vfs.file.exists[/etc/passwd,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.exists[/proc/1/root]',	TYPE_UINT,	'/^0$/',		0,	0),
			array('vfs.file.contents[/etc/passwd]',	TYPE_ANY,	'/root/',		-1,	-1),
			array('vfs.file.contents[/etc/doesnotexist]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.regexp[/etc/doesnotexist,root]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.regexp[/etc/passwd,root]',	TYPE_ANY,	'/root/',		-1,	-1),
			array('vfs.file.regexp[/etc/passwd,notfound]',	TYPE_ANY,	'/EOF/',		-1,	-1),
			array('vfs.file.regmatch[/etc/doesnotexist,root]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.regmatch[/etc/passwd,notfound]',	TYPE_UINT,	'/0/',		0,	0),
			array('vfs.file.regmatch[/etc/passwd,root]',	TYPE_UINT,	'/1/',		1,	1),
			array('vfs.file.regmatch[/etc/passwd,root,utf8]',	TYPE_UINT,	'/1/',		1,	1),
			array('vfs.file.regmatch[/etc/passwd,root,wrong_encoding]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.md5sum[/etc/doesnotexist]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.md5sum[/etc/passwd]',	TYPE_ANY,	'/[a-f0-9]{32}/',		-1,	-1),
			array('vfs.file.md5sum[/etc/passwd,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.size[/etc/passwd]',	TYPE_UINT,	'',		128,	1000000),
			array('vfs.file.size[/etc/doesnotexist]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.size[/proc/1/root]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.time[/etc/doesnotexist,modify]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.file.time[/etc/passwd,access]',	TYPE_UINT,	'',		128,	-1),
			array('vfs.file.time[/etc/passwd,change]',	TYPE_UINT,	'',		128,	-1),
			array('vfs.file.time[/etc/passwd,modify]',	TYPE_UINT,	'',		128,	-1),
			array('vfs.file.time[/etc/passwd,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.fs.discovery',	TYPE_ANY,	'/FSNAME/',		-1,	-1),
			array('vfs.fs.discovery',	TYPE_ANY,	'/dev/',		-1,	-1),
			array('vfs.fs.inode[/]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.inode[/,free]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.inode[/,pfree]',	TYPE_UINT,	'',		0,	100),
			array('vfs.fs.inode[/,pused]',	TYPE_UINT,	'',		0,	100),
			array('vfs.fs.inode[/,total]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.inode[/,used]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.inode[/,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('vfs.fs.size[/]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.size[/,free]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.size[/,pfree]',	TYPE_UINT,	'',		0,	100),
			array('vfs.fs.size[/,pused]',	TYPE_UINT,	'',		0,	100),
			array('vfs.fs.size[/,total]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.size[/,used]',	TYPE_UINT,	'',		-1,	-1),
			array('vfs.fs.size[/,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.boottime',	TYPE_UINT,	'',		128,	-1),
			array('system.cpu.intr',	TYPE_UINT,	'',		-1,	-1),
			array('system.cpu.load[all,avg1]',	TYPE_FLOAT,	'',		0.001,	10),
			array('system.cpu.load[all,avg5]',	TYPE_FLOAT,	'',		0.001,	10),
			array('system.cpu.load[all,avg15]',	TYPE_FLOAT,	'',		0.001,	10),
//			array('system.cpu.load[1,avg1]',	TYPE_FLOAT,	'',		0.001,	10),
//			array('system.cpu.load[1,avg5]',	TYPE_FLOAT,	'',		0.001,	10),
//			array('system.cpu.load[1,avg15]',	TYPE_FLOAT,	'',		0.001,	10),
			array('system.cpu.load[128,avg1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.load[128,avg5]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.load[128,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.num[]',	TYPE_UINT,	'',		1,	16),
			array('system.cpu.num[max]',	TYPE_UINT,	'',		1,	16),
			array('system.cpu.num[online]',	TYPE_UINT,	'',		1,	16),
			array('system.cpu.num[wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.switches',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.util[]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[,,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[,user]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,idle,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,idle,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,idle,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,interrupt,avg1]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,interrupt,avg5]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,interrupt,avg15]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,iowait,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,iowait,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,iowait,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,nice,avg1]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,nice,avg5]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,nice,avg15]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,softirq,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,softirq,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,softirq,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,steal,avg1]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,steal,avg5]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,steal,avg15]',	TYPE_FLOAT,	'',		0.0,	100),
			array('system.cpu.util[all,system,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,system,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,system,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,user,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,user,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[all,user,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[0,user,avg1]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[0,user,avg5]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[0,user,avg15]',	TYPE_FLOAT,	'',		0.001,	100),
			array('system.cpu.util[128,user,avg1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.util[128,user,avg5]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.util[128,user,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.util[wrong_param,user,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.util[all,wrong_param,avg15]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.cpu.util[all,user,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.hostname[]',	TYPE_ANY,	'/hudson/',		-1,	-1),
			array('system.hostname[wrong_parameter]',	TYPE_ANY,	'/hudson/',		-1,	-1),
			array('eventlog[system]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('log[logfile]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('logrt[logfile]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.collisions[lo]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.collisions[eth0]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.collisions[eth1]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.collisions[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.discovery',	TYPE_ANY,	'/IFNAME/',		-1,	-1),
			array('net.if.discovery',	TYPE_ANY,	'/eth/',		-1,	-1),
			array('net.if.in[lo,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.in[]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.in[eth1]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.in[eth1,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.in[eth1,dropped]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.in[eth1,errors]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.in[eth1,packets]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.in[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.in[eth1,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.out[lo,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.out[]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.out[eth1]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.out[eth1,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.out[eth1,dropped]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.out[eth1,errors]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.out[eth1,packets]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.out[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.out[eth1,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.total[lo,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.total[]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.total[eth1]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.total[eth1,bytes]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.total[eth1,dropped]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.total[eth1,errors]',	TYPE_UINT,	'',		-1,	-1),
			array('net.if.total[eth1,packets]',	TYPE_UINT,	'',		128,	-1),
			array('net.if.total[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.if.total[eth1,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.tcp.listen[]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.tcp.listen[1234]',	TYPE_UINT,	'/0/',		0,	0),
			array('net.tcp.listen[80]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.tcp.listen[80,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.tcp.listen[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.udp.listen[]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.udp.listen[1234]',	TYPE_UINT,	'/0/',		0,	0),
			array('net.udp.listen[68]',	TYPE_UINT,	'/1/',		1,	1),
			array('net.udp.listen[68,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('net.udp.listen[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('sensor[w83781d-i2c-0-2d,temp1]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.localtime[]',	TYPE_UINT,	'',						-1,	-1),
			array('system.localtime[local]',	TYPE_ANY,	'/20/',						-1,	-1),
			array('system.localtime[utc]',	TYPE_UINT,	'',						-1,	-1),
			array('system.localtime[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.run[echo test]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.sw.arch',	TYPE_ANY,	'/86/',		-1,	-1),
			array('system.sw.arch[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.sw.os[]',	TYPE_ANY,	'/Ubuntu/',		-1,	-1),
			array('system.sw.os[full]',	TYPE_ANY,	'/Ubuntu/',		-1,	-1),
			array('system.sw.os[name]',	TYPE_ANY,	'/Ubuntu/',		-1,	-1),
			array('system.sw.os[short]',	TYPE_ANY,	'/generic/',		-1,	-1),
			array('system.sw.os[short,wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.sw.os[wrong_param]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.swap.in[]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.in[all]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.in[all,count]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.in[all,sectors]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.in[all,pages]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.in[all,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.swap.in[wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.swap.size[]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[,free]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[all,]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[all,free]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[all,pfree]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[all,pused]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[all,total]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.size[/dev/sda5,total]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.swap.out[]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.out[all]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.out[all,count]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.out[all,sectors]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.out[all,pages]',	TYPE_UINT,	'',		-1,	-1),
			array('system.swap.out[all,wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.swap.out[wrong_option]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
			array('system.uname',	TYPE_ANY,	'/Linux/',		-1,	-1),
			array('system.uptime',	TYPE_UINT,	'',		128,	-1),
			array('system.users.num',	TYPE_UINT,	'',		0,	64),
			array('vm.memory.size[]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[available]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[buffers]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[cached]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[free]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[pfree]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[shared]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[total]',	TYPE_UINT,	'',		-1,	-1),
			array('vm.memory.size[wrong_parameter]',	TYPE_ANY,	ZBX_NOTSUPPORTED,		-1,	-1),
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

// All tests below this line should be enhanced

			array('net.dns[,zabbix.com]',			TYPE_UINT,	'/1/',				1,	1),
			array('net.dns.record[,zabbix.com]',	TYPE_ANY,	'/zabbix\.com/',	-1,	-1),
			array('net.tcp.dns[,zabbix.com]',		TYPE_UINT,	'/1/',				1,	1),
			array('net.tcp.dns.query[,zabbix.com]',	TYPE_ANY,	'/zabbix\.com/',	-1,	-1),
			array('net.tcp.port[,80]',				TYPE_UINT,	'/1/',				1,	1),
// TODO
			array('net.tcp.service[ssh,127.0.0.1,22]',		TYPE_UINT,	'/1/',				1,	1),
			array('net.tcp.service.perf[ssh,127.0.0.1,22]',	TYPE_FLOAT,	'',					0.001,	10),
			array('proc.num[inetd,,,]',						TYPE_ANY,	'/0/',				0,	0),
			array('proc.mem[inetd,,]',						TYPE_ANY,	'/0/'		,		0,	0),
			array('system.hw.chassis[]',					TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
			array('system.hw.cpu[]',						TYPE_ANY,	'/MHz/',			-1,	-1),
			array('system.hw.macaddr[]',					TYPE_ANY,	'/eth[0-9]/',		-1,	-1),
			array('system.sw.packages[]',					TYPE_ANY,	'/php/',			-1,	-1),
			array('wmi.get[root\\cimv2,select Caption from Win32_OperatingSystem]',	TYPE_ANY,	ZBX_NOTSUPPORTED,	-1,	-1),
		);
	}

	/**
	* @dataProvider metrics
	*/
	public function testMetrics_remoteGet($metric, $type, $pattern, $range_from, $range_to) {
		$agent_ip = "127.0.0.1";
		$binary = "/home/hudson/public_html/".PHPUNIT_URL."/bin/zabbix_get";

		$cmd = "$binary -s $agent_ip -k '$metric'";

		$result = chop(shell_exec($cmd));

// Validate value type
		switch ($type) {
		case TYPE_UINT:
			$this->assertTrue(is_numeric($result), "I was expecting unsigned integer but got: \n".print_r($result, true).' for metric '.$metric);
			$this->assertTrue(preg_match('/[0-9]{1,20}/', $result) > 0, "I was expecting unsigned integer but got: \n".print_r($result, true).' for metric '.$metric);
			break;
		case TYPE_FLOAT:
			$this->assertTrue(is_float((float)$result), "I was expecting float number but got: \n".print_r($result, true).' for metric '.$metric);
			break;
		}

		if ($range_from != -1) {
			$this->assertTrue($result >= $range_from, "I was expecting result to be more or equal to $range_from but got: \n".print_r($result, true).' for metric '.$metric);
		}

		if ($range_to != -1) {
			$this->assertTrue($result <= $range_to, "I was expecting result to be less or equal to $range_to but got: \n".print_r($result, true).' for metric '.$metric);
		}


// Validate regexp
		if ($pattern != '') {
			$this->assertTrue(preg_match($pattern, $result) > 0, "I was expecting: \n".print_r($pattern, true)."but got: \n".print_r($result, true).' for metric '.$metric);
		}
	}
}
