/**
 * This is a project designed to get around sites using Cloudflare's "I'm under attack" mode.
 * Using the PhantomJS headless browser, it queries a site given to it as the second parameter,
 *  waits six seconds and returns the cookies required to continue using this site.  With this,
 *  it is possible to automate scrapers or spiders that would otherwise be thwarted by Cloudflare's
 *  anti-bot protection.
 *
 * To run this: phantomjs cloudflare-challenge.js http://www.example.org/
 *
 * Copyright © 2015 by Alex Wilson <antoligy@antoligy.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all
 * copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND ISC DISCLAIMS ALL WARRANTIES WITH
 * REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL ISC BE LIABLE FOR ANY
 * SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT
 * OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */


/**
 * Namespaced object.
 * @type {Object}
 */
var antoligy = antoligy || {};

/**
 * Simple wrapper to retrieve Cloudflare's 'solved' cookie.
 * @type {Object}
 */
antoligy.cloudflareChallenge = {

    webpage:	false,
    system:		false,
    page:		false,
    url:		false,
    userAgent:	false,
    post:       false,
    headers:    false,

    parseQuery: function(queryString) {
        var query = {};
        var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
        }

        return query;
    },

    /**
     * Initiate object.
     */
    init: function() {
        this.webpage	= require('webpage');
        this.system		= require('system');
        this.page		= this.webpage.create();
        this.url		= this.system.args[1];
        if (this.system.args[2]) {
            this.post = this.system.args[2];
        }
        if (this.system.args[3]) {
            this.headers = this.parseQuery(this.system.args[3]);
        }
        this.userAgent	= 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0';
        this.timeout	= 6000;
    },

    /**
     * "Solve" Cloudflare's challenge using PhantomJS's engine.
     * @return {String} JSON containing our cookies.
     */
    solve: function() {
        var self = this;
        this.page.settings.userAgent = this.userAgent;
        if (this.headers) {
            this.page.customHeaders = this.headers;
        }

        var handle = function(status) {
            setTimeout(function() {
                console.log(JSON.stringify(phantom.cookies));
                phantom.exit();
            }, self.timeout);
        };
        if (this.post) {
            this.page.open(this.url, 'post', this.post, handle);
        } else {
            this.page.open(this.url, handle);
        }
    }

}

/**
 * In order to carry on making requests, both user agent and IP address must what is returned here.
 */
antoligy.cloudflareChallenge.init();
antoligy.cloudflareChallenge.solve();
