var curent_action_confirm = "";
var shouldReloadTree=false;

//Открывает окно для редактирования или создания нового действия модуля
function show_action_edit(strlink, name)
{
    var popup=$("#popup_div");
    popup.html('');
    popup.load(start_interface.global_link + strlink);
    popup.dialog({
        resizable: true,
        height:400,
        width:600,
        zIndex:1000,
        title:name,
        buttons:{},
        modal: true,
        show: "scale",
        hide: "scale",
        close: function() {update_action_list(); }

    });
}

function santaUpdateRegion(regid, loadFrom,opts)
{
    if (typeof  opts === "undefined")
        opts={};
    opts.url=loadFrom;
    opts.error=function(jqXHR, textStatus, errorThrown) {
        var msg='';
        if (textStatus)
            msg+=textStatus;
        if (errorThrown)
            msg+=" "+errorThrown;
        reg.html(msg)
    };
    opts.success=function(result) {
        reg.empty().html(result);
        if (regid=='content')
            onMainContentLoaded();
    };
    $('#popup_div').css('display','none');
    var reg=$("#"+regid);
    reg.html('<span id="contentLoading">Loading...</span>');
    $.ajax(opts);
}

function santaUpdateRegionSynchron(regid, loadFrom)
{
    santaUpdateRegion(regid, loadFrom,{async:false});
}

/**
 * Эмитирует клик по левому пункту меню (с подтверждением).
 *
 * Аналогично jspub_click, но перед тем как осуществить переход выводится сообщение
 * и переход осуществиться только в том случае, если пользователь подтвердит это
 * сообщение (тоесть ответит "Yes")
 * @dialog_action URL, на который осуществляем переход
 * @dialog_message Сообщение, которое нужно подтвердить, прежде чем будет осуществлён переход
 */

function jspub_confirm(dialog_action, dialog_message)
{
    //curent_action_confirm = dialog_action;
    //$( "#ext_layout:ui-dialog" ).dialog( "destroy" );
    var popup=$("#popup_msg_div");
    popup.html('<p>'+dialog_message+'</p>');
    popup.dialog({
        resizable: false,
        height:180,
        modal: true,
        buttons: {
            "Yes": function() {
                start_interface.link_go(dialog_action);
                $(this).dialog( "close" );
            },
            "No": function() {
                $(this).dialog( "close" );
            }
        }
    });
    return false;
}

/**
 * Сохраняем значение в куках пользователя под определённым именем
 *
 */
function jspub_cookie_set(cookieName, cookieValue, expires, path, domain, secure)
{
    document.cookie =
        encodeURIComponent(cookieName) + '=' + encodeURIComponent(cookieValue)
            + (expires ? '; expires=' + expires.toGMTString() : '')
            + (path ? '; path=' + path : '')
            + (domain ? '; domain=' + domain : '')
            + (secure ? '; secure' : '');
}

/**
 * Возвращает из Кук значение по его имени
 *
 */

function jspub_cookie_get(cookieName)
{
    var cookieValue = '';
    var posName = document.cookie.indexOf(encodeURIComponent(cookieName) + '=');

    if (posName != -1)
    {
        var posValue = posName + (encodeURIComponent(cookieName) + '=').length;
        var endPos = document.cookie.indexOf(';', posValue);
        if (endPos != -1)
            cookieValue = decodeURIComponent(document.cookie.substring(posValue, endPos));
        else
            cookieValue = decodeURIComponent(document.cookie.substring(posValue));
    }
    return (cookieValue);
}



/**
 * Эмитирует клик по левому пункту меню.
 *
 * Функция используется для отправки любых GET (и только их) запросов модулю
 * в качестве параметра используется URL для перехода, который должен начинаться с
 * идентификатора пугкта левого меню.
 * @param lnk URL, на который осуществляем переход
 */

function jspub_click(lnk)
{
    start_interface.link_go(lnk);
    return false;
}

