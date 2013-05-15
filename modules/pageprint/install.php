<?php
/**
 * Модуль мозданиея печатной версии
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0
 */


/**
 * Класс с параметрами инсталяции/деинсталяции модуля Example
 *
 * Важно отметить, что имя класса должно быть уникальным у всех моудей,
 * поэтому для удобства рекомендуем использовать в качетсве имени класса
 * следующую строчку <i><идентификатор модуля>_install</i>
 */
class pageprint_install extends install_modules
{
    /**
     * Класс может содержать любые внтренние переменные которые могут быть использованы
     * в методах инсталяции
     *
     * @var string
     */
	var $path_pictures = "content/images/news";

	/**
	 * Инсталяция базового модуля
	 *
	 * Вызывается один раз при инсталяции базового модуля в систему SantaFox
	 * В качестве параметра передаётся идентификатор базового модуля. Вам как,
	 * разработчику модуля заранее известен базовый идентификатор (так какон
	 * придумывается вами и должен быть уникальным) и вы непосредственно
	 * использоват его в теле модуля. Однако мы рекомендуем пользоваться параметром
	 * метода, так как в случае изменения индентификатора базового модуля, вы сможете
	 * избежать потенциальных ошибок.
	 *
	 * Следует помнить, что этот метод будет вызван ОДИН раз, в момент инсталяции базового
	 * модуля
	 * @param string $id_module Идентификатор создаваемого базового модуля
     * @param boolean $reinstall переинсталяция?
	 */
	function install($id_module, $reinstall = false)
	{
	}

	/**
     * Деинсталяция базового модуля
     *
     * Метод вызывается при деинтсаляции базового модуля.
     *
     * Рекомендуем использовать параметр метода, с тем что бы правильно идентифицировать
     * удаляемые объекты
     * @param string $id_module Идентификатор удаляемого базового модуля
     */

	function uninstall($id_module)
	{
	}


	/**
     * Инсталяция дочернего модуля
     *
     * Методы вызывается, при инсталяции каждого дочернего модуля.
     * @param string $id_module Идентификатор вновь создаваемого дочернего модуля
     * @param boolean $reinstall переинсталяция?
     */
	function install_children($id_module, $reinstall = false)
	{
	}

	/**
	 * Деинсталяция дочернего модуля
	 *
	 * Методы вызывается, при деинсталяции каждого дочернего модуля.
    *
    * @param string $id_module ID удоляемого дочернего модуля
    */
	function uninstall_children($id_module)
	{
	}
}

//Дальше идёт непосредственное описание свойств модуля, свойств модуля к страницам
//публичных методов а также предустановок интсаляции

/*
    Создаём экземпляр выше описанного класса <b>install</b>. Следует учесть
    что имя переменной должно не подлежит изменению.

*/
$install = new pageprint_install();

/*
    Задаёт имя языковой переменной хранящей название модуля
*/
$install->set_name('[#pageprint_modul_base_name#]');

/*
    Задаётся уникальный идентификатор базового модуля. Этот идентификатор
    должен соответствовать имени папки в которой лежит модуль а так же имени
    основного класса (<i><идентификатор>.class.php</i>)
*/
$install->set_id_modul('pageprint');

/*
    Устанавливает тип административного интерфейса, который необходим модулю
    для управления своими данными. Варианты занчений:
    0 - модули не имеют административного интерфейса (АИ)
	1 - модули имеют один АИ, на базовый модуль
	2 - каждый дочерний модуля имеет свою админку
*/
$install->set_admin_interface(0);

/*
    Здесь и дальше, для создания совйстив используюся специальные классы
    имена которых начинаются на <i>properties_</i>. На данный момент этих
    классов 6:
    <code>
        $p = new properties_checkbox();
        $p = new properties_date();
        $p = new properties_file();
        $p = new properties_pagesite();
        $p = new properties_select();
        $p = new properties_string();
    </code>

    Каждое своёство имеет свой интерфейс для ввода значений и немного
    отличаются по набору доступных методов. Более подробно смотри в документации
    по разарботке модулей

    Зоздав одно свойство и поределив ех характеристики вам необходимо указать
    инсталятору где (как) это свойство будет использоваться. Как свойство модуля,
    как свойства модуля к страницк или как свойство действия
*/

