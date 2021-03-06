<?php
/**
@file ee.php
@author Giancarlo A. Chiappe Aguilar <gchiappe@qox-corp.com> <gchiappe@outlook.com.pe>
@version 7.0.8.45

@section LICENSE

MIT License

Copyright (c) 2017 QOX Corporation

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

@section DESCRIPTION

ExEngine Core

ExEngine PHP Framework core, this file contains the main functions, and the needed
ones to load any module that extends the framework functionality.

*/

// QOX ExEngine
// Copyright © 2003-2009 DGS (darkgiank.info)
// Copyright © 2009-2013 LinkFast Company (linkfastsa.com)
// Copyright © 2013-2017 QOX Corporation (qox-corp.com)
//
// Based on "DGS ExEngine" by Giancarlo A. Chiappe Aguilar <gchiappe@outlook.com.pe>

namespace {
	/* PHP Version Check */
	if (version_compare(PHP_VERSION, '5.4.0', '<')) {
		print '<h1>ExEngine</h1><p>ExEngine requires PHP 5.4 or higher, please update your installation.</p>';
		exit();
	}
}

namespace ExEngine {
/// ExEngine 7 Framework Core Class (default config array is in eefx/cfg.php).
	class Core {

		public $cArray; 		/// Loaded Configuration Array.
		public $aArray;			/// Arguments Array.
		public $dbArray;		/// Default Database Array.
		public $libsLoaded; 	/// Loaded Libs Array.
		public $eeScriptPath; 	/// Full path to ee7.php file.
		public $extendedLoaded; /// Extended Engines loaded Bool.

		public $appName = "default"; /// Set the application name, some libs/mixed/extendedengines needs this to be changed.

		public $eePath;			/// ExEngine Path (automatic).
		public $eeDir;			/// ExEngine Directory (Relative to $appPath).
		public $appPath;		/// Application Path (automatic, overridable).

        /**
         * Deprecation Notice:
         * ForwardMode is going deprecated, removal on next minor version.
         * @var bool
         */
		public $forwardMode = false; 	/// ExEngine 6 ForwardMode controller.

        /**
         * Deprecation Notice:
         * ForwardMode is going deprecated, removal on next minor version.
         * @var string
         */
		public $ee6version = "6.4.3.2";	/// ExEngine 6 ForwardMode Version

		public $is64bit = false; /// Check for 64-bit execution, useful when using very big integers.

		public $msEnabled = false; /// Multi-Site (MuS) Mode (Like- ExEngine 6 MU)

		const V_MAJOR = 7;
		const V_MINOR = 0;
		const V_BUILD = 8;
		const V_REVIS = 45;

        /**
         * Deprecation Notice:
         * ForwardMode is going deprecated, removal on next minor version.
         */
		const REALVERSION = "7.0.8";
		const BUILD = 45;

		const REL_DATE = "09 JAN 2017";
		const RELEASE = "alpha";
		const EE7WP = "http://oss.qox-corp.com/exengine";

		private static $instance = false;

		// Update Settings (overridable, use "ee_comups_server" and "ee_comups_package" in config array) ( no operational yet )
		const COMUPS_SERVER = "update-oss.qox-corp.com"; /// Comups update server for update checking.
		const COMUPS_PKG	= "exengine"; /// Comups package name for version checking.

        /**
         * Returns the current ExEngine's Core instance.
         *
         * @return bool|Core
         */
		public static function &get_instance()
		{
			return self::$instance;
		}

