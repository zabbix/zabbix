#!/usr/bin/perl
#
#
#
#
#
#
#
#

use lib '/opt/zabbix/scripts';

use strict;
use Data::Dumper;
use DBI;
use Sys::Syslog qw(:standard :macros);
use DateTime;
use POSIX qw(strftime);
use RSM;

openlog("mysql_zbx_part", "ndelay,pid", LOG_LOCAL0);

my $config = get_rsm_config();

my $db_schema = $config->{'db'}->{'name'};

my $dsn = 'DBI:mysql:'.$db_schema.':mysql_socket=/var/lib/mysql/mysql.sock';
my $db_user_name = $config->{'db'}->{'user'};
my $db_password = $config->{'db'}->{'password'};

my $tables = {	'history' => { 'period' => 'day', 'keep_history' => '60'},
		'history_log' => { 'period' => 'day', 'keep_history' => '60'},
		'history_str' => { 'period' => 'day', 'keep_history' => '60'},
		'history_text' => { 'period' => 'day', 'keep_history' => '60'},
		'history_uint' => { 'period' => 'day', 'keep_history' => '60'},

		'trends' => { 'period' => 'month', 'keep_history' => '9999'},
		'trends_uint' => { 'period' => 'month', 'keep_history' => '9999'},

		'acknowledges' => { 'period' => 'month', 'keep_history' => '24'},
		'alerts' => { 'period' => 'month', 'keep_history' => '12'},
		'auditlog' => { 'period' => 'month', 'keep_history' => '24'},
		'events' => { 'period' => 'month', 'keep_history' => '12'},
		'service_alarms' => { 'period' => 'month', 'keep_history' => '1'},
	     };
my $amount_partitions = 10;

my $curr_tz = 'Europe/London';

my $part_tables;

my $dbh = DBI->connect($dsn, $db_user_name, $db_password);

unless ( check_have_partition() ) {
    print "Your installation of MySQL is not support partitioning tables function!\n";
    syslog(LOG_CRIT, 'Your installation of MySQL is not support partitioning tables function!');
    exit 1;
}

my $sth = $dbh->prepare(qq{SELECT table_name, partition_name, lower(partition_method) as partition_method,
				    rtrim(ltrim(partition_expression)) as partition_expression,
			    partition_description, table_rows
			    FROM information_schema.partitions
			    WHERE partition_name IS NOT NULL AND table_schema = ?});
$sth->execute($db_schema);


while (my $row =  $sth->fetchrow_hashref()) {
    $part_tables->{$row->{'table_name'}}->{$row->{'partition_name'}} = $row;
}

$sth->finish();

foreach my $key (sort keys %{$tables}) {
    unless (defined($part_tables->{$key})) {
	syslog(LOG_ERR, 'Partitioning for "'.$part_tables->{$key}.'" is not found! There is possible the table is not partitioned.');
	next;
    }

    create_next_partition($key, $part_tables->{$key}, $tables->{$key}->{'period'});
    remove_old_partitions($key, $part_tables->{$key}, $tables->{$key}->{'period'}, $tables->{$key}->{'keep_history'})
}

delete_old_data();


$dbh->disconnect();


sub check_have_partition {
    my $result = 0;
    #my $sth = $dbh->prepare(qq{SELECT variable_value FROM information_schema.global_variables WHERE variable_name = 'have_partitioning'});
    my $sth = $dbh->prepare(qq{SELECT plugin_status FROM information_schema.plugins WHERE plugin_name = 'partition'});

    $sth->execute();

    my $row = $sth->fetchrow_array();

    $sth->finish();

    #return 1 if $row eq 'YES';
    return 1 if $row eq 'ACTIVE';
}

sub create_next_partition {
    my $table_name = shift;
    my $table_part = shift;
    my $period = shift;

    for (my $curr_part = 0; $curr_part < $amount_partitions; $curr_part++) {
        my $next_name = name_next_part($tables->{$table_name}->{'period'}, $curr_part);
	my $found = 0;


	foreach my $partition (sort keys %{$table_part}) {
	    if ($next_name eq $partition) {
		syslog(LOG_INFO, "Next partition for $table_name table is already created. It is $next_name");
    		$found = 1;
	    }
        }

	if ( $found == 0 ) {
	    syslog(LOG_INFO, "Creating a partition for $table_name table ($next_name)");
            my $query = 'ALTER TABLE '."$db_schema.$table_name".' ADD PARTITION (PARTITION '.$next_name.
			' VALUES less than (UNIX_TIMESTAMP("'.date_next_part($tables->{$table_name}->{'period'}, $curr_part).'") div 1))';

	    syslog(LOG_DEBUG, $query);
	    $dbh->do($query);
        }
    }
}


