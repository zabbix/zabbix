package Alerts;

use strict;
use warnings;
use Redis;
use base 'Exporter';

our @EXPORT = qw(add_alert);

my $redis;

use constant ALERTS_KEY => "new-alerts";

sub add_alert
{
	my $value = shift;

	$redis = Redis->new unless ($redis);

	die("Cannot connect to Redis server: $!\n") unless ($redis);

	$redis->lpush(ALERTS_KEY, $value);
}

1;