/**
 * Включает или выключает элементы HTML элементы административного интерфейса.
 *
 * Функция производит выключение (или включение) заданных элметов в зависимости от того
 * отмечена или не отмечена галочка, переданная в качестве переданного параметра.
 * @elem1ID Передаётся идентификатор чекбокса
 * @elem2ID Идентификатор объекта, который нужно включить или выключить.
 */
function jspub_disabled_change(elem1ID,elem2ID)
{
    var el1=$('#'+elem1ID),el2=$('#'+elem2ID);

    if(el2.is('select'))
    {
        if (el1.attr('checked'))
            el2.selectmenu('disable');
        else
            el2.selectmenu('enable');
    }
    else
    {
        if (el1.attr("checked"))
            el2.attr("disabled",true);
        else
            el2.removeAttr("disabled");
    }
}

function santaFormSubmitSuccess(responseText)
{
    if (parseInt(responseText.errore_count) > 0)
    {
        santaShowPopupHint("Error", responseText.errore_text,0);
        return;
    }
    //Значит ошибок небыло и нужно вывести сообщение с результатом...
    var msg_label = responseText.result_label;
    var msg = responseText.result_message;
    var timeout=3;//по-умолчанию таймаут 3 секунды
    if (responseText.hasOwnProperty('msg_timeout'))
        timeout=responseText.msg_timeout;
    if (msg != "")
        santaShowPopupHint(msg_label, msg,timeout*1000);
    //...и возможно перейти на другой пункт меню
    var id_link =  responseText.redirect;
    if (id_link != "")
        jspub_click(id_link);
}

function santaFormSubmit(formID,data)
{
    for (var i in CKEDITOR.instances)
    {
        CKEDITOR.instances[i].destroy();
    }
    if (typeof  data === "undefined")
        data={};
    var foptions={
        success: santaFormSubmitSuccess,
        dataType:  'json',
        data: data
    };
    $('#'+formID).ajaxSubmit(foptions);
}

/**
 * Производит отправку формы
 *
 * @param formID ID HTML объекта FORM, которую отправляем
 * @param url URL, на который осуществляем отсылку
 */
function jspub_form_submit(formID, url)
{
    //different variants: http://stackoverflow.com/questions/169506/obtain-form-input-fields-using-jquery
    var parameters = {};
    $('#'+formID+" :input").each(function(){
        if ($(this).attr('type')=="checkbox")
        {
            if ($(this).attr('checked'))
                parameters[this.name]=1;
            else
                parameters[this.name]=0;
        }
        else
            parameters[this.name] = $(this).val();
    });

    //Теперь непосредственно отсылка формы
    $.post(url, parameters,  function(data){
        try
        {
            //Обрабатываем ответ
            var post_res=jQuery.parseJSON(data);
            //сначала проверка на наличие ошибок
            if (parseInt(post_res.errore_count) > 0)
            {
                santaShowPopupHint("Error", post_res.errore_text,0);
            }
            else
            {
                //Значит ошибок небыло и нужно вывести сообщение с результатом...
                var msg_label = post_res.result_label;
                var msg = post_res.result_message;
                var timeout=3;//по-умолчанию таймаут 3 секунды
                if (post_res.hasOwnProperty('msg_timeout'))
                    timeout=post_res.msg_timeout;
                if (msg != "")
                    santaShowPopupHint(msg_label, msg,timeout*1000);
                //...и возможно перейти на другой пункт меню
                var id_link =  post_res.redirect;
                if (id_link != "")
                    jspub_click(id_link);
            }
        }
        catch (e)
        {
            santaShowPopupHint('Error', 'Form not submitted:'+e,3000);
        }


    });

    return false;
}

