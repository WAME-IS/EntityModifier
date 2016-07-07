<?php

namespace Wame\EntityModifier\DI;

use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;
use Wame\EntityModifier\Model\EntityModifier;

class Extension extends CompilerExtension
{

    public function __construct()
    {
        if (!file_exists(EntityModifier::ENTITIES_PATH)) {
            mkdir(EntityModifier::ENTITIES_PATH, 0760, TRUE);
        }
    }

    public function setCompiler(Compiler $compiler, $name)
    {
        parent::setCompiler($compiler, $name);
        $this->compiler->addConfig(["doctrine" => ["metadata" => [EntityModifier::ENTITIES_NAMESPACE => EntityModifier::ENTITIES_PATH]]]);
        return $this;
    }

    public function loadConfiguration()
    {

        $builder = $this->getContainerBuilder();

        $builder->addDefinition('entityModifier')
            ->setClass(EntityModifier::class, array('@cacheStorage', '@doctrine.default.entityManager'))
            ->addTag('kdyby.subscriber');
    }

    public function addTraits(&$lines, $traits)
    {
        foreach ($traits as $trait) {
            if (!is_array($trait) || !isset($trait['class']) || !isset($trait['trait'])) {
                throw new InvalidArgumentException('Trait definition has to be array containing "class" and "trait"');
            }

            // Adds new service definition
            $lines[] = Helpers::format(
                    '$service->getEntityBuilder(?)->addTrait(?)', $trait['class'], $trait['trait']
            );
        }
    }

    public function afterCompile(ClassType $class)
    {
        $config = $this->getConfig();

        $init = $class->getMethod('createServiceEntityModifier');
        $lines = explode(";\n", trim($init->getBody()));
        $init->setBody(NULL);
        array_pop($lines);

        if (isset($config['traits'])) {
            $this->addTraits($lines, $config['traits']);
        }

        $lines[] = 'return $service;';
        $init->setBody(implode(";\n", $lines));
    }
}