		function __construct($args=null,$configArray="default",$dbArr="default") {

			$this->eeScriptPath = __FILE__ ;
			$this->eePath = dirname(__FILE__)."/";

			#Parse arguments
			if (isset($args)) {
				if (is_array($args)) {
					$this->argsSet($args);
				} else {
					$this->errorExit("ExEngine Core","Invalid Arguments variable, should be an array or empty.");
				}
			} else {
				$this->argsSet();
			}

			#If Enabled, Disable Browser's Cache
			if (!$this->argsGet("BrowserCache")) {
				$this->miscDisableBrowserCache();
			}

			#ConfigFile Check and Include
			if (isset($configArray)) {
				if ($configArray != "default") {
					if (is_array($configArray)) {
						$this->cArray = $configArray;
					} else {
						$this->errorExit("ExEngine Core","Invalid Configuration variable, should be an array or empty to use default config file.");
					}
				} else {
					if (!$this->configFileSet()) {
						$this->errorExit("ExEngine Config Error [XC01]","Invalid Configuration File.");
						exit();
					}
				}

				#Check if APP_PATH is gonna be overriden.
				if (!isset($this->cArray["app_path"])) {
					$this->appPath = $this->eePath . "../";
				}
				#Check if EE_DIR is gonna be overriden.
				if (!isset($this->cArray["ee_dir"])) {
					$dA = explode('/', dirname(__FILE__));
					$dAk = array_keys($dA);
					$dAkm = max($dAk);
					$this->eeDir = $dA[$dAkm]."/" ;
				}
				#Set TimeZone if set in Config Array
				if (isset($this->cArray["php_timezone"])) {
					date_default_timezone_set($this->cArray["php_timezone"]);
				}
				#Add custom PEAR directory to include_path
				if ($this->cArray["pear_path"] != "auto") {
					//$pIncPath = get_include_path();
					set_include_path(".:".$this->cArray["pear_path"]);
				}
				#Check ForwardMode in CfgFile.
				if ($this->cArray["forwardmode"]) {
					$this->forwardMode=true;
				}
			}

			#Check for ExEngine MS (Multi-Site)
			global $ee_ms;
			if (isset($ee_ms)) {
				if (is_array($ee_ms)) {
					$this->cArray = array_merge($this->cArray,$ee_ms);
					$this->msEnabled = true;
					if (isset($ee_ms["db"]) && is_array($ee_ms["db"]) && array_key_exists("type",$ee_ms["db"])) {
						$this->dbArray = $ee_ms["db"];
					}
				} else {
					$this->errorExit("ExEngine 7 Core","Invalid Multi-Site Configuration variable, should be an array.");
				}
			}

			#Database Array Copy
			if (isset($dbArr) && $dbArr != "default") {
				if (is_array($dbArr)) {
					if (array_key_exists("type",$dbArr)) {
						$this->dbArray = $dbArr;
					} else
						$this->errorWarning("Database array is not consistent, no default database support.");
				} else {
					$this->errorWarning("Database variable is not an array, no default database support.");
				}
			}

			#Print ExEngine7 Slogan.
			if (!$this->argsGet("SilentMode") && $this->argsGet("ShowSlogan")) {
				$this->miscMessages("Slogan");
			}

			self::$instance =& $this;

			/* php version check, required 5.4+ */
			if (version_compare(phpversion(),'5.4','<')) {
				$this->libLoadRes("jquery");
				$this->errorExit('ExEngine Core','PHP Version not supported, please use PHP 5.4+. Current version: ' . phpversion());
			}

			/* load libraries */
			$this->libLoad();

			#Check 64-bit System
			if (strlen(PHP_INT_MAX)>=19) {
				$this->is64bit = true;
			} else {
				$this->is64bit = false;
				$this->errorWarning("ExEngine is running in a 32-bit environment, 64-bit environment is recommended.");
			}

			if (defined('STDIN')) {
				echo 'X-Powered by QOX ExEngine ('.$this->miscUName(). ($this->is64bit ? ", x64":"") . ")\n";
			} else {
				if ($this->aArray['AutoSession']) {
					$this->sessionCreator();
				}
			}

			$this->initEnd();
		}

		private final function initEnd() {
			#Check EE Storage Folder
			if ($this->cArray["storage"] && $this->cArray["storage_check"]) {
				\ee_storage::checkStorageFolder();
			}
		}

		# Config Checking
		private final function configFileSet() {
			$cF = $this->eePath."eefx/cfg.php";
			$tP = 0;
			$ee_config=null;
			if (file_exists($cF)) {
				$ee_config = null;
				include_once($cF);
				$tP++;
			}
			# Config parser
			if (is_array($ee_config)) {
				$this->cArray = $ee_config;
				$tP++;
			}
			# Database parser
			if (isset($ee_ddb)) {
				if (is_array($ee_ddb)) {
					if (array_key_exists("type",$ee_ddb)) {
						$this->dbArray = $ee_ddb;
					} else
						$this->errorWarning("Default database array is not consistent, no default database support.");
				} else {
					$this->errorWarning("Default database variable is not an array, no default database support.");
				}
			}
			if ($tP == 2)
				return true;
			else
				return false;
		}

		#Config Functions
		final function configGetParam($ParameterFromConfigArray) {
			if (isset($this->cArray[$ParameterFromConfigArray])) {
				if ($ParameterFromConfigArray == "https_path") {
					if ($this->cArray["https_path"] == "same")
						return $this->cArray["http_path"];
					else
						return $this->cArray["https_path"];
				} else
					return $this->cArray[$ParameterFromConfigArray];
			} else {
				return null;
			}
		}

		/*
		#CommandInterpreter
		final function cmdDirectCommand($Command,$Argument) {
			# TODO
			return false;
		}*/

		#Library Management
		private final function libLoad() {
			if (!$this->argsGet("LoadLibs")) {
				$this->errorWarning("No Libraries are loaded, this mode is not supported.");
			} else {
				# You can disable Libraries commenting any of them, putting a # before libLoadRes.
				#			PROVIDES				CLASSES PROVIDED
				# VERY IMPORTANT
				$this->libLoadRes("me");			# MixedEngines Control Library		(me)
				#

				$this->libLoadRes("eedbm");			# SQL Database Manager				(eedbm)
                /** EE's JQuery is going deprecated, removal on next minor version */
				$this->libLoadRes("jquery");		# ExEngine's jQuery					(jquery)
				$this->libLoadRes("eema");		    # ExEngine Message Agent            (eema)

				if ($this->argsGet("SpecialMode") == "MVCOnly") {
					$this->libLoadRes("eemvcil"); #MVC-ExEngine
					$this->aArray["SilentMode"] = true;
				} else {
					$this->libLoadRes("eendbm");		# NoSQL Database Manager			(eendbm)
					$this->libLoadRes("ee7info");		# EE7 Information Service Class 	(ee7info)
					$this->libLoadRes("browser");		# Client Browser properties Class 	(browser)
					$this->libLoadRes("log");			# Loging Class						(eelog)
					$this->libLoadRes("ifile");			# Internet Files Manipulation		(ifile)
					$this->libLoadRes("mail");			# Internet Mail Class				(eemail)
					$this->libLoadRes("gd");			# GD Image Manipulation				(gd)
                    # MVC-ExEngine:
                    # - eemvc_index
                    # - eemvc_model
                    # - eemvc_model_dbo (& DBO variants)
                    # - eemvc_controller
                    # - eemvc_methods
					$this->libLoadRes("eemvcil");
				}
				if ($this->cArray["devguard"])
					$this->libLoadRes("devguard"); # DevGuard Class (ee_devguard)
				if ($this->cArray["storage"]) {
					$this->libLoadRes("eestorage"); # Storage Class (ee_storage)
				}
			}
		}

