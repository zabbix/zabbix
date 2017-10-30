#!/usr/bin/perl

use strict;
use warnings;

use JSON::XS;
use Types::Serialiser;

########################
#                      #
# validation functions #
#                      #
########################

use constant JSON_STRING	=> 0x01;
use constant JSON_OBJECT	=> 0x02;
use constant JSON_ARRAY		=> 0x04;
use constant JSON_BOOLEAN	=> 0x08;
use constant JSON_NULL		=> 0x10;

use constant SCHEMA_KEYS	=> {
	'type'	=> undef,
	'keys'	=> undef,
	'elem'	=> undef,
	'rule'	=> undef,
	'print'	=> undef
};

use constant KEY_KEYS	=> {
	'mand'	=> undef,
	'key'	=> undef,
	'value'	=> undef,
	'print'	=> undef
};

sub process_json($$$);

sub process_json($$$)
{
	my $path = shift;
	my $json = shift;
	my $schema = shift;

	croak("processing undefined JSON path") unless (defined($path));

	# $json may be undefined if we are asked to process null

	croak("undefined JSON schema for $path") unless (defined($schema));
	croak("invalid JSON schema for $path") unless (ref($schema) eq 'HASH');

	foreach my $schema_key (keys(%{$schema}))
	{
		croak("unexpected key '$schema_key' in schema for $path") unless(exists(SCHEMA_KEYS->{$schema_key}));
	}

	croak("missing 'type' in schema for $path") unless (exists($schema->{'type'}));

	my $type = $schema->{'type'};

	croak("undefined 'type' in schema for $path") unless (defined($type));

	if (exists($schema->{'rule'}) && !($type & JSON_STRING))
	{
		croak("'rule' in schema for $path that cannot be a string or a number");
	}

	if (exists($schema->{'keys'}) && !($type & JSON_OBJECT))
	{
		croak("'keys' in schema for $path that cannot be an object");
	}

	if (exists($schema->{'elem'}) && !($type & JSON_ARRAY))
	{
		croak("'elem' in schema for $path that cannot be an array");
	}

	if (!defined($json))	# JSON null
	{
		die("$path cannot be null") unless ($type & JSON_NULL);
	}
	elsif (ref($json) eq '')
	{
		if (Types::Serialiser::is_bool($json))	# JSON true or JSON false
		{
			die("$path cannot be boolean") unless ($type & JSON_BOOLEAN);
		}
		elsif (exists($schema->{'rule'}))	# JSON string and there is a rule given
		{
			my $rule = $schema->{'rule'};

			croak("unexpected type of 'rule' in schema for $path") unless (ref($rule) eq 'Regexp');

			die("$path does not pass validation rule") if ($json !~ $rule);
		}
	}
	elsif (ref($json) eq 'HASH')	# JSON object
	{
		die("$path cannot be an object") unless ($type & JSON_OBJECT);
		croak("missing 'keys' in schema for $path that can be an object") unless (exists($schema->{'keys'}));

		my $keys = $schema->{'keys'};

		croak("undefined 'keys' in schema for $path that can be an object") unless (defined($keys));

		if (ref($keys) eq 'CODE')	# reference to process_*()
		{
			&{$keys}($path, $json)
		}
		elsif (ref($keys) eq 'ARRAY')	# schemas for keys
		{
			my %expected_keys = ();

			foreach my $expected_key (@{$keys})
			{
				unless (ref($expected_key) eq 'HASH')
				{
					croak("unexpected type of one of 'keys' in schema for $path");
				}

				foreach my $key_key (keys(%{$expected_key}))
				{
					unless (exists(KEY_KEYS->{$key_key}))
					{
						croak("unexpected key '$key_key' in schema of key for $path");
					}
				}

				croak("missing key name in schema for $path") unless (exists($expected_key->{'key'}));

				my $key = $expected_key->{'key'};

				croak("missing key 'value' in schema for $path") unless (exists($expected_key->{'value'}));

				if (exists($json->{$key}))
				{
					process_json("$path.$key", $json->{$key}, $expected_key->{'value'});

					if (exists($expected_key->{'print'}))
					{
						my $print = $expected_key->{'print'};

						unless (ref($print) eq 'CODE')
						{
							croak("'print' of key $key for object $path is not a reference" .
									" to subroutine");
						}

						&{$print}($json->{$key});
					}
				}
				elsif (exists($expected_key->{'mand'}))
				{
					die("missing mandatory key $key in object $path");
				}

				$expected_keys{$key} = undef;
			}

			foreach my $key (keys(%{$json}))
			{
				die("unexpected key $key in $path object") unless (exists($expected_keys{$key}));
			}
		}
		else
		{
			croak("unexpected type of 'keys' in schema for $path");
		}
	}
	elsif (ref($json) eq 'ARRAY')	# JSON array
	{
		die("$path cannot be an array") unless ($type & JSON_ARRAY);
		croak("missing 'elem' in schema for $path that can be an array") unless (exists($schema->{'elem'}));

		my $elem = $schema->{'elem'};

		croak("undefined 'elem' in schema for $path that can be an array") unless (defined($elem));

		if (ref($elem) eq 'CODE')	# reference to process_*()
		{
			&{$elem}($path, $json)
		}
		elsif (ref($elem) eq 'HASH')	# schema for array element
		{
			for (my $i = 0; $i < scalar(@{$json}); $i++)
			{
				process_json("$path.[$i]", $json->[$i], $elem);
			}
		}
		else
		{
			croak("unexpected type of 'elem' in schema for $path");
		}
	}
	else
	{
		croak("processing unexpected JSON");
	}

	if (exists($schema->{'print'}))
	{
		my $print = $schema->{'print'};

		croak("'print' in schema for $path is not a reference to subroutine") unless (ref($print) eq 'CODE');
		&{$print}($json);
	}
}

