<?php 
/*
Plugin Name: RePost
Plugin URI: http://pirex.com.br/wordpress-plugins/
Description: Creates a interface to republish content from others blogs
Author: Leo Germani
Stable tag: 1.1
Author URI: http://pirex.com.br/wordpress-plugins

    RePost is released under the GNU General Public License (GPL)
    http://www.gnu.org/licenses/gpl.txt

*/

function repost_xajax(){
	global $xajax;
	$xajax->registerFunction("repost_subscribeToBlog");
	$xajax->registerFunction("repost_refresh_view");
	$xajax->registerFunction("repost_showSingle");
	$xajax->registerFunction("repost_markRead");
	$xajax->registerFunction("repost_republish");
	$xajax->registerFunction("repost_clearCache"); 
	
}


load_plugin_textdomain('repost', 'wp-content/plugins/repost');

require_once(ABSPATH . "wp-content/plugins/repost/repost_interface.php");

$repost_tablesrc = $wpdb->prefix."repost_sources";
$repost_tableitem = $wpdb->prefix."repost_items";


function repost_tables(){
	global $wpdb;
	
	$repost_tablesrc = $wpdb->prefix."repost_sources";
	$repost_tableitem = $wpdb->prefix."repost_items";
	
	mysql_query("CREATE TABLE IF NOT EXISTS ".$repost_tablesrc." (
	  `id` int(10) unsigned NOT NULL auto_increment,
	  `type` text NOT NULL,
	  `url` text NULL,
	  `blog_id` int(10) NULL,
	  `title` varchar(255) NOT NULL default '',
	  `link` varchar(255) default NULL,
	  `description` varchar(255) default NULL,
	  `xml` text,
	  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
	  `last_item` int(10) NULL,
	  PRIMARY KEY  (`id`)	  
	)");


	mysql_query("CREATE TABLE IF NOT EXISTS ".$repost_tableitem." (
	  `id` int(10) unsigned NOT NULL auto_increment,
	  `source_id` int(10) unsigned NOT NULL default '0',
	  `original_id` int(10) unsigned NOT NULL default '0',
	  `link` text,
	  `title` text,
	  `content` text,
	  `author` varchar(255) default NULL,
	  `modified` datetime default NULL,
	  `xml` text,
	  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
	  `old` tinyint(1) NOT NULL default '0',
	  `published` tinyint(1) NOT NULL default '0',
	  
	  PRIMARY KEY  (`id`)
	  
	) ");

	
	update_option("repost_dbversion","AA ".$repost_tablesrc." AA".mysql_error());
	
}

function repost_add_menu(){
	
	if (function_exists("add_management_page")) add_management_page(__('Repost Settings',"repost"), __('Repost',"repost"), 8, basename(__FILE__), 'repost_admin_page');
	if (function_exists("add_submenu_page")) add_submenu_page('post.php',__('Repost',"repost"), __('Repost',"repost"), 8, basename(__FILE__), 'repost_write_page');
	

}


