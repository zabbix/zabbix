/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package metric

import (
	"reflect"
	"testing"

	"zabbix.com/pkg/conf"
)

var (
	paramURI              = NewConnParam("URI", "Description.").WithDefault("localhost:1521").WithSession()
	paramUsername         = NewConnParam("User", "Description.").WithDefault("")
	paramRequiredUsername = NewConnParam("User", "Description.").SetRequired()
	paramUserValidation   = NewConnParam("User", "Description.").WithDefault("").WithValidator(
		SetValidator{Set: []string{"", "supervisor", "admin", "guest"}})
	paramPassword               = NewConnParam("Password", "Description.").WithDefault("")
	paramPasswordWithSetDefault = NewConnParam("Password", "Description.").WithDefault("Password")
	paramGeneral                = NewParam("GeneralParam", "Description.")
)

var metricSet = MetricSet{
	"metric.foo": New("Foo description.",
		[]*Param{paramURI, paramUsername, paramPassword,
			NewParam("Param1", "Description.").WithDefault("60").WithValidator(SetValidator{Set: []string{"15", "60"}}),
		}, false),
	"metric.bar": New("bar description.",
		[]*Param{paramURI, paramUsername, NewSessionOnlyParam("Password", "Description.")}, true),
	"metric.bar.strict": New("bar description.",
		[]*Param{paramURI, paramUsername, paramPassword,
			NewSessionOnlyParam("Param1", "Description.").SetRequired(),
		}, false),
	"metric.query": New("Query description.",
		[]*Param{paramURI, paramUsername, paramPassword,
			NewParam("QueryName", "Description.").SetRequired(),
		}, true),
	"metric.requiredSessionParam": New("RequiredSessionParam description.",
		[]*Param{paramURI, paramRequiredUsername, paramPassword}, false),
	"metric.withoutPassword": New("WithoutPassword description.",
		[]*Param{paramURI, paramUsername}, false),
	"metric.userValidation": New("UserValidation description.",
		[]*Param{paramURI, paramUserValidation, paramPassword}, false),
	"metric.conn": New("Conn description.", []*Param{paramURI, paramUsername, paramPasswordWithSetDefault}, false),
}

