<?php
/**
 * Ядро
 *
 * Ядро CMS. С общими (публичными и приватными) функциями.
 * @name kernel
 * @copyright ArtProm (с) 2001-2012
 * @version 3.0
 */

class kernel
{

    /**
     * Объект класса ftpshnik
     *
     * @var ftpshnik
     */
    private $ftp_client = null;

    //private $ftp_root = false;
    private $santa_ftp_root = false;

    private $flag_can_recurs_save = true;
    private $ftp_dir_chmod_temp = array();
    private $header_is_sent = false;

    /**
     * Счетчик запросов в MySQL
     *
     * @var integer
     */
    private $queriesCount = 0;

    /**
     * Хранит признак того что произошла установка текущей страницы
     * в админстративном интерфейсе.
     *
     * Используется для в функции priv_page_curent_set()
     *
     * @access private
     * @var boolean
     */
    private $curent_page_selected = false;

    /**
     * Переменная хранит информацию о префиксе информационной базы данных
     *
     * @access private
     * @var string
     */
    private $prefix;

    /**
     * Текущий модуль
     *
     * Содержит ID с модулем в котором сейчас ведется непосредственная работа в Фронт-офисе
     * @access private
     * @var string
     */
    private $curent_modul;

    /**
     * ID текущего действи для фронтенда
     *
     * Содержит ID текущего действия, обрабатываемого во фронтенд
     * @access private
     * @var string
     */
    private $curent_actionid;

    /**
     * Текущая страница сайта
     *
     * Содержит ID со страницей которая сейчас запрашивается человеком из фронт офиса
     * @access public
     * @var String
     */
    public $curent_page;

    /**
     * Текущий домен сайта
     *
     * Содержит ID со страницей которая является корневой для той, которая сейчас запрашивается
     * человеком из фронт офиса
     * @access private
     * @var String
     */
    private $curent_page_main;

    /**
     * Кэш карты сайта
     *
     * Используется для хранения массива всех страниц в линейном виде
     * (в виде одного большого массива)
     * @access private
     * @var array
     */
    private $mapsite_cache;

    /**
     * Древовидный кэш карты сайта
     *
     * Используется для хранения информации о потомках для каждой страницы
     * @access private
     * @var array
     */
    private $mapsite_cache_tree = array();


    /**
     * Кэш дороги
     *
     * Содержит информацию о дороге к текущей странице сайта
     * @access private
     * @var array
     */
    private $waysite_cache;

    /**
     * Включенные модули
     *
     * Список модулей, которые уже прошли подключение при обработке ссылок в шаблоне
     * @var array
     * @access private
     */
    private $include_modules;


    /**
     * Массив с днями недели, дни с заглавной буквы
     *
     * @var array
     * @access private
     */
    private $weekdays_f = array("Воскресенье", "Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота");


    /**
     * Названия месяцев. Все слова с заглавных букв
     *
     * @var array
     * @access private
     */
    private $months_f = array("", "Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря");
    //@todo использовать lang[]


    /**
     * Путь к корню сайта
     *
     * Содержит абсолютный путь к корню сайта
     * @var string
     * @access private
     */
    private $site_root;

    /**
     * Содержит строку дополнительных тайтлов
     *
     * Эти дополнительные тайтлы могут простовляться методами модулей
     * @var string
     * @access private
     */
    private $modul_title;

    /**
     * Массив с отладочными сообщениями и сообщениями о ошибках
     *
     * @var array
     * @access private
     */
    private $message_debug = array();

    /**
     * Массив открытых методов
     *
     * Содержит массив методов которое открыты для использования в
     * прямом вызове
     * @var array
     * @access private
     */
    private $open_metod = array();

    /**
     * Содержит имя файла шаблона страницы, который должен
     * может быть заменён. Используеться в DA (Direct Access)
     *
     * @var string
     */
    private $page_template_new = '';

    private $resurs_mysql = false;

    private $chmod_access_full = 0777;
    private $chmod_access_lim  = 0644;
    private $chmod_access_dir  = 0755;

    private $timer_start = 0;
    private $timer_end = 0;
    private $timer_elapsed = 0;

    /**
     * Переменная для хранения текущей секции
     *
     * @var string
     */
    private $curent_section = '';

    /*
        Переменныые для обработки POST запросов без перегрузки страницы

    */
    private $response_post_error = array(); //Массив с возможными ошибками обработки запроса.

    /**
     * Переменная хранит контент, который формируется
     * в процессе построения страницы
     *
     * @var string
     */
    private $content_for_show = "";

    /**
     * Содержит путь до
     *
     * @var string
     */
    private $path_for_content = "";


    /**
     * Массив языков, которые могут быть доступны в системе
     *
     * @var array
     */
    private $lang = array("ru" => "[#admin_lang_caption_for_ru#]",
        "en" => "[#admin_lang_caption_for_en#]",
        "ua" => "[#admin_lang_caption_for_ua#]"
    );


    /** @var список имеющихся постпроцессоров */
    private $postprocessors=null;

    public $is_windows;

    /**
     * Конструктор
     *
     * Устанавливает значения основных переменных (членов класса) ядра
     * @param string $prefix Префикс информационых баз
     * @access private
     * @return void
     */
    function kernel($prefix)
    {
        $this->prefix = $prefix;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            $this->is_windows = true;
        else
            $this->is_windows = false;
        //теперь произведем соединение с базой данных MySql
        $this->resurs_mysql = mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        if (!$this->resurs_mysql)
            die('mysql connect failed');

        if (!mysql_select_db (DB_BASENAME, $this->resurs_mysql))
            die('mysql select db failed');

        ini_set('session.use_only_cookies',1);
        $ver_arr = false;
        preg_match("/^([\\d]\\.[\\d]+)/i", mysql_get_server_info($this->resurs_mysql), $ver_arr);

        if ($ver_arr[1]+0 >= 4.1)
            $this->runSQL("SET NAMES utf8");

        //Функция для начала выполнения страницы
        $this->priv_timer_start();

        // Определяем корень сайта
        $this->priv_path_root_set();

        //Проверяем константы на адекватность значений
        $this->path_for_content = PATH_PAGE_CONTENT;
        if (mb_substr($this->path_for_content, 0, 1) !== '/')
            $this->path_for_content ="/".$this->path_for_content;

    }

    public function get_current_actionid()
    {
        return $this->curent_actionid;
    }

    public function set_current_actionid($actionid)
    {
        $this->curent_actionid=$actionid;
    }

    /**
     * Возвращает максимальный набор прав
     *
     * Если параметр задан в <i>true</i>, то возвращается значение
     * прав в виде строки "0xxx". Если не задан, то возвращается
     * число в восьмеричной форме
     * @param $ret_string
     * @return mixed
     * @access private
     */
    function priv_chmod_full_get($ret_string = false)
    {
        //по умолчанию вернем восьмеричное число
        $ret = $this->chmod_access_full;
        if ($ret_string)
        {
            $ret = trim(substr(sprintf("%o", $this->chmod_access_full), -3));
            if (strlen($ret) < 4)
                $ret = '0'.$ret;
        }
        return $ret;
    }

    /**
     * Возвращает ограниченный набор прав
     *
     * Если параметр задан в <i>true</i>, то возвращается значение
     * прав в виде строки "0xxx". Если не задан, то возвращается
     * число в восьмеричной форме
     * @param $ret_string
     * @return mixed
     * @access private
     */
    function priv_chmod_limit_get($ret_string = false)
    {
        //по умолчанию вернем восьмеричное число
        $ret = $this->chmod_access_lim;
        if ($ret_string)
        {
            $ret = trim(substr(sprintf("%o", $this->chmod_access_lim), -3));
            if (strlen($ret) < 4)
                $ret = '0'.$ret;
        }
        return $ret;

    }

    /**
     * Возвращает код ограниченного набора прав для директорий
     *
     * Метод возвращает код прав доступа, используемый в UNIX. Данный код проставляется
     * на все стандартные папки, которым не нужен полный доступ. Метод может использоваться
     * компонентами административного интерфейса при установке стандартных и полных прав.
     * @param boolean $ret_string Если задан в true, то возвращается значение прав в виде строки "0xxx",
     * иначе число в восьмеричной форме
     * @access private
     * @return mixed
     */
    function priv_chmod_dir_get($ret_string = false)
    {
        //по умолчанию вернем восьмеричное число
        $ret = $this->chmod_access_dir;
        if ($ret_string)
        {
            $ret = trim(substr(sprintf("%o", $this->chmod_access_dir), -3));
            if (strlen($ret) < 4)
                $ret = '0'.$ret;
        }
        return $ret;
    }

    /**
     * Возвращает код уровня доступа
     * Метод возвращает код прав доступа, используемый в UNIX. Возвращаемый код
     * зависит от параметра $type_chmod, который может принимать следующие значения:
     *   1 - ограниченные права на файл;
     *   2 – будут возвращены полные права для файлов и директорий;
     *   3 - буду возвращены ограниченные права для директории;
     * То, какие конкретно это буду права, определяют переменные ядра.
     * @param integer $type_chmod Число от 1 до 3, определяющее объект
     * @param boolean $ret_string Если задан в true, то возвращается значение прав в виде строки "0xxx",
     * иначе число в восьмеричной форме
     * @return mixed
     */
    function pub_chmod_type_get($type_chmod = 1, $ret_string = false)
    {
        //по умолчанию вернем восьмеричное число
        if ($type_chmod == 3)
            $ret = $this->chmod_access_dir;
        elseif ($type_chmod == 2)
            $ret = $this->chmod_access_full;
        else
            $ret = $this->chmod_access_lim;

        if ($ret_string)
        {
            if ($type_chmod == 3)
                $ret = $this->priv_chmod_dir_get($ret_string);
            elseif ($type_chmod == 2)
                $ret = $this->priv_chmod_full_get($ret_string);
            else
                $ret = $this->priv_chmod_limit_get($ret_string);
        }
        return $ret;
    }



    /**
     * Возвращает путь к корню сайта
     *
     * Путь к public_html или аналагичной корневой директории, содержащей скрипты.
     * Путь выдается абсолютный, т.е. от /
     * @return string
     */
    function pub_site_root_get()
    {
        return $this->site_root;
    }

    /**
     * Запуск таймера для отсчета времени
     *
     * Предназначена для запуска отсчета времени выполнения скриптов при генерации страницы
     * @access private
     */
    function priv_timer_start()
    {
        $this->timer_start = microtime();
    }

    /**
     * Остановка таймера
     *
     * Предназначена для остановки отсчета времени выполнения скриптов при генерации страницы
     * @access private
     */
    function priv_timer_stop()
    {
        $this->timer_end  = microtime();
    }

    /**
     * Возвращает отсчитанное время
     *
     * Остановка таймера времени отсчета
     * @access private
     * @return float
     */
    function priv_timer_elapsed()
    {
        if ($this->timer_elapsed)
            return $this->timer_elapsed;

        $start_u = substr($this->timer_start, 0, 10);
        $start_s = substr($this->timer_start, 11, 10);
        $stop_u  = substr($this->timer_end, 0, 10);
        $stop_s  = substr($this->timer_end, 11, 10);
        $start_total = doubleval($start_u) + $start_s;
        $stop_total  = doubleval($stop_u) + $stop_s;

        $this->timer_elapsed = $stop_total - $start_total;
        return $this->timer_elapsed;
    }

    /**
     * Возвращает массив со всеми страницами сайта.
     *
     * Возвращаемый массив имеет линейную структуру. В качестве ключа используется
     * ID страницы. В качестве значения - другой массив со всеми свойствами страницы, в том
     * числе и свойствами, добавленными модулями
     *
     * Результаты метод можно использовать для отображения структуры сайта в том или ином виде.
     * Не рекомендуется его использовать для анализа свойств страницы.
     *
     * Возвращаемый массив имеет следующий вид:
     * <code>
     *      [index] => Array
     *   	(
     *            [id] => index                         //Дублируется ID страницы
     *            [parent_id] =>                        //ID родительской страницы (если есть)
     *            [caption] => Главная страница         //Название страницы
     *            [curent] => 1                         //Признак того что страница является текущей (true, false)
     *            [properties] => Array                 //Массив дополнительных свойств
     *                (
     *                    [title_other] => 1            //Признак того что есть title
     *                    [name_title] => Наша компания //Непосредственно title страницы
     *                    [template] => design/in.html  //Используемый шаблон
     *                    [link_other_page] => about    //ID другой страница сайта,
     *                                                  //на которую осуществляется перенаправление пользователя
     *                )
     *      )
     * </code>
     * Помимо этого, в <i>[properties]</i> могут содержаться значения свойств модулей к странице, если
     * в системе есть инсталлированные модули, прописавшие эти свойства, и значения свойств задано
     * у рассматриваемой странице. В этом случае ключом для обращения к данному свойству будет
     * <i>[IDмодуля_IDсвойства]</i>
     *
     * Все значения берутся без учета наследования. Для любой странице в массиве <i>[properties]</i> всегда
     * будут присутствовать ключи <i>[title_other]</i> и <i>[name_title]</i>. Остальные ключи в этом подмассиве
     * могут отсутствовать, если их конкретные значения не указаны у конкретной странице.
     * @access public
     * @return array
     */

    function pub_mapsite_get()
    {
        if (empty($this->mapsite_cache))
            $this->mapsite_cache = $this->pub_mapsite_cashe_create();

        return $this->mapsite_cache;
    }


    /**
     * Возвращает URL с которого посетитель попал на сайт
     *
     * Может использоваться любыми модулями для анализа или сбора информации о
     * посетителе сайта
     * @access public
     * @return string
     */
    function pub_tracker_from_get()
    {
        return $_SESSION['vars_kernel']['tracking']['enter_from'];
    }


    /**
     * Возвращает поисковое слово по которому посетитель попал на сайт
     *
     * Может использоваться любыми модулями для анализа или сбора информации о
     * посетителе сайта
     * @access public
     * @return string
     */
    function pub_tracker_search_word_get()
    {
        return $_SESSION['vars_kernel']['tracking']['search_word'];
    }


    /**
     * Возвращает первый URL, на который попал посетитель при входе на сайт
     *
     * Может использоваться любыми модулями для анализа или сбора информации о
     * посетителе сайта
     * @access public
     * @return string
     */
    function pub_tracker_enter_point_get()
    {
        return $_SESSION['vars_kernel']['tracking']['enter_point'];
    }


    /**
     * Возвращает массив страниц по которым прошелся посетитель
     *
     * Может использоваться любыми модулями для анализа или сбора информации о
     * посетителе сайта
     * @access public
     * @return array
     */
    function pub_tracker_walking_path_get()
    {
        return $_SESSION['vars_kernel']['tracking']['walking_path'];
    }


    /**
     * Возвращает время (в секундах), которое посетитель провел с момента входа на сайт
     *
     * Может использоваться любыми модулями для анализа или сбора информации о
     * посетителе сайта
     * @access public
     * @return string
     */
    function pub_tracker_walking_time_get()
    {
        return $_SESSION['vars_kernel']['tracking']['walking_time'];
    }


    /**
     * Возвращает имя поисковика
     *
     *
     * Может использоваться любыми модулями для анализа или сбора информации о
     * посетителе сайта
     */
    function pub_tracker_search_engine_get()
    {
        return $_SESSION['vars_kernel']['tracking']['enter_from_domain'];
    }

    /**
     * Возвращает префикс MySQL-таблиц
     *
     * Возвращает префикс информационной базы для доступа к таблицам MySql,
     * например, для таблицы "ap_admin" префикс будет "ap".
     * @access private
     * @return String
     */
    function pub_prefix_get()
    {
        return $this->prefix;
    }


    /**
     * Возвращает код текущего языка административного интерфейса
     *
     * Возвращает двухбуквенный код языка, используемого текущим администратором сайта
     * @access private
     * @return string
     */
    function priv_langauge_current_get()
    {
        if ((isset($_SESSION['vars_kernel']['lang'])) && (!empty($_SESSION['vars_kernel']['lang'])))
            return $_SESSION['vars_kernel']['lang'];
        else
            return DEFAULT_LANGUAGE;
    }

    /**
     * Возвращает используемую кодовую страницу
     *
     * Возвращает название текущей кодовой страницы, используемой у текущего администратора сайта
     * @access private
     * @return string
     */
    function priv_charset_current_get()
    {
        if ((isset($_SESSION['vars_kernel']['codepage'])) && (!empty($_SESSION['vars_kernel']['codepage'])))
            return $_SESSION['vars_kernel']['codepage'];
        else
            return DEFAULT_CHARSET;
    }

    /**
     * Возвращает массив проинсталлированных языковых переменных
     *
     * Возвращает массив, где в качестве значений указаны двухбуквенные обозначения
     * языков, чьи языковые перемененные проинсталлированные в ядре. Ядро не отслеживает
     * факт наличия представления на том или ином языке для языковых переменных. Задача
     * разработчиков самостоятельно отслеживать наличие представлений языковых переменных
     * используемых ими.
     * @param boolean $only_isset
     * @access private
     * @return array
     */
    function priv_languages_get($only_isset = true)
    {
        //Если надо вывести те языки, которые теоретически знает SantaFox
        //то просто вернём массив ядра.
        if (!$only_isset)
            return $this->lang;

        //В противном случае, узнаем те языки, которые есть
        //в системе
        $query = 'SELECT lang FROM '.$this->pub_prefix_get().'_all_lang
        	      GROUP BY lang';

        $result = $this->runSQL($query);
        $a_ret = array();
        while ($row = mysql_fetch_assoc($result))
            $a_ret[$row['lang']] = "[#admin_lang_caption_for_".$row['lang']."#]";

        return $a_ret;
    }

    /**
     * Возвращает массив доступных кодовых страниц
     *
     * Возвращает массив всех кодовых страниц, доступных ядру и администратору сайта
     * @return array
     * @access private
     */
    function priv_codepages_get()
    {
        $a_ret['utf-8']   = '[#admin_codepage_caption_for_utf8#]';
        return $a_ret;
    }


    /**
     * Очещает сессию от всех переменных ядра
     *
     * Очещает сессию от всех переменных ядра, но не очищает всю сессию вообще
     * @access private
     * @return void
     */
    function priv_session_empty()
    {

        unset($_SESSION['vars_kernel']);
        //$this->debug($_SESSION, true);
    }


    /**
     * Возвращает путь к файлам с контентом
     *
     * Возвращает путь к файлам, где хранится контент на метки.
     * Там хранится только тот контент - который редактируется с
     * помощью редактора контента
     * @access private
     * @return string
     */
    function priv_path_pages_content_get()
    {
        return PATH_PAGE_CONTENT;
    }


    /**
     * Возвращает путь к шаблонам страниц
     *
     * Возвращает путь только к шаблонам старниц. Шаблоны модулей, используемых на страница сайта
     * эта фукнция не возвращает
     * @access private
     * @return string
     */
    function priv_path_page_template_get()
    {
        return PATH_PAGE_TEMPLATE;
    }


    /**
     * Производит регистрацию массивов $_GET и $_POST.
     *
     * Функция вызывается перед формирование административного интерфейса
     * и производит запись массивов $_GET и $_POST в сессии. В дальнейшем,
     * обращение к этим массивам осуществляется с помощью функций
     * {@link pub_httpget_get} и {@link pub_httppost_get}
     *
     * Кроме того, функция запоминает выбранную секцию основного меню, если
     * она передаётся в переменной $_POST или устанваливает значение главного
     * меню значением по умолчанию, если такого это первый вход в админи
     * @access private
     * @return void
     */
    function priv_session_vars_set()
    {

        $_SESSION['vars_kernel']['my_get'] = $_GET;

        //if (!empty($_POST))
        $_SESSION['vars_kernel']['my_post'] = $_POST;

        //Определим, указывается ли текушая секция;
        if (isset($_GET['section']))
        {
            $_SESSION['vars_kernel']['curent_section'] = trim($_GET['section']);
            $this->curent_section = trim($_GET['section']);
        }
        if ((empty($_SESSION['vars_kernel']['curent_section'])) && (defined("ADMIN_SECTION_DEFAULT")))
            $_SESSION['vars_kernel']['curent_section'] = ADMIN_SECTION_DEFAULT;
        elseif ((empty($_SESSION['vars_kernel']['curent_section'])) && (!defined("ADMIN_SECTION_DEFAULT")))
            $_SESSION['vars_kernel']['curent_section'] = 'structure';
    }