		final function libLoadFromFile($lib) {
			if (file_exists($lib)) {
				include_once($lib);
			} else {
				$this->errorWarning($lib." cannot be found or is not accesible by ExEngine.");
				return false;
			}
		}

		final function libGetResPath($engine,$mode="full") {
			if ($mode == "full") {
				return $this->eePath."eefx/res/".$engine."/" ;
			} elseif ($mode == "http") {
				if ($_SERVER['SERVER_PORT']=="443") {
					return $this->configGetParam("https_path")."eefx/res/".$engine."/" ;
				} else {
					return $this->configGetParam("http_path")."eefx/res/".$engine."/" ;
				}
			} else {
				$this->errorExit("ExEngine Paths Error [XC06]","ExEngine 7 : Library Resources : libGetResPath second argument is invalid.<br/>");
			}
		}

		final function libLoadRes($file) {
			if (!$this->libIsLoaded($file)) {
				if (file_exists($this->eePath."eefx/lib/".$file.".php")) {
					$this->libsLoaded[] = $file;
					include_once($this->eePath."eefx/lib/".$file.".php");
					return true;
				} else {
					$this->errorWarning($file." cannot be found or is not accesible by ExEngine. Please check Libs folder.");
				}
			} else {
				$this->miscMessShow($file." Library is already loaded.");
				return false;
			}
		}

		final function libIsLoaded($libName) {
			$c=0;
			if (is_array($this->libsLoaded)) {
				foreach ($this->libsLoaded as $lib) {
					if ($libName == $lib) $c++;
				}
				if ($c>0)
					return true;
				else
					return false;
			} else
				return false;
		}

		#Error Management
		final function errorExit($title,$mess,$wikiPage=null,$noexit=false, $UseTheme='default') {
			if ($this->argsGet("ErrorLevel") >= 1) {
				if ($this->argsGet("VisualError")) {
					if (!defined('STDIN')) {
						if ($wikiPage) {
							$mess .= '<br/><br/> <a href="http://oss.qox-corp.com/wikis/ee/index.php?title='.$wikiPage.'">More information...</a>';
						}
						if (!$UseTheme) {
							print "<h2>".$title."</h2><br/>".$mess;
							if ($this->argsGet("HaltOnError")) {
								if (!$noexit) {
									if (!$UseTheme)
										print " <br/>\nExEngine Core halted.";
									exit();
								}
							}
						}
						else {
							$haltinfo = '';
							if ($this->argsGet("HaltOnError")) {
								if (!$noexit) {
									$haltinfo = "ExEngine Core halted.";
								}
							}
							include_once($this->eePath.'eefx/common/error_mgmt/themes/'.$UseTheme.'/errorexit.phtml');
							if ($this->argsGet("HaltOnError")) {
								if (!$noexit) {
									exit();
								}
							}
						}

					} else {
						if ($wikiPage)
							$mess .= ' More Info at: http://oss.qox-corp.com/wikis/ee/index.php?title='.$wikiPage;
						echo $title.' -> '. $mess . "\n";
						if (!$noexit) {
							print "ExEngine Core halted.\n";
							exit;
						}
					}
				} else {
					if (!$this->argsGet("SilentMode")) {
						print "<!-- == ".$title." ==\n    ".$mess." -->\n";
						if ($this->argsGet("HaltOnError"))
							if (!$noexit)
								exit();
					}
				}
			}
		}

		final function errorWarning($mess) {
			if ($this->argsGet("ErrorLevel") >= 2) {
				if (!$this->argsGet("SilentMode")) {
					if (!defined('STDIN')) {
						if ($this->argsGet("VisualWarning")) {
							print "<p><b>ExEngine Warning:</b> ".$mess."</p>";

						} else {
							print "<!-- == ExEngine Warning ==\n    ".$mess." -->\n";

						}
					} else {
						echo "ExEngine Warning: ".$mess."\n";
					}
				}
			}
		}

		final function errorFunction($app,$message) {
			return $app." - ".$message;
		}

		final function errorLib($libName) {
			if (!class_exists($libName)) {
				$this->errorExit("ExEngine Library Error [XC03]","Enable Library load to use some EE7 functions, like 'meLoad'.<br/><a href=\"http://oss.qox-corp.com/wikis/ee/index.php?title=Library_(English)#Provides\" target=\"_blank\">More info</a>.");
			}
		}

