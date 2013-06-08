$(function() {
    // проверка ие
    var ie6 = navigator.appVersion.indexOf("MSIE 6.") != -1;
    var ie7 = navigator.appVersion.indexOf("MSIE 7.") != -1;
    var ie8 = navigator.appVersion.indexOf("MSIE 8.") != -1;
    var ie9 = navigator.appVersion.indexOf("MSIE 9.") != -1;
    if (ie6 || ie7 || ie8 || ie9) {
        $("footer nav").columnize({
            width: 310,
            columns: 3
        });
    }

    // Меню 2-го уровня
    $(".sub_menu").each(function () {
        $(this).parent().eq(0).hover(function () {
            $($(this).children()[0]).addClass("active");
            $(".sub_menu:eq(0)", this).show();
        }, function () {
            $($(this).children()[0]).removeClass("active");
            $(".sub_menu:eq(0)", this).hide();
        });
    });

    //Выравневание под дорогой, если есть меню 3-го уровня
    var widthFirstCol = $($($(".under_road")[0]).children()[0]).width();
    var widthSecondCol = $($($(".under_road")[0]).children()[1]).width();
    if(widthFirstCol != 0) {
        $($(".under_road:eq(0)").children()[1]).width(widthSecondCol-widthFirstCol-30);
        $($(".under_road:eq(1)").children()[1]).width(widthSecondCol-widthFirstCol-30);
    }



    //Заменить в дороге "Главная"
    if(window.location.href.indexOf("/en/") != -1) {
        var road = $(".road");
        if(road != null) {
            $(road.children()[0]).text("Main").attr("title", "Main");
        }
        $(".logo").attr("href", "/en");
    }

    // Языковое меню
    $(".lang_menu .bottom").hover(function () {
        if($(".lang_menu .bottom a").text() == "Russian") {
            $(".lang_menu .bottom a").css({"paddingRight": "27px"});
        }
        $(".lang_menu .bottom a").css({"display": "block"});
    }, function () {
        $(".lang_menu .bottom a").css({"display": "none"});
    });



    //Карусель
    $('#carousel').carousel({
        loop: true,
        autoScroll: true,
        speed: "fast",
        pause: 5000,
        continuous: true,
        insertNextAction: function () {
            return $('<img class="next_slide" src="/design/images/slider/next_slide.png"/>').appendTo(this);
        },
        insertPrevAction: function () {
            return $('<img class="prev_slide" src="/design/images/slider/prev_slide.png"/>').appendTo(this);
        }
    });



    //Продукты перетаскивание
    $("#touch_carousel").touchCarousel({
        itemsPerPage: 1,
        scrollbar: false,
        snapToItems: false,
        scrollToLast: false,
        loopItems: false
    });

    //Продукция hint
    var timer = 0;
    $(".touchcarousel-item").each(function(i) {
        $(this).hover(function() {
            clearInterval(timer);
            $(".hint").stop();
            var shift = ($(this).width())*i - 100 + parseInt($(".touchcarousel-container").css("left").split("px")[0]);
            if($(window).width() < shift+156) {
                shift = $(window).width() - 166;
            } else if(shift < 0) {
                shift = 10;
            }
            $(".hint").css({"left": shift}).animate({opacity: 1},400);
            timer = setInterval(function() {
                $(".hint").animate({opacity: 0},400);
            }, 2000);
        }, function() {
            $(".hint").animate({opacity: 0},400);
        });
    });
});