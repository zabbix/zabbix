%define debug_package %{nil}

Name:		zabbix-agentd
Version:	1.1.1
Release:	2
Group:		System Environment/Daemons
License:	GPL
Summary:	ZABBIX network monitor agent
Vendor:		ZABBIX SIA
URL:		http://www.zabbix.org
Packager:	Alexey Zilber <AlexeyZilber@gmail.com>
Source:		zabbix-%{version}.tar.gz

Autoreq:	no
Buildroot: 	%{_tmppath}/%{name}-%{version}-%{release}-buildroot

Prefix:		/usr/local/zabbix
Prefix:		/var/run
Prefix:		/var/log

Requires:         sed
Requires(post):   chkconfig, initscripts
Requires(preun):  chkconfig, initscripts
Requires(postun): initscripts

%define zabbix_bindir	/usr/local/zabbix/bin
%define zabbix_confdir	/etc/zabbix
%define zabbix_initdir	/etc/rc.d/init.d
%define zabbix_docdir	/usr/share/doc/%{name}-%{version}-%{release}

%define zabbix_piddir	/var/run/zabbix
%define zabbix_logdir	/var/log/zabbix

%description
The ZABBIX agent is a network monitor

%prep
%setup -n zabbix-%{version}

%build
%configure --enable-agent
make

%clean
rm -fr $RPM_BUILD_ROOT

%install
rm -fr $RPM_BUILD_ROOT

# copy documentation
install -d %{buildroot}%{zabbix_docdir}
install -m 644 AUTHORS %{buildroot}%{zabbix_docdir}/AUTHORS
install -m 644 COPYING %{buildroot}%{zabbix_docdir}/COPYING
install -m 644 NEWS %{buildroot}%{zabbix_docdir}/NEWS
install -m 644 README %{buildroot}%{zabbix_docdir}/README
install -m 644 ChangeLog %{buildroot}%{zabbix_docdir}/ChangeLog


# copy binaries
install -d %{buildroot}%{zabbix_bindir}
install -s -m 755 src/zabbix_agent/zabbix_agentd %{buildroot}%{zabbix_bindir}/zabbix_agentd

# copy config files
install -d %{buildroot}%{zabbix_confdir}
install -m 755 misc/conf/zabbix_agentd.conf %{buildroot}%{zabbix_confdir}/zabbix_agentd.conf

# copy startup script
install -d %{buildroot}%{zabbix_initdir}
install -m 755 misc/init.d/redhat/8.0/zabbix_agentd %{buildroot}%{zabbix_initdir}/zabbix_agentd

%post
# create ZABBIX group
if [ -z "`grep zabbix /etc/group`" ]; then
  /usr/sbin/groupadd zabbix >/dev/null 2>&1
fi

# create ZABBIX uzer
if [ -z "`grep zabbix /etc/passwd`" ]; then
  /usr/sbin/useradd -g zabbix zabbix >/dev/null 2>&1
fi

# configure ZABBIX agent daemon
mkdir -p %{zabbix_piddir}
mkdir -p %{zabbix_logdir}
%{__sed} -i -e 's|Hostname=localhost|Hostname=`uname -n`|g' \
	-e 's|PidFile=/var/tmp/zabbix_agentd.pid|PidFile=%{zabbix_piddir}/zabbix_agentd.pid|g' \
	-e 's|LogFile=/tmp/zabbix_agentd.log|LogFile=%{zabbix_logdir}/zabbix_agentd.log|g' \
	-e 's|#RefreshActiveChecks=120|RefreshActiveChecks=600|g' \
	-e 's|#DisableActive=1|DisableActive=0|g' \
	-e 's|Timeout=3|Timeout=30|g' \
	-e 's|DebugLevel=3|DebugLevel=2|g' \
	%{zabbix_confdir}/zabbix_agentd.conf
chown zabbix.zabbix %{zabbix_piddir}
chown zabbix.zabbix %{zabbix_logdir}
%{__sed} -i -e 's|progdir=\"/usr/local/zabbix/bin/\"|USER=zabbix; progdir=\"%{zabbix_bindir}/\"; conffile=\"%{zabbix_confdir}/zabbix_agentd.conf\"|g' \
	-e 's|su -c \$progdir\$prog - \$USER|su -c \"\$progdir\$prog -c \$conffile\" - \$USER|g' \
	%{zabbix_initdir}/zabbix_agentd 
chkconfig --add zabbix_agentd
chkconfig --levels 345 zabbix_agentd on
%preun
if [ -n "`/sbin/pidof zabbix_agentd`" ]; then
service zabbix_agentd stop
sleep 2
fi
%postun
rm -f %{zabbix_piddir}/zabbix_agentd.pid
rm -f %{zabbix_logdir}/zabbix_agentd.log

%files
%dir %attr(0755,root,root) %{zabbix_docdir}
%attr(0644,root,root) %{zabbix_docdir}/AUTHORS
%attr(0644,root,root) %{zabbix_docdir}/COPYING
%attr(0644,root,root) %{zabbix_docdir}/NEWS
%attr(0644,root,root) %{zabbix_docdir}/README
%attr(0644,root,root) %{zabbix_docdir}/ChangeLog

%dir %attr(0755,root,root) %{zabbix_confdir}
%attr(0644,root,root) %config(noreplace) %{zabbix_confdir}/zabbix_agentd.conf

%dir %attr(0755,root,root) %{zabbix_bindir}
%attr(0755,root,root) %{zabbix_bindir}/zabbix_agentd

%dir %attr(0755,root,root) %{zabbix_initdir}
%attr(0755,root,root) %{zabbix_initdir}/zabbix_agentd

%changelog
* Wed Jul 19 2006 Alexey Zilber <AlexeyZilber@gmail.com>
- 1.1.1
- Updated packaging, cleaned up directory structure for
- RedHat compatibility, added uninstall,build checks.
* Thu Dec 01 2005 Eugene Grigorjev <eugene.grigorjev@zabbix.com>
- 1.1beta2
- initial packaging

