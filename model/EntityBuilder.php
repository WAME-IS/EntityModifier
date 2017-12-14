<?php

namespace Wame\EntityModifier\Model;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Reflection;


class EntityBuilder
{
    /** @var string[] Base class */
    private $baseClass;

    /** @var FieldDefinition[] Fields to add */
    private $fields = [];

    /** @var string[] Traits to add */
    private $traits = [];

    /** @var boolean */
    private $isBuilt = false;

    
    public function __construct($baseClass)
    {
        $this->baseClass = $baseClass;
    }


    public function addField(FieldDefinition $fieldDefinition)
    {
        $this->fields[] = $fieldDefinition;
    }


    public function addTrait($trait)
    {
        $this->traits[] = $trait;
    }


    public function build($path, $className)
    {
        $class = new \Nette\Reflection\ClassType($this->baseClass);

        $phpFile = new PhpFile();

        $phpClass = $phpFile->addClass($className);
        $phpClass->addExtend($this->baseClass);

        $table = $class->getAnnotation('ORM\Table');
        if ($table) $phpClass->addComment('@ORM\Table(name="' . $table->name . '")');

        $phpClass->addComment('@ORM\Entity');

        $phpFile->addNamespace($phpClass->getNamespace()->getName())
                ->addUse('Doctrine\ORM\Mapping', 'ORM');

        $this->buildFields($phpClass, $this->fields);
        $this->buildTraits($phpClass, $this->traits);

        file_put_contents($path, (string) $phpFile); //Save new source
    }


    private function buildFields(ClassType $phpClass, $fields)
    {
        foreach ($fields as $field) {
            $property = $phpClass->addProperty($field->getName(), $field->getValue());
            $property->setVisibility('protected');

            foreach ($field->getOptions() as $option) {
                $property->addComment($option);
            }
        }
    }

    private function buildTraits(ClassType $phpClass, $traits)
    {
        foreach ($traits as $trait) {
            $phpClass->addTrait($trait);
        }
    }


    function getName()
    {
        return $this->baseClass;
    }


    function isBuilt()
    {
        return $this->isBuilt;
    }

    
    function setBuilt($isBuilt)
    {
        $this->isBuilt = $isBuilt;
    }

        
    function hash()
    {
        return md5(serialize($this->baseClass) . serialize($this->fields) . serialize($this->traits));
    }

}
