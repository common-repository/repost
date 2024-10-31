<?php 

function repost_add_interface_css(){
	
	echo "<link rel='stylesheet' href='". get_option('siteurl') ."/wp-content/plugins/repost/repost.css' type='text/css' />";
	echo "<script type='text/javascript' src='". get_option('siteurl') ."/wp-content/plugins/repost/repost.js'></script>
";
}

add_action('admin_head','repost_add_interface_css');

$repost_item_template = '

	<div class="repost_item #item_class#" id="repost_item_#item_id#">

		<div class="repost_item_actions">
			<span id="repost_item_markRead_#item_id#">#markRead_link#</span>
			 | 
			<span id="repost_item_republish_#item_id#">#republish_link#</span>
		</div>
		
		<span class="repost_item_source">#source_name#</span>
		<div id="repost_sensitive_area_#item_d#"  onclick="repost_doAction(\'showSingle\',#item_id#)">
			<h3>
			<b>
			#item_title#
			</b>
			</h3>
			<span class="repost_item_meta">#item_date#</span>

			<div class="repost_item_content">
			#item_content#
			</div>
		</div>


	</div>';
				
			
				
function repost_write_items($sql,$breakContent = true){
	
	global $repost_item_template,$wpdb,$repost_tablesrc,$repost_tableitem;
	
	$items = $wpdb->get_results($sql);
	
	foreach($items as $item){
	
		$op = $repost_item_template;
		
		$content = ($breakContent) ? wp_html_excerpt($item->content,300) : $item->content;
		$op = str_replace("#item_content#",	$content, $op);
		
		$class = ($item->old) ? "repost_read" : "";
		$op = str_replace("#item_class#",	$class, $op);
		
		if($item->published){
			$republished = '<b>Republished</b>';
			$markRead = '';
		}else{
			$republished = '<a href="javascript:if(confirm(\''.__("Are you sure you want to republish this post?","repost").'\')) repost_doAction(\'republish\',#item_id#);">'.__("Republish","repost").'</a>';
			if($item->old){
				$markRead = '<a href="javascript:repost_doAction(\'markUnread\',#item_id#)">'.__("Mark as Unread","repost").'</a>';
			}else{
				$markRead = '<a href="javascript:repost_doAction(\'markRead\',#item_id#)">'.__("Mark as Read","repost").'</a>';
			}
		}
		$op = str_replace("#markRead_link#",	$markRead, $op);
		$op = str_replace("#republish_link#",	$republished, $op);
		
		$op = str_replace("#item_id#",		$item->id, $op);
		$op = str_replace("#source_name#",	$item->source_name, $op);
		$op = str_replace("#item_title#",	$item->title, $op);
		$op = str_replace("#item_date#",	$item->modified, $op);
			
		$output.=$op;		
	}
	return $output;	
}



function repost_refresh_view($src){
	global $wpdb,$repost_tablesrc,$repost_tableitem;
	if ($src==0){
		$sql = "SELECT $repost_tableitem.*,$repost_tablesrc.title as source_name FROM $repost_tableitem INNER JOIN $repost_tablesrc ON $repost_tableitem.source_id = $repost_tablesrc.id ORDER BY old ASC";
	}else{
		$sql = "SELECT $repost_tableitem.*,$repost_tablesrc.title as source_name FROM $repost_tableitem INNER JOIN $repost_tablesrc ON $repost_tableitem.source_id = $repost_tablesrc.id WHERE $repost_tableitem.source_id = $src ORDER BY old ASC";
	}
	$output = repost_write_items($sql);
	
	$objResponse = new xajaxResponse();
	$objResponse->addAssign("repost_List","style.display","block");
	$objResponse->addAssign("repost_singlePost","style.display","none");
	$objResponse->addAssign("repost_List","innerHTML",$output);
	return $objResponse;

}

function repost_showSingle($src){
	global $wpdb,$repost_tablesrc,$repost_tableitem;
	
	repost_markRead($src);
	
	$sql = "SELECT $repost_tableitem.*,$repost_tablesrc.title as source_name FROM $repost_tableitem INNER JOIN $repost_tablesrc ON $repost_tableitem.source_id = $repost_tablesrc.id WHERE $repost_tableitem.id = $src";
	
	$output = repost_write_items($sql,false);
	
	$objResponse = new xajaxResponse();
	$objResponse->addAssign("repost_singlePost","style.display","block");
	$objResponse->addAssign("repost_List","style.display","none");
	$objResponse->addAssign("repost_singlePost","innerHTML",$output);
	return $objResponse;

}

