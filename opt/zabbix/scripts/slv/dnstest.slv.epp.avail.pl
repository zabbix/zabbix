#!/usr/bin/perl -w
#
# EPP availability

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.epp[{$DNSTEST.TLD}';
my $cfg_key_out = 'dnstest.slv.epp.avail';

parse_opts();
exit_if_running();

set_slv_config(get_dnstest_config());

db_connect();

my $interval = get_macro_epp_delay();
my $cfg_minonline = get_macro_epp_probe_online();
my $probe_avail_limit = get_macro_probe_avail_limit();

my ($from, $till, $value_ts) = get_interval_bounds($interval);

my $probes_ref = get_online_probes($from, $till, $probe_avail_limit, undef);
my $online_probes = scalar(@$probes_ref);
my $tlds_ref = get_tlds('EPP');

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    my $lastclock = get_lastclock($tld, $cfg_key_out);
    next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

    if ($online_probes < $cfg_minonline)
    {
	info("success ($online_probes probes are online, min - $cfg_minonline)");
	push_value($tld, $cfg_key_out, $value_ts, UP);
	next;
    }

    my $hostids_ref = probes2tldhostids($tld, $probes_ref);
    if (scalar(@$hostids_ref) == 0)
    {
        wrn("no probe hosts found");
        next;
    }

    my $items_ref = get_items_by_hostids($hostids_ref, $cfg_key_in, 0); # not complete key
    if (scalar(@$items_ref) == 0)
    {
        wrn("no items ($cfg_key_in) found");
        next;
    }

    my $values_ref = get_item_values($items_ref, $from, $till);
    my $probes_with_values = scalar(keys(%$values_ref));
    if ($probes_with_values < $cfg_minonline)
    {
	info("success ($probes_with_values online probes have results, min - $cfg_minonline)");
	push_value($tld, $cfg_key_out, $value_ts, UP);
	next;
    }

    my $success_probes = 0;
    foreach my $itemid (keys(%$values_ref))
    {
	my $probe_result = check_item_values($values_ref->{$itemid});

	$success_probes++ if (SUCCESS == $probe_result);

	my $hostid = -1;
	foreach (@$items_ref)
	{
	    if ($_->{'itemid'} == $itemid)
	    {
		$hostid = $_->{'hostid'};
	    }
	}

	dbg("  i:$itemid (h:$hostid): ", (SUCCESS == $probe_result ? "success" : "fail"), " (values: ", join(', ', @{$values_ref->{$itemid}}), ")");
    }

    my $test_result = DOWN;
    my $perc = $success_probes * 100 / scalar(@$items_ref);
    $test_result = UP if ($perc > SLV_UNAVAILABILITY_LIMIT);

    info(($test_result == UP ? "success" : "fail"), " ($perc% UP)");
    push_value($tld, $cfg_key_out, $value_ts, $test_result);
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);

# SUCCESS - no values or at least one successful value
# FAIL - all values unsuccessful
sub check_item_values
{
    my $values_ref = shift;

    return SUCCESS if (scalar(@$values_ref) == 0);

    foreach (@$values_ref)
    {
	return SUCCESS if ($_ == UP);
    }

    return FAIL;
}
