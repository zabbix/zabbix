#!/bin/bash

ZBX_ROOT=".."
ZBX_RPM_TOPDIR="$(cd $(dirname $0); pwd)"
ZBX_COMMON_H="$ZBX_ROOT/include/common.h"
ZBX_SPEC="zabbix.spec"
ZBX_SPEC_TMPL="zabbix.spec.tmpl"
ZBX_CHLOG_DATE="$(date +'%a %b %e %Y')"
ZBX_CHLOG_NAME="Zabbix Support"
ZBX_CHLOG_EMAIL="support[at]zabbix.com"

err()
{
    echo "ERROR: "$*
    exit 1
}

setup_env()
{
    zbx_tgz=$1

    cp -f $zbx_tgz SOURCES || exit

    for dir in SPECS SRPMS RPMS BUILD; do
	[ -d $dir ] || mkdir $dir || exit
    done
}

set_spec_version()
{
    zbx_version=$1
    zbx_release=$2

    sed -e "s/\<__TMPL_ZABBIX_VERSION__\>/$zbx_version/g" \
	-e "s/\<__TMPL_ZABBIX_RPM_RELEASE__\>/$zbx_release/g" \
	-e "s/\<__TMPL_CHLOG_DATE__\>/$ZBX_CHLOG_DATE/g" \
	-e "s/\<__TMPL_CHLOG_NAME__\>/$ZBX_CHLOG_NAME/g" \
	-e "s/\<__TMPL_CHLOG_EMAIL__\>/$ZBX_CHLOG_EMAIL/g" \
    $ZBX_SPEC_TMPL > SPECS/$ZBX_SPEC || exit
}

cd $ZBX_RPM_TOPDIR || exit

# get Zabbix version
[ -r $ZBX_COMMON_H ] || err "$ZBX_COMMON_H not found"
zbx_version=$(egrep '^#define.*\<ZABBIX_VERSION\>' $ZBX_COMMON_H | awk '{print $NF}' | sed 's/[^0-9A-Za-z.-]//g')
[ -n "$zbx_version" ] || err "could not get Zabbix version from $ZBX_COMMON_H"

# get RPM release
zbx_release=$(egrep '^\<Release\>' $ZBX_SPEC_TMPL | awk '{print $NF}')
[ -n "$zbx_release" ] || err "could not get RPM Release from $ZBX_SPEC_TMPL"

# Zabbix tarball should be in place
zbx_tgz=$ZBX_ROOT/zabbix-$zbx_version.tar.gz
[ -r $zbx_tgz ] || err "$zbx_tgz not found (did you forget to run 'make dist'?)"

# setup RPM environment
setup_env $zbx_tgz

# set version in SPEC file
set_spec_version $zbx_version $zbx_release

# build RPMs
cd SPECS || exit
rpmbuild --define="_topdir $ZBX_RPM_TOPDIR" -ba $ZBX_SPEC
