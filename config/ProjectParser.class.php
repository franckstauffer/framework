<?php
/**
 * @package framework.config
 * Project parser is used to converted project.XX.xml file in php file useable by the framework
 */
class config_ProjectParser
{
	/**
	 * Merge specific config file of project with defulat config file and save config file in cache/config
	 * @return array old and current configuration
	 */
	public function execute($computedDeps)
	{
		// Config dir.
		$configDir = WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'config';
		if (!is_dir($configDir))
		{
			return;
		}

		// Cache config dir.
		$cacheConfigDir = WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'config';
		
		$currentProfile = (defined("PROFILE") ? PROFILE : trim(file_get_contents(WEBEDIT_HOME."/profile")));
		$cacheFile = $cacheConfigDir."/project.".$currentProfile.".xml.php";
		$oldConfig = null;
		$oldDefines = array();
		if (is_file($cacheFile))
		{
			$lines = file($cacheFile, FILE_IGNORE_NEW_LINES);
			if ($lines !== false)
			{
				unset($lines[0]);
				foreach ($lines as $lineIndex => $line)
				{
					$matches = null;
					if (preg_match('/^define\(\'(.*)\', (.*)\);$/', $line, $matches))
					{
						$lines[$lineIndex] = '$oldDefines[\'' .$matches[1] .'\'] = \''.str_replace("'", "\\'", $matches[2]).'\';';
					}
					elseif (substr($line, 0, 20) == 'Framework::$config =') 
					{
						$lines[$lineIndex] = '$oldConfig ='.substr($line, 20);
					}
				}
				$oldConfigCode = join("\n", $lines);
				eval($oldConfigCode);
			}
		}

		// Config Dir for over write.
		$fileList = scandir($configDir);

		// Load Xml file of default configuration file.
		$defaultXmlFile = simplexml_load_file(WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'project.xml');
		$projectXmlPath = WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'project.xml';
		if (file_exists($projectXmlPath))
		{
			$projectXmlFile = simplexml_load_file($projectXmlPath);
		}
		else
		{
			$projectXmlFile = null;
		}

		// -- Global constants.
		$globalConstants = array();
		if (isset($computedDeps["PEAR_DIR"]))
		{
			$this->addConstant($globalConstants, "PEAR_DIR", $computedDeps["PEAR_DIR"]);
		}
		if (isset($computedDeps["LOCAL_REPOSITORY"]))
		{
			$this->addConstant($globalConstants, "LOCAL_REPOSITORY", $computedDeps["LOCAL_REPOSITORY"]);
		}
		if (isset($computedDeps["WWW_GROUP"]))
		{
			$this->addConstant($globalConstants, "WWW_GROUP", $computedDeps["WWW_GROUP"]);
		}
		if (isset($computedDeps["OUTGOING_HTTP_PROXY_HOST"]))
		{
			$this->addConstant($globalConstants, "OUTGOING_HTTP_PROXY_HOST", $computedDeps["OUTGOING_HTTP_PROXY_HOST"]);
			$this->addConstant($globalConstants, "OUTGOING_HTTP_PROXY_PORT", $computedDeps["OUTGOING_HTTP_PROXY_PORT"]);
		}
	
		// Framework default.
		$this->constructDefineList($defaultXmlFile->defines, $globalConstants);

		// project.xml file.
		if ($projectXmlFile !== null)
		{
			$this->constructDefineList($projectXmlFile->defines, $globalConstants);
		}
		
		// -- Modules informations.
		$packagesVersion = $this->compilePackageVersion();
		list($moduleContants, $moduleXmlFiles, $packagesVersion) = $this->compileModulesConfig($packagesVersion);
		
		// Injections
		$docInjections = $this->searchForDocInjections();

		foreach ($fileList as $profilFile)
		{
			if (!is_dir($configDir . DIRECTORY_SEPARATOR . $profilFile) && $profilFile == "project.".$currentProfile.".xml")
			{
				$profileName = substr(basename($profilFile), 8, -4);
				// Create array for manage configuration.
				$configList = array('packageversion' => $packagesVersion);

				// Load xml file.
				$xmlFile = simplexml_load_file($configDir . DIRECTORY_SEPARATOR . $profilFile);

				// -- Global constants.
				// project.<profile>.xml file.
				$globalConstantsForProfile = $globalConstants;
				$this->constructDefineList($xmlFile->defines, $globalConstantsForProfile);
				
				if (!isset($globalConstantsForProfile["TMP_PATH"]))
				{
					if (function_exists('sys_get_temp_dir'))
					{
						$TMP_PATH = sys_get_temp_dir();
					}
					else
					{
						$tmpfile = @tempnam(null, 'loc_');
						if ($tmpfile)
						{
							$TMP_PATH = dirname($tmpfile);
							@unlink($tmpfile);
						}
						else  if (DIRECTORY_SEPARATOR === '\\')
						{
							if (isset($_ENV['TMP']))
							{
								$TMP_PATH = $_ENV['TMP'];
							} 
							else if (isset($_ENV['TEMP']))
							{
								$TMP_PATH = $_ENV['TEMP'];
							}
							else 
							{
								throw new Exception('Please define TMP_PATH in project.xml config file');
							}
						}
						else
						{
							$TMP_PATH ='/tmp';
						}
					}
					$globalConstantsForProfile["TMP_PATH"] = 'define(\'TMP_PATH\', '. var_export($TMP_PATH, true).');';
				}

				// Remove module constants already set in global ones.
				$moduleContantsForProfile = $moduleContants;
				foreach (array_keys($globalConstantsForProfile) as $key)
				{
					if (isset($moduleContantsForProfile[$key]))
					{
						echo "unset module constant $key\n";
						unset($moduleContantsForProfile[$key]);
					}
				}
				
				if (isset($computedDeps["DEVELOPMENT_MODE"]) && $computedDeps["DEVELOPMENT_MODE"])
				{
					$this->addConstant($globalConstantsForProfile, "AG_DEVELOPMENT_MODE", "true");
				}
				

				// -- Config list.
				// project.<profile>.xml file.
				$this->constructConfigList($xmlFile->config, $configList);

				// project.xml file.
				if ($projectXmlFile !== null)
				{
					$this->constructConfigList($projectXmlFile->config, $configList);
				}
				
				if (count($docInjections) > 0)
				{
					if (!isset($configList['injection']))
					{
						$configList['injection'] = array();
					}
					if (!isset($configList['injection']['document']))
					{
						$configList['injection']['document'] = array();
					}
					foreach ($docInjections as $replaced => $injection)
					{
						$configList['injection']['document'][$replaced] = $injection;
					}
				}

				// module.xml files.
				foreach ($moduleXmlFiles as $module => $moduleXmlFile)
				{
					if ($moduleXmlFile->project)
					{
						if (!isset($configList['modules']))
						{
							$configList['modules'] = array();
						}
						if (!isset($configList['modules'][$module]))
						{
							$configList['modules'][$module] = array();
						}
						$this->constructConfigList($moduleXmlFile->project, $configList['modules'][$module]);
					}
					if ($moduleXmlFile->projectconfig)
					{
						$this->constructConfigList($moduleXmlFile->projectconfig, $configList);
					}
				}

				// Framework default.
				$this->constructConfigList($defaultXmlFile->config, $configList);

				// Browsers generic project.
				if ($projectXmlFile !== null)
				{
					$this->constructBrowsersList($projectXmlFile->browsers, $configList);
				}

				// Browsers Default.
				$this->constructBrowsersList($defaultXmlFile->browsers, $configList);

				// Write in file
				$content = "<?php // File auto generated by ProjectParser.";
				$content .= "\n // GLOBAL DEFINE PART // \n";
				$content .= implode("\n", $globalConstantsForProfile);
				$content .= "\n // MODULES DEFINE PART // \n";
				$content .= implode("\n", $moduleContantsForProfile);
				$content .= "\n // Framework::\$config PART // \n";
				$content .= "Framework::\$config = " . var_export($configList, true) . ';';
				
				if ($oldConfig !== null && $profileName == $currentProfile)
				{
					$currentDefines = array();
					foreach (array_merge($globalConstantsForProfile, $moduleContantsForProfile) as $defineLine)
					{
						$matches = null;
						if (preg_match('/^define\(\'(.*)\', (.*)\);$/', $defineLine, $matches))
						{
							$currentDefines[$matches[1]] = $matches[2];
						}
					}
					$currentConfig = array("config" => $configList, "defines" => $currentDefines);
				}
				
				$this->writeFile($cacheConfigDir . DIRECTORY_SEPARATOR . $profilFile . '.php', $content);
			}
		}
		return ($oldConfig !== null) ? array("old" => array("defines" => $oldDefines, "config" => $oldConfig), "current" => $currentConfig) : null;
	}

