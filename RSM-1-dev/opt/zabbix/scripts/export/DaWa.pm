package DaWa;

use strict;
use warnings;
use RSMSLV;
use Text::CSV_XS;
use File::Path qw(make_path);

# catalogs
use constant ID_PROBE => 'probe';
use constant ID_TLD => 'tld';
use constant ID_NS_NAME => 'ns_name';
use constant ID_NS_IP => 'ns_ip';
use constant ID_TRANSPORT_PROTOCOL => 'transport_protocol';
use constant ID_TEST_TYPE => 'test_type';
use constant ID_SERVICE_CATEGORY => 'service_category';
use constant ID_TLD_TYPE => 'tld_type';
use constant ID_STATUS_MAP => 'status_map';
use constant ID_IP_VERSION => 'ip_version';

# data files
use constant DATA_TEST => 'test';
use constant DATA_NSTEST => 'nstest';
use constant DATA_CYCLE => 'cycle';
use constant DATA_INCIDENT => 'incident';
use constant DATA_INCIDENT_END => 'incidentEnd';
use constant DATA_FALSE_POSITIVE => 'falsePositive';

our %CATALOGS = (
	ID_PROBE() => 'probeNames.csv',
	ID_TLD() => 'tlds.csv',
	ID_NS_NAME() => 'nsFQDNs.csv',
	ID_NS_IP() => 'ipAddresses.csv',
	ID_TRANSPORT_PROTOCOL() => 'transportProtocols.csv',
	ID_TEST_TYPE() => 'testTypes.csv',
	ID_SERVICE_CATEGORY() => 'serviceCategory.csv',
	ID_TLD_TYPE() => 'tldTypes.csv',
	ID_STATUS_MAP() => 'statusMaps.csv',
	ID_IP_VERSION() => 'ipVersions.csv');

our %DATAFILES = (
	DATA_TEST() => 'tests.csv',
	DATA_NSTEST() => 'nsTests.csv',
	DATA_CYCLE() => 'cycles.csv',
	DATA_INCIDENT() => 'incidents.csv',
	DATA_INCIDENT_END() => 'incidentsEndTime.csv',
	DATA_FALSE_POSITIVE() => 'falsePositiveChanges.csv');

use base 'Exporter';

our @EXPORT = qw(ID_PROBE ID_TLD ID_NS_NAME ID_NS_IP ID_TRANSPORT_PROTOCOL ID_TEST_TYPE ID_SERVICE_CATEGORY
		ID_TLD_TYPE ID_STATUS_MAP ID_IP_VERSION DATA_TEST DATA_NSTEST DATA_CYCLE DATA_INCIDENT DATA_INCIDENT_END
		DATA_FALSE_POSITIVE
		%CATALOGS %DATAFILES
		dw_csv_init dw_append_csv dw_load_ids_from_db dw_get_id dw_get_name dw_write_csv_files
		dw_write_csv_catalogs dw_delete_csvs dw_get_cycle_id dw_translate_cycle_id dw_error dw_set_date);

my %_MAX_IDS = (
	ID_PROBE() => 32767,
	ID_TLD() => 32767,
	ID_NS_NAME() => 32767,
	ID_NS_IP() => 32767,
	ID_TRANSPORT_PROTOCOL() => 127,
	ID_TEST_TYPE() => 127,
	ID_SERVICE_CATEGORY() => 127,
	ID_TLD_TYPE() => 127,
	ID_STATUS_MAP() => 127,
	ID_IP_VERSION() => 127);

my (%_csv_files, %_csv_catalogs, $_csv);

my ($_year, $_month, $_day);

my $_dw_error = "";

my $_catalogs_loaded = 1;

sub dw_csv_init
{
	$_csv = Text::CSV_XS->new({binary => 1, auto_diag => 1});
	$_csv->eol("\n");

	$_catalogs_loaded = 0;
}

# only works with data files
sub dw_append_csv
{
	my $id_type = shift;
	my $rows_ref = shift;	# [] or [[], [], ...]

	if (ref($rows_ref->[0]) eq '')
	{
		push(@{$_csv_files{$id_type}{'rows'}}, $rows_ref);
	}
        elsif (ref($rows_ref->[0]) eq 'ARRAY')
        {
                foreach my $row (@$rows_ref)
                {
			push(@{$_csv_files{$id_type}{'rows'}}, $row);
                }
        }
        else
        {
                fail("internal error: invalid row format for CSV");
        }
}

