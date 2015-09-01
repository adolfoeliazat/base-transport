<?php

namespace Base\Transport\Normalizers;

use Base\Transport\Entities\AtcoCode;
use Base\Transport\Entities\FavouritePlace;
use Base\Geospatial\Geolocation;
use Base\Geospatial\Normalizers\GeospatialNormalizer;
use Base\Transport\Entities\FavouritePlaceCategory;

class FavouritePlaceNormalizerTest extends \PHPUnit_Framework_TestCase
{
    protected $serializer;

    public function setUp()
    {
        $this->serializer = new \Symfony\Component\Serializer\Serializer([
            new FavouritePlaceNormalizer(),
            new AtcoCodeNormalizer(),
            new GeospatialNormalizer(),
            new FavouritePlaceCategoryNormalizer(),
        ]);
    }

    public function testNormalization()
    {
        $favouritePlace = new FavouritePlace();
        $favouritePlace->setLabel('City Centre');
        $favouritePlace->setLocation('NG1 5AW');
        $favouritePlace->setGeolocation(new Geolocation(52.9549135, -1.1582327));
        $favouritePlace->setStops([
            new AtcoCode('3390Y4'),
            new AtcoCode('3390Y3'),
            new AtcoCode('3390Y2'),
        ]);

        $category = new FavouritePlaceCategory();
        $category->setLabel('Label');
        $category->setId('id');

        $favouritePlace->setCategory($category);

        $normalized = $this->serializer->normalize($favouritePlace);

        $this->assertEquals($this->getNormalized(), $normalized);
    }

    public function testNormalizeWithoutGeolocation()
    {
        $favouritePlace = new FavouritePlace();
        $favouritePlace->setLabel('City Centre');

        $normalized = $this->serializer->normalize($favouritePlace);

        $this->assertNull($normalized['feature']);
    }

    public function testNormalizeWithoutLocationOrLabel()
    {
        $favouritePlace = new FavouritePlace();
        $favouritePlace->setGeolocation(new Geolocation(52.9549135, -1.1582327));

        $normalized = $this->serializer->normalize($favouritePlace);

        $expectedGeometry = [
            "type" => "Point",
            "coordinates" => [52.9549135, -1.1582327],
        ];
        $this->assertEquals($expectedGeometry, $normalized['feature']['geometry']);

        $expectedFeatureName = "";
        $this->assertEquals($expectedFeatureName, $normalized['feature']['properties']['name']);
    }

    public function testNormalizeWithoutLocation()
    {
        $expectedFeatureName = "the feature name comes from the label by default";

        $favouritePlace = new FavouritePlace();
        $favouritePlace->setGeolocation(new Geolocation(52.9549135, -1.1582327));
        $favouritePlace->setLabel($expectedFeatureName);

        $normalized = $this->serializer->normalize($favouritePlace);

        $expectedGeometry = [
            "type" => "Point",
            "coordinates" => [52.9549135, -1.1582327],
        ];
        $this->assertEquals($expectedGeometry, $normalized['feature']['geometry']);

        $this->assertEquals($expectedFeatureName, $normalized['feature']['properties']['name']);
    }

    public function testDenormalization()
    {
        $favouritePlace = $this->serializer->denormalize($this->getNormalized(), 'Base\\Transport\\Entities\\FavouritePlace', 'mongo');

        $this->assertEquals('City Centre', $favouritePlace->getLabel());
        $this->assertEquals('NG1 5AW', $favouritePlace->getLocation());
        $this->assertInstanceOf(Geolocation::class, $favouritePlace->getGeolocation());
        $this->assertInternalType('array', $favouritePlace->getStops());
        $this->assertContainsOnlyInstancesOf(AtcoCode::class, $favouritePlace->getStops());
    }

    public function testDenormalizationWithoutGeolocation()
    {
        $normalized = $this->getNormalized();
        $normalized['feature'] = null;

        $favouritePlace = $this->serializer->denormalize($normalized, 'Base\\Transport\\Entities\\FavouritePlace', 'mongo');

        $this->assertNull($favouritePlace->getLocation());
    }

    public function testDenormalizationFromWordPress()
    {
        $normalized = [
            'id' => 'fp-542ac6f94a632',
            'label' => 'New Favourite Place',
            'stops' => [],
            'feature' => [
                'properties' => [],
                'geometry' => [
                    'coordinates' => []
                ]
            ]
        ];

        $favouritePlace = $this->serializer->denormalize($normalized, 'Base\\Transport\\Entities\\FavouritePlace', 'json');

        $this->assertEquals('fp-542ac6f94a632', $favouritePlace->getId());
        $this->assertEquals('New Favourite Place', $favouritePlace->getLabel());
        $this->assertNull($favouritePlace->getGeolocation());
    }

    private function getNormalized()
    {
        return [
            'id' => null,
            'label' => 'City Centre',
            'feature' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [52.9549135, -1.1582327],
                ],
                'properties' => [
                    'name' => 'NG1 5AW',
                ],
            ],
            'stops' => [
                '3390Y4',
                '3390Y3',
                '3390Y2',
            ],
            'category' => [
                'id' => 'id',
                'label' => 'Label',
            ],
        ];
    }
}
