<?php
// Add to crontab
// Replace {PROJECT_HOME} by path of your project
// * * * * * /usr/bin/php {PROJECT_HOME}/changecronconsole.php 2>&1 >> {PROJECT_HOME}/log/project/crontab.log

define('PROJECT_HOME', dirname(realpath(__FILE__)));
if (file_exists(PROJECT_HOME."/site_is_disabled"))
{
	exit(0);
}

chdir(PROJECT_HOME);
require_once PROJECT_HOME . "/framework/Framework.php";
Framework::initialize();

if (isset($_SERVER['REMOTE_ADDR']))
{
	Framework::info(__FILE__ . ' Invalid call');
	exit(0);
}

if (!defined('CHANGECRON_EXECUTION') || CHANGECRON_EXECUTION != 'console')
{
	Framework::info(__FILE__ . ' Disabled');
	exit(0);
}

RequestContext::getInstance()->setMode(RequestContext::BACKOFFICE_MODE);

if (defined('NODE_NAME') && ModuleService::getInstance()->moduleExists('clustersafe'))
{
	$node = clustersafe_WebnodeService::getInstance()->getCurrentNode();
	if ($node === null || !$node->isPublished()) 
	{
		Framework::info('Node deactivated cron stoped.');
		exit(0);
	}
}

$taskId = (isset($arguments) && is_numeric($arguments[0])) ? intval($arguments[0]) : null;
if ($taskId !== null)
{
	$runnableTask = DocumentHelper::getDocumentInstanceIfExists($taskId);
	if ($runnableTask instanceof task_persistentdocument_plannedtask)
	{
		try
		{
			task_PlannedTaskRunner::executeSystemTask($runnableTask);
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			exit(1);
		}
	}
	exit();
}

$taskService = task_PlannedtaskService::getInstance();
foreach ($taskService->getTasksToAutoUnlock() as $task) 
{
	/* @var $task task_persistentdocument_plannedtask */
	$taskService->autoUnlock($task);
}

foreach ($taskService->getTasksToLock() as $task) 
{
	/* @var $task task_persistentdocument_plannedtask */
	$taskService->lock($task);
}

//Clean task instance
f_persistentdocument_PersistentProvider::getInstance()->setDocumentCache(false);
$start = time();
$relativeScriptPath = 'changecronconsole.php';
foreach ($taskService->getTasksToStartIds() as $taskId)
{
	/* @var $task task_persistentdocument_plannedtask */
	$task = task_persistentdocument_plannedtask::getInstanceById($taskId);
	if (in_array($task->getExecutionStatus(), array(task_PlannedtaskService::STATUS_SUCCESS, task_PlannedtaskService::STATUS_FAILED)))
	{
		$taskService->start($task);
		echo gmdate('Y-m-d H:i:s'), "\t" , $taskId , "\t", $task->getLabel(), "\t", 'Start', PHP_EOL;
		$result = f_util_System::execScript($relativeScriptPath, array($taskId), true);
		if ($result === false)
		{
			echo gmdate('Y-m-d H:i:s'), "\t" , $taskId , "\t", $task->getLabel(), "\tEnd Error", PHP_EOL;
		}
		else
		{
			echo gmdate('Y-m-d H:i:s'), "\t" , $taskId , "\t", $task->getLabel(), "\tEnd Succes", PHP_EOL;
		}
	}
	else
	{
		Framework::info($task->getLabel() . ' '. $taskId . ' Already running');
	}
	
	if (time() - $start > 60) 
	{
		Framework::info('Stop changecronconsole after 60s');
		break;
	}
}