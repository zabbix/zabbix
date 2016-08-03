#!/usr/bin/perl

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use Getopt::Long;
use Data::Dumper;
use RSM;
use TLD_constants qw(:general :templates :api :config);
use TLDs;

use constant LINUX_TEMPLATEID => 10001;

use constant HOST_STATUS_NOT_MONITORED => 1;

use constant HOST_STATUS_PROXY_ACTIVE => 5;

use constant true => 1;
use constant false => 0;

my %macros = ('{$RSM.EPP.ENABLED}' => 0, '{$RSM.IP4.ENABLED}' => 0, '{$RSM.IP6.ENABLED}' => 0,
		'{$RSM.RDDS43.ENABLED}' => 0, '{$RSM.RDDS80.ENABLED}' => 0, '{$RSM.RDAP.ENABLED}' => 0);

sub add_probe($$);
sub delete_probe($);
sub disable_probe($);

sub validate_input;
sub usage;

my %OPTS;
my $rv = GetOptions(\%OPTS, "probe=s", "ip=s",
			    "epp!", "ipv4!", "ipv6!", "rdds43!", "rdds80!", "rdap!", "resolver=s",
                	    "delete!", "disable!", "add!",
                	    "verbose!", "quiet!", "help|?");

usage() if ($OPTS{'help'} or not $rv);

validate_input();

my $config = get_rsm_config();

zbx_connect($config->{'zapi'}->{'url'}, $config->{'zapi'}->{'user'}, $config->{'zapi'}->{'password'});

if ($OPTS{'delete'}) {
    delete_probe($OPTS{'probe'});
}
elsif ($OPTS{'disable'}) {
    disable_probe($OPTS{'probe'});
}
elsif ($OPTS{'add'}) {
    add_probe($OPTS{'probe'}, $OPTS{'ip'});
}

exit;

################

