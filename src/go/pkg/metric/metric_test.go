/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	paramPassword = NewConnParam("Password", "Description.").WithDefault("")
	paramGeneral  = NewParam("GeneralParam", "Description.")
)

var metricSet = MetricSet{
	"metric.foo": New("Foo description.",
		[]*Param{paramURI, paramUsername, paramPassword,
			NewParam("Param1", "Description.").WithDefault("60").WithValidator(SetValidator{Set: []string{"15", "60"}}),
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
}

func TestMetric_EvalParams(t *testing.T) {
	type args struct {
		rawParams []string
		sessions  interface{}
	}
	tests := []struct {
		name      string
		m         *Metric
		args      args
		want      map[string]string
		wantErr   bool
		wantPanic bool
	}{
		{
			name: "Must fail if too many parameters passed",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "15", "excessParam"},
				sessions:  map[string]conf.Session{},
			},
			want:      nil,
			wantErr:   true,
			wantPanic: false,
		},
		{
			name: "Must not fail if passed more parameters than described, but the metric has the varParam enabled",
			m:    metricSet["metric.query"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "queryName", "queryParam1", "queryParam2"},
				sessions:  map[string]conf.Session{},
			},
			want:      map[string]string{"Password": "password", "QueryName": "queryName", "URI": "localhost", "User": "user"},
			wantErr:   false,
			wantPanic: false,
		},
		{
			name: "Must fail if a required parameter is not specified",
			m:    metricSet["metric.query"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "", "queryParam1"},
				sessions:  map[string]conf.Session{},
			},
			want:      nil,
			wantErr:   true,
			wantPanic: false,
		},
		{
			name: "Must fail if validation failed",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "wrongValue"},
				sessions:  map[string]conf.Session{},
			},
			want:      nil,
			wantErr:   true,
			wantPanic: false,
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
			want:      nil,
			wantErr:   true,
			wantPanic: false,
		},
		{
			name: "Must fail if a connection parameter passed along with a session",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"Session1", "", "password"},
				sessions: map[string]conf.Session{
					"Session1": {URI: "localhost", User: "user", Password: "password"},
				},
			},
			want:      nil,
			wantErr:   true,
			wantPanic: false,
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
			want:      nil,
			wantErr:   true,
			wantPanic: false,
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
			want:      nil,
			wantErr:   false,
			wantPanic: true,
		},
		{
			name: "Must successfully return parsed parameters (without session)",
			m:    metricSet["metric.foo"],
			args: args{
				rawParams: []string{"localhost", "user", "password", "15"},
				sessions:  map[string]conf.Session{},
			},
			want:      map[string]string{"URI": "localhost", "User": "user", "Password": "password", "Param1": "15"},
			wantErr:   false,
			wantPanic: false,
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
			wantErr:   false,
			wantPanic: false,
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

			gotParams, err := tt.m.EvalParams(tt.args.rawParams, tt.args.sessions)
			if (err != nil) != tt.wantErr {
				t.Errorf("EvalParams() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotParams, tt.want) {
				t.Errorf("EvalParams() got = %v, want %v", gotParams, tt.want)
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
				description: "Metric description.",
				params:      []*Param{paramURI, paramUsername, paramPassword, paramGeneral},
				varParam:    false,
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
