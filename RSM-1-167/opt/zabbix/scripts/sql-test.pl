#!/usr/bin/perl

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

unless ($ARGV[0])
{
	print("usage: $0 <query>\n");
	exit(1);
}

parse_opts();

setopt('nolog');
setopt('debug');
setopt('warnslow', 0);

set_slv_config(get_rsm_config());

db_connect();

my $rows_ref = db_select($ARGV[0]);
$rows_ref = db_select("explain extended $ARGV[0]");

my @table;

my $r = $rows_ref->[0];
__putcol("select_type", $r->[1]);
__putcol("table", $r->[2]);
__putcol("type", $r->[3]);
__putcol("possible_keys", $r->[4]);
__putcol("key", $r->[5]);
__putcol("key_len", $r->[6]);
__putcol("ref", $r->[7]);
__putcol("rows", $r->[8]);
__putcol("filtered", $r->[9]);
__putcol("Extra", $r->[10]);

my $linelen = 0;
foreach my $ref (@table)
{
	my $maxlen = $ref->[0];
	$linelen += 3 + $maxlen;
}

__puthr($linelen);
foreach my $ref (@table)
{
	my $maxlen = $ref->[0];
	my $string = $ref->[1];
	my $blanks = $maxlen - length($string);

	print("| $string ");
	print(" ") while ($blanks--);
}
print("|\n");

__puthr($linelen);

foreach my $ref (@table)
{
	my $maxlen = $ref->[0];
	my $string = $ref->[2];
	my $blanks = $maxlen - length($string);

	print("| $string ");
	print(" ") while ($blanks--);
}
print("|\n");

__puthr($linelen);

sub __putcol
{
	my $title = shift;
	my $value = shift || "NULL";

	my $maxlen = length($title);
	my $vallen = length($value);

	$maxlen = $vallen if ($vallen > $maxlen);

	my @a;

	$a[0] = $maxlen;
	$a[1] = $title;
	$a[2] = $value;

	push(@table, \@a);
}

sub __puthr
{
	my $len = shift;

	print("+");
	$len--;
	print("-") while ($len--);
	print("+\n");
}