######################
#                    #
# escaping functions #
#                    #
######################

sub escape_str($)
{
	my $str = shift;

	$str =~ s/\\/\\\\/;	# replace '\' with '\\'
	$str =~ s/\|/\\\|/;	# replace '|' with '\|'
	$str =~ s/\n/\\n/;	# replace LF with '\n'
	$str =~ s/\r/\\r/;	# replace CR with '\r'

	return $str;
}

sub escape_arr($)
{
	my $arr = shift;

	my $escaped = [];

	foreach my $str (@{$arr})
	{
		push(@{$escaped}, escape_str($str));
	}

	return $escaped;
}

######################
#                    #
# printing functions #
#                    #
######################

sub print_test_case($)
{
	my $test_case = shift;

	print("|CASE|" . escape_str($test_case) . "|\n");
}

sub print_tested_function($)
{
	my $tested_function = shift;

	print("|TESTED_FUNCTION|" . escape_str($tested_function) . "|\n");
}

sub print_in($$)
{
	my $in = shift;

	print("|IN_NAMES|" . join("|", @{escape_arr($in->{'names'})}) . "|\n");
	print("|IN_VALUES|" . join("|", @{escape_arr($in->{'values'})}) . "|\n");
}

sub print_out($)
{
	my $out = shift;

	print("|OUT_NAMES|" . join("|", @{escape_arr($out->{'names'})}) . "|\n");
	print("|OUT_VALUES|" . join("|", @{escape_arr($out->{'values'})}) . "|\n");
}

sub print_db_data($)
{
	my $db_data = shift;

	print("|DB_DATA|" . escape_str($db_data) . "|\n");
}

sub print_fields($)
{
	my $fields = shift;

	print("|FIELDS|" . join("|", @{escape_arr($fields)}) . "|\n");
}

sub print_row($)
{
	my $row = shift;

	print("|ROW|" . join("|", @{escape_arr($row)}) . "|\n");
}

sub print_function($)
{
	my $function = shift;

	print("|FUNCTION|" . escape_str($function) . "|\n");
}

sub print_function_out($)
{
	my $function_out = shift;

	print("|FUNC_OUT_PARAMS|" . join("|", @{escape_arr($function_out->{'params'})}) . "|\n");
	print("|FUNC_OUT_VALUES|" . join("|", @{escape_arr($function_out->{'values'})}) . "|\n");
}

######################################################
#                                                    #
# validation schemas and custom processing functions #
#                                                    #
######################################################

