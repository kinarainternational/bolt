# Kinara Reference Sheet

Order management and billing reference system integrated with PlentyMarkets.

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Run migrations and seed data
php artisan migrate
php artisan db:seed

# Build frontend
npm run build

# Or run in development
composer run dev
```

## Business Logic Summary

### Order Filtering

- **Order Types:** Sales Orders only (typeId = 1)
- **Order Status:** Shipped/Delivered only (statusId 7.0 to 7.99)
- **View all statuses:** `php artisan plenty:statuses`

### Per-Order Charges (All Flat Per Order)

| Charge | Amount | Basis |
|--------|--------|-------|
| Warehouse Processing Charge | €0.25 | Per order |
| Picking Charge | €1.62 | Per order |
| Second Pick | €0.30 | Per additional item (Qty - 1) |
| Pack Shipment | €0.71 | Per order |
| Packaging Material | €0.45 | Per order |
| Technology Fee | €0.50 | Per order |
| Tablet Configuration | €5.55 | Per tablet |

### Shipping Rates (FedEx Economy)

| Country | Rate |
|---------|------|
| Poland | €7.09 |
| Czech Republic | €8.00 |
| Romania | €11.71 |
| Bulgaria | €12.19 |
| Latvia | €12.66 |

### Variable Charges (Manual Input)

| Charge | Rate |
|--------|------|
| Warehouse workers | €58/hour |
| Inbound Pallet | €6/pallet |
| Pallet storage | €12/pallet/month |
| Returns | €3/return |
| Reset tablet | €5.55/reset |

### Monthly Fixed Charges (Excluded from Kinara %)

| Charge | Amount |
|--------|--------|
| Portal | €75.00 |
| Account Management Fee | €1,200.00 |

### Kinara Fee

- **Rate:** 8% of subtotal
- **Excludes:** Fixed charges (Portal + Account Management)

### Example Calculation

Order with 2 items (1 tablet, 1 sticker) shipping to Poland:

| Charge | Amount |
|--------|--------|
| Warehouse Processing | €0.25 |
| Picking Charge | €1.62 |
| Second Pick (1 × €0.30) | €0.30 |
| Pack Shipment | €0.71 |
| Packaging Material | €0.45 |
| Technology Fee | €0.50 |
| Tablet Configuration | €5.55 |
| Shipping (Poland) | €7.09 |
| **Total** | **€16.47** |

## PlentyMarkets API Integration

### Authentication

```
POST {base_url}/rest/login
```

Access token cached for 23 hours (token valid for 24 hours).

### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/rest/login` | POST | Authenticate |
| `/rest/orders` | GET | Fetch orders with pagination |
| `/rest/orders/{orderId}` | GET | Fetch single order |
| `/rest/orders/statuses` | GET | Fetch all order statuses |
| `/rest/orders/shipping/countries` | GET | Fetch countries (cached 24h) |

### Order Type Filtering

Only Sales Orders (typeId = 1) are included in billing.

### Status Filtering

Only shipped/delivered orders are billable:

| Status ID | Name | Included |
|-----------|------|----------|
| 7 | Outgoing items booked | Yes |
| 7.01 | Shipment registered | Yes |
| 7.02 | Shipped, tracking allocated | Yes |
| < 7 | Pending/Processing | No |
| 8.x | Cancelled | No |
| 9.x | Returns | No |

**Filter Logic:** `statusId >= 7.0 && statusId < 8.0`

### Tablet Detection

Only one tablet variation:
- **Variation ID 1139:** Bolt Tablet V3

```php
private const int TABLET_VARIATION_ID = 1139;
```

### Item Counting

- Count **total quantity** of items, not unique products
- Only `typeId = 1` (Variation) items are counted
- Example: 3x Tablet + 2x Sticker = **5 items**

## Database Schema

### `kinara_charges` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Display name |
| `slug` | string | Unique identifier |
| `amount` | decimal(10,2) | Amount in EUR |
| `charge_type` | string | `per_order` or `monthly` |
| `calculation_basis` | string | `flat`, `per_additional_item`, or `per_tablet` |
| `is_active` | boolean | Whether charge is active |

### `shipping_rates` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `country_name` | string | Country name |
| `plenty_country_id` | int | PlentyMarkets country ID |
| `amount` | decimal(8,2) | Shipping rate in EUR |
| `carrier` | string | Carrier name (FedEx Economy) |
| `is_active` | boolean | Whether rate is active |

## Environment Configuration

```env
PLENTYSYSTEM_BASE_URL=https://p{YOUR_PID}.my.plentysystems.com
PLENTYSYSTEM_USERNAME=your_username
PLENTYSYSTEM_PASSWORD=your_password
PLENTYSYSTEM_TIMEOUT=30
```

## Artisan Commands

```bash
# List all PlentyMarkets order statuses
php artisan plenty:statuses

# Seed charges and shipping rates
php artisan db:seed --class=KinaraChargeSeeder
php artisan db:seed --class=ShippingRateSeeder
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/OrdersTest.php

# Run with filter
php artisan test --filter=orders
```

## Documentation

- **BUSINESS_LOGIC.md** - Complete calculation documentation
- **EXPORT_QUESTIONS.md** - Questions and answers from management

## Contract Reference

Based on: Services Agreement_Bolt Operations OÜ_Kinara International GmbH_28.10.2025.pdf
