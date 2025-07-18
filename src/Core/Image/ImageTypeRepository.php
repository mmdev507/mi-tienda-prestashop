<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\PrestaShop\Core\Image;

use Db;

class ImageTypeRepository
{
    /**
     * @var Db
     */
    private $db;
    /**
     * @var string
     */
    private $db_prefix;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->db_prefix = $db->getPrefix();
    }

    public function setTypes(array $types, ?string $theme_name = null)
    {
        if (null !== $theme_name) {
            $this->remoteAllTypesByTheme($theme_name);
        } else {
            $this->removeAllTypes();
        }

        foreach ($types as $name => $data) {
            $this->createType(
                $name,
                $data['width'],
                $data['height'],
                $data['scope'],
                $theme_name
            );
        }

        return $this;
    }

    public function createType($name, $width, $height, array $scope, ?string $theme_name = null)
    {
        $data = [
            'name' => $this->db->escape($name),
            'width' => $this->db->escape($width),
            'height' => $this->db->escape($height),
            'theme_name' => $this->db->escape($theme_name),
        ];

        foreach ($this->getScopeList() as $scope_item) {
            if (in_array($scope_item, $scope)) {
                $data[$scope_item] = 1;
            } else {
                $data[$scope_item] = 0;
            }
        }

        $this->db->insert('image_type', $data);

        return $this->getIdByName($name);
    }

    public function getScopeList()
    {
        return ['products', 'categories', 'manufacturers', 'suppliers', 'stores'];
    }

    public function getIdByName($name, ?string $theme_name = null)
    {
        $escaped_name = $this->db->escape($name);

        if ($theme_name) {
            $escaped_theme_name = $this->db->escape($theme_name);
            $id_image_type = $this->db->getValue(
                "SELECT id_image_type FROM {$this->db_prefix}image_type WHERE name = '$escaped_name' AND theme_name = '$escaped_theme_name'"
            );
        } else {
            $id_image_type = $this->db->getValue(
                "SELECT id_image_type FROM {$this->db_prefix}image_type WHERE name = '$escaped_name' AND theme_name IS NULL"
            );
        }

        return (int) $id_image_type;
    }

    protected function removeAllTypes()
    {
        Db::getInstance()->execute(
            "TRUNCATE TABLE {$this->db_prefix}image_type"
        );
    }

    protected function remoteAllTypesByTheme($theme_name)
    {
        $escaped_theme_name = $this->db->escape($theme_name);
        Db::getInstance()->execute(
            "DELETE FROM {$this->db_prefix}image_type WHERE theme_name = '$escaped_theme_name'"
        );
    }
}
