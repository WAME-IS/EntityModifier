# EntityModifier
This plugin is used to modify entities in other modules dynamicaly. Modified classes are cached in /temp/entites folder and reused.

## Example of modifications:
```
Wame\EntityModifier\Model\EntityModifier $entityModifier;

$entityModifier->getEntityBuilder(\Wame\ArticleModule\Entities\ArticleEntity::class)->addField(new FieldDefinition('ienze', [
    '@Doctrine\ORM\Mapping\Column(name="ienze", type="text", length=42, nullable=true)'
]));

$entityModifier->getEntityBuilder(\Wame\ArticleModule\Entities\ArticleEntity::class)->addField(new FieldDefinition('ienza', [
    '@Doctrine\ORM\Mapping\ManyToMany(targetEntity="Wame\SeoModule\Entities\SeoEntity")'
]));

$entityModifier->getEntityBuilder(\Wame\ArticleModule\Entities\ArticleEntity::class)->addTrait("Wame\Core\Entities\Columns\Lang");
```

Also config can be used directly for same purpose:
```
entityModifier:
    traits:
    - {class: Wame\ArticleModule\Entities\ArticleEntity, trait: Wame\Core\Entities\Columns\Parameters}
```
