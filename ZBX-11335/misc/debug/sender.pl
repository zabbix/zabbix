#!/usr/bin/perl

use strict;
use warnings;
use Getopt::Long;
use IO::Socket;

my $host = '127.0.0.1';
my $port = 10051;
my $input = '-';
my $output = '-';
my $help = 0;

my %options =
(
	'host|h=s' => \$host,
	'port=i' => \$port,
	'input=s' => \$input,
	'output=s' => \$output,
	'help' => \$help
);

GetOptions(%options) or die "Bad command-line arguments\n";

do { print "Usage: $0 -h <host> -p <port> -i <file> -o <file>\n"; exit } if $help;

my $socket = new IO::Socket::INET(PeerAddr => $host, PeerPort => $port, Proto => 'tcp', Timeout => 1);
die "Could not connect to $host:$port: $!\n" unless $socket;

$socket->autoflush(0);

open INPUT, $input;
my $send = do { local $/; <INPUT> };
my $length = length $send;
close INPUT;

print $socket "ZBXD\1";
do { print $socket chr($length % 256); $length /= 256 } for 1..8;
print $socket $send;

$socket->flush();

open OUTPUT, "> $output";
print OUTPUT while <$socket>;
close OUTPUT;

close $socket;
