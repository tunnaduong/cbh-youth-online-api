<?php

namespace Database\Seeders;

use App\Models\ShopCategory;
use App\Models\ShopProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShopSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $categories = [
      [
        'name' => 'Thời trang',
        'description' => 'Áo thun, áo khoác đồng phục CBH thiết kế độc quyền.',
      ],
      [
        'name' => 'Phụ kiện',
        'description' => 'Móc chìa khóa, huy hiệu, sổ tay kỷ niệm.',
      ],
      [
        'name' => 'Khác',
        'description' => 'Các vật dụng lưu niệm khác.',
      ],
    ];

    foreach ($categories as $cat) {
      $category = ShopCategory::create([
        'name' => $cat['name'],
        'slug' => Str::slug($cat['name']),
        'description' => $cat['description'],
      ]);

      if ($cat['name'] === 'Thời trang') {
        ShopProduct::create([
          'name' => 'Áo thun Chuyên Biên Hòa 2024',
          'slug' => 'ao-thun-cbh-2024',
          'description' => 'Áo thun chất liệu cotton 100%, co giãn 4 chiều, in hình logo trường cách điệu.',
          'price' => 150000,
          'stock' => 100,
          'category_id' => $category->id,
          'is_active' => true,
        ]);

        ShopProduct::create([
          'name' => 'Áo Hoodie CBH Youth',
          'slug' => 'ao-hoodie-cbh-youth',
          'description' => 'Áo hoodie nỉ bông cao cấp, giữ ấm tốt, phong cách trẻ trung.',
          'price' => 350000,
          'stock' => 50,
          'category_id' => $category->id,
          'is_active' => true,
        ]);
      }

      if ($cat['name'] === 'Phụ kiện') {
        ShopProduct::create([
          'name' => 'Móc chìa khóa Mica CBH',
          'slug' => 'moc-chia-khoa-mica-cbh',
          'description' => 'Móc chìa khóa mica 2 mặt in hình các khối chuyên.',
          'price' => 250000,
          'stock' => 200,
          'category_id' => $category->id,
          'is_active' => true,
        ]);

        ShopProduct::create([
          'name' => 'Bình nước CBH Online',
          'slug' => 'binh-nuoc-cbh-online',
          'description' => 'Bình giữ nhiệt inox 500ml in tên thành viên.',
          'price' => 120000,
          'stock' => 30,
          'category_id' => $category->id,
          'is_active' => true,
        ]);
      }
    }
  }
}