/*
    Дальше описываются необходимые свойства модуля.

    Добавление свойства как свойства модуля происходит с помощью метода
    <i>$install->add_modul_properties($p)</i> в качестве параметра которому
    передаётся объект совйства
*/

$p = new properties_file();
$p->set_id('template_print');
$p->set_caption('[#pageprint_page_module_label_template_print#]');
$p->set_patch('modules/pageprint/templates_user');
$p->set_mask('htm,html');
$p->set_default('modules/pageprint/templates_user/print_template.html');
$install->add_modul_properties($p);

//Создали объект свойства
/*
$p = new properties_checkbox();

//Определили уникаль идентифиактор свойства, по которому к нему потом
//можно будет обращаться из кода модуля
$p->set_id('param_modul_1');

//Задать имя свойства (можно пользоваться как языковой переменной так и
//непосредственно ввести свойство
$p->set_caption('[#example_module_label_propertes1#]');

//Проставим значение по умолчанию
$p->set_default('false');

//Добавляем свойство к модулю
$install->add_modul_properties($p);

//По аналогии добавим свойства модуля всех типов
$p = new properties_date();
$p->set_id('param_modul_2');
$p->set_caption('[#example_module_label_propertes2#]');
$p->set_default('10/11/1979');
$install->add_modul_properties($p);
*/
/*
$p = new properties_pagesite();
$p->set_id('param_modul_4');
$p->set_caption('[#example_module_label_propertes4#]');
$p->set_default('index');
$install->add_modul_properties($p);

$p = new properties_select();
$p->set_id('param_modul_5');
$p->set_caption('[#example_module_label_propertes5#]');
$p->set_data(array("val1"=>"[#example_module_propertes5_val1#]","val2"=>"[#example_module_propertes5_val2#]"));
$p->set_default('val2');
$install->add_modul_properties($p);

$p = new properties_string();
$p->set_id('param_modul_6');
$p->set_caption('[#example_module_label_propertes6#]');
$p->set_default('santafox@santafox.ru');
$install->add_modul_properties($p);
*/
/*
    Дальше описываются необходимые свойства модуля к страницам сайта.
    Зачения этих свойств могут быть установлены для каждой конкртеной странцы
    сайта или же отнаследованы от родительской

    Добавление свойства как свойства модуля происходит с помощью метода
    <i>$install->add_page_properties($p)</i> в качестве параметра которому
    передаётся объект совйства
*/
/*
$p = new properties_file();
$p->set_id('printtemplate');
$p->set_caption('[#pageprint_module_fp_label_template#]');
$p->set_patch('modules/pageprint/templates_user');
$p->set_mask('htm,html');
$install->add_page_properties($p);*/

/*
$p = new properties_checkbox();
$p->set_id('param_modul_for_page_1');
$p->set_caption('[#example_module_fp_label_propertes1#]');
$install->add_page_properties($p);


$p = new properties_date();
$p->set_id('param_modul_for_page_2');
$p->set_caption('[#example_module_fp_label_propertes2#]');
$install->add_page_properties($p);


$p = new properties_pagesite();
$p->set_id('param_modul_for_page_4');
$p->set_caption('[#example_module_fp_label_propertes4#]');
$install->add_page_properties($p);

$p = new properties_select();
$p->set_id('param_modul_for_page_5');
$p->set_caption('[#example_module_fp_label_propertes5#]');
$p->set_data(array("val1"=>"[#example_module_fp_propertes5_val1#]","val2"=>"[#example_module_fp_propertes5_val2#]"));
$install->add_page_properties($p);

$p = new properties_string();
$p->set_id('param_modul_for_page_6');
$p->set_caption('[#example_module_fp_label_propertes6#]');
$install->add_page_properties($p);
*/

