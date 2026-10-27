@props([
    'method',
])

{{--
    Renders one payment method's recipient details and transfer steps.

    Values come from the order's instruction snapshot (or, only while the
    settings table is unseeded, from config/payments.php). Nothing about an
    account is written into a template (PRD FR-05: instructions are
    configuration, not code). Blade escapes all of it by default.
--}}
<div {{ $attributes->class('payment-instructions') }}>
    @if ($method->isDummy)
        {{-- Placeholder data must never read as a real instruction.
             PaymentMethodRegistry already hides dummy methods outside local
             and testing, so this warning is the second layer, not the only
             one. --}}
        <x-alert variant="error" title="Data contoh — jangan melakukan transfer" class="u-mb-4">
            Metode ini adalah data pengembangan, bukan rekening sebenarnya.
            Detail pembayaran resmi belum dikonfirmasi oleh pemilik bisnis.
        </x-alert>
    @endif

    @if (filled($method->account))
        <dl class="detail-list">
            @foreach ($method->account as $row)
                <div class="detail-row">
                    <dt>{{ $row['label'] }}</dt>
                    <dd>{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if (filled($method->steps))
        <ol class="payment-steps">
            @foreach ($method->steps as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
    @endif

    @if (filled($method->qrImageUrl))
        {{-- QRIS: the participant has to see the code in order to scan it, so
             the image itself is part of the instructions. The URL comes from the
             order's snapshot, which is what the participant was shown when the
             order was created. --}}
        <img
            src="{{ $method->qrImageUrl }}"
            alt="Kode QR pembayaran {{ $method->label }}"
            class="payment-qr-image"
            loading="lazy"
        >
    @endif
</div>
