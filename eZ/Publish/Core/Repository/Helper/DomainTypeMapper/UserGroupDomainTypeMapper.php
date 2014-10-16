<?php
/**
 * File containing the UserGroupDomainTypeMapper class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Repository\Helper\DomainTypeMapper;

use eZ\Publish\Core\Repository\Helper\DomainTypeMapper;
use eZ\Publish\Core\Repository\Values\User\UserGroup;
use eZ\Publish\SPI\Persistence\Content as SPIContent;

/**
 * DomainTypeMapper for UserGroup object
 *
 * @internal
 */
class UserGroupDomainTypeMapper implements DomainTypeMapper
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
        // @todo Inject location and search handler to set parentId and subGroupCount

        return new UserGroup(
            array(
                'parentId' => null,
                'subGroupCount' => null
            ) + $contentProperties
        );
    }
}
