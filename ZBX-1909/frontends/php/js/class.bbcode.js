//Javascript document
/*
 ** ZABBIX
 ** Copyright (C) 2000-2010 SIA Zabbix
 **
 ** This program is free software; you can redistribute it and/or modify
 ** it under the terms of the GNU General Public License as published by
 ** the Free Software Foundation; either version 2 of the License, or
 ** (at your option) any later version.
 **
 ** This program is distributed in the hope that it will be useful,
 ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 ** GNU General Public License for more details.
 **
 ** You should have received a copy of the GNU General Public License
 ** along with this program; if not, write to the Free Software
 ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 **/

var BBCode = {
opentags:		null,		// open tag stack
crlf2br:		null,		// convert CRLF to <br>?
noparse:		null,		// ignore BBCode tags?
urlstart:		null,		// beginning of the URL if zero or greater (ignored if -1)

RE:{
	'bbcode':		/^\/?(?:b|i|u|pre|code|color|size|noparse|url)$/, // BBcode tags
	'color':		/^(:?#(?:[0-9a-f]{3})?[0-9a-f]{3})$/i, // color names or hex color
	'uri':			/^[-;\/\?:@&=\+\$,_\.!~\*'\(\)%0-9a-z]{1,512}$/i, // reserved, unreserved, escaped and alpha-numeric [RFC2396]
	'format':		/([\r\n])|(?:\[([a-z]{1,16})(?:=([^\x00-\x1F''\(\)<>\[\]]{1,256}))?\])|(?:\[\/([a-z]{1,16})\])/ig // main regular expression: CRLF, [tag=option], [tag] or [/tag]
},

// post must be HTML-encoded
Parse: function(post){
	this.crlf2br = false;
	this.noparse = false;
	this.urlstart = -1;
	this.opentags = new Array();

	var result = post.replace(this.RE.format, this.toHtml.bind(this));

	if(this.opentags.length) {
		var endtags = '';

		if(this.opentags[this.opentags.length-1].bbtag == 'url') {
			this.opentags.pop();
			endtags += '\'>' + post.substr(this.urlstart, post.length-this.urlstart) + '</a>';
		}

		while(this.opentags.length){
			endtags += this.opentags.pop().etag;
		}
	}

return endtags ? result + endtags : result;
},

// check if it's a valid BBCode tag
isValidTag: function(str){
	if(empty(str)) return false;

return this.RE.bbcode.test(str);
},

toHtml: function(mstr, crlf, tag, option, tagEnd, offset, string){
	if(!empty(crlf)){
		if(!this.crlf2br) return mstr;

		if(crlf == '\r') return '';
		else if(crlf == '\n') return '<br />';
	}

// handle start tags
	if(this.isValidTag(tag)){
		if(this.noparse) return '['+tag+']';

		if(!empty(this.opentags) && (this.opentags[this.opentags.length-1].bbtag == 'url') && (this.urlstart >= 0)){
			return '['+tag+']';
		}

		switch(tag){
			case 'noparse':
				this.noparse = true;
				return '';
			case 'url':
				this.opentags.push({'bbtag':tag, 'etag': '</a>'});

				if(option && this.RE.uri.test(option)) {
					this.urlstart = -1;
					return '<a href="' + option + '">';
				}

				this.urlstart = mstr.length + offset;

				return '<a href="';
			case 'code':
				this.opentags.push({'bbtag':tag, 'etag': '</code></pre>'});
				this.noparse = false;
				return '<pre><code>';
			case 'pre':
				this.opentags.push({'bbtag':tag, 'etag': '</pre>'});
				this.noparse = false;
				return '<pre>';
			case 'color':
				if(!option || !this.RE.color.test(option)) option = 'inherit';

				this.opentags.push({'bbtag':tag, 'etag': '</span>'});
				return '<span style="color: ' + option + '">';
			case 'size':
				option = option || '7';

				this.opentags.push({'bbtag':tag, 'etag': '</span>'});
				return '<span style="font-size: ' + parseInt(option, 10)+'pt">';
			default:
				this.opentags.push({'bbtag':tag, 'etag': '</' + tag + '>'});
				return '<' + tag + '>';
		}
	}

// process end tags
	if(this.isValidTag(tagEnd)) {
		if(this.noparse){
			if(tagEnd == 'noparse')  {
				this.noparse = false;
				return '';
			}

			return '[/'+tagEnd+']';
		}

		if(!this.opentags.length || this.opentags[this.opentags.length-1].bbtag != tagEnd){
			return '<span style="color: red">[/'+tagEnd+']</span>';
		}

		if(tagEnd == 'url'){
			if(this.urlstart > 0){
				return '">' + string.substr(this.urlstart, offset-this.urlstart) + this.opentags.pop().etag;
			}
			return this.opentags.pop().etag;
		}
		else if(tagEnd == 'code' || tagEnd == 'pre')
			this.crlf2br = true;

		return this.opentags.pop().etag;
	}

return mstr;
}
}