	private function writeFile($path, $content)
	{
		$dir = dirname($path);
		if (!is_dir($dir))
		{
			if (mkdir($dir, 0777, true) === false)
			{
				throw new Exception("Could not create directory $dir");
			}
		}
		if (file_put_contents($path, $content) === false)
		{
			throw new Exception("Could not write file $path");
		}
	}

	/**
	 * Make an array with all define string to write in file
	 *
	 * @param SimpleXmlElement $simpleXmlList
	 * @param Array $globalConstants
	 */
	private function constructDefineList($simpleXmlList, &$globalConstants)
	{
		// Here are some defines we can find in the file we are parsing:
		//
		// With a single quote:
		//   <define name="AG_WEBAPP_NAME">Fred's Change</define>
		// With constants inside:
		//   <define name="WEBAPP_HOME">WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'webapp'</define>
		// With version number:
		//   <define name="FRAMEWORK_VERSION">2.0.1</define>
		// With a boolean value:
		//   <define name="AG_DEVELOPMENT_MODE">false</define>
		foreach ($simpleXmlList->define as $define)
		{
			$name = (string) $define['name'];
			$value = trim((string) $define);
			
			$this->addConstant($globalConstants, $name, $value);
		}
	}
	
	private function addConstant(&$globalConstants, $name, $value)
	{
		// All numeric and boolean values are taken as is, without adding any quote arround.
			if (!is_numeric($value) && $value != "true" && $value != "false")
			{
				$quoteCount = substr_count($value, "'");
				// For strings, I quote the ones that contain an odd number of single quotes.
				// This generally means that the developper really wanted to use a single quote.
				// For example:
				// <define name="AG_WEBAPP_NAME">Fred's Change</define>
				if ($quoteCount == 0 || ($quoteCount & 1))
				{
					$value = var_export($value, true);
				}
			}
			$globalConstants[$name] = 'define(\'' . $name . '\', ' . $value . ');';
	}

