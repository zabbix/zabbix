#!/usr/bin/perl

use warnings;
use strict;

use lib '/opt/zabbix/scripts';

use RSM;
use RSMSLV;
use DaWa;

use constant SQL_NAME_MAX => 255;	# according to MySQL maximum to have unique index with utf-8 (up to 3 bytes per char) is 255

parse_opts('force!');

setopt('nolog');

my $config = get_rsm_config();

__db_connect();

__create_tables(getopt('force'));

my ($dbh, $global_sql);

sub __db_connect
{
	fail("no database configuration defined") if (not defined($config) or
		not defined($config->{'db'}) or
		not defined($config->{'db'}->{'name'}));

	$global_sql = 'DBI:mysql:'.$config->{'db'}->{'name'}.':'.$config->{'db'}->{'host'};

	$dbh = DBI->connect($global_sql, $config->{'db'}->{'user'}, $config->{'db'}->{'password'},
		{
			AutoCommit	=> 0,
			PrintError	=> 0,
			HandleError	=> \&__handle_db_error,
		}) or __handle_db_error(DBI->errstr);
}

sub __handle_db_error
{
	my $msg = shift;

	eval { $dbh->rollback() };

	fail("database error: $msg (query was: $global_sql)");
}

sub __db_select
{
	$global_sql = shift;

	my $sth = $dbh->prepare($global_sql)
		or fail("cannot prepare [$global_sql]: ", $dbh->errstr);

	dbg("[$global_sql]");

	$sth->execute()
		or fail("cannot execute [$global_sql]: ", $sth->errstr);

	return $sth->fetchall_arrayref();
}

sub __db_exec
{
	$global_sql = shift;

	my $sth = $dbh->prepare($global_sql)
		or fail("cannot prepare [$global_sql]: ", $dbh->errstr);

	dbg("[$global_sql]");

	$sth->execute()
		or fail("cannot execute [$global_sql]: ", $sth->errstr);
}

sub __table_exists
{
	my $table = shift;

	my $rows_ref = __db_select("show tables like '$table'");

	return scalar(@$rows_ref);
}

sub __create_tables
{
	my $force = shift;

	foreach my $table (keys(%CATALOGS))
	{
		$table = "rsm_$table";

		if (__table_exists($table) != 0 && $force)
		{
			__db_exec("drop table `$table`");

			$dbh->commit();
		}

		if (__table_exists($table) == 0)
		{
			eval
			{
				info("creating table '$table'...");

				__db_exec("create table `$table` (`id` bigint(20) unsigned not null auto_increment, `name` varchar(" . SQL_NAME_MAX . ") not null, primary key (`id`)) engine=innodb default charset=utf8");
				__db_exec("create unique index `${table}_1` ON `$table` (`name`)");

				if ($table eq 'rsm_test_type')
				{
					info("  adding test types...");

					__db_exec("insert into `$table` (`id`,`name`) values (1,'dns')");
					__db_exec("insert into `$table` (`id`,`name`) values (2,'rdds43')");
					__db_exec("insert into `$table` (`id`,`name`) values (3,'rdds80')");
					__db_exec("insert into `$table` (`id`,`name`) values (4,'rdap')");
					__db_exec("insert into `$table` (`id`,`name`) values (5,'eppsession')");
					__db_exec("insert into `$table` (`id`,`name`) values (6,'eppinfo')");
					__db_exec("insert into `$table` (`id`,`name`) values (7,'epptransform')");
					__db_exec("insert into `$table` (`id`,`name`) values (8,'smrdl')");
					__db_exec("insert into `$table` (`id`,`name`) values (9,'surl')");
					__db_exec("insert into `$table` (`id`,`name`) values (10,'dnl')");
					__db_exec("insert into `$table` (`id`,`name`) values (11,'cnis')");
				}
				elsif ($table eq 'rsm_service_category')
				{
					info("  adding service categories...");

					__db_exec("insert into `$table` (`id`,`name`) values (1,'dns')");
					__db_exec("insert into `$table` (`id`,`name`) values (2,'dnssec')");
					__db_exec("insert into `$table` (`id`,`name`) values (3,'rdds')");
					__db_exec("insert into `$table` (`id`,`name`) values (4,'epp')");
					__db_exec("insert into `$table` (`id`,`name`) values (5,'ns')");
					__db_exec("insert into `$table` (`id`,`name`) values (6,'smdrl')");
					__db_exec("insert into `$table` (`id`,`name`) values (7,'surl')");
					__db_exec("insert into `$table` (`id`,`name`) values (8,'dnl')");
					__db_exec("insert into `$table` (`id`,`name`) values (9,'cnis')");
				}
				elsif ($table eq 'rsm_tld_type')
				{
					info("  adding tld types...");

					__db_exec("insert into `$table` (`id`,`name`) values (1,'ccTLD')");
					__db_exec("insert into `$table` (`id`,`name`) values (2,'gTLD')");
					__db_exec("insert into `$table` (`id`,`name`) values (3,'testTLD')");
					__db_exec("insert into `$table` (`id`,`name`) values (4,'otherTLD')");
				}
				elsif ($table eq 'rsm_transport_protocol')
				{
					info("  adding transport protocols...");

					__db_exec("insert into `$table` (`id`,`name`) values (1,'udp')");
					__db_exec("insert into `$table` (`id`,`name`) values (2,'tcp')");
				}
				elsif ($table eq 'rsm_status_map')
				{
					info("  adding result maps...");

					__db_exec("insert into `$table` (`id`,`name`) values (1,'Unknown')");
					__db_exec("insert into `$table` (`id`,`name`) values (2,'Up')");
					__db_exec("insert into `$table` (`id`,`name`) values (3,'Down')");
					__db_exec("insert into `$table` (`id`,`name`) values (4,'No result')");
					__db_exec("insert into `$table` (`id`,`name`) values (5,'Online')");
					__db_exec("insert into `$table` (`id`,`name`) values (6,'Offline')");
				}
				elsif ($table eq 'rsm_ip_version')
				{
					info("  adding ip versions...");

					__db_exec("insert into `$table` (`id`,`name`) values (1,'IPv4')");
					__db_exec("insert into `$table` (`id`,`name`) values (2,'IPv6')");
				}

				$dbh->commit();
			};

			if ($@)
			{
				my $msg = "transaction aborted because $@";

				# now rollback to undo the incomplete changes
				# but do it in an eval{} as it may also fail
				eval { $dbh->rollback() };

				fail($msg);

				# application on-error-clean-up code should be here
			}
		}
	}
}

__END__

=head1 NAME

db-patch.pl - patch Zabbix database to support export of data from Zabbix database in CSV format

=head1 SYNOPSIS

db-patch.pl [--warnslow <seconds>] [--dry-run] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--warnslow> seconds

Issue a warning in case an SQL query takes more than specified number of seconds. A floating-point number
is supported as seconds (i. e. 0.5, 1, 1.5 are valid).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will patch existing Zabbix database to satisfy the requirements to export monitoring data in CSV format.

=cut
