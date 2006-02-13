#!/bin/sh

premake="no"
copy="no"
tgz="no"
configure="no"
domake="no"
config_param="--enable-agent --prefix=`pwd`"
dotest="no"
cleanwarnings="no"
docat="yes"
help="no"
noparam=0;

for cmd
do
  case "$cmd" in
    copy )    copy="yes"; noparam=1;;
    cpy )     copy="yes"; noparam=1;;
    pre ) premake="yes"; noparam=1;;
    premake ) premake="yes"; noparam=1;;
    conf )      configure="yes"; noparam=1;;
    config )    configure="yes"; noparam=1;;
    configure ) configure="yes"; noparam=1;;
    make )    domake="yes"; noparam=1;;
    test )    dotest="yes"; noparam=1;;
    tar )     tgz="yes"; noparam=1;;
    nocat )   docat="no"; noparam=1;;
    cat )   docat="yes"; noparam=1;;
    --enable-* ) config_param="$config_param $cmd";; 
    --with-* ) config_param="$config_param $cmd";;
    help ) help="yes";;
    h ) help="yes";;
    * ) 
        echo "$0: ERROR: uncnown parameter \"$cmd\""; 
	help="yes";
  esac
done
if [ "$help" = "yes" ] || [ $noparam = 0 ]
then
        echo
        echo "Usage:"
        echo "  $0 [copy|cpy] [premake|pre] [configure|config|conf] [make] [test] [tar] [cat] [nocat] [--enable-*] [--with-*]"
        echo
        echo "Examples:"
        echo "  $0 conf make test            - compyle, test, and hsow report"
        echo "  $0 cpy tar nocat             - make archive .tar.gz and don't show report"
        echo "  $0 cat                       - cat last REPORT"
        echo "  $0                           - show this help"
        exit 1;
fi

if [ "$copy" = "yes" ] || [ $premake = "yes" ] || 
  [ $configure = "yes" ] || [ $domake = "yes" ] || 
  [ $dotest = "yes" ] || [ $tgz = "yes" ]
then
  cleanwarnings="yes"
fi

if [ "$cleanwarnings" = "yes" ] 
then
  rm -f WARNINGS
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
  echo "------------------" >> WARNINGS 
  echo "   TEST RESULTS   " >> WARNINGS 
  echo "------------------" >> WARNINGS 
  ./src/zabbix_agent/zabbix_agentd -p >> WARNINGS
fi

if [ "$tgz" = "yes" ] 
then
  echo "Zipping..."
  cd ..
  rm -f zabbix.tar.gz
  tar cvzf zabbix.tar.gz zabbix
fi

if [ "$docat" = "yes" ] 
then
  echo
  echo WARNINGS
  echo "-----------------------------------"
  cat WARNINGS
  echo "-----------------------------------"
fi