//отображает всплывающее сообщение
function santaShowPopupHint(header, text,timeout)
{
    var popup=$("#popup_msg_div");
    popup.html('<p>'+text+'</p>');
    //$("#popup_msg_div").dialog( "destroy" );
    popup.dialog({
        resizable: false,
        height:180,
        width:300,
        modal: true,
        title: header,
        buttons: {
            "OK": function() {
                timeout = 0;
                $(this).dialog( "close" );
            }
        },
        show: "scale",
        hide: "scale"//"explode"
    });
    //autoclose
    if (timeout!=0) {
        setTimeout(function(){
            if (timeout!=0) {
                $("#popup_msg_div").dialog("close");
            }
        }, timeout);
    }
}

//здесь хранится текущая выбранная страница во всплывающем диве
var structSelectedPage='';
//Функция открывает слой для выбора страницы сайта
//во всех свойствах с типом (страница сайта)
function showPageSelector(fieldIDorHandler)
{
    var popup=$('#popup_sitemap_div');
    popup.html('');
    popup.load("index.php?action=select_page", function(response, status, xhr) {
        if (status == "error")
            $("#popup_sitemap_div").html("Load error: " + xhr.status + " " + xhr.statusText);
    });

    $("#popup_sitemap_div").dialog({
        resizable: true,
        height:400,
        width:250,
        zIndex:20000,
        title: 'Выбор страницы',
        buttons: {},
        modal: true
        //,close: function() {update_action_list(); }

    });
    structSelectedPage = fieldIDorHandler;
    //start_interface.dialog.addKeyListener(27, start_interface.dialog.hide, start_interface.dialog);
    return true;
}



//Открывает новое окно для загрузки туда редактора контента
//Используется в свойствах страницы, для вызова редактора контента для конкретной
function go_edit_content(name_file, no_redactor)
{
    var name_edit = 'edit_';
    var left=parseInt(100);
    var top=parseInt(1);
    var width = parseInt(screen.width / 2 );
    var height = parseInt(screen.height / 2);
    if (width < 700)
        width = 700;
    var newWin;
    if (no_redactor)
        newWin=window.open('/admin/index.php?action=edit_content&file='+name_file+'&edit='+name_edit+'&no_redactor=1', '_blank', 'alwaysRaised=yes,dependent=yes,resizable=yes,titlebar=no,toolbar=no,menubar=no,location=no,status=no,scrollbars=no,left=' + left + ',top=' + top + ',width=' + width +',height='+height,'Content');
    else
        newWin=window.open('/admin/index.php?action=edit_content&file='+name_file+'&edit='+name_edit, '_blank', 'alwaysRaised=yes,dependent=yes,resizable=yes,titlebar=no,toolbar=no,menubar=no,location=no,status=no,scrollbars=no,left=' + left + ',top=' + top + ',width=' + width +',height='+height,'Content');
    newWin.focus();
}


//Скрывает или показывает иконки  редактирования контента и html у определённой метки
function show_icons_go_edit_content(elID)
{
    var sele=$('#sel_modul_ext_'+elID);
    var iblock=$('#html_icons_block_'+elID);
    if (!sele.attr("disabled") && sele.val()==1)
        iblock.css('display','inline');
    else
        iblock.css('display','none');
}

//Обновляет список выбранных текущих действий при редактировании настроек модуля
function update_action_list()
{
    $("#actions_list_table").load(start_interface.global_link + 'action_update_list', function(response, status, xhr) {
        $('#module_actions_container').css("display","block");
        $('#page_tabs').find('a[href=#mod_action_list]').parent().show();
        if (status == "error")
            $("#actions_list_table").html("Load error: " + xhr.status + " " + xhr.statusText);
    });
}




//Функция используется только интерфейсом подсистемы статистики, в
//дальнейшем нужно перейти на стандартную
function admin_form_submit(url, id_insert, formID)
{
    var postArr = $('#'+formID).serializeArray();
    $.post(url, postArr,  function(data){
        $("#"+id_insert).html(data);
    });
}

//Функция выполняет подсвтеку строки с действием в настройках модуля, пока не уневерсальна
//Из ней нужно сделать функцию, которой смогли бы пользоваться все модули.
//used in admin_modules.html only
function mouse_select_element(obj, del_class)
{
    if (del_class)
        $(obj).removeClass('table_action_select');
    else
        $(obj).addClass('table_action_select');
}


