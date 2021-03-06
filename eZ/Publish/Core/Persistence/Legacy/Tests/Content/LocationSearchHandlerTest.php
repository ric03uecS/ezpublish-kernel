<?php
/**
 * File contains: eZ\Publish\Core\Persistence\Legacy\Tests\Content\Location\Search\SearchHandlerTest class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Legacy\Tests\Content;

use eZ\Publish\Core\Persistence;
use eZ\Publish\Core\Persistence\Legacy\Content\Search;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Common\Gateway\CriteriaConverter;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Common\Gateway\SortClauseConverter;
use eZ\Publish\SPI\Persistence\Content\Location as SPILocation;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Location\Gateway\CriterionHandler as LocationCriterionHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Common\Gateway\CriterionHandler as CommonCriterionHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Location\Gateway\SortClauseHandler as LocationSortClauseHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Common\Gateway\SortClauseHandler as CommonSortClauseHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\ConverterRegistry;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\DateAndTime;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\Integer;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\TextLine;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\Url;

/**
 * Test case for LocationSearchHandler
 */
class LocationSearchHandlerTest extends LanguageAwareTestCase
{
    protected static $setUp = false;

    /**
     * Only set up once for these read only tests on a large fixture
     *
     * Skipping the reset-up, since setting up for these tests takes quite some
     * time, which is not required to spent, since we are only reading from the
     * database anyways.
     *
     * @return void
     */
    public function setUp()
    {
        if ( !self::$setUp )
        {
            parent::setUp();
            $this->insertDatabaseFixture( __DIR__ . '/SearchHandler/_fixtures/full_dump.php' );
            self::$setUp = $this->handler;
        }
        else
        {
            $this->handler = self::$setUp;
        }
    }

    /**
     * Assert that the elements are
     */
    protected function assertSearchResults( $expectedIds, $searchResult )
    {
        $ids = array_map(
            function ( $hit )
            {
                return $hit->valueObject->id;
            },
            $searchResult->searchHits
        );

        sort( $ids );

        $this->assertEquals( $expectedIds, $ids );
    }

