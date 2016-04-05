package ApiHelper;

use strict;
use warnings;
use File::Path qw(make_path remove_tree);
use DateTime::Format::RFC3339;
use JSON::XS;
use base 'Exporter';

use constant AH_SUCCESS	=> 0;
use constant AH_FAIL	=> 1;

use constant AH_ALARMED_YES		=> 'Yes';
use constant AH_ALARMED_NO		=> 'No';
use constant AH_ALARMED_DISABLED	=> 'Disabled';
use constant AH_ALARMED_MAINTENANCE	=> 'No (maintenance)';

use constant AH_STATUS_UP		=> 'Up';
use constant AH_STATUS_DOWN		=> 'Down';
use constant AH_STATUS_MAINTENANCE	=> 'Up (maintenance)';

use constant AH_ENABLED_YES	=> 'Yes';
use constant AH_ENABLED_NO	=> 'No';

use constant AH_FALSE_POSITIVE_TRUE	=> 'True';
use constant AH_FALSE_POSITIVE_FALSE	=> 'False';

use constant AH_INCIDENT_ACTIVE	=> 'Active';
use constant AH_INCIDENT_ENDED	=> 'Resolved';

our @EXPORT = qw(AH_SUCCESS AH_FAIL AH_ALARMED_YES AH_ALARMED_NO AH_ALARMED_DISABLED AH_ALARMED_MAINTENANCE AH_STATUS_UP
		AH_STATUS_DOWN AH_STATUS_MAINTENANCE AH_ENABLED_YES AH_ENABLED_NO AH_FALSE_POSITIVE_TRUE
		AH_FALSE_POSITIVE_FALSE AH_INCIDENT_ACTIVE AH_INCIDENT_ENDED
		ah_get_error ah_begin ah_end
		ah_save_alarmed ah_save_service_availability ah_save_incident_state ah_save_false_positive
		ah_save_incident_results ah_get_continue_file ah_get_last_audit ah_save_audit
		ah_encode_pretty_json ah_save_tld_status ah_set_base_dir ah_set_tmp_dir);

use constant AH_FILE_POSTFIX			=> '';	# e. g. ".json"
use constant AH_TLD_STATE_FILE			=> 'state' . AH_FILE_POSTFIX;
use constant AH_ALARMED_FILE			=> 'alarmed' . AH_FILE_POSTFIX;
use constant AH_SERVICE_AVAILABILITY_FILE	=> 'serviceAvailability' . AH_FILE_POSTFIX;
use constant AH_INCIDENT_STATE_FILE		=> 'state' . AH_FILE_POSTFIX;
use constant AH_FALSE_POSITIVE_FILE		=> 'falsePositive' . AH_FILE_POSTFIX;

use constant AH_CONTINUE_FILE	=> 'last_update.txt';	# file with timestamp of last run with --continue
use constant AH_AUDIT_FILE	=> 'last_audit.txt';	# file containing timestamp of last auditlog entry that was checked (false_positive change)

use constant AH_JSON_FILE_VERSION	=> 1;

# When files are generated they are first saved to $AH_TMP_DIR . When the script is finished, they are copied over
# to $AH_BASE_DIR . This allows atomic process of generating files. These variables can be overriden using functions
# ah_set_base_dir() and ah_set_tmp_dir() .
my $AH_BASE_DIR = '/opt/zabbix/sla';
my $AH_TMP_DIR = '/opt/zabbix/tmp';

my $error_string = "";

sub ah_get_error
{
	return $error_string;
}

sub ah_begin
{
	my $err;

	if (-d __target_dir())
	{
		remove_tree(__target_dir(), {keep_root => 1, error => \$err});

		if (@$err)
		{
			__set_file_error($err);
			$error_string = "cannot empty temporary directory $error_string";
			return AH_FAIL;
		}
	}
	else
	{
		remove_tree(__target_dir(), {error => \$err});

		if (@$err)
		{
			__set_file_error($err);
			$error_string = "cannot delete temporary directory $error_string";
			return AH_FAIL;
		}

		make_path(__target_dir(), {error => \$err});

		if (@$err)
		{
			__set_file_error($err);
			$error_string = "cannot create temporary directory $error_string";
			return AH_FAIL;
		}
	}

	if (-f __source_dir())
	{
		if (!unlink(__source_dir()))
		{
			__set_file_error($!);
			return AH_FAIL;
		}
	}

	make_path(__source_dir(), {error => \$err});

	if (@$err)
	{
		__set_file_error($err);
		$error_string = "cannot create base directory $error_string";
		return AH_FAIL;
	}

	return AH_SUCCESS;
}

sub ah_end
{
	my $strip_components = () = __target_dir() =~ /\//g;

	return __system('tar -cf - ', __target_dir(), ' 2>/dev/null | tar --ignore-command-error -C ', __source_dir(), ' --strip-components=', $strip_components, ' -xf -');
}

