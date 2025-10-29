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

package systemrun

import (
	"log"
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/errs"
	zbxlog "golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

// both mocks should be moved to their correct packages in sdk, with normal mock setup and expectations.

var _ zbxlog.Logger = (*mockLogger)(nil)
var _ plugin.ContextProvider = (*contextMock)(nil)

type mockLogger struct {
	warningCount int
	debugCount   int
}

type contextMock struct {
	clientID uint64
}

// Infof empty mock function.
func (ml *mockLogger) Infof(_ string, _ ...any) {}

// Critf empty mock function.
func (ml *mockLogger) Critf(_ string, _ ...any) {}

// Errf empty mock function.
func (ml *mockLogger) Errf(_ string, _ ...any) {}

// Warningf mock function.
func (ml *mockLogger) Warningf(_ string, _ ...any) {
	ml.warningCount++
}

// Debugf mock function.
func (ml *mockLogger) Debugf(_ string, _ ...any) {
	ml.debugCount++
}

// Tracef empty mock function.
func (ml *mockLogger) Tracef(_ string, _ ...any) {}

// ClientID mock function.
func (cm *contextMock) ClientID() uint64 {
	return cm.clientID
}

// ItemID empty mock function.
func (cm *contextMock) ItemID() uint64 { return 0 }

// Output empty mock function.
//
//nolint:ireturn
func (cm *contextMock) Output() plugin.ResultWriter {
	return nil
}

// Meta empty mock function.
//
//nolint:ireturn,nolintlint
func (cm *contextMock) Meta() *plugin.Meta {
	return nil
}

// GlobalRegexp empty mock function.
//
//nolint:ireturn
func (cm *contextMock) GlobalRegexp() plugin.RegexpMatcher {
	return nil
}

// Timeout empty mock function.
func (cm *contextMock) Timeout() int { return 0 }

// Delay empty mock function.
func (cm *contextMock) Delay() string { return "" }

func TestPlugin_Configure(t *testing.T) {
	t.Parallel()

	type args struct {
		options any
	}

	tests := []struct {
		name                string
		p                   *Plugin
		args                args
		want                Options
		wantWarningLogCount int
	}{
		{
			"+valid",
			&Plugin{},
			args{
				[]byte(`LogRemoteCommands=0`),
			},
			Options{LogRemoteCommands: 0},
			0,
		},
		{
			"+remoteCommandSet",
			&Plugin{},
			args{
				[]byte(`LogRemoteCommands=1`),
			},
			Options{LogRemoteCommands: 1},
			0,
		},
		{
			"-remoteCommandInvalid",
			&Plugin{},
			args{
				[]byte(`LogRemoteCommands=14`),
			},
			Options{LogRemoteCommands: 0},
			1,
		},
		{
			"-unknownOptionField",
			&Plugin{},
			args{
				[]byte(`Foobar=14`),
			},
			Options{LogRemoteCommands: 0},
			1,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			ml := &mockLogger{}

			tt.p.Base.Logger = ml

			tt.p.Configure(nil, tt.args.options)

			if diff := cmp.Diff(tt.want, tt.p.options); diff != "" {
				t.Fatalf("Plugin.Configure options() = %s", diff)
			}

			if ml.warningCount != tt.wantWarningLogCount {
				t.Fatalf(
					"Plugin.Configure() warning log called times= %v, wantWarningLog %v",
					ml.warningCount,
					tt.wantWarningLogCount,
				)
			}
		})
	}
}