sub __dw_check_id
{
	my $id_type = shift;
	my $id = shift;

	my $max_id = $_MAX_IDS{$id_type};

	fail("unknown catalog: \"$id_type\"") unless ($max_id);

	return E_FAIL if ($id > $max_id);

	return SUCCESS;
}

# only works with catalogs
sub dw_load_ids_from_db
{
	foreach my $id_type (keys(%CATALOGS))
	{
		delete($_csv_catalogs{$id_type});

		my $rows_ref = db_select("select name,id from rsm_$id_type");

		foreach my $row_ref (@$rows_ref)
		{
			fail("ID overflow of catalog \"$id_type\": ", $row_ref->[1])
				unless (__dw_check_id($id_type, $row_ref->[1]) == SUCCESS);

			$_csv_catalogs{$id_type}{$row_ref->[0]} = $row_ref->[1];
		}
	}

	$_catalogs_loaded = 1;
}

# only works with catalogs
sub dw_get_id
{
	my $id_type = shift;
	my $name = shift;

	if (!$name)
	{
		wrn("cannot get $id_type ID by name: name undefined");
		return undef;
	}

	if (opt('dry-run'))
	{
		return $name;
	}

	return $_csv_catalogs{$id_type}{$name} if ($_csv_catalogs{$id_type}{$name});

	my $id = db_exec("insert into rsm_$id_type (name) values ('$name')");

	fail("ID overflow of catalog \"$id_type\": $id") unless (__dw_check_id($id_type, $id) == SUCCESS);

	$_csv_catalogs{$id_type}{$name} = $id;

	return $id;
}

sub dw_get_name
{
	my $id_type = shift;
	my $id = shift;

	return undef unless(defined($id) && ($id ne ''));

	my $found_name;
	foreach my $name (keys(%{$_csv_catalogs{$id_type}}))
	{
		if ($id == $_csv_catalogs{$id_type}{$name})
		{
			$found_name = $name;
			last;
		}
	}

	return $found_name;
}

sub __csv_file_name
{
	my $id_type = shift;

	die("File '$id_type' is unknown") unless ($DATAFILES{$id_type});

	return $DATAFILES{$id_type};
}

sub dw_write_csv_files
{
	foreach my $id_type (keys(%DATAFILES))
	{
		__write_csv_file($id_type);
		undef($_csv_files{$id_type}{'rows'});
	}
}

sub dw_write_csv_catalogs
{
	my $debug = shift;

	foreach my $id_type (keys(%CATALOGS))
	{
		__write_csv_catalog($id_type);
		undef($_csv_catalogs{$id_type}{'rows'});
	}
}

sub dw_delete_csvs
{
	foreach my $id_type (keys(%DATAFILES))
	{
		my $name = __get_target_dir() . __csv_file_name($id_type);

		if (-f $name)
		{
			unlink($name) or fail("cannot delete file \"$name\": $!");
		}
	}

	foreach my $id_type (keys(%CATALOGS))
	{
		my $name = __get_target_dir() . __csv_catalog_name($id_type);

		if (-f $name)
		{
			unlink($name) or fail("cannot delete file \"$name\": $!");
		}
	}
}

sub dw_get_cycle_id
{
	my $clock = shift;
	my $service_category_id = shift;
	my $tld_id = shift;
	my $ns_id = shift;
	my $ip_id = shift;

	$ns_id = 0 unless (defined($ns_id));
	$ip_id = 0 unless (defined($ip_id));

	return "$clock-$service_category_id-$tld_id-$ns_id-$ip_id";
}

sub dw_translate_cycle_id
{
	my $cycle_id = shift;

	my @parts = split('-', $cycle_id);

	my $clock = $parts[0];
	my $service_category = dw_get_name(ID_SERVICE_CATEGORY, $parts[1]);
	my $tld = dw_get_name(ID_TLD, $parts[2]);
	my $ns = dw_get_name(ID_NS_NAME, $parts[3]) || '';
	my $ip = dw_get_name(ID_NS_IP, $parts[4]) || '';

	return "$clock-$service_category-$tld-$ns-$ip";
}

sub dw_error
{
	return $_dw_error;
}

sub dw_set_date
{
	$_year = shift;
	$_month = shift;
	$_day = shift;
}

#################
# Internal subs #
#################

