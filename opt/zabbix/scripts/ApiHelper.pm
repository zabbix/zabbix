package ApiHelper;

use strict;
use warnings;
use File::Path qw(make_path);
use DateTime::Format::RFC3339;
use JSON::XS;
use base 'Exporter';

use constant AH_SUCCESS => 0;
use constant AH_FAIL => 1;
use constant AH_ALARMED_YES => 'Yes';
use constant AH_ALARMED_NO => 'No';
use constant AH_ALARMED_DISABLED => 'Disabled';

our @EXPORT = qw(AH_SUCCESS AH_FAIL AH_ALARMED_YES AH_ALARMED_NO AH_ALARMED_DISABLED ah_get_error
		ah_save_alarmed ah_save_service_availability ah_save_incident_state ah_save_false_positive
		ah_save_incident_results ah_get_continue_file ah_get_api_tld ah_get_last_audit ah_save_audit
		ah_encode_pretty_json);

use constant AH_BASE_DIR => '/opt/zabbix/sla';

use constant AH_ROOT_ZONE_DIR => 'zz--root';	# map root zone (.) to something human readable

use constant AH_INCIDENT_ACTIVE => 'Active';
use constant AH_INCIDENT_ENDED => 'Resolved';

use constant AH_ALARMED_FILE => 'alarmed.json';
use constant AH_SERVICE_AVAILABILITY_FILE => 'serviceAvailability.json';
use constant AH_INCIDENT_STATE_FILE => 'state.json';
use constant AH_FALSE_POSITIVE_FILE => 'falsePositive.json';

use constant AH_CONTINUE_FILE => AH_BASE_DIR . '/last_update.txt';	# file with timestamp of last run with --continue
use constant AH_AUDIT_FILE => AH_BASE_DIR . '/last_audit.txt';		# file containing timestamp of last auditlog
									# entry that was checked (false_positive change)

use constant AH_FALSE_POSITIVE_FALSE => 'False';
use constant AH_FALSE_POSITIVE_TRUE => 'True';

use constant AH_JSON_FILE_VERSION => 1;

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

	my $path = AH_BASE_DIR . "/$tld/data/$service";
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
	my $incidentid = shift;
	my $inc_path_ptr = shift;	# pointer

	return __make_base_path($tld, $service, $inc_path_ptr, "incidents/$incidentid");
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

	my $fh;

	if (!open($fh, '>', $full_path))
	{
		__set_error("cannot open file $full_path: $!");
		return AH_FAIL;
	}

	if (!print { $fh } $text)
	{
		__set_error("cannot write to file $full_path: $!");
		return AH_FAIL;
	}

	close($fh);

	return AH_SUCCESS;
}

sub ah_save_alarmed
{
	my $tld = shift;
	my $service = shift;
	my $status = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$base_path) == AH_SUCCESS);

	# if service is disabled there should be no availability file
	if ($status eq AH_ALARMED_DISABLED)
	{
		my $avail_path = $base_path . '/' . AH_SERVICE_AVAILABILITY_FILE;

		if ((-e $avail_path) and not unlink($avail_path))
		{
			__set_file_error($!);
			return AH_FAIL;
		}
	}

	my $json_ref = {'alarmed' => $status};

	return __write_file($base_path . '/' . AH_ALARMED_FILE, __encode_json($json_ref));
}

sub ah_save_service_availability
{
	my $tld = shift;
	my $service = shift;
	my $downtime = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$base_path) == AH_SUCCESS);

	my $json_ref = {'serviceAvailability' => $downtime};

	return __write_file($base_path . '/' . AH_SERVICE_AVAILABILITY_FILE, __encode_json($json_ref));
}

sub __incident_id
{
	my $start = shift;
	my $eventid = shift;

	return "$start.$eventid";
}

sub ah_save_incident_state
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $end = shift;
	my $false_positive = shift;

	my $incidentid = __incident_id($start, $eventid);

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $incidentid, \$inc_path) == AH_SUCCESS);

	my $json_ref = {'falsePositive' => AH_FALSE_POSITIVE_FALSE, 'updateTime' => undef};

	return AH_FAIL unless (__write_file($inc_path . '/' . AH_FALSE_POSITIVE_FILE, __encode_json($json_ref)) == AH_SUCCESS);

	$json_ref =
		{
			'incidents' =>
			[
				{
					'incidentID' => $incidentid,
					'startTime' => $start,
					'falsePositive' => ($false_positive == 0 ? AH_FALSE_POSITIVE_FALSE : AH_FALSE_POSITIVE_TRUE),
					'state' => ($end ? AH_INCIDENT_ENDED : AH_INCIDENT_ACTIVE),
					'endTime' => $end
				}
			]
		};

	return __write_file($inc_path . '/'. AH_INCIDENT_STATE_FILE, __encode_json($json_ref));
}

sub ah_save_false_positive
{
	my $tld = shift;
	my $service = shift;
	my $start = shift;		# incident start time
	my $eventid = shift;		# incident is identified by event ID
	my $false_positive = shift;
	my $clock = shift;		# time of flase_positive flag change

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, __incident_id($start, $eventid), \$inc_path) == AH_SUCCESS);

	my $inc_state_file = $inc_path . '/'. AH_INCIDENT_STATE_FILE;

	my $json_ref =
		{
			'falsePositive' => ($false_positive == 0 ? AH_FALSE_POSITIVE_FALSE : AH_FALSE_POSITIVE_TRUE),
			'updateTime' => $clock
		};

	return AH_FAIL unless (__write_file($inc_path . '/' . AH_FALSE_POSITIVE_FILE, __encode_json($json_ref)) == AH_SUCCESS);

	$json_ref = undef;

	return AH_FAIL unless (__parse_json_file($inc_state_file, \$json_ref) == AH_SUCCESS);

	$json_ref->{'incident'}->{'falsePositive'} = ($false_positive == 0 ? AH_FALSE_POSITIVE_FALSE : AH_FALSE_POSITIVE_TRUE);

	return __write_file($inc_state_file, __encode_json($json_ref));
}

sub ah_save_incident_results
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $tr_ref = shift;
	my $clock = shift;

	my $inc_path;

	my $incidentid = __incident_id($start, $eventid);

	return AH_FAIL unless (__make_inc_path($tld, $service, $incidentid, \$inc_path) == AH_SUCCESS);

	return __write_file("$inc_path/$clock.$incidentid.json", __encode_json($tr_ref));
}

sub ah_get_continue_file
{
	return AH_CONTINUE_FILE;
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

sub __encode_json
{
	my $json_ref = shift;

	$json_ref->{'version'} = AH_JSON_FILE_VERSION;
	$json_ref->{'lastModifiedTime'} = time();

	return encode_json($json_ref);
}

sub ah_encode_pretty_json
{
	return JSON->new->utf8(1)->pretty(1)->encode(shift);
}

sub __read_file
{
	my $filename = shift;
	my $buf_ref = shift;

	my $fh;

	if (!open($fh, '<', $filename))
	{
		__set_file_error("cannot open $filename: $!");
		return AH_FAIL;
	}

	$$buf_ref = "";

	while (my $line = <$fh>)
	{
		$$buf_ref .= $line;
	}

	close($fh);

	return AH_SUCCESS;
}

sub __parse_json_file
{
	my $file = shift;
	my $json_ref_ref = shift;	# double ref

	my $buf;

	return AH_FAIL unless (__read_file($file, \$buf) == AH_SUCCESS);

	$$json_ref_ref = decode_json($buf);

	return AH_SUCCESS;
}

1;
