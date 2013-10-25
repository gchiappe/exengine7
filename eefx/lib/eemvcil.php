<?php
/**
@file eemvcil.php
@author Giancarlo Chiappe <gch@linkfastsa.com> <gchiappe@gmail.com>
@version 0.0.1.27

@section LICENSE

ExEngine is free software; you can redistribute it and/or modify it under the
terms of the GNU Lesser Gereral Public Licence as published by the Free Software
Foundation; either version 2 of the Licence, or (at your opinion) any later version.
ExEngine is distributed in the hope that it will be usefull, but WITHOUT ANY WARRANTY;
without even the implied warranty of merchantability or fitness for a particular purpose.
See the GNU Lesser General Public Licence for more details.

You should have received a copy of the GNU Lesser General Public Licence along with ExEngine;
if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, Ma 02111-1307 USA.

@section DESCRIPTION

ExEngine 7 / Libs / ExEngine's Model View Controller Implementation Library (eemvcil)

ExEngine MVC Implementation Library

*/

/// Get Instance Function, connects controllers.
function &eemvc_get_instance()
{
	return eemvc_controller::get_instance();
}

function &eemvc_get_index_instance() {
	return eemvc_index::get_instance();
}

class eemvc_index {
	
	const VERSION = "0.0.1.27"; /// Version of EE MVC Implementation library.

	private $ee; /// This is the connector to the main ExEngine object.
	public $controllername; /// Name of the Controller in use.
	public $defcontroller=null;
	
	public $viewsFolder = "views/"; /// Name of the views folder, should be relative to the index file.
	public $modelsFolder = "models/"; /// Name of the models folder, should be relative to the index file.
	public $controllersFolder = "controllers/" ; /// Name of the controllers folder, should be relative to the index file.
	public $staticFolder = "static/"; /// Name of the static folder, should be relative to the index file.
	public $indexname = "index.php"; /// Name of the index file, normally this should not be changed.
	public $SessionMode=false; /// Set to true if you are going to use sessions, remember that "session_start()" does not work with EEMVC.
	public $AlwaysSilent=false; /// Set to true if you do not want to show warnings or slogans to the rendered pages, this is a global variable, you can set silent to a specific controller by setting the $this->imSilent variable to true.
	
	public $dgEnabled = false; /// Enable EE's DevGuard for the project.
	public $dgKey=null; /// EE's DevGuard ServerKey.

	public $BootstrapEnabled = false; /// Enable Twitter's Bootstrap libraries loading.
	public $jQueryEnabled = false; /// Set to true if you want jQuery Enabled.
	public $jQueryUITheme="base"; /// Default EEMVC JQuery UI Theme.
	public $jQueryVersion = null; /// Default EEMVC JQuery JS Lib Version.
	public $jQueryUIVersion = null; /// Default EEMVC JQuery UI JS Lib Version.
	
	public $errorHandler=false; /// Set to the error handler controller name, that controller should be made using the error controller template.
	
	public $urlParsedData; /// Parsed data from the URL string, please do not modify in runtime.
	
	public $staticFolderHTTP; /// HTTP path (URL) to the static folder, made for views rendering.
	public $viewsFolderHTTP;  /// HTTP path (URL) to the views folder, made for views rendering.
	public $modelsFolderHTTP;  /// HTTP path (URL) to the models folder, made for views rendering.
	public $controllersFolderHTTP;  /// HTTP path (URL) to the controllers folder, made for views rendering.
	public $controllersFolderR=null;
	
	public $sameControllerFolderHTTP;
	
	public $actualInputQuery;
	public $unModUrlParsedData;
	
	private $origControllerFolderName;
	
	private static $inst;
	/// Connection static function.
	public static function &get_instance()
	{
		if (self::$inst instanceof eemvc_index)
			return self::$inst; 
		else
			return false;
	}

	/// Default constructor for the index listener.
	final function __construct(&$parent,$defaultcontroller=null) {
		$this->ee = &$parent;		
		$this->debug("MVC Initialized.");			
		if ($defaultcontroller==null) {
			$this->debug("Index: No default controller set.");	
		} else
		$this->defcontroller = $defaultcontroller;
		self::$inst = &$this;
	}
	
	# ExEngine UnitTesting
	var $unitTest = false;
	var $utSuite;
	final function prepareUnitTesting() {
		$this->ee->eeLoad("unittest");
		$eeunit = &eeunit_get_instance();
		if ($eeunit) {
			$this->utSuite = &$eeunit;
			$this->utSuite->write("<b>MVC-ExEngine</b><tab>ExEngine Unit Testing Suite Detected!");
		}
		$this->debug("Unit Testing Mode");
		if(defined('STDIN') && !$this->utSuite) {
			echo 'MVC-ExEngine 7 -> Unit Testing Mode ENABLED'."\n";
		} else {
			$this->utSuite->write("<b>MVC-ExEngine</b><tab>Unit Testing Mode <green>ENABLED</green>");
		}
		$this->unitTest = true;
	}
	