sub add_probe($$) {
    my $probe_name = shift;
    my $probe_ip = shift;

    my ($probe, $probe_hostgroup, $probe_host, $probe_host_mon, $probe_tmpl, $probe_tmpl_status);

    my ($result, $probes_groupid, $probes_mon_groupid, $probe_tmpl_health);

    print "Trying to add '".$probe_name."' probe...\n";

    if (is_probe_exist($probe_name)) {
	print "The probe with name '".$probe_name."' already exists! Trying to enable it\n";
    }

    ###### Checking and creating required groups and templates

    print "Getting 'Probes' host group: ";
    $probes_groupid = create_group('Probes');
    is_not_empty($probes_groupid, true);

    print "Getting 'Probes - Mon' host group: ";
    $probes_mon_groupid = create_group('Probes - Mon');
    is_not_empty($probes_mon_groupid, true);

    print "Getting 'Template Proxy Health' template: ";
    $probe_tmpl_health = create_template('Template Proxy Health', LINUX_TEMPLATEID);
    is_not_empty($probe_tmpl_health, true);

    ########## Creating new Probe

    print "Creating '$probe_name' with '$probe_ip' IP: ";

    $probe = create_passive_proxy($probe_name, $probe_ip);

    is_not_empty($probe, true);

    ########## Creating new Host Group

    print "Creating '$probe_name' host group: ";

    $probe_hostgroup = create_group($probe_name);

    is_not_empty($probe_hostgroup, true);

    ########## Creating Probe template

    print "Creating '$probe_name' template: ";

	$probe_tmpl = create_probe_template($probe_name, $OPTS{'epp'}, $OPTS{'ipv4'}, $OPTS{'ipv6'}, $OPTS{'rdds43'},
			$OPTS{'rdds80'}, $OPTS{'rdap'}, $OPTS{'resolver'});

    is_not_empty($probe_tmpl, true);

    ########## Creating Probe status template

    print "Creating '$probe_name' probe status template: ";

    my $root_servers_macros = update_root_servers();
    $probe_tmpl_status = create_probe_status_template($probe_name, $probe_tmpl, $root_servers_macros);

    is_not_empty($probe_tmpl_status, true);

    ########## Creating Probe host

    print "Creating '$probe_name' host: ";
    $probe_host = create_host({'groups' => [{'groupid' => $probe_hostgroup}, {'groupid' => $probes_groupid}],
                                          'templates' => [{'templateid' => $probe_tmpl_status}],
                                          'host' => $probe_name,
                                          'status' => HOST_STATUS_MONITORED,
                                          'proxy_hostid' => $probe,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
                                                            'ip'=> '127.0.0.1',
                                                            'dns' => '', 'port' => '10050'}]
                });

    is_not_empty($probe_host);

    ########## Creating Probe monitoring host

    print "Creating Probe monitoring host: ";
    $probe_host_mon = create_host({'groups' => [{'groupid' => $probes_mon_groupid}],
                                          'templates' => [{'templateid' => $probe_tmpl_health}],
                                          'host' => $probe_name.' - mon',
                                          'status' => HOST_STATUS_MONITORED,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
                                                            'ip'=> $probe_ip,
                                                            'dns' => '', 'port' => '10050'}]
                            });

    is_not_empty($probe_host_mon, true);

    create_macro('{$RSM.PROXY_NAME}', $probe_name, $probe_host_mon, true);

    ########## Creating TLD hosts for the Probe

    my $tld_list = get_host_group('TLDs', true);

    print "Creating TLD hosts for the Probe...\n";

    foreach my $tld (@{$tld_list->{'hosts'}}) {
	my $tld_name = $tld->{'name'};
	my $tld_groupid = create_group('TLD '.$tld_name);

	my $main_templateid = create_template('Template '.$tld_name);

	print "Creating '$tld_name $probe_name' host for '$tld_name' TLD: ";

	my $tld_host = create_host({'groups' => [{'groupid' => $tld_groupid}, {'groupid' => $probe_hostgroup}],
                                          'templates' => [{'templateid' => $main_templateid}, {'templateid' => $probe_tmpl}],
                                          'host' => $tld_name.' '.$probe_name,
                                          'proxy_hostid' => $probe,
                                          'status' => HOST_STATUS_MONITORED,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});

	is_not_empty($tld_host, false);
    }

    ##########

    print "The probe has been added successful\n";
    print "Do not forget to tune macros!\n";
}

