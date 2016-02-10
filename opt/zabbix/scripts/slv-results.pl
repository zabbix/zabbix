#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}

use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

parse_opts('tld=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

$tld = getopt('tld');
my $from = getopt('from');
my $till = getopt('till');

if (!$tld || !$from || !$till)
{
	print("usage: $0 --tld <tld> --from <unixtime> --till <unixtime> [options]\n");
	exit(1);
}

set_slv_config(get_rsm_config());

db_connect();

my $rows_ref = db_select(
	"select i.key_,hi.clock,hi.value".
	" from items i,hosts h,history_uint hi".
	" where h.host='$tld'".
		" and i.hostid=h.hostid".
		" and i.itemid=hi.itemid".
		" and i.key_ like 'rsm.slv.%'".
		" and hi.clock between $from and $till".
        " order by hi.clock,hi.ns");

printf("%-30s %-40s %s\n", "KEY", "CLOCK", "VALUE");
print("----------------------------------------------------------------------------------------------------\n");
foreach my $row_ref (@$rows_ref)
{
	my $key = $row_ref->[0];
	my $clock = $row_ref->[1];
	my $value = $row_ref->[2];

    printf("%-30s %-40s %s\n", $key, ts_full($clock), $value);
}
