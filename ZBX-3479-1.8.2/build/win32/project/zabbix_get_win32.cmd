@echo off

mkdir Win32
mkdir Win32\zabbix_get
mkdir Win32\zabbix_get\zbxcomms

mc -U -s -h ".\\" -r ".\\" messages.mc

echo ..\..\..\src\libs\zbxsys\threads.c > cl.tmp
echo ..\..\..\src\libs\zbxsys\symbols.c >> cl.tmp
echo ..\..\..\src\libs\zbxsys\mutexs.c >> cl.tmp
echo ..\..\..\src\libs\zbxlog\log.c >> cl.tmp
echo ..\..\..\src\libs\zbxconf\cfg.c >> cl.tmp
echo ..\..\..\src\libs\zbxcrypto\base64.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\zbxgetopt.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\xml.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\str.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\misc.c >> cl.tmp
echo ..\..\..\src\libs\zbxcommon\comms.c >> cl.tmp
echo ..\..\..\src\zabbix_get\zabbix_get.c >> cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /I "../../../src/zabbix_get" /D "_WINDOWS" /D "WIN32" /D "NDEBUG" /D "_CONSOLE" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win32\zabbix_get\zabbix_get.pch" /Fo"Win32\zabbix_get\\" /Fd"Win32\zabbix_get\\" /FR"Win32\zabbix_get\\" /W3 /c /TC @cl.tmp /nologo

echo ..\..\..\src\libs\zbxcomms\comms.c > cl.tmp
cl.exe /O2 /Ob1 /I "./" /I "../include/" /I "../../../include/" /I "../../../src/zabbix_get" /D "_WINDOWS" /D "WIN32" /D "NDEBUG" /D "_CONSOLE" /D "HAVE_IPV6" /D "_VC80_UPGRADE=0x0600" /D "_MBCS" /GF /FD /EHsc /MT /Gy /Fp"Win32\zabbix_get\zabbix_get.pch" /Fo"Win32\zabbix_get\zbxcomms\\" /Fd"Win32\zabbix_get\zbxcomms\\" /FR"Win32\zabbix_get\\" /W3 /c /TC @cl.tmp /nologo

echo ".\Win32\zabbix_get\zabbix_get.obj" > link.tmp
echo ".\Win32\zabbix_get\comms.obj" >> link.tmp
echo ".\Win32\zabbix_get\misc.obj" >> link.tmp
echo ".\Win32\zabbix_get\str.obj" >> link.tmp
echo ".\Win32\zabbix_get\xml.obj" >> link.tmp
echo ".\Win32\zabbix_get\zbxgetopt.obj" >> link.tmp
echo ".\Win32\zabbix_get\zbxcomms\comms.obj" >> link.tmp
echo ".\Win32\zabbix_get\base64.obj" >> link.tmp
echo ".\Win32\zabbix_get\cfg.obj" >> link.tmp
echo ".\Win32\zabbix_get\log.obj" >> link.tmp
echo ".\Win32\zabbix_get\mutexs.obj" >> link.tmp
echo ".\Win32\zabbix_get\symbols.obj" >> link.tmp
echo ".\Win32\zabbix_get\threads.obj" >> link.tmp
link.exe /OUT:"../../../bin/win32/zabbix_get.exe" /INCREMENTAL:NO /MANIFEST /MANIFESTFILE:"Win32\zabbix_get\zabbix_get.exe.intermediate.manifest" /MANIFESTUAC:"level='asInvoker' uiAccess='false'" /PDB:"Win32\zabbix_get\zabbix_get.pdb" /SUBSYSTEM:CONSOLE /DYNAMICBASE:NO /MACHINE:X86 ws2_32.lib odbc32.lib odbccp32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib @link.tmp /NOLOGO

echo .\Win32\zabbix_get\zabbix_get.exe.intermediate.manifest > manifest.tmp
mt.exe /outputresource:"..\..\..\bin\win32\zabbix_get.exe;#1" /manifest @manifest.tmp /nologo

echo Manifest resource last updated at %TIME% on %DATE% > .\Win32\zabbix_get\mt.dep

echo .\Win32\zabbix_get\comms.sbr > bscmake.tmp
echo .\Win32\zabbix_get\misc.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\str.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\xml.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\zbxgetopt.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\base64.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\cfg.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\log.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\mutexs.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\symbols.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\threads.sbr >> bscmake.tmp
echo .\Win32\zabbix_get\zabbix_get.sbr >> bscmake.tmp
bscmake.exe /o "Win32\zabbix_get\zabbix_get.bsc" @bscmake.tmp /nologo

del cl.tmp link.tmp manifest.tmp bscmake.tmp