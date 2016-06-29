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
        $entityName = $event->getEntityName();
        if ($this->isModified($entityName)) {
            $this->buildTempEntity($entityName);
            $event->setEntityName($this->getModifiedClassName($entityName));
        }
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
        if ($builder->isBuilt()) {
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
        $metadata->table = [];
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
            if ($this->isModified($mapping['targetEntity'])) {
                $metadata->associationMappings[$key]['targetEntity'] = $this->getModifiedClassName($mapping['targetEntity']);
                $this->buildTempEntity($mapping['targetEntity']);
            }
            if ($this->isModified($mapping['sourceEntity'])) {
                $metadata->associationMappings[$key]['sourceEntity'] = $this->getModifiedClassName($mapping['sourceEntity']);
                $this->buildTempEntity($mapping['sourceEntity']);
            }
        }
        foreach ($metadata->discriminatorMap as $key => $entity) {
            if ($this->isModified($entity)) {
                $metadata->discriminatorMap[$key] = $this->getModifiedClassName($entity);
                $this->buildTempEntity($entity);
            }
        }
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