//Обновляет левое меню, в случаях когда необходимо перестроить какой-то блок, или обновить какие-то данные
function update_left_menu(url)
{
    santaUpdateRegion('west', "index.php?action=get_left_menu&"+url);
}



function form_submit_include_content_auto()
{
    for (var i in CKEDITOR.instances)
    {
        CKEDITOR.instances[i].destroy();
    }
    return false;
}

//Вызвается в старых модулях, для сабмита формы с редактором контента
function form_submit_include_content(name_area)
{
    if (!name_area)
        name_area = 'content_html';
    //Закрываем редактор контента в этой области, после чего всё и сабмитится
    var inst = CKEDITOR.instances[name_area];
    if (typeof inst != 'undefined')
        inst.destroy();
    return true;
}


// для редактора контента, встроенного в страницу
//+для редактора в отдельном окне в структуре
function start_include_content(name_area,is_separate_window)
{
    if (!name_area)
        name_area = 'content_html';
    var config =
    {
        contentsCss : '/design/styles/screen.css',
        skin : 'moono',
        autoUpdateElement:true,
        width: '100%',
        filebrowserBrowseUrl : '/components/html_editor/ckeditor/plugins/kcfinder/browse.php?type=files',
        filebrowserImageBrowseUrl : '/components/html_editor/ckeditor/plugins/kcfinder/browse.php?type=images',
        filebrowserFlashBrowseUrl : '/components/html_editor/ckeditor/plugins/kcfinder/browse.php?type=files',
        filebrowserUploadUrl : '/components/html_editor/ckeditor/plugins/kcfinder/upload.php?type=files',
        filebrowserImageUploadUrl : '/components/html_editor/ckeditor/plugins/kcfinder/upload.php?type=images',
        filebrowserFlashUploadUrl : '/components/html_editor/ckeditor/plugins/kcfinder/upload.php?type=files',
        LinkBrowserWindowHeight:440,
        ImageBrowserWindowHeight:440,
        FlashBrowserWindowHeight:440,
        LinkUpload:false,
        ImageUpload:false,
        FlashUpload:false,
        language:'ru',
        toolbar: [
            ['AjaxSave','Source','Cut','Copy','Paste','PasteText','PasteFromWord'],
            ['Image','Flash','Table','HorizontalRule','SpecialChar','PageBreak'],
            ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote','CreateDiv'],
            ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],

            '/',
            ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],

            ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
            ['Link','Unlink','Anchor'],

           // '/',
            ['Styles','Format','Font','FontSize'],
            ['TextColor','BGColor'],
            ['Maximize', 'ShowBlocks']
        ]
    };
    if (is_separate_window)
        config["extraPlugins"] = 'ajaxsave';
    /*убираем предыдущий instance, если есть
     var inst = CKEDITOR.instances[name_area];
     if (inst)
     inst.destroy(true);
     $('#'+name_area).ckeditor(config);*/
    CKEDITOR.replace(name_area, config);

    CKEDITOR.on('dialogDefinition', function( ev )
    {
        var dialogName = ev.data.name;
        var dialogDefinition = ev.data.definition;
        if ( dialogName != 'link' )
            return;
        var infoTab = dialogDefinition.getContents('info');
        infoTab.add(
            {
                type : 'vbox',
                id : 'buttonPageSelector',
                children : [
                    {
                        type : 'button',
                        id : 'browse',
                        label: 'Выбрать из структуры',
                        onClick : function (e){
                            showPageSelector(function(el){
                                var dialog = CKEDITOR.dialog.getCurrent();
                                dialog.getContentElement('info', 'linkType').setValue('');
                                dialog.getContentElement('info', 'url').setValue('/'+el+'.html');
                            });
                        }
                    }]
            }
        );
    });
    return true;
}

