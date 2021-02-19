/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"net/http"
	"os"

	"github.com/chromedp/cdproto/network"
	"github.com/chromedp/cdproto/page"
	"github.com/chromedp/chromedp"
	"zabbix.com/internal/agent"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxerr"
)

var confDefault string
var options serviceOptions

func main() {
	var confFlag string
	var helpFlag bool

	const (
		confDescription = "Path to the configuration file"
		helpDefault     = false
		helpDescription = "Display this help message"
	)

	flag.StringVar(&confFlag, "config", confDefault, confDescription)
	flag.StringVar(&confFlag, "c", confDefault, confDescription+" (shorthand)")
	flag.BoolVar(&helpFlag, "help", helpDefault, helpDescription)
	flag.BoolVar(&helpFlag, "h", helpDefault, helpDescription+" (shorthand)")

	flag.Parse()

	if helpFlag {
		flag.Usage()
		os.Exit(0)
	}

	flag.Parse()

	loadConfig(confFlag)

	if err := start(false); err != nil {
		panic(err)
	}

	fmt.Println("stopping zabbix server")
}

func start(tls bool) error {
	http.HandleFunc("/report", reportHandler)

	if tls {
		return http.ListenAndServeTLS(":10053", "", "", nil)
	}
	fmt.Println("starting zabbix server")
	return http.ListenAndServe(":10053", nil)
}

type serviceOptions struct {
	ListenPort  int    `conf:"optional,range=1024:32767,default=10053"`
	AllowedIP   string `conf:"optional"`
	LogType     string `conf:"optional,default=file"`
	LogFile     string `conf:"optional,default=/tmp/zabbix_agent2.log"`
	LogFileSize int    `conf:"optional,range=0:1024,default=1"`
	Timeout     int    `conf:"optional,range=1:30,default=3"`
	TLSAccept   string `conf:"optional"`
	TLSCAFile   string `conf:"optional"`
	TLSCertFile string `conf:"optional"`
	TLSKeyFile  string `conf:"optional"`
}

type body struct {
	URL        string     `json:"URL"`
	Header     header     `json:"headers"`
	Parameters parameters `json:"parameters"`
}

type header struct {
	Cookie string `json:"Cookie"`
}

type parameters struct {
	Height int `json:"height"`
	Width  int `json:"width"`
}

func loadConfig(path string) {
	fmt.Println(path)
	if err := conf.Load(path, &options); err != nil {
		fatalExit("", err)
	}

	fmt.Printf("%+v", options)
}

func fatalExit(message string, err error) {
	if len(message) == 0 {
		message = err.Error()
	} else {
		message = fmt.Sprintf("%s: %s", message, err.Error())
	}

	if agent.Options.LogType == "file" {
		log.Critf("%s", message)
	}

	fmt.Fprintf(os.Stderr, "zabbix_web_service [%d]: ERROR: %s\n", os.Getpid(), message)
	os.Exit(1)
}

func reportHandler(w http.ResponseWriter, r *http.Request) {
	//TODO: change to json
	if r.Method != http.MethodPost {
		http.Error(w, "Method is not supported.", http.StatusMethodNotAllowed)
		return
	}

	contentType := r.Header.Get("Content-Type")
	if contentType != "application/json" {
		http.Error(w, "Content Type is not application/json.", http.StatusUnsupportedMediaType)
		return
	}

	b, err := ioutil.ReadAll(r.Body)
	if err != nil {
		http.Error(w, zbxerr.ErrorCannotFetchData.Error(), 500)
		return
	}

	var req body

	if err = json.Unmarshal(b, &req); err != nil {
		http.Error(w, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err).Error(), http.StatusBadRequest)
		return
	}

	ctx, cancel := chromedp.NewContext(context.Background())
	defer cancel()

	var buf []byte
	if err = chromedp.Run(ctx, chromedp.Tasks{
		network.SetExtraHTTPHeaders(network.Headers(map[string]interface{}{"Cookie": req.Header.Cookie})),
		chromedp.Navigate(req.URL),
		chromedp.WaitReady("body"),
		chromedp.ActionFunc(func(ctx context.Context) error {
			var err error
			buf, _, err = page.PrintToPDF().
				WithPrintBackground(true).
				WithPreferCSSPageSize(true).
				WithPaperWidth(float64(req.Parameters.Width) / 25.4).
				WithPaperHeight(float64(req.Parameters.Height) / 25.4).
				Do(ctx)
			return err
		}),
	}); err != nil {
		http.Error(w, zbxerr.ErrorCannotFetchData.Wrap(err).Error(), http.StatusInternalServerError)
		return
	}

	err = ioutil.WriteFile("/home/eriks/Desktop/test.pdf", buf, 0644)
	if err != nil {
		panic(err)
	}

	return
}
