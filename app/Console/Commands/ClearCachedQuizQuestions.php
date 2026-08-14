<?php

namespace App\Console\Commands;

use App\Models\QuizQuestion;
use Illuminate\Console\Command;

/**
 * Wipes the reusable AI-generated quiz question bank (cyo_quiz_questions).
 * Every AI-generated question gets saved there (see QuizController), and it
 * doubles as a fallback source when the AI call fails or is rate-limited.
 * Deletes via a regular DELETE (not raw TRUNCATE) so the
 * cyo_quiz_question_seen rows cascade-delete cleanly per the FK constraint.
 */
class ClearCachedQuizQuestions extends Command
{
  /**
   * Tên lệnh khi gọi Artisan
   */
  protected $signature = 'quiz:clear-cache
    {--topic= : Chỉ xóa câu hỏi thuộc một chủ đề cụ thể}
    {--grade= : Chỉ xóa câu hỏi thuộc một khối lớp cụ thể (10|11|12)}
    {--difficulty= : Chỉ xóa câu hỏi thuộc một mức độ cụ thể (easy|medium|hard)}
    {--dry-run : Chỉ báo số lượng sẽ xóa, không xóa thật}
    {--force : Bỏ qua bước xác nhận}';

  /**
   * Mô tả command
   */
  protected $description = 'Xóa toàn bộ (hoặc theo bộ lọc) câu hỏi quiz đã lưu (cache) trong ngân hàng câu hỏi, buộc các quiz sau đó phải sinh lại từ AI';

  public function handle()
  {
    $query = QuizQuestion::query();

    if ($topic = $this->option('topic')) {
      $query->where('topic', $topic);
    }
    if ($grade = $this->option('grade')) {
      $query->where('grade', $grade);
    }
    if ($difficulty = $this->option('difficulty')) {
      $query->where('difficulty', $difficulty);
    }

    $count = (clone $query)->count();

    if ($count === 0) {
      $this->info('✅ Không có câu hỏi nào trong cache khớp với bộ lọc.');
      return;
    }

    if ($this->option('dry-run')) {
      $this->info("🔍 [Dry run] Sẽ xóa {$count} câu hỏi đã cache.");
      return;
    }

    if (!$this->option('force') && !$this->confirm("Bạn có chắc muốn xóa {$count} câu hỏi đã cache? Hành động này không thể hoàn tác.")) {
      $this->info('Đã hủy.');
      return;
    }

    // A regular DELETE (not raw TRUNCATE) so the DB-level ON DELETE CASCADE
    // constraint on cyo_quiz_question_seen.quiz_question_id still fires.
    $query->delete();

    $this->info("✅ Đã xóa {$count} câu hỏi đã cache.");
  }
}
