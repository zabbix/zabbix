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
	"database/sql"
	"testing"

	"golang.zabbix.com/agent2/plugins/oracle/mock"
	"golang.zabbix.com/sdk/uri"
)

var (
	testConfig = mock.TestConfig{ //nolint:gochecknoglobals
		OraURI:  "localhost",
		OraUser: "ZABBIX_MON",
		OraPwd:  "zabbix",
		OraSrv:  "XE",
	}
)

// closeRows function closes rows if exits. In case of problem - returns an error to subtest.
func closeRows(t *testing.T, rows *sql.Rows) { //nolint:unused
	t.Helper()

	if rows != nil {
		err := rows.Close()
		if err != nil {
			t.Errorf("rows.Close() error: %v", err)
		}
	}
}

// newConnDet function constructs ConnDetails with default values for testing.
func newConnDet(t *testing.T, username, privilege string) ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds( //nolint:errcheck
		testConfig.OraURI+"?service="+testConfig.OraSrv,
		username,
		"zabbix",
		URIDefaults)

	return ConnDetails{*u, privilege, false}
}

// newConnDetNoVersCheck function the same as newConnDet, except NoVersionCheck makes true to switch
// off server version check request at the connection creation to avoid real connection to
// the server.
func newConnDetNoVersCheck(t *testing.T, username, privilege string) ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds( //nolint:errcheck
		testConfig.OraURI+"?service="+testConfig.OraSrv,
		username,
		"zabbix",
		URIDefaults)

	return ConnDetails{*u, privilege, true}
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