use constant ARRAY_OF_STRINGS	=> {
	'type'	=> JSON_ARRAY,
	'elem'	=> {
		'type'	=> JSON_STRING
	}
};

use constant NAMES_AND_VALUES	=> {
	'type'	=> JSON_OBJECT,
	'keys'	=> [
		{
			'mand'	=> undef,
			'key'	=> "names",
			'value'	=> ARRAY_OF_STRINGS
		},
		{
			'mand'	=> undef,
			'key'	=> "values",
			'value'	=> ARRAY_OF_STRINGS
		}
	]
};

use constant DB_DATA	=> {
	'type'	=> JSON_OBJECT,
	'keys'	=> [
		{
			'mand'	=> undef,
			'key'	=> "fields",
			'value'	=> ARRAY_OF_STRINGS,
			'print'	=> \&print_fields
		},
		{
			'mand'	=> undef,
			'key'	=> "rows",
			'value'	=> {
				'type'	=> JSON_ARRAY,
				'elem'	=> {
					'type'	=> JSON_ARRAY,
					'elem'	=> {
						'type'	=> JSON_STRING	# null is not supported
					},
					'print'	=> \&print_row
				}
			}
		}
	]
};

sub process_db_data($$)
{
	my $path = shift;
	my $db_data = shift;

	while (my ($source_name, $source_data) = each(%{$db_data}))
	{
		print_db_data($source_name);
		process_json("$path.$source_name", $source_data, DB_DATA);
	}
}

use constant PARAMS_AND_VALUES	=> {
	'type'	=> JSON_OBJECT,
	'keys'	=> [
		{
			'mand'	=> undef,
			'key'	=> "params",
			'value'	=> ARRAY_OF_STRINGS
		},
		{
			'mand'	=> undef,
			'key'	=> "values",
			'value'	=> ARRAY_OF_STRINGS
		}
	]
};

use constant FUNCTION_DATA	=> {
	'type'	=> JSON_OBJECT,
	'keys'	=> [
		{
			'mand'	=> undef,
			'key'	=> "out",
			'value'	=> PARAMS_AND_VALUES,
			'print'	=> \&print_function_out
		}
	]
};

sub process_functions($$)
{
	my $path = shift;
	my $functions = shift;

	while (my ($function_name, $function_data) = each(%{$functions}))
	{
		print_function($function_name);
		process_json("$path.$function_name", $function_data, FUNCTION_DATA);
	}
}

use constant SINGLE_TEST_CASE	=> {
	'type'	=> JSON_OBJECT,
	'keys'	=> [
		{
			'mand'	=> undef,
			'key'	=> "test_case",
			'value'	=> {
				'type'	=> JSON_STRING,
				'rule'	=> qr/\w/
			},
			'print'	=> \&print_test_case
		},
		{
			'mand'	=> undef,
			'key'	=> "tested_function",
			'value'	=> {
				'type'	=> JSON_STRING,
				'rule'	=> qr/[_a-zA-Z][_a-zA-Z0-9]*/
			},
			'print'	=> \&print_tested_function
		},
		{
			'mand'	=> undef,
			'key'	=> "in",
			'value'	=> NAMES_AND_VALUES,
			'print'	=> \&print_in
		},
		{
			'mand'	=> undef,
			'key'	=> "out",
			'value'	=> NAMES_AND_VALUES,
			'print'	=> \&print_out
		},
		{
			'key'	=> "db_data",
			'value'	=> {
				'type'	=> JSON_OBJECT,
				'keys'	=> \&process_db_data
			}
		},
		{
			'key'	=> "functions",
			'value'	=> {
				'type'	=> JSON_OBJECT,
				'keys'	=> \&process_functions
			}
		}
	]
};

use constant MULTIPLE_TEST_CASES	=> {
	'type'	=> JSON_ARRAY,
	'elem'	=> SINGLE_TEST_CASE
};

#############################
#                           #
# actual script starts here #
#                           #
#############################

my $input = "";

for my $line (<STDIN>)
{
	$input .= $line;
}

my $json = decode_json($input);

process_json("\$", $json, (ref($json) eq 'ARRAY' ? MULTIPLE_TEST_CASES : SINGLE_TEST_CASE));