    /**
     * Регистрирует выбранный элемент левого меню
     *
     * Функция вызывается когда происходит смена левого пункта меню
     * и этот пункт меню запоминается в сессии для текущей секции.
     * Для получения текущего пункта меню используется функциz {@link pub_section_leftmenu_get}
     * @param string $curent
     * @param boolean $update
     * @return void
     */
    public function priv_section_leftmenu_set($curent = '', $update = false)
    {
        if (isset($_GET['leftmenu']))
        {
            $lm=$_GET['leftmenu'];
            if(preg_match('~^(.+?)&(.+)$~',$lm,$m))
            {
                $lm=$m[1];
                $chunks = explode('&',$m[2]);
                foreach($chunks as $ch)
                {
                    if (strpos($ch,'=')===false)
                        continue;
                    list($name,$val)=explode('=',$ch);
                    $_GET[$name]=$val;
                    $_SESSION['vars_kernel']['my_get'][$name]=$val;
                }
            }
            $_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu'] = trim($lm);
        }
        if ($update && !empty($curent))
            $_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu'] = trim($curent);

        if (empty($_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu']) && !isset($_GET['leftmenu']))
            $_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu'] = trim($curent);
    }


    /**
     * Возвращает массив $_GET, взятый из сессии.
     *
     * При каком либо обращении к сайту происходит запись массив $_GET и $_POST
     * в сессию. С помощью этой функции модуль может получить доступ к данным,
     * содержащимся в массиве $_GET
     *
     * В качестве параметра можно передать название переменной, содержащейся
     * в массиве $_GET. Тогда будет возвращено сразу её непосредственное значение
     * или пустая строка, если такой переменной нет
     *
     * Смотри так же функций {@link pub_httppost_get}
     * @access public
     * @param string $name_var Имя переменной, содержащейся в массиве GET
     * @param bool $prepare Определяет, будет ли для переменной применён метод $kernel->pub_str_prepare_set()
     * @return array|string|integer
     */

    function pub_httpget_get($name_var = "", $prepare = true)
    {
        $arr = array();

        if ((isset($_SESSION['vars_kernel']['my_get'])) && (!empty($_SESSION['vars_kernel']['my_get'])))
            $arr = $_SESSION['vars_kernel']['my_get'];

        if ((!empty($name_var)) && (isset($arr[$name_var])))
            $arr = $arr[$name_var];
        elseif ((!empty($name_var)) && (!isset($arr[$name_var])))
            $arr = '';

        if (!empty($name_var) && $prepare && !is_array($arr))
            $arr = $this->pub_str_prepare_set($arr);

        return $arr;
    }

    /**
     * Возвращает массив $_POST, взятый из сессии.
     *
     * При каком либо обращении к сайту происходит запись массив $_GET и $_POST
     * в сессию. С помощью этой функции модуль может получить доступ к данным,
     * содержащимся в массиве $_POST
     *
     * В качестве параметра можно передать название переменной, содержащейся
     * в массиве $_POST. Тогда будет возвращено сразу её непосредственное значение
     * или пустая строка, если такой переменной нет
     *
     * Смотри так же функций {@link pub_httpget_get}
     * @access public
     * @param string $name_var Имя переменной, содержащейся в массиве
     * @param bool $prepare Определяет, будет ли для переменной применён метод $kernel->pub_str_prepare_set()
     * @return array|string|integer
     */
    function pub_httppost_get($name_var = "", $prepare = true)
    {
        $arr = array();

        if ((isset($_SESSION['vars_kernel']['my_post'])) && (!empty($_SESSION['vars_kernel']['my_post'])))
            $arr = $_SESSION['vars_kernel']['my_post'];

        if ((!empty($name_var)) && (isset($arr[$name_var])))
            $arr = $arr[$name_var];
        elseif ((!empty($name_var)) && (!isset($arr[$name_var])))
            $arr = '';

        if ((!empty($name_var)) && $prepare && is_string($arr))
            $arr = $this->pub_str_prepare_set($arr);

        return $arr;
    }


    //****************************************************************************
    /**
     * Возвращает массив доступных шаблонов страницы
     *
     * Обращается к папке переданной в качестве параметра и возвращает
     * массив со всеми файлами вида *.html
     * @param string $path Путь к папке, где лежат шаблоны
     * @access private
     * @return array
     */
    function priv_templates_get($path)
    {
        //chdir('../');
        $array_return = array();

        $dir = opendir($path);
//        $array_return[''] = '[#label_properties_no_select_option#]';
        while ($filename = readdir($dir))
        {
            if (preg_match("/^([a-z0-9_-]+)\\.html$/i", $filename))
            {
                $str = htmlspecialchars($path.'/'.$filename);
                $array_return[$str] = $str;
            }
        }
        closedir($dir);
        return $array_return;
    }

    /**
     * Возвращает логин текущего администратора сайта
     *
     * Возвращает логин текущего администратора сайта, который ведет в данный момент
     * работу в административном интерфейсе, для которого выполняется скрипт.
     * @access private
     * @return string
     */
    function priv_admin_current_get()
    {
        if ((isset($_SESSION['vars_kernel']['back_users'])) && (!empty($_SESSION['vars_kernel']['back_users'])))
            return $_SESSION['vars_kernel']['back_users'];
        else
            return '';
    }


    /**
     * Возвращает ID текущего администратора сайта
     *
     * Возвращает ID текущего администратора сайта, который ведет в данный момент
     * работу в административном интерфейсе, для которого выполняется скрипт.
     * @access private
     * @return integer
     */
    function priv_admin_id_current_get()
    {
        if ((isset($_SESSION['vars_kernel']['back_users_id'])) && (!empty($_SESSION['vars_kernel']['back_users_id'])))
            return $_SESSION['vars_kernel']['back_users_id'];
        else
            return '';
    }

    /**
     * Возвращает идентификатор секции, в которой сейчас находится администратор сайта
     *
     * При использовании данной функции в административном интерфейсе модуля,
     * будет всегда возвращен идентификатор базового модуля в независимости от того,
     * какой тип административного интерфейса использует модуль
     * @access public
     * @return string
     */
    function pub_section_current_get()

    {
        if ((isset($this->curent_section)) && (!empty($this->curent_section)))
            return $this->curent_section;
        elseif ((isset($_SESSION['vars_kernel']['curent_section'])) && (!empty($_SESSION['vars_kernel']['curent_section'])))
            return $_SESSION['vars_kernel']['curent_section'];
        else
            return '';
    }

    /**
     * Возвращает идентификатор левого меню, в котором сейчас находится администратор сайта
     *
     * Левое меню может быть использовано любым модулем или частью административного интерфейса.
     * @return string
     */
    public function pub_section_leftmenu_get()
    {
        if (isset($_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu']) && !empty($_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu']))
            return $_SESSION['vars_kernel'][$this->pub_section_current_get()]['leftmenu'];
        else
            return '';
    }


    /**
     * Возрващает массив груп, которым пренадлежит текущий администратор сайта
     *
     * Возрващает массив груп, которым пренадлежит текущий администратор сайта
     * @access private
     * @return array
     */
    function priv_admin_groups_curent_get()
    {
        if ((isset($_SESSION['vars_kernel']['back_groups'])) && (!empty($_SESSION['vars_kernel']['back_groups'])))
            return $_SESSION['vars_kernel']['back_groups'];
        else
            return array();
    }

    /**
     * Возвращает признак Главного администратора
     *
     * Возвращет <i>true</i> в случае, если текщий администратор является главным
     * (ROOTAdmin). Главный администратор имеет доступ ко всем настройкам
     * административного интерфейса
     * @access private
     * @return boolean
     */
    function priv_admin_is_root()
    {
        if (isset($_SESSION['vars_kernel']['root_admin']))
            return $_SESSION['vars_kernel']['root_admin'];
        else
            return false;
    }

    /**
     * Возвращает массив меток найденных в переданной строке
     *
     * Ищутся метки следующего вида <i>[#my_label#]</i>
     * @param string $html Строка для поиска меток
     * @access private
     * @return array
     */
    function priv_page_textlabels_get($html)
    {
        preg_match_all("/\\[\\#([a-zA-ZабвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ0-9_-]{1,100})\\#\\]/u",$html,$out);
        return $out;
    }


    /**
     * 301 Редирект на заданный URL
     *
     * Редирект происходит с помощью отсылки заголовков браузеру
     * @access private
     * @param string $url URL на который производится редирект
     * @return void
     */
    function priv_redirect_301($url)
    {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: ".$url);
        exit();
    }

    /**
     * Устанавливает текущую страницу сайта
     *
     * Под текущей страницей сайта понимается та страница, которую сейчас просматривает
     * пользователь, либо ту, с которой работает администратор сайта.
     * @param string $id ID страницы, с которой ведется работа
     * @param boolean $front_office Признак того, к фронт или бэкофису относится эта страница
     * @access private
     * @return void
     */
    function priv_page_current_set($id, $front_office = false)
    {
        if ($front_office)
        {
            $this->curent_page = $id;
        }
        else
        {
            $_SESSION['vars_kernel']['page_curent'] = $id;
            // Находим текущую главную страницу
            $mapsite = $this->pub_mapsite_get();
            $main_page = $id;
            while (!empty($mapsite[$main_page]['parent_id']))
            {
                $main_page = $mapsite[$main_page]['parent_id'];
            }
            $this->curent_page_main = $main_page;
            $_SESSION['vars_kernel']['page_main_curent'] = $main_page;
        }

    }


    /**
     * Возвращает идентификатор страницы сайта, на которой находится пользователь или администратор
     *
     * Чаще всего используются в публичных методах модуля, но может быть применена и в
     * административном интерфейсе модуля.
     *
     * Если вызов произошел из публичного метода модуля то возвращается идентификатор страницы
     * сайта, которая формируются для пользователя. Полученное значение может быть использовано
     * для формирования ссылок на эту страницу.
     *
     * В случае вызова из административного интерфейса модуля, будет возращен идентификатор страницы,
     * с которой работал (в последний раз) администратор сайта.
     * @access public
     * @return string
     */
    function pub_page_current_get()
    {


        if (!empty($this->curent_page))
            return $this->curent_page;

        //Значит вызов не из Фронт офиса, и текущую страницу берем из
        //сессии

        $action = $this->pub_httpget_get();

        if ((isset($action['id_p'])) && (!empty($action['id_p'])) && (!$this->curent_page_selected))
        {
            $this->curent_page_selected = true;
            $this->priv_page_current_set($action['id_p']);
        }

        if ((!isset($_SESSION['vars_kernel']['page_curent']) || empty($_SESSION['vars_kernel']['page_curent'])) &&
            (!isset($action['id_p']) || empty($action['id_p'])))
            $this->priv_page_current_set('index');

        $ret_id = '';
        if (isset($_SESSION['vars_kernel']['page_curent']))
            $ret_id = $_SESSION['vars_kernel']['page_curent'];

        return $ret_id;

    }


    /**
     * Устанавливает ID вызываемого модуля
     *
     * Устанавливает ID модуля перед началом работы публичных функций. Необходима для
     * автоматического обращения публичных методов ядра к свойствам конкретных модулей без
     * указания их ID
     * @access private
     * @param $id string Индетефикатор модуля
     */
    function priv_module_for_action_set($id)
    {
        $this->curent_modul = $id;
    }

    /**
     * Возвращает идентификатор текущего модуля
     *
     * Функция может использоваться как в публичных методах модуля, так и в
     * административном интерфейсе модуля.
     * Параметр $check_front_only используется только ядром и другими системными
     * функциями, в тех случаях, когда необходимо узнать идентификатор модуля, который
     * обрабатывается именно при работе фронт-офиса.
     * @param boolean $check_front_only Если <i>true</i> - то обрабатывается только текущий модуль фронт-офиса
     * @access public
     * @return string
     */
    function pub_module_id_get($check_front_only = false)
    {
        //Проверим если вызов этой функции идет для нужд Фронт-офиса, то тогда
        //возьмем переменную ядра

        if (!empty($this->curent_modul))
            return $this->curent_modul;

        if ($check_front_only)
            return '';

        $value = '';
        //Если она пуста, значит функция вызывается из АИ и возьмем данные из сессии
        //При этом, если текущим модулем является дочерний модуль - то вернем его
        //Если базовый модуль - то вернем его
        if (isset($_SESSION['vars_kernel']['modul_properties_curent_children']))
            $value = trim($_SESSION['vars_kernel']['modul_properties_curent_children']);
        elseif (isset($_SESSION['vars_kernel']['modul_properties_curent_base']))
            $value = $_SESSION['vars_kernel']['modul_properties_curent_base'];

        return $value;


    }

    /**
     * Получает массив методов модуля, к которым разрешено прямое обращение
     *
     * Смотри описание к {@link pub_da_metod_set}
     * Используется ядром, для того получения списка модулей, которые могут быть вызваны
     * @param string $id_modul Идентификатор модуля, чей метод нужно получить
     * @return array | bool
     * @access private
     */
    function priv_da_metod_get($id_modul = '')
    {
        if (empty($id_modul))
            $id_modul = $this->pub_module_id_get(true);
        if (empty($id_modul))
            return false;
        if (!isset($this->open_metod[$id_modul]))
            return false;
        return $this->open_metod[$id_modul];
    }

    /**
     * Задаёт имя метода модуля, участвующего в прямом вызове
     *
     * Прямой вызов - это возможность обратиться к действию модуля через
     * POST или GET запрос. Для того что бы методу модуля разрешить
     * такой доступ, в конструкторе класса модуля необходимо вызвать эту
     * функцию. Пример:
     *
     * <code>
     * class pageprint
     * {
     *
     *     function pageprint()
     *     {
     *         global $kernel;
     *
     *         $kernel->pub_da_metod_set('pub_metod', false);
     *     }
     *
     *     function pub_metod()
     *     {
     *         global $kernel;
     *
     *         $html = "";
     *         //...
     *         return $html;
     *     }
     *
     * }
     * </code>
     *
     * @param string $id_metod Имя метода, как он назван в классе, которые можно вызывать на прямую
     * @param boolean $run_and_stop Если TRUE, то после отработки метода его результат будет выведен на экран и дальнейшей обработки не будет, в противном случае метод отработает и страница будет строиться дальше по своему алгоритму.
     * @return boolean
     */
    function pub_da_metod_set($id_metod, $run_and_stop = true)
    {
        //По умолчанию выставляем для текущего модуля
        $id_modul = $this->pub_module_id_get(true);
        if (empty($id_metod) || empty($id_modul))
            return false;
        //Добавим этот метод в разрешённые
        $this->open_metod[$id_modul][$id_metod] = $run_and_stop;
        return true;
    }

    /**
     * Устанваливает ID модуля, с которым ведется работа в административном интерфесе
     *
     * Устанваливает ID текущего модуля в административном интерфесе.
     * @param string $id_modul
     * @param boolean $base Если <i>true</i> то происходит смена базового модуля
     * @access private
     * @return void
     */
    function priv_module_current_set($id_modul, $base = false)
    {
        if ($base)
        {
            $_SESSION['vars_kernel']['modul_properties_curent_base'] = trim($id_modul);
            unset($_SESSION['vars_kernel']['modul_properties_curent_children']);
        }
        else
        {
            $_SESSION['vars_kernel']['modul_properties_curent_children'] = trim($id_modul);
        }
    }


    /**
     * Определяет разрешение на доступ к разделу
     *
     * Определяет разрешение на доступ к одному из основных разделов
     * системы. Для Главного администратора всегда возвращается <i>true</i>
     * @param string $id_access Точка доступа
     * @param string $id_menu ID модуля
     * @access private
     * @return boolean
     */
    function priv_admin_access_for_group_get($id_access = '', $id_menu = '')
    {
        if ($this->priv_admin_is_root())
            return true;
        //Массив групп, к которым принадлежит текущий пользователь
        $agroups = $this->priv_admin_groups_curent_get();
        if (empty($id_menu))
            $id_menu = $this->pub_module_id_get();
        if ($id_menu == "stat")
            $id_menu = "kernel";
        return manager_users::admin_access_for_group_get(join(",", $agroups), intval(count($agroups)), $id_menu, $id_access);
    }

    /**
     * Формирования представления языковых переменных
     *
     * Возвращает представления массива языковых переменных
     * для заданного языка
     * @param array $label Массив языковых переменных
     * @param string $lang Двухбуквенный код языка
     * @param int $quot_action 0 - ничего не делаем с кавычками, 1 - заменяем на символы HTML, 2 - оставляем как есть, но слэшуем
     * @access private
     * @return array
     */
    function priv_textlabels_values_get($label, $lang, $quot_action = 1)
    {
        $str_tmp = join( $label, "," );
        $str_tmp = str_replace(",","','",$str_tmp);
        $str_tmp = "'".$str_tmp."'";

        $query = "SELECT `lang`, `element`, `text`
                  FROM `".$this->prefix."_all_lang`
                  WHERE (`lang` = '".$lang."') and ( `element` IN (".$str_tmp."))";

        $result = $this->runSQL($query);
        $array_text_tmp = array();

        while ($row = mysql_fetch_assoc($result))
        {

            $str = $row['text'];
            if ($quot_action == 1)
                $str = str_replace('"', '&quot;', $str);
            elseif ($quot_action == 2)
                $str = addslashes($str);

            $array_text_tmp[$row['element']] = $str;
        }
        $array_text = array();
        foreach ($label as $key)
        {
            if (isset($array_text_tmp[$key]))
                $array_text[] = $array_text_tmp[$key];
            else
                $array_text[] = $key;
        }
        return $array_text;
    }

    /**
     * Заменяет все переменные их текстовыми представлениями
     *
     * Заменяет текстовые переменные вида [#text_label#] их текстовыми представлениями
     * @param array $full_label содержит языковые переменные в виде массива
     * @param string $text_replace текст, который будет
     * @param string $html Строка, в которой происходит замена меток
     * @access private
     * @return string
     */
    function priv_textlabels_replace($full_label, $text_replace, $html)
    {
        $str = join($full_label,',');
        $str = str_replace('[#','/\[#',$str);
        $str = str_replace('#]','#\]/',$str);
        $tmp_a = explode(',',$str);
        return preg_replace($tmp_a, $text_replace, $html);

    }


    /**
     * Заменяет все метки на страницы
     *
     * Производит замену всех меток вида [# #] на их текстовое представление
     * в зависимости от языка
     * @param string $html Строка, в которой проиисходит замена
     * @param int $quot_action 0 - ничего не делаем с кавычками, 1 - заменяем на символы HTML, 2 - оставляем как есть, но слэшуем
     * @access private
     * @return string
     */
    function priv_page_textlabels_replace($html, $quot_action = 0)
    {
        $label = $this->priv_page_textlabels_get($html);
        if (!empty($label[0]))
        {
            $array_text  = $this->priv_textlabels_values_get($label[1], $this->priv_langauge_current_get(), $quot_action);
            $str = $this->priv_textlabels_replace($label[0],$array_text,$html);
        }
        else
            $str = $html;

        return $str;
    }


    /**
     * Заменяет текстовые метки в переданном контенте на их языковое представление
     *
     * В переданной строке производит поиск всех языковых меток и производит
     * их замену на соответствующее представление для текущего языка
     * @param string $html
     * @access public
     * @return string
     */
    function pub_page_textlabel_replace($html)
    {
        return $this->priv_page_textlabels_replace($html);
    }


    /**
     * Установка заданной кодовой страницы
     *
     * Заменяет в переданной строке метку <i>[#set_charset#]</i> на
     * кодовую страницу, выбранную у текущего администратора сайта
     * @param string $html
     * @access private
     * @return string
     */
    function priv_page_charset_set($html)
    {
        $html = str_replace ("[#set_charset#]", $this->priv_charset_current_get(), $html);
        return $html;

    }

    public static function is_backend()
    {
        return preg_match("/^\\/admin\\//",$_SERVER['REQUEST_URI']);
    }

    /**
     * Метод для вывода соформированной страницы на экран
     *
     * Конечный метод, который вызывается для вывода сформированной страницы сайта
     * на экран пользователя
     * @param string $html
     * @param boolean $for_edit Если true - то значит это вывод в редактор контента и не нужно делать ряд вещей
     * @param boolean $js_encode
     * @access private
     * @return string
     */
    function priv_output($html = "", $for_edit = false, $js_encode=false)
    {
        $is_xml_data = false;
        $is_backend=self::is_backend();
        //Заменим значения переменных на страницах
        //нужными значениями в зависимости от языка.
        if (!$this->header_is_sent && isset($_SERVER['HTTP_HOST']))
        {
            if (mb_strlen($html)>5 && mb_substr(mb_strtolower($html),0,5)=="<?xml")
            {
                header("Content-Type: text/xml; charset=".$this->priv_charset_current_get());
                $is_xml_data = true;
            }
            else
                header("Content-Type: text/html; charset=".$this->priv_charset_current_get());
            $this->header_is_sent = true;
        }

        if (!$for_edit && !$is_xml_data && !$is_backend)
        {
            $html = $this->priv_page_charset_set($html);
            if (defined('TIME_CREAT') && TIME_CREAT)
            {
                $this->priv_timer_stop();
                $elapsed = $this->priv_timer_elapsed();
                //если нашли </body>, то перед ним
                if (mb_strpos($html, "</BODY>")!==false)
                    $html = str_replace("</BODY>", "<!-- $elapsed --></BODY>", $html);
                elseif (mb_strpos($html, "</body>")!==false)
                    $html = str_replace("</body>", "<!-- $elapsed --></body>", $html);
                else //иначе в конце
                    $html .= "<!-- $elapsed -->";
            }
            //заменим %GET[param1]% %POST[param2]% %REQUEST[param3]% на их значения или пустые строки
            if (preg_match_all('/\\%(REQUEST|GET|POST)\\[([^<>]+)\\]\\%/U', $html, $matches, PREG_SET_ORDER))
            {
                foreach ($matches as $match)
                {
                    switch ($match[1])
                    {
                        default:
                        case 'POST':
                            $var = $_POST;
                            break;
                        case 'GET':
                            $var = $_GET;
                            break;
                        case 'REQUEST':
                            $var = $_REQUEST;
                            break;
                    }
                    $repl = "";
                    if (isset($var[$match[2]]))
                        $repl = htmlspecialchars($var[$match[2]]);
                    $html = str_replace($match[0], $repl, $html);
                }
            }
            //если требуется, HTTP-запрос и не-ajax запрос и не в админке, то добавим stat.php
            if (defined("GENERATE_STATISTIC") && GENERATE_STATISTIC && isset($_SERVER['HTTP_HOST']) && !$this->pub_is_ajax_request() && !$is_xml_data)
            {
                //если нашли </body>, то перед ним
                if (mb_strpos($html, "</BODY>")!==false)
                    $html = str_replace("</BODY>", "<script type='text/javascript' src='/stat.php'></script></BODY>", $html);
                elseif (mb_strpos($html, "</body>")!==false)
                    $html = str_replace("</body>", "<script type='text/javascript' src='/stat.php'></script></body>", $html);
                else //иначе в конце
                    $html .= "<script type='text/javascript' src='/stat.php'></script>";
            }

        }

        if ($js_encode && !$is_xml_data && defined('WEBFORM_CODING') && WEBFORM_CODING && !$is_backend)
        {
            $html = $this->pub_page_email_encode($html);
            $html = $this->pub_page_form_encode($html);
        }

        if (!$is_backend && preg_match_all("|\\%html_escape\\[(.*)\\]\\%|isU",$html, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $html= str_replace($match[0],htmlentities(strip_tags($match[1]),ENT_QUOTES,"UTF-8"),$html);
            }
        }

        if ($is_backend && !$for_edit)
            $html = $this->priv_page_textlabels_replace($html);
        print $html;
    }


    /**
     * Определяет, является ли текущий запрос ajax-запросом
     * @return bool
     */
    public function pub_is_ajax_request()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
            return true;
        else
            return false;
    }


    /**
     * Производит "кодирование" встречающихся E-mail адресов
     *
     * Функция предназначена для кодирования всех находящихся в строке
     * E-mail адресов, с тем что бы они не были "считаны" спамерами с сайта
     * @param string $page
     * @access public
     * @return string
     */
    function pub_page_email_encode($page)
    {
        $matches = false;
        if (preg_match_all ("/(<a[^>]+?mailto:.*?>.*?<\\/a>|<area.+?href.+?>)/is", $page, $matches))
        {
            foreach ($matches[1] as $value)
            {
                if (strstr($value, "@"))
                    $page=str_replace($value, $this->priv_string_js_encode($value), $page);
            }
        }
        return $page;
    }


    /**
     * Производит "кодирование" встречающихся форм
     *
     * Функция предназначена для кодирования всех находящихся в строке
     * форм, с тем что бы они не были "считаны" спамерами с сайта
     * @param string $page
     * @access public
     * @return string
     */
    function pub_page_form_encode($page)
    {
        $matches = false;
        if (preg_match_all ("/(<form.*?>|<input.*?>|<textarea.*?>.*?<\\/textarea>|<select.*?>.*?<\\/select>)/is", $page, $matches))
        {
            foreach ($matches[1] as $value)
            {
                $page=str_replace($value, $this->priv_string_js_encode($value), $page);
            }
        }
        return $page;
    }


    /**
     * Производит кодирование E-mail
     *
     * Кодировка e-mail, чтобы спамерские роботы не могли считать их.
     * @param string $string e-mail
     * @access private
     * @return string
     */
    function priv_string_js_encode($string)
    {
        $enc_string = '';
        $length = mb_strlen($string);
        $rnd=rand(3,8);
        for ($i=0; $i < $length; $rnd_old=$rnd, $rnd=rand(3,8), $i+=$rnd_old)
        {
            $current_str = mb_substr($string, $i, $rnd);
            $current_str="document.write('".addcslashes($current_str, "'\n\r")."');";
            $enc_string .=$current_str;
        }
        $html = "<script language=\"JavaScript\" type=\"text/JavaScript\">".$enc_string."</script>";
        //$html .= "<noscript>[#please_enable_js#]</noscript>";
        return $html;
    }



