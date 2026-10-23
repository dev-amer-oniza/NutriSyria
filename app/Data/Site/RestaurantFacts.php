<?php

namespace App\Data\Site;

/** One active restaurant (brand) as the public site shows it; empty details are null. */
final readonly class RestaurantFacts
{
    /** @param  list<array{label: string, href: string}>  $phones */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?string $logo,
        public ?string $city,
        public ?string $address,
        public ?string $openingHours,
        public array $phones,
        public ?string $whatsappUrl,
        public ?string $instagramUrl,
        public ?string $facebookUrl,
        public bool $hasDelivery,
        public ?string $deliveryNote,
        /** Active products, approved or not. */
        public int $productCount,
        /** Approved meals (with official values) shown on the site. */
        public int $mealCount = 0,
    ) {}

    public function withMealCount(int $count): self
    {
        return new self(...[...get_object_vars($this), 'mealCount' => $count]);
    }

    public function hasContact(): bool
    {
        return $this->phones || $this->whatsappUrl || $this->instagramUrl || $this->facebookUrl;
    }
}