function repost_admin_page(){
	global $wpdb, $blog_id, $repost_tablesrc,$repost_tableitem;
	
	if(isset($_POST["unsubscribe"])){		
		if(is_array($_POST["sources"])){			
			$i=0;
			for($i==0;$i<sizeof($_POST["sources"]);$i++){				
				repost_unsubscribeToBlog($_POST["sources"][$i]);				
			}	
		}		
	}
	
	if(isset($_POST["submit_options"])){		
		
		$newOpt = array();
		$newOpt["top_comment"] = $_POST["top_comment"];
		$newOpt["clearCache"] = $_POST["clearCache"];
		$newOpt["clearCache_old"] = $_POST["clearCache_old"];
		update_option("repost",$newOpt);
		
	}
	
	if (!get_option("repost")){
		$newOpt = array();
		$newOpt["top_comment"] = __("Post originally published in #LINK# on #DATE#");
		$newOpt["clearCache"] = 30;
		$newOpt["clearCache_old"] = 0;
		update_option("repost",$newOpt);	
	}
	$options = get_option("repost");
	?>
	
	<div class="wrap">
	
	<h2><?php _e("Repost Settings","repost"); ?></h2>
	<BR>
	<a href="post-new.php?page=repost.php"><?php _e("Republish Content!","repost"); ?></a>
	
	<BR>
	<form name="repost_sources" method="post">
	<input type="hidden" name="unsubscribe" value="1">
	<BR>
	<h3><?php _e("Current Subscribed Sources","repost"); ?></h3>
	
	<table class="widefat">
	<thead>
	<tr class="thead">
		<th scope="col" class="check-column"></th>
		<th><?php _e('Source Name') ?></th>
		<th><?php _e('Type') ?></th>
		
	</tr>
	</thead>
	<tbody id="sources_list" class="list:user user-list">
	
	<?php
	
	
	$srcs = $wpdb->get_results("SELECT * FROM $repost_tablesrc ORDER BY type");
	
	foreach($srcs as $src){
		$type = "mu_blog" ? __("Community Blog","repost") : __("RSS Feed","repost");
		?>
		<tr>
		<td ><input type="checkbox" name="sources[]" value="<?php echo $src->blog_id; ?>"></td>
		<td><?php echo $src->title; ?></td>
		<td><?php echo $type; ?></td>
		
		</tr>
		<?php
		
	}
	?>
	
	</tbody>
	</table>
	<div class="submit">
	<input type="button" onClick="if(confirm('<?php _e('Are you sure you want to unsubscribe from this sources?', 'repost'); ?>')) submit();" name="submit_unsubscribe" value="<?php _e('Unsubscribe to Sources', 'repost'); ?> &raquo;">
	</div>
	</form>
	<form name="repost_new_source" method="post">
	<BR><BR>
	

	<h3><?php _e("Subscribe to a blog from the community","repost"); ?></h3>
	
	<?php
	
	if (function_exists("get_site_option")){
		echo "<select name='blogs'>";
		//$blogs = get_site_option( "blog_list" );
		$blogs = get_blog_list();
			if( is_array( $blogs ) ) {
				
				
				foreach ( (array) $blogs as $b ) {
						if($blog_id!=$b['blog_id']){
							echo "<option value='".$b['blog_id']."'";
							if($b['blog_id']==$options["mainBlog"]) echo " selected";
							echo ">".$b['domain']."</option>";	
						}						
				}
				
			}
		echo "</select>";
		?>
		
		<div class="submit">
		<input type="button" onClick="xajax_repost_subscribeToBlog(document.repost_new_source.blogs[document.repost_new_source.blogs.selectedIndex].value)" name="submit_addblog" value="<?php _e('Subscribe to community blog', 'repost'); ?> &raquo;">
		</div>
		
		<?php
	}else{
		echo __("Subscribe to community blog is only possible with wordpress MU","repost");	
	}
	
	?>
	
	</form>
	<BR><BR>
	<form name="repost_options" method="post">
	
	<h3><?php _e("Add this on the top of republished content","repost"); ?></h3>
	
	<textarea name="top_comment" style="width:300px"><?php echo $options["top_comment"]; ?></textarea>
	
	<BR>
	<h3><?php _e("Auto clear cache table","repost"); ?></h3><?php _e("Delete read posts from temporary table","repost"); ?><BR>
	<input type="radio" name="clearCache" value="1" <?php if($options["clearCache"]==1) echo "checked"; ?>> <?php _e("Every day","repost"); ?>
	<input type="radio" name="clearCache" value="10" <?php if($options["clearCache"]==10) echo "checked"; ?>> <?php _e("Every 10 days","repost"); ?>
	<input type="radio" name="clearCache" value="30" <?php if($options["clearCache"]==30) echo "checked"; ?>> <?php _e("Every 30 days","repost"); ?>
	<input type="radio" name="clearCache" value="0" <?php if($options["clearCache"]==0) echo "checked"; ?>> <?php _e("Never","repost"); ?>
	
	<BR><BR>
	<?php _e("Delete all posts, even unread, from temporary table after they've been there for","repost"); ?>
	
	<select name="clearCache_old">
	
		<option value="0"><?php _e("Never","repost"); ?></option>
		<?php 
		$x=1;
		for ($x==1; $x<91; $x++) {
			echo "<option value='$x'";
			if($x==$options["clearCache_old"]) echo " selected";
			echo ">$x</option>";	
		}
		?>
	
	</select>
	
	<?php _e("days","repost"); ?>
	<div class="submit">
	<input type="submit" name="submit_options" value="<?php _e('Save Options', 'repost'); ?> &raquo;">
	</div>
	
	</form>
	
	
	<?php
}


