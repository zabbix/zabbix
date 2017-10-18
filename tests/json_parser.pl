#!/usr/bin/perl

use strict;
use warnings;

use Encode;
use JSON::XS;
use Data::Dumper;
use Path::Tiny;
use Getopt::Long;

sub array_to_str2($$)
{
	my $array = shift;
	my $prefix = shift;

	return $str = "|" . uc($prefix) . "|" . join("|", @{$array});
}

sub json2tmpl($)
{
	my $json = shift;

	die("JSON array expected") unless (ref($json) eq 'ARRAY');

	my $str;

	foreach my $cases (@{$json})
	{
		foreach my $key_case (keys %{$cases})
		{
			my $case = $cases->{$key_case};

			$str .= "|CASE|" . $key_case . "|\n";

			foreach my $key_case_data (keys %{$case})
			{
				my $base_element = $case->{$key_case_data};

				if ($key_case_data eq "tested_function")
				{
					die("ERROR: invalid json (tested_function)") unless (ref($base_element) ne 'HASH');

					$str .= "|" . uc($key_case_data) . "|" . $base_element . "|\n";
				}
				elsif ($key_case_data eq "in" or $key_case_data eq "out")
				{
					die("ERROR: invalid json (in/out)") unless (ref($base_element) ne 'ARRAY');

					$str .= array_to_str2($base_element->{"names"}, uc($key_case_data) . "_NAMES") . "\n";
					$str .= array_to_str2($base_element->{"values"}, uc($key_case_data) . "_VALUES") . "\n";
				}
				elsif ($key_case_data eq "db_data")
				{
					die("ERROR: invalid json (db_data)") unless (ref($base_element) ne 'ARRAY');

					foreach my $source_name (keys %{$base_element})
					{
						my $source = $base_element->{$source_name};

						$str .= "|" . uc($key_case_data) . "|" . $source_name . "|\n";

						foreach my $row (@{$source->{"rows"}})
						{
							$str .= array_to_str2($source->{"fields"}, "FIELDS") . "\n";
							$str .= array_to_str2($row, "ROW") . "\n";
						}
					}
				}
				elsif ($key_case_data eq "functions")
				{
					die("ERROR: invalid json (functions)") unless (ref($base_element) ne 'ARRAY');

					foreach my $function_name (keys %{$base_element})
					{
						my $function = $base_element->{$function_name};

						$str .= "|" . uc($key_case_data) . "|" . $function_name . "|\n";

						foreach my $key_params (keys %{$function})
						{
							my $params = $function->{$key_params};

							$str .= array_to_str2($params->{"params"}, "FUNC_" . uc($key_params) . "_PARAMS") . "\n";
							$str .= array_to_str2($params->{"values"}, "FUNC_" . uc($key_params) . "_VALUES") . "\n";
						}
					}
				}
			}
		}
	}

	return $str;
}

my $file;

die("Error in command line: Missing required argument \"file\"\n") unless GetOptions("file=s" => \$file);

path("parsed_data")->spew_utf8(json2tmpl(decode_json(path($file)->slurp_utf8())));
