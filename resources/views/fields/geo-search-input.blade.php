@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraInputAttributeBag = $getExtraInputAttributeBag();
    $isAutofocused = $isAutofocused();
    $isDisabled = $isDisabled();
    $isRequired = $isRequired();
    $isConcealed = $isConcealed();
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $key = $getKey();
    $id = $getId();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixIconColor = $getPrefixIconColor();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixIconColor = $getSuffixIconColor();
    $suffixLabel = $getSuffixLabel();
    $statePath = $getStatePath();
    $state = $getState();
    $livewireKey = $getLivewireKey();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-select-wrp"
>
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :inline-prefix="$isPrefixInline"
        :inline-suffix="$isSuffixInline"
        :prefix="$prefixLabel"
        :prefix-actions="$prefixActions"
        :prefix-icon="$prefixIcon"
        :prefix-icon-color="$prefixIconColor"
        :suffix="$suffixLabel"
        :suffix-actions="$suffixActions"
        :suffix-icon="$suffixIcon"
        :suffix-icon-color="$suffixIconColor"
        :valid="! $errors->has($statePath)"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
                ->class([
                    'fi-fo-select',
                    'fi-fo-select-has-inline-prefix' => $isPrefixInline && (count($prefixActions) || $prefixIcon || filled($prefixLabel)),
                ])
        "
    >
        <div
            class="fi-hidden"
            x-data="{
                isDisabled: @js($isDisabled),
                init() {
                    const container = $el.nextElementSibling
                    container.dispatchEvent(
                        new CustomEvent('set-select-property', {
                            detail: { isDisabled: this.isDisabled },
                        }),
                    )
                },
            }"
        ></div>
        <div
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select', 'filament/forms') }}"
            x-data="selectFormComponent({
                        canOptionLabelsWrap: false,
                        canSelectPlaceholder: false,
                        isHtmlAllowed: true,
                        getSearchResultsUsing: async (search) => {
                            return await $wire.callSchemaComponentMethod(
                                @js($key),
                                'getSearchResults',
                                { search },
                            )
                        },
                        initialState: @js($state),
                        isAutofocused: @js($isAutofocused),
                        isDisabled: @js($isDisabled),
                        isMultiple: false,
                        isReorderable: false,
                        isSearchable: true,
                        livewireId: @js($this->getId()),
                        hasDynamicSearchResults: true,
                        loadingMessage: @js($getLoadingMessage()),
                        maxItems: @js($getResultsLimit()),
                        noSearchResultsMessage: @js($getNoSearchResultsMessage()),
                        options: [],
                        optionsLimit: @js($getResultsLimit()),
                        placeholder: @js($getPlaceholder()),
                        searchDebounce: @js($getSearchDebounce()),
                        searchingMessage: @js($getSearchingMessage()),
                        searchPrompt: @js($getSearchPrompt()),
                        state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                        statePath: @js($statePath),
                    })"
            wire:ignore
            wire:key="{{ $livewireKey }}.{{
                substr(md5(serialize([
                    $isDisabled,
                ])), 0, 64)
            }}"
            x-on:keydown.esc="select.dropdown.isActive && $event.stopPropagation()"
            x-on:set-select-property="$event.detail.isDisabled ? select.disable() : select.enable()"
            {{
                $attributes
                    ->merge($getExtraAlpineAttributes(), escape: false)
                    ->class(['fi-select-input'])
            }}
        >
            <div x-ref="select"></div>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
