<?php

class DictionaryParser
{
	var $dictionary_string;
	var $offset;
	var $result;
	var $eod = false;

	function DictionaryParser($dictionary_string, $offset = 0)
	{
		$this->offset = $offset;
		$this->dictionary_string = $dictionary_string;
		$this->parse();
	}

	function parse()
	{
		$result = array();
		$key = false;
		do
		{
			$key = $this->search_key();
			if ($key !== false)
			{
				$value = $this->search_value();
				$result[(string)$key] = $value;
			}
		}while (($key !== false) && !$this->eod);
		$this->result = $result;

		//$this->search_value();
	}

	function get_result()
	{
		return $this->result;
	}

	function get_last_offset()
	{
		return $this->offset;
	}

	function search_key()
	{

		if (preg_match("'(/(.+?))(\s|\[|\(|/|>>|<<)'", $this->dictionary_string, $matches, PREG_OFFSET_CAPTURE, $this->offset))
		{
			$key = $matches[1][0];
			$this->offset = $matches[3][1];
			return $key;
		}
		else
			return false;
	}

	function search_value()
	{

		if (preg_match("'\S+?\s*?(/|<<|>>)'s", $this->dictionary_string, $matches, PREG_OFFSET_CAPTURE, $this->offset))
		{

			$new_offset = $matches[1][1];
			$value = substr($this->dictionary_string, $this->offset, $new_offset - $this->offset);
			$value = trim($value);

			if ($value{0} == '[' && preg_match("'\[(.+?)\]'s", $this->dictionary_string, $matches2, PREG_OFFSET_CAPTURE, $this->offset))
			{
				$value = $matches2[0][0];
				$this->offset = $matches2[0][1] + strlen($value);
				return $value;
			}



			if (substr($value, 0, 2) == "<<")
			{
				$dict_obj = new DictionaryParser($this->dictionary_string, $this->offset);
				$value = $dict_obj->get_result();
				$this->offset = $dict_obj->get_last_offset();
			}
			else
				$this->offset = $new_offset;

			$symbol = $matches[1][0];


			if ($symbol == ">>")
			{
				$this->eod = true;
				//$this->offset = $new_offset;
			}


			return $value;

		}
	}


}

/*
$dict_str = <<< EOD
<< /Type /Example
/Subtype /DictionaryExample
/Link 0 66 R
/Version 0.01
/IntegerItem 12
/StringItem (a string)
/Subdictionary << /Item1 0.4
/Item2 true
/LastItem (not!)
/VeryLastItem (OK)
>>
>>
EOD;

$dict_str = "<< /Type /Page
/Parent 2 0 R
/Resources 10 0 R
/Contents 30 0 R
/CropBox[0 0 225 225]
>>";
*/
//$dict_str = "<</ColorSpace<</Cs6 12 0 R>>/Font<</F1 11 0 R/F2 19 0 R/F3 20 0 R/F4 21 0 R/F5 22 0 R/F6 23 0 R/F7 24 0 R/F8 25 0 R>>/XObject<</Im1 61 0 R/Im2 62 0 R/Im3 63 0 R/Im4 64 0 R/Im5 65 0 R/Im6 66 0 R/Im7 67 0 R/Im8 68 0 R/Im9 69 0 R/Im10 70 0 R>>/ProcSet[/PDF/Text/ImageB]/ExtGState<</GS1 71 0 R/GS2 72 0 R>>>>";

//$dict_str = "<</Type/Font/Encoding 29 0 R/BaseFont/PPALIC+Newton-Plain/FirstChar 32/LastChar 255/Subtype/Type1/ToUnicode 30 0 R/FontDescriptor 32 0 R/Widths[233 280 280 280 280 899 280 280 392 392 280 280 326 448 288 280 531 526 532 532 532 532 532 531 531 532 288 326 280 280 280 474 280 724 714 776 872 706 280 280 875 280 280 280 280 280 280 280 280 280 769 599 280 873 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 756 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 280 481 280 280 280 280 280 280 280 280 280 280 280 280 280 1155 280 481 280 280 280 280 724 699 714 624 747 706 1022 597 886 280 812 735 1034 875 809 876 662 776 692 729 934 753 857 756 280 280 280 280 685 759 280 742 495 559 523 434 539 489 757 449 622 622 577 550 715 636 566 622 564 499 468 503 764 500 621 552 880 880 560 760 501 517 820 546]>>";

/*
$dict_str = "28 0 obj<</Type/Encoding/Differences[1/afii10033/afii10034/afii10032/afii10038/afii10022/afii10035/afii10026/afii10031/afii10017/afii10029/afii10046/afii10045/afii10027/afii10024/afii10037/afii10049/afii10018 32/space 40/parenleft/parenright 48/zero/one/two 52/four]>>";

$dict_str = "<<
/Type /Page
/Parent 1 0 R
/Resources 6 0 R
/Contents [ 29 0 R 46 0 R 48 0 R 50 0 R 52 0 R 54 0 R 61 0 R 63 0 R ]
/MediaBox [ 0 0 1162 1587 ]
/CropBox [ 0 0 1162 1587 ]
/Rotate 0
>>
";

$dict_str = "<<
/Type /Page
/Parent 1 0 R
/Resources 6 0 R
/Contents [ 29 0 R 46 0 R 48 0 R 50 0 R 52 0 R 54 0 R 61 0 R 63 0 R ]
/MediaBox [ 0 0 1162 1587 ]
/CropBox [ 0 0 1162 1587 ]
/Rotate 0
>>
endobj";

$dp = new DictionaryParser($dict_str);
print_r($dp->get_result());

*/
?>