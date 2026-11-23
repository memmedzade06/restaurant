<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Hədiyyə marketi üçün bütün menyunu evopos QR menyusundan götürür:
 * kateqoriyalar, məhsullar və şəkilləri. Nəticə config/market_menu.php faylına yazılır,
 * şəkillər public/images/market/menu/ qovluğuna (kiçildilmiş) yüklənir.
 *
 * Xal qiyməti: 1 AZN = 1 xal (yuvarlaqlaşdırılır, ən azı 1 xal). Qiyməti 0 olan məhsullar düşmür.
 * Menyu dəyişəndə yenidən işə salın: php artisan market:sync-menu
 */
class SyncMarketMenu extends Command
{
    protected $signature = 'market:sync-menu {--base=https://50-qepik-elmler.evopos.az : evopos menyusunun ünvanı} {--no-images : şəkilləri yükləmə}';

    protected $description = 'Hədiyyə marketinə bütün menyunu (kateqoriyalar, məhsullar, şəkillər) evopos-dan yüklə';

    private const IMG_DIR = 'images/market/menu';
    private const IMG_MAX = 360; // şəklin ən uzun tərəfi (px)

    public function handle(): int
    {
        $base = rtrim($this->option('base'), '/');
        $http = Http::timeout(30)->acceptJson()->withUserAgent('Mozilla/5.0');

        $cats = $http->get("$base/api/menu/v1/product-categories")->throw()->json();
        $products = $http->get("$base/api/menu/v1/products")->throw()->json();

        $cats = collect($cats)->filter(fn ($c) => $c['show_in_qr_menu'] ?? true)->sortBy('position')->values();
        $products = collect($products)
            ->filter(fn ($p) => ($p['is_active'] ?? true) && ($p['show_in_qr_menu'] ?? true) && ! ($p['is_stopped'] ?? false) && (float) $p['price'] > 0)
            ->sortBy([['position', 'asc'], ['id', 'asc']]);

        if (! $this->option('no-images') && ! is_dir(public_path(self::IMG_DIR))) {
            mkdir(public_path(self::IMG_DIR), 0755, true);
        }

        $categories = [];
        foreach ($cats as $c) {
            $categories[] = [
                'key' => 'menu-' . $c['id'],
                'label' => trim($c['name']),
                'image' => $this->image($base, $c['thumbnail'] ?? null, 'cat_' . $c['id']),
            ];
        }
        $known = array_column($categories, 'key');

        $items = [];
        $bar = $this->output->createProgressBar($products->count());
        foreach ($products as $p) {
            $cat = 'menu-' . $p['product_category_id'];
            $bar->advance();
            if (! in_array($cat, $known, true)) {
                continue;
            }
            $items[] = [
                'key' => 'menu_' . $p['id'],
                'label' => trim(preg_replace('/\s+/u', ' ', $p['name'])),
                'cost' => max(1, (int) round((float) $p['price'])),
                'cat' => $cat,
                'desc' => '',
                'image' => $this->image($base, $p['thumbnail'] ?? null, (string) $p['id']),
            ];
        }
        $bar->finish();
        $this->newLine();

        // boş kateqoriyalar göstərilmir
        $used = array_unique(array_column($items, 'cat'));
        $categories = array_values(array_filter($categories, fn ($c) => in_array($c['key'], $used, true)));

        $php = "<?php\n\n// Avtomatik yaradılıb: php artisan market:sync-menu (" . now('Asia/Baku')->format('d.m.Y H:i') . ").\n"
            . "// Əl ilə dəyişməyin — növbəti sinxronizasiyada üzərinə yazılacaq. Seçilmiş hədiyyələr config/market.php-dədir.\n\n"
            . 'return ' . var_export(['categories' => $categories, 'items' => $items], true) . ";\n";
        file_put_contents(config_path('market_menu.php'), $php);

        $this->info(count($categories) . ' kateqoriya, ' . count($items) . ' məhsul yazıldı: config/market_menu.php');

        return self::SUCCESS;
    }

    /** Şəkli yüklə, kiçilt və public/ altındakı yolunu qaytar (alınmasa null) */
    private function image(string $base, ?string $path, string $name): ?string
    {
        $rel = self::IMG_DIR . "/$name.jpg";
        if ($this->option('no-images')) {
            return is_file(public_path($rel)) ? $rel : null;
        }
        if (! $path) {
            return null;
        }
        try {
            $data = Http::timeout(30)->withUserAgent('Mozilla/5.0')->get($base . $path)->throw()->body();
        } catch (\Throwable $e) {
            $this->warn("Şəkil yüklənmədi: $path");

            return is_file(public_path($rel)) ? $rel : null;
        }
        $src = @imagecreatefromstring($data); // fayl adı .webp olsa da, içi jpeg/png/webp ola bilər
        if (! $src) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $k = min(1, self::IMG_MAX / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // şəffaf fon ağ olsun
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        imagejpeg($dst, public_path($rel), 82);

        return $rel;
    }
}
