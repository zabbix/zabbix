
                     ZabbixW32 version 1.0.1

******************************************************************************


About
-----

ZabbixW32 is Zabbix agent for Win32 systems. It will work on Windows NT 4.0,
Windows 2000, Windows XP and Windows Server 2003. ZabbixW32 doesn't supposed
to work on other Windows platforms.


Installation
------------

Installation is very simple and includes 3 steps:

1. Unpack ZabbixW32.exe
2. Create configuration file c:\zabbix_agentd.conf (it has the same syntax as
   for UNIX agent).
3. Run command "ZabbixW32.exe install" to install Zabbix agent as service.
   If you wish to use configuration file other that c:\zabbix_agentd.conf,
   you should use the following command for service installation:
   "ZabbixW32.exe --config <your_configuration_file> install". Full path to
   configuration file should be specified.

Now you can use Control Panel to start agent's service or run
"ZabbixW32.exe start".

Windows NT 4.0 Note:
ZabbixW32 uses PDH (Performance Data Helper) API to gather various system
information, so PDH.DLL is needed. This DLL is not supplied with Windows NT 4.0
by default, so you need to download and install it by yourself. Microsoft
Knowledge Base article number 284996 describes this in detail and contains a 
download link. You can find this article at 
http://support.microsoft.com/default.aspx?scid=kb;en-us;284996


Command line syntax
-------------------

Usage: zabbixw32 [options] [command]

Where possible commands are:
   check-config    : Check configuration file and exit
   standalone      : Run in standalone mode
   start           : Start Zabbix Win32 Agent service
   stop            : Stop Zabbix Win32 Agent service
   install         : Install Zabbix Win32 Agent as service
   remove          : Remove previously installed Zabbix Win32 Agent service
   install-events  : Install Zabbix Win32 Agent as event source for Event Log
                     This is done automatically when service is being installed
   remove-events   : Remove Zabbix Win32 Agent event source
                     This is done automatically when service is being removed
   help            : Display help information
   version         : Display version information

And possible options are:
   --config <file> : Specify alternate configuration file
                     (default is C:\zabbix_agentd.conf)


Configuration file
------------------

Zabbix Win32 agent suports the following configuration parameters:

Server = <ip_address>[,<ip_address>[,<ip_address> ...]]
  Sets IP address(es) of Zabbix server(s). Agent will accept connections only
  from this address(es). To specify multiple servers, you can either write
  their addresses in one line separated by commans, or create multiple
  "Server = ..." lines.

ListenPort = <port_number>
  Sets TCP port number for incoming connections.

LogFile = <path>
  Sets the agent's log file. If this parameter is omitted, Event Log will
  be used. You can also specify Event Log as a target for logging implicitly
  by setting <path> to "{EventLog}" (without quotes).

LogLevel = <mask>
  Sets log level. It's an or'ed value of the following flags:
    0x01 - Log critical messages
    0x02 - Log warning messages
    0x04 - Log informational messages
  Default value is 0x07, which means "log all messages". Value can be either
  in decimal or hexadecimal form.

Timeout = <number>
  Sets the request processing timeout (in seconds). If server request will
  not be processed within specified timeout, appropriate error code will be
  returned to server. Default is 3 seconds.

MaxCollectorProcessingTime = <number>
  Sets maximum acceptable processing time of one data sample by collector
  thread (in milliseconds). If processing time will exceed specified value,
  warning message will be written to log file. Default value is 100
  milliseconds.

Alias = <alias_name>:<parameter_name>
  Sets the alias for parameter. It can be useful to substitute long and 
  complex parameter name with a smaller and simplies one. For example, if
  you wish to retrieve paging file usage in percents from the server, you
  can use parameter "perf_counter[\Paging File(_Total)\% Usage]", or you
  can define an alias by adding the following line to configuration file:

    Alias = pg_usage:perf_counter[\Paging File(_Total)\% Usage]

  After that you can use parameter name "pg_usage" to retrieve the same
  information. You can specify as many "Alias" records as you wish.
  Please note tht aliases can not be used for parameters defined in 
  "PerfCounter" configuration file records.

PerfCounter = <parameter_name>,"<perf_counter_path>",<period>
  Defines new parameter <parameter_name> which is an average value for
  system performance counter <perf_counter_path> for the specified time
  period <period> (in seconds). For example, if you wish to receive average
  number of processor interrupts per second for last minute, you can define
  new parameter "interrupts" as following:

    PerfCounter = interrupts,"\Processor(0)\Interrupts/sec",60

  Please note double quotes around performance counter path. Samples for
  calculating average value will be taken every second.

LogUnresolvedSymbols = (yes | no)
  Controls logging of unresolved symbols during agent startup. Values can be
  strings "yes" or "no" (without quotes).

UserParameter = <parameter_name>,<executable_path>
  Defines new parameter <parameter_name> which is an output of executable
  file specified by <executable_path>. Executable file should be console
  executable and send it's output to STDOUT.

