package Parallel;

use strict;
use warnings;

use POSIX qw(:sys_wait_h);
use IO::Pipe;
use Exporter qw(import);
use Time::HiRes qw(time);
use Data::Dumper;

our @EXPORT = qw(fork_without_pipe fork_with_pipe handle_children print_children children_running set_max_children);

my $MAX_CHILDREN = 64;
my %PIDS;

sub ts_str
{
	my $ts = shift;
	$ts = time() unless ($ts);

	my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($ts);

	$year += 1900;
	$mon++;

	return sprintf("%4.2d/%2.2d/%2.2d %2.2d:%2.2d:%2.2d", $year, $mon, $mday, $hour, $min, $sec);
}

# SIGCHLD handler
$SIG{CHLD} = sub
{
        while ((my $pid = waitpid(-1, WNOHANG)) > 0)
	{
		#print(ts_str(), " child $pid exited\n");

		$PIDS{$pid}{'alive'} = 0 if ($PIDS{$pid});
        }
};

sub fork_without_pipe
{
	return undef if (children_running() >= $MAX_CHILDREN);

	my $pid = fork();

	die("fork() failed: $!") unless (defined($pid));

	if ($pid)
	{
		# parent
		$PIDS{$pid}{'alive'} = 1;
	}
	else
	{
		# child
		undef(%PIDS);
	}

	return $pid;
}

sub fork_with_pipe
{
	my $setfh_ref = shift;

	return undef if (children_running() >= $MAX_CHILDREN);

	my $pipe = IO::Pipe->new();

	my $pid = fork();

	die("fork() failed: $!") unless (defined($pid));

	if ($pid)
	{
		# parent
		my $fh = $pipe->reader();
		$fh->blocking(0);	# set non-blocking I/O

		$PIDS{$pid}{'alive'} = 1;
		$PIDS{$pid}{'pipe'} = $pipe;

		return $pid;
	}

	# child
	undef(%PIDS);

	my $fh = $pipe->writer();

	$setfh_ref->($fh) if ($setfh_ref);

	return $pid;
}

sub handle_children
{
	my $t0 = time();

	foreach my $pid (keys(%PIDS))
	{
		if (my $pipe = $PIDS{$pid}{'pipe'})
		{
			while (my $line = $pipe->getline())
			{
				print($line);
			}
		}

		delete($PIDS{$pid}) unless ($PIDS{$pid}{'alive'});
	}

	my $t1 = time();
	my $diff = $t1 - $t0;

	#printf("%s handle_children() took %f s\n", ts_str($1), $diff) if ($diff > 0.001);
}

sub print_children
{
	my $print_sub = shift;

	my $alive = 0;
	my $dead = 0;

	foreach my $pid (keys(%PIDS))
        {
		if ($PIDS{$pid}{'alive'} != 0)
		{
			$alive++;
		}
		else
		{
			$dead++;
		}
	}

	my $msg = "children: alive:$alive dead:$dead";

	if ($print_sub)
	{
		$print_sub->($msg);
	}
	else
	{
		print("$msg\n");
	}
}

sub children_running
{
	return scalar(keys(%PIDS));
}

sub set_max_children
{
	$MAX_CHILDREN = shift;
}

1;
