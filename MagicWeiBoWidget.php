<?php
/*
Plugin Name: Magic WeiBo Widget
Plugin URI: http://www.himagic.net/
Description: List your WeiBo entries to Widget with customized template. Supports 4hr cache, PHP code, none authentication.
Author: Hi Magic!
Author URI: http://www.himagic.net
History: 
2011/08/11 1.00 release
*/
//add_action('wp_footer', 'CopyrightOfHiMagic');
if (!function_exists('CopyrightOfHiMagic')) {function CopyrightOfHiMagic(){{echo '<a style="display:none;" href="http://www.himagic.net/">Hi Magic!</a>';}}}

global $wp_version;
if (version_compare($wp_version, '2.8', '>='))
{
	class WP_Widget_MagicWeiBoWidget extends WP_Widget 
	{
		function getCurrentVer()
		{
			return trim(substr(trim('
						Version: 1.00
						'), 8));
		}

		function WP_Widget_MagicWeiBoWidget() 
		{
			$widget_ops = array('classname' => 'widget_magicweibowidget', 'description' => __( 'Your blog&#8217;s Magic WeiBo Widget. Ver '.WP_Widget_MagicWeiBoWidget::getCurrentVer()) );
			$control_ops = array('width' => 600, 'height' => 350);
			$this->WP_Widget('magicweibowidget', 'Magic WeiBo Widget', $widget_ops, $control_ops);
		}

		function getNickdate($date)
		{
			$return = 'Unknown';
			$created = strtotime($date);
			if ($created != -1) 
			{
				$now = time();
				$span = $now - $created;
				$s = $span%60;
				$m = floor($span%(60*60)/(60));
				$h = floor($span%(60*60*24)/(60*60));
				$d = floor($span/(60*60*24));
				$ds = gmdate('m月d日 H:i', $created + 8*60*60);
				if ($span < 0) 
				{
					$return = '未来';
				}
				else if ($d == 1) 
				{
					$return = '昨天'.gmdate(' H:i', $created + 8*60*60);
				}
				else if ($d < 1) 
				{
					if ($h < 1) 
					{
						if ($m < 1) 
						{
							$return = $s.'秒前';
						}
						else 
						{
							$return = $m.'分钟前';
						}
					}
					else 
					{
						$return = $h.'小时前';
					}
				}
				else 
				{
					$return = $ds;
				}
			}

			return $return;
		}

		function getCacheJson($url)
		{
			$return = false;
			if ($url !== false) 
			{
				$cacheFile = dirname(__FILE__).'/cache/'.md5($url);
				$cacheDate = @filemtime($cacheFile);
				if ($cacheDate !== false
					&& (time() - $cacheDate) < 60*60*4) 
				{
					$return = file_get_contents($cacheFile);
				}
				else 
				{
					@include('MagicLib.class.php');
					$cacheContent = MagicExtractor::file_get_contents($url);
					if ($cacheContent !== false && $cacheContent !== '') 
					{
						file_put_contents($cacheFile, $cacheContent);
						$return = $cacheContent;
					}
				}
			}
			
			$return = preg_replace('/"id":(\d+)/', '"id":"$1"',$return);

			return json_decode($return, true);
		}

		function widget($args, $instance) 
		{
			extract( $args );
			$title = apply_filters('widget_title', $instance['title']);
			$uid = trim($instance['uid']);
			$template = $instance['template'];
			$description = $instance['description'];
			$count = $instance['count'];
			$source = $instance['source'];

			$user = false;
			$item = false;
			$apiKey = '3451621091';

			if ($source == 'weibo.com' && $uid != '') 
			{
				$item = WP_Widget_MagicWeiBoWidget::getCacheJson('http://api.t.sina.com.cn/statuses/user_timeline/'.$uid.'.json?source='.$apiKey.'&count='.$count);
				//$user = WP_Widget_MagicWeiBoWidget::getCacheJson('http://api.t.sina.com.cn/users/show/'.$uid.'.json?source='.$apiKey);
			}

			if (is_array($item)) 
			{
				$prephase = '';
				$output = '沉默是金';
				$copyright = '<p style="text-align:right;font-size:8px;">v'.WP_Widget_MagicWeiBoWidget::getCurrentVer().' Powered By <a href="http://www.himagic.net/">Hi Magic!</a></p>';

				$itemKeys = array('{TEXT}', '{DATE}', '{FROM}', '{URL}');
				$userKeys = array('{FOLLOWS}', '{NAME}', '{ICON}', '{WEB}');

				if (count($item) > 0) 
				{
					$items = array();
					for ($i = 0; $i < count($item); $i++) 
					{
						if ($source == 'weibo.com') 
						{
							array_push($items, str_replace($itemKeys, array($item[$i]['text'].(($item[$i]['truncated'])?'...':''), WP_Widget_MagicWeiBoWidget::getNickdate($item[$i]['created_at']), $item[$i]['source'], 'http://api.t.sina.com.cn/'.$item[$i]['user']['id'].'/statuses/'.$item[$i]['id']), $template));
							if ($i == 0)

							{
								$user = $item[$i]['user'];
							}
							
						}
					}
					$output = implode("\r\n", $items);
				}

				if (is_array($user)) 
				{
					if ($source == 'weibo.com') 
					{
						$prephase = str_replace($userKeys, array($user['followers_count'], $user['name'], $user['profile_image_url'], (($user['url'] == '') ? 'http://weibo.com/'.$user['id'] : $user['url'])), $description);
					}
				}

				echo $before_widget;
				if (!empty($title))
				{
					echo $before_title . $title . $after_title;
				}
				echo $prephase;
				?>
				<ul>
					<?php eval('?>'.$output); ?>
				</ul>
				<?php
				echo $copyright;
				echo $after_widget;
			}
		}

		function update($new_instance, $old_instance) 
		{
			$instance = $old_instance;
			$instance['title'] = strip_tags($new_instance['title']);
			$instance['uid'] = stripslashes($new_instance['uid']);
			$instance['template'] = stripslashes($new_instance['template']);
			$instance['description'] = stripslashes($new_instance['description']);
			$instance['count'] = $new_instance['count'];
			$instance['source'] = $new_instance['source'];

			return $instance;
		}

		function form($instance) 
		{
			//Defaults
			$instance = wp_parse_args( (array) $instance, array(
				'title' => 'My WeiBo'
				, 'uid' => '2265853200'
				, 'description'=>'<div style="overflow:hidden;padding:4px;background:url(http://img.t.sinajs.cn/t35/style/images/widget/logo_txt.png) right bottom no-repeat;"><img src="{ICON}" style="float:left;margin:2px;"/> <a style="font-size:20px;" href="{WEB}">{NAME}</a> <br>粉丝({FOLLOWS})</div><div style="clear:both;"></div>'
				, 'template'=>'<li>{TEXT}<br/>[<a href="{URL}" ref="nofollow">转发|评论</a> {DATE}]</li>'
				, 'count'=>'5'
				, 'source'=>'weibo.com'));
			$title = esc_attr( $instance['title'] );
			$uid = esc_attr( $instance['uid'] );
			$description = htmlspecialchars($instance['description']);
			$template = htmlspecialchars($instance['template']);
			$count = $instance['count'];
			$source = $instance['source'];
		?>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>">Title(标题):</label> <br/><input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('uid'); ?>">WeiBo UID(微博用户ID, 应该是一串数字):</label> <br/><input class="widefat" id="<?php echo $this->get_field_id('uid'); ?>" name="<?php echo $this->get_field_name('uid'); ?>" type="text" value="<?php echo $uid; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('source'); ?>">WeiBo Source(微博来源):</label> <br/>
				<select id="<?php echo $this->get_field_id('source'); ?>" name="<?php echo $this->get_field_name('source'); ?>">
					<option value="weibo.com" <?=($source=='weibo.com')?'selected':''?>>WeiBo.com(Sina)</option>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('count'); ?>">Display count(显示条目, 将要显示的微博数量):</label> <br/>
				<select id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>">
					<option value="5" <?=($count=='5')?'selected':''?>>5</option>
					<option value="5" <?=($count=='10')?'selected':''?>>10</option>
					<option value="5" <?=($count=='15')?'selected':''?>>15</option>
					<option value="5" <?=($count=='20')?'selected':''?>>20</option>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('description'); ?>">Description(说明, 显示在微博列表前面，可放置头像等):</label><br/>
				<textarea id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>" style="width: 600px; height: 120px;"><?=$description?></textarea><br>
				可用关键词，{NAME}=博主名 {ICON}=博主头像 {WEB}=博主主页 {FOLLOWS}=粉丝数
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('template'); ?>">Template(模板):</label><br/>
				<textarea id="<?php echo $this->get_field_id('template'); ?>" name="<?php echo $this->get_field_name('template'); ?>" style="width: 600px; height: 80px;"><?=$template?></textarea><br>
				可用关键词，{TEXT}=微博内容 {DATE}=发表日期 {FROM}=发表来源 {URL}=微博链接
			</p>
			<p>
				<label>Link for cleaning cache(清除缓存链接, 可利用该链接强制更新缓存):</label><br/>
				<a style="color:red;" href="<?=WP_PLUGIN_URL.'/'.substr(dirname(__FILE__), strrpos(str_replace('\\', '/', dirname(__FILE__)), '/')+1)?>/cleaner.php">右键点击此处，选择复制快捷方式</a><br>
				微博信息将默认被缓存4小时，在需要时可访问此链接强制清除缓存，达到及时刷新缓存的目的
			</p>
			<p><i><font style="font-size:small;" color="">(PHP code should be enclosed in &lt;?php and ?&gt; tags)</font></i></p>
	<?php
		}
	}
	add_action('widgets_init', create_function('', 'return register_widget("WP_Widget_MagicWeiBoWidget");'));
}
?>