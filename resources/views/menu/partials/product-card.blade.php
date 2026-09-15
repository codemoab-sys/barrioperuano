<div class="product-card" data-name="{{ strtolower($product->name) }}" data-desc="{{ strtolower($product->description ?? '') }}">
    <div class="product-media">
        @if($product->image)
            <img src="{{ route('uploaded-assets.show', ['path' => $product->image]) }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <div class="product-placeholder"><i class="bi bi-image"></i></div>
        @endif

        @if(isset($badge) && $badge)
            <span class="badge-custom badge-{{ $badge }}">{{ $badgeText }}</span>
        @elseif($product->promotional_price > 0)
            <span class="badge-custom badge-promo">OFERTA</span>
        @elseif($product->is_new)
            <span class="badge-custom badge-new">NUEVO</span>
        @endif

        @if($product->promotional_price > 0 && $product->price > 0)
            @php $discount = round((1 - $product->promotional_price / $product->price) * 100); @endphp
            @if($discount > 0)
                <span class="badge-discount">-{{ $discount }}%</span>
            @endif
        @endif
    </div>

    <div class="product-body">
        <h3 class="product-name">{{ $product->name }}</h3>
        @if($product->description)
            <p class="product-desc">{{ $product->description }}</p>
        @endif

        <div class="product-price-row">
            @if($product->promotional_price > 0)
                <span class="price promo">{{ $currency }} {{ number_format($product->promotional_price, 2) }}</span>
                <span class="price-old">{{ $currency }} {{ number_format($product->price, 2) }}</span>
            @else
                <span class="price">{{ $currency }} {{ number_format($product->price, 2) }}</span>
            @endif
        </div>
    </div>
</div>
