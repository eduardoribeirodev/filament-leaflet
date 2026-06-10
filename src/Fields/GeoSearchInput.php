<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Fields;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\GeoSearchProvider;
use EduardoRibeiroDev\FilamentLeaflet\Services\GeoSearchService;
use EduardoRibeiroDev\FilamentLeaflet\StateCasts\GeoSearchResultStateCast;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\GeoSearchResult;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Concerns;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Livewire\Attributes\Renderless;

class GeoSearchInput extends Field
{
    use Concerns\CanBeSearchable;
    use Concerns\CanFixIndistinctState;
    use Concerns\HasAffixes;
    use Concerns\HasExtraInputAttributes;
    use Concerns\HasLoadingMessage;
    use Concerns\HasPlaceholder;
    use HasExtraAlpineAttributes;

    protected string $view = 'filament-leaflet::fields.geo-search-input';

    protected int|Closure $resultsLimit = 25;
    protected GeoSearchProvider|string|Closure|null $provider = null;
    protected bool|Closure|null $addressDetails = null;
    protected string|Closure|null $language = null;
    protected bool|Closure|null $bounded = null;
    protected int|Closure|null $cacheTtl = null;
    protected int|Closure|null $minSearchLength = null;
    protected bool|Closure|null $useShortLabels = null;
    protected bool $textMode = false;

    /** @var string[] */
    protected array $countryCodes = [];

