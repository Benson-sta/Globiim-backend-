# Globiim Commerce Expansion API

Base path: `/api/v1/commerce`. Protected routes require a Laravel Sanctum bearer token.

## Local discovery
- `GET /local/businesses?q=&city=&locality=&seller_type=` — approved local sellers, searchable by town/neighbourhood.

## Video commerce
- `GET /watch-shop?q=&category=` — published creator videos linked to published products.
- `POST /products/{productId}/videos` — authenticated creator or seller submits a product video for moderation. Video and thumbnail are URL fields; binary uploads/CDN signing are not configured.

## Wider marketplace listings
- `GET /listings?type=&city=&q=` — published agriculture, equipment, property, stay, service and opportunity listings.
- `GET /listings/{slug}` — listing details.
- `POST /listings` — approved seller creates a draft listing.
- `POST /listings/{id}/publish` — owner publishes their draft.
- `POST /listings/{id}/bookings` — request a property viewing, stay, rental or service enquiry.
- `GET /bookings/mine` — current user's enquiries.

Listing types are `agriculture`, `equipment`, `property`, `stay`, `service`, and `opportunity`. Attributes and media are JSON arrays/objects so each category can have its own structured fields without forcing every listing into a product shape.

## Partner Network
- `GET /partner-network?type=&q=` — approved business partner directory.
- `POST /partner-network/{partnerId}/applications` — submit an application/proposal.

Partner profile approval and applications management are administrative workflows and are not exposed to ordinary users.

## Delivery tracking
- `GET /orders/{orderId}/shipments` — buyer reads courier shipment records for their own order. This does not let buyers choose a rider. Courier dispatch and live tracking provider integration are not connected.

## Important boundaries
- Existing Commerce v1 endpoints remain in place for product catalogue, stores, seller applications, cart, checkout, orders, RFQs, saved items and reviews.
- Checkout currently creates an unpaid order. No payment gateway, refunds, tax calculation, courier dispatch, taxi dispatch, seller payouts or creator commission settlement is activated.
- Product/listing/video media are URL-based; configure secure object storage, signed upload URLs and media moderation before accepting uploads.
- Product videos are pending moderation by default; admin moderation endpoints are not yet included.
- These files are source code only. Run Composer install, migrations, PHP lint and API tests in a configured Laravel environment before frontend connection or production use.
