/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package smart

import (
	"errors"
	stdlog "log"
	"os"
	"testing"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin"
	"github.com/google/go-cmp/cmp"
	"zabbix.com/plugins/smart/mock"
)

func Test_runner_executeBase(t *testing.T) {
	log.DefaultLogger = stdlog.New(os.Stdout, "", stdlog.LstdFlags)

	type expectation struct {
		args []string
		err  error
		out  []byte
	}

	type fields struct {
		plugin      *Plugin
		devices     map[string]deviceParser
		jsonDevices map[string]jsonDevice
	}

	type args struct {
		basicDev   []deviceInfo
		jsonRunner bool
	}

	tests := []struct {
		name            string
		expectations    []expectation
		fields          fields
		args            args
		wantJsonDevices map[string]jsonDevice
		wantDevices     map[string]deviceParser
		wantErr         bool
	}{
		{
			"+valid",
			[]expectation{
				{
					args: []string{"-a", "/dev/csmi0", "-j"},
					err:  nil,
					out:  mock.OutputAllDiscInfoCSMI,
				},
			},
			fields{
				jsonDevices: map[string]jsonDevice{},
				devices:     map[string]deviceParser{},
			},
			args{
				[]deviceInfo{
					{
						Name:     "/dev/csmi0",
						InfoName: "/dev/csmi0",
						DevType:  "ata",
					},
				},
				false,
			},
			map[string]jsonDevice{},
			map[string]deviceParser{},
			false,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			cpuCount = 1

			m := &mock.MockController{}

			for _, e := range tt.expectations {
				m.ExpectExecute().
					WithArgs(e.args...).
					WillReturnOutput(e.out).
					WillReturnError(e.err)
			}

			log.New("test")

			r := &runner{
				plugin: &Plugin{
					ctl:  m,
					Base: plugin.Base{Logger: log.New("test")},
				},
				names:       make(chan string, 10),
				err:         make(chan error, 10),
				done:        make(chan struct{}),
				devices:     tt.fields.devices,
				jsonDevices: tt.fields.jsonDevices,
			}

			defer func() {
				close(r.err)
			}()

			err := r.executeBase(tt.args.basicDev, tt.args.jsonRunner)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"runner.executeBase() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}
			if diff := cmp.Diff(tt.wantJsonDevices, r.jsonDevices); diff != "" {
				t.Fatalf(
					"runner.executeBase() jsonDevices mismatch (-want +got):\n%s",
					diff,
				)
			}
			if diff := cmp.Diff(tt.wantDevices, r.devices); diff != "" {
				t.Fatalf(
					"runner.executeBase() devices mismatch (-want +got):\n%s",
					diff,
				)
			}
			if err := m.ExpectationsWhereMet(); err != nil {
				t.Fatalf(
					"runner.executeBase() expectations where not met, error = %v",
					err,
				)
			}
		})
	}
}

func Test_evaluateVersion(t *testing.T) {
	type args struct {
		versionDigits []int
	}
	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{"+correct_version", args{[]int{7, 1}}, false},
		{"+correct_version_one_digit", args{[]int{8}}, false},
		{"+correct_version_multiple_digits", args{[]int{7, 1, 2}}, false},
		{"-incorrect_version", args{[]int{7, 0}}, true},
		{"-malformed_version", args{[]int{-7, 0}}, true},
		{"-empty", args{}, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if err := evaluateVersion(tt.args.versionDigits); (err != nil) != tt.wantErr {
				t.Errorf(
					"evaluateVersion() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}
		})
	}
}