sub __get_target_dir
{
	return '' if (!$tld);

	return $_year . '/' . $_month . '/' . $_day . '/' . $tld  . '/';
}

# only works with data files
sub __write_csv_file
{
	my $id_type = shift;

	if (opt('dry-run'))
	{
		my $header_printed = 0;
		foreach my $row (@{$_csv_files{$id_type}{'rows'}})
		{
			if ($header_printed == 0)
			{
				print("** ", $id_type, " **\n");
				$header_printed = 1;
			}

			print(join(',', @$row), "\n");
		}

		return 1;
	}

	return 1 if (!$_csv_files{$id_type}{'rows'});

	my $name = __get_target_dir();

	if ($name ne '')
	{
		if (!__make_path($name))
		{
			die(dw_error());
		}
	}

	$name .= __csv_file_name($id_type);

	my $fh;

	unless (open($fh, ">:encoding(utf8)", $name))
	{
		die($name . ": $!");
		return;
	}

	dbg("dumping to ", $name, "...");

	foreach my $row (@{$_csv_files{$id_type}{'rows'}})
	{
		if (opt('debug'))
		{
			my $str = '';
			my $has_undef = 0;
			foreach (@$row)
			{
				if (defined($_))
				{
					$str .= " [$_]";
				}
				else
				{
					$has_undef = 1;
					$str .= " [UNDEF]";
				}
			}

			wrn("$id_type CSV record with UNDEF value: ", $str) if ($has_undef == 1);
		}
		dbg('dumping: ', join(',', @$row)) if (opt('debug'));
		$_csv->print($fh, $row);
	}

	unless (close($fh))
	{
		die($name . ": $!");
                return;
	}

	return 1;
}

# only works with catalogs
sub __write_csv_catalog
{
	my $id_type = shift;

	return 1 if (opt('dry-run'));

	return 1 if (scalar(keys(%{$_csv_catalogs{$id_type}})) == 0);

	my $name = __get_target_dir();

	if ($name ne '')
	{
		if (!__make_path($name))
		{
			die(dw_error());
		}
	}

	$name .= __csv_catalog_name($id_type);

	my $fh;

	unless (open($fh, ">:encoding(utf8)", $name))
	{
		die($name . ": $!");
		return;
	}

	dbg("dumping to ", $name, "...");
	foreach my $name (sort {$_csv_catalogs{$id_type}{$a} <=> $_csv_catalogs{$id_type}{$b}} (keys(%{$_csv_catalogs{$id_type}})))
	{
		my $id = $_csv_catalogs{$id_type}{$name};

		dbg("dumping: $id,$name");
		$_csv->print($fh, [$id, $name]);
	}

	unless (close($fh))
	{
		die($name . ": $!");
                return;
	}

	return 1;
}

sub __csv_catalog_name
{
	my $id_type = shift;

	die("Catalog '$id_type' is unknown") unless ($CATALOGS{$id_type});

	return $CATALOGS{$id_type};
}

sub __make_path
{
	my $path = shift;

	make_path($path, {error => \my $err});

	if (@$err)
	{
		__set_dw_error($err);
		return;
	}

	return 1;
}

sub __set_dw_error
{
	$_dw_error = join('', @_);
}

#sub __read_csv_file
#{
# 	my $id_type = shift;
#
# 	my $file = __csv_file_name($id_type);
#
# 	if (!$_csv_files->{$file})
# 	{
# 		$_csv_files->{$file}->{'name'} =  __csv_file_name($file);
# 	}
#
# 	my $name = $_csv_files->{$file}->{'name'};
#
# 	if (! -r $name)
# 	{
# 		# file do not exist
# 		return 1;
# 	}
#
# 	my $fh;
#
# 	unless (open($fh, "<:encoding(utf8)", $name))
# 	{
# 		die($name . ": $!");
# 		return;
# 	}
#
# 	my @rows;
#
# 	while (my $row = $_csv->getline($fh))
# 	{
# 		#$row->[2] =~ m/pattern/ or next; # 3rd field should match
#
# 		push(@rows, $row);
# 	}
#
# 	close($fh);
#
# 	$_csv_files->{$file}->{'rows'} = \@rows;
#
# 	foreach my $row (@{$_csv_files->{$file}->{'rows'}})
# 	{
# 		dbg('read: ', join(',', @$row));
# 	}
#
# 	return 1;
# }
1;
