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

    // Меню 3-го уровня
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



    //Смена языка
    if (Modernizr.localstorage) {
        $(".lang_menu a").click(function() {
            localStorage.setItem("language", $(this).text());
        });
    }

    //Заменить в дороге "Главная"
    if(window.location.href.indexOf("/en/") != -1) {
        var road = $(".road");
        if(road != null) {
            $(road.children()[0]).text("Main").attr("title", "Main");
        }
        $(".logo").attr("href", "/en");
    }
});

if (Modernizr.localstorage) {
    if(localStorage.getItem("language") == "En" && window.location.href.indexOf("/en/") == -1) {
        window.location.replace(window.location.origin+"/en");
    }
    if(localStorage.getItem("language") == "Ru" && window.location.href.indexOf("/ru/") == -1) {
        window.location.replace(window.location.origin+"/ru");
    }
}