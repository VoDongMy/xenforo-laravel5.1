(function(taigachat, $, XenForo, undefined){
		
	taigachat.customColor = "";
	
	var initialFired = false;
	var reverse = false;
	var initialTime = 0;
	var lastRefresh = 0;
	var lastRefreshServer = 0;
	var lastMessage = 0;
	var nextRefresh = 0;
	var isRefreshing = false;
	var tabUnfocused = false;
	var lastScroll = 0;
	var lastPostTime = 0;
	var lastPostMessage = "";
	var scrolled = false;
	var boxHeight = 0;
	var lastUpdates = [];

	XenForo.taigachat_PopupMenu = XenForo.PopupMenu;
	XenForo.taigachat_PopupMenu.setMenuPosition = function(caller){
		var controlLayout, // control coordinates
			menuLayout, // menu coordinates
			contentLayout, // #content coordinates
			$content,
			$window,
			proposedLeft,
			proposedTop;

		controlLayout = this.$control.coords('outer');

		this.$control.removeClass('BottomControl');

		this.$menu.removeClass('BottomControl').css(
		{
			left: controlLayout.left,
			top: controlLayout.top + controlLayout.height
		});

		menuLayout = this.$menu.coords('outer');

		$content = $('#content .pageContent');
		if ($content.length)
		{
			contentLayout = $content.coords('outer');
		}
		else
		{
			contentLayout = $('body').coords('outer');
		}

		$window = $(window);
		$window.sT = $window.scrollTop();
		$window.sL = $window.scrollLeft();

		
		if (menuLayout.left + menuLayout.width > contentLayout.left + contentLayout.width)
		{
			proposedLeft = controlLayout.left + controlLayout.width - menuLayout.width;
			if (proposedLeft > $window.sL || XenForo._isWebkitMobile)
			{
				this.$menu.css('left', proposedLeft);
			}
		}

		proposedTop = controlLayout.top - menuLayout.height-500;
		this.$control.addClass('BottomControl');
		this.$menu.addClass('BottomControl');
		this.$menu.css('top', proposedTop);
	};

	function fixBalloonCounter(){
		// workaround lack of support for doing nothing to alert counts
		XenForo.balloonCounterUpdate = function($balloon, newTotal)
		{
			if(newTotal == 'IGNORE')
				return;
				
			XenForo.balloonCounterUpdateOriginal($balloon, newTotal);
		};
	}
	eval("XenForo.balloonCounterUpdateOriginal = " + XenForo.balloonCounterUpdate.toString());
	fixBalloonCounter();

	function handleVisibilityChange() {
		tabUnfocused = !!document[taigachat.hidden];
	}
	
	function updateActivity(){
		XenForo.ajax(
			taigachat.url_activity, 
			{
			}, 
			function(json){
				if(XenForo.hasResponseError(json) !== false){				
					return true;
				}
			},
			{
				cache: false,
				global: false
			}
		);
	}	
	

	$(document).ready(function(){
		
		if(taigachat.room != 1){
			taigachat.speed = false;
		}

		// workaround xenporta recentthreadsx5 bug
		fixBalloonCounter();
		
		if (typeof document.hidden !== "undefined") {
			taigachat.hidden = "hidden";
			taigachat.visibilityChange = "visibilitychange";
		} else if (typeof document.mozHidden !== "undefined") {
			taigachat.hidden = "mozHidden";
			taigachat.visibilityChange = "mozvisibilitychange";
		} else if (typeof document.msHidden !== "undefined") {
			taigachat.hidden = "msHidden";
			taigachat.visibilityChange = "msvisibilitychange";
		} else if (typeof document.webkitHidden !== "undefined") {
			taigachat.hidden = "webkitHidden";
			taigachat.visibilityChange = "webkitvisibilitychange";
		}
		
		boxHeight = $("#taigachat_box").height();
			
		if(typeof document.addEventListener !== "undefined" && typeof taigachat.hidden !== "undefined"){
			document.addEventListener(taigachat.visibilityChange, handleVisibilityChange, false);
		}

		$(document).bind(
		{
			XenForoWindowFocus: taigachat.focus
		});
		
		$(document).mousemove(function(){
			$.flashTitle(false);
		});
		
		$("#taigachat_message").keypress(function (e) {
			if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
				sendShout();
				return false;
			}
			return true;
		});
				
		$("#taigachat_send").click(sendShout);
		
		$(".taigachat_motd, #taigachat_full .categoryStrip, #taigachat_sidebar h3").dblclick(function(e){
			$("a.OverlayTrigger[href*='taigachat/motd']").trigger('click');
			e.preventDefault();
			e.stopPropagation();
			return false;
		});
		
		lastScroll = new Date().getTime();
		$("#taigachat_box").scroll(function(){
			lastScroll = new Date().getTime();		
		});
		
		refreshShoutbox(true, true, false);
		
		$("#taigachat_send, #taigachat_message").removeAttr('disabled').removeClass('disabled');
		
		$("#taigachat_message").focus(function(e){
			if($("#taigachat_toolbar:visible").length == 0){
				$("#taigachat_toolbar").slideDown(500);
			}
		});
		
		$("#taigachat_controls").after("<div id='taigachat_temp' style='display:none'></div>");
		
		$(".taigachat_messagetext").live('dblclick', function(e){
			if((taigachat.canModify && $(this).parents("li").data("userid") == XenForo.visitor.user_id) || taigachat.canModifyAll){
				$("#taigachat_edit_"+$(this).parents("li").data("messageid")).click();
			}
			e.preventDefault();
			e.stopPropagation();
			return false;
		});
		
		$(".taigachat_delete").live('click', function(e){
			e.stopPropagation();
			e.preventDefault();
			
			var $link = $(this);
			
			XenForo.ajax(
				$(this).attr("href"), 
				{
				}, 
				function(json){
					$("#taigachat_message_"+$link.data("messageid")).fadeOut(500);	
				},
				{
					cache: false
				}
			);	
		
			return false;
		});
		
		$("#taigachat_smiliepicker").click(function(){
			var self = this;
			var $smilies = $("#taigachat_smilies_box");

			if ($smilies.children().length){
				$smilies.slideToggle();
				return;
			}

			if (self.smiliesPending)
				return;
			
			self.smiliesPending = true;

			XenForo.ajax(
				'index.php?editor/smilies',
				{},
				function(ajaxData) {
					if (XenForo.hasResponseError(ajaxData)){
						return;
					}

					if (ajaxData.templateHtml){
						$smilies.html(ajaxData.templateHtml);
						$smilies.hide();
						$smilies.on('click', '.Smilie', function(e) {
							e.preventDefault();
							e.stopPropagation();
							if($("#taigachat_message").val() == $("#taigachat_message").attr("placeholder")){
								$("#taigachat_message").removeClass("prompt").val("");
							}
							$("#taigachat_message").insertAroundCaret(" " + $(this).children("img").attr("alt") + " ", "");
							return true;
						});
						$smilies.xfActivate();
						$smilies.slideToggle();
					}
				}
			).complete(function() {
				self.smiliesPending = false;
			});	
		});
				
		$(".taigachat_ban").live('click', function(e){
			XenForo.alert(XenForo.phrases.dark_banned_successfully, '', 2000);
			var href = $(this).data('link');
			setTimeout(function(){
				window.location = href;
			}, 1800);
			e.preventDefault();
			return false;
		});
		
		$(".taigachat_bbcode").live('click', function(e){
			
			var bbcode = $(this).attr("data-code");
			if(!bbcode)
				return true;
			e.stopPropagation();
			if($("#taigachat_message").val() == $("#taigachat_message").attr("placeholder")){
				$("#taigachat_message").removeClass("prompt").val("");
			}
			var position = bbcode.length;
			var ins = getCaretLength($("#taigachat_message").get(0)) > 0;
			$("#taigachat_message").insertAroundCaret(bbcode.substring(0, bbcode.indexOf('][')+1), bbcode.substring(bbcode.indexOf('][')+1, bbcode.length));
			if(bbcode.indexOf('=][') != -1){
				position = bbcode.indexOf('=][')+1;
			} else {			
				position = bbcode.indexOf('][')+1;
			}
			
			if(!ins)
				setCaretPosition($("#taigachat_message").get(0), getCaretPosition($("#taigachat_message").get(0)) - (bbcode.length - position));
			else		
				setCaretPosition($("#taigachat_message").get(0), getCaretPosition($("#taigachat_message").get(0)) + bbcode.length - position);
			return true;		
		});
		
		if(taigachat.speed && !taigachat.fake){
			setInterval(updateActivity, taigachat.fastactivity ? 45000 : 145000);
			if(taigachat.fastactivity)
				updateActivity();
		}
		
		setTimeout(function(){
			if(XenForo._isWebkitMobile && reverse){
				setInterval(function(){
					scrollChatBottom(false);
				}, 100);
			}
		}, 2000);
		
		if(taigachat.customColor.length == 6)
			$("#taigachat_message").css({color: "#"+taigachat.customColor});
			
		if($(".taigachat_popup_body, .dark_taigachat_full").length > 0)
			$("#taigachat_message").focus();

	});


	function sendShout(){
		
		// silently prevent same message within 5 seconds
		if(lastPostTime + 5000 > new Date().getTime() && lastPostMessage == $("#taigachat_message").val())
			 return;    
			 
		if($("#taigachat_message").val().length == 0 || $("#taigachat_message").val() == $("#taigachat_message").attr("placeholder")) 
			return;
			
		$("#taigachat_send, #taigachat_message").attr('disabled', true).addClass('disabled');		 
			 
		lastPostMessage = $("#taigachat_message").val();
		lastPostTime = new Date().getTime();
		
		XenForo.ajax(
			taigachat.url_post, 
			{
				message: $("#taigachat_message").val(),
				sidebar: taigachat.sidebar ? "1" : "0",
				lastrefresh: lastRefreshServer,
				color: taigachat.customColor,
				room: taigachat.room
			}, 
			function(json){
				
				if(XenForo.hasResponseError(json) !== false){	
					
					$("#taigachat_send, #taigachat_message").removeAttr('disabled').removeClass('disabled');
					$("#taigachat_message").blur();
					$("#taigachat_message").focus();
								
					return true;
				}
				
				var prune = false;
				
				if(typeof json.too_fast != "undefined" && json.too_fast){
					XenForo.alert(json.phrase, '', 3000);
				} else {				
					
					if($("#taigachat_message").val() == '/prune')
						prune = true;
					
					if($("#taigachat_message").val().indexOf('/unban') === 0)
						XenForo.alert(XenForo.phrases.dark_unbanned_successfully, '', 3000);
					
					$("#taigachat_message").val("");	
					
					handleListResponse(json, false, true);
				
				}
				
				$("#taigachat_send, #taigachat_message").removeAttr('disabled').removeClass('disabled');
				$("#taigachat_message").blur();
				$("#taigachat_message").focus();
							
				if(prune)
					location.reload();
				
			},
			{
				cache: false,
				error: function(xhr, textStatus, errorThrown){			
					$("#taigachat_send, #taigachat_message").removeAttr('disabled').removeClass('disabled');
					$("#taigachat_message").blur();
					$("#taigachat_message").focus();		
					try
					{
						success.call(null, $.parseJSON(xhr.responseText), textStatus);
					}
					catch (e)
					{
						if(xhr.responseText.substr(0, 1) == '{' && xhr.responseText.substr(-1) == '}')
							XenForo.handleServerError(xhr, textStatus, errorThrown);
						else
							// handle truncation of JSON e.g. due to speed mode on slow server
							$("#taigachat_message").val("");	
					}
				}
			}
		);
	}

	taigachat.focus = function(e){
		nextRefresh = 3000;
		$.flashTitle(false);
	}

	// force = ignore focus event delay and ignore document focus
	// unsync = out-of-sync request, do not restart timer
	function refreshShoutbox(initial, force, unsync){
		
		// Assert initial refresh will only happen once
		if(initial){
			if(initialFired) 
				return;
			initialFired = true;
			initialTime = new Date().getTime();
		} else {
			// Assert we aren't refreshing within 2 seconds of the first refresh - i.e. document focus event
			if(initialTime + 2000 > new Date().getTime() && !force)
				return;
		}
		
		if(initialTime + 50 * 60 * 1000 < new Date().getTime() && !initial){
			// time for a CSRF token refresh...
			XenForo._CsrfRefresh.refresh();
			taigachat.refreshtime = 10;    
			restartTimer();
			initialTime = new Date().getTime();
			return;
		}
		
		isRefreshing = true;
		
		XenForo.ajax(
			taigachat.speed ? taigachat.speedurl : taigachat.url, 
			taigachat.speed ? {} : {
				sidebar: taigachat.sidebar ? "1" : "0",
				lastrefresh: lastRefreshServer,
				fake: taigachat.fake ? "1" : "0",
				room: taigachat.room
			}, 
			function(json, textStatus){			
				isRefreshing = false;
				
				if (XenForo.hasResponseError(json))
				{
					return false;
				}

				handleListResponse(json, initial, unsync);
							
				if(initial){
					setInterval(checkRefresh, 250);
				}			
			},  
			{
				global: false, 
				dataType: 'json',
				cache: false, 
				type: taigachat.speed ? 'get' : 'post',
				error: function(xhr, textStatus, errorThrown){					
					try
					{
						success.call(null, $.parseJSON(xhr.responseText), textStatus);
					}
					catch (e)
					{
						if(initial){
							setInterval(checkRefresh, 250);
						}
						
						if(xhr.responseText.substr(0, 1) == '{' && xhr.responseText.substr(-1) == '}')
							XenForo.handleServerError(xhr, textStatus, errorThrown);						
					}
					finally
					{
						isRefreshing = false;	
					}
				}
			}
		); // ajax

		if(!unsync){
			restartTimer();
		}		
	}

	taigachat.changeColor = function(){	
		var color = $("#color").val();
		
		XenForo.ajax(
			taigachat.url_savecolor, 
			{
				color: color
			}, 
			function(json){
				if(XenForo.hasResponseError(json) !== false){				
					return true;
				}
				taigachat.customColor = json.color;
				$("#taigachat_message").css({color: "#"+json.color});
				XenForo.alert(json.saved, '', 2000);
			},
			{cache: false}
		);
	}


	function handleListResponse(json, initial, unsync){
		
		lastRefreshServer = parseInt(json.lastrefresh, 10) || 0;
					
		if(XenForo.hasTemplateHtml(json) && json.templateHtml.indexOf("<html") !== -1)
			return false;
		
		var gotNew = 0;
		reverse = parseInt(json.reverse, 10) == 1 ? true : false;
		
		if(lastRefreshServer > lastRefresh || unsync){
						
			lastRefresh = lastRefreshServer;
						
			$("#taigachat_motd").html(json.motd);	
			$("#taigachat_count, .navTab.taigachat .Total").html(json.numInChat);
			
			$("#taigachat_online_users_holder").html(json.onlineUsers);
			
			$("#taigachat_box > ol > li").addClass('taigachat_remove');
			for(var i in json.messageIds){
				$("#taigachat_message_"+json.messageIds[i]).removeClass('taigachat_remove');
			}
			$(".taigachat_remove").remove();
			
			for(var i in json.messages){
				var message = json.messages[i];
				if(message.id in lastUpdates && lastUpdates[message.id] >= message.last_update)
					continue;
					
				if(lastUpdates[message.id] < message.last_update){
					$("#taigachat_message_"+message.id).remove();
					
					if(message.previous === 0){
						for(var j = 0; j < json.messageIds.length; j++){
							if(message.id == json.messageIds[j]){
								if(!reverse && j > 0){
									message.previous = json.messageIds[j-1];
								} else if(reverse && j < json.messageIds.length-1){
									message.previous = json.messageIds[j+1];									
								}
								break;			
							}
						}
					}
				}
				
				lastUpdates[message.id] = message.last_update;
							
				gotNew++;
						
				var elementToInsert = $(message.html).attr("style", "visibility:hidden").addClass("taigachat_new");
					
				if(message.previous > 0 && $("#taigachat_message_"+message.previous).length)
					elementToInsert.insertAfter("#taigachat_message_"+message.previous);
				else
					if(reverse)
						elementToInsert.appendTo("#taigachat_box > ol");
					else
						elementToInsert.prependTo("#taigachat_box > ol");
			} 
			
			
			if(initial || gotNew>0){
				
				if(taigachat.newtab)
					$(".taigachat_new a.internalLink").attr("target", "_blank");
				
				if(!taigachat.activity_newtab)
					$(".taigachat_new .taigachat_activity a.internalLink").removeAttr("target");
				else
					$(".taigachat_new .taigachat_activity a.internalLink").attr("target", "_blank");
									
				
				XenForo.activate($('.taigachat_new'));
				
				if(!initial && taigachat.showAlert && $(".dark_taigachat_full").length > 0){
					$.flashTitle(XenForo.phrases.dark_new_chat_message, 2000);
				}		
				
				if(reverse){
					$("li.taigachat_new img").load(function(e){
						if(initial || $(this).height() > 16)
							scrollChatBottom(true);
					});				
				}
			
				for(var id in XenForo._ignoredUsers){
					$(".taigachat_new[data-userid='"+id+"']").each(function(){
						if(taigachat.ignorehide){
							$(this).addClass("taigachat_ignored").addClass("taigachat_ignorehide")
						} else {
							$(this).addClass("taigachat_ignored").children().removeClass("taigachat_me");
							$(this).contents().find(".taigachat_messagetext").html(XenForo.phrases.dark_ignored);
						}
					});
				}
				
				if(taigachat.speed){
					$(".taigachat_new .taigachat_absolute_timestamp").each(function(){
						var serverTime = XenForo.serverTimeInfo.now,
							today = XenForo.serverTimeInfo.today,
							todayDow = XenForo.serverTimeInfo.todayDow;
						var calcDow;
						var yesterday = today - 86400;
						var week = today - 6 * 86400;
						var thisTime = parseInt($(this).data('timestamp'), 10);
						var thisDate = new Date(thisTime * 1000);
						var timeString;
						if(json.twelveHour){							
							var hours = thisDate.getHours();
							var minutes = thisDate.getMinutes();
							var ampm = hours >= 12 ? 'PM' : 'AM';
							hours = hours % 12;
							hours = hours ? hours : 12;
							minutes = minutes < 10 ? '0'+minutes : minutes;
							timeString = hours + ':' + minutes + ' ' + ampm;
						} else {
							timeString = thisDate.toTimeString().replace(/.*(\d{2}:\d{2}):\d{2}.*/, "$1");
						}
						
						if (thisTime > today){
							
							if(taigachat.timedisplay == 'Absolute'){			
								$(this).text(XenForo.phrases.today_at_x.replace(/%time%/, timeString)); 
							} else {			
								$(this).text(timeString);
							}
							
						} else if(thisTime > yesterday){
							
							$(this).text(XenForo.phrases.yesterday_at_x
									.replace(/%time%/, timeString));
									
						} else if(thisTime > week){
							
							calcDow = todayDow - Math.ceil((today - thisTime) / 86400);
							if (calcDow < 0)
							{
								calcDow += 7;
							}

							$(this).text(XenForo.phrases.day_x_at_time_y
								.replace('%day%', XenForo.phrases['day' + calcDow])
								.replace(/%time%/, timeString)
							);
						}
						
						$(this).text($(this).text() + " - ");
						
					});
				}
				
			}		
				
			if(initial || gotNew > 2 || lastMessage + 15000 > new Date().getTime()){			
				$("#taigachat_box > ol > li.taigachat_new").removeClass("taigachat_new").each(showModerationPopups).css({visibility:"visible"}).show();
			} else {                
				$("#taigachat_box > ol > li.taigachat_new").removeClass("taigachat_new").each(showModerationPopups).css({visibility:"visible",display:"none"}).fadeIn(600);                
			}
			
		}
		
		if(initial || gotNew>0){
			
			if(reverse){         
				var total = $("#taigachat_box > ol > li").length;
				total -= taigachat.limit;
				if(total > 0)
					$("#taigachat_box > ol > li").slice(0, total).remove();  
					
			} else {
				$("#taigachat_box > ol > li").slice(taigachat.limit).remove();
			}
			
			if(reverse)
				scrollChatBottom(false);
			
			taigachat.refreshtime = 5;    
			restartTimer();
			
		} else {    
			if(!unsync){
				restartTimer();                        
			}
		}
				
		// don't count initial load against anti fade
		if(gotNew > 0 && !initial){                
			lastMessage = new Date().getTime();
		}
	}

	// jquery context
	function showModerationPopups(){
		if( (taigachat.canModify && XenForo.visitor.user_id == $(this).data('userid')) || taigachat.canModifyAll ){
			$(this).children(".Popup").show();
			
			if(taigachat.canBan && XenForo.visitor.user_id != $(this).data('userid'))
				$("#taigachat_canban_"+$(this).data('messageid')).show();
		}
	}

	function scrollChatBottom(force){			
		if(taigachat.fake)
			return;
		
		if(force || !scrolled || lastScroll < new Date().getTime() - 20000 || $("#taigachat_box").get(0).scrollTop >= $("#taigachat_box").get(0).scrollHeight - boxHeight - 35){
			var scrollHeight = $("#taigachat_box").get(0).scrollHeight;
			if(typeof scrollHeight == "undefined" || scrollHeight < 100)
				scrollHeight = 99999;
			$("#taigachat_box").get(0).scrollTop = scrollHeight;
		}
		scrolled = true;
	}

	function restartTimer(){
		if(XenForo._hasFocus){
			nextRefresh = new Date().getTime() + taigachat.focusedRefreshTime * 1000;
		} else if(tabUnfocused){
			nextRefresh = new Date().getTime() + taigachat.tabUnfocusedRefreshTime * 1000;		
		} else {
			nextRefresh = new Date().getTime() + taigachat.unfocusedRefreshTime * 1000;
		}
	}

	function checkRefresh(){		
		if(nextRefresh < new Date().getTime()){
			if(isRefreshing){
				nextRefresh = new Date().getTime();
				return;
			}			
			refreshShoutbox(false, false, false);			
		}
	}

	// http://stackoverflow.com/questions/946534/insert-text-into-textarea-with-jquery, modified slightly
	jQuery.fn.extend({
		insertAroundCaret: function(myValue, myValue2){
			return this.each(function(i) {
				if(document.selection) {
					this.focus();
					sel = document.selection.createRange();
					sel.text = myValue + sel.text + myValue2;
					this.focus();
				} else if(this.selectionStart || this.selectionStart == '0') {
					var startPos = this.selectionStart;
					var endPos = this.selectionEnd;
					var scrollTop = this.scrollTop;
					this.value = this.value.substring(0, startPos)+myValue+this.value.substring(startPos, endPos)+myValue2+this.value.substring(endPos,this.value.length);
					this.focus();
					this.selectionStart = startPos + myValue.length + myValue2.length + (endPos-startPos);
					this.selectionEnd = startPos + myValue.length + myValue2.length + (endPos-startPos);
					this.scrollTop = scrollTop;
				} else {
					this.value += myValue + myValue2;
					this.focus();
				}
			})
		}
	});

	// http://blog.vishalon.net/index.php/javascript-getting-and-setting-caret-position-in-textarea/
	function getCaretPosition (ctrl) {
		var CaretPos = 0;    // IE Support
		if(document.selection){
			ctrl.focus ();
			var Sel = document.selection.createRange ();
			Sel.moveStart ('character', -ctrl.value.length);
			CaretPos = Sel.text.length;
		}
		// Firefox support
		else if(ctrl.selectionStart || ctrl.selectionStart == '0')
			CaretPos = ctrl.selectionStart;
		return (CaretPos);
	}
	function getCaretLength (ctrl) {
		var CaretPos = 0;
		if(document.selection){
			ctrl.focus ();
			var Sel = document.selection.createRange();
			CaretPos = Sel.text.length;
		}
		else if(ctrl.selectionEnd || ctrl.selectionEnd == '0')
			CaretPos = ctrl.selectionEnd-ctrl.selectionStart;
		return (CaretPos);
	}
	function setCaretPosition(ctrl, pos){
		if(ctrl.setSelectionRange){
			ctrl.focus();
			ctrl.setSelectionRange(pos,pos);
		}
		else if(ctrl.createTextRange){
			var range = ctrl.createTextRange();
			range.collapse(true);
			range.moveEnd('character', pos);
			range.moveStart('character', pos);
			range.select();
		}
	}

	var original  = document.title;
	var newTitle  = document.title;
	var timeoutId = undefined;
	var flashingActive = false;

	var doTheFlash = function(){		
		if(flashingActive && !XenForo._hasFocus)
			document.title = (document.title == original) ? newTitle : original;
		else {
			document.title = original + ".";
			document.title = original + "..";
			document.title = original;
		}
	};	

	$.flashTitle = function(newMsg, interval) {
		if(newMsg == false){			
			document.title = original;
			flashingActive = false;			
		} else {			
			flashingActive = true;
			newTitle = newMsg;
			
			clearInterval(timeoutId);
			timeoutId = setInterval(doTheFlash, interval);				
		}
	}; 
	
	
}(window.taigachat = window.taigachat || {}, jQuery, XenForo));
