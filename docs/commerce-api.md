# Globiim Commerce API (v1)

Base path: `/api/v1/commerce`

All authenticated endpoints require a Laravel Sanctum bearer token:
`Authorization: Bearer <token>`

## Public discovery

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/products` | Product search and catalog |
| GET | `/products/{slug}` | Product detail, media and published reviews |
| GET | `/stores` | Approved stores; supports `q`, `city`, `seller_type` |
| GET | `/stores/{slug}` | Store profile and published products |
| GET | `/business-requests` | Open supplier RFQs and business requests |

Product listing filters: `q`, `category`, `city`, `seller_type`, `newest=1`, `per_page` (max 50). Results are paginated. Product media records may include image or video URLs; this supports Watch & Shop product video attachments.

## Authenticated seller endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/seller/apply` | Apply for Creator, Retailer, Manufacturer or Wholesaler seller account |
| GET | `/seller/me` | Current user's seller profile and listings |
| POST | `/products` | Create a product draft with optional media |
| POST | `/products/{id}/publish` | Publish an owned draft after seller approval |

Seller application JSON:
```json
{
  "store_name": "Example Store",
  "seller_type": "manufacturer",
  "description": "What the business makes",
  "country_code": "KE",
  "city": "Nairobi",
  "locality": "Industrial Area"
}
```

A seller must have an approved seller profile before product creation. New products are saved as drafts. Seller approval and product moderation must be performed by an authorized admin workflow before launch; this API does not expose admin approval routes.

Product JSON supports `name`, `description`, `category`, `product_type`, `price`, `currency`, `stock_quantity`, `minimum_order_quantity`, `wholesale_terms`, `city` and `media[]`. Product types: physical, digital, service, agriculture, equipment, property. Media types: image, video. Media URLs must point to configured storage/CDN assets; this API does not upload binary files yet.

## Buyer endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/cart` | Read active cart |
| POST | `/cart/items` | Add product: `product_id`, optional `quantity` |
| PATCH | `/cart/items/{cartItemId}` | Set quantity; zero removes item |
| POST | `/checkout` | Create pending order from active cart |
| GET | `/orders` | Current user's order history |
| POST | `/orders/{orderId}/cancel` | Cancel an unpaid pending order and release reserved stock |
| POST | `/saved/{productId}` | Save product |
| DELETE | `/saved/{productId}` | Unsave product |
| GET | `/saved` | Saved products |
| POST | `/reviews` | Publish a 0–5 rating and optional written review |

Checkout requires `shipping_address.name`, `shipping_address.phone`, `shipping_address.address`, and `shipping_address.city`. Orders are created with `payment_status=unpaid`; delivery fee is currently zero. No payment is collected and no courier is booked. Configure and test payment, tax, delivery, refunds and seller settlement providers before accepting real orders.

## Enquiries and business network

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/quotes` | Send a product/seller quotation request |
| POST | `/business-requests` | Publish supplier RFQ, partnership, equipment, agriculture, property, stay, service or opportunity request |
| GET | `/business-requests/mine` | List the signed-in user's requests |

Quote JSON: `seller_id`, optional `product_id`, `message`.

Business request JSON: `request_type`, `title`, `description`, optional `category`, `country_code`, `city`, `budget_min`, `budget_max`, `currency`.

## Current implementation boundaries

- Laravel 12 + MySQL schema; protected endpoints use Sanctum.
- Cart and order writes use database transactions where stock is reserved/decremented.
- Checkout creates an unpaid order and reserves inventory. Customers can cancel an unpaid pending order to release stock. Payment gateway, refunds, delivery tracking, seller payouts and courier/taxi dispatch are not connected.
- Store approval/admin moderation, inventory imports, multi-warehouse fulfilment, affiliate commissions, creator revenue sharing, real-estate viewing workflows, accommodation bookings and external sourcing integrations are future phases.
- Reviews accept 0-star ratings as requested; verified-purchase status is derived from the user's order history. Reviews are not filtered by positive/negative sentiment.
- Media currently accepts URLs only. Add private upload/signing and malware/content validation before production use.
- Run `composer install`, `php artisan migrate`, PHP lint and automated API tests in a configured environment before connecting the frontend.
