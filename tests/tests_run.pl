#!/usr/bin/perl

use strict;
use warnings;

use YAML::XS qw(LoadFile Dump);
use Path::Tiny qw(path);
use IPC::Run3 qw(run3);
use Time::HiRes qw(clock);
use File::Basename qw(dirname);

use constant TEST_SUITE_ATTRIBUTES	=> ('name', 'tests', 'skipped', 'errors', 'failures', 'time');
use constant TEST_CASE_ATTRIBUTES	=> ('name', 'assertions', 'time');

sub escape_xml_entity($)
{
	my $entity = shift;

	$entity =~ s/</&lt;/g;
	$entity =~ s/>/&gt;/g;
	$entity =~ s/&/&amp;/g;

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

	my $start = clock();

	$test_suite->{'tests'}++;

	my $test_case = {
		'name'		=> $test_data->{'test case'} // "N/A",
		'assertions'	=> 0
	};

	if (path($test_exec)->is_file)
	{
		my $in = Dump($test_data);
		my $out;
		my $err;

		eval {run3($test_exec, \$in, \$out, \$err)};

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

	my $end = clock();

	$test_suite->{'time'} += $test_case->{'time'} = $end - $start;

	push(@{$test_suite->{'testcases'}}, $test_case);
}

my $iter = path(".")->iterator({
	'recurse'		=> 1,
	'follow_symlinks'	=> 0
});

my @test_suites = ();

while (my $path = $iter->())
{
	next unless ($path->is_file());
	next unless ($path->basename =~ qr/^(.+)\.yaml$/);

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

print("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
print("<testsuites>\n");

foreach my $test_suite (@test_suites)
{
	print("  <testsuite");

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
