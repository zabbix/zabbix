#!/usr/bin/perl

use lib '/home/vl/svn/zabbix/branches/2.0.rsm/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

set_slv_config(get_rsm_config('/home/vl/svn/zabbix/branches/2.0.rsm/opt/zabbix/scripts/rsm.conf'));

setopt('nolog');

init_values();

my $hostname = "test_host";
my $key = "log[D:\\datalog\\test.log]";
my $timestamp = time();
my $value = "2016-11-25 15:10:49,187 WARN  [Test] TestMessage6";

push_value($hostname, $key, $timestamp, $value);

send_values();