func Test_cutPrefix(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"+has_prefix", args{"/dev/sda"}, "sda"},
		{"-no_prefix", args{"sda"}, "sda"},
		{"-empty", args{""}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := cutPrefix(tt.args.in); got != tt.want {
				t.Errorf("cutPrefix() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_deviceParser_checkErr(t *testing.T) {
	type fields struct{ Smartctl smartctlField }

	tests := []struct {
		name    string
		fields  fields
		wantErr bool
		wantMsg string
	}{
		{
			"+no_err",
			fields{smartctlField{Messages: nil, ExitStatus: 0}},
			false,
			"",
		},
		{
			"+no_err",
			fields{smartctlField{Messages: nil, ExitStatus: 4}},
			false,
			"",
		},
		{
			"+warning",
			fields{
				smartctlField{Messages: []message{{"barfoo"}}, ExitStatus: 3},
			},
			true,
			"barfoo",
		},
		{
			"-error_status_one",
			fields{
				smartctlField{Messages: []message{{"barfoo"}}, ExitStatus: 1},
			},
			true,
			"barfoo",
		},
		{
			"-error_status_two",
			fields{
				smartctlField{Messages: []message{{"foobar"}}, ExitStatus: 2},
			},
			true,
			"foobar",
		},
		{
			"-two_err",
			fields{
				smartctlField{
					Messages:   []message{{"foobar"}, {"barfoo"}},
					ExitStatus: 2,
				},
			},
			true,
			"foobar, barfoo",
		},
		{
			"-unknown_err/no message",
			fields{smartctlField{Messages: []message{}, ExitStatus: 2}},
			true,
			"unknown error from smartctl",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			dp := deviceParser{Smartctl: tt.fields.Smartctl}
			err := dp.checkErr()
			if (err != nil) != tt.wantErr {
				t.Errorf(
					"deviceParser.checkErr() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}

			if (err != nil) && err.Error() != tt.wantMsg {
				t.Errorf(
					"deviceParser.checkErr() error message = %v, want %v",
					err,
					tt.wantErr,
				)
			}
		})
	}
}

func TestPlugin_checkVersion(t *testing.T) {
	type expect struct {
		exec bool
	}

	type fields struct {
		execErr      error
		execOut      []byte
		lastVerCheck time.Time
	}

	tests := []struct {
		name    string
		expect  expect
		fields  fields
		wantErr bool
	}{
		{
			"+valid",
			expect{true},
			fields{execOut: mock.OutputVersionValid},
			false,
		},
		{
			"-noCheck",
			expect{false},
			fields{
				lastVerCheck: time.Now(),
			},
			false,
		},
		{
			"-executeErr",
			expect{true},
			fields{
				execOut: mock.OutputVersionValid,
				execErr: errors.New("fail"),
			},
			true,
		},
		{
			"-unmarshalErr",
			expect{true},
			fields{execOut: []byte("{")},
			true,
		},
		{
			"-evaluateVersionErr",
			expect{true},
			fields{execOut: mock.OutputVersionInvalid},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			lastVerCheck = tt.fields.lastVerCheck

			m := &mock.MockController{}

			if tt.expect.exec {
				m.ExpectExecute().
					WithArgs("-j", "-V").
					WillReturnOutput(tt.fields.execOut).
					WillReturnError(tt.fields.execErr)
			}

			p := &Plugin{ctl: m}

			if err := p.checkVersion(); (err != nil) != tt.wantErr {
				t.Fatalf(
					"Plugin.checkVersion() error = %v, wantErr %v",
					err, tt.wantErr,
				)
			}
			if err := m.ExpectationsWhereMet(); err != nil {
				t.Fatalf(
					"Plugin.checkVersion() expectations where not met, error = %v",
					err,
				)
			}
		})
	}
}

func TestPlugin_getDevices(t *testing.T) {
	t.Parallel()

	type expect struct {
		raidScanExec bool
	}

	type fields struct {
		basicScanOut []byte
		basicScanErr error

		raidScanOut []byte
		raidScanErr error
	}

	tests := []struct {
		name         string
		expect       expect
		fields       fields
		wantBasic    []deviceInfo
		wantRaid     []deviceInfo
		wantMegaraid []deviceInfo
		wantErr      bool
	}{
		// TODO: need to get smartctl --scann -d sat -j  outputs of megaraid
		// devices.
		{
			"+valid",
			expect{true},
			fields{
				basicScanOut: mock.OutputScan,
				raidScanOut:  mock.OutputScanTypeSAT,
			},
			[]deviceInfo{
				{
					Name:     "/dev/csmi0,0",
					InfoName: "/dev/csmi0,0",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,2",
					InfoName: "/dev/csmi0,2",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,3",
					InfoName: "/dev/csmi0,3",
					DevType:  "ata",
				},
			},
			[]deviceInfo{
				{
					Name:     "/dev/sda",
					InfoName: "/dev/sda [SAT]",
					DevType:  "sat",
				},
			},
			nil,
			false,
		},
		{
			"-basicScanErr",
			expect{false},
			fields{
				basicScanOut: mock.OutputScan,
				basicScanErr: errors.New("fail"),
			},
			nil,
			nil,
			nil,
			true,
		},
		{
			"-raidScanErr",
			expect{true},
			fields{
				basicScanOut: mock.OutputScan,
				raidScanOut:  mock.OutputScanTypeSAT,
				raidScanErr:  errors.New("fail"),
			},
			nil,
			nil,
			nil,
			true,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			m := &mock.MockController{}

			m.ExpectExecute().
				WithArgs("--scan", "-j").
				WillReturnOutput(tt.fields.basicScanOut).
				WillReturnError(tt.fields.basicScanErr)

			if tt.expect.raidScanExec {
				m.ExpectExecute().
					WithArgs("--scan", "-d", "sat", "-j").
					WillReturnOutput(tt.fields.raidScanOut).
					WillReturnError(tt.fields.raidScanErr)
			}

			p := &Plugin{ctl: m}

			gotBasic, gotRaid, gotMegaraid, err := p.getDevices()
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"Plugin.getDevices() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}
			if diff := cmp.Diff(
				tt.wantBasic, gotBasic,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("Plugin.getDevices() gotBasic = %s", diff)
			}
			if diff := cmp.Diff(
				tt.wantRaid, gotRaid,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("Plugin.getDevices() gotRaid = %s", diff)
			}
			if diff := cmp.Diff(
				tt.wantMegaraid, gotMegaraid,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("Plugin.getDevices() gotMegaraid = %s", diff)
			}
			if err := m.ExpectationsWhereMet(); err != nil {
				t.Fatalf(
					"Plugin.getDevices() expectations where not met, error = %v",
					err,
				)
			}
		})
	}
}

func Test_formatDeviceOutput(t *testing.T) {
	t.Parallel()

	sampleBasicDev1 := deviceInfo{
		Name:     "/dev/csmi0,0",
		InfoName: "/dev/csmi0,0",
		DevType:  "ata",
	}

	sampleBasicDev2 := deviceInfo{
		Name:     "/dev/csmi0,2",
		InfoName: "/dev/csmi0,2",
		DevType:  "ata",
	}

	sampleRaidDev1 := deviceInfo{
		Name:     "/dev/sda",
		InfoName: "/dev/sda [SAT]",
		DevType:  "sat",
	}

	sampleRaidDev2 := deviceInfo{
		Name:     "/dev/sdb",
		InfoName: "/dev/sdb [SAT]",
		DevType:  "sat",
	}

	sampleMegaraidDev1 := deviceInfo{
		Name:     "frogs_hallucination",
		InfoName: "frogs_hallucination",
		DevType:  "megaraid",
	}

	sampleMegaraidDev2 := deviceInfo{
		Name:     "cows_imagination",
		InfoName: "cows_imagination",
		DevType:  "megaraid",
	}

	type args struct {
		basic []deviceInfo
		raid  []deviceInfo
	}

	tests := []struct {
		name            string
		args            args
		wantBasicDev    []deviceInfo
		wantRaidDev     []deviceInfo
		wantMegaraidDev []deviceInfo
	}{
		{
			"+valid",
			args{
				[]deviceInfo{sampleBasicDev1, sampleBasicDev2},
				[]deviceInfo{
					sampleRaidDev1, sampleRaidDev2,
					sampleMegaraidDev1, sampleMegaraidDev2,
				},
			},
			[]deviceInfo{sampleBasicDev1, sampleBasicDev2},
			[]deviceInfo{sampleRaidDev1, sampleRaidDev2},
			[]deviceInfo{sampleMegaraidDev1, sampleMegaraidDev2},
		},
		{
			"+megaraidDevices",
			args{
				[]deviceInfo{},
				[]deviceInfo{
					sampleRaidDev1, sampleRaidDev2,
					sampleMegaraidDev1, sampleMegaraidDev2,
				},
			},
			nil,
			[]deviceInfo{sampleRaidDev1, sampleRaidDev2},
			[]deviceInfo{sampleMegaraidDev1, sampleMegaraidDev2},
		},
		{
			"-duplicateDevInRaidAndBasic",
			args{
				[]deviceInfo{sampleBasicDev1, sampleRaidDev1},
				[]deviceInfo{sampleRaidDev1, sampleRaidDev2},
			},
			[]deviceInfo{sampleBasicDev1},
			[]deviceInfo{sampleRaidDev1, sampleRaidDev2},
			nil,
		},
		{
			"-noDevices",
			args{[]deviceInfo{}, []deviceInfo{}},
			nil,
			nil,
			nil,
		},
		{
			"-nilDevices",
			args{nil, nil},
			nil,
			nil,
			nil,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotBasicDev, gotRaidDev, gotMegaraidDev := formatDeviceOutput(
				tt.args.basic, tt.args.raid,
			)

			if diff := cmp.Diff(
				tt.wantBasicDev, gotBasicDev,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("formatDeviceOutput() gotBasicDev = %s", diff)
			}

			if diff := cmp.Diff(
				tt.wantRaidDev, gotRaidDev,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("formatDeviceOutput() gotRaidDev = %s", diff)
			}

			if diff := cmp.Diff(
				tt.wantMegaraidDev, gotMegaraidDev,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("formatDeviceOutput() gotMegaraidDev = %s", diff)
			}
		})
	}
}