    /**
     * Производит регистрацию администратора сайта в сессии
     *
     * Вызывается после того как был введен логин и пароль для доступа в административный
     * раздел сайта. При успешной регистрации возвращает ID администратора сайта.
     * Возвращаемые значения:
     *  -2: Пользователь уже зарегистрирован
     *  -1: Пользователя не существует
     *      Во всех остальных случаях возвращается ID это пользователя
     * @param string $login
     * @param string $pass
     * @access private
     * @return string
     */
    function priv_session_admin_save($login, $pass)
    {
        $login = mysql_real_escape_string($login);
        $pass  = mysql_real_escape_string($pass);

        $query = "SELECT id, login, pass, lang, code_page
	        	  FROM ".$this->prefix."_admin
	    	      WHERE login='$login' AND pass='$pass' AND (enabled = 1)
    	    	  LIMIT 1
          		 ";
        $result = $this->runSQL($query);
        $row = mysql_fetch_assoc($result);

        if (!isset($row['id']))
            return -1;

        if (!$this->priv_admin_unique_check($row['id']))
            return -2;

        $_SESSION['vars_kernel']['back_users']      = trim($row['login']);
        $_SESSION['vars_kernel']['back_users_id']   = trim($row['id']);
        $_SESSION['vars_kernel']['lang']            = trim($row['lang']);
        $_SESSION['vars_kernel']['codepage']        = trim($row['code_page']);

        return trim($row['id']);
    }


    /**
     * Проверка на уникальность входящего администратора
     *
     * Выполняется при входе администратора и проверяет, не находится
     * ли такой админ уже в системе, чтобы под одним логином не могло
     * сидеть несколько человек одновременно.
     * Если админ уникальный, возвращается <i>true</i>, иначе <i>false</i>
     * @param int $id
     * @access private
     * @return boolean
     */
    function priv_admin_unique_check($id)
    {
        $unique = false;

        $sql = "SELECT
                id_admin AS admin
                FROM
                `".$this->prefix."_admin_trace`
                WHERE
                (
                time
                BETWEEN
                DATE_ADD(NOW(), INTERVAL -5 MINUTE)
                AND
                NOW()
                )
                AND id_admin='".$id."'
                ";
        $result = $this->runSQL($sql);

        if (!mysql_num_rows($result))
            $unique = true;

        return $unique;
    }

    /**
     * Производит регистрацию групп администратора в сессии
     *
     * В сессию записываются те группы, которым принадлежит вошедший администратор
     * @access private
     * @param string $login
     * @return string
     */
    function priv_session_groups_save($login)
    {
        $query = "SELECT user_id,group_id
                  FROM ".$this->prefix."_admin_cross_group
                  WHERE user_id='$login'
                  ";

        $result = $this->runSQL($query);

        $group_array = array();
        $str_link = '';

        while ($row = mysql_fetch_assoc($result))
        {
            $group_array[] = $row['group_id'];
            $str_link .="'".$row['group_id']."',";

        }
        $_SESSION['vars_kernel']['back_groups'] = $group_array;

        return $str_link;
    }


    /**
     * Записывает в сессию признак Главного администратора
     *
     * Если текущий администратор является главным, то информация об этом
     * записывается в сессию
     * @param string $str_group Строка с группами администратора
     * @access private
     * @return void
     *
     */
    function priv_session_main_admin_save($str_group)
    {
        $query = "SELECT id, main_admin
                  FROM ".$this->prefix."_admin_group
                  WHERE (id IN (".mb_substr($str_group,0,-1).")) and (main_admin = 1)
                  LIMIT 1
                  ";

        $result = $this->runSQL($query);
        $row = mysql_fetch_assoc($result);
        if ($row)
            $_SESSION['vars_kernel']['root_admin'] = true;
        else
            $_SESSION['vars_kernel']['root_admin'] = false;

    }



    /**
     * Производит регистрацию администратора в системе
     *
     * Производит регистрацию администратора в системе
     * @param string $login
     * @param string $password
     * @access private
     * @return void
     */
    function priv_admin_register($login, $password)
    {
        if ((!empty($login)) && (!empty($password)))
        {
            $id_user = $this->priv_session_admin_save($login, $password);
            if ($id_user == -1)
                $_SESSION['vars_kernel']['errore_register'] = '[#start_login_not_exist#]';

            if ($id_user == -2)
                $_SESSION['vars_kernel']['errore_register'] = '[#start_login_failed_entered#]';

            if ($id_user > 0)
            {
                $arr_groups = $this->priv_session_groups_save($id_user);
                if (!empty($arr_groups))
                    $this->priv_session_main_admin_save($arr_groups);
            }
        }
        $this->pub_redirect_refresh_global("/admin/");
    }


    /**
     * Заполняет при необходимости пустое значение массива значением по умолчанию
     *
     * Проверяет массив $obj на наличие ключа $name_value и если не находит
     * его, устанавливает этому ключу значение $def_value
     * @param array $obj
     * @param string $name_value
     * @param string $def_value
     * @access private
     * @return array
     */
    function priv_value_check($obj, $name_value, $def_value)
    {
        $val_ret = $def_value;

        if (isset($obj[$name_value]))
        {
            if (!empty($obj[$name_value]))
                $val_ret = $obj[$name_value];
        }
        return $val_ret;
    }


    /**
     * Возвращает массив со всеми свойствами выбранной страницы.
     *
     * Возвращает массив вида
     * [title_other] - title страницы (если не задан, используется caption)
     * [name_title]
     * [template] - шалбон это страницы
     * [link_other_page] - автоматически переходить на указанную страницу
     * [mapsite1_visible], [visible], [feedback1_visible] - свойства, которые были приписаны странице модулями
     * [caption] - название страницы
     * @param string $id ID страницы
     * @access private
     * @return array
     */
    function priv_page_properties_get($id)
    {
        $out = array();

        if (!empty($id))
        {
            $row = $this->db_get_record_simple("_structure","id='".$id."'", "id, properties, caption");
            if (!empty($row['properties']))
                $out = unserialize($row['properties']);

            $out['caption'] = $row['caption'];
        }
        return $out;
    }

    /**
     * Возвращает массив ссылок, с соответсвующими методами
     *
     * Значение каждой метки вытаскиваются через наследование(рекурсию),
     * так как они могут быть отнаследованы от разных страниц. Просмотр
     * идёт до страницы без родителя, но наследуется первое	указанное
     * значение
     * @param array $array_link
     * @param string $id_page
     * @param $serialize_all_data все данные?
     * @access private
     * @return array
     */
    function priv_page_real_link_get($array_link, $id_page, $serialize_all_data=false)
    {
        //Будем просматривать страницы вверх от текущей до тех пор, пока весь массив
        //ссылок не будет заполнены значениями методов
        if (empty($id_page))
            return $array_link;
        $row = $this->db_get_record_simple("_structure","id='".$id_page."'", "id, parent_id, serialize");
        if (!empty($row['serialize']))
        {
            $serialize = unserialize(stripslashes($row['serialize']));
            foreach ($serialize as $key => $val)
            {
                if (!isset($val['postprocessors']) || !is_array($val['postprocessors']))
                    $val['postprocessors']=array();
                if ($serialize_all_data)
                {
                    if (isset($array_link[$key]))
                    {
                        if (!is_array($array_link[$key]))
                        {
                            $array_link[$key] = $val;
                            $array_link[$key]['page'] = $id_page;
                        }
                    }
                    else
                    {
                        $array_link[$key] = $val;
                        $array_link[$key]['page'] = $id_page;
                        $array_link[$key]['isadditional'] = true;
                    }
                }
                elseif (isset($array_link[$key]) && !is_array($array_link[$key]))
                {
                    $array_link[$key] = $val;
                    // Надо знать от какой страницы мы наследуем
                    $array_link[$key]['page'] = $id_page;
                }
            }

        }
        if (!empty($row['parent_id']))
            $array_link = $this->priv_page_real_link_get($array_link, $row['parent_id'] ,$serialize_all_data);
        return $array_link;
    }

    /**
     * Возвращает массив Serialize для указанной страницы
     *
     * Serialize массив содержит информацию о привязке действий к меткам
     * @param string $id_page - ID старницы
     * @access private
     * @return array
     */
    function priv_page_serialize_get($id_page)
    {
        if (!empty($id_page))
        {
            $row = $this->db_get_record_simple("_structure","id='".$id_page."'", "id, serialize");
            if ($row && !empty($row['serialize']))
                return unserialize(stripslashes($row['serialize']));

        }
        return array();
    }



    /**
     * Возвращает массив подключенных модулей
     *
     * При указании идентификатора базового модуля возвращается массив всех
     * дочерних модулей, этого базового модуля. Если параметр не указан, то
     * будет возвращён массив всех дочерних модулей, всех базовых модулей,
     * проинсталлированных в CMS.
     * @param String $id_modul Идентификатор базового модуля, для которого нужно выбрать
     *                         дочерние, если не указан то выбираются все
     * @access public
     * @return array
     */
    function pub_modules_get($id_modul="")
    {
        $mod = new manager_modules();
        return $mod -> return_modules($id_modul);
    }



    /**
     * Возвращает конкретное свойство конкретной страницы (с наследованием, если это нужно)
     *
     * Функция применяется в публичных метода модулях и позволяет модулю получить доступ к значению
     * свойства страницы, из тех свойств, которые модуль установил. <br>
     * Возвращается массив следующего вида:
     * <code>
     *  $arr = pub_page_property_get($kernel->pub_page_current_get(),'show_info')
     *  $arr['isset'];      // Признак того существует данное свойство или нет;
     *  $arr['naslednoe'];  //Признак того унаследовано значение или нет
     *  $arr['value'];      //Непосредственно значение
     * </code>
     *
     * @param $id_page string Идентификатор страницы
     * @param $id_prop string Идентификатор свойства
     * @param $nasledovat boolean Применять наследование
     * @access public
     * @return array
     */
    function pub_page_property_get($id_page, $id_prop, $nasledovat = true)
    {
        $out['isset'] = false;
        $out['value'] = '';

        if (mb_strpos($id_prop, "_") === false)
            $id_modul = $this->pub_module_id_get(true);
        if (!empty($id_modul))
            $search_prop = $id_modul.'_'.$id_prop;
        else
            $search_prop = $id_prop;

        if (empty($id_page))
            return $out;
        $this->pub_mapsite_cashe_create();

        //Данные будем брать из кэша, для ядра он сформирован линейным способом
        if (!empty($this->mapsite_cache[$id_page]['properties']))
        {
            //Проверим, если запрашиваются свойства хранящиеся в колонке
            if (mb_strtolower($search_prop) == "caption")
            {
                $out['isset'] = true;
                $out['value'] = $this->mapsite_cache[$id_page]['caption'];
                $out['naslednoe'] = false;
            }

            $serialize = $this->mapsite_cache[$id_page]['properties'];
            if (isset($serialize[$search_prop]))
            {
                $out['isset'] = true;
                $out['value'] = $serialize[$search_prop];
                $out['naslednoe'] = false;
            }
        }
        if (($nasledovat) && (!empty($this->mapsite_cache[$id_page]['parent_id'])) && (!$out['isset']))
        {
            //Значит нужно узнать это свойство у родителя
            $out = $this->pub_page_property_get($this->mapsite_cache[$id_page]['parent_id'], $id_prop, $nasledovat);
            $out['naslednoe'] = true;
        }

        return $out;
    }

    /**
     * Сохраняет свойства страницы переданные в массиве
     *
     * Используется в админке в разделе структура для сохранения изменений
     * @param string $id ID страницы
     * @param array $prop массив со свойствами страницы
     * @param string $caption название страницы
     * @access private
     * @return void
     */
    function priv_page_properties_set($id, $prop, $caption)
    {
        if ((isset($id)) && (!empty($id)))
        {
            $query = 'UPDATE `'.$this->pub_prefix_get().'_structure`
                      SET properties = "'.mysql_real_escape_string(serialize($prop)).'",
                      	  caption = "'.$caption.'"
                      WHERE id = "'.$id.'"
                      ';
            $this->runSQL($query);
        }
    }

    /**
     * Устанавливает конктретное свойство конкрентной странице
     *
     * Аналогично {@link priv_page_properties_set} но устанавливает только одно
     * заданное свойство
     * @param string $id_page  Индентефикатор страницы
     * @param string $id_prop Индетефикатор свойства
     * @param string $value Значение свойства
     * @access private
     * @return void
     */
    function priv_page_property_set($id_page, $id_prop, $value)
    {

        $id_modul = $this->pub_module_id_get(true);
        if (!empty($id_modul))
            $search_prop = $id_modul.'_'.$id_prop;
        else
            $search_prop = $id_prop;

        if (!empty($id_page))
        {
            $this->pub_mapsite_cashe_create();

            $serialize = $this->mapsite_cache[$id_page]['properties'];
            $serialize[$search_prop] = $value;

            //Теперь запишем это свойство в таблицу и заново заполним кэш
            $query = 'UPDATE `'.$this->pub_prefix_get().'_structure`
                      SET properties = "'.addslashes(serialize($serialize)).'"
                      WHERE id = "'.$id_page.'"
                      ';
            //$result =
            $this->runSQL($query);

            $this->mapsite_cache = '';
            $this->pub_mapsite_cashe_create();
        }
    }


    /**
     * Возвращает массив всех представлений заданного $id в разных языках
     *
     * В возвращаемом массиве язык является ключём массива, а значение -
     * представлением id в этом языке.
     * @param string $id Языковая переменная
     * @access private
     * @return array
     */
    function priv_textlabel_values_get($id)
    {
        $out = array();
        $items = $this->db_get_list_simple("_all_lang","element = '".$id."' GROUP BY lang");
        foreach ($items  as $item)
        {
            $out[$item['lang']]=$item['text'];
        }
        return $out;

    }

    /**
     * Возвращает значения свойства модуля
     *
     * Функция может вызываться как для параметров текущего модуля (из которого происходит её вызов)
     * так и с указанием идентификатора конкретного модуля, чей параметр необходимо узнать. Помимо этого возможно
     * указать признак, разрешающий получить значение параметра с использованием механизма наследования.
     * <br>
     * Для использования данной функции внутри конкретного модуля для получения значения свойства этого модуля
     * необходимо указать только имя этого свойства
     * <code>$kernel->pub_modul_properties_get('<имя свойства модуля>')</code>
     * <br>
     * Возвращается массив следующего вида:
     * <code>
     *  $arr = $kernel->pub_modul_properties_get('user_template')
     *  $arr['isset'];      // Признак того существует данное свойство или нет;
     *  $arr['naslednoe'];  //Признак того унаследовано значение или нет
     *  $arr['value'];      //Непосредственно значение
     * </code>
     * @param string $name_prop Имя получаемого значения свойства
     * @param string $id_modul Если необходимо, идентификатор модуля
     * @param Boolean $nasled Если необходимо, признак возможности использования наследования
     * @access public
     * @return array
     */
    function pub_modul_properties_get($name_prop, $id_modul = '', $nasled = true)
    {
        $id_modul_curent = $this->pub_module_id_get();
        if (empty($id_modul) && !empty($id_modul_curent))
            $mod = $id_modul_curent;
        else
            $mod = $id_modul;

        if (empty($mod))
            return false;
        $row = $this->db_get_record_simple("_modules","id='".$mod."'");
        $serialize = array();
        if (!empty($row['serialize']))
            $serialize = unserialize($row['serialize']);

        if ((!isset($serialize[$name_prop])) && (!empty($row['parent_id'])) && ($nasled))
        {
            $return_array = $this->pub_modul_properties_get($name_prop, $row['parent_id']);
            $return_array['naslednoe'] = true;
        }
        else
        {
            $return_array = array();
            $return_array['isset'] = isset($serialize[$name_prop]);
            $return_array['naslednoe'] = false;
            $return_array['value'] = '';
            if (isset($serialize[$name_prop]))
                $return_array['value'] = $serialize[$name_prop];
        }
        return $return_array;
    }


    /**
     * Функция подготовки строки для записи в mySql
     *
     * Функция производит проверку на использование магических кавычек и
     * необходимость их экранирования
     * @param string $str Обрабатываемая строка
     * @access public
     * @return string
     */
    function pub_str_prepare_set($str)
    {
        $str = trim($str);
        if (!get_magic_quotes_gpc())
            $str = mysql_real_escape_string($str);
        return $str;
    }

    /**
     * Функция подготовки строки после взятия из mySql
     *
     * Функция производит обработку строки после того как она взята из mySql
     * @param string $str Обрабатываемая строка
     * @param bool $html Признак применения метода htmlspecialchars к возвращаемой строке
     * @access public
     * @return string
     */

    function pub_str_prepare_get($str, $html = false)
    {
        $str = trim($str);
        $str = stripslashes($str);
        if ($html)
            $str = htmlspecialchars($str);
        return $str;
    }


    /**
     * Обновляет языковые представления в базе данных
     *
     *
     * @param string $id ID языковой переменной
     * @param array $new_lang соержит новые значения для языковой переменной. Ключ - идентификатор языка.
     * @return void
     */
    public function pub_textlabels_update($id, $new_lang)
    {
        foreach ($new_lang as $key => $val)
        {
            $val = $this->pub_str_prepare_set($val);
            if (strlen($key) == 2)
            {
                $query = 'UPDATE '.$this->pub_prefix_get().'_all_lang
        	      SET text = "'.mysql_real_escape_string($val).'"
                  WHERE (lang = "'.$key.'") and (element = "'.$id.'")
				  ';
                $this->runSQL($query);
            }
        }
    }


    /**
     * Сохраняет объект модуля, который уже создан при обрабтке ссылок
     *
     * @param string $id_modul
     * @param object $obj
     * @access private
     * @return void
     */
    function priv_module_including_set($id_modul, $obj)
    {
        $this->include_modules[$id_modul] = $obj;
    }

    /**
     * Возвращает объект модуля по его ID, если он уже подключался
     *
     * Если объект ещё не подключался то возвращается <i>false</i>
     * @param string $id_modul
     * @access private
     * @return object $obj если он не подключался возвращается <i>false</i>
     */

    function priv_module_including_get($id_modul)
    {
        if (isset($this->include_modules[$id_modul]))
            return $this->include_modules[$id_modul];
        else
            return false;
    }


    /**
     * Глобальное перенаправление на заданный URL
     *
     * В качестве параметра передаётся URL, начиная от доменного
     * имени, на который необходимо осуществить перенаправление.
     * @access public
     * @param string $url URL на который производится перенаправление.
     * @param bool $scheme Признак перехода по защищенному протоколу.
     * @return void
     */

    function pub_redirect_refresh_global($url, $scheme = SSL_CONNECTION)
    {
        if (preg_match('/^\/?admin/', $url))
            header("Location: ".($scheme?'https':'http')."://".$_SERVER['HTTP_HOST'].$url);
        else
            header("Location: http://".$_SERVER['HTTP_HOST'].$url);
        die;
    }


    /**
     * Переход на заданный URL через JavaScript (перегрузка через Ajax)
     *
     * Будет выведен код JavaScript для перезагрузки только
     * области контента. В этом случае $url должен начинаться с идентификатора действия,
     * которое будет доступно через метод {@link pub_section_leftmenu_get()}, после этого могут
     * идти дополнительные параметры. Пример передаваемого в таком случае URL-а:
     * <code>$kernel-> pub_redirect_refresh ("myaction&param1=value1&param2=value2");</code>
     * @access public
     * @param string $url URL на который производится редирект
     * @return void
     */
    function pub_redirect_refresh($url)
    {
        $this->priv_output('<script type="text/javascript">start_interface.link_go("'.$url.'")</script>');
        die;
    }

    public function get_postprocessors_dir()
    {
        return dirname(__FILE__)."/postprocessors/";
    }

    /**
     * Возвращает список имеющихся постпроцессоров
     * @return array
     */
    public function get_postprocessors()
    {
        if (!is_null($this->postprocessors))
            return $this->postprocessors;
        require_once dirname(__FILE__)."/postprocessor.php";
        $ret = array();
        $ppdir=$this->get_postprocessors_dir();

        if (!is_dir($ppdir))
            return $ret;
        $dh = opendir($ppdir);
        $curr_lang=$this->priv_langauge_current_get();
        while ($file = readdir($dh))
        {
            if (!is_file($ppdir.$file) || !preg_match('~\.php$~i',$file))
                continue;
            include_once($ppdir.$file);
            $class_name=preg_replace('~\.php$~i','',$file);
            if (!class_exists($class_name))
                continue;
            /** @var $pclass postprocessor */
            $pclass = new $class_name;
            if (!$pclass instanceof postprocessor)
                continue;
            $ret[$class_name]= $pclass->get_name($curr_lang);
        }
        closedir($dh);
        $this->postprocessors=$ret;
        return $ret;
    }

    /**
     * Переход на заданный URL, с указанием нижнего меню (перегрузка всей страницы)
     *
     * Работ функции идентична {@link pub_redirect_refresh}, с той лишь
     * разницей, что перегружается вся страница, а не только область конента
     * @access public
     * @param string $url URL на который производится переход
     * @param boolean $scheme
     * @return void
     */
    function pub_redirect_refresh_reload($url, $scheme = SSL_CONNECTION)
    {

        //Значит сначала нужно определить установить новый пункт меню
        //и затем уже сделать переход
        $link = '';
        if (!preg_match("/\\&/i",$url))
            $name_menu = $url;
        else
        {
            //Значит передаются ещё какие-то параметры.. их нужно послать дальше по URL-у
            $ret = array();
            preg_match_all("/^(.*?)\\&(.*?)$/i", $url, $ret);
            $link = $ret[2][0];
            $name_menu = $ret[1][0];
        }
        $this->priv_section_leftmenu_set($name_menu, true);
        header("Location: ".($scheme?'https':'http')."://".$_SERVER['HTTP_HOST'].'/admin/index.php?'.$link);
    }


    /**
     * Возвращает ссылку для формы POST
     *
     * Формирует ссылку для использование её в поле "action" тега <form>
     * Переход осуществляется целиком во всём окне
     * @access public
     * @param string $url часть ссылки с параметрами для модуля, начинается с идентификатора пункта левого меню
     * @param bool $set_left_menu признак того, что ссылка начинается с пункта левого меню.
     * @return string
     */

    function pub_redirect_for_form($url, $set_left_menu = true)
    {
        $url = trim($url);

        if ($set_left_menu)
            $str = "/admin/index.php?action=set_left_menu&leftmenu=";
        else
        {
            $str = "/admin/index.php";
            if (!empty($url))
                $str .= '?';
        }

        $str .= $url;
        return $str;
    }


    /**
     * Метод для вывода отладочной информации
     *
     * Вывод отладочной информации на экран
     * @return void
     * @param mixed $text переменная, содержимое которой нужно отобразить
     * @param boolean $direct_output Если задано в <b><i>true</i></b> то информацию будет выведена непосредственно в поток
     * @access public
     *
     */
    function debug($text, $direct_output = false)
    {
        $str  = '';
        if (is_array($text) || is_object($text))
        {
            $str  = '<pre>';
            $str .= highlight_string(trim(print_r($text, true)));
            $str .= '</pre>';
        }
        else
            $str .= $text."<br>";

        //Значит надо вывести через обвязку JavaScript
        if ($direct_output)
            print_r($str);
        else
            $this->message_debug[] = $str;

    }

