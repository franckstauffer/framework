<?php
/**
 * @package framework.service
 * @method change_InjectionService getInstance()
 */
class change_InjectionService extends change_Singleton
{
	/**
	 * @var array
	 */
	private $infos;
	
	/**
	 * Get the array containing all the injection related informations 
	 * 
	 * @return array 
	 */
	public function getInfos()
	{
		if ($this->infos === null)
		{
			$path = f_util_FileUtils::buildProjectPath('build', 'injection', 'info.ser');
			if (file_exists($path))
			{
			   $this->infos = unserialize(file_get_contents($path));
			}
			else 
			{
				$this->infos = array();
			}
		}
		return $this->infos; 
	}
	
	/**
	 * This method will update the injection only if needed. If you want to force update, you should call compile (or first restore and compile).
	 */
	public function update()
	{
		// Check if injection is up to date
		$injectionInfos = $this->getInfos();
		$injectionConfig = Framework::getConfigurationValue('injection/class', array());
		$recompile = false;
		foreach ($injectionInfos as $className => $value) 
		{
			if (isset($value['checkmtime']))
			{
				$fileInfo = new SplFileInfo($value['path']);
				if ($fileInfo->getMTime() != $value['mtime'])
				{
					$recompile = true;
					break;
				}
			}
		}
		if ($recompile)
		{
			Framework::info('Injections are not up-to-date : recompiling informations');
			$this->restore();
			$this->compile(false);
		}
	}
	
	/**
	 * Reset the array  containing all the injection related informations 
	 */
	public function resetInfos()
	{
		$path = f_util_FileUtils::buildProjectPath('build', 'injection', 'info.ser');
		if (file_exists($path))
		{
			@unlink($path);
		}
		$this->infos = null;
	}
	
	public function restore()
	{
		foreach ($this->getInfos() as $className => $info)
		{
			$autoloadClassPath = change_AutoloadBuilder::getInstance()->buildLinkPathByClass($className);
			if ($autoloadClassPath !== false && isset($info['path']))
			{
				@unlink($autoloadClassPath);
				f_util_FileUtils::symlink($info['path'], $autoloadClassPath);
			}  
		}
	}
	
	/**
	 * @param array $toRecompile
	 * @param boolean $checkValidity
	 * @return array 
	 */
	public function compile($checkValidity = true)
	{
		$returnValue = array();
		$newInjectionInfos = array();

		foreach (Framework::getConfigurationValue('injection/document', array()) as $originalModelName => $replacingModelName)
		{
			$docInject = new change_DocumentInjection($originalModelName, $replacingModelName);
			if (!$checkValidity || ($checkValidity && $docInject->isValid()))
			{
				$newInjectionInfos = array_merge($newInjectionInfos, $docInject->generate());
				$returnValue[$originalModelName] = $replacingModelName;
			}
		}
		
		$this->infos = $newInjectionInfos;
				
		foreach (Framework::getConfigurationValue('injection/class', array()) as $originalClassName => $classNames)
		{
			$originalClassInfo = $this->buildClassInfo($originalClassName);
			
			$replacingClassInfos = array();
			foreach (explode(',', $classNames) as $className)
			{
				$className = trim($className);
				if (empty($className)) {continue;}
				$replacingClassInfos[] = $this->buildClassInfo($className);
			}
			
			if (count($replacingClassInfos) === 0) {continue;}
			$injection = new change_Injection($originalClassInfo, $replacingClassInfos);
			if (!$checkValidity || ($checkValidity && $injection->isValid()))
			{
				$newInjectionInfos = array_merge($newInjectionInfos, $injection->generate());
				$returnValue[$originalClassName] = $classNames; 
			}
			else
			{
				Framework::error('Invalid Injection of ' . $originalClassName . ' by ' . $classNames);
			}
		}
		
		$this->setInfos($newInjectionInfos);
		
		foreach ($newInjectionInfos as $className => $info)
		{
			$autoloadClassPath = change_AutoloadBuilder::getInstance()->buildLinkPathByClass($className);
			if ($autoloadClassPath !== false || isset($info['link']))
			{
				@unlink($autoloadClassPath);
				f_util_FileUtils::symlink($info['link'], $autoloadClassPath);
			}  
		}
	
		return $returnValue;
	}
	
	/**
	 * @param type $className
	 * @return type 
	 */
	private function buildClassInfo($className)
	{
		$infos = $this->getInfos();
		if (isset($infos[$className]) && file_exists($infos[$className]['path']))
		{
			return array('name' => $className, 'path' => $infos[$className]['path']);
		}
		return array('name' => $className, 'path' => realpath(change_AutoloadBuilder::getInstance()->buildLinkPathByClass($className)));
	}
	
	/**
	 * @param array $infos
	 */
	protected function setInfos($infos)
	{
		$this->infos = $infos;
		f_util_FileUtils::writeAndCreateContainer(f_util_FileUtils::buildProjectPath('build', 'injection', 'info.ser'), serialize($this->infos), f_util_FileUtils::OVERRIDE);
	}
}