func TestPlugin_scanDevices(t *testing.T) {
	t.Parallel()

	type fields struct {
		execErr error
		execOut []byte
	}

	type args struct {
		args []string
	}

	tests := []struct {
		name    string
		fields  fields
		args    args
		want    []deviceInfo
		wantErr bool
	}{
		{
			"+valid",
			fields{execOut: mock.OutputScan},
			args{[]string{"--scan", "-j"}},
			[]deviceInfo{
				{
					Name:     "/dev/csmi0,0",
					InfoName: "/dev/csmi0,0",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,2",
					InfoName: "/dev/csmi0,2",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,3",
					InfoName: "/dev/csmi0,3",
					DevType:  "ata",
				},
			},
			false,
		},
		{
			"-execErr",
			fields{execOut: mock.OutputScan, execErr: errors.New("fail")},
			args{[]string{"--scan", "-j"}},
			nil,
			true,
		},
		{
			"-marshalErr",
			fields{execOut: []byte("{")},
			args{[]string{"--scan", "-j"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			m := &mock.MockController{}

			m.ExpectExecute().
				WithArgs(tt.args.args...).
				WillReturnOutput(tt.fields.execOut).
				WillReturnError(tt.fields.execErr)

			p := &Plugin{ctl: m}

			got, err := p.scanDevices(tt.args.args...)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"Plugin.scanDevices() error = %v, wantErr %v",
					err, tt.wantErr,
				)
			}
			if diff := cmp.Diff(
				tt.want, got, cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("Plugin.scanDevices() = %s", diff)
			}
			if err := m.ExpectationsWhereMet(); err != nil {
				t.Fatalf(
					"Plugin.scanDevices() expectations where not met, error = %v",
					err,
				)
			}
		})
	}
}
