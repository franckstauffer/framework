<?php
class commands_CompilePermissions extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	function getUsage()
	{
		return "";
	}
	
	function getAlias()
	{
		return "cperm";
	}

	/**
	 * @return string
	 */
	function getDescription()
	{
		return "compile user permissions";
	}

	/**
	 * @see c_ChangescriptCommand::getEvents()
	 */
	public function getEvents()
	{
		return array(
			array('target' => 'compile-roles'),
		);
	}
	
	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Compile permissions ==");
		
		$this->loadFramework();
		f_persistentdocument_PersistentProvider::getInstance()->clearAllPermissions();
		$ps = change_PermissionService::getInstance();
		$ps->compileAllPermissions();
		
		$this->quitOk("Permissions compiled");
	}
}