//Добавляет к строкам функцию по их образанию
String.prototype.ellipse = function(maxLength){
    if(this.length > maxLength){
        return this.substr(0, maxLength-3) + '...';
    }
    return this;
};


/* Russian (UTF-8) initialisation for the jQuery UI date picker plugin. */
/* Written by Andrew Stromnov (stromnov@gmail.com). */
jQuery(function($){
    $.datepicker.regional['ru'] = {
        closeText: 'Закрыть',
        prevText: '&#x3c;Пред',
        nextText: 'След&#x3e;',
        currentText: 'Сегодня',
        monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь',
            'Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
        monthNamesShort: ['Янв','Фев','Мар','Апр','Май','Июн',
            'Июл','Авг','Сен','Окт','Ноя','Дек'],
        dayNames: ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'],
        dayNamesShort: ['вск','пнд','втр','срд','чтв','птн','сбт'],
        dayNamesMin: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
        weekHeader: 'Не',
        dateFormat: 'dd.mm.yy',
        firstDay: 1,
        isRTL: false,
        showMonthAfterYear: false,
        yearSuffix: ''};
    $.datepicker.setDefaults($.datepicker.regional['ru']);
});

//Отдельная функция
var pageInfoblocksLoaded=0;

//вызывается когда загружен блок данных для страницы
function onPageInfoblockLoad()
{
    pageInfoblocksLoaded++;
    if (pageInfoblocksLoaded==2)
    {//если загрузилось всё - показываем

        $('#content_header').show();
        $('#page_tabs').parent().tabs({ selected: 0 });
        $('#page_container').css({'display':'block'});
        $("#contentLoading").remove();
    }

}

function onMainContentLoaded()
{
    var mc=$("#content_container");
    mc.find("button").button();
    mc.find("input:button").button();
    mc.find("input:submit").button();
    mc.find('select').selectmenu({
        style:'dropdown',
        maxHeight: 200
    });
}

function beforeStructureChange()
{
    $('#content_header').before('<span id="contentLoading">Loading, Please wait..</span>');
    $('#structure_page_name').html('').parent().hide();
    $('#page_container').css({'display':'none'});
    $('#contentLoading').show();
}

function structure_tree_click_node(url)
{
    //Самое простое - заполним поля с основными данными страницами новыми значениями.
    //Сначала надо получить эти данные
    var url_link_main = start_interface.global_link + url + "&type=get_main_param";//index.php?action=set_left_menu&leftmenu=view&id=about2&type=get_main_param

    beforeStructureChange();

    pageInfoblocksLoaded=0;
    //Загрузка данных о самой странице и свойствах модулей
    $.get(url_link_main, function (data)
    {
        var comboStore = jQuery.parseJSON(data);
        if (comboStore != null)
            set_propertes_main(comboStore);

    });
    run_update_metki(url);
}