    /**
     * Returns the location search handler to test
     *
     * This method returns a fully functional search handler to perform tests on.
     *
     * @param array $fullTextSearchConfiguration
     *
     * @return \eZ\Publish\Core\Persistence\Legacy\Content\Search\Location\Handler
     */
    protected function getLocationSearchHandler( array $fullTextSearchConfiguration = array() )
    {
        $transformationProcessor = new Persistence\TransformationProcessor\DefinitionBased(
            new Persistence\TransformationProcessor\DefinitionBased\Parser(),
            new Persistence\TransformationProcessor\PcreCompiler(
                new Persistence\Utf8Converter()
            ),
            glob( __DIR__ . '/../../../Tests/TransformationProcessor/_fixtures/transformations/*.tr' )
        );
        $commaSeparatedCollectionValueHandler = new CommonCriterionHandler\FieldValue\Handler\Collection(
            $this->getDatabaseHandler(),
            $transformationProcessor,
            ","
        );
        $hyphenSeparatedCollectionValueHandler = new CommonCriterionHandler\FieldValue\Handler\Collection(
            $this->getDatabaseHandler(),
            $transformationProcessor,
            "-"
        );
        $simpleValueHandler = new CommonCriterionHandler\FieldValue\Handler\Simple(
            $this->getDatabaseHandler(),
            $transformationProcessor
        );
        $compositeValueHandler = new CommonCriterionHandler\FieldValue\Handler\Composite(
            $this->getDatabaseHandler(),
            $transformationProcessor
        );

        return new Search\Location\Handler(
            new Search\Location\Gateway\DoctrineDatabase(
                $this->getDatabaseHandler(),
                new CriteriaConverter(
                    array(
                        new LocationCriterionHandler\LocationId( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\ParentLocationId( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\LocationRemoteId( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\Subtree( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\Visibility( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\Location\Depth( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\Location\Priority( $this->getDatabaseHandler() ),
                        new LocationCriterionHandler\Location\IsMainLocation( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\ContentId( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\ContentTypeGroupId( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\ContentTypeId( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\ContentTypeIdentifier( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\DateMetadata( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\Field(
                            $this->getDatabaseHandler(),
                            new ConverterRegistry(
                                array(
                                    'ezdatetime' => new DateAndTime(),
                                    'ezinteger' => new Integer(),
                                    'ezstring' => new TextLine(),
                                    'ezprice' => new Integer(),
                                    'ezurl' => new Url()
                                )
                            ),
                            new CommonCriterionHandler\FieldValue\Converter(
                                new CommonCriterionHandler\FieldValue\HandlerRegistry(
                                    array(
                                        "ezboolean" => $simpleValueHandler,
                                        "ezcountry" => $commaSeparatedCollectionValueHandler,
                                        "ezdate" => $simpleValueHandler,
                                        "ezdatetime" => $simpleValueHandler,
                                        "ezemail" => $simpleValueHandler,
                                        "ezinteger" => $simpleValueHandler,
                                        "ezobjectrelation" => $simpleValueHandler,
                                        "ezobjectrelationlist" => $commaSeparatedCollectionValueHandler,
                                        "ezselection" => $hyphenSeparatedCollectionValueHandler,
                                        "eztime" => $simpleValueHandler,
                                    )
                                ),
                                $compositeValueHandler
                            ),
                            $transformationProcessor
                        ),
                        new CommonCriterionHandler\FullText(
                            $this->getDatabaseHandler(),
                            $transformationProcessor,
                            $fullTextSearchConfiguration
                        ),
                        new CommonCriterionHandler\LanguageCode(
                            $this->getDatabaseHandler(),
                            $this->getLanguageMaskGenerator()
                        ),
                        new CommonCriterionHandler\LogicalAnd( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\LogicalNot( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\LogicalOr( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\MapLocationDistance( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\MatchAll( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\ObjectStateId( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\FieldRelation( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\RemoteId( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\SectionId( $this->getDatabaseHandler() ),
                        new CommonCriterionHandler\UserMetadata( $this->getDatabaseHandler() ),
                    )
                ),
                new SortClauseConverter(
                    array(
                        new LocationSortClauseHandler\Location\Id( $this->getDatabaseHandler() ),
                        new CommonSortClauseHandler\ContentId( $this->getDatabaseHandler() ),
                    )
                )
            ),
            $this->getLocationMapperMock()
        );
    }

    /**
     * Returns a location mapper mock
     *
     * @return \eZ\Publish\Core\Persistence\Legacy\Content\Location\Mapper
     */
    protected function getLocationMapperMock()
    {
        $mapperMock = $this->getMock(
            'eZ\\Publish\\Core\\Persistence\\Legacy\\Content\\Location\\Mapper',
            array( 'createLocationsFromRows' )
        );
        $mapperMock
            ->expects( $this->any() )
            ->method( 'createLocationsFromRows' )
            ->with( $this->isType( 'array' ) )
            ->will(
                $this->returnCallback(
                    function ( $rows )
                    {
                        $locations = array();
                        foreach ( $rows as $row )
                        {
                            $locationId = (int)$row['node_id'];
                            if ( !isset( $locations[$locationId] ) )
                            {
                                $locations[$locationId] = new SPILocation();
                                $locations[$locationId]->id = $locationId;
                            }
                        }
                        return array_values( $locations );
                    }
                )
            );
        return $mapperMock;
    }

    public function testFindWithoutOffsetLimit()
    {
        $handler = $this->getLocationSearchHandler();

        $searchResult = $handler->findLocations(
            new LocationQuery(
                array(
                    'filter' => new Criterion\LocationId( 2 )
                )
            )
        );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertCount( 1, $searchResult->searchHits );
    }

    public function testFindWithZeroLimit()
    {
        $handler = $this->getLocationSearchHandler();

        $searchResult = $handler->findLocations(
            new LocationQuery(
                array(
                    'filter' => new Criterion\LocationId( 2 ),
                    'offset' => 0,
                    'limit' => 0,
                )
            )
        );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( array(), $searchResult->searchHits );
    }

    /**
     * Issue with PHP_MAX_INT limit overflow in databases
     */
    public function testFindWithNullLimit()
    {
        $handler = $this->getLocationSearchHandler();

        $searchResult = $handler->findLocations(
            new LocationQuery(
                array(
                    'filter' => new Criterion\LocationId( 2 ),
                    'offset' => 0,
                    'limit' => null,
                )
            )
        );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertCount( 1, $searchResult->searchHits );
    }

    /**
     * Issue with offsetting to the nonexistent results produces \ezcQueryInvalidParameterException exception.
     */
    public function testFindWithOffsetToNonexistent()
    {
        $handler = $this->getLocationSearchHandler();

        $searchResult = $handler->findLocations(
            new LocationQuery(
                array(
                    'filter' => new Criterion\LocationId( 2 ),
                    'offset'    => 1000,
                    'limit'     => null,
                )
            )
        );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( array(), $searchResult->searchHits );
    }

    public function testLocationIdFilter()
    {
        $this->assertSearchResults(
            array( 12, 13 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LocationId(
                            array( 4, 12, 13 )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testParentLocationIdFilter()
    {
        $this->assertSearchResults(
            array( 12, 13, 14, 44, 227 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ParentLocationId( 5 ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testLocationIdAndCombinatorFilter()
    {
        $this->assertSearchResults(
            array( 13 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalAnd(
                            array(
                                new Criterion\LocationId(
                                    array( 4, 12, 13 )
                                ),
                                new Criterion\LocationId(
                                    array( 13, 44 )
                                ),
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testLocationIdParentLocationIdAndCombinatorFilter()
    {
        $this->assertSearchResults(
            array( 44, 160 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalAnd(
                            array(
                                new Criterion\LocationId(
                                    array( 2, 44, 160, 166 )
                                ),
                                new Criterion\ParentLocationId(
                                    array( 5, 156 )
                                ),
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testContentDepthFilterEq()
    {
        $this->assertSearchResults(
            array( 2, 5, 43, 48, 58 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::EQ, 1 ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testContentDepthFilterIn()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 43, 44, 48, 51, 52, 53, 54, 56, 58, 59, 69, 77, 86, 96, 107, 153, 156, 167, 190, 227 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::IN, array( 1, 2 ) ),
                    )
                )
            )
        );
    }

    public function testContentDepthFilterBetween()
    {
        $this->assertSearchResults(
            array( 2, 5, 43, 48, 58 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::BETWEEN, array( 0, 1 ) ),
                    )
                )
            )
        );
    }

    public function testContentDepthFilterGreaterThan()
    {
        $this->assertSearchResults(
            array( 99, 102, 135, 136, 137, 139, 140, 142, 143, 144, 145, 148, 151, 174, 175, 177, 194, 196, 197, 198, 199, 200, 201, 202, 203, 205, 206, 207, 208, 209, 210, 211, 212, 214, 215 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::GT, 4 ),
                    )
                )
            )
        );
    }

    public function testContentDepthFilterGreaterThanOrEqual()
    {
        $this->assertSearchResults(
            array( 99, 102, 135, 136, 137, 139, 140, 142, 143, 144, 145, 148, 151, 174, 175, 177, 194, 196, 197, 198, 199, 200, 201, 202, 203, 205, 206, 207, 208, 209, 210, 211, 212, 214, 215 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::GTE, 5 ),
                    )
                )
            )
        );
    }

    public function testContentDepthFilterLessThan()
    {
        $this->assertSearchResults(
            array( 2, 5, 43, 48, 58 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::LT, 2 ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testContentDepthFilterLessThanOrEqual()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 43, 44, 48, 51, 52, 53, 54, 56, 58, 59, 69, 77, 86, 96, 107, 153, 156, 167, 190, 227 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Depth( Criterion\Operator::LTE, 2 ),
                    )
                )
            )
        );
    }

    public function testLocationPriorityFilter()
    {
        $this->assertSearchResults(
            array( 156, 167, 190 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Location\Priority(
                            Criterion\Operator::BETWEEN,
                            array( 1, 10 )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testLocationRemoteIdFilter()
    {
        $this->assertSearchResults(
            array( 2, 5 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LocationRemoteId(
                            array( '3f6d92f8044aed134f32153517850f5a', 'f3e90596361e31d496d4026eb624c983' )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testVisibilityFilterVisible()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Visibility(
                            Criterion\Visibility::VISIBLE
                        ),
                        'limit' => 5,
                        'sortClauses' => array( new SortClause\Location\Id ),
                    )
                )
            )
        );
    }

    public function testVisibilityFilterHidden()
    {
        $this->assertSearchResults(
            array( 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Visibility(
                            Criterion\Visibility::HIDDEN
                        ),
                    )
                )
            )
        );
    }

    public function testLocationNotCombinatorFilter()
    {
        $this->assertSearchResults(
            array( 2, 5 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalAnd(
                            array(
                                new Criterion\LocationId(
                                    array( 2, 5, 12, 356 )
                                ),
                                new Criterion\LogicalNot(
                                    new Criterion\LocationId(
                                        array( 12, 13, 14 )
                                    )
                                ),
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testLocationOrCombinatorFilter()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalOr(
                            array(
                                new Criterion\LocationId(
                                    array( 2, 5, 12 )
                                ),
                                new Criterion\LocationId(
                                    array( 12, 13, 14 )
                                ),
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testContentIdFilterEquals()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ContentId( 223 ),
                    )
                )
            )
        );
    }

    public function testContentIdFilterIn()
    {
        $this->assertSearchResults(
            array( 225, 226, 227 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ContentId(
                            array( 223, 224, 225 )
                        ),
                    )
                )
            )
        );
    }

    public function testContentTypeGroupFilter()
    {
        $this->assertSearchResults(
            array( 5, 12, 13, 14, 15, 44, 45, 227, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ContentTypeGroupId( 2 ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testContentTypeIdFilter()
    {
        $this->assertSearchResults(
            array( 15, 45, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ContentTypeId( 4 ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testContentTypeIdentifierFilter()
    {
        $this->assertSearchResults(
            array( 43, 48, 51, 52, 53 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ContentTypeIdentifier( 'folder' ),
                        'limit' => 5,
                        'sortClauses' => array( new SortClause\Location\Id ),
                    )
                )
            )
        );
    }

    public function testObjectStateIdFilter()
    {
        $this->assertSearchResults(
            array( 5, 12, 13, 14, 15, 43, 44, 45, 48, 51 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ObjectStateId( 1 ),
                        'limit' => 10,
                        'sortClauses' => array( new SortClause\ContentId ),
                    )
                )
            )
        );
    }

    public function testObjectStateIdFilterIn()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 15, 43, 44, 45, 48 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\ObjectStateId( array( 1, 2 ) ),
                        'limit' => 10,
                        'sortClauses' => array( new SortClause\Location\Id ),
                    )
                )
            )
        );
    }

    public function testRemoteIdFilter()
    {
        $this->assertSearchResults(
            array( 5, 45 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\RemoteId(
                            array( 'f5c88a2209584891056f987fd965b0ba', 'faaeb9be3bd98ed09f606fc16d144eca' )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testSectionFilter()
    {
        $this->assertSearchResults(
            array( 5, 12, 13, 14, 15, 44, 45, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\SectionId( array( 2 ) ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testDateMetadataFilterModifiedGreater()
    {
        $this->assertSearchResults(
            array( 12, 227, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\DateMetadata(
                            Criterion\DateMetadata::MODIFIED,
                            Criterion\Operator::GT,
                            1311154214
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testDateMetadataFilterModifiedGreaterOrEqual()
    {
        $this->assertSearchResults(
            array( 12, 15, 227, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\DateMetadata(
                            Criterion\DateMetadata::MODIFIED,
                            Criterion\Operator::GTE,
                            1311154214
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testDateMetadataFilterModifiedIn()
    {
        $this->assertSearchResults(
            array( 12, 15, 227, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\DateMetadata(
                            Criterion\DateMetadata::MODIFIED,
                            Criterion\Operator::IN,
                            array( 1311154214, 1311154215 )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testDateMetadataFilterModifiedBetween()
    {
        $this->assertSearchResults(
            array( 12, 15, 227, 228 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\DateMetadata(
                            Criterion\DateMetadata::MODIFIED,
                            Criterion\Operator::BETWEEN,
                            array( 1311154213, 1311154215 )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testDateMetadataFilterCreatedBetween()
    {
        $this->assertSearchResults(
            array( 68, 133, 227 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\DateMetadata(
                            Criterion\DateMetadata::CREATED,
                            Criterion\Operator::BETWEEN,
                            array( 1299780749, 1311154215 )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterOwnerWrongUserId()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::OWNER,
                            Criterion\Operator::EQ,
                            2
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterOwnerAdministrator()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 15, 43, 44, 45, 48 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::OWNER,
                            Criterion\Operator::EQ,
                            14
                        ),
                        'limit' => 10,
                        'sortClauses' => array( new SortClause\Location\Id ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterOwnerEqAMember()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::OWNER,
                            Criterion\Operator::EQ,
                            226
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterOwnerInAMember()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::OWNER,
                            Criterion\Operator::IN,
                            array( 226 )
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterCreatorEqAMember()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::MODIFIER,
                            Criterion\Operator::EQ,
                            226
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterCreatorInAMember()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::MODIFIER,
                            Criterion\Operator::IN,
                            array( 226 )
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterEqGroupMember()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::GROUP,
                            Criterion\Operator::EQ,
                            11
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterInGroupMember()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::GROUP,
                            Criterion\Operator::IN,
                            array( 11 )
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterEqGroupMemberNoMatch()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::GROUP,
                            Criterion\Operator::EQ,
                            13
                        ),
                    )
                )
            )
        );
    }

    public function testUserMetadataFilterInGroupMemberNoMatch()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\UserMetadata(
                            Criterion\UserMetadata::GROUP,
                            Criterion\Operator::IN,
                            array( 13 )
                        ),
                    )
                )
            )
        );
    }

    public function testLanguageCodeFilter()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 15, 43, 44, 45, 48 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LanguageCode( 'eng-US' ),
                        'limit' => 10,
                        'sortClauses' => array( new SortClause\Location\Id ),
                    )
                )
            )
        );
    }

    public function testLanguageCodeFilterIn()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 15, 43, 44, 45, 48 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LanguageCode( array( 'eng-US', 'eng-GB' ) ),
                        'limit' => 10,
                        'sortClauses' => array( new SortClause\Location\Id ),
                    )
                )
            )
        );
    }

    public function testLanguageCodeFilterWithAlwaysAvailable()
    {
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 15, 43, 44, 45, 48, 51, 52, 53, 58, 59, 70, 72, 76, 78, 82 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LanguageCode( 'eng-GB', true ),
                        'limit' => 20,
                        'sortClauses' => array( new SortClause\ContentId ),
                    )
                )
            )
        );
    }

    public function testMatchAllFilter()
    {
        $this->markTestIncomplete( "Needs SearchHit" );
        $result = $this->getLocationSearchHandler()->findLocations(
            new LocationQuery(
                array(
                    'filter' => new Criterion\MatchAll(),
                    'limit' => 10,
                    'sortClauses' => array( new SortClause\Location\Id ),
                )
            )
        );

        $this->assertCount( 100, $result );
        $this->assertSearchResults(
            array( 2, 5, 12, 13, 14, 15, 43, 44, 45, 48 ),
            $result
        );
    }

    public function testFullTextFilter()
    {
        $this->assertSearchResults(
            array( 193 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FullText( 'applied webpage' ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFullTextWildcardFilter()
    {
        $this->assertSearchResults(
            array( 193 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FullText( 'applie*' ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFullTextDisabledWildcardFilter()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler( array( 'enableWildcards' => false ) )->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FullText( 'applie*' ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFullTextFilterStopwordRemoval()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FullText( 'the' ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFullTextFilterNoStopwordRemoval()
    {
        $this->markTestIncomplete( "Needs SearchHit" );
        $handler = $this->getLocationSearchHandler(
            array(
                'searchThresholdValue' => PHP_INT_MAX
            )
        );

        $result = $handler->findLocations(
            new LocationQuery(
                array(
                    'filter' => new Criterion\FullText(
                        'the'
                    ),
                    'limit' => 10,
                )
            )
        );

        $this->assertEquals(
            10,
            count(
                array_map(
                    function ( $hit )
                    {
                        return $hit->valueObject->contentInfo->id;
                    },
                    $result->searchHits
                )
            )
        );
    }

    public function testFieldRelationFilterContainsSingle()
    {
        $this->assertSearchResults(
            array( 69 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FieldRelation(
                            'billboard',
                            Criterion\Operator::CONTAINS,
                            array( 60 )
                        ),
                    )
                )
            )
        );
    }

    public function testFieldRelationFilterContainsSingleNoMatch()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FieldRelation(
                            'billboard',
                            Criterion\Operator::CONTAINS,
                            array( 4 )
                        ),
                    )
                )
            )
        );
    }

    public function testFieldRelationFilterContainsArray()
    {
        $this->assertSearchResults(
            array( 69 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FieldRelation(
                            'billboard',
                            Criterion\Operator::CONTAINS,
                            array( 60, 75 )
                        ),
                    )
                )
            )
        );
    }

    public function testFieldRelationFilterContainsArrayNotMatch()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FieldRelation(
                            'billboard',
                            Criterion\Operator::CONTAINS,
                            array( 60, 64 )
                        ),
                    )
                )
            )
        );
    }

    public function testFieldRelationFilterInArray()
    {
        $this->assertSearchResults(
            array( 69, 77 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FieldRelation(
                            'billboard',
                            Criterion\Operator::IN,
                            array( 60, 64 )
                        ),
                    )
                )
            )
        );
    }

    public function testFieldRelationFilterInArrayNotMatch()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\FieldRelation(
                            'billboard',
                            Criterion\Operator::IN,
                            array( 4, 10 )
                        ),
                    )
                )
            )
        );
    }

    public function testFieldFilter()
    {
        $this->assertSearchResults(
            array( 12 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Field(
                            'name',
                            Criterion\Operator::EQ,
                            'members'
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFieldFilterIn()
    {
        $this->assertSearchResults(
            array( 12, 44 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Field(
                            'name',
                            Criterion\Operator::IN,
                            array( 'members', 'anonymous users' )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFieldFilterContainsPartial()
    {
        $this->assertSearchResults(
            array( 44 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Field(
                            'name',
                            Criterion\Operator::CONTAINS,
                            'nonymous use'
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFieldFilterContainsSimple()
    {
        $this->assertSearchResults(
            array( 79 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Field(
                            'publish_date',
                            Criterion\Operator::CONTAINS,
                            1174643880
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFieldFilterContainsSimpleNoMatch()
    {
        $this->assertSearchResults(
            array(),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Field(
                            'publish_date',
                            Criterion\Operator::CONTAINS,
                            1174643
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFieldFilterBetween()
    {
        $this->assertSearchResults(
            array( 71, 73, 74 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\Field(
                            'price',
                            Criterion\Operator::BETWEEN,
                            array( 10000, 1000000 )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testFieldFilterOr()
    {
        $this->assertSearchResults(
            array( 12, 71, 73, 74 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalOr(
                            array(
                                new Criterion\Field(
                                    'name',
                                    Criterion\Operator::EQ,
                                    'members'
                                ),
                                new Criterion\Field(
                                    'price',
                                    Criterion\Operator::BETWEEN,
                                    array( 10000, 1000000 )
                                )
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testIsMainLocationFilter()
    {
        $this->assertSearchResults(
            array( 225 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalAnd(
                            array(
                                new Criterion\ParentLocationId( 224 ),
                                new Criterion\Location\IsMainLocation(
                                    Criterion\Location\IsMainLocation::MAIN
                                )
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }

    public function testIsNotMainLocationFilter()
    {
        $this->assertSearchResults(
            array( 510 ),
            $this->getLocationSearchHandler()->findLocations(
                new LocationQuery(
                    array(
                        'filter' => new Criterion\LogicalAnd(
                            array(
                                new Criterion\ParentLocationId( 224 ),
                                new Criterion\Location\IsMainLocation(
                                    Criterion\Location\IsMainLocation::NOT_MAIN
                                )
                            )
                        ),
                        'limit' => 10,
                    )
                )
            )
        );
    }
}