		#Arguments Control
		final function argsGet($det) {
			if (isset($this->aArray)) {
				if (array_key_exists($det,$this->aArray)) {
					return $this->aArray[$det];
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

		final function argsSet($args=null) {
			#Default values of Arguments
			$a["ErrorLevel"] = 2;
			$a["LoadLibs"] = true;
			$a["SilentMode"] = false;
			$a["ShowSlogan"] = true;
			$a["HaltOnError"] = true;
			$a["VisualError"] = true;
			$a["VisualWarning"] = true;
			$a["BrowserCache"] = true;
			$a["SpecialMode"] = null;
			$a["AutoSession"] = true;

			if (isset($args) && is_array($args)) {
				$a = array_merge($a,$args);
			}

			if ($a["SpecialMode"] == "MVCOnly") {
				$a["SilentMode"] = true;
			}

			#Production Enviroment
			$this->aArray = $a;
		}

		#MixedEngines Loader (ExEngine Formatted PHP Files)
		public $loadedME;
		public $loadedMEVersion;
		final function meLoad($enginePath,$ReturnNewObject=0,$requiredVersion="0.0.0.0") {
			$this->debugThis("ee-core","meLoad: ".$enginePath);
			$this->errorLib("\\ExEngine\\MixedEngineLoader");

			if (!$this->strContains($enginePath,",")) {
				$a = new MixedEngineLoader($this,$enginePath,$requiredVersion);
				$r = $a->load();
				$this->debugThis("ee-core","meLoad: ".$enginePath.": ".var_export($r,true));
				if ($ReturnNewObject == 1) {
					$ee7p = $this;
					$rObj = null;
					eval('$rObj = new '.$enginePath.'($ee7p);');
					$r = $rObj;
				}
			} else {
				$a = new MixedEngineLoader($this,null,"0.0.0.0");
				$r = $a->checkAndLoad($enginePath,"me");
				$this->debugThis("ee-core","meLoad: ".$enginePath.": ".var_export($r,true));
			}

			return $r;

		}

		final function meGetResPath($engine,$mode="full") {
			if ($mode == "full") {
				return $this->eePath."eefx/engines/resources/".$engine."/" ;
			} elseif ($mode == "http") {
				if ($this->strContains($this->configGetParam("http_path"),"http://")) {
					return $this->configGetParam("http_path")."eefx/engines/resources/".$engine."/" ;
				} else {
					if (strlen($_SERVER['SERVER_NAME']) == 0)
						$this->errorExit("ExEngine MixedEngines Error [XC04]","ExEngine : MixedEngines : meGetResPath -> SERVER_NAME is not defined.");
					return "http://" . $_SERVER['SERVER_NAME'] . $this->configGetParam("http_path")."eefx/engines/resources/".$engine."/" ;
				}
			} elseif ($mode == "https") {
				if ($this->strContains($this->configGetParam("https_path"),"https://")) {
					return $this->configGetParam("https_path")."eefx/engines/resources/".$engine."/" ;
				} else {
					if (strlen($_SERVER['SERVER_NAME']) == 0)
						$this->errorExit("ExEngine MixedEngines Error [XC04]","ExEngine : MixedEngines : meGetResPath -> SERVER_NAME is not defined.");
					return "https://" . $_SERVER['SERVER_NAME'] . $this->configGetParam("http_path")."eefx/engines/resources/".$engine."/" ;
				}
			} elseif ($mode == "httpauto") {
				if ($this->strContains($this->configGetParam("https_path"),"http://") || $this->strContains($this->configGetParam("https_path"),"https://") ) {
					$this->errorExit("ExEngine MixedEngines Error [XC04]","ExEngine : MixedEngines : meGetResPath httpauto mode is not supported in your configuration.<br/>");
				} else {
					if (strlen($_SERVER['SERVER_NAME']) == 0)
						$this->errorExit("ExEngine MixedEngines Error [XC04]","ExEngine : MixedEngines : meGetResPath -> SERVER_NAME is not defined.");
					return "//" . $_SERVER['SERVER_NAME'] . $this->configGetParam("http_path")."eefx/engines/resources/".$engine."/" ;
				}
			} else {
				$this->errorExit("ExEngine MixedEngines Error [XC04]","ExEngine 7 : MixedEngines : meGetResPath second argument is invalid.<br/>");
			}
		}

		#Extended Engines (Normal PHP Include)
		public $loadedEE;

		/**
		 * @param $name string Extended engine to load, can load multiple engines comma separating them.
		 * @return bool|int
		 */
		final function eeLoad($name) {
			$findme   = ',';
			$pos = strpos($name, $findme);
			if ($pos === false) {
				if (!$this->eeIsLoaded($name)) {
					if (file_exists($this->eePath."eefx/extended/core/".$name.".php")) {
						$this->loadedEE[$name] = true;
						include_once($this->eePath."eefx/extended/core/".$name.".php");
						return true;
					} else
						return false;
				} else
					return true;
			} else {
				$err = 0;
				$load = 0;
				$ees = explode(",",$name);
				foreach ($ees as $ext) {
					if (!$this->eeIsLoaded($ext)) {
						if (file_exists($this->eePath."eefx/extended/core/".$ext.".php")) {
							$this->loadedEE[$ext] = true;
							include_once($this->eePath."eefx/extended/core/".$ext.".php");
							$load++;
						} else
							$err++;
					} else
						$load++;
				}
				if ($err > 0) { return (-1)*$err; } else if ($load > 0) { return true; }
			}
		}

		final function eeExists($name) {
			if (file_exists($this->eePath."eefx/extended/core/".$name.".php")) {
				return true;
			} else
				return false;
		}

		final function eeIsLoaded($name) {
			if (isset($this->loadedEE[$name])) {
				return true;
			} else
				return false;
		}

		final function eeResPath($mode="full") {
			if ($mode == "full") {
				return $this->eePath."eefx/extended/resources/" ;
			} elseif ($mode == "http") {
				if ($this->strContains($this->configGetParam("http_path"),"//")) {
					return $this->configGetParam("http_path")."eefx/extended/resources/" ;
				} else {
					if (strlen($_SERVER['SERVER_NAME']) == 0)
						$this->errorExit("ExEngine MixedEngines Error [XC05]","ExEngine : ExtendedEngines : eeResPath -> SERVER_NAME is not defined.");
					return "http://" . $_SERVER['SERVER_NAME'] . $this->configGetParam("http_path")."eefx/extended/resources/" ;
				}
			} elseif ($mode == "https") {
				if ($this->configGetParam("https_path")!= "auto" && ($this->strContains($this->configGetParam("https_path"),"//") )) {
					return $this->configGetParam("https_path")."eefx/extended/resources/" ;
				} else {
					if (strlen($_SERVER['SERVER_NAME']) == 0)
						$this->errorExit("ExEngine MixedEngines Error [XC05]","ExEngine : ExtendedEngines : eeResPath -> SERVER_NAME is not defined.");
					return "https://" . $_SERVER['SERVER_NAME'] . $this->configGetParam("http_path")."eefx/extended/resources/" ;
				}
			} elseif ($mode == "httpauto") {
				if ($this->strContains($this->configGetParam("https_path"),"http://") || $this->strContains($this->configGetParam("https_path"),"https://") ) {
					$this->errorExit("ExEngine MixedEngines Error [XC05]","ExEngine : ExtendedEngines : eeResPath httpauto mode is not supported in your configuration.<br/>");
				} else {
					if (strlen($_SERVER['SERVER_NAME']) == 0)
						$this->errorExit("ExEngine MixedEngines Error [XC05]","ExEngine : ExtendedEngines : eeResPath -> SERVER_NAME is not defined.");
					return "//" . $_SERVER['SERVER_NAME'] . $this->configGetParam("http_path")."eefx/extended/resources/" ;
				}
			} else {
				$this->errorExit("ExEngine MixedEngines Error [XC05]","ExEngine : ExtendedEngines : eeResPath argument is invalid.<br/>");
			}
		}

		#Log Functions
		final function logAuto($message,$logObject=null) {
			if ($this->libIsLoaded("log")) {
				if (isset($logObject))
					$logObject->logThis($message);
			}
		}

		#HTTP Arguments Functions
		final function httpGet($add=null,$base=null,$addtobase=false) {
			if (!isset($base)) {
				if (isset($add)) {
					if (isset($_SERVER['QUERY_STRING']) && !$addtobase) {
						$r = $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&".$add;
					} else {
						$r = $_SERVER['PHP_SELF']."?".$add;
					}
				} else {
					$r = $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING'];
				}
			} else {
				if ($this->strContains($base,"?") === false) {
					$r = $base . "?" . $add;
				} else {
					$r = $base . "&" . $add;
				}
			}
			return $r;
		}

		final function httpGetUrlFromPath($Path) {
			$R = str_replace($_SERVER['DOCUMENT_ROOT'],'',$Path);
			if (substr($R,0,1)=='/')
				return '//' . $_SERVER['HTTP_HOST'] . $R;
			else
				return '//' . $_SERVER['HTTP_HOST'] . '/'.$R;
		}

		final function httpMixedArgs() {
			if (isset($_POST)) {
				$postArgs = $_POST;
			}
			if (isset($_GET)) {
				$getArgs = $_GET;
			}
			if (is_array($postArgs) && is_array($getArgs))	return array_merge($postArgs,$getArgs);
			elseif (is_array($postArgs)) return $postArgs;
			elseif (is_array($getArgs)) return $getArgs;
			return [];
		}

		final function httpCheckURL($url) {
			$response =	@get_headers($url);
			if ($this->strContains($response[0],"200"))
				return true;
			else
				return false;
		}

		final function sessionCreator($SessionName='EXENGINE-SESSIONID', $SessionLifeTime=0,$SessionCookiePath='/', $SessionDomain=null) {
			if (isset($SessionLifeTime) && !isset($SessionDomain)) {
				session_set_cookie_params($SessionLifeTime,$SessionCookiePath);
			} elseif (isset($SessionLifeTime) && isset($SessionDomain)) {
				session_set_cookie_params($SessionLifeTime,$SessionCookiePath,$SessionDomain);
			}
			if (isset($SessionName)) {
				session_name($SessionName);
			}
			session_start();
			if (isset($SessionLifeTime) && !isset($SessionDomain)) {
				setcookie(session_name(),session_id(),time()+$SessionLifeTime,$SessionCookiePath);
			} elseif (isset($SessionLifeTime) && isset($SessionDomain)) {
				setcookie(session_name(),session_id(),time()+$SessionLifeTime,$SessionCookiePath,$SessionDomain);
			}
		}

		#Debug functions (requieres the monitor client and debug-mode enabled in cfg file)
		final function debugThis($app,$message,$dateFormat="%I:%M:%S %P - %b/%d/%Y") {
			$app = $this->miscURLClean($app);
			if ($this->cArray["debug"]) {

				if ($this->aArray["AutoSession"])
					@session_start();

				if (session_status() != PHP_SESSION_NONE) {
					if (isset($_SESSION["exengine-debugger-apps"]) && is_array($_SESSION["exengine-debugger-apps"])) {
						foreach ($_SESSION["exengine-debugger-apps"] as $appf) {
							if ($appf == $app) {
								$found=true;
								break;
							}else{
								$found=false;
							}
						}
					} else {
						$found = false;
					}
					if (!$found) {
						$_SESSION["exengine-debugger-apps"][] = $app;
					}
					$da = array ( "date" => strftime($dateFormat), "msg"=> $message);
					if (!isset($_SESSION[$app][0])) {
						$_SESSION[$app][0] = $da;
					} else {
						$_SESSION[$app][] = $da;
					}
				}  else return false;
			}
		}

		final function debugClean($app) {
			$app = $this->miscURLClean($app);
			if ($this->cArray["debug"]) {

				if ($this->aArray["AutoSession"])
					@session_start();

				if (session_status() != PHP_SESSION_NONE) {
					$found=false;
					if (isset($_SESSION["exengine-debugger-apps"]) && is_array($_SESSION["exengine-debugger-apps"])) {
						foreach ($_SESSION["exengine-debugger-apps"] as $appf) {
							if ($appf == $app) {
								$found=true;
								break;
							}else{
								$found=false;
							}
						}
					} else {
						$found = false;
					}

					if ($found) {
						$err=0;
						for ($c=0;$c<count($_SESSION[$app]);$c++) {
							$_SESSION[$app]=null;
							if (isset($_SESSION[$app][$c])) {
								$err++;
							}
						}
						if ($err==0) {
							return true;
						} else {
							return false;
						}
					}else{
						return false;
					}
				}  else return false;
			}
		}

		final function debugDisconnect($app) {
			$app = $this->miscURLClean($app);
			if ($this->cArray["debug"]) {
				if ($this->aArray["AutoSession"])
					@session_start();
				if (session_status() != PHP_SESSION_NONE) {
					if (isset($_SESSION["exengine-debugger-apps"]) && is_array($_SESSION["exengine-debugger-apps"])) {
						foreach ($_SESSION["exengine-debugger-apps"] as $appf) {
							if ($appf == $app) {
								$found=true;
								break;
							}else{
								$found=false;
							}
						}
					} else {
						$found = false;
					}
					if (!$found) {
						return false;
					}else{
						unset($_SESSION[$app]);
						return true;
					}
				}  else return false;
			}
		}

		final function debugCleanAll() {
			if ($this->cArray["debug"]) {
				if ($this->aArray["AutoSession"])
					@session_start();
				if (session_status() != PHP_SESSION_NONE) {
					if (isset($_SESSION["exengine-debugger-apps"])) {
						$apps = $_SESSION["exengine-debugger-apps"];
						foreach ($apps as $app) {
							unset($_SESSION[$app]);
						}
						unset($_SESSION["exengine-debugger-apps"]);
						if (!isset($_SESSION["exengine-debugger-apps"])) {
							return true;
						} else {
							return false;
						}
					} else {
						return true;
					}
				} else {
					return false;
				}
			} else return false;

		}

		final function debugCreateClient($useMa=false, $CreateSession=true) {
			$pee = &$this;
			$cd = true;
			if (!$useMa) {
				$eemaLegacyMode = true;
				include_once($this->miscGetResPath("full")."eema.php");
			}
			else
				include_once($this->miscGetResPath("full")."eema.php");
		}

		/* message agent client creator */
		final function maCreateClient($CreateSession=true) {
			$this->debugCreateClient(true);
		}

		#Misc Functions
		final function miscMessShow($message) {
			if (!$this->argsGet("SilentMode")) {
				print "<!-- " . $message . " -->\n";
			}
		}
		final function miscGetResPath($mode="full") {
			if ($mode == "full") {
				return $this->eePath."eefx/common/" ;
			} elseif ($mode == "http") {
				return $this->configGetParam("http_path")."eefx/common/" ;
			} else {
				$this->errorExit("ExEngine Paths Error [XC06]",'ExEngine 7 : Misc Functions : misGetResPath argument is invalid.<br/>');
			}
		}
		final static function miscPhpSelfNoPath($gArgs=false) {
			if (!$gArgs) {
				$a[0] = basename($_SERVER['SCRIPT_NAME']);
			} else {
				# From PowWeb Forum / B&T Support / http://forum.powweb.com/showthread.php?t=49016
				#this is not working...
				$a = null;
				preg_match('#(\w*)\.php(.)*#',$a,$_SERVER['SCRIPT_NAME']);
			}
			return $a[0];
		}
		final function miscGetFXPath() {
			return $this->eePath."eefx/";
		}
		final function miscDisableBrowserCache() {
			@header("Cache-Control: no-cache, must-revalidate");
			@header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		}
		final function miscGetJSNames() {
			$js[] = "basic.js";
			return $js;
		}
		final function miscUName() {
			return "ExEngine ".self::V_MAJOR.".".self::V_MINOR.".".self::V_BUILD." Rev. ".self::V_REVIS;
		}
		final function miscMessages($mss,$ret=0) {
			$ee7_string = "ExEngine";
			if ($this->msEnabled) {
				$extra = " (MuS)";
			} else {
				$extra = null;
			}
			switch ($mss) {
				case "Slogan":
					if ($this->cArray["debug"]) {
						$_versionString = $this->miscGetVersion();
						$str = "X-Powered by QOX ".$ee7_string.$extra."/".$_versionString." // DebugMode Enabled // https://github.com/QOXCorp/exengine";
						if ($ret==0)
							$this->miscMessShow($str);
					} else {
						$str= "X-Powered by QOX ".$ee7_string.$extra." // https://github.com/QOXCorp/exengine";
						if ($ret==0)
							$this->miscMessShow($str);
					}
					if ($ret==0)
						return true;
					else
						return $str;
					break;
				default:
					return false;
					break;
			}
		}
		final function miscGetVersion() {
			return 	self::V_MAJOR . "." . self::V_MINOR . "." . self::V_BUILD . "." . self::V_REVIS ;
		}
		final function miscURLClean($input) {
			#From hello at weblap dot ro found in http://www.php.net/manual/en/function.preg-replace.php.
			$a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
			$b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
			$rmAcc = str_replace($a, $b, $input);
			return strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),array('', '-', ''), $rmAcc));
		}

