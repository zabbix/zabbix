/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package dbconn

import (
	"testing"

	"golang.zabbix.com/agent2/plugins/oracle/mock"
	"golang.zabbix.com/sdk/uri"
)

var (
	//testConfig contains connection properties to mocked Oracle environment.
	testConfig = mock.TestConfig{ //nolint:gochecknoglobals
		OraURI:  "localhost",
		OraUser: "ZABBIX_MON",
		OraPwd:  "zabbix",
		OraSrv:  "XE",
	}
)

func newConnDet(t *testing.T, username, privilege string) ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds( //nolint:errcheck
		testConfig.OraURI+"?service="+testConfig.OraSrv,
		username,
		"zabbix",
		URIDefaults)

	return ConnDetails{*u, privilege, false}
}

func newConnDetHostname(t *testing.T, hostname, service string) *ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds( //nolint:errcheck
		hostname+"?service="+service,
		"any_username",
		"any_password",
		nil)

	return &ConnDetails{*u, "", false}
}
