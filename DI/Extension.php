<?php

namespace Wame\EntityModifier\DI;

use Nette\DI\CompilerExtension;
use Wame\EntityModifier\Model\EntityModifier;

class Extension extends CompilerExtension
{

    public function __construct()
    {
        if (!file_exists(EntityModifier::ENTITIES_PATH)) {
            mkdir(EntityModifier::ENTITIES_PATH, 0760, TRUE);
        }
    }

    public function setCompiler(\Nette\DI\Compiler $compiler, $name)
    {
        parent::setCompiler($compiler, $name);
        $this->compiler->addConfig(["doctrine" => ["metadata" => [EntityModifier::ENTITIES_NAMESPACE => EntityModifier::ENTITIES_PATH]]]);
        return $this;
    }
}
