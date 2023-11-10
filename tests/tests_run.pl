#!/usr/bin/env perl

use strict;
use warnings;

use YAML::XS qw(LoadFile Dump);
use Path::Tiny qw(path);
use IPC::Run3 qw(run3);
use Time::HiRes qw(time);
use File::Basename qw(dirname);
use Getopt::Long qw(GetOptions);
use Cwd qw(getcwd);
use Pod::Usage;

use constant TEST_SUITE_ATTRIBUTES	=> ('name', 'tests', 'skipped', 'errors', 'failures', 'time');
use constant TEST_CASE_ATTRIBUTES	=> ('name', 'assertions', 'time');

use constant TEST_CASE_HEADER_FORMAT	=> " %*s │ %-7s │ %s\n";
use constant TEST_CASE_FORMAT		=> " %*d │ %-7s │ %s\n";
use constant TEST_SUITE_HEADER_FORMAT	=> " %-*s │ %9s │ %7s │ %6s │ %8s │ %5s\n";
use constant TEST_SUITE_FORMAT		=> " %-*s │ %9d │ %7d │ %6d │ %8d │ %5.2f\n";
use constant TEST_SUITE_PATTERN		=> qr/^( [a-zA-Z0-9_:]+\b\D*)(\d+)(\D*)(\d+)(\D*)(\d+)(\D*)(\d+)(.*)$/;

sub escape_xml_entity($)
{
	my $entity = shift;

	$entity =~ s/&/&amp;/g;
	$entity =~ s/</&lt;/g;
	$entity =~ s/>/&gt;/g;

	return $entity;
}

sub escape_xml_attribute($)
{
	my $attribute = escape_xml_entity(shift);

	$attribute =~ s/'/&apos;/g;
	$attribute =~ s/"/&quot;/g;

	return $attribute;
}

sub launch($$$)
{
	my $test_suite = shift;
	my $test_exec = shift;
	my $test_data = shift;

	my $start = time();

	$test_suite->{'tests'}++;

	my $test_case = {
		'name'		=> $test_data->{'test case'} // "N/A",
		'assertions'	=> 0
	};

	utf8::encode($test_case->{'name'});

	if (path($test_exec)->is_file)
	{
		my $in = Dump($test_data);
		my $out;
		my $err;

		my($path, $filename) = $test_exec =~ m{(.+)/([^/]+)$};

		if ($path ne '.')
		{
			my $current_dir = getcwd();
			chdir $path;
			eval {run3('./' . $filename, \$in, \$out, \$err)};
			chdir $current_dir;
		}
		else
		{
			eval {run3($test_exec, \$in, \$out, \$err)};
		}

		if ($@)	# something went wrong with run3()
		{
			$test_case->{'error'} = $!;
			$test_suite->{'errors'}++;
		}
		elsif ($?)	# something went wrong with test executable
		{
			$test_case->{'failure'} = "test case failed";
			$test_suite->{'failures'}++;

			$test_case->{'system-out'} = $out;
			$test_case->{'system-err'} = $err;
		}
	}
	else
	{
		$test_case->{'skipped'} = "no executable";
		$test_suite->{'skipped'}++;
	}

	my $end = time();

	$test_suite->{'time'} += $test_case->{'time'} = $end - $start;

	push(@{$test_suite->{'testcases'}}, $test_case);
}

my $xml;
my $target_suite;
my $help = 0;

GetOptions(
	'help|?' => \$help,
	'xml:s' => \$xml,
	'suite=s' => \$target_suite
) or pod2usage(2);

pod2usage(-verbose => 2, -noperldoc => 1) if ($help);

my $iter = path(dirname(Cwd::abs_path(__FILE__)))->iterator({
	'recurse'		=> 1,
	'follow_symlinks'	=> 0
});

my @test_suites = ();

while (my $path = $iter->())
{
	next unless ($path->is_file());
	next unless ($path->basename =~ qr/^(.+)\.yaml$/);
	next unless (not defined $target_suite or $1 eq $target_suite);
	next if ($path->basename =~ qr/^(.+)\.inc\.yaml$/);

	my $test_suite = {
		'name'		=> $1,
		'tests'		=> 0,
		'skipped'	=> 0,
		'errors'	=> 0,
		'failures'	=> 0,
		'time'		=> 0.0,
		'testcases'	=> []
	};

	foreach my $test_case (LoadFile($path))
	{
		launch($test_suite, $path->parent() . "/$1", $test_case);
	}

	push(@test_suites, $test_suite);
}