The following parameters can be presented in configuration file for
compatibility with UNIX agents but has no effect:

  StartAgents
  DebugLevel
  PidFile
  NoTimeWait
               

Parameters supported by Zabbix Win32 Agent
------------------------------------------

I. Zabbix standard parameters

check_port[<port>]
check_port[<host>,<port>]
cksum[<path>]		<path> can be normal Windows path, like C:\, or UNC.
			Agent will return UNSUPPORTED if file is larger than
			64MB.
diskfree[<path>]	<path> can be normal Windows path, like C:\, or UNC
disktotal[<path>]       <path> can be normal Windows path, like C:\, or UNC
filesize[<path>]	<path> can be normal Windows path, like C:\, or UNC
memory[free]
memory[total]
memory[cached]		Only on Windows XP and Windows Server 2003
ping
proc_cnt[<process_name>]
swap[free]
swap[total]
system[hostname]
system[proccount]
system[procload]
system[procload5]
system[procload15]
system[uname]
system[uptime]
version[zabbix_agent]


II. Win32-specific parameters

agent[avg_collector_time] 
Average time spent by collector thread on each sample processing for
last minute (in milliseconds)                             

agent[max_collector_time]
Maximum time spent by collector thread on sample processing (in milliseconds)                             

agent[accepted_requests]
Total number of requests accepted by agent for processing.

agent[rejected_requests]
Total number of requests rejected by agent because they was coming from
unallowed source.

agent[timed_out_requests]
Total number of requests timed out in processing.

agent[accept_errors]
Total number of accept() syscall errors.

agent[processed_requests]
Total number of requests successfully processed by agent.

agent[failed_requests]
Total number of requests with errors in processing (requests generated
ZBX_ERROR return code).

agent[unsupported_requests]
Total number of requests for unsupported parameters (requests generated
ZBX_UNSUPPORTED return code).

cpu_util
Average CPU(s) utilization (in percents) for last minute

cpu_util5
Average CPU(s) utilization (in percents) for last 5 minutes

cpu_util15
Average CPU(s) utilization (in percents) for last 15 minutes

cpu_util[<instance>]
Average specific CPU utilization (in percents) for last minute, where
<instance> is zero-based CPU number

cpu_util5[<instance>]
Average specific CPU utilization (in percents) for last 5 minutes, where
<instance> is zero-based CPU number

cpu_util15[<instance>]
Average specific CPU utilization (in percents) for last 15 minutes, where
<instance> is zero-based CPU number

diskused[<instance>]
Number of used bytes on specific drive.

md5_hash[<file name>]
MD5 hash of specified file (returned as string). Agent will return UNSUPPORTED
if file is larger than 64MB.

perf_counter[<path>]
Value of any performance counter, where <path> is the counter path (you can use
Performance Monitor to obtain list of available counters). Please note that
this parameter will return correct value only for counters which requires just
one sample (like "\System\Threads"). It will not work as expected for counters
that requires more than one sample - like CPU utilization.

proc_info[<process>:<attribute>:<type>]
Different information about specific process(es).
    <process>   - process name (same as in proc_cnt[] parameter)
    <attribute> - requested process attribute. The following attributes are
                  currenty supported:
       vmsize      - Size of process virtual memory in Kbytes
       wkset       - Size of process working set (amount of physical memory
                     used by process) in Kbytes
       pf          - Number of page faults
       ktime       - Process kernel time in milliseconds
       utime       - Process user time in milliseconds
       io_read_b   - Number of bytes read by process during I/O operations
       io_read_op  - Number of read operation performed by process
       io_write_b  - Number of bytes written by process during I/O operations
       io_write_op - Number of write operation performed by process
       io_other_b  - Number of bytes transferred by process during operations
                     other than read and write operations
       io_other_op - Number of I/O operations performed by process, other
                     than read and write operations
       gdiobj      - Number of GDI objects used by process
       userobj     - Number of USER objects used by process
    <type>      - representation type (meaningful when more than one process
                  with the same name exists). Valid values are:
         min - minimal value among all processes named <process>
         max - maximal value among all processes named <process>
         avg - average value for all processes named <process>
         sum - sum of values for all processes named <process>
Examples:
1. To get amount of physical memory taken by all Internet Explorer processes,
use the following parameter:
   proc_info[iexplore.exe:wkset:sum]
2. To get average number of page faults for Internet Explorer processes,
use the following parameter:
   proc_info[iexplore.exe:pf:avg]
Notes:
1. All io_xxx,gdiobj and userobj attributes available only on Windows 2000
   and later versions of Windows, not on Windows NT 4.0.

service_state[<srv>]
State of service <srv>. The following states can be returned:
   0 - Running
   1 - Paused
   2 - Start pending
   3 - Pause pending
   4 - Continue pending
   5 - Stop pending
   6 - Stopped
   7 - Unknown
 255 - SCM communication error
Please note that <srv> should be real service name (as it seen in service
properties under "Name:"), not service display name!