    function priv_debug_get($jscript = false)
    {
        $str = '';
        if ($jscript && !empty($this->message_debug))
        {
            //$str .= "message_debug = new Array();";
            //Выводим для функции яваскрипта
            foreach ($this->message_debug as $val)
            {
                $val = addslashes($val);
                $val = preg_replace("/(\r\n|\n|\r)/", "<br><br>", $val);
                $str .= "message_debug[message_debug.length] = '".$val."';";
            }
            $str = '<script>'.$str.'</script>';
        } else
        {
            if (!empty($this->message_debug))
                $str = "<p>".join("</p><p>",$this->message_debug)."</p>";
        }
        return $str;

    }

    /**
     * Создает линейный массив с картой сайта
     *
     * Такой масив удобен для различных функций обращающихся напрямую к свойствам
     * страницы или её меткам по её ID
     * @access private
     * @return array
     */
    function priv_mapsite_create_line()
    {
        $items = $this->db_get_list_simple("_structure","true ORDER BY parent_id, order_number");
        //Переберем результаты запроса
        $pages = array();
        $this->mapsite_cache_tree = array();
        foreach ($items as $row)
        {

            $pages[$row['id']]['id'] = $row['id'];
            $pages[$row['id']]['parent_id'] = $row['parent_id'];
            $pages[$row['id']]['caption'] = $row['caption'];

            $pages[$row['id']]['properties'] = array();
            if (isset($row['properties']))
                $pages[$row['id']]['properties'] = unserialize($row['properties']);

            $pages[$row['id']]['curent'] = ($this->pub_page_current_get() == $row['id']);

            // Добавим информации для построения дерева структруы
            if (!empty($row['parent_id']))
                $this->mapsite_cache_tree[$row['parent_id']][] = $row['id'];
        }
        return $pages;
    }

    /**
     * Создает древовидный массив с картой сайта
     *
     * Такой масив удобен в тех случая когда важно подченненость страниц
     * Функция рекурсивная
     * @access private
     * @param $page_id страница от которой строится дерево
     * @return array
     */
    function priv_mapsite_create_tree($page_id = '')
    {
        //Составим запрос
        $pages = array();
        if (isset($this->mapsite_cache_tree[$page_id]))
        {
            foreach ($this->mapsite_cache_tree[$page_id] as $val)
            {
                $pages[$val] = $this->mapsite_cache[$val];
                $pages[$val]['include'] = $this->priv_mapsite_create_tree($val);
            }
        }
        return $pages;

    }

    /**
     * Особое построение линейной структуры из линейной же, но только
     * для страниц подченённых указанной
     *
     * Надо разобраться
     * @param string $page_id
     * @param array $pages
     * @return array
     * @access private
     */
    function priv_mapsite_create_line_for_page($page_id = '', $pages = array())
    {
        if (isset($this->mapsite_cache[$page_id]))
            $pages[$page_id] = $this->mapsite_cache[$page_id];

        //Теперь провреим, есть ли у этой страницы дети
        if (isset($this->mapsite_cache_tree[$page_id]))
        {
            foreach ($this->mapsite_cache_tree[$page_id] as $val)
            {
                $pages[$val] = $this->mapsite_cache[$val];
                $pages = $this->priv_mapsite_create_line_for_page($val, $pages);
            }
        }
        return $pages;

    }

    //********************************************************************************
    //			Функции, используемые другими модулями и объектами
    //********************************************************************************

    /**
     * Формирует массив с картой сайта
     *
     * Функция используются модулями для необходимости получить структуру сайта.
     * Массив имеет следующий вид:
     * Ключ - id страницы. Значение - массив из следующих ключей (caption, properties, curent, include)
     * curent - флаг того, что данная страница является текущей
     * include - массив(идентичный) страниц, подчинённых данной.
     * @param  int $type Тип выходного массива (0 - Линейный массив, 1 = древовидный массив)
     * @param string $id Идентификатор страницы, от которой формируется структура
     * @access public
     * @return array
     */

    function pub_mapsite_cashe_create($type = 0, $id = '')
    {

        //В любом случае создаем линейную карту
        if (empty($this->mapsite_cache))
            $this->mapsite_cache = $this->priv_mapsite_create_line();

        //Вариант, когда нам нужна просто вся линейная карта
        if (($type == 0) && (empty($id)))
            return $this->mapsite_cache;

        //Это тоже линейная карта, но построенная уже от конкретной страницы
        //Поэтому нужно учитывать дерево страниц
        if (($type == 0) && (!empty($id)))
            return $this->priv_mapsite_create_line_for_page($id);


        //Древовидная будет по сложнее
        $pages = array();
        $pages_return = array();
        if ($type == 1)
        {
            //Создадим со страницы, если задан конкретный ID
            if ((!empty($id)) && (isset($this->mapsite_cache[$id])))
                $pages[$id] = $this->mapsite_cache[$id];


            //Если конкретный ID не задан, или по нему не получилось.
            //Переберем все
            if (empty($pages))
            {
                foreach ($this->mapsite_cache as $val)
                {
                    if (!empty($val['parent_id']))
                        break;

                    $pages[$val['id']] = $val;
                }
            }

            //Имеем массив страниц, для которых нужно построить дерево подчинения
            //это сделаем как раз через рекурсивную функцию
            foreach ($pages as $key => $val)
                $pages_return = $this->priv_mapsite_create_tree($key);

        }
        return $pages_return;
    }


    /**
     * Возвращает массив страниц, входящих в дорогу
     *
     * Дорога - ветка структуры, в которой находиться пользователь.
     * Массив имеет следующий вид:
     * [pagefaq] => Array
     *   (
     *       [id] => pagefaq - ID страницы
     *       [parent_id] => - rus родительская страница
     *       [caption] => Вопросы и ответы - название (caption) страницы
     *       [properties] => Array - свойства страницы
     *           (
     *               [title_other] => 1 - флаг, указывающий на то, что title страницы должен быть иным, чем ее название
     *               [name_title] => Вопросы и ответы - title страницы
     *           )
     *
     *       [curent] => 1 - флаг, указывающий на то, что данная страница является текущей
     *   )
     * @param string $id_page ID страницы, для которой необходимо построить дорогу, если не задано - то для текущей
     * @access public
     * @return array
     */
    function pub_waysite_get($id_page = '')
    {
        if (empty($id_page))
            $id_page = $this->pub_page_current_get();

        $this->pub_mapsite_cashe_create();

        //Если дорога пустая - то построим её
        if (empty($this->waysite_cache))
        {
            $tmp = array();
            do
            {
                if (isset($this->mapsite_cache[$id_page]))
                {
                    $tmp[] = $this->mapsite_cache[$id_page];
                    if (!empty($this->mapsite_cache[$id_page]['parent_id']))
                        $id_page = $this->mapsite_cache[$id_page]['parent_id'];
                    else
                        $id_page = '';
                }
                else
                    break;
            }
            while (!empty($id_page));
            krsort($tmp);
            //$this->waysite_cache = array();

            //Сделаем в качестве ключа id страницы
            foreach ($tmp as $val)
                $this->waysite_cache[$val['id']] = $val;

        }
        //$this->debug($this->waysite_cache);
        return $this->waysite_cache;

    }


    /**
     * Добавляет страницы в кэш дороги
     *
     * При этом, $adds_array состоит из двух элементов:
     * $adds_array[url] - URL, куда приведет клик по элементу дороги
     * $adds_array[caption] - название (текст) элемента дороги
     * Добавление происходит в конец пути, т.е. ПОСЛЕ текущей страницы
     * @param array $adds_array
     * @access public
     * @return array
     */
    function pub_waysite_set($adds_array)
    {
        $pages_array = $this->pub_waysite_get();
        $pages_array['additional_way'][] = $adds_array;
        $this->waysite_cache = $pages_array;
        return $this->waysite_cache;
    }


    /**
     * Получить массив файлов в указанном каталоге
     *
     * Возвращает массив файлов содержащихся в каталоге, переданном в качестве параметра.
     * В качестве ключа элемента массива используется имя файла вместе с путём к нему,
     * а в качестве значения – только имя файла.
     * @param string $path Путь к папке, в которой необходимо считывать содержимое
     * @access public
     * @return array
     */
    function pub_files_list_get($path)
    {

        $ret = array();
        if (!(empty($path)))
        {
            $d = dir($path);
            while (false !== ($entry = $d->read()))
            {
                $link = $path.'/'.$entry;
                if (is_file($link))
                    $ret[$link] = $entry;
            }
            $d->close();
        }
        return $ret;
    }


    /**
     * Получить массив каталогов в указанном каталоге
     *
     * @param string $path Путь к папке, в которой необходимо считывать содержимое
     * @access public
     * @return array
     */
    function pub_dirs_list_get($path)
    {
        $ret = array();
        if (!$path)
            return $ret;
        if (substr($path,-1)!='/')
            $path.='/';
        if (!is_dir($path))
            return $ret;
        $d = dir($path);
        while (false !== ($entry = $d->read()))
        {
            if ($entry=='.' || $entry=='..')
                continue;
            $link = $path.$entry.'/';
            if (is_dir($link))
                $ret[$link] = $entry;
        }
        $d->close();
        return $ret;
    }

    /**
     * Получить массив файлов в указанном каталоге
     *
     * Возвращает массив файлов содержащихся в каталоге, переданном в качестве параметра.
     * В качестве ключа элемента массива используется имя файла вместе с путём к нему,
     * а в качестве значения – только имя файла.
     * @param string $path Путь к папке, в которой необходимо считывать содержимое
     * @access public
     * @return array
     */
    function pub_files_list_recursive_get($path)
    {

        $ret = array();
        if (!(empty($path)))
        {
            $d = dir($path);
            while (false !== ($entry = $d->read()))
            {
                if (($entry == ".") || ($entry == ".."))
                    continue;
                if (substr($path,-1)!="/")
                    $link = $path.'/'.$entry;
                else
                    $link = $path.$entry;
                if (is_file($link))
                    $ret[$link] = $entry;
                elseif(is_dir($link))
                    $ret += $this->pub_files_list_recursive_get($link."/");
            }
            $d->close();
        }
        return $ret;
    }


    /**
     * Вызывается при изменении ID страницы
     *
     * Производит замену ID страницы на новый, с изменением всех мест,
     * где есть этот ID. Возвращает <i>true</i> если операция прошла успешно
     * и <i>false</i> в противном случае
     * @param string $old_id
     * @param string $new_id
     * @access private
     * @return boolean
     */
    function priv_page_id_replace($old_id, $new_id)
    {
        //Проверка на пустые значения
        if ((empty($old_id)) || (empty($new_id)))
            return false;

        $old_id = mb_strtolower(trim($old_id));
        $new_id = mb_strtolower(trim($new_id));

        //Проверим, что бы страницы с таким ID небыло в базе
        if ($this->priv_page_exist($new_id))
            return false;

        //Прежде всего, найдём файлы контента (если они есть) для этой страницы, и поменяем их
        //имена на новые
        $out = $this->pub_page_property_get($old_id,'template');
        if ($out['isset'])
        {
            $html_template = file_get_contents($out['value']);
            $curent_link = $this->priv_page_textlabels_get($html_template);
            $curent_link = $curent_link[1];

            //Имея массив ссылок, используемых в шаблоне, просто проверим, есть ли файлы на эти ссылки
            //foreach ($curent_link as $key => $val)
            foreach ($curent_link as $val)
            {
                $str_link_file_old = PATH_PAGE_CONTENT.'/'.$old_id.'_'.$val.'.html';
                $str_link_file_new = PATH_PAGE_CONTENT.'/'.$new_id.'_'.$val.'.html';
                if (file_exists($str_link_file_old))
                {
                    //$this->debug($str_link_file_new);
                    if (file_exists($str_link_file_new))
                        unlink($str_link_file_new);


                    if (!rename($str_link_file_old, $str_link_file_new))
                        return false;
                }
            }
        }

        //теперь сразу заменим все упоминания старого id в таблице структуры у подчинённых страниц...
        $query = 'UPDATE `'.$this->pub_prefix_get().'_structure`
        	      SET parent_id = "'.$new_id.'"
                  WHERE (parent_id = "'.$old_id.'")
				  ';
        //$result =
        $this->runSQL($query);

        //... и собственно у непосредственно самой страницы
        $query = 'UPDATE `'.$this->pub_prefix_get().'_structure`
        	      SET id = "'.$new_id.'"
                  WHERE (id = "'.$old_id.'")
				  ';
        //$result =
        $this->runSQL($query);

        //Обновим mapsite_cashe
        $this->mapsite_cache = '';
        $this->pub_mapsite_cashe_create();

        //Обновим ID
        $this->priv_page_current_set($new_id);

        return true;
    }


    /**
     * Определяет, существует ли страница с данным ID
     *
     * @param string $id_page Провреяемое ID
     * @access public
     * @return boolean
     */
    public function priv_page_exist($id_page)
    {
        $row = $this->db_get_record_simple("_structure","id='".$id_page."'");
        if ($row)
            return true;
        else
            return false;

    }


    //********************************************************************************************************************
    //*			Функции используемые модулями для работы с пользователеми сайта
    //*******************************************************************************************************************


    /**
     * Добавляет нового пользователя сайта (фронт-офиса).
     *
     * Возвращает идентификатор вновь добавленного пользователя либо
     * код ошибки (с отрицательным знаком).
     *		-1 : пользователь с таким логином (еmail-ом) уже существует и НЕ подтвержден
     *		-2 : пользователь с таким логином (еmail-ом) уже существует и подтвержден
     * @param string $login
     * @param string $password
     * @param string $email
     * @param string $name
     * @return int
     */
    function pub_user_add_new($login, $password, $email, $name)
    {
        return manager_users::user_add_new($login, $password, $email, $name);
    }




    /**
     * Производит авторизацию пользователя сайта
     *
     * Регистрация происходит по переданному логину и паролю и делает этого пользователя текущим.
     * В качестве логина может быть передан как непосредственно логин так и e-mail.
     *		 1 : пользователь с таким логином(еmail-ом) успешно зарегистрирован.
     *		-1 : пользователь с таким логином(еmail-ом) не существует.
     *		-2 : пользователь с таким логином(еmail-ом) отключен администратором сайта
     *		-3 : пользователь с таким логином(еmail-ом) не подтвердил ещё регистрацию.
     * @param string $login
     * @param string $password
     * @param boolean $unic_login Если true - то уникальность пользователя проверяется по логину, если false - то по email-у
     * @access public
     * @return int
     */
    public function pub_user_register($login, $password, $unic_login = true)
    {
        $res = manager_users::fof_user_authorization($login, $password, $unic_login);
        if ($res < 0)
        {
            $_SESSION['vars_kernel']['user_fof'] = array();
            return $res;
        }

        //Теперь запишим информацию о этом юзери в сессию
        $_SESSION['vars_kernel']['user_fof'] = $res;
        return 1;
    }


    /**
     * Проверяет, авторизирован пользователь в системе или нет
     *
     * Возвращает true или false соответственно
     * @return boolean
     * @access public
     */
    function pub_user_is_registred()
    {
        if ((isset($_SESSION['vars_kernel']['user_fof'])) && (!empty($_SESSION['vars_kernel']['user_fof'])))
        {
            return $_SESSION['vars_kernel']['user_fof']['tree']['id'];
        }
        else
        {
            return false;
        }
    }


    /**
     * Возвращает значение конкретного поля у текущего пользователя.
     *
     * Функция используются (как правило) теми модулями, которые добавляли какие-то
     * дополнительные поля к пользователям сайта, и хотят получить эти значения.
     *
     * Второй параметр может использоваться в том случае, если необходимо узнать
     * значение свойства к пользователю сайта, добавленное другим (не текущим модулем).
     * @param string $name_field Имя поля, значение которого необходимо узнать
     * @param string $name_modul
     * @access public
     * @return string
     */
    function pub_user_field_get($name_field, $name_modul = "")
    {
        if (empty($name_field))
            return '';

        $id_modul = $this->pub_module_id_get();
        if (empty($id_modul))
            return '';

        if ($name_modul == "")
            $name_modul = $id_modul;

        if (isset($_SESSION['vars_kernel']['user_fof']['line'][$name_field]))
            return trim($_SESSION['vars_kernel']['user_fof']['line'][$name_field]);

        $link = $name_modul."-".$name_field;

//        $this->debug($link);
        if (isset($_SESSION['vars_kernel']['user_fof']['line'][$link]))
            return trim($_SESSION['vars_kernel']['user_fof']['line'][$link]);
        elseif (isset($_SESSION['vars_kernel']['user_fof']['line'][preg_replace("/([0-9]+)/", "", $link)]))
        {
            $link = preg_replace("/([0-9]+)/", "", $link);
            return trim($_SESSION['vars_kernel']['user_fof']['line'][$link]);
        }
        else
            return '';

    }


    /**
     * Возвращает всю доступную информацию о текущем (авторизированным) пользователе фронт-офиса.
     *
     * Возвращаемый массив может иметь два вида – линейный и древовидный ( в зависимости от
     * передаваемого в метод параметра). Если передано true, то параметры будут сгруппированы по
     * модулям, их добавившим, в противном случае, всё будет представлено в линейном виде.
     * @param boolean $tree Тип выходного массива
     * @access public
     * @return array
     */
    function pub_user_info_get($tree = false)
    {
        if ($tree)
            return $_SESSION['vars_kernel']['user_fof']['tree'];
        else
            return $_SESSION['vars_kernel']['user_fof']['line'];
    }

    /**
     * Возвращает всю доступную информацию о пользователе с переданным идентификатором, либо массив пользователей
     *
     * Если пользователь не указан - то
     * возвращается информация по всем имеющимся записям
     * @param mixed $id_user ID конкретного пользователя - если необходимо.
     * @param boolean $tree - если <i>true</i> то возвращается в виде "дерева"
     * @param string $orderby - поле для сортировки
     * @param integer $offset смещение
     * @param integer $limit лимит
     * @param string $cond условие выборки
     * @access public
     * @return array
     */
    function pub_users_info_get($id_user = "", $tree = true, $orderby="`login`", $offset=null, $limit=null,$cond="true")
    {
        return manager_users::users_info_get($id_user, $tree, $orderby,$offset,$limit,$cond);
    }


    /**
     * Возвращает общее кол-во юзеров
     * @return integer
     */
    function pub_users_total_get()
    {
        return manager_users::get_total_users();
    }

    /**
     * Возвращает массив с дополнительными полями, которые добавили модули
     *
     * Дополнительные поля, это те поля, которые были прописаны модулями. Возвращается
     * просто массив доступных полей, без значений у конкретного пользователя
     * @param string $cond
     * @access public
     * @return array
     */
    function pub_users_fields_get($cond='true')
    {
        return manager_users::users_fields_get($cond);
    }

    /**
     * Возвращает всю доступную информацию о пользователе по логину
     *
     * В качестве параметра передается логин пользователя или e-mail, по которому будет определён пользователь.
     * @param string $login логин или e-mail пользователя, чьи данные запрашиваются.
     * @param boolean $is_login Если <i>true</i> - то передается логин, в противном случае передается e-mail
     * @access public
     * @return array
     */
    function pub_user_login_info_get($login, $is_login = true)
    {
        $login = mysql_real_escape_string($login);
        return manager_users::user_info_get($login, $is_login);
    }

    /**
     * Записывает измененную информацию о пользователе
     *
     * Массив должен быть аналогичен тому, который возвращает функция {@link pub_users_info_get}
     * Те пользователи и поля, которые не перечислены в массиве не меняются
     * @param array $data Массив пользователей с полями и их значениями
     * @param boolean $update_curent Если true - то производить обновление информации о пользователи в сессии
     * @access public
     * @return boolean
     */
    function pub_users_info_set($data, $update_curent = true)
    {
        $ret = manager_users::users_info_save($data);
        if (($ret) && (count($data) == 1) && ($update_curent))
        {

            $id_user = each($data);
            $id_user = intval($id_user['key']);
            $res = manager_users::fof_user_authorization('', '', '',$id_user);
            if ($res < 0)
            {
                $_SESSION['vars_kernel']['user_fof'] = array();
                return $res;
            }
            //Теперь запишем информацию об этом пользователе в сессию
            $_SESSION['vars_kernel']['user_fof'] = $res;
        }
        return $ret;
    }


    /**
     * Производит полное удаление пользователя сайта из базы
     *
     * Помимо этого удаляет все дополнительные поля для него.
     * @param integer $id_user Идентификатор пользователя сайта, которого нужно удалить
     * @access public
     * @return boolean
     */
    function pub_user_delete($id_user)
    {
        return manager_users::user_delete($id_user);
    }


    /**
     * Очищает информацию о текущем пользователе сайта
     *
     * @access public
     * @return void
     */
    function pub_user_unregister()
    {
        $_SESSION['vars_kernel']['user_fof'] = array();
    }


    /**
     * Подтверждает учетную запись пользователя сайта по переданному идентификатору пользователя.
     *
     * Изначально зарегистрированный пользователь находится в неподтвержденном состоянии, т.е.
     * учетная запись уже существует, но для того, чтобы ей можно было пользоваться, необходимо
     * ее подтвердить (обычно по e-mail`у)
     * Возвращает <i>true</i> если подтверждение прошло успешно и <i>false</i> в противном случае
     * @param integer $id_user Идентификатор пользователя сайта
     * @access public
     * @return boolean
     */
    function pub_user_verify($id_user)
    {
        return manager_users::user_verify($id_user);
    }

    /**
     * Включает или отключает пользователя фронт-офиса
     *
     * Функция используется для того, чтобы можно было,
     * например, временно отключить кого-то из посетителей
     * Возрващает <i>true</i> - если действие выполнено успешно
     * @param integer $id_user Идентификатор пользователя сайта
     * @param Boolean $enabled true - включить пользователя, false - выключить
     * @access public
     * @return boolean
     */
    function pub_user_change_enabled($id_user, $enabled = true)
    {
        return manager_users::user_change_enabled($id_user, $enabled);
    }

    /**
     * Возвращает список всех доступных групп пользователей сайта
     *
     * Возвращает массив вида
     * [0] => Array
     *  (
     *      [id] => 1
     *      [name] => standart
     *      [full_name] => Обычные посетители
     *  )
     * @return array
     * @access public
     */
    function pub_users_group_get()
    {
        return manager_users::users_group_get();
    }

    /**
     * Сохраняет группы, в которые входит пользователь
     *
     * В качестве параметра ID пользователя и массив, где значения
     * это ID групп, в которые включен пользователь
     * @param int $id
     * @param array $data
     * @return boolean
     */
    function pub_users_group_set($id, $data)
    {
        return manager_users::users_group_set($id, $data);
    }

    /**
     * Получает информацию о группах пользователя сайта
     *
     * @param integer $id Идентификатор пользователя
     * @return array
     */
    function pub_user_group_get($id)
    {
        return manager_users::user_group_get($id, true);
    }

