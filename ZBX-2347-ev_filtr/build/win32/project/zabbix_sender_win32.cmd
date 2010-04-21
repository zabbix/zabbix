@echo off

mkdir Win32
mkdir Win32\zabbix_sender
mkdir Win32\zabbix_sender\zbxcomms

mc -U -s -h ".\\" -r ".\\" messages.mc

echo ..\..\..\src\libs\zbxjson\json.c > cl.tmp
echo ..\..\..\src\libs\zbxcrypto\base64.c >> cl.tmp
echo ..\..\..\src\libs\zbxsys\mutexs.c >> cl.tmp
echo ..\..\..\src\zabbix_sender\zabbix_sender.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\zbxgetopt.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\xml.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\str.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\regexp.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\misc.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\gnuregex.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\comms.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\alias.c >> cl.tmp
echo ..\..\..\src\libs\zbxconf\cfg.c >> cl.tmp
echo ..\..\..\src\libs\zbxlog\log.c >> cl.tmp
echo ..\..\..\src\libs\zbxsys\threads.c >> cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /D "_WINDOWS" /D "HAVE_WINLDAP_H" /D "HAVE_ASSERT_H" /D "WIN32" /D "NDEBUG" /D "_CONSOLE" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win32\zabbix_sender\zabbix_sender.pch" /Fo"Win32\zabbix_sender\\" /Fd"Win32\zabbix_sender\\" /FR"Win32\zabbix_sender\\" /W3 /c /TC @cl.tmp /nologo

echo ..\..\..\src\libs\zbxcomms\comms.c > cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /D "_WINDOWS" /D "HAVE_WINLDAP_H" /D "HAVE_ASSERT_H" /D "WIN32" /D "NDEBUG" /D "_CONSOLE" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win32\zabbix_sender\zabbix_sender.pch" /Fo"Win32\zabbix_sender\zbxcomms\\" /Fd"Win32\zabbix_sender\zbxcomms\\" /FR"Win32\zabbix_sender\\" /W3 /c /TC @cl.tmp /nologo

echo ".\Win32\zabbix_sender\zabbix_sender.obj" > link.tmp
echo ".\Win32\zabbix_sender\mutexs.obj" >> link.tmp
echo ".\Win32\zabbix_sender\threads.obj" >> link.tmp
echo ".\Win32\zabbix_sender\log.obj" >> link.tmp
echo ".\Win32\zabbix_sender\cfg.obj" >> link.tmp
echo ".\Win32\zabbix_sender\zbxcomms\comms.obj" >> link.tmp
echo ".\Win32\zabbix_sender\alias.obj" >> link.tmp
echo ".\Win32\zabbix_sender\comms.obj" >> link.tmp
echo ".\Win32\zabbix_sender\gnuregex.obj" >> link.tmp
echo ".\Win32\zabbix_sender\misc.obj" >> link.tmp
echo ".\Win32\zabbix_sender\regexp.obj" >> link.tmp
echo ".\Win32\zabbix_sender\str.obj" >> link.tmp
echo ".\Win32\zabbix_sender\xml.obj" >> link.tmp
echo ".\Win32\zabbix_sender\zbxgetopt.obj" >> link.tmp
echo ".\Win32\zabbix_sender\base64.obj" >> link.tmp
echo ".\Win32\zabbix_sender\json.obj" >> link.tmp
link.exe /OUT:"../../../bin/win32/zabbix_sender.exe" /INCREMENTAL:NO /MANIFEST /MANIFESTFILE:"Win32\zabbix_sender\zabbix_sender.exe.intermediate.manifest" /MANIFESTUAC:"level='asInvoker' uiAccess='false'" /PDB:"Win32\zabbix_sender\zabbix_sender.pdb" /SUBSYSTEM:CONSOLE /DYNAMICBASE:NO /MACHINE:X86 ws2_32.lib odbc32.lib odbccp32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib @link.tmp /NOLOGO

echo .\Win32\zabbix_sender\zabbix_sender.exe.intermediate.manifest > manifest.tmp
mt.exe /outputresource:"..\..\..\bin\win32\zabbix_sender.exe;#1" /manifest @manifest.tmp /nologo

echo Manifest resource last updated at %TIME% on %DATE% > .\Win32\zabbix_sender\mt.dep

echo .\Win32\zabbix_sender\mutexs.sbr > bscmake.tmp
echo .\Win32\zabbix_sender\threads.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\log.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\cfg.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\comms.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\alias.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\gnuregex.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\misc.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\regexp.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\str.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\xml.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\zbxgetopt.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\base64.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\json.sbr >> bscmake.tmp
echo .\Win32\zabbix_sender\zabbix_sender.sbr >> bscmake.tmp
bscmake.exe /o "Win32\zabbix_sender\zabbix_sender.bsc" @bscmake.tmp /nologo

del cl.tmp link.tmp manifest.tmp bscmake.tmp