	final function prepareController($Controller) {
		$Controller = strtolower($Controller);
		if (file_exists($this->controllersFolder.$Controller.".php")) {
			if(defined('STDIN') && !$this->utSuite) {
				echo 'MVC-ExEngine 7 -> Preparing controller '.ucfirst($Controller)." for unit testing.\n";
			} else {
				$this->utSuite->write("<b>MVC-ExEngine</b><tab>Preparing controller ".ucfirst($Controller)." for unit testing.");
			}
			include_once($this->controllersFolder.$Controller.".php");
			$Controller = ucfirst($Controller);
			$Controller = new $Controller($this->ee,$this);		
			return $Controller;	
		} else {
			if(defined('STDIN') && !$this->utSuite) {
				echo 'MVC-ExEngine 7 -> Controller '.ucfirst($Controller).' Not Found. (Test Halted)'."\n";
				exit;
			} elseif ($this->utSuite) {
				$this->utSuite->write("<b>MVC-ExEngine</b><tab>Controller ".ucfirst($Controller)." Not Found. (Test Halted)");
				exit;
			}
			else
				$this->ee->errorExit("MVC-ExEngine","Controller ".ucfirst($Controller)." Not Found. (Test Halted)");
		}
	}
	
	final function prepareModel(eemvc_controller $controller, $model) {	
		$controller->loadModel($model,null,false);
		$model = ucfirst($model);
		$modelx = new $model();
		return $modelx;
	}
	#ExEngine UnitTesting
	
	final function loadView($filename,$data=null,$return=false,$dynamic=true,$checkmime=false) {
		$this->specialLoadViewStatic($filename,false,$checkmime,$data,$dynamic);
	}
	
	/// Loads a view for the View Simulator, useful for designers that want to test the basic functionality of their pages.
	final function specialLoadViewStatic($filename,$fullpath=false,$checkmime=false,$data=null,$dynamic=true) {
		
		if ($fullpath) {
			$view_fileo = $filename;
		}
		else
			$view_fileo = $this->viewsFolder.$filename;	
		
		$view_file = $view_fileo;	
		
		if (!file_exists($view_file)) {
			$view_file = $view_fileo.".php";
		}
		
		if (!file_exists($view_file)) {
			$view_file = $view_fileo.".html";
		}

		if (file_exists($view_file)) {
			
			$this->debug("specialLoadViewStatic: Loading: ".$view_file);
			
			if ($checkmime) {
				$this->ee->eeLoad("mime");
				$eemime = new eemime($this->ee);
				$mime_type = $eemime->getMIMEType($view_file);				
				$this->debug("specialLoadViewStatic: File Mime Type: ".$mime_type);
			}

			$data["EEMVC_SF"] = $this->staticFolderHTTP;
			$data["EEMVC_SFTAGGED"] =  $this->controllersFolderHTTP."?EEMVC_SPECIAL=STATICTAGGED&FILE=";
			$data["EEMVC_C"] = $this->controllersFolderHTTP;
			$data["EEMVC_SC"] = $this->controllersFolderHTTP."?EEMVC_SPECIAL=VIEWSIMULATOR&VIEW=".$view_file."&ERROR=NODYNAMIC&";
			$data["EEMVC_SCF"] = $this->controllersFolderHTTP."?EEMVC_SPECIAL=VIEWSIMULATOR&VIEW=".$view_file."&ERROR=NODYNAMIC&";
			$data["EEMVC_SCFOLDER"] = $this->controllersFolderHTTP."?EEMVC_SPECIAL=VIEWSIMULATOR&VIEW=".$view_file."&ERROR=NODYNAMIC&";
			
			$data["EEMVC_VS"] = $this->controllersFolderHTTP."?EEMVC_SPECIAL=VIEWSIMULATOR&VIEW=";
			
			if ($this->jQueryEnabled) {
				$jq = new jquery($this->ee);
				$jqstr = $jq->load($this->jQueryVersion,true);			
				$jqstr2 = $jq->load_ui($this->jQueryUITheme,$this->jQueryUIVersion,true);			
				$jqstr3 = $jq->load_migrate(true);			
			} else {
				$jqstr = '<!-- MVC-EXENGINE: jQuery is not enabled. -->';

			}
			$data["EEMVC_JQUERY"]  = $jqstr; 
			$data["EEMVC_JQUERYUI"]  = $jqstr2; 
			$data["EEMVC_JQUERYMIGRATE"] = $jqstr3;

			extract($data);	
			
			ob_start();		
			
			if ($dynamic) {
				if ((bool) @ini_get('short_open_tag') === FALSE)
				{
					$this->debug("loadView: Mode: ShortTags_Rewriter");
					echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($view_file))));
				}
				else
				{		
					$this->debug("specialLoadViewStatic: Mode: Include");	
					include($view_file);
				}
			}
			else
			{
				$this->debug("specialLoadViewStatic: Mode: ReadFile");
				readfile($view_file);
			}		
			
			$this->debug("specialLoadViewStatic: View loaded: ".$view_file);
			
			$output = ob_get_contents();
			ob_end_clean();		
			
			if ($checkmime)
				header('Content-type: '.$mime_type);
			
			echo $output;			
		} else {
			$this->ee->errorExit("MVC-ExEngine","View (".$view_file.") not found.","eemvcil");
		}	
	}
	
	/// This function will start the MVC listener, should be called in the index file.
	final function start() {	

		if ($this->SessionMode===true) { session_start(); $this->debug("SessionMode=true"); } else {$this->debug("SessionMode=false");}	

		if ($this->dgEnabled) {
			$dg = new ee_devguard();
			$dg->guard($this->dgKey);
		}
		
		if (!$this->ee->argsGet("SilentMode")) {
			print "<h1>MVC-ExEngine can not work with SilentMode argument set to FALSE. Please set it to TRUE.</h1>";
			exit();
		}
		
		$this->setStaticFolder();
		$this->origControllerFolderName = $this->controllersFolder;	

		if (isset($_GET['EEMVC_SPECIAL'])) {
			
			switch ($_GET['EEMVC_SPECIAL']) {
				case 'VIEWSIMULATOR':
				if ($this->ee->cArray["debug"]) {
					if (isset($_GET['ERROR'])) if ($_GET['ERROR'] == "NODYNAMIC") $this->ee->errorExit("EEMVCIL","EEMVC_SPECIAL: EEMVC_SC and EEMVC_SCF special tags does no work in the Views Simulator.",null,true);
					$this->specialLoadViewStatic($_GET['VIEW']);
				} else {
					$this->ee->errorExit("MVC-ExEngine","VIEWSIMULATOR doesn´t work in production mode. (Enable debug mode first)","eemvcil");	
				}
				break;
				case 'STATICTAGGED':
				$file = $this->staticFolder.$_GET['FILE'];
				$this->specialLoadViewStatic($file,true,true);
				break;
				default:
				$this->ee->errorExit("EEMVCIL","EEMVC_SPECIAL: Mode Not Found.");
				break;
			}
			
		} else {	 
			
			if (!$this->ee->strContains($_SERVER['REQUEST_URI'],$this->indexname)) {
				header("Location: ".$_SERVER['REQUEST_URI'].$this->indexname);
				exit();
			}
			
			$this->debug("Index: MVC Started, waiting to controller name, CONTROLLER_NAME/.");
			$this->parseURL();				 
			
			
			if ( ( ( empty($this->urlParsedData) && (substr($_SERVER['REQUEST_URI'],strlen($_SERVER['REQUEST_URI'])-1,1) != "/") )
				||  
				( count($this->urlParsedData) > 0 && end($this->urlParsedData) != null )  )
				&& 
				!$this->ee->strContains($_SERVER['REQUEST_URI'],"?",false) 
				) {
				header("Location: ". $_SERVER['REQUEST_URI']."/" );
			exit();
		} else if (!$this->ee->strContains($_SERVER['REQUEST_URI'],"/?",false) && substr($_SERVER['REQUEST_URI'],strlen($_SERVER['REQUEST_URI'])-1,1) != "/") {
			header("Location: ". str_replace("?","/?",$_SERVER['REQUEST_URI']) );
			exit();
		}		 
		
		$this->debug(print_r($this->urlParsedData,1));		
		
		if (count($this->urlParsedData) > 0 && $this->ee->strContains($this->urlParsedData[count($this->urlParsedData)-1],"?") && !$this->ee->strContains($this->urlParsedData[count($this->urlParsedData)-1],"/?")) {
			$this->debug("Index: Has GET Query and No '/'");
			$ne = explode("?",$this->urlParsedData[count($this->urlParsedData)-1]);
			$this->debug("Index: New Last index (".(count($this->urlParsedData)-1)."): ". $ne[0]);
			$this->urlParsedData[count($this->urlParsedData)-1] = $ne[0];
			$this->debug("Index: New url array: " .print_r($this->urlParsedData,1));	
		}
		
		if (isset($this->urlParsedData[0]) && (!empty($this->urlParsedData[0]))) {
			
			$output = $this->load_controller($this->urlParsedData[0]);
		} else {
			if ($this->defcontroller) {
				$this->debug("Index: Loading default controller: ".$this->defcontroller);	
				$output = $this->load_controller($this->defcontroller);
			}else {
				$this->ee->errorExit("MVC-ExEngine","No default controller set.","eemvcil");
			}
		}	 	 
		
		if (!$this->AlwaysSilent) {
			$rpl = "<head>\n"."\t<!-- ".$this->ee->miscMessages("Slogan",1)." (MVC-ExEngine) -->";
			if ($this->dgEnabled) $rpl .= $dg->guard_float_menu();
			$output = str_replace("<head>",$rpl,$output);			
		}		 
		print $output; 
	}
	
}