unless (defined($xml))
{
	use Term::ANSIColor qw(:constants);

	# find out requirements for column width

	my $longest_suite_name = 0;
	my $largest_case_index = 1;
	my $longest_case_name = 0;

	foreach my $test_suite (@test_suites)
	{
		if ($longest_suite_name < length($test_suite->{'name'}))
		{
			$longest_suite_name = length($test_suite->{'name'});
		}

		$largest_case_index = $test_suite->{'tests'} if ($largest_case_index < $test_suite->{'tests'});

		foreach my $test_case (@{$test_suite->{'testcases'}})
		{
			if ($longest_case_name < length($test_case->{'name'}))
			{
				$longest_case_name = length($test_case->{'name'});
			}
		}
	}

	$largest_case_index--;	# indices start from 0

	my $longest_case_index = 1;

	until (($largest_case_index /= 10) < 1)
	{
		$longest_case_index++;
	}

	if ($longest_case_name < length("Test case description"))
	{
		$longest_case_name = length("Test case description");
	}

	# do the printing

	my $split_cases = sub ($) {
		my $split_character = shift;

		print("─") for 1 .. $longest_case_index + 2;
		print($split_character);
		print("─") for 1 .. 7 + 2;
		print($split_character);
		print("─") for 1 .. $longest_case_name + 2;
		print("\n");
	};

	$split_cases->("┬");
	printf(TEST_CASE_HEADER_FORMAT, $longest_case_index, "#", "Status", "Test case description");
	$split_cases->("┴");

	foreach my $test_suite (@test_suites)
	{
		print(" " . BOLD . $test_suite->{'name'} . RESET . "\n");
		$split_cases->("┬");

		my $case_index = 0;

		foreach my $test_case (@{$test_suite->{'testcases'}})
		{
			my $case_status;
			my $color_status;

			if (exists($test_case->{'skipped'}))
			{
				$color_status = BRIGHT_YELLOW . ($case_status = "SKIPPED") . RESET;
			}
			elsif (exists($test_case->{'error'}))
			{
				$color_status = BRIGHT_MAGENTA . ($case_status = "ERROR") . RESET;
			}
			elsif (exists($test_case->{'failure'}))
			{
				$color_status = BRIGHT_RED . ($case_status = "FAILURE") . RESET;
			}
			else
			{
				$color_status = BRIGHT_GREEN . ($case_status = "OK") . RESET;
			}

			my $line = sprintf(TEST_CASE_FORMAT, $longest_case_index, $case_index, $case_status,
					$test_case->{'name'});

			$line =~ s/$case_status/$color_status/;
			print($line);

			if (exists($test_case->{'system-out'}))
			{
				print(BRIGHT_CYAN . BOLD . "STDOUT:" . RESET . "\n" . $test_case->{'system-out'});
			}

			if (exists($test_case->{'system-err'}))
			{
				print(BRIGHT_RED . BOLD . "STDERR:" . RESET . "\n" . $test_case->{'system-err'});
			}

			$case_index++;
		}

		$split_cases->("┴");
	}

	my $split_suites = sub ($) {
		my $split_character = shift;

		print("─") for 1 .. $longest_suite_name + 2;
		print($split_character);
		print("─") for 1 .. 9 + 2;
		print($split_character);
		print("─") for 1 .. 7 + 2;
		print($split_character);
		print("─") for 1 .. 6 + 2;
		print($split_character);
		print("─") for 1 .. 8 + 2;
		print($split_character);
		print("─") for 1 .. 5 + 2;
		print("\n");
	};

	$split_suites->("┬");
	printf(TEST_SUITE_HEADER_FORMAT, $longest_suite_name, "Test suite", "Succeeded", "Skipped", "Errors",
			"Failures", "Time");
	$split_suites->("┼");

	my $succeeded = 0;
	my $skipped = 0;
	my $errors = 0;
	my $failures = 0;
	my $time = 0.0;

	foreach my $test_suite (@test_suites)
	{
		sprintf(TEST_SUITE_FORMAT, $longest_suite_name, $test_suite->{'name'}, $test_suite->{'tests'} -
				$test_suite->{'skipped'} - $test_suite->{'errors'} - $test_suite->{'failures'},
				$test_suite->{'skipped'}, $test_suite->{'errors'}, $test_suite->{'failures'},
				$test_suite->{'time'}) =~ TEST_SUITE_PATTERN;

		print($1 . $2 . $3. ($4 eq "0" ? "0" : BRIGHT_YELLOW . BOLD . $4 . RESET) . $5 .
				($6 eq "0" ? "0" : BRIGHT_MAGENTA . BOLD . $6 . RESET) . $7 .
				($8 eq "0" ? "0" : BRIGHT_RED . BOLD . $8 . RESET) . $9 . "\n");

		$succeeded += $test_suite->{'tests'} - $test_suite->{'skipped'} - $test_suite->{'errors'} -
				$test_suite->{'failures'};
		$skipped += $test_suite->{'skipped'};
		$errors += $test_suite->{'errors'};
		$failures += $test_suite->{'failures'};
		$time += $test_suite->{'time'};
	}

	$split_suites->("┼");
	printf(TEST_SUITE_HEADER_FORMAT, $longest_suite_name, "Test suite", "Succeeded", "Skipped", "Errors",
			"Failures", "Time");
	$split_suites->("┼");
	sprintf(TEST_SUITE_FORMAT, $longest_suite_name, "Total:", $succeeded, $skipped, $errors, $failures, $time) =~
			TEST_SUITE_PATTERN;
	print($1 . $2 . $3. ($4 eq "0" ? "0" : BRIGHT_YELLOW . BOLD . $4 . RESET) . $5 .
			($6 eq "0" ? "0" : BRIGHT_MAGENTA . BOLD . $6 . RESET) . $7 .
			($8 eq "0" ? "0" : BRIGHT_RED . BOLD . $8 . RESET) . $9 . "\n");
	$split_suites->("┴");

	exit();	# stop here, do not print XML
}