sub __make_path
{
	my $path = shift;

	make_path($path, {error => \my $err});

	if (@$err)
	{
		__set_file_error($err);
		return AH_FAIL;
	}

	return AH_SUCCESS;
}

sub __make_source_tld_path
{
	my $tld = shift;
	my $result_path_ptr = shift;	# pointer
	my $add_path = shift;

	$tld = lc($tld);

	my $path = __source_dir() . "/$tld/data";
	$path .= "/$add_path" if ($add_path);

	return AH_FAIL unless (__make_path($path) == AH_SUCCESS);

	$$result_path_ptr = $path;

	return AH_SUCCESS;
}

sub __make_target_tld_path
{
	my $tld = shift;
	my $result_path_ptr = shift;	# pointer
	my $add_path = shift;

	$tld = lc($tld);

	my $path = __target_dir() . "/$tld/data";
	$path .= "/$add_path" if ($add_path);

	return AH_FAIL unless (__make_path($path) == AH_SUCCESS);

	$$result_path_ptr = $path;

	return AH_SUCCESS;
}

sub __make_source_service_path
{
	my $tld = shift;
	my $service = shift;
	my $service_path_ptr = shift;	# pointer
	my $add_path = shift;

	$service = lc($service);

	my $path = $service;

	$path .= "/$add_path" if ($add_path);

	return __make_source_tld_path($tld, $service_path_ptr, $path);
}

sub __make_taret_service_path
{
	my $tld = shift;
	my $service = shift;
	my $service_path_ptr = shift;	# pointer
	my $add_path = shift;

	$service = lc($service);

	my $path = $service;

	$path .= "/$add_path" if ($add_path);

	return __make_target_tld_path($tld, $service_path_ptr, $path);
}

sub __make_inc_path
{
	my $tld = shift;
	my $service = shift;
	my $incidentid = shift;
	my $inc_path_ptr = shift;	# pointer

	return __make_taret_service_path($tld, $service, $inc_path_ptr, "incidents/$incidentid");
}

sub __set_error
{
	$error_string = shift;
}

sub __set_file_error
{
	my $err = shift;

	$error_string = "";

	if (ref($err) eq "ARRAY")
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

	$error_string = join('', $err, @_);
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
	my $now = shift;
	my $tld = shift;
	my $service = shift;
	my $status = shift;

	my $target_path;

	return AH_FAIL unless (__make_taret_service_path($tld, $service, \$target_path) == AH_SUCCESS);

	# if service is disabled there should be no availability file
	if ($status eq AH_ALARMED_DISABLED)
	{
		my $avail_path = $target_path . '/' . AH_SERVICE_AVAILABILITY_FILE;

		if ((-e $avail_path) and not unlink($avail_path))
		{
			__set_file_error($!);
			return AH_FAIL;
		}
	}

	my $json_ref;

	if ($now)
	{
		$json_ref->{'lastUpdateApiDatabase'} = $now;
	}
	else
	{
		my ($source_path, $buf);

		return AH_FAIL unless (__make_source_service_path($tld, $service, \$source_path) == AH_SUCCESS);

		if (__read_file($source_path . '/' . AH_ALARMED_FILE, \$buf) == AH_SUCCESS)
		{
			$json_ref = decode_json($buf);

			$json_ref->{'lastUpdateApiDatabase'} = $json_ref->{'lastUpdateApiDatabase'} + 1;
		}
		else
		{
			$json_ref->{'lastUpdateApiDatabase'} = time();
		}
	}

	$json_ref->{'alarmed'} = $status;

	return __write_file($target_path . '/' . AH_ALARMED_FILE, __encode_json($json_ref));
}

sub ah_save_service_availability
{
	my $now = shift;
	my $tld = shift;
	my $service = shift;
	my $rollweek = shift;

	my $service_path;

	return AH_FAIL unless (__make_taret_service_path($tld, $service, \$service_path) == AH_SUCCESS);

	my $json_ref = {'serviceAvailability' => $rollweek};

	$json_ref->{'lastUpdateApiDatabase'} = $now;

	return __write_file($service_path . '/' . AH_SERVICE_AVAILABILITY_FILE, __encode_json($json_ref));
}

sub __incident_id
{
	my $start = shift;
	my $eventid = shift;

	return "$start.$eventid";
}

sub ah_save_incident_state
{
	my $now = shift;
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

	$json_ref->{'lastUpdateApiDatabase'} = $now;

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

	$json_ref->{'lastUpdateApiDatabase'} = $now;

	return __write_file($inc_path . '/'. AH_INCIDENT_STATE_FILE, __encode_json($json_ref));
}

