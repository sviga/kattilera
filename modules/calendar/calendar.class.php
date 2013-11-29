<?php
require_once realpath(dirname(__FILE__)."/../../")."/include/basemodule.class.php";

/**
     * Модуль "Календарь"
 *
 * @author sviga sviga@gmail.com
 * @name calendar
 * @version 1.0
 *
 */
class calendar extends basemodule
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

    public function calendar()
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

    /**
     * Публичное действие для отображения календаря
     *
     * @param string $template Путь к файлу с шаблонами
     * @param string $id_modules IDшники модулей
     * @return string
     */
    public function pub_show_calendar($template, $id_modules = '') {
        global $kernel;

        $month = $kernel->pub_httppost_get("month");
        if($month == "") {
            $month = date('m');
        }
        $month = $this->pub_items_get($month);

        $calendareData = json_encode($month);
        if($kernel->pub_httppost_get("dataOnly")) {
            exit($calendareData);
        }

        $this->set_templates($kernel->pub_template_parse($template));

        $content = $this->get_template_block('content');
        $content = str_replace('%page_url%', $_SERVER['REQUEST_URI'], $content);
        $content = str_replace('%calendar_data%', $calendareData, $content);

        return $content;
    }

    function pub_item_get($item_id)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_calendar', '`module_id` = "'.$kernel->pub_module_id_get().'"  AND `available` AND `id` ="'.intval($item_id).'"', '*,DATE_FORMAT(`date`, "%d-%m-%Y") AS `date`');
    }

    function pub_items_get($month)
    {
        global $kernel;

        $where = array();

        $where[] = '`module_id` = "' . $kernel->pub_module_id_get() . '"';
        $where[] = '`available` = 1';
        $where[] = 'MONTH(`date`) = "' . $month . '"';

        return $kernel->db_get_list_simple("_calendar", implode(' AND ', $where), '*, DATE_FORMAT(`date`, "%d-%m-%Y") AS `date`');
    }

    function pub_offset_get()
    {
        global $kernel;
        $offset = intval($kernel->pub_httpget_get('offset'));
        return $offset;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления помтроеним меню
     * @return boolean
     */
    public function interface_get_menu($menu)
    {
        $menu->set_menu_block('[#calendar_menu_label#]');
        $menu->set_menu("[#calendar_menu_show_list#]", "show_list", array('flush' => 1));
        $menu->set_menu("[#calendar_menu_add_new#]", "show_add", array('flush' => 1));
        //$menu->set_menu("[#calendar_menu_between#]", "select_between", array('flush' => 1));
        $menu->set_menu_block('[#calendar_menu_label1#]');
        $menu->set_menu_plain($this->priv_show_date_picker());
        //$this->priv_show_date_picker();
        $menu->set_menu_default('show_list');
        return true;
    }

    function priv_show_date_picker()
    {
        global $kernel;

        $this->set_templates_admin_prefix('modules/calendar/templates_admin/');
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

        $this->set_templates_admin_prefix('modules/calendar/templates_admin/');
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
                $this->priv_item_save($values);
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
            case 'available_on':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_calendar` SET `available` = "1" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'available_off':
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_calendar` SET `available` = "0" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            case 'delete':
                $query = 'DELETE FROM `' . $kernel->pub_prefix_get() . '_calendar` WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
            default:
                $query = 'UPDATE `' . $kernel->pub_prefix_get() . '_calendar` SET `module_id` = "' . $action . '" WHERE `id` IN (' . implode(', ', $items) . ')';
                $kernel->runSQL($query);
                break;
        }

        return mysql_affected_rows();
    }

    function priv_item_delete($item_id)
    {
        global $kernel;
        $query = 'DELETE FROM `' . $kernel->pub_prefix_get() . '_calendar` WHERE `id` = ' . $item_id . '';
        $kernel->runSQL($query);
    }

    private function priv_item_save($item_data)
    {
        global $kernel;
        list($day, $month, $year) = explode('.', $item_data['date']);
        $query = 'REPLACE `' . $kernel->pub_prefix_get() . '_calendar` (`id`, `module_id`, `date`, `available`, `header`, `description`, `source_url`) '
            . ' VALUES (' . $item_data['id'] . '
            ,"' . $kernel->pub_module_id_get() . '"
            ,"' . $year . '-' . $month . '-' . $day . '"
            ,"' . ((isset($item_data['available'])) ? (1) : (0)) . '"
            ,"' . mysql_real_escape_string($item_data['header']) . '"
            ,"' . str_replace('\r\n', "<br/>", mysql_real_escape_string($item_data['description']))  . '"
            ,"' . $item_data['source_url'] . '"
            )';
        $kernel->runSQL($query);
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
                'checked',
                '',
                '',
                ''
            );
        }
        else
        {

            return array(
                str_replace('-','.',$item_data['date']),
                ($item_data['available']) ? ('checked') : (''),
                htmlspecialchars($item_data['header']),
                htmlspecialchars($item_data['description']),
                $item_data['source_url']
            );
        }
    }

    function priv_get_item_data_search()
    {
        $array = array(
            '%date%',
            '%available%',
            '%header%',
            '%description%',
            '%source_url%'
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
        $item=$kernel->db_get_record_simple('_calendar',"`id` = '".$item_id."'","*, DATE_FORMAT(`date`,'%d-%m-%Y') AS date");
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
        $query = 'SHOW COLUMNS FROM `' . $kernel->pub_prefix_get() . '_calendar`';
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

        $rows = $kernel->db_get_list_simple('_calendar',$cond, '*, DATE_FORMAT(`date`, "%d-%m-%Y") AS `date_rus`', $offset, $limit);


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
            $line = str_replace('%action_edit%', 'show_edit', $line);
            $line = str_replace('%action_remove%', 'item_remove', $line);
            $lines[] = $line;
        }

        $header = $this->get_template_block('table_header');
        $header = str_replace('%img_sort_' . $field . '%', (($direction == 'ASC') ? ($this->get_template_block('img_sort_asc')) : ($this->get_template_block('img_sort_desc'))), $header);
        $header = preg_replace('/\%img_sort_\w+%/', '', $header);

        $content = $header . implode("\n", $lines) . $this->get_template_block('table_footer');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('list_actions'), $content);

        $modules = $kernel->pub_modules_get('calendar');
        $array = array();
        foreach ($modules as $module_id => $properties)
        {
            if ($module_id != $kernel->pub_module_id_get())
                $array[$module_id] = $properties['caption'];
        }
        if (count($modules) > 1)
        {
            $actions = array(
                '[#calendar_actions_simple#]' => array(
                    'available_on' => '[#calendar_show_list_action_available_on#]',
                    'available_off' => '[#calendar_show_list_action_available_off#]',
                    'delete' => '[#calendar_show_list_action_delete#]'//
                ),
                '[#calendar_actions_advanced#]' => $array
            );
            $content = str_replace('%actions%', $this->priv_show_html_select('action', $actions, array(), true), $content);
        }
        else
        {
            $actions = array(
                'available_on' => '[#calendar_show_list_action_available_on#]',
                'available_off' => '[#calendar_show_list_action_available_off#]',
                'delete' => '[#calendar_show_list_action_delete#]'
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
            $query = 'SELECT COUNT(*) AS totalCount FROM `' . $kernel->pub_prefix_get() . '_calendar` WHERE `module_id` = "' . $kernel->pub_module_id_get() . '" AND `date` = "' . $date . '"';
        }
        elseif (!empty($start) && !empty($stop))
        {
            $query = 'SELECT COUNT(*) AS totalCount FROM `' . $kernel->pub_prefix_get() . '_calendar` WHERE `module_id` = "' . $kernel->pub_module_id_get() . '" AND `date` BETWEEN "' . $start . '" AND "' . $stop . '"';
        }
        else
        {
            $query = 'SELECT COUNT(*) AS totalCount FROM `' . $kernel->pub_prefix_get() . '_calendar` WHERE `module_id` = "' . $kernel->pub_module_id_get() . '"';
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
        $rows = $kernel->db_get_list_simple("_calendar",'`module_id` = "'.$modul_id.'" AND `delivery` = 1 AND `post_date` IS NULL');
        $arr = array();
        foreach ($rows as $row)
        {
            $arr[$row['id']]['time'] = $row['date'] . ' ' . $row['time'];
            $arr[$row['id']]['header'] = $row['header'];
            $arr[$row['id']]['announce'] = $row['description'];
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
            $query = "UPDATE " . $kernel->pub_prefix_get() . "_calendar
                        SET post_date = '$time'
                        WHERE id = $id
                        LIMIT 1";

            $kernel->runSQL($query);
        }

        //А теперь вернём полную инфу по новости
        $row = $kernel->db_get_record_simple('_calendar',"id='".$id."'");

        if (!$row)
            return array();
        return $row;

    }
}