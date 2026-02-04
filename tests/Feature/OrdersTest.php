<?php

namespace Tests\Feature;

use App\Services\PlentySystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_page_displays_grouped_orders_by_country(): void
    {
        $this->mock(PlentySystemService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getOrdersForDateRange')
                ->once()
                ->andReturn([
                    [
                        'id' => 1,
                        'typeId' => 1,
                        'statusId' => 5,
                        'statusName' => 'Shipped',
                        'createdAt' => '2024-01-15T10:30:00+00:00',
                        'updatedAt' => '2024-01-16T08:00:00+00:00',
                        'plentyId' => 12345,
                        'addresses' => [
                            ['id' => 100, 'countryId' => 1],
                        ],
                        'addressRelations' => [
                            ['typeId' => 1, 'addressId' => 100],
                            ['typeId' => 2, 'addressId' => 100],
                        ],
                        'amounts' => [
                            ['currency' => 'EUR', 'grossTotal' => 99.99, 'netTotal' => 83.19],
                        ],
                    ],
                    [
                        'id' => 2,
                        'typeId' => 1,
                        'statusId' => 5,
                        'statusName' => 'Shipped',
                        'createdAt' => '2024-01-15T11:30:00+00:00',
                        'updatedAt' => '2024-01-16T09:00:00+00:00',
                        'plentyId' => 12345,
                        'addresses' => [
                            ['id' => 101, 'countryId' => 1],
                        ],
                        'addressRelations' => [
                            ['typeId' => 1, 'addressId' => 101],
                            ['typeId' => 2, 'addressId' => 101],
                        ],
                        'amounts' => [
                            ['currency' => 'EUR', 'grossTotal' => 50.00, 'netTotal' => 42.00],
                        ],
                    ],
                ]);

            $mock->shouldReceive('extractOrderCountries')
                ->twice()
                ->andReturn([
                    'billing_country_id' => 1,
                    'billing_country_name' => 'Germany',
                    'delivery_country_id' => 1,
                    'delivery_country_name' => 'Germany',
                ]);
        });

        $response = $this->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('groupedOrders', 1)
            ->has('totalOrders')
            ->has('filters')
            ->has('availableMonths')
            ->where('totalOrders', 2)
            ->where('groupedOrders.0.country_name', 'Germany')
            ->where('groupedOrders.0.order_count', 2)
        );
    }

    public function test_orders_page_handles_empty_orders(): void
    {
        $this->mock(PlentySystemService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getOrdersForDateRange')
                ->once()
                ->andReturn([]);
        });

        $response = $this->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('groupedOrders', 0)
            ->where('totalOrders', 0)
        );
    }

    public function test_orders_page_accepts_month_filter(): void
    {
        $this->mock(PlentySystemService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getOrdersForDateRange')
                ->once()
                ->andReturn([]);
        });

        $response = $this->get(route('orders.index', ['year' => 2024, 'month' => 6]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.year', 2024)
            ->where('filters.month', 6)
        );
    }

    public function test_orders_are_grouped_by_delivery_country(): void
    {
        $this->mock(PlentySystemService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getOrdersForDateRange')
                ->once()
                ->andReturn([
                    [
                        'id' => 1,
                        'typeId' => 1,
                        'statusId' => 5,
                        'createdAt' => '2024-01-15T10:30:00+00:00',
                        'updatedAt' => '2024-01-16T08:00:00+00:00',
                        'plentyId' => 12345,
                        'addresses' => [['id' => 100, 'countryId' => 1]],
                        'addressRelations' => [['typeId' => 2, 'addressId' => 100]],
                        'amounts' => [['currency' => 'EUR', 'grossTotal' => 100, 'netTotal' => 84]],
                    ],
                    [
                        'id' => 2,
                        'typeId' => 1,
                        'statusId' => 5,
                        'createdAt' => '2024-01-15T11:30:00+00:00',
                        'updatedAt' => '2024-01-16T09:00:00+00:00',
                        'plentyId' => 12345,
                        'addresses' => [['id' => 101, 'countryId' => 6]],
                        'addressRelations' => [['typeId' => 2, 'addressId' => 101]],
                        'amounts' => [['currency' => 'EUR', 'grossTotal' => 200, 'netTotal' => 168]],
                    ],
                ]);

            $mock->shouldReceive('extractOrderCountries')
                ->once()
                ->andReturn([
                    'billing_country_id' => null,
                    'billing_country_name' => null,
                    'delivery_country_id' => 1,
                    'delivery_country_name' => 'Germany',
                ]);

            $mock->shouldReceive('extractOrderCountries')
                ->once()
                ->andReturn([
                    'billing_country_id' => null,
                    'billing_country_name' => null,
                    'delivery_country_id' => 6,
                    'delivery_country_name' => 'Czech Republic',
                ]);
        });

        $response = $this->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('groupedOrders', 2)
            ->where('totalOrders', 2)
        );
    }
}