function run_update_metki(url)
{
    var url_link_metk = start_interface.global_link + url + "&type=get_metka";
    //Второй, отдельный запрос, должен загрузить информацию о метках для выбранного шаблона
    $.get(url_link_metk, function (data)
    {
        var comboStore = jQuery.parseJSON(data);
        if (comboStore != null)
            set_metki(comboStore);
    });

}
//А теперь вложенная функция, чтобы иметь доступ ко всем переменным
//созданым при инсталяции
function set_propertes_main(d)
{
    // название страницы
    $('#structure_page_name').html('"<a href="/'+d.id_curent_page+'.html" class="link2page" target="_blank" title="Откроется в новом окне">'+d.caption+'</a>"');

    //Проставляем поля формы
    $("#fieldPageName").val(d.caption);
    $("#fieldPageTitle").val(d.name_title);
    $("#fieldPageURL").val(d.link_other_page);
    $("#fieldPageID").val(d.id_curent_page);
    $("#fieldPageOnlyAuth").val(d.only_auth);
    $('select#fieldPageTemplate').selectmenu("value",d.page_template);

    var flag_tpl= $('#flag_template'),fieldPageTpl=$('#fieldPageTemplate'),el,el_nasled;
    if (d.template_naslednoe)
    {
        flag_tpl.attr("checked", "checked");
        fieldPageTpl.selectmenu('disable');
    }
    else
    {
        flag_tpl.removeAttr('checked');
        fieldPageTpl.selectmenu('enable');
    }

    if (d.page_is_main)
        flag_tpl.attr("disabled", true);
    else
        flag_tpl.removeAttr("disabled");

    //Заполняем остальные вспомогательные элементы
    $('#main_label_name_page').html(d.caption);
    $('#link_for_preview').attr('href', d.link_for_preview);

    //проставляем свойства страницы для модулей
    for (var i = 0; i < d.page_prop.length; i++)
    {
        el=$('#' + d.page_prop[i].name);
        el_nasled=$('#' + d.page_prop[i].name_nasled);
        if(el.is('select'))
            el.selectmenu("value",d.page_prop[i].value);
        else
            el.val(d.page_prop[i].value);
        if (d.page_prop[i].naslednoe)//чекбокс наследования если надо
        {
            el_nasled.attr("checked", "checked");
            if(el.is('select'))
                el.selectmenu("disable");
            else
                el.attr("disabled", true);
        }
        else
        {
            el_nasled.removeAttr('checked');
            if(el.is('select'))
                el.selectmenu("enable");
            else
                el.removeAttr("disabled");
        }
        if (d.page_is_main)//если главная - отключим чекбокс
            el_nasled.attr("disabled", true);
        else
            el_nasled.removeAttr("disabled");
    }
    onPageInfoblockLoad();
}
var metkiCount=0;
var arr_link_content = [];
function set_metki(d)
{
    var ctable=$('#table_metki_content'), table_metki_content= "",hasPostprocessors = !is_empty(postProcessors),str_content;
    metkiCount = d.length;
    arr_link_content = [];
    for (var i = 0; i < d.length; i++)
    {
        str_content = '<tr>';
        //Имя метки со скрытым инпутом, куда пишется непосредственное значение
        str_content += '<td class="metka_name">';
        str_content += '<label for="flag_metka_' + i + '" class="struct_page_label">' + d[i].name + '</label>';
        str_content += '</td>';
        //Галочка наследования
        str_content += '<td class="nasled">';
        str_content += '<input type="checkbox" name="' + d[i].name + '" id="flag_metka_' + i + '" data-elid="'+i+'">';

        str_content += '<td>';
        str_content += '<select id="sel_modul_ext_' + i + '" class="select_module_action" data-elid="'+i+'">';
        str_content += buildMetkaActionsSelect();
        str_content += '</select>';
        str_content += '</td>';

        str_content += '<td>';
        str_content += '<span style="height: 26px; display: inline;float: left;" id="html_icons_block_'+i+'"><img class="edit_icon" title="Визуальный редактор контента"  src="/admin/templates/default/images/icon_edit.gif" onclick="go_edit_content(arr_link_content[' + i + '], false)"><img class="edit_icon" title="HTML редактор контента" src="/admin/templates/default/images/icon_edit_textarea.gif"  onclick="go_edit_content(arr_link_content[' + i + '], true)"></span>';
        str_content += '</td>';

        if (hasPostprocessors)
        {
            str_content += '<td><select id="sel_label_postprocessor_' + i + '" class="select_postprocessor">';
            str_content += buildMetkaPostprocessorsSelect();
            str_content += '</select></td>';
        }
        str_content += '</tr>';
        table_metki_content+=str_content;
    }
    ctable.empty().html(table_metki_content);
    //теперь отдельным циклом повесим обработчики
    var flag_nasled,sel_modul_ext,sel_pp;
    for (i = 0; i < d.length; i++)
    {
        sel_modul_ext=$("#sel_modul_ext_" + i);
        sel_pp=$("#sel_label_postprocessor_" + i);
        //при изменении селекта с действиями скроем или покажем иконки редактора контента
        sel_modul_ext.change(function (){
            var elID = $(this).attr('data-elid');
            show_icons_go_edit_content(elID);
        });

        sel_modul_ext.val(d[i].id_action);//поставим в селект выбранное значение
        if (d[i].postprocessors!=null && d[i].postprocessors.length>0)
            sel_pp.val(d[i].postprocessors[0]);//поставим в селект выбранное значение, пока просто первый элемент, в дальнейшем возможно будет chaining из потспроцессоров

        flag_nasled=$("#flag_metka_" + i);
        // если унаследовано - отключим селект и поставим чекбокс наследования
        if (d[i].naslednoe)
        {
            flag_nasled.attr("checked", "checked");
            sel_modul_ext.attr("disabled", true);
            sel_pp.attr("disabled", true);
        }

        //повесим обработчик на клик по чекбоксу наследования
        flag_nasled.change(function () {
            var elID = $(this).attr('data-elid');
            var sel_modul_ext=$("#sel_modul_ext_"+elID);
            var sel_label_pp=$("#sel_label_postprocessor_"+elID);
            var disAttr = sel_modul_ext.attr("disabled");
            var disAttrPP = sel_label_pp.attr("disabled");

            if (typeof  disAttr!== 'undefined' && disAttr!=false)
            {
                sel_modul_ext.removeAttr("disabled");
                // и покажем иконки редактирования, если требуется
                show_icons_go_edit_content(elID);
            }
            else
                sel_modul_ext.attr("disabled",true);

            if (typeof  disAttrPP!== 'undefined' && disAttrPP!=false)
                sel_label_pp.removeAttr("disabled");
            else
                sel_label_pp.attr("disabled",true);

            jspub_disabled_change(this.id, 'sel_modul_ext_' +elID);

            //иконки для визуального редактора и html
            show_icons_go_edit_content(elID);

            if (hasPostprocessors)
                jspub_disabled_change(this.id, 'sel_label_postprocessor_' +elID);
        });
        // прячем иконки
        show_icons_go_edit_content(i);
        //сохраняем название файла для редактирования контента
        arr_link_content[i] = d[i].file_edit;
    }

    //украсим селекты с помощью selectmenu
    ctable.find('select.select_module_action').selectmenu({
        style:'dropdown'
        ,maxHeight:200
    });
    ctable.find('select.select_postprocessor').selectmenu({
        style:'dropdown'
        ,maxHeight:200
        ,width:150
    });
    onPageInfoblockLoad();
}

