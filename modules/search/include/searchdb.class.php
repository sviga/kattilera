<?PHP

class searchdb
{


	public static function array2str($arr)
	{
		$str_parts = array();
		foreach ($arr as $value)
			$str_parts[] = "'".mysql_real_escape_string($value)."'";
		return join(", ", $str_parts);
	}


	public static function delete_doubles()
	{
        global $kernel;
	    $sql = "SELECT id, count(*) as cnt
               FROM `".$kernel->pub_prefix_get()."_".$kernel->pub_module_id_get()."_docs`
               GROUP BY contents_hash
               HAVING cnt > 1";
	    $res=$kernel->runSQL($sql);
	    $ids2delete = array();
	    while ($row = mysql_fetch_assoc($res))
	        $ids2delete[] = $row['id'];
        mysql_free_result($res);

	    if (!$ids2delete)
            return;
        $sql = "DELETE FROM `".$kernel->pub_prefix_get()."_".$kernel->pub_module_id_get()."_docs`
                       WHERE id IN (".implode(",",$ids2delete).")";
        $kernel->runSQL($sql);
	}


	public static function get_word_ids($words,$moduleID=null)
	{
        global $kernel;
		if (count($words) == 0)
			return array();
		$words_str = self::array2str($words);
        if (!$moduleID)
            $moduleID=$kernel->pub_module_id_get();
        $res=$kernel->runSQL("SELECT id, word FROM `".$kernel->pub_prefix_get()."_".$moduleID."_words` WHERE word IN ($words_str)");
		$result = array();
		while ($row = mysql_fetch_assoc($res))
			$result[$row['word']] = $row['id'];
        mysql_free_result($res);
		return $result;
	}

    public static function get_words_table_name()
    {
        global $kernel;
        return "_".$kernel->pub_module_id_get()."_words";
    }

    public static function get_docs_table_name()
    {
        global $kernel;
        return "_".$kernel->pub_module_id_get()."_docs";
    }

    public static function get_index_table_name()
    {
        global $kernel;
        return "_".$kernel->pub_module_id_get()."_index";
    }

    public static function get_ignored_table_name()
    {
        global $kernel;
        return "_".$kernel->pub_module_id_get()."_ignored";
    }

    public static function clear_index()
    {
        global $kernel;
        $kernel->runSQL("TRUNCATE TABLE ".$kernel->pub_prefix_get().self::get_docs_table_name());
        $kernel->runSQL("TRUNCATE TABLE ".$kernel->pub_prefix_get().self::get_words_table_name());
        $kernel->runSQL("TRUNCATE TABLE ".$kernel->pub_prefix_get().self::get_index_table_name());
    }

	public static function add_words($words)
	{
        global $kernel;
		if (count($words) == 0)
			return array();
		$ids = array();
		//$kernel->runSQL("LOCK TABLE `".$kernel->pub_prefix_get().self::get_words_table_name()."` WRITE");
		foreach ($words as $word)
		{
            $kernel->runSQL("INSERT INTO `".$kernel->pub_prefix_get().self::get_words_table_name()."` VALUES (NULL, '".mysql_real_escape_string($word)."')");
			$ids[$word] = mysql_insert_id();
		}
        //$kernel->runSQL("UNLOCK TABLES");
		return $ids;
	}


	public static function get_url_id($url)
	{
        global $kernel;
		$doc_hash = md5($url);
        $rec= $kernel->db_get_record_simple(self::get_docs_table_name(),"doc_hash='".$doc_hash."'","id");
        if (!$rec)
            return false;
        return $rec['id'];
	}


	public static function add_url($url, $contents_hash)
	{
        global $kernel;
		$doc_hash = md5($url);
		$url = mysql_real_escape_string($url);
        $kernel->runSQL("INSERT INTO ".$kernel->pub_prefix_get().self::get_docs_table_name()." VALUES (NULL, '$url', '$doc_hash', '$contents_hash', -2, '')");
		return mysql_insert_id();
	}


	public static function get_contents_hash($url_id)
	{
        global $kernel;
        $rec=$kernel->db_get_record_simple(self::get_docs_table_name(),"id=".$url_id,"contents_hash");
		if (!$rec)
			return false;
        return $rec['contents_hash'];
	}


    public static function empty_url_data_from_index($url_id)
	{
        global $kernel;
        $kernel->runSQL("DELETE FROM ".$kernel->pub_prefix_get().self::get_index_table_name()." WHERE doc_id = $url_id");
	}