print("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
print("<testsuites>\n");

foreach my $test_suite (@test_suites)
{
	print("  <testsuite");

	$test_suite->{'name'} = $xml . "." . $test_suite->{'name'} unless ($xml eq "");

	foreach my $attribute (TEST_SUITE_ATTRIBUTES)
	{
		die("missing test suite attribute \"$attribute\"") unless (exists($test_suite->{$attribute}));
		print(" $attribute=\"" . escape_xml_attribute($test_suite->{$attribute}) . "\"");
	}

	print(">\n");

	foreach my $test_case (@{$test_suite->{'testcases'}})
	{
		print("    <testcase");

		foreach my $attribute (TEST_CASE_ATTRIBUTES)
		{
			die("missing test case attribute \"$attribute\"") unless (exists($test_case->{$attribute}));
			print(" $attribute=\"" . escape_xml_attribute($test_case->{$attribute}) . "\"");
		}

		print(">\n");

		if (exists($test_case->{'skipped'}))
		{
			print("      <skipped");

			if (defined($test_case->{'skipped'}))
			{
				print(" message=\"" . escape_xml_attribute($test_case->{'skipped'}) . "\"");
			}

			print("/>\n");
		}
		elsif (exists($test_case->{'error'}))
		{
			# message attribute can be added too
			print("      <error>" . escape_xml_entity($test_case->{'error'}) . "</error>\n");
		}
		elsif (exists($test_case->{'failure'}))
		{
			# message attribute can be added too
			print("      <failure>" . escape_xml_entity($test_case->{'failure'}) . "</failure>\n");
		}

		if (exists($test_case->{'system-out'}))
		{
			print("      <system-out>" . escape_xml_entity($test_case->{'system-out'}) . "</system-out>\n");
		}

		if (exists($test_case->{'system-err'}))
		{
			print("      <system-err>" . escape_xml_entity($test_case->{'system-err'}) . "</system-err>\n");
		}

		print("    </testcase>\n");
	}

	print("  </testsuite>\n");
}

print("</testsuites>\n");

foreach my $test_suite (@test_suites)
{
	exit(-1) unless ($test_suite->{'failures'} + $test_suite->{'errors'} == 0);
}

__END__

=head1 SYNOPSIS

tests_run.pl [options]

  Options:

    -s|--suite <suite>        run specific test suite instead of all
    -x|--xml                  output in XML format
    -h|--help                 show help message

=head1 OPTIONS

=over 8

=item B<--suite> name

Run the test cases of specified test suite. Usually it's the name of the function for which the test suite is written.

=item B<--xml>

Output results in XML format.

=item B<--help>

Print this help message and exit.

=back

=cut

=head1 EXAMPLE

The following example will run all the test cases of convert_to_utf8 test suite:

  tests/tests_run.pl -s convert_to_utf8
