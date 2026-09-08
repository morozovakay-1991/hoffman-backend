<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    private const PLACEHOLDER_BODY = 'Текст документа появится здесь — согласуется с юристом.';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documents = [
            LegalDocument::SLUG_PRIVACY => [
                'ru' => 'Политика конфиденциальности',
                'en' => 'Privacy Policy',
            ],
            LegalDocument::SLUG_TERMS => [
                'ru' => 'Условия использования',
                'en' => 'Terms of Use',
            ],
            LegalDocument::SLUG_LICENSE => [
                'ru' => 'Лицензионное соглашение',
                'en' => 'License Agreement',
            ],
        ];

        foreach ($documents as $slug => $title) {
            LegalDocument::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'body' => [
                        'ru' => self::PLACEHOLDER_BODY,
                        'en' => self::PLACEHOLDER_BODY,
                    ],
                ],
            );
        }
    }
}
