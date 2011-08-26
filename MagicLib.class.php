<?php
/*
Author: Hi Magic!
Author URI: http://www.himagic.net
*/
if (!class_exists('MagicExtractor')) : 
Class MagicExtractor
{
	function files_get_contents($urls)
	{
		$return = array();
		$mh = curl_multi_init();
		$conn = array();
		foreach ($urls as $i=>$url) 
		{
			$conn[$i] = curl_init($urls[$i]);
			curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
			curl_multi_add_handle($mh, $conn[$i]);
		}

		do 
		{
			$mrc = curl_multi_exec($mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) 
		{
			if (curl_multi_select($mh) != -1) 
			{
				do 
				{
					$mrc = curl_multi_exec($mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}

		foreach ($urls as $i=>$url) 
		{
			$return[$i] = curl_multi_getcontent($conn[$i]);
			curl_close($conn[$i]);
		}
		
		return $return;
	}

	function file_get_contents($url, $options=false) 
	{
		$result = false;
		if (in_array('curl', get_loaded_extensions()))
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)');
			if ($options !== false) 
			{
				if (isset($options['useragent'])) 
				{
					curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
				}
				if (isset($options['cookie'])) 
				{
					curl_setopt($ch, CURLOPT_COOKIE, $options['cookie']);
				}
				if (isset($options['header'])) 
				{
					curl_setopt($ch, CURLOPT_HEADER, $options['header']);
				}
				if (isset($options['headers'])) 
				{
					if (!is_array($options['headers'])) 
					{
						$options['headers'] = array($options['headers']);
					}
					curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
				}
				if (isset($options['post'])) 
				{
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post']);
				}
				if (isset($options['refer'])) 
				{
					curl_setopt($ch, CURLOPT_REFERER, $options['refer']);
				}
			}
			//curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8000');
			curl_setopt($ch, CURLOPT_URL, $url);
			$result = curl_exec($ch);
			curl_close($ch);
		}
		if ($result === false) 
		{
			$result = @file_get_contents($url);
		}
		
		return $result;
	}

	function cleanLink($content)
	{
		preg_match_all("'<a [^>]*?>[^>]*?</a>'si", $content, $matches);
		if (count($matches) > 0)
		{
			$ori = array();
			$cur = array();
			foreach ($matches[0] as $match) 
			{
				array_push($ori, $match);
				$match = str_replace('"', '\'', $match);
				$match = preg_replace("'href'si", 'href', $match);
				$match = MagicExtractor::magicSubStr($match, 'href=\'', '\'');
				array_push($cur, $match);
			}
			$content = str_replace($ori, $cur, $content);
		}
		return $content;
	}

	function cleanHTMLComment($content)
	{
		$return = $content;
		while (strpos($return, '<!--') !== false) 
		{
			if (strpos($return, '-->') === false) 
			{
				$return .= '-->';
			}
			$comment = MagicExtractor::magicSubStr($return, '<!--', '-->');
			$return = str_replace('<!--' . $comment . '-->', '', $return);
		}
		return $return;
	}

	function getRandomKey($min, $max = 0, $chars = 'AaBbCDdEeFfGgHhIiJjKLMNnPQqRrSTtUVWXYZ023456789')
	{
		$charA = str_split($chars);
		$count = count($charA) - 1;
		if ($max == 0) 
		{
			$max = $min;
		}
		$number = rand($min, $max);
		$return = '';

		for ($i = 0; $i < $number; $i++) 
		{
			$return .= $charA[rand(0, $count)];
		}

		return $return;
	}

	function unHtmlSpecialChars($string)
	{
		$string = str_replace('&lt;', '<', $string);
		$string = str_replace('&gt;', '>', $string);
		$string = str_replace('&quot;', '"', $string);
		$string = str_replace('&#039;', "'", $string);
		$string = str_replace('&amp;', '&', $string);

		return $string;
	}

	function magicGetWebPage($url)
	{
		$content = MagicExtractor::file_get_contents($url);
		if( $content === false)
		{
			$content = '';
		}

		return $content;
	}

	function magicGrabWebPage($url, $path, $filter = 'img,script,link', $realName = true)
	{
		$md5Name = md5($url);
		$result = $this->magicGetWebResource($url, $path.'/'.$md5Name, $realName);
		if ($result != false) 
		{
			$info = parse_url($url);
			$newContent = $this->magicLocalizeResource($result['content'], $path.'/'.$md5Name, $filter, $url, $realName);
			if (file_exists($result['filename']) && $fp = fopen($result['filename'], "w")) 
			{
				if (fwrite($fp, $newContent))
				{
					echo 'updated!';
				}
				fclose($fp);
			}
		}
	}

	function magicSubStr($string, $start, $end)
	{
		$tmpString = $string;
		if (strpos($tmpString, $start) !== false) 
		{
			$tmpString = substr($tmpString, strpos($tmpString, $start) + strlen($start));
			if (strpos($tmpString, $end) !== false) 
			{
				$tmpString = substr($tmpString, 0, strpos($tmpString, $end));
			}
			else 
			{
				$tmpString = '';
			}
		}
		else 
		{
			$tmpString = '';
		}
		
		return $tmpString;
	}

	function magicMatch($string, $regs, $limit=-1)
	{
		$return = array();
		$pos = 0;
		$firstReg = null;

		while (true) 
		{
			$tmpResult = array();
			foreach ($regs as $reg) 
			{
				$findMode = false;
				if ($firstReg === null) 
				{
					$firstReg = $reg['start'];
				}
				$tmpStr = '';
				$tmpPos = strpos($string, $reg['start'], $pos);
				if ($tmpPos !== false) 
				{
					$pos = $tmpPos + strlen($reg['start']);
					if ($reg['end'] === '') 
					{
						$findMode = true;	//~~just update pos in this loop
					}
					else 
					{
						$tmpPos = strpos($string, $reg['end'], $pos);
						if ($tmpPos !== false) 
						{
							$tmpStr = substr($string, $pos, ($tmpPos - $pos));
							$pos = $tmpPos;
							if (isset($reg['remove'])) 
							{
								$tmpStr = str_replace($reg['remove'], '', $tmpStr);
							}
						}
					}
				}
				if ($tmpStr === false) 
				{
					$tmpResult = array();
					break;
				}
				else 
				{
					array_push($tmpResult, $tmpStr);
				}
			}

			if (count($tmpResult) > 0) 
			{
				array_push($return, $tmpResult);
			}
			
			if ($firstReg === null 
				|| strpos($string, $firstReg, $pos) === false) 
			{
				break;
			}

			if ($limit != -1 && count($return) >= $limit) 
			{
				break;
			}
		}

		return $return;
	}

	function magicLocalizeResource($text, $path, $filter='img', $baseUrl = '', $realName = true)
	{
		if ($filter == '') 
		{
			return false;
		}

		$tags = split(',', $filter);

		for ($i = 0; is_array($tags) && $i < count($tags); $i++) 
		{
			$tag = trim($tags[$i]);
		
			preg_match_all("'<".$tag."[^>]*?>'si", $text, $matches);
			
			$previousSrc = array();
			foreach ($matches as $matche)
			{
				foreach ($matche as $oneTag)
				{
					$matchProp = 'src';
					if (strtolower($tag) === 'object')
					{
						$matchProp = 'value';
					}
					if (strtolower($tag) === 'link')
					{
						$matchProp = 'href';
					}
					preg_match("'".$matchProp."[\s]*=[\s]*([\"|\'| ]?)([^\"|\'| ]*)([\"|\'| ])'si", $oneTag, $src);
					if (count($src) > 0) 
					{
						$previousSrc[]=$src[2];
					}
				}
			}
		
			$currentSrc = array();

			foreach ($previousSrc as $key=>$url) 
			{
				$info = parse_url($baseUrl);
				if (!isset($info['port'])) 
				{
					$info['port'] = 80;
				}
				$preUrl = $info['scheme'].'://'.$info['host'].':'.$info['port'];
				if (!strstr($url, '://')) 
				{
					if (substr($url, 0, 1) == '/') 
					{
						$url = $preUrl . $url;
					}
					else 
					{
						$url = substr($baseUrl, 0, strpos($baseUrl, basename($baseUrl))) . $url;
					}
				}

				if ($tmpResult = $this->magicGetWebResource($url, $path, $realName)) 
				{
					$currentSrc[$key] = $tmpResult['fileurl'];
				}
				else 
				{
					array_splice($previousSrc, $key);
				}
			}

			$text = str_replace($previousSrc, $currentSrc, $text);	
		}

		return $text;
	}

	function magicGetWebResource($url, $path, $filename = true)
	{
		if (!(($uploads = wp_upload_dir()) && false === $uploads['error']))
		{
			return false;
		}

		$return = array();
		$return['filename'] = $filename;

		$url = (strtolower(substr($url, 0, 4)) == 'http') ? $url : 'http://'.$url;
		$return['content'] = MagicExtractor::file_get_contents($url);

		if ($return['filename'] === false) 
		{
			$baseName = substr($url, strrpos($url, '.'));
			$return['filename'] = substr(md5(time().chr(rand(1,150))),rand(0,25),6);
			//$return['filename'] = wp_unique_filename($uploads['path'], $return['filename'], $unique_filename_callback);
			$return['filename'] = wp_unique_filename($uploads['path'], $return['filename']);
			$return['filename'] = $uploads['path'].'/'.$path.'/'.rawurlencode($return['filename'].$baseName);
			$return['fileurl'] = $uploads['url'].'/'.$path.'/'.rawurlencode($return['filename'].$baseName);
		}
		else if ($return['filename'] === true) 
		{
			$name = basename($url);
			$return['fileurl'] = $uploads['url'].'/'.$path.'/'.rawurlencode($name);
			$return['filename'] = $uploads['path'].'/'.$path.'/'.rawurlencode($name);
		}
		else 
		{
			$return['fileurl'] = $uploads['url'].'/'.$path.'/'.rawurlencode($return['filename']);
			$return['filename'] = $uploads['path'].'/'.$path.'/'.rawurlencode($return['filename']);
		}

		$pathArr = split('/', $path);
		$prePath = $uploads['path'];
		for ($i = 0; $i < count($pathArr); $i++) 
		{
			$prePath = $prePath . '/' . $pathArr[$i];
			@mkdir($prePath, 0777);
		}

		if (file_exists($uploads['path'].'/'.$path) && $fp = fopen($return['filename'], "w+")) 
		{
			if (fwrite($fp, $return['content']))
			{
				fclose($fp);
				return $return;
			}
			else 
			{
				fclose($fp);
			}
		}

		return false;
	}

	function magicTextExtractor($cond, $data, $debug = false)
	{
		$result = array();
		if ($cond == '' || $data == '') 
		{
			return $result;
		}

		$dataPos = 0;
		$dataLen = strlen($data);
		$resultX = 0;
		$resultY = 0;

		$statement = 0;

		for ($condArrIndex = 0; $condArrIndex < count($cond) && $dataPos < $dataLen;) 
		{
			$oneCond = $cond[$condArrIndex];
			$result[$resultX] = array();

			if ($debug) 
			{
				echo '<br/><b>Now parsing #'.$statement.' statment</b><br/>';
			}

			if ($debug && $statement > 256) 
			{
				break;
			}

			while ($dataPos < $dataLen) 
			{
				$subResult = '';
				$foundResult = false;
				$conditionFinish = false;
				$posStep = 0;

				if ($oneCond['condition']['what'] == '') 
				{
					$conditionFinish = true;
					$condIndex = $dataLen; //~~avoid nextStatement = 1 & posStep = 1
				}
				else 
				{
					$condIndex = strpos($data, $oneCond['condition']['what'], $dataPos);
				}

				if ($oneCond['start'] == '')
				{
					$subResult = substr($data, $dataPos);
					$posStep = strlen($subResult);
					if ($debug) 
					{
						echo '$oneCond[start] is empty, $subResult = '.htmlspecialchars($subResult).' $posStep = '.$posStep.'<br/>';
					}
					$foundResult = true;
					$conditionFinish = true;
				}
				else 
				{
					$startIndex = strpos($data, $oneCond['start'], $dataPos);
					if ($debug) 
					{
						echo '$startIndex = '.$startIndex.'<br/>';
						echo '$condIndex = '.$condIndex.'<br/>';
						echo '$condIndex === false '.(($condIndex === false)?'true':'false').'<br/>';
					}

					if ($startIndex === false 
						|| ($condIndex !== false && $condIndex < $startIndex)
						) 
					{
						$conditionFinish = true;
						$posStep = $condIndex + strlen($oneCond['condition']['what']);
					}
					else 
					{
						if ($oneCond['end'] == '') 
						{
							$subResult = substr($data, $startIndex);
							$posStep = strlen($subResult);
							$foundResult = true;
							$conditionFinish = true;
						}
						else 
						{
							$endIndex = strpos($data, $oneCond['end'], $startIndex);
							if ($debug) 
							{
								echo '$endIndex = '.$endIndex.'<br/>';
							}

							if ($endIndex === false) 
							{
								$subResult = substr($data, $startIndex);
								$posStep = strlen($subResult);
								$foundResult = true;
								$conditionFinish = true;
							}
							else 
							{
								$tmpIndex = $startIndex + strlen($oneCond['start']);
								$subResult = substr($data, $tmpIndex, ($endIndex - $tmpIndex));
								$posStep = $endIndex + strlen($oneCond['end']);
								$foundResult = true;
							}
						}
					}
				}

				$dataPos = $posStep;
				if ($debug) 
				{
					echo '$dataPos = '.$dataPos.'<br/><br/>';
				}
				
				if ($foundResult) 
				{
					if ($debug) 
					{
						echo 'found matched result:'.htmlspecialchars($subResult).'<br/>';
					}
					array_push($result[$resultX], $subResult);
				}

				if ($conditionFinish) 
				{
					$resultX++;
					$condArrIndex += max(0, $oneCond['condition']['where']);
					if ($debug) 
					{
						echo 'this statement is terminated, we should goto #'.$condArrIndex.'<br/>';
					}
					break;
				}
			}
			$statement++;

		}
		
		if ($debug) 
		{
			echo '<pre>';
			print_r($result);
			echo '</pre>';
		}

		return $result;
	}
}
endif;



if (!function_exists('wp_unique_filename')):
	function wp_unique_filename( $dir, $filename, $unique_filename_callback = NULL ) {
		$filename = strtolower( $filename );
		// separate the filename into a name and extension
		$info = pathinfo($filename);
		$ext = $info['extension'];
		$name = basename($filename, ".{$ext}");
		
		// edge case: if file is named '.ext', treat as an empty name
		if( $name === ".$ext" )
			$name = '';

		// Increment the file number until we have a unique file to save in $dir. Use $override['unique_filename_callback'] if supplied.
		if ( $unique_filename_callback && function_exists( $unique_filename_callback ) ) {
			$filename = $unique_filename_callback( $dir, $name );
		} else {
			$number = '';

			if ( empty( $ext ) )
				$ext = '';
			else
				$ext = strtolower( ".$ext" );

			$filename = str_replace( $ext, '', $filename );
			// Strip % so the server doesn't try to decode entities.
			$filename = str_replace('%', '', sanitize_title_with_dashes( $filename ) ) . $ext;

			while ( file_exists( $dir . "/$filename" ) ) {
				if ( '' == "$number$ext" )
					$filename = $filename . ++$number . $ext;
				else
					$filename = str_replace( "$number$ext", ++$number . $ext, $filename );
			}
		}

		return $filename;
	}
endif;

//$extractor = new MagicExtractor();
//$extractor->magicGrabWebPage('http://mdn.mainichi.jp/mdnnews/news/20080920p2a00m0na005000c.html', 'localhost');


/*
$htmlData = '<b>hello</b>heyhey<b>world</b><br/><b>hi</b>heyhey<b>magic</b>';

$conditionArr = array
				(
					array
					(
						'start' => '<b>',
						'end' => '</b>',
						'condition' => array('what' => '<br/>', 'where' => 0)
					)
				);


$duowanStatement1 = array
					(
						array
						(
						'start' => '<ul class="first_b_te first_b_time">',
						'end' => '</ul>',
						'condition' => array('what' => '', 'where' => 0)
						)
					);
$duowanStatement2 = array
					(
						array
						(
						'start' => '<li><span>',
						'end' => '</span>',
						'condition' => array('what' => '<a target="_blank"', 'where' => 1)
						)
					);
$tmpResult = magicTextExtractor($duowanStatement1, magicGetWebPage('http://psp.duowan1.com/'));
$finalResult = magicTextExtractor($duowanStatement2, $tmpResult[0][0]);
/*/
?>