function repost_subscribeToBlog($blog){
	global $wpdb, $repost_tablesrc,$repost_tableitem;
	$tableName = $wpdb->prefix."repost_sources";
	
	switch_to_blog($blog);
	$type = "mu_blog";
	$url = get_option("siteurl");
	$title = get_option("blogname");
	$description = get_option("description");
	
	$objResponse = new xajaxResponse();
	
	$i = $wpdb->get_var("SELECT COUNT(*) FROM $repost_tablesrc WHERE blog_id = $blog");
	
	if (!$i){
		
		if(mysql_query("INSERT INTO $tableName(type, url, blog_id, title, description) VALUES('$type','$url','$blog','$title','$description')")){
		
			$type_r = ($type == "mu_blog") ? __("Community Blog","repost") : __("RSS Feed","repost");
			$result = "
				<tr>
				<td><input type='checkbox' name='sources[]' value='$blog'></td>
				<td>$title</td>
				<td>$type_r</td>
				</tr>";
			$objResponse->addAppend("sources_list","innerHTML",$result);
			repost_updateBlogSources(true);		
		}	
	}
	return $objResponse;
}

function repost_unsubscribeToBlog($blog){
	global $wpdb, $repost_tablesrc,$repost_tableitem;
	
	mysql_query("DELETE FROM $repost_tableitem WHERE source_id IN (SELECT id FROM $repost_tablesrc WHERE blog_id = $blog)");
	mysql_query("DELETE FROM $repost_tablesrc WHERE blog_id = $blog");
	
}



function repost_updateBlogSources($force = false){
	global $wpdb, $repost_tablesrc,$repost_tableitem;

	$now = date("Y-m-d H:i:s");
	$srcs = $wpdb->get_results("SELECT * FROM $repost_tablesrc WHERE type = 'mu_blog'");
	
	foreach($srcs as $src){
		
		$lastUpdate = strtotime($src->timestamp);
		
		#Update only once every 3 minutes
		if($force || 180 < (time() - $lastUpdate)){
			mysql_query("UPDATE $repost_tablesrc SET timestamp = '$now' WHERE id = ".$src->id);
						
			$lastItem = $wpdb->get_var("SELECT last_item FROM $repost_tablesrc WHERE id = ".$src->id);
			$tableposts = $wpdb->base_prefix . $src->blog_id . "_posts";
			$filter = ($lastItem) ? "AND ID > $lastItem" : "";
			$newQuery = "SELECT * from $tableposts WHERE post_type = 'post' AND post_status = 'publish' $filter ORDER BY ID ASC";
			$newPosts = $wpdb->get_results($newQuery);
			
			foreach($newPosts as $newPost){
				
				$orig_id = $newPost->ID;
				$title = $newPost->post_title;
				$content = $newPost->post_content;
				$author = $newPost->post_author;
				$modified = $newPost->post_modified;
				$link = get_blog_permalink($src->blog_id, $newPost->ID);
				$orig_id = $newPost->ID;
				
				mysql_query("INSERT INTO $repost_tableitem(source_id,original_id,link,title,content,author,modified) VALUES(".$src->id.",$orig_id,'$link','$title','$content','$author','$modified')");
				mysql_query("UPDATE $repost_tablesrc SET last_item = $orig_id");
			
			}
		}
	}
}


function repost_clearCache($response=false,$force=false){
	global $repost_tableitem;
	
	
	##Clears read posts
	$options = get_option("repost");
	if(!get_option("repost_lastClear")){
		$force = true;
		$now = date("Y-m-d");
		update_option("repost_lastClear",$now);
	}
	$lastClear = strtotime(get_option("repost_lastClear"));
	$oneDay = 60*60*24;
	$diff = (time() - $lastClear)/$oneDay;
	
	if($force || $diff >= $options["clearCache"]){
		
		mysql_query("DELETE FROM $repost_tableitem WHERE old = 1");
		$now = date("Y-m-d");
		update_option("repost_lastClear",$now);		
	}
	
	##Clear all posts after they are too old
	$isOld = $options["clearCache_old"];
	if ($isOld){
		$days = $isOld*60*60*24;
		$days = time() - $days;
		$days = date("Y-m-d",$days);	
		mysql_query("DELETE FROM $repost_tableitem WHERE date(timestamp) < '$days'");
	}
	
	
	if($response){
		$objResponse = new xajaxResponse();
		return $objResponse;	
	}

}


function repost_write_page(){

	repost_updateBlogSources();
	repost_clearCache();
	repost_republish_interface();	
	
}


register_activation_hook( __FILE__, 'repost_tables' );

add_action('init','repost_xajax');
add_action('admin_menu', 'repost_add_menu');


?>
