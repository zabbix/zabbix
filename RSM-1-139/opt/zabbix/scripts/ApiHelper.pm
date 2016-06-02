package ApiHelper;

use strict;
use warnings;
use File::Path qw(make_path);
use DateTime::Format::RFC3339;
use base 'Exporter';

use constant AH_SUCCESS => 0;
use constant AH_FAIL => 1;
use constant AH_BASE_DIR => '/opt/zabbix/sla';

use constant AH_INCIDENT_ACTIVE => 'ACTIVE';
use constant AH_END_FILE => 'end';
use constant AH_FALSE_POSITIVE_FILE => 'falsePositive';
use constant AH_ALARMED_FILE => 'alarmed';
use constant AH_ALARMED_YES => 'YES';
use constant AH_ALARMED_NO => 'NO';
use constant AH_ALARMED_DISABLED => 'DISABLED';
use constant AH_SERVICE_AVAILABILITY_FILE => 'serviceAvailability';

use constant AH_ROOT_ZONE_DIR => 'zz--root';			# map root zone name (.) to something human readable
use constant AH_CONTINUE_FILE => 'last_update.txt';		# name of the file containing the timestamp of the last
								# run with --continue
use constant AH_AUDIT_FILE => AH_BASE_DIR . '/last_audit.txt';	# name of the file containing the timestamp of the last
								# auditlog entry that was checked (false_positive change)

our @EXPORT = qw(AH_SUCCESS AH_FAIL AH_ALARMED_YES AH_ALARMED_NO AH_ALARMED_DISABLED ah_get_error
		ah_save_alarmed ah_save_service_availability ah_save_incident ah_save_false_positive
		ah_save_incident_json ah_get_continue_file ah_get_api_tld ah_get_last_audit ah_save_audit);

my $error_string = "";

sub ah_get_error
{
	return $error_string;
}

sub __make_base_path
{
	my $tld = shift;
	my $service = shift;
	my $result_path_ptr = shift;	# pointer
	my $add_path = shift;

	$tld = lc($tld);
	$service = lc($service);

	my $path = AH_BASE_DIR . "/$tld/$service";
	$path .= "/$add_path" if ($add_path);

	make_path($path, {error => \my $err});

	if (@$err)
	{
		__set_file_error($err);
		return AH_FAIL;
	}

	$$result_path_ptr = $path;

	return AH_SUCCESS;
}

sub __make_inc_path
{
	my $tld = shift;
	my $service = shift;
	my $start = shift;
	my $eventid = shift;
	my $inc_path_ptr = shift;	# pointer

	return __make_base_path($tld, $service, $inc_path_ptr, "incidents/$start.$eventid");
}

sub __set_error
{
	$error_string = shift;
}

sub __set_file_error
{
	my $err = shift;

	$error_string = "";

	if (@$err)
	{
		for my $diag (@$err)
		{
			my ($file, $message) = %$diag;
			if ($file eq '')
			{
				$error_string .= "$message. ";
			}
			else
			{
				$error_string .= "$file: $message. ";
			}

			return;
		}
	}
}

sub __write_file
{
	my $full_path = shift;
	my $text = shift;
	my $clock = shift;

	my $OUTFILE;

	unless (open($OUTFILE, '>', $full_path))
	{
		__set_error("cannot open file $full_path: $!");
		return AH_FAIL;
	}

	unless (print { $OUTFILE } $text)
	{
		__set_error("cannot write to file $full_path: $!");
		return AH_FAIL;
	}

	close($OUTFILE);

	utime($clock, $clock, $full_path) if (defined($clock));

	return AH_SUCCESS;
}

sub __apply_inc_end
{
	my $inc_path = shift;
	my $end = shift;
	my $lastclock = shift;

	my $end_path = "$inc_path/" . AH_END_FILE;

	return __write_file($end_path, AH_INCIDENT_ACTIVE, $lastclock) unless (defined($end));

	my $dt = DateTime->from_epoch('epoch' => $end);
	my $f = DateTime::Format::RFC3339->new();

	return __write_file($end_path, $f->format_datetime($dt), $end);
}

sub __apply_inc_false_positive
{
	my $inc_path = shift;
	my $false_positive = shift;
	my $clock = shift;

	my $false_positive_path = "$inc_path/" . AH_FALSE_POSITIVE_FILE;

	if ($false_positive != 0)
	{
		return __write_file($false_positive_path, '', $clock);
	}

	if ((-e $false_positive_path) and not unlink($false_positive_path))
	{
		__set_file_error($!);
		return AH_FAIL;
	}

	return AH_SUCCESS;
}

sub ah_save_alarmed
{
	my $tld = shift;
	my $service = shift;
	my $status = shift;
	my $clock = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$base_path) == AH_SUCCESS);

	my $alarmed_path = "$base_path/" . AH_ALARMED_FILE;

	# if service is disabled there should be no availability file
	if ($status eq AH_ALARMED_DISABLED)
	{
		my $avail_path = "$base_path/" . AH_SERVICE_AVAILABILITY_FILE;;

		if ((-e $avail_path) and not unlink($avail_path))
		{
			__set_file_error($!);
			return AH_FAIL;
		}
	}

	return __write_file($alarmed_path, $status, $clock);
}

sub ah_save_service_availability
{
	my $tld = shift;
	my $service = shift;
	my $downtime = shift;
	my $clock = shift;

	my $service_availability_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$service_availability_path) == AH_SUCCESS);

	return __write_file("$service_availability_path/" . AH_SERVICE_AVAILABILITY_FILE, $downtime, $clock);
}

sub ah_save_incident
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $end = shift;
	my $false_positive = shift;
	my $lastclock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path) == AH_SUCCESS);

	return AH_FAIL unless (__apply_inc_end($inc_path, $end, $lastclock) == AH_SUCCESS);

	return __apply_inc_false_positive($inc_path, $false_positive, $start);
}

sub ah_save_false_positive
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $false_positive = shift;
	my $clock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path) == AH_SUCCESS);

	return __apply_inc_false_positive($inc_path, $false_positive, $clock);
}

sub ah_save_incident_json
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $json = shift;
	my $clock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path) == AH_SUCCESS);

	my $json_path = "$inc_path/$clock.$eventid.json";

	return __write_file($json_path, "$json\n", $clock);
}

sub ah_get_continue_file
{
	return AH_BASE_DIR . '/' . AH_CONTINUE_FILE;
}

sub ah_get_api_tld
{
	my $tld = shift;

	return AH_ROOT_ZONE_DIR if ($tld eq ".");

	return $tld;
}

# get the time of last audit log entry that was checked
sub ah_get_last_audit
{
	my $audit_file = AH_AUDIT_FILE;
	my $handle;

	if (-e $audit_file)
	{
		fail("cannot open last audit check file $audit_file\": $!") unless (open($handle, '<', $audit_file));

		chomp(my @lines = <$handle>);

		close($handle);

		return $lines[0];
	}

	return 0;
}

sub ah_save_audit
{
	my $clock = shift;

	return __write_file(AH_AUDIT_FILE, $clock);
}

1;