sub ah_save_false_positive
{
	my $now = shift;
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

	$json_ref->{'lastUpdateApiDatabase'} = $now;

	return AH_FAIL unless (__write_file($inc_path . '/' . AH_FALSE_POSITIVE_FILE, __encode_json($json_ref)) == AH_SUCCESS);

	$json_ref = undef;

	return AH_FAIL unless (__parse_json_file($inc_state_file, \$json_ref) == AH_SUCCESS);

	$json_ref->{'incident'}->{'falsePositive'} = ($false_positive == 0 ? AH_FALSE_POSITIVE_FALSE : AH_FALSE_POSITIVE_TRUE);

	$json_ref->{'lastUpdateApiDatabase'} = $now;

	return __write_file($inc_state_file, __encode_json($json_ref));
}

sub ah_save_incident_results
{
	my $now = shift;
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $tr_ref = shift;
	my $clock = shift;

	my $inc_path;

	my $incidentid = __incident_id($start, $eventid);

	return AH_FAIL unless (__make_inc_path($tld, $service, $incidentid, \$inc_path) == AH_SUCCESS);

	$tr_ref->{'lastUpdateApiDatabase'} = $now;

	return __write_file("$inc_path/$clock.$incidentid" . AH_FILE_POSTFIX, __encode_json($tr_ref));
}

sub ah_get_continue_file
{
	return __source_dir() . '/' . AH_CONTINUE_FILE;
}

# get the time of last audit log entry that was checked
sub ah_get_last_audit
{
	my $audit_file = __source_dir() . '/' . AH_AUDIT_FILE;
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

	return __write_file(__target_dir() . '/' . AH_AUDIT_FILE, $clock);
}

sub __encode_json
{
	my $json_ref = shift;

	$json_ref->{'version'} = AH_JSON_FILE_VERSION;

	return encode_json($json_ref);
}

sub ah_encode_pretty_json
{
	return JSON->new->utf8(1)->pretty(1)->encode(shift);
}

sub ah_save_tld_status
{
	my $now = shift;
	my $tld = shift;
	my $tld_ref = shift;

	my $base_path;

	return AH_FAIL unless (__make_source_tld_path($tld, \$base_path) == AH_SUCCESS);

	my $buf;
	my $json_ref;
	my $file = $base_path . '/' . AH_TLD_STATE_FILE;

	if (__read_file($file, \$buf) == AH_SUCCESS)
	{
		$json_ref = decode_json($buf);
	}
	else
	{
		$json_ref = {'tld' => $tld};
	}

	if ($now)
	{
		$json_ref->{'lastUpdateApiDatabase'} = $now;
	}
	elsif ($json_ref->{'lastUpdateApiDatabase'})
	{
		$json_ref->{'lastUpdateApiDatabase'} = $json_ref->{'lastUpdateApiDatabase'} + 1;
	}
	else
	{
		$json_ref->{'lastUpdateApiDatabase'} = time();
	}

	return AH_FAIL unless (__make_target_tld_path($tld, \$base_path) == AH_SUCCESS);

	$file = $base_path . '/' . AH_TLD_STATE_FILE;

	$json_ref->{'status'} = $tld_ref->{'status'};

	foreach my $service (keys(%{$tld_ref->{'services'}}))
	{
		my $service_idx = -1;

		if ($json_ref->{'testedService'})
		{
			my $idx = 0;
			foreach my $service_ptr (@{$json_ref->{'testedService'}})
			{
				if (lc($service) eq lc($service_ptr->{'service'}))
				{
					$service_idx = $idx;
					last;
				}

				$idx++;
			}
		}

		if ($service_idx == -1)
		{
			# add
			push(@{$json_ref->{'testedService'}}, $tld_ref->{'services'}->{$service});
			$service_idx = scalar(@{$json_ref->{'testedService'}}) - 1;
		}
		else
		{
			$json_ref->{'testedService'}->[$service_idx] = $tld_ref->{'services'}->{$service};
		}

		$json_ref->{'testedService'}->[$service_idx]->{'service'} = uc($service);
	}

	return __write_file($file, __encode_json($json_ref));
}

sub ah_set_base_dir
{
	$AH_BASE_DIR = shift;
}

sub ah_set_tmp_dir
{
	$AH_TMP_DIR = shift;
}

sub __source_dir
{
	return $AH_BASE_DIR;
}

sub __target_dir
{
	return $AH_TMP_DIR;
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

sub __system
{
	my $cmd = join('', @_);

	system($cmd);

	if ($? == -1)
	{
		__set_error("failed to execute: $!");
		return AH_FAIL;
	}

	if ($? & 127)
	{
		__set_error("child died with signal %d, %s coredump", ($? & 127),  ($? & 128) ? 'with' : 'without');
		return AH_FAIL;
	}

	return AH_SUCCESS;
}

1;