sub delete_probe($) {
    my $probe_name = shift;

    my ($probe, $probe_hostgroup, $probe_host, $probe_host_mon, $probe_tmpl, $probe_tmpl_status);

    my ($result);

    print "Trying to remove '".$probe_name."' probe...\n";

    $probe = get_probe($probe_name, true);

    check_probe_data($probe, "The probe is not found. Terminating...");

    $probe_host = get_host($probe_name, false);

    check_probe_data($probe_host, "The probe host is not found", false);

    $probe_host_mon = get_host($probe_name.' - mon', false);

    check_probe_data($probe_host_mon, "Probe monitoring host with name '$probe_name - mon' is not found", false);

    $probe_tmpl = get_template('Template '.$probe_name, true, false);

    check_probe_data($probe_tmpl, "Probe monitoring template with name 'Template $probe_name' is not found", false);

    $probe_tmpl_status = get_template('Template '.$probe_name.' Status', false, false);

    check_probe_data($probe_tmpl_status, "Probe Status monitoring template with name 'Template $probe_name Status' is not found", false);

    $probe_hostgroup = get_host_group($probe_name, false);

    check_probe_data($probe_hostgroup, "Host group with name '$probe_name' is not found", false);

    ########## Deleting probe template
    if (keys %{$probe_tmpl}) {
	my $templateid = $probe_tmpl->{'templateid'};
	my $template_name = $probe_tmpl->{'host'};

	print "Trying to remove '$template_name' probe template: ";

        $result = remove_templates([ $templateid ]);

	is_not_empty($result->{'templateids'}, false);
    }

    ########## Deleting probe template status
    if (keys %{$probe_tmpl_status}) {
	my $templateid = $probe_tmpl_status->{'templateid'};
        my $template_name = $probe_tmpl_status->{'host'};

	print "Trying to remove '$template_name' probe template status: ";

	$result = remove_templates([ $templateid ]);

	is_not_empty($result->{'templateids'}, false);
    }

    ########## Deleting probe host
    if (keys %{$probe_host}) {
        my $hostid = $probe_host->{'hostid'};
        my $host_name = $probe_host->{'host'};

        print "Trying to remove '$host_name' probe host: ";

        $result = remove_hosts( [ {'hostid' => $hostid} ] );

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Deleting TLDs and probe host linked to the Probe
    foreach my $host (@{$probe->{'hosts'}}) {
        my $host_name = $host->{'host'};
        my $hostid = $host->{'hostid'};

        print "Trying to remove '$host_name' host: ";

        $result = remove_hosts( [ {'hostid' => $hostid} ] );

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Deleting probe status monitoring host linked to the Probe
    if (keys %{$probe_host_mon}) {
	my $host_name = $probe_host_mon->{'host'};
	my $hostid = $probe_host_mon->{'hostid'};

	print "Trying to delete '$host_name' host: ";

	$result = remove_hosts( [ {'hostid' => $hostid} ] );

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Deleting Probe group
    if (keys %{$probe_hostgroup}) {
	my $hostgroupid = $probe_hostgroup->{'groupid'};

	print "Trying to remove '$probe_name' host group: ";

        $result = remove_hostgroups( [ $hostgroupid ] );

	is_not_empty($result->{'groupids'}, false);
    }

    ########## Deleting Probe
    print "Trying to remove '$probe_name' Probe: ";

    $result = remove_probes( [ {'proxyid' => $probe->{'proxyid'}} ] );

    is_not_empty($result->{'proxyids'}, false);

    ##########

    print "The probe has been removed successful\n";
    print "Do not forget to tune macros!\n";
}

sub disable_probe($) {
    my $probe_name = shift;

    my ($probe, $probe_hostgroup, $probe_host, $probe_host_mon, $probe_tmpl, $probe_tmpl_status);

    my ($result);

    print "Trying to disable '".$probe_name."' probe...\n";

    $probe = get_probe($probe_name, true);

    check_probe_data($probe, "The probe is not found. Terminating...");

    $probe_host = get_host($probe_name, false);

    check_probe_data($probe_host, "The probe host is not found", false);

    $probe_host_mon = get_host($probe_name.' - mon', false);

    check_probe_data($probe_host_mon, "Probe monitoring host with name '$probe_name - mon' is not found", false);

    $probe_tmpl = get_template('Template '.$probe_name, true, false);

    check_probe_data($probe_tmpl, "Probe monitoring template with name 'Template $probe_name' is not found", false);

    $probe_tmpl_status = get_template('Template '.$probe_name.' Status', false, false);

    check_probe_data($probe_tmpl_status, "Probe Status monitoring template with name 'Template $probe_name Status' is not found", false);


    ########## Disabling TLDs linked to the probe and Probe monitoring host

    foreach my $host (@{$probe->{'hosts'}}) {
	my $host_name = $host->{'host'};
	my $hostid = $host->{'hostid'};

	print "Trying to disable '$host_name' host: ";

	$result = disable_host($hostid);

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Disabling probe host
    if (defined($probe_host->{'host'})) {
	my $host_name = $probe_host->{'host'};
	my $hostid = $probe_host->{'hostid'};

	print "Trying to disable '$host_name' host: ";

	$result = disable_host($hostid);

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Disabling probe monitoring host
    if (defined($probe_host_mon->{'host'})) {
	my $host_name = $probe_host_mon->{'host'};
	my $hostid = $probe_host_mon->{'hostid'};

	print "Trying to disable '$host_name' host: ";

	$result = disable_host($hostid);

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Disabling all services on the Probe
    foreach my $macro (@{$probe_tmpl->{'macros'}}) {
	my $macro_name = $macro->{'macro'};
	my $hostmacroid = $macro->{'hostmacroid'};

	next unless (defined($macros{$macro_name}));

	print "Disabling macro '$macro_name': ";

	$result = macro_value($hostmacroid, $macros{$macro_name});

	is_not_empty($result->{'hostmacroids'}, false);
    }

    ########## Move the Probe to active mode

    print "Disabling '$probe_name' Probe: ";

    $result = set_proxy_status( $probe->{'proxyid'}, HOST_STATUS_PROXY_ACTIVE);

    is_not_empty($result->{'proxyids'}, false);

    ##########

    print "The probe has been disabled successful\n";
    print "Do not forget to tune macros!\n";
}

sub check_probe_data($) {
    my $data = shift;
    my $message = shift;
    my $do_exit = shift;

    unless (keys %{$data}) {
	print $message."\n";
	exit if !defined($do_exit) or $do_exit != false;
    }

    return true;
}

##############

sub is_not_empty($$) {
    my $var = shift;
    my $do_exit = shift;

    if (defined($var) and scalar($var)) {
        print "success\n";
    }
    else {
        print "failed\n";
        exit if !defined($do_exit) or $do_exit != false;
    }
}

##############

sub usage {
    my ($opt_name, $opt_value) = @_;

    print <<EOF;

    Usage: $0 [options]

Required options

        --probe=STRING
                PROBE name
Other options

        --delete
                delete specified Probe
                (default: off)
        --disable
		disable specified Probe
                (default: on)
	--add
		add new probe with specified name and options
		(default: off)

Options for adding new probe. Argument --add.
    --ip
          IP of new probe node
          (default: empty)
	--epp
		Enable EPP support for the Probe
		(default: disabled)
	--ipv4
		Enable IPv4 support for the Probe
		(default: disabled)
	--ipv6
		Enable IPv6 support for the Probe
		(default: disabled)
	--rdds43
		Enable RDDS43 support for the Probe
		(default: disabled)
	--rdds80
		Enable RDDS80 support for the Probe
		(default: disabled)
	--rdap
		Enable RDAP support for the Probe
		(default: disabled)
	--resolver
		The name of resolver
		(default: 127.0.0.1)
EOF
exit(1);
}

sub validate_input {
    my $msg;

    $msg  = "Probe name must be specified (--probe)\n" unless (defined($OPTS{'probe'}));

    if ((defined($OPTS{'delete'}) and defined($OPTS{'disable'})) or
	(defined($OPTS{'delete'}) and defined($OPTS{'add'})) or
	(defined($OPTS{'disable'}) and defined($OPTS{'add'}))) {
	$msg .= "You need to choose only one option from --disable, --add, --delete\n";
    }

    if (!defined($OPTS{'delete'}) and !defined($OPTS{'add'}) and !defined($OPTS{'disable'})) {
	$msg .= "At least one option --disable, --add, --delete must be specified\n";
    }

    if (defined($OPTS{'add'}) and !defined($OPTS{'ip'})) {
        $msg .= "You need to specify Probe IP using --ip argument\n";
    }

    if (defined($OPTS{'add'}) and !defined($OPTS{'resolver'})) {
        $OPTS{'resolver'} = '127.0.0.1';
    }

    if (defined($OPTS{'add'}) and !defined($OPTS{'ip'})) {
        $msg .= "You need to specify IP of the new node using --ip option\n";
    }

    $OPTS{'epp'} = 0 unless defined($OPTS{'epp'});
    $OPTS{'ipv4'} = 0 unless defined($OPTS{'ipv4'});
    $OPTS{'ipv6'} = 0 unless defined($OPTS{'ipv6'});
    $OPTS{'rdds43'} = 0 unless defined($OPTS{'rdds43'});
    $OPTS{'rdds80'} = 0 unless defined($OPTS{'rdds80'});
    $OPTS{'rdap'} = 0 unless defined($OPTS{'rdap'});

    if (defined($msg)) {
        print($msg);
        usage();
    }
}
