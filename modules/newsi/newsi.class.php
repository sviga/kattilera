<?php
require_once realpath(dirname(__FILE__)."/../../")."/include/basemodule.class.php";

/**
 * Модуль "Новости"
 *
 * @author Александр Ильин mecommayou@gmail.com, s@nchez s@nchez.me
 * @copyright ArtProm (с) 2001-2011
 * @name newsi
 * @version 1.2
 *
 */
class newsi extends basemodule
{
    /**
     * Действие по умолчанию
     *
     * @var string
     */
    protected $action_default = '';

    /**
     * Название перемнной в GET запросе определяющей действие
     *
     * @var string
     */
    protected $action_name = 'view';

    /**
     * Префикс путей к шаблонам административного интерфейса
     *
     * @var string
     */
    protected $templates_admin_prefix = '';

    /**
     * Префикс путей к шаблонам пользовательского интерфейса
     *
     * @var string
     */
    protected $templates_user_prefix = '';

    public function newsi()
    {
        global $kernel;
        if ($kernel->pub_httpget_get('flush'))
            $kernel->pub_session_unset();
    }


    /**
     * Возвращает имя переменной GET запроса определяющей действие
     *
     * @return string
     */
    protected function get_action_name()
    {
        return $this->action_name;
    }

    /**
     * Возвращает значение указанного действия, если установленно или значение по умолчанию
     *
     * @param string $action_name Имя параметра в GET запросе
     * @return string
     */
    protected function get_action_value($action_name)
    {
        global $kernel;

        if ($kernel->pub_httpget_get($action_name))
            return $kernel->pub_httpget_get($action_name);
        else
            return $this->action_default;
    }


    /**
     * Устанавливает действие по умолчанию
     *
     * @param string $value Имя GET параметра определяющего действие
     */
    protected function set_action_default($value)
    {
        $this->action_default = $value;
    }

    /**
     * Устанавливает имя переменной в GET запросе определяющей действие
     *
     * @param string $name
     */
    protected function set_action_name($name)
    {
        $this->action_name = $name;
    }

    /**
     * Возвращет префикс путей к шаблонам административного интерфейса
     *
     * @return string
     */
    protected function get_templates_admin_prefix()
    {
        return $this->templates_admin_prefix;
    }

    /**
     * Устанавливает префикс к шаблонам админки
     *
     * @param string $prefix
     */
    protected function set_templates_admin_prefix($prefix)
    {
        $this->templates_admin_prefix = $prefix;
    }

    /**
     * Возвращет префикс путей к шаблонам пользовательского интерфейса
     *
     * @return string
     */
    protected function get_templates_user_prefix()
    {
        return $this->templates_user_prefix;
    }

    /**
     * Устанавливает префикс путей к шаблонам пользовательского интерфейса
     *
     * @param string $prefix
     */
    protected function set_templates_user_prefix($prefix)
    {
        $this->templates_user_prefix = $prefix;
    }


