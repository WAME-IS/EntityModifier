<?php

namespace Wame\EntityModifier\Model;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Mapping\ClassMetadataFactory;
use Kdyby\Events\Subscriber;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Object;
use Wame\Core\Event\RepositoryEntitySetEvent;
use Wame\EntityModifier\Model\EntityBuilder;
use Wame\Utils\Strings;

class EntityModifier extends Object implements Subscriber
{

    const ENTITIES_NAMESPACE = 'Temp\\Entities\\',
        ENTITIES_PATH = TEMP_PATH . '/entities/';

    /** @var EntityBuilder[] */
    private $builders = [];

    /** @var Cache */
    private $cache;

    /** @var ClassMetadataFactory */
    private $metaFactory;

    public function __construct(IStorage $cacheStorage, EntityManager $em)
    {
        $this->cache = new Cache($cacheStorage, 'EntityModifier');
        $this->metaFactory = $em->getMetadataFactory();
    }

    /**
     * @internal Events
     * @return array
     */
    public function getSubscribedEvents()
    {
        return ['Wame\\Core\\Repositories\\BaseRepository::onEntitynNameSet', 'loadClassMetadata'];
    }

    /**
     * @param RepositoryEntitySetEvent $event
     * @internal Event call
     */
    public function onEntitynNameSet(RepositoryEntitySetEvent $event)
    {
        $event->setEntityName($this->modifyName($event->getEntityName()));
    }

    /**
     * Builds temp entity file, loads it into doctrain and fixes associations with other entities.
     * 
     * @param type $entityName
     * @return type
     */
    public function buildTempEntity($entityName)
    {
        $builder = $this->getEntityBuilder($entityName);
        if ($builder->isBuilt() || class_exists($this->getModifiedClassName($entityName))) {
            return;
        }

        $tempEntityName = $this->buildTempEntityClass($entityName);

        $metadata = $this->metaFactory->getMetadataFor($entityName);
        $originalMetadata = clone($metadata);

        //clear parent metadata
        $metadata->associationMappings = [];
        $metadata->fieldMappings = [];
        $metadata->fieldNames = [];
        $metadata->columnNames = [];
        $metadata->identifier = [];
        $metadata->table = null;
        $metadata->isMappedSuperclass = true;

        $tempMetadata = $this->metaFactory->getMetadataFor($tempEntityName);
        $tempMetadata->associationMappings = array_merge($tempMetadata->associationMappings, $originalMetadata->associationMappings);
        $tempMetadata->table = $originalMetadata->table;
    }

    /**
     * @param LoadClassMetadataEventArgs $event
     * @internal Event call
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        $metadata = $event->getClassMetadata();
        foreach ($metadata->associationMappings as $key => $mapping) {
            $metadata->associationMappings[$key]['targetEntity'] = $this->modifyName($mapping['targetEntity']);
            $metadata->associationMappings[$key]['sourceEntity'] = $this->modifyName($mapping['sourceEntity']);
        }
        foreach ($metadata->discriminatorMap as $key => $entity) {
            $metadata->discriminatorMap[$key] = $this->modifyName($entity);
        }
    }

    public function modifyName($name)
    {
        if ($this->isModified($name)) {
            $this->buildTempEntity($name);
            return $this->getModifiedClassName($name);
        } else {
            if (Strings::startsWith($name, self::ENTITIES_NAMESPACE)) {
                return substr($name, strlen(self::ENTITIES_NAMESPACE));
            }
        }
        return $name;
    }

    /**
     * Build entity class
     */
    protected function buildTempEntityClass($entityName)
    {
        //Check for changes
        $builder = $this->getEntityBuilder($entityName);

        $hash = $builder->hash();
        $oldHash = $this->cache->load($entityName);

        $path = self::ENTITIES_PATH . str_replace("\\", "_", $entityName) . '.php';
        $className = $this->getModifiedClassName($entityName);

        //Generate php files
        if ($hash != $oldHash) {
            $builder->build($path, $className);
            $this->cache->save($entityName, $hash);
        }
        $builder->setBuilt(true);

        require $path;
        return $className;
    }

    private function isModified($entityName)
    {
        return isset($this->builders[$entityName]);
    }

    private function getModifiedClassName($entityName)
    {
        return self::ENTITIES_NAMESPACE . $entityName;
    }

    /**
     * Gets entity builder for specified entity class. Entity can be then modified with addField and addTrait methods.
     * 
     * @param string $entityName Entity class name
     * @return EntityBuilder
     */
    public function getEntityBuilder($entityName)
    {
        if (!isset($this->builders[$entityName])) {
            $this->builders[$entityName] = new EntityBuilder($entityName);
        }
        return $this->builders[$entityName];
    }
}
