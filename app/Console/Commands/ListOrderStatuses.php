<?php

namespace App\Console\Commands;

use App\Services\PlentySystemService;
use Illuminate\Console\Command;

class ListOrderStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plenty:statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all order statuses from PlentyMarkets';

    /**
     * Execute the console command.
     */
    public function handle(PlentySystemService $plentySystem): int
    {
        $this->info('Fetching order statuses from PlentyMarkets...');

        try {
            $response = $plentySystem->getOrderStatuses();
            $statuses = $response['entries'] ?? [];

            if (empty($statuses)) {
                $this->warn('No statuses found.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($statuses as $status) {
                $names = $status['names'] ?? [];
                $name = $names['en'] ?? $names['de'] ?? 'Unknown';

                $rows[] = [
                    $status['statusId'] ?? 'N/A',
                    $name,
                ];
            }

            // Sort by statusId
            usort($rows, fn ($a, $b) => floatval($a[0]) <=> floatval($b[0]));

            $this->table(
                ['Status ID', 'Name (EN)'],
                $rows
            );

            $this->info('Total: '.count($statuses).' statuses');
            $this->newLine();
            $this->info('Look for statuses like "Shipped", "Completed", "Delivered" to use for billing.');

        } catch (\Exception $e) {
            $this->error('Failed to fetch statuses: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