    /** @var float[]|null */
    protected ?array $viewbox = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchDebounce(500);
        $this->noSearchResultsMessage(__('filament-leaflet::fields.geo_search_input.no_results'));
        $this->searchPrompt(__('filament-leaflet::fields.geo_search_input.search_prompt'));
        $this->placeholder(__('filament-leaflet::fields.geo_search_input.placeholder'));
    }

    // ─── Fluent customisation API ─────────────────────────────────────────────

    /**
     * Set the geocoding provider to use for search queries.
     */
    public function provider(GeoSearchProvider|string|Closure $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Maximum number of results returned per search (1–50).
     */
    public function limit(int|Closure $limit): static
    {
        $this->resultsLimit = $limit;
        return $this;
    }

    /**
     * Include structured address details in the API response.
     * Enabled by default — disable when you only need coordinates.
     */
    public function withAddressDetails(bool|Closure $enabled = true): static
    {
        $this->addressDetails = $enabled;
        return $this;
    }

    /**
     * Preferred language for result labels, e.g. 'pt-BR', 'en', 'es'.
     * Maps to the Nominatim `Accept-Language` header.
     */
    public function language(string|Closure $language): static
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Restrict results to one or more ISO 3166-1 alpha-2 country codes.
     *
     * @param  string|string[]  $codes  e.g. 'br' or ['br', 'ar']
     */
    public function countryCodes(string|array|Closure $codes): static
    {
        $this->countryCodes = $codes instanceof Closure ? $codes : (array) $codes;
        return $this;
    }

    /**
     * Restrict results to fall within the given viewbox.
     * Requires {@see viewbox()} to be set.
     */
    public function bounded(bool|Closure $bounded = true): static
    {
        $this->bounded = $bounded;
        return $this;
    }

    /**
     * Set a geographic bounding box to bias (or restrict) search results.
     *
     * @param  float  $minLon  West longitude
     * @param  float  $minLat  South latitude
     * @param  float  $maxLon  East longitude
     * @param  float  $maxLat  North latitude
     */
    public function viewbox(float $minLon, float $minLat, float $maxLon, float $maxLat): static
    {
        $this->viewbox = [
            $minLon,
            $minLat,
            $maxLon,
            $maxLat
        ];

        return $this;
    }

    /**
     * Cache geocoding results using Laravel's cache layer.
     *
     * @param  int  $ttl  Seconds to cache results (default 3600)
     */
    public function cacheResults(int|Closure $ttl = 3600): static
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Minimum number of characters the user must type before a search fires.
     * Useful to avoid noisy/expensive requests on short inputs (default 2).
     */
    public function minSearchLength(int|Closure $length): static
    {
        $this->minSearchLength = $length;
        return $this;
    }

    /**
     * Use short labels for search results, e.g. "New York, USA" instead of "New York, New York, United States of America".
     */
    public function useShortLabels(bool|Closure $useShort = true): static
    {
        $this->useShortLabels = $useShort;
        return $this;
    }

    /**
     * Return only the text label instead of coordinates array.
     * When enabled, the field value will be the place name/text instead of coordinates.
     */
    public function textMode(bool|Closure $enabled = true): static
    {
        $this->textMode = $enabled;
        return $this;
    }

    protected function buildService(): GeoSearchService
    {
        $service = app(GeoSearchService::class);
        // Evaluate configured options lazily
        if ($provider = $this->getProvider()) {
            $service->provider($provider);
        }

        $service->limit($this->getResultsLimit());

        if (($addressDetails = $this->getAddressDetails()) !== null) {
            $service->withAddressDetails($addressDetails);
        }

        if (($bounded = $this->getBounded()) !== null) {
            $service->bounded($bounded);
        }

        if (($ttl = $this->getCacheTtl()) !== null) {
            $service->cacheResults($ttl);
        }

        if (($language = $this->getLanguage()) !== null) {
            $service->language($language);
        }

        $countryCodes = $this->getCountryCodes();
        if (!empty($countryCodes)) {
            $service->countryCodes($countryCodes);
        }

        if ($viewbox = $this->getViewbox()) {
            $service->viewbox(...$viewbox);
        }

        return $service;
    }

    /**
     * @return array<string>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getSearchResults(string $search): array
    {
        if (mb_strlen($search) < $this->minSearchLength) {
            return [];
        }

        $results = $this->buildService()->search($search);

        return collect($results)
            ->map(fn(GeoSearchResult $result) => [
                'label'  => $result->{$this->useShortLabels ? 'name' : 'displayName'},
                'value'  => $result
            ])
            ->values()
            ->all();
    }

    public function getResultsLimit(): int
    {
        return (int) $this->evaluate($this->resultsLimit);
    }

    public function getMinSearchLength(): int
    {
        return max(1, (int) $this->evaluate($this->minSearchLength));
    }

    public function getDefaultStateCasts(): array
    {
        return [
            app(GeoSearchResultStateCast::class),
        ];
    }

    public function getProvider(): ?GeoSearchProvider
    {
        if ($this->provider === null) {
            return null;
        }

        $provider = $this->evaluate($this->provider);

        return is_string($provider) ? GeoSearchProvider::from($provider) : $provider;
    }

    public function getAddressDetails(): ?bool
    {
        if ($this->addressDetails === null) {
            return null;
        }

        return (bool) $this->evaluate($this->addressDetails);
    }

    public function getLanguage(): ?string
    {
        if ($this->language === null) {
            return null;
        }

        return (string) $this->evaluate($this->language);
    }

    public function getBounded(): ?bool
    {
        if ($this->bounded === null) {
            return null;
        }

        return (bool) $this->evaluate($this->bounded);
    }

    public function getCacheTtl(): ?int
    {
        if ($this->cacheTtl === null) {
            return null;
        }

        return (int) $this->evaluate($this->cacheTtl);
    }

    public function getUseShortLabels(): ?bool
    {
        if ($this->useShortLabels === null) {
            return null;
        }

        return (bool) $this->evaluate($this->useShortLabels);
    }

    public function getCountryCodes(): array
    {
        if ($this->countryCodes instanceof Closure) {
            return (array) $this->evaluate($this->countryCodes);
        }

        return $this->countryCodes ?? [];
    }

    public function getViewbox(): ?array
    {
        return $this->viewbox;
    }

    public function isTextMode(): bool
    {
        return (bool) $this->evaluate($this->textMode);
    }
}
