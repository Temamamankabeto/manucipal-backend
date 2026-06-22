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
            ['en', 'payment_request_created', 'Payment request created'],
            ['om', 'payment_request_created', 'Gaaffiin kaffaltii uumameera'],
            ['am', 'payment_request_created', 'የክፍያ ጥያቄ ተፈጥሯል'],
            ['en', 'create_payment_request', 'Create Payment Request'],
            ['om', 'create_payment_request', 'Gaaffii Kaffaltii Uumi'],
            ['am', 'create_payment_request', 'የክፍያ ጥያቄ ፍጠር'],
            ['en', 'create_payment_request_description', 'Create the request first. Receiver is selected during submit on the detail page.'],
            ['om', 'create_payment_request_description', 'Dura gaaffii uumi. Fuudhaan yeroo gara fuula balʼinaatti dhiyaatu filatama.'],
            ['am', 'create_payment_request_description', 'መጀመሪያ ጥያቄውን ይፍጠሩ። ተቀባዩ በዝርዝር ገጽ ላይ ሲቀርብ ይመረጣል።'],
            ['en', 'payment_request_information', 'Payment Request Information'],
            ['om', 'payment_request_information', 'Odeeffannoo Gaaffii Kaffaltii'],
            ['am', 'payment_request_information', 'የክፍያ ጥያቄ መረጃ'],
            ['en', 'requesting_entity', 'Requesting Entity'],
            ['om', 'requesting_entity', 'Qaama Gaafatu'],
            ['am', 'requesting_entity', 'ጠያቂ አካል'],
            ['en', 'office_department_organization', 'Office / department / organization'],
            ['om', 'office_department_organization', 'Waajjira / departmentii / dhaabbata'],
            ['am', 'office_department_organization', 'ቢሮ / ክፍል / ድርጅት'],
            ['en', 'payment_category', 'Payment Category'],
            ['om', 'payment_category', 'Gosa Kaffaltii'],
            ['am', 'payment_category', 'የክፍያ ምድብ'],
            ['en', 'select_payment_category', 'Select payment category'],
            ['om', 'select_payment_category', 'Gosa kaffaltii fili'],
            ['am', 'select_payment_category', 'የክፍያ ምድብ ይምረጡ'],
            ['en', 'payment_type', 'Payment Type'],
            ['om', 'payment_type', 'Akaakuu Kaffaltii'],
            ['am', 'payment_type', 'የክፍያ አይነት'],
            ['en', 'select_payment_type', 'Select payment type'],
            ['om', 'select_payment_type', 'Akaakuu kaffaltii fili'],
            ['am', 'select_payment_type', 'የክፍያ አይነት ይምረጡ'],
            ['en', 'select_category_first', 'Select category first'],
            ['om', 'select_category_first', 'Dura gosa fili'],
            ['am', 'select_category_first', 'መጀመሪያ ምድብ ይምረጡ'],
            ['en', 'payment_purpose_justification', 'Payment Purpose / Justification'],
            ['om', 'payment_purpose_justification', 'Kaayyoo / Sababa Kaffaltii'],
            ['am', 'payment_purpose_justification', 'የክፍያ ዓላማ / ምክንያት'],
            ['en', 'write_payment_reason_justification', 'Write payment reason / justification'],
            ['om', 'write_payment_reason_justification', 'Sababa kaffaltii barreessi'],
            ['am', 'write_payment_reason_justification', 'የክፍያ ምክንያት ይጻፉ'],
            ['en', 'documents', 'Documents'],
            ['om', 'documents', 'Dokumentoota'],
            ['am', 'documents', 'ሰነዶች'],
            ['en', 'upload_or_scan_payment_documents', 'Upload or scan payment documents'],
            ['om', 'upload_or_scan_payment_documents', 'Dokumentoota kaffaltii olkaaʼi yookaan skaan godhi'],
            ['am', 'upload_or_scan_payment_documents', 'የክፍያ ሰነዶችን ይጫኑ ወይም ይስካኑ'],
            ['en', 'remove', 'Remove'],
            ['om', 'remove', 'Haqi'],
            ['am', 'remove', 'አስወግድ'],
            ['en', 'saving', 'Saving...'],
            ['om', 'saving', 'Olkaaʼaa jira...'],
            ['am', 'saving', 'በማስቀመጥ ላይ...'],
            ['en', 'save_draft', 'Save Draft'],
            ['om', 'save_draft', 'Akka qopheessaatti olkaaʼi'],
            ['am', 'save_draft', 'ረቂቅ አስቀምጥ'],
        ];

        foreach ($items as [$language, $key, $value]) {
            Translation::updateOrCreate(
                ['language' => $language, 'translation_key' => $key],
                ['translation_value' => $value]
            );
        }
    }
}
