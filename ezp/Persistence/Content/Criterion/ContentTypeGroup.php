<?php
/**
 * File containing the \ezp\Persistence\Content\Criterion\ContentTypeGroup
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace ezp\Persistence\Content\Criterion;

/**
 * @package ezp.persistence.content.criteria
 */
class ContentTypeGroup extends Criterion implements \ezp\Persistence\Content\Interfaces\Criterion
{
    /**
     * Creates a new ContentTypeGroup criterion
     *
     * Content will be matched if it matches one of the contentTypeGroupId in $value
     *
     * @param null $target Not used
     * @param string $operator
     *        Possible values:
     *        - Operator::IN: match against a list of contentTypeGroupId. $value must be an array of contentTypeGroupId
     *        - Operator::EQ: match against a single contentTypeGroupId. $value must be a single contentTypeGroupId
     * @param integer|array(integer) One or more contentTypeGroupId that must be matched
     *
     * @throw InvalidArgumentException if a non numeric id is given
     * @throw InvalidArgumentException if the value type doesn't match the operator
     */
    public function __construct( $target = null, $operator, $value )
    {
        if ( $operator != Operator::IN )
        {
            if ( !is_array( $value ) )
            {
                throw new \InvalidArgumentException( "Operator::IN requires an array of values" );
            }
            foreach( $subtreeId as $id )
            {
                if ( !is_numeric( $id ) )
                {
                    throw new \InvalidArgumentException( "Only numeric ids are accepted" );
                }
            }
        }
        // single value, EQ operator
        elseif ( $operator == Operator::EQ )
        {
            if ( is_array( $value ) )
            {
                throw new \InvalidArgumentException( "Operator::EQ requires a single value" );
            }
            if ( !is_numeric( $value ) )
            {
                throw new \InvalidArgumentException( "Only numeric ids are accepted" );
            }
        }
        $this->operator = $operator;
        $this->value = $value;
    }

    /**
     * List of ContentTypeGroup ids that must be matched
     * @var integer|array(integer)
     */
    public $value;
}
?>



