<?php
/**
 * @package framework.validation
 */
class validation_PasswordValidator extends validation_ValidatorImpl implements validation_Validator
{
	const SECURITY_LEVEL_LOW = 'low';
	const SECURITY_LEVEL_MEDIUM = 'medium';
	const SECURITY_LEVEL_HIGH = 'high';
	const SECURITY_LEVEL_NONE = false;
	
	private $securityLevel = null;
	

	private function getSecurityLevel()
	{
		if ($this->securityLevel === null)
		{
			$securityLevel = $this->getParameter();
			if ((is_bool($securityLevel) && $securityLevel) || empty($securityLevel) || !in_array($securityLevel, array('low', 'medium', 'high')))
			{
				$usersPreference = ModuleService::getInstance()->getPreferencesDocument('users');
				if ($usersPreference !== null && f_util_ClassUtils::methodExists($usersPreference, 'getSecuritylevel'))
				{
					$securityLevel = $usersPreference->getSecuritylevel();
					if (empty($securityLevel))
					{
						$securityLevel = self::SECURITY_LEVEL_NONE;
					}
				}
			}
			$this->securityLevel = $securityLevel;
		}
		return $this->securityLevel;
	}
	
	/**
	 * Returns the error message.
	 *
	 * @return string
	 */
	protected function getMessage()
	{
		$code = $this->getMessageCode();
		
		if ($this->getSecurityLevel())
		{
			$code = str_replace('.Message;', '.Message.' . ucfirst($this->getSecurityLevel()) . ';', $code);
		}
		return f_Locale::translate($code, array('param' => $this->getParameter()));
	}
	

	/**
	 * Validate $data and append error message in $errors.
	 *
	 * @param validation_Property $Field
	 * @param validation_Errors $errors
	 * 
	 * @return void
	 */
	protected function doValidate(validation_Property $field, validation_Errors $errors)
	{
		$securityLevel = $this->getSecurityLevel();
		switch ($securityLevel)
		{
			case self::SECURITY_LEVEL_LOW:
				$validate = $this->doValidateLow($field->getValue());
				break;
			case self::SECURITY_LEVEL_MEDIUM:
				$validate = $this->doValidateMedium($field->getValue());
				break;
			case self::SECURITY_LEVEL_HIGH:
				$validate = $this->doValidateHigh($field->getValue());
				break;
			case self::SECURITY_LEVEL_NONE:
				$validate = true;
				break;
			default:
				throw new ValidatorConfigurationException(__CLASS__ . ' must have a valid parameter: value must be "' . self::SECURITY_LEVEL_LOW . '", "' . self::SECURITY_LEVEL_MEDIUM . '" or "' . self::SECURITY_LEVEL_HIGH . '"');
		}
		if (!$validate)
		{
			$this->reject($field->getName(), $errors);
		}
	}
	

	/**
	 * Password check: 6 chars min 
	 * Security level: low.
	 *
	 * @param string $password
	 * @return boolean
	 */
	private function doValidateLow($password)
	{
		$passwordLength = f_util_StringUtils::strlen($password);
		return $passwordLength >= 6;
	}
	

	/**
	 * Password check: 6 chars min and 12 chars max with letters and digits.
	 * Security level: medium.
	 *
	 * @param string $password
	 * @return boolean
	 */
	private function doValidateMedium($password)
	{
		return true && $this->doValidateLow($password) && f_util_StringUtils::containsLetter($password) && f_util_StringUtils::containsDigit($password);
	}
	

	/**
	 * Password check: 6 chars min and 12 chars max with uppercased letters, lowercased letters and digits.
	 * Security level: high.
	 *
	 * @param string $password
	 * @return boolean
	 */
	private function doValidateHigh($password)
	{
		return true && $this->doValidateLow($password) && f_util_StringUtils::containsUppercasedLetter($password) && f_util_StringUtils::containsLowercasedLetter($password) && f_util_StringUtils::containsDigit($password);
	}
}