/// This function will call the controller, parse variables, session and render, the use of this function is totally automatic.
private final function load_controller($name) {

	ob_start();			
	
	if ($name != null)
		$this->controllername = $name;
	else
		$name = $this->defcontroller;	
	
	$ctl_folder = $this->controllersFolder;

	

	if ($this->controllersFolderR != null) {			
		$this->controllersFolder = $this->controllersFolderR;
	}
	
	
	if (is_dir($this->controllersFolder.$name) && (file_exists($this->controllersFolder.$name."/".$this->urlParsedData[1].".php") || file_exists($this->controllersFolder.$name."/".$this->defcontroller.".php"))) {
		
		if (file_exists($this->controllersFolder.$name."/".$this->urlParsedData[1].".php") && isset($this->urlParsedData[1]) && !empty($this->urlParsedData[1])) {
			
			$this->controllersFolder = $this->controllersFolder.$name."/";				 
			$nc = $this->urlParsedData[1];
			$this->urlParsedData = array_slice($this->urlParsedData, 1);
			print $this->load_controller($nc);
		} else {
			
			$this->controllersFolder = $this->controllersFolder.$name."/";				 				
			$nc = $this->defcontroller;			 
			print $this->load_controller($nc);
		}
	} else {
		$namel = $name.".php";	
		
		if (file_exists($this->controllersFolder.$namel)) {
			
			$this->debug("Index: Loading controller: ".$this->controllersFolder.$name);
			
			$strx = "//" . $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."/";
			
			
			
			$this->sameControllerFolderHTTP = $strx.str_replace($this->origControllerFolderName,"",$this->controllersFolder);
			$this->debug("SCFH: ".$this->sameControllerFolderHTTP);
			
			include_once($this->controllersFolder.$namel);
			
			$name = ucfirst($name);
			$ctrl = new $name($this->ee,$this);
			
			if (isset($ctrl->imSilent)) {
				if ($ctrl->imSilent)
					$this->AlwaysSilent = true; 
			}
			
			if (isset($this->urlParsedData[1]) && !empty($this->urlParsedData[1]) && !isset($this->urlParsedData[2])) { 
				if (method_exists($name,$this->urlParsedData[1])) {	

					$ctrl->functionName = $this->urlParsedData[1];

					if (method_exists($name,'__startup')) {
						$ctrl->__startup();	
					}						
					
					
					call_user_func(array($ctrl, $this->urlParsedData[1]));	
					
					
					if (method_exists($name,'__atdestroy')) {
						$ctrl->__atdestroy();	
					}
				} else {
					$this->raiseError("e404cs",array($name,$this->urlParsedData[1]),$ctl_folder,true,__LINE__);	
				}					 
			} elseif (isset($this->urlParsedData[1]) && !empty($this->urlParsedData[1]) && isset($this->urlParsedData[2])) {			
				
				if (method_exists($name,$this->urlParsedData[1])) {

					$ctrl->functionName = $this->urlParsedData[1];

					if (method_exists($name,'__startup')) {
						$ctrl->__startup();	
					}
										
					call_user_func_array(array($ctrl, $this->urlParsedData[1]), array_slice($this->urlParsedData, 2)); 
					
					
					if (method_exists($name,'__atdestroy')) {
						$ctrl->__atdestroy();	
					}
				} else {
					$this->raiseError("e404mnf",array("ErrorType" => "Method not found.", "Controller" => $this->controllersFolder.$name , "Method" => $this->urlParsedData[1]),$ctl_folder,true,__LINE__);
				}
				
			} else {

				if (method_exists($name,'index')) {

					$ctrl->functionName = "index";

					if (method_exists($name,'__startup')) {
						$ctrl->__startup();	
					}	
					
						
					$ctrl->index();				
					
					if (method_exists($name,'__atdestroy')) {
						$ctrl->__atdestroy();	
					}

				} else {
					$this->raiseError("e404mnf",array("ErrorType" => "Method not found.", "Controller" => $name , "Method" => "index"),$ctl_folder,true,__LINE__);
				}
				
			}
		} elseif ($ctl_folder == $this->controllersFolder) {
			
			$strx = "//" . $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."/";
			$this->sameControllerFolderHTTP = $strx.str_replace($this->origControllerFolderName,"",$this->controllersFolder).$name."";
			if (file_exists($this->controllersFolder.$this->defcontroller.".php")) {
				include_once($this->controllersFolder.$this->defcontroller.".php");
				$name = ucfirst($this->defcontroller);
				$ctrl = new $name($this->ee,$this);
				
				if (isset($ctrl->imSilent)) {
					if ($ctrl->imSilent)
						$this->AlwaysSilent = true; 
				}
				if (isset($this->urlParsedData[0]) && !empty($this->urlParsedData[0]) && !isset($this->urlParsedData[1])) {
					if (method_exists($name,$this->urlParsedData[0])) {
						$ctrl->functionName = $this->urlParsedData[0];
						if (method_exists($name,'__startup')) {
							$ctrl->__startup();	
						}
						call_user_func(array($ctrl, $this->urlParsedData[0]));
						
						if (method_exists($name,'__atdestroy')) {
							$ctrl->__atdestroy();	
						}						
					} else {
						$this->raiseError("e404cs",array($name,$this->urlParsedData[0]),$ctl_folder,true,__LINE__);		
					}				
				} elseif (isset($this->urlParsedData[0]) && !empty($this->urlParsedData[0]) && isset($this->urlParsedData[1])) {			
					
					if (method_exists($name,$this->urlParsedData[0])) {
						$ctrl->functionName = $this->urlParsedData[0];
						if (method_exists($name,'__startup')) {
							$ctrl->__startup();	
						}

						call_user_func_array(array($ctrl, $this->urlParsedData[0]), array_slice($this->urlParsedData, 1)); 
						
						if (method_exists($name,'__atdestroy')) {
							$ctrl->__atdestroy();	
						}
						
					} else {
						$this->raiseError("e404cnf",array("ErrorType"=> "Controller not found","Controller" => $this->urlParsedData[0]),$ctl_folder,true,__LINE__);		
					}					 
				} else {
					$this->raiseError("e404",array("Controller"=>$name),$ctl_folder,true,__LINE__);
				}
			} else {
				$this->raiseError("e404ndc",array("Error1_Type"=> "Controller not found", "Error1_Msg" => "Controller \"".$this->urlParsedData[0]. "\" not found. ", "Error2_Type" => "Default Controller not found", "Error2_Msg"=>"No default controller \"$this->controllersFolder$this->defcontroller\" found."),$ctl_folder,true,__LINE__,__FILE__);
			}
		} else {				 
			$this->raiseError("e404",array($name),$ctl_folder,true,__LINE__);		  
		}
		
	}
	if ($this->controllersFolderR != null) {
		$this->controllersFolderR = $this->controllersFolder;
		$this->controllersFolder = $ctl_folder;
	}
	
	$this->output = ob_get_contents();
	ob_end_clean();
	
	return $this->output;		 
}

	 private $ctl_folder; /// System Variable for the controllers folder.
	 
	 /// This function will raise an error to the user, if is defined by the developer, it will call the error controller, if not it will raise a default exengine 7 errorExit.
	 final private function raiseError($error,$data,$controllersfolder=null,$noexit=false,$linenumber=__LINE__,$file=__FILE__) {
	 	if ($controllersfolder == null )
	 		$controllersfolder = $this->controllersFolder;	 	
	 	if ($this->errorHandler) {
	 		if (file_exists($controllersfolder.$this->errorHandler.".php")) {
	 			include_once($controllersfolder.$this->errorHandler.".php");
	 			$name = ucfirst($this->errorHandler);
	 			$ctrl = new $name($this->ee,$this);
	 			
	 			if (method_exists($name,$error)) {
	 				call_user_func_array(array($ctrl, $error), $data);
	 			} else {
	 				if ($this->ee->cArray["debug"])
	 					$this->ee->errorExit("MVC-ExEngine: Error ".$error,print_r($data,true)."<br/>"."Line Number: ".$linenumber."<br/>"."File: ".$file,null,$noexit);
	 				else {
	 					$this->ee->errorExit("Application Error #".$error,"Powered by MVC-ExEngine",null,$noexit);
	 				}
	 			}				
	 		}
	 	} else {
	 		if ($this->ee->cArray["debug"])
				$this->ee->errorExit("MVC-ExEngine: Error ".$error,print_r($data,true)."<br/>"."Line Number: ".$linenumber."<br/>"."File: ".$file,null,$noexit);
			else {
				$this->ee->errorExit("Application Error #".$error,"Powered by MVC-ExEngine",null,$noexit);
			}
	 	}
	 }
	 
	 /// This function will parse the URL.
	 final private function parseURL() {
	 	$ru = $_SERVER['REQUEST_URI'];
	 	$sn = $_SERVER['SCRIPT_NAME'];
	 	$data = str_replace($sn,"",$ru);
	 	
	 	$this->debug("Input Query: ".$data);
	 	
	 	$x = explode("/",$data);
	 	
	 	for ($i=0 ; $i<count($x) ; $i++) {
	 		$x[$i] = urldecode($x[$i]);
	 	}
	 	
	 	$this->actualInputQuery = $data;
	 	$this->urlParsedData = array_slice($x,1);
	 	$this->unModUrlParsedData = array_slice($x,1);
	 	
	 	$this->debug("Parsed Data: " . print_r($this->urlParsedData,true));
	 }
	 
	 /// This function sets the static folder path.
	 final function setStaticFolder() {
	 	$str = "//" . $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].$this->staticFolder;		
	 	$str = str_replace($this->indexname,"",$str);		
	 	$this->staticFolderHTTP = $str;
	 	
	 	$str = "//" . $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."/";		
	 	$this->controllersFolderHTTP = $str;
	 	
	 	
	 	
	 	
	 }
	 
	 /// Shortcut to the ExEngine Debugger (Session or remote) for the index class.
	 final function debug($message) {
	 	$this->ee->debugThis("eemvcil",$message);
	 }
	 
	 /// Shortcut to the ExEngine Debugger for the actual controller.
	 /*final function debugController($message) {
		 $this->ee->debugThis("eemvc-".$this->controllername,$message);
		}*/
	}

	class eemvc_methods {
		
		var $cparent;	
		
		final function sf() {
			return $this->cparent->index->staticFolderHTTP;;
		}
		
		final function fsf() {
			return $this->cparent->index->staticFolder ;	
		}
		
		final function c() {
			return $this->cparent->index->controllersFolderHTTP;
		}
		
	/* TODO: REMOVE
	final function scpath() {
		$urldata = $this->cparent->index->unModUrlParsedData;
		$size = count($urldata);
		$str_make = null;
		$urldata = array_slice($urldata,0,($size-3));
		$size = count($urldata);
		for ($i = 0; $i < $size ; $i++) {
			$str_make .= $urldata[$i].'/';	
		}
		return $str_make;
	}
	*/
	
	final function sc() {		
		return $this->cparent->index->sameControllerFolderHTTP.$this->cparent->index->controllername."/";	
	}

	final function scfolder() {
		return $this->cparent->index->sameControllerFolderHTTP;
	}
	
	final function scf() {
		if (strlen($this->cparent->functionName) > 0)
			$addtrailing = "/";	
		else $addtrailing = null;
		if ($this->cparent->functionName == "index")
		return $this->cparent->index->sameControllerFolderHTTP;
			else
		return $this->cparent->index->sameControllerFolderHTTP.$this->cparent->index->controllername."/".$this->cparent->functionName.$addtrailing;		
	}
	
	final function vs() {
		return $this->cparent->index->controllersFolderHTTP."?EEMVC_SPECIAL=VIEWSIMULATOR&VIEW=";	
	}
	
	final function __construct(&$parent) {
		$this->cparent = &$parent;
	}
	
	final function getSession($element) {
		if ($this->cparent->index->SessionMode)			
			return @$_SESSION[$element];
		else {
			$this->cparent->debug("Cannot get a session variable, SessionMode is set to false.");
			return null;	
		}			
	}
	
	final function setSession($element,$value) {
		if ($this->cparent->index->SessionMode)
			$_SESSION[$element] = $value;	
		else {
			$this->cparent->debug("Cannot get a session variable, SessionMode is set to false.");
			return null;	
		}
	}
	
	final function clearSession() {
		if ($this->cparent->index->dgEnabled) {
			$dgSession = $_SESSION["DG_SA"];
		}
		session_unset();	
		if ($this->cparent->index->dgEnabled) {
			$_SESSION["DG_SA"] = $dgSession;
		}
	}
	
	final function remSession($element) {
		unset($_SESSION[$element]);	
	}

	final function get($element) {
		return @$_GET[$element];	
	}
	
	final function post($element) {
		return @$_POST[$element];	
	}
	
	final function file($pname) {
		return @$_FILES[$pname];	
	}
	
	final function allpost() {
		return @$_POST;	
	}
	
	final function allget() {
		return @$_GET;	
	}
}

