!function ($) {
    $(function () {
        "use strict";

        // Functions
        $.fn.adaptTopMargin = function(sourceElement, targetElement, padding) {

            if(!padding) {
                padding = 0;
            }

            $(targetElement).css('margin-top', $(sourceElement).height() + padding + 'px');
        };

        // onResize
        $(window).resize(function() {
            $.fn.adaptTopMargin('div.header', 'li.header', 35);
            $.fn.adaptTopMargin('li.header', 'li.person.first', $('div.header').height() + 55);
        });

        // onLoad
        $.fn.adaptTopMargin('div.header', 'li.header', 35);
        $.fn.adaptTopMargin('li.header', 'li.person.first', $('div.header').height() + 55);
    })
}(window.jQuery);