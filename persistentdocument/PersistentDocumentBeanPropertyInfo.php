<?php
class f_persistentdocument_PersistentDocumentBeanPropertyInfo implements BeanPropertyInfo
{
	/**
	 * @var PropertyInfo
	 */
	private $propertyInfo;
	
	/**
	 * @var String
	 */
	private $moduleName;
	
	/**
	 * @var String
	 */
	private $documentName;
	
	private $cardinality;
	private $defaultValue;
	private $name;
	private $type;
	/**
	 * @var String Validation rules
	 */
	private $constraints;
	/**
	 * @var String
	 */
	private $documentType;
	/**
	 * @var String
	 */
	private $className;
	
	public function __construct($moduleName, $documentName, $propertyInfo)
	{
		$this->propertyInfo = $propertyInfo;
		$this->moduleName = $moduleName;
		$this->documentName = $documentName;
	}
	
	/**
	 * @return integer
	 */
	public function getMaxOccurs()
	{
		return $this->propertyInfo->getMaxOccurs();
	}
	
	/**
	 * @return integer
	 */
	public function getMinOccurs()
	{
		return $this->propertyInfo->getMinOccurs();
	}
	
	/**
	 * @see BeanPropertyInfo::getCardinality()
	 *
	 * @return integer
	 */
	public function getCardinality()
	{
		if ($this->cardinality === null)
		{
			$this->cardinality = $this->propertyInfo->getMaxOccurs();
		}
		return $this->cardinality;
	}
	
	/**
	 * @see BeanPropertyInfo::getDefaultValue()
	 *
	 * @return mixed
	 */
	public function getDefaultValue()
	{
		if ($this->defaultValue === null)
		{
			$this->defaultValue = $this->propertyInfo->getDefaultValue();
		}
		return $this->defaultValue;
	}
	
	/**
	 * @see BeanPropertyInfo::getLabelKey()
	 *
	 * @return string
	 */
	public function getLabelKey()
	{
		return 'm.' . $this->moduleName . '.document.' . $this->documentName . '.' . strtolower($this->getName());
	}
	
	/**
	 * @see BeanPropertyInfo::getHelpKey()
	 *
	 * @return string
	 */
	public function getHelpKey()
	{
		return 'm.' . $this->moduleName . '.document.' . $this->documentName . '.' . strtolower($this->getName()) . '-help';
	}
	
	/**
	 * @see BeanPropertyInfo::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		if ($this->name === null)
		{
			$this->name = $this->propertyInfo->getName();
		}
		return $this->name;
	}
	
	/**
	 * @see BeanPropertyInfo::getType()
	 *
	 * @return string
	 */
	public function getType()
	{
		if ($this->type === null)
		{
			if ($this->propertyInfo->isDocument())
			{
				$this->type = BeanPropertyType::DOCUMENT;
				$this->documentType = $this->propertyInfo->getDocumentType();
				$matches = null;
				if (preg_match('/^modules_(\w+)\/(\w+)$/', $this->documentType, $matches))
				{
					$this->className = $matches[1]."_persistentdocument_".$matches[2];	
				}
				else
				{
					throw new Exception("Could not parse document type ".$this->documentType);
				}
			}
			else
			{
				$this->type = $this->propertyInfo->getType();
			}
		}
		return $this->type;
	}
	
	/**
	 * If the property type is BeanPropertyType::DOCUMENT,
	 * returns the linked document model
	 * @return string
	 */
	public function getDocumentType()
	{
		// getType() must be called before getDocumentType()
		// because getType() fill documentType.
		$this->getType();
		return $this->documentType;
	}
	
	/**
	 * If the property type if DOCUMENT, BEAN or CLASS
	 * @return string
	 */
	public function getClassName()
	{
		// getType() must be called before getClassName()
		// because getType() fill documentType.
		$this->getType();
		return $this->className;
	}
	
