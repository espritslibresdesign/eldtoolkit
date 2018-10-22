(function ($) {
    jQuery("span.email").each(
            function (i) {
                this.innerHTML = this.innerHTML.replace(/^(^[\w+%-.]+) \[.+\] ([\w.-]+) \[.+\] ([a-zA-Z]{2,4})(.*)$/, '<a href="mailto:$1@$2.$3">$1@$2.$3</a>');
            });
    jQuery("span.emailto").each(
            function (i) {
                var a = this.innerHTML.split(': ');
                this.innerHTML = a[1].replace(/^(^[\w+%-.]+) \[.+\] ([\w.-]+) \[.+\] ([a-zA-Z]{2,4})(.*)$/, '<a href="mailto:%22' + encodeURI(a[0]) + '%22%20%3c$1@$2.$3%3e">' + a[0] + '</a>');
            });
})(jQuery);