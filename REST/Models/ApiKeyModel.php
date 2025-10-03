<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use DateTime;
use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\ModelHelper;
use JTL\Plugin\Admin\InputType;
use JTL\REST\Permissions;

/**
 * Class ApiKeyModel
 *
 * @package JTL\REST\Models
 * @property int      $id
 * @method int getId()
 * @method void setId(int $value)
 * @property int      $permissions
 * @method string getPermissions()
 * @method void setPermissions(int $value)
 * @property string   $key
 * @method string getKey()
 * @method static setKey(string $value)
 * @property DateTime $created
 * @method DateTime getCreated()
 * @method void setCreated(DateTime $value)
 */
final class ApiKeyModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'api_keys';
    }

    /**
     * Setting of keyname is not supported!
     * Call will always throw an Exception with code ERR_DATABASE!
     * @inheritdoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    /**
     * @inheritdoc
     */
    protected function onRegisterHandlers(): void
    {
        parent::onRegisterHandlers();
        $this->registerGetter(
            'created',
            fn($value, $default): ?DateTime => ModelHelper::fromStrToDateTime($value, $default)
        );
        $this->registerSetter('created', fn($value): ?string => ModelHelper::fromDateTimeToStr($value));
    }

    /**
     * @inheritdoc
     */
    public function save(?array $partial = null, bool $updateChildModels = true): bool
    {
        $permissions = new Permissions();
        foreach ($_POST['permissions'] ?? [] as $method) {
            $permissions->set($permissions->getConst($method));
        }
        $this->members['permissions'] = $permissions->getValue();

        return parent::save($partial, $updateChildModels);
    }

    /**
     * @inheritdoc
     */
    public function getAttributes(): array
    {
        $attributes                = [];
        $attributes['id']          = DataAttribute::create('id', 'int', null, false, true);
        $attributes['key']         = DataAttribute::create('key', 'varchar', null, false);
        $attributes['permissions'] = DataAttribute::create('permissions', 'int', 0, false);
        $attributes['created']     = DataAttribute::create('created', 'datetime', null, false);

        $attributes['id']->getInputConfig()->setModifyable(false);
        $attributes['permissions']->getInputConfig()->setInputType(InputType::CHECKBOX);
        $attributes['permissions']->getInputConfig()->setAllowedValues([
            'GET'    => 'permissionsAllowMethodGet',
            'POST'   => 'permissionsAllowMethodPost',
            'PUT'    => 'permissionsAllowMethodPut',
            'DELETE' => 'permissionsAllowMethodDelete'
        ]);
        $attributes['created']->getInputConfig()->setInputType(InputType::DATE);
        $attributes['created']->getInputConfig()->setHidden(true);

        return $attributes;
    }

    public function getPermissionsValue(): int
    {
        return $this->members['permissions'];
    }

    /**
     * @inheritdoc
     */
    public function getAttribValue($attribName, $default = null)
    {
        if ($attribName === 'permissions') {
            $value       = (int)$this->members[$attribName];
            $allowed     = [];
            $permissions = new Permissions($value);
            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
                if ($permissions->methodAllowed($method)) {
                    $allowed[] = $method;
                }
            }

            return \implode(', ', \count($allowed) > 0 ? $allowed : ['NONE']);
        }

        return parent::getAttribValue($attribName, $default);
    }
}
