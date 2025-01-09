#!/usr/bin/env perl

#
# Copyright (C) 2001-2025 Zabbix SIA
#
# This program is free software: you can redistribute it and/or modify it under the terms of
# the GNU Affero General Public License as published by the Free Software Foundation, version 3.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
# without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License along with this program.
# If not, see <https://www.gnu.org/licenses/>.
#

#########################################
#### ABOUT ZABBIX SNMP TRAP RECEIVER ####
#########################################

# This is an embedded perl SNMP trapper receiver designed for sending data to the server.
# The receiver will pass the received SNMP traps to Zabbix server or proxy running on the
# same machine. Please configure the server/proxy accordingly.
#
# Read more about using embedded perl with Net-SNMP:
#	http://net-snmp.sourceforge.net/wiki/index.php/Tut:Extending_snmpd_using_perl

#################################################
#### ZABBIX SNMP TRAP RECEIVER CONFIGURATION ####
#################################################

### Option: SNMPTrapperFile
#	Temporary file used for passing data to the server (or proxy). Must be the same
#	as in the server (or proxy) configuration file.
#
# Mandatory: yes
# Default:
$SNMPTrapperFile = '/tmp/zabbix_traps.tmp';

### Option: DateTimeFormat
#	The date time format in strftime() format. Please make sure to have a corresponding
#	log time format for the SNMP trap items.
#
# Mandatory: yes
# Default:
$DateTimeFormat = '%Y-%m-%dT%T%z';

###################################
#### ZABBIX SNMP TRAP RECEIVER ####
###################################

use Fcntl qw(O_WRONLY O_APPEND O_CREAT);
use POSIX qw(strftime);
use NetSNMP::TrapReceiver;

sub get_header_regex
{
	my $format = shift;
	my $regex = $format;

	$regex =~ s/%Y/[0-9]{4}/g;
	$regex =~ s/%m/[0-9]{2}/g;
	$regex =~ s/%d/[0-9]{2}/g;
	$regex =~ s/%T/[0-9]{2}:[0-9]{2}:[0-9]{2}/g;
	$regex =~ s/%z/[+-][0-9]{4}/g;
	$regex =~ s/%H/[0-9]{2}/g;
	$regex =~ s/%M/[0-9]{2}/g;
	$regex =~ s/%S/[0-9]{2}/g;

	return "$regex ZBXTRAP";
}

sub zabbix_receiver
{
	my (%pdu_info) = %{$_[0]};
	my (@varbinds) = @{$_[1]};
	my $r = get_header_regex $DateTimeFormat;

	# fail if received vars clearly contain injection
	foreach my $x (@varbinds)
	{
		if ($x->[1] =~ /$r/)
		{
			return NETSNMPTRAPD_HANDLER_FAIL;
		}
	}

	# open the output file
	unless (sysopen(OUTPUT_FILE, $SNMPTrapperFile, O_WRONLY|O_APPEND|O_CREAT, 0666))
	{
		print STDERR "Cannot open [$SNMPTrapperFile]: $!\n";
		return NETSNMPTRAPD_HANDLER_FAIL;
	}

	# get the host name
	my $hostname = $pdu_info{'receivedfrom'} || 'unknown';
	if ($hostname ne 'unknown')
	{
		$hostname =~ /\[(.*?)\].*/;                    # format: "UDP: [127.0.0.1]:41070->[127.0.0.1]"
		$hostname = $1 || 'unknown';
	}

	# print trap header
	#       timestamp must be placed at the beginning of the first line (can be omitted)
	#       the first line must include the header "ZBXTRAP [IP/DNS address] "
	#              * IP/DNS address is the used to find the corresponding SNMP trap items
	#              * this header will be cut during processing (will not appear in the item value)
	printf OUTPUT_FILE "%s ZBXTRAP %s\n", strftime($DateTimeFormat, localtime), $hostname;

	# print the PDU info
	print OUTPUT_FILE "PDU INFO:\n";
	foreach my $key(keys(%pdu_info))
	{
		if ($pdu_info{$key} !~ /^[[:print:]]*$/)
		{
			my $OctetAsHex = unpack('H*', $pdu_info{$key});	# convert octet string to hex
			$pdu_info{$key} = "0x$OctetAsHex";		# apply 0x prefix for consistency
		}

		printf OUTPUT_FILE "  %-30s %s\n", $key, $pdu_info{$key};
	}

	# print the variable bindings:
	print OUTPUT_FILE "VARBINDS:\n";
	foreach my $x (@varbinds)
	{
		printf OUTPUT_FILE "  %-30s type=%-2d value=%s\n", $x->[0], $x->[2], $x->[1];
	}

	close (OUTPUT_FILE);

	return NETSNMPTRAPD_HANDLER_OK;
}

NetSNMP::TrapReceiver::register("all", \&zabbix_receiver) or
	die "failed to register Zabbix SNMP trap receiver\n";

print STDOUT "Loaded Zabbix SNMP trap receiver\n";
