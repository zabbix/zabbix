package DNSTest;

use strict;
use warnings;
use Config::Tiny;
use base 'Exporter';

our @EXPORT = qw(get_dnstest_config);

my $config_file = '/opt/zabbix/scripts/dnstest.conf';

sub get_dnstest_config
{
    my $config = Config::Tiny->new;

    $config = Config::Tiny->read($config_file);

    unless (defined($config))
    {
	print STDERR (Config::Tiny->errstr(), "\n");
	exit(-1);
    }

    return $config;
}

1;
