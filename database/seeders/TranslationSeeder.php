<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['en', 'payments', 'Payments'], ['om', 'payments', 'Kaffaltiiwwan'], ['am', 'payments', 'ክፍያዎች'],
            ['en', 'procurements', 'Procurements'], ['om', 'procurements', 'Bittaawwan'], ['am', 'procurements', 'ግዢዎች'],
            ['en', 'budgets', 'Budgets'], ['om', 'budgets', 'Baajata'], ['am', 'budgets', 'በጀቶች'],
            ['en', 'translations', 'Translations'], ['om', 'translations', 'Hiikkaa'], ['am', 'translations', 'ትርጉሞች'],
        ];

        foreach ($items as [$language, $key, $value]) {
            Translation::updateOrCreate(
                ['language' => $language, 'translation_key' => $key],
                ['translation_value' => $value]
            );
        }
    }
}
