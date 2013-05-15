<?PHP

/**
 * Основной управляющий класс модуля «Версия для печати»
 *
 * Модуль предназначен организации новостных лент,
 * или любой другой информации со схожей системой вывода
 * и обработки
 * @copyright ArtProm (с) 2001-2007
 * @version 1.0
 */

class pageprint
{
    var $template_array = array();
    var $one_admin = false;

    /**
     * Путь, к шаблонам модуля (туда будут скопированны шаблоны при инсталяции
     *
     * @var string
     */
    var $path_templates = "modules/pageprint/templates_user"; //


    /**
     * Конструктор класса
     *
     * @return news
     */
    function pageprint()
    {
        global $kernel;
        //Разрешили метод pub_create_pageprint данного модуля
        //вызывать напрямую через URL, при этом после выполнения
        //метода дальнейшая обработка продолжается
        $kernel->pub_da_metod_set('pub_create_pageprint', false);

    }


    //***********************************************************************
    //	Наборы Публичных методов из которых будут строится макросы
    //**********************************************************************


    function pub_show_link($template_label)
    {
        global $kernel;

        //Получим шаблон который указан для страницы
        $template = $template_label;
        if (empty($template))
            return 'Не выбран шаблон, используемый для формирования ссылки';
        else
            $template = $kernel->pub_template_parse($template);

        //Получили из шаблона блок с сылкой
        if (!isset($template['link']))
            return 'В шаблоне не определён блок @link';

        $html = $template['link'];
        $html = str_replace('%link%', $kernel->pub_da_link_create('pub_create_pageprint'),$html);

        //Код по формированию контента

        return $html;
    }

    function pub_create_pageprint()
    {
        global $kernel;

        //А теперь подменим шаблон страницы, на тот, что используетсся для
        //печатной версии
        $prop = $kernel->pub_modul_properties_get('template_print');
        if (!($prop['isset']))
            $kernel->debug("Не задан шаблон печатной версии страницы в свойствах модуля", true);

        if (!file_exists($prop['value']))
        {
            $kernel->debug("Файла ".$prop['value']." не существует",true);
            die;
        }
        $kernel->pub_da_page_template_set($prop['value']);
        return true;
    }


    //***********************************************************************
    //	Наборы внутренних методов модуля
    //**********************************************************************




    //***********************************************************************
    //	Наборы методов, для работы с админкой модуля
    //**********************************************************************

    /**
     * Формирует меню модуля
     *
     * @param object $menu
     * @return boolean
     */
	function interface_get_menu($menu)
	{
	    //У модуля нет админки

        //$menu->set_menu_block('Управление');
        //$menu->set_menu("Пункт 1","point1");
        //$menu->set_menu("Пункт 2","point2");
        //$menu->set_menu_default('point1');
	    return true;
	}

    /**
	 * Предопределйнный метод, используется для вызова административного интерфейса модуля
	 * У данного модуля админка одна, для всех экземпляров, так как в админке надо только
	 * Редактировать шаблоны
	 */
    function start_admin()
    {
        global $kernel;
        $html = '';
        //У модуля нет админки
        //$kernel->pub_section_current_get();

        return $html;
    }


}

?>