		final function miscReformatJSON($inputString)
		{
			$stringArray = explode("\\", $inputString);
			$newString = "";

			foreach($stringArray as $string) {

				$newString .= $string;
			}
			return $newString;
		}


		# Time Functions

		final function timeDiff( $start, $end )
		{
			# @author J de Silva  <giddomains@gmail.com> @copyright Copyright 2005, J de Silva

			$uts['start']      =    strtotime( $start );
			$uts['end']        =    strtotime( $end );
			if( $uts['start']!==-1 && $uts['end']!==-1 )
			{
				if( $uts['end'] >= $uts['start'] )
				{
					$diff    =    $uts['end'] - $uts['start'];
					if( $days=intval((floor($diff/86400))) )
						$diff = $diff % 86400;
					if( $hours=intval((floor($diff/3600))) )
						$diff = $diff % 3600;
					if( $minutes=intval((floor($diff/60))) )
						$diff = $diff % 60;
					$diff    =    intval( $diff );
					return( array('days'=>$days, 'hours'=>$hours, 'minutes'=>$minutes, 'seconds'=>$diff) );
				}
				else
				{
					trigger_error( "Ending date/time is earlier than the start date/time", E_USER_WARNING );
				}
			}
			else

			{
				trigger_error( "Invalid date/time data detected", E_USER_WARNING );
			}
			return( false );
		}
		final function timeDiffMinutes($start,$end) {
			$uts['start']      =    strtotime( $start );
			$uts['end']        =    strtotime( $end );
			if( $uts['start']!==-1 && $uts['end']!==-1 )
			{
				if( $uts['end'] >= $uts['start'] )
				{
					$diff    =    $uts['end'] - $uts['start'];
					$minutes=intval((floor($diff/60)));
					return( $minutes );
				}
				else
				{
					trigger_error( "Ending date/time is earlier than the start date/time", E_USER_WARNING );
				}
			}
			else
			{
				trigger_error( "Invalid date/time data detected", E_USER_WARNING );
			}
			return( false );
		}
		# String Functions
        /**
         * Returns true if $SubString is found in $String.
         *
         * @param string $String The haystack.
         * @param string $SubString The needle.
         * @param bool $CaseSensitive Enables case sensitive search.
         * @return bool
         */
		final function strContains($String,$SubString,$CaseSensitive=true) {
			if (!$CaseSensitive){
				$String = strtolower($String); $SubString = strtolower($SubString);
			}
			$pos = strpos($String,$SubString);
			if($pos === false) {
				return false;
			} else {
				return true;
			}
		}
		# Server Detection
		final function osCheck() {
			if ($this->strContains(PHP_OS,"Linux")) {
				return "linux";
			} else if ($this->strContains(PHP_OS,"Win")) {
				return "windows";
			} else {
				return "unknown";
			}
		}

