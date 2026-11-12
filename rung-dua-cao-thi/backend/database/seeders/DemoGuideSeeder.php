<?php

namespace Database\Seeders;

use App\Models\Guide;
use Illuminate\Database\Seeder;

class DemoGuideSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Nguyễn Minh An', 'Trần Thị Bích', 'Lê Quốc Bảo', 'Phạm Thu Cúc', 'Hoàng Văn Dũng',
            'Võ Thị Hạnh', 'Đặng Minh Khang', 'Bùi Ngọc Lan', 'Đỗ Quốc Minh', 'Hồ Thị Nga',
            'Ngô Văn Phúc', 'Dương Thu Quỳnh', 'Lý Minh Sơn', 'Trương Thị Thảo', 'Mai Quốc Vinh',
        ];

        foreach ($names as $index => $name) {
            $number = $index + 1;
            $marker = sprintf('DEMO-SEED:GUIDE-%02d', $number);
            $guide = Guide::withTrashed()->firstOrNew(['notes' => $marker]);

            if (! $guide->exists) {
                $guide->fill([
                    'name' => $name,
                    'phone' => sprintf('0901234%03d', $number),
                    'notes' => $marker,
                    'is_active' => true,
                ])->save();
            } elseif ($guide->trashed()) {
                $guide->restore();
            }
        }
    }
}
