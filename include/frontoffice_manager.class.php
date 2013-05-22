<?php
/**
 * Основной класс, управляющий сайтом при просмотре его пользователями
 *
 * Решает следующие задачи:
 * <ul>
 * <li>Определение запрашиваемой страницы</li>
 * <li>Формирование страницы и вызов соответствующих модулей и их методов</li>
 * </ul>
 *
 * @name frontoffice_manager
 * @package Kernel
 * @copyright ArtProm (с) 2001-2012
 * @version 3.0
 */

class frontoffice_manager
{

    /**
     * Конструктор объекта
     */
    function __construct()
    {
        if (defined("GENERATE_STATISTIC") && GENERATE_STATISTIC)
            $this->session_tracking_set();
    }


    /**
     * Устанавливает в сессии переменные, следящие за передвижениями пользователя по сайту
     *
     * Устанавливает следующие переменные в сессию:
     * <code>
     *        $_SESSION['vars_kernel']['tracking']['enter_from'] Страница, с которой пришел посетитель
     *        $_SESSION['vars_kernel']['tracking']['enter_from_domain'] Домен, с которого пришел посетитель
     *        $_SESSION['vars_kernel']['tracking']['search_word'] Слово в поисковой системе, по которому пришел посетитель
     *        $_SESSION['vars_kernel']['tracking']['enter_point'] Точка входа на сайт
     *        $_SESSION['vars_kernel']['tracking']['enter_unixtime'] unixtime-время, в которое посетитель попал на сайт.
     *        Служит для определения следующих параметров:
     *        $_SESSION['vars_kernel']['tracking']['walking_path'][15] Страницы, которые посетил пользователь.
     *        Последний индекс указывает через сколько времени от начала сессии он оказался на этой странице.
     *        $_SESSION['vars_kernel']['tracking']['walking_time'] Время, которое пользователь провел на сайте в данный момент.
     * </code>
     * @access private
     * @return void
     */
    function session_tracking_set()
    {
        // Устанавливаем точку входа и страницу, с которой пришли на сайт
        if (!isset($_SESSION['vars_kernel']['tracking']))
        {
            require_once (dirname(dirname(__FILE__)) . "/admin/manager_stat.class.php");
            $manager_stat = new manager_stat();
            $user_array = $manager_stat->visitor_info_get(session_id());

            $_SESSION['vars_kernel']['tracking']['enter_from'] = $user_array['referer'];
            $_SESSION['vars_kernel']['tracking']['enter_from_domain'] = $user_array['referer_domain'];
            $_SESSION['vars_kernel']['tracking']['search_word'] = $user_array['word'];
            $_SESSION['vars_kernel']['tracking']['enter_point'] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $_SESSION['vars_kernel']['tracking']['enter_unixtime'] = time();
        }
        else
        {
            $_SESSION['vars_kernel']['tracking']['walking_path'][(time() - $_SESSION['vars_kernel']['tracking']['enter_unixtime'])] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $_SESSION['vars_kernel']['tracking']['walking_time'] = time() - $_SESSION['vars_kernel']['tracking']['enter_unixtime'];
        }

    }

    public static function throw_404_error()
    {
        global $kernel;
        if (isset($_SERVER["HTTP_REFERER"]) && !empty($_SERVER["HTTP_REFERER"]))
        { //есть ли реферер?
            $mstat = new manager_stat();
            $idSearch = $mstat->get_IDSearch_by_referer($_SERVER["HTTP_REFERER"]);
            if ($idSearch > 0)
            {
                $word = $mstat->get_word_by_referer($idSearch, $_SERVER["HTTP_REFERER"]);
                if ($word)
                {
                    $webroot = dirname(dirname(__FILE__))."/";
                    if (file_exists($webroot.'modules/search/include/searcher.class.php'))
                    { //модуль поиска существует, подключаем необходимые классы
                        require_once $webroot.'modules/search/include/searcher.class.php';
                        require_once $webroot.'modules/search/include/searchdb.class.php';
                        require_once $webroot.'modules/search/include/htmlparser.class.php';
                        require_once $webroot.'modules/search/include/lingua_stem_ru.class.php';
                        require_once $webroot.'modules/search/include/indexator.class.php';
                        $searcher = new Searcher($kernel->pub_prefix_get() . "_search1");

                        $searcher->set_results_per_page(1);
                        $searcher->set_doc_format('html');
                        $searcher->set_operation('and');

                        $results = $searcher->search($word);
                        if (count($results) > 0) //что-то найдено
                            $kernel->priv_redirect_301($results[0]['url']);
                    }
                }
            }
        }
        if (defined("PAGE_FOR_404") && PAGE_FOR_404)
            $redirPage = PAGE_FOR_404;
        else
            $redirPage = "index";
        $kernel->priv_redirect_301("/" . $redirPage);
    }