function repost_markRead($item_id,$markUnread = false){
	global $repost_tablesrc,$repost_tableitem;
	$value = ($markUnread) ? 0 : 1;
	
	$sql = "UPDATE $repost_tableitem SET old = '$value' WHERE id = $item_id";
	mysql_query($sql);
	$objResponse = new xajaxResponse();
	
	if($markUnread){
		$objResponse->addAssign("repost_item_$item_id","className"," repost_item");
		$objResponse->addAssign("repost_item_markRead_$item_id","innerHTML",'<a href="javascript:repost_doAction(\'markRead\','.$item_id.')">'.__("Mark as Read","repost").'</a>');
		
	}else{
		$objResponse->addAppend("repost_item_$item_id","className"," repost_read");
		$objResponse->addAssign("repost_item_markRead_$item_id","innerHTML",'<a href="javascript:repost_doAction(\'markUnread\','.$item_id.')">'.__("Mark as Unread","repost").'</a>');
	}
	return $objResponse;
	

}

function repost_republish($item_id){
	global $wpdb,$repost_tablesrc,$repost_tableitem, $user_id;
	
	$the_post = $wpdb->get_row("SELECT $repost_tableitem.*,$repost_tablesrc.title as source_name FROM $repost_tableitem INNER JOIN $repost_tablesrc ON $repost_tableitem.source_id = $repost_tablesrc.id WHERE $repost_tableitem.id = $item_id");
	$options = get_option("repost");
	
	if ($options["top_comment"]) {
		
		$top_comment = $options["top_comment"];
		$top_comment = str_replace("#LINK#","<a href='".$the_post->link."'>".$the_post->source_name."</a>",$top_comment);
		$top_comment = str_replace("#DATE#",$the_post->modified,$top_comment);
		
		$content = "<span class='postmetadata'>".$top_comment."</span><BR><BR>";
		
	}
	
	$content .= $the_post->content;
	$author = ($the_post->author) ? $the_post->author : $user_id;
	
	$result = array('post_status' => 'publish', 'post_type' => 'post', 'post_author' => $author,
	'post_content' => $content, 'post_title'=>$the_post->title);
	
	$POSTID = wp_insert_post($result);
	$objResponse = new xajaxResponse();
	if($POSTID){
		
		mysql_query("UPDATE $repost_tableitem SET published = 1 WHERE id = $item_id");
		repost_markRead($item_id);
		$objResponse->addAssign("repost_item_markRead_$item_id","style.display","none");
		$objResponse->addAssign("repost_item_republish_$item_id","innerHTML","<b>".__("Republished","repost")."</b>");
		
		
	}
	return $objResponse;
}


function repost_republish_interface(){
	global $wpdb,$repost_tablesrc,$repost_tableitem;

	?>
	<div class="wrap">
		<div id="repost_leftMenu">
			<h2><?php _e("Sources","repost"); ?></h2>
			<ul>
				<li><a href="javascript:repost_doAction('refresh_view',0)"><?php _e("All sources","repost"); ?></a></li>
				<?php 
				$srcs = $wpdb->get_results("SELECT * FROM $repost_tablesrc ORDER BY type");
				foreach($srcs as $src){
					?>				
					<li><a href="javascript:repost_doAction('refresh_view',<?php echo $src->id; ?>)"><?php echo $src->title; ?></a></li>
					<?php				
				}			
				?>
			</ul>
			<a href="edit.php?page=repost.php"><?php _e("Manage sources","repost"); ?></a><BR>
			<a href="javascript:repost_doAction('clearCache',0)"><?php _e("Clear read posts now","repost"); ?></a>
		</div>
		<div id="repost_container">
			<div id="repost_singlePost"></div>
			<div id="repost_List">
				<script>repost_doAction("refresh_view",0);</script>
			</div>
		</div>
	</div>
	<?php
	
}
?>
