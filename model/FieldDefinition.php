<?php

namespace Wame\EntityModifier\Model;


class FieldDefinition extends \Nette\Object
{
	/** @var string */
	private $name;

	/** @var array */
	private $options = [];

	/** @var mixed */
	private $value;


	public function __construct($name, $options, $value = null)
    {
		$this->name = $name;
		$this->options = $options;
		$this->value = $value;
	}


	public function getName()
    {
		return $this->name;
	}


	public function getOptions()
    {
		return $this->options;
	}


	public function getValue()
    {
		return $this->value;
	}


	public function setName($name)
    {
		$this->name = $name;

		return $this;
	}


	public function setOptions($options)
    {
		$this->options = $options;

		return $this;
	}


	public function setValue($value)
    {
		$this->value = $value;

		return $this;
	}

}
