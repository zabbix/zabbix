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

for cmd
do
  case "$cmd" in
    copy )    copy="yes";;
    pre ) premake="yes";;
    premake ) premake="yes";;
    conf )      configure="yes";;
    config )    configure="yes";;
    configure ) configure="yes";;
    make )    domake="yes";;
    test )    dotest="yes";;
    tar )     tgz="yes";;
    nocat )   docat="no";;
    --enable-* ) config_param="$config_param $cmd";; 
    --with-* ) config_param="$config_param $cmd";;
    * ) 
        echo "$0: ERROR: uncnown parameter \"$cmd\""; 
        echo
        echo "Usage:"
        echo "  $0 [copy] [premake|pre] [configure|config|conf] [make] [test] [tar] [nocat] [--enable-*] [--with-*]"
        echo
        exit 1;;
  esac
done

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
  export CFLAGS="-Wall"
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