class eemvc_controller {
	public $ee; /// Parent EE7 Object.
	public $index; /// Parent eemvc_index object.
	public $db; /// Default database object, should be loaded first using $this->loadDb.
	public $functionName; /// The name of the in-use function.
	
	public $r; /// Input data methods  
	
	public static $im; /// don't remenber ... :(
		
	private static $inst; /// This contoller instance.
	
	public $imSilent = false; /// Set this controller to silent, useful for writing ajax/comet servers.
	
	/// Default constructor, cannot be overriden, private __atconstruct function should be created in the controller to create a custom event.
	final function __construct(&$ee,&$parent) {
		$this->ee = &$ee;
		$this->index = &$parent;		
		
		self::$inst =& $this;
		
		$this->r = new eemvc_methods($this);
		if (method_exists($this,'__atconstruct')) {
			$this->__atconstruct();	
		}
	}	
	
	/// Connection static function.
	public static function &get_instance()
	{
		return self::$inst;
	}
	
	/// Connects to the default or a connection array specified database (100% compatible with EE DB Manager, depends on its version).
	final function loadDB($dbObj="default") {		
		$this->db = new eedbm($this->ee,$dbObj);
	}
	
	/// Loads a model, by default will create an object with the same name.
	final function loadModel($model_name,$obj_name=null,$create_obj=true) {
		$this->debug("loadModel: Load: ".$model_name);
		if ($this->index->unitTest && defined('STDIN') && !$this->index->utSuite) {
			echo 'MVC-ExEngine 7 -> Preparing model '.ucfirst($model_name).' for unit testing.'."\n";
		} else		
		if ($this->index->utSuite)
			$this->index->utSuite->write("<b>MVC-ExEngine</b><tab>Preparing model ".ucfirst($model_name)." for unit testing.");
		
		$m_file = $this->index->modelsFolder.$model_name.".php";
		
		if (file_exists($m_file)) {
			include_once($m_file);		
			
			$model_name = explode("/",$model_name);
			$model_name = $model_name[(count($model_name)-1)];
			$model_name = ucfirst($model_name);

			if ($obj_name==null)
				$obj_name = $model_name;
			
			if ($create_obj) {
				$this->$obj_name = new $model_name();			
				$this->debug("loadModel: ".$model_name.' ('.$m_file.') Done. ($this->'.$obj_name.')');
			}
			else
				$this->debug("loadModel: ".$model_name.' ('.$m_file.') Done.');

		} else {
			$this->debug("loadModel: ".$model_name.'-Not found');
			if ($this->index->unitTest && defined('STDIN') && !$this->index->utSuite) {
				echo 'MVC-ExEngine 7 -> Model '.$model_name.' not found. (Test Halted)'."\n";
				exit;
			} else
			if ($this->index->utSuite) {
				$this->index->utSuite->write("<b>MVC-ExEngine</b><tab>Model ".$model_name." not found. (Test Halted).");
				exit;
			}
			else
				$this->ee->errorExit("MVC-ExEngine","Model not found.</br><b>Trace:</b><br/>Controller: ".get_class($this)."<br/>Function: ".$this->functionName,"ExEngine_MVC_Implementation_Library");
		}		
	}
	