    public static function lock_index()
	{
        global $kernel;
        $kernel->runSQL("LOCK TABLES ".$kernel->pub_prefix_get().self::get_index_table_name()." WRITE");
	}

	public static function unlock_tables()
	{
        global $kernel;
		$kernel->runSQL("UNLOCK TABLES");
	}

    public static function add_to_index($doc_id, $word_id, $weight)
	{
        global $kernel;
        $kernel->runSQL("INSERT INTO ".$kernel->pub_prefix_get().self::get_index_table_name()." VALUES (NULL, $doc_id, $word_id, $weight)");

	}

	public static function search($word_ids, $limit=0, $length=20, $operation='or', $format_id = false)
	{
        global $kernel;
		if (count($word_ids) == 0)
			return array();

		if ($operation == 'and')
			$addition = "HAVING kolvo = ".count($word_ids);
		else
			$addition = "";

		if ($format_id !== false)
			$addition2 = "AND d.format_id = $format_id";
		else
			$addition2 = "";
		$query = "
			SELECT SQL_CALC_FOUND_ROWS i.doc_id, d.doc, d.snipped, sum(i.weight) as relevance, count(*) as kolvo
			FROM ".$kernel->pub_prefix_get().self::get_index_table_name()." i, ".$kernel->pub_prefix_get().self::get_docs_table_name()." d
			WHERE i.word_id IN (".implode(",",$word_ids).") AND i.doc_id = d.id  $addition2
			GROUP BY i.doc_id
			$addition
			ORDER BY kolvo DESC, relevance DESC
			LIMIT $limit, $length";
		$res=$kernel->runSQL($query);
		$result = array();
		while ($row = mysql_fetch_assoc($res))
			$result[] = $row;
        mysql_free_result($res);
		return $result;
	}



	public static function found_rows()
	{
        global $kernel;
		return mysql_result($kernel->runSQL("SELECT found_rows()"), 0);
	}



	public static function update_doc_data($url_id, $snipped, $contents_hash, $format_id)
	{
        global $kernel;
		$snipped = mysql_real_escape_string($snipped);
		$query = "UPDATE ".$kernel->pub_prefix_get().self::get_docs_table_name()."
					SET
						snipped = '$snipped', contents_hash='$contents_hash', format_id='$format_id'
					WHERE
						id = $url_id
					LIMIT 1";
		$kernel->runSQL($query);
	}



	public static function optimize_tables()
	{
        global $kernel;
        $kernel->runSQL("LOCK TABLES ".$kernel->pub_prefix_get().self::get_docs_table_name()." WRITE, ".$kernel->pub_prefix_get().self::get_index_table_name()." WRITE");
        $kernel->runSQL("OPTIMIZE TABLE ".$kernel->pub_prefix_get().self::get_docs_table_name());
        $kernel->runSQL("OPTIMIZE TABLE ".$kernel->pub_prefix_get().self::get_index_table_name());
        self::unlock_tables();
	}

    public static function count_pages()
	{
	    global $kernel;
        $total = 0;
	    $result = $kernel->runSQL("SELECT count(*) AS count FROM `".$kernel->pub_prefix_get().self::get_docs_table_name()."`");
        if ($row = mysql_fetch_assoc($result))
            $total = $row['count'];
        mysql_free_result($result);
	    return $total;
	}

    public static function count_words()
	{
	    global $kernel;
        $total = 0;
	    $result = $kernel->runSQL("SELECT count(*) AS count FROM `".$kernel->pub_prefix_get().self::get_words_table_name()."`");
        if ($row = mysql_fetch_assoc($result))
            $total = $row['count'];
        mysql_free_result($result);
	    return $total;
	}

	public static function get_ignored_strings()
	{
	    global $kernel;
        return $kernel->db_get_list_simple(self::get_ignored_table_name(),"true");
	}

    public static function delete_ignored_string($id)
	{
	    global $kernel;
	    $kernel->runSQL("DELETE FROM `".$kernel->pub_prefix_get().self::get_ignored_table_name()."` WHERE id=".$id);
	}

    public static function add_ignored_string($string)
	{
	    global $kernel;
	    $kernel->runSQL("REPLACE INTO `".$kernel->pub_prefix_get().self::get_ignored_table_name()."` (`word`) VALUES ('".$string."')");
	}
}