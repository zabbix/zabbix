Name: zabbix
Version: 1.0beta10
Release: 1
Group: System Environment/Daemons
License: LGPL
Source: %{name}-%{version}.tar.gz
Patch0:	zabbix-1.0b9-ping.diff
Patch1: zabbix-1.0b9-phpconf.diff
Patch2:	zabbix-1.0b9-rh-init.diff
BuildRoot: %{_tmppath}/%{name}-root
BuildPrereq: mysql, ucd-snmp
BuildPrereq: mysql-devel, ucd-snmp-devel
BuildPrereq: rpm-devel, openssl-devel
Requires: mysql, ucd-snmp
Summary: A network monitor.

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
%patch0 -p1
%patch1 -p1
%patch2 -p1

%build
%configure --with-mysql --with-ucd-snmp
make

%post
/usr/sbin/useradd -r zabbix
/sbin/chkconfig --add zabbix_suckerd
/sbin/chkconfig --add zabbix_trapperd

%post agent
/usr/sbin/useradd -r zabbix
/sbin/chkconfig --add zabbix_agentd

%preun
if [ $1 = 0 ] ; then
    /usr/sbin/userdel zabbix
    /sbin/chkconfig --del zabbix_suckerd
    /sbin/chkconfig --del zabbix_trapperd
    /sbin/service zabbix_suckerd stop >/dev/null 2>&1
    /sbin/service zabbix_trapperd stop >/dev/null 2>&1
fi
exit 0

%preun agent
if [ $1 = 0 ] ; then
    /usr/sbin/userdel zabbix
    /sbin/chkconfig --del zabbix_agentd
    /sbin/service zabbix_agentd stop >/dev/null 2>&1
fi
exit 0

%clean
rm -fr $RPM_BUILD_ROOT

%install
rm -fr $RPM_BUILD_ROOT
#make install DESTDIR=$RPM_BUILD_ROOT
install -d %{buildroot}%{_sbindir}
install -m 755 bin/zabbix_sender %{buildroot}%{_sbindir}/
install -m 755 bin/zabbix_suckerd %{buildroot}%{_sbindir}/
install -m 755 bin/zabbix_trapper %{buildroot}%{_sbindir}/
install -m 755 bin/zabbix_trapperd %{buildroot}%{_sbindir}/
install -d %{buildroot}%{_libdir}/%{name}
cp -r frontends/php %{buildroot}%{_libdir}/%{name}/
install -d %{buildroot}%{_sysconfdir}/zabbix
install -d %{buildroot}%{_sysconfdir}/rc.d/init.d
install -m 755 misc/conf/zabbix_suckerd.conf %{buildroot}%{_sysconfdir}/zabbix/
install -m 755 misc/conf/zabbix_trapper.conf %{buildroot}%{_sysconfdir}/zabbix/
install -m 755 misc/conf/zabbix_trapperd.conf %{buildroot}%{_sysconfdir}/zabbix/
install -m 755 misc/conf/zabbix_php.conf %{buildroot}%{_sysconfdir}/zabbix/
install -m 755 misc/init.d/redhat/8.0/zabbix_suckerd %{buildroot}%{_sysconfdir}/rc.d/init.d/
install -m 755 misc/init.d/redhat/8.0/zabbix_trapperd %{buildroot}%{_sysconfdir}/rc.d/init.d/

install -m 755 bin/zabbix_agent %{buildroot}%{_sbindir}/
install -m 755 bin/zabbix_agentd %{buildroot}%{_sbindir}/
install -m 755 misc/conf/zabbix_agent.conf %{buildroot}%{_sysconfdir}/zabbix/
install -m 755 misc/conf/zabbix_agentd.conf %{buildroot}%{_sysconfdir}/zabbix/
install -m 755 misc/init.d/redhat/8.0/zabbix_agentd %{buildroot}%{_sysconfdir}/rc.d/init.d/

%files
%defattr(-,root,root)
%doc AUTHORS COPYING NEWS README INSTALL TODO doc create upgrades
%attr(0644,root,root) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_suckerd.conf
%attr(0644,root,root) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_trapper.conf
%attr(0644,root,root) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_trapperd.conf
%config(noreplace) %{_sysconfdir}/rc.d/init.d/zabbix_suckerd
%config(noreplace) %{_sysconfdir}/rc.d/init.d/zabbix_trapperd
%attr(0755,root,root) %{_sbindir}/zabbix_trapper
%attr(0755,root,root) %{_sbindir}/zabbix_trapperd
%attr(0755,root,root) %{_sbindir}/zabbix_suckerd

%files agent 
%defattr(-,root,root)
%attr(0644,root,root) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_agent.conf
%attr(0644,root,root) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_agentd.conf
%config(noreplace) %{_sysconfdir}/rc.d/init.d/zabbix_agentd
%attr(0755,root,root) %{_sbindir}/zabbix_agent
%attr(0755,root,root) %{_sbindir}/zabbix_agentd
%attr(0755,root,root) %{_sbindir}/zabbix_sender

%files phpfrontend
%defattr(-,root,root)
%attr(0644,root,root) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_php.conf
%attr(0755,root,root) %{_libdir}/%{name}/php

%changelog
* Alexei Vladishev <alex@gobbo.caves.lv>
- update to 1.0beta10 

* Tue Jun 01 2003 Harald Holzer <hholzer@may.co.at>
- update to 1.0beta9
- move phpfrontend config to /etc/zabbix

* Tue May 23 2003 Harald Holzer <hholzer@may.co.at>
- split the php frontend in a extra package

* Tue May 20 2003 Harald Holzer <hholzer@may.co.at>
- 1.0beta8
- initial packaging