		# File Functions
		final function fileExtension($FileNameString) {
			# http://davidwalsh.name/php-function-get-file-extension-string
			return substr(strrchr($FileNameString,'.'),1);
		}

		# Original PHP code by Chirp Internet: www.chirp.com.au // Please acknowledge use of this code by including this header.
		final function fileGetImagesFromDir($Directory) {
			$dir = $Directory;
			$imagetypes = $this->configGetParam("GIFD_ValidImages");
			//global $imagetypes;
			// array to hold return value
			$retval = array();
			// add trailing slash if missing
			if(substr($dir, -1) != "/") $dir .= "/";
			// full server path to directory
			$fulldir = $dir;
			$d = @dir($fulldir) or die("getImages: Failed opening directory $dir for reading");
			while(false !== ($entry = $d->read())) {
				// skip hidden files
				if($entry[0] == ".") continue;
				// check for image files
				$f = escapeshellarg("$fulldir$entry");
				$mimetype = trim(`file -bi $f`);
				foreach($imagetypes as $valid_type) {
					if(preg_match("@^{$valid_type}@", $mimetype)) {
						$retval[] = array( 'file' => "/$dir$entry", 'size' => getimagesize("$fulldir$entry") );
						break;
					}
				}
			}
			$d->close();
			return $retval;
		}

