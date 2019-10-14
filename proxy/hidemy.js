var url_name= "https://hidemy.name/ru/proxy-list/?country=UA&type=45#list";
var page = require('webpage').create();

page.open(url_name, function (status) {
    var content = page.content;
    console.log(content);
    phantom.exit();
});