func TestMetric_EvalParams(t *testing.T) {
	type args struct {
		rawParams []string
		sessions  interface{}
	}
	tests := []struct {
		name          string
		m             *Metric
		args          args
		want          map[string]string
		wantHardcoded map[string]bool
		wantExtra     []string
		wantErr       bool
		wantPanic     bool
	}{
		{
			name: "Must fail if too many parameters passed",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "15", "excessParam"},
				sessions:  map[string]conf.Session{},
			},
			want:          nil,
			wantHardcoded: nil,
			wantErr:       true,
			wantPanic:     false,
		},
		{
			name: "Must not fail if passed more parameters than described, but the metric has the varParam enabled",
			m:    metricSet["metric.query"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "queryName", "queryParam1", "queryParam2"},
				sessions:  map[string]conf.Session{},
			},
			want:          map[string]string{"Password": "password", "QueryName": "queryName", "URI": "localhost", "User": "user"},
			wantExtra:     []string{"queryParam1", "queryParam2"},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must not fail if passed more parameters than described, " +
				"but the metric has the varParam enabled (with session)",
			m: metricSet["metric.query"],
			args: args{
				rawParams: []string{"Session1", "", "", "queryName", "queryParam1", "queryParam2"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"Password": "password", "QueryName": "queryName", "URI": "localhost", "User": "user", "sessionName": "Session1",
			},
			wantExtra:     []string{"queryParam1", "queryParam2"},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must not fail if passed session only parameters none strict",
			m:    metricSet["metric.bar"],
			args: args{
				rawParams: []string{"Session1", "", "queryParam1"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"Password": "password", "URI": "localhost", "User": "user", "sessionName": "Session1",
			},
			wantExtra:     []string{"queryParam1"},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must not fail if missing session only parameters none strict",
			m:    metricSet["metric.bar"],
			args: args{
				rawParams: []string{"Session1", "", "queryParam1"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user"},
				},
			},
			want: map[string]string{
				"Password": "", "URI": "localhost", "User": "user", "sessionName": "Session1",
			},
			wantExtra:     []string{"queryParam1"},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must not fail if passed session only parameters none strict",
			m:    metricSet["metric.bar"],
			args: args{
				rawParams: []string{"Session1", "", "queryParam1"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"Password": "password", "URI": "localhost", "User": "user", "sessionName": "Session1",
			},
			wantExtra:     []string{"queryParam1"},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must pass if a connection parameter passed along with a session",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"Session1", "", "password"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"Param1": "60", "Password": "password", "URI": "localhost", "User": "user", "sessionName": "Session1",
			},
			wantHardcoded: map[string]bool{"Param1": true},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must pass if a connection parameter overwritten from key",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"Session1", "", "new_password"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"Param1": "60", "Password": "new_password", "URI": "localhost", "User": "user", "sessionName": "Session1",
			},
			wantHardcoded: map[string]bool{"Param1": true},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must fail if session only parameter passed in key",
			m:    metricSet["metric.bar.strict"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "param1"},
				sessions:  map[string]conf.Session{},
			},
			want:          nil,
			wantExtra:     nil,
			wantHardcoded: nil,
			wantErr:       true,
			wantPanic:     false,
		},
		{
			name: "Must fail if a required parameter is not specified",
			m:    metricSet["metric.query"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "", "queryParam1"},
				sessions:  map[string]conf.Session{},
			},
			want:          nil,
			wantHardcoded: nil,
			wantErr:       true,
			wantPanic:     false,
		},
		{
			name: "Must fail if validation failed",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "wrongValue"},
				sessions:  map[string]conf.Session{},
			},
			want:          nil,
			wantHardcoded: nil,
			wantErr:       true,
			wantPanic:     false,
		},
		{
			name: "Must fail if a session parameter did not pass validation",
			m:    metricSet["metric.userValidation"],
			args: args{
				rawParams: []string{"Session1"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "bob", Password: "password"},
				},
			},
			want:          nil,
			wantHardcoded: nil,
			wantErr:       true,
			wantPanic:     false,
		},
		{
			name: "Must fail if a required parameter is omitted in a session",
			m:    metricSet["metric.requiredSessionParam"],
			args: args{
				rawParams: []string{"Session1"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", Password: "password"},
				},
			},
			want:          nil,
			wantHardcoded: nil,
			wantErr:       true,
			wantPanic:     false,
		},
		{
			name: "Must panic if cannot find any session's parameter in a schema",
			m:    metricSet["metric.withoutPassword"],
			args: args{
				rawParams: []string{"Session1"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want:          nil,
			wantHardcoded: nil,
			wantErr:       false,
			wantPanic:     true,
		},
		{
			name: "Must successfully return parsed parameters (without session)",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "15"},
				sessions:  map[string]conf.Session{},
			},
			want:          map[string]string{"URI": "localhost", "User": "user", "Password": "password", "Param1": "15"},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must successfully return parsed parameters (with session)",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"Session1", "", "", "15"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"URI": "localhost", "User": "user", "Password": "password", "Param1": "15", "sessionName": "Session1",
			},
			wantHardcoded: map[string]bool{},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must successfully return parsed parameters (with hardcoded)",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"Session1", "", ""},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want: map[string]string{
				"URI": "localhost", "User": "user", "Password": "password", "Param1": "60", "sessionName": "Session1",
			},
			wantHardcoded: map[string]bool{"Param1": true},
			wantErr:       false,
			wantPanic:     false,
		},
		{
			name: "Must successfully return parsed parameters (with hardcoded)",
			m:    metricSet["metric.conn"],
			args: args{
				rawParams: []string{"Session1", "", ""},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user"},
				},
			},
			want: map[string]string{
				"URI": "localhost", "User": "user", "Password": "Password", "sessionName": "Session1",
			},
			wantHardcoded: map[string]bool{"Password": true},
			wantErr:       false,
			wantPanic:     false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.wantPanic {
				defer func() {
					if r := recover(); r == nil {
						t.Error("Metric.EvalParams() must panic with runtime error")
					}
				}()
			}

			gotParams, gotExtraParams, hc, err := tt.m.EvalParams(tt.args.rawParams, tt.args.sessions)
			if (err != nil) != tt.wantErr {
				t.Errorf("EvalParams() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotParams, tt.want) {
				t.Errorf("EvalParams() got = %v, want %v", gotParams, tt.want)
			}
			if !reflect.DeepEqual(hc, tt.wantHardcoded) {
				t.Errorf("EvalParams() gotHardcoded = %v, wantHardcoded %v", hc, tt.wantHardcoded)
			}
			if !reflect.DeepEqual(gotExtraParams, tt.wantExtra) {
				t.Errorf("EvalParams() got extraParams = %v, wantExtra %v", gotExtraParams, tt.wantExtra)
			}
		})
	}
}

