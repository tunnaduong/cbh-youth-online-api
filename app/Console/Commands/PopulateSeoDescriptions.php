<?php

namespace App\Console\Commands;

use App\Models\ForumMainCategory;
use App\Models\ForumSubforum;
use Illuminate\Console\Command;

class PopulateSeoDescriptions extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'seo:populate-descriptions {--catid= : Category ID to update} {--subid= : Subforum ID to update} {--force : Overwrite existing SEO descriptions}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Populate SEO descriptions for specific category or subforum';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $catId = $this->option('catid');
    $subId = $this->option('subid');

    // Validate that only one option is provided
    if ($catId && $subId) {
      $this->error('Please specify either --catid or --subid, not both.');
      return 1;
    }

    if (!$catId && !$subId) {
      $this->error('Please specify either --catid or --subid.');
      return 1;
    }

    // Read SEO description from stdin
    $seoDescription = '';
    while (($line = fgets(STDIN)) !== false) {
      $seoDescription .= $line;
    }
    $seoDescription = trim($seoDescription);

    if (empty($seoDescription)) {
      $this->error('No SEO description provided.');
      return 1;
    }

    // Handle category update
    if ($catId) {
      $category = ForumMainCategory::find($catId);
      if (!$category) {
        $this->error("Category with ID {$catId} not found.");
        return 1;
      }

      // Check if SEO description already exists
      if (!empty($category->seo_description) && !$this->option('force')) {
        $this->error("SEO description already exists for category '{$category->name}'. Use --force to overwrite.");
        return 1;
      }

      $category->update(['seo_description' => $seoDescription]);
      $this->info("✅ SEO description saved for category '{$category->name}' (ID: {$catId})");
    }

    // Handle subforum update
    if ($subId) {
      $subforum = ForumSubforum::with('mainCategory')->find($subId);
      if (!$subforum) {
        $this->error("Subforum with ID {$subId} not found.");
        return 1;
      }

      // Check if SEO description already exists
      if (!empty($subforum->seo_description) && !$this->option('force')) {
        $this->error("SEO description already exists for subforum '{$subforum->name}'. Use --force to overwrite.");
        return 1;
      }

      $subforum->update(['seo_description' => $seoDescription]);
      $this->info("✅ SEO description saved for subforum '{$subforum->name}' (ID: {$subId}) in category '{$subforum->mainCategory->name}'");
    }

    return 0;
  }
}