    /**
     * Разбирает шаблон
     *
     * Разбирает шаблон стандартного вида и формирует массив, где в качестве ключа используется
     * метка, а значения - HTML код.
     * Например, из шаблона
     * <code>
     * <!--@begin -->
     * <table cellpadding="3" cellspacing="1" border="0" bordercolor="black" width="100%">
     * 	<tr class="table_content_shapka" bgcolor="#F05800">
     * 		<th>[#shop_admin_users_stat_caption1#]</th>
     * 		<th>[#shop_admin_users_stat_caption2#]</th>
     * 		<th>[#shop_admin_users_stat_caption3#]</th>
     * 	</tr>
     * <!-- @line -->
     * 	<tr class="table_content_str" bgcolor="#FFF3EB">
     * 		<td>%username%</td>
     * 		<td>%usernum%</td>
     * 		<td>%usersum%</td>
     * 	</tr>
     * <!-- @end -->
     * 	<tr class="table_content_shapka" bgcolor="#F05800">
     * 		<th>[#shop_admin_users_stat_caption4#]</th>
     * 		<th>%itognum%</th>
     * 		<th>%itogsum%</th>
     * 	</tr>
     * </table>
     * </code>
     * Получится массив
     * <code>
     * Array
     * (
     *     [begin] =>
     * <table cellpadding="3" cellspacing="1" border="0" bordercolor="black" width="100%">
     * 	<tr class="table_content_shapka" bgcolor="#F05800">
     * 		<th>[#shop_admin_users_stat_caption1#]</th>
     * 		<th>[#shop_admin_users_stat_caption2#]</th>
     * 		<th>[#shop_admin_users_stat_caption3#]</th>
     * 	</tr>
     *
     *     [line] =>
     * 	<tr class="table_content_str" bgcolor="#FFF3EB">
     * 		<td>%username%</td>
     * 		<td>%usernum%</td>
     * 		<td>%usersum%</td>
     * 	</tr>
     *
     *     [end] =>
     * 	<tr class="table_content_shapka" bgcolor="#F05800">
     * 		<th>[#shop_admin_users_stat_caption4#]</th>
     * 		<th>%itognum%</th>
     * 		<th>%itogsum%</th>
     * 	</tr>
     * </table>
     * )
     * </code>
     * Начало блока задаётся как "<i><!-- @Имя_блока -- ></i>". Блок будет продолжаться до
     * конца файла, либо до объявления другого блока. Шаблон может быть много уровневым. Для
     * этого внутри любого блока вы можете определить зарезервированный блок вида
     * "<i><!-- @@nextlevel -- ></i>" (обратите внимания, должно стоять две "собачки").
     * Указание этого подблока говорит о том, что ваш блок будет иметь несколько подуровней.
     * В этом случае, выходной массив для такого блока будет состоять и массива, где в качестве
     * ключа число (уровень вложенности) а в качестве значений - HTML
     * <br>
     * Пример многоуровневого шаблона:
     * Например, из шаблона
     * <code>
     * <!--@begin -->
     * Начало
     *
     * <!-- @line -->
     * Линия уровня 0 <br>
     *
     * <!-- @@nextlevel -->
     * &nbsp;&nbsp; Линия уровня 1 <br>
     *
     * <!-- @@nextlevel -->
     * &nbsp;&nbsp;&nbsp;&nbsp; Линия уровня 2 <br>
     *
     * <!-- @end -->
     * Конец
     *
     * </code>
     * Получившийся массив будет иметь вид:
     * <code>
     * Array
     * (
     *     [begin] => Начало
     *
     *     [line] => Array
     *               (
     *                   [0] => Линия уровня 0
     *                   [1] => Линия уровня 1
     *                   [2] => Линия уровня 2
     *               )
     *     [end] => Конец
     * )
     * </code>
     * @return array
     * @param string  $filename Путь к файлу шаблонов
     * @param boolean $createlevel При отсутствии уровней всё равно создавать как нулевой уровень
     * @access public
     */
    function pub_template_parse($filename, $createlevel = false)
    {
        if (!file_exists($filename))
            return array();
        $tmpl = file_get_contents($filename);

        //конвертируем шаблон из 1251, если такой тип указан в конфиге - только для templates_user, но не шаблонов админки
        if (mb_strpos($filename, "admin/")===false && defined("IS_1251_TEMPLATES") && IS_1251_TEMPLATES)
        {
            $tmpl = @iconv('cp1251', 'UTF-8//TRANSLIT', $tmpl);
            //+заменяем кодировку в хидере
            $tmpl = str_ireplace("windows-1251", "UTF-8", $tmpl);
        }

        $parts = preg_split("/<!--\\s*?\\@([^\\@]*?)\\s*?-->/i", $tmpl);

        $arr = array();
        $matches = false;
        preg_match_all("/<!--\\s*?\\@([^\\@]*?)\\s*?-->/", $tmpl, $matches);


        foreach ($matches[1] as $i => $word)
            $arr[$word] = $parts[$i+1];

        if (!isset($arr['begin']))
            $arr['begin'] = "";

        if (!isset($arr['end']))
            $arr['end'] = "";

        $result = array();
        foreach ($arr as $key => $val)
        {
            $level_templates = $val;
            $level_templates_arr = preg_split("/<!--\\s*?\\@@nextlevel\\s*?-->/i", $level_templates);

            //Если не нашли вложенных уровней
            if (count($level_templates_arr) == 1)
            {
                $level_templates_arr = '';
                if ($createlevel)
                    $level_templates_arr[] = $val;
                else
                    $level_templates_arr = $val;
            }
            $result[$key] = $level_templates_arr;
        }
        return $result;
    }


    /**
     * Выполняет MySQL запрос к базе данных
     *
     * В случае ошибки выводит его на экран с указанием метода, в котором произошла ошибка
     * @param string $sql Выполняемый SQL запрос
     * @param string $link ресурс соединения с базой данных
     * @return resource
     * @access public
     */
    function runSQL($sql = "", $link = "")
    {
        ++$this->queriesCount;
        $sql=trim($sql);

        $curent_link = $this->resurs_mysql;
        if (!empty($link))
            $curent_link = $link;

        $result=mysql_query($sql, $curent_link);
        $errorMsg=mysql_error();
        if (!$errorMsg=="")
        {
            $err = debug_backtrace();
            $err = $err[0];
            if (isset($_SERVER['HTTP_HOST']))
                $httpHost = $_SERVER['HTTP_HOST'];
            else
                $httpHost = "unknown";
            $error = "Ошибка (errore):<br>
                    <b>Файл (file):</b> ".$err['file'].
                (isset($_SERVER['REQUEST_URI'])?"<br><b>GET запрос (GET request):</b>".$_SERVER['REQUEST_URI']:"").
                "<br><b>Строка (String):</b> ".$err['line']." (".$err['class'].$err['type'].$err['function'].")".
                "<br><b>SQL запрос (SQL query):</b><br><pre>'".$sql."'</pre><br>".$errorMsg;
            if ( preg_match("/\\.ap$/", $httpHost) || (defined("PRINT_MYSQL_ERRORS") && PRINT_MYSQL_ERRORS))
                echo $error."\n";
            else
            {
                if (defined('EMAIL_FOR_ERRORE'))
                {
                    if ($this->pub_is_valid_email(EMAIL_FOR_ERRORE))
                        $this->pub_mail(array(EMAIL_FOR_ERRORE), array(""), EMAIL_FOR_ERRORE, "", $httpHost, $error);
                }
            }

        }

        return $result;
    }

    /**
     * Простой метод для получения массива записей из таблицы БД
     *
     * @param string $table Имя таблицы БД без префикса
     * @param string $cond условие выборки, возможно с ORDER BY или GROUP BY, для получения всех записей - "true"
     * @param string $fields поля выборки через запятую, либо все - *
     * @param integer $offset смещение для LIMIT
     * @param integer $limit лимит для LIMIT
     * @return array
     * @access public
     */
    public function db_get_list_simple($table, $cond, $fields="*", $offset=null, $limit=null)
    {
        $query = "SELECT ".$fields." FROM `".$this->pub_prefix_get().$table."` WHERE ".$cond;
        return $this->db_get_list($query,$offset,$limit);
    }

    /**
     * Метод для получения массива записей из БД
     *
     * @param string $query sql-запрос
     * @param integer $offset смещение для LIMIT
     * @param integer $limit лимит для LIMIT
     * @return array
     * @access public
     */
    public function db_get_list($query, $offset=null, $limit=null)
    {
        if (!is_null($offset) && !is_null($limit))
            $query .= " LIMIT ".$offset.", ".$limit;
        $res = $this->runSQL($query);
        $ret = array();
        while ($row = mysql_fetch_assoc($res))
            $ret[] = $row;
        mysql_free_result($res);
        return $ret;
    }

    /**
     * Простой метод для добавления записи в БД
     *
     * @param string $table Имя таблицы БД без префикса
     * @param array $rec key-value массив полей со значениями
     * @param string $type тип INSERT или REPLACE
     * @return integer
     * @access public
     */
    public function db_add_record($table, $rec, $type="INSERT")
    {
        if (strtoupper($type)=="REPLACE")
            $query = "REPLACE";
        else
            $query = "INSERT";
        $query.=" INTO `".$this->pub_prefix_get().$table."` ";
        $fnames  = array();
        $fvalues = array();
        foreach ($rec as $n=>$v)
        {
            $fnames[]  = '`'.$n.'`';
            if (is_null($v))
                $fvalues[] = "NULL";
            else
                $fvalues[] = "'".$v."'";
        }
        $query .= "(".implode(', ',$fnames).") VALUES ";
        $query .= "(".implode(', ', $fvalues).")";

        $res = $this->runSQL($query);
        if (!$res)
            return 0;
        //PHP converts AUTO_INCREMENT values to longs. If you're using an AUTO_INCREMENT column of type BIGINT,
        //use the MySQL function LAST_INSERT_ID() to get the accurate AUTO_INCREMENT value.
        $lres=mysql_query ('SELECT LAST_INSERT_ID()',$this->resurs_mysql);
        $arr=mysql_fetch_row($lres);
        $last_insert_ID=$arr[0];
        mysql_free_result($lres);
        return $last_insert_ID;
    }

    /**
     * Простой метод для сохранения записи(записей) в БД
     *
     * @param string $table Имя таблицы БД без префикса
     * @param array $rec key-value массив полей со значениями
     * @param string $condition условие
     * @return integer
     * @access public
     */
    public function db_update_record($table, $rec, $condition)
    {
        $query="UPDATE `".$this->pub_prefix_get().$table."` SET ";
        $setfields  = array();
        foreach ($rec as $n=>$v)
        {
            if (is_null($v))
                $setfields[]  = "`".$n."`=NULL";
            else
                $setfields[]  = "`".$n."`='".$v."'";
        }
        $query .= implode(', ',$setfields)." WHERE ".$condition;

        $res= $this->runSQL($query);
        if (!$res)
            return 0;
        else
            return mysql_affected_rows($this->resurs_mysql);
    }
    /**
     * Простой метод для получения одной записи из БД
     *
     * @param string $table Имя таблицы БД без префикса
     * @param string $cond условие выборки, возможно с ORDER BY или GROUP BY, для получения всех записей - "true"
     * @param string $fields поля выборки через запятую, либо все - *
     * @return mixed
     * @access public
     */
    public function db_get_record_simple($table, $cond, $fields="*")
    {

        $query = "SELECT ".$fields." FROM `".$this->pub_prefix_get().$table."` WHERE ".$cond." LIMIT 1";

        $res = $this->runSQL($query);
        $ret = false;
        if ($row = mysql_fetch_assoc($res))
            $ret = $row;
        mysql_free_result($res);
        return $ret;
    }

    /**
     * Преобразует массив для использования в динамических формах
     *
     * Передаваемый в функцию массив преобразуются к виду:
     *  [["key","val"],["key","val"],["key","val"], ...]
     * @param array $arr Преобразуемый массив
     * @return string
     * @access public
     */
    function pub_array_convert_form($arr)
    {
        if (empty($arr))
            return '[]';
        $out = array();
        foreach ($arr as $key => $val)
            $out[] .= '["'.$key.'","'.$val.'"]';
        return "[".join(",",$out)."]";
    }

    /**
     * Преобразует массив и все вложенные массивы для использования в компонентах Ext`а.
     *
     * Метод идентичен методу pub_array_convert_form и отличается от него только тем,
     * что обрабатывает вложенные массивы.
     * @param array $array Обрабатываемый массив
     * @return string
     * @access public
     */
    function pub_array_convert_form_rec($array)
    {
        $string = array();
        $tmp = array();
        foreach ($array as $value)
        {
            if (is_array($value))
                $string[] = $this->pub_array_convert_form_rec($value);
            else
                $tmp[] =  '"'.$value.'"';
        }
        if (!empty($tmp))
            $string[] = '['.implode(',', $tmp).']';
        $string = implode(',', $string);
        return $string;

    }

    /**
     * Замена переменных вида %name% на значения переданного массива
     *
     * Метод обрабатывает переданную строку на предмет наличия в ней
     * переменных, заключённых в одинарные проценты (%myvar%), заменяет
     * эти переменные на значения, переданные во втором параметре. Переменная
     * %myvar% будет заменена на значение, которое расположено в
     * массиве по ключу myvar.
     * <code>
     *  $html = "Ваше имя: %name% <br>Ваш возраст: %age%";
     *  $data = array(name => "Евгений", age => "27")
     *
     *  $html = pub_array_key_2_value($html, $data);
     * </code>
     *
     * В результате работы функции вы получите:
     * <code>
     *   Ваше имя: Евгений
     *   Ваш возраст 27
     * </code>
     * @param string $html Обрабатываемый контент
     * @param array $array Массив с переменными для замены
     * @return string
     */
    function pub_array_key_2_value($html, $array)
    {
        foreach ($array AS $key => $value)
        {
            $html = str_replace("%".$key."%", $value, $html);
        }
        return $html;
    }


    /**
     * Добавляет текст поверх изображения
     *
     * Метод предназначен для возможности включать в состав изображения произвольный текст.
     * Помимо изображения, на которое надо нанести текст, необходимо передать массив с
     * параметрами, отвечающими за добавления текста к изображению. Данный массив
     * выглядит следующим образом:
     * <code>
     *    $text_arr[font] = '/content/files/fonts/tahoma.tff';//путь имя файла шрифта, используемого для написания текста. Начинать с "/"
     *    $text_arr[text] = 'Супер предложение'; // непосредственно добавляемая фраза
     *    $text_arr[x] = 10; //положение текста по ширине, относительно верхнего левого угла изображения
     *    $text_arr[y] = 10; //положение текста по высоте, относительно верхнего левого угла изображения
     *    $text_arr[color] = '#FF0000; //цвет текста в формате #RRGGBB
     * <code>
     *
     * Если последний параметр не задан, то изменённое изображение будет выведено только на экран, и не будет сохранено
     * @param string $image Путь к файлу с картинкой
     * @param array $text_arr Массив с параметрами добавляемого текста
     * @param mixed $output Имя и путь файла для записи изменённого изображения. Если не задано, то картинка будет выведена на экран
     * @access public
     * @return void
     */
    function pub_image_text_write($image, $text_arr, $output=false)
    {//@todo not used?
        if (!$output)
            $output=$image;
        $image_resource = imagecreatefromgif($image);
        if ($image_resource)
        {
            foreach ($text_arr AS $label)
            {
                $font = $_SERVER['DOCUMENT_ROOT'].$label['font'];
                $text = $label['text'];
                $size = $label['size'];
                $position_x = $label['x'];
                $position_y = $label['y'];
                preg_match("/\\#(.{2})(.{2})(.{2})/", $label['color'], $color);
                unset($color[0]);
                $color_red = hexdec($color['1']);
                $color_green = hexdec($color['2']);
                $color_blue = hexdec($color['3']);
                $color = imagecolorallocate($image_resource, $color_red, $color_green, $color_blue);
                imagettftext($image_resource, $size, 0, $position_x, $position_y, $color, $font, $text);
            }
            if ($output != "null")
            {
                $this->pub_ftp_dir_chmod_change($output);
                imagegif($image_resource, $output);
                $this->pub_ftp_dir_chmod_change($output);
            }
            else
            {
                header("Content-Type: image/gif; name=\"fiz.gif\"");
                //header("Content-Disposition: inline; filename=\"fiz.gif\"");
                imagegif($image_resource);
            }
        }

    }



    /**
     * Меняет размеры загруженной картинки до нужных размеров и сохраняет ее в каталог
     *
     * Метод обрабатывает изображения в трёх форматах: jpg, gif, png. Из исходного
     * изображения могут быть сформированы большое и малое изображение, кроме того,
     * к большому изображению может быть добавлена защита в виде «водяного знака».
     *
     * В качестве параметра для формирования большого изображения передаётся массив
     * следующего вида:
     * <code>
     *   $big['width'] = 400;
     *   $big['height'] = 300;
     * </code>
     *
     * В массиве указываются значения длины и ширины, к которым должно быть приведено
     * большое изображение. Аналогичным образом указывается массив параметров для
     * создания маленького изображения:
     * <code>
     *   $big['width'] = 100;
     *   $big['height'] = 75;
     * </code>
     *
     * Следует учитывать, что ширина и высота обработанных изображений может отличаться,
     * если будут нарушаться пропорции исходного изображения. При выполнении масштабирования
     * предпочтение отдаётся сохранению пропорций, а затем ширине изображения.
     *
     * Для добавления водяного знака необходимо определить массив его настроек и передать его
     * в метод. Массив выглядит следующим образом:
     * <code>
     *   $watermark_image['path'] = 'content/files/fatermark.gif';
     *   $watermark_image['place'] = 0;
     *   $watermark_image['transparency'] = 30;
     * </code>
     *
     * Ключ path указывает путь и имя файла водяного изображения. Ключ place определяет
     * местоположение водяной марки относительно большого изображения, и может принимать
     * следующие значения:
     * 0 – по центру;
     * 1 – левый верхний уровень;
     * 2 – правый верхний угол;
     * 3 – правый нижний угол;
     * 4 – левый нижний угол;
     * Последний ключ (transparency) определяет уровень прозрачности, в процентах,
     * который должен иметь водяной знак. Принимает значения от 1 до 100 и если не задан,
     * то равен 50.
     *
     * После выполнения метода будет создано два (или одно) изображения, которые будут
     * помещены в папку $path_full_image, при этом большое изображение непосредственно
     * помещается в эту папку, а маленькое помещается во вложенную папку с именем 'tn'
     * ($path_full_image.'/tn'). Имена файлов большого и маленького изображения будут
     * одинаковы.
     *
     * Пример:
     * <code>
     *       //Путь к обрабатываемому файлу
     *       $tmp_name = 'temp/temp.jpg'
     *
     *       //Параметры большого изображения
     *       $big = array(
     *           'width' => 400,
     *           'height' => 300
     *       );
     *
     *       //Параметры малого изображения
     *       $thumb = array(
     *           'width' => 100,
     *           'height' => 75
     *       );
     *
     *       //Параметры водяной марки
     *       $watermark_image = array(
     *           'path' => 'content/files/fatermark.gif',
     *           'place' => 3,
     *           'transparency' => 25
     *       );
     *
     *       //Задаём путь для сохранения обработанных изображений.
     *       //такой путь должен существовать
     *       $path_to_save = 'content/images/'.$kernel->pub_module_id_get();
     *       $filename = $kernel->pub_image_save($tmp_name, 'img', $path_to_save, $big, $thumb, $watermark_image);
     *
     * </code>
     *
     * Если взять в качестве идентификатора модуля значение 'news', то будут
     * созданы следующие файлы:
     * <code>
     *   content/images/news/img_345222534.jpg   //большое изображение
     *   content/images/news/tn/img_345222534.jpg //малое изображение
     * </code>
     * А переменная $filename будет содержать:
     * <code>
     *   img_345222534.jpg
     * </code>
     * @param string $ufile Путь и имя файла обрабатываемого изображения
     * @param int $id Начальная часть имени файла уже обработанного изображения, к которой будет добавлена уникальная составляющая
     * @param string $path_full_image Путь, куда будет сохранено изменённое изображение
     * @param mixed $big Массив с параметрами для формирования БОЛЬШОГО изображения. Если 0, то данное изображение не формируется
     * @param mixed $thumb Массив с параметрами для формирования МАЛОГО изображения. Если 0, то данное изображение не формируется
     * @param mixed $watermark_image_b Массив с параметрами для формирования водяного знака на большом изображении.
     * @param mixed $source
     * @param mixed $watermark_image_s Массив с параметрами для формирования водяного знака на исходном изображении.
     * @access public
     * @return string
     */
    function pub_image_save($ufile, $id, $path_full_image, $big=0, $thumb=0, $watermark_image_b=0, $source = 0, $watermark_image_s=0)
    {
        $newname="";
        if (isset($ufile) && ($ufile!=""))
        {
            //Перед тем, как начинать преобразование, возможно надо открыть папку для записи
            //так как она могла быть переписана по FTP и тогда скрипт не сможет сюда писать

            $close_full_path = false;
            $close_tn = false;
            $close_source = false;

            if (!is_writable($path_full_image))
            {
                $this->pub_ftp_dir_chmod_change($path_full_image);
                $close_full_path = true;
            }
            if (!is_writable($path_full_image."/tn"))
            {
                $this->pub_ftp_dir_chmod_change($path_full_image."/tn");
                $close_tn = true;
            }
            if (!is_writable($path_full_image."/source"))
            {
                $this->pub_ftp_dir_chmod_change($path_full_image."/source");
                $close_source = true;
            }


            $this->priv_set_memory_for_image($ufile);

            $type = ".jpg";
            $a = getimagesize($ufile);
            if ($a[2] == "1")
                $type = ".gif";
            elseif ($a[2] == "3")
                $type = ".png";

            //$image_width = $a[0];
            //$image_height = $a[1];

            //Определим имя файла
            $newname = $id."_".date("U").$type;

            $file_big   = $path_full_image."/".$newname;
            $file_small = $path_full_image."/tn/".$newname;
            $file_surce = $path_full_image."/source/".$newname;

            $im=null;
            if ($type == ".jpg")
                $im = @ImageCreateFromJPEG($ufile);
            elseif($type == ".gif")
                $im = @ImageCreateFromGIF($ufile);
            elseif ($type == ".png")
                $im = @ImageCreateFromPNG($ufile);

            if (is_resource($im))
            {
                //Создаём маленькое изображение
                if ($thumb != 0)
                {
                    $im_small = $this->pub_image_resize_to($im, $thumb);
                    if ($im_small)
                    {
                        if ($type == ".jpg")
                            ImageJPEG($im_small, $file_small, 100);
                        elseif ($type == ".gif")
                            ImageGIF($im_small , $file_small);
                        elseif ($type == ".png")
                            ImagePNG($im_small , $file_small);

                        ImageDestroy($im_small);
                        chmod ($file_small, $this->priv_chmod_limit_get());
                    }
                }
                //Если $big не равен нулю, то сохраняем и большую картинку
                if ($big != 0)
                {
                    // Если он равен 1, не меняем размеры изображения
                    if ($big == 1)
                    {
                        unset($big);
                        $big['width'] = $a[0];//$sx;
                        $big['height'] = $a[1];//$sy;
                    }

                    //Создадим большое изображение
                    $im_big = $this->pub_image_resize_to($im, $big, $watermark_image_b);
                    if ($im_big)
                    {
                        if ($type == ".jpg")
                            ImageJPEG($im_big, $file_big, 75);
                        elseif ($type == ".gif")
                            ImageGIF($im_big , $file_big);
                        elseif ($type == ".png")
                            ImagePNG($im_big , $file_big);

                        ImageDestroy($im_big);
                        chmod ($file_big, $this->priv_chmod_limit_get());
                    }
                }

                //И самое последние, если надо то исправим исходное изображение
                //Если $big не равен нулю, то сохраняем и большую картинку
                if ($source != 0)
                {
                    // Если он равен 1, не меняем размеры изображения
                    /*
                    if ($source == 1)
                    {
                        unset($source);
                        $source['width']  = $sx;
                        $source['height'] = $sy;
                    }*/

                    //Создадим большое изображение
                    $im_source = $this->pub_image_resize_to($im, $source, $watermark_image_s);
                    if ($im_source)
                    {
                        if ($type == ".jpg")
                            ImageJPEG($im_source, $file_surce, 75);
                        elseif ($type == ".gif")
                            ImageGIF($im_source , $file_surce);
                        elseif ($type == ".png")
                            ImagePNG($im_source , $file_surce);
                        ImageDestroy($im_source);
                        chmod ($file_surce, $this->priv_chmod_limit_get());
                    }
                }
                else
                {//$source == 0, просто копируем не изменяя
                    $this->pub_file_copy($ufile, $path_full_image."/source/".$newname,$close_source, false);
                }

                ImageDestroy($im);
            }
            if ($close_full_path)
                $this->pub_ftp_dir_chmod_change($path_full_image);
            if ($close_tn)
                $this->pub_ftp_dir_chmod_change($path_full_image."/tn");
            if ($close_source)
                $this->pub_ftp_dir_chmod_change($path_full_image."/source");
        }
        return $newname;
    }