		/**
         * TagMode
         * (c) 2012 LinkFast Company Open Source
         *
         * TagMode is a View-Controller architecture for small web applications.
         * For bigger applications, consider using MVC-ExEngine.
         */
		private $TagTempFile;
		private $TagStringLoaded=false;
		private $TagStringsArr;

		final function tagLoadStringsFile($file,$type="XML",$name="default",$merge=false) {
			if (!isset($this->TagTempFile[$name]) || $merge) {
				if (file_exists($file)) {
					if ($type=="XML") {
						$doc = new DOMDocument();
						$doc->load( $file );
						$strings = $doc->getElementsByTagName( "string" );
						foreach( $strings as $string )
						{
							$strName = $string->getAttribute('name');
							$urlDec = $string->getAttribute('urldec');
							$strData = $string->nodeValue;
							if ($urlDec == "true") {
								$this->TagStringsArr[$name][$strName] = urldecode($strData);
							} else {
								$this->TagStringsArr[$name][$strName] = $strData;
							}
						}
						$this->TagStringLoaded = true;
					} elseif ($type=="PHP") {
						$Str = null;
						include_once($file);
						if (!isset($this->TagStringsArr[$name])) {
							$this->TagStringsArr[$name] = $Str;
						} elseif ($merge) {
							$this->TagStringsArr[$name] = array_merge($this->TagStringsArr[$name],$Str);
						}
						unset($Str);
						$this->TagStringLoaded = true;
					} else {
						$this->errorExit("tagLoadStringsFile","Unsupported format.");
					}
				}else {
					$this->errorExit("tagLoadStringsFile","File not found.");
				}
			} else {
				$this->errorExit("tagLoadStringsFile","Strings file already loaded with that name, you can set another name for more than one strings file.");
			}
		}

