@props([
    'title' => null,
    'description' => null,
    'variant' => null,
    'elevated' => false,
])

<div {{ $attributes->class([
    'card',
    'card-muted' => $variant === 'muted',
    'card-elevated' => $elevated,
]) }}>
    @if ($title || $description)
        <div class="card-header">
            @if ($title)
                <h2 class="card-title">{{ $title }}</h2>
            @endif

            @if ($description)
                <p class="page-description">{{ $description }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}

    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