	final function debug ($msg) {
		//$this->index->debugController($msg);	
		$this->ee->debugThis("eemvc-".get_class($this),$msg);
		
	}
	
	final function loadView($filename,$data=null,$return=false,$dynamic=true,$checkmime=false) {	
		
		$view_fileo = $this->index->viewsFolder.$filename;	
		
		$view_file = $view_fileo;	
		
		if (!file_exists($view_file)) {
			$view_file = $view_fileo.".php";
		}
		
		if (!file_exists($view_file)) {
			$view_file = $view_fileo.".html";
		}
		
		if ($checkmime) {
			$this->ee->eeLoad("mime");
			$eemime = new eemime($this->ee);
			$mime_type = $eemime->getMIMEType($view_file);				
			$this->debug("specialLoadViewStatic: File Mime Type: ".$mime_type);
		}
		
		if (file_exists($view_file)) {
			
			$this->debug("loadView: Loading: ".$view_file);

			$data["EEMVC_SF"] = $this->index->staticFolderHTTP;
			$data["EEMVC_SFTAGGED"] =  $this->index->controllersFolderHTTP."?EEMVC_SPECIAL=STATICTAGGED&FILE=";
			
			$data["EEMVC_C"] = $this->index->controllersFolderHTTP;
			$data["EEMVC_SCFOLDER"] = $this->index->sameControllerFolderHTTP;
			$data["EEMVC_SC"] = $this->index->sameControllerFolderHTTP.$this->index->controllername."/";
			$data["EEMVC_SCF"] = $this->index->sameControllerFolderHTTP.$this->functionName."/";
			
			$data["EEMVC_VS"] = $this->index->controllersFolderHTTP."?EEMVC_SPECIAL=VIEWSIMULATOR&VIEW=";
			
			$jq = new jquery($this->ee);
			$jqstr = $jq->load($this->index->jQueryVersion,true);
			$data["EEMVC_JQUERY"]  = $jqstr; 
			$jqstr2 = $jq->load_ui($this->index->jQueryUITheme,$this->index->jQueryUIVersion,true);			
			$data["EEMVC_JQUERYUI"]  = $jqstr2; 
			$jqstr3 = $jq->load_migrate(true);
			$data["EEMVC_JQUERYMIGRATE"] = $jqstr3;

			extract($data);	
			
			ob_start();	
			
			if ($dynamic) {
				if ((bool) @ini_get('short_open_tag') === FALSE)
				{
					$this->debug("loadView: Mode: ShortTags_Rewriter");
					echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($view_file))));
				}
				else
				{		
					$this->debug("loadView: Mode: Include");	
					include($view_file);
				}
			}
			else
			{
				$this->debug("loadView: Mode: ReadFile");
				readfile($view_file);
			}
			
			$this->debug("loadView: Mode: View loaded: ".$view_file);
			
			$output = ob_get_contents();
			ob_end_clean();
			
			//$this->index->debug($output);
			
			if ($return)
			{
				return $output;
			} else {
				if ($checkmime)
					header('Content-type: '.$mime_type);
				echo $output;
			}				
		} else {
			$this->ee->errorExit("MVC-ExEngine","View (".$view_file.") not found.","eemvcil");
		}
	}
}

