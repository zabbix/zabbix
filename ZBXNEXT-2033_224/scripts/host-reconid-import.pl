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
	printf "    --ci=<pos>              - CI field position (default: 3)\n";
	printf "    --reconid=<pos>         - Recon ID field position (default: 4)\n";
	printf "    --serviceclass=<pos>    - Service class field position (default: 5)\n";
}

my $URL;
my $USER;
my $PASSWORD;
my $CI = 3;
my $RECONID = 4;
my $SERVICECLASS = 5;

GetOptions(
	"url=s" => \$URL,
	"username=s" => \$USER,
	"password=s" => \$PASSWORD,
	"ci=s" => \$CI,
	"reconid=s" => \$RECONID,
	"serviceclasss=s" => \$SERVICECLASS
) or die("Error in command line arguments\n");

$URL = $URL . "/api_jsonrpc.php";

my $filename = $ARGV[0];

if (!$filename or !$URL or !$USER or !$PASSWORD)
{
	display_usage();
	exit;
}

$CI--;
$RECONID--;
$SERVICECLASS--;

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
die "Authentication failed\n" unless $response and $response->content->{'result'};

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
		inventory => { tag => '', serialno_b => ''},
	},
	id => 3,
	auth => "$authID",
};

# Update host inventory in database

FILE_LOOP: while (<FP>) {
 	chomp;
 	my @fields = split "," , $_;
	my $hostname = $fields[$CI];
	my $i;
	
	if (!exists $fields[$CI]) {
		next;
	}
	
	foreach $i (1, 2) {
		if (exists $hosts{$hostname}) {
			printf "Importing CI '%s' ReconID '%s', service class '%s' to host '%s'\n", 
					$fields[$CI] , $fields[$RECONID], $fields[$SERVICECLASS], $hostname;
			
			$json->{params}->{hostid} = $hosts{$hostname};
			$json->{params}->{inventory}->{tag} = $fields[$RECONID];
			$json->{params}->{inventory}->{serialno_b} = $fields[$SERVICECLASS];
			
			$response = $client->call($URL, $json);

			# Check if response was successful
			die "Host update failed for host '" . $hostname . "' (" . $hosts{$hostname} . ")\n" 
				unless $response->content->{result};
				
			next FILE_LOOP;
		}
		
		$hostname = uc($hostname);
	}
	
	printf "Host '%s' was not found in Zabbix\n", $fields[$CI]
}
 
close (FP); 