    /**
     * @param array $file - массив для одного файла из $_FILE
     * @param string $save_path путь, куда сохраняем
     * @param array $thumb_settings массив настроек tn-изображения
     * @param array $big_settings массив настроек изображения
     * @param array $source_settings массив настроек src-изображения
     * @return bool|string
     */
    public function save_uploaded_image($file, $save_path, $thumb_settings=array(), $big_settings=array(), $source_settings = array())
    {
        if (!$file|| !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
            return false;
        $ufile=$file['tmp_name'];
        $paths2close = array();
        //Перед тем, как начинать преобразование, возможно надо открыть папку для записи
        //так как она могла быть переписана по FTP и тогда скрипт не сможет сюда писать
        if ($big_settings && !is_writable($save_path))
        {
            $this->pub_ftp_dir_chmod_change($save_path);
            $paths2close[]=$save_path;
        }
        if ($thumb_settings && !is_writable($save_path."/tn"))
        {
            $this->pub_ftp_dir_chmod_change($save_path."/tn");
            $paths2close[]=$save_path."/tn";
        }
        if ($source_settings && !is_writable($save_path."/source"))
        {
            $this->pub_ftp_dir_chmod_change($save_path."/source");
            $paths2close[]=$save_path."/source";
        }
        $this->priv_set_memory_for_image($ufile);
        list($width,$height,$itype) = getimagesize($ufile);
        if (!$width)
            return false;

        if ($itype == IMAGETYPE_GIF)
        {
            $ext = ".gif";
            $im = ImageCreateFromGIF($ufile);
        }
        elseif ($itype == IMAGETYPE_PNG)
        {
            $ext = ".png";
            $im = ImageCreateFromPNG($ufile);
        }
        else
        {
            $ext = ".jpg";
            $im = ImageCreateFromJPEG($ufile);
        }
        //Определим имя файла
        $origFilename=pathinfo($file['name'],PATHINFO_FILENAME);
        $origFilename=$this->pub_translit_string($origFilename);//в транслит
        $origFilename = str_replace('.','_',$origFilename);
        $origFilename = preg_replace('~([^\da-z_-]+)~i','',$origFilename); //оставим только символы из набора
        $maxLength=250;
        if (strlen($origFilename)+strlen($ext)>$maxLength)
            $origFilename=substr($origFilename,0,$maxLength-strlen($ext));
        if (!$origFilename)
            $origFilename = date("Y_m_d");
        $newname=$origFilename.$ext;
        $n=1;
        while (file_exists($save_path."/".$newname) || file_exists($save_path."/tn/".$newname) || file_exists($save_path."/source/".$newname))
        {
            $n++;
            $newname=$origFilename.$n.$ext;
            $len = strlen($newname);
            if ($len>$maxLength)
                $newname=substr($origFilename,0,$maxLength-$len).$n.$ext;
        }
        $file_big   = $save_path."/".$newname;
        $file_small = $save_path."/tn/".$newname;
        $file_surce = $save_path."/source/".$newname;
        if (!is_resource($im))
            return false;

        //Создаём маленькое изображение, если надо
        if ($thumb_settings)
        {
            $im_small = $this->pub_image_resize_to($im, $thumb_settings);
            if ($im_small)
            {
                if ($itype == IMAGETYPE_PNG)
                    ImagePNG($im_small , $file_small);
                elseif ($itype == IMAGETYPE_GIF)
                    ImageGIF($im_small , $file_small);
                else
                    ImageJPEG($im_small, $file_small, 95);

                ImageDestroy($im_small);
                chmod ($file_small, $this->priv_chmod_limit_get());
            }
        }
        //Если $big_settings, то сохраняем-обрабатываем и большую картинку
        if ($big_settings)
        {
            if (isset($big_settings['water_add']) && $big_settings['water_add']==0)
                $big_wm=array();
            else
                $big_wm=$big_settings;
            //Создадим большое изображение
            $im_big = $this->pub_image_resize_to($im, $big_settings, $big_wm);
            if ($im_big)
            {
                if ($itype == IMAGETYPE_GIF)
                    ImageGIF($im_big , $file_big);
                elseif ($itype ==  IMAGETYPE_PNG)
                    ImagePNG($im_big , $file_big);
                else
                    ImageJPEG($im_big, $file_big, 85);
                ImageDestroy($im_big);
                chmod ($file_big, $this->priv_chmod_limit_get());
            }
        }

        //И самое последние, если надо то исправим исходное изображение
        if ($source_settings)
        {
            if (isset($source_settings['water_add']) && $source_settings['water_add']==0)
                $src_wm=array();
            else
                $src_wm=$big_settings;
            //Обрабатываем исходное изображение
            $im_source = $this->pub_image_resize_to($im, $source_settings, $src_wm);
            if ($im_source)
            {
                if ($itype == IMAGETYPE_GIF)
                    ImageGIF($im_source , $file_surce);
                elseif ($itype == IMAGETYPE_PNG)
                    ImagePNG($im_source , $file_surce);
                else
                    ImageJPEG($im_source, $file_surce, 85);
                ImageDestroy($im_source);
                chmod ($file_surce, $this->priv_chmod_limit_get());
            }
        }
        elseif (!is_null($source_settings)) //просто копируем не изменяя
            $this->pub_file_copy($ufile, $save_path."/source/".$newname, false, false);

        imagedestroy($im);
        foreach($paths2close as $path2close)
        {
            $this->pub_ftp_dir_chmod_change($path2close);
        }
        return $newname;
    }

    /** Проверяет email-адрес на валидность
     * @param  $email
     * @return boolean
     */
    public function pub_is_valid_email($email)
    {
        return preg_match('/^[a-z0-9][a-z0-9_\\.-]*@[a-z0-9\\.-]+\\.[a-z]{2,6}$/i',$email);
    }

    /**
     * Отправка электронных писем
     *
     * Метод позволяет осуществить отправку электронных писем с сервера, на котором размещён сайт.
     * Письма могут быть отправлены сразу нескольким адресатам. Кроме, того к письму могут быть
     * приложены изображения, ссылки на которые есть в самом письме.
     *
     * Пример:
     * <code>
     *   global $kernel;
     *
     *   //Имя и адрес получателя
     *   $toname[0] = "Сергей Петров";
     *   $toaddr[0] = "sergey.p@mymail.ru";
     *
     *   //Имя и адрес отправителя
     *   $fromname = "Робот с сервера ".$_SERVER['HTTP_HOST'];
     *   $fromaddr = "noreply@".$_SERVER['HTTP_HOST'];
     *
     *   //Заголовок сообщения
     *   $subject = "Автоматическое письмо с сайта ".$_SERVER['HTTP_HOST'];
     *
     *   //Текст сообщения
     *   $message = "Hello <b>word</b>!";
     *
     *   //Отправка сообщения
     *   $kernel->pub_mail($toaddr, $toname, $fromaddr, $fromname, $subject, $message);
     * </code>
     * @param array $toaddr Массив адресов получателей письма
     * @param array $toname Имена получателей письма. Ключи должны соответствовать ключам в массиве $toaddr
     * @param string $fromaddr Адрес отправителя письма
     * @param string $fromname Имя отправителя письма
     * @param string $subject Тема письма
     * @param string $message Тело письма. Может содержать HTML
     * @param boolean $attach Если true, то к телу письма будут прикреплены изображения, ссылки на которые встретились в теле письма.
     * @param string $hostname Адрес хоста, где находятся изображения, которые могут
     * быть прикреплены. Если не задан или равен "", то используется имя хоста, на котором работает сайт.
     * @param mixed $att_files Файлы, которые должны быть прикреплены к письму. Массив с полными путями
     * @param mixed $replyto Email для Reply-To
     * @access public
     * @return int Количество отправленных писем
     */
    function pub_mail($toaddr, $toname, $fromaddr, $fromname, $subject, $message, $attach=false, $hostname="", $att_files=false, $replyto=false)
    {
        require_once dirname(__FILE__)."/class.phpmailer.php";
        $fromaddr = preg_replace('~@www\.~i','@',$fromaddr);//уберём www. из email отправителя
        $sended = 0;
        if (!$this->pub_is_valid_email($fromaddr))
            return 0;
        foreach ($toaddr AS $key => $email)
        {
            $email=trim($email);
            if (!$this->pub_is_valid_email($email))
                continue;
            try
            {
                $mail = new PHPMailer(true);
                $mail->CharSet = "utf-8";
                if ($replyto)
                    $mail->AddReplyTo($replyto, $fromname);
                $mail->AddAddress($email, $toname[$key]);
                $mail->SetFrom($fromaddr, $fromname);
                $mail->Subject = $subject;

                if ($attach)
                {
                    //ищем <img src=... в письме и обрабатываем, добавляя эти картинки как аттачи
                    $img_files = false;
                    if (preg_match_all('/<img.*?src=([\"\']+.*?[\"\']+).*?\/*?>/i', $message, $img_files))
                    {
                        $img_files = array_unique($img_files[1]);
                        foreach ($img_files as $filepath)
                        {
                            $filepath = trim($filepath,'"\'');
                            if (preg_match("/(^\\.\\.\\/)|(^\\/)/", $filepath))
                            {
                                $message = str_replace($filepath, $hostname.$filepath, $message);
                                $filepath = $hostname.$filepath;
                            }
                            $file_orig_name = md5($filepath);
                            switch(strtolower(pathinfo($filepath,PATHINFO_EXTENSION)))
                            {
                                case 'jpg':
                                case 'jpeg':
                                    $etype='image/jpeg';
                                    break;
                                case 'png':
                                    $etype='image/png';
                                    break;
                                case 'gif':
                                    $etype='image/gif';
                                    break;
                                default:
                                    $etype='application/octet-stream';
                                    break;
                            }
                            $mail->AddEmbeddedImage($filepath, $file_orig_name, $file_orig_name,'base64',$etype);
                            $message = str_replace($filepath, 'cid:'.$file_orig_name, $message);
                        }
                    }

                    //заменяем ссылки на css-файлы на сам css прямо в html письма
                    $css_body = "";
                    $css_files = false;
                    if (preg_match_all('/<link href=([\"\']*?.*?[\"\']*?) rel="stylesheet" type="text\/css">/i', $message, $css_files))
                    {
                        foreach ($css_files[1] AS $arr)
                        {
                            $arr = preg_replace('/[\"\']/i', '', $arr);
                            if (preg_match("/(^\\.\\.\\/)|(^\\/)/", $arr))
                            {
                                $message = str_replace($arr, $hostname.$arr, $message);
                                $arr = $hostname.$arr;
                            }
                            $fp = fopen($arr, "r");
                            while (!feof($fp))
                            {
                                $css_body .= fread($fp, 2048);
                            }
                            fclose($fp);
                        }
                        $css_body = preg_replace("/^\\./im", " .", $css_body);
                        $message = preg_replace('/<link href=([\"\']*?.*?[\"\']*?) rel="stylesheet" type="text\/css">/i', '<style type="text/css">'.$css_body.'</style>', $message);
                        unset($css_body);
                    }

                }
                $mail->emptyAltBody = true;
                $message = str_replace("\r\n", "\n", $message);
                $mail->MsgHTML($message);
                if (is_array($att_files))
                {
                    foreach ($att_files as $att_file)
                    {
                        if (file_exists($att_file))
                            $mail->AddAttachment($att_file);
                    }
                }

                if ($mail->Send())
                    $sended++;
            }
            catch (Exception $e)
            {
                if (defined("SHOW_INT_ERRORE_MESSAGE") && SHOW_INT_ERRORE_MESSAGE)
                    print "Error: ".$e->getMessage()."\n\n";
            }
        }
        return $sended;
    }

    /**
     * Возвращает массив со всеми дополнительными настройками модуля
     *
     * В данный массив модуль может записать любые значения, которые ему необходимо сохранить.
     * Пользователь никак не может влиять на эти значения, они доступны только с помощью
     * функций ядра {@link pub_module_serial_get($idmodule)}
     * и {@link pub_module_serial_set()}
     * @param string $id
     * @access public
     * @return array
     */
    function pub_module_serial_get($id = "")
    {
        $idmodule = $id;
        if (empty($id))
            $idmodule = $this->pub_module_id_get();
        $data = $this->db_get_record_simple("_modules","id='".$idmodule."'","module_setings");
        $return  = array();
        if ($data['module_setings'] != "")
            $return = unserialize($data['module_setings']);

        return $return;
    }

    /**
     * Записывает массив со всеми дополнительными настройки модуля
     *
     * В данный массив модуль может записать любые знания, которые ему захочется
     * Пользователь никак не может влиять на эти значения, они доступны только по
     * средствам функций ядра {@link pub_module_serial_get($idmodule)}
     * и {@link pub_module_serial_set()}
     * @param array $array Записываемый массив
     * @param string $id Идентификатор модуля, чьи параметры сохраняются
     * @access public
     * @return void
     */
    function pub_module_serial_set($array, $id = "")
    {
        $idmodule = $id;
        if (empty($id))
            $idmodule = $this->pub_module_id_get();

        $array = serialize($array);

        $sql = "UPDATE `".$this->pub_prefix_get()."_modules`
                SET module_setings='$array'
                WHERE id='$idmodule'";

        $this->runSQL($sql);
    }

    /**
     * Выводит файл с помощью
     *
     * Используется в админке
     * @param string $name_file
     * @return string
     * @access private
     */
    function priv_help_html_get($name_file)
    {
        $html = file_get_contents('admin/help/'.$this->priv_langauge_current_get().'/'.$name_file);
        $html = '
			<html>
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=[#set_charset#]">
			<link rel="StyleSheet" href="css/dtree.css" type="text/css" />
			</head>

			<body leftmargin="0" rightmargin="0" topmargin="0" marginheight="0" style="border-style: none">
			'.$html;

        $html .= '</body></html>';

        return $html;
    }


    /**
     * Возвращает title страницы
     *
     * Данная функция вызывается как метод ядра, если действие по замене title
     * указано в административном интерфейсе у конкретной метки
     * @param string $name_link
     * @return string
     * @access private
     */
    function priv_page_title_get($name_link)
    {
        $arr = $this->pub_page_property_get($this->pub_page_current_get(), "title_other");
        if ($arr['value'])
        {
            $arr = $this->pub_page_property_get($this->pub_page_current_get(), "name_title");
            $str = $arr['value'];
        }
        else
        {
            $arr = $this->pub_page_property_get($this->pub_page_current_get(), "caption");
            $str = $arr['value'];
        }

        //Теперь добавим информацию по тайтлу от других модулей

        if ($this->modul_title!="")
            return $this->modul_title;

        return $str.$this->modul_title;
    }

    /**
     * Добавляет текст к строке, возвращаемой методом ядра "вернуть тайтл".
     *
     * С помощью этой функции добавляется дополнительная информация к тайтлу
     * страницы. Между уже существующем тайтлом и вновь добавляемым добавляется символ "-".
     * @param string $text Текст, который добавляется к тайтлу
     * @access public
     * @return void
     */
    function pub_page_title_add($text)
    {
        $this->modul_title = trim($text);
    }

    /**
     * Загружает в html редактор контент из указанного файла
     *
     * @param string $name_link
     * @return string
     * @access private
     */
    function priv_html_editor_start($name_link)
    {
        global $kernel;
        $name_link = $kernel->pub_translit_string($name_link);
        $name_file = $this->priv_path_pages_content_get().'/'.$this->pub_page_current_get().'_'.$name_link.'.html';
        $html = '';
        if (file_exists($name_file))
            $html = file_get_contents($name_file);
        return $html;
    }


    /**
     * Выводит ошибку
     *
     * Функция ничего не возвращает, а сразу выводит ошибку на экран
     * @param string $message
     * @access private
     * @return void
     */
    function priv_error_show($message)
    {
        $html = $message;
        $this->priv_output($html);
    }


    /**
     * Создание новой директории в папке /content
     *
     * Чаще всего метод используются модулями, для того что бы создать необходимые
     * для себя папки, в которых могут храниться какие-то файлы. Как правило, метод
     * вызывается в инсталляторах модулей. В качестве параметра разделителя директорий
     * может быть передан как символ кассой черты, так и символ вертикальной черты и
     * обратной кассой черты («/», «|», «\»)
     * <code>
     *     function install_children($id_module)
     *     {
     *       global $kernel;
     *
     *       $kernel->pub_dir_create_in_files($id_module.'|filedoc');
     *       $kernel->pub_dir_create_in_files($id_module.'|filepdf');
     *      }
     * </code>
     * В результате такого вызова будут созданы следующие папки (допустим, что $id_module = 'news'):
     * <code>
     *     /content/files/news
     *     /content/files/news/filedoc
     *     /content/files/news/filepdf
     * </code>
     * @param mixed $dir Строка пути, который необходимо создать
     * @param boolean $direct Если установлен в true то указанный в путь ($dir) рассматривается от корня сайта. По умолчанию, указанный путь создаётся в папке content/files
     * @return boolean
     */
    function pub_dir_create_in_files($dir = false, $direct = false)
    {
        if (!$dir)
            return false;
        $arr_dir = preg_split('(/|\\\\|,|;)', $dir);
        if (count($arr_dir) <= 0)
            return false;
        //Если вызов не по прямому пути, значит обрабатываем директории в папке контент
        $str_dir = '';
        if (!$direct)
            $str_dir = "content/files";
        foreach ($arr_dir as $name_dir)
        {
            if (!empty($name_dir))
            {
                $str_dir .= "/".$name_dir;
                $this->pub_file_dir_create($str_dir);
            }
        }
        return true;
    }

    function pub_dir_create_in_images($dir = false)
    {
        if (!$dir)
            return false;
        $arr_dir = preg_split('(/|\\\\|,|;)', $dir);
        if (count($arr_dir) <= 0)
            return false;
        //Если вызов не по прямому пути, значит обрабатываем директории в папке контент
        $str_dir = "content/images";
        foreach ($arr_dir as $name_dir)
        {
            $str_dir .= "/".$name_dir;
            $this->pub_file_dir_create($str_dir);
        }
        return true;
    }

    /**
     * Возвращает класс строки таблицы, для использования в HTML
     *
     * По переданному номеру строки определяется чётная это строка или нет
     * и в соответствии с этим возвращается либо пустая строка, либо строка с именем класса
     * @param integer $num Номер строки выводимой строки.
     * @param string $str_dop Возможные дополнения, которые нужно дописать помимо класса.
     * @return string
     * @access public
     */
    function pub_table_tr_class($num, $str_dop = '')
    {
        $str = $str_dop;
        if ((ceil($num/2) - floor($num/2)) == 0)
            $str .= ' class="admin_table_string"';
        return $str;
    }

    /**
     * Преобразует дату формата MySql в представление даты принятое в РФ
     *
     * В качестве основного параметра передаётся строка с датой в формате
     * времени MySql (ГГГГ-ММ-ДД, после может идти время, которое данной
     * функцией не учитывается). Возвращается дата в виде
     * "<день недели>, %d <месяц> %Y г.
     * @param string $data Дата в формате MySql
     * @param boolean $w Признак необходимости вывода дня недели
     * @return string
     */
    function pub_data_to_string($data, $w = false)
    {
        $str = '';
        $mtime = @mktime(0,0,0,intval(substr($data,5,2)),intval(substr($data,8,2)),intval(substr($data,0,4)));
        //день недели если нужно
        if ($w)
            $str .= $this->weekdays_f[intval(date("w",$mtime))].', ';
        $str .= date("d",$mtime).' ';
        $str .= $this->months_f[(intval(date("m",$mtime)))].' ';
        $str .= date("Y",$mtime).' г.';
        return $str;
    }

    /**
     * Сохраняет значение в сессию
     *
     * Функция сохраняет в сессии какое-то значение, доступ к
     * которому в дальнейшем можно получить с помощью метода pub_session_get.
     * Для сохранения значения необходимо указать его имя и непосредственное
     * само сохраняемое значение. В случае успешной установки возвращает
     * true, в случае неудачи – false.
     * Следует учитывать, что область видимости сохраненных
     * значений у каждого модуля своя.
     * @param string $name Имя сохраняемого значения
     * @param mixed $value Само сохраняемое значение
     * @return boolean
     */
    public function pub_session_set($name, $value = '')
    {
        if (!is_resource($value) && is_string($name))
        {
            $_SESSION['vars_kernel']['modules_session'][$this->pub_module_id_get()][$name] = serialize($value);
            return true;
        }
        else
            return false;
    }

    /**
     * Возвращает сохраненное значение из сессии
     *
     * Функция возвращает сохраненные в сессии значения. Для получения
     * значения необходимо указать имя, под которым значение было
     * сохранено. Если имя не указано, то будет возращён весь массив
     * сохраненных переменных.
     * Следует учитывать, что область видимости сохраненных
     * значений у каждого модуля своя.
     * @param string $name Имя получаемого значения
     * @param string $module Модуль для которого берём значение
     * @return mixed
     */
    public function pub_session_get($name = null, $module=null)
    {
        if (is_null($module))
            $module = $this->pub_module_id_get();
        switch (gettype($name))
        {
            // Если не указанна конкретная переменная, то вернем все.
            case 'NULL':
                return array_map('unserialize',$_SESSION['vars_kernel']['modules_session'][$module]);
                break;

            // Если указанна переменная и она существует в сесии - вернем только ее значение, иначе - false.
            case 'string':
                if (isset($_SESSION['vars_kernel']['modules_session'][$module][$name]))
                    return unserialize($_SESSION['vars_kernel']['modules_session'][$module][$name]);
                else
                    return null;
                break;

            // Если указаная переменная не типа string, то вернем false
            default:
                return false;
                break;
        }
    }

    /**
     * Удаляет все (или конкретные) сохранённые в сессии значения
     *
     * Функция очищает все значения, которые были сохранены модулем.
     * Если задан параметр $name то удаляется только одно конкретное
     * значение. Второй параметр можно использовать для очистки
     * сохраненных значений другого модуля.
     * @param string $name Имя удаляемого значения
     * @param string $module_id Идентификатор модуля, чьи значений будут удаляться.
     * @return boolean
     */
    public function pub_session_unset($name = null, $module_id = null)
    {
        if (is_null($module_id))
            $module_id = $this->pub_module_id_get();

        if (is_null($name) && isset($_SESSION['vars_kernel']['modules_session'][$module_id]))
        {
            unset($_SESSION['vars_kernel']['modules_session'][$module_id]);
            return true;
        }
        elseif (isset($_SESSION['vars_kernel']['modules_session'][$module_id][$name]))
        {
            unset($_SESSION['vars_kernel']['modules_session'][$module_id][$name]);
            return true;
        }
        else
            return false;
    }

    /**
     * Подменяет шаблон страницы сайта
     *
     * Функция применяется только в методах модулей, вызываемых средствами DA
     * (Direct Access). После вызова функции, шаблон страницы заменяется на указанный
     * и когда метод (модуля) закончит свою работу и вернёт управление классу,
     * строящему страницу, в качестве шаблона страницы, будет использоваться указанный файл.
     * Соответственно будут обработаны и выведены только те метки, которые указаны в
     * этом шаблоне.
     * @param string Путь и файл с новым шаблоном страницы.
     * @return boolean
     */

    public function pub_da_page_template_set($file_name)
    {
        $file_name = trim($file_name);
        if (!empty($file_name) && (@file_exists($file_name)))
        {
            $this->page_template_new = $file_name;
            return true;
        }
        else
            return false;

    }

    /**
     * Проверяет наличие подменённого шаблолна и возвращает путь к нему и имя
     * @return mixed
     */
    function priv_page_template_get_da()
    {
        if  (empty($this->page_template_new))
            return false;
        else
            return $this->page_template_new;
    }

    /**
     * Создаёт URL для вызова метода через DA.
     *
     * Метод используется в публичных методах модуля (как правило),
     * для формирования URL, по которому сможет перейти пользователь.
     * @param string $metod_name Имя метода модуля, который должен быть вызван
     * @return string
     */
    public function pub_da_link_create($metod_name)
    {
        $metod_name = trim($metod_name);
        if (empty($metod_name))
            return "#";
        if (isset($_SERVER['SSL']))
            $link = 'https://';
        else
            $link = 'http://';

        $link .=  $_SERVER['HTTP_HOST']."/".$this->pub_page_current_get().".html";
        $link .= "?da_modul=".$this->pub_module_id_get();
        $link .= "&da_metod=".$metod_name;
        if ((isset($_SERVER['QUERY_STRING'])) && (!empty($_SERVER['QUERY_STRING'])))
            $link .= "&".$_SERVER['QUERY_STRING'];
        return $link;
    }

    /**
     * Возвращает количество запросов в MySQL
     *
     * @return integer
     */
    public function pub_mysql_queries_get()
    {
        return $this->queriesCount;
    }


    /**
     * Подготовка ответа браузеру для форм, отправленных методом jspub_form_submit()
     *
     * Функция обрабатывает (накапливает) сообщения об ошибках и формирует ответную строку
     * для возврата её браузеру и обработки JavaScript-ом. В качестве параметра может быть
     * передано сообщение, которое будет отображено администратору и автоматически скроется
     * через несколько секунд.
     * Кроме того можно задать идентификатор левого пункта меню, на который нужно перейти
     * в случае отсутствия ошибок. Если такой идентификатор не задан, то форма останется
     * без изменений.
     * @param string $message Сообщение, выводимое в случае, если не было ошибок.
     * @param string $link_reload Идентификатор левого меню, на который необходимо осуществить переход.
     * @param integer $timeout таймаут в секундах (сколько будет висеть сообщение)
     * @return string
     */
    function pub_httppost_response($message = '', $link_reload = '', $timeout=3)
    {
        //Прежде надо проверить, есть ли ошибки.
        $text = '';
        if (count($this->response_post_error) > 0)
        {
            foreach ($this->response_post_error as $message_err)
            {
                $text .= $message_err."<br>";
            }
            //Заменим возможные языковые переменные
            $text = $this->priv_page_textlabels_replace($text);
        }
        if (!empty($message))
            $message = $this->priv_page_textlabels_replace($message);

        $label = $this->priv_page_textlabels_replace("[#admin_label_for_show_result#]");
        //Заменим возможные языковые переменные в массиве

        $errors_count=count($this->response_post_error);
        $return = array();
        $return['errore_count']    = $errors_count;
        $return['errore_text']     = $text;
        $return['result_message']  = $message;
        $return['redirect']        = $link_reload;
        $return['result_label']    = $label;
        if ($errors_count==0)
            $return['msg_timeout'] = $timeout;

        return $this->pub_json_encode($return);
    }

    /**
     * Функция регистрация ошибки при обработке POST запроса
     *
     * Функция вызывается при выполнении обработки POST запроса в административном
     * интерфейсе. При этом, данный пост запрос должен быть получен с помощью функции
     * JavaScript jspub_form_submit()
     * Если данная ошибка критическая (то есть дальше обработка запроса должна быть
     * прервана), то используется второй параметр (значение TRUE) в этом,  случае
     * будет возвращаться результат идентичный тому, что возвращает
     * функция pub_httppost_response().
     * <code>
     * public function start_admin()
     *{
     *    global $kernel;
     *
     *    $content = '';
     *    switch ($kernel->pub_section_leftmenu_get())
     *    {
     *        case 'users_list':
     *            $content = $this->list_users();
     *            break;
     *
     *        case 'edit_show':
     *            $content = $this->form_show();
     *            break;
     *
     *        case 'edit_save':
     *            $content = $this->form_save();
     *            break;
     *     }
     *
     *    return $content;
     *}
     *
     *function list_users()
     *{
     *    global $kernel;
     *
     *    $html = '';
     *    // Формирует таблицу со пользователями всем пользовтаелями
     *    //при клике по строке переход осуществялется на edit_show
     *
     *    return $html;
     *}
     *
     *function form_show()
     *{
     *    global $kernel;
     *
     *    $html = '';
     *    // Вызов метода для построения формы редактирования
     *    // отправка этой формы осуществляется c использованием
     *    // функция jspub_form_submit()
     *
     *    return $html;
     *}
     *
     *
     *function form_save()
     *{
     *    global $kernel;
     *
     *    //Забрали параметры из формы
     *    $name = $kernel->pub_httppost_get('name');
     *    if (empty($name))
     *    {
     *        //Форма заполнена не полностью, это критическая ошибка
     *       return $kernel->pub_httppost_errore('Форма заполнена не полностью', true);
     *    }
     *
     *    //Выполняем дейсвтия по сохранению данных или ещё какие-то проверки
     *
     *    //...
     *
     *    //При этом, в случае возникновения проблем, фиксируем их.
     *    //Например...
     *    $kernel->pub_httppost_errore('Такое имя уже используется');
     *
     *    //... или
     *    $kernel->pub_httppost_errore('Аккаунт временно отключён');
     *
     *    //После всех обработок необходимо вернуть результат
     *    $message = 'Запись успешно добавлена'; //сообщение о том что процесс успешно выполнен
     *    $link = 'users_list&myparam1=yes&myparam2=no'; //строка для перехода
     *
     *    return $kernel->pub_httppost_response($message, $link);
     *}
     * </code>
     * @param String $message Сообщение об ошибке.
     * @param Bool $critical Если True, то ошибка считается критической и выполнение останавливается.
     * @return Void | String
     */
    function pub_httppost_errore($message, $critical = false)
    {
        if (!empty($message))
            $this->response_post_error[] = $message;

        if ($critical)
            return $this->pub_httppost_response();
        return "";
    }

    function priv_frontcontent_set($content)
    {
        $this->content_for_show = $content;
    }

    function priv_frontcontent_get()
    {
        return $this->content_for_show;

    }

    //-------------- НЕТ в описании API ---------------------
    /**
     * Возвращает количество ошибок
     *
     * возвращается количество ошибок, которое было накоплено, путём вызова функции pub_httppost_errore
     * используется для анализа необходимости выполнения конкретных действий.
     * @return integer
     */
    function pub_httppost_errorecount()
    {
        return count($this->response_post_error);
    }


    function pub_content_curent_set($content)
    {
        $this->content_for_show = $content;
    }

    function pub_content_curent_get()
    {
        return $this->content_for_show;
    }

    /**
     * Возвращает путь от корня сайта до папки, где лежат файлы контента
     * @return string
     */
    function pub_path_for_content()
    {
        return $this->path_for_content;
    }

    /**
     * Сохраняет контент в файл
     *
     * @param string $file
     * @param string $content
     * @param boolean $only_ftp использовать только запись по фтп?
     * @param boolean $ignore_ftp_root_check пропустить проверку wwwroot для санты? (только для фтп)
     * @return boolean
     */
    function pub_file_save($file, $content, $only_ftp=false, $ignore_ftp_root_check = false)
    {
        //Следует учитывать что таким образом у нас работает
        //скрипт
        //Возможны ситуации (по мере возрастания проблем:
        /*
          1. Файл существует и может быть изменён
          2. Файл не существует - но межет быть создан -
             Такого варианта всего скорей не будет (если только
             права на папку 0777
          3. Файл существует но не может быть изменён
          4. Файл не существует и не может быть создан

          На практике, выходит что варианта только два
          1. Есть файл, или нет, но его можно записать
          скриптом, что собственно и происходит.
          2. Скрипт не может записать туда файл, пытаться изменить
          права на файл - безсмыслено - потому один вариант - писать
          файл по FTP.
        */
        $file = $this->priv_file_full_patch($file);

        //Узнаем собсвтенно что с этим файлом
        clearstatcache();

        //$content = stripslashes($content);

        //Сначала определяем, существует файл или нет

        $file_exist = file_exists($file);

        $parse_file = pathinfo($file);
        $curent_dir = $parse_file['dirname'];

        //Можем ли мы записать в сам файл или в папку, где он находиться
        if ($file_exist)
            $file_write = is_writable($file);
        else
            $file_write = is_writable($curent_dir);
        //Пока не важно, есть файл или нет, главное
        //что мы можем его переписать, что мы и делаем
        //иначе, нужно выполнять
        //дейсвтия в том случае, если скриптом файл не записать.
        if ($file_write && !$only_ftp)
            return $this->priv_file_save_script($file, $content);
        else
            return $this->priv_ftp_file_save($file, $content, $ignore_ftp_root_check);
        //В дальнейшем, можно сделать проверку, на то что файл дейсвтитльно был изменеён
    }

    /**
     * Открывает файл для записи и возвращает указатель на него
     * @param $file string
     * @param $mode
     * @return mixed
     */
    function pub_file_open($file, $mode)
    {
        //Перед открытием файла происходит проврека на то,
        //сужествет он или нет, и можем ли мы в него писать

        //Если писать мы в него можем - то просто вренём указатель на файл
        //Если писать не можем (или файла) нет - то создадаим
        if (empty($file))
            return 0;

        $file = $this->priv_file_full_patch($file);

        if ((file_exists($file)) && is_writable($file))
            return fopen($file, $mode);

        //Вот теперь чуть сложнее. Если файла просто нет - то мы его создаём
        //И если он существует, то необходимо взять его контент.
        //При этом, котнет лучше брать по частям
        $content = "";
        if (file_exists($file))
        {
            $content = file_get_contents($file);
        }
        //В случае, если файл сужествует, то возьмём - сделаем пока тоже самое
        $this->pub_file_save($file, $content);
        return fopen($file, $mode);
    }

    /**
     * Закрывает открытый файл
     *
     */
    function pub_file_close($resurce)
    {
        if ($resurce)
            fclose($resurce);
    }

    /**
     * Функция проверяет, присутствует ли в пути строка полного
     * пути до файла
     *
     * Проверка на наличе файла не производиться, так как функция может быть
     * применяться для новых файлов, которых ещё нет
     * @param string $file
     * @return string
     */

    function priv_file_full_patch($file)
    {
        $str       = trim($file);
        $root      = $this->pub_site_root_get();

        if ((mb_substr($str,0,1) !== "/") && (mb_substr($str,1,1) !== ":"))
            $str = "/".$str;
        $matches = false;
        //Определим, под чем мы вообще работаем, что бы правильно отстроить пути
        if ($this->is_windows)
            $str = str_replace("/", "\\", $str);
        if (!preg_match("'^".preg_quote($root)."'i", $str, $matches))
            $str = $root.$str;
        return $str;
    }

    /**
     * Перемещение файла
     *
     * @param string $path
     * @param string $new_path
     * @param boolean $close_dir
     * @param boolean $is_move_temp
     * @return string
     */
    function pub_file_move($path, $new_path, $close_dir = true, $is_move_temp = false)
    {
        //Работа будет аналогична тому, как это делается при
        //записи контента.
        if (!$is_move_temp)
            $path = $this->priv_file_full_patch($path);

        $new_path = $this->priv_file_full_patch($new_path);
        //Проверка на то, что перемещаемый файл существует
        if (!file_exists($path))
        {
            $this->debug("Не существует перемещаемого файла",true);
            die(0);
        }

        //Теперь попытаемся тупа его переместить в указанное место
        if (@move_uploaded_file($path, $new_path))
            return true;

        //Тупо не получилось, и необходимо сзделать это через стандартную
        //процедуру временной смены прав на папку.
        $this->pub_ftp_dir_chmod_change($new_path);

        move_uploaded_file($path, $new_path);
        if ($close_dir)
            $this->pub_ftp_dir_chmod_change($new_path);
        return true;
    }

    /**
     * Перемещение файла
     *
     * @param string $path
     * @param string $new_path
     * @param boolean $close_dir
     * @param boolean $test_full_path
     * @return boolean
     */
    function pub_file_copy($path, $new_path, $close_dir = true, $test_full_path = true)
    {
        //Работа будет аналогична тому, как это делается при записи контента.
        if ($test_full_path)
        {
            $path = $this->priv_file_full_patch($path);
            $new_path = $this->priv_file_full_patch($new_path);
        }

        //Проверка на то, что перемещаемый файл существует
        if (!file_exists($path))
        {
            $this->debug("Не существует копируемого файла - ".$path,true);
            die(0);
        }

        //Теперь попытаемся тупа его переместить в указанное место
        if (@copy($path, $new_path))
            return true;

        //Тупо не получилось, и необходимо сделать это через стандартную
        //процедуру временной смены прав на папку.
        $this->pub_ftp_dir_chmod_change($new_path);

        //Перед тем как переписывать, надо провреить, может такой файл уже есть
        //и тогда его надо удалить, удалить прям функцией PHP так как папка открыта
        if (file_exists($new_path))
            unlink($new_path);

        $ret = @copy($path, $new_path);
        if ($close_dir)
            $this->pub_ftp_dir_chmod_change($new_path);
        return $ret;
    }


    /**
     * Создаёт директорию скриптом
     * @param string $dirFull полный путь
     * @return bool
     */
    function pub_file_dir_create_script($dirFull)
    {
        $root = $this->pub_site_root_get()."/";
        if ($this->is_windows)
        {
            $dirFull = str_replace("\\","/", $dirFull);
            $root = str_replace("\\","/", $root);
        }
        $dir = substr($dirFull, strlen($root));
        $dirs = explode("/", $dir);

        $curr_dir = $root;
        foreach ($dirs as $cdir)
        {
            if (empty($cdir))
                continue;
            if (file_exists($curr_dir.$cdir))
            {
                $curr_dir .= $cdir."/";
                continue;
            }
            if (@mkdir($curr_dir.$cdir))
            {
                $curr_dir .= $cdir."/";
                continue;
            }
            else
                return false;
        }
        return true;
    }


    function pub_file_dir_create($dir)
    {
        if (empty($dir))
            return false;
        $dirFull = $this->priv_file_full_patch($dir);

        //Проверим, а может сама создаваема директория уже существует
        if (file_exists($dirFull))
            return true;
        if ($this->pub_file_dir_create_script($dirFull))
            return true;
        $ftpshnik = $this->get_ftp_client(true);
        if (!$ftpshnik)//не получилось что-то с фтп
            return false;

        //полный путь на фтп нам не нужен, только от корня санты
        $ftp_dir = $this->convert_path4ftp($dirFull, false);
        $res = $ftpshnik->createDirs($ftp_dir);
        if ($res)
            return true;
        else
            return false;
    }


    /**
     * Если в качестве параметра передан путь до папки,
     * то функция вернёт путь до родительской папки, что бы можно было поставить на неё права
     * @param $path string
     * @return string
     */
    function pub_file_dir_parent($path)
    {
        $path = trim($path);

        //Узнаем эту родительскую папку
        $tmp = explode("/", $path);
        //Провреим, а вдруг путь оканчивался тоже на слэш
        if (empty($tmp[(count($tmp)-1)]))
            unset($tmp[(count($tmp)-1)]);

        //Гарантировано, последний элемент содержит имя создаваемой папки
        //и его тоже надо удалить
        unset($tmp[(count($tmp)-1)]);

        //Проверим, а есть ли такая папка
        $dir_parent = join("/",$tmp);
        return $dir_parent;
    }

    /**
     * Функция проивзодит удаление заданного файла или папки
     *
     * Пока функциия не использует рекурсию
     * @param string $path
     * @param bool $delet_parent_dir Если true, и в качестве параметра передаётся директория,
     * то сама директория так же будет удалена, иначе, будет удалено только её содержимое.
     * @return bool
     */
    function pub_file_delete($path, $delet_parent_dir = true)
    {
        $status = false;

        if (empty($path))
            return $status;

        $path = $this->priv_file_full_patch($path);

        if (!file_exists($path))
            return $status;

        //Для директории удалим всё её содержимое
        if (is_dir($path))
        {
            //Проверим, а есть ли что удалить внутри этой дериктории
            if($handle = opendir($path))
            {
                while (false !== ($subdir = readdir($handle)))
                {
                    if (($subdir != ".") && ($subdir != ".."))
                    {
                        if (!$this->pub_file_delete($path."/".$subdir, true))
                        {
                            return false;
                        }
                    }
                }
                closedir($handle);
            }

            //Непосредственно удаление директории, если мы этого хотим
            if ($delet_parent_dir)
            {
                //Сначала просто пытаемся удалить директорию скриптом
                $status = @rmdir($path);
                if (!$status)
                {
                    //Удалить скриптом не удалось, поэтому, проставим на неё полные права
                    //и уже тогда удалим, полные права нужно ставить не только на саму директорию
                    //но и на её родителя
                    $this->pub_ftp_dir_chmod_change($path);
                    $dir_parent = $this->pub_file_dir_parent($path);
                    $this->pub_ftp_dir_chmod_change($dir_parent);

                    //B вот теперь снова пытаемся удалить
                    $status = @rmdir($path);
                    $this->pub_ftp_dir_chmod_change($dir_parent);
                }
            }
        }
        else
        {
            //А это, удаление непосредственно файла
            //Попытаемся удалить скриптом
            $status = @unlink($path);
            //Если не вышло, то сразу же попробуем удалить
            //через FTP
            if (!$status)
            {
                $status = $this->priv_ftp_file_delet($path);
            }
            //Если и через FTP не вышло, то попытаемся открыть права и удалить
            //ещё раз и так и так.
            if (!$status)
            {
                //Удалить файл не удалось, поставим права и тогда удалим.
                $this->pub_ftp_dir_chmod_change($path);
                //B вот теперь снова пытаемся удалить
                $status = @unlink($path);
                //Но сейчас, мы тоже могли не удалить файл, такак у самого
                //файла могут быть "плохие права". В этом случае мы сейчас так же
                if (!$status)
                    $status = $this->priv_ftp_file_delet($path);
                //И возварщаем права на папку
                $this->pub_ftp_dir_chmod_change($path);
            }
        }
        return $status;
    }

    function priv_file_save_script($file, $content, $del_if_exist = false)
    {
        $current_umask = umask(0);
        if ($del_if_exist && file_exists($file))
        {
            if (!@unlink($file))
                //if (!$this->pub_file_delete($file, false))
            {
                $this->debug($this->priv_page_textlabels_replace("[#kernel_ftp_change_chmod3#]<br><i>".$file."</i"), true);
                return false;
            }
        }
        $fp = fopen($file, "w+");
        fwrite($fp, $content);
        fclose($fp);
        @chmod($file, 0664);
        umask($current_umask);
        return true;
    }

    /**
     * Сохраняет содержимое в файл по фтп
     *
     * @param string $file путь куда сохранять
     * @param string $content содержимое
     * @param boolean $ignore_root_check пропускать проверку wwwroot для санты
     * @return boolean
     */
    private function priv_ftp_file_save($file, $content, $ignore_root_check=false)
    {
        //Устанавливаем связь с FTP, если её ещё нет
        $ftpshnik = $this->get_ftp_client(false, $ignore_root_check);
        if (!$ftpshnik)
        {
            $this->debug($this->priv_page_textlabels_replace("[#kernel_ftp_connect_error_common#]"), true);
            return false;
        }
        //Сначала попробуем поменть временно права на папку в 777
        //создать файл скриптом, и после этого вернуть прова обратно.

        //Меняем права, если всё хорошо то говорим то делаем дальше
        if ($this->pub_ftp_dir_chmod_change($file, false, false))
        {
            //Пишим файл скриптом
            $this->priv_file_save_script($file, $content, true);

            //Возвращаем старые права на папку
            $this->pub_ftp_dir_chmod_change($file);
        }
        else
        {
            //Это значит что всё плохо, и мы не можем поменять права на папку, что бы
            //записать файл, и нужно делать что-то и как-то по другому.
            //Попытаемся удалить этот файл и тогда ещё раз записать
            //$this->debug("Пришли", true);
            $this->pub_file_delete($file);
            if ($this->flag_can_recurs_save)
            {
                $this->flag_can_recurs_save = false;
                $this->pub_file_save($file, $content);
                $this->flag_can_recurs_save  = true;
            }
            else
            {
                //Ну и самый последний вариант,
                $ftp_path = $this->convert_path4ftp($file);
                if (!$ftpshnik->putFileContentsComplete($content, $ftp_path, true, true))
                {
                    $this->debug($this->priv_page_textlabels_replace("[#kernel_ftp_connect_error_prefix#]").$ftpshnik->getLastError(), true);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Конверитирует путь файла в правильный для фтп
     * т.к. в методы, работающие с файлами, могут передать и полный путь - тогда обрежем его
     * или путь вида /upload/file.txt, т.е. со слэшем вначале
     *
     * @param string $path
     * @param boolean $need_full
     * @return string
     */
    public function convert_path4ftp($path, $need_full = true)
    {
        $root = $this->pub_site_root_get();
        if ($this->is_windows)
            $root = str_replace("\\", "/", $root);
        $root .= "/";
        $rootpos = mb_strpos($path, $root);
        if ($rootpos !== false && $rootpos == 0)
            $path = mb_substr($path, mb_strlen($root));
        elseif (mb_substr($path, 0, 1) == "/") //
            $path = mb_substr($path, 1, mb_strlen($path)-1);
        //if ($this->curent_os == "winnt")
        //    $path = str_replace("\\", "/", $path);
        if ($need_full)
            $path = $this->santa_ftp_root.$path;
        return $path;
    }

    /**
     * Удаляет файл по фтп
     *
     * @param string $path путь
     * @return boolean
     */
    private function priv_ftp_file_delet($path)
    {
        $ftp = $this->get_ftp_client(false);
        if (!$ftp)
        {
            $this->debug($this->priv_page_textlabels_replace("[#kernel_ftp_connect_error_common#]" ), true);
            die(); //@todo return false or die() ?
        }
        $path = $this->convert_path4ftp($path);
        if ($ftp->deleteFile($path))
            return true;
        else
            return false;
    }

    function pub_ftp_dir_array_clear()
    {
        $this->ftp_dir_chmod_temp = array();
    }

    /**
     * Ставит полные или возвращает старые права на ФАЙЛ
     *
     * функция может вызваться в разной последовательности
     * Можно сначала открыть полные права для нескольких функций,
     * а потом вызвать их закрытие
     * @param string $file
     * @param boolean $show_errore
     * @return mixed
     */
    function pub_ftp_file_chmod_change($file, $show_errore = true)
    {
        if (empty($file))
            return false;
        $file = $this->priv_file_full_patch($file);
        //Сюда может быть передан только путь к файлу
        $curent_chmod_dir = $file;
        //$parse_file       = pathinfo($file);
        //if (!isset($parse_file['extension']))
        //    return;
        if (isset($this->ftp_dir_chmod_temp[$curent_chmod_dir]))
            $res = $this->pub_ftp_dir_chmod_close($file, false, false, $show_errore);
        else
            $res = $this->pub_ftp_dir_chmod_open($file, false, $show_errore);
        return $res;
    }

    /**
     * Ставит полные или возвращает старые права на папку
     *
     * функция может вызваться в разной последовательности
     * Можно сначала открыть полные права для нескольких функций,
     * а потом вызвать их закрытие
     *
     * @param string $file
     * @param boolean $change_for_parent
     * @param boolean $show_errore
     * @return mixed
     */
    function pub_ftp_dir_chmod_change($file, $change_for_parent = false, $show_errore = true)
    {
        if (empty($file))
            return false;
        $file = $this->priv_file_full_patch($file);
        //Сюда может быть передан как файл так и просто папка
        if (!is_dir($file))
        {
            $parse_file = pathinfo($file);
            $curent_chmod_dir = $parse_file['dirname'];
        }
        else
            $curent_chmod_dir = $file;
        //Если надо, то возьмём родителя этой папки и будем менять именно его
        if ($change_for_parent)
            $curent_chmod_dir = $this->pub_file_dir_parent($curent_chmod_dir);

        if (isset($this->ftp_dir_chmod_temp[$curent_chmod_dir]))
            $res = $this->pub_ftp_dir_chmod_close($file, $change_for_parent, false, $show_errore);
        else
            $res = $this->pub_ftp_dir_chmod_open($file, $change_for_parent, $show_errore);
        return $res;
    }

    /**
     * Открывает путь на запись
     *
     * @param string $file путь
     * @param boolean $change_for_parent
     * @param boolean $show_errore
     * @return mixed
     */
    public function pub_ftp_dir_chmod_open($file, $change_for_parent = false, $show_errore = true)
    {
        if (empty($file))
            return false;

        $file = $this->priv_file_full_patch($file);
        //Сюда может быть передан как файл так и просто папка
        if (!is_dir($file))
        {
            $parse_file = pathinfo($file);
            $curent_chmod_dir = $parse_file['dirname'];
        }
        else
            $curent_chmod_dir = $file;

        //Если надо, то возьмём родителя этой папки и будем менять именно его
        if ($change_for_parent)
            $curent_chmod_dir = $this->pub_file_dir_parent($curent_chmod_dir);

        //Теперь можем открывать доступ к папке
        clearstatcache();

        //Теперь собственно либо поставим полные права, либо вернём
        //те которые были
        //@todo возможно и такое: PHP Warning:  fileperms(): stat failed for
        $before_change = trim(substr(sprintf('%o', fileperms($curent_chmod_dir)), -4));

        //Сначала пробуем поставить полные права скриптом
        $result = @chmod($curent_chmod_dir, 0777);
        if (!$result)
        {
            //$chmod_cmd = 'CHMOD 0777 '.$change_dir;
            //$result = @ftp_site($conn_id, $chmod_cmd);
            $ftpshnik = $this->get_ftp_client(false);
            if (!$ftpshnik)
            {
                if ($show_errore)
                {
                    $str = $this->priv_page_textlabels_replace("[#kernel_ftp_connect_error_common#]" );
                    $this->debug($str, true);
                    die(0);
                }
                else
                    return false;
            }
            $ftp_path = $this->convert_path4ftp($curent_chmod_dir);
            $result = $ftpshnik->chmod($ftp_path, 0777);
            if (!$result)
            {
                if ($show_errore)
                {
                    $str = $this->priv_page_textlabels_replace("[#kernel_ftp_change_chmod1#] <br>[#kernel_ftp_change_chmod1_dir1#]: <i>".$curent_chmod_dir."</i> <br>[#kernel_ftp_change_chmod1_dir2#]: <i>".$ftp_path."</i>" );
                    $this->debug($str, true);
                    die(0);
                }
                else
                    return false;
            }
        }
        //сохраняем предыдущие права
        $this->ftp_dir_chmod_temp[$curent_chmod_dir] = $before_change;
        clearstatcache();
        return $before_change;
    }


    /**
     * Выставляет прежние права на папку
     *
     * @param string $file  путь
     * @param boolean $change_for_parent
     * @param mixed $chmod
     * @param boolean $show_errore
     * @return mixed
     */
    public function pub_ftp_dir_chmod_close($file, $change_for_parent = false, $chmod = false, $show_errore = true)
    {
        if (empty($file))
            return false;

        $file = $this->priv_file_full_patch($file);
        //Сюда может быть передан как файл так и просто папка
        if (!is_dir($file))
        {
            $parse_file = pathinfo($file);
            $curent_chmod_dir = $parse_file['dirname'];
        }
        else
            $curent_chmod_dir = $file;

        //Если надо, то возьмём родителя этой папки и будем менять именно его
        if ($change_for_parent)
            $curent_chmod_dir = $this->pub_file_dir_parent($curent_chmod_dir);
        //Если права не заданы, то попытаемся их взять из переменной ядра
        if (!$chmod)
            $chmod = $this->ftp_dir_chmod_temp[$curent_chmod_dir];
        else
        {
            $chmod = trim($chmod);
            if (preg_match("/^[0-9]+$/i", $chmod))
                return false;
        }

        //Теперь можем открывать доступ к папке
        clearstatcache();

        //Теперь собственно либо поставим полные права, либо вернём
        //те которые были
        //@todo возможно и такое: PHP Warning:  fileperms(): stat failed for
        $before_change = trim(substr(sprintf('%o', fileperms($curent_chmod_dir)), -4));

        //Сначала пробуем поставить полные права скриптом
        $result = @chmod($curent_chmod_dir, octdec($chmod));
        if (!$result)
        {
            //$chmod_cmd = 'CHMOD '.$chmod.' '.$change_dir;
            //$result = @ftp_site($conn_id, $chmod_cmd);
            $ftpshnik = $this->get_ftp_client(false, true);
            if (!$ftpshnik)
            {
                if ($show_errore)
                {
                    $str = $this->priv_page_textlabels_replace("[#kernel_ftp_connect_error_common#]" );
                    $this->debug($str, true);
                    die(0);
                }
                else
                    return false;
            }
            $ftp_path = $this->convert_path4ftp($curent_chmod_dir);
            $result = $ftpshnik->chmod($ftp_path, $chmod);
            if (!$result)
            {
                if ($show_errore)
                {
                    $this->debug($this->priv_page_textlabels_replace("[#kernel_ftp_change_chmod2#] <i>".$ftp_path."</i>" ), true);
                    die(0);
                } else
                    return false;
            }
        }
        //Удалим инофрмацию о папке
        if (isset($this->ftp_dir_chmod_temp[$curent_chmod_dir]))
            unset($this->ftp_dir_chmod_temp[$curent_chmod_dir]);

        clearstatcache();
        return $before_change;
    }





    //-------------- Больше не нужны, можно оставить как синонимы ----------------------------


    /**
     * Удаляет заданную директорию и всё её содержимое
     *
     * Удаляются как файлы, так и каталоги, которые вложены в переданную директорию. Функция
     * рекурсивная. Если второй параметр задан в true, это значит что саму передаваемую папку
     * необходимо оставить. Как правило, данный метод вызывается при деинсталляции модуля.
     * В отличии от pub_dir_create_in_files, здесь необходимо указывать полный путь
     * от корня сайта:
     * <code>
     *     function uninstall_children($id_module)
     *     {
     *       global $kernel;
     *
     *       $kernel->pub_dir_recurs_delete('content/files/'.$id_module');
     *      }
     * </code>
     * @param string $dir Путь к удаляемой директории
     * @param boolean $no_delet_main Признак того, что не надо удалять саму последнюю директорию
     * @access public
     * @return void
     */
    function pub_dir_recurs_delete($dir, $no_delet_main = true)
    {
        $this->pub_file_delete($dir, $no_delet_main);
    }

    function priv_path_root_set()
    {
        $root = realpath(dirname(__FILE__).'/../');
        $this->site_root = $root;
    }

    function pub_json_decode($param)
    {
        if (function_exists("json_decode"))
            return json_decode($param);
        //Значит функции нет, и надо подключать ZEND
        require_once(dirname(dirname(__FILE__))."/components/json/json.php");
        $sf_json = new Services_JSON();
        return $sf_json->decode($param);
    }

    function pub_json_encode($param)
    {
        //Сначала проверим, может существует функция кодирования уже в PHP
        // Т.е. вдруг модуль подключён
        if (function_exists("json_encode"))
            return json_encode($param);
        //Значит функции нет, и надо подключать ZEND
        require_once(dirname(dirname(__FILE__))."/components/json/json.php");
        $sf_json = new Services_JSON();
        return $sf_json->encode($param);
    }


    function pub_console_show($str)
    {
        if (isset($_SERVER['HTTP_HOST']))
        {//веб-версия
            if (is_string($str))
                $out = htmlspecialchars($str);
            else
                $out = htmlspecialchars(var_export($str, true));
            $html = '<script>start_interface.show_console_info("'.$out.'")</script>';
            $this->priv_output($html);
        }
        else
        {//консольная версия
            if (is_string($str))
                print $str;
            else
                print var_export($str, true);
            print "\n";
        }

    }


    public function pub_add_watermark2image($image, $watermark_image, $size)
    {
        if (!isset($watermark_image['path']) || !$watermark_image['path'])
            return $image;
        $copy = ImageCreateFromGIF($watermark_image['path']);
        $cpx = ImageSx($copy);
        $cpy = ImageSy($copy);

        $transparency = 50;
        if (isset($watermark_image['transparency']))
            $transparency = intval($watermark_image['transparency']);

        if ($transparency > 100)
            $transparency = 100;

        if ($transparency < 1)
            $transparency = 1;

        switch ($watermark_image['place'])
        {
            // По центру
            case 0:
                ImageCopyMerge($image, $copy, ($size['width']-$cpx)/2, ($size['height']-$cpy)/2, 0, 0, $cpx, $cpy, $transparency);
                break;

            // левый верхний край
            case 1:
                ImageCopyMerge($image, $copy, 0, 0, 0, 0, $cpx, $cpy, $transparency);
                break;

            // правый верхний край
            case 2:
                ImageCopyMerge($image, $copy, ($size['width']-$cpx), 0, 0, 0, $cpx, $cpy, $transparency);
                break;

            // правый нижний край
            case 3:
                ImageCopyMerge($image, $copy, ($size['width']-$cpx), ($size['height']-$cpy), 0, 0, $cpx, $cpy, $transparency);
                break;

            // левый нижний край
            case 4:
                ImageCopyMerge($image, $copy, 0, ($size['height']-$cpy), 0, 0, $cpx, $cpy, $transparency);
                break;
        }
        imagedestroy($copy);
        return $image;
    }

    /** Ресайз изображения + добавление водяного знака
     * @param resource $source рерурс - исходное изображение
     * @param array $size массив с размерами
     * @param array|int $watermark_image если 0, то НЕ добавляем водяной знак
     * @return bool|resource
     */
    function pub_image_resize_to($source, $size, $watermark_image = 0)
    {
        if (!isset($size['width']) || !isset($size['height']) || !is_resource($source))
            return false;

        $size['width']  = intval($size['width']);
        $size['height'] = intval($size['height']);

        //Пераметры исходного изображения
        $source_w = ImageSx($source);
        $source_h = ImageSy($source);
        $source_k = $source_w / $source_h;

        //исходная меньше чем нам нужно - ничего не делаем с ней, возвращаем копию + водяной знак если надо
        if ($source_w<=$size['width'] && $source_h<=$size['height'])
        {
            $dest = imagecreatetruecolor($source_w, $source_h);
            //imagecopy($dest, $source, 0, 0, 0, 0, $source_w, $source_h);
            imagealphablending($dest, false);
            imagesavealpha($dest,true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefilledrectangle($dest, 0, 0, $size['width'], $size['height'], $transparent);
            imagecopy($dest, $source, 0, 0, 0, 0, $source_w, $source_h);
            if ($watermark_image != 0)
                $dest=$this->pub_add_watermark2image($dest, $watermark_image, array("width"=>$source_w, "height"=>$source_h));
            return $dest;
        }

        //Сначала более простые варианты обработки, которые можно объеденить
        $offset_x = 0;
        $offset_y = 0;
        if (($size['height'] == 0) && ($size['width'] > 0))
        {
            //Высота может быть любой, а вот ширина жестко задана
            //и потому расчитываем новую нужноу высоту
            $size['height'] = round($size['width'] * (1/$source_k));
        }
        elseif (($size['height'] > 0) && ($size['width'] == 0))
        {
            //ПОхожий варинат, но жестко задана только высота
            //а ширина может быть любой.
            $size['width'] = round($size['height'] * $source_k);
        }
        elseif (($size['width'] > 0) || ($size['height'] > 0))
        {
            $proportion_X = $source_w / $size['width'];
            $proportion_Y = $source_h / $size['height'];

            $proportion = min($proportion_X, $proportion_Y);

            $target['width']  = $size['width'] * $proportion;
            $target['height'] = $size['height']* $proportion;

            $original['diagonal_center'] = round(sqrt(($source_w*$source_w)+($source_h*$source_h))/2);
            $target['diagonal_center']   = round(sqrt(($target['width']*$target['width'])+($target['height']*$target['height']))/2);

            $crop = round($original['diagonal_center'] - $target['diagonal_center']);

            if ($proportion_X !== $proportion_Y )
            {
                if ($proportion_X < $proportion_Y )
                    $offset_y = round((($source_h/2)*$crop)/$target['diagonal_center']);
                else
                    $offset_x = round((($source_w/2)*$crop)/$target['diagonal_center']);

                $source_w = $target['width'];
                $source_h = $target['height'];
            }

        }

        //Теперь осталось только выполнеить непосредственное преобразование
        $image_new = ImageCreateTrueColor($size['width'], $size['height']);

        imagealphablending($image_new, false);
        imagesavealpha($image_new,true);
        $transparent = imagecolorallocatealpha($image_new, 255, 255, 255, 127);
        imagefilledrectangle($image_new, 0,0, $size['width'], $size['height'], $transparent);

        // ...и копируем туда уменьшенное изображение
        imagecopyresampled($image_new, $source, 0,0, $offset_x, $offset_y, $size['width'], $size['height'], $source_w, $source_h);

        if ($watermark_image)
            $image_new=$this->pub_add_watermark2image($image_new, $watermark_image, $size);
        return $image_new;
    }
    /**
     * Выставляет необходимый лимит памяти для обработки картики
     *
     * @param string $filename
     * @return boolean
     */
    function priv_set_memory_for_image( $filename )
    {
        $imageInfo = getimagesize($filename);
        $MB = 1048576;  // number of bytes in 1M
        $K64 = 65536;    // number of bytes in 64K
        $TWEAKFACTOR = 2;  // 1.5 Or whatever works for you
        if (isset($imageInfo['channels']))
            $channels = $imageInfo['channels'];
        else
            $channels = 3;
        $memoryNeeded = round( ( $imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $channels/8  + $K64) * $TWEAKFACTOR);
        //ini_get('memory_limit') only works if compiled with "--enable-memory-limit" also
        //Default memory limit is 8MB so well stick with that.
        //To find out what yours is, view your php.ini file.
        $iniMemLimit = ini_get('memory_limit');
        if (empty($iniMemLimit))
            $memoryLimit = 8 * $MB;
        else
        {
            $memoryLimit = intval(substr($iniMemLimit, 0, strlen($iniMemLimit)))*$MB;
        }
        if (function_exists('memory_get_usage') && memory_get_usage() + $memoryNeeded > $memoryLimit)
        {
            $newLimit = ceil($memoryLimit/$MB) + ceil( ( memory_get_usage() + $memoryNeeded - $memoryLimit) / $MB);
            ini_set('memory_limit', $newLimit . 'M' );
            return true;
        }
        else
            return false;
    }

    /**
     * Проверяет, есть ли у нас данные для соединения с FTP
     *
     * @return boolean
     */
    public function is_ftp_credentionals_set()
    {
        if (!defined("FTP_HOST") || !defined("FTP_LOGIN") || !defined("FTP_PASS") ||
            trim(FTP_HOST)=="" || trim(FTP_LOGIN)=="" || trim(FTP_PASS)=="")
            return false;
        return true;
    }

    /**
     * Lazy-load и инициализация ftp-клиента
     * Возвращает инициализированный ftp-клиент, который уже "находится" в папке wwwroot санты
     *
     * @param boolean $need_root_chdir делать ли chdir в wwwroot с сантой на существующем фтп-клиенте?
     * @param boolean $ignore_root_check пропускать проверку wwwroot для санты?
     * @return ftpshnik
     */
    public function get_ftp_client($need_root_chdir=false, $ignore_root_check = false)
    {
        if (!is_null($this->ftp_client))
        {
            if ($need_root_chdir)
                $this->ftp_client->chdir($this->santa_ftp_root);
            return $this->ftp_client;
        }
        //проверим, можем ли мы создать фтп-клиент...
        if (!$this->is_ftp_credentionals_set())
            return false;
        require_once dirname(__FILE__)."/ftpshnik.class.php";
        $ftp = new ftpshnik(FTP_HOST, FTP_LOGIN, FTP_PASS);

        if (!$ftp->init())
            return false;
        //проверяем, есть ли сохранённый ftp_root для санты в настройках модуля kernel
        $kernel_props = $this->pub_module_serial_get('kernel');
        if (isset($kernel_props['ftp_root']))
        {
            if ($ignore_root_check)
            {
                $this->santa_ftp_root = $kernel_props['ftp_root'];
                $ftp->chdir($this->santa_ftp_root);
                $this->ftp_client = $ftp;
                return $ftp;
            }

            $ini_content = $ftp->getFileContentsComplete("ini.php", $kernel_props['ftp_root']);
            if ($ini_content && $ftp->isMySantaIni($ini_content, DB_HOST, DB_BASENAME, DB_USERNAME, PREFIX))
            {
                $this->santa_ftp_root = $kernel_props['ftp_root'];
                $ftp->chdir($this->santa_ftp_root);
                $this->ftp_client = $ftp;
                return $ftp;
            }
        }

        //не получилось загрузить ini-файл, или не тот ini-файл - будем пытаться найти его снова
        $santa_ftp_root = $ftp->findSantaRootByIni();
        if (!$santa_ftp_root)
            return false;
        $kernel_props['ftp_root'] = $santa_ftp_root;
        $this->pub_module_serial_set($kernel_props, 'kernel');
        $ftp->chdir($santa_ftp_root);
        $this->santa_ftp_root = $santa_ftp_root;
        $this->ftp_client = $ftp;
        return $ftp;
    }

    public function pub_add_line2file($filename, $string)
    {
        $isNewFile = false;
        $fullPath = $filename;
        if (!file_exists($fullPath))
            $isNewFile = true;
        $fh = @fopen($fullPath, "a");
        if ($fh)
        {
            @fwrite($fh, $string."\n");
            @fclose($fh);
        }
        if ($isNewFile)
            @chmod($fullPath, 0755);

    }


    /**
     * Делает транслит строки, +замена пробела на подчёркивание
     * @param string $str
     * @return string
     */
    public function pub_translit_string($str)
    {
        $chars = array(
            "А" => "A" ,
            "Б" => "B" ,
            "В" => "V" ,
            "Г" => "G" ,
            "Д" => "D" ,
            "Е" => "E" ,
            "Ё" => "YO" ,
            "Ж" => "ZH" ,
            "З" => "Z" ,
            "И" => "I" ,
            "Й" => "J" ,
            "К" => "K" ,
            "Л" => "L" ,
            "М" => "M" ,
            "Н" => "N" ,
            "О" => "O" ,
            "П" => "P" ,
            "Р" => "R" ,
            "С" => "S" ,
            "Т" => "T" ,
            "У" => "U" ,
            "Ф" => "F" ,
            "Х" => "X" ,
            "Ц" => "C" ,
            "Ч" => "CH" ,
            "Ш" => "SH" ,
            "Щ" => "SHH" ,
            "Ъ" => "'" ,
            "Ы" => "Y" ,
            "Ь" => "" ,
            "Э" => "E" ,
            "Ю" => "YU" ,
            "Я" => "YA" ,
            "а" => "a" ,
            "б" => "b" ,
            "в" => "v" ,
            "г" => "g" ,
            "д" => "d" ,
            "е" => "e" ,
            "ё" => "yo" ,
            "ж" => "zh" ,
            "з" => "z" ,
            "и" => "i" ,
            "й" => "j" ,
            "к" => "k" ,
            "л" => "l" ,
            "м" => "m" ,
            "н" => "n" ,
            "о" => "o" ,
            "п" => "p" ,
            "р" => "r" ,
            "с" => "s" ,
            "т" => "t" ,
            "у" => "u" ,
            "ф" => "f" ,
            "х" => "x" ,
            "ц" => "c" ,
            "ч" => "ch" ,
            "ш" => "sh" ,
            "щ" => "shh" ,
            "ъ" => "" ,
            "ы" => "y" ,
            "ь" => "" ,
            "э" => "e" ,
            "ю" => "yu" ,
            "я" => "ya",
            " " => "_");
        return strtr($str, $chars);
    }

    /**
     * Возвращает информацию о таблице из БД
     *
     * @param string $tname имя таблицы (без префикса)
     * @return array
     */
    public function db_get_table_info($tname)
    {
        global $kernel;
        $query  = "DESCRIBE `".$kernel->pub_prefix_get().strtolower($tname)."`";
        $result = $kernel->runSQL($query);
        $res = array();
        while ($row = mysql_fetch_assoc($result))
            $res[$row['Field']] = $row;
        mysql_free_result($result);
        return $res;
    }

}