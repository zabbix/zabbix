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
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/uri"
)

func TestOraConn_WhoAmI(t *testing.T) {
	t.Parallel()

	type args = struct {
		username string
	}

	tests := []struct {
		name string
		args args
	}{
		{
			"+specified",
			args{"myusername"},
		},
		{
			"+empty",
			args{""},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotCon := OraConn{username: tt.args.username}

			if diff := cmp.Diff(tt.args.username, gotCon.WhoAmI()); diff != "" {
				t.Errorf("OraConn.WhoAmI(): %s", diff)
			}
		})
	}
}

func TestOraConn_updateLastAccessTime(t *testing.T) {
	t.Parallel()

	type args = struct {
		accessTime time.Time
	}

	tests := []struct {
		name string
		args args
	}{
		{
			"+specified",
			args{time.Now()},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotCon := OraConn{}
			gotCon.updateLastAccessTime(tt.args.accessTime)

			if diff := cmp.Diff(tt.args.accessTime, gotCon.lastAccessTime); diff != "" {
				t.Errorf("OraConn.updateLastAccessTime(): %s", diff)
			}
		})
	}
}

func TestOraConn_getLastAccessTime(t *testing.T) {
	t.Parallel()

	type args = struct {
		accessTime time.Time
	}

	tests := []struct {
		name string
		args args
	}{
		{
			"+specified",
			args{time.Now()},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotCon := OraConn{lastAccessTime: tt.args.accessTime}

			if diff := cmp.Diff(tt.args.accessTime, gotCon.getLastAccessTime()); diff != "" {
				t.Errorf("OraConn.updateLastAccessTime(): %s", diff)
			}
		})
	}
}

func Test_getTNSType(t *testing.T) {
	t.Parallel()

	type args = struct {
		host         string
		onlyHostname bool

		resolveTNS bool
	}

	type want struct {
		result TNSNameType
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+tnsValue",
			args{"(DESCR...)", true, true}, //the last two can be any
			want{tnsValue},
		},
		{
			"+tnsValueOptionalParam",
			args{"(DESCR...)", false, false},
			want{tnsValue},
		},
		{
			"+tnsKey",
			args{"no_tns_value", false, true},
			want{tnsKey},
		},
		{
			"+tnsNoneOnlyHostnameTrue",
			args{"no_tns_value", true, true},
			want{tnsNone},
		},
		{
			"+tnsNoneOnlyHostnameFalse",
			args{"no_tns_value", false, false},
			want{tnsNone},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotResult := getTNSType(tt.args.host, tt.args.onlyHostname, tt.args.resolveTNS)

			if diff := cmp.Diff(tt.want.result, gotResult); diff != "" {
				t.Errorf("OraConn.updateLastAccessTime(): %s", diff)
			}
		})
	}
}

func Test_prepareConnectString(t *testing.T) {
	t.Parallel()

	type args = struct {
		tnsType        TNSNameType
		cd             *ConnDetails
		connectTimeout time.Duration
	}

	type want struct {
		result    string
		wantPanic bool
		wantErr   bool
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+tnsKey",
			args{
				tnsKey,
				newConnDetHostname(t, "zbx_tns", "XE"), //hostname any
				1,                                      //any
			},
			want{"zbx_tns", false, false},
		},
		{
			"+tnsKey",
			args{
				tnsValue,
				newConnDetHostname(t, "(DESCRIPTION=..", "XE"),
				1, //any
			},
			want{"(DESCRIPTION=..", false, false},
		},
		{
			"+tnsNone",
			args{
				tnsNone,
				newConnDetHostname(t, "tcp://myhost:1521", "XE"),
				1000000000, //any
			},
			want{
				`(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=myhost)(PORT=1521))` +
					`(CONNECT_DATA=(SERVICE_NAME="XE"))(CONNECT_TIMEOUT=1)(RETRY_COUNT=0))`,
				false,
				false,
			},
		},
		{
			"+tnsEmptyPortService",
			args{
				tnsNone,
				newConnDetHostname(t, "localhost", ""), //hostname any
				1,
			},
			want{
				`(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=localhost)(PORT=))` +
					`(CONNECT_DATA=(SERVICE_NAME=""))(CONNECT_TIMEOUT=0)(RETRY_COUNT=0))`,
				false,
				false,
			},
		},
		{
			"+tnsServiceDecodeOk",
			args{
				tnsNone,
				newConnDetHostname(t, "localhost", "XE%23"),
				1,
			},
			want{
				`(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=localhost)(PORT=))` +
					`(CONNECT_DATA=(SERVICE_NAME="XE#"))(CONNECT_TIMEOUT=0)(RETRY_COUNT=0))`,
				false,
				false,
			},
		},
		{
			"-tnsServiceDecodeFail",
			args{
				tnsNone,
				newConnDetHostname(t, "localhost", "XE%ZZ"),
				1, //any
			},
			want{
				"",
				false,
				true,
			},
		},
		{
			"-unknownTNSType",
			args{
				TNSNameType(100),
				newConnDetHostname(t, "any", "any"),
				1, //any
			},
			want{
				"",
				true,
				true,
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			defer func() {
				r := recover()
				if tt.want.wantPanic && r == nil {
					t.Fatalf("prepareConnectString() expected panic did not occur")
				}

				if !tt.want.wantPanic && r != nil {
					t.Fatalf("prepareConnectString() unexpected panic occurred")
				}
			}()

			gotResult, err := prepareConnectString(tt.args.tnsType, tt.args.cd, tt.args.connectTimeout)
			if err != nil && !tt.want.wantErr {
				t.Fatalf("prepareConnectString() unwanted error = %v", err)
			}

			if diff := cmp.Diff(tt.want.result, gotResult); diff != "" {
				t.Errorf("prepareConnectString(): %s", diff)
			}
		})
	}
}

func newConnDetHostname(t *testing.T, hostname, service string) *ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds(
		hostname+"?service="+service,
		"any_username",
		"any_password",
		nil)

	return &ConnDetails{*u, "", false}
}
