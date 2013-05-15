<?php

//require_once("pdfobject.class.php");

class UgolPdfObject extends PdfObject
{
	var $after_obj_position;
	var $stream = "";
	var $key;
	var $dictionary_position;

	function UgolPdfObject(&$pdfparser, $key)
	{
		$this->key = $key;
		$this->pdfparser = &$pdfparser;
		$this->after_obj_position = $this->get_afterobj_position($pdfparser, $key);
		$this->parse();
	}


	function parse()
	{
		$ap = $this->after_obj_position;
		if (preg_match("/<<(.+?)>>\s*?(stream|endobj)/s", $this->pdfparser->data, $matches, PREG_OFFSET_CAPTURE, $ap))
		{
			$this->dictionary_str = $matches[0][0];

			//print $this->key." ".$this->dictionary_str."\n";
			$this->dictionary_position = $matches[0][1];
			$dictionaryparser = new DictionaryParser($this->dictionary_str);
			$this->dictionary_array = $dictionaryparser->get_result();
		}
	}

	function fill_stream()
	{
		$dictionary = $this->dictionary_str;
		//print $dictionary."\n";

		if (!isset($this->dictionary_array["/Length"]))
			return;

		$length = $this->dictionary_array["/Length"];

		if (preg_match("/(\d+?)\s+?(\d+?)\sR/", $length, $matches))
		{
			$key = $matches[1]." ".$matches[2];
			/* @var $pdf_object PdfObject */

			$pdf_object = $this->createobject($this->pdfparser, $key);
			$length = (int)trim($pdf_object->get_data());
		}


		//print "$this->key $length\n";

		//$stream_start_position = $this->after_obj_position + strlen($dictionary);
		$stream_start_position = $this->dictionary_position + strlen($dictionary);
		if ($this->pdfparser->data{$stream_start_position} == "\r")
			$stream_start_position++;
		if ($this->pdfparser->data{$stream_start_position} == "\n")
			$stream_start_position++;

		/*$str = substr($this->pdfparser->data, $stream_start_position, 10);
		$str = addcslashes($str, "\r\n");
		*/


		$stream = substr($this->pdfparser->data, $stream_start_position, $length);

		if (isset($this->dictionary_array['/Filter']))
		{
			$filters = $this->dictionary_array['/Filter'];

			if (preg_match("'/ASCII85Decode'", $filters))
				$stream = $this->base85_decode($stream);

			if (preg_match("'/(Flate|LZW)Decode'i", $filters))
				$stream = gzuncompress($stream);
		}

		$this->stream = $stream;

		if (preg_match("'/(DCTDecode|Image|DeviceGray|DeviceRGB|DeviceCMYK|ObjStm)'", $dictionary))
		{
			$this->data = "";
		}
		else
			$this->data = $stream;
	}



	function base85_decode($text)
	{
		$text = preg_replace("/~>.*?$/", "", rtrim($text));
		$result = "";
		for ($i=0; $i<strlen($text); $i++)
		{
			$char = $text{$i};
			$ord = ord($char) - 33;
			if ($ord >= 0 && $ord <= 84)
			{
				$ords[] = $ord;
				if (count($ords) == 5)
				{
					$int = $ords[0]*85*85*85*85 + $ords[1]*85*85*85 + $ords[2]*85*85 + $ords[3]*85 + $ords[4];
					$result .= pack("N", $int);
					$ords = array();
				}
			}
		}

		$count = count($ords);
		if ($count > 0 )
		{
			for ($i=0; $i<5-$count; $i++)
				array_push($ords, 84);

			//$ords = array_reverse($ords);
			$int = $ords[0]*85*85*85*85 + $ords[1]*85*85*85 + $ords[2]*85*85 + $ords[3]*85 + $ords[4];
			$hex = dechex($int);
			$result .= substr(pack("N", $int), 0, $count-1);
		}
		return $result;
	}



	function get_tounicode_ref()
	{
		if (preg_match("'/ToUnicode\s+?(\d+?)\s+?(\d+?)\s+?R'i", $this->dictionary, $matches))
		{
			return $matches[1]." ".$matches[2];
		}
		else
			return false;
	}


