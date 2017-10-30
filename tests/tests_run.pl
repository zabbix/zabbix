#!/usr/bin/perl

use strict;
use warnings;

use JSON::XS;
use Path::Tiny;
use IPC::Run3;

sub launch($$)
{
	my $test = shift;
	my $in = shift;
	my $out;
	my $err;

	run3("./json_parser.pl | $test", \$in, \$out, \$err);

	print("OUT:\n$out\n");
	print("ERR:\n$err\n");
}

my $iter = path(".")->iterator({
	'recurse'		=> 1,
	'follow_symlinks'	=> 0
});

while (my $path = $iter->())
{
	next unless ($path->is_file());
	next unless ($path->basename =~ qr/^(.+)\.json$/);

	my $json = decode_json($path->slurp_utf8());

	if (ref($json) eq 'ARRAY')
	{
		foreach my $test_case (@{$json})
		{
			my $data = encode_json($test_case);

			launch($path->parent() . "/$1", $data);
		}
	}
	elsif (ref($json) eq 'HASH')
	{
		my $data = encode_json($json);

		launch($path->parent() . "/$1", $data);
	}
}
