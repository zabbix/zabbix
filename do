#!/bin/sh

#
# Description:
#	ZABBIX compilateion script
# Author:
#	Eugene Grigorjev
#

win2nix="no"
premake="no"
copy="no"
tgz="no"
configure="no"
domake="no"
config_param="--prefix=`pwd`"
dotest="no"
cleanwarnings="no"
docat="yes"
help="no"
noparam=0;
def="--enable-agent --enable-server --with-mysql --with-ldap --with-net-snmp"

for cmd
do
  case "$cmd" in
    win2nix )	win2nix="yes";		noparam=1;;
    copy )	copy="yes";		noparam=1;;
    cpy )	copy="yes";		noparam=1;;
    pre )	premake="yes";		noparam=1;;
    premake )	premake="yes";		noparam=1;;
    conf )	configure="yes";	noparam=1;;
    config )	configure="yes";	noparam=1;;
    configure )	configure="yes";	noparam=1;;
    make )	domake="yes";		noparam=1;;
    test )	dotest="yes";		noparam=1;;
    tar )	tgz="yes";		noparam=1;;
    nocat )	docat="no";		noparam=1;;
    cat )	docat="yes";		noparam=1;;
    def )		config_param="$config_param $def";;
    --enable-* )	config_param="$config_param $cmd";; 
    --with-* )		config_param="$config_param $cmd";;
    --prefix=* )	config_param="$config_param $cmd";;
    help )	help="yes";;
    h )		help="yes";;
    * ) 
        echo "$0: ERROR: uncnown parameter \"$cmd\""; 
	help="yes";
  esac
done
if [ "$help" = "yes" ] || [ $noparam = 0 ]
then
        echo
        echo "Usage:"
        echo "  $0 [commands] [options]"
	echo
	echo " Commands:"
	echo "   [win2nix]                - convers win EOL [\\r\\n] to nix EOL [\\r]"
	echo "   [copy|cpy]               - copy automake files"
	echo "   [premake|pre]            - make configuration file"
	echo "   [configure|config|conf]  - configure make files"
	echo "   [make]                   - make applications"
	echo "   [test]                   - test applications"
	echo "   [tar]                    - create ../zabbix.tar.gz of this folder"
	echo
	echo " Options:"
	echo "   [def]            - default configuration \"$def\""
	echo "   [cat]            - cat WARRNING file at the end (defaut - ON)"
	echo "   [nocat]          - do not cat WARRNING file"
	echo "   [--enable-*]     - option for configuration"
	echo "   [--with-*]       - option for configuration"
        echo
        echo "Examples:"
        echo "  $0 conf def make test        - compyle, test, and sow report"
        echo "  $0 cpy tar nocat             - make archive .tar.gz and don't show report"
        echo "  $0 cat                       - cat last REPORT"
        echo "  $0                           - show this help"
        exit 1;
fi

if [ "$copy" = "yes" ] || [ $premake = "yes" ] || 
  [ $configure = "yes" ] || [ $domake = "yes" ] || 
  [ $dotest = "yes" ] || [ $tgz = "yes" ] ||
  [ "$win2nix" = "yes" ]
then
  cleanwarnings="yes"
fi

if [ "$cleanwarnings" = "yes" ] 
then
  rm -f WARNINGS
fi

if [ "$win2nix" = "yes" ]
then
  echo "Replacing..."
  echo "Replacing..." >> WARNINGS
  find ./src/zabbix_agent_win32/ -name "*.cpp" -exec vi "+%s/\\r$//" "+wq" "-es" {} ';' -print 2>> WARNINGS
  find ./src/zabbix_agent_win32/ -name "*.h" -exec vi "+%s/\\r$//" "+wq" "-es" {} ';' -print 2>> WARNINGS
fi

if [ "$premake" = "yes" ] 
then
  echo "Pre-making..."
  echo "Pre-making..." >> WARNINGS
  aclocal 2>> WARNINGS
  autoconf 2>> WARNINGS
  autoheader 2>> WARNINGS
  automake -a 2>> WARNINGS
  automake 2>> WARNINGS
fi

if [ "$copy" = "yes" ] 
then
  echo "Copyng..."
  echo "Copyng..." >> WARNINGS
  rm -f config.guess config.sub depcomp install-sh missing 2>> WARNINGS

  cp /usr/share/automake-1.9/config.guess config.guess 2>> WARNINGS
  cp /usr/share/automake-1.9/config.sub   config.sub 2>> WARNINGS
  cp /usr/share/automake-1.9/depcomp      depcomp 2>> WARNINGS
  cp /usr/share/automake-1.9/install-sh   install-sh 2>> WARNINGS
  cp /usr/share/automake-1.9/missing      missing 2>> WARNINGS
fi

if [ "$configure" = "yes" ] 
then
  echo "Configuring..."
  echo "Configuring..." >> WARNINGS
  #export CFLAGS="-Wall"
  #export CFLAGS="-Wall -pedantic"
  ./configure $config_param 2>>WARNINGS 
fi

if [ "$domake" = "yes" ] 
then
  echo "Cleaning..."
  echo "Cleaning..." >> WARNINGS
  make clean 2>>WARNINGS 
  echo "Making..."
  echo "Making..." >> WARNINGS
  make 2>>WARNINGS 
fi

if [ "$dotest" = "yes" ] 
then
  echo "Testing..."
  echo "Testing..." >> WARNINGS
  ./src/zabbix_agent/zabbix_agent -h >> WARNINGS
  ./src/zabbix_agent/zabbix_agentd -h >> WARNINGS
  ./src/zabbix_get/zabbix_get -h >> WARNINGS
  ./src/zabbix_sender/zabbix_sender -h >> WARNINGS
  ./src/zabbix_server/zabbix_server -h >> WARNINGS
  echo "------------------------" >> WARNINGS 
  echo "   Agent TEST RESULTS   " >> WARNINGS 
  echo "------------------------" >> WARNINGS 
  ./src/zabbix_agent/zabbix_agentd -p >> WARNINGS
fi

if [ "$tgz" = "yes" ] 
then
  echo "Zipping..."
  rm -f ../zabbix.tar.gz
  tar cvzf ../zabbix.tar.gz .
fi

if [ "$docat" = "yes" ] 
then
  echo
  echo WARNINGS
  echo "-----------------------------------"
  cat WARNINGS
  echo "-----------------------------------"
fi

