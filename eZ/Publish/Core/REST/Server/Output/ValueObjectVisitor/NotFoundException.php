<?php
/**
 * File containing the NotFoundException ValueObjectVisitor class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Server\Output\ValueObjectVisitor;

/**
 * NotFoundException value object visitor
 */
class NotFoundException extends Exception
{
    /**
     * Returns HTTP status code
     *
     * @return int
     */
    protected function getStatus()
    {
        return 404;
    }
}
