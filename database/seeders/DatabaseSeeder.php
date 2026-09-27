<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * ممنوع WithoutModelEvents هنا: موديل Vehicle بيولّد الـ slug في حدث creating،
     * ولو الأحداث اتقفلت العربيات بتتسجل من غير slug وصفحات الموقع بتقع.
     */
    public function run(): void
    {
        $this->call([
            CreateTestUsersSeeder::class,   // أدمن + موظف + عميل
            DemoStorefrontSeeder::class,    // ماركات وموديلات و10 عربيات معروضة
        ]);
    }
}