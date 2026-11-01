<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommerceController extends Controller
{
    public function products(Request $request)
    {
        $query = DB::table('commerce_products as p')
            ->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')
            ->where('p.status', 'published')
            ->where('s.status', 'approved')
            ->select('p.*', 's.store_name', 's.slug as store_slug', 's.seller_type', 's.verified as seller_verified', 's.city as seller_city');

        if ($request->filled('q')) {
            $term = '%' . addcslashes($request->string('q')->toString(), '%_') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('p.name', 'like', $term)->orWhere('p.description', 'like', $term)
                    ->orWhere('p.category', 'like', $term)->orWhere('s.store_name', 'like', $term);
            });
        }
        if ($request->filled('category')) $query->where('p.category', $request->string('category')->toString());
        if ($request->filled('city')) $query->where('p.city', $request->string('city')->toString());
        if ($request->filled('seller_type')) $query->where('s.seller_type', $request->string('seller_type')->toString());
        if ($request->boolean('newest')) $query->orderByDesc('p.created_at');
        else $query->orderByDesc('p.featured')->orderByDesc('p.published_at');

        $products = $query->paginate(min(max((int) $request->input('per_page', 20), 1), 50));
        $ids = collect($products->items())->pluck('id');
        $media = DB::table('commerce_product_media')->whereIn('product_id', $ids)->orderBy('position')->get()->groupBy('product_id');
        $products->getCollection()->transform(function ($product) use ($media) {
            $product->media = $media->get($product->id, collect())->values();
            return $product;
        });

        return response()->json(['data' => $products]);
    }

    public function product(string $product)
    {
        $item = DB::table('commerce_products as p')
            ->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')
            ->where('p.slug', $product)->where('p.status', 'published')->where('s.status', 'approved')
            ->select('p.*', 's.store_name', 's.slug as store_slug', 's.seller_type', 's.verified as seller_verified', 's.city as seller_city')
            ->first();
        if (!$item) return response()->json(['message' => 'Product not found.'], 404);
        $item->media = DB::table('commerce_product_media')->where('product_id', $item->id)->orderBy('position')->get();
        $item->reviews = DB::table('commerce_reviews as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.product_id', $item->id)->where('r.status', 'published')
            ->orderByDesc('r.created_at')->limit(20)
            ->get(['r.rating', 'r.body', 'r.verified_purchase', 'r.created_at', 'u.name as reviewer']);
        return response()->json(['data' => $item]);
    }


    public function stores(Request $request)
    {
        $query = DB::table('commerce_sellers')->where('status', 'approved');
        if ($request->filled('q')) $query->where('store_name', 'like', '%' . addcslashes($request->string('q')->toString(), '%_') . '%');
        if ($request->filled('city')) $query->where('city', $request->string('city')->toString());
        if ($request->filled('seller_type')) $query->where('seller_type', $request->string('seller_type')->toString());
        return response()->json(['data' => $query->orderByDesc('verified')->orderBy('store_name')->paginate(20)]);
    }

    public function businessRequests(Request $request)
    {
        $query = DB::table('commerce_business_requests')->where('status', 'open');
        if ($request->filled('request_type')) $query->where('request_type', $request->string('request_type')->toString());
        if ($request->filled('city')) $query->where('city', $request->string('city')->toString());
        return response()->json(['data' => $query->orderByDesc('created_at')->paginate(20)]);
    }

    public function seller(string $slug)
    {
        $seller = DB::table('commerce_sellers')->where('slug', $slug)->where('status', 'approved')->first();
        if (!$seller) return response()->json(['message' => 'Store not found.'], 404);
        $seller->products = DB::table('commerce_products')->where('seller_id', $seller->id)
            ->where('status', 'published')->orderByDesc('published_at')->limit(30)->get();
        return response()->json(['data' => $seller]);
    }

    public function applyAsSeller(Request $request)
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'seller_type' => ['required', Rule::in(['creator', 'retailer', 'manufacturer', 'wholesaler'])],
            'description' => ['nullable', 'string', 'max:3000'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'locality' => ['nullable', 'string', 'max:120'],
        ]);
        $slug = Str::slug($data['store_name']);
        if (DB::table('commerce_sellers')->where('slug', $slug)->exists()) $slug .= '-' . $request->user()->id;
        $seller = DB::table('commerce_sellers')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['store_name' => $data['store_name'], 'slug' => $slug, 'seller_type' => $data['seller_type'],
             'description' => $data['description'] ?? null, 'country_code' => strtoupper($data['country_code'] ?? 'KE'),
             'city' => $data['city'] ?? null, 'locality' => $data['locality'] ?? null,
             'status' => 'pending', 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['message' => 'Seller application submitted for review.', 'status' => 'pending'], 202);
    }

    public function myStore(Request $request)
    {
        $seller = DB::table('commerce_sellers')->where('user_id', $request->user()->id)->first();
        if (!$seller) return response()->json(['data' => null], 200);
        $seller->products = DB::table('commerce_products')->where('seller_id', $seller->id)->orderByDesc('created_at')->get();
        return response()->json(['data' => $seller]);
    }

    public function createProduct(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:100'],
            'product_type' => ['nullable', Rule::in(['physical', 'digital', 'service', 'agriculture', 'equipment', 'property'])],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'wholesale_terms' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'array', 'max:12'],
            'media.*.media_type' => ['required_with:media', Rule::in(['image', 'video'])],
            'media.*.url' => ['required_with:media', 'url', 'max:2048'],
            'media.*.thumbnail_url' => ['nullable', 'url', 'max:2048'],
        ]);
        $seller = DB::table('commerce_sellers')->where('user_id', $request->user()->id)->first();
        if (!$seller || $seller->status !== 'approved') return response()->json(['message' => 'An approved seller account is required to publish products. You may submit a seller application first.'], 403);
        $slug = Str::slug($data['name']) . '-' . Str::lower(Str::random(7));
        $id = DB::table('commerce_products')->insertGetId([
            'seller_id' => $seller->id, 'name' => $data['name'], 'slug' => $slug,
            'description' => $data['description'] ?? null, 'category' => $data['category'],
            'product_type' => $data['product_type'] ?? 'physical', 'price' => $data['price'],
            'currency' => strtoupper($data['currency'] ?? 'KES'), 'stock_quantity' => $data['stock_quantity'] ?? 0,
            'minimum_order_quantity' => $data['minimum_order_quantity'] ?? 1,
            'wholesale_terms' => $data['wholesale_terms'] ?? null, 'status' => 'draft',
            'country_code' => $seller->country_code, 'city' => $data['city'] ?? $seller->city,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($data['media'] ?? [] as $position => $media) {
            DB::table('commerce_product_media')->insert([
                'product_id' => $id, 'media_type' => $media['media_type'], 'url' => $media['url'],
                'thumbnail_url' => $media['thumbnail_url'] ?? null, 'position' => $position,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Product saved as a draft.', 'data' => DB::table('commerce_products')->find($id)], 201);
    }

    public function publishProduct(Request $request, int $product)
    {
        $seller = DB::table('commerce_sellers')->where('user_id', $request->user()->id)->first();
        $item = DB::table('commerce_products')->where('id', $product)->where('seller_id', $seller->id ?? 0)->first();
        if (!$item) return response()->json(['message' => 'Product not found.'], 404);
        DB::table('commerce_products')->where('id', $product)->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Product published.', 'data' => DB::table('commerce_products')->find($product)]);
    }

    public function cart(Request $request)
    {
        $cart = $this->activeCart($request->user()->id);
        return response()->json(['data' => $this->cartPayload($cart->id)]);
    }

    public function addToCart(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:1000']]);
        $product = DB::table('commerce_products')->where('id', $data['product_id'])->where('status', 'published')->first();
        if (!$product) return response()->json(['message' => 'Product is unavailable.'], 404);
        $quantity = $data['quantity'] ?? 1;
        if ($product->stock_quantity < $quantity) return response()->json(['message' => 'Not enough stock is currently available.'], 422);
        $cart = $this->activeCart($request->user()->id);
        $line = DB::table('commerce_cart_items')->where('cart_id', $cart->id)->where('product_id', $product->id)->first();
        $newQuantity = ($line->quantity ?? 0) + $quantity;
        if ($newQuantity > $product->stock_quantity) return response()->json(['message' => 'Not enough stock is currently available.'], 422);
        if ($line) {
            DB::table('commerce_cart_items')->where('id', $line->id)->update(['quantity' => $newQuantity, 'updated_at' => now()]);
        } else {
            DB::table('commerce_cart_items')->insert([
                'cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Added to cart.', 'data' => $this->cartPayload($cart->id)], 201);
    }

    public function updateCartItem(Request $request, int $item)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:1000']]);
        $cart = $this->activeCart($request->user()->id);
        $line = DB::table('commerce_cart_items')->where('id', $item)->where('cart_id', $cart->id)->first();
        if (!$line) return response()->json(['message' => 'Cart item not found.'], 404);
        if ($data['quantity'] === 0) DB::table('commerce_cart_items')->where('id', $item)->delete();
        else DB::table('commerce_cart_items')->where('id', $item)->update(['quantity' => $data['quantity'], 'updated_at' => now()]);
        return response()->json(['data' => $this->cartPayload($cart->id)]);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['required', 'string', 'max:120'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.address' => ['required', 'string', 'max:500'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ]);
        $userId = $request->user()->id;
        $cart = $this->activeCart($userId);
        $order = DB::transaction(function () use ($cart, $userId, $data) {
            $lines = DB::table('commerce_cart_items as ci')->join('commerce_products as p', 'p.id', '=', 'ci.product_id')
                ->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')
                ->where('ci.cart_id', $cart->id)->where('p.status', 'published')->where('s.status', 'approved')
                ->lockForUpdate()->select('ci.quantity', 'p.id as product_id', 'p.name', 'p.price', 'p.currency', 'p.stock_quantity', 'p.minimum_order_quantity', 'p.seller_id')->get();
            if ($lines->isEmpty()) abort(422, 'Your cart is empty or contains unavailable products.');
            $currency = $lines->first()->currency;
            if ($lines->contains(fn ($line) => $line->currency !== $currency)) abort(422, 'A single order must use one currency.');
            $subtotal = 0;
            foreach ($lines as $line) {
                if ($line->quantity < $line->minimum_order_quantity) abort(422, 'A cart item is below its minimum order quantity.');
                if ($line->quantity > $line->stock_quantity) abort(422, 'Stock changed for ' . $line->name . '. Please update your cart.');
                $subtotal += (float) $line->price * $line->quantity;
            }
            $orderId = DB::table('commerce_orders')->insertGetId([
                'user_id' => $userId, 'order_number' => 'GLB-' . now()->format('ymd') . '-' . Str::upper(Str::random(8)),
                'status' => 'pending', 'payment_status' => 'unpaid', 'currency' => $currency,
                'subtotal' => $subtotal, 'delivery_fee' => 0, 'total' => $subtotal,
                'shipping_address' => json_encode($data['shipping_address']),
                'customer_note' => $data['customer_note'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::table('commerce_order_items')->insert([
                    'order_id' => $orderId, 'seller_id' => $line->seller_id, 'product_id' => $line->product_id,
                    'product_name' => $line->name, 'quantity' => $line->quantity, 'unit_price' => $line->price,
                    'line_total' => (float) $line->price * $line->quantity, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('commerce_products')->where('id', $line->product_id)->decrement('stock_quantity', $line->quantity);
            }
            DB::table('commerce_cart_items')->where('cart_id', $cart->id)->delete();
            return DB::table('commerce_orders')->find($orderId);
        });
        return response()->json(['message' => 'Order created. Payment is still pending until a payment provider is configured.', 'data' => $order], 201);
    }

    public function orders(Request $request)
    {
        $orders = DB::table('commerce_orders')->where('user_id', $request->user()->id)->orderByDesc('created_at')->paginate(20);
        foreach ($orders->items() as $order) $order->items = DB::table('commerce_order_items')->where('order_id', $order->id)->get();
        return response()->json(['data' => $orders]);
    }


    public function cancelOrder(Request $request, int $order)
    {
        DB::transaction(function () use ($request, $order) {
            $record = DB::table('commerce_orders')->where('id', $order)
                ->where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (!$record) abort(404, 'Order not found.');
            if ($record->payment_status !== 'unpaid' || !in_array($record->status, ['pending', 'confirmed'], true)) {
                abort(422, 'Only unpaid pending orders can be cancelled by the customer.');
            }
            $items = DB::table('commerce_order_items')->where('order_id', $record->id)->get();
            foreach ($items as $item) {
                if ($item->product_id) {
                    DB::table('commerce_products')->where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
                }
            }
            DB::table('commerce_orders')->where('id', $record->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
        return response()->json(['message' => 'Unpaid order cancelled and reserved stock released.']);
    }

    public function requestQuote(Request $request)
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:commerce_sellers,id'],
            'product_id' => ['nullable', 'integer', 'exists:commerce_products,id'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $id = DB::table('commerce_quote_requests')->insertGetId([
            ...$data, 'user_id' => $request->user()->id, 'status' => 'submitted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Quote request sent.', 'data' => DB::table('commerce_quote_requests')->find($id)], 201);
    }

    public function createBusinessRequest(Request $request)
    {
        $data = $request->validate([
            'request_type' => ['required', Rule::in(['supplier_rfq', 'partnership', 'equipment', 'agriculture', 'property', 'stay', 'service', 'opportunity'])],
            'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:100'], 'country_code' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'], 'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'gte:budget_min'], 'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $id = DB::table('commerce_business_requests')->insertGetId([
            ...$data, 'user_id' => $request->user()->id, 'country_code' => strtoupper($data['country_code'] ?? 'KE'),
            'currency' => strtoupper($data['currency'] ?? 'KES'), 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Business request published.', 'data' => DB::table('commerce_business_requests')->find($id)], 201);
    }

    public function myBusinessRequests(Request $request)
    {
        return response()->json(['data' => DB::table('commerce_business_requests')->where('user_id', $request->user()->id)->orderByDesc('created_at')->paginate(20)]);
    }

    public function saveProduct(Request $request, int $product)
    {
        $exists = DB::table('commerce_products')->where('id', $product)->where('status', 'published')->exists();
        if (!$exists) return response()->json(['message' => 'Product not found.'], 404);
        DB::table('commerce_saved_products')->updateOrInsert(
            ['user_id' => $request->user()->id, 'product_id' => $product],
            ['created_at' => now(), 'updated_at' => now()]
        );
        return response()->json(['message' => 'Product saved.']);
    }

    public function unsaveProduct(Request $request, int $product)
    {
        DB::table('commerce_saved_products')->where('user_id', $request->user()->id)->where('product_id', $product)->delete();
        return response()->json(['message' => 'Product removed from saved items.']);
    }

    public function savedProducts(Request $request)
    {
        $items = DB::table('commerce_saved_products as sp')->join('commerce_products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.user_id', $request->user()->id)->where('p.status', 'published')
            ->orderByDesc('sp.created_at')->select('p.*')->paginate(20);
        return response()->json(['data' => $items]);
    }

    public function review(Request $request)
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:commerce_sellers,id'],
            'product_id' => ['nullable', 'integer', 'exists:commerce_products,id'],
            'rating' => ['required', 'integer', 'min:0', 'max:5'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);
        $verified = DB::table('commerce_orders as o')->join('commerce_order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.user_id', $request->user()->id)->where('oi.seller_id', $data['seller_id'])
            ->when(isset($data['product_id']), fn ($q) => $q->where('oi.product_id', $data['product_id']))
            ->whereIn('o.status', ['paid', 'processing', 'shipped', 'delivered'])->exists();
        $id = DB::table('commerce_reviews')->insertGetId([
            ...$data, 'user_id' => $request->user()->id, 'verified_purchase' => $verified,
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Your review has been published.', 'data' => DB::table('commerce_reviews')->find($id)], 201);
    }

    private function activeCart(int $userId): object
    {
        $cart = DB::table('commerce_carts')->where('user_id', $userId)->where('status', 'active')->first();
        if ($cart) return $cart;
        $id = DB::table('commerce_carts')->insertGetId(['user_id' => $userId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('commerce_carts')->find($id);
    }

    private function cartPayload(int $cartId): array
    {
        $items = DB::table('commerce_cart_items as ci')->join('commerce_products as p', 'p.id', '=', 'ci.product_id')
            ->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')->where('ci.cart_id', $cartId)
            ->select('ci.id as cart_item_id', 'ci.quantity', 'p.id as product_id', 'p.name', 'p.slug', 'p.price', 'p.currency', 'p.stock_quantity', 's.store_name', 's.id as seller_id')->get();
        return ['items' => $items, 'subtotal' => $items->sum(fn ($item) => (float) $item->price * $item->quantity),
            'item_count' => $items->sum('quantity')];
    }
}
