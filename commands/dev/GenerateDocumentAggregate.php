<?php
class commands_GenerateDocumentAggregate extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	function getUsage()
	{
		$usage = "<outputModuleName> <outputClassName>  modelNames [options]\n";
		$usage .= "where options in:";
		$usage .= "  --override: override the output file if it exists \n";
		return $usage;
	}
	
	/**
	 * @return string
	 */
	function getDescription()
	{
		return "aggregate several documents into one single bean";
	}
	
	/**
	 * @return string[]
	 */
	function getOptions()
	{
		return array("--override");
	}
	
	function getParameters($completeParamCount, $params, $options, $current)
	{
		if ($completeParamCount == 0)
		{
			$this->loadFramework();
			return array_keys(ModuleService::getInstance()->getModulesObj());
		}
		if ($completeParamCount > 1)
		{
			$this->loadFramework();
			return $this->getModelNames();
		}
		return array();
	}
	
	private function getModelNames()
	{
		$names = array();
		foreach (f_persistentdocument_PersistentDocumentModel::getDocumentModelNamesByModules() as $_names)
		{
			$names = array_merge($names, $_names);
		}
		return $names;
	}
	
	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		return count($params) > 3;
	}
	
	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->loadFramework();
		$shortModuleName = $params[0];
		$beanClassName = $shortModuleName . '_' . $params[1];
		$models = array_slice($params, 2);
		$classes = array();
		foreach ($models as $modelName)
		{
			if (!f_util_ClassUtils::classExists($modelName))
			{
				$model = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($modelName);
				$className = $model->getModuleName() . '_persistentdocument_' . $model->getDocumentName();
				if (!f_util_ClassUtils::classExists($className))
				{
					return $this->quitError("$className does not exist!\n");
				}
				$classes[] = $className;
			}
		}
		
		$moduleLibDir = f_util_FileUtils::buildModulesPath($shortModuleName, 'lib');
		
		if (!f_util_FileUtils::isDirectoryWritable(dirname($moduleLibDir)))
		{
			return $this->quitError('directory ' . dirname($moduleLibDir) . ' is not writable');
		}
		
		$moduleBeanPath = f_util_FileUtils::buildPath($moduleLibDir, 'beans', $params[1] . '.php');
		if (file_exists($moduleBeanPath))
		{
			$this->warnMessage("$moduleBeanPath already exists.\n");
			if (isset($options['override']) && $options['override'] == true && $this->yesNo("Are you sure you want to override $moduleBeanPath ?"))
			{
				f_util_FileUtils::unlink($moduleBeanPath);
			}
			else
			{
				return $this->quitError("Use --override if you want to override the file.\n");
			}
		}
		
		bean_BeanAggregateGenerator::generate($moduleBeanPath, $beanClassName, $classes);
		$this->message("adding $beanClassName to autoload\n");
		change_AutoloadBuilder::getInstance()->appendFile($moduleBeanPath);

		return $this->quitOk($moduleBeanPath." file ready");
	}
}