func TestNew(t *testing.T) {
	type args struct {
		description string
		params      []*Param
		varParam    bool
	}
	tests := []struct {
		name      string
		args      args
		want      *Metric
		wantPanic bool
	}{
		{
			name: "Must fail if a parameter has a non-unique name",
			args: args{
				"Metric description.",
				[]*Param{paramURI, paramUsername, paramUsername, paramPassword, NewParam("Param", "Description.")},
				false,
			},
			want:      nil,
			wantPanic: true,
		},
		{
			name: "Must fail if a session placed not first",
			args: args{
				"Metric description.",
				[]*Param{paramUsername, paramPassword, paramURI, NewParam("Param", "Description.")},
				false,
			},
			want:      nil,
			wantPanic: true,
		},
		{
			name: "Must fail if parameters describing a connection placed not in a row",
			args: args{
				"Metric description.",
				[]*Param{paramURI, paramUsername, NewParam("Param", "Description."), paramPassword},
				false,
			},
			want:      nil,
			wantPanic: true,
		},
		{
			name: "Must successfully return a new metric",
			args: args{
				"Metric description.",
				[]*Param{paramURI, paramUsername, paramPassword, paramGeneral},
				false,
			},
			want: &Metric{
				"Metric description.",
				[]*Param{paramURI, paramUsername, paramPassword, paramGeneral},
				false,
			},
			wantPanic: false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.wantPanic {
				defer func() {
					if r := recover(); r == nil {
						t.Error("New() must panic with runtime error")
					}
				}()
			}

			if got := New(tt.args.description, tt.args.params, tt.args.varParam); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("New() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestSetDefaults(t *testing.T) {
	type args struct {
		params    map[string]string
		hardCoded map[string]bool
		defaults  interface{}
	}
	tests := []struct {
		name    string
		args    args
		want    map[string]string
		wantErr bool
	}{
		{
			"only_defaults",
			args{
				map[string]string{"Foo": "", "Bar": ""},
				nil,
				struct {
					Foo string
					Bar string
				}{"A", "B"},
			},
			map[string]string{"Foo": "A", "Bar": "B"},
			false,
		},
		{
			"default_overrides_empty",
			args{
				map[string]string{"Foo": "bar", "Abc": ""},
				nil,
				struct{ Abc string }{"foo"},
			},
			map[string]string{"Foo": "bar", "Abc": "foo"},
			false,
		},
		{
			"default_does_not_override_empty",
			args{
				map[string]string{"Foo": "bar", "Abc": "def"},
				nil,
				struct {
					Foo string
					Abc string
				}{"A", "B"},
			},
			map[string]string{"Foo": "bar", "Abc": "def"},
			false,
		},
		{
			"set_param_not_in_default",
			args{
				map[string]string{"foo": "bar", "abc": "def"},
				nil,
				struct {
					A string
					B string
				}{"A", "B"},
			},
			map[string]string{"foo": "bar", "abc": "def", "A": "A", "B": "B"},
			false,
		},
		{
			"set_default_not_in_param",
			args{
				map[string]string{"foo": "bar"},
				nil,
				struct {
					Bar string
				}{"A"},
			},
			map[string]string{"foo": "bar", "Bar": "A"},
			false,
		},
		{
			"empty_default",
			args{
				map[string]string{"foo": "bar"},
				nil,
				struct{}{},
			},
			map[string]string{"foo": "bar"},
			false,
		},
		{
			"empty_params",
			args{
				map[string]string{},
				nil,
				struct {
					Foo string
					Bar string
				}{"A", "B"},
			},
			map[string]string{"Foo": "A", "Bar": "B"},
			false,
		},
		{
			"empty_defaults",
			args{
				map[string]string{"Foo": "A", "Bar": "B"},
				nil,
				nil,
			},
			map[string]string{"Foo": "A", "Bar": "B"},
			false,
		},
		{
			"with_hardcoded",
			args{
				map[string]string{"Foo": "A", "Bar": "B"},
				map[string]bool{"Foo": true},
				struct {
					Foo string
					Bar string
				}{"C", "G"},
			},
			map[string]string{"Foo": "C", "Bar": "B"},
			false,
		},
		{
			"fail_with_int",
			args{
				map[string]string{"Foo": "bar"},
				nil,
				struct {
					Foo string
					Bar int
				}{"A", 3}},
			map[string]string{"Foo": "bar"},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if err := SetDefaults(tt.args.params, tt.args.hardCoded, tt.args.defaults); (err != nil) != tt.wantErr {
				t.Errorf("SetDefaults() error = %v, wantErr %v", err, tt.wantErr)
			}

			if !reflect.DeepEqual(tt.args.params, tt.want) {
				t.Errorf("SetDefaults() got = %v, want %v", tt.args.params, tt.want)
			}
		})
	}
}

func TestSessionToMap(t *testing.T) {
	type args struct {
		session interface{}
	}
	tests := []struct {
		name    string
		args    args
		wantOut map[string]string
		wantErr bool
	}{
		{
			"set_session",
			args{
				struct {
					Foo string
					Bar string
				}{"A", "B"}},
			map[string]string{"Foo": "A", "Bar": "B"},
			false,
		},
		{
			"empty_input",
			args{
				struct {
					Foo string
					Bar string
				}{},
			},
			map[string]string{"Foo": "", "Bar": ""},
			false,
		},
		{
			"nil_inputs",
			args{nil},
			nil,
			false,
		},
		{
			"with_int",
			args{
				struct {
					Foo string
					Bar int
				}{"A", 3}},
			map[string]string{"Foo": "A", "Bar": ""},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotOut, err := sessionToMap(tt.args.session)
			if (err != nil) != tt.wantErr {
				t.Errorf("SessionToMap() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if !reflect.DeepEqual(gotOut, tt.wantOut) {
				t.Errorf("SessionToMap() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_setDefaults(t *testing.T) {
	type args struct {
		params    map[string]string
		defaults  map[string]string
		hardCoded map[string]bool
	}
	tests := []struct {
		name string
		args args
		want map[string]string
	}{
		{
			"only_defaults",
			args{
				map[string]string{"foo": "", "bar": ""},
				map[string]string{"foo": "abc", "bar": "def"},
				nil,
			},
			map[string]string{"foo": "abc", "bar": "def"},
		},
		{
			"default_overrides_empty",
			args{
				map[string]string{"foo": "bar", "abc": ""},
				map[string]string{"foo": "bar", "abc": "foo"},
				nil,
			},
			map[string]string{"foo": "bar", "abc": "foo"},
		},
		{
			"default_does_not_override_empty",
			args{
				map[string]string{"foo": "bar", "abc": "def"},
				map[string]string{"foo": "bar", "abc": "foo"},
				nil,
			},
			map[string]string{"foo": "bar", "abc": "def"},
		},
		{
			"set_param_not_in_default",
			args{
				map[string]string{"foo": "bar", "abc": "def"},
				map[string]string{"bar": "foo"},
				nil,
			},
			map[string]string{"foo": "bar", "bar": "foo", "abc": "def"},
		},
		{
			"set_default_not_in_param",
			args{
				map[string]string{"foo": "bar"},
				map[string]string{"foo": "bar", "bar": "foo"},
				nil,
			},
			map[string]string{"foo": "bar", "bar": "foo"},
		},
		{
			"empty_default",
			args{
				map[string]string{"foo": "bar"},
				map[string]string{},
				nil,
			},
			map[string]string{"foo": "bar"},
		},
		{
			"empty_params",
			args{
				map[string]string{},
				map[string]string{"foo": "bar"},
				nil,
			},
			map[string]string{"foo": "bar"},
		},
		{
			"hardcoded",
			args{
				map[string]string{"foo": "abc"},
				map[string]string{"foo": "bar"},
				map[string]bool{"foo": true},
			},
			map[string]string{"foo": "bar"},
		},
		{
			"default_empty_value",
			args{
				map[string]string{"foo": "abc", "bar": "def", "ghj": ""},
				map[string]string{"foo": "", "bar": "mno"},
				map[string]bool{"bar": true},
			},
			map[string]string{"foo": "abc", "bar": "mno", "ghj": ""},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			setDefaults(tt.args.params, tt.args.hardCoded, tt.args.defaults)
			if !reflect.DeepEqual(tt.args.params, tt.want) {
				t.Errorf("SetDefaults() got = %v, want %v", tt.args.params, tt.want)
			}
		})
	}
}
