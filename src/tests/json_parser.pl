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

sub hash_to_str($$$)
{
    my $str;
    my $hash = shift;
    my $str1 = shift . "|";
    my $str2 = shift . "|";

    foreach my $k (keys %$hash)
    {
        $str1 .= $k . "|";
        $str2 .= $hash->{$k} . "|";
    }

    $str .= "|" . $str1 . "\n";
    $str .= "|" . $str2 . "\n";
    
    return $str;
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

sub array_to_str($$$)
{
    my $str;
    my $array = shift;
    my $str1;
    my $str2;

    foreach my $key (@$array)
    {
        foreach my $key_value (keys %$key)
        {
            $str1 .= $key_value . "|";
            $str2 .= $key->{$key_value} . "|";
        }
    }

    $str .= "|" . shift . "|" . $str1 . "\n";
    $str .= "|" . shift . "|" . $str2 . "\n";
    
    return $str;
}

sub rows_to_str($$)
{
    my $str;
    my $data_src = shift;
    my $data_title = shift;
    
    foreach my $data_src_name (keys %$data_src) #tablename
    {
        my $data_src_value = $data_src->{$data_src_name};

        $str .= "|" . $data_title . "|" . $data_src_name . "|\n";
        
        foreach my $rows (@$data_src_value)
        {
            foreach my $key_row (keys %$rows) #fields, row
            {
                $str .= array_to_str($rows->{$key_row}, "FIELDS", "ROW");
            }
        }
    }
    
    return $str;
}

sub functions_to_str($$)
{
    my $str;
    my $func = shift;
    my $func_title = shift;
    
    foreach my $key_func (keys %$func)
    {
        $str .= "|" . $func_title . "|" . $key_func . "|\n";
        
        my $func_data = $func->{$key_func};
        
        foreach my $key_data (keys %$func_data)
        {
            $str .= array_to_str($func_data->{$key_data}, "FUNC_" . uc($key_data), "FUNC_" . uc($key_data) . "_VALUES");
        }
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
                        
                        $str .= array_to_str2($base_element->{"names"}, uc($key_case_data) . "_NAMES") . "\n";
                        $str .= array_to_str2($base_element->{"values"}, uc($key_case_data) . "_VALUES") . "\n";
                    }
                    elsif ($key_case_data eq "db_data")
                    {
                        die("ERROR: invalid json (db_data)") unless (ref($base_element) ne 'ARRAY');

                        #print($base_element->{$key_case_data});
                        
                        #print  Dumper($base_element);
                        
                          print Dumper($base_element);
                        
                        my $db1 = $base_element->{$key_case_data};
                    
                        foreach my $db (@$db1)
                        {
                            print Dumper($base_element);
                        }
                    
                        #foreach my $db (keys %$db1)
                        #foreach my $db (keys %$db_data)
                        #
                             print("\n1\n");
                        #    my $tables = $db_data->{$db};
                        #    $str .= "|" . uc($key_case_data) . "|\n";
                        #    foreach my $table (@$tables)
                        #    {
                        #        $str .= "|" . uc($key_case_data) . "|" . $table . "|\n";
                        #    }
                        #}
                            #foreach my $key (@$array)
                            #foreach my $key_value (keys %$key)
                        
                        #foreach my $data_src_name (keys %$data_src)
                        
                        #foreach my $data_src_name (keys %$data_src) #tablename
                        #foreach my $data (keys %($base_element->{$key_case_data}))
                        #{
                        #}
                        #$str .= rows_to_str($base_element, uc($key_case_data));
                    }
                    elsif ($key_case_data eq "functions")
                    {
                        my $db1 = $base_element->{"functions"};
                        
                        #print  Dumper($base_element);
                        
                        foreach my $db (keys %$db1)
                        {
                            print("\n1\n");
                        }
                        
                                        #my $str;
                                        #my $func = shift;
                                        #my $func_title = shift;
                                        
                                        #foreach my $key_func (keys %$func)
                                        #{
                                        #    $str .= "|" . $func_title . "|" . $key_func . "|\n";
                                        #    
                                        #    my $func_data = $func->{$key_func};
                                        #    
                                        #    foreach my $key_data (keys %$func_data)
                                        #    {
                                        #        $str .= array_to_str($func_data->{$key_data}, "FUNC_" . uc($key_data), "FUNC_" . uc($key_data) . "_VALUES");
                                        #    }
                                        #}
                    
                                        #return $str;
                    }
                }
            }
        }
    }

    print($str);
    
    path("parsed_data")->spew_utf8($str);
}

#TODO: support multiple files
die("Error in command line: Missing required argument \"file\"\n") unless GetOptions("file=s" => \$file);
process_json(extract_json());