    /**
     * Точка входа
     *
     * Вызывается при получении непосредственно запроса от браузера. Запрашивает страницу,
     * подготавливает её к выводу и непосредственно выводит.
     * @return void
     */
    function start()
    {
        global $kernel;
        //определим страницу, кторую запрашивают
        $uri = $_SERVER['REQUEST_URI'];
        $section_id = null;
        //Проверим корректность задания страницы
        if (isset($_GET['sitepage']) && $kernel->is_valid_sitepage_id($_GET['sitepage'])) //если задан параметр, какую страницу выдать, то выставим её в первую очередь
            $section_id = $_GET['sitepage'];
        elseif (preg_match("~^/([^\?]+)~i", $uri, $matches))
            $section_id = $matches[1];
        elseif (preg_match("'^/(\\?.*)?$'", $uri))
            $section_id = "index";
        else
            self::throw_404_error();

        $section_id = strtolower($section_id);

        //Сразу проставим этустраницу в текущую, так как это влияет на карту сайта
        $kernel->priv_page_current_set($section_id, true);

        //Проверка на то, что эту страницу могут смотреть только авторизированные пользователи
        $only_auth = $kernel->pub_page_property_get($section_id, 'only_auth', false);
        if ($only_auth['isset'] && $only_auth['value'] && !$kernel->pub_user_is_registred()) //"Для просмотра необходима регестрация и (или) авторизация"
            self::throw_404_error();

        //Проверим, может нужно перейти на какую-то другую страницу
        //При этом необходимо вытащить все параметры после вопроса и передать их дальше
        $id_other = $kernel->pub_page_property_get($section_id, 'link_other_page', false);
        if ($id_other['isset'] && !empty($id_other['value']))
        {
            $query = '';
            $pars_uri = parse_url($uri);
            if ((isset($pars_uri['query'])) && (!empty($pars_uri['query'])) && ($pars_uri['query'] !== "/"))
                $query = "?" . $pars_uri['query'];

            $str_url = "/" . trim($id_other['value']) . $query;
            $kernel->pub_redirect_refresh_global($str_url);
        }

        //Теперь, зная запрашиваемую страницу, надйдем её в базе
        //Проверяем, существует ли эта страница
        if (!$kernel->priv_page_exist($section_id))
            self::throw_404_error();

        //прежде всего шаблон, который она использует.
        $file_template = $kernel->pub_page_property_get($section_id, 'template');
        if (!$file_template['isset'])
        {
            if (defined("SHOW_INT_ERRORE_MESSAGE") && SHOW_INT_ERRORE_MESSAGE)
                $kernel->priv_error_show('[#errore_message_isset_template#]');
            die;
        }

        if (!file_exists($file_template['value']))
        {
            if (defined("SHOW_INT_ERRORE_MESSAGE") && SHOW_INT_ERRORE_MESSAGE)
                $kernel->priv_error_show('[#errore_message_isset_file_template#]');
            die;
        }

        //Сделаем запись в статиски сейчас вот таким способом
        if (defined('GENERATE_STATISTIC') && GENERATE_STATISTIC)
        {
            $stat = new manager_stat();
            $stat->set_stat();
        }

        $kernel->priv_session_vars_set();

        //Получим заготовку для имени страницы в кэше
        $file_name_cache = $section_id;
        if (isset($_SERVER['REDIRECT_QUERY_STRING']))
            $file_name_cache = $section_id . $_SERVER['REDIRECT_QUERY_STRING'];

        //Если производить кэширование, то проверим наличие этой страницы в кэше
        //и при её нахождение выведем её.
        if (defined("CACHED_PAGE") && CACHED_PAGE)
        {
            $file_name_cache = md5($file_name_cache);
            $file_name_cache = "cache/" . $file_name_cache . ".html";
            if (file_exists($file_name_cache))
            {
                $html = file_get_contents($file_name_cache);
                $kernel->priv_output($html, false, true);
                die;
            }
        }

        //Проверим, возможно есть вызов функции прямого модуля или метода
        //и тогда нам нужно его просто
        $this->priv_test_direct_run_metod();

        //Теперь надо вызвать пару (пока одну) функцтию ядра и посмотреть,
        //может после вызова какого-то метода, произошли изменения и их надо учесть/
        $new_name = $kernel->priv_page_template_get_da();
        if (!($new_name === false))
            $file_template['value'] = $new_name;

        //Производит непосредственное формирование страницы
        $html = file_get_contents($file_template['value']);

        //конвертируем шаблон из 1251, если такой тип указан в конфиге
        if (defined("IS_1251_TEMPLATES") && IS_1251_TEMPLATES)
        {
            $html = @iconv('cp1251', 'UTF-8//TRANSLIT', $html);
            //+заменяем кодировку в хидере
            $html = str_ireplace("windows-1251", "UTF-8", $html);
        }
        $kernel->priv_frontcontent_set($this->generate_html_template($html));

        //Теперь нужно вытащить все метки с шаблона и узнать что соответсвует каждой метки
        $array_link = $kernel->priv_page_textlabels_get($kernel->priv_frontcontent_get());
        $array_link = $array_link[1];
        $array_link = array_flip($array_link);

        $array_link = $kernel->priv_page_real_link_get($array_link, $section_id, true);
        // Создаем массив с приоритетами выполнения модулей
        $priority_array = array();
        // Ядру - самый низкий приоритет
        $priority_array['kernel'] = 1;

        // Устанавливаем приоритеты для модулей
        $modules = $kernel->db_get_list_simple("_modules", "parent_id IS NOT NULL", "id,parent_id");
        foreach ($modules as $data)
        {
            // В будущем приоритеты можно будет брать из таблиц. Пока так.
            switch ($data['parent_id'])
            {
                case 'catalog':
                    $priority_array[$data['id']] = 100;
                    break;
                case 'waysite':
                    $priority_array[$data['id']] = 50;
                    break;
                case 'menu':
                    $priority_array[$data['id']] = 55;
                    break;
                case 'glossary':
                    $priority_array[$data['id']] = 98;
                    break;
                case 'newsi': //модуль должен отработать после kernel, т.к. меняет title
                    $priority_array[$data['id']] = 110;
                    break;
                case 'faq': //модуль должен отработать после kernel, т.к. меняет title
                    $priority_array[$data['id']] = 120;
                    break;
                default:
                    $priority_array[$data['id']] = 99;
            }
        }

        // Приписываем приоритеты от модулей к действиям
        foreach ($array_link AS $key => $val)
        {
            if (!is_array($val))
                continue;

            if ($val['id_mod'])
            {
                $prioritrt_metod = 0;
                //Здесь пока небольшое исключение для SAPE и Linkfeed
                //В дальнейшем это будет сделано уже для всех модулей
                if (preg_match("/^(sape|linkfeed)[0-9]+$/", $val['id_mod']))
                {
                    $tmp = unserialize($val['run']['param']);
                    if (isset($tmp['num_for_sort']))
                        $prioritrt_metod = intval($tmp['num_for_sort']);
                }

                $array_link[$key]['priority'] = $priority_array[$array_link[$key]['id_mod']] + $prioritrt_metod;
            }
            else
                $array_link[$key]['priority'] = intval(100);
        }
        //И сортируем массив в соответствии с приоритетом
        uasort($array_link, array("frontoffice_manager", "compare_priority"));

        //Обходим массив меток, и создаем выходной итоговую страницу
        foreach ($array_link as $key => $val)
        {

            if (isset($val['isadditional']))
                continue;
            $this->replace_labels_by_generated_content($key, $val);
        }

        //Второй проход (только оставшиеся метки), после отработки модулей,
        //чтобы заменить метки и в контенте, созданном модулями
        $gcontent = $this->generate_html_template($kernel->priv_frontcontent_get());
        $kernel->priv_frontcontent_set($gcontent);
        $array_link = $kernel->priv_page_textlabels_get($kernel->priv_frontcontent_get());
        $array_link = $array_link[1];
        $array_link = array_flip($array_link);
        $array_link = $kernel->priv_page_real_link_get($array_link, $section_id, true);
        foreach ($array_link as $key => $val)
        {
            if (strpos($kernel->priv_frontcontent_get(), '[#' . $key . '#]') === false)
                continue;
            $this->replace_labels_by_generated_content($key, $val);
        }

        $html = $kernel->priv_frontcontent_get();

        //Запишем в кэш страницу, если ведётся работа с кэшем.
        if (defined("CACHED_PAGE") && CACHED_PAGE)
        {
            $file = fopen($file_name_cache, "w+");
            fwrite($file, $html);
            fclose($file);
        }
        $kernel->priv_output($html, false, true);
    }

