@echo off

mkdir Win64
mkdir Win64\zabbix_agentd
mkdir Win64\zabbix_agentd\zbxsysinfo
mkdir Win64\zabbix_agentd\zbxcomms

mc -U -s -h ".\\" -r ".\\" messages.mc

echo ..\..\..\src\libs\zbxjson\json.c > cl.tmp
echo ..\..\..\src\libs\zbxplugin\zbxplugin.c >> cl.tmp
echo ..\..\..\src\libs\zbxsys\threads.c >> cl.tmp
echo ..\..\..\src\libs\zbxsys\symbols.c >> cl.tmp
echo ..\..\..\src\libs\zbxsys\mutexs.c >> cl.tmp
echo ..\..\..\src\libs\zbxwin32\service.c >> cl.tmp
echo ..\..\..\src\libs\zbxwin32\perfmon.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\simple\simple.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\simple\ntp.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\win32.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\uptime.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\swap.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\services.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\sensors.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\proc.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\pdhmon.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\net.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\memory.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\kernel.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\inodes.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\diskspace.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\diskio.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\win32\cpu.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\sysinfo.c >> cl.tmp
echo ..\..\..\src\libs\zbxconf\cfg.c >> cl.tmp
echo ..\..\..\src\libs\zbxcrypto\md5.c >> cl.tmp
echo ..\..\..\src\libs\zbxcrypto\base64.c >> cl.tmp
echo ..\..\..\src\libs\zbxlog\log.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\zbxgetopt.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\xml.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\str.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\regexp.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\misc.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\gnuregex.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\comms.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\alias.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\zbxconf.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\zabbix_agentd.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\stats.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\perfstat.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\logfiles.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\listener.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\interfaces.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\eventlog.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\diskdevices.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\cpustat.c >> cl.tmp
echo ..\..\..\src\zabbix_agent\active.c >> cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "NDEBUG" /D "_WIN64" /D "_WINDOWS" /D "HAVE_WINLDAP_H" /D "HAVE_ASSERT_H" /D "_CONSOLE" /D "ZABBIX_SERVICE" /D "WITH_COMMON_METRICS" /D "WITH_SPECIFIC_METRICS" /D "WITH_SIMPLE_METRICS" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win64\zabbix_agentd\zabbix_agentd.pch" /Fo"Win64\zabbix_agentd\\" /Fd"Win64\zabbix_agentd\\" /FR"Win64\zabbix_agentd\\" /W3 /c /TC @cl.tmp /nologo

echo ..\..\..\src\libs\zbxcomms\comms.c > cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "NDEBUG" /D "_WIN64" /D "_WINDOWS" /D "HAVE_WINLDAP_H" /D "HAVE_ASSERT_H" /D "_CONSOLE" /D "ZABBIX_SERVICE" /D "WITH_COMMON_METRICS" /D "WITH_SPECIFIC_METRICS" /D "WITH_SIMPLE_METRICS" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win64\zabbix_agentd\zabbix_agentd.pch" /Fo"Win64\zabbix_agentd\zbxcomms\\" /Fd"Win64\zabbix_agentd\zbxcomms\\" /FR"Win64\zabbix_agentd\\" /W3 /c /TC @cl.tmp /nologo

echo ..\..\..\src\libs\zbxsysinfo\common\system.c > cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\common\net.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\common\http.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\common\file.c >> cl.tmp
echo ..\..\..\src\libs\zbxsysinfo\common\common.c >> cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "NDEBUG" /D "_WIN64" /D "_WINDOWS" /D "HAVE_WINLDAP_H" /D "HAVE_ASSERT_H" /D "_CONSOLE" /D "ZABBIX_SERVICE" /D "WITH_COMMON_METRICS" /D "WITH_SPECIFIC_METRICS" /D "WITH_SIMPLE_METRICS" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win64\zabbix_agentd\zabbix_agentd.pch" /Fo"Win64\zabbix_agentd\zbxsysinfo\\" /Fd"Win64\zabbix_agentd\zbxsysinfo\\" /FR"Win64\zabbix_agentd\\" /W3 /c /TC @cl.tmp /nologo

rc.exe /d "NDEBUG" /d "_VC80_UPGRADE=0x0600" /l 0x419 /fo"Win64\resource.res" .\resource.rc

echo ".\Win64\zabbix_agentd\active.obj" > link.tmp
echo ".\Win64\zabbix_agentd\cpustat.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\diskdevices.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\eventlog.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\interfaces.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\listener.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\logfiles.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\perfstat.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\stats.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zabbix_agentd.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxconf.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\alias.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\comms.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\gnuregex.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\misc.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\regexp.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\str.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\xml.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxgetopt.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\log.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\base64.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\md5.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\cfg.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\sysinfo.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\cpu.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\diskio.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\diskspace.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\inodes.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\kernel.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\memory.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\net.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\pdhmon.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\proc.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\sensors.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\services.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\swap.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\uptime.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\win32.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxsysinfo\common.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxsysinfo\file.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxsysinfo\http.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxsysinfo\net.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxsysinfo\system.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\ntp.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\simple.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\perfmon.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\service.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\mutexs.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\symbols.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\threads.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxplugin.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\zbxcomms\comms.obj" >> link.tmp
echo ".\Win64\zabbix_agentd\json.obj" >> link.tmp
echo ".\Win64\resource.res" >> link.tmp
link.exe /OUT:"../../../bin/win64/zabbix_agentd.exe" /INCREMENTAL:NO /MANIFEST /MANIFESTFILE:"Win64\zabbix_agentd\zabbix_agentd.exe.intermediate.manifest" /MANIFESTUAC:"level='asInvoker' uiAccess='false'" /PDB:"Win64\zabbix_agentd\zabbix_agentd.pdb" /SUBSYSTEM:CONSOLE /DYNAMICBASE:NO /MACHINE:X64 ws2_32.lib pdh.lib psapi.lib odbc32.lib odbccp32.lib Wldap32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib @link.tmp /NOLOGO

echo .\Win64\zabbix_agentd\zabbix_agentd.exe.intermediate.manifest > manifest.tmp
mt.exe /outputresource:"..\..\..\bin\win32\zabbix_agentd.exe;#1" /manifest @manifest.tmp /nologo

echo Manifest resource last updated at %TIME% on %DATE% > .\Win64\zabbix_agentd\mt.dep

echo .\Win64\zabbix_agentd\cpustat.sbr > bscmake.tmp
echo .\Win64\zabbix_agentd\diskdevices.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\eventlog.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\interfaces.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\listener.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\logfiles.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\perfstat.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\stats.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\zabbix_agentd.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\zbxconf.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\alias.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\comms.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\gnuregex.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\misc.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\regexp.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\str.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\xml.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\zbxgetopt.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\log.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\base64.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\md5.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\cfg.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\sysinfo.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\cpu.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\diskio.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\diskspace.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\inodes.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\kernel.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\memory.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\net.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\pdhmon.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\proc.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\sensors.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\services.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\swap.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\uptime.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\win32.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\common.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\file.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\http.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\system.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\ntp.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\simple.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\perfmon.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\service.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\mutexs.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\symbols.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\threads.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\zbxplugin.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\json.sbr >> bscmake.tmp
echo .\Win64\zabbix_agentd\active.sbr >> bscmake.tmp
bscmake.exe /o "Win64\zabbix_agentd\zabbix_agentd.bsc" @bscmake.tmp /nologo

del cl.tmp link.tmp manifest.tmp bscmake.tmp