	function get_tounicode($default_charset)
	{
		$this->fill_stream();
		//print $this->stream."\n";

		$tounicode = array();
		if (preg_match_all("/beginbfchar(.+?)endbfchar/si", $this->stream, $matches))
		{
			$associates_blocks = $matches[1];
			foreach ($associates_blocks as $associates)
			{
				$lines = preg_split("/(\r|\rn|\n)+/", $associates);
				foreach ($lines as $line)
				{
					if (preg_match_all("'<([a-zA-Z0-9]+?)>'", $line, $matches))
					{
						if (count($matches[1])>=2)
							$tounicode[(string)strtoupper((string)$matches[1][0])] = @iconv("UTF-16BE", $default_charset, pack("H*", $matches[1][1]));
					}
				}

			}
		}

		if (preg_match_all("/beginbfrange(.+?)endbfrange/si", $this->stream, $matches))
		{
			$associates_blocks = $matches[1];
			foreach ($associates_blocks as $associates)
			{
				$lines = preg_split("/(\r|\rn|\n)+/", $associates);
				foreach ($lines as $line)
				{
					if (preg_match_all("'<([a-zA-Z0-9]+?)>'", $line, $matches))
					{
						if (count($matches[1])==3)
						{
							$glyph_size = strlen($matches[1][0]);

							$from = hexdec($matches[1][0]);
							$to = hexdec($matches[1][1]);
							$result = hexdec($matches[1][2]);

							for ($glyph = $from; $glyph <= $to; $glyph++)
							{
								$glyph_format = "%0{$glyph_size}X";
								$result_symbol = pack("H*", sprintf("%04X", $result + $glyph - $from));
								$tounicode[strtoupper(sprintf($glyph_format, $glyph))] = @iconv("UTF-16BE", $default_charset, $result_symbol);
							}
						}
					}
				}
			}
		}


		return $tounicode;
	}


	function get_charset_difference()
	{
		$encoding = array();
		if (!isset($this->dictionary_array['/Differences']))
			return array();

		$arr_str = trim($this->dictionary_array['/Differences'], "[]");

		$arr_str = preg_replace("'\s+?/'", "/", $arr_str);


		$parts = preg_split("/\s+?/", $arr_str);
		$glyph_win_encoding = get_glyph_win_encoding();

		foreach ($parts as $part)
		{
			$codes = explode("/", $part);
			$start_code = $codes[0];

			for ($i=1; $i<count($codes); $i++)
			{
				if (isset($glyph_win_encoding[$codes[$i]]))
					$encoding[$start_code + $i-1] = chr($glyph_win_encoding[$codes[$i]]);
			}
		}
		return $encoding;
	}



	function get_text()
	{
		$this->fill_stream();

		$eolp = "(\r|\r\n|\n)";
		$text = '';

		$Tc = 0;
		static $font_num = "/F1";

		$block = $this->data;

		if (!preg_match("/(TJ|Tj)/", $block))
		{
			return "";
		}

		$lines = preg_split("/$eolp+/", $block);
		foreach ($lines as $line)
		{
			$localtext = "";
			$line = trim($line);


			if (preg_match("'(/(F|TT)(\d+?))\s+?'i", $line, $matches))
			{
				$font_num = $matches[1];
				$localtext .= " ";
			}

			if (preg_match("/(T\*|Tm)/", $line))
				$localtext .= "\n";

			if (preg_match("/([-0-9.]+?)\s+?([-0-9.]+?)\s+?TD$/", $line, $matches))
			{
				if (doubleval($matches[2]) < -0.2)
					$localtext .= "\n";
			}

			if (preg_match("/^(.+?)Tc$/", $line, $matches))
			{
				$Tc = trim($matches[1])*1000;
				//print "Changing Tc to $Tc\n";
			}

			if (preg_match("'TJ$'i", $line))
			{
				$localtext .= $this->parse_tj($Tc, $font_num, $line);
				$Tc = 0;
			}
			//print "$line    === $localtext\n";
			$text .= $localtext;
		}
		return $text;
	}