    private function replace_labels_by_generated_content($label, $val)
    {
        global $kernel;
        $html_replace = '';
        if (isset($val['id_mod']) && $val['id_mod'])
        {
            if ($val['id_mod'] == 'kernel')
                $html_replace = $this->run_metod_kernel($label, $val);
            else
                $html_replace = $this->run_metod_modul($val);
            $html_replace = $this->do_postprocessing($val, $html_replace);
        }
        $html = str_replace('[#' . $label . '#]', $html_replace, $kernel->priv_frontcontent_get());
        //Теперь необходимо обновить этот контент в переменной ядра
        //что бы кто-то (модуль) мог иметь доступ к этому контенту и влиять на него.
        $kernel->priv_frontcontent_set($html);
    }

    private function do_postprocessing($labelData, $html)
    {
        global $kernel;
        $system_postprocessors = $kernel->get_postprocessors();
        foreach ($labelData['postprocessors'] as $pp)
        {
            if (!array_key_exists($pp, $system_postprocessors)) //указанного постпроцессора нет в списке, возвращённом системой
                continue;
            include_once $kernel->get_postprocessors_dir() . "/" . $pp . ".php";
            /** @var $ppobj postprocessor */
            $ppobj = new $pp;
            $html = $ppobj->do_postprocessing($html);
        }
        return $html;
    }