	/**
	 * @see BeanPropertyInfo::getValidationRules()
	 *
	 * @return string
	 */
	public function getValidationRules()
	{
		if ($this->constraints === null)
		{
			$constraints = $this->propertyInfo->getConstraints();
			if (f_util_StringUtils::isEmpty($constraints))
			{
				$this->constraints = "";
			}
			else
			{
				$this->constraints = $this->getName() . '{' . $constraints . '}';	
			}
		}
		return $this->constraints;
	}
	
	/**
	 * @see BeanPropertyInfo::isRequired()
	 *
	 * @return boolean
	 */
	public function isRequired()
	{
		return $this->propertyInfo->isRequired();
	}
	
	/**
	 * @see BeanPropertyInfo::isHidden()
	 *
	 * @return boolean
	 */
	public function isHidden()
	{
		$propertyName = $this->propertyInfo->getName();
		if ($propertyName == 'id' || $propertyName == 'model')
		{
			return true;
		}
		return false;
	}
	
	/**
	 * @see BeanPropertyInfo::getConverter()
	 * 
	 * @return BeanValueConverter or null
	 */
	public function getConverter()
	{
		if ($this->hasList() && $this->isDocument())
		{
			$list = $this->getList();
			if ($list instanceof list_persistentdocument_editablelist)
			{
				if ($this->propertyInfo->isArray())
				{
					return new bean_EditableListMultipleConverter($list);
				}
				return new bean_EditableListConverter($list);
			}
		}
		
		if ($this->propertyInfo->isDocument())
		{
			if ($this->propertyInfo->isArray())
			{
				return new bean_DocumentsConverter();
			}
			return new bean_DocumentConverter();
		}
		
		switch ($this->getType())
		{
			case BeanPropertyType::DATETIME:
				return new bean_DateTimeConverter();
			case BeanPropertyType::XHTMLFRAGMENT:
				return new bean_XHTMLFragmentConverter();
			case BeanPropertyType::DOUBLE:
				return new bean_DecimalConverter();	
			case BeanPropertyType::BOOLEAN:
				return new bean_BooleanConverter();
			case BeanPropertyType::INTEGER:
				return new bean_IntegerConverter();
		}
		return null;
	}
	
	private function isDocument()
	{
		return $this->propertyInfo->isDocument();
	}

	private $hasAttachedList;
	private $attachedList;
	
	/**
	 * @see BeanPropertyInfo::getList()
	 *
	 */
	public function getList()
	{
		if (!$this->hasList())
		{
			throw new Exception("Property has no attached list");
		}
		
		if ($this->attachedList === null)
		{
			$this->attachedList = list_ListService::getInstance()->getByListId($this->propertyInfo->getFromList());
		}
		return $this->attachedList;
	}
	
	/**
	 * @see BeanPropertyInfo::hasList()
	 *
	 * @return boolean
	 */
	public function hasList()
	{
		if ($this->hasAttachedList === null)
		{
			$this->hasAttachedList = f_util_StringUtils::isNotEmpty($this->propertyInfo->getFromList());
		}
		return $this->hasAttachedList;
	}
	
	/**
	 * @see BeanPropertyInfo::getSetterName()
	 * @return string
	 */
	public function getSetterName()
	{
		if ($this->propertyInfo->isArray())
		{
			return 'set' . ucfirst($this->getName()) . 'Array';
		}
		return 'set' . ucfirst($this->getName());
	}
	
	/**
	 * @see BeanPropertyInfo::getGetterName()
	 * @return string
	 */
	public function getGetterName()
	{
		if ($this->propertyInfo->isArray())
		{
			return 'get' . ucfirst($this->getName()) . 'Array';
		}
		
		if (BeanPropertyType::XHTMLFRAGMENT == $this->getType())
		{
			return 'get' . ucfirst($this->getName()) . 'AsHtml';
		}
		return 'get' . ucfirst($this->getName());
	}
	
	/**
	 * @param string $name
	 * @param array $arguments
	 * @deprecated
	 */
	public function __call($name, $arguments)
	{
		switch ($name)
		{
			case 'getFormPropertyInfo': 
				Framework::error('Call to deleted ' . get_class($this) . '->getFormPropertyInfo method');
				return null;
			
			default: 
				throw new BadMethodCallException('No method ' . get_class($this) . '->' . $name);
		}
	}
}