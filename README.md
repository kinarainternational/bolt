# Kinara Reference Sheet

Order management and billing reference system integrated with PlentyMarkets.

## PlentyMarkets API Integration

### Authentication

The system authenticates with PlentyMarkets REST API using OAuth 2.0:

```
POST {base_url}/rest/login
```

**Request Body:**
```json
{
    "username": "your_username",
    "password": "your_password"
}
```

**Response:**
```json
{
    "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "tokenType": "Bearer",
    "expiresIn": 86400,
    "refreshToken": "..."
}
```

The access token is cached for 23 hours (token valid for 24 hours).

### API Endpoints Used

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/rest/login` | POST | Authenticate and obtain access token |
| `/rest/orders` | GET | Fetch orders with pagination |
| `/rest/orders/{orderId}` | GET | Fetch a single order |
| `/rest/orders/shipping/countries` | GET | Fetch all countries (cached 24 hours) |

### Fetching Orders

Orders are fetched for a specific date range with pagination:

```
GET {base_url}/rest/orders?with[]=addresses&with[]=addressRelations&createdAtFrom={start}&createdAtTo={end}&page={page}&itemsPerPage=250
```

**Query Parameters:**
- `with[]` - Include related data: `addresses`, `addressRelations`
- `createdAtFrom` - Start date (ISO 8601 format)
- `createdAtTo` - End date (ISO 8601 format)
- `page` - Page number for pagination
- `itemsPerPage` - Items per page (max 250)

**Response Structure:**
```json
{
    "page": 1,
    "totalsCount": 150,
    "isLastPage": false,
    "entries": [
        {
            "id": 12345,
            "typeId": 1,
            "statusId": 5,
            "statusName": "Shipped",
            "createdAt": "2024-01-15T10:30:00+00:00",
            "plentyId": 71370,
            "addresses": [...],
            "addressRelations": [...],
            "orderItems": [...],
            "amounts": [...]
        }
    ]
}
```

### Order Structure

#### Address Relations
Address relations link addresses to their purpose:
- `typeId: 1` = Billing address
- `typeId: 2` = Delivery address

```json
{
    "addressRelations": [
        { "typeId": 1, "addressId": 100 },
        { "typeId": 2, "addressId": 101 }
    ],
    "addresses": [
        { "id": 100, "countryId": 1, "name1": "Company", ... },
        { "id": 101, "countryId": 6, "name1": "Company", ... }
    ]
}
```

#### Order Items
Each order contains items with variation information:

```json
{
    "orderItems": [
        {
            "typeId": 1,
            "itemVariationId": 1234,
            "quantity": 2,
            "orderItemName": "Product Name"
        }
    ]
}
```

- `typeId: 1` = Product item (used for SKU counting)
- `typeId: 6` = Shipping item (excluded from SKU count)

#### Tablet Detection
Tablets are identified by their variation ID:
- **Tablet Variation ID:** `1139`

```php
// In OrdersController.php
private const TABLET_VARIATION_ID = 1139;

private function orderHasTablet(array $order): bool
{
    foreach ($order['orderItems'] as $item) {
        if ($item['itemVariationId'] === self::TABLET_VARIATION_ID) {
            return true;
        }
    }
    return false;
}
```

## Charge Calculations

### Database Schema: `kinara_charges`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Display name |
| `slug` | string | Unique identifier |
| `amount` | decimal(10,2) | Charge amount in EUR |
| `tablet_only` | boolean | Only applies to tablet orders |
| `charge_type` | string | `per_order` or `monthly` |
| `is_active` | boolean | Whether charge is active |

### Per-Order Charges

Applied to each order. Calculated in `KinaraCharge::calculateOrderTotal()`:

| Charge | Amount (EUR) | Tablet Only |
|--------|--------------|-------------|
| Picking Charge | 1.53 | No |
| Shipping Charge | 1.62 | No |
| Tablet Configuration | 3.50 | Yes |
| Packaging Material | 0.45 | No |

**Calculation Logic:**
```php
public static function calculateOrderTotal(bool $hasTablet = false): float
{
    $query = self::query()
        ->where('is_active', true)
        ->where('charge_type', 'per_order');

    if (!$hasTablet) {
        $query->where('tablet_only', false);
    }

    return (float) $query->sum('amount');
}
```

**Results:**
- Order without tablet: **3.60 EUR** (1.53 + 1.62 + 0.45)
- Order with tablet: **7.10 EUR** (1.53 + 1.62 + 3.50 + 0.45)

### Monthly Fixed Charges

Fixed fees applied once per month:

| Charge | Amount (EUR) |
|--------|--------------|
| Portal | 500.00 |
| Account Management Fee | 1,500.00 |
| **Total** | **2,000.00** |

**Calculation:**
```php
public static function calculateMonthlyTotal(): float
{
    return (float) self::query()
        ->where('is_active', true)
        ->where('charge_type', 'monthly')
        ->sum('amount');
}
```

### Grand Total Calculation

```
Grand Total = Sum of all order charges + Monthly fixed charges
```

In the Vue component:
```typescript
const totalOrderCharges = computed(() => {
    return props.groupedOrders.reduce(
        (sum, group) => sum + group.total_charges,
        0,
    );
});

const grandTotal = computed(() => {
    return totalOrderCharges.value + props.monthlyTotal;
});
```

## Data Flow

```
┌─────────────────────┐
│  PlentyMarkets API  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ PlentySystemService │
│  - Authentication   │
│  - Fetch orders     │
│  - Extract countries│
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  OrdersController   │
│  - Group by country │
│  - Count SKUs       │
│  - Detect tablets   │
│  - Calculate charges│
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│   KinaraCharge      │
│  (Database Model)   │
│  - Per-order charges│
│  - Monthly charges  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Orders/Index.vue   │
│  - Display grouped  │
│  - Show charges     │
│  - Month filter     │
└─────────────────────┘
```

## Environment Configuration

Required environment variables in `.env`:

```env
PLENTYSYSTEM_BASE_URL=https://p{YOUR_PID}.my.plentysystems.com
PLENTYSYSTEM_USERNAME=your_username
PLENTYSYSTEM_PASSWORD=your_password
PLENTYSYSTEM_TIMEOUT=30
```

## Running the Application

```bash
# Install dependencies
composer install
npm install

# Run migrations and seed charges
php artisan migrate
php artisan db:seed --class=KinaraChargeSeeder

# Build frontend
npm run build

# Or run in development
npm run dev
```

## Testing

```bash
# Run all tests
php artisan test

# Run orders tests only
php artisan test tests/Feature/OrdersTest.php
```
