%define apxs    /usr/sbin/apxs2
%define apache_datadir          %(%{apxs} -q DATADIR)
%define apache_sysconfdir       %(%{apxs} -q SYSCONFDIR)

Name: zabbix
Version: 1.1alpha6
Release: 1
Group: System Environment/Daemons
License: GPL
Source: %{name}-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-root
BuildPrereq: mysql-client, mysql-devel, ucdsnmp
Requires: mysql-client, ucdsnmp
Summary: A network monitor.

%define zabbix_prefix           /opt/%{name}
%define zabbix_bindir 		%{zabbix_prefix}/bin
%define zabbix_confdir 		%{_sysconfdir}/%{name}
%define zabbix_phpfrontend	%{zabbix_prefix}/frontends/php

%description
zabbix is a network monitor.

%package agent
Summary: Zabbix agent
Group: System Environment/Daemons

%description agent
the zabbix network monitor agent.

%package phpfrontend
Summary: Zabbix web frontend (php).
Group: System Environment/Daemons
Requires: php

%description phpfrontend
a php frontend for zabbix.

%prep
%setup -q

%build
%configure --with-mysql --with-ucd-snmp
make

# adjust in several files /home/zabbix
for zabbixfile in misc/conf/* misc/init.d/suse/*/zabbix_agentd src/zabbix_server/{alerter,server}.c; do
  sed -i -e "s#/home/zabbix/bin#%{zabbix_bindir}#g" \
         -e "s#PidFile=/var/tmp#PidFile=%{_localstatedir}/run#g" \
         -e "s#LogFile=/tmp#LogFile=%{_localstatedir}/log#g" \
         -e "s#/home/zabbix/lock#%{_localstatedir}/lock#g" $zabbixfile
done

# adjust /home/zabbix to /usr/share/doc/packages/
sed -i -e "s#/home/zabbix#%{_defaultdocdir}#g" create/data/images.sql

%pre
if [ -z "`grep zabbix etc/group`" ]; then
  usr/sbin/groupadd zabbix >/dev/null 2>&1
fi
if [ -z "`grep zabbix etc/passwd`" ]; then
  usr/sbin/useradd -g zabbix zabbix >/dev/null 2>&1
fi

%pre agent
if [ -z "`grep zabbix etc/group`" ]; then
  usr/sbin/groupadd zabbix >/dev/null 2>&1
fi
if [ -z "`grep zabbix etc/passwd`" ]; then
  usr/sbin/useradd -g zabbix zabbix >/dev/null 2>&1
fi

%post agent
%{fillup_and_insserv -f zabbix_agentd}

if [ -z "`grep zabbix_agent etc/services`" ]; then
  cat >>etc/services <<EOF
zabbix_agent	10050/tcp
EOF
fi

if [ -z "`grep zabbix_trap etc/services`" ]; then
  cat >>etc/services <<EOF
zabbix_trap	10051/tcp
EOF
fi

%postun agent
%{insserv_cleanup}

%clean
rm -fr $RPM_BUILD_ROOT

%install
rm -fr $RPM_BUILD_ROOT
#make install DESTDIR=$RPM_BUILD_ROOT

# create directory structure
install -d %{buildroot}%{zabbix_bindir}
install -d %{buildroot}%{zabbix_confdir}
install -d %{buildroot}%{_sysconfdir}/init.d
install -d %{buildroot}%{apache_sysconfdir}/conf.d

# copy binaries
install -m 755 bin/zabbix_* %{buildroot}%{zabbix_bindir}

# copy conf files
install -m 755 misc/conf/zabbix_*.conf %{buildroot}%{zabbix_confdir}

# copy frontends
cp -r frontends %{buildroot}%{zabbix_prefix}

# apache2 config
cat >zabbix.conf <<EOF
Alias /%{name} %{zabbix_phpfrontend}

<Directory "%{zabbix_phpfrontend}">
    Options FollowSymLinks
    AllowOverride None
    Order allow,deny
    Allow from all
</Directory>
EOF

install -m 644 zabbix.conf %{buildroot}%{apache_sysconfdir}/conf.d

# SuSE Start Scripts
install -m 755 misc/init.d/suse/9.1/zabbix_* %{buildroot}%{_sysconfdir}/init.d/

%files
%defattr(-,root,root)
%doc AUTHORS COPYING NEWS README INSTALL create upgrades
%dir %attr(0755,root,root) %{zabbix_confdir}
%attr(0644,root,root) %config(noreplace) %{zabbix_confdir}/zabbix_server.conf
%dir %attr(0755,root,root) %{zabbix_prefix}
%dir %attr(0755,root,root) %{zabbix_bindir}
%attr(0755,root,root) %{zabbix_bindir}/zabbix_server

%files agent 
%defattr(-,root,root)
%dir %attr(0755,root,root) %{zabbix_confdir}
%attr(0644,root,root) %config(noreplace) %{zabbix_confdir}/zabbix_agent.conf
%attr(0644,root,root) %config(noreplace) %{zabbix_confdir}/zabbix_agentd.conf
%config(noreplace) %{_sysconfdir}/init.d/zabbix_agentd
%dir %attr(0755,root,root) %{zabbix_prefix}
%dir %attr(0755,root,root) %{zabbix_bindir}
%attr(0755,root,root) %{zabbix_bindir}/zabbix_agent
%attr(0755,root,root) %{zabbix_bindir}/zabbix_agentd
%attr(0755,root,root) %{zabbix_bindir}/zabbix_sender

%files phpfrontend
%defattr(-,root,root)
%attr(0644,root,root) %config(noreplace) %{apache_sysconfdir}/conf.d/zabbix.conf
%dir %attr(0755,root,root) %{zabbix_prefix}
%dir %attr(0755,root,root) %{zabbix_prefix}/frontends
%attr(0755,root,root) %{zabbix_phpfrontend}

%changelog
* Fri Jan 29 2005 Dirk Datzert <dirk@datzert.de>
- update to 1.1aplha6

* Tue Jun 01 2003 Alexei Vladishev <alexei.vladishev@zabbix.com>
- update to 1.0beta10 

* Tue Jun 01 2003 Harald Holzer <hholzer@may.co.at>
- update to 1.0beta9
- move phpfrontend config to /etc/zabbix

* Tue May 23 2003 Harald Holzer <hholzer@may.co.at>
- split the php frontend in a extra package

* Tue May 20 2003 Harald Holzer <hholzer@may.co.at>
- 1.0beta8
- initial packaging
