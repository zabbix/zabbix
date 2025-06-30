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

	zbxlog "golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

var (
	_ zbxlog.Logger = (*mockLogger)(nil)

	config = TestConfig{ //nolint:gochecknoglobals
		OraURI:  "localhost",
		OraUser: "ZABBIX_MON",
		OraPwd:  "zabbix",
		OraSrv:  "XE",
	}
)

// TestConfig type contains a test Oracle server connection credentials.
type TestConfig struct {
	OraURI  string
	OraUser string
	OraPwd  string
	OraSrv  string
}

// mockLogger type is empty zbxlog.Logger implementation for unittests.
type mockLogger struct {
}

// Infof empty mock function.
func (ml *mockLogger) Infof(_ string, _ ...any) {} //nolint:revive

// Critf empty mock function.
func (ml *mockLogger) Critf(_ string, _ ...any) {} //nolint:revive

// Errf empty mock function.
func (ml *mockLogger) Errf(_ string, _ ...any) {} //nolint:revive

// Warningf mock function.
func (ml *mockLogger) Warningf(_ string, _ ...any) {} //nolint:revive

// Debugf mock function.
func (ml *mockLogger) Debugf(_ string, _ ...any) {} //nolint:revive

// Tracef empty mock function.
func (ml *mockLogger) Tracef(_ string, _ ...any) {} //nolint:revive

// CloseRows function closes rows if exits. In case of problem - returns an error to subtest.
func CloseRows(t *testing.T, rows *sql.Rows) {
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
		config.OraURI+"?service="+config.OraSrv,
		username,
		"zabbix",
		URIDefaults)

	return ConnDetails{Uri: *u, Privilege: privilege}
}

// newConnDet function the same as newConnDet, except NoVersionCheck makes true to switch
// off server version check request at the connection creation to avoid real connection to
// the server.
func newConnDetNoCheck(t *testing.T, username, privilege string) ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds( //nolint:errcheck
		config.OraURI+"?service="+config.OraSrv,
		username,
		"zabbix",
		URIDefaults)

	return ConnDetails{*u, privilege, true}
}