	/**
	 * Make and array of configuration
	 *
	 * @param SimpleXmlElement $simpleXml
	 * @param array $array
	 */
	private function constructConfigList($simpleXml, &$array)
	{
		foreach ($simpleXml->children() as $name => $children)
		{
			if ($name != 'entry')
			{
				if (!isset($array[$name]))
				{
					$array[$name] = array();
				}
				// Call recursively
				$this->constructConfigList($children, $array[$name]);
			}
			else
			{
				// Construct a final array with properties
				foreach ($simpleXml->entry as $entry)
				{
					$nameEntry = (string) $entry['name'];
					$valueEntry = trim((string) $entry);

					if (!isset($array[$nameEntry]))
					{
						if (strpos($valueEntry, ';'))
						{
							$valueEntry = explode(';', $valueEntry);
						}

						$array[$nameEntry] = $valueEntry;
					}
				}
				break;
			}
		}
	}

	/**
	 * @param SimpleXmlElement $simpleXml
	 * @param array $array
	 */
	private function constructBrowsersList($simpleXml, &$array)
	{
		if (!isset($array['browsers']))
		{
			$array['browsers'] = array('frontoffice' => array(), 'backoffice' => array());
		}

		foreach ($simpleXml->children() as $access => $children)
		{
			if ($access == 'frontoffice')
			{
				$this->constructBrowsersAccessList($children, $array['browsers']['frontoffice']);
			}
			else if ($access == 'backoffice')
			{
				$this->constructBrowsersAccessList($children, $array['browsers']['backoffice']);
			}
		}
	}

	/**
	 * @param SimpleXmlElement $simpleXml
	 * @param array $array
	 */
	private function constructBrowsersAccessList($simpleXml, &$array)
	{
		if ($simpleXml === null)
		{
			return;
		}

		foreach ($simpleXml->browser as $browser)
		{
			$browserName = (string) $browser['name'];
			$versions = array();

			foreach ($browser->version as $version)
			{
				$versions[] = trim((string) $version);
			}

			$array[$browserName] = $versions;
		}
	}

	/**
	 * @return Array<String, String>
	 */
	private function compilePackageVersion()
	{
		$packagesVersion = array();
		$files = glob(WEBEDIT_HOME . '/modules/*/change.xml');
		if (!is_array($files) || count($files) == 0)
		{
			return $packagesVersion;
		}
		foreach ($files as $changeXmlFile)
		{
			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->load($changeXmlFile);
			if ($doc->documentElement)
			{
				$name = null; $version =null;
				foreach ($doc->documentElement->childNodes as $node) 
				{
					if ($node->nodeName == 'name')
					{
						$name = trim($node->textContent);
					}
					elseif ($node->nodeName == 'version')
					{
						$version = trim($node->textContent);
					}
				}
				if ($name && $version)
				{
					$packagesVersion['modules_' . $name] = $version;
				}
				continue;
			}
			$packagesVersion['modules_' . basename(dirname($changeXmlFile))] = null;			
		}
		return $packagesVersion;
	}

