@props([
    'title',
    'description' => null,
    'icon' => null,
])

{{--
    Shared empty / no-results state. `styling.md` section 17 requires these
    non-ideal states to be designed, not left blank.
--}}
<div {{ $attributes->class('state') }}>
    <div class="state-icon" aria-hidden="true">{{ $icon ?? '•' }}</div>

    <p class="state-title">{{ $title }}</p>

    @if ($description)
        <p class="state-description">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="state-actions">
            {{ $actions }}
        </div>
    @endisset
</div>
