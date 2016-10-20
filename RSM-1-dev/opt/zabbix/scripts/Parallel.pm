package Parallel;

use strict;
use warnings;

use POSIX qw(:sys_wait_h);
use IO::Pipe;
use Exporter qw(import);
use Time::HiRes qw(time);
use Data::Dumper;

our @EXPORT = qw(fork_without_pipe fork_with_pipe handle_children print_children children_running set_max_children);

my $_MAX_CHILDREN;
my %_PIDS;

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

		$_PIDS{$pid}{'alive'} = 0 if ($_PIDS{$pid});
        }
};

sub fork_without_pipe
{
	$_MAX_CHILDREN = __get_cpu_num() if (!defined($_MAX_CHILDREN));

	return undef if (children_running() >= $_MAX_CHILDREN);

	my $pid = fork();

	die("fork() failed: $!") unless (defined($pid));

	if ($pid)
	{
		# parent
		$_PIDS{$pid}{'alive'} = 1;
	}
	else
	{
		# child
		undef(%_PIDS);
	}

	return $pid;
}

sub fork_with_pipe
{
	my $setfh_ref = shift;

	$_MAX_CHILDREN = __get_cpu_num() if (!defined($_MAX_CHILDREN));

	return undef if (children_running() >= $_MAX_CHILDREN);

	my $pipe = IO::Pipe->new();

	my $pid = fork();

	die("fork() failed: $!") unless (defined($pid));

	if ($pid)
	{
		# parent
		my $fh = $pipe->reader();
		$fh->blocking(0);	# set non-blocking I/O

		$_PIDS{$pid}{'alive'} = 1;
		$_PIDS{$pid}{'pipe'} = $pipe;

		return $pid;
	}

	# child
	undef(%_PIDS);

	my $fh = $pipe->writer();

	$setfh_ref->($fh) if ($setfh_ref);

	return $pid;
}

sub handle_children
{
	my $t0 = time();

	foreach my $pid (keys(%_PIDS))
	{
		if (my $pipe = $_PIDS{$pid}{'pipe'})
		{
			while (my $line = $pipe->getline())
			{
				print($line);
			}
		}

		delete($_PIDS{$pid}) unless ($_PIDS{$pid}{'alive'});
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

	foreach my $pid (keys(%_PIDS))
        {
		if ($_PIDS{$pid}{'alive'} != 0)
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
	return scalar(keys(%_PIDS));
}

sub set_max_children
{
	$_MAX_CHILDREN = shift;
}

sub __get_cpu_num
{
	open(CPU, "/proc/cpuinfo") or die("Can't open cpuinfo: $!\n");
	my $cpu_num = scalar(map(/^processor\s+: [0-9]+$/, <CPU>));
	close(CPU);

	return $cpu_num
}

1;
