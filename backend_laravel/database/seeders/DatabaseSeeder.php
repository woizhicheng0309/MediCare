<?php
// Seeders 建立預先準備好的資料
namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
        DemoSeeder::class, // the order matters here, benefits should comes after
        BenefitSeeder::class,
    ]   );
    }
}
