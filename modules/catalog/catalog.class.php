<?php
require_once realpath(dirname(__FILE__)."/../../")."/include/basemodule.class.php";
require 'catalog.commons.class.php';

/**
 * Модуль "Каталог товаров"
 *
 * @author s@nchez s@nchez.me
 * @copyright ArtProm (с) 2001-2013
 * @name catalog
 * @version 2.0
 *
 */
class catalog extends BaseModule
{

    /**
     * Название параметра с id-шником категории для фронтэнда
     *
     * @var string
     */
    private $frontend_param_cat_id_name = "cid";

    /**
     * Название параметра с id-шником товара для фронтэнда
     *
     * @var string
     */
    private $frontend_param_item_id_name = "itemid";

    /**
     * Название параметра с лимитом на страницу для фронтэнда
     *
     * @var string
     */
    private $frontend_param_limit_name = "limit";

    /**
     * Название параметра со сдвигом для фронтэнда
     *
     * @var string
     */
    private $frontend_param_offset_name = "offset";

    /**
     * Название параметра со сдвигом для админки
     *
     * @var string
     */
    private $admin_param_offset_name = "offset";


    /**
     * На сколько увеличиваем порядок в списках
     *
     * @var integer
     */
    private $order_inc = 5;

    /**
     * Название cookie, где храним товары корзины
     *
     * @var string
     */
    private $basketid_cookie_name = "basket_";


    /**
     * Сколько дней хранить cookie
     *
     * @var integer
     */
    private $basketid_cookie_days = 7;


    /**
     * Запись текущего заказа для фронтенд
     *
     * @var array
     */
    private $current_basket_order = null;

    /**
     * Текущие товары в корзине
     *
     * @var array
     */
    private $current_basket_items = false;


    /**
     * Дорога добавлена?
     * @var boolean
     */
    private $is_way_set = false;


    /**
     * Корзина инициализирована?
     * @var boolean
     */
    private $is_basket_inited = false;

    private $structure_cookie_name;

    /** @var array айдишники текущих категорий, moduleid=>catid */
    private $current_cat_IDs = array();

    /**
     *  Конструктор класса модуля
     * @return catalog
     */
    public function __construct()
    {
        parent::__construct();
        global $kernel;
        if ($kernel->pub_httpget_get('flush'))
            $kernel->pub_session_unset();
        $this->structure_cookie_name = "tree_".$kernel->pub_module_id_get();
    }

    private function basket_init()
    {
        global $kernel;

        if ($this->is_basket_inited)
            return;
        $moduleid = $kernel->pub_module_id_get();
        if (rand(1,1000)==1) //чистим старые корзины с вероятностью 1 из 1000
            CatalogCommons::clean_old_baskets($moduleid);

        $this->is_basket_inited = true;

        //устанавливаем уникальное имя cookie для этого экземпляра модуля
        $this->basketid_cookie_name .= $moduleid;

        if (isset($_COOKIE[$this->basketid_cookie_name]))
        {
            //проверка что значение у cookie валидное
            if (preg_match("/^([a-z0-9]+)$/i", $_COOKIE[$this->basketid_cookie_name]))
            {
                //проверка, что запись в БД существует
                $this->current_basket_order = $this->get_basket_order_by_sid($_COOKIE[$this->basketid_cookie_name]);
            }
        }

        if (isset($_REQUEST["catalog_basket_additemid"]) && !empty($_REQUEST["catalog_basket_additemid"])) //добавляем товар в корзину если надо
        {
            $add_item_id = $_REQUEST["catalog_basket_additemid"];
            if (is_array($add_item_id)) //для добавления сразу группы товаров через чекбоксы
            {
                foreach ($add_item_id as $aiid => $aiq)
                {
                    $this->add_basket_item(intval($aiid), $aiq);
                }
                /*
                $aiids = array_keys($add_item_id);
                foreach ($aiids as $aid)
                {
                    $this->add_basket_item(intval($aid));
                }
                */
            }
            else //один товар
            {
                $qty = 1;
                if (isset($_REQUEST['qty']))
                {
                    $qty = intval($_REQUEST['qty']);
                    if ($qty < 1)
                        $qty = 1;
                }
                $this->add_basket_item(intval($add_item_id), $qty);
            }
            if (isset($_REQUEST['redir2']) && !empty($_REQUEST['redir2']))
            {
                $redirURL = $_REQUEST['redir2'];
                if (substr($redirURL, 0, 1) != "/")
                    $redirURL = "/".$redirURL;
            }
            else
                $redirURL = "/".$kernel->pub_page_current_get().".html";
            $kernel->pub_redirect_refresh_global($redirURL);
        }

        //+обновление кол-ва
        $param = $kernel->pub_httppost_get("catalog_basket_upd_qty");
        if (!empty($param))
        {
            $qties = $kernel->pub_httppost_get("basket_item_qty");
            foreach ($qties as $itemid => $qty)
                $this->update_basket_item_qty($itemid, $qty);
        }
        //+удаление из корзины
        $param = $kernel->pub_httpget_get("catalog_basket_removeitemid");
        if (!empty($param))
            $this->remove_item_from_basket($param);
    }

    /**
     * Возвращает текущий заказ корзины товаров
     * при необходимости создаёт запись в БД и ставит cookie с созданным ID-шником
     *
     * @return array
     */
    private function get_current_basket_order()
    {
        if (!empty($this->current_basket_order))
            return $this->current_basket_order;
        $new_basketsid = CatalogCommons::generate_random_string(32, true);
        $new_orderid = $this->add_basket_order($new_basketsid);
        $this->current_basket_order = array("id" => $new_orderid, "sessionid" => $new_basketsid);
        setcookie($this->basketid_cookie_name, $new_basketsid, time() + $this->basketid_cookie_days * 24 * 60 * 60);
        return $this->current_basket_order;
    }

    /**
     * Публичный метод для отображения названия элементов
     *
     * @param string $fields_template шаблон товара
     * @param string $category_template шаблон категории
     * @return string
     */
    public function pub_catalog_show_item_name($fields_template, $category_template)
    {
        global $kernel;
        $itemid = $kernel->pub_httpget_get($this->frontend_param_item_id_name);
        if (!empty($itemid))
        { //товар - используем шаблон
            $curr_item = $this->get_item_full_data($itemid);
            if (!$curr_item) //не нашли товар
                return '';
            foreach ($curr_item as $iprop => $ival)
            {
                $fields_template = str_replace("%".$iprop."%", $ival, $fields_template);
            }
            $fields_template = $this->process_variables_out($fields_template);
            //очистим оставшиеся метки
            $fields_template = $this->clear_left_labels($fields_template);

            return $fields_template;
        }
        else
        { //значит не товар, а категория
            $curr_cid = $this->get_current_catid(true);
            if ($curr_cid == 0) //не нашли, попробуем категорию по-умолчанию
            {
                $curr_cid = $this->get_default_catid();
                if ($curr_cid == 0) //не нашли
                    return '';
            }
            $curr_cat = $this->get_category($curr_cid);
            $cprops = CatalogCommons::get_cats_props();
            foreach ($cprops as $cprop)
            {
                $category_template = str_replace("%".$cprop['name_db']."%", $curr_cat[$cprop['name_db']], $category_template);
            }
            $category_template = $this->process_variables_out($category_template);
            $category_template = $this->clear_left_labels($category_template);
            return $category_template;
        }

    }

    /**
     * Сохраняет порядок свойств группы
     * @return void
     */
    private function save_gprops_order()
    {
        global $kernel;
        $porders = $kernel->pub_httppost_get("porder");
        foreach ($porders as $pid => $order)
        {
            if (is_numeric($order))
            {
                $query = "UPDATE `".$kernel->pub_prefix_get()."_catalog_item_props` SET `order`=".$order." WHERE `id`=".$pid;
                $kernel->runSQL($query);
            }
        }
        $groupid = $kernel->pub_httppost_get("group_id");
        //удалим все видимые, потом добавим только отмеченные
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_visible_gprops` WHERE group_id='.$groupid.
            " AND `module_id`='".$kernel->pub_module_id_get()."'";
        $kernel->runSQL($query);
        if (is_array($kernel->pub_httppost_get("grprop")))
        {
            $gprops = array_keys($kernel->pub_httppost_get("grprop"));
            foreach ($gprops as $propdb)
            {
                $this->add_group_visible_prop($kernel->pub_module_id_get(), $groupid, $propdb);
            }
        }
    }

    /**
     * Добавляет видимое свойство в группу
     *
     * @param string $moduleid
     * @param integer $groupid
     * @param string $propdb
     * @return void
     */
    private function add_group_visible_prop($moduleid, $groupid, $propdb)
    {
        global $kernel;
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_visible_gprops` (`group_id`,`module_id`,`prop`) '.
            'VALUES ('.$groupid.",'".$moduleid."','".$propdb."')";
        $kernel->runSQL($query);
    }

    /**
     * Сохраняет порядок свойств для заказа (корзины)
     *
     */
    private function save_order_fields_order()
    {
        global $kernel;
        $porders = $kernel->pub_httppost_get("porder");
        foreach ($porders as $pid => $order)
        {
            if (is_numeric($order))
            {
                $query = "UPDATE `".$kernel->pub_prefix_get()."_catalog_".$kernel->pub_module_id_get()."_basket_order_fields` SET `order`=".$order." WHERE `id`=".$pid;
                $kernel->runSQL($query);
            }
        }
    }

    /**
     * Возвращает следующий порядок (order) для товаров в категории
     *
     * @param integer $cat_id id-шник категории
     * @return integer след. порядок
     */
    private function get_next_order_in_cat($cat_id)
    {
        global $kernel;
        $query = 'SELECT `order` FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` '.
            'WHERE cat_id='.$cat_id.' ORDER BY `order` DESC LIMIT 1';
        $ret = $this->order_inc;
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $ret = $row['order'] + $this->order_inc;
        mysql_free_result($result);
        return $ret;
    }

    /**
     * Возвращает товар по общему свойству
     *
     * @param string $propname
     * @param string $propval
     * @return mixed
     */
    private function get_item_by_prop($propname, $propval)
    {
        global $kernel;
        $res = false;
        $query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` '.
            'WHERE `'.$propname.'` ="'.mysql_real_escape_string($propval).'" LIMIT 1';
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $res = $row;
        mysql_free_result($result);
        return $res;
    }

    /**
     * Добавляет связь между товарами
     *
     * @param integer $itemid1
     * @param integer $itemid2
     * @return void
     */
    private function add_items_link($itemid1, $itemid2)
    {
        global $kernel;
        $query = 'REPLACE INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items_links` (`itemid1`,`itemid2`) '.
            ' VALUES ('.$itemid1.','.$itemid2.')';
        $kernel->runSQL($query);
    }

    /**
     * Импортирует весь CSV-файл
     *
     * @param integer $group_id id-шник тов. группы
     * @param string $file имя CSV файла
     * @param string $separator разделитель полей
     * @param integer $cat_id       id-шник категории
     * @param integer $cat_id4new   id-шник категории для новых товаров
     * @return void
     */
    private function make_csv_import($group_id, $file, $separator, $cat_id, $cat_id4new)
    {
        global $kernel;
        $csv_items = $this->parse_csv_file($file, $separator);

        if (count($csv_items) == 0)
            return;

        //пропускаем первую строчку если указано
        $bypass_1st_line = $kernel->pub_httppost_get('bypass_1st_line');
        if (!empty($bypass_1st_line))
        {
            array_shift($csv_items);
            if (count($csv_items) == 0)
                return;
        }
        $main_prop = $this->get_common_main_prop();


        $order = 0;
        if ($cat_id > 0)
            $order = $this->get_next_order_in_cat($cat_id);

        $order4newcat = 0;
        if ($cat_id4new > 0)
            $order4newcat = $this->get_next_order_in_cat($cat_id4new);


        $count_columns = count($csv_items[0]); //кол-во столбцов
        $uniqs = $kernel->pub_httppost_get('uniq');
        if (!is_array($uniqs))
            $uniqs = array();
        $cfields = $kernel->pub_httppost_get('elem'); //поля для столбцов
        $group = CatalogCommons::get_group($group_id);
        $group_props = CatalogCommons::get_props2($group_id);
        $common_props = CatalogCommons::get_props2(0);

        //все товары из группы, в которую готовим импорт (сначала только common-поля)
        $group_items1 = $this->get_items(0, 0, $group_id, false);
        $group_items = array();
        foreach ($group_items1 as $gi)
        {
            //теперь занесём в этот массив полную инфу о товарах
            $commonid = $gi['id'];
            unset($gi['id']);
            $gi['common_id'] = $commonid;
            $igf = $this->get_item_group_fields($gi['ext_id'], $group['name_db']);
            $newitem = $gi + $igf;
            $group_items[] = $newitem;
        }

        foreach ($csv_items as $csv_item)
        {
            $common_fields = array(); // значения общих свойств
            $group_fields = array(); // значения свойств группы
            $uniq_fields = array(); //уникальные столбцы
            $linked_ids = array();
            for ($i = 0; $i < $count_columns; $i++)
            { //цикл по столбцам
                if ($cfields[$i] == '') //значит столбец игнорируем (выбрано "Игнорировать")
                    continue;
                if ($cfields[$i] == '__linked__')
                { //поле для связей товаров
                    $lseparator = $kernel->pub_httppost_get("separator_".$i);
                    if (!empty($lseparator))
                    { //обрабатываем только если разделитель не пустой
                        $lvals = explode($lseparator, $csv_item[$i]);
                        foreach ($lvals as $lval)
                        {
                            $lval = trim($lval);
                            if (empty($lval))
                                continue;
                            $litem = $this->get_item_by_prop($main_prop, $lval);
                            if ($litem)
                                $linked_ids[] = $litem['id'];
                        }
                    }
                    continue;
                }
                if (mb_strpos($cfields[$i], 'group0_') === false)
                { //свойство тов. группы
                    $fname = $cfields[$i];
                    if (array_key_exists($fname, $group_fields))
                    { //значит значения надо "склеить"
                        $group_fields[$fname] .= ' '.$csv_item[$i];
                    }
                    else
                        $group_fields[$fname] = $csv_item[$i];
                }
                else
                { //значит выбрано common-свойство
                    $fname = mb_substr($cfields[$i], 7);
                    if (array_key_exists($fname, $common_fields))
                    { //значит значения надо "склеить"
                        $common_fields[$fname] .= ' '.$csv_item[$i];
                    }
                    else
                        $common_fields[$fname] = $csv_item[$i];
                }
                if (array_key_exists($i, $uniqs)) //значит это поле-уникальное
                    $uniq_fields[$fname] = $csv_item[$i];

            }

            $found_item_id = $this->get_itemid_in_group($uniq_fields, $group_items);
            if ($found_item_id > 0)
            { //update
                //сначала обновим таблицу товаров группы, если надо
                if (count($group_fields) != 0)
                {
                    $uitem = $this->get_item($found_item_id);
                    $query = 'UPDATE '.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']).' SET ';
                    $grfields_keys = array_keys($group_fields);
                    for ($j = 0; $j < count($grfields_keys); $j++)
                    {
                        $grfields_key = $grfields_keys[$j];
                        $query .= '`'.$grfields_key.'`='.$this->prepare_property_value2($group_fields[$grfields_key], $group_props[$grfields_key]['type']);
                        if ($j != (count($grfields_keys) - 1))
                            $query .= ', ';
                    }
                    $query .= ' WHERE `id`='.$uitem['ext_id'];
                    $kernel->runSQL($query);
                }


                // теперь общую таблицу товаров если надо
                if (count($common_fields) != 0)
                {
                    $query = 'UPDATE '.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items SET ';
                    $comfields_keys = array_keys($common_fields);
                    for ($j = 0; $j < count($comfields_keys); $j++)
                    {
                        $comfields_key = $comfields_keys[$j];
                        $query .= '`'.$comfields_key.'`='.$this->prepare_property_value2($common_fields[$comfields_key], $common_props[$comfields_key]['type']);
                        if ($j != (count($comfields_keys) - 1))
                            $query .= ', ';
                    }
                    $query .= ' WHERE `id`='.$found_item_id;
                    $kernel->runSQL($query);
                }

                foreach ($linked_ids as $linked_id)
                    $this->add_items_link($linked_id, $found_item_id);

            }
            else
            { //insert
                //сначала добавим в таблицу товаров группы
                $query = 'INSERT INTO '.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']).' ';
                if (count($group_fields) == 0)
                    $query .= '(`id`) VALUES (NULL)';
                else
                {
                    $grfields_keys = array_keys($group_fields);
                    $query .= '(`'.implode('`,`', $grfields_keys).'`, `id`) VALUES (';
                    foreach ($grfields_keys as $grfields_key)
                    {
                        //$query .= '`'.$grfields_key.'`='.$this->prepare_property_value2($group_fields[$grfields_key],$group_props[$grfields_key]['type']).',';
                        $query .= $this->prepare_property_value2($group_fields[$grfields_key], $group_props[$grfields_key]['type']).',';
                    }
                    $query .= 'NULL)';
                }
                $kernel->runSQL($query);
                $ext_id = mysql_insert_id();


                $is_available = 1;
                // теперь в общую таблицу товаров
                $query = 'INSERT INTO '.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items';
                if (count($common_fields) == 0)
                    $query .= '(`group_id`,`ext_id`,`available`) VALUES ('.$group_id.','.$ext_id.','.$is_available.')';
                else
                {
                    $comfields_keys = array_keys($common_fields);
                    $query .= '(`'.implode('`,`', $comfields_keys).'`, `group_id`,`ext_id`,`available`) VALUES (';
                    foreach ($comfields_keys as $comfields_key)
                    {
                        $query .= $this->prepare_property_value2($common_fields[$comfields_key], $common_props[$comfields_key]['type']).',';
                    }
                    $query .= $group_id.', '.$ext_id.', '.$is_available.')';
                }
                $kernel->runSQL($query);
                $comm_id = mysql_insert_id();

                foreach ($linked_ids as $linked_id)
                {
                    $this->add_items_link($linked_id, $comm_id);
                }

                if ($cat_id > 0)
                { //добавляем в категорию
                    $query = "INSERT INTO `".$kernel->pub_prefix_get()."_catalog_".$kernel->pub_module_id_get()."_item2cat` ".
                        "(`cat_id`,`item_id`,`order`) VALUES ".
                        "(".$cat_id.", ".$comm_id.", ".$order.")";
                    $kernel->runSQL($query);
                    $order += $this->order_inc;
                }

                if ($cat_id4new > 0)
                { //если указана категория для новых, то добавим и в неё
                    $query = "INSERT INTO `".$kernel->pub_prefix_get()."_catalog_".$kernel->pub_module_id_get()."_item2cat` ".
                        "(`cat_id`,`item_id`,`order`) VALUES ".
                        "(".$cat_id4new.", ".$comm_id.", ".$order4newcat.")";
                    $kernel->runSQL($query);
                    $order4newcat += $this->order_inc;
                }
            }
        }
        @unlink($file);
        //@unlink('content/files/'.$kernel->pub_module_id_get().'/'.$file);
    }

    /**
     * Проверяет по уникальным полям, присутствует ли товар в массиве товаров группы
     *
     * @param array $uniq_fields массив назв.поля=>значение, по которым проверяем наличие
     * @param array $gitems массив товаров товарной группы
     * @return integer 0, если товар не найден в массиве, иначе его common-id
     */
    private function get_itemid_in_group($uniq_fields, $gitems)
    {
        if (count($uniq_fields) == 0 || count($gitems) == 0)
            return 0;
        $ret = 0;
        $uniq_keys = array_keys($uniq_fields);
        foreach ($gitems as $gitem)
        {
            //проходим по всем уникальным ключам
            $is_fields_eq = true;
            foreach ($uniq_keys as $uniq_key)
            {
                if ($uniq_fields[$uniq_key] != $gitem[$uniq_key])
                {
                    $is_fields_eq = false;
                    break;
                }
            }
            if ($is_fields_eq)
            {
                $ret = $gitem['common_id'];
                break;
            }
        }

        return $ret;
    }

    /**
     * Показывает таблицу импорта из CSV-файла в админке
     *
     * @param integer $group_id id-щник тов. группы для импорта
     * @param file   $file tmp-имя uploaded-файла
     * @param string $separator разделитель
     * @return string
     */
    private function show_import_csv_table($group_id, $file, $separator)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'import_csv2.html'));
        $content = $this->get_template_block("table_header");
        $items = $this->parse_csv_file($file, $separator, 10); //показываем первые 10 товаров
        $elems = count($items[0]);
        $props = CatalogCommons::get_props($group_id, true);
        $props_select = $this->get_template_block("prop_option");
        $props_select = str_replace('%prop_name%', '', $props_select);
        $props_select = str_replace('%prop_name_full%', '[#catalog_import_ignore_column_label#]', $props_select);
        $props_select = $kernel->pub_page_textlabel_replace($props_select);
        foreach ($props as $prop)
        {
            if ($prop['type'] == 'file' || $prop['type'] == 'pict')
                continue; //эти типы не импортируются в любом случае
            $prop_select = $this->get_template_block("prop_option");
            $opt_name = $prop['name_db'];
            if ($prop['group_id'] == 0)
                $opt_name = 'group0_'.$opt_name;
            $prop_select = str_replace('%prop_name%', $opt_name, $prop_select);
            $prop_select = str_replace('%prop_name_full%', $prop['name_full'], $prop_select);
            $props_select .= $prop_select;
        }

        $main_prop = $this->get_common_main_prop();
        if ($main_prop)
        { //связать с другими товарами можно только если есть "главное" свойство
            $prop_select = $this->get_template_block("prop_option");
            $prop_select = str_replace('%prop_name%', '__linked__', $prop_select);
            $prop_select = str_replace('%prop_name_full%', $kernel->pub_page_textlabel_replace('[#catalog_import_linked_col#]'), $prop_select);
            $props_select .= $prop_select;
        }

        $theads = '';
        for ($i = 0; $i < $elems; $i++)
        {
            $thead = $this->get_template_block('thead');
            $thead = str_replace('%prop%', 'elem['.$i.']', $thead);
            $thead = str_replace('%cb%', 'uniq['.$i.']', $thead);
            $thead = str_replace('%id%', $i, $thead);
            $thead = str_replace('%props%', $props_select, $thead);
            $theads .= $thead;
        }

        $content = str_replace('%theads%', $theads, $content);

        $tlines = '';
        foreach ($items as $item_line)
        {
            $tline = $this->get_template_block('tline');
            $tline = str_replace('%cols%', implode('</td><td>', $item_line), $tline);
            $tlines .= $tline;
        }
        $content .= $tlines;
        $content .= $this->get_template_block('table_footer');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('import_csv3'), $content);
        return $content;
    }

    /**
     * Парсит загруженный CSV-файл в админке в массив
     *
     * @param string  $file имя файла
     * @param string  $separator разделитель полей
     * @param integer $limit макс. кол-во обрабатываемых строк
     * @return array
     */
    private function parse_csv_file($file, $separator, $limit = null)
    {
        $fc = file_get_contents($file);
        $lines = explode("\n", $fc);

        $res = array();
        $max_els = 0;
        foreach ($lines as $line)
        { //сначала найдём макс. кол-во эл-тов в строке
            $lc = $this->get_real_elements_from_line($line, $separator);
            if (count($lc) > $max_els)
                $max_els = count($lc);
        }
        $curr = 0;
        foreach ($lines as $line)
        { //теперь будем "запоминать" только строки с макс. кол-вом элементов,
            //остальные - игнорировать
            $lc = $this->get_real_elements_from_line($line, $separator);
            if (count($lc) == $max_els)
            {
                $res[] = $lc;
                $curr++;
            }
            if (!is_null($limit) && $limit == $curr)
                break;
        }
        return $res;
    }

    /**
     * Возвращает непустые элементы из строки, разделённые символом $separator
     *
     * @param string $line      строка
     * @param string $separator разделитель элементов
     * @return array
     */
    private function get_real_elements_from_line($line, $separator)
    {
        $res = array();
        $elems = explode($separator, $line);
        foreach ($elems as $elem)
        {
            $elem = trim($elem);
            if (mb_strlen($elem) == 0)
                continue;
            $firstChar = mb_substr($elem, 0, 1);
            $lastChar = mb_substr($elem, -1);
            if (($firstChar == "'" && $lastChar == "'") || ($firstChar == '"' && $lastChar == '"'))
                $elem = mb_substr($elem, 1, mb_strlen($elem) - 2);
            $res[] = $elem;
        }
        return $res;
    }

    /**
     * Показывает форму импорта из CSV-файла в админке
     *
     * @return string
     */
    private function show_import_csv_form()
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'import_csv.html'));
        $content = $this->get_template_block('import_form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('import_csv2'), $content);

        $groups = CatalogCommons::get_groups();
        $gblock = '';
        foreach ($groups as $group)
        {
            $gblock .= $this->get_template_block('group_item');
            $gblock = str_replace('%group_id%', $group['id'], $gblock);
            $gblock = str_replace('%group_name%', htmlspecialchars($group['name_full']), $gblock);
        }
        $content = str_replace('%groups%', $gblock, $content);

        $cats = $this->get_child_categories(0, 0, array());
        $options = '';
        $cat_shift = $this->get_template_block('cat_shift');
        foreach ($cats as $cat)
        {
            $option = $this->get_template_block('cat_option');
            $option = str_replace('%cat_id%', $cat['id'], $option);
            $option = str_replace('%cat_name%', str_repeat($cat_shift, $cat['depth']).$cat['name'], $option);
            $options .= $option;
        }
        $content = str_replace('%cats_options%', $options, $content);

        return $content;
    }

    /**
     * Добавляет категории в дорогу
     * @param array $cats_way_elems категории
     * @return void
     */
    private function add_categories2waysite($cats_way_elems)
    {
        global $kernel;
        if ($this->is_way_set)
            return;
        $way_cat_tpl = $kernel->pub_modul_properties_get("catalog_property_way_cay_tpl", $kernel->pub_module_id_get());
        if (empty($way_cat_tpl) || !isset($way_cat_tpl['value']) || empty($way_cat_tpl['value']))
            $way_cat_tpl = false;
        else
            $way_cat_tpl = $way_cat_tpl['value'];
        if ($way_cat_tpl)
        {
            foreach ($cats_way_elems as $cwe)
            {
                if ($cwe['id'] == 0)
                    continue;
                if ($cwe['_hide_from_waysite'] == 1)
                    continue;
                $cat_label = $way_cat_tpl;
                foreach ($cwe as $cpname => $cpval)
                {
                    $cat_label = str_replace("%".$cpname."%", $cpval, $cat_label);
                }
                $kernel->pub_waysite_set(array('caption' => $cat_label,
                        'url' => '/'.$kernel->pub_page_current_get().'.html?'.$this->frontend_param_cat_id_name.'='.$cwe['id'])
                );
            }
        }
        $this->is_way_set = true;
    }

    /**
     * Отображает полную информацию о товаре
     *
     * @param integer $itemid id-шник товара
     * @return string
     */
    public function pub_catalog_show_item_details($itemid)
    {
        global $kernel;
        //Прежде всего инфу по товару
        $itemid = intval($itemid);
        $idata = $this->get_item_full_data($itemid);
        if (!$idata)
            frontoffice_manager::throw_404_error();

        //Узнаем его группу, что бы взять шаблон
        $group = CatalogCommons::get_group($idata['group_id']);
        if (empty($group['template_items_one']))
            return "У товарной группы не определён шаблон вывода карточки товаров";
        $tpl = CatalogCommons::get_templates_user_prefix().$group['template_items_one'];
        $this->set_templates($kernel->pub_template_parse($tpl));
        //Шаблон карточки
        $block = $this->get_template_block('item');
        $props = CatalogCommons::get_props($idata['group_id'], true);

        //Теперь ищем переменные, свойств и заменяем их
        $block = $this->process_item_props_out($idata, $props, $block, $group);
        $moduleid = $kernel->pub_module_id_get();
        $catid = $this->get_current_catid(true);
        if ($catid == 0)
        { // если не знаем точно текущую категорию,
            //находим кратчайшую дорогу в категориях к этому товару
            $max_way = $this->get_max_catway2item($itemid);
            if (count($max_way) > 0)
            {
                $catid = $max_way[count($max_way) - 1]['id'];
                $this->current_cat_IDs[$moduleid] = $catid;
            }
        }
        else
        {
            $max_way = $this->get_way2cat($catid, true);
            $this->current_cat_IDs[$moduleid] = $catid;
        }

        $cway = array();

        $this->add_categories2waysite($max_way);
        foreach ($max_way as $cat)
        {
            if ($cat['id'] == 0)
                continue;
            if ($cat['id'] == $catid)
                $cwblock = $this->get_template_block('cat_way_active');
            else
                $cwblock = $this->get_template_block('cat_way_passive');
            $cwblock = str_replace('%cat_name%', $cat['name'], $cwblock);
            $cwblock = str_replace('%cat_link%', $kernel->pub_page_current_get().'.html?'.$this->frontend_param_cat_id_name.'='.$cat['id'], $cwblock);
            $cway[] = $cwblock;
        }

        //+сам товар в дорогу, если у нас есть шаблон
        $way_item_tpl = $kernel->pub_modul_properties_get("catalog_property_way_item_tpl", $moduleid);
        if (empty($way_item_tpl) || !isset($way_item_tpl['value']) || empty($way_item_tpl['value']))
            $way_item_tpl = false;
        else
            $way_item_tpl = $way_item_tpl['value'];
        if ($way_item_tpl)
        {

            $item_label = $way_item_tpl;
            foreach ($idata as $ipname => $ipval)
            {
                $item_label = str_replace("%".$ipname."%", $ipval, $item_label);
            }
            $kernel->pub_waysite_set(array('caption' => $item_label,
                    'url' => '/'.$kernel->pub_page_current_get().'.html?'.$this->frontend_param_item_id_name.'='.$itemid)
            );
        }

        $cway_block = $this->get_template_block('cat_way_block');
        $cway_block = str_replace('%cat_way%', implode($this->get_template_block('cat_way_separator'), $cway), $cway_block);

        $block = str_replace('%cat_way_block%', $cway_block, $block);

        $last_cat_block = "";
        if (isset($_COOKIE[$moduleid.'_last_catid']))
        {
            $lastcat = $this->get_category(intval($_COOKIE[$moduleid.'_last_catid']));
            if ($lastcat)
            {
                $last_cat_block = $this->get_template_block('last_cat_block');
                foreach ($lastcat as $lk => $lv)
                {
                    $last_cat_block = str_replace("%".$lk."%", $lv, $last_cat_block);
                }
            }
        }
        $block = str_replace('%last_cat_block%', $last_cat_block, $block);
        $block = $this->process_filters_in_template($block);
        $block = $this->process_variables_out($block);
        $block = $this->replace_current_page_url($block);
        //очистим оставшиеся метки
        $block = $this->clear_left_labels($block);
        return $block;
    }

    /**
     * Обрабатывает вызовы внутренних фильтров в шаблоне
     *
     * @param string $content
     * @param string $ignored stringID фильтра, который игнорируем (базовая защита от рекурсии)
     * @return string
     */
    private function process_filters_in_template($content, $ignored = null)
    {
        //обработаем фильтры, если они есть в шаблоне
        if (preg_match_all("/%show_selection_([a-z0-9_-]+)%/iU", $content, $matches))
        { //тип 1: %show_selection_NAME%
            foreach ($matches[1] as $filterStringID)
            {
                if ($filterStringID == $ignored)
                    $replacement = '';
                else
                    $replacement = $this->pub_catalog_show_inner_selection_results($filterStringID);
                $content = str_ireplace("%show_selection_".$filterStringID."%", $replacement, $content);
            }
        }

        if (preg_match_all("/%show_selection_([a-z0-9_-]+)\\((.+)\\)%/iU", $content, $matches, PREG_SET_ORDER))
        { //тип 2 (с параметрами) : %show_selection_NAME(param1=value1;param2=value2)%
            foreach ($matches as $match)
            {
                $filterStringID = $match[1];
                if ($filterStringID == $ignored)
                    $replacement = '';
                else
                {
                    $paramsStr = trim($match[2]);
                    //remove any %NNNN_value%
                    $paramsStr = preg_replace("/%([a-z0-9_-]+)_value%/i", "", $paramsStr);
                    $params = explode(";", $paramsStr);
                    $paramsKV = array();

                    foreach ($params as $pstr)
                    {
                        list($pname, $pvalue) = explode("=", $pstr, 2);
                        $paramsKV[trim($pname)] = trim($pvalue);
                    }
                    $replacement = $this->pub_catalog_show_inner_selection_results($filterStringID, false, $paramsKV);
                }
                $content = str_ireplace("%show_selection_".$filterStringID."(".$match[2].")%", $replacement, $content);
            }
        }
        return $content;
    }

    /**
     * Возвращает товары из группы по айдишникам
     *
     * @param string $group_name имя товарной группы
     * @param array  $itemids    id-шники товаров, которые нас интересуют
     * @return array
     */
    private function get_group_items($group_name, $itemids)
    {
        global $kernel;
        $query = "SELECT * FROM `".$kernel->pub_prefix_get()."_catalog_items_".$kernel->pub_module_id_get()."_".strtolower($group_name)."` ".
            "WHERE `id` IN (".implode(',', $itemids).")";
        $result = $kernel->runSQL($query);
        $items = array();
        while ($row = mysql_fetch_assoc($result))
            $items[$row['id']] = $row;
        mysql_free_result($result);
        return $items;
    }

    /**
     * Публичный метод для отображения и обработки формы оформления заказа
     *
     * @param $template string HTML-шаблон формы
     * @param $manager_mail_tpl string шаблон письма менеджеру
     * @param $manager_mail_subj string тема письма менеджеру
     * @param $manager_email string Email менеджера
     * @param $user_mail_tpl string шаблон письма пользователю
     * @param $user_mail_subj string тема письма пользователю
     *
     * @return string
     */
    public function pub_catalog_show_basket_order_form($template, $manager_mail_tpl, $manager_mail_subj, $manager_email, $user_mail_tpl, $user_mail_subj)
    {
        global $kernel;
        if (empty($template) || !file_exists($template))
            return "template not found.";
        $this->basket_init();
        $parsed_tpl = $kernel->pub_template_parse($template);
        $this->set_templates($parsed_tpl);

        $order_received = $kernel->pub_httpget_get("order_received");
        if ($order_received == "true")
        {
            $block = $this->get_template_block("order_received");
            setcookie($this->basketid_cookie_name, "", time() - $this->basketid_cookie_days * 24 * 60 * 60);
            return $block;
        }
        $bitems = $this->get_basket_items();
        if (count($bitems) == 0)
            return $this->get_template_block("no_basket_items");

        $ofields = CatalogCommons::get_order_fields2();
        $content = "";
        $userid = intval($kernel->pub_user_is_registred());
        $process_order = $kernel->pub_httppost_get("process_order");
        if (!empty($process_order))
        { //обрабатываем форму
            $form_ok = true;
            $user_email = false;

            $fvalues = array();
            $fvalues_orig = array();
            foreach ($ofields as $db_field => $ofield)
            {
                $postvar = nl2br(htmlspecialchars($kernel->pub_httppost_get($db_field, false)));
                $fvalues_orig[$db_field] = $postvar;
                $fvalues[$db_field] = nl2br(htmlspecialchars($kernel->pub_httppost_get($db_field))); //сохраним сразу и заэскейпленное значение
                if ($kernel->pub_is_valid_email($postvar))
                    $user_email = $postvar;

                if (empty($postvar) && $ofield['isrequired'] == 1)
                { // если поле не заполнено, но помечено как обязательное
                    $msg = $this->get_template_block("required_field_not_filled");
                    $msg = str_replace("%field_name%", $ofield['name_full'], $msg);
                    $content .= $msg;
                    $form_ok = false;
                    break;
                }
                elseif ($postvar && !empty($ofield['regexp']) && !preg_match($ofield['regexp'], $postvar))
                {
                    $msg = $this->get_template_block("incorrect_field_value");
                    $msg = str_replace("%field_name%", $ofield['name_full'], $msg);
                    $content .= $msg;
                    $form_ok = false;
                    break;
                }
            }

            //if (!$user_email)
            //    $content .= $this->get_template_block("no_email_error");
            //else
            if ($form_ok)
            { //если всё ок - делаем ретурн здесь, не выводим хтмл-форму

                //$block = $this->get_template_block("order_received");
                $currOrder = $this->get_current_basket_order();

                //номер заказа в сабже
                $manager_mail_subj = str_replace("%orderid%", $currOrder['id'], $manager_mail_subj);
                $user_mail_subj = str_replace("%orderid%", $currOrder['id'], $user_mail_subj);

                $fvalues_orig['id'] = $fvalues['id'] = $currOrder['id'];

                $from_email = $manager_email;

                //письмо менеджеру
                $msg_body = $this->process_basket_items_tpl($manager_mail_tpl, $bitems, $fvalues_orig);
                $kernel->pub_mail(array($manager_email), array($manager_email), $from_email, 'robot', $manager_mail_subj, $msg_body, false, "", "", $user_email);


                //письмо юзеру
                if ($user_email)
                {
                    $msg_body = $this->process_basket_items_tpl($user_mail_tpl, $bitems, $fvalues_orig);
                    $kernel->pub_mail(array($user_email), array($user_email), $from_email, 'robot', $user_mail_subj, $msg_body, false, "", "", $manager_email);
                }

                //обновляем запись в БД
                $updateSQL = "UPDATE `".$kernel->pub_prefix_get()."_catalog_".$kernel->pub_module_id_get()."_basket_orders` SET ";
                foreach ($fvalues as $fk => $fv)
                {
                    $updateSQL .= "`".$fk."`='".$fv."', ";
                }
                $updateSQL .= " `userid`= ".$userid.",";
                $totalprice = floatval($this->convert_basket_sum_strings("%field_total[price]%", true));
                $updateSQL .= "`totalprice`=".$totalprice.", ";
                $updateSQL .= " `text`='".mysql_real_escape_string($this->process_basket_items_tpl(CatalogCommons::get_templates_admin_prefix()."_order_info_db.html", $bitems, $fvalues))."',";

                $updateSQL .= " `lastaccess`= '".date("Y-m-d H:i:s")."' WHERE `id`='".$currOrder['id']."'";
                $kernel->runSQL($updateSQL);

                //чистим корзину
                $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_items` WHERE `orderid`='.$currOrder['id'];
                $kernel->runSQL($query);

                $kernel->pub_redirect_refresh_global("/".$kernel->pub_page_current_get().".html?order_received=true");
            }
        }

        $form = $this->get_template_block("form");
        $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_basket_orders');
        //заполним ранее введённые поля
        foreach ($ofields as $db_field => $ofield)
        {
            $postvar = $kernel->pub_httppost_get($db_field, false);
            if ($ofield['type'] == 'enum')
            {
                $tstr = $tinfo[$db_field]['Type'];
                $enum_elems = explode(',', mb_substr($tstr, 5, -1));
                $form = str_replace("%".$db_field."_".$postvar."_selected%", "selected", $form);
                foreach ($enum_elems as $enum_elem)
                {
                    $enum_elem = mb_substr($enum_elem, 1, mb_strlen($enum_elem) - 2);
                    $form = str_replace("%".$db_field."_".$enum_elem."_selected%", " ", $form);
                }
            }
            else
                $form = str_replace("%".$db_field."_value%", htmlspecialchars($postvar), $form);
        }
        $form = $this->process_variables_out($form);
        //очистим оставшиеся метки в шаблоне
        $form = $this->clear_left_labels($form);

        $content .= $form;
        return $content;

    }


    /**
     * Строит HTML со списком товаров корзины на основании шаблона
     * используется в pub_catalog_show_basket_items и в письмах менеджеру и пользователю
     * при обработке заказа
     *
     * @param string $template шаблон
     * @param array $basket_items товары корзины
     * @param array $order_fields набор заполненных полей key=>value для заказа
     *
     * @return string
     */
    private function process_basket_items_tpl($template, $basket_items, $order_fields = array())
    {
        global $kernel;
        if (empty($template) || !file_exists($template))
            return "template not found.";
        $this->set_templates($kernel->pub_template_parse($template));

        if (count($basket_items) == 0)
            return $this->get_template_block('list_null');

        //Получаем общие свойства.
        //при этом, надо пройтись по свойствам и если там есть
        //картинки, то нужно продублировать их свойствами большого
        //и маленького изображения
        $props = CatalogCommons::get_props(0, false);
        if (isset($props['add_param']))
            $props['add_param'] = unserialize($props['add_param']);

        //Сформируем сначала строки с товарами
        $rows = '';
        $curr = 1;
        $matches = false;
        $groups = CatalogCommons::get_groups();
        foreach ($basket_items as $basketitem)
        {
            $item = $basketitem["item"];
            if ($curr % 2 == 0) //строка - чётная
                $odd_even = "even";
            else
                $odd_even = "odd";

            //Взяли блок строчки
            $block = $this->get_template_block('row_'.$odd_even);
            if (empty($block))
                $block = $this->get_template_block('row');

            $block = str_replace("%odd_even%", $odd_even, $block);
            $block = str_replace("%items_qty%", $basketitem["qty"], $block);

            if (preg_match_all("/\\%field_sum\\[([a-z0-9_-]+)\\]\\%/iU", $block, $matches))
            {
                foreach ($matches[1] as $sum_field)
                {
                    $is_zero_price = false;
                    $sum = "";
                    if (isset($item[$sum_field]) && is_numeric($item[$sum_field]))
                    {
                        if (intval($item[$sum_field]) == 0)
                            $is_zero_price = true;
                        else
                            $sum = $basketitem['qty'] * $item[$sum_field];
                    }
                    else
                        $is_zero_price = true;
                    if ($is_zero_price)
                        $block = str_ireplace("%field_sum[".$sum_field."]%", $this->get_template_block('zero_price_label'), $block);
                    else
                        $block = str_ireplace("%field_sum[".$sum_field."]%", $sum, $block);
                }
            }

            //Теперь ищем переменные свойств и заменяем их
            $block = $this->process_item_props_out($item, $props, $block, $groups[$basketitem['item']['group_id']]);
            $block .= $this->get_template_block('row_delimeter');

            $rows .= $block;
            $curr++;
        }
        $content = $this->get_template_block('list');
        $content = str_replace("%row%", $rows, $content);
        $content = $this->convert_basket_sum_strings($content);

        if ($order_fields)
        {
            foreach ($order_fields as $ofield => $ovalue)
            {
                $content = str_replace("%".$ofield."_value%", $ovalue, $content);
            }
        }


        $last_cat_block = "";
        if (isset($_COOKIE[$kernel->pub_module_id_get().'_last_catid']))
        {
            $lastcat = $this->get_category(intval($_COOKIE[$kernel->pub_module_id_get().'_last_catid']));
            if ($lastcat)
            {
                $last_cat_block = $this->get_template_block('last_cat_block');
                foreach ($lastcat as $lk => $lv)
                {
                    $last_cat_block = str_replace("%".$lk."%", $lv, $last_cat_block);
                }
            }
        }
        $content = str_replace('%last_cat_block%', $last_cat_block, $content);

        $content = $this->replace_current_page_url($content);
        $content = $this->process_variables_out($content);
        //очистим оставшиеся метки
        $content = $this->clear_left_labels($content);

        return $content;
    }

    /**
     * Публичный метод для отображения списка товаров в корзине
     *
     * @param $template string шаблон
     *
     * @return string
     */
    public function pub_catalog_show_basket_items($template)
    {
        $this->basket_init();
        return $this->process_basket_items_tpl($template, $this->get_basket_items());
    }

    /**
     * Публичный метод для отображения стикера корзины
     *
     * @param string $empty_tpl шаблон для пустой корзины
     * @param string $not_empty_tpl шаблон для корзины с товарами
     *
     * @return string
     */
    public function pub_catalog_show_basket_label($empty_tpl, $not_empty_tpl)
    {
        $this->basket_init();
        //$order = $this->get_basket_order_by_sid($this->get_current_basket_sessionid());
        //$basketsid = $this->get_current_basket_sessionid();
        $bitems = $this->get_basket_items();
        if (count($bitems) == 0)
            $template = $empty_tpl;
        else
            $template = $not_empty_tpl;
        $template = file_get_contents($template);
        $template = $this->convert_basket_sum_strings($template);
        $template = $this->process_variables_out($template);
        //очистим оставшиеся метки
        $template = $this->clear_left_labels($template);

        return $template;
    }

    /**
     * Подсчитывает кол-во для корзины с учётом кол-ва товаров в корзине
     * @param string $column столбец, по которому считать
     * @param boolean $ignore_zero_price игнорировать приставку для товаров с нулевой ценой?
     * @return integer
     */
    private function get_basket_column_sum($column, $ignore_zero_price = false)
    {
        global $kernel;
        $total = 0;
        $bitems = $this->get_basket_items();
        $is_zero_price_found = false;
        foreach ($bitems as $bitem)
        {
            $item = $bitem['item'];
            if (isset($item[$column]) && is_numeric($item[$column]) && intval($item[$column]) > 0)
                $total += $bitem['qty'] * $item[$column];
            else
                $is_zero_price_found = true;
        }
        $ret = $this->cleanup_number($total);
        if ($is_zero_price_found && !$ignore_zero_price)
            $ret = $ret.$kernel->pub_page_textlabel_replace("[#catalog_basket_plus_zero_price_label#]");
        return $ret;
    }

    /**
     * Конвертирует суммы столбцов в корзине товаров
     * меняет строки вида field_total[поле] на сумму по всем товарам с учётом кол-ва
     * +меняет строку %total_basket_items% на кол-во товаров в корзине
     *
     * @param string $text
     * @param boolean $ignore_zero_price игнорировать приставку для товаров с нулевой ценой?
     * @return string
     */
    private function convert_basket_sum_strings($text, $ignore_zero_price = false)
    {
        $text = str_replace("%total_basket_items%", count($this->get_basket_items()), $text);
        $matches = false;
        if (!preg_match_all("/\\%field_total\\[([a-z0-9_-]+)\\]\\%/iU", $text, $matches))
            return $text;
        foreach ($matches[1] as $match)
        {
            $val = $this->get_basket_column_sum($match, $ignore_zero_price);
            $text = str_ireplace("%field_total[".$match."]%", $val, $text);
        }
        return $text;
    }

    /**
     * Убирает товар из корзины
     *
     * @param integer $itemid ID-шник товара
     * @return void
     */
    private function remove_item_from_basket($itemid)
    {
        global $kernel;
        $currOrder = $this->get_current_basket_order();
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_items` '.
            'WHERE `itemid` = '.intval($itemid)." AND `orderid`='".$currOrder['id']."'";
        $kernel->runSQL($query);
        $this->update_basket_lastaccess();
    }

    /**
     * Обновляет кол-во товара в корзине
     *
     * @param integer $itemid ID-шник товара
     * @param integer $newqty новое кол-во
     * @return void
     */
    private function update_basket_item_qty($itemid, $newqty)
    {
        $currOrder = $this->get_current_basket_order();
        $newqty = abs(intval($newqty));
        if ($newqty == 0)
        {
            $this->remove_item_from_basket($itemid);
            return;
        }
        global $kernel;
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_items` '.
            'SET `qty`='.$newqty.' '.
            'WHERE `itemid` = '.intval($itemid)." AND `orderid`='".$currOrder['id']."'";
        $kernel->runSQL($query);
        $this->update_basket_lastaccess();
    }

    /**
     * Обновляет время последнего изменения в корзине
     * @return void
     */
    private function update_basket_lastaccess()
    {
        global $kernel;
        $currOrder = $this->get_current_basket_order();
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_orders` '.
            'SET `lastaccess`="'.date("Y-m-d H:i:s").'" '.
            'WHERE `id`='.$currOrder['id'];
        $kernel->runSQL($query);
    }

    /**
     * Возвращает запись о заказе товара из БД по ID-шнику сессии
     * @param string $sessionid ID-шник сессии
     * @return array
     */
    private function get_basket_order_by_sid($sessionid)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_basket_orders', '`sessionid` = "'.mysql_real_escape_string($sessionid).'"', "*");
    }

    /**
     * Добавляет товар в корзину
     *
     * @param integer $itemid ID-шник товара
     * @param integer $qty кол-во, которое надо добавить
     * @return void
     */
    private function add_basket_item($itemid, $qty = 1)
    {
        $qty = abs(intval($qty));
        //если товар с таким itemid уже есть в корзине
        //просто увеличим кол-во на $qty
        $basket_item = $this->get_basket_item_by_itemid($itemid);
        if ($basket_item)
        {
            $this->update_basket_item_qty($itemid, $basket_item['qty'] + $qty);
            return;
        }

        if ($qty == 0)
            return; //не добавляем нулевое кол-во
        global $kernel;
        $currOrder = $this->get_current_basket_order();
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_items` '.
            "(`orderid`, `itemid`, `qty`) VALUES (".$currOrder['id'].", ".intval($itemid).", ".$qty.")";
        $kernel->runSQL($query);
        $this->update_basket_lastaccess();
    }


    /**
     * Возвращает запись товара в корзине по itemid
     * @param integer $itemid ID-шник товара
     * @return array
     */
    private function get_basket_item_by_itemid($itemid)
    {
        global $kernel;
        $currOrder = $this->get_current_basket_order();
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_basket_items', '`orderid` = '.$currOrder['id'].' AND `itemid`='.intval($itemid));
    }

    /**
     * Добавляет заказ в БД, все поля пустые, кроме sessionid и lastaccess
     *
     * @param string $secret_session_id ID-шник сессии
     * @return string
     */
    private function add_basket_order($secret_session_id)
    {
        global $kernel;
        $query = 'REPLACE INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_orders` '.
            "(`sessionid`, `lastaccess`) VALUES ('".$secret_session_id."', NOW())";
        $kernel->runSQL($query);
        return mysql_insert_id();
    }

    /**
     * Возвращает текущие товары из корзины
     * если надо, загружает из БД (lazy loading)
     *
     * @return array
     */
    private function get_basket_items()
    {
        if ($this->current_basket_items)
            return $this->current_basket_items;
        $currOrder = $this->get_current_basket_order();
        $bitems = $this->get_basket_items_fromdb($currOrder['id']);
        $arr = array();
        foreach ($bitems as $bitem)
        {
            $bitem['item'] = $this->get_item_full_data($bitem['itemid']);
            $arr[] = $bitem;
        }
        $this->current_basket_items = $arr;
        return $this->current_basket_items;
    }

    /**
     * Возвращает массив с товарами корзины из БД
     * @param integer $orderid IDшник заказа
     * @return array
     */
    private function get_basket_items_fromdb($orderid)
    {
        global $kernel;
        return $kernel->db_get_list_simple('_catalog_'.$kernel->pub_module_id_get().'_basket_items', ' `orderid` = '.$orderid.' ORDER BY id DESC');
    }

    /**
     * Заменяет в шаблоне %current_page_url%
     * на урл текущей страницы c учётом ТОЛЬКО параметра cid (catid)
     *
     * @param string $content
     *
     * @return string
     */
    private function replace_current_page_url($content)
    {
        global $kernel;
        $url = $kernel->pub_page_current_get().".html?";
        if (isset($_REQUEST[$this->frontend_param_cat_id_name]))
            $url .= $this->frontend_param_cat_id_name."=".$_REQUEST[$this->frontend_param_cat_id_name]."&";
        if (isset($_REQUEST[$this->frontend_param_offset_name]))
            $url .= $this->frontend_param_offset_name."=".$_REQUEST[$this->frontend_param_offset_name]."&";
        if (isset($_REQUEST[$this->frontend_param_limit_name]))
            $url .= $this->frontend_param_limit_name."=".$_REQUEST[$this->frontend_param_limit_name]."&";
        if (isset($_REQUEST[$this->frontend_param_item_id_name]))
            $url .= $this->frontend_param_item_id_name."=".$_REQUEST[$this->frontend_param_item_id_name]."&";
        /*
        $qs = $_SERVER['QUERY_STRING'];
        if (empty($qs))
            $url .= "?";
        else
        {
            $url .= "?".$qs;
            if (substr($url, -1)!="&")
                $url .= "&";
        }
	*/
        return str_replace("%current_page_url%", $url, $content);
    }

    private function prepare_inner_filter_sql($sql, $params = array(), &$linkParams)
    {
        global $kernel;
        $matches = false;
        //param[param_name] меняем на строки из POST-запроса
        if (preg_match_all("/param\\[(.+)\\]/iU", $sql, $matches))
        {
            $allow_empty_params = false;
            if (mb_strpos($sql, 'REMOVE_NOT_SET') !== false)
                $allow_empty_params = true;
            foreach ($matches[1] as $param)
            {
                $is_in_request = false;
                if (isset($_REQUEST[$param]) && !empty($_REQUEST[$param]))
                    $is_in_request = true;
                $is_in_params = false;
                if (isset($params[$param]) && !empty($params[$param]))
                    $is_in_params = true;
                if ($is_in_request)
                {
                    if (is_array($_REQUEST[$param]))
                    { //например группа чекбоксов с именем name[]
                        if (count($_REQUEST[$param]) == 1 && !preg_match("/IN\\s+\\(([^(]*)(param".preg_quote("[".$param."]").")([^)]*)\\)/isU", $sql))
                        {
                            $value = $_REQUEST[$param][0];
                        }
                        else
                        {
                            $avalues = array();
                            foreach ($_REQUEST[$param] as $aparam)
                            {
                                $linkParams .= $param."[]=".urlencode($aparam)."&";
                                $avalues[] = "'".$kernel->pub_str_prepare_set($aparam)."'";
                            }
                            $value = implode(",", $avalues);
                        }
                    }
                    else
                    {
                        $linkParams .= $param."=".urlencode($_REQUEST[$param])."&";
                        $value = $kernel->pub_str_prepare_set($_REQUEST[$param]);
                    }
                }
                elseif ($is_in_params)
                {
                    $linkParams .= $param."=".urlencode($params[$param])."&";
                    $pval = $params[$param];
                    $firstChar = mb_substr($pval, 0, 1);
                    $lastChar = mb_substr($pval, -1);
                    if (($firstChar == '"' && $lastChar == '"') || ($firstChar == "'" && $lastChar == "'"))
                        $pval = mb_substr($pval, 1, mb_strlen($pval) - 2);
                    $value = $kernel->pub_str_prepare_set($pval);
                }
                else
                {
                    if ($allow_empty_params)
                        $value = "%PARAM_NOT_SET%";
                    else
                        return false;
                    //$this->get_template_block('list_null');

                }
                $sql = str_ireplace("param[".$param."]", $value, $sql);
            }
            //убираем REMOVE_NOT_SET[..%PARAM_NOT_SET%..] полностью
            //..а для оставшихся просто убираем наш спецпрефикс REMOVE_NOT_SET и оставляет то, что было внутри скобок
            $pattern = "/REMOVE_NOT_SET\\[(.+)\\]/sU";
            if (preg_match_all($pattern, $sql, $matches))
            {
                foreach ($matches[1] as $match)
                {
                    if (mb_strpos($match, '%PARAM_NOT_SET%') === false)
                        $sql = str_replace('REMOVE_NOT_SET['.$match.']', " ".$match." ", $sql);
                    else
                        $sql = str_replace('REMOVE_NOT_SET['.$match.']', " ", $sql);
                }
            }
        }
        return $sql;
    }


    /**
     * Публичный метод для отображения выборки по внутреннему фильтру
     *
     * @param string  $filter_stringid   строковый ID-шник внутреннего фильтра
     * @param boolean  $use_group_template   использовать шаблон тов. группы?
     * @param array  $params массив параметров
     * @param boolean  $need_postprocessing очищать оставшиеся метки  и выводить переменные?
     * @return string
     */
    public function pub_catalog_show_inner_selection_results($filter_stringid, $use_group_template = false, $params = array(), $need_postprocessing = true)
    {
        global $kernel;
        $filter = CatalogCommons::get_inner_filter_by_stringid($filter_stringid);
        if (!$filter)
            return "Inner filter '".$filter_stringid."' not found";
        if (empty($filter['groupid']))
            $group = false;
        else
        {
            $group = CatalogCommons::get_group(intval($filter['groupid']));
            if (!$group) //если мы не нашли товарную группу - запрос не получится
                return "ERROR";
        }
        if ($use_group_template && $group)
        {
            if (empty($group['template_items_list']))
                return "У товарной группы не определён шаблон вывода списка товаров";
            $tpl = CatalogCommons::get_templates_user_prefix().$group['template_items_list'];
            $linkParams = "filterid=".$filter_stringid."&";
        }
        else
        {
            $tpl = CatalogCommons::get_templates_user_prefix().$filter['template'];
            $linkParams = "";
            if (isset($_REQUEST['filterid']))
                $linkParams .= "filterid=".$filter_stringid."&";
        }
        $curr_cat_id = 0;
        if (strlen($filter['catids']) == 0) //показываем товары из текущей - добавляем параметр с категорией
        {
            $curr_cat_id = $this->get_current_catIDs();
            if ($curr_cat_id)
            {
                if (is_array($curr_cat_id))
                {
                    foreach ($curr_cat_id as $ccid)
                    {
                        $linkParams .= "cid[".$ccid."]=on&";
                    }
                }
                else
                    $linkParams .= "cid=".$curr_cat_id."&";
            }
        }
        $this->set_templates($kernel->pub_template_parse($tpl));

        $sql = $this->process_variables_out($filter['query']);
        $sql = $this->prepare_inner_filter_sql($sql, $params, $linkParams);

        if (!$sql)
        {
            $content = $this->process_filters_in_template($this->get_template_block('list_null'), $filter['stringid']);
            if ($curr_cat_id && is_numeric($curr_cat_id))
                $content = $this->cats_props_out($curr_cat_id, $content);
            return $content;
        }
        $filter['query'] = $sql;


        $query = $this->convert_inner_filter_query2sql($filter, $group);
        if (!$query)
            return $this->process_filters_in_template($this->get_template_block('list_null'), $filter['stringid']);
        //обрежем и модифицируем запрос для получения общего кол-ва товаров
        $pos = mb_strpos(mb_strtolower($query), "order by");
        if ($pos === false)
            $countQuery = $query;
        else
            $countQuery = mb_substr($query, 0, $pos);

        $pos = mb_strpos(mb_strtolower($countQuery), " from");
        $countQuery = "SELECT COUNT(*) AS count ".mb_substr($countQuery, $pos);
        $total = 0;
        $result = $kernel->runSQL($countQuery);

        if ($row = mysql_fetch_assoc($result))
            $total = $row['count'];
        mysql_free_result($result);

        /*
        Ограничения по количеству
        Так же нужно иметь возможность ограничить получаемый результат по количеству (LIMIT в mysql).
        К примеру, если нам нужно получить ТОП-5 товаров с низкой ценой, то мы установим значение 5.
        Если значение не установлено – тогда в результат отдается все найденные товары
        */
        if ((!empty($filter['limit']) && intval($filter['limit']) > 0) && $total > intval($filter['limit']))
            $total = intval($filter['limit']);

        $offset = $this->get_offset_user();
        if ($offset >= $total)
            $offset = 0;

        $limit = $filter['perpage'];

        //добавим LIMIT к запросу и выполним его
        if ($limit > 0)
            $query .= " LIMIT ".$offset.", ".$limit;
        $items = array();
        $result = $kernel->runSQL($query);
        if ($result)
        {
            while ($row = mysql_fetch_assoc($result))
                $items[] = $row;
            mysql_free_result($result);
        }
        $count = count($items);

        if ($count == 0)
        {
            $content = $this->process_filters_in_template($this->get_template_block('list_null'), $filter['stringid']);
            if ($curr_cat_id && is_numeric($curr_cat_id))
                $content = $this->cats_props_out($curr_cat_id, $content);
            return $content;
        }

        if ($group)
            $props = CatalogCommons::get_props($group['id'], true);
        else
            $props = CatalogCommons::get_props(0, false);

        if (empty($filter['targetpage']))
            $targetPage = $kernel->pub_page_current_get();
        else
            $targetPage = $filter['targetpage'];
        //Сформируем сначала строки с товарами
        $rows = '';
        $curr = 1;
        foreach ($items as $item)
        {
            if ($curr % 2 == 0) //строка - чётная
                $odd_even = "even";
            else
                $odd_even = "odd";
            //Взяли блок строчки
            $block = $this->get_template_block('row_'.$odd_even);
            if (empty($block))
                $block = $this->get_template_block('row');
            $block = str_replace("%odd_even%", $odd_even, $block);
            //Теперь ищем переменные, свойств и заменяем их
            $block = $this->process_item_props_out($item, $props, $block, $group);
            $block = str_replace("%link%", $targetPage.'.html?'.$this->frontend_param_item_id_name.'='.$item['id'], $block);
            $rows .= $block;
            $curr++;
        }

        $content = $this->get_template_block('list');
        $content = str_replace("%row%", $rows, $content);
        $content = str_replace("%total_in_cat%", $total, $content);
        $purl = $kernel->pub_page_current_get().'.html?'.$linkParams.$this->frontend_param_offset_name.'=';
        $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit, $purl, intval($filter['maxpages'])), $content);
        if ($curr_cat_id && is_numeric($curr_cat_id))
            $content = $this->cats_props_out($curr_cat_id, $content);
        $content = $this->process_filters_in_template($content, $filter['stringid']);
        $content = $this->replace_current_page_url($content);

        if ($need_postprocessing)
        {
            $content = $this->process_variables_out($content);
            //очистим оставшиеся метки
            $content = $this->clear_left_labels($content);
        }
        return $content;
    }

    /**
     * Заменяет в блоке строки вида %variable[myvar]% на значения переменных
     * +вычисляет формулы вида %formula[0.1*6]%
     * +обрабатывает текст %nl2br[....]%, заменяя переводы строки на <br> (функция nl2br)
     * +добавляет текущую дату %date[Y-m-d H:s]% в указанном формате (функция date() )
     * +обрабатывет текст %xml_cleanup[...]% заменяя символы для валидности xmk
     * @param string $block
     * @return string
     */
    private function process_variables_out($block)
    {
        if (preg_match_all("|\\%variable\\[([a-z0-9_]+)\\]\\%|iU", $block, $matches))
        {
            $vars = CatalogCommons::get_variables();
            foreach ($matches[1] as $var)
            {
                if (array_key_exists($var, $vars))
                {
                    $repl = $vars[$var]['value'];
                    if (preg_match("|^([\\d,\\.]+)$|", $repl)) //для дробных числовых значений
                        $repl = str_replace(",", ".", $repl);
                }
                else
                    $repl = "";
                $block = str_ireplace("%variable[".$var."]%", $repl, $block);
            }
        }
        if (preg_match_all("|\\%date\\[(.+)\\]\\%|isU", $block, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $block = str_replace($match[0], date($match[1]), $block);
            }
        }
        if (preg_match_all("|\\%nl2br\\[(.+)\\]\\%|isU", $block, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $block = str_replace($match[0], nl2br($match[1]), $block);
            }
        }
        if (preg_match_all("|\\%xml_cleanup\\[(.+)\\]\\%|isU", $block, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $block = str_replace($match[0], str_replace(array('"', '&', '>', '<', '\''), array('&quot;', '&amp;', '&gt;', '&lt;', '&apos;'), $match[1]), $block);
            }
        }

        if (preg_match_all("|\\%formula\\[(.+)\\]\\%|iU", $block, $matches))
        {
            foreach ($matches[1] as $match)
            {
                //проверим, не осталось ли незаполненных переменных
                if (preg_match("/%([a-z0-9_-]+)_value%/i", $match))
                    $repl = "";
                else
                    $repl = @eval("return ".$match.";");
                $block = str_ireplace("%formula[".$match."]%", $repl, $block);
            }
        }
        return $block;
    }

    /**
     * Подготавливает свойства товара для вывода во frontend, для методов
     * pub_catalog_show_inner_selection_results, pub_catalog_show_item_details,
     * pub_catalog_show_basket_items и pub_catalog_show_items
     *
     * @param array $item товар
     * @param array $props свойства товара
     * @param string $block часть шаблона для товара
     * @param array $group массив тов. группы, чтобы выводить название группы
     * @return string
     */
    private function process_item_props_out($item, $props, $block, $group = array())
    {
        global $kernel;
        if ($group && isset($group['name_full']))
            $block = str_replace("%group.name%", $group['name_full'], $block);

        foreach ($props as $cp)
        {
            $value = '';
            if (isset($item[$cp['name_db']]))
                $value = $item[$cp['name_db']];

            //Прежде всего, вместо непосредственно %<idprop>_value% и %<idprop>_name%
            //может стоять %<idprop>%. В этом случае нужно брать блок @<idprop> и заменять переменную
            //блоком, в котором могут быть всё те-же переменные (%<idprop>_value% и %<idprop>_name%)
            //А если значение пустое, то вместо этого берётся блок @<idprop>_null

            //Алгоритм сделаем с избытком и без доп проверок, что бы переменные %<idprop>_value% и %<idprop>_name% можно
            //было использовать везде. В крайнем случае это будут пустые строки, что не страшно.

            //Взяли блок для переменной, если его нет - то строка будет пустой
            if (mb_strlen($value) == 0)
                $block = str_replace("%".$cp['name_db']."%", $this->get_template_block($cp['name_db']."_null"), $block);
            else
            {
                $block = str_replace("%".$cp['name_db']."%", $this->get_template_block($cp['name_db']), $block);
                switch ($cp['type'])
                {
                    case 'number':
                        $value = $this->cleanup_number($value);
                        break;
                    case 'date':
                        $dformat = $kernel->pub_modul_properties_get('catalog_property_date_format', $kernel->pub_module_id_get());
                        $dformat = trim($dformat['value']);
                        if (empty($dformat))
                            $dformat = 'd.m.Y';
                        $time_val = strtotime($value);
                        $value = date($dformat, $time_val);
                        break;
                    case 'set':
                        $vblocks = array();
                        if (isset($this->templates['separator_'.$cp['name_db']]))
                            $sep = $this->templates['separator_'.$cp['name_db']];
                        elseif (isset($this->templates['item_sets_separator']))
                            $sep = $this->templates['item_sets_separator'];
                        else
                            $sep = "\n";
                        $set_value_tpl = $this->get_template_block($cp['name_db'].'_val');
                        if (!$set_value_tpl)
                            $set_value_tpl = '%setvalue%';
                        foreach (explode(",", $value) as $v)
                        {
                            $vblock = $set_value_tpl;
                            $vblock = str_replace("%setvalue%", $v, $vblock);
                            $vblocks[] = $vblock;
                        }
                        $value = implode($sep, $vblocks);
                        break;
                }

                $block = str_replace('%'.$cp['name_db'].'_value%', $value, $block);
            }
            $block = str_replace('%'.$cp['name_db'].'_name%', $cp['name_full'], $block);

            //Здесь нужно будет обработать доп. переменные для блоков
            //И теперь, если это картинка, то нужно ещё обработать доп переменные на большое/маленькое изображение
            //и на размеры изображения
            if ($cp['type'] == 'pict' && !empty($value))
            {
                //Сначала размеры большого изображения
                if (file_exists($kernel->pub_site_root_get().'/'.$value))
                {
                    $size = @getimagesize($value);
                    if ($size)
                    {
                        $block = str_replace('%'.$cp['name_db'].'_width%', $size[0], $block);
                        $block = str_replace('%'.$cp['name_db'].'_height%', $size[1], $block);
                    }
                }
                //кроме этого надо добавить переменные для малого и исходного изображения
                $path_parts = pathinfo($value);
                $path_small = $path_parts['dirname'].'/tn/'.$path_parts['basename'];
                $path_source = $path_parts['dirname'].'/source/'.$path_parts['basename'];

                if (file_exists($path_small))
                { //размеры маленького изображения, если есть
                    $size = @getimagesize($path_small);
                    if ($size)
                    {
                        $block = str_replace('%'.$cp['name_db'].'_small_width%', $size[0], $block);
                        $block = str_replace('%'.$cp['name_db'].'_small_height%', $size[1], $block);
                    }
                    $block = str_replace('%'.$cp['name_db'].'_small%', $path_small, $block);
                }

                if (file_exists($path_source))
                { //размеры исходного изображения, если есть
                    $size = @getimagesize($path_source);
                    if ($size)
                    {
                        $block = str_replace('%'.$cp['name_db'].'_source_width%', $size[0], $block);
                        $block = str_replace('%'.$cp['name_db'].'_source_height%', $size[1], $block);
                    }
                    $block = str_replace('%'.$cp['name_db'].'_source%', $path_source, $block);
                }
            }
        }
        if (isset($item['commonid']))
            $item_common_id = $item['commonid'];
        else
            $item_common_id = $item['id'];
        $block = str_replace('%item_id%', $item_common_id, $block);

        //проверим, осталось ли в шаблоне чтото вида %aaaaa_value%
        //пример использование - alt в img, равное названию товара
        $matches = false;
        if (preg_match_all("/\\%([a-z0-9_-]+)_value\\%/iU", $block, $matches))
        {
            foreach ($matches[1] as $prop)
            {
                if (isset($item[$prop]))
                    $block = str_ireplace("%".$prop."_value%", $item[$prop], $block);
            }
        }
        return $block;
    }


    /**
     * Публичный метод для отображения списка товаров
     *
     * @param integer        $limit                    товаров на страницу
     * @param integer        $show_cats_if_empty_items выводить ли список категорий, если нет товаров?
     * @param string        $cats_tpl                 файл шаблона для списка категорий
     * @param string        $multi_group_tpl          файл шаблона для разных групп
     * @param integer|boolean $catid                    idшник категории (для прямого вызова)
     * @param string|boolean  $custom_template          файл шаблона (для прямого вызова)
     * @return string
     */
    public function pub_catalog_show_items($limit, $show_cats_if_empty_items, $cats_tpl, $multi_group_tpl = '', $catid = false, $custom_template = false)
    {
        global $kernel;
        if (!$catid)
        {
            $itemid = $kernel->pub_httpget_get($this->frontend_param_item_id_name);
            if (!empty($itemid))
                return $this->pub_catalog_show_item_details($itemid);
        }
        else
            $this->add_categories2waysite($this->get_way2cat($catid, true));

        if (isset($_REQUEST['filterid'])) //значит работаем по внешнему фильтру
            return $this->pub_catalog_show_inner_selection_results($_REQUEST['filterid'], true);

        if (!$catid)
        {
            $catid = $this->get_current_catid(true);
            if ($catid == 0)
            {
                $catid = $this->get_default_catid();
                if ($catid == 0)
                    $catid = $this->get_random_catid();
            }
            else
                $this->add_categories2waysite($this->get_way2cat($catid, true));
        }
        $category = $this->get_category($catid);
        if (!$category)
            frontoffice_manager::throw_404_error();

        if (!$custom_template) //remember last catid
            setcookie($kernel->pub_module_id_get().'_last_catid', $category['id'], time() + 31 * 24 * 60 * 60);

        $total = $this->get_cat_items_count($catid, true);
        $offset = $this->get_offset_user();
        if ($total == 0)
            $items = array();
        else
        {
            $poupularity_sort_days = 0;
            $popprop = $kernel->pub_modul_properties_get("catalog_property_popular_days");
            if ($popprop['isset'])
                $poupularity_sort_days = intval($popprop['value']);
            if ($poupularity_sort_days > 0)
            {
                $statConds = array();
                $statUrlPrefix = "`uri`='/".$kernel->pub_page_current_get().".html?".$this->frontend_param_item_id_name."=";
                $itemids = $this->get_cat_itemids($catid);

                foreach ($itemids as $itemid)
                {
                    $statConds[] = $statUrlPrefix.$itemid."'";
                }
                $time = strtotime("-".$poupularity_sort_days." days");
                $fromTs = mktime(0, 0, 0, date("m", $time), date("d", $time), date("Y", $time));
                $allitems0 = $this->get_cat_items($catid, 0, 0, true);

                $skipLen = 1 + strlen("/".$kernel->pub_page_current_get().".html?".$this->frontend_param_item_id_name."=");
                $cond = "`tstc`>=".$fromTs." AND (".implode(" OR ", $statConds).") GROUP BY `itemid` ORDER BY count DESC";
                $fields = "COUNT(uri) AS count,SUBSTR(`uri`,".$skipLen.") AS itemid";

                $fitems = $kernel->db_get_list_simple("_stat_uri", $cond, $fields);
                $farray = array();

                $pos = 0;
                foreach ($fitems as $fitem)
                {
                    $farray[$fitem['itemid']] = $pos;
                    $pos++;
                }
                $allitems = array();
                $noStatItems = array();
                foreach ($allitems0 as $aitem)
                {
                    if (isset($farray[$aitem['id']]))
                        $allitems[$farray[$aitem['id']]] = $aitem;
                    else
                        $noStatItems[] = $aitem;
                }
                ksort($allitems, SORT_NUMERIC);

                $allitems = array_merge($allitems, $noStatItems);
                if ($limit > 0)
                    $items = array_slice($allitems, $offset, $limit);
                else
                    $items = $allitems;

            }
            else
                $items = $this->get_cat_items($catid, $offset, $limit, true);
        }

        $count = count($items);
        if ($count == 0)
        {
            if ($show_cats_if_empty_items)
                return $this->pub_catalog_show_cats($cats_tpl, $catid);
            else
            {
                if ($custom_template)
                {
                    $tpl = CatalogCommons::get_templates_user_prefix().$custom_template;
                    $this->set_templates($kernel->pub_template_parse($tpl));
                    $content = $this->get_template_block('list_null');
                }
                else
                {
                    if (!empty($multi_group_tpl))
                    {
                        $this->set_templates($kernel->pub_template_parse($multi_group_tpl));
                        $content = $this->get_template_block('list_null');
                    }
                    else
                        $content = $kernel->priv_page_textlabels_replace("[#catalog_show_items_list_no_items#]");
                }
                $content = $this->cats_props_out($category['id'], $content);
                return $content;
            }
        }

        $itemids = array();
        $groupid = 0;
        //проверим, принадлежат ли все товары к одной товарной группе
        //и сохраним id-шники
        $is_single_group = true;
        if ($count > 0)
        {
            $groupid = $items[0]['group_id'];
            foreach ($items as $item)
            {
                $itemids[] = $item['ext_id'];
                if ($groupid != $item['group_id'])
                {
                    $is_single_group = false;
                    break;
                }
            }
        }
        $group = false;
        if ($is_single_group)
        {
            $group = CatalogCommons::get_group($groupid);
            if ($custom_template)
                $tpl = CatalogCommons::get_templates_user_prefix().$custom_template;
            else
            {
                if (empty($group['template_items_list']))
                    return $kernel->priv_page_textlabels_replace("[#catalog_no_group_template_list#]");
                $tpl = CatalogCommons::get_templates_user_prefix().$group['template_items_list'];
            }

            $this->set_templates($kernel->pub_template_parse($tpl));

            if ($count == 0)
                return $this->get_template_block('list_null');

            $items2 = $this->get_group_items($group['name_db'], $itemids);
            $newitems = array();
            foreach ($items as $item)
            {
                $tmp = $item + $items2[$item['ext_id']];
                $tmp['id'] = $item['id'];
                $newitems[] = $tmp;
            }
            $items = $newitems;
            $props = CatalogCommons::get_props($groupid, true);
        }
        else
        {
            if ($custom_template)
            { //experimental
                $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_user_prefix().$custom_template));
                $props = CatalogCommons::get_common_props($kernel->pub_module_id_get(), false);
            }
            else
            {
                if (!empty($multi_group_tpl))
                {
                    $this->set_templates($kernel->pub_template_parse($multi_group_tpl));
                    $props = CatalogCommons::get_common_props($kernel->pub_module_id_get(), false);
                }
                else
                {
                    //Пока выведем такое предпрежедение
                    return "К сожалению, в этой версии каталога не предусмотрен вывод одним списком товаров, принадлежащих к разным товарным группам.";
                }
            }
        }

        //Получаем свойства, к этой группе.
        //при этом, надо пройтись по свойствам и если там есть
        //картинки, то нужно продублировать их свойствами большого
        //и маленького изображения
        for ($i = 0; $i < count($props); $i++)
        {
            if (isset($props[$i]['add_param']))
                $props[$i]['add_param'] = unserialize($props[$i]['add_param']);
        }


        if ($is_single_group)
            $groups = array($group['id'] => $group);
        else
            $groups = CatalogCommons::get_groups();
        //Сформируем сначала строки с товарами
        $rows = '';
        $curr = 1;
        foreach ($items as $item)
        {
            if ($curr % 2 == 0) //строка - чётная
                $odd_even = "even";
            else
                $odd_even = "odd";

            //Взяли блок строчки
            $block = $this->get_template_block('row_'.$odd_even);
            if (empty($block))
                $block = $this->get_template_block('row');

            $block = str_replace("%odd_even%", $odd_even, $block);
            //Теперь ищем переменные, свойств и заменяем их
            $block = $this->process_item_props_out($item, $props, $block, $groups[$item['group_id']]);

            $block = str_replace("%link%", $kernel->pub_page_current_get().'.html?'.$this->frontend_param_item_id_name.'='.$item['id'], $block);
            $rows .= $block;
            $curr++;
        }
        $content = $this->get_template_block('list');
        $content = str_replace("%row%", $rows, $content);
        $content = str_replace("%total_in_cat%", $total, $content);
        $purl = $kernel->pub_page_current_get().'.html?'.$this->frontend_param_cat_id_name.'='.$catid.'&'.$this->frontend_param_offset_name.'=';
        $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit, $purl, 15), $content);
        $content = $this->cats_props_out($category['id'], $content);
        $content = $this->process_variables_out($content);
        $content = $this->process_filters_in_template($content);
        $content = $this->replace_current_page_url($content);
        //очистим оставшиеся метки
        $content = $this->clear_left_labels($content);
        return $content;
    }


    /**
     * Возвращает дорогу к категории от корня
     * @param integer $id id-шник категории
     * @param boolean $skip_root пропускать рут-категорию
     * @param array $cached_cats массив всех категорий
     * @return array
     */
    private function get_way2cat($id, $skip_root = false, $cached_cats = null)
    {
        global $kernel;
        if ($id == 0)
            return array();
        if (is_null($cached_cats))
            $cached_cats = CatalogCommons::get_all_categories($kernel->pub_module_id_get());
        $cats = array();
        $cid = $id;
        do
        {
            //$cat = $this->get_category($cid);
            if (!isset($cached_cats[$cid]))
                break;
            $cat = $cached_cats[$cid];
            $cats[] = $cat;
            $cid = $cat['parent_id'];
        }
        while ($cid != 0);

        $depth = count($cats);
        $res = array();
        for ($i = 0; $i < count($cats); $i++)
        {
            $elem = $cats[$i];
            $elem['depth'] = $depth--;
            $res[] = $elem;
        }
        if (!$skip_root)
        {
            $elem = array('depth' => 0, 'id' => 0, 'name' => 'root');
            $res[] = $elem;
        }
        return array_reverse($res);
    }

    /**
     * Публичный метод для отображения дерева категорий
     *
     * @param string  $template   имя файла шаблона
     * @param integer  $fromcat    id-шник категории, с которой строим
     * @param integer $fromlevel  Уровень начала построения
     * @param integer $openlevels Кол-во раскрываемых уровней меню
     * @param integer $showlevels макс. кол-во выводимых уровней меню
     * @param string $items_pagename страница товара
     * @return string
     * @access public
     */
    public function pub_catalog_show_cats($template, $fromcat = 0, $fromlevel = 1, $openlevels = 1, $showlevels = 1, $items_pagename = '')
    {
        global $kernel;

        if (empty($template) || !file_exists($template))
            return "template not found.";

        $items_pagename = trim($items_pagename);
        if (mb_strlen($items_pagename) == 0)
            $items_pagename = $kernel->pub_page_current_get();
        $items_pagename .= '.html';

        $parsed_template = $kernel->pub_template_parse($template);
        $this->set_templates($parsed_template);
        $fromcat = intval($fromcat);
        $curr_cid = $this->get_current_catid(true);
        $cway = array();
        $need_add_way = true;
        if ($curr_cid == 0)
        {
            $itemid = $kernel->pub_httpget_get($this->frontend_param_item_id_name);
            if (!empty($itemid))
            {
                $cway = $this->get_max_catway2item(intval($itemid));
                if (count($cway) > 0)
                {
                    $curr_cid = $cway[count($cway) - 1]['id'];
                    array_unshift($cway, array('depth' => 0, 'id' => 0, 'name' => 'root'));
                }
            }
            if ($curr_cid == 0)
            {
                $need_add_way = false;
                $curr_cid = $this->get_default_catid();
                if ($curr_cid == 0)
                    $curr_cid = $this->get_random_catid();
            }
        }

        if (empty($cway))
            $cway = $this->get_way2cat($curr_cid);
        if (count($cway) > 0)
            $curr_cat = $cway[count($cway) - 1];
        else
            $curr_cat = false;
        if ($need_add_way)
            $this->add_categories2waysite($cway);

        //позиция категории начала построения в пути
        $fromcat_depth_in_way = intval($this->is_cat_in_array($fromcat, $cway));

        if ($fromcat_depth_in_way >= 0)
        { //Категория начала построения присутствует в пути
            if (count($cway) < $fromcat_depth_in_way + $fromlevel)
                return '';
            //пользователь не дошёл до нужной глубины - меню не нужно

            $catid = $cway[$fromcat_depth_in_way + $fromlevel - 1]['id'];
            $cats = $this->get_child_categories2($catid, 0, array(), $showlevels, $cway, $openlevels);
        }
        else
        { //Категория начала построения НЕ присутствует в пути
            if ($fromlevel != 1)
                return '';
            $catid = $fromcat;
            $cats = $this->get_child_categories($catid, 0, array(), $showlevels);
        }

        $content = '';
        $prev_depth = -1;
        $opened_depths = array();

        $cats_props = CatalogCommons::get_cats_props();

        foreach ($cats as $cat)
        {
            if ($prev_depth != $cat['depth'])
            {
                if ($prev_depth > $cat['depth'])
                {
                    for ($pd = $prev_depth; $pd > $cat['depth']; $pd--)
                    {
                        $content .= $this->get_template_block_with_depth('end', $pd);
                    }
                    if (array_key_exists($prev_depth, $opened_depths))
                        unset($opened_depths[$prev_depth]);

                    //если ещё нет открытой категории с этим уровнем, откроем
                    if (!array_key_exists($cat['depth'], $opened_depths))
                        $content .= $this->get_template_block_with_depth('begin', $cat['depth']);
                }
                else //$prev_depth < $cat['depth']
                    $content .= $this->get_template_block_with_depth('begin', $cat['depth']);

                $opened_depths[$cat['depth']] = true;
                $first = true;
            }
            else
                $first = false;

            if ($first)
                $cblock = '';
            else
                $cblock = $this->get_template_block_with_depth('delimiter', $cat['depth']);

            if ($curr_cid == $cat['id'])
            { //это текущая категория
                $cblock .= $this->get_template_block_with_depth('activelink', $cat['depth']);
            }
            elseif ($this->is_cat_in_array($cat['id'], $cway) >= 0)
            { //эта категория присутствует в пути от корня
                $cblock .= $this->get_template_block_with_depth('passiveactive', $cat['depth']);
            }
            else
                $cblock .= $this->get_template_block_with_depth('link', $cat['depth']);


            foreach ($cats_props as $cat_prop)
            {
                $prop_value = '';
                if (isset($cat[$cat_prop['name_db']]))
                    $prop_value = $cat[$cat_prop['name_db']];
                if (empty($prop_value))
                    $cblock = str_replace("%".$cat_prop['name_db']."%", $this->get_template_block($cat_prop['name_db'].'_null'), $cblock);
                else
                {
                    $cblock = str_replace("%".$cat_prop['name_db']."%", $this->get_template_block($cat_prop['name_db']), $cblock);
                    $cblock = str_replace("%".$cat_prop['name_db']."_value%", $prop_value, $cblock);

                    if ($cat_prop['type'] == 'pict')
                    {
                        $size = @getimagesize($prop_value);
                        if ($size === false)
                            $size = array(0 => "", 1 => "");
                        $cblock = str_replace('%'.$cat_prop['name_db'].'_width%', $size[0], $cblock);
                        $cblock = str_replace('%'.$cat_prop['name_db'].'_height%', $size[1], $cblock);

                        //кроме этого надо добавить переменные для малого и исходного изображения
                        $path_parts = pathinfo($prop_value);
                        $path_small = $path_parts['dirname'].'/tn/'.$path_parts['basename'];
                        $path_source = $path_parts['dirname'].'/source/'.$path_parts['basename'];

                        $cblock = str_replace('%'.$cat_prop['name_db'].'_small%', $path_small, $cblock);
                        $cblock = str_replace('%'.$cat_prop['name_db'].'_source%', $path_source, $cblock);
                    }
                }
                $cblock = str_replace('%'.$cat_prop['name_db'].'_name%', $cat_prop['name_full'], $cblock);

            }

            //проверим, осталось ли в шаблоне чтото вида %aaaaa_value%
            //пример использование - alt в img
            $matches = false;
            if (preg_match_all("/\\%([a-z0-9_-]+)_value\\%/iU", $cblock, $matches))
            {
                foreach ($matches[1] as $prop)
                {
                    if (isset($cat[$prop]))
                        $cblock = str_ireplace("%".$prop."_value%", $cat[$prop], $cblock);
                }
            }

            $cblock = str_replace('%link%', $items_pagename.'?'.$this->frontend_param_cat_id_name.'='.$cat['id'], $cblock);
            $cblock = str_replace('%id%', $cat['id'], $cblock);

            //experimental
            $match = false;
            if (preg_match("|\\%show_items_list\\[(.+)\\]\\%|U", $cblock, $match))
            {
                $items_tpl = $match[1];
                $items_block = $this->pub_catalog_show_items(0, false, "", "", $cat['id'], $items_tpl);
                $this->set_templates($parsed_template);
                $cblock = str_replace($match[0], $items_block, $cblock);
            }
            $cblock = str_replace('%cat_items_count%', $cat['_items_count'], $cblock);
            $content .= $cblock;
            $prev_depth = $cat['depth'];
        }
        arsort($opened_depths);
        //закроем все открытые "глубины"
        foreach ($opened_depths as $ok => $ov)
        {
            $content .= $this->get_template_block_with_depth('end', $ok);
        }

        if ($curr_cat)
        {
            if (isset($curr_cat['name']))
                $content = str_replace("%curr_category_name%", $curr_cat['name'], $content);
            $content = $this->cats_props_out($curr_cat['id'], $content);
        }
        $content = $this->process_variables_out($content);
        //очистим оставшиеся метки
        $content = $this->clear_left_labels($content);
        return $content;
    }


    /**
     * Убирает нули справа после запятой в дробных числах
     *
     * @param float $num
     * @return string
     */
    function cleanup_number($num)
    {
        return preg_replace("/\\.([0]+)$/", "", $num);
    }

    /**
     * Возвращает id-шник текущей категории во front-end
     * Сохраняет выбранную категорию в сессии
     *
     * @param boolean  $http_only  "искать" только в хттт-параметре?
     * @return integer id-шник текущей категории
     */
    private function get_current_catid($http_only = false)
    {
        global $kernel;
        $catid = intval($kernel->pub_httpget_get($this->frontend_param_cat_id_name));
        if ($http_only)
            return ($catid < 1) ? 0 : $catid;

        if ($catid > 0)
        {
            $kernel->pub_session_set("curr_cat_id", $catid);
            return $catid;
        }
        elseif (!is_null($catid = $kernel->pub_session_get("curr_cat_id")))
            return $catid;
        else
            return 0;
        /*
        elseif (($catid = $this->get_default_catid()) > 0)
            return $catid;
        else
            return $this->get_random_catid();*/
    }

    /**
     * Возвращает id-шник категории "по-умолчанию", если такая есть
     *
     * @return integer id-шник категории
     */
    private function get_default_catid()
    {
        global $kernel;
        $ret = 0;
        $query = 'SELECT `id` FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` WHERE `is_default`=1 LIMIT 1';
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $ret = $row['id'];
        mysql_free_result($result);
        return $ret;
    }

    private function get_random_catid()
    {
        global $kernel;
        $ret = 0;
        $query = 'SELECT `id` FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` ORDER BY RAND() LIMIT 1';
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $ret = $row['id'];
        mysql_free_result($result);
        return $ret;
    }

    /**
     * Добавляет товарную группу в БД.
     * создаёт запись в _catalog_item_groups и новую таблицу
     *
     * @param string $name имя товарной группы
     * @param string $namedb БД-имя товарной группы
     * @return integer ID добавленной группы
     */
    private function add_group($name, $namedb)
    {
        global $kernel;

        if (mb_strlen($name) == 0)
            return 0;

        if (mb_strlen($namedb) == 0)
            $namedb = $name;

        $namedb = strtolower($this->translate_string2db($namedb));
        $list_items = $kernel->pub_httppost_get('list_items');
        $one_items = $kernel->pub_httppost_get('one_items');

        if ($namedb == 'items') //это название зарезервировано, т.к. используется как алиас в выборках
            $namedb = 'gitems';

        $n = 2;
        $namedb0 = $namedb;
        while ($this->is_group_exists($namedb))
            $namedb = $namedb0.$n++;

        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_item_groups`'.
            ' (`module_id`,`name_db`,`name_full`, `template_items_list`, `template_items_one`) '.
            'VALUES ("'.$kernel->pub_module_id_get().'","'.$namedb.'","'.$kernel->pub_str_prepare_set($name).'","'.$list_items.'","'.$one_items.'")';
        $kernel->runSQL($query);
        $id = mysql_insert_id();


        //по-умолчанию добавляем все common-свойства как видимые для новой тов. группы
        $cprops = CatalogCommons::get_common_props($kernel->pub_module_id_get(), false);
        foreach ($cprops as $cprop)
        {
            $this->add_group_visible_prop($kernel->pub_module_id_get(), $id, $cprop['name_db']);
        }

        $query = 'CREATE TABLE `'.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.$namedb.'` ( '
            .' `id` int(10) unsigned NOT NULL auto_increment, '
            .' PRIMARY KEY  (`id`) '
            .' ) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci AUTO_INCREMENT=1';
        $kernel->runSQL($query);
        return $id;
    }

    /**
     * Сохраняет товарную группу в БД.
     *
     * @param integer $id id товарной группы
     * @param string $name имя товарной группы
     * @param string $namedb БД-имя группы
     * @return integer id
     */
    private function save_group($id, $name, $namedb)
    {
        if (mb_strlen($name) == 0)
            return 0;
        if (mb_strlen($namedb) == 0)
            $namedb = $name;
        global $kernel;
        if ($id < 1)
            return 0;
        $group = CatalogCommons::get_group($id);
        if (!$group)
            return 0;

        //Кроме этого, получим значение выбранных для групп шаблонов
        $list_items = $kernel->pub_httppost_get('list_items');
        $one_items = $kernel->pub_httppost_get('one_items');

        $namedb = $this->translate_string2db($namedb);
        $namedb = strtolower($namedb);


        $ccbs = isset($_POST['ccb']) ? $_POST['ccb'] : array();

        $catids = array();
        foreach ($ccbs as $catid => $value)
        {
            if ($value == 1)
                $catids[] = $catid;
        }
        $defcatids = implode(",", $catids);
        if ($namedb != $group['name_db'])
        { //изменилось БД-имя товарной группы
            $n = 1;
            while ($this->is_group_exists($namedb))
                $namedb .= $n++;
            if ($namedb == 'items') //это название зарезервировано, т.к. используется как алиас в выборках
                $namedb = 'gitems';
            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.$group['name_db'].'` '.
                'RENAME `'.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.$namedb.'` ';
            $kernel->runSQL($query);
        }
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_groups` '.
            'SET `name_db`="'.$kernel->pub_str_prepare_set($namedb).'", '.
            '`name_full`="'.$kernel->pub_str_prepare_set($name).'", '.
            '`defcatids`="'.$defcatids.'", '.
            '`template_items_list`="'.$list_items.'", '.
            '`template_items_one`="'.$kernel->pub_str_prepare_set($one_items).'" '.
            'WHERE `id`='.$id;
        $kernel->runSQL($query);
        //$this->regenerate_group_tpls($group['id']);
        return $id;
    }


    /**
     * Конвертирует строку создания ENUM или SET-поля в массив
     *
     * @param string $strстрока типа ENUM - "enum('знач1','знач2','знач3')" или SET - "set('1','2','3')"
     * @param boolean $needDefault добавлять первое дефолтовое значение?
     * @return array массив со значениями enum
     */
    private function get_enum_set_prop_values($str, $needDefault = true)
    {
        $str = preg_replace('~^(enum|set)~', '', $str);
        $elems = explode("','", mb_substr($str, 2, -2));
        $res = array();
        //Добавим сюда сразу 0-вое значение
        //при выводе оно будет пропускаться
        //и при сохранении снова же добавляться.
        if ($needDefault)
            $res[0] = 'Не выбран';

        foreach ($elems as $el)
            $res[] = str_replace("''", "'", stripslashes($el));
        $res = array_unique($res);
        return $res;
    }

    /**
     * Пересоздаёт шаблоны для редактирования ВСЕХ товарных групп
     * @param $force boolean
     * @return void
     */
    private function regenerate_all_groups_tpls($force = false)
    {
        //$groups = CatalogCommons::get_groups();
        //foreach ($groups as $group)
        //    $this->regenerate_group_tpls($group['id'], $force);
    }

    /**
     * Генерируем шаблон для редактирования товара
     *
     * Шаблон создаётся для заданной товарной группы. Потом он может быть
     * отредактированны админом так, как ему надо
     * @param integer $groupid
     */
    /*
    function regenerate_admin_template($groupid)
    {
        global $kernel;

        if ($groupid == 0)
            return true;

        $group = $this->get_group($groupid);
        $props = CatalogCommons::get_props($groupid, true);

        //Произведём первичную сортировку массива со свойствами, что бы шаблон был
        //оптимизирован изначально. В дальнейшем пользователь его самостоятельно поменяет
        //Получим свойства, которые
        $sort_def =  array();
        $sort_def['string'] = '01';
        $sort_def['enum']   = '02';
        $sort_def['number'] = '03';
        $sort_def['pict']   = '04';
        $sort_def['text']   = '05';
        $sort_def['html']   = '06';
        $sort_def['file']   = '07';


        $sort = array();
        //Сформируем массив с индексами $props по которому потом и будем
        //строить результ
        foreach ($props as $key => $val)
            $sort[$sort_def[$val['type']].'_'.$key] = $key;

        ksort($sort);

        //Начинаем проходить по массиву возможных свойств, проверять
        //подходит ли нам свойство и начинаем формировать шаблон
        $content = '';
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'blank_adminedit_item_tpl.html'));
        $lines = array();
        $num = 0;
        foreach ($sort as $id_in_prop)
        {
            $val = $props[$id_in_prop];
            $line = $this->get_template_block('add_prop_'.$val['type']);
            $line = str_replace('%prop_name_db%'  , $props['name_db']  , $line);
            $line = str_replace('%prop_name_full%', $props['name_full'], $line);
            //$line = str_replace('%prop_value%', ,$line);

            $line = str_replace('%class%', $kernel->pub_table_tr_class($num),$line);

            $num++;
        }

    }
    */

    /**
     * Формирует часть шаблона со свойствами товара
     *
     * Используется при генерации шаблонво списка товаров и карточки товаров
     * @param array $template распаршенный шаблон
     * @param array $props массив со свойствами
     * @param boolean $include_html формировать для свойства типа HTML
     * @return array
     */
    private function regenerate_group_tpls_only_prop($template, $props, $include_html = false)
    {
        $list_prop_addon = "\n\n";

        if (isset($template['cat_way_block']) && isset($template['cat_way_separator']) &&
            isset($template['cat_way_active']) && isset($template['cat_way_passive'])
        )
        {
            $list_prop_addon .= "<!-- @cat_way_block -->\n".$template['cat_way_block']."\n\n";
            $list_prop_addon .= "<!-- @cat_way_separator -->\n".$template['cat_way_separator']."\n\n";
            $list_prop_addon .= "<!-- @cat_way_active -->\n".$template['cat_way_active']."\n\n";
            $list_prop_addon .= "<!-- @cat_way_passive -->\n".$template['cat_way_passive']."\n\n";
        }
        $prop_names_block = '';
        foreach ($props as $prop)
        {
            if ($prop['type'] == 'html' && !$include_html)
                continue;
            if (isset($template[$prop['name_db']]))
                continue;
            $prop_names_block .= "%".$prop['name_db']."%\n";
        }

        $only_names = $prop_names_block;

        $only_values = '';
        foreach ($props as $prop)
        {
            if ($prop['type'] == 'html' && !$include_html)
                continue;

            if (isset($this->templates[$prop['name_db']])) //чтобы иметь возможность делать особые метки для некоторых полей
                $field = $this->templates[$prop['name_db']];
            else
                $field = $this->get_template_block('prop_'.$prop['type']);

            $field = trim($field);
            $list_prop_addon .= "<!-- @".$prop['name_db']."_null -->";
            $list_prop_addon .= $template[$prop['type'].'_null'];

            //Заменим в самом свойстве в строке эти переменные
            $field = str_replace('%prop_name_full%', $prop['name_full'], $field);
            $field = str_replace('%prop_value%', '%'.$prop['name_db'].'_value%', $field);
            $field = str_replace('%prop%', '%'.$prop['name_db'].'%', $field);
            $field = str_replace('%prop_name_db%', $prop['name_db'], $field);
            $only_values .= "<!-- @".$prop['name_db']." -->\n".$field."\n";

            //И ещё заменим в доп свойствах, если они там есть
            $list_prop_addon = str_replace('%prop_name_full%', $prop['name_full'], $list_prop_addon);
            $list_prop_addon = str_replace('%prop_value%', '%'.$prop['name_db'].'_value%', $list_prop_addon);
        }
        return array('only_values' => $only_values, 'addon' => $list_prop_addon, 'only_names' => $only_names);
    }


    /**
     * Пересоздаёт шаблоны для редактирования товарной группы
     * и вывода всех полей товара во фронтэнд
     *
     * @param integer $groupid id-шник товарной группы
     * @param boolean $for_item_card Если тру, то шаблон будет создаваться для карточки тоавара а не для списка
     * @param boolean $force требуется ли принудительное пересоздание шаблонов, даже если они были изменены
     * @return boolean
     */
    private function regenerate_group_tpls($groupid, $for_item_card = false, $force = false)
    {
        global $kernel;
        if ($groupid == 0)
            return true;
        $group = CatalogCommons::get_group($groupid);
        //пересоздаём шаблон для фронтэнда
        if ($for_item_card)
            $viewfilename = CatalogCommons::get_templates_user_prefix().$kernel->pub_module_id_get().'_'.$group['name_db'].'_card.html';
        else
            $viewfilename = CatalogCommons::get_templates_user_prefix().$kernel->pub_module_id_get().'_'.$group['name_db'].'_list.html';

        $props = CatalogCommons::get_props($groupid, true);
        $visible_props = array_keys($this->get_group_visible_props($groupid));
        foreach ($props as $k => $prop)
        {
            if ($prop['group_id'] == 0 && !in_array($prop['name_db'], $visible_props))
                unset($props[$k]);
        }
        //Пока уберём это проверку, так как она должна будет делаться в форме, и подтвержаться там
        //if ($force || !CatalogCommons::isTemplateChanged($viewfilename, $group['front_tpl_md5']))
        //{//только если шаблон не был изменён или пользователь подтвердил

        if ($for_item_card)
            $arr_template = $kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'frontend_templates/blank_item_one.html');
        else
            $arr_template = $kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'frontend_templates/blank_items_list.html');

        $this->set_templates($arr_template);

        $viewfh = '';
        //Начнём конструировать таблицу шаблон для отображения списка товаров

        //блок информации по товару в списке
        //Вместе с ним сразу создаём шаблон для карточки товара
        $arr_prop = $this->regenerate_group_tpls_only_prop($arr_template, $props, $for_item_card);

        if ($for_item_card)
        {
            $viewfh .= "<!-- @item -->\n";
            $viewfh .= str_replace('%list_prop%', $arr_prop['only_names'], $this->get_template_block('item'));
        }
        else
        { //шаблон списка
            $viewfh .= "<!-- @list -->\n";
            $viewfh .= $this->get_template_block('list');
            $cats_props = CatalogCommons::get_cats_props();
            foreach ($cats_props as $cprop)
            {
                $tblock = $this->get_template_block('category_'.$cprop['name_db']);
                if (!is_null($tblock))
                    $viewfh .= "\n\n<!-- @category_".$cprop['name_db']." -->\n".$tblock."\n\n";
            }
        }

        //блок, если в списке нет элементов
        if (!$for_item_card)
        {
            $viewfh .= "\n\n\n<!-- @list_null -->\n";
            $viewfh .= $this->get_template_block('list_null');
        }


        //Теперь собственно добавим это в результирующий шаблон
        if (!$for_item_card)
        {
            $row_odd = $this->get_template_block('row_odd');
            $row_even = $this->get_template_block('row_even');
            if (!empty($row_odd) && !empty($row_even))
            {
                $viewfh .= "\n\n\n<!-- @row_odd -->\n";
                $viewfh .= str_replace('%list_prop%', $arr_prop['only_names'], $this->get_template_block('row_odd'));
                $viewfh .= "\n\n\n<!-- @row_even -->\n";
                $viewfh .= str_replace('%list_prop%', $arr_prop['only_names'], $this->get_template_block('row_even'));
            }
            else
            {
                $viewfh .= "\n\n\n<!-- @row -->\n";
                $viewfh .= str_replace('%list_prop%', $arr_prop['only_names'], $this->get_template_block('row'));
            }

        }

        $viewfh .= "\n".$arr_prop['only_values'];

        //Добавим доп блоки, которые были сформированы при обработке свойств
        $viewfh .= $arr_prop['addon'];

        //Теперь добавим блок, разделитель между товарами в списке
        //и всё это только для списка товаров
        if (!$for_item_card)
        {
            $viewfh .= "\n\n\n<!-- @row_delimeter -->\n";
            $viewfh .= $this->get_template_block('row_delimeter');


            //блок для вывода навигации по страницам
            $viewfh .= "\n\n\n<!-- @pages -->\n";
            $viewfh .= $this->get_template_block('pages');
            //...и всё для неё
            $viewfh .= "\n<!-- @page_first -->\n";
            $viewfh .= $this->get_template_block('page_first');
            $viewfh .= "\n<!-- @page_backward -->\n";
            $viewfh .= $this->get_template_block('page_backward');
            $viewfh .= "\n<!-- @page_backward_disabled -->\n";
            $viewfh .= $this->get_template_block('page_backward_disabled');
            $viewfh .= "\n<!-- @page_previous -->\n";
            $viewfh .= $this->get_template_block('page_previous');
            $viewfh .= "\n<!-- @page_previous_disabled -->\n";
            $viewfh .= $this->get_template_block('page_previous_disabled');
            $viewfh .= "\n<!-- @page_forward -->\n";
            $viewfh .= $this->get_template_block('page_forward');
            $viewfh .= "\n<!-- @page_forward_disabled -->\n";
            $viewfh .= $this->get_template_block('page_forward_disabled');
            $viewfh .= "\n<!-- @page_next -->\n";
            $viewfh .= $this->get_template_block('page_next');
            $viewfh .= "\n<!-- @page_next_disabled -->\n";
            $viewfh .= $this->get_template_block('page_next_disabled');
            $viewfh .= "\n<!-- @page_last -->\n";
            $viewfh .= $this->get_template_block('page_last');
            $viewfh .= "\n<!-- @page_active -->\n";
            $viewfh .= $this->get_template_block('page_active');
            $viewfh .= "\n<!-- @page_passive -->\n";
            $viewfh .= $this->get_template_block('page_passive');
            $viewfh .= "\n<!-- @page_delimeter -->\n";
            $viewfh .= $this->get_template_block('page_delimeter');
            $viewfh .= "\n<!-- @page_null -->\n";
            $viewfh .= $this->get_template_block('page_null');
        }
        //fwrite($viewfh, $this->get_template_block('footer'));

        //Теперь запишем эту информацию
        $kernel->pub_file_save($viewfilename, $viewfh);
        //fclose($viewfh);

        //$query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_groups` SET `front_tpl_md5`="'.md5_file($viewfilename).'" WHERE `id`='.$groupid;
        //$kernel->runSQL($query);

        //Сразу же сделаем шаблон для карточки товара
        //}
        return true;
        //пересоздаём шаблон редактирования для админки
        //Это пока отключили
        /*
        $editfilename = CatalogCommons::get_templates_admin_prefix().$kernel->pub_module_id_get().'_'.$group['name_db'].'_edit_tpl.html';

        if ($force || !CatalogCommons::isTemplateChanged($editfilename, $group['back_tpl_md5']))
        {//только если файл не был изменён или пользователь подтвердил
            $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'blank_edit_tpl.html'));
            $editfh = fopen($editfilename, "w");
            if (!$editfh)
                return false;
            $props = CatalogCommons::get_props($groupid, true);
            $tinfo = $this->get_dbtable_info('_catalog_items_'.$kernel->pub_module_id_get().'_'.$group['name_db']);
            $tinfo = $tinfo + $this->get_dbtable_info('_catalog_'.$kernel->pub_module_id_get().'_items');
            $block = $this->get_template_block("form_header");
            $block = str_replace('%rows%', count($props)+1, $block);
            $cats = $this->get_child_categories(0);
            $cats_content = '';
            foreach ($cats as $cat)
            {
                $catline = $this->get_template_block("category_item");
                $catline = str_replace("%id%", $cat['id'], $catline);
                $catline = str_replace("%catname%", $cat['name'], $catline);
                $catline = str_replace("%shift%", str_repeat("&nbsp;",$cat['depth']), $catline);
                $cats_content .= $catline;
            }
            $block = str_replace("%categories%", $cats_content, $block);
            fwrite($editfh,"<!-- @form_header -->\n".$block);
            foreach ($props as $prop)
            {
                $field = '';
                switch ($prop['type'])
                {
                    case 'enum':
                        fwrite($editfh,"\n<!-- @prop_".$prop['name_db']." -->\n");
                        $field = $this->get_template_block('add_prop_enum');
                        $vals  = $this->get_enum_prop_values($tinfo[$prop['name_db']]['Type']);
                        $options = $this->get_template_block('prop_enum_value');
                        $options = str_replace('%enum_value%', '',$options);
                        $options = str_replace('%enum_name%', $kernel->pub_page_textlabel_replace('[#catalog_prop_need_select_label#]'),$options);
                        foreach ($vals as $val)
                        {
                            $option = $this->get_template_block('prop_enum_value');
                            $option = str_replace('%enum_value%', $val,$option);
                            $option = str_replace('%enum_name%', $val,$option);
                            $options .= $option;
                        }
                        $field = str_replace('%prop_enum_values%', $options, $field);
                        break;
                    case 'file':
                    case 'pict':
                         fwrite($editfh,"\n<!-- @prop_".$prop['name_db']."_edit -->\n");
                         $field = $this->get_template_block('edit_prop_'.$prop['type']);
                         $field = str_replace('%prop_name_full%',$prop['name_full'],$field);
                         $field = str_replace('%prop_name_db%',$prop['name_db'],$field);
                         fwrite($editfh, $field."\n");
                         fwrite($editfh,"\n<!-- @prop_".$prop['name_db']."_add -->\n");
                         $field = $this->get_template_block('add_prop_'.$prop['type']);
                        break;
                    case 'number':
                    case 'text':
                    case 'string':
                    case 'html':
                        fwrite($editfh,"\n<!-- @prop_".$prop['name_db']." -->\n");
                        $field = $this->get_template_block('add_prop_'.$prop['type']);
                        break;
                }
                $field = str_replace('%prop_name_full%',$prop['name_full'],$field);
                $field = str_replace('%prop_name_db%',$prop['name_db'],$field);
                fwrite($editfh, $field."\n");
            }
            fwrite($editfh,"\n<!-- @form_footer -->\n".$this->get_template_block("form_footer"));
            fclose($editfh);
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_groups` SET `back_tpl_md5`="'.md5_file($editfilename).'" WHERE `id`='.$groupid;
            $kernel->runSQL($query);
        }
        */
    }


    /**
     * Подготавливает значение свойства для обновления БД
     *
     * @param string $val  значение свойства
     * @param string $type тип свойства
     * @return string
     */
    private function prepare_property_value($val, $type)
    {
        global $kernel;
        if (!is_array($val))
        {
            $val = trim($val);
            if (mb_strlen($val) == 0)
                return 'NULL';
        }
        switch ($type)
        {
            case 'number':
                $val = str_replace(',', '.', $val);
                $val = str_replace(' ', '', $val);
                if (!is_numeric($val))
                    $val = 0;
                break;
            case 'date':
                $dvals = explode(".", $val);
                $val = '"'.$dvals[2].'-'.$dvals[1].'-'.$dvals[0].'"';
                break;
            case 'set':
                $elems = array();
                foreach (array_keys($val) as $el)
                {
                    $elems[] = mysql_real_escape_string($el);
                }
                $val = "'".implode(",", $elems)."'";
                break;
            default:
                $val = '"'.$kernel->pub_str_prepare_set($val).'"';
                break;
        }
        return $val;
    }

    /**
     * Подготавливает значение свойства для обновления БД (используется при импорте)
     *
     * @param string $val  значение свойства
     * @param string $type тип свойства
     * @return string
     */
    private function prepare_property_value2($val, $type)
    {
        $ret = trim($val);
        if (mb_strlen($ret) == 0)
            return 'NULL';
        if ($type == 'number')
        {
            //$ret = str_replace(',','.',$ret);
            $ret = preg_replace('/[^\d\\.,]/', '', $ret); //уберём всё кроме цифр, точек и запятых
            $pos = mb_strpos($ret, '.');
            if ($pos)
            { //если нашлась точка, разобъём на целую и дробную части по ней
                $part1 = mb_substr($ret, 0, $pos);
                $part2 = mb_substr($ret, $pos + 1);
            }
            else
            { //если точка не нашлась, попробуем разбить запятой
                $pos = mb_strpos($ret, ',');
                if ($pos)
                {
                    $part1 = mb_substr($ret, 0, $pos);
                    $part2 = mb_substr($ret, $pos + 1);
                }
                else
                { //не нашлась ни точка, ни запятая
                    $part1 = $ret;
                    $part2 = '0';
                }
            }

            $part1 = preg_replace('/[^\d]/', '', $part1); //ещё раз уберём всё кроме цифр (,.) в обоих частях
            $part2 = preg_replace('/[^\d]/', '', $part2);
            $ret = $part1.'.'.$part2;

            //if (!is_numeric($ret)) $ret = 0;
        }
        else
            $ret = '"'.mysql_real_escape_string($ret).'"';
        return $ret;
    }

    /**
     * Сохраняет категорию в БД
     *
     * @param $id integer id-шник категории
     * @return string
     */
    private function save_category($id)
    {
        global $kernel;
        if ($kernel->pub_httppost_get('isdefault'))
        { //значит эта категория будет по-умолчанию, сбрасываем другую
            $query = "UPDATE `".$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` SET `is_default`=0 WHERE `is_default`=1';
            $kernel->runSQL($query);
            $isdef = 1;
        }
        else
            $isdef = 0;
        if ($kernel->pub_httppost_get('_hide_from_waysite'))
            $_hide_from_waysite = 1;
        else
            $_hide_from_waysite = 0;

        $props = CatalogCommons::get_cats_props();
        $cat = $this->get_category($id);
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` SET `_hide_from_waysite`= '.$_hide_from_waysite.', ';
        for ($i = 0; $i < count($props); $i++)
        {
            $prop = $props[$i];
            if ($prop['type'] == 'file' || $prop['type'] == 'pict')
            {
                if (isset($_FILES[$prop['name_db']]))
                {
                    if ($prop['type'] == 'pict')
                        $val = $this->process_pict_upload($_FILES[$prop['name_db']], $prop);
                    else
                        $val = $this->process_file_upload($_FILES[$prop['name_db']]);
                }
                elseif (!empty($cat[$prop['name_db']]))
                    $val = $cat[$prop['name_db']];
                else
                    $val = '';
            }
            else
                $val = $kernel->pub_httppost_get($prop['name_db'], false);
            $query .= '`'.$prop['name_db'].'`='.$this->prepare_property_value($val, $prop['type']).',';
        }
        $query .= ' `is_default`='.$isdef.' WHERE `id`='.$id;
        $kernel->runSQL($query);
        $this->regenerate_all_groups_tpls(false);
        return $kernel->pub_httppost_response('[#common_saved_label#]', 'category_items&id='.$id);
    }

    /**
     * Обновляет порядок товаров в категории с шагом $this->order_inc
     *
     * @param integer $catid id-шник категории
     * @return void
     */
    private function refresh_items_order_in_cat($catid)
    {
        global $kernel;
        $itemids = $this->get_cat_itemids($catid);
        $order = $this->order_inc;
        foreach ($itemids as $itemid)
        {
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` '.
                'SET `order`='.$order.' '.
                'WHERE `item_id`='.$itemid.' AND `cat_id`='.$catid;
            $kernel->runSQL($query);
            $order += $this->order_inc;
        }
    }

    /**
     * Сохраняет товары категории в БД
     * @param integer $catid IDшник категории
     * @return void
     */
    private function save_category_items($catid)
    {
        global $kernel;

        $val = $kernel->pub_httppost_get("saveorder");
        if (!empty($val)) //сохраняем порядок товаров?
        {
            $iorders = $kernel->pub_httppost_get("iorder");
            foreach ($iorders as $itemid => $order)
            {
                if (is_numeric($order))
                {
                    $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` SET `order`='.$order.' WHERE `cat_id`='.$catid.' AND `item_id`='.$itemid;
                    $kernel->runSQL($query);
                }
            }
            $this->refresh_items_order_in_cat($catid);
        }

        $val = $kernel->pub_httppost_get("saveselected");
        if (!empty($val)) // производим действие с отмеченными?
        {
            $vals = $kernel->pub_httppost_get("icb");
            $itemids = array();
            foreach ($vals as $itemid => $checked)
            {
                if ($checked)
                    $itemids[] = $itemid;
            }
            switch ($kernel->pub_httppost_get("withselected"))
            {
                case "remove_from_current":
                    if (count($itemids))
                    {
                        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` WHERE `cat_id`='.$catid.' AND `item_id` IN ('.implode(',', $itemids).')';
                        $kernel->runSQL($query);
                    }
                    break;
                case "move2":
                    $moveid = intval($kernel->pub_httppost_get("cats"));
                    if ($moveid > 0)
                    {
                        if (count($itemids))
                        {
                            $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` WHERE `cat_id`='.$catid.' AND `item_id` IN ('.implode(',', $itemids).')';
                            $kernel->runSQL($query);
                        }

                        foreach ($itemids as $itemid)
                        {
                            $order = $this->get_next_order_in_cat($moveid);
                            $query = 'REPLACE INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat`
               	                    (`cat_id`,`order`,`item_id`)
               	                    VALUES
               	                    ("'.$moveid.'","'.$order.'","'.$itemid.'")';
                            $kernel->runSQL($query);
                        }
                    }
                    break;
                case "delete_selected":
                    $this->delete_items($itemids);
                    break;
            }

        }
    }


    /**
     * Удаляет товары по id-шникам
     *
     * @param array $itemids массив id-шников товаров
     * @return void
     */
    private function delete_items($itemids)
    {
        foreach ($itemids as $itemid)
            $this->delete_item($itemid);
    }


    /**
     * Обработка file-upload, для свойств типа "файл"
     *
     * @param  string $file имя поля (input type='file') в html-форме
     * @return string имя сохранённого файла
     */
    private function process_file_upload($file)
    {
        global $kernel;

        if (!is_uploaded_file($file['tmp_name']))
            return '';

        //Имя файла пропустим через транслит, что бы исключить руские буквы
        //отделив сначала расширение
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = basename($file['name'], ".".$file_ext);
        $only_name = $this->translate_string2db($filename);
        $filename = $only_name.".".$file_ext;

        //Путь, куда будут сохранятся файл или изображение
        $dir = 'content/files/'.$kernel->pub_module_id_get().'/';

        //Проверим наличе дубликата, и добавим цифру если что
        $i = 0;
        while (file_exists($dir.$filename))
        {
            $filename = $only_name.'_'.$i.'.'.$file_ext; //$i."_".$filename;
            $i++;
        }

        $kernel->pub_file_move($file['tmp_name'], $dir.$filename, true, true);
        return $dir.$filename;
    }

    /**
     * Обработка file-upload, для свойств типа "картинка"
     *
     * @param string $file  имя поля (input type='file') в html-форме
     * @param array $prop  Массив с параметрами свойства, в нём для картинки передаются всяки дополнения
     * @return string имя сохранённого файла
     */
    private function process_pict_upload($file, $prop)
    {
        global $kernel;
        if (!is_uploaded_file($file['tmp_name']))
            return '';
        //Прежде определим, заданы ли параметры у этого
        //свойства с картинкой
        if ((isset($prop['add_param'])) && (!empty($prop['add_param'])))
            $prop['add_param'] = unserialize($prop['add_param']);
        else
            return $this->process_file_upload($file, $prop);

        //Имя файла пропустим через транслит, что бы исключить руские буквы
        //отделив сначала расширение
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = basename($file['name'], ".".$file_ext);
        $only_name = $this->translate_string2db($filename);
        $filename = $only_name;

        //Теперь определим, нужно ли нам что-то делать с исходным изображением

        //У картинки всё сложнее, надо определить, будем ли мы
        //создавать большое изображение и добавлять к нему водяной знак
        $big = null;
        if ($prop['add_param']['big']['isset'])
        {
            $big = array(
                'width' => $prop['add_param']['big']['width'],
                'height' => $prop['add_param']['big']['height']
            );
        }
        //Параметры водяной марки для большого изображения...
        $watermark_image_big = 0;
        $_tmp = $prop['add_param']['big'];
        $_tmp['water_path'] = $kernel->priv_file_full_patch($_tmp['water_path']);
        $_is_add = $kernel->pub_httppost_get($prop['name_db'].'_need_add_big_water');

        if (isset($_tmp['water_add']) &&
            //(file_exists($_tmp['water_path'])) &&
            is_file($_tmp['water_path']) &&
            ($_tmp['water_add'] == 1 || ($_tmp['water_add'] == 2 && !empty($_is_add)))
        )
        {
            $watermark_image_big = array(
                'path' => $_tmp['water_path'],
                'place' => $_tmp['water_position'],
                'transparency' => 25 //@todo use field settings
            );
        }

        //А теперь смотрим, нужно ли нам модифицировать исходное изображение
        $source_res = 0;
        if ($prop['add_param']['source']['isset'])
        {
            $source_res = array(
                'width' => $prop['add_param']['source']['width'],
                'height' => $prop['add_param']['source']['height']
            );
        }

        //... может и знак надо к нему добавить
        $watermark_image_source = 0;
        $_tmp = $prop['add_param']['source'];
        $_tmp['water_path'] = $kernel->priv_file_full_patch($_tmp['water_path']);
        $_is_add = $kernel->pub_httppost_get($prop['name_db'].'_need_add_source_water');

        if (isset($_tmp['water_add']) &&
            file_exists($_tmp['water_path']) &&
            ($_tmp['water_add'] == 1 || ($_tmp['water_add'] == 2 && !empty($_is_add)))
        )
        {
            $watermark_image_source = array(
                'path' => $_tmp['water_path'],
                'place' => $_tmp['water_position'],
                'transparency' => 25 //@todo use field settings
            );
        }

        //теперь параметры малого изображения
        $thumb = 0;
        if ($prop['add_param']['small']['isset'])
        {
            $thumb = array(
                'width' => $prop['add_param']['small']['width'],
                'height' => $prop['add_param']['small']['height']
            );
        }

        //Задаём путь для сохранения обработанных изображений.
        //такой путь должен существовать
        $path_to_save = 'content/files/'.$kernel->pub_module_id_get();
        $path_to_create = $kernel->pub_module_id_get();

        if (!empty($prop['add_param']['pict_path']))
        {
            $path_to_create .= "/".$prop['add_param']['pict_path'];
            $path_to_save .= "/".$prop['add_param']['pict_path'];
        }

        //Теперь вызовим созданий директорий, что бы они точно были
        //$kernel->pub_dir_create_in_files($path_to_create);
        $kernel->pub_dir_create_in_files($path_to_create."/tn");
        $kernel->pub_dir_create_in_files($path_to_create."/source");
        $filename = $kernel->pub_image_save($file['tmp_name'], $filename, $path_to_save, $big, $thumb, $watermark_image_big, $source_res, $watermark_image_source);
        //Обязательно добавим к файлу путь, так как иначе может возникнуть большая путаница
        return $path_to_save.'/'.$filename;
    }


    /**
     * Сохраняет изменённые товары в админке при быстром редактировании (пункт "Товары" в меню)
     * @return void
     */
    private function change_selected_items()
    {
        global $kernel;
        $kv = $_POST['iv'];
        if (!is_array($kv))
            return;
        foreach ($kv as $itemid => $idata)
        {
            $udata = array();
            foreach ($idata as $k => $v)
            {
                if (empty($v))
                    $udata[] = "`".$k."`=NULL";
                else
                    $udata[] = "`".$k."`='".mysql_real_escape_string($v)."'";
            }
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` SET '.implode(",", $udata).' WHERE id='.$itemid;
            $kernel->runSQL($query);
        }
    }

    /**
     * Выполняет действия с выбранными товарами в админке (пункт "Товары" в меню)
     *
     */
    private function save_selected_items()
    {
        global $kernel;
        $vals = $kernel->pub_httppost_get("icb");
        if (!is_array($vals))
            return;
        $itemids = array();
        foreach ($vals as $itemid => $checked)
            $itemids[] = $itemid;
        $action = $kernel->pub_httppost_get('withselected');
        switch ($action)
        {
            case 'delete_selected':
                $this->delete_items($itemids);
                break;
            default: //добавление в категорию, параметр- айдишник категории
                if (count($itemids) > 0)
                {
                    $cat_itemids = $this->get_cat_itemids($action);
                    $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` (`item_id`, `cat_id`, `order`) VALUES ';
                    $vals = array();
                    $order = $this->get_next_order_in_cat($action);
                    foreach ($itemids as $itemid)
                    {
                        if (in_array($itemid, $cat_itemids))
                            continue; //если товар уже есть вэтой категории - пропускаем
                        $vals[] = ' ('.$itemid.','.$action.', '.$order.')';
                        $order += $this->order_inc;
                    }
                    if (count($vals) > 0)
                    {
                        $query .= implode(',', $vals);
                        $kernel->runSQL($query);
                    }
                }
                break;
        }

    }

    /**
     * Сохраняет товар в БД после его редактирования или добавления
     * @return string
     */
    private function save_item()
    {
        global $kernel;
        $itemid = $kernel->pub_httppost_get("id");
        //Если $itemid равен 0, то это новый товар и сначала его просто добавим
        //а потом вызовем стандартную функцию сохранения
        if (intval($itemid) == 0)
        {
            $itemid = $this->add_item();
            if (!$itemid)
                return $kernel->pub_httppost_errore('[#interface_global_label_error#]', true);
        }
        $item = $this->get_item_full_data($itemid);

        $moduleid = $kernel->pub_module_id_get();
        $main_prop = $this->get_common_main_prop();
        //сначала сохраним common-свойства
        $props = CatalogCommons::get_common_props($kernel->pub_module_id_get());
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_items` SET ';
        for ($i = 0; $i < count($props); $i++)
        {
            $prop = $props[$i];
            if ($prop['type'] == 'file' || $prop['type'] == 'pict')
            {
                if (isset($_FILES[$prop['name_db']]))
                {
                    if ($prop['type'] == 'pict')
                        $val = $this->process_pict_upload($_FILES[$prop['name_db']], $prop);
                    else
                        $val = $this->process_file_upload($_FILES[$prop['name_db']]);
                }
                elseif (!empty($item[$prop['name_db']]))
                    $val = $item[$prop['name_db']];
                else
                    $val = '';
            }
            else
                $val = $kernel->pub_httppost_get($prop['name_db'], false);
            if ($val && $main_prop == $prop['name_db'])
            {
                $exrec = $kernel->db_get_record_simple("_catalog_".$moduleid."_items", "`".$prop['name_db']."`='".mysql_real_escape_string($val)."'", "id");
                if ($exrec && $exrec['id'] != $itemid)
                {
                    $msg = $kernel->pub_page_textlabel_replace('[#catalog_not_uniq_main_prop_save#]');
                    $msg = str_replace('%fieldname%', $prop['name_full'], $msg);
                    return $kernel->pub_httppost_errore($msg, true);
                }
            }
            $query .= '`'.$prop['name_db'].'`='.$this->prepare_property_value($val, $prop['type']).',';
        }
        $aval = $kernel->pub_httppost_get("available");
        if (empty($aval))
            $aval = 0;
        else
            $aval = 1;
        $query .= ' `available`='.$aval.' WHERE `id`='.$itemid;
        $kernel->runSQL($query);

        //теперь custom-свойства этой товарной группы
        //если они есть у этой товарной группы
        $props = CatalogCommons::get_props($item['group_id'], false);
        $group = CatalogCommons::get_group($item['group_id']);

        if (count($props) > 0)
        {
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_items_'.$moduleid.'_'.strtolower($group['name_db']).'` SET ';
            for ($i = 0; $i < count($props); $i++)
            {
                $prop = $props[$i];
                if ($prop['type'] == 'file' || $prop['type'] == 'pict')
                {
                    if (isset($_FILES[$prop['name_db']]))
                    {
                        if ($prop['type'] == 'pict')
                            $val = $this->process_pict_upload($_FILES[$prop['name_db']], $prop);
                        else
                            $val = $this->process_file_upload($_FILES[$prop['name_db']]);
                    }
                    elseif (!empty($item[$prop['name_db']]))
                        $val = $item[$prop['name_db']];
                    else
                        $val = '';
                }
                else
                    $val = $kernel->pub_httppost_get($prop['name_db'], false);
                $query .= '`'.$prop['name_db'].'`='.$this->prepare_property_value($val, $prop['type']);

                if ($i != count($props) - 1)
                    $query .= ',';
            }
            $query .= ' WHERE `id`='.$item['ext_id'];
            $kernel->runSQL($query);
        }

        //...и категории
        $item_catids = $this->get_item_catids_with_order($itemid);
        $cats = CatalogCommons::get_all_categories($moduleid);
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_item2cat` (`item_id`, `cat_id`, `order`) VALUES ';
        $vals = array();
        if (isset($_POST['ccb']) && is_array($_POST['ccb']))
            $ccbPost=$_POST['ccb'];
        else
            $ccbPost=array();
        foreach ($cats as $cat)
        {
            if (isset($ccbPost[$cat['id']]))
            { //добавляем запись, только если отмечен чекбокс...
                if (!array_key_exists($cat['id'], $item_catids))
                { //...и товар ещё не принадлежит к категории
                    $order = $this->get_next_order_in_cat($cat['id']);
                    $vals[] = ' ('.$itemid.','.$cat['id'].', '.$order.')';
                }
            }
            else
            { //чекбокс не отмечен
                if (array_key_exists($cat['id'], $item_catids))
                { //товар был в категории, но чекбокс снят - удалим
                    $del_q = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_item2cat`
                              WHERE  `item_id`='.$itemid.' AND `cat_id`='.$cat['id'];
                    $kernel->runSQL($del_q);
                }
            }
        }
        if ($vals)
        {
            $query .= implode(',', $vals);
            $kernel->runSQL($query);
        }

        $addlinkedid = intval($kernel->pub_httppost_get('addlinkedid'));
        if ($addlinkedid)
        {
            $id2 = intval($kernel->pub_httppost_get('addlinkedid'));
            $this->add_items_link($itemid, $id2);
            return $kernel->pub_httppost_response("[#catalog_linked_item_added_msg#]", "item_edit&id=".$itemid.'&redir2='.$kernel->pub_httppost_get('redir2'));
        }
        return $kernel->pub_httppost_response('[#common_saved_label#]', $kernel->pub_httppost_get('redir2'));
    }


    /**
     * Добавляет товар в БД
     *
     * @return integer id-шник добавленого товара
     */
    private function add_item()
    {
        global $kernel;
        $groupid = intval($kernel->pub_httppost_get("group_id"));
        if (!$groupid)
            return 0;
        $group = CatalogCommons::get_group($groupid);
        //сохраним в кукисах ID-шник товарной группы, чтобы сделать её активной при след. добавлении
        setcookie("last_add_item_groupid", $groupid);
        $query = 'INSERT INTO '.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']).
            ' (`id`) VALUES (NULL)';
        $kernel->runSQL($query);
        $ext_id = mysql_insert_id();
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` '.
            '(`ext_id`,`group_id`) VALUES '.
            '('.$ext_id.','.$groupid.')';
        $kernel->runSQL($query);
        $id = mysql_insert_id();
        return $id;
    }

    /**
     * Сохраняет свойство(поле) для товарной группы. Создаёт запись в _catalog_item_groups и новую таблицу
     *
     * @param integer $pid id-шник свойства
     * @param string $name_full полное имя свойства
     * @param string $name_db БД-имя свойства
     * @param string $cb_inlist
     * @param string $sort
     * @param string $cb_ismain
     * @return void
     */
    private function save_prop($pid, $name_full, $name_db, $cb_inlist, $sort, $cb_ismain)
    {
        global $kernel;
        $prop = $this->get_prop($pid);

        if (empty($cb_inlist))
            $inlist = 0;
        else
            $inlist = 1;
        if (empty($cb_ismain))
            $ismain = 0;
        else
            $ismain = 1;

        $name_db = $this->translate_string2db($name_db);
        $moduleid = $kernel->pub_module_id_get();
        //изменилось ли БД-имя?
        if ($name_db != $prop['name_db'])
        {
            $n = 1;
            while ($this->is_prop_exists($prop['group_id'], $name_db) || $this->is_prop_exists(0, $name_db))
                $name_db .= $n++;
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_props` SET `name_db`="'.$name_db.'" WHERE `id`='.$pid;
            $kernel->runSQL($query);
            if ($prop['group_id'] > 0)
            {
                $group = CatalogCommons::get_group($prop['group_id']);
                $table = '_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']);
                @unlink($kernel->pub_site_root_get()."/modules/catalog/templates_admin/items_search_form_".$group['name_db'].".html");
            }
            else
            {
                $table = '_catalog_'.$moduleid.'_items';
                //изменилось БД-имя и это общее свойство - обновим таблицу видимых свойств для групп
                $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_visible_gprops`
                    SET `prop`="'.$kernel->pub_str_prepare_set($name_db).'"
                    WHERE `prop`="'.$prop['name_db'].'" AND `module_id`="'.$moduleid.'"';
                $kernel->runSQL($query);
            }
            $values = null;
            if ($prop['type'] == 'enum' || $prop['type'] == 'set')
            {
                $tinfo = $kernel->db_get_table_info($table);
                $values = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
            }
            $db_type = $this->convert_field_type_2_db($prop['type'], $values);

            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE COLUMN `'.$prop['name_db'].'` `'.$name_db.'` '.$db_type;
            $kernel->runSQL($query);
        }

        //изменились поля?
        if ($name_full != $prop['name_full'] || $inlist != $prop['showinlist'] || $sort != $prop['sorted'] || $ismain != $prop['ismain'])
        {
            if ($sort > 0)
            { //сбросим `sorted` для остальных полей
                $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_props` SET `sorted`=0 WHERE `group_id`=0 AND `module_id`="'.$moduleid.'"';
                $kernel->runSQL($query);
            }
            if ($ismain == 1)
            { //сбросим `ismain` для остальных полей
                $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_props` SET `ismain`=0 WHERE `group_id`=0 AND `module_id`="'.$moduleid.'"';
                $kernel->runSQL($query);
            }

            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_props` SET
                `name_full`="'.$name_full.'",
                `showinlist`='.$inlist.',
                `ismain`='.$ismain.',
                `sorted`='.$sort.'
                WHERE `id`='.$pid;
            $kernel->runSQL($query);
        }

        //Если это изображение, то обновим информацию по изображениям
        if ($prop['type'] == 'pict')
        {

            $prop['add_param']['pict_path'] = $kernel->pub_httppost_get('pict_path');

            //Исходное изображение
            if ($kernel->pub_httppost_get('pict_source_isset'))
                $prop['add_param']['source']['isset'] = true;
            else
                $prop['add_param']['source']['isset'] = false;

            $prop['add_param']['source']['width'] = intval($kernel->pub_httppost_get('pict_source_width'));
            $prop['add_param']['source']['height'] = intval($kernel->pub_httppost_get('pict_source_height'));
            $prop['add_param']['source']['water_add'] = intval($kernel->pub_httppost_get('pict_source_water_add'));
            $prop['add_param']['source']['water_path'] = $kernel->pub_httppost_get('path_source_water_path');
            $prop['add_param']['source']['water_position'] = intval($kernel->pub_httppost_get('pict_source_water_position'));

            // большое изображение
            if ($kernel->pub_httppost_get('pict_big_isset'))
                $prop['add_param']['big']['isset'] = true;
            else
                $prop['add_param']['big']['isset'] = false;

            $prop['add_param']['big']['width'] = intval($kernel->pub_httppost_get('pict_big_width'));
            $prop['add_param']['big']['height'] = intval($kernel->pub_httppost_get('pict_big_height'));
            $prop['add_param']['big']['water_add'] = intval($kernel->pub_httppost_get('pict_big_water_add'));
            $prop['add_param']['big']['water_path'] = $kernel->pub_httppost_get('path_big_water_path');
            $prop['add_param']['big']['water_position'] = intval($kernel->pub_httppost_get('pict_big_water_position'));

            //Малое изображение
            if ($kernel->pub_httppost_get('pict_small_isset'))
                $prop['add_param']['small']['isset'] = true;
            else
                $prop['add_param']['small']['isset'] = false;

            $prop['add_param']['small']['width'] = intval($kernel->pub_httppost_get('pict_small_width'));
            $prop['add_param']['small']['height'] = intval($kernel->pub_httppost_get('pict_small_height'));

            //Теперь обновим и запишим этот массив в mysql
            $query = "UPDATE `".$kernel->pub_prefix_get()."_catalog_item_props` SET
                `add_param`='".serialize($prop['add_param'])."'
                WHERE `id`=".$pid;

            $kernel->runSQL($query);
        }

        if (($name_full != $prop['name_full'] || $name_db != $prop['name_db']) && in_array($prop['type'], array('string', 'text', 'html', 'number', 'enum')))
        { //изменилось что-то, что требует регенерации шаблона поиска
            if ($prop['group_id'] == 0)
            { //изменилось общее свойство
                $groups = CatalogCommons::get_groups($kernel->pub_module_id_get());
                foreach ($groups as $group)
                {
                    $this->generate_search_form($group['id'], array());
                }
            }
            else //изменилось свойство группы
                $this->generate_search_form($prop['group_id'], array());
        }

        //Перегенерацию шаблонов убираем пока, она будет ручной
        /*
        if ($prop['group_id']>0)
            $this->regenerate_group_tpls($prop['group_id']);
        else
        {
            $this->regenerate_all_groups_tpls(false);
            CatalogCommons::regenerate_frontend_item_common_block($kernel->pub_module_id_get(), false);
        }
        */
    }


    /**
     * Сохраняет свойство(поле) для КАТЕГОРИИ.
     *
     * @param integer $pid id-шник свойства
     * @param string $name_full полное имя свойства
     * @param string $name_db БД-имя свойства
     * @return void
     */
    private function save_cat_prop($pid, $name_full, $name_db)
    {
        global $kernel;
        $prop = $this->get_cat_prop($pid);
        if (isset($prop['add_param']))
            $prop['add_param'] = @unserialize($prop['add_param']);
        $table = "_catalog_".$kernel->pub_module_id_get()."_cats_props";

        //изменилось ли БД-имя?
        if ($name_db != $prop['name_db'])
        {
            $n = 1;
            while ($this->is_cat_prop_exists($name_db))
                $name_db .= $n++;
            $query = 'UPDATE `'.$kernel->pub_prefix_get().$table.'` SET '.
                '`name_db`="'.$kernel->pub_str_prepare_set($name_db).'" WHERE `id`='.$pid;
            $kernel->runSQL($query);

            $values = null;
            if ($prop['type'] == 'enum')
            {
                $tinfo = $kernel->db_get_table_info($table);
                $values = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
            }
            $db_type = $this->convert_field_type_2_db($prop['type'], $values);
            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` CHANGE COLUMN `'.$prop['name_db'].'` `'.$name_db.'` '.$db_type;
            $kernel->runSQL($query);
        }

        //изменилось название?
        if ($name_full != $prop['name_full'])
        {
            $query = 'UPDATE `'.$kernel->pub_prefix_get().$table.'` SET `name_full`="'.$kernel->pub_str_prepare_set($name_full).'" WHERE `id`='.$pid;
            $kernel->runSQL($query);
        }

        //Если это изображение, то обновим информацию по изображениям
        if ($prop['type'] == 'pict')
        {
            $prop['add_param']['pict_path'] = $kernel->pub_httppost_get('pict_path');

            //Исходное изображение
            if ($kernel->pub_httppost_get('pict_source_isset'))
                $prop['add_param']['source']['isset'] = true;
            else
                $prop['add_param']['source']['isset'] = false;

            $prop['add_param']['source']['width'] = intval($kernel->pub_httppost_get('pict_source_width'));
            $prop['add_param']['source']['height'] = intval($kernel->pub_httppost_get('pict_source_height'));
            $prop['add_param']['source']['water_add'] = intval($kernel->pub_httppost_get('pict_source_water_add'));
            $prop['add_param']['source']['water_path'] = $kernel->pub_httppost_get('path_source_water_path');
            $prop['add_param']['source']['water_position'] = intval($kernel->pub_httppost_get('pict_source_water_position'));

            // большое изображение
            if ($kernel->pub_httppost_get('pict_big_isset'))
                $prop['add_param']['big']['isset'] = true;
            else
                $prop['add_param']['big']['isset'] = false;

            $prop['add_param']['big']['width'] = intval($kernel->pub_httppost_get('pict_big_width'));
            $prop['add_param']['big']['height'] = intval($kernel->pub_httppost_get('pict_big_height'));
            $prop['add_param']['big']['water_add'] = intval($kernel->pub_httppost_get('pict_big_water_add'));
            $prop['add_param']['big']['water_path'] = $kernel->pub_httppost_get('path_big_water_path');
            $prop['add_param']['big']['water_position'] = intval($kernel->pub_httppost_get('pict_big_water_position'));

            //Малое изображение
            if ($kernel->pub_httppost_get('pict_small_isset'))
                $prop['add_param']['small']['isset'] = true;
            else
                $prop['add_param']['small']['isset'] = false;

            $prop['add_param']['small']['width'] = intval($kernel->pub_httppost_get('pict_small_width'));
            $prop['add_param']['small']['height'] = intval($kernel->pub_httppost_get('pict_small_height'));


            //Теперь обновим и запишим этот массив в mysql
            $query = "UPDATE `".$kernel->pub_prefix_get().$table."` SET `add_param`='".serialize($prop['add_param'])."' WHERE `id`=".$pid;
            $kernel->runSQL($query);
        }
    }

    /**
     * Добавляет поле(свойство) в товарную группу в БД.
     * Создаёт запись в _catalog_item_props и изменяет таблицу _catalog_items_[moduleid]_[groupdbname]
     *
     * @return string БД-имя добавленного свойства
     */
    private function add_prop_in_group()
    {
        global $kernel;
        //Взяли параметры из формы
        $pvalues = $kernel->pub_httppost_get('enum_values', false);
        $pname = $kernel->pub_httppost_get('name_full');
        $pnamedb = $kernel->pub_httppost_get('name_db');
        $group_id = $kernel->pub_httppost_get('group_id');
        $ptype = $kernel->pub_httppost_get('ptype');
        $inlist = $kernel->pub_httppost_get('inlist');
        $sorted = $kernel->pub_httppost_get('sorted');
        $ismain = $kernel->pub_httppost_get('ismain');
        $group_id = intval($group_id);
        if (empty($inlist))
            $inlist = 0;
        else
            $inlist = 1;

        if (empty($ismain))
            $ismain = 0;
        else
            $ismain = 1;
        if (empty($sorted))
            $sorted = 0;
        else
            $sorted = 1;

        //Проверим и проставим значения
        $group = CatalogCommons::get_group($group_id);
        if (mb_strlen($pnamedb) == 0)
            $pnamedb = $pname;
        $namedb = $this->translate_string2db($pnamedb);
        $n = 2;
        $namedb0 = $namedb;
        while ($this->is_prop_exists($group_id, $namedb) || $this->is_prop_exists(0, $namedb))
            $namedb = $namedb0.$n++;

        if (empty($pvalues))
            $values = "NULL";
        else
        {
            $pva = explode("\n", $pvalues);
            $values = array();
            foreach ($pva as $v)
            {
                $v = trim($v);
                if (mb_strlen($v) != 0)
                    $values[] = $v;
            }
            if (count($values) == 0)
                $values = "NULL";
        }

        //узнаем order у последнего св-ва в этой группе и добавим 10
        $gprops = CatalogCommons::get_props($group_id, true);
        $props_count = count($gprops);
        if ($props_count == 0)
            $order = 10;
        else
            $order = $gprops[$props_count - 1]['order'] + 10;
        $moduleid = $kernel->pub_module_id_get();
        if ($ismain == 1)
        { //сбросим `ismain` для остальных полей
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_props` SET `ismain`=0 WHERE `group_id`=0 AND module_id="'.$moduleid.'"';
            $kernel->runSQL($query);
        }
        if ($sorted > 0)
        { //сбросим `sorted` для остальных полей
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_item_props` SET `sorted`=0 WHERE `group_id`=0 AND module_id="'.$moduleid.'"';
            $kernel->runSQL($query);
        }

        if ($ptype == 'pict')
            $add_param = '"'.mysql_real_escape_string(serialize(self::make_default_pict_prop_addparam())).'"';
        else
            $add_param = "NULL";
        //Собственно запросы по добавлению
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_item_props`
                 (`module_id`,`group_id`,`name_db`,`name_full`,`type`, `showinlist`, `sorted`,`order`, `ismain`,`add_param`)
                 VALUES
                 ("'.$kernel->pub_module_id_get().'",'.$group_id.',"'.$namedb.'","'.$pname.'","'.$ptype.'",'.$inlist.','.$sorted.', '.$order.', '.$ismain.','.$add_param.')';
        $kernel->runSQL($query);

        if ($group_id > 0)
        {
            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_items_'.$moduleid.'_'.strtolower($group['name_db']).'` ADD COLUMN `'.$namedb."` ".$this->convert_field_type_2_db($ptype, $values);
            @unlink($kernel->pub_site_root_get()."/modules/catalog/templates_admin/items_search_form_".$group['name_db'].".html");
        }
        else
        { //common-свойство
            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_items`
                      ADD COLUMN `'.$namedb."` ".$this->convert_field_type_2_db($ptype, $values);
            //по-умолчанию добавляем как видимое для всех тов. групп
            $groups = CatalogCommons::get_groups();
            foreach ($groups as $agroup)
            {
                $this->add_group_visible_prop($kernel->pub_module_id_get(), $agroup['id'], $namedb);
            }
        }
        $kernel->runSQL($query);
        /*
        if ($group_id > 0)
            $this->regenerate_group_tpls($group_id, false);
        else
        {
            $this->regenerate_all_groups_tpls(false);
            CatalogCommons::regenerate_frontend_item_common_block($kernel->pub_module_id_get(), false);
        }*/

        if (in_array($ptype, array('string', 'text', 'html', 'number', 'enum')))
        { //тип свойства такой, который используется в шаблоне поиска
            if ($group_id == 0)
            { //добавили общее свойство
                $groups = CatalogCommons::get_groups($moduleid);
                foreach ($groups as $group)
                {
                    $this->generate_search_form($group['id'], array());
                }
            }
            else //добавили свойство группы
                $this->generate_search_form($group_id, array());
        }
        return $namedb;
    }


    /**
     * Добавляет поле(свойство) для категории.
     * Создаёт запись в _catalog_item_props и изменяет таблицу _catalog_items_[moduleid]_[groupdbname]
     *
     * @param string $pname имя свойства
     * @param string $ptype тип свойства
     * @param string $pvalues набор значений для свойства типа "набор значений"
     * @param string $pnamedb БД-имя
     * @return string БД-имя добавленного свойства
     */
    private function add_prop_in_cat($pname, $ptype, $pvalues, $pnamedb)
    {
        global $kernel;
        if (mb_strlen($pnamedb) == 0)
            $pnamedb = $pname;
        $namedb = $this->translate_string2db($pnamedb);
        $n = 1;
        while ($this->is_cat_prop_exists($namedb))
            $namedb .= $n++;
        if (empty($pvalues))
            $values = "NULL";
        else
        {
            $pva = explode("\n", $pvalues);
            $values = array();
            foreach ($pva as $v)
            {
                $v = trim($v);
                if (mb_strlen($v) != 0)
                    $values[] = $v;
            }
            if (count($values) == 0)
                $values = "NULL";
        }
        if ($ptype == 'pict')
            $add_param = '"'.mysql_real_escape_string(serialize(self::make_default_pict_prop_addparam())).'"';
        else
            $add_param = "NULL";
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats_props`
                 (`name_db`,`name_full`,`type`,`add_param`)
                 VALUES
                 ("'.$namedb.'","'.mysql_real_escape_string($pname).'","'.$ptype.'",'.$add_param.')';
        $kernel->runSQL($query);

        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats`
                  ADD COLUMN `'.$namedb."` ".$this->convert_field_type_2_db($ptype, $values);
        $kernel->runSQL($query);
        return $namedb;
    }


    /**
     * Возвращает товары из БД
     *
     * @param integer $offset        смещение
     * @param integer $limit         лимит
     * @param integer $group_id      id-шник товарной группы
     * @param boolean $only_visible  только видимые?
     * @return array
     */
    private function get_items($offset = 0, $limit = 100, $group_id = 0, $only_visible = true)
    {
        global $kernel;
        $where = array();
        if ($group_id > 0)
            $where[] = ' `group_id`='.$group_id;
        if ($only_visible)
            $where[] = ' `available`=1';
        if (count($where) > 0)
            $query = implode(" AND ", $where);
        else
            $query = "true";

        $sort_field = $this->get_common_sort_prop();
        if ($sort_field)
        {
            $query .= ' ORDER BY ISNULL(`'.$sort_field['name_db'].'`)  , `'.$sort_field['name_db'].'` ';
            if ($sort_field['sorted'] == 2)
                $query .= " DESC ";
        }
        if ($limit == 0)
            $limit = null;
        $items = $kernel->db_get_list_simple('_catalog_'.$kernel->pub_module_id_get().'_items', $query, "*", $offset, $limit);
        return $items;
    }

    /**
     * Возвращает товары из БД, не принадлежащие ни к одной категории
     *
     * @param integer $offset        смещение
     * @param integer $limit         лимит
     * @return array
     */
    private function get_items_without_cat($offset = 0, $limit = 100)
    {
        global $kernel;
        $items = array();
        $query = 'SELECT items.* FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` AS items '.
            'LEFT JOIN `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` AS i2c ON i2c.item_id=items.id '.
            'WHERE i2c.item_id IS NULL';
        $sort_field = $this->get_common_sort_prop();
        if ($sort_field)
        {
            $query .= ' ORDER BY ISNULL(items.`'.$sort_field['name_db'].'`),  items.`'.$sort_field['name_db'].'` ';
            if ($sort_field['sorted'] == 2)
                $query .= " DESC ";
        }
        if ($limit != 0)
            $query .= ' LIMIT '.$offset.','.$limit;
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $items[] = $row;
        mysql_free_result($result);
        return $items;
    }

    /**
     * Возвращает кол-во товаров из БД, не принадлежащие ни к одной категории
     *
     * @return integer
     */
    private function get_items_without_cat_count()
    {
        global $kernel;
        $query = 'SELECT count(items.id) as count FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` AS items '.
            'LEFT JOIN `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` AS i2c ON i2c.item_id=items.id '.
            'WHERE i2c.item_id IS NULL';
        $result = $kernel->runSQL($query);
        $total = 0;
        if ($row = mysql_fetch_assoc($result))
            $total = $row['count'];
        mysql_free_result($result);
        return $total;
    }


    /**
     * Возвращает кол-во товаров, если $group_id>0, то входящих в указанную тов. группу
     *
     * @param integer  $group_id      id-шник товарной группы
     * @param boolean $only_visible  только видимые?
     * @return array
     */
    private function get_items_count($group_id = 0, $only_visible = false)
    {
        global $kernel;
        $where = array();
        $query = 'SELECT COUNT(*) AS count FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` AS items';
        if ($only_visible)
            $where[] = 'items.`available`=1';
        if ($group_id > 0)
            $where[] = 'items.`group_id`='.$group_id;
        if (count($where) > 0)
            $query .= ' WHERE '.implode(' AND ', $where);
        $count = 0;
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $count = $row['count'];
        mysql_free_result($result);
        return $count;
    }


    /**
     * Возвращает свойство КАТЕГОРИИ по id-шнику
     *
     * @param integer $id  id-шник свойства
     * @return array
     */
    private function get_cat_prop($id)
    {
        global $kernel;
        $res = false;
        $query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats_props` WHERE `id` ='.$id.' LIMIT 1';
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
        {
            $res = $row;
            //if (isset($res['add_param']))
            //    $res['add_param'] = @unserialize($res['add_param']);
        }
        mysql_free_result($result);
        return $res;
    }

    /**
     * Возвращает свойство по id-шнику
     *
     * @param integer $id id-шник свойства
     * @return array
     */
    private function get_prop($id)
    {
        global $kernel;
        $res = $kernel->db_get_record_simple('_catalog_item_props', 'id='.$id);
        //Если свойство с типом=картинка, то сразу вытащим из дополнительных параметров информацию по картинке
        if ($res['type'] == 'pict')
        {
            if (isset($res['add_param']) && !empty($res['add_param']))
                $res['add_param'] = @unserialize($res['add_param']);
            else
                $res['add_param'] = self::make_default_pict_prop_addparam();
        }
        return $res;
    }

    /**
     * Возвращает запись товара по id-шнику (только common-свойства)
     *
     * @param integer $id id-шник товара
     * @return array
     */
    private function get_item($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_items', '`id` ="'.intval($id).'"');
    }

    /**
     * Возвращает запись товара по id-шнику common-свойства + custom
     *
     * @param integer $id  id-шник товара
     * @return array
     */
    private function get_item_full_data($id = 0)
    {
        $id = intval($id);
        //Сначала получаем общие свойства
        $item1 = $this->get_item($id);
        if (!$item1)
            return false;

        $group = CatalogCommons::get_group($item1['group_id']);
        $commonid = $item1['id'];
        unset($item1['id']);
        $item1['commonid'] = $commonid;

        //теперь добавим custom-поля из тов. группы
        $itemc = $this->get_item_group_fields($item1['ext_id'], $group['name_db']);
        if ($itemc)
            $item1 = $item1 + $itemc;
        return $item1;
    }


    /**
     * Возвращает только custom-поля товара (из таблицы тов. группы) по id-шнику
     *
     * @param integer $id  ext-id-шник товара
     * @param string  $group_name БД-название товарной группы
     * @return array
     */
    private function get_item_group_fields($id, $group_name)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group_name), '`id`='.$id);
    }


    /**
     * Возвращает категорию по id-шнику
     *
     * @param integer $id  id-шник категории
     * @return array
     */
    private function get_category($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_catalog_".$kernel->pub_module_id_get()."_cats", "`id`=".$id);
    }


    /**
     * Проверяет, существует ли товарная группа с указанным именем для текущего модуля
     *
     * @param string $name имя товарной группы
     * @return boolean
     */
    private function is_group_exists($name)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_item_groups', '`name_db` = "'.$name.'"'.' AND `module_id` = "'.$kernel->pub_module_id_get().'"');
    }


    /**
     * Проверяет, существует ли свойство с БД-именем $pname для категорий
     * для текущего модуля
     *
     * @param string $pname имя свойства
     * @return boolean
     */
    private function is_cat_prop_exists($pname)
    {
        $reserved = array('id', 'parent_id', 'is_default', 'order', '_hide_from_waysite', '_items_count', '_subcats_count', 'depth');
        if (in_array($pname, $reserved))
            return true;
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_cats_props', '`name_db` ="'.$pname.'"');
    }

    /**
     * Проверяет, существует ли свойство ($pname) в тов. группе
     * с указанным id ($group_id) для текущего модуля
     *
     * @param string $group_id id-шник товарной группы
     * @param string $pname имя свойства
     * @return boolean
     */
    private function is_prop_exists($group_id, $pname)
    {
        $reservedCommon = array('id', 'module_id', 'group_id', 'available', 'ext_id');
        $reservedCustom = array('id');
        if ($group_id == 0 && in_array($pname, $reservedCommon))
            return true;
        elseif (in_array($pname, $reservedCustom))
            return true;
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_item_props', '`name_db` ="'.$pname.'" AND `group_id`='.$group_id);
    }


    /**
     * Возвращает id-шники товаров, принадлежих к категории
     *
     * @param integer $catid id-шник категории
     * @return array
     */
    private function get_cat_itemids($catid)
    {
        global $kernel;
        $rows = $kernel->db_get_list_simple('_catalog_'.$kernel->pub_module_id_get().'_item2cat', '`cat_id`='.$catid.' ORDER BY `order`', 'item_id');
        $ret = array();
        foreach ($rows as $row)
        {
            $ret[] = $row['item_id'];
        }
        return $ret;
    }


    /**
     * Возвращает id-шники категорий, к которым принадлежит товар
     *
     * @param integer $itemid id-шник товара
     * @return array
     */
    private function get_item_catids($itemid)
    {
        global $kernel;
        $query = 'SELECT `cat_id` FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` WHERE `item_id`='.$itemid;
        $ret = array();
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $ret[] = $row['cat_id'];
        mysql_free_result($result);
        return $ret;
    }

    /**
     * Возвращает id-шники категорий и порядок в них, к которым принадлежит товар
     * в виде массива с элементами [id-шник категории]=>[порядок в категории]
     * используется при сохранении товара (принадлежность к категориям)
     *
     * @param integer $itemid id-шник товара
     * @return array
     */
    private function get_item_catids_with_order($itemid)
    {
        global $kernel;
        $query = 'SELECT `cat_id`, `order` FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` WHERE `item_id`='.$itemid;
        $ret = array();
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $ret[$row['cat_id']] = $row['order'];
        mysql_free_result($result);
        return $ret;
    }

    /**
     * Возвращает товары, принадлежащие к категории
     *
     * @param integer  $catid  id-шник категории
     * @param integer  $offset смещение
     * @param integer  $limit  лимит
     * @param boolean $only_visible  только видимые?
     * @return array
     */
    private function get_cat_items($catid, $offset = 0, $limit = 20, $only_visible = false)
    {
        global $kernel;

        $query = 'SELECT items.*, i2c.`order` FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` AS i2c '.
            ' LEFT JOIN `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` AS items ON items.id=i2c.item_id ';
        $query .= 'WHERE i2c.cat_id='.$catid;

        if ($only_visible)
            $query .= ' AND `available`=1 ';

        $sort_field = $this->get_common_sort_prop();
        if ($sort_field)
        {
            $query .= ' ORDER BY ISNULL(items.`'.$sort_field['name_db'].'`), items.`'.$sort_field['name_db']."` ";
            if ($sort_field['sorted'] == 2)
                $query .= " DESC ";
        }
        else
            $query .= ' ORDER BY i2c.`order` ASC ';
        if ($limit != 0)
            $query .= ' LIMIT '.$offset.','.$limit;
        $ret = array();
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $ret[] = $row;
        mysql_free_result($result);
        return $ret;
    }

    /**
     * Возвращает кол-во товаров, принадлежащих к категории
     *
     * @param integer  $catid  id-шник категории
     * @param boolean $only_visible  только видимые?
     * @return integer
     */
    private function get_cat_items_count($catid, $only_visible = true)
    {
        global $kernel;
        $query = 'SELECT COUNT(*) AS count FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` AS i2c '.
            'LEFT JOIN `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` AS `items` ON i2c.item_id=items.id '.
            'WHERE i2c.cat_id='.$catid;
        if ($only_visible)
            $query .= ' AND items.`available`=1 ';
        $count = 0;
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $count = $row['count'];
        mysql_free_result($result);
        return $count;
    }


    protected function get_categories_tree($node_id, $module_id = false)
    {
        global $kernel;
        if (!$module_id)
            $module_id = $kernel->pub_module_id_get();
        $data = array();
        $rows = CatalogCommons::get_child_cats_with_count($module_id, $node_id, 'cats.id,cats.name', true);
        foreach ($rows as $row)
        {
            $array = array(
                'data' => htmlspecialchars($row['name']).'&nbsp;('.$row['_items_count'].')',
                'attr' => array("id" => $row['id'], 'rel' => 'default'),
            );
            if ($row['_subcats_count'] == 0)
                $array['leaf'] = true;
            else
            {
                $array['children'] = $this->get_categories_tree($row['id'], $module_id);
                ;
                $array['leaf'] = false;
                $array['attr']['rel'] = "folder";
            }
            $data[] = $array;
        }
        return $data;
    }

    /**
     * Проверяет, есть ли категория с указанным id в массиве
     *
     * @param integer $catid
     * @param array   $cats
     * @return integer позицию в массиве если найдена, иначе -1
     */
    private function is_cat_in_array($catid, $cats)
    {
        for ($i = 0; $i < count($cats); $i++)
        {
            if ($cats[$i]['id'] == $catid)
                return $i;
        }
        return -1;
    }


    /**
     * Возвращает вложенные категории
     *
     * @param integer $node_id id-шник родительской категории
     * @param integer $depth текущая глубина
     * @param array   $data массив с данными
     * @param integer $maxdepth максимальная глубина
     * @param string|boolean $module_id
     * @return array
     */
    protected function get_child_categories($node_id, $depth = 0, $data = array(), $maxdepth = 100, $module_id = false)
    {
        global $kernel;
        if ($depth >= $maxdepth)
            return $data;
        $currdepth = $depth++;
        if (!$module_id)
            $module_id = $kernel->pub_module_id_get();
        $rows = CatalogCommons::get_child_cats_with_count($module_id, $node_id, 'cats.*', true);
        foreach ($rows as $row)
        {
            $row['depth'] = $currdepth;
            $data[] = $row;
            if ($row['_subcats_count'] > 0)
                $data = $this->get_child_categories($row['id'], $depth, $data, $maxdepth, $module_id);
        }
        return $data;
    }

    /**
     * Возвращает вложенные категории с учётом кол-ва раскрываемых уровней
     *
     * @param integer $node_id id-шник родительской категории
     * @param integer $depth текущая глубина
     * @param array   $data массив с данными
     * @param integer $maxdepth максимальная глубина
     * @param array   $way дорога пользователя в категориях
     * @param integer $openlevels кол-во раскрываемых уровней
     * @return array
     */
    protected function get_child_categories2($node_id, $depth = 0, $data = array(), $maxdepth = 100, $way, $openlevels)
    {
        global $kernel;
        if ($depth >= $maxdepth)
            return $data;
        $currdepth = $depth++;
        $rows = CatalogCommons::get_child_cats_with_count($kernel->pub_module_id_get(), $node_id, 'cats.*', true);
        foreach ($rows as $row)
        {
            $row['depth'] = $currdepth;
            $data[] = $row;
            $wpos = $this->is_cat_in_array($row['id'], $way);
            if (($wpos >= 0 || $depth < $openlevels) && $row['_subcats_count'] > 0)
                $data = $this->get_child_categories2($row['id'], $depth, $data, $maxdepth, $way, $openlevels);
        }
        return $data;
    }

    /**
     * Конвертирует строку в приемлимую для использования в БД
     *
     * @param string $s строка для конвертирования
     * @return string
     */
    private function translate_string2db($s)
    {
        global $kernel;
        $res = $kernel->pub_translit_string($s);
        $res = preg_replace("/[^0-9a-z_]/i", '', $res);
        return $res;
    }

    /**
     * Конвертирует тип поля для использования в БД
     * @param string $type тип поля
     * @param array $values значения (для enum)
     * @return string
     */
    private function convert_field_type_2_db($type, $values = null)
    {
        switch ($type)
        {
            case 'text':
                return 'text';
            case 'set':
                $arr = array();
                foreach ($values as $val)
                    $arr[] = "'".mysql_real_escape_string(str_replace(',', '', $val))."'";
                return 'set ('.implode(',', array_unique($arr)).')';
            case 'html':
                return 'text';
            case 'file':
            case 'pict':
            case 'string':
                return 'varchar (255)';
            case 'date':
                return 'date';
            case 'enum':
                $arr = array();
                foreach ($values as $val)
                    $arr[] = "'".mysql_real_escape_string($val)."'";
                return 'enum ('.implode(',', array_unique($arr)).')';
            case 'number':
                return 'decimal(12,2)';
            default:
                return 'text';
        }
    }


    /**
     * Выводит список товаров в админке
     * Сюда попадают все товары магазина. Есть возможность в форме устаноить фильтр по группе, к которой принадлежит товар
     * @param integer $group_id IDшник группы
     * @return string
     */
    private function show_items($group_id)
    {
        global $kernel;
        //Получим все необхоимые данные
        $offset = $this->get_offset_admin();
        $limit = $this->get_limit_admin();
        $groups = CatalogCommons::get_groups();
        //$purl='show_items&group_id='.$group_id.'&'.$this->admin_param_offset_name.'=';
        $purl = 'show_items';
        if (isset($_GET['search_results']) && $kernel->pub_session_get("search_items_query"))
        {
            //пока без ограничения
            $offset = 0;
            $limit = 10000;
            $squery = $kernel->pub_session_get("search_items_query");
            $result = $kernel->runSQL($squery);
            $items = array();
            while ($row = mysql_fetch_assoc($result))
                $items[] = $row;
            mysql_free_result($result);
            $total = count($items);
            $header_label = $kernel->pub_page_textlabel_replace("[#catalog_items_all_list_search_results_mainlabel#]");
            $purl .= '&search_results=1&group_id='.$group_id;
        }
        else
        {
            $kernel->pub_session_unset("search_items_query");
            if ($group_id == -1) //показываем товары без категории
            {
                $header_label = $kernel->pub_page_textlabel_replace("[#catalog_items_all_list_filter_mainlabel#]");
                $items = $this->get_items_without_cat($offset, $limit);
                $total = $this->get_items_without_cat_count();
            }
            else
            { //groupid >= 0
                $purl .= '&group_id='.$group_id;
                if (count($groups) == 1)
                { //если у нас только одна тов. группа, её и выберем
                    $groupsTmp = $groups;
                    $firstGroup = array_shift($groupsTmp);
                    $group_id = $firstGroup['id'];
                }

                $header_label = $kernel->pub_page_textlabel_replace("[#catalog_items_all_list_filter_mainlabel#]");
                $total = $this->get_items_count($group_id, false);
                $items = $this->get_items($offset, $limit, $group_id, false);
            }
        }
        $count = count($items);
        $purl .= '&'.$this->admin_param_offset_name.'=';
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'items_list.html'));
        $content = '';
        // Сформируем список доступных товарных групп
        if (count($groups) > 0)
        {
            $content .= $this->get_template_block('header');
            $groups_vals = $this->get_template_block('group_value');
            $groups_vals = str_replace('%group_id%', 0, $groups_vals);
            $groups_vals = str_replace('%group_name%', '[#catalog_items_all_list_filter_all_group#]', $groups_vals);
            if ($group_id == 0)
                $groups_vals = str_replace('%gselected%', "selected", $groups_vals);
            else
                $groups_vals = str_replace('%gselected%', "", $groups_vals);

            $groups_vals .= $this->get_template_block('group_value');
            $groups_vals = str_replace('%group_id%', -1, $groups_vals);
            $groups_vals = str_replace('%group_name%', '[#catalog_items_all_list_filter_no_cat#]', $groups_vals);
            if ($group_id == -1)
                $groups_vals = str_replace('%gselected%', "selected", $groups_vals);
            else
                $groups_vals = str_replace('%gselected%', "", $groups_vals);
            foreach ($groups as $group)
            {
                $option = $this->get_template_block('group_value');
                $option = str_replace('%group_id%', $group['id'], $option);
                $option = str_replace('%group_name%', $group['name_full'], $option);
                if ($group_id == $group['id'])
                    $option = str_replace('%gselected%', 'selected', $option);
                else
                    $option = str_replace('%gselected%', '', $option);
                $groups_vals .= $option;
            }
            $content = str_replace('%group_values%', $groups_vals, $content);
        }

        $search_form = "";
        $search_link_display = "none";
        if ($group_id > 0)
        {
            $curr_group = $groups[$group_id];
            $group_form_filename = "modules/catalog/templates_admin/items_search_form_".$curr_group['name_db'].".html";
            if (file_exists($group_form_filename))
                $search_form = file_get_contents($group_form_filename);
            else
            {
                $search_form = $this->generate_search_form($curr_group['id'], array());
                //заново установим шаблон
                $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'items_list.html'));
                $search_form = str_replace("%search_form_action%", $kernel->pub_redirect_for_form('show_items'), $search_form);
                $search_form = str_replace("%groupid%", $curr_group['id'], $search_form);
                $kernel->pub_file_save($group_form_filename, $search_form);
            }
            $search_link_display = "block";
        }

        $content = str_replace('%search_form%', $search_form, $content);
        $content = str_replace('%search_link_display%', $search_link_display, $content);
        $content = str_replace('%header_label%', $header_label, $content);

        //Теперь будем выводить товары, если надо
        if ($count == 0)
            $content .= $this->get_template_block('no_data');
        else
        {
            $content .= $this->get_template_block('table_header');
            $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('save_selected_items'), $content);
            $cprops = CatalogCommons::get_common_props($kernel->pub_module_id_get(), true);
            $cfields_block = '';
            foreach ($cprops as $cprop)
            {
                $block = $this->get_template_block('list_prop_name');
                $block = str_replace('%list_prop_name%', $cprop['name_full'], $block);
                $cfields_block .= $block;
            }
            $content = str_replace('%list_prop_names%', $cfields_block, $content);
            $num = $offset + 1;
            $common_table_info = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_items');
            $enum_props_cache = array();
            foreach ($items as $item)
            {
                $line = $this->get_template_block('table_body');
                $cvals = '';
                foreach ($cprops as $cprop)
                {
                    $prop_value = '';
                    if ($cprop['type'] == 'number' || $cprop['type'] == "string")
                    {
                        $prop_line = $this->get_template_block('list_prop_value_edit');
                        $prop_value = htmlspecialchars($item[$cprop['name_db']]);
                    }
                    elseif ($cprop['type'] == 'enum')
                    {
                        $prop_line = $this->get_template_block('list_prop_value_select_edit');
                        $cache_key = $cprop['name_db'];
                        if (isset($enum_props_cache[$cache_key]))
                            $enum_props = $enum_props_cache[$cache_key];
                        else
                        {
                            $enum_props = $this->get_enum_set_prop_values($common_table_info[$cprop['name_db']]['Type']);
                            $enum_props_cache[$cache_key] = $enum_props;
                        }
                        $optlines = '';
                        foreach ($enum_props as $enum_option)
                        {
                            if ($item[$cprop['name_db']] == $enum_option)
                                $optline = $this->get_template_block('list_prop_value_select_option_selected_edit');
                            else
                                $optline = $this->get_template_block('list_prop_value_select_option_edit');

                            $optline = str_replace('%option_value%', $enum_option, $optline);
                            $optline = str_replace('%option_value_escaped%', htmlspecialchars($enum_option), $optline);
                            $optlines .= $optline;
                        }
                        $prop_line = str_replace('%options%', $optlines, $prop_line);
                    }
                    else
                    {
                        $prop_line = $this->get_template_block('list_prop_value');
                        $prop_value = $item[$cprop['name_db']];
                        if ($cprop['type'] == 'pict' && !empty($prop_value))
                        {
                            $path_parts = pathinfo($prop_value);
                            $path_small = $path_parts['dirname'].'/tn/'.$path_parts['basename'];
                            if (file_exists($path_small))
                                $prop_value = "<img src='/".$path_small."' width=50 />";
                        }

                    }
                    $prop_line = str_replace("%name_db%", $cprop['name_db'], $prop_line);
                    $prop_line = str_replace('%list_prop_value%', $prop_value, $prop_line);
                    $cvals .= $prop_line;
                }
                $line = str_replace('%list_prop_values%', $cvals, $line);
                $line = str_replace('%id%', $item['id'], $line);
                $line = str_replace('%group%', $groups[$item['group_id']]['name_full'], $line);

                $line = str_replace('%number%', $num++, $line);
                $content .= $line;
            }
            $content .= $this->get_template_block('table_footer');

            $cats = $this->get_child_categories(0);
            $cat_lines = "";
            foreach ($cats as $cat)
            {
                $cat_line = $this->get_template_block('category_value');
                $cat_line = str_replace("%shift%", str_repeat("&nbsp;&nbsp;", $cat['depth']), $cat_line);
                $cat_line = str_replace("%category_id%", $cat['id'], $cat_line);
                $cat_line = str_replace("%category_name%", $cat['name'], $cat_line);
                $cat_lines .= $cat_line;
            }
            $content = str_replace("%category_values%", $cat_lines, $content);
            $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit, $purl, 0, 'url'), $content);
        }

        if (count($groups) > 0)
        {
            $content .= $this->get_template_block('addform');
            $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('item_add'), $content);
            $groups_vals = '';
            $last_add_item_groupid = 0;
            if (isset($_COOKIE['last_add_item_groupid']))
                $last_add_item_groupid = $_COOKIE['last_add_item_groupid'];
            foreach ($groups as $group)
            {
                $option = $this->get_template_block('group_value');
                $option = str_replace('%group_id%', $group['id'], $option);
                $option = str_replace('%group_name%', $group['name_full'], $option);
                if ($last_add_item_groupid == $group['id'])
                    $option = str_replace('%gselected%', 'selected', $option);
                else
                    $option = str_replace('%gselected%', '', $option);
                $groups_vals .= $option;
            }
            $content = str_replace('%group_values%', $groups_vals, $content);
        }
        else
            $content .= $this->get_template_block('no_groups');

        $content = str_replace('%group_id%', $group_id, $content);
        $content = str_replace('%redir2%', urlencode($purl.$offset), $content);
        return $content;
    }


    /**
     * Выводит список внутренних фильтров в админке
     *
     * @return string
     * /
     */
    private function show_inner_filters()
    {
        global $kernel;
        $filters = CatalogCommons::get_inner_filters();
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'inner_filters.html'));
        $html = $this->get_template_block('header');
        $content = '';
        if (count($filters) > 0)
        {
            $content .= $this->get_template_block('table_header');
            //$content     = str_replace('%form_action%', $kernel->pub_redirect_for_form('regen_tpls4groups'), $content);
            foreach ($filters as $filter)
            {
                $line = $this->get_template_block('table_body');
                $line = str_replace('%name%', $filter['name'], $line);
                $line = str_replace('%stringid%', $filter['stringid'], $line);
                $line = str_replace('%id%', $filter['id'], $line);
                $content .= $line;
            }
            $content .= $this->get_template_block('table_footer');
            //$content = str_replace("%form_action%", $kernel->pub_redirect_for_form('regen_tpl4itemlist'), $content);
        }
        else
            $content = $this->get_template_block('no_data');

        // Теперь заменим в итоговой форме переменную табличкой
        $html = str_replace("%table%", $content, $html);

        $groups = CatalogCommons::get_groups();
        $group_lines = '';
        foreach ($groups as $group)
        {
            $line = $this->get_template_block('group_item');
            $line = str_replace('%name%', $group['name_full'], $line);
            $line = str_replace('%id%', $group['id'], $line);
            $group_lines .= $line;
        }
        $html = str_replace("%group_items%", $group_lines, $html);
        $html = str_replace("%gen_form_action%", $kernel->pub_redirect_for_form('show_gen_search_form'), $html);
        return $html;
    }


    /**
     * Выводит список товарных групп в админке
     *
     * @return string
     * /
     */
    private function show_groups()
    {
        global $kernel;
        $groups = CatalogCommons::get_groups(null, true);
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'groups_list.html'));

        $html = $this->get_template_block('header');
        $content = '';
        if (count($groups) > 0)
        {
            $content .= $this->get_template_block('table_header');
            $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('regen_tpls4groups'), $content);
            $num = 1;
            foreach ($groups as $group)
            {
                $line = $this->get_template_block('table_body');
                if ($group['_items_count'] == 0)
                    $delete_block = $this->get_template_block('delete_block');
                else
                    $delete_block = '';
                $line = str_replace('%delete_block%', $delete_block, $line);
                $line = $kernel->pub_array_key_2_value($line, $group);
                $line = str_replace('%number%', $num++, $line);
                $content .= $line;
            }
            $content .= $this->get_template_block('table_footer');
            $content = str_replace("%form_action%", $kernel->pub_redirect_for_form('regen_tpl4itemlist'), $content);
        }
        else
            $content = $this->get_template_block('no_data');

        // Теперь заменим в итоговой форме переменную табличкой с существующими группами (если они есть)
        $html = str_replace("%table%", $content, $html);

        return $html;
    }

    /**
     *    Форма редактирования товара в админке
     *
     * @param integer $id       - idшник товара
     * @param integer $group_id - idшник шруппы, если идёт добавление нового товара
     * @param integer $id_cat   - При ID = 0, сюда может передаваться ID категории, в которой создаётся товар
     * @return string
     */
    private function show_item_form($id = 0, $group_id = 0, $id_cat = 0)
    {
        global $kernel;
        //Получим все необходимые данные
        if ($id > 0)
        {
            $item = $this->get_item_full_data($id);
            $group = CatalogCommons::get_group($item['group_id']);
            $item_catids = $this->get_item_catids($id);
        }
        else
        {
            //Новый товар
            $item = array();
            $item['available'] = 1;
            $item['id'] = 0;
            $group = CatalogCommons::get_group($group_id);
            $item_catids = explode(",", $group['defcatids']);
        }
        $moduleid = $kernel->pub_module_id_get();
        $tinfo = $kernel->db_get_table_info('_catalog_items_'.$moduleid.'_'.$group['name_db']);
        $tinfo = $tinfo + $kernel->db_get_table_info('_catalog_'.$moduleid.'_items');
        $props = CatalogCommons::get_props($group['id'], true);
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'items_edit.html'));
        //Произведём первичную сортировку массива со свойствами, чтобы шаблон был
        //оптимизирован изначально. В дальнейшем пользователь его самостоятельно поменяет
        //Получим свойства, которые
        $sort_def = array();
        $sort_def['string'] = '01';
        $sort_def['enum'] = '02';
        $sort_def['number'] = '03';
        $sort_def['pict'] = '04';
        $sort_def['text'] = '05';
        $sort_def['html'] = '06';
        $sort_def['file'] = '07';
        $sort_def['date'] = '08';
        $sort_def['set'] = '09';

        $visible_props = $this->get_group_visible_props($group['id']);
        //Прежде всего сформируем массив свойств, преобразованных для HTML вывода
        $lines = array();
        foreach ($props as $prop)
        {
            if ($prop['group_id'] == 0 && !array_key_exists($prop['name_db'], $visible_props))
                continue;
            $template_line = $this->get_template_block('prop_'.$prop['type']);
            //Запишим значения заменяемых переменных
            //для большенства свойств
            $pname_db = $prop['name_db'];
            $pname_full = $prop['name_full'];
            $origValue = isset($item[$pname_db]) ? $item[$pname_db] : "";
            $pvalue = htmlspecialchars($origValue);

            $need_add_water_marka = "";
            //Теперь, для более сложных свойств нужно сделать чуть больше
            //и возможно изменить переменную $pvalue а может и $template_line

            switch ($prop['type'])
            {
                case 'enum':
                    //Получили значения для перечечления
                    $enum_vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
                    $enum_options = array();
                    $not_selected_lang_var = $kernel->priv_page_textlabels_replace("[#catalog_prop_type_enum_notselect#]");
                    foreach ($enum_vals as $enum_val)
                    {
                        if (isset($item[$prop['name_db']]) && $item[$prop['name_db']] == $enum_val)
                            $enum_templ = $this->get_template_block('prop_enum_value_selected');
                        else
                            $enum_templ = $this->get_template_block('prop_enum_value');
                        if ($enum_val != $not_selected_lang_var)
                            $enum_templ = str_replace("%enum_key%", $enum_val, $enum_templ);
                        else
                            $enum_templ = str_replace("%enum_key%", "", $enum_templ);
                        $enum_templ = str_replace("%enum_val%", $enum_val, $enum_templ);
                        $enum_options[] = $enum_templ;
                    }
                    $pvalue = join("", $enum_options);
                    break;
                case 'set':
                    //Получили значения для перечечления
                    $enum_vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
                    $enum_options = array();
                    $currValues = explode(",", $origValue);
                    foreach ($enum_vals as $enum_val)
                    {
                        if (in_array($enum_val, $currValues))
                            $enum_templ = $this->get_template_block('prop_set_value_checked');
                        else
                            $enum_templ = $this->get_template_block('prop_set_value');
                        $enum_templ = str_replace("%value%", $enum_val, $enum_templ);
                        $enum_templ = str_replace("%value_escaped%", htmlspecialchars($enum_val), $enum_templ);
                        $enum_options[] = $enum_templ;
                    }
                    $pvalue = join("", $enum_options);
                    break;
                case 'html':
                    //Создадим редактор контента
                    $editor = new edit_content();
                    $editor->set_edit_name($prop['name_db']);
                    $editor->set_simple_theme(true);
                    if (isset($item[$prop['name_db']]))
                        $editor->set_content($item[$prop['name_db']]);
                    $pvalue = $editor->create();
                    break;
                case 'number':
                    if (isset($item[$prop['name_db']]))
                        $pvalue = $this->cleanup_number($item[$prop['name_db']]);
                    break;
                case 'file':
                case 'pict':
                    if ($pvalue)
                    { //Изменения нужно вносить только в том случае, если есть какой-то загруженный файл
                        $template_line = $this->get_template_block('prop_'.$prop['type'].'_edit');
                        $pvalue = "/".$item[$prop['name_db']];
                        //Нужно проставить id свойсвта, что бы можно было удалить файл
                        $template_line = str_replace('%id%', $id, $template_line);
                        //Для файлов картинок нужно вставить вопросы о необходимости
                        //добавлять водяной знак к исходной картинке, или большой картинке
                    }
                    //Если это картинка и стоит запрос на добавление вопроса о водяном знаки - добавим его
                    if ($prop['type'] == 'pict' && isset($prop['add_param']))
                    {
                        $prop['add_param'] = unserialize($prop['add_param']);
                        if (isset($prop['add_param']['source']['water_add']) && (intval($prop['add_param']['source']['water_add']) == 2))
                            $need_add_water_marka .= $this->get_template_block('prop_'.$prop['type'].'_marka_source');

                        if (isset($prop['add_param']['big']['water_add']) && (intval($prop['add_param']['big']['water_add']) == 2))
                            $need_add_water_marka .= $this->get_template_block('prop_'.$prop['type'].'_marka_big');
                    }
                    break;

                case 'date':
                    if (!empty($pvalue))
                        $tvalue = strtotime($pvalue);
                    else
                        $tvalue = time();
                    $pvalue = date("d.m.Y", $tvalue);
                    break;
            }

            //сначало это, так как тут у нас есть важные параметры
            $template_line = str_replace('%need_add_water_marka%', $need_add_water_marka, $template_line);

            //Всё прошли, теперь просто заменим
            $template_line = str_replace('%prop_value%', $pvalue, $template_line);
            $template_line = str_replace('%prop_name_full%', $pname_full, $template_line);
            $template_line = str_replace('%prop_name_db%', $pname_db, $template_line);
            $lines[$sort_def[$prop['type']].'_'.$prop['id']] = $template_line;
        }
        //Отсортируем свойства пока в автоматическом режиме
        //ksort($lines); // временно отключено
        //Проставим чередование строк только после сортировки
        $num = 1;
        foreach ($lines as $key => $val)
        {
            $lines[$key] = str_replace("%class%", $kernel->pub_table_tr_class($num), $val);
            $num++;
        }
        //Начнём строить итоговоую форму
        $content = $this->get_template_block('form');
        $linked_block = "";
        if ($id > 0)
        { //связанные товары только при редактировании
            $main_prop = $this->get_common_main_prop();
            $linked_vals = "";
            if ($main_prop)
            {
                $linked_items = $this->get_linked_items($id);
                foreach ($linked_items as $litem)
                {
                    $linked_val = $this->get_template_block('linked_item');
                    $linked_val = str_replace("%lid%", $litem['id'], $linked_val);
                    $linked_val = str_replace("%namestring%", htmlspecialchars($litem[$main_prop]), $linked_val);
                    $linked_vals .= $linked_val;
                }
                $linked_data = $this->get_template_block('linked_search_block');
            }
            else
                $linked_data = $this->get_template_block('linked_no_main_prop_block');
            $linked_block = $this->get_template_block('linked');
            $linked_block = str_replace("%linked_data%", $linked_data, $linked_block);
            $linked_block = str_replace("%linked_items%", $linked_vals, $linked_block);
        }
        $content = str_replace("%linked%", $linked_block, $content);
        //Отметка о том, включён товар или нет
        if ($item['available'] == 1)
            $content = str_replace('%isavalchecked%', 'checked', $content);
        else
            $content = str_replace('%isavalchecked%', '', $content);
        $content = str_replace("%group.name%", $group['name_full'], $content);
        $checkedIDs = $item_catids;
        $checkedIDs[] = $id_cat;

        $all_cats = CatalogCommons::get_all_categories($moduleid);
        $opened_cats = array();
        foreach ($checkedIDs as $chid)
        {
            $way = $this->get_way2cat($chid, true, $all_cats);
            foreach ($way as $wel)
            {
                $opened_cats[$wel['id']] = true;
            }
        }
        $opened_cats = array_keys($opened_cats);
        $catsblock = $this->build_item_categories_block(0, $checkedIDs, $opened_cats);
        $form_action = $kernel->pub_redirect_for_form('item_save');
        $redir2 = $kernel->pub_httpget_get('redir2');
        if (!$redir2)
            $redir2 = 'show_items';
        $content = str_replace('%props%', implode("\n", $lines), $content);
        $content = str_replace('%categories%', $catsblock, $content);
        $content = str_replace('%form_action%', $form_action, $content);
        $content = str_replace('%id%', $id, $content);
        $content = str_replace('%group_id%', $group_id, $content);
        $content = str_replace('%redir2%', urlencode($redir2), $content);
        return $content;
    }


    private function build_item_categories_block($pid, array $checkedIDs = array(), $opened_cats = array())
    {
        global $kernel;
        $moduleid = $kernel->pub_module_id_get();
        $prfx = $kernel->pub_prefix_get();
        $sql = 'SELECT cats.id,cats.name, COUNT(subcats.id) AS _subcats_count FROM `'.$prfx.'_catalog_'.$moduleid.'_cats` AS cats
                LEFT JOIN '.$prfx.'_catalog_'.$moduleid.'_cats AS subcats ON subcats.parent_id=cats.id
                WHERE cats.`parent_id` = '.$pid.'
                GROUP BY cats.id
                ORDER BY cats.`order`';
        $cats = $kernel->db_get_list($sql);
        $categories = array();
        foreach ($cats as $cat)
        {
            $opened = in_array($cat['id'], $opened_cats);
            if ($cat['_subcats_count'] == 0 || $opened)
                $bname = 'category_line_no_childs';
            else
                $bname = 'category_line';
            if (in_array($cat['id'], $checkedIDs))
                $bname .= '_checked';

            $catline = $this->get_template_block($bname);
            if ($opened && $cat['_subcats_count'] > 0)
            {
                $blockstart = $this->get_template_block('subcats_block_start');
                $blockstart = str_replace("%id%", $cat['id'], $blockstart);
                $placeholder = $blockstart.$this->build_item_categories_block($cat['id'], $checkedIDs, $opened_cats).$this->get_template_block('subcats_block_end');
            }
            else
                $placeholder = '';
            $catline = str_replace("%placeholder%", $placeholder, $catline);
            $catline = str_replace("%id%", $cat["id"], $catline);
            $catline = str_replace("%name%", $cat["name"], $catline);
            $categories[] = $catline;
        }
        return implode("\n", $categories);
    }

    /**
     * Возвращает связанные товары
     *
     * @param integer $itemid
     * @param boolean $only_visible только видимые?
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    private function get_linked_items($itemid, $only_visible = false, $offset = 0, $limit = 0)
    {
        global $kernel;
        $return = array();

        $query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` WHERE ('.
            'id IN (SELECT itemid2 FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items_links` WHERE itemid1='.$itemid.') OR '.
            'id IN (SELECT itemid1 FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items_links` WHERE itemid2='.$itemid.') )';

        if ($only_visible)
            $query .= " AND `available`=1";
        $sort_field = $this->get_common_sort_prop();
        if ($sort_field)
        {
            $query .= ' ORDER BY ISNULL(`'.$sort_field['name_db'].'`), `'.$sort_field['name_db']."` ";
            if ($sort_field['sorted'] == 2)
                $query .= " DESC ";
        }
        if ($limit > 0)
            $query .= " LIMIT ".$offset.",".$limit;
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $return[$row['id']] = $row;
        mysql_free_result($result);
        return $return;
    }

    /**
     * Возвращает количество связанных товаров
     *
     * @param integer $itemid
     * @param boolean $only_visible только видимые?
     * @return integer
     */
    private function get_linked_items_count($itemid, $only_visible = false)
    {
        global $kernel;
        $count = 0;
        $query = 'SELECT COUNT(*) AS count FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` WHERE ('.
            'id IN (SELECT itemid2 FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items_links` WHERE itemid1='.$itemid.') OR '.
            'id IN (SELECT itemid1 FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items_links` WHERE itemid2='.$itemid.') )';

        if ($only_visible)
            $query .= " AND `available`=1";

        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $count = $row['count'];
        mysql_free_result($result);
        return $count;
    }

    /**
     *    Выводит свойства категорий в админке
     *
     * @return string
     */
    private function show_cat_props()
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'category_props.html'));
        $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_cats');

        $props = CatalogCommons::get_cats_props();
        $content = $this->get_template_block('header');
        if (count($props))
        {
            $content .= $this->get_template_block('table_header');
            $num = 1;
            foreach ($props as $prop)
            {
                if ($prop['type'] == 'enum')
                {
                    $line = $this->get_template_block('property_enum');
                    $property_enum_values = array();
                    $enum_vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
                    foreach ($enum_vals as $ev)
                    {
                        $property_enum_values[] = str_replace("%val%", $ev, $this->get_template_block('property_enum_value'));
                    }
                    $line = str_replace('%property_enum_values%', implode($this->get_template_block('property_enum_sep'), $property_enum_values), $line);
                }
                else
                    $line = $this->get_template_block('property');
                $line = str_replace('%property_name%', $prop['name_full'], $line);
                $line = str_replace('%property_dbname%', $prop['name_db'], $line);
                $line = str_replace('%num%', $num, $line);
                $line = str_replace('%property_type%', "[#catalog_prop_type_".$prop['type']."#]", $line);

                if ($prop['name_db'] == 'name')
                    $line = str_replace('%actions%', '', $line);
                else
                {
                    $actions = $this->get_template_block('property_del_link').$this->get_template_block('property_edit_link');
                    $actions = str_replace('%property_id%', $prop['id'], $actions);
                    $actions = str_replace('%property_name%', $prop['name_full'], $actions);
                    $line = str_replace('%actions%', $actions, $line);
                }
                $content .= $line;
                $num++;
            }
            $content .= $this->get_template_block('table_footer');
        }
        else
            $content .= $this->get_template_block('no_props');
        $content .= $this->get_template_block('add_prop_form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('prop_add'), $content);
        return $content;
    }

    /**
     * Возвращает видимые общие свойства для группы
     *
     * @param integer $groupid
     * @return array
     */
    private function get_group_visible_props($groupid)
    {
        global $kernel;
        $items = array();
        $query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_catalog_visible_gprops` WHERE group_id='.$groupid.
            " AND `module_id`='".$kernel->pub_module_id_get()."'";
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $items[$row['prop']] = $row;
        mysql_free_result($result);
        return $items;
    }

    /**
     *    Выводит свойства товарной группы в админке
     *   в вывод попадают все свойства товарной группы, как общие так и не общие
     *
     * @param $id integer - idшник товарной группы (= 0 для общих свойств)
     * @return string
     */
    private function show_group_props($id)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'group_props_list.html'));

        $html = $this->get_template_block('header');
        $html = str_replace('%form_action%', $kernel->pub_redirect_for_form('save_gprops_order'), $html);
        $html = str_replace('%gid%', $id, $html);
        $content = '';

        //При вызове из общих свойстов $id=0, и значит мы покажем только общие свойтва
        $tinfo_global = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_items');
        if ($id == 0)
        {
            //Если формируется только общий список свойств
            $html = str_replace('%label_table%', $this->get_template_block('label_table_global'), $html);
            $gvisprops = array();
            $tinfo_group = array();
        }
        else
        {
            //Список свойств формируется из категории
            $group = CatalogCommons::get_group($id);
            $tinfo_group = $kernel->db_get_table_info('_catalog_items_'.$kernel->pub_module_id_get().'_'.$group['name_db']);
            $html = str_replace('%label_table%', $this->get_template_block('label_table_group'), $html);
            $html = str_replace('%name%', $group['name_full'], $html);
            $gvisprops = $this->get_group_visible_props($id);
        }
        //Возьмём все свойства, общие и не общие
        $props = CatalogCommons::get_props($id, true);
        if (count($props))
        {
            $content .= $this->get_template_block('table_header');
            $num = 1;
            foreach ($props as $prop)
            {
                //Проверим, общее это свойство или нет и поменяем заголовки и параметры таблицы
                if (intval($prop['group_id']) > 0)
                {
                    $is_global = $this->get_template_block('property_is_no_global');
                    $tinfo = $tinfo_group;
                }
                else
                {
                    $is_global = $this->get_template_block('property_is_global');
                    $tinfo = $tinfo_global;
                }


                if ($prop['type'] == 'enum' || $prop['type'] == 'set')
                { //для ENUM и SET - спецобработка
                    $line = $this->get_template_block('property_'.$prop['type']);
                    $property_enum_values = array();
                    $enum_vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
                    foreach ($enum_vals as $ev)
                    {
                        $property_enum_values[] = str_replace("%val%", $ev, $this->get_template_block('property_'.$prop['type'].'_value'));
                    }
                    $line = str_replace('%property_'.$prop['type'].'_values%', implode($this->get_template_block('property_'.$prop['type'].'_sep'), $property_enum_values), $line);
                }
                else
                    $line = $this->get_template_block('property');

                //Добавим возможные действия
                $actions = $this->get_template_block('property_del_link').$this->get_template_block('property_edit_link');
                $actions = str_replace('%property_id%', $prop['id'], $actions);
                $actions = str_replace('%property_name%', $prop['name_full'], $actions);
                $actions = str_replace('%groupid%', $prop['group_id'], $actions);
                //Кроме того добавим id именно той группы, для готорой мы всё этос строим
                //что бы было понятно куда возвращаться после того как было вызвано редактирование
                //или удаление свойства.
                $actions = str_replace('%idg_control%', $id, $actions);


                if ($prop['group_id'] == 0 && $id != 0)
                { //выводим чекбокс только для общих свойств и при редактировании какой-либо товарной группы
                    $viscb = $this->get_template_block('visible_in_group_checkbox');
                    if (array_key_exists($prop['name_db'], $gvisprops))
                        $viscb = str_replace("%grprop_checked%", " checked", $viscb);
                    else
                        $viscb = str_replace("%grprop_checked%", " ", $viscb);
                    $viscb = str_replace("%prop%", $prop['name_db'], $viscb);
                    $line = str_replace('%visible_in_group_checkbox%', $viscb, $line);
                }
                else
                    $line = str_replace('%visible_in_group_checkbox%', '', $line);

                $line = str_replace('%property_name%', $prop['name_full'], $line);
                $line = str_replace('%property_dbname%', $prop['name_db'], $line);
                $line = str_replace('%property_global%', $is_global, $line);
                $line = str_replace('%num%', $num, $line);
                $line = str_replace('%property_type%', "[#catalog_prop_type_".$prop['type']."#]", $line);
                $line = str_replace('%actions%', $actions, $line);
                $line = str_replace('%order%', $prop['order'], $line);
                $line = str_replace('%property_id%', $prop['id'], $line);

                $content .= $line;
                $num++;
            }
            $content .= $this->get_template_block('table_footer');
        }
        else
            $content .= $this->get_template_block('no_props');

        $html = str_replace('%table%', $content, $html);
        $html = str_replace('%groupid%', $id, $html);
        return $html;
    }

    /**
     * Возвращает список темплейтов в папке
     *
     * @param string $path
     * @param boolean $hide_not_selected спрятать "не выбрано"?
     * @return array
     */
    private function get_template_file($path = 'modules/catalog/templates_user', $hide_not_selected = false)
    {
        $array_select = array();
        $exts = array('html', 'htm');
        $d = dir($path);
        while ($entry = $d->read())
        {
            $link = $path.'/'.$entry;
            if (is_file($link))
            {
                if (!empty($exts))
                {
                    $ext = pathinfo($entry, PATHINFO_EXTENSION);
                    if (!in_array($ext, $exts))
                        continue;
                }
                $array_select[$entry] = $entry;
            }
        }
        $d->close();
        if (!$hide_not_selected)
            $array_select[''] = '[#label_properties_no_select_option#]';
        ksort($array_select);
        return $array_select;
    }


    /**
     * Конвертирует строку запроса для внутреннего фильтра в нормальный SQL
     * добавляет LEFT JOIN'ы при необходимости, учитывает категории
     * @param array $filter - DB-запись о фильтре
     * @param array $group - DB-запись о тов. группе
     * @return string
     */
    private function convert_inner_filter_query2sql($filter, $group)
    {
        global $kernel;
        $query = $filter['query'];
        //чтобы спрятать в админке
        $query = preg_replace("/REMOVE_NOT_SET\\[(.+)\\]/sU", " $1 ", $query);
        if (stripos($query, "LIKE") !== false)
        { //LIKE [ресивер] меняем на LIKE '%ресивер%'
            $query = preg_replace("/LIKE(?:\\s*)\\[(.+)\\]/iU", "LIKE  '%$1%'", $query);
        }

        //[string] меняем на 'string'
        $query = preg_replace("/\\[(.+)\\]/iU", "'$1'", $query);

        //ORDERASC (field) меняем на ORDER BY `field` ASC
        //ORDERDESC (field) меняем на ORDER BY `field` DESC
        $pattern = "/ORDER(ASC|DESC)(?:\\s*)\\(([a-z0-9_\\.]+)\\)/iU";
        $query = preg_replace($pattern, "ORDER BY $2 $1", $query);

        //GROUP (field) меняем на GROUP BY `field`
        $query = preg_replace("/GROUP(?:\\s*)\\((.+)\\)/iU", "GROUP BY `$1`", $query);


        //если были ORDER BY, GROUP BY, надо перед первым поставить закрывающую скобку и запомнить
        $pattern = "/(ORDER\\s+BY)|(GROUP\\s+BY)/iU";
        $closed = false;
        $matches = false;
        if (preg_match($pattern, $query, $matches))
        {
            $query = str_replace($matches[0], ") ".$matches[0], $query);
            $closed = true;
        }

        $cprops = CatalogCommons::get_props2(0);
        $cfields = "items.id ";
        if (count($cprops) > 0)
            $cfields .= ", items.".implode(", items.", array_keys($cprops));
        $moduleid = $kernel->pub_module_id_get();
        if ($group)
        { //фильтр по товарной группе
            $gprops = CatalogCommons::get_props2($group['id']);

            $gfields = "";
            if (count($gprops) > 0)
                $gfields = ", ".$group['name_db'].".".implode(", ".$group['name_db'].".", array_keys($gprops));

            $queryPrefix = "SELECT ".$cfields.$gfields." FROM ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_items AS items ".
                "LEFT JOIN ".$kernel->pub_prefix_get()."_catalog_items_".$moduleid."_".strtolower($group['name_db'])." AS ".$group['name_db']." ON items.ext_id = ".$group['name_db'].".id ";
            //показываем только товары, доступные для frontend + ограничиваемся нужной нам тов. группой
            $query = " items.`available`=1 AND items.`group_id`=".$group['id'].") AND (".$query;

        }
        else
        { //фильтр по всем товарам (общие свойства)
            $queryPrefix = "SELECT ".$cfields." FROM ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_items AS items ";
            $query = " items.`available`=1 ) AND  (".$query;

        }
        if (!$closed)
            $query .= ")";

        // если все категории - никаких условий не добавляется
        // в текущей - определяем текущую и добавляем  LEFT JOIN sf_catalog_catalog1_cats AS cats ON cats.id =curcatid
        //   если текущая ==0, берём из всех
        // в выбранных LEFT JOIN sf_catalog_catalog1_cats AS cats ON cats.id IN (catid1,catid2,catid3)
        if ($filter['catids'] != "0") //все категории
        {
            if (strlen($filter['catids']) == 0) //показывать товары из текущей
            {
                $curr_cat_id = $this->get_current_catIDs();
                if ($curr_cat_id)
                {
                    if (is_array($curr_cat_id))
                        $query = " LEFT JOIN ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_item2cat AS i2c ON i2c.item_id=items.id  WHERE ( i2c.cat_id IN (".implode(",", $curr_cat_id).") AND ".$query;
                    else
                        $query = " LEFT JOIN ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_item2cat AS i2c ON i2c.item_id=items.id WHERE ( i2c.cat_id=".$curr_cat_id." AND ".$query;
                }
                else
                {
                    $catid = intval($kernel->pub_httpget_get("fcid")); //доп.возможность передать фильтру айдишник категории, незаметный для других методов

                    if ($catid == 0 && isset($this->current_cat_IDs[$moduleid])) //не нашли ранее, но есть catid, заполненный в карточке товара
                        $catid = $this->current_cat_IDs[$moduleid];
                    if ($catid == 0) //"выбранная категория", но категорий ни в каком виде не передано
                        return null;
                    $query = " LEFT JOIN ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_item2cat AS i2c ON i2c.item_id=items.id WHERE ( i2c.cat_id=".$catid." AND ".$query;
                }

            }
            else //выбранные категории
                $query = " LEFT JOIN ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_item2cat AS i2c ON i2c.item_id=items.id  WHERE ( i2c.cat_id IN (".$filter['catids'].") AND ".$query;
        }
        else
            $query = " WHERE (".$query;
        $query = $queryPrefix." ".$query;

        $query = iconv("UTF-8", "UTF-8//IGNORE", $query);
        return $query;
    }

    /**
     *    Сохранение внутреннего фильтра в админке
     *
     * @param $id integer - idшник внутреннего фильтра ( -1 == добавление)
     * @return mixed если всё ок - вернёт false, иначе - сообщение об ошибке
     */
    private function save_inner_filter($id)
    {
        global $kernel;

        $name = trim($kernel->pub_httppost_get('name'));
        $stringid = trim($kernel->pub_httppost_get('stringid'));
        $query = trim($kernel->pub_httppost_get('query'));
        $template = trim($kernel->pub_httppost_get('template'));
        $limit = intval($kernel->pub_httppost_get('limit'));
        $perpage = intval($kernel->pub_httppost_get('perpage'));
        $maxpages = intval($kernel->pub_httppost_get('maxpages'));
        $targetpage = trim($kernel->pub_httppost_get('targetpage'));
        $groupid = trim($kernel->pub_httppost_get('groupid'));

        if (empty($name) || empty($stringid) || empty($query) || empty($template))
            return "[#catalog_edit_inner_filter_save_msg_error#] [#catalog_edit_inner_filter_save_msg_emptyfields#]";


        $exFilter = CatalogCommons::get_inner_filter_by_stringid($stringid);
        if ($stringid == "null" || ($exFilter && $exFilter['id'] != $id))
            return "[#catalog_edit_inner_filter_save_msg_error#] [#catalog_edit_inner_filter_save_msg_stringid_exists#]";

        $cattype = $kernel->pub_httppost_get('cattype');

        switch ($cattype)
        {
            case "all":
                $catids = "0";
                break;
            case "current":
                $catids = "";
                break;
            case "selected":
            default:
                $ccbs = $_POST['ccb'];
                $cids_arr = array();
                foreach ($ccbs as $catID => $ischecked)
                {
                    if ($ischecked == 1)
                        $cids_arr[] = $catID;
                }
                $catids = implode(",", $cids_arr);
                break;

        }
        if ($id > 0)
        {
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_inner_filters` '.
                'SET `name`="'.$name.'", '.
                '`stringid`="'.$stringid.'", '.
                '`query`="'.$query.'", '.
                '`template`="'.$template.'", '.
                '`limit`="'.$limit.'", '.
                '`perpage`="'.$perpage.'", '.
                '`maxpages`="'.$maxpages.'", '.
                '`catids`="'.$catids.'", '.
                '`targetpage`="'.$targetpage.'", '.
                '`groupid`="'.$groupid.'" '.
                ' WHERE `id`='.$id;
        }
        else
        {
            $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_inner_filters` '.
                "(`name`, `stringid`, `query`, `template`, `limit`, `perpage`, `maxpages`, `catids`, `targetpage`,`groupid`) VALUES ".
                "('".$name."','".$stringid."', '".$query."', '".$template."', ".$limit.",".$perpage.",".$maxpages.",'".$catids."','".$targetpage."','".$groupid."')";
        }
        $kernel->runSQL($query);
        return false;
    }

    /**
     *    Форма редактирования внутреннего фильтра в админке
     *
     * @param $id integer - idшник внутреннего фильтра ( -1 == добавление)
     * @return string
     */
    private function show_inner_filter_form($id)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'inner_filter_form.html'));

        $content = $this->get_template_block('form_header');

        //По умолчанию создаём новую
        $item['name'] = '';
        $item['stringid'] = '';
        $item['template'] = '';
        $item['query'] = '';
        $item['maxpages'] = '10';
        $item['limit'] = '';
        $item['perpage'] = '10';
        $item['catids'] = '';
        $item['targetpage'] = '';
        $item['groupid'] = 0;

        $name_form = '[#catalog_edit_inner_filter_label_add#]';

        //А если редактирование - то переопределим.
        if ($id > 0)
        {
            $item = CatalogCommons::get_inner_filter($id);
            $name_form = '[#catalog_edit_inner_filter_label_edit#]';
        }

        $groups = CatalogCommons::get_groups();
        ///$groupline = $this->get_template_block('group_item');

        $selected_group = array();

        $groupselectlist = "";
        foreach ($groups as $group)
        {
            $chk = "";
            if ($item['groupid'] == $group['id'])
            {
                $chk = ' selected="selected"';
                $selected_group = $group;
            }

            $line = $this->get_template_block('group_option_for_select');
            $line = str_replace('%name%', $group['name_full'], $line);
            $line = str_replace('%id%', $group['id'], $line);
            $line = str_replace('%sel%', $chk, $line);
            $groupselectlist .= $line;

        }

        //Сформируем строчки селектов, для выбора шаблонов
        //пока это будет делать прям здесь, потом необходимо будет переделывать
        //через свойства.
        // Если есть дополнительные параметры обработаем их
        $arr_files = $this->get_template_file();

        //И теперь добавим собственно для шаблона списка товаров
        $html_template_list = '';
        foreach ($arr_files as $key => $val)
        {
            $chk = '';
            if ($key == $item['template'])
                $chk = ' selected="selected"';

            $line = $this->get_template_block('option_for_select');
            $line = str_replace('%name%', $val, $line);
            $line = str_replace('%key%', $key, $line);
            $line = str_replace('%sel%', $chk, $line);
            $html_template_list .= $line;
        }

        //Строим список категорий, к которым будет применяться фильтр
        $cats = $this->get_child_categories(0);
        $item_catids = explode(",", $item['catids']);
        $categories = array();
        foreach ($cats as $cat)
        {
            $repl = "";
            if (in_array($cat['id'], $item_catids))
                $repl = ' checked="checked"';
            $catline = $this->get_template_block('category_item');
            $catline = str_replace("%checked%", $repl, $catline);
            $catline = str_replace("%id%", $cat["id"], $catline);
            $catline = str_replace("%catname%", $cat["name"], $catline);
            $catline = str_replace("%shift%", str_repeat("&nbsp;&nbsp;&nbsp;", $cat['depth']), $catline);
            $categories[] = $catline;
        }

        //$item['catids'] - пустое - текущая категория; 0 - все; catid1,catid2,... - выбранные
        if ($item['catids'] == "0")
            $cattype = "all";
        elseif (empty($item['catids']))
            $cattype = "current";
        else
            $cattype = "selected";
        switch ($cattype)
        {
            case "current":
                $content = str_replace('%cattype_all%', "", $content);
                $content = str_replace('%cattype_selected%', "", $content);
                $content = str_replace('%cattype_current%', " selected", $content);
                $content = str_replace('%selectedcats_display%', "none", $content);
                break;
            case "all":
                $content = str_replace('%cattype_all%', " selected", $content);
                $content = str_replace('%cattype_selected%', "", $content);
                $content = str_replace('%cattype_current%', "", $content);
                $content = str_replace('%selectedcats_display%', "none", $content);
                break;
            case "selected":
            default:
                $content = str_replace('%cattype_all%', "", $content);
                $content = str_replace('%cattype_selected%', " selected", $content);
                $content = str_replace('%cattype_current%', "", $content);
                $content = str_replace('%selectedcats_display%', "block", $content);
                break;
        }

        $content = str_replace('%template%', $html_template_list, $content);
        $content = str_replace('%id%', $id, $content);
        $content = str_replace('%name%', $item['name'], $content);
        $content = str_replace('%groupselectlist%', $groupselectlist, $content);
        $content = str_replace('%stringid%', $item['stringid'], $content);
        $content = str_replace('%query%', $item['query'], $content);
        $content = str_replace('%limit%', $item['limit'], $content);
        $content = str_replace('%perpage%', $item['perpage'], $content);
        $content = str_replace('%maxpages%', $item['maxpages'], $content);
        $content = str_replace('%targetpage%', $item['targetpage'], $content);

        if ($selected_group)
        {
            $sql_val = $this->convert_inner_filter_query2sql($item, $selected_group);
            $sql_val = $this->process_variables_out($sql_val);
        }
        else
            $sql_val = 'n/a';
        $content = str_replace('%sql%', $sql_val, $content);

        $content = str_replace('%categories%', join("\n", $categories), $content);
        $content = str_replace('%groups_props%', CatalogCommons::get_all_group_props_html(), $content);
        $content = str_replace('%form_header_txt%', $name_form, $content);
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('inner_filter_save'), $content);

        return $content;
    }


    /**
     *    Форма редактирования товарной группы в админке
     *
     * @param $id integer - idшник товарной группы (0 : общие свойства, -1 : добавление)
     * @return string
     */
    private function show_group_form($id)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'group_form.html'));

        $content = $this->get_template_block('form_header');
        //По умолчанию создаём новую
        $group['name_full'] = '';
        $group['name_db'] = '';
        $group['template_items_list'] = '';
        $group['template_items_one'] = '';
        $group['defcatids'] = '';

        $name_form = '[#catalog_edit_group_label_add#]';

        //А если редактирование - то переопределим.
        if ($id > 0)
        {
            $group = CatalogCommons::get_group($id);
            $name_form = '[#catalog_edit_group_label_edit#]';
        }

        //Сформируем строчки селектов, для выбора шаблонов
        //пока это будет делать прям здесь, потом необходимо будет переделывать
        //через свойства.
        // Если есть дополнительные параметры обработаем их
        $arr_files = $this->get_template_file();

        //И теперь добавим собственно для шаблона списка товаров
        $html_template_list = '';
        foreach ($arr_files as $key => $val)
        {
            $chk = '';
            if ($key == $group['template_items_list'])
                $chk = ' selected="selected"';

            $line = $this->get_template_block('option_for_select');
            $line = str_replace('%name%', $val, $line);
            $line = str_replace('%key%', $key, $line);
            $line = str_replace('%sel%', $chk, $line);
            $html_template_list .= $line;
        }

        //И теперь добавим собственно для шаблона карточки товаров
        $html_template_item = '';
        foreach ($arr_files as $key => $val)
        {
            $chk = '';
            if ($key == $group['template_items_one'])
                $chk = ' selected="selected"';

            $line = $this->get_template_block('option_for_select');
            $line = str_replace('%name%', $val, $line);
            $line = str_replace('%key%', $key, $line);
            $line = str_replace('%sel%', $chk, $line);
            $html_template_item .= $line;
        }

        $cats = $this->get_child_categories(0);
        //$item_catids = $this->get_item_catids($id);
        $categories = array();
        $defcatids = explode(",", $group['defcatids']);
        foreach ($cats as $cat)
        {
            $repl = "";
            if (in_array($cat['id'], $defcatids))
                $repl = ' checked="checked"';
            $catline = $this->get_template_block('category_item');
            $catline = str_replace("%checked%", $repl, $catline);
            $catline = str_replace("%id%", $cat["id"], $catline);
            $catline = str_replace("%catname%", $cat["name"], $catline);
            $catline = str_replace("%shift%", str_repeat("&nbsp;&nbsp;&nbsp;", $cat['depth']), $catline);
            $categories[] = $catline;
        }

        $content = str_replace('%template_list%', $html_template_list, $content);
        $content = str_replace('%template_item%', $html_template_item, $content);
        $content = str_replace('%groupid%', $id, $content);
        $content = str_replace('%name%', $group['name_full'], $content);
        $content = str_replace('%dbname%', $group['name_db'], $content);
        $content = str_replace('%categories%', join("\n", $categories), $content);

        $content = str_replace('%form_header_txt%', $name_form, $content);
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('group_save'), $content);
        $content = str_replace('%form_action2%', $kernel->pub_redirect_for_form('regen_tpls4groups&id_group='.$id), $content);
        return $content;
    }

    /**
     *    Вывод товаров категории в админке
     *
     * @return string
     */
    private function show_category_items()
    {
        global $kernel;

        //Взяли шаблон
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'category_items.html'));
        $cprops = CatalogCommons::get_common_props($kernel->pub_module_id_get(), true);


        $id_cat = $kernel->pub_httpget_get("id");
        $id_cat = intval($id_cat);
        if ($id_cat <= 0)
            return $this->get_template_block('not_select_cat');


        $cdata = $this->get_category($id_cat);

        //Определим список достпуных товарных групп, для создания новых
        //товаров
        // Сформируем список достпных товарных групп, для добавления нового товара
        $groups = CatalogCommons::get_groups();
        $groups_vals = '';
        $selected_groupid = 0;
        if (isset($_COOKIE['last_add_item_groupid']))
            $selected_groupid = $_COOKIE['last_add_item_groupid'];
        if (count($groups) > 0)
        {
            foreach ($groups as $group)
            {
                $option = $this->get_template_block('group_value');
                $option = str_replace('%group_id%', $group['id'], $option);
                $option = str_replace('%group_name%', $group['name_full'], $option);
                if ($selected_groupid == $group['id'])
                    $option = str_replace('%gselected%', 'selected', $option);
                else
                    $option = str_replace('%gselected%', '', $option);
                $groups_vals .= $option;
            }
        }


        //Сначала построим колонки свойств, которые будут выводиться
        $colum_th = '';
        foreach ($cprops as $cprop)
            $colum_th .= '<th>'.$cprop['name_full'].'</th>';

        $offset = $this->get_offset_admin();
        $limit = $this->get_limit_admin();

        $total = $this->get_cat_items_count($id_cat, false);

        //Сформируем список товаров
        //товары категории
        $items = $this->get_cat_items($cdata['id'], $offset, $limit, false);
        $count = count($items);
        //По умолчанию вернём что нет товаров тут
        $items_html = $this->get_template_block('cat_items_empty');
        $pblock = '';
        $purl = 'category_items&id='.$id_cat.'&'.$this->admin_param_offset_name.'=';
        if ($count > 0)
        {
            //Сначала формируем массив с достпными строками товара
            $lines = array();
            $common_table_info = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_items');
            $enum_props_cache = array();
            foreach ($items as $item)
            {
                $line = $this->get_template_block('cat_items_line');
                $colum_td = '';
                foreach ($cprops as $cprop)
                {
                    /*
                    $prop_value = $item[$cprop['name_db']];
                    if ($cprop['type'] == 'number' || $cprop['type'] == "string")
                    {
                        $prop_value = htmlspecialchars($prop_value);
                        $prop_value ='<input style="width:88%; margin:0 auto; float:none;" type="text" name="iv['.$item['id'].']['.$cprop['name_db'].']" value="'.$prop_value.'">';
                    }
                    */

                    $prop_value = '';
                    if ($cprop['type'] == 'number' || $cprop['type'] == "string")
                    {
                        $prop_line = $this->get_template_block('list_prop_value_edit');
                        $prop_value = htmlspecialchars($item[$cprop['name_db']]);
                    }
                    elseif ($cprop['type'] == 'enum')
                    {
                        $prop_line = $this->get_template_block('list_prop_value_select_edit');
                        $cache_key = $cprop['name_db'];
                        if (isset($enum_props_cache[$cache_key]))
                            $enum_props = $enum_props_cache[$cache_key];
                        else
                        {
                            $enum_props = $this->get_enum_set_prop_values($common_table_info[$cprop['name_db']]['Type']);
                            $enum_props_cache[$cache_key] = $enum_props;
                        }
                        $optlines = '';
                        foreach ($enum_props as $enum_option)
                        {
                            if ($item[$cprop['name_db']] == $enum_option)
                                $optline = $this->get_template_block('list_prop_value_select_option_selected_edit');
                            else
                                $optline = $this->get_template_block('list_prop_value_select_option_edit');

                            $optline = str_replace('%option_value%', $enum_option, $optline);
                            $optline = str_replace('%option_value_escaped%', htmlspecialchars($enum_option), $optline);
                            $optlines .= $optline;
                        }
                        $prop_line = str_replace('%options%', $optlines, $prop_line);
                    }
                    else
                    {
                        $prop_line = $this->get_template_block('list_prop_value');
                        $prop_value = $item[$cprop['name_db']];
                        if ($cprop['type'] == 'pict' && !empty($prop_value))
                        {
                            $path_parts = pathinfo($prop_value);
                            $path_small = $path_parts['dirname'].'/tn/'.$path_parts['basename'];
                            if (file_exists($path_small))
                                $prop_value = "<img src='/".$path_small."' width=50 />";
                        }
                    }
                    $prop_line = str_replace("%name_db%", $cprop['name_db'], $prop_line);
                    $prop_line = str_replace('%list_prop_value%', $prop_value, $prop_line);
                    $colum_td .= $prop_line;
                }
                $line = str_replace('%colum_td%', $colum_td, $line);
                $line = str_replace('%item_order%', $item['order'], $line);
                $line = str_replace('%item_id%', $item['id'], $line);
                $line = str_replace('%group_name%', $groups[$item['group_id']]['name_full'], $line);
                $lines[] = $line;
            }
            //Теперь возьмём форму самой таблицы и вставим туда эти строки
            $items_html = $this->get_template_block('cat_items');
            $items_html = str_replace('%form_action%', $kernel->pub_redirect_for_form('category_items_save'), $items_html);
            $items_html = str_replace('%cat_items_line%', join("\n", $lines), $items_html);
            $pblock = $this->build_pages_nav($total, $offset, $limit, $purl, 0, 'url');
            $search_block = $this->get_template_block('search_block');
        }
        else
            $search_block = '';
        //Построим список категорий куда пользователь может перенести товар
        $cats = $this->get_child_categories(0, 0, array());
        $options = '';
        $cat_shift = $this->get_template_block('cat_shift');
        foreach ($cats as $cat)
        {
            $option = $this->get_template_block('cat_option');
            $option = str_replace('%cat_id%', $cat['id'], $option);
            $option = str_replace('%cat_name%', str_repeat($cat_shift, $cat['depth']).$cat['name'], $option);
            $options .= $option;
        }
        //Получили то, что всегда будет выводиться, несмотря ни на что
        $content = $this->get_template_block('header');

        //сначала итемсы, так как там ещё есть переменные
        $content = str_replace('%items%', $items_html, $content);
        //Теперь всё остальное
        $content = str_replace('%colum_th%', $colum_th, $content);
        $content = str_replace('%cname%', $cdata['name'], $content);
        $content = str_replace('%cid%', $cdata['id'], $content);
        $content = str_replace('%group_values%', $groups_vals, $content);
        $content = str_replace('%cats_options%', $options, $content);
        $content = str_replace('%numcolspan%', count($cprops) + 2, $content);
        $content = str_replace('%pages%', $pblock, $content);
        $content = str_replace('%catid%', $id_cat, $content);
        $content = str_replace('%search_block%', $search_block, $content);
        $content = str_replace('%redir2%', urlencode($purl.$offset), $content);

        return $content;
    }

    /**
     *    Форма редактирования категории в админке
     *
     * @param $id integer - id-шник категории
     * @return string
     */
    private function show_category_form($id)
    {
        global $kernel;
        $cat = $this->get_category($id);
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'category_form.html'));
        $content = $this->get_template_block('header');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('category_save'), $content);
        $content = str_replace('%pid%', $cat['parent_id'], $content);

        if ($cat['_hide_from_waysite'] == 1)
            $content = str_replace('%_hide_from_waysitechecked%', 'checked', $content);
        else
            $content = str_replace('%_hide_from_waysitechecked%', '', $content);
        if ($cat['is_default'] == 1)
            $content = str_replace('%isdefaultchecked%', 'checked', $content);
        else
            $content = str_replace('%isdefaultchecked%', '', $content);
        $props = CatalogCommons::get_cats_props();
        $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_cats');
        $lines = '';
        foreach ($props as $prop)
        {
            $line = $this->get_template_block('prop_'.$prop['type']);

            switch ($prop['type'])
            {
                //Для простых свойств всё просто. Просто заменяем значение в форме
                case 'text':
                case 'string':
                case 'number':
                    $line = str_replace('%prop_value%', htmlspecialchars($cat[$prop['name_db']]), $line);
                    break;

                //Для файла нужно вывести форму загрузки файла, и возможность удалить этот файл
                case 'file':
                    if ($cat[$prop['name_db']])
                    {
                        $prop_val = $this->get_template_block('prop_file_value');
                        $prop_val = str_replace('%path%', '/'.$cat[$prop['name_db']], $prop_val);
                    }
                    else
                        $prop_val = $this->get_template_block('prop_file_value_null');
                    $line = str_replace('%prop_value%', $prop_val, $line);

                    break;

                //Ихображение
                case 'pict':
                    if ($cat[$prop['name_db']])
                    {
                        $prop_val = $this->get_template_block('prop_pict_value');
                        $prop_val = str_replace('%path%', '/'.$cat[$prop['name_db']], $prop_val);
                    }
                    else
                        $prop_val = $this->get_template_block('prop_pict_value_null');
                    $line = str_replace('%prop_value%', $prop_val, $line);
                    break;

                //Перечесление
                case 'enum':
                    $vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
                    $options = $this->get_template_block('prop_enum_value');
                    $options = str_replace('%enum_value%', '', $options);
                    $options = str_replace('%enum_name%', '[#catalog_edit_category_need_select_label#]', $options);
                    foreach ($vals as $val)
                    {
                        $option = $this->get_template_block('prop_enum_value');
                        $option = str_replace('%enum_value%', $val, $option);
                        $option = str_replace('%enum_name%', $val, $option);
                        if ($val == $cat[$prop['name_db']])
                            $option = str_replace('%selected%', 'selected', $option);
                        $options .= $option;
                    }
                    $options = str_replace('%selected%', '', $options);
                    $line = str_replace('%prop_enum_values%', $options, $line);
                    break;

                //Текст, редактируемый редактором контента
                case 'html':
                    $editor = new edit_content(true);
                    $editor->set_edit_name($prop['name_db']);
                    $editor->set_simple_theme();
                    $editor->set_content($cat[$prop['name_db']]);
                    $line = str_replace('%prop_value%', $editor->create(), $line);
                    break;
            }
            $line = str_replace('%prop_name_full%', $prop['name_full'], $line);
            $line = str_replace('%prop_name_db%', $prop['name_db'], $line);
            $lines .= $line;
        }
        $content = str_replace('%props%', $lines, $content);
        $content = str_replace('%id%', $cat['id'], $content);
        $content .= $this->get_template_block('footer');
        return $content;
    }

    /**
     *  Удаляет категорию из БД
     *
     * @param $cat array удаляемая категория
     * @return void
     */
    private function delete_category($cat)
    {
        global $kernel;
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` WHERE `id`='.$cat['id'];
        $kernel->runSQL($query);
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` WHERE `cat_id`='.$cat['id'];
        $kernel->runSQL($query);
        //переносим child'ы удаляемой категории на уровень выше
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` SET `parent_id`='.$cat['parent_id'].' WHERE `parent_id`='.$cat['id'];
        $kernel->runSQL($query);
        $this->regenerate_all_groups_tpls(false);
    }

    /**
     *  Удаляет свойство из БД
     *
     * @param $propid  integer id-шник свойства
     * @param $groupid integer id-шник товарной группы
     * @return void
     */
    private function delete_prop($propid, $groupid = 0)
    {
        global $kernel;
        $prop = $this->get_prop($propid);

        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_item_props` WHERE `id`='.$propid;
        $kernel->runSQL($query);
        if ($groupid > 0)
        {
            $group = CatalogCommons::get_group($groupid);
            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']).'` DROP COLUMN `'.$prop['name_db']."`";
            $kernel->runSQL($query);
            //$this->regenerate_group_tpls($groupid, false);
            @unlink($kernel->pub_site_root_get()."/modules/catalog/templates_admin/items_search_form_".$group['name_db'].".html");
        }
        else
        { //общее свойство
            $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` DROP COLUMN `'.$prop['name_db']."`";
            $kernel->runSQL($query);


            $query = 'DELETE FROM `'.$kernel->pub_prefix_get()."_catalog_visible_gprops` WHERE `prop`='".$prop['name_db']."' ".
                "AND `module_id`='".$kernel->pub_module_id_get()."'";
            $kernel->runSQL($query);

            $this->regenerate_all_groups_tpls(false);
            //CatalogCommons::regenerate_frontend_item_common_block($kernel->pub_module_id_get(), false);
        }

        if (in_array($prop['type'], array('string', 'text', 'html', 'number', 'enum')))
        { //тип свойства был такой, который используется в шаблоне поиска
            if ($prop['group_id'] == 0)
            { //это было общее свойство
                $groups = CatalogCommons::get_groups($kernel->pub_module_id_get());
                foreach ($groups as $group)
                {
                    $this->generate_search_form($group['id'], array());
                }
            }
            else //это было свойство группы
                $this->generate_search_form($prop['group_id'], array());
        }
    }

    /**
     *  Удаляет свойство КАТЕГОРИИ
     *
     * @param $propid  integer id-шник свойства категории
     * @return void
     */
    private function delete_cat_prop($propid)
    {
        global $kernel;
        $prop = $this->get_cat_prop($propid);
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats_props` WHERE `id`='.$propid;
        $kernel->runSQL($query);
        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats`'
            .' DROP COLUMN `'.$prop['name_db']."`";
        $kernel->runSQL($query);
    }

    /**
     *    Форма редактирования свойства КАТЕГОРИИ в админке
     *
     * @param $id integer - id-шник свойства
     * @return string
     */
    private function show_cat_prop_form($id)
    {
        global $kernel;
        $prop = $this->get_cat_prop($id);

        if (isset($prop['add_param']))
            $prop['add_param'] = @unserialize($prop['add_param']);
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'category_property_form.html'));

        //Для определённых свойств нужно сделать дополнения
        $block_addparam = '';
        switch ($prop['type'])
        {
            case 'enum':
                $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_cats');
                $vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
                $lines = '';
                foreach ($vals as $val)
                {
                    $line = $this->get_template_block('enum_val');
                    $line = str_replace('%val_name%', $val, $line);
                    $line = str_replace('%val_name_urlencoded%', urlencode($val), $line);
                    $lines .= $line;
                }
                $block_addparam = $this->get_template_block('enum_vals');
                $block_addparam = str_replace('%lines%', $lines, $block_addparam);
                $block_addparam = str_replace('%add_enum%', $kernel->pub_redirect_for_form('cat_enum_prop_add'), $block_addparam);
                break;
            case 'pict':
                $addons_param = $this->get_template_block('addons_pict');
                //Сначала всё, что касается исходного изображения
                $source_check = "";
                if ($prop['add_param']['source']['isset'])
                    $source_check = ' checked="checked"';
                //Отметим тек значение по дабавлению водяного знака
                $_tmp_array = array("pswas0" => "", "pswas1" => "", "pswas2" => "");
                $_tmp_array['pswas'.intval($prop['add_param']['source']['water_add'])] = ' selected="selected"';
                $addons_param = $kernel->pub_array_key_2_value($addons_param, $_tmp_array);

                //Аналогично обрабатываем список возможного расположения водяного знака
                $_tmp_array = array("pswps0" => "", "pswps1" => "", "pswps2" => "", "pswps3" => "", "pswps4" => "");
                $_tmp_array['pswps'.intval($prop['add_param']['source']['water_position'])] = ' selected="selected"';
                $addons_param = $kernel->pub_array_key_2_value($addons_param, $_tmp_array);

                //Теперь оставшиеся простые значения
                $addons_param = str_replace('%source_check%', $source_check, $addons_param);
                $addons_param = str_replace('%path_source_water_path%', $prop['add_param']['source']['water_path'], $addons_param);
                $addons_param = str_replace('%pict_source_width%', $prop['add_param']['source']['width'], $addons_param);
                $addons_param = str_replace('%pict_source_height%', $prop['add_param']['source']['height'], $addons_param);

                //Теперь всё, что касается большого изображения
                $big_check = "";
                if ($prop['add_param']['big']['isset'])
                    $big_check = ' checked="checked"';

                //Отметим тек значение по дабавлению водяного знака
                $_tmp_array = array("pbwas0" => "", "pbwas1" => "", "pbwas2" => "");
                $_tmp_array['pbwas'.intval($prop['add_param']['big']['water_add'])] = ' selected="selected"';
                $addons_param = $kernel->pub_array_key_2_value($addons_param, $_tmp_array);

                //Аналогично обрабатываем список возможного расположения водяного знака
                $_tmp_array = array("pbwps0" => "", "pbwps1" => "", "pbwps2" => "", "pbwps3" => "", "pbwps4" => "");
                $_tmp_array['pbwps'.intval($prop['add_param']['big']['water_position'])] = ' selected="selected"';
                $addons_param = $kernel->pub_array_key_2_value($addons_param, $_tmp_array);

                //Теперь оставшиеся простые значения
                $addons_param = str_replace('%big_check%', $big_check, $addons_param);
                $addons_param = str_replace('%path_big_water_path%', $prop['add_param']['big']['water_path'], $addons_param);
                $addons_param = str_replace('%pict_big_width%', $prop['add_param']['big']['width'], $addons_param);
                $addons_param = str_replace('%pict_big_height%', $prop['add_param']['big']['height'], $addons_param);

                //Теперь всё, что касается малого изображения
                $small_check = "";
                if ($prop['add_param']['small']['isset'])
                    $small_check = ' checked="checked"';

                $addons_param = str_replace('%small_check%', $small_check, $addons_param);
                $addons_param = str_replace('%pict_small_width%', $prop['add_param']['small']['width'], $addons_param);
                $addons_param = str_replace('%pict_small_height%', $prop['add_param']['small']['height'], $addons_param);

                //Общее для всех параметров
                $addons_param = str_replace('%pict_path%', $prop['add_param']['pict_path'], $addons_param);
                $addons_param = str_replace('%pict_path_start%', 'content/files/'.$kernel->pub_module_id_get().'/', $addons_param);
                $block_addparam = $addons_param; //added
                break;
        }

        //Собственно вывод
        $content = $this->get_template_block('header');

        //первым делом, так как там есть метки
        $content = str_replace('%block_addparam%', $block_addparam, $content);

        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('cat_prop_save'), $content);
        $content = str_replace('%name_full%', $prop['name_full'], $content);
        $content = str_replace('%name_db%', $prop['name_db'], $content);
        $content = str_replace('%prop_type%', $kernel->pub_page_textlabel_replace('[#catalog_prop_type_'.$prop['type'].'#]'), $content);
        $content = str_replace('%id%', $prop['id'], $content);

        //$content = str_replace('%group_id%', $prop['group_id'], $content);
        return $content;
    }


    /**
     *    Форма редактирования и добавления свойства в админке
     *
     * @param integer $id_prop - id-шник свойства
     * @param integer $id_group - id-шник группы
     * @param integer $id_group_control  - id-шник
     * @return string
     */
    private function show_prop_form($id_prop = 0, $id_group = 0, $id_group_control = 0)
    {
        global $kernel;

        $id_prop = intval($id_prop);
        $id_group = intval($id_group);
        $id_group_control = intval($id_group_control);

        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'property_form.html'));

        //Получим массив с параметрами свойства или сделаем пустой,
        //если это новое свойство
        $_tmp_label = "_add";
        $action = "prop_add";
        $off_type = '';
        $prop = array("id" => 0,
            "group_id" => $id_group,
            "name_db" => "",
            "name_full" => "",
            "type" => "string",
            "showinlist" => 0,
            "sorted" => 0,
            "ismain" => 0
        );

        if ($id_prop > 0)
        {
            $prop = $this->get_prop($id_prop);
            $action = "prop_save";
            $_tmp_label = "";
            $off_type = 'disabled="disabled"';
        }

        //Сразу определимся с заголовком, так как у нас могут быть четыре разных варинанта
        $form_label = 'label_form_group';
        if ($id_group == 0)
            $form_label = 'label_form_global';

        $form_label .= $_tmp_label;
        $form_label = $this->get_template_block(trim($form_label));

        if ($id_group > 0)
        {
            $arr = CatalogCommons::get_group($id_group);
            $form_label = str_replace('%name%', $arr['name_full'], $form_label);
        }

        //Определим какой тип поля должен быть выбран, и заменим в шаблоне
        $select_type = $this->get_template_block('prop_type');
        $select_type = str_replace('value="'.$prop['type'].'"', 'value="'.$prop['type'].'" selected="selected"', $select_type);
        $content = $this->get_template_block('header');
        if ($prop['group_id'] == 0)
        {
            $content = str_replace("%sort_enabled%", "", $content);
            $content = str_replace("%ismain_enabled%", "", $content);
            $inlist_checked = '';
            if ($prop['showinlist'] == 1)
                $inlist_checked = ' checked="checked"';
            if ($prop['ismain'] == 1)
                $content = str_replace("%ismain_checked%", " checked", $content);
        }
        else
        {
            $inlist_checked = ' disabled="true"';
            //сортировка возможна только для общих свойств
            $content = str_replace("%sort_enabled%", " disabled='true'", $content);
            //основным может быть только общее свойство
            $content = str_replace("%ismain_enabled%", " disabled='true'", $content);
        }
        $content = str_replace("%ismain_checked%", "", $content);


        $sort_params = "";
        $sort_param = $this->get_template_block('sort_param');
        $sort_param = str_replace("%sort_value%", 0, $sort_param);
        $sort_param = str_replace("%sort_checked%", $prop['sorted'] == 0 ? " selected" : "", $sort_param);
        $sort_param = str_replace("%sort_name%", $kernel->pub_page_textlabel_replace("[#catalog_prop_sort_no#]"), $sort_param);
        $sort_params .= $sort_param;
        $sort_param = $this->get_template_block('sort_param');
        $sort_param = str_replace("%sort_value%", 1, $sort_param);
        $sort_param = str_replace("%sort_checked%", $prop['sorted'] == 1 ? " selected" : "", $sort_param);
        $sort_param = str_replace("%sort_name%", $kernel->pub_page_textlabel_replace("[#catalog_prop_sort_asc#]"), $sort_param);
        $sort_params .= $sort_param;
        $sort_param = $this->get_template_block('sort_param');
        $sort_param = str_replace("%sort_value%", 2, $sort_param);
        $sort_param = str_replace("%sort_checked%", $prop['sorted'] == 2 ? " selected" : "", $sort_param);
        $sort_param = str_replace("%sort_name%", $kernel->pub_page_textlabel_replace("[#catalog_prop_sort_desc#]"), $sort_param);
        $sort_params .= $sort_param;
        $content = str_replace("%sort_params%", $sort_params, $content);
        $content .= $this->get_template_block('footer');

        //Теперь, в зависимости от того, какое это поле, возможно нам нужно показать
        //что-то дополнительное
        $addons_param = '';
        switch ($prop['type'])
        {
            case 'set':
            case 'enum':
                if ($prop['group_id'] == 0)
                    $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_items');
                else
                {
                    $group = CatalogCommons::get_group($prop['group_id']);
                    $tinfo = $kernel->db_get_table_info('_catalog_items_'.$kernel->pub_module_id_get().'_'.$group['name_db']);
                }
                $addons_param = $this->get_template_block($prop['type'].'_vals');
                $vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
                $lines = '';
                foreach ($vals as $num_val => $val)
                {
                    //пропускаем нулевое (не выбранное значение)
                    if ($num_val == 0)
                        continue;
                    $line = $this->get_template_block($prop['type'].'_val');
                    $line = str_replace('%action_del%', 'enum_prop_delete&enumval='.urlencode($val).'&propid=%id%&id_group_control='.$id_group_control, $line);
                    $line = str_replace('%val_name%', $val, $line);
                    $lines .= $line;
                }
                $addons_param = str_replace('%vals%', $lines, $addons_param);
                $addons_param = str_replace('%form_action%', $kernel->pub_redirect_for_form('enum_prop_add&id_group_control='.$id_group_control), $addons_param);
                break;
            case 'pict':
                $addons_param = $this->get_template_block('addons_pict');
                if (isset($prop['add_param']['big']['water_position']))
                    $prop['add_param']['big']['place'] = $prop['add_param']['big']['water_position'];
                if (isset($prop['add_param']['source']['water_position']))
                    $prop['add_param']['source']['place'] = $prop['add_param']['source']['water_position'];
                $addons_param = self::process_image_settings_block($addons_param, $prop['add_param']);

                //Общее для всех параметров
                $addons_param = str_replace('%pict_path%', $prop['add_param']['pict_path'], $addons_param);
                $addons_param = str_replace('%pict_path_start%', 'content/files/'.$kernel->pub_module_id_get().'/', $addons_param);
                break;
        }


        //Обязательно первым, так там есть ещё переменные
        //для картинки например новые парметры будут стоять в двух местах, так как способ их
        //ввода не отличается ни при заведении нового, ни при вводе старого

        $content = str_replace('%addons_param%', $addons_param, $content);

        //Теперь, если это поле "изображение" то тут ещё толпа параметров, которые надо
        //получить из свойств модуля
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form($action.'&id_group_control='.$id_group_control), $content);
        $content = str_replace('%form_label%', $form_label, $content);
        $content = str_replace('%name_full%', $prop['name_full'], $content);
        $content = str_replace('%name_db%', $prop['name_db'], $content);
        $content = str_replace('%prop_type%', $select_type, $content);
        $content = str_replace('%id%', $prop['id'], $content);
        $content = str_replace('%group_id%', $prop['group_id'], $content);
        $content = str_replace('%off_type%', $off_type, $content);

        //$content = str_replace('%sorted_checked%' , $sorted_checked  , $content);
        $content = str_replace('%inlist_checked%', $inlist_checked, $content);

        return $content;
    }

    /**
     * Возвращает настройки для построения меню категорий
     *
     * @return data_tree
     */
    private function create_categories_tree()
    {
        global $kernel;
        $nodes = $this->get_categories_tree(0);

        $tree = new data_tree('[#catalog_menu_label_cats#]', '0', $nodes);
        $tree->set_action_click_node('category_items');
        $tree->set_action_move_node('category_move');
        $tree->set_drag_and_drop(true);
        $tree->set_tree_ID($kernel->pub_module_id_get());

        //$tree->not_click_main = true;
        //$tree->set_node_default($node_default);

        //Создаём контекстное меню
        $tree->contextmenu_action_set('[#catalog_category_add_label#]', 'category_add');
        $tree->contextmenu_delimiter();
        $tree->contextmenu_action_remove('[#catalog_category_remove_label#]', 'category_delete', 0, '[#catalog_category_del_alert#]');
        $tree->set_name_cookie($this->structure_cookie_name);
        return $tree;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления построением меню
     * @return boolean true
     */
    public function interface_get_menu($menu)
    {
        $menu->set_menu_block('[#catalog_menu_label_cats#]');
        $menu->set_tree($this->create_categories_tree());
        $menu->set_menu_block('[#catalog_menu_label#]');
        $menu->set_menu("[#catalog_menu_all_props#]", "show_group_props&id=0" /*, array('flush' => 1)*/);
        $menu->set_menu("[#catalog_menu_groups#]", "show_groups", array('flush' => 1));
        $menu->set_menu("[#catalog_menu_cat_props#]", "show_cat_props", array('flush' => 1));
        $menu->set_menu("[#catalog_menu_items#]", "show_items", array('flush' => 1));
        $menu->set_menu("[#catalog_inner_filters#]", "show_inner_filters", array('flush' => 1));
        $menu->set_menu("[#catalog_basket_order_settings_label#]", "show_order_fields", array('flush' => 1));
        $menu->set_menu("[#catalog_menu_variables#]", "show_variables", array('flush' => 1));

        $menu->set_menu_block('[#catalog_menu_label_import_export#]');
        $menu->set_menu("[#catalog_import_csv_menuitem#]", "import_csv", array('flush' => 1));
        $menu->set_menu("[#catalog_export_csv_menuitem#]", "show_csv_export", array('flush' => 1));
        $menu->set_menu("[#catalog_menu_label_import_commerceml#]", "import_commerceml", array('flush' => 1));
        //$menu->set_menu_default('show_items');
        return true;
    }

    /**
     * Возвращает последний order вложенных категорий
     *
     * @param integer $pid id-шник родительской категории
     * @param integer $skip сколько категорий пропустить
     * @return integer
     */
    public function get_last_order_in_cat($pid, $skip = -1)
    {
        global $kernel;
        $sql = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_cats` WHERE `parent_id` = '.$pid;
        if ($skip == -1) //просто последнюю, с упорядоченные по убыванию
            $sql .= ' ORDER BY `order` DESC LIMIT 1';
        else
            $sql .= ' ORDER BY `order` ASC LIMIT '.$skip.', 1';
        $query = $kernel->runSQL($sql);
        if (mysql_num_rows($query) > 0)
        {
            $row = mysql_fetch_assoc($query);
            return $row['order'];
        }
        else
            return 1;
    }

    /**
     * Тестирование фильтра из админкеи
     *
     * @param integer $id  ID-шник фильтра
     * @return string
     */
    private function test_filter($id)
    {
        $filter = CatalogCommons::get_inner_filter($id);
        if (!$filter)
            return "not found";
        if ($filter['catids'] == "") //показывать товары из текущей
            return "Ошибка - невозможно показать товары из ТЕКУЩЕЙ категории";
        if (preg_match("/param\\[(.+)\\]/iU", $filter['query']))
            return "Ошибка - невозможно показать товары без параметров формы";

        $response = $this->pub_catalog_show_inner_selection_results($filter['stringid']);
        return $response;
    }

    /**
     * Отображает поля корзины (заказа)
     *
     * @return string
     */
    private function show_order_fields()
    {
        global $kernel;
        $fields = CatalogCommons::get_order_fields();
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'order_fields_list.html'));
        $html = $this->get_template_block('header');
        $content = '';
        $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_basket_orders');
        $html = str_replace('%form_action%', $kernel->pub_redirect_for_form('save_order_fields_order'), $html);
        $num = 1;
        if (count($fields) > 0)
        {
            $content .= $this->get_template_block('table_header');
            foreach ($fields as $field)
            {
                if ($field['type'] == 'enum')
                {
                    $line = $this->get_template_block('line_enum');
                    $property_enum_values = array();
                    $enum_vals = $this->get_enum_set_prop_values($tinfo[$field['name_db']]['Type']);
                    foreach ($enum_vals as $ev)
                    {
                        $property_enum_values[] = str_replace("%val%", $ev, $this->get_template_block('property_enum_value'));
                    }
                    $line = str_replace('%property_enum_values%', implode($this->get_template_block('property_enum_sep'), $property_enum_values), $line);
                }
                else
                    $line = $this->get_template_block('line');
                $line = str_replace('%name_db%', $field['name_db'], $line);
                $line = str_replace('%name_full%', $field['name_full'], $line);
                $line = str_replace('%order%', $field['order'], $line);
                $line = str_replace('%id%', $field['id'], $line);
                if ($field['isrequired'] == 1)
                    $line = str_replace('%property_required%', $this->get_template_block('property_is_required'), $line);
                else
                    $line = str_replace('%property_required%', $this->get_template_block('property_is_not_required'), $line);
                $line = str_replace('%property_type%', "[#catalog_prop_type_".$field['type']."#]", $line);
                //Добавим возможные действия
                $actions = $this->get_template_block('property_del_link').$this->get_template_block('property_edit_link');
                $actions = str_replace('%id%', $field['id'], $actions);
                $actions = str_replace('%name_full%', $field['name_full'], $actions);
                $line = str_replace('%actions%', $actions, $line);
                $line = str_replace('%num%', $num, $line);
                $content .= $line;
                $num++;
            }
            $content .= $this->get_template_block('table_footer');
        }
        else
            $content = $this->get_template_block('no_data');
        $html = str_replace("%table%", $content, $html);
        $html = str_replace("%form_action_tpls%", $kernel->pub_redirect_for_form('regenerate_order_tpls'), $html);
        return $html;
    }

    /**
     *    Форма редактирования и добавления поля
     *  корзины (заказа) в админке
     *
     * @param $id integer - id-шник поля
     * @return string
     */
    private function show_order_field_form($id)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'order_field_form.html'));
        //Если $id == 0 значит это новое поле
        if ($id > 0)
        { //редактирование
            $prop = CatalogCommons::get_order_field($id);
            $action = "order_field_save";
            $off_type = 'disabled="disabled"'; //если это редактирование, запрещаем менять тип
            $form_label = "label_form_edit";
        }
        else
        { //добавление
            $action = "order_field_add";
            $off_type = '';
            $form_label = "label_form_add";
            $prop = array("id" => 0,
                "name_db" => "",
                "name_full" => "",
                "type" => "string",
                "regexp" => "",
                "isrequired" => 0,
            );
        }
        $form_label = $this->get_template_block(trim($form_label));

        //Определим какой тип поля должен быть выбран, и заменим в шаблоне
        $select_type = $this->get_template_block('prop_type');
        $select_type = str_replace('value="'.$prop['type'].'"', 'value="'.$prop['type'].'" selected="selected"', $select_type);
        $content = $this->get_template_block('header');
        $content .= $this->get_template_block('footer');

        //Теперь, в зависимости от того, какое это поле, возможно нам нужно показать
        //что-то дополнительное
        if ($prop['type'] == 'enum')
        {
            //Если это поле "список значений", получим уже введённые значения
            $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_basket_orders');

            $addons_param = $this->get_template_block('enum_vals');
            $vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
            $lines = '';
            foreach ($vals as $num_val => $val)
            {
                //пропускаем нулевое (не выбранное значение)
                if ($num_val == 0)
                    continue;
                $line = $this->get_template_block('enum_val');
                $line = str_replace('%action_del%', 'order_enum_field_delete&enumval='.urlencode($val).'&id=%id%', $line);
                $line = str_replace('%val_name%', $val, $line);
                $lines .= $line;
            }
            $addons_param = str_replace('%vals%', $lines, $addons_param);
            $addons_param = str_replace('%form_action%', $kernel->pub_redirect_for_form('order_enum_field_add'), $addons_param);
        }
        else
            $addons_param = $this->get_template_block('enum_new');

        $content = str_replace('%addons_param%', $addons_param, $content);
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form($action), $content);
        $content = str_replace('%form_label%', $form_label, $content);
        $content = str_replace('%name_full%', $prop['name_full'], $content);
        $content = str_replace('%name_db%', $prop['name_db'], $content);
        $content = str_replace('%prop_type%', $select_type, $content);
        $content = str_replace('%id%', $prop['id'], $content);
        $content = str_replace('%off_type%', $off_type, $content);
        $content = str_replace('%regexp%', $prop['regexp'], $content);
        if ($prop['isrequired'] == 1)
            $content = str_replace('%req_checked%', "checked", $content);
        else
            $content = str_replace('%req_checked%', "", $content);
        return $content;
    }


    /**
     * Сохраняет поле для заказов (корзины)
     *
     * @param integer $id id-шник поля
     * @param string $name_full полное имя поля
     * @param string $name_db БД-имя поля
     * @param string $regexp регэксп для поля
     * @param string $cb_req чекбокс из POST - обязательное поле или нет
     * @return void
     */
    private function save_order_field($id, $name_full, $name_db, $regexp, $cb_req)
    {
        global $kernel;
        $prop = CatalogCommons::get_order_field($id);
        if (empty($cb_req))
            $req = 0;
        else
            $req = 1;

        $name_db = $this->translate_string2db($name_db);

        //изменилось ли БД-имя?
        if ($name_db != $prop['name_db'])
        {
            $n = 1;
            while (CatalogCommons::is_order_field_exists($name_db))
                $name_db .= $n++;
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields` SET '.
                '`name_db`="'.$kernel->pub_str_prepare_set($name_db).'" WHERE `id`='.$id;
            $kernel->runSQL($query);
            $table = $kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_orders';
            $values = null;
            if ($prop['type'] == 'enum')
            {
                $tinfo = $kernel->db_get_table_info($table);
                $values = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
            }
            $db_type = $this->convert_field_type_2_db($prop['type'], $values);
            $query = 'ALTER TABLE `'.$table.'` CHANGE COLUMN `'.$prop['name_db'].'` `'.$name_db.'` '.$db_type;
            $kernel->runSQL($query);
        }

        //изменились поля?
        if ($name_full != $prop['name_full'] || $regexp != $prop['regexp'] || $req != $prop['isrequired'])
        {
            $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields` SET '.
                '`name_full`="'.$kernel->pub_str_prepare_set($name_full).'", '.
                '`isrequired`='.$req.', '.
                '`regexp`="'.$kernel->pub_str_prepare_set($regexp).'" '.
                'WHERE `id`='.$id;
            $kernel->runSQL($query);
        }
    }


    /**
     * Добавляет поле в таблицу заказов (корзины)
     *
     * @param string $name_full полное имя поля
     * @param string $name_db БД-имя поля
     * @param string $regexp регэксп для поля
     * @param string $cb_req чекбокс из POST - обязательное поле или нет
     * @param string $ptype тип поля
     * @param string $value значения для типа enum
     * @return string БД-имя добавленного свойства
     */
    private function add_order_field($name_full, $name_db, $regexp, $cb_req, $ptype, $value)
    {
        global $kernel;
        if (empty($cb_req))
            $req = 0;
        else
            $req = 1;
        if (mb_strlen($name_db) == 0)
            $name_db = $name_full;
        $namedb = $this->translate_string2db($name_db);
        $n = 2;
        $namedb0 = $namedb;
        while (CatalogCommons::is_order_field_exists($namedb))
            $namedb = $namedb0.$n++;
        if (empty($value))
            $values = "NULL";
        else
        {
            $pva = explode("\n", $value);
            $values = array();
            foreach ($pva as $v)
            {
                $v = trim($v);
                if (mb_strlen($v) != 0)
                    $values[] = $v;
            }
            if (count($values) == 0)
                $values = "NULL";
        }

        //узнаем order у последнего св-ва и добавим 10
        $gprops = CatalogCommons::get_order_fields();
        $props_count = count($gprops);
        if ($props_count == 0)
            $order = 10;
        else
            $order = $gprops[$props_count - 1]['order'] + 10;

        //Собственно запросы по добавлению
        $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields`
                  (`name_db`,`name_full`,`type`, `order`, `isrequired`, `regexp`)
                  VALUES
                  ("'.$namedb.'","'.$kernel->pub_str_prepare_set($name_full).'","'.$ptype.'", '.$order.', '.$req.', "'.$kernel->pub_str_prepare_set($regexp).'")';
        $kernel->runSQL($query);

        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_orders`
                  ADD COLUMN `'.$namedb."` ".$this->convert_field_type_2_db($ptype, $values);
        $kernel->runSQL($query);

        return $namedb;
    }


    /**
     *  Удаляет поле из таблицы заказов (корзины)
     *
     * @param $id  integer id-шник поля
     * @return void
     */
    private function delete_order_field($id)
    {
        global $kernel;
        $prop = CatalogCommons::get_order_field($id);
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields` WHERE `id`='.$id;
        $kernel->runSQL($query);
        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_basket_orders` '.
            'DROP COLUMN `'.$prop['name_db']."`";
        $kernel->runSQL($query);
    }


    /**
     * Удаляет одно из возможных значений поля типа enum
     * для таблицы заказов (корзины)
     * @param $id integer id-шник поля
     * @return void
     */
    private function delete_order_enum_field($id)
    {
        global $kernel;
        $enumval = $kernel->pub_httpget_get("enumval", false);
        $prop = CatalogCommons::get_order_field($id);
        $table = '_catalog_'.$kernel->pub_module_id_get().'_basket_orders';
        $tinfo = $kernel->db_get_table_info($table);
        $evals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
        $newevals = array();
        foreach ($evals as $eval)
        {
            if ($eval != $enumval)
                $newevals[] = $eval;
        }
        $query = 'UPDATE `'.$kernel->pub_prefix_get().$table.'` SET `'.$prop['name_db'].'`=NULL '.
            'WHERE `'.$prop['name_db'].'`="'.$kernel->pub_str_prepare_set($enumval).'"';
        $kernel->runSQL($query);
        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE `'.$prop['name_db'].'` `'.$prop['name_db'].'` '.$this->convert_field_type_2_db('enum', $newevals);
        $kernel->runSQL($query);
    }


    /**
     *  К уже существующему полю таблицы заказов (корзины)
     *  типа enum  добавляет новое значение
     * @param $id  integer id-шник поля
     * @return void
     */
    private function add_order_enum_field($id)
    {
        global $kernel;
        $enumval = $kernel->pub_httppost_get("enumval", false);
        $prop = CatalogCommons::get_order_field($id);
        $table = '_catalog_'.$kernel->pub_module_id_get().'_basket_orders';
        $tinfo = $kernel->db_get_table_info($table);
        $evals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
        $evals[] = $enumval;
        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE `'.$prop['name_db'].'` `'.$prop['name_db'].'` '.$this->convert_field_type_2_db('enum', $evals);
        $kernel->runSQL($query);
    }


    /**
     * Пересоздаёт шаблон для списка товаров в корзине
     *
     * @param $out_filename string имя генерируемого файла шаблона
     * @return void
     */
    private function regenerate_basket_items_tpl($out_filename)
    {
        global $kernel;

        if (empty($out_filename))
            return;
        $outfilename = CatalogCommons::get_templates_user_prefix().$out_filename;

        //только общие свойства
        $props = CatalogCommons::get_props(0, false);
        $blank_tpl = $kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'frontend_templates/blank_basket.html');
        $this->set_templates($blank_tpl);

        $outfh = '';
        $outfh .= "<!-- @list -->\n";
        $outfh .= $this->get_template_block('list');
        //блок, если в списке нет элементов
        $outfh .= "\n\n\n<!-- @list_null -->\n";
        $outfh .= $this->get_template_block('list_null')."\n\n\n";

        $row_odd = $this->get_template_block('row_odd');
        $row_even = $this->get_template_block('row_even');
        if (!empty($row_odd) && !empty($row_even))
        {
            $row_block = "\n\n\n<!-- @row_odd -->\n";
            $row_block .= $this->get_template_block('row_odd');
            $row_block .= "\n\n\n<!-- @row_even -->\n";
            $row_block .= $this->get_template_block('row_even');
        }
        else
        {
            $row_block = "\n\n\n<!-- @row -->\n";
            $row_block .= $this->get_template_block('row');
        }

        $props_db_names = array();
        //$props_txt = '';
        foreach ($props as $prop)
        {
            $props_db_names[] = "%".$prop['name_db']."%";
            $block = "<!-- @".$prop['name_db']." -->\n".$this->get_template_block('prop_'.$prop['type'])."\n\n";
            $block .= "<!-- @".$prop['name_db']."_null -->\n".$this->get_template_block($prop['type'].'_null')."\n\n";
            $block = str_replace("%prop_name_full%", $prop['name_full'], $block);
            $block = str_replace("%prop_value%", '%'.$prop['name_db'].'_value%', $block);
            $outfh .= $block;
        }

        $row_block = str_replace("%props%", implode("\n", $props_db_names), $row_block);
        $outfh .= $row_block;
        $outfh .= "\n\n\n<!-- @row_delimeter -->\n";
        $outfh .= $this->get_template_block('row_delimeter');
        $kernel->pub_file_save($outfilename, $outfh);
    }


    /**
     * Пересоздаёт шаблон для списка формы оформления заказа в корзине
     *
     * @param $out_filename string имя генерируемого файла шаблона
     * @return void
     */
    private function regenerate_basket_order_tpl($out_filename)
    {
        global $kernel;

        if (empty($out_filename))
            return;
        $outfilename = CatalogCommons::get_templates_user_prefix().$out_filename;

        $tinfo = $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_basket_orders');

        $props = CatalogCommons::get_order_fields();
        $blank_tpl = $kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'frontend_templates/blank_order.html');
        $this->set_templates($blank_tpl);

        $outfh = "<!-- @form -->\n".$this->get_template_block('header');

        foreach ($props as $prop)
        {
            $block = $this->get_template_block('prop_'.$prop['type'])."\n\n";
            if ($prop['type'] == 'enum')
            {
                $enum_vals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
                $str = "\n";
                foreach ($enum_vals as $eval)
                {
                    $line = $this->get_template_block("enum_item")."\n";
                    $line = str_replace("%enum_val%", $eval, $line);
                    $str .= $line;
                }
                $block = str_replace("%enum_vals%", $str, $block);
            }
            $block = str_replace("%prop_name_full%", $prop['name_full'], $block);
            $block = str_replace("%prop_name_db%", $prop['name_db'], $block);
            $block = str_replace("%prop_value%", '%'.$prop['name_db'].'_value%', $block);
            if ($prop['isrequired'] == 1)
                $block = str_replace("%req%", $this->get_template_block('required'), $block);
            else
                $block = str_replace("%req%", $this->get_template_block('not_required'), $block);

            $outfh .= $block;
        }

        $outfh .= "\n".$this->get_template_block('footer');

        $outfh .= "\n\n<!-- @order_received -->\n".$this->get_template_block("order_received");
        $outfh .= "\n\n<!-- @required_field_not_filled -->\n".$this->get_template_block("required_field_not_filled");
        $outfh .= "\n\n<!-- @incorrect_field_value -->\n".$this->get_template_block("incorrect_field_value");
        $outfh .= "\n\n<!-- @no_basket_items -->\n".$this->get_template_block("no_basket_items");
        $outfh .= "\n\n<!-- @no_email_error -->\n".$this->get_template_block("no_email_error");

        $kernel->pub_file_save($outfilename, $outfh);
    }

    /**
     * Удаляет фильтр из БД
     *
     * @param integer $id
     * @return void
     */
    private function delete_inner_filter($id)
    {
        global $kernel;
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_inner_filters` '.
            'WHERE `id`='.intval($id);
        $kernel->runSQL($query);
    }

    /**
     * Отображает форму для экспорта в CSV-файл
     * @param string $template шаблон
     * @return string
     */
    private function show_csv_export_form($template = '')
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'export_csv.html'));
        $content = $this->get_template_block('import_form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('make_csv_export'), $content);
        $props_html = CatalogCommons::get_all_group_props_html(true);
        $content = str_replace('%all_props%', $props_html, $content);
        $content = str_replace('%template_value%', htmlspecialchars($template), $content);
        $filters = CatalogCommons::get_inner_filters();
        $filter_lines = '';
        foreach ($filters as $filter)
        {
            $filter_line = $this->get_template_block('filter_items');
            $filter_line = str_replace('%filterid%', $filter['id'], $filter_line);
            $filter_line = str_replace('%filtername%', htmlspecialchars($filter['name']), $filter_line);
            $filter_lines .= $filter_line;
        }
        $content = str_replace('%filter_items%', $filter_lines, $content);
        return $content;
    }

    /**
     * Создаёт CSV-текст из шаблона для экспорта
     *
     * @param string $template
     * @param integer $filterid
     * @return string
     */
    private function make_csv_export($template, $filterid)
    {
        global $kernel;
        //сначала создадим заголовок
        $headers = array();
        $matches = false;
        $match = false;

        $fields_separator = ";";
        $hitems = explode($fields_separator, $template);
        $gprops = CatalogCommons::get_all_group_props_array();
        $allprops = array();
        foreach ($gprops as $gprop)
        {
            $allprops = array_merge($allprops, $gprop['props']);
        }
        foreach ($hitems as $hitem)
        {
            $hitem = trim($hitem);
            if (empty($hitem))
                continue;
            if (preg_match("|\\%linked_items\\[(.+)\\]\\%|iU", $hitem, $match))
            { //для связанных товаров, %linked_items[/]%

                $hitem = str_replace($match[0], $kernel->pub_page_textlabel_replace('[#catalog_linked_items_list_label#]'), $hitem);
                $headers[] = $hitem;
                continue;
            }

            if (preg_match_all("|\\%([a-z0-9_-]+)\\%|iU", $hitem, $matches))
            {
                foreach ($matches[1] as $match)
                {
                    if (!isset($allprops[$match]))
                        $replacement = $match;
                    else
                        $replacement = $allprops[$match]['name_full'];
                    $hitem = str_replace("%".$match."%", $replacement, $hitem);
                }
                $headers[] = $hitem;
            }
        }
        if (count($headers) == 0)
            return "";
        $lines = implode($fields_separator, $headers)."\n";
        if ($filterid == 0)
            $items = $this->get_items(0, 0, 0, false);
        else
        {
            $items = array();
            $filter = CatalogCommons::get_inner_filter($filterid);
            if (empty($filter['groupid']))
                $group = false;
            else
                $group = CatalogCommons::get_group(intval($filter['groupid']));
            $sql = $this->process_variables_out($filter['query']);
            $tmpStr = "";
            $sql = $this->prepare_inner_filter_sql($sql, array(), $tmpStr);
            $filter['query'] = $sql;
            $query = $this->convert_inner_filter_query2sql($filter, $group);
            $result = $kernel->runSQL($query);
            if ($result)
            {
                while ($row = mysql_fetch_assoc($result))
                    $items[] = $row;
                mysql_free_result($result);
            }
        }
        $main_prop = $this->get_common_main_prop();
        foreach ($items as $item)
        {
            $itemFD = $this->get_item_full_data($item['id']);
            $line = $template;
            foreach ($itemFD as $prop => $value)
            {
                if ($prop == "id") //чтобы айдишник был общий, а не из таблицы тов. группы
                    $value = $itemFD["commonid"];
                elseif (is_string($value))
                    $value = '"'.str_replace('"', '\\"', $value).'"';
                $line = str_replace("%".$prop."%", $value, $line);

            }
            if ($main_prop && preg_match("|\\%linked_items\\[(.+)\\]\\%|iU", $line, $match))
            { //
                $separator = $match[1];
                $linked_items = $this->get_linked_items($itemFD["commonid"], false, 0, 0);
                $linked_names = array();
                foreach ($linked_items as $litem)
                {
                    $linked_names[] = '"'.str_replace('"', '\\"', $litem[$main_prop]).'"';
                }
                $line = str_replace($match[0], implode($separator, $linked_names), $line);
            }
            //заменим оставшиеся метки на пустую строку

            $line = preg_replace("|\\%([a-z0-9_-]+)\\%|iU", "", $line);
            $lines .= $line."\n";
        }
        return $lines;
    }

    private function show_gen_search_form($groupid)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'gen_search_form.html'));
        $content = $this->get_template_block('form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('gen_search_form'), $content);
        $props = CatalogCommons::get_props($groupid, true);
        $group = CatalogCommons::get_group($groupid);

        $templates = $this->get_template_file('modules/catalog/templates_user', true);
        $tpllines = "";
        foreach ($templates as $tpl)
        {
            $line = $this->get_template_block('template-line');
            $line = str_replace("%value%", $tpl, $line);
            $tpllines .= $line;
        }
        $content = str_replace("%template-lines%", $tpllines, $content);

        $content = str_replace("%group_name%", $group['name_full'], $content);
        $content = str_replace("%groupid%", $groupid, $content);
        $props_lines = "";
        foreach ($props as $prop)
        {
            switch ($prop['type'])
            {
                case 'string':
                case 'html':
                case 'text':
                    $pline = $this->get_template_block('string-prop');
                    break;
                case 'number':
                    $pline = $this->get_template_block('number-prop');
                    break;
                case 'enum':
                    $pline = $this->get_template_block('enum-prop');
                    break;
                case 'file':
                case 'pict':
                    $pline = $this->get_template_block('file-prop');
                    break;
                default:
                    continue 2;
            }
            $pline = str_replace("%propnamedb%", $prop['name_db'], $pline);
            $pline = str_replace("%propnamefull%", $prop['name_full'], $pline);
            $props_lines .= $pline;
        }
        $content = str_replace("%props_lines%", $props_lines, $content);
        return $content;
    }

    /**
     * Создаёт форму поиска
     * если не указаны свойства, значит это регенерация формы для поиска из админки, используем visible props тов. группы
     * @param integer $groupid
     * @param array $genprops
     * @return string
     */
    private function generate_search_form($groupid, $genprops)
    {
        global $kernel;
        if (!$genprops)
        {
            $genprops = array();
            $props = CatalogCommons::get_props($groupid, true);
            $visible_props = $this->get_group_visible_props($groupid);
            foreach ($props as $prop)
            {

                if ($prop['group_id'] == 0 && !array_key_exists($prop['name_db'], $visible_props))
                    continue;

                if ($prop['type'] == 'string' || $prop['type'] == 'text' || $prop['type'] == 'html')
                    $genprops[$prop['name_db']] = "like";
                elseif ($prop['type'] == 'number')
                    $genprops[$prop['name_db']] = "diapazon";
                elseif ($prop['type'] == 'enum')
                    $genprops[$prop['name_db']] = "select";
            }
        }

        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'search_form_tpl.html'));
        $all_group_props = CatalogCommons::get_props2($groupid) + CatalogCommons::get_props2(0);
        $group = CatalogCommons::get_group($groupid);
        $content = $this->get_template_block("header");
        foreach ($genprops as $propname => $processtype)
        {
            if ($processtype == "ignore")
                continue;
            $dbtype = $all_group_props[$propname]['type'];
            switch ($dbtype)
            {
                case 'number':
                    if ($processtype == "accurate")
                        $block = $this->get_template_block("numberinput");
                    else
                    { //diapazon
                        $block = $this->get_template_block("diapazon");
                        $block = str_replace("%diapazons_from%", $this->get_template_block("diapazons_from"), $block);
                        $block = str_replace("%diapazons_to%", $this->get_template_block("diapazons_to"), $block);
                    }
                    break;
                case 'string':
                case 'html':
                case 'text':
                    $block = $this->get_template_block("textinput");
                    break;
                case 'enum':
                    //processtype = select | radio | checkbox
                    $block = $this->get_template_block($processtype);
                    $tinfo = $kernel->db_get_table_info('_catalog_items_'.$kernel->pub_module_id_get().'_'.$group['name_db']);
                    $tinfo = $tinfo + $kernel->db_get_table_info('_catalog_'.$kernel->pub_module_id_get().'_items');
                    if ($processtype == "checkbox")
                        $enum_vals = $this->get_enum_set_prop_values($tinfo[$all_group_props[$propname]['name_db']]['Type'], false);
                    else
                    {
                        $enum_vals = $this->get_enum_set_prop_values($tinfo[$all_group_props[$propname]['name_db']]['Type'], true);
                        if (!empty($enum_vals))
                            $enum_vals[0] = "";
                    }
                    $strings = "";
                    foreach ($enum_vals as $val)
                    {
                        $string = $this->get_template_block($processtype."s");
                        $string = str_replace("%value%", $val, $string);
                        $string = str_replace("%valuekey%", $val, $string);
                        $strings .= $string."\n";
                    }
                    $block = str_replace("%".$processtype."s%", $strings, $block);
                    break;
                case 'file':
                case 'pict':
                    //processtype = select | radio | checkbox
                    $block = $this->get_template_block($processtype);
                    if ($processtype == "checkbox")
                    { //чекбокс в данном случае один
                        $string = $this->get_template_block($processtype."s");
                        $string = str_replace("%valuekey%", $propname, $string);
                        $string = str_replace("%value%", $kernel->pub_page_textlabel_replace("[#catalog_gen_search_form_file_necessary_label#]"), $string);
                        $block = str_replace("%".$processtype."s%", $string, $block);
                    }
                    else
                    { //а селектов и радио - 2
                        $strings = "";
                        $string = $this->get_template_block($processtype."s");
                        $string = str_replace("%value%", $kernel->pub_page_textlabel_replace("[#catalog_gen_search_form_file_necessary_label#]"), $string);
                        $string = str_replace("%valuekey%", $propname, $string);
                        $strings .= $string;
                        $string = $this->get_template_block($processtype."s");
                        $string = str_replace("%value%", $kernel->pub_page_textlabel_replace("[#catalog_gen_search_form_file_notnecessary_label#]"), $string);
                        $string = str_replace("%valuekey%", "", $string);
                        $strings .= $string;
                        $block = str_replace("%".$processtype."s%", $strings, $block);
                    }
                    break;
                default:
                    $block = '';
                    break;
            }


            $block = str_replace("%prop_full_name%", $all_group_props[$propname]['name_full'], $block);
            $block = str_replace("%prop_db_name%", $all_group_props[$propname]['name_db'], $block);
            $content .= $block."\n";
        }
        $content .= $this->get_template_block("footer");
        return $content;
    }


    /**
     * Генерирует запрос для внутреннего фильтра
     *
     * @param integer $groupid
     * @param array $props
     * @return string
     */
    private function generate_inner_filter_query($groupid, $props)
    {
        $all_group_props = CatalogCommons::get_props2($groupid) + CatalogCommons::get_props2(0);
        $group = CatalogCommons::get_group($groupid);
        $conditions = array("true");
        $prfx = " AND ";

        $all_group_props['id'] = array('type' => 'number', 'group_id' => 0); //для ID-шника товара

        foreach ($props as $propname => $processtype)
        {
            if ($processtype == "ignore" || !isset($all_group_props[$propname]))
                continue;

            if ($all_group_props[$propname]['group_id'] != 0) //НЕ общее свойство
                $fieldname = "`".$group['name_db']."`.`".$propname."`";
            else
                $fieldname = "`items`.`".$propname."`";

            $dbtype = $all_group_props[$propname]['type'];
            if ($dbtype == "file" || $dbtype == "pict")
            {
                if ($all_group_props[$propname]['group_id'] != 0) //НЕ общее свойство
                    $conditions[] = "REMOVE_NOT_SET[".$prfx." `".$group['name_db']."`.`param[".$propname."]` IS NOT NULL]";
                else //общее
                    $conditions[] = "REMOVE_NOT_SET[".$prfx." `param[".$propname."]` IS NOT NULL]";
                continue;
            }
            switch ($processtype)
            {
                case 'accurate':
                    $conditions[] = "REMOVE_NOT_SET[".$prfx.$fieldname."='param[".$propname."]']";
                    break;
                case 'like':
                    $conditions[] = "REMOVE_NOT_SET[".$prfx.$fieldname." LIKE '%param[".$propname."]%']";
                    break;
                case 'diapazon':
                    $conditions[] = "REMOVE_NOT_SET[".$prfx.$fieldname.">=param[".$propname."_from]]";
                    $conditions[] = "REMOVE_NOT_SET[".$prfx.$fieldname."<=param[".$propname."_to]]";
                    break;
                case 'select':
                case 'radio':
                    $conditions[] = "REMOVE_NOT_SET[".$prfx.$fieldname."='param[".$propname."]']";
                    break;
                case 'checkbox':
                    $conditions[] = "REMOVE_NOT_SET[".$prfx.$fieldname." IN (param[".$propname."])]";
                    break;
            }
        }
        $query = implode("\n", $conditions);
        return $query;
    }

    /**
     * Генерирует внутренний фильтр и сохраняет в БД
     *
     * @param string $name
     * @param string $templatename
     * @param integer $groupid
     * @param array $props
     * @return void
     */
    private function generate_inner_filter($name, $templatename, $groupid, $props)
    {
        global $kernel;
        $stringid0 = $kernel->pub_translit_string($name);
        $stringid = $stringid0;
        $i = 1;
        while (CatalogCommons::get_inner_filter_by_stringid($stringid))
        {
            $stringid = $stringid0.++$i;
        }
        $query = mysql_real_escape_string($this->generate_inner_filter_query($groupid, $props));
        $limit = "0";
        $perpage = "0";
        $maxpages = "0";
        $targetpage = "";
        $catids = "0";
        $iquery = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_inner_filters` '.
            "(`name`, `stringid`, `query`, `template`, `limit`, `perpage`, `maxpages`, `catids`, `targetpage`,`groupid`) VALUES ".
            "('".$name."','".$stringid."', '".$query."', '".$templatename."', ".$limit.",".$perpage.",".$maxpages.",'".$catids."','".$targetpage."','".$groupid."')";
        $kernel->runSQL($iquery);
    }

    /**
     * Возвращает общее свойство, по которому вести сортировку
     * @return array
     */
    private function get_common_sort_prop()
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_item_props', '`group_id`=0 AND `sorted`>0 AND module_id="'.$kernel->pub_module_id_get().'"', '`name_db`,`sorted`');
    }

    /**
     * Публичный метод для вывода списка связанных товаров
     *
     * @param string $template
     * @param integer $limit
     * @return string
     */
    public function pub_catalog_show_linked_items($template, $limit)
    {
        global $kernel;
        $itemid = intval($kernel->pub_httpget_get($this->frontend_param_item_id_name));
        if ($itemid < 1)
            return "";
        //если нет параметра - айдишника товара, нам нечего выводить

        $this->set_templates($kernel->pub_template_parse($template));

        $offset = $this->get_offset_user();
        $items = array_values($this->get_linked_items($itemid, true, $offset, $limit));

        $count = count($items);
        $total = $this->get_linked_items_count($itemid, true);


        if ($count == 0)
        {
            return $this->get_template_block('list_null');
            //return "Нет товаров";
        }
        $itemids = array();
        //проверим, принадлежат ли все товары к одной товарной группе
        //и сохраним id-шники
        $is_single_group = true;

        $groupid = $items[0]['group_id'];
        foreach ($items as $item)
        {
            $itemids[] = $item['ext_id'];
            if ($groupid != $item['group_id'])
            {
                $is_single_group = false;
                break;
            }
        }

        if ($is_single_group)
        {
            $group = CatalogCommons::get_group($groupid);
            $items2 = $this->get_group_items($group['name_db'], $itemids);
            $newitems = array();
            foreach ($items as $item)
            {
                $tmp = $item + $items2[$item['ext_id']];
                $tmp['id'] = $item['id'];
                $newitems[] = $tmp;
            }
            $items = $newitems;
            $props = CatalogCommons::get_props($groupid, true);
        }
        else
            $props = CatalogCommons::get_common_props($kernel->pub_module_id_get(), false);


        //при этом, надо пройтись по свойствам и если там есть
        //картинки, то нужно продублировать их свойствами большого
        //и маленького изображения

        for ($p = 0; $p < count($props); $p++)
        {
            if (isset($props[$p]['add_param']))
                $props[$p]['add_param'] = unserialize($props[$p]['add_param']);
        }

        //Сформируем сначала строки с товарами
        $rows = '';
        $curr = 1;
        foreach ($items as $item)
        {
            if ($curr % 2 == 0) //строка - чётная
                $odd_even = "even";
            else
                $odd_even = "odd";
            //Взяли блок строчки
            $block = $this->get_template_block('row_'.$odd_even);
            if (empty($block))
                $block = $this->get_template_block('row');
            $block = str_replace("%odd_even%", $odd_even, $block);
            //Теперь ищем переменные свойств и заменяем их
            $block = $this->process_item_props_out($item, $props, $block, CatalogCommons::get_group($groupid));
            $block = str_replace("%link%", $kernel->pub_page_current_get().'.html?'.$this->frontend_param_item_id_name.'='.$item['id'], $block);
            $rows .= $block;
            $curr++;
        }

        $content = $this->get_template_block('list');
        $content = str_replace("%row%", $rows, $content);
        $content = str_replace("%total_in_cat%", $total, $content);
        $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit, $kernel->pub_page_current_get().'.html?'.$this->frontend_param_item_id_name.'='.$itemid.'&'.$this->frontend_param_offset_name.'=', 0, 'link'), $content);
        $content = $this->process_variables_out($content);
        $content = $this->replace_current_page_url($content);
        //очистим оставшиеся метки
        $content = $this->clear_left_labels($content);
        return $content;
    }

    /**
     * Возвращает "основное" общее свойство
     *
     * @return string|boolean
     */
    private function get_common_main_prop()
    {
        global $kernel;
        $row = $kernel->db_get_record_simple('_catalog_item_props', '`module_id`="'.$kernel->pub_module_id_get().'" AND `group_id`=0 AND `ismain`=1', 'name_db');
        if ($row)
            return $row['name_db'];
        return false;
    }

    private function show_variables()
    {
        global $kernel;
        $items = CatalogCommons::get_variables();
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'variables.html'));
        $html = $this->get_template_block('header');
        $content = '';
        if (count($items) > 0)
        {
            $content .= $this->get_template_block('table_header');
            //$content     = str_replace('%form_action%', $kernel->pub_redirect_for_form('regen_tpls4groups'), $content);
            foreach ($items as $item)
            {
                $line = $this->get_template_block('table_body');
                $line = str_replace('%name_db%', $item['name_db'], $line);
                $line = str_replace('%name_full%', $item['name_full'], $line);
                $line = str_replace('%value%', $item['value'], $line);
                $content .= $line;
            }
            $content .= $this->get_template_block('table_footer');
        }
        else
            $content = $this->get_template_block('no_data');
        $html = str_replace("%table%", $content, $html);
        return $html;
    }

    private function show_variable_form($name_db)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'variable.html'));
        if (empty($name_db))
        {
            $form_header_txt = '[#catalog_edit_variable_header_label#]';
            $item = array("name_db" => "", "name_full" => "", "value" => "");
        }
        else
        {
            $form_header_txt = '[#catalog_edit_variable_header_label#]';
            $item = CatalogCommons::get_variable($name_db);
        }

        $content = $this->get_template_block('form');
        $content = str_replace('%form_header_txt%', $form_header_txt, $content);
        $content = str_replace('%value%', $item['value'], $content);
        $content = str_replace('%name_full%', $item['name_full'], $content);
        $content = str_replace('%name_db%', $item['name_db'], $content);
        $content = str_replace('%prev_name_db%', $item['name_db'], $content);
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('variable_save'), $content);
        return $content;
    }

    /**
     *  сохраняет настройки для импорта commerceml
     *  пока в сессии, позже в БД
     * @param $settings array
     * @return void
     */
    private function save_import_commerceml_settings($settings)
    {
        $_SESSION['import_commerceml_settings'] = serialize($settings);
    }

    /**
     *  возвращает настройки для импорта commerceml
     *  пока в сессии, позже в БД
     * @return array
     */
    private function get_import_commerceml_settings()
    {
        /*
        global $kernel;
        $settings=$kernel->pub_session_get('import_commerceml_settings');
        if (!$settings)
        {
            //если нет сохранённых - создадим заглушки
            $settings=array('groupid'=>-1,'catid'=>-1,'assoc'=>array(),'priceField'=>'','pricePerField'=>'');
        }
        */
        if (isset($_SESSION['import_commerceml_settings']))
            return unserialize($_SESSION['import_commerceml_settings']);
        else
            return array('groupid' => -1, 'catid' => -1, 'assoc' => array(), 'priceField' => '', 'pricePerField' => '', 'priceType' => '', 'ID_field' => '', 'name_field' => '');
    }

    private function import_commerceml_buildprops_select($commonProps, $selected)
    {
        $options = '';
        foreach ($commonProps as $cprop)
        {
            if ($cprop['name_db'] == $selected)
                $line = $this->get_template_block('common_prop_option_selected');
            else
                $line = $this->get_template_block('common_prop_option');

            $line = str_replace('%name_db%', $cprop['name_db'], $line);
            $line = str_replace('%name_full%', $cprop['name_full'], $line);
            $options .= $line;
        }
        return $options;
    }

    private function import_commerceml()
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'import_commerceml.html'));

        $msg = '';
        $settings = $this->get_import_commerceml_settings();
        if (isset($_POST['upload_import_file']))
        {
            //сначала заполнение всех настроек из POST, чтобы они сохранились даже в случае ошибки
            $settings['catid'] = intval($kernel->pub_httppost_get('catid'));
            $settings['groupid'] = intval($kernel->pub_httppost_get('groupid'));
            $settings['priceField'] = $kernel->pub_httppost_get('price_field', false);
            $settings['pricePerField'] = $kernel->pub_httppost_get('price_per_field', false);
            $settings['ID_field'] = $kernel->pub_httppost_get('ID_field', false);
            $settings['name_field'] = $kernel->pub_httppost_get('name_field', false);
            $settings['priceType'] = trim($kernel->pub_httppost_get('priceType', false));
            $settings['assoc'] = array(); //очистим, и заполним данными из POST
            if (isset($_POST['assocprops']))
            {
                foreach ($_POST['assocprops'] as $aprop)
                {
                    if (isset($aprop['name']) && isset($aprop['propid']) && !empty($aprop['name']) && !empty($aprop['propid']))
                        $settings['assoc'][trim($aprop['name'])] = $aprop['propid'];
                }
            }

            $this->save_import_commerceml_settings($settings);

            if ($kernel->pub_httppost_get('import_type', false) == 'import')
                $import_type = 'import'; //информация о товарах
            else
                $import_type = 'offers';
            //цены
            $shouldProcess = true;
            if (!isset($_FILES['commerceml_file']) || !is_uploaded_file($_FILES['commerceml_file']['tmp_name']))
            {
                $shouldProcess = false;
                $msg .= '[#catalog_commerceml_error_nofile#]';
            }
            elseif (!$settings['ID_field'])
            {
                $shouldProcess = false;
                $msg = '[#catalog_commerceml_error_reqfields_not_filled#]'; //не заполнены обязательные поля
            }
            else
            {
                //для разных типов импорта разные обязательные поля
                if ($import_type == 'import')
                {
                    if (!$settings['groupid'] || !$settings['name_field'])
                    {
                        $shouldProcess = false;
                        $msg = '[#catalog_commerceml_error_reqfields_not_filled#]'; //не заполнены обязательные поля
                    }
                }
                else
                {
                    if (!$settings['priceField'] || !$settings['priceType'])
                    {
                        $shouldProcess = false;
                        $msg = '[#catalog_commerceml_error_reqfields_not_filled#]'; //не заполнены обязательные поля
                    }
                }
            }

            if ($shouldProcess)
            {
                libxml_use_internal_errors(true); //чтобы не было ворнингов
                $xml = simplexml_load_file($_FILES['commerceml_file']['tmp_name']);

                $moduleid = $kernel->pub_module_id_get();
                if ($import_type == 'import')
                {
                    $group = CatalogCommons::get_group($settings['groupid']);
                    if (!$group)
                        $msg = '[#catalog_commerceml_error_no_group#]';
                    elseif (!$xml || !isset($xml->{'Классификатор'}->{'Свойства'}) || !isset($xml->{'Каталог'}->{'Товары'}))
                        $msg = '[#catalog_commerceml_error_malformed_xml#]';
                    else
                    {
                        //пройдём по свойствам, запомним ID
                        $assocs = array();
                        foreach ($xml->{'Классификатор'}->{'Свойства'}[0] as $prop)
                        {
                            $propID = (string)$prop->{'Ид'};
                            $propName = (string)$prop->{'Наименование'};
                            if (isset($settings['assoc'][$propName])) //
                                $assocs[$propID] = $propName;
                        }
                        $updated = 0;
                        $added = 0;
                        foreach ($xml->{'Каталог'}->{'Товары'}[0] as $item)
                        {
                            $item1Cid = (string)$item->{'Ид'};
                            $item1Cname = (string)$item->{'Наименование'};
                            $commonFields = array("`".$settings['name_field']."`" => "'".mysql_real_escape_string($item1Cname)."'");
                            $groupFields = array();
                            if (isset($item->{'ЗначенияСвойств'}[0]))
                            {
                                foreach ($item->{'ЗначенияСвойств'}[0] as $fvalue)
                                {
                                    $propID = (string)$fvalue->{'Ид'};
                                    if (!isset($assocs[$propID]))
                                        continue;

                                    $propValue = (string)$fvalue->{'Значение'};
                                    $propDbName = $settings['assoc'][$assocs[$propID]];
                                    if (preg_match('~^group0_~', $propDbName))
                                    {
                                        $propDbName = substr($propDbName, 7);
                                        $commonFields["`".$propDbName."`"] = "'".mysql_real_escape_string($propValue)."'";
                                    }
                                    else
                                        $groupFields["`".$propDbName."`"] = "'".mysql_real_escape_string($propValue)."'";
                                }
                            }
                            $exRec = $kernel->db_get_record_simple('_catalog_'.$moduleid.'_items', "`".$settings['ID_field']."`='".mysql_real_escape_string($item1Cid)."'");


                            if ($exRec)
                            {
                                if (count($groupFields))
                                {
                                    $q = "UPDATE `".$kernel->pub_prefix_get()."_catalog_items_".$moduleid."_".strtolower($group['name_db'])."`
                                    SET ";
                                    foreach ($groupFields as $k => $v)
                                    {
                                        $q .= $k."=".$v.", ";
                                    }
                                    $q .= " id=".$exRec['ext_id']." WHERE id='".$exRec['ext_id']."'";
                                    $kernel->runSQL($q);
                                }
                                //в $commonFields как минимум одно свойство из-за name
                                $q = "UPDATE `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_items`
                                    SET ";
                                foreach ($commonFields as $k => $v)
                                {
                                    $q .= $k."=".$v.", ";
                                }
                                $q .= " id=".$exRec['id']." WHERE id='".$exRec['id']."'";
                                $kernel->runSQL($q);
                                $updated++;
                            }
                            else
                            {
                                $groupFields["`id`"] = "NULL";
                                $commonFields["`".$settings['ID_field']."`"] = "'".mysql_real_escape_string($item1Cid)."'";
                                $query = "INSERT INTO `".$kernel->pub_prefix_get()."_catalog_items_".$moduleid."_".strtolower($group['name_db'])."`
                                          (".implode(",", array_keys($groupFields)).")
                                          VALUES
                                          (".implode(",", $groupFields).")";
                                $kernel->runSQL($query);
                                $ext_id = mysql_insert_id();

                                //в $commonFields как минимум одно свойство из-за name и 1СID
                                $q = "INSERT INTO `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_items`
                                    (".implode(",", array_keys($commonFields)).",`available`,`group_id`,`ext_id`)
                                    VALUES
                                    (".implode(",", $commonFields).",'1','".$settings['groupid']."','".$ext_id."')";
                                $kernel->runSQL($q);

                                if ($settings['catid'])
                                { //если надо - добавляем в категорию
                                    $newItemID = mysql_insert_id();
                                    $newOrdr = $this->get_next_order_in_cat($settings['catid']);
                                    $query = "INSERT INTO `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_item2cat` ".
                                        "(`cat_id`,`item_id`,`order`) VALUES ".
                                        "(".$settings['catid'].", ".$newItemID.", ".$newOrdr.")";
                                    $kernel->runSQL($query);
                                }
                                $added++;
                            }
                        }
                        $msg = $kernel->pub_page_textlabel_replace("[#catalog_commerceml_import_completed#]");
                        $msg = str_replace('%added%', $added, $msg);
                        $msg = str_replace('%updated%', $updated, $msg);

                    }
                }
                else
                { //offers.xml
                    if (!$xml || !isset($xml->{'ПакетПредложений'}->{'Предложения'}) || !isset($xml->{'ПакетПредложений'}->{'ТипыЦен'}))
                        $msg = '[#catalog_commerceml_error_malformed_xml#]';
                    else
                    {

                        $priceTypeID = false;
                        foreach ($xml->{'ПакетПредложений'}->{'ТипыЦен'}[0] as $priceType)
                        {
                            if ((string)$priceType->{'Наименование'} == $settings['priceType'])
                            {
                                $priceTypeID = (string)$priceType->{'Ид'};
                                break;
                            }
                        }
                        if (!$priceTypeID)
                            $msg = '[#catalog_commerceml_error_no_pricetype_found#]';
                        else
                        {
                            $updated = 0;
                            foreach ($xml->{'ПакетПредложений'}->{'Предложения'}[0] as $offer)
                            {
                                $offerID = (string)$offer->{'Ид'};


                                if (!isset($offer->{'Цены'}[0]))
                                    continue;

                                $oprice = false;
                                foreach ($offer->{'Цены'}[0] as $opriceElem)
                                {
                                    if ($priceTypeID == (string)$opriceElem->{'ИдТипаЦены'})
                                    {
                                        $oprice = $opriceElem;
                                        break;
                                    }
                                }
                                if (!$oprice)
                                    continue;

                                $price = $oprice->{'ЦенаЗаЕдиницу'};
                                $q = "UPDATE `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_items`
                                SET `".$settings['priceField']."`='".mysql_real_escape_string($price)."' ";
                                if ($settings['pricePerField'])
                                    $q .= ",`".$settings['pricePerField']."`='".(string)$oprice->{'Единица'}."'";
                                $q .= " WHERE `".$settings['ID_field']."`='".mysql_real_escape_string($offerID)."'";
                                $kernel->runSQL($q);
                                $updated++;
                            }
                            $msg = $kernel->pub_page_textlabel_replace("[#catalog_commerceml_offers_completed#]");
                            $msg = str_replace('%updated%', $updated, $msg);
                        }
                    }
                }

            }

            $_SESSION['import_commerceml_msg'] = $msg;
            $kernel->pub_redirect_refresh_reload("import_commerceml");
        }

        if (isset($_SESSION['import_commerceml_msg']) && !empty($_SESSION['import_commerceml_msg']))
        {
            $msg = $_SESSION['import_commerceml_msg'];
            unset($_SESSION['import_commerceml_msg']);
        }

        $content = $this->get_template_block('content');

        $currGroupProps = array();
        $gprops = '';
        $groups = CatalogCommons::get_groups();
        $gblock = '';
        foreach ($groups as $group)
        {
            $props = CatalogCommons::get_props($group['id'], true);
            if ($group['id'] == $settings['groupid'])
            {
                foreach ($props as $prop)
                {
                    $opt_name = $prop['name_db'];
                    if ($prop['group_id'] == 0)
                        $opt_name = 'group0_'.$opt_name;
                    $currGroupProps[$opt_name] = $prop['name_full'];
                }
                $gblock .= $this->get_template_block('group_item_selected');

            }
            else
                $gblock .= $this->get_template_block('group_item');
            $gblock = str_replace('%group_id%', $group['id'], $gblock);
            $gblock = str_replace('%group_name%', htmlspecialchars($group['name_full']), $gblock);


            $thisGroupProps = array();
            foreach ($props as $prop)
            {
                if ($prop['type'] == 'file' || $prop['type'] == 'pict')
                    continue;
                $opt_name = $prop['name_db'];
                if ($prop['group_id'] == 0)
                    $opt_name = 'group0_'.$opt_name;
                $thisGroupProps[] = '{"name_db":"'.$opt_name.'","name_full":"'.$prop['name_full'].'"}';
            }
            $gprops .= '"'.$group['id'].'":['.implode(",", $thisGroupProps).'],'."\n";
        }

        $props_assoc_lines = '';
        $assocLinesCount = 0;
        foreach ($settings['assoc'] as $name1C => $nameDB)
        {
            if (!array_key_exists($nameDB, $currGroupProps))
                continue;
            $assocLinesCount++;
            $line = $this->get_template_block('props_assoc_line');

            $proplines = '';
            foreach ($currGroupProps as $propNameDB => $propNameFull)
            {
                if ($propNameDB == $nameDB)
                    $propline = $this->get_template_block('propline_selected');
                else
                    $propline = $this->get_template_block('propline');
                $propline = str_replace('%namedb%', $propNameDB, $propline);
                $propline = str_replace('%namefull%', $propNameFull, $propline);
                $proplines .= $propline;
            }
            $line = str_replace('%proplines%', $proplines, $line);
            $line = str_replace('%name1C%', htmlspecialchars($name1C), $line);

            $props_assoc_lines .= $line;
        }

        $commonProps = CatalogCommons::get_common_props($kernel->pub_module_id_get());

        $price_per_field_options = $this->import_commerceml_buildprops_select($commonProps, $settings['pricePerField']);
        $price_field_options = $this->import_commerceml_buildprops_select($commonProps, $settings['priceField']);
        $ID_field_options = $this->import_commerceml_buildprops_select($commonProps, $settings['ID_field']);
        $name_field_options = $this->import_commerceml_buildprops_select($commonProps, $settings['name_field']);

        $cats = $this->get_child_categories(0, 0, array());
        $options = '';
        $cat_shift = $this->get_template_block('cat_shift');
        foreach ($cats as $cat)
        {
            if ($cat['id'] == $settings['catid'])
                $option = $this->get_template_block('cat_option_selected');
            else
                $option = $this->get_template_block('cat_option');
            $option = str_replace('%cat_id%', $cat['id'], $option);
            $option = str_replace('%cat_name%', str_repeat($cat_shift, $cat['depth']).$cat['name'], $option);
            $options .= $option;
        }
        $content = str_replace('%cats_options%', $options, $content);
        $content = str_replace('var currAssocLines=0;', 'var currAssocLines='.$assocLinesCount.';', $content);
        $content = str_replace('%props_assoc_lines%', $props_assoc_lines, $content);
        $content = str_replace('%groups%', $gblock, $content);
        $content = str_replace('/*gprops*/', $gprops, $content);
        $content = str_replace('%price_field_options%', $price_field_options, $content);
        $content = str_replace('%price_per_field_options%', $price_per_field_options, $content);
        $content = str_replace('%ID_field_options%', $ID_field_options, $content);
        $content = str_replace('%name_field_options%', $name_field_options, $content);
        $content = str_replace('%priceType%', htmlspecialchars($settings['priceType']), $content);


        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('import_commerceml'), $content);
        $content = str_replace('%msg%', $msg, $content);
        return $content;
    }


    private function delete_group($groupid)
    {
        global $kernel;
        $group = CatalogCommons::get_group($groupid);
        if (!$group)
            return;
        $modid = $kernel->pub_module_id_get();
        $crec = $kernel->db_get_record_simple("_catalog_".$modid."_items", "group_id=".$group['id'], "COUNT(*) AS count");
        //не удаляем, если есть товары в группе
        if ($crec['count'] > 0)
            return;
        $prfx = $kernel->pub_prefix_get();
        $q = "DELETE FROM ".$prfx."_catalog_item_props WHERE group_id='".$group['id']."' AND module_id='".$modid."'";
        $kernel->runSQL($q);
        $q = "DELETE FROM ".$prfx."_catalog_visible_gprops WHERE group_id='".$group['id']."' AND module_id='".$modid."'";
        $kernel->runSQL($q);
        $q = "DELETE FROM ".$prfx."_catalog_item_groups WHERE id='".$group['id']."'";
        $kernel->runSQL($q);
        $q = "DELETE FROM  ".$prfx."_catalog_".$modid."_inner_filters WHERE groupid='".$group['id']."'";
        $kernel->runSQL($q);
        $this->generate_search_form($groupid, array());
    }

    /** Возвращает результаты быстрого поиска
     * @param string $term запрос
     * @param integer $catid ID категории
     * @param array $ignoreItemIds айдишники товаров, которые НЕ надо включать в результаты
     * @return array
     */
    public function get_quicksearch_results($term, $catid = null, $ignoreItemIds = array())
    {
        global $kernel;
        $terms = explode(" ", trim($term));
        if (!$terms)
            return array();
        $moduleid = $kernel->pub_module_id_get();
        $props = CatalogCommons::get_props(0, true);


        $addcond = "";
        if ($catid)
            $addcond .= " AND id IN (SELECT item_id FROM ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_item2cat WHERE cat_id=".$catid.")";
        if ($ignoreItemIds)
            $addcond .= " AND id NOT IN (".implode(",", $ignoreItemIds).")";

        //сначала пробуем найти товары со ВСЕМИ словами из запроса (AND-логика)
        $cond = $this->get_like_condition_for_terms($terms, $props, "AND");
        $results = $kernel->db_get_list_simple("_catalog_".$moduleid."_items", $cond.$addcond, "*", 0, 100);
        if (!$results)
        { //если не нашли, пробуем найти хотя бы с одним словом из запроса (OR-логика)
            $cond = $this->get_like_condition_for_terms($terms, $props, "OR");
            $results = $kernel->db_get_list_simple("_catalog_".$moduleid."_items", $cond.$addcond, "*", 0, 100);
        }

        $sep = " | ";
        foreach ($results as &$r)
        {
            $stringBlocks = array();
            foreach ($props as $prop)
            {
                if (!$prop['showinlist'] || $prop['type'] == 'file' || $prop['type'] == 'pict')
                    continue;
                $strval = $r[$prop['name_db']];
                if (mb_strlen($strval) == 0)
                    continue;
                $stringBlocks[] = $strval;
            }
            $r['_string'] = strip_tags(implode($sep, $stringBlocks));
        }
        return $results;
    }


    private function get_like_condition_for_terms($terms, $props, $separator = "OR")
    {
        $tblocks = array();
        foreach ($terms as $t)
        {
            $t = trim($t);
            if (mb_strlen($t) == 0)
                continue;
            $propsblocks = array();
            foreach ($props as $prop)
            {
                if (!in_array($prop['type'], array('enum', 'string', 'number')))
                    continue;
                $propsblocks[] = "`".$prop['name_db']."` LIKE '%".mysql_real_escape_string($t)."%'";
            }
            $tblocks[] = "(".implode(" OR ", $propsblocks).")";
        }
        return "(".implode(" ".$separator." ", $tblocks).")";
    }

    /**
     * Функция для отображения административного интерфейса
     *
     * @return string
     */
    public function start_admin()
    {
        global $kernel;
        $action = $kernel->pub_section_leftmenu_get();
        //если это не работа с деревом, "забудем" куку с выделенной нодой
        if (!in_array($action, array('category_items', 'category_move', 'category_items_save', 'save_selected_items', 'item_edit', 'item_save')))
            setcookie($this->structure_cookie_name, "");
        $moduleid = $kernel->pub_module_id_get();
        switch ($action)
        {
            case 'get_item_subcats_block':
                $this->set_templates($kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'items_edit.html'));
                $cid = intval($kernel->pub_httpget_get('cid'));
                $catsblock = $this->build_item_categories_block($cid);
                return "<ul>".$catsblock."</ul>";

            //ajax-поиск товаров
            case 'get_items_quicksearch_result':
                $term = $kernel->pub_httpget_get('term', false);
                $catid = intval($kernel->pub_httpget_get('catid', false));
                $ignoredid = intval($kernel->pub_httpget_get('ignored', false));
                if ($ignoredid)
                    $ignored = array($ignoredid);
                else
                    $ignored = array();
                $results = $this->get_quicksearch_results($term, $catid, $ignored);
                $jdata = array();
                foreach ($results as $result)
                {
                    $jdata[] = array("label" => $result['_string'], "value" => $result['id']);
                }
                return $kernel->pub_json_encode($jdata);
                break;

            //Удаление тов.группы
            case 'delete_group':
                $this->delete_group($kernel->pub_httpget_get('id'));
                $kernel->pub_redirect_refresh("show_groups");
                break;

            //импорт из 1С-формата CommerceML
            case 'import_commerceml':
                return $this->import_commerceml();

            case 'item_clone':
                $id = $kernel->pub_httpget_get('id');
                $newID = $this->item_clone($id);
                if ($newID)
                    return $this->show_item_form($newID, 0);
                else
                    return $this->show_item_form($id, 0);

            case 'show_variables':
                return $this->show_variables();

            case 'variable_delete':
                $namedb = $kernel->pub_httpget_get('name_db');
                $kernel->runSQL("DELETE FROM `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_variables` WHERE `name_db`='".$namedb."'");
                $kernel->pub_redirect_refresh('show_variables');
                break;

            case 'show_variable_form':
                $name_db = $kernel->pub_httpget_get("name_db");
                return $this->show_variable_form($name_db);

            case 'variable_save':
                $name_full = trim($kernel->pub_httppost_get('name_full'));
                $name_db = trim($kernel->pub_httppost_get('name_db'));
                $prev_name_db = trim($kernel->pub_httppost_get('prev_name_db'));
                $value = trim($kernel->pub_httppost_get('value'));
                if (!preg_match("/^([0-9a-z_]+)$/i", $name_db))
                    return $kernel->pub_httppost_response("[#catalog_variable_save_msg_incorrect_namedb#]");
                elseif (empty($name_full))
                    return $kernel->pub_httppost_response("[#catalog_variable_save_msg_empty_name_full#]");
                elseif (empty($value))
                    return $kernel->pub_httppost_response("[#catalog_variable_save_msg_empty_value#]");
                else
                {
                    if (empty($prev_name_db))
                        $query = "REPLACE INTO `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_variables` ".
                            "(`name_db`,`name_full`,`value`) VALUES ".
                            "('".$name_db."','".$name_full."','".$value."')";
                    else
                        $query = "UPDATE `".$kernel->pub_prefix_get()."_catalog_".$moduleid."_variables` ".
                            "SET `name_db`='".$name_db."',`name_full`='".$name_full."',`value`='".$value."' ".
                            "WHERE `name_db`='".$prev_name_db."'";
                    $kernel->runSQL($query);
                    return $kernel->pub_httppost_response("[#catalog_variable_save_msg_ok#]", "show_variables");
                }

            case 'show_gen_search_form':
                $groupid = $kernel->pub_httpget_get('groupid', false);
                if (empty($groupid))
                    $groupid = $kernel->pub_httppost_get('groupid', false);
                return $this->show_gen_search_form($groupid);

            case 'gen_search_form':
                $groupid = $kernel->pub_httppost_get('id', false);
                $outfile = $kernel->pub_httppost_get('outfile');
                $filtername = $kernel->pub_httppost_get('filtername');
                $filtertpl = $kernel->pub_httppost_get('filtertpl');
                $props = $_POST['prop'];
                if (!empty($outfile))
                {
                    $content = $this->generate_search_form($groupid, $props);
                    $ext = mb_substr(mb_strtolower($outfile), -4);
                    if ($ext != "html" && $ext != ".htm")
                        $outfile .= ".html";
                    $kernel->pub_file_save("modules/catalog/templates_user/".$outfile, $content);
                }
                if (!empty($filtername) && !empty($filtertpl))
                {
                    $this->generate_inner_filter($filtername, $filtertpl, $groupid, $props);
                }
                $kernel->pub_redirect_refresh_reload("show_groups");
                return '';

            case 'show_csv_export':
                return $this->show_csv_export_form();

            case 'make_csv_export':
                $template = $kernel->pub_httppost_get('template', false);
                $filterid = intval($kernel->pub_httppost_get('filterid', false));
                $exported = $this->make_csv_export($template, $filterid);
                header('Content-type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename=export.csv');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: '.mb_strlen($exported));
                //ob_clean();
                flush();
                print $exported;
                exit;

            case 'show_order_fields':
                return $this->show_order_fields();

            case 'save_order_fields_order':
                $this->save_order_fields_order();
                $kernel->pub_redirect_refresh_reload("show_order_fields");
                break;

            //Вызов формы редактирования добавления/конкретного поля корзины
            case 'order_field_edit':
                $id = $kernel->pub_httpget_get('id');
                return $this->show_order_field_form($id);

            case 'order_field_save':
                $id = $kernel->pub_httppost_get('id', false);
                $name_db = $kernel->pub_httppost_get('name_db', false);
                $name_full = $kernel->pub_httppost_get('name_full', false);
                $req = $kernel->pub_httppost_get('req', false);
                $regexp = $kernel->pub_httppost_get('regexp', false);
                $this->save_order_field($id, $name_full, $name_db, $regexp, $req);
                return $kernel->pub_httppost_response("[#catalog_order_field_saved_msg#]", "show_order_fields");

            case 'order_field_add':
                $name_db = $kernel->pub_httppost_get('name_db', false);
                $name_full = $kernel->pub_httppost_get('name_full', false);
                $req = $kernel->pub_httppost_get('req', false);
                $regexp = $kernel->pub_httppost_get('regexp', false);
                $ptype = $kernel->pub_httppost_get('ptype', false);
                $values = $kernel->pub_httppost_get('enum_values', false);
                $this->add_order_field($name_full, $name_db, $regexp, $req, $ptype, $values);
                return $kernel->pub_httppost_response("[#catalog_order_field_saved_msg#]", "show_order_fields");
                break;
            case 'order_field_delete':
                $this->delete_order_field($kernel->pub_httpget_get('id'));
                $kernel->pub_redirect_refresh("show_order_fields");
                break;

            case 'order_enum_field_delete':
                $id = $kernel->pub_httpget_get("id");
                $this->delete_order_enum_field($id);
                return $this->show_order_field_form($id);

            case 'order_enum_field_add':
                $id = $kernel->pub_httppost_get("id");
                $this->add_order_enum_field($id);
                $str = "order_field_edit&id=".$id;
                return $kernel->pub_httppost_response("[#catalog_edit_property_enum_add_msg#]", $str);

            case 'regenerate_order_tpls':
                $basket_items_tpl = $kernel->pub_httppost_get("basket_items_tpl");
                $basket_items_tpl_translit = $kernel->pub_translit_string($basket_items_tpl);
                $ext = strtolower(substr($basket_items_tpl_translit, -4));
                if ($ext != "html" && $ext != ".htm")
                    $basket_items_tpl_translit .= ".html";
                $basket_order_tpl = $kernel->pub_httppost_get("order_form_tpl");
                $basket_order_tpl_translit = $kernel->pub_translit_string($basket_order_tpl);
                $ext = strtolower(substr($basket_order_tpl_translit, -4));
                if ($ext != "html" && $ext != ".htm")
                    $basket_order_tpl_translit .= ".html";
                $this->regenerate_basket_items_tpl($basket_items_tpl_translit);
                $this->regenerate_basket_order_tpl($basket_order_tpl_translit);
                //значит была транслитерация
                if ($basket_order_tpl_translit != $basket_order_tpl || $basket_items_tpl_translit != $basket_items_tpl)
                    return $kernel->pub_httppost_response("[#catalog_order_tpls_regenerated_translit_msg#]", "show_order_fields");
                else
                    return $kernel->pub_httppost_response("[#catalog_order_tpls_regenerated_msg#]", "show_order_fields");

            case 'inner_filter_delete':
                $this->delete_inner_filter($kernel->pub_httpget_get("id"));
                $kernel->pub_redirect_refresh("show_inner_filters");
                break;

            case 'test_filter':
                return $this->test_filter($kernel->pub_httpget_get("id"));

            case 'show_inner_filters':
                return $this->show_inner_filters();

            case 'show_inner_filter_form':
                $id = $kernel->pub_httpget_get("id");
                return $this->show_inner_filter_form($id);

            case 'inner_filter_save':
                $id = $kernel->pub_httppost_get("id");
                $saveError = $this->save_inner_filter($id);
                if (!$saveError)
                    return $kernel->pub_httppost_response("[#catalog_edit_inner_filter_save_msg_ok#]", "show_inner_filters");
                else
                    return $kernel->pub_httppost_response("[#catalog_edit_inner_filter_save_msg_error#]", $saveError);

            case 'category_move':
                $cid = $kernel->pub_httppost_get("node");
                $parentNew = $kernel->pub_httppost_get("newParent");
                $indexNew = $kernel->pub_httppost_get("index");
                $order2replace = $this->get_last_order_in_cat($parentNew, $indexNew);
                $query = "UPDATE `".$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_cats` SET `order`='.($order2replace + 1).' WHERE `parent_id`='.$parentNew.' AND `order`='.$order2replace;
                $kernel->runSQL($query);
                $query = "UPDATE `".$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_cats` SET `parent_id`='.$parentNew.', `order`='.$order2replace.' WHERE `id`='.$cid;
                $kernel->runSQL($query);
                break;

            case 'save_selected_items':
                $group_id = $kernel->pub_httppost_get('group_id');
                $change_items = $kernel->pub_httppost_get('change_items');
                if (empty($change_items))
                    $this->save_selected_items();
                else
                    $this->change_selected_items();
                $kernel->pub_redirect_refresh_reload('show_items&group_id='.$group_id);
                break;

            case 'import_csv': //первый шаг - показываем форму для upload
                $newfilename = $kernel->pub_session_get('importcsv_filename');
                $group_id = $kernel->pub_session_get('importcsv_groupid');
                $separator = $kernel->pub_session_get('importcsv_separator');
                //если в сессии есть значения для второго шага импорта, покажем таблицу с первыми 10 эл-тами
                if (!is_null($newfilename) && !is_null($group_id) && !is_null($separator))
                    return $this->show_import_csv_table($group_id, $newfilename, $separator);
                else //иначе - начальную форму
                    return $this->show_import_csv_form();

            case 'import_csv2': //сохраняем в сессии данные для импорта
                $group_id = $kernel->pub_httppost_get('group');
                $separator = $kernel->pub_httppost_get('separator');
                $cat_id = intval($kernel->pub_httppost_get('cat'));
                $cat_id4new = intval($kernel->pub_httppost_get('cat4new'));
                $import_from = $kernel->pub_httppost_get("importfrom");
                if ($separator == 'tab')
                    $sep = "\t";
                else
                    $sep = ";";
                $need_save = false;
                $newfilename = '';
                if ($import_from == "textarea")
                { //через буфер обмена
                    $buffer = trim($kernel->pub_httppost_get("textarea", false));
                    if (mb_strlen($buffer) > 0)
                    {
                        $buffer = str_replace('\r\n', "\n", $buffer);
                        $newfilename = 'content/files/'.$moduleid.'/buffer_'.time().'.txt';
                        $kernel->pub_file_save($newfilename, $buffer);
                        $need_save = true;
                    }
                }
                else if (isset($_FILES['importcsv']) && is_uploaded_file($_FILES['importcsv']['tmp_name']))
                { //из файла, если он закачан
                    $newfilename = $this->process_file_upload($_FILES['importcsv']);
                    $need_save = true;
                }
                if ($need_save)
                {
                    $kernel->pub_session_set('importcsv_filename', $newfilename);
                    $kernel->pub_session_set('importcsv_groupid', $group_id);
                    $kernel->pub_session_set('importcsv_separator', $sep);
                    $kernel->pub_session_set('importcsv_catid', $cat_id);
                    $kernel->pub_session_set('importcsv_catid4new', $cat_id4new);
                }
                $kernel->pub_redirect_refresh_reload('import_csv');
                break;

            case 'import_csv3': //импортируем весь файл на основе настроек, выбранных админом
                $newfilename = $kernel->pub_session_get('importcsv_filename');
                $group_id = $kernel->pub_session_get('importcsv_groupid');
                $cat_id = $kernel->pub_session_get('importcsv_catid');
                $cat_id4new = $kernel->pub_session_get('importcsv_catid4new');
                $separator = $kernel->pub_session_get('importcsv_separator');
                $this->make_csv_import($group_id, $newfilename, $separator, $cat_id, $cat_id4new);
                $kernel->pub_session_unset('importcsv_filename');
                $kernel->pub_session_unset('importcsv_groupid');
                $kernel->pub_session_unset('importcsv_separator');
                $kernel->pub_session_unset('importcsv_catid');
                $kernel->pub_session_unset('importcsv_catid4new');
                $kernel->pub_redirect_refresh_reload('show_items');
                break;

            //Формирование списка свойств товаров
            //как общего так и для товарной группы
            case 'show_group_props':
                $id = $kernel->pub_httpget_get('id');
                return $this->show_group_props($id);

            //Вызов формы редактирования добавления/конкртеного свойства
            case 'prop_edit':
                $id_group = $kernel->pub_httpget_get('id_group');
                $id_prop = $kernel->pub_httpget_get('id');
                //ID группы, из которой было вызвано редактирование, так как
                //из группы могут вызваны на редактирования и общие свойства
                $id_group_control = $kernel->pub_httpget_get('idg_control');
                return $this->show_prop_form($id_prop, $id_group, $id_group_control);

            //Сохраняем значения свойства
            case 'prop_save':
                $id = $kernel->pub_httppost_get('id');
                $name_db = $kernel->pub_httppost_get('name_db');
                $name_full = $kernel->pub_httppost_get('name_full');
                $inlist = $kernel->pub_httppost_get('inlist');
                $sorted = intval($kernel->pub_httppost_get('sorted'));
                $ismain = $kernel->pub_httppost_get('ismain');
                $this->save_prop($id, $name_full, $name_db, $inlist, $sorted, $ismain);
                //Теперь опредилим, куда нужно вернуться
                $id_group_control = $kernel->pub_httpget_get('id_group_control');
                $id_group_control = intval($id_group_control);
                return $kernel->pub_httppost_response("[#catalog_prop_saved_msg#]", "show_group_props&id=".$id_group_control);

            //Сохраняем свойство КАТЕГОРИИ
            case 'cat_prop_save':
                $id = $kernel->pub_httppost_get('id');
                $name_db = $kernel->pub_httppost_get('name_db');
                $name_full = $kernel->pub_httppost_get('name_full');
                $this->save_cat_prop($id, $name_full, $name_db);
                return $kernel->pub_httppost_response("[#catalog_prop_saved_msg#]", "show_cat_props");

            //Добавляем новое свойтво и общее и в группу
            case 'prop_add':
                $this->add_prop_in_group();
                //Теперь определим, куда нужно вернуться
                $id_group_control = $kernel->pub_httpget_get('id_group_control');
                $id_group_control = intval($id_group_control);
                return $kernel->pub_httppost_response("[#catalog_edit_property_added_msg#]", "show_group_props&id=".$id_group_control);

            //Удаляем свойство товарной группы
            case 'prop_delete':
                $groupid = $kernel->pub_httpget_get("groupid");
                $propid = $kernel->pub_httpget_get("id");
                $this->delete_prop($propid, $groupid);

                //Теперь опрделим, куда нужно вернуться
                $id_group_control = $kernel->pub_httpget_get('idg_control');
                $id_group_control = intval($id_group_control);
                $kernel->pub_redirect_refresh("show_group_props&id=".$id_group_control);
                break;

            //Вызывается при добавлении к уже существующему перечислению нового значения
            case 'enum_prop_add':

                $enumval = $kernel->pub_httppost_get("enumval", false);
                $propid = $kernel->pub_httppost_get("id");
                $prop = $this->get_prop($propid);
                if ($prop['group_id'] == 0)
                    $table = '_catalog_'.$kernel->pub_module_id_get().'_items';
                else
                {
                    $group = CatalogCommons::get_group($prop['group_id']);
                    $table = '_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']);
                }
                $tinfo = $kernel->db_get_table_info($table);
                $evals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
                if (substr($tinfo[$prop['name_db']]['Type'], 0, 3) == 'set')
                {
                    $ptype = 'set';
                    if (count($evals) == 64)
                        return $kernel->pub_httppost_errore('[#catalog_set_type_64_max_error_msg#]', true);
                }
                else
                    $ptype = 'enum';

                $evals[] = $enumval;
                $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE `'.$prop['name_db'].'` `'.$prop['name_db'].'` '.$this->convert_field_type_2_db($ptype, $evals);
                $kernel->runSQL($query);

                //ID группы, из которой было вызвано редактирование, так как
                //из группы могут вызваны на редактирования и общие свойства
                $group_id = $kernel->pub_httppost_get('group_id');
                $id_group_control = intval($kernel->pub_httpget_get('id_group_control'));

                $url = "prop_edit&id=".$propid."&id_group=".$group_id."&idg_control=$id_group_control";
                return $kernel->pub_httppost_response("[#catalog_edit_property_enum_add_msg#]", $url);

            case 'enum_prop_delete':
                $this->enum_set_prop_delete();
                $id_prop = $kernel->pub_httpget_get('propid');
                $group_id = $kernel->pub_httppost_get('group_id');
                $id_group_control = $kernel->pub_httpget_get('id_group_control');
                $id_group_control = intval($id_group_control);
                $str = "prop_edit&id=".$id_prop."&id_group=".$group_id."&idg_control=$id_group_control";
                $kernel->pub_redirect_refresh($str);
                break;

            //Работа с группами свойств
            //Выводит список доступных товарных групп
            case 'show_groups':
                return $this->show_groups();

            //Выводит форму редактирования/добавления товарной группы
            case 'show_group_form':
                $id = $kernel->pub_httpget_get('id');
                return $this->show_group_form($id);

            case 'group_save':
                $id = intval($kernel->pub_httppost_get('id'));
                $name = $kernel->pub_httppost_get('name');
                $namedb = $kernel->pub_httppost_get('namedb');
                if ($id == -1)
                    $groupid = $this->add_group($name, $namedb);
                else
                    $groupid = $this->save_group($id, $name, $namedb);
                if ($groupid > 0)
                    return $kernel->pub_httppost_response("[#catalog_edit_group_save_msg_ok#]", "show_groups");
                else
                    return $kernel->pub_httppost_response("[#catalog_edit_group_save_msg_err#]", "show_groups");
                break;
            case 'save_gprops_order':
                //$gid = $kernel->pub_httppost_get('group_id');
                $this->save_gprops_order();
                $kernel->pub_redirect_refresh_reload("show_groups");
                break;

            //Управление свойсвами всех категорий
            case 'show_cat_props':
                $id = $kernel->pub_httpget_get('id');
                return $this->show_cat_props($id);

            //Вызывает на редактирование свойство категории
            case 'cat_prop_edit':
                $id = $kernel->pub_httpget_get('id');
                return $this->show_cat_prop_form($id);

            //Добавляет новое свойство к категории
            case 'cat_prop_add':
                $values = $kernel->pub_httppost_get('values', false);
                $pname = $kernel->pub_httppost_get('pname');
                $ptype = $kernel->pub_httppost_get('ptype');
                $pnamedb = $kernel->pub_httppost_get('pnamedb');
                $this->add_prop_in_cat($pname, $ptype, $values, $pnamedb);
                return $kernel->pub_httppost_response("[#catalog_edit_property_cat_add_new_prop_msg#]", "show_cat_props");

            case 'cat_prop_delete':
                $propid = $kernel->pub_httpget_get("id");
                $this->delete_cat_prop($propid);
                $kernel->pub_redirect_refresh("show_cat_props");
                break;

            case 'cat_enum_prop_add':
                $enumval = $kernel->pub_httppost_get("enumval", false);
                $propid = $kernel->pub_httppost_get("id");
                $prop = $this->get_cat_prop($propid);
                $table = '_catalog_'.$moduleid.'_cats';
                $tinfo = $kernel->db_get_table_info($table);
                $evals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
                $evals[] = $enumval;
                $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE `'.$prop['name_db'].'` `'.$prop['name_db'].'` '.$this->convert_field_type_2_db('enum', $evals);
                $kernel->runSQL($query);
                return $kernel->pub_httppost_response("[#catalog_edit_property_cat_enum_addnew_msg#]", "cat_prop_edit&id=".$propid);

            case 'cat_enum_prop_delete':
                $enumval = $kernel->pub_httpget_get("enumval", false);
                $propid = $kernel->pub_httpget_get("propid");
                $prop = $this->get_cat_prop($propid);
                $table = '_catalog_'.$moduleid.'_cats';
                $tinfo = $kernel->db_get_table_info($table);
                $evals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type']);
                $newevals = array();
                foreach ($evals as $eval)
                {
                    if ($eval != $enumval)
                        $newevals[] = $eval;
                }
                $query = 'UPDATE `'.$kernel->pub_prefix_get().$table.'` SET `'.$prop['name_db'].'`=NULL WHERE `'.$prop['name_db'].'`="'.$kernel->pub_str_prepare_set($enumval).'"';
                $kernel->runSQL($query);
                $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE `'.$prop['name_db'].'` `'.$prop['name_db'].'` '.$this->convert_field_type_2_db('enum', $newevals);
                $kernel->runSQL($query);
                $kernel->pub_redirect_refresh("cat_prop_edit&id=".$propid);
                break;

            //Работа с категориями в дереве

            //Добавляем новую категорию через дерево
            case 'category_add':
                $pid = $kernel->pub_httppost_get("node");
                $name = $kernel->pub_page_textlabel_replace('[#catalog_category_new_name#]');
                if ($pid == 'index')
                    $pid = 0;
                $order = $this->get_last_order_in_cat($pid) + 2;
                $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_cats` (`parent_id`,`name`,`order`) '.
                    'VALUES ('.$pid.',"'.$kernel->pub_str_prepare_set($name).'", '.$order.')';
                $kernel->runSQL($query);
                $cid = mysql_insert_id();
                $this->regenerate_all_groups_tpls(false);
                $kernel->pub_redirect_refresh_reload('category_edit&id='.$cid.'&selectcat='.$cid);
                break;

            //Вызов формы редактирования категории
            case 'category_edit':
                $id = $kernel->pub_httpget_get("id");
                return $this->show_category_form($id);

            //Сохраняет параметры отредактированнной категории
            case 'category_save':
                return $this->save_category($kernel->pub_httppost_get('id'));

            //Удаление категории
            case 'category_delete':
                $cat = $this->get_category(intval($kernel->pub_httppost_get('node')));
                if (!$cat)
                    $kernel->pub_redirect_refresh_reload('show_items');
                $this->delete_category($cat);
                $kernel->pub_redirect_refresh_reload('category_edit&id='.$cat['parent_id'].'&selectcat='.$cat['parent_id']);
                break;

            //Удаляет файл и очищает поле файл и изображения в категории
            case 'cat_clear_field':
                $id = $kernel->pub_httpget_get('id');
                $dprop = $kernel->pub_httpget_get('field');
                $cat = $this->get_category($id);
                $query = "UPDATE `".$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_cats` SET `'.$dprop.'`=NULL WHERE `id`='.intval($id);
                $kernel->runSQL($query);
                $kernel->pub_file_delete($cat[$dprop]);
                $kernel->pub_redirect_refresh('category_edit&id='.$id.'&selectcat='.$id);
                break;

            //Список товаров в категории
            case 'category_items':
                return $this->show_category_items();

            case 'category_items_save':
                $catid = $kernel->pub_httppost_get("catid");
                $change_items = $kernel->pub_httppost_get('change_items');
                if (empty($change_items))
                    $this->save_category_items($catid);
                else
                    $this->change_selected_items();
                return $kernel->pub_httppost_response('[#catalog_variable_save_msg_ok#]', 'category_items&id='.$catid);

            //Выводит форму всех товаров, с фильтром по товарной группе
            case 'show_items':
                $group_id = 0;
                $gid_param = $kernel->pub_httpget_get('group_id');
                if (!empty($gid_param))
                    $group_id = intval($gid_param);
                else
                {
                    $gid_param = $kernel->pub_httppost_get('group_id');
                    if (!empty($gid_param))
                        $group_id = intval($gid_param);
                }
                $search_param = $kernel->pub_httppost_get('search');
                if (!empty($search_param))
                {
                    $groups = CatalogCommons::get_groups();
                    $postprops = $kernel->pub_httppost_get();
                    unset($postprops['group_id']);
                    unset($postprops['search']);
                    $search_props = array();
                    $matches = false;
                    foreach ($postprops as $ppname => $ppvalue)
                    {
                        if (preg_match("/([a-z0-9_-]+)_(to|from)$/", $ppname, $matches))
                        {
                            $search_props[$matches[1]] = "diapazon";
                            continue;
                        }
                        if (empty($ppvalue) /*|| !isset($all_group_props[$ppname])*/)
                            continue;
                        if ($ppname == "id")
                            $search_props[$ppname] = "accurate";
                        elseif (is_array($ppvalue))
                        { //enum-checkboxes
                            $search_props[$ppname] = "checkbox";
                        }
                        else
                        {
                            $search_props[$ppname] = "like";
                        }
                    }
                    $squery = $this->generate_inner_filter_query($group_id, $search_props);
                    $link = '';
                    $squery = $this->prepare_inner_filter_sql($squery, array(), $link);

                    $squery = "SELECT items.* FROM ".$kernel->pub_prefix_get()."_catalog_".$moduleid."_items AS items ".
                        "LEFT JOIN ".$kernel->pub_prefix_get()."_catalog_items_".$moduleid."_".strtolower($groups[$group_id]['name_db'])." AS `".strtolower($groups[$group_id]['name_db'])."` ON items.ext_id = `".strtolower($groups[$group_id]['name_db'])."`.id ".
                        "WHERE items.`group_id`=".$group_id." AND (".$squery.")";
                    $kernel->pub_session_set("search_items_query", $squery);
                    $kernel->pub_redirect_refresh_reload('show_items&search_results=1&group_id='.$group_id);
                    die();
                }
                return $this->show_items($group_id);

            //Форма редактирования товара
            case 'item_edit':
                $id = $kernel->pub_httpget_get('id');
                $removlinkedid = $kernel->pub_httpget_get("removlinkedid");
                if (!empty($removlinkedid))
                {
                    $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_items_links` WHERE '.
                        '(itemid1='.$id.' AND itemid2='.$removlinkedid.') OR '.
                        '(itemid2='.$id.' AND itemid1='.$removlinkedid.')';
                    $kernel->runSQL($query);
                }
                return $this->show_item_form($id, 0);

            //Добавление товара вызывает ту же строку с редактированием
            case 'item_add':
                $group_id = $kernel->pub_httppost_get('group_id');
                if (!$group_id)
                {
                    $group_id = $kernel->pub_httpget_get('group_id');
                    if (!$group_id)
                        return '';
                }
                $id_cat = $kernel->pub_httpget_get('id_cat');
                if (empty($id_cat))
                    $id_cat = $kernel->pub_httppost_get('id_cat');
                return $this->show_item_form(0, intval($group_id), intval($id_cat));

            //Сохраняет товар после редактирования или добавляет новый
            case 'item_save':
                return $this->save_item();

            //Очистка поля с файлом в товаре
            case 'item_clear_field':
                $id_tovar = $kernel->pub_httpget_get('id');
                $dprop = $kernel->pub_httpget_get('field');
                $id_tovar = intval($id_tovar);
                $item = $this->get_item_full_data($id_tovar);
                //определяем, common-свойство или нет
                $tinfo = $kernel->db_get_table_info('_catalog_'.$moduleid.'_items');
                if (array_key_exists($dprop, $tinfo))
                    $query = "UPDATE `".$kernel->pub_prefix_get().'_catalog_'.$moduleid.'_items` SET `'.$dprop.'`=NULL WHERE `id`='.$id_tovar;
                else
                {
                    $group = CatalogCommons::get_group($item['group_id']);
                    $query = "UPDATE `".$kernel->pub_prefix_get().'_catalog_items_'.$moduleid.'_'.strtolower($group['name_db']).'` SET `'.$dprop.'`=NULL WHERE `id`='.$item['id'];
                }
                $kernel->runSQL($query);
                //Теперь удаяем само изображение
                //и его уменьшенную копию
                $path_parts = pathinfo($item[$dprop]);
                $kernel->pub_file_delete($item[$dprop]);
                $kernel->pub_file_delete($path_parts['dirname'].'/tn/'.$path_parts['basename']);
                $kernel->pub_file_delete($path_parts['dirname'].'/source/'.$path_parts['basename']);
                $kernel->pub_redirect_refresh("item_edit&id=".$id_tovar.'&redir2='.urlencode($kernel->pub_httpget_get('redir2')));
                break;

            //Удаление товара
            case 'item_delete':
                $groupid = $kernel->pub_httpget_get('group_id');
                $itemid = $kernel->pub_httpget_get('id');
                $this->delete_item($itemid);
                //Если указана ID категории, то нужно вренуться в неё
                $id_cat = $kernel->pub_httpget_get('id_cat');
                $id_cat = intval($id_cat);
                if ($id_cat > 0)
                    $kernel->pub_redirect_refresh("category_items&id=".$id_cat);
                else
                    $kernel->pub_redirect_refresh("show_items&group_id=".$groupid);
                break;

            //Генерация шаблонов
            case 'regen_tpls4groups':
                $id_group = $kernel->pub_httpget_get("id_group");
                $id_group = intval($id_group);
                if ($id_group > 0)
                {
                    //Формируем шаблон списка товаров
                    $this->regenerate_group_tpls($id_group);
                    //Формируем шаблон карточки товара
                    $this->regenerate_group_tpls($id_group, true);
                }
                $kernel->pub_redirect_refresh("show_groups");
                break;

            case 'regen_tpl4itemlist':
                $kernel->pub_redirect_refresh_reload("show_groups");
                break;

            //Созданием админского шаблона редактирования товара
            /*
            case 'generate_admin_templ':
                $id = $kernel->pub_httpget_get("id");
                $this->regenerate_admin_template($id);

                return $kernel->pub_redirect_refresh("show_groups");
                break;
            */
        }

        return null;
    }

    /**
     * Удаляет товар из БД
     *
     * @param $id integer id-шник товара
     * @return boolean
     */
    private function delete_item($id)
    {
        global $kernel;
        $item = $this->get_item_full_data($id);
        if (!$item)
            return false;
        $group = CatalogCommons::get_group($item['group_id']);
        if (!$group)
            return false;

        $modid = $kernel->pub_module_id_get();

        //удаление картинок и файлов
        $props = CatalogCommons::get_props($item['group_id'], true);
        foreach ($props as $prop)
        {
            if (!in_array($prop['type'], array('file', 'pict')) || !$item[$prop['name_db']])
                continue;
            $kernel->pub_file_delete($item[$prop['name_db']]);
            if ($prop['type'] == 'pict')
            {
                //надо также удалить source и tn изображения
                $kernel->pub_file_delete(str_replace($modid.'/', $modid.'/tn/', $item[$prop['name_db']]));
                $kernel->pub_file_delete(str_replace($modid.'/', $modid.'/source/', $item[$prop['name_db']]));
            }
        }
        //из общей таблицы товаров
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$modid.'_items` WHERE `id`='.$id;
        $kernel->runSQL($query);
        //из таблицы связанных товаров
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$modid.'_items_links` WHERE `itemid1`='.$id.' OR `itemid2`='.$id;
        $kernel->runSQL($query);
        //из таблицы принадлежности к категориям
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_'.$modid.'_item2cat` WHERE `item_id`='.$id;
        $kernel->runSQL($query);
        //из таблицы тов. группы
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_catalog_items_'.$modid.'_'.strtolower($group['name_db']).'` WHERE `id`='.$item['ext_id'];
        $kernel->runSQL($query);
        return true;
    }

    /**
     * Возвращает максимальное кол-во терминов на страницу в админке
     *
     * @return integer
     */
    private function get_limit_admin()
    {
        global $kernel;
        $property = $kernel->pub_modul_properties_get('catalog_terms_per_page_admin');
        if ($property['isset'] && intval($property['value']) > 0)
            return intval($property['value']);
        else
            return 20;
    }


    /**
     * Возвращает текущий сдвиг во фронтэнде
     *
     * @return integer
     */
    private function get_offset_user()
    {
        global $kernel;
        $offset = intval($kernel->pub_httpget_get($this->frontend_param_offset_name));
        if ($offset < 0)
            $offset = 0;
        return $offset;
    }

    /**
     * Возвращает текущий сдвиг  (для админки)
     *
     * @return integer
     */
    private function get_offset_admin()
    {
        global $kernel;
        $offset = intval($kernel->pub_httpget_get($this->admin_param_offset_name));
        if ($offset < 0)
            $offset = 0;
        return $offset;
    }


    /**
     * Удаляет одно из возможных значений поля типа enum (общее либо товарной группы)
     * @return void
     */
    protected function enum_set_prop_delete()
    {
        global $kernel;
        $val2del = $kernel->pub_httpget_get("enumval", false);
        $propid = $kernel->pub_httpget_get("propid");
        $prop = $this->get_prop($propid);
        if ($prop['group_id'] == 0)
            $table = '_catalog_'.$kernel->pub_module_id_get().'_items';
        else
        {
            $group = CatalogCommons::get_group($prop['group_id']);
            $table = '_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']);
        }
        $tinfo = $kernel->db_get_table_info($table);
        $evals = $this->get_enum_set_prop_values($tinfo[$prop['name_db']]['Type'], false);
        $newevals = array();
        foreach ($evals as $eval)
        {
            if ($eval != $val2del)
                $newevals[] = $eval;
        }
        if (substr($tinfo[$prop['name_db']]['Type'], 0, 3) == 'set')
            $ptype = 'set';
        else
            $ptype = 'enum';
        if ($ptype == 'enum')
            $query = 'UPDATE `'.$kernel->pub_prefix_get().$table.'` SET `'.$prop['name_db'].'`=NULL WHERE `'.$prop['name_db'].'`="'.mysql_real_escape_string($val2del).'"';
        else
            $query = "UPDATE `".$kernel->pub_prefix_get().$table."` SET `".$prop['name_db']."`=REPLACE(`".$prop['name_db']."`,'".mysql_real_escape_string($val2del)."','') WHERE `".$prop['name_db']."` LIKE '%".mysql_real_escape_string($val2del)."%' ";
        $kernel->runSQL($query);
        $query = 'ALTER TABLE `'.$kernel->pub_prefix_get().$table.'` CHANGE `'.$prop['name_db'].'` `'.$prop['name_db'].'` '.$this->convert_field_type_2_db($ptype, $newevals);
        $kernel->runSQL($query);
    }

    public static function clone_file_field($prop_type,$val)
    {
        global $kernel;

        $site_root = $kernel->pub_site_root_get();
        $full_orig_path=$site_root.'/'.$val;
        $origFilename=pathinfo($full_orig_path,PATHINFO_FILENAME);
        $ext='.'.pathinfo($full_orig_path,PATHINFO_EXTENSION);
        $save_path_rel = pathinfo($val,PATHINFO_DIRNAME);
        $save_path_full = $site_root.'/'.$save_path_rel;
        $newname=$origFilename.$ext;
        $n=1;
        while (file_exists($save_path_full."/".$newname) || file_exists($save_path_full."/tn/".$newname) || file_exists($save_path_full."/source/".$newname))
        {
            $n++;
            $newname=$origFilename.'_'.$n.$ext;
        }
        $newval = $save_path_rel.'/'.$newname;
        copy($full_orig_path,$save_path_full.'/'.$newname);
        if($prop_type=='pict')
        {
            copy($full_orig_path,$save_path_full.'/tn/'.$newname);
            copy($full_orig_path,$save_path_full.'/source/'.$newname);
        }
        return $newval;
    }

    /**
     * Клонирует товар по переданному айдишнику
     * @param  integer $id
     * @return integer айдишник нового товара
     */
    private function item_clone($id)
    {
        global $kernel;
        //сначала клонируем запись в общей таблице товаров
        $olditem = $this->get_item($id);
        if (!$olditem)
            return false;

        $props = CatalogCommons::get_props2(0);

        $newitem = array();
        foreach ($olditem as $k => $v)
        {
            if ($k == "id")
                continue;
            elseif ($k == "ext_id")
                $v = 0;
            elseif ($k == "name")
                $v .= " копия";
            if ($v && isset($props[$k]) && in_array($props[$k]['type'],array('file','pict')))
                $v=self::clone_file_field($props[$k]['type'],$v);

            if ($k != "ext_id" && mb_strlen($v) == 0)
                $newitem[$k] = null;
            else
                $newitem[$k] = mysql_real_escape_string($v);
        }

        if (!$newitem)
            $newitem = array("id" => null);
        $newID = $kernel->db_add_record('_catalog_'.$kernel->pub_module_id_get().'_items', $newitem);

        //теперь в таблице товарной группы
        $group = CatalogCommons::get_group($olditem['group_id']);
        $olditem = $this->get_item_group_fields($olditem['ext_id'], $group['name_db']);
        if (!$olditem)
            return false;

        $props = CatalogCommons::get_props2($olditem['group_id']);
        $newitem = array();
        foreach ($olditem as $k => $v)
        {
            if ($k == "id")
                continue;
            if ($v && isset($props[$k]) && in_array($props[$k]['type'],array('file','pict')))
                $v=self::clone_file_field($props[$k]['type'],$v);

            if (mb_strlen($v) == 0)
                $newitem[$k] = null;
            else
                $newitem[$k] = mysql_real_escape_string($v);
        }
        if (!$newitem)
            $newitem = array("id" => null);
        $newExtID = $kernel->db_add_record('_catalog_items_'.$kernel->pub_module_id_get().'_'.strtolower($group['name_db']), $newitem);

        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_items` SET `ext_id`='.$newExtID.' WHERE id='.$newID;
        $kernel->runSQL($query);

        //скопируем принадлежность к категориям
        $catIDs = $this->get_item_catids_with_order($id);
        foreach ($catIDs as $cid => $order)
        {
            $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_catalog_'.$kernel->pub_module_id_get().'_item2cat` (`cat_id`,`item_id`,`order`) VALUES ('.$cid.','.$newID.','.$order.')';
            $kernel->runSQL($query);
        }

        //скопируем связанные товары
        $linked = $kernel->db_get_list_simple('_catalog_'.$kernel->pub_module_id_get().'_items_links','itemid1='.$id.' OR itemid2='.$id);
        foreach($linked as $row)
        {
            if ($row['itemid1'] == $id)
                $lid = $row['itemid2'];
            else
                $lid = $row['itemid1'];
            $this->add_items_link($newID, $lid);
        }
        return $newID;
    }

    /** Возвращает максимальную дорогу в категориях к товару
     * @param integer  $itemid айдишник товара
     * @param array $cached_cats массив всех категорий
     * @return array
     */
    private function get_max_catway2item($itemid, $cached_cats = null)
    {
        $catids = $this->get_item_catids($itemid);
        $max_length = 0;
        $max_way = array();
        foreach ($catids as $catid)
        {
            $way = $this->get_way2cat($catid, true, $cached_cats);
            if (count($way) > $max_length)
            {
                $max_way = $way;
                $max_length = count($way);
            }
        }
        return $max_way;
    }

    /**
     * Публичный метод для отображения экспорта
     *
     * @param string  $tpl   шаблон
     * @param string  $filterid   строковый ID-шник внутреннего фильтра
     * @return string
     */
    public function pub_catalog_show_export($tpl, $filterid)
    {
        global $kernel;
        $categories = false;
        if ($filterid && $filterid != "null")
        {
            //если указан внутренний фильтр - возьмём товары из него
            //в данном случае будет использоваться шаблон, прописанный в фильтре
            $content = $this->pub_catalog_show_inner_selection_results($filterid, false, array(), false);
            $this->set_templates($kernel->pub_template_parse($tpl));
        }
        else
        {
            $this->set_templates($kernel->pub_template_parse($tpl));
            $groups = CatalogCommons::get_groups();
            $groups_props = array(); //здесь будем кэшировать свойства групп, чтобы не повторять запросы к БД
            $items = $this->get_items(0, 0, 0, true);
            if (count($items) == 0)
                $content = $this->get_template_block('list_null');
            else
                $content = $this->get_template_block('list');
            $rows = "";
            foreach ($items as $item)
            {
                $commonid = $item['id'];
                unset($item['id']);
                $item['commonid'] = $commonid;
                $igroup = $groups[$item['group_id']];
                if (isset($groups_props[$item['group_id']]))
                    $props = $groups_props[$item['group_id']];
                else
                {
                    $props = CatalogCommons::get_props($item['group_id'], true);
                    $groups_props[$item['group_id']] = $props;
                }

                $item2 = $this->get_item_group_fields($item['ext_id'], $igroup['name_db']);
                if ($item2)
                    $item = $item + $item2;

                $block = $this->get_template_block('row');
                $block = $this->process_item_props_out($item, $props, $block, $igroup);
                $rows .= $block;

            }
            $content = str_replace("%row%", $rows, $content);
        }

        //добавим категории в шаблон, если надо
        if (mb_strpos($content, "%categories_flat_list%") !== false)
        {
            if (!$categories)
                $categories = CatalogCommons::get_all_categories($kernel->pub_module_id_get());
            if (count($categories) == 0)
                $block = $this->get_template_block('categories_flat_null');
            else
            {
                $block = $this->get_template_block('categories_flat_list');
                $rows = "";
                foreach ($categories as $cat)
                {
                    $row = $this->get_template_block('category_flat_row');
                    foreach ($cat as $ck => $cv)
                    {
                        $row = str_replace("%".$ck."%", $cv, $row);
                        $row = str_replace("%".$ck."_value%", $cv, $row);
                    }
                    $rows .= $row;
                }
                $block = str_replace("%category_row%", $rows, $block);
            }
            $content = str_replace("%categories_flat_list%", $block, $content);

        }

        if (preg_match_all("|\\%single_cat_id\\[(\\d+)\\]\\%|isU", $content, $matches, PREG_SET_ORDER))
        {
            if (!$categories)
                $categories = CatalogCommons::get_all_categories($kernel->pub_module_id_get());

            foreach ($matches as $match)
            {
                $itemid = intval($match[1]);
                $max_way = $this->get_max_catway2item($itemid, $categories);
                if (count($max_way) > 0)
                    $content = str_replace($match[0], $max_way[count($max_way) - 1]['id'], $content);
                else
                    $content = str_replace($match[0], "0", $content);
            }
        }

        $content = $this->process_variables_out($content);
        $content = $this->clear_left_labels($content);
        return $content;
    }


    private function get_current_catIDs()
    {
        if (isset($_GET[$this->frontend_param_cat_id_name]))
        {
            if (is_numeric($_GET[$this->frontend_param_cat_id_name]))
                return intval($_GET[$this->frontend_param_cat_id_name]);
            if (is_array($_GET[$this->frontend_param_cat_id_name]))
            {
                $arr = array();
                foreach (array_keys($_GET[$this->frontend_param_cat_id_name]) as $cid)
                {
                    $cid = intval($cid);
                    if ($cid > 0)
                        $arr[] = $cid;
                }
                if ($arr)
                    return $arr;
            }
        }
        if (isset($_POST[$this->frontend_param_cat_id_name]))
        {
            if (is_numeric($_POST[$this->frontend_param_cat_id_name]))
                return intval($_POST[$this->frontend_param_cat_id_name]);
            if (is_array($_POST[$this->frontend_param_cat_id_name]))
            {
                $arr = array();
                foreach (array_keys($_POST[$this->frontend_param_cat_id_name]) as $cid)
                {
                    $cid = intval($cid);
                    if ($cid > 0)
                        $arr[] = $cid;
                }
                if ($arr)
                    return $arr;
            }
        }
        return 0;
    }


    public function pub_catalog_show_compare($tpl, $max_items)
    {
        global $kernel;
        $post_cb_name = 'additems2compare'; //параметр для чекбоксов
        $single_param_name = 'add2compare'; //параметр при единичном добавлении
        $remove_param_name = 'remove_from_compare'; //параметр для удаления
        $groupID = 0;
        $moduleid = $kernel->pub_module_id_get();
        $session_name = $moduleid.'_compared_items';
        if (isset($_SESSION[$session_name]) && $_SESSION[$session_name])
        {
            $items2compare = $_SESSION[$session_name];
            $groupID = $items2compare[key($items2compare)]['group_id'];
        }
        else
            $items2compare = array();


        $is_modifed_list = false;
        if (count($items2compare) < $max_items && isset($_POST[$post_cb_name]) && is_array($_POST[$post_cb_name]))
        { //чекбоксы
            foreach ($_POST[$post_cb_name] as $id)
            {
                if (!CatalogCommons::is_valid_itemid($id))
                    continue;
                $idata = $this->get_item_full_data($id);
                if (!$idata || ($groupID && $idata['group_id'] != $groupID))
                    continue;

                $items2compare[$idata['commonid']] = $idata;
                $groupID = $idata['group_id'];
                if (count($items2compare) == $max_items)
                    break;
            }
            $is_modifed_list = true;
        }
        //добавление единичного товара
        if (count($items2compare) < $max_items && isset($_REQUEST[$single_param_name]) && CatalogCommons::is_valid_itemid($_REQUEST[$single_param_name]))
        {
            $idata = $this->get_item_full_data($_REQUEST[$single_param_name]);
            if ($idata && (!$groupID || $groupID == $idata['group_id']))
            {
                $items2compare[$idata['commonid']] = $idata;
                $groupID = $idata['group_id'];
            }
            $is_modifed_list = true;
        }

        //удаление из сравнения
        if (isset($_POST[$remove_param_name]) && is_array($_POST[$remove_param_name]))
        { //чекбоксами
            foreach ($_POST[$remove_param_name] as $riid)
            {
                if (isset($items2compare[$riid]))
                    unset($items2compare[$riid]);
            }
            $is_modifed_list = true;
        }
        elseif (isset($_REQUEST[$remove_param_name]) && isset($items2compare[$_REQUEST[$remove_param_name]])) //единичный товар
        {
            unset($items2compare[$_REQUEST[$remove_param_name]]);
            $is_modifed_list = true;
        }

        //добавим в сессию
        $_SESSION[$session_name] = $items2compare;

        //редирект назад если надо
        if ($is_modifed_list)
        {
            if (isset($_REQUEST['redir2']) && !empty($_REQUEST['redir2']))
            {
                $redirURL = $_REQUEST['redir2'];
                if (substr($redirURL, 0, 1) != "/")
                    $redirURL = "/".$redirURL;
            }
            else
                $redirURL = "/".$kernel->pub_page_current_get().".html";
            $kernel->pub_redirect_refresh_global($redirURL);
        }

        //отображение
        $this->set_templates($kernel->pub_template_parse($tpl));
        if (!$items2compare)
            return $this->get_template_block('list_null');
        if (count($items2compare) == 1)
            return $this->get_template_block('less_than_two');
        $content = $this->get_template_block('content');
        $props = CatalogCommons::get_props($groupID, true);
        foreach ($props as $prop)
        {
            $is_same_value = true;
            $val = null;
            $inum = 0;
            $pvalues = array();
            foreach ($items2compare as $item)
            {
                $inum++;
                if ($inum == 1)
                    $val = $item[$prop['name_db']];
                elseif ($val != $item[$prop['name_db']])
                    $is_same_value = false;
                $pvalue = $this->get_template_block('prop_value');
                $pvalue = str_replace('%value%', $item[$prop['name_db']], $pvalue);
                $pvalues[] = $pvalue;
            }
            //различные блоки для одинаковых и разных значений свойств
            if ($is_same_value)
                $pline = $this->get_template_block('same_value_line');
            else
                $pline = $this->get_template_block('diff_value_line');
            $pline = str_replace('%name_full%', $prop['name_full'], $pline);
            $pline = str_replace('%prop_values%', implode($this->get_template_block('prop_values_separator'), $pvalues), $pline);
            $content = str_replace('%'.$prop['name_db'].'_line%', $pline, $content);
        }
        //в заголовке - вывод информации о сравниваемых товарах (название, фото)
        $iheaders = array();
        foreach ($items2compare as $item)
        {
            $iheader = $this->get_template_block('item_header');
            $iheader = $this->process_item_props_out($item, $props, $iheader);
            $iheaders[] = $iheader;
        }
        $content = str_replace('%items_headers%', implode($this->get_template_block('iheaders_separator'), $iheaders), $content);

        $content = $this->clear_left_labels($content);
        return $content;

    }

    private function cats_props_out($catid, $content)
    {
        $cat = $this->get_category($catid);
        if (!$cat)
            return $content;
        $content = str_replace("%catid%", $cat['id'], $content);
        $cats_props = CatalogCommons::get_cats_props();
        foreach ($cats_props as $cprop)
        {
            if (mb_strpos($content, '%category_'.$cprop['name_db'].'%') !== false)
            {
                $content = str_replace('%category_'.$cprop['name_db'].'%', $this->get_template_block('category_'.$cprop['name_db']), $content);
                $content = str_replace('%category_'.$cprop['name_db'].'_value%', $cat[$cprop['name_db']], $content);
                $content = str_replace('%category_'.$cprop['name_db'].'_name%', $cprop['name_full'], $content);
            }
        }
        return $content;
    }
}