	/**
	 * @return Array
	 */
	private function compileModulesConfig($modulestates)
	{
		$constants = array();
		$moduleXmlFiles = array();		
		$files = glob(WEBEDIT_HOME . '/modules/*/config/module.xml');
		if (is_array($files) || count($files) > 0)
		{
			foreach ($files as $moduleXmlFile)
			{
				if (!is_readable($moduleXmlFile)) {continue;}
				$ini = array();
				$moduleName = basename(dirname(dirname($moduleXmlFile)));
				$ini = $this->parseModuleXmlConfig($moduleXmlFile, $ini);
				$moduleXmlFiles[$moduleName] = simplexml_load_file($moduleXmlFile);
				$moduleXmlOverrideFile = implode(DIRECTORY_SEPARATOR, array(WEBEDIT_HOME, 'override', 'modules', $moduleName, 'config', 'module.xml'));
				if (is_readable($moduleXmlOverrideFile))
				{
					$ini = $this->parseModuleXmlConfig($moduleXmlOverrideFile, $ini);
					$moduleXmlFiles[$moduleName] = simplexml_load_file($moduleXmlOverrideFile);
				}
				if (isset($ini['module']))
				{
					$pname = 'modules_'.$moduleName;
					$version = isset($modulestates[$pname]) ? $modulestates[$pname] : null;
					$modulestates[$pname] = array('ENABLED' => true, 'VISIBLE' => true, 'CATEGORY' => null, 'ICON' => 'package', 'USETOPIC' => false, 'VERSION' => $version);
					$upperModulename = strtoupper($moduleName);
					foreach ($ini['module'] as $key => $value)
					{
						switch ($key) {
							case 'ENABLED':
							case 'VISIBLE':
							case 'CATEGORY':
							case 'ICON':
							case 'USETOPIC':
								if ($value === 'true')
								{
									$value = true;
								}
								else if ($value === 'false')
								{
									$value = false;
								}
								$modulestates[$pname][$key] = $value;
								break;
							default:
								$value = $this->literalize($value);
								$constantName = 'MOD_' . $upperModulename . '_' . $key;
								$constants[$constantName] = 'define(\''.$constantName.'\', '.$value.');';
							break;
						}
					}
				}
			}
		}
		return array($constants, $moduleXmlFiles, $modulestates);
	}
	
	private function searchForDocInjections()
	{
		$injections = array();
		foreach (glob(WEBEDIT_HOME . '/modules/*/persistentdocument/*.xml') as $docFile)
		{
			$doc = f_util_DOMUtils::fromPath($docFile);
			$root = $doc->documentElement;
			if ($root->hasAttribute("inject") && $root->getAttribute("inject") == "true")
			{
				$extend = $root->getAttribute("extend");
				$matches = null;
				if (!preg_match('/^modules_(.*)\/(.*)$/', $extend, $matches))
				{
					echo "Warn: bad attribute extend for $docFile\n";
					continue;
				}
				$docName = basename($docFile, ".xml");
				$moduleName = basename(dirname(dirname($docFile)));
				$injections[$matches[1]."/".$matches[2]] = $moduleName."/".$docName;
			}
		}
		return $injections;
	}

	private function parseModuleXmlConfig($configFilepath, $ini)
	{
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->load($configFilepath);
		if ($doc->documentElement)
		{
			foreach ($doc->documentElement->childNodes as $node)
			{
				if ($node->nodeType !== XML_ELEMENT_NODE) {continue;}
				$tagName = $node->nodeName;
				if (!isset($ini[$tagName])) {$ini[$tagName] = array();}
				foreach ($node->childNodes as $item)
				{
					if ($item->nodeType !== XML_ELEMENT_NODE) {continue;}
					$ini[$tagName][strtoupper($item->nodeName)] = trim($item->textContent);
				}
			}
		}
		return $ini;
	}

	private function literalize($value)
	{
		if ($value == null)
		{
			return 'null';
		}

		// lowercase our value for comparison
		$value = trim($value);
		$lvalue = strtolower($value);

		if ($lvalue == 'on' || $lvalue == 'yes' || $lvalue == 'true')
		{
			return 'true';
		}
		else if ($lvalue == 'off' || $lvalue == 'no' || $lvalue == 'false')
		{
			return 'false';
		}
		else if (!is_numeric($value))
		{
			return "'" . $value . "'";
		}
		return $value;
	}
}