class eemvc_model {
	
	//Default Database Object (used for Code-Hinting compatibility)
	public $db;
	public $r; 

	public function __toString() {
		$obj = $this;
		unset($obj->db);
		unset($obj->r);
		return print_r($obj,true);
	}
	
	function __construct() {
		$this->r = new eemvc_methods($this);		
	}
	
	//Database loader, compatible with EEDBM (used for Code-Hinting compatibility)
	final function loadDB($dbObj="default") {		
		$this->db = new eedbm($this->ee,$dbObj);
	}
	
	//Get all Controller's properties
	function __get($key)
	{
		$Contr =& eemvc_get_instance();
		return $Contr->$key;
	}
	
	//Call Controller's methods
	function __call($name,$args=null) {
		$Contr =& eemvc_get_instance();		
		if (method_exists('eemvc_controller',$name)) {
			if ($args==null) {
				call_user_func(array($Contr,$name));
			} else {
				call_user_func_array(array($Contr,$name), $args); 
			}
		}
	}
}

class eemvc_model_dbo extends eemvc_model {
	
	private function getProperties() {
		$vars = get_object_vars($this);		
		unset($vars["db"]);
		unset($vars["r"]);
		unset($vars["TABLEID"]);
		unset($vars["INDEXKEY"]);		
		if (isset ($this->EXCLUDEVARS) ) {
			unset($vars["EXCLUDEVARS"]);
			for ($c = 0; $c < count($this->EXCLUDEVARS); $c++) {
				unset($vars[$this->EXCLUDEVARS[$c]]);	
			}
		}		
		return $vars;
	}

