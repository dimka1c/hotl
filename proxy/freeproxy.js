var url_name= "http://free-proxy.cz/ru/proxylist/country/UA/socks/ping/all";
var page = require('webpage').create();

page.settings.userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36';

page.open(url_name, function (status) {
    var content = page.content;
    console.log(content);
    phantom.exit();
});