	function parse_tj($Tc, $font_num, $line)
	{
		$localtext = "";

		if (preg_match("'\[(.+?)\]'", $line, $matches))
			$insider = $matches[1];
		else
			$insider = substr($line, 0, strlen($line)-2);

		$parts = $this->parse_insider($insider);

		$text = "";
		$x = 0;
		foreach ($parts as $part)
		{
			$localtext = "";

			if (-$x + $Tc > 200)
				$space = " ";
			else
				$space = "";

			//print "[$part=(-$x + $Tc)]";

			if ($part{0} == '(')
			{
				$localtext = substr($part, 1, strlen($part)-2);
				$localtext = str_replace("\\(", "(", $localtext);
				$localtext = str_replace("\\)", ")", $localtext);

				if (preg_match_all("/".preg_quote("\\")."([0-7]{3})/", $localtext, $matches))
				{
					foreach ($matches[0] as $key=>$val)
					{
						$localtext = str_replace($val, chr(octdec($matches[1][$key])), $localtext);
						if ($key < count($matches[0])-1 && $Tc > 200)
							$localtext .= " ";
					}
				}

				if (isset($this->pdfparser->charset_differences[$font_num]))
				{
					//print_r($this->pdfparser->charset_differences[$font_num]);
					//die;
					$old_localtext = $localtext;
					$localtext = "";
					for ($i=0; $i<strlen($old_localtext); $i++)
					{
						$char_code = ord($old_localtext{$i});
						if (isset($this->pdfparser->charset_differences[$font_num][$char_code]))
							$localtext .= $this->pdfparser->charset_differences[$font_num][$char_code];
						else
							$localtext .= $old_localtext{$i};
					}
				}
				$localtext = $space.$localtext;
			}
			elseif ($part{0} == "<")
			{
				$hex = substr($part, 1, strlen($part)-2);
				if (!isset($this->pdfparser->tounucodes[$font_num]) || count($this->pdfparser->tounucodes[$font_num]) == 0)
					continue;
				reset($this->pdfparser->tounucodes[$font_num]);
				list ($key, $val) = each($this->pdfparser->tounucodes[$font_num]);
				$key_length = strlen($key);

				for ($i=0; $i < strlen($hex); $i += $key_length)
				{
					$chunk = substr($hex, $i, $key_length);
					//print "$chunk\n";
					if (strlen($chunk) < strlen($key))
						$chunk .= "0";
					$chunk = strtoupper($chunk);

					if (!isset($this->pdfparser->tounucodes[$font_num][(string)$chunk]))
					{
						$localtext .= " ";
						//print "laga[$font_num][".(string)$chunk."]\n";
					}
					else
					{
						$localtext .= $this->pdfparser->tounucodes[$font_num][(string)$chunk];
						if ($i < strlen($hex)-$key_length && $Tc > 200)
							$localtext .= " ";
					}
				}
				$localtext = $space.$localtext;
			}
			else
			{
				$x = (double)$part;
			}
			$text .= $localtext;
		}
		return $text;
	}


	function parse_insider($insider)
	{
		$insider = str_replace("\\(", "artprom{", $insider);
		$insider = str_replace("\\)", "artprom}", $insider);
		$result = array();
		if (preg_match_all("'(<([a-zA-Z0-9]+?)>|\(.+?\))'", $insider, $matches, PREG_OFFSET_CAPTURE))
		{
			$text_blocks = $matches[0];

			$last = 0;

			foreach ($text_blocks as $text_block)
			{
				$space_val = substr($insider, $last, $text_block[1] - $last);
				$trimmed_space_val = trim($space_val);
				if (strlen($trimmed_space_val) > 0)
					$result[] = $trimmed_space_val;

				$text = $text_block[0];
				$text = str_replace("artprom{", "\\(", $text);
				$text = str_replace("artprom}", "\\)", $text);
				$result[] = $text;
				$last = $text_block[1] + strlen($text_block[0]);
			}
		}
		return $result;
	}
}

/*
$res = UgolPdfObject::parse_insider("(A\()120(W)-120.0<1ab3bc>(A) 95 (Y again)");
print_R($res);
*/
?>