	public function __toString() {
		$obj = clone $this;
		unset($obj->db);
		unset($obj->r);
		unset($obj->TABLEID);
		unset($obj->INDEXKEY);
		if (isset($obj->EXCLUDEVARS))
			unset($obj->EXCLUDEVARS);
		return print_r($obj,true);
	}
	
	final function load($SafeMode=true) {		
		$ik = $this->INDEXKEY;
		
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		
		if (isset($this->$ik)) {
			
			if (method_exists($this,'__befload')) {
				$this->__befload();	
			}
			
			$this->loadDb();
			$this->db->open();				
			$q = $this->db->query("SELECT * FROM ".$this->TABLEID." WHERE ".$this->INDEXKEY." = '".urlencode($this->$ik)."' LIMIT 1");
			if (!$q) return false;
			if ($this->db->rowCount($q) == 0) return false;
			$data = $this->db->fetchArray($q,$SafeMode,MYSQLI_ASSOC);
			unset($data[$this->INDEXKEY]);
			$keys = @array_keys($data);
			for ($c = 0; $c < count($keys); $c++) {
				$this->$keys[$c] = $data[$keys[$c]];	
			}
			
			if (method_exists($this,'__aftload')) {
				return $this->__aftload();	
			} else
			return true;
			
		} else return false;
	}
	
	function search($SearchArray=null,$SafeMode=true) {
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		$cn = get_class($this);	
		$this->loadDb();
		$this->db->open();	
		$re = null;
		$o=0;
		if ($SearchArray!=null && is_array($SearchArray))
			$w = $this->db->searchArrayToSQL($SearchArray);
		else return false;
		$q = $this->db->query("SELECT * FROM ".$this->TABLEID. " " . $w);
		if ($q) {
			while ($row = $this->db->fetchArray($q,$SafeMode,MYSQLI_ASSOC)) {
				unset($v);
				$v = new $cn();	
				if (method_exists($v,'__befload')) {
					$v->__befload();
				}							
				$keys = @array_keys($row);
				for ($c = 0; $c < count($keys); $c++) {
					$v->$keys[$c] = $row[$keys[$c]];	
				}	
				if (method_exists($v,'__aftload')) {
					$v->__aftload();
				}
				$re[$o] = &$v;		
				$o++;
			}
		} else return false;
		return $re;
	}

