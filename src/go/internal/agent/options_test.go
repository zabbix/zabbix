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
	"reflect"
	"testing"

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

func TestParseServerActive(t *testing.T) {
	t.Parallel()

	invalidServerActiveConfigurationErrorEmptyValue := `address "": empty value`
	invalidServerActiveConfigurationErrorEmptyValueWithFooPort10051 := `address "foo:10051" specified more than once`

	tests := []struct {
		name         string
		serverActive string
		err          error
		wantErr      bool
		result       [][]string
	}{
		{
			"+IPv6",
			"fe80::72d5:8d8b:b2ca:206", nil,
			false,
			[][]string{{"[fe80::72d5:8d8b:b2ca:206]:10051"}},
		},
		{
			"+emptyString",
			"",
			nil,
			false,
			[][]string{},
		},
		{
			"-squareBrackets",
			" [ ]:80 ",
			errors.New(`address "[ ]:80": empty value`),
			true,
			nil,
		},
		{
			"-emptySpaceAddressAndValidPort",
			" :80 ",
			errors.New(`address ":80": empty value`),
			true,
			nil,
		},
		{
			"+zero",
			" 0 ",
			nil,
			false,
			[][]string{{"0:10051"}},
		},
		{
			"+loopbackAddress",
			"127.0.0.1",
			nil,
			false,
			[][]string{{"127.0.0.1:10051"}},
		},
		{
			"+loopbackAddressIPv6",
			"::1",
			nil,
			false,
			[][]string{{"[::1]:10051"}},
		},
		{
			"+stringThatShouldNotBeValidIPAddress",
			"aaa",
			nil,
			false,
			[][]string{{"aaa:10051"}},
		},
		{
			"+loopbackAddressAndPort",
			"127.0.0.1:123",
			nil,
			false,
			[][]string{{"127.0.0.1:123"}},
		},
		{
			"+loopbackAddressIPv6AndPort",
			"::1:123",
			nil,
			false,
			[][]string{{"[::1:123]:10051"}},
		},
		{
			"+stringThatShouldNotBeValidIPAddressAndPort",
			"aaa:123",
			nil,
			false,
			[][]string{{"aaa:123"}},
		},
		{
			"+defaultIPAddressInSquareBracketsAndPort",
			"[127.0.0.1]:123",
			nil,
			false,
			[][]string{{"127.0.0.1:123"}},
		},
		{
			"+loopbackIPv6AddressInSquareBracketsAndPort",
			"[::1]:123",
			nil,
			false,
			[][]string{{"[::1]:123"}},
		},
		{
			"+stringThatShouldNotBeValidIPAddressInSquareBracketsAndPort",
			"[aaa]:123",
			nil,
			false,
			[][]string{{"aaa:123"}},
		},
		{
			"+loopbackIPv6AddressInSquareBracketsWithSpaces",
			"[ ::1 ]",
			nil,
			false,
			[][]string{{"[::1]:10051"}},
		},
		{
			"+twoIPv6AddressesSecondIsInSquareBrackets",
			"fe80::72d5:8d8b:b2ca:206, [fe80::72d5:8d8b:b2ca:207]:10052",
			nil,
			false,
			[][]string{{"[fe80::72d5:8d8b:b2ca:206]:10051"}, {"[fe80::72d5:8d8b:b2ca:207]:10052"}},
		},
		{
			"-coma",
			",",
			errors.New(invalidServerActiveConfigurationErrorEmptyValue),
			true,
			nil,
		},
		{
			"-comaWithEmptySpacesAround",
			" , ",
			errors.New(invalidServerActiveConfigurationErrorEmptyValue),
			true,
			nil,
		},
		{
			"+twoLoopbackAddressesEmptySpaceBeforeComa",
			"127.0.0.1 , 127.0.0.2:10052 ",
			nil,
			false,
			[][]string{{"127.0.0.1:10051"}, {"127.0.0.2:10052"}},
		},
		{
			"+twoLoopbackAddresses",
			"127.0.0.1,127.0.0.2:10052",
			nil,
			false,
			[][]string{{"127.0.0.1:10051"}, {"127.0.0.2:10052"}},
		},
		{
			"+twoIPv6CompressedAddresses",
			"::1, ::2",
			nil,
			false,
			[][]string{{"[::1]:10051"}, {"[::2]:10051"}},
		},
		{
			"+twoStringsThatShouldNotBeValidIPAddressesWithEmptySpace",
			"aaa, aab",
			nil,
			false,
			[][]string{{"aaa:10051"}, {"aab:10051"}},
		},
		{
			"+stringThatShouldNotBeValidIPAddressWithPort",
			"aaa:10052,aab",
			nil,
			false,
			[][]string{{"aaa:10052"}, {"aab:10051"}},
		},
		{
			"+twoIPAddressesWithPorts",
			"127.0.0.1:123,127.0.0.2:123",
			nil,
			false,
			[][]string{{"127.0.0.1:123"}, {"127.0.0.2:123"}},
		},
		{
			"+twoCompressedIPv6AddressesWithPorts",
			"::2:123,[::1:123]:10052",
			nil,
			false,
			[][]string{{"[::2:123]:10051"}, {"[::1:123]:10052"}},
		},
		{
			"+twoStringsThatShouldNotBeValidIPAddressesWithPorts",
			"aaa:123,aab:123",
			nil,
			false,
			[][]string{{"aaa:123"}, {"aab:123"}},
		},
		{
			"+twoIPAddressesInSquareBracketsWithPorts",
			"[127.0.0.1]:123,[127.0.0.2]:123",
			nil,
			false,
			[][]string{{"127.0.0.1:123"}, {"127.0.0.2:123"}},
		},
		{
			"+twoIPv6CompressedAddressesWithPorts",
			"[::1]:123,[::2]:123",
			nil,
			false,
			[][]string{{"[::1]:123"}, {"[::2]:123"}},
		},
		{
			"+twoStringsThatShouldNotBeValidIPAddressesInSquareBracketsWithPorts",
			"[aaa]:123,[aab]:123",
			nil,
			false,
			[][]string{{"aaa:123"}, {"aab:123"}},
		},
		{
			"+twoStringsThatShouldNotBeValidIPAddresses",
			"abc,aaa",
			nil,
			false,
			[][]string{{"abc:10051"}, {"aaa:10051"}},
		},
		{
			"+clusterConfigurationSeparatedBySemicolon",
			"foo;bar,baz",
			nil,
			false,
			[][]string{{"foo:10051", "bar:10051"}, {"baz:10051"}},
		},
		{
			"+clusterConfigurationSeparatedBySemicolonWithPorts",
			"foo:10051;bar:10052,baz:10053",
			nil,
			false,
			[][]string{{"foo:10051", "bar:10052"}, {"baz:10053"}},
		},
		{
			"-twoStringsSeparatedByComa",
			"foo,foo",
			errors.New(invalidServerActiveConfigurationErrorEmptyValueWithFooPort10051),
			true,
			nil,
		},
		{
			"-twoStringsSeparatedBySemicolon",
			"foo;foo",
			errors.New(invalidServerActiveConfigurationErrorEmptyValueWithFooPort10051),
			true,
			nil,
		},
		{
			"-threeClustersWithStringThatShouldNotBeValidIPAddresses",
			"foo;bar,foo2;foo",
			errors.New(invalidServerActiveConfigurationErrorEmptyValueWithFooPort10051),
			true,
			nil,
		},
		{
			"-semicolon",
			";",
			errors.New(invalidServerActiveConfigurationErrorEmptyValue),
			true,
			nil,
		},
		{
			"-semicolonWithEmptySpaceBefore",
			" ;",
			errors.New(invalidServerActiveConfigurationErrorEmptyValue),
			true,
			nil,
		},
		{
			"-semicolonWithEmptySpaceAfter",
			"; ",
			errors.New(invalidServerActiveConfigurationErrorEmptyValue),
			true,
			nil,
		},
		{
			"-semicolonWithEmptySpacesBeforeAndAfter",
			" ; ",
			errors.New(invalidServerActiveConfigurationErrorEmptyValue),
			true,
			nil,
		},
		{
			"-newline",
			"\n",
			nil,
			false,
			[][]string{},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			al, err := ParseServerActive(tt.serverActive)

			if (err != nil) != tt.wantErr {
				t.Fatalf("ValidateOptions() error expectation failed: wantErr=%v, got err=%v", tt.wantErr, err)
			}

			if tt.wantErr && err.Error() != tt.err.Error() {
				t.Fatalf("ValidateOptions() unexpected error:\n%v\nexpected error:\n%v\n", err, tt.err)
			}

			if len(al) != len(tt.result) {
				t.Errorf("ParseServerActive failed for ServerActive input: %s, expect: %d got: %d "+
					"address in the list\n", tt.serverActive, len(tt.result), len(al))
			} else if !reflect.DeepEqual(al, tt.result) {
				t.Errorf("ParseServerActive failed for ServerActive input: %s, received value: %s "+
					"does not match expected value: %s\n", tt.serverActive, al, tt.result)
			}
		})
	}
}
