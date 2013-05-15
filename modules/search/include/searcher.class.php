<?PHP

class Searcher
{
    // Результатов на страницу
    var $results_per_page = 20;

    // максимальный размер снипета (подсвеченного куска текста)
    var $max_snipped_length = 350;


    //private
    var $number_of_results;
    var $format_id = false;
    var $operation = "or";


    function set_results_per_page($results_per_page)
    {
        $this->results_per_page = $results_per_page;
    }

    function set_operation($operation)
    {
        $this->operation = $operation;
    }

    function set_doc_format($doc_format)
    {
        $this->format_id = Searcher::format2format_id($doc_format);
    }


    static function get_stop_words()
    {
        $stop_words = array('в', 'и', 'на', 'к', 'с', 'у', 'из', 'от', 'о', 'за', 'по', 'при', 'а', 'для', 'как',
            'a', 'an', 'is', 'are', 'there');
        return $stop_words;
    }

    public static function get_word_symbols()
    {
        $rus_small_letters = "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";
        $rus_big_letters   = "АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ";
        $word_symbols = $rus_small_letters.$rus_big_letters."a-zA-Z0-9";
        return $word_symbols;
    }

    public static function text2words($text)
    {
        $word_symbols = self::get_word_symbols();

        $text = preg_replace("'[^".$word_symbols."]+'su", " ", $text);
        $text = preg_replace("/\\s+/u", " ", $text);
        $text = trim($text);
        $text = mb_strtolower($text);
        if (mb_strlen($text) > 0)
            $words = explode(" ", $text);
        else
            $words = array();
        return $words;
    }


    /**
     * Статический метод
     *
     * @static
     * @param String $format
     * @return int
     */
    static function format2format_id($format)
    {
        $format = strtolower($format);
        switch ($format)
        {
            case "any":
                $format_id = false;
                break;
            case "html":
                $format_id = 1;
                break;
            case "pdf":
                $format_id = 2;
                break;
            default:
                $format_id = 0;
        }
        return $format_id;
    }


    /**
     * Осуществляет поиск. $text - поисковый запрос.
     * Возвращает массив с результатами
     *
     * @param string $text
     * @param integer $offset смещение
     * @return array
     */
    function search($text, $offset = 0)
    {
        $words = self::text2words($text);
        $stems = array();
        $stop_words = self::get_stop_words();

        foreach ($words as $word)
            if (!in_array($word, $stop_words))
                $stems[] = Lingua_Stem_Ru::stem_word($word);

        array_unique($stems);

        $word_ids = searchdb::get_word_ids($stems);

        if (count($word_ids) < count($stems) && $this->operation == 'and')
        {
            $this->number_of_results = 0;
            return array();
        }
        $docs = searchdb::search($word_ids, $offset, $this->results_per_page, $this->operation, $this->format_id);
        $this->number_of_results = searchdb::found_rows();

        $num = $offset;
        $results = array();
        foreach ($docs as $doc)
        {
            $result = array('url' => $doc['doc']);
            $raw_snipped = unserialize($doc['snipped']);
            $result['title'] = $raw_snipped['title'];
            $result['snipped'] = $this->get_snipped($raw_snipped, $stems);
            $result['num'] = ++$num;

            $results[] = $result;
        }

        return $results;
    }

    /************ private *****************/


    function get_snipped($snipped, $stems)
    {
        $lengths = array();
        foreach ($snipped['positions'] as $stem => $stem_positions)
            if (in_arraY((string)$stem, $stems, true))
                foreach ($stem_positions as $position => $length)
                    $lengths[$position] = $length;

        $positions = array_keys($lengths);
        sort($positions);
        $result = "";

        $snip_length = 30;
        $predidush = 0;
        $delimiter = " ||((++artprom||(++ ";
        foreach ($positions as $position)
        {
            $diff = $position - $predidush;

            if ($diff > $snip_length * 2)
            {
                $dobavka = $this->trim_right(substr($snipped['text'], $predidush, $snip_length));
                $dobavka .= $delimiter . $this->trim_left(substr($snipped['text'], $position - $snip_length, $snip_length));
            }
            elseif ($diff > $snip_length)
            {
                $dobavka = $delimiter . $this->trim_left(substr($snipped['text'], $position - $snip_length, $snip_length));
            }
            else
                $dobavka = substr($snipped['text'], $predidush, $diff);

            $dobavka = htmlspecialchars($dobavka);
            $result .= $dobavka;

            $result .= "<b>" . substr($snipped['text'], $position, $lengths[$position]) . "</b>";
            $predidush = $position + $lengths[$position];

        }
        $result .= $this->trim_right(substr($snipped['text'], $predidush, $snip_length));
        $snipped = $this->correct_snipped($result);
        return $snipped;
    }


    function correct_snipped($result)
    {
        $delimiter = " ||((++artprom||(++ ";
        $parts = preg_split("/" . preg_quote($delimiter, "/") . "/", $result);

        $counts = array();
        foreach ($parts as $key => $part)
        {
            $count = substr_count($part, "<b>");
            if ($count > 0)
                $counts[$key] = $count;
        }

        arsort($counts);
        $part_keys = array();
        $snip_count = 2;
        $i = 0;

        foreach ($counts as $part_key => $count)
        {
            if (++$i > $snip_count)
                break;
            $part_keys[] = $part_key;
        }

        sort($part_keys);

        $snipped_parts = array();
        foreach ($part_keys as $key)
            $snipped_parts[] = trim($parts[$key]);

        $result = "... " . join(" ... ", $snipped_parts) . " ...";

        if (strlen($result) > $this->max_snipped_length)
            $result = substr($result, 0, $this->max_snipped_length);

        $result = preg_replace("'<[^<]*?$'", "", $result);
        $b = substr_count($result, "<b>");
        $bz = substr_count($result, "</b>");

        if ($b > $bz)
            $result .= "</b>";
        $result .= " ...";

        return $result;
    }


    function trim_left($text)
    {
        return preg_replace("/^[^\\s]+?\\s/", "", $text);
    }

    function trim_right($text)
    {
        $res = preg_replace("/\\s[^\\s]+?$/", "", $text);
        return $res;
    }

}