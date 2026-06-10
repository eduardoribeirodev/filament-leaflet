<?php

namespace EduardoRibeiroDev\FilamentLeaflet\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class Address implements Arrayable
{
    public function __construct(
        public readonly ?string $suburb,
        public readonly ?string $street,
        public readonly ?string $houseNumber,
        public readonly ?string $cityDistrict,
        public readonly ?string $city,
        public readonly ?string $county,
        public readonly ?string $state,
        public readonly ?string $province,
        public readonly ?string $region,
        public readonly ?string $postcode,
        public readonly ?string $country,
        public readonly ?string $countryCode,
    ) {}

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'suburb' => $this->suburb,
            'city_district' => $this->cityDistrict,
            'city' => $this->city,
            'county' => $this->county,
            'state' => $this->state,
            'province' => $this->province,
            'region' => $this->region,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'country_code' => $this->countryCode,
        ];
    }

    public static function fromArray(array $data): self
    {
        $getDataAttribute = function(string|array $columns) use ($data) {
            $columns = (array) $columns;

            foreach ($columns as $column) {
                if (array_key_exists($column, $data)) {
                    return $data[$column];
                }
            }

            return null;
        };

        return new static(
            suburb:       $getDataAttribute('suburb'),
            street:       $getDataAttribute(['road', 'route', 'street', 'text', 'addressLine']),
            houseNumber:  $getDataAttribute(['house_number', 'street_number', 'address', 'number']),
            cityDistrict: $getDataAttribute('city_district'),
            city:         $getDataAttribute(['city', 'town', 'village']),
            county:       $getDataAttribute('county'),
            state:        $getDataAttribute('state'),
            province:     $getDataAttribute('province'),
            region:       $getDataAttribute('region'),
            postcode:     $getDataAttribute('postcode'),
            country:      $getDataAttribute('country'),
            countryCode:  $getDataAttribute('country_code')
        );
    }
}
