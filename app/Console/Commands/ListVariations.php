<?php

namespace App\Console\Commands;

use App\Services\PlentySystemService;
use Illuminate\Console\Command;

class ListVariations extends Command
{
    protected $signature = 'plenty:variations';

    protected $description = 'List all product variations from PlentyMarkets';

    public function handle(PlentySystemService $plentySystem): int
    {
        $this->info('Fetching variations from PlentyMarkets...');

        try {
            $variations = $plentySystem->getVariations();

            if (empty($variations)) {
                $this->warn('No variations found.');

                return Command::SUCCESS;
            }

            $this->info('Found '.count($variations).' variations:');
            $this->newLine();

            $rows = [];
            foreach ($variations as $variation) {
                $rows[] = [
                    $variation['id'] ?? '-',
                    $variation['itemId'] ?? '-',
                    $variation['name'] ?? $variation['number'] ?? '-',
                    $variation['number'] ?? '-',
                    $variation['isActive'] ?? false ? 'Yes' : 'No',
                ];
            }

            $this->table(
                ['Variation ID', 'Item ID', 'Name', 'Number', 'Active'],
                $rows
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to fetch variations: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
