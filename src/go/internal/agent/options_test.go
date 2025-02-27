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

package agent

import (
	"errors"
	"testing"
	"reflect"

	"github.com/google/go-cmp/cmp"
	"github.com/google/go-cmp/cmp/cmpopts"
	"golang.zabbix.com/sdk/conf"
)

func TestAgentOptions_RemovePluginSystemOptions(t *testing.T) {
	t.Parallel()

	testPath := "path/to/plugin"
	forActiveChecksOn := 1

	type fields struct {
		Plugins map[string]any
	}

	tests := []struct {
		name       string
		fields     fields
		wantSysOpt PluginSystemOptions
		wantOpt    *AgentOptions
		wantErr    bool
	}{
		{
			"+valid",
			fields{
				map[string]any{
					"debug": &conf.Node{
						Nodes: []any{
							&conf.Node{
								Name: "System",
								Nodes: []any{
									&conf.Node{
										Name: "Path",
										Line: 1,
										Nodes: []any{
											&conf.Value{Value: []byte("path/to/plugin"), Line: 1},
										},
									},
								},
							},
						},
					},
				},
			},
			PluginSystemOptions{
				"debug": SystemOptions{Path: &testPath},
			},
			&AgentOptions{
				Plugins: map[string]any{"debug": &conf.Node{Nodes: []any{}}},
			},
			false,
		},
		{
			"+full",
			fields{
				map[string]any{
					"debug": &conf.Node{
						Nodes: []any{
							&conf.Node{
								Name: "System",
								Nodes: []any{
									&conf.Node{
										Name: "Path",
										Line: 1,
										Nodes: []any{
											&conf.Value{Value: []byte("path/to/plugin"), Line: 1},
										},
									},
									&conf.Node{
										Name: "Capacity",
										Line: 2,
										Nodes: []any{
											&conf.Value{Value: []byte("15"), Line: 2},
										},
									},
									&conf.Node{
										Name: "ForceActiveChecksOnStart",
										Line: 3,
										Nodes: []any{
											&conf.Value{Value: []byte("1"), Line: 3},
										},
									},
								},
							},
						},
					},
				},
			},
			PluginSystemOptions{
				"debug": SystemOptions{
					Path:                     &testPath,
					Capacity:                 15,
					ForceActiveChecksOnStart: &forActiveChecksOn,
				},
			},
			&AgentOptions{
				Plugins: map[string]any{"debug": &conf.Node{Nodes: []any{}}},
			},
			false,
		},
		{
			"+leftoverOptions",
			fields{
				map[string]any{
					"debug": &conf.Node{
						Nodes: []any{
							&conf.Node{
								Name: "System",
								Nodes: []any{
									&conf.Node{
										Name: "Path",
										Line: 1,
										Nodes: []any{
											&conf.Value{Value: []byte("path/to/plugin"), Line: 1},
										},
									},
									&conf.Node{
										Name: "Capacity",
										Line: 2,
										Nodes: []any{
											&conf.Value{Value: []byte("15"), Line: 2},
										},
									},
									&conf.Node{
										Name: "ForceActiveChecksOnStart",
										Line: 3,
										Nodes: []any{
											&conf.Value{Value: []byte("1"), Line: 3},
										},
									},
								},
							},
							&conf.Node{
								Name: "Leftover",
								Line: 4,
								Nodes: []any{
									&conf.Value{Value: []byte("foobar"), Line: 4},
								},
							},
						},
					},
				},
			},
			PluginSystemOptions{
				"debug": SystemOptions{
					Path:                     &testPath,
					Capacity:                 15,
					ForceActiveChecksOnStart: &forActiveChecksOn,
				},
			},
			&AgentOptions{
				Plugins: map[string]any{
					"debug": &conf.Node{
						Nodes: []any{
							&conf.Node{
								Name: "Leftover",
								Nodes: []any{
									&conf.Value{Value: []uint8("foobar"), Line: 4},
								},
								Line: 4,
							},
						},
					},
				},
			},
			false,
		},
		{
			"-empty",
			fields{map[string]any{}},
			PluginSystemOptions{},
			&AgentOptions{Plugins: map[string]any{}},
			false,
		},
		{
			"-nil",
			fields{nil},
			PluginSystemOptions{},
			&AgentOptions{Plugins: nil},
			false,
		},
		{
			"-err",
			fields{
				map[string]any{"debug": "foobar"},
			},
			nil,
			&AgentOptions{Plugins: map[string]any{"debug": "foobar"}},
			true,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			a := &AgentOptions{
				Plugins: tt.fields.Plugins,
			}

			got, err := a.RemovePluginSystemOptions()
			if (err != nil) != tt.wantErr {
				t.Fatalf("AgentOptions.RemovePluginSystemOptions() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.wantOpt.Plugins, a.Plugins, cmpopts.IgnoreUnexported(conf.Node{})); diff != "" {
				t.Fatalf("AgentOptions.RemovePluginSystemOptions() Agent options = %s", diff)
			}

			if diff := cmp.Diff(tt.wantSysOpt, got); diff != "" {
				t.Fatalf("AgentOptions.RemovePluginSystemOptions() System options = %s", diff)
			}
		})
	}
}