    function pub_show_selection($template)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse($template));

        $content = $this->get_template_block('form');
        $content = str_replace('%url%', $kernel->pub_page_current_get(), $content);
        $content = str_replace('%date_alone_name%', 'date', $content);
        $content = str_replace('%date_start_name%', 'start', $content);
        $content = str_replace('%date_stop_name%', 'stop', $content);

        return $content;
    }

    /**
     * Публичное действие для отображения ленты новостей
     *
     * @param string $template Путь к файлу с шаблонами
     * @param integer $limit Количество выводимых новостей
     * @param string $type Тип отбора новостей для вывода
     * @param string $page ID странцы сайта с архивом новостей
     * @param string $id_modules IDшники модулей
     * @return string
     */
    public function pub_show_lenta($template, $limit, $type, $page, $id_modules = '')
    {
        global $kernel;

        $limit=intval($limit);


        $offset = $this->pub_offset_get();
        $items = $this->pub_items_get($limit, $offset, true, $type, $id_modules);
        $total = $this->pub_news_avaiable_get($type, null,null,null,$id_modules);

        $this->set_templates($kernel->pub_template_parse($template));
        if (empty($items))
            $content = $this->get_template_block('no_data');
        else
        {
            $lines = '';
            foreach ($items as $item)
            {
                $line = $this->get_template_block('rows');

                if (empty($item['image']))
                    $line = str_replace('%image%', $this->get_template_block('no_images'), $line);
                else
                    $line = str_replace('%image%', str_replace(array('%image_source%', '%image_thumb%', '%image_big%'), array('/content/images/' . $item['module_id'] . '/source/' . $item['image'], '/content/images/' . $item['module_id'] . '/tn/' . $item['image'], '/content/images/' . $item['module_id'] . '/' . $item['image']), $this->get_template_block('image')), $line);
                if (empty($item['source_name']) && empty($item['source_url']))
                    $line = str_replace('%source%', '', $line);
                elseif (!empty($item['source_name']) && !empty($item['source_url']))
                    $line = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_name'], $item['source_url']), $this->get_template_block('source')), $line);
                elseif (!empty($item['source_name']) && empty($item['source_url']))
                    $line = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_name'], ''), $this->get_template_block('source_no_url')), $line);
                elseif (empty($item['source_name']) && !empty($item['source_url']))
                    $line = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_url']), $this->get_template_block('source')), $line);
                //Страницу, для отображения полного текста новости нужно брать в виде текущей только
                //в том случае, если берётся
                $page_ch = $page;
                if ($kernel->pub_module_id_get() !== $item['module_id'])
                {
                    $page_ch = $kernel->pub_modul_properties_get("page_for_lenta", $item['module_id']);
                    $page_ch = $page_ch['value'];
                }

                $line = str_replace('%id%', $item['id'], $line);
                $line = str_replace('%url%', $page_ch . '?id=' . $item['id'], $line);
                $line = str_replace('%date%', $item['date'], $line);
                $line = str_replace('%time%', $item['time'], $line);
                $line = str_replace('%header%', $item['header'], $line);
                $line = str_replace('%description_short%', $item['description_short'], $line);
                $line = str_replace('%description_full%', $item['description_full'], $line);
                $line = str_replace('%author%', $item['author'], $line);

                $lines .= $line . "\r\n";
            }

            $content = $this->get_template_block('content');
            $content = str_replace('%rows%', $lines, $content);

            $page_url=$kernel->pub_page_current_get().'?offset=';
            $max_pages=10;
            $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit,$page_url,$max_pages,'url'), $content);
        }

        return $content;
    }

    /**
     * Публичное действие для оотбражения архива новостей
     *
     * @param string $template Путь к файлу с шаблонами
     * @param integer $limit Количество новостей на страницу
     * @param string $type Тип отбора новостей для вывода
     * @param string $pages_type Тип постраничной навигации (уже не используется)
     * @param integer $max_pages Страниц в блоке
     * @return string
     */
    public function pub_show_archive($template, $limit, $type, $pages_type, $max_pages)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse($template));

        // Отображение конретной новости
        if ($kernel->pub_httpget_get('id') && is_numeric($kernel->pub_httpget_get('id')))
        {
            $item = $this->pub_item_get($kernel->pub_httpget_get('id'));
            if (!$item)
                frontoffice_manager::throw_404_error();
            $content = $this->get_template_block('fulltext');

            if (empty($item['image']))
                $content = str_replace('%image%', $this->get_template_block('fulltext_no_images'), $content);
            else
            {
                $image = $this->get_template_block('fulltext_image');
                $image = str_replace('%image_source%', '/content/images/' . $kernel->pub_module_id_get() . '/source/' . $item['image'], $image);
                $image = str_replace('%image_thumb%', '/content/images/' . $kernel->pub_module_id_get() . '/tn/' . $item['image'], $image);
                $image = str_replace('%image_big%', '/content/images/' . $kernel->pub_module_id_get() . '/' . $item['image'], $image);
                $content = str_replace('%image%', $image, $content);
            }

            if (empty($item['source_name']) && empty($item['source_url']))
                $content = str_replace('%source%', '', $content);
            elseif (!empty($item['source_name']) && !empty($item['source_url']))
                $content = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_name'], $item['source_url']), $this->get_template_block('fulltext_source')), $content);
            elseif (!empty($item['source_name']) && empty($item['source_url']))
                $content = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_name'], ''), $this->get_template_block('fulltext_source_no_url')), $content);
            elseif (empty($item['source_name']) && !empty($item['source_url']))
                $content = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_url']), $this->get_template_block('fulltext_source')), $content);
            $kernel->pub_page_title_add($item['header']);
            $content = str_replace('%date%', $item['date'], $content);
            $content = str_replace('%time%', $item['time'], $content);
            $content = str_replace('%header%', $item['header'], $content);
            $content = str_replace('%description_short%', $item['description_short'], $content);
            $content = str_replace('%description_full%', $item['description_full'], $content);
            $content = str_replace('%author%', $item['author'], $content);
            $kernel->pub_waysite_set(array('url' => '#', 'caption' => $item['header']));
        }
        else // Отображение списка
        {

            // Проверка при отборе за дату
            $date = null;
            if (preg_match('/\d{1,2}\-\d{1,2}\-\d{2,4}/', $kernel->pub_httpget_get('date')))
            {
                list($day, $month, $year) = explode("-", $kernel->pub_httpget_get('date'));
                if (checkdate($month, $day, $year))
                    $date = $year . '-' . $month . '-' . $day;

            }

            $start = null;
            if (preg_match('/\d{1,2}\-\d{1,2}\-\d{2,4}/', $kernel->pub_httpget_get('start')))
            {
                list($day, $month, $year) = explode("-", $kernel->pub_httpget_get('start'));
                if (checkdate($month, $day, $year))
                    $start = $year . '-' . $month . '-' . $day;
            }

            $stop = null;
            if (preg_match('/\d{1,2}\-\d{1,2}\-\d{2,4}/', $kernel->pub_httpget_get('stop')))
            {
                list($day, $month, $year) = explode("-", $kernel->pub_httpget_get('stop'));
                if (checkdate($month, $day, $year))
                    $stop = $year . '-' . $month . '-' . $day;
            }

            $offset=$this->pub_offset_get();
            if (empty($date) && empty($stop) && empty($start))
                $items = $this->pub_items_get($limit, $offset, false, $type, "");
            elseif (!empty($date))
                $items = $this->pub_items_get($limit, $offset, false, $type, "", $date);
            elseif (!empty($start) || !empty($stop))
                $items = $this->pub_items_get($limit, $offset, false, $type, "", null, $start, $stop);

            if (empty($items))
                $content = $this->get_template_block('no_data');
            else
            {
                $lines = '';
                foreach ($items as $item)
                {
                    $line = $this->get_template_block('rows');

                    if (empty($item['image']))
                        $line = str_replace('%image%', $this->get_template_block('no_images'), $line);
                    else
                        $line = str_replace('%image%', str_replace(array('%image_source%', '%image_thumb%', '%image_big%'), array('/content/images/' . $kernel->pub_module_id_get() . '/source/' . $item['image'], '/content/images/' . $kernel->pub_module_id_get() . '/tn/' . $item['image'], '/content/images/' . $kernel->pub_module_id_get() . '/' . $item['image']), $this->get_template_block('image')), $line);

                    if (empty($item['source_name']) && empty($item['source_url']))
                        $line = str_replace('%source%', '', $line);
                    elseif (!empty($item['source_name']) && !empty($item['source_url']))
                        $line = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_name'], $item['source_url']), $this->get_template_block('source')), $line);
                    elseif (!empty($item['source_name']) && empty($item['source_url']))
                        $line = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_name'], ''), $this->get_template_block('source_no_url')), $line);
                    elseif (empty($item['source_name']) && !empty($item['source_url']))
                        $line = str_replace('%source%', str_replace(array('%source_name%', '%source_url%'), array($item['source_url']), $this->get_template_block('source')), $line);

                    $line = str_replace('%id%', $item['id'], $line);
                    $line = str_replace('%url%', $kernel->pub_page_current_get() . '?id=' . $item['id'], $line);
                    $line = str_replace('%date%', $item['date'], $line);
                    $line = str_replace('%time%', $item['time'], $line);
                    $line = str_replace('%header%', $item['header'], $line);
                    $line = str_replace('%description_short%', $item['description_short'], $line);
                    $line = str_replace('%description_full%', $item['description_full'], $line);
                    $line = str_replace('%author%', $item['author'], $line);

                    $lines .= $line;
                }

                $content = $this->get_template_block('content');
                $content = str_replace('%rows%', $lines, $content);

                $total = $this->pub_news_avaiable_get($type, $kernel->pub_httpget_get('date'), $kernel->pub_httpget_get('start'), $kernel->pub_httpget_get('stop'));


                $page_url=$kernel->pub_page_current_get().'?';
                if ($this->check_date($kernel->pub_httpget_get('date')))
                    $page_url.= 'date=' . $kernel->pub_httpget_get('date').'&';
                if ($this->check_date($kernel->pub_httpget_get('start')))
                    $page_url.= 'start=' . $kernel->pub_httpget_get('start').'&';
                if ($this->check_date($kernel->pub_httpget_get('stop')))
                    $page_url.= 'stop=' . $kernel->pub_httpget_get('stop').'&';
                $page_url.='offset=';

                $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit,$page_url,$max_pages,'url'), $content);

            }
        }

        $content = str_replace('%url_back%', ((isset($_SERVER['HTTP_REFERER'])) ? ($_SERVER['HTTP_REFERER']) : ('/')), $content);

        return $content;
    }

    function pub_item_get($item_id)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_newsi', '`module_id` = "'.$kernel->pub_module_id_get().'"  AND `available` AND `id` ="'.intval($item_id).'"', '*,DATE_FORMAT(`date`, "%d-%m-%Y") AS `date`');
    }

    function pub_items_get($limit, $offset = 0, $lenta = false, $type = null, $modules = '', $date = null, $start = null, $stop = null)
    {
        global $kernel;

        $where = array();
        $order = array();

        $tableName = $kernel->pub_prefix_get()."_newsi";
        //Если дополнительные модули не передаются, значит запрашиваем новости только тек моудля
        if (empty($modules))
            $where[] = '`module_id` = "' . $kernel->pub_module_id_get() . '"';
        else
        {
            $tmp = '"' . str_replace(",", '","', $modules) . '"';
            $where[] = '`module_id` IN ("' . $kernel->pub_module_id_get() . '",' . $tmp . ')';
        }
        $where[] = '`available` = 1';
        if ($lenta)
            $where[] = '`lenta` = 1';
        switch ($type)
        {
            case 'past':
                $order[] = $tableName.'.`date` DESC';
                $order[] = '`time` DESC';
                $where[] = '`date` <= DATE(NOW())';
                break;
            case 'future':
                $order[] = $tableName.'.`date` ASC';
                $order[] = '`time` ASC';
                $where[] = $tableName.'.`date` >= DATE(NOW())';
                break;
            default:
                $order[] = $tableName.'.`date` DESC';
                $order[] = '`time` DESC';
                break;
        }

        if (!is_null($date))
            $where[] = $tableName.'.`date` = "' . $date . '"';

        if (!empty($start) && empty($stop))
            $where[] = $tableName.'.`date` >= "' . $start . '"';
        elseif (empty($start) && !empty($stop))
            $where[] = $tableName.'.`date` <= "' . $stop . '"';
        elseif (!empty($start) && !empty($stop))
            $where[] = $tableName.'.`date` BETWEEN "' . $start . '" AND "' . $stop . '"';
        return $kernel->db_get_list_simple("_newsi", implode(' AND ', $where) . ' ORDER BY ' . implode(', ', $order), '*, DATE_FORMAT(`date`, "%d-%m-%Y") AS `date`', $offset,$limit);
    }

    function pub_offset_get()
    {
        global $kernel;
        $offset = intval($kernel->pub_httpget_get('offset'));
        return $offset;
    }

    function pub_news_avaiable_get($type = null, $date = null, $start = null, $stop = null,$module_ids='')
    {
        global $kernel;

        if (preg_match('/\d{1,2}\-\d{1,2}\-\d{2,4}/', $date))
        {
            list($day, $month, $year) = explode("-", $date);
            if (checkdate($month, $day, $year))
                $date = $year . '-' . $month . '-' . $day;
            else
                $date = null;
        }
        else
            $date = null;

        if (preg_match('/\d{1,2}\-\d{1,2}\-\d{2,4}/', $start))
        {
            list($day, $month, $year) = explode("-", $start);
            if (checkdate($month, $day, $year))
                $start = $year . '-' . $month . '-' . $day;
            else
                $start = null;
        }
        else
            $start = null;

        if (preg_match('/\d{1,2}\-\d{1,2}\-\d{2,4}/', $stop))
        {
            list($day, $month, $year) = explode("-", $stop);
            if (checkdate($month, $day, $year))
                $stop = $year . '-' . $month . '-' . $day;
            else
                $stop = null;
        }
        else
            $stop = null;

        $where = array();


        if (!empty($module_ids))
        {
            $tmp = '"' . str_replace(",", '","', $module_ids) . '"';
            $where[] = '`module_id` IN ("' . $kernel->pub_module_id_get() . '",' . $tmp . ')';
        }
        else
            $where[] = '`module_id`="' . $kernel->pub_module_id_get() . '"';
        $where[] = '`available` = 1';

        switch ($type)
        {
            case 'past':
                $where[] = '`date` <= DATE(NOW())';
                break;
            case 'future':
                $where[] = '`date` >= DATE(NOW())';
                break;
            case 'default':
            default:
                break;
        }

        if (!empty($date))
            $where[] = '`date` = "' . $date . '"';

        if (!empty($start) && empty($stop))
            $where[] = '`date` >= "' . $start . '"';
        elseif (empty($start) && !empty($stop))
            $where[] = '`date` <= "' . $stop . '"';
        elseif (!empty($start) && !empty($stop))
            $where[] = '`date` BETWEEN "' . $start . '" AND "' . $stop . '"';
        $total=0;
        $trec= $kernel->db_get_record_simple("_newsi",implode(' AND ', $where),'COUNT(*) AS `count`');
        if ($trec)
            $total=$trec['count'];
        return $total;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления помтроеним меню
     * @return boolean
     */
    public function interface_get_menu($menu)
    {
        $menu->set_menu_block('[#news_menu_label#]');
        $menu->set_menu("[#news_menu_show_list#]", "show_list", array('flush' => 1));
        $menu->set_menu("[#news_menu_add_new#]", "show_add", array('flush' => 1));
        $menu->set_menu("[#news_menu_between#]", "select_between", array('flush' => 1));
        $menu->set_menu_block('[#news_menu_label1#]');
        $menu->set_menu_plain($this->priv_show_date_picker());
        //$this->priv_show_date_picker();
        $menu->set_menu_default('show_list');
        return true;
    }

    function priv_show_date_picker()
    {
        global $kernel;

        $this->set_templates_admin_prefix('modules/newsi/templates_admin/');
        $this->set_templates($kernel->pub_template_parse($this->get_templates_admin_prefix() . 'date_picker.html'));
        $content = $this->get_template_block('date_picker');
        return $content;
    }

    /**
     * Функция для отображаения административного интерфейса
     *
     * @return string
     */
    public function start_admin()
    {
        global $kernel;

        $this->set_templates_admin_prefix('modules/newsi/templates_admin/');
        switch ($kernel->pub_section_leftmenu_get())
        {
            default:
            case 'show_list':
                return $this->priv_show_list($this->priv_get_limit(), $this->priv_get_offset(), $this->priv_get_field(), $this->priv_get_direction(), $this->priv_get_start(), $this->priv_get_stop(), $this->priv_get_date());
                break;

            case 'select_between';
                $this->set_templates($kernel->pub_template_parse($this->get_templates_admin_prefix() . 'select_between.html'));
                $content = $this->get_template_block('form');
                $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('test_select_between'), $content);
                $content = str_replace('%form_action_sucsess%', 'admin/index.php?action=set_left_menu&leftmenu=show_list', $content);
                return $content;
                break;

            case 'test_select_between':
                return '{success: true}';
                break;

            case 'show_edit':
                return $this->show_item_form($kernel->pub_httpget_get('id'));
                break;

            case 'show_add':
                return $this->show_item_form();
                break;

            case 'item_save':
                $values = $kernel->pub_httppost_get('values');
                $values['description_full'] = $kernel->pub_httppost_get('content_html');
                $this->priv_item_save($values, $_FILES['image']);
                $kernel->pub_redirect_refresh_reload('show_list');
                break;

            case 'item_remove':
                $this->priv_item_delete($kernel->pub_httpget_get('id'));
                $kernel->pub_redirect_refresh('show_list');
                break;

            case 'list_actions':
                $this->priv_items_do_action($kernel->pub_httppost_get('action'), $kernel->pub_httppost_get('items'));
                $kernel->pub_redirect_refresh_reload('show_list');
                break;

            case 'show_between':
                break;
        }

        return ((isset($content)) ? ($content) : (null));
    }

    function priv_items_do_action($action, $items)
    {
        global $kernel;

        if (empty($items))
            return false;

        switch ($action)
        {
            case 'lenta_on':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `lenta` = "1" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'lenta_off':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `lenta` = "0" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'available_on':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `available` = "1" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'available_off':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `available` = "0" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'rss_on':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `rss` = "1" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'rss_off':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `rss` = "0" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'delete':
                $query = 'DELETE FROM `' . $kernel->pub_prefix_get() . '_newsi` WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            default:
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_newsi` SET `module_id` = "' . $action . '" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
        }

        return mysql_affected_rows();
    }

    function priv_item_delete($item_id)
    {
        global $kernel;
        $query = 'DELETE FROM `' . $kernel->pub_prefix_get() . '_newsi` WHERE `id` = ' . $item_id . '';
        $kernel->runSQL($query);
    }

    private function priv_item_save($item_data, $file)
    {
        global $kernel;
        list($day, $month, $year) = explode('.', $item_data['date']);
        //        if (preg_match('/^\d{1,2}:\d{1,2}:\d{1,2}$/', trim($item_data['time'])) && checkdate($month, $day, $year)) {
        $query = 'REPLACE `' . $kernel->pub_prefix_get() . '_newsi` (`id`, `module_id`, `date`, `time`, `available`, `lenta`, `delivery`, `rss`, `header`, `description_short`, `description_full`, `author`, `source_name`, `source_url`, `image`) '
            . ' VALUES (' . $item_data['id'] . '
            ,"' . $kernel->pub_module_id_get() . '"
            ,"' . $year . '-' . $month . '-' . $day . '"
            ,"' . $item_data['time'] . '"
            ,"' . ((isset($item_data['available'])) ? (1) : (0)) . '"
            ,"' . ((isset($item_data['lenta'])) ? (1) : (0)) . '"
            ,"' . ((isset($item_data['delivery'])) ? (1) : (0)) . '"
            ,"' . ((isset($item_data['rss'])) ? (1) : (0)) . '"
            ,"' . mysql_real_escape_string($item_data['header']) . '"
            ,"' . mysql_real_escape_string($item_data['description_short']) . '"
            ,"' . $item_data['description_full'] . '"
            ,"' . mysql_real_escape_string($item_data['author']) . '"
            ,"' . mysql_real_escape_string($item_data['source_name']) . '"
            ,"' . $item_data['source_url'] . '"
            ,"' . $this->priv_get_image_filename($file, ((isset($item_data['remove_image'])) ? (true) : (false)), $item_data['id']) . '"
            )';
        $kernel->runSQL($query);
        //        }
    }

    private function priv_get_image_filename($data, $remove = false, $item_id = null)
    {
        global $kernel;
        $filename = '';
        $old_filename = '';
        if (is_numeric($item_id))
        {
            $query = 'SELECT `image` FROM `' . $kernel->pub_prefix_get() . '_newsi` WHERE `id` = ' . $item_id . ' LIMIT 1';
            $result = $kernel->runSQL($query);
            if (mysql_num_rows($result))
                $old_filename = mysql_result($result, 0, 'image');
        }

        if (is_array($data) && $data['error'] == 0)
        {
            $img_big_width = $kernel->pub_modul_properties_get('img_big_width');
            $img_big_height = $kernel->pub_modul_properties_get('img_big_height');
            $img_small_width = $kernel->pub_modul_properties_get('img_small_width');
            $img_small_height = $kernel->pub_modul_properties_get('img_small_height');

            if (empty($img_big_height['value']) || empty($img_big_width['value']))
                $big = 0;
            else
            {
                $big = array(
                    'width' => $img_big_width['value'],
                    'height' => $img_big_height['value']
                );
            }

            if (empty($img_small_height['value']) || empty($img_small_width['value']))
                $thumb = 0;
            else
            {
                $thumb = array(
                    'width' => $img_small_width['value'],
                    'height' => $img_small_height['value']
                );
            }

            if (!empty($old_filename))
            {
                $kernel->pub_file_delete('content/images/' . $kernel->pub_module_id_get() . '/' . $old_filename);
                $kernel->pub_file_delete('content/images/' . $kernel->pub_module_id_get() . '/tn/' . $old_filename);
                $kernel->pub_file_delete('content/images/' . $kernel->pub_module_id_get() . '/source/' . $old_filename);
            }

            $filename = $kernel->pub_image_save($data['tmp_name'], '', 'content/images/' . $kernel->pub_module_id_get(), $big, $thumb);

            $kernel->pub_file_move($data['tmp_name'], 'content/images/' . $kernel->pub_module_id_get() . '/source/' . $filename, true, true);
        }
        elseif ($remove)
        {
            $kernel->pub_file_delete('content/images/' . $kernel->pub_module_id_get() . '/' . $old_filename);
            $kernel->pub_file_delete('content/images/' . $kernel->pub_module_id_get() . '/tn/' . $old_filename);
            $kernel->pub_file_delete('content/images/' . $kernel->pub_module_id_get() . '/source/' . $old_filename);
            $filename = '';
        }
        elseif (!empty($old_filename))
            return $old_filename;
        return $filename;
    }

    private function show_item_form($item_id = null)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse($this->get_templates_admin_prefix() . 'item_form.html'));

        $content = $this->get_template_block('form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('item_save'), $content);
        $content = str_replace('%id%', ((is_numeric($item_id)) ? ($item_id) : ('NULL')), $content);

        $item_data = $this->get_item_data($item_id);
        if (!empty($item_data['image']))
            $content = str_replace('%image_block%', ((is_numeric($item_id)) ? ($this->get_template_block('image')) : ('&nbsp;')), $content);
        else
            $content = str_replace('%image_block%', ('&nbsp;'), $content);

        //Если это ввод новой новости, то надо добавить значение текущего времени и даты
        if (is_null($item_id))
        {
            $content = str_replace('%time%', date('H:i:s'), $content);
            $content = str_replace('%date%', date('d.m.Y'), $content);
        }
        $content = str_replace($this->priv_get_item_data_search(), $this->priv_get_item_data_replace($item_id), $content);
        return $content;
    }

    function priv_get_item_data_replace($item_id)
    {
        global $kernel;
        $item_data = $this->get_item_data($item_id);

        $editor = new edit_content();
        $editor->set_edit_name('content_html');
        $editor->set_simple_theme(true);

        if (empty($item_data))
        {
            $deliver = $kernel->pub_modul_properties_get('deliver');
            $lenta = $kernel->pub_modul_properties_get('lenta');
            $rss = $kernel->pub_modul_properties_get('rss');

            return array(
                '',
                '',
                'checked',
                (($deliver['value'] == 'on' || $deliver['value'] == 'true') ? ('checked') : ('')),
                (($lenta['value'] == 'on' || $lenta['value'] == 'true') ? ('checked') : ('')),
                (($rss['value'] == 'on' || $rss['value'] == 'true') ? ('checked') : ('')),
                '',
                '',
                $editor->create(),
                '',
                '',
                '',
                ''
            );
        }
        else
        {
            $editor->set_content($item_data['description_full']);

            return array(
                str_replace('-','.',$item_data['date']),
                $item_data['time'],
                ($item_data['lenta'] == 1) ? ('checked') : (''),
                ($item_data['available'] == 1) ? ('checked') : (''),
                ($item_data['delivery'] == 1) ? ('checked') : (''),
                ($item_data['rss'] == 1) ? ('checked') : (''),
                htmlspecialchars($item_data['header']),
                htmlspecialchars($item_data['description_short']),
                $editor->create(),
                htmlspecialchars($item_data['author']),
                htmlspecialchars($item_data['source_name']),
                $item_data['source_url'],
                '/content/images/' . $kernel->pub_module_id_get() . '/tn/' . $item_data['image']
            );
        }
    }

    function priv_get_item_data_search()
    {
        $array = array(
            '%date%',
            '%time%',
            '%available%',
            '%lenta%',
            '%delivery%',
            '%rss%',
            '%header%',
            '%description_short%',
            '%description_full%',
            '%author%',
            '%source_name%',
            '%source_url%',
            '%image_url%'
        );

        return $array;
    }

    /**
     * Возвращает данные по указанному ID
     *
     * @param integer|null $item_id
     * @return array
     */
    private function get_item_data($item_id)
    {
        global $kernel;
        if (!is_numeric($item_id))
            return array();
        //Дату необходимо вернуть в формате ДД-ММ-ГГГГ
        $item=$kernel->db_get_record_simple('_newsi',"`id` = '".$item_id."'","*, DATE_FORMAT(`date`,'%d-%m-%Y') AS date");
        return $item;
    }


    private function priv_get_limit()
    {
        global $kernel;
        $property = $kernel->pub_modul_properties_get('news_per_page');
        if ($property['isset'] && is_numeric($property['value']))
            return $property['value'];
        else
            return 10;
    }

    /**
     * Возвращает текущий сдвиг
     *
     * @return integer
     */
    private function priv_get_offset()
    {
        global $kernel;
        $offset = $kernel->pub_httpget_get('offset');
        if (trim($offset) == '')
            $offset = $kernel->pub_session_get('offset');
        if (!is_numeric($offset))
            $offset = 0;
        $kernel->pub_session_set('offset', $offset);
        return $offset;
    }

    private function priv_get_direction()
    {
        global $kernel;
        $direction = $kernel->pub_httpget_get('direction');
        if (empty($direction))
            $direction = $kernel->pub_session_get('direction');
        if (!in_array(strtoupper($direction), array('ASC', 'DESC')))
            $direction = 'DESC';
        $kernel->pub_session_set('direction', $direction);
        return $direction;
    }

    private function priv_get_field()
    {
        global $kernel;
        $query = 'SHOW COLUMNS FROM `' . $kernel->pub_prefix_get() . '_newsi`';
        $result = $kernel->runSQL($query);
        $fields = array();
        while ($row = mysql_fetch_assoc($result))
        {
            $fields[] = $row['Field'];
        }
        mysql_free_result($result);
        $field = $kernel->pub_httpget_get('field');
        if (empty($field))
            $field = $kernel->pub_session_get('field');
        if (!in_array($field, $fields))
            $field = 'date';
        $kernel->pub_session_set('field', $field);
        return $field;
    }

    private function priv_get_start()
    {
        global $kernel;
        $start = $kernel->pub_httpget_get('start');
        if (empty($start))
            $start = $kernel->pub_session_get('start');
        $kernel->pub_session_set('start', $start);
        return $start;
    }

    private function priv_get_stop()
    {
        global $kernel;
        $stop = $kernel->pub_httpget_get('stop');
        if (empty($stop))
            $stop = $kernel->pub_session_get('stop');
        $kernel->pub_session_set('stop', $stop);
        return $stop;
    }

    private function priv_get_date()
    {
        global $kernel;
        $date = $kernel->pub_httpget_get('date');
        if (empty($date))
            $date = $kernel->pub_session_get('date');
        $kernel->pub_session_set('date', $date);
        return $date;
    }


    /**
     * Отображает список новостей
     *
     * @param integer $limit Лимит новостей
     * @param integer $offset Сдвиг
     * @param string $field Поле для сортировки
     * @param string $direction НАправление сортировки
     * @param string $start
     * @param string $stop
     * @param string $date
     * @return string
     */
    private function priv_show_list($limit, $offset, $field, $direction, $start = null, $stop = null, $date = null)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse($this->get_templates_admin_prefix() . 'show_list.html'));

        if (!is_null($start) && !is_null($stop))
            $cond='`module_id` = "'.$kernel->pub_module_id_get() .'" AND `date` BETWEEN "'.$start.'" AND "'.$stop.'" ORDER BY `'.$field.'` '.$direction;
        elseif (!is_null($date))
            $cond='`module_id` = "'.$kernel->pub_module_id_get() .'" AND `date` = "' . $date . '" ORDER BY `'.$field.'` '.$direction;
        else
            $cond='`module_id` = "'.$kernel->pub_module_id_get() .'" ORDER BY `'.$field.'` '.$direction;

        $rows = $kernel->db_get_list_simple('_newsi',$cond, '*, DATE_FORMAT(`date`, "%d-%m-%Y") AS `date_rus`', $offset, $limit);


        if (count($rows)==0)
            return $this->get_template_block('no_data');

        $lines = array();
        $first_element_number = $offset + 1;
        foreach($rows as $row)
        {
            $line = $this->get_template_block('table_body');
            $line = str_replace('%number%', $first_element_number++, $line);
            $line = str_replace('%id%', $row['id'], $line);
            $line = str_replace('%date%', $row['date_rus'], $line);
            $line = str_replace('%header%', $row['header'], $line);
            $line = str_replace('%available%', (($row['available']) ? ($this->get_template_block('on')) : ($this->get_template_block('off'))), $line);
            $line = str_replace('%lenta%', (($row['lenta']) ? ($this->get_template_block('on')) : ($this->get_template_block('off'))), $line);
            $line = str_replace('%rss%', (($row['rss']) ? ($this->get_template_block('on')) : ($this->get_template_block('off'))), $line);
            $line = str_replace('%author%', $row['author'], $line);
            $line = str_replace('%action_edit%', 'show_edit', $line);
            $line = str_replace('%action_remove%', 'item_remove', $line);
            $lines[] = $line;
        }

        $header = $this->get_template_block('table_header');
        $header = str_replace('%img_sort_' . $field . '%', (($direction == 'ASC') ? ($this->get_template_block('img_sort_asc')) : ($this->get_template_block('img_sort_desc'))), $header);
        $header = preg_replace('/\%img_sort_\w+%/', '', $header);

        $content = $header . implode("\n", $lines) . $this->get_template_block('table_footer');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('list_actions'), $content);

        $modules = $kernel->pub_modules_get('newsi');
        $array = array();
        foreach ($modules as $module_id => $properties)
        {
            if ($module_id != $kernel->pub_module_id_get())
                $array[$module_id] = $properties['caption'];
        }
        if (count($modules) > 1)
        {
            $actions = array(
                '[#news_actions_simple#]' => array(
                    'lenta_on' => '[#news_show_list_action_lenta_on#]',
                    'lenta_off' => '[#news_show_list_action_lenta_off#]',
                    'available_on' => '[#news_show_list_action_available_on#]',
                    'available_off' => '[#news_show_list_action_available_off#]',
                    'rss_on' => '[#news_show_list_action_rss_on#]',
                    'rss_off' => '[#news_show_list_action_rss_off#]',
                    'delete' => '[#news_show_list_action_delete#]'
//                    'move' => '[#news_show_list_action_move#]'
                ),
                '[#news_actions_advanced#]' => $array
            );
            $content = str_replace('%actions%', $this->priv_show_html_select('action', $actions, array(), true), $content);
        }
        else
        {
            $actions = array(
                'lenta_on' => '[#news_show_list_action_lenta_on#]',
                'lenta_off' => '[#news_show_list_action_lenta_off#]',
                'available_on' => '[#news_show_list_action_available_on#]',
                'available_off' => '[#news_show_list_action_available_off#]',
                'rss_on' => '[#news_show_list_action_rss_on#]',
                'rss_off' => '[#news_show_list_action_rss_off#]',
                'delete' => '[#news_show_list_action_delete#]',
                'move' => '[#news_show_list_action_move#]'
            );
            $content = str_replace('%actions%', $this->priv_show_html_select('action', $actions), $content);
        }

        $content = str_replace('%pages%', (is_null($date) ? ($this->priv_show_pages($offset, $limit, $field, $direction, $date, $start, $stop)) : ('')), $content);
        $sort_headers = $this->priv_get_sort_headers($field, $direction, $kernel->pub_httpget_get('date'), $start, $stop);
        $content = str_replace(array_keys($sort_headers), $sort_headers, $content);

        return $content;
    }

    private function priv_get_sort_headers($field, $direction, $date = null, $start = null, $stop = null)
    {
        $url = 'show_list&offset=0&field=%field%&direction=%direction%';

        if (!empty($date))
            $url .= '&date=' . $date;
        elseif (!empty($start) && !empty($stop))
            $url .= '&start=' . $start . '&stop=' . $stop;

        $array = array( //
            '%url_sort_id%' => (($field == 'id') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'id'), $url)) : (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'id'), $url))),
            '%url_sort_date%' => (($field == 'date') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'date'), $url)) : (str_replace(array('%direction%', '%field%'), array('ASC', 'date'), $url))),
            '%url_sort_header%' => (($field == 'header') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'header'), $url)) : (str_replace(array('%direction%', '%field%'), array('ASC', 'header'), $url))),
            '%url_sort_available%' => (($field == 'available') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'available'), $url)) : (str_replace(array('%direction%', '%field%'), array('ASC', 'available'), $url))),
            '%url_sort_lenta%' => (($field == 'lenta') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'lenta'), $url)) : (str_replace(array('%direction%', '%field%'), array('ASC', 'lenta'), $url))),
            '%url_sort_rss%' => (($field == 'rss') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'rss'), $url)) : (str_replace(array('%direction%', '%field%'), array('ASC', 'rss'), $url))),
            '%url_sort_author%' => (($field == 'author') ? (str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC') ? ('DESC') : ('ASC'), 'author'), $url)) : (str_replace(array('%direction%', '%field%'), array('ASC', 'author'), $url))),
        );
        return $array;
    }

    private function priv_show_pages($offset, $limit, $field, $direction, $date = null, $start = null, $stop = null)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse($this->get_templates_admin_prefix() . 'pages.html'));

        if (!empty($date))
        {
            $query = 'SELECT COUNT(*) AS totalCount FROM `' . $kernel->pub_prefix_get() . '_newsi` WHERE `module_id` = "' . $kernel->pub_module_id_get() . '" AND `date` = "' . $date . '"';
        }
        elseif (!empty($start) && !empty($stop))
        {
            $query = 'SELECT COUNT(*) AS totalCount FROM `' . $kernel->pub_prefix_get() . '_newsi` WHERE `module_id` = "' . $kernel->pub_module_id_get() . '" AND `date` BETWEEN "' . $start . '" AND "' . $stop . '"';
        }
        else
        {
            $query = 'SELECT COUNT(*) AS totalCount FROM `' . $kernel->pub_prefix_get() . '_newsi` WHERE `module_id` = "' . $kernel->pub_module_id_get() . '"';
        }

        $total = mysql_result($kernel->runSQL($query), 0, 'totalCount');
        $pages = ceil($total / $limit);

        if ($pages == 1)
        {
            return '';
        }

        $content = array();
        for ($page = 0; $page < $pages; $page++)
        {
            $url = 'show_list&offset=' . $limit * $page . '&field=' . $field . '&direction=' . $direction;

            if (!empty($date))
            {
                $url .= '&date=' . $date;
            }
            elseif (!empty($start) && !empty($stop))
            {
                $url .= '&start=' . $start . '&stop=' . $stop;
            }
            $content[] = str_replace(array('%url%', '%page%'), array($url, ($page + 1)), (($limit * $page == $offset) ? ($this->get_template_block('page_passive')) : ($this->get_template_block('page'))));
        }

        $content = implode($this->get_template_block('delimeter'), $content);

        return $content;
    }

    /**
     * Возвращает html код select'a
     *
     * @param string $name
     * @param array $array
     * @param array $selected
     * @param boolean $optgruop
     * @param string $style
     * @param boolean $multiple
     * @param integer $size
     * @param boolean $disabled
     * @param string $adds
     * @return string
     */
    private function priv_show_html_select($name, $array, $selected = array(), $optgruop = false, $style = "", $multiple = false, $size = 1, $disabled = false, $adds = '')
    {
        $html_select = '<select id="' . $name . '" ' . ($multiple ? 'multiple="multiple"' : '') . ' size="' . $size . '" name="' . $name . '" style="' . $style . '"' . ($disabled ? 'disabled="disabled"' : '') . ' class="text" ' . $adds . '>' . "\n";

        switch ($optgruop)
        {
            case false:
                foreach ($array as $option => $label)
                {
                    if (!is_null($selected) && in_array($option, $selected))
                    {
                        $html_select .= '<option value="' . $option . '" selected="selected"">' . htmlspecialchars($label) . '</option>' . "\n";
                    }
                    else
                    {
                        $html_select .= '<option value="' . $option . '">' . $label . '</option>' . "\n";
                    }
                }
                break;

            case true:
                foreach ($array as $key => $value)
                {
                    $html_select .= '<optgroup label="' . $key . '">' . "\n";
                    foreach ($value as $option => $label)
                    {
                        if (!is_null($selected) && in_array($option, $selected))
                        {
                            $html_select .= '<option value="' . $option . '" selected="selected" style="background-color: white;">' . htmlspecialchars($label) . '</option>' . "\n";
                        }
                        else
                        {
                            $html_select .= '<option value="' . $option . '">' . $label . '</option>' . "\n";
                        }
                    }
                    $html_select .= '</optgroup>' . "\n";
                }
                break;
        }
        $html_select .= '</select>' . "\n";
        return $html_select;
    }

    function check_date($date)
    {
        if (preg_match('/(\d{1,2})\-(\d{1,2})\-(\d{2,4})/', $date, $subpatterns) && checkdate($subpatterns[2], $subpatterns[1], $subpatterns[3]))
            return true;
        else
            return false;
    }

    public function get_news_for_submit($modul_id)
    {
        global $kernel;
        $rows = $kernel->db_get_list_simple("_newsi",'`module_id` = "'.$modul_id.'" AND `delivery` = 1 AND `post_date` IS NULL');
        $arr = array();
        foreach ($rows as $row)
        {
            $arr[$row['id']]['time'] = $row['date'] . ' ' . $row['time'];
            $arr[$row['id']]['header'] = $row['header'];
            $arr[$row['id']]['announce'] = $row['description_short'];
        }
        return $arr;
    }

    /**
     * Возвращает все поля новости и записывает информацию о том что новость отослана
     *
     * @param integer $id
     * @param string $time
     * @param boolean $is_test
     * @return array
     */
    function get_full_info_and_submit($id, $time, $is_test = true)
    {
        global $kernel;

        if (!$is_test)
        {
            //Сначала напишем что новость отослана
            $query = "UPDATE " . $kernel->pub_prefix_get() . "_newsi
                        SET post_date = '$time'
                        WHERE id = $id
                        LIMIT 1";

            $kernel->runSQL($query);
        }

        //А теперь вернём полную инфу по новости
        $row = $kernel->db_get_record_simple('_newsi',"id='".$id."'");

        if (!$row)
            return array();
        return $row;

    }
}