//via http://stackoverflow.com/questions/4994201/is-object-empty
var hasOwnProperty = Object.prototype.hasOwnProperty;
function is_empty(obj)
{
    // Assume if it has a length property with a non-zero value
    // that that property is correct.
    if (obj.length && obj.length > 0)
        return false;
    for (var key in obj)
    {
        if (hasOwnProperty.call(obj, key))
            return false;
    }
    return true;
}

var postProcessors = {};
function buildMetkaPostprocessorsSelect()
{
    var res="<option value=''></option>";//первый элемент - пустой (нет постпроцессора)
    for(var key in postProcessors)
    {
        res+="<option value='"+key+"'>"+postProcessors[key]+"</option>";
    }
    return res;
}

var allModulesActions = [];
function buildMetkaActionsSelect()
{
    var elem, res="",lastOptGroup="";
    for (var j = 0; j < allModulesActions.length; j++)
    {
        elem = allModulesActions[j];
        if (!elem[0] && !elem[1]) //Действие не выбранно
        {
            res+="<option value=''>"+elem[2]+"</option>";
            continue;
        }

        if (elem[0]) //optgroup
        {
            if (lastOptGroup)
                res+=lastOptGroup+"</optgroup>";
            lastOptGroup="<optgroup label='"+elem[0]+"'>";
        }
        else
            lastOptGroup+="<option value='"+elem[1]+"'>"+elem[2]+"</option>";
    }
    if (lastOptGroup)
        res+=lastOptGroup+"</optgroup>";
    return res;

}


