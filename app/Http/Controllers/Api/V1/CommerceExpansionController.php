<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommerceExpansionController extends Controller
{
    public function localBusinesses(Request $request)
    {
        $query = DB::table('commerce_sellers as s')
            ->where('s.status', 'approved')
            ->select('s.id', 's.store_name', 's.slug', 's.description', 's.seller_type',
                's.verified', 's.city', 's.locality', 's.latitude', 's.longitude');

        if ($request->filled('q')) {
            $term = '%' . addcslashes($request->string('q')->toString(), '%_') . '%';
            $query->where(fn ($q) => $q->where('s.store_name', 'like', $term)
                ->orWhere('s.description', 'like', $term)->orWhere('s.locality', 'like', $term));
        }
        if ($request->filled('city')) $query->where('s.city', $request->string('city')->toString());
        if ($request->filled('locality')) $query->where('s.locality', $request->string('locality')->toString());
        if ($request->filled('seller_type')) $query->where('s.seller_type', $request->string('seller_type')->toString());

        return response()->json(['data' => $query->orderByDesc('s.verified')->orderBy('s.store_name')->paginate(24)]);
    }

    public function watchAndShop(Request $request)
    {
        $query = DB::table('commerce_product_videos as v')
            ->join('commerce_products as p', 'p.id', '=', 'v.product_id')
            ->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')
            ->join('users as u', 'u.id', '=', 'v.creator_user_id')
            ->where('v.status', 'published')->where('p.status', 'published')->where('s.status', 'approved')
            ->select('v.id', 'v.video_url', 'v.thumbnail_url', 'v.caption', 'v.views_count',
                'p.id as product_id', 'p.name as product_name', 'p.slug as product_slug',
                'p.price', 'p.currency', 'p.category', 's.store_name', 's.slug as store_slug',
                'u.id as creator_id', 'u.name as creator_name');

        if ($request->filled('category')) $query->where('p.category', $request->string('category')->toString());
        if ($request->filled('q')) {
            $term = '%' . addcslashes($request->string('q')->toString(), '%_') . '%';
            $query->where(fn ($q) => $q->where('p.name', 'like', $term)->orWhere('v.caption', 'like', $term));
        }

        return response()->json(['data' => $query->orderByDesc('v.created_at')->paginate(20)]);
    }

    public function createProductVideo(Request $request, int $product)
    {
        $data = $request->validate([
            'video_url' => ['required', 'url', 'max:2048'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $item = DB::table('commerce_products as p')->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')
            ->where('p.id', $product)->where('p.status', 'published')->where('s.status', 'approved')
            ->select('p.id', 'p.seller_id')->first();
        if (!$item) return response()->json(['message' => 'Published product not found.'], 404);

        $isOwner = DB::table('commerce_sellers')->where('id', $item->seller_id)
            ->where('user_id', $request->user()->id)->exists();
        if (!$isOwner && ($request->user()->status ?? null) !== 'active') {
            return response()->json(['message' => 'An active account is required to attach creator content.'], 403);
        }

        $id = DB::table('commerce_product_videos')->insertGetId([
            'product_id' => $product, 'creator_user_id' => $request->user()->id,
            'video_url' => $data['video_url'], 'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'caption' => $data['caption'] ?? null, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Product video submitted for moderation.', 'data' => DB::table('commerce_product_videos')->find($id)], 201);
    }

    public function listings(Request $request)
    {
        $query = DB::table('commerce_marketplace_listings as l')
            ->join('commerce_sellers as s', 's.id', '=', 'l.seller_id')
            ->where('l.status', 'published')->where('s.status', 'approved')
            ->select('l.*', 's.store_name', 's.slug as store_slug', 's.verified as seller_verified');
        if ($request->filled('type')) $query->where('l.listing_type', $request->string('type')->toString());
        if ($request->filled('city')) $query->where('l.city', $request->string('city')->toString());
        if ($request->filled('q')) {
            $term = '%' . addcslashes($request->string('q')->toString(), '%_') . '%';
            $query->where(fn ($q) => $q->where('l.title', 'like', $term)->orWhere('l.description', 'like', $term));
        }
        return response()->json(['data' => $query->orderByDesc('l.published_at')->paginate(24)]);
    }

    public function listing(string $slug)
    {
        $listing = DB::table('commerce_marketplace_listings as l')
            ->join('commerce_sellers as s', 's.id', '=', 'l.seller_id')
            ->where('l.slug', $slug)->where('l.status', 'published')->where('s.status', 'approved')
            ->select('l.*', 's.store_name', 's.slug as store_slug', 's.verified as seller_verified')->first();
        if (!$listing) return response()->json(['message' => 'Listing not found.'], 404);
        return response()->json(['data' => $listing]);
    }

    public function createListing(Request $request)
    {
        $data = $request->validate([
            'listing_type' => ['required', Rule::in(['agriculture', 'equipment', 'property', 'stay', 'service', 'opportunity'])],
            'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:10000'],
            'price' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'],
            'price_period' => ['nullable', Rule::in(['once', 'night', 'month', 'acre', 'day', 'hour'])],
            'city' => ['nullable', 'string', 'max:100'], 'locality' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'], 'attributes' => ['nullable', 'array'],
            'media' => ['nullable', 'array', 'max:20'],
        ]);
        $seller = DB::table('commerce_sellers')->where('user_id', $request->user()->id)->where('status', 'approved')->first();
        if (!$seller) return response()->json(['message' => 'An approved seller/business profile is required.'], 403);
        $slug = Str::slug($data['title']) . '-' . Str::lower(Str::random(7));
        $id = DB::table('commerce_marketplace_listings')->insertGetId([
            ...$data, 'seller_id' => $seller->id, 'slug' => $slug,
            'currency' => strtoupper($data['currency'] ?? 'KES'),
            'country_code' => strtoupper($data['country_code'] ?? 'KE'),
            'attributes' => isset($data['attributes']) ? json_encode($data['attributes']) : null,
            'media' => isset($data['media']) ? json_encode($data['media']) : null,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Listing saved as a draft.', 'data' => DB::table('commerce_marketplace_listings')->find($id)], 201);
    }

    public function publishListing(Request $request, int $listing)
    {
        $seller = DB::table('commerce_sellers')->where('user_id', $request->user()->id)->first();
        $record = DB::table('commerce_marketplace_listings')->where('id', $listing)
            ->where('seller_id', $seller->id ?? 0)->first();
        if (!$record) return response()->json(['message' => 'Listing not found.'], 404);
        DB::table('commerce_marketplace_listings')->where('id', $listing)
            ->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Listing published.', 'data' => DB::table('commerce_marketplace_listings')->find($listing)]);
    }

    public function requestListingBooking(Request $request, int $listing)
    {
        $data = $request->validate([
            'booking_type' => ['required', Rule::in(['viewing', 'stay_enquiry', 'rental_enquiry', 'service_enquiry'])],
            'requested_start' => ['nullable', 'date', 'after_or_equal:now'],
            'requested_end' => ['nullable', 'date', 'after:requested_start'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);
        $exists = DB::table('commerce_marketplace_listings')->where('id', $listing)->where('status', 'published')->exists();
        if (!$exists) return response()->json(['message' => 'Listing not found.'], 404);
        $id = DB::table('commerce_listing_bookings')->insertGetId([
            ...$data, 'listing_id' => $listing, 'user_id' => $request->user()->id,
            'status' => 'requested', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Enquiry submitted. The listing owner can respond through Commerce and Messaging.', 'data' => DB::table('commerce_listing_bookings')->find($id)], 201);
    }

    public function myBookings(Request $request)
    {
        return response()->json(['data' => DB::table('commerce_listing_bookings as b')
            ->join('commerce_marketplace_listings as l', 'l.id', '=', 'b.listing_id')
            ->where('b.user_id', $request->user()->id)->orderByDesc('b.created_at')
            ->select('b.*', 'l.title as listing_title', 'l.slug as listing_slug')->paginate(20)]);
    }

    public function partnerDirectory(Request $request)
    {
        $query = DB::table('commerce_partner_profiles as p')
            ->join('commerce_sellers as s', 's.id', '=', 'p.seller_id')
            ->where('p.status', 'approved')->where('s.status', 'approved')
            ->select('p.*', 's.store_name', 's.slug as store_slug', 's.city', 's.verified');
        if ($request->filled('type')) $query->where('p.partner_type', $request->string('type')->toString());
        if ($request->filled('q')) {
            $term = '%' . addcslashes($request->string('q')->toString(), '%_') . '%';
            $query->where(fn ($q) => $q->where('p.headline', 'like', $term)->orWhere('p.description', 'like', $term));
        }
        return response()->json(['data' => $query->orderBy('p.headline')->paginate(24)]);
    }

    public function applyToPartner(Request $request, int $partner)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:5', 'max:5000'], 'proposal' => ['nullable', 'array']]);
        $profile = DB::table('commerce_partner_profiles')->where('id', $partner)->where('status', 'approved')->first();
        if (!$profile) return response()->json(['message' => 'Partner profile not found.'], 404);
        $ownsPartner = DB::table('commerce_sellers')->where('id', $profile->seller_id)
            ->where('user_id', $request->user()->id)->exists();
        if ($ownsPartner) {
            return response()->json(['message' => 'You cannot apply to your own partner profile.'], 422);
        }
        $id = DB::table('commerce_partner_applications')->insertGetId([
            'partner_profile_id' => $partner, 'applicant_user_id' => $request->user()->id,
            'message' => $data['message'], 'proposal' => isset($data['proposal']) ? json_encode($data['proposal']) : null,
            'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Partner application submitted.', 'data' => DB::table('commerce_partner_applications')->find($id)], 201);
    }

    public function myShipments(Request $request, int $order)
    {
        $owned = DB::table('commerce_orders')->where('id', $order)->where('user_id', $request->user()->id)->exists();
        if (!$owned) return response()->json(['message' => 'Order not found.'], 404);
        return response()->json(['data' => DB::table('commerce_shipments')->where('order_id', $order)->orderBy('id')->get()]);
    }
}
