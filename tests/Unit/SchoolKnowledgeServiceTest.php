<?php

namespace Tests\Unit;

use App\Services\SchoolKnowledgeService;
use Tests\TestCase;

class SchoolKnowledgeServiceTest extends TestCase
{
    public function test_terms_drop_filler_words_and_repeats(): void
    {
        $this->assertSame(['26/3', 'hội', 'trại'], SchoolKnowledgeService::terms('Hội trại 26/3 của trường là khi nào, hội trại?'));
        $this->assertSame([], SchoolKnowledgeService::terms('trường Chuyên Biên Hòa'));
        $this->assertCount(6, SchoolKnowledgeService::terms('một hai ba bốn năm sáu bảy tám chín mười mươi'));
    }

    public function test_a_date_is_one_term_however_it_is_written(): void
    {
        $this->assertSame(['26/3'], SchoolKnowledgeService::terms('ngày 26/03'));
        $this->assertSame(['26/3'], SchoolKnowledgeService::terms('26-3-2026'));
        $this->assertSame(['20/11', 'văn', 'nghệ'], SchoolKnowledgeService::terms('văn nghệ 20.11'));
        // Not a date: kept as numbers.
        $this->assertSame(['40', '13'], SchoolKnowledgeService::terms('40/13'));

        $this->assertSame(4.0, SchoolKnowledgeService::score(['26/3'], 'RECAP 26/03', 'Sáng ngày 26 tháng 3, sân trường rộn ràng.'));
        $this->assertSame(1.0, SchoolKnowledgeService::score(['26/3'], 'Tổng duyệt văn nghệ', 'Chào mừng ngày 26-3-2026.'));
        $this->assertSame(0.0, SchoolKnowledgeService::score(['6/3'], 'RECAP 26/03', 'Ngày 26/3 và 16/3.'));
        $this->assertSame(0.0, SchoolKnowledgeService::score(['26/3'], 'Lịch thi', 'Ngày 26/30 hoặc 126/3.'));
    }

    public function test_a_title_match_outweighs_a_body_match(): void
    {
        $terms = ['hội', 'trại'];

        $this->assertSame(8.0, SchoolKnowledgeService::score($terms, 'Hội trại 26/3', 'Thông báo về hội trại năm nay.'));
        $this->assertSame(2.0, SchoolKnowledgeService::score($terms, 'Thông báo', 'Kế hoạch hội trại.'));
        // Without accents it still matches.
        $this->assertSame(6.0, SchoolKnowledgeService::score(['hoi', 'trai'], 'Hội trại 26/3', ''));
    }

    public function test_only_whole_words_match_and_typed_accents_count(): void
    {
        $this->assertSame(0.0, SchoolKnowledgeService::score(['hội'], 'Thời khóa biểu', 'Hỏi đáp'));
        $this->assertSame(3.0, SchoolKnowledgeService::score(['olympic'], 'Olympic tiếng Anh', ''));
        $this->assertSame(0.0, SchoolKnowledgeService::score(['olympic'], 'Giải bóng đá', 'Lịch thi đấu'));
    }

    public function test_a_rare_word_weighs_more_than_a_common_one(): void
    {
        $weights = ['26/3' => 2.0, 'động' => 0.5];

        $this->assertSame(6.5, SchoolKnowledgeService::score(['26/3', 'động'], 'RECAP 26/03', 'Các hoạt động.', $weights));
        $this->assertSame(0.5, SchoolKnowledgeService::score(['26/3', 'động'], 'Thông báo', 'Các hoạt động.', $weights));
    }

    public function test_the_excerpt_is_the_part_around_the_match(): void
    {
        $text = str_repeat('mở đầu ', 200) . 'lịch thi đấu bóng đá ngày 26/03 ' . str_repeat('kết thúc ', 200);

        foreach ([['bóng'], ['không có', 'bóng'], ['26/3']] as $terms) {
            $excerpt = SchoolKnowledgeService::excerpt($text, $terms);
            $this->assertStringContainsString('lịch thi đấu bóng đá ngày 26/03', $excerpt);
            $this->assertLessThanOrEqual(502, mb_strlen($excerpt));
            $this->assertStringStartsWith('…', $excerpt);
            $this->assertStringEndsWith('…', $excerpt);
        }
        $this->assertSame('ngắn', SchoolKnowledgeService::excerpt('ngắn', ['x']));
    }

    public function test_markdown_and_html_become_plain_text(): void
    {
        $this->assertSame(
            'Tiêu đề Xem tại đây nhé',
            SchoolKnowledgeService::plainText("## Tiêu đề\n\n![ảnh](https://x/y.png) Xem [tại đây](https://x) **nhé**")
        );
        $this->assertSame('Một Hai & ba', SchoolKnowledgeService::plainText('<p>Một</p><p>Hai &amp; ba</p>'));
    }
}