func TestCutAfterN(t *testing.T) {
	type args struct {
		s string
		n int
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"+base", args{"foobar", 3}, "foo"},
		{"+shorter string", args{"foo", 4}, "foo"},
		{"+cut after zero", args{"foobar", 0}, ""},
		{"+shorter by one byte", args{"foo", 2}, "fo"},
		{"-empty string", args{"", 3}, ""},
		{"-empty", args{"", 0}, ""},
		{"-one utf-8 character", args{"ыы", 1}, "ы"},
		{"-two utf-8 characters", args{"ыыыы", 2}, "ыы"},
		{"-japanese utf-8 characters", args{"日本語", 2}, "日本"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := CutAfterN(tt.args.s, tt.args.n); got != tt.want {
				t.Errorf("CutAfterN() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_removeSystem(t *testing.T) {
	t.Parallel()

	type args struct {
		privateOptions any
	}

	tests := []struct {
		name string
		args args
		want any
	}{
		{
			"+valid",
			args{
				&conf.Node{
					Nodes: []any{
						&conf.Node{
							Name: "System",
							Nodes: []any{
								&conf.Node{
									Name: "Path",
									Line: 1,
									Nodes: []any{
										&conf.Value{Value: []byte("path/to/plugin"), Line: 1},
									},
								},
							},
						},
					},
				},
			},
			&conf.Node{Nodes: []any{}},
		},
		{
			"+additionalDataBefore",
			args{
				&conf.Node{
					Nodes: []any{
						&conf.Node{
							Name: "LeftoverBefore",
							Line: 1,
							Nodes: []any{
								&conf.Value{Value: []byte("foobar"), Line: 1},
							},
						},
						&conf.Node{
							Name: "System",
							Nodes: []any{
								&conf.Node{
									Name: "Path",
									Line: 2,
									Nodes: []any{
										&conf.Value{Value: []byte("path/to/plugin"), Line: 2},
									},
								},
							},
						},
					},
				},
			},
			&conf.Node{
				Nodes: []any{
					&conf.Node{
						Name: "LeftoverBefore",
						Line: 1,
						Nodes: []any{
							&conf.Value{Value: []byte("foobar"), Line: 1},
						},
					},
				},
			},
		},
		{
			"+additionalDataAfter",
			args{
				&conf.Node{
					Nodes: []any{
						&conf.Node{
							Name: "System",
							Nodes: []any{
								&conf.Node{
									Name: "Path",
									Line: 1,
									Nodes: []any{
										&conf.Value{Value: []byte("path/to/plugin"), Line: 1},
									},
								},
							},
						},
						&conf.Node{
							Name: "LeftoverAfter",
							Line: 2,
							Nodes: []any{
								&conf.Value{Value: []byte("foobar"), Line: 2},
							},
						},
					},
				},
			},
			&conf.Node{
				Nodes: []any{
					&conf.Node{
						Name: "LeftoverAfter",
						Line: 2,
						Nodes: []any{
							&conf.Value{Value: []byte("foobar"), Line: 2},
						},
					},
				},
			},
		},
		{
			"+additionalDataBeforeAndAfter",
			args{
				&conf.Node{
					Nodes: []any{
						&conf.Node{
							Name: "LeftoverBefore",
							Line: 1,
							Nodes: []any{
								&conf.Value{Value: []byte("foobar"), Line: 1},
							},
						},
						&conf.Node{
							Name: "System",
							Nodes: []any{
								&conf.Node{
									Name: "Path",
									Line: 2,
									Nodes: []any{
										&conf.Value{Value: []byte("path/to/plugin"), Line: 2},
									},
								},
							},
						},
						&conf.Node{
							Name: "LeftoverAfter",
							Line: 3,
							Nodes: []any{
								&conf.Value{Value: []byte("foobar"), Line: 3},
							},
						},
					},
				},
			},
			&conf.Node{
				Nodes: []any{
					&conf.Node{
						Name: "LeftoverBefore",
						Line: 1,
						Nodes: []any{
							&conf.Value{Value: []byte("foobar"), Line: 1},
						},
					},
					&conf.Node{
						Name: "LeftoverAfter",
						Line: 3,
						Nodes: []any{
							&conf.Value{Value: []byte("foobar"), Line: 3},
						},
					},
				},
			},
		},
		{
			"+noSystemData",
			args{
				&conf.Node{
					Nodes: []any{
						&conf.Node{
							Name: "Before",
							Line: 1,
							Nodes: []any{
								&conf.Value{Value: []byte("foobar"), Line: 1},
							},
						},
						&conf.Node{
							Name: "NotSystem",
							Nodes: []any{
								&conf.Node{
									Name: "Path",
									Line: 2,
									Nodes: []any{
										&conf.Value{Value: []byte("path/to/not/plugin"), Line: 2},
									},
								},
							},
						},
						&conf.Node{
							Name: "After",
							Line: 3,
							Nodes: []any{
								&conf.Value{Value: []byte("foobar"), Line: 3},
							},
						},
					},
				},
			},
			&conf.Node{
				Nodes: []any{
					&conf.Node{
						Name: "Before",
						Line: 1,
						Nodes: []any{
							&conf.Value{Value: []byte("foobar"), Line: 1},
						},
					},
					&conf.Node{
						Name: "NotSystem",
						Nodes: []any{
							&conf.Node{
								Name: "Path",
								Line: 2,
								Nodes: []any{
									&conf.Value{Value: []byte("path/to/not/plugin"), Line: 2},
								},
							},
						},
					},
					&conf.Node{
						Name: "After",
						Line: 3,
						Nodes: []any{
							&conf.Value{Value: []byte("foobar"), Line: 3},
						},
					},
				},
			},
		},
		{
			"-empty",
			args{nil},
			nil,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := removeSystem(tt.args.privateOptions)
			if diff := cmp.Diff(tt.want, got, cmpopts.IgnoreUnexported(conf.Node{})); diff != "" {
				t.Fatalf("removeSystem() = %s", diff)
			}
		})
	}
}

/*
* Only for the 'Server' parameter (for now).
 */
func Test_ValidateOptions(t *testing.T) {
	t.Parallel()

	invalidServerConfigurationError := "Invalid \"Server\" configuration parameter: invalid \"Server\" " +
		"configuration: incorrect address parameter: "

	type args struct {
		options *AgentOptions
	}

	tests := []struct {
		name    string
		args    args
		err     error
		wantErr bool
	}{
		{
			"+wrongIP",
			args{
				&AgentOptions{
					Server: "999.999.999.999",
				},
			},
			nil,
			false,
		},
		{
			"+singleAddr",
			args{
				&AgentOptions{
					Server: "127.0.0.1",
				},
			},
			nil,
			false,
		},
		{
			"+multipleAddr",
			args{
				&AgentOptions{
					Server: "localhost,127.0.0.1",
				},
			},
			nil,
			false,
		},
		{
			"+empty",
			args{
				&AgentOptions{
					Server: "",
				},
			},
			nil,
			false,
		},
		{
			"-newline",
			args{
				&AgentOptions{
					Server: "\n",
				},
			},
			errors.New(invalidServerConfigurationError + "\"\n\"."),
			true,
		},
		{
			"-coma",
			args{
				&AgentOptions{
					Server: ",",
				},
			},
			errors.New(invalidServerConfigurationError + "\"\"."),
			true,
		},
		{
			"-trailingComa",
			args{
				&AgentOptions{
					Server: "localhost,",
				},
			},
			errors.New(invalidServerConfigurationError + "\"\"."),
			true,
		},
		{
			"-semicolonOnly",
			args{
				&AgentOptions{
					Server: ";",
				},
			},
			errors.New(invalidServerConfigurationError + "\";\"."),
			true,
		},
		{
			"-semicolonWithMultipleAddr",
			args{
				&AgentOptions{
					Server: "127.0.0.1;localhost",
				},
			},
			errors.New(invalidServerConfigurationError + "\"127.0.0.1;localhost\"."),
			true,
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := ValidateOptions(tt.args.options)

			if (err != nil) != tt.wantErr {
				t.Fatalf("ValidateOptions() error expectation failed: wantErr=%v, got err=%v", tt.wantErr, err)
			}

			if tt.wantErr && err.Error() != tt.err.Error() {
				t.Fatalf("ValidateOptions() unexpected error:\n%v\nexpected error:\n%v\n", err, tt.err)
			}
		})
	}
}

type ParseServerActiveParams struct {
	serverActive string
	isError      bool
	result       [][]string
}

func TestParseServerActive(t *testing.T) {

	var inputs = []ParseServerActiveParams{
		{"fe80::72d5:8d8b:b2ca:206", false, [][]string{{"[fe80::72d5:8d8b:b2ca:206]:10051"}}},
		{"", false, [][]string{}},
		{" [ ]:80 ", true, nil},
		{" :80 ", true, nil},
		{" 0 ", false, [][]string{{"0:10051"}}},
		{"127.0.0.1", false, [][]string{{"127.0.0.1:10051"}}},
		{"::1", false, [][]string{{"[::1]:10051"}}},
		{"aaa", false, [][]string{{"aaa:10051"}}},
		{"127.0.0.1:123", false, [][]string{{"127.0.0.1:123"}}},
		{"::1:123", false, [][]string{{"[::1:123]:10051"}}},
		{"aaa:123", false, [][]string{{"aaa:123"}}},
		{"[127.0.0.1]:123", false, [][]string{{"127.0.0.1:123"}}},
		{"[::1]:123", false, [][]string{{"[::1]:123"}}},
		{"[aaa]:123", false, [][]string{{"aaa:123"}}},
		{"[ ::1 ]", false, [][]string{{"[::1]:10051"}}},
		{"fe80::72d5:8d8b:b2ca:206, [fe80::72d5:8d8b:b2ca:207]:10052", false,
			[][]string{{"[fe80::72d5:8d8b:b2ca:206]:10051"}, {"[fe80::72d5:8d8b:b2ca:207]:10052"}}},
		{",", true, nil},
		{" , ", true, nil},
		{"127.0.0.1 , 127.0.0.2:10052 ", false, [][]string{{"127.0.0.1:10051"}, {"127.0.0.2:10052"}}},
		{"127.0.0.1,127.0.0.2:10052", false, [][]string{{"127.0.0.1:10051"}, {"127.0.0.2:10052"}}},
		{"::1, ::2", false, [][]string{{"[::1]:10051"}, {"[::2]:10051"}}},
		{"aaa, aab", false, [][]string{{"aaa:10051"}, {"aab:10051"}}},
		{"aaa:10052,aab", false, [][]string{{"aaa:10052"}, {"aab:10051"}}},
		{"127.0.0.1:123,127.0.0.2:123", false, [][]string{{"127.0.0.1:123"}, {"127.0.0.2:123"}}},
		{"::2:123,[::1:123]:10052", false, [][]string{{"[::2:123]:10051"}, {"[::1:123]:10052"}}},
		{"aaa:123,aab:123", false, [][]string{{"aaa:123"}, {"aab:123"}}},
		{"[127.0.0.1]:123,[127.0.0.2]:123", false, [][]string{{"127.0.0.1:123"}, {"127.0.0.2:123"}}},
		{"[::1]:123,[::2]:123", false, [][]string{{"[::1]:123"}, {"[::2]:123"}}},
		{"[aaa]:123,[aab]:123", false, [][]string{{"aaa:123"}, {"aab:123"}}},
		{"abc,aaa", false, [][]string{{"abc:10051"}, {"aaa:10051"}}},
		{"foo;bar,baz", false, [][]string{{"foo:10051", "bar:10051"}, {"baz:10051"}}},
		{"foo:10051;bar:10052,baz:10053", false, [][]string{{"foo:10051", "bar:10052"}, {"baz:10053"}}},
		{"foo,foo", true, nil},
		{"foo;foo", true, nil},
		{"foo;bar,foo2;foo", true, nil},
		{";", true, nil},
		{" ;", true, nil},
		{"; ", true, nil},
		{" ; ", true, nil},
		{",", true, nil},
		{"\n", true, nil},
	}

	for i, p := range inputs {
		var al [][]string
		var err error

		//agent.Options.ServerActive = p.serverActive
		if al, err = ParseServerActive(p.serverActive); nil != err && true != p.isError {
			t.Errorf("[%d] test with value \"%s\" failed: %s\n", i, p.serverActive, err.Error())
			continue
		}

		if p.isError {
			continue
		}

		if len(al) != len(p.result) {
			t.Errorf("[%d] test with value \"%s\" failed, expect: %d got: %d address in the list\n", i, p.serverActive, len(p.result), len(al))
		} else if !reflect.DeepEqual(al, p.result) {
			t.Errorf("[%d] test with value \"%s\" failed: received value: %s does not match: %s\n", i, p.serverActive, al, p.result)
		}
	}
}