/*
    Схожим образом добавляются необходимые дополнительные поля
    для авторизированных пользователей сайта

    Важно!!
    На даный момент к пользователю сайта можно добавить поля только
    типа properties_string();

    Добавление свойства как свойства модуля происходит с помощью метода
    <i>$install->add_user_properties($p, $multi = false, $admin= false)</i>
    в качестве параметра которому передаётся объект свойства.
    Если $multi <i>TRUE</i> - то этот параметр будет прописываться каждым
    экземпляром дочернего модуля, в противном случае только базовым модулем.
    Если $admin <i>TRUE</i> - то значит доступ к этому парметру пользователя
    должен иметь только администратор, в противном случае и сам пользователь
    имеет доступ к этому свойству

*/
/*
$p = new properties_string();
$p->set_id("data_b");
$p->set_caption("[#example_module_user_label_propertes1#]");
$install->add_user_properties($p);
*/

/*
    Добавим уровни доступа к модулю для администраторов сайта

    Для добавления уровня доступа используется метод
    <i>$install->add_admin_acces_label($id, $label)</i>. В качестве параметров
    передаётся идентефикатор уровня доступа и название уровня доступа для
    администратора сайта
*/
  //  $install->add_admin_acces_label('access_b', '[#example_module_access_label_1#]');
  //  $install->add_admin_acces_label('access_p', '[#example_module_access_label_2#]');



/*
    Теперь необходимо создать публичные методы модуля из которых будут строится
    действия. В какчестве параметров публичных методов используются всё теже
    стандартные классы свойств.

    Что бы добавить к модулю публичный класс необходимо объявить о его создании
    и затем добавить (при необходимости) параметры, которые будут указаны
    администратором сайта при создании из этогово метода конкретного действия
*/
/*
    Объявление публичный метод. В качестве параметра передаём имя публичного метода
    так, как оно написано в коде класса модуля и языковую переменную с навзванием этого
    метода для администратора сайта
*/
$install->add_public_metod('pub_show_link', '[#pageprint_pub_show_link#]');

/*
    Теперь добавим свойства к этому методу
    свойства должны добавляться в том порядке, в котором они указаны в конструкторе
    этого метода

    Для добавления свойства к методу испольуется функция
    $install->add_public_metod_parametrs('<имя метода>',$p);
*/

$p = new properties_file();
$p->set_id('template_print_link');
$p->set_caption('[#pageprint_module_fp_label_template#]');
$p->set_patch('modules/pageprint/templates_user');
$p->set_mask('htm,html');
$p->set_default('modules/pageprint/templates_user/label.html');
$install->add_public_metod_parametrs('pub_show_link',$p);


/*
$p = new properties_checkbox();
$p->set_id('param_metod_1');
$p->set_caption('[#example_module_pub1_propertes1#]');
$p->set_default('true');
$install->add_public_metod_parametrs('pub_create_content',$p);

$p = new properties_date();
$p->set_id('param_metod_2');
$p->set_caption('[#example_module_pub1_propertes2#]');
$p->set_default('07/01/2008');
$install->add_public_metod_parametrs('pub_create_content',$p);

$p = new properties_file();
$p->set_id('param_metod_3');
$p->set_caption('[#example_module_pub1_propertes3#]');
$p->set_patch('modules/example/templates_user');
$p->set_mask('htm,html');
$p->set_default('modules/example/templates_user/template_user.html');
$install->add_public_metod_parametrs('pub_create_content',$p);

$p = new properties_pagesite();
$p->set_id('param_metod_4');
$p->set_caption('[#example_module_pub1_propertes4#]');
$p->set_default('rus');
$install->add_public_metod_parametrs('pub_create_content',$p);

$p = new properties_select();
$p->set_id('param_metod_5');
$p->set_caption('[#example_module_pub1_propertes5#]');
$p->set_data(array("val1"=>"[#example_module_fp_propertes5_val1#]","val2"=>"[#example_module_fp_propertes5_val2#]"));
$p->set_default('val2');
$install->add_public_metod_parametrs('pub_create_content',$p);

$p = new properties_string();
$p->set_id('param_metod_6');
$p->set_caption('[#example_module_pub1_propertes6#]');
$p->set_default('4');
$install->add_public_metod_parametrs('pub_create_content',$p);
*/
/*
    Таким образом мы описали один публичный метод модуля. В коде модуля
    публичный метод будет выглядеть следующим образом

    <code>
    function pub_create_content($p1, $p2, $p3, $p4, $p5, $p6)
    {
        global $kernel;

        $html = '';

        //Код по формированию контента

        return $html;
    }
    </code>

*/



