@props([
    'label',
    'value',
    'meta' => null,
    'href' => null,
])

{{--
    KPI card per styling.md section 9: short label, prominent value in primary
    blue, small supporting metadata. No icons or gradients.

    When `href` is set the whole card becomes a link to the filtered list that
    produced the number, so an admin can always get from a figure to the rows
    behind it.
--}}
<x-card {{ $attributes }}>
    <p class="kpi-label">{{ $label }}</p>

    <p class="kpi-value">{{ $value }}</p>

    @if ($meta)
        <p class="kpi-meta">{{ $meta }}</p>
    @endif

    @if ($href)
        <p class="kpi-meta">
            <a href="{{ $href }}">Lihat detail</a>
        </p>
    @endif
</x-card>
