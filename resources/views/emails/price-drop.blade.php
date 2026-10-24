@php($product = $alert->product)
<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#F3F5F4;font-family:Arial,sans-serif;color:#1B2840">
<table role="presentation" width="100%" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #D8DDE3;border-radius:12px">
    <tr><td style="padding:24px">
        <p style="margin:0 0 8px;font-size:14px;color:#4B5869">ShopMate price alert</p>
        <h1 style="margin:0 0 12px;font-size:20px">{{ $product->name }} is now ₹{{ number_format($product->best_price) }}</h1>
        <p style="margin:0 0 16px">You asked us to tell you when it reached ₹{{ number_format($alert->target_price) }}. The lowest price is now at <strong>{{ $product->bestOffer?->store?->name }}</strong>.</p>
        <p style="margin:0 0 20px"><a href="{{ route('products.show', $product) }}" style="background:#1B2840;color:#ffffff;padding:10px 18px;border-radius:999px;text-decoration:none;font-weight:bold">See all store prices</a></p>
        <p style="margin:0;font-size:12px;color:#6F7B8A">This alert has now fired and won't email you again. Prices change often, so check the store before buying.</p>
    </td></tr>
</table>
</body>
</html>