/*!
 * Autogrow Textarea Plugin Version v2.0
 * http://www.technoreply.com/autogrow-textarea-plugin-version-2-0
 *
 * Copyright 2011, Jevin O. Sewaruth
 *
 * Date: March 13, 2011
 */
jQuery.fn.autoGrow = function(){
    return this.each(function(){
        // Variables
        var colsDefault = this.cols;
        var rowsDefault = this.rows;

        //Functions
        var grow = function() {
            growByRef(this);
        };

        var growByRef = function(obj) {
            var linesCount = 0;
            var lines = obj.value.split('\n');

            for (var i=lines.length-1; i>=0; --i)
            {
                linesCount += Math.floor((lines[i].length / colsDefault) + 1);
            }

            if (linesCount >= rowsDefault)
                obj.rows = linesCount + 1;
            else
                obj.rows = rowsDefault;
        };

        var characterWidth = function (obj){
            var characterWidth,temp1,temp2;
            var tempCols = obj.cols;
            obj.cols = 1;
            temp1 = obj.offsetWidth;
            obj.cols = 2;
            temp2 = obj.offsetWidth;
            characterWidth = temp2 - temp1;
            obj.cols = tempCols;
            return characterWidth;
        };

        // Manipulations
        this.style.width = "auto";
        this.style.height = "auto";
        this.style.overflow = "hidden";
        this.style.width = ((characterWidth(this) * this.cols) + 6) + "px";
        this.onkeyup = grow;
        this.onfocus = grow;
        this.onblur = grow;
        growByRef(this);
    });
};


function saveStructForm(url,isFull,modulesProps)
{
    var postArr = {page_properties: {
        page_template:$('select#fieldPageTemplate').selectmenu("value"),
        page_title:$("#fieldPageTitle").val(),
        page_name:$("#fieldPageName").val(),
        page_id:$("#fieldPageID").val(),
        page_url:$("#fieldPageURL").val()
    }};

    if ($('#flag_template').attr('checked'))
        postArr.page_properties.flag_template='on';
    if ($('#fieldPageOnlyAuth').attr('checked'))
        postArr.page_properties.only_auth='on';

    if (isFull)
    {
        postArr.properties_cb = {};
        postArr.properties = {};
        postArr.page_inheritance = {};
        postArr.page_modules = {};
        postArr.page_postprocessors = {};
        var elem,elemName;
        //свойства модулей
        for (var j=0;j<modulesProps.length;j++)
        {
            elem = modulesProps[j];
            if ($('#'+elem[0]).attr('checked'))
                postArr.properties_cb[elem[0]] = 'on';
            else
            {
                elemName = String(elem[1]).substr(4);
                postArr.properties[elemName] = $('#'+elem[1]).val();
            }
        }
        //метки
        for (j=0; j<metkiCount;j++)
        {
            elemName = $('#flag_metka_'+j).attr('name');
            if ($('#flag_metka_'+j).attr('checked'))
            { //наследное
                postArr.page_inheritance[elemName]='on';
            }
            else
            {
                postArr.page_modules[elemName]=$('#sel_modul_ext_'+j).val();
                postArr.page_postprocessors[elemName]=[$('#sel_label_postprocessor_'+j).val()];
            }
        }
    }
    $.post(url, postArr,  function(data){
        //Обрабатываем ответ
        var post_res=jQuery.parseJSON(data);
        if (post_res!=null)
        {
            //сначала проверка на наличие ошибок
            if (post_res.success)
            {
                santaShowPopupHint("Info",post_res.info,1000);
                //обновим всё дерево, если требуется
                if (shouldReloadTree)
                    santaUpdateRegion('west','index.php?action=get_left_menu');
                else if(!isFull)
                    run_update_metki('view');//обновляем метки для нового шаблона
            }
            else
                santaShowPopupHint("Error", post_res.info,0);
        }
    });


}


