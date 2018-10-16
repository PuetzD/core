<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\Deferred;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteProtected;
use Shopware\Core\System\User\UserDefinition;

class MediaDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            new FkField('user_id', 'userId', UserDefinition::class),

            (new StringField('mime_type', 'mimeType'))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING), new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new StringField('file_extension', 'fileExtension'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING), new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new IntField('file_size', 'fileSize'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new ObjectField('meta_data', 'metaData'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->setFlags(new Deferred()),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, false),

            new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', false, 'id'),
            new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', false, 'id'),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id', true))->setFlags(new CascadeDelete()),
            (new BoolField('has_file', 'hasFile'))->setFlags(new Deferred()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public static function getStructClass(): string
    {
        return MediaStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return MediaTranslationDefinition::class;
    }
}
