#!/usr/bin/perl
use strict;
use warnings;
use Encode;
use JSON::XS;
use Data::Dumper;
use Path::Tiny;
use Getopt::Long;

my $file;

sub extract_json()
{
    my $json;

    local $/; #enable slurp
    open my $fh, "<", "$file";
    $json = <$fh>;

    return decode_json($json);
}

sub array_to_str2($$)
{
    my $array = shift;
    my $prefix = shift;
    my $str = "|" . uc($prefix) . "|";

    foreach my $v (@$array)
    {
        $str .= $v . "|";
    }

    return $str;
}

sub process_json($)
{
    my $str;
    my $json = shift;

    if(ref($json) eq 'ARRAY')
    {
        foreach my $cases (@$json)
        {
            foreach my $key_case (keys %$cases)
            {
                my $case = $cases->{$key_case};

                $str .= "|CASE|" . $key_case . "|\n";

                foreach my $key_case_data (keys %$case)
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

                        $str .= array_to_str2($base_element->{"names"}, uc($key_case_data)) . "\n";
                        $str .= array_to_str2($base_element->{"values"}, uc($key_case_data)) . "\n";
                    }
                    elsif ($key_case_data eq "db_data")
                    {
                        die("ERROR: invalid json (db_data)") unless (ref($base_element) ne 'ARRAY');

                        foreach my $source_name (keys %$base_element)
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

                        foreach my $function_name (keys %$base_element)
                        {
                            my $function = $base_element->{$function_name};

                            $str .= "|" . uc($key_case_data) . "|" . $function_name . "|\n";
                            foreach my $key_params (keys %$function)
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
    }

    print($str);

    path("parsed_data")->spew_utf8($str);
}

die("Error in command line: Missing required argument \"file\"\n") unless GetOptions("file=s" => \$file);
process_json(extract_json());
