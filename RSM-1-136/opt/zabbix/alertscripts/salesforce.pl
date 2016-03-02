#!/usr/bin/perl
#
# Examples: http://ubertechs.blogspot.com/2011/06/salesforce-perl-using-wwwsalesforce.html
#
#
#
#
# Case table: http://www.salesforce.com/us/developer/docs/api/Content/sforce_api_objects_case.htm
# DateTime format: http://www.salesforce.com/us/developer/docs/officetoolkit/Content/sforce_api_calls_soql_select_dateformats.htm
#

use lib '/opt/zabbix/scripts';

use strict;
use WWW::Salesforce;
use Getopt::Long;
use Data::Dumper;
use RSM;

my $config = get_rsm_config();

my $tld;
my $target = 'DNS';
my $subject;
my $description;

my $exit = 0;

my $args = GetOptions("tld=s" => \$tld,
                    "target=s"   => \$target,
		    "subject=s"	=> \$subject,
		    "description=s" => \$description);

unless (defined($tld)) {
    print "Please specify TLD: --tld=<tld_name>.\n";
    $exit++;
}
unless (defined($subject)) {
    print "Please specify subject of message: --subject=<subject>.\n";
    $exit++;
}
unless (defined($description)) {
    print "Please specify descrition of the message: --descrition=<description>.\n";
    $exit++;
}

exit -1 if $exit > 0;

# Authenticate first via SOAP interface to get a session ID:
my $sforce = eval { WWW::Salesforce->login(
                    username => $config->{'salesforce'}->{'username'},
                    password => $config->{'salesforce'}->{'password'} ); };
	    die "Could not login to SFDC: $@" if $@;

# Get the session ID:
my $hdr = $sforce->get_session_header();
my $sid = ${$hdr->{_value}->[0]}->{_value}->[0];

print $sid."\n";

my $accountid = get_account($target);

if (!defined($accountid)) {
    print "Could not find AccountId for $target\n";
    exit;
}

my $caseid = check_case($accountid, $subject);

if (!defined($caseid)) {
    $caseid = create_case($accountid, $subject, $description);

    print "Incedent with CaseID = $caseid has created\n";
}
else {
    my $commentid = add_comment($caseid, $description);

    print "Comment to CaseID = $caseid is added. CommentID is $commentid\n";
}

sub get_account($) {
    my $target = shift;

    my $query = "SELECT Id, Name FROM Account WHERE Name like '%$target'";
    my $results = eval { $sforce->query( query => $query); };

    return unless defined $results;

    my $size = $results->result->{'size'};

    if ($size == 1) {
        return $results->result->{'records'}->{'Id'}[0];
    }
    elsif ($size > 1) {
	return ${$results->result->{'records'}}[0]->{'Id'}[0];
    }

}

sub check_case($) {
    my $accountid = shift;
    my $subject = shift;

    my $query = "SELECT Id, IsClosed, CaseNumber, Status, AccountId, Subject, Description,CreatedDate FROM Case WHERE AccountId = '$accountid' AND Subject = '$subject' AND Status != 'Closed'";
    my $results   = eval { $sforce->query( query => $query); };

    return unless defined $results;

    my $size = $results->result->{'size'};

    if ($size == 1) {
        return $results->result->{'records'}->{'Id'}[0];
    }
    elsif ($size > 1) {
        return ${$results->result->{'records'}}[0]->{'Id'}[0];
    }

    return;
}

sub create_case() {
    my $accountid = shift;
    my $subject = shift;
    my $description = shift;

    my %case=();

    %case = (
	AccountID => $accountid,
        Subject => $subject,
        Description => $description,
    );

    my $result = $sforce->create(type => 'case',%case);

    if (defined($result->result) and $result->result->{'success'} eq 'true') {
        return $result->result->{'id'};
    }

    return $result->result;
}

sub add_comment($$) {
    my $caseid = shift;
    my $comment = shift;

    return if (!defined($caseid) or !defined($comment));

    my %case_comment;

    %case_comment = (
	ParentId => $caseid,
        CommentBody => $comment,
    );

    my $result = $sforce->create(type => 'CaseComment', %case_comment);

    if (defined($result->result) and $result->result->{'success'} eq 'true') {
	return $result->result->{'id'};
    }

    return $result->result;
}

