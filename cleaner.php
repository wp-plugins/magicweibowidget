<?php
$path = dirname(__FILE__).'/cache';
$dir = @opendir($path);
$msg = '';
if ($dir)
{
	$removed = 0;
	while (($file = readdir($dir)) !== false)
	{
		if (is_file($path.'/'.$file) && @unlink($path.'/'.$file))
		{
			$removed++;
		}
	}
	$msg = 'Totally removed '.$removed.' cache file(s)';
}
else 
{
	$msg = 'Not found cache folder';
}
@closedir($dir);
echo $msg;
?>