    /**
     * Сравнивает приоритет двух модулей (действий)
     *
     * Возвращает "0" если приоритеты одинаковые, "-1" если приоритет $a больше $b, "1" если наоборот
     * @param array $a
     * @param array $b
     * @return int
     */
    public function compare_priority($a, $b)
    {
        if ($a['priority'] == $b['priority'])
            return 0;
        return ($a['priority'] > $b['priority']) ? -1 : 1;
    }


    /**
     * Создает HTML шаблон, добавляя в него содержимое действий "редактора контента"
     *
     * Функция рекрсивная, а значит находит содержимое действий "редактора контента" не только
     * первого уровня, а всех, которые есть, как гы глубоко они не были "зарыты"
     * @param string $html_template
     * @param array $metki_exist Массив с метками, которые не нужно обрабатывать, так как они уже есть
     * @return string
     */
    private function generate_html_template($html_template, $metki_exist = array())
    {
        global $kernel;

        $curent_link = $kernel->priv_page_textlabels_get($html_template);
        $curent_link[0] = array_unique($curent_link[0]);
        $curent_link[1] = array_unique($curent_link[1]);

        //Узнаем значения ссылок с учетом наследования
        $link_in_page_real = $curent_link[1];

        $link_in_page_real = array_flip($link_in_page_real);
        $link_in_page_real = $kernel->priv_page_real_link_get($link_in_page_real, $kernel->pub_page_current_get());

        foreach ($link_in_page_real AS $metka => $massiv)
        {
            if (isset($metki_exist[$metka]))
                continue;
            if (($link_in_page_real[$metka]['id_mod'] == "kernel") && ($link_in_page_real[$metka]['run']['name'] == "priv_html_editor_start") && (is_file($kernel->priv_path_pages_content_get() . '/' . $link_in_page_real[$metka]['page'] . '_' . $kernel->pub_translit_string($metka) . '.html')))
            {
                $metka_content = file_get_contents($kernel->priv_path_pages_content_get() . '/' . $link_in_page_real[$metka]['page'] . '_' . $kernel->pub_translit_string($metka) . '.html');
                $metka_content = $this->generate_html_template($metka_content, $link_in_page_real);
                $metka_content = $this->do_postprocessing($massiv, $metka_content);
                $html_template = str_replace("[#" . $metka . "#]", $metka_content, $html_template);
            }
        }
        return $html_template;
    }