func TestPlugin_Validate(t *testing.T) {
	t.Parallel()

	type args struct {
		options interface{}
	}

	tests := []struct {
		name    string
		p       *Plugin
		args    args
		wantErr bool
	}{
		{
			"+valid",
			&Plugin{},
			args{
				[]byte(`LogRemoteCommands=1`),
			},
			false,
		},
		{
			"-remoteCommandInvalid",
			&Plugin{},
			args{
				[]byte(`LogRemoteCommands=12`),
			},
			true,
		},
		{
			"-invalidConfig",
			&Plugin{},
			args{
				[]byte(`foobar`),
			},
			true,
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			if err := tt.p.Validate(tt.args.options); (err != nil) != tt.wantErr {
				t.Fatalf("Plugin.Validate() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestPlugin_Export(t *testing.T) {
	t.Parallel()

	log.Default()

	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	tests := []struct {
		name                string
		p                   *Plugin
		args                args
		want                any
		wantWarningLogCount int
		wantDebugLogCount   int
		wantErr             bool
	}{
		{
			"+valid",
			&Plugin{
				executor: &zbxcmd.ZBXExecMock{Success: true},
			},
			args{
				params: []string{"cmd"},
				ctx:    &contextMock{},
			},
			"success",
			0,
			2,
			false,
		},
		{
			"+logRemoteCmd",
			&Plugin{
				executor: &zbxcmd.ZBXExecMock{Success: true},
				options:  Options{LogRemoteCommands: 1},
			},
			args{
				params: []string{"cmd"},
				ctx:    &contextMock{clientID: 12},
			},
			"success",
			1,
			1,
			false,
		},
		{
			"+logRemoteCmdWithNoClientID",
			&Plugin{
				executor: &zbxcmd.ZBXExecMock{Success: true},
				options:  Options{LogRemoteCommands: 1},
			},
			args{
				params: []string{"cmd"},
				ctx:    &contextMock{},
			},
			"success",
			0,
			2,
			false,
		},
		{
			"+executorInit",
			&Plugin{
				executorInitFunc: func() (zbxcmd.Executor, error) {
					return &zbxcmd.ZBXExecMock{Success: true}, nil
				},
			},
			args{
				params: []string{"cmd"},
				ctx:    &contextMock{},
			},
			"success",
			0,
			2,
			false,
		},
		{
			"+nowait",
			&Plugin{
				executorInitFunc: func() (zbxcmd.Executor, error) {
					return &zbxcmd.ZBXExecMock{Success: true}, nil
				},
			},
			args{
				params: []string{"cmd", "nowait"},
				ctx:    &contextMock{},
			},
			1,
			0,
			1,
			false,
		},
		{
			"-tooManyParameters",
			&Plugin{
				executorInitFunc: func() (zbxcmd.Executor, error) {
					return &zbxcmd.ZBXExecMock{Success: true}, nil
				},
			},
			args{
				params: []string{"cmd", "nowait", "foobar"},
				ctx:    &contextMock{},
			},
			nil,
			0,
			0,
			true,
		},
		{
			"-execInitError",
			&Plugin{
				executorInitFunc: func() (zbxcmd.Executor, error) {
					return nil, errs.New("fail")
				},
			},
			args{
				params: []string{"cmd", "nowait"},
				ctx:    &contextMock{},
			},
			nil,
			0,
			1,
			true,
		},
		{
			"-waitExecErr",
			&Plugin{
				executorInitFunc: func() (zbxcmd.Executor, error) {
					return &zbxcmd.ZBXExecMock{Success: false}, nil
				},
			},
			args{
				params: []string{"cmd"},
				ctx:    &contextMock{},
			},
			nil,
			0,
			1,
			true,
		},
		{
			"-nowaitExecErr",
			&Plugin{
				executorInitFunc: func() (zbxcmd.Executor, error) {
					return &zbxcmd.ZBXExecMock{Success: false}, nil
				},
			},
			args{
				params: []string{"cmd", "nowait"},
				ctx:    &contextMock{},
			},
			nil,
			0,
			1,
			true,
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			ml := &mockLogger{}

			tt.p.Base.Logger = ml

			got, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
			}

			if ml.debugCount != tt.wantDebugLogCount {
				t.Fatalf(
					"Plugin.Export() debug log called = %v, wantDebugLog %v",
					ml.debugCount,
					tt.wantDebugLogCount,
				)
			}

			if ml.warningCount != tt.wantWarningLogCount {
				t.Fatalf(
					"Plugin.Export() warning log called = %v, wantWarningLog %v",
					ml.warningCount,
					tt.wantWarningLogCount,
				)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("Plugin.Export() = %s", diff)
			}
		})
	}
}

func Test_parseParameters(t *testing.T) {
	t.Parallel()

	type args struct {
		params []string
	}

	tests := []struct {
		name     string
		args     args
		wantCmd  string
		wantWait bool
		wantErr  bool
	}{
		{
			"+onlyFirst",
			args{
				params: []string{"cmd"},
			},
			"cmd",
			true,
			false,
		},
		{
			"+secondEmpty",
			args{
				params: []string{"cmd", ""},
			},
			"cmd",
			true,
			false,
		},
		{
			"+wait",
			args{
				params: []string{"cmd", "wait"},
			},
			"cmd",
			true,
			false,
		},
		{
			"+noWait",
			args{
				params: []string{"cmd", "nowait"},
			},
			"cmd",
			false,
			false,
		},
		{
			"-firstNotSet",
			args{
				params: []string{},
			},
			"",
			false,
			true,
		},
		{
			"-firstEmpty",
			args{
				params: []string{""},
			},
			"",
			false,
			true,
		},
		{
			"-invalidSecondParameter",
			args{
				params: []string{"cmd", "foobar"},
			},
			"",
			false,
			true,
		},
		{
			"-tooManyParameters",
			args{
				params: []string{"cmd", "foo", "bar"},
			},
			"",
			false,
			true,
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, got1, err := parseParameters(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Fatalf("parseParameters() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.wantCmd, got); diff != "" {
				t.Fatalf("parseParameters() got cmd= %s", diff)
			}

			if diff := cmp.Diff(tt.wantWait, got1); diff != "" {
				t.Fatalf("parseParameters() got wait = %s", diff)
			}
		})
	}
}
