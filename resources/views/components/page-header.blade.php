@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('page-header') }}>
    <div>
        <h1 class="page-title">{{ $title }}</h1>

        @if ($description)
            <p class="page-description">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="page-header-actions">
            {{ $actions }}
        </div>
    @endisset
</div>
