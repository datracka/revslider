<?php
 
 class UniteBaseAdminClassRev extends UniteBaseClassRev{
 	
		const ACTION_ADMIN_MENU = "admin_menu";
		const ACTION_ADMIN_INIT = "admin_init";	
		const ACTION_ADD_SCRIPTS = "admin_enqueue_scripts";
		const ACTION_ADD_METABOXES = "add_meta_boxes";
		const ACTION_SAVE_POST = "save_post";
		
		const ROLE_ADMIN = "admin";
		const ROLE_EDITOR = "editor";
		const ROLE_AUTHOR = "author";
		
		protected static $master_view;
		protected static $view;
		
		private static $arrSettings = array();
		private static $arrMenuPages = array();
		private static $tempVars = array();
		private static $startupError = "";
		private static $menuRole = self::ROLE_ADMIN;
		private static $arrMetaBoxes = array();		//option boxes that will be added to post
		
		private static $allowed_views = array('master-view', 'system/validation', 'system/video_dialog', 'system/update_dialog', 'system/general_settings_dialog', 'sliders', 'slider', 'slider_template', 'slides', 'slide', 'navigation-editor', 'slide-editor', 'slide-overview', 'slide-editor', 'slider-overview', 'themepunch-google-fonts');
		
		/**
		 * 
		 * main constructor		 
		 */
		public function __construct($mainFile,$t,$defaultView){
			
			parent::__construct($mainFile,$t);
			
			//set view
			self::$view = self::getGetVar("view");
			if(empty(self::$view))
				self::$view = $defaultView;
				
			//add internal hook for adding a menu in arrMenus
			self::addAction(self::ACTION_ADMIN_MENU, "addAdminMenu");
			self::addAction(self::ACTION_ADD_METABOXES, "onAddMetaboxes");
			self::addAction(self::ACTION_SAVE_POST, "onSavePost");
			
			//if not inside plugin don't continue
			if($this->isInsidePlugin() == true){
				self::addAction(self::ACTION_ADD_SCRIPTS, "addCommonScripts");
				self::addAction(self::ACTION_ADD_SCRIPTS, "onAddScripts");
			}
			
			//a must event for any admin. call onActivate function.
			$this->addEvent_onActivate();
			$this->addAction_onActivate();
			
			self::addActionAjax("show_image", "onShowImage");
			
			
		}		
		
		/**
		 * 
		 * add some meta box
		 * return metabox handle
		 */
		public static function addMetaBox($title,$content = null, $customDrawFunction = null,$location="post"){
			
			$box = array();
			$box["title"] = $title;
			$box["location"] = $location;
			$box["content"] = $content;
			$box["draw_function"] = $customDrawFunction;
			
			self::$arrMetaBoxes[] = $box;			
		}
		
		
		/**
		 * 
		 * on add metaboxes
		 */
		public static function onAddMetaboxes(){
			
			foreach(self::$arrMetaBoxes as $index=>$box){
				
				$title = $box["title"];
				$location = $box["location"];
				
				$boxID = "mymetabox_".self::$dir_plugin.'_'.$index;
				$function = array(self::$t, "onAddMetaBoxContent");
				
				if(is_array($location)){
					foreach($location as $loc)
						add_meta_box($boxID,$title,$function,$loc,'normal','default');
				}else
			    	add_meta_box($boxID,$title,$function,$location,'normal','default');
			}
		}
		
		/**
		 * 
		 * on save post meta. Update metaboxes data from post, add it to the post meta 
		 */
		public static function onSavePost(){
			
			//protection against autosave
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ){
				$postID = UniteFunctionsRev::getPostVariable("ID");
	        	return $postID;
			}
			
			$postID = UniteFunctionsRev::getPostVariable("ID");
			if(empty($postID))
				return(false);
				
				
			foreach(self::$arrMetaBoxes as $box){
				$content = UniteFunctionsRev::getVal($box, "content");
				if(gettype($content) != "object")
					continue;
					
				$arrSettingNames = $content->getArrSettingNames();
				foreach($arrSettingNames as $name){
					$value = UniteFunctionsRev::getPostVariable($name);
					update_post_meta( $postID, $name, $value );
				}	//end foreach settings

			} //end foreach meta
			
		}
		
		/**
		 * 
		 * on add metabox content
		 */
		public static function onAddMetaBoxContent($post,$boxData){
			
			$postID = $post->ID;
			
			$boxID = UniteFunctionsRev::getVal($boxData, "id");
			$index = str_replace("mymetabox_".self::$dir_plugin.'_',"",$boxID);
			
			$arrMetabox = self::$arrMetaBoxes[$index];
			
			$content = UniteFunctionsRev::getVal($arrMetabox, "content");
			
			$contentType = getType($content);
			switch ($contentType){
				case "string":
					echo $content;
				break;
				default:		//settings object					
					$output = new UniteSettingsProductSidebarRev();
					$output->setDefaultInputClass(UniteSettingsProductSidebarRev::INPUT_CLASS_LONG);					
					$content->updateValuesFromPostMeta($postID);										
					$output->init($content);

					//draw element
					$drawFunction = UniteFunctionsRev::getVal($arrMetabox, "draw_function");
					if(!empty($drawFunction))
						call_user_func($drawFunction,$output);
					else
						$output->draw();
						
				break;
			}
			
		}
		
		
		
		/**
		 * 
		 * set the menu role - for viewing menus
		 */
		public static function setMenuRole($menuRole){
			self::$menuRole = $menuRole;
		}
		
		/**
		 * 
		 * set startup error to be shown in master view
		 */
		public static function setStartupError($errorMessage){
			self::$startupError = $errorMessage;
		}
		
		
		/**
		 * 
		 * tells if the the current plugin opened is this plugin or not 
		 * in the admin side.
		 */
		private function isInsidePlugin(){
			$page = self::getGetVar("page");
			if($page == self::$dir_plugin || $page == 'themepunch-google-fonts')
				return(true);
			return(false);
		} 
		
		
		/**
		 * 
		 * add common used scripts
		 */
		public static function addCommonScripts(){

            $prefix = (is_ssl()) ? 'https://' : 'http://';
                
			//include jquery ui
			if(GlobalsRevSlider::$isNewVersion){	//load new jquery ui library
				$urlJqueryUI = $prefix."ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/jquery-ui.min.js";
				self::addScriptAbsoluteUrl($urlJqueryUI,"jquery-ui");
				
				wp_enqueue_style('jui-smoothness', esc_url_raw($prefix.'ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/jquery-ui.css'), array(), null);
				
				if(function_exists("wp_enqueue_media"))
					wp_enqueue_media();
				
			}else{	//load old jquery ui library
				
				$urlJqueryUI = $prefix."ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js";
				self::addScriptAbsoluteUrl($urlJqueryUI,"jquery-ui");
				wp_enqueue_style('jui-smoothness', esc_url_raw($prefix.'ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/base/jquery-ui.css'), array(), null);
				
			}
			
			
			
			self::addScriptCommon("settings","unite_settings");
			self::addScriptCommon("admin","unite_admin");
			self::addScriptCommon("jquery.tipsy","tipsy");
			
			//--- add styles
			
			self::addStyleCommon("admin","unite_admin");
			
			//add tipsy
			self::addStyleCommon("tipsy","tipsy");
			
			//include farbtastic
			self::addScriptCommon("my-farbtastic","my-farbtastic","js/farbtastic");
			self::addStyleCommon("farbtastic","farbtastic","js/farbtastic");
			
			//include codemirror
			self::addScriptCommon("codemirror","codemirror_js","js/codemirror");
			self::addScriptCommon("css","codemirror_js_css","js/codemirror");
			self::addStyleCommon("codemirror","codemirror_css","js/codemirror");
			
			//include dropdown checklist
			self::addScriptCommon("ui.dropdownchecklist-1.4-min","dropdownchecklist_js","js/dropdownchecklist");
			//self::addScriptCommon("ui.dropdownchecklist","dropdownchecklist_js","js/dropdownchecklist");
			//self::addStyleCommon("ui.dropdownchecklist.standalone","dropdownchecklist_css","js/dropdownchecklist");
						
		}
		
		
		
		/**
		 * 
		 * admin pages parent, includes all the admin files by default
		 */
		public static function adminPages(){
			//self::validateAdminPermissions();
		}
		
		
		/**
		 * 
		 * validate permission that the user is admin, and can manage options.
		 */
		protected static function isAdminPermissions(){
			
			if( is_admin() &&  current_user_can("manage_options") )
				return(true);
				
			return(false);
		}
		
		/**
		 * 
		 * validate admin permissions, if no pemissions - exit
		 */
		protected static function validateAdminPermissions(){
			if(!self::isAdminPermissions()){
				echo "access denied";
				return(false);
			}			
		}
		
		/**
		 * 
		 * set view that will be the master
		 */
		protected static function setMasterView($masterView){
			self::$master_view = $masterView;
		}
		
		/**
		 * 
		 * inlcude some view file
		 */
		protected static function requireView($view){
			try{
				
				//require master view file, and 
				if(!empty(self::$master_view) && !isset(self::$tempVars["is_masterView"]) ){
					$masterViewFilepath = self::$path_views.self::$master_view.".php";
					UniteFunctionsRev::validateFilepath($masterViewFilepath,"Master View");
					
					self::$tempVars["is_masterView"] = true;
					require $masterViewFilepath;
				}else{		//simple require the view file.
					
					if(!in_array($view, self::$allowed_views)) UniteFunctionsRev::throwError(__('Wrong Request', REVSLIDER_TEXTDOMAIN));
				
					$viewFilepath = self::$path_views.$view.".php";
					
					UniteFunctionsRev::validateFilepath($viewFilepath,"View");
					require $viewFilepath;
				}
				
			}catch (Exception $e){
				echo "<br><br>View (".esc_attr($view).") Error: <b>".$e->getMessage()."</b>";
				
				if(self::$debugMode == true)
					dmp($e->getTraceAsString());
			}
		}
		
		/**
		 * require some template from "templates" folder
		 */
		protected static function getPathTemplate($templateName){
			
			$pathTemplate = self::$path_templates.$templateName.".php";
			UniteFunctionsRev::validateFilepath($pathTemplate,"Template");
			
			return($pathTemplate);
		}
		
		/**
		 * 
		 * require settings file, the filename without .php
		 */
		public static function requireSettings($settingsFile){
			
			try{
				require self::$path_plugin."settings/$settingsFile.php";
			}catch (Exception $e){
				echo "<br><br>Settings ($settingsFile) Error: <b>".$e->getMessage()."</b>";
				dmp($e->getTraceAsString());
			}
		}
		
		/**
		 * 
		 * get path to settings file
		 * @param $settingsFile
		 */
		protected static function getSettingsFilePath($settingsFile){
			
			$filepath = self::$path_plugin."settings/$settingsFile.php";
			return($filepath);
		}
		
		
		/**
		 * 
		 * add all js and css needed for media upload
		 */
		protected static function addMediaUploadIncludes(){
			
			self::addWPScript("thickbox");
			self::addWPStyle("thickbox");
			self::addWPScript("media-upload");
			
		}
		
		
		/**
		 * add admin menus from the list.
		 */
		public static function addAdminMenu(){
			global $revslider_screens;
			
			$role = "manage_options";
			
			switch(self::$menuRole){
				case self::ROLE_AUTHOR:
					$role = "edit_published_posts";
				break;
				case self::ROLE_EDITOR:
					$role = "edit_pages";
				break;		
				default:		
				case self::ROLE_ADMIN:
					$role = "manage_options";
				break;
			}
			
			foreach(self::$arrMenuPages as $menu){
				$title = $menu["title"];
				$pageFunctionName = $menu["pageFunction"];
				$revslider_screens[] = add_menu_page( $title, $title, $role, self::$dir_plugin, array(self::$t, $pageFunctionName), 'dashicons-update' );
			}
			
			if(!isset($GLOBALS['admin_page_hooks']['themepunch-google-fonts'])){ //only add if menu is not already registered
				$revslider_screens[] = add_menu_page(__('Punch Fonts', REVSLIDER_TEXTDOMAIN), __('Punch Fonts', REVSLIDER_TEXTDOMAIN), $role, 'themepunch-google-fonts', array(self::$t, 'display_plugin_submenu_page_google_fonts'), 'dashicons-editor-textcolor');
			}
		}
		
		
		/**
		 * 
		 * add menu page
		 */
		protected static function addMenuPage($title,$pageFunctionName){
			
			self::$arrMenuPages[] = array("title"=>$title,"pageFunction"=>$pageFunctionName);
			
		}

		/**
		 * 
		 * get url to some view.
		 */
		public static function getViewUrl($viewName,$urlParams=""){
			$params = "&view=".$viewName;
			if(!empty($urlParams))
				$params .= "&".$urlParams;
			
			$link = admin_url( "admin.php?page=".self::$dir_plugin.$params);
			return($link);
		}
		
		/**
		 * 
		 * register the "onActivate" event
		 */
		protected function addEvent_onActivate($eventFunc = "onActivate"){
			register_activation_hook( self::$mainFile, array(self::$t, $eventFunc) );
		}
		
		
		protected function addAction_onActivate(){
			register_activation_hook( self::$mainFile, array(self::$t, 'onActivateHook') );
		}
		
		
		public static function onActivateHook(){
			
			$options = array();
			
			$options = apply_filters('revslider_mod_activation_option', $options);
			
			$operations = new RevOperations();
			$operations->updateGeneralSettings($options);
			
		}
		
		/**
		 * 
		 * store settings in the object
		 */
		protected static function storeSettings($key,$settings){
			self::$arrSettings[$key] = $settings;
		}
		
		/**
		 * 
		 * get settings object
		 */
		protected static function getSettings($key){
			if(!isset(self::$arrSettings[$key]))
				UniteFunctionsRev::throwError("Settings $key not found");
			$settings = self::$arrSettings[$key];
			return($settings);
		}
		
		
		/**
		 * 
		 * add ajax back end callback, on some action to some function.
		 */
		protected static function addActionAjax($ajaxAction,$eventFunction){
			self::addAction('wp_ajax_'.self::$dir_plugin."_".$ajaxAction, $eventFunction);
			self::addAction('wp_ajax_nopriv_'.self::$dir_plugin."_".$ajaxAction, $eventFunction);
		}
		
		/**
		 * 
		 * echo json ajax response
		 */
		private static function ajaxResponse($success,$message,$arrData = null){
			
			$response = array();			
			$response["success"] = $success;				
			$response["message"] = $message;
			
			if(!empty($arrData)){
				
				if(gettype($arrData) == "string")
					$arrData = array("data"=>$arrData);				
				
				$response = array_merge($response,$arrData);
			}
				
			$json = json_encode($response);
			
			echo $json;
			exit();
		}

		/**
		 * 
		 * echo json ajax response, without message, only data
		 */
		protected static function ajaxResponseData($arrData){
			if(gettype($arrData) == "string")
				$arrData = array("data"=>$arrData);
			
			self::ajaxResponse(true,"",$arrData);
		}
		
		/**
		 * 
		 * echo json ajax response
		 */
		protected static function ajaxResponseError($message,$arrData = null){
			
			self::ajaxResponse(false,$message,$arrData,true);
		}
		
		/**
		 * echo ajax success response
		 */
		protected static function ajaxResponseSuccess($message,$arrData = null){
			
			self::ajaxResponse(true,$message,$arrData,true);
			
		}
		
		/**
		 * echo ajax success response
		 */
		protected static function ajaxResponseSuccessRedirect($message,$url){
			$arrData = array("is_redirect"=>true,"redirect_url"=>$url);
			
			self::ajaxResponse(true,$message,$arrData,true);
		}
		
 }
 
 ?>
