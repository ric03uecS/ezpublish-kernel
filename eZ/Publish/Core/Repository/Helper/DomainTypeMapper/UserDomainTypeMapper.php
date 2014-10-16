<?php
/**
 * File containing the UserDomainTypeMapper class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Repository\Helper\DomainTypeMapper;

use eZ\Publish\Core\FieldType\User\Value as UserValue;
use eZ\Publish\Core\Repository\Helper\DomainTypeMapper;
use eZ\Publish\Core\Repository\Values\User\User;
use eZ\Publish\SPI\Persistence\Content as SPIContent;

/**
 * DomainTypeMapper for User object
 *
 * @internal
 */
class UserDomainTypeMapper implements DomainTypeMapper
{
    /**
     * Builds a Content domain object from value object returned from persistence.
     *
     * @param \eZ\Publish\SPI\Persistence\Content $spiContent
     * @param array $contentProperties Main properties for Content
     *
     * @return \eZ\Publish\Core\Repository\Values\Content\Content
     */
    public function buildContentObject( SPIContent $spiContent, array $contentProperties )
    {
        // Get spiUser value from Field Value
        foreach ( $contentProperties['internalFields'] as $field )
        {
            if ( $field->value instanceof UserValue )
                break;
        }

        return new User(
            array(
                'login' => $field->value->login,
                'email' => $field->value->email,
                'passwordHash' => $field->value->passwordHash,
                'hashAlgorithm' => (int)$field->value->passwordHashType,
                'enabled' => $field->value->enabled,
                'maxLogin' => (int)$field->value->maxLogin,
            ) + $contentProperties
        );
    }
}
