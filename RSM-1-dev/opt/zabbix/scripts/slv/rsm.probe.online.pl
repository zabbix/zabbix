#!/usr/bin/perl
#
# EPP downtime of current month in minutes

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

use constant PROBE_LASTACCESS_ITEM	=> 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';
use constant PROBE_KEY_MANUAL		=> 'rsm.probe.status[manual]';
use constant PROBE_KEY_AUTOMATIC	=> 'rsm.probe.status[automatic,%]';	# match all in SQL

use constant ONLINE	=> 1;
use constant OFFLINE	=> 0;

my $cfg_key_out = 'rsm.probe.online';

parse_opts('now=i');
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $probe_avail_limit = get_macro_probe_avail_limit();

my $now = (opt('now') ? getopt('now') : time() - PROBE_ONLINE_SHIFT);

my $from = truncate_from($now);
my $till = $from + 59;
my $value_ts = $from;

dbg("selected period: ", selected_period($from, $till), ", with value timestamp: ", ts_full($value_ts));

my $probes_ref = get_probes(ENABLED_DNS);

my $probe_times_ref = __get_probe_times($from, $till, $probes_ref);
my @online_probes = keys(%{$probe_times_ref});

init_values();

foreach my $probe (keys(%$probes_ref))
{
	my $itemid = get_probe_online_key_itemid($probe);

	fail(rsm_slv_error()) unless ($itemid);

	next if (!opt('dry-run') && uint_value_exists($value_ts, $itemid) == SUCCESS);

	my @result = grep(/^$probe$/, @online_probes);
	my $status = (@result ? UP : DOWN);

	my $status_str = ($status == UP ? "Up" : "Down");

	push_value("$probe - mon", $cfg_key_out, $value_ts, $status, $status_str);
}

send_values();

slv_exit(SUCCESS);

sub __get_probe_times
{
	my $from = shift;
	my $till = shift;
	my $probes_ref = shift; # { host => hostid, ... }

	my $probe_avail_limit = get_macro_probe_avail_limit();

	dbg("from:$from till:$till probe_avail_limit:$probe_avail_limit");

	my $result;

	# check probe lastaccess time
	foreach my $probe (keys(%$probes_ref))
	{
		my $host = "$probe - mon";

		my $itemid = get_itemid_by_host($host, PROBE_LASTACCESS_ITEM);
		if (!$itemid)
		{
			wrn("configuration error: ", rsm_slv_error());
			next;
		}

		my $times_ref = __get_lastaccess_times($itemid, $probe_avail_limit, $from, $till);

		my $hostid = $probes_ref->{$probe};

		if (scalar(@$times_ref) != 0)
		{
			dbg("$probe reachable times: ", join(',', @$times_ref)) if (opt('debug'));

			$times_ref = __get_probestatus_times($probe, $hostid, $times_ref, PROBE_KEY_MANUAL);
		}

		if (scalar(@$times_ref) != 0)
		{
			dbg("$probe manual probestatus times: ", join(',', @$times_ref)) if (opt('debug'));

			$times_ref = __get_probestatus_times($probe, $hostid, $times_ref, PROBE_KEY_AUTOMATIC);
		}

		if (scalar(@$times_ref) != 0)
		{
			dbg("$probe automatic probestatus times: ", join(',', @$times_ref)) if (opt('debug'));

			$result->{$probe} = $times_ref;
		}
	}

	return $result;
}

# Times when probe "lastaccess" within $probe_avail_limit.
sub __get_lastaccess_times
{
	my $itemid = shift;
	my $probe_avail_limit = shift;
	my $from = shift;
	my $till = shift;

	my ($rows_ref, @times, $last_status);

	# get the previous status
	$rows_ref = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$itemid".
			" and clock between ".($from-3600)." and ".($from-1).
		" order by itemid desc,clock desc".
		" limit 1");

	$last_status = UP;
	if (scalar(@$rows_ref) != 0)
	{
		my $clock = $rows_ref->[0]->[0];
		my $value = $rows_ref->[0]->[1];

		dbg("clock:$clock value:$value");

		$last_status = DOWN if ($clock - $value > $probe_avail_limit);
	}

	push(@times, truncate_from($from)) if ($last_status == UP);

	$rows_ref = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$itemid".
	    		" and clock between $from and $till".
	    		" and value!=0".
		" order by itemid,clock");

	foreach my $row_ref (@$rows_ref)
	{
		my $clock = $row_ref->[0];
		my $value = $row_ref->[1];

		my $status = ($clock - $value > $probe_avail_limit) ? DOWN : UP;

		if ($last_status != $status)
		{
			if ($status == UP)
			{
				$clock = truncate_from($clock);
			}
			else
			{
				$clock = truncate_till($clock);
			}

			push(@times, $clock);

			dbg("clock:$clock diff:", ($clock - $value));

			$last_status = $status;
		}
	}

	# push "till" to @times if it contains odd number of elements
	if (scalar(@times) != 0)
	{
		push(@times, $till) if ($last_status == UP);
	}

	return \@times;
}

sub __get_probestatus_times
{
	my $probe = shift;
	my $hostid = shift;
	my $times_ref = shift; # input
	my $key = shift;

	my ($rows_ref, @times, $last_status);

	my $key_match = "i.key_";
	$key_match .= ($key =~ m/%/) ? " like '$key'" : "='$key'";

	my $itemid;
	if ($key =~ m/%/)
	{
		$itemid = get_itemid_like_by_hostid($hostid, $key);
	}
	else
	{
		$itemid = get_itemid_by_hostid($hostid, $key);
	}

	if (!$itemid)
	{
		wrn("configuration error: ", rsm_slv_error());
		return;
	}

	$rows_ref = db_select(
		"select value".
		" from history_uint".
		" where itemid=$itemid".
			" and clock<" . $times_ref->[0].
		" order by clock desc".
		" limit 1");

	$last_status = UP;
	if (scalar(@$rows_ref) != 0)
	{
		my $value = $rows_ref->[0]->[0];

		$last_status = DOWN if ($value == OFFLINE);
	}

	my $idx = 0;
	my $times_count = scalar(@$times_ref);
	while ($idx < $times_count)
	{
		my $from = $times_ref->[$idx++];
		my $till = $times_ref->[$idx++];

		$rows_ref = db_select(
			"select clock,value".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between $from and $till".
			" order by itemid,clock");

		push(@times, $from) if ($last_status == UP);

		foreach my $row_ref (@$rows_ref)
		{
			my $clock = $row_ref->[0];
			my $value = $row_ref->[1];

			my $status = ($value == OFFLINE) ? DOWN : UP;

			if ($last_status != $status)
			{
				push(@times, $clock);

				dbg("clock:$clock value:$value");

				$last_status = $status;
			}
		}

		# push "till" to @times if it contains odd number of elements
		if (scalar(@times) != 0)
		{
			push(@times, $till) if ($last_status == UP);
		}
	}

	return \@times;
}