/*
    Заключительный этап инсталятора

    Если необходимо вы можете сразу создать нужно количество дочерних моудлей
    а так же дейсвтий с необходимыми параметрами у этих дочерних модулей

    Данный способ является временным и будет переработан
*/

/*
    Имя добавляемого дочернего модуля, задаётся ID языковой переменной
*/
$install->module_copy[0]['name'] = 'pageprint_modul_base_name1';

// Автоматически создадим действие на основе существующего публичного метода
$install->module_copy[0]['action'][0]['caption']    = 'Вывести ссылку печатной версии';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_show_link';

/*
    Значения свойств вновь создаваемого дочернего модуля
    Если значения свойств не указаны то они будут отнаследованы от значения
    по умолчанию у базового модуля
*/
//$install->module_copy[0]['properties']['param_modul_1'] = 'true';
//$install->module_copy[0]['properties']['param_modul_2'] = '01/01/2008';
//$install->module_copy[0]['properties']['param_modul_3'] = '';
//$install->module_copy[0]['properties']['param_modul_4'] = '';
//$install->module_copy[0]['properties']['param_modul_5'] = 'val1';
//$install->module_copy[0]['properties']['param_modul_6'] = 'help@santafox.ru';

/*
    Значения свойств к странице вновь создаваемого дочернего модуля
    Свойства могут быть указаны к конкртеной странице сайта, если
    вам известно что существует страница с таким ID. Как правил указываются
    значения для самой страницы

*/
//$install->module_copy[0]['properties_in_page']['index']['printtemplate'] = 'modules/pageprint/templates_user/label.html';
//$install->module_copy[0]['properties_in_page']['index']['param_modul_2'] = '01/11/2004';
//$install->module_copy[0]['properties_in_page']['index']['param_modul_3'] = '';
//$install->module_copy[0]['properties_in_page']['index']['param_modul_4'] = '';
//$install->module_copy[0]['properties_in_page']['index']['param_modul_5'] = 'val2';
//$install->module_copy[0]['properties_in_page']['index']['param_modul_6'] = 'help@email.ru';


//Значение свойств модуля к странице
//Описание
//properties_in_page
/*
$install->module_copy[0]['action'][0]['caption'] = 'Вывести ленту';
$install->module_copy[0]['action'][0]['id_metod'] = 'pub_news_lenta';
$install->module_copy[0]['action'][0]['param']['news_num_per_page'] = 3;
$install->module_copy[0]['action'][0]['param']['submodule_list'] = 'news1';
$install->module_copy[0]['action'][0]['param']['lenta_selection_type'] = 'ORDER BY time DESC';
$install->module_copy[0]['action'][0]['param']['lenta_template'] = 'modules/news/templates_user/lenta.html';

$install->module_copy[0]['action'][1]['caption'] = 'Вывести список';
$install->module_copy[0]['action'][1]['id_metod'] = 'pub_news_spisok';
$install->module_copy[0]['action'][1]['param']['news_slection_type'] = 'ORDER BY time DESC';
$install->module_copy[0]['action'][1]['param']['news_num_per_page'] = '10';
$install->module_copy[0]['action'][1]['param']['spisok_template'] = 'modules/news/templates_user/spisoc.html';


$install->module_copy[0]['action'][2]['caption'] = 'Вывести текст новости';
$install->module_copy[0]['action'][2]['id_metod'] = 'pub_news_fulltext';
$install->module_copy[0]['action'][2]['param']['full_template'] = 'modules/news/templates_user/fulltext.html';
*/
?>
