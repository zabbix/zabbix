#!/usr/bin/perl

use 5.010;
use strict;
use warnings;
use JSON::RPC::Client;
use Data::Dumper;
use JSON;
use Getopt::Long;

sub display_usage
{
	printf "ReconID -> Zabbix:host_inventory.tag importing utility\n";
	printf "Usage: host-record-import.pl <options> <filename>\n";
	printf "  Where <options> are:\n";
	printf "    --url=<url>             - Zabbix frontend URL\n";
	printf "    --username=<username>   - Zabbix user name\n";
	printf "    --password=<password>   - Zabbix user password\n";
}

my $URL;
my $USER;
my $PASSWORD;

GetOptions(
	"url=s" => \$URL,
	"username=s" => \$USER,
	"password=s" => \$PASSWORD
) or die("Error in command line arguments\n");

$URL = $URL . "/api_jsonrpc.php";

my $filename = $ARGV[0];

if (!$filename or !$URL or !$USER or !$PASSWORD)
{
	display_usage();
	exit;
}

# Update host inventory in database
open (FP, $filename) or die "Failed to open input file '" . $filename . "'";

# Authenticate yourself
my $client = new JSON::RPC::Client;
my $authID;
my $response;
my %hosts;

my $json = {
	jsonrpc => "2.0",
	method => "user.login",
	params => {
		user => $USER,
		password => $PASSWORD
	},
	id => 1
};

$response = $client->call($URL, $json);

# Check if response was successful
die "Authentication failed\n" unless $response->content->{'result'};

$authID = $response->content->{'result'};

$json = {
	jsonrpc=> '2.0',
	method => 'host.get',
	params =>
	{
		output => ['hostid','host'],# get only host id and host name
		sortfield => 'host',        # sort by host name
	},
	id => 2,
	auth => "$authID",
};

$response = $client->call($URL, $json);

# Check if response was successful
die "Failed to retrieve hosts\n" unless $response->content->{result};

# Create host=>hostid mapping
foreach my $host (@{$response->content->{result}}) {
	$hosts{$host->{host}} = $host->{hostid};
}

$json = {
	jsonrpc=> '2.0',
	method => 'host.update',
	params =>
	{	
		hostid => 0,
		inventory_mode => '1',
		inventory => { tag => ''},
	},
	id => 3,
	auth => "$authID",
};

# Update host inventory in database

while (<FP>) {
 	chomp;
 	my @fields = split "," , $_;
	
	if (exists $fields[1] and exists $hosts{$fields[1]}) {
		printf "Importing CI '%s' ReconID '%s'\n", $fields[1] , $fields[3];
		
		$json->{params}->{hostid} = $hosts{$fields[1]};
		$json->{params}->{inventory}->{tag} = $fields[3];
		
		$response = $client->call($URL, $json);

		# Check if response was successful
		die "Host update failed for host '" . $fields[1] . "' (" . $hosts{$fields[1]} . ")\n" 
			unless $response->content->{result};
	}
	else {
		printf "Host '%s' was not found in Zabbix\n", $fields[1]
	}
}
 
close (FP); 

