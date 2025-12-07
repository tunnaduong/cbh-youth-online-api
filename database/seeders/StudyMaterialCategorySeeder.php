<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StudyMaterialCategory;
use Illuminate\Support\Str;

class StudyMaterialCategorySeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $categories = [
      [
        'name' => 'Toán học',
        'description' => 'Tài liệu ôn thi môn Toán',
        'order' => 1,
      ],
      [
        'name' => 'Vật lý',
        'description' => 'Tài liệu ôn thi môn Vật lý',
        'order' => 2,
      ],
      [
        'name' => 'Hóa học',
        'description' => 'Tài liệu ôn thi môn Hóa học',
        'order' => 3,
      ],
      [
        'name' => 'Sinh học',
        'description' => 'Tài liệu ôn thi môn Sinh học',
        'order' => 4,
      ],
      [
        'name' => 'Ngữ văn',
        'description' => 'Tài liệu ôn thi môn Ngữ văn',
        'order' => 5,
      ],
      [
        'name' => 'Lịch sử',
        'description' => 'Tài liệu ôn thi môn Lịch sử',
        'order' => 6,
      ],
      [
        'name' => 'Địa lý',
        'description' => 'Tài liệu ôn thi môn Địa lý',
        'order' => 7,
      ],
      [
        'name' => 'Tiếng Anh',
        'description' => 'Tài liệu ôn thi môn Tiếng Anh',
        'order' => 8,
      ],
      [
        'name' => 'Tin học',
        'description' => 'Tài liệu ôn thi môn Tin học',
        'order' => 9,
      ],
      [
        'name' => 'Khác',
        'description' => 'Các tài liệu khác',
        'order' => 10,
      ],
    ];

    foreach ($categories as $category) {
      StudyMaterialCategory::create([
        'name' => $category['name'],
        'description' => $category['description'],
        'slug' => Str::slug($category['name']),
        'order' => $category['order'],
      ]);
    }
  }
}