	function load_all($WhereArray=null,$SafeMode=true) {
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		$cn = get_class($this);	
		$this->loadDb();
		$this->db->open();	
		$re = null;
		$o=0;
		if ($WhereArray!=null && is_array($WhereArray))
			$w = $this->db->whereArrayToSQL($WhereArray);
		else $w = null;
		$q = $this->db->query("SELECT * FROM ".$this->TABLEID. " " . $w);
		if ($q) {
			while ($row = $this->db->fetchArray($q,$SafeMode,MYSQLI_ASSOC)) {
				unset($v);
				$v = new $cn();	
				if (method_exists($v,'__befload')) {
					$v->__befload();
				}							
				$keys = @array_keys($row);
				for ($c = 0; $c < count($keys); $c++) {
					$v->$keys[$c] = $row[$keys[$c]];	
				}	
				if (method_exists($v,'__aftload')) {
					$v->__aftload();
				}
				$re[$o] = &$v;		
				$o++;
			}
		} else return false;
		return $re;
	}
	
	function debug($message) {
		$this->ee->debugThis("eemvc-dbo-".get_class($this),$message);
	}
	
	function load_values($SafeMode=true) {
		$ik = $this->INDEXKEY;
		
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		
		$v = $this->getProperties();	
		$nnc = 0;		
		foreach (array_keys($v) as $ak) {
			if ($v[$ak] == null) unset($v[$ak]); else $nnc++;
		}
		if ($nnc == 0) { $this->debug("load_values() requires at least one property set.");  return false; }
		
		
		if (method_exists($this,'__befload')) {
			$this->__befload();	
		}
		
		$this->loadDb();
		$this->db->open();	
		
		$wq = $this->db->whereArrayToSQL($v);	
		
		$q = $this->db->query("SELECT * FROM `".$this->TABLEID."` ".$wq." LIMIT 1");		
		if (!$q) return false;
		if ($this->db->rowCount($q) == 0) return false;
		$data = $this->db->fetchArray($q,$SafeMode,MYSQLI_ASSOC);
		unset($data[$this->INDEXKEY]);
		$keys = @array_keys($data);
		for ($c = 0; $c < count($keys); $c++) {
			$this->$keys[$c] = $data[$keys[$c]];	
		}
		
		if (method_exists($this,'__aftload')) {
			return $this->__aftload();	
		} else
		return true;		
	}
	
	function load_page($from,$count,$SafeMode=true) {		
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		$cn = get_class($this);	
		$this->loadDb();
		$this->db->open();	
		$re = null;
		$c=0;
		$q = $this->db->query("SELECT * FROM `".$this->TABLEID."` LIMIT ".$from." , ".$count);
		if ($q) {
			while ($row = $this->db->fetchArray($q,$SafeMode,MYSQLI_ASSOC)) {
				unset($v);
				$v = new $cn();				
				if (method_exists($v,'__befload')) {
					$v->__befload();
				}				
				$keys = @array_keys($row);
				for ($c = 0; $c < count($keys); $c++) {
					$v->$keys[$c] = $row[$keys[$c]];	
				}	
				if (method_exists($v,'__aftload')) {
					$v->__aftload();
				}
				$re[$o] = &$v;		
				$o++;
			}
		} else return false;
		return $re;
	}
	
	final function insert($SafeMode=true) {
		$ik = $this->INDEXKEY;
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		if (isset($ik)) {
			if (method_exists($this,'__befinsert')) {
				$this->__befinsert();	
			}
			$iarr = $this->getProperties();
			$this->loadDb();
			$this->db->open();
			$r = $this->db->insertArray($this->TABLEID,$iarr,$SafeMode);
			if ($r) {
				$this->$ik = $this->db->InsertedID;	
				if (method_exists($this,'__aftinsert')) {
					$this->__aftinsert($r);	
				}
				return true;
			} else 
			return false;			
		}		
	}
	
	final function update($SafeMode=true) {
		$ik = $this->INDEXKEY;
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		if (isset($ik)) {
			if (method_exists($this,'__befupdate')) {
				$this->__befupdate();	
			}
			$this->loadDb();
			$this->db->open();		
			$uarr = $this->getProperties();
			unset($uarr[$ik]);
			$warr = array( $ik => $this->$ik );	
			$res = $this->db->updateArray($this->TABLEID,$uarr,$warr,$SafeMode);		
			if (method_exists($this,'__aftupdate')) {
				return $this->__aftupdate($r);	
			} else
			return $r;
		} else return false;
	}
	
	final function delete() {
		$ik = $this->INDEXKEY;
		if (!isset($this->TABLEID)) $this->TABLEID = get_class($this);
		if (isset($this->$ik)) {
			$this->loadDb();
			$this->db->open();
			$q = $this->db->query("DELETE FROM `".$this->TABLEID."` WHERE `".$ik."` = '".urlencode($this->$ik)."' LIMIT 1");		
			$this->$this->INDEXKEY = null;
			return true;
		} else return false;
	}
}