		final function tagGetString($strName,$name="default") {
			if ($this->TagStringLoaded) {
				if (array_key_exists($strName,$this->TagStringsArr[$name])) {
					return $this->TagStringsArr[$name][$strName];
				} else {
					return "tagGetString : Error : String name not found in array.";
				}
			} else {
				$this->errorExit("tagLoadStringsFile","Strings XML/PHP file is not loaded.");
			}
		}

		final function tagGetStrings($name="default") {
			if ($this->TagStringLoaded) {
				if (array_key_exists($name,$this->TagStringsArr)) {
					return $this->TagStringsArr[$name];
				}
			} else {
				$this->errorExit("tagLoadStringsFile","Strings XML/PHP file is not loaded.");
			}
		}

		final function tagLoad($name,$file,$strReplace=false,$stringsfile="default") {
			$namex=$stringsfile;
			if (file_exists($file)) {
				$this->TagTempFile[$name] = file_get_contents($file);
				if ($this->TagStringLoaded && $strReplace) {
					//Replace ^VALUE^ with TagStringArr data.
					$getConstRegex = "/\^[\w]*\^/";
					$refConstRegex = "/[\w]+/";
					preg_match_all($getConstRegex,$this->TagTempFile[$name], $matches);
					//print_r($matches);
					foreach ($matches[0] as $match) {
						preg_match($refConstRegex,$match, $refMatches);
						//print $match." . " . $refMatches[0];
						if (array_key_exists($refMatches[0],$this->TagStringsArr[$namex])) {
							$this->TagTempFile[$name] = str_replace($match,$this->TagStringsArr[$namex][$refMatches[0]],$this->TagTempFile[$name]);
						}
					}
				}
			} else {
				$this->errorExit("tagLoad","File not found.");
			}
		}

		final function tagReplace($name,$tag,$data) {
			$this->TagTempFile[$name] = str_replace("[EE7-".$tag."]", $data, $this->TagTempFile[$name]);
		}

		final function tagGetOld($name) {
			return $this->TagTempFile[$name];
		}

		final function tagGet($name,$clean=true) {
			if ($clean) {
				$getConstRegex = "/\[EE7-[\w]*\]/";
				preg_match_all($getConstRegex,$this->TagTempFile[$name], $matches);
				foreach ($matches[0] as $match) {
					$this->TagTempFile[$name] = str_replace($match,"",$this->TagTempFile[$name]);
				}
			}
			return $this->TagTempFile[$name];
		}

		final function tagShowOld($name) {
			print $this->TagTempFile[$name];
		}

		final function tagShow($name,$clean=true) {
			if ($clean) {
				$getConstRegex = "/\[EE7-[\w]*\]/";
				preg_match_all($getConstRegex,$this->TagTempFile[$name], $matches);
				foreach ($matches[0] as $match) {
					$this->TagTempFile[$name] = str_replace($match,"",$this->TagTempFile[$name]);
				}
			}
			print $this->TagTempFile[$name];
		}

        /* class functions */
        final function classGetRealName($Object) {
            $classname = get_class($Object);
            if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
                $classname = $matches[1];
            }
            return $classname;
        }
	}
}

namespace {
	/* legacy support */
	class exengine extends \ExEngine\Core {

	}
	function &ee_gi()
	{
		if(!\ExEngine\Core::get_instance()) {
			die('ExEngine not instantiated. Cannot continue.');
		} else
			return \ExEngine\Core::get_instance();
	}
	//Prevent from non-include access
	if (@\ExEngine\Core::miscPhpSelfNoPath() == "ee.php") {
		header("Location: eefx/?from=ee.php");
	}
}

?>