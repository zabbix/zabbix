%define debug_package %{nil}

%define _prefix		/usr/local/zabbix

Name:		zabbix-frontend
Version:	1.1beta2
Release:	1
Group:		System Environment/Daemons
License:	GPL
Summary:	ZABBIX network monitor frontend
Vendor:		ZABBIX SIA
URL:		http://www.zabbix.org
Packager:	Eugene Grigorjev <eugene.grigorjev@zabbix.com>
Source:		zabbix-1.1beta2.tar.gz

Autoreq:	no
Requires:	php
Buildroot: 	%{_tmppath}/%{name}-%{version}-%{release}-buildroot

#Prefix:		%{_prefix}

%define zabbix_wwwdir	%{_prefix}/www
%define zabbix_docdir	%{_prefix}/doc

%description
The frontend for ZABBIX network monitor.

%prep
%setup -n zabbix-1.1beta2

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

# copy frontend files
install -d %{buildroot}%{zabbix_wwwdir}
cp -r frontends/php/* %{buildroot}%{zabbix_wwwdir}

%post

# configure ZABBIX server daemon
TMP_FILE=`mktemp $TMPDIR/zbxtmpXXXXXX`

sed	-e "s#\$DB_TYPE =\"POSTGRESQL\";#\$DB_TYPE =\"MYSQL\";#g" \
	%{zabbix_wwwdir}/include/db.inc.php > $TMP_FILE
cat $TMP_FILE > %{zabbix_wwwdir}/include/db.inc.php

rm -f $TMP_FILE

%files

%defattr(-,root,root)

%dir %attr(0755,root,root) %{zabbix_docdir}
%attr(0644,root,root) %{zabbix_docdir}/AUTHORS
%attr(0644,root,root) %{zabbix_docdir}/COPYING
%attr(0644,root,root) %{zabbix_docdir}/NEWS
%attr(0644,root,root) %{zabbix_docdir}/README

%attr(0755,root,root) %{zabbix_wwwdir}

%changelog
* Thu Dec 01 2005 Eugene Grigorjev <eugene.grigorjev@zabbix.com>
- 1.1beta2
- initial packaging

