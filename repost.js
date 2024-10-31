
function repost_doAction(action,arg){
	
	if(action=="refresh_view"){
		xajax_repost_refresh_view(arg);	
	}	
	if(action=="showSingle"){
		xajax_repost_showSingle(arg);	
	}
	if(action=="markRead"){
		xajax_repost_markRead(arg);	
	}
	if(action=="markUnread"){
		xajax_repost_markRead(arg,false);	
	}
	if(action=="republish"){
		xajax_repost_republish(arg);	
	}
	if(action=="clearCache"){
		xajax_repost_clearCache(true,true);	
		window.location.reload();
	}


}