sub remove_old_partitions {
    my $table_name = shift;
    my $table_part = shift;
    my $period = shift;
    my $keep_history = shift;

    my $curr_date = DateTime->now;
    $curr_date->set_time_zone( $curr_tz );

    if ( $period eq 'day' ) {
        $curr_date->add(days => -$keep_history);

	$curr_date->add(hours => -$curr_date->strftime('%H'));
	$curr_date->add(minutes => -$curr_date->strftime('%M'));
	$curr_date->add(seconds => -$curr_date->strftime('%S'));
    }
    elsif ( $period eq 'week' ) {
    }
    elsif ( $period eq 'month' ) {
        $curr_date->add(months => -$keep_history);

	$curr_date->add(days => -$curr_date->strftime('%d')+1);
        $curr_date->add(hours => -$curr_date->strftime('%H'));
        $curr_date->add(minutes => -$curr_date->strftime('%M'));
        $curr_date->add(seconds => -$curr_date->strftime('%S'));
    }

    foreach my $partition (sort keys %{$table_part}) {
	if ($table_part->{$partition}->{'partition_description'} <= $curr_date->epoch) {
	    syslog(LOG_INFO, "Removing old $partition partition from $table_name table");

	    my $query = "ALTER TABLE $db_schema.$table_name DROP PARTITION $partition";

	    syslog(LOG_DEBUG, $query);
	    $dbh->do($query);
	}
    }
}


sub name_next_part {
    my $period = shift;
    my $curr_part = shift;

    my $name_template;

    my $curr_date = DateTime->now;
    $curr_date->set_time_zone( $curr_tz );

    if ( $period eq 'day' ) {
	my $curr_date = $curr_date->truncate( to => 'day' );
	$curr_date->add(days => 1 + $curr_part);

	$name_template = $curr_date->strftime('p%Y_%m_%d');
    }
    elsif ($period eq 'week') {
	my $curr_date = $curr_date->truncate( to => 'week' );
	$curr_date->add(days => 7 * $curr_part);

        $name_template = $curr_date->strftime('p%Y_%m_w%W');
    }
    elsif ($period eq 'month') {
	my $curr_date = $curr_date->truncate( to => 'month' );
        $curr_date->add(months => 1 + $curr_part);

        $name_template = $curr_date->strftime('p%Y_%m');
    }

    return $name_template;
}


sub date_next_part {
    my $period = shift;
    my $curr_part = shift;

    my $period_date;

    my $curr_date = DateTime->now;

    $curr_date->set_time_zone( $curr_tz );

    if ( $period eq 'day' ) {
	my $curr_date = $curr_date->truncate( to => 'day' );
	$curr_date->add(days => 2 + $curr_part);
        $period_date = $curr_date->strftime('%Y-%m-%d');
    }
    elsif ($period eq 'week') {
	my $curr_date = $curr_date->truncate( to => 'week' );
	$curr_date->add(days => 7 * $curr_part + 1);
        $period_date = $curr_date->strftime('%Y-%m-%d');
    }
    elsif ($period eq 'month') {
	my $curr_date = $curr_date->truncate( to => 'month' );
        $curr_date->add(months => 2 + $curr_part);

        $period_date = $curr_date->strftime('%Y-%m-%d');
    }


    return $period_date;
}

sub delete_old_data {
    $dbh->do("DELETE FROM sessions WHERE lastaccess < UNIX_TIMESTAMP(NOW() - INTERVAL 1 MONTH)");
    $dbh->do("TRUNCATE housekeeper");
    $dbh->do("DELETE FROM auditlog_details WHERE NOT EXISTS (SELECT NULL FROM auditlog WHERE auditlog.auditid = auditlog_details.auditid)");
}