    /**
     * Осуществляет вызов метода кокртеного модуля
     *
     * Анализируя переданный в параметре массив определяет какой модуль
     * необходимо подключать и какой объект соответсвенно создавать.
     * После создания объект происходит вызов указанного метода.
     * Пример массива передаваемого в качестве параметра методу
     * <code>
     * [id_mod] => menu1     //Уникальное ID модуля, формирующего действие
    [id_action] => 3      //Уникальное ID дейсвтия
    [run] => Array
    (
    [name] => pub_show_menu_static  //Имя метода модуля, отвечающего за действие
    [param] => a:2:{s:13:"id_page_start";s:5:"index";s:16:"count_level_show";s:1:"1";} // Строчное представление
    массива с параметра метода
    )
     * </code>
     * @param array $in Массив данных для вызова метода
     * @param boolean $direct_run
     * @return string
     */
    private function run_metod_modul($in, $direct_run = false)
    {
        global $kernel;
        //Прежде всего узнаем родительский модуль
        $modul = new manager_modules();
        $id_modul = $modul->return_info_modules($in['id_mod']);
        if ($id_modul === false)
            return false;

        $start_modul = trim($id_modul['parent_id']);
        if (empty($start_modul))
            return false;

        //Теперь подключим класс этого модуля и установим переменную ядра с именем конкретного
        //модуля, производящего сейчас вызов
        $kernel->priv_module_for_action_set($in['id_mod']);

        if (isset($in['id_action']))
            $kernel->set_current_actionid($in['id_action']);
        $modul = $kernel->priv_module_including_get($start_modul);
        if ($modul === false)
        {
            $str_file = "modules/" . $start_modul . "/" . $start_modul . ".class.php";
            include_once($str_file);

            //Теперь можно создать объект
            //$run = $in['run'];
            $modul = new $start_modul();
            $kernel->priv_module_including_set($start_modul, $modul);
        }

        //Узнаем имя модуля и его параметры и осуществим вызов этого метода
        $name_metod = $in['run']['name'];

        //Для прямого вызова надо проверить, а можем ли мы это вызывать
        $stop = false;
        if ($direct_run)
        {
            $open_action = $kernel->priv_da_metod_get();
            if (!isset($open_action[$name_metod]))
                return false;
            $stop = ($open_action[$name_metod]);
            settype($stop, 'bool');
        }

        $param = unserialize(stripslashes($in['run']['param']));
        $html = call_user_func_array(array(&$modul, $name_metod), $param);
        $kernel->priv_module_for_action_set('');
        $kernel->set_current_actionid(null);

        //Для прямого вызова вернём массив с флагом останвливаться или нет
        if (!$direct_run)
            return $html;
        else
            return array('stop' => $stop, 'content' => $html);
    }

    /**
     * Вызов основных функций ядра по фомрирвоанию контента для метки
     *
     * @param string $name_link Имя метода ядра, отвечающего за фомирование контенета
     * @param array $in
     * @return string
     */
    private function run_metod_kernel($name_link, $in)
    {
        global $kernel;
        $name_metod = $in['run']['name'];
        $param = array($name_link);
        $html = call_user_func_array(array(&$kernel, $name_metod), $param);
        return $html;
    }


    private function priv_test_direct_run_metod()
    {
        global $kernel;
        //Определим, есть ли вообще прямой вызов
        $id_modul = $kernel->pub_httpget_get('da_modul');
        $id_metod = $kernel->pub_httpget_get('da_metod');
        if ((empty($id_modul)) || (empty($id_metod)))
        {
            $id_modul = $kernel->pub_httppost_get('da_modul');
            $id_metod = $kernel->pub_httppost_get('da_metod');
        }

        if ((empty($id_modul)) || (empty($id_metod)))
            return false;

        $in = array();
        $in['id_mod'] = $id_modul;
        $in['run']['name'] = $id_metod;
        $in['run']['param'] = serialize(array());

        $res = $this->run_metod_modul($in, true);
        if ($res['stop'])
        {
            $kernel->priv_output($res['content']);
